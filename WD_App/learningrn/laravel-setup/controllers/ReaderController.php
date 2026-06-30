<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reader app: downloaded_readings by zone and reading_date for the logged-in reader.
 * Table: downloaded_readings (expected columns: reader_id, zone, reading_date, and reading/account fields).
 */
class ReaderController extends Controller
{
    /**
     * POST /api/reader/submit-reading
     * Retry-safe submit endpoint used by mobile app offline sync.
     */
    public function submitReading(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|numeric',
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'nullable|date',
            'reader_notes' => 'nullable|string',
            'reader_id' => 'nullable|numeric',
            'consumption' => 'nullable|numeric|min:0',
        ]);

        $scheduleId = (int) $validated['schedule_id'];
        $currentReading = (float) $validated['current_reading'];
        $readingDate = isset($validated['reading_date']) && $validated['reading_date']
            ? date('Y-m-d', strtotime($validated['reading_date']))
            : date('Y-m-d');
        $readerId = isset($validated['reader_id']) && $validated['reader_id']
            ? (int) $validated['reader_id']
            : (optional($request->user())->id ?: null);
        $readerNotes = $validated['reader_notes'] ?? '';
        $explicitConsumption = isset($validated['consumption']) ? (float) $validated['consumption'] : null;

        try {
            $scheduleTable = 'meter_reading_schedules';
            if (!$this->tableExists($scheduleTable)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule table not found',
                ], 500);
            }

            $result = DB::transaction(function () use (
                $scheduleTable,
                $scheduleId,
                $currentReading,
                $readingDate,
                $readerId,
                $readerNotes,
                $explicitConsumption
            ) {
                $schedule = DB::table($scheduleTable)
                    ->where('id', $scheduleId)
                    ->lockForUpdate()
                    ->first();

                if (!$schedule) {
                    return [
                        'http' => 404,
                        'payload' => [
                            'success' => false,
                            'message' => 'Schedule not found',
                        ],
                    ];
                }

                $scheduleArr = (array) $schedule;
                $currentCol = $this->firstExistingColumn($scheduleTable, ['current_reading', 'currentReading', 'reading']);
                $previousCol = $this->firstExistingColumn($scheduleTable, ['previous_reading', 'last_reading', 'lastReading']);
                $dateCol = $this->firstExistingColumn($scheduleTable, ['reading_date', 'bill_date', 'updated_at']);
                $readerCol = $this->firstExistingColumn($scheduleTable, ['reader_id', 'readerId', 'user_id']);
                $notesCol = $this->firstExistingColumn($scheduleTable, ['reader_notes', 'notes']);
                $consumptionCol = $this->firstExistingColumn($scheduleTable, ['consumption']);
                $statusCol = $this->firstExistingColumn($scheduleTable, ['status']);
                $updatedAtCol = $this->firstExistingColumn($scheduleTable, ['updated_at']);

                // Idempotency: same value + same reading date should return success (already processed)
                $existingCurrent = ($currentCol && array_key_exists($currentCol, $scheduleArr)) ? $scheduleArr[$currentCol] : null;
                $existingDate = ($dateCol && array_key_exists($dateCol, $scheduleArr)) ? (string) $scheduleArr[$dateCol] : null;
                $existingDateNorm = $existingDate ? date('Y-m-d', strtotime($existingDate)) : null;
                if ($existingCurrent !== null && (float) $existingCurrent === $currentReading && ($existingDateNorm === null || $existingDateNorm === $readingDate)) {
                    return [
                        'http' => 200,
                        'payload' => [
                            'success' => true,
                            'already_processed' => true,
                            'message' => 'Reading already processed',
                            'data' => [
                                'schedule_id' => $scheduleId,
                                'current_reading' => $currentReading,
                                'reading_date' => $readingDate,
                            ],
                        ],
                    ];
                }

                $computedConsumption = $explicitConsumption;
                if ($computedConsumption === null && $previousCol && array_key_exists($previousCol, $scheduleArr)) {
                    $prev = (float) ($scheduleArr[$previousCol] ?? 0);
                    $computedConsumption = max(0, $currentReading - $prev);
                }
                if ($computedConsumption === null) {
                    $computedConsumption = 0;
                }

                $updateData = [];
                if ($currentCol) $updateData[$currentCol] = $currentReading;
                if ($dateCol && $dateCol !== 'updated_at') $updateData[$dateCol] = $readingDate;
                if ($readerCol && $readerId !== null) $updateData[$readerCol] = $readerId;
                if ($notesCol) $updateData[$notesCol] = $readerNotes;
                if ($consumptionCol) $updateData[$consumptionCol] = $computedConsumption;
                if ($statusCol) $updateData[$statusCol] = 'completed';
                if ($updatedAtCol) $updateData[$updatedAtCol] = now();

                if (!empty($updateData)) {
                    DB::table($scheduleTable)->where('id', $scheduleId)->update($updateData);
                }

                // Keep downloaded_readings consistent if table exists
                $downloadedTable = 'downloaded_readings';
                if ($this->tableExists($downloadedTable) && $readerId !== null) {
                    $downloadedUpdate = [];
                    $drCurrentCol = $this->firstExistingColumn($downloadedTable, ['current_reading', 'reading']);
                    $drConsumptionCol = $this->firstExistingColumn($downloadedTable, ['consumption']);
                    $drReaderCol = $this->firstExistingColumn($downloadedTable, ['reader_id', 'user_id']);
                    $drDateCol = $this->firstExistingColumn($downloadedTable, ['reading_date', 'date']);
                    $drUpdatedAtCol = $this->firstExistingColumn($downloadedTable, ['updated_at']);

                    if ($drCurrentCol) $downloadedUpdate[$drCurrentCol] = $currentReading;
                    if ($drConsumptionCol) $downloadedUpdate[$drConsumptionCol] = $computedConsumption;
                    if ($drUpdatedAtCol) $downloadedUpdate[$drUpdatedAtCol] = now();

                    if (!empty($downloadedUpdate)) {
                        $query = DB::table($downloadedTable)->where('schedule_id', $scheduleId);
                        if ($drReaderCol) $query->where($drReaderCol, $readerId);
                        if ($drDateCol) $query->whereDate($drDateCol, $readingDate);
                        $query->update($downloadedUpdate);
                    }
                }

                return [
                    'http' => 200,
                    'payload' => [
                        'success' => true,
                        'message' => 'Reading submitted successfully',
                        'data' => [
                            'schedule_id' => $scheduleId,
                            'current_reading' => $currentReading,
                            'consumption' => $computedConsumption,
                            'reading_date' => $readingDate,
                        ],
                    ],
                ];
            });

            return response()->json($result['payload'], $result['http']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting reading: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/reader/downloaded-readings/filters?reader_id=123&reading_date=2026-03-03
     * Returns distinct zones and reading_dates for the given reader from downloaded_readings.
     * If reading_date is provided, zones are limited to those that have at least one record on that date (for the reader).
     */
    public function downloadedReadingsFilters(Request $request)
    {
        $readerId = $request->query('reader_id');
        $readingDate = $request->query('reading_date');
        if (!$readerId) {
            return response()->json(['zones' => [], 'reading_dates' => []], 200);
        }

        try {
            $table = 'downloaded_readings';
            $readerIdCol = $this->readerIdColumn($table);
            $zoneCol = $this->zoneColumn($table);
            $dateCol = $this->readingDateColumn($table);

            $zonesQuery = DB::table($table)->where($readerIdCol, $readerId);
            if ($readingDate !== null && $readingDate !== '') {
                $dateNorm = $readingDate instanceof \DateTimeInterface
                    ? $readingDate->format('Y-m-d')
                    : date('Y-m-d', strtotime($readingDate));
                $zonesQuery->where($dateCol, $dateNorm);
            }
            $zones = $zonesQuery->distinct()
                ->pluck($zoneCol)
                ->filter()
                ->values()
                ->toArray();

            $readingDates = DB::table($table)
                ->where($readerIdCol, $readerId)
                ->distinct()
                ->orderBy($dateCol, 'desc')
                ->pluck($dateCol)
                ->map(function ($d) {
                    return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d;
                })
                ->unique()
                ->values()
                ->toArray();

            return response()->json([
                'zones' => $zones,
                'reading_dates' => $readingDates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'zones' => [],
                'reading_dates' => [],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/reader/downloaded-readings?reader_id=123&zone=Z1&reading_date=2025-02-24
     * Returns list of downloaded_readings for the reader, optional zone and reading_date filter.
     */
    public function downloadedReadings(Request $request)
    {
        $readerId = $request->query('reader_id');
        $zone = $request->query('zone');
        $readingDate = $request->query('reading_date');

        if (!$readerId) {
            return response()->json(['data' => []], 200);
        }

        try {
            $table = 'downloaded_readings';
            $readerIdCol = $this->readerIdColumn($table);
            $zoneCol = $this->zoneColumn($table);
            $dateCol = $this->readingDateColumn($table);

            $query = DB::table($table)->where($readerIdCol, $readerId);

            if ($zone !== null && $zone !== '') {
                $query->where($zoneCol, $zone);
            }
            if ($readingDate !== null && $readingDate !== '') {
                $query->where($dateCol, $readingDate);
            }

            $rows = $query->orderBy($dateCol, 'desc')->get();

            // Normalize to common keys for the app
            $data = $rows->map(function ($row) use ($zoneCol, $dateCol) {
                $r = (array) $row;
                return array_merge($r, [
                    'zone' => $r[$zoneCol] ?? $r['zone'] ?? null,
                    'reading_date' => isset($r[$dateCol]) ? ($r[$dateCol] instanceof \DateTimeInterface ? $r[$dateCol]->format('Y-m-d') : (string) $r[$dateCol]) : ($r['reading_date'] ?? null),
                    'account_number' => $r['account_number'] ?? $r['account_no'] ?? $r['accountNumber'] ?? null,
                    'account_name' => $r['account_name'] ?? $r['name'] ?? $r['consumer_name'] ?? null,
                    'current_reading' => $r['current_reading'] ?? $r['reading'] ?? null,
                    'consumption' => $r['consumption'] ?? null,
                ]);
            })->toArray();

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function readerIdColumn(string $table): string
    {
        $cols = ['reader_id', 'user_id', 'readerId'];
        foreach ($cols as $c) {
            if ($this->columnExists($table, $c)) {
                return $c;
            }
        }
        return 'reader_id';
    }

    private function zoneColumn(string $table): string
    {
        $cols = ['zone', 'zone_name', 'zone_id'];
        foreach ($cols as $c) {
            if ($this->columnExists($table, $c)) {
                return $c;
            }
        }
        return 'zone';
    }

    private function readingDateColumn(string $table): string
    {
        $cols = ['reading_date', 'reading_date_date', 'date'];
        foreach ($cols as $c) {
            if ($this->columnExists($table, $c)) {
                return $c;
            }
        }
        return 'reading_date';
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return in_array($column, Schema::getColumnListing($table), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }
        return null;
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
