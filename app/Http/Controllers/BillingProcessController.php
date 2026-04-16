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
use App\Imports\DmLedgerImport;
use Maatwebsite\Excel\Facades\Excel;

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

    
    
    // * Prepare meter reading data for:
    //  * - Meter Reading Preparation: zone + dates → active consumers in zone only (excludes disconnected/inactive); can_save only when no existing schedules.
    //  * - Single Consumer: account_no + zone + dates → that consumer (any status); can_save always; allows adding even if batch exists.
    //  * - Multiple Consumers: account_numbers + dates → those consumers (any status); can_save always; allows adding even if batch exists.
    //  */
    public function prepareMeterReading(Request $request)
    {
        $request->validate([
            'zone' => 'nullable|string',
            'account_no' => 'nullable|string',
            'account_numbers' => 'nullable|array',
            'account_numbers.*' => 'string',
            'bill_month' => 'required|date',
            'bill_date' => 'required|date',
            'due_date' => 'required|date',
            'disconnection_date' => 'required|date',
        ]);

        $billMonth = Carbon::parse($request->bill_month);
        $billDate = Carbon::parse($request->bill_date);
        $dueDate = Carbon::parse($request->due_date);
        $disconnectionDate = Carbon::parse($request->disconnection_date);
        $billMonthYmd = $billMonth->format('Y-m-d');

        $zone = $request->zone ? trim($request->zone) : null;
        $accountNo = $request->account_no ? trim($request->account_no) : null;
        $accountNumbers = $request->account_numbers ? array_values(array_filter(array_map('trim', $request->account_numbers))) : null;

        // Determine mode: zone-only (active only), single account (any status), or multiple accounts (any status)
        $isSingle = $accountNo !== null && $accountNo !== '';
        $isMultiple = $accountNumbers !== null && count($accountNumbers) > 0;
        $isAccountsScope = $isSingle || $isMultiple;

        $consumers = collect();
        $effectiveZone = $zone;

        if ($isSingle) {
            $consumer = ConsumerZoneOne::where(function ($q) use ($accountNo) {
                $q->where('account_no', $accountNo)
                  ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [str_replace('-', '', $accountNo)]);
            })->first();
            if (!$consumer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account number not found: ' . $accountNo,
                ], 404);
            }
            $consumers = collect([$consumer]);
            $effectiveZone = $consumer->zone_code ?? $zone;
        } elseif ($isMultiple) {
            $normalizedAccounts = array_map(function ($a) {
                return str_replace('-', '', $a);
            }, $accountNumbers);
            $consumers = ConsumerZoneOne::where(function ($q) use ($accountNumbers, $normalizedAccounts) {
                foreach ($accountNumbers as $i => $acc) {
                    $norm = $normalizedAccounts[$i] ?? str_replace('-', '', $acc);
                    $q->orWhere('account_no', $acc)
                      ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$norm]);
                }
            })->get();
            if ($consumers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No consumers found for the given account numbers.',
                ], 404);
            }
            $effectiveZone = $consumers->first()->zone_code ?? $zone;
        } else {
            if (!$zone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zone is required for Meter Reading Preparation.',
                ], 422);
            }
            // Zone-only: only ACTIVE consumers (exclude disconnected / inactive)
            $consumers = ConsumerZoneOne::where('zone_code', $zone)
                ->whereIn(DB::raw('UPPER(TRIM(COALESCE(status_code, "")))'), ['A', 'ACTIVE'])
                ->orderBy('sequence')
                ->orderBy('account_no')
                ->get();
        }

        $existingCount = 0;
        if ($effectiveZone) {
            $existingCount = MeterReadingSchedule::where('zone', $effectiveZone)
                ->where('bill_month', $billMonthYmd)
                ->count();
        }
        if ($isAccountsScope) {
            $existingForAccounts = MeterReadingSchedule::whereIn('account_number', $consumers->pluck('account_no')->filter()->unique()->values()->all())
                ->where('bill_month', $billMonthYmd)
                ->count();
            $existingCount = $existingForAccounts;
        }

        // can_save: zone-only = only when no existing schedules; single/multiple = always (additive allowed)
        $canSave = $isAccountsScope ? true : ($existingCount === 0);
        $sedr = 1;
        $data = [];
        $ledgerBalanceYear = $billMonth->format('Y');
        foreach ($consumers as $consumer) {
            $accountNoVal = $consumer->account_no ?? '';
            $previousReading = $this->getPreviousReading($accountNoVal);
            $currentBill = 0.00;
            $wmc = 0.00;
          //  $arrears = (float) ($previousReading['arrears'] ?? 0);
               // Must match consumer ledger footer Current Balance (same year as bill month)
           // $arrears = ConsumerLedgerController::computeLedgerFooterBalance((int) $consumer->id, $ledgerBalanceYear);
               $arrears = ConsumerLedgerController::computeLedgerFooterBalance((int) $consumer->id, $ledgerBalanceYear);
            // Meter Reading Preparation (zone) and (Single Consumer): arrears must not be negative; Multiple Consumers path unchanged
            if (!$isMultiple) {
                $arrears = max(0.0, (float) $arrears);
            }  
            $total = $currentBill + $wmc + $arrears;
            $data[] = [
                'sedr' => (string) $sedr++,
                'account_number' => $accountNoVal,
                'account_name' => $consumer->account_name ?? '',
                'address' => $consumer->address1 ?? '',
                'zone' => $consumer->zone_code ?? $effectiveZone ?? '',
                'category' => $consumer->category_code ?? '',
                'meter_number' => $consumer->meter_number ?? '',
                'bill_month' => $billMonthYmd,
                'bill_date' => $billDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'disconnection_date' => $disconnectionDate->format('Y-m-d'),
                'prev_date' => $previousReading['date'],
                'prev_read' => $previousReading['reading'],
                'pres_read' => 0,
                'volume' => $previousReading['volume'],
                'current_bill' => $currentBill,
                'water_maintenance_charge' => $wmc,
                'arrears' => $arrears,
                'total' => $total,
                'status' => $consumer->status_label ?? 'Active',
                'consumer_zone_id' => $consumer->id,
            ];
        }

        $summary = [
            'zone' => $effectiveZone ?? $zone ?? '—',
            'bill_month' => $billMonth->format('F Y'),
            'existing_schedules' => $existingCount,
        ];

        return response()->json([
            'success' => true,
            'message' => $isAccountsScope
                ? count($data) . ' consumer(s) prepared. You can save even if schedules already exist for this period (additive).'
                : count($data) . ' active consumer(s) prepared for Zone ' . ($effectiveZone ?? $zone) . '.',
            'data' => $data,
            'summary' => $summary,
            'can_save' => $canSave && count($data) > 0,
        ]);
    }

    /**
     * Save prepared meter reading schedules to database.
     * When save_scope = 'accounts' (Single/Multiple Consumer), allows saving even if zone+bill_month already has schedules (additive).
     */
    public function saveMeterReadingSchedules(Request $request)
    {
        $request->validate([
            'schedules' => 'required|array',
            'schedules.*.consumer_zone_id' => 'nullable|exists:consumer_zone,id',
            'schedules.*.account_number' => 'required|string',
            'schedules.*.zone' => 'required|string',
            'schedules.*.bill_month' => 'required|date',
            'save_scope' => 'nullable|string|in:zone,accounts',
        ]);

        try {
            $zone = $request->schedules[0]['zone'];
            $billMonth = Carbon::parse($request->schedules[0]['bill_month']);
            $saveScope = $request->input('save_scope', 'zone');

            // When save_scope = 'zone' (default): block if any schedules exist for this zone+bill_month.
            // When save_scope = 'accounts' (Single/Multiple Consumer): allow additive save; skip per-account if that account already has a schedule for this period.
            $existingSchedules = MeterReadingSchedule::where('zone', $zone)
                ->where('bill_month', $billMonth->format('Y-m-d'))
                ->count();

            if ($saveScope !== 'accounts' && $existingSchedules > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedules already exist for Zone ' . $zone . ' for ' . $billMonth->format('F Y') . '. Please delete existing schedules first.',
                    'existing_count' => $existingSchedules
                ], 422);
            }

            $savedSchedules = [];
            $billDateYmd = isset($request->schedules[0]['bill_date']) ? Carbon::parse($request->schedules[0]['bill_date'])->format('Y-m-d') : null;
            $dueDateYmd = isset($request->schedules[0]['due_date']) ? Carbon::parse($request->schedules[0]['due_date'])->format('Y-m-d') : null;
            $disconnectionDateYmd = isset($request->schedules[0]['disconnection_date']) ? Carbon::parse($request->schedules[0]['disconnection_date'])->format('Y-m-d') : null;

            // Get the authenticated user's formatted name
            $preparedBy = $this->getFormattedUserName();

            DB::beginTransaction();
            try {
                foreach ($request->schedules as $scheduleData) {
                    // Additive (save_scope = 'accounts'): skip if this account already has a schedule for this zone+period to avoid duplicates
                    if ($saveScope === 'accounts') {
                        $billDateRow = isset($scheduleData['bill_date']) ? Carbon::parse($scheduleData['bill_date'])->format('Y-m-d') : $billDateYmd;
                        $dueDateRow = isset($scheduleData['due_date']) ? Carbon::parse($scheduleData['due_date'])->format('Y-m-d') : $dueDateYmd;
                        $disconnectionDateRow = isset($scheduleData['disconnection_date']) ? Carbon::parse($scheduleData['disconnection_date'])->format('Y-m-d') : $disconnectionDateYmd;
                        $alreadyExists = MeterReadingSchedule::where('zone', $scheduleData['zone'])
                            ->where('bill_month', $billMonth->format('Y-m-d'))
                            ->where('bill_date', $billDateRow)
                            ->where('due_date', $dueDateRow)
                            ->where('disconnection_date', $disconnectionDateRow)
                            ->where(function ($q) use ($scheduleData) {
                                $acc = $scheduleData['account_number'];
                                $q->where('account_number', $acc)
                                  ->orWhereRaw("REPLACE(account_number, '-', '') = ?", [str_replace('-', '', $acc)]);
                            })
                            ->exists();
                        if ($alreadyExists) {
                            continue; // skip duplicate account for this period
                        }
                    }

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

                    // Previous reading: use edited value from UI (prev_read) — same logic for all process types (Meter Reading Preparation / Single / Multiple)
                    $prevRead = isset($scheduleData['prev_read'])
                        ? (is_numeric($scheduleData['prev_read']) ? (float) $scheduleData['prev_read'] : 0)
                        : 0;
                    
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
                        'previous_reading' => $prevRead,
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
                            'reading' => $prevRead, // Same as meter_reading_schedules.previous_reading (edited Prev. Read from UI)
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

                $savedCount = count($savedSchedules);
                $message = $savedCount > 0
                    ? 'Successfully saved ' . $savedCount . ' meter reading schedule(s) to database!'
                    : ($saveScope === 'accounts'
                        ? 'No new schedules saved; all selected account(s) already have a schedule for this period.'
                        : 'Successfully saved ' . $savedCount . ' meter reading schedule(s) to database!');

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'saved_count' => $savedCount,
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
     * Billing breakdown from consumer_ledgers + consumer_payments.
     *
     * A charge row is unpaid until covered by a consumer_payments record (reading_id / schedule).
     * Isolate latest unpaid BILLING first: billamount → Current Bill; others on that row only → WMC.
     * Unpaid PENALTY → Penalty (skip if already paid).
     * All other unpaid amounts → Arrears CY/PY by year (older billings’ billamount+others, DM debits, etc.).
     * DM: unpaid debit → Arrears CY or PY by row date year.
     * PENALTY: unpaid PENALTY rows (penalties.paid_at or payment penalty allocation when applicable).
     * viewType is kept for API compatibility; classification uses calendar year vs latest unpaid billing date.
     *
     * @param int $consumerId consumer_zone_id
     * @param string $viewType legacy: pre_due|post_due
     * @param \Carbon\Carbon|null $asOfDate payment / billing date context
     * @param int|null $payMonths optional Pay N months amount
     * @param string|null $selectedBillMonthYmd selected bill month Y-m (cutoff = end of that month when set)
     * @return array { current_bill, penalty, water_maintenance_charge, arrears_cy, arrears_py, advances, unpaid_count, past_due_count, amount_due? }
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
    
    /**
     * True when a payment in consumer_payments (paid_at set) covers this schedule/reading.
     */
    private function isScheduleCoveredByConsumerPayment(int $consumerZoneId, ?int $downloadedReadingId, ?int $scheduleId): bool
    {
        if ($downloadedReadingId) {
            $coveredByReading = ConsumerPayment::where('consumer_id', $consumerZoneId)
                ->where('reading_id', $downloadedReadingId)
                ->whereNotNull('paid_at')
                ->exists();
            if ($coveredByReading) {
                return true;
            }
            $reading = DownloadedReading::find($downloadedReadingId);
            $derivedScheduleId = $reading ? (int) ($reading->schedule_id ?? 0) : 0;
            if ($derivedScheduleId > 0 && $this->hasNullReadingPaymentForScheduleMonth($consumerZoneId, $derivedScheduleId)) {
                return true;
            }
            return false;
        }
        if ($scheduleId) {
            $readingIds = DownloadedReading::where('schedule_id', $scheduleId)->pluck('id');
            if ($readingIds->isNotEmpty()) {
                $coveredByReading = ConsumerPayment::where('consumer_id', $consumerZoneId)
                    ->whereIn('reading_id', $readingIds)
                    ->whereNotNull('paid_at')
                    ->exists();
                if ($coveredByReading) {
                    return true;
                }
            }

            return $this->hasNullReadingPaymentForScheduleMonth($consumerZoneId, (int) $scheduleId);
        }

        return false;
    }

    /**
     * Legacy payment fallback: treat null-reading payments as coverage for the same schedule month.
     */
    private function hasNullReadingPaymentForScheduleMonth(int $consumerZoneId, int $scheduleId): bool
    {
        [$start, $end] = $this->getScheduleMonthRange($scheduleId);
        if (!$start || !$end) {
            return false;
        }

        return ConsumerPayment::where('consumer_id', $consumerZoneId)
            ->whereNull('reading_id')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')])
            ->exists();
    }

    /**
     * Legacy payment fallback for penalty allocation: sum penalty on null-reading payments in schedule month.
     */
    private function getNullReadingPenaltyPaidForScheduleMonth(int $consumerZoneId, int $scheduleId, ?Carbon $penaltyDate = null): float
    {
        [$start, $end] = $this->getScheduleMonthRange($scheduleId);
        if (!$start || !$end) {
            return 0.0;
        }

        $query = ConsumerPayment::where('consumer_id', $consumerZoneId)
            ->whereNull('reading_id')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]);
        if ($penaltyDate) {
            $query->where('paid_at', '>=', $penaltyDate->copy()->startOfDay()->format('Y-m-d H:i:s'));
        }

        return (float) $query->sum('penalty');
    }

    /**
     * Returns [monthStart, monthEnd] based on schedule bill_month (fallback: due_date/date).
     */
    private function getScheduleMonthRange(int $scheduleId): array
    {
        $schedule = MeterReadingSchedule::find($scheduleId);
        if (!$schedule) {
            return [null, null];
        }

        try {
            if (!empty($schedule->bill_month)) {
                $m = Carbon::parse($schedule->bill_month)->startOfMonth();
                return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()];
            }
            if (!empty($schedule->due_date)) {
                $m = Carbon::parse($schedule->due_date)->startOfMonth();
                return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()];
            }
            if (!empty($schedule->date)) {
                $m = Carbon::parse($schedule->date)->startOfMonth();
                return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()];
            }
        } catch (\Throwable $e) {
            return [null, null];
        }

        return [null, null];
    }

    /**
     * BILLING/BILL row is unpaid until covered by a consumer_payments row (same reading or schedule).
     */
    private function isBillingRowUnpaid(ConsumerLedger $row, int $consumerZoneId): bool
    {
        if (! in_array((string) ($row->trans ?? ''), ['BILLING', 'BILL'], true)) {
            return false;
        }
        if (((float) ($row->billamount ?? 0)) <= 0 && ((float) ($row->debit ?? 0)) <= 0) {
            return false;
        }

        return ! $this->isScheduleCoveredByConsumerPayment(
            $consumerZoneId,
            $row->downloaded_reading_id ? (int) $row->downloaded_reading_id : null,
            $row->schedule_id ? (int) $row->schedule_id : null
        );
    }

    /**
     * PENALTY row is unpaid unless penalties.paid_at is set or a payment recorded penalty for the schedule.
     */
    private function isPenaltyLedgerRowUnpaid(ConsumerLedger $row, int $consumerZoneId): bool
    {
        if ((string) ($row->trans ?? '') !== 'PENALTY') {
            return false;
        }
        $amt = (float) ($row->penalty ?? 0);
            if ($amt <= 0) {
            $amt = (float) ($row->debit ?? 0);
            }
            if ($amt <= 0) {
            return false;
        }
        if ($row->penalty_id) {
            $pen = Penalty::find($row->penalty_id);
            if ($pen && $pen->paid_at) {
                return false;
            }
        }
        if ($row->schedule_id) {
            $readingIds = DownloadedReading::where('schedule_id', $row->schedule_id)->pluck('id');
            $penaltyDate = null;
            try {
                if (!empty($row->date)) {
                    $penaltyDate = Carbon::parse($row->date);
                }
            } catch (\Throwable $e) {
                $penaltyDate = null;
            }
            $paidPenalty = 0.0;
            if ($readingIds->isNotEmpty()) {
                $readingPenaltyQuery = ConsumerPayment::where('consumer_id', $consumerZoneId)
                    ->whereIn('reading_id', $readingIds)
                    ->whereNotNull('paid_at');
                if ($penaltyDate) {
                    $readingPenaltyQuery->where('paid_at', '>=', $penaltyDate->copy()->startOfDay()->format('Y-m-d H:i:s'));
                }
                $paidPenalty += (float) $readingPenaltyQuery->sum('penalty');
            }
            $paidPenalty += $this->getNullReadingPenaltyPaidForScheduleMonth($consumerZoneId, (int) $row->schedule_id, $penaltyDate);
            if ($paidPenalty + 0.005 >= $amt) {
                return false;
            }
        }

        return true;
    }

    /**
     * DM debit is unpaid carried balance until the same schedule has a recorded payment (as BILLING).
     */
    private function isDmRowUnpaid(ConsumerLedger $row, int $consumerZoneId): bool
    {
        if ((string) ($row->trans ?? '') !== 'DM') {
                        return false;
                    }
        if (((float) ($row->debit ?? 0)) <= 0) {
            return false;
        }

        return ! $this->isScheduleCoveredByConsumerPayment(
            $consumerZoneId,
            $row->downloaded_reading_id ? (int) $row->downloaded_reading_id : null,
            $row->schedule_id ? (int) $row->schedule_id : null
        );
    }

    private function getBillingBreakdownForConsumer(int $consumerId, string $viewType, $asOfDate = null, ?int $payMonths = null, ?string $selectedBillMonthYmd = null): array
    {
        $today = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        $currentYear = (int) $today->format('Y');
        $todayYmd = $today->format('Y-m-d');
        $selectedMonthStart = null;
        $selectedMonthEnd = null;
        if (!empty($selectedBillMonthYmd)) {
            try {
                $selectedMonthStart = Carbon::createFromFormat('Y-m', $selectedBillMonthYmd)->startOfMonth();
                $selectedMonthEnd = $selectedMonthStart->copy()->endOfMonth();
            } catch (\Throwable $e) {
                $selectedMonthStart = null;
                $selectedMonthEnd = null;
            }
        }
        // Cycle reset (no paid_at): if latest PAYMENT before selected month has zero balance,
        // treat that point as a new cycle and exclude older rows from arrears/current computations.
        $cycleResetDateYmd = null;
        if ($selectedMonthStart) {
            $cycleResetEntry = ConsumerLedger::where('consumer_zone_id', $consumerId)
                ->where('trans', 'PAYMENT')
                ->whereNotNull('balance')
                ->whereRaw('ABS(balance) < 0.01')
                ->whereDate('date', '<', $selectedMonthStart->format('Y-m-d'))
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            if ($cycleResetEntry && !empty($cycleResetEntry->date)) {
                $cycleResetDateYmd = Carbon::parse($cycleResetEntry->date)->format('Y-m-d');
            }
        }
        // When a bill month is explicitly selected, compute using that month's range
        // instead of transaction_date cutoff so selected-month current bill is never excluded.
        $calculationCutoffYmd = $selectedMonthEnd
            ? $selectedMonthEnd->format('Y-m-d')
            : $todayYmd;

        /*
         * Breakdown is NOT built from one total then split.
         * 1) Latest unpaid BILLING only → Current Bill (billamount).
         * 2) WMC = SUM(unpaid BILLING others) across all unpaid months.
         * 3) Unpaid PENALTY rows → Penalty (excluded if already paid via consumer_payments / penalties.paid_at).
         * 4) Arrears CY/PY = unpaid principal/carry only (older billing billamount + DM), no WMC/Penalty duplication.
         * Payment history is reflected via which rows are still “unpaid” (consumer_payments coverage).
         */
        $ledgerQuery = ConsumerLedger::where('consumer_zone_id', $consumerId)
            ->whereRaw('COALESCE(date, txtime) <= ?', [$calculationCutoffYmd . ' 23:59:59'])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');
        if ($cycleResetDateYmd) {
            $ledgerQuery->whereRaw('DATE(COALESCE(txtime, date)) > ?', [$cycleResetDateYmd]);
        }
        $ledgerRows = $ledgerQuery->get();

        $currentBill = 0.0;
        $arrearsCurrentYear = 0.0;
        $arrearsPreviousYear = 0.0;
        $waterMaintenanceCharge = 0.0;
        $penalty = 0.0;

        $unpaidBillingRows = $ledgerRows->filter(function (ConsumerLedger $row) use ($consumerId) {
            return $this->isBillingRowUnpaid($row, $consumerId);
        })->values();

        if ($unpaidBillingRows->isNotEmpty()) {
            $latestUnpaidDate = null;
            foreach ($unpaidBillingRows as $row) {
                $d = $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null;
                if ($d === null) {
                    continue;
                }
                if ($latestUnpaidDate === null || $d > $latestUnpaidDate) {
                    $latestUnpaidDate = $d;
                }
            }

            // Latest unpaid billing only → Current Bill (principal only).
            foreach ($unpaidBillingRows as $row) {
                $rowDate = $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null;
                if ($rowDate === null || $latestUnpaidDate === null) {
                    continue;
                }
                if ($rowDate !== $latestUnpaidDate) {
                    continue;
                }
                $currentBill += round((float) ($row->billamount ?? 0), 2);
            }

            // WMC is the sum of "others" from ALL unpaid billing rows.
            foreach ($unpaidBillingRows as $row) {
                $waterMaintenanceCharge += round((float) ($row->others ?? 0), 2);
            }

            // Older unpaid billings → arrears principal only, by year.
            // "others" is already represented in WMC, so do not duplicate in arrears.
            foreach ($unpaidBillingRows as $row) {
                $rowDate = $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null;
                if ($rowDate === null || $latestUnpaidDate === null) {
                    $ba = (float) ($row->billamount ?? 0);
                    if ($ba > 0) {
                        $arrearsCurrentYear += round($ba, 2);
                    }

                    continue;
                }
                if ($rowDate === $latestUnpaidDate) {
                    continue;
                }
                $ba = (float) ($row->billamount ?? 0);
                $chunk = round($ba, 2);
                if ($chunk <= 0) {
                    continue;
                }
                if ((int) Carbon::parse($rowDate)->format('Y') === $currentYear) {
                    $arrearsCurrentYear += $chunk;
        } else {
                    $arrearsPreviousYear += $chunk;
                }
            }
        }

        foreach ($ledgerRows as $row) {
            if (! $row instanceof ConsumerLedger) {
                continue;
            }
            if ($this->isDmRowUnpaid($row, $consumerId)) {
                $dm = (float) ($row->debit ?? 0);
                if ($dm <= 0) {
                    continue;
                }
                $rowDate = $row->date ? Carbon::parse($row->date)->format('Y-m-d') : $calculationCutoffYmd;
                if ((int) Carbon::parse($rowDate)->format('Y') === $currentYear) {
                    $arrearsCurrentYear += round($dm, 2);
                } else {
                    $arrearsPreviousYear += round($dm, 2);
                }
            }
        }

        foreach ($ledgerRows as $row) {
            if (! $row instanceof ConsumerLedger) {
                continue;
            }
            if (! $this->isPenaltyLedgerRowUnpaid($row, $consumerId)) {
                continue;
            }
            $p = (float) ($row->penalty ?? 0);
            if ($p <= 0) {
                $p = (float) ($row->debit ?? 0);
            }
            if ($p > 0) {
                $penalty += round($p, 2);
            }
        }

        $currentBill = round($currentBill, 2);
        $arrearsCurrentYear = round(max(0, $arrearsCurrentYear), 2);
        $arrearsPreviousYear = round(max(0, $arrearsPreviousYear), 2);
        $waterMaintenanceCharge = round(max(0, $waterMaintenanceCharge), 2);
        $penalty = round(max(0, $penalty), 2);

        $advancesAgg = DB::table('consumer_ledgers')
            ->where('consumer_zone_id', $consumerId)
            ->whereRaw('COALESCE(date, txtime) <= ?', [$calculationCutoffYmd . ' 23:59:59']);
        if ($cycleResetDateYmd) {
            $advancesAgg->whereRaw('DATE(COALESCE(txtime, date)) > ?', [$cycleResetDateYmd]);
        }
        $advancesRow = $advancesAgg->selectRaw(
            'CASE WHEN SUM(COALESCE(credit,0)) > SUM(COALESCE(debit,0)) THEN SUM(COALESCE(credit,0)) - SUM(COALESCE(debit,0)) ELSE 0 END as adv'
        )->first();
        $advances = round((float) ($advancesRow->adv ?? 0), 2);

        $unpaidCount = $unpaidBillingRows->count();
        $pastDueCount = 0;

        $result = [
            'current_bill' => round($currentBill, 2),
            'penalty' => $penalty,
            'water_maintenance_charge' => $waterMaintenanceCharge,
            'arrears_cy' => $arrearsCurrentYear,
            'arrears_py' => $arrearsPreviousYear,
            'advances' => $advances,
            'unpaid_count' => $unpaidCount,
            'past_due_count' => $pastDueCount,
            'view_type' => $viewType,
        ];

        if ($payMonths !== null && $payMonths >= 1 && $payMonths <= 3) {
            $result['amount_due'] = round($currentBill + $arrearsCurrentYear + $arrearsPreviousYear + $penalty + $waterMaintenanceCharge - $advances, 2);
            $result['pay_months'] = $payMonths;
            $result['arrears_cy_after_pay'] = round($arrearsCurrentYear, 2);
            $result['arrears_py_after_pay'] = round($arrearsPreviousYear, 2);
        }

        return $result;
    }

    /**
     * Public entry point for breakdown data (used by MeterReadingController::getBillMonthDetails).
     * Latest unpaid BILLING → current bill + WMC; unpaid penalty; remainder → arrears by year (payment history via coverage).
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
                $ledgerArrears = ConsumerLedgerController::computeLedgerFooterBalance((int) $consumer->id, date('Y'));

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
                     'arrears' => $ledgerArrears,
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
            'process_type' => 'nullable|string',
            'reading_date' => 'nullable|date',
        ]);

        $zone = $request->input('zone');
        $processType = $request->input('process_type');
        $readingDateInput = $request->input('reading_date');

        // Bill Printing export must match the on-screen Bill Printing table exactly.
        if ($processType === 'Bill Printing') {
            if (!$zone || $zone === 'all') {
                return response()->json([
                    'success' => false,
                    'message' => 'Zone is required for Bill Printing export.',
                ], 422);
            }
            if (!$readingDateInput) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reading Date is required for Bill Printing export.',
                ], 422);
            }

            $readingDate = Carbon::parse($readingDateInput)->format('Y-m-d');

            $downloadedRows = DB::table('downloaded_readings as dr')
                ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
                ->select([
                    'dr.id as downloaded_id',
                    DB::raw('COALESCE(mrs.account_number, dr.account_number) as account_number'),
                    DB::raw('COALESCE(mrs.account_name, dr.account_name) as account_name'),
                    'mrs.meter_number',
                    'dr.consumption as volume',
                    'dr.current_bill as downloaded_current_bill',
                ])
                ->where(function($query) use ($zone) {
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
                })
                ->whereDate('dr.reading_date', $readingDate)
                ->orderByDesc('dr.id')
                ->get();

            // Match table behavior: latest downloaded row per account, then account-tail ascending.
            $rowsByAccount = collect($downloadedRows)
                ->unique(function ($row) {
                    return strtoupper(trim((string) ($row->account_number ?? '')));
                })
                ->values();

            $tailNumber = function ($accountNumber) {
                $parts = explode('-', (string) $accountNumber);
                $tail = trim((string) end($parts));
                return is_numeric($tail) ? (int) $tail : PHP_INT_MAX;
            };

            $sortedRows = $rowsByAccount->sort(function ($a, $b) use ($tailNumber) {
                $na = $tailNumber($a->account_number ?? '');
                $nb = $tailNumber($b->account_number ?? '');
                if ($na === $nb) {
                    return strnatcasecmp((string) ($a->account_number ?? ''), (string) ($b->account_number ?? ''));
                }
                return $na <=> $nb;
            })->values();

            $records = $sortedRows->map(function ($item) {
                $currentBill = (float) ($item->downloaded_current_bill ?? 0);
                $maintenanceCharge = $currentBill > 0 ? 20.00 : 0.00;
                $totalAmount = $currentBill + $maintenanceCharge;

                return [
                    'Account #' => $item->account_number ?? '',
                    'Account Name' => $item->account_name ?? '',
                    'Meter #' => $item->meter_number ?? '',
                    'Consumption' => number_format((float) ($item->volume ?? 0), 0),
                    'Current Bill' => number_format($currentBill, 2),
                    'Water Maintenance Charge' => number_format($maintenanceCharge, 2),
                    'Total Amount' => number_format($totalAmount, 2),
                ];
            });

            if ($records->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No bill printing records found to export for the selected zone and reading date.',
                ], 404);
            }

            $zoneText = "Zone-{$zone}";
            $dateText = Carbon::parse($readingDate)->format('Ymd');
            $filename = "Bill-Printing-{$zoneText}-{$dateText}-" . Carbon::now()->format('His') . '.xlsx';

            if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new class($records) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle {
                        protected $data;

                        public function __construct($data)
                        {
                            $this->data = collect($data);
                        }

                        public function collection()
                        {
                            return $this->data;
                        }

                        public function headings(): array
                        {
                            return [
                                'Account #',
                                'Account Name',
                                'Meter #',
                                'Consumption',
                                'Current Bill',
                                'Water Maintenance Charge',
                                'Total Amount',
                            ];
                        }

                        public function title(): string
                        {
                            return 'Bill Printing';
                        }
                    },
                    $filename
                );
            }

            $csvFilename = str_replace('.xlsx', '.csv', $filename);
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$csvFilename}\"",
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, [
                    'Account #',
                    'Account Name',
                    'Meter #',
                    'Consumption',
                    'Current Bill',
                    'Water Maintenance Charge',
                    'Total Amount',
                ]);
                foreach ($records as $record) {
                    fputcsv($file, array_values($record));
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // Build base query for schedules in the selected zone (or all zones)
        $query = MeterReadingSchedule::query()
            ->with('consumer')
            ->orderBy('zone')
            ->orderByAccountNumberTail();

        if ($zone && $zone !== 'all') {
            $query->where('zone', $zone);
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No billing records found to export for the selected filters.',
            ], 404);
        }

              // Map schedules to flat array for export (only required columns)
        $records = $schedules->map(function (MeterReadingSchedule $item) {
            $currentBill = $item->current_bill !== null ? (float) $item->current_bill : 0.0;
            $arrears = $item->arrears !== null ? (float) $item->arrears : 0.0;
            // Water maintenance charge: 20.00 when there is a bill, otherwise 0
            $maintenanceCharge = $currentBill > 0 ? 20.00 : 0.00;
            $totalAmount = $item->total_amount !== null
                ? (float) $item->total_amount
                : ($currentBill + $arrears + $maintenanceCharge);

            return [
                'Account #' => $item->account_number ?? '',
                'Account Name' => $item->account_name ?? $item->consumer->account_name ?? '',
                'Meter #' => $item->meter_number ?? '',
                'Consumption' => $item->consumption !== null ? number_format((float) $item->consumption, 0) : '',
                'Water Maintenance Charge' => number_format($maintenanceCharge, 2),
                'Total Amount' => number_format($totalAmount, 2),
            ];
        });

        $zoneText = $zone && $zone !== 'all' ? "Zone-{$zone}" : 'All-Zones';
        $filename = "Billing-Records-{$zoneText}-" . Carbon::now()->format('YmdHis') . '.xlsx';

        // Use Laravel Excel if available, otherwise fall back to CSV stream
        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new class($records) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle {
                    protected $data;

                    public function __construct($data)
                    {
                        $this->data = collect($data);
                    }

                    public function collection()
                    {
                        return $this->data;
                    }

                    public function headings(): array
                    {
                        return [
                            'Account #',
                            'Account Name',
                            'Meter #',
                            'Consumption',
                            'Water Maintenance Charge',
                            'Total Amount',
                        ];
                    }

                    public function title(): string
                    {
                        return 'Billing Records';
                    }
                },
                $filename
            );
        }

        // Fallback: CSV if Excel package is not available
        $csvFilename = str_replace('.xlsx', '.csv', $filename);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$csvFilename}\"",
        ];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Account #',
                'Account Name',
                'Meter #',
                'Consumption',
                'Water Maintenance Charge',
                'Total Amount',
            ]);

            foreach ($records as $record) {
                fputcsv($file, array_values($record));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
     * Delete meter reading schedules
     */
    public function deleteSchedules(Request $request)
    {
        $request->validate([
            'zone' => 'required|string',
            'bill_month' => 'required|date'
        ]);

        try {
            $zone = $request->zone;
            $billMonth = Carbon::parse($request->bill_month);

            $deleted = MeterReadingSchedule::where('zone', $zone)
                ->where('bill_month', $billMonth->format('Y-m-d'))
                ->delete();

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

            // KARON NABAG O // Filter by exact Reading Date only (when readings were taken)
            // // Use only dr.reading_date so Bill Printing shows only records for the selected date
             $date = $readingDate->format('Y-m-d');
             $readings = (clone $baseQuery)
                 ->whereDate('dr.reading_date', $date)
                 ->orderBy(DB::raw('COALESCE(mrs.sedr_number, dr.account_number)'))
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
                 
                   // Bill Printing: Current Bill from downloaded_readings.current_bill; Water Maintenance = 20
                $currentBill = (float) ($reading->downloaded_current_bill ?? 0);
                $waterMaintenanceCharge = 20.00;
                 
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

                // De-duplicate by account_number so each consumer appears only once in Bill Printing
                $formattedReadings = collect($formattedReadings)
                    ->sortByDesc('downloaded_id')   // keep latest downloaded row for each account
                    ->unique('account_number')
                    ->values()
                    ->all();

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
     * Calculate water bill based on consumption (cubic meters)
     * This is a basic calculation - adjust rates according to your actual tariff structure
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
                }
                // Arrears = balance from consumer_ledger immediately before the BILLING for this schedule
                if ($consumer && !empty($row->schedule_id)) {
                    $billingLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                        ->where('schedule_id', $row->schedule_id)
                        ->whereIn('trans', ['BILLING', 'BILL'])
                        ->orderBy('id', 'asc')
                        ->first();
                    if ($billingLedger) {
                        $prevLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                            ->whereNotNull('balance')
                            ->where('id', '<', $billingLedger->id)
                            ->orderBy('id', 'desc')
                            ->first();
                        $arrears = $prevLedger ? (float) ($prevLedger->balance ?? 0) : 0.00;
                    }
                }

                $wmc = ($currentBill > 0) ? (float) self::WMC_PER_MONTH : 0.00;
                $penaltyBase = $currentBill; // 10% on current_bill only, not current_bill + WMC
                $calculatedPenalty = round($penaltyBase * (float) self::PENALTY_RATE, 2);
                if ($calculatedPenalty < (float) self::PENALTY_PER_MONTH && $penaltyBase > 0) {
                    $calculatedPenalty = (float) self::PENALTY_PER_MONTH;
                }
                $total = round($currentBill + $wmc + $arrears + $calculatedPenalty, 2);
                
                // Compute actual outstanding balance before penalty
                $outstandingBalance = $currentBill + $arrears;

                // Skip consumers with credit or zero balance
                    if ($outstandingBalance <= 0) {
                  continue;
                        }   

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

    // //   * Generate penalty report from penalties table.
    // //  * Joins meter_reading_schedules and consumer_zone for zone, account, sequence, rate_code.
    // //  */
    // public function penaltyReport(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'zone' => 'nullable|string',
    //             'bill_month' => 'nullable|string',
    //             'bill_year' => 'nullable|integer',
    //         ]);

    //         $zone = $request->get('zone');
    //         $billMonth = $request->get('bill_month'); // Format: MM-YYYY or MM
    //         $billYear = $request->get('bill_year') ?? date('Y');

    //         // Parse bill month
    //         $month = null;
    //         $year = $billYear;
    //         if ($billMonth) {
    //             if (strpos($billMonth, '-') !== false) {
    //                 $parts = explode('-', $billMonth);
    //                 $month = $parts[0] ?? null;
    //                 $year = $parts[1] ?? $billYear;
    //             } else {
    //                 $month = $billMonth;
    //             }
    //         }

    //         // Query penalties table joined with meter_reading_schedules and consumer_zone
    //         $query = DB::table('penalties')
    //             ->leftJoin('meter_reading_schedules as mrs', 'penalties.schedule_id', '=', 'mrs.id')
    //             ->leftJoin('consumer_zone as cz', 'penalties.consumer_zone_id', '=', 'cz.id')
    //             ->select(
    //                 'penalties.id',
    //                 'penalties.date',
    //                 'penalties.due_date',
    //                 'penalties.reference',
    //                 'penalties.penalty_amount',
    //                 'penalties.bill_amount',
    //                 'mrs.zone as mrs_zone',
    //                 'mrs.bill_month',
    //                 'mrs.account_number as mrs_account_number',
    //                 'mrs.account_name as mrs_account_name',
    //                 'mrs.category',
    //                 'cz.zone_code',
    //                 'cz.sequence',
    //                 'cz.rate_code',
    //                 'cz.account_no',
    //                 'cz.account_name as cz_account_name'
    //             );

    //         // Apply zone filter (schedule zone or consumer_zone.zone_code)
    //         if ($zone && $zone !== '' && $zone !== 'All Zones') {
    //             $query->where(function ($q) use ($zone) {
    //                 $q->where('mrs.zone', $zone)
    //                     ->orWhere('cz.zone_code', $zone)
    //                     ->orWhereRaw('LPAD(TRIM(COALESCE(mrs.zone, "")), 3, "0") = ?', [$zone])
    //                     ->orWhereRaw('LPAD(TRIM(COALESCE(cz.zone_code, "")), 3, "0") = ?', [$zone]);
    //             });
    //         }

    //         // Apply bill month filter by penalty due_date (or bill_month from schedule)
    //         if ($month && $month !== '') {
    //             $query->where(function ($q) use ($month, $year) {
    //                 $q->where(function ($q1) use ($month, $year) {
    //                     $q1->whereMonth('penalties.due_date', $month)->whereYear('penalties.due_date', $year);
    //                 })->orWhere(function ($q2) use ($month, $year) {
    //                     $q2->whereMonth('mrs.bill_month', $month)->whereYear('mrs.bill_month', $year);
    //                 });
    //             });
    //         } elseif ($year) {
    //             $query->where(function ($q) use ($year) {
    //                 $q->whereYear('penalties.due_date', $year)
    //                     ->orWhereYear('mrs.bill_month', $year);
    //             });
    //         }

    //         $rows = $query->orderBy('penalties.due_date', 'asc')
    //             ->orderBy('cz.sequence')
    //             ->orderBy('penalties.id')
    //             ->get();

    //         if ($rows->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No records found for the selected criteria.',
    //                 'data' => [],
    //                 'summary' => [
    //                     'zone' => $zone ?? 'All Zones',
    //                     'bill_month' => $month ? sprintf('%02d-%d', $month, $year) : 'All Months',
    //                     'total_penalized' => 0,
    //                     'total_penalty' => 0,
    //                 ]
    //             ]);
    //         }

    //         $data = [];
    //         foreach ($rows as $row) {
    //             $penaltyDate = $row->date ? Carbon::parse($row->date) : ($row->due_date ? Carbon::parse($row->due_date) : Carbon::today());
    //             $billMonthFormatted = $row->bill_month ? Carbon::parse($row->bill_month)->format('m-Y') : ($row->due_date ? Carbon::parse($row->due_date)->format('m-Y') : '');
    //             $zoneCode = $row->zone_code ?? $row->mrs_zone ?? '';
    //             $accountNumber = $row->mrs_account_number ?? $row->account_no ?? '';
    //             $accountName = $row->mrs_account_name ?? $row->cz_account_name ?? '';
    //             $penaltyAmount = (float) ($row->penalty_amount ?? 0);

    //             $data[] = [
    //                 'zone_code' => $zoneCode,
    //                 'bill_month' => $billMonthFormatted,
    //                 'sequence' => $row->sequence ?? 0,
    //                 'account_number' => $accountNumber,
    //                 'account_name' => $accountName,
    //                 'rate_code' => $row->rate_code ?? $row->category ?? 'P1',
    //                 'date' => $penaltyDate->format('m/d/Y'),
    //                 'rate_code1' => 'LP', // Late Payment
    //                 'penalty' => round($penaltyAmount, 2),
    //                 'ref' => $row->reference ?? 'Late Payment',
    //                 'sedr' => '',
    //             ];
    //         }

    //         // Calculate summary
    //         $totalPenalty = array_sum(array_column($data, 'penalty'));

    //         // Group by zone for summary
    //         $summaryByZone = [];
    //         foreach ($data as $record) {
    //             $zoneKey = $record['zone_code'] ?? 'Unknown';
    //             if (!isset($summaryByZone[$zoneKey])) {
    //                 $summaryByZone[$zoneKey] = [
    //                     'zone' => $zoneKey,
    //                     'accounts' => 0,
    //                     'total_penalty' => 0
    //                 ];
    //             }
    //             $summaryByZone[$zoneKey]['accounts']++;
    //             $summaryByZone[$zoneKey]['total_penalty'] += $record['penalty'];
    //         }

    //         // Group by penalty type for summary
    //         $summaryByPenaltyType = [];
    //         foreach ($data as $record) {
    //             $penaltyType = $record['rate_code1'] ?? 'LP';
    //             if (!isset($summaryByPenaltyType[$penaltyType])) {
    //                 $summaryByPenaltyType[$penaltyType] = [
    //                     'type' => $penaltyType,
    //                     'accounts' => 0,
    //                     'total_amount' => 0
    //                 ];
    //             }
    //             $summaryByPenaltyType[$penaltyType]['accounts']++;
    //             $summaryByPenaltyType[$penaltyType]['total_amount'] += $record['penalty'];
    //         }

    //         $summary = [
    //             'zone' => $zone ?? 'All Zones',
    //             'bill_month' => $month ? sprintf('%02d-%d', $month, $year) : 'All Months',
    //             'total_penalized' => count($data),
    //             'total_penalty' => round($totalPenalty, 2),
    //             'by_zone' => array_values($summaryByZone),
    //             'by_penalty_type' => array_values($summaryByPenaltyType),
    //         ];

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Penalty report generated successfully.',
    //             'data' => $data,
    //             'summary' => $summary,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Penalty Report Error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString(),
    //             'request' => $request->all()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error generating penalty report: ' . $e->getMessage(),
    //             'data' => [],
    //             'summary' => [
    //                 'zone' => $request->get('zone') ?? 'All Zones',
    //                 'bill_month' => 'Error',
    //                 'total_penalized' => 0,
    //                 'total_penalty' => 0,
    //             ]
    //         ], 500);
    //     }
    // }
    
       /**
     * Generate penalty report from penalties table.
     * Joins meter_reading_schedules and consumer_zone for zone, account, sequence, rate_code.
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
                    $parts = explode('-', $billMonth);
                    $month = $parts[0] ?? null;
                    $year = $parts[1] ?? $billYear;
                } else {
                    $month = $billMonth;
                }
            }

            // Query penalties table joined with meter_reading_schedules and consumer_zone
            $query = DB::table('penalties')
                ->leftJoin('meter_reading_schedules as mrs', 'penalties.schedule_id', '=', 'mrs.id')
                ->leftJoin('consumer_zone as cz', 'penalties.consumer_zone_id', '=', 'cz.id')
                ->select(
                    'penalties.id',
                    'penalties.date',
                    'penalties.due_date',
                    'penalties.reference',
                    'penalties.penalty_amount',
                    'penalties.bill_amount',
                    'mrs.zone as mrs_zone',
                    'mrs.bill_month',
                    'mrs.account_number as mrs_account_number',
                    'mrs.account_name as mrs_account_name',
                    'mrs.category',
                    'cz.zone_code',
                    'cz.sequence',
                    'cz.rate_code',
                    'cz.account_no',
                    'cz.account_name as cz_account_name'
                );

            // Apply zone filter (schedule zone or consumer_zone.zone_code)
            if ($zone && $zone !== '' && $zone !== 'All Zones') {
                $query->where(function ($q) use ($zone) {
                    $q->where('mrs.zone', $zone)
                        ->orWhere('cz.zone_code', $zone)
                        ->orWhereRaw('LPAD(TRIM(COALESCE(mrs.zone, "")), 3, "0") = ?', [$zone])
                        ->orWhereRaw('LPAD(TRIM(COALESCE(cz.zone_code, "")), 3, "0") = ?', [$zone]);
                });
            }

            // Apply bill month filter by penalty due_date (or bill_month from schedule)
            if ($month && $month !== '') {
                $query->where(function ($q) use ($month, $year) {
                    $q->where(function ($q1) use ($month, $year) {
                        $q1->whereMonth('penalties.due_date', $month)->whereYear('penalties.due_date', $year);
                    })->orWhere(function ($q2) use ($month, $year) {
                        $q2->whereMonth('mrs.bill_month', $month)->whereYear('mrs.bill_month', $year);
                    });
                });
            } elseif ($year) {
                $query->where(function ($q) use ($year) {
                    $q->whereYear('penalties.due_date', $year)
                        ->orWhereYear('mrs.bill_month', $year);
                });
            }

            $rows = $query->orderBy('penalties.due_date', 'asc')
                ->orderBy('cz.sequence')
                ->orderBy('penalties.id')
                ->get();

            if ($rows->isEmpty()) {
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

            $data = [];
            foreach ($rows as $row) {
                $penaltyDate = $row->date ? Carbon::parse($row->date) : ($row->due_date ? Carbon::parse($row->due_date) : Carbon::today());
                $billMonthFormatted = $row->bill_month ? Carbon::parse($row->bill_month)->format('m-Y') : ($row->due_date ? Carbon::parse($row->due_date)->format('m-Y') : '');
                $zoneCode = $row->zone_code ?? $row->mrs_zone ?? '';
                $accountNumber = $row->mrs_account_number ?? $row->account_no ?? '';
                $accountName = $row->mrs_account_name ?? $row->cz_account_name ?? '';
                $penaltyAmount = (float) ($row->penalty_amount ?? 0);

                $data[] = [
                    'zone_code' => $zoneCode,
                    'bill_month' => $billMonthFormatted,
                    'sequence' => $row->sequence ?? 0,
                    'account_number' => $accountNumber,
                    'account_name' => $accountName,
                    'rate_code' => $row->rate_code ?? $row->category ?? 'P1',
                    'date' => $penaltyDate->format('m/d/Y'),
                    'rate_code1' => 'LP', // Late Payment
                    'penalty' => round($penaltyAmount, 2),
                    'ref' => $row->reference ?? 'Late Payment',
                    'sedr' => '',
                ];
            }

            // Calculate summary
            $totalPenalty = array_sum(array_column($data, 'penalty'));

            // Group by category (rate_code) for breakdown used in printed report
            $summaryByCategory = [];
            foreach ($data as $record) {
                $category = $record['rate_code'] ?? 'UNKNOWN';
                if (!isset($summaryByCategory[$category])) {
                    $summaryByCategory[$category] = [
                        'category' => $category,
                        'consumers' => 0,
                        // For now we don't have cubic meter directly linked to penalties;
                        // keep this at 0 so the front-end can still display the column.
                        'cubic_meter' => 0,
                        'total_penalty' => 0.0,
                    ];
                }

                $summaryByCategory[$category]['consumers']++;
                $summaryByCategory[$category]['total_penalty'] += $record['penalty'];
            }

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
                'by_category' => array_values($summaryByCategory),
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
     * Get surcharge candidate for a specific consumer account on selected bill date.
     * Used by Generate Penalty (Single Consumer).
     */
    public function getSingleConsumerPenaltyCandidate(Request $request)
    {
        try {
            $request->validate([
                'account_number' => 'required|string',
                'bill_date' => 'required|date',
            ]);

            $accountNumber = trim((string) $request->input('account_number'));
            $normalizedAccount = str_replace('-', '', $accountNumber);
            $billDate = Carbon::parse($request->input('bill_date'))->startOfDay();
            $today = Carbon::now()->startOfDay();

            $rows = MeterReadingSchedule::query()
                ->leftJoin('downloaded_readings as dr', 'dr.schedule_id', '=', 'meter_reading_schedules.id')
                ->where(function ($q) use ($accountNumber, $normalizedAccount) {
                    $q->whereRaw('TRIM(meter_reading_schedules.account_number) = ?', [$accountNumber])
                        ->orWhereRaw("REPLACE(TRIM(meter_reading_schedules.account_number), '-', '') = ?", [$normalizedAccount]);
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
                )
                ->get();

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
                }

                if ($consumer && !empty($row->schedule_id)) {
                    $billingLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                        ->where('schedule_id', $row->schedule_id)
                        ->whereIn('trans', ['BILLING', 'BILL'])
                        ->orderBy('id', 'asc')
                        ->first();
                    if ($billingLedger) {
                        $prevLedger = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                            ->whereNotNull('balance')
                            ->where('id', '<', $billingLedger->id)
                            ->orderBy('id', 'desc')
                            ->first();
                        $arrears = $prevLedger ? (float) ($prevLedger->balance ?? 0) : 0.00;
                    }
                }

                $wmc = ($currentBill > 0) ? (float) self::WMC_PER_MONTH : 0.00;
                $penaltyBase = $currentBill;
                $calculatedPenalty = round($penaltyBase * (float) self::PENALTY_RATE, 2);
                if ($calculatedPenalty < (float) self::PENALTY_PER_MONTH && $penaltyBase > 0) {
                    $calculatedPenalty = (float) self::PENALTY_PER_MONTH;
                }
                $total = round($currentBill + $wmc + $arrears + $calculatedPenalty, 2);

                $outstandingBalance = $currentBill + $arrears;
                if ($outstandingBalance <= 0) {
                    continue;
                }

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
                'account_number' => $accountNumber,
                'bill_date' => $billDate->format('Y-m-d'),
            ];

            return response()->json([
                'success' => true,
                'message' => count($data) > 0
                    ? count($data) . ' consumer record(s) eligible for penalty.'
                    : 'No eligible past-due billing found for this account and bill date.',
                'data' => $data,
                'summary' => $summary,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('getSingleConsumerPenaltyCandidate error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading single-consumer penalty candidate: ' . $e->getMessage(),
            ], 500);
        }
    }

         
     /**
     * Apply surcharge (penalty) to selected past-due consumers.
     * Creates Penalty records for each selected item from Generate Surcharge.
     */
    public function applySurcharge(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'items.*.schedule_id' => 'required|integer',
                'items.*.downloaded_id' => 'nullable|integer',
                'items.*.consumer_zone_id' => 'nullable|integer',
                'items.*.current_bill' => 'nullable|numeric|min:0',
                'items.*.due_date' => 'nullable|string',
                'items.*.calculated_penalty' => 'required|numeric|min:0',
            ]);

            $items = $request->input('items', []);
            $applied = 0;
            $skipped = 0;
            $errors = [];

            foreach ($items as $item) {
                $scheduleId = (int) ($item['schedule_id'] ?? 0);
                $downloadedId = isset($item['downloaded_id']) ? (int) $item['downloaded_id'] : null;
                $consumerZoneId = isset($item['consumer_zone_id']) ? (int) $item['consumer_zone_id'] : null;
                $currentBill = (float) ($item['current_bill'] ?? 0);
                $dueDateStr = $item['due_date'] ?? null;
                $calculatedPenalty = (float) ($item['calculated_penalty'] ?? 0);

                if ($scheduleId <= 0 || $calculatedPenalty <= 0) {
                    $skipped++;
                    continue;
                }

                // Skip if penalty already exists for this schedule
                $exists = Penalty::where('schedule_id', $scheduleId)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $dueDate = $dueDateStr ? Carbon::parse($dueDateStr) : Carbon::today();
                $penaltyDate = $dueDate->copy()->addDay();
                $reference = $dueDate->format('m-Y');
                $dueDateFormatted = $dueDate->format('Y-m-d');
                $penaltyDateTime = $penaltyDate->format('Y-m-d') . ' 00:00:00';
                $username = Auth::check() ? (Auth::user()->name ?? 'Billing') : 'Billing';

                try {
                    DB::transaction(function () use (
                        $consumerZoneId,
                        $scheduleId,
                        $downloadedId,
                        $currentBill,
                        $calculatedPenalty,
                        $dueDateFormatted,
                        $penaltyDate,
                        $reference,
                        $penaltyDateTime,
                        $username,
                    ) {
                        // Create penalty record first (same as existing logic)
                        $penaltyRecord = Penalty::create([
                            'consumer_zone_id' => $consumerZoneId ?: null,
                            'schedule_id' => $scheduleId,
                            'downloaded_reading_id' => $downloadedId,
                            'date' => $penaltyDate->format('Y-m-d'),
                            'due_date' => $dueDateFormatted,
                            'reference' => $reference,
                            'bill_amount' => $currentBill,
                            'penalty_amount' => $calculatedPenalty,
                            'balance' => $calculatedPenalty,
                            'username' => $username,
                            'txtime' => $penaltyDateTime,
                        ]);

                        // Create PENALTY row in consumer_ledger and update consumer_zone balance (same logic as CollectionController)
                        if ($consumerZoneId) {
                            $prevLedger = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                                ->whereNotNull('balance')
                                ->orderBy('id', 'desc')
                                ->first();
                            $previousBalance = $prevLedger ? (float) ($prevLedger->balance ?? 0) : 0.00;
                            $consumerZone = ConsumerZoneOne::find($consumerZoneId);
                            if ($consumerZone !== null) {
                                $previousBalance = $previousBalance ?: (float) ($consumerZone->balance ?? 0);
                            }
                            $newBalance = $previousBalance + $calculatedPenalty;

                            ConsumerLedger::create([
                                'consumer_zone_id' => $consumerZoneId,
                                'trans' => 'PENALTY',
                                'penalty_id' => $penaltyRecord->id,
                                'schedule_id' => $scheduleId,
                                'downloaded_reading_id' => $downloadedId,
                                'date' => $penaltyDate->format('Y-m-d'),
                                'due_date' => $dueDateFormatted,
                                'reference' => $reference,
                                'reading' => null,
                                'volume' => null,
                                'billamount' => 0,
                                'penalty' => $calculatedPenalty,
                                'others' => 0,
                                'debit' => $calculatedPenalty,
                                'credit' => 0,
                                'balance' => $newBalance,
                                'username' => $username,
                                'txtime' => $penaltyDateTime,
                            ]);

                            if ($consumerZone !== null) {
                                $consumerZone->balance = $newBalance;
                                $consumerZone->save();
                            }
                        }
                    });
                    $applied++;
                } catch (\Throwable $e) {
                    Log::error('applySurcharge item error: ' . $e->getMessage(), ['item' => $item, 'trace' => $e->getTraceAsString()]);
                    $errors[] = ($item['account_number'] ?? 'Schedule ' . $scheduleId) . ': ' . $e->getMessage();
                }
            }

            $message = $applied . ' surcharge(s) applied successfully.';
            if ($skipped > 0) {
                $message .= ' ' . $skipped . ' skipped (already applied or invalid).';
            }
            if (count($errors) > 0) {
                $message .= ' Errors: ' . implode('; ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= ' (+' . (count($errors) - 3) . ' more)';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'applied' => $applied,
                'skipped' => $skipped,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('applySurcharge error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error applying surcharge: ' . $e->getMessage(),
            ], 500);
        }
    }
     /**
     * Export penalty report as Excel (same data source as penaltyReport: penalties table).
     */
    public function exportPenaltyReport(Request $request)
    {
        $zone = $request->get('zone');
        $billMonth = $request->get('bill_month');
        $billYear = $request->get('bill_year') ?? date('Y');

        $month = null;
        $year = $billYear;
        if ($billMonth) {
            if (strpos($billMonth, '-') !== false) {
                $parts = explode('-', $billMonth);
                $month = $parts[0] ?? null;
                $year = $parts[1] ?? $billYear;
            } else {
                $month = $billMonth;
            }
        }

        $query = DB::table('penalties')
            ->leftJoin('meter_reading_schedules as mrs', 'penalties.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', 'penalties.consumer_zone_id', '=', 'cz.id')
            ->select(
                'penalties.id',
                'penalties.date',
                'penalties.due_date',
                'penalties.reference',
                'penalties.penalty_amount',
                'mrs.zone as mrs_zone',
                'mrs.bill_month',
                'mrs.account_number as mrs_account_number',
                'mrs.account_name as mrs_account_name',
                'mrs.category',
                'cz.zone_code',
                'cz.sequence',
                'cz.rate_code',
                'cz.account_no',
                'cz.account_name as cz_account_name'
            );

        if ($zone && $zone !== '' && $zone !== 'All Zones') {
            $query->where(function ($q) use ($zone) {
                $q->where('mrs.zone', $zone)
                    ->orWhere('cz.zone_code', $zone)
                    ->orWhereRaw('LPAD(TRIM(COALESCE(mrs.zone, "")), 3, "0") = ?', [$zone])
                    ->orWhereRaw('LPAD(TRIM(COALESCE(cz.zone_code, "")), 3, "0") = ?', [$zone]);
            });
        }

        if ($month && $month !== '') {
            $query->where(function ($q) use ($month, $year) {
                $q->where(function ($q1) use ($month, $year) {
                    $q1->whereMonth('penalties.due_date', $month)->whereYear('penalties.due_date', $year);
                })->orWhere(function ($q2) use ($month, $year) {
                    $q2->whereMonth('mrs.bill_month', $month)->whereYear('mrs.bill_month', $year);
                });
            });
        } elseif ($year) {
            $query->where(function ($q) use ($year) {
                $q->whereYear('penalties.due_date', $year)
                    ->orWhereYear('mrs.bill_month', $year);
            });
        }

        $rows = $query->orderBy('penalties.due_date', 'asc')
            ->orderBy('cz.sequence')
            ->orderBy('penalties.id')
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $penaltyDate = $row->date ? Carbon::parse($row->date) : ($row->due_date ? Carbon::parse($row->due_date) : Carbon::today());
            $billMonthFormatted = $row->bill_month ? Carbon::parse($row->bill_month)->format('m-Y') : ($row->due_date ? Carbon::parse($row->due_date)->format('m-Y') : '');
            $zoneCode = $row->zone_code ?? $row->mrs_zone ?? '';
            $accountNumber = $row->mrs_account_number ?? $row->account_no ?? '';
            $accountName = $row->mrs_account_name ?? $row->cz_account_name ?? '';
            $penaltyAmount = (float) ($row->penalty_amount ?? 0);

            $data[] = [
                $zoneCode,
                $billMonthFormatted,
                $row->sequence ?? 0,
                $accountNumber,
                $accountName,
                $row->rate_code ?? $row->category ?? 'P1',
                $penaltyDate->format('m/d/Y'),
                'LP',
                round($penaltyAmount, 2),
                $row->reference ?? 'Late Payment',
            ];
        }

        $zoneText = ($zone && $zone !== 'All Zones') ? "Zone-{$zone}" : 'All-Zones';
        $monthText = $month ? sprintf('%02d-%d', $month, $year) : $year;
        $filename = "Penalty-Report-{$zoneText}-{$monthText}-" . Carbon::now()->format('YmdHis') . '.xlsx';

        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return Excel::download(
                new class($data) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle {
                    protected $rows;

                    public function __construct($rows)
                    {
                        $this->rows = collect($rows);
                    }

                    public function collection()
                    {
                        return $this->rows;
                    }

                    public function headings(): array
                    {
                        return ['Zone_code', 'Bill month', 'Sequence', 'Account_no', 'Account_name', 'Rate_code', 'Date', 'Rate_code1', 'Penalty', 'Ref'];
                    }

                    public function title(): string
                    {
                        return 'Penalty Report';
                    }
                },
                $filename
            );
        }

        return redirect()->back()->with('error', 'Excel export is not available. Laravel Excel package is missing.');
    }

    /**
     * Show consumer master list with filters
     */
    // public function consumerMasterList(Request $request)
    // {
    //     $filters = $request->only([
    //         'search',
    //         'zone',
    //         'status',
    //         'senior_citizen',
    //         'meter_number',
    //         'address',
    //         'meter_location',
    //         'ledger_status',
    //     ]);

    //     $zones = ConsumerZoneOne::select('zone_code')
    //         ->distinct()
    //         ->orderBy('zone_code')
    //         ->pluck('zone_code');

    //     $baseQuery = ConsumerZoneOne::query();

    //     if (!empty($filters['search'])) {
    //         $searchTerm = $filters['search'];
    //         $baseQuery->where(function ($q) use ($searchTerm) {
    //             $q->where('account_name', 'like', '%' . $searchTerm . '%')
    //               ->orWhere('account_no', 'like', '%' . $searchTerm . '%');
    //         });
    //     }

    //     if (!empty($filters['zone'])) {
    //         $baseQuery->where('zone_code', $filters['zone']);
    //     }

    //     if (!empty($filters['status'])) {
    //         $statusValue = $filters['status'];

    //         $statusMap = [
    //             'Active' => ['A', 'ACTIVE', 'Active', 'A - ACTIVE'],
    //              'Pending' => ['P', 'PENDING', 'Pending'],
    //              'Disconnected' => ['X', 'DISCONNECTED', 'Disconnected', 'D'],
    //         ];

    //         if (isset($statusMap[$statusValue])) {
    //             $baseQuery->whereIn('status_code', $statusMap[$statusValue]);
    //         } else {
    //             $baseQuery->where('status_code', $statusValue);
    //         }
    //     }

    //     if (!empty($filters['senior_citizen']) && Schema::hasColumn('consumer_zone', 'is_senior_citizen')) {
    //         $baseQuery->where('is_senior_citizen', true);
    //     }

    //     if (!empty($filters['meter_number'])) {
    //         $baseQuery->where('meter_number', 'like', '%' . $filters['meter_number'] . '%');
    //     }

    //     if (!empty($filters['address'])) {
    //         $baseQuery->where(function ($q) use ($filters) {
    //             $q->where('address1', 'like', '%' . $filters['address'] . '%');

    //             if (Schema::hasColumn('consumer_zone', 'address_2')) {
    //                 $q->orWhere('address_2', 'like', '%' . $filters['address'] . '%');
    //             }
    //         });
    //     }

    //     if (!empty($filters['meter_location']) && Schema::hasColumn('consumer_zone', 'meter_location')) {
    //         $baseQuery->where('meter_location', 'like', '%' . $filters['meter_location'] . '%');
    //     }

    //     if (!empty($filters['ledger_status'])) {
    //         if ($filters['ledger_status'] === 'missing') {
    //             $baseQuery->whereDoesntHave('ledgers');
    //         } elseif ($filters['ledger_status'] === 'imported') {
    //             $baseQuery->whereHas('ledgers');
    //         }
    //     }

    //     $consumersQuery = (clone $baseQuery)->orderBy('zone_code');

    //     if (Schema::hasColumn('consumer_zone', 'route')) {
    //         $consumersQuery->orderBy('route');
    //     }

    //     if (Schema::hasColumn('consumer_zone', 'sequence')) {
    //         $consumersQuery->orderBy('sequence');
    //     }

    //     // Eager load ledger count to check if consumer has imported ledger entries
    //     $consumers = $consumersQuery->withCount('ledgers')->get();

    //     $summaryByZone = (clone $baseQuery)
    //         ->select('zone_code as zone', DB::raw('COUNT(*) as total'))
    //         ->groupBy('zone_code')
    //         ->orderBy('zone_code')
    //         ->get();

    //     return view('reports.system-report.consumer-master-list', [
    //         'zones' => $zones,
    //         'consumers' => $consumers,
    //         'summaryByZone' => $summaryByZone,
    //         'filters' => $filters,
    //     ]);
    // }
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
                 'Pending' => ['P', 'PENDING', 'Pending'],
                 'Disconnected' => ['X', 'DISCONNECTED', 'Disconnected', 'D'],
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

        // Reading guide print fields:
        // - Prevdate / PrevRdg from previous month schedule
        // - PresRdg from latest/current generated month schedule
        $previousMonth = Carbon::now()->subMonthNoOverflow();
        $accountNos = $consumers->pluck('account_no')->filter()->values();
        $previousScheduleByAccount = collect();
        $latestScheduleByAccount = collect();
        if ($accountNos->isNotEmpty()) {
            $previousScheduleByAccount = MeterReadingSchedule::whereIn('account_number', $accountNos)
                ->whereYear('bill_month', $previousMonth->year)
                ->whereMonth('bill_month', $previousMonth->month)
                ->orderBy('bill_date', 'desc')
                ->orderBy('id', 'desc')
                ->get(['account_number', 'bill_date', 'current_reading', 'previous_reading'])
                ->groupBy('account_number')
                ->map(function ($rows) {
                    return $rows->first();
                });

            $latestGeneratedBillMonth = MeterReadingSchedule::whereNotNull('bill_month')->max('bill_month');
            if ($latestGeneratedBillMonth) {
                $latestMonth = Carbon::parse($latestGeneratedBillMonth);
                $latestScheduleByAccount = MeterReadingSchedule::whereIn('account_number', $accountNos)
                    ->whereYear('bill_month', $latestMonth->year)
                    ->whereMonth('bill_month', $latestMonth->month)
                    ->orderBy('bill_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->get(['account_number', 'bill_month', 'bill_date', 'current_reading', 'previous_reading'])
                    ->groupBy('account_number')
                    ->map(function ($rows) {
                        return $rows->first();
                    });
            }
        }

        $consumers->transform(function ($consumer) use ($previousScheduleByAccount, $latestScheduleByAccount) {
            $prev = $previousScheduleByAccount->get($consumer->account_no);
            $latest = $latestScheduleByAccount->get($consumer->account_no);
            $consumer->prev_bill_date = $prev && $prev->bill_date ? Carbon::parse($prev->bill_date)->format('m/d/Y') : '';
            $consumer->prev_pres_rdg = $latest && $latest->current_reading !== null ? (string) ((int) $latest->current_reading) : '';
            $consumer->prev_prev_rdg = $prev && $prev->current_reading !== null
                ? (string) ((int) $prev->current_reading)
                : ($prev && $prev->previous_reading !== null ? (string) ((int) $prev->previous_reading) : '');

            return $consumer;
        });

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
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'amount_due' => 'required|numeric|min:0',
            'amount_tendered' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
            'official_receipt_number' => 'nullable|string|max:20',
            'is_update' => 'nullable|boolean',
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
            'transaction_date' => 'nullable|date',
            'pay_months' => 'nullable|integer|in:1,2,3',
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

                // Allow multiple payments per bill month: no duplicate check on reading_id.

                if ($validated['official_receipt_number']) {
                    $q = ConsumerPayment::where('or_number', $validated['official_receipt_number']);
                    // When updating, exclude the payment we're updating so it can keep its OR number (find by reading_id+OR or by OR+consumer when loaded by OR #)
                    if ($isUpdate) {
                        $paymentBeingUpdated = null;
                        if ($downloaded) {
                            $paymentBeingUpdated = ConsumerPayment::where('reading_id', $downloaded->id)
                                ->where('or_number', $validated['official_receipt_number'])
                                ->first();
                        }
                        if (!$paymentBeingUpdated) {
                            $byOr = ConsumerPayment::where('or_number', $validated['official_receipt_number']);
                            if ($consumerId) {
                                $byOr->where('consumer_id', $consumerId);
                            }
                            $paymentBeingUpdated = $byOr->first();
                        }
                        if ($paymentBeingUpdated) {
                            $q->where('id', '!=', $paymentBeingUpdated->id);
                        } elseif ($downloaded) {
                            $q->where('reading_id', '!=', $downloaded->id);
                        }
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
                    'account_name' => $validated['account_name'] ?? null,
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
                    if ($isUpdate) {
                        if (empty($validated['official_receipt_number'])) {
                            throw new \Exception('OR number is required to update an existing payment. Enter the OR number of the payment you want to update.');
                        }
                        // Update: first try payment for this billing record (reading_id + OR)
                        $consumerPayment = ConsumerPayment::where('reading_id', $downloaded->id)
                            ->where('or_number', $validated['official_receipt_number'])
                            ->first();
                        // Fallback: if not found, find by OR + consumer (same account) so "search by OR #" can update even when payment is linked to a different bill month
                        if (!$consumerPayment && $consumerId) {
                            $consumerPayment = ConsumerPayment::where('or_number', $validated['official_receipt_number'])
                                ->where('consumer_id', $consumerId)
                                ->first();
                        }
                        if ($consumerPayment) {
                            $consumerPayment->update($paymentData);
                        } else {
                            throw new \Exception(
                                'No payment found with OR #' . $validated['official_receipt_number'] . '.' .
                                ($consumerId ? ' Ensure the account number matches the payment.' : ' Load the record by OR number or enter account number first.')
                            );
                        }
                    } else {
                        // New payment: always create (allows multiple payments per same month/reading)
                        $consumerPayment = ConsumerPayment::create(array_merge($paymentData, ['reading_id' => $downloaded->id]));
                    }
                } elseif ($isUpdate && !empty($validated['official_receipt_number'])) {
                    // Update by OR # when record was loaded by OR (no downloaded_id): find payment by OR and account/consumer
                    $byOr = ConsumerPayment::where('or_number', $validated['official_receipt_number']);
                    if ($consumerId) {
                        $byOr->where('consumer_id', $consumerId);
                    }
                    $consumerPayment = $byOr->first();
                    if ($consumerPayment) {
                        if (!$consumerId && $consumerPayment->consumer_id) {
                            $consumerId = $consumerPayment->consumer_id;
                        }
                        $consumerPayment->update($paymentData);
                    } else {
                        throw new \Exception(
                            'No payment found with OR #' . $validated['official_receipt_number'] . '.' .
                            ($consumerId ? ' Ensure the account number matches the payment.' : ' Enter account number and try again.')
                        );
                    }
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

                // Separate sundries from the water-bill amount.
                // paid_at reconciliation should use bill-only amount to avoid over-marking.
                $sundriesTotal = 0;
                foreach ($validated['sundries'] ?? [] as $s) {
                    $sundriesTotal += round((float)($s['amount'] ?? 0), 2);
                }
                $billPaymentAmount = max(0, round(($validated['amount_due'] ?? 0) - $sundriesTotal, 2));

                // Separate sundries from the water-bill amount.
                // paid_at reconciliation should use bill-only amount to avoid over-marking.
                $sundriesTotal = 0;
                foreach ($validated['sundries'] ?? [] as $s) {
                    $sundriesTotal += round((float)($s['amount'] ?? 0), 2);
                }
                $billPaymentAmount = max(0, round(($validated['amount_due'] ?? 0) - $sundriesTotal, 2));

                // Apply payment to unpaid charge rows (paid_at is the only truth).
                // Run for both new and update so legacy records with missing paid_at can be reconciled.
                // Order: PY (previous year) billing first, then penalty (ledger + Penalty model), then CY (current year) billing (respect pay_months).
                if ($consumerId) {
                    $remaining = $billPaymentAmount;
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
                        : (int) $unpaidBillingRows->max(function ($row) {
                            if ($row instanceof ConsumerLedger) {
                                $billMonth = $this->getBillMonthFromRow($row);
                                return $billMonth ? (int) $billMonth->format('Y') : (int) date('Y');
                            }
                            return (int) date('Y');
                        });
                    $pyBilling = $unpaidBillingRows->filter(function ($row) use ($currentYear) {
                        if ($row instanceof ConsumerLedger) {
                            return (($this->getBillMonthFromRow($row)?->format('Y')) ?? $currentYear) < $currentYear;
                        }
                        return false;
                    });
                    $cyBilling = $unpaidBillingRows->filter(function ($row) use ($currentYear) {
                        if ($row instanceof ConsumerLedger) {
                            return (($this->getBillMonthFromRow($row)?->format('Y')) ?? $currentYear) >= $currentYear;
                        }
                        return false;
                    });

                    // 1) PY: set paid_at for previous-year unpaid BILLING rows when fully covered
                    /** @var ConsumerLedger $billingRow */
                    foreach ($pyBilling as $billingRow) {
                        if (!($billingRow instanceof ConsumerLedger)) {
                            continue;
                        }
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
                        if (!($penaltyRow instanceof ConsumerLedger)) {
                            continue;
                        }
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
                        if (!($p instanceof Penalty)) {
                            continue;
                        }
                        $amt = (float)($p->penalty_amount ?? 0);
                        if ($amt <= 0) continue;
                        if ($remaining + 0.009 >= $amt) {
                            Penalty::where('id', $p->id)->update(['paid_at' => $paidAt]);
                            $remaining -= $amt;
                        } else {
                            break;
                        }
                    }

                    // 4) CY: set paid_at for current-year unpaid BILLING rows (respect pay_months)
                    $billingRowsMarked = 0;
                    foreach ($cyBilling as $billingRow) {
                        if (!($billingRow instanceof ConsumerLedger)) {
                            continue;
                        }
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

                // billPaymentAmount already computed above (amount_due - sundriesTotal)
                // and reused here for the PAYMENT ledger row credit.

                // Create or update ConsumerLedger PAYMENT (bill portion only; skip for sundry-only payments)
                if ($consumerId && $billPaymentAmount > 0) {
                    $newBalance = ($outstandingBalance ?? 0) - $billPaymentAmount;
                    // When updating, use the balance *before* this payment row so we don't double-subtract the payment
                    if ($isUpdate && $consumerPayment) {
                        $existingPaymentRow = ConsumerLedger::where('consumer_payment_id', $consumerPayment->id)
                            ->where('trans', 'PAYMENT')
                            ->where(function ($q) {
                                $q->whereNull('reference')->orWhere('reference', 'not like', '%-SC');
                            })
                            ->first();
                        if (!$existingPaymentRow) {
                            $existingPaymentRow = ConsumerLedger::where('consumer_payment_id', $consumerPayment->id)
                                ->where('trans', 'PAYMENT')
                                ->first();
                        }
                        if ($existingPaymentRow) {
                            $previousEntry = ConsumerLedger::where('consumer_zone_id', $consumerId)
                                ->whereNotNull('balance')
                                ->where(function ($q) use ($existingPaymentRow) {
                                    $q->where('date', '<', $existingPaymentRow->date)
                                        ->orWhere(function ($q2) use ($existingPaymentRow) {
                                            $q2->where('date', $existingPaymentRow->date)
                                                ->where('id', '<', $existingPaymentRow->id);
                                        });
                                })
                                ->orderBy('date', 'desc')
                                ->orderBy('id', 'desc')
                                ->first();
                            $balanceBeforeThisPayment = $previousEntry ? (float)($previousEntry->balance ?? 0) : 0;
                            $newBalance = round($balanceBeforeThisPayment - $billPaymentAmount, 2);
                        }
                    }
                    $orNumber = $validated['official_receipt_number'] ?? ($downloaded ? 'Payment DR#' . $downloaded->id : 'Payment ACCT#' . ($validated['account_number'] ?? ''));
                    // Use transaction date (paidAt) so ledger and reports show the same date as the form (e.g. 02/03/2026)
                    $ledgerDate = $paidAt;
                    $readingId = $downloaded?->id;
                    $scheduleId = $downloaded?->schedule_id;
                    if ($downloaded && !$isUpdate) {
                        // Only delete orphan -SC rows that might have been left behind (e.g. from old single-payment flow).
                        // Do not delete all -SC rows for this reading, so multiple payments per month keep their -SC entries.
                        ConsumerLedger::where('consumer_zone_id', $consumerId)
                            ->where('trans', 'PAYMENT')
                            ->where('downloaded_reading_id', $downloaded->id)
                            ->where('reference', 'like', '%-SC')
                            ->whereNull('consumer_payment_id')
                            ->delete();
                    }
                    $ledgerPayload = [
                        'consumer_zone_id' => $consumerId,
                        'consumer_payment_id' => $consumerPayment->id,
                        'schedule_id' => $scheduleId,
                        'date' => $ledgerDate->format('Y-m-d'),
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
                        'txtime' => $ledgerDate->format('Y-m-d H:i:s'),
                        'paid_at' => $ledgerDate,
                    ];
                    if ($readingId !== null) {
                        if ($isUpdate) {
                            // Update the existing ledger row for this payment
                            $ledgerRow = ConsumerLedger::where('consumer_payment_id', $consumerPayment->id)
                                ->where('trans', 'PAYMENT')
                                ->first();
                            if ($ledgerRow) {
                                $ledgerRow->update($ledgerPayload);
                            } else {
                                ConsumerLedger::create(array_merge($ledgerPayload, [
                                    'trans' => 'PAYMENT',
                                    'downloaded_reading_id' => $readingId,
                                ]));
                            }
                        } else {
                            // New payment: always create a new ledger row (supports multiple payments per reading)
                            ConsumerLedger::create(array_merge($ledgerPayload, [
                                'trans' => 'PAYMENT',
                                'downloaded_reading_id' => $readingId,
                            ]));
                        }
                    } else {
                        // No reading_id (e.g. update by OR only, or new-account payment)
                        if ($isUpdate) {
                            $ledgerRow = ConsumerLedger::where('consumer_payment_id', $consumerPayment->id)
                                ->where('trans', 'PAYMENT')
                                ->first();
                            if ($ledgerRow) {
                                $ledgerRow->update($ledgerPayload);
                            } else {
                                ConsumerLedger::create(array_merge($ledgerPayload, [
                                    'trans' => 'PAYMENT',
                                    'downloaded_reading_id' => null,
                                ]));
                            }
                        } else {
                            ConsumerLedger::create(array_merge($ledgerPayload, [
                                'trans' => 'PAYMENT',
                                'downloaded_reading_id' => null,
                            ]));
                        }
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
                                    'date' => $ledgerDate->format('Y-m-d'),
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
                                    'txtime' => $ledgerDate->format('Y-m-d H:i:s'),
                                    'paid_at' => $ledgerDate,
                                ]
                            );
                        } else {
                            ConsumerLedger::create([
                                'consumer_zone_id' => $consumerId,
                                'consumer_payment_id' => $consumerPayment->id,
                                'schedule_id' => $downloaded ? $downloaded->schedule_id : null,
                                'downloaded_reading_id' => $downloaded ? $downloaded->id : null,
                                'trans' => 'PAYMENT',
                                'date' => $ledgerDate->format('Y-m-d'),
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
                                'txtime' => $ledgerDate->format('Y-m-d H:i:s'),
                                'paid_at' => $ledgerDate,
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
    
    /**
     * Akoang mga Bag ong functions Ayaw Walaa hHahaha
     * Insert a single DM (Debit Memo) for one consumer (by consumer_zone_id).
     * Reference is auto-generated (6-digit number) and validated for uniqueness.
     */
    public function storeDmLedger(Request $request)
    {
        $request->validate([
            'consumer_zone_id' => 'required|integer|exists:consumer_zone,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
        ]);

        $consumer = ConsumerZoneOne::find($request->consumer_zone_id);
        if (!$consumer) {
            return response()->json(['success' => false, 'message' => 'Consumer not found.'], 404);
        }

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $amount = (float) $request->amount;

        // Strict: do not allow duplicate DM for same consumer on same date
        $alreadyExists = ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->where('date', $date)
            ->where('trans', 'DM')
            ->exists();
        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'This consumer already has a DM for the selected date. Duplicate not allowed.',
            ], 422);
        }

        try {
            DB::beginTransaction();
            $reference = $this->createOneDmLedgerEntry($consumer, $date, $amount);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DM added for ' . ($consumer->account_no ?? '') . ' - ' . ($consumer->account_name ?? ''),
                'reference' => $reference,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store DM failed', [
                'consumer_zone_id' => $consumer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add DM: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create one DM ledger entry and update consumer balance. Call within a transaction.
     * Returns the generated 6-digit reference.
     */
    private function createOneDmLedgerEntry(ConsumerZoneOne $consumer, string $date, float $amount): string
    {
        $reference = $this->generateUniqueDmReference($date);
        $debit = round($amount, 2);
        $username = Auth::check() ? (Auth::user()->name ?? 'SYSTEM') : 'SYSTEM';
        $dateTime = Carbon::parse($date)->startOfDay();
        $currentBalance = (float) ($consumer->balance ?? 0);
        $newBalance = round($currentBalance + $debit, 2);

        ConsumerLedger::create([
            'consumer_zone_id' => $consumer->id,
            'consumer_payment_id' => null,
            'schedule_id' => null,
            'downloaded_reading_id' => null,
            'penalty_id' => null,
            'billing_adjustment_id' => null,
            'trans' => 'DM',
            'date' => $date,
            'due_date' => null,
            'reference' => $reference,
            'reading' => 0,
            'volume' => 0,
            'billamount' => 0,
            'penalty' => 0,
            'others' => 0,
            'debit' => $debit,
            'credit' => 0,
            'balance' => $newBalance,
            'username' => $username,
            'txtime' => $dateTime,
        ]);

        $consumer->balance = $newBalance;
        $consumer->save();

        return $reference;
    }

    /**
     * Bulk DM upload via Excel. File must have columns: account_no, amount. Date is fixed to 2026-02-27.
     */
    public function storeDmLedgerImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $fixedDate = '2026-02-27';
        $imported = 0;
        $failed = 0;
        $errors = [];

        try {
            $data = Excel::toArray(new DmLedgerImport(), $request->file('file'));
            $rows = $data[0] ?? [];

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The file is empty or has no data rows.',
                    'imported' => 0,
                    'failed' => 0,
                    'errors' => [],
                ], 422);
            }

            // First row is header (numeric indices). Find column indices for account_no and amount.
            $header = $rows[0];
            $accountNoCol = $this->findColumnIndex($header, ['account_no', 'account_number', 'accountnumber', 'account no', 'acct_no', 'acctno']);
            $amountCol = $this->findColumnIndex($header, ['amount', 'debit', 'amt']);

            if ($accountNoCol === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel must have a column for account number (e.g. account_no or account_number).',
                    'imported' => 0,
                    'failed' => 0,
                    'errors' => [],
                ], 422);
            }
            if ($amountCol === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel must have a column for amount (e.g. amount or debit).',
                    'imported' => 0,
                    'failed' => 0,
                    'errors' => [],
                ], 422);
            }

            $accountNoKeys = ['account_no', 'account_number', 'accountnumber', 'account no', 'acct_no', 'acctno'];
            $amountKeys = ['amount', 'debit', 'amt'];

            $processedInThisFile = []; // Track account_no to reject duplicates within the same file

            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // Skip header row
                }

                $rowNum = $index + 1; // 1-based row number in sheet

                // Row may be numeric array (raw) or associative (depends on reader)
                if (isset($row[$accountNoCol])) {
                    $accountNo = trim((string) $row[$accountNoCol]);
                } else {
                    $accountNo = $this->getRowValueCaseInsensitive(is_array($row) ? $row : [], $accountNoKeys);
                    $accountNo = $accountNo !== null ? trim((string) $accountNo) : null;
                }
                if ($accountNo === '') {
                    $accountNo = null;
                }

                if (isset($row[$amountCol])) {
                    $amountVal = $row[$amountCol];
                } else {
                    $amountVal = $this->getRowValueCaseInsensitive(is_array($row) ? $row : [], $amountKeys);
                }
                if ($amountVal !== null && $amountVal !== '') {
                    $amountVal = is_numeric($amountVal) ? (float) $amountVal : null;
                } else {
                    $amountVal = null;
                }

                if (!$accountNo) {
                    if ($amountVal === null || $amountVal === '') {
                        continue; // Skip fully empty rows
                    }
                    $errors[] = "Row {$rowNum}: Missing account_no.";
                    $failed++;
                    continue;
                }
                if ($amountVal === null || $amountVal < 0) {
                    $errors[] = "Row {$rowNum}: Invalid or missing amount.";
                    $failed++;
                    continue;
                }

                $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
                if (!$consumer) {
                    $consumer = ConsumerZoneOne::whereRaw('TRIM(account_no) = ?', [$accountNo])->first();
                }
                if (!$consumer) {
                    $errors[] = "Row {$rowNum}: Account [{$accountNo}] not found.";
                    $failed++;
                    continue;
                }

                // Strict: duplicate in file – same account_no already processed in this upload
                if (isset($processedInThisFile[$accountNo])) {
                    $errors[] = "Row {$rowNum}: Duplicate in file – [{$accountNo}] already processed in row {$processedInThisFile[$accountNo]}.";
                    $failed++;
                    continue;
                }

                // Strict: duplicate in DB – consumer already has a DM for this date
                $alreadyExists = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->where('date', $fixedDate)
                    ->where('trans', 'DM')
                    ->exists();
                if ($alreadyExists) {
                    $errors[] = "Row {$rowNum}: Duplicate – [{$accountNo}] already has a DM for {$fixedDate}.";
                    $failed++;
                    continue;
                }

                try {
                    DB::beginTransaction();
                    $this->createOneDmLedgerEntry($consumer, $fixedDate, $amountVal);
                    DB::commit();
                    $imported++;
                    $processedInThisFile[$accountNo] = $rowNum;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                    $failed++;
                }
            }

            $message = "DM import completed. Imported: {$imported}, Failed: {$failed}.";
            if ($imported === 0 && $failed === 0 && count($rows) > 1) {
                $message = 'No data rows were processed. Check that the file has a header row (account_no, amount) and at least one data row with valid account numbers and amounts.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error('DM import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'imported' => 0,
                'failed' => 0,
                'errors' => [],
            ], 500);
        }
    }

    /**
     * Get value from row by possible column names (case-insensitive, trimmed keys).
     */
    private function getRowValueCaseInsensitive(array $row, array $possibleKeys): mixed
    {
        $rowLower = [];
        foreach ($row as $k => $v) {
            $rowLower[trim(strtolower(str_replace([' ', '_'], '', (string) $k)))] = $v;
        }
        foreach ($possibleKeys as $key) {
            $normalized = trim(strtolower(str_replace([' ', '_'], '', $key)));
            if (isset($rowLower[$normalized])) {
                return $rowLower[$normalized];
            }
        }
        return null;
    }

    /**
     * Find column index in header row by possible names (case-insensitive).
     * Header row can be numeric indices 0,1,2... with cell values as column names.
     */
    private function findColumnIndex(array $headerRow, array $possibleNames): ?int
    {
        $normalizedNames = array_map(function ($name) {
            return trim(strtolower(str_replace([' ', '_'], '', (string) $name)));
        }, $possibleNames);

        foreach ($headerRow as $index => $cellValue) {
            $cellNormalized = trim(strtolower(str_replace([' ', '_'], '', (string) $cellValue)));
            if (in_array($cellNormalized, $normalizedNames, true)) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Generate a unique DM reference: 6-digit number (000001, 000002, ...).
     * Ensures no duplicate reference in consumer_ledgers.
     */
    private function generateUniqueDmReference(string $date): string
    {
        $maxAttempts = 100;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastRef = ConsumerLedger::where('trans', 'DM')
                ->whereRaw('reference REGEXP ?', ['^[0-9]{6}$'])
                ->orderByRaw('CAST(reference AS UNSIGNED) DESC')
                ->value('reference');

            $seq = 1;
            if ($lastRef !== null && preg_match('/^\d{1,6}$/', $lastRef)) {
                $seq = (int) $lastRef + 1;
            }

            if ($seq > 999999) {
                throw new \RuntimeException('DM reference sequence exhausted (max 999999).');
            }

            $reference = str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

            if (!ConsumerLedger::where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new \RuntimeException('Unable to generate unique DM reference after ' . $maxAttempts . ' attempts.');
    }
    
    /**
     * Save a cancelled OR marker for collection report.
     */
    public function storeCancelledOr(Request $request)
    {
        try {
            $validated = $request->validate([
                'or_number' => 'required|string|max:50',
            ]);

            $orNumber = trim((string) $validated['or_number']);
            if ($orNumber === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'OR number is required.',
                ], 422);
            }

            $remarksValue = 'Cancelled OR#' . $orNumber;
            $existing = ConsumerPayment::where('or_number', $orNumber)->first();

            if ($existing) {
                $isCancelledMarker = strcasecmp(trim((string) ($existing->account_name ?? '')), 'Cancelled') === 0
                    || str_starts_with((string) ($existing->remarks ?? ''), 'Cancelled OR#');

                if (!$isCancelledMarker) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OR # ' . $orNumber . ' is already used by an existing payment.',
                    ], 409);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'OR # ' . $orNumber . ' is already marked as cancelled.',
                    'data' => [
                        'id' => $existing->id,
                        'or_number' => $existing->or_number,
                    ],
                ]);
            }

            $cancelled = ConsumerPayment::create([
                'reading_id' => null,
                'consumer_id' => null,
                'account_name' => 'Cancelled',
                'payment_method' => null,
                'payment_amount' => 0,
                'amount_tendered' => 0,
                'change_amount' => 0,
                'or_number' => $orNumber,
                'paid_at' => null,
                'remarks' => $remarksValue,
                'created_by' => $this->getFormattedUserName(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cancelled OR # ' . $orNumber . ' saved.',
                'data' => [
                    'id' => $cancelled->id,
                    'or_number' => $cancelled->or_number,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Save cancelled OR error', [
                'or_number' => $request->input('or_number'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save cancelled OR: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Delete payment by OR number
     */
    public function deletePayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'or_number' => 'required|string'
            ]);

            $orNumber = $validated['or_number'];
            $lroRemarks = 'Payment OR#' . $orNumber;
            
            // Find payment by OR number
            $payment = ConsumerPayment::where('or_number', $orNumber)->first();

            // If no consumer_payment exists, still clean orphan LRO payment rows for this OR.
            if (!$payment) {
                $deletedOrphanLroRows = LROLedger::where('type', 'CM')
                    ->where('remarks', $lroRemarks)
                    ->delete();

                if ($deletedOrphanLroRows > 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No consumer payment found for OR # ' . $orNumber . ', but orphan LRO row(s) were deleted.',
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Payment with OR # ' . $orNumber . ' not found.'
                ], 404);
            }

            // Delete the payment (cascade delete will handle consumer_ledger payment entries).
            $payment->delete();

            // Always delete corresponding LRO payment rows (CM) tagged by OR in remarks.
            $deletedLroRows = LROLedger::where('type', 'CM')
                ->where('remarks', $lroRemarks)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment with OR # ' . $orNumber . ' has been deleted successfully.',
                'deleted_lro_rows' => $deletedLroRows,
            ]);
        } catch (\Exception $e) {
            Log::error('Delete payment error', [
                'or_number' => $request->input('or_number'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment: ' . $e->getMessage()
            ], 500);
        }
    }
}



