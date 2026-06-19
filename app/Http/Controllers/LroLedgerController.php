<?php

namespace App\Http\Controllers;

use App\Models\BillingAdjustment;
use App\Models\ConsumerZone;
use App\Models\LROLedger;
use Illuminate\Http\Request;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

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
            $accountNoColumn = mr_col('account_no');
            $consumer = ConsumerZone::query()->where($accountNoColumn, $accountNo)->first();
            if (!$consumer) {
                $normalized = str_replace('-', '', $accountNo);
                $consumer = ConsumerZone::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
            }
            $consumerZoneId = $consumer?->id;
        }

        $arType = $validated['ar_type'] ?? $validated['ar'] ?? $validated['ledger'] ?? 'LRO';
        $statusRaw = $validated['status'] ?? 'Pending';
        $payload = [
            'consumer_zone_id' => $consumerZoneId,
            'type' => $validated['type'] ?? 'CM',
            'date' => !empty($validated['date']) ? $validated['date'] : null,
            'bam_no' => $validated['bam_no'] ?? $validated['reference'] ?? null,
            'amount' => (float) ($validated['amount'] ?? 0),
            'ledger' => $arType,
            'acct_code' => $validated['acct_code'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'correct_reading' => isset($validated['correct_reading']) ? (float) $validated['correct_reading'] : 0,
        ];

        if ($this->shouldPostToLroLedger($statusRaw)) {
            $normalized = strtoupper(trim((string) $statusRaw));
            $lroStatus = in_array($normalized, ['POSTED', 'PAID'], true)
                ? ['status' => 'Posted', 'paid_at' => now()]
                : ['status' => 'Approved', 'paid_at' => null];

            $entry = LROLedger::create(LROLedger::filterTableAttributes(array_merge($payload, $lroStatus)));

            return response()->json([
                'success' => true,
                'message' => 'Billing adjustment saved to LRO ledger.',
                'data' => ['id' => $entry->id],
            ]);
        }

        $billingAdjustment = BillingAdjustment::create([
            'type' => $validated['type'] ?? 'CM',
            'ledger' => 'LRO',
            'date' => !empty($validated['date']) ? $validated['date'] : null,
            'bam_no' => $validated['bam_no'] ?? $validated['reference'] ?? null,
            'consumer_zone_id' => $consumerZoneId,
            'amount' => (float) ($validated['amount'] ?? 0),
            'acct_code' => $validated['acct_code'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => strcasecmp(trim($statusRaw), 'Cancelled') === 0 ? 'Cancelled' : 'Pending',
            'connect_reading' => isset($validated['correct_reading']) ? (int) $validated['correct_reading'] : 0,
            'username' => auth()->user()?->name ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Billing adjustment saved (pending — not posted to ledger yet).',
            'data' => ['id' => $billingAdjustment->id],
        ]);
    }

    private function shouldPostToLroLedger(?string $status): bool
    {
        $normalized = strtoupper(trim((string) ($status ?? '')));

        return in_array($normalized, ['APPROVED', 'POSTED', 'PAID'], true);
    }
}
