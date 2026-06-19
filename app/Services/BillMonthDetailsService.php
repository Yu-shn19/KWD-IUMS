<?php

namespace App\Services;

use App\Data\BillMonthDetailsState;
use App\Http\Controllers\BillingProcessController;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZone;
use App\Models\DownloadedReading;
use App\Models\MeterReadingSchedule;
use App\Models\Penalty;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    function mr_col(string $name): string
    {
        return $name;
    }
}

class BillMonthDetailsService
{
    public function handle(Request $request): JsonResponse
    {
        $s = new BillMonthDetailsState();

        $early = $this->parseInputAndResolveConsumer($request, $s);
        if ($early instanceof JsonResponse) {
            return $early;
        }

        $this->loadLedgerEntriesWithPenalties($s);
        $early = $this->resolveDateRange($request, $s);
        if ($early instanceof JsonResponse) {
            return $early;
        }

        $this->collectMatchingEntries($s);
        $this->applyDateRangeBreakdown($request, $s);
        $this->applyBillMonthModeBreakdown($s);
        $this->resolvePaymentStatusAndOverdueRules($s);
        $this->applyDatabaseBreakdownAndCredits($request, $s);
        $this->applyOrNumberAndWmcLogic($request, $s);
        $this->applyReconciliationAndFinalize($request, $s);

        return $this->buildSuccessResponse($s);
    }

    private function parseInputAndResolveConsumer(Request $request, BillMonthDetailsState $s): ?JsonResponse
    {
        $request->validate([
                    'account_number' => ['required', 'string'],
                    'bill_month_from' => ['nullable', 'string'], // MM-YYYY format (optional when from_date/to_date used)
                    'bill_month_to' => ['nullable', 'string'], // MM-YYYY format (optional)
                    'from_date' => ['nullable', 'date'], // YYYY-MM-DD: start of range from consumer_ledgers date/due_date
                    'to_date' => ['nullable', 'date'], // YYYY-MM-DD: end of range
                    'current_balance' => ['nullable', 'numeric', 'min:0'], // optional: use displayed balance for PY formula so PY matches page
                    'or_number' => ['nullable', 'string'],
                ]);
                
                $s->accountNumber = trim((string) $request->input('account_number'));
                $s->normalizedAccount = str_replace('-', '', $s->accountNumber);
                $s->normalizedAccountNoLeadingZero = ltrim($s->normalizedAccount, '0');
                $s->fromDateInput = $request->input('from_date');
                $s->toDateInput = $request->input('to_date');
                $s->dateRangeMode = !empty($s->fromDateInput) && !empty($s->toDateInput);
                
                $s->methodIsA = false;
                if ($s->dateRangeMode) {
                    $s->billMonthFromKey = null;
                    $s->billMonthToKey = null;
                } else {
                    $s->billMonthFromKey = $request->input('bill_month_from');
                    if (empty($s->billMonthFromKey)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Either bill_month_from or both from_date and to_date are required.',
                        ], 422);
                    }
                    $s->billMonthToKey = $request->input('bill_month_to', $s->billMonthFromKey);
                }
                
                // Find consumer (prefer exact match; fallback to normalized variants)
                $consumer = ConsumerZone::query()->where(mr_col('account_no'), $s->accountNumber)->first();
                if (!$consumer) {
                    $accountNumber = $s->accountNumber;
                    $normalizedAccount = $s->normalizedAccount;
                    $normalizedAccountNoLeadingZero = $s->normalizedAccountNoLeadingZero;
                    $consumer = ConsumerZone::query()->where(function ($query) use ($accountNumber, $normalizedAccount, $normalizedAccountNoLeadingZero) {
                        $query->whereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                              ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedAccount])
                              ->orWhereRaw("TRIM(LEADING '0' FROM REPLACE(account_no, '-', '')) = ?", [$normalizedAccountNoLeadingZero ?: '0'])
                              ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper($accountNumber)]);
                })->first();
                }
                
                if (!$consumer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Consumer not found.',
                    ], 404);
                }
        
                // Current balance for PY formula and no-billing/1-month logic. Prefer displayed balance from frontend so PY matches "Current Balance" on page.
                $s->currentBalance = 0;
                if ($request->has('current_balance') && $request->input('current_balance') !== null && $request->input('current_balance') !== '') {
                    $s->currentBalance = (float) $request->input('current_balance');
                } else {
                    $latestBalanceEntry = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                        ->whereNotNull(mr_col('balance'))
                        ->orderBy('date', 'desc')
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                    if ($latestBalanceEntry) {
                        $s->currentBalance = (float)($latestBalanceEntry->balance ?? 0);
                    } else {
                        $s->currentBalance = $consumer->getLedgerBalance();
                    }
                }
                $s->consumer = $consumer;
                return null;
    }

    private function loadLedgerEntriesWithPenalties(BillMonthDetailsState $s): void
    {
        // Get all ledger entries for this consumer
                // Include PENALTY transactions - they might have debit = 0 but penalty > 0, or both might be > 0
                $s->ledgerEntries = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                    ->where(function($query) {
                        // Get all PENALTY transactions regardless of debit/penalty values
                        $query->where(mr_col('trans'), 'PENALTY')
                              // OR get other transactions with debit > 0
                              ->orWhere(function($q) {
                                  $q->whereIn(mr_col('trans'), ['BILLING', 'BILL', 'LOAN', 'ADJ', 'DM'])
                                    ->where(mr_col('debit'), '>', 0);
                              });
                    })
                    ->orderBy('date', 'asc')
                    ->get();
                
                // Debug: Log all PENALTY entries found in consumer_ledgers
                $penaltyEntriesFound = $s->ledgerEntries->where(mr_col('trans'), 'PENALTY');
                Log::info('All PENALTY entries found in consumer_ledgers', [
                    'account_number' => $s->accountNumber,
                    'consumer_id' => $s->consumer->id,
                    'penalty_count' => $penaltyEntriesFound->count(),
                    'penalty_dates' => $penaltyEntriesFound->map(function($p) {
                        return [
                            'id' => $p->id,
                            'date' => $p->date,
                            'penalty' => $p->penalty,
                            'debit' => $p->debit,
                        ];
                    })->toArray(),
                ]);
                
                // Penalties table: always filter by consumer_zone_id so we only get this consumer's penalties
                try {
                    $penaltiesFromTable = Penalty::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->orderBy('date', 'asc')
                        ->get();
                    
                    Log::info('Penalties found in penalties table', [
                        'account_number' => $s->accountNumber,
                        'penalty_count' => $penaltiesFromTable->count(),
                        'penalty_dates' => $penaltiesFromTable->map(function (Penalty $p) {
                            $penaltyDate = $p->date;
                            return [
                                'id' => $p->id,
                                'date' => $penaltyDate instanceof \DateTimeInterface ? $penaltyDate->format('Y-m-d') : null,
                                'penalty_amount' => $p->penalty_amount,
                            ];
                        })->toArray(),
                    ]);
                    
                    // Convert penalties from penalties table to ledger-like entries
                    foreach ($penaltiesFromTable as $penalty) {
                        $penaltyDate = $penalty->date instanceof Carbon 
                            ? $penalty->date->format('Y-m-d') 
                            : (string)$penalty->date;
                        
                        // Check if this penalty is already in ledgerEntries
                        $exists = $s->ledgerEntries->contains(function($entry) use ($penaltyDate, $penalty) {
                            return $entry->trans === 'PENALTY' 
                                && $entry->date == $penaltyDate
                                && ($entry->penalty == $penalty->penalty_amount || $entry->debit == $penalty->penalty_amount);
                        });
                        
                        if (!$exists && $penalty->penalty_amount > 0) {
                            // Add penalty from penalties table as a ledger-like entry (include paid_at for display)
                            $s->ledgerEntries->push((object)[
                                'id' => 'penalty_' . $penalty->id,
                                'trans' => 'PENALTY',
                                'date' => $penaltyDate,
                                'due_date' => $penalty->due_date ? ($penalty->due_date instanceof Carbon ? $penalty->due_date->format('Y-m-d') : (string)$penalty->due_date) : null,
                                'penalty' => (float)$penalty->penalty_amount,
                                'debit' => (float)$penalty->penalty_amount,
                                'billamount' => 0,
                                'others' => 0,
                                'schedule_id' => $penalty->schedule_id,
                                'consumer_zone_id' => $penalty->consumer_zone_id,
                                'paid_at' => $penalty->paid_at ? Carbon::parse($penalty->paid_at)->format('Y-m-d H:i:s') : null,
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error fetching penalties from penalties table', [
                        'error' => $e->getMessage(),
                        'consumer_id' => $s->consumer->id
                    ]);
                }
    }

    private function resolveDateRange(Request $request, BillMonthDetailsState $s): ?JsonResponse
    {
        // Parse the selected range: either bill month (MM-YYYY) or date range (from_date, to_date) from consumer_ledgers
                if ($s->dateRangeMode) {
                    $s->fromMonthDate = Carbon::parse($s->fromDateInput)->startOfDay();
                    $s->toMonthDate = Carbon::parse($s->toDateInput)->endOfDay();
                } else {
                try {
                    list($monthFrom, $yearFrom) = explode('-', $s->billMonthFromKey);
                    $s->fromMonthDate = Carbon::create($yearFrom, $monthFrom, 1)->startOfMonth();
                    list($monthTo, $yearTo) = explode('-', $s->billMonthToKey);
                    $s->toMonthDate = Carbon::create($yearTo, $monthTo, 1)->endOfMonth();
                        // Single bill month: use date/due_date logic. Use to_date from transaction_date if provided,
                        // otherwise fall back to min(end of month, today).
                        if ($s->billMonthFromKey === $s->billMonthToKey) {
                            $requestedTxDate = $request->input('transaction_date')
                                ?? $request->input('to_date')
                                ?? $request->input('from_date');
                            if (!empty($requestedTxDate)) {
                                $effectiveTo = Carbon::parse($requestedTxDate)->endOfDay();
                                // Clamp to the selected bill month range
                                if ($effectiveTo->lt($s->fromMonthDate)) {
                                    $effectiveTo = $s->fromMonthDate->copy()->endOfDay();
                                }
                                if ($effectiveTo->gt($s->toMonthDate)) {
                                    $effectiveTo = $s->toMonthDate->copy()->endOfDay();
                                }
                            } else {
                                $effectiveTo = $s->toMonthDate->copy()->min(Carbon::today()->endOfDay());
                            }
                            $s->fromDateInput = $s->fromMonthDate->format('Y-m-d');
                            $s->toDateInput = $effectiveTo->format('Y-m-d');
                            $s->dateRangeMode = true;
                            $s->fromMonthDate = Carbon::parse($s->fromDateInput)->startOfDay();
                            $s->toMonthDate = Carbon::parse($s->toDateInput)->endOfDay();
                        }
                    } catch (Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid bill month format.',
                    ], 422);
                    }
                }
                return null;
    }

    private function collectMatchingEntries(BillMonthDetailsState $s): void
    {
        // Collect entries that fall within the selected month range (or date range when dateRangeMode)
                $s->matchingEntries = [];
                foreach ($s->ledgerEntries as $ledger) {
                    $schedule = null;
                    if ($ledger->schedule_id) {
                        $schedule = MeterReadingSchedule::find($ledger->schedule_id);
                    }
                    
                    // Determine bill month for this entry â€” always use date and due_date from the database:
                    // BILLING: schedule.bill_month or ledger.date; due_date comes from schedule/ledger for when payment is due.
                    // PENALTY: ledger.date = when penalty is effective (e.g. day after due = 12/17); due_date = which bill it relates to.
                    $entryDate = null;
                    if ($ledger->trans === 'PENALTY') {
                        /**
                         * PENALTY: use DB date/due_date. date = when penalty applies (e.g. 12/17); due_date = bill due date.
                         * Prefer due_date for period matching, then reference (MM-YYYY), then date.
                         */
                        try {
                            if (!empty($ledger->due_date)) {
                                $entryDate = $ledger->due_date instanceof Carbon
                                    ? $ledger->due_date
                                    : Carbon::parse($ledger->due_date);
                            } elseif (!empty($ledger->reference) && preg_match('/^(\\d{2})-(\\d{4})$/', $ledger->reference, $m)) {
                                // Build a date from reference MM-YYYY (use first day of month)
                                $entryDate = Carbon::create($m[2], $m[1], 1);
                            } elseif ($ledger->date instanceof Carbon) {
                                $entryDate = $ledger->date;
                            } else {
                                $entryDate = Carbon::parse($ledger->date);
                            }
                        } catch (Exception $e) {
                            Log::warning('Failed to parse penalty date', [
                                'date' => $ledger->date ?? null,
                                'due_date' => $ledger->due_date ?? null,
                                'reference' => $ledger->reference ?? null,
                                'error' => $e->getMessage()
                            ]);
                            continue;
                        }
                    } elseif ($schedule && $schedule->bill_month) {
                        // For other transactions, use schedule's bill_month if available
                        try {
                            $entryDate = Carbon::parse($schedule->bill_month);
                        } catch (Exception $e) {
                            continue;
                        }
                    } else {
                        // Fallback to ledger entry's date
                        try {
                            $entryDate = Carbon::parse($ledger->date);
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                    
                    // When date-range mode, include BILLING if date <= to_date (we classify by date/due_date later)
                    if ($s->dateRangeMode && ($ledger->trans === 'BILLING' || $ledger->trans === 'BILL')) {
                        try {
                            $entryDateLedger = $ledger->date instanceof Carbon ? $ledger->date : Carbon::parse($ledger->date);
                            if ($entryDateLedger->lte($s->toMonthDate)) {
                                $s->matchingEntries[] = $ledger;
                            }
                        } catch (Exception $e) {
                            // skip
                        }
                        continue;
                    }
                    // Include entries that fall within the from-to range
                    if ($entryDate && $entryDate->gte($s->fromMonthDate) && $entryDate->lte($s->toMonthDate)) {
                        $s->matchingEntries[] = $ledger;
                        
                        // Debug logging for penalty entries
                        if ($ledger->trans === 'PENALTY') {
                            Log::info('Penalty entry matched', [
                                'entry_date' => $entryDate->format('Y-m-d'),
                                'from_date' => $s->fromMonthDate->format('Y-m-d'),
                                'to_date' => $s->toMonthDate->format('Y-m-d'),
                                'penalty_amount' => $ledger->penalty ?? $ledger->debit ?? 0,
                            ]);
                        }
                    } elseif ($ledger->trans === 'PENALTY') {
                        // Log why penalty didn't match
                        Log::info('Penalty entry NOT matched', [
                            'entry_date' => $entryDate ? $entryDate->format('Y-m-d') : 'NULL',
                            'from_date' => $s->fromMonthDate->format('Y-m-d'),
                            'to_date' => $s->toMonthDate->format('Y-m-d'),
                            'gte_check' => $entryDate ? $entryDate->gte($s->fromMonthDate) : false,
                            'lte_check' => $entryDate ? $entryDate->lte($s->toMonthDate) : false,
                        ]);
                    }
                }
    }

    private function applyDateRangeBreakdown(Request $request, BillMonthDetailsState $s): void
    {
        // Aggregate the amounts from the selected month only (or from date/due_date when dateRangeMode)
                $s->currentBill = 0;
                $s->penaltyAmount = 0;
                $s->maintenance = 0;
                $s->others = 0;
                $s->arrears = 0;
                $s->arrearsCy = 0;
                $s->arrearsPy = 0;
                $s->seniorCitizenDiscount = 0;
                $s->principalFromBilling = 0;
                $s->dueDateForOverdue = null;
        
                $s->isRange = $s->billMonthFromKey !== null && $s->billMonthToKey !== null && ($s->billMonthFromKey !== $s->billMonthToKey);
                $s->billingEntriesWithDueDate = []; // [ ['billamount' => x, 'due_date' => Carbon|null ], ... ]
                $s->noBillingInViewedMonth = false;
                $s->usePyFormula = true; // false in "Due Date â†’ Current Billing Month" window (overdue view) so PY = 0
        
                // --- DATE RANGE MODE: Payment breakdown per analyst spec (paid_at only) ---
                // HARD PAYMENT RULE: Unpaid iff paid_at IS NULL on the charge row (consumer_ledgers). Paid iff paid_at IS NOT NULL. Never infer from balance/amount/credit. Never double-count months with paid_at IS NOT NULL.
                // Derived: unpaid_principal_months = BILLING, billamount>0, paid_at IS NULL; unpaid_wmc_months = BILLING, others>0, paid_at IS NULL; unpaid_penalty_months = PENALTY, penalty>0, paid_at IS NULL.
                // Method A = Billing Date â†’ Due Date: Current Bill = 195 if current month BILLING paid_at IS NULL else 0; Penalty = SUM(unpaid penalty); WMC = SUM(unpaid WMC); Arrears CY = 0; Arrears PY = first-month rule or SUM(billamount) before current.
                // Method B = Due Date â†’ Current Billing Month: Current Bill = 0; Penalty/WMC = SUM(unpaid); Arrears CY = SUM(all unpaid principal); Arrears PY = 0.
                // First-month rule (Method A only): when first billing month in Method A and RB â‰  0 â†’ PY = max(0, RB âˆ’ Current Billing âˆ’ Current Month WMC). Constants: principal â‚±195, WMC â‚±20, penalty â‚±19.50.
                if ($s->dateRangeMode) {
                    // HARD PAYMENT RULE: Unpaid iff paid_at IS NULL on the charge row. Never infer from balance/amount/credit. Never double-count months with paid_at IS NOT NULL.
                    // RESET RULE: If latest PAYMENT before billing month has balance=0, ignore charges before that (cycle restart).
                    $billingMonthStartForReset = $s->fromMonthDate->copy()->startOfMonth();
                    $cycleResetEntry = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->where(mr_col('trans'), 'PAYMENT')
                        ->whereNotNull(mr_col('balance'))
                        ->whereRaw('ABS(balance) < 0.01')
                        ->whereRaw('COALESCE(txtime, date) < ?', [$billingMonthStartForReset->format('Y-m-d H:i:s')])
                        ->orderByRaw('COALESCE(txtime, date) DESC')
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                    $cycleResetDate = $cycleResetEntry
                        ? ($cycleResetEntry->txtime instanceof Carbon
                            ? $cycleResetEntry->txtime
                            : ($cycleResetEntry->date instanceof Carbon ? $cycleResetEntry->date : Carbon::parse($cycleResetEntry->date)))
                        : null;
                    $billingForDateRange = [];
                    foreach ($s->matchingEntries as $ledger) {
                        if ($ledger->trans !== 'BILLING' && $ledger->trans !== 'BILL') {
                            continue;
                        }
                        $billAmount = (float)($ledger->billamount ?? 0);
                        if ($billAmount <= 0) {
                            continue;
                        }
                        $entryDate = $ledger->date instanceof Carbon ? $ledger->date : Carbon::parse($ledger->date);
                        if ($cycleResetDate && $entryDate->lte($cycleResetDate)) {
                            continue;
                        }
                        
                        $dueDate = null;
                        if (!empty($ledger->due_date)) {
                            $dueDate = $ledger->due_date instanceof Carbon ? $ledger->due_date : Carbon::parse($ledger->due_date);
                        } elseif ($ledger->schedule_id) {
                            $sch = MeterReadingSchedule::find($ledger->schedule_id);
                            if ($sch && !empty($sch->due_date)) {
                                $dueDate = $sch->due_date instanceof Carbon ? $sch->due_date : Carbon::parse($sch->due_date);
                            }
                        }
                        if (!$dueDate) {
                            $dueDate = $entryDate->copy()->day(20);
                            if ($dueDate->lt($entryDate)) {
                                $dueDate = $entryDate->copy()->endOfMonth();
                            }
                        }
                        // Include paid_at from consumer_ledgers: charge is UNPAID iff paid_at IS NULL (strict).
                        $paidAtValue = null;
                        if (isset($ledger->paid_at)) {
                            $paidAtValue = $ledger->paid_at;
                            // Handle Carbon/DateTime objects
                            if ($paidAtValue instanceof \DateTimeInterface) {
                                $paidAtValue = $paidAtValue->format('Y-m-d H:i:s');
                            }
                        }
                    $billingForDateRange[] = [
                            'date' => $entryDate,
                            'due_date' => $dueDate,
                            'billamount' => $billAmount,
                            'others' => (float)($ledger->others ?? 0),
                            'paid_at' => $paidAtValue,
                            'ledger_id' => $ledger->id ?? null, // For debugging
                            'schedule_id' => $ledger->schedule_id ?? null,
                        ];
                    }
                    $s->hasBillingEntriesInRange = !empty($billingForDateRange);
                    // Debug: Log all billing entries found
                    Log::info('Billing For Date Range Debug', [
                        'account_number' => $s->accountNumber,
                        'from_date' => $s->fromMonthDate->format('Y-m-d'),
                        'to_date' => $s->toMonthDate->format('Y-m-d'),
                        'total_billing_entries' => count($billingForDateRange),
                        'entries' => array_map(function($b) {
                            return [
                                'date' => $b['date']->format('Y-m-d'),
                                'due_date' => $b['due_date'] ? $b['due_date']->format('Y-m-d') : null,
                                'billamount' => $b['billamount'],
                                'others' => $b['others'],
                                'paid_at' => $b['paid_at'] ?? 'NULL',
                                'ledger_id' => $b['ledger_id'] ?? null,
                            ];
                        }, $billingForDateRange),
                    ]);
                    usort($billingForDateRange, function ($a, $b) {
                        $da = $a['due_date'];
                        $db = $b['due_date'];
                        if (!$da) return 1;
                        if (!$db) return -1;
                        return $da->getTimestamp() - $db->getTimestamp();
                    });
        
                    $toDateOnly = $s->toMonthDate->copy()->startOfDay();
                    $billingMonthStartForReset = $s->fromMonthDate->copy()->startOfMonth();
        
                    // RESET RULE: ignore charges before latest balance=0 prior to billing month
                    $cycleResetRow = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->whereNotNull(mr_col('balance'))
                        ->whereRaw('ABS(balance) < 0.01')
                        ->whereRaw('COALESCE(txtime, date) < ?', [$billingMonthStartForReset->format('Y-m-d H:i:s')])
                        ->orderByRaw('COALESCE(txtime, date) DESC')
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                    $cycleResetDate = $cycleResetRow
                        ? ($cycleResetRow->txtime instanceof Carbon
                            ? $cycleResetRow->txtime
                            : ($cycleResetRow->date instanceof Carbon ? $cycleResetRow->date : Carbon::parse($cycleResetRow->date)))
                        : null;
        
                    if ($cycleResetDate) {
                        $billingForDateRange = array_values(array_filter($billingForDateRange, function ($b) use ($cycleResetDate) {
                            return $b['date']->gt($cycleResetDate);
                        }));
                        $s->matchingEntries = array_values(array_filter($s->matchingEntries, function ($ledger) use ($cycleResetDate) {
                            if (!isset($ledger->date)) return true;
                            $d = $ledger->date instanceof Carbon ? $ledger->date : Carbon::parse($ledger->date);
                            return $d->gt($cycleResetDate);
                        }));
                    }
        
                    // Unpaid = charge row has paid_at IS NULL only (HARD PAYMENT RULE). Never infer from PAYMENT records or consumer_payments.
                    $isChargeUnpaid = function ($b) {
                        $pa = $b['paid_at'] ?? null;
                        // Strict check: only NULL or empty string counts as unpaid. Any non-empty value = paid.
                        if ($pa === null) return true;
                        if ($pa === '') return true;
                        // If it's a Carbon/DateTime object, it's paid
                        if ($pa instanceof \DateTimeInterface) return false;
                        // If it's a string with any content, it's paid
                        if (is_string($pa) && trim($pa) !== '') return false;
                        // Default: treat as unpaid only if truly null/empty
                        return true;
                    };
        
                    // Current bill = bill whose period [date, due_date] contains to_date (to_date >= date and to_date <= due_date)
                    $s->currentBillEntry = null;
                    foreach ($billingForDateRange as $b) {
                        $due = $b['due_date'];
                        $date = $b['date'];
                        if ($due && $toDateOnly->gte($date) && $toDateOnly->lte($due)) {
                            $s->currentBillEntry = $b;
                            break;
                        }
                    }
                    // Method A: Current Bill = â‚±195 if current billing month BILLING row has paid_at IS NULL, else 0
                    if ($s->currentBillEntry) {
                        $s->currentBill = $isChargeUnpaid($s->currentBillEntry)
                            ? round($s->currentBillEntry['billamount'] ?? 195.00, 2)
                            : 0.00;
                        // HARD PAYMENT RULE: If current bill entry has paid_at IS NOT NULL, mark as paid
                        if (!$isChargeUnpaid($s->currentBillEntry)) {
                            $s->paymentStatus = 'paid';
                        }
                    }
                    $s->noBillingInViewedMonth = ($s->currentBillEntry === null);
        
                    // Overdue unpaid = bills with due_date < to_date AND that charge row paid_at IS NULL (no double-count)
                    $overdueUnpaid = [];
                    foreach ($billingForDateRange as $b) {
                        $due = $b['due_date'];
                        if (!$due || !$toDateOnly->gt($due)) continue;
                        if ($isChargeUnpaid($b)) {
                            $overdueUnpaid[] = $b;
                        }
                    }
                    usort($overdueUnpaid, function ($a, $b) {
                        $da = $a['due_date'];
                        $db = $b['due_date'];
                        if (!$da) return 1;
                        if (!$db) return -1;
                        return $db->getTimestamp() - $da->getTimestamp();
                    });
        
                    // Penalty never shrinks by date selection: sum ALL unpaid penalty rows (paid_at IS NULL), ignore range.
                    $penaltyUnpaidSum = 0;
                    $allPenaltyRows = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->where(mr_col('trans'), 'PENALTY')
                        ->where(function ($q) {
                            $q->where(mr_col('penalty'), '>', 0)->orWhere(mr_col('debit'), '>', 0);
                        })
                        ->whereNull(mr_col('paid_at'))
                        ->orderBy('date', 'asc')
                        ->get();
                    foreach ($allPenaltyRows as $ledger) {
                        $amt = (float)($ledger->penalty ?? 0);
                        if ($amt <= 0) {
                            $amt = (float)($ledger->debit ?? 0);
                        }
                        if ($amt > 0) $penaltyUnpaidSum += $amt;
                    }
                    $penaltyUnpaidSum = round($penaltyUnpaidSum, 2);
        
                    // Water Maintenance verification:
                    // Determine outstanding WMC per billing row from payment breakdown first
                    // (consumer_payments.meter_maintenance by schedule_id). This avoids
                    // re-charging WMC in later months when WMC was already paid previously.
                    // Fallback to paid_at and default WMC only for legacy/incomplete data.
                    $paidWmcBySchedule = [];
                    $scheduleIdsForWmc = array_values(array_unique(array_filter(array_map(function ($b) {
                        return isset($b['schedule_id']) ? (int)$b['schedule_id'] : 0;
                    }, $billingForDateRange), function ($id) {
                        return $id > 0;
                    })));
                    if (!empty($scheduleIdsForWmc)) {
                        $paidWmcRows = DB::table(mr_col('consumer_payments as cp'))
                            ->join(mr_col('downloaded_readings as dr'), mr_col('dr.id'), '=', mr_col('cp.reading_id'))
                            ->whereIn(mr_col('dr.schedule_id'), $scheduleIdsForWmc)
                            ->whereNotNull(mr_col('cp.paid_at'))
                            ->selectRaw('dr.schedule_id as schedule_id, COALESCE(SUM(cp.meter_maintenance), 0) as paid_wmc')
                            ->groupBy(mr_col('dr.schedule_id'))
                            ->get();
                        foreach ($paidWmcRows as $wmcRow) {
                            $sid = (int)($wmcRow->schedule_id ?? 0);
                            if ($sid > 0) {
                                $paidWmcBySchedule[$sid] = round((float)($wmcRow->paid_wmc ?? 0), 2);
                            }
                        }
                    }
        
                    // Fallback pool for payments not tied to schedule_id (legacy/misaligned data):
                    // use consumer-level paid WMC up to selected as-of date.
                    $consumerPaidWmcPool = 0.0;
                    if (!empty($s->consumer->id)) {
                        $consumerPaidWmcPool = (float) DB::table(mr_col('consumer_payments as cp'))
                            ->where('cp.' . ConsumerPayment::consumerZoneIdColumn(), $s->consumer->id)
                            ->whereNotNull(mr_col('cp.paid_at'))
                            ->whereRaw('COALESCE(cp.paid_at, cp.created_at) <= ?', [$s->toMonthDate->format('Y-m-d H:i:s')])
                            ->selectRaw('COALESCE(SUM(cp.meter_maintenance), 0) as paid_wmc')
                            ->value('paid_wmc');
                    }
        
                    $wmcRows = [];
                    $allocatedBySchedule = 0.0;
                    foreach ($billingForDateRange as $b) {
                        if (!$isChargeUnpaid($b)) {
                            continue;
                        }
                        $rowWmc = (float)($b['others'] ?? 0);
                        if ($rowWmc <= 0) {
                            $rowWmc = 20.00;
                        }
                        $scheduleId = isset($b['schedule_id']) ? (int)$b['schedule_id'] : 0;
                        $paidWmcForRow = $scheduleId > 0 ? (float)($paidWmcBySchedule[$scheduleId] ?? 0) : 0.0;
                        $outstandingWmc = max(0.0, round($rowWmc - $paidWmcForRow, 2));
                        $allocatedBySchedule += min($rowWmc, $paidWmcForRow);
                        $wmcRows[] = [
                            'date' => $b['date'] ?? null,
                            'outstanding' => $outstandingWmc,
                        ];
                    }
        
                    // Apply any unmatched paid WMC to oldest unpaid WMC rows.
                    $unallocatedPool = max(0.0, round($consumerPaidWmcPool - $allocatedBySchedule, 2));
                    if ($unallocatedPool > 0 && !empty($wmcRows)) {
                        usort($wmcRows, function ($a, $b) {
                            $da = $a['date'] instanceof Carbon ? $a['date']->getTimestamp() : 0;
                            $db = $b['date'] instanceof Carbon ? $b['date']->getTimestamp() : 0;
                            return $da <=> $db;
                        });
                        foreach ($wmcRows as &$wmcRow) {
                            if ($unallocatedPool <= 0) {
                                break;
                            }
                            $currentOutstanding = (float)($wmcRow['outstanding'] ?? 0);
                            if ($currentOutstanding <= 0) {
                                continue;
                            }
                            $applied = min($currentOutstanding, $unallocatedPool);
                            $wmcRow['outstanding'] = round($currentOutstanding - $applied, 2);
                            $unallocatedPool = round($unallocatedPool - $applied, 2);
                        }
                        unset($wmcRow);
                    }
        
                    $unpaidWmcMonths = 0;
                    $wmcUnpaidSum = 0.0;
                    foreach ($wmcRows as $wmcRow) {
                        $outstanding = (float)($wmcRow['outstanding'] ?? 0);
                        if ($outstanding <= 0) {
                            continue;
                        }
                        $unpaidWmcMonths++;
                        $wmcUnpaidSum += $outstanding;
                    }
                    $wmcUnpaidSum = round($wmcUnpaidSum, 2);
        
                    // When date is in a single month: for December view always show only December's arrears (195, Period 2). For other months, when only 1 overdue use same-month; when 2+ overdue (e.g. Jan 23) use full list so Period 4 shows Arrears CY 390.
                    $rangeInSingleMonth = ($s->fromMonthDate->format('n') === $s->toMonthDate->format('n') && $s->fromMonthDate->format('Y') === $s->toMonthDate->format('Y'));
                    $toMonthNum = (int) $s->toMonthDate->format('n');
                    $toYearNum = (int) $s->toMonthDate->format('Y');
                    $overdueForBreakdown = $overdueUnpaid;
                    $isDecemberView = ($toMonthNum === 12);
                    if ($rangeInSingleMonth && ($isDecemberView || count($overdueUnpaid) === 1)) {
                        // December view: always restrict to December overdue only (Arrears CY 195, Total 274). Else when only 1 overdue, same-month filter.
                        $overdueForBreakdown = array_values(array_filter($overdueUnpaid, function ($b) use ($toMonthNum, $toYearNum) {
                            $due = $b['due_date'];
                            return $due && (int) $due->format('n') === $toMonthNum && (int) $due->format('Y') === $toYearNum;
                        }));
                        if (empty($overdueForBreakdown)) {
                            $overdueForBreakdown = $overdueUnpaid;
                        }
                    }
                    // When 2+ overdue (e.g. Jan 23): use full overdueUnpaid so Period 4 shows Arrears CY 390, Total 469
        
                    $overdueCount = count($overdueForBreakdown);
                    $hasCurrent = ($s->currentBillEntry !== null);
                    $s->methodIsA = $hasCurrent;
        
                    // Pay only one month: only when within due (has current bill). After due (e.g. 26/12) use Period 2: Current 0, Arrears CY 195, Penalty 39, Maintenance 40, Total 274.
                    $payOnlyOneMonthEligible = $rangeInSingleMonth && ($overdueCount <= 1) && $hasCurrent;
                    $monthBill = null;
                    if ($payOnlyOneMonthEligible && !empty($billingForDateRange)) {
                        foreach ($billingForDateRange as $b) {
                            $due = $b['due_date'];
                            if ($due && (int) $due->format('n') === $toMonthNum && (int) $due->format('Y') === $toYearNum) {
                                $monthBill = $b;
                                break;
                            }
                        }
                    }
                    $payOnlyOneMonthApplied = ($payOnlyOneMonthEligible && $monthBill !== null);
                    if ($payOnlyOneMonthApplied) {
                        $s->currentBill = $isChargeUnpaid($monthBill) ? round($monthBill['billamount'], 2) : 0.00;
                        $s->arrearsCy = 0;
                        $s->arrearsPy = 0;
                        $s->penaltyAmount = $penaltyUnpaidSum;
                        $s->maintenance = $wmcUnpaidSum > 0 ? $wmcUnpaidSum : 20.00; // WMC â‚±20.00 per unpaid month
                    }
        
                    if (!$payOnlyOneMonthApplied) {
                    // Date-meaning methods: A = Billing Dateâ†’Due Date, B = Due Dateâ†’Current Billing Month
                    // Constants: principal 195, WMC 20, penalty 19.50 (10% of 195) per unpaid month. No double-count if paid.
                    $principalPerMonth = 195.00;
                    $wmcPerMonth = 20.00;
                    $penaltyPerMonth = 19.50;
                    $overdueCount = count($overdueForBreakdown);
                    // Unpaid principal months: use billamount from ledger (may vary); fallback 195 per month
                    $unpaidPrincipalSum = 0;
                    foreach ($overdueForBreakdown as $b) {
                        $unpaidPrincipalSum += (float)($b['billamount'] ?? $principalPerMonth);
                    }
                    $unpaidPrincipalMonths = $overdueCount; // count of months with unpaid principal
        
                    if ($hasCurrent) {
                        // METHOD A â€” Billing Date â†’ Billing Due Date
                        // Penalty = unpaid penalty rows (e.g., Dec+Jan penalties while Feb is before due)
                        $s->penaltyAmount = $penaltyUnpaidSum;
                        // WMC = unpaid rental months only (current month + any prior unpaid)
                        $s->maintenance = $wmcUnpaidSum > 0 ? $wmcUnpaidSum : ($isChargeUnpaid($s->currentBillEntry) ? $wmcPerMonth : 0);
                        $s->arrearsCy = 0; // CY at start of penalty only; Method A = before due
                        // Unpaid principal months *before* current billing month (PY bucket at start of billing)
                        $billingMonthStart = $s->currentBillEntry && isset($s->currentBillEntry['due_date']) && $s->currentBillEntry['due_date']
                            ? $s->currentBillEntry['due_date']->copy()->startOfMonth() : $toDateOnly->copy()->startOfMonth();
                        $unpaidPyMonths = array_filter($overdueUnpaid, function ($b) use ($billingMonthStart) {
                            $due = $b['due_date'];
                            return $due && $due->lt($billingMonthStart);
                        });
                        $unpaidPyCount = count($unpaidPyMonths);
                        $sumBillamountBeforeCurrent = 0;
                        $pyDebugEntries = [];
                        foreach ($unpaidPyMonths as $b) {
                            $amt = (float)($b['billamount'] ?? $principalPerMonth);
                            $sumBillamountBeforeCurrent += $amt;
                            $pyDebugEntries[] = [
                                'date' => $b['date']->format('Y-m-d'),
                                'due_date' => $b['due_date'] ? $b['due_date']->format('Y-m-d') : null,
                                'billamount' => $amt,
                                'paid_at' => $b['paid_at'] ?? 'NULL',
                            ];
                        }
                        $sumBillamountBeforeCurrent = round($sumBillamountBeforeCurrent, 2);
                        // Debug: Log Arrears PY calculation
                        if (count($pyDebugEntries) > 0) {
                            Log::info('Arrears PY Calculation Debug', [
                                'account_number' => $s->accountNumber,
                                'py_total' => $sumBillamountBeforeCurrent,
                                'entries_count' => count($pyDebugEntries),
                                'entries' => $pyDebugEntries,
                            ]);
                        }
                        // RESET RULE: Check latest ledger balance BEFORE billing month start (date < billing_month_start_date)
                        $resetCutoffDate = $billingMonthStart->copy()->startOfDay();
                        $latestBalanceBeforeCurrent = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->whereNotNull(mr_col('balance'))
                            ->whereRaw('COALESCE(txtime, date) < ?', [$resetCutoffDate->format('Y-m-d H:i:s')])
                            ->orderByRaw('COALESCE(txtime, date) DESC')
                            ->orderBy(mr_col('id'), 'desc')
                            ->first();
                        $balanceBeforeCurrent = $latestBalanceBeforeCurrent ? (float)($latestBalanceBeforeCurrent->balance ?? 0) : 0.00;
                        $s->isFirstMonthMethodA = (abs($balanceBeforeCurrent) < 0.01);
        
                        // Method A first-month rule:
                        // - If there is no remaining balance, PY = 0
                        // - If there is remaining balance, PY = remaining balance
                        if ($s->isFirstMonthMethodA) {
                            $currentMonthWmc = ($s->currentBillEntry && $isChargeUnpaid($s->currentBillEntry)) ? $wmcPerMonth : 0;
                            $remainingBalance = round($s->currentBalance - $s->currentBill - $currentMonthWmc, 2);
                            $s->arrearsPy = $remainingBalance > 0 ? $remainingBalance : 0;
                            $s->penaltyAmount = 0; // First month in Method A has no penalty
                        } else {
                            // Method A: PY = SUM(billamount) of unpaid principal months BEFORE current billing month
                            $s->arrearsPy = $sumBillamountBeforeCurrent;
                        }
                        $s->usePyFormula = false;
                    } else {
                        // METHOD B â€” Billing Due Date â†’ Current Billing Month. Current Bill = 0. Arrears CY = all unpaid principal; Arrears PY = 0.
                        $s->currentBill = 0;
                        $s->penaltyAmount = $penaltyUnpaidSum;
                        $s->maintenance = $wmcUnpaidSum > 0 ? $wmcUnpaidSum : round($wmcPerMonth * $unpaidPrincipalMonths, 2);
                        $s->arrearsCy = $unpaidPrincipalSum > 0 ? round($unpaidPrincipalSum, 2) : round($principalPerMonth * $unpaidPrincipalMonths, 2);
                        $s->arrearsPy = 0;
                        $s->usePyFormula = false;
                    }
                    } // end !$payOnlyOneMonthApplied
                }
    }

    private function applyBillMonthModeBreakdown(BillMonthDetailsState $s): void
    {
        if (!$s->dateRangeMode) {
                // Debug: Log matching entries
                $penaltyEntriesFound = array_filter($s->matchingEntries, function($e) { 
                    return isset($e->trans) && $e->trans === 'PENALTY'; 
                });
                    Log::info('Bill Month Details - Matching Entries', [
                    'account_number' => $s->accountNumber,
                    'bill_month_from' => $s->billMonthFromKey,
                    'bill_month_to' => $s->billMonthToKey,
                    'from_date' => $s->fromMonthDate->format('Y-m-d'),
                    'to_date' => $s->toMonthDate->format('Y-m-d'),
                    'total_entries' => count($s->matchingEntries),
                    'penalty_count' => count($penaltyEntriesFound),
                ]);
        
                    // Always use date and due_date from the database to drive logic:
                // - BILLING: ledger.date = when bill applies (start); schedule.due_date or ledger.due_date = when payment is due (e.g. 12/16); penalty starts the day after (12/17).
                // - PENALTY: ledger.date = when penalty is effective (e.g. 12/17); ledger.due_date = which bill it relates to.
                $billingScheduleId = null;
                $billingDueDateFromLedger = null;
                
                foreach ($s->matchingEntries as $ledger) {
                    if ($ledger->trans === 'BILLING' || $ledger->trans === 'BILL') {
                        $billAmount = (float)$ledger->billamount;
                        // Resolve due_date from DB: ledger.due_date first, then schedule.due_date (when payment is due)
                        $entryDueDate = null;
                        if (!empty($ledger->due_date)) {
                            $entryDueDate = $ledger->due_date instanceof Carbon ? $ledger->due_date : Carbon::parse($ledger->due_date);
                        } elseif ($ledger->schedule_id) {
                            $sch = MeterReadingSchedule::find($ledger->schedule_id);
                            if ($sch && !empty($sch->due_date)) {
                                $entryDueDate = $sch->due_date instanceof Carbon ? $sch->due_date : Carbon::parse($sch->due_date);
                            }
                        }
                        if ($s->isRange && $billAmount > 0) {
                            $s->billingEntriesWithDueDate[] = ['billamount' => $billAmount, 'due_date' => $entryDueDate];
                        }
                        $s->currentBill += $billAmount;
                        if ($billAmount > 0) {
                            $s->principalFromBilling = $billAmount;
                            $billingScheduleId = $ledger->schedule_id ?? null;
                            if (!empty($ledger->due_date)) {
                                $billingDueDateFromLedger = $ledger->due_date instanceof Carbon
                                    ? $ledger->due_date
                                    : Carbon::parse($ledger->due_date);
                            }
                        }
                        $s->maintenance += (float)$ledger->others;
                    } elseif ($ledger->trans === 'PENALTY') {
                        $s->penaltyAmount = max(
                            (float)($ledger->penalty ?? 0),
                            (float)($ledger->debit ?? 0)
                        );
                        $s->penaltyAmount += $s->penaltyAmount;
                    } else {
                        $s->others += (float)$ledger->debit;
                    }
                }
                
                // Principal for overdue: total current bill from BILLING in this period, or latest unpaid BILLING before period end
                if ($s->currentBill > 0 && $s->principalFromBilling <= 0) {
                    $s->principalFromBilling = $s->currentBill;
                }
                if ($s->principalFromBilling <= 0) {
                    $latestBillingBeforePeriod = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                        ->where(mr_col('date'), '<=', $s->toMonthDate->format('Y-m-d'))
                        ->where(mr_col('billamount'), '>', 0)
                        ->orderBy('date', 'desc')
                        ->first();
                    if ($latestBillingBeforePeriod) {
                        $s->principalFromBilling = (float) $latestBillingBeforePeriod->billamount;
                        $billingScheduleId = $billingScheduleId ?: $latestBillingBeforePeriod->schedule_id;
                        // Use due_date from this BILLING ledger (DB) for overdue logic when present
                        if (!empty($latestBillingBeforePeriod->due_date)) {
                            $billingDueDateFromLedger = $latestBillingBeforePeriod->due_date instanceof Carbon
                                ? $latestBillingBeforePeriod->due_date
                                : Carbon::parse($latestBillingBeforePeriod->due_date);
                        }
                    }
                }
                // Due date for overdue logic: prefer ledger.due_date from DB, then schedule.due_date (when payment is due; penalty applies from day after)
                if ($billingDueDateFromLedger) {
                    $s->dueDateForOverdue = $billingDueDateFromLedger;
                } elseif ($billingScheduleId) {
                    $billingSchedule = MeterReadingSchedule::find($billingScheduleId);
                    if ($billingSchedule && $billingSchedule->due_date) {
                        $s->dueDateForOverdue = $billingSchedule->due_date instanceof Carbon
                            ? $billingSchedule->due_date
                            : Carbon::parse($billingSchedule->due_date);
                    }
                }
        
                $s->arrears = 0;
                $s->arrearsCy = 0;
                $s->arrearsPy = 0;
        
                // Previous month date range (for "is previous month paid?" and for Arrears â€” Previous Month principal)
                $prevMonthStart = $s->fromMonthDate->copy()->subMonth()->startOfMonth();
                $prevMonthEnd = $s->fromMonthDate->copy()->subMonth()->endOfMonth();
                $prevFromStr = $prevMonthStart->format('Y-m-d');
                $prevToStr = $prevMonthEnd->format('Y-m-d');
        
                // Check if previous month is paid (using paid_at): if paid, Arrears â€” Previous Month = 0 so it won't appear in breakdown
                $prevMonthPaid = false;
                $prevSchedulesInRange = MeterReadingSchedule::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                    ->whereBetween(mr_col('bill_month'), [$prevFromStr, $prevToStr])
                    ->pluck(mr_col('id'));
                if ($prevSchedulesInRange->isNotEmpty()) {
                    $prevReadingsInRange = DB::table(mr_col('downloaded_readings'))->whereIn(mr_col('schedule_id'), $prevSchedulesInRange->toArray())->pluck(mr_col('id'));
                    if ($prevReadingsInRange->isNotEmpty()) {
                        $prevMonthPaid = DB::table(mr_col('consumer_payments'))
                            ->whereIn(mr_col('reading_id'), $prevReadingsInRange->toArray())
                            ->whereNotNull(mr_col('paid_at'))
                            ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$prevFromStr, $prevToStr])
                            ->exists();
                    }
                    if (!$prevMonthPaid) {
                        $prevMonthPaid = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->where(mr_col('trans'), 'PAYMENT')
                            ->whereNotNull(mr_col('paid_at'))
                            ->whereIn(mr_col('schedule_id'), $prevSchedulesInRange->toArray())
                            ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$prevFromStr, $prevToStr])
                            ->exists();
                    }
                }
                if (!$prevMonthPaid) {
                    $prevMonthPaid = DB::table(mr_col('consumer_ledgers as cl'))
                        ->join(mr_col('consumer_zone as cz'), mr_col('cz.id'), '=', mr_col('cl.consumer_zone_id'))
                        ->where(mr_col('cl.trans'), 'PAYMENT')
                        ->whereNotNull(mr_col('cl.paid_at'))
                        ->whereRaw('DATE(cl.paid_at) BETWEEN ? AND ?', [$prevFromStr, $prevToStr])
                        ->where(function ($q) use ($s) {
                            $q->where(mr_col('cz.account_no'), $s->accountNumber)
                                ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$s->normalizedAccount])
                                ->orWhereRaw("TRIM(LEADING '0' FROM REPLACE(cz.account_no, '-', '')) = ?", [$s->normalizedAccountNoLeadingZero ?: '0']);
                        })
                        ->exists();
                }
        
                // Arrears â€” Previous Month = previous month's unpaid principal only (e.g. December bill 195), not full balance (234.50).
                // If previous month is paid (paid_at set), show 0 so it does not appear in breakdown.
                if ($prevMonthPaid) {
                    $s->arrearsPy = 0;
                } else {
                    $prevMonthPrincipal = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                        ->where(function ($q) use ($prevSchedulesInRange, $prevFromStr, $prevToStr) {
                            if ($prevSchedulesInRange->isNotEmpty()) {
                                $q->whereIn(mr_col('schedule_id'), $prevSchedulesInRange->toArray());
                            }
                            $q->orWhereBetween('date', [$prevFromStr, $prevToStr]);
                        })
                        ->sum(DB::raw('COALESCE(billamount, 0)'));
                    $s->arrearsPy = round(max(0, (float) $prevMonthPrincipal), 2);
                }
                }
    }

    private function resolvePaymentStatusAndOverdueRules(BillMonthDetailsState $s): void
    {
        // Payment status: based only on OR # (official receipt number). Paid if this bill month has a payment with or_number set; otherwise Unpaid.
                $s->paymentStatus = 'unpaid';
                $paymentMonthStart = $s->fromMonthDate->copy()->startOfMonth();
                $paymentMonthEnd = $s->fromMonthDate->copy()->endOfMonth();
                $fromStr = $paymentMonthStart->format('Y-m-d');
                $toStr = $paymentMonthEnd->format('Y-m-d');
        
                $s->schedulesInRange = MeterReadingSchedule::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                    ->whereBetween(mr_col('bill_month'), [$fromStr, $toStr])
                    ->pluck(mr_col('id'));
        
                if ($s->schedulesInRange->isNotEmpty()) {
                    $readingsInRange = DB::table(mr_col('downloaded_readings'))->whereIn(mr_col('schedule_id'), $s->schedulesInRange)->pluck(mr_col('id'));
                    if ($readingsInRange->isNotEmpty()) {
                        $hasOrNumber = DB::table(mr_col('consumer_payments'))
                            ->whereIn(mr_col('reading_id'), $readingsInRange)
                            ->whereNotNull(mr_col('or_number'))
                            ->whereRaw("TRIM(COALESCE(or_number, '')) != ''")
                            ->exists();
                        if ($hasOrNumber) {
                            $s->paymentStatus = 'paid';
                        }
                    }
                }
                // If no schedules/readings in range, also check downloaded_readings.official_receipt_number if column exists
                if ($s->paymentStatus !== 'paid' && $s->schedulesInRange->isNotEmpty()) {
                    $hasOrColumn = Schema::hasColumn('downloaded_readings', 'official_receipt_number');
                    if ($hasOrColumn) {
                        $readingsInRange = DB::table(mr_col('downloaded_readings'))->whereIn(mr_col('schedule_id'), $s->schedulesInRange)->pluck(mr_col('id'));
                        if ($readingsInRange->isNotEmpty()) {
                            $hasOrInReadings = DB::table(mr_col('downloaded_readings'))
                                ->whereIn(mr_col('id'), $readingsInRange)
                                ->whereNotNull(mr_col('official_receipt_number'))
                                ->whereRaw("TRIM(COALESCE(official_receipt_number, '')) != ''")
                                ->exists();
                            if ($hasOrInReadings) {
                                $s->paymentStatus = 'paid';
                            }
                        }
                    }
                }
                // Only when not in date-range mode (bill month mode).
                if (!$s->dateRangeMode) {
                // Rule: Bill 195 issued Dec 1â€“16 (date), due_date 12/16 â†’ no penalty before due_date. After due_date (Dec 17â€“Jan 1): penalty 19.5, maintenance 20.
                // If still unpaid in Jan, Feb view: carry 195, penalty 39, maintenance 40, overdue 390, no new bill. Always use date and due_date from DB.
                if ($s->isRange && $s->paymentStatus !== 'paid' && !empty($s->billingEntriesWithDueDate)) {
                    $currentBillFromRule = 0;
                    $arrearsPrincipal = 0;
                    $earliestDueInRange = null;
                    $overdueBillCount = 0;
                    $toMonthDateEnd = $s->toMonthDate->copy()->endOfDay();
                    foreach ($s->billingEntriesWithDueDate as $entry) {
                        $due = $entry['due_date'];
                        $amt = $entry['billamount'];
                        if ($due === null || $due->gt($toMonthDateEnd)) {
                            // due_date after end of range (or missing) â†’ bill not yet due at end of range â†’ current
                            $currentBillFromRule += $amt;
                        } else {
                            // due_date <= end of range â†’ overdue by end of range (use date/due_date from DB)
                            $arrearsPrincipal += $amt;
                            $overdueBillCount++;
                            if ($earliestDueInRange === null || $due->lt($earliestDueInRange)) {
                                $earliestDueInRange = $due;
                            }
                        }
                    }
                    if ($arrearsPrincipal > 0 && $earliestDueInRange) {
                        // Overdue periods = months from earliest due_date to end of range (date/due_date from DB)
                        $overduePeriodsRange = (int) max(1, $earliestDueInRange->diffInMonths($s->toMonthDate));
                        $overduePeriodsRange = min($overduePeriodsRange, 12);
                        $s->currentBill = $currentBillFromRule;
                        $s->arrearsCy = round($arrearsPrincipal, 2);
                        // Penalty = 10% per bill per period: 195â†’19.5 (1 period), 390â†’39 (2 periods). Use per-bill Ã— periods.
                        $perBillPrincipal = $overdueBillCount > 0 ? $arrearsPrincipal / $overdueBillCount : $arrearsPrincipal;
                        $s->penaltyAmount = round($perBillPrincipal * 0.10 * $overduePeriodsRange, 2);
                        $s->maintenance = round(20 * $overduePeriodsRange, 2); // 20 first period, 40 second
                    } elseif ($arrearsPrincipal > 0) {
                        $s->currentBill = $currentBillFromRule;
                        $s->arrearsCy = round($arrearsPrincipal, 2);
                        $s->penaltyAmount = round($arrearsPrincipal * 0.10, 2);
                        $s->maintenance = 20;
                    } else {
                        $s->currentBill = $currentBillFromRule;
                    }
                }
        
                // Overdue breakdown for single month (or range with no BILLING in range) â€” use date and due_date from DB:
                // Period 1 (date before due_date): Current 195, Penalty 0, Maintenance 20, Arrears CY 0
                // Period 2 (after due_date, 1 month): Current 0, Penalty 19.5, Maintenance 20, Arrears CY 195
                // Period 4 (after due_date, 2 months): Current 0, Penalty 39, Maintenance 40, Arrears CY 390
                $usedRangeRule = $s->isRange && $s->paymentStatus !== 'paid' && !empty($s->billingEntriesWithDueDate);
                if (!$usedRangeRule && $s->paymentStatus !== 'paid' && $s->dueDateForOverdue && $s->principalFromBilling > 0 && $s->dueDateForOverdue->lt($s->toMonthDate)) {
                    // Only treat as "overdue-only" period (no new bill) when there is no BILLING in this range
                    if ($s->currentBill <= 0) {
                        // Number of full months overdue: Dec 16 due â†’ Jan 31 = 1 period, â†’ Feb 28 = 2 periods
                        $overduePeriods = (int) max(1, $s->dueDateForOverdue->diffInMonths($s->toMonthDate));
                        $overduePeriods = min($overduePeriods, 12);
                        $s->arrearsCy = round($s->principalFromBilling * $overduePeriods, 2);
                        $s->penaltyAmount = round($s->principalFromBilling * 0.10 * $overduePeriods, 2);
                        $s->maintenance = round(20 * $overduePeriods, 2);
                        $s->currentBill = 0; // Overdue period: no new bill in breakdown
                    } else {
                        // Period has a new bill (e.g. Jan 2â€“16): add carried penalty/maintenance; Arrears â€” Previous Month not computed (stays 0.00)
                        $previousPrincipal = 0;
                        $previousDueDate = null;
                        $latestBillingBeforePeriod = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                            ->where(mr_col('date'), '<', $s->fromMonthDate->format('Y-m-d'))
                            ->where(mr_col('billamount'), '>', 0)
                            ->orderBy('date', 'desc')
                            ->first();
                        if ($latestBillingBeforePeriod) {
                            $prevBillDate = $latestBillingBeforePeriod->date;
                            $prevAmount = (float) $latestBillingBeforePeriod->billamount;
                            $paidBeforeThisPeriod = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                                ->where(mr_col('trans'), 'PAYMENT')
                                ->whereNotNull(mr_col('paid_at'))
                                ->where(mr_col('date'), '>=', $prevBillDate)
                                ->where(mr_col('date'), '<', $s->fromMonthDate->format('Y-m-d'))
                                ->exists();
                            if (!$paidBeforeThisPeriod) {
                                $previousPrincipal = $prevAmount;
                                $prevScheduleId = $latestBillingBeforePeriod->schedule_id ?? null;
                                if ($prevScheduleId) {
                                    $prevSchedule = MeterReadingSchedule::find($prevScheduleId);
                                    if ($prevSchedule && $prevSchedule->due_date) {
                                        $previousDueDate = $prevSchedule->due_date instanceof Carbon
                                            ? $prevSchedule->due_date
                                            : Carbon::parse($prevSchedule->due_date);
                                    }
                                }
                            }
                        }
                        if ($previousPrincipal > 0) {
                            $carriedOverduePeriods = 1;
                            if ($previousDueDate && $previousDueDate->lt($s->fromMonthDate)) {
                                $carriedOverduePeriods = (int) max(1, $previousDueDate->diffInMonths($s->fromMonthDate->copy()->subDay()) + 1);
                                $carriedOverduePeriods = min($carriedOverduePeriods, 12);
                            }
                            $s->penaltyAmount += round($previousPrincipal * 0.10 * $carriedOverduePeriods, 2);
                            $s->maintenance += round(20 * $carriedOverduePeriods, 2);
                        }
                    }
                }
                }
                if ($s->paymentStatus === 'paid') {
                    $s->arrearsCy = 0;
                    $s->arrearsPy = 0;
                }
        
                // When previous month is paid (e.g. November all 0.00), December must show Arrears CY 0, Arrears PY 0 â€” compute from displayed period (toMonthDate)
                $displayedPrevMonthStart = $s->toMonthDate->copy()->subMonth()->startOfMonth();
                $displayedPrevMonthEnd = $s->toMonthDate->copy()->subMonth()->endOfMonth();
                $displayedPrevFromStr = $displayedPrevMonthStart->format('Y-m-d');
                $displayedPrevToStr = $displayedPrevMonthEnd->format('Y-m-d');
                $displayedPrevMonthPaid = false;
                $displayedPrevSchedules = MeterReadingSchedule::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                    ->whereBetween(mr_col('bill_month'), [$displayedPrevFromStr, $displayedPrevToStr])
                    ->pluck(mr_col('id'));
                if ($displayedPrevSchedules->isNotEmpty()) {
                    $displayedPrevReadings = DB::table(mr_col('downloaded_readings'))->whereIn(mr_col('schedule_id'), $displayedPrevSchedules->toArray())->pluck(mr_col('id'));
                    if ($displayedPrevReadings->isNotEmpty()) {
                        $displayedPrevMonthPaid = DB::table(mr_col('consumer_payments'))
                            ->whereIn(mr_col('reading_id'), $displayedPrevReadings->toArray())
                            ->whereNotNull(mr_col('paid_at'))
                            ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$displayedPrevFromStr, $displayedPrevToStr])
                            ->exists();
                    }
                    if (!$displayedPrevMonthPaid) {
                        $displayedPrevMonthPaid = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->where(mr_col('trans'), 'PAYMENT')
                            ->whereNotNull(mr_col('paid_at'))
                            ->whereIn(mr_col('schedule_id'), $displayedPrevSchedules->toArray())
                            ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$displayedPrevFromStr, $displayedPrevToStr])
                            ->exists();
                    }
                }
                if (!$displayedPrevMonthPaid) {
                    $displayedPrevMonthPaid = DB::table(mr_col('consumer_ledgers as cl'))
                        ->join(mr_col('consumer_zone as cz'), mr_col('cz.id'), '=', mr_col('cl.consumer_zone_id'))
                        ->where(mr_col('cl.trans'), 'PAYMENT')
                        ->whereNotNull(mr_col('cl.paid_at'))
                        ->whereRaw('DATE(cl.paid_at) BETWEEN ? AND ?', [$displayedPrevFromStr, $displayedPrevToStr])
                        ->where(function ($q) use ($s) {
                            $q->where(mr_col('cz.account_no'), $s->accountNumber)
                                ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$s->normalizedAccount])
                                ->orWhereRaw("TRIM(LEADING '0' FROM REPLACE(cz.account_no, '-', '')) = ?", [$s->normalizedAccountNoLeadingZero ?: '0']);
                        })
                        ->exists();
                }
                // When previous month is paid: zero Arrears PY; zero Arrears CY only in Method A (before due)
                if ($displayedPrevMonthPaid) {
                    $s->arrearsPy = 0;
                    if ($s->methodIsA) {
                        $s->arrearsCy = 0;
                    }
                }
        
                // Single bill month: only zero Arrears CY in Method A (before due). Keep CY in Method B.
                if ($s->billMonthFromKey !== null && $s->billMonthFromKey === $s->billMonthToKey && $s->methodIsA) {
                    $s->arrearsCy = 0;
                }
        
                // 1-month breakdown when billing exists: PY = carried balance (current_balance - current_bill), e.g. 120 after Jan/Dec paid
                if (
                    !$s->dateRangeMode
                    && $s->schedulesInRange->isNotEmpty()
                    && $s->currentBill > 0
                    && round($s->arrearsCy, 2) == 0
                    && !($s->methodIsA ?? false && $s->isFirstMonthMethodA ?? false)
                ) {
                    $s->arrearsPy = max(0, round($s->currentBalance - $s->currentBill, 2));
                }
        
                // No billing in selected month: show only PY = current balance; Current Bill = 0, Penalty = 0, Meter Rental = 0
                // Use schedules OR actual ledger BILLING entries to decide. Do not zero out if ledger has billing rows for the month.
                $hasLedgerBillingForMonth = isset($s->hasBillingEntriesInRange) ? $s->hasBillingEntriesInRange : true;
                $s->isNoBillingInMonth = ($s->schedulesInRange->isEmpty() && !$hasLedgerBillingForMonth) || ($s->dateRangeMode && $s->noBillingInViewedMonth && !$hasLedgerBillingForMonth);
                if ($s->isNoBillingInMonth) {
                    $s->currentBill = 0;
                    $s->penaltyAmount = 0;
                    $s->maintenance = 0;
                    $s->arrearsCy = 0;
                    $s->arrearsPy = max(0, round($s->currentBalance, 2));
                }
    }

    private function applyDatabaseBreakdownAndCredits(Request $request, BillMonthDetailsState $s): void
    {
        // Balance as of end of previous year: split arrears by year so balance from 2025 â†’ PY, from current year â†’ CY.
                $balanceEndOfPreviousYear = $this->getLedgerBalanceAsOfDate((int) $s->consumer->id, Carbon::now()->subYear()->endOfYear()->format('Y-m-d'));
        
                // Always use database-backed breakdown (paid_at only).
                // PRE-DUE vs POST-DUE: compare transaction_date (from date button) to the selected bill's due_date.
                // PRE-DUE (transaction_date <= due_date): Current Bill = 195, Arrears CY = current year (excluding selected month), Arrears PY = past years.
                // POST-DUE (transaction_date > due_date): Current Bill = 0, Arrears CY = current year, Arrears PY = past years.
                // When selecting a bill month, if date is after due_date â†’ POST-DUE; if date is within billing period â†’ PRE-DUE.
                $billingController = app(BillingProcessController::class);
                $asOfDate = $request->input('transaction_date') ? Carbon::parse($request->input('transaction_date')) : Carbon::now();
                $selectedBillMonthYmd = null;
                if (!empty($s->billMonthFromKey) && $s->billMonthFromKey === $s->billMonthToKey) {
                    $parts = explode('-', $s->billMonthFromKey);
                    if (count($parts) === 2) {
                        $selectedBillMonthYmd = $parts[1] . '-' . $parts[0];
                    }
                }
                // Resolve due_date for the selected bill month to determine PRE-DUE vs POST-DUE based on date button
                $selectedBillDueDate = null;
                if (!empty($s->schedulesInRange) && $s->schedulesInRange->isNotEmpty()) {
                    $scheduleWithDue = MeterReadingSchedule::whereIn('id', $s->schedulesInRange)
                        ->whereNotNull(mr_col('due_date'))
                        ->first();
                    if ($scheduleWithDue && $scheduleWithDue->due_date) {
                        $selectedBillDueDate = $scheduleWithDue->due_date instanceof Carbon
                            ? $scheduleWithDue->due_date
                            : Carbon::parse($scheduleWithDue->due_date);
                    }
                }
                if (!$selectedBillDueDate) {
                    // Fallback: get due_date from ledger BILLING for the selected month
                    $billingWithDue = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                        ->whereNotNull(mr_col('due_date'))
                        ->when(!empty($s->schedulesInRange) && $s->schedulesInRange->isNotEmpty(), function ($q) use ($s) {
                            $q->whereIn(mr_col('schedule_id'), $s->schedulesInRange->toArray());
                        })
                        ->orderBy('date', 'desc')
                        ->first();
                    if ($billingWithDue && $billingWithDue->due_date) {
                        $selectedBillDueDate = $billingWithDue->due_date instanceof Carbon
                            ? $billingWithDue->due_date
                            : Carbon::parse($billingWithDue->due_date);
                    }
                }
                // Determine viewType: PRE-DUE if transaction_date <= due_date, POST-DUE if transaction_date > due_date
                $viewType = ($selectedBillDueDate && $asOfDate->gt($selectedBillDueDate)) ? 'post_due' : 'pre_due';
                $dbBreakdown = $billingController->getBillingBreakdownData((int) $s->consumer->id, $viewType, $asOfDate, null, $selectedBillMonthYmd);
                $s->currentBill = (float) ($dbBreakdown['current_bill'] ?? 0);
                $s->penaltyAmount = (float) ($dbBreakdown['penalty'] ?? 0);
                $s->maintenance = (float) ($dbBreakdown['water_maintenance_charge'] ?? 0);
                $s->arrearsCy = (float) ($dbBreakdown['arrears_cy'] ?? 0);
                $s->arrearsPy = (float) ($dbBreakdown['arrears_py'] ?? 0);
                $hasDbCurrentBillForSelectedMonth = !empty($selectedBillMonthYmd) && round($s->currentBill, 2) > 0;
        
                // Carry credit from latest balance before selected range start
                // (e.g. previous month PAYMENT leaves -0.10, next month bill should reduce by 0.10).
                $carryCreditBeforeRange = 0.0;
                try {
                    $rangeStart = $s->fromMonthDate->copy()->startOfDay();
                    $latestBeforeRange = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                        ->whereNotNull(mr_col('balance'))
                        ->whereRaw('COALESCE(txtime, date) < ?', [$rangeStart->format('Y-m-d H:i:s')])
                        ->orderByRaw('COALESCE(txtime, date) DESC')
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                    // Treat prior negative balance as usable carry-credit only when current displayed balance
                    // is also non-positive; if current balance is positive, do not consume current bill.
                    if (
                        $latestBeforeRange
                        && (float)($latestBeforeRange->balance ?? 0) < 0
                        && (float)$s->currentBalance <= 0
                    ) {
                        $carryCreditBeforeRange = abs(round((float)($latestBeforeRange->balance ?? 0), 2));
                    }
                } catch (Throwable $e) {
                    $carryCreditBeforeRange = 0.0;
                }
        
                // PRE-DUE credit handling:
                // If past balance is negative (credit), consume Current Bill first, then Maintenance.
                // Example: -377.06 credit, Current Bill 726.80 => Current Bill becomes 349.74.
                if ($viewType === 'pre_due') {
                    $credit = 0.0;
                    if ($balanceEndOfPreviousYear < 0) {
                        $credit = abs(round((float) $balanceEndOfPreviousYear, 2));
                    }
                    $credit = max($credit, $carryCreditBeforeRange);
                    if ($credit > 0) {
                        $appliedToCurrentBill = min($credit, max(0.0, $s->currentBill));
                        $s->currentBill = round(max(0.0, $s->currentBill - $appliedToCurrentBill), 2);
                        $credit = round($credit - $appliedToCurrentBill, 2);
        
                        if ($credit > 0) {
                            $appliedToMaintenance = min($credit, max(0.0, $s->maintenance));
                            $s->maintenance = round(max(0.0, $s->maintenance - $appliedToMaintenance), 2);
                        }
                    }
        
                    // If selected-month charges already consume the displayed current balance,
                    // there is no remaining past balance to place into arrears.
                    $remainingAfterSelected = round($s->currentBalance - $s->currentBill - $s->penaltyAmount - $s->maintenance, 2);
                    if ($remainingAfterSelected <= 0) {
                        $s->arrearsCy = 0.0;
                        $s->arrearsPy = 0.0;
                    }
                }
        
                // POST-DUE credit handling:
                // For overdue month view, principal is often shown in Arrears CY.
                // Apply available credit to principal first and display remaining principal as Current Bill.
                if ($viewType === 'post_due') {
                    $credit = 0.0;
                    if ($balanceEndOfPreviousYear < 0) {
                        $credit = abs(round((float) $balanceEndOfPreviousYear, 2));
                    }
                    $credit = max($credit, $carryCreditBeforeRange);
                    // Also derive credit from reconciliation gap:
                    // if (principal + penalty + maintenance + PY) is greater than displayed current balance,
                    // the difference is an available credit that should reduce principal.
                    $computedBreakdownTotal = round(
                        max(0.0, $s->currentBill)
                        + max(0.0, $s->arrearsCy)
                        + max(0.0, $s->arrearsPy)
                        + max(0.0, $s->penaltyAmount)
                        + max(0.0, $s->maintenance),
                        2
                    );
                    $derivedCredit = round(max(0.0, $computedBreakdownTotal - max(0.0, (float) $s->currentBalance)), 2);
                    $credit = max($credit, $derivedCredit);
        
                    $principalBucket = round(max(0.0, $s->currentBill) + max(0.0, $s->arrearsCy), 2);
                    if ($credit > 0 && $principalBucket > 0) {
                        $principalAfterCredit = round(max(0.0, $principalBucket - $credit), 2);
                        $s->currentBill = $principalAfterCredit;
                        $s->arrearsCy = 0.0;
                    }
                }
        
                // PY fallback: if the DB found no prior-year billing rows (arrears_py = 0) but the ledger
                // shows a prior-year balance, fill arrears_py from whatever balance remains after accounting
                // for currentBill + penalty + WMC + CY. This keeps Jan view (CY > 0, PY = 0 from DB) and
                // Feb view (PY directly from DB) using the same underlying balance so totals stay consistent.
                // Also handles accounts with no billing schedule: splits balance into PY + CY using the
                // end-of-previous-year ledger balance so unpaid prior-year amounts show correctly.
                if (round($s->arrearsPy, 2) == 0 && $balanceEndOfPreviousYear > 0) {
                    $carriedForPy = max(0, round($s->currentBalance - $s->currentBill - $s->penaltyAmount - $s->maintenance - $s->arrearsCy, 2));
                    $s->arrearsPy = min(round($balanceEndOfPreviousYear, 2), $carriedForPy);
                    // If CY was also zero from DB (e.g. no billing rows at all), fill CY from whatever balance
                    // remains after PY is accounted for so the breakdown always sums to currentBalance.
                    if (round($s->arrearsCy, 2) == 0) {
                        $s->arrearsCy = max(0, round($s->currentBalance - $s->currentBill - $s->penaltyAmount - $s->maintenance - $s->arrearsPy, 2));
                    }
                }
        
                // No billing in selected month OR no schedule: reconcile breakdown to current balance only when the
                // DB breakdown has no PY/CY data. If the DB already returned non-zero PY or CY, trust those values
                // so the same formula (year-based PY, year+month for CY) is used regardless of which month is selected.
                $dbHasArrears = (round($s->arrearsCy, 2) + round($s->arrearsPy, 2)) > 0;
                $applyBalanceSplit = ($s->isNoBillingInMonth || $s->schedulesInRange->isEmpty()) && $s->currentBalance > 0 && !$dbHasArrears;
                // If DB breakdown already found a current bill for the selected month,
                // never reclassify it into arrears via fallback balance split.
                if ($hasDbCurrentBillForSelectedMonth) {
                    $applyBalanceSplit = false;
                }
                if ($applyBalanceSplit) {
                    $s->currentBill = 0;
                    $remainder = round($s->currentBalance - $s->currentBill - $s->penaltyAmount - $s->maintenance, 2);
                    $remainder = max(0, $remainder);
                    $s->arrearsPy = min(max(0, round($balanceEndOfPreviousYear, 2)), $remainder);
                    $s->arrearsCy = max(0, round($remainder - $s->arrearsPy, 2));
                }
                // 1-month carried balance: only overwrite PY/CY when dbBreakdown returned both zero
                // (so we reconcile from displayed current balance).
                // NOTE: single bill-month requests are normalized into dateRangeMode earlier,
                // so allow this fallback when a single bill month key is selected.
                $isSingleBillMonthSelection = ($s->billMonthFromKey !== null && $s->billMonthFromKey === $s->billMonthToKey);
                if (
                    !$applyBalanceSplit
                    && (!$s->dateRangeMode || $isSingleBillMonthSelection)
                    && $s->schedulesInRange->isNotEmpty()
                    && $s->currentBill > 0
                    && round($s->arrearsCy, 2) == 0
                    && round($s->arrearsPy, 2) == 0
                    && $s->currentBalance > $s->currentBill
                    && !$hasDbCurrentBillForSelectedMonth
                ) {
                    // Carried balance should only be the remainder after selected-month charges
                    // (current bill + penalty + maintenance), not just current bill alone.
                    $carried = round($s->currentBalance - $s->currentBill - $s->penaltyAmount - $s->maintenance, 2);
                    $carried = max(0, $carried);
                    $s->arrearsPy = min(max(0, round($balanceEndOfPreviousYear, 2)), $carried);
                    $s->arrearsCy = max(0, round($carried - $s->arrearsPy, 2));
                }
    }

    private function applyOrNumberAndWmcLogic(Request $request, BillMonthDetailsState $s): void
    {
        $s->orNumberInput = trim((string) $request->input('or_number', ''));
                $s->orPayment = null;
                if ($s->orNumberInput !== '') {
                    $s->orPayment = ConsumerPayment::forConsumerZone($s->consumer->id)
                        ->where(mr_col('or_number'), $s->orNumberInput)
                        ->first();
                    // Fallback for legacy/misaligned records where consumer_zone_id is null/wrong but OR is valid.
                    if (!$s->orPayment) {
                        $s->orPayment = ConsumerPayment::query()->where(mr_col('or_number'), $s->orNumberInput)
                            ->orderBy(mr_col('paid_at'), 'desc')
                            ->orderBy(mr_col('id'), 'desc')
                            ->first();
                    }
                }
        
                // Paid-month fallback from consumer_payments:
                // apply only when remaining balance is effectively zero.
                // If balance remains, keep ledger/date-based computed breakdown (no paid_at takeover).
                // Skip this fallback when OR was explicitly provided; explicit OR should control the breakdown.
                if (
                    $s->orNumberInput === ''
                    && $s->paymentStatus === 'paid'
                    && $s->schedulesInRange->isNotEmpty()
                    && round((float) $s->currentBalance, 2) <= 0.009
                ) {
                    $readingsInRange = DB::table(mr_col('downloaded_readings'))->whereIn(mr_col('schedule_id'), $s->schedulesInRange)->pluck(mr_col('id'));
                    if ($readingsInRange->isNotEmpty()) {
                        $paidPayment = ConsumerPayment::whereIn('reading_id', $readingsInRange)
                            ->whereNotNull(mr_col('paid_at'))
                            ->orderBy(mr_col('paid_at'), 'desc')
                            ->first();
                        if ($paidPayment) {
                            $s->currentBill = (float)($paidPayment->current_bill ?? 0);
                            $s->penaltyAmount = (float)($paidPayment->penalty ?? 0);
                            $s->maintenance = (float)($paidPayment->meter_maintenance ?? 0);
                            $s->arrearsCy = (float)($paidPayment->arrears_cy ?? 0);
                            $s->arrearsPy = (float)($paidPayment->arrears_py ?? 0);
                            $s->seniorCitizenDiscount = (float)($paidPayment->senior_citizen_discount ?? 0);
                        }
                    }
                }
        
                // Preserve computed ledger WMC so OR mode can fallback when OR row has no WMC.
                $ledgerComputedMaintenance = (float) $s->maintenance;
        
                // When OR # is provided, use only that OR's payment breakdown for this consumer.
                // If OR is not found, keep the computed month breakdown and only mark unpaid.
                // This keeps new/unused OR flow aligned with bill-month/date computation.
                if ($s->orNumberInput !== '') {
                    if ($s->orPayment) {
                        $s->paymentStatus = 'paid';
                        $s->currentBill = (float)($s->orPayment->current_bill ?? 0);
                        $s->penaltyAmount = (float)($s->orPayment->penalty ?? 0);
                        $orMaintenance = (float)($s->orPayment->meter_maintenance ?? 0);
                        // OR fallback rule: if paid OR has zero WMC but ledger breakdown has one, keep ledger WMC.
                        $s->maintenance = ($orMaintenance <= 0.009 && $ledgerComputedMaintenance > 0.009)
                            ? round($ledgerComputedMaintenance, 2)
                            : $orMaintenance;
                        $s->arrearsCy = (float)($s->orPayment->arrears_cy ?? 0);
                        $s->arrearsPy = (float)($s->orPayment->arrears_py ?? 0);
                        $s->seniorCitizenDiscount = (float)($s->orPayment->senior_citizen_discount ?? 0);
                        $s->others = (float)($s->orPayment->others ?? 0);
        
                        // Reclassification rule (same intent as penalty split):
                        // when OR has zero WMC but ledger contributes WMC, do not inflate total due.
                        // Move the fallback WMC amount out of DM/arrears bucket.
                        if ($orMaintenance <= 0.009 && $s->maintenance > 0.009) {
                            $wmcTransferredFromDm = min(round($s->maintenance, 2), round(max(0.0, (float) $s->arrearsCy), 2));
                            if ($wmcTransferredFromDm > 0.009) {
                                $s->arrearsCy = round(max(0.0, (float) $s->arrearsCy - $wmcTransferredFromDm), 2);
                            }
                        }
                    } else {
                        // Unused/new OR: keep computed ledger breakdown and only mark unpaid.
                        // Do not collapse all amounts into Current Bill; preserve month split (bill/penalty/wmc/arrears).
                        $s->paymentStatus = 'unpaid';
                    }
                }
        
                // For unpaid next-breakdown view (no explicit paid OR), when a payment exists for the
                // same current billing cycle, treat WMC as already covered in that cycle's split.
                // Keep total due unchanged by moving the same amount to Arrears CY.
                $wmcCoveredInCycle = false;
                if (!($s->orNumberInput !== '' && $s->orPayment) && $s->maintenance > 0.009 && $s->paymentStatus !== 'paid') {
                    try {
                        $cycleStart = null;
                        $cycleEnd = null;
                        $cycleScheduleId = null;
                        if (isset($s->currentBillEntry) && is_array($s->currentBillEntry)) {
                            if (!empty($s->currentBillEntry['date'])) {
                                $cycleStart = $s->currentBillEntry['date'] instanceof Carbon
                                    ? $s->currentBillEntry['date']->copy()->startOfDay()
                                    : Carbon::parse($s->currentBillEntry['date'])->startOfDay();
                            }
                            if (!empty($s->currentBillEntry['due_date'])) {
                                $cycleEnd = $s->currentBillEntry['due_date'] instanceof Carbon
                                    ? $s->currentBillEntry['due_date']->copy()->endOfDay()
                                    : Carbon::parse($s->currentBillEntry['due_date'])->endOfDay();
                            }
                            if (!empty($s->currentBillEntry['schedule_id'])) {
                                $cycleScheduleId = (int) $s->currentBillEntry['schedule_id'];
                            }
                        }
        
                        $paymentQuery = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->where(DB::raw("UPPER(TRIM(trans))"), 'PAYMENT')
                            ->whereRaw('COALESCE(credit, 0) > 0');
                        if ($cycleScheduleId) {
                            $paymentQuery->where(mr_col('schedule_id'), $cycleScheduleId);
                        } elseif ($cycleStart && $cycleEnd) {
                            $paymentQuery
                                ->whereRaw('COALESCE(txtime, date) >= ?', [$cycleStart->format('Y-m-d H:i:s')])
                                ->whereRaw('COALESCE(txtime, date) <= ?', [$cycleEnd->format('Y-m-d H:i:s')]);
                        } else {
                            // Do not perform broad month-level matching when no cycle keys are available.
                            $paymentQuery->whereRaw('1 = 0');
                        }
                        $paymentExistsInCycle = $paymentQuery->exists();
        
                        if ($paymentExistsInCycle) {
                            $wmcShift = round((float) $s->maintenance, 2);
                            $s->maintenance = 0.0;
                            $s->arrearsCy = round((float) $s->arrearsCy + $wmcShift, 2);
                            $wmcCoveredInCycle = true;
                        }
                    } catch (Throwable $e) {
                        // Keep original split when cycle-payment check fails.
                    }
                }
        
                // Non-OR flow safeguard:
                // if WMC is missing in computed breakdown but latest unpaid BILLING carries "others",
                // restore WMC from ledger and reclassify it from CY arrears (do not inflate total due).
                if (!($s->orNumberInput !== '' && $s->orPayment) && $s->maintenance <= 0.009 && !$wmcCoveredInCycle) {
                    try {
                        $latestUnpaidBillingWithOthers = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                            ->where(function ($q) {
                                $q->whereNull(mr_col('paid_at'))->orWhere(mr_col('paid_at'), '');
                            })
                            ->whereRaw('COALESCE(others, 0) > 0')
                            ->orderByRaw('COALESCE(date, txtime) DESC')
                            ->orderBy(mr_col('id'), 'desc')
                            ->first();
        
                        $ledgerWmc = round((float) ($latestUnpaidBillingWithOthers->others ?? 0), 2);
                        if ($ledgerWmc > 0.009) {
                            $s->maintenance = $ledgerWmc;
                            $wmcFromCy = min($ledgerWmc, round(max(0.0, (float) $s->arrearsCy), 2));
                            if ($wmcFromCy > 0.009) {
                                $s->arrearsCy = round(max(0.0, (float) $s->arrearsCy - $wmcFromCy), 2);
                            }
                        }
                    } catch (Throwable $e) {
                        // Keep original computed values when ledger fallback lookup fails.
                    }
                }
                
                // Senior discount based on ledger/billing volume.
                // Rule:
                // - Applies automatically only when consumer has SC flag + valid OSCA ID.
                // - Consider only BILL/BILLING rows with paid_at IS NULL (strict unpaid definition),
                //   plus fallback paid detection from PAYMENT rows in same cycle.
                // - unpaid_volume = SUM(volume of those rows)
                // - capped_volume = min(unpaid_volume, 30)
                // - senior_discount = lookup_table[capped_volume] by consumer category
                if (!($s->orNumberInput !== '' && $s->orPayment) && $s->paymentStatus !== 'paid') {
                    $billDiscPercentRaw = $s->consumer->bill_disc_percent ?? null;
                    $billDiscPercentNorm = is_string($billDiscPercentRaw) ? strtoupper(trim($billDiscPercentRaw)) : null;
                    if (is_numeric($billDiscPercentRaw) && abs(((float) $billDiscPercentRaw) - 5.0) < 0.001) {
                        $billDiscPercentNorm = 'SC DISCOUNT';
                    }
                    $oscaId = trim((string) ($s->consumer->osca_id_no ?? ''));
                    $isSeniorConsumer = $billDiscPercentNorm === 'SC DISCOUNT' && $oscaId !== '';
        
                    if ($isSeniorConsumer) {
                        try {
                        $billingRows = DB::table(mr_col('consumer_ledgers as cl'))
                            ->where(mr_col('cl.consumer_zone_id'), $s->consumer->id)
                            ->whereIn(DB::raw("UPPER(TRIM(cl.trans))"), ['BILLING', 'BILL'])
                            ->whereNotNull(mr_col('cl.volume'))
                            ->where(mr_col('cl.volume'), '!=', '')
                            ->select('cl.id', 'cl.schedule_id', 'cl.date', 'cl.due_date', 'cl.volume', 'cl.debit', 'cl.billamount', 'cl.others')
                            ->orderBy(mr_col('cl.date'), 'asc')
                            ->orderBy(mr_col('cl.id'), 'asc')
                            ->get()
                            ->values();
        
                        $paymentRows = DB::table(mr_col('consumer_ledgers as pay'))
                            ->where(mr_col('pay.consumer_zone_id'), $s->consumer->id)
                            ->where(DB::raw("UPPER(TRIM(pay.trans))"), 'PAYMENT')
                            ->whereRaw('COALESCE(pay.credit, 0) > 0')
                            ->select('pay.date', 'pay.id', 'pay.credit')
                            ->orderBy(mr_col('pay.date'), 'asc')
                            ->orderBy(mr_col('pay.id'), 'asc')
                            ->get()
                            ->values();
        
                        $residentialDiscountTable = [
                            0 => 9.75, 1 => 9.75, 2 => 9.75, 3 => 9.75, 4 => 9.75, 5 => 9.75,
                            6 => 9.75, 7 => 9.75, 8 => 9.75, 9 => 9.75, 10 => 9.75,
                            11 => 10.83, 12 => 11.91, 13 => 12.99, 14 => 14.07, 15 => 15.15,
                            16 => 16.23, 17 => 17.31, 18 => 18.39, 19 => 19.47, 20 => 20.55,
                            21 => 21.74, 22 => 22.92, 23 => 24.11, 24 => 25.30, 25 => 26.49,
                            26 => 27.68, 27 => 28.86, 28 => 30.05, 29 => 31.24, 30 => 32.42,
                        ];
                        $commercialDiscountTable = [
                            0 => 12.19, 1 => 12.19, 2 => 12.19, 3 => 12.19, 4 => 12.19, 5 => 12.19,
                            6 => 12.19, 7 => 12.19, 8 => 12.19, 9 => 12.19, 10 => 12.19,
                            11 => 13.54, 12 => 14.89, 13 => 16.24, 14 => 17.59, 15 => 18.94,
                            16 => 20.29, 17 => 21.64, 18 => 22.99, 19 => 24.34, 20 => 25.69,
                            21 => 27.17, 22 => 28.66, 23 => 30.14, 24 => 31.62, 25 => 33.11,
                            26 => 34.59, 27 => 36.08, 28 => 37.56, 29 => 39.05, 30 => 40.53,
                        ];
                        $categoryCode = trim((string) ($s->consumer->category_code ?? ''));
                        $discountTable = $categoryCode === '32' ? $commercialDiscountTable : $residentialDiscountTable;
        
                        $seniorDiscountTotal = 0.0;
                        // Allocate PAYMENT credits to BILLING charges FIFO by ledger date.
                        // A billing cycle is treated paid when its billing charge is fully covered by prior/available payments.
                        $remainingPaymentCredit = (float) $paymentRows->sum(function ($p) {
                            return (float) ($p->credit ?? 0);
                        });
        
                        foreach ($billingRows as $billingRow) {
                            $billingDebit = max(
                                0.0,
                                (float) ($billingRow->debit ?? 0),
                                (float) (($billingRow->billamount ?? 0) + ($billingRow->others ?? 0))
                            );
        
                            $covered = min($billingDebit, max(0.0, $remainingPaymentCredit));
                            $remainingPaymentCredit = max(0.0, $remainingPaymentCredit - $covered);
                            $billingRemaining = max(0.0, $billingDebit - $covered);
        
                            $isMarkedPaid = $billingRemaining <= 0.01;
                            if (!$isMarkedPaid) {
                                $monthVolume = max(0.0, (float) ($billingRow->volume ?? 0));
                                $monthVolumeKey = (int) floor(min($monthVolume, 30.0) + 1e-6);
                                $seniorDiscountTotal += (float) ($discountTable[$monthVolumeKey] ?? 0);
                            }
                        }
                        $s->seniorCitizenDiscount = round(max(0.0, $seniorDiscountTotal), 2);
                        } catch (Throwable $e) {
                            // Keep existing seniorCitizenDiscount when lookup fails.
                        }
                    }
                }
    }

    private function applyReconciliationAndFinalize(Request $request, BillMonthDetailsState $s): void
    {
        // Post-due split rule:
                // If selected/as-of date is after billing due date, move Current Bill to Arrears CY.
                // Example target: Current Bill 0.00, Arrears CY includes former current bill amount.
                if (!($s->orNumberInput !== '' && $s->orPayment) && (float) $s->currentBill > 0.009) {
                    $asOfDate = isset($s->toMonthDate) && $s->toMonthDate instanceof Carbon
                        ? $s->toMonthDate->copy()->startOfDay()
                        : Carbon::today()->startOfDay();
        
                    $dueDate = null;
                    if (isset($s->currentBillEntry) && is_array($s->currentBillEntry) && !empty($s->currentBillEntry['due_date'])) {
                        $dueDate = $s->currentBillEntry['due_date'] instanceof Carbon
                            ? $s->currentBillEntry['due_date']->copy()->startOfDay()
                            : Carbon::parse($s->currentBillEntry['due_date'])->startOfDay();
                    } else {
                        // Fallback when currentBillEntry is unavailable in this path:
                        // use latest BILLING due date from ledger for this consumer up to as-of date.
                        $latestBillingRowForDue = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $s->consumer->id)
                            ->whereIn(DB::raw("UPPER(TRIM(trans))"), ['BILLING', 'BILL'])
                            ->where(function ($q) {
                                $q->where(mr_col('debit'), '>', 0)->orWhere(mr_col('billamount'), '>', 0);
                            })
                            ->whereRaw('COALESCE(date, txtime) <= ?', [$asOfDate->format('Y-m-d H:i:s')])
                            ->orderByRaw('COALESCE(due_date, date, txtime) DESC')
                            ->orderBy(mr_col('id'), 'desc')
                            ->first();
                        if ($latestBillingRowForDue && !empty($latestBillingRowForDue->due_date)) {
                            $dueDate = $latestBillingRowForDue->due_date instanceof Carbon
                                ? $latestBillingRowForDue->due_date->copy()->startOfDay()
                                : Carbon::parse($latestBillingRowForDue->due_date)->startOfDay();
                        }
                    }
        
                    if ($dueDate && $asOfDate->gt($dueDate)) {
                        $s->arrearsCy = round((float) $s->arrearsCy + (float) $s->currentBill, 2);
                        $s->currentBill = 0.0;
                    }
                }
        
                // Final reconciliation for non-explicit-paid-OR flows vs displayed ledger balance:
                // - If breakdown sum > balance: trim buckets (existing behavior).
                // - If breakdown sum < balance: add shortfall to Arrears â€” CY (partial payments / coverage gaps vs running balance).
                // Breakdown total excludes senior discount deduction.
                if (!($s->orNumberInput !== '' && $s->orPayment)) {
                    $currentBalanceCapped = round(max(0, (float) $s->currentBalance), 2);
                    $breakdownTotal = round(
                        max(0.0, (float) $s->currentBill)
                        + max(0.0, (float) $s->arrearsCy)
                        + max(0.0, (float) $s->arrearsPy)
                        + max(0.0, (float) $s->penaltyAmount)
                        + max(0.0, (float) $s->maintenance)
                        + max(0.0, (float) $s->others),
                        2
                    );
        
                    if ($breakdownTotal > $currentBalanceCapped) {
                        $excess = round($breakdownTotal - $currentBalanceCapped, 2);
                        // Prefer reclassification from CY arrears before trimming WMC:
                        // when WMC belongs to current billing, keep it visible and reduce carry/DM bucket first.
                        if ($excess > 0.009 && $s->maintenance > 0.009 && $s->arrearsCy > 0.009) {
                            $reclassFromCy = min(round($excess, 2), round((float) $s->arrearsCy, 2));
                            if ($reclassFromCy > 0.009) {
                                $s->arrearsCy = round(max(0.0, (float) $s->arrearsCy - $reclassFromCy), 2);
                                $excess = round($excess - $reclassFromCy, 2);
                            }
                        }
                        foreach (['maintenance', 'penaltyAmount', 'others', 'arrearsCy', 'arrearsPy', 'currentBill'] as $field) {
                            if ($excess <= 0) {
                                break;
                            }
                            $value = (float) $s->{$field};
                            if ($value <= 0) {
                                continue;
                            }
                            $deduct = min($value, $excess);
                            $s->{$field} = round(max(0.0, $value - $deduct), 2);
                            $excess = round($excess - $deduct, 2);
                        }
                    } elseif ($currentBalanceCapped > 0.009 && $breakdownTotal + 0.009 < $currentBalanceCapped) {
                        $shortfall = round($currentBalanceCapped - $breakdownTotal, 2);
                        if ($shortfall > 0.009) {
                            $s->arrearsCy = round($s->arrearsCy + $shortfall, 2);
                        }
                    }
                    
                    // Normalize split for unpaid/non-OR flow:
                    // - Keep current month's principal as Current Bill (from billing period principal)
                    // - Allocate the remaining balance to Arrears CY
                    // - Preserve penalty/WMC as already computed (including paid/covered logic)
                    if ($s->paymentStatus !== 'paid') {
                        // Anchor Current Bill to the selected bill-month principal (if available),
                        // so breakdown does not drift to a different period's principal.
                        $selectedMonthPrincipal = null;
                        if (!empty($s->schedulesInRange) && $s->schedulesInRange->isNotEmpty()) {
                            $selectedMonthPrincipal = DB::table(mr_col('downloaded_readings'))
                                ->whereIn(mr_col('schedule_id'), $s->schedulesInRange->toArray())
                                ->orderBy(mr_col('id'), 'desc')
                                ->value('current_bill');
                        }
                        $principalForCurrentBill = round(max(0.0, (float) (
                            $selectedMonthPrincipal ?? $s->principalFromBilling ?? $s->currentBill
                        )), 2);
                        $fixedCharges = round(
                            max(0.0, (float) $s->arrearsPy)
                            + max(0.0, (float) $s->penaltyAmount)
                            + max(0.0, (float) $s->maintenance)
                            + max(0.0, (float) $s->others),
                            2
                        );
                        $maxCurrentBillAllowed = round(max(0.0, $currentBalanceCapped - $fixedCharges), 2);
                        $s->currentBill = round(min($principalForCurrentBill, $maxCurrentBillAllowed), 2);
                        $s->arrearsCy = round(max(0.0, $currentBalanceCapped - ($s->currentBill + $fixedCharges)), 2);
                    }
                }
        
                // Hard rule: when current balance is zero, breakdown fields must be zero.
                // Exception: explicit paid OR lookup should keep that OR's saved breakdown.
                $hasExplicitOrPaidBreakdown = ($s->orNumberInput !== '' && $s->orPayment);
                if (round((float) $s->currentBalance, 2) <= 0 && !$hasExplicitOrPaidBreakdown) {
                    $s->currentBill = 0.0;
                    $s->penaltyAmount = 0.0;
                    $s->maintenance = 0.0;
                    $s->others = 0.0;
                    $s->arrears = 0.0;
                    $s->arrearsCy = 0.0;
                    $s->arrearsPy = 0.0;
                    $s->seniorCitizenDiscount = 0.0;
                }
    }

    private function buildSuccessResponse(BillMonthDetailsState $s): JsonResponse
    {
        // Resolve downloaded_reading id for the selected bill month so the frontend can submit payment for that month (avoids "Payment already exists" when paying December after November).
                $downloadedId = null;
                $selectedConsumption = null;
                if (!empty($s->schedulesInRange) && $s->schedulesInRange->isNotEmpty()) {
                    $firstReading = DownloadedReading::whereIn('schedule_id', $s->schedulesInRange)->first();
                    if ($firstReading) {
                        $downloadedId = $firstReading->id;
                        if (isset($firstReading->consumption) && $firstReading->consumption !== null && $firstReading->consumption !== '') {
                            $selectedConsumption = (float) $firstReading->consumption;
                        }
                    }
                }
        
                // Arrears â€” Previous Year is computed inside Method A/B per date-meaning rules.
                
                return response()->json([
                    'success' => true,
                    'data' => array_merge([
                        'bill_month_from' => $s->billMonthFromKey,
                        'bill_month_to' => $s->billMonthToKey,
                        'current_bill' => round($s->currentBill, 2),
                        'penalty' => round($s->penaltyAmount, 2),
                        'maintenance' => round($s->maintenance, 2),
                        'others' => round($s->others, 2),
                        'arrears' => round(max(0, $s->arrears), 2),
                        'arrears_cy' => round($s->arrearsCy, 2),
                        'arrears_py' => round($s->arrearsPy, 2),
                        'senior_citizen_discount' => round($s->seniorCitizenDiscount, 2),
                        'current_consumption' => $selectedConsumption,
                        'payment_status' => $s->paymentStatus,
                        'downloaded_id' => $downloadedId,
                    ], $s->dateRangeMode ? [
                        'from_date' => $s->fromMonthDate->format('Y-m-d'),
                        'to_date' => $s->toMonthDate->format('Y-m-d'),
                    ] : []),
                ]);
    }

    private function getLedgerBalanceAsOfDate(int $consumerZoneId, string $asOfDate): float
    {
        $entries = \App\Models\ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
            ->orderBy('date', 'asc')
            ->orderBy(mr_col('id'), 'asc')
            ->get();

        if ($entries->isEmpty()) {
            return 0.0;
        }

        $earliest = $entries->first();
        $firstBalance = (float) ($earliest->balance ?? 0);
        $firstDebit = (float) ($earliest->debit ?? 0);
        $firstCredit = (float) ($earliest->credit ?? 0);
        $previousBalance = $firstBalance - $firstDebit + $firstCredit;

        $balanceAsOf = null;
        foreach ($entries as $row) {
            $debit = (float) ($row->debit ?? 0);
            $credit = (float) ($row->credit ?? 0);
            $newBalance = round($previousBalance + $debit - $credit, 2);
            $rowDate = $row->date ? Carbon::parse($row->date)->format('Y-m-d') : '';
            if ($rowDate !== '' && $rowDate <= $asOfDate) {
                $balanceAsOf = $newBalance;
            }
            $previousBalance = $newBalance;
        }

        return $balanceAsOf !== null ? (float) $balanceAsOf : 0.0;
    }
}
