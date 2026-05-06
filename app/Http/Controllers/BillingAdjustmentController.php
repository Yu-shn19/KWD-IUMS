<?php

namespace App\Http\Controllers;

use App\Models\BillingAdjustment;
use App\Models\ConsumerLedger;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZoneOne;
use App\Models\LROLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BillingAdjustmentController extends Controller
{
    /**
     * Display the billing adjustment form and list of all transactions
     */
    public function index()
    {
        $billingAdjustments = BillingAdjustment::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // LRO-only BAM entries stored in legacy lro_ledger table
        // Hide orphan payment CM rows when their OR no longer exists in consumer_payments.
        $lroEntries = $this->getVisibleLroEntries();

        $previewBamNo = $this->generateBamNumber();

        return view('transaction.billing_adjustment', compact('billingAdjustments', 'lroEntries', 'previewBamNo'));
    }

    /**
     * Show the form for editing a billing adjustment
     */
    public function edit($id)
    {
        $billingAdjustment = BillingAdjustment::with(['consumerLedgers', 'consumerZone'])->findOrFail($id);
        $billingAdjustments = BillingAdjustment::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $lroEntries = $this->getVisibleLroEntries();
        $previewBamNo = $this->generateBamNumber();
        return view('transaction.billing_adjustment', compact('billingAdjustment', 'billingAdjustments', 'lroEntries', 'previewBamNo'));
    }

    /**
     * Get billing adjustment data for editing
     */
    public function show($id)
    {
        $billingAdjustment = BillingAdjustment::with(['consumerLedgers', 'consumerZone'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $billingAdjustment
        ]);
    }

    /**
     * Store a new billing adjustment
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:CM,DM',
            'type_ar' => 'required|in:AR,LRO',
            'date' => 'required|date',
            'account_no' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'acct_code' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'current_bill' => 'nullable|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'arrears' => 'nullable|numeric|min:0',
            'sc_discount' => 'nullable|numeric|min:0',
            'loans' => 'nullable|numeric|min:0',
            'others' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'status' => 'required|in:Pending,Approved,Cancelled',
            'connect_reading' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Find consumer zone by account number
            $consumerZone = ConsumerZoneOne::where('account_no', $request->account_no)->first();
            
            if (!$consumerZone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found: ' . $request->account_no
                ], 404);
            }

            // Generate auto BAM number (format: BAM-YYYYMMDD-XXXXX)
            $bamNo = $this->generateBamNumber();

            // Generate auto reference number (5 digits like 12689)
            $referenceNumber = $this->generateReferenceNumber();

            // Parse date (handle multiple formats)
            try {
                $dateCarbon = Carbon::createFromFormat('m/d/Y', $request->date);
            } catch (\Exception $e) {
                try {
                    $dateCarbon = Carbon::parse($request->date);
                } catch (\Exception $e2) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format. Please use MM/DD/YYYY format.'
                    ], 422);
                }
            }
            $date = $dateCarbon->format('Y-m-d');
            $dateTime = $dateCarbon->format('Y-m-d H:i:s');

            // Get latest balance before this transaction
            $latestLedger = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                ->where('txtime', '<', $dateTime)
                ->orderBy('txtime', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $latestLedger ? (float)($latestLedger->balance ?? 0) : (float)($consumerZone->balance ?? 0);

            // Calculate new balance (credit decreases balance for CM, debit increases for DM)
            $amount = (float)$request->amount;
            if ($request->type === 'CM') {
                // Credit Memo: credit decreases balance
                $newBalance = round($previousBalance - $amount, 2);
                $debit = 0;
                $credit = $amount;
            } else {
                // Debit Memo: debit increases balance
                $newBalance = round($previousBalance + $amount, 2);
                $debit = $amount;
                $credit = 0;
            }

            // Create billing adjustment record
            $billingAdjustment = BillingAdjustment::create([
                'type' => $request->type,
                'type_ar' => $request->type_ar,
                'date' => $date,
                'bam_no' => $bamNo,
                'account_no' => $request->account_no,
                'consumer_zone_id' => $consumerZone->id,
                'amount' => $amount,
                'acct_code' => $request->acct_code,
                'reference' => $request->reference ?: $referenceNumber,
                'current_bill' => (float)($request->current_bill ?? 0),
                'penalty' => (float)($request->penalty ?? 0),
                'arrears' => (float)($request->arrears ?? 0),
                'sc_discount' => (float)($request->sc_discount ?? 0),
                'loans' => (float)($request->loans ?? 0),
                'others' => (float)($request->others ?? 0),
                'remarks' => $request->remarks,
                'status' => $request->status,
                'connect_reading' => (int)($request->connect_reading ?? 0),
                'username' => auth()->user()->name ?? 'SYSTEM',
            ]);

            // Create consumer ledger entry
            $ledger = ConsumerLedger::create([
                'consumer_zone_id' => $consumerZone->id,
                'billing_adjustment_id' => $billingAdjustment->id,
                'trans' => $request->type, // Use actual type: CM or DM
                'date' => $date,
                'due_date' => null,
                'reference' => $referenceNumber,
                'reading' => 0,
                'volume' => 0,
                'billamount' => 0,
                'penalty' => 0,
                'others' => 0,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $newBalance,
                'username' => auth()->user()->name ?? 'SYSTEM',
                'txtime' => $dateTime,
            ]);

            // Update consumer zone balance
            $consumerZone->balance = $newBalance;
            $consumerZone->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment saved successfully',
                'bam_no' => $bamNo,
                'reference' => $referenceNumber,
                'billing_adjustment_id' => $billingAdjustment->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving billing adjustment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving billing adjustment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing AR billing adjustment from the new UI (AJAX).
     * If the user switches the ledger from AR → LRO, or sets status to Posted (paid),
     * removes the consumer_ledger and billing_adjustments record and creates a fresh lro_ledger entry.
     * Otherwise maps new-UI field names to the existing update() implementation.
     */
    public function updateFromNewUi(Request $request, $id)
    {
        $ar = strtoupper((string) $request->input('ar', 'AR'));
        $status = strtoupper((string) $request->input('status', 'Pending'));

        // Prevent silent AR -> LRO migration when user sets status to Paid/Posted.
        // Moving to LRO must be explicit via ledger selection.
        if ($ar !== 'LRO' && $status === 'POSTED') {
            return response()->json([
                'success' => false,
                'message' => 'Paid status is only allowed in LRO ledger. Set Ledger to LRO to continue.',
            ], 422);
        }

        // ── Explicit AR → LRO switch ─────────────────────────────────────────────
        if ($ar === 'LRO') {
            try {
                DB::beginTransaction();

                // Remove the consumer_ledger row linked to this billing_adjustment
                ConsumerLedger::where('billing_adjustment_id', $id)->delete();

                // Remove the billing_adjustment record itself
                BillingAdjustment::where('id', $id)->delete();

                $type = $request->input('type', 'CM');
                if (!in_array($type, ['CM', 'DM'], true)) {
                    $type = 'CM';
                }

                $accountNo = trim((string) $request->input('account', ''));
                $consumerZone = $this->findConsumerForLroAccount($accountNo);

                // Create a new lro_ledger entry with the submitted data (LRO = paid/posted)
                $payload = [
                    'type'            => $type,
                    'date'            => $request->input('date') ?: null,
                    'account'         => $accountNo !== '' ? $accountNo : null,
                    'name'            => $request->input('account_name') ?? $request->input('name'),
                    'bam_no'          => $request->input('bam_no'),
                    'amount'          => (float)($request->input('amount', 0)),
                    'ar_type'         => 'LRO',
                    'acct_code'       => $request->input('acct_code'),
                    'reference'       => $request->input('reference'),
                    'remarks'         => $request->input('remarks'),
                    'status'          => $status === 'POSTED' ? 'Posted' : $request->input('status', 'Pending'),
                    'correct_reading' => (float)($request->input('correct_reading', 0)),
                ];
                if ($this->lroLedgerHasConsumerZoneIdColumn()) {
                    $payload['consumer_zone_id'] = $consumerZone?->id;
                }
                $entry = LROLedger::create($payload);

                DB::commit();

                $message = $status === 'POSTED'
                    ? 'Marked as paid; record moved to LRO ledger.'
                    : 'Moved to LRO ledger successfully.';

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data'    => ['id' => $entry->id, 'bam_no' => $entry->bam_no],
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('updateFromNewUi AR→LRO/Posted failed', ['id' => $id, 'error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
            }
        }

        // ── Normal AR update ──────────────────────────────────────────────────────
        $mapped = $request->all();
        $mapped['type_ar'] = 'AR';
        $mapped['account_no'] = $request->input('account');

        $type = $request->input('type', 'CM');
        if (!in_array($type, ['CM', 'DM'], true)) {
            $type = 'CM';
        }
        $mapped['type'] = $type;

        $mapped['current_bill']    = $mapped['current_bill']    ?? 0;
        $mapped['penalty']         = $mapped['penalty']         ?? 0;
        $mapped['arrears']         = $mapped['arrears']         ?? 0;
        $mapped['sc_discount']     = $mapped['sc_discount']     ?? 0;
        $mapped['loans']           = $mapped['loans']           ?? 0;
        $mapped['others']          = $mapped['others']          ?? 0;
        $mapped['connect_reading'] = $mapped['correct_reading'] ?? 0;

        $newRequest = new Request($mapped);
        return $this->update($newRequest, $id);
    }

    /**
     * Show the form for editing an LRO billing adjustment
     */
    public function editLro($id)
    {
        $lroEntry = LROLedger::findOrFail($id);
        $billingAdjustments = BillingAdjustment::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $lroEntries = $this->getVisibleLroEntries();
        $previewBamNo = $lroEntry->bam_no ?? $this->generateBamNumber();
        return view('transaction.billing_adjustment', compact('lroEntry', 'billingAdjustments', 'lroEntries', 'previewBamNo'));
    }

    /**
     * Load LRO entries for list display, excluding orphan payment CM rows.
     * A payment CM row is considered orphan when remarks = "Payment OR#..." but OR no longer exists in consumer_payments.
     */
    private function getVisibleLroEntries()
    {
        $entries = LROLedger::orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Hard-clean duplicate posted CM payment rows in lro_ledger.
        // Keep only the newest row per payment signature; delete older duplicates.
        $paymentCmRows = $entries->filter(function ($row) {
            $type = strtoupper((string) ($row->type ?? ''));
            $status = strtoupper((string) ($row->status ?? ''));
            $remarks = trim((string) ($row->remarks ?? ''));
            return $type === 'CM' && $status === 'POSTED' && str_starts_with($remarks, 'Payment OR#');
        });

        $duplicateIdsToDelete = collect();
        $grouped = $paymentCmRows->groupBy(function ($row) {
            return implode('|', [
                trim((string) ($row->remarks ?? '')),
                trim((string) ($row->bam_no ?? '')),
                trim((string) ($row->acct_code ?? '')),
                trim((string) ($row->account ?? '')),
                trim((string) ($row->name ?? '')),
                number_format((float) ($row->amount ?? 0), 2, '.', ''),
            ]);
        });

        foreach ($grouped as $rows) {
            if ($rows->count() <= 1) {
                continue;
            }

            // Keep latest id, delete the rest.
            $ids = $rows->pluck('id')->sortDesc()->values();
            $idsToDelete = $ids->slice(1);
            if ($idsToDelete->isNotEmpty()) {
                $duplicateIdsToDelete = $duplicateIdsToDelete->merge($idsToDelete);
            }
        }

        if ($duplicateIdsToDelete->isNotEmpty()) {
            LROLedger::whereIn('id', $duplicateIdsToDelete->unique()->values()->all())->delete();
            $entries = $entries->reject(function ($row) use ($duplicateIdsToDelete) {
                return $duplicateIdsToDelete->contains($row->id);
            })->values();
        }

        $existingOrSet = ConsumerPayment::whereNotNull('or_number')
            ->pluck('or_number')
            ->map(function ($or) {
                return trim((string) $or);
            })
            ->filter()
            ->flip();

        return $entries->filter(function ($row) use ($existingOrSet) {
            $type = strtoupper((string) ($row->type ?? ''));
            $status = strtoupper((string) ($row->status ?? ''));
            $remarks = trim((string) ($row->remarks ?? ''));

            // Only inspect posted CM payment rows generated from payment flow.
            if ($type !== 'CM' || $status !== 'POSTED') {
                return true;
            }
            if (!str_starts_with($remarks, 'Payment OR#')) {
                return true;
            }

            $orNumber = trim((string) preg_replace('/^Payment OR#/', '', $remarks));
            if ($orNumber === '') {
                return false;
            }

            return $existingOrSet->has($orNumber);
        })->values();
    }

    /**
     * Update an existing LRO billing adjustment.
     * If the user switches the ledger from LRO → AR, removes the lro_ledger row first,
     * then creates a new billing_adjustments + consumer_ledgers entry (same order as AR→LRO).
     * Otherwise updates the lro_ledger row in place.
     */
    public function updateLro(Request $request, $id)
    {
        $entry = LROLedger::findOrFail($id);

        $ar = strtoupper((string) $request->input('ar', 'LRO'));

        // ── LRO → AR switch ──────────────────────────────────────────────────────
        if ($ar === 'AR') {
            try {
                DB::beginTransaction();

                $accountNo = $request->input('account');
                $consumerZone = ConsumerZoneOne::where('account_no', $accountNo)->first();

                if (!$consumerZone) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Account not found: ' . $accountNo], 404);
                }

                $type = $request->input('type', 'CM');
                if (!in_array($type, ['CM', 'DM'], true)) {
                    $type = 'CM';
                }

                $amount   = (float) $request->input('amount', 0);
                $dateRaw  = $request->input('date');
                try {
                    $dateCarbon = Carbon::parse($dateRaw);
                } catch (\Exception $e) {
                    $dateCarbon = Carbon::now();
                }
                $date     = $dateCarbon->format('Y-m-d');
                $dateTime = $dateCarbon->format('Y-m-d H:i:s');

                // Remove the old LRO row first (same order as AR→LRO: remove then create)
                $entry->delete();

                // Get latest consumer ledger balance
                $latestLedger    = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                    ->orderBy('txtime', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
                $previousBalance = $latestLedger ? (float)($latestLedger->balance ?? 0) : (float)($consumerZone->balance ?? 0);

                if ($type === 'CM') {
                    $newBalance = round($previousBalance - $amount, 2);
                    $debit      = 0;
                    $credit     = $amount;
                } else {
                    $newBalance = round($previousBalance + $amount, 2);
                    $debit      = $amount;
                    $credit     = 0;
                }

                $referenceNumber = $this->generateReferenceNumber();

                // Create billing_adjustments record
                $billingAdjustment = BillingAdjustment::create([
                    'type'            => $type,
                    'type_ar'         => 'AR',
                    'date'            => $date,
                    'bam_no'          => $request->input('bam_no') ?: $entry->bam_no,
                    'account_no'      => $accountNo,
                    'consumer_zone_id'=> $consumerZone->id,
                    'amount'          => $amount,
                    'acct_code'       => $request->input('acct_code'),
                    'reference'       => $request->input('reference') ?: $referenceNumber,
                    'current_bill'    => 0,
                    'penalty'         => 0,
                    'arrears'         => 0,
                    'sc_discount'     => 0,
                    'loans'           => 0,
                    'others'          => 0,
                    'remarks'         => $request->input('remarks'),
                    'status'          => $request->input('status', 'Pending'),
                    'connect_reading' => (int)($request->input('correct_reading', 0)),
                    'username'        => auth()->user()->name ?? 'SYSTEM',
                ]);

                // Create consumer_ledgers row
                ConsumerLedger::create([
                    'consumer_zone_id'      => $consumerZone->id,
                    'billing_adjustment_id' => $billingAdjustment->id,
                    'trans'                 => $type,
                    'date'                  => $date,
                    'due_date'              => null,
                    'reference'             => $referenceNumber,
                    'reading'               => 0,
                    'volume'                => 0,
                    'billamount'            => 0,
                    'penalty'               => 0,
                    'others'                => 0,
                    'debit'                 => $debit,
                    'credit'                => $credit,
                    'balance'               => $newBalance,
                    'username'              => auth()->user()->name ?? 'SYSTEM',
                    'txtime'                => $dateTime,
                ]);

                // Update consumer zone balance
                $consumerZone->balance = $newBalance;
                $consumerZone->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Moved to AR (Consumer Ledger) successfully.',
                    'data'    => ['id' => $billingAdjustment->id, 'bam_no' => $billingAdjustment->bam_no],
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('updateLro LRO→AR switch failed', ['id' => $id, 'error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
            }
        }

        // ── Normal LRO update ─────────────────────────────────────────────────────
        $validated = $request->validate([
            'type'            => ['nullable', 'string', 'max:20'],
            'date'            => ['nullable', 'date'],
            'account'         => ['nullable', 'string', 'max:50'],
            'account_name'    => ['nullable', 'string', 'max:255'],
            'bam_no'          => ['nullable', 'string', 'max:50'],
            'amount'          => ['nullable', 'numeric', 'min:0'],
            'acct_code'       => ['nullable', 'string', 'max:50'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'remarks'         => ['nullable', 'string'],
            'status'          => ['nullable', 'string', 'max:20'],
            'correct_reading' => ['nullable', 'numeric'],
        ]);

        $nextAccount = array_key_exists('account', $validated)
            ? trim((string) ($validated['account'] ?? ''))
            : trim((string) ($entry->account ?? ''));
        $consumerZone = $this->findConsumerForLroAccount($nextAccount);

        $payload = [
            'type'            => $validated['type']            ?? $entry->type,
            'date'            => !empty($validated['date'])    ? $validated['date'] : $entry->date,
            'account'         => array_key_exists('account', $validated) ? ($nextAccount !== '' ? $nextAccount : null) : $entry->account,
            'name'            => $validated['account_name']    ?? $entry->name,
            'bam_no'          => $validated['bam_no']          ?? $entry->bam_no,
            'amount'          => isset($validated['amount'])   ? (float) $validated['amount'] : $entry->amount,
            'acct_code'       => $validated['acct_code']       ?? $entry->acct_code,
            'reference'       => $validated['reference']       ?? $entry->reference,
            'remarks'         => $validated['remarks']         ?? $entry->remarks,
            'status'          => $validated['status']          ?? $entry->status,
            'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : $entry->correct_reading,
        ];
        if ($this->lroLedgerHasConsumerZoneIdColumn()) {
            $payload['consumer_zone_id'] = $consumerZone?->id ?? $entry->consumer_zone_id;
        }

        $entry->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'LRO billing adjustment updated successfully.',
            'data'    => ['bam_no' => $entry->bam_no],
        ]);
    }

    /**
     * Save from new Billing Adjustment UI (AJAX).
     * - If AR: use the existing billing adjustment implementation (store in consumer_ledgers + billing_adjustments).
     * - If LRO: use the LRO ledger implementation (store in lro_ledgers only).
     */
    public function saveFromNewUi(Request $request)
    {
        $ar = strtoupper((string) $request->input('ar', 'AR'));

        // LRO path: behave like LroLedgerController::store (new implementation)
        if ($ar === 'LRO') {
            $request->merge([
                'date' => $request->input('date') === '' ? null : $request->input('date'),
            ]);

            $validated = $request->validate([
                'type' => ['nullable', 'string', 'max:20'],
                'date' => ['nullable', 'date'],
                'account' => ['nullable', 'string', 'max:50'],
                'name' => ['nullable', 'string', 'max:255'],
                'account_name' => ['nullable', 'string', 'max:255'],
                'bam_no' => ['nullable', 'string', 'max:50'],
                'amount' => ['nullable', 'numeric', 'min:0'],
                'ar_type' => ['nullable', 'string', 'max:10'],
                'ar' => ['nullable', 'string', 'max:10'],
                'acct_code' => ['nullable', 'string', 'max:50'],
                'reference' => ['nullable', 'string', 'max:255'],
                'remarks' => ['nullable', 'string'],
                'status' => ['nullable', 'string', 'max:20'],
                'correct_reading' => ['nullable', 'numeric'],
            ]);

            $name = $validated['name'] ?? $validated['account_name'] ?? null;
            $arType = $validated['ar_type'] ?? $validated['ar'] ?? 'LRO';
            $accountNo = trim((string) ($validated['account'] ?? ''));
            $consumerZone = $this->findConsumerForLroAccount($accountNo);

            $payload = [
                'type' => $validated['type'] ?? 'CM',
                'date' => !empty($validated['date']) ? $validated['date'] : null,
                'account' => $accountNo !== '' ? $accountNo : null,
                'name' => $name,
                'bam_no' => $validated['bam_no'] ?? null,
                'amount' => (float) ($validated['amount'] ?? 0),
                'ar_type' => $arType,
                'acct_code' => $validated['acct_code'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $validated['status'] ?? 'Pending',
                'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : 0,
            ];
            if ($this->lroLedgerHasConsumerZoneIdColumn()) {
                $payload['consumer_zone_id'] = $consumerZone?->id;
            }

            $entry = LROLedger::create($payload);

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment saved.',
                'data' => ['id' => $entry->id],
            ]);
        }

        // AR path: adapt new UI payload to the existing store() implementation
        $mapped = $request->all();

        // Map fields to match the original implementation
        $mapped['type_ar'] = 'AR';
        $mapped['account_no'] = $request->input('account');

        // Ensure type is CM or DM
        $type = $request->input('type', 'CM');
        if (!in_array($type, ['CM', 'DM'], true)) {
            $type = 'CM';
        }
        $mapped['type'] = $type;

        // Provide required numeric fields with safe defaults
        $mapped['current_bill'] = 0;
        $mapped['penalty'] = 0;
        $mapped['arrears'] = 0;
        $mapped['sc_discount'] = 0;
        $mapped['loans'] = 0;
        $mapped['others'] = 0;
        $mapped['connect_reading'] = 0;

        // If date is HTML5 date (Y-m-d), let original store() handle parsing
        $newRequest = new Request($mapped);

        return $this->store($newRequest);
    }

    /**
     * Generate unique sequential BAM number starting at 13203.
     * Checks both billing_adjustments (AR) and lro_ledger tables to avoid duplicates.
     */
    private function generateBamNumber()
    {
        $lastAr = BillingAdjustment::whereNotNull('bam_no')
            ->whereRaw("bam_no REGEXP '^[0-9]+$'")
            ->orderByRaw('CAST(bam_no AS UNSIGNED) DESC')
            ->value('bam_no');

        $lastLro = LROLedger::whereNotNull('bam_no')
            ->whereRaw("bam_no REGEXP '^[0-9]+$'")
            ->orderByRaw('CAST(bam_no AS UNSIGNED) DESC')
            ->value('bam_no');

        $max = max((int) ($lastAr ?? 0), (int) ($lastLro ?? 0));

        return (string) ($max > 0 ? $max + 1 : 13203);
    }

    /**
     * Generate unique 5-digit reference number (like 12689)
     */
    private function generateReferenceNumber()
    {
        $maxAttempts = 100;

        for ($i = 0; $i < $maxAttempts; $i++) {
            // Generate 5-digit number (10000-99999)
            $number = rand(10000, 99999);
            $reference = (string)$number;

            // Check if it exists in consumer_ledgers or billing_adjustments
            $existsInLedger = ConsumerLedger::where('reference', $reference)->exists();
            $existsInAdjustment = BillingAdjustment::where('reference', $reference)->exists();

            if (!$existsInLedger && !$existsInAdjustment) {
                return $reference;
            }
        }

        // Fallback: use timestamp last 5 digits
        return substr(time(), -5);
    }

    /**
     * Update an existing billing adjustment
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:CM,DM',
            'type_ar' => 'required|in:AR,LRO',
            'date' => 'required|date',
            'account_no' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'acct_code' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'current_bill' => 'nullable|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'arrears' => 'nullable|numeric|min:0',
            'sc_discount' => 'nullable|numeric|min:0',
            'loans' => 'nullable|numeric|min:0',
            'others' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'status' => 'required|in:Pending,Approved,Cancelled',
            'connect_reading' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $billingAdjustment = BillingAdjustment::findOrFail($id);
            $oldLedger = ConsumerLedger::where('billing_adjustment_id', $id)->first();
            
            if (!$oldLedger) {
                return response()->json([
                    'success' => false,
                    'message' => 'Related consumer ledger entry not found'
                ], 404);
            }

            // Find consumer zone by account number
            $consumerZone = ConsumerZoneOne::where('account_no', $request->account_no)->first();
            
            if (!$consumerZone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found: ' . $request->account_no
                ], 404);
            }

            // Parse date
            try {
                $dateCarbon = Carbon::createFromFormat('m/d/Y', $request->date);
            } catch (\Exception $e) {
                try {
                    $dateCarbon = Carbon::parse($request->date);
                } catch (\Exception $e2) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format. Please use MM/DD/YYYY format.'
                    ], 422);
                }
            }
            $date = $dateCarbon->format('Y-m-d');
            $dateTime = $dateCarbon->format('Y-m-d H:i:s');

            // Store old values for balance reversal
            $oldAmount = (float)$billingAdjustment->amount;
            $oldType = $billingAdjustment->type;
            $oldDate = $billingAdjustment->date;
            $oldDateTime = Carbon::parse($oldDate)->format('Y-m-d H:i:s');
            $oldBalance = (float)($oldLedger->balance ?? 0);

            // Calculate what the balance was BEFORE the old transaction
            // If CM: oldBalance = previousBalance - oldAmount, so previousBalance = oldBalance + oldAmount
            // If DM: oldBalance = previousBalance + oldAmount, so previousBalance = oldBalance - oldAmount
            if ($oldType === 'CM') {
                $previousBalanceBeforeOld = round($oldBalance + $oldAmount, 2);
            } else {
                $previousBalanceBeforeOld = round($oldBalance - $oldAmount, 2);
            }

            // Verify by checking the actual ledger entry before this one
            $actualPreviousLedger = ConsumerLedger::where('consumer_zone_id', $oldLedger->consumer_zone_id)
                ->where('txtime', '<', $oldDateTime)
                ->orderBy('txtime', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($actualPreviousLedger) {
                $previousBalanceBeforeOld = (float)($actualPreviousLedger->balance ?? $previousBalanceBeforeOld);
            } else {
                // If no previous ledger, use consumer zone balance
                $consumerZoneBefore = ConsumerZoneOne::find($oldLedger->consumer_zone_id);
                if ($consumerZoneBefore) {
                    $previousBalanceBeforeOld = (float)($consumerZoneBefore->balance ?? $previousBalanceBeforeOld);
                }
            }

            // Calculate new balance with new transaction values
            $newAmount = (float)$request->amount;
            if ($request->type === 'CM') {
                $newBalance = round($previousBalanceBeforeOld - $newAmount, 2);
                $debit = 0;
                $credit = $newAmount;
            } else {
                $newBalance = round($previousBalanceBeforeOld + $newAmount, 2);
                $debit = $newAmount;
                $credit = 0;
            }

            // Update billing adjustment record
            $billingAdjustment->update([
                'type' => $request->type,
                'type_ar' => $request->type_ar,
                'date' => $date,
                'account_no' => $request->account_no,
                'consumer_zone_id' => $consumerZone->id,
                'amount' => $newAmount,
                'acct_code' => $request->acct_code,
                'reference' => $request->reference ?: $billingAdjustment->reference,
                'current_bill' => (float)($request->current_bill ?? 0),
                'penalty' => (float)($request->penalty ?? 0),
                'arrears' => (float)($request->arrears ?? 0),
                'sc_discount' => (float)($request->sc_discount ?? 0),
                'loans' => (float)($request->loans ?? 0),
                'others' => (float)($request->others ?? 0),
                'remarks' => $request->remarks,
                'status' => $request->status,
                'connect_reading' => (int)($request->connect_reading ?? 0),
                'username' => auth()->user()->name ?? 'SYSTEM',
            ]);

            // Update consumer ledger entry
            $oldLedger->update([
                'consumer_zone_id' => $consumerZone->id,
                'trans' => $request->type, // Use actual type: CM or DM
                'date' => $date,
                'reference' => $request->reference ?: $oldLedger->reference,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $newBalance,
                'username' => auth()->user()->name ?? 'SYSTEM',
                'txtime' => $dateTime,
            ]);

            // Recalculate balances for all subsequent ledger entries
            $this->recalculateSubsequentBalances($consumerZone->id, $dateTime, $newBalance);

            // Update consumer zone balance
            $latestBalance = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                ->orderBy('txtime', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($latestBalance) {
                $consumerZone->balance = (float)($latestBalance->balance ?? 0);
                $consumerZone->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment updated successfully',
                'bam_no' => $billingAdjustment->bam_no,
                'reference' => $oldLedger->reference,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating billing adjustment', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating billing adjustment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate balances for all ledger entries after a given datetime
     */
    private function recalculateSubsequentBalances($consumerZoneId, $afterDateTime, $startingBalance)
    {
        $subsequentEntries = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
            ->where('txtime', '>', $afterDateTime)
            ->orderBy('txtime', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $currentBalance = $startingBalance;

        foreach ($subsequentEntries as $entry) {
            $currentBalance = round($currentBalance + (float)$entry->debit - (float)$entry->credit, 2);
            $entry->balance = $currentBalance;
            $entry->save();
        }
    }

    /**
     * Resolve consumer by account using exact and normalized matching.
     */
    private function findConsumerForLroAccount(?string $accountNo): ?ConsumerZoneOne
    {
        $accountNo = trim((string) ($accountNo ?? ''));
        if ($accountNo === '') {
            return null;
        }

        $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
        if ($consumer) {
            return $consumer;
        }

        $normalized = str_replace('-', '', $accountNo);
        return ConsumerZoneOne::whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalized])->first();
    }

    private function lroLedgerHasConsumerZoneIdColumn(): bool
    {
        return Schema::hasTable('lro_ledger') && Schema::hasColumn('lro_ledger', 'consumer_zone_id');
    }
}
