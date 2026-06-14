<?php

namespace App\Http\Controllers;

use App\Imports\CollectionImport;
use App\Models\Collection;
use App\Models\ConsumerLedger;
use App\Models\ConsumerZoneOne;
use App\Models\Penalty;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CollectionController extends Controller
{
    /**
     * Display the collection import page
     */
    public function index()
    {
        return view('collection.import');
    }

    /**
     * Import collection data from Excel file
     */
    public function import(Request $request)
    {
        // Increase execution time limit for large file imports
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        
        Log::info('Collection import request received', [
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
            Log::info('Starting collection import');
            
            $uploadedFile = $request->file('file');
            
            // Create import instance
            $import = new CollectionImport();
            
            // Count existing records before import
            $beforeCount = Collection::count();
            
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
            $afterCount = Collection::count();
            $importedCount = $afterCount - $beforeCount;
            
            // Get import statistics
            $imported = $import->importedCount ?? $importedCount;
            $skipped = $import->skippedCount ?? 0;
            $errors = $import->errors ?? [];
            
            Log::info('Import completed', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'beforeCount' => $beforeCount,
                'afterCount' => $afterCount
            ]);
            
            if ($imported > 0) {
                $message = "Collection imported successfully! {$imported} record(s) imported.";
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
            Log::error('Collection import error', [
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
     * Sync collection payments to consumer_ledgers
     * Matches by account_no and date (coll_date = DATE(txtime))
     */
    public function syncToLedger(Request $request)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        try {
            Log::info('Starting collection to ledger sync');

            $collections = Collection::whereNotNull('account_no')
                ->whereNotNull('coll_date')
                ->where('pay_amount', '>', 0)
                ->where(function($query) {
                    $query->whereNull('cancel')
                          ->orWhere('cancel', '!=', 'Y')
                          ->orWhere('cancel', '!=', 'YES');
                })
                ->orderBy('coll_date')
                ->orderBy('account_no')
                ->get();

            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($collections as $collection) {
                try {
                    // Find consumer zone by account_no
                    $consumerZone = ConsumerZoneOne::where('account_no', $collection->account_no)->first();

                    if (!$consumerZone) {
                        $skippedCount++;
                        $errors[] = "Account {$collection->account_no} not found for collection ID {$collection->id}";
                        continue;
                    }

                    // Check if main payment already exists in ledger for this date and account
                    $existingPayment = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                        ->where('trans', 'PAYMENT')
                        ->whereDate('txtime', $collection->coll_date->format('Y-m-d'))
                        ->where('credit', $collection->pay_amount)
                        ->first();

                    // Flag: main payment already exists (from previous sync run)
                    // We will still allow SC discount PAYMENT to be created if missing.
                    $hasMainPayment = (bool) $existingPayment;

                    $penaltyAmount = (float)($collection->penalty ?? 0);
                    $paymentAmount = (float)$collection->pay_amount;
                    // sc_discount can be stored as negative (discount). Use absolute value as credit.
                    $rawScDiscount = (float)($collection->sc_discount ?? 0);
                    $scDiscount   = abs($rawScDiscount);
                    
                    // Create datetime from coll_date and coll_time for payment
                    $paymentDateTime = $collection->coll_date->format('Y-m-d');
                    if ($collection->coll_time) {
                        $time = is_string($collection->coll_time) ? $collection->coll_time : $collection->coll_time->format('H:i:s');
                        $paymentDateTime .= ' ' . $time;
                    } else {
                        $paymentDateTime .= ' 00:00:00';
                    }
                    
                    // FIRST: Check if a penalty already exists for this collection date
                    // This could have been created by generatePenalties() method
                    // We need to check this FIRST because the penalty balance should be used for payment calculation
                    // IMPORTANT: Find ANY penalty on the same date, regardless of amount or reference
                    // This ensures we use the correct balance even if penalty was created separately
                    $existingPenalty = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                        ->where('trans', 'PENALTY')
                        ->whereDate('txtime', $collection->coll_date->format('Y-m-d'))
                        ->where('txtime', '<', $paymentDateTime) // Must be before the payment time
                        ->orderBy('txtime', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    if ($existingPenalty && $penaltyAmount > 0) {
                        // Verify the penalty amount matches (log warning if it doesn't)
                        $foundPenaltyAmount = (float)($existingPenalty->penalty ?? 0);
                        if (abs($foundPenaltyAmount - $penaltyAmount) > 0.01) {
                            Log::warning('Penalty amount mismatch, but using existing penalty balance anyway', [
                                'account_no' => $collection->account_no,
                                'penalty_id' => $existingPenalty->id,
                                'expected_penalty' => $penaltyAmount,
                                'found_penalty' => $foundPenaltyAmount,
                                'difference' => abs($foundPenaltyAmount - $penaltyAmount)
                            ]);
                        }
                    }
                    
                    // Get the latest balance before this payment
                    // If penalty exists, we want to use the penalty's balance
                    // If penalty doesn't exist, we want the latest balance before the payment time
                    if ($existingPenalty) {
                        // Use the penalty's balance as the starting point for payment calculation
                        $previousBalance = (float)($existingPenalty->balance ?? 0);
                        Log::info('Found existing penalty, using its balance for payment calculation', [
                            'account_no' => $collection->account_no,
                            'penalty_id' => $existingPenalty->id,
                            'penalty_balance' => $previousBalance,
                            'penalty_date' => $existingPenalty->txtime,
                            'penalty_amount' => $existingPenalty->penalty
                        ]);
                    } else {
                        // No penalty exists yet, get the latest balance before the payment datetime
                        $latestLedger = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                            ->where('txtime', '<', $paymentDateTime)
                            ->orderBy('txtime', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();

                        $previousBalance = $latestLedger ? (float)($latestLedger->balance ?? 0) : (float)($consumerZone->balance ?? 0);
                        
                        Log::info('Getting balance before payment (no penalty found)', [
                            'account_no' => $collection->account_no,
                            'previous_entry_id' => $latestLedger ? $latestLedger->id : null,
                            'previous_entry_trans' => $latestLedger ? $latestLedger->trans : null,
                            'previous_balance' => $previousBalance,
                            'payment_datetime' => $paymentDateTime
                        ]);
                        
                        // If collection has penalty amount, create PENALTY row BEFORE the PAYMENT row
                        if ($penaltyAmount > 0) {
                            // Penalty date should be before the payment (use same date but earlier time)
                            $penaltyDateTime = $collection->coll_date->format('Y-m-d') . ' 00:00:00';
                            
                            // Calculate balance after penalty: previous balance + penalty (debit increases balance)
                            $balanceAfterPenalty = round($previousBalance + $penaltyAmount, 2);
                            
                            Log::info('Creating penalty row', [
                                'account_no' => $collection->account_no,
                                'previous_balance' => $previousBalance,
                                'penalty_amount' => $penaltyAmount,
                                'balance_after_penalty' => $balanceAfterPenalty
                            ]);
                            
                            // Create penalty entry in consumer_ledgers
                            $penaltyLedger = ConsumerLedger::create([
                                'consumer_zone_id' => $consumerZone->id,
                                'trans' => 'PENALTY',
                                'date' => $collection->coll_date->format('Y-m-d'),
                                'due_date' => null,
                                'reference' => $collection->or_number ?? 'Collection Penalty',
                                'reading' => null,
                                'volume' => null,
                                'billamount' => 0,
                                'penalty' => $penaltyAmount,
                                'others' => 0,
                                'debit' => $penaltyAmount,
                                'credit' => 0,
                                'balance' => $balanceAfterPenalty,
                                'username' => $collection->username ?? 'SYSTEM',
                                'txtime' => $penaltyDateTime,
                            ]);
                            
                            // Update previous balance for payment calculation
                            $previousBalance = $balanceAfterPenalty;
                            
                            Log::info('Penalty row created from collection', [
                                'collection_id' => $collection->id,
                                'account_no' => $collection->account_no,
                                'penalty_amount' => $penaltyAmount,
                                'penalty_date' => $penaltyDateTime,
                                'penalty_balance' => $balanceAfterPenalty,
                                'penalty_ledger_id' => $penaltyLedger->id
                            ]);
                        }
                    }
                    
                    // OPTIONAL: Create separate PAYMENT entry for Senior Citizen Discount (sc_discount)
                    // This is recorded as its own PAYMENT row with an "-SC" suffix in the reference,
                    // e.g. "324463-SC", and reduces the balance by the discount amount.
                    if ($scDiscount > 0) {
                        // Avoid creating duplicate SC discount payments
                        $existingScPayment = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                            ->where('trans', 'PAYMENT')
                            ->whereDate('txtime', $collection->coll_date->format('Y-m-d'))
                            ->where('credit', $scDiscount)
                            ->where('reference', ($collection->or_number ? $collection->or_number . '-SC' : 'SC Discount'))
                            ->first();

                        if (!$existingScPayment) {
                            $balanceAfterSc = round($previousBalance - $scDiscount, 2);

                            Log::info('Creating SC discount payment entry', [
                                'account_no' => $collection->account_no,
                                'collection_id' => $collection->id,
                                'or_number' => $collection->or_number,
                                'previous_balance' => $previousBalance,
                                'sc_discount' => $scDiscount,
                                'new_balance' => $balanceAfterSc,
                                'calculation' => "{$previousBalance} - {$scDiscount} = {$balanceAfterSc}"
                            ]);

                            ConsumerLedger::create([
                                'consumer_zone_id' => $consumerZone->id,
                                'trans' => 'PAYMENT',
                                'date' => $collection->coll_date->format('Y-m-d'),
                                'due_date' => null,
                                'reference' => $collection->or_number
                                    ? $collection->or_number . '-SC'
                                    : 'SC Discount',
                                'reading' => null,
                                'volume' => null,
                                'billamount' => 0,
                                'penalty' => 0,
                                'others' => 0,
                                'debit' => 0,
                                'credit' => $scDiscount,
                                'balance' => $balanceAfterSc,
                                'username' => $collection->username ?? 'SYSTEM',
                                'txtime' => $paymentDateTime,
                            ]);

                            // Update previousBalance so the main payment is applied after the SC discount
                            $previousBalance = $balanceAfterSc;
                        } else {
                            Log::info('SC discount payment already exists, skipping duplicate', [
                                'account_no' => $collection->account_no,
                                'collection_id' => $collection->id,
                                'sc_discount' => $scDiscount,
                            ]);
                        }
                    }

                    // Create / update main payment only if it does NOT already exist
                    if (!$hasMainPayment) {
                        // Calculate balance after main payment: previous balance - payment (credit decreases balance)
                        $newBalance = round($previousBalance - $paymentAmount, 2);
                        
                        Log::info('Creating main payment entry from collection', [
                            'account_no' => $collection->account_no,
                            'collection_id' => $collection->id,
                            'or_number' => $collection->or_number,
                            'penalty_exists' => $existingPenalty ? 'YES' : 'NO',
                            'penalty_id' => $existingPenalty ? $existingPenalty->id : null,
                            'previous_balance' => $previousBalance,
                            'penalty_amount' => $penaltyAmount,
                            'sc_discount' => $scDiscount,
                            'payment_amount' => $paymentAmount,
                            'new_balance' => $newBalance,
                            'calculation' => "{$previousBalance} - {$paymentAmount} = {$newBalance}"
                        ]);

                        // Create main payment entry in consumer_ledgers
                        $ledger = ConsumerLedger::create([
                            'consumer_zone_id' => $consumerZone->id,
                            'trans' => 'PAYMENT',
                            'date' => $collection->coll_date->format('Y-m-d'),
                            'due_date' => null,
                            'reference' => $collection->or_number ?? 'Collection Payment',
                            'reading' => null,
                            'volume' => null,
                            'billamount' => 0,
                            'penalty' => 0, // Penalty is already in separate PENALTY row
                            'others' => (float)($collection->others ?? 0),
                            'debit' => 0,
                            'credit' => $paymentAmount,
                            'balance' => $newBalance,
                            'username' => $collection->username ?? 'SYSTEM',
                            'txtime' => $paymentDateTime,
                        ]);

                        // Update consumer zone balance to final balance after SC discount (if any) and payment
                        $consumerZone->balance = $newBalance;
                        $consumerZone->save();
                    } else {
                        // If main payment already exists but SC discount was just added, update consumer balance
                        $consumerZone->balance = $previousBalance;
                        $consumerZone->save();
                        Log::info('Main payment already exists; only SC discount (if any) affected balance', [
                            'account_no' => $collection->account_no,
                            'collection_id' => $collection->id,
                            'final_balance' => $previousBalance,
                        ]);
                    }

                    $syncedCount++;

                } catch (\Exception $e) {
                    $skippedCount++;
                    $errors[] = "Error syncing collection ID {$collection->id}: " . $e->getMessage();
                    Log::error("Error syncing collection to ledger", [
                        'collection_id' => $collection->id,
                        'account_no' => $collection->account_no,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Sync completed! {$syncedCount} payment(s) synced to ledger.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} record(s) skipped.";
            }

            Log::info('Collection to ledger sync completed', [
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'errors' => count($errors)
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'synced' => $syncedCount,
                    'skipped' => $skippedCount,
                    'errors' => array_slice($errors, 0, 10) // Return first 10 errors
                ]);
            }

            return back()->with([
                'success' => $message,
                'sync_success' => true
            ])->with('sync_errors', $errors);

        } catch (\Exception $e) {
            Log::error('Collection to ledger sync error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = 'An error occurred during sync: ' . $e->getMessage();

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
     * Sync ONLY Senior Citizen discounts (sc_discount) from collection to consumer_ledgers
     * Does not create or touch main PAYMENT rows, only adds missing "-SC" payment entries.
     */
    public function syncScDiscountsOnly(Request $request)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        try {
            Log::info('Starting SC discount only sync from collection to ledger');

            // Get all rows that have an SC discount (can be stored as negative); ignore cancel flag so we don't miss any
            $collections = Collection::whereNotNull('account_no')
                ->whereNotNull('coll_date')
                ->where('sc_discount', '!=', 0)
                ->orderBy('coll_date')
                ->orderBy('account_no')
                ->get();

            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($collections as $collection) {
                try {
                    $consumerZone = ConsumerZoneOne::where('account_no', $collection->account_no)->first();

                    if (!$consumerZone) {
                        $skippedCount++;
                        $errors[] = "Account {$collection->account_no} not found for collection ID {$collection->id}";
                        continue;
                    }

                    // sc_discount may be stored negative (e.g. -9.75); treat its absolute value as credit
                    $rawScDiscount = (float)($collection->sc_discount ?? 0);
                    $scDiscount = abs($rawScDiscount);
                    if ($scDiscount <= 0) {
                        $skippedCount++;
                        continue;
                    }

                    // Build payment datetime from coll_date + coll_time
                    $paymentDateTime = $collection->coll_date->format('Y-m-d');
                    if ($collection->coll_time) {
                        $time = is_string($collection->coll_time) ? $collection->coll_time : $collection->coll_time->format('H:i:s');
                        $paymentDateTime .= ' ' . $time;
                    } else {
                        $paymentDateTime .= ' 00:00:00';
                    }

                    // Check if SC discount PAYMENT already exists
                    $scReference = $collection->or_number
                        ? $collection->or_number . '-SC'
                        : 'SC Discount';

                    $existingScPayment = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                        ->where('trans', 'PAYMENT')
                        ->whereDate('txtime', $collection->coll_date->format('Y-m-d'))
                        ->where('credit', $scDiscount)
                        ->where('reference', $scReference)
                        ->first();

                    if ($existingScPayment) {
                        $skippedCount++;
                        continue;
                    }

                    // Get latest balance BEFORE this SC entry
                    $latestLedger = ConsumerLedger::where('consumer_zone_id', $consumerZone->id)
                        ->where('txtime', '<=', $paymentDateTime)
                        ->orderBy('txtime', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    $previousBalance = $latestLedger
                        ? (float)($latestLedger->balance ?? 0)
                        : (float)($consumerZone->balance ?? 0);

                    $newBalance = round($previousBalance - $scDiscount, 2);

                    Log::info('Creating SC-only payment entry', [
                        'account_no' => $collection->account_no,
                        'collection_id' => $collection->id,
                        'or_number' => $collection->or_number,
                        'previous_balance' => $previousBalance,
                        'sc_discount' => $scDiscount,
                        'new_balance' => $newBalance,
                        'calculation' => "{$previousBalance} - {$scDiscount} = {$newBalance}"
                    ]);

                    ConsumerLedger::create([
                        'consumer_zone_id' => $consumerZone->id,
                        'trans' => 'PAYMENT',
                        'date' => $collection->coll_date->format('Y-m-d'),
                        'due_date' => null,
                        'reference' => $scReference,
                        'reading' => null,
                        'volume' => null,
                        'billamount' => 0,
                        'penalty' => 0,
                        'others' => 0,
                        'debit' => 0,
                        'credit' => $scDiscount,
                        'balance' => $newBalance,
                        'username' => $collection->username ?? 'SYSTEM',
                        'txtime' => $paymentDateTime,
                    ]);

                    // Update consumer balance
                    $consumerZone->balance = $newBalance;
                    $consumerZone->save();

                    $syncedCount++;
                } catch (\Exception $e) {
                    $skippedCount++;
                    $errors[] = "Error syncing SC discount for collection ID {$collection->id}: " . $e->getMessage();
                    Log::error("Error syncing SC-only discount to ledger", [
                        'collection_id' => $collection->id,
                        'account_no' => $collection->account_no,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "SC Discount sync completed! {$syncedCount} SC discount payment(s) synced to ledger.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} record(s) skipped.";
            }

            Log::info('SC discount only sync completed', [
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'errors' => count($errors)
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'synced' => $syncedCount,
                    'skipped' => $skippedCount,
                    'errors' => array_slice($errors, 0, 10),
                ]);
            }

            return back()->with([
                'success' => $message,
                'sync_sc_success' => true,
            ])->with('sync_sc_errors', $errors);

        } catch (\Exception $e) {
            Log::error('SC discount only sync error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = 'An error occurred during SC discount sync: ' . $e->getMessage();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }

            return back()->with('error', $errorMessage);
        }
    }

    /**
     * Generate penalties for late payments
     * Checks if collection coll_date exceeds the due_date from consumer_ledgers table
     * Uses penalty amount from collection table to create PENALTY row
     * Due date is 15 days after bill date
     */
    public function generatePenalties(Request $request)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        try {
            Log::info('Starting penalty generation for late payments');

            // Find all BILL entries with due_date in December 2025 or future
            // Due date is 15 days after bill date, so bills dated 12/01, 12/02, 12/03 have due dates around 12/16, 12/17, 12/18
            $today = Carbon::today();
            
            // Build query for BILL entries - check due_date field in consumer_ledgers
            // Note: Some entries use 'BILL' and some use 'BILLING' as transaction type
            // First, let's check what bills exist to debug
            $allBills = ConsumerLedger::whereIn('trans', ['BILL', 'BILLING'])
                ->whereNotNull('due_date')
                ->limit(20)
                ->get(['id', 'date', 'due_date', 'trans', 'consumer_zone_id']);
            
            Log::info('Sample BILL/BILLING entries in database', [
                'count' => $allBills->count(),
                'sample_bills' => $allBills->map(function($bill) {
                    return [
                        'id' => $bill->id,
                        'date' => $bill->date,
                        'due_date' => $bill->due_date,
                        'trans' => $bill->trans,
                        'due_date_formatted' => $bill->due_date ? Carbon::parse($bill->due_date)->format('Y-m-d') : null,
                        'year' => $bill->due_date ? Carbon::parse($bill->due_date)->year : null,
                        'month' => $bill->due_date ? Carbon::parse($bill->due_date)->month : null
                    ];
                })
            ]);
            
            $billQuery = ConsumerLedger::whereIn('trans', ['BILL', 'BILLING'])
                ->whereNotNull('due_date')
                ->with('consumerZone');

            // Find bills with due dates in December 2025 or future periods
            // Try multiple approaches to catch different date formats
            $billQuery->where(function($query) use ($today) {
                // Bills with due dates in December 2025 (using DATE function for safety)
                $query->where(function($q) {
                    $q->whereRaw('YEAR(due_date) = 2025')
                      ->whereRaw('MONTH(due_date) = 12');
                });
                
                // Also include future billing periods
                $query->orWhereRaw('DATE(due_date) >= ?', [$today->format('Y-m-d')]);
            });

            $billEntries = $billQuery->get();
            
            Log::info('Found bills for penalty generation', [
                'count' => $billEntries->count(),
                'query_conditions' => 'due_date in December 2025 OR due_date >= today',
                'sample_bills' => $billEntries->take(10)->map(function($bill) {
                    return [
                        'id' => $bill->id,
                        'date' => $bill->date,
                        'due_date' => $bill->due_date,
                        'account_no' => $bill->consumerZone ? $bill->consumerZone->account_no : null,
                        'billamount' => $bill->billamount
                    ];
                })
            ]);
            
            if ($billEntries->isEmpty()) {
                Log::warning('No bills found for penalty generation', [
                    'query' => 'trans=BILL, due_date in Dec 2025 or future'
                ]);
            }

            $penaltiesGenerated = 0;
            $skippedCount = 0;
            $errors = [];
            $processedBills = [];

            foreach ($billEntries as $billEntry) {
                try {
                    if (!$billEntry->consumerZone || !$billEntry->due_date) {
                        $skippedCount++;
                        $errors[] = "Bill ID {$billEntry->id}: Consumer zone or due_date not found";
                        continue;
                    }

                    $accountNo = $billEntry->consumerZone->account_no;
                    $billAmount = (float)($billEntry->billamount ?? 0);
                    $dueDate = Carbon::parse($billEntry->due_date);
                    $dueDateStr = $dueDate->format('Y-m-d');

                    // Skip if already processed for this account and due_date combination
                    $billKey = "{$accountNo}_{$dueDateStr}";
                    if (isset($processedBills[$billKey])) {
                        continue;
                    }

                    if ($billAmount <= 0) {
                        $skippedCount++;
                        continue;
                    }

                    // Find collection records for this account that were paid AFTER the due date
                    // We need to get the penalty amount from the collection table
                    $lateCollections = Collection::where('account_no', $accountNo)
                        ->whereNotNull('coll_date')
                        ->where('pay_amount', '>', 0)
                        ->where(function($query) {
                            $query->whereNull('cancel')
                                  ->orWhere('cancel', '!=', 'Y')
                                  ->orWhere('cancel', '!=', 'YES');
                        })
                        ->whereDate('coll_date', '>', $dueDateStr)
                        ->orderBy('coll_date')
                        ->get();
                    
                    Log::info('Checking for late collections', [
                        'account_no' => $accountNo,
                        'due_date' => $dueDateStr,
                        'bill_date' => $billEntry->date,
                        'late_collections_count' => $lateCollections->count(),
                        'sample_collections' => $lateCollections->take(5)->map(function($col) {
                            return [
                                'id' => $col->id,
                                'coll_date' => $col->coll_date ? $col->coll_date->format('Y-m-d') : null,
                                'penalty' => $col->penalty,
                                'pay_amount' => $col->pay_amount,
                                'or_number' => $col->or_number
                            ];
                        })
                    ]);
                    
                    if ($lateCollections->isEmpty()) {
                        $skippedCount++;
                        $processedBills[$billKey] = true;
                        
                        // Also check all collections for this account to see what we have
                        $allCollections = Collection::where('account_no', $accountNo)
                            ->whereNotNull('coll_date')
                            ->get();
                        
                        Log::info('No late collections found - checking all collections', [
                            'account_no' => $accountNo,
                            'due_date' => $dueDateStr,
                            'bill_date' => $billEntry->date,
                            'total_collections' => $allCollections->count(),
                            'all_collection_dates' => $allCollections->map(function($col) use ($dueDateStr) {
                                $collDate = $col->coll_date ? $col->coll_date->format('Y-m-d') : null;
                                $isLate = $collDate && $collDate > $dueDateStr;
                                return [
                                    'coll_date' => $collDate,
                                    'is_late' => $isLate,
                                    'penalty' => $col->penalty,
                                    'pay_amount' => $col->pay_amount
                                ];
                            })->toArray()
                        ]);
                        continue;
                    }
                    
                    // Check if any of the late collections have penalty data
                    // Do not generate penalty if there's no penalty amount in the collection records
                    $hasCollectionPenalty = $lateCollections->filter(function($col) {
                        return isset($col->penalty) && (float)$col->penalty > 0;
                    })->isNotEmpty();
                    
                    if (!$hasCollectionPenalty) {
                        $skippedCount++;
                        $processedBills[$billKey] = true;
                        Log::info('Skipping penalty generation - no penalty data in collection records', [
                            'account_no' => $accountNo,
                            'due_date' => $dueDateStr,
                            'late_collections_count' => $lateCollections->count(),
                            'penalty_amounts' => $lateCollections->pluck('penalty')->toArray()
                        ]);
                        continue;
                    }
                    
                    // Use the penalty amount from the collection record (not calculate it)
                    // Get the first late collection that has a penalty amount
                    $collectionPenalty = null;
                    foreach ($lateCollections as $lateCol) {
                        $penaltyAmt = (float)($lateCol->penalty ?? 0);
                        if ($penaltyAmt > 0) {
                            $collectionPenalty = $lateCol;
                            break;
                        }
                    }
                    
                    if (!$collectionPenalty) {
                        $skippedCount++;
                        $processedBills[$billKey] = true;
                        Log::info('No collection with penalty amount found', [
                            'account_no' => $accountNo,
                            'late_collections_count' => $lateCollections->count(),
                            'penalty_amounts' => $lateCollections->pluck('penalty')->toArray()
                        ]);
                        continue;
                    }
                    
                    $penaltyAmount = (float)($collectionPenalty->penalty ?? 0);
                    
                    Log::info('Found collection with penalty', [
                        'account_no' => $accountNo,
                        'collection_id' => $collectionPenalty->id,
                        'penalty_amount' => $penaltyAmount,
                        'coll_date' => $collectionPenalty->coll_date ? $collectionPenalty->coll_date->format('Y-m-d') : null,
                        'due_date' => $dueDateStr
                    ]);
                    
                    // Check if penalty already exists for this billing and collection
                    $existingPenalty = ConsumerLedger::where('consumer_zone_id', $billEntry->consumer_zone_id)
                        ->where('trans', 'PENALTY')
                        ->where('due_date', $dueDateStr)
                        ->where('penalty', $penaltyAmount)
                        ->whereDate('txtime', $collectionPenalty->coll_date->format('Y-m-d'))
                        ->first();

                    if ($existingPenalty) {
                        $skippedCount++;
                        $processedBills[$billKey] = true;
                        Log::info('Penalty already exists', [
                            'account_no' => $accountNo,
                            'due_date' => $dueDateStr
                        ]);
                        continue; // Penalty already exists
                    }

                    // Get the latest balance BEFORE the penalty date
                    // We need to find the balance just before the collection date (when penalty will be applied)
                    $penaltyDate = $collectionPenalty->coll_date;
                    $penaltyDateStr = $penaltyDate->format('Y-m-d');
                    
                    // Get the latest entry before or on the penalty date (but before the penalty time)
                    $previousBalanceEntry = ConsumerLedger::where('consumer_zone_id', $billEntry->consumer_zone_id)
                        ->where(function($query) use ($penaltyDateStr) {
                            $query->whereDate('txtime', '<', $penaltyDateStr)
                                  ->orWhere(function($q) use ($penaltyDateStr) {
                                      $q->whereDate('txtime', '=', $penaltyDateStr)
                                        ->where('txtime', '<', $penaltyDateStr . ' 00:00:00');
                                  });
                        })
                        ->orderBy('txtime', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    // If no entry found, try getting by date
                    if (!$previousBalanceEntry) {
                        $previousBalanceEntry = ConsumerLedger::where('consumer_zone_id', $billEntry->consumer_zone_id)
                            ->where(function($query) use ($penaltyDate) {
                                $query->where('date', '<', $penaltyDate->format('Y-m-d'))
                                      ->orWhere(function($q) use ($penaltyDate) {
                                          $q->where('date', '=', $penaltyDate->format('Y-m-d'))
                                            ->where('txtime', '<', $penaltyDate->format('Y-m-d') . ' 00:00:00');
                                      });
                            })
                            ->orderBy('date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();
                    }
                    
                    // Get the balance from the previous entry
                    if ($previousBalanceEntry) {
                        $previousBalance = (float)($previousBalanceEntry->balance ?? 0);
                    } else {
                        // No previous entry, use the bill's balance as starting point
                        $previousBalance = (float)($billEntry->balance ?? 0);
                        // If bill balance is 0, calculate from consumer balance
                        if ($previousBalance == 0) {
                            $previousBalance = (float)($billEntry->consumerZone->balance ?? 0);
                            $billDebit = (float)($billEntry->debit ?? 0);
                            $billCredit = (float)($billEntry->credit ?? 0);
                            $previousBalance = round($previousBalance + $billDebit - $billCredit, 2);
                        }
                    }
                    
                    // Penalty is added to the previous balance
                    $newBalance = round($previousBalance + $penaltyAmount, 2);
                    
                    Log::info('Calculating penalty balance - detailed', [
                        'account_no' => $accountNo,
                        'previous_balance_entry_id' => $previousBalanceEntry ? $previousBalanceEntry->id : null,
                        'previous_balance' => $previousBalance,
                        'penalty_amount' => $penaltyAmount,
                        'new_balance' => $newBalance,
                        'bill_id' => $billEntry->id,
                        'bill_balance' => $billEntry->balance,
                        'penalty_date' => $penaltyDateStr
                    ]);
                    
                    Log::info('Calculating penalty balance', [
                        'account_no' => $accountNo,
                        'bill_balance' => $billBalance,
                        'penalty_amount' => $penaltyAmount,
                        'new_balance' => $newBalance,
                        'bill_id' => $billEntry->id
                    ]);

                    // Penalty date should be the same as collection date, but with earlier time to appear before payment
                    // This ensures PENALTY row appears before PAYMENT row in the ledger
                    $penaltyDate = $collectionPenalty->coll_date;
                    $penaltyDateTime = $penaltyDate->format('Y-m-d') . ' 00:00:00'; // Set to start of day
                    $reference = $collectionPenalty->or_number ?? $dueDate->format('m-Y'); // Use OR number or MM-YYYY format

                    // Create penalty entry in consumer_ledgers
                    $penaltyLedger = ConsumerLedger::create([
                        'consumer_zone_id' => $billEntry->consumer_zone_id,
                        'trans' => 'PENALTY',
                        'date' => $penaltyDate->format('Y-m-d'),
                        'due_date' => $dueDateStr,
                        'reference' => $reference,
                        'reading' => null,
                        'volume' => null,
                        'billamount' => 0,
                        'penalty' => $penaltyAmount,
                        'others' => 0,
                        'debit' => $penaltyAmount,
                        'credit' => 0,
                        'balance' => $newBalance,
                        'username' => $collectionPenalty->username ?? 'SYSTEM',
                        'txtime' => $penaltyDateTime,
                    ]);
                    
                    Log::info('Penalty ledger entry created', [
                        'penalty_id' => $penaltyLedger->id,
                        'account_no' => $accountNo,
                        'penalty_date' => $penaltyDate->format('Y-m-d'),
                        'penalty_amount' => $penaltyAmount,
                        'new_balance' => $newBalance,
                        'due_date' => $dueDateStr
                    ]);

                    // Create penalty entry in penalties table
                    $penaltyRecord = Penalty::create([
                        'consumer_zone_id' => $billEntry->consumer_zone_id,
                        'schedule_id' => $billEntry->schedule_id ?? null,
                        'downloaded_reading_id' => $billEntry->downloaded_reading_id ?? null,
                        'date' => $penaltyDate->format('Y-m-d'),
                        'due_date' => $dueDateStr,
                        'reference' => $reference,
                        'bill_amount' => $billAmount,
                        'penalty_amount' => $penaltyAmount,
                        'balance' => $newBalance,
                        'username' => $collectionPenalty->username ?? 'SYSTEM',
                        'txtime' => $penaltyDateTime,
                    ]);

                    // Update consumer zone balance
                    $billEntry->consumerZone->balance = $newBalance;
                    $billEntry->consumerZone->save();

                    $penaltiesGenerated++;
                    $processedBills[$billKey] = true;

                    // Verify the penalty was created
                    $verifyPenalty = ConsumerLedger::find($penaltyLedger->id);
                    if (!$verifyPenalty) {
                        Log::error('Penalty ledger entry not found after creation!', [
                            'penalty_id' => $penaltyLedger->id,
                            'account_no' => $accountNo
                        ]);
                        $errors[] = "Penalty created but not found for account {$accountNo}";
                    } else {
                        Log::info('Penalty verified in database', [
                            'penalty_id' => $verifyPenalty->id,
                            'trans' => $verifyPenalty->trans,
                            'date' => $verifyPenalty->date,
                            'balance' => $verifyPenalty->balance
                        ]);
                    }

                    Log::info('Penalty generated successfully', [
                        'account_no' => $accountNo,
                        'bill_id' => $billEntry->id,
                        'bill_date' => $billEntry->date,
                        'bill_amount' => $billAmount,
                        'penalty_amount' => $penaltyAmount,
                        'due_date' => $dueDateStr,
                        'penalty_date' => $penaltyDate->format('Y-m-d'),
                        'collection_date' => $collectionPenalty->coll_date->format('Y-m-d'),
                        'collection_id' => $collectionPenalty->id,
                        'penalty_ledger_id' => $penaltyLedger->id
                    ]);

                } catch (\Exception $e) {
                    $skippedCount++;
                    $errors[] = "Error processing bill ID {$billEntry->id}: " . $e->getMessage();
                    Log::error("Error generating penalty for bill", [
                        'bill_id' => $billEntry->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Penalty generation completed! {$penaltiesGenerated} penalty(ies) generated.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} record(s) skipped.";
            }

            Log::info('Penalty generation completed', [
                'generated' => $penaltiesGenerated,
                'skipped' => $skippedCount,
                'errors' => count($errors),
                'total_bills_processed' => $billEntries->count(),
                'date_range' => 'December 2025 and future periods'
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'generated' => $penaltiesGenerated,
                    'skipped' => $skippedCount,
                    'errors' => array_slice($errors, 0, 10)
                ]);
            }

            return back()->with([
                'success' => $message,
                'penalty_generation_success' => true
            ])->with('penalty_errors', $errors);

        } catch (\Exception $e) {
            Log::error('Penalty generation error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = 'An error occurred during penalty generation: ' . $e->getMessage();

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
     * Get merged data showing collection and ledger entries together
     */
    public function getMergedData(Request $request)
    {
        try {
            $accountNo = $request->input('account_no');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if (!$accountNo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account number is required'
                ], 400);
            }

            $consumerZone = ConsumerZoneOne::where('account_no', $accountNo)->first();

            if (!$consumerZone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            // Get ledger entries
            $ledgerQuery = ConsumerLedger::where('consumer_zone_id', $consumerZone->id);
            if ($startDate) {
                $ledgerQuery->whereDate('txtime', '>=', $startDate);
            }
            if ($endDate) {
                $ledgerQuery->whereDate('txtime', '<=', $endDate);
            }
            $ledgers = $ledgerQuery->orderBy('txtime')->orderBy('id')->get();

            // Get collection entries
            $collectionQuery = Collection::where('account_no', $accountNo);
            if ($startDate) {
                $collectionQuery->whereDate('coll_date', '>=', $startDate);
            }
            if ($endDate) {
                $collectionQuery->whereDate('coll_date', '<=', $endDate);
            }
            $collections = $collectionQuery->orderBy('coll_date')->orderBy('id')->get();

            // Merge and sort by date
            $merged = collect();

            // Add ledger entries
            foreach ($ledgers as $ledger) {
                $merged->push([
                    'type' => 'ledger',
                    'id' => $ledger->id,
                    'date' => $ledger->txtime ? $ledger->txtime->format('Y-m-d') : ($ledger->date ?? null),
                    'datetime' => $ledger->txtime ? $ledger->txtime->format('Y-m-d H:i:s') : null,
                    'trans' => $ledger->trans,
                    'reference' => $ledger->reference,
                    'debit' => (float)($ledger->debit ?? 0),
                    'credit' => (float)($ledger->credit ?? 0),
                    'balance' => (float)($ledger->balance ?? 0),
                    'data' => $ledger
                ]);
            }

            // Add collection entries
            foreach ($collections as $collection) {
                $merged->push([
                    'type' => 'collection',
                    'id' => $collection->id,
                    'date' => $collection->coll_date ? $collection->coll_date->format('Y-m-d') : null,
                    'datetime' => $collection->coll_date ? 
                        ($collection->coll_date->format('Y-m-d') . ' ' . ($collection->coll_time ?? '00:00:00')) : null,
                    'trans' => 'PAYMENT',
                    'reference' => $collection->or_number,
                    'debit' => 0,
                    'credit' => (float)($collection->pay_amount ?? 0),
                    'balance' => null, // Will be calculated
                    'data' => $collection
                ]);
            }

            // Sort by date and time
            $merged = $merged->sortBy(function($item) {
                return $item['datetime'] ?? $item['date'] ?? '0000-00-00 00:00:00';
            })->values();

            return response()->json([
                'success' => true,
                'account_no' => $accountNo,
                'account_name' => $consumerZone->account_name,
                'merged_data' => $merged,
                'ledger_count' => $ledgers->count(),
                'collection_count' => $collections->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting merged data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving merged data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Automatically generate penalties for December 2025 billing when due date is reached
     * Only generates if:
     * - Due date has passed
     * - No collection record exists for that billing period
     * - Consumer has outstanding balance
     * - No January 2026 bill exists (if Jan 2026 exists, it means they paid December, so exclude)
     */
    public function autoGenerateDecember2025Penalties(Request $request)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        try {
            Log::info('Starting automatic penalty generation for December 2025 billing');

            $today = Carbon::today();
            $december2025Start = Carbon::create(2025, 12, 1)->startOfMonth();
            $december2025End = Carbon::create(2025, 12, 31)->endOfMonth();
            $january2026Start = Carbon::create(2026, 1, 1)->startOfMonth();
            $january2026End = Carbon::create(2026, 1, 31)->endOfMonth();

            // Find all meter_reading_schedules for December 2025
            $decemberSchedules = DB::table('meter_reading_schedules as mrs')
                ->leftJoin('consumer_zone as cz', 'mrs.consumer_zone_id', '=', 'cz.id')
                ->select(
                    'mrs.id as schedule_id',
                    'cz.account_no as account_number',
                    'mrs.bill_month',
                    'mrs.bill_date',
                    'mrs.due_date',
                    'mrs.current_bill',
                    'mrs.arrears',
                    'cz.id as consumer_zone_id',
                    'cz.account_no',
                    'cz.account_name'
                )
                ->whereBetween('mrs.bill_month', [$december2025Start->format('Y-m-d'), $december2025End->format('Y-m-d')])
                ->whereNotNull('mrs.due_date')
                ->where('mrs.due_date', '<=', $today->format('Y-m-d')) // Due date has passed
                ->whereNotNull('cz.id')
                ->get();

            Log::info('Found December 2025 schedules with passed due dates', [
                'count' => $decemberSchedules->count()
            ]);

            $penaltiesGenerated = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($decemberSchedules as $schedule) {
                try {
                    $accountNo = $schedule->account_number;
                    $consumerZoneId = $schedule->consumer_zone_id;
                    $dueDate = Carbon::parse($schedule->due_date);
                    $billMonth = Carbon::parse($schedule->bill_month);
                    $currentBill = (float)($schedule->current_bill ?? 0);

                    if (!$consumerZoneId || $currentBill <= 0) {
                        $skippedCount++;
                        continue;
                    }

                    // Check if penalty already exists for this schedule
                    $existingPenalty = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                        ->where('schedule_id', $schedule->schedule_id)
                        ->where('trans', 'PENALTY')
                        ->where('due_date', $dueDate->format('Y-m-d'))
                        ->first();

                    if ($existingPenalty) {
                        $skippedCount++;
                        Log::info('Penalty already exists for schedule', [
                            'account_no' => $accountNo,
                            'schedule_id' => $schedule->schedule_id
                        ]);
                        continue;
                    }

                    // Check if there's a collection record for this billing period
                    // Look for collections around the due_date (within reasonable range)
                    $collectionExists = Collection::where('account_no', $accountNo)
                        ->whereNotNull('coll_date')
                        ->where('pay_amount', '>', 0)
                        ->where(function($query) {
                            $query->whereNull('cancel')
                                  ->orWhere('cancel', '!=', 'Y')
                                  ->orWhere('cancel', '!=', 'YES');
                        })
                        ->where(function($query) use ($dueDate, $billMonth) {
                            // Check if collection date is around the due date or bill month
                            $query->whereBetween('coll_date', [
                                $billMonth->copy()->subDays(5)->format('Y-m-d'),
                                $dueDate->copy()->addDays(30)->format('Y-m-d')
                            ]);
                        })
                        ->exists();

                    if ($collectionExists) {
                        $skippedCount++;
                        Log::info('Collection exists for December 2025 billing, skipping penalty', [
                            'account_no' => $accountNo,
                            'schedule_id' => $schedule->schedule_id
                        ]);
                        continue;
                    }

                    // Check if there's a January 2026 bill (if exists, it means they paid December, so exclude)
                    $hasJanuary2026Bill = DB::table('meter_reading_schedules as mrs')
                        ->where('mrs.consumer_zone_id', $schedule->consumer_zone_id)
                        ->whereBetween('mrs.bill_month', [$january2026Start->format('Y-m-d'), $january2026End->format('Y-m-d')])
                        ->whereNotNull('mrs.bill_date')
                        ->exists();

                    if ($hasJanuary2026Bill) {
                        $skippedCount++;
                        Log::info('January 2026 bill exists, consumer likely paid December, skipping penalty', [
                            'account_no' => $accountNo,
                            'schedule_id' => $schedule->schedule_id
                        ]);
                        continue;
                    }

                    // Get consumer balance to check if they have outstanding balance
                    $consumerZone = ConsumerZoneOne::find($consumerZoneId);
                    if (!$consumerZone) {
                        $skippedCount++;
                        continue;
                    }

                    // Get latest balance from ledger
                    $latestLedgerEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                        ->whereNotNull('balance')
                        ->orderBy('date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    $previousBalance = $latestLedgerEntry 
                        ? (float)($latestLedgerEntry->balance ?? 0) 
                        : (float)($consumerZone->balance ?? 0);

                    // Only generate penalty if consumer has outstanding balance
                    if ($previousBalance <= 0) {
                        $skippedCount++;
                        Log::info('Skipping penalty - consumer has no outstanding balance', [
                            'account_no' => $accountNo,
                            'schedule_id' => $schedule->schedule_id,
                            'previous_balance' => $previousBalance
                        ]);
                        continue;
                    }

                    // Calculate penalty: 10% of current bill
                    $penaltyAmount = round($currentBill * 0.10, 2);
                    
                    if ($penaltyAmount <= 0) {
                        $skippedCount++;
                        continue;
                    }

                    // Calculate new balance after penalty
                    $newBalance = round($previousBalance + $penaltyAmount, 2);

                    // Penalty date is one day after due date (for display purposes)
                    $penaltyDate = $dueDate->copy()->addDay();
                    $penaltyDateTime = $penaltyDate->format('Y-m-d') . ' 00:00:00';

                    // Create penalty entry in consumer_ledgers
                    $penaltyLedger = ConsumerLedger::create([
                        'consumer_zone_id' => $consumerZoneId,
                        'schedule_id' => $schedule->schedule_id,
                        'trans' => 'PENALTY',
                        'date' => $penaltyDate->format('Y-m-d'),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $billMonth->format('m-Y'), // Format: 12-2025
                        'reading' => null,
                        'volume' => null,
                        'billamount' => 0,
                        'penalty' => $penaltyAmount,
                        'others' => 0,
                        'debit' => $penaltyAmount,
                        'credit' => 0,
                        'balance' => $newBalance,
                        'username' => 'SYSTEM',
                        'txtime' => $penaltyDateTime,
                    ]);

                    // Create penalty entry in penalties table
                    Penalty::create([
                        'consumer_zone_id' => $consumerZoneId,
                        'schedule_id' => $schedule->schedule_id,
                        'downloaded_reading_id' => null,
                        'date' => $penaltyDate->format('Y-m-d'),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $billMonth->format('m-Y'),
                        'bill_amount' => $currentBill,
                        'penalty_amount' => $penaltyAmount,
                        'balance' => $newBalance,
                        'username' => 'SYSTEM',
                        'txtime' => $penaltyDateTime,
                    ]);

                    // Update consumer balance
                    $consumerZone->balance = $newBalance;
                    $consumerZone->save();

                    $penaltiesGenerated++;

                    Log::info('Penalty generated for December 2025 billing', [
                        'account_no' => $accountNo,
                        'schedule_id' => $schedule->schedule_id,
                        'due_date' => $dueDate->format('Y-m-d'),
                        'penalty_amount' => $penaltyAmount,
                        'previous_balance' => $previousBalance,
                        'new_balance' => $newBalance
                    ]);

                } catch (\Exception $e) {
                    $skippedCount++;
                    $errors[] = "Error processing schedule ID {$schedule->schedule_id}: " . $e->getMessage();
                    Log::error('Error generating penalty for December 2025 schedule', [
                        'schedule_id' => $schedule->schedule_id,
                        'account_no' => $schedule->account_number,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Automatic penalty generation completed! {$penaltiesGenerated} penalty(ies) generated for December 2025 billing.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} record(s) skipped.";
            }

            Log::info('Automatic penalty generation for December 2025 completed', [
                'generated' => $penaltiesGenerated,
                'skipped' => $skippedCount,
                'errors' => count($errors)
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'generated' => $penaltiesGenerated,
                    'skipped' => $skippedCount,
                    'errors' => array_slice($errors, 0, 10)
                ]);
            }

            return back()->with([
                'success' => $message,
                'auto_penalty_generation_success' => true
            ])->with('penalty_errors', $errors);

        } catch (\Exception $e) {
            Log::error('Automatic penalty generation error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = 'An error occurred during automatic penalty generation: ' . $e->getMessage();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return back()->with('error', $errorMessage);
        }
    }
}
