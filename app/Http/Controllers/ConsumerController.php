<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZoneOne;
use App\Models\MeterReadingSchedule;
use App\Models\ConsumerLedger;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ConsumerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $accountNo = $request->query('account_no') ?: $request->query('account_number') ?: $request->query('account');
        $accountNo = $accountNo ? trim((string) $accountNo) : null;

        if ($accountNo !== null && $accountNo !== '') {
            $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
            if (!$consumer) {
                $normalized = str_replace('-', '', $accountNo);
                $consumer = ConsumerZoneOne::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalized])->first();
            }
        } else {
            $consumer = ConsumerZoneOne::orderBy('id', 'desc')->first();
        }

        $latestBill = null;
        $meterReading = null;

        if ($consumer) {
            $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
            $meterReading = $this->getLatestMeterReading($consumer->account_no);
        }

        return view('Files.main-consumer', compact('consumer', 'latestBill', 'meterReading'));
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
            'account_no' => 'required|string|unique:consumer_zone,account_no',
            'account_name' => 'required|string',
            'address1' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_code' => 'required|string',
            'category_code' => 'nullable|string',
            'meter_number' => 'nullable|string',
            'meter_brand' => 'nullable|string',
            'rate_code' => 'nullable|string',
            'status_code' => 'nullable|string',
            'sequence' => 'nullable|integer',
            'consumer_deposit' => 'nullable|numeric',
            'installation_fee' => 'nullable|numeric',
            'installation_balance' => 'nullable|numeric',
            'balance' => 'nullable|numeric',
            'cons_ctrl' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            
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
        $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
        $meterReading = $this->getLatestMeterReading($consumer->account_no);

        return view('Files.main-consumer', compact('consumer', 'latestBill', 'meterReading'));
    }

    /**
     * Get latest meter reading for main-consumer card.
     * Current Reading = downloaded_readings.current_reading (latest row by account).
     * Previous Reading = consumer_ledgers.reading from the last BILLING transaction for this consumer.
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
            ->where(function ($query) use ($accountNumber, $normalizedAccount, $upperAccount) {
                $query->where('dr.account_number', $accountNumber)
                    ->orWhere('dr.account_number', $normalizedAccount)
                    ->orWhereRaw("REPLACE(TRIM(dr.account_number), '-', '') = ?", [$normalizedAccount])
                    ->orWhereRaw("UPPER(TRIM(dr.account_number)) = ?", [$upperAccount])
                    ->orWhereRaw("REPLACE(REPLACE(TRIM(dr.account_number), '-', ''), ' ', '') = ?", [$normalizedAccount]);
            })
            ->orderByDesc('dr.reading_date')
            ->orderByDesc('dr.completed_at')
            ->orderByDesc('dr.id')
            ->first(['dr.schedule_id', 'dr.current_reading', 'dr.reading_date']);

        $scheduleId = $dr ? $dr->schedule_id : null;
        $currentReading = $dr && $dr->current_reading !== null ? (int) $dr->current_reading : null;
        $currentDate = $dr ? $dr->reading_date : null;

        // Previous Reading: from last BILLING in consumer_ledgers (reading column)
        $ledgerPrevious = $this->getPreviousReadingFromConsumerLedger($accountNumber, $normalizedAccount);
        $previousReading = $ledgerPrevious !== null ? (int) $ledgerPrevious['reading'] : 0;
        $previousDate = $ledgerPrevious !== null ? ($ledgerPrevious['date'] ?? null) : null;

        // If no downloaded_reading and no ledger, return null
        if ($dr === null && $ledgerPrevious === null) {
            return null;
        }

        return (object) [
            'schedule_id' => $scheduleId,
            'current_reading' => $currentReading,
            'current_reading_date' => $currentDate,
            'previous_reading' => $previousReading,
            'previous_reading_date' => $previousDate,
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
            ->select(
                'dr.id as downloaded_id',
                'dr.schedule_id',
                'dr.account_number',
                'dr.account_name',
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
                'mrs.category'
            )
            ->where(function ($query) use ($accountNumber, $normalizedAccount) {
                $query->where('dr.account_number', $accountNumber)
                      ->orWhereRaw("REPLACE(dr.account_number, '-', '') = ?", [$normalizedAccount])
                      ->orWhereRaw("UPPER(TRIM(dr.account_number)) = ?", [strtoupper(trim($accountNumber))])
                      ->orWhere('mrs.account_number', $accountNumber)
                      ->orWhereRaw("REPLACE(mrs.account_number, '-', '') = ?", [$normalizedAccount])
                      ->orWhereRaw("UPPER(TRIM(mrs.account_number)) = ?", [strtoupper(trim($accountNumber))]);
            })
            ->orderByDesc('dr.reading_date')
            ->orderByDesc('dr.completed_at')
            ->orderByDesc('dr.created_at')
            ->first();

        // If no result with schedule join, try querying downloaded_readings directly
        if (!$reading) {
            $directReading = DB::table('downloaded_readings as dr')
                ->select(
                    'dr.id as downloaded_id',
                    'dr.schedule_id',
                    'dr.account_number',
                    'dr.account_name',
                    'dr.consumption',
                    'dr.current_bill as downloaded_current_bill',
                    'dr.reading_date',
                    'dr.status'
                )
                ->where(function ($query) use ($accountNumber, $normalizedAccount) {
                    $query->where('dr.account_number', $accountNumber)
                          ->orWhereRaw("REPLACE(dr.account_number, '-', '') = ?", [$normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(dr.account_number)) = ?", [strtoupper(trim($accountNumber))]);
                })
                ->orderByDesc('dr.reading_date')
                ->orderByDesc('dr.completed_at')
                ->orderByDesc('dr.created_at')
                ->first();

            if ($directReading && $directReading->schedule_id) {
                // Get schedule data separately
                $schedule = DB::table('meter_reading_schedules')
                    ->where('id', $directReading->schedule_id)
                    ->first();
                
                if ($schedule) {
                    // Merge schedule data into reading object
                    // Note: meter_rental, materials, septage_fee, others don't exist in meter_reading_schedules table
                    // They are stored elsewhere or use default values
                    // Keep downloaded_current_bill if exists, otherwise use schedule's current_bill
                    $reading = (object) array_merge((array) $directReading, [
                        'bill_month' => $schedule->bill_month ?? null,
                        'bill_date' => $schedule->bill_date ?? null,
                        'due_date' => $schedule->due_date ?? null,
                        'schedule_current_bill' => $schedule->current_bill ?? null,
                        'arrears' => $schedule->arrears ?? null,
                        'total_amount' => $schedule->total_amount ?? null,
                        'category' => $schedule->category ?? null,
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
        $validator = Validator::make($request->all(), [
            'install_date' => 'nullable|date',
            'account_no' => 'required|string|unique:consumer_zone,account_no,' . $consumer->id,
            'account_name' => 'required|string',
            'address1' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_code' => 'required|string',
            'category_code' => 'nullable|string',
            'meter_number' => 'nullable|string',
            'meter_brand' => 'nullable|string',
            'rate_code' => 'nullable|string',
            'status_code' => 'nullable|string',
            'sequence' => 'nullable|integer',
            'consumer_deposit' => 'nullable|numeric',
            'installation_fee' => 'nullable|numeric',
            'installation_balance' => 'nullable|numeric',
            'balance' => 'nullable|numeric',
            'cons_ctrl' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            $consumer->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Consumer updated successfully!',
                'consumer' => $consumer->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update consumer: ' . $e->getMessage()
            ], 500);
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

