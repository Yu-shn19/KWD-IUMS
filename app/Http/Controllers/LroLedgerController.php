<?php

namespace App\Http\Controllers;

use App\Models\ConsumerZoneOne;
use App\Models\LROLedger;
use Illuminate\Http\Request;

class LroLedgerController extends Controller
{
    /**
     * Store a new LRO Ledger (Billing Adjustment Memo) entry.
     */
    public function store(Request $request)
    {
        $request->merge([
            'date' => $request->input('date') === '' ? null : $request->input('date'),
        ]);

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:20'],
            'date' => ['nullable', 'date'],
            'account' => ['nullable', 'string', 'max:50'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'bam_no' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'ar_type' => ['nullable', 'string', 'max:10'],
            'ar' => ['nullable', 'string', 'max:10'],
            'ledger' => ['nullable', 'string', 'max:10'],
            'acct_code' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
            'correct_reading' => ['nullable', 'numeric'],
        ]);

        $accountNo = trim((string) ($validated['account'] ?? $validated['account_no'] ?? ''));
        $consumerZoneId = null;
        if ($accountNo !== '') {
            $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
            if (!$consumer) {
                $normalized = str_replace('-', '', $accountNo);
                $consumer = ConsumerZoneOne::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
            }
            $consumerZoneId = $consumer?->id;
        }

        $arType = $validated['ar_type'] ?? $validated['ar'] ?? $validated['ledger'] ?? 'LRO';

        $entry = LROLedger::create(LROLedger::filterTableAttributes([
            'consumer_zone_id' => $consumerZoneId,
            'type' => $validated['type'] ?? 'CM',
            'date' => !empty($validated['date']) ? $validated['date'] : null,
            'bam_no' => $validated['bam_no'] ?? $validated['reference'] ?? null,
            'amount' => (float) ($validated['amount'] ?? 0),
            'ledger' => $arType,
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
}
