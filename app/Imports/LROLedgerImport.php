<?php

namespace App\Imports;

use App\Models\LROLedger;
use App\Models\ConsumerZoneOne;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class LROLedgerImport implements ToModel, WithHeadingRow, SkipsOnFailure, WithChunkReading
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

    /**
     * Convert value to decimal/float, handling various formats
     */
    private function parseDecimal($value)
    {
        if (empty($value) || $value === null) {
            return 0;
        }

        // If it's already numeric, return as float
        if (is_numeric($value)) {
            return (float)$value;
        }

        // Try to extract number from string (remove currency symbols, commas, etc.)
        $cleaned = preg_replace('/[^0-9.-]/', '', (string)$value);
        return $cleaned !== '' ? (float)$cleaned : 0;
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
            // Parse date fields
            $billmonth = $this->parseDate($this->getRowValue($row, [
                'billmonth', 
                'bill_month', 
                'bill month',
                'BILLMONTH',
                'billing_month'
            ]));
            
            $cbaDate = $this->parseDate($this->getRowValue($row, [
                'cba_date', 
                'cba date', 
                'CBA_DATE',
                'date'
            ]));
            
            // Get account_no and account_name - these are required for linking to consumer_zone
            $accountNo = $this->getRowValue($row, [
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
            ]);
            
            $accountName = $this->getRowValue($row, [
                'account_name', 
                'account name', 
                'accountname',
                'ACCOUNT_NAME',
                'name'
            ]);
            
            // Skip if account_no is missing (required for linking)
            if (empty($accountNo)) {
                $this->skippedCount++;
                $this->errors[] = "Row {$rowCount}: Missing account_no";
                return null;
            }
            
            // Try to find consumer_zone_id by account_no and account_name
            $consumerZone = null;
            $consumerZoneId = null;
            
            if (!empty($accountNo)) {
                $query = ConsumerZoneOne::where('account_no', $accountNo);
                
                // If account_name is provided, also match by name for better accuracy
                if (!empty($accountName)) {
                    $query->where('account_name', $accountName);
                }
                
                $consumerZone = $query->first();
                
                if ($consumerZone) {
                    $consumerZoneId = $consumerZone->id;
                } else {
                    // Log warning but don't skip - we'll store without consumer_zone_id
                    Log::warning("Consumer zone not found for LRO ledger", [
                        'account_no' => $accountNo,
                        'account_name' => $accountName,
                        'row' => $rowCount
                    ]);
                }
            }
            
            // Build LRO ledger data array
            $lroLedgerData = [
                'billmonth'     => $billmonth,
                'cba_date'      => $cbaDate,
                'cba_type'      => $this->getRowValue($row, [
                    'cba_type', 
                    'cba type', 
                    'CBA_TYPE',
                    'type'
                ]),
                'cba_no'        => $this->getRowValue($row, [
                    'cba_no', 
                    'cba no', 
                    'CBA_NO',
                    'cba_number',
                    'cba number'
                ]),
                'zone_code'     => $this->getRowValue($row, [
                    'zone_code', 
                    'zone code', 
                    'ZONE_CODE',
                    'zone'
                ]),
                'account_no'    => $accountNo,
                'account_name'  => $accountName,
                'cba_remarks'  => $this->getRowValue($row, [
                    'cba_remarks', 
                    'cba remarks', 
                    'CBA_REMARKS',
                    'remarks',
                    'remark'
                ]),
                'ar_dm'         => $this->parseDecimal($this->getRowValue($row, [
                    'ar_dm', 
                    'ar dm', 
                    'AR_DM',
                    'ar debit memo',
                    'ar_debit_memo'
                ])),
                'ar_cm'         => $this->parseDecimal($this->getRowValue($row, [
                    'ar_cm', 
                    'ar cm', 
                    'AR_CM',
                    'ar credit memo',
                    'ar_credit_memo'
                ])),
                'lro_dm'        => $this->parseDecimal($this->getRowValue($row, [
                    'lro_dm', 
                    'lro dm', 
                    'LRO_DM',
                    'lro debit memo',
                    'lro_debit_memo'
                ])),
                'lro_cm'        => $this->parseDecimal($this->getRowValue($row, [
                    'lro_cm', 
                    'lro cm', 
                    'LRO_CM',
                    'lro credit memo',
                    'lro_credit_memo'
                ])),
                'cba_amount'    => $this->parseDecimal($this->getRowValue($row, [
                    'cba_amount', 
                    'cba amount', 
                    'CBA_AMOUNT',
                    'amount'
                ])),
                'acct_group'    => $this->getRowValue($row, [
                    'acct_group', 
                    'acct group', 
                    'ACCT_GROUP',
                    'account_group',
                    'account group'
                ]),
                'consumer_zone_id' => $consumerZoneId,
            ];
            
            // Trim string values
            foreach ($lroLedgerData as $key => $value) {
                if (is_string($value)) {
                    $lroLedgerData[$key] = trim($value);
                }
            }
            
            // Create and save the LRO ledger entry
            $lroLedger = LROLedger::create($lroLedgerData);
            
            if ($lroLedger && $lroLedger->id) {
                $this->importedCount++;
                Log::info("LRO Ledger entry created successfully", [
                    'id' => $lroLedger->id,
                    'account_no' => $lroLedgerData['account_no'] ?? null,
                    'account_name' => $lroLedgerData['account_name'] ?? null,
                    'consumer_zone_id' => $consumerZoneId
                ]);
                return $lroLedger;
            } else {
                $this->skippedCount++;
                $this->errors[] = "Row {$rowCount}: Failed to create LRO ledger row";
                Log::warning("Failed to create LRO ledger row", ['data' => $lroLedgerData]);
                return null;
            }
        } catch (\Exception $e) {
            $this->skippedCount++;
            $this->errors[] = "Row {$rowCount}: Error importing row - " . $e->getMessage();
            Log::error("Error importing LRO ledger row: " . $e->getMessage(), [
                'row' => $row,
                'row_number' => $rowCount,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
