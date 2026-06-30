<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Only read consumer_zone import columns (A–I). Some spreadsheets report a huge
 * used range (e.g. column XDR) which exhausts memory without this filter.
 */
class ConsumerZoneColumnReadFilter implements IReadFilter
{
    private const MAX_COLUMN_INDEX = 9;

    public function readCell($column, $row, $worksheetName = ''): bool
    {
        return Coordinate::columnIndexFromString((string) $column) <= self::MAX_COLUMN_INDEX;
    }
}
