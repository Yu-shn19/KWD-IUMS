<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZoneOne; // MAO NI AKOANG GI ADD
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
            $zonesQuery = DB::table('meter_reading_schedules as mrs')
                ->join('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
                ->where('mrs.assigned_reader_id', $readerId)
                ->select('cz.zone_code as zone')
                ->distinct();

            if ($readingDate !== null && $readingDate !== '') {
                $dateNorm = $readingDate instanceof \DateTimeInterface
                    ? $readingDate->format('Y-m-d')
                    : date('Y-m-d', strtotime($readingDate));
                $zonesQuery->where(function ($q) use ($dateNorm) {
                    $q->whereDate('mrs.reading_date', $dateNorm)
                      ->orWhereDate('mrs.bill_date', $dateNorm);
                });
            }

            $zones = $zonesQuery->pluck('zone')
                ->filter(function ($z) {
                    return $z !== null && $z !== '';
                })
                ->values()
                ->toArray();

            $readingDates = DB::table('meter_reading_schedules as mrs')
                ->where('mrs.assigned_reader_id', $readerId)
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
            $query = DB::table('meter_reading_schedules as mrs')
                ->join('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
                ->where('mrs.assigned_reader_id', $readerId)
                ->select(
                    'mrs.id',
                    'mrs.sedr_number',
                    'mrs.reading_date',
                    'mrs.bill_date',
                    'mrs.due_date',
                    'mrs.previous_reading',
                    'mrs.current_reading',
                    'mrs.consumption',
                    'mrs.current_bill',
                    'mrs.arrears',
                    'mrs.status',
                    'cz.zone_code as zone',
                    'cz.account_no as account_number',
                    'cz.account_name',
                    'cz.address',
                    'cz.meter_number',
                    'cz.category_code as category'
                );

            if ($zone !== null && $zone !== '') {
                $query->where('cz.zone_code', $zone);
            }
            if ($readingDate !== null && $readingDate !== '') {
                $dateNorm = $readingDate instanceof \DateTimeInterface
                    ? $readingDate->format('Y-m-d')
                    : date('Y-m-d', strtotime($readingDate));
                $query->where(function ($q) use ($dateNorm) {
                    $q->whereDate('mrs.reading_date', $dateNorm)
                      ->orWhereDate('mrs.bill_date', $dateNorm);
                });
            }

            $rows = $query->orderByRaw('COALESCE(mrs.reading_date, mrs.bill_date) DESC')
                ->orderBy('mrs.sedr_number')
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
    
    
        /**
     * GET /api/consumer/zone?account_no=...
     * Returns consumer_zone row fields used by the mobile app (lat/lng for maps). apil ni sa akoang gi add
     * Authenticated readers only (api.reader middleware).
     */
    public function getConsumerZone(Request $request)
    {
        $accountNo = trim((string) $request->query('account_no', ''));
        if ($accountNo === '' || strlen($accountNo) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter account_no is required.',
            ], 422);
        }

        $normalized = str_replace('-', '', $accountNo);

        $consumer = ConsumerZoneOne::where(function ($query) use ($accountNo, $normalized) {
            $query->where('account_no', $accountNo)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])
                ->orWhereRaw('UPPER(TRIM(account_no)) = ?', [strtoupper(trim($accountNo))]);
        })->first();

        if (! $consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer not found for this account number.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'consumer' => [
                'id' => $consumer->id,
                'account_no' => $consumer->account_no,
                'account_name' => $consumer->account_name,
                'latitude' => $consumer->latitude,
                'longitude' => $consumer->longitude,
                'zone_code' => $consumer->zone_code ?? null,
                'meter_number' => $consumer->meter_number ?? null,
            ],
        ]);
    }
    
    
    /**
     * POST /api/consumer/coordinates
     * Save latitude/longitude on consumer_zone (ConsumerZoneOne) for the given account_no.
     * Authenticated readers only (api.reader middleware). MAO NI AKOANG GI ADD 
     */
    public function saveConsumerCoordinates(Request $request)
    {
        $accountNo = trim((string) $request->input('account_no', ''));
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if ($accountNo === '' || $latitude === null || $longitude === null) {
            return response()->json([
                'success' => false,
                'message' => 'account_no, latitude, and longitude are required.',
            ], 422);
        }

        $lat = filter_var($latitude, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($longitude, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid latitude or longitude.',
            ], 422);
        }

        $normalized = str_replace('-', '', $accountNo);

        $consumer = ConsumerZoneOne::where(function ($query) use ($accountNo, $normalized) {
            $query->where('account_no', $accountNo)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])
                ->orWhereRaw('UPPER(TRIM(account_no)) = ?', [strtoupper(trim($accountNo))]);
        })->first();

        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer not found for this account number.',
            ], 404);
        }

        $consumer->update(ConsumerZoneOne::filterTableAttributes([
            'latitude' => $lat,
            'longitude' => $lng,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Coordinates saved for '.$consumer->account_no.'.',
            'account_no' => $consumer->account_no,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }
}


