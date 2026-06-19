<?php

namespace App\Imports;

use App\Models\ConsumerLedger;
use App\Models\ConsumerZone;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class ConsumerLedgerImport implements ToModel, WithHeadingRow, SkipsOnFailure, WithChunkReading
{
    use SkipsFailures;
    
    public $importedCount = 0;
    public $skippedCount = 0;
    public $errors = [];
    public $debugFirstRows = [];
    public $fileHasAccountNo = false;
    
    /**
     * The account number to use if not present in the Excel file
     */
    protected $defaultAccountNo;
    
    /**
     * Constructor - accepts optional account_no for files without account_no column
     */
    public function __construct($defaultAccountNo = null)
    {
        $this->defaultAccountNo = $defaultAccountNo;
    }
    
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
        
        // Get account_no from the uploaded file - try multiple possible column names
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
        
        // Trim any whitespace
        if ($accountNo) {
            $accountNo = trim($accountNo);
        }
        
        // If account_no is not in the file, use the default from the button click
        if (!$accountNo && $this->defaultAccountNo) {
            $accountNo = $this->defaultAccountNo;
        }
        
        if (!$accountNo) {
            $this->skippedCount++;
            if ($rowCount === 1) {
                $this->errors[] = "ERROR: No account number provided. Your file does not have an 'account_no' column. You MUST select an account number when uploading the file.";
            }
            return null;
        }
        
        // Lookup consumer_zone by account_no (try exact match first, then trim both)
        $consumerZone = ConsumerZone::where('account_no', $accountNo)->first();
        
        if (!$consumerZone) {
            // Try with trimmed values in case of whitespace issues
            $consumerZone = ConsumerZone::whereRaw('TRIM(account_no) = ?', [trim($accountNo)])->first();
        }
        
        if (!$consumerZone) {
            $this->skippedCount++;
            $this->errors[] = "Row {$rowCount}: Account [{$accountNo}] not found";
            return null;
        }
        
        // Check if record already exists using cl_ctrl as unique identifier
        $clCtrl = $this->getRowValue($row, ['cl_ctrl', 'cl_ctrl_no', 'clctrl']);
        
        // Allow importing even if record exists (removed duplicate check)
        // This allows re-importing or adding similar records
        
        // Parse date fields
        $dateValue = $this->getRowValue($row, ['date', 'trans_date', 'transaction_date']);
        $date = $this->parseDate($dateValue);
        
        $dueDate = $this->parseDate($this->getRowValue($row, ['due_date', 'duedate', 'due date', 'DUE_DATE']));
        $reference = $this->getRowValue($row, ['reference', 'reference_no', 'REFERENCE']);
        
        try {
            $ledgerData = [
                'consumer_zone_id' => $consumerZone->id,
                'trans'            => $this->getRowValue($row, ['trans', 'transaction', 'transaction_type']),
                'date'             => $date,
                'due_date'         => $dueDate,
                'reference'        => $reference,
                'reading'          => $this->getRowValue($row, ['reading', 'meter_reading', 'current_reading']),
                'volume'           => $this->getRowValue($row, ['volume', 'consumption']),
                'billamount'       => $this->getRowValue($row, ['billamount', 'bill_amount', 'amount']) ?? 0,
                'penalty'          => $this->getRowValue($row, ['penalty']) ?? 0,
                'others'           => $this->getRowValue($row, ['others']) ?? 0,
                'debit'            => $this->getRowValue($row, ['debit']) ?? 0,
                'credit'           => $this->getRowValue($row, ['credit']) ?? 0,
                'balance'          => $this->getRowValue($row, ['balance']) ?? 0,
                'username'         => $this->getRowValue($row, ['username', 'user']),
                'txtime'           => $this->parseDateTime($this->getRowValue($row, ['txtime', 'transaction_time'])),
                'cl_ctrl'          => $clCtrl,
            ];
            
            // Create and save the ledger entry
            $ledger = ConsumerLedger::create($ledgerData);
            
            if ($ledger && $ledger->id) {
                $this->importedCount++;
                Log::info("Ledger entry created successfully", [
                    'id' => $ledger->id,
                    'account_no' => $accountNo,
                    'consumer_zone_id' => $consumerZone->id
                ]);
                return $ledger;
            } else {
                $this->skippedCount++;
                $this->errors[] = "Failed to create ledger row";
                Log::warning("Failed to create ledger row", ['data' => $ledgerData]);
                return null;
            }
        } catch (\Exception $e) {
            $this->skippedCount++;
            $this->errors[] = "Error importing row: " . $e->getMessage();
            Log::error("Error importing ledger row: " . $e->getMessage(), [
                'row' => $row,
                'account_no' => $accountNo,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}

