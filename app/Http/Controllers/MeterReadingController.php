<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZoneOne;
use App\Models\Penalty;
use App\Models\LROLedger;
use Carbon\Carbon;
use App\Imports\PreviousReadingImport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class MeterReadingController extends Controller
{
    /**
     * Users with role reader, excluding configured identifiers (email local-part / name).
     */
    private function meterReadersBaseQuery(): Builder
    {
        $query = User::query()
            ->where(function (Builder $q) {
                $q->where('role', 'reader')
                    ->orWhere('role', 'Reader')
                    ->orWhere('role', 'READER');
            });

        $tokens = array_unique(array_map('strtolower', array_filter(config('meter_reading.excluded_reader_identifiers', []))));
        if ($tokens !== []) {
            $query->whereNot(function (Builder $q) use ($tokens) {
                $q->where(function (Builder $inner) use ($tokens) {
                    foreach ($tokens as $t) {
                        $inner->orWhere(function (Builder $w) use ($t) {
                            $w->whereRaw('LOWER(SUBSTRING_INDEX(TRIM(IFNULL(email, "")), ?, 1)) = ?', ['@', $t])
                                ->orWhereRaw('LOWER(TRIM(IFNULL(email, ""))) = ?', [$t])
                                ->orWhereRaw('LOWER(TRIM(IFNULL(name, ""))) = ?', [$t]);
                        });
                    }
                });
            });
        }

        return $query;
    }

    private function scheduleAssignmentUpdatePayload(int $readerId): array
    {
        $payload = [
            'assigned_reader_id' => $readerId,
            'status' => 'Assigned',
        ];

        if (Schema::hasColumn('meter_reading_schedules', 'assigned_at')) {
            $payload['assigned_at'] = now();
        }

        return $payload;
    }

    private function scheduleUnassignmentUpdatePayload(): array
    {
        $payload = [
            'assigned_reader_id' => null,
            'status' => 'Prepared',
        ];

        if (Schema::hasColumn('meter_reading_schedules', 'assigned_at')) {
            $payload['assigned_at'] = null;
        }

        return $payload;
    }

    private function findScheduleByAccountNo(string $accountNo): ?MeterReadingSchedule
    {
        $normalized = str_replace('-', '', $accountNo);
        $consumer = ConsumerZoneOne::where(function ($q) use ($accountNo, $normalized) {
            $q->where('account_no', $accountNo)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized]);
        })->first();

        if (!$consumer) {
            return null;
        }

        return MeterReadingSchedule::where('consumer_zone_id', $consumer->id)
            ->orderByDesc('bill_month')
            ->orderByDesc('id')
            ->first();
    }

    private function downloadedReadingsHasCompletedAt(): bool
    {
        return Schema::hasColumn('downloaded_readings', 'completed_at');
    }

    private function applyDownloadedReadingConsumerJoin($query, string $drAlias = 'dr', string $mrsAlias = 'mrs', string $czAlias = 'cz')
    {
        $joins = $query->getQuery()->joins ?? [];
        $joined = collect($joins)->map(fn ($j) => (string) ($j->table ?? ''))->implode(' ');

        if (!str_contains($joined, "{$mrsAlias}")) {
            $query->leftJoin("meter_reading_schedules as {$mrsAlias}", "{$drAlias}.schedule_id", '=', "{$mrsAlias}.id");
        }

        if (!str_contains($joined, "{$czAlias}")) {
            $query->leftJoin("consumer_zone as {$czAlias}", function ($join) use ($drAlias, $mrsAlias, $czAlias) {
                $join->on("{$czAlias}.id", '=', "{$drAlias}.consumer_zone_id")
                    ->orOn("{$czAlias}.id", '=', "{$mrsAlias}.consumer_zone_id");
            });
        }

        return $query;
    }

    private function downloadedReadingBaseSelectColumns(): array
    {
        $cols = [
            'dr.id as downloaded_id',
            'dr.schedule_id',
            'dr.reader_id',
            'dr.consumer_zone_id',
            'cz.account_no as account_number',
            'cz.account_name',
            'cz.zone_code as zone',
            'dr.previous_reading',
            'dr.current_reading',
            'dr.consumption',
            'dr.current_bill as downloaded_current_bill',
            'dr.reading_date',
            'dr.status',
            'dr.reader_notes',
        ];

        if ($this->downloadedReadingsHasCompletedAt()) {
            $cols[] = 'dr.completed_at';
        }

        return array_merge($cols, [
            'cp.payment_method',
            'cp.payment_amount',
            'cp.amount_tendered',
            'cp.change_amount',
            'cp.or_number as official_receipt_number',
            'cp.remarks as payment_remarks',
            'cp.paid_at',
            'dr.created_at as downloaded_created_at',
            'dr.updated_at as downloaded_updated_at',
        ]);
    }

    private function applyDownloadedReadingRecencyOrder($query)
    {
        $query->orderByDesc('dr.reading_date');
        if ($this->downloadedReadingsHasCompletedAt()) {
            $query->orderByDesc('dr.completed_at');
        }
        return $query->orderByDesc('dr.created_at');
    }

    private function resolvePaymentConsumer(?ConsumerPayment $payment): ?ConsumerZoneOne
    {
        if (!$payment) {
            return null;
        }

        $consumerZoneId = $payment->consumer_zone_id ?? $payment->consumer_id;
        if ($consumerZoneId) {
            return ConsumerZoneOne::find($consumerZoneId);
        }

        return null;
    }

    /**
     * Display meter reading page with readers and their assignments
     */
    public function index()
    {
        $readers = $this->meterReadersBaseQuery()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Get reader assignments with zones and status
        $readerAssignments = [];
        
        foreach ($readers as $reader) {
            // Get unique zones assigned to this reader
            $assignments = MeterReadingSchedule::where('meter_reading_schedules.assigned_reader_id', $reader->id)
                ->joinConsumerZone()
                ->select(
                    'cz.zone_code as zone',
                    DB::raw('count(*) as total_schedules'),
                    DB::raw('MAX(meter_reading_schedules.status) as status')
                )
                ->groupBy('cz.zone_code')
                ->get();

            foreach ($assignments as $assignment) {
                $readerAssignments[] = [
                    'reader_id' => $reader->id,
                    'reader_name' => $this->formatName($reader),
                    'zone' => $assignment->zone,
                    'total_schedules' => $assignment->total_schedules,
                    'status' => $assignment->status,
                    'pda_number' => null, // Can be added later
                ];
            }
        }

        // Get readers without assignments
        $readersWithoutAssignments = $readers->filter(function($reader) use ($readerAssignments) {
            return !collect($readerAssignments)->where('reader_id', $reader->id)->count();
        });

        // Add readers without assignments to the list
        foreach ($readersWithoutAssignments as $reader) {
            $readerAssignments[] = [
                'reader_id' => $reader->id,
                'reader_name' => $this->formatName($reader),
                'zone' => '-',
                'total_schedules' => 0,
                'status' => 'Not Assigned',
                'pda_number' => null,
            ];
        }

        return view('processes.meter-reading', [
            'readers' => $readers,
            'readerAssignments' => $readerAssignments,
            'totalAssignments' => count($readerAssignments)
        ]);
    }

    /**
     * Format user name
     */
    private function formatName($user)
    {
        $name = strtoupper($user->last_name) . ', ' . strtoupper($user->first_name);
        
        if ($user->middle_name) {
            $name .= ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.';
        }
        
        if ($user->extension) {
            $name .= ' ' . strtoupper($user->extension);
        }
        
        return $name;
    }

       /**
     * Get reader assignments (API)
     */
    public function getReaderAssignments(Request $request)
    {
        $readerId = $request->filled('reader_id') ? (int) $request->input('reader_id') : null;
        $zone = $request->get('zone');
        $billMonthRaw = $request->get('bill_month');
        $getBillMonths = $request->get('get_bill_months');

        // If requesting available bill months
        if ($getBillMonths) {
            if (! $readerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'reader_id is required',
                ]);
            }

            $billMonths = MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->whereNotNull('bill_month')
                ->distinct()
                ->orderBy('bill_month', 'DESC')
                ->pluck('bill_month')
                ->map(function ($month) {
                    $date = Carbon::parse($month);

                    return [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('F Y').' ('.$date->format('Y-m-d').')',
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'bill_months' => $billMonths,
            ]);
        }

        if (! $readerId) {
            return response()->json([
                'success' => false,
                'message' => 'reader_id is required',
            ], 422);
        }

        $query = MeterReadingSchedule::with(['consumerZone', 'assignedReader']);
        $latestBillMonth = null;
        $billMonthNormalized = null;

        $query->where('assigned_reader_id', $readerId);

        if ($billMonthRaw !== null && $billMonthRaw !== '') {
            try {
                $bm = Carbon::parse($billMonthRaw);
                $query->whereYear('bill_month', $bm->year)
                    ->whereMonth('bill_month', $bm->month);
                $billMonthNormalized = $bm->copy()->startOfMonth()->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bill_month',
                ], 422);
            }
        } else {
            $latestBillMonth = MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->whereIn('status', ['Prepared', 'Assigned', 'In Progress', 'Completed'])
                ->orderBy('bill_month', 'DESC')
                ->value('bill_month');

            if ($latestBillMonth) {
                $lm = Carbon::parse($latestBillMonth);
                $query->whereYear('bill_month', $lm->year)
                    ->whereMonth('bill_month', $lm->month);
                $billMonthNormalized = $lm->copy()->startOfMonth()->format('Y-m-d');
            }
        }

        if ($zone) {
            $query->forZoneCode($zone);
        }

        $schedules = $query->orderBy('sedr_number')->get();

        return response()->json([
            'success' => true,
            'bill_month' => $billMonthNormalized ?? ($latestBillMonth ? Carbon::parse($latestBillMonth)->format('Y-m-d') : null),
            'data' => $schedules->values()->all(),
            'total' => $schedules->count(),
        ]);
    }
 /**
     * Upload Excel to bulk-update previous_reading in meter_reading_schedules.
     * Required columns: account_no, account_name, previous_reading.
     * Strict: no duplicate account_no in file; one schedule updated per account (latest by bill_month).
     */
    public function uploadPreviousReading(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $imported = 0;
        $failed = 0;
        $errors = [];

        try {
            $data = Excel::toArray(new PreviousReadingImport(), $request->file('file'));
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

            $header = $rows[0];
            $accountNoCol = $this->findColumnIndex($header, ['account_no', 'account_number', 'accountnumber', 'account no']);
            $accountNameCol = $this->findColumnIndex($header, ['account_name', 'accountname', 'account name', 'name']);
            $previousReadingCol = $this->findColumnIndex($header, ['previous_reading', 'previousreading', 'previous reading', 'prev_reading', 'prev_read']);

            if ($accountNoCol === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel must have column: account_no (or account_number).',
                    'imported' => 0,
                    'failed' => 0,
                    'errors' => [],
                ], 422);
            }
            if ($previousReadingCol === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel must have column: previous_reading (or prev_reading).',
                    'imported' => 0,
                    'failed' => 0,
                    'errors' => [],
                ], 422);
            }

            $processedInThisFile = []; // Strict: reject duplicate account_no in file

            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                $rowNum = $index + 1;

                $accountNo = isset($row[$accountNoCol]) ? trim((string) $row[$accountNoCol]) : null;
                if ($accountNo === '') {
                    $accountNo = null;
                }
                $accountName = ($accountNameCol !== null && isset($row[$accountNameCol])) ? trim((string) $row[$accountNameCol]) : '';
                $previousReadingVal = isset($row[$previousReadingCol]) ? $row[$previousReadingCol] : null;

                if (!$accountNo) {
                    $errors[] = "Row {$rowNum}: Missing account_no.";
                    $failed++;
                    continue;
                }

                // Strict: duplicate account_no in file
                if (isset($processedInThisFile[$accountNo])) {
                    $errors[] = "Row {$rowNum}: Duplicate in file – [{$accountNo}] already processed in row {$processedInThisFile[$accountNo]}.";
                    $failed++;
                    continue;
                }

                $previousReadingInt = null;
                if ($previousReadingVal !== null && $previousReadingVal !== '') {
                    if (is_numeric($previousReadingVal)) {
                        $previousReadingInt = (int) round((float) $previousReadingVal);
                        if ($previousReadingInt < 0) {
                            $errors[] = "Row {$rowNum}: previous_reading must be >= 0.";
                            $failed++;
                            continue;
                        }
                    } else {
                        $errors[] = "Row {$rowNum}: previous_reading must be numeric.";
                        $failed++;
                        continue;
                    }
                } else {
                    $errors[] = "Row {$rowNum}: Missing or invalid previous_reading.";
                    $failed++;
                    continue;
                }

                // Match by account_number (normalize for comparison)
                $schedule = $this->findScheduleByAccountNo($accountNo);

                if (!$schedule) {
                    $errors[] = "Row {$rowNum}: No schedule found for account [{$accountNo}].";
                    $failed++;
                    continue;
                }

                $schedule->previous_reading = $previousReadingInt;
                $schedule->save();
                $processedInThisFile[$accountNo] = $rowNum;
                $imported++;
            }

            return response()->json([
                'success' => true,
                'message' => $imported > 0 ? "Updated previous_reading for {$imported} account(s)." : 'No rows updated.',
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            Log::error('Upload previous_reading failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Find column index by possible header names (case-insensitive, spaces/underscores normalized).
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
     * Update the previous_reading for a consumer's latest meter reading schedule
     * from the main-consumer page (Meter Reading card → Save Previous Reading).
     *
     * The new value is stored as a MANUAL OVERRIDE on the schedule:
     *   - meter_reading_schedules.previous_reading           (kept in sync)
     *   - meter_reading_schedules.previous_reading_override  (signals override)
     *   - meter_reading_schedules.previous_reading_override_at / _by
     *
     * BillingProcessController::getPreviousReading() inspects this override
     * before any other source so the next Meter Reading Preparation will use
     * the corrected value as Prev. Read.
     *
     * The existing BILLING entry in consumer_ledgers and any downloaded_readings
     * rows are intentionally NOT touched — they remain historical records.
     */
    public function updateConsumerMeterReading(Request $request)
    {
        $validated = $request->validate([
            'schedule_id'      => 'required|integer|exists:meter_reading_schedules,id',
            'account_no'       => 'required|string',
            'previous_reading' => 'required|integer|min:0',
        ]);

        $scheduleId = (int) $validated['schedule_id'];
        $accountNo  = trim((string) $validated['account_no']);
        $newPrev    = (int) $validated['previous_reading'];

        try {
            $schedule = MeterReadingSchedule::with('consumerZone')->find($scheduleId);
            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meter reading schedule not found.',
                ], 404);
            }

            $normalizedAccount = str_replace('-', '', $accountNo);
            $consumerMatches = $schedule->consumer_zone_id
                && ConsumerZoneOne::where('id', $schedule->consumer_zone_id)
                    ->where(function ($q) use ($accountNo, $normalizedAccount) {
                        $q->where('account_no', $accountNo)
                            ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount]);
                    })
                    ->exists();
            if (!$consumerMatches) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account number does not match the schedule.',
                ], 422);
            }

            $oldPrev = $schedule->previous_reading !== null ? (int) $schedule->previous_reading : null;

            $hasOverrideColumn = Schema::hasColumn('meter_reading_schedules', 'previous_reading_override');

            $schedule->previous_reading = $newPrev;
            if ($hasOverrideColumn) {
                $schedule->previous_reading_override = $newPrev;
                $schedule->previous_reading_override_at = Carbon::now();
                $schedule->previous_reading_override_by = optional(auth()->user())->name;
            }
            $schedule->save();

            Log::info('Consumer previous_reading override saved (next-billing only)', [
                'schedule_id'  => $scheduleId,
                'account_no'   => $accountNo,
                'old_previous' => $oldPrev,
                'new_previous' => $newPrev,
                'has_override_column' => $hasOverrideColumn,
                'user'         => optional(auth()->user())->name,
            ]);

            return response()->json([
                'success'          => true,
                'message'          => 'Previous reading saved. It will take effect on the next billing.',
                'schedule_id'      => $scheduleId,
                'previous_reading' => $newPrev,
            ]);
        } catch (\Throwable $e) {
            Log::error('updateConsumerMeterReading failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'account_no'  => $accountNo,
                'trace'       => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save previous reading: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save the BASE READING for a consumer (consumer_zone.base_reading).
     *
     * Used for NEW consumers whose water meter is NOT brand-new — i.e. the
     * meter already shows a non-zero value but no readings have been billed
     * yet. The base reading is consumed by
     * BillingProcessController::getPreviousReading() as the last-resort
     * fallback (Priority 4) so the first Meter Reading Preparation uses the
     * configured starting value instead of 0.
     */
    public function updateConsumerBaseReading(Request $request)
    {
        $validated = $request->validate([
            'account_no'        => 'required|string',
            'base_reading'      => 'required|integer|min:0',
            'base_reading_date' => 'nullable|date',
        ]);

        $accountNo   = trim((string) $validated['account_no']);
        $newBase     = (int) $validated['base_reading'];
        $baseDate    = $validated['base_reading_date'] ?? null;

        if (!Schema::hasColumn('consumer_zone', 'base_reading')) {
            return response()->json([
                'success' => false,
                'message' => 'Base reading is not supported in this database (missing column). Run the latest migrations.',
            ], 500);
        }

        try {
            $normalizedAccount = str_replace('-', '', $accountNo);
            $upperAccount = strtoupper($accountNo);

            $consumer = ConsumerZoneOne::where('account_no', $accountNo)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [$upperAccount])
                ->first();

            if (!$consumer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Consumer not found.',
                ], 404);
            }

            // Refuse to set a base reading once the consumer already has any
            // reading history (downloaded readings, completed/in-progress
            // schedules, or BILLING ledger rows). The base value would
            // conflict with established billing data.
            $hasDownloadedReading = DB::table('downloaded_readings')
                ->where('consumer_zone_id', $consumer->id)
                ->exists();

            $hasScheduleHistory = MeterReadingSchedule::where('consumer_zone_id', $consumer->id)
                ->where(function ($query) {
                    $query->whereNotNull('current_reading')
                        ->orWhereNotNull('reading_date')
                        ->orWhereIn('status', ['Completed', 'Verified', 'In Progress']);
                })
                ->exists();

            $hasBillingLedger = DB::table('consumer_ledgers')
                ->where('consumer_zone_id', $consumer->id)
                ->whereIn('trans', ['BILLING', 'BILL'])
                ->whereNotNull('reading')
                ->where('reading', '>', 0)
                ->exists();

            if ($hasDownloadedReading || $hasScheduleHistory || $hasBillingLedger) {
                Log::info('updateConsumerBaseReading blocked: consumer has reading history', [
                    'account_no'             => $accountNo,
                    'has_downloaded_reading' => $hasDownloadedReading,
                    'has_schedule_history'   => $hasScheduleHistory,
                    'has_billing_ledger'     => $hasBillingLedger,
                    'user'                   => optional(auth()->user())->name,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Base reading is locked: this consumer already has Current/Previous reading history.',
                ], 422);
            }

            $oldBase = $consumer->base_reading !== null ? (int) $consumer->base_reading : null;

            $consumer->base_reading = $newBase;
            $consumer->base_reading_date = $baseDate
                ? Carbon::parse($baseDate)->format('Y-m-d')
                : Carbon::now()->format('Y-m-d');
            $consumer->base_reading_at = Carbon::now();
            $consumer->base_reading_by = optional(auth()->user())->name;
            $consumer->save();

            Log::info('Consumer base_reading saved from main-consumer page', [
                'account_no' => $accountNo,
                'old_base'   => $oldBase,
                'new_base'   => $newBase,
                'base_date'  => $consumer->base_reading_date,
                'user'       => optional(auth()->user())->name,
            ]);

            return response()->json([
                'success'           => true,
                'message'           => 'Base reading saved. It will be used on the first Meter Reading Preparation.',
                'account_no'        => $consumer->account_no,
                'base_reading'      => $newBase,
                'base_reading_date' => $consumer->base_reading_date instanceof \DateTimeInterface
                    ? $consumer->base_reading_date->format('Y-m-d')
                    : (string) $consumer->base_reading_date,
            ]);
        } catch (\Throwable $e) {
            Log::error('updateConsumerBaseReading failed: ' . $e->getMessage(), [
                'account_no' => $accountNo,
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save base reading: ' . $e->getMessage(),
            ], 500);
        }
    }

    
    /**
     * Assign schedules by zone to a specific reader
     */
    public function assignSchedulesToReader(Request $request)
    {
        $request->validate([
            'reader_id' => 'required|exists:users,id',
            'zone' => 'required|string',
            'bill_month' => 'required|date'
        ]);

        try {
            $readerId = $request->reader_id;
            $zone = $request->zone;
            $billMonth = Carbon::parse($request->bill_month)->format('Y-m-d');

            // Check if reader exists and has role 'reader'
            $reader = User::find($readerId);
            if (!in_array(strtolower($reader->role), ['reader'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not a meter reader'
                ], 422);
            }

            // Get all unassigned or prepared schedules for this zone and bill month
            $schedules = MeterReadingSchedule::forZoneCode($zone)
                ->where('bill_month', $billMonth)
                ->whereIn('status', ['Prepared'])
                ->whereNull('assigned_reader_id')
                ->get();

            if ($schedules->isEmpty()) {
                $alreadyAssigned = MeterReadingSchedule::forZoneCode($zone)
                    ->where('bill_month', $billMonth)
                    ->whereNotNull('assigned_reader_id')
                    ->count();

                $message = $alreadyAssigned > 0
                    ? 'All schedules for Zone ' . $zone . ' for this bill month are already assigned to a reader.'
                    : 'No prepared schedules found for Zone ' . $zone . ' for this bill month. Please prepare and save schedules in Billing Processes first.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }

            $scheduleIds = $schedules->pluck('id')->all();
            $updated = MeterReadingSchedule::whereIn('id', $scheduleIds)
                ->update($this->scheduleAssignmentUpdatePayload($readerId));

            return response()->json([
                'success' => true,
                'message' => 'Successfully assigned ' . $updated . ' schedule(s) in Zone ' . $zone . ' to ' . $this->formatName($reader),
                'assigned_count' => $updated,
                'reader_name' => $this->formatName($reader),
                'zone' => $zone
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    // /**
    //  * Get available readers for assignment
    //  */
    // public function getAvailableReaders()
    // {
    //     $readers = User::where('role', 'reader')
    //         ->orWhere('role', 'Reader')
    //         ->orWhere('role', 'READER')
    //         ->orderBy('last_name')
    //         ->orderBy('first_name')
    //         ->get()
    //         ->map(function($reader) {
    //             return [
    //                 'id' => $reader->id,
    //                 'name' => $this->formatName($reader),
    //                 'email' => $reader->email
    //             ];
    //         });

    //     return response()->json([
    //         'success' => true,
    //         'data' => $readers,
    //         'total' => $readers->count()
    //     ]);
    // }
    
    /**
     * Get available readers for assignment
     */
    public function getAvailableReaders()
    {
        $readers = $this->meterReadersBaseQuery()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function($reader) {
                return [
                    'id' => $reader->id,
                    'name' => $this->formatName($reader),
                    'email' => $reader->email
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $readers,
            'total' => $readers->count()
        ]);
    }


    /**
     * Get available zones for assignment
     */
    public function getAvailableZones(Request $request)
    {
        $billMonth = $request->get('bill_month');

        $query = MeterReadingSchedule::query()
            ->joinConsumerZone()
            ->select('cz.zone_code as zone', DB::raw('count(*) as total_schedules'))
            ->whereIn('meter_reading_schedules.status', ['Prepared'])
            ->whereNull('meter_reading_schedules.assigned_reader_id')
            ->groupBy('cz.zone_code');

        if ($billMonth) {
            $query->where('meter_reading_schedules.bill_month', Carbon::parse($billMonth)->format('Y-m-d'));
        }

        $zones = $query->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
            'total' => $zones->count()
        ]);
    }

    /**
     * Unassign schedules (remove reader assignment)
     */
    public function unassignSchedules(Request $request)
    {
        $request->validate([
            'zone' => 'required|string',
            'bill_month' => 'required|date'
        ]);

        try {
            $zone = $request->zone;
            $billMonth = Carbon::parse($request->bill_month)->format('Y-m-d');

            $updated = MeterReadingSchedule::forZoneCode($zone)
                ->where('bill_month', $billMonth)
                ->where('status', 'Assigned')
                ->update($this->scheduleUnassignmentUpdatePayload());

            return response()->json([
                'success' => true,
                'message' => 'Successfully unassigned ' . $updated . ' schedule(s) from Zone ' . $zone,
                'unassigned_count' => $updated
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unassigning schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download schedules for mobile app
     */
    public function downloadSchedulesForMobile(Request $request)
    {
        $request->validate([
            'reader_id' => 'nullable|exists:users,id',
            'zone' => 'nullable|string',
            'bill_month' => 'nullable|date'
        ]);

        try {
            $query = MeterReadingSchedule::with(['consumer', 'assignedReader'])
                ->whereIn('status', ['Assigned', 'In Progress']);

            if ($request->reader_id) {
                $query->where('assigned_reader_id', $request->reader_id);
            }

            if ($request->zone) {
                $query->forZoneCode($request->zone);
            }

            if ($request->bill_month) {
                $query->where('bill_month', Carbon::parse($request->bill_month)->format('Y-m-d'));
            }

            $schedules = $query->orderBy('sedr_number')->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No schedules found for download'
                ], 404);
            }

            // Format data for mobile app
            $firstSchedule = $schedules->first();
            /** @var \Carbon\Carbon|null $billMonth */
            $billMonth = $firstSchedule->bill_month;
            $mobileData = [
                'download_info' => [
                    'downloaded_at' => now()->toDateTimeString(),
                    'total_schedules' => $schedules->count(),
                    'zones' => $schedules->pluck('zone')->unique()->values(),
                    'bill_month' => $billMonth ? $billMonth->format('F Y') : null
                ],
                'reader_info' => $firstSchedule->assignedReader ? [
                    'id' => $firstSchedule->assignedReader->id,
                    'name' => $this->formatName($firstSchedule->assignedReader),
                    'email' => $firstSchedule->assignedReader->email
                ] : null,
                'schedules' => $schedules->map(function (MeterReadingSchedule $schedule) {
                    $prevReadingDate = $schedule->previous_reading_date;
                    $scheduleBillMonth = $schedule->bill_month;
                    $scheduleBillDate = $schedule->bill_date;
                    $scheduleDueDate = $schedule->due_date;
                    return [
                        'id' => $schedule->id,
                        'sedr_number' => $schedule->sedr_number,
                        'account_number' => $schedule->account_number,
                        'account_name' => $schedule->account_name,
                        'address' => $schedule->address,
                        'zone' => $schedule->zone,
                        'category' => $schedule->category,
                        'meter_number' => $schedule->meter_number,
                        'previous_reading' => $schedule->previous_reading,
                        'previous_reading_date' => $prevReadingDate instanceof \DateTimeInterface ? $prevReadingDate->format('m/d/Y') : null,
                        'current_reading' => $schedule->current_reading,
                        'consumption' => $schedule->consumption,
                        'bill_month' => $scheduleBillMonth instanceof \DateTimeInterface ? $scheduleBillMonth->format('Y-m-d') : null,
                        'bill_date' => $scheduleBillDate instanceof \DateTimeInterface ? $scheduleBillDate->format('m/d/Y') : null,
                        'due_date' => $scheduleDueDate instanceof \DateTimeInterface ? $scheduleDueDate->format('m/d/Y') : null,
                        'status' => $schedule->status
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'message' => 'Schedules ready for mobile app',
                'data' => $mobileData
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error preparing download: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display Download Reading page
     */
    public function downloadReadingPage()
    {
        $readers = $this->meterReadersBaseQuery()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Get summary of assignments
        $assignmentsSummary = MeterReadingSchedule::select(
                'assigned_reader_id',
                DB::raw('COUNT(*) as total_routes'),
                DB::raw('SUM(CASE WHEN status = "Assigned" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress'),
                DB::raw('SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed')
            )
            ->whereNotNull('assigned_reader_id')
            ->groupBy('assigned_reader_id')
            ->get()
            ->keyBy('assigned_reader_id');

        return view('processes.download-reading', compact('readers', 'assignmentsSummary'));
    }
 /**
     * JSON summary of reader assignments for the download-reading page (badge updates).
     */
    public function getAssignmentsSummary()
    {
        $rows = MeterReadingSchedule::select(
                'assigned_reader_id',
                DB::raw('COUNT(*) as total_routes'),
                DB::raw('SUM(CASE WHEN status = "Assigned" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress'),
                DB::raw('SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed')
            )
            ->whereNotNull('assigned_reader_id')
            ->groupBy('assigned_reader_id')
            ->get();

        $summary = [];
        foreach ($rows as $row) {
            $summary[(int) $row->assigned_reader_id] = [
                'total_routes' => (int) $row->total_routes,
                'pending'     => (int) $row->pending,
                'in_progress' => (int) $row->in_progress,
                'completed'   => (int) $row->completed,
            ];
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }
    /**
     * Display billing payment page focused on downloaded readings
     */
    public function billingPayment()
    {
        $zoneStats = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->on('cz.id', '=', 'dr.consumer_zone_id')
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select(
                'cz.zone_code as zone',
                DB::raw('MAX(dr.reading_date) as latest_reading_date'),
                DB::raw('COUNT(*) as total_downloaded')
            )
            ->whereNotNull('cz.zone_code')
            ->groupBy('cz.zone_code')
            ->orderBy('cz.zone_code')
            ->get();

        $zones = $zoneStats->pluck('zone');

        $latestReadingDates = $zoneStats->mapWithKeys(function ($stat) {
            $latest = $stat->latest_reading_date ? Carbon::parse($stat->latest_reading_date)->format('Y-m-d') : null;
            return [$stat->zone => $latest];
        });

        $defaultZone = $zones->first();
        $defaultReadingDate = $defaultZone && $latestReadingDates->has($defaultZone)
            ? $latestReadingDates->get($defaultZone)
            : Carbon::now()->format('Y-m-d');

        // Get latest payment record from consumer_payments table
        $latestPaymentRecord = DB::table('consumer_payments')
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc')
            ->first();

        // Calculate pending payments - count downloaded_readings without payments
        $pendingPayments = DB::table('downloaded_readings as dr')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->where(function($query) {
                $query->where('dr.status', '!=', 'paid')
                      ->orWhereNull('cp.paid_at');
            })
            ->count();

        $summaryMetrics = [
            'total_zones' => $zones->count(),
            'downloaded_today' => DownloadedReading::whereDate('reading_date', Carbon::today())->count(),
            'pending_payments' => $pendingPayments,
            'latest_payment' => $latestPaymentRecord && isset($latestPaymentRecord->paid_at)
                ? Carbon::parse($latestPaymentRecord->paid_at)->format('F d, Y g:i A')
                : null,
        ];

        return view('transaction.billing_payment', [
            'zones' => $zones,
            'defaultZone' => $defaultZone,
            'defaultReadingDate' => $defaultReadingDate,
            'summaryMetrics' => $summaryMetrics,
            'latestReadingDates' => $latestReadingDates,
        ]);
    }

    /**
     * Look up billing/payment data for a given account number and bill month.
     * Fetches all data from downloaded_readings table joined with meter_reading_schedules.
     */
    public function lookupBillingRecord(Request $request)
    {
        $request->validate([
            'account_number' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string'],
            'bill_month' => ['nullable', 'string'],
            'or_number' => ['nullable', 'string'],
        ]);

        $accountNumber = $request->input('account_number') ? strtoupper(trim($request->input('account_number'))) : null;
        $accountName = $request->input('account_name') ? strtoupper(trim($request->input('account_name'))) : null;
        $billMonthInput = trim((string) $request->input('bill_month', ''));
        $orNumberInput = trim((string) $request->input('or_number', ''));

        // Require either account_number, account_name, or or_number
        if (!$accountNumber && !$accountName && $orNumberInput === '') {
            return response()->json([
                'success' => false,
                'message' => 'Either account number, account name, or OR number is required.',
            ], 422);
        }
        
        $normalizedAccount = $accountNumber ? str_replace('-', '', $accountNumber) : null;

        // Resolve bill month if provided - handle multiple formats
        $billMonth = $billMonthInput !== '' ? $this->resolveBillMonth($billMonthInput) : null;

        if ($billMonthInput !== '' && !$billMonth) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bill month format. Use MM-YYYY, MM/YYYY, or DD/MM/YYYY.',
            ], 422);
        }

        $billMonthDate = $billMonth?->copy()->startOfMonth();

        $lookupSuccessMessage = null; // Overridden when account has no billing schedule (allow form to load; arrears from ledger)

        try {
        $reading = null;
        $orLookupPayment = null;

        // Lookup by OR # from consumer_payments table
        if ($orNumberInput !== '') {
            $payment = ConsumerPayment::where('or_number', $orNumberInput)->first();
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'OR number not found.',
                ], 404);
            }
            $orLookupPayment = $payment;
            if ($payment->reading_id) {
                $dr = DB::table('downloaded_readings')->where('id', $payment->reading_id)->first();
                if (!$dr) {
                    // Linked reading missing: fall back to consumer_payment + consumer so the form can still load and be updated
                    $consumer = $this->resolvePaymentConsumer($payment);
                    if (!$consumer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment record found but linked reading and consumer not found. Cannot load form.',
                    ], 404);
                }
                $accountNumber = $consumer->account_no ? strtoupper(trim($consumer->account_no)) : null;
                    $normalizedAccount = $accountNumber ? str_replace('-', '', $accountNumber) : null;
                    $billMonthDateObj = $billMonthDate ? $billMonthDate->format('Y-m-d') : null;
                    $reading = (object) [
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code ?? null,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => (float) ($payment->current_bill ?? 0),
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => $payment->payment_method ?? null,
                        'payment_amount' => $payment->payment_amount ?? null,
                        'amount_tendered' => $payment->amount_tendered ?? null,
                        'change_amount' => $payment->change_amount ?? null,
                        'official_receipt_number' => $payment->or_number,
                        'payment_remarks' => $payment->remarks ?? null,
                        'paid_at' => $payment->paid_at ?? null,
                        'schedule_account_name' => $consumer->account_name,
                        'address' => $consumer->address1 ?? '',
                        'category' => $consumer->category_code ?? '',
                        'meter_number' => $consumer->meter_number ?? null,
                        'bill_month' => $billMonthDateObj,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => (float) ($payment->current_bill ?? 0),
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ];
                } else {
                    $drQuery = DB::table('downloaded_readings as dr');
                    $this->applyDownloadedReadingConsumerJoin($drQuery);
                    $drRow = $drQuery
                        ->where('dr.id', $payment->reading_id)
                        ->select(
                            'dr.id as downloaded_id',
                            'dr.schedule_id',
                            'dr.reader_id',
                            'cz.account_no as account_number',
                            'cz.account_name',
                            'cz.zone_code as zone',
                            'cz.address',
                            'cz.category_code as category',
                            'cz.meter_number',
                            'dr.previous_reading',
                            'dr.current_reading',
                            'dr.consumption',
                            'dr.current_bill as downloaded_current_bill',
                            'dr.reading_date',
                            'dr.status',
                            'dr.reader_notes',
                            'dr.created_at as downloaded_created_at',
                            'dr.updated_at as downloaded_updated_at',
                            'mrs.sedr_number',
                            'mrs.bill_month',
                            'mrs.bill_date',
                            'mrs.due_date',
                            'mrs.disconnection_date',
                            'mrs.previous_reading_date',
                            'mrs.current_bill as schedule_current_bill',
                            'mrs.arrears',
                            'mrs.total_amount',
                            'mrs.status as schedule_status'
                        )
                        ->first();

                    $consumerForDr = null;
                    if ($drRow && !empty($dr->consumer_zone_id)) {
                        $consumerForDr = ConsumerZoneOne::find($dr->consumer_zone_id);
                    } elseif ($drRow && $drRow->schedule_id) {
                        $czId = DB::table('meter_reading_schedules')->where('id', $drRow->schedule_id)->value('consumer_zone_id');
                        if ($czId) {
                            $consumerForDr = ConsumerZoneOne::find($czId);
                        }
                    }

                    $reading = (object) [
                        'downloaded_id' => $drRow->downloaded_id ?? $dr->id,
                        'schedule_id' => $drRow->schedule_id ?? $dr->schedule_id,
                        'reader_id' => $drRow->reader_id ?? $dr->reader_id ?? null,
                        'account_number' => $drRow->account_number ?? $consumerForDr?->account_no,
                        'account_name' => $drRow->account_name ?? $consumerForDr?->account_name,
                        'zone' => $drRow->zone ?? $consumerForDr?->zone_code,
                        'previous_reading' => $drRow->previous_reading ?? $dr->previous_reading ?? 0,
                        'current_reading' => $drRow->current_reading ?? $dr->current_reading ?? null,
                        'consumption' => $drRow->consumption ?? $dr->consumption ?? 0,
                        'downloaded_current_bill' => $drRow->downloaded_current_bill ?? $dr->current_bill ?? ($drRow->schedule_current_bill ?? 0),
                        'reading_date' => $drRow->reading_date ?? $dr->reading_date ?? null,
                        'status' => $drRow->status ?? $dr->status ?? 'Prepared',
                        'reader_notes' => $drRow->reader_notes ?? $dr->reader_notes ?? null,
                        'completed_at' => $this->downloadedReadingsHasCompletedAt() ? ($dr->completed_at ?? null) : null,
                        'payment_method' => $payment->payment_method ?? null,
                        'payment_amount' => $payment->payment_amount ?? null,
                        'amount_tendered' => $payment->amount_tendered ?? null,
                        'change_amount' => $payment->change_amount ?? null,
                        'official_receipt_number' => $payment->or_number,
                        'payment_remarks' => $payment->remarks ?? null,
                        'paid_at' => $payment->paid_at ?? null,
                        'downloaded_created_at' => $drRow->downloaded_created_at ?? $dr->created_at ?? null,
                        'downloaded_updated_at' => $drRow->downloaded_updated_at ?? $dr->updated_at ?? null,
                        'sedr_number' => $drRow->sedr_number ?? null,
                        'schedule_account_name' => $drRow->account_name ?? $consumerForDr?->account_name,
                        'address' => $drRow->address ?? $consumerForDr?->address,
                        'category' => $drRow->category ?? $consumerForDr?->category_code,
                        'meter_number' => $drRow->meter_number ?? $consumerForDr?->meter_number,
                        'bill_month' => $drRow->bill_month ?? null,
                        'bill_date' => $drRow->bill_date ?? null,
                        'due_date' => $drRow->due_date ?? null,
                        'disconnection_date' => $drRow->disconnection_date ?? null,
                        'previous_reading_date' => $drRow->previous_reading_date ?? null,
                        'schedule_current_bill' => $drRow->schedule_current_bill ?? null,
                        'arrears' => $drRow->arrears ?? null,
                        'total_amount' => $drRow->total_amount ?? null,
                        'schedule_status' => $drRow->schedule_status ?? null,
                    ];
                    $accountNumber = $reading->account_number ? strtoupper(trim($reading->account_number)) : null;
                    $normalizedAccount = $accountNumber ? str_replace('-', '', $accountNumber) : null;
                }
            } else {
                $consumer = $this->resolvePaymentConsumer($payment);
                if (!$consumer) {
                    // Support "Others" payments where consumer_id is null and only account_name is stored.
                    $paidAt = $payment->paid_at ? Carbon::parse($payment->paid_at) : null;
                    $billMonthDateObj = $billMonthDate
                        ? $billMonthDate->format('Y-m-d')
                        : ($paidAt ? $paidAt->copy()->startOfMonth()->format('Y-m-d') : null);

                    $accountNumber = null;
                    $normalizedAccount = null;
                    $reading = (object) [
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => null,
                        'account_name' => $payment->account_name ?? '',
                        'zone' => null,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => (float) ($payment->current_bill ?? 0),
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => $payment->payment_method ?? null,
                        'payment_amount' => $payment->payment_amount ?? null,
                        'amount_tendered' => $payment->amount_tendered ?? null,
                        'change_amount' => $payment->change_amount ?? null,
                        'official_receipt_number' => $payment->or_number,
                        'payment_remarks' => $payment->remarks ?? null,
                        'paid_at' => $payment->paid_at ?? null,
                        'schedule_account_name' => $payment->account_name ?? '',
                        'address' => '',
                        'category' => '',
                        'meter_number' => null,
                        'bill_month' => $billMonthDateObj,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => (float) ($payment->current_bill ?? 0),
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ];
                } else {
                    $accountNumber = $consumer->account_no ? strtoupper(trim($consumer->account_no)) : null;
                    $normalizedAccount = $accountNumber ? str_replace('-', '', $accountNumber) : null;
                    $billMonthDateObj = $billMonthDate ? $billMonthDate->format('Y-m-d') : null;
                    $reading = (object) [
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code ?? null,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                    'downloaded_current_bill' => 0.0,
                    'reading_date' => null,
                    'status' => 'Prepared',
                    'reader_notes' => null,
                    'completed_at' => null,
                    'payment_method' => $payment->payment_method ?? null,
                    'payment_amount' => $payment->payment_amount ?? null,
                    'amount_tendered' => $payment->amount_tendered ?? null,
                    'change_amount' => $payment->change_amount ?? null,
                    'official_receipt_number' => $payment->or_number,
                    'payment_remarks' => $payment->remarks ?? null,
                    'paid_at' => $payment->paid_at ?? null,
                    'schedule_account_name' => $consumer->account_name,
                    'address' => $consumer->address1 ?? '',
                    'category' => $consumer->category_code ?? '',
                    'meter_number' => $consumer->meter_number ?? null,
                    'bill_month' => $billMonthDateObj,
                    'bill_date' => null,
                    'due_date' => null,
                    'disconnection_date' => null,
                    'previous_reading_date' => null,
                    'schedule_current_bill' => 0.0,
                    'arrears' => null,
                    'total_amount' => null,
                    'schedule_status' => null,
                    'sedr_number' => null,
                    'downloaded_created_at' => null,
                    'downloaded_updated_at' => null,
                    'payment_reference' => null,
                ];
                    
                }
            }
            $lookupSuccessMessage = 'Billing record loaded by OR number.';
        }

        if ($reading === null) {
        // Query downloaded_readings table with LEFT JOIN to handle cases where schedule might not exist
        $readingQuery = DB::table('downloaded_readings as dr')
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->on('cz.id', '=', 'dr.consumer_zone_id')
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select(array_merge($this->downloadedReadingBaseSelectColumns(), [
                'mrs.sedr_number',
                'cz.account_name as schedule_account_name',
                'cz.address',
                'cz.category_code as category',
                'cz.meter_number',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.disconnection_date',
                'mrs.previous_reading_date',
                'mrs.current_bill as schedule_current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'mrs.status as schedule_status',
            ]))
            ->where(function ($query) use ($accountNumber, $accountName, $normalizedAccount) {
                if ($accountNumber) {
                    $query->where('cz.account_no', $accountNumber)
                          ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNumber))]);
                }
                if ($accountName) {
                    $query->orWhereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $accountName . '%']);
                }
            });

        // Filter by bill month if provided - check both schedule bill_month and reading_date
        if ($billMonthDate) {
            $readingQuery->where(function ($query) use ($billMonthDate) {
                $query->whereDate('mrs.bill_month', $billMonthDate->format('Y-m-d'))
                      ->orWhere(function ($q) use ($billMonthDate) {
                          // Also match by reading_date if bill_month doesn't match
                          $q->whereNull('mrs.bill_month')
                            ->whereYear('dr.reading_date', $billMonthDate->year)
                            ->whereMonth('dr.reading_date', $billMonthDate->month);
                      });
            });
        }

        // Order by most recent reading first
        $reading = $this->applyDownloadedReadingRecencyOrder($readingQuery)->first();

        // If no result with schedule join, try querying downloaded_readings directly
        if (!$reading) {
            $directQuery = DB::table('downloaded_readings as dr')
                ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
                ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
                ->leftJoin('consumer_zone as cz', function ($join) {
                    $join->on('cz.id', '=', 'dr.consumer_zone_id')
                        ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
                })
                ->select($this->downloadedReadingBaseSelectColumns())
                ->where(function ($query) use ($accountNumber, $accountName, $normalizedAccount) {
                    if ($accountNumber) {
                        $query->where('cz.account_no', $accountNumber)
                              ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                              ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNumber))]);
                    }
                    if ($accountName) {
                        $query->orWhereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $accountName . '%']);
                    }
                });

            // Filter by reading_date if bill month provided
            if ($billMonthDate) {
                $directQuery->where(function ($query) use ($billMonthDate) {
                    $query->whereYear('dr.reading_date', $billMonthDate->year)
                          ->whereMonth('dr.reading_date', $billMonthDate->month);
                });
            }

            $reading = $this->applyDownloadedReadingRecencyOrder($directQuery)->first();

            // If still no result, try to get schedule data separately
            if ($reading && $reading->schedule_id) {
                $schedule = DB::table('meter_reading_schedules')
                    ->where('id', $reading->schedule_id)
                    ->first();
                
                if ($schedule) {
                    $consumer = null;
                    if (!empty($schedule->consumer_zone_id)) {
                        $consumer = ConsumerZoneOne::find($schedule->consumer_zone_id);
                    }
                    $readingArray = (array) $reading;
                    $readingArray['sedr_number'] = $schedule->sedr_number ?? null;
                    $readingArray['schedule_account_name'] = $consumer?->account_name;
                    $readingArray['address'] = $consumer?->address;
                    $readingArray['category'] = $consumer?->category_code;
                    $readingArray['meter_number'] = $consumer?->meter_number;
                    $readingArray['bill_month'] = $schedule->bill_month ?? null;
                    $readingArray['bill_date'] = $schedule->bill_date ?? null;
                    $readingArray['due_date'] = $schedule->due_date ?? null;
                    $readingArray['disconnection_date'] = $schedule->disconnection_date ?? null;
                    $readingArray['previous_reading_date'] = $schedule->previous_reading_date ?? null;
                    // Keep downloaded_current_bill if exists, otherwise use schedule's current_bill
                    $readingArray['schedule_current_bill'] = $schedule->current_bill ?? null;
                    $readingArray['arrears'] = $schedule->arrears ?? null;
                    $readingArray['total_amount'] = $schedule->total_amount ?? null;
                    $readingArray['schedule_status'] = $schedule->status ?? null;
                    $reading = (object) $readingArray;
                }
            }
        }

        if (!$reading) {
            $searchTerm = $accountNumber ?: $accountName;
            $searchType = $accountNumber ? 'account number' : 'account name';

            // Check if account exists in consumer_zone table
            $consumerExists = false;
            if ($accountNumber) {
                $normalizedAccount = str_replace('-', '', $accountNumber);
                $consumerExists = \App\Models\ConsumerZoneOne::where(function($query) use ($accountNumber, $normalizedAccount) {
                    $query->where('account_no', $accountNumber)
                          ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($accountNumber))]);
                })->exists();
            } elseif ($accountName) {
                $consumerExists = \App\Models\ConsumerZoneOne::whereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $accountName . '%'])->exists();
            }

            // Check if schedule exists for this account (any month)
            $scheduleExists = false;
            if ($accountNumber) {
                $normalizedAccount = str_replace('-', '', $accountNumber);
                $scheduleExists = DB::table('meter_reading_schedules as mrs')
                    ->join('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
                    ->where(function ($query) use ($accountNumber, $normalizedAccount) {
                        $query->where('cz.account_no', $accountNumber)
                              ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                              ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNumber))]);
                    })
                    ->exists();
            } elseif ($accountName) {
                $scheduleExists = DB::table('meter_reading_schedules as mrs')
                    ->join('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
                    ->whereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $accountName . '%'])
                    ->exists();
            }

            if (!$consumerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account ' . $searchType . ' "' . $searchTerm . '" not found in the system. Please verify the account number.',
                ], 404);
            }
            if (!$scheduleExists) {
                // Allow form to load so user can record payment; breakdown/arrears (e.g. Arrears — Previous Year) will come from ledger via bill-month-details.
                $consumer = null;
                if ($accountNumber) {
                    $normalizedAccount = str_replace('-', '', $accountNumber);
                    $consumer = \App\Models\ConsumerZoneOne::where(function ($q) use ($accountNumber, $normalizedAccount) {
                        $q->where('account_no', $accountNumber)
                          ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($accountNumber))]);
                    })->first();
                } elseif ($accountName) {
                    $consumer = \App\Models\ConsumerZoneOne::whereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $accountName . '%'])->first();
                }
                if ($consumer) {
                    $reading = (object) [
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => 0.0,
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => null,
                        'payment_amount' => null,
                        'amount_tendered' => null,
                        'change_amount' => null,
                        'official_receipt_number' => null,
                        'payment_remarks' => null,
                        'paid_at' => null,
                        'schedule_account_name' => $consumer->account_name,
                        'address' => $consumer->address1 ?? '',
                        'category' => $consumer->category_code ?? '',
                        'meter_number' => $consumer->meter_number ?? '',
                        'bill_month' => $billMonthDate ? $billMonthDate->format('Y-m-d') : null,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => 0.0,
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ];
                    $lookupSuccessMessage = 'Account "' . $searchTerm . '" exists but has no billing schedule. The account may need to be added to a billing cycle first. Unpaid amounts from previous years can be entered in Arrears — Previous Year.';
                }
            } else {
            // No completed meter reading yet – use schedule for this bill month so payment form can open (breakdown from ledger)
            if ($billMonthDate && ($accountNumber || $accountName)) {
                $scheduleQuery = DB::table('meter_reading_schedules as mrs')
                    ->leftJoin('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
                    ->whereDate('mrs.bill_month', $billMonthDate->format('Y-m-d'));
                if ($accountNumber) {
                    $normalizedAccount = str_replace('-', '', $accountNumber);
                    $scheduleQuery->where(function ($q) use ($accountNumber, $normalizedAccount) {
                        $q->where('cz.account_no', $accountNumber)
                          ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNumber))]);
                    });
                } else {
                    $scheduleQuery->whereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $accountName . '%']);
                }
                $scheduleRow = $scheduleQuery->select('mrs.*', 'cz.account_no', 'cz.account_name', 'cz.zone_code', 'cz.address', 'cz.category_code', 'cz.meter_number')->first();
                if ($scheduleRow) {
                    $scheduleId = $scheduleRow->id;
                    $dr = \App\Models\DownloadedReading::where('schedule_id', $scheduleId)->first();
                    if (!$dr) {
                        $dr = \App\Models\DownloadedReading::create([
                            'consumer_zone_id' => $scheduleRow->consumer_zone_id,
                            'schedule_id' => $scheduleId,
                            'previous_reading' => $scheduleRow->previous_reading ?? 0,
                            'current_reading' => $scheduleRow->current_reading ?? null,
                            'consumption' => $scheduleRow->consumption ?? 0,
                            'current_bill' => $scheduleRow->current_bill ?? 0,
                            'reading_date' => $scheduleRow->bill_date ?? $billMonthDate->format('Y-m-d'),
                            'status' => 'Prepared',
                        ]);
                    }
                    $base = (array) $dr->toArray();
                    $reading = (object) array_merge($base, [
                        'downloaded_id' => $dr->id,
                        'downloaded_current_bill' => $dr->current_bill ?? $scheduleRow->current_bill ?? null,
                        'schedule_id' => $dr->schedule_id,
                        'account_number' => $scheduleRow->account_no ?? $accountNumber,
                        'account_name' => $scheduleRow->account_name ?? '',
                        'zone' => $scheduleRow->zone_code ?? '',
                        'bill_month' => $scheduleRow->bill_month ?? null,
                        'bill_date' => $scheduleRow->bill_date ?? null,
                        'due_date' => $scheduleRow->due_date ?? null,
                        'disconnection_date' => $scheduleRow->disconnection_date ?? null,
                        'previous_reading_date' => $scheduleRow->previous_reading_date ?? null,
                        'schedule_current_bill' => $scheduleRow->current_bill ?? null,
                        'arrears' => $scheduleRow->arrears ?? null,
                        'total_amount' => $scheduleRow->total_amount ?? null,
                        'schedule_status' => $scheduleRow->status ?? null,
                        'sedr_number' => $scheduleRow->sedr_number ?? null,
                        'schedule_account_name' => $scheduleRow->account_name ?? null,
                        'address' => $scheduleRow->address ?? null,
                        'category' => $scheduleRow->category_code ?? null,
                        'meter_number' => $scheduleRow->meter_number ?? null,
                    ]);
                    $paymentRow = DB::table('consumer_payments')->where('reading_id', $dr->id)->first();
                    if ($paymentRow) {
                        $reading->payment_method = $paymentRow->payment_method ?? null;
                        $reading->payment_amount = $paymentRow->payment_amount ?? null;
                        $reading->amount_tendered = $paymentRow->amount_tendered ?? null;
                        $reading->change_amount = $paymentRow->change_amount ?? null;
                        $reading->official_receipt_number = $paymentRow->or_number ?? null;
                        $reading->payment_remarks = $paymentRow->remarks ?? null;
                        $reading->paid_at = $paymentRow->paid_at ?? null;
                    }
                }
            }

            if (!$reading) {
                // Still no downloaded/schedule reading for this bill month, but account + schedules exist.
                // Build a minimal virtual "reading" from the consumer record so payment can still proceed
                // and the breakdown will come purely from the ledger (paid_at-only logic).
                // Use same normalized matching as consumerExists so we find consumer when account_no format differs (e.g. 011-12-250 vs 11-12-250).
                $consumer = null;
                if ($accountNumber) {
                    $normalizedAccount = str_replace('-', '', $accountNumber);
                    $consumer = \App\Models\ConsumerZoneOne::where(function ($q) use ($accountNumber, $normalizedAccount) {
                        $q->where('account_no', $accountNumber)
                          ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($accountNumber))]);
                    })->first();
                } elseif ($accountName) {
                    $consumer = \App\Models\ConsumerZoneOne::whereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $accountName . '%'])->first();
                }
                if ($consumer) {
                    $reading = (object) [
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => 0.0,
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => null,
                        'payment_amount' => null,
                        'amount_tendered' => null,
                        'change_amount' => null,
                        'official_receipt_number' => null,
                        'payment_remarks' => null,
                        'paid_at' => null,
                        'schedule_account_name' => $consumer->account_name,
                        'address' => $consumer->address1 ?? '',
                        'category' => $consumer->category_code ?? '',
                        'meter_number' => $consumer->meter_number ?? '',
                        'bill_month' => $billMonthDate ? $billMonthDate->format('Y-m-d') : null,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => 0.0,
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ];
                }
            }
            }
        }

        if (!$reading) {
            return response()->json([
                'success' => false,
                'message' => 'Billing record could not be loaded for this account. Please try again or select a bill month.',
            ], 200);
        }
        }

        // Get reader information if available
        $reader = null;
        if ($reading->reader_id) {
            $reader = User::find($reading->reader_id);
        }
        
        // Always source address from consumer_zone.address when possible (single source of truth).
        // meter_reading_schedules.address may be stale or missing.
        $consumerForAddress = null;
        $accForAddress = strtoupper(trim((string) ($reading->account_number ?? $accountNumber ?? '')));
        if ($accForAddress !== '') {
            $normForAddress = str_replace('-', '', $accForAddress);
            $consumerForAddress = \App\Models\ConsumerZoneOne::where(function ($q) use ($accForAddress, $normForAddress) {
                $q->where('account_no', $accForAddress)
                    ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normForAddress])
                    ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [$accForAddress]);
            })->first();
        }

        // Prepare account data from downloaded_readings
        $accountData = [
            'number' => $reading->account_number ?? $accountNumber,
            'name' => $reading->account_name ?? $reading->schedule_account_name ?? '',
            'zone' => $reading->zone ?? '',
            'category' => $reading->category ?? '',
            'consumer_category' => $consumerForAddress?->category_code,
            'address' => $consumerForAddress?->address ?? ($reading->address ?? ''),
            'bill_disc_percent' => $consumerForAddress?->bill_disc_percent,
            'osca_id_no' => $consumerForAddress?->osca_id_no,
            'bill_disc_updated_at' => !empty($consumerForAddress?->bill_disc_updated_at)
                ? Carbon::parse($consumerForAddress->bill_disc_updated_at)->format('Y-m-d')
                : null,
            'meter_number' => $reading->meter_number ?? null,
            'reader_id' => $reading->reader_id,
            'reader_name' => $reader ? $this->formatName($reader) : null,
        ];

        // Prepare billing data from downloaded_readings and schedule
        $billMonthFromSchedule = $reading->bill_month ? Carbon::parse($reading->bill_month) : null;
        $billMonthDate = $billMonthDate ?? ($billMonthFromSchedule ? $billMonthFromSchedule->copy()->startOfMonth() : null);

        // Calculate current bill if not available
        // Priority: downloaded_readings.current_bill > meter_reading_schedules.current_bill > calculated from consumption
        $consumption = $reading->consumption ?? 0;
        $downloadedCurrentBill = isset($reading->downloaded_current_bill) && $reading->downloaded_current_bill !== null 
            ? (float) $reading->downloaded_current_bill 
            : null;
        $scheduleCurrentBill = isset($reading->schedule_current_bill) && $reading->schedule_current_bill !== null 
            ? (float) $reading->schedule_current_bill 
            : null;
        
        // Use downloaded_readings.current_bill if available, otherwise use schedule's current_bill
        $storedCurrentBill = $downloadedCurrentBill ?? $scheduleCurrentBill ?? 0.0;
        
        // Get category from reading (from schedule) to determine calculation method
        $category = $reading->category ?? '';
        
        // Meter maintenance charge (separate from current bill)
        $meterMaintenanceCharge = 20.00;
        
        // If current_bill is 0 or null, calculate it from consumption using category
        // Note: Current bill does NOT include meter maintenance charge (₱20) or penalty
        $currentBill = $storedCurrentBill;
        if ($currentBill <= 0 && $consumption > 0) {
            // Calculate bill WITHOUT meter rental (meter rental shown separately)
            $currentBill = $this->calculateWaterBill($consumption, $category);
        }
        
        // Penalty from penalties table: scope by consumer_zone_id, match by schedule_id or downloaded_reading_id
        $penalty = 0.0;
        $consumerByAccount = \App\Models\ConsumerZoneOne::where(function ($q) use ($reading) {
            $acc = $reading->account_number ?? '';
            $norm = str_replace('-', '', $acc);
            $q->where('account_no', $acc)
              ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$norm])
              ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($acc))]);
        })->first();
        // Fallback: resolve consumer from schedule's account_number if reading account didn't match
        if (!$consumerByAccount && !empty($reading->schedule_id)) {
            $czId = DB::table('meter_reading_schedules')->where('id', $reading->schedule_id)->value('consumer_zone_id');
            if ($czId) {
                $consumerByAccount = ConsumerZoneOne::find($czId);
            }
        }
        if ($consumerByAccount && (!empty($reading->schedule_id) || !empty($reading->downloaded_id))) {
            $penaltyQuery = Penalty::where('consumer_zone_id', $consumerByAccount->id)
                ->where(function ($q) use ($reading) {
                    if (!empty($reading->schedule_id)) {
                        $q->where('schedule_id', $reading->schedule_id);
                    }
                    if (!empty($reading->downloaded_id)) {
                        if (!empty($reading->schedule_id)) {
                            $q->orWhere('downloaded_reading_id', $reading->downloaded_id);
                        } else {
                            $q->where('downloaded_reading_id', $reading->downloaded_id);
                        }
                    }
                });
            $penaltyRecords = $penaltyQuery->get();
            foreach ($penaltyRecords as $rec) {
                if ($rec->penalty_amount !== null) {
                    $penalty += (float) $rec->penalty_amount;
                }
            }
            $penalty = round($penalty, 2);
        }
        // Get penalty from consumer_ledgers (PENALTY trans) for this consumer and bill month (so breakdown shows ledger penalty e.g. 62.48)
        $billMonthForPenalty = $billMonthDate ?? ($reading->bill_month ? Carbon::parse($reading->bill_month)->startOfMonth() : null);
        if ($billMonthForPenalty && $consumerByAccount) {
            $monthStart = $billMonthForPenalty->copy()->startOfMonth();
            $monthEnd = $billMonthForPenalty->copy()->endOfMonth();
            $ledgerPenaltyQuery = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerByAccount->id)
                ->where('trans', 'PENALTY')
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
            if (!empty($reading->schedule_id) || !empty($reading->downloaded_id)) {
                $ledgerPenaltyQuery->where(function ($q) use ($reading) {
                    if (!empty($reading->schedule_id)) {
                        $q->where('schedule_id', $reading->schedule_id);
                    }
                    if (!empty($reading->downloaded_id)) {
                        if (!empty($reading->schedule_id)) {
                            $q->orWhere('downloaded_reading_id', $reading->downloaded_id);
                        } else {
                            $q->where('downloaded_reading_id', $reading->downloaded_id);
                        }
                    }
                });
            }
            $ledgerPenaltySum = $ledgerPenaltyQuery->get()->sum(function ($row) {
                $p = (float) ($row->penalty ?? 0);
                $d = (float) ($row->debit ?? 0);
                return $p > 0 ? $p : $d;
            });
            if ($ledgerPenaltySum > 0) {
                $penalty = round($ledgerPenaltySum, 2);
            } else {
                // No match with schedule: get any PENALTY in this month for consumer (by date only)
                $ledgerPenaltySum = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerByAccount->id)
                    ->where('trans', 'PENALTY')
                    ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                    ->get()
                    ->sum(function ($row) {
                        $p = (float) ($row->penalty ?? 0);
                        $d = (float) ($row->debit ?? 0);
                        return $p > 0 ? $p : $d;
                    });
                if ($ledgerPenaltySum > 0) {
                    $penalty = round($ledgerPenaltySum, 2);
                }
            }
        }

        // For breakdown payment: get Current Bill and Water Maintenance from ledger BILLING/BILL entry for this month (so breakdown matches ledger)
        $billMonthForLedger = $billMonthDate ?? ($reading->bill_month ? Carbon::parse($reading->bill_month)->startOfMonth() : null);
        if ($consumerByAccount && $billMonthForLedger) {
            $monthStart = $billMonthForLedger->copy()->startOfMonth();
            $monthEnd = $billMonthForLedger->copy()->endOfMonth();
            $billingEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerByAccount->id)
                ->whereIn('trans', ['BILLING', 'BILL'])
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->when(!empty($reading->schedule_id), function ($q) use ($reading) {
                    $q->where('schedule_id', $reading->schedule_id);
                })
                ->orderBy('date', 'desc')
                ->first();
            if ($billingEntry) {
                $ledgerBillAmount = (float) ($billingEntry->billamount ?? 0);
                $ledgerOthers = (float) ($billingEntry->others ?? 0);
                if ($ledgerBillAmount > 0) {
                    $currentBill = round($ledgerBillAmount, 2);
                }
                if ($ledgerOthers >= 0) {
                    $meterMaintenanceCharge = round($ledgerOthers, 2);
                }
            }
        }

        $billingData = [
            'bill_month' => $billMonthDate?->format('Y-m'),
            'bill_month_input' => $billMonthDate?->format('m-Y'),
            'bill_month_display' => $billMonthDate?->format('F Y'),
            'bill_date' => $reading->bill_date ? Carbon::parse($reading->bill_date)->format('Y-m-d') : null,
            'due_date' => $reading->due_date ? Carbon::parse($reading->due_date)->format('Y-m-d') : null,
            'disconnection_date' => $reading->disconnection_date ? Carbon::parse($reading->disconnection_date)->format('Y-m-d') : null,
            'previous_reading' => $reading->previous_reading ?? 0,
            'previous_reading_date' => $reading->previous_reading_date ? Carbon::parse($reading->previous_reading_date)->format('Y-m-d') : null,
            'current_reading' => $reading->current_reading ?? null,
            'reading_date' => $reading->reading_date ? Carbon::parse($reading->reading_date)->format('Y-m-d') : null,
            'consumption' => $consumption,
            'current_bill' => round($currentBill, 2),
            'meter_maintenance_charge' => $meterMaintenanceCharge,
            'penalty' => $penalty,
            'arrears' => $reading->arrears !== null ? (float) $reading->arrears : 0.0,
            'total_amount' => $reading->total_amount !== null ? (float) $reading->total_amount : 0.0,
            'sedr_number' => $reading->sedr_number ?? null,
        ];

        // Prepare payment data from downloaded_readings
        $paymentAmount = $reading->payment_amount !== null
            ? (float) $reading->payment_amount
            : ($reading->total_amount !== null ? (float) $reading->total_amount : ($billingData['current_bill'] + $billingData['arrears']));

        // Payment status: use paid_at from consumer_payments and consumer_ledgers so user can tell if consumer is already paid
        // Consider paid when either table has paid_at set for this reading/schedule (do not remove or add function once paid)
        $paymentRow = ConsumerPayment::where('reading_id', $reading->downloaded_id ?? null)
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc')
            ->first();

        $ledgerPaymentRow = null;
        if (!$paymentRow || !$paymentRow->paid_at) {
            // 1) Match by downloaded_reading_id (exact reading)
            $ledgerPaymentRow = \App\Models\ConsumerLedger::where('downloaded_reading_id', $reading->downloaded_id ?? 0)
                ->where('trans', 'PAYMENT')
                ->whereNotNull('paid_at')
                ->orderBy('paid_at', 'desc')
                ->first();
            // 2) Fallback: match by consumer_zone_id + schedule_id so ledger PAYMENT for this bill shows as paid even if downloaded_reading_id differs
            if (!$ledgerPaymentRow && $consumerByAccount && !empty($reading->schedule_id)) {
                $ledgerPaymentRow = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerByAccount->id)
                    ->where('schedule_id', $reading->schedule_id)
                    ->where('trans', 'PAYMENT')
                    ->whereNotNull('paid_at')
                    ->where(function ($q) {
                        $q->whereNull('reference')->orWhereRaw("reference NOT LIKE '%-SC'");
                    })
                    ->orderBy('paid_at', 'desc')
                    ->first();
                if (!$ledgerPaymentRow) {
                    $ledgerPaymentRow = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerByAccount->id)
                        ->where('schedule_id', $reading->schedule_id)
                        ->where('trans', 'PAYMENT')
                        ->whereNotNull('paid_at')
                        ->orderBy('paid_at', 'desc')
                        ->first();
                }
            }
            // Do not use "any PAYMENT with date in bill month" — paid only when payment is for THIS reading/schedule and paid_at is set (matches ledger)
        }

        $isPaid = ($paymentRow && $paymentRow->paid_at) || ($ledgerPaymentRow && $ledgerPaymentRow->paid_at);
        $paidAtSource = $paymentRow && $paymentRow->paid_at
            ? $paymentRow->paid_at
            : ($ledgerPaymentRow && $ledgerPaymentRow->paid_at ? $ledgerPaymentRow->paid_at : null);

        if ($isPaid && $paidAtSource) {
            $paymentData = [
                'amount' => $paymentRow ? round((float)($paymentRow->payment_amount ?? 0), 2) : (isset($ledgerPaymentRow) ? round((float)($ledgerPaymentRow->credit ?? 0), 2) : round($paymentAmount, 2)),
                'tendered' => $paymentRow ? (float)($paymentRow->amount_tendered ?? 0) : 0.0,
                'change' => $paymentRow ? (float)($paymentRow->change_amount ?? 0) : 0.0,
                'method' => $paymentRow->payment_method ?? $reading->payment_method ?? null,
                'remarks' => $paymentRow->remarks ?? $reading->payment_remarks ?? $reading->reader_notes ?? null,
                'reference' => $paymentRow->or_number ?? (isset($ledgerPaymentRow) ? $ledgerPaymentRow->reference : null) ?? $reading->payment_reference ?? null,
                'status' => 'paid',
                'paid_at' => Carbon::parse($paidAtSource)->format('Y-m-d H:i:s'),
            ];
            if ($paymentRow) {
                $paymentData['current_bill'] = round((float)($paymentRow->current_bill ?? 0), 2);
                $paymentData['penalty'] = round((float)($paymentRow->penalty ?? 0), 2);
                $paymentData['meter_maintenance'] = round((float)($paymentRow->meter_maintenance ?? 0), 2);
                $paymentData['arrears_cy'] = round((float)($paymentRow->arrears_cy ?? 0), 2);
                $paymentData['arrears_py'] = round((float)($paymentRow->arrears_py ?? 0), 2);
                $paymentData['advances'] = round((float)($paymentRow->advances ?? 0), 2);
                $paymentData['senior_citizen_discount'] = round((float)($paymentRow->senior_citizen_discount ?? 0), 2);
                $paymentData['others'] = round((float)($paymentRow->others ?? 0), 2);
            }
        } else {
            $paymentData = [
                'amount' => round($paymentAmount, 2),
                'tendered' => $reading->amount_tendered !== null ? (float) $reading->amount_tendered : 0.0,
                'change' => $reading->change_amount !== null ? (float) $reading->change_amount : 0.0,
                'method' => $reading->payment_method ?? null,
                'remarks' => $reading->payment_remarks ?? $reading->reader_notes ?? null,
                'reference' => $reading->payment_reference ?? null,
                'status' => 'unpaid',
                'paid_at' => null,
            ];
        }

        // OR lookup rule: if OR exists in consumer_payments, treat it as a paid reference and
        // return that exact OR breakdown so the UI status and amounts match the searched OR.
        if ($orNumberInput !== '' && $orLookupPayment) {
            $paymentData['status'] = 'paid';
            $paymentData['reference'] = $orLookupPayment->or_number ?? $orNumberInput;
            $paymentData['amount'] = round((float) ($orLookupPayment->payment_amount ?? $paymentData['amount'] ?? 0), 2);
            $paymentData['tendered'] = (float) ($orLookupPayment->amount_tendered ?? $paymentData['tendered'] ?? 0);
            $paymentData['change'] = (float) ($orLookupPayment->change_amount ?? $paymentData['change'] ?? 0);
            $paymentData['method'] = $orLookupPayment->payment_method ?? ($paymentData['method'] ?? null);
            $paymentData['remarks'] = $orLookupPayment->remarks ?? ($paymentData['remarks'] ?? null);
            $paymentData['paid_at'] = $orLookupPayment->paid_at
                ? Carbon::parse($orLookupPayment->paid_at)->format('Y-m-d H:i:s')
                : ($paymentData['paid_at'] ?? null);

            $paymentData['current_bill'] = round((float) ($orLookupPayment->current_bill ?? 0), 2);
            $paymentData['penalty'] = round((float) ($orLookupPayment->penalty ?? 0), 2);
            $paymentData['meter_maintenance'] = round((float) ($orLookupPayment->meter_maintenance ?? 0), 2);
            $paymentData['arrears_cy'] = round((float) ($orLookupPayment->arrears_cy ?? 0), 2);
            $paymentData['arrears_py'] = round((float) ($orLookupPayment->arrears_py ?? 0), 2);
            $paymentData['advances'] = round((float) ($orLookupPayment->advances ?? 0), 2);
            $paymentData['senior_citizen_discount'] = round((float) ($orLookupPayment->senior_citizen_discount ?? 0), 2);
            $paymentData['others'] = round((float) ($orLookupPayment->others ?? 0), 2);
        }

        // Prepare downloaded reading details
        $downloadedReadingData = [
            'id' => $reading->downloaded_id,
            'schedule_id' => $reading->schedule_id,
            'reader_id' => $reading->reader_id,
            'reader_notes' => $reading->reader_notes ?? null,
            'completed_at' => (is_object($reading) && property_exists($reading, 'completed_at') && $reading->completed_at)
                ? Carbon::parse($reading->completed_at)->format('Y-m-d H:i:s')
                : null,
            'created_at' => $reading->downloaded_created_at ? Carbon::parse($reading->downloaded_created_at)->format('Y-m-d H:i:s') : null,
            'updated_at' => $reading->downloaded_updated_at ? Carbon::parse($reading->downloaded_updated_at)->format('Y-m-d H:i:s') : null,
        ];

        // Get consumer's current balance - use the same calculation as ledger (consumer_zone_id already resolved above for penalty)
        $currentBalance = 0.00;
        $accountNumberForBalance = $accountData['number'] ?? $accountNumber;
        $consumerForBalance = $consumerByAccount ?? ($accountNumberForBalance ? \App\Models\ConsumerZoneOne::where(function ($q) use ($accountNumberForBalance) {
            $norm = str_replace('-', '', $accountNumberForBalance);
            $q->where('account_no', $accountNumberForBalance)
              ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$norm])
              ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($accountNumberForBalance))]);
        })->first() : null);
        if ($consumerForBalance) {
                // Call the ledger's getLedger method directly to get the exact same balance
                try {
                    $ledgerRequest = new \Illuminate\Http\Request();
                    $ledgerRequest->merge([
                        'account_no' => $accountNumberForBalance,
                        'year' => '' // Empty year to get all records for balance calculation
                    ]);
                    
                    $ledgerController = new \App\Http\Controllers\ConsumerLedgerController();
                    $ledgerResponse = $ledgerController->getLedger($ledgerRequest);
                    $ledgerData = json_decode($ledgerResponse->getContent(), true);
                    
                    if (isset($ledgerData['summary']['balance'])) {
                        $currentBalance = (float)$ledgerData['summary']['balance'];
                    }
                } catch (Exception $e) {
                    // Fallback to direct calculation if ledger call fails
                    Log::error('Error getting balance from ledger: ' . $e->getMessage());
                    $currentBalance = 0.00; // Default to 0 if ledger call fails
            }
        }

        // Add current balance to account data
        $accountData['current_balance'] = round($currentBalance, 2);

        // When record was loaded by OR number, fetch LRO payment entries linked to that OR.
        // This is used by Billing Payment to repopulate SUNDRIES for already-paid records.
        $lroEntriesByOr = [];
        if ($orNumberInput !== '') {
            $orRemarks = 'Payment OR#' . trim($orNumberInput);
            $consumerZoneId = $consumerForBalance?->id;

            $baseLroQuery = LROLedger::with('consumerZone')
                ->where('remarks', $orRemarks)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc');

            $candidateRows = $consumerZoneId
                ? (clone $baseLroQuery)->forConsumerZone($consumerZoneId)->get()
                : (clone $baseLroQuery)->get();

            $lroEntriesByOr = $candidateRows->map(function ($row) {
                return [
                    'id' => $row->id,
                    'type' => $row->type,
                    'date' => $row->date,
                    'account' => $row->account_no,
                    'name' => $row->account_name,
                    'bam_no' => $row->bam_no,
                    'amount' => (float) ($row->amount ?? 0),
                    'ar_type' => $row->ar_type,
                    'acct_code' => $row->acct_code,
                    'reference' => $row->reference,
                    'remarks' => $row->remarks,
                    'status' => $row->status,
                ];
            })->values()->toArray();
        }

        // Fetch SUNDRIES (LRO Ledger) entries for this account.
        // Only DM (charge) rows are shown. For each DM row, sum all matching CM (credit) rows
        // to determine the remaining unpaid balance. Skip fully-paid entries.
        $sundries = [];
        $consumerZoneIdForSundries = $consumerForBalance?->id;
        if ($consumerZoneIdForSundries) {
            try {
                $dmRows = LROLedger::forConsumerZone($consumerZoneIdForSundries)
                    ->where('type', 'DM')
                    ->where('status', 'Approved')
                    ->orderBy('date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get(['id', 'acct_code', 'bam_no', 'amount', 'status']);

                foreach ($dmRows as $row) {
                    $dmAmount   = (float)($row->amount ?? 0);
                    $bamRef     = $row->bam_no;

                    // Sum all CM credits that offset this specific DM entry
                    $paidAmount = (float) LROLedger::forConsumerZone($consumerZoneIdForSundries)
                        ->where('type', 'CM')
                        ->where('acct_code', $row->acct_code)
                        ->where('bam_no', $bamRef)
                        ->sum('amount');

                    $remaining = round($dmAmount - $paidAmount, 2);

                    if ($remaining <= 0) {
                        continue; // Fully paid — do not show
                    }

                    $sundries[] = [
                        'id'        => $row->id,
                        'acct_code' => $row->acct_code,
                        'bam_no'    => $bamRef,
                        'reference' => $bamRef,
                        'amount'    => $remaining,   // remaining unpaid balance
                        'name'      => $consumerForBalance->account_name ?? '',
                    ];

                    if (count($sundries) >= 4) {
                        break; // cap at 4 rows (matches the 4 sundry slots on the form)
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('lookupBillingRecord: LRO ledger sundries fetch failed', ['message' => $e->getMessage()]);
                $sundries = [];
            }
        }

        return response()->json([
            'success' => true,
            'message' => $lookupSuccessMessage ?? 'Downloaded reading record loaded successfully from downloaded_readings table.',
            'data' => [
                'account' => $accountData,
                'billing' => $billingData,
                'payment' => $paymentData,
                'downloaded_reading' => $downloadedReadingData,
                'sundries' => $sundries,
                'lro_entries_by_or' => $lroEntriesByOr,
            ],
        ]);
        } catch (\Throwable $e) {
            Log::error('lookupBillingRecord error', [
                'message' => $e->getMessage(),
                'account' => $accountNumber ?: $accountName,
                'trace' => $e->getTraceAsString(),
            ]);
            $userMessage = 'Unable to fetch billing record.';
            if (config('app.debug')) {
                $userMessage .= ' ' . $e->getMessage();
            } else {
                $userMessage .= ' Please verify the account number and bill month, or try again later.';
            }
            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ], 200);
        }
    }
    
    
    /**
     * Look up LRO Ledger (lro_ledger) entries by BAM No.
     * Used by Billing Payment "Search BAM No." to populate SUNDRIES rows.
     */
    public function lookupBamNo(Request $request)
    {
        $request->validate([
            'bam_no' => ['required', 'string', 'max:50'],
        ]);

        $bamNo = trim((string) $request->input('bam_no', ''));
        if ($bamNo === '') {
            return response()->json([
                'success' => false,
                'message' => 'BAM No. is required.',
            ], 422);
        }

        // Fetch LRO ledger entries by BAM No (or legacy "reference") from lro_ledger.
        // This BAM search is ONLY for "Others" entries.
        $rows = LROLedger::with('consumerZone')
            ->where('bam_no', $bamNo)
            ->where('type', 'Others')
            ->where('status', 'Approved')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->limit(4) // matches the 4 sundry slots on the payment form
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'BAM No. not found in LRO Ledger.',
            ], 404);
        }

        $first = $rows->first();
        // Check if this BAM already has a posted payment CM row and capture its OR number from remarks.
        $paidRow = LROLedger::where('bam_no', $bamNo)
            ->where('type', 'CM')
            ->where('status', 'Posted')
            ->whereNotNull('remarks')
            ->where('remarks', 'like', 'Payment OR#%')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first(['id', 'remarks', 'date']);

        $paidOrNumber = null;
        if ($paidRow && !empty($paidRow->remarks)) {
            $paidOrNumber = trim((string) preg_replace('/^Payment OR#/', '', (string) $paidRow->remarks));
            if ($paidOrNumber === '') {
                $paidOrNumber = null;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'BAM No. loaded from LRO Ledger.',
            'data' => [
                'account' => [
                    'number' => $first->account_no,
                    'name' => $first->account_name,
                    'date' => $first->date,
                ],
                'payment' => [
                    'is_paid' => $paidRow !== null,
                    'or_number' => $paidOrNumber,
                    'paid_at' => $paidRow?->date,
                ],
                'sundries' => $rows->map(function ($row) {
                    $bamRef = $row->bam_no;
                    return [
                        'id'        => $row->id,
                        'type'      => $row->type,
                        'acct_code' => $row->acct_code,
                        'bam_no'    => $bamRef,
                        'reference' => $bamRef,
                        'amount'    => (float) ($row->amount ?? 0),
                        'name'      => $row->account_name,
                        'account'   => $row->account_no,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Calculate water bill based on consumption (cubic meters) and category
     * This matches the calculation used in ReadAndBill.js
     * 
     * @param float $consumption Consumption in cubic meters
     * @param string|null $category Consumer category ('commercial' or 'residential')
     * @return float Calculated bill amount
     */
    private function calculateWaterBill($consumption, $category = null)
    {
        $cu = (int) $consumption;
        
        // Default to residential if category is not specified or invalid
        $isCommercial = $category && strtolower($category) === 'commercial';
        
        if ($isCommercial) {
            return $this->computeCommercial($cu);
        } else {
            return $this->computeResidential($cu);
        }
    }

    /**
     * Calculate commercial water bill with tiered pricing (excluding meter rental)
     * Meter rental (₱20) should be shown separately as Water Maintenance Charge
     */
    private function computeCommercial($cu)
    {
        $minCharge = 243.75;
        // Note: Meter rental (₱20) is NOT included here - it's shown separately
        
        if ($cu <= 10) {
            return $minCharge;
        } elseif ($cu <= 20) {
            return $minCharge + (($cu - 10) * 27.0);
        } elseif ($cu <= 30) {
            return $minCharge + (10 * 27.0) + (($cu - 20) * 29.69);
        } elseif ($cu <= 40) {
            return $minCharge + (10 * 27.0) + (10 * 29.69) + (($cu - 30) * 32.62);
        } else {
            return $minCharge + (10 * 27.0) + (10 * 29.69) + (10 * 32.62) + (($cu - 40) * 35.62);
        }
    }

    /**
     * Calculate residential water bill with tiered pricing (excluding meter rental)
     * Meter rental (₱20) should be shown separately as Water Maintenance Charge
     */
    private function computeResidential($cu)
    {
        $minCharge = 195.0;
        // Note: Meter rental (₱20) is NOT included here - it's shown separately
        
        if ($cu <= 10) {
            return $minCharge;
        } elseif ($cu <= 20) {
            return $minCharge + (($cu - 10) * 21.6);
        } elseif ($cu <= 30) {
            return $minCharge + (10 * 21.6) + (($cu - 20) * 23.75);
        } elseif ($cu <= 40) {
            return $minCharge + (10 * 21.6) + (10 * 23.75) + (($cu - 30) * 26.1);
        } else {
            return $minCharge + (10 * 21.6) + (10 * 23.75) + (10 * 26.1) + (($cu - 40) * 28.5);
        }
    }

    /**
     * Get BILL entries from schedules for balance calculation
     */
    private function getBillEntriesFromSchedulesForBalance($accountNo, $consumerZoneId)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $ledgerEntries = [];

        $schedulesQuery = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->select(
                'mrs.id as schedule_id',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_reading',
                'mrs.previous_reading',
                'mrs.consumption',
                'mrs.current_bill',
                'mrs.arrears',
                'mrs.prepared_by',
                'mrs.created_at',
                'dr.id as downloaded_id',
                'dr.current_bill as downloaded_current_bill'
            )
            ->where('mrs.consumer_zone_id', $consumerZoneId)
            ->whereNotNull('mrs.bill_date');

        $schedules = $schedulesQuery->orderBy('mrs.bill_date', 'desc')
                                   ->orderBy('mrs.created_at', 'desc')
                                   ->get();

        foreach ($schedules as $schedule) {
            $existingLedger = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->where('trans', 'BILL')
                ->first();

            if ($existingLedger) {
                continue;
            }

            $billDate = $schedule->bill_date ? Carbon::parse($schedule->bill_date) : null;
            if (!$billDate) {
                continue;
            }

            $currentBill = $schedule->downloaded_current_bill ?? $schedule->current_bill ?? 0;
            $others = 20.00;

            $ledgerEntries[] = [
                'id' => 'mrs_' . $schedule->schedule_id,
                'trans' => 'BILL',
                'date' => $billDate->format('Y-m-d'),
                'debit' => round((float)$currentBill + (float)$others, 2),
                'credit' => 0,
            ];
        }

        return $ledgerEntries;
    }

    /**
     * Get PAYMENT entries from downloaded_readings for balance calculation
     */
    private function getPaymentEntriesFromDownloadedReadingsForBalance($accountNo, $consumerZoneId)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $ledgerEntries = [];

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

        $payments = $paymentsQuery->orderBy('cp.paid_at', 'desc')
                                  ->orderBy('cp.created_at', 'desc')
                                  ->get();

        foreach ($payments as $payment) {
            $existingLedger = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('downloaded_reading_id', $payment->downloaded_id)
                ->where('trans', 'PAYMENT')
                ->first();

            if ($existingLedger) {
                continue;
            }

            $paidDate = null;
            if ($payment->paid_at) {
                try {
                    $paidDate = Carbon::parse($payment->paid_at);
                } catch (Exception $e) {
                    // Skip if date parsing fails
                }
            }

            if (!$paidDate && $payment->created_at) {
                try {
                    $paidDate = Carbon::parse($payment->created_at);
                } catch (Exception $e) {
                    // Skip if date parsing fails
                }
            }

            if (!$paidDate) {
                continue;
            }

            $paymentAmount = (float)($payment->payment_amount ?? 0);
            if ($paymentAmount > 0) {
                $sortDate = $paidDate;
                if ($payment->related_bill_date) {
                    try {
                        $sortDate = Carbon::parse($payment->related_bill_date);
                    } catch (Exception $e) {
                        $sortDate = $paidDate;
                    }
                }
                
                $ledgerEntries[] = [
                    'id' => 'pay_' . $payment->downloaded_id,
                    'trans' => 'PAYMENT',
                    'date' => $paidDate->format('Y-m-d'),
                    'sort_date' => $sortDate->format('Y-m-d'),
                    'debit' => 0,
                    'credit' => round($paymentAmount, 2),
                ];
            }
        }

        return $ledgerEntries;
    }

    /**
     * Create penalty entries in penalties table when due date is reached
     * Automatic penalty creation is disabled; this method is a no-op.
     */
    private function createPenaltyEntries($accountNo, $consumerZoneId)
    {
        return; // Automatic penalty disabled - do not create new penalties
        $normalizedAccount = str_replace('-', '', $accountNo);
        $today = Carbon::today();

        $schedulesQuery = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->select(
                'mrs.id as schedule_id',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_bill',
                'mrs.prepared_by',
                'dr.id as downloaded_id',
                'dr.current_bill as downloaded_current_bill',
                'dr.status',
                'dr.paid_at'
            )
            ->where('mrs.consumer_zone_id', $consumerZoneId)
            ->whereNotNull('mrs.due_date')
            ->whereNotNull('mrs.bill_date')
            ->where('mrs.due_date', '<=', $today);

        $schedules = $schedulesQuery->orderBy('mrs.due_date', 'desc')
                                   ->get();

        foreach ($schedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date);
            $currentBill = $schedule->downloaded_current_bill ?? $schedule->current_bill ?? 0;

            // Skip if no current bill or invalid due date
            if ($currentBill <= 0 || !$dueDate) {
                continue;
            }

            // Penalty is created one day AFTER the due date (matching past records)
            $penaltyDate = $dueDate->copy()->addDay();
            if ($today->lessThan($penaltyDate)) {
                continue; // Too early, penalty not due yet
            }

            // Check if penalty entry already exists in penalties table
            $existingPenalty = Penalty::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->first();

            if ($existingPenalty) {
                continue;
            }

            // Check if payment was made on or before the due date
            // If payment exists and was made ahead of due date, skip penalty creation
            $paymentMadeBeforeDueDate = false;
            
            // Check ConsumerLedger for PAYMENT entries for this schedule
            $paymentLedgerEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->where('trans', 'PAYMENT')
                ->where(function($query) use ($dueDate) {
                    $query->where('date', '<=', $dueDate->format('Y-m-d'))
                          ->orWhere(function($q) use ($dueDate) {
                              $q->whereNotNull('txtime')
                                ->whereDate('txtime', '<=', $dueDate->format('Y-m-d'));
                          });
                })
                ->first();
            
            if ($paymentLedgerEntry) {
                $paymentMadeBeforeDueDate = true;
            } else {
                // Also check downloaded_readings for payments made before due date
                if ($schedule->downloaded_id) {
                    $downloadedReading = DB::table('downloaded_readings')
                        ->where('id', $schedule->downloaded_id)
                        ->whereNotNull('paid_at')
                        ->whereDate('paid_at', '<=', $dueDate->format('Y-m-d'))
                        ->first();
                    
                    if ($downloadedReading) {
                        $paymentMadeBeforeDueDate = true;
                    }
                }
                
                // Also check consumer_payments table for payments made before due date
                if (!$paymentMadeBeforeDueDate && $schedule->downloaded_id) {
                    $consumerPayment = DB::table('consumer_payments as cp')
                        ->join('downloaded_readings as dr', 'cp.reading_id', '=', 'dr.id')
                        ->where('dr.schedule_id', $schedule->schedule_id)
                        ->where(function($query) use ($dueDate) {
                            $query->whereNotNull('cp.paid_at')
                                  ->whereDate('cp.paid_at', '<=', $dueDate->format('Y-m-d'))
                                  ->where(function($q) {
                                      $q->where('cp.payment_amount', '>', 0)
                                        ->orWhereNotNull('cp.payment_amount');
                                  });
                        })
                        ->first();
                    
                    if ($consumerPayment) {
                        $paymentMadeBeforeDueDate = true;
                    }
                }
            }
            
            // If payment was made before due date, check if there's still an outstanding balance
            // If balance exists, penalty should still be created
            if ($paymentMadeBeforeDueDate) {
                // Get the balance as of the due date to check if payment fully covered the bill
                $balanceAtDueDate = 0;
                
                // Get the latest balance entry on or before the due date
                $balanceEntryAtDueDate = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                    ->where(function($query) use ($dueDate) {
                        $query->where('date', '<=', $dueDate->format('Y-m-d'))
                              ->orWhere('due_date', '<=', $dueDate->format('Y-m-d'));
                    })
                    ->whereNotNull('balance')
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
                
                if ($balanceEntryAtDueDate) {
                    $balanceAtDueDate = (float)($balanceEntryAtDueDate->balance ?? 0);
                } else {
                    // If no ledger entry found, check consumer balance
                    $consumer = \App\Models\ConsumerZoneOne::find($consumerZoneId);
                    if ($consumer) {
                        $balanceAtDueDate = (float)($consumer->balance ?? 0);
                    }
                }
                
                // If there's still a positive balance (outstanding amount), create penalty
                // Only skip penalty if balance is 0 or negative (fully paid or overpaid)
                if ($balanceAtDueDate > 0.01) {
                    // Don't skip - continue to create penalty
                    $paymentMadeBeforeDueDate = false; // Override to allow penalty creation
                } else {
                    continue; // Skip penalty - payment made and balance cleared
                }
            }

            // Get the BILL entry to get the Bill Amount (penalty is 10% of bill amount)
            $billEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->where('trans', 'BILL')
                ->first();

            $billAmount = $billEntry ? (float)($billEntry->billamount ?? 0) : $currentBill;

            if ($billAmount <= 0) {
                continue;
            }

            // Calculate penalty: 10% of Bill Amount
            $penaltyAmount = round($billAmount * 0.10, 2);

            if ($penaltyAmount > 0) {
                // Get the latest balance before this penalty
                $previousBalanceEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                    ->where(function($query) use ($dueDate) {
                        $query->where('date', '<=', $dueDate->format('Y-m-d'))
                              ->orWhere('due_date', '<=', $dueDate->format('Y-m-d'));
                    })
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                $previousBalance = $previousBalanceEntry ? (float)($previousBalanceEntry->balance ?? 0) : 0;

                if ($previousBalance == 0) {
                    $consumer = \App\Models\ConsumerZoneOne::find($consumerZoneId);
                    $previousBalance = $consumer ? (float)($consumer->balance ?? 0) : 0;
                }

                $newBalance = $previousBalance + $penaltyAmount;

                // Generate reference in format: MM-YYYY (matching past records)
                $reference = $dueDate->format('m-Y');

                // Extract first name from prepared_by
                $username = 'System';
                if ($schedule->prepared_by) {
                    $parts = explode(' ', trim($schedule->prepared_by));
                    $username = $parts[0] ?? 'System';
                }

                // Create penalty entry in penalties table
                try {
                    Penalty::create([
                        'consumer_zone_id' => $consumerZoneId,
                        'schedule_id' => $schedule->schedule_id,
                        'downloaded_reading_id' => $schedule->downloaded_id,
                        'date' => $penaltyDate->format('Y-m-d'), // One day after due date
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $reference, // Format: MM-YYYY (e.g., "12-2025")
                        'bill_amount' => $billAmount,
                        'penalty_amount' => $penaltyAmount,
                        'balance' => $newBalance,
                        'username' => $username,
                        'txtime' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    Log::info('Penalty entry created in penalties table', [
                        'account_no' => $accountNo,
                        'schedule_id' => $schedule->schedule_id,
                        'due_date' => $dueDate->format('Y-m-d'),
                        'penalty_amount' => $penaltyAmount,
                        'balance' => $newBalance
                    ]);
                } catch (Exception $e) {
                    Log::error('Error creating penalty entry in penalties table', [
                        'account_no' => $accountNo,
                        'schedule_id' => $schedule->schedule_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Get PENALTY entries for balance calculation from penalties table
     */
    private function getPenaltyEntriesForBalance($accountNo, $consumerZoneId)
    {
        // First, create any missing penalty entries
        $this->createPenaltyEntries($accountNo, $consumerZoneId);

        // Get penalty entries from penalties table
        $penalties = Penalty::where('consumer_zone_id', $consumerZoneId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $penaltyEntries = [];

        foreach ($penalties as $penalty) {
            $penaltyDate = $penalty->date instanceof \DateTimeInterface ? $penalty->date->format('Y-m-d') : (string) $penalty->date;
            $penaltyDueDate = $penalty->due_date instanceof \DateTimeInterface ? $penalty->due_date->format('Y-m-d') : ($penalty->due_date ? (string) $penalty->due_date : '');
            $penaltyTxtime = $penalty->txtime instanceof \DateTimeInterface ? $penalty->txtime->format('Y-m-d H:i:s') : '';
            $penaltyEntries[] = [
                'id' => $penalty->id,
                'trans' => 'PENALTY',
                'date' => $penaltyDate,
                'due_date' => $penaltyDueDate,
                'reference' => $penalty->reference ?? '',
                'reading' => '',
                'volume' => '',
                'billamount' => 0,
                'penalty' => $penalty->penalty_amount,
                'others' => 0,
                'debit' => $penalty->penalty_amount,
                'credit' => 0,
                'balance' => $penalty->balance,
                'username' => $penalty->username ?? '',
                'txtime' => $penaltyTxtime,
                'schedule_id' => $penalty->schedule_id,
                'downloaded_reading_id' => $penalty->downloaded_reading_id,
                'consumer_zone_id' => $penalty->consumer_zone_id,
            ];
        }

        return $penaltyEntries;
    }

    /** OR number sequence starts at this value (100000, 100001, 100002, ...). */
    const OR_NUMBER_START = 334844;

    /**
     * Generate the next Official Receipt (OR) number in sequence.
     * Sequence: 100000, 100001, 100002, ... (auto-increment from 100000).
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateOrNumber()
    {
        try {
            $maxOrNumber = 0;
            
            // Check downloaded_readings table for OR numbers (numeric only)
            $hasOrColumn = Schema::hasColumn('downloaded_readings', 'official_receipt_number');
            if ($hasOrColumn) {
                $maxOrFromReadings = DB::table('downloaded_readings')
                    ->whereNotNull('official_receipt_number')
                    ->where('official_receipt_number', '!=', '')
                    ->whereRaw('official_receipt_number REGEXP "^[0-9]+$"')
                    ->selectRaw('MAX(CAST(official_receipt_number AS UNSIGNED)) as max_or')
                    ->value('max_or');
                
                if ($maxOrFromReadings) {
                    $maxOrNumber = max($maxOrNumber, (int) $maxOrFromReadings);
                }
            }
            
            // Check consumer_payments: include base number from "123456-SC" so sequence is correct
            $hasPaymentsOrColumn = Schema::hasColumn('consumer_payments', 'or_number');
            if ($hasPaymentsOrColumn) {
                $paymentOrs = DB::table('consumer_payments')
                    ->whereNotNull('or_number')
                    ->where('or_number', '!=', '')
                    ->pluck('or_number');
                foreach ($paymentOrs as $orVal) {
                    if (preg_match('/^(\d+)/', $orVal, $m)) {
                        $maxOrNumber = max($maxOrNumber, (int) $m[1]);
                    }
                }
            }

            // Next OR: max(existing) + 1, but never below 100000
            $newOrNumber = max(self::OR_NUMBER_START, $maxOrNumber + 1);
            $orNumber = (string) $newOrNumber;

            // Uniqueness check for exact numeric OR (in case of race)
            $exists = false;
            if ($hasOrColumn) {
                $exists = DB::table('downloaded_readings')
                    ->where('official_receipt_number', $orNumber)
                    ->exists();
            }
            if (!$exists && $hasPaymentsOrColumn) {
                $exists = DB::table('consumer_payments')
                    ->where('or_number', $orNumber)
                    ->exists();
            }
            if ($exists) {
                $newOrNumber++;
                $orNumber = (string) $newOrNumber;
            }
            
            return response()->json([
                'success' => true,
                'or_number' => $orNumber,
            ]);
        } catch (Exception $e) {
            Log::error('Error generating OR number', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $fallbackOr = (string) self::OR_NUMBER_START;
            
            return response()->json([
                'success' => true,
                'or_number' => $fallbackOr,
                'warning' => 'Using fallback OR number (database error). Retry to get next in sequence.',
            ]);
        }
    }

    /**
     * Attempt to resolve a bill month string into a Carbon instance.
     * Handles multiple date formats including MM/DD/YYYY, DD/MM/YYYY, MM-YYYY, etc.
     */
    private function resolveBillMonth(string $billMonthInput): ?Carbon
    {
        // Try common bill month formats first
        $formats = [
            'm-Y',      // 08-2025
            'm/Y',      // 08/2025
            'Y-m',      // 2025-08
            'Y/m',      // 2025/08
            'm/d/Y',    // 08/12/2025 (MM/DD/YYYY)
            'd/m/Y',    // 12/08/2025 (DD/MM/YYYY)
            'Y-m-d',    // 2025-08-12
            'Y/m/d',    // 2025/08/12
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $billMonthInput);
                // If it's a full date, extract just the month/year
                if (in_array($format, ['m/d/Y', 'd/m/Y', 'Y-m-d', 'Y/m/d'])) {
                    return $parsed->startOfMonth();
                }
                return $parsed->startOfMonth();
            } catch (Throwable $e) {
                continue;
            }
        }

        // Last resort: try Carbon's parse
        try {
            $parsed = Carbon::parse($billMonthInput);
            return $parsed->startOfMonth();
        } catch (Throwable $e) {
            return null;
        }
    }
    
    /**
     * Get list of unpaid bill months for a consumer
     */
    public function getUnpaidBillMonths(Request $request)
    {
        $request->validate([
            'account_number' => ['required', 'string'],
        ]);
        
        $accountNumber = strtoupper(trim($request->input('account_number')));
        $normalizedAccount = str_replace('-', '', $accountNumber);
        
        // Find consumer
        $consumer = \App\Models\ConsumerZoneOne::where(function($query) use ($accountNumber, $normalizedAccount) {
            $query->where('account_no', $accountNumber)
                  ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                  ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [$accountNumber]);
        })->first();
        
        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer not found.',
            ], 404);
        }
        
        // Get all ledger entries with debits (charges) from consumer_ledgers
        $ledgerEntries = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->whereIn('trans', ['BILLING', 'BILL'])
            ->where('debit', '>', 0)
            ->orderBy('date', 'desc')
            ->get();
        
        $billMonthsData = [];
        $seenMonths = [];
        
        foreach ($ledgerEntries as $ledger) {
            // Get associated schedule for bill_month
            $schedule = null;
            if ($ledger->schedule_id) {
                $schedule = MeterReadingSchedule::find($ledger->schedule_id);
            }
            
            // Determine bill month
            $sortDate = null;
            if ($schedule && $schedule->bill_month) {
                try {
                    $billMonthDate = Carbon::parse($schedule->bill_month);
                    $billMonthKey = $billMonthDate->format('m-Y');
                    $billMonthDisplay = $billMonthDate->format('M Y');
                    $sortDate = $schedule->bill_month;
                } catch (Exception $e) {
                    continue;
                }
            } else {
                // Use ledger date
                try {
                    $billMonthDate = Carbon::parse($ledger->date);
                    $billMonthKey = $billMonthDate->format('m-Y');
                    $billMonthDisplay = $billMonthDate->format('M Y');
                    $sortDate = $ledger->date;
                } catch (Exception $e) {
                    continue;
                }
            }
            
            // Add to list if not already seen
            if (!isset($seenMonths[$billMonthKey])) {
                $billMonthsData[] = [
                    'key' => $billMonthKey,
                    'display' => $billMonthDisplay,
                    'schedule_id' => $ledger->schedule_id,
                    'sort_date' => $sortDate,
                ];
                $seenMonths[$billMonthKey] = true;
            }
        }
        
        // Sort by date descending (latest first)
        usort($billMonthsData, function($a, $b) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        });
        
        // Remove sort_date from final output
        $billMonths = array_map(function($item) {
            return [
                'key' => $item['key'],
                'display' => $item['display'],
                'schedule_id' => $item['schedule_id'],
            ];
        }, $billMonthsData);
        
        return response()->json([
            'success' => true,
            'data' => $billMonths,
        ]);
    }
    
    /**
     * Get details of a specific bill month for payment
     */
    public function getBillMonthDetails(Request $request)
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
        
        $accountNumber = trim((string) $request->input('account_number'));
        $normalizedAccount = str_replace('-', '', $accountNumber);
        $normalizedAccountNoLeadingZero = ltrim($normalizedAccount, '0');
        $fromDateInput = $request->input('from_date');
        $toDateInput = $request->input('to_date');
        $dateRangeMode = !empty($fromDateInput) && !empty($toDateInput);
        
        $methodIsA = false;
        if ($dateRangeMode) {
            $billMonthFromKey = null;
            $billMonthToKey = null;
        } else {
            $billMonthFromKey = $request->input('bill_month_from');
            if (empty($billMonthFromKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either bill_month_from or both from_date and to_date are required.',
                ], 422);
            }
            $billMonthToKey = $request->input('bill_month_to', $billMonthFromKey);
        }
        
        // Find consumer (prefer exact match; fallback to normalized variants)
        $consumer = \App\Models\ConsumerZoneOne::where('account_no', $accountNumber)->first();
        if (!$consumer) {
            $consumer = \App\Models\ConsumerZoneOne::where(function($query) use ($accountNumber, $normalizedAccount, $normalizedAccountNoLeadingZero) {
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
        $currentBalance = 0;
        if ($request->has('current_balance') && $request->input('current_balance') !== null && $request->input('current_balance') !== '') {
            $currentBalance = (float) $request->input('current_balance');
        } else {
            $latestBalanceEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereNotNull('balance')
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            if ($latestBalanceEntry) {
                $currentBalance = (float)($latestBalanceEntry->balance ?? 0);
            } else {
                $currentBalance = $consumer->getLedgerBalance();
            }
        }
        
        // Get all ledger entries for this consumer
        // Include PENALTY transactions - they might have debit = 0 but penalty > 0, or both might be > 0
        $ledgerEntries = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
            ->where(function($query) {
                // Get all PENALTY transactions regardless of debit/penalty values
                $query->where('trans', 'PENALTY')
                      // OR get other transactions with debit > 0
                      ->orWhere(function($q) {
                          $q->whereIn('trans', ['BILLING', 'BILL', 'LOAN', 'ADJ', 'DM'])
                            ->where('debit', '>', 0);
                      });
            })
            ->orderBy('date', 'asc')
            ->get();
        
        // Debug: Log all PENALTY entries found in consumer_ledgers
        $penaltyEntriesFound = $ledgerEntries->where('trans', 'PENALTY');
        Log::info('All PENALTY entries found in consumer_ledgers', [
            'account_number' => $accountNumber,
            'consumer_id' => $consumer->id,
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
            $penaltiesFromTable = Penalty::where('consumer_zone_id', $consumer->id)
                ->orderBy('date', 'asc')
                ->get();
            
            Log::info('Penalties found in penalties table', [
                'account_number' => $accountNumber,
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
                $exists = $ledgerEntries->contains(function($entry) use ($penaltyDate, $penalty) {
                    return $entry->trans === 'PENALTY' 
                        && $entry->date == $penaltyDate
                        && ($entry->penalty == $penalty->penalty_amount || $entry->debit == $penalty->penalty_amount);
                });
                
                if (!$exists && $penalty->penalty_amount > 0) {
                    // Add penalty from penalties table as a ledger-like entry (include paid_at for display)
                    $ledgerEntries->push((object)[
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
                'consumer_id' => $consumer->id
            ]);
        }
        
        $allEntries = $ledgerEntries;
        
        // Parse the selected range: either bill month (MM-YYYY) or date range (from_date, to_date) from consumer_ledgers
        if ($dateRangeMode) {
            $fromMonthDate = Carbon::parse($fromDateInput)->startOfDay();
            $toMonthDate = Carbon::parse($toDateInput)->endOfDay();
        } else {
        try {
            list($monthFrom, $yearFrom) = explode('-', $billMonthFromKey);
            $fromMonthDate = Carbon::create($yearFrom, $monthFrom, 1)->startOfMonth();
            list($monthTo, $yearTo) = explode('-', $billMonthToKey);
            $toMonthDate = Carbon::create($yearTo, $monthTo, 1)->endOfMonth();
                // Single bill month: use date/due_date logic. Use to_date from transaction_date if provided,
                // otherwise fall back to min(end of month, today).
                if ($billMonthFromKey === $billMonthToKey) {
                    $requestedTxDate = $request->input('transaction_date')
                        ?? $request->input('to_date')
                        ?? $request->input('from_date');
                    if (!empty($requestedTxDate)) {
                        $effectiveTo = Carbon::parse($requestedTxDate)->endOfDay();
                        // Clamp to the selected bill month range
                        if ($effectiveTo->lt($fromMonthDate)) {
                            $effectiveTo = $fromMonthDate->copy()->endOfDay();
                        }
                        if ($effectiveTo->gt($toMonthDate)) {
                            $effectiveTo = $toMonthDate->copy()->endOfDay();
                        }
                    } else {
                        $effectiveTo = $toMonthDate->copy()->min(Carbon::today()->endOfDay());
                    }
                    $fromDateInput = $fromMonthDate->format('Y-m-d');
                    $toDateInput = $effectiveTo->format('Y-m-d');
                    $dateRangeMode = true;
                    $fromMonthDate = Carbon::parse($fromDateInput)->startOfDay();
                    $toMonthDate = Carbon::parse($toDateInput)->endOfDay();
                }
            } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bill month format.',
            ], 422);
            }
        }
        
        // Collect entries that fall within the selected month range (or date range when dateRangeMode)
        $matchingEntries = [];
        foreach ($allEntries as $ledger) {
            $schedule = null;
            if ($ledger->schedule_id) {
                $schedule = MeterReadingSchedule::find($ledger->schedule_id);
            }
            
            // Determine bill month for this entry — always use date and due_date from the database:
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
            if ($dateRangeMode && ($ledger->trans === 'BILLING' || $ledger->trans === 'BILL')) {
                try {
                    $entryDateLedger = $ledger->date instanceof Carbon ? $ledger->date : Carbon::parse($ledger->date);
                    if ($entryDateLedger->lte($toMonthDate)) {
                        $matchingEntries[] = $ledger;
                    }
                } catch (Exception $e) {
                    // skip
                }
                continue;
            }
            // Include entries that fall within the from-to range
            if ($entryDate && $entryDate->gte($fromMonthDate) && $entryDate->lte($toMonthDate)) {
                $matchingEntries[] = $ledger;
                
                // Debug logging for penalty entries
                if ($ledger->trans === 'PENALTY') {
                    Log::info('Penalty entry matched', [
                        'entry_date' => $entryDate->format('Y-m-d'),
                        'from_date' => $fromMonthDate->format('Y-m-d'),
                        'to_date' => $toMonthDate->format('Y-m-d'),
                        'penalty_amount' => $ledger->penalty ?? $ledger->debit ?? 0,
                    ]);
                }
            } elseif ($ledger->trans === 'PENALTY') {
                // Log why penalty didn't match
                Log::info('Penalty entry NOT matched', [
                    'entry_date' => $entryDate ? $entryDate->format('Y-m-d') : 'NULL',
                    'from_date' => $fromMonthDate->format('Y-m-d'),
                    'to_date' => $toMonthDate->format('Y-m-d'),
                    'gte_check' => $entryDate ? $entryDate->gte($fromMonthDate) : false,
                    'lte_check' => $entryDate ? $entryDate->lte($toMonthDate) : false,
                ]);
            }
        }
        
        // Aggregate the amounts from the selected month only (or from date/due_date when dateRangeMode)
        $currentBill = 0;
        $penalty = 0;
        $maintenance = 0;
        $others = 0;
        $arrears = 0;
        $arrearsCy = 0;
        $arrearsPy = 0;
        $seniorCitizenDiscount = 0;

        $isRange = $billMonthFromKey !== null && $billMonthToKey !== null && ($billMonthFromKey !== $billMonthToKey);
        $billingEntriesWithDueDate = []; // [ ['billamount' => x, 'due_date' => Carbon|null ], ... ]
        $noBillingInViewedMonth = false;
        $usePyFormula = true; // false in "Due Date → Current Billing Month" window (overdue view) so PY = 0

        // --- DATE RANGE MODE: Payment breakdown per analyst spec (paid_at only) ---
        // HARD PAYMENT RULE: Unpaid iff paid_at IS NULL on the charge row (consumer_ledgers). Paid iff paid_at IS NOT NULL. Never infer from balance/amount/credit. Never double-count months with paid_at IS NOT NULL.
        // Derived: unpaid_principal_months = BILLING, billamount>0, paid_at IS NULL; unpaid_wmc_months = BILLING, others>0, paid_at IS NULL; unpaid_penalty_months = PENALTY, penalty>0, paid_at IS NULL.
        // Method A = Billing Date → Due Date: Current Bill = 195 if current month BILLING paid_at IS NULL else 0; Penalty = SUM(unpaid penalty); WMC = SUM(unpaid WMC); Arrears CY = 0; Arrears PY = first-month rule or SUM(billamount) before current.
        // Method B = Due Date → Current Billing Month: Current Bill = 0; Penalty/WMC = SUM(unpaid); Arrears CY = SUM(all unpaid principal); Arrears PY = 0.
        // First-month rule (Method A only): when first billing month in Method A and RB ≠ 0 → PY = max(0, RB − Current Billing − Current Month WMC). Constants: principal ₱195, WMC ₱20, penalty ₱19.50.
        if ($dateRangeMode) {
            // HARD PAYMENT RULE: Unpaid iff paid_at IS NULL on the charge row. Never infer from balance/amount/credit. Never double-count months with paid_at IS NOT NULL.
            // RESET RULE: If latest PAYMENT before billing month has balance=0, ignore charges before that (cycle restart).
            $billingMonthStartForReset = $fromMonthDate->copy()->startOfMonth();
            $cycleResetEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->where('trans', 'PAYMENT')
                ->whereNotNull('balance')
                ->whereRaw('ABS(balance) < 0.01')
                ->whereRaw('COALESCE(txtime, date) < ?', [$billingMonthStartForReset->format('Y-m-d H:i:s')])
                ->orderByRaw('COALESCE(txtime, date) DESC')
                ->orderBy('id', 'desc')
                ->first();
            $cycleResetDate = $cycleResetEntry
                ? ($cycleResetEntry->txtime instanceof Carbon
                    ? $cycleResetEntry->txtime
                    : ($cycleResetEntry->date instanceof Carbon ? $cycleResetEntry->date : Carbon::parse($cycleResetEntry->date)))
                : null;
            $billingForDateRange = [];
            foreach ($matchingEntries as $ledger) {
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
            $hasBillingEntriesInRange = !empty($billingForDateRange);
            // Debug: Log all billing entries found
            Log::info('Billing For Date Range Debug', [
                'account_number' => $accountNumber,
                'from_date' => $fromMonthDate->format('Y-m-d'),
                'to_date' => $toMonthDate->format('Y-m-d'),
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

            $toDateOnly = $toMonthDate->copy()->startOfDay();
            $billingMonthStartForReset = $fromMonthDate->copy()->startOfMonth();

            // RESET RULE: ignore charges before latest balance=0 prior to billing month
            $cycleResetRow = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereNotNull('balance')
                ->whereRaw('ABS(balance) < 0.01')
                ->whereRaw('COALESCE(txtime, date) < ?', [$billingMonthStartForReset->format('Y-m-d H:i:s')])
                ->orderByRaw('COALESCE(txtime, date) DESC')
                ->orderBy('id', 'desc')
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
                $matchingEntries = array_values(array_filter($matchingEntries, function ($ledger) use ($cycleResetDate) {
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
            $currentBillEntry = null;
            foreach ($billingForDateRange as $b) {
                $due = $b['due_date'];
                $date = $b['date'];
                if ($due && $toDateOnly->gte($date) && $toDateOnly->lte($due)) {
                    $currentBillEntry = $b;
                    break;
                }
            }
            // Method A: Current Bill = ₱195 if current billing month BILLING row has paid_at IS NULL, else 0
            if ($currentBillEntry) {
                $currentBill = $isChargeUnpaid($currentBillEntry)
                    ? round($currentBillEntry['billamount'] ?? 195.00, 2)
                    : 0.00;
                // HARD PAYMENT RULE: If current bill entry has paid_at IS NOT NULL, mark as paid
                if (!$isChargeUnpaid($currentBillEntry)) {
                    $paymentStatus = 'paid';
                }
            }
            $noBillingInViewedMonth = ($currentBillEntry === null);

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
            $allPenaltyRows = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->where('trans', 'PENALTY')
                ->where(function ($q) {
                    $q->where('penalty', '>', 0)->orWhere('debit', '>', 0);
                })
                ->whereNull('paid_at')
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
                $paidWmcRows = DB::table('consumer_payments as cp')
                    ->join('downloaded_readings as dr', 'dr.id', '=', 'cp.reading_id')
                    ->whereIn('dr.schedule_id', $scheduleIdsForWmc)
                    ->whereNotNull('cp.paid_at')
                    ->selectRaw('dr.schedule_id as schedule_id, COALESCE(SUM(cp.meter_maintenance), 0) as paid_wmc')
                    ->groupBy('dr.schedule_id')
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
            if (!empty($consumer->id)) {
                $consumerPaidWmcPool = (float) DB::table('consumer_payments as cp')
                    ->where('cp.' . ConsumerPayment::consumerZoneIdColumn(), $consumer->id)
                    ->whereNotNull('cp.paid_at')
                    ->whereRaw('COALESCE(cp.paid_at, cp.created_at) <= ?', [$toMonthDate->format('Y-m-d H:i:s')])
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
            $rangeInSingleMonth = ($fromMonthDate->format('n') === $toMonthDate->format('n') && $fromMonthDate->format('Y') === $toMonthDate->format('Y'));
            $toMonthNum = (int) $toMonthDate->format('n');
            $toYearNum = (int) $toMonthDate->format('Y');
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
            $hasCurrent = ($currentBillEntry !== null);
            $methodIsA = $hasCurrent;

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
                $currentBill = $isChargeUnpaid($monthBill) ? round($monthBill['billamount'], 2) : 0.00;
                $arrearsCy = 0;
                $arrearsPy = 0;
                $penalty = $penaltyUnpaidSum;
                $maintenance = $wmcUnpaidSum > 0 ? $wmcUnpaidSum : 20.00; // WMC ₱20.00 per unpaid month
            }

            if (!$payOnlyOneMonthApplied) {
            // Date-meaning methods: A = Billing Date→Due Date, B = Due Date→Current Billing Month
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
                // METHOD A — Billing Date → Billing Due Date
                // Penalty = unpaid penalty rows (e.g., Dec+Jan penalties while Feb is before due)
                $penalty = $penaltyUnpaidSum;
                // WMC = unpaid rental months only (current month + any prior unpaid)
                $maintenance = $wmcUnpaidSum > 0 ? $wmcUnpaidSum : ($isChargeUnpaid($currentBillEntry) ? $wmcPerMonth : 0);
                $arrearsCy = 0; // CY at start of penalty only; Method A = before due
                // Unpaid principal months *before* current billing month (PY bucket at start of billing)
                $billingMonthStart = $currentBillEntry && isset($currentBillEntry['due_date']) && $currentBillEntry['due_date']
                    ? $currentBillEntry['due_date']->copy()->startOfMonth() : $toDateOnly->copy()->startOfMonth();
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
                        'account_number' => $accountNumber,
                        'py_total' => $sumBillamountBeforeCurrent,
                        'entries_count' => count($pyDebugEntries),
                        'entries' => $pyDebugEntries,
                    ]);
                }
                // RESET RULE: Check latest ledger balance BEFORE billing month start (date < billing_month_start_date)
                $resetCutoffDate = $billingMonthStart->copy()->startOfDay();
                $latestBalanceBeforeCurrent = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->whereNotNull('balance')
                    ->whereRaw('COALESCE(txtime, date) < ?', [$resetCutoffDate->format('Y-m-d H:i:s')])
                    ->orderByRaw('COALESCE(txtime, date) DESC')
                    ->orderBy('id', 'desc')
                    ->first();
                $balanceBeforeCurrent = $latestBalanceBeforeCurrent ? (float)($latestBalanceBeforeCurrent->balance ?? 0) : 0.00;
                $isFirstMonthMethodA = (abs($balanceBeforeCurrent) < 0.01);

                // Method A first-month rule:
                // - If there is no remaining balance, PY = 0
                // - If there is remaining balance, PY = remaining balance
                if ($isFirstMonthMethodA) {
                    $currentMonthWmc = ($currentBillEntry && $isChargeUnpaid($currentBillEntry)) ? $wmcPerMonth : 0;
                    $remainingBalance = round($currentBalance - $currentBill - $currentMonthWmc, 2);
                    $arrearsPy = $remainingBalance > 0 ? $remainingBalance : 0;
                    $penalty = 0; // First month in Method A has no penalty
                } else {
                    // Method A: PY = SUM(billamount) of unpaid principal months BEFORE current billing month
                    $arrearsPy = $sumBillamountBeforeCurrent;
                }
                $usePyFormula = false;
            } else {
                // METHOD B — Billing Due Date → Current Billing Month. Current Bill = 0. Arrears CY = all unpaid principal; Arrears PY = 0.
                $currentBill = 0;
                $penalty = $penaltyUnpaidSum;
                $maintenance = $wmcUnpaidSum > 0 ? $wmcUnpaidSum : round($wmcPerMonth * $unpaidPrincipalMonths, 2);
                $arrearsCy = $unpaidPrincipalSum > 0 ? round($unpaidPrincipalSum, 2) : round($principalPerMonth * $unpaidPrincipalMonths, 2);
                $arrearsPy = 0;
                $usePyFormula = false;
            }
            } // end !$payOnlyOneMonthApplied
        }

        if (!$dateRangeMode) {
        // Debug: Log matching entries
        $penaltyEntriesFound = array_filter($matchingEntries, function($e) { 
            return isset($e->trans) && $e->trans === 'PENALTY'; 
        });
            Log::info('Bill Month Details - Matching Entries', [
            'account_number' => $accountNumber,
            'bill_month_from' => $billMonthFromKey,
            'bill_month_to' => $billMonthToKey,
            'from_date' => $fromMonthDate->format('Y-m-d'),
            'to_date' => $toMonthDate->format('Y-m-d'),
            'total_entries' => count($matchingEntries),
            'penalty_count' => count($penaltyEntriesFound),
        ]);

            // Always use date and due_date from the database to drive logic:
        // - BILLING: ledger.date = when bill applies (start); schedule.due_date or ledger.due_date = when payment is due (e.g. 12/16); penalty starts the day after (12/17).
        // - PENALTY: ledger.date = when penalty is effective (e.g. 12/17); ledger.due_date = which bill it relates to.
        $principalFromBilling = 0;
        $dueDateForOverdue = null;
        $billingScheduleId = null;
        $billingDueDateFromLedger = null;
        
        foreach ($matchingEntries as $ledger) {
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
                if ($isRange && $billAmount > 0) {
                    $billingEntriesWithDueDate[] = ['billamount' => $billAmount, 'due_date' => $entryDueDate];
                }
                $currentBill += $billAmount;
                if ($billAmount > 0) {
                    $principalFromBilling = $billAmount;
                    $billingScheduleId = $ledger->schedule_id ?? null;
                    if (!empty($ledger->due_date)) {
                        $billingDueDateFromLedger = $ledger->due_date instanceof Carbon
                            ? $ledger->due_date
                            : Carbon::parse($ledger->due_date);
                    }
                }
                $maintenance += (float)$ledger->others;
            } elseif ($ledger->trans === 'PENALTY') {
                $penaltyAmount = max(
                    (float)($ledger->penalty ?? 0),
                    (float)($ledger->debit ?? 0)
                );
                $penalty += $penaltyAmount;
            } else {
                $others += (float)$ledger->debit;
            }
        }
        
        // Principal for overdue: total current bill from BILLING in this period, or latest unpaid BILLING before period end
        if ($currentBill > 0 && $principalFromBilling <= 0) {
            $principalFromBilling = $currentBill;
        }
        if ($principalFromBilling <= 0) {
            $latestBillingBeforePeriod = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereIn('trans', ['BILLING', 'BILL'])
                ->where('date', '<=', $toMonthDate->format('Y-m-d'))
                ->where('billamount', '>', 0)
                ->orderBy('date', 'desc')
                ->first();
            if ($latestBillingBeforePeriod) {
                $principalFromBilling = (float) $latestBillingBeforePeriod->billamount;
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
            $dueDateForOverdue = $billingDueDateFromLedger;
        } elseif ($billingScheduleId) {
            $billingSchedule = MeterReadingSchedule::find($billingScheduleId);
            if ($billingSchedule && $billingSchedule->due_date) {
                $dueDateForOverdue = $billingSchedule->due_date instanceof Carbon
                    ? $billingSchedule->due_date
                    : Carbon::parse($billingSchedule->due_date);
            }
        }

        $arrears = 0;
        $arrearsCy = 0;
        $arrearsPy = 0;

        // Previous month date range (for "is previous month paid?" and for Arrears — Previous Month principal)
        $prevMonthStart = $fromMonthDate->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $fromMonthDate->copy()->subMonth()->endOfMonth();
        $prevFromStr = $prevMonthStart->format('Y-m-d');
        $prevToStr = $prevMonthEnd->format('Y-m-d');

        // Check if previous month is paid (using paid_at): if paid, Arrears — Previous Month = 0 so it won't appear in breakdown
        $prevMonthPaid = false;
        $prevSchedulesInRange = MeterReadingSchedule::where('consumer_zone_id', $consumer->id)
            ->whereBetween('bill_month', [$prevFromStr, $prevToStr])
            ->pluck('id');
        if ($prevSchedulesInRange->isNotEmpty()) {
            $prevReadingsInRange = DB::table('downloaded_readings')->whereIn('schedule_id', $prevSchedulesInRange->toArray())->pluck('id');
            if ($prevReadingsInRange->isNotEmpty()) {
                $prevMonthPaid = DB::table('consumer_payments')
                    ->whereIn('reading_id', $prevReadingsInRange->toArray())
                    ->whereNotNull('paid_at')
                    ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$prevFromStr, $prevToStr])
                    ->exists();
            }
            if (!$prevMonthPaid) {
                $prevMonthPaid = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->where('trans', 'PAYMENT')
                    ->whereNotNull('paid_at')
                    ->whereIn('schedule_id', $prevSchedulesInRange->toArray())
                    ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$prevFromStr, $prevToStr])
                    ->exists();
            }
        }
        if (!$prevMonthPaid) {
            $prevMonthPaid = DB::table('consumer_ledgers as cl')
                ->join('consumer_zone as cz', 'cz.id', '=', 'cl.consumer_zone_id')
                ->where('cl.trans', 'PAYMENT')
                ->whereNotNull('cl.paid_at')
                ->whereRaw('DATE(cl.paid_at) BETWEEN ? AND ?', [$prevFromStr, $prevToStr])
                ->where(function ($q) use ($accountNumber, $normalizedAccount, $normalizedAccountNoLeadingZero) {
                    $q->where('cz.account_no', $accountNumber)
                        ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                        ->orWhereRaw("TRIM(LEADING '0' FROM REPLACE(cz.account_no, '-', '')) = ?", [$normalizedAccountNoLeadingZero ?: '0']);
                })
                ->exists();
        }

        // Arrears — Previous Month = previous month's unpaid principal only (e.g. December bill 195), not full balance (234.50).
        // If previous month is paid (paid_at set), show 0 so it does not appear in breakdown.
        if ($prevMonthPaid) {
            $arrearsPy = 0;
        } else {
            $prevMonthPrincipal = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereIn('trans', ['BILLING', 'BILL'])
                ->where(function ($q) use ($prevSchedulesInRange, $prevFromStr, $prevToStr) {
                    if ($prevSchedulesInRange->isNotEmpty()) {
                        $q->whereIn('schedule_id', $prevSchedulesInRange->toArray());
                    }
                    $q->orWhereBetween('date', [$prevFromStr, $prevToStr]);
                })
                ->sum(DB::raw('COALESCE(billamount, 0)'));
            $arrearsPy = round(max(0, (float) $prevMonthPrincipal), 2);
        }
        }

        // Payment status: based only on OR # (official receipt number). Paid if this bill month has a payment with or_number set; otherwise Unpaid.
        $paymentStatus = 'unpaid';
        $paymentMonthStart = $fromMonthDate->copy()->startOfMonth();
        $paymentMonthEnd = $fromMonthDate->copy()->endOfMonth();
        $fromStr = $paymentMonthStart->format('Y-m-d');
        $toStr = $paymentMonthEnd->format('Y-m-d');

        $schedulesInRange = MeterReadingSchedule::where('consumer_zone_id', $consumer->id)
            ->whereBetween('bill_month', [$fromStr, $toStr])
            ->pluck('id');

        if ($schedulesInRange->isNotEmpty()) {
            $readingsInRange = DB::table('downloaded_readings')->whereIn('schedule_id', $schedulesInRange)->pluck('id');
            if ($readingsInRange->isNotEmpty()) {
                $hasOrNumber = DB::table('consumer_payments')
                    ->whereIn('reading_id', $readingsInRange)
                    ->whereNotNull('or_number')
                    ->whereRaw("TRIM(COALESCE(or_number, '')) != ''")
                    ->exists();
                if ($hasOrNumber) {
                    $paymentStatus = 'paid';
                }
            }
        }
        // If no schedules/readings in range, also check downloaded_readings.official_receipt_number if column exists
        if ($paymentStatus !== 'paid' && $schedulesInRange->isNotEmpty()) {
            $hasOrColumn = \Illuminate\Support\Facades\Schema::hasColumn('downloaded_readings', 'official_receipt_number');
            if ($hasOrColumn) {
                $readingsInRange = DB::table('downloaded_readings')->whereIn('schedule_id', $schedulesInRange)->pluck('id');
                if ($readingsInRange->isNotEmpty()) {
                    $hasOrInReadings = DB::table('downloaded_readings')
                        ->whereIn('id', $readingsInRange)
                        ->whereNotNull('official_receipt_number')
                        ->whereRaw("TRIM(COALESCE(official_receipt_number, '')) != ''")
                        ->exists();
                    if ($hasOrInReadings) {
                        $paymentStatus = 'paid';
                    }
                }
            }
        }
        // Only when not in date-range mode (bill month mode).
        if (!$dateRangeMode) {
        // Rule: Bill 195 issued Dec 1–16 (date), due_date 12/16 → no penalty before due_date. After due_date (Dec 17–Jan 1): penalty 19.5, maintenance 20.
        // If still unpaid in Jan, Feb view: carry 195, penalty 39, maintenance 40, overdue 390, no new bill. Always use date and due_date from DB.
        if ($isRange && $paymentStatus !== 'paid' && !empty($billingEntriesWithDueDate)) {
            $currentBillFromRule = 0;
            $arrearsPrincipal = 0;
            $earliestDueInRange = null;
            $overdueBillCount = 0;
            $toMonthDateEnd = $toMonthDate->copy()->endOfDay();
            foreach ($billingEntriesWithDueDate as $entry) {
                $due = $entry['due_date'];
                $amt = $entry['billamount'];
                if ($due === null || $due->gt($toMonthDateEnd)) {
                    // due_date after end of range (or missing) → bill not yet due at end of range → current
                    $currentBillFromRule += $amt;
                } else {
                    // due_date <= end of range → overdue by end of range (use date/due_date from DB)
                    $arrearsPrincipal += $amt;
                    $overdueBillCount++;
                    if ($earliestDueInRange === null || $due->lt($earliestDueInRange)) {
                        $earliestDueInRange = $due;
                    }
                }
            }
            if ($arrearsPrincipal > 0 && $earliestDueInRange) {
                // Overdue periods = months from earliest due_date to end of range (date/due_date from DB)
                $overduePeriodsRange = (int) max(1, $earliestDueInRange->diffInMonths($toMonthDate));
                $overduePeriodsRange = min($overduePeriodsRange, 12);
                $currentBill = $currentBillFromRule;
                $arrearsCy = round($arrearsPrincipal, 2);
                // Penalty = 10% per bill per period: 195→19.5 (1 period), 390→39 (2 periods). Use per-bill × periods.
                $perBillPrincipal = $overdueBillCount > 0 ? $arrearsPrincipal / $overdueBillCount : $arrearsPrincipal;
                $penalty = round($perBillPrincipal * 0.10 * $overduePeriodsRange, 2);
                $maintenance = round(20 * $overduePeriodsRange, 2); // 20 first period, 40 second
            } elseif ($arrearsPrincipal > 0) {
                $currentBill = $currentBillFromRule;
                $arrearsCy = round($arrearsPrincipal, 2);
                $penalty = round($arrearsPrincipal * 0.10, 2);
                $maintenance = 20;
            } else {
                $currentBill = $currentBillFromRule;
            }
        }

        // Overdue breakdown for single month (or range with no BILLING in range) — use date and due_date from DB:
        // Period 1 (date before due_date): Current 195, Penalty 0, Maintenance 20, Arrears CY 0
        // Period 2 (after due_date, 1 month): Current 0, Penalty 19.5, Maintenance 20, Arrears CY 195
        // Period 4 (after due_date, 2 months): Current 0, Penalty 39, Maintenance 40, Arrears CY 390
        $usedRangeRule = $isRange && $paymentStatus !== 'paid' && !empty($billingEntriesWithDueDate);
        if (!$usedRangeRule && $paymentStatus !== 'paid' && $dueDateForOverdue && $principalFromBilling > 0 && $dueDateForOverdue->lt($toMonthDate)) {
            // Only treat as "overdue-only" period (no new bill) when there is no BILLING in this range
            if ($currentBill <= 0) {
                // Number of full months overdue: Dec 16 due → Jan 31 = 1 period, → Feb 28 = 2 periods
                $overduePeriods = (int) max(1, $dueDateForOverdue->diffInMonths($toMonthDate));
                $overduePeriods = min($overduePeriods, 12);
                $arrearsCy = round($principalFromBilling * $overduePeriods, 2);
                $penalty = round($principalFromBilling * 0.10 * $overduePeriods, 2);
                $maintenance = round(20 * $overduePeriods, 2);
                $currentBill = 0; // Overdue period: no new bill in breakdown
            } else {
                // Period has a new bill (e.g. Jan 2–16): add carried penalty/maintenance; Arrears — Previous Month not computed (stays 0.00)
                $previousPrincipal = 0;
                $previousDueDate = null;
                $latestBillingBeforePeriod = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->whereIn('trans', ['BILLING', 'BILL'])
                    ->where('date', '<', $fromMonthDate->format('Y-m-d'))
                    ->where('billamount', '>', 0)
                    ->orderBy('date', 'desc')
                    ->first();
                if ($latestBillingBeforePeriod) {
                    $prevBillDate = $latestBillingBeforePeriod->date;
                    $prevAmount = (float) $latestBillingBeforePeriod->billamount;
                    $paidBeforeThisPeriod = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                        ->where('trans', 'PAYMENT')
                        ->whereNotNull('paid_at')
                        ->where('date', '>=', $prevBillDate)
                        ->where('date', '<', $fromMonthDate->format('Y-m-d'))
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
                    if ($previousDueDate && $previousDueDate->lt($fromMonthDate)) {
                        $carriedOverduePeriods = (int) max(1, $previousDueDate->diffInMonths($fromMonthDate->copy()->subDay()) + 1);
                        $carriedOverduePeriods = min($carriedOverduePeriods, 12);
                    }
                    $penalty += round($previousPrincipal * 0.10 * $carriedOverduePeriods, 2);
                    $maintenance += round(20 * $carriedOverduePeriods, 2);
                }
            }
        }
        }
        if ($paymentStatus === 'paid') {
            $arrearsCy = 0;
            $arrearsPy = 0;
        }

        // When previous month is paid (e.g. November all 0.00), December must show Arrears CY 0, Arrears PY 0 — compute from displayed period (toMonthDate)
        $displayedPrevMonthStart = $toMonthDate->copy()->subMonth()->startOfMonth();
        $displayedPrevMonthEnd = $toMonthDate->copy()->subMonth()->endOfMonth();
        $displayedPrevFromStr = $displayedPrevMonthStart->format('Y-m-d');
        $displayedPrevToStr = $displayedPrevMonthEnd->format('Y-m-d');
        $displayedPrevMonthPaid = false;
        $displayedPrevSchedules = MeterReadingSchedule::where('consumer_zone_id', $consumer->id)
            ->whereBetween('bill_month', [$displayedPrevFromStr, $displayedPrevToStr])
            ->pluck('id');
        if ($displayedPrevSchedules->isNotEmpty()) {
            $displayedPrevReadings = DB::table('downloaded_readings')->whereIn('schedule_id', $displayedPrevSchedules->toArray())->pluck('id');
            if ($displayedPrevReadings->isNotEmpty()) {
                $displayedPrevMonthPaid = DB::table('consumer_payments')
                    ->whereIn('reading_id', $displayedPrevReadings->toArray())
                    ->whereNotNull('paid_at')
                    ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$displayedPrevFromStr, $displayedPrevToStr])
                    ->exists();
            }
            if (!$displayedPrevMonthPaid) {
                $displayedPrevMonthPaid = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->where('trans', 'PAYMENT')
                    ->whereNotNull('paid_at')
                    ->whereIn('schedule_id', $displayedPrevSchedules->toArray())
                    ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$displayedPrevFromStr, $displayedPrevToStr])
                    ->exists();
            }
        }
        if (!$displayedPrevMonthPaid) {
            $displayedPrevMonthPaid = DB::table('consumer_ledgers as cl')
                ->join('consumer_zone as cz', 'cz.id', '=', 'cl.consumer_zone_id')
                ->where('cl.trans', 'PAYMENT')
                ->whereNotNull('cl.paid_at')
                ->whereRaw('DATE(cl.paid_at) BETWEEN ? AND ?', [$displayedPrevFromStr, $displayedPrevToStr])
                ->where(function ($q) use ($accountNumber, $normalizedAccount, $normalizedAccountNoLeadingZero) {
                    $q->where('cz.account_no', $accountNumber)
                        ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                        ->orWhereRaw("TRIM(LEADING '0' FROM REPLACE(cz.account_no, '-', '')) = ?", [$normalizedAccountNoLeadingZero ?: '0']);
                })
                ->exists();
        }
        // When previous month is paid: zero Arrears PY; zero Arrears CY only in Method A (before due)
        if ($displayedPrevMonthPaid) {
            $arrearsPy = 0;
            if ($methodIsA) {
                $arrearsCy = 0;
            }
        }

        // Single bill month: only zero Arrears CY in Method A (before due). Keep CY in Method B.
        if ($billMonthFromKey !== null && $billMonthFromKey === $billMonthToKey && $methodIsA) {
            $arrearsCy = 0;
        }

        // 1-month breakdown when billing exists: PY = carried balance (current_balance - current_bill), e.g. 120 after Jan/Dec paid
        if (
            !$dateRangeMode
            && $schedulesInRange->isNotEmpty()
            && $currentBill > 0
            && round($arrearsCy, 2) == 0
            && !($methodIsA ?? false && $isFirstMonthMethodA ?? false)
        ) {
            $arrearsPy = max(0, round($currentBalance - $currentBill, 2));
        }

        // No billing in selected month: show only PY = current balance; Current Bill = 0, Penalty = 0, Meter Rental = 0
        // Use schedules OR actual ledger BILLING entries to decide. Do not zero out if ledger has billing rows for the month.
        $hasLedgerBillingForMonth = isset($hasBillingEntriesInRange) ? $hasBillingEntriesInRange : true;
        $isNoBillingInMonth = ($schedulesInRange->isEmpty() && !$hasLedgerBillingForMonth) || ($dateRangeMode && $noBillingInViewedMonth && !$hasLedgerBillingForMonth);
        if ($isNoBillingInMonth) {
            $currentBill = 0;
            $penalty = 0;
            $maintenance = 0;
            $arrearsCy = 0;
            $arrearsPy = max(0, round($currentBalance, 2));
        }

        // Balance as of end of previous year: split arrears by year so balance from 2025 → PY, from current year → CY.
        $balanceEndOfPreviousYear = $this->getLedgerBalanceAsOfDate((int) $consumer->id, Carbon::now()->subYear()->endOfYear()->format('Y-m-d'));

        // Always use database-backed breakdown (paid_at only).
        // PRE-DUE vs POST-DUE: compare transaction_date (from date button) to the selected bill's due_date.
        // PRE-DUE (transaction_date <= due_date): Current Bill = 195, Arrears CY = current year (excluding selected month), Arrears PY = past years.
        // POST-DUE (transaction_date > due_date): Current Bill = 0, Arrears CY = current year, Arrears PY = past years.
        // When selecting a bill month, if date is after due_date → POST-DUE; if date is within billing period → PRE-DUE.
        $billingController = app(\App\Http\Controllers\BillingProcessController::class);
        $asOfDate = $request->input('transaction_date') ? Carbon::parse($request->input('transaction_date')) : Carbon::now();
        $selectedBillMonthYmd = null;
        if (!empty($billMonthFromKey) && $billMonthFromKey === $billMonthToKey) {
            $parts = explode('-', $billMonthFromKey);
            if (count($parts) === 2) {
                $selectedBillMonthYmd = $parts[1] . '-' . $parts[0];
            }
        }
        // Resolve due_date for the selected bill month to determine PRE-DUE vs POST-DUE based on date button
        $selectedBillDueDate = null;
        if (!empty($schedulesInRange) && $schedulesInRange->isNotEmpty()) {
            $scheduleWithDue = MeterReadingSchedule::whereIn('id', $schedulesInRange)
                ->whereNotNull('due_date')
                ->first();
            if ($scheduleWithDue && $scheduleWithDue->due_date) {
                $selectedBillDueDate = $scheduleWithDue->due_date instanceof Carbon
                    ? $scheduleWithDue->due_date
                    : Carbon::parse($scheduleWithDue->due_date);
            }
        }
        if (!$selectedBillDueDate) {
            // Fallback: get due_date from ledger BILLING for the selected month
            $billingWithDue = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereIn('trans', ['BILLING', 'BILL'])
                ->whereNotNull('due_date')
                ->when(!empty($schedulesInRange) && $schedulesInRange->isNotEmpty(), function ($q) use ($schedulesInRange) {
                    $q->whereIn('schedule_id', $schedulesInRange->toArray());
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
        $dbBreakdown = $billingController->getBillingBreakdownData((int) $consumer->id, $viewType, $asOfDate, null, $selectedBillMonthYmd);
        $currentBill = (float) ($dbBreakdown['current_bill'] ?? 0);
        $penalty = (float) ($dbBreakdown['penalty'] ?? 0);
        $maintenance = (float) ($dbBreakdown['water_maintenance_charge'] ?? 0);
        $arrearsCy = (float) ($dbBreakdown['arrears_cy'] ?? 0);
        $arrearsPy = (float) ($dbBreakdown['arrears_py'] ?? 0);
        $hasDbCurrentBillForSelectedMonth = !empty($selectedBillMonthYmd) && round($currentBill, 2) > 0;

        // Carry credit from latest balance before selected range start
        // (e.g. previous month PAYMENT leaves -0.10, next month bill should reduce by 0.10).
        $carryCreditBeforeRange = 0.0;
        try {
            $rangeStart = $fromMonthDate->copy()->startOfDay();
            $latestBeforeRange = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereNotNull('balance')
                ->whereRaw('COALESCE(txtime, date) < ?', [$rangeStart->format('Y-m-d H:i:s')])
                ->orderByRaw('COALESCE(txtime, date) DESC')
                ->orderBy('id', 'desc')
                ->first();
            // Treat prior negative balance as usable carry-credit only when current displayed balance
            // is also non-positive; if current balance is positive, do not consume current bill.
            if (
                $latestBeforeRange
                && (float)($latestBeforeRange->balance ?? 0) < 0
                && (float)$currentBalance <= 0
            ) {
                $carryCreditBeforeRange = abs(round((float)($latestBeforeRange->balance ?? 0), 2));
            }
        } catch (\Throwable $e) {
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
                $appliedToCurrentBill = min($credit, max(0.0, $currentBill));
                $currentBill = round(max(0.0, $currentBill - $appliedToCurrentBill), 2);
                $credit = round($credit - $appliedToCurrentBill, 2);

                if ($credit > 0) {
                    $appliedToMaintenance = min($credit, max(0.0, $maintenance));
                    $maintenance = round(max(0.0, $maintenance - $appliedToMaintenance), 2);
                }
            }

            // If selected-month charges already consume the displayed current balance,
            // there is no remaining past balance to place into arrears.
            $remainingAfterSelected = round($currentBalance - $currentBill - $penalty - $maintenance, 2);
            if ($remainingAfterSelected <= 0) {
                $arrearsCy = 0.0;
                $arrearsPy = 0.0;
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
                max(0.0, $currentBill)
                + max(0.0, $arrearsCy)
                + max(0.0, $arrearsPy)
                + max(0.0, $penalty)
                + max(0.0, $maintenance),
                2
            );
            $derivedCredit = round(max(0.0, $computedBreakdownTotal - max(0.0, (float) $currentBalance)), 2);
            $credit = max($credit, $derivedCredit);

            $principalBucket = round(max(0.0, $currentBill) + max(0.0, $arrearsCy), 2);
            if ($credit > 0 && $principalBucket > 0) {
                $principalAfterCredit = round(max(0.0, $principalBucket - $credit), 2);
                $currentBill = $principalAfterCredit;
                $arrearsCy = 0.0;
            }
        }

        // PY fallback: if the DB found no prior-year billing rows (arrears_py = 0) but the ledger
        // shows a prior-year balance, fill arrears_py from whatever balance remains after accounting
        // for currentBill + penalty + WMC + CY. This keeps Jan view (CY > 0, PY = 0 from DB) and
        // Feb view (PY directly from DB) using the same underlying balance so totals stay consistent.
        // Also handles accounts with no billing schedule: splits balance into PY + CY using the
        // end-of-previous-year ledger balance so unpaid prior-year amounts show correctly.
        if (round($arrearsPy, 2) == 0 && $balanceEndOfPreviousYear > 0) {
            $carriedForPy = max(0, round($currentBalance - $currentBill - $penalty - $maintenance - $arrearsCy, 2));
            $arrearsPy = min(round($balanceEndOfPreviousYear, 2), $carriedForPy);
            // If CY was also zero from DB (e.g. no billing rows at all), fill CY from whatever balance
            // remains after PY is accounted for so the breakdown always sums to currentBalance.
            if (round($arrearsCy, 2) == 0) {
                $arrearsCy = max(0, round($currentBalance - $currentBill - $penalty - $maintenance - $arrearsPy, 2));
            }
        }

        // No billing in selected month OR no schedule: reconcile breakdown to current balance only when the
        // DB breakdown has no PY/CY data. If the DB already returned non-zero PY or CY, trust those values
        // so the same formula (year-based PY, year+month for CY) is used regardless of which month is selected.
        $dbHasArrears = (round($arrearsCy, 2) + round($arrearsPy, 2)) > 0;
        $applyBalanceSplit = ($isNoBillingInMonth || $schedulesInRange->isEmpty()) && $currentBalance > 0 && !$dbHasArrears;
        // If DB breakdown already found a current bill for the selected month,
        // never reclassify it into arrears via fallback balance split.
        if ($hasDbCurrentBillForSelectedMonth) {
            $applyBalanceSplit = false;
        }
        if ($applyBalanceSplit) {
            $currentBill = 0;
            $remainder = round($currentBalance - $currentBill - $penalty - $maintenance, 2);
            $remainder = max(0, $remainder);
            $arrearsPy = min(max(0, round($balanceEndOfPreviousYear, 2)), $remainder);
            $arrearsCy = max(0, round($remainder - $arrearsPy, 2));
        }
        // 1-month carried balance: only overwrite PY/CY when dbBreakdown returned both zero
        // (so we reconcile from displayed current balance).
        // NOTE: single bill-month requests are normalized into dateRangeMode earlier,
        // so allow this fallback when a single bill month key is selected.
        $isSingleBillMonthSelection = ($billMonthFromKey !== null && $billMonthFromKey === $billMonthToKey);
        if (
            !$applyBalanceSplit
            && (!$dateRangeMode || $isSingleBillMonthSelection)
            && $schedulesInRange->isNotEmpty()
            && $currentBill > 0
            && round($arrearsCy, 2) == 0
            && round($arrearsPy, 2) == 0
            && $currentBalance > $currentBill
            && !$hasDbCurrentBillForSelectedMonth
        ) {
            // Carried balance should only be the remainder after selected-month charges
            // (current bill + penalty + maintenance), not just current bill alone.
            $carried = round($currentBalance - $currentBill - $penalty - $maintenance, 2);
            $carried = max(0, $carried);
            $arrearsPy = min(max(0, round($balanceEndOfPreviousYear, 2)), $carried);
            $arrearsCy = max(0, round($carried - $arrearsPy, 2));
        }

        $orNumberInput = trim((string) $request->input('or_number', ''));
        $orPayment = null;
        if ($orNumberInput !== '') {
            $orPayment = ConsumerPayment::forConsumerZone($consumer->id)
                ->where('or_number', $orNumberInput)
                ->first();
            // Fallback for legacy/misaligned records where consumer_zone_id is null/wrong but OR is valid.
            if (!$orPayment) {
                $orPayment = ConsumerPayment::where('or_number', $orNumberInput)
                    ->orderBy('paid_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
            }
        }

        // Paid-month fallback from consumer_payments:
        // apply only when remaining balance is effectively zero.
        // If balance remains, keep ledger/date-based computed breakdown (no paid_at takeover).
        // Skip this fallback when OR was explicitly provided; explicit OR should control the breakdown.
        if (
            $orNumberInput === ''
            && $paymentStatus === 'paid'
            && $schedulesInRange->isNotEmpty()
            && round((float) $currentBalance, 2) <= 0.009
        ) {
            $readingsInRange = DB::table('downloaded_readings')->whereIn('schedule_id', $schedulesInRange)->pluck('id');
            if ($readingsInRange->isNotEmpty()) {
                $paidPayment = ConsumerPayment::whereIn('reading_id', $readingsInRange)
                    ->whereNotNull('paid_at')
                    ->orderBy('paid_at', 'desc')
                    ->first();
                if ($paidPayment) {
                    $currentBill = (float)($paidPayment->current_bill ?? 0);
                    $penalty = (float)($paidPayment->penalty ?? 0);
                    $maintenance = (float)($paidPayment->meter_maintenance ?? 0);
                    $arrearsCy = (float)($paidPayment->arrears_cy ?? 0);
                    $arrearsPy = (float)($paidPayment->arrears_py ?? 0);
                    $seniorCitizenDiscount = (float)($paidPayment->senior_citizen_discount ?? 0);
                }
            }
        }

        // Preserve computed ledger WMC so OR mode can fallback when OR row has no WMC.
        $ledgerComputedMaintenance = (float) $maintenance;

        // When OR # is provided, use only that OR's payment breakdown for this consumer.
        // If OR is not found, keep the computed month breakdown and only mark unpaid.
        // This keeps new/unused OR flow aligned with bill-month/date computation.
        if ($orNumberInput !== '') {
            if ($orPayment) {
                $paymentStatus = 'paid';
                $currentBill = (float)($orPayment->current_bill ?? 0);
                $penalty = (float)($orPayment->penalty ?? 0);
                $orMaintenance = (float)($orPayment->meter_maintenance ?? 0);
                // OR fallback rule: if paid OR has zero WMC but ledger breakdown has one, keep ledger WMC.
                $maintenance = ($orMaintenance <= 0.009 && $ledgerComputedMaintenance > 0.009)
                    ? round($ledgerComputedMaintenance, 2)
                    : $orMaintenance;
                $arrearsCy = (float)($orPayment->arrears_cy ?? 0);
                $arrearsPy = (float)($orPayment->arrears_py ?? 0);
                $seniorCitizenDiscount = (float)($orPayment->senior_citizen_discount ?? 0);
                $others = (float)($orPayment->others ?? 0);

                // Reclassification rule (same intent as penalty split):
                // when OR has zero WMC but ledger contributes WMC, do not inflate total due.
                // Move the fallback WMC amount out of DM/arrears bucket.
                if ($orMaintenance <= 0.009 && $maintenance > 0.009) {
                    $wmcTransferredFromDm = min(round($maintenance, 2), round(max(0.0, (float) $arrearsCy), 2));
                    if ($wmcTransferredFromDm > 0.009) {
                        $arrearsCy = round(max(0.0, (float) $arrearsCy - $wmcTransferredFromDm), 2);
                    }
                }
            } else {
                // Unused/new OR: keep computed ledger breakdown and only mark unpaid.
                // Do not collapse all amounts into Current Bill; preserve month split (bill/penalty/wmc/arrears).
                $paymentStatus = 'unpaid';
            }
        }

        // For unpaid next-breakdown view (no explicit paid OR), when a payment exists for the
        // same current billing cycle, treat WMC as already covered in that cycle's split.
        // Keep total due unchanged by moving the same amount to Arrears CY.
        $wmcCoveredInCycle = false;
        if (!($orNumberInput !== '' && $orPayment) && $maintenance > 0.009 && $paymentStatus !== 'paid') {
            try {
                $cycleStart = null;
                $cycleEnd = null;
                $cycleScheduleId = null;
                if (isset($currentBillEntry) && is_array($currentBillEntry)) {
                    if (!empty($currentBillEntry['date'])) {
                        $cycleStart = $currentBillEntry['date'] instanceof Carbon
                            ? $currentBillEntry['date']->copy()->startOfDay()
                            : Carbon::parse($currentBillEntry['date'])->startOfDay();
                    }
                    if (!empty($currentBillEntry['due_date'])) {
                        $cycleEnd = $currentBillEntry['due_date'] instanceof Carbon
                            ? $currentBillEntry['due_date']->copy()->endOfDay()
                            : Carbon::parse($currentBillEntry['due_date'])->endOfDay();
                    }
                    if (!empty($currentBillEntry['schedule_id'])) {
                        $cycleScheduleId = (int) $currentBillEntry['schedule_id'];
                    }
                }

                $paymentQuery = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->where(DB::raw("UPPER(TRIM(trans))"), 'PAYMENT')
                    ->whereRaw('COALESCE(credit, 0) > 0');
                if ($cycleScheduleId) {
                    $paymentQuery->where('schedule_id', $cycleScheduleId);
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
                    $wmcShift = round((float) $maintenance, 2);
                    $maintenance = 0.0;
                    $arrearsCy = round((float) $arrearsCy + $wmcShift, 2);
                    $wmcCoveredInCycle = true;
                }
            } catch (\Throwable $e) {
                // Keep original split when cycle-payment check fails.
            }
        }

        // Non-OR flow safeguard:
        // if WMC is missing in computed breakdown but latest unpaid BILLING carries "others",
        // restore WMC from ledger and reclassify it from CY arrears (do not inflate total due).
        if (!($orNumberInput !== '' && $orPayment) && $maintenance <= 0.009 && !$wmcCoveredInCycle) {
            try {
                $latestUnpaidBillingWithOthers = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->whereIn('trans', ['BILLING', 'BILL'])
                    ->where(function ($q) {
                        $q->whereNull('paid_at')->orWhere('paid_at', '');
                    })
                    ->whereRaw('COALESCE(others, 0) > 0')
                    ->orderByRaw('COALESCE(date, txtime) DESC')
                    ->orderBy('id', 'desc')
                    ->first();

                $ledgerWmc = round((float) ($latestUnpaidBillingWithOthers->others ?? 0), 2);
                if ($ledgerWmc > 0.009) {
                    $maintenance = $ledgerWmc;
                    $wmcFromCy = min($ledgerWmc, round(max(0.0, (float) $arrearsCy), 2));
                    if ($wmcFromCy > 0.009) {
                        $arrearsCy = round(max(0.0, (float) $arrearsCy - $wmcFromCy), 2);
                    }
                }
            } catch (\Throwable $e) {
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
        if (!($orNumberInput !== '' && $orPayment) && $paymentStatus !== 'paid') {
            $billDiscPercentRaw = $consumer->bill_disc_percent ?? null;
            $billDiscPercentNorm = is_string($billDiscPercentRaw) ? strtoupper(trim($billDiscPercentRaw)) : null;
            if (is_numeric($billDiscPercentRaw) && abs(((float) $billDiscPercentRaw) - 5.0) < 0.001) {
                $billDiscPercentNorm = 'SC DISCOUNT';
            }
            $oscaId = trim((string) ($consumer->osca_id_no ?? ''));
            $isSeniorConsumer = $billDiscPercentNorm === 'SC DISCOUNT' && $oscaId !== '';

            if ($isSeniorConsumer) {
                try {
                $billingRows = DB::table('consumer_ledgers as cl')
                    ->where('cl.consumer_zone_id', $consumer->id)
                    ->whereIn(DB::raw("UPPER(TRIM(cl.trans))"), ['BILLING', 'BILL'])
                    ->whereNotNull('cl.volume')
                    ->where('cl.volume', '!=', '')
                    ->select('cl.id', 'cl.schedule_id', 'cl.date', 'cl.due_date', 'cl.volume', 'cl.debit', 'cl.billamount', 'cl.others')
                    ->orderBy('cl.date', 'asc')
                    ->orderBy('cl.id', 'asc')
                    ->get()
                    ->values();

                $paymentRows = DB::table('consumer_ledgers as pay')
                    ->where('pay.consumer_zone_id', $consumer->id)
                    ->where(DB::raw("UPPER(TRIM(pay.trans))"), 'PAYMENT')
                    ->whereRaw('COALESCE(pay.credit, 0) > 0')
                    ->select('pay.date', 'pay.id', 'pay.credit')
                    ->orderBy('pay.date', 'asc')
                    ->orderBy('pay.id', 'asc')
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
                $categoryCode = trim((string) ($consumer->category_code ?? ''));
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
                $seniorCitizenDiscount = round(max(0.0, $seniorDiscountTotal), 2);
                } catch (\Throwable $e) {
                    // Keep existing seniorCitizenDiscount when lookup fails.
                }
            }
        }

        // Post-due split rule:
        // If selected/as-of date is after billing due date, move Current Bill to Arrears CY.
        // Example target: Current Bill 0.00, Arrears CY includes former current bill amount.
        if (!($orNumberInput !== '' && $orPayment) && (float) $currentBill > 0.009) {
            $asOfDate = isset($toMonthDate) && $toMonthDate instanceof Carbon
                ? $toMonthDate->copy()->startOfDay()
                : Carbon::today()->startOfDay();

            $dueDate = null;
            if (isset($currentBillEntry) && is_array($currentBillEntry) && !empty($currentBillEntry['due_date'])) {
                $dueDate = $currentBillEntry['due_date'] instanceof Carbon
                    ? $currentBillEntry['due_date']->copy()->startOfDay()
                    : Carbon::parse($currentBillEntry['due_date'])->startOfDay();
            } else {
                // Fallback when currentBillEntry is unavailable in this path:
                // use latest BILLING due date from ledger for this consumer up to as-of date.
                $latestBillingRowForDue = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->whereIn(DB::raw("UPPER(TRIM(trans))"), ['BILLING', 'BILL'])
                    ->where(function ($q) {
                        $q->where('debit', '>', 0)->orWhere('billamount', '>', 0);
                    })
                    ->whereRaw('COALESCE(date, txtime) <= ?', [$asOfDate->format('Y-m-d H:i:s')])
                    ->orderByRaw('COALESCE(due_date, date, txtime) DESC')
                    ->orderBy('id', 'desc')
                    ->first();
                if ($latestBillingRowForDue && !empty($latestBillingRowForDue->due_date)) {
                    $dueDate = $latestBillingRowForDue->due_date instanceof Carbon
                        ? $latestBillingRowForDue->due_date->copy()->startOfDay()
                        : Carbon::parse($latestBillingRowForDue->due_date)->startOfDay();
                }
            }

            if ($dueDate && $asOfDate->gt($dueDate)) {
                $arrearsCy = round((float) $arrearsCy + (float) $currentBill, 2);
                $currentBill = 0.0;
            }
        }

        // Final reconciliation for non-explicit-paid-OR flows vs displayed ledger balance:
        // - If breakdown sum > balance: trim buckets (existing behavior).
        // - If breakdown sum < balance: add shortfall to Arrears — CY (partial payments / coverage gaps vs running balance).
        // Breakdown total excludes senior discount deduction.
        if (!($orNumberInput !== '' && $orPayment)) {
            $currentBalanceCapped = round(max(0, (float) $currentBalance), 2);
            $breakdownTotal = round(
                max(0.0, (float) $currentBill)
                + max(0.0, (float) $arrearsCy)
                + max(0.0, (float) $arrearsPy)
                + max(0.0, (float) $penalty)
                + max(0.0, (float) $maintenance)
                + max(0.0, (float) $others),
                2
            );

            if ($breakdownTotal > $currentBalanceCapped) {
                $excess = round($breakdownTotal - $currentBalanceCapped, 2);
                // Prefer reclassification from CY arrears before trimming WMC:
                // when WMC belongs to current billing, keep it visible and reduce carry/DM bucket first.
                if ($excess > 0.009 && $maintenance > 0.009 && $arrearsCy > 0.009) {
                    $reclassFromCy = min(round($excess, 2), round((float) $arrearsCy, 2));
                    if ($reclassFromCy > 0.009) {
                        $arrearsCy = round(max(0.0, (float) $arrearsCy - $reclassFromCy), 2);
                        $excess = round($excess - $reclassFromCy, 2);
                    }
                }
                foreach (['maintenance', 'penalty', 'others', 'arrearsCy', 'arrearsPy', 'currentBill'] as $field) {
                    if ($excess <= 0) {
                        break;
                    }
                    $value = (float) $$field;
                    if ($value <= 0) {
                        continue;
                    }
                    $deduct = min($value, $excess);
                    $$field = round(max(0.0, $value - $deduct), 2);
                    $excess = round($excess - $deduct, 2);
                }
            } elseif ($currentBalanceCapped > 0.009 && $breakdownTotal + 0.009 < $currentBalanceCapped) {
                $shortfall = round($currentBalanceCapped - $breakdownTotal, 2);
                if ($shortfall > 0.009) {
                    $arrearsCy = round($arrearsCy + $shortfall, 2);
                }
            }
            
            // Normalize split for unpaid/non-OR flow:
            // - Keep current month's principal as Current Bill (from billing period principal)
            // - Allocate the remaining balance to Arrears CY
            // - Preserve penalty/WMC as already computed (including paid/covered logic)
            if ($paymentStatus !== 'paid') {
                // Anchor Current Bill to the selected bill-month principal (if available),
                // so breakdown does not drift to a different period's principal.
                $selectedMonthPrincipal = null;
                if (!empty($schedulesInRange) && $schedulesInRange->isNotEmpty()) {
                    $selectedMonthPrincipal = DB::table('downloaded_readings')
                        ->whereIn('schedule_id', $schedulesInRange->toArray())
                        ->orderBy('id', 'desc')
                        ->value('current_bill');
                }
                $principalForCurrentBill = round(max(0.0, (float) (
                    $selectedMonthPrincipal ?? $principalFromBilling ?? $currentBill
                )), 2);
                $fixedCharges = round(
                    max(0.0, (float) $arrearsPy)
                    + max(0.0, (float) $penalty)
                    + max(0.0, (float) $maintenance)
                    + max(0.0, (float) $others),
                    2
                );
                $maxCurrentBillAllowed = round(max(0.0, $currentBalanceCapped - $fixedCharges), 2);
                $currentBill = round(min($principalForCurrentBill, $maxCurrentBillAllowed), 2);
                $arrearsCy = round(max(0.0, $currentBalanceCapped - ($currentBill + $fixedCharges)), 2);
            }
        }

        // Hard rule: when current balance is zero, breakdown fields must be zero.
        // Exception: explicit paid OR lookup should keep that OR's saved breakdown.
        $hasExplicitOrPaidBreakdown = ($orNumberInput !== '' && $orPayment);
        if (round((float) $currentBalance, 2) <= 0 && !$hasExplicitOrPaidBreakdown) {
            $currentBill = 0.0;
            $penalty = 0.0;
            $maintenance = 0.0;
            $others = 0.0;
            $arrears = 0.0;
            $arrearsCy = 0.0;
            $arrearsPy = 0.0;
            $seniorCitizenDiscount = 0.0;
        }

        // Resolve downloaded_reading id for the selected bill month so the frontend can submit payment for that month (avoids "Payment already exists" when paying December after November).
        $downloadedId = null;
        $selectedConsumption = null;
        if (!empty($schedulesInRange) && $schedulesInRange->isNotEmpty()) {
            $firstReading = \App\Models\DownloadedReading::whereIn('schedule_id', $schedulesInRange)->first();
            if ($firstReading) {
                $downloadedId = $firstReading->id;
                if (isset($firstReading->consumption) && $firstReading->consumption !== null && $firstReading->consumption !== '') {
                    $selectedConsumption = (float) $firstReading->consumption;
                }
            }
        }

        // Arrears — Previous Year is computed inside Method A/B per date-meaning rules.
        
        return response()->json([
            'success' => true,
            'data' => array_merge([
                'bill_month_from' => $billMonthFromKey,
                'bill_month_to' => $billMonthToKey,
                'current_bill' => round($currentBill, 2),
                'penalty' => round($penalty, 2),
                'maintenance' => round($maintenance, 2),
                'others' => round($others, 2),
                'arrears' => round(max(0, $arrears), 2),
                'arrears_cy' => round($arrearsCy, 2),
                'arrears_py' => round($arrearsPy, 2),
                'senior_citizen_discount' => round($seniorCitizenDiscount, 2),
                'current_consumption' => $selectedConsumption,
                'payment_status' => $paymentStatus,
                'downloaded_id' => $downloadedId,
            ], $dateRangeMode ? [
                'from_date' => $fromMonthDate->format('Y-m-d'),
                'to_date' => $toMonthDate->format('Y-m-d'),
            ] : []),
        ]);
    }

    /**
     * Compute running balance as of a given date (inclusive) from consumer_ledgers.
     * Used to split arrears by year: balance at end of previous year → Arrears PY; rest → Arrears CY.
     *
     * @param int $consumerZoneId
     * @param string $asOfDate Y-m-d
     * @return float
     */
    private function getLedgerBalanceAsOfDate(int $consumerZoneId, string $asOfDate): float
    {
        $entries = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
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

    /**
     * Get account suggestions for autocomplete
     * Returns accounts matching the search term (account number or name)
     */
    public function getAccountSuggestions(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'], // Query string, minimum 2 characters
        ]);
        
        $query = strtoupper(trim($request->input('q')));
        $normalizedQuery = str_replace('-', '', $query);
        
        // Get unique accounts from consumer_zone table
        $accounts = \App\Models\ConsumerZoneOne::where(function($q) use ($query, $normalizedQuery) {
                $q->whereRaw("UPPER(TRIM(account_no)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("REPLACE(UPPER(TRIM(account_no)), '-', '') LIKE ?", [$normalizedQuery . '%'])
                  ->orWhereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $query . '%']);
            })
            ->select('account_no', 'account_name')
            ->distinct()
            ->orderBy('account_no', 'asc')
            ->limit(20) // Limit to 20 results
            ->get()
            ->map(function($account) {
                return [
                    'account_number' => $account->account_no,
                    'account_name' => $account->account_name,
                    'display' => $account->account_no . ' - ' . ($account->account_name ?? ''),
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }
}
