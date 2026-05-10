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
}
