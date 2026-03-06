<?php

namespace App\Http\Controllers;

use App\Imports\LROLedgerImport;
use App\Models\LROLedger;
use App\Models\LroLedgers;
use App\Models\ConsumerZoneOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LRO_ConsumerLedgerController extends Controller
{
    /**
     * Display the LRO Ledger import page
     */
    public function index()
    {
        return view('lro-ledger.import');
    }

    /**
     * Import LRO Ledger data from Excel file
     */
    public function import(Request $request)
    {
        // Increase execution time limit for large file imports
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        
        Log::info('LRO Ledger import request received', [
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'no file',
            'method' => $request->method(),
        ]);

        try {
            // Custom validation for Excel files
            $uploadedFile = $request->file('file');
            
            if (!$uploadedFile) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['Please select a file to upload.']
                ]);
            }
            
            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $mimeType = $uploadedFile->getMimeType();
            $allowedExtensions = ['xls', 'xlsx', 'xltx', 'xlsm', 'csv', 'txt'];
            $allowedMimeTypes = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
                'application/vnd.ms-excel.sheet.macroEnabled.12',
                'application/vnd.ms-office',
                'application/octet-stream',
                'text/plain',
                'text/csv',
                'application/csv'
            ];
            
            // Validate by extension OR MIME type
            if (!in_array($extension, $allowedExtensions) && !in_array($mimeType, $allowedMimeTypes)) {
                Log::error('File validation failed', [
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'original_name' => $uploadedFile->getClientOriginalName()
                ]);
                
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['Please upload a valid Excel file (.xls, .xlsx, .xltx) or CSV file. Your file extension is: ' . $extension . ', MIME type is: ' . $mimeType]
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'message' => $e->getMessage()
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return back()->withErrors($e->errors())->with('error', 'Validation failed: ' . implode(', ', $e->errors()['file'] ?? []));
        }

        try {
            Log::info('Starting LRO Ledger import');
            
            $uploadedFile = $request->file('file');
            
            // Create import instance
            $import = new LROLedgerImport();
            
            // Count existing records before import
            $beforeCount = LROLedger::count();
            
            // Ensure the file is actually readable
            $filePath = $uploadedFile->getRealPath();
            if (!$filePath || !is_readable($filePath)) {
                throw new \Exception('The uploaded file cannot be read. Please ensure the file is not corrupted.');
            }
            
            Log::info('File path before import', [
                'file_path' => $filePath,
                'exists' => file_exists($filePath),
                'readable' => is_readable($filePath),
                'size' => filesize($filePath)
            ]);
            
            // Import the file
            try {
                Excel::import($import, $uploadedFile);
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                if (strpos($e->getMessage(), 'OLE') !== false || strpos($e->getMessage(), 'not recognised') !== false) {
                    Log::error('OLE file read error', [
                        'message' => $e->getMessage(),
                        'file_path' => $filePath,
                        'extension' => $extension
                    ]);
                    throw new \Exception('The Excel file appears to be corrupted or is not in a valid Excel format. Please try re-saving the file in Excel and upload again.');
                }
                throw $e;
            }
            
            // Count records after import
            $afterCount = LROLedger::count();
            $importedCount = $afterCount - $beforeCount;
            
            // Get import statistics
            $imported = $import->importedCount ?? $importedCount;
            $skipped = $import->skippedCount ?? 0;
            $errors = $import->errors ?? [];
            
            Log::info('LRO Ledger import completed', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'beforeCount' => $beforeCount,
                'afterCount' => $afterCount
            ]);
            
            if ($imported > 0) {
                $message = "LRO Ledger imported successfully! {$imported} record(s) imported.";
                if ($skipped > 0) {
                    $message .= " {$skipped} row(s) skipped.";
                }
                
                Log::info('Returning success response', ['message' => $message]);
                
                session()->flash('success', $message);
                session()->flash('import_success', true);
                
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'import_success' => true,
                        'imported' => $imported,
                        'skipped' => $skipped
                    ]);
                }
                
                return back()->with([
                    'success' => $message,
                    'import_success' => true
                ]);
            } else {
                $message = 'Import failed. No new records were imported. ' . $skipped . ' row(s) were skipped.';
                
                if (!empty($errors)) {
                    $sampleErrors = array_slice($errors, 0, 5);
                    $message .= ' Issues: ' . implode(' | ', $sampleErrors);
                    
                    Log::warning('Import failed', [
                        'errors' => $errors,
                        'debug_info' => !empty($import->debugFirstRows) ? $import->debugFirstRows[0] : []
                    ]);
                } else {
                    $message .= ' Please verify your file format matches the expected structure.';
                    Log::warning('No records imported', ['message' => $message]);
                }
                
                session()->flash('warning', $message);
                
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'imported' => $imported,
                        'skipped' => $skipped,
                        'errors' => $errors
                    ], 422);
                }
                
                return back()->with('warning', $message);
            }
        } catch (\Exception $e) {
            Log::error('LRO Ledger import error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            $errorMessage = 'An error occurred during import: ' . $e->getMessage();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }
            
            return back()->with('error', $errorMessage);
        }
    }

    /**
     * Get LRO Ledger data for a consumer by account number
     */
    public function getLROLedger(Request $request)
    {
        // Ensure string so leading zeros (e.g. 011-12-130) are not lost
        $accountNo = trim((string) $request->input('account_no'));
        $year = $request->input('year', '');

        Log::info('LRO Ledger request received', [
            'account_no' => $accountNo,
            'year' => $year
        ]);

        if ($accountNo === '') {
            return response()->json([
                'success' => false,
                'message' => 'Account number is required'
            ], 400);
        }

        $normalizedInput = str_replace('-', '', strtoupper($accountNo));

        // Find consumer: exact match first, then normalized (handles 011-12-130 vs 01112130 in DB)
        $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
        if (!$consumer) {
            $consumer = ConsumerZoneOne::whereRaw(
                "UPPER(TRIM(REPLACE(COALESCE(account_no, ''), '-', ''))) = ?",
                [$normalizedInput]
            )->first();
        }

        if (!$consumer) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer not found'
            ], 404);
        }

        // Use consumer's account_no from DB for exact match; normalized for flexible match
        $consumerAccount = trim((string) $consumer->account_no);
        $normalizedAccountNo = str_replace('-', '', strtoupper($consumerAccount));
        if ($normalizedAccountNo === '') {
            $normalizedAccountNo = $normalizedInput;
        }

        Log::info('LRO Ledger: Query parameters', [
            'account_no' => $accountNo,
            'consumer_account' => $consumerAccount,
            'normalized' => $normalizedAccountNo,
            'consumer_id' => $consumer->id,
            'year' => $year
        ]);

        // --- 1) Fetch from lro_ledger (BAM entries: account, date, type, amount)
        $fromSingular = LROLedger::where(function ($query) use ($consumerAccount, $normalizedAccountNo) {
            $query->where('account', $consumerAccount)
                ->orWhereRaw(
                    "UPPER(TRIM(REPLACE(COALESCE(account, ''), '-', ''))) = ?",
                    [$normalizedAccountNo]
                );
        })
            ->orderByRaw('COALESCE(date, "1970-01-01") ASC')
            ->orderBy('id', 'asc')
            ->get();

        $rawRows = [];
        foreach ($fromSingular as $ledger) {
            $amount = (float)($ledger->amount ?? 0);
            $typeUpper = strtoupper((string)($ledger->type ?? ''));
            $debit = $typeUpper === 'DM' ? $amount : 0;
            $credit = $typeUpper === 'CM' ? $amount : 0;
            $d = $ledger->date ?? null;
            $sortDate = $d ? \Carbon\Carbon::parse($d) : \Carbon\Carbon::parse('1970-01-01');
            $rawRows[] = [
                'sort_date' => $sortDate,
                'sort_id'   => (int) $ledger->id,
                'debit'     => $debit,
                'credit'    => $credit,
                'trans'     => $ledger->acct_code ?? $ledger->type ?? '',
                'ref'       => $ledger->bam_no ?? $ledger->reference ?? '',
                'date_display' => $d ? (($d instanceof \Carbon\Carbon) ? $d->format('m/d/Y') : \Carbon\Carbon::parse($d)->format('m/d/Y')) : '',
                'summary_key'   => $ledger->acct_code ?: ($ledger->type ?? ''),
                'summary_title' => $ledger->reference ?? $ledger->remarks ?? ($ledger->acct_code ?: $ledger->type ?? ''),
            ];
        }

        // --- 2) Fetch from lro_ledgers (imported/CBA: account_no, cba_date, ar_dm, lro_dm, ar_cm, lro_cm) if table exists
        if (Schema::hasTable('lro_ledgers')) {
            $hasAccountNo      = Schema::hasColumn('lro_ledgers', 'account_no');
            $hasConsumerZoneId = Schema::hasColumn('lro_ledgers', 'consumer_zone_id');
            $hasCbaDate        = Schema::hasColumn('lro_ledgers', 'cba_date');

            // Skip query if neither lookup column exists (prevents returning all rows)
            $canQuery = $hasAccountNo || ($hasConsumerZoneId && $consumer && isset($consumer->id));
            $fromPlural = $canQuery
                ? LroLedgers::where(function ($query) use ($consumerAccount, $normalizedAccountNo, $consumer, $hasAccountNo, $hasConsumerZoneId) {
                    $first = true;
                    if ($hasAccountNo) {
                        $query->where('account_no', $consumerAccount)
                            ->orWhereRaw(
                                "UPPER(TRIM(REPLACE(COALESCE(account_no, ''), '-', ''))) = ?",
                                [$normalizedAccountNo]
                            );
                        $first = false;
                    }
                    if ($hasConsumerZoneId && $consumer && isset($consumer->id)) {
                        if ($first) {
                            $query->where('consumer_zone_id', $consumer->id);
                        } else {
                            $query->orWhere('consumer_zone_id', $consumer->id);
                        }
                    }
                })
                    ->when($hasCbaDate, fn ($q) => $q->orderByRaw('COALESCE(cba_date, "1970-01-01") ASC'))
                    ->orderBy('id', 'asc')
                    ->get()
                : collect();

            foreach ($fromPlural as $ledger) {
                $debit = (float)($ledger->ar_dm ?? 0) + (float)($ledger->lro_dm ?? 0);
                $credit = (float)($ledger->ar_cm ?? 0) + (float)($ledger->lro_cm ?? 0);
                $d = $hasCbaDate ? ($ledger->cba_date ?? null) : null;
                $sortDate = $d ? \Carbon\Carbon::parse($d) : \Carbon\Carbon::parse('1970-01-01');
                $rawRows[] = [
                    'sort_date'    => $sortDate,
                    'sort_id'      => (int) $ledger->id + 100000000, // keep singular ids first when same date
                    'debit'        => $debit,
                    'credit'       => $credit,
                    'trans'        => $ledger->acct_group ?? $ledger->cba_type ?? '',
                    'ref'          => $ledger->cba_no ?? '',
                    'date_display' => $d ? (($d instanceof \Carbon\Carbon) ? $d->format('m/d/Y') : \Carbon\Carbon::parse($d)->format('m/d/Y')) : '',
                    'summary_key'  => $ledger->acct_group ?? $ledger->cba_type ?? '',
                    'summary_title'=> $ledger->cba_remarks ?? $ledger->acct_group ?? $ledger->cba_type ?? '',
                ];
            }
        }

        // --- 3) Sort merged by date then id
        usort($rawRows, function ($a, $b) {
            $cmp = $a['sort_date']->getTimestamp() - $b['sort_date']->getTimestamp();
            return $cmp !== 0 ? $cmp : ($a['sort_id'] - $b['sort_id']);
        });

        // --- 4) Apply year filter if provided
        if ($year && $year !== '' && $year !== 'all') {
            $rawRows = array_values(array_filter($rawRows, function ($row) use ($year) {
                return $row['sort_date']->format('Y') == $year;
            }));
        }

        Log::info('LRO Ledger: Merged records count', [
            'total_rows' => count($rawRows),
            'year_filter' => $year,
        ]);

        // --- 5) Build transactions and summary from merged rows
        $runningBalance = 0.00;
        $transactions = [];
        $summaryByAccount = [];

        foreach ($rawRows as $row) {
            $debit = $row['debit'];
            $credit = $row['credit'];
            $runningBalance = $runningBalance + $debit - $credit;

            $transactions[] = [
                'trans'   => $row['trans'],
                'date'    => $row['date_display'],
                'reference' => $row['ref'],
                'debit'   => $debit > 0 ? number_format($debit, 2) : '',
                'credit'  => $credit > 0 ? number_format($credit, 2) : '',
                'balance' => number_format($runningBalance, 2),
                'username' => '',
            ];

            $summaryKey = $row['summary_key'];
            if ($summaryKey !== '') {
                if (!isset($summaryByAccount[$summaryKey])) {
                    $summaryByAccount[$summaryKey] = [
                        'acct_code' => $summaryKey,
                        'acct_title' => $row['summary_title'],
                        'charges' => 0,
                        'payments' => 0,
                        'balance' => 0,
                    ];
                }
                $summaryByAccount[$summaryKey]['charges'] += $debit;
                $summaryByAccount[$summaryKey]['payments'] += $credit;
                $summaryByAccount[$summaryKey]['balance'] = $summaryByAccount[$summaryKey]['charges'] - $summaryByAccount[$summaryKey]['payments'];
            }
        }

        // Format summary values
        $summary = array_map(function($item) {
            return [
                'acct_code' => $item['acct_code'],
                'acct_title' => $item['acct_title'],
                'charges' => number_format($item['charges'], 2),
                'payments' => number_format($item['payments'], 2),
                'balance' => number_format($item['balance'], 2),
            ];
        }, array_values($summaryByAccount));

        $response = [
            'success' => true,
            'consumer' => [
                'account_no' => $consumer->account_no,
                'account_name' => $consumer->account_name,
            ],
            'transactions' => $transactions,
            'summary' => $summary,
            'balance' => number_format($runningBalance, 2),
            'year' => $year,
            'total_transactions' => count($transactions)
        ];
        
        // Add debug info in development
        if (config('app.debug')) {
            $response['debug'] = [
                'query_account_no' => $accountNo,
                'consumer_id' => $consumer->id,
                'merged_rows_count' => count($rawRows),
                'transactions_count' => count($transactions),
                'lro_ledgers_table_used' => Schema::hasTable('lro_ledgers'),
            ];
        }
        
        return response()->json($response);
    }
}
