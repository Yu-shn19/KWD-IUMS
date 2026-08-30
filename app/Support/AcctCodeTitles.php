<?php

namespace App\Support;

class AcctCodeTitles
{
    public const TITLES = [
        '19901020' => 'Advances for Payroll',
        '19901030' => 'Advances to Special Disbursing Offices',
        '19901040' => 'Advances to Officers and Employees',
        '20102040' => 'Loans Payable-Domestic - Non-Current',
        '20401090' => "Customer's Deposit Payable",
        '40201990' => 'Other Service Income',
        '40603990' => 'Miscellineous Income',
    ];

    public static function titleFor(?string $acctCode): ?string
    {
        $code = trim((string) ($acctCode ?? ''));

        if ($code === '') {
            return null;
        }

        return self::TITLES[$code] ?? null;
    }

    public static function resolve(?string $acctCode, ?string $remarks = null, ?string $fallback = null): string
    {
        $fromCode = self::titleFor($acctCode);
        if ($fromCode !== null) {
            return $fromCode;
        }

        $remarksText = trim((string) ($remarks ?? ''));
        if ($remarksText !== '') {
            return $remarksText;
        }

        $codeText = trim((string) ($acctCode ?? ''));
        if ($codeText !== '') {
            return $codeText;
        }

        return trim((string) ($fallback ?? ''));
    }
}
