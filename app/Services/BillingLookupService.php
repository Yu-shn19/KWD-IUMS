<?php

namespace App\Services;

use App\Data\BillingReadingRecord;
use App\Data\BillingLookupState;
use App\Http\Controllers\ConsumerLedgerController;
use App\Models\ConsumerLedger;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZone;
use App\Models\DownloadedReading;
use App\Models\LROLedger;
use App\Support\SundryLedgerRemarks;
use App\Models\Penalty;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    function mr_col(string $name): string
    {
        return $name;
    }
}

class BillingLookupService
{
    public function lookup(Request $request): JsonResponse
    {
        $accountNumber = null;
        $accountName = null;

        try {
            return $this->performLookup($request);
        } catch (Throwable $e) {
            $accountNumber = $request->input('account_number') ? strtoupper(trim((string) $request->input('account_number'))) : null;
            $accountName = $request->input('account_name') ? strtoupper(trim((string) $request->input('account_name'))) : null;

            Log::error('lookupBillingRecord error', [
                'message' => $e->getMessage(),
                'account' => $accountNumber ?: $accountName,
                'trace' => $e->getTraceAsString(),
            ]);

            $userMessage = 'Unable to fetch billing record.';
            if (config('app.debug')) {
                $userMessage .= ' ' . $e->getMessage();
            } else {
                $userMessage .= ' Please verify the account number and bill month, or try again later.';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ], 200);
        }
    }

    private function performLookup(Request $request): JsonResponse
    {
        $parsed = $this->parseLookupRequest($request);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }

        $state = $parsed;

        if ($state->orNumberInput !== '') {
            $orResponse = $this->lookupReadingByOrNumber($state);
            if ($orResponse instanceof JsonResponse) {
                return $orResponse;
            }
        }

        if ($state->reading === null) {
            $accountResponse = $this->lookupReadingByAccount($state);
            if ($accountResponse instanceof JsonResponse) {
                return $accountResponse;
            }
        }

        if (!$state->reading) {
            return response()->json([
                'success' => false,
                'message' => 'Billing record could not be loaded for this account. Please try again or select a bill month.',
            ], 200);
        }

        return $this->buildLookupJsonResponse($state);
    }

    /**
     * @return JsonResponse|BillingLookupState
     */
    private function parseLookupRequest(Request $request): JsonResponse|BillingLookupState
    {
        $request->validate([
            'account_number' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string'],
            'bill_month' => ['nullable', 'string'],
            'or_number' => ['nullable', 'string'],
        ]);

        $accountNumber = $request->input('account_number') ? strtoupper(trim($request->input('account_number'))) : null;
        $accountName = $request->input('account_name') ? strtoupper(trim($request->input('account_name'))) : null;
        $billMonthInput = trim((string) $request->input('bill_month', ''));
        $orNumberInput = trim((string) $request->input('or_number', ''));

        // Require either account_number, account_name, or or_number
        if (!$accountNumber && !$accountName && $orNumberInput === '') {
            return response()->json([
                'success' => false,
                'message' => 'Either account number, account name, or OR number is required.',
            ], 422);
        }
        
        $normalizedAccount = $accountNumber ? str_replace('-', '', $accountNumber) : null;

        // Resolve bill month if provided - handle multiple formats
        $billMonth = $billMonthInput !== '' ? $this->resolveBillMonth($billMonthInput) : null;

        if ($billMonthInput !== '' && !$billMonth) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bill month format. Use MM-YYYY, MM/YYYY, or DD/MM/YYYY.',
            ], 422);
        }

        $billMonthDate = $billMonth?->copy()->startOfMonth();
        return new BillingLookupState(
            accountNumber: $accountNumber,
            accountName: $accountName,
            normalizedAccount: $normalizedAccount,
            billMonthDate: $billMonthDate,
            orNumberInput: $orNumberInput,
        );
    }

    private function lookupReadingByOrNumber(BillingLookupState $state): ?JsonResponse
    {
        // Lookup by OR # from consumer_payments table
            $payment = ConsumerPayment::query()->where(mr_col('or_number'), $state->orNumberInput)->first();
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'OR number not found.',
                ], 404);
            }
            $state->orLookupPayment = $payment;
            if ($payment->reading_id) {
                $dr = DB::table(mr_col('downloaded_readings'))->where(mr_col('id'), $payment->reading_id)->first();
                if (!$dr) {
                    // Linked reading missing: fall back to consumer_payment + consumer so the form can still load and be updated
                    $consumer = $this->resolvePaymentConsumer($payment);
                    if (!$consumer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment record found but linked reading and consumer not found. Cannot load form.',
                    ], 404);
                }
                $state->accountNumber = $consumer->account_no ? strtoupper(trim($consumer->account_no)) : null;
                    $state->normalizedAccount = $state->accountNumber ? str_replace('-', '', $state->accountNumber) : null;
                    $billMonthDateObj = $state->billMonthDate ? $state->billMonthDate->format('Y-m-d') : null;
                    $state->reading = BillingReadingRecord::make([
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code ?? null,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => (float) ($payment->current_bill ?? 0),
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => $payment->payment_method ?? null,
                        'payment_amount' => $payment->payment_amount ?? null,
                        'amount_tendered' => $payment->amount_tendered ?? null,
                        'change_amount' => $payment->change_amount ?? null,
                        'official_receipt_number' => $payment->or_number,
                        'payment_remarks' => $payment->remarks ?? null,
                        'paid_at' => $payment->paid_at ?? null,
                        'schedule_account_name' => $consumer->account_name,
                        'address' => $consumer->address ?? '',
                        'category' => $consumer->category_code ?? '',
                        'meter_number' => $consumer->meter_number ?? null,
                        'bill_month' => $billMonthDateObj,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => (float) ($payment->current_bill ?? 0),
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ]);
                } else {
                    $drQuery = DB::table(mr_col('downloaded_readings as dr'));
                    $this->applyDownloadedReadingConsumerJoin($drQuery, 'dr');
                    $drRow = $drQuery
                        ->where(mr_col('dr.id'), $payment->reading_id)
                        ->select(
                            'dr.id as downloaded_id',
                            'dr.schedule_id',
                            'dr.reader_id',
                            'cz.account_no as account_number',
                            'cz.account_name',
                            'cz.zone_code as zone',
                            'cz.address',
                            'cz.category_code as category',
                            'cz.meter_number',
                            'dr.previous_reading',
                            'dr.current_reading',
                            'dr.consumption',
                            'dr.current_bill as downloaded_current_bill',
                            'dr.reading_date',
                            'dr.status',
                            'dr.reader_notes',
                            'dr.created_at as downloaded_created_at',
                            'dr.updated_at as downloaded_updated_at',
                            'mrs.sedr_number',
                            'mrs.bill_month',
                            'mrs.bill_date',
                            'mrs.due_date',
                            'mrs.disconnection_date',
                            'mrs.previous_reading_date',
                            'mrs.current_bill as schedule_current_bill',
                            'mrs.arrears',
                            'mrs.total_amount',
                            'mrs.status as schedule_status'
                        )
                        ->first();

                    $consumerForDr = null;
                    if ($drRow && !empty($dr->consumer_zone_id)) {
                        $consumerForDr = ConsumerZone::find($dr->consumer_zone_id);
                    } elseif ($drRow && $drRow->schedule_id) {
                        $czId = DB::table(mr_col('meter_reading_schedules'))->where(mr_col('id'), $drRow->schedule_id)->value(mr_col('consumer_zone_id'));
                        if ($czId) {
                            $consumerForDr = ConsumerZone::find($czId);
                        }
                    }

                    $state->reading = BillingReadingRecord::make([
                        'downloaded_id' => $drRow->downloaded_id ?? $dr->id,
                        'schedule_id' => $drRow->schedule_id ?? $dr->schedule_id,
                        'reader_id' => $drRow->reader_id ?? $dr->reader_id ?? null,
                        'account_number' => $drRow->account_number ?? $consumerForDr?->account_no,
                        'account_name' => $drRow->account_name ?? $consumerForDr?->account_name,
                        'zone' => $drRow->zone ?? $consumerForDr?->zone_code,
                        'previous_reading' => $drRow->previous_reading ?? $dr->previous_reading ?? 0,
                        'current_reading' => $drRow->current_reading ?? $dr->current_reading ?? null,
                        'consumption' => $drRow->consumption ?? $dr->consumption ?? 0,
                        'downloaded_current_bill' => $drRow->downloaded_current_bill ?? $dr->current_bill ?? ($drRow->schedule_current_bill ?? 0),
                        'reading_date' => $drRow->reading_date ?? $dr->reading_date ?? null,
                        'status' => $drRow->status ?? $dr->status ?? 'Prepared',
                        'reader_notes' => $drRow->reader_notes ?? $dr->reader_notes ?? null,
                        'completed_at' => $this->downloadedReadingsHasCompletedAt() ? ($dr->completed_at ?? null) : null,
                        'payment_method' => $payment->payment_method ?? null,
                        'payment_amount' => $payment->payment_amount ?? null,
                        'amount_tendered' => $payment->amount_tendered ?? null,
                        'change_amount' => $payment->change_amount ?? null,
                        'official_receipt_number' => $payment->or_number,
                        'payment_remarks' => $payment->remarks ?? null,
                        'paid_at' => $payment->paid_at ?? null,
                        'downloaded_created_at' => $drRow->downloaded_created_at ?? $dr->created_at ?? null,
                        'downloaded_updated_at' => $drRow->downloaded_updated_at ?? $dr->updated_at ?? null,
                        'sedr_number' => $drRow->sedr_number ?? null,
                        'schedule_account_name' => $drRow->account_name ?? $consumerForDr?->account_name,
                        'address' => $drRow->address ?? $consumerForDr?->address,
                        'category' => $drRow->category ?? $consumerForDr?->category_code,
                        'meter_number' => $drRow->meter_number ?? $consumerForDr?->meter_number,
                        'bill_month' => $drRow->bill_month ?? null,
                        'bill_date' => $drRow->bill_date ?? null,
                        'due_date' => $drRow->due_date ?? null,
                        'disconnection_date' => $drRow->disconnection_date ?? null,
                        'previous_reading_date' => $drRow->previous_reading_date ?? null,
                        'schedule_current_bill' => $drRow->schedule_current_bill ?? null,
                        'arrears' => $drRow->arrears ?? null,
                        'total_amount' => $drRow->total_amount ?? null,
                        'schedule_status' => $drRow->schedule_status ?? null,
                    ]);
                    $state->accountNumber = $state->reading->account_number ? strtoupper(trim($state->reading->account_number)) : null;
                    $state->normalizedAccount = $state->accountNumber ? str_replace('-', '', $state->accountNumber) : null;
                }
            } else {
                $consumer = $this->resolvePaymentConsumer($payment);
                if (!$consumer) {
                    // Support "Others" payments where consumer_id is null and only account_name is stored.
                    $paidAt = $payment->paid_at ? Carbon::parse($payment->paid_at) : null;
                    $billMonthDateObj = $state->billMonthDate
                        ? $state->billMonthDate->format('Y-m-d')
                        : ($paidAt ? $paidAt->copy()->startOfMonth()->format('Y-m-d') : null);

                    $state->accountNumber = null;
                    $state->normalizedAccount = null;
                    $state->reading = BillingReadingRecord::make([
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => null,
                        'account_name' => $payment->account_name ?? '',
                        'zone' => null,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => (float) ($payment->current_bill ?? 0),
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => $payment->payment_method ?? null,
                        'payment_amount' => $payment->payment_amount ?? null,
                        'amount_tendered' => $payment->amount_tendered ?? null,
                        'change_amount' => $payment->change_amount ?? null,
                        'official_receipt_number' => $payment->or_number,
                        'payment_remarks' => $payment->remarks ?? null,
                        'paid_at' => $payment->paid_at ?? null,
                        'schedule_account_name' => $payment->account_name ?? '',
                        'address' => '',
                        'category' => '',
                        'meter_number' => null,
                        'bill_month' => $billMonthDateObj,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => (float) ($payment->current_bill ?? 0),
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ]);
                } else {
                    $state->accountNumber = $consumer->account_no ? strtoupper(trim($consumer->account_no)) : null;
                    $state->normalizedAccount = $state->accountNumber ? str_replace('-', '', $state->accountNumber) : null;
                    $billMonthDateObj = $state->billMonthDate ? $state->billMonthDate->format('Y-m-d') : null;
                    $state->reading = BillingReadingRecord::make([
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code ?? null,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                    'downloaded_current_bill' => 0.0,
                    'reading_date' => null,
                    'status' => 'Prepared',
                    'reader_notes' => null,
                    'completed_at' => null,
                    'payment_method' => $payment->payment_method ?? null,
                    'payment_amount' => $payment->payment_amount ?? null,
                    'amount_tendered' => $payment->amount_tendered ?? null,
                    'change_amount' => $payment->change_amount ?? null,
                    'official_receipt_number' => $payment->or_number,
                    'payment_remarks' => $payment->remarks ?? null,
                    'paid_at' => $payment->paid_at ?? null,
                    'schedule_account_name' => $consumer->account_name,
                    'address' => $consumer->address ?? '',
                    'category' => $consumer->category_code ?? '',
                    'meter_number' => $consumer->meter_number ?? null,
                    'bill_month' => $billMonthDateObj,
                    'bill_date' => null,
                    'due_date' => null,
                    'disconnection_date' => null,
                    'previous_reading_date' => null,
                    'schedule_current_bill' => 0.0,
                    'arrears' => null,
                    'total_amount' => null,
                    'schedule_status' => null,
                    'sedr_number' => null,
                    'downloaded_created_at' => null,
                    'downloaded_updated_at' => null,
                    'payment_reference' => null,
                ]);
                    
                }
            }
            $state->lookupSuccessMessage = 'Billing record loaded by OR number.';
        return null;
    }

    private function lookupReadingByAccount(BillingLookupState $state): ?JsonResponse
    {
        if ($state->reading !== null) {
            return null;
        }

        // Query downloaded_readings table with LEFT JOIN to handle cases where schedule might not exist
        $readingQuery = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('consumer_payments as cp'), mr_col('cp.reading_id'), '=', mr_col('dr.id'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
                    ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
            })
            ->select(array_merge($this->downloadedReadingBaseSelectColumns(), [
                'mrs.sedr_number',
                'cz.account_name as schedule_account_name',
                'cz.address',
                'cz.category_code as category',
                'cz.meter_number',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.disconnection_date',
                'mrs.previous_reading_date',
                'mrs.current_bill as schedule_current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'mrs.status as schedule_status',
            ]))
            ->where(function ($query) use ($state) {
                if ($state->accountNumber) {
                    $query->where(mr_col('cz.account_no'), $state->accountNumber)
                          ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$state->normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                }
                if ($state->accountName) {
                    $query->orWhereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $state->accountName . '%']);
                }
            });

        // Filter by bill month if provided - check both schedule bill_month and reading_date
        if ($state->billMonthDate) {
            $readingQuery->where(function ($query) use ($state) {
                $query->whereDate(mr_col('mrs.bill_month'), $state->billMonthDate->format('Y-m-d'))
                      ->orWhere(function ($q) use ($state) {
                          // Also match by reading_date if bill_month doesn't match
                          $q->whereNull(mr_col('mrs.bill_month'))
                            ->whereYear('dr.reading_date', $state->billMonthDate->year)
                            ->whereMonth('dr.reading_date', $state->billMonthDate->month);
                      });
            });
        }

        // Order by most recent reading first
        $state->reading = ($__row = $this->applyDownloadedReadingRecencyOrder($readingQuery)->first()) ? BillingReadingRecord::fromStdClass($__row) : null;

        // If no result with schedule join, try querying downloaded_readings directly
        if (!$state->reading) {
            $directQuery = DB::table(mr_col('downloaded_readings as dr'))
                ->leftJoin(mr_col('consumer_payments as cp'), mr_col('cp.reading_id'), '=', mr_col('dr.id'))
                ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
                ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                    $join->on(mr_col('cz.id'), '=', mr_col('dr.consumer_zone_id'))
                        ->orOn('cz.id', '=', 'mrs.consumer_zone_id');
                })
                ->select($this->downloadedReadingBaseSelectColumns())
                ->where(function ($query) use ($state) {
                    if ($state->accountNumber) {
                        $query->where(mr_col('cz.account_no'), $state->accountNumber)
                              ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$state->normalizedAccount])
                              ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                    }
                    if ($state->accountName) {
                        $query->orWhereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $state->accountName . '%']);
                    }
                });

            // Filter by reading_date if bill month provided
            if ($state->billMonthDate) {
                $directQuery->where(function ($query) use ($state) {
                    $query->whereYear('dr.reading_date', $state->billMonthDate->year)
                          ->whereMonth('dr.reading_date', $state->billMonthDate->month);
                });
            }

            $state->reading = ($__row = $this->applyDownloadedReadingRecencyOrder($directQuery)->first()) ? BillingReadingRecord::fromStdClass($__row) : null;

            // If still no result, try to get schedule data separately
            if ($state->reading && $state->reading->schedule_id) {
                $schedule = DB::table(mr_col('meter_reading_schedules'))
                    ->where(mr_col('id'), $state->reading->schedule_id)
                    ->first();
                
                if ($schedule) {
                    $consumer = null;
                    if (!empty($schedule->consumer_zone_id)) {
                        $consumer = ConsumerZone::find($schedule->consumer_zone_id);
                    }
                    $readingArray = (array) $state->reading;
                    $readingArray['sedr_number'] = $schedule->sedr_number ?? null;
                    $readingArray['schedule_account_name'] = $consumer?->account_name;
                    $readingArray['address'] = $consumer?->address;
                    $readingArray['category'] = $consumer?->category_code;
                    $readingArray['meter_number'] = $consumer?->meter_number;
                    $readingArray['bill_month'] = $schedule->bill_month ?? null;
                    $readingArray['bill_date'] = $schedule->bill_date ?? null;
                    $readingArray['due_date'] = $schedule->due_date ?? null;
                    $readingArray['disconnection_date'] = $schedule->disconnection_date ?? null;
                    $readingArray['previous_reading_date'] = $schedule->previous_reading_date ?? null;
                    // Keep downloaded_current_bill if exists, otherwise use schedule's current_bill
                    $readingArray['schedule_current_bill'] = $schedule->current_bill ?? null;
                    $readingArray['arrears'] = $schedule->arrears ?? null;
                    $readingArray['total_amount'] = $schedule->total_amount ?? null;
                    $readingArray['schedule_status'] = $schedule->status ?? null;
                    $state->reading = BillingReadingRecord::make($readingArray);
                }
            }
        }

        if (!$state->reading) {
            $searchTerm = $state->accountNumber ?: $state->accountName;
            $searchType = $state->accountNumber ? 'account number' : 'account name';

            // Check if account exists in consumer_zone table
            $consumerExists = false;
            if ($state->accountNumber) {
                $state->normalizedAccount = str_replace('-', '', $state->accountNumber);
                $consumerExists = ConsumerZone::query()->where(function ($query) use ($state) {
                    $query->where(mr_col('account_no'), $state->accountNumber)
                          ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$state->normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                })->exists();
            } elseif ($state->accountName) {
                $consumerExists = ConsumerZone::whereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $state->accountName . '%'])->exists();
            }

            // Check if schedule exists for this account (any month)
            $scheduleExists = false;
            if ($state->accountNumber) {
                $state->normalizedAccount = str_replace('-', '', $state->accountNumber);
                $scheduleExists = DB::table(mr_col('meter_reading_schedules as mrs'))
                    ->join(mr_col('consumer_zone as cz'), mr_col('mrs.consumer_zone_id'), '=', mr_col('cz.id'))
                    ->where(function ($query) use ($state) {
                        $query->where(mr_col('cz.account_no'), $state->accountNumber)
                              ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$state->normalizedAccount])
                              ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                    })
                    ->exists();
            } elseif ($state->accountName) {
                $scheduleExists = DB::table(mr_col('meter_reading_schedules as mrs'))
                    ->join(mr_col('consumer_zone as cz'), mr_col('mrs.consumer_zone_id'), '=', mr_col('cz.id'))
                    ->whereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $state->accountName . '%'])
                    ->exists();
            }

            if (!$consumerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account ' . $searchType . ' "' . $searchTerm . '" not found in the system. Please verify the account number.',
                ], 404);
            }
            if (!$scheduleExists) {
                // Allow form to load so user can record payment; breakdown/arrears (e.g. Arrears â€” Previous Year) will come from ledger via bill-month-details.
                $consumer = null;
                if ($state->accountNumber) {
                    $state->normalizedAccount = str_replace('-', '', $state->accountNumber);
                    $consumer = ConsumerZone::query()->where(function ($q) use ($state) {
                        $q->where(mr_col('account_no'), $state->accountNumber)
                          ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$state->normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                    })->first();
                } elseif ($state->accountName) {
                    $consumer = ConsumerZone::whereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $state->accountName . '%'])->first();
                }
                if ($consumer) {
                    $state->reading = BillingReadingRecord::make([
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => 0.0,
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => null,
                        'payment_amount' => null,
                        'amount_tendered' => null,
                        'change_amount' => null,
                        'official_receipt_number' => null,
                        'payment_remarks' => null,
                        'paid_at' => null,
                        'schedule_account_name' => $consumer->account_name,
                        'address' => $consumer->address ?? '',
                        'category' => $consumer->category_code ?? '',
                        'meter_number' => $consumer->meter_number ?? '',
                        'bill_month' => $state->billMonthDate ? $state->billMonthDate->format('Y-m-d') : null,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => 0.0,
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ]);
                    $state->lookupSuccessMessage = 'Account "' . $searchTerm . '" exists but has no billing schedule. The account may need to be added to a billing cycle first. Unpaid amounts from previous years can be entered in Arrears â€” Previous Year.';
                }
            } else {
            // No completed meter reading yet â€“ use schedule for this bill month so payment form can open (breakdown from ledger)
            if ($state->billMonthDate && ($state->accountNumber || $state->accountName)) {
                $scheduleQuery = DB::table(mr_col('meter_reading_schedules as mrs'))
                    ->leftJoin(mr_col('consumer_zone as cz'), mr_col('mrs.consumer_zone_id'), '=', mr_col('cz.id'))
                    ->whereDate(mr_col('mrs.bill_month'), $state->billMonthDate->format('Y-m-d'));
                if ($state->accountNumber) {
                    $state->normalizedAccount = str_replace('-', '', $state->accountNumber);
                    $scheduleQuery->where(function ($q) use ($state) {
                        $q->where(mr_col('cz.account_no'), $state->accountNumber)
                          ->orWhereRaw("REPLACE(cz.account_no, '-', '') = ?", [$state->normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(cz.account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                    });
                } else {
                    $scheduleQuery->whereRaw("UPPER(TRIM(cz.account_name)) LIKE ?", ['%' . $state->accountName . '%']);
                }
                $scheduleRow = $scheduleQuery->select('mrs.*', 'cz.account_no', 'cz.account_name', 'cz.zone_code', 'cz.address', 'cz.category_code', 'cz.meter_number')->first();
                if ($scheduleRow) {
                    $scheduleId = $scheduleRow->id;
                    $dr = DownloadedReading::query()->where(mr_col('schedule_id'), $scheduleId)->first();
                    if (!$dr) {
                        $dr = DownloadedReading::create([
                            'consumer_zone_id' => $scheduleRow->consumer_zone_id,
                            'schedule_id' => $scheduleId,
                            'previous_reading' => $scheduleRow->previous_reading ?? 0,
                            'current_reading' => $scheduleRow->current_reading ?? null,
                            'consumption' => $scheduleRow->consumption ?? 0,
                            'current_bill' => $scheduleRow->current_bill ?? 0,
                            'reading_date' => $scheduleRow->bill_date ?? $state->billMonthDate->format('Y-m-d'),
                            'status' => 'Prepared',
                        ]);
                    }
                    $base = (array) $dr->toArray();
                    $state->reading = BillingReadingRecord::make(array_merge($base, [
                        'downloaded_id' => $dr->id,
                        'downloaded_current_bill' => $dr->current_bill ?? $scheduleRow->current_bill ?? null,
                        'schedule_id' => $dr->schedule_id,
                        'account_number' => $scheduleRow->account_no ?? $state->accountNumber,
                        'account_name' => $scheduleRow->account_name ?? '',
                        'zone' => $scheduleRow->zone_code ?? '',
                        'bill_month' => $scheduleRow->bill_month ?? null,
                        'bill_date' => $scheduleRow->bill_date ?? null,
                        'due_date' => $scheduleRow->due_date ?? null,
                        'disconnection_date' => $scheduleRow->disconnection_date ?? null,
                        'previous_reading_date' => $scheduleRow->previous_reading_date ?? null,
                        'schedule_current_bill' => $scheduleRow->current_bill ?? null,
                        'arrears' => $scheduleRow->arrears ?? null,
                        'total_amount' => $scheduleRow->total_amount ?? null,
                        'schedule_status' => $scheduleRow->status ?? null,
                        'sedr_number' => $scheduleRow->sedr_number ?? null,
                        'schedule_account_name' => $scheduleRow->account_name ?? null,
                        'address' => $scheduleRow->address ?? null,
                        'category' => $scheduleRow->category_code ?? null,
                        'meter_number' => $scheduleRow->meter_number ?? null,
                    ]));
                    $paymentRow = DB::table(mr_col('consumer_payments'))->where(mr_col('reading_id'), $dr->id)->first();
                    if ($paymentRow) {
                        $state->reading->payment_method = $paymentRow->payment_method ?? null;
                        $state->reading->payment_amount = $paymentRow->payment_amount ?? null;
                        $state->reading->amount_tendered = $paymentRow->amount_tendered ?? null;
                        $state->reading->change_amount = $paymentRow->change_amount ?? null;
                        $state->reading->official_receipt_number = $paymentRow->or_number ?? null;
                        $state->reading->payment_remarks = $paymentRow->remarks ?? null;
                        $state->reading->paid_at = $paymentRow->paid_at ?? null;
                    }
                }
            }

            if (!$state->reading) {
                // Still no downloaded/schedule reading for this bill month, but account + schedules exist.
                // Build a minimal virtual "reading" from the consumer record so payment can still proceed
                // and the breakdown will come purely from the ledger (paid_at-only logic).
                // Use same normalized matching as consumerExists so we find consumer when account_no format differs (e.g. 011-12-250 vs 11-12-250).
                $consumer = null;
                if ($state->accountNumber) {
                    $state->normalizedAccount = str_replace('-', '', $state->accountNumber);
                    $consumer = ConsumerZone::query()->where(function ($q) use ($state) {
                        $q->where(mr_col('account_no'), $state->accountNumber)
                          ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$state->normalizedAccount])
                          ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper(trim($state->accountNumber))]);
                    })->first();
                } elseif ($state->accountName) {
                    $consumer = ConsumerZone::whereRaw("UPPER(TRIM(account_name)) LIKE ?", ['%' . $state->accountName . '%'])->first();
                }
                if ($consumer) {
                    $state->reading = BillingReadingRecord::make([
                        'downloaded_id' => null,
                        'schedule_id' => null,
                        'reader_id' => null,
                        'account_number' => $consumer->account_no,
                        'account_name' => $consumer->account_name,
                        'zone' => $consumer->zone_code,
                        'previous_reading' => 0,
                        'current_reading' => null,
                        'consumption' => 0,
                        'downloaded_current_bill' => 0.0,
                        'reading_date' => null,
                        'status' => 'Prepared',
                        'reader_notes' => null,
                        'completed_at' => null,
                        'payment_method' => null,
                        'payment_amount' => null,
                        'amount_tendered' => null,
                        'change_amount' => null,
                        'official_receipt_number' => null,
                        'payment_remarks' => null,
                        'paid_at' => null,
                        'schedule_account_name' => $consumer->account_name,
                        'address' => $consumer->address ?? '',
                        'category' => $consumer->category_code ?? '',
                        'meter_number' => $consumer->meter_number ?? '',
                        'bill_month' => $state->billMonthDate ? $state->billMonthDate->format('Y-m-d') : null,
                        'bill_date' => null,
                        'due_date' => null,
                        'disconnection_date' => null,
                        'previous_reading_date' => null,
                        'schedule_current_bill' => 0.0,
                        'arrears' => null,
                        'total_amount' => null,
                        'schedule_status' => null,
                        'sedr_number' => null,
                        'downloaded_created_at' => null,
                        'downloaded_updated_at' => null,
                        'payment_reference' => null,
                    ]);
                }
            }
        }
        }

        return null;
    }

    private function buildLookupJsonResponse(BillingLookupState $state): JsonResponse
    {
        $consumer = $this->resolveConsumerForLookup($state);

        $accountData = $this->buildLookupAccountData($state, $consumer);
        $billingData = $this->buildLookupBillingData($state, $consumer);
        $paymentData = $this->buildLookupPaymentData($state, $consumer, $billingData);
        $downloadedReadingData = $this->buildLookupDownloadedReadingData($state);

        $accountNumberForBalance = $accountData['number'] ?? $state->accountNumber;
        $consumerForBalance = $consumer ?? $this->resolveConsumerByAccountNumber($accountNumberForBalance);
        $accountData['current_balance'] = round(
            $this->resolveLookupCurrentBalance($accountNumberForBalance, $consumerForBalance),
            2
        );

        return response()->json([
            'success' => true,
            'message' => $state->lookupSuccessMessage ?? 'Downloaded reading record loaded successfully from downloaded_readings table.',
            'data' => [
                'account' => $accountData,
                'billing' => $billingData,
                'payment' => $paymentData,
                'downloaded_reading' => $downloadedReadingData,
                'sundries' => $this->fetchLookupSundries($consumerForBalance),
                'lro_entries_by_or' => $this->fetchLroEntriesByOr($state, $consumerForBalance),
            ],
        ]);
    }

    private function resolveConsumerByAccountNumber(?string $accountNumber): ?ConsumerZone
    {
        $acc = strtoupper(trim((string) $accountNumber));
        if ($acc === '') {
            return null;
        }

        $norm = str_replace('-', '', $acc);

        return ConsumerZone::query()->where(function ($q) use ($acc, $norm) {
            $q->where(mr_col('account_no'), $acc)
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$norm])
                ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [$acc]);
        })->first();
    }

    private function resolveConsumerForLookup(BillingLookupState $state): ?ConsumerZone
    {
        $consumer = $this->resolveConsumerByAccountNumber($state->reading->account_number ?? null);
        if (!$consumer && !empty($state->reading->schedule_id)) {
            $czId = DB::table(mr_col('meter_reading_schedules'))->where(mr_col('id'), $state->reading->schedule_id)->value(mr_col('consumer_zone_id'));
            if ($czId) {
                $consumer = ConsumerZone::find($czId);
            }
        }

        return $consumer;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLookupAccountData(BillingLookupState $state, ?ConsumerZone $consumer): array
    {
        $reader = null;
        if ($state->reading->reader_id) {
            $reader = User::find($state->reading->reader_id);
        }

        $consumerForAddress = $consumer ?? $this->resolveConsumerByAccountNumber(
            $state->reading->account_number ?? $state->accountNumber
        );

        return [
            'number' => $state->reading->account_number ?? $state->accountNumber,
            'name' => $state->reading->account_name ?? $state->reading->schedule_account_name ?? '',
            'zone' => $state->reading->zone ?? '',
            'category' => $state->reading->category ?? '',
            'consumer_category' => $consumerForAddress?->category_code,
            'address' => $consumerForAddress?->address ?? ($state->reading->address ?? ''),
            'bill_disc_percent' => $consumerForAddress?->bill_disc_percent,
            'osca_id_no' => $consumerForAddress?->osca_id_no,
            'bill_disc_updated_at' => !empty($consumerForAddress?->bill_disc_updated_at)
                ? Carbon::parse($consumerForAddress->bill_disc_updated_at)->format('Y-m-d')
                : null,
            'meter_number' => $state->reading->meter_number ?? null,
            'reader_id' => $state->reading->reader_id,
            'reader_name' => $reader ? $this->formatName($reader) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLookupBillingData(BillingLookupState $state, ?ConsumerZone $consumer): array
    {
        $billMonthFromSchedule = $state->reading->bill_month ? Carbon::parse($state->reading->bill_month) : null;
        $state->billMonthDate = $state->billMonthDate ?? ($billMonthFromSchedule ? $billMonthFromSchedule->copy()->startOfMonth() : null);

        $consumption = $state->reading->consumption ?? 0;
        $downloadedCurrentBill = isset($state->reading->downloaded_current_bill) && $state->reading->downloaded_current_bill !== null
            ? (float) $state->reading->downloaded_current_bill
            : null;
        $scheduleCurrentBill = isset($state->reading->schedule_current_bill) && $state->reading->schedule_current_bill !== null
            ? (float) $state->reading->schedule_current_bill
            : null;

        $storedCurrentBill = $downloadedCurrentBill ?? $scheduleCurrentBill ?? 0.0;
        $category = $state->reading->category ?? '';
        $meterMaintenanceCharge = 20.00;

        $currentBill = $storedCurrentBill;
        if ($currentBill <= 0 && $consumption > 0) {
            $currentBill = $this->calculateWaterBill($consumption, $category);
        }

        $penalty = $this->resolveLookupPenalty($state, $consumer);
        $ledgerOverrides = $this->applyLedgerBillingOverrides($state, $consumer);
        if ($ledgerOverrides['current_bill'] !== null) {
            $currentBill = $ledgerOverrides['current_bill'];
        }
        if ($ledgerOverrides['meter_maintenance_charge'] !== null) {
            $meterMaintenanceCharge = $ledgerOverrides['meter_maintenance_charge'];
        }

        return [
            'bill_month' => $state->billMonthDate?->format('Y-m'),
            'bill_month_input' => $state->billMonthDate?->format('m-Y'),
            'bill_month_display' => $state->billMonthDate?->format('F Y'),
            'bill_date' => $state->reading->bill_date ? Carbon::parse($state->reading->bill_date)->format('Y-m-d') : null,
            'due_date' => $state->reading->due_date ? Carbon::parse($state->reading->due_date)->format('Y-m-d') : null,
            'disconnection_date' => $state->reading->disconnection_date ? Carbon::parse($state->reading->disconnection_date)->format('Y-m-d') : null,
            'previous_reading' => $state->reading->previous_reading ?? 0,
            'previous_reading_date' => $state->reading->previous_reading_date ? Carbon::parse($state->reading->previous_reading_date)->format('Y-m-d') : null,
            'current_reading' => $state->reading->current_reading ?? null,
            'reading_date' => $state->reading->reading_date ? Carbon::parse($state->reading->reading_date)->format('Y-m-d') : null,
            'consumption' => $consumption,
            'current_bill' => round($currentBill, 2),
            'meter_maintenance_charge' => $meterMaintenanceCharge,
            'penalty' => $penalty,
            'arrears' => $state->reading->arrears !== null ? (float) $state->reading->arrears : 0.0,
            'total_amount' => $state->reading->total_amount !== null ? (float) $state->reading->total_amount : 0.0,
            'sedr_number' => $state->reading->sedr_number ?? null,
        ];
    }

    private function resolveLookupPenalty(BillingLookupState $state, ?ConsumerZone $consumer): float
    {
        $penalty = 0.0;

        $penaltiesHasDownloadedReadingId = Schema::hasColumn('penalties', 'downloaded_reading_id');
        if ($consumer && (!empty($state->reading->schedule_id) || ($penaltiesHasDownloadedReadingId && !empty($state->reading->downloaded_id)))) {
            $penaltyQuery = Penalty::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                ->where(function ($q) use ($state, $penaltiesHasDownloadedReadingId) {
                    if (!empty($state->reading->schedule_id)) {
                        $q->where(mr_col('schedule_id'), $state->reading->schedule_id);
                    }
                    if ($penaltiesHasDownloadedReadingId && !empty($state->reading->downloaded_id)) {
                        if (!empty($state->reading->schedule_id)) {
                            $q->orWhere(mr_col('downloaded_reading_id'), $state->reading->downloaded_id);
                        } else {
                            $q->where(mr_col('downloaded_reading_id'), $state->reading->downloaded_id);
                        }
                    }
                });
            foreach ($penaltyQuery->get() as $rec) {
                if ($rec->penalty_amount !== null) {
                    $penalty += (float) $rec->penalty_amount;
                }
            }
            $penalty = round($penalty, 2);
        }

        $billMonthForPenalty = $state->billMonthDate ?? ($state->reading->bill_month ? Carbon::parse($state->reading->bill_month)->startOfMonth() : null);
        if (!$billMonthForPenalty || !$consumer) {
            return $penalty;
        }

        $monthStart = $billMonthForPenalty->copy()->startOfMonth();
        $monthEnd = $billMonthForPenalty->copy()->endOfMonth();
        $ledgerPenaltyQuery = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->where(mr_col('trans'), 'PENALTY')
            ->whereBetween(mr_col('date'), [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
        if (!empty($state->reading->schedule_id) || !empty($state->reading->downloaded_id)) {
            $ledgerPenaltyQuery->where(function ($q) use ($state) {
                if (!empty($state->reading->schedule_id)) {
                    $q->where(mr_col('schedule_id'), $state->reading->schedule_id);
                }
                if (!empty($state->reading->downloaded_id)) {
                    if (!empty($state->reading->schedule_id)) {
                        $q->orWhere(mr_col('downloaded_reading_id'), $state->reading->downloaded_id);
                    } else {
                        $q->where(mr_col('downloaded_reading_id'), $state->reading->downloaded_id);
                    }
                }
            });
        }

        $ledgerPenaltySum = $ledgerPenaltyQuery->get()->sum(function ($row) {
            $p = (float) ($row->penalty ?? 0);
            $d = (float) ($row->debit ?? 0);

            return $p > 0 ? $p : $d;
        });
        if ($ledgerPenaltySum > 0) {
            return round($ledgerPenaltySum, 2);
        }

        $ledgerPenaltySum = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->where(mr_col('trans'), 'PENALTY')
            ->whereBetween(mr_col('date'), [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->get()
            ->sum(function ($row) {
                $p = (float) ($row->penalty ?? 0);
                $d = (float) ($row->debit ?? 0);

                return $p > 0 ? $p : $d;
            });
        if ($ledgerPenaltySum > 0) {
            return round($ledgerPenaltySum, 2);
        }

        return $penalty;
    }

    /**
     * @return array{current_bill: float|null, meter_maintenance_charge: float|null}
     */
    private function applyLedgerBillingOverrides(BillingLookupState $state, ?ConsumerZone $consumer): array
    {
        $billMonthForLedger = $state->billMonthDate ?? ($state->reading->bill_month ? Carbon::parse($state->reading->bill_month)->startOfMonth() : null);
        if (!$consumer || !$billMonthForLedger) {
            return ['current_bill' => null, 'meter_maintenance_charge' => null];
        }

        $monthStart = $billMonthForLedger->copy()->startOfMonth();
        $monthEnd = $billMonthForLedger->copy()->endOfMonth();
        $billingEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
            ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
            ->whereBetween(mr_col('date'), [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->when(!empty($state->reading->schedule_id), function ($q) use ($state) {
                $q->where(mr_col('schedule_id'), $state->reading->schedule_id);
            })
            ->orderBy('date', 'desc')
            ->first();

        if (!$billingEntry) {
            return ['current_bill' => null, 'meter_maintenance_charge' => null];
        }

        $currentBill = null;
        $meterMaintenanceCharge = null;
        $ledgerBillAmount = (float) ($billingEntry->billamount ?? 0);
        $ledgerOthers = (float) ($billingEntry->others ?? 0);
        if ($ledgerBillAmount > 0) {
            $currentBill = round($ledgerBillAmount, 2);
        }
        if ($ledgerOthers >= 0) {
            $meterMaintenanceCharge = round($ledgerOthers, 2);
        }

        return [
            'current_bill' => $currentBill,
            'meter_maintenance_charge' => $meterMaintenanceCharge,
        ];
    }

    /**
     * @param  array<string, mixed>  $billingData
     * @return array<string, mixed>
     */
    private function buildLookupPaymentData(BillingLookupState $state, ?ConsumerZone $consumer, array $billingData): array
    {
        $paymentAmount = $state->reading->payment_amount !== null
            ? (float) $state->reading->payment_amount
            : ($state->reading->total_amount !== null ? (float) $state->reading->total_amount : ($billingData['current_bill'] + $billingData['arrears']));

        $paymentRow = ConsumerPayment::query()->where(mr_col('reading_id'), $state->reading->downloaded_id ?? null)
            ->whereNotNull(mr_col('paid_at'))
            ->orderBy(mr_col('paid_at'), 'desc')
            ->first();

        $ledgerPaymentRow = null;
        if (!$paymentRow || !$paymentRow->paid_at) {
            $ledgerPaymentRow = ConsumerLedger::query()->where(mr_col('downloaded_reading_id'), $state->reading->downloaded_id ?? 0)
                ->where(mr_col('trans'), 'PAYMENT')
                ->whereNotNull(mr_col('paid_at'))
                ->orderBy(mr_col('paid_at'), 'desc')
                ->first();
            if (!$ledgerPaymentRow && $consumer && !empty($state->reading->schedule_id)) {
                $ledgerPaymentRow = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                    ->where(mr_col('schedule_id'), $state->reading->schedule_id)
                    ->where(mr_col('trans'), 'PAYMENT')
                    ->whereNotNull(mr_col('paid_at'))
                    ->where(function ($q) {
                        $q->whereNull(mr_col('reference'))->orWhereRaw("reference NOT LIKE '%-SC'");
                    })
                    ->orderBy(mr_col('paid_at'), 'desc')
                    ->first();
                if (!$ledgerPaymentRow) {
                    $ledgerPaymentRow = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumer->id)
                        ->where(mr_col('schedule_id'), $state->reading->schedule_id)
                        ->where(mr_col('trans'), 'PAYMENT')
                        ->whereNotNull(mr_col('paid_at'))
                        ->orderBy(mr_col('paid_at'), 'desc')
                        ->first();
                }
            }
        }

        $isPaid = ($paymentRow && $paymentRow->paid_at) || ($ledgerPaymentRow && $ledgerPaymentRow->paid_at);
        $paidAtSource = $paymentRow && $paymentRow->paid_at
            ? $paymentRow->paid_at
            : ($ledgerPaymentRow && $ledgerPaymentRow->paid_at ? $ledgerPaymentRow->paid_at : null);

        if ($isPaid && $paidAtSource) {
            $paymentData = [
                'amount' => $paymentRow ? round((float) ($paymentRow->payment_amount ?? 0), 2) : (isset($ledgerPaymentRow) ? round((float) ($ledgerPaymentRow->credit ?? 0), 2) : round($paymentAmount, 2)),
                'tendered' => $paymentRow ? (float) ($paymentRow->amount_tendered ?? 0) : 0.0,
                'change' => $paymentRow ? (float) ($paymentRow->change_amount ?? 0) : 0.0,
                'method' => $paymentRow->payment_method ?? $state->reading->payment_method ?? null,
                'remarks' => $paymentRow->remarks ?? $state->reading->payment_remarks ?? $state->reading->reader_notes ?? null,
                'reference' => $paymentRow->or_number ?? (isset($ledgerPaymentRow) ? $ledgerPaymentRow->reference : null) ?? $state->reading->payment_reference ?? null,
                'status' => 'paid',
                'paid_at' => Carbon::parse($paidAtSource)->format('Y-m-d H:i:s'),
            ];
            if ($paymentRow) {
                $paymentData['current_bill'] = round((float) ($paymentRow->current_bill ?? 0), 2);
                $paymentData['penalty'] = round((float) ($paymentRow->penalty ?? 0), 2);
                $paymentData['meter_maintenance'] = round((float) ($paymentRow->meter_maintenance ?? 0), 2);
                $paymentData['arrears_cy'] = round((float) ($paymentRow->arrears_cy ?? 0), 2);
                $paymentData['arrears_py'] = round((float) ($paymentRow->arrears_py ?? 0), 2);
                $paymentData['advances'] = round((float) ($paymentRow->advances ?? 0), 2);
                $paymentData['senior_citizen_discount'] = round((float) ($paymentRow->senior_citizen_discount ?? 0), 2);
                $paymentData['others'] = round((float) ($paymentRow->others ?? 0), 2);
            }
        } else {
            $paymentData = [
                'amount' => round($paymentAmount, 2),
                'tendered' => $state->reading->amount_tendered !== null ? (float) $state->reading->amount_tendered : 0.0,
                'change' => $state->reading->change_amount !== null ? (float) $state->reading->change_amount : 0.0,
                'method' => $state->reading->payment_method ?? null,
                'remarks' => $state->reading->payment_remarks ?? $state->reading->reader_notes ?? null,
                'reference' => $state->reading->payment_reference ?? null,
                'status' => 'unpaid',
                'paid_at' => null,
            ];
        }

        if ($state->orNumberInput !== '' && $state->orLookupPayment) {
            $paymentData['status'] = 'paid';
            $paymentData['reference'] = $state->orLookupPayment->or_number ?? $state->orNumberInput;
            $paymentData['amount'] = round((float) ($state->orLookupPayment->payment_amount ?? $paymentData['amount'] ?? 0), 2);
            $paymentData['tendered'] = (float) ($state->orLookupPayment->amount_tendered ?? $paymentData['tendered'] ?? 0);
            $paymentData['change'] = (float) ($state->orLookupPayment->change_amount ?? $paymentData['change'] ?? 0);
            $paymentData['method'] = $state->orLookupPayment->payment_method ?? ($paymentData['method'] ?? null);
            $paymentData['remarks'] = $state->orLookupPayment->remarks ?? ($paymentData['remarks'] ?? null);
            $paymentData['paid_at'] = $state->orLookupPayment->paid_at
                ? Carbon::parse($state->orLookupPayment->paid_at)->format('Y-m-d H:i:s')
                : ($paymentData['paid_at'] ?? null);

            $paymentData['current_bill'] = round((float) ($state->orLookupPayment->current_bill ?? 0), 2);
            $paymentData['penalty'] = round((float) ($state->orLookupPayment->penalty ?? 0), 2);
            $paymentData['meter_maintenance'] = round((float) ($state->orLookupPayment->meter_maintenance ?? 0), 2);
            $paymentData['arrears_cy'] = round((float) ($state->orLookupPayment->arrears_cy ?? 0), 2);
            $paymentData['arrears_py'] = round((float) ($state->orLookupPayment->arrears_py ?? 0), 2);
            $paymentData['advances'] = round((float) ($state->orLookupPayment->advances ?? 0), 2);
            $paymentData['senior_citizen_discount'] = round((float) ($state->orLookupPayment->senior_citizen_discount ?? 0), 2);
            $paymentData['others'] = round((float) ($state->orLookupPayment->others ?? 0), 2);
        }

        return $paymentData;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLookupDownloadedReadingData(BillingLookupState $state): array
    {
        return [
            'id' => $state->reading->downloaded_id,
            'schedule_id' => $state->reading->schedule_id,
            'reader_id' => $state->reading->reader_id,
            'reader_notes' => $state->reading->reader_notes ?? null,
            'completed_at' => (is_object($state->reading) && property_exists($state->reading, 'completed_at') && $state->reading->completed_at)
                ? Carbon::parse($state->reading->completed_at)->format('Y-m-d H:i:s')
                : null,
            'created_at' => $state->reading->downloaded_created_at ? Carbon::parse($state->reading->downloaded_created_at)->format('Y-m-d H:i:s') : null,
            'updated_at' => $state->reading->downloaded_updated_at ? Carbon::parse($state->reading->downloaded_updated_at)->format('Y-m-d H:i:s') : null,
        ];
    }

    private function resolveLookupCurrentBalance(?string $accountNumber, ?ConsumerZone $consumer): float
    {
        if (!$consumer) {
            return 0.00;
        }

        try {
            $ledgerRequest = new Request();
            $ledgerRequest->merge([
                'account_no' => $accountNumber,
                'year' => '',
            ]);

            $ledgerController = new ConsumerLedgerController();
            $ledgerResponse = $ledgerController->getLedger($ledgerRequest);
            $ledgerData = json_decode($ledgerResponse->getContent(), true);

            if (isset($ledgerData['summary']['balance'])) {
                return (float) $ledgerData['summary']['balance'];
            }
        } catch (Exception $e) {
            Log::error('Error getting balance from ledger: ' . $e->getMessage());
        }

        return 0.00;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLroEntriesByOr(BillingLookupState $state, ?ConsumerZone $consumer): array
    {
        if ($state->orNumberInput === '') {
            return [];
        }

        $orRemarks = 'Payment OR#' . trim($state->orNumberInput);
        $consumerZoneId = $consumer?->id;

        $baseLroQuery = LROLedger::with('consumerZone')
            ->where(mr_col('remarks'), $orRemarks)
            ->orderBy('date', 'asc')
            ->orderBy(mr_col('id'), 'asc');

        $candidateRows = $consumerZoneId
            ? (clone $baseLroQuery)->forConsumerZone($consumerZoneId)->get()
            : (clone $baseLroQuery)->get();

        return $candidateRows->map(function ($row) {
            return [
                'id' => $row->id,
                'type' => $row->type,
                'date' => $row->date,
                'account' => $row->account_no,
                'name' => $row->account_name,
                'bam_no' => $row->bam_no,
                'amount' => (float) ($row->amount ?? 0),
                'ar_type' => $row->ar_type,
                'acct_code' => $row->acct_code,
                'reference' => $row->reference,
                'remarks' => $row->remarks,
                'status' => $row->status,
            ];
        })->values()->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLookupSundries(?ConsumerZone $consumer): array
    {
        $consumerZoneId = $consumer?->id;
        if (!$consumerZoneId) {
            return [];
        }

        try {
            $sundries = [];
            $dmRows = LROLedger::forConsumerZone($consumerZoneId)
                ->whereRaw("UPPER(TRIM(COALESCE(ledger, ''))) = 'LRO'")
                ->where(mr_col('type'), 'DM')
                ->where(mr_col('status'), 'Approved')
                ->orderBy('date', 'asc')
                ->orderBy(mr_col('id'), 'asc')
                ->get(['id', 'ledger', 'acct_code', 'bam_no', 'amount', 'status']);

            foreach ($dmRows as $row) {
                $dmAmount = (float) ($row->amount ?? 0);
                $bamRef = $row->bam_no;
                $chargeId = (int) $row->id;

                $paidAmount = SundryLedgerRemarks::paidAmountForCharge($consumerZoneId, $chargeId);
                if ($paidAmount <= 0) {
                    $paidAmount = (float) LROLedger::forConsumerZone($consumerZoneId)
                        ->where(mr_col('type'), 'CM')
                        ->where(mr_col('acct_code'), $row->acct_code)
                        ->where(mr_col('bam_no'), $bamRef)
                        ->sum(mr_col('amount'));
                }

                $remaining = round($dmAmount - $paidAmount, 2);
                if ($remaining <= 0) {
                    continue;
                }

                $sundries[] = [
                    'id' => $chargeId,
                    'lro_ledger_id' => $chargeId,
                    'ledger' => $row->ledger ?? 'LRO',
                    'acct_code' => $row->acct_code,
                    'bam_no' => $bamRef,
                    'amount' => $remaining,
                    'name' => $consumer->account_name ?? '',
                ];

                if (count($sundries) >= 4) {
                    break;
                }
            }

            return $sundries;
        } catch (Throwable $e) {
            Log::warning('lookupBillingRecord: LRO ledger sundries fetch failed', ['message' => $e->getMessage()]);

            return [];
        }
    }
    private function downloadedReadingsHasCompletedAt(): bool
    {
        return Schema::hasColumn('downloaded_readings', 'completed_at');
    }

    private function applyDownloadedReadingConsumerJoin($query, string $drAlias = 'dr', string $mrsAlias = 'mrs', string $czAlias = 'cz')
    {
        $baseQuery = $query instanceof Builder
            ? $query->getQuery()
            : $query;

        $joins = $baseQuery->joins ?? [];
        $joined = collect($joins)->map(fn ($j) => (string) ($j->table ?? ''))->implode(mr_col(' '));

        if (!str_contains($joined, "{$mrsAlias}")) {
            $query->leftJoin(mr_col("meter_reading_schedules as {$mrsAlias}"), mr_col("{$drAlias}.schedule_id"), '=', mr_col("{$mrsAlias}.id"));
        }

        if (!str_contains($joined, "{$czAlias}")) {
            $query->leftJoin(mr_col("consumer_zone as {$czAlias}"), function ($join) use ($drAlias, $mrsAlias, $czAlias) {
                $join->on("{$czAlias}.id", '=', "{$drAlias}.consumer_zone_id")
                    ->orOn("{$czAlias}.id", '=', "{$mrsAlias}.consumer_zone_id");
            });
        }

        return $query;
    }

    private function downloadedReadingBaseSelectColumns(): array
    {
        $cols = [
            'dr.id as downloaded_id',
            'dr.schedule_id',
            'dr.reader_id',
            'dr.consumer_zone_id',
            'cz.account_no as account_number',
            'cz.account_name',
            'cz.zone_code as zone',
            'dr.previous_reading',
            'dr.current_reading',
            'dr.consumption',
            'dr.current_bill as downloaded_current_bill',
            'dr.reading_date',
            'dr.status',
            'dr.reader_notes',
        ];

        if ($this->downloadedReadingsHasCompletedAt()) {
            $cols[] = 'dr.completed_at';
        }

        return array_merge($cols, [
            'cp.payment_method',
            'cp.payment_amount',
            'cp.amount_tendered',
            'cp.change_amount',
            'cp.or_number as official_receipt_number',
            'cp.remarks as payment_remarks',
            'cp.paid_at',
            'dr.created_at as downloaded_created_at',
            'dr.updated_at as downloaded_updated_at',
        ]);
    }

    private function applyDownloadedReadingRecencyOrder($query)
    {
        $query->orderByDesc(mr_col('dr.reading_date'));
        if ($this->downloadedReadingsHasCompletedAt()) {
            $query->orderByDesc(mr_col('dr.completed_at'));
        }

        return $query->orderByDesc(mr_col('dr.created_at'));
    }

    private function resolvePaymentConsumer(?ConsumerPayment $payment): ?ConsumerZone
    {
        if (!$payment) {
            return null;
        }

        $consumerZoneId = $payment->consumer_zone_id ?? $payment->consumer_id;
        if ($consumerZoneId) {
            return ConsumerZone::find($consumerZoneId);
        }

        return null;
    }

    private function formatName(User $user): string
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

    private function calculateWaterBill(float $consumption, ?string $category = null, ?string $rateCode = null): float
    {
        return app(WaterBillingService::class)->calculate($consumption, $category, $rateCode);
    }

    private function resolveBillMonth(string $billMonthInput): ?Carbon
    {
        $formats = [
            'm-Y',
            'm/Y',
            'Y-m',
            'Y/m',
            'm/d/Y',
            'd/m/Y',
            'Y-m-d',
            'Y/m/d',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $billMonthInput);

                return $parsed->startOfMonth();
            } catch (Throwable $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($billMonthInput)->startOfMonth();
        } catch (Throwable $e) {
            return null;
        }
    }
}
