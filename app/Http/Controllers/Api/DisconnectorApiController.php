<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisconnectionOrder;
use App\Models\User;
use App\Models\ConsumerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class DisconnectorApiController extends Controller
{
    /**
     * Get disconnection assignments for a specific disconnector
     * 
     * GET /api/disconnector/assignments?disconnector_id={id}
     */
    public function getAssignments(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
        ]);

        $disconnectorId = $request->input('disconnector_id');

        try {
            // Get all active assignments for this disconnector
            $orders = DisconnectionOrder::query()
                ->where(mr_col('disconnector_id'), $disconnectorId)
                ->whereIn(mr_col('status'), ['assigned', 'in-progress'])
                ->with('consumerZone')
                ->orderBy(mr_col('disconnection_date'), 'asc')
                ->orderBy(mr_col('created_at'), 'desc')
                ->get();

            $consumerZoneColumn = DisconnectionOrder::consumerZoneIdColumn();
            $consumerIds = $orders->pluck($consumerZoneColumn)->filter()->unique()->values()->all();
            $accountNos = $orders->pluck('account_no')->filter()->unique()->values()->all();
            $agingByConsumer = $this->buildAgingBucketsFromReportLogic($consumerIds, Carbon::now());
            $agingByAccount = $this->buildAgingBucketsByAccount($accountNos, Carbon::now());

            $assignments = $orders->map(function($order) use ($agingByConsumer, $agingByAccount) {
                $row = $this->formatAssignmentForMobile($order);
                $consumerKey = (string) ($row['consumer_id'] ?? '');
                $accountKey = $this->normalizeAccountNo($row['account_no'] ?? $row['account_number'] ?? '');
                $aging = ($consumerKey !== '' && isset($agingByConsumer[$consumerKey]))
                    ? $agingByConsumer[$consumerKey]
                    : (($accountKey !== '' && isset($agingByAccount[$accountKey])) ? $agingByAccount[$accountKey] : null);

                if ($aging) {
                    $row['current'] = $aging['current'];
                    $row['CURRENT'] = $aging['current'];
                    $row['_30'] = $aging['_30'];
                    $row['_60'] = $aging['_60'];
                    $row['_90'] = $aging['_90'];
                    $row['_over90'] = $aging['_over90'];
                    $row['_OVER90'] = $aging['_over90'];
                    $row['prev_year'] = $aging['prev_year'];
                    $row['PREV_YEAR'] = $aging['prev_year'];
                    $row['total_balance'] = $aging['total_balance'];
                    $row['BALANCE'] = $aging['total_balance'];
                }

                return $row;
            });

            return response()->json([
                'success' => true,
                'assignments' => $assignments,
                'count' => count($assignments),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching assignments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all disconnection orders (for admin/dashboard)
     * 
     * GET /api/disconnector/orders?status={status}&zone={zone}
     */
    public function getOrders(Request $request)
    {
        try {
            $query = DisconnectionOrder::with(['consumerZone', 'disconnector']);

            if ($request->has('status')) {
                $query->where(mr_col('status'), $request->input('status'));
            }

            if ($request->has('zone')) {
                $query->where(mr_col('zone_code'), $request->input('zone'));
            }

            if ($request->has('disconnector_id')) {
                $query->where(mr_col('disconnector_id'), $request->input('disconnector_id'));
            }

            $orders = $query->orderBy(mr_col('created_at'), 'desc')->get();

            return response()->json([
                'success' => true,
                'orders' => $orders,
                'count' => count($orders),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching orders: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single disconnection order details
     * 
     * GET /api/disconnector/orders/{orderId}
     */
    public function getOrder($orderId)
    {
        try {
            $order = DisconnectionOrder::with(['consumerZone', 'disconnector'])
                ->findOrFail($orderId);

            return response()->json([
                'success' => true,
                'order' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update assignment status (mark as in-progress, disconnected, reconnected, etc.)
     * 
     * POST /api/disconnector/assignments/status
     * {
     *   "order_id": 1,
     *   "status": "disconnected|in-progress|reconnected",
     *   "notes": "optional notes"
     * }
     */
    public function updateAssignmentStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:disconnection_orders,id',
            'status' => 'required|in:in-progress,disconnected,reconnected,cancelled',
            'notes' => 'nullable|string',
        ]);

        try {
            $order = DisconnectionOrder::findOrFail($request->input('order_id'));
            $status = $request->input('status');
            $notes = $request->input('notes');

            switch ($status) {
                case 'in-progress':
                    $order->update([
                        'status' => 'in-progress',
                        'notes' => $notes ?? $order->notes,
                    ]);
                    break;

                case 'disconnected':
                    $order->markAsDisconnected($notes);
                    // Consumer status_code is automatically updated to 'X' in the model method
                    break;

                case 'reconnected':
                    $order->markAsReconnected($notes);
                    // Consumer status_code is automatically updated to 'A' in the model method
                    break;

                case 'cancelled':
                    $order->update([
                        'status' => 'cancelled',
                        'notes' => $notes ?? $order->notes,
                    ]);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'order' => $order->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently clear active assignments for a disconnector from server queue.
     *
     * POST /api/disconnector/assignments/clear-all
     * {
     *   "disconnector_id": 123
     * }
     */
    public function clearAllAssignments(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
        ]);

        $disconnectorId = (int) $request->input('disconnector_id');

        try {
            $affected = DisconnectionOrder::query()
                ->where(mr_col('disconnector_id'), $disconnectorId)
                ->whereIn(mr_col('status'), ['pending', 'assigned', 'in-progress'])
                ->update([
                    'status' => 'cancelled',
                    'notes' => 'Cleared by disconnector from mobile app',
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Assignments cleared successfully.',
                'cleared_count' => $affected,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing assignments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get orders cancelled due to payment (for disconnector notifications: do not disconnect these consumers)
     *
     * GET /api/disconnector/cancelled-due-to-payment?disconnector_id={id}&since={datetime}
     * since: optional, ISO datetime; default last 30 days
     */
    public function getCancelledDueToPayment(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
            'since' => 'nullable|date',
        ]);

        $disconnectorId = $request->input('disconnector_id');
        $since = $request->input('since')
            ? Carbon::parse($request->input('since'))
            : now()->subDays(30);

        try {
            $orders = DisconnectionOrder::query()
                ->where(mr_col('disconnector_id'), $disconnectorId)
                ->where(mr_col('status'), 'cancelled')
                ->where(mr_col('notes'), 'like', '%' . DisconnectionOrder::CANCELLED_DUE_TO_PAYMENT_NOTE_SUFFIX)
                ->where(mr_col('updated_at'), '>=', $since)
                ->with('consumerZone')
                ->orderBy(mr_col('updated_at'), 'desc')
                ->get()
                ->map(function ($order) {
                    return $this->formatAssignmentForMobile($order);
                });

            return response()->json([
                'success' => true,
                'cancelled_due_to_payment' => $orders,
                'count' => $orders->count(),
                'message' => 'Consumers who paid and were removed from your disconnection list.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching notifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for disconnector dashboard
     * 
     * GET /api/disconnector/stats?disconnector_id={id}
     */
    public function getStats(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
        ]);

        $disconnectorId = $request->input('disconnector_id');

        try {
            $stats = [
                'total_assigned' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)->count(),
                'pending' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->where(mr_col('status'), 'pending')->count(),
                'assigned' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->where(mr_col('status'), 'assigned')->count(),
                'in_progress' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->where(mr_col('status'), 'in-progress')->count(),
                'disconnected' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->where(mr_col('status'), 'disconnected')->count(),
                'reconnected' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->where(mr_col('status'), 'reconnected')->count(),
                'cancelled' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->where(mr_col('status'), 'cancelled')->count(),
                'total_outstanding' => DisconnectionOrder::query()->where(mr_col('disconnector_id'), $disconnectorId)
                    ->whereIn(mr_col('status'), ['assigned', 'in-progress'])
                    ->sum(mr_col('total_outstanding')),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format disconnection order for mobile app
     * Includes consumer_has_paid so the app can disable the Disconnect button when the consumer has already paid.
     */
    private function formatAssignmentForMobile($order)
    {
        $consumerHasPaid = false;
        $consumerZoneId = $order->consumer_zone_id;
        if ($consumerZoneId) {
            $consumerHasPaid = ConsumerPayment::forConsumerZone($consumerZoneId)
                ->whereNotNull('paid_at')
                ->exists();
        }

  $consumer = $order->relationLoaded('consumerZone') ? $order->consumerZone : null;
        $latitude = $consumer?->latitude;
        $longitude = $consumer?->longitude;

        return [
            'id' => $order->id,
            'account_no' => $order->account_no,
            'account_number' => $order->account_no,
            'account_name' => $order->account_name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $order->address,
            'zone_code' => $order->zone_code,
            'meter_number' => $order->meter_number,
            'card_number' => $order->card_number,
            'this_month_arrears' => (float)$order->this_month_arrears,
            'last_month_arrears' => (float)$order->last_month_arrears,
            'others_ar' => (float)$order->others_ar,
            'total_outstanding' => (float)$order->total_outstanding,
            'unpaid_months' => $order->unpaid_months,
            'oldest_unpaid_date' => $order->oldest_unpaid_date?->format('Y-m-d'),
            'latest_unpaid_date' => $order->latest_unpaid_date?->format('Y-m-d'),
            'disconnection_date' => $order->disconnection_date?->format('Y-m-d'),
            'status' => $order->status,
            'notes' => $order->notes,
            'consumer_zone_id' => $consumerZoneId,
            'consumer_id' => $consumerZoneId,
            'consumer_has_paid' => $consumerHasPaid,
            'type' => 'disconnection',
            'assignment_type' => 'disconnection',
        ];
    }

    /**
     * Build AR-aging buckets using the same FIFO-style logic used in report computations.
     * Returns keyed by consumer_zone_id (string).
     */
    private function buildAgingBucketsFromReportLogic(array $consumerIds, Carbon $asOf): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $consumerIds))));
        if (empty($ids)) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $asOfDate = $asOf->format('Y-m-d');

        $sql = "
            WITH charges AS (
                SELECT
                    cl.consumer_zone_id,
                    cz.account_no,
                    UPPER(TRIM(cl.trans)) AS trans,
                    cl.id,
                    cl.`date` AS trans_date,
                    COALESCE(cl.due_date, cl.`date`) AS aging_date,
                    cl.debit AS amount
                FROM consumer_ledgers cl
                INNER JOIN consumer_zone cz ON cz.id = cl.consumer_zone_id
                WHERE UPPER(TRIM(cl.trans)) IN ('DM', 'BILL', 'BILLING', 'PENALTY')
                  AND cl.debit > 0
                  AND cl.`date` <= ?
                  AND cl.consumer_zone_id IN ({$placeholders})
            ),
            payments AS (
                SELECT
                    cl.consumer_zone_id,
                    SUM(
                        CASE
                            WHEN UPPER(TRIM(cl.trans)) = 'CM'
                                THEN GREATEST(COALESCE(cl.credit, 0), COALESCE(-cl.debit, 0), 0)
                            ELSE GREATEST(COALESCE(cl.credit, 0), 0)
                        END
                    ) AS total_payment
                FROM consumer_ledgers cl
                WHERE UPPER(TRIM(cl.trans)) IN ('PAYMENT', 'CM')
                  AND (
                      cl.credit > 0
                      OR (
                          UPPER(TRIM(cl.trans)) = 'CM'
                          AND (COALESCE(cl.credit, 0) <> 0 OR COALESCE(cl.debit, 0) < 0)
                      )
                  )
                  AND cl.`date` <= ?
                  AND cl.consumer_zone_id IN ({$placeholders})
                GROUP BY cl.consumer_zone_id
            ),
            ordered AS (
                SELECT
                    c.*,
                    COALESCE(
                        SUM(c.amount) OVER (
                            PARTITION BY c.consumer_zone_id
                            ORDER BY
                                CASE WHEN c.trans = 'DM' THEN 0 ELSE 1 END,
                                c.aging_date,
                                c.trans_date,
                                c.id
                            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                        ),
                        0
                    ) AS prev_total,
                    SUM(c.amount) OVER (
                        PARTITION BY c.consumer_zone_id
                        ORDER BY
                            CASE WHEN c.trans = 'DM' THEN 0 ELSE 1 END,
                            c.aging_date,
                            c.trans_date,
                            c.id
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS run_total
                FROM charges c
            ),
            unpaid AS (
                SELECT
                    o.consumer_zone_id,
                    o.trans,
                    o.aging_date,
                    GREATEST(
                        0,
                        GREATEST(0, o.run_total - COALESCE(p.total_payment, 0))
                        - GREATEST(0, o.prev_total - COALESCE(p.total_payment, 0))
                    ) AS unpaid_amount
                FROM ordered o
                LEFT JOIN payments p ON p.consumer_zone_id = o.consumer_zone_id
            )
            SELECT
                consumer_zone_id,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND DATEDIFF(?, aging_date) <= 0 THEN unpaid_amount ELSE 0 END), 2) AS current,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND DATEDIFF(?, aging_date) > 0 AND PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) = 0 THEN unpaid_amount ELSE 0 END), 2) AS _30,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) = 1 THEN unpaid_amount ELSE 0 END), 2) AS _60,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) = 2 THEN unpaid_amount ELSE 0 END), 2) AS _90,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND (trans = 'DM' OR PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) >= 3) THEN unpaid_amount ELSE 0 END), 2) AS _over90,
                ROUND(0, 2) AS prev_year,
                ROUND(SUM(unpaid_amount), 2) AS total_balance
            FROM unpaid
            GROUP BY consumer_zone_id
        ";

        $bindings = array_merge(
            [$asOfDate],
            $ids,
            [$asOfDate],
            $ids,
            [$asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate]
        );

        $rows = DB::select($sql, $bindings);
        $map = [];
        foreach ($rows as $row) {
            $k = (string) ($row->consumer_zone_id ?? '');
            if ($k === '') continue;
            $map[$k] = [
                'current' => round((float) ($row->current ?? 0), 2),
                '_30' => round((float) ($row->_30 ?? 0), 2),
                '_60' => round((float) ($row->_60 ?? 0), 2),
                '_90' => round((float) ($row->_90 ?? 0), 2),
                '_over90' => round((float) ($row->_over90 ?? 0), 2),
                'prev_year' => round((float) ($row->prev_year ?? 0), 2),
                'total_balance' => round((float) ($row->total_balance ?? 0), 2),
            ];
        }

        return $map;
    }

    private function normalizeAccountNo($value): string
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '') return '';
        return preg_replace('/[^A-Z0-9]/', '', $raw);
    }

    /**
     * Fallback for assignments without consumer_id: compute/report buckets by account_no.
     */
    private function buildAgingBucketsByAccount(array $accountNos, Carbon $asOf): array
    {
        if (empty($accountNos)) return [];
        $normalized = array_values(array_unique(array_filter(array_map(fn($a) => $this->normalizeAccountNo($a), $accountNos))));
        if (empty($normalized)) return [];

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $asOfDate = $asOf->format('Y-m-d');

        $sql = "
            WITH charges AS (
                SELECT
                    cz.account_no,
                    UPPER(TRIM(cl.trans)) AS trans,
                    cl.id,
                    cl.`date` AS trans_date,
                    COALESCE(cl.due_date, cl.`date`) AS aging_date,
                    cl.debit AS amount
                FROM consumer_ledgers cl
                INNER JOIN consumer_zone cz ON cz.id = cl.consumer_zone_id
                WHERE UPPER(TRIM(cl.trans)) IN ('DM', 'BILL', 'BILLING', 'PENALTY')
                  AND cl.debit > 0
                  AND cl.`date` <= ?
                  AND REPLACE(REPLACE(REPLACE(UPPER(cz.account_no), '-', ''), ' ', ''), '.', '') IN ({$placeholders})
            ),
            payments AS (
                SELECT
                    cz.account_no,
                    SUM(
                        CASE
                            WHEN UPPER(TRIM(cl.trans)) = 'CM'
                                THEN GREATEST(COALESCE(cl.credit, 0), COALESCE(-cl.debit, 0), 0)
                            ELSE GREATEST(COALESCE(cl.credit, 0), 0)
                        END
                    ) AS total_payment
                FROM consumer_ledgers cl
                INNER JOIN consumer_zone cz ON cz.id = cl.consumer_zone_id
                WHERE UPPER(TRIM(cl.trans)) IN ('PAYMENT', 'CM')
                  AND (
                      cl.credit > 0
                      OR (
                          UPPER(TRIM(cl.trans)) = 'CM'
                          AND (COALESCE(cl.credit, 0) <> 0 OR COALESCE(cl.debit, 0) < 0)
                      )
                  )
                  AND cl.`date` <= ?
                  AND REPLACE(REPLACE(REPLACE(UPPER(cz.account_no), '-', ''), ' ', ''), '.', '') IN ({$placeholders})
                GROUP BY cz.account_no
            ),
            ordered AS (
                SELECT
                    c.*,
                    COALESCE(
                        SUM(c.amount) OVER (
                            PARTITION BY c.account_no
                            ORDER BY
                                CASE WHEN c.trans = 'DM' THEN 0 ELSE 1 END,
                                c.aging_date,
                                c.trans_date,
                                c.id
                            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                        ),
                        0
                    ) AS prev_total,
                    SUM(c.amount) OVER (
                        PARTITION BY c.account_no
                        ORDER BY
                            CASE WHEN c.trans = 'DM' THEN 0 ELSE 1 END,
                            c.aging_date,
                            c.trans_date,
                            c.id
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS run_total
                FROM charges c
            ),
            unpaid AS (
                SELECT
                    o.account_no,
                    o.trans,
                    o.aging_date,
                    GREATEST(
                        0,
                        GREATEST(0, o.run_total - COALESCE(p.total_payment, 0))
                        - GREATEST(0, o.prev_total - COALESCE(p.total_payment, 0))
                    ) AS unpaid_amount
                FROM ordered o
                LEFT JOIN payments p ON p.account_no = o.account_no
            )
            SELECT
                account_no,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND DATEDIFF(?, aging_date) <= 0 THEN unpaid_amount ELSE 0 END), 2) AS current,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND DATEDIFF(?, aging_date) > 0 AND PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) = 0 THEN unpaid_amount ELSE 0 END), 2) AS _30,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) = 1 THEN unpaid_amount ELSE 0 END), 2) AS _60,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND trans <> 'DM' AND PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) = 2 THEN unpaid_amount ELSE 0 END), 2) AS _90,
                ROUND(SUM(CASE WHEN unpaid_amount > 0 AND (trans = 'DM' OR PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(aging_date, '%Y%m')) >= 3) THEN unpaid_amount ELSE 0 END), 2) AS _over90,
                ROUND(0, 2) AS prev_year,
                ROUND(SUM(unpaid_amount), 2) AS total_balance
            FROM unpaid
            GROUP BY account_no
        ";

        $bindings = array_merge(
            [$asOfDate],
            $normalized,
            [$asOfDate],
            $normalized,
            [$asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate]
        );

        $rows = DB::select($sql, $bindings);
        $map = [];
        foreach ($rows as $row) {
            $key = $this->normalizeAccountNo($row->account_no ?? '');
            if ($key === '') continue;
            $map[$key] = [
                'current' => round((float) ($row->current ?? 0), 2),
                '_30' => round((float) ($row->_30 ?? 0), 2),
                '_60' => round((float) ($row->_60 ?? 0), 2),
                '_90' => round((float) ($row->_90 ?? 0), 2),
                '_over90' => round((float) ($row->_over90 ?? 0), 2),
                'prev_year' => round((float) ($row->prev_year ?? 0), 2),
                'total_balance' => round((float) ($row->total_balance ?? 0), 2),
            ];
        }
        return $map;
    }

    /**
     * GET /api/disconnector/ar-aging-summary
     * AR aging buckets per account (same month-based logic as AR Aging Summary report).
     */
    public function getArAgingSummary(Request $request)
    {
        try {
            $asOf = Carbon::now();
            $disconnectorId = $request->input('disconnector_id');

            $ordersQuery = DisconnectionOrder::query();
            if ($disconnectorId) {
                $ordersQuery->where(mr_col('disconnector_id'), $disconnectorId);
            } else {
                $ordersQuery->whereIn(mr_col('status'), ['assigned', 'in-progress']);
            }

            $orders = $ordersQuery->get();
            $consumerZoneColumn = DisconnectionOrder::consumerZoneIdColumn();
            $consumerIds = $orders->pluck($consumerZoneColumn)->filter()->unique()->values()->all();
            $accountNos = $orders->pluck('account_no')->filter()->unique()->values()->all();

            $byConsumer = $this->buildAgingBucketsFromReportLogic($consumerIds, $asOf);
            $byAccount = $this->buildAgingBucketsByAccount($accountNos, $asOf);

            $detailRecords = [];
            $seen = [];

            if (! empty($consumerIds)) {
                $zones = DB::table(mr_col('consumer_zone'))
                    ->whereIn(mr_col('id'), $consumerIds)
                    ->select('id', 'account_no', 'account_name', 'zone_code')
                    ->get();

                foreach ($zones as $zone) {
                    $key = (string) $zone->id;
                    $aging = $byConsumer[$key] ?? null;
                    if (! $aging) {
                        continue;
                    }
                    $seen[$key] = true;
                    $detailRecords[] = $this->formatAgingDetailRow($zone->account_no, $zone->account_name, $zone->id, $aging);
                }
            }

            foreach ($byAccount as $normAccount => $aging) {
                $zone = DB::table(mr_col('consumer_zone'))
                    ->whereRaw(
                        "REPLACE(REPLACE(REPLACE(UPPER(account_no), '-', ''), ' ', ''), '.', '') = ?",
                        [$normAccount]
                    )
                    ->select('id', 'account_no', 'account_name')
                    ->first();

                $consumerId = $zone->id ?? null;
                if ($consumerId && isset($seen[(string) $consumerId])) {
                    continue;
                }

                $detailRecords[] = $this->formatAgingDetailRow(
                    $zone->account_no ?? $normAccount,
                    $zone->account_name ?? '',
                    $consumerId,
                    $aging
                );
            }

            return response()->json([
                'success' => true,
                'data' => $detailRecords,
                'detailRecords' => $detailRecords,
                'count' => count($detailRecords),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching AR aging summary: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    private function formatAgingDetailRow($accountNo, $accountName, $consumerZoneId, array $aging): array
    {
        $balance = round((float) ($aging['total_balance'] ?? 0), 2);

        return [
            'consumer_zone_id' => $consumerZoneId,
            'consumer_id' => $consumerZoneId,
            'account_no' => $accountNo,
            'account_number' => $accountNo,
            'account_name' => $accountName,
            'current' => $aging['current'],
            'CURRENT' => $aging['current'],
            '_30' => $aging['_30'],
            '_60' => $aging['_60'],
            '_90' => $aging['_90'],
            '_over90' => $aging['_over90'],
            '_OVER90' => $aging['_over90'],
            'prev_year' => $aging['prev_year'],
            'PREV_YEAR' => $aging['prev_year'],
            'balance' => $balance,
            'total_balance' => $balance,
            'BALANCE' => $balance,
        ];
    }
}
