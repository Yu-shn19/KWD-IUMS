<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Import for previous_reading bulk update.
 * Excel must have header row with: account_no, account_name, previous_reading.
 * Returns raw rows so the controller can validate and map by header.
 */
class PreviousReadingImport implements ToArray, WithStartRow
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
