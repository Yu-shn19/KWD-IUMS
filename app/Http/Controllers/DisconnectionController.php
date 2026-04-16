<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConsumerZoneOne;
use App\Models\ConsumerLedger;
use App\Models\DisconnectionOrder;
use App\Models\MeterReadingSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisconnectionController extends Controller
{
    /**
     * Display list of consumers eligible for disconnection by zone
     */
    public function index(Request $request)
    {
        $zone = $request->get('zone');
        $billingMonth = $request->get('billing_month'); // Format: YYYY-MM
        $billingDate = $request->get('billing_date');
        $filterType = $request->get('filter_type', 'disconnection_date'); // 'disconnection_date' or '3_consecutive'
        
        // Use different filter based on filter_type parameter
        if ($filterType === '3_consecutive') {
            $consumers = $this->getConsumersWith3ConsecutiveUnpaidMonths($request);
            // The method already returns the view, so we return it directly
            return $consumers;
        } else {
            // Default: use disconnection date filter
            // Priority: billing_month > billing_date
            $billingFilter = $billingMonth ?: $billingDate;
            $isMonthFilter = !empty($billingMonth);
            $consumers = $this->getConsumersForDisconnection($zone, $billingFilter, $isMonthFilter);
            
            // Group by zone
            $consumersByZone = $consumers->groupBy('zone_code');
            
            // Get all zones for filter
            $zones = ConsumerZoneOne::select('zone_code')
                ->distinct()
                ->whereNotNull('zone_code')
                ->orderBy('zone_code')
                ->pluck('zone_code');
            
            // Get disconnectors for dropdown
            $disconnectors = User::where('role', 'disconnector')
                ->orderBy('name')
                ->get();
            
            // Calculate totals
            $totalConsumers = $consumers->count();
            $totalOutstanding = $consumers->sum('total_outstanding');

            // Default disconnection date for the form: from schedule when billing month/date is selected
            $defaultDisconnectionDate = null;
            if ($billingFilter) {
                $querySchedule = function ($withZone) use ($zone, $billingFilter, $isMonthFilter) {
                    $q = MeterReadingSchedule::whereNotNull('disconnection_date');
                    if ($withZone && $zone) {
                        $q->where('zone', $zone);
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
                    return $q->orderBy('disconnection_date')->first();
                };
                $schedule = $querySchedule(true);
                if (!$schedule && $zone) {
                    $schedule = $querySchedule(false); // fallback: any zone for this billing month
                }
                if ($schedule && $schedule->disconnection_date) {
                    $defaultDisconnectionDate = Carbon::parse($schedule->disconnection_date)->format('Y-m-d');
                }
                // Fallback: use first consumer's disconnection date from the list (from their schedule)
                if (!$defaultDisconnectionDate && $consumers->isNotEmpty()) {
                    $first = $consumers->first();
                    if (!empty($first->disconnection_date)) {
                        $defaultDisconnectionDate = Carbon::parse($first->disconnection_date)->format('Y-m-d');
                    }
                }
            }
            if (!$defaultDisconnectionDate) {
                $defaultDisconnectionDate = Carbon::today()->addDays(7)->format('Y-m-d');
            }

            return view('disconnection.index', compact('consumersByZone', 'zones', 'zone', 'filterType', 'disconnectors', 'totalConsumers', 'totalOutstanding', 'billingDate', 'billingMonth', 'defaultDisconnectionDate'));
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
        $isMonthFilter = !empty($billingMonth);
        if (!$billingFilter) {
            return Carbon::today()->addDays(7)->format('Y-m-d');
        }
        $querySchedule = function ($withZone) use ($zone, $billingFilter, $isMonthFilter) {
            $q = MeterReadingSchedule::whereNotNull('disconnection_date');
            if ($withZone && $zone) {
                $q->where('zone', $zone);
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
            return $q->orderBy('disconnection_date')->first();
        };
        $schedule = $querySchedule(true);
        if (!$schedule && $zone) {
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
        ]);

        $consumerIds = $request->input('consumer_ids');
        $disconnectionDate = Carbon::parse($request->input('disconnection_date'));
        
        $consumers = ConsumerZoneOne::whereIn('id', $consumerIds)
            ->with(['ledgers' => function($query) {
                $query->where('trans', 'BILLING')
                    ->where(function($q) {
                        $q->whereNull('credit')->orWhere('credit', 0);
                    })
                    ->where('due_date', '<', now())
                    ->orderBy('due_date', 'desc');
            }])
            ->get();

        $penaltyLedgersByConsumer = ConsumerLedger::whereIn('consumer_zone_id', $consumers->pluck('id'))
            ->where('trans', 'PENALTY')
            ->get()
            ->groupBy('consumer_zone_id');

        $consumersWithOutstanding = $consumers->map(function($consumer) use ($penaltyLedgersByConsumer) {
            $ledgers = $consumer->ledgers;
            // Use direct ledger table balance so total is correct (avoids getLedger context issues)
            $totalAmountDue = $this->getCurrentBalanceFromLedgerTable($consumer);

            $penalties = $penaltyLedgersByConsumer->get($consumer->id, collect());

            $firstLedger = $ledgers->first();

            $thisMonthBill = $firstLedger ? (float)($firstLedger->debit ?? 0) : 0;
            $firstDueDate = $firstLedger && $firstLedger->due_date ? Carbon::parse($firstLedger->due_date)->format('Y-m-d') : null;
            $thisMonthPenalty = $firstDueDate ? $penalties->sum(function($p) use ($firstDueDate) {
                $pDue = $p->due_date ? Carbon::parse($p->due_date)->format('Y-m-d') : null;
                return $pDue === $firstDueDate ? (float)($p->debit ?? $p->penalty ?? 0) : 0;
            }) : 0;
            $thisMonthArrears = $thisMonthBill + $thisMonthPenalty;

            // Last Month/ Arrears CY = remaining amount (total minus This Month/ Arrears)
            $lastMonthArrears = $totalAmountDue - $thisMonthArrears;
            $lastMonthArrears = $lastMonthArrears > 0 ? round($lastMonthArrears, 2) : 0;

            $consumer->setAttribute('this_month_arrears', round($thisMonthArrears, 2));
            $consumer->setAttribute('last_month_arrears', (float) $lastMonthArrears);
            $consumer->setAttribute('others_ar', 0);
            $consumer->setAttribute('total_outstanding', $totalAmountDue);
            $consumer->setAttribute('unpaid_months', $this->adjustUnpaidMonthsForPriorArrears((int) $ledgers->count(), (float) $lastMonthArrears));
            $consumer->setAttribute('card_number', $consumer->sequence ?? '1');

            return $consumer;
        })
        ->filter(function($consumer) {
            // Exclude consumers with no Last Month/Arrears CY (i.e., only current month due)
            return $consumer->total_outstanding > 0
                && ($consumer->last_month_arrears ?? 0) > 0.01;
        });

        return view('disconnection.notice', compact('consumersWithOutstanding', 'disconnectionDate'));
    }

    /**
     * Get consumers who have passed disconnection date from meter_reading_schedules and haven't paid
     */
    private function getConsumersForDisconnection($zone = null, $billingFilter = null, $isMonthFilter = false)
    {
        $today = Carbon::today();
        
        // Get unique account numbers from schedules that have passed due date
        $query = DB::table('meter_reading_schedules as mrs')
            ->join('consumer_zone as cz', function($join) {
                $join->on(DB::raw('mrs.account_number COLLATE utf8mb4_unicode_ci'), '=', DB::raw('cz.account_no COLLATE utf8mb4_unicode_ci'));
            })
            ->where('mrs.due_date', '<=', $today)
            ->whereNotNull('mrs.due_date')
            ->select('cz.account_no', 'cz.id as consumer_zone_id')
            ->distinct();

        if ($zone) {
            $query->where('cz.zone_code', $zone);
        }

        // Filter by billing month or billing date if provided
        if ($billingFilter) {
            if ($isMonthFilter) {
                // Filter by month (format: YYYY-MM)
                $monthCarbon = Carbon::createFromFormat('Y-m', $billingFilter)->startOfMonth();
                $query->where(function($q) use ($monthCarbon) {
                    $q->whereYear('mrs.bill_month', $monthCarbon->year)
                      ->whereMonth('mrs.bill_month', $monthCarbon->month)
                      ->orWhere(function($subQ) use ($monthCarbon) {
                          // Also check bill_date falls within the month
                          $subQ->whereYear('mrs.bill_date', $monthCarbon->year)
                               ->whereMonth('mrs.bill_date', $monthCarbon->month);
                      });
                });
            } else {
                // Filter by specific billing date
                $billingDateCarbon = Carbon::parse($billingFilter);
                $query->where(function($q) use ($billingDateCarbon) {
                    $q->whereDate('mrs.bill_date', $billingDateCarbon->format('Y-m-d'))
                      ->orWhereDate('mrs.bill_month', $billingDateCarbon->format('Y-m-01'))
                      ->orWhere(function($subQ) use ($billingDateCarbon) {
                          // Also check if billing date falls within the bill month
                          $subQ->whereYear('mrs.bill_month', $billingDateCarbon->year)
                               ->whereMonth('mrs.bill_month', $billingDateCarbon->month);
                      });
                });
            }
        }

        $accountData = $query->get();
        $accountNos = $accountData->pluck('account_no')->unique();
        $consumerIds = $accountData->pluck('consumer_zone_id')->unique();

        if ($accountNos->isEmpty()) {
            return collect();
        }

        // BULK: Get all consumers at once
        $consumers = ConsumerZoneOne::whereIn('account_no', $accountNos)->get()->keyBy('account_no');

        // BULK: Calculate balances using same method as ledger view (running balance calculation)
        $latestBalances = $this->calculateBalancesBulk($consumerIds);

        // BULK: Get all schedules for all accounts (with billing month/date filter if provided), based on due_date
        $schedulesQuery = MeterReadingSchedule::whereIn('account_number', $accountNos)
            ->where('due_date', '<=', $today)
            ->whereNotNull('due_date');
        
        // Filter by billing month or billing date if provided
        if ($billingFilter) {
            if ($isMonthFilter) {
                // Filter by month (format: YYYY-MM)
                $monthCarbon = Carbon::createFromFormat('Y-m', $billingFilter)->startOfMonth();
                $schedulesQuery->where(function($q) use ($monthCarbon) {
                    $q->whereYear('bill_month', $monthCarbon->year)
                      ->whereMonth('bill_month', $monthCarbon->month)
                      ->orWhere(function($subQ) use ($monthCarbon) {
                          $subQ->whereYear('bill_date', $monthCarbon->year)
                               ->whereMonth('bill_date', $monthCarbon->month);
                      });
                });
            } else {
                // Filter by specific billing date
                $billingDateCarbon = Carbon::parse($billingFilter);
                $schedulesQuery->where(function($q) use ($billingDateCarbon) {
                    $q->whereDate('bill_date', $billingDateCarbon->format('Y-m-d'))
                      ->orWhereDate('bill_month', $billingDateCarbon->format('Y-m-01'))
                      ->orWhere(function($subQ) use ($billingDateCarbon) {
                          $subQ->whereYear('bill_month', $billingDateCarbon->year)
                               ->whereMonth('bill_month', $billingDateCarbon->month);
                      });
                });
            }
        }
        
        $allSchedules = $schedulesQuery->get()->groupBy('account_number');

        // BULK: Get all billing records for all consumers
        $allBillings = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->whereIn('trans', ['BILLING', 'BILL'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $today)
            ->select('consumer_zone_id', 'schedule_id', 'due_date', 'debit', 'credit', 'balance', 'date')
            ->orderBy('consumer_zone_id')
            ->orderBy('due_date', 'asc')
            ->get()
            ->groupBy('consumer_zone_id');

        // BULK: Get all payment records for all consumers from consumer_ledgers
        $allPayments = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->where('trans', 'PAYMENT')
            ->whereNotNull('schedule_id')
            ->select('consumer_zone_id', 'schedule_id', 'date', 'credit')
            ->get()
            ->groupBy('consumer_zone_id')
            ->map(function($payments) {
                // Group payments by schedule_id for quick lookup
                return $payments->pluck('schedule_id')->unique()->toArray();
            });

        // BULK: Get all penalty records for all consumers (used to compute This Month/Arrears vs Last Month/Arrears CY)
        $penaltyLedgersByConsumer = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->where('trans', 'PENALTY')
            ->get()
            ->groupBy('consumer_zone_id');

        // BULK: Get last PAYMENT date for each consumer from consumer_ledgers
        $lastPayments = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->where('trans', 'PAYMENT')
            ->whereNotNull('date')
            ->orderBy('consumer_zone_id')
            ->orderByRaw('CAST(date AS DATE) DESC')
            ->get()
            ->groupBy('consumer_zone_id')
            ->map(function ($payments) {
                return $payments->first();
            });

        $eligibleConsumers = collect();
        $twoMonthsAgo = Carbon::today()->subMonths(2);

        foreach ($accountNos as $accountNo) {
            $consumer = $consumers->get($accountNo);
            
            if (!$consumer) {
                continue;
            }

            // Get current balance from bulk query (latest balance from consumer_ledgers)
            // This matches the ledger view balance which uses the latest balance entry
            $currentBalance = $latestBalances->get($consumer->id, 0);

            // Derive This Month/Arrears amount from the latest BILL/BILLING entry
            $consumerBillings = $allBillings->get($consumer->id, collect());

            // Check if there has been a PAYMENT transaction within the last 2 months
            $lastPaymentEntry = $lastPayments->get($consumer->id);
            $noRecentPayment = !$lastPaymentEntry
                || Carbon::parse($lastPaymentEntry->date)->lt($twoMonthsAgo);
            
            // Only include if they have outstanding balance and no recent payment
            if ($currentBalance > 0 && $noRecentPayment) {
                // Calculate unpaid dates based on PAYMENT records and collection table
                $unpaidDatesData = $this->calculateUnpaidDates(
                    $consumer->id, 
                    $allBillings->get($consumer->id, collect()), 
                    $allPayments->get($consumer->id, [])
                );

                // Exclude consumers whose Total Amount Due equals only This Month/Arrears (no Last Month/Arrears CY)
                $lastMonthArrearsAmount = $this->computeLastMonthArrearsAmount(
                    $currentBalance,
                    $consumerBillings,
                    $penaltyLedgersByConsumer->get($consumer->id, collect())
                );

                // Skip consumers where Last Month/Arrears CY is effectively zero (<= 0.01)
                if ($lastMonthArrearsAmount <= 0.01) {
                    continue;
                }
                
                // Get schedules from bulk query
                $passedSchedules = $allSchedules->get($accountNo, collect());

                // Get the earliest and latest due dates that have passed
                $earliestDisconnectionDate = $passedSchedules->isNotEmpty() ? $passedSchedules->min('due_date') : null;
                $latestDisconnectionDate = $passedSchedules->isNotEmpty() ? $passedSchedules->max('due_date') : null;

                $consumer->unpaid_months = $this->adjustUnpaidMonthsForPriorArrears(
                    (int) $unpaidDatesData['unpaid_count'],
                    $lastMonthArrearsAmount
                );
                $agingBuckets = $this->computeDisconnectionAgingBreakdown(
                    $consumerBillings,
                    $allPayments->get($consumer->id, []),
                    (float) $currentBalance,
                    $penaltyLedgersByConsumer->get($consumer->id, collect())
                );
                $consumer->total_outstanding = $currentBalance; // Use latest balance from consumer_ledgers
                $consumer->oldest_unpaid_date = $unpaidDatesData['oldest_unpaid_date'];
                $consumer->latest_unpaid_date = $unpaidDatesData['latest_unpaid_date'];
                $consumer->disconnection_date = $earliestDisconnectionDate;
                $consumer->latest_disconnection_date = $latestDisconnectionDate;
                $consumer->passed_schedules_count = $passedSchedules->count();
                $consumer->last_reading = $this->getLatestReadingFromSchedules($passedSchedules);
                $consumer->aging_current = $agingBuckets['current'];
                $consumer->aging_30_days = $agingBuckets['days_30'];
                $consumer->aging_60_days = $agingBuckets['days_60'];
                $consumer->aging_90_days = $agingBuckets['days_90'];
                $consumer->aging_over_90 = $agingBuckets['over_90'];
                
                $eligibleConsumers->push($consumer);
            }
        }

        return $eligibleConsumers;
    }

    /**
     * Calculate balances in bulk using the same method as ledger view (getLedger)
     * This ensures the balance matches exactly with what's shown in the Account Ledger view
     */
    private function calculateBalancesBulk($consumerIds)
    {
        // Get all ledger entries for all consumers, ordered chronologically
        $allLedgers = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->orderBy('consumer_zone_id')
            ->orderByRaw('CAST(date AS DATE) ASC')
            ->orderBy('id', 'asc')
            ->select('consumer_zone_id', 'debit', 'credit', 'balance', 'trans', 'date', 'id')
            ->get()
            ->groupBy('consumer_zone_id');

        $balances = [];

        foreach ($allLedgers as $consumerId => $ledgers) {
            if ($ledgers->isEmpty()) {
                $balances[$consumerId] = 0.00;
                continue;
            }

            // Calculate running balance the same way as getLedger()
            $runningBalance = 0.00;
            $firstEntry = $ledgers->first();
            
            // If first entry exists, start with its balance minus its debit/credit to get the starting balance
            if ($firstEntry) {
                $firstBalance = (float)($firstEntry->balance ?? 0);
                $firstDebit = (float)($firstEntry->debit ?? 0);
                $firstCredit = (float)($firstEntry->credit ?? 0);
                $runningBalance = $firstBalance - $firstDebit + $firstCredit;
            }

            // Calculate running balance for all entries
            foreach ($ledgers as $ledger) {
                $debit = (float)($ledger->debit ?? 0);
                $credit = (float)($ledger->credit ?? 0);
                $trans = strtoupper(trim($ledger->trans ?? ''));
                
                // Calculate new balance: previous balance + debit - credit
                $newBalance = $runningBalance + $debit - $credit;
                
                // Special handling for PAYMENT entries: if payment exactly matches balance, show 0.00
                if ($trans === 'PAYMENT' && $credit > 0) {
                    // If payment exactly matches the previous balance (within 0.01 rounding tolerance), set to 0.00
                    if (abs($credit - $runningBalance) <= 0.01) {
                        $newBalance = 0.00;
                    }
                    // If payment is slightly more than balance (within 0.01), also set to 0.00
                    elseif ($runningBalance > 0 && $credit > $runningBalance && ($credit - $runningBalance) <= 0.01) {
                        $newBalance = 0.00;
                    }
                }
                
                // Round to 2 decimal places
                $newBalance = round($newBalance, 2);
                
                // Update running balance for next iteration
                $runningBalance = $newBalance;
            }

            $balances[$consumerId] = round($runningBalance, 2);
        }

        return collect($balances);
    }

    /**
     * Calculate unpaid dates based on BILL/BILLING and PAYMENT records in consumer_ledger.
     * This is used to determine oldest and latest unpaid dates.
     * Note: Total outstanding is calculated using calculateBalancesBulk() to match ledger view balance exactly.
     */
    private function calculateUnpaidDates($consumerId, $billings, $paidScheduleIds = [])
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
            $debit = (float)($billing->debit ?? 0);
            $credit = (float)($billing->credit ?? 0);
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
            if (!$isPaid && !$scheduleId) {
                if ($debit <= $credit) {
                    $isPaid = true;
                }
            }
            
            if (!$isPaid) {
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

    private function getLatestReadingFromSchedules($schedules): float
    {
        if (!$schedules || $schedules->isEmpty()) {
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
    private function computeDisconnectionAgingBreakdown($billings, array $paidScheduleIds, float $currentBalance, $penalties = null): array
    {
        $penalties = $penalties ?? collect();

        // Identical definition to generateNotice: last_month_arrears from computeLastMonthArrearsAmount;
        // this_month_arrears = total - last_month (same as bill + penalty on latest due in that method).
        $lastMonthArrears = $this->computeLastMonthArrearsAmount($currentBalance, $billings, $penalties);
        $thisMonthArrears = round(max(0.0, $currentBalance - $lastMonthArrears), 2);

        $breakdown = [
            'current' => $thisMonthArrears,
            'days_30' => max(0.0, $lastMonthArrears),
            'days_60' => 0.0,
            'days_90' => 0.0,
            'over_90' => 0.0,
        ];

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
     * Outstanding from prior billing cycles (total balance minus latest bill + penalty on latest due date).
     * Same rules as generateNotice / printNotice.
     */
    private function computeLastMonthArrearsAmount(float $currentBalance, $consumerBillings, $penalties): float
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
     * Get the current balance from ledger using the same calculation as ledger view
     * This calls the ConsumerLedgerController to get the exact balance shown in ledger.blade.php
     */
    private function getCurrentBalance(ConsumerZoneOne $consumer): float
    {
        try {
            // Use the same method as ledger view to get accurate balance
            $ledgerController = new ConsumerLedgerController();
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'account_no' => $consumer->account_no,
                'year' => '' // Get all records for accurate balance calculation
            ]);
            
            $ledgerResponse = $ledgerController->getLedger($request);
            $ledgerData = json_decode($ledgerResponse->getContent(), true);
            
            if (isset($ledgerData['summary']['balance'])) {
                return round((float)$ledgerData['summary']['balance'], 2);
            }
        } catch (\Exception $e) {
            \Log::error('Error getting balance from ledger for account ' . $consumer->account_no . ': ' . $e->getMessage());
        }
        
        return $this->getCurrentBalanceFromLedgerTable($consumer);
    }

    /**
     * Get current balance directly from consumer_ledger (same order as ledger view).
     * Used when getLedger is unreliable (e.g. print request context) so Last Month/ Arrears CY gets correct total.
     */
    private function getCurrentBalanceFromLedgerTable(ConsumerZoneOne $consumer): float
    {
        $lastLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->where(function ($q) {
                $q->whereNull('billing_adjustment_id')
                    ->orWhereHas('billingAdjustment', function ($q2) {
                        $q2->where('status', 'Approved');
                    });
            })
            ->orderByRaw('CAST(date AS DATE) ASC')
            ->orderByRaw("CASE WHEN UPPER(TRIM(trans)) IN ('BILLING','BILL') THEN 0 WHEN UPPER(TRIM(trans)) = 'PAYMENT' THEN 1 ELSE 2 END ASC")
            ->orderBy('id', 'asc')
            ->get()
            ->last();

        if ($lastLedger && $lastLedger->balance !== null) {
            return round((float) $lastLedger->balance, 2);
        }

        return round((float)($consumer->balance ?? 0), 2);
    }

    /**
     * Check consecutive unpaid months
     */
    private function checkConsecutiveMonths($unpaidBills)
    {
        if ($unpaidBills->isEmpty()) {
            return 0;
        }

        // Group by month-year
        $months = $unpaidBills->map(function($bill) {
            return $bill->due_date->format('Y-m');
        })->unique()->sort()->values();

        if ($months->isEmpty()) {
            return 0;
        }

        // Check for consecutive months
        $maxConsecutive = 1;
        $currentConsecutive = 1;

        for ($i = 1; $i < $months->count(); $i++) {
            $prevMonth = Carbon::parse($months[$i - 1] . '-01');
            $currentMonth = Carbon::parse($months[$i] . '-01');
            
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
     * Get consumers with 3 consecutive bill months without payment
     * OPTIMIZED: Only checks records from 2025 onwards for faster processing
     */
    private function getConsumersWith3ConsecutiveUnpaidMonths(Request $request)
    {
        $zone = $request->get('zone');
        
        $query = ConsumerZoneOne::query();
        
        if ($zone) {
            $query->where('zone_code', $zone);
        }

        $consumers = $query->get();
        
        if ($consumers->isEmpty()) {
            $zones = ConsumerZoneOne::select('zone_code')
                ->distinct()
                ->whereNotNull('zone_code')
                ->orderBy('zone_code')
                ->pluck('zone_code');
            $consumersByZone = collect();
            $filterType = '3_consecutive';
            $disconnectors = User::where('role', 'disconnector')->orderBy('name')->get();
            $totalConsumers = 0;
            $totalOutstanding = 0;
            $billingMonth = $request->get('billing_month');
            $billingDate = $request->get('billing_date');
            $defaultDisconnectionDate = $this->getDefaultDisconnectionDateFromBilling($zone, $billingMonth, $billingDate);
            return view('disconnection.index', compact('consumersByZone', 'zones', 'zone', 'filterType', 'disconnectors', 'totalConsumers', 'totalOutstanding', 'billingDate', 'billingMonth', 'defaultDisconnectionDate'));
        }

        $consumerIds = $consumers->pluck('id');
        
        // OPTIMIZATION: Only check records from 2025 onwards for faster processing
        // This significantly reduces the dataset size and improves performance
        $startDate = Carbon::create(2025, 1, 1)->startOfDay();
        $today = Carbon::today();
        
        // BULK: Get all billings for all consumers at once (only from 2025)
        $allBillings = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->whereIn('trans', ['BILL', 'BILLING'])
            ->whereNotNull('date')
            ->whereNotNull('due_date')
            ->where(function($q) use ($startDate) {
                $q->whereYear('due_date', '>=', 2025)
                  ->orWhereYear('date', '>=', 2025);
            })
            ->where('due_date', '<=', $today)
            ->orderBy('consumer_zone_id')
            ->orderBy('due_date', 'desc')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('consumer_zone_id');

        // BULK: Calculate balances using same method as ledger view (running balance calculation)
        $latestBalances = $this->calculateBalancesBulk($consumerIds);

        // BULK: Get all payments grouped by consumer (only from 2025)
        // Get paid schedule_ids for quick lookup
        $allPayments = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->where('trans', 'PAYMENT')
            ->whereNotNull('schedule_id')
            ->where(function($q) use ($startDate) {
                $q->whereYear('date', '>=', 2025);
            })
            ->select('consumer_zone_id', 'schedule_id')
            ->get()
            ->groupBy('consumer_zone_id')
            ->map(function($payments) {
                // Return array of paid schedule_ids for quick lookup
                return $payments->pluck('schedule_id')->unique()->toArray();
            });

        $penaltyLedgersByConsumer = ConsumerLedger::whereIn('consumer_zone_id', $consumerIds)
            ->where('trans', 'PENALTY')
            ->get()
            ->groupBy('consumer_zone_id');

        $latestScheduleReadingsByAccount = MeterReadingSchedule::whereIn('account_number', $consumers->pluck('account_no')->filter()->unique()->values())
            ->whereNotNull('current_reading')
            ->orderBy('account_number')
            ->orderByDesc('due_date')
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('account_number')
            ->map(function ($items) {
                $first = $items->first();
                return $first ? (float) ($first->current_reading ?? 0) : 0.0;
            });

        $eligibleConsumers = collect();

        foreach ($consumers as $consumer) {
            $billings = $allBillings->get($consumer->id, collect());

            // Skip if no billings from 2025 onwards
            if ($billings->isEmpty()) {
                continue;
            }

            // Early exit: Need at least 3 billings to have 3 consecutive months
            if ($billings->count() < 3) {
                continue;
            }

            // Get current balance from bulk query (latest balance from consumer_ledgers)
            // This matches the ledger view balance which uses the latest balance entry
            $currentBalance = $latestBalances->get($consumer->id, 0);
            
            // Skip if no outstanding balance
            if ($currentBalance <= 0) {
                continue;
            }
            
            // Calculate unpaid dates based on PAYMENT records and collection table
                $unpaidDatesData = $this->calculateUnpaidDates(
                    $consumer->id, 
                    $billings, 
                    $allPayments->get($consumer->id, [])
                );

            // Check for 3 consecutive months without payment (optimized)
            // Create payment collection format for the check method
            $paidScheduleIds = $allPayments->get($consumer->id, []);
            $paymentsCollection = collect($paidScheduleIds)->map(function($scheduleId) {
                return (object)[
                    'schedule_id' => $scheduleId,
                    'date' => null, // Not needed for schedule_id-based checking
                ];
            });
            
            $consecutiveUnpaid = $this->check3ConsecutiveUnpaidMonthsOptimized($consumer, $billings, $paymentsCollection);
            
            if ($consecutiveUnpaid['has_3_consecutive']) {
                $lastMonthArrearsAmount = $this->computeLastMonthArrearsAmount(
                    $currentBalance,
                    $billings,
                    $penaltyLedgersByConsumer->get($consumer->id, collect())
                );
                $agingBuckets = $this->computeDisconnectionAgingBreakdown(
                    $billings,
                    $allPayments->get($consumer->id, []),
                    (float) $currentBalance,
                    $penaltyLedgersByConsumer->get($consumer->id, collect())
                );
                $consumer->unpaid_months = $this->adjustUnpaidMonthsForPriorArrears(
                    (int) $unpaidDatesData['unpaid_count'],
                    $lastMonthArrearsAmount
                );
                $consumer->total_outstanding = $currentBalance; // Use exact ledger balance
                $consumer->consecutive_unpaid_months = $consecutiveUnpaid['months'];
                $consumer->oldest_unpaid_date = $unpaidDatesData['oldest_unpaid_date'] ?? $consecutiveUnpaid['oldest_date'];
                $consumer->latest_unpaid_date = $unpaidDatesData['latest_unpaid_date'] ?? $consecutiveUnpaid['latest_date'];
                $consumer->last_reading = (float) ($latestScheduleReadingsByAccount->get($consumer->account_no, 0) ?? 0);
                $consumer->aging_current = $agingBuckets['current'];
                $consumer->aging_30_days = $agingBuckets['days_30'];
                $consumer->aging_60_days = $agingBuckets['days_60'];
                $consumer->aging_90_days = $agingBuckets['days_90'];
                $consumer->aging_over_90 = $agingBuckets['over_90'];
                
                $eligibleConsumers->push($consumer);
            }
        }

        // Group by zone
        $consumersByZone = $eligibleConsumers->groupBy('zone_code');
        
        // Get all zones for filter
        $zones = ConsumerZoneOne::select('zone_code')
            ->distinct()
            ->whereNotNull('zone_code')
            ->orderBy('zone_code')
            ->pluck('zone_code');

        // Get disconnectors for dropdown
        $disconnectors = User::where('role', 'disconnector')
            ->orderBy('name')
            ->get();

        // Calculate totals
        $totalConsumers = $eligibleConsumers->count();
        $totalOutstanding = $eligibleConsumers->sum('total_outstanding');

        $filterType = '3_consecutive';
        $billingMonth = $request->get('billing_month');
        $billingDate = $request->get('billing_date');
        $defaultDisconnectionDate = $this->getDefaultDisconnectionDateFromBilling($zone, $billingMonth, $billingDate);
        return view('disconnection.index', compact('consumersByZone', 'zones', 'zone', 'filterType', 'disconnectors', 'totalConsumers', 'totalOutstanding', 'billingDate', 'billingMonth', 'defaultDisconnectionDate'));
    }

    /**
     * Get default disconnection date from meter_reading_schedules when billing month/date is provided.
     */
    private function getDefaultDisconnectionDateFromBilling($zone, $billingMonth, $billingDate, $consumers = null)
    {
        $billingFilter = $billingMonth ?: $billingDate;
        if (!$billingFilter) {
            return Carbon::today()->addDays(7)->format('Y-m-d');
        }
        $isMonthFilter = !empty($billingMonth);
        $querySchedule = function ($withZone) use ($zone, $billingFilter, $isMonthFilter) {
            $q = MeterReadingSchedule::whereNotNull('disconnection_date');
            if ($withZone && $zone) {
                $q->where('zone', $zone);
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
            return $q->orderBy('disconnection_date')->first();
        };
        $schedule = $querySchedule(true);
        if (!$schedule && $zone) {
            $schedule = $querySchedule(false);
        }
        if ($schedule && $schedule->disconnection_date) {
            return Carbon::parse($schedule->disconnection_date)->format('Y-m-d');
        }
        if ($consumers && $consumers->isNotEmpty()) {
            $first = $consumers->first();
            if (!empty($first->disconnection_date)) {
                return Carbon::parse($first->disconnection_date)->format('Y-m-d');
            }
        }
        return Carbon::today()->addDays(7)->format('Y-m-d');
    }

    /**
     * Check if consumer has 3 consecutive months without payment (optimized version)
     * Uses only consumer_ledger records (BILL/BILLING and PAYMENT)
     */
    private function check3ConsecutiveUnpaidMonthsOptimized(ConsumerZoneOne $consumer, $billings, $payments)
    {
        $result = [
            'has_3_consecutive' => false,
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
        $billingMonths = $billings->filter(function($billing) use ($startDate) {
            $dueDate = is_string($billing->due_date) ? Carbon::parse($billing->due_date) : $billing->due_date;
            return $dueDate >= $startDate;
        })->map(function($billing) {
            $dueDate = is_string($billing->due_date) ? Carbon::parse($billing->due_date) : $billing->due_date;
            return [
                'month_key' => $dueDate->format('Y-m'),
                'due_date' => $dueDate,
                'billing' => $billing,
                'debit' => (float)($billing->debit ?? 0),
                'credit' => (float)($billing->credit ?? 0),
                'balance' => (float)($billing->balance ?? 0),
            ];
        })->sortByDesc('due_date')->values(); // Sort descending to check most recent first

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
                if (!isset($paymentsByMonth[$monthKey])) {
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
            
            if (!$hasPayment) {
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

        if (count($unpaidMonths) < 3) {
            return $result;
        }

        // Sort unpaid months by date (ascending - oldest first)
        $unpaidMonths = collect($unpaidMonths)->sortBy('due_date')->values();

        if ($unpaidMonths->isEmpty()) {
            return $result;
        }

        // Check for 3 consecutive months (optimized - stop early when found)
        $consecutiveCount = 1;
        $maxConsecutive = 1;
        $consecutiveMonths = [$unpaidMonths[0]];

        for ($i = 1; $i < $unpaidMonths->count(); $i++) {
            $prevMonth = Carbon::parse($unpaidMonths[$i - 1]['month_key'] . '-01');
            $currentMonth = Carbon::parse($unpaidMonths[$i]['month_key'] . '-01');
            
            // Check if current month is exactly 1 month after previous
            if ($currentMonth->diffInMonths($prevMonth) == 1) {
                $consecutiveCount++;
                $consecutiveMonths[] = $unpaidMonths[$i];
                
                if ($consecutiveCount > $maxConsecutive) {
                    $maxConsecutive = $consecutiveCount;
                }
                
                // If we found 3 consecutive, we can stop early
                if ($consecutiveCount >= 3) {
                    break;
                }
            } else {
                // If we already found 3 consecutive, stop
                if ($maxConsecutive >= 3) {
                    break;
                }
                // Reset if not consecutive
                $consecutiveCount = 1;
                $consecutiveMonths = [$unpaidMonths[$i]];
            }
        }

        if ($maxConsecutive >= 3) {
            $result['has_3_consecutive'] = true;
            $result['count'] = $maxConsecutive;
            $result['months'] = collect($consecutiveMonths)->pluck('month_key')->toArray();
            $result['oldest_date'] = $consecutiveMonths[0]['due_date'];
            $result['latest_date'] = $consecutiveMonths[count($consecutiveMonths) - 1]['due_date'];
        }

        return $result;
    }

    /**
     * Print/Download disconnection notice
     */
    public function printNotice(Request $request)
    {
        $consumerIds = $request->input('consumer_ids', []);
        $disconnectionDate = $request->filled('disconnection_date')
            ? Carbon::parse($request->input('disconnection_date'))
            : Carbon::today();

        if (empty($consumerIds)) {
            return redirect()->route('disconnection.index')
                ->with('error', 'Please select at least one consumer.');
        }

        $consumers = ConsumerZoneOne::whereIn('id', $consumerIds)
            ->with(['ledgers' => function($query) {
                $query->where('trans', 'BILLING')
                    ->where(function($q) {
                        $q->whereNull('credit')->orWhere('credit', 0);
                    })
                    ->where('due_date', '<', now())
                    ->orderBy('due_date', 'desc');
            }])
            ->get();

        $penaltyLedgersByConsumer = ConsumerLedger::whereIn('consumer_zone_id', $consumers->pluck('id'))
            ->where('trans', 'PENALTY')
            ->get()
            ->groupBy('consumer_zone_id');

        $consumersWithOutstanding = $consumers->map(function($consumer) use ($penaltyLedgersByConsumer) {
            $ledgers = $consumer->ledgers;
            // Use direct ledger table balance so total is correct (avoids getLedger context issues on print)
            $totalAmountDue = $this->getCurrentBalanceFromLedgerTable($consumer);

            $penalties = $penaltyLedgersByConsumer->get($consumer->id, collect());

            $firstLedger = $ledgers->first();

            $thisMonthBill = $firstLedger ? (float)($firstLedger->debit ?? 0) : 0;
            $firstDueDate = $firstLedger && $firstLedger->due_date ? Carbon::parse($firstLedger->due_date)->format('Y-m-d') : null;
            $thisMonthPenalty = $firstDueDate ? $penalties->sum(function($p) use ($firstDueDate) {
                $pDue = $p->due_date ? Carbon::parse($p->due_date)->format('Y-m-d') : null;
                return $pDue === $firstDueDate ? (float)($p->debit ?? $p->penalty ?? 0) : 0;
            }) : 0;
            $thisMonthArrears = $thisMonthBill + $thisMonthPenalty;

            // Last Month/ Arrears CY = remaining amount (total minus This Month/ Arrears)
            $lastMonthArrears = $totalAmountDue - $thisMonthArrears;
            $lastMonthArrears = $lastMonthArrears > 0 ? round($lastMonthArrears, 2) : 0;

            $consumer->setAttribute('this_month_arrears', round($thisMonthArrears, 2));
            $consumer->setAttribute('last_month_arrears', (float) $lastMonthArrears);
            $consumer->setAttribute('others_ar', 0);
            $consumer->setAttribute('total_outstanding', $totalAmountDue);
            $consumer->setAttribute('unpaid_months', $this->adjustUnpaidMonthsForPriorArrears((int) $ledgers->count(), (float) $lastMonthArrears));
            $consumer->setAttribute('card_number', $consumer->sequence ?? '1');

            return $consumer;
        })
        ->filter(function($consumer) {
            // Exclude consumers with no Last Month/Arrears CY (i.e., only current month due)
            return $consumer->total_outstanding > 0
                && ($consumer->last_month_arrears ?? 0) > 0.01;
        });

        return view('disconnection.print', compact('consumersWithOutstanding', 'disconnectionDate'));
    }

    /**
     * Save disconnection orders and optionally assign to disconnectors
     */
    public function saveAndAssign(Request $request)
    {
        $request->validate([
            'consumer_ids' => 'required|array',
            'consumer_ids.*' => 'exists:consumer_zone,id',
            'disconnection_date' => 'required|date',
            'assign_to' => ['nullable', Rule::when($request->filled('assign_to'), ['exists:users,id'])],
        ]);

        $consumerIds = $request->input('consumer_ids');
        $disconnectionDate = Carbon::parse($request->input('disconnection_date'));
        $assignToId = $request->filled('assign_to') ? (int) $request->input('assign_to') : null;

        try {
            DB::beginTransaction();

            $consumers = ConsumerZoneOne::whereIn('id', $consumerIds)->get();

            $idsForLedger = $consumers->pluck('id');
            $allBillingsForSplit = ConsumerLedger::whereIn('consumer_zone_id', $idsForLedger)
                ->whereIn('trans', ['BILL', 'BILLING'])
                ->whereNotNull('due_date')
                ->get()
                ->groupBy('consumer_zone_id');
            $penaltyLedgersForOrders = ConsumerLedger::whereIn('consumer_zone_id', $idsForLedger)
                ->where('trans', 'PENALTY')
                ->get()
                ->groupBy('consumer_zone_id');

            $createdOrders = [];
            $skippedOrders = [];

            foreach ($consumers as $consumer) {
                // Get the current balance from ledger (same as ledger view shows)
                $totalAmountDue = $this->getCurrentBalance($consumer);
                
                // Skip if balance is 0 or negative (already paid)
                if ($totalAmountDue <= 0) {
                    $skippedOrders[] = [
                        'account_no' => $consumer->account_no,
                        'reason' => 'No outstanding balance'
                    ];
                    continue;
                }

                // Get ledgers for arrears breakdown
                $ledgers = $consumer->ledgers()
                    ->where('trans', 'BILLING')
                    ->where('due_date', '<', now())
                    ->orderBy('due_date', 'desc')
                    ->get();
                
                // For display purposes, calculate arrears breakdown
                $thisMonthArrears = $ledgers->first() ? (float)($ledgers->first()->debit ?? 0) : 0;
                $lastMonthArrears = $ledgers->count() > 1 ? (float)($ledgers->skip(1)->first()->debit ?? 0) : 0;
                $othersAR = $ledgers->skip(2)->sum(function($ledger) {
                    return (float)($ledger->debit ?? 0);
                });

                $lastMonthArrearsCY = $this->computeLastMonthArrearsAmount(
                    $totalAmountDue,
                    $allBillingsForSplit->get($consumer->id, collect()),
                    $penaltyLedgersForOrders->get($consumer->id, collect())
                );
                $unpaidMonthsAdjusted = $this->adjustUnpaidMonthsForPriorArrears((int) $ledgers->count(), $lastMonthArrearsCY);

                // Check if order already exists for this date
                $existingOrder = DisconnectionOrder::where('consumer_id', $consumer->id)
                    ->where('disconnection_date', $disconnectionDate)
                    ->where('status', '!=', 'cancelled')
                    ->first();

                if (!$existingOrder) {
                    $oldestUnpaid = $ledgers->isNotEmpty() ? ($ledgers->last()->due_date ?? $disconnectionDate) : $disconnectionDate;
                    $latestUnpaid = $ledgers->isNotEmpty() ? ($ledgers->first()->due_date ?? $disconnectionDate) : $disconnectionDate;
                    $order = DisconnectionOrder::create([
                        'consumer_id' => $consumer->id,
                        'disconnector_id' => $assignToId,
                        'account_no' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'address' => $consumer->address1,
                        'zone_code' => $consumer->zone_code,
                        'meter_number' => $consumer->meter_number,
                        'card_number' => $consumer->sequence ?? 1,
                        'this_month_arrears' => $thisMonthArrears,
                        'last_month_arrears' => $lastMonthArrears,
                        'others_ar' => $othersAR,
                        'total_outstanding' => $totalAmountDue,
                        'unpaid_months' => $unpaidMonthsAdjusted,
                        'oldest_unpaid_date' => $oldestUnpaid,
                        'latest_unpaid_date' => $latestUnpaid,
                        'disconnection_date' => $disconnectionDate,
                        'status' => $assignToId ? 'assigned' : 'pending',
                        'assigned_at' => $assignToId ? now() : null,
                    ]);

                    $createdOrders[] = $order;
                    
                    \Log::info('Disconnection order created', [
                        'order_id' => $order->id,
                        'account_no' => $consumer->account_no,
                        'total_outstanding' => $totalAmountDue,
                        'disconnector_id' => $assignToId,
                        'status' => $assignToId ? 'assigned' : 'pending'
                    ]);
                } else {
                    $skippedOrders[] = [
                        'account_no' => $consumer->account_no,
                        'reason' => 'Order already exists for this date'
                    ];
                }
            }

            DB::commit();

            $message = count($createdOrders) . ' disconnection order(s) created.';
            if ($assignToId) {
                $message .= ' Assigned to disconnector.';
            } else {
                $message .= ' Saved as pending (assign from Disconnection Orders Management).';
            }
            if (count($skippedOrders) > 0) {
                $message .= ' ' . count($skippedOrders) . ' order(s) skipped.';
            }

            return redirect()->route('disconnection.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error saving disconnection orders: ' . $e->getMessage());
            return redirect()->route('disconnection.index')
                ->with('error', 'Error saving disconnection orders: ' . $e->getMessage());
        }
    }

    /**
     * Show disconnection assignments page (for assigning orders to disconnectors)
     */
    public function assignments(Request $request)
    {
        $orders = DisconnectionOrder::query()
            ->with(['consumer', 'disconnector'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $orders->where('status', $request->input('status'));
        }

        // Filter by zone
        if ($request->has('zone')) {
            $orders->where('zone_code', $request->input('zone'));
        }

        // Filter by disconnector
        if ($request->has('disconnector_id')) {
            $orders->where('disconnector_id', $request->input('disconnector_id'));
        }

        $orders = $orders->paginate(20);

        // Get all zones
        $zones = DisconnectionOrder::select('zone_code')
            ->distinct()
            ->whereNotNull('zone_code')
            ->orderBy('zone_code')
            ->pluck('zone_code');

        // Get all disconnectors (users with disconnector role)
        $disconnectors = User::where('role', 'disconnector')
            ->orderBy('name')
            ->get();

        return view('disconnection.assignments', compact('orders', 'zones', 'disconnectors'));
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
                ->with('success', count($orderIds) . ' order(s) assigned to disconnector.');

        } catch (\Exception $e) {
            return redirect()->route('disconnection.assignments')
                ->with('error', 'Error assigning orders: ' . $e->getMessage());
        }
    }
}
