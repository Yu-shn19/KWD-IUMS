<?php

namespace App\Console\Commands;

use App\Models\ConsumerZoneOne;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivatePendingConsumersCommand extends Command
{
    protected $signature = 'consumers:activate-pending-after-install
                            {--dry-run : List candidates without updating}
                            {--backfill : Enroll existing Pending consumers with install_date (see config consumer.auto_activation_start_date)}';

    protected $description = 'Activate Pending (P) new installs when auto_activate_on is reached (install_date + configured days).';

    public function handle(): int
    {
        $tz = config('app.timezone');
        $today = Carbon::today($tz);

        if ($this->option('backfill')) {
            $enrolled = $this->backfillEnrollment($today, (bool) $this->option('dry-run'));
            $this->info("Backfill enrolled: {$enrolled}");
        }

        $query = ConsumerZoneOne::query()
            ->where('pending_install_activation', true)
            ->whereNotNull('auto_activate_on')
            ->whereDate('auto_activate_on', '<=', $today)
            ->where(function ($q) {
                $q->where('status_code', 'P')
                    ->orWhere('status_code', 'p')
                    ->orWhereRaw("UPPER(TRIM(status_code)) = 'PENDING'");
            });

        $count = (clone $query)->count();

        $this->info('Activation date (app timezone): '.$today->toDateString());
        $this->info('Days after install: '.config('consumer.days_before_active', 15));
        $this->info("Candidates due for activation: {$count}");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no status updates.');

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Nothing to activate.');

            return self::SUCCESS;
        }

        $updated = 0;

        $query->orderBy('id')->chunkById(100, function ($consumers) use (&$updated) {
            foreach ($consumers as $consumer) {
                DB::table('consumer_zone')
                    ->where('id', $consumer->id)
                    ->update([
                        'status_code' => 'A',
                        'pending_install_activation' => false,
                        'auto_activate_on' => null,
                        'updated_at' => now(),
                    ]);
                $updated++;
            }
        });

        $this->info("Activated: {$updated}");

        return self::SUCCESS;
    }

    private function backfillEnrollment(Carbon $today, bool $dryRun): int
    {
        $startDate = config('consumer.auto_activation_start_date');
        if (! $startDate) {
            $this->warn('consumer.auto_activation_start_date is not set; skipping backfill.');

            return 0;
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Invalid consumer.auto_activation_start_date: '.$startDate);

            return 0;
        }

        $days = (int) config('consumer.days_before_active', 15);
        $enrolled = 0;

        ConsumerZoneOne::query()
            ->where(function ($q) {
                $q->where('status_code', 'P')
                    ->orWhere('status_code', 'p')
                    ->orWhereRaw("UPPER(TRIM(status_code)) = 'PENDING'");
            })
            ->whereNotNull('install_date')
            ->where('created_at', '>=', $start)
            ->where(function ($q) {
                $q->where('pending_install_activation', false)
                    ->orWhereNull('pending_install_activation');
            })
            ->orderBy('id')
            ->chunkById(100, function ($consumers) use ($days, $dryRun, &$enrolled) {
                foreach ($consumers as $consumer) {
                    $activateOn = Carbon::parse($consumer->install_date)->addDays($days)->toDateString();

                    if ($dryRun) {
                        $enrolled++;

                        continue;
                    }

                    DB::table('consumer_zone')
                        ->where('id', $consumer->id)
                        ->update([
                            'pending_install_activation' => true,
                            'auto_activate_on' => $activateOn,
                            'updated_at' => now(),
                        ]);
                    $enrolled++;
                }
            });

        return $enrolled;
    }
}
