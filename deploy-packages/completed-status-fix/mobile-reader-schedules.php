<?php
/**
 * Drop-in reader schedules endpoint for the mobile app.
 * Matches Download Reading: schedule Completed / Curr. Read / downloaded_readings => completed.
 *
 * URL: https://YOUR-DOMAIN/mobile-reader-schedules.php?reader_id=ID
 * Auth: Bearer token (same as /api/reader/schedules)
 *
 * Upload this single file to the server public/ folder if full git deploy is delayed.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Bootstrap failed: ' . $e->getMessage()]);
    exit;
}

use App\Models\User;
use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

function mrs_json($payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function mrs_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return $_GET['api_token'] ?? null;
}

$token = mrs_bearer_token();
if (!$token) {
    mrs_json(['success' => false, 'message' => 'Unauthorized. Please login.'], 401);
}

try {
    $decoded = base64_decode($token, true);
    $parts = $decoded !== false ? explode(':', $decoded) : [];
    if (count($parts) !== 2) {
        mrs_json(['success' => false, 'message' => 'Invalid token format'], 401);
    }
    $userId = (int) $parts[0];
    $timestamp = (int) $parts[1];
    if ($timestamp > 0 && (time() - $timestamp) > 86400) {
        mrs_json(['success' => false, 'message' => 'Token expired. Please login again.'], 401);
    }
    $reader = User::find($userId);
    $role = strtolower((string) ($reader->role ?? ''));
    if (!$reader || ($role !== 'reader' && $role !== 'disconnector')) {
        mrs_json(['success' => false, 'message' => 'Access denied.'], 403);
    }
} catch (Throwable $e) {
    mrs_json(['success' => false, 'message' => 'Invalid authentication token'], 401);
}

$readerId = (int) ($_GET['reader_id'] ?? $reader->id);
if ($readerId !== (int) $reader->id) {
    // Allow explicit reader_id only when it matches the authenticated user
    $readerId = (int) $reader->id;
}

$billMonthRaw = $_GET['bill_month'] ?? null;

try {
    $query = MeterReadingSchedule::with('consumerZone')
        ->where('assigned_reader_id', $readerId)
        ->whereIn('status', ['Assigned', 'In Progress', 'Completed', 'Prepared']);

    if ($billMonthRaw) {
        $bm = Carbon::parse($billMonthRaw);
        $query->whereYear('bill_month', $bm->year)->whereMonth('bill_month', $bm->month);
        $billMonthNormalized = $bm->copy()->startOfMonth()->format('Y-m-d');
    } else {
        $latestBillMonth = MeterReadingSchedule::query()
            ->where('assigned_reader_id', $readerId)
            ->whereIn('status', ['Assigned', 'In Progress', 'Completed', 'Prepared'])
            ->orderByDesc('bill_month')
            ->value('bill_month');
        if ($latestBillMonth) {
            $lm = Carbon::parse($latestBillMonth);
            $query->whereYear('bill_month', $lm->year)->whereMonth('bill_month', $lm->month);
            $billMonthNormalized = $lm->copy()->startOfMonth()->format('Y-m-d');
        } else {
            $billMonthNormalized = null;
        }
    }

    $schedules = $query->orderByRaw("
        CASE
            WHEN status = 'Assigned' THEN 1
            WHEN status = 'In Progress' THEN 2
            WHEN status = 'Completed' THEN 3
            ELSE 4
        END
    ")->orderBy('id')->get();

    $scheduleIds = $schedules->pluck('id')->map(fn ($id) => (int) $id)->all();
    $consumerZoneIds = $schedules->pluck('consumer_zone_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

    $downloadedByScheduleId = collect();
    $downloadedByConsumerZoneId = collect();
    $downloadedByAccount = collect();

    if (!empty($scheduleIds) || !empty($consumerZoneIds)) {
        $downloadRows = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'mrs.id', '=', 'dr.schedule_id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->whereRaw('cz.id = COALESCE(dr.consumer_zone_id, mrs.consumer_zone_id)');
            })
            ->where(function ($q) use ($scheduleIds, $consumerZoneIds, $readerId, $billMonthNormalized) {
                if (!empty($scheduleIds)) {
                    $q->whereIn('dr.schedule_id', $scheduleIds);
                }
                if (!empty($consumerZoneIds)) {
                    $q->orWhereIn('dr.consumer_zone_id', $consumerZoneIds);
                }
                if ($billMonthNormalized) {
                    $q->orWhere(function ($q2) use ($readerId, $billMonthNormalized) {
                        $q2->where('mrs.assigned_reader_id', $readerId)
                            ->whereYear('mrs.bill_month', (int) substr($billMonthNormalized, 0, 4))
                            ->whereMonth('mrs.bill_month', (int) substr($billMonthNormalized, 5, 2));
                    });
                }
            })
            ->where(function ($q) {
                $q->whereNotNull('dr.current_reading')
                    ->orWhereRaw('LOWER(COALESCE(dr.status, "")) IN (?, ?)', ['completed', 'verified']);
            })
            ->orderByDesc('dr.id')
            ->select([
                'dr.id',
                'dr.schedule_id',
                'dr.consumer_zone_id',
                'dr.reader_id',
                'dr.current_reading',
                'dr.consumption',
                'dr.reading_date',
                'dr.status',
                'dr.reader_notes',
                'mrs.consumer_zone_id as schedule_consumer_zone_id',
                'cz.account_no',
                'cz.id as resolved_consumer_zone_id',
            ])
            ->get();

        foreach ($downloadRows as $dr) {
            $sid = (int) ($dr->schedule_id ?? 0);
            $czid = (int) ($dr->consumer_zone_id ?: ($dr->schedule_consumer_zone_id ?? 0) ?: ($dr->resolved_consumer_zone_id ?? 0));
            $acct = strtolower(trim((string) ($dr->account_no ?? '')));

            if ($sid > 0 && !$downloadedByScheduleId->has($sid)) {
                $downloadedByScheduleId->put($sid, $dr);
            }
            if ($czid > 0 && !$downloadedByConsumerZoneId->has($czid)) {
                $downloadedByConsumerZoneId->put($czid, $dr);
            }
            if ($acct !== '' && !$downloadedByAccount->has($acct)) {
                $downloadedByAccount->put($acct, $dr);
            }
        }
    }

    $rateCodes = collect();
    if (!empty($consumerZoneIds)) {
        $rateCodes = DB::table('consumer_zone')
            ->whereIn('id', $consumerZoneIds)
            ->select('id', 'rate_code')
            ->get()
            ->keyBy('id');
    }

    $formatName = function ($user) {
        $parts = array_filter([
            $user->first_name ?? null,
            $user->middle_name ?? null,
            $user->last_name ?? null,
        ]);
        if (!empty($parts)) {
            return implode(' ', $parts);
        }
        return $user->name ?? 'READER';
    };

    $payload = [
        'success' => true,
        'message' => 'Schedules retrieved successfully',
        'version' => '1.2-mobile-reader-schedules',
        'bill_month' => $billMonthNormalized ?? null,
        'reader' => [
            'id' => $reader->id,
            'name' => $formatName($reader),
        ],
        'total_schedules' => $schedules->count(),
        'schedules' => $schedules->map(function ($schedule) use (
            $downloadedByScheduleId,
            $downloadedByConsumerZoneId,
            $downloadedByAccount,
            $rateCodes
        ) {
            $accountKey = strtolower(trim((string) ($schedule->account_number ?? '')));
            $downloaded = $downloadedByScheduleId->get((int) $schedule->id);
            if (!$downloaded && $schedule->consumer_zone_id) {
                $downloaded = $downloadedByConsumerZoneId->get((int) $schedule->consumer_zone_id);
            }
            if (!$downloaded && $accountKey !== '') {
                $downloaded = $downloadedByAccount->get($accountKey);
            }

            $scheduleHasReading = $schedule->current_reading !== null && $schedule->current_reading !== '';
            $scheduleCompleted = strcasecmp((string) $schedule->status, 'Completed') === 0
                || strcasecmp((string) $schedule->status, 'Verified') === 0;
            $hasDownloadedReading = (bool) $downloaded;
            $isReallyCompleted = $hasDownloadedReading || $scheduleCompleted || $scheduleHasReading;

            $readingDate = null;
            if ($downloaded && !empty($downloaded->reading_date)) {
                try {
                    $readingDate = Carbon::parse($downloaded->reading_date)->format('Y-m-d');
                } catch (Throwable $e) {
                    $readingDate = (string) $downloaded->reading_date;
                }
            } elseif ($schedule->reading_date) {
                $readingDate = $schedule->reading_date?->format('Y-m-d');
            }

            return [
                'id' => $schedule->id,
                'sedr_number' => $schedule->sedr_number,
                'account_number' => $schedule->account_number,
                'account_name' => $schedule->account_name,
                'address' => $schedule->address,
                'zone' => $schedule->zone,
                'category' => $schedule->category,
                'rate_code' => $rateCodes->get($schedule->consumer_zone_id)?->rate_code ?? null,
                'meter_number' => $schedule->meter_number,
                'previous_reading' => $schedule->previous_reading,
                'previous_reading_date' => $schedule->previous_reading_date?->format('Y-m-d'),
                'current_reading' => $downloaded
                    ? $downloaded->current_reading
                    : ($scheduleHasReading ? $schedule->current_reading : null),
                'reading_date' => $readingDate,
                'consumption' => $downloaded
                    ? $downloaded->consumption
                    : ($scheduleHasReading ? $schedule->consumption : null),
                'status' => $isReallyCompleted ? 'completed' : $schedule->status,
                'schedule_status' => $schedule->status,
                'schedule_current_reading' => $schedule->current_reading,
                'schedule_consumption' => $schedule->consumption,
                'has_downloaded_reading' => $hasDownloadedReading || $isReallyCompleted,
                'downloaded_reading_id' => $downloaded->id ?? null,
                'downloaded_reading_status' => $downloaded->status ?? ($isReallyCompleted ? 'completed' : null),
                'bill_month' => $schedule->bill_month?->format('Y-m-d'),
                'bill_date' => $schedule->bill_date?->format('Y-m-d'),
                'due_date' => $schedule->due_date?->format('Y-m-d'),
                'arrears' => (float) ($schedule->arrears ?? 0),
                'prior_years' => (float) ($schedule->prior_years ?? 0),
                'penalty' => (float) ($schedule->penalty ?? 0),
                'meter_rental_arrears' => (float) ($schedule->meter_rental_arrears ?? 0),
                'reader_notes' => $downloaded->reader_notes ?? null,
            ];
        })->values(),
    ];

    mrs_json($payload);
} catch (Throwable $e) {
    mrs_json([
        'success' => false,
        'message' => 'Error loading schedules: ' . $e->getMessage(),
    ], 500);
}
