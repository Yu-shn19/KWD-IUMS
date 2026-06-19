<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ConsumerZone;
use App\Models\ConsumerPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Penalty;
use App\Models\ConsumerLedger;

class CreatePenalties extends Command
{
    protected $signature = 'penalties:create {--account= : Process specific account number} {--debug : Show detailed output}';

    protected $description = 'Create penalty entries for all consumers with overdue bills';

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
            'january_2026_exists' => 0,
            'paid_before_due' => 0,
        ];

        if ($accountNo) {
            $normalized = str_replace('-', '', trim($accountNo));
            $consumer = ConsumerZone::where('account_no', trim($accountNo))
                ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])
                ->first();
            if (!$consumer) {
                $this->error("Consumer not found for account: {$accountNo}");

                return 1;
            }
            $consumers = collect([$consumer]);
            $this->info("Processing account: {$accountNo}");
        } else {
            $consumers = ConsumerZone::all();
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
                            $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + $count;
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
        $this->info('Penalty creation completed!');
        $this->info("Created: {$createdCount}");
        $this->info("Skipped: {$skippedCount}");
        if ($errorCount > 0) {
            $this->warn("Errors: {$errorCount}");
        }

        if ($debug && array_sum($skipReasons) > 0) {
            $this->newLine();
            $this->info('Skip reasons breakdown:');
            foreach ($skipReasons as $reason => $count) {
                if ($count > 0) {
                    $this->line('  - ' . ucfirst(str_replace('_', ' ', $reason)) . ": {$count}");
                }
            }
        }

        return 0;
    }

    private function createPenaltiesForConsumer($consumer, $today, $debug = false)
    {
        $accountNo = $consumer->account_no;
        $consumerZoneId = $consumer->id;
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
            'january_2026_exists' => 0,
            'paid_before_due' => 0,
        ];

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
            ->where('mrs.consumer_zone_id', $consumerZoneId)
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

            if ($today->lessThan($dueDate)) {
                $skipped++;
                $skipReasons['too_early']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Too early (due: {$dueDate->format('Y-m-d')}, today: {$today->format('Y-m-d')})");
                }
                continue;
            }

            if ($billMonth && $billMonth->year == 2025 && $billMonth->month == 12) {
                $collectionExists = ConsumerPayment::query()
                    ->forAccountNo($accountNo)
                    ->importable()
                    ->whereBetween('paid_at', [
                        $billMonth->copy()->subDays(5)->format('Y-m-d 00:00:00'),
                        $dueDate->copy()->addDays(30)->format('Y-m-d 23:59:59'),
                    ])
                    ->exists();

                if ($collectionExists) {
                    $skipped++;
                    $skipReasons['collection_exists']++;
                    if ($debug) {
                        $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Collection exists for December 2025 billing");
                    }
                    continue;
                }

                $hasJanuary2026Bill = DB::table('meter_reading_schedules')
                    ->where('consumer_zone_id', $consumerZoneId)
                    ->whereBetween('bill_month', [
                        Carbon::create(2026, 1, 1)->startOfMonth()->format('Y-m-d'),
                        Carbon::create(2026, 1, 31)->endOfMonth()->format('Y-m-d'),
                    ])
                    ->whereNotNull('bill_date')
                    ->exists();

                if ($hasJanuary2026Bill) {
                    $skipped++;
                    $skipReasons['january_2026_exists']++;
                    if ($debug) {
                        $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: January 2026 bill exists (consumer likely paid December)");
                    }
                    continue;
                }
            }

            $penaltyDate = $dueDate->copy()->addDay();

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

            $existingLedgerPenalty = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('trans', 'PENALTY')
                ->where('schedule_id', $schedule->schedule_id)
                ->first();

            if ($existingLedgerPenalty) {
                $skipped++;
                $skipReasons['already_exists']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: PENALTY ledger already exists (ID: {$existingLedgerPenalty->id})");
                }
                continue;
            }

            if ($this->wasPaidOnOrBeforeDueDate($consumerZoneId, $schedule, $dueDate)) {
                $skipped++;
                $skipReasons['paid_before_due']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Paid on or before due date");
                }
                continue;
            }

            $billEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where('schedule_id', $schedule->schedule_id)
                ->whereIn('trans', ['BILL', 'BILLING'])
                ->first();

            $billAmount = $billEntry ? (float) ($billEntry->billamount ?? 0) : (float) $currentBill;

            if ($billAmount <= 0) {
                $skipped++;
                $skipReasons['invalid_bill_amount']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Invalid bill amount ({$billAmount})");
                }
                continue;
            }

            $previousBalanceEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
                ->where(function ($query) use ($dueDate) {
                    $query->where('date', '<=', $dueDate->format('Y-m-d'))
                        ->orWhere('due_date', '<=', $dueDate->format('Y-m-d'));
                })
                ->where('trans', '!=', 'PENALTY')
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $previousBalanceEntry ? (float) ($previousBalanceEntry->balance ?? 0) : 0;

            if ($previousBalance == 0) {
                $previousBalance = (float) ($consumer->balance ?? 0);
            }

            if ($previousBalance <= 0) {
                $skipped++;
                $skipReasons['no_balance']++;
                if ($debug) {
                    $this->line("  Skipped {$accountNo} schedule {$schedule->schedule_id}: Consumer has no outstanding balance (balance: {$previousBalance})");
                }
                continue;
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

            $newBalance = $previousBalance + $penaltyAmount;
            $reference = $dueDate->format('m-Y');
            $username = $this->extractFirstName($schedule->prepared_by ?? 'System');

            try {
                $penaltyRecord = Penalty::create($this->filterPenaltyAttributes([
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
                    'txtime' => $penaltyDate->format('Y-m-d'),
                ]));

                ConsumerLedger::create($this->filterLedgerAttributes([
                    'consumer_zone_id' => $consumerZoneId,
                    'penalty_id' => $penaltyRecord->id,
                    'schedule_id' => $schedule->schedule_id,
                    'downloaded_reading_id' => $schedule->downloaded_id,
                    'trans' => 'PENALTY',
                    'date' => $penaltyDate->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'reference' => $reference,
                    'reading' => 0,
                    'volume' => 0,
                    'billamount' => 0,
                    'penalty' => $penaltyAmount,
                    'others' => 0,
                    'debit' => $penaltyAmount,
                    'credit' => 0,
                    'balance' => $newBalance,
                    'username' => $username,
                    'txtime' => $penaltyDate->format('Y-m-d H:i:s'),
                ]));

                $created++;
                if ($debug) {
                    $this->info("  Created penalty for {$accountNo} schedule {$schedule->schedule_id}: {$penaltyAmount} (due: {$dueDate->format('Y-m-d')})");
                }
            } catch (\Exception $e) {
                \Log::error('Error creating penalty in command', [
                    'account_no' => $accountNo,
                    'schedule_id' => $schedule->schedule_id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
                if ($debug) {
                    $this->error("  Error creating penalty for {$accountNo} schedule {$schedule->schedule_id}: " . $e->getMessage());
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'skip_reasons' => $skipReasons];
    }

    private function wasPaidOnOrBeforeDueDate(int $consumerZoneId, $schedule, Carbon $dueDate): bool
    {
        $dueDateStr = $dueDate->format('Y-m-d');

        $paymentLedgerEntry = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
            ->where('schedule_id', $schedule->schedule_id)
            ->where('trans', 'PAYMENT')
            ->where(function ($query) use ($dueDateStr) {
                $query->where('date', '<=', $dueDateStr)
                    ->orWhere(function ($q) use ($dueDateStr) {
                        $q->whereNotNull('txtime')
                            ->whereDate('txtime', '<=', $dueDateStr);
                    });
            })
            ->exists();

        if ($paymentLedgerEntry) {
            return true;
        }

        if (!empty($schedule->downloaded_id)) {
            if (Schema::hasColumn('downloaded_readings', 'paid_at')) {
                $paidOnReading = DB::table('downloaded_readings')
                    ->where('id', $schedule->downloaded_id)
                    ->whereNotNull('paid_at')
                    ->whereDate('paid_at', '<=', $dueDateStr)
                    ->exists();
                if ($paidOnReading) {
                    return true;
                }
            }

            $paidViaPayments = DB::table('consumer_payments')
                ->where('reading_id', $schedule->downloaded_id)
                ->whereNotNull('paid_at')
                ->whereDate('paid_at', '<=', $dueDateStr)
                ->where('payment_amount', '>', 0)
                ->exists();
            if ($paidViaPayments) {
                return true;
            }
        }

        return false;
    }

    private function filterPenaltyAttributes(array $data): array
    {
        if (!Schema::hasTable('penalties')) {
            return $data;
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('penalties', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function filterLedgerAttributes(array $data): array
    {
        if (!Schema::hasTable('consumer_ledgers')) {
            return $data;
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('consumer_ledgers', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function extractFirstName(?string $name): string
    {
        $parts = explode(' ', trim((string) $name));

        return $parts[0] !== '' ? $parts[0] : 'System';
    }
}
