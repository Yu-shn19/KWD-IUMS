<?php

namespace App\Http\Controllers;

use App\Models\ConsumerLedger;
use App\Models\ConsumerZone;
use App\Models\DisconnectionOrder;
use App\Models\MeterReadingSchedule;
use App\Models\User;
use App\Services\DisconnectionBulkContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class DisconnectionController extends Controller
{
    private static function disconnectionOrderIdColumn(): string
    {
        return (new DisconnectionOrder)->getKeyName();
    }

    private static function consumerZoneIdColumn(): string
    {
        return (new ConsumerZone)->getKeyName();
    }

    private static function consumerLedgerIdColumn(): string
    {
        return (new ConsumerLedger)->getKeyName();
    }

    private static function meterReadingScheduleIdColumn(): string
    {
        return (new MeterReadingSchedule)->getKeyName();
    }

    private function ledgerQueryForConsumers(iterable $consumerIds)
    {
        return ConsumerLedger::query()->whereIn(mr_col('consumer_zone_id'), $consumerIds);
    }

    private function getOrdersTabData(Request $request): array
    {
        $ordersZone = (string) $request->get('orders_zone', '');
        $ordersStatusFromRequest = (string) $request->get('orders_status', '');
        $ordersDisconnector = (string) $request->get('orders_disconnector', '');
        $ordersDateSaved = (string) $request->get('orders_date_saved', '');
        $viewTab = (string) $request->get('view_tab', 'candidates');
        if (! in_array($viewTab, ['candidates', 'orders', 'disconnected'], true)) {
            $viewTab = 'candidates';
        }

        $doZoneCode = mr_col('zone_code');
        $doCreatedAt = mr_col('created_at');
        $doStatus = mr_col('status');

        $applySharedOrderFilters = function ($query) use ($ordersZone, $ordersDisconnector, $ordersDateSaved, $doZoneCode, $doCreatedAt): void {
            if ($ordersZone !== '') {
                $query->where($doZoneCode, $ordersZone);
            }
            if ($ordersDisconnector !== '') {
                $query->where('disconnector_id', (int) $ordersDisconnector);
            }
            if ($ordersDateSaved !== '') {
                try {
                    $query->whereDate($doCreatedAt, Carbon::parse($ordersDateSaved)->format('Y-m-d'));
                } catch (\Throwable $e) {
                    // Ignore invalid date input and keep default results.
                }
            }
        };

        // Saved / Assigned: use status dropdown from the orders filter. When the page was loaded for the
        // Disconnected tab, the request repeats orders_status=disconnected (hidden field); that must not
        // narrow this list or both tabs show the same rows when switching without a full reload.
        $statusForSavedAssignedTab = $ordersStatusFromRequest;
        if ($viewTab === 'disconnected') {
            $statusForSavedAssignedTab = '';
        }

        $ordersQuery = DisconnectionOrder::query()
            ->with(['disconnector'])
            ->orderByDesc($doCreatedAt);
        $applySharedOrderFilters($ordersQuery);
        if ($statusForSavedAssignedTab !== '') {
            $ordersQuery->where($doStatus, $statusForSavedAssignedTab);
        }
        $ordersList = $ordersQuery->limit(500)->get();

        // Disconnected Only tab: always disconnected rows, same zone / disconnector / date filters.
        $disconnectedQuery = DisconnectionOrder::query()
            ->with(['disconnector'])
            ->where($doStatus, 'disconnected')
            ->orderByDesc($doCreatedAt);
        $applySharedOrderFilters($disconnectedQuery);
        $disconnectedOrdersList = $disconnectedQuery->limit(500)->get();

        $ordersZoneOptions = DisconnectionOrder::query()
            ->whereNotNull($doZoneCode)
            ->where($doZoneCode, '!=', '')
            ->distinct()
            ->orderBy($doZoneCode)
            ->pluck($doZoneCode);

        return [
            'ordersList' => $ordersList,
            'disconnectedOrdersList' => $disconnectedOrdersList,
            'ordersZone' => $ordersZone,
            'ordersStatus' => $viewTab === 'disconnected' ? '' : $ordersStatusFromRequest,
            'ordersDisconnector' => $ordersDisconnector,
            'ordersDateSaved' => $ordersDateSaved,
            'ordersZoneOptions' => $ordersZoneOptions,
            'viewTab' => $viewTab,
        ];
    }

    /**
     * Display list of consumers eligible for disconnection by zone
     */
    public function index(Request $request)
    {
        $zone = $request->get('zone');
        $billingMonth = $request->get('billing_month'); // Format: YYYY-MM
        $billingDate = $request->get('billing_date');
        $filterType = $request->get('filter_type', 'disconnection_date'); // disconnection_date | 2_consecutive | 3_consecutive
        $hasAnyFilter = ! empty($zone) || ! empty($billingMonth) || ! empty($billingDate);

        // Keep the page lightweight on first load: show filters only until user applies at least one filter.
        if (! $hasAnyFilter) {
            $czZoneCode = mr_col('zone_code');
            $userRole = mr_col('role');
            $userName = mr_col('name');

            $zones = ConsumerZone::select($czZoneCode)
                ->distinct()
                ->whereNotNull($czZoneCode)
                ->orderBy($czZoneCode)
                ->pluck($czZoneCode);

            $disconnectors = User::query()
                ->where($userRole, 'disconnector')
                ->orderBy($userName)
                ->get();

            $consumersByZone = collect();
            $totalConsumers = 0;
            $totalOutstanding = 0;
            $defaultDisconnectionDate = Carbon::today()->addDays(7)->format('Y-m-d');

            return view('disconnection.index', array_merge(compact(
                'consumersByZone',
                'zones',
                'zone',
                'filterType',
                'disconnectors',
                'totalConsumers',
                'totalOutstanding',
                'billingDate',
                'billingMonth',
                'defaultDisconnectionDate'
            ), $this->getOrdersTabData($request)));
        }

        // Use different filter based on filter_type parameter
        if (in_array($filterType, ['2_consecutive', '3_consecutive'], true)) {
            $requiredMonths = $filterType === '2_consecutive' ? 2 : 3;

            return $this->getConsumersWithConsecutiveUnpaidMonths($request, $requiredMonths);
        } else {
            // Default: use disconnection date filter
            // Priority: billing_month > billing_date
            $billingFilter = $billingMonth ?: $billingDate;
            $isMonthFilter = ! empty($billingMonth);
            $consumers = $this->getConsumersForDisconnection($zone, $billingFilter, $isMonthFilter);

            $totalOutstandingKey = mr_col('total_outstanding');
            $czZoneCode = mr_col('zone_code');
            $userRole = mr_col('role');
            $userName = mr_col('name');
            $mrsDisconnectionDate = mr_col('disconnection_date');

            // Group by zone
            $consumersByZone = $consumers->groupBy($czZoneCode);

            // Get all zones for filter
            $zones = ConsumerZone::select($czZoneCode)
                ->distinct()
                ->whereNotNull($czZoneCode)
                ->orderBy($czZoneCode)
                ->pluck($czZoneCode);

            // Get disconnectors for dropdown
            $disconnectors = User::query()
                ->where($userRole, 'disconnector')
                ->orderBy($userName)
                ->get();

            // Calculate totals
            $totalConsumers = $consumers->count();
            $totalOutstanding = $consumers->sum($totalOutstandingKey);

            // Default disconnection date for the form: from schedule when billing month/date is selected
            $defaultDisconnectionDate = null;
            if ($billingFilter) {
                $querySchedule = function ($withZone) use ($zone, $billingFilter, $isMonthFilter, $mrsDisconnectionDate) {
                    $q = MeterReadingSchedule::query()->whereNotNull($mrsDisconnectionDate);
                    if ($withZone && $zone) {
                        $q->forZoneCode($zone);
                    }
                    if ($isMonthFilter) {
                        $monthCarbon = Carbon::createFromFormat('Y-m', $billingFilter)->startOfMonth();
                        $q->where(function ($query) use ($monthCarbon) {
                            $query->whereYear('bill_month', $monthCarbon->year)
                                ->whereMonth('bill_month', $monthCarbon->month);
                        });
                    } else {
                        $billingDateCarbon = Carbon::parse($billingFilter);
                        $q->where(function ($query) use ($billingDateCarbon) {
                            $query->whereDate('bill_date', $billingDateCarbon)
                                ->orWhereDate('bill_month', $billingDateCarbon->format('Y-m-01'));
                        });
                    }

                    return $q->orderBy($mrsDisconnectionDate)->first();
                };
                $schedule = $querySchedule(true);
                if (! $schedule && $zone) {
                    $schedule = $querySchedule(false); // fallback: any zone for this billing month
                }
                if ($schedule && $schedule->disconnection_date) {
                    $defaultDisconnectionDate = Carbon::parse($schedule->disconnection_date)->format('Y-m-d');
                }
                // Fallback: use first consumer's disconnection date from the list (from their schedule)
                if (! $defaultDisconnectionDate && $consumers->isNotEmpty()) {
                    $first = $consumers->first();
                    if (! empty($first->disconnection_date)) {
                        $defaultDisconnectionDate = Carbon::parse($first->disconnection_date)->format('Y-m-d');
                    }
                }
            }
            if (! $defaultDisconnectionDate) {
                $defaultDisconnectionDate = Carbon::today()->addDays(7)->format('Y-m-d');
            }

            return view('disconnection.index', array_merge(
                compact('consumersByZone', 'zones', 'zone', 'filterType', 'disconnectors', 'totalConsumers', 'totalOutstanding', 'billingDate', 'billingMonth', 'defaultDisconnectionDate'),
                $this->getOrdersTabData($request)
            ));
        }
    }

    /**
     * Get default disconnection date from meter_reading_schedules for the given billing month/date and zone (for use in view).
     */
    private function getDefaultDisconnectionDateForBillingFilter(Request $request): string
    {
        $zone = $request->get('zone');
        $billingMonth = $request->get('billing_month');
        $billingDate = $request->get('billing_date');
        $billingFilter = $billingMonth ?: $billingDate;
        $isMonthFilter = ! empty($billingMonth);
        if (! $billingFilter) {
            return Carbon::today()->addDays(7)->format('Y-m-d');
        }
        $querySchedule = function ($withZone) use ($zone, $billingFilter, $isMonthFilter) {
            $mrsDisconnectionDate = mr_col('disconnection_date');
            $q = MeterReadingSchedule::query()->whereNotNull($mrsDisconnectionDate);
            if ($withZone && $zone) {
                $q->forZoneCode($zone);
            }
            if ($isMonthFilter) {
                $monthCarbon = Carbon::createFromFormat('Y-m', $billingFilter)->startOfMonth();
                $q->where(function ($query) use ($monthCarbon) {
                    $query->whereYear('bill_month', $monthCarbon->year)
                        ->whereMonth('bill_month', $monthCarbon->month);
                });
            } else {
                $billingDateCarbon = Carbon::parse($billingFilter);
                $q->where(function ($query) use ($billingDateCarbon) {
                    $query->whereDate('bill_date', $billingDateCarbon)
                        ->orWhereDate('bill_month', $billingDateCarbon->format('Y-m-01'));
                });
            }

            return $q->orderBy($mrsDisconnectionDate)->first();
        };
        $schedule = $querySchedule(true);
        if (! $schedule && $zone) {
            $schedule = $querySchedule(false);
        }
        if ($schedule && $schedule->disconnection_date) {
            return Carbon::parse($schedule->disconnection_date)->format('Y-m-d');
        }

        return Carbon::today()->addDays(7)->format('Y-m-d');
    }

    /**
     * Generate disconnection notice for selected consumers
     */
    public function generateNotice(Request $request)
    {
        $request->validate([
            'consumer_ids' => 'required|array',
            'consumer_ids.*' => 'exists:consumer_zone,id',
            'disconnection_date' => 'required|date',
            'list_billing_month' => 'nullable|string',
            'list_billing_date' => 'nullable|date',
            'financials' => 'nullable|array',
            'financials.*.this_month_arrears' => 'nullable|numeric|min:0',
            'financials.*.last_month_arrears' => 'nullable|numeric|min:0',
            'financials.*.others_ar' => 'nullable|numeric|min:0',
            'financials.*.total_outstanding' => 'nullable|numeric|min:0',
        ]);

        $disconnectionDate = Carbon::parse($request->input('disconnection_date'));
        $listBillingMonth = $request->input('list_billing_month') ?: null;
        $listBillingDate = $request->input('list_billing_date') ?: null;
        $financialsInput = $request->input('financials', []);

        $consumersWithOutstanding = $this->buildConsumersForDisconnectionNotice(
            $request->input('consumer_ids'),
            $listBillingMonth,
            $listBillingDate,
            is_array($financialsInput) ? $financialsInput : []
        );

        return view('disconnection.notice', compact(
            'consumersWithOutstanding',
            'disconnectionDate',
            'listBillingMonth',
            'listBillingDate'
        ));
    }

    /**
     * Get consumers who have passed disconnection date from meter_reading_schedules and haven't paid
     */
    public function getConsumersForDisconnection(?string $zone = null, ?string $billingFilter = null, bool $isMonthFilter = false): Collection
    {
        $ledgerCutoffDate = $this->resolveLedgerCutoffDate($billingFilter, $isMonthFilter);
        $candidateConsumers = $this->queryDisconnectionCandidateConsumers($zone);

        $accountNos = $candidateConsumers->pluck('account_no')->filter()->unique()->values();
        $consumerIds = $candidateConsumers->pluck('id')->filter()->unique()->values();

        if ($accountNos->isEmpty()) {
            return collect();
        }

        $bulkContext = $this->loadDisconnectionBulkContext(
            $consumerIds,
            $accountNos,
            Carbon::today(),
            $billingFilter,
            $isMonthFilter,
            $ledgerCutoffDate
        );

        return $this->buildEligibleDisconnectionConsumers($accountNos, $bulkContext);
    }

    private function resolveLedgerCutoffDate(?string $billingFilter, bool $isMonthFilter): ?string
    {
        if (empty($billingFilter)) {
            return null;
        }

        try {
            return $isMonthFilter
                ? Carbon::createFromFormat('Y-m', $billingFilter)->endOfMonth()->format('Y-m-d')
                : Carbon::parse($billingFilter)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * AR-style candidate source: start from consumer_zone (not schedule-gated).
     */
    private function queryDisconnectionCandidateConsumers(?string $zone): Collection
    {
        $query = ConsumerZone::query()
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhereNotIn(DB::raw('UPPER(TRIM(status_code))'), ['X', 'D', 'DISCONNECTED']);
            });

        if ($zone) {
            $query->where(mr_col('zone_code'), $zone);
        }

        return $query->select(mr_col('id'), mr_col('account_no'))->get();
    }

    private function loadDisconnectionBulkContext(
        Collection $consumerIds,
        Collection $accountNos,
        Carbon $today,
        ?string $billingFilter,
        bool $isMonthFilter,
        ?string $ledgerCutoffDate
    ): DisconnectionBulkContext {
        $accountNoColumn = mr_col('account_no');
        $consumers = ConsumerZone::query()->whereIn($accountNoColumn, $accountNos)->get()->keyBy($accountNoColumn);
        $latestBalances = $this->calculateBalancesBulk($consumerIds, $ledgerCutoffDate);
        $consumersById = $this->consumersByIdForZoneIds($consumerIds);

        $clConsumerZoneId = mr_col('consumer_zone_id');
        $mrsDueDate = mr_col('due_date');
        $clTrans = mr_col('trans');
        $clDate = mr_col('date');
        $clScheduleId = mr_col('schedule_id');

        $schedulesQuery = MeterReadingSchedule::query()
            ->whereIn($clConsumerZoneId, $consumerIds)
            ->where($mrsDueDate, '<=', $today)
            ->whereNotNull($mrsDueDate);

        if ($billingFilter) {
            $this->applyScheduleBillingFilter($schedulesQuery, $billingFilter, $isMonthFilter);
        }

        $allSchedules = $schedulesQuery->get()->groupBy(fn ($schedule) => $this->accountNoForScheduleConsumer($schedule, $consumersById));

        $allBillingsQuery = $this->ledgerQueryForConsumers($consumerIds)
            ->whereIn($clTrans, ['BILLING', 'BILL'])
            ->whereNotNull($mrsDueDate)
            ->where($mrsDueDate, '<=', $today);
        ConsumerLedgerController::applyVisibleLedgerScope($allBillingsQuery);
        $allBillings = $allBillingsQuery
            ->select($clConsumerZoneId, $clScheduleId, $mrsDueDate, mr_col('debit'), mr_col('credit'), mr_col('balance'), $clDate)
            ->orderBy($clConsumerZoneId)
            ->orderBy($mrsDueDate, 'asc')
            ->when($ledgerCutoffDate, function ($query) use ($ledgerCutoffDate, $clDate) {
                $query->whereDate($clDate, '<=', $ledgerCutoffDate);
            })
            ->get()
            ->groupBy($clConsumerZoneId);

        $allPaymentsQuery = $this->ledgerQueryForConsumers($consumerIds)
            ->where($clTrans, 'PAYMENT')
            ->whereNotNull($clScheduleId);
        ConsumerLedgerController::applyVisibleLedgerScope($allPaymentsQuery);
        $allPayments = $allPaymentsQuery
            ->select($clConsumerZoneId, $clScheduleId, $clDate, mr_col('credit'))
            ->when($ledgerCutoffDate, function ($query) use ($ledgerCutoffDate, $clDate) {
                $query->whereDate($clDate, '<=', $ledgerCutoffDate);
            })
            ->get()
            ->groupBy($clConsumerZoneId)
            ->map(function ($payments) use ($clScheduleId) {
                return $payments->pluck($clScheduleId)->unique()->toArray();
            });

        $penaltyLedgersQuery = $this->ledgerQueryForConsumers($consumerIds)
            ->where($clTrans, 'PENALTY');
        ConsumerLedgerController::applyVisibleLedgerScope($penaltyLedgersQuery);
        $penaltyLedgersByConsumer = $penaltyLedgersQuery
            ->when($ledgerCutoffDate, function ($query) use ($ledgerCutoffDate, $clDate) {
                $query->whereDate($clDate, '<=', $ledgerCutoffDate);
            })
            ->get()
            ->groupBy($clConsumerZoneId);

        return new DisconnectionBulkContext(
            consumers: $consumers,
            latestBalances: $latestBalances,
            allSchedules: $allSchedules,
            latestScheduleReadingsByAccount: $this->latestScheduleReadingsByAccount($consumerIds, $consumersById, $billingFilter, $isMonthFilter),
            latestCurrentBillsByAccount: $this->latestCurrentBillsByAccount($consumerIds, $consumersById),
            allBillings: $allBillings,
            allPayments: $allPayments,
            penaltyLedgersByConsumer: $penaltyLedgersByConsumer,
            arAgingBucketsByConsumer: $this->computeAraAgingBucketsBulk($consumerIds, $ledgerCutoffDate),
        );
    }

    private function buildEligibleDisconnectionConsumers(Collection $accountNos, DisconnectionBulkContext $context): Collection
    {
        $eligibleConsumers = collect();

        foreach ($accountNos as $accountNo) {
            $consumer = $this->enrichDisconnectionConsumer($accountNo, $context);

            if ($consumer !== null) {
                $eligibleConsumers->push($consumer);
            }
        }

        return $eligibleConsumers;
    }

    private function enrichDisconnectionConsumer(string $accountNo, DisconnectionBulkContext $context): ?ConsumerZone
    {
        $consumer = $context->consumers->get($accountNo);

        if (! $consumer) {
            return null;
        }

        $currentBalance = $context->latestBalances->get($consumer->id, 0);
        $consumerBillings = $context->allBillings->get($consumer->id, collect());
        $unpaidDatesData = $this->calculateUnpaidDates(
            $consumer->id,
            $consumerBillings,
            $context->allPayments->get($consumer->id, [])
        );
        $passedSchedules = $context->allSchedules->get($accountNo, collect());
        $scheduleDueDateKey = mr_col('due_date');
        $earliestDisconnectionDate = $passedSchedules->isNotEmpty() ? $passedSchedules->min($scheduleDueDateKey) : null;
        $latestDisconnectionDate = $passedSchedules->isNotEmpty() ? $passedSchedules->max($scheduleDueDateKey) : null;

        $consumer->unpaid_months = $this->adjustUnpaidMonthsForPriorArrears(
            (int) $unpaidDatesData['unpaid_count'],
            $this->computeLastMonthArrearsAmount(
                $currentBalance,
                $consumerBillings,
                $context->penaltyLedgersByConsumer->get($consumer->id, collect())
            )
        );

        $agingBuckets = $context->arAgingBucketsByConsumer->get($consumer->id, [
            'current' => 0.0,
            'days_30' => 0.0,
            'days_60' => 0.0,
            'days_90' => 0.0,
            'over_90' => 0.0,
        ]);

        $hasLastMonthCyBucket = round((float) ($agingBuckets['days_60'] ?? 0) + (float) ($agingBuckets['days_90'] ?? 0), 2) > 0.01;
        if (! $hasLastMonthCyBucket) {
            return null;
        }

        $consumer->ledger_balance = round((float) $currentBalance, 2);
        $consumer->total_outstanding = $this->sumIndexAgingOutstanding($agingBuckets);
        $consumer->notice_soa_total = (float) $consumer->total_outstanding;
        $consumer->oldest_unpaid_date = $unpaidDatesData['oldest_unpaid_date'];
        $consumer->latest_unpaid_date = $unpaidDatesData['latest_unpaid_date'];
        $consumer->disconnection_date = $earliestDisconnectionDate;
        $consumer->latest_disconnection_date = $latestDisconnectionDate;
        $consumer->passed_schedules_count = $passedSchedules->count();
        $consumer->last_reading = (float) ($context->latestScheduleReadingsByAccount->get($accountNo, 0) ?? 0);
        $rawCurrentBill = (float) ($context->latestCurrentBillsByAccount->get($accountNo, 0) ?? 0);
        $consumer->current_bill = $rawCurrentBill;
        $consumer->current_bill_with_maintenance = round($rawCurrentBill + 20, 2);
        $consumer->aging_current = $agingBuckets['current'];
        $consumer->aging_30_days = $agingBuckets['days_30'];
        $consumer->aging_60_days = $agingBuckets['days_60'];
        $consumer->aging_90_days = $agingBuckets['days_90'];
        $consumer->aging_over_90 = $agingBuckets['over_90'];

        return $consumer;
    }

    /**
     * Account Ledger footer balance (bulk) — same rules as F10 Account Ledger / getLedger().
     */
    private function calculateBalancesBulk(iterable $consumerIds, ?string $ledgerCutoffDate = null): Collection
    {
        return ConsumerLedgerController::computeAccountLedgerFooterBalancesBulk($consumerIds, $ledgerCutoffDate);
    }

    /**
     * Calculate unpaid dates based on BILL/BILLING and PAYMENT records in consumer_ledger.
     * This is used to determine oldest and latest unpaid dates.
     * Note: Total outstanding on the list is the sum of index aging columns (see sumIndexAgingOutstanding()).
     */
    private function calculateUnpaidDates(int $consumerId, Collection $billings, array $paidScheduleIds = []): array
    {
        $unpaidDates = [];

        if ($billings->isEmpty()) {
            return [
                'unpaid_count' => 0,
                'oldest_unpaid_date' => null,
                'latest_unpaid_date' => null,
            ];
        }

        foreach ($billings as $billing) {
            $scheduleId = $billing->schedule_id;
            $debit = (float) ($billing->debit ?? 0);
            $credit = (float) ($billing->credit ?? 0);
            $dueDate = $billing->due_date;
            $dueDateCarbon = is_string($dueDate) ? Carbon::parse($dueDate) : $dueDate;

            // Check if this billing has been paid
            // A billing is considered unpaid if:
            // 1. No PAYMENT record exists with the same schedule_id (when schedule_id exists), AND
            // 2. The billing still has outstanding amount (debit > credit)
            $isPaid = false;

            // First check: Payment in consumer_ledgers by schedule_id
            if ($scheduleId && in_array($scheduleId, $paidScheduleIds)) {
                // Payment exists for this schedule_id, check if it fully covers the bill
                // If debit <= credit, the bill is fully paid
                if ($debit <= $credit) {
                    $isPaid = true;
                }
            }

            // Second check: If no schedule_id, check if debit <= credit (might be paid via other means)
            if (! $isPaid && ! $scheduleId) {
                if ($debit <= $credit) {
                    $isPaid = true;
                }
            }

            if (! $isPaid) {
                // Collect unpaid dates
                if ($dueDate) {
                    $unpaidDates[] = $dueDateCarbon;
                }
            }
        }

        // Sort unpaid dates
        $unpaidDates = collect($unpaidDates)->sort()->values();

        return [
            'unpaid_count' => count($unpaidDates),
            'oldest_unpaid_date' => $unpaidDates->isNotEmpty() ? $unpaidDates->first() : null,
            'latest_unpaid_date' => $unpaidDates->isNotEmpty() ? $unpaidDates->last() : null,
        ];
    }

    private function getLatestReadingFromSchedules(?Collection $schedules): float
    {
        if (! $schedules || $schedules->isEmpty()) {
            return 0.0;
        }

        $latestWithReading = $schedules->filter(function ($schedule) {
            return isset($schedule->current_reading) && $schedule->current_reading !== null;
        })->sortByDesc(function ($schedule) {
            $dateRef = $schedule->due_date ?? $schedule->bill_date ?? null;

            return $dateRef ? Carbon::parse($dateRef)->timestamp : 0;
        })->first();

        return $latestWithReading ? (float) $latestWithReading->current_reading : 0.0;
    }

    /**
     * Print aging — same split as generateNotice / notice preview:
     * - CURRENT = This Month/Arrears (latest BILLING debit + PENALTY on same due date)
     * - 30 DAYS = Last Month/Arrears CY = total balance minus that (see computeLastMonthArrearsAmount)
     *
     * @param  mixed  $billings  BILL/BILLING ledger rows (same basis as list queries)
     */
    private function computeDisconnectionAgingBreakdown(Collection $billings, array $paidScheduleIds, float $currentBalance, ?Collection $penalties = null, ?Collection $paymentCredits = null): array
    {
        $penalties = $penalties ?? collect();
        $paymentCredits = $paymentCredits ?? collect();
        $asOf = Carbon::today();

        // Build charge rows similar to AR aging summary:
        // charges are BILL/BILLING + PENALTY debits, aged by due_date (fallback date).
        $charges = collect();
        foreach ($billings as $billing) {
            $amount = (float) ($billing->debit ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $agingDateRaw = $billing->due_date ?? $billing->date ?? null;
            if (! $agingDateRaw) {
                continue;
            }
            $agingDate = is_string($agingDateRaw) ? Carbon::parse($agingDateRaw) : $agingDateRaw;
            $transDateRaw = $billing->date ?? $agingDateRaw;
            $transDate = is_string($transDateRaw) ? Carbon::parse($transDateRaw) : $transDateRaw;
            $charges->push([
                'trans' => strtoupper(trim((string) ($billing->trans ?? 'BILLING'))),
                'amount' => $amount,
                'aging_date' => $agingDate,
                'trans_date' => $transDate,
                'id' => (int) ($billing->id ?? 0),
            ]);
        }
        foreach ($penalties as $penalty) {
            $amount = (float) ($penalty->debit ?? $penalty->penalty ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $agingDateRaw = $penalty->due_date ?? $penalty->date ?? null;
            if (! $agingDateRaw) {
                continue;
            }
            $agingDate = is_string($agingDateRaw) ? Carbon::parse($agingDateRaw) : $agingDateRaw;
            $transDateRaw = $penalty->date ?? $agingDateRaw;
            $transDate = is_string($transDateRaw) ? Carbon::parse($transDateRaw) : $transDateRaw;
            $charges->push([
                'trans' => 'PENALTY',
                'amount' => $amount,
                'aging_date' => $agingDate,
                'trans_date' => $transDate,
                'id' => (int) ($penalty->id ?? 0),
            ]);
        }

        if ($charges->isEmpty()) {
            return $this->roundAgingBreakdown([
                'current' => 0.0,
                'days_30' => 0.0,
                'days_60' => 0.0,
                'days_90' => 0.0,
                'over_90' => 0.0,
            ]);
        }

        $totalPayment = (float) $paymentCredits->sum(function ($entry) {
            $trans = strtoupper(trim((string) ($entry->trans ?? '')));
            $credit = (float) ($entry->credit ?? 0);
            $debit = (float) ($entry->debit ?? 0);
            if ($trans === 'CM') {
                return max($credit, max(-$debit, 0));
            }

            return max($credit, 0);
        });

        $orderedCharges = $charges->sort(function ($a, $b) {
            // Match AR order: DM first, then aging_date, trans_date, id.
            $aDm = $a['trans'] === 'DM' ? 0 : 1;
            $bDm = $b['trans'] === 'DM' ? 0 : 1;
            if ($aDm !== $bDm) {
                return $aDm <=> $bDm;
            }
            $agingCmp = $a['aging_date']->timestamp <=> $b['aging_date']->timestamp;
            if ($agingCmp !== 0) {
                return $agingCmp;
            }
            $dateCmp = $a['trans_date']->timestamp <=> $b['trans_date']->timestamp;
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        })->values();

        $breakdown = [
            'current' => 0.0,
            'days_30' => 0.0,
            'days_60' => 0.0,
            'days_90' => 0.0,
            'over_90' => 0.0,
        ];

        $runningTotal = 0.0;
        foreach ($orderedCharges as $charge) {
            $prevTotal = $runningTotal;
            $runningTotal += (float) $charge['amount'];

            $unpaidAmount = max(
                0.0,
                max(0.0, $runningTotal - $totalPayment) - max(0.0, $prevTotal - $totalPayment)
            );
            if ($unpaidAmount <= 0.0) {
                continue;
            }

            $daysDiff = $charge['aging_date']->diffInDays($asOf, false);
            if ($charge['trans'] === 'DM' || $daysDiff > 90) {
                $breakdown['over_90'] += $unpaidAmount;
            } elseif ($daysDiff <= 0) {
                $breakdown['current'] += $unpaidAmount;
            } elseif ($daysDiff <= 30) {
                $breakdown['days_30'] += $unpaidAmount;
            } elseif ($daysDiff <= 60) {
                $breakdown['days_60'] += $unpaidAmount;
            } else {
                $breakdown['days_90'] += $unpaidAmount;
            }
        }

        return $this->roundAgingBreakdown($breakdown);
    }

    private function roundAgingBreakdown(array $breakdown): array
    {
        foreach ($breakdown as $key => $value) {
            $breakdown[$key] = round(max(0.0, (float) $value), 2);
        }

        return $breakdown;
    }

    /**
     * Compute AR aging buckets using the same FIFO SQL basis as AR Aging Summary report.
     */
    private function computeAraAgingBucketsBulk(iterable $consumerIds, ?string $cutoffDate = null): Collection
    {
        $consumerIds = collect($consumerIds)->filter()->unique()->values();
        if ($consumerIds->isEmpty()) {
            return collect();
        }

        $asOfDate = $cutoffDate ?: Carbon::today()->format('Y-m-d');
        $billingCutoffDate = $cutoffDate ?: Carbon::today()->format('Y-m-d');
        $paymentCutoffDate = $cutoffDate ?: Carbon::today()->format('Y-m-d');

        $idPlaceholders = implode(',', array_fill(0, $consumerIds->count(), '?'));
        $visibleLedgerSql = "
                  AND (
                      cl.billing_adjustment_id IS NULL
                      OR EXISTS (
                          SELECT 1 FROM billing_adjustments ba
                          WHERE ba.id = cl.billing_adjustment_id
                            AND ba.status = 'Approved'
                      )
                  )";

        $agingSql = "
            WITH charges AS (
                SELECT
                    cl.consumer_zone_id,
                    UPPER(TRIM(cl.trans)) AS trans,
                    cl.id,
                    cl.`date` AS trans_date,
                    COALESCE(cl.due_date, cl.`date`) AS aging_date,
                    cl.debit AS amount
                FROM consumer_ledgers cl
                WHERE cl.consumer_zone_id IN ({$idPlaceholders})
                  AND UPPER(TRIM(cl.trans)) IN ('DM', 'BILLING', 'PENALTY')
                  AND cl.debit > 0
                  AND cl.`date` <= ?
                  {$visibleLedgerSql}
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
                WHERE cl.consumer_zone_id IN ({$idPlaceholders})
                  AND UPPER(TRIM(cl.trans)) IN ('PAYMENT', 'CM')
                  AND (
                      cl.credit > 0
                      OR (
                          UPPER(TRIM(cl.trans)) = 'CM'
                          AND (COALESCE(cl.credit, 0) <> 0 OR COALESCE(cl.debit, 0) < 0)
                      )
                  )
                  AND cl.`date` <= ?
                  {$visibleLedgerSql}
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
                ROUND(SUM(
                    CASE
                        WHEN unpaid_amount > 0
                         AND trans <> 'DM'
                         AND DATEDIFF(?, aging_date) <= 0
                        THEN unpaid_amount ELSE 0
                    END
                ), 2) AS current,
                ROUND(SUM(
                    CASE
                        WHEN unpaid_amount > 0
                         AND trans <> 'DM'
                         AND DATEDIFF(?, aging_date) BETWEEN 1 AND 30
                        THEN unpaid_amount ELSE 0
                    END
                ), 2) AS _30,
                ROUND(SUM(
                    CASE
                        WHEN unpaid_amount > 0
                         AND trans <> 'DM'
                         AND DATEDIFF(?, aging_date) BETWEEN 31 AND 60
                        THEN unpaid_amount ELSE 0
                    END
                ), 2) AS _60,
                ROUND(SUM(
                    CASE
                        WHEN unpaid_amount > 0
                         AND trans <> 'DM'
                         AND DATEDIFF(?, aging_date) BETWEEN 61 AND 90
                        THEN unpaid_amount ELSE 0
                    END
                ), 2) AS _90,
                ROUND(SUM(
                    CASE
                        WHEN unpaid_amount > 0
                         AND (
                             trans = 'DM'
                             OR DATEDIFF(?, aging_date) > 90
                         )
                        THEN unpaid_amount ELSE 0
                    END
                ), 2) AS _over90
            FROM unpaid
            GROUP BY consumer_zone_id
        ";

        $bindings = array_merge(
            $consumerIds->all(),
            [$billingCutoffDate],
            $consumerIds->all(),
            [$paymentCutoffDate],
            [$asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate]
        );

        $rows = collect(DB::select($agingSql, $bindings));

        return $rows->mapWithKeys(function ($row) {
            return [
                (int) $row->consumer_zone_id => [
                    'current' => round((float) ($row->current ?? 0), 2),
                    'days_30' => round((float) ($row->_30 ?? 0), 2),
                    'days_60' => round((float) ($row->_60 ?? 0), 2),
                    'days_90' => round((float) ($row->_90 ?? 0), 2),
                    'over_90' => round((float) ($row->_over90 ?? 0), 2),
                ],
            ];
        });
    }

    /**
     * Outstanding from prior billing cycles (total balance minus latest bill + penalty on latest due date).
     * Same rules as generateNotice / printNotice.
     */
    private function computeLastMonthArrearsAmount(float $currentBalance, Collection $consumerBillings, Collection $penalties): float
    {
        if ($consumerBillings->isEmpty()) {
            return max(0.0, $currentBalance);
        }

        $latestBilling = $consumerBillings->sortByDesc(function ($billing) {
            $dueDate = $billing->due_date;

            return is_string($dueDate) ? Carbon::parse($dueDate) : $dueDate;
        })->first();

        $thisMonthBill = $latestBilling ? (float) ($latestBilling->debit ?? 0) : 0.0;
        $firstDueDate = $latestBilling && $latestBilling->due_date
            ? (is_string($latestBilling->due_date) ? Carbon::parse($latestBilling->due_date)->format('Y-m-d') : $latestBilling->due_date->format('Y-m-d'))
            : null;

        $thisMonthPenalty = $firstDueDate ? $penalties->sum(function ($p) use ($firstDueDate) {
            $pDue = $p->due_date
                ? (is_string($p->due_date) ? Carbon::parse($p->due_date)->format('Y-m-d') : $p->due_date->format('Y-m-d'))
                : null;

            return $pDue === $firstDueDate ? (float) ($p->debit ?? $p->penalty ?? 0) : 0;
        }) : 0.0;

        $thisMonthArrearsAmount = $thisMonthBill + $thisMonthPenalty;
        $lastMonthArrearsAmount = $currentBalance - $thisMonthArrearsAmount;

        return $lastMonthArrearsAmount > 0 ? round($lastMonthArrearsAmount, 2) : 0.0;
    }

    /**
     * When there is arrears from a previous month, unpaid months reflects at least 2 (current bill + prior balance).
     */
    private function adjustUnpaidMonthsForPriorArrears(int $unpaidCount, float $lastMonthArrearsAmount): int
    {
        if ($lastMonthArrearsAmount > 0.01) {
            return max($unpaidCount, 2);
        }

        return $unpaidCount;
    }

    /**
     * Build notice/print consumer rows using the same financial basis as disconnection index list.
     *
     * @param  array<int|string>  $consumerIds
     * @param  array<int|string, array<string, mixed>>|null  $financialsInput
     */
    private function buildConsumersForDisconnectionNotice(
        array $consumerIds,
        ?string $listBillingMonth = null,
        ?string $listBillingDateYmd = null,
        ?array $financialsInput = null
    ): Collection {
        $consumerIds = collect($consumerIds)->filter()->unique()->values();
        if ($consumerIds->isEmpty()) {
            return collect();
        }

        $ledgerCutoffDate = $this->resolveListLedgerCutoffForAging($listBillingMonth, $listBillingDateYmd);
        $financialsInput = $financialsInput ?? [];

        $consumers = ConsumerZone::whereIn('id', $consumerIds)
            ->with(['ledgers' => function ($query) {
                $query->where('trans', 'BILLING')
                    ->where(function ($q) {
                        $q->whereNull('credit')->orWhere('credit', 0);
                    })
                    ->where('due_date', '<', now())
                    ->orderBy('due_date', 'desc');
            }])
            ->get()
            ->sortBy(fn ($consumer) => $consumerIds->search($consumer->id))
            ->values();

        $arAgingBucketsByConsumer = $this->computeAraAgingBucketsBulk($consumerIds, $ledgerCutoffDate);

        return $consumers->map(function ($consumer) use ($arAgingBucketsByConsumer, $financialsInput) {
            $agingBuckets = $arAgingBucketsByConsumer->get($consumer->id, [
                'current' => 0.0,
                'days_30' => 0.0,
                'days_60' => 0.0,
                'days_90' => 0.0,
                'over_90' => 0.0,
            ]);
            $posted = $financialsInput[$consumer->id] ?? $financialsInput[(string) $consumer->id] ?? null;

            $this->hydrateConsumerForDisconnectionNotice(
                $consumer,
                $agingBuckets,
                is_array($posted) ? $posted : null
            );

            return $consumer;
        })
            ->filter(function ($consumer) {
                return $consumer->total_outstanding > 0
                    && (($consumer->last_month_arrears ?? 0) + ($consumer->others_ar ?? 0) > 0.01);
            })
            ->values();
    }

    /**
     * Total Outstanding / Total Amount Due on disconnection screens.
     * Always the sum of the three displayed aging columns (never raw ledger balance).
     * This Month (days_30) + Last Month CY (days_60) + Other/A/R (days_90 + over_90).
     */
    private function sumIndexAgingOutstanding(array $agingBuckets): float
    {
        return round(
            (float) ($agingBuckets['days_30'] ?? 0)
            + (float) ($agingBuckets['days_60'] ?? 0)
            + (float) ($agingBuckets['days_90'] ?? 0)
            + (float) ($agingBuckets['over_90'] ?? 0),
            2
        );
    }

    /**
     * Map AR aging buckets to notice/index SOA lines.
     * This Month = days_30; Last Month CY = days_60 + days_90; Other / A/R = over_90 only.
     *
     * @return array{this_month_arrears: float, last_month_arrears: float, others_ar: float}
     */
    private function mapAgingBucketsToNoticeFinancialLines(array $agingBuckets): array
    {
        return [
            'this_month_arrears' => round((float) ($agingBuckets['days_30'] ?? 0), 2),
            'last_month_arrears' => round((float) ($agingBuckets['days_60'] ?? 0) + (float) ($agingBuckets['days_90'] ?? 0), 2),
            'others_ar' => round((float) ($agingBuckets['over_90'] ?? 0), 2),
        ];
    }

    /**
     * Map AR aging buckets onto disconnection notice line items (preview + print).
     * Total Amount Due = sum of the three SOA lines (matches index Total Outstanding).
     */
    private function hydrateConsumerForDisconnectionNotice(
        ConsumerZone $consumer,
        array $agingBuckets,
        ?array $postedFinancials = null
    ): void {
        $usePosted = is_array($postedFinancials)
            && array_key_exists('this_month_arrears', $postedFinancials)
            && array_key_exists('last_month_arrears', $postedFinancials);

        if ($usePosted) {
            $thisMonthArrears = round(max(0.0, (float) $postedFinancials['this_month_arrears']), 2);
            $lastMonthArrears = round(max(0.0, (float) $postedFinancials['last_month_arrears']), 2);
            $othersAr = round(max(0.0, (float) ($postedFinancials['others_ar'] ?? 0)), 2);
        } else {
            $mapped = $this->mapAgingBucketsToNoticeFinancialLines($agingBuckets);
            $thisMonthArrears = $mapped['this_month_arrears'];
            $lastMonthArrears = $mapped['last_month_arrears'];
            $othersAr = $mapped['others_ar'];
        }

        $totalOutstanding = round($thisMonthArrears + $lastMonthArrears + $othersAr, 2);

        $consumer->setAttribute('this_month_arrears', $thisMonthArrears);
        $consumer->setAttribute('last_month_arrears', $lastMonthArrears);
        $consumer->setAttribute('others_ar', $othersAr);
        $consumer->setAttribute('total_outstanding', $totalOutstanding);
        $consumer->setAttribute('notice_soa_total', $totalOutstanding);

        $has90DayBucket = (float) ($agingBuckets['days_90'] ?? 0) > 0.01;
        $consumer->setAttribute('notice_delinquent_months', $has90DayBucket ? 3 : 2);

        $priorBucketsTotal = $lastMonthArrears + $othersAr;
        $consumer->setAttribute(
            'unpaid_months',
            $this->adjustUnpaidMonthsForPriorArrears((int) $consumer->ledgers->count(), $priorBucketsTotal)
        );
        $consumer->setAttribute('card_number', $consumer->sequence ?? '1');
    }

    /**
     * Get the current balance from ledger using the same calculation as ledger view
     * This calls the ConsumerLedgerController to get the exact balance shown in ledger.blade.php
     */
    private function getCurrentBalance(ConsumerZone $consumer): float
    {
        try {
            // Use the same method as ledger view to get accurate balance
            $ledgerController = new ConsumerLedgerController;
            $request = new Request;
            $request->merge([
                'account_no' => $consumer->account_no,
                'year' => '', // Get all records for accurate balance calculation
            ]);

            $ledgerResponse = $ledgerController->getLedger($request);
            $ledgerData = json_decode($ledgerResponse->getContent(), true);

            if (isset($ledgerData['summary']['balance'])) {
                return round((float) $ledgerData['summary']['balance'], 2);
            }
        } catch (\Exception $e) {
            Log::error('Error getting balance from ledger for account '.$consumer->account_no.': '.$e->getMessage());
        }

        return $this->getCurrentBalanceFromLedgerTable($consumer);
    }

    /**
     * Get current balance directly from consumer_ledger (same order as ledger view).
     * Used when getLedger is unreliable (e.g. print request context) so Last Month/ Arrears CY gets correct total.
     */
    private function getCurrentBalanceFromLedgerTable(ConsumerZone $consumer): float
    {
        $clConsumerZoneId = mr_col('consumer_zone_id');
        $clId = self::consumerLedgerIdColumn();

        $lastLedger = ConsumerLedger::query()
            ->where($clConsumerZoneId, $consumer->id)
            ->where(function ($q) {
                $q->whereNull('billing_adjustment_id')
                    ->orWhereHas('billingAdjustment', function ($q2) {
                        $q2->where(mr_col('status'), 'Approved');
                    });
            })
            ->orderByRaw('CAST(date AS DATE) ASC')
            ->orderByRaw("CASE WHEN UPPER(TRIM(trans)) IN ('BILLING','BILL') THEN 0 WHEN UPPER(TRIM(trans)) = 'PAYMENT' THEN 1 ELSE 2 END ASC")
            ->orderBy($clId, 'asc')
            ->get()
            ->last();

        if ($lastLedger && $lastLedger->balance !== null) {
            return round((float) $lastLedger->balance, 2);
        }

        return round((float) ($consumer->balance ?? 0), 2);
    }

    /**
     * Check consecutive unpaid months
     */
    private function checkConsecutiveMonths(Collection $unpaidBills): int
    {
        if ($unpaidBills->isEmpty()) {
            return 0;
        }

        // Group by month-year
        $months = $unpaidBills->map(function ($bill) {
            return $bill->due_date->format('Y-m');
        })->unique()->sort()->values();

        if ($months->isEmpty()) {
            return 0;
        }

        // Check for consecutive months
        $maxConsecutive = 1;
        $currentConsecutive = 1;

        for ($i = 1; $i < $months->count(); $i++) {
            $prevMonth = Carbon::parse($months[$i - 1].'-01');
            $currentMonth = Carbon::parse($months[$i].'-01');

            // Check if current month is exactly 1 month after previous
            if ($currentMonth->diffInMonths($prevMonth) == 1) {
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                $currentConsecutive = 1;
            }
        }

        return $maxConsecutive;
    }

    /**
     * Get consumers with N consecutive bill months without payment (no PAYMENT for schedule).
     * OPTIMIZED: Only checks records from 2025 onwards for faster processing
     *
     * @param  int  $requiredMonths  2 or 3
     */
    private function getConsumersWithConsecutiveUnpaidMonths(Request $request, int $requiredMonths): View
    {
        $requiredMonths = max(2, min(3, $requiredMonths));
        $filterType = $requiredMonths === 2 ? '2_consecutive' : '3_consecutive';
        $zone = $request->get('zone');
        $billingMonth = $request->get('billing_month');
        $billingDate = $request->get('billing_date');
        $ledgerCutoffDate = $this->resolveListLedgerCutoffForAging($billingMonth, $billingDate);

        $consumers = $this->getActiveDisconnectionCandidates($zone);
        if ($consumers->isEmpty()) {
            return $this->renderConsecutiveUnpaidMonthsIndexView($request, $zone, $filterType, collect());
        }

        $lookupData = $this->loadConsecutiveUnpaidLookupData(
            $consumers->pluck('id'),
            $ledgerCutoffDate,
            $billingMonth ?: $billingDate,
            ! empty($billingMonth)
        );
        $eligibleConsumers = $this->filterConsecutiveUnpaidEligibleConsumers($consumers, $lookupData, $requiredMonths);

        return $this->renderConsecutiveUnpaidMonthsIndexView($request, $zone, $filterType, $eligibleConsumers);
    }

    /**
     * Active (non-disconnected) consumers for disconnection candidate screens.
     */
    private function getActiveDisconnectionCandidates(?string $zone): Collection
    {
        $query = ConsumerZone::query()
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhereNotIn(DB::raw('UPPER(TRIM(status_code))'), ['X', 'D', 'DISCONNECTED']);
            });

        if ($zone) {
            $query->where(mr_col('zone_code'), $zone);
        }

        return $query->get();
    }

    /**
     * Bulk ledger, payment, and schedule data for consecutive-unpaid filtering.
     *
     * @return array{
     *     allBillings: Collection<int, Collection<int, ConsumerLedger>>,
     *     latestBalances: Collection<int, float|int>,
     *     allPayments: Collection<int, array<int>>,
     *     penaltyLedgersByConsumer: Collection<int, Collection<int, ConsumerLedger>>,
     *     latestScheduleReadingsByAccount: Collection<string, float|int>,
     *     latestCurrentBillsByAccount: Collection<string, float|int>
     * }
     */
    private function loadConsecutiveUnpaidLookupData(
        Collection $consumerIds,
        ?string $ledgerCutoffDate,
        ?string $billingFilter,
        bool $isMonthFilter
    ): array {
        $today = Carbon::today();

        $clConsumerZoneId = mr_col('consumer_zone_id');
        $clTrans = mr_col('trans');
        $clDate = mr_col('date');
        $mrsDueDate = mr_col('due_date');
        $clScheduleId = mr_col('schedule_id');

        $allBillings = $this->ledgerQueryForConsumers($consumerIds)
            ->whereIn($clTrans, ['BILL', 'BILLING'])
            ->whereNotNull($clDate)
            ->whereNotNull($mrsDueDate)
            ->where(function ($q) use ($mrsDueDate, $clDate) {
                $q->whereYear($mrsDueDate, '>=', 2025)
                    ->orWhereYear($clDate, '>=', 2025);
            })
            ->where($mrsDueDate, '<=', $today)
            ->when($ledgerCutoffDate, function ($query) use ($ledgerCutoffDate, $clDate) {
                $query->whereDate($clDate, '<=', $ledgerCutoffDate);
            })
            ->orderBy($clConsumerZoneId)
            ->orderBy($mrsDueDate, 'desc')
            ->orderBy($clDate, 'desc')
            ->get()
            ->groupBy($clConsumerZoneId);

        $latestBalances = $this->calculateBalancesBulk($consumerIds, $ledgerCutoffDate);

        $allPayments = $this->ledgerQueryForConsumers($consumerIds)
            ->where($clTrans, 'PAYMENT')
            ->whereNotNull($clScheduleId)
            ->where(function ($q) use ($clDate) {
                $q->whereYear($clDate, '>=', 2025);
            })
            ->select($clConsumerZoneId, $clScheduleId)
            ->when($ledgerCutoffDate, function ($query) use ($ledgerCutoffDate, $clDate) {
                $query->whereDate($clDate, '<=', $ledgerCutoffDate);
            })
            ->get()
            ->groupBy($clConsumerZoneId)
            ->map(function ($payments) use ($clScheduleId) {
                return $payments->pluck($clScheduleId)->unique()->toArray();
            });

        $penaltyLedgersByConsumer = $this->ledgerQueryForConsumers($consumerIds)
            ->where($clTrans, 'PENALTY')
            ->when($ledgerCutoffDate, function ($query) use ($ledgerCutoffDate, $clDate) {
                $query->whereDate($clDate, '<=', $ledgerCutoffDate);
            })
            ->get()
            ->groupBy($clConsumerZoneId);

        $consumersById = $this->consumersByIdForZoneIds($consumerIds);

        return [
            'allBillings' => $allBillings,
            'latestBalances' => $latestBalances,
            'allPayments' => $allPayments,
            'penaltyLedgersByConsumer' => $penaltyLedgersByConsumer,
            'latestScheduleReadingsByAccount' => $this->latestScheduleReadingsByAccount(
                $consumerIds,
                $consumersById,
                $billingFilter,
                $isMonthFilter
            ),
            'latestCurrentBillsByAccount' => $this->latestCurrentBillsByAccount($consumerIds, $consumersById),
        ];
    }

    /**
     * @param  array{
     *     allBillings: Collection<int, Collection<int, ConsumerLedger>>,
     *     latestBalances: Collection<int, float|int>,
     *     allPayments: Collection<int, array<int>>,
     *     penaltyLedgersByConsumer: Collection<int, Collection<int, ConsumerLedger>>,
     *     latestScheduleReadingsByAccount: Collection<string, float|int>,
     *     latestCurrentBillsByAccount: Collection<string, float|int>
     * }  $lookupData
     */
    private function filterConsecutiveUnpaidEligibleConsumers(
        Collection $consumers,
        array $lookupData,
        int $requiredMonths
    ): Collection {
        $eligibleConsumers = collect();

        foreach ($consumers as $consumer) {
            $enriched = $this->tryEnrichConsecutiveUnpaidConsumer($consumer, $lookupData, $requiredMonths);
            if ($enriched !== null) {
                $eligibleConsumers->push($enriched);
            }
        }

        return $eligibleConsumers;
    }

    /**
     * @param  array{
     *     allBillings: Collection<int, Collection<int, ConsumerLedger>>,
     *     latestBalances: Collection<int, float|int>,
     *     allPayments: Collection<int, array<int>>,
     *     penaltyLedgersByConsumer: Collection<int, Collection<int, ConsumerLedger>>,
     *     latestScheduleReadingsByAccount: Collection<string, float|int>,
     *     latestCurrentBillsByAccount: Collection<string, float|int>
     * }  $lookupData
     */
    private function tryEnrichConsecutiveUnpaidConsumer(
        ConsumerZone $consumer,
        array $lookupData,
        int $requiredMonths
    ): ?ConsumerZone {
        $billings = $lookupData['allBillings']->get($consumer->id, collect());
        if ($billings->isEmpty() || $billings->count() < $requiredMonths) {
            return null;
        }

        $currentBalance = $lookupData['latestBalances']->get($consumer->id, 0);
        if ($currentBalance <= 0) {
            return null;
        }

        $paidScheduleIds = $lookupData['allPayments']->get($consumer->id, []);
        $unpaidDatesData = $this->calculateUnpaidDates($consumer->id, $billings, $paidScheduleIds);
        $paymentsCollection = collect($paidScheduleIds)->map(function ($scheduleId) {
            return (object) [
                'schedule_id' => $scheduleId,
                'date' => null,
            ];
        });
        $consecutiveUnpaid = $this->checkConsecutiveUnpaidMonthsOptimized($billings, $paymentsCollection, $requiredMonths);

        if (! $consecutiveUnpaid['has_consecutive']) {
            return null;
        }

        $consumerPenalties = $lookupData['penaltyLedgersByConsumer']->get($consumer->id, collect());
        $lastMonthArrearsAmount = $this->computeLastMonthArrearsAmount($currentBalance, $billings, $consumerPenalties);
        $agingBuckets = $this->computeDisconnectionAgingBreakdown(
            $billings,
            $paidScheduleIds,
            (float) $currentBalance,
            $consumerPenalties
        );

        $consumer->unpaid_months = $this->adjustUnpaidMonthsForPriorArrears(
            (int) $unpaidDatesData['unpaid_count'],
            $lastMonthArrearsAmount
        );
        $consumer->ledger_balance = round((float) $currentBalance, 2);
        $consumer->total_outstanding = $this->sumIndexAgingOutstanding($agingBuckets);
        $consumer->notice_soa_total = (float) $consumer->total_outstanding;
        $consumer->consecutive_unpaid_months = $consecutiveUnpaid['months'];
        $consumer->oldest_unpaid_date = $unpaidDatesData['oldest_unpaid_date'] ?? $consecutiveUnpaid['oldest_date'];
        $consumer->latest_unpaid_date = $unpaidDatesData['latest_unpaid_date'] ?? $consecutiveUnpaid['latest_date'];
        $consumer->last_reading = (float) ($lookupData['latestScheduleReadingsByAccount']->get($consumer->account_no, 0) ?? 0);
        $rawCurrentBill = (float) ($lookupData['latestCurrentBillsByAccount']->get($consumer->account_no, 0) ?? 0);
        $consumer->current_bill = $rawCurrentBill;
        $consumer->current_bill_with_maintenance = round($rawCurrentBill + 20, 2);
        $consumer->aging_current = $agingBuckets['current'];
        $consumer->aging_30_days = $agingBuckets['days_30'];
        $consumer->aging_60_days = $agingBuckets['days_60'];
        $consumer->aging_90_days = $agingBuckets['days_90'];
        $consumer->aging_over_90 = $agingBuckets['over_90'];

        return $consumer;
    }

    private function renderConsecutiveUnpaidMonthsIndexView(
        Request $request,
        ?string $zone,
        string $filterType,
        Collection $eligibleConsumers
    ): View {
        $czZoneCode = mr_col('zone_code');
        $userRole = mr_col('role');
        $userName = mr_col('name');
        $totalOutstandingKey = mr_col('total_outstanding');

        $consumersByZone = $eligibleConsumers->groupBy($czZoneCode);
        $zones = ConsumerZone::select($czZoneCode)
            ->distinct()
            ->whereNotNull($czZoneCode)
            ->orderBy($czZoneCode)
            ->pluck($czZoneCode);
        $disconnectors = User::query()->where($userRole, 'disconnector')->orderBy($userName)->get();
        $totalConsumers = $eligibleConsumers->count();
        $totalOutstanding = $eligibleConsumers->sum($totalOutstandingKey);
        $billingMonth = $request->get('billing_month');
        $billingDate = $request->get('billing_date');
        $defaultDisconnectionDate = $this->getDefaultDisconnectionDateFromBilling($zone, $billingMonth, $billingDate);

        return view('disconnection.index', array_merge(
            compact('consumersByZone', 'zones', 'zone', 'filterType', 'disconnectors', 'totalConsumers', 'totalOutstanding', 'billingDate', 'billingMonth', 'defaultDisconnectionDate'),
            $this->getOrdersTabData($request)
        ));
    }

    /**
     * Get default disconnection date from meter_reading_schedules when billing month/date is provided.
     */
    public function getDefaultDisconnectionDateFromBilling(?string $zone, ?string $billingMonth, ?string $billingDate, ?Collection $consumers = null): string
    {
        $billingFilter = $billingMonth ?: $billingDate;
        if (! $billingFilter) {
            return Carbon::today()->addDays(7)->format('Y-m-d');
        }
        $isMonthFilter = ! empty($billingMonth);
        $querySchedule = function ($withZone) use ($zone, $billingFilter, $isMonthFilter) {
            $mrsDisconnectionDate = mr_col('disconnection_date');
            $q = MeterReadingSchedule::query()->whereNotNull($mrsDisconnectionDate);
            if ($withZone && $zone) {
                $q->forZoneCode($zone);
            }
            if ($isMonthFilter) {
                $monthCarbon = Carbon::createFromFormat('Y-m', $billingFilter)->startOfMonth();
                $q->where(function ($query) use ($monthCarbon) {
                    $query->whereYear('bill_month', $monthCarbon->year)
                        ->whereMonth('bill_month', $monthCarbon->month);
                });
            } else {
                $billingDateCarbon = Carbon::parse($billingFilter);
                $q->where(function ($query) use ($billingDateCarbon) {
                    $query->whereDate('bill_date', $billingDateCarbon)
                        ->orWhereDate('bill_month', $billingDateCarbon->format('Y-m-01'));
                });
            }

            return $q->orderBy($mrsDisconnectionDate)->first();
        };
        $schedule = $querySchedule(true);
        if (! $schedule && $zone) {
            $schedule = $querySchedule(false);
        }
        if ($schedule && $schedule->disconnection_date) {
            return Carbon::parse($schedule->disconnection_date)->format('Y-m-d');
        }
        if ($consumers && $consumers->isNotEmpty()) {
            $first = $consumers->first();
            if (! empty($first->disconnection_date)) {
                return Carbon::parse($first->disconnection_date)->format('Y-m-d');
            }
        }

        return Carbon::today()->addDays(7)->format('Y-m-d');
    }

    /**
     * Check if consumer has N consecutive months without payment (optimized version).
     * Uses only consumer_ledger records (BILL/BILLING and PAYMENT).
     *
     * @param  int  $requiredMonths  Minimum consecutive unpaid months (2 or 3)
     */
    private function checkConsecutiveUnpaidMonthsOptimized(Collection $billings, Collection $payments, int $requiredMonths = 3): array
    {
        $requiredMonths = max(2, $requiredMonths);

        $result = [
            'has_consecutive' => false,
            'count' => 0,
            'months' => [],
            'oldest_date' => null,
            'latest_date' => null,
        ];

        if ($billings->isEmpty()) {
            return $result;
        }

        // Group billings by month-year based on due_date (only from 2025)
        $startDate = Carbon::create(2025, 1, 1);
        $billingMonths = $billings->filter(function ($billing) use ($startDate) {
            $dueDate = is_string($billing->due_date) ? Carbon::parse($billing->due_date) : $billing->due_date;

            return $dueDate >= $startDate;
        })->map(function ($billing) {
            $dueDate = is_string($billing->due_date) ? Carbon::parse($billing->due_date) : $billing->due_date;

            return [
                'month_key' => $dueDate->format('Y-m'),
                'due_date' => $dueDate,
                'billing' => $billing,
                'debit' => (float) ($billing->debit ?? 0),
                'credit' => (float) ($billing->credit ?? 0),
                'balance' => (float) ($billing->balance ?? 0),
            ];
        })->sortByDesc(mr_col('due_date'))->values(); // Sort descending to check most recent first

        // Check each billing month to see if it's unpaid (optimized with hash maps)
        $unpaidMonths = [];

        // Pre-process payments by month and schedule_id for O(1) lookup
        $paymentsByMonth = [];
        $paymentsBySchedule = [];

        foreach ($payments as $payment) {
            if ($payment->date) {
                $paymentDate = Carbon::parse($payment->date);
                $monthKey = $paymentDate->format('Y-m');

                // Index by month
                if (! isset($paymentsByMonth[$monthKey])) {
                    $paymentsByMonth[$monthKey] = true; // Just track existence
                }
            }

            // Index by schedule_id
            if ($payment->schedule_id) {
                $paymentsBySchedule[$payment->schedule_id] = true;
            }
        }

        foreach ($billingMonths as $billingMonth) {
            $debit = $billingMonth['debit'];
            $credit = $billingMonth['credit'];
            $balance = $billingMonth['balance'];
            $scheduleId = $billingMonth['billing']->schedule_id ?? null;
            $dueDate = $billingMonth['due_date'];

            // Check if payment exists for this schedule_id
            $hasPayment = false;
            if ($scheduleId && isset($paymentsBySchedule[$scheduleId])) {
                $hasPayment = true;
            }

            // A billing is considered unpaid if:
            // 1. No PAYMENT record exists with the same schedule_id, AND
            // 2. The billing has outstanding amount (debit > credit) or positive balance
            $isUnpaid = false;

            if (! $hasPayment) {
                // No payment record, check if there's outstanding amount
                $isUnpaid = ($debit > $credit) || ($balance > 0);
            } else {
                // Payment exists, but check if it fully covers the bill
                // If debit > credit, there's still outstanding amount
                $isUnpaid = ($debit > $credit);
            }

            if ($isUnpaid) {
                $unpaidMonths[] = $billingMonth;
            }
        }

        if (count($unpaidMonths) < $requiredMonths) {
            return $result;
        }

        // Sort unpaid months by date (ascending - oldest first)
        $dueDateKey = mr_col('due_date');
        $unpaidMonths = collect($unpaidMonths)->sortBy($dueDateKey)->values();

        if ($unpaidMonths->isEmpty()) {
            return $result;
        }

        $consecutiveCount = 1;
        $maxConsecutive = 1;
        $bestStreakMonths = [$unpaidMonths[0]];

        for ($i = 1; $i < $unpaidMonths->count(); $i++) {
            $prevMonth = Carbon::parse($unpaidMonths[$i - 1]['month_key'].'-01');
            $currentMonth = Carbon::parse($unpaidMonths[$i]['month_key'].'-01');

            if ($currentMonth->diffInMonths($prevMonth) == 1) {
                $consecutiveCount++;
                if ($consecutiveCount > $maxConsecutive) {
                    $maxConsecutive = $consecutiveCount;
                    $bestStreakMonths = $unpaidMonths->slice($i - $consecutiveCount + 1, $consecutiveCount)->values()->all();
                }
                if ($consecutiveCount >= $requiredMonths) {
                    $bestStreakMonths = $unpaidMonths->slice($i - $consecutiveCount + 1, $consecutiveCount)->values()->all();
                    break;
                }
            } else {
                if ($maxConsecutive >= $requiredMonths) {
                    break;
                }
                $consecutiveCount = 1;
            }
        }

        if ($maxConsecutive >= $requiredMonths) {
            $result['has_consecutive'] = true;
            $result['count'] = $maxConsecutive;
            $result['months'] = collect($bestStreakMonths)->pluck('month_key')->toArray();
            $result['oldest_date'] = $bestStreakMonths[0]['due_date'];
            $result['latest_date'] = $bestStreakMonths[count($bestStreakMonths) - 1]['due_date'];
        }

        return $result;
    }

    /**
     * Print/Download disconnection notice
     */
    public function printNotice(Request $request)
    {
        $request->validate([
            'consumer_ids' => 'required|array',
            'consumer_ids.*' => 'exists:consumer_zone,id',
            'disconnection_date' => 'nullable|date',
            'list_billing_month' => 'nullable|string',
            'list_billing_date' => 'nullable|date',
            'financials' => 'nullable|array',
            'financials.*.this_month_arrears' => 'nullable|numeric|min:0',
            'financials.*.last_month_arrears' => 'nullable|numeric|min:0',
            'financials.*.others_ar' => 'nullable|numeric|min:0',
            'financials.*.total_outstanding' => 'nullable|numeric|min:0',
        ]);

        $consumerIds = $request->input('consumer_ids', []);
        $disconnectionDate = $request->filled('disconnection_date')
            ? Carbon::parse($request->input('disconnection_date'))
            : Carbon::today();

        if (empty($consumerIds)) {
            return redirect()->route('disconnection.index')
                ->with('error', 'Please select at least one consumer.');
        }

        $listBillingMonth = $request->input('list_billing_month') ?: null;
        $listBillingDate = $request->input('list_billing_date') ?: null;
        $financialsInput = $request->input('financials', []);

        $consumersWithOutstanding = $this->buildConsumersForDisconnectionNotice(
            $consumerIds,
            $listBillingMonth,
            $listBillingDate,
            is_array($financialsInput) ? $financialsInput : []
        );

        if ($consumersWithOutstanding->isEmpty()) {
            return redirect()->route('disconnection.index')
                ->with('error', 'No consumers with outstanding balance for printing.');
        }

        return view('disconnection.print', compact('consumersWithOutstanding', 'disconnectionDate'));
    }

    /**
     * Ledger / AR aging as-of date for Save & Send — must match the candidate list
     * (billing month → end of that month; billing date → that calendar day).
     */
    private function resolveListLedgerCutoffForAging(?string $listBillingMonth, ?string $listBillingDateYmd): ?string
    {
        if ($listBillingMonth !== null && $listBillingMonth !== '') {
            try {
                return Carbon::createFromFormat('Y-m', $listBillingMonth)->endOfMonth()->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        if ($listBillingDateYmd !== null && $listBillingDateYmd !== '') {
            try {
                return Carbon::parse($listBillingDateYmd)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Duplicate save = same consumer, same disconnection date, and same list filter context (billing month/date/mode).
     * Different billing months (or other list context) allow a new row even if the calendar disconnection date repeats.
     */
    private function findExistingDisconnectionOrderForListContext(
        int $consumerId,
        Carbon $disconnectionDate,
        ?string $listBillingMonth,
        ?string $listBillingDateYmd,
        ?string $listFilterType
    ): ?DisconnectionOrder {
        $doDisconnectionDate = mr_col('disconnection_date');
        $doStatus = mr_col('status');
        $doListBillingMonth = mr_col('list_billing_month');
        $doListBillingDate = mr_col('list_billing_date');
        $doListFilterType = mr_col('list_filter_type');

        $q = DisconnectionOrder::forConsumerZone($consumerId)
            ->whereDate($doDisconnectionDate, $disconnectionDate->format('Y-m-d'))
            ->where($doStatus, '!=', 'cancelled')
            ->lockForUpdate();

        if ($listBillingMonth !== null && $listBillingMonth !== '') {
            $q->where($doListBillingMonth, $listBillingMonth);
        } else {
            $q->whereNull($doListBillingMonth);
        }

        if ($listBillingDateYmd !== null && $listBillingDateYmd !== '') {
            $q->whereDate($doListBillingDate, $listBillingDateYmd);
        } else {
            $q->whereNull($doListBillingDate);
        }

        if ($listFilterType !== null && $listFilterType !== '') {
            $q->where(function ($sub) use ($listFilterType, $doListFilterType) {
                $sub->where($doListFilterType, $listFilterType);
                // Legacy rows: migration backfilled null → disconnection_date; older saves may still have NULL.
                if ($listFilterType === 'disconnection_date') {
                    $sub->orWhereNull($doListFilterType);
                }
            });
        } else {
            $q->where(function ($sub) use ($doListFilterType) {
                $sub->whereNull($doListFilterType)
                    ->orWhere($doListFilterType, 'disconnection_date');
            });
        }

        return $q->first();
    }

    /**
     * Create disconnection orders for the given consumers (same persistence rules as Save & Send).
     *
     * @return array{created: int, updated: int, skipped: int, created_orders: DisconnectionOrder[], updated_orders: DisconnectionOrder[], skipped_orders: array}
     */
    public function syncDisconnectionOrders(
        array $consumerIds,
        Carbon $disconnectionDate,
        int $disconnectorId,
        ?string $listBillingMonth,
        ?string $listBillingDateYmd,
        ?string $listFilterType,
        array $financialsInput = []
    ): array {
        $consumerIds = array_values(array_unique(array_map('intval', $consumerIds)));
        sort($consumerIds);

        DB::beginTransaction();

        try {
            $czId = self::consumerZoneIdColumn();
            $clConsumerZoneId = mr_col('consumer_zone_id');
            $clTrans = mr_col('trans');
            $mrsDueDate = mr_col('due_date');
            $clScheduleId = mr_col('schedule_id');

            $consumers = ConsumerZone::query()
                ->whereIn($czId, $consumerIds)
                ->orderBy($czId)
                ->lockForUpdate()
                ->get();
            $consumersById = $this->consumersByIdForZoneIds($consumerIds);
            $latestScheduleReadingsByAccount = $this->latestScheduleReadingsByAccount($consumerIds, $consumersById);
            $latestCurrentBillsByAccount = $this->latestCurrentBillsByAccount($consumerIds, $consumersById);

            $idsForLedger = $consumers->pluck('id')->values();
            $allBillingsForSplit = $this->ledgerQueryForConsumers($idsForLedger)
                ->whereIn($clTrans, ['BILL', 'BILLING'])
                ->whereNotNull($mrsDueDate)
                ->where($mrsDueDate, '<', now())
                ->get()
                ->groupBy($clConsumerZoneId);
            $penaltyLedgersForOrders = $this->ledgerQueryForConsumers($idsForLedger)
                ->where($clTrans, 'PENALTY')
                ->get()
                ->groupBy($clConsumerZoneId);
            $paidScheduleIdsByConsumer = $this->ledgerQueryForConsumers($idsForLedger)
                ->where($clTrans, 'PAYMENT')
                ->whereNotNull($clScheduleId)
                ->get()
                ->groupBy($clConsumerZoneId)
                ->map(function ($rows) use ($clScheduleId) {
                    return $rows->pluck($clScheduleId)->unique()->values()->all();
                });
            $allBillingsForUnpaidCalc = $this->ledgerQueryForConsumers($idsForLedger)
                ->whereIn($clTrans, ['BILL', 'BILLING'])
                ->whereNotNull($mrsDueDate)
                ->where($mrsDueDate, '<=', Carbon::today())
                ->get()
                ->groupBy($clConsumerZoneId);

            $agingLedgerCutoff = $this->resolveListLedgerCutoffForAging($listBillingMonth, $listBillingDateYmd);
            $arBucketsForSync = $this->computeAraAgingBucketsBulk($consumerIds, $agingLedgerCutoff);

            $createdOrders = [];
            $updatedOrders = [];
            $skippedOrders = [];

            foreach ($consumers as $consumer) {
                $posted = $financialsInput[$consumer->id] ?? null;
                $usePostedFinancials = is_array($posted)
                    && isset($posted['this_month_arrears'], $posted['last_month_arrears']);

                if ($usePostedFinancials) {
                    $thisMonthArrears = round(max(0.0, (float) $posted['this_month_arrears']), 2);
                    $lastMonthArrearsCY = round(max(0.0, (float) $posted['last_month_arrears']), 2);
                    $othersArPosted = round(max(0.0, (float) ($posted['others_ar'] ?? 0)), 2);
                    $totalAmountDue = round($thisMonthArrears + $lastMonthArrearsCY + $othersArPosted, 2);
                } else {
                    $b = $arBucketsForSync->get($consumer->id, [
                        'current' => 0.0,
                        'days_30' => 0.0,
                        'days_60' => 0.0,
                        'days_90' => 0.0,
                        'over_90' => 0.0,
                    ]);
                    $mapped = $this->mapAgingBucketsToNoticeFinancialLines($b);
                    $thisMonthArrears = $mapped['this_month_arrears'];
                    $lastMonthArrearsCY = $mapped['last_month_arrears'];
                    $othersArPosted = $mapped['others_ar'];
                    $totalAmountDue = $this->sumIndexAgingOutstanding($b);
                }

                if ($totalAmountDue <= 0) {
                    $skippedOrders[] = [
                        'account_no' => $consumer->account_no,
                        'reason' => 'No outstanding balance',
                    ];

                    continue;
                }

                $unpaidDatesData = $this->calculateUnpaidDates(
                    $consumer->id,
                    $allBillingsForUnpaidCalc->get($consumer->id, collect()),
                    $paidScheduleIdsByConsumer->get($consumer->id, [])
                );
                $unpaidMonthsAdjusted = $this->adjustUnpaidMonthsForPriorArrears(
                    (int) $unpaidDatesData['unpaid_count'],
                    (float) $lastMonthArrearsCY + (float) $othersArPosted
                );

                $oldestUnpaid = $unpaidDatesData['oldest_unpaid_date'] ?? $disconnectionDate;
                $latestUnpaid = $unpaidDatesData['latest_unpaid_date'] ?? $disconnectionDate;
                $rawCurrentBill = (float) ($latestCurrentBillsByAccount->get($consumer->account_no, 0) ?? 0);
                $currentBillWithMaintenance = round($rawCurrentBill + 20, 2);

                $existingOrder = $this->findExistingDisconnectionOrderForListContext(
                    $consumer->id,
                    $disconnectionDate,
                    $listBillingMonth,
                    $listBillingDateYmd,
                    $listFilterType
                );

                if (! $existingOrder) {
                    $cardNo = $consumer->sequence ?? null;
                    $cardNumber = is_numeric($cardNo) ? (int) $cardNo : 1;

                    $order = DisconnectionOrder::create(DisconnectionOrder::filterTableAttributes([
                        'consumer_zone_id' => $consumer->id,
                        'disconnector_id' => $disconnectorId,
                        'account_no' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'address' => $consumer->address,
                        'zone_code' => $consumer->zone_code,
                        'meter_number' => $consumer->meter_number,
                        'last_reading' => (float) ($latestScheduleReadingsByAccount->get($consumer->account_no, 0) ?? 0),
                        'card_number' => $cardNumber,
                        'this_month_arrears' => $thisMonthArrears,
                        'last_month_arrears' => (float) $lastMonthArrearsCY,
                        'others_ar' => $othersArPosted,
                        'total_outstanding' => $totalAmountDue,
                        'current_bill_with_maintenance' => $currentBillWithMaintenance,
                        'unpaid_months' => $unpaidMonthsAdjusted,
                        'oldest_unpaid_date' => $oldestUnpaid,
                        'latest_unpaid_date' => $latestUnpaid,
                        'disconnection_date' => $disconnectionDate,
                        'list_billing_month' => $listBillingMonth,
                        'list_billing_date' => $listBillingDateYmd,
                        'list_filter_type' => $listFilterType,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                    ]));

                    $createdOrders[] = $order;

                    Log::info('Disconnection order created', [
                        'order_id' => $order->id,
                        'account_no' => $consumer->account_no,
                        'total_outstanding' => $totalAmountDue,
                        'disconnector_id' => $disconnectorId,
                        'status' => 'assigned',
                    ]);
                } elseif (in_array($existingOrder->status, ['assigned', 'pending', 'in-progress'], true)) {
                    // Same list context + date already exists: refresh assignment and snapshot for the mobile app.
                    $existingOrder->update([
                        'disconnector_id' => $disconnectorId,
                        'this_month_arrears' => $thisMonthArrears,
                        'last_month_arrears' => (float) $lastMonthArrearsCY,
                        'others_ar' => $othersArPosted,
                        'total_outstanding' => $totalAmountDue,
                        'current_bill_with_maintenance' => $currentBillWithMaintenance,
                        'unpaid_months' => $unpaidMonthsAdjusted,
                        'oldest_unpaid_date' => $oldestUnpaid,
                        'latest_unpaid_date' => $latestUnpaid,
                        'last_reading' => (float) ($latestScheduleReadingsByAccount->get($consumer->account_no, 0) ?? 0),
                        'assigned_at' => now(),
                    ]);
                    $updatedOrders[] = $existingOrder;

                    Log::info('Disconnection order reassigned', [
                        'order_id' => $existingOrder->id,
                        'account_no' => $consumer->account_no,
                        'disconnector_id' => $disconnectorId,
                        'status' => $existingOrder->status,
                    ]);
                } else {
                    $skippedOrders[] = [
                        'account_no' => $consumer->account_no,
                        'reason' => 'Order already exists for this list context (e.g. disconnected); remove or cancel the old order to create a new one.',
                    ];
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'created' => count($createdOrders),
            'updated' => count($updatedOrders),
            'skipped' => count($skippedOrders),
            'created_orders' => $createdOrders,
            'updated_orders' => $updatedOrders,
            'skipped_orders' => $skippedOrders,
        ];
    }

    /**
     * Save disconnection orders and assign to disconnector (defaults from config/disconnection.php).
     */
    public function saveAndAssign(Request $request)
    {
        $request->validate([
            'consumer_ids' => 'required|array',
            'consumer_ids.*' => 'exists:consumer_zone,id',
            'disconnection_date' => 'required|date',
            'assign_to' => 'nullable|exists:users,id',
            'list_billing_month' => 'nullable|date_format:Y-m',
            'list_billing_date' => 'nullable|date',
            'list_filter_type' => 'nullable|string|in:disconnection_date,2_consecutive,3_consecutive',
            'financials' => 'nullable|array',
            'financials.*' => 'array',
            'financials.*.this_month_arrears' => 'nullable|numeric|min:0',
            'financials.*.last_month_arrears' => 'nullable|numeric|min:0',
            'financials.*.others_ar' => 'nullable|numeric|min:0',
            'financials.*.total_outstanding' => 'nullable|numeric|min:0',
        ]);

        $consumerIds = array_values(array_unique(array_map('intval', (array) $request->input('consumer_ids'))));
        sort($consumerIds);
        $disconnectionDate = Carbon::parse($request->input('disconnection_date'));
        $defaultDisconnectorId = (int) config('disconnection.default_disconnector_id', 47);
        $assignToId = $request->filled('assign_to')
            ? (int) $request->input('assign_to')
            : $defaultDisconnectorId;
        $financialsInput = $request->input('financials', []);

        $listBillingMonth = $request->filled('list_billing_month') ? $request->input('list_billing_month') : null;
        $listBillingDate = $request->filled('list_billing_date')
            ? Carbon::parse($request->input('list_billing_date'))->format('Y-m-d')
            : null;
        $listFilterType = $request->filled('list_filter_type') ? $request->input('list_filter_type') : null;

        try {
            $result = $this->syncDisconnectionOrders(
                $consumerIds,
                $disconnectionDate,
                $assignToId,
                $listBillingMonth,
                $listBillingDate,
                $listFilterType,
                $financialsInput
            );

            $savedTotal = $result['created'] + ($result['updated'] ?? 0);
            if ($savedTotal === 0) {
                $message = $result['skipped'] > 0
                    ? 'No new orders created. '.$result['skipped'].' account(s) were skipped (duplicate or not applicable).'
                    : 'No orders were created.';
                $firstSkip = $result['skipped_orders'][0] ?? null;
                if (is_array($firstSkip) && ! empty($firstSkip['reason'])) {
                    $message .= ' '.$firstSkip['account_no'].': '.$firstSkip['reason'];
                }

                return redirect()->route('disconnection.index')
                    ->with('disconnection_save_warning', $message);
            }

            $message = '';
            if ($result['created'] > 0) {
                $message .= $result['created'].' disconnection order(s) created and assigned to disconnector.';
            }
            if (($result['updated'] ?? 0) > 0) {
                $message .= ($message !== '' ? ' ' : '')
                    .$result['updated'].' existing order(s) updated (reassigned / refreshed for mobile).';
            }
            if ($result['skipped'] > 0) {
                $message .= ' '.$result['skipped'].' order(s) skipped (not applicable).';
            }

            return redirect()->route('disconnection.index')
                ->with('success', trim($message));
        } catch (\Exception $e) {
            Log::error('Error saving disconnection orders: '.$e->getMessage());

            return redirect()->route('disconnection.index')
                ->with('error', 'Error saving disconnection orders: '.$e->getMessage());
        }
    }

    /**
     * Resolve the disconnection history date range (disconnected_at) for assignments list / export.
     * Supports legacy ?date_saved=YYYY-MM-DD (single day, same as from and to).
     *
     * @return array{from: \Carbon\Carbon, to: \Carbon\Carbon, from_date: string, to_date: string}
     */
    private function resolveAssignmentsDisconnectionDateRange(Request $request): array
    {
        $fromStr = trim((string) $request->input('disconnected_from', ''));
        $toStr = trim((string) $request->input('disconnected_to', ''));

        if ($request->filled('date_saved')) {
            $fromStr = $toStr = (string) $request->input('date_saved');
        }

        if ($fromStr === '' && $toStr === '') {
            $fromStr = Carbon::now()->startOfMonth()->toDateString();
            $toStr = Carbon::now()->toDateString();
        } elseif ($fromStr !== '' && $toStr === '') {
            $toStr = Carbon::now()->toDateString();
        } elseif ($fromStr === '' && $toStr !== '') {
            $fromStr = Carbon::parse($toStr)->startOfMonth()->toDateString();
        }

        try {
            $from = Carbon::parse($fromStr)->startOfDay();
            $to = Carbon::parse($toStr)->endOfDay();
        } catch (\Throwable) {
            $from = Carbon::now()->startOfMonth()->startOfDay();
            $to = Carbon::now()->endOfDay();
            $fromStr = $from->toDateString();
            $toStr = $to->toDateString();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            $fromStr = $from->toDateString();
            $toStr = $to->toDateString();
        }

        return [
            'from' => $from,
            'to' => $to,
            'from_date' => $fromStr,
            'to_date' => $toStr,
        ];
    }

    /**
     * Orders with a recorded disconnect time within the selected range (disconnected_at).
     *
     * @return Builder<DisconnectionOrder>
     */
    private function assignmentsFilteredQuery(Request $request): Builder
    {
        $range = $this->resolveAssignmentsDisconnectionDateRange($request);
        $doDisconnectedAt = mr_col('disconnected_at');

        $query = DisconnectionOrder::query()
            ->with(['consumer', 'disconnector']);

        $query->whereNotNull($doDisconnectedAt)
            ->whereBetween($doDisconnectedAt, [$range['from'], $range['to']])
            ->orderByDesc($doDisconnectedAt);

        return $query;
    }

    /**
     * Past disconnection activity by disconnected_at date range.
     */
    public function assignments(Request $request)
    {
        $range = $this->resolveAssignmentsDisconnectionDateRange($request);
        $orders = $this->assignmentsFilteredQuery($request)->paginate(20)->withQueryString();

        return view('disconnection.assignments', compact('orders', 'range'));
    }

    /**
     * Export filtered disconnection assignments to Excel (all rows, not only current page).
     */
    public function exportAssignments(Request $request)
    {
        $statusLabels = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'in-progress' => 'In Progress',
            'disconnected' => 'Disconnected',
            'reconnected' => 'Reconnected',
        ];

        $records = $this->assignmentsFilteredQuery($request)
            ->get()
            ->map(function (DisconnectionOrder $order) use ($statusLabels) {
                if ($order->disconnected_at) {
                    $disconnectionDate = $order->disconnected_at->format('M d, Y h:i A');
                } elseif ($order->disconnection_date) {
                    $disconnectionDate = Carbon::parse($order->disconnection_date)->format('M d, Y');
                } else {
                    $disconnectionDate = 'N/A';
                }

                return [
                    $order->account_no ?? '',
                    $order->account_name ?? '',
                    $order->zone_code ?? '',
                    round((float) $order->total_outstanding, 2),
                    $disconnectionDate,
                    $statusLabels[$order->status] ?? ucfirst((string) $order->status),
                    optional($order->disconnector)->name ?? '',
                ];
            });

        $range = $this->resolveAssignmentsDisconnectionDateRange($request);
        $filename = 'Disconnection-History-'.$range['from_date'].'_to_'.$range['to_date'].'-'.Carbon::now()->format('His').'.xlsx';

        return Excel::download(
            new class($records) implements FromCollection, WithHeadings, WithTitle
            {
                public function __construct(
                    private Collection $rows
                ) {}

                public function collection()
                {
                    return $this->rows;
                }

                public function headings(): array
                {
                    return [
                        'Account No.',
                        'Account Name',
                        'Zone',
                        'Total Outstanding',
                        'Disconnection Date',
                        'Status',
                        'Assigned To',
                    ];
                }

                public function title(): string
                {
                    return 'Disconnection History';
                }
            },
            $filename
        );
    }

    /**
     * Assign orders to a disconnector
     */
    public function assignOrders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:disconnection_orders,id',
            'disconnector_id' => 'required|exists:users,id',
        ]);

        $orderIds = $request->input('order_ids');
        $disconnectorId = $request->input('disconnector_id');

        try {
            DisconnectionOrder::whereIn('id', $orderIds)->update([
                'disconnector_id' => $disconnectorId,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            return redirect()->route('disconnection.assignments')
                ->with('success', count($orderIds).' order(s) assigned to disconnector.');

        } catch (\Exception $e) {
            return redirect()->route('disconnection.assignments')
                ->with('error', 'Error assigning orders: '.$e->getMessage());
        }
    }

    /**
     * Update editable fields of a disconnection order from the Orders tab.
     */
    public function updateOrder(Request $request, int $orderId)
    {
        $request->validate([
            'current_bill_with_maintenance' => 'required|numeric|min:0',
        ]);

        try {
            $order = DisconnectionOrder::findOrFail($orderId);
            $order->update([
                'current_bill_with_maintenance' => round((float) $request->input('current_bill_with_maintenance'), 2),
            ]);

            return redirect()->back()->with('success', 'Order updated successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error updating order: '.$e->getMessage());
        }
    }

    private function consumersByIdForZoneIds(iterable $consumerIds): Collection
    {
        $ids = collect($consumerIds)->filter()->unique()->values()->all();

        return $ids === []
            ? collect()
            : ConsumerZone::query()->whereIn(self::consumerZoneIdColumn(), $ids)->get()->keyBy(self::consumerZoneIdColumn());
    }

    private function accountNoForScheduleConsumer(object $schedule, Collection $consumersById): string
    {
        $consumerZoneId = is_object($schedule)
            ? ($schedule->consumer_zone_id ?? null)
            : ($schedule['consumer_zone_id'] ?? null);

        return trim((string) ($consumersById->get($consumerZoneId)?->account_no ?? ''));
    }

    private function applyScheduleBillingFilter(Builder $query, ?string $billingFilter, bool $isMonthFilter): void
    {
        if (!$billingFilter) {
            return;
        }

        if ($isMonthFilter) {
            $mrsBillMonth = mr_col('bill_month');
            $mrsBillDate = mr_col('bill_date');
            $monthCarbon = Carbon::createFromFormat('Y-m', $billingFilter)->startOfMonth();
            $query->where(function ($q) use ($monthCarbon, $mrsBillMonth, $mrsBillDate) {
                $q->whereYear($mrsBillMonth, $monthCarbon->year)
                    ->whereMonth($mrsBillMonth, $monthCarbon->month)
                    ->orWhere(function ($subQ) use ($monthCarbon, $mrsBillDate) {
                        $subQ->whereYear($mrsBillDate, $monthCarbon->year)
                            ->whereMonth($mrsBillDate, $monthCarbon->month);
                    });
            });
        } else {
            $mrsBillDate = mr_col('bill_date');
            $mrsBillMonth = mr_col('bill_month');
            $billingDateCarbon = Carbon::parse($billingFilter);
            $query->where(function ($q) use ($billingDateCarbon, $mrsBillDate, $mrsBillMonth) {
                $q->whereDate($mrsBillDate, $billingDateCarbon->format('Y-m-d'))
                    ->orWhereDate($mrsBillMonth, $billingDateCarbon->format('Y-m-01'))
                    ->orWhere(function ($subQ) use ($billingDateCarbon, $mrsBillMonth) {
                        $subQ->whereYear($mrsBillMonth, $billingDateCarbon->year)
                            ->whereMonth($mrsBillMonth, $billingDateCarbon->month);
                    });
            });
        }
    }

    private function latestScheduleReadingsByAccount(iterable $consumerIds, Collection $consumersById, ?string $billingFilter = null, bool $isMonthFilter = false): Collection
    {
        $clConsumerZoneId = mr_col('consumer_zone_id');
        $mrsReadingDate = mr_col('reading_date');
        $mrsBillDate = mr_col('bill_date');
        $mrsDueDate = mr_col('due_date');
        $mrsId = self::meterReadingScheduleIdColumn();

        $query = MeterReadingSchedule::query()
            ->whereIn($clConsumerZoneId, $consumerIds)
            ->whereNotNull(mr_col('current_reading'));
        $this->applyScheduleBillingFilter($query, $billingFilter, (bool) $isMonthFilter);

        return $query
            ->orderBy($clConsumerZoneId)
            ->orderByDesc($mrsReadingDate)
            ->orderByDesc($mrsBillDate)
            ->orderByDesc($mrsDueDate)
            ->orderByDesc($mrsId)
            ->get()
            ->groupBy(fn ($schedule) => $this->accountNoForScheduleConsumer($schedule, $consumersById))
            ->map(function ($items) {
                $first = $items->first();

                return $first ? (float) ($first->current_reading ?? 0) : 0.0;
            });
    }

    private function latestCurrentBillsByAccount(iterable $consumerIds, Collection $consumersById): Collection
    {
        $drTable = mr_col('downloaded_readings as dr');
        $drConsumerZoneId = mr_col('dr.consumer_zone_id');
        $drCurrentBill = mr_col('dr.current_bill');
        $drReadingDate = mr_col('dr.reading_date');
        $drCreatedAt = mr_col('dr.created_at');
        $drId = mr_col('dr.id');

        return DB::table($drTable)
            ->whereIn($drConsumerZoneId, $consumerIds)
            ->whereNotNull($drCurrentBill)
            ->orderBy($drConsumerZoneId)
            ->orderByDesc($drReadingDate)
            ->orderByDesc($drCreatedAt)
            ->orderByDesc($drId)
            ->get([$drConsumerZoneId, $drCurrentBill])
            ->groupBy(fn ($row) => $this->accountNoForScheduleConsumer($row, $consumersById))
            ->map(function ($items) {
                $first = $items->first();

                return $first ? (float) ($first->current_bill ?? 0) : 0.0;
            });
    }
}
