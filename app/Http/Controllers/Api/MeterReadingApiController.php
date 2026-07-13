<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use App\Models\ConsumerLedger;
use App\Services\WaterBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class MeterReadingApiController extends Controller
{
    /**
     * Reader Login - For mobile app authentication
     */
    public function login(Request $request)
    {
        // Accept both 'username' and 'email' for backward compatibility
        $request->validate([
            'password' => 'required'
        ]);

        // Get username or email from request
        $username = $request->input('username') ?? $request->input('email');
        
        if (!$username) {
            return response()->json([
                'success' => false,
                'message' => 'Username or email is required'
            ], 422);
        }

        // Find user by email (username field contains email)
        $user = User::query()->where(mr_col('email'), $username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if user is a reader or disconnector
        $userRole = strtolower($user->role ?? '');
        if ($userRole !== 'reader' && $userRole !== 'disconnector') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only readers and disconnectors can use this app.'
            ], 403);
        }

        // For simplicity, we'll use user ID as token
        // In production, use Laravel Sanctum or Passport
        $token = base64_encode($user->id . ':' . time());

        // Return data in same format as old api-login.php for compatibility
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $this->formatName($user),
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]
        ]);
    }

      /**
     * Get assigned schedules for a reader with downloaded reading status
     */
    public function getAssignedSchedules(Request $request)
    {
        $request->validate([
            'reader_id' => 'required|exists:users,id'
        ]);

        $readerId = $request->reader_id;
        $zone = $request->get('zone');
        $billMonth = $request->get('bill_month');

        // Verify reader/disconnector exists
        $reader = User::find($readerId);
        $userRole = strtolower($reader->role ?? '');
        if (!$reader || ($userRole !== 'reader' && $userRole !== 'disconnector')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reader/disconnector ID'
            ], 403);
        }

        // Get the latest bill_month for this reader (prioritize active routes over completed ones)
        $latestBillMonth = null;
        $assignedZones = [];
        
        if ($readerId && !$billMonth) {
            // First, try to get latest bill_month from active routes (Assigned/In Progress)
            $latestBillMonth = MeterReadingSchedule::query()
                ->where(mr_col('assigned_reader_id'), $readerId)
                ->whereIn(mr_col('status'), ['Assigned', 'In Progress'])
                ->orderBy(mr_col('bill_month'), 'DESC')
                ->value(mr_col('bill_month'));
            
            // Get zones that have active routes in the latest bill_month
            if ($latestBillMonth) {
                $assignedZones = MeterReadingSchedule::query()
                    ->joinConsumerZone()
                    ->where(mr_col('meter_reading_schedules.assigned_reader_id'), $readerId)
                    ->where(mr_col('meter_reading_schedules.bill_month'), $latestBillMonth)
                    ->whereIn(mr_col('meter_reading_schedules.status'), ['Assigned', 'In Progress'])
                    ->distinct()
                    ->pluck('cz.zone_code')
                    ->filter()
                    ->values()
                    ->toArray();
            }
            
            // If no active routes, then get from completed routes
            if (!$latestBillMonth) {
                $latestBillMonth = MeterReadingSchedule::query()
                    ->where(mr_col('assigned_reader_id'), $readerId)
                    ->where(mr_col('status'), 'Completed')
                    ->orderBy(mr_col('bill_month'), 'DESC')
                    ->value(mr_col('bill_month'));
                
                // Get zones from completed routes in the latest bill_month
                if ($latestBillMonth) {
                    $assignedZones = MeterReadingSchedule::query()
                        ->joinConsumerZone()
                        ->where(mr_col('meter_reading_schedules.assigned_reader_id'), $readerId)
                        ->where(mr_col('meter_reading_schedules.bill_month'), $latestBillMonth)
                        ->where(mr_col('meter_reading_schedules.status'), 'Completed')
                        ->distinct()
                        ->pluck('cz.zone_code')
                        ->filter()
                        ->values()
                        ->toArray();
                }
            }
        }

        $query = MeterReadingSchedule::with('consumerZone')
            ->where(mr_col('assigned_reader_id'), $readerId)
            ->whereIn(mr_col('status'), ['Assigned', 'In Progress', 'Completed']);

        // Filter by zone: use provided one, or zones with active assignments
        if ($zone) {
            $query->forZoneCode($zone);
        } elseif (!empty($assignedZones)) {
            $query->whereHas('consumerZone', function ($q) use ($assignedZones) {
                $q->whereIn(mr_col('zone_code'), $assignedZones);
            });
        }

        // Filter by bill_month: use provided one, or latest one, or none
        if ($billMonth) {
            $query->where(mr_col('bill_month'), Carbon::parse($billMonth)->format('Y-m-d'));
        } elseif ($latestBillMonth) {
            // Automatically filter by latest bill_month to exclude old completed routes
            $query->where(mr_col('bill_month'), $latestBillMonth);
        }

        // Order by status priority (active routes first), then by account # tail (after last "-")
        $schedules = $query->orderByRaw("
            CASE 
                WHEN status = 'Assigned' THEN 1
                WHEN status = 'In Progress' THEN 2
                WHEN status = 'Completed' THEN 3
                ELSE 4
            END
        ")->orderByAccountNumberTail()->get();

        // Get downloaded readings for this reader (only for current bill_month schedules)
        $downloadedReadings = collect();
        if ($schedules->isNotEmpty()) {
            $scheduleIds = $schedules->pluck('id')->toArray();
        $downloadedReadings = DownloadedReading::query()
            ->where(mr_col('reader_id'), $readerId)
            ->where(mr_col('status'), 'completed')
                ->whereIn(mr_col('schedule_id'), $scheduleIds)
            ->get()
            ->keyBy(mr_col('schedule_id'));
        }

        // Get rate codes from consumer_zone table for all schedules
        $rateCodes = collect();
        if ($schedules->isNotEmpty()) {
            $consumerZoneIds = $schedules->pluck('consumer_zone_id')->filter()->unique()->values()->toArray();
            $rateCodes = DB::table(mr_col('consumer_zone'))
                ->whereIn(mr_col('id'), $consumerZoneIds)
                ->select('id', 'account_no', 'rate_code')
                ->get()
                ->keyBy(mr_col('id'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Schedules retrieved successfully',
            'reader' => [
                'id' => $reader->id,
                'name' => $this->formatName($reader)
            ],
            'total_schedules' => $schedules->count(),
            'schedules' => $schedules->map(function($schedule) use ($downloadedReadings, $rateCodes) {
                $downloaded = $downloadedReadings->get($schedule->id);
                $rateCode = $rateCodes->get($schedule->consumer_zone_id)?->rate_code ?? null;
                
                return [
                    'id' => $schedule->id,
                    'sedr_number' => $schedule->sedr_number,
                    'account_number' => $schedule->account_number,
                    'account_name' => $schedule->account_name,
                    'address' => $schedule->address,
                    'zone' => $schedule->zone,
                    'category' => $schedule->category,
                    'rate_code' => $rateCode,
                    'meter_number' => $schedule->meter_number,
                    'previous_reading' => $schedule->previous_reading,
                    'previous_reading_date' => $schedule->previous_reading_date?->format('Y-m-d'),
                    // Use downloaded reading if exists, otherwise use schedule data
                    'current_reading' => $downloaded ? $downloaded->current_reading : $schedule->current_reading,
                    'reading_date' => $downloaded ? $downloaded->reading_date->format('Y-m-d') : ($schedule->reading_date?->format('Y-m-d')),
                    'consumption' => $downloaded ? $downloaded->consumption : $schedule->consumption,
                    'status' => $downloaded ? 'completed' : $schedule->status,
                    'bill_month' => $schedule->bill_month->format('Y-m-d'),
                    'bill_date' => $schedule->bill_date->format('Y-m-d'),
                    'due_date' => $schedule->due_date->format('Y-m-d'),
                    'arrears' => (float)($schedule->arrears ?? 0),
                    'reader_notes' => $downloaded ? $downloaded->reader_notes : null
                ];
            })
        ]);
    }

    /**
     * Submit meter reading from mobile app.
     * Writes meter_reading_schedules + downloaded_readings (and ledger when present).
     */
    public function submitReading(Request $request)
    {
        Log::info('📥 Submit Reading Request Received', [
            'schedule_id' => $request->input('schedule_id'),
            'account_number' => $request->input('account_number'),
            'current_reading' => $request->input('current_reading'),
            'reader_id' => $request->input('reader_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $request->validate([
            'schedule_id' => 'nullable|integer',
            'account_number' => 'nullable|string|max:50',
            'current_reading' => 'required|integer|min:0',
            'reading_date' => 'nullable|date',
            'reader_notes' => 'nullable|string',
            'reader_id' => 'required|exists:users,id'
        ]);

        if (!$request->filled('schedule_id') && !$request->filled('account_number')) {
            return response()->json([
                'success' => false,
                'message' => 'schedule_id or account_number is required'
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $schedule = $this->resolveScheduleForSubmit($request);

                if (!$schedule) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected schedule id is invalid. No matching meter_reading_schedules row found for this reader/account on this server.'
                    ], 422);
                }

                Log::info('📋 Schedule Found', [
                    'schedule_id' => $schedule->id,
                    'account_number' => $schedule->account_number,
                    'previous_reading' => $schedule->previous_reading,
                    'resolved_from' => (int) $request->input('schedule_id') === (int) $schedule->id
                        ? 'schedule_id'
                        : 'account_number'
                ]);

                // Verify this schedule belongs to the requesting reader (if assigned_reader_id is set)
                if ($schedule->assigned_reader_id && (int) $schedule->assigned_reader_id !== (int) $request->reader_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to update this schedule'
                    ], 403);
                }

                $currentReading = (int) $request->current_reading;
                $previousReading = (int) ($schedule->previous_reading ?? 0);
                $consumption = max(0, $currentReading - $previousReading);

                $rateCode = null;
                $consumer = $schedule->consumerZone;
                if (!$consumer && $schedule->consumer_zone_id) {
                    $consumer = \App\Models\ConsumerZone::find($schedule->consumer_zone_id);
                }
                if ($consumer) {
                    $rateCode = $consumer->rate_code;
                }

                $currentBill = round(
                    $this->calculateWaterBill($consumption, $schedule->category, $rateCode),
                    2
                );

                // STEP 1: meter_reading_schedules
                $schedule->update(MeterReadingSchedule::filterTableAttributes([
                    'current_reading' => $currentReading,
                    'reading_date' => $request->reading_date ?? now(),
                    'consumption' => $consumption,
                    'current_bill' => $currentBill,
                    'status' => 'Completed',
                ]));
                $schedule->refresh();

                // STEP 2: downloaded_readings
                $reader = User::find($request->reader_id);

                $downloadedPayload = [
                    'consumer_zone_id' => $schedule->consumer_zone_id,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'consumption' => $consumption,
                    'current_bill' => $currentBill,
                    'reading_date' => $request->reading_date ?? now(),
                    'status' => 'completed',
                    'reader_notes' => $request->reader_notes,
                    'prepared_by' => $this->formatName($reader),
                ];
                if (Schema::hasColumn('downloaded_readings', 'completed_at')) {
                    $downloadedPayload['completed_at'] = now();
                }

                $downloaded = DownloadedReading::updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'reader_id' => $request->reader_id,
                    ],
                    $downloadedPayload
                );

                // STEP 3: consumer_ledgers (when BILL/BILLING row exists)
                if ($consumer) {
                    $ledgerEntry = ConsumerLedger::query()
                        ->where(mr_col('consumer_zone_id'), $consumer->id)
                        ->where(mr_col('schedule_id'), $schedule->id)
                        ->whereIn(mr_col('trans'), ['BILL', 'BILLING'])
                        ->first();

                    if ($ledgerEntry) {
                        $previousEntry = ConsumerLedger::query()
                            ->where(mr_col('consumer_zone_id'), $consumer->id)
                            ->where(mr_col('id'), '<', $ledgerEntry->id)
                            ->orderBy(mr_col('id'), 'desc')
                            ->first();

                        $previousBalance = $previousEntry
                            ? (float) $previousEntry->balance
                            : (float) ($consumer->balance ?? 0);

                        $others = 20.00;
                        $debit = $currentBill + $others;
                        $newBalance = $previousBalance + $debit;

                        $ledgerEntry->update([
                            'trans' => 'BILLING',
                            'reference' => $schedule->sedr_number ?? $ledgerEntry->reference,
                            'reading' => $currentReading,
                            'volume' => $consumption,
                            'billamount' => $currentBill,
                            'others' => $others,
                            'debit' => $debit,
                            'balance' => $newBalance,
                            'txtime' => now()
                        ]);

                        $subsequentEntries = ConsumerLedger::query()
                            ->where(mr_col('consumer_zone_id'), $consumer->id)
                            ->where(mr_col('id'), '>', $ledgerEntry->id)
                            ->orderBy(mr_col('id'), 'asc')
                            ->get();

                        $runningBalance = $newBalance;
                        foreach ($subsequentEntries as $entry) {
                            $entryDebit = (float) ($entry->debit ?? 0);
                            $entryCredit = (float) ($entry->credit ?? 0);
                            $runningBalance = $runningBalance + $entryDebit - $entryCredit;
                            $entry->update(['balance' => $runningBalance]);
                        }
                    }
                }

                Log::info('✅ Reading Submitted Successfully', [
                    'schedule_id' => $schedule->id,
                    'downloaded_reading_id' => $downloaded->id,
                    'account_number' => $schedule->account_number,
                    'current_reading' => $schedule->current_reading,
                    'consumption' => $schedule->consumption,
                    'current_bill' => $currentBill,
                    'status' => $schedule->status
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Meter reading submitted successfully',
                    'schedule' => [
                        'id' => $schedule->id,
                        'account_number' => $schedule->account_number,
                        'current_reading' => $schedule->current_reading,
                        'consumption' => $schedule->consumption,
                        'current_bill' => $schedule->current_bill,
                        'status' => $schedule->status
                    ],
                    'downloaded_reading' => [
                        'id' => $downloaded->id,
                        'schedule_id' => $downloaded->schedule_id,
                        'reader_id' => $downloaded->reader_id,
                        'current_reading' => $downloaded->current_reading,
                        'consumption' => $downloaded->consumption,
                        'current_bill' => $downloaded->current_bill,
                        'status' => $downloaded->status,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('❌ Error Submitting Reading', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'schedule_id' => $request->schedule_id ?? 'N/A',
                'account_number' => $request->account_number ?? 'N/A',
                'reader_id' => $request->reader_id ?? 'N/A'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error submitting reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve schedule by id, or rematch by account_number for this reader
     * (handles stale mobile schedule IDs after switching databases/hosts).
     */
    private function resolveScheduleForSubmit(Request $request): ?MeterReadingSchedule
    {
        $scheduleId = $request->input('schedule_id');
        $readerId = (int) $request->input('reader_id');
        $account = trim((string) $request->input('account_number', ''));

        if ($scheduleId) {
            $byId = MeterReadingSchedule::with('consumerZone')->find($scheduleId);
            if ($byId) {
                return $byId;
            }
        }

        if ($account === '') {
            return null;
        }

        $query = MeterReadingSchedule::with('consumerZone')
            ->where(mr_col('assigned_reader_id'), $readerId)
            ->whereHas('consumerZone', function ($q) use ($account) {
                $q->where(mr_col('account_no'), $account);
            })
            ->whereIn(mr_col('status'), ['Assigned', 'In Progress', 'Completed'])
            ->orderByDesc(mr_col('bill_month'))
            ->orderByDesc(mr_col('id'));

        $matched = $query->first();
        if ($matched) {
            return $matched;
        }

        // Last resort: any schedule for this account (unassigned / other statuses)
        return MeterReadingSchedule::with('consumerZone')
            ->whereHas('consumerZone', function ($q) use ($account) {
                $q->where(mr_col('account_no'), $account);
            })
            ->orderByDesc(mr_col('bill_month'))
            ->orderByDesc(mr_col('id'))
            ->first();
    }

    /**
     * Update schedule status (e.g., mark as "In Progress")
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:meter_reading_schedules,id',
            'status' => 'required|in:Assigned,In Progress,Completed',
            'reader_id' => 'required|exists:users,id'
        ]);

        try {
            $schedule = MeterReadingSchedule::find($request->schedule_id);

            // Verify authorization
            if ($schedule->assigned_reader_id != $request->reader_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this schedule'
                ], 403);
            }

            $schedule->update(MeterReadingSchedule::filterTableAttributes([
                'status' => $request->status,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'schedule' => [
                    'id' => $schedule->id,
                    'status' => $schedule->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reader statistics
     */
    public function getReaderStats(Request $request)
    {
        $request->validate([
            'reader_id' => 'required|exists:users,id'
        ]);

        $readerId = $request->reader_id;

        $stats = [
            'total_assigned' => MeterReadingSchedule::query()
                ->where(mr_col('assigned_reader_id'), $readerId)
                ->whereIn(mr_col('status'), ['Assigned', 'In Progress', 'Completed'])
                ->count(),
            'pending' => MeterReadingSchedule::query()
                ->where(mr_col('assigned_reader_id'), $readerId)
                ->where(mr_col('status'), 'Assigned')
                ->count(),
            'in_progress' => MeterReadingSchedule::query()
                ->where(mr_col('assigned_reader_id'), $readerId)
                ->where(mr_col('status'), 'In Progress')
                ->count(),
            'completed' => MeterReadingSchedule::query()
                ->where(mr_col('assigned_reader_id'), $readerId)
                ->where(mr_col('status'), 'Completed')
                ->count()
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Calculate water bill based on consumption (cubic meters), category, and rate_code
     * Uses database pricing tiers if available, falls back to hardcoded calculations
     * Category mapping: 12=Residential, 22=Government, 32=Industrial, 33=Commercial A,
     * 34=Commercial B, 35=Commercial D, 36=Wholesale
     */
    private function calculateWaterBill($consumption, $category = null, $rateCode = null)
    {
        $cu = (int) $consumption;
        if ($cu < 0) return 0;
        
        $catId = $this->resolveCategoryId($category);
        
        // Try to get pricing tier from database first
        $pricingTier = \App\Models\PricingTier::getByCategoryAndRateCode($catId, $rateCode);
        
        if ($pricingTier) {
            // Use database pricing tier
            return $pricingTier->calculateBill($cu);
        }
        
        // Fallback to unified water billing service
        return app(WaterBillingService::class)->calculate($cu, $category, $rateCode);
    }

    /**
     * Resolve category ID from category string (for pricing_tiers lookup).
     */
    private function resolveCategoryId($category)
    {
        if ($category === null || $category === '') {
            return null;
        }
        
        $raw = strtoupper(trim($category));
        if ($raw === '') {
            return null;
        }
        
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return match ($raw) {
            'RES', 'RESIDENTIAL' => 12,
            'GOVT', 'GOVT-LGU', 'GOVERNMENT' => 22,
            'INDUSTRIAL' => 32,
            'COM-A', 'COMMERCIAL A', 'COMMERCIAL_A' => 33,
            'COM-B', 'COMMERCIAL B', 'COMMERCIAL_B' => 34,
            'COM-C', 'COMMERCIAL C', 'COMMERCIAL_C' => 35,
            default => $this->resolveLegacyCategoryId($raw),
        };
    }

    private function resolveLegacyCategoryId(string $raw): ?int
    {
        if (strpos($raw, 'RES') !== false) return 12;
        if (strpos($raw, 'GOV') !== false) return 22;
        if (strpos($raw, 'IND') !== false) return 32;
        if (strpos($raw, 'COMA') !== false) return 33;
        if (strpos($raw, 'COMB') !== false) return 34;
        if (strpos($raw, 'COMC') !== false) return 35;
        if (strpos($raw, 'COMD') !== false) return 35;
        if (strpos($raw, 'COM') !== false) return 32;
        if (strpos($raw, 'WHOLESALE') !== false || strpos($raw, 'BULK') !== false) return 36;

        return null;
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
}
