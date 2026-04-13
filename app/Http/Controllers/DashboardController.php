<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZoneOne;
use App\Models\DownloadedReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $activeConsumersCurrent = DB::table('consumer_zone')
            ->where('status_code', 'A')
            ->count();
        $activeConsumersPrevious = DB::table('consumer_zone')
            ->where('status_code', 'A')
            ->where('created_at', '<=', $previousMonthEnd)
            ->count();

        $monthlyCollectionsCurrent = DB::table('consumer_payments')
            ->whereNotNull('payment_amount')
            ->whereBetween('paid_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('payment_amount');
        $monthlyCollectionsPrevious = DB::table('consumer_payments')
            ->whereNotNull('payment_amount')
            ->whereBetween('paid_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('payment_amount');

        
           $newConnectionsCurrent = ConsumerZoneOne::whereNotNull('install_date')
            ->whereBetween('install_date', [$currentMonthStart, $currentMonthEnd])
            ->count();
           $newConnectionsPrevious = ConsumerZoneOne::whereNotNull('install_date')
            ->whereBetween('install_date', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $pendingBillsCurrent = DB::table('downloaded_readings as dr')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->whereNull('cp.paid_at')
            ->whereBetween('dr.created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $pendingBillsPrevious = DB::table('downloaded_readings as dr')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->whereNull('cp.paid_at')
            ->whereBetween('dr.created_at', [$previousMonthStart, $previousMonthEnd])
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
        $consumptionData = $months->map(function (Carbon $date) {
            return DB::table('downloaded_readings')
                ->whereBetween('reading_date', [$date->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d')])
                ->sum(DB::raw('COALESCE(consumption, 0)'));
        });

        // Get billing status from downloaded_readings and check for overdue based on meter_reading_schedules
        $billingStatusQuery = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->select(
                DB::raw("
                    CASE 
                        WHEN cp.paid_at IS NOT NULL THEN 'paid'
                        WHEN mrs.due_date IS NOT NULL AND mrs.due_date < NOW() AND (cp.paid_at IS NULL OR cp.paid_at = '') THEN 'overdue'
                        ELSE LOWER(COALESCE(dr.status, 'pending'))
                    END as billing_status
                ")
            )
            ->get()
            ->groupBy('billing_status')
            ->map(fn($group) => $group->count());

        $billingStatus = [
            'paid' => (int) ($billingStatusQuery['paid'] ?? 0),
            'pending' => (int) ($billingStatusQuery['pending'] ?? $billingStatusQuery['downloaded'] ?? 0),
            'overdue' => (int) ($billingStatusQuery['overdue'] ?? 0),
        ];

        $recentMessages = DB::table('downloaded_readings as dr')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->select(
                'dr.account_name',
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
                'labels' => ['Paid', 'Pending', 'Overdue'],
                'data' => array_values($billingStatus),
            ],
        ];

        return view('welcome', [
            'cards' => $cards,
            'charts' => $charts,
            'recentMessages' => $recentMessages,
        ]);
    }

    protected function formatMetric($current, $previous, $allowNegative = true): array
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
