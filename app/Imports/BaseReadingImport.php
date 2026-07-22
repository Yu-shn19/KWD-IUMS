<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Import for base_reading bulk update on consumer_zone.
 * Excel must have header row with: account_no, account_name, base_reading.
 * Returns raw rows so the controller can validate and map by header.
 */
class BaseReadingImport implements ToArray, WithStartRow
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
