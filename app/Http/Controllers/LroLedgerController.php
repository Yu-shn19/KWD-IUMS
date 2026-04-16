<?php

namespace App\Http\Controllers;

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

        $entry = LROLedger::create([
            'type' => $validated['type'] ?? 'CM',
            'date' => !empty($validated['date']) ? $validated['date'] : null,
            'account' => $validated['account'] ?? null,
            'name' => $name,
            'bam_no' => $validated['bam_no'] ?? null,
            'amount' => (float) ($validated['amount'] ?? 0),
            'ar_type' => $arType,
            'acct_code' => $validated['acct_code'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'] ?? 'Pending',
            'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Billing adjustment saved.',
            'data' => ['id' => $entry->id],
        ]);
    }
}
