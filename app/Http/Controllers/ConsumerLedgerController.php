<?php

namespace App\Http\Controllers;
use App\Imports\ConsumerLedgerImport;
use App\Models\ConsumerLedger;
use App\Models\ConsumerZoneOne;
use App\Models\OutstandingPayment;
use App\Models\Penalty;
use App\Models\Collection;
use App\Models\DisconnectionOrder;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ConsumerLedgerController extends Controller
{
     /**
     * One ledger row: same running-balance step as consumer ledger UI (footer Current Balance).
     */
    public static function nextRunningBalance(float $runningBalance, ConsumerLedger $ledger): float
    {
        $debit = (float) ($ledger->debit ?? 0);
        $credit = (float) ($ledger->credit ?? 0);
        $trans = strtoupper(trim($ledger->trans ?? ''));

        $newBalance = $runningBalance + $debit - $credit;

        if ($trans === 'PAYMENT' && $credit > 0) {
            if (abs($credit - $runningBalance) <= 0.01) {
                $newBalance = 0.00;
            } elseif ($runningBalance > 0 && $credit > $runningBalance && ($credit - $runningBalance) <= 0.01) {
                $newBalance = 0.00;
            }
        }

        return round($newBalance, 2);
    }

    /**
     * Same visibility rules as Account Ledger F10 (exclude pending/cancelled BAM).
     */
    public static function applyVisibleLedgerScope($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('billing_adjustment_id')
                ->orWhereHas('billingAdjustment', function ($q2) {
                    $q2->where('status', 'Approved');
                });
        });
    }

    /**
     * Same row ordering as getLedger() so running balance matches the on-screen ledger.
     */
    public static function applyAccountLedgerDisplayOrder($query): void
    {
        $query->orderByRaw('CAST(date AS DATE) ASC')
            ->orderByRaw("
        CASE
            WHEN UPPER(TRIM(trans)) IN ('PAYMENT', 'CM') THEN
                COALESCE(
                    (SELECT cp.created_at
                     FROM consumer_payments cp
                     WHERE cp.id = consumer_ledgers.consumer_payment_id
                     LIMIT 1),
                    (SELECT cp2.created_at
                     FROM consumer_payments cp2
                     WHERE cp2.reading_id = consumer_ledgers.downloaded_reading_id
                     ORDER BY cp2.created_at DESC, cp2.id DESC
                     LIMIT 1),
                    (SELECT cp3.created_at
                     FROM consumer_payments cp3
                     WHERE cp3.or_number = REPLACE(consumer_ledgers.reference, '-SC', '')
                     ORDER BY cp3.created_at DESC, cp3.id DESC
                     LIMIT 1),
                     (SELECT ba.created_at
                     FROM billing_adjustments ba
                     WHERE ba.id = consumer_ledgers.billing_adjustment_id
                     LIMIT 1),
                    consumer_ledgers.txtime,
                    consumer_ledgers.created_at
                )
            WHEN UPPER(TRIM(trans)) IN ('BILLING','BILL') THEN
                COALESCE(
                    (SELECT dr.created_at
                     FROM downloaded_readings dr
                     WHERE dr.id = consumer_ledgers.downloaded_reading_id
                     LIMIT 1),
                    (SELECT dr2.created_at
                     FROM downloaded_readings dr2
                     WHERE dr2.schedule_id = consumer_ledgers.schedule_id
                     ORDER BY dr2.created_at DESC, dr2.id DESC
                     LIMIT 1),
                     (SELECT ba2.created_at
                     FROM billing_adjustments ba2
                     WHERE ba2.id = consumer_ledgers.billing_adjustment_id
                     LIMIT 1),
                    consumer_ledgers.txtime,
                    consumer_ledgers.created_at
                )
            ELSE
                COALESCE(consumer_ledgers.txtime, consumer_ledgers.created_at)
        END ASC
    ")
            ->orderBy('id', 'asc');
    }

    /**
     * Footer Current Balance for Account Ledger (all years, optional date cutoff).
     */
    public static function computeAccountLedgerFooterBalance(int $consumerZoneId, ?string $cutoffDateYmd = null): float
    {
        $ledgersQuery = ConsumerLedger::where('consumer_zone_id', $consumerZoneId);
        self::applyVisibleLedgerScope($ledgersQuery);

        if ($cutoffDateYmd !== null && $cutoffDateYmd !== '') {
            $ledgersQuery->whereDate('date', '<=', $cutoffDateYmd);
        }

        self::applyAccountLedgerDisplayOrder($ledgersQuery);
        $ledgers = $ledgersQuery->get(['debit', 'credit', 'trans']);

        $runningBalance = 0.00;
        foreach ($ledgers as $ledger) {
            $runningBalance = self::nextRunningBalance($runningBalance, $ledger);
        }

        return round($runningBalance, 2);
    }

    /**
     * @param  iterable<int|string>  $consumerIds
     */
    public static function computeAccountLedgerFooterBalancesBulk(iterable $consumerIds, ?string $cutoffDateYmd = null): \Illuminate\Support\Collection
    {
        $balances = [];
        foreach (collect($consumerIds)->filter()->unique() as $consumerId) {
            $balances[(int) $consumerId] = self::computeAccountLedgerFooterBalance((int) $consumerId, $cutoffDateYmd);
        }

        return collect($balances);
    }

    /**
     * Matches getLedger() summary.balance / ledger.blade.php footer Current Balance for the same year scope.
     *
     * @param  string|int|null  $year  Omit or null/'' for all years (no WHERE on date).
     */
    public static function computeLedgerFooterBalance(int $consumerZoneId, $year = null): float
    {
        if ($year !== null && $year !== '') {
            $ledgersQuery = ConsumerLedger::where('consumer_zone_id', $consumerZoneId);
            self::applyVisibleLedgerScope($ledgersQuery);
            $ledgersQuery->whereYear('date', $year);
            self::applyAccountLedgerDisplayOrder($ledgersQuery);
            $ledgers = $ledgersQuery->get(['debit', 'credit', 'trans']);

            $runningBalance = 0.00;
            foreach ($ledgers as $ledger) {
                $runningBalance = self::nextRunningBalance($runningBalance, $ledger);
            }

            return round($runningBalance, 2);
        }

        return self::computeAccountLedgerFooterBalance($consumerZoneId);
    }

    /**
     * Recalculated running balance immediately before the ledger row with id $beforeThisLedgerId is applied
     * (same ordering and nextRunningBalance rules as getLedger / footer). Used for surcharge "Arrears" display.
     */
    public static function computeRunningBalanceBeforeLedgerEntry(int $consumerZoneId, int $beforeThisLedgerId, $year = null): float
    {
        $ledgersQuery = ConsumerLedger::where('consumer_zone_id', $consumerZoneId);
        self::applyVisibleLedgerScope($ledgersQuery);

        if ($year !== null && $year !== '') {
            $ledgersQuery->whereYear('date', $year);
        }

        self::applyAccountLedgerDisplayOrder($ledgersQuery);
        $ledgers = $ledgersQuery->get(['id', 'debit', 'credit', 'trans']);

        $runningBalance = 0.00;
        foreach ($ledgers as $ledger) {
            if ((int) $ledger->id === (int) $beforeThisLedgerId) {
                return round($runningBalance, 2);
            }
            $runningBalance = self::nextRunningBalance($runningBalance, $ledger);
        }

        return 0.00;
    }

    
    /**
     * Get ledger data for a consumer by account number
     */
    public function getLedger(Request $request)
    {
        $accountNo = $request->input('account_no');
        $year = $request->input('year', date('Y'));

        \Log::info('Ledger request received', [
            'account_no' => $accountNo,
            'year' => $year
        ]);

        if (!$accountNo) {
            return response()->json([
                'success' => false,
                'message' => 'Account number is required'
            ], 400);
        }

        // Find consumer by account_no (exact + normalized)
        $consumer = ConsumerZoneOne::findByAccountNo($accountNo);

        \Log::info('Consumer lookup result', [
            'found' => $consumer ? true : false,
            'consumer_id' => $consumer ? $consumer->id : null
        ]);

        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer not found'
            ], 404);
        }

        $ledgersQuery = ConsumerLedger::where('consumer_zone_id', $consumer->id);
        self::applyVisibleLedgerScope($ledgersQuery);

        if ($year && $year != '') {
            $ledgersQuery->whereYear('date', $year);
        }

        self::applyAccountLedgerDisplayOrder($ledgersQuery);
        $ledgers = $ledgersQuery->get();
        
        \Log::info('Ledgers fetched from database', [
            'consumer_zone_id' => $consumer->id,
            'year' => $year,
            'count' => $ledgers->count(),
        ]);

        // Calculate running balance and handle exact payment matches
        // When payment exactly matches balance, show 0.00 instead of negative
        $runningBalance = 0.00;
        // $firstEntry = $ledgers->first();
        
        // // If first entry exists, start with its balance minus its debit/credit to get the starting balance
        // if ($firstEntry) {
        //     $firstBalance = (float)($firstEntry->balance ?? 0);
        //     $firstDebit = (float)($firstEntry->debit ?? 0);
        //     $firstCredit = (float)($firstEntry->credit ?? 0);
        //     $runningBalance = $firstBalance - $firstDebit + $firstCredit;
        // }
        
        // $ledgersWithBalance = $ledgers->map(function($ledger) use (&$runningBalance) {
        //     $debit = (float)($ledger->debit ?? 0);
        //     $credit = (float)($ledger->credit ?? 0);
        //     $trans = strtoupper(trim($ledger->trans ?? ''));
            
        //     // Calculate new balance: previous balance + debit - credit
        //     $newBalance = $runningBalance + $debit - $credit;
            
        //     // Special handling for PAYMENT entries: if payment exactly matches balance, show 0.00
        //     if ($trans === 'PAYMENT' && $credit > 0) {
        //         // If payment exactly matches the previous balance (within 0.01 rounding tolerance), set to 0.00
        //         if (abs($credit - $runningBalance) <= 0.01) {
        //             $newBalance = 0.00;
        //         }
        //         // If payment is slightly more than balance (within 0.01), also set to 0.00
        //         elseif ($runningBalance > 0 && $credit > $runningBalance && ($credit - $runningBalance) <= 0.01) {
        //             $newBalance = 0.00;
        //         }
        //     }
            
        //     // Round to 2 decimal places
        //     $newBalance = round($newBalance, 2);
            
        //     // Update running balance for next iteration
        //     $runningBalance = $newBalance;
        
             $ledgersWithBalance = $ledgers->map(function ($ledger) use (&$runningBalance) {
            $runningBalance = self::nextRunningBalance($runningBalance, $ledger);
            $newBalance = $runningBalance;
            
            return [
                'id' => $ledger->id ?? null,
                'trans' => $ledger->trans ?? '',
                'date' => $ledger->date ?? '',
                'due_date' => $ledger->due_date ?? '',
                'reference' => $ledger->reference ?? '',
                'reading' => $ledger->reading ?? '',
                'volume' => $ledger->volume ?? '',
                'billamount' => $ledger->billamount ?? 0,
                'penalty' => $ledger->penalty ?? 0,
                'others' => $ledger->others ?? 0,
                'debit' => $ledger->debit ?? 0,
                'credit' => $ledger->credit ?? 0,
                'balance' => $newBalance, // Use calculated balance with exact payment matching logic
                'username' => $this->extractFirstName($ledger->username ?? ''),
                'txtime' => $ledger->txtime ?? '',
            ];
        });

        // Calculate totals from the fetched ledgers
        $totalBill = $ledgers->sum('billamount');
        $totalLoans = 0;
        
        // Get the current balance from the last calculated entry
        $lastEntry = $ledgersWithBalance->last();
        $currentBalance = $lastEntry ? (float)($lastEntry['balance'] ?? 0) : 0.00;
        
        // Get latest bill amount from the ledgers
        $latestBillEntry = $ledgers->where('trans', 'BILLING')
            ->sortByDesc('date')
            ->first();
        $latestBillAmount = $latestBillEntry ? (float)($latestBillEntry->billamount ?? 0) : 0.00;

        \Log::info('Ledger data prepared with calculated balances (exact payment matching)', [
            'ledgers_count' => $ledgers->count(),
            'total_bill' => $totalBill,
            'current_balance' => $currentBalance,
            'latest_bill_amount' => $latestBillAmount
        ]);

        return response()->json([
            'success' => true,
            'consumer' => [
                'account_no' => $consumer->account_no,
                'account_name' => $consumer->account_name,
                'zone_code' => $consumer->zone_code,
                'address1' => $consumer->address1,
                'meter_number' => $consumer->meter_number,
                'cons_ctrl' => $consumer->cons_ctrl,
                'status_code' => $consumer->status_code,
                'status_label' => $consumer->status_label,
                'sequence' => $consumer->sequence,
                'bill_disc_percent' => $consumer->bill_disc_percent,
                'osca_id_no' => $consumer->osca_id_no,
                'bill_disc_updated_at' => ! empty($consumer->bill_disc_updated_at)
                    ? Carbon::parse($consumer->bill_disc_updated_at)->format('Y-m-d')
                    : null,
            ],
            'ledgers' => $ledgersWithBalance->values(),
            'summary' => [
                'total_bill' => $totalBill,
                'total_loans' => $totalLoans,
                'balance' => $currentBalance,
                'latest_bill_amount' => $latestBillAmount,
                'year' => $year,
                'total_transactions' => $ledgers->count()
            ]
        ]);
    }

    /**
     * Get consumption data for a consumer by account number (Highly Optimized)
     */
    public function getConsumption(Request $request)
    {
        try {
            $accountNo = $request->input('account_no');
            $year = (int)$request->input('year', date('Y'));

            if (!$accountNo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account number is required'
                ], 400);
            }

            // Find consumer by account_no (only select needed columns)
            $consumer = ConsumerZoneOne::where('account_no', $accountNo)
                ->first(['id', 'account_no', 'account_name']);

            if (!$consumer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Consumer not found'
                ], 404);
            }

            $previousYear = $year - 1;
            
            // Use raw query builder for better performance
            // Limit date range to only what we need
            $startDate = "{$previousYear}-01-01";
            $endDate = ($year + 1) . "-01-01";
            
            // Try optimized query - also check for 'BILL' transaction type
            try {
                $consumptionData = DB::table('consumer_ledgers')
                    ->where('consumer_zone_id', $consumer->id)
                    ->whereIn('trans', ['BILLING', 'BILL']) // Accept both formats
                    ->where('date', '>=', $startDate)
                    ->where('date', '<', $endDate)
                    ->where(function($query) {
                        $query->whereNotNull('volume')
                              ->where('volume', '!=', '')
                              ->where('volume', '!=', '0');
                    })
                    ->select(
                        DB::raw('LEFT(date, 4) as year'),
                        DB::raw('SUBSTRING(date, 6, 2) as month'),
                        DB::raw('SUM(CAST(COALESCE(volume, 0) AS DECIMAL(10,2))) as total_consumption')
                    )
                    ->groupBy(DB::raw('LEFT(date, 4)'), DB::raw('SUBSTRING(date, 6, 2)'))
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();
            } catch (\Exception $e) {
                // Fallback to raw SQL if query builder fails
                \Log::warning('Query builder failed, using raw SQL: ' . $e->getMessage());
                $consumptionData = DB::select("
                    SELECT 
                        LEFT(date, 4) as year,
                        SUBSTRING(date, 6, 2) as month,
                        SUM(CAST(COALESCE(volume, 0) AS DECIMAL(10,2))) as total_consumption
                    FROM consumer_ledgers
                    WHERE consumer_zone_id = ?
                        AND trans IN ('BILLING', 'BILL')
                        AND date >= ?
                        AND date < ?
                        AND (volume IS NOT NULL AND volume != '' AND volume != '0')
                    GROUP BY LEFT(date, 4), SUBSTRING(date, 6, 2)
                    ORDER BY year ASC, month ASC
                ", [
                    $consumer->id,
                    $startDate,
                    $endDate
                ]);
            }
            
            // Debug: Log what we found
            \Log::info('Consumption query results', [
                'account_no' => $accountNo,
                'year' => $year,
                'consumer_id' => $consumer->id,
                'date_range' => [$startDate, $endDate],
                'results_count' => count($consumptionData),
                'results' => $consumptionData
            ]);

            // Also check raw data to see what's in the database
            $rawData = DB::table('consumer_ledgers')
                ->where('consumer_zone_id', $consumer->id)
                ->whereIn('trans', ['BILLING', 'BILL'])
                ->where('date', '>=', $startDate)
                ->where('date', '<', $endDate)
                ->select('date', 'trans', 'volume', 'billamount')
                ->orderBy('date')
                ->get();
            
            \Log::info('Raw billing entries found', [
                'account_no' => $accountNo,
                'year' => $year,
                'raw_count' => count($rawData),
                'sample' => $rawData->take(5)->toArray()
            ]);

            // Initialize arrays with 12 months (0 for months with no data)
            $monthlyData = array_fill(0, 12, 0);
            $previousYearMonthlyData = array_fill(0, 12, 0);

            // Process results
            foreach ($consumptionData as $data) {
                $monthIndex = (int)$data->month - 1; // Convert to 0-based index
                if ($monthIndex < 0 || $monthIndex > 11) continue; // Safety check
                
                $consumption = (float)$data->total_consumption;
                
                if ((int)$data->year == $year) {
                    $monthlyData[$monthIndex] = $consumption;
                } elseif ((int)$data->year == $previousYear) {
                    $previousYearMonthlyData[$monthIndex] = $consumption;
                }
            }

            // Calculate statistics (optimized - single pass)
            $totalConsumption = array_sum($monthlyData);
            $monthsWithData = array_filter($monthlyData, fn($v) => $v > 0);
            $monthCount = count($monthsWithData);
            $averageConsumption = $monthCount > 0 ? $totalConsumption / $monthCount : 0;
            $highestConsumption = $monthCount > 0 ? max($monthlyData) : 0;
            $lowestConsumption = $monthCount > 0 ? min($monthsWithData) : 0;

            return response()->json([
                'success' => true,
                'consumption' => $monthlyData,
                'previousYearData' => $previousYearMonthlyData,
                'statistics' => [
                    'total' => round($totalConsumption, 2),
                    'average' => round($averageConsumption, 2),
                    'highest' => round($highestConsumption, 2),
                    'lowest' => round($lowestConsumption, 2),
                ],
                'year' => $year,
                'previousYear' => $previousYear,
                'consumer' => [
                    'account_no' => $consumer->account_no,
                    'account_name' => $consumer->account_name,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getConsumption: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading consumption data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get BILL entries from meter_reading_schedules table
     * These are the latest billing records
     */
    private function getBillEntriesFromSchedules($accountNo, $consumerZoneId, $year = null)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $ledgerEntries = [];

        // Query meter_reading_schedules for BILL entries
        $schedulesQuery = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->leftJoin('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
            ->select(
                'mrs.id as schedule_id',
                'cz.account_no as account_number',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_reading',
                'mrs.previous_reading',
                'mrs.consumption',
                'mrs.current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'mrs.prepared_by',
                'mrs.created_at',
                'mrs.sedr_number',
                'dr.id as downloaded_id',
                'dr.current_bill as downloaded_current_bill',
                'dr.current_reading as downloaded_current_reading',
                'dr.consumption as downloaded_consumption'
            )
            ->where('mrs.consumer_zone_id', $consumerZoneId)
            ->whereNotNull('mrs.bill_date');

        // Apply year filter if provided
        if ($year && $year != '') {
            $schedulesQuery->where(function($query) use ($year) {
                $query->whereYear('mrs.bill_date', $year)
                      ->orWhereYear('mrs.bill_month', $year);
            });
        }

        $schedules = $schedulesQuery->orderBy('mrs.bill_date', 'desc')
                                   ->orderBy('mrs.created_at', 'desc')
                                   ->get();

        foreach ($schedules as $schedule) {
            // Check if this schedule already has a ConsumerLedger entry
            $existingLedger = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->whereIn('trans', ['BILL', 'BILLING']) // Accept both formats
                ->first();

            // Skip if already exists in ConsumerLedger (to avoid duplicates)
            if ($existingLedger) {
                continue;
            }

            $billDate = $schedule->bill_date ? Carbon::parse($schedule->bill_date) : null;
            $dueDate = $schedule->due_date ? Carbon::parse($schedule->due_date) : null;

            if (!$billDate) {
                continue;
            }

            // Use downloaded_readings data if available (actual submitted data), otherwise schedule's data
            $currentBill = $schedule->downloaded_current_bill ?? $schedule->current_bill ?? 0;
            $currentReading = $schedule->downloaded_current_reading ?? $schedule->current_reading ?? $schedule->previous_reading ?? '';
            $consumption = $schedule->downloaded_consumption ?? $schedule->consumption ?? 0;

            // Penalty is NOT shown in BILL entry - it will be created as a separate PENALTY row
            // Separate PENALTY entries are created by createPenaltyEntries() method
            // This matches past records where penalty appears as a separate row, not in the BILL entry
            $penalty = 0; // Always 0 in BILL entry - penalty is a separate row

            // Others column is always 20.00 (Water Maintenance Charge/Meter Rental)
            $others = 20.00;

            // Use the values we determined above
            $reading = $currentReading;
            $volume = $consumption;
            
            // Calculate debit (current_bill + others)
            $debit = round((float)$currentBill + (float)$others, 2);
            
            $ledgerEntries[] = [
                'id' => 'mrs_' . $schedule->schedule_id, // Prefix to avoid conflicts
                'trans' => 'BILLING', // Use 'BILLING' to match old format (not 'BILL')
                'date' => $billDate->format('Y-m-d'),
                'due_date' => $dueDate ? $dueDate->format('Y-m-d') : '',
                'reference' => $schedule->sedr_number ?? '', // Show SEDR number as reference
                'reading' => $reading,
                'volume' => $volume > 0 ? number_format((float)$volume, 2, '.', '') : '',
                'billamount' => round((float)$currentBill, 2),
                'penalty' => 0, // Always 0 - penalty is a separate PENALTY row, not shown in BILL entry
                'others' => round((float)$others, 2), // Always 20.00
                'debit' => $debit,
                'credit' => 0,
                'balance' => round((float)($schedule->total_amount ?? 0), 2),
                'username' => $this->extractFirstName($schedule->prepared_by ?? ''),
                'txtime' => $schedule->created_at ? Carbon::parse($schedule->created_at)->format('Y-m-d H:i:s') : '',
                'schedule_id' => $schedule->schedule_id, // Link to meter_reading_schedules
                'downloaded_reading_id' => $schedule->downloaded_id, // Link to downloaded_readings if exists
                'consumer_zone_id' => $consumerZoneId, // Link to consumer_ledgers
            ];
        }

        \Log::info('BILL entries from schedules', [
            'account_no' => $accountNo,
            'total_schedules' => $schedules->count(),
            'bill_entries' => count($ledgerEntries)
        ]);

        return $ledgerEntries;
    }

    /**
     * Get PAYMENT entries from consumer_payments table (preferred) or downloaded_readings (fallback)
     * These are the latest payment records
     */
    private function getPaymentEntriesFromDownloadedReadings($accountNo, $consumerZoneId, $year = null)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $ledgerEntries = [];

        // Query consumer_payments joined with downloaded_readings and meter_reading_schedules
        // Match the working implementation from MeterReadingController
        $paymentsQuery = DB::table('downloaded_readings as dr')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->on('cz.id', '=', 'dr.consumer_zone_id')
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select(
                'dr.id as downloaded_id',
                'dr.schedule_id',
                'cp.payment_amount',
                'cp.paid_at',
                'cp.created_at',
                'cp.or_number',
                'cp.created_by',
                'mrs.bill_date as related_bill_date'
            )
            ->where(function ($query) use ($accountNo, $normalizedAccount, $consumerZoneId) {
                if ($consumerZoneId) {
                    $query->where('dr.consumer_zone_id', $consumerZoneId)
                        ->orWhere('mrs.consumer_zone_id', $consumerZoneId);
                } else {
                    $query->where('cz.account_no', $accountNo)
                        ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                        ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNo))]);
                }
            })
            ->where(function($query) {
                $query->where('dr.status', 'paid')
                      ->orWhereNotNull('cp.paid_at')
                      ->orWhere(function($q) {
                          $q->whereNotNull('cp.payment_amount')
                            ->where('cp.payment_amount', '>', 0);
                      });
            });

        // Apply year filter if provided
        if ($year && $year != '') {
            $paymentsQuery->where(function($query) use ($year) {
                $query->whereYear('cp.paid_at', $year)
                      ->orWhereYear('cp.created_at', $year);
            });
        }

        $payments = $paymentsQuery->orderBy('cp.paid_at', 'desc')
                                  ->orderBy('cp.created_at', 'desc')
                                  ->get();

        foreach ($payments as $payment) {
            // Check if this payment already has a ConsumerLedger entry
            $existingLedger = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('downloaded_reading_id', $payment->downloaded_id)
                ->where('trans', 'PAYMENT')
                ->first();

            // Skip if already exists in ConsumerLedger (to avoid duplicates)
            if ($existingLedger) {
                continue;
            }

            // Get payment amount
            $paymentAmount = (float)($payment->payment_amount ?? 0);
            
            // Skip if no payment amount
            if ($paymentAmount <= 0) {
                continue;
            }

            // Use paid_at from consumer_payments
            $paidDate = null;
            if ($payment->paid_at) {
                try {
                    $paidDate = Carbon::parse($payment->paid_at);
                } catch (\Exception $e) {
                    // Skip if date parsing fails
                }
            }

            if (!$paidDate && $payment->created_at) {
                try {
                    $paidDate = Carbon::parse($payment->created_at);
                } catch (\Exception $e) {
                    // Skip if date parsing fails
                }
            }

            if (!$paidDate) {
                continue; // Skip if no valid date
            }

            // Get schedule_id
            $scheduleId = $payment->schedule_id ?? null;

            // Use related bill_date for sorting if available, otherwise use payment date
            $sortDate = $paidDate;
            if ($payment->related_bill_date) {
                try {
                    $sortDate = Carbon::parse($payment->related_bill_date);
                } catch (\Exception $e) {
                    $sortDate = $paidDate;
                }
            }
            
            $ledgerEntries[] = [
                'id' => 'pay_' . $payment->downloaded_id, // Prefix to avoid conflicts
                'trans' => 'PAYMENT',
                'date' => $paidDate->format('Y-m-d'), // Display the actual payment date
                'sort_date' => $sortDate->format('Y-m-d'), // Use bill_date for sorting when available
                'due_date' => '',
                'reference' => $payment->or_number ?? '',
                'reading' => '',
                'volume' => '',
                'billamount' => 0,
                'penalty' => 0,
                'others' => 0,
                'debit' => 0,
                'credit' => round($paymentAmount, 2),
                'balance' => 0, // Balance will be calculated in frontend
                'username' => $this->extractFirstName($payment->created_by ?? ''),
                'txtime' => $paidDate->format('Y-m-d H:i:s'),
                'schedule_id' => $scheduleId, // Link to meter_reading_schedules
                'downloaded_reading_id' => $payment->downloaded_id, // Link to downloaded_readings
                'consumer_zone_id' => $consumerZoneId, // Link to consumer_ledgers
            ];
        }

        \Log::info('PAYMENT entries from downloaded_readings', [
            'account_no' => $accountNo,
            'total_payments' => $payments->count(),
            'payment_entries' => count($ledgerEntries)
        ]);

        return $ledgerEntries;
    }

    /**
     * Get collection entries from collection table
     * These represent PAYMENT transactions from the collection table
     */
    private function getCollectionEntries($accountNo, $consumerZoneId, $year = null)
    {
        $ledgerEntries = [];

        // Check if collection table exists
        if (!Schema::hasTable('collection')) {
            \Log::info('Collection table does not exist, skipping collection entries');
            return $ledgerEntries;
        }

        try {
            $normalizedAccount = str_replace('-', '', $accountNo);

            // Query collection table for this account
            $collectionsQuery = Collection::where('account_no', $accountNo)
                ->where(function($query) {
                    $query->whereNull('cancel')
                          ->orWhere('cancel', '')
                          ->orWhere('cancel', '0');
                });

            // Apply year filter if provided
            if ($year && $year != '') {
                $collectionsQuery->where(function($query) use ($year) {
                    $query->whereYear('coll_date', $year)
                          ->orWhereRaw("YEAR(coll_date) = ?", [$year]);
                });
            }

            $collections = $collectionsQuery
                ->orderBy('coll_date', 'asc')
                ->orderByRaw('COALESCE(coll_time, "00:00:00") ASC')
                ->get();

            \Log::info('Collection entries fetched', [
                'account_no' => $accountNo,
                'year' => $year,
                'total_collections' => $collections->count()
            ]);

            // Get existing PAYMENT entries from consumer_ledgers to avoid duplicates
            $existingPaymentReferences = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('trans', 'PAYMENT')
                ->whereNotNull('reference')
                ->pluck('reference')
                ->toArray();

            foreach ($collections as $collection) {
                // Skip if this collection's or_number already exists as a PAYMENT reference
                if (!empty($collection->or_number) && in_array($collection->or_number, $existingPaymentReferences)) {
                    \Log::info('Skipping duplicate collection entry', [
                        'or_number' => $collection->or_number,
                        'coll_date' => $collection->coll_date
                    ]);
                    continue;
                }

                // Skip if no payment amount
                $payAmount = (float)($collection->pay_amount ?? 0);
                if ($payAmount <= 0) {
                    continue;
                }

                // Parse collection date
                $collDate = $collection->coll_date;
                if (!$collDate) {
                    continue;
                }

                try {
                    $collDateObj = Carbon::parse($collDate);
                } catch (\Exception $e) {
                    \Log::warning('Invalid coll_date in collection', [
                        'collection_id' => $collection->id ?? null,
                        'coll_date' => $collDate,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }

                // Parse collection time
                $collTime = $collection->coll_time ?? '00:00:00';
                
                // Validate time format (HH:MM:SS)
                if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $collTime)) {
                    $collTime = '00:00:00';
                }

                // Combine date and time for txtime
                try {
                    $txtime = $collDateObj->format('Y-m-d') . ' ' . $collTime;
                    $txtimeObj = Carbon::parse($txtime);
                } catch (\Exception $e) {
                    \Log::warning('Invalid txtime in collection', [
                        'collection_id' => $collection->id ?? null,
                        'coll_date' => $collDate,
                        'coll_time' => $collTime,
                        'error' => $e->getMessage()
                    ]);
                    $txtime = $collDateObj->format('Y-m-d H:i:s');
                }

                $ledgerEntries[] = [
                    'id' => 'coll_' . ($collection->id ?? uniqid()), // Prefix to avoid conflicts
                    'trans' => 'PAYMENT',
                    'date' => $collDateObj->format('Y-m-d'),
                    'due_date' => '',
                    'reference' => $collection->or_number ?? '',
                    'reading' => '',
                    'volume' => '',
                    'billamount' => 0,
                    'penalty' => 0,
                    'others' => 0,
                    'debit' => 0,
                    'credit' => round($payAmount, 2),
                    'balance' => 0, // Will be calculated in balance computation
                    'username' => $this->extractFirstName($collection->username ?? ''),
                    'txtime' => $txtime,
                    'consumer_zone_id' => $consumerZoneId,
                ];
            }

            \Log::info('Collection entries processed', [
                'account_no' => $accountNo,
                'total_collections' => $collections->count(),
                'collection_entries' => count($ledgerEntries)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching collection entries', [
                'account_no' => $accountNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $ledgerEntries;
    }

    /**
     * Create penalty entries for bills with passed due dates
     * This automatically records penalties in consumer_ledgers when due dates are reached
     */
    private function createPenaltyEntries($accountNo, $consumerZoneId, $year = null)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $penaltyEntries = [];
        $today = Carbon::today();

        // First, get existing penalties from penalties table
        // NOTE: Penalties from consumer_ledgers are already included in $existingLedgers in getLedger()
        // So we only need to fetch from penalties table here to avoid duplication
        $existingPenaltiesQuery = Penalty::where('consumer_zone_id', $consumerZoneId);
        
        // Apply year filter if provided
        if ($year && $year != '') {
            $existingPenaltiesQuery->where(function($query) use ($year) {
                $query->whereYear('date', $year)
                      ->orWhereYear('due_date', $year);
            });
        }
        
        $existingPenalties = $existingPenaltiesQuery->get()
            ->map(function($penalty) {
                return [
                    'id' => 'penalty_' . $penalty->id, // Use non-numeric ID to trigger balance recalculation
                    'trans' => 'PENALTY',
                    'date' => $penalty->date->format('Y-m-d'),
                    'due_date' => $penalty->due_date ? $penalty->due_date->format('Y-m-d') : '',
                    'reference' => $penalty->reference ?? '',
                    'reading' => '',
                    'volume' => '',
                    'billamount' => 0,
                    'penalty' => $penalty->penalty_amount,
                    'others' => 0,
                    'debit' => $penalty->penalty_amount,
                    'credit' => 0,
                    'balance' => 0, // Will be recalculated based on previous entry
                    'username' => $penalty->username ?? '',
                    'txtime' => $penalty->txtime ? $penalty->txtime->format('Y-m-d H:i:s') : '',
                    'schedule_id' => $penalty->schedule_id,
                    'downloaded_reading_id' => $penalty->downloaded_reading_id,
                    'consumer_zone_id' => $penalty->consumer_zone_id,
                ];
            })
            ->toArray();

        // Query for bills with passed due dates that don't have penalty entries yet
        $schedulesQuery = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->select(
                'mrs.id as schedule_id',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_bill',
                'mrs.prepared_by',
                'mrs.created_at',
                'dr.id as downloaded_id',
                'dr.current_bill as downloaded_current_bill',
                'dr.status',
                'dr.paid_at'
            )
            ->where('mrs.consumer_zone_id', $consumerZoneId)
            ->whereNotNull('mrs.due_date')
            ->whereNotNull('mrs.bill_date')
            ->where('mrs.due_date', '<=', $today); // Due date has passed

        // Apply year filter if provided
        if ($year && $year != '') {
            $schedulesQuery->where(function($query) use ($year) {
                $query->whereYear('mrs.bill_date', $year)
                      ->orWhereYear('mrs.due_date', $year);
            });
        }

        $schedules = $schedulesQuery->orderBy('mrs.due_date', 'desc')
                                   ->get();

        \Log::info('Penalty creation - schedules found', [
            'account_no' => $accountNo,
            'consumer_zone_id' => $consumerZoneId,
            'schedules_count' => $schedules->count(),
            'today' => $today->format('Y-m-d')
        ]);

        foreach ($schedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date);
            $currentBill = $schedule->downloaded_current_bill ?? $schedule->current_bill ?? 0;

            // Skip if no current bill or invalid due date
            if ($currentBill <= 0 || !$dueDate) {
                \Log::info('Penalty skipped - no bill or invalid date', [
                    'schedule_id' => $schedule->schedule_id,
                    'current_bill' => $currentBill,
                    'due_date' => $dueDate ? $dueDate->format('Y-m-d') : 'null'
                ]);
                continue;
            }
            
            // Check if due date has passed (today must be after the due date)
            // Penalty is created when the due date is reached or has passed
            // Check if today is greater than or equal to due date
            if ($today->lessThan($dueDate)) {
                \Log::info('Penalty skipped - too early', [
                    'schedule_id' => $schedule->schedule_id,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'today' => $today->format('Y-m-d')
                ]);
                continue; // Too early, due date hasn't been reached yet
            }
            
            // Penalty date for display is one day after due date (matching past records)
            $penaltyDate = $dueDate->copy()->addDay();
            
            \Log::info('Processing penalty for schedule', [
                'schedule_id' => $schedule->schedule_id,
                'due_date' => $dueDate->format('Y-m-d'),
                'penalty_date' => $penaltyDate->format('Y-m-d'),
                'today' => $today->format('Y-m-d'),
                'current_bill' => $currentBill
            ]);

            // Check if penalty entry already exists in penalties table
            $existingPenalty = Penalty::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->first();

            // Skip if penalty entry already exists in penalties table
            if ($existingPenalty) {
                \Log::info('Penalty skipped - already exists in penalties table', [
                    'schedule_id' => $schedule->schedule_id,
                    'due_date' => $dueDate->format('Y-m-d')
                ]);
                continue;
            }
            
            // Also check if penalty entry already exists in consumer_ledgers table
            // This prevents duplicates when penalties are created by generatePenalties() method
            $existingLedgerPenalty = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('trans', 'PENALTY')
                ->where('due_date', $dueDate->format('Y-m-d'))
                ->first();
            
            // Skip if penalty entry already exists in consumer_ledgers
            if ($existingLedgerPenalty) {
                \Log::info('Penalty skipped - already exists in consumer_ledgers table', [
                    'schedule_id' => $schedule->schedule_id,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'ledger_id' => $existingLedgerPenalty->id
                ]);
                continue;
            }

            // Get the BILL entry for this schedule to get the Bill Amount
            // Penalty is 10% of the Bill Amount from the BILLING entry (matching past records)
            // First check in database, then check in schedules table
            $billEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->where('trans', 'BILL')
                ->first();
            
            // If no BILL entry in database, also check if there's a BILL entry from schedules
            // (might not be saved to database yet but exists in the merged entries)
            if (!$billEntry) {
                // Try to get bill amount from schedule directly
                $billAmount = $currentBill;
            } else {
                // Use billamount from the BILL entry in database
                $billAmount = (float)($billEntry->billamount ?? 0);
            }
            
            // Ensure we have a valid bill amount
            if ($billAmount <= 0) {
                \Log::info('Penalty skipped - invalid bill amount', [
                    'schedule_id' => $schedule->schedule_id,
                    'bill_amount' => $billAmount,
                    'has_bill_entry' => $billEntry ? 'yes' : 'no'
                ]);
                continue;
            }
            
            // Get the latest balance before this penalty (from entries on or before the due date)
            // This ensures we get the balance after the BILL entry but before the penalty
            $previousBalanceEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where(function($query) use ($dueDate) {
                    $query->where('date', '<=', $dueDate->format('Y-m-d'))
                          ->orWhere('due_date', '<=', $dueDate->format('Y-m-d'));
                })
                ->where('trans', '!=', 'PENALTY') // Exclude existing penalties
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $previousBalanceEntry ? (float)($previousBalanceEntry->balance ?? 0) : 0;
            
            // If no previous balance found, try to get from consumer
            if ($previousBalance == 0) {
                $consumer = ConsumerZoneOne::find($consumerZoneId);
                $previousBalance = $consumer ? (float)($consumer->balance ?? 0) : 0;
            }

            // CRITICAL: Only create penalty if consumer has an outstanding balance (arrears)
            // If balance is 0 or negative (fully paid), do NOT create penalty
            if ($previousBalance <= 0) {
                \Log::info('Penalty skipped - consumer has no outstanding balance', [
                    'account_no' => $accountNo,
                    'schedule_id' => $schedule->schedule_id,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'previous_balance' => $previousBalance,
                    'note' => 'Consumer has no arrears/balance, penalty not applicable'
                ]);
                continue; // Skip penalty creation if consumer has no balance
            }
            
            // Calculate penalty: 10% of Bill Amount (matching past records)
            // Penalty is only created when due date has passed AND consumer has outstanding balance
            $penalty = round($billAmount * 0.10, 2);
            
            if ($penalty > 0) {
                // Penalty date is ONE DAY AFTER the due date (matching past records)
                // Example: Due date 04/16/2025, penalty on 04/17/2025
                $penaltyDate = $dueDate->copy()->addDay();
                
                $newBalance = $previousBalance + $penalty;

                // Generate reference in format: MM-YYYY (matching past records like "04-2025", "11-2025")
                $reference = $dueDate->format('m-Y');

                // Create penalty entry in penalties table
                try {
                    $penaltyRecord = Penalty::create([
                        'consumer_zone_id' => $consumerZoneId,
                        'schedule_id' => $schedule->schedule_id,
                        'downloaded_reading_id' => $schedule->downloaded_id,
                        'date' => $penaltyDate->format('Y-m-d'), // One day after due date
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $reference, // Format: MM-YYYY (e.g., "04-2025")
                        'bill_amount' => $billAmount,
                        'penalty_amount' => $penalty,
                        'balance' => $newBalance,
                        'username' => $this->extractFirstName($schedule->prepared_by ?? 'System'),
                        'txtime' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    // Add to penalty entries array for display
                    $penaltyEntries[] = [
                        'id' => $penaltyRecord->id,
                        'trans' => 'PENALTY',
                        'date' => $penaltyDate->format('Y-m-d'), // One day after due date
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $reference, // Format: MM-YYYY (e.g., "04-2025")
                        'reading' => '',
                        'volume' => '',
                        'billamount' => 0,
                        'penalty' => $penalty,
                        'others' => 0,
                        'debit' => $penalty,
                        'credit' => 0,
                        'balance' => $newBalance,
                        'username' => $this->extractFirstName($schedule->prepared_by ?? 'System'),
                        'txtime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'schedule_id' => $schedule->schedule_id,
                        'downloaded_reading_id' => $schedule->downloaded_id,
                        'consumer_zone_id' => $consumerZoneId,
                    ];

                    \Log::info('Penalty entry created in penalties table', [
                        'account_no' => $accountNo,
                        'schedule_id' => $schedule->schedule_id,
                        'due_date' => $dueDate->format('Y-m-d'),
                        'penalty' => $penalty,
                        'balance' => $newBalance
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error creating penalty entry in penalties table', [
                        'account_no' => $accountNo,
                        'schedule_id' => $schedule->schedule_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Merge existing penalties with newly created ones
        $allPenaltyEntries = array_merge($existingPenalties, $penaltyEntries);

        \Log::info('Penalty entries processed', [
            'account_no' => $accountNo,
            'existing_penalties_count' => count($existingPenalties),
            'new_penalties_count' => count($penaltyEntries),
            'total_penalty_entries_count' => count($allPenaltyEntries),
            'note' => 'Penalties from consumer_ledgers are handled by getLedger() to avoid duplication'
        ]);

        return $allPenaltyEntries;
    }

    /**
     * Get ledger entries from downloaded_readings and meter_reading_schedules tables
     * @deprecated Use getBillEntriesFromSchedules and getPaymentEntriesFromDownloadedReadings instead
     */
    private function getLedgerFromDownloadedReadings($accountNo, $year = null)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $ledgerEntries = [];

        // Query downloaded_readings joined with meter_reading_schedules and consumer_payments
        // Use LEFT JOIN to include all downloaded_readings even without schedules
        $readingsQuery = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->on('cz.id', '=', 'dr.consumer_zone_id')
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->select(
                'dr.id as downloaded_id',
                'dr.schedule_id',
                'cz.account_no as account_number',
                'dr.consumption',
                'dr.current_reading',
                'dr.current_bill as downloaded_current_bill',
                'dr.reading_date',
                'dr.status',
                'cp.payment_method',
                'cp.payment_amount',
                'cp.amount_tendered',
                'cp.change_amount',
                'cp.payment_reference',
                'cp.or_number as official_receipt_number',
                'cp.paid_at',
                'dr.created_at',
                'cp.created_by as payment_prepared_by',
                'mrs.id as schedule_id_from_mrs',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_bill as schedule_current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'mrs.previous_reading',
                'mrs.prepared_by'
            )
            ->where(function ($query) use ($accountNo, $normalizedAccount, $consumerZoneId) {
                if ($consumerZoneId) {
                    $query->where('dr.consumer_zone_id', $consumerZoneId)
                        ->orWhere('mrs.consumer_zone_id', $consumerZoneId);
                } else {
                    $query->where('cz.account_no', $accountNo)
                        ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                        ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNo))]);
                }
            });

        // Apply year filter if provided
        if ($year && $year != '') {
            $readingsQuery->where(function($query) use ($year) {
                $query->whereYear('dr.reading_date', $year)
                      ->orWhereYear('cp.paid_at', $year)
                      ->orWhereYear('dr.created_at', $year)
                      ->orWhereYear('mrs.bill_date', $year)
                      ->orWhereYear('mrs.bill_month', $year);
            });
        }

        $readings = $readingsQuery->orderBy(DB::raw('COALESCE(cp.paid_at, dr.reading_date, dr.created_at)'), 'desc')
                                  ->orderBy('dr.created_at', 'desc')
                                  ->get();

        \Log::info('Ledger query results', [
            'account_no' => $accountNo,
            'total_readings' => $readings->count(),
            'paid_count' => $readings->filter(function($r) {
                $status = strtolower(trim($r->status ?? ''));
                return ($status === 'paid') || ($r->paid_at !== null) || (($r->payment_amount ?? 0) > 0);
            })->count(),
            'readings_detail' => $readings->map(function($r) {
                return [
                    'id' => $r->downloaded_id,
                    'status' => $r->status,
                    'paid_at' => $r->paid_at,
                    'payment_amount' => $r->payment_amount,
                    'bill_date' => $r->bill_date,
                    'reading_date' => $r->reading_date
                ];
            })->toArray()
        ]);

        foreach ($readings as $reading) {
            // Check payment status first (case-insensitive)
            $status = strtolower(trim($reading->status ?? ''));
            $isPaid = ($status === 'paid') || 
                     ($reading->paid_at !== null) || 
                     (($reading->payment_amount ?? 0) > 0);
            
            $billDate = $reading->bill_date ? Carbon::parse($reading->bill_date) : ($reading->reading_date ? Carbon::parse($reading->reading_date) : null);
            $dueDate = $reading->due_date ? Carbon::parse($reading->due_date) : null;
            
            // Use downloaded_readings.current_bill if available, otherwise schedule's current_bill
            $currentBill = $reading->downloaded_current_bill ?? $reading->schedule_current_bill ?? 0;
            
            // Penalty is NOT shown in BILL entry - it will be created as a separate PENALTY row
            // Separate PENALTY entries are created by createPenaltyEntries() method
            // This matches past records where penalty appears as a separate row, not in the BILL entry
            $penalty = 0; // Always 0 in BILL entry - penalty is a separate row

            // Create BILL entry from reading/schedule if bill_date exists
            // Create BILL entry regardless of payment status (for historical record)
            if ($billDate) {
                // Others column is always 20.00 (Water Maintenance Charge/Meter Rental)
                $others = 20.00;
                
                // Get schedule_id - prioritize from dr.schedule_id, then from mrs.id
                $scheduleId = $reading->schedule_id ?? $reading->schedule_id_from_mrs ?? null;
                
                $ledgerEntries[] = [
                    'id' => 'dr_' . $reading->downloaded_id, // Prefix to avoid conflicts
                    'trans' => 'BILL',
                    'date' => $billDate->format('Y-m-d'),
                    'due_date' => $dueDate ? $dueDate->format('Y-m-d') : '',
                    'reference' => $reading->official_receipt_number ?? '',
                    'reading' => $reading->current_reading ?? $reading->previous_reading ?? '',
                    'volume' => $reading->consumption ?? '',
                    'billamount' => round((float)$currentBill, 2),
                    'penalty' => 0, // Always 0 - penalty is a separate PENALTY row, not shown in BILL entry
                    'others' => round((float)$others, 2), // Always 20.00
                    'debit' => round((float)$currentBill + (float)$others, 2), // Debit = current bill + others only (arrears handled by running balance)
                    'credit' => 0,
                    'balance' => round((float)($reading->total_amount ?? 0), 2),
                    'username' => $this->extractFirstName($reading->prepared_by ?? ''), // Extract first name from prepared_by field
                    'txtime' => $reading->created_at ? Carbon::parse($reading->created_at)->format('Y-m-d H:i:s') : '',
                    'schedule_id' => $scheduleId, // Link to meter_reading_schedules
                    'downloaded_reading_id' => $reading->downloaded_id, // Link to downloaded_readings
                ];
            }

            // Create PAYMENT entry if payment exists
            // Always create payment entry if status is paid or paid_at exists
            if ($isPaid) {
                // Use paid_at if available, otherwise use created_at or reading_date
                $paidDate = null;
                if ($reading->paid_at) {
                    try {
                        $paidDate = Carbon::parse($reading->paid_at);
                    } catch (\Exception $e) {
                        \Log::warning('Error parsing paid_at', ['paid_at' => $reading->paid_at, 'error' => $e->getMessage()]);
                    }
                }
                
                if (!$paidDate && $reading->created_at) {
                    try {
                        $paidDate = Carbon::parse($reading->created_at);
                    } catch (\Exception $e) {
                        \Log::warning('Error parsing created_at', ['created_at' => $reading->created_at, 'error' => $e->getMessage()]);
                    }
                }
                
                if (!$paidDate && $reading->reading_date) {
                    try {
                        $paidDate = Carbon::parse($reading->reading_date);
                    } catch (\Exception $e) {
                        \Log::warning('Error parsing reading_date', ['reading_date' => $reading->reading_date, 'error' => $e->getMessage()]);
                    }
                }
                
                if (!$paidDate) {
                    $paidDate = Carbon::now();
                }
                
                // Get payment amount - prioritize payment_amount
                $paymentAmount = (float)($reading->payment_amount ?? 0);
                
                // If payment_amount is 0 but status is paid, try to get from other fields
                if ($paymentAmount == 0) {
                    // Try total_amount first
                    $paymentAmount = (float)($reading->total_amount ?? 0);
                    
                    // If still 0, try current bill
                    if ($paymentAmount == 0) {
                        $paymentAmount = (float)$currentBill;
                    }
                }
                
                // Create payment entry regardless of amount (even if 0, for record keeping)
                // But log if amount is 0
                if ($paymentAmount == 0) {
                    \Log::warning('Payment entry with zero amount', [
                        'account_no' => $accountNo,
                        'downloaded_id' => $reading->downloaded_id,
                        'status' => $reading->status,
                        'paid_at' => $reading->paid_at,
                        'payment_amount' => $reading->payment_amount,
                        'total_amount' => $reading->total_amount,
                        'current_bill' => $currentBill
                    ]);
                }
                
                // Get schedule_id - prioritize from dr.schedule_id, then from mrs.id
                $scheduleId = $reading->schedule_id ?? $reading->schedule_id_from_mrs ?? null;
                
                $paymentEntry = [
                    'id' => 'pay_' . $reading->downloaded_id, // Prefix to avoid conflicts
                    'trans' => 'PAYMENT',
                    'date' => $paidDate->format('Y-m-d'),
                    'due_date' => '',
                    'reference' => $reading->official_receipt_number ?? $reading->payment_reference ?? '',
                    'reading' => '',
                    'volume' => '',
                    'billamount' => 0,
                    'penalty' => 0,
                    'others' => 0,
                    'debit' => 0,
                    'credit' => round($paymentAmount, 2),
                    'balance' => 0, // Balance will be calculated in frontend
                    'username' => $this->extractFirstName($reading->payment_prepared_by ?? ''), // Extract first name from prepared_by field for payments
                    'txtime' => $paidDate->format('Y-m-d H:i:s'),
                    'schedule_id' => $scheduleId, // Link to meter_reading_schedules
                    'downloaded_reading_id' => $reading->downloaded_id, // Link to downloaded_readings
                ];
                
                $ledgerEntries[] = $paymentEntry;
                
                \Log::info('Payment entry created for ledger', [
                    'account_no' => $accountNo,
                    'downloaded_id' => $reading->downloaded_id,
                    'payment_amount' => $paymentAmount,
                    'paid_at' => $reading->paid_at,
                    'status' => $reading->status,
                    'is_paid_check' => $isPaid,
                    'entry' => $paymentEntry
                ]);
            }
        }

        \Log::info('Ledger entries created', [
            'account_no' => $accountNo,
            'total_entries' => count($ledgerEntries),
            'bill_entries' => count(array_filter($ledgerEntries, function($e) { return $e['trans'] === 'BILL'; })),
            'payment_entries' => count(array_filter($ledgerEntries, function($e) { return $e['trans'] === 'PAYMENT'; })),
            'payment_details' => array_map(function($e) {
                return ['date' => $e['date'], 'credit' => $e['credit'], 'reference' => $e['reference']];
            }, array_filter($ledgerEntries, function($e) { return $e['trans'] === 'PAYMENT'; }))
        ]);

        return $ledgerEntries;
    }

    /**
     * Extract first name from formatted name string
     * Format: "LAST_NAME, FIRST_NAME M. EXTENSION"
     * Returns: "FIRST_NAME"
     */
    private function extractFirstName($formattedName)
    {
        if (empty($formattedName)) {
            return '';
        }

        // Split by comma to separate last name from first name
        $parts = explode(',', $formattedName);
        
        if (count($parts) < 2) {
            // If no comma, try to extract first word (might be just first name)
            $words = explode(' ', trim($formattedName));
            return !empty($words[0]) ? trim($words[0]) : '';
        }

        // Get the part after comma (first name + middle initial + extension)
        $namePart = trim($parts[1]);
        
        // Split by space to get individual parts
        $nameWords = explode(' ', $namePart);
        
        // First word is the first name
        return !empty($nameWords[0]) ? trim($nameWords[0]) : '';
    }

    /**
     * Get formatted username from authenticated user
     * Fallback to SYSTEM when unauthenticated
     */
    private function getFormattedUserName()
    {
        $user = Auth::user();

        if (!$user) {
            return 'SYSTEM';
        }

        $formattedName = strtoupper($user->last_name ?? '') . ', ' . strtoupper($user->first_name ?? '');

        if (!empty($user->middle_name)) {
            $formattedName .= ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.';
        }

        if (!empty($user->extension)) {
            $formattedName .= ' ' . strtoupper($user->extension);
        }

        return trim($formattedName) ?: ($user->name ?? 'UNKNOWN');
    }

    /**
     * Calculate current balance from existing ledger entries (without generating new entries)
     * This matches the running-balance logic for outstanding payments.
     */
    private function calculateCurrentBalanceFromLedger(ConsumerZoneOne $consumer): float
    {
        // Recompute running balance safely:
        // 1) Prefer debit/credit math when present.
        // 2) If debit/credit both zero but a stored balance exists, treat it as a reset.
        $running = 0.0;

        $ledgers = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($ledgers as $ledger) {
            $debit = (float)($ledger->debit ?? 0);
            $credit = (float)($ledger->credit ?? 0);
            $hasMovement = ($debit != 0) || ($credit != 0);

            if ($hasMovement) {
                $running = $running + $debit - $credit;
            } elseif ($ledger->balance !== null) {
                // If no movement but a stored balance exists, treat as authoritative reset
                $running = (float)$ledger->balance;
            }
        }

        return round($running, 2);
    }

    /**
     * Get current balance from ledger entries only (excludes unpaid bills from schedules)
     * Use this for meter reading preparation to get actual arrears
     */
    public function getLedgerBalanceOnly($accountNo): float
    {
        $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
        if (!$consumer) {
            return 0.00;
        }

        // Get ledger entries directly from database - NO calculations, NO unpaid bills
        $ledgers = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->orderByRaw('CAST(date AS DATE) ASC')
            ->orderBy('id', 'asc')
            ->get();

        if ($ledgers->isEmpty()) {
            return (float)($consumer->balance ?? 0);
        }

        // Calculate running balance chronologically from ledger entries only
        $runningBalance = 0.00;

        // Recalculate balances for all entries chronologically
        foreach ($ledgers as $ledger) {
            $debit = (float)($ledger->debit ?? 0);
            $credit = (float)($ledger->credit ?? 0);
            $trans = strtoupper(trim($ledger->trans ?? ''));
            
            // Calculate new balance: previous balance + debit - credit
            $newBalance = $runningBalance + $debit - $credit;
            
            // Special handling for PAYMENT entries
            if ($trans === 'PAYMENT' && $credit > 0) {
                // If payment exactly matches the previous balance (within 0.01 rounding tolerance)
                if (abs($credit - $runningBalance) < 0.01) {
                    $newBalance = 0.00;
                }
                // If payment covers or exceeds a positive balance
                elseif ($runningBalance > 0 && $credit > $runningBalance) {
                    $overpayment = $credit - $runningBalance;
                    // If overpayment is small (<= 20.00), treat as fully paid (balance = 0.00)
                    if ($overpayment <= 20.00) {
                        $newBalance = 0.00;
                    } else {
                        $newBalance = -$overpayment;
                    }
                }
                // If balance is already negative (overpayment) and another payment comes in
                elseif ($runningBalance < 0 && $credit > 0) {
                    $newOverpayment = abs($newBalance);
                    if ($newOverpayment <= 20.00) {
                        $newBalance = 0.00;
                    }
                }
            }
            
            // Round to 2 decimal places
            $newBalance = round($newBalance, 2);
            
            // Update running balance for next iteration
            $runningBalance = $newBalance;
        }

        return round($runningBalance, 2);
    }

    /**
     * Get current balance for a consumer (same calculation as getLedger but optimized for batch processing)
     * This replicates the exact balance calculation from getLedger() method
     * NOTE: This includes unpaid bills from schedules - use getLedgerBalanceOnly() for arrears calculation
     */
    public function getCurrentBalance($accountNo): float
    {
        $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
        if (!$consumer) {
            return 0.00;
        }

        // Get existing ledger entries
        $existingLedgers = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Get bill entries from schedules
        $billEntries = $this->getBillEntriesFromSchedules($consumer->account_no, $consumer->id, '');

        // Get payment entries
        $paymentEntries = $this->getPaymentEntriesFromDownloadedReadings($consumer->account_no, $consumer->id, '');

        // Get collection entries
        $collectionEntries = $this->getCollectionEntries($consumer->account_no, $consumer->id, '');

        // Get penalty entries.
        // IMPORTANT:
        // Previously this called createPenaltyEntries() which would AUTO-GENERATE
        // penalties for bills with passed due dates whenever the ledger was viewed.
        // That auto-generation is now DISABLED to avoid conflicts with the new
        // surcharge/penalty flow in BillingProcessController.
        //
        // From now on we ONLY read existing entries from the penalties table,
        // using createPenaltyEntries() strictly as a formatter (no side effects).
        $penaltyEntries = $this->createPenaltyEntries($consumer->account_no, $consumer->id, '');

        // Merge all entries
        $allLedgers = collect($existingLedgers)
            ->merge($billEntries)
            ->merge($paymentEntries)
            ->merge($collectionEntries)
            ->merge($penaltyEntries);

        // Sort chronologically
        $allLedgers = $allLedgers->sortBy(function($ledger) {
            $isObject = is_object($ledger);
            $date = $isObject ? ($ledger->date ?? '') : ($ledger['date'] ?? '');
            $trans = $isObject ? ($ledger->trans ?? '') : ($ledger['trans'] ?? '');
            
            try {
                $timestamp = strtotime($date);
                if ($timestamp === false) {
                    return PHP_INT_MAX;
                }
                
                $transOffset = 0;
                if (stripos($trans, 'BILL') !== false || stripos($trans, 'BILLING') !== false) {
                    $transOffset = 1;
                } elseif (stripos($trans, 'PAYMENT') !== false) {
                    $transOffset = 2;
                } elseif (stripos($trans, 'PENALTY') !== false) {
                    $transOffset = 3;
                }
                
                return $timestamp + $transOffset;
            } catch (\Exception $e) {
                return PHP_INT_MAX;
            }
        })->values();

        // Calculate running balance
        $earliestOldLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->whereNotNull('balance')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $previousBalance = 0.00;
        if ($earliestOldLedger) {
            $firstEntryBalance = (float)($earliestOldLedger->balance ?? 0);
            $firstEntryDebit = (float)($earliestOldLedger->debit ?? 0);
            $firstEntryCredit = (float)($earliestOldLedger->credit ?? 0);
            $previousBalance = $firstEntryBalance - $firstEntryDebit + $firstEntryCredit;
        } else {
            $previousBalance = (float)($consumer->balance ?? 0);
        }

        $allLedgers = $allLedgers->map(function($ledger) use (&$previousBalance) {
            $isObject = is_object($ledger);
            $hasId = $isObject ? isset($ledger->id) : isset($ledger['id']);
            $ledgerId = $isObject ? ($ledger->id ?? null) : ($ledger['id'] ?? null);

            if ($hasId && $ledgerId && is_numeric($ledgerId)) {
                $balance = $isObject ? (float)($ledger->balance ?? 0) : (float)($ledger['balance'] ?? 0);
                $previousBalance = $balance;
                return $ledger;
            }

            $debit = $isObject ? (float)($ledger->debit ?? 0) : (float)($ledger['debit'] ?? 0);
            $credit = $isObject ? (float)($ledger->credit ?? 0) : (float)($ledger['credit'] ?? 0);
            $newBalance = $previousBalance + $debit - $credit;

            $trans = $isObject ? ($ledger->trans ?? '') : ($ledger['trans'] ?? '');
            if (strtoupper(trim($trans)) === 'PAYMENT' && $credit > 0 && $previousBalance > 0) {
                if ($credit >= $previousBalance) {
                    $overpayment = $credit - $previousBalance;
                    if ($overpayment <= 20.00) {
                        $newBalance = 0.00;
                    } else {
                        $newBalance = -$overpayment;
                    }
                }
            }

            $previousBalance = $newBalance;

            if ($isObject) {
                $ledger->balance = round($newBalance, 2);
            } else {
                $ledger['balance'] = round($newBalance, 2);
            }

            return $ledger;
        });

        // Get balance from last entry
        $lastEntry = $allLedgers->last();
        $currentBalance = 0.00;
        if ($lastEntry) {
            $currentBalance = is_object($lastEntry) 
                ? (float)($lastEntry->balance ?? 0) 
                : (float)($lastEntry['balance'] ?? 0);
        } else {
            $latestLedgerEntry = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereNotNull('balance')
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $currentBalance = $latestLedgerEntry ? (float)($latestLedgerEntry->balance ?? 0) : $previousBalance;
        }

        // Check if current bill is already in ledger
        $currentBillInLedger = $allLedgers->contains(function($ledger) {
            $trans = is_object($ledger) ? ($ledger->trans ?? '') : ($ledger['trans'] ?? '');
            $billamount = is_object($ledger) ? ($ledger->billamount ?? 0) : ($ledger['billamount'] ?? 0);
            return strtoupper(trim($trans)) === 'BILLING' && $billamount > 0;
        });

        // Get latest unpaid bill
        $normalizedAccount = str_replace('-', '', $consumer->account_no);
        $latestBillFromDownloaded = DB::table('downloaded_readings as dr')
            ->where('dr.consumer_zone_id', $consumer->id)
            ->whereNotNull('dr.current_bill')
            ->orderBy('dr.reading_date', 'desc')
            ->orderBy('dr.created_at', 'desc')
            ->first();

        $latestBillAmount = 0;
        $latestBillPaid = false;

        if ($latestBillFromDownloaded) {
            $latestBillAmount = (float)($latestBillFromDownloaded->current_bill ?? 0);
            $latestBillPaid = DB::table('consumer_payments as cp')
                ->where('cp.reading_id', $latestBillFromDownloaded->id ?? null)
                ->whereNotNull('cp.payment_amount')
                ->where('cp.payment_amount', '>', 0)
                ->exists();

            if (!$latestBillPaid) {
                $drStatus = DB::table('downloaded_readings')
                    ->where('id', $latestBillFromDownloaded->id ?? null)
                    ->value('status');
                $latestBillPaid = (strtolower(trim($drStatus ?? '')) === 'paid');
            }
        }

        // Add current bill if unpaid and not already in ledger
        if (!$latestBillPaid && $latestBillAmount > 0 && !$currentBillInLedger) {
            $maintenanceCharge = 20.00;
            $currentBillTotal = $latestBillAmount + $maintenanceCharge;

            if ($currentBalance < 0) {
                if (abs($currentBalance) <= $currentBillTotal) {
                    $currentBalance = 0.00;
                } else {
                    $currentBalance = round($currentBalance + $currentBillTotal, 2);
                }
            } else {
                $currentBalance = round($currentBalance + $currentBillTotal, 2);
            }
        } elseif ($currentBalance < 0 && abs($currentBalance) <= 20.00) {
            $currentBalance = 0.00;
        }

        return round($currentBalance, 2);
    }

    /**
     * Get current balance preferring latest stored balance; fallback to recomputed running balance
     */
    private function getAuthoritativeBalance(ConsumerZoneOne $consumer): float
    {
        $latest = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->whereNotNull('balance')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            return round((float)($latest->balance ?? 0), 2);
        }

        return $this->calculateCurrentBalanceFromLedger($consumer);
    }

    public function import(Request $request)
    {
        // Increase execution time limit for large file imports
        set_time_limit(0); // 0 = unlimited, or use a specific value like 300 for 5 minutes
        ini_set('max_execution_time', 0);
        
        \Illuminate\Support\Facades\Log::info('Ledger import request received', [
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'no file',
            'method' => $request->method(),
            'all' => $request->all()
        ]);
        
        $uploadedFile = $request->file('file');
        if ($uploadedFile) {
            \Illuminate\Support\Facades\Log::info('Uploaded file details', [
                'original_name' => $uploadedFile->getClientOriginalName(),
                'extension' => $uploadedFile->getClientOriginalExtension(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'guess_extension' => $uploadedFile->guessExtension(),
                'guess_client_extension' => $uploadedFile->guessClientExtension(),
            ]);
        }

        try {
            // Custom validation for Excel files (especially old .xls format)
            $uploadedFile = $request->file('file');
            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $mimeType = $uploadedFile->getMimeType();
            $allowedExtensions = ['xls', 'xlsx', 'xltx', 'xlsm', 'csv', 'txt'];
            $allowedMimeTypes = [
                'application/vnd.ms-excel',  // Excel 97-2003 (.xls)
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',  // Excel 2007+ (.xlsx)
                'application/vnd.openxmlformats-officedocument.spreadsheetml.template',  // Excel template (.xltx)
                'application/vnd.ms-excel.sheet.macroEnabled.12',  // Excel macro-enabled (.xlsm)
                'application/vnd.ms-office',
                'application/octet-stream',  // Sometimes old Excel files show this
                'text/plain',
                'text/csv',
                'application/csv'
            ];
            
            // Validate by extension OR MIME type (not both - more flexible)
            if (!in_array($extension, $allowedExtensions) && !in_array($mimeType, $allowedMimeTypes)) {
                \Illuminate\Support\Facades\Log::error('File validation failed - not in allowed lists', [
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'original_name' => $uploadedFile->getClientOriginalName()
                ]);
                
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['Please upload a valid Excel file (.xls, .xlsx, .xltx) or CSV file. Your file extension is: ' . $extension . ', MIME type is: ' . $mimeType]
                ]);
            }
            
            // If we get here, validation passed
            $validated = ['file' => $uploadedFile];
            \Illuminate\Support\Facades\Log::info('File validation passed', [
                'extension' => $extension,
                'mime_type' => $mimeType,
                'original_name' => $uploadedFile->getClientOriginalName()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Validation failed', [
                'errors' => $e->errors(),
                'message' => $e->getMessage()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return back()->withErrors($e->errors())->with('error', 'Validation failed: ' . implode(', ', $e->errors()['file'] ?? []));
        }

        try {
            \Illuminate\Support\Facades\Log::info('Starting ledger import');
            
            $uploadedFile = $request->file('file');
            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            
            // Get account_no from request (if provided from the button click)
            $accountNo = $request->input('account_no');
            
            // Check if file has data before importing
            // Pass account_no to import class if provided (for files without account_no column)
            $import = new ConsumerLedgerImport($accountNo);
            
            // Count existing records before import
            $beforeCount = ConsumerLedger::count();
            
            // Ensure the file is actually readable
            $filePath = $uploadedFile->getRealPath();
            if (!$filePath || !is_readable($filePath)) {
                throw new \Exception('The uploaded file cannot be read. Please ensure the file is not corrupted.');
            }
            
            \Illuminate\Support\Facades\Log::info('File path before import', [
                'file_path' => $filePath,
                'exists' => file_exists($filePath),
                'readable' => is_readable($filePath),
                'size' => filesize($filePath)
            ]);
            
            // Import the file - Laravel Excel should auto-detect based on extension
            // For .xls files, PhpSpreadsheet uses the Xls reader automatically
            try {
                Excel::import($import, $uploadedFile);
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                // If it's an OLE file error, try to provide more helpful message
                if (strpos($e->getMessage(), 'OLE') !== false || strpos($e->getMessage(), 'not recognised') !== false) {
                    \Illuminate\Support\Facades\Log::error('OLE file read error', [
                        'message' => $e->getMessage(),
                        'file_path' => $filePath,
                        'extension' => $extension
                    ]);
                    throw new \Exception('The Excel file appears to be corrupted or is not in a valid Excel format. Please try re-saving the file in Excel and upload again.');
                }
                throw $e;
            }
            
            // Count records after import
            $afterCount = ConsumerLedger::count();
            $importedCount = $afterCount - $beforeCount;
            
            // Get import statistics
            $imported = $import->importedCount ?? $importedCount;
            $skipped = $import->skippedCount ?? 0;
            $errors = $import->errors ?? [];
            
            \Illuminate\Support\Facades\Log::info('Import completed', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'beforeCount' => $beforeCount,
                'afterCount' => $afterCount
            ]);
            
            if ($imported > 0) {
                $message = "Ledger imported successfully! {$imported} record(s) imported.";
                if ($skipped > 0) {
                    $message .= " {$skipped} row(s) skipped.";
                }
                
                \Illuminate\Support\Facades\Log::info('Returning success response', ['message' => $message]);
                
                // Set flash messages for both AJAX and regular requests
                session()->flash('success', $message);
                session()->flash('import_success', true);
                
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'import_success' => true,
                        'imported' => $imported,
                        'skipped' => $skipped
                    ]);
                }
                
                return back()->with([
                    'success' => $message,
                    'import_success' => true
                ]);
            } else {
                $message = 'Import failed. No new records were imported. ' . $skipped . ' row(s) were skipped.';
                
                if (!empty($errors)) {
                    // Check for specific account_no error
                    if (isset($errors[0]) && strpos($errors[0], 'No account number') !== false) {
                        $message = '❌ ' . $errors[0] . ' Please try again and select an account number from the dropdown before uploading.';
                    } else {
                        $sampleErrors = array_slice($errors, 0, 3);
                        $message .= ' Issues: ' . implode(' | ', $sampleErrors);
                    }
                    
                    \Illuminate\Support\Facades\Log::warning('Import failed', [
                        'errors' => $errors,
                        'debug_info' => !empty($import->debugFirstRows) ? $import->debugFirstRows[0] : []
                    ]);
                } else {
                    $message .= ' Please verify the account numbers in your file match records in the system.';
                    \Illuminate\Support\Facades\Log::warning('No records imported', ['message' => $message]);
                }
                
                // Set flash message
                session()->flash('warning', $message);
                
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'imported' => $imported,
                        'skipped' => $skipped,
                        'errors' => $errors
                    ], 422);
                }
                
                return back()->with('warning', $message);
            }
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            
            foreach ($failures as $failure) {
                $errorMessages[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            
            return back()->with('error', 'Import failed: ' . implode(' | ', $errorMessages));
        } catch (\Exception $e) {
            // Log the full error for debugging
            \Illuminate\Support\Facades\Log::error('Ledger import error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Import failed: ' . $e->getMessage();
            
            // Check if it's a duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                $errorMessage = 'The ledger has already been uploaded. Duplicate entries are not allowed.';
            }
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', $errorMessage . ' Check logs for more details.');
        }
    }
}
