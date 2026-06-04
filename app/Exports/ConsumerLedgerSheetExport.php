<?php

namespace App\Exports;

use App\Models\ConsumerZoneOne;
use App\Services\ConsumerLedgerCardBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ConsumerLedgerSheetExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /** @var int Header row with grouped BILLINGS / PAYMENT / BALANCE */
    private const HEADER_GROUP_ROW = 5;

    /** @var int Header row with w/Sales, Penalties, M.R sub-columns */
    private const HEADER_SUB_ROW = 6;

    /** @var int First data row */
    private const DATA_START_ROW = 7;

    public function __construct(
        protected ConsumerZoneOne $consumer,
        protected Collection $ledgerRows,
        protected string $sheetTitle,
        protected string $asOf = '',
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $c = $this->consumer;
        $rows = [
            ['Name: '.($c->account_name ?? '')],
            ['Acct. # '.($c->account_no ?? '')],
            ['Meter No.', self::formatMeterNumber($c->meter_number)],
            ['', ''],
        ];

        foreach ($this->ledgerRows as $row) {
            $rows[] = [
                $row['date'] ?? '',
                $row['reference'] ?? '',
                $row['reading'] ?? '',
                $row['consumption'] ?? '',
                ConsumerLedgerCardBuilder::formatMoney($row['bill_sales'] ?? 0),
                ConsumerLedgerCardBuilder::formatMoney($row['bill_penalty'] ?? 0),
                ConsumerLedgerCardBuilder::formatMoney($row['bill_mr'] ?? 0),
                ConsumerLedgerCardBuilder::formatMoney($row['pay_sales'] ?? 0),
                ConsumerLedgerCardBuilder::formatMoney($row['pay_penalty'] ?? 0),
                ConsumerLedgerCardBuilder::formatMoney($row['pay_mr'] ?? 0),
                ConsumerLedgerCardBuilder::formatMoney($row['bal_sales'] ?? 0, false),
                ConsumerLedgerCardBuilder::formatMoney($row['bal_penalty'] ?? 0, false),
                ConsumerLedgerCardBuilder::formatMoney($row['bal_mr'] ?? 0, false),
                '',
                ConsumerLedgerCardBuilder::formatMoney($row['total_balance'] ?? 0, false),
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $groupRow = self::HEADER_GROUP_ROW;
                $subRow = self::HEADER_SUB_ROW;
                $dataStart = self::DATA_START_ROW;
                $ledgerCount = $this->ledgerRows->count();
                $c = $this->consumer;

                // Name and account number span columns A–E only
                $sheet->setCellValue('A1', 'Name: ');
                $sheet->setCellValue('B1', ($c->account_name ?? ''));
                $sheet->mergeCells('B1:E1');
                $sheet->setCellValue('A2', 'Acct. # ');
                $sheet->setCellValue('B2',($c->account_no ?? ''));
                $sheet->mergeCells('B2:E2');

                // FromArray writes data at row 5; make room for two header rows
                $sheet->insertNewRowBefore($groupRow, 2);

                // Table headers only in AfterSheet (not duplicated in FromArray)
                $sheet->mergeCells("A{$groupRow}:A{$subRow}");
                $sheet->mergeCells("B{$groupRow}:B{$subRow}");
                $sheet->mergeCells("C{$groupRow}:C{$subRow}");
                $sheet->mergeCells("D{$groupRow}:D{$subRow}");

                $sheet->setCellValue("A{$groupRow}", 'Date');
                $sheet->setCellValue("B{$groupRow}", 'O.R/Billde');
                $sheet->setCellValue("C{$groupRow}", 'Reading');
                $sheet->setCellValue("D{$groupRow}", 'Cuns.');

                $sheet->mergeCells("E{$groupRow}:G{$groupRow}");
                $sheet->setCellValue("E{$groupRow}", 'BILLINGS');
                $sheet->mergeCells("H{$groupRow}:J{$groupRow}");
                $sheet->setCellValue("H{$groupRow}", 'PAYMENT');
                $sheet->mergeCells("K{$groupRow}:M{$groupRow}");
                $sheet->setCellValue("K{$groupRow}", 'BALANCE');

                $sheet->setCellValue("E{$subRow}", 'w/Sales');
                $sheet->setCellValue("F{$subRow}", 'Penalties');
                $sheet->setCellValue("G{$subRow}", 'M.R');
                $sheet->setCellValue("H{$subRow}", 'w/Sales');
                $sheet->setCellValue("I{$subRow}", 'Penalties');
                $sheet->setCellValue("J{$subRow}", 'M.R');
                $sheet->setCellValue("K{$subRow}", 'w/Sales');
                $sheet->setCellValue("L{$subRow}", 'Penalties');
                $sheet->setCellValue("M{$subRow}", 'M.R');

                $sheet->mergeCells("O{$groupRow}:O{$subRow}");
                $sheet->setCellValue("O{$groupRow}", 'Total Amount');

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3')->getFont()->setBold(true);
                $sheet->getStyle("A{$groupRow}:O{$subRow}")->getFont()->setBold(true);

                $sheet->freezePane('A'.$dataStart);

                $lastRow = $dataStart - 1 + $ledgerCount;

                // Consumer info rows (Name, Acct, Meter) — left-aligned only
                $sheet->getStyle('A1:E3')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                if ($lastRow >= $groupRow) {
                    $sheet->getStyle("A{$groupRow}:O{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                if ($lastRow >= $groupRow) {
                    $sheet->getStyle("A{$groupRow}:O{$lastRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("A{$groupRow}:O{$subRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF2F2F2');
                }
            },
        ];
    }

    public static function formatMeterNumber(mixed $meterNumber): string
    {
        $value = trim((string) ($meterNumber ?? ''));

        return $value;
    }

    public static function makeSheetTitle(string $accountNo, string $name, array &$usedTitles): string
    {
        $raw = trim($accountNo.' '.($name ?: ''));
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $raw) ?: 'Ledger';
        $title = mb_substr($title, 0, 31);

        $base = $title;
        $suffix = 2;
        while (in_array($title, $usedTitles, true)) {
            $extra = ' ('.$suffix.')';
            $title = mb_substr($base, 0, 31 - mb_strlen($extra)).$extra;
            $suffix++;
        }

        $usedTitles[] = $title;

        return $title;
    }
}
