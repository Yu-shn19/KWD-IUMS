<?php

namespace App\Http\Controllers;

use App\Imports\BaseReadingImport;
use App\Imports\ConsumerZoneImport;
use App\Models\ConsumerZone;
use App\Models\MeterReadingSchedule;
use App\Models\ConsumerLedger;
use App\Models\Setting;
use App\Services\WaterBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class ConsumerController extends Controller
{
    /**
     * Resolve consumer from ?account= / ?account_no= / ?account_number=.
     * When $fallbackToLatest is true and no account is in the query, returns the most recently created row (main consumer page default).
     */
     public static function resolveConsumerFromRequest(Request $request, bool $fallbackToLatest = false): ?ConsumerZone
    {
        $accountNoColumn = mr_col('account_no');
        $consumerIdColumn = (new ConsumerZone)->getKeyName();

        $accountNo = $request->query('account_no') ?: $request->query('account_number') ?: $request->query('account');
        $accountNo = $accountNo ? trim((string) $accountNo) : null;

        if ($accountNo !== null && $accountNo !== '') {
            $consumer = ConsumerZone::query()->where($accountNoColumn, $accountNo)->first();
            if (!$consumer) {
                $normalized = str_replace('-', '', $accountNo);
                $consumer = ConsumerZone::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalized])->first();
            }

            if ($consumer) {
                return $consumer;
            }

            // Stale or typo ?account= in URL: still show a consumer on main page when requested
            if ($fallbackToLatest) {
                return ConsumerZone::query()->orderBy($consumerIdColumn, 'desc')->first();
            }

            return null;
        }

        if ($fallbackToLatest) {
            return ConsumerZone::query()->orderBy($consumerIdColumn, 'desc')->first();
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
        $zones = ConsumerZone::distinctZoneCodes();

        if ($consumer) {
            $consumer->setAttribute(
                'latest_disconnected_at',
                optional($consumer->latestDisconnectedAtForDisplay())->toIso8601String()
            );
            $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
            $meterReading = $this->getLatestMeterReading($consumer->account_no);
        }

        return view('Files.main-consumer', compact('consumer', 'latestBill', 'meterReading', 'zones'));
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
        $accountNoColumn = mr_col('account_no');

        // Match only from the START of each field (first 2+ chars), not in the middle
        $consumers = ConsumerZone::where(function($q) use ($query, $normalizedQuery) {
                $q->whereRaw("UPPER(TRIM(account_no)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("REPLACE(UPPER(TRIM(account_no)), '-', '') LIKE ?", [$normalizedQuery . '%'])
                  ->orWhereRaw("UPPER(TRIM(account_name)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("UPPER(TRIM(meter_number)) LIKE ?", [$query . '%'])
                  ->orWhereRaw("UPPER(TRIM(cons_ctrl)) LIKE ?", [$query . '%']);
            })
            ->select('id', 'account_no', 'account_name', 'meter_number', 'cons_ctrl', 'zone_code')
            ->distinct()
            ->orderBy($accountNoColumn, 'asc')
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
        $accountNoColumn = mr_col('account_no');

        // Use proper where closure to handle multiple OR conditions correctly
        $consumer = ConsumerZone::where(function($q) use ($query, $normalizedQuery, $searchTerm) {
                $q->whereRaw("UPPER(TRIM(account_no)) LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("REPLACE(UPPER(TRIM(account_no)), '-', '') LIKE ?", ['%' . $normalizedQuery . '%'])
                  ->orWhereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("UPPER(TRIM(meter_number)) LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("UPPER(TRIM(cons_ctrl)) LIKE ?", ['%' . $query . '%']);
            })
            ->orderBy($accountNoColumn, 'asc')
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
     * Check whether an account number is available (not already used).
     */
    public function checkAccountNo(Request $request)
    {
        $request->validate([
            'account_no' => 'required|string|max:100',
            'exclude_id' => 'nullable|integer',
        ]);

        $accountNo = $this->normalizeAccountNo($request->input('account_no'));
        $excludeId = $request->input('exclude_id') ? (int) $request->input('exclude_id') : null;

        if ($accountNo === '') {
            return response()->json([
                'available' => false,
                'message' => 'Account number is required.',
            ]);
        }

        if ($this->accountNoIsTaken($accountNo, $excludeId)) {
            return response()->json([
                'available' => false,
                'message' => 'This account number is already assigned to another consumer.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'Account number is available.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->normalizeConsumerRequestInput($request);

        $validator = Validator::make($request->all(), [
            'install_date' => 'nullable|date',
            'transaction_date' => 'nullable|date',
            'account_no' => 'required|string|max:100',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
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

        $validator->after(function ($v) use ($request) {
            if ($this->accountNoIsTaken($request->input('account_no'))) {
                $v->errors()->add(
                    'account_no',
                    'This account number is already assigned to another consumer.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            ConsumerZone::syncInstallActivationFields($data);

            $consumer = ConsumerZone::create(ConsumerZone::filterTableAttributes($data));

            return response()->json([
                'success' => true,
                'message' => 'Consumer created successfully!',
                'consumer' => $consumer
            ], 201);
        } catch (QueryException $e) {
            if ($this->isAccountNoUniqueViolation($e)) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'account_no' => ['This account number is already assigned to another consumer.'],
                    ],
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create consumer: ' . $e->getMessage()
            ], 500);
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
    public function show(ConsumerZone $consumer)
    {
         $consumer->setAttribute(
            'latest_disconnected_at',
            optional($consumer->latestDisconnectedAtForDisplay())->toIso8601String()
        );
        $latestBill = $this->getLatestBillFromDownloadedReadings($consumer->account_no);
        $meterReading = $this->getLatestMeterReading($consumer->account_no);
        $zones = ConsumerZone::distinctZoneCodes();

        return view('Files.main-consumer', compact('consumer', 'latestBill', 'meterReading', 'zones'));
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

        $drTable = mr_col('downloaded_readings as dr');
        $mrsTable = mr_col('meter_reading_schedules as mrs');
        $czTable = mr_col('consumer_zone as cz');
        $drScheduleId = mr_col('dr.schedule_id');
        $mrsId = mr_col('mrs.id');
        $drConsumerZoneId = mr_col('dr.consumer_zone_id');
        $mrsConsumerZoneId = mr_col('mrs.consumer_zone_id');
        $czId = mr_col('cz.id');
        $czAccountNo = mr_col('cz.account_no');
        $drReadingDate = mr_col('dr.reading_date');
        $drId = mr_col('dr.id');
        $accountNoColumn = mr_col('account_no');

        // Current Reading: from downloaded_readings.current_reading (latest by account)
        $dr = DB::table($drTable)
            ->leftJoin($mrsTable, $drScheduleId, '=', $mrsId)
            ->leftJoin($czTable, function ($join) use ($czId, $drConsumerZoneId, $mrsConsumerZoneId) {
                $join->on($czId, '=', $drConsumerZoneId)
                    ->orOn($czId, '=', $mrsConsumerZoneId);
            })
            ->where(function ($query) use ($accountNumber, $normalizedAccount, $upperAccount, $czAccountNo) {
                $query->where($czAccountNo, $accountNumber)
                    ->orWhere($czAccountNo, $normalizedAccount)
                    ->orWhereRaw("REPLACE(TRIM(cz.account_no), '-', '') = ?", [$normalizedAccount])
                    ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [$upperAccount])
                    ->orWhereRaw("REPLACE(REPLACE(TRIM(cz.account_no), '-', ''), ' ', '') = ?", [$normalizedAccount]);
            })
            ->orderByDesc($drReadingDate)
            ->orderByDesc($drId)
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
            $base = ConsumerZone::query()
                ->where($accountNoColumn, $accountNumber)
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
        $accountNoColumn = mr_col('account_no');
        $clTable = mr_col('consumer_ledgers');
        $clConsumerZoneId = mr_col('consumer_zone_id');
        $clTrans = mr_col('trans');
        $clId = mr_col('id');

        $consumer = ConsumerZone::query()
            ->where($accountNoColumn, $accountNumber)
            ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedAccount])
            ->first();
        if (!$consumer) {
            return null;
        }

        $ledger = DB::table($clTable)
            ->where($clConsumerZoneId, $consumer->id)
            ->whereIn($clTrans, ['BILLING', 'BILL'])
            ->orderByDesc($clId)
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

        $drTable = mr_col('downloaded_readings as dr');
        $mrsTable = mr_col('meter_reading_schedules as mrs');
        $czTable = mr_col('consumer_zone as cz');
        $drScheduleId = mr_col('dr.schedule_id');
        $mrsId = mr_col('mrs.id');
        $drConsumerZoneId = mr_col('dr.consumer_zone_id');
        $mrsConsumerZoneId = mr_col('mrs.consumer_zone_id');
        $czId = mr_col('cz.id');
        $czAccountNo = mr_col('cz.account_no');
        $drReadingDate = mr_col('dr.reading_date');
        $drCreatedAt = mr_col('dr.created_at');
        $accountNoColumn = mr_col('account_no');
        $mrsTablePlain = mr_col('meter_reading_schedules');
        $mrsScheduleId = mr_col('id');
        $czTablePlain = mr_col('consumer_zone');
        $clConsumerZoneId = mr_col('consumer_zone_id');
        $clBalance = mr_col('balance');
        $clDate = mr_col('date');
        $clId = (new ConsumerLedger)->getKeyName();

        // Query downloaded_readings table with LEFT JOIN to meter_reading_schedules
        $reading = DB::table($drTable)
            ->leftJoin($mrsTable, $drScheduleId, '=', $mrsId)
            ->leftJoin($czTable, function ($join) use ($czId, $drConsumerZoneId, $mrsConsumerZoneId) {
                $join->on($czId, '=', $drConsumerZoneId)
                    ->orOn($czId, '=', $mrsConsumerZoneId);
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
            ->where(function ($query) use ($accountNumber, $normalizedAccount, $czAccountNo) {
                $query->where($czAccountNo, $accountNumber)
                      ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$normalizedAccount])
                      ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($accountNumber))]);
            })
            ->orderByDesc($drReadingDate)
            ->orderByDesc($drCreatedAt)
            ->first();

        // If no result with schedule join, try by consumer_zone_id on downloaded_readings
        if (!$reading) {
            $consumer = ConsumerZone::query()
                ->where($accountNoColumn, $accountNumber)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalizedAccount])
                ->first();

            if ($consumer) {
                $directReading = DB::table($drTable)
                    ->leftJoin($czTable, $drConsumerZoneId, '=', $czId)
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
                    ->where($drConsumerZoneId, $consumer->id)
                    ->orderByDesc($drReadingDate)
                    ->orderByDesc($drCreatedAt)
                    ->first();
            } else {
                $directReading = null;
            }

            if ($directReading && $directReading->schedule_id) {
                // Get schedule data separately
                $schedule = DB::table($mrsTablePlain)
                    ->where($mrsScheduleId, $directReading->schedule_id)
                    ->first();
                
                if ($schedule) {
                    $categoryCode = null;
                    if (!empty($schedule->consumer_zone_id)) {
                        $categoryCode = DB::table($czTablePlain)
                            ->where($czId, $schedule->consumer_zone_id)
                            ->value(mr_col('category_code'));
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
        $consumer = ConsumerZone::query()->where($accountNoColumn, $accountNumber)->first();
        $latestBalance = 0.0;
        if ($consumer) {
            $latestBalanceEntry = ConsumerLedger::query()
                ->where($clConsumerZoneId, $consumer->id)
                ->whereNotNull($clBalance)
                ->orderBy($clDate, 'desc')
                ->orderBy($clId, 'desc')
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
    private function calculateWaterBill($consumption, $category = null, $rateCode = null)
    {
        return app(WaterBillingService::class)->calculate($consumption, $category, $rateCode);
    }

    
    
    /**
     * Update the specified resource in storage.
     */
      public function update(Request $request, ConsumerZone $consumer)
    {
        $this->normalizeConsumerRequestInput($request);
        $billDiscChanged = $this->consumerBillDiscChangedFromRequest($request, $consumer);

        $rules = [
            'install_date' => 'nullable|date',
            'transaction_date' => 'nullable|date',
            'account_no' => 'required|string|max:100',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
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

        if ($billDiscChanged && $this->isScDiscountPercent($request->input('bill_disc_percent'))) {
            $rules['bill_disc_updated_at'] = 'required|date';
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($v) use ($request, $consumer) {
            if ($this->accountNoIsTaken($request->input('account_no'), $consumer->id)) {
                $v->errors()->add(
                    'account_no',
                    'This account number is already assigned to another consumer.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->except(['_token', '_method']);
            ConsumerZone::syncInstallActivationFields($data, $consumer);
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
                $consumer->update(ConsumerZone::filterTableAttributes($data));
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

        $baTable = mr_col('billing_adjustments');
        $clTable = mr_col('consumer_ledgers');
        $collectionTable = mr_col('collection');
        $doTable = mr_col('disconnection_orders');
        $consumerZoneIdColumn = mr_col('consumer_zone_id');
        $consumerIdColumn = mr_col('consumer_id');
        $accountNoColumn = mr_col('account_no');
        $accountNameColumn = mr_col('account_name');

        // billing_adjustments links via consumer_zone_id; account_no lives on consumer_zone.
        if ($newAccountNo !== '' && Schema::hasTable('billing_adjustments') && Schema::hasColumn('billing_adjustments', 'account_no')) {
            DB::table($baTable)
                ->where($consumerZoneIdColumn, $consumerId)
                ->update([$accountNoColumn => $newAccountNo]);
        }

        if ($newAccountNo !== '' && Schema::hasTable('consumer_ledgers') && Schema::hasColumn('consumer_ledgers', 'account_no')) {
            DB::table($clTable)
                ->where($consumerZoneIdColumn, $consumerId)
                ->update([$accountNoColumn => $newAccountNo]);
        }

        // downloaded_readings and meter_reading_schedules store consumer_zone_id only; account fields live on consumer_zone.

        if (Schema::hasTable('collection')) {
            if ($accountNoChanged && Schema::hasColumn('collection', 'account_no')) {
                DB::table($collectionTable)
                    ->where(function ($q) use ($oldAccountNo, $normalizedOld, $accountNoColumn) {
                        $q->where($accountNoColumn, $oldAccountNo)
                          ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedOld]);
                    })
                    ->update([$accountNoColumn => $newAccountNo]);
            }
            if ($accountNameChanged && Schema::hasColumn('collection', 'account_name')) {
                DB::table($collectionTable)
                    ->where(function ($q) use ($newAccountNo, $oldAccountNo, $normalizedOld, $normalizedNew, $accountNoColumn) {
                        $q->where($accountNoColumn, $newAccountNo)
                          ->orWhere($accountNoColumn, $oldAccountNo)
                          ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedOld])
                          ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalizedNew]);
                    })
                    ->update([$accountNameColumn => $newAccountName]);
            }
        }

        if (Schema::hasTable('disconnection_orders')) {
            $applyDisconnectionConsumerScope = function ($query) use ($consumerId, $consumerZoneIdColumn, $consumerIdColumn) {
                if (Schema::hasColumn('disconnection_orders', 'consumer_zone_id')) {
                    $query->where($consumerZoneIdColumn, $consumerId);
                } elseif (Schema::hasColumn('disconnection_orders', 'consumer_id')) {
                    $query->where($consumerIdColumn, $consumerId);
                }
            };
            if ($accountNoChanged && Schema::hasColumn('disconnection_orders', 'account_no')) {
                DB::table($doTable)
                    ->where($applyDisconnectionConsumerScope)
                    ->update([$accountNoColumn => $newAccountNo]);
            }
            if ($accountNameChanged && Schema::hasColumn('disconnection_orders', 'account_name')) {
                DB::table($doTable)
                    ->where($applyDisconnectionConsumerScope)
                    ->update([$accountNameColumn => $newAccountName]);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConsumerZone $consumer)
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
     * Normalize bill_disc_percent for comparison (null = None, SC DISCOUNT = senior discount).
     */
    private function normalizeBillDiscPercent($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        $s = trim((string) $value);
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

        // Legacy non-SC values (e.g. "0", "10") match UI "None".
        return null;
    }

    private function isScDiscountPercent($value): bool
    {
        return $this->normalizeBillDiscPercent($value) === 'SC DISCOUNT';
    }

    /**
     * True when bill discount fields in request differ from stored values.
     */
    private function consumerBillDiscChangedFromRequest(Request $request, ConsumerZone $consumer): bool
    {
        $normAmount = function ($v) {
            if ($v === '' || $v === null) {
                return null;
            }
            if (! is_numeric($v)) {
                return null;
            }

            return round((float) $v, 4);
        };

        $percentChanged = $this->normalizeBillDiscPercent($request->input('bill_disc_percent'))
            !== $this->normalizeBillDiscPercent($consumer->bill_disc_percent);
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

    /**
     * Display the consumer master list import page.
     */
    public function importIndex()
    {
        return view('consumer.import');
    }

    /**
     * Import consumer_zone master list from Excel file.
     */
    public function importStore(Request $request)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        DB::connection()->disableQueryLog();

        Log::info('Consumer zone import request received', [
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'no file',
            'content_length' => $request->server('CONTENT_LENGTH'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]);

        try {
            if (!$request->hasFile('file') && (int) $request->server('CONTENT_LENGTH') > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['The uploaded file exceeds the server upload limit. Ask your administrator to increase upload_max_filesize and post_max_size in PHP settings.'],
                ]);
            }

            $uploadedFile = $request->file('file');

            if (!$uploadedFile) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['Please select a file to upload.'],
                ]);
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $mimeType = $uploadedFile->getMimeType();
            $allowedExtensions = ['xls', 'xlsx', 'xltx', 'xlsm', 'csv', 'txt'];
            $allowedMimeTypes = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
                'application/vnd.ms-excel.sheet.macroEnabled.12',
                'application/vnd.ms-office',
                'application/octet-stream',
                'text/plain',
                'text/csv',
                'application/csv',
            ];

            if (!in_array($extension, $allowedExtensions, true) && !in_array($mimeType, $allowedMimeTypes, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['The file must be an Excel file (.xlsx, .xls, .xltx, .csv).'],
                ]);
            }

            $import = new ConsumerZoneImport();
            $beforeCount = ConsumerZone::count();

            $filePath = $uploadedFile->getRealPath();
            if (!$filePath || !is_readable($filePath)) {
                throw new \Exception('The uploaded file cannot be read. Please ensure the file is not corrupted.');
            }

            try {
                Excel::import($import, $uploadedFile);
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                if (strpos($e->getMessage(), 'OLE') !== false || strpos($e->getMessage(), 'not recognised') !== false) {
                    throw new \Exception('The Excel file appears to be corrupted or is not in a valid Excel format. Please try re-saving the file in Excel and upload again.');
                }
                throw $e;
            } finally {
                HeadingRowFormatter::reset();
            }

            $afterCount = ConsumerZone::count();
            $imported = $import->importedCount;
            $updated = $import->updatedCount;
            $skipped = $import->skippedCount;
            $errors = $import->errors;

            Log::info('Consumer zone import completed', [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'beforeCount' => $beforeCount,
                'afterCount' => $afterCount,
            ]);

            $processed = $imported + $updated;

            if ($processed > 0) {
                $message = "Consumer master list imported successfully! {$imported} new record(s), {$updated} updated.";
                if ($skipped > 0) {
                    $message .= " {$skipped} row(s) skipped.";
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'imported' => $imported,
                        'updated' => $updated,
                        'skipped' => $skipped,
                    ]);
                }

                return back()->with('success', $message);
            }

            $message = 'Import failed. No records were imported or updated.';
            if ($skipped > 0) {
                $message .= " {$skipped} row(s) skipped.";
            }
            if (!empty($errors)) {
                $message .= ' Issues: ' . implode(' | ', array_slice($errors, 0, 5));
            } else {
                $message .= ' Please verify your file format matches the expected column headers.';
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'imported' => $imported,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ], 422);
            }

            return back()->with('warning', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Consumer zone import error', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = 'An error occurred during import: ' . $e->getMessage();
            if (str_contains($e->getMessage(), 'memory') || str_contains($e->getMessage(), 'Memory')) {
                $errorMessage = 'Import ran out of memory. Restart the server with higher memory_limit (512M+) and try again.';
            } elseif (str_contains($e->getMessage(), 'Maximum execution time')) {
                $errorMessage = 'Import timed out. Restart the server with max_execution_time=300 (or 0) and try again.';
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }

            return back()->with('error', $errorMessage);
        }
    }

    /**
     * Display the Upload Base Reading (Excel) page.
     */
    public function uploadBaseReadingIndex()
    {
        return view('consumer.upload-base-reading');
    }

    /**
     * Bulk-update consumer_zone.base_reading from Excel.
     * Required columns: account_no, account_name, base_reading.
     * Same lock rules as updateConsumerBaseReading (MeterReadingController).
     */
    public function uploadBaseReadingStore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $imported = 0;
        $failed = 0;
        $errors = [];

        if (!Schema::hasColumn('consumer_zone', 'base_reading')) {
            return back()->with('error', 'Base reading is not supported in this database (missing column). Run the latest migrations.');
        }

        try {
            $data = Excel::toArray(new BaseReadingImport(), $request->file('file'));
            $rows = $data[0] ?? [];

            if (empty($rows)) {
                return back()->with('error', 'The file is empty or has no data rows.');
            }

            $header = $rows[0];
            $accountNoCol = $this->findExcelColumnIndex($header, ['account_no', 'account_number', 'accountnumber', 'account no']);
            $baseReadingCol = $this->findExcelColumnIndex($header, ['base_reading', 'basereading', 'base reading', 'base_read']);

            if ($accountNoCol === null) {
                return back()->with('error', 'Excel must have column: account_no (or account_number).');
            }
            if ($baseReadingCol === null) {
                return back()->with('error', 'Excel must have column: base_reading.');
            }

            $processedInThisFile = [];
            $today = Carbon::now()->format('Y-m-d');

            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                $rowNum = $index + 1;

                $accountNo = isset($row[$accountNoCol]) ? trim((string) $row[$accountNoCol]) : null;
                if ($accountNo === '') {
                    $accountNo = null;
                }

                $baseReadingVal = isset($row[$baseReadingCol]) ? $row[$baseReadingCol] : null;

                if (!$accountNo) {
                    $errors[] = "Row {$rowNum}: Missing account_no.";
                    $failed++;
                    continue;
                }

                if (isset($processedInThisFile[$accountNo])) {
                    $errors[] = "Row {$rowNum}: Duplicate in file – [{$accountNo}] already processed in row {$processedInThisFile[$accountNo]}.";
                    $failed++;
                    continue;
                }

                $baseReadingInt = null;
                if ($baseReadingVal !== null && $baseReadingVal !== '') {
                    if (is_numeric($baseReadingVal)) {
                        $baseReadingInt = (int) round((float) $baseReadingVal);
                        if ($baseReadingInt < 0) {
                            $errors[] = "Row {$rowNum}: base_reading must be >= 0.";
                            $failed++;
                            continue;
                        }
                    } else {
                        $errors[] = "Row {$rowNum}: base_reading must be numeric.";
                        $failed++;
                        continue;
                    }
                } else {
                    $errors[] = "Row {$rowNum}: Missing or invalid base_reading.";
                    $failed++;
                    continue;
                }

                $consumer = ConsumerZone::findByAccountNo($accountNo);
                if (!$consumer) {
                    $errors[] = "Row {$rowNum}: Consumer not found for account [{$accountNo}].";
                    $failed++;
                    continue;
                }

                if ($this->consumerHasBaseReadingLock($consumer)) {
                    $errors[] = "Row {$rowNum}: Base reading is locked for [{$accountNo}] — consumer already has reading history.";
                    $failed++;
                    continue;
                }

                $consumer->update(ConsumerZone::filterTableAttributes([
                    'base_reading' => $baseReadingInt,
                    'base_reading_date' => $today,
                ]));

                $processedInThisFile[$accountNo] = $rowNum;
                $imported++;
            }

            Log::info('Bulk base_reading upload completed', [
                'imported' => $imported,
                'failed' => $failed,
                'user' => optional(Auth::user())->name,
            ]);

            $message = $imported > 0
                ? "Updated base_reading for {$imported} account(s)."
                : 'No rows updated.';
            if ($failed > 0) {
                $message .= " {$failed} row(s) failed.";
            }

            if ($imported > 0) {
                return back()
                    ->with('success', $message)
                    ->with('upload_errors', $errors);
            }

            return back()
                ->with('warning', $message)
                ->with('upload_errors', $errors);
        } catch (\Throwable $e) {
            Log::error('Upload base_reading failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Same lock rules as MeterReadingController::updateConsumerBaseReading.
     */
    private function consumerHasBaseReadingLock(ConsumerZone $consumer): bool
    {
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

        return $hasDownloadedReading || $hasScheduleHistory || $hasBillingLedger;
    }

    /**
     * Find column index by possible header names (case-insensitive, spaces/underscores normalized).
     */
    private function findExcelColumnIndex(array $headerRow, array $possibleNames): ?int
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

    private function normalizeAccountNo(?string $accountNo): string
    {
        return trim((string) ($accountNo ?? ''));
    }

    /**
     * Convert empty strings to null for optional numeric fields so Laravel validation passes.
     */
    private function normalizeConsumerRequestInput(Request $request): void
    {
        $merge = [
            'account_no' => $this->normalizeAccountNo($request->input('account_no')),
        ];

        foreach (['bill_disc_amount', 'latitude', 'longitude'] as $key) {
            $value = $request->input($key);
            if ($value === '' || $value === null) {
                $merge[$key] = null;
            }
        }

        $sequence = $request->input('sequence');
        if ($sequence === '' || $sequence === null) {
            $merge['sequence'] = null;
        }

        $billDiscPercent = $request->input('bill_disc_percent');
        if ($billDiscPercent === '' || $billDiscPercent === null) {
            $merge['bill_disc_percent'] = null;
        }

        $request->merge($merge);
    }

    private function accountNoIsTaken(string $accountNo, ?int $exceptConsumerId = null): bool
    {
        $accountNo = $this->normalizeAccountNo($accountNo);
        if ($accountNo === '') {
            return false;
        }

        $existing = ConsumerZone::findByAccountNo($accountNo);
        if (!$existing) {
            return false;
        }

        return $exceptConsumerId === null || (int) $existing->id !== (int) $exceptConsumerId;
    }

    private function isAccountNoUniqueViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            && str_contains($message, 'account_no');
    }
}

