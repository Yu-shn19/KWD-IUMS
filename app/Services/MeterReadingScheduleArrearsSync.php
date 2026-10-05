<?php

namespace App\Services;

use App\Http\Controllers\ConsumerLedgerController;
use App\Models\MeterReadingSchedule;
use Illuminate\Support\Facades\Schema;

/**
 * Keep open meter_reading_schedules.arrears / total_amount in sync with
 * Account Ledger Current Balance (same footer formula as Meter Reading Preparation).
 */
class MeterReadingScheduleArrearsSync
{
    /** Statuses that still carry a live arrears snapshot for field use. */
    private const OPEN_STATUSES = ['Prepared', 'Assigned', 'In Progress'];

    /**
     * Refresh open schedules for one consumer after a ledger-crediting payment.
     * Does not create tables; only updates existing schedule money columns.
     * Leaves settled / paid bill months alone.
     *
     * @return int Number of schedules updated
     */
    public static function refreshForConsumer(int $consumerZoneId): int
    {
        $consumerZoneId = (int) $consumerZoneId;
        if ($consumerZoneId <= 0 || ! Schema::hasTable('meter_reading_schedules')) {
            return 0;
        }

        $schedules = self::openSchedulesForConsumer($consumerZoneId);
        if ($schedules->isEmpty()) {
            return 0;
        }

        $footerBalances = ConsumerLedgerController::computeAccountLedgerFooterBalancesBulk([$consumerZoneId]);
        $ledgerBalance = round((float) ($footerBalances[$consumerZoneId] ?? 0), 2);

        $updated = 0;
        foreach ($schedules as $schedule) {
            if (self::refreshSchedule($schedule, $ledgerBalance)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Open = Prepared / Assigned / In Progress, or Completed with downloaded reading not yet paid.
     *
     * @return \Illuminate\Support\Collection<int, MeterReadingSchedule>
     */
    private static function openSchedulesForConsumer(int $consumerZoneId)
    {
        $query = MeterReadingSchedule::query()
            ->where('consumer_zone_id', $consumerZoneId)
            ->where(function ($q) {
                $q->whereIn('status', self::OPEN_STATUSES)
                    ->orWhere(function ($completed) {
                        $completed->whereIn('status', ['Completed', 'Verified'])
                            ->whereHas('downloadedReading', function ($dr) {
                                // Not settled: no paid_at and status is not "paid".
                                $dr->whereNull('paid_at')
                                    ->where(function ($status) {
                                        $status->whereNull('status')
                                            ->orWhereRaw('LOWER(status) <> ?', ['paid']);
                                    });
                            });
                    });
            });

        return $query->get();
    }

    /**
     * Recompute arrears from ledger footer Current Balance (prep/assign collapse pattern).
     * total_amount = current_billing + arrears.
     */
    private static function refreshSchedule(MeterReadingSchedule $schedule, float $ledgerBalance): bool
    {
        $currentBill = round((float) ($schedule->current_billing ?? 0), 2);
        $storedArrears = round((float) ($schedule->arrears ?? 0), 2);
        $storedPenalty = round((float) ($schedule->penalty ?? 0), 2);
        $storedMr = round((float) ($schedule->meter_rental_arrears ?? 0), 2);
        $storedPrior = round((float) ($schedule->prior_years ?? 0), 2);
        $storedOutstanding = round($storedArrears + $storedPenalty + $storedMr + $storedPrior, 2);

        // Same as Meter Reading Preparation / assign / mobile unread overlay:
        // when stored components diverge from Account Ledger footer, collapse into arrears.
        // If current_billing is already on the schedule (Completed, unpaid), keep that
        // amount out of arrears so total_amount = current_bill + arrears equals the footer.
        $targetArrears = $ledgerBalance;
        if ($currentBill > 0.009) {
            $targetArrears = round($ledgerBalance - $currentBill, 2);
        }
        $targetTotal = round($currentBill + $targetArrears, 2);
        $storedTotal = round((float) ($schedule->total_amount ?? 0), 2);

        if (
            abs($storedOutstanding - $targetArrears) <= 0.02
            && abs($storedTotal - $targetTotal) <= 0.02
            && abs($storedArrears - $targetArrears) <= 0.02
            && abs($storedPenalty) <= 0.02
            && abs($storedMr) <= 0.02
            && abs($storedPrior) <= 0.02
        ) {
            return false;
        }

        $schedule->update(MeterReadingSchedule::filterTableAttributes([
            'arrears' => $targetArrears,
            'penalty' => 0,
            'meter_rental_arrears' => 0,
            'prior_years' => 0,
            'total_amount' => $targetTotal,
        ]));

        return true;
    }
}
