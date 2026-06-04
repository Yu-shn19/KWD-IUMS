<?php

namespace App\Exports;

use App\Services\ConsumerLedgerCardBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ConsumerLedgerMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected Collection $consumers,
        protected array $filters,
    ) {}

    public function sheets(): array
    {
        $builder = app(ConsumerLedgerCardBuilder::class);
        $usedTitles = [];
        $sheets = [];

        foreach ($this->consumers as $consumer) {
            $title = ConsumerLedgerSheetExport::makeSheetTitle(
                (string) ($consumer->account_no ?? ''),
                (string) ($consumer->account_name ?? ''),
                $usedTitles
            );

            $card = $builder->build($consumer, $this->filters);
            $rows = $card['rows'];

            $sheets[] = new ConsumerLedgerSheetExport(
                $consumer,
                $rows,
                $title,
                (string) ($this->filters['as_of'] ?? '')
            );
        }

        return $sheets;
    }
}
