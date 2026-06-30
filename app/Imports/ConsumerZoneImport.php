<?php

namespace App\Imports;

use App\Models\ConsumerZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ConsumerZoneImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, SkipsOnFailure, WithColumnLimit, WithReadFilter
{
    use SkipsFailures;

    /**
     * Excel header => consumer_zone column.
     *
     * @var array<string, string>
     */
    private const COLUMN_MAP = [
        'zone_code' => 'zone_code',
        'last_name' => 'last_name',
        'first_name' => 'first_name',
        'account_number' => 'account_no',
        'account_no' => 'account_no',
        'category_code' => 'category_code',
        'account_name' => 'account_name',
        'address' => 'address',
        'status_code' => 'status_code',
        'bill_disc_percent' => 'bill_disc_percent',
    ];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<int, array<string, mixed>> */
    public array $debugFirstRows = [];

    private int $rowCount = 0;

    public function __construct()
    {
        HeadingRowFormatter::default(HeadingRowFormatter::FORMATTER_NONE);
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function endColumn(): string
    {
        return 'I';
    }

    public function readFilter(): IReadFilter
    {
        return new ConsumerZoneColumnReadFilter();
    }

    public function collection(Collection $rows): void
    {
        $chunkImported = 0;
        $chunkUpdated = 0;

        Log::info('Consumer zone import processing started', [
            'rows_in_file' => $rows->count(),
        ]);

        $accountNos = [];
        $parsedRows = [];

        foreach ($rows as $row) {
            $this->rowCount++;
            $rowArray = $this->normalizeRowKeys($row instanceof Collection ? $row->toArray() : (array) $row);

            if ($this->rowCount <= 3) {
                $this->debugFirstRows[] = [
                    'row_number' => $this->rowCount,
                    'keys' => array_keys($rowArray),
                    'values' => $rowArray,
                ];
            }

            $payload = $this->buildRowPayload($rowArray);
            if ($payload === null) {
                $this->skippedCount++;

                continue;
            }

            $accountNos[] = $payload['account_no'];
            $parsedRows[] = $payload;
        }

        if ($parsedRows === []) {
            Log::info('Consumer zone import finished (no valid rows)');

            return;
        }

        $existingByKey = $this->loadExistingConsumers($accountNos);

        DB::transaction(function () use ($parsedRows, &$existingByKey, &$chunkImported, &$chunkUpdated) {
            foreach ($parsedRows as $payload) {
                $lookupKey = $this->normalizeAccountKey($payload['account_no']);
                $existing = $existingByKey[$lookupKey] ?? null;

                try {
                    if ($existing) {
                        ConsumerZone::syncInstallActivationFields($payload, $existing);
                        $existing->update($payload);
                        $this->updatedCount++;
                        $chunkUpdated++;
                    } else {
                        ConsumerZone::syncInstallActivationFields($payload);
                        $created = ConsumerZone::create($payload);
                        $this->importedCount++;
                        $chunkImported++;
                        $existingByKey[$lookupKey] = $created;
                    }
                } catch (\Throwable $e) {
                    $this->skippedCount++;
                    $this->errors[] = "Account {$payload['account_no']}: {$e->getMessage()}";
                    Log::error('Consumer zone import save failed', [
                        'account_no' => $payload['account_no'],
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Consumer zone import processing finished', [
            'imported' => $chunkImported,
            'updated' => $chunkUpdated,
            'total_rows_processed' => $this->rowCount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function buildRowPayload(array $row): ?array
    {
        try {
            $mapped = $this->mapColumns($row);

            $accountNo = trim((string) ($mapped['account_no'] ?? ''));
            if ($accountNo === '') {
                $this->errors[] = "Row {$this->rowCount}: Missing account_number";

                return null;
            }

            $lastName = trim((string) ($mapped['last_name'] ?? ''));
            $firstName = trim((string) ($mapped['first_name'] ?? ''));
            $accountName = trim((string) ($mapped['account_name'] ?? ''));

            if ($accountName === '' && ($firstName !== '' || $lastName !== '')) {
                $accountName = trim("{$lastName}, {$firstName}", ', ');
            }

            if ($accountName === '') {
                $accountName = $accountNo;
            }

            if ($firstName === '') {
                $firstName = $accountName;
            }

            if ($lastName === '') {
                $lastName = $accountName;
            }

            $address = trim((string) ($mapped['address'] ?? ''));
            if ($address === '') {
                $address = '-';
            }

            return [
                'zone_code' => $this->nullableString($mapped['zone_code'] ?? null),
                'last_name' => $lastName,
                'first_name' => $firstName,
                'account_no' => $accountNo,
                'category_code' => $this->nullableString($mapped['category_code'] ?? null),
                'account_name' => $accountName,
                'address' => $address,
                'status_code' => $this->nullableString($mapped['status_code'] ?? null),
                'bill_disc_percent' => $this->nullableString($mapped['bill_disc_percent'] ?? null),
            ];
        } catch (\Throwable $e) {
            $this->errors[] = "Row {$this->rowCount}: {$e->getMessage()}";

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapColumns(array $row): array
    {
        $mapped = [];

        foreach ($row as $header => $value) {
            $normalizedHeader = $this->normalizeHeaderKey((string) $header);
            $dbColumn = self::COLUMN_MAP[$normalizedHeader] ?? null;

            if ($dbColumn === null) {
                continue;
            }

            if ($dbColumn === 'account_no') {
                $mapped['account_no'] = $this->formatAccountNo($value);
            } else {
                $mapped[$dbColumn] = $this->formatCellValue($value);
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeaderKey((string) $key)] = $value;
        }

        return $normalized;
    }

    private function normalizeHeaderKey(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower($header);
        $header = str_replace([' ', '-'], '_', $header);

        return $header;
    }

    private function formatAccountNo(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            return trim((string) (is_float($value) ? sprintf('%.0f', $value) : $value));
        }

        return trim((string) $value);
    }

    private function formatCellValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            if (is_float($value) && floor($value) == $value) {
                return (string) (int) $value;
            }

            return rtrim(rtrim((string) $value, '0'), '.');
        }

        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, string>  $accountNos
     * @return array<string, ConsumerZone>
     */
    private function loadExistingConsumers(array $accountNos): array
    {
        $accountNos = array_values(array_unique(array_filter(array_map('trim', $accountNos))));
        if ($accountNos === []) {
            return [];
        }

        $normalized = array_values(array_unique(array_map([$this, 'normalizeAccountKey'], $accountNos)));

        $consumers = ConsumerZone::query()
            ->where(function ($query) use ($accountNos, $normalized) {
                $query->whereIn('account_no', $accountNos);

                if ($normalized !== []) {
                    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
                    $query->orWhereRaw(
                        "REPLACE(TRIM(account_no), '-', '') IN ({$placeholders})",
                        $normalized
                    );
                }
            })
            ->get();

        $indexed = [];
        foreach ($consumers as $consumer) {
            $indexed[$this->normalizeAccountKey((string) $consumer->account_no)] = $consumer;
        }

        return $indexed;
    }

    private function normalizeAccountKey(string $accountNo): string
    {
        return strtoupper(str_replace('-', '', trim($accountNo)));
    }
}
