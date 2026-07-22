<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZone;
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
use App\Exports\BillPrintingExport;
use App\Exports\BillingRecordsExport;
use App\Imports\DmLedgerImport;
use App\Services\ConsumerMasterListService;
use App\Services\DownloadedReadingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;


if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class BillingProcessController extends Controller
{
    /** Billing adjustment constants (source of truth: paid_at only) */
    private const MONTHLY_PRINCIPAL = 195.00;
    private const PENALTY_RATE = 0.10;
   // private const PENALTY_PER_MONTH = 19.50;
    private const WMC_PER_MONTH = 20.00;

    /**
     * Display the billing processes page
     */
    public function index()
    {
        return view('processes.billing-processes', [
            'zones' => ConsumerZone::distinctZoneCodes(),
        ]);
    }

    /**
     * Distinct zone codes from consumer_zone for Billing Processes dropdowns.
     */
    public function getZones()
    {
        return response()->json([
            'success' => true,
            'data' => ConsumerZone::distinctZoneCodes()->values(),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function applyZoneCodeFilter($query, string $zone, string $column = 'cz.zone_code'): void
    {
        ConsumerZone::applyZoneCodeConstraint($query, $zone, $column);
    }

    
    
    // * Prepare meter reading data for:
    //  * - Meter Reading Preparation: zone + dates â†’ active consumers in zone only (excludes disconnected/inactive); can_save only when no existing schedules.
    //  * - Single Consumer: account_no + zone + dates â†’ that consumer (any status); can_save always; allows adding even if batch exists.
    //  * - Multiple Consumers: account_numbers + dates â†’ those consumers (any status); can_save always; allows adding even if batch exists.
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
            $consumer = ConsumerZone::where(function ($q) use ($accountNo) {
                $q->where(mr_col('account_no'), $accountNo)
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
            $consumers = ConsumerZone::where(function ($q) use ($accountNumbers, $normalizedAccounts) {
                foreach ($accountNumbers as $i => $acc) {
                    $norm = $normalizedAccounts[$i] ?? str_replace('-', '', $acc);
                    $q->orWhere(mr_col('account_no'), $acc)
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
            $consumersQuery = ConsumerZone::query()
                ->whereIn(DB::raw('UPPER(TRIM(COALESCE(status_code, "")))'), ['A', 'ACTIVE'])
                ->orderBy(mr_col('sequence'))
                ->orderBy(mr_col('account_no'));
            $this->applyZoneCodeFilter($consumersQuery, $zone, 'zone_code');
            $consumers = $consumersQuery->get();
        }

        $existingCount = 0;
        if ($effectiveZone) {
            $existingCount = MeterReadingSchedule::forZoneCode($effectiveZone)
                ->where(mr_col('bill_month'), $billMonthYmd)
                ->count();
        }
        if ($isAccountsScope) {
            $consumerZoneIds = $consumers->pluck(mr_col('id'))->filter()->unique()->values()->all();
            $existingForAccounts = MeterReadingSchedule::query()->whereIn(mr_col('consumer_zone_id'), $consumerZoneIds)
                ->where(mr_col('bill_month'), $billMonthYmd)
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
            $total = $currentBill + $wmc + $arrears;
            $data[] = [
                'sedr' => (string) $sedr++,
                'account_number' => $accountNoVal,
                'account_name' => $consumer->account_name ?? '',
                'address' => $consumer->address ?? '',
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
            'zone' => $effectiveZone ?? $zone ?? 'â€”',
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
            $existingSchedules = MeterReadingSchedule::forZoneCode($zone)
                ->where(mr_col('bill_month'), $billMonth->format('Y-m-d'))
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
                    $consumerZoneId = $scheduleData['consumer_zone_id'] ?? null;
                    if (!$consumerZoneId && !empty($scheduleData['account_number'])) {
                        $consumerZoneId = ConsumerZone::where(function ($query) use ($scheduleData) {
                            $accountNumber = $scheduleData['account_number'];
                            $query->where(mr_col('account_no'), $accountNumber)
                                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [str_replace('-', '', $accountNumber)]);
                        })->value(mr_col('id'));
                    }

                    if (!$consumerZoneId) {
                        throw new \RuntimeException('Consumer not found for account: ' . ($scheduleData['account_number'] ?? 'unknown'));
                    }

                    // Additive (save_scope = 'accounts'): skip if this consumer already has a schedule for this period
                    if ($saveScope === 'accounts') {
                        $billDateRow = isset($scheduleData['bill_date']) ? Carbon::parse($scheduleData['bill_date'])->format('Y-m-d') : $billDateYmd;
                        $dueDateRow = isset($scheduleData['due_date']) ? Carbon::parse($scheduleData['due_date'])->format('Y-m-d') : $dueDateYmd;
                        $disconnectionDateRow = isset($scheduleData['disconnection_date']) ? Carbon::parse($scheduleData['disconnection_date'])->format('Y-m-d') : $disconnectionDateYmd;
                        $alreadyExists = MeterReadingSchedule::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                            ->where(mr_col('bill_month'), $billMonth->format('Y-m-d'))
                            ->where(mr_col('bill_date'), $billDateRow)
                            ->where(mr_col('due_date'), $dueDateRow)
                            ->where(mr_col('disconnection_date'), $disconnectionDateRow)
                            ->exists();
                        if ($alreadyExists) {
                            continue;
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

                    // Previous reading: use edited value from UI (prev_read) â€” same logic for all process types (Meter Reading Preparation / Single / Multiple)
                    $prevRead = isset($scheduleData['prev_read'])
                        ? (is_numeric($scheduleData['prev_read']) ? (float) $scheduleData['prev_read'] : 0)
                        : 0;
                    
                    $schedule = MeterReadingSchedule::create(MeterReadingSchedule::filterTableAttributes([
                        'consumer_zone_id' => $consumerZoneId,
                        'bill_month' => Carbon::parse($scheduleData['bill_month']),
                        'bill_date' => Carbon::parse($scheduleData['bill_date']),
                        'due_date' => Carbon::parse($scheduleData['due_date']),
                        'disconnection_date' => Carbon::parse($scheduleData['disconnection_date']),
                        'previous_reading_date' => $previousReadingDate,
                        'previous_reading' => $prevRead,
                        'arrears' => $scheduleData['arrears'] ?? 0.00,
                        'status' => 'Prepared',
                        'sedr_number' => $scheduleData['sedr'],
                        'prepared_by' => $preparedBy,
                    ]));

                    $savedSchedules[] = $schedule->id;

                    $consumer = ConsumerZone::find($consumerZoneId);

                    if ($consumer) {
                        // Get the latest balance directly from consumer_ledgers table (source of truth)
                        // Follow the data in consumer_ledgers table - use the most recent entry by ID
                        // ID represents actual creation order, so highest ID = most recent entry
                        // Exclude any existing entry with the same schedule_id to avoid duplicates
                        $latestLedgerEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                            ->whereNotNull(mr_col('balance')) // Only entries with a balance value
                            ->where(function($query) use ($schedule) {
                                $query->whereNull(mr_col('schedule_id'))
                                      ->orWhere(mr_col('schedule_id'), '!=', $schedule->id); // Exclude this schedule if it already exists
                            })
                            ->orderBy(mr_col('id'), 'desc') // ID is the source of truth for creation order
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
     * Get previous reading and volume for a consumer by account number.
     * Checks (in priority order):
     *   1. downloaded_readings.current_reading (most recent actual reading)
     *   2. meter_reading_schedules.current_reading (latest completed/verified)
     *   3. consumer_ledgers.reading (legacy data)
     */
    private function getPreviousReading(string $accountNo)
    {
        // Find the consumer by account_no
        $consumer = ConsumerZone::query()->where(mr_col('account_no'), $accountNo)->first();

        if (! $consumer) {
            return [
                'date' => Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => 0,
                'volume' => 0,
                'arrears' => 0.00,
                'balance' => 0.00,
            ];
        }

        $normalizedAccount = str_replace('-', '', $accountNo);

        // Priority 1: Check downloaded_readings (most recent actual reading)
        $latestDownloadedReading = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->where(function ($query) use ($accountNo, $normalizedAccount) {
                $query->where(mr_col('cz.account_no'), $accountNo)
                    ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount]);
            })
            ->whereNotNull(mr_col('dr.current_reading'))
            ->where(mr_col('dr.current_reading'), '>', 0)
            ->select(
                'dr.current_reading as reading',
                'dr.consumption as volume',
                'dr.reading_date as date',
                'dr.current_bill',
                'mrs.arrears',
                'mrs.total_amount'
            )
            ->orderBy(mr_col('dr.reading_date'), 'desc')
            ->orderBy(mr_col('dr.created_at'), 'desc')
            ->first();

        if ($latestDownloadedReading) {
            // Get arrears from schedule (stored arrears value)
            $arrears = (float) ($latestDownloadedReading->arrears ?? 0);
            $latestBalance = (float) ($latestDownloadedReading->total_amount ?? 0);

            // If no arrears stored but balance exists, calculate from balance
            if ($arrears == 0 && $latestBalance > 0) {
                $currentBill = (float) ($latestDownloadedReading->current_bill ?? 0);
                $arrears = max(0, $latestBalance - $currentBill);
            }

            $result = [
                'date' => $latestDownloadedReading->date ? Carbon::parse($latestDownloadedReading->date)->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $latestDownloadedReading->reading,
                'volume' => $latestDownloadedReading->volume ?? 0,
                'arrears' => $arrears,
                'balance' => $latestBalance,
            ];

            Log::info('Previous reading from downloaded_readings', [
                'account_no' => $accountNo,
                'source' => 'downloaded_readings',
                'result' => $result,
            ]);

            return $result;
        }

        // Priority 2: Check meter_reading_schedules (latest completed reading)
        $latestSchedule = DB::table(mr_col('meter_reading_schedules as mrs'))
            ->where(mr_col('mrs.consumer_zone_id'), $consumer->id)
            ->whereNotNull(mr_col('mrs.current_reading'))
            ->where(mr_col('mrs.current_reading'), '>', 0)
            ->whereIn(mr_col('mrs.status'), ['Completed', 'Verified'])
            ->select(
                'mrs.current_reading as reading',
                'mrs.consumption as volume',
                'mrs.reading_date as date',
                'mrs.current_bill',
                'mrs.arrears',
                'mrs.total_amount'
            )
            ->orderBy(mr_col('mrs.reading_date'), 'desc')
            ->orderBy(mr_col('mrs.id'), 'desc')
            ->first();

        if ($latestSchedule) {
            // Get arrears from schedule (stored arrears value)
            $arrears = (float) ($latestSchedule->arrears ?? 0);
            $latestBalance = (float) ($latestSchedule->total_amount ?? 0);

            // If no arrears stored but balance exists, calculate from balance
            if ($arrears == 0 && $latestBalance > 0) {
                $currentBill = (float) ($latestSchedule->current_bill ?? 0);
                $arrears = max(0, $latestBalance - $currentBill);
            }

            $result = [
                'date' => $latestSchedule->date ? Carbon::parse($latestSchedule->date)->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $latestSchedule->reading,
                'volume' => $latestSchedule->volume ?? 0,
                'arrears' => $arrears,
                'balance' => $latestBalance,
            ];

            Log::info('Previous reading from meter_reading_schedules', [
                'account_no' => $accountNo,
                'source' => 'meter_reading_schedules',
                'result' => $result,
            ]);

            return $result;
        }

        // Priority 3: Check consumer_ledgers (legacy data)
        $latestLedger = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->whereNotNull(mr_col('reading'))
            ->where(mr_col('reading'), '>', 0)
            ->orderBy(mr_col('id'), 'DESC')
            ->first();

        if ($latestLedger) {
            // Get latest balance from ledger entries
            $latestBalanceEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                ->whereNotNull(mr_col('balance'))
                ->orderBy(mr_col('id'), 'DESC')
                ->first();

            $latestBalance = $latestBalanceEntry ? (float) ($latestBalanceEntry->balance ?? 0) : ($consumer->balance ?? 0.00);

            // Calculate arrears (balance minus current bill if available)
            $currentBill = $latestLedger->billamount ?? 0;
            $arrears = max(0, $latestBalance - $currentBill);

            $result = [
                'date' => $latestLedger->date ? Carbon::parse($latestLedger->date)->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $latestLedger->reading,
                'volume' => $latestLedger->volume ?? 0,
                'arrears' => $arrears,
                'balance' => $latestBalance,
            ];

            Log::info('Previous reading from consumer_ledgers', [
                'account_no' => $accountNo,
                'source' => 'consumer_ledgers',
                'ledger_id' => $latestLedger->id,
                'result' => $result,
            ]);

            return $result;
        }

        // Priority 4: Base reading on the consumer master (new consumer with
        // an existing non-zero meter â€” set from the main-consumer page).
        if (Schema::hasColumn('consumer_zone', 'base_reading')
            && $consumer->base_reading !== null
            && $consumer->base_reading !== ''
        ) {
            $baseReading = (int) $consumer->base_reading;
            $baseDate = null;
            if (Schema::hasColumn('consumer_zone', 'base_reading_date') && $consumer->base_reading_date) {
                try {
                    $baseDate = Carbon::parse($consumer->base_reading_date);
                } catch (\Throwable $e) {
                    $baseDate = null;
                }
            }

            $defaultBalance = $consumer->balance ?? 0.00;

            $result = [
                'date' => $baseDate ? $baseDate->format('m/d/Y') : Carbon::now()->subMonth()->format('m/d/Y'),
                'reading' => $baseReading,
                'volume' => 0,
                'arrears' => $defaultBalance,
                'balance' => $defaultBalance,
            ];

            Log::info('Previous reading from consumer base_reading (new consumer)', [
                'account_no' => $accountNo,
                'source'     => 'consumer_zone.base_reading',
                'result'     => $result,
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
            'balance' => $defaultBalance,
        ];
    }

    /**
     * Billing breakdown from consumer_ledgers + consumer_payments.
     *
     * A charge row is unpaid until covered by a consumer_payments record (reading_id / schedule).
     * Isolate latest unpaid BILLING first: billamount â†’ Current Bill; others on that row only â†’ WMC.
     * Unpaid PENALTY â†’ Penalty (skip if already paid).
     * All other unpaid amounts â†’ Arrears CY/PY by year (older billingsâ€™ billamount+others, DM debits, etc.).
     * DM: unpaid debit â†’ Arrears CY or PY by row date year.
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
            $coveredByReading = ConsumerPayment::forConsumerZone($consumerZoneId)
                ->where(mr_col('reading_id'), $downloadedReadingId)
                ->whereNotNull(mr_col('paid_at'))
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
            $readingIds = DownloadedReading::query()->where(mr_col('schedule_id'), $scheduleId)->pluck(mr_col('id'));
            if ($readingIds->isNotEmpty()) {
                $coveredByReading = ConsumerPayment::forConsumerZone($consumerZoneId)
                    ->whereIn(mr_col('reading_id'), $readingIds)
                    ->whereNotNull(mr_col('paid_at'))
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

        return ConsumerPayment::forConsumerZone($consumerZoneId)
            ->whereNull(mr_col('reading_id'))
            ->whereNotNull(mr_col('paid_at'))
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

        $query = ConsumerPayment::forConsumerZone($consumerZoneId)
            ->whereNull(mr_col('reading_id'))
            ->whereNotNull(mr_col('paid_at'))
            ->whereBetween('paid_at', [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]);
        if ($penaltyDate) {
            $query->where(mr_col('paid_at'), '>=', $penaltyDate->copy()->startOfDay()->format('Y-m-d H:i:s'));
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
            $readingIds = DownloadedReading::query()->where(mr_col('schedule_id'), $row->schedule_id)->pluck(mr_col('id'));
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
                $readingPenaltyQuery = ConsumerPayment::forConsumerZone($consumerZoneId)
                    ->whereIn(mr_col('reading_id'), $readingIds)
                    ->whereNotNull(mr_col('paid_at'));
                if ($penaltyDate) {
                    $readingPenaltyQuery->where(mr_col('paid_at'), '>=', $penaltyDate->copy()->startOfDay()->format('Y-m-d H:i:s'));
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
            $cycleResetEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
                ->where(mr_col('trans'), 'PAYMENT')
                ->whereNotNull(mr_col('balance'))
                ->whereRaw('ABS(balance) < 0.01')
                ->where(mr_col('date'), '<', $selectedMonthStart->format('Y-m-d'))
                ->orderBy(mr_col('date'), 'desc')
                ->orderBy(mr_col('id'), 'desc')
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
         * 1) Latest unpaid BILLING only â†’ Current Bill (billamount).
         * 2) WMC = SUM(unpaid BILLING others) across all unpaid months.
         * 3) Unpaid PENALTY rows â†’ Penalty (excluded if already paid via consumer_payments / penalties.paid_at).
         * 4) Arrears CY/PY = unpaid principal/carry only (older billing billamount + DM), no WMC/Penalty duplication.
         * Payment history is reflected via which rows are still â€œunpaidâ€ (consumer_payments coverage).
         */
        $ledgerQuery = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->whereRaw('COALESCE(date, txtime) <= ?', [$calculationCutoffYmd . ' 23:59:59'])
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc');
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

            // Latest unpaid billing only â†’ Current Bill (principal only).
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

            // Older unpaid billings â†’ arrears principal only, by year.
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

        $advancesAgg = DB::table(mr_col('consumer_ledgers'))
            ->where(mr_col('consumer_zone_id'), $consumerId)
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
     * Latest unpaid BILLING â†’ current bill + WMC; unpaid penalty; remainder â†’ arrears by year (payment history via coverage).
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

        $consumer = ConsumerZone::query()->where(mr_col('account_no'), $validated['account_no'])->first();
        if (!$consumer) {
            $normalized = str_replace('-', '', $validated['account_no']);
            $consumer = ConsumerZone::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
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

            $query = ConsumerZone::query();

            if ($searchType === 'account') {
                $query->where(mr_col('account_no'), 'like', '%' . $searchValue . '%');
            }

            if ($zone) {
                $this->applyZoneCodeFilter($query, $zone, 'zone_code');
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
                    'address' => $consumer->address ?? '',
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

        $consumer = ConsumerZone::where(function ($query) use ($accountNumber) {
                $query->where(mr_col('account_no'), $accountNumber)
                      ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [str_replace('-', '', $accountNumber)]);
            })
            ->first();

        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Account number not found. Please verify and try again.',
            ], 404);
        }

        $latestReading = DownloadedReading::with(['schedule.consumerZone', 'consumerZone'])
            ->forAccountNo($accountNumber)
            ->orderByDesc(DB::raw(
                Schema::hasColumn((new DownloadedReading())->getTable(), 'completed_at')
                    ? 'COALESCE(reading_date, completed_at, updated_at, created_at)'
                    : 'COALESCE(reading_date, updated_at, created_at)'
            ))
            ->orderByDesc(mr_col('id'))
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
    public function exportData(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|in:excel,pdf',
            'zone' => 'nullable|string',
            'process_type' => 'nullable|string',
            'reading_date' => 'nullable|date',
        ]);

        if (($validated['process_type'] ?? null) === 'Bill Printing') {
            return $this->exportBillPrinting(
                $validated['zone'] ?? null,
                $validated['reading_date'] ?? null
            );
        }

        return $this->exportBillingSchedules($validated['zone'] ?? null);
    }

    private function exportBillPrinting(?string $zone, ?string $readingDateInput): Response|JsonResponse
    {
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
        $records = $this->buildBillPrintingRecords($zone, $readingDate);

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No bill printing records found to export for the selected zone and reading date.',
            ], 404);
        }

        $zoneText = "Zone-{$zone}";
        $dateText = Carbon::parse($readingDate)->format('Ymd');
        $filename = "Bill-Printing-{$zoneText}-{$dateText}-" . Carbon::now()->format('His') . '.xlsx';

        return $this->downloadExportCollection($records, new BillPrintingExport($records), $filename);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function buildBillPrintingRecords(string $zone, string $readingDate): Collection
    {
        $downloadedRows = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select([
                'dr.id as downloaded_id',
                'cz.account_no as account_number',
                'cz.account_name',
                'cz.meter_number',
                'dr.consumption as volume',
                'dr.current_bill as downloaded_current_bill',
            ])
            ->where(function ($query) use ($zone) {
                $query->whereNotNull(mr_col('cz.zone_code'));
                $this->applyZoneCodeFilter($query, $zone, 'cz.zone_code');
            })
            ->whereDate('dr.reading_date', $readingDate)
            ->orderByDesc(mr_col('dr.id'))
            ->get();

        $rowsByAccount = collect($downloadedRows)
            ->unique(function ($row) {
                return strtoupper(trim((string) ($row->account_number ?? '')));
            })
            ->values();

        $sortedRows = $rowsByAccount->sort(function ($a, $b) {
            $na = $this->accountTailNumber($a->account_number ?? '');
            $nb = $this->accountTailNumber($b->account_number ?? '');
            if ($na === $nb) {
                return strnatcasecmp((string) ($a->account_number ?? ''), (string) ($b->account_number ?? ''));
            }

            return $na <=> $nb;
        })->values();

        return $sortedRows->map(function ($item) {
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
    }

    private function exportBillingSchedules(?string $zone): Response|JsonResponse
    {
        $records = $this->buildBillingScheduleRecords($zone);

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No billing records found to export for the selected filters.',
            ], 404);
        }

        $zoneText = $zone && $zone !== 'all' ? "Zone-{$zone}" : 'All-Zones';
        $filename = "Billing-Records-{$zoneText}-" . Carbon::now()->format('YmdHis') . '.xlsx';

        return $this->downloadExportCollection($records, new BillingRecordsExport($records), $filename);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function buildBillingScheduleRecords(?string $zone): Collection
    {
        $query = MeterReadingSchedule::query()
            ->with('consumerZone')
            ->joinConsumerZone()
            ->select('meter_reading_schedules.*')
            ->orderBy(mr_col('cz.zone_code'))
            ->orderByAccountNumberTail();

        if ($zone && $zone !== 'all') {
            $this->applyZoneCodeFilter($query, $zone, 'cz.zone_code');
        }

        return $query->get()->map(function (MeterReadingSchedule $item) {
            $currentBill = $item->current_bill !== null ? (float) $item->current_bill : 0.0;
            $arrears = $item->arrears !== null ? (float) $item->arrears : 0.0;
            $maintenanceCharge = $currentBill > 0 ? 20.00 : 0.00;
            $totalAmount = $item->total_amount !== null
                ? (float) $item->total_amount
                : ($currentBill + $arrears + $maintenanceCharge);

            return [
                'Account #' => $item->account_number ?? '',
                'Account Name' => $item->account_name ?? $item->consumerZone->account_name ?? '',
                'Meter #' => $item->meter_number ?? '',
                'Consumption' => $item->consumption !== null ? number_format((float) $item->consumption, 0) : '',
                'Water Maintenance Charge' => number_format($maintenanceCharge, 2),
                'Total Amount' => number_format($totalAmount, 2),
            ];
        });
    }

    /**
     * @param Collection<int, array<string, string>> $records
     */
    private function downloadExportCollection(
        Collection $records,
        object $export,
        string $filename
    ): BinaryFileResponse|StreamedResponse {
        if (class_exists(Excel::class)) {
            return Excel::download($export, $filename);
        }

        $headings = $export instanceof WithHeadings ? $export->headings() : [];
        $csvFilename = str_replace('.xlsx', '.csv', $filename);

        return response()->stream(function () use ($records, $headings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headings);

            foreach ($records as $record) {
                fputcsv($file, array_values($record));
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$csvFilename}\"",
        ]);
    }

    private function accountTailNumber(mixed $accountNumber): int
    {
        $parts = explode('-', (string) $accountNumber);
        $tail = trim((string) end($parts));

        return is_numeric($tail) ? (int) $tail : PHP_INT_MAX;
    }


    /**
     * Debug endpoint - Get all consumers info by zone
     */
    public function debugConsumers(Request $request)
    {
        $zone = $request->get('zone');
        
        if ($zone) {
            $consumers = ConsumerZone::query()->where(mr_col('zone_code'), $zone)->get();
            $grouped = $consumers->groupBy(mr_col('status_code'));
            
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
        $zones = ConsumerZone::select('zone_code as zone', DB::raw('count(*) as total'))
            ->groupBy(mr_col('zone_code'))
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
            ->joinConsumerZone()
            ->select(
                'cz.zone_code as batch_zone',
                'meter_reading_schedules.bill_month',
                'meter_reading_schedules.bill_date',
                'meter_reading_schedules.due_date',
                'meter_reading_schedules.disconnection_date'
            )
            ->selectRaw('COUNT(*) as schedule_count')
            ->groupBy(
                'cz.zone_code',
                'meter_reading_schedules.bill_month',
                'meter_reading_schedules.bill_date',
                'meter_reading_schedules.due_date',
                'meter_reading_schedules.disconnection_date'
            );

        if ($zoneFilter && $zoneFilter !== '' && $zoneFilter !== 'all') {
            $this->applyZoneCodeFilter($query, $zoneFilter, 'cz.zone_code');
        }

        if ($billMonthFilter && $billMonthFilter !== '' && $billMonthFilter !== 'all') {
            $query->where(mr_col('bill_month'), Carbon::parse($billMonthFilter)->format('Y-m-d'));
        }

        if ($sortBy === 'bill_month') {
            $query->orderByRaw(mr_col('meter_reading_schedules.bill_month ') . $sortDir . ', cz.zone_code ASC');
        } else {
            $query->orderByRaw(mr_col('cz.zone_code ') . $sortDir . ', meter_reading_schedules.bill_month DESC');
        }

        $batches = $query->get()
            ->map(function ($row) {
                $zoneCode = $row->batch_zone ?? null;

                return [
                    'zone' => $zoneCode !== null && $zoneCode !== '' ? (string) $zoneCode : null,
                    'bill_month' => $row->bill_month ? Carbon::parse($row->bill_month)->format('Y-m-d') : null,
                    'bill_date' => $row->bill_date ? Carbon::parse($row->bill_date)->format('Y-m-d') : null,
                    'due_date' => $row->due_date ? Carbon::parse($row->due_date)->format('Y-m-d') : null,
                    'disconnection_date' => $row->disconnection_date ? Carbon::parse($row->disconnection_date)->format('Y-m-d') : null,
                    'schedule_count' => (int) $row->schedule_count,
                ];
            });

        // Distinct bill months for dropdown (optionally filtered by zone)
        $distinctMonthsQuery = MeterReadingSchedule::query()
            ->joinConsumerZone()
            ->select('meter_reading_schedules.bill_month')
            ->whereNotNull(mr_col('meter_reading_schedules.bill_month'))
            ->distinct();
        if ($zoneFilter && $zoneFilter !== '' && $zoneFilter !== 'all') {
            $this->applyZoneCodeFilter($distinctMonthsQuery, $zoneFilter, 'cz.zone_code');
        }
        $distinct_bill_months = $distinctMonthsQuery->orderBy(mr_col('meter_reading_schedules.bill_month'), 'desc')->pluck(mr_col('bill_month'))->map(function ($d) {
            return $d ? Carbon::parse($d)->format('Y-m-d') : null;
        })->filter()->values()->toArray();

        $distinct_zones = MeterReadingSchedule::query()
            ->joinConsumerZone()
            ->whereNotNull(mr_col('cz.zone_code'))
            ->where(mr_col('cz.zone_code'), '!=', '')
            ->distinct()
            ->orderBy(mr_col('cz.zone_code'))
            ->pluck(mr_col('cz.zone_code'))
            ->map(fn ($z) => trim((string) $z))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $batches,
            'distinct_bill_months' => $distinct_bill_months,
            'distinct_zones' => $distinct_zones,
        ]);
    }
    /**
     * Get meter reading schedules by zone and period
     */
    public function getSchedules(Request $request)
    {
        $zone = $request->get('zone');
        $billMonth = $request->get('bill_month');

        $query = MeterReadingSchedule::with(['consumerZone', 'assignedReader']);

        if ($zone) {
            $query->forZoneCode($zone);
        }

        if ($billMonth) {
            $query->where(mr_col('bill_month'), Carbon::parse($billMonth)->format('Y-m-d'));
        }

        $schedules = $query->orderBy(mr_col('sedr_number'))->get();

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
            $updated = MeterReadingSchedule::query()->whereIn(mr_col('id'), $request->schedule_ids)
                ->update(MeterReadingSchedule::filterTableAttributes([
                    'assigned_reader_id' => $request->reader_id,
                    'status' => 'Assigned',
                ]));

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

            $deleted = MeterReadingSchedule::forZoneCode($zone)
                ->where(mr_col('bill_month'), $billMonth->format('Y-m-d'))
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

            $scheduleIds = MeterReadingSchedule::forZoneCode($zone)
                ->where(mr_col('bill_month'), $billMonth)
                ->where(mr_col('bill_date'), $billDate)
                ->where(mr_col('due_date'), $dueDate)
                ->where(mr_col('disconnection_date'), $disconnectionDate)
                ->pluck(mr_col('id'));

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

            MeterReadingSchedule::query()->whereIn(mr_col('id'), $scheduleIds)->update([
                'bill_month' => $newBillMonth,
                'bill_date' => $newBillDate,
                'due_date' => $newDueDate,
                'disconnection_date' => $newDisconnectionDate,
            ]);

            ConsumerLedger::query()->whereIn(mr_col('schedule_id'), $scheduleIds)->update(['due_date' => $newDueDate]);

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
     * Get downloaded readings for bill printing.
     * Fetches from downloaded_readings table joined with meter_reading_schedules.
     * Filters by zone and reading_date only.
     */
    public function getDownloadedReadings(Request $request): JsonResponse
    {
        $request->validate([
            'zone' => 'required|string',
            'reading_date' => 'required|date',
        ]);

        try {
            $zone = (string) $request->zone;
            $readingDate = Carbon::parse($request->reading_date);
            $readings = $this->fetchDownloadedReadingsForZone($zone, $readingDate->format('Y-m-d'));

            if ($readings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No downloaded readings found for Zone ' . $zone . ' on ' . $readingDate->format('F d, Y'),
                ], 404);
            }

            $firstReading = $readings->first();
            $billMonth = $firstReading->bill_month
                ? Carbon::parse($firstReading->bill_month)
                : Carbon::now();

            $formattedReadings = $this->formatDownloadedReadingsForBillPrinting($readings);

            return response()->json([
                'success' => true,
                'message' => count($formattedReadings) . ' downloaded reading(s) found for Zone ' . $zone,
                'data' => $formattedReadings,
                'summary' => [
                    'zone' => $zone,
                    'bill_month' => $billMonth->format('F Y'),
                    'reading_date' => $readingDate->format('F d, Y'),
                    'prepared_date' => Carbon::now()->format('F d, Y'),
                    'total_readings' => count($formattedReadings),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading downloaded readings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function fetchDownloadedReadingsForZone(string $zone, string $readingDateYmd): Collection
    {
        $selectColumns = [
            'dr.id as downloaded_id',
            'mrs.sedr_number as sedr',
            'cz.account_no as account_number',
            'cz.account_name',
            'cz.address',
            'cz.zone_code as zone',
            'cz.category_code as category',
            'cz.meter_number',
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
            'cp.payment_method',
            'cp.payment_amount',
            'cp.amount_tendered',
            'cp.change_amount',
            'cp.or_number as payment_reference',
            'cp.remarks as payment_remarks',
            'cp.paid_at',
        ];

        return DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->leftJoin(mr_col('consumer_payments as cp'), mr_col('cp.reading_id'), '=', mr_col('dr.id'))
            ->select($selectColumns)
            ->where(function ($query) use ($zone) {
                $query->whereNotNull(mr_col('cz.zone_code'));
                $this->applyZoneCodeFilter($query, $zone, 'cz.zone_code');
            })
            ->whereDate('dr.reading_date', $readingDateYmd)
            ->orderBy(mr_col('mrs.sedr_number'))
            ->get();
    }

    /**
     * @param Collection<int, object> $readings
     * @return list<array<string, mixed>>
     */
    private function formatDownloadedReadingsForBillPrinting(Collection $readings): array
    {
        $today = Carbon::now()->startOfDay();
        $formatted = [];

        foreach ($readings as $reading) {
            $formatted[] = $this->formatSingleDownloadedReading($reading, $today);
        }

        return collect($formatted)
            ->sortByDesc(mr_col('downloaded_id'))
            ->unique(mr_col('account_number'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSingleDownloadedReading(object $reading, Carbon $today): array
    {
        $consumption = $reading->volume ?? 0;
        $penalty = 0.00;
        $arrearsCy = 0.00;
        $arrearsPy = 0.00;
        $viewType = 'post_due';

        $consumer = $this->findConsumerByAccountNumber($reading->account_number ?? null);
        if ($consumer) {
            $dueDate = !empty($reading->due_date) ? Carbon::parse($reading->due_date)->startOfDay() : null;
            $viewType = ($dueDate && $today->lte($dueDate)) ? 'pre_due' : 'post_due';
            $breakdown = $this->getBillingBreakdownForConsumer((int) $consumer->id, $viewType, null, null);
            $penalty = (float) ($breakdown['penalty'] ?? 0);
            $arrearsCy = (float) ($breakdown['arrears_cy'] ?? 0);
            $arrearsPy = (float) ($breakdown['arrears_py'] ?? 0);
        } else {
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

        return [
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
            'payment_method' => $reading->payment_method ?? null,
            'payment_amount' => isset($reading->payment_amount) ? (float) $reading->payment_amount : null,
            'amount_tendered' => isset($reading->amount_tendered) ? (float) $reading->amount_tendered : null,
            'change_amount' => isset($reading->change_amount) ? (float) $reading->change_amount : null,
            'payment_reference' => $reading->payment_reference ?? null,
            'payment_remarks' => $reading->payment_remarks ?? null,
            'paid_at' => $reading->paid_at ? Carbon::parse($reading->paid_at)->format('Y-m-d H:i:s') : null,
        ];
    }

    private function findConsumerByAccountNumber(?string $accountNumber): ?ConsumerZone
    {
        if (empty($accountNumber)) {
            return null;
        }

        $consumer = ConsumerZone::query()->where(mr_col('account_no'), $accountNumber)->first();
        if ($consumer) {
            return $consumer;
        }

        $normalized = str_replace('-', '', $accountNumber);

        return ConsumerZone::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
    }

    /**
     * Calculate water bill based on consumption (cubic meters)
     * This is a basic calculation - adjust rates according to your actual tariff structure
     */
    private function calculateWaterBill(float $consumption)
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
     * Ledger remaining = same as Account Ledger footer (recalculated running balance, not stored balance column).
     * Penalty base = min(current bill, ledger remaining).
     *
     * @return array{reconciled_owed: float, penalty_base: float, ledger_remaining: float}
     */
    private function resolveSurchargePenaltyDetails(float $currentBill, float $arrears, ?ConsumerZone $consumer): array
    {
        $billAmount = max(0.0, $currentBill);
        $composedOwed = max(0.0, $currentBill + $arrears);

        if ($consumer) {
            $ledgerRemaining = max(
                0.0,
                ConsumerLedgerController::computeLedgerFooterBalance((int) $consumer->id, null)
            );

            $reconciledOwed = min($composedOwed, $ledgerRemaining);
            $penaltyBase = max(0.0, min($billAmount, $ledgerRemaining));
        } else {
            $ledgerRemaining = $composedOwed;
            $reconciledOwed = $composedOwed;
            $penaltyBase = max(0.0, min($billAmount, $composedOwed));
        }

        return [
            'reconciled_owed' => $reconciledOwed,
            'penalty_base' => $penaltyBase,
            'ledger_remaining' => $ledgerRemaining,
        ];
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

            // Schedules in zone with this bill_date and due_date already passed.
            // Partial payments are allowed; rows are excluded later only when reconciled balance shows nothing owed.
            $schedulesQuery = MeterReadingSchedule::query()
                ->leftJoin(mr_col('downloaded_readings as dr'), mr_col('dr.schedule_id'), '=', mr_col('meter_reading_schedules.id'))
                ->joinConsumerZone()
                ->where(function ($q) use ($zone) {
                    $this->applyZoneCodeFilter($q, $zone, 'cz.zone_code');
                })
                ->whereDate('meter_reading_schedules.bill_date', $billDate->format('Y-m-d'))
                ->whereNotNull(mr_col('meter_reading_schedules.due_date'))
                ->whereRaw('CAST(meter_reading_schedules.due_date AS DATE) < ?', [$today->format('Y-m-d')])
                ->select(
                    'meter_reading_schedules.id as schedule_id',
                    'cz.account_no as account_number',
                    'cz.account_name',
                    'cz.address',
                    'cz.zone_code as zone',
                    'cz.category_code as category',
                    'cz.meter_number',
                    'meter_reading_schedules.sedr_number as sedr',
                    'meter_reading_schedules.previous_reading_date as prev_date',
                    'meter_reading_schedules.previous_reading as prev_read',
                    'meter_reading_schedules.bill_date',
                    'meter_reading_schedules.due_date',
                    'meter_reading_schedules.consumer_zone_id',
                    'dr.id as downloaded_id',
                    'dr.current_reading as pres_read',
                    'dr.consumption as volume',
                    'dr.current_bill as dr_current_bill'
                );

            $rows = $schedulesQuery->get();

            $data = [];
            foreach ($rows as $row) {
                $consumer = !empty($row->consumer_zone_id)
                    ? ConsumerZone::find($row->consumer_zone_id)
                    : null;
                if (!$consumer && !empty($row->account_number)) {
                    $consumer = ConsumerZone::query()->where(mr_col('account_no'), $row->account_number)->first();
                }
                if (!$consumer && !empty($row->account_number)) {
                    $norm = str_replace('-', '', $row->account_number);
                    $consumer = ConsumerZone::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$norm])->first();
                }

                $arrearsBeforeBill = 0.00;
                $currentBill = (float) ($row->dr_current_bill ?? 0);
                if ($currentBill <= 0 && $consumer) {
                    $breakdown = $this->getBillingBreakdownForConsumer((int) $consumer->id, 'post_due', null, null);
                    $currentBill = (float) ($breakdown['current_bill'] ?? 0);
                }
                // Internal: balance before this schedule's BILLING (for reconcile with ledger footer)
                if ($consumer && !empty($row->schedule_id)) {
                    $billingLedger = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                        ->where(mr_col('schedule_id'), $row->schedule_id)
                        ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                        ->orderBy(mr_col('id'), 'asc')
                        ->first();
                    if ($billingLedger) {
                        $arrearsBeforeBill = ConsumerLedgerController::computeRunningBalanceBeforeLedgerEntry(
                            (int) $consumer->id,
                            (int) $billingLedger->id,
                            null
                        );
                    }
                }

                $surchargeDetails = $this->resolveSurchargePenaltyDetails((float) $currentBill, (float) $arrearsBeforeBill, $consumer);
                $penaltyBase = $surchargeDetails['penalty_base'];
                $reconciledOwed = $surchargeDetails['reconciled_owed'];
                $ledgerRemaining = $surchargeDetails['ledger_remaining'];

                $wmc = ($currentBill > 0) ? (float) self::WMC_PER_MONTH : 0.00;
                // Strict 10% of penalty base (no â‚±19.50 minimum â€” that overstated small balances)
                $calculatedPenalty = round($penaltyBase * (float) self::PENALTY_RATE, 2);
                // Arrears column = Account Ledger footer (computeLedgerFooterBalance); Total = that + penalty only
                $arrearsColumn = round(max(0.0, (float) $ledgerRemaining), 2);
                $total = round($arrearsColumn + $calculatedPenalty, 2);

                // Skip when reconciled debt is zero (ledger vs bill+arrears already aligned here)
                if ($reconciledOwed <= 0) {
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
                    'arrears' => $arrearsColumn,
                    'calculated_penalty' => $calculatedPenalty,
                    'penalty_base' => round($penaltyBase, 2),
                    'ledger_remaining' => round($ledgerRemaining, 2),
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
            $query = DB::table(mr_col('penalties'))
                ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('penalties.schedule_id'), '=', mr_col('mrs.id'))
                ->leftJoin(mr_col('consumer_zone as cz'), mr_col('penalties.consumer_zone_id'), '=', mr_col('cz.id'))
                ->select(
                    'penalties.id',
                    'penalties.date',
                    'penalties.due_date',
                    'penalties.reference',
                    'penalties.penalty_amount',
                    'penalties.bill_amount',
                    'cz.zone_code as mrs_zone',
                    'mrs.bill_month',
                    'cz.account_no as mrs_account_number',
                    'cz.account_name as mrs_account_name',
                    'cz.category_code as category',
                    'cz.zone_code',
                    'cz.sequence',
                    'cz.rate_code',
                    'cz.account_no',
                    'cz.account_name as cz_account_name'
                );

            // Apply zone filter (schedule zone or consumer_zone.zone_code)
            if ($zone && $zone !== '' && $zone !== 'All Zones') {
                $this->applyZoneCodeFilter($query, $zone, 'cz.zone_code');
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

            $rows = $query->orderBy(mr_col('penalties.due_date'), 'asc')
                ->orderBy(mr_col('cz.sequence'))
                ->orderBy(mr_col('penalties.id'))
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

            // Past-due schedules only; partial PAYMENT rows allowed â€” filter by reconciled balance below.
            $rows = MeterReadingSchedule::query()
                ->leftJoin(mr_col('downloaded_readings as dr'), mr_col('dr.schedule_id'), '=', mr_col('meter_reading_schedules.id'))
                ->joinConsumerZone()
                ->where(function ($q) use ($accountNumber, $normalizedAccount) {
                    $q->whereRaw('TRIM(cz.account_no) = ?', [$accountNumber])
                        ->orWhereRaw("REPLACE(TRIM(cz.account_no), '-', '') = ?", [$normalizedAccount]);
                })
                ->whereDate('meter_reading_schedules.bill_date', $billDate->format('Y-m-d'))
                ->whereNotNull(mr_col('meter_reading_schedules.due_date'))
                ->whereRaw('CAST(meter_reading_schedules.due_date AS DATE) < ?', [$today->format('Y-m-d')])
                ->select(
                    'meter_reading_schedules.id as schedule_id',
                    'cz.account_no as account_number',
                    'cz.account_name',
                    'cz.address',
                    'cz.zone_code as zone',
                    'cz.category_code as category',
                    'cz.meter_number',
                    'meter_reading_schedules.sedr_number as sedr',
                    'meter_reading_schedules.previous_reading_date as prev_date',
                    'meter_reading_schedules.previous_reading as prev_read',
                    'meter_reading_schedules.bill_date',
                    'meter_reading_schedules.due_date',
                    'meter_reading_schedules.consumer_zone_id',
                    'dr.id as downloaded_id',
                    'dr.current_reading as pres_read',
                    'dr.consumption as volume',
                    'dr.current_bill as dr_current_bill'
                )
                ->get();

            $data = [];
            foreach ($rows as $row) {
                $consumer = !empty($row->consumer_zone_id)
                    ? ConsumerZone::find($row->consumer_zone_id)
                    : null;
                if (!$consumer && !empty($row->account_number)) {
                    $consumer = ConsumerZone::query()->where(mr_col('account_no'), $row->account_number)->first();
                }
                if (!$consumer && !empty($row->account_number)) {
                    $norm = str_replace('-', '', $row->account_number);
                    $consumer = ConsumerZone::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$norm])->first();
                }

                $arrearsBeforeBill = 0.00;
                $currentBill = (float) ($row->dr_current_bill ?? 0);
                if ($currentBill <= 0 && $consumer) {
                    $breakdown = $this->getBillingBreakdownForConsumer((int) $consumer->id, 'post_due', null, null);
                    $currentBill = (float) ($breakdown['current_bill'] ?? 0);
                }

                if ($consumer && !empty($row->schedule_id)) {
                    $billingLedger = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                        ->where(mr_col('schedule_id'), $row->schedule_id)
                        ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                        ->orderBy(mr_col('id'), 'asc')
                        ->first();
                    if ($billingLedger) {
                        $arrearsBeforeBill = ConsumerLedgerController::computeRunningBalanceBeforeLedgerEntry(
                            (int) $consumer->id,
                            (int) $billingLedger->id,
                            null
                        );
                    }
                }

                $surchargeDetails = $this->resolveSurchargePenaltyDetails((float) $currentBill, (float) $arrearsBeforeBill, $consumer);
                $penaltyBase = $surchargeDetails['penalty_base'];
                $reconciledOwed = $surchargeDetails['reconciled_owed'];
                $ledgerRemaining = $surchargeDetails['ledger_remaining'];

                $wmc = ($currentBill > 0) ? (float) self::WMC_PER_MONTH : 0.00;
                // Strict 10% of penalty base (no fixed minimum)
                $calculatedPenalty = round($penaltyBase * (float) self::PENALTY_RATE, 2);
                $arrearsColumn = round(max(0.0, (float) $ledgerRemaining), 2);
                $total = round($arrearsColumn + $calculatedPenalty, 2);

                if ($reconciledOwed <= 0) {
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
                    'arrears' => $arrearsColumn,
                    'calculated_penalty' => $calculatedPenalty,
                    'penalty_base' => round($penaltyBase, 2),
                    'ledger_remaining' => round($ledgerRemaining, 2),
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
                $exists = Penalty::query()->where(mr_col('schedule_id'), $scheduleId)->exists();
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
                            $prevLedger = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                                ->whereNotNull(mr_col('balance'))
                                ->orderBy(mr_col('id'), 'desc')
                                ->first();
                            $previousBalance = $prevLedger ? (float) ($prevLedger->balance ?? 0) : 0.00;
                            $consumerZone = ConsumerZone::find($consumerZoneId);
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
                                'reading' => 0,
                                'volume' => 0,
                                'billamount' => 0,
                                'penalty' => $calculatedPenalty,
                                'others' => 0,
                                'debit' => $calculatedPenalty,
                                'credit' => 0,
                                'balance' => $newBalance,
                                'username' => $username,
                                'txtime' => $penaltyDateTime,
                            ]);

                            // Balance is tracked on consumer_ledgers only.
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

        $query = DB::table(mr_col('penalties'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('penalties.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), mr_col('penalties.consumer_zone_id'), '=', mr_col('cz.id'))
            ->select(
                'penalties.id',
                'penalties.date',
                'penalties.due_date',
                'penalties.reference',
                'penalties.penalty_amount',
                'cz.zone_code as mrs_zone',
                'mrs.bill_month',
                'cz.account_no as mrs_account_number',
                'cz.account_name as mrs_account_name',
                'cz.category_code as category',
                'cz.zone_code',
                'cz.sequence',
                'cz.rate_code',
                'cz.account_no',
                'cz.account_name as cz_account_name'
            );

        if ($zone && $zone !== '' && $zone !== 'All Zones') {
            $this->applyZoneCodeFilter($query, $zone, 'cz.zone_code');
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

        $rows = $query->orderBy(mr_col('penalties.due_date'), 'asc')
            ->orderBy(mr_col('cz.sequence'))
            ->orderBy(mr_col('penalties.id'))
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

        if (class_exists(Excel::class)) {
            return Excel::download(
                new class($data) implements FromCollection, WithHeadings, WithTitle {
                    protected Collection $rows;

                    public function __construct(array $rows)
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

    //     $zones = ConsumerZone::select('zone_code')
    //         ->distinct()
    //         ->orderBy(mr_col('zone_code'))
    //         ->pluck(mr_col('zone_code'));

    //     $baseQuery = ConsumerZone::query();

    //     if (!empty($filters['search'])) {
    //         $searchTerm = $filters['search'];
    //         $baseQuery->where(function ($q) use ($searchTerm) {
    //             $q->where(mr_col('account_name'), 'like', '%' . $searchTerm . '%')
    //               ->orWhere(mr_col('account_no'), 'like', '%' . $searchTerm . '%');
    //         });
    //     }

    //     if (!empty($filters['zone'])) {
    //         $baseQuery->where(mr_col('zone_code'), $filters['zone']);
    //     }

    //     if (!empty($filters['status'])) {
    //         $statusValue = $filters['status'];

    //         $statusMap = [
    //             'Active' => ['A', 'ACTIVE', 'Active', 'A - ACTIVE'],
    //              'Pending' => ['P', 'PENDING', 'Pending'],
    //              'Disconnected' => ['X', 'DISCONNECTED', 'Disconnected', 'D'],
    //         ];

    //         if (isset($statusMap[$statusValue])) {
    //             $baseQuery->whereIn(mr_col('status_code'), $statusMap[$statusValue]);
    //         } else {
    //             $baseQuery->where(mr_col('status_code'), $statusValue);
    //         }
    //     }

    //     if (!empty($filters['senior_citizen']) && Schema::hasColumn('consumer_zone', 'is_senior_citizen')) {
    //         $baseQuery->where(mr_col('is_senior_citizen'), true);
    //     }

    //     if (!empty($filters['meter_number'])) {
    //         $baseQuery->where(mr_col('meter_number'), 'like', '%' . $filters['meter_number'] . '%');
    //     }

    //     if (!empty($filters['address'])) {
    //         $baseQuery->where(mr_col('address'), 'like', '%' . $filters['address'] . '%');
    //     }

    //     if (!empty($filters['meter_location']) && Schema::hasColumn('consumer_zone', 'meter_location')) {
    //         $baseQuery->where(mr_col('meter_location'), 'like', '%' . $filters['meter_location'] . '%');
    //     }

    //     if (!empty($filters['ledger_status'])) {
    //         if ($filters['ledger_status'] === 'missing') {
    //             $baseQuery->whereDoesntHave('ledgers');
    //         } elseif ($filters['ledger_status'] === 'imported') {
    //             $baseQuery->whereHas('ledgers');
    //         }
    //     }

    //     $consumersQuery = (clone $baseQuery)->orderBy(mr_col('zone_code'));

    //     if (Schema::hasColumn('consumer_zone', 'route')) {
    //         $consumersQuery->orderBy(mr_col('route'));
    //     }

    //     if (Schema::hasColumn('consumer_zone', 'sequence')) {
    //         $consumersQuery->orderBy(mr_col('sequence'));
    //     }

    //     // Eager load ledger count to check if consumer has imported ledger entries
    //     $consumers = $consumersQuery->withCount('ledgers')->get();

    //     // Reading guide print fields:
    //     // - Prevdate / PrevRdg from previous month schedule
    //     // - PresRdg from latest/current generated month schedule
    //     $previousMonth = Carbon::now()->subMonthNoOverflow();
    //     $accountNos = $consumers->pluck(mr_col('account_no'))->filter()->values();
    //     $previousScheduleByAccount = collect();
    //     $latestScheduleByAccount = collect();
    //     if ($accountNos->isNotEmpty()) {
    //         $previousScheduleByAccount = MeterReadingSchedule::query()->whereIn(mr_col('account_number'), $accountNos)
    //             ->whereYear('bill_month', $previousMonth->year)
    //             ->whereMonth('bill_month', $previousMonth->month)
    //             ->orderBy(mr_col('bill_date'), 'desc')
    //             ->orderBy(mr_col('id'), 'desc')
    //             ->get(['account_number', 'bill_date', 'current_reading', 'previous_reading'])
    //             ->groupBy(mr_col('account_number'))
    //             ->map(function ($rows) {
    //                 return $rows->first();
    //             });

    //         $latestGeneratedBillMonth = MeterReadingSchedule::query()->whereNotNull(mr_col('bill_month'))->max('bill_month');
    //         if ($latestGeneratedBillMonth) {
    //             $latestMonth = Carbon::parse($latestGeneratedBillMonth);
    //             $latestScheduleByAccount = MeterReadingSchedule::query()->whereIn(mr_col('account_number'), $accountNos)
    //                 ->whereYear('bill_month', $latestMonth->year)
    //                 ->whereMonth('bill_month', $latestMonth->month)
    //                 ->orderBy(mr_col('bill_date'), 'desc')
    //                 ->orderBy(mr_col('id'), 'desc')
    //                 ->get(['account_number', 'bill_month', 'bill_date', 'current_reading', 'previous_reading'])
    //                 ->groupBy(mr_col('account_number'))
    //                 ->map(function ($rows) {
    //                     return $rows->first();
    //                 });
    //         }
    //     }

    //     $consumers->transform(function ($consumer) use ($previousScheduleByAccount, $latestScheduleByAccount) {
    //         $prev = $previousScheduleByAccount->get($consumer->account_no);
    //         $latest = $latestScheduleByAccount->get($consumer->account_no);
    //         $consumer->prev_bill_date = $prev && $prev->bill_date ? Carbon::parse($prev->bill_date)->format('m/d/Y') : '';
    //         $consumer->prev_pres_rdg = $latest && $latest->current_reading !== null ? (string) ((int) $latest->current_reading) : '';
    //         $consumer->prev_prev_rdg = $prev && $prev->current_reading !== null
    //             ? (string) ((int) $prev->current_reading)
    //             : ($prev && $prev->previous_reading !== null ? (string) ((int) $prev->previous_reading) : '');

    //         return $consumer;
    //     });

    //     $summaryByZone = (clone $baseQuery)
    //         ->select('zone_code as zone', DB::raw('COUNT(*) as total'))
    //         ->groupBy(mr_col('zone_code'))
    //         ->orderBy(mr_col('zone_code'))
    //         ->get();

    //     return view('reports.system-report.consumer-master-list', [
    //         'zones' => $zones,
    //         'consumers' => $consumers,
    //         'summaryByZone' => $summaryByZone,
    //         'filters' => $filters,
    //     ]);
    // }
    
    public function consumerMasterList(Request $request, ConsumerMasterListService $service): View
    {
        return view('reports.system-report.consumer-master-list', $service->buildReportData($request));
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
            'sundries.*.lro_ledger_id' => 'required_with:sundries|integer|min:1',
            'sundries.*.ledger'        => 'nullable|string|max:10',
            'sundries.*.acct_code'     => 'nullable|string|max:50',
            'sundries.*.amount'        => 'required_with:sundries|numeric|min:0.01',
        ]);

        if ($validated['amount_tendered'] < $validated['amount_due']) {
            return response()->json([
                'success' => false,
                'message' => 'Amount tendered must be equal to or greater than the amount due.',
            ], 422);
        }

        try {
            $paymentDetails = app(DownloadedReadingPaymentService::class)->process($validated);

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
    private function extractFirstName(string $formattedName)
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

        $consumer = ConsumerZone::find($request->consumer_zone_id);
        if (!$consumer) {
            return response()->json(['success' => false, 'message' => 'Consumer not found.'], 404);
        }

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $amount = (float) $request->amount;

        // Strict: do not allow duplicate DM for same consumer on same date
        $alreadyExists = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->where(mr_col('date'), $date)
            ->where(mr_col('trans'), 'DM')
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
    private function createOneDmLedgerEntry(ConsumerZone $consumer, string $date, float $amount): string
    {
        return $this->createOneMemoLedgerEntry($consumer, $date, abs($amount), 'DM');
    }

    /**
     * Insert one DM or CM ledger row.
     * Positive Excel amount → DM (debit). Negative Excel amount → CM (credit = abs value).
     */
    private function createOneMemoLedgerEntry(ConsumerZone $consumer, string $date, float $amount, string $trans): string
    {
        $trans = strtoupper($trans) === 'CM' ? 'CM' : 'DM';
        $magnitude = round(abs($amount), 2);
        $reference = $this->generateUniqueMemoReference($trans, $date);
        $username = \App\Support\AuthUsername::formatted();
        $dateTime = Carbon::parse($date)->startOfDay();
        $currentBalance = $consumer->getLedgerBalance();

        if ($trans === 'CM') {
            // Credit Memo: store magnitude on credit (same pattern as BillingAdjustment), balance decreases.
            $debit = 0;
            $credit = $magnitude;
            $newBalance = round($currentBalance - $magnitude, 2);
        } else {
            $debit = $magnitude;
            $credit = 0;
            $newBalance = round($currentBalance + $magnitude, 2);
        }

        ConsumerLedger::create([
            'consumer_zone_id' => $consumer->id,
            'consumer_payment_id' => null,
            'schedule_id' => null,
            'downloaded_reading_id' => null,
            'penalty_id' => null,
            'billing_adjustment_id' => null,
            'trans' => $trans,
            'date' => $date,
            'due_date' => null,
            'reference' => $reference,
            'reading' => 0,
            'volume' => 0,
            'billamount' => 0,
            'penalty' => 0,
            'others' => 0,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $newBalance,
            'username' => $username,
            'txtime' => $dateTime,
        ]);

        return $reference;
    }

    /**
     * Bulk DM upload via Excel. File must have columns: account_no, amount. Date is set by the user.
     */
    /**
     * Dedicated Files page for bulk DM Excel upload.
     */
    public function uploadDmIndex(): View
    {
        return view('consumer.upload-dm');
    }

    public function storeDmLedgerImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'date' => 'required|date',
        ]);

        $dmDate = Carbon::parse($request->date)->format('Y-m-d');
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
                    if ($amountVal === null || $amountVal === '' || (is_numeric($amountVal) && abs((float) $amountVal) < 0.00001)) {
                        continue; // Skip empty / zero rows
                    }
                    $errors[] = "Row {$rowNum}: Missing account_no.";
                    $failed++;
                    continue;
                }

                // Skip zero or blank amount quietly (no error)
                if ($amountVal === null || $amountVal === '' || abs((float) $amountVal) < 0.00001) {
                    continue;
                }

                // Skip non-numeric amount quietly
                if (!is_numeric($amountVal)) {
                    continue;
                }

                $trans = $amountVal < 0 ? 'CM' : 'DM';
                $magnitude = abs($amountVal);

                $consumer = ConsumerZone::query()->where(mr_col('account_no'), $accountNo)->first();
                if (!$consumer) {
                    $consumer = ConsumerZone::whereRaw('TRIM(account_no) = ?', [$accountNo])->first();
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

                // Strict: duplicate in DB – consumer already has same memo type for this date
                $alreadyExists = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                    ->where(mr_col('date'), $dmDate)
                    ->where(mr_col('trans'), $trans)
                    ->exists();
                if ($alreadyExists) {
                    $errors[] = "Row {$rowNum}: Duplicate – [{$accountNo}] already has a {$trans} for {$dmDate}.";
                    $failed++;
                    continue;
                }

                try {
                    DB::beginTransaction();
                    $this->createOneMemoLedgerEntry($consumer, $dmDate, $magnitude, $trans);
                    DB::commit();
                    $imported++;
                    $processedInThisFile[$accountNo] = $rowNum;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                    $failed++;
                }
            }

            $message = "DM/CM import completed. Imported: {$imported}, Failed: {$failed}.";
            if ($imported === 0 && $failed === 0 && count($rows) > 1) {
                $message = 'No data rows were processed. Check that the file has a header row (account_no, amount) and at least one data row with valid account numbers and amounts. Use positive amounts for DM and negative amounts for CM.';
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
     * Generate a unique DM/CM reference: 6-digit number (000001, 000002, ...).
     * Ensures no duplicate reference in consumer_ledgers.
     */
    private function generateUniqueMemoReference(string $trans, string $date): string
    {
        $trans = strtoupper($trans) === 'CM' ? 'CM' : 'DM';
        $maxAttempts = 100;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastRef = ConsumerLedger::query()->whereIn(mr_col('trans'), ['DM', 'CM'])
                ->whereRaw('reference REGEXP ?', ['^[0-9]{6}$'])
                ->orderByRaw(mr_col('CAST(reference AS UNSIGNED) DESC'))
                ->value(mr_col('reference'));

            $seq = 1;
            if ($lastRef !== null && preg_match('/^\d{1,6}$/', $lastRef)) {
                $seq = (int) $lastRef + 1;
            }

            if ($seq > 999999) {
                throw new \RuntimeException("{$trans} reference sequence exhausted (max 999999).");
            }

            $reference = str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

            if (!ConsumerLedger::query()->where(mr_col('reference'), $reference)->exists()) {
                return $reference;
            }
        }

        throw new \RuntimeException("Unable to generate unique {$trans} reference after {$maxAttempts} attempts.");
    }

    /**
     * @deprecated Use generateUniqueMemoReference()
     */
    private function generateUniqueDmReference(string $date): string
    {
        return $this->generateUniqueMemoReference('DM', $date);
    }
    
    /**
     * Save a cancelled OR marker for collection report.
     */
    public function storeCancelledOr(Request $request)
    {
        try {
            $validated = $request->validate([
                'or_number' => 'required|string|max:50',
                'transaction_date' => 'nullable|date',
            ]);

            $orNumber = trim((string) $validated['or_number']);
            $paidAt = !empty($validated['transaction_date'])
                ? Carbon::parse($validated['transaction_date'])->setTimeFromTimeString(now()->format('H:i:s'))
                : now();
            if ($orNumber === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'OR number is required.',
                ], 422);
            }

            $remarksValue = 'Cancelled OR#' . $orNumber;
            $existing = ConsumerPayment::query()->where(mr_col('or_number'), $orNumber)->first();

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

            $cancelled = ConsumerPayment::create(ConsumerPayment::filterTableAttributes([
                'reading_id' => null,
                'consumer_zone_id' => null,
                'payment_method' => null,
                'payment_amount' => 0,
                'amount_tendered' => 0,
                'change_amount' => 0,
                'or_number' => $orNumber,
                'paid_at' => $paidAt,
                'remarks' => $remarksValue,
                'created_by' => $this->getFormattedUserName(),
            ]));

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
            $payment = ConsumerPayment::query()->where(mr_col('or_number'), $orNumber)->first();

            // If no consumer_payment exists, still clean orphan LRO payment rows for this OR.
            if (!$payment) {
                $deletedOrphanLroRows = LROLedger::query()->where(mr_col('type'), 'CM')
                    ->where(mr_col('remarks'), $lroRemarks)
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
            $deletedLroRows = LROLedger::query()->where(mr_col('type'), 'CM')
                ->where(mr_col('remarks'), $lroRemarks)
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



