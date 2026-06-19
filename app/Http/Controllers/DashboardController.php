<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZone;
use App\Models\DownloadedReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $czTable = mr_col('consumer_zone');
        $statusCodeColumn = mr_col('status_code');
        $createdAtColumn = mr_col('created_at');
        $cpTable = mr_col('consumer_payments');
        $paymentAmountColumn = mr_col('payment_amount');
        $paidAtColumn = mr_col('paid_at');
        $cpPaidAt = mr_col('cp.paid_at');
        $installDateColumn = mr_col('install_date');
        $drTable = mr_col('downloaded_readings as dr');
        $drTablePlain = mr_col('downloaded_readings');
        $cpTableAlias = mr_col('consumer_payments as cp');
        $cpReadingId = mr_col('cp.reading_id');
        $drId = mr_col('dr.id');
        $drCreatedAt = mr_col('dr.created_at');
        $drReadingDate = mr_col('dr.reading_date');
        $drConsumerZoneId = mr_col('dr.consumer_zone_id');
        $readingDateColumn = mr_col('reading_date');
        $consumptionColumn = mr_col('consumption');
        $czTableAlias = mr_col('consumer_zone as cz');

        $activeConsumersCurrent = DB::table($czTable)
            ->where($statusCodeColumn, 'A')
            ->count();
        $activeConsumersPrevious = DB::table($czTable)
            ->where($statusCodeColumn, 'A')
            ->where($createdAtColumn, '<=', $previousMonthEnd)
            ->count();

        $monthlyCollectionsCurrent = DB::table($cpTable)
            ->whereNotNull($paymentAmountColumn)
            ->whereBetween($paidAtColumn, [$currentMonthStart, $currentMonthEnd])
            ->sum($paymentAmountColumn);
        $monthlyCollectionsPrevious = DB::table($cpTable)
            ->whereNotNull($paymentAmountColumn)
            ->whereBetween($paidAtColumn, [$previousMonthStart, $previousMonthEnd])
            ->sum($paymentAmountColumn);

        
           $newConnectionsCurrent = ConsumerZone::query()
            ->whereNotNull($installDateColumn)
            ->whereBetween($installDateColumn, [$currentMonthStart, $currentMonthEnd])
            ->count();
           $newConnectionsPrevious = ConsumerZone::query()
            ->whereNotNull($installDateColumn)
            ->whereBetween($installDateColumn, [$previousMonthStart, $previousMonthEnd])
            ->count();

        $pendingBillsCurrent = DB::table($drTable)
            ->leftJoin($cpTableAlias, $cpReadingId, '=', $drId)
            ->whereNull($cpPaidAt)
            ->whereBetween($drCreatedAt, [$currentMonthStart, $currentMonthEnd])
            ->count();
        $pendingBillsPrevious = DB::table($drTable)
            ->leftJoin($cpTableAlias, $cpReadingId, '=', $drId)
            ->whereNull($cpPaidAt)
            ->whereBetween($drCreatedAt, [$previousMonthStart, $previousMonthEnd])
            ->count();

        $metrics = [
            'active_consumers' => $this->formatMetric($activeConsumersCurrent, $activeConsumersPrevious),
            'monthly_collections' => $this->formatMetric($monthlyCollectionsCurrent, $monthlyCollectionsPrevious),
            'new_connections' => $this->formatMetric($newConnectionsCurrent, $newConnectionsPrevious),
            'pending_bills' => $this->formatMetric($pendingBillsCurrent, $pendingBillsPrevious, false),
        ];

        $months = collect(range(0, 11))
            ->map(fn ($i) => Carbon::now()->subMonths($i)->startOfMonth())
            ->sortBy(fn ($date) => $date->timestamp)
            ->values();

        $consumptionLabels = $months->map(fn (Carbon $date) => $date->format('M'));
        $consumptionData = $months->map(function (Carbon $date) use ($drTablePlain, $readingDateColumn, $consumptionColumn) {
            return DB::table($drTablePlain)
                ->whereBetween($readingDateColumn, [$date->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d')])
                ->sum(DB::raw('COALESCE(consumption, 0)'));
        });

        // Monthly billing status: Paid vs Unpaid (for readings billed within the selected month).
        $monthlyBilledReadings = DB::table($drTable)
            ->whereBetween($drReadingDate, [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
            ->whereNotNull($drConsumerZoneId)
            ->count($drId);

        $paidMonthlyBilledReadings = DB::table($drTable)
            ->whereBetween($drReadingDate, [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
            ->whereNotNull($drConsumerZoneId)
            ->whereExists(function ($query) use ($cpTableAlias, $cpReadingId, $drId, $cpPaidAt) {
                $query->select(DB::raw(1))
                    ->from($cpTableAlias)
                    ->whereColumn($cpReadingId, $drId)
                    ->whereNotNull($cpPaidAt);
            })
            ->count($drId);

        $unpaidMonthlyBilledReadings = max(0, $monthlyBilledReadings - $paidMonthlyBilledReadings);

        $billingStatus = [
            'paid' => (int) $paidMonthlyBilledReadings,
            'unpaid' => (int) $unpaidMonthlyBilledReadings,
        ];

        $recentMessages = DB::table($drTable)
            ->leftJoin($czTableAlias, $drConsumerZoneId, '=', mr_col('cz.id'))
            ->leftJoin($cpTableAlias, $cpReadingId, '=', $drId)
            ->select(
                'cz.account_name',
                'cp.remarks as payment_remarks',
                'dr.reader_notes',
                'dr.updated_at',
                'dr.created_at',
                DB::raw('COALESCE(cp.updated_at, dr.updated_at, dr.created_at) as message_timestamp')
            )
            ->where(function($query) {
                $query->whereNotNull('cp.remarks')
                      ->orWhereNotNull('dr.reader_notes');
            })
            ->orderByDesc(DB::raw('COALESCE(cp.updated_at, dr.updated_at, dr.created_at)'))
            ->limit(5)
            ->get()
            ->map(function ($reading) {
                $message = $reading->payment_remarks ?: $reading->reader_notes;
                if (!$message) {
                    return null;
                }

                $timestamp = $reading->message_timestamp ? Carbon::parse($reading->message_timestamp) : null;

                return [
                    'account' => $reading->account_name ?? 'Unknown account',
                    'message' => $message,
                    'timestamp' => $timestamp ? $timestamp->diffForHumans() : '—',
                ];
            })
            ->filter()
            ->values();

        $cards = [
            [
                'title' => 'Active Consumers',
                'value' => $metrics['active_consumers']['current'],
                'change_percent' => $metrics['active_consumers']['change_percent'],
                'trend' => $metrics['active_consumers']['trend'],
                'subtitle' => 'vs last month',
                'icon' => 'users',
                'icon_color' => 'text-info',
                'format' => 'number',
            ],
            [
                'title' => 'Collections (Monthly)',
                'value' => $metrics['monthly_collections']['current'],
                'change_percent' => $metrics['monthly_collections']['change_percent'],
                'trend' => $metrics['monthly_collections']['trend'],
                'subtitle' => 'vs last month',
                'icon' => 'hand-holding-usd',
                'icon_color' => 'text-success',
                'format' => 'currency',
            ],
             [
                 'title' => 'New Connections',
                 'value' => $metrics['new_connections']['current'],
                 'change_percent' => $metrics['new_connections']['change_percent'],
                 'trend' => $metrics['new_connections']['trend'],
                 'subtitle' => 'vs last month',
                 'icon' => 'faucet',
                 'icon_color' => 'text-primary',
                 'format' => 'number',
             ],
            [
                'title' => 'Pending Bills',
                'value' => $metrics['pending_bills']['current'],
                'change_percent' => $metrics['pending_bills']['change_percent'],
                'trend' => $metrics['pending_bills']['trend'],
                'subtitle' => 'vs last month',
                'icon' => 'file-invoice-dollar',
                'icon_color' => 'text-warning',
                'format' => 'number',
            ],
        ];

        $charts = [
            'consumption' => [
                'labels' => $consumptionLabels,
                'data' => $consumptionData,
            ],
            'billing_status' => [
                'labels' => ['Paid', 'Unpaid'],
                'data' => array_values($billingStatus),
                'total' => (int) $monthlyBilledReadings,
                'period' => $currentMonthStart->format('F Y'),
            ],
        ];

        return view('welcome', [
            'cards' => $cards,
            'charts' => $charts,
            'recentMessages' => $recentMessages,
        ]);
    }

    protected function formatMetric(float $current, float $previous, bool $allowNegative = true): array
    {
        $current = (float) $current;
        $previous = (float) $previous;
        $difference = $current - $previous;
        $trend = $difference >= 0 ? 'up' : 'down';

        if (!$allowNegative && $difference < 0) {
            $trend = 'down';
        }

        $changePercent = null;
        if ($previous > 0) {
            $changePercent = round(($difference / $previous) * 100, 1);
        } elseif ($current > 0) {
            $changePercent = 100.0;
        } else {
            $changePercent = 0.0;
        }

        if (!$allowNegative && $current < 0) {
            $current = 0;
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $changePercent,
            'trend' => $trend,
        ];
    }
}
