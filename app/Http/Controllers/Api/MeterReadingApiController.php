<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

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
        $user = User::where('email', $username)->first();

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
            $latestBillMonth = MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->whereIn('status', ['Assigned', 'In Progress'])
                ->orderBy('bill_month', 'DESC')
                ->value('bill_month');
            
            // Get zones that have active routes in the latest bill_month
            if ($latestBillMonth) {
                $assignedZones = MeterReadingSchedule::query()
                    ->joinConsumerZone()
                    ->where('meter_reading_schedules.assigned_reader_id', $readerId)
                    ->where('meter_reading_schedules.bill_month', $latestBillMonth)
                    ->whereIn('meter_reading_schedules.status', ['Assigned', 'In Progress'])
                    ->distinct()
                    ->pluck('cz.zone_code')
                    ->filter()
                    ->values()
                    ->toArray();
            }
            
            // If no active routes, then get from completed routes
            if (!$latestBillMonth) {
                $latestBillMonth = MeterReadingSchedule::where('assigned_reader_id', $readerId)
                    ->where('status', 'Completed')
                    ->orderBy('bill_month', 'DESC')
                    ->value('bill_month');
                
                // Get zones from completed routes in the latest bill_month
                if ($latestBillMonth) {
                    $assignedZones = MeterReadingSchedule::query()
                        ->joinConsumerZone()
                        ->where('meter_reading_schedules.assigned_reader_id', $readerId)
                        ->where('meter_reading_schedules.bill_month', $latestBillMonth)
                        ->where('meter_reading_schedules.status', 'Completed')
                        ->distinct()
                        ->pluck('cz.zone_code')
                        ->filter()
                        ->values()
                        ->toArray();
                }
            }
        }

        $query = MeterReadingSchedule::with('consumerZone')
            ->where('assigned_reader_id', $readerId)
            ->whereIn('status', ['Assigned', 'In Progress', 'Completed']);

        // Filter by zone: use provided one, or zones with active assignments
        if ($zone) {
            $query->forZoneCode($zone);
        } elseif (!empty($assignedZones)) {
            $query->whereHas('consumerZone', function ($q) use ($assignedZones) {
                $q->whereIn('zone_code', $assignedZones);
            });
        }

        // Filter by bill_month: use provided one, or latest one, or none
        if ($billMonth) {
            $query->where('bill_month', Carbon::parse($billMonth)->format('Y-m-d'));
        } elseif ($latestBillMonth) {
            // Automatically filter by latest bill_month to exclude old completed routes
            $query->where('bill_month', $latestBillMonth);
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
        $downloadedReadings = DownloadedReading::where('reader_id', $readerId)
            ->where('status', 'completed')
                ->whereIn('schedule_id', $scheduleIds)
            ->get()
            ->keyBy('schedule_id');
        }

        // Get rate codes from consumer_zone table for all schedules
        $rateCodes = collect();
        if ($schedules->isNotEmpty()) {
            $consumerZoneIds = $schedules->pluck('consumer_zone_id')->filter()->unique()->values()->toArray();
            $rateCodes = DB::table('consumer_zone')
                ->whereIn('id', $consumerZoneIds)
                ->select('id', 'account_no', 'rate_code')
                ->get()
                ->keyBy('id');
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
     * Submit meter reading from mobile app
     */
    public function submitReading(Request $request)
    {
        // Log incoming request for debugging
        \Log::info('📥 Submit Reading Request Received', [
            'schedule_id' => $request->input('schedule_id'),
            'current_reading' => $request->input('current_reading'),
            'reader_id' => $request->input('reader_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $request->validate([
            'schedule_id' => 'required|exists:meter_reading_schedules,id',
            'current_reading' => 'required|integer|min:0',
            'reading_date' => 'nullable|date',
            'reader_notes' => 'nullable|string',
            'reader_id' => 'required|exists:users,id'
        ]);

        try {
            $schedule = MeterReadingSchedule::with('consumerZone')->find($request->schedule_id);
            \Log::info('📋 Schedule Found', [
                'schedule_id' => $schedule->id,
                'account_number' => $schedule->account_number,
                'previous_reading' => $schedule->previous_reading
            ]);

            // Verify this schedule belongs to the requesting reader (if assigned_reader_id is set)
            // Allow unassigned schedules or schedules with null assigned_reader_id to be updated by any reader
            if ($schedule->assigned_reader_id && $schedule->assigned_reader_id != $request->reader_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this schedule'
                ], 403);
            }

            // Calculate consumption
            $currentReading = (int) $request->current_reading;
            $previousReading = (int) ($schedule->previous_reading ?? 0);
            $consumption = max(0, $currentReading - $previousReading);

            // Get rate_code from consumer_zone
            $rateCode = null;
            $consumer = $schedule->consumerZone;
            if (!$consumer && $schedule->consumer_zone_id) {
                $consumer = \App\Models\ConsumerZoneOne::find($schedule->consumer_zone_id);
            }

            if ($consumer) {
                $rateCode = $consumer->rate_code;
            }

            // Calculate current_bill based on consumption, category, and rate_code
            // This matches the mobile app logic exactly
            $currentBill = $this->calculateWaterBill($consumption, $schedule->category, $rateCode);
            // Round to 2 decimal places to match mobile app precision
            $currentBill = round($currentBill, 2);

            // STEP 1: Update main system first (meter_reading_schedules)
            // This is the source of truth for the web interface and billing system
            $schedule->update(MeterReadingSchedule::filterTableAttributes([
                'current_reading' => $currentReading,
                'reading_date' => $request->reading_date ?? now(),
                'consumption' => $consumption,
                'current_bill' => $currentBill,
                'status' => 'Completed',
            ]));

            // STEP 2: After main system update succeeds, save to downloaded_readings
            // This table is for mobile app tracking and offline persistence
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

            DownloadedReading::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'reader_id' => $request->reader_id,
                ],
                $downloadedPayload
            );

            // STEP 3: Update the ConsumerLedger entry with actual reading data
            // Reuse the consumer we already looked up for rate_code

            if ($consumer) {
                // Find the existing ledger entry for this schedule
                $ledgerEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                    ->where('schedule_id', $schedule->id)
                    ->whereIn('trans', ['BILL', 'BILLING']) // Accept both formats
                    ->first();

                if ($ledgerEntry) {
                    // Get the previous balance (before this entry)
                    $previousEntry = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                        ->where('id', '<', $ledgerEntry->id)
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    $previousBalance = $previousEntry ? (float)$previousEntry->balance : (float)($consumer->balance ?? 0);
                    
                    // Calculate new values
                    $others = 20.00; // Water Maintenance Charge
                    $debit = $currentBill + $others;
                    $newBalance = $previousBalance + $debit;
                    
                    // Update the ledger entry with actual reading data
                    // Now that reading is completed, add the water maintenance charge
                    $ledgerEntry->update([
                        'trans' => 'BILLING', // Ensure it's 'BILLING' format
                        'reference' => $schedule->sedr_number ?? $ledgerEntry->reference, // Update reference if available
                        'reading' => $currentReading,
                        'volume' => $consumption,
                        'billamount' => $currentBill,
                        'others' => $others, // Water Maintenance Charge - only added after reading is completed
                        'debit' => $debit,
                        'balance' => $newBalance,
                        'txtime' => now()
                    ]);

                    // Recalculate balances for all subsequent entries
                    $subsequentEntries = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
                        ->where('id', '>', $ledgerEntry->id)
                        ->orderBy('id', 'asc')
                        ->get();
                    
                    $runningBalance = $newBalance;
                    foreach ($subsequentEntries as $entry) {
                        $entryDebit = (float)($entry->debit ?? 0);
                        $entryCredit = (float)($entry->credit ?? 0);
                        $runningBalance = $runningBalance + $entryDebit - $entryCredit;
                        $entry->update(['balance' => $runningBalance]);
                    }
                }
            }

            \Log::info('✅ Reading Submitted Successfully', [
                'schedule_id' => $schedule->id,
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
                    'status' => $schedule->status
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error Submitting Reading', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'schedule_id' => $request->schedule_id ?? 'N/A',
                'reader_id' => $request->reader_id ?? 'N/A'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error submitting reading: ' . $e->getMessage()
            ], 500);
        }
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
            'total_assigned' => MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->whereIn('status', ['Assigned', 'In Progress', 'Completed'])
                ->count(),
            'pending' => MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->where('status', 'Assigned')
                ->count(),
            'in_progress' => MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->where('status', 'In Progress')
                ->count(),
            'completed' => MeterReadingSchedule::where('assigned_reader_id', $readerId)
                ->where('status', 'Completed')
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
        
        // Fallback to hardcoded calculations if no database tier found
        switch ($catId) {
            case 12:
                return $this->computeResidential($cu);
            case 22:
                return $this->computeResidential($cu); // Government uses residential rates
            case 32:
                return $this->computeCommercialIndustrial($cu, $rateCode);
            case 33:
                return $this->computeCommercialA($cu, $rateCode);
            case 34:
                return $this->computeCommercialB($cu);
            case 35:
                return $this->computeCommercialD($cu);
            case 36:
                return $this->computeWholesale($cu);
            default:
                // Fallback: treat as residential
            return $this->computeResidential($cu);
        }
    }

    /**
     * Resolve category ID from category string (matches mobile app logic)
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
        
        // If numeric-like string, parse it
        if (is_numeric($raw)) {
            return (int) $raw;
        }
        
        // Map common text to IDs
        if (strpos($raw, 'RES') !== false) return 12;
        if (strpos($raw, 'GOV') !== false) return 22;
        if (strpos($raw, 'IND') !== false) return 32;
        if (strpos($raw, 'COMA') !== false) return 33;
        if (strpos($raw, 'COMB') !== false) return 34;
        if (strpos($raw, 'COMD') !== false) return 35;
        if (strpos($raw, 'COM') !== false) return 32; // general commercial/industrial
        if (strpos($raw, 'WHOLESALE') !== false || strpos($raw, 'BULK') !== false) return 36;
        
        return null;
    }

    /**
     * Rate Code C computation (for categories 32 and 33) - Tiered pricing
     * Minimum: ₱390.00 for 10 cubic meters
     * Note: Meter rental (₱20) is NOT included - shown separately
     */
    private function computeRateCodeC($cu)
    {
        $minCharge = 390.0;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            // 11-20 cubic meters: minCharge + (cu - 10) * 43.20
            return $minCharge + (($cu - 10) * 43.20);
        } else if ($cu <= 30) {
            // 21-30 cubic meters: minCharge + 10 * 43.20 + (cu - 20) * 47.50
            return $minCharge + (10 * 43.20) + (($cu - 20) * 47.50);
        } else if ($cu <= 40) {
            // 31-40 cubic meters: minCharge + 10 * 43.20 + 10 * 47.50 + (cu - 30) * 52.20
            return $minCharge + (10 * 43.20) + (10 * 47.50) + (($cu - 30) * 52.20);
        } else {
            // 41+ cubic meters: minCharge + 10 * 43.20 + 10 * 47.50 + 10 * 52.20 + (cu - 40) * 57.00
            return $minCharge + (10 * 43.20) + (10 * 47.50) + (10 * 52.20) + (($cu - 40) * 57.00);
        }
    }

    /**
     * Rate Code D computation (for categories 32 and 33) - Tiered pricing
     * Minimum: ₱243.75 for 10 cubic meters
     * Note: Meter rental (₱20) is NOT included - shown separately
     */
    private function computeRateCodeD($cu)
    {
        $minCharge = 243.75;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            // 11-20 cubic meters: minCharge + (cu - 10) * 27.00
            return $minCharge + (($cu - 10) * 27.00);
        } else if ($cu <= 30) {
            // 21-30 cubic meters: minCharge + 10 * 27.00 + (cu - 20) * 29.65
            return $minCharge + (10 * 27.00) + (($cu - 20) * 29.65);
        } else if ($cu <= 40) {
            // 31-40 cubic meters: minCharge + 10 * 27.00 + 10 * 29.65 + (cu - 30) * 32.60
            return $minCharge + (10 * 27.00) + (10 * 29.65) + (($cu - 30) * 32.60);
        } else {
            // 41+ cubic meters: minCharge + 10 * 27.00 + 10 * 29.65 + 10 * 32.60 + (cu - 40) * 35.60
            return $minCharge + (10 * 27.00) + (10 * 29.65) + (10 * 32.60) + (($cu - 40) * 35.60);
        }
    }

    /**
     * Industrial / Commercial general (Category 32) - now based on rate code
     */
    private function computeCommercialIndustrial($cu, $rateCode = null)
    {
        // Check rate code first
        if ($rateCode) {
            $rateCodeUpper = strtoupper(trim($rateCode));
            if ($rateCodeUpper === 'C') {
                return $this->computeRateCodeC($cu);
            } else if ($rateCodeUpper === 'D') {
                return $this->computeRateCodeD($cu);
            }
        }
        // Fallback to old calculation if no rate code or invalid rate code
        $minCharge = 390.0;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            return $minCharge + (($cu - 10) * 43.2);
        } else if ($cu <= 30) {
            return $minCharge + (10 * 43.2) + (($cu - 20) * 47.5);
        } else if ($cu <= 40) {
            return $minCharge + (10 * 43.2) + (10 * 47.5) + (($cu - 30) * 52.2);
        } else {
            return $minCharge + (10 * 43.2) + (10 * 47.5) + (10 * 52.2) + (($cu - 40) * 57.0);
        }
    }

    /**
     * Commercial A (Category 33) - now based on rate code
     */
    private function computeCommercialA($cu, $rateCode = null)
    {
        // Check rate code first
        if ($rateCode) {
            $rateCodeUpper = strtoupper(trim($rateCode));
            if ($rateCodeUpper === 'C') {
                return $this->computeRateCodeC($cu);
            } else if ($rateCodeUpper === 'D') {
                return $this->computeRateCodeD($cu);
            }
        }
        // Fallback to old calculation if no rate code or invalid rate code
        $minCharge = 341.25;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            return $minCharge + (($cu - 10) * 37.8);
        } else if ($cu <= 30) {
            return $minCharge + (10 * 37.8) + (($cu - 20) * 41.55);
        } else if ($cu <= 40) {
            return $minCharge + (10 * 37.8) + (10 * 41.55) + (($cu - 30) * 45.65);
        } else {
            return $minCharge + (10 * 37.8) + (10 * 41.55) + (10 * 45.65) + (($cu - 40) * 49.85);
        }
    }

    /**
     * Commercial B (Category 34)
     */
    private function computeCommercialB($cu)
    {
        $minCharge = 292.5;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            return $minCharge + (($cu - 10) * 32.4);
        } else if ($cu <= 30) {
            return $minCharge + (10 * 32.4) + (($cu - 20) * 35.6);
        } else if ($cu <= 40) {
            return $minCharge + (10 * 32.4) + (10 * 35.6) + (($cu - 30) * 39.15);
        } else {
            return $minCharge + (10 * 32.4) + (10 * 35.6) + (10 * 39.15) + (($cu - 40) * 42.75);
        }
    }

    /**
     * Commercial D (Category 35)
     */
    private function computeCommercialD($cu)
    {
        $minCharge = 243.75;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            return $minCharge + (($cu - 10) * 27.0);
        } else if ($cu <= 30) {
            return $minCharge + (10 * 27.0) + (($cu - 20) * 29.65);
        } else if ($cu <= 40) {
            return $minCharge + (10 * 27.0) + (10 * 29.65) + (($cu - 30) * 32.6);
        } else {
            return $minCharge + (10 * 27.0) + (10 * 29.65) + (10 * 32.6) + (($cu - 40) * 35.6);
        }
    }

    /**
     * Wholesale (Category 36)
     */
    private function computeWholesale($cu)
    {
        $minCharge = 585.0;
        if ($cu <= 10) {
            return $minCharge;
        } else if ($cu <= 20) {
            return $minCharge + (($cu - 10) * 64.8);
        } else if ($cu <= 30) {
            return $minCharge + (10 * 64.8) + (($cu - 20) * 71.25);
        } else if ($cu <= 40) {
            return $minCharge + (10 * 64.8) + (10 * 71.25) + (($cu - 30) * 78.3);
        } else {
            return $minCharge + (10 * 64.8) + (10 * 71.25) + (10 * 78.3) + (($cu - 40) * 85.5);
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
