<?php

namespace App\Console\Commands;

use App\Http\Controllers\DisconnectionController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoAssignDisconnectionOrdersCommand extends Command
{
    protected $signature = 'disconnection:auto-assign-current-month
                            {--month= : Billing month YYYY-MM (default: current month in app timezone)}
                            {--dry-run : List candidate count without creating orders}';

    protected $description = 'Create disconnection orders for all disconnection-date candidates for a billing month (same logic as the web list), assigned to the default disconnector.';

    public function handle(): int
    {
        $tz = config('app.timezone');
        $billingMonth = $this->option('month')
            ?: Carbon::now($tz)->format('Y-m');

        if (! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            $this->error('Invalid --month; use YYYY-MM.');

            return self::FAILURE;
        }

        try {
            Carbon::createFromFormat('Y-m', $billingMonth)->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Invalid billing month: '.$billingMonth);

            return self::FAILURE;
        }

        $disconnectorId = (int) config('disconnection.default_disconnector_id', 47);
        if (! User::query()->whereKey($disconnectorId)->exists()) {
            $this->error("Default disconnector user id {$disconnectorId} does not exist. Set DISCONNECTOR_DEFAULT_ID in .env.");

            return self::FAILURE;
        }

        /** @var DisconnectionController $controller */
        $controller = app(DisconnectionController::class);

        $candidates = $controller->getConsumersForDisconnection(null, $billingMonth, true);
        $ids = $candidates->pluck('id')->unique()->values()->all();

        $defaultDateYmd = $controller->getDefaultDisconnectionDateFromBilling(null, $billingMonth, null, $candidates);
        $disconnectionDate = Carbon::parse($defaultDateYmd);

        $this->info("Billing month: {$billingMonth}");
        $this->info('Disconnection date: '.$disconnectionDate->format('Y-m-d'));
        $this->info('Candidates: '.count($ids));
        $this->info("Disconnector id: {$disconnectorId}");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no orders created.');

            return self::SUCCESS;
        }

        if (count($ids) === 0) {
            $this->info('No candidates; nothing to do.');

            return self::SUCCESS;
        }

        try {
            $result = $controller->syncDisconnectionOrders(
                $ids,
                $disconnectionDate,
                $disconnectorId,
                $billingMonth,
                null,
                'disconnection_date',
                []
            );
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info("Created: {$result['created']}, updated: {$result['updated']}, skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
