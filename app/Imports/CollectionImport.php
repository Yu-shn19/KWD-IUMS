<?php

namespace App\Imports;

use App\Models\ConsumerPayment;
use App\Models\ConsumerZone;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class CollectionImport implements ToModel, WithHeadingRow, SkipsOnFailure, WithChunkReading
{
    use SkipsFailures;
    
    public $importedCount = 0;
    public $skippedCount = 0;
    public $errors = [];
    public $debugFirstRows = [];
    
    /**
     * Chunk size for reading Excel file in batches (improves memory usage)
     */
    public function chunkSize(): int
    {
        return 500; // Process 500 rows at a time
    }
    
    /**
     * Parse date from various formats to MySQL date format (Y-m-d)
     */
    private function parseDate($dateValue)
    {
        if (empty($dateValue)) {
            return null;
        }

        // If it's already a valid date string in MySQL format, return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }

        try {
            // Try to parse common Excel date formats
            // Formats like: 16-Apr-18, 16-Apr-2018, Apr-16-18, etc.
            $parsed = Carbon::parse($dateValue);
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            // If parsing fails, try to extract date from datetime strings
            // Format like: "02-Apr-18 13:13:32"
            if (preg_match('/(\d{1,2}-[A-Za-z]{3}-\d{2,4})/', $dateValue, $matches)) {
                try {
                    $parsed = Carbon::parse($matches[1]);
                    return $parsed->format('Y-m-d');
                } catch (\Exception $e2) {
                    return null;
                }
            }
            return null;
        }
    }

    /**
     * Parse datetime from various formats to MySQL datetime format
     */
    private function parseDateTime($dateTimeValue)
    {
        if (empty($dateTimeValue)) {
            return null;
        }

        try {
            // Try to parse datetime strings like "02-Apr-18 13:13:32"
            $parsed = Carbon::parse($dateTimeValue);
            return $parsed->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse time from various formats to MySQL time format (H:i:s)
     */
    private function parseTime($timeValue)
    {
        if (empty($timeValue)) {
            return null;
        }

        try {
            // If it's already in time format (HH:MM:SS or HH:MM), return as is
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeValue)) {
                return strlen($timeValue) === 5 ? $timeValue . ':00' : $timeValue;
            }

            // Try to parse time strings
            $parsed = Carbon::parse($timeValue);
            return $parsed->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper function to get value from row with case-insensitive key matching
     */
    private function getRowValue(array $row, array $possibleKeys)
    {
        // First try exact match
        foreach ($possibleKeys as $key) {
            if (isset($row[$key])) {
                return $row[$key];
            }
        }
        
        // Then try case-insensitive match
        $rowKeysLower = array_change_key_case($row, CASE_LOWER);
        foreach ($possibleKeys as $key) {
            $keyLower = strtolower($key);
            if (isset($rowKeysLower[$keyLower])) {
                return $rowKeysLower[$keyLower];
            }
        }
        
        // Try with spaces replaced with underscores and vice versa
        foreach ($row as $rowKey => $value) {
            $normalizedKey = strtolower(str_replace([' ', '_'], '', $rowKey));
            foreach ($possibleKeys as $key) {
                $normalizedPossible = strtolower(str_replace([' ', '_'], '', $key));
                if ($normalizedKey === $normalizedPossible) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    public function model(array $row)
    {
        // Debug: Store first 3 rows for inspection
        static $rowCount = 0;
        $rowCount++;
        
        if ($rowCount <= 3) {
            $this->debugFirstRows[] = [
                'row_number' => $rowCount,
                'keys' => array_keys($row),
                'values' => $row
            ];
        }
        
        try {
            // Parse date and time fields
            $collDate = $this->parseDate($this->getRowValue($row, [
                'coll_date', 
                'collection_date', 
                'coll date',
                'COLL_DATE'
            ]));
            
            $collTime = $this->parseTime($this->getRowValue($row, [
                'coll_time', 
                'collection_time', 
                'coll time',
                'COLL_TIME'
            ]));
            
            // Build collection data array
            $collectionData = [
                'zone_code'      => $this->getRowValue($row, ['zone_code', 'zone code', 'ZONE_CODE']),
                'sequence'       => $this->getRowValue($row, ['sequence', 'seq', 'SEQUENCE']),
                'account_no'     => $this->getRowValue($row, [
                    'account_no', 
                    'account_number', 
                    'accountnumber',
                    'account no',
                    'Account No',
                    'ACCOUNT_NO',
                    'Acct #',
                    'acct #',
                    'AcctNo',
                    'Acct No'
                ]),
                'account_name'   => $this->getRowValue($row, [
                    'account_name', 
                    'account name', 
                    'accountname',
                    'ACCOUNT_NAME',
                    'name'
                ]),
                'coll_date'      => $collDate,
                'coll_time'      => $collTime,
                'pay_mode'       => $this->getRowValue($row, [
                    'pay_mode', 
                    'pay mode', 
                    'payment_mode',
                    'PAY_MODE',
                    'mode'
                ]),
                'or_number'      => $this->getRowValue($row, [
                    'or_number', 
                    'or number', 
                    'ornumber',
                    'OR_NUMBER',
                    'or_no',
                    'or no',
                    'receipt_number',
                    'receipt number'
                ]),
                'pay_amount'     => $this->getRowValue($row, [
                    'pay_amount', 
                    'pay amount', 
                    'payment_amount',
                    'PAY_AMOUNT',
                    'amount'
                ]) ?? 0,
                'current_bill'   => $this->getRowValue($row, [
                    'current_bill', 
                    'current bill', 
                    'currentbill',
                    'CURRENT_BILL'
                ]) ?? 0,
                'meter_rental'   => $this->getRowValue($row, [
                    'meter_rental', 
                    'meter rental', 
                    'meterrental',
                    'METER_RENTAL'
                ]) ?? 0,
                'arrears'        => $this->getRowValue($row, [
                    'arrears', 
                    'ARREARS'
                ]) ?? 0,
                'penalty'        => $this->getRowValue($row, [
                    'penalty', 
                    'PENALTY'
                ]) ?? 0,
                'materials'      => $this->getRowValue($row, [
                    'materials', 
                    'MATERIALS'
                ]) ?? 0,
                'others'         => $this->getRowValue($row, [
                    'others', 
                    'OTHERS'
                ]) ?? 0,
                'advances'       => $this->getRowValue($row, [
                    'advances', 
                    'ADVANCES'
                ]) ?? 0,
                'sc_discount'    => $this->getRowValue($row, [
                    'sc_discount', 
                    'sc discount', 
                    'scdiscount',
                    'SC_DISCOUNT',
                    'senior_citizen_discount'
                ]) ?? 0,
                'fees_charges'   => $this->getRowValue($row, [
                    'fees_charges', 
                    'fees charges', 
                    'feescharges',
                    'FEES_CHARGES',
                    'fees',
                    'charges'
                ]) ?? 0,
                'materials_loan' => $this->getRowValue($row, [
                    'materials_loan', 
                    'materials loan', 
                    'materialsloan',
                    'MATERIALS_LOAN'
                ]) ?? 0,
                'prev_yr'        => $this->getRowValue($row, [
                    'prev_yr', 
                    'prev yr', 
                    'prevyear',
                    'PREV_YR',
                    'previous_year',
                    'previous year'
                ]) ?? 0,
                'cancel'         => $this->getRowValue($row, [
                    'cancel', 
                    'CANCEL',
                    'cancelled',
                    'cancelled_flag'
                ]),
                'username'       => $this->getRowValue($row, [
                    'username', 
                    'user', 
                    'USERNAME',
                    'user_name',
                    'user name'
                ]),
                'sund1_amt'      => $this->getRowValue($row, [
                    'sund1_amt', 
                    'sund1 amt', 
                    'sund1amt',
                    'SUND1_AMT',
                    'sundry1_amount',
                    'sundry 1 amount'
                ]) ?? 0,
                'sund2_amt'      => $this->getRowValue($row, [
                    'sund2_amt', 
                    'sund2 amt', 
                    'sund2amt',
                    'SUND2_AMT',
                    'sundry2_amount',
                    'sundry 2 amount'
                ]) ?? 0,
                'sund3_amt'      => $this->getRowValue($row, [
                    'sund3_amt', 
                    'sund3 amt', 
                    'sund3amt',
                    'SUND3_AMT',
                    'sundry3_amount',
                    'sundry 3 amount'
                ]) ?? 0,
                'sund4_amt'      => $this->getRowValue($row, [
                    'sund4_amt', 
                    'sund4 amt', 
                    'sund4amt',
                    'SUND4_AMT',
                    'sundry4_amount',
                    'sundry 4 amount'
                ]) ?? 0,
                'sund5_amt'      => $this->getRowValue($row, [
                    'sund5_amt', 
                    'sund5 amt', 
                    'sund5amt',
                    'SUND5_AMT',
                    'sundry5_amount',
                    'sundry 5 amount'
                ]) ?? 0,
                'sund1_code'     => $this->getRowValue($row, [
                    'sund1_code', 
                    'sund1 code', 
                    'sund1code',
                    'SUND1_CODE',
                    'sundry1_code',
                    'sundry 1 code'
                ]),
                'sund2_code'     => $this->getRowValue($row, [
                    'sund2_code', 
                    'sund2 code', 
                    'sund2code',
                    'SUND2_CODE',
                    'sundry2_code',
                    'sundry 2 code'
                ]),
                'sund3_code'     => $this->getRowValue($row, [
                    'sund3_code', 
                    'sund3 code', 
                    'sund3code',
                    'SUND3_CODE',
                    'sundry3_code',
                    'sundry 3 code'
                ]),
                'sund4_code'     => $this->getRowValue($row, [
                    'sund4_code', 
                    'sund4 code', 
                    'sund4code',
                    'SUND4_CODE',
                    'sundry4_code',
                    'sundry 4 code'
                ]),
                'sund5_code'     => $this->getRowValue($row, [
                    'sund5_code', 
                    'sund5 code', 
                    'sund5code',
                    'SUND5_CODE',
                    'sundry5_code',
                    'sundry 5 code'
                ]),
            ];
            
            // Trim string values
            foreach ($collectionData as $key => $value) {
                if (is_string($value)) {
                    $collectionData[$key] = trim($value);
                }
            }

            $cancel = strtoupper(trim((string) ($collectionData['cancel'] ?? '')));
            if (in_array($cancel, ['Y', 'YES', '1'], true)) {
                $this->skippedCount++;
                return null;
            }

            $accountNo = trim((string) ($collectionData['account_no'] ?? ''));
            if ($accountNo === '') {
                $this->skippedCount++;
                $this->errors[] = "Row {$rowCount}: Missing account number";
                return null;
            }

            $consumer = ConsumerZone::query()->where(mr_col('account_no'), $accountNo)->first();
            if (!$consumer) {
                $normalized = str_replace('-', '', $accountNo);
                $consumer = ConsumerZone::query()->whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
            }

            if (!$consumer) {
                $this->skippedCount++;
                $this->errors[] = "Row {$rowCount}: Consumer not found for account {$accountNo}";
                return null;
            }

            $paidAt = null;
            if ($collDate) {
                $paidAt = $collDate . ' ' . ($collTime ?: '00:00:00');
            }

            $paymentPayload = ConsumerPayment::filterTableAttributes([
                'consumer_zone_id' => $consumer->id,
                'payment_method' => $collectionData['pay_mode'] ?? null,
                'payment_amount' => (float) ($collectionData['pay_amount'] ?? 0),
                'senior_citizen_discount' => (float) ($collectionData['sc_discount'] ?? 0),
                'current_bill' => (float) ($collectionData['current_bill'] ?? 0),
                'penalty' => (float) ($collectionData['penalty'] ?? 0),
                'meter_maintenance' => (float) ($collectionData['meter_rental'] ?? 0),
                'arrears_cy' => (float) ($collectionData['arrears'] ?? 0),
                'arrears_py' => (float) ($collectionData['prev_yr'] ?? 0),
                'others' => (float) ($collectionData['others'] ?? 0),
                'advances' => (float) ($collectionData['advances'] ?? 0),
                'or_number' => $collectionData['or_number'] ?? null,
                'paid_at' => $paidAt,
                'created_by' => $collectionData['username'] ?? null,
            ]);

            $payment = ConsumerPayment::create($paymentPayload);

            if ($payment && $payment->id) {
                $this->importedCount++;
                Log::info('Consumer payment created from collection import', [
                    'id' => $payment->id,
                    'account_no' => $accountNo,
                    'or_number' => $paymentPayload['or_number'] ?? null,
                ]);
                return $payment;
            }

            $this->skippedCount++;
            $this->errors[] = "Row {$rowCount}: Failed to create payment row";
            Log::warning('Failed to create consumer payment from import row', ['data' => $paymentPayload]);
            return null;
        } catch (\Exception $e) {
            $this->skippedCount++;
            $this->errors[] = "Row {$rowCount}: Error importing row - " . $e->getMessage();
            Log::error("Error importing collection row: " . $e->getMessage(), [
                'row' => $row,
                'row_number' => $rowCount,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
