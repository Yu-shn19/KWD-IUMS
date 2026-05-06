<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reader app: Retrieve Zone based on meter_reading_schedules (assigned to the reader).
 * All data comes from meter_reading_schedules table, not downloaded_readings.
 */
class ReaderController extends Controller
{
    /**
     * GET /api/reader/downloaded-readings/filters?reader_id=123&reading_date=2026-03-03
     * Returns distinct zones and reading_dates for the given reader from meter_reading_schedules.
     * If reading_date is provided, zones are limited to those that have at least one schedule on that date.
     */
    public function downloadedReadingsFilters(Request $request)
    {
        $readerId = $request->query('reader_id');
        $readingDate = $request->query('reading_date');
        if (!$readerId) {
            return response()->json(['zones' => [], 'reading_dates' => []], 200);
        }

        try {
            $table = 'meter_reading_schedules';

            $zonesQuery = DB::table($table)
                ->where('assigned_reader_id', $readerId)
                ->select('zone')
                ->distinct();

            if ($readingDate !== null && $readingDate !== '') {
                $dateNorm = $readingDate instanceof \DateTimeInterface
                    ? $readingDate->format('Y-m-d')
                    : date('Y-m-d', strtotime($readingDate));
                $zonesQuery->where(function ($q) use ($dateNorm) {
                    $q->whereDate('reading_date', $dateNorm)
                      ->orWhereDate('bill_date', $dateNorm);
                });
            }

            $zones = $zonesQuery->pluck('zone')
                ->filter(function ($z) {
                    return $z !== null && $z !== '';
                })
                ->values()
                ->toArray();

            $readingDates = DB::table($table)
                ->where('assigned_reader_id', $readerId)
                ->selectRaw('DISTINCT COALESCE(reading_date, bill_date) as dt')
                ->whereNotNull(DB::raw('COALESCE(reading_date, bill_date)'))
                ->orderBy('dt', 'desc')
                ->pluck('dt')
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
     * Returns list of meter_reading_schedules for the assigned reader, optional zone and reading_date filter.
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
            $query = DB::table('meter_reading_schedules')
                ->where('assigned_reader_id', $readerId);

            if ($zone !== null && $zone !== '') {
                $query->where('zone', $zone);
            }
            if ($readingDate !== null && $readingDate !== '') {
                $dateNorm = $readingDate instanceof \DateTimeInterface
                    ? $readingDate->format('Y-m-d')
                    : date('Y-m-d', strtotime($readingDate));
                $query->where(function ($q) use ($dateNorm) {
                    $q->whereDate('reading_date', $dateNorm)
                      ->orWhereDate('bill_date', $dateNorm);
                });
            }

            $rows = $query->orderByRaw('COALESCE(reading_date, bill_date) DESC')
                ->orderBy('sedr_number')
                ->get();

            $data = $rows->map(function ($row) {
                $r = (array) $row;
                $readingDateFormatted = isset($r['reading_date'])
                    ? ($r['reading_date'] instanceof \DateTimeInterface ? $r['reading_date']->format('Y-m-d') : (string) $r['reading_date'])
                    : null;
                $billDateFormatted = isset($r['bill_date'])
                    ? ($r['bill_date'] instanceof \DateTimeInterface ? $r['bill_date']->format('Y-m-d') : (string) $r['bill_date'])
                    : null;
                $dueDateFormatted = isset($r['due_date'])
                    ? ($r['due_date'] instanceof \DateTimeInterface ? $r['due_date']->format('Y-m-d') : (string) $r['due_date'])
                    : null;
                return [
                    'id' => $r['id'] ?? null,
                    'schedule_id' => $r['id'] ?? null,
                    'zone' => $r['zone'] ?? null,
                    'account_number' => $r['account_number'] ?? null,
                    'account_name' => $r['account_name'] ?? null,
                    'address' => $r['address'] ?? null,
                    'meter_number' => $r['meter_number'] ?? null,
                    'category' => $r['category'] ?? null,
                    'sedr_number' => $r['sedr_number'] ?? null,
                    'reading_date' => $readingDateFormatted,
                    'bill_date' => $billDateFormatted,
                    'due_date' => $dueDateFormatted,
                    'previous_reading' => $r['previous_reading'] ?? null,
                    'current_reading' => $r['current_reading'] ?? null,
                    'consumption' => $r['consumption'] ?? null,
                    'current_bill' => $r['current_bill'] ?? null,
                    'arrears' => $r['arrears'] ?? null,
                    'status' => $r['status'] ?? null,
                ];
            })->toArray();

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
