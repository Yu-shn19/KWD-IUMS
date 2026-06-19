<?php

namespace App\Support;

/**
 * Account codes used for SUNDRIES on Billing Payment (LRO ledger only).
 */
class SundryAccountCodes
{
    public const CODES = [
        '19901020',
        '19901030',
        '19901040',
    ];

    public static function isSundry(?string $acctCode): bool
    {
        $code = trim((string) ($acctCode ?? ''));

        return $code !== '' && in_array($code, self::CODES, true);
    }

    public static function defaultChargeType(?string $acctCode): string
    {
        return self::isSundry($acctCode) ? 'DM' : 'CM';
    }
}
