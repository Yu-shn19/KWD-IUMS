<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZoneOne;
use App\Models\ConsumerLedger;
use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use App\Models\ConsumerPayment;
use App\Models\Penalty;
use App\Models\DisconnectionOrder;
use App\Models\LROLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BillingProcessController extends Controller
{
    /** Billing adjustment constants (source of truth: paid_at only) */
    private const MONTHLY_PRINCIPAL = 195.00;
    private const PENALTY_RATE = 0.10;
    private const PENALTY_PER_MONTH = 19.50;
    private const WMC_PER_MONTH = 20.00;

    /**
     * Display the billing processes page
     */
    public function index()
    {
        return view('processes.billing-processes');
    }

    /**
     * Prepare meter reading data for a specific zone and billing period
     */
    public function prepareMeterReading(Request $request)
    {
        $request->validate([
            'zone' => 'required|string',
            'bill_month' => 'required|date',
            'bill_date' => 'required|date',
            'due_date' => 'required|date',
            'disconnection_date' => 'required|date',
        ]);

        try {
            $zone = $request->zone;
            $billMonth = Carbon::parse($request->bill_month);
            $billDate = Carbon::parse($request->bill_date);
            $dueDate = Carbon::parse($request->due_date);
            $disconnectionDate = Carbon::parse($request->disconnection_date);

            // First, check if there are any consumers in this zone at all
            $totalInZone = ConsumerZoneOne::where('zone_code', $zone)->count();
            
            // Fetch consumers for the selected zone (case-insensitive status check)
            $consumers = ConsumerZoneOne::where('zone_code', $zone)
                ->where(function($query) {
                    $query->where('status_code', 'A')
                          ->orWhere('status_code', 'ACTIVE')
                          ->orWhere('status_code', 'Active')
                          ->orWhere('status_code', 'active');
                })
                ->orderBy('sequence', 'asc')
                ->orderBy('account_no', 'asc')
                ->get();

            if ($consumers->isEmpty()) {
                // Provide more helpful error message
                if ($totalInZone > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Found ' . $totalInZone . ' consumer(s) in Zone ' . $zone . ', but none are marked as Active. Please check consumer status.'
                    ], 404);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No consumers found in Zone ' . $zone . '. Please verify the zone has registered consumers.'
                    ], 404);
                }
            }

            // Check if schedules already exist for this zone and bill month
            $existingSchedules = MeterReadingSchedule::where('zone', $zone)
                ->where('bill_month', $billMonth->format('Y-m-d'))
                ->count();

            // Prepare meter reading data (NOT SAVED YET)
            $meterReadingData = [];
            $sedr = 1;

            // Get balances from the same source as ledger view (getLedger summary.balance)
            // This ensures the Arrears column exactly matches "Current Balance" in ledger.blade.php (footerBalance)
            $ledgerBalanceMap = [];
            $ledgerController = new \App\Http\Controllers\ConsumerLedgerController();
            
            foreach ($consumers as $consumer) {
                try {
                    $ledgerRequest = new Request([
                        'account_no' => $consumer->account_no,
                        'year' => '', // All years - matches ledger when year filter is blank
                    ]);
                    $ledgerResponse = $ledgerController->getLedger($ledgerRequest);
                    $data = $ledgerResponse->getData(true);
                    if (!empty($data['success']) && isset($data['summary']['balance'])) {
                        $ledgerBalance = (float) $data['summary']['balance'];
                    } else {
                        $ledgerBalance = 0.00;
                    }
                    $ledgerBalanceMap[$consumer->id] = $ledgerBalance;
                } catch (\Exception $e) {
                    Log::error('Error getting ledger balance for account ' . $consumer->account_no . ': ' . $e->getMessage());
                    $ledgerBalanceMap[$consumer->id] = 0.00;
                }
            }

            foreach ($consumers as $consumer) {
                // Get previous reading - checks downloaded_readings, meter_reading_schedules, and consumer_ledgers
                $previousReading = $this->getPreviousReading($consumer->account_no);
                
                // Use the actual date from previous reading if available, otherwise calculate from bill_month
                $prevDate = !empty($previousReading['date']) && $previousReading['date'] !== Carbon::now()->subMonth()->format('m/d/Y')
                    ? $previousReading['date']
                    : $billMonth->copy()->subMonth()->format('m/d/Y');
                
                // Get current balance from ledger (same source as ledger view)
                // This is the exact balance shown in ledger.blade.php line 148-149 (summary.balance)
                $ledgerBalance = $ledgerBalanceMap[$consumer->id] ?? 0.00;
                
                // Calculate water maintenance charge: 20.00 if there's a current bill, 0.00 otherwise
                // Since current_bill is 0.00 at preparation stage, water_maintenance_charge will be 0.00
                // It will be calculated properly when the bill is actually generated
                $currentBill = 0.00; // Will be calculated when reading is done
                $waterMaintenanceCharge = ($currentBill > 0) ? 20.00 : 0.00;
                
                // Prepare data for frontend display using consumer_zone fields
                $meterReadingData[] = [
                    'sedr' => $sedr++,
                    'consumer_zone_id' => $consumer->id,
                    'account_number' => $consumer->account_no,
                    'account_name' => $consumer->account_name,
                    'address' => $consumer->address1 ?? '',
                    'zone' => $consumer->zone_code,
                    'category' => $consumer->category_code ?? '',
                    'meter_number' => $consumer->meter_number ?? '',
                    'prev_date' => $prevDate,
                    'prev_read' => $previousReading['reading'] ?? 0,
                    'prev_volume' => $previousReading['volume'] ?? 0, // Latest volume from all sources
                    'pres_read' => 0, // To be filled during actual reading
                    'volume' => $previousReading['volume'] ?? 0, // Show latest volume
                    'current_bill' => $currentBill,
                    'water_maintenance_charge' => $waterMaintenanceCharge, // 20.00 if current_bill > 0, else 0.00
                    'arrears' => $ledgerBalance, // Current balance from ledger (remaining balance)
                    'balance' => $ledgerBalance, // Latest balance from ledger
                    'total' => 0.00,
                    'status' => 'Prepared',
                    'bill_month' => $billMonth->format('Y-m-d'),
                    'bill_date' => $billDate->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'disconnection_date' => $disconnectionDate->format('Y-m-d'),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Meter reading preparation completed! Review the data and click "Save Schedules" to store.',
                'data' => $meterReadingData,
                'summary' => [
                    'zone' => $zone,
                    'bill_month' => $billMonth->format('F Y'),
                    'total_consumers' => count($meterReadingData),
                    'prepared_date' => Carbon::now()->format('F d, Y'),
                    'existing_schedules' => $existingSchedules
                ],
                'can_save' => $existingSchedules === 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error preparing meter reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save prepared meter reading schedules to database
     */
    public function saveMeterReadingSchedules(Request $request)
    {
        $request->validate([
            'schedules' => 'required|array',
            'schedules.*.consumer_zone_id' => 'nullable|exists:consumer_zone,id',
            'schedules.*.account_number' => 'required|string',
            'schedules.*.zone' => 'required|string',
            'schedules.*.bill_month' => 'required|date',
        ]);

        try {
            $zone = $request->schedules[0]['zone'];
            $billMonth = Carbon::parse($request->schedules[0]['bill_month']);

            // Check if schedules already exist
            $existingSchedules = MeterReadingSchedule::where('zone', $zone)
                ->where('bill_month', $billMonth->format('Y-m-d'))
                ->count();

            if ($existingSchedules > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedules already exist for Zone ' . $zone . ' for ' . $billMonth->format('F Y') . '. Please delete existing schedules first.',
                    'existing_count' => $existingSchedules
                ], 422);
            }

            $savedSchedules = [];

            // Get the authenticated user's formatted name
            $preparedBy = $this->getFormattedUserName();

            DB::beginTransaction();
            try {
                foreach ($request->schedules as $scheduleData) {
                    // Parse previous reading date if provided, otherwise calculate from bill_month
                    $prevDateStr = $scheduleData['prev_date'] ?? null;
                    $previousReadingDate = null;
                    
                    if ($prevDateStr) {
                        try {
                            // Try parsing the date (format: m/d/Y)
                            $previousReadingDate = Carbon::createFromFormat('m/d/Y', $prevDateStr);
                        } catch (\Exception $e) {
                            // Fallback to calculating from bill_month
                            $previousReadingDate = Carbon::parse($scheduleData['bill_month'])->subMonth();
                        }
                    } else {
                        $previousReadingDate = Carbon::parse($scheduleData['bill_month'])->subMonth();
                    }
                    
                    // Create meter reading schedule using account_number as the link (no consumer_id column)
                    $schedule = MeterReadingSchedule::create([
                        'zone' => $scheduleData['zone'],
                        'account_number' => $scheduleData['account_number'],
                        'account_name' => $scheduleData['account_name'],
                        'address' => $scheduleData['address'],
                        'category' => $scheduleData['category'],
                        'meter_number' => $scheduleData['meter_number'],
                        'bill_month' => Carbon::parse($scheduleData['bill_month']),
                        'bill_date' => Carbon::parse($scheduleData['bill_date']),
                        'due_date' => Carbon::parse($scheduleData['due_date']),
                        'disconnection_date' => Carbon::parse($scheduleData['disconnection_date']),
                        'previous_reading_date' => $previousReadingDate,
                        'previous_reading' => $scheduleData['prev_read'],
                        'arrears' => $scheduleData['arrears'] ?? 0.00,
                        'status' => 'Prepared',
                        'sedr_number' => $scheduleData['sedr'],
                        'prepared_by' => $preparedBy, // Save username of user who created the preparation
                    ]);

                    $savedSchedules[] = $schedule->id;

                    // Create corresponding ConsumerLedger entry for this bill
                    // Find consumer_zone_id from account_number
                    $consumer = ConsumerZoneOne::where(function ($query) use ($scheduleData) {
                        $accountNumber = $scheduleData['account_number'];
                        $query->where('account_no', $accountNumber)
                              ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [str_replace('-', '', $accountNumber)]);
                    })->first();

                    if ($consumer) {
                        // Get the latest balance directly from consumer_ledgers table (source of truth)
                        // Follow the data in consumer_ledgers table - use the most recent entry by ID
                        // ID represents actual creation order, so highest ID = most recent entry
                        // Exclude any existing entry with the same schedule_id to avoid duplicates
                        $latestLedgerEntry = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                            ->whereNotNull('balance') // Only entries with a balance value
                            ->where(function($query) use ($schedule) {
                                $query->whereNull('schedule_id')
                                      ->orWhere('schedule_id', '!=', $schedule->id); // Exclude this schedule if it already exists
                            })
                            ->orderBy('id', 'desc') // ID is the source of truth for creation order
                            ->first();
                        
                        // Use the balance from the most recent ledger entry
                        // If no ledger entry exists, fall back to consumer's balance field
                        $previousBalance = $latestLedgerEntry ? (float)($latestLedgerEntry->balance ?? 0) : (float)($consumer->balance ?? 0);
                        
                        // Calculate bill amounts
                        // current_bill is 0 at this point (no reading yet), but we can use estimated if provided
                        $currentBill = (float)($scheduleData['current_bill'] ?? 0);
                        // Water Maintenance Charge should NOT be added during preparation
                        // It will only be added to the ledger when the reading is actually completed
                        $others = 0.00; // Water Maintenance Charge - set to 0 during preparation
                        $debit = $currentBill + $others; // Should be 0.00 + 0.00 = 0.00
                        $newBalance = $previousBalance + $debit; // Previous balance + 0.00 = previous balance
                        
                        // Log for debugging to help identify issues
                        Log::info('Balance calculation for BILLING entry during schedule save - following consumer_ledgers table', [
                            'account_number' => $scheduleData['account_number'],
                            'bill_date' => Carbon::parse($scheduleData['bill_date'])->format('Y-m-d'),
                            'latest_ledger_entry_id' => $latestLedgerEntry ? $latestLedgerEntry->id : null,
                            'latest_ledger_entry_date' => $latestLedgerEntry ? $latestLedgerEntry->date : null,
                            'latest_ledger_entry_trans' => $latestLedgerEntry ? $latestLedgerEntry->trans : null,
                            'latest_ledger_entry_balance' => $latestLedgerEntry ? (float)($latestLedgerEntry->balance ?? 0) : null,
                            'previous_balance_used' => $previousBalance,
                            'current_bill' => $currentBill,
                            'others' => $others,
                            'debit' => $debit,
                            'new_balance' => $newBalance
                        ]);
                        
                        // Extract first name from prepared_by
                        $username = $this->extractFirstName($preparedBy);
                        
                        // Create BILLING entry in consumer_ledgers
                        ConsumerLedger::create([
                            'consumer_zone_id' => $consumer->id,
                            'schedule_id' => $schedule->id,
                            'trans' => 'BILLING', // Use 'BILLING' to match historical format
                            'date' => Carbon::parse($scheduleData['bill_date'])->format('Y-m-d'),
                            'due_date' => Carbon::parse($scheduleData['due_date'])->format('Y-m-d'),
                            'reference' => $scheduleData['sedr'] ?? '', // SEDR number as reference
                            'reading' => $scheduleData['prev_read'], // Previous reading (current reading not available yet)
                            'volume' => 0, // Consumption is 0 until reading is taken
                            'billamount' => $currentBill,
                            'penalty' => 0, // Penalty is a separate entry, not included in BILLING
                            'others' => $others,
                            'debit' => $debit,
                            'credit' => 0,
                            'balance' => $newBalance,
                            'username' => $username,
                            'txtime' => now(),
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully saved ' . count($savedSchedules) . ' meter reading schedule(s) to database!',
                    'saved_count' => count($savedSchedules),
                    'schedule_ids' => $savedSchedules
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get previous reading and volume for a consumer by account number
     * Checks downloaded_readings, meter_reading_schedules, and consumer_ledgers in priority order
     * Ensures continuity with previous data
     */
    private function getPreviousReading($accountNo)
    {
        // Find the consumer by account_no
        $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
        
        if (!$consumer) {
            return [
                'date' => Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => 0,
                'volume' => 0,
                'arrears' => 0.00,
                'balance' => 0.00
            ];
        }

        $normalizedAccount = str_replace('-', '', $accountNo);

        // Priority 1: Check downloaded_readings (most recent actual reading)
        $latestDownloadedReading = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->where(function ($query) use ($accountNo, $normalizedAccount) {
                $query->where('dr.account_number', $accountNo)
                      ->orWhereRaw("REPLACE(dr.account_number, '-', '') = ?", [$normalizedAccount]);
            })
            ->whereNotNull('dr.current_reading')
            ->where('dr.current_reading', '>', 0)
            ->select(
                'dr.current_reading as reading',
                'dr.consumption as volume',
                'dr.reading_date as date',
                'dr.current_bill',
                'mrs.arrears',
                'mrs.total_amount'
            )
            ->orderBy('dr.reading_date', 'desc')
            ->orderBy('dr.created_at', 'desc')
            ->first();

        if ($latestDownloadedReading) {
            // Get arrears from schedule (stored arrears value)
            $arrears = (float)($latestDownloadedReading->arrears ?? 0);
            $latestBalance = (float)($latestDownloadedReading->total_amount ?? 0);
            
            // If no arrears stored but balance exists, calculate from balance
            if ($arrears == 0 && $latestBalance > 0) {
                $currentBill = (float)($latestDownloadedReading->current_bill ?? 0);
                $arrears = max(0, $latestBalance - $currentBill);
            }

            $result = [
                'date' => $latestDownloadedReading->date ? Carbon::parse($latestDownloadedReading->date)->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $latestDownloadedReading->reading,
                'volume' => $latestDownloadedReading->volume ?? 0,
                'arrears' => $arrears,
                'balance' => $latestBalance
            ];
            
            Log::info('Previous reading from downloaded_readings', [
                'account_no' => $accountNo,
                'source' => 'downloaded_readings',
                'result' => $result
            ]);
            
            return $result;
        }

        // Priority 2: Check meter_reading_schedules (latest completed reading)
        $latestSchedule = DB::table('meter_reading_schedules')
            ->where(function ($query) use ($accountNo, $normalizedAccount) {
                $query->where('account_number', $accountNo)
                      ->orWhereRaw("REPLACE(account_number, '-', '') = ?", [$normalizedAccount]);
            })
            ->whereNotNull('current_reading')
            ->where('current_reading', '>', 0)
            ->whereIn('status', ['Completed', 'Verified'])
            ->select(
                'current_reading as reading',
                'consumption as volume',
                'reading_date as date',
                'current_bill',
                'arrears',
                'total_amount'
            )
            ->orderBy('reading_date', 'desc')
            ->orderBy('completed_at', 'desc')
            ->first();

        if ($latestSchedule) {
            // Get arrears from schedule (stored arrears value)
            $arrears = (float)($latestSchedule->arrears ?? 0);
            $latestBalance = (float)($latestSchedule->total_amount ?? 0);
            
            // If no arrears stored but balance exists, calculate from balance
            if ($arrears == 0 && $latestBalance > 0) {
                $currentBill = (float)($latestSchedule->current_bill ?? 0);
                $arrears = max(0, $latestBalance - $currentBill);
            }

            $result = [
                'date' => $latestSchedule->date ? Carbon::parse($latestSchedule->date)->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $latestSchedule->reading,
                'volume' => $latestSchedule->volume ?? 0,
                'arrears' => $arrears,
                'balance' => $latestBalance
            ];
            
            Log::info('Previous reading from meter_reading_schedules', [
                'account_no' => $accountNo,
                'source' => 'meter_reading_schedules',
                'result' => $result
            ]);
            
            return $result;
        }

        // Priority 3: Check consumer_ledgers (legacy data)
        $latestLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->whereNotNull('reading')
            ->where('reading', '>', 0)
            ->orderBy('id', 'DESC')
            ->first();

        if ($latestLedger) {
            // Get latest balance from ledger entries
            $latestBalanceEntry = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereNotNull('balance')
                ->orderBy('id', 'DESC')
                ->first();
            
            $latestBalance = $latestBalanceEntry ? (float)($latestBalanceEntry->balance ?? 0) : ($consumer->balance ?? 0.00);
            
            // Calculate arrears (balance minus current bill if available)
            $currentBill = $latestLedger->billamount ?? 0;
            $arrears = max(0, $latestBalance - $currentBill);

            $result = [
                'date' => $latestLedger->date ? Carbon::parse($latestLedger->date)->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $latestLedger->reading,
                'volume' => $latestLedger->volume ?? 0,
                'arrears' => $arrears,
                'balance' => $latestBalance
            ];
            
            Log::info('Previous reading from consumer_ledgers', [
                'account_no' => $accountNo,
                'source' => 'consumer_ledgers',
                'ledger_id' => $latestLedger->id,
                'result' => $result
            ]);
            
            return $result;
        }

        // If no reading found in any source, return defaults
        $defaultBalance = $consumer->balance ?? 0.00;
        return [
            'date' => Carbon::now()->subMonth()->format('m/d/Y'),
            'reading' => 0,
            'volume' => 0,
            'arrears' => $defaultBalance,
            'balance' => $defaultBalance
        ];
    }

    /**
     * Compute billing breakdown (PRE-DUE or POST-DUE) from consumer_ledgers.
     *
     * Source of truth:
     *   - Payment status = paid_at only (NULL = UNPAID, NOT NULL = PAID)
     *   - BILLING rows:
     *       date       = billing_date  (start of billing)
     *       due_date   = end of PRE-DUE window
     *       billamount = Current Bill (principal)
     *       others     = Water Maintenance / Meter Rental
     *   - PENALTY rows:
     *       trans      = 'PENALTY'
     *       date       = penalty_date (starts the day AFTER billing due_date)
     *       amount     = 10% of billamount (₱19.50); no explicit due_date
     *
     * PRE-DUE (Billing Date → Billing Due Date):
     *   - Current Bill            = current month principal (₱195)
     *   - Water Maintenance       = meter rental for all unpaid months (1 → 20, 2 → 40, 3 → 60, …)
     *   - Penalty                 = only for past‑due unpaid months (penalty rows already created for earlier bills)
     *   - Arrears — Current Year  = 0
     *   - Arrears — Previous Year = sum of prior unpaid principal (billing_month < current_month, paid_at IS NULL)
     *
     * POST-DUE (Billing Due Date → Current Billing Month):
     *   - Current Bill            = 0
     *   - Water Maintenance       = meter rental for all unpaid months
     *   - Penalty                 = all unpaid PENALTY rows whose billing due_date has passed
     *   - Arrears — Current Year  = unpaid principal where billing_year = current_year
     *   - Arrears — Previous Year = unpaid principal where billing_year < current_year
     *
     * @param int $consumerId consumer_zone_id
     * @param string $viewType 'pre_due'|'post_due'
     * @param \Carbon\Carbon|null $asOfDate effective "today" for determining past‑due (default: now)
     * @param int|null $payMonths 1, 2, or 3 for "Pay N months" amount; null = full breakdown only
     * @param string|null $selectedBillMonthYmd selected bill month in Y-m format (e.g. 2025-12); when set, breakdown is for that month (Arrears PY = prior unpaid months)
     * @return array { current_bill, penalty, water_maintenance_charge, arrears_cy, arrears_py, unpaid_count, past_due_count, amount_due? }
     */
    /**
     * Resolve bill month from a ledger row: schedule.bill_month, else due_date, else date.
     * @return \Carbon\Carbon|null
     */
    private function getBillMonthFromRow(ConsumerLedger $row): ?Carbon
    {
        if ($row->schedule && $row->schedule->bill_month) {
            return Carbon::parse($row->schedule->bill_month);
        }
        if (!empty($row->due_date)) {
            return Carbon::parse($row->due_date);
        }
        if (!empty($row->date)) {
            return Carbon::parse($row->date);
        }
        return null;
    }

    private function getBillingBreakdownForConsumer(int $consumerId, string $viewType, $asOfDate = null, ?int $payMonths = null, ?string $selectedBillMonthYmd = null): array
    {
        $today = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();

        // Unpaid BILLING rows (principal + meter rental)
        $billingRows = ConsumerLedger::where('consumer_zone_id', $consumerId)
            ->whereIn('trans', ['BILLING', 'BILL'])
            ->where('billamount', '>', 0)
            ->with('schedule:id,bill_month,due_date')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $unpaid = $billingRows->whereNull('paid_at');
        $unpaidCount = $unpaid->count();
        // "Past-due" BILLING = due_date < asOfDate
        $pastDueUnpaid = $unpaid->filter(function ($row) use ($today) {
            $due = $row->due_date ? Carbon::parse($row->due_date) : null;
            return $due && $today->greaterThan($due);
        });
        $pastDueCount = $pastDueUnpaid->count();

        // Current month for breakdown: use selected bill month when provided (e.g. from "Select Bill Month to Pay"), else latest billing month
        if ($selectedBillMonthYmd !== null && $selectedBillMonthYmd !== '') {
            $currentMonth = $selectedBillMonthYmd;
        } else {
            $currentMonth = $billingRows->isEmpty() ? null : $billingRows->max(function ($row) {
                $billMonth = $this->getBillMonthFromRow($row);
                return $billMonth ? $billMonth->format('Y-m') : null;
            });
        }
        $currentYear = $billingRows->isEmpty()
            ? (int) $today->format('Y')
            : (int) max(array_map(function ($row) {
                /** @var ConsumerLedger $row */
                $billMonth = $this->getBillMonthFromRow($row);
                return $billMonth ? (int) $billMonth->format('Y') : (int) date('Y');
            }, $billingRows->all()));

        $monthlyPrincipal = (float) self::MONTHLY_PRINCIPAL;
        $wmcPerMonth = (float) self::WMC_PER_MONTH;

        // Unpaid PENALTY: ConsumerLedger PENALTY rows + Penalty model rows
        $penaltyRows = ConsumerLedger::where('consumer_zone_id', $consumerId)
            ->where('trans', 'PENALTY')
            ->whereNull('paid_at')
            ->orderBy('date', 'asc')
            ->get();
        $penaltyTotal = 0.0;
        foreach ($penaltyRows as $prow) {
            $amt = (float) ($prow->penalty ?? 0);
            if ($amt <= 0) {
                $amt = (float) ($prow->debit ?? 0);
            }
            if ($amt <= 0) {
                continue;
            }
            $penaltyTotal += $amt;
        }
        $unpaidPenaltyModels = Penalty::where('consumer_zone_id', $consumerId)->whereNull('paid_at')->get();
        foreach ($unpaidPenaltyModels as $p) {
            $penaltyTotal += (float) ($p->penalty_amount ?? 0);
        }

        if ($viewType === 'pre_due') {
            // PRE-DUE:
            // - Current Bill = actual billing amount for selected month from unpaid rows
            // - Arrears — Current Year = unpaid principal where billing_year = current_year AND bill_month != currentMonth (exclude selected month)
            // - Arrears — Previous Year = unpaid principal where billing_year < current_year
            // - Penalty = all unpaid penalty rows
            // - Water Maintenance Charge = unpaid months (same 1–3 month patterns as examples)
            // Get actual bill amount for selected month from unpaid billing rows
            $currentBill = 0.00;
            if ($currentMonth !== null) {
                $currentBillRow = $unpaid->first(function ($row) use ($currentMonth) {
                    $billMonth = $this->getBillMonthFromRow($row);
                    if (!$billMonth) {
                        return false;
                    }
                    $billMonthYmd = $billMonth->format('Y-m');
                    return $billMonthYmd === $currentMonth;
                });
                if ($currentBillRow) {
                    $currentBill = (float) ($currentBillRow->billamount ?? 0);
                }
            }
            // Penalty rows already exist only for past‑due bills, and our window (asOfDate)
            // ensures we only include penalties that are "active" as of today.
            $penalty = round($penaltyTotal, 2);
            // Example patterns:
            // 1 unpaid month → 20.00
            // 2 unpaid months → 40.00
            // 3 unpaid months → 60.00
            $waterMaintenanceCharge = round($wmcPerMonth * $unpaidCount, 2);
            // Year-based arrears: CY = current year (excluding selected month), PY = past years (e.g. 2016-2025 → PY, 2026 → CY)
            $arrearsCurrentYear = round($unpaid->sum(function ($row) use ($currentYear, $currentMonth) {
                $billMonth = $this->getBillMonthFromRow($row);
                if (!$billMonth) {
                    return 0;
                }
                $year = (int) $billMonth->format('Y');
                $billMonthYmd = $billMonth->format('Y-m');
                // Include only if: year === currentYear AND bill_month !== currentMonth (exclude selected month from arrears)
                return ($year === $currentYear && $billMonthYmd !== $currentMonth) ? (float) ($row->billamount ?? 0) : 0;
            }), 2);
            $arrearsPreviousYear = round($unpaid->sum(function ($row) use ($currentYear) {
                $billMonth = $this->getBillMonthFromRow($row);
                $year = $billMonth ? (int) $billMonth->format('Y') : $currentYear;
                return $year < $currentYear ? (float) ($row->billamount ?? 0) : 0;
            }), 2);
        } else {
            // POST-DUE:
            // - Current Bill            = 0
            // - Water Maintenance       = unpaid months
            // - Penalty                 = all unpaid penalty rows for past‑due bills
            // - Arrears — Current Year  = unpaid principal where billing_year = current_year
            // - Arrears — Previous Year = unpaid principal where billing_year < current_year
            $currentBill = 0.00;
            $penalty = round($penaltyTotal, 2);
            $waterMaintenanceCharge = round($wmcPerMonth * $unpaidCount, 2);
            $arrearsCurrentYear = round($unpaid->sum(function ($row) use ($currentYear) {
                $billMonth = $this->getBillMonthFromRow($row);
                $year = $billMonth ? (int) $billMonth->format('Y') : $currentYear;
                return $year === $currentYear ? (float) ($row->billamount ?? 0) : 0;
            }), 2);
            $arrearsPreviousYear = round($unpaid->sum(function ($row) use ($currentYear) {
                $billMonth = $this->getBillMonthFromRow($row);
                $year = $billMonth ? (int) $billMonth->format('Y') : $currentYear;
                return $year < $currentYear ? (float) ($row->billamount ?? 0) : 0;
            }), 2);
        }

        $result = [
            'current_bill' => round($currentBill, 2),
            'penalty' => $penalty,
            'water_maintenance_charge' => $waterMaintenanceCharge,
            'arrears_cy' => $arrearsCurrentYear,
            'arrears_py' => $arrearsPreviousYear,
            'unpaid_count' => $unpaidCount,
            'past_due_count' => $pastDueCount,
            'view_type' => $viewType,
        ];

        if ($payMonths !== null && $payMonths >= 1 && $payMonths <= 3) {
            $principalPay = $payMonths * $monthlyPrincipal;
            $result['amount_due'] = round($principalPay + $penalty + $waterMaintenanceCharge, 2);
            $result['pay_months'] = $payMonths;
            $remainingPrincipal = max(0, $unpaidCount - $payMonths) * $monthlyPrincipal;
            $result['arrears_cy_after_pay'] = round($remainingPrincipal, 2);
            $result['arrears_py_after_pay'] = 0.00;
        }

        return $result;
    }

    /**
     * Public entry point for breakdown data (used by MeterReadingController::getBillMonthDetails).
     * Returns same array as getBillingBreakdownForConsumer so payment form always uses database (paid_at only).
     * @param string|null $selectedBillMonthYmd selected bill month in Y-m format (e.g. 2025-12); when set, Arrears PY = prior unpaid months for that month (1st/2nd/3rd month format)
     */
    public function getBillingBreakdownData(int $consumerId, string $viewType, $asOfDate = null, ?int $payMonths = null, ?string $selectedBillMonthYmd = null): array
    {
        return $this->getBillingBreakdownForConsumer($consumerId, $viewType, $asOfDate, $payMonths, $selectedBillMonthYmd);
    }

    /**
     * API: Get billing breakdown for an account (PRE-DUE or POST-DUE).
     * Used by payment form for Pay 1 / 2 / 3 months.
     * Query: account_no, view_type (pre_due|post_due), pay_months (optional 1|2|3).
     */
    public function getBillingBreakdown(Request $request)
    {
        $validated = $request->validate([
            'account_no' => 'required|string',
            'view_type' => 'required|in:pre_due,post_due',
            'as_of_date' => 'nullable|date',
            'pay_months' => 'nullable|integer|in:1,2,3',
        ]);

        $consumer = ConsumerZoneOne::where('account_no', $validated['account_no'])->first();
        if (!$consumer) {
            $normalized = str_replace('-', '', $validated['account_no']);
            $consumer = ConsumerZoneOne::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
        }
        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer not found for account number.',
            ], 404);
        }

        $asOf = !empty($validated['as_of_date'])
            ? Carbon::parse($validated['as_of_date'])
            : null;
        $payMonths = isset($validated['pay_months']) ? (int) $validated['pay_months'] : null;

        $breakdown = $this->getBillingBreakdownForConsumer(
            (int) $consumer->id,
            $validated['view_type'],
            $asOf,
            $payMonths
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($breakdown, [
                'account_no' => $consumer->account_no,
                'account_name' => $consumer->account_name,
            ]),
        ]);
    }

    /**
     * Search billing records
     */
    public function searchRecords(Request $request)
    {
        $request->validate([
            'search_type' => 'required|in:sedr,account',
            'search_value' => 'required|string',
            'zone' => 'nullable|string',
        ]);

        try {
            $searchType = $request->search_type;
            $searchValue = $request->search_value;
            $zone = $request->zone;

            $query = ConsumerZoneOne::query();

            if ($searchType === 'account') {
                $query->where('account_no', 'like', '%' . $searchValue . '%');
            }

            if ($zone) {
                $query->where('zone_code', $zone);
            }

            $consumers = $query->get();

            if ($consumers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No records found matching your search'
                ], 404);
            }

            $results = [];
            $sedr = 1;

            foreach ($consumers as $consumer) {
                $previousReading = $this->getPreviousReading($consumer->account_no);
                
                $results[] = [
                    'sedr' => $sedr++,
                    'account_number' => $consumer->account_no,
                    'account_name' => $consumer->account_name,
                    'address' => $consumer->address1 ?? '',
                    'zone' => $consumer->zone_code,
                    'category' => $consumer->category_code ?? '',
                    'meter_number' => $consumer->meter_number ?? '',
                    'prev_date' => $previousReading['date'],
                    'prev_read' => $previousReading['reading'],
                    'pres_read' => 0,
                    'volume' => $previousReading['volume'], // Show latest volume from consumer_ledgers
                    'current_bill' => 0.00,
                    'arrears' => $previousReading['arrears'],
                    'total' => 0.00,
                    'status' => 'Active'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick lookup: Fetch account name and latest bill from downloaded readings.
     */
    public function lookupAccountLatestBill(Request $request)
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
        ]);

        $accountNumber = strtoupper(trim($validated['account_number']));

        $consumer = ConsumerZoneOne::where(function ($query) use ($accountNumber) {
                $query->where('account_no', $accountNumber)
                      ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [str_replace('-', '', $accountNumber)]);
            })
            ->first();

        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Account number not found. Please verify and try again.',
            ], 404);
        }

        $latestReading = DownloadedReading::with(['schedule' => function ($query) {
                $query->select(
                    'id',
                    'account_number',
                    'account_name',
                    'zone',
                    'category',
                    'address',
                    'meter_number',
                    'bill_month',
                    'current_bill',
                    'arrears',
                    'total_amount',
                    'status'
                );
            }])
            ->where(function ($query) use ($accountNumber) {
                $trimmed = str_replace('-', '', $accountNumber);

                $query->where('account_number', $accountNumber)
                      ->orWhereRaw("REPLACE(account_number, '-', '') = ?", [$trimmed])
                      ->orWhereHas('schedule', function ($scheduleQuery) use ($accountNumber, $trimmed) {
                          $scheduleQuery->where('account_number', $accountNumber)
                                        ->orWhereRaw("REPLACE(account_number, '-', '') = ?", [$trimmed]);
                      });
            })
            ->orderByDesc(DB::raw("COALESCE(reading_date, completed_at, updated_at, created_at)"))
            ->orderByDesc('id')
            ->first();

        if (!$latestReading) {
        return response()->json([
            'success' => true,
            'data' => [
                'account_number' => $consumer->account_no,
                'account_name' => $consumer->account_name,
                'zone' => $consumer->zone_code,
                'category' => $consumer->category_code,
                'latest_bill' => null,
                'message' => 'No downloaded readings found for this account yet.',
            ],
        ]);
        }

        $schedule = $latestReading->schedule;
        $billMonth = $schedule?->bill_month ? Carbon::parse($schedule->bill_month) : null;

        $currentBill = $schedule?->current_bill !== null
            ? (float) $schedule->current_bill
            : null;

        $arrears = $schedule?->arrears !== null
            ? (float) $schedule->arrears
            : 0.0;

        $totalAmount = $schedule?->total_amount !== null
            ? (float) $schedule->total_amount
            : null;

        $paymentAmount = $latestReading->payment_amount !== null
            ? (float) $latestReading->payment_amount
            : null;

        $latestAmount = $paymentAmount
            ?? $totalAmount
            ?? (($currentBill ?? 0.0) + $arrears);

        $latestBill = [
            'amount' => round($latestAmount, 2),
            'current_bill' => $currentBill,
            'arrears' => $arrears,
            'total_amount' => $totalAmount,
            'payment_amount' => $paymentAmount,
            'bill_month' => $billMonth?->format('Y-m'),
            'bill_month_display' => $billMonth?->format('F Y'),
            'reading_date' => optional($latestReading->reading_date)->format('Y-m-d'),
            'status' => $latestReading->status,
            'payment_status' => $latestReading->status === 'paid' ? 'Paid' : ucfirst($latestReading->status ?? 'Pending'),
            'payment_method' => $latestReading->payment_method,
            'paid_at' => optional($latestReading->paid_at)?->format('Y-m-d H:i:s'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'account_number' => $consumer->account_no,
                'account_name' => $consumer->account_name,
                'zone' => $consumer->zone_code,
                'category' => $consumer->category_code,
                'latest_bill' => $latestBill,
            ],
        ]);
    }

    /**
     * Export billing data
     */
    public function exportData(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf',
            'zone' => 'nullable|string',
        ]);

        // This would generate the actual export file
        // For now, return success message
        return response()->json([
            'success' => true,
            'message' => 'Export functionality will be implemented'
        ]);
    }

    /**
     * Debug endpoint - Get all consumers info by zone
     */
    public function debugConsumers(Request $request)
    {
        $zone = $request->get('zone');
        
        if ($zone) {
            $consumers = ConsumerZoneOne::where('zone_code', $zone)->get();
            $grouped = $consumers->groupBy('status_code');
            
            return response()->json([
                'zone' => $zone,
                'total' => $consumers->count(),
                'by_status' => $grouped->map(function($items) {
                    return $items->count();
                }),
                'consumers' => $consumers->map(function($c) {
                    return [
                        'id' => $c->id,
                        'account_no' => $c->account_no,
                        'account_name' => $c->account_name,
                        'zone_code' => $c->zone_code,
                        'status_code' => $c->status_code,
                        'status_label' => $c->status_label,
                        'category_code' => $c->category_code
                    ];
                })
            ]);
        }
        
        // Get all zones with consumer counts
        $zones = ConsumerZoneOne::select('zone_code as zone', DB::raw('count(*) as total'))
            ->groupBy('zone_code')
            ->get();
            
        return response()->json([
            'message' => 'Available zones',
            'zones' => $zones
        ]);
    }

    /**
     * Get distinct meter reading preparation batches for Schedule Viewing tab
     * Sorted by zone ASC, then bill_month DESC by default. Accepts sort_by, sort_dir, zone, bill_month (filters).
     */
    public function getScheduleBatches(Request $request)
    {
        $sortBy = $request->get('sort_by', 'zone');
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $zoneFilter = $request->get('zone');
        $billMonthFilter = $request->get('bill_month');

        $query = MeterReadingSchedule::query()
            ->select('zone', 'bill_month', 'bill_date', 'due_date', 'disconnection_date')
            ->selectRaw('COUNT(*) as schedule_count')
            ->groupBy('zone', 'bill_month', 'bill_date', 'due_date', 'disconnection_date');

        if ($zoneFilter && $zoneFilter !== '' && $zoneFilter !== 'all') {
            $query->where('zone', $zoneFilter);
        }

        if ($billMonthFilter && $billMonthFilter !== '' && $billMonthFilter !== 'all') {
            $query->where('bill_month', Carbon::parse($billMonthFilter)->format('Y-m-d'));
        }

        if ($sortBy === 'bill_month') {
            $query->orderByRaw('bill_month ' . $sortDir . ', zone ASC');
        } else {
            $query->orderByRaw('zone ' . $sortDir . ', bill_month DESC');
        }

        $batches = $query->get()
            ->map(function ($row) {
                return [
                    'zone' => $row->zone,
                    'bill_month' => $row->bill_month ? Carbon::parse($row->bill_month)->format('Y-m-d') : null,
                    'bill_date' => $row->bill_date ? Carbon::parse($row->bill_date)->format('Y-m-d') : null,
                    'due_date' => $row->due_date ? Carbon::parse($row->due_date)->format('Y-m-d') : null,
                    'disconnection_date' => $row->disconnection_date ? Carbon::parse($row->disconnection_date)->format('Y-m-d') : null,
                    'schedule_count' => (int) $row->schedule_count,
                ];
            });

        // Distinct bill months for dropdown (optionally filtered by zone)
        $distinctMonthsQuery = MeterReadingSchedule::query()->select('bill_month')->whereNotNull('bill_month')->distinct();
        if ($zoneFilter && $zoneFilter !== '' && $zoneFilter !== 'all') {
            $distinctMonthsQuery->where('zone', $zoneFilter);
        }
        $distinct_bill_months = $distinctMonthsQuery->orderBy('bill_month', 'desc')->pluck('bill_month')->map(function ($d) {
            return $d ? Carbon::parse($d)->format('Y-m-d') : null;
        })->filter()->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $batches,
            'distinct_bill_months' => $distinct_bill_months,
        ]);
    }

    /**
     * Get meter reading schedules by zone and period
     */
    public function getSchedules(Request $request)
    {
        $zone = $request->get('zone');
        $billMonth = $request->get('bill_month');

        $query = MeterReadingSchedule::with(['consumer', 'assignedReader']);

        if ($zone) {
            $query->where('zone', $zone);
        }

        if ($billMonth) {
            $query->where('bill_month', Carbon::parse($billMonth)->format('Y-m-d'));
        }

        $schedules = $query->orderBy('sedr_number')->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
            'total' => $schedules->count()
        ]);
    }

    /**
     * Assign schedules to a reader
     */
    public function assignToReader(Request $request)
    {
        $request->validate([
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'exists:meter_reading_schedules,id',
            'reader_id' => 'required|exists:users,id'
        ]);

        try {
            $updated = MeterReadingSchedule::whereIn('id', $request->schedule_ids)
                ->update([
                    'assigned_reader_id' => $request->reader_id,
                    'status' => 'Assigned',
                    'assigned_at' => Carbon::now()
                ]);

            return response()->json([
                'success' => true,
                'message' => $updated . ' schedule(s) assigned to reader successfully',
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete meter reading schedules (by zone + bill_month, or by exact batch when bill_date/due_date/disconnection_date provided).
     * Database cascade will delete related consumer_ledgers (and downloaded_readings, penalties) when schedules are deleted.
     */
    public function deleteSchedules(Request $request)
    {
        $request->validate([
            'zone' => 'required|string',
            'bill_month' => 'required|date',
            'bill_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'disconnection_date' => 'nullable|date',
        ]);

        try {
            $zone = $request->zone;
            $billMonth = Carbon::parse($request->bill_month)->format('Y-m-d');

            $query = MeterReadingSchedule::where('zone', $zone)->where('bill_month', $billMonth);

            if ($request->filled('bill_date')) {
                $query->where('bill_date', Carbon::parse($request->bill_date)->format('Y-m-d'));
            }
            if ($request->filled('due_date')) {
                $query->where('due_date', Carbon::parse($request->due_date)->format('Y-m-d'));
            }
            if ($request->filled('disconnection_date')) {
                $query->where('disconnection_date', Carbon::parse($request->disconnection_date)->format('Y-m-d'));
            }

            $deleted = $query->delete();

            return response()->json([
                'success' => true,
                'message' => $deleted . ' schedule(s) deleted successfully',
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a batch of meter reading schedules (same zone, bill_month, bill_date, due_date, disconnection_date).
     * Also updates due_date on related consumer_ledgers so ledger stays in sync.
     */
    public function updateScheduleBatch(Request $request)
    {
        $request->validate([
            'zone' => 'required|string',
            'bill_month' => 'required|date',
            'bill_date' => 'required|date',
            'due_date' => 'required|date',
            'disconnection_date' => 'required|date',
            'new_bill_month' => 'required|date',
            'new_bill_date' => 'required|date',
            'new_due_date' => 'required|date',
            'new_disconnection_date' => 'required|date',
        ]);

        try {
            $zone = $request->zone;
            $billMonth = Carbon::parse($request->bill_month)->format('Y-m-d');
            $billDate = Carbon::parse($request->bill_date)->format('Y-m-d');
            $dueDate = Carbon::parse($request->due_date)->format('Y-m-d');
            $disconnectionDate = Carbon::parse($request->disconnection_date)->format('Y-m-d');

            $scheduleIds = MeterReadingSchedule::where('zone', $zone)
                ->where('bill_month', $billMonth)
                ->where('bill_date', $billDate)
                ->where('due_date', $dueDate)
                ->where('disconnection_date', $disconnectionDate)
                ->pluck('id');

            if ($scheduleIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No schedules found for the selected batch.',
                ], 404);
            }

            $newBillMonth = Carbon::parse($request->new_bill_month)->format('Y-m-d');
            $newBillDate = Carbon::parse($request->new_bill_date)->format('Y-m-d');
            $newDueDate = Carbon::parse($request->new_due_date)->format('Y-m-d');
            $newDisconnectionDate = Carbon::parse($request->new_disconnection_date)->format('Y-m-d');

            MeterReadingSchedule::whereIn('id', $scheduleIds)->update([
                'bill_month' => $newBillMonth,
                'bill_date' => $newBillDate,
                'due_date' => $newDueDate,
                'disconnection_date' => $newDisconnectionDate,
            ]);

            ConsumerLedger::whereIn('schedule_id', $scheduleIds)->update(['due_date' => $newDueDate]);

            return response()->json([
                'success' => true,
                'message' => $scheduleIds->count() . ' schedule(s) and related ledger due dates updated successfully.',
                'updated_count' => $scheduleIds->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get downloaded readings for bill printing
     * Fetches from downloaded_readings table joined with meter_reading_schedules
     * Filters by zone and reading_date only
     */
    public function getDownloadedReadings(Request $request)
    {
        $request->validate([
            'zone' => 'required|string',
            'reading_date' => 'required|date'
        ]);

        try {
            $zone = $request->zone;
            $readingDate = Carbon::parse($request->reading_date);

            // Build select columns - payment information comes from consumer_payments table
            // Use COALESCE to handle cases where meter_reading_schedules might not exist
            $selectColumns = [
                'dr.id as downloaded_id',
                DB::raw('COALESCE(mrs.sedr_number, dr.account_number) as sedr'),
                DB::raw('COALESCE(mrs.account_number, dr.account_number) as account_number'),
                DB::raw('COALESCE(mrs.account_name, dr.account_name) as account_name'),
                'mrs.address',
                DB::raw('COALESCE(mrs.zone, dr.zone) as zone'),
                DB::raw('COALESCE(mrs.category, NULL) as category'),
                'mrs.meter_number',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.disconnection_date',
                'mrs.previous_reading_date as prev_date',
                'dr.previous_reading as prev_read',
                'dr.current_reading as pres_read',
                'dr.consumption as volume',
                'dr.reading_date',
                'dr.status',
                'dr.current_bill as downloaded_current_bill',
                DB::raw('COALESCE(mrs.arrears, 0) as arrears'),
                // Payment fields from consumer_payments table
                'cp.payment_method',
                'cp.payment_amount',
                'cp.amount_tendered',
                'cp.change_amount',
                'cp.or_number as payment_reference',
                'cp.remarks as payment_remarks',
                'cp.paid_at',
            ];

            // Query downloaded_readings - primary table, use LEFT JOIN for schedules
            // Filter by zone and reading_date
            // Check zone from both dr.zone and mrs.zone to handle all cases
            $baseQuery = DB::table('downloaded_readings as dr')
                ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
                ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
                ->select($selectColumns)
                ->where(function($query) use ($zone) {
                    // Normalize zone comparison: exact, padded, and trimmed/no-leading-zero for both dr and mrs
                    $query->where(function($qq) use ($zone) {
                        $qq->where('dr.zone', $zone)
                           ->orWhereRaw('LPAD(dr.zone, 3, "0") = ?', [$zone])
                           ->orWhereRaw('TRIM(LEADING "0" FROM dr.zone) = TRIM(LEADING "0" FROM ?)', [$zone]);
                    })
                    ->orWhere(function($qq) use ($zone) {
                        $qq->whereNotNull('mrs.zone')
                           ->where(function($qqq) use ($zone) {
                               $qqq->where('mrs.zone', $zone)
                                   ->orWhereRaw('LPAD(mrs.zone, 3, "0") = ?', [$zone])
                                   ->orWhereRaw('TRIM(LEADING "0" FROM mrs.zone) = TRIM(LEADING "0" FROM ?)', [$zone]);
                           });
                    });
                });

            // Filter by exact Reading Date only (when readings were taken)
            // Use only dr.reading_date so Bill Printing shows only records for the selected date
            $date = $readingDate->format('Y-m-d');
            // Order by CUSTOMER ACCOUNT NUMBER ascending (e.g. 011-12-020 lowest to highest) for Daily billing report
            $readings = (clone $baseQuery)
                ->whereDate('dr.reading_date', $date)
                ->orderBy(DB::raw('COALESCE(mrs.account_number, dr.account_number)'), 'asc')
                ->get();

            if ($readings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No downloaded readings found for Zone ' . $zone . ' on ' . $readingDate->format('F d, Y')
                ], 404);
            }

            // Get bill_month from the first record for summary
            $billMonth = $readings->first()->bill_month ? Carbon::parse($readings->first()->bill_month) : Carbon::now();

            // Format data for display: PRE-DUE vs POST-DUE formulas (paid_at is sole source of truth)
            $formattedReadings = [];
            $today = Carbon::now()->startOfDay();
            foreach ($readings as $reading) {
                $consumption = $reading->volume ?? 0;
                $currentBill = 0.00;
                $penalty = 0.00;
                $waterMaintenanceCharge = 0.00;
                $arrearsCy = 0.00;
                $arrearsPy = 0.00;
                $viewType = 'post_due';

                $consumer = null;
                if (!empty($reading->account_number)) {
                    $consumer = ConsumerZoneOne::where('account_no', $reading->account_number)->first();
                    if (!$consumer) {
                        $normalized = str_replace('-', '', $reading->account_number);
                        $consumer = ConsumerZoneOne::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
                    }
                }
                if ($consumer) {
                    $dueDate = !empty($reading->due_date) ? Carbon::parse($reading->due_date)->startOfDay() : null;
                    $viewType = ($dueDate && $today->lte($dueDate)) ? 'pre_due' : 'post_due';
                    $breakdown = $this->getBillingBreakdownForConsumer((int) $consumer->id, $viewType, null, null);
                    $currentBill = (float) ($breakdown['current_bill'] ?? 0);
                    $penalty = (float) ($breakdown['penalty'] ?? 0);
                    $waterMaintenanceCharge = (float) ($breakdown['water_maintenance_charge'] ?? 0);
                    $arrearsCy = (float) ($breakdown['arrears_cy'] ?? 0);
                    $arrearsPy = (float) ($breakdown['arrears_py'] ?? 0);
                } else {
                    $currentBill = isset($reading->downloaded_current_bill) ? (float) $reading->downloaded_current_bill : 0.00;
                    $arrearsPy = (float) ($reading->arrears ?? 0);
                }

                $arrears = $arrearsCy + $arrearsPy;
                $total = round($currentBill + $penalty + $waterMaintenanceCharge + $arrears, 2);
                $statusCode = strtolower($reading->status ?? 'downloaded');
                $statusLabel = match ($statusCode) {
                    'paid' => 'Paid',
                    'completed' => 'Completed',
                    'pending' => 'Pending',
                    default => 'Downloaded',
                };

                $formattedReading = [
                    'downloaded_id' => $reading->downloaded_id,
                    'sedr' => $reading->sedr ?? '-',
                    'account_number' => $reading->account_number ?? '-',
                    'account_name' => $reading->account_name ?? '-',
                    'address' => $reading->address ?? '-',
                    'zone' => $reading->zone ?? '-',
                    'category' => $reading->category ?? '-',
                    'meter_number' => $reading->meter_number ?? '-',
                    'prev_date' => $reading->prev_date ? Carbon::parse($reading->prev_date)->format('m/d/Y') : '-',
                    'prev_read' => $reading->prev_read ?? 0,
                    'pres_read' => $reading->pres_read ?? 0,
                    'volume' => $consumption,
                    'penalty_charge' => round($penalty, 2),
                    'water_maintenance_charge' => round($waterMaintenanceCharge, 2),
                    'current_bill' => round($currentBill, 2),
                    'arrears' => round($arrears, 2),
                    'arrears_cy' => round($arrearsCy, 2),
                    'arrears_py' => round($arrearsPy, 2),
                    'total' => $total,
                    'status' => $statusLabel,
                    'status_code' => $statusCode,
                    'view_type' => $viewType,
                    'due_date' => $reading->due_date ? Carbon::parse($reading->due_date)->format('F d, Y') : null,
                ];

                // Payment fields: source of truth is consumer_payments.paid_at only
                $formattedReading['payment_method'] = $reading->payment_method ?? null;
                $formattedReading['payment_amount'] = isset($reading->payment_amount) ? (float) $reading->payment_amount : null;
                $formattedReading['amount_tendered'] = isset($reading->amount_tendered) ? (float) $reading->amount_tendered : null;
                $formattedReading['change_amount'] = isset($reading->change_amount) ? (float) $reading->change_amount : null;
                $formattedReading['payment_reference'] = $reading->payment_reference ?? null;
                $formattedReading['payment_remarks'] = $reading->payment_remarks ?? null;
                $formattedReading['paid_at'] = $reading->paid_at ? Carbon::parse($reading->paid_at)->format('Y-m-d H:i:s') : null;

                $formattedReadings[] = $formattedReading;
            }

        return response()->json([
            'success' => true,
            'message' => count($formattedReadings) . ' downloaded reading(s) found for Zone ' . $zone,
            'data' => $formattedReadings,
            'summary' => [
                'zone' => $zone,
                'bill_month' => $billMonth->format('F Y'),
                'reading_date' => $readingDate->format('F d, Y'),
                'prepared_date' => Carbon::now()->format('F d, Y'),
                'total_readings' => count($formattedReadings)
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error loading downloaded readings: ' . $e->getMessage()
        ], 500);
    }
    }

    /**
     * Get surcharge candidates: past-due consumers (no payment) for the selected zone and bill date.
     * Used by Generate Surcharge to list consumers that can have penalty/surcharge applied.
     */
    public function getSurchargeCandidates(Request $request)
    {
        try {
            $request->validate([
                'zone' => 'required|string',
                'bill_date' => 'required|date',
            ]);

            $zone = trim($request->input('zone'));
            $billDate = Carbon::parse($request->input('bill_date'))->startOfDay();
            $today = Carbon::now()->startOfDay();

            // Schedules in zone with this bill_date, due_date already passed, and no PAYMENT for this schedule
            $schedulesQuery = MeterReadingSchedule::query()
                ->leftJoin('downloaded_readings as dr', 'dr.schedule_id', '=', 'meter_reading_schedules.id')
                ->where(function ($q) use ($zone) {
                    $q->where('meter_reading_schedules.zone', $zone)
                        ->orWhereRaw('LPAD(TRIM(meter_reading_schedules.zone), 3, "0") = ?', [$zone])
                        ->orWhereRaw('TRIM(LEADING "0" FROM meter_reading_schedules.zone) = TRIM(LEADING "0" FROM ?)', [$zone]);
                })
                ->whereDate('meter_reading_schedules.bill_date', $billDate->format('Y-m-d'))
                ->whereNotNull('meter_reading_schedules.due_date')
                ->whereRaw('CAST(meter_reading_schedules.due_date AS DATE) < ?', [$today->format('Y-m-d')])
                ->whereDoesntHave('ledgerEntries', function ($q) {
                    $q->where('trans', 'PAYMENT');
                })
                ->select(
                    'meter_reading_schedules.id as schedule_id',
                    'meter_reading_schedules.account_number',
                    'meter_reading_schedules.account_name',
                    'meter_reading_schedules.address',
                    'meter_reading_schedules.zone',
                    'meter_reading_schedules.category',
                    'meter_reading_schedules.meter_number',
                    'meter_reading_schedules.sedr_number as sedr',
                    'meter_reading_schedules.previous_reading_date as prev_date',
                    'meter_reading_schedules.previous_reading as prev_read',
                    'meter_reading_schedules.bill_date',
                    'meter_reading_schedules.due_date',
                    'dr.id as downloaded_id',
                    'dr.current_reading as pres_read',
                    'dr.consumption as volume',
                    'dr.current_bill as dr_current_bill'
                );

            $rows = $schedulesQuery->get();

            // Resolve consumer_zone_id and build list (exclude if PAYMENT exists in consumer_ledgers for this schedule)
            $paidScheduleIds = ConsumerLedger::where('trans', 'PAYMENT')
                ->whereIn('schedule_id', $rows->pluck('schedule_id')->filter()->toArray())
                ->pluck('schedule_id')
                ->toArray();

            $data = [];
            foreach ($rows as $row) {
                if (in_array($row->schedule_id, $paidScheduleIds, true)) {
                    continue;
                }

                $consumer = ConsumerZoneOne::where('account_no', $row->account_number)->first();
                if (!$consumer) {
                    $norm = str_replace('-', '', $row->account_number);
                    $consumer = ConsumerZoneOne::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$norm])->first();
                }

                $arrears = 0.00;
                $currentBill = (float) ($row->dr_current_bill ?? 0);
                if ($currentBill <= 0 && $consumer) {
                    $breakdown = $this->getBillingBreakdownForConsumer((int) $consumer->id, 'post_due', null, null);
                    $currentBill = (float) ($breakdown['current_bill'] ?? 0);
                    $arrears = (float) ($breakdown['arrears_cy'] ?? 0) + (float) ($breakdown['arrears_py'] ?? 0);
                } elseif ($consumer) {
                    $breakdown = $this->getBillingBreakdownForConsumer((int) $consumer->id, 'post_due', null, null);
                    $arrears = (float) ($breakdown['arrears_cy'] ?? 0) + (float) ($breakdown['arrears_py'] ?? 0);
                }

                $wmc = ($currentBill > 0) ? (float) self::WMC_PER_MONTH : 0.00;
                $penaltyBase = $currentBill + $wmc;
                $calculatedPenalty = round($penaltyBase * (float) self::PENALTY_RATE, 2);
                if ($calculatedPenalty < (float) self::PENALTY_PER_MONTH && $penaltyBase > 0) {
                    $calculatedPenalty = (float) self::PENALTY_PER_MONTH;
                }
                $total = round($currentBill + $wmc + $arrears + $calculatedPenalty, 2);

                $data[] = [
                    'schedule_id' => $row->schedule_id,
                    'downloaded_id' => $row->downloaded_id,
                    'consumer_zone_id' => $consumer ? $consumer->id : null,
                    'account_number' => $row->account_number,
                    'account_name' => $row->account_name,
                    'address' => $row->address,
                    'zone' => $row->zone,
                    'category' => $row->category,
                    'meter_number' => $row->meter_number,
                    'sedr' => $row->sedr,
                    'prev_date' => $row->prev_date ? Carbon::parse($row->prev_date)->format('m/d/Y') : '-',
                    'prev_read' => $row->prev_read ?? 0,
                    'pres_read' => $row->pres_read ?? 0,
                    'volume' => $row->volume ?? 0,
                    'current_bill' => round($currentBill, 2),
                    'arrears' => round($arrears, 2),
                    'calculated_penalty' => $calculatedPenalty,
                    'penalty_base' => round($penaltyBase, 2),
                    'total' => $total,
                    'due_date' => $row->due_date ? Carbon::parse($row->due_date)->format('Y-m-d') : null,
                    'status' => 'Past Due',
                    'include' => true,
                ];
            }

            $summary = [
                'total_records' => count($data),
                'total_penalty' => round(array_sum(array_column($data, 'calculated_penalty')), 2),
                'zone' => $zone,
                'bill_date' => $billDate->format('Y-m-d'),
            ];

            return response()->json([
                'success' => true,
                'message' => count($data) . ' past-due consumer(s) found for surcharge.',
                'data' => $data,
                'summary' => $summary,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('getSurchargeCandidates error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading surcharge candidates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate water bill based on consumption (cubic meters)
     */
    private function calculateWaterBill($consumption)
    {
        // Basic rate structure (adjust to your actual rates)
        $minimumCharge = 150.00; // For 0-10 cubic meters
        $rate = 15.00; // Per cubic meter beyond minimum

        if ($consumption <= 10) {
            return $minimumCharge;
        }

        return $minimumCharge + (($consumption - 10) * $rate);
    }

    /**
     * Generate penalty report for consumers who have reached due date
     * Gets data from downloaded_readings table only
     */
    public function penaltyReport(Request $request)
    {
        try {
            $request->validate([
                'zone' => 'nullable|string',
                'bill_month' => 'nullable|string',
                'bill_year' => 'nullable|integer',
            ]);

            $zone = $request->get('zone');
            $billMonth = $request->get('bill_month'); // Format: MM-YYYY or MM
            $billYear = $request->get('bill_year') ?? date('Y');

            // Parse bill month
            $month = null;
            $year = $billYear;
            if ($billMonth) {
                if (strpos($billMonth, '-') !== false) {
                    list($month, $year) = explode('-', $billMonth);
                } else {
                    $month = $billMonth;
                }
            }

            // Query downloaded_readings joined with meter_reading_schedules
            // Base zone and bill_month filters on meter_reading_schedules table
            $query = DB::table('downloaded_readings as dr')
                ->join('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
                ->leftJoin('consumer_zone as cz', function($join) {
                    $join->on(DB::raw('mrs.account_number COLLATE utf8mb4_unicode_ci'), '=', DB::raw('cz.account_no COLLATE utf8mb4_unicode_ci'));
                })
                ->select(
                    'dr.id',
                    'dr.account_number',
                    'dr.account_name',
                    'dr.current_bill as downloaded_current_bill',
                    'dr.consumption',
                    'dr.reading_date',
                    'mrs.bill_month',
                    'mrs.due_date',
                    'mrs.category',
                    'mrs.sedr_number',
                    'mrs.zone',
                    'cz.zone_code',
                    'cz.sequence',
                    'cz.rate_code'
                )
                ->whereNotNull('mrs.due_date');

            // Apply zone filter - base on meter_reading_schedules.zone
            if ($zone && $zone !== '' && $zone !== 'All Zones') {
                $query->where('mrs.zone', $zone);
            }

            // Apply bill month filter - base on meter_reading_schedules.bill_month
            if ($month && $month !== '') {
                $query->whereMonth('mrs.bill_month', $month);
            }
            if ($year) {
                $query->whereYear('mrs.bill_month', $year);
            }

            $readings = $query->orderBy('mrs.due_date')
                             ->orderBy('cz.sequence')
                             ->get();

            if ($readings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No records found for the selected criteria.',
                    'data' => [],
                    'summary' => [
                        'zone' => $zone ?? 'All Zones',
                        'bill_month' => $month ? sprintf('%02d-%d', $month, $year) : 'All Months',
                        'total_penalized' => 0,
                        'total_penalty' => 0,
                    ]
                ]);
            }

            $today = Carbon::today();
            $data = [];
            
            foreach ($readings as $reading) {
                // Get current bill (prioritize downloaded_readings.current_bill)
                $currentBill = $reading->downloaded_current_bill ?? 0;
                
                // Calculate penalty: 10% of current bill if due date has passed
                $dueDate = $reading->due_date ? Carbon::parse($reading->due_date) : null;
                $penalty = 0;
                
                if ($dueDate && $today->greaterThanOrEqualTo($dueDate) && $currentBill > 0) {
                    $penalty = round($currentBill * 0.10, 2);
                }

                // Only include records with penalty > 0
                if ($penalty > 0) {
                    $billMonthDate = $reading->bill_month ? Carbon::parse($reading->bill_month) : null;
                    $penaltyDate = $dueDate ? $dueDate : ($reading->reading_date ? Carbon::parse($reading->reading_date) : $today);
                    
                    $data[] = [
                        'zone_code' => $reading->zone ?? $reading->zone_code ?? '',
                        'bill_month' => $billMonthDate ? $billMonthDate->format('m-Y') : '',
                        'sequence' => $reading->sequence ?? 0,
                        'account_number' => $reading->account_number ?? '',
                        'account_name' => $reading->account_name ?? '',
                        'rate_code' => $reading->rate_code ?? $reading->category ?? 'P1',
                        'date' => $penaltyDate->format('m/d/Y'),
                        'rate_code1' => 'LP', // Late Payment
                        'penalty' => round($penalty, 2),
                        'ref' => 'Late Payment',
                        'sedr' => $reading->sedr_number ?? '',
                    ];
                }
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No penalized consumers found for the selected criteria.',
                    'data' => [],
                    'summary' => [
                        'zone' => $zone ?? 'All Zones',
                        'bill_month' => $month ? sprintf('%02d-%d', $month, $year) : 'All Months',
                        'total_penalized' => 0,
                        'total_penalty' => 0,
                    ]
                ]);
            }

            // Calculate summary
            $totalPenalty = array_sum(array_column($data, 'penalty'));
            
            // Group by zone for summary
            $summaryByZone = [];
            foreach ($data as $record) {
                $zoneKey = $record['zone_code'] ?? 'Unknown';
                if (!isset($summaryByZone[$zoneKey])) {
                    $summaryByZone[$zoneKey] = [
                        'zone' => $zoneKey,
                        'accounts' => 0,
                        'total_penalty' => 0
                    ];
                }
                $summaryByZone[$zoneKey]['accounts']++;
                $summaryByZone[$zoneKey]['total_penalty'] += $record['penalty'];
            }
            
            // Group by penalty type for summary
            $summaryByPenaltyType = [];
            foreach ($data as $record) {
                $penaltyType = $record['rate_code1'] ?? 'LP';
                if (!isset($summaryByPenaltyType[$penaltyType])) {
                    $summaryByPenaltyType[$penaltyType] = [
                        'type' => $penaltyType,
                        'accounts' => 0,
                        'total_amount' => 0
                    ];
                }
                $summaryByPenaltyType[$penaltyType]['accounts']++;
                $summaryByPenaltyType[$penaltyType]['total_amount'] += $record['penalty'];
            }
            
            $summary = [
                'zone' => $zone ?? 'All Zones',
                'bill_month' => $month ? sprintf('%02d-%d', $month, $year) : 'All Months',
                'total_penalized' => count($data),
                'total_penalty' => round($totalPenalty, 2),
                'by_zone' => array_values($summaryByZone),
                'by_penalty_type' => array_values($summaryByPenaltyType),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Penalty report generated successfully.',
                'data' => $data,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Penalty Report Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating penalty report: ' . $e->getMessage(),
                'data' => [],
                'summary' => [
                    'zone' => $request->get('zone') ?? 'All Zones',
                    'bill_month' => 'Error',
                    'total_penalized' => 0,
                    'total_penalty' => 0,
                ]
            ], 500);
        }
    }

    /**
     * Show consumer master list with filters
     */
    public function consumerMasterList(Request $request)
    {
        $filters = $request->only([
            'search',
            'zone',
            'status',
            'senior_citizen',
            'meter_number',
            'address',
            'meter_location',
            'ledger_status',
        ]);

        $zones = ConsumerZoneOne::select('zone_code')
            ->distinct()
            ->orderBy('zone_code')
            ->pluck('zone_code');

        $baseQuery = ConsumerZoneOne::query();

        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('account_name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('account_no', 'like', '%' . $searchTerm . '%');
            });
        }

        if (!empty($filters['zone'])) {
            $baseQuery->where('zone_code', $filters['zone']);
        }

        if (!empty($filters['status'])) {
            $statusValue = $filters['status'];

            $statusMap = [
                'Active' => ['A', 'ACTIVE', 'Active', 'A - ACTIVE'],
                'Inactive' => ['I', 'INACTIVE', 'Inactive'],
                'Suspended' => ['S', 'SUSPENDED', 'Suspended'],
                'Disconnected' => ['D', 'DISCONNECTED', 'Disconnected'],
            ];

            if (isset($statusMap[$statusValue])) {
                $baseQuery->whereIn('status_code', $statusMap[$statusValue]);
            } else {
                $baseQuery->where('status_code', $statusValue);
            }
        }

        if (!empty($filters['senior_citizen']) && Schema::hasColumn('consumer_zone', 'is_senior_citizen')) {
            $baseQuery->where('is_senior_citizen', true);
        }

        if (!empty($filters['meter_number'])) {
            $baseQuery->where('meter_number', 'like', '%' . $filters['meter_number'] . '%');
        }

        if (!empty($filters['address'])) {
            $baseQuery->where(function ($q) use ($filters) {
                $q->where('address1', 'like', '%' . $filters['address'] . '%');

                if (Schema::hasColumn('consumer_zone', 'address_2')) {
                    $q->orWhere('address_2', 'like', '%' . $filters['address'] . '%');
                }
            });
        }

        if (!empty($filters['meter_location']) && Schema::hasColumn('consumer_zone', 'meter_location')) {
            $baseQuery->where('meter_location', 'like', '%' . $filters['meter_location'] . '%');
        }

        if (!empty($filters['ledger_status'])) {
            if ($filters['ledger_status'] === 'missing') {
                $baseQuery->whereDoesntHave('ledgers');
            } elseif ($filters['ledger_status'] === 'imported') {
                $baseQuery->whereHas('ledgers');
            }
        }

        $consumersQuery = (clone $baseQuery)->orderBy('zone_code');

        if (Schema::hasColumn('consumer_zone', 'route')) {
            $consumersQuery->orderBy('route');
        }

        if (Schema::hasColumn('consumer_zone', 'sequence')) {
            $consumersQuery->orderBy('sequence');
        }

        // Eager load ledger count to check if consumer has imported ledger entries
        $consumers = $consumersQuery->withCount('ledgers')->get();

        $summaryByZone = (clone $baseQuery)
            ->select('zone_code as zone', DB::raw('COUNT(*) as total'))
            ->groupBy('zone_code')
            ->orderBy('zone_code')
            ->get();

        return view('reports.system-report.consumer-master-list', [
            'zones' => $zones,
            'consumers' => $consumers,
            'summaryByZone' => $summaryByZone,
            'filters' => $filters,
        ]);
    }

    /**
     * Mark a downloaded reading as paid and store payment details
     */
    public function markDownloadedReadingPaid(Request $request)
    {
        // Check if payment columns exist
        if (!Schema::hasColumn('downloaded_readings', 'paid_at')) {
            return response()->json([
                'success' => false,
                'message' => 'Payment functionality is not available. Please run database migrations first.',
            ], 503);
        }

        $validated = $request->validate([
            'downloaded_id' => 'nullable|exists:downloaded_readings,id',
            'account_number' => 'required_if:downloaded_id,null|nullable|string|max:50',
            'amount_due' => 'required|numeric|min:0',
            'amount_tendered' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
            'official_receipt_number' => 'nullable|string|max:20',
            'is_update' => 'nullable|boolean', // Flag to indicate if this is an update
            'current_bill' => 'nullable|numeric|min:0',
            'senior_citizen_discount' => 'nullable|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'meter_maintenance' => 'nullable|numeric|min:0',
            'arrears_cy' => 'nullable|numeric|min:0',
            'arrears_py' => 'nullable|numeric|min:0',
            'advances' => 'nullable|numeric|min:0',
            'others' => 'nullable|numeric|min:0',
            'materials' => 'nullable|numeric|min:0',
            'fees_charges' => 'nullable|numeric|min:0',
            'inspection_fee' => 'nullable|numeric|min:0',
            'transaction_date' => 'nullable|date', // Payment date (for "paid before due date" and ledger display)
            'pay_months' => 'nullable|integer|in:1,2,3', // Pay 1, 2, or 3 principal months only; penalty/WMC unchanged
            'sundries'   => 'nullable|array',
            'sundries.*.acct_code' => 'required_with:sundries|string|max:50',
            'sundries.*.bam_no'    => 'nullable|string|max:50',
            'sundries.*.amount'    => 'required_with:sundries|numeric|min:0.01',
        ]);

        if ($validated['amount_tendered'] < $validated['amount_due']) {
            return response()->json([
                'success' => false,
                'message' => 'Amount tendered must be equal to or greater than the amount due.',
            ], 422);
        }

        try {
            $paymentDetails = DB::transaction(function () use ($validated) {
                /** @var \App\Models\DownloadedReading|null $downloaded */
                $downloaded = null;
                $accountNumber = $validated['account_number'] ?? null;
                if (!empty($validated['downloaded_id'])) {
                    $downloaded = DownloadedReading::lockForUpdate()
                        ->with('schedule')
                        ->findOrFail($validated['downloaded_id']);
                    $accountNumber = $downloaded->account_number ?? $accountNumber;
                }

                $isUpdate = $validated['is_update'] ?? false;
                $consumer = null;
                $consumerId = null;
                $outstandingBalance = null;

                if ($accountNumber) {
                    $consumer = ConsumerZoneOne::where('account_no', $accountNumber)->first();
                    if (!$consumer) {
                        $normalized = str_replace('-', '', $accountNumber);
                        $consumer = ConsumerZoneOne::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
                    }
                    if (!$consumer && $downloaded && $downloaded->schedule && !empty($downloaded->schedule->account_number)) {
                        $scheduleAccount = trim($downloaded->schedule->account_number);
                        $consumer = ConsumerZoneOne::where('account_no', $scheduleAccount)->first();
                        if (!$consumer) {
                            $consumer = ConsumerZoneOne::whereRaw("REPLACE(account_no, '-', '') = ?", [str_replace('-', '', $scheduleAccount)])->first();
                        }
                    }
                    if ($consumer) {
                        $consumerId = $consumer->id;
                        $latestLedgerEntry = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                            ->whereNotNull('balance')
                            ->orderBy('date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();
                        $outstandingBalance = $latestLedgerEntry ? (float)($latestLedgerEntry->balance ?? 0) : (float)($consumer->balance ?? 0);
                        // Allow partial payments: payment is recorded regardless of outstanding balance.
                    }
                }

                // If no specific reading was provided but we have a consumer, use their latest downloaded reading so reading_id is set
                if (!$downloaded && $consumerId) {
                    $consumer = $consumer ?? ConsumerZoneOne::find($consumerId);
                    if ($consumer && !empty(trim($consumer->account_no ?? ''))) {
                        $accountNo = trim($consumer->account_no);
                        $normalized = str_replace('-', '', $accountNo);
                        $downloaded = DownloadedReading::lockForUpdate()
                            ->with('schedule')
                            ->where('account_number', $accountNo)
                            ->orWhereRaw("REPLACE(account_number, '-', '') = ?", [$normalized])
                            ->orderBy('reading_date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();
                    }
                }

                if ($downloaded) {
                    $existingPaidPayment = ConsumerPayment::where('reading_id', $downloaded->id)->whereNotNull('paid_at')->first();
                    if (!$isUpdate && $existingPaidPayment) {
                        $existingOrNumber = $existingPaidPayment->or_number ? " (OR #{$existingPaidPayment->or_number})" : '';
                        $paidDate = $existingPaidPayment->paid_at ? Carbon::parse($existingPaidPayment->paid_at)->format('M d, Y') : '';
                        throw new \Exception(
                            "Payment already exists for account number {$downloaded->account_number}{$existingOrNumber}. " .
                            "Payment was recorded on {$paidDate}. " .
                            "Use edit mode to update existing payments."
                        );
                    }
                }

                if ($validated['official_receipt_number']) {
                    $q = ConsumerPayment::where('or_number', $validated['official_receipt_number']);
                    if ($downloaded) {
                        $q->where('reading_id', '!=', $downloaded->id);
                    }
                    if ($q->exists()) {
                        throw new \Exception(
                            "Official Receipt Number {$validated['official_receipt_number']} is already in use by another payment. Please use a different OR number."
                        );
                    }
                }

                $change = max(0, round($validated['amount_tendered'] - $validated['amount_due'], 2));
                $now = Carbon::now();
                // Use transaction_date as paid_at when provided (e.g. backdated payment), else now
                $paidAt = !empty($validated['transaction_date'])
                    ? Carbon::parse($validated['transaction_date'])->setTimeFromTimeString($now->format('H:i:s'))
                    : $now;

                // Update or create consumer_payment; store full breakdown so all particulars are in DB
                $paymentData = [
                    'consumer_id' => $consumerId,
                    'payment_method' => $validated['payment_method'],
                    'payment_amount' => round($validated['amount_due'], 2),
                    'amount_tendered' => round($validated['amount_tendered'], 2),
                    'change_amount' => $change,
                    'senior_citizen_discount' => round($validated['senior_citizen_discount'] ?? 0, 2),
                    'current_bill' => round($validated['current_bill'] ?? 0, 2),
                    'penalty' => round($validated['penalty'] ?? 0, 2),
                    'meter_maintenance' => round($validated['meter_maintenance'] ?? 0, 2),
                    'arrears_cy' => round($validated['arrears_cy'] ?? 0, 2),
                    'arrears_py' => round($validated['arrears_py'] ?? 0, 2),
                    'advances' => round($validated['advances'] ?? 0, 2),
                    'others' => round($validated['others'] ?? 0, 2),
                    'materials' => round($validated['materials'] ?? 0, 2),
                    'fees_charges' => round($validated['fees_charges'] ?? 0, 2),
                    'inspection_fee' => round($validated['inspection_fee'] ?? 0, 2),
                    'or_number' => $validated['official_receipt_number'] ?? null,
                    'paid_at' => $paidAt,
                    'remarks' => $validated['remarks'] ?? null,
                    'created_by' => $this->getFormattedUserName(),
                ];
                if ($downloaded) {
                    $consumerPayment = ConsumerPayment::updateOrCreate(
                        ['reading_id' => $downloaded->id],
                        array_merge($paymentData, ['reading_id' => $downloaded->id])
                    );
                } else {
                    // New accounts (no meter reading yet): allow payment with reading_id null
                    $consumerPayment = ConsumerPayment::create(array_merge($paymentData, ['reading_id' => null]));
                }

                // Whenever a consumer has a payment (consumer_payments.paid_at), cancel active disconnection orders
                // so the disconnector is notified not to disconnect—even if balance is not fully cleared.
                if ($consumerId && $consumerPayment && $consumerPayment->paid_at) {
                    $cancelled = DisconnectionOrder::cancelActiveOrdersForConsumerDueToPayment($consumerId, $consumerPayment->paid_at);
                    if ($cancelled > 0) {
                        Log::info('Disconnection orders cancelled due to payment', [
                            'consumer_id' => $consumerId,
                            'account' => $accountNumber ?? null,
                            'cancelled_count' => $cancelled,
                        ]);
                    }
                } elseif ($consumerPayment && $consumerPayment->paid_at && !$consumerId) {
                    Log::warning('Disconnection cancel skipped: payment saved but consumer not resolved', [
                        'account_number' => $accountNumber ?? null,
                        'downloaded_id' => $downloaded?->id,
                    ]);
                }

                // Apply payment to unpaid charge rows (paid_at is the only truth)
                // Skip on update: only for new payments so we don't touch already-paid ledger rows.
                // Order: PY (previous year) billing first, then penalty (ledger + Penalty model), then CY (current year) billing (respect pay_months).
                if (!$isUpdate && $consumerId) {
                    $remaining = (float)($validated['amount_due'] ?? 0);
                    $payMonths = isset($validated['pay_months']) ? (int) $validated['pay_months'] : null;
                    $maxBillingRowsToMark = ($payMonths >= 1 && $payMonths <= 3) ? $payMonths : PHP_INT_MAX;

                    $unpaidBillingRows = ConsumerLedger::where('consumer_zone_id', $consumerId)
                        ->whereIn('trans', ['BILLING', 'BILL'])
                        ->where('billamount', '>', 0)
                        ->whereNull('paid_at')
                        ->with('schedule:id,bill_month,due_date')
                        ->orderBy('date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();
                    $currentYear = $unpaidBillingRows->isEmpty()
                        ? (int) Carbon::now()->format('Y')
                        : (int) $unpaidBillingRows->max(fn ($row) => $this->getBillMonthFromRow($row) ? (int) $this->getBillMonthFromRow($row)->format('Y') : (int) date('Y'));
                    $pyBilling = $unpaidBillingRows->filter(fn ($row) => ($this->getBillMonthFromRow($row)?->format('Y') ?? $currentYear) < $currentYear);
                    $cyBilling = $unpaidBillingRows->filter(fn ($row) => ($this->getBillMonthFromRow($row)?->format('Y') ?? $currentYear) >= $currentYear);

                    // 1) PY: set paid_at for previous-year unpaid BILLING rows when fully covered
                    /** @var ConsumerLedger $billingRow */
                    foreach ($pyBilling as $billingRow) {
                        $principal = (float)($billingRow->billamount ?? 0);
                        $wmc = (float)($billingRow->others ?? 0);
                        $totalCharge = $principal + $wmc;
                        if ($totalCharge <= 0) continue;
                        if ($remaining + 0.009 >= $totalCharge) {
                            $billingRow->paid_at = $paidAt;
                            $billingRow->save();
                            $remaining -= $totalCharge;
                        } else {
                            break;
                        }
                    }

                    // 2) Penalty: ConsumerLedger PENALTY rows
                    $unpaidPenaltyRows = ConsumerLedger::where('consumer_zone_id', $consumerId)
                        ->where('trans', 'PENALTY')
                        ->where(function ($q) {
                            $q->where('penalty', '>', 0)->orWhere('debit', '>', 0);
                        })
                        ->whereNull('paid_at')
                        ->orderBy('date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();
                    foreach ($unpaidPenaltyRows as $penaltyRow) {
                        $amt = (float)($penaltyRow->penalty ?? 0);
                        if ($amt <= 0) $amt = (float)($penaltyRow->debit ?? 0);
                        if ($amt <= 0) continue;
                        if ($remaining + 0.009 >= $amt) {
                            $penaltyRow->paid_at = $paidAt;
                            $penaltyRow->save();
                            $remaining -= $amt;
                        } else {
                            break;
                        }
                    }

                    // 3) Penalty model: set paid_at when fully covered
                    $unpaidPenaltyModels = Penalty::where('consumer_zone_id', $consumerId)->whereNull('paid_at')->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
                    foreach ($unpaidPenaltyModels as $p) {
                        $amt = (float)($p->penalty_amount ?? 0);
                        if ($amt <= 0) continue;
                        if ($remaining + 0.009 >= $amt) {
                            $p->paid_at = $paidAt;
                            $p->save();
                            $remaining -= $amt;
                        } else {
                            break;
                        }
                    }

                    // 4) CY: set paid_at for current-year unpaid BILLING rows (respect pay_months)
                    $billingRowsMarked = 0;
                    foreach ($cyBilling as $billingRow) {
                        if ($billingRowsMarked >= $maxBillingRowsToMark) break;
                        $principal = (float)($billingRow->billamount ?? 0);
                        $wmc = (float)($billingRow->others ?? 0);
                        $totalCharge = $principal + $wmc;
                        if ($totalCharge <= 0) continue;
                        if ($remaining + 0.009 >= $totalCharge) {
                            $billingRow->paid_at = $paidAt;
                            $billingRow->save();
                            $remaining -= $totalCharge;
                            $billingRowsMarked++;
                        } else {
                            break;
                        }
                    }
                }

                // Determine if this payment should mark the status as 'paid'
                // Only mark as 'paid' if ALL remaining balance (including current bill) is cleared
                // Recalculate balance AFTER saving payment to get accurate balance
                $shouldMarkAsPaid = false;
                $accountForBalance = $downloaded ? $downloaded->account_number : ($validated['account_number'] ?? null);
                if ($consumer && $accountForBalance) {
                    // Get accurate current balance AFTER payment using ledger controller
                    // This includes the payment we just saved and the current bill if unpaid
                    $balanceAfterPayment = 0;
                    try {
                        $ledgerController = new \App\Http\Controllers\ConsumerLedgerController();
                        $ledgerRequest = new \Illuminate\Http\Request();
                        $ledgerRequest->merge([
                            'account_no' => $accountForBalance,
                            'year' => '' // Get all records for accurate balance calculation
                        ]);
                        
                        $ledgerResponse = $ledgerController->getLedger($ledgerRequest);
                        $ledgerData = json_decode($ledgerResponse->getContent(), true);
                        
                        if (isset($ledgerData['summary']['balance'])) {
                            $balanceAfterPayment = (float)$ledgerData['summary']['balance'];
                        } else {
                            // Fallback: calculate from outstandingBalance
                            $balanceAfterPayment = ($outstandingBalance ?? 0) - $validated['amount_due'];
                        }
                    } catch (\Exception $e) {
                        // Fallback: calculate from outstandingBalance
                        Log::error('Error getting balance from ledger in payment processing: ' . $e->getMessage());
                        $balanceAfterPayment = ($outstandingBalance ?? 0) - $validated['amount_due'];
                    }
                    
                    // Only mark as 'paid' if ALL remaining balance is cleared (balance <= 0.01 after payment)
                    // This is especially important when paying past bills (like November) - don't mark as paid
                    // unless the current bill is also fully paid (balance after payment must be <= 0.01)
                    if ($balanceAfterPayment <= 0.01) {
                        // All balance is cleared (including current bill if it exists), can mark as paid
                        $shouldMarkAsPaid = true;
                    } else {
                        // Still has outstanding balance (including current bill if unpaid)
                        // Don't mark as paid - this ensures past bills (like November) don't get marked as paid
                        // when current bill is still unpaid
                        $shouldMarkAsPaid = false;
                    }
                } else {
                    // If no consumer found, default to marking as paid (fallback behavior)
                    $shouldMarkAsPaid = true;
                }

                // Only update status-related fields on downloaded_readings when we have a downloaded reading
                if ($downloaded) {
                if ($shouldMarkAsPaid) {
                    $downloaded->status = 'paid';
                    $downloaded->paid_at = $paidAt;
                } else {
                    // Keep status as 'completed' (not 'paid') if balance is not fully cleared
                    if ($downloaded->status !== 'completed') {
                        $downloaded->status = 'completed';
                    }
                }
                $downloaded->prepared_by = $this->getFormattedUserName();
                if (!$downloaded->completed_at) {
                    $downloaded->completed_at = $now;
                }
                $downloaded->save();

                if ($downloaded->schedule) {
                    $schedule = $downloaded->schedule;
                    $schedule->status = 'Completed';
                    $schedule->completed_at = $schedule->completed_at ?? $now;
                    $schedule->save();

                    $dueDate = $schedule->due_date ? Carbon::parse($schedule->due_date) : null;
                    if ($dueDate && $paidAt->lte($dueDate->endOfDay())) {
                        $billAmount = (float)($schedule->current_bill ?? $downloaded->current_bill ?? 0);
                        $totalPaid = ConsumerPayment::where('reading_id', $downloaded->id)
                            ->whereNotNull('paid_at')
                            ->whereDate('paid_at', '<=', $dueDate->format('Y-m-d'))
                            ->selectRaw('COALESCE(SUM(payment_amount + COALESCE(senior_citizen_discount, 0)), 0) as total')
                            ->value('total');
                        if ($billAmount > 0 && $totalPaid + 0.01 >= $billAmount && $consumerId) {
                            $penaltiesToDelete = Penalty::where('consumer_zone_id', $consumerId)
                                ->where('schedule_id', $schedule->id)
                                ->pluck('id');
                            if ($penaltiesToDelete->isNotEmpty()) {
                                Penalty::whereIn('id', $penaltiesToDelete)->delete();
                                ConsumerLedger::where('consumer_zone_id', $consumerId)
                                    ->where('trans', 'PENALTY')
                                    ->whereIn('schedule_id', [$schedule->id])
                                    ->delete();
                            }
                        }
                    }
                }
                }

                // Separate sundries total from the water-bill payment so only the
                // bill portion is credited to consumer_ledgers; sundries go to lro_ledger only.
                $sundriesTotal = 0;
                foreach ($validated['sundries'] ?? [] as $s) {
                    $sundriesTotal += round((float)($s['amount'] ?? 0), 2);
                }
                $billPaymentAmount = max(0, round($validated['amount_due'] - $sundriesTotal, 2));

                // Create or update ConsumerLedger PAYMENT (bill portion only; skip for sundry-only payments)
                if ($consumerId && $billPaymentAmount > 0) {
                    $newBalance = ($outstandingBalance ?? 0) - $billPaymentAmount;
                    $orNumber = $validated['official_receipt_number'] ?? ($downloaded ? 'Payment DR#' . $downloaded->id : 'Payment ACCT#' . ($validated['account_number'] ?? ''));
                    $ledgerNow = Carbon::now();
                    $readingId = $downloaded?->id;
                    $scheduleId = $downloaded?->schedule_id;
                    if ($downloaded && !$isUpdate) {
                        ConsumerLedger::where('consumer_zone_id', $consumerId)
                            ->where('trans', 'PAYMENT')
                            ->where('downloaded_reading_id', $downloaded->id)
                            ->where('reference', 'like', '%-SC')
                            ->delete();
                    }
                    $ledgerPayload = [
                        'consumer_zone_id' => $consumerId,
                        'consumer_payment_id' => $consumerPayment->id,
                        'schedule_id' => $scheduleId,
                        'date' => $ledgerNow->format('Y-m-d'),
                        'due_date' => null,
                        'reference' => $orNumber,
                        'reading' => null,
                        'volume' => null,
                        'billamount' => 0,
                        'penalty' => round((float)($validated['penalty'] ?? 0), 2),
                        'others' => 0,
                        'debit' => 0,
                        'credit' => $billPaymentAmount,
                        'balance' => round($newBalance, 2),
                        'username' => $this->getFormattedUserName(),
                        'txtime' => $ledgerNow->format('Y-m-d H:i:s'),
                        'paid_at' => $ledgerNow,
                    ];
                    if ($readingId !== null) {
                        ConsumerLedger::updateOrCreate(
                            [
                                'consumer_zone_id' => $consumerId,
                                'trans' => 'PAYMENT',
                                'downloaded_reading_id' => $readingId,
                            ],
                            $ledgerPayload
                        );
                    } else {
                        ConsumerLedger::create(array_merge($ledgerPayload, [
                            'trans' => 'PAYMENT',
                            'downloaded_reading_id' => null,
                        ]));
                    }

                    $scDiscount = round($validated['senior_citizen_discount'] ?? 0, 2);
                    if ($scDiscount > 0) {
                        if ($isUpdate && $downloaded && $readingId !== null) {
                            // Update existing -SC row only; do not create new ledger line
                            ConsumerLedger::updateOrCreate(
                                [
                                    'consumer_zone_id' => $consumerId,
                                    'trans' => 'PAYMENT',
                                    'downloaded_reading_id' => $readingId,
                                    'reference' => $orNumber . '-SC',
                                ],
                                [
                                    'consumer_payment_id' => $consumerPayment->id,
                                    'schedule_id' => $downloaded->schedule_id,
                                    'date' => $ledgerNow->format('Y-m-d'),
                                    'due_date' => null,
                                    'reading' => null,
                                    'volume' => null,
                                    'billamount' => 0,
                                    'penalty' => 0,
                                    'others' => 0,
                                    'debit' => 0,
                                    'credit' => $scDiscount,
                                    'balance' => round($newBalance - $scDiscount, 2),
                                    'username' => $this->getFormattedUserName(),
                                    'txtime' => $ledgerNow->format('Y-m-d H:i:s'),
                                    'paid_at' => $ledgerNow,
                                ]
                            );
                        } else {
                            ConsumerLedger::create([
                                'consumer_zone_id' => $consumerId,
                                'schedule_id' => $downloaded ? $downloaded->schedule_id : null,
                                'downloaded_reading_id' => $downloaded ? $downloaded->id : null,
                                'trans' => 'PAYMENT',
                                'date' => $ledgerNow->format('Y-m-d'),
                                'due_date' => null,
                                'reference' => $orNumber . '-SC',
                                'reading' => null,
                                'volume' => null,
                                'billamount' => 0,
                                'penalty' => 0,
                                'others' => 0,
                                'debit' => 0,
                                'credit' => $scDiscount,
                                'balance' => round($newBalance - $scDiscount, 2),
                                'username' => $this->getFormattedUserName(),
                                'txtime' => $ledgerNow->format('Y-m-d H:i:s'),
                                'paid_at' => $ledgerNow,
                            ]);
                        }
                    }
                }

                // Create LRO credit (CM) rows for any sundries included in this payment.
                // Skip on update: do not create new LRO ledger lines when only updating payment data.
                // Each CM row nets against the original DM row so the LRO Summary shows Payments = Charges → Balance = 0.
                if (!$isUpdate && !empty($validated['sundries']) && $accountNumber) {
                    $consumerName = $consumer ? ($consumer->account_name ?? '') : '';
                    foreach ($validated['sundries'] as $sundry) {
                        $sundryAmount = round((float)($sundry['amount'] ?? 0), 2);
                        $sundryAcctCode = $sundry['acct_code'] ?? null;
                        if ($sundryAmount <= 0 || !$sundryAcctCode) {
                            continue;
                        }
                        LROLedger::create([
                            'account'   => $accountNumber,
                            'type'      => 'CM',
                            'date'      => $paidAt->format('Y-m-d'),
                            'bam_no'    => $sundry['bam_no'] ?? null,
                            'amount'    => $sundryAmount,
                            'ar_type'   => 'LRO',
                            'acct_code' => $sundryAcctCode,
                            'name'      => $consumerName,
                            'reference' => $sundry['bam_no'] ?? null,
                            'remarks'   => 'Payment OR#' . ($validated['official_receipt_number'] ?? ''),
                            'status'    => 'Posted',
                        ]);
                    }
                }

                return [
                    'downloaded_id' => $downloaded?->id,
                    'status' => 'Paid',
                    'status_code' => 'paid',
                    'paid_at' => $consumerPayment->paid_at?->format('Y-m-d H:i:s'),
                    'payment_method' => $consumerPayment->payment_method,
                    'payment_amount' => (float) $consumerPayment->payment_amount,
                    'amount_tendered' => (float) $consumerPayment->amount_tendered,
                    'change_amount' => (float) $consumerPayment->change_amount,
                    'payment_reference' => null,
                    'payment_remarks' => $consumerPayment->remarks,
                    'official_receipt_number' => $consumerPayment->or_number,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'data' => $paymentDetails,
            ]);
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            $isNoPaymentRecord = str_contains($message, 'Payment cannot be created automatically');
            return response()->json([
                'success' => false,
                'message' => $isNoPaymentRecord ? $message : 'Failed to record payment: ' . $message,
            ], $isNoPaymentRecord ? 422 : 500);
        }
    }

    /**
     * Get formatted username from authenticated user
     * Format: LAST_NAME, FIRST_NAME M. EXTENSION
     */
    private function getFormattedUserName()
    {
        $user = Auth::user();
        
        if (!$user) {
            return 'SYSTEM';
        }

        // Format: LAST_NAME, FIRST_NAME M. EXTENSION
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
     * Extract first name from a formatted name (e.g., "DELA CRUZ, JUAN M." -> "JUAN")
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
}

