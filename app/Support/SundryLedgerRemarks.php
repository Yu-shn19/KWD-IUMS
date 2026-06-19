<?php

namespace App\Support;

use App\Models\LROLedger;

/**
 * Link LRO sundry payment CM rows to DM charge rows via remarks (no extra DB column).
 */
class SundryLedgerRemarks
{
    public static function paymentRemark(string $orNumber, int $chargeId): string
    {
        return 'Payment OR#' . trim($orNumber) . '|charge:' . $chargeId;
    }

    public static function chargeIdFromRemarks(?string $remarks): ?int
    {
        if (preg_match('/\|charge:(\d+)/', (string) $remarks, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public static function orNumberFromRemarks(?string $remarks): ?string
    {
        if (preg_match('/^Payment OR#([^|]+)/', trim((string) $remarks), $matches)) {
            $or = trim($matches[1]);

            return $or !== '' ? $or : null;
        }

        return null;
    }

    /**
     * Sum LRO payment CM rows per OR for Service Rev. (648) on collection reports.
     *
     * @param  iterable<string|int|null>  $orNumbers
     * @return array<string, float>
     */
    public static function serviceRevTotalsByOr(iterable $orNumbers): array
    {
        $totals = [];
        foreach ($orNumbers as $or) {
            $key = trim((string) $or);
            if ($key === '' || strtoupper($key) === 'N/A') {
                continue;
            }
            $totals[$key] = 0.0;
        }

        if ($totals === []) {
            return $totals;
        }

        $orLookup = array_fill_keys(array_keys($totals), true);
        $orKeys = array_keys($totals);

        $rows = LROLedger::query()
            ->whereRaw("UPPER(TRIM(COALESCE(ledger, ''))) = 'LRO'")
            ->whereRaw("UPPER(TRIM(COALESCE(type, ''))) = 'CM'")
            ->where(function ($query) use ($orKeys) {
                foreach ($orKeys as $or) {
                    $query->orWhere('remarks', 'like', 'Payment OR#' . $or . '%');
                }
            })
            ->get(['remarks', 'amount']);

        foreach ($rows as $row) {
            $or = self::orNumberFromRemarks($row->remarks);
            if ($or === null || !isset($orLookup[$or])) {
                continue;
            }
            $totals[$or] += round((float) ($row->amount ?? 0), 2);
        }

        return $totals;
    }

    public static function serviceRevTotalForOrNumbers(iterable $orNumbers): float
    {
        return array_sum(self::serviceRevTotalsByOr($orNumbers));
    }

    public static function paidAmountForCharge(int $consumerZoneId, int $chargeId): float
    {
        if ($chargeId <= 0) {
            return 0.0;
        }

        $needle = '|charge:' . $chargeId;

        return (float) LROLedger::forConsumerZone($consumerZoneId)
            ->whereRaw("UPPER(TRIM(COALESCE(ledger, ''))) = 'LRO'")
            ->whereRaw("UPPER(TRIM(COALESCE(type, ''))) = 'CM'")
            ->whereRaw('remarks LIKE ?', ['%' . $needle])
            ->sum('amount');
    }
}
