<?php

namespace App\Http\Controllers;

use App\Models\BillingAdjustment;
use App\Models\ConsumerLedger;
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

        // LRO BAM entries stored in lro_ledger table
        $lroEntries = LROLedger::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

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
        $lroEntries = LROLedger::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
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
            'ledger' => 'required|in:AR,LRO',
            'date' => 'required|date',
            'account_no' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'acct_code' => 'nullable|string|max:50',
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

            $consumerZone = $this->findConsumerForLroAccount($request->account_no);

            if (!$consumerZone) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Account not found: ' . $request->account_no
                ], 404);
            }

            // Generate auto BAM number (format: BAM-YYYYMMDD-XXXXX)
            $bamNo = $this->generateBamNumber();

            // Parse date (handle multiple formats)
            try {
                $dateCarbon = Carbon::createFromFormat('m/d/Y', $request->date);
            } catch (\Exception $e) {
                try {
                    $dateCarbon = Carbon::parse($request->date);
                } catch (\Exception $e2) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format. Please use MM/DD/YYYY format.'
                    ], 422);
                }
            }
            $date = $dateCarbon->format('Y-m-d');
            $dateTime = $dateCarbon->format('Y-m-d H:i:s');

            // Get latest balance before this transaction (consumer_ledgers is source of truth)
            $previousBalance = $this->getLedgerBalanceBefore($consumerZone->id, $dateTime);

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

            // Create billing adjustment record (account_no lives on consumer_zone via consumer_zone_id)
            $billingAdjustment = BillingAdjustment::create($this->buildBillingAdjustmentAttributes([
                'type' => $request->type,
                'ledger' => $request->ledger,
                'date' => $date,
                'bam_no' => $bamNo,
                'consumer_zone_id' => $consumerZone->id,
                'amount' => $amount,
                'acct_code' => $request->acct_code,
                'current_bill' => (float)($request->current_bill ?? 0),
                'penalty' => (float)($request->penalty ?? 0),
                'arrears' => (float)($request->arrears ?? 0),
                'sc_discount' => (float)($request->sc_discount ?? 0),
                'loans' => (float)($request->loans ?? 0),
                'others' => (float)($request->others ?? 0),
                'remarks' => $request->remarks,
                'status' => $this->normalizeArStatus($request->status),
                'connect_reading' => (int)($request->connect_reading ?? 0),
                'username' => $this->authUsername(),
            ]));

            // Create consumer ledger entry
            $ledger = ConsumerLedger::create([
                'consumer_zone_id' => $consumerZone->id,
                'billing_adjustment_id' => $billingAdjustment->id,
                'trans' => $request->type, // Use actual type: CM or DM
                'date' => $date,
                'due_date' => null,
                'reading' => 0,
                'volume' => 0,
                'billamount' => 0,
                'penalty' => 0,
                'others' => 0,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $newBalance,
                'username' => $this->authUsername(),
                'txtime' => $dateTime,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment saved successfully',
                'bam_no' => $bamNo,
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

                if ($accountNo !== '' && !$consumerZone) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Account not found: ' . $accountNo], 404);
                }

                $entry = LROLedger::create($this->buildLroLedgerAttributes([
                    'type'            => $type,
                    'ledger'          => 'LRO',
                    'date'            => $request->input('date') ?: null,
                    'consumer_zone_id'=> $consumerZone?->id,
                    'bam_no'          => $request->input('bam_no'),
                    'amount'          => (float)($request->input('amount', 0)),
                    'acct_code'       => $request->input('acct_code'),
                    'remarks'         => $request->input('remarks'),
                    'status'          => $status === 'POSTED' ? 'Posted' : $request->input('status', 'Pending'),
                    'correct_reading' => (float)($request->input('correct_reading', 0)),
                ]));

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
        $mapped['ledger'] = 'AR';
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
        $mapped['status'] = $this->normalizeArStatus($request->input('status', 'Pending'));

        $newRequest = new Request($mapped);
        return $this->update($newRequest, $id);
    }

    /**
     * Show the form for editing an LRO billing adjustment
     */
    public function editLro($id)
    {
        $lroEntry = LROLedger::with('consumerZone')->findOrFail($id);
        $billingAdjustments = BillingAdjustment::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $lroEntries = LROLedger::with('consumerZone')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $previewBamNo = $lroEntry->bam_no ?? $this->generateBamNumber();
        return view('transaction.billing_adjustment', compact('lroEntry', 'billingAdjustments', 'lroEntries', 'previewBamNo'));
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
                $consumerZone = $this->findConsumerForLroAccount($accountNo);

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
                $previousBalance = $this->getLedgerBalanceBefore($consumerZone->id);

                if ($type === 'CM') {
                    $newBalance = round($previousBalance - $amount, 2);
                    $debit      = 0;
                    $credit     = $amount;
                } else {
                    $newBalance = round($previousBalance + $amount, 2);
                    $debit      = $amount;
                    $credit     = 0;
                }


                // Create billing_adjustments record (account_no via consumer_zone_id)
                $billingAdjustment = BillingAdjustment::create($this->buildBillingAdjustmentAttributes([
                    'type'            => $type,
                    'ledger'          => 'AR',
                    'date'            => $date,
                    'bam_no'          => $request->input('bam_no') ?: $entry->bam_no,
                    'consumer_zone_id'=> $consumerZone->id,
                    'amount'          => $amount,
                    'acct_code'       => $request->input('acct_code'),
                    'current_bill'    => 0,
                    'penalty'         => 0,
                    'arrears'         => 0,
                    'sc_discount'     => 0,
                    'loans'           => 0,
                    'others'          => 0,
                    'remarks'         => $request->input('remarks'),
                    'status'          => $this->normalizeArStatus($request->input('status', 'Pending')),
                    'connect_reading' => (int)($request->input('correct_reading', 0)),
                    'username'        => $this->authUsername(),
                ]));

                // Create consumer_ledgers row
                ConsumerLedger::create([
                    'consumer_zone_id'      => $consumerZone->id,
                    'billing_adjustment_id' => $billingAdjustment->id,
                    'trans'                 => $type,
                    'date'                  => $date,
                    'due_date'              => null,
                    'reading'               => 0,
                    'volume'                => 0,
                    'billamount'            => 0,
                    'penalty'               => 0,
                    'others'                => 0,
                    'debit'                 => $debit,
                    'credit'                => $credit,
                    'balance'               => $newBalance,
                    'username'              => $this->authUsername(),
                    'txtime'                => $dateTime,
                ]);

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
            : trim((string) ($entry->account_no ?? ''));
        $consumerZone = $this->findConsumerForLroAccount($nextAccount);

        if ($nextAccount !== '' && !$consumerZone) {
            return response()->json(['success' => false, 'message' => 'Account not found: ' . $nextAccount], 404);
        }

        $entry->update($this->buildLroLedgerAttributes([
            'type'            => $validated['type']            ?? $entry->type,
            'ledger'          => 'LRO',
            'date'            => !empty($validated['date'])    ? $validated['date'] : $entry->date,
            'consumer_zone_id'=> $consumerZone?->id ?? $entry->consumer_zone_id,
            'bam_no'          => $validated['bam_no']          ?? $entry->bam_no,
            'amount'          => isset($validated['amount'])   ? (float) $validated['amount'] : $entry->amount,
            'acct_code'       => $validated['acct_code']       ?? $entry->acct_code,
            'remarks'         => $validated['remarks']         ?? $entry->remarks,
            'status'          => $validated['status']          ?? $entry->status,
            'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : $entry->correct_reading,
        ]));

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
                'remarks' => ['nullable', 'string'],
                'status' => ['nullable', 'string', 'max:20'],
                'correct_reading' => ['nullable', 'numeric'],
            ]);

            $accountNo = trim((string) ($validated['account'] ?? ''));
            $consumerZone = $this->findConsumerForLroAccount($accountNo);

            if ($accountNo !== '' && !$consumerZone) {
                return response()->json(['success' => false, 'message' => 'Account not found: ' . $accountNo], 404);
            }

            $entry = LROLedger::create($this->buildLroLedgerAttributes([
                'type' => $validated['type'] ?? 'CM',
                'ledger' => 'LRO',
                'date' => !empty($validated['date']) ? $validated['date'] : null,
                'consumer_zone_id' => $consumerZone?->id,
                'bam_no' => $validated['bam_no'] ?? null,
                'amount' => (float) ($validated['amount'] ?? 0),
                'acct_code' => $validated['acct_code'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $validated['status'] ?? 'Pending',
                'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : 0,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment saved.',
                'data' => ['id' => $entry->id],
            ]);
        }

        // AR path: adapt new UI payload to the existing store() implementation
        $status = strtoupper((string) $request->input('status', 'Pending'));
        if ($status === 'POSTED') {
            return response()->json([
                'success' => false,
                'message' => 'Paid status is only allowed in LRO ledger. Set Ledger to LRO to continue.',
            ], 422);
        }

        $mapped = $request->all();

        // Map fields to match the original implementation
        $mapped['ledger'] = 'AR';
        $mapped['account_no'] = $request->input('account');
        $mapped['status'] = $this->normalizeArStatus($request->input('status', 'Pending'));

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
     * Update an existing billing adjustment
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:CM,DM',
            'ledger' => 'required|in:AR,LRO',
            'date' => 'required|date',
            'account_no' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'acct_code' => 'nullable|string|max:50',
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
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Related consumer ledger entry not found'
                ], 404);
            }

            $consumerZone = $this->findConsumerForLroAccount($request->account_no);

            if (!$consumerZone) {
                DB::rollBack();

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
                    DB::rollBack();

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
            $billingAdjustment->update($this->buildBillingAdjustmentAttributes([
                'type' => $request->type,
                'ledger' => $request->ledger,
                'date' => $date,
                'consumer_zone_id' => $consumerZone->id,
                'amount' => $newAmount,
                'acct_code' => $request->acct_code,
                'current_bill' => (float)($request->current_bill ?? 0),
                'penalty' => (float)($request->penalty ?? 0),
                'arrears' => (float)($request->arrears ?? 0),
                'sc_discount' => (float)($request->sc_discount ?? 0),
                'loans' => (float)($request->loans ?? 0),
                'others' => (float)($request->others ?? 0),
                'remarks' => $request->remarks,
                'status' => $this->normalizeArStatus($request->status),
                'connect_reading' => (int)($request->connect_reading ?? 0),
                'username' => $this->authUsername(),
            ]));

            // Update consumer ledger entry
            $oldLedger->update([
                'consumer_zone_id' => $consumerZone->id,
                'trans' => $request->type, // Use actual type: CM or DM
                'date' => $date,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $newBalance,
                'username' => $this->authUsername(),
                'txtime' => $dateTime,
            ]);

            // Recalculate balances for all subsequent ledger entries
            $this->recalculateSubsequentBalances($consumerZone->id, $dateTime, $newBalance);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment updated successfully',
                'bam_no' => $billingAdjustment->bam_no,
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
     * Latest running balance from consumer_ledgers (source of truth).
     */
    private function getLedgerBalanceBefore(int $consumerZoneId, ?string $beforeDateTime = null): float
    {
        $query = ConsumerLedger::where('consumer_zone_id', $consumerZoneId);

        if ($beforeDateTime !== null && $beforeDateTime !== '') {
            $query->where('txtime', '<', $beforeDateTime);
        }

        $latestLedger = $query
            ->orderBy('txtime', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latestLedger ? (float) ($latestLedger->balance ?? 0) : 0.0;
    }

    /**
     * Map UI status values to billing_adjustments.status (Pending, Approved, Cancelled).
     */
    private function normalizeArStatus(?string $status): string
    {
        $normalized = ucfirst(strtolower(trim((string) ($status ?? 'Pending'))));

        if (in_array($normalized, ['Pending', 'Approved', 'Cancelled'], true)) {
            return $normalized;
        }

        if (strcasecmp((string) $status, 'Posted') === 0) {
            return 'Approved';
        }

        return 'Pending';
    }

    private function authUsername(): string
    {
        $user = auth()->user();

        return $user?->name ?? 'SYSTEM';
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
        $upper = strtoupper(trim($accountNo));

        return ConsumerZoneOne::where(function ($q) use ($accountNo, $normalized, $upper) {
            $q->where('account_no', $accountNo)
                ->orWhereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalized])
                ->orWhereRaw('UPPER(TRIM(account_no)) = ?', [$upper]);
        })->first();
    }

    /**
     * Build billing_adjustments attributes, keeping only columns that exist on the table.
     * account_no is resolved from consumer_zone via consumer_zone_id (not stored on billing_adjustments).
     */
    private function buildBillingAdjustmentAttributes(array $data): array
    {
        if (!Schema::hasTable('billing_adjustments')) {
            return $data;
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('billing_adjustments', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Build lro_ledger attributes, keeping only columns that exist on the table.
     * account_no / account_name are resolved from consumer_zone via consumer_zone_id.
     */
    private function buildLroLedgerAttributes(array $data): array
    {
        return LROLedger::filterTableAttributes($data);
    }
}
