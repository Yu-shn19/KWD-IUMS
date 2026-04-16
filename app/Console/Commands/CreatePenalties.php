<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ConsumerZoneOne;
use App\Http\Controllers\ConsumerLedgerController;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Penalty;
use App\Models\ConsumerLedger;

class CreatePenalties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'penalties:create {--account= : Process specific account number} {--debug : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create penalty entries for all consumers with overdue bills';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting penalty creation process...');
        
        $accountNo = $this->option('account');
        $debug = $this->option('debug');
        $today = Carbon::today();
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $skipReasons = [
            'no_bill' => 0,
            'too_early' => 0,
            'already_exists' => 0,
            'invalid_bill_amount' => 0,
            'no_penalty_amount' => 0,
            'no_balance' => 0,
            'collection_exists' => 0,
            'january_2026_exists' => 0
        ];

        if ($accountNo) {
            // Process specific account
            $consumer = ConsumerZoneOne::where('account_no', $accountNo)->first();
            if (!$consumer) {
                $this->error("Consumer not found for account: {$accountNo}");
                return 1;
            }
            $consumers = collect([$consumer]);
            $this->info("Processing account: {$accountNo}");
        } else {
            // Process all consumers
            $consumers = ConsumerZoneOne::all();
            $this->info("Processing {$consumers->count()} consumers...");
        }

        $bar = $this->output->createProgressBar($consumers->count());
        $bar->start();

        foreach ($consumers as $consumer) {
            try {
                $result = $this->createPenaltiesForConsumer($consumer, $today, $debug);
                $createdCount += $result['created'];
                $skippedCount += $result['skipped'];
                if ($debug && isset($result['skip_reasons'])) {
                    foreach ($result['skip_reasons'] as $reason => $count) {
                        if ($count > 0) {
                            $skipReasons[$reason] += $count;
                        }
                    }
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Error processing {$consumer->account_no}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Penalty creation completed!");
        $this->info("Created: {$createdCount}");
        $this->info("Skipped: {$skippedCount}");
        if ($errorCount > 0) {
            $this->warn("Errors: {$errorCount}");
        }
        
        if ($debug && array_sum($skipReasons) > 0) {
            $this->newLine();
            $this->info("Skip reasons breakdown:");
            foreach ($skipReasons as $reason => $count) {
                if ($count > 0) {
                    $this->line("  - " . ucfirst(str_replace('_', ' ', $reason)) . ": {$count}");
                }
            }
        }

        return 0;
    }

    private function createPenaltiesForConsumer($consumer, $today, $debug = false)
    {
        $accountNo = $consumer->account_no;
        $consumerZoneId = $consumer->id;
        $normalizedAccount = str_replace('-', '', $accountNo);
        $created = 0;
        $skipped = 0;
        $skipReasons = [
            'no_bill' => 0,
            'too_early' => 0,
            'already_exists' => 0,
            'invalid_bill_amount' => 0,
            'no_penalty_amount' => 0,
            'no_balance' => 0,
            'collection_exists' => 0,
            'january_2026_exists' => 0
        ];

        // Query for bills with passed due dates
        $schedulesQuery = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->select(
                'mrs.id as schedule_id',
                'mrs.bill_date',
                'mrs.bill_month',
                'mrs.due_date',
                'mrs.current_bill',
                'mrs.prepared_by',
                'dr.id as downloaded_id',
                'dr.current_bill as downloaded_current_bill'
            )
            ->where(function ($query) use ($accountNo, $normalizedAccount) {
                $query->where('mrs.account_number', $accountNo)
                      ->orWhereRaw("REPLACE(mrs.account_number, '-', '') = ?", [$normalizedAccount])
                      ->orWhereRaw("UPPER(TRIM(mrs.account_number)) = ?", [strtoupper(trim($accountNo))]);
            })
            ->whereNotNull('mrs.due_date')
            ->whereNotNull('mrs.bill_date')
            ->where('mrs.due_date', '<=', $today);

        $schedules = $schedulesQuery->orderBy('mrs.due_date', 'desc')->get();

        foreach ($schedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date);
            $currentBill = $schedule->downloaded_current_bill ?? $schedule->current_bill ?? 0;
            $billMonth = $schedule->bill_month ? Carbon::parse($schedule->bill_month) : null;

            if ($currentBill <= 0 || !$dueDate) {
                $skipped++;
                $skipReasons['no_bill']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: No bill or invalid date (bill: {$currentBill})");
                }
                continue;
            }

            // Penalty is created when the due date is reached or has passed
            // Check if today is greater than or equal to due date
            if ($today->lessThan($dueDate)) {
                $skipped++;
                $skipReasons['too_early']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Too early (due: {$dueDate->format('Y-m-d')}, today: {$today->format('Y-m-d')})");
                }
                continue;
            }
            
            // SPECIAL LOGIC FOR DECEMBER 2025 BILLING:
            // Only generate penalty if there's NO collection record AND NO January 2026 bill
            if ($billMonth && $billMonth->year == 2025 && $billMonth->month == 12) {
                // Check if there's a collection record for this billing period
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
                    $skipped++;
                    $skipReasons['collection_exists'] = ($skipReasons['collection_exists'] ?? 0) + 1;
                    if ($debug) {
                        $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Collection exists for December 2025 billing");
                    }
                    continue;
                }

                // Check if there's a January 2026 bill (if exists, it means they paid December, so exclude)
                $hasJanuary2026Bill = DB::table('meter_reading_schedules as mrs')
                    ->where('mrs.account_number', $accountNo)
                    ->whereBetween('mrs.bill_month', [
                        Carbon::create(2026, 1, 1)->startOfMonth()->format('Y-m-d'),
                        Carbon::create(2026, 1, 31)->endOfMonth()->format('Y-m-d')
                    ])
                    ->whereNotNull('mrs.bill_date')
                    ->exists();

                if ($hasJanuary2026Bill) {
                    $skipped++;
                    $skipReasons['january_2026_exists'] = ($skipReasons['january_2026_exists'] ?? 0) + 1;
                    if ($debug) {
                        $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: January 2026 bill exists (consumer likely paid December)");
                    }
                    continue;
                }
            }
            
            // Penalty date is the due date (or one day after for display purposes matching past records)
            $penaltyDate = $dueDate->copy()->addDay();

            // Check if penalty already exists
            $existingPenalty = Penalty::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->first();

            if ($existingPenalty) {
                $skipped++;
                $skipReasons['already_exists']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Penalty already exists (ID: {$existingPenalty->id})");
                }
                continue;
            }

            // Get bill amount
            $billEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->where('trans', 'BILL')
                ->first();

            $billAmount = $billEntry ? (float)($billEntry->billamount ?? 0) : $currentBill;

            if ($billAmount <= 0) {
                $skipped++;
                $skipReasons['invalid_bill_amount']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Invalid bill amount ({$billAmount})");
                }
                continue;
            }

            // Get previous balance first to check if consumer has outstanding balance
            $previousBalanceEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where(function($query) use ($dueDate) {
                    $query->where('date', '<=', $dueDate->format('Y-m-d'))
                          ->orWhere('due_date', '<=', $dueDate->format('Y-m-d'));
                })
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $previousBalanceEntry ? (float)($previousBalanceEntry->balance ?? 0) : 0;

            if ($previousBalance == 0) {
                $previousBalance = (float)($consumer->balance ?? 0);
            }

            // CRITICAL: Only create penalty if consumer has an outstanding balance (arrears)
            // If balance is 0 or negative (fully paid), do NOT create penalty
            if ($previousBalance <= 0) {
                $skipped++;
                $skipReasons['no_balance']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Consumer has no outstanding balance (balance: {$previousBalance})");
                }
                continue; // Skip penalty creation if consumer has no balance
            }

            $penaltyAmount = round($billAmount * 0.10, 2);

            if ($penaltyAmount <= 0) {
                $skipped++;
                $skipReasons['no_penalty_amount']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: No penalty amount calculated");
                }
                continue;
            }

            if ($penaltyAmount > 0) {
                $newBalance = $previousBalance + $penaltyAmount;
                $reference = $dueDate->format('m-Y');

                // Extract first name
                $username = 'System';
                if ($schedule->prepared_by) {
                    $parts = explode(' ', trim($schedule->prepared_by));
                    $username = $parts[0] ?? 'System';
                }

                // Create penalty in both penalties table and consumer_ledgers
                try {
                    // Create penalty entry in consumer_ledgers first
                    $penaltyLedger = ConsumerLedger::create([
                        'consumer_zone_id' => $consumerZoneId,
                        'schedule_id' => $schedule->schedule_id,
                        'trans' => 'PENALTY',
                        'date' => $penaltyDate->format('Y-m-d'),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $reference,
                        'reading' => null,
                        'volume' => null,
                        'billamount' => 0,
                        'penalty' => $penaltyAmount,
                        'others' => 0,
                        'debit' => $penaltyAmount,
                        'credit' => 0,
                        'balance' => $newBalance,
                        'username' => $username,
                        'txtime' => $penaltyDate->format('Y-m-d') . ' 00:00:00',
                    ]);

                    // Create penalty entry in penalties table
                    Penalty::create([
                        'consumer_zone_id' => $consumerZoneId,
                        'schedule_id' => $schedule->schedule_id,
                        'downloaded_reading_id' => $schedule->downloaded_id,
                        'date' => $penaltyDate->format('Y-m-d'),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'reference' => $reference,
                        'bill_amount' => $billAmount,
                        'penalty_amount' => $penaltyAmount,
                        'balance' => $newBalance,
                        'username' => $username,
                        'txtime' => $penaltyDate->format('Y-m-d') . ' 00:00:00',
                    ]);

                    // Update consumer balance
                    $consumer->balance = $newBalance;
                    $consumer->save();

                    $created++;
                    if ($debug) {
                        $this->info("  Created penalty for {$accountNo} schedule {$schedule->schedule_id}: ₱{$penaltyAmount} (due: {$dueDate->format('Y-m-d')})");
                    }
                } catch (\Exception $e) {
                    \Log::error('Error creating penalty in command', [
                        'account_no' => $accountNo,
                        'schedule_id' => $schedule->schedule_id,
                        'error' => $e->getMessage()
                    ]);
                    $skipped++;
                    if ($debug) {
                        $this->error("  Error creating penalty for {$accountNo} schedule {$schedule->schedule_id}: " . $e->getMessage());
                    }
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'skip_reasons' => $skipReasons];
    }
}
