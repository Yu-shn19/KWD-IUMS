<?php

namespace App\Http\Controllers;

use App\Models\BillingAdjustment;
use App\Models\ConsumerLedger;
use App\Models\ConsumerZone;
use App\Models\LROLedger;
use App\Support\SundryAccountCodes;
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
        $this->purgeStalePendingLroLedgerRows();

        $dateColumn = 'date';
        $billingIdColumn = (new BillingAdjustment)->getKeyName();
        $lroIdColumn = (new LROLedger)->getKeyName();

        $billingAdjustments = BillingAdjustment::query()
            ->with('consumerZone')
            ->orderBy($dateColumn, 'desc')
            ->orderBy($billingIdColumn, 'desc')
            ->get();

        // LRO BAM entries stored in lro_ledger table (approved/paid — pending lives in billing_adjustments)
        $lroEntries = LROLedger::query()
            ->with('consumerZone')
            ->whereRaw($this->lroLedgerListedStatusesRaw())
            ->orderBy($dateColumn, 'desc')
            ->orderBy($lroIdColumn, 'desc')
            ->get();

        $previewBamNo = $this->generateBamNumber();

        return view('transaction.billing_adjustment', compact('billingAdjustments', 'lroEntries', 'previewBamNo'));
    }

    /**
     * Show the form for editing a billing adjustment
     */
    public function edit(int $id)
    {
        $dateColumn = 'date';
        $billingIdColumn = (new BillingAdjustment)->getKeyName();
        $lroIdColumn = (new LROLedger)->getKeyName();

        $billingAdjustment = BillingAdjustment::with(['consumerLedgers', 'consumerZone'])->findOrFail($id);

        if ($this->isPaidArCm($billingAdjustment)) {
            return redirect()
                ->route('billing-adjustment')
                ->with('error', 'Paid AR CM entries cannot be edited.');
        }
        $billingAdjustments = BillingAdjustment::query()
            ->with('consumerZone')
            ->orderBy($dateColumn, 'desc')
            ->orderBy($billingIdColumn, 'desc')
            ->get();
        $lroEntries = LROLedger::query()
            ->with('consumerZone')
            ->whereRaw($this->lroLedgerListedStatusesRaw())
            ->orderBy($dateColumn, 'desc')
            ->orderBy($lroIdColumn, 'desc')
            ->get();
        $previewBamNo = $this->generateBamNumber();
        return view('transaction.billing_adjustment', compact('billingAdjustment', 'billingAdjustments', 'lroEntries', 'previewBamNo'));
    }

    /**
     * Delete a paid AR CM billing adjustment (removes consumer ledger rows and recalculates balances).
     */
    public function destroyAr(int $id)
    {
        try {
            DB::beginTransaction();

            $billingAdjustment = BillingAdjustment::with('consumerLedgers')->findOrFail($id);

            if (!$this->isPaidArCm($billingAdjustment)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Only paid AR CM entries can be deleted from the list.',
                ], 422);
            }

            $ledger = $billingAdjustment->consumerLedgers->first();
            $consumerZoneId = $ledger
                ? (int) $ledger->consumer_zone_id
                : (int) ($billingAdjustment->consumer_zone_id ?? 0);
            $fromDateTime = $ledger && $ledger->txtime
                ? Carbon::parse($ledger->txtime)->format('Y-m-d H:i:s')
                : ($billingAdjustment->date
                    ? Carbon::parse($billingAdjustment->date)->format('Y-m-d 00:00:00')
                    : null);

            $billingAdjustment->delete();

            if ($consumerZoneId > 0 && $fromDateTime) {
                $this->recalculateBalancesFrom($consumerZoneId, $fromDateTime);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paid AR CM billing adjustment deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('destroyAr failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete billing adjustment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get billing adjustment data for editing
     */
    public function show(int $id)
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
            'status' => 'required|string|max:20',
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

            if ($reject = $this->rejectSundryBamRules($request->acct_code, $request->ledger, $request->type)) {
                DB::rollBack();

                return $reject;
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

            $status = $this->normalizeBillingStatus(
                $request->status,
                strtoupper((string) $request->type) === 'CM' && strtoupper((string) $request->ledger) === 'AR'
            );

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
                'status' => $status,
                'connect_reading' => (int)($request->connect_reading ?? 0),
                'username' => $this->authUsername(),
            ]));

            if ($this->shouldPostToConsumerLedgerForBam($status, $request->acct_code, $request->ledger)) {
                ConsumerLedger::create([
                    'consumer_zone_id' => $consumerZone->id,
                    'billing_adjustment_id' => $billingAdjustment->id,
                    'trans' => $request->type,
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

                $this->recalculateSubsequentBalances($consumerZone->id, $dateTime, $newBalance);
            } else {
                $this->removeMatchingLroLedgerRows($billingAdjustment);
            }

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
    public function updateFromNewUi(Request $request,int $id)
    {
        $ar = strtoupper((string) $request->input('ar', 'AR'));
        $status = strtoupper((string) $request->input('status', 'Pending'));
        $requestType = strtoupper((string) $request->input('type', 'CM'));

        $existingBillingAdjustment = BillingAdjustment::findOrFail($id);
        if ($this->isPaidArCm($existingBillingAdjustment)) {
            return response()->json([
                'success' => false,
                'message' => 'Paid AR CM entries cannot be edited. Use Delete from the list.',
            ], 422);
        }

        // Paid status is allowed for AR CM and LRO entries only.
        if ($ar !== 'LRO' && $status === 'POSTED' && $requestType !== 'CM') {
            return response()->json([
                'success' => false,
                'message' => 'Paid status is only allowed for CM entries.',
            ], 422);
        }

        if ($reject = $this->rejectSundryBamRules(
            $request->input('acct_code'),
            $ar,
            $request->input('type', 'CM')
        )) {
            return $reject;
        }

        // ── Explicit AR → LRO switch ─────────────────────────────────────────────
        if ($ar === 'LRO') {
            try {
                DB::beginTransaction();

                $billingAdjustmentIdColumn = 'billing_adjustment_id';
                $idColumn = (new BillingAdjustment)->getKeyName();
                $billingAdjustment = BillingAdjustment::findOrFail($id);
                $existingLedger = ConsumerLedger::query()
                    ->where($billingAdjustmentIdColumn, $id)
                    ->first();

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

                if ($this->shouldPostToLroLedger($status)) {
                    if ($existingLedger) {
                        $oldConsumerZoneId = (int) $existingLedger->consumer_zone_id;
                        $oldDateTime = $existingLedger->txtime
                            ? Carbon::parse($existingLedger->txtime)->format('Y-m-d H:i:s')
                            : Carbon::parse($billingAdjustment->date)->format('Y-m-d H:i:s');
                        $existingLedger->delete();
                        $this->recalculateBalancesFrom($oldConsumerZoneId, $oldDateTime);
                    }

                    $preservedBamNo = $request->input('bam_no') ?: $billingAdjustment->bam_no;

                    BillingAdjustment::query()->where($idColumn, $id)->delete();

                    $lroStatus = $this->resolveLroLedgerStatus($status);

                    $entry = LROLedger::create($this->buildLroLedgerAttributes(array_merge([
                        'type'            => $type,
                        'ledger'          => 'LRO',
                        'date'            => $request->input('date') ?: null,
                        'consumer_zone_id'=> $consumerZone?->id,
                        'bam_no'          => $preservedBamNo,
                        'amount'          => (float)($request->input('amount', 0)),
                        'acct_code'       => $request->input('acct_code'),
                        'remarks'         => $request->input('remarks'),
                        'correct_reading' => (float)($request->input('correct_reading', 0)),
                    ], $lroStatus)));

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => $this->isPaidStatus($status)
                            ? 'Marked as paid; record moved to LRO ledger.'
                            : 'Approved; record moved to LRO ledger.',
                        'data'    => ['id' => $entry->id, 'bam_no' => $entry->bam_no],
                    ]);
                }

                if ($existingLedger) {
                    $oldConsumerZoneId = (int) $existingLedger->consumer_zone_id;
                    $oldDateTime = $existingLedger->txtime
                        ? Carbon::parse($existingLedger->txtime)->format('Y-m-d H:i:s')
                        : Carbon::parse($billingAdjustment->date)->format('Y-m-d H:i:s');
                    $existingLedger->delete();
                    $this->recalculateBalancesFrom($oldConsumerZoneId, $oldDateTime);
                }

                $billingAdjustment->update($this->buildBillingAdjustmentAttributes([
                    'type'            => $type,
                    'ledger'          => 'LRO',
                    'date'            => $request->input('date') ?: $billingAdjustment->date,
                    'consumer_zone_id'=> $consumerZone?->id ?? $billingAdjustment->consumer_zone_id,
                    'amount'          => (float)($request->input('amount', $billingAdjustment->amount ?? 0)),
                    'acct_code'       => $request->input('acct_code'),
                    'remarks'         => $request->input('remarks'),
                    'status'          => $this->normalizeArStatus($request->input('status', 'Pending')),
                    'connect_reading' => (int)($request->input('correct_reading', $billingAdjustment->connect_reading ?? 0)),
                    'username'        => $this->authUsername(),
                ]));

                $this->removeMatchingLroLedgerRows($billingAdjustment->fresh());

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Moved to LRO (pending — not posted to ledger yet).',
                    'data'    => ['id' => $billingAdjustment->id, 'bam_no' => $billingAdjustment->bam_no],
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
        $mapped['status'] = $this->normalizeBillingStatus(
            $request->input('status', 'Pending'),
            $type === 'CM'
        );

        $newRequest = new Request($mapped);
        return $this->update($newRequest, $id);
    }

    /**
     * Show the form for editing an LRO billing adjustment
     */
    public function editLro(int $id)
    {
        $dateColumn = 'date';
        $billingIdColumn = (new BillingAdjustment)->getKeyName();
        $lroIdColumn = (new LROLedger)->getKeyName();

        $lroEntry = LROLedger::with('consumerZone')->findOrFail($id);

        if ($this->isPaidStatus($lroEntry->status)) {
            return redirect()
                ->route('billing-adjustment')
                ->with('error', 'Paid LRO entries cannot be edited.');
        }
        $billingAdjustments = BillingAdjustment::query()
            ->with('consumerZone')
            ->orderBy($dateColumn, 'desc')
            ->orderBy($billingIdColumn, 'desc')
            ->get();
        $lroEntries = LROLedger::query()
            ->with('consumerZone')
            ->whereRaw($this->lroLedgerListedStatusesRaw())
            ->orderBy($dateColumn, 'desc')
            ->orderBy($lroIdColumn, 'desc')
            ->get();
        $previewBamNo = $lroEntry->bam_no ?? $this->generateBamNumber();
        return view('transaction.billing_adjustment', compact('lroEntry', 'billingAdjustments', 'lroEntries', 'previewBamNo'));
    }

    /**
     * Delete a paid LRO billing adjustment from lro_ledger.
     */
    public function destroyLro(int $id)
    {
        try {
            $entry = LROLedger::findOrFail($id);

            if (!$this->isPaidStatus($entry->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only paid LRO entries can be deleted from the list.',
                ], 422);
            }

            $entry->delete();

            return response()->json([
                'success' => true,
                'message' => 'Paid LRO billing adjustment deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('destroyLro failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete LRO billing adjustment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing LRO billing adjustment.
     * If the user switches the ledger from LRO → AR, removes the lro_ledger row first,
     * then creates a new billing_adjustments + consumer_ledgers entry (same order as AR→LRO).
     * Otherwise updates the lro_ledger row in place.
     */
    public function updateLro(Request $request,int $id)
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

                if ($reject = $this->rejectSundryBamRules($request->input('acct_code', $entry->acct_code), 'AR', $type)) {
                    DB::rollBack();

                    return $reject;
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
                $preservedBamNo = $entry->bam_no;
                $arStatus = $this->normalizeArStatus($request->input('status', 'Pending'));

                $entry->delete();

                $billingAdjustment = BillingAdjustment::create($this->buildBillingAdjustmentAttributes([
                    'type'            => $type,
                    'ledger'          => 'AR',
                    'date'            => $date,
                    'bam_no'          => $request->input('bam_no') ?: $preservedBamNo,
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
                    'status'          => $arStatus,
                    'connect_reading' => (int)($request->input('correct_reading', 0)),
                    'username'        => $this->authUsername(),
                ]));

                if ($this->shouldPostToConsumerLedger($arStatus)) {
                    $previousBalance = $this->getLedgerBalanceBefore($consumerZone->id, $dateTime);

                    if ($type === 'CM') {
                        $newBalance = round($previousBalance - $amount, 2);
                        $debit      = 0;
                        $credit     = $amount;
                    } else {
                        $newBalance = round($previousBalance + $amount, 2);
                        $debit      = $amount;
                        $credit     = 0;
                    }

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

                    $this->recalculateSubsequentBalances($consumerZone->id, $dateTime, $newBalance);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $this->shouldPostToConsumerLedger($arStatus)
                        ? 'Moved to AR (Consumer Ledger) successfully.'
                        : 'Moved to AR (pending — not posted to ledger yet).',
                    'data'    => ['id' => $billingAdjustment->id, 'bam_no' => $billingAdjustment->bam_no],
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('updateLro LRO→AR switch failed', ['id' => $id, 'error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
            }
        }

        // ── Normal LRO update ─────────────────────────────────────────────────────
        if ($this->isPaidStatus($entry->status)) {
            return response()->json([
                'success' => false,
                'message' => 'Paid LRO entries cannot be edited. Use Delete from the list.',
            ], 422);
        }

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

        $acctCode = $validated['acct_code'] ?? $entry->acct_code;
        $entryType = $validated['type'] ?? $entry->type;
        if ($reject = $this->rejectSundryBamRules($acctCode, 'LRO', $entryType)) {
            return $reject;
        }

        $nextStatus = $validated['status'] ?? $entry->status;
        if (!$this->shouldPostToLroLedger($nextStatus)) {
            try {
                DB::beginTransaction();

                $billingAdjustment = BillingAdjustment::create($this->buildBillingAdjustmentAttributes([
                    'type'            => $validated['type'] ?? $entry->type,
                    'ledger'          => 'LRO',
                    'date'            => !empty($validated['date']) ? $validated['date'] : $entry->date,
                    'bam_no'          => $validated['bam_no'] ?? $entry->bam_no,
                    'consumer_zone_id'=> $consumerZone?->id ?? $entry->consumer_zone_id,
                    'amount'          => isset($validated['amount']) ? (float) $validated['amount'] : $entry->amount,
                    'acct_code'       => $validated['acct_code'] ?? $entry->acct_code,
                    'remarks'         => $validated['remarks'] ?? $entry->remarks,
                    'status'          => $this->normalizeArStatus($nextStatus),
                    'connect_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : $entry->correct_reading,
                    'username'        => $this->authUsername(),
                ]));

                $entry->delete();

                $this->removeMatchingLroLedgerRows($billingAdjustment);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Moved to pending LRO (removed from LRO ledger).',
                    'data'    => ['id' => $billingAdjustment->id, 'bam_no' => $billingAdjustment->bam_no],
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('updateLro pending move failed', ['id' => $id, 'error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
            }
        }

        $lroStatus = $this->resolveLroLedgerStatus($validated['status'] ?? $entry->status);

        $entry->update($this->buildLroLedgerAttributes(array_merge([
            'type'            => $validated['type']            ?? $entry->type,
            'ledger'          => 'LRO',
            'date'            => !empty($validated['date'])    ? $validated['date'] : $entry->date,
            'consumer_zone_id'=> $consumerZone?->id ?? $entry->consumer_zone_id,
            'bam_no'          => $validated['bam_no']          ?? $entry->bam_no,
            'amount'          => isset($validated['amount'])   ? (float) $validated['amount'] : $entry->amount,
            'acct_code'       => $validated['acct_code']       ?? $entry->acct_code,
            'remarks'         => $validated['remarks']         ?? $entry->remarks,
            'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : $entry->correct_reading,
        ], $lroStatus)));

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

            if ($reject = $this->rejectSundryBamRules(
                $validated['acct_code'] ?? null,
                'LRO',
                $validated['type'] ?? 'CM'
            )) {
                return $reject;
            }

            $statusRaw = $validated['status'] ?? 'Pending';
            $bamNo = $validated['bam_no'] ?? $this->generateBamNumber();

            if ($this->shouldPostToLroLedger($statusRaw)) {
                $lroStatus = $this->resolveLroLedgerStatus($statusRaw);

                $entry = LROLedger::create($this->buildLroLedgerAttributes(array_merge([
                    'type' => $validated['type'] ?? SundryAccountCodes::defaultChargeType($validated['acct_code'] ?? null),
                    'ledger' => 'LRO',
                    'date' => !empty($validated['date']) ? $validated['date'] : null,
                    'consumer_zone_id' => $consumerZone?->id,
                    'bam_no' => $bamNo,
                    'amount' => (float) ($validated['amount'] ?? 0),
                    'acct_code' => $validated['acct_code'] ?? null,
                    'remarks' => $validated['remarks'] ?? null,
                    'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : 0,
                ], $lroStatus)));

                return response()->json([
                    'success' => true,
                    'message' => $this->isPaidStatus($statusRaw)
                        ? 'Billing adjustment saved to LRO ledger (paid).'
                        : 'Billing adjustment saved to LRO ledger (approved).',
                    'data' => ['id' => $entry->id, 'bam_no' => $entry->bam_no],
                ]);
            }

            $billingAdjustment = BillingAdjustment::create($this->buildBillingAdjustmentAttributes([
                'type' => $validated['type'] ?? 'CM',
                'ledger' => 'LRO',
                'date' => !empty($validated['date']) ? $validated['date'] : null,
                'bam_no' => $bamNo,
                'consumer_zone_id' => $consumerZone?->id,
                'amount' => (float) ($validated['amount'] ?? 0),
                'acct_code' => $validated['acct_code'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $this->normalizeArStatus($statusRaw),
                'connect_reading' => isset($validated['correct_reading']) ? (int) $validated['correct_reading'] : 0,
                'username' => $this->authUsername(),
            ]));

            $this->removeMatchingLroLedgerRows($billingAdjustment);

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment saved (pending — not posted to ledger yet).',
                'data' => ['id' => $billingAdjustment->id, 'bam_no' => $billingAdjustment->bam_no],
            ]);
        }

        // AR path: adapt new UI payload to the existing store() implementation
        $status = strtoupper((string) $request->input('status', 'Pending'));
        $type = strtoupper((string) $request->input('type', 'CM'));
        if ($status === 'POSTED' && $type !== 'CM') {
            return response()->json([
                'success' => false,
                'message' => 'Paid status is only allowed for CM entries.',
            ], 422);
        }

        if ($reject = $this->rejectSundryBamRules(
            $request->input('acct_code'),
            'AR',
            $request->input('type', 'CM')
        )) {
            return $reject;
        }

        $mapped = $request->all();

        // Map fields to match the original implementation
        $mapped['ledger'] = 'AR';
        $mapped['account_no'] = $request->input('account');
        $mapped['status'] = $this->normalizeBillingStatus($request->input('status', 'Pending'), $type === 'CM');

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
    public function update(Request $request,int $id)
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
            'status' => 'required|string|max:20',
            'connect_reading' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $billingAdjustment = BillingAdjustment::findOrFail($id);

            if ($this->isPaidArCm($billingAdjustment)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Paid AR CM entries cannot be edited. Use Delete from the list.',
                ], 422);
            }

            $billingAdjustmentIdColumn = 'billing_adjustment_id';
            $oldLedger = ConsumerLedger::query()
                ->where($billingAdjustmentIdColumn, $id)
                ->first();

            $consumerZone = $this->findConsumerForLroAccount($request->account_no);

            if (!$consumerZone) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Account not found: ' . $request->account_no
                ], 404);
            }

            if ($reject = $this->rejectSundryBamRules($request->acct_code, $request->ledger, $request->type)) {
                DB::rollBack();

                return $reject;
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

            $status = $this->normalizeBillingStatus(
                $request->status,
                strtoupper((string) $request->type) === 'CM' && strtoupper((string) $request->ledger) === 'AR'
            );
            $newAmount = (float) $request->amount;

            if ($request->type === 'CM') {
                $debit = 0;
                $credit = $newAmount;
            } else {
                $debit = $newAmount;
                $credit = 0;
            }

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
                'status' => $status,
                'connect_reading' => (int)($request->connect_reading ?? 0),
                'username' => $this->authUsername(),
            ]));

            if ($this->shouldPostToConsumerLedgerForBam($status, $request->acct_code, $request->ledger)) {
                $previousBalance = $oldLedger
                    ? $this->getLedgerBalanceBeforeEntry((int) $consumerZone->id, $dateTime, (int) $oldLedger->id)
                    : $this->getLedgerBalanceBefore($consumerZone->id, $dateTime);
                $newBalance = $request->type === 'CM'
                    ? round($previousBalance - $newAmount, 2)
                    : round($previousBalance + $newAmount, 2);

                if ($oldLedger) {
                    $oldConsumerZoneId = (int) $oldLedger->consumer_zone_id;
                    $oldDateTime = $oldLedger->txtime
                        ? Carbon::parse($oldLedger->txtime)->format('Y-m-d H:i:s')
                        : Carbon::parse($billingAdjustment->date)->format('Y-m-d H:i:s');

                    $oldLedger->update([
                        'consumer_zone_id' => $consumerZone->id,
                        'trans' => $request->type,
                        'date' => $date,
                        'debit' => $debit,
                        'credit' => $credit,
                        'balance' => $newBalance,
                        'username' => $this->authUsername(),
                        'txtime' => $dateTime,
                    ]);

                    $newConsumerZoneId = (int) $consumerZone->id;
                    $consumerChanged = $oldConsumerZoneId !== $newConsumerZoneId;
                    $dateChanged = $oldDateTime !== $dateTime;

                    if ($consumerChanged) {
                        $this->recalculateBalancesFrom($oldConsumerZoneId, $oldDateTime);
                        $this->recalculateBalancesFrom($newConsumerZoneId, $dateTime);
                    } elseif ($dateChanged) {
                        $this->recalculateBalancesFrom($newConsumerZoneId, min($oldDateTime, $dateTime));
                    } else {
                        $this->recalculateBalancesFrom($newConsumerZoneId, $dateTime);
                    }
                } else {
                    ConsumerLedger::create([
                        'consumer_zone_id' => $consumerZone->id,
                        'billing_adjustment_id' => $billingAdjustment->id,
                        'trans' => $request->type,
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

                    $this->recalculateSubsequentBalances($consumerZone->id, $dateTime, $newBalance);
                }
            } elseif ($oldLedger) {
                $oldConsumerZoneId = (int) $oldLedger->consumer_zone_id;
                $oldDateTime = $oldLedger->txtime
                    ? Carbon::parse($oldLedger->txtime)->format('Y-m-d H:i:s')
                    : Carbon::parse($billingAdjustment->date)->format('Y-m-d H:i:s');
                $oldLedger->delete();
                $this->recalculateBalancesFrom($oldConsumerZoneId, $oldDateTime);
            }

            if (!$this->shouldPostToConsumerLedger($status) || strtoupper((string) $request->ledger) === 'LRO') {
                $this->removeMatchingLroLedgerRows($billingAdjustment->fresh());
            }

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
     * Recalculate balances for all ledger entries after a given datetime.
     */
    private function recalculateSubsequentBalances(int $consumerZoneId, string $afterDateTime, float $startingBalance): void
    {
        $consumerZoneIdColumn = 'consumer_zone_id';
        $txtimeColumn = 'txtime';
        $idColumn = (new ConsumerLedger)->getKeyName();

        $subsequentEntries = ConsumerLedger::query()
            ->where($consumerZoneIdColumn, $consumerZoneId)
            ->where($txtimeColumn, '>', $afterDateTime)
            ->orderBy($txtimeColumn, 'asc')
            ->orderBy($idColumn, 'asc')
            ->get();

        $this->applyRunningBalances($subsequentEntries, $startingBalance);
    }

    /**
     * Recalculate running balances from a datetime forward (inclusive), ordered by txtime then id.
     */
    private function recalculateBalancesFrom(int $consumerZoneId, string $fromDateTime): void
    {
        $startingBalance = $this->getLedgerBalanceBefore($consumerZoneId, $fromDateTime);

        $consumerZoneIdColumn = 'consumer_zone_id';
        $txtimeColumn = 'txtime';
        $idColumn = (new ConsumerLedger)->getKeyName();

        $entries = ConsumerLedger::query()
            ->where($consumerZoneIdColumn, $consumerZoneId)
            ->where($txtimeColumn, '>=', $fromDateTime)
            ->orderBy($txtimeColumn, 'asc')
            ->orderBy($idColumn, 'asc')
            ->get();

        $this->applyRunningBalances($entries, $startingBalance);
    }

    /**
     * Walk ledger rows in order, persist updated running balances when they change.
     *
     * @param  \Illuminate\Support\Collection<int, ConsumerLedger>  $entries
     */
    private function applyRunningBalances($entries, float $startingBalance): void
    {
        $currentBalance = $startingBalance;

        foreach ($entries as $entry) {
            $currentBalance = round($currentBalance + (float) $entry->debit - (float) $entry->credit, 2);

            if ((float) $entry->balance !== $currentBalance) {
                $entry->balance = $currentBalance;
                $entry->save();
            }
        }
    }

    /**
     * Running balance immediately before a specific ledger entry (handles same-txtime ordering).
     */
    private function getLedgerBalanceBeforeEntry(int $consumerZoneId, string $dateTime, int $ledgerId): float
    {
        $balance = $this->getLedgerBalanceBefore($consumerZoneId, $dateTime);

        $consumerZoneIdColumn = 'consumer_zone_id';
        $txtimeColumn = 'txtime';
        $idColumn = (new ConsumerLedger)->getKeyName();

        $sameTimeEarlier = ConsumerLedger::query()
            ->where($consumerZoneIdColumn, $consumerZoneId)
            ->where($txtimeColumn, '=', $dateTime)
            ->where($idColumn, '<', $ledgerId)
            ->orderBy($idColumn, 'asc')
            ->get();

        foreach ($sameTimeEarlier as $entry) {
            $balance = round($balance + (float) $entry->debit - (float) $entry->credit, 2);
        }

        return $balance;
    }

    /**
     * Latest running balance from consumer_ledgers (source of truth).
     */
    private function getLedgerBalanceBefore(int $consumerZoneId, ?string $beforeDateTime = null): float
    {
        $consumerZoneIdColumn = 'consumer_zone_id';
        $txtimeColumn = 'txtime';
        $idColumn = (new ConsumerLedger)->getKeyName();

        $query = ConsumerLedger::query()->where($consumerZoneIdColumn, $consumerZoneId);

        if ($beforeDateTime !== null && $beforeDateTime !== '') {
            $query->where($txtimeColumn, '<', $beforeDateTime);
        }

        $latestLedger = $query
            ->orderBy($txtimeColumn, 'desc')
            ->orderBy($idColumn, 'desc')
            ->first();

        return $latestLedger ? (float) ($latestLedger->balance ?? 0) : 0.0;
    }

    /**
     * Map UI status values to billing_adjustments.status.
     * When $allowPosted is true (AR CM), Posted/Paid is stored as Posted.
     */
    private function normalizeBillingStatus(?string $status, bool $allowPosted = false): string
    {
        $raw = trim((string) ($status ?? 'Pending'));
        $upper = strtoupper($raw);

        if ($allowPosted && in_array($upper, ['POSTED', 'PAID'], true)) {
            return 'Posted';
        }

        $normalized = ucfirst(strtolower($raw));
        if (in_array($normalized, ['Pending', 'Approved', 'Cancelled'], true)) {
            return $normalized;
        }

        if (in_array($upper, ['POSTED', 'PAID'], true)) {
            return 'Approved';
        }

        return 'Pending';
    }

    /**
     * Map UI status for pending LRO rows in billing_adjustments (never stores Posted).
     */
    private function normalizeArStatus(?string $status): string
    {
        return $this->normalizeBillingStatus($status, false);
    }

    private function isPendingStatus(?string $status): bool
    {
        return strcasecmp(trim((string) ($status ?? 'Pending')), 'Pending') === 0;
    }

    private function isCancelledStatus(?string $status): bool
    {
        return strcasecmp(trim((string) ($status ?? '')), 'Cancelled') === 0;
    }

    private function isPaidStatus(?string $status): bool
    {
        $normalized = strtoupper(trim((string) ($status ?? '')));

        return in_array($normalized, ['POSTED', 'PAID'], true);
    }

    private function isPaidArCm(BillingAdjustment $billingAdjustment): bool
    {
        return strtoupper((string) ($billingAdjustment->ledger ?? '')) === 'AR'
            && ($billingAdjustment->type ?? '') === 'CM'
            && $this->isPaidStatus($billingAdjustment->status);
    }

    private function shouldPostToConsumerLedger(?string $status): bool
    {
        $normalized = strtoupper(trim((string) ($status ?? '')));

        return in_array($normalized, ['APPROVED', 'POSTED', 'PAID'], true);
    }

    private function shouldPostToConsumerLedgerForBam(?string $status, ?string $acctCode, ?string $ledger): bool
    {
        if (SundryAccountCodes::isSundry($acctCode)) {
            return false;
        }

        if (strtoupper(trim((string) ($ledger ?? ''))) !== 'AR') {
            return false;
        }

        return $this->shouldPostToConsumerLedger($status);
    }

    /**
     * Sundries belong on LRO ledger as DM charges; CM credits are created via Billing Payment.
     */
    private function rejectSundryBamRules(?string $acctCode, ?string $ledger, ?string $type): ?\Illuminate\Http\JsonResponse
    {
        if (!SundryAccountCodes::isSundry($acctCode)) {
            return null;
        }

        if (strtoupper(trim((string) ($ledger ?? ''))) === 'AR') {
            return response()->json([
                'success' => false,
                'message' => 'Sundries must use LRO ledger only. They cannot be posted to the Account (Consumer) Ledger.',
            ], 422);
        }

        if (strtoupper(trim((string) ($type ?? ''))) === 'CM') {
            return response()->json([
                'success' => false,
                'message' => 'Sundry charges must be Type DM (Debit Memo). CM credits are created automatically when sundries are paid in Billing Payment.',
            ], 422);
        }

        return null;
    }

    private function shouldPostToLroLedger(?string $status): bool
    {
        $normalized = strtoupper(trim((string) ($status ?? '')));

        return in_array($normalized, ['APPROVED', 'POSTED', 'PAID'], true);
    }

    /**
     * @return array{status: string, paid_at: \Illuminate\Support\Carbon|null}
     */
    private function resolveLroLedgerStatus(?string $status): array
    {
        if ($this->isPaidStatus($status)) {
            return ['status' => 'Posted', 'paid_at' => now()];
        }

        if (strcasecmp(trim((string) ($status ?? '')), 'Approved') === 0) {
            return ['status' => 'Approved', 'paid_at' => null];
        }

        return ['status' => 'Pending', 'paid_at' => null];
    }

    private function lroLedgerListedStatusesRaw(): string
    {
        return "UPPER(TRIM(COALESCE(status, ''))) IN ('APPROVED', 'POSTED', 'PAID')";
    }

    /**
     * Remove stale lro_ledger rows that match a pending/cancelled billing adjustment memo.
     */
    private function removeMatchingLroLedgerRows(BillingAdjustment $billingAdjustment): void
    {
        $consumerZoneId = $billingAdjustment->consumer_zone_id;
        if (!$consumerZoneId) {
            return;
        }

        $query = LROLedger::query()->where(['consumer_zone_id' => $consumerZoneId]);

        $bamNo = trim((string) ($billingAdjustment->bam_no ?? ''));
        if ($bamNo !== '') {
            $query->where(['bam_no' => $bamNo]);
        } else {
            $query->where([
                'type' => $billingAdjustment->type,
                'amount' => $billingAdjustment->amount,
            ]);

            if (!empty($billingAdjustment->date)) {
                $query->whereDate('date', Carbon::parse($billingAdjustment->date)->format('Y-m-d'));
            }
        }

        $query->delete();
    }

    /**
     * Remove legacy lro_ledger rows that should never appear in the consumer LRO ledger (pending/cancelled).
     */
    private function purgeStalePendingLroLedgerRows(): void
    {
        LROLedger::query()
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN ('PENDING', 'CANCELLED')")
            ->delete();
    }

    private function authUsername(): string
    {
        $user = auth();

        return $user?->name ?? 'SYSTEM';
    }

    /**
     * Resolve consumer by account using exact and normalized matching.
     */
    private function findConsumerForLroAccount(?string $accountNo): ?ConsumerZone
    {
        $accountNo = trim((string) ($accountNo ?? ''));
        if ($accountNo === '') {
            return null;
        }

        $accountNoColumn = 'account_no';
        $consumer = ConsumerZone::query()->where($accountNoColumn, $accountNo)->first();
        if ($consumer) {
            return $consumer;
        }

        $normalized = str_replace('-', '', $accountNo);
        $upper = strtoupper(trim($accountNo));

        return ConsumerZone::query()->where(function ($q) use ($accountNo, $normalized, $upper, $accountNoColumn) {
            $q->where($accountNoColumn, $accountNo)
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
