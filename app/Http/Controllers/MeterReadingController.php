<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZone;
use App\Models\ConsumerLedger;
use App\Models\Penalty;
use App\Models\LROLedger;
use App\Support\SundryLedgerRemarks;
use Carbon\Carbon;
use App\Imports\PreviousReadingImport;
use App\Services\BillMonthDetailsService;
use App\Services\BillingLookupService;
use App\Services\WaterBillingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;


if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class MeterReadingController extends Controller
{
    /**
     * Users with role reader, excluding configured identifiers (email local-part / name).
     */
    private function meterReadersBaseQuery(): Builder
    {
        $query = User::query()
            ->where(function (Builder $q) {
                $q->where(mr_col('role'), 'reader')
                    ->orWhere(mr_col('role'), 'Reader')
                    ->orWhere(mr_col('role'), 'READER');
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
        return MeterReadingSchedule::filterTableAttributes([
            'assigned_reader_id' => $readerId,
            'status' => 'Assigned',
        ]);
    }

    private function scheduleUnassignmentUpdatePayload(): array
    {
        return MeterReadingSchedule::filterTableAttributes([
            'assigned_reader_id' => null,
            'status' => 'Prepared',
        ]);
    }

    private function findScheduleByAccountNo(string $accountNo): ?MeterReadingSchedule
    {
        $normalized = str_replace('-', '', $accountNo);
        $consumer = ConsumerZone::where(function ($q) use ($accountNo, $normalized) {
            $q->where(mr_col('account_no'), $accountNo)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized]);
        })->first();

        if (!$consumer) {
            return null;
        }

        return MeterReadingSchedule::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->orderByDesc(mr_col('bill_month'))
            ->orderByDesc(mr_col('id'))
            ->first();
    }

    private function downloadedReadingsHasCompletedAt(): bool
    {
        return Schema::hasColumn('downloaded_readings', 'completed_at');
    }

    private function applyDownloadedReadingConsumerJoin($query, string $drAlias = 'dr', string $mrsAlias = 'mrs', string $czAlias = 'cz')
    {
        $baseQuery = $query instanceof Builder
            ? $query->getQuery()
            : $query;

        $joins = $baseQuery->joins ?? [];
        $joined = collect($joins)->map(fn ($j) => (string) ($j->table ?? mr_col('')))->implode(mr_col(' '));

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
            'dr.current_billing as downloaded_current_billing',
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
        $query->orderByDesc(mr_col('dr.reading_date'));
        if ($this->downloadedReadingsHasCompletedAt()) {
            $query->orderByDesc(mr_col('dr.completed_at'));
        }
        return $query->orderByDesc(mr_col('dr.created_at'));
    }

    /**
     * Match consumer by account number (exact, normalized, prefix) and/or account name (partial).
     *
     * @param  QueryBuilder|Builder  $query
     */
    private function whereConsumerAccountMatch($query, ?string $accountNumber, ?string $accountName, string $accountNoColumn = 'cz.account_no', string $accountNameColumn = 'cz.account_name'): void
    {
        if (!$accountNumber && !$accountName) {
            return;
        }

        $query->where(function ($outer) use ($accountNumber, $accountName, $accountNoColumn, $accountNameColumn) {
            $hasAccountClause = false;

            if ($accountNumber) {
                $normalized = str_replace('-', '', $accountNumber);
                $upper = strtoupper(trim($accountNumber));
                $normalizedUpper = str_replace('-', '', $upper);

                $outer->where(function ($sub) use ($accountNumber, $normalized, $upper, $normalizedUpper, $accountNoColumn) {
                    $sub->where(mr_col($accountNoColumn), $accountNumber)
                        ->orWhereRaw("REPLACE({$accountNoColumn}, '-', '') = ?", [$normalized])
                        ->orWhereRaw("UPPER(TRIM({$accountNoColumn})) = ?", [$upper])
                        ->orWhereRaw("UPPER(TRIM({$accountNoColumn})) LIKE ?", [$upper . '%'])
                        ->orWhereRaw("REPLACE(UPPER(TRIM({$accountNoColumn})), '-', '') LIKE ?", [$normalizedUpper . '%']);
                });
                $hasAccountClause = true;
            }

            if ($accountName) {
                $nameClause = function ($sub) use ($accountName, $accountNameColumn) {
                    $sub->whereRaw("UPPER(TRIM({$accountNameColumn})) LIKE ?", ['%' . $accountName . '%']);
                };

                if ($hasAccountClause) {
                    $outer->orWhere($nameClause);
                } else {
                    $outer->where($nameClause);
                }
            }
        });
    }

    /**
     * Display meter reading page with readers and their assignments
     */
    public function index()
    {
        $readers = $this->meterReadersBaseQuery()
            ->orderBy(mr_col('last_name'))
            ->orderBy(mr_col('first_name'))
            ->get();

        // Get reader assignments with zones and status
        $readerAssignments = [];
        
        foreach ($readers as $reader) {
            // Get unique zones assigned to this reader
            $assignments = MeterReadingSchedule::query()->where(mr_col('meter_reading_schedules.assigned_reader_id'), $reader->id)
                ->joinConsumerZone()
                ->select(
                    'cz.zone_code as zone',
                    DB::raw('count(*) as total_schedules'),
                    DB::raw('MAX(meter_reading_schedules.status) as status')
                )
                ->groupBy(mr_col('cz.zone_code'))
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
            return !collect($readerAssignments)->where(mr_col('reader_id'), $reader->id)->count();
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
            'totalAssignments' => count($readerAssignments),
            'zones' => ConsumerZone::distinctZoneCodes(),
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

            $billMonths = MeterReadingSchedule::query()->where(mr_col('assigned_reader_id'), $readerId)
                ->whereNotNull(mr_col('bill_month'))
                ->distinct()
                ->orderBy(mr_col('bill_month'), 'DESC')
                ->pluck(mr_col('bill_month'))
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

        $query->where(mr_col('assigned_reader_id'), $readerId);

        if ($billMonthRaw !== null && $billMonthRaw !== '') {
            try {
                $bm = Carbon::parse($billMonthRaw);
                $query->whereYear('bill_month', $bm->year)
                    ->whereMonth('bill_month', $bm->month);
                $billMonthNormalized = $bm->copy()->startOfMonth()->format('Y-m-d');
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bill_month',
                ], 422);
            }
        } else {
            $latestBillMonth = MeterReadingSchedule::query()->where(mr_col('assigned_reader_id'), $readerId)
                ->whereIn(mr_col('status'), ['Prepared', 'Assigned', 'In Progress', 'Completed'])
                ->orderBy(mr_col('bill_month'), 'DESC')
                ->value(mr_col('bill_month'));

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

        $schedules = $query->orderBy(mr_col('sedr_number'))->get();

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
                    $errors[] = "Row {$rowNum}: Duplicate in file ? [{$accountNo}] already processed in row {$processedInThisFile[$accountNo]}.";
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

                $schedule->update(MeterReadingSchedule::filterTableAttributes([
                    'previous_reading' => $previousReadingInt,
                ]));
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
        } catch (Throwable $e) {
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
     * Update previous_reading on a meter reading schedule from the main-consumer page.
     * Does not modify consumer_ledgers or downloaded_readings (historical records stay as-is).
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
                && ConsumerZone::query()->where(mr_col('id'), $schedule->consumer_zone_id)
                    ->where(function ($q) use ($accountNo, $normalizedAccount) {
                        $q->where(mr_col('account_no'), $accountNo)
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

            $schedule->update(MeterReadingSchedule::filterTableAttributes([
                'previous_reading' => $newPrev,
            ]));

            Log::info('Consumer previous_reading saved on schedule', [
                'schedule_id'  => $scheduleId,
                'account_no'   => $accountNo,
                'old_previous' => $oldPrev,
                'new_previous' => $newPrev,
                'user'         => optional(Auth::user())->name,
            ]);

            return response()->json([
                'success'          => true,
                'message'          => 'Previous reading saved. It will take effect on the next billing.',
                'schedule_id'      => $scheduleId,
                'previous_reading' => $newPrev,
            ]);
        } catch (Throwable $e) {
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
     * Used for NEW consumers whose water meter is NOT brand-new ? i.e. the
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

            $consumer = ConsumerZone::query()->where(mr_col('account_no'), $accountNo)
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
            $hasDownloadedReading = DB::table(mr_col('downloaded_readings'))
                ->where(mr_col('consumer_zone_id'), $consumer->id)
                ->exists();

            $hasScheduleHistory = MeterReadingSchedule::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                ->where(function ($query) {
                    $query->whereNotNull(mr_col('current_reading'))
                        ->orWhereNotNull('reading_date')
                        ->orWhereIn('status', ['Completed', 'Verified', 'In Progress']);
                })
                ->exists();

            $hasBillingLedger = DB::table(mr_col('consumer_ledgers'))
                ->where(mr_col('consumer_zone_id'), $consumer->id)
                ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
                ->whereNotNull(mr_col('reading'))
                ->where(mr_col('reading'), '>', 0)
                ->exists();

            if ($hasDownloadedReading || $hasScheduleHistory || $hasBillingLedger) {
                Log::info('updateConsumerBaseReading blocked: consumer has reading history', [
                    'account_no'             => $accountNo,
                    'has_downloaded_reading' => $hasDownloadedReading,
                    'has_schedule_history'   => $hasScheduleHistory,
                    'has_billing_ledger'     => $hasBillingLedger,
                    'user'                   => optional(Auth::user())->name,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Base reading is locked: this consumer already has Current/Previous reading history.',
                ], 422);
            }

            $oldBase = $consumer->base_reading !== null ? (int) $consumer->base_reading : null;

            $consumer->update(ConsumerZone::filterTableAttributes([
                'base_reading' => $newBase,
                'base_reading_date' => $baseDate
                    ? Carbon::parse($baseDate)->format('Y-m-d')
                    : Carbon::now()->format('Y-m-d'),
            ]));

            Log::info('Consumer base_reading saved from main-consumer page', [
                'account_no' => $accountNo,
                'old_base'   => $oldBase,
                'new_base'   => $newBase,
                'base_date'  => $consumer->base_reading_date,
                'user'       => optional(Auth::user())->name,
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
        } catch (Throwable $e) {
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
                ->where(mr_col('bill_month'), $billMonth)
                ->whereIn(mr_col('status'), ['Prepared'])
                ->whereNull(mr_col('assigned_reader_id'))
                ->get();

            if ($schedules->isEmpty()) {
                $alreadyAssigned = MeterReadingSchedule::forZoneCode($zone)
                    ->where(mr_col('bill_month'), $billMonth)
                    ->whereNotNull(mr_col('assigned_reader_id'))
                    ->count();

                $message = $alreadyAssigned > 0
                    ? 'All schedules for Zone ' . $zone . ' for this bill month are already assigned to a reader.'
                    : 'No prepared schedules found for Zone ' . $zone . ' for this bill month. Please prepare and save schedules in Billing Processes first.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }

            $scheduleIds = $schedules->pluck(mr_col('id'))->all();
            $updated = MeterReadingSchedule::query()->whereIn(mr_col('id'), $scheduleIds)
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
    //     $readers = User::query()->where(mr_col('role'), 'reader')
    //         ->orWhere(mr_col('role'), 'Reader')
    //         ->orWhere(mr_col('role'), 'READER')
    //         ->orderBy(mr_col('last_name'))
    //         ->orderBy(mr_col('first_name'))
    //         ->get()
    //         ->map(function (User $reader) {
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
            ->orderBy(mr_col('last_name'))
            ->orderBy(mr_col('first_name'))
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
            ->whereIn(mr_col('meter_reading_schedules.status'), ['Prepared'])
            ->whereNull(mr_col('meter_reading_schedules.assigned_reader_id'))
            ->groupBy(mr_col('cz.zone_code'));

        if ($billMonth) {
            $query->where(mr_col('meter_reading_schedules.bill_month'), Carbon::parse($billMonth)->format('Y-m-d'));
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
                ->where(mr_col('bill_month'), $billMonth)
                ->where(mr_col('status'), 'Assigned')
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
                ->whereIn(mr_col('status'), ['Assigned', 'In Progress']);

            if ($request->reader_id) {
                $query->where(mr_col('assigned_reader_id'), $request->reader_id);
            }

            if ($request->zone) {
                $query->forZoneCode($request->zone);
            }

            if ($request->bill_month) {
                $query->where(mr_col('bill_month'), Carbon::parse($request->bill_month)->format('Y-m-d'));
            }

            $schedules = $query->orderBy(mr_col('sedr_number'))->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No schedules found for download'
                ], 404);
            }

            // Format data for mobile app
            $firstSchedule = $schedules->first();
            /** @var Carbon|null $billMonth */
            $billMonth = $firstSchedule->bill_month;
            $mobileData = [
                'download_info' => [
                    'downloaded_at' => now()->toDateTimeString(),
                    'total_schedules' => $schedules->count(),
                    'zones' => $schedules->pluck(mr_col('zone'))->unique()->values(),
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
            ->orderBy(mr_col('last_name'))
            ->orderBy(mr_col('first_name'))
            ->get();

        // Get summary of assignments
        $assignmentsSummary = MeterReadingSchedule::select(
                'assigned_reader_id',
                DB::raw('COUNT(*) as total_routes'),
                DB::raw('SUM(CASE WHEN status = "Assigned" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress'),
                DB::raw('SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed')
            )
            ->whereNotNull(mr_col('assigned_reader_id'))
            ->groupBy(mr_col('assigned_reader_id'))
            ->get()
            ->keyBy(mr_col('assigned_reader_id'));

        $meterReaders = $readers->map(function ($reader) {
            $name = strtoupper((string) $reader->last_name) . ', ' . strtoupper((string) $reader->first_name);
            if (! empty($reader->middle_name)) {
                $name .= ' ' . strtoupper(substr((string) $reader->middle_name, 0, 1)) . '.';
            }

            return [
                'id' => $reader->id,
                'name' => $name,
            ];
        })->values();

        return view('processes.download-reading', compact('readers', 'assignmentsSummary', 'meterReaders'));
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
            ->whereNotNull(mr_col('assigned_reader_id'))
            ->groupBy(mr_col('assigned_reader_id'))
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
     * Delete one meter reading schedule from Download Reading (View Routes).
     * Removes that schedule's BILL/BILLING ledger rows and penalties, then the
     * schedule itself (downloaded_readings cascade via FK). Recalculates remaining
     * ledger balances for that consumer only.
     */
    public function deleteSchedule(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:meter_reading_schedules,id',
            'account_no' => 'required|string',
        ]);

        $scheduleId = (int) $validated['schedule_id'];
        $accountNo = trim((string) $validated['account_no']);

        try {
            $schedule = MeterReadingSchedule::with('consumerZone')->find($scheduleId);
            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meter reading schedule not found.',
                ], 404);
            }

            $normalizedAccount = str_replace('-', '', $accountNo);
            $upperAccount = strtoupper($accountNo);

            $consumerMatches = $schedule->consumer_zone_id
                && ConsumerZone::query()->where(mr_col('id'), $schedule->consumer_zone_id)
                    ->where(function ($q) use ($accountNo, $normalizedAccount, $upperAccount) {
                        $q->where(mr_col('account_no'), $accountNo)
                            ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                            ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [$upperAccount]);
                    })
                    ->exists();

            if (!$consumerMatches) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account number does not match the schedule.',
                ], 422);
            }

            $consumerZoneId = $schedule->consumer_zone_id ? (int) $schedule->consumer_zone_id : null;
            $zone = $schedule->zone;
            $billMonth = $schedule->bill_month;
            $accountNumber = $schedule->account_number ?? $accountNo;
            $deletedBillingLedgers = 0;

            DB::transaction(function () use ($schedule, $scheduleId, $consumerZoneId, &$deletedBillingLedgers) {
                // 1. BILL/BILLING ledger rows for this schedule only (not PAYMENT/PENALTY/other).
                $ledgerQuery = ConsumerLedger::query()
                    ->where(mr_col('schedule_id'), $scheduleId)
                    ->whereIn(mr_col('trans'), ['BILLING', 'BILL']);

                if ($consumerZoneId) {
                    $ledgerQuery->where(mr_col('consumer_zone_id'), $consumerZoneId);
                }

                $deletedBillingLedgers = $ledgerQuery->delete();

                // 2. Query-builder delete ? skips Penalty Eloquent deleting hook (RESTRICT on schedule_id).
                Penalty::query()->where('schedule_id', $scheduleId)->delete();

                // 3. Schedule delete cascades downloaded_readings; leftover ledger FKs are nulled.
                $schedule->delete();

                // 4. Recalculate remaining balances for this consumer only.
                if ($consumerZoneId) {
                    $this->recalculateConsumerLedgerBalances($consumerZoneId);
                }
            });

            Log::info('Meter reading schedule deleted', [
                'schedule_id' => $scheduleId,
                'account_number' => $accountNumber,
                'zone' => $zone,
                'bill_month' => $billMonth instanceof \DateTimeInterface
                    ? $billMonth->format('Y-m-d')
                    : $billMonth,
                'deleted_billing_ledgers' => $deletedBillingLedgers,
                'user' => optional(Auth::user())->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Schedule deleted successfully.',
                'schedule_id' => $scheduleId,
            ]);
        } catch (Throwable $e) {
            Log::error('deleteSchedule failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'account_no' => $accountNo,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete schedule.',
            ], 500);
        }
    }

    /**
     * Update one meter reading schedule from Download Reading (View Routes).
     * Syncs due_date to related ledger rows and readings to downloaded_readings when present.
     */
    public function updateSchedule(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:meter_reading_schedules,id',
            'assigned_reader_id' => 'nullable|integer|exists:users,id',
            'bill_month' => 'required|date',
            'bill_date' => 'required|date',
            'due_date' => 'required|date',
            'disconnection_date' => 'required|date',
            'previous_reading_date' => 'nullable|date',
            'previous_reading' => 'required|integer|min:0',
            'current_reading' => 'nullable|integer|min:0',
            'reading_date' => 'nullable|date',
            'consumption' => 'nullable|integer',
            'current_billing' => 'nullable|numeric|min:0',
            'arrears' => 'nullable|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'meter_rental_arrears' => 'nullable|numeric|min:0',
            'prior_years' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:Prepared,Assigned,In Progress,Completed',
            'sedr_number' => 'nullable|integer',
        ]);

        $scheduleId = (int) $validated['schedule_id'];
        $previousReading = (int) $validated['previous_reading'];
        $currentReading = $request->filled('current_reading') ? (int) $validated['current_reading'] : null;
        $consumption = $request->filled('consumption') ? (int) $validated['consumption'] : null;
        if ($currentReading !== null && $consumption === null) {
            $consumption = $currentReading - $previousReading;
        }

        $dueDate = Carbon::parse($validated['due_date'])->format('Y-m-d');

        try {
            $schedule = MeterReadingSchedule::with(['consumerZone', 'downloadedReading'])->find($scheduleId);
            if (! $schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meter reading schedule not found.',
                ], 404);
            }

            $payload = MeterReadingSchedule::filterTableAttributes([
                'assigned_reader_id' => $validated['assigned_reader_id'] ?: null,
                'bill_month' => Carbon::parse($validated['bill_month'])->format('Y-m-d'),
                'bill_date' => Carbon::parse($validated['bill_date'])->format('Y-m-d'),
                'due_date' => $dueDate,
                'disconnection_date' => Carbon::parse($validated['disconnection_date'])->format('Y-m-d'),
                'previous_reading_date' => ! empty($validated['previous_reading_date'])
                    ? Carbon::parse($validated['previous_reading_date'])->format('Y-m-d')
                    : null,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'reading_date' => ! empty($validated['reading_date'])
                    ? Carbon::parse($validated['reading_date'])->format('Y-m-d')
                    : null,
                'consumption' => $consumption ?? 0,
                'current_billing' => round((float) ($validated['current_billing'] ?? 0), 2),
                'arrears' => round((float) ($validated['arrears'] ?? 0), 2),
                'penalty' => round((float) ($validated['penalty'] ?? 0), 2),
                'meter_rental_arrears' => round((float) ($validated['meter_rental_arrears'] ?? 0), 2),
                'prior_years' => round((float) ($validated['prior_years'] ?? 0), 2),
                'total_amount' => round((float) ($validated['total_amount'] ?? 0), 2),
                'status' => $validated['status'],
                'sedr_number' => $validated['sedr_number'] ?? $schedule->sedr_number,
            ]);

            DB::transaction(function () use ($schedule, $payload, $dueDate, $previousReading, $currentReading, $consumption, $validated) {
                $schedule->update($payload);

                ConsumerLedger::query()
                    ->where(mr_col('schedule_id'), $schedule->id)
                    ->update(['due_date' => $dueDate]);

                $downloaded = $schedule->downloadedReading;
                if ($downloaded) {
                    $downloadPayload = [
                        'previous_reading' => $previousReading,
                        'current_reading' => $currentReading,
                        'consumption' => $consumption ?? $downloaded->consumption,
                    ];
                    if (! empty($validated['reading_date'])) {
                        $downloadPayload['reading_date'] = Carbon::parse($validated['reading_date'])->format('Y-m-d');
                    }
                    if (array_key_exists('current_billing', $payload)) {
                        $downloadPayload['current_billing'] = $payload['current_billing'];
                    }
                    $downloaded->fill($downloadPayload);
                    $downloaded->save();
                }
            });

            $schedule->refresh()->load(['consumerZone', 'assignedReader']);

            Log::info('Meter reading schedule updated', [
                'schedule_id' => $scheduleId,
                'account_no' => $schedule->account_number,
                'user' => optional(Auth::user())->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Schedule updated for ' . ($schedule->account_number ?? '') . '.',
                'data' => $schedule,
            ]);
        } catch (Throwable $e) {
            Log::error('updateSchedule failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update bill/due/disconnection dates for many schedules shown on Download Reading.
     */
    public function updateSchedulesBatch(Request $request)
    {
        $validated = $request->validate([
            'schedule_ids' => 'required|array|min:1',
            'schedule_ids.*' => 'integer|exists:meter_reading_schedules,id',
            'bill_month' => 'required|date',
            'bill_date' => 'required|date',
            'due_date' => 'required|date',
            'disconnection_date' => 'required|date',
        ]);

        $scheduleIds = array_values(array_unique(array_map('intval', $validated['schedule_ids'])));
        $billMonth = Carbon::parse($validated['bill_month'])->format('Y-m-d');
        $billDate = Carbon::parse($validated['bill_date'])->format('Y-m-d');
        $dueDate = Carbon::parse($validated['due_date'])->format('Y-m-d');
        $disconnectionDate = Carbon::parse($validated['disconnection_date'])->format('Y-m-d');

        try {
            DB::transaction(function () use ($scheduleIds, $billMonth, $billDate, $dueDate, $disconnectionDate) {
                MeterReadingSchedule::query()->whereIn(mr_col('id'), $scheduleIds)->update(
                    MeterReadingSchedule::filterTableAttributes([
                        'bill_month' => $billMonth,
                        'bill_date' => $billDate,
                        'due_date' => $dueDate,
                        'disconnection_date' => $disconnectionDate,
                    ])
                );

                ConsumerLedger::query()
                    ->whereIn(mr_col('schedule_id'), $scheduleIds)
                    ->update(['due_date' => $dueDate]);
            });

            return response()->json([
                'success' => true,
                'message' => count($scheduleIds) . ' schedule(s) updated.',
                'updated_count' => count($scheduleIds),
            ]);
        } catch (Throwable $e) {
            Log::error('updateSchedulesBatch failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedules: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recompute running balances for one consumer's remaining ledger rows.
     * running = round(running + debit - credit, 2). Does not trust client amounts.
     */
    protected function recalculateConsumerLedgerBalances(int $consumerZoneId): void
    {
        $ledgers = ConsumerLedger::query()
            ->where(mr_col('consumer_zone_id'), $consumerZoneId)
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc')
            ->get();

        $running = 0.0;

        foreach ($ledgers as $ledger) {
            $debit = (float) ($ledger->debit ?? 0);
            $credit = (float) ($ledger->credit ?? 0);
            $running = round($running + $debit - $credit, 2);

            $stored = $ledger->balance !== null ? round((float) $ledger->balance, 2) : null;
            if ($stored !== $running) {
                $ledger->balance = $running;
                $ledger->save();
            }
        }
    }

    /**
     * Display billing payment page focused on downloaded readings
     */
    public function billingPayment()
    {
        $zoneStats = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select(
                'cz.zone_code as zone',
                DB::raw('MAX(dr.reading_date) as latest_reading_date'),
                DB::raw('COUNT(*) as total_downloaded')
            )
            ->whereNotNull(mr_col('cz.zone_code'))
            ->groupBy(mr_col('cz.zone_code'))
            ->orderBy(mr_col('cz.zone_code'))
            ->get();

        $zones = $zoneStats->pluck(mr_col('zone'));

        $latestReadingDates = $zoneStats->mapWithKeys(function ($stat) {
            $latest = $stat->latest_reading_date ? Carbon::parse($stat->latest_reading_date)->format('Y-m-d') : null;
            return [$stat->zone => $latest];
        });

        $defaultZone = $zones->first();
        $defaultReadingDate = $defaultZone && $latestReadingDates->has($defaultZone)
            ? $latestReadingDates->get($defaultZone)
            : Carbon::now()->format('Y-m-d');

        // Get latest payment record from consumer_payments table
        $latestPaymentRecord = DB::table(mr_col('consumer_payments'))
            ->whereNotNull(mr_col('paid_at'))
            ->orderBy(mr_col('paid_at'), 'desc')
            ->first();

        // Calculate pending payments - count downloaded_readings without payments
        $pendingPayments = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('consumer_payments as cp'), mr_col('cp.reading_id'), '=', mr_col('dr.id'))
            ->where(function($query) {
                $query->where(mr_col('dr.status'), '!=', 'paid')
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
        return app(BillingLookupService::class)->lookup($request);
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
            ->where(mr_col('bam_no'), $bamNo)
            ->where(mr_col('type'), 'Others')
            ->where(mr_col('status'), 'Approved')
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc')
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
        $paidRow = LROLedger::query()->where(mr_col('bam_no'), $bamNo)
            ->where(mr_col('type'), 'CM')
            ->where(mr_col('status'), 'Posted')
            ->whereNotNull(mr_col('remarks'))
            ->where(mr_col('remarks'), 'like', 'Payment OR#%')
            ->orderBy(mr_col('date'), 'desc')
            ->orderBy(mr_col('id'), 'desc')
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
                        'id'            => $row->id,
                        'lro_ledger_id' => $row->id,
                        'ledger'        => $row->ledger ?? 'LRO',
                        'type'          => $row->type,
                        'acct_code'     => $row->acct_code,
                        'bam_no'        => $bamRef,
                        'amount'        => (float) ($row->amount ?? 0),
                        'name'          => $row->account_name,
                        'account'       => $row->account_no,
                        'remarks'       => $row->remarks,
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
    private function calculateWaterBill($consumption, $category = null, $rateCode = null)
    {
        return app(WaterBillingService::class)->calculate($consumption, $category, $rateCode);
    }

    /**
     * Get BILL entries from schedules for balance calculation
     */
    private function getBillEntriesFromSchedulesForBalance($accountNo, $consumerZoneId)
    {
        $normalizedAccount = str_replace('-', '', $accountNo);
        $ledgerEntries = [];

        $schedulesQuery = DB::table(mr_col('meter_reading_schedules as mrs'))
            ->leftJoin(mr_col('downloaded_readings as dr'), mr_col('mrs.id'), '=', mr_col('dr.schedule_id'))
            ->select(
                'mrs.id as schedule_id',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_reading',
                'mrs.previous_reading',
                'mrs.consumption',
                'mrs.current_billing',
                'mrs.arrears',
                'mrs.prepared_by',
                'mrs.created_at',
                'dr.id as downloaded_id',
                'dr.current_billing as downloaded_current_billing'
            )
            ->where(mr_col('mrs.consumer_zone_id'), $consumerZoneId)
            ->whereNotNull(mr_col('mrs.bill_date'));

        $schedules = $schedulesQuery->orderBy(mr_col('mrs.bill_date'), 'desc')
                                   ->orderBy(mr_col('mrs.created_at'), 'desc')
                                   ->get();

        foreach ($schedules as $schedule) {
            $existingLedger = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                ->where(mr_col('schedule_id'), $schedule->schedule_id)
                ->where(mr_col('trans'), 'BILL')
                ->first();

            if ($existingLedger) {
                continue;
            }

            $billDate = $schedule->bill_date ? Carbon::parse($schedule->bill_date) : null;
            if (!$billDate) {
                continue;
            }

            $currentBill = $schedule->downloaded_current_billing ?? $schedule->current_billing ?? 0;
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

        $paymentsQuery = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('consumer_payments as cp'), mr_col('cp.reading_id'), '=', mr_col('dr.id'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
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
                    $query->where(mr_col('dr.consumer_zone_id'), $consumerZoneId)
                        ->orWhere(mr_col('mrs.consumer_zone_id'), $consumerZoneId);
                } else {
                    $query->where(mr_col('cz.account_no'), $accountNo)
                        ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                        ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNo))]);
                }
            })
            ->where(function($query) {
                $query->where(mr_col('dr.status'), 'paid')
                      ->orWhereNotNull('cp.paid_at')
                      ->orWhere(function($q) {
                          $q->whereNotNull(mr_col('cp.payment_amount'))
                            ->where(mr_col('cp.payment_amount'), '>', 0);
                      });
            });

        $payments = $paymentsQuery->orderBy(mr_col('cp.paid_at'), 'desc')
                                  ->orderBy(mr_col('cp.created_at'), 'desc')
                                  ->get();

        foreach ($payments as $payment) {
            $existingLedger = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                ->where(mr_col('downloaded_reading_id'), $payment->downloaded_id)
                ->where(mr_col('trans'), 'PAYMENT')
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

        $schedulesQuery = DB::table(mr_col('meter_reading_schedules as mrs'))
            ->leftJoin(mr_col('downloaded_readings as dr'), mr_col('mrs.id'), '=', mr_col('dr.schedule_id'))
            ->select(
                'mrs.id as schedule_id',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_billing',
                'mrs.prepared_by',
                'dr.id as downloaded_id',
                'dr.current_billing as downloaded_current_billing',
                'dr.status',
                'dr.paid_at'
            )
            ->where(mr_col('mrs.consumer_zone_id'), $consumerZoneId)
            ->whereNotNull(mr_col('mrs.due_date'))
            ->whereNotNull(mr_col('mrs.bill_date'))
            ->where(mr_col('mrs.due_date'), '<=', $today);

        $schedules = $schedulesQuery->orderBy(mr_col('mrs.due_date'), 'desc')
                                   ->get();

        foreach ($schedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date);
            $currentBill = $schedule->downloaded_current_billing ?? $schedule->current_billing ?? 0;

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
            $existingPenalty = Penalty::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                ->where(mr_col('schedule_id'), $schedule->schedule_id)
                ->first();

            if ($existingPenalty) {
                continue;
            }

            // Check if payment was made on or before the due date
            // If payment exists and was made ahead of due date, skip penalty creation
            $paymentMadeBeforeDueDate = false;
            
            // Check ConsumerLedger for PAYMENT entries for this schedule
            $paymentLedgerEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                ->where(mr_col('schedule_id'), $schedule->schedule_id)
                ->where(mr_col('trans'), 'PAYMENT')
                ->where(function($query) use ($dueDate) {
                    $query->where(mr_col('date'), '<=', $dueDate->format('Y-m-d'))
                          ->orWhere(function($q) use ($dueDate) {
                              $q->whereNotNull(mr_col('txtime'))
                                ->whereDate('txtime', '<=', $dueDate->format('Y-m-d'));
                          });
                })
                ->first();
            
            if ($paymentLedgerEntry) {
                $paymentMadeBeforeDueDate = true;
            } else {
                // Also check downloaded_readings for payments made before due date
                if ($schedule->downloaded_id) {
                    $downloadedReading = DB::table(mr_col('downloaded_readings'))
                        ->where(mr_col('id'), $schedule->downloaded_id)
                        ->whereNotNull(mr_col('paid_at'))
                        ->whereDate('paid_at', '<=', $dueDate->format('Y-m-d'))
                        ->first();
                    
                    if ($downloadedReading) {
                        $paymentMadeBeforeDueDate = true;
                    }
                }
                
                // Also check consumer_payments table for payments made before due date
                if (!$paymentMadeBeforeDueDate && $schedule->downloaded_id) {
                    $consumerPayment = DB::table(mr_col('consumer_payments as cp'))
                        ->join(mr_col('downloaded_readings as dr'), mr_col('cp.reading_id'), '=', mr_col('dr.id'))
                        ->where(mr_col('dr.schedule_id'), $schedule->schedule_id)
                        ->where(function($query) use ($dueDate) {
                            $query->whereNotNull(mr_col('cp.paid_at'))
                                  ->whereDate('cp.paid_at', '<=', $dueDate->format('Y-m-d'))
                                  ->where(function($q) {
                                      $q->where(mr_col('cp.payment_amount'), '>', 0)
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
                $balanceEntryAtDueDate = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                    ->where(function($query) use ($dueDate) {
                        $query->where(mr_col('date'), '<=', $dueDate->format('Y-m-d'))
                              ->orWhere(mr_col('due_date'), '<=', $dueDate->format('Y-m-d'));
                    })
                    ->whereNotNull(mr_col('balance'))
                    ->orderBy(mr_col('date'), 'desc')
                    ->orderBy(mr_col('id'), 'desc')
                    ->first();
                
                if ($balanceEntryAtDueDate) {
                    $balanceAtDueDate = (float)($balanceEntryAtDueDate->balance ?? 0);
                } else {
                    // If no ledger entry found, check consumer balance
                    $consumer = ConsumerZone::find($consumerZoneId);
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
            $billEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                ->where(mr_col('schedule_id'), $schedule->schedule_id)
                ->where(mr_col('trans'), 'BILL')
                ->first();

            $billAmount = $billEntry ? (float)($billEntry->billamount ?? 0) : $currentBill;

            if ($billAmount <= 0) {
                continue;
            }

            // Calculate penalty: 10% of Bill Amount
            $penaltyAmount = round($billAmount * 0.10, 2);

            if ($penaltyAmount > 0) {
                // Get the latest balance before this penalty
                $previousBalanceEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
                    ->where(function($query) use ($dueDate) {
                        $query->where(mr_col('date'), '<=', $dueDate->format('Y-m-d'))
                              ->orWhere(mr_col('due_date'), '<=', $dueDate->format('Y-m-d'));
                    })
                    ->orderBy(mr_col('date'), 'desc')
                    ->orderBy(mr_col('id'), 'desc')
                    ->first();

                $previousBalance = $previousBalanceEntry ? (float)($previousBalanceEntry->balance ?? 0) : 0;

                if ($previousBalance == 0) {
                    $consumer = ConsumerZone::find($consumerZoneId);
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
                    Penalty::create(Penalty::filterTableAttributes([
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
                    ]));

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
        $penalties = Penalty::query()->where(mr_col('consumer_zone_id'), $consumerZoneId)
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc')
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
                $maxOrFromReadings = DB::table(mr_col('downloaded_readings'))
                    ->whereNotNull(mr_col('official_receipt_number'))
                    ->where(mr_col('official_receipt_number'), '!=', '')
                    ->whereRaw('official_receipt_number REGEXP "^[0-9]+$"')
                    ->selectRaw('MAX(CAST(official_receipt_number AS UNSIGNED)) as max_or')
                    ->value(mr_col('max_or'));
                
                if ($maxOrFromReadings) {
                    $maxOrNumber = max($maxOrNumber, (int) $maxOrFromReadings);
                }
            }
            
            // Check consumer_payments: include base number from "123456-SC" so sequence is correct
            $hasPaymentsOrColumn = Schema::hasColumn('consumer_payments', 'or_number');
            if ($hasPaymentsOrColumn) {
                $paymentOrs = DB::table(mr_col('consumer_payments'))
                    ->whereNotNull(mr_col('or_number'))
                    ->where(mr_col('or_number'), '!=', '')
                    ->pluck(mr_col('or_number'));
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
                $exists = DB::table(mr_col('downloaded_readings'))
                    ->where(mr_col('official_receipt_number'), $orNumber)
                    ->exists();
            }
            if (!$exists && $hasPaymentsOrColumn) {
                $exists = DB::table(mr_col('consumer_payments'))
                    ->where(mr_col('or_number'), $orNumber)
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
        $consumer = ConsumerZone::where(function($query) use ($accountNumber, $normalizedAccount) {
            $query->where(mr_col('account_no'), $accountNumber)
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
        $ledgerEntries = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
            ->where(mr_col('debit'), '>', 0)
            ->orderBy(mr_col('date'), 'desc')
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
        return app(BillMonthDetailsService::class)->handle($request);
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
        $accounts = ConsumerZone::where(function($q) use ($query, $normalizedQuery) {
                $q->whereRaw("UPPER(TRIM(account_no)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("REPLACE(UPPER(TRIM(account_no)), '-', '') LIKE ?", [$normalizedQuery . '%'])
                  ->orWhereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $query . '%']);
            })
            ->select('account_no', 'account_name')
            ->distinct()
            ->orderBy(mr_col('account_no'), 'asc')
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
