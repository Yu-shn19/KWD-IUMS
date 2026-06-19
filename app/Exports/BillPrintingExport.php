<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BillPrintingExport implements FromCollection, WithHeadings, WithTitle
{
    /** @param Collection<int, array<string, string>> $records */
    public function __construct(
        protected Collection $records,
    ) {}

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'Account #',
            'Account Name',
            'Meter #',
            'Consumption',
            'Current Bill',
            'Water Maintenance Charge',
            'Total Amount',
        ];
    }

    public function title(): string
    {
        return 'Bill Printing';
    }
}
