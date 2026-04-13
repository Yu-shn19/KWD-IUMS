<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Import class for bulk DM upload. Excel must have header row with:
 * - account_no (or account_number, etc.)
 * - amount
 * Date is fixed to 2026-02-27 in the controller.
 * Returns raw rows (numeric indices) so the controller can map by header.
 */
class DmLedgerImport implements ToArray, WithStartRow
{
    public function startRow(): int
    {
        return 1; // Header on row 1, data from row 2
    }

    public function array(array $array)
    {
        return $array;
    }
}

