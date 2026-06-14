<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZoneOne;
use App\Models\MeterReadingSchedule;
use App\Models\ConsumerLedger;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConsumerController extends Controller
{
    /**
     * Resolve consumer from ?account= / ?account_no= / ?account_number=.
     * When $fallbackToLatest is true and no account is in the query, returns the most recently created row (main consumer page default).
     */
     public static function resolveConsumerFromRequest(Request $request, bool $fallbackToLatest = false): ?ConsumerZoneOne
    {
        $accountNo = $request->query('account_no') ?: $request->query('account_number') ?: $request->query('account');
        $accountNo = $accountNo ? trim((string) $accountNo) : null;

        if ($accountNo !== null && $accountNo !== '') {
            $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
            if (!$consumer) {
                $normalized = str_replace('-', '', $accountNo);
                $consumer = ConsumerZoneOne::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalized])->first();
            }

            if ($consumer) {
                return $consumer;
            }

            // Stale or typo ?account= in URL: still show a consumer on main page when requested
            if ($fallbackToLatest) {
                return ConsumerZoneOne::orderBy('id', 'desc')->first();
            }

            return null;
        }

        if ($fallbackToLatest) {
            return ConsumerZoneOne::orderBy('id', 'desc')->first();
        }

        return null;
    }

    /**
     * Display a listing of the resource.
     */
     public function index(Request $request)
    {
        $consumer = self::resolveConsumerFromRequest($request, true);

        $latestBill = null;
        $meterReading = null;

        if ($consumer) {
            $consumer->setAttribute(
                'latest_disconnected_at',
                optional($consumer->latestDisconnectedAtForDisplay())->toIso8601String()
            );
            $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
            $meterReading = $this->getLatestMeterReading($consumer->account_no);
        }

        return view('Files.main-consumer', compact('consumer', 'latestBill', 'meterReading'));
    }

    /**
     * Consumer tab views: resolve consumer from request so header shows on all tabs.
     */
    public function ledger(Request $request)
    {
        $consumer = $this->resolveConsumerFromRequest($request);
        return view('consumer.ledger', compact('consumer'));
    }

    public function lroLedger(Request $request)
    {
        $consumer = $this->resolveConsumerFromRequest($request);
        return view('consumer.lro-ledger', compact('consumer'));
    }

    public function service(Request $request)
    {
        $consumer = $this->resolveConsumerFromRequest($request);
        return view('consumer.service', compact('consumer'));
    }

    public function meter(Request $request)
    {
        $consumer = $this->resolveConsumerFromRequest($request);
        return view('consumer.meter', compact('consumer'));
    }

    public function location(Request $request)
    {
        $consumer = $this->resolveConsumerFromRequest($request);
        return view('consumer.location', compact('consumer'));
    }

    public function consumption(Request $request)
    {
        $consumer = $this->resolveConsumerFromRequest($request);
        return view('consumer.consumption', compact('consumer'));
    }

    /**
     * Get consumer suggestions for autocomplete
     */
    public function getSuggestions(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'], // Query string, minimum 2 characters
        ]);
        
        $query = strtoupper(trim($request->input('q')));
        $normalizedQuery = str_replace('-', '', $query);
        
        // Match only from the START of each field (first 2+ chars), not in the middle
        $consumers = ConsumerZoneOne::where(function($q) use ($query, $normalizedQuery) {
                $q->whereRaw("UPPER(TRIM(account_no)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("REPLACE(UPPER(TRIM(account_no)), '-', '') LIKE ?", [$normalizedQuery . '%'])
                  ->orWhereRaw("UPPER(TRIM(account_name)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("UPPER(TRIM(meter_number)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("UPPER(TRIM(cons_ctrl)) LIKE ?", [$query . '%']);
            })
            ->select('id', 'account_no', 'account_name', 'meter_number', 'cons_ctrl', 'zone_code')
            ->distinct()
            ->orderBy('account_no', 'asc')
            ->limit(20) // Limit to 20 results
            ->get()
            ->map(function($consumer) {
                return [
                    'id' => $consumer->id,
                    'account_no' => $consumer->account_no,
                    'account_name' => $consumer->account_name,
                    'meter_number' => $consumer->meter_number ?? '',
                    'cons_ctrl' => $consumer->cons_ctrl ?? '',
                    'zone_code' => $consumer->zone_code ?? '',
                    'display' => $consumer->account_no . ' - ' . ($consumer->account_name ?? ''),
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $consumers,
        ]);
    }

    /**
     * Search for consumers (improved version)
     */
    public function search(Request $request)
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2'],
        ]);

        $searchTerm = trim($request->input('search'));
        $query = strtoupper($searchTerm);
        $normalizedQuery = str_replace('-', '', $query);

        // Use proper where closure to handle multiple OR conditions correctly
        $consumer = ConsumerZoneOne::where(function($q) use ($query, $normalizedQuery, $searchTerm) {
                $q->whereRaw("UPPER(TRIM(account_no)) LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("REPLACE(UPPER(TRIM(account_no)), '-', '') LIKE ?", ['%' . $normalizedQuery . '%'])
                  ->orWhereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("UPPER(TRIM(meter_number)) LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("UPPER(TRIM(cons_ctrl)) LIKE ?", ['%' . $query . '%']);
            })
            ->orderBy('account_no', 'asc')
            ->first();

        if ($consumer) {
              $consumer->setAttribute(
                'latest_disconnected_at',
                optional($consumer->latestDisconnectedAtForDisplay())->toIso8601String()
            );
            $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
            $meterReading = $this->getLatestMeterReading($consumer->account_no);

            return response()->json([
                'success' => true,
                'consumer' => $consumer,
                'latest_bill' => $latestBill,
                'meter_reading' => $meterReading,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No consumer found matching your search'
            ], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'install_date' => 'nullable|date',
            'transaction_date' => 'nullable|date',
            'account_no' => 'required|string|unique:consumer_zone,account_no',
            'account_name' => 'required|string',
            'gender' => 'required|string|in:Male,Female,Others',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_code' => 'required|string',
            'category_code' => 'nullable|string',
            'meter_number' => 'nullable|string',
            'meter_brand' => 'nullable|string',
            'rate_code' => 'nullable|string',
            'status_code' => 'nullable|string',
            'sequence' => 'nullable|integer',
            'cons_ctrl' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            ConsumerZoneOne::syncInstallActivationFields($data);

            $consumer = ConsumerZoneOne::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Consumer created successfully!',
                'consumer' => $consumer
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create consumer: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(ConsumerZoneOne $consumer)
    {
         $consumer->setAttribute(
            'latest_disconnected_at',
            optional($consumer->latestDisconnectedAtForDisplay())->toIso8601String()
        );
        $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
        $meterReading = $this->getLatestMeterReading($consumer->account_no);

        return view('Files.main-consumer', compact('consumer', 'latestBill', 'meterReading'));
    }

   /**
     * Get latest meter reading for main-consumer card.
     * Current Reading = downloaded_readings.current_reading (latest row by account).
     * Previous Reading = consumer_ledgers.reading from the last BILLING transaction for this consumer.
     *
     * Also exposes base_reading info (from consumer_zone) which is used as the
     * starting meter value for NEW consumers with no historical readings.
     */
    private function getLatestMeterReading($accountNumber)
    {
        if (!$accountNumber) {
            return null;
        }

        $accountNumber = trim((string) $accountNumber);
        $normalizedAccount = str_replace('-', '', $accountNumber);
        $normalizedAccount = preg_replace('/\s+/', '', $normalizedAccount);
        $upperAccount = strtoupper($accountNumber);

        // Current Reading: from downloaded_readings.current_reading (latest by account)
        $dr = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->on('cz.id', '=', 'dr.consumer_zone_id')
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->where(function ($query) use ($accountNumber, $normalizedAccount, $upperAccount) {
                $query->where('cz.account_no', $accountNumber)
                    ->orWhere('cz.account_no', $normalizedAccount)
                    ->orWhereRaw("REPLACE(TRIM(cz.account_no), '-', '') = ?", [$normalizedAccount])
                    ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [$upperAccount])
                    ->orWhereRaw("REPLACE(REPLACE(TRIM(cz.account_no), '-', ''), ' ', '') = ?", [$normalizedAccount]);
            })
            ->orderByDesc('dr.reading_date')
            ->orderByDesc('dr.id')
            ->first(['dr.schedule_id', 'dr.current_reading', 'dr.reading_date']);

        $scheduleId = $dr ? $dr->schedule_id : null;
        $currentReading = $dr && $dr->current_reading !== null ? (int) $dr->current_reading : null;
        $currentDate = $dr ? $dr->reading_date : null;

        // Previous Reading: from last BILLING in consumer_ledgers (reading column)
        $ledgerPrevious = $this->getPreviousReadingFromConsumerLedger($accountNumber, $normalizedAccount);
        $previousReading = $ledgerPrevious !== null ? (int) $ledgerPrevious['reading'] : 0;
        $previousDate = $ledgerPrevious !== null ? ($ledgerPrevious['date'] ?? null) : null;

        // Base Reading (consumer master): starting value for new consumers without history
        $baseReading = null;
        $baseReadingDate = null;
        if (Schema::hasColumn('consumer_zone', 'base_reading')) {
            $base = ConsumerZoneOne::where('account_no', $accountNumber)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                ->first(['base_reading', 'base_reading_date']);
            if ($base) {
                $baseReading = $base->base_reading !== null ? (int) $base->base_reading : null;
                $baseReadingDate = $base->base_reading_date
                    ? \Carbon\Carbon::parse($base->base_reading_date)->format('Y-m-d')
                    : null;
            }
        }

        // Always return the object so the consumer profile can render the
        // Base Reading section even when no readings/ledger exist (new consumer).
        return (object) [
            'schedule_id' => $scheduleId,
            'current_reading' => $currentReading,
            'current_reading_date' => $currentDate,
            'previous_reading' => $previousReading,
            'previous_reading_date' => $previousDate,
            'base_reading' => $baseReading,
            'base_reading_date' => $baseReadingDate,
        ];
    }

    /**
     * Previous reading from consumer_ledgers: last BILLING row for this consumer (reading + date).
     * Returns ['reading' => int, 'date' => Y-m-d or null] or null.
     */
    private function getPreviousReadingFromConsumerLedger($accountNumber, $normalizedAccount)
    {
        $consumer = ConsumerZoneOne::where('account_no', $accountNumber)
            ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedAccount])
            ->first();
        if (!$consumer) {
            return null;
        }

        $ledger = DB::table('consumer_ledgers')
            ->where('consumer_zone_id', $consumer->id)
            ->whereIn('trans', ['BILLING', 'BILL'])
            ->orderByDesc('id')
            ->first(['reading', 'date']);

        if (!$ledger) {
            return null;
        }
        if ($ledger->reading === null) {
            return null;
        }

        return [
            'reading' => (int) $ledger->reading,
            'date' => $ledger->date ? \Carbon\Carbon::parse($ledger->date)->format('Y-m-d') : null,
        ];
    }

    /**
     * Get latest bill from downloaded_readings table joined with meter_reading_schedules.
     * Uses the same logic as billing_payment.blade.php lookupBillingRecord method.
     */
    private function getLatestBillFromDownloadedReadings($accountNumber)
    {
        if (!$accountNumber) {
            return null;
        }

        $normalizedAccount = str_replace('-', '', $accountNumber);

        // Query downloaded_readings table with LEFT JOIN to meter_reading_schedules
        // Same logic as lookupBillingRecord in MeterReadingController
        $reading = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', function ($join) {
                $join->on('cz.id', '=', 'dr.consumer_zone_id')
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select(
                'dr.id as downloaded_id',
                'dr.schedule_id',
                'cz.account_no as account_number',
                'cz.account_name',
                'dr.consumption',
                'dr.current_bill as downloaded_current_bill',
                'dr.reading_date',
                'dr.status',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.current_bill as schedule_current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'cz.category_code as category'
            )
            ->where(function ($query) use ($accountNumber, $normalizedAccount) {
                $query->where('cz.account_no', $accountNumber)
                      ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                      ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNumber))]);
            })
            ->orderByDesc('dr.reading_date')
            ->orderByDesc('dr.created_at')
            ->first();

        // If no result with schedule join, try by consumer_zone_id on downloaded_readings
        if (!$reading) {
            $consumer = ConsumerZoneOne::where('account_no', $accountNumber)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                ->first();

            if ($consumer) {
                $directReading = DB::table('downloaded_readings as dr')
                    ->leftJoin('consumer_zone as cz', 'dr.consumer_zone_id', '=', 'cz.id')
                    ->select(
                        'dr.id as downloaded_id',
                        'dr.schedule_id',
                        'cz.account_no as account_number',
                        'cz.account_name',
                        'dr.consumption',
                        'dr.current_bill as downloaded_current_bill',
                        'dr.reading_date',
                        'dr.status'
                    )
                    ->where('dr.consumer_zone_id', $consumer->id)
                    ->orderByDesc('dr.reading_date')
                    ->orderByDesc('dr.created_at')
                    ->first();
            } else {
                $directReading = null;
            }

            if ($directReading && $directReading->schedule_id) {
                // Get schedule data separately
                $schedule = DB::table('meter_reading_schedules')
                    ->where('id', $directReading->schedule_id)
                    ->first();
                
                if ($schedule) {
                    $categoryCode = null;
                    if (!empty($schedule->consumer_zone_id)) {
                        $categoryCode = DB::table('consumer_zone')
                            ->where('id', $schedule->consumer_zone_id)
                            ->value('category_code');
                    }
                    // Merge schedule data into reading object
                    $reading = (object) array_merge((array) $directReading, [
                        'bill_month' => $schedule->bill_month ?? null,
                        'bill_date' => $schedule->bill_date ?? null,
                        'due_date' => $schedule->due_date ?? null,
                        'schedule_current_bill' => $schedule->current_bill ?? null,
                        'arrears' => $schedule->arrears ?? null,
                        'total_amount' => $schedule->total_amount ?? null,
                        'category' => $categoryCode,
                    ]);
                } else {
                    $reading = $directReading;
                }
            } else {
                $reading = $directReading;
            }
        }

        if (!$reading) {
            return null;
        }

        // Calculate current bill if not available (same logic as lookupBillingRecord)
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
        
        // If current_bill is 0 or null, calculate it from consumption
        $currentBill = $storedCurrentBill;
        if ($currentBill <= 0 && $consumption > 0) {
            // Calculate bill using the same method as billing payment
            $currentBill = $this->calculateWaterBill($consumption, $reading->category ?? null);
        }

        // Meter maintenance charge (Water Maintenance Charge) - constant value as per billing_payment logic
        $meterMaintenanceCharge = 20.00;
        
        // Get latest balance from consumer_ledgers (source of truth for arrears/balance)
        $consumer = ConsumerZoneOne::where('account_no', $accountNumber)->first();
        $latestBalance = 0.0;
        if ($consumer) {
            $latestBalanceEntry = ConsumerLedger::where('consumer_zone_id', $consumer->id)
                ->whereNotNull('balance')
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $latestBalance = $latestBalanceEntry ? (float)($latestBalanceEntry->balance ?? 0) : (float)($consumer->balance ?? 0);
        }

        // Prepare bill data in the format expected by the view
        // Note: materials, septage_fee, others are not stored in meter_reading_schedules table
        // They default to 0.00 unless stored elsewhere (e.g., in downloaded_readings or another table)
        return (object) [
            'account_no' => trim((string) $accountNumber),
            'bill_month' => $reading->bill_month ?? null,
            'bill_date' => $reading->bill_date ?? null,
            'current_bill' => round($currentBill, 2),
            'meter_rental' => $meterMaintenanceCharge, // Fixed at 20.00 as per billing_payment logic
            'arrears' => round($latestBalance, 2), // Use latest ledger balance as arrears/balance
            'materials' => 0.0, // Default to 0.00 - not stored in meter_reading_schedules
            'septage_fee' => 0.0, // Default to 0.00 - not stored in meter_reading_schedules
            'others' => 0.0, // Default to 0.00 - not stored in meter_reading_schedules
            'total_amount' => $reading->total_amount !== null ? (float) $reading->total_amount : 0.0,
        ];
    }

    /**
     * Calculate water bill based on consumption (cubic meters) and category
     * Simplified version - matches the calculation used in billing payment
     */
    private function calculateWaterBill($consumption, $category = null)
    {
        $cu = (int) $consumption;
        
        // Default to residential if category is not specified or invalid
        $isCommercial = $category && (stripos($category, 'commercial') !== false || stripos($category, 'industrial') !== false);
        
        if ($isCommercial) {
            return $this->computeCommercial($cu);
        } else {
            return $this->computeResidential($cu);
        }
    }

    /**
     * Calculate commercial water bill with tiered pricing (excluding meter rental)
     */
    private function computeCommercial($cu)
    {
        $minCharge = 243.75;
        
        if ($cu <= 10) {
            return $minCharge;
        } elseif ($cu <= 20) {
            return $minCharge + (($cu - 10) * 24.38);
        } elseif ($cu <= 30) {
            return $minCharge + (10 * 24.38) + (($cu - 20) * 48.75);
        } else {
            return $minCharge + (10 * 24.38) + (10 * 48.75) + (($cu - 30) * 73.13);
        }
    }

    /**
     * Calculate residential water bill with tiered pricing (excluding meter rental)
     */
    private function computeResidential($cu)
    {
        $minCharge = 162.50;
        
        if ($cu <= 10) {
            return $minCharge;
        } elseif ($cu <= 20) {
            return $minCharge + (($cu - 10) * 16.25);
        } elseif ($cu <= 30) {
            return $minCharge + (10 * 16.25) + (($cu - 20) * 32.50);
        } else {
            return $minCharge + (10 * 16.25) + (10 * 32.50) + (($cu - 30) * 48.75);
        }
    }

    
    
    /**
     * Update the specified resource in storage.
     */
      public function update(Request $request, ConsumerZoneOne $consumer)
    {
        $billDiscChanged = $this->consumerBillDiscChangedFromRequest($request, $consumer);

        $rules = [
            'install_date' => 'nullable|date',
            'transaction_date' => 'nullable|date',
            'account_no' => 'required|string|unique:consumer_zone,account_no,' . $consumer->id,
            'account_name' => 'required|string',
            'gender' => 'required|string|in:Male,Female,Others',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_code' => 'required|string',
            'category_code' => 'nullable|string',
            'meter_number' => 'nullable|string',
            'meter_brand' => 'nullable|string',
            'rate_code' => 'nullable|string',
            'status_code' => 'nullable|string',
            'sequence' => 'nullable|integer',
            'cons_ctrl' => 'nullable|string',
            'bill_disc_percent' => 'nullable|string|in:SC DISCOUNT',
            'bill_disc_amount' => 'nullable|numeric|min:0',
            'osca_id_no' => 'nullable|string|max:100',
            'remark' => 'nullable|string|max:2000',
            'bill_disc_updated_at' => 'nullable|date',
        ];

        if ($billDiscChanged) {
            $rules['bill_disc_updated_at'] = 'required|date';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->except(['_token', '_method']);
            ConsumerZoneOne::syncInstallActivationFields($data, $consumer);
            $oldAccountNo = trim((string) ($consumer->account_no ?? ''));
            $oldAccountName = trim((string) ($consumer->account_name ?? ''));
            $newAccountNo = trim((string) ($data['account_no'] ?? $oldAccountNo));
            $newAccountName = trim((string) ($data['account_name'] ?? $oldAccountName));
            
             $fresh = $consumer->fresh();
            if ($fresh) {
                $fresh->setAttribute(
                    'latest_disconnected_at',
                    optional($fresh->latestDisconnectedAtForDisplay())->toIso8601String()
                );
            }

            DB::transaction(function () use ($consumer, $data, $oldAccountNo, $newAccountNo, $oldAccountName, $newAccountName) {
                $consumer->update($data);
                $this->syncConsumerIdentityChanges(
                    (int) $consumer->id,
                    $oldAccountNo,
                    $newAccountNo,
                    $oldAccountName,
                    $newAccountName
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Consumer updated successfully!',
                 'consumer' => $fresh
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update consumer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Propagate changed account identity fields to related records so legacy lists/reports stay in sync.
     */
    private function syncConsumerIdentityChanges(
        int $consumerId,
        string $oldAccountNo,
        string $newAccountNo,
        string $oldAccountName,
        string $newAccountName
    ): void {
        $accountNoChanged = $newAccountNo !== '' && $oldAccountNo !== $newAccountNo;
        $accountNameChanged = $oldAccountName !== $newAccountName;

        $normalizedOld = str_replace('-', '', $oldAccountNo);
        $normalizedNew = str_replace('-', '', $newAccountNo);

        // billing_adjustments links via consumer_zone_id; account_no lives on consumer_zone.
        if ($newAccountNo !== '' && Schema::hasTable('billing_adjustments') && Schema::hasColumn('billing_adjustments', 'account_no')) {
            DB::table('billing_adjustments')
                ->where('consumer_zone_id', $consumerId)
                ->update(['account_no' => $newAccountNo]);
        }

        if ($newAccountNo !== '' && Schema::hasTable('consumer_ledgers') && Schema::hasColumn('consumer_ledgers', 'account_no')) {
            DB::table('consumer_ledgers')
                ->where('consumer_zone_id', $consumerId)
                ->update(['account_no' => $newAccountNo]);
        }

        // downloaded_readings and meter_reading_schedules store consumer_zone_id only; account fields live on consumer_zone.

        if (Schema::hasTable('collection')) {
            if ($accountNoChanged && Schema::hasColumn('collection', 'account_no')) {
                DB::table('collection')
                    ->where(function ($q) use ($oldAccountNo, $normalizedOld) {
                        $q->where('account_no', $oldAccountNo)
                          ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedOld]);
                    })
                    ->update(['account_no' => $newAccountNo]);
            }
            if ($accountNameChanged && Schema::hasColumn('collection', 'account_name')) {
                DB::table('collection')
                    ->where(function ($q) use ($newAccountNo, $oldAccountNo, $normalizedOld, $normalizedNew) {
                        $q->where('account_no', $newAccountNo)
                          ->orWhere('account_no', $oldAccountNo)
                          ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedOld])
                          ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedNew]);
                    })
                    ->update(['account_name' => $newAccountName]);
            }
        }

        if (Schema::hasTable('disconnection_orders')) {
            $applyDisconnectionConsumerScope = function ($query) use ($consumerId) {
                if (Schema::hasColumn('disconnection_orders', 'consumer_zone_id')) {
                    $query->where('consumer_zone_id', $consumerId);
                } elseif (Schema::hasColumn('disconnection_orders', 'consumer_id')) {
                    $query->where('consumer_id', $consumerId);
                }
            };
            if ($accountNoChanged && Schema::hasColumn('disconnection_orders', 'account_no')) {
                DB::table('disconnection_orders')
                    ->where($applyDisconnectionConsumerScope)
                    ->update(['account_no' => $newAccountNo]);
            }
            if ($accountNameChanged && Schema::hasColumn('disconnection_orders', 'account_name')) {
                DB::table('disconnection_orders')
                    ->where($applyDisconnectionConsumerScope)
                    ->update(['account_name' => $newAccountName]);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConsumerZoneOne $consumer)
    {
        try {
            $consumer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Consumer deleted successfully!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete consumer: ' . $e->getMessage()
            ], 500);
        }
    }

 /**
    * True when bill discount fields in request differ from stored values.
     */
    private function consumerBillDiscChangedFromRequest(Request $request, ConsumerZoneOne $consumer): bool
    {
        $normPercent = function ($v) {
            if ($v === '' || $v === null) {
                return null;
            }
            $s = trim((string) $v);
            if ($s === '') {
                return null;
            }
            // Backward compatibility: treat numeric 5/5.00 as SC DISCOUNT.
            if (is_numeric($s) && abs(((float) $s) - 5.0) < 0.001) {
                return 'SC DISCOUNT';
            }
            if (strtoupper($s) === 'SC DISCOUNT') {
                return 'SC DISCOUNT';
            }
            return $s;
        };

        $normAmount = function ($v) {
            if ($v === '' || $v === null) {
                return null;
            }
            if (! is_numeric($v)) {
                return null;
            }

            return round((float) $v, 4);
        };

        $percentChanged = $normPercent($request->input('bill_disc_percent')) !== $normPercent($consumer->bill_disc_percent);
        $amountChanged = $request->has('bill_disc_amount')
            && $normAmount($request->input('bill_disc_amount')) !== $normAmount($consumer->bill_disc_amount);

        return $percentChanged || $amountChanged;
    }

    /**
     * Verify PIN for editing on main-consumer page (Edit, Delete, Save Previous Reading).
     */
    public function verifyEditPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        if (Setting::verifyConsumerEditPin($request->pin)) {
            return response()->json(['success' => true]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid PIN.',
        ], 422);
    }
}

