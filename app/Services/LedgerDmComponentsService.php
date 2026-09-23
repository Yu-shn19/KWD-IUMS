<?php

namespace App\Services;

use App\Http\Controllers\ConsumerLedgerController;
use App\Models\ConsumerLedger;
use App\Models\ConsumerPayment;
use App\Models\Penalty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

if (! function_exists(__NAMESPACE__.'\mr_col')) {
    function mr_col(string $name): string
    {
        return $name;
    }
}

class LedgerDmComponentsService
{
    /** Disconnection candidates: Meter Rental Arrears must be at least this amount. */
    public const DISCONNECTION_METER_RENTAL_ARREARS_THRESHOLD = 40.0;

    /**
     * Same Meter Rental Arrears figure used by Meter Reading Preparation.
     *
     * @param  array<int, int>  $consumerZoneIds
     * @return array<int, array{current_arrears: float, penalty: float, others: float, prio_years: float}>
     */
    public function computeForConsumers(array $consumerZoneIds): array
    {
        $consumerZoneIds = array_values(array_filter(array_map('intval', $consumerZoneIds)));
        if ($consumerZoneIds === []) {
            return [];
        }

        return $this->applyPaymentAndBillingCarryToDmComponents(
            $consumerZoneIds,
            $this->getLedgerDmComponentsByConsumer($consumerZoneIds)
        );
    }

    /**
     * Meter Rental Arrears ← others after DM + unpaid BILLING WMC − payments.
     *
     * @param  array<int, int>  $consumerZoneIds
     * @return array<int, float>
     */
    public function meterRentalArrearsByConsumer(array $consumerZoneIds): array
    {
        $result = [];
        foreach ($this->computeForConsumers($consumerZoneIds) as $cid => $components) {
            $result[(int) $cid] = round((float) ($components['others'] ?? 0), 2);
        }

        return $result;
    }

    public function isEligibleForDisconnectionByMeterRentalArrears(float $meterRentalArrears): bool
    {
        return round($meterRentalArrears, 2) >= self::DISCONNECTION_METER_RENTAL_ARREARS_THRESHOLD;
    }

    /**
     * Payment Breakdown Current Penalty: past (DM / schedule.penalty) + current (posted surcharges).
     * Prefers the full ledger DM+PENALTY unpaid total when it already covers the schedule;
     * otherwise adds schedule past + unpaid posted penalty so past is never absorbed into arrears.
     */
    public function unpaidPenaltyForPaymentBreakdown(int $consumerZoneId, float $schedulePenalty = 0.0): float
    {
        if ($consumerZoneId <= 0) {
            return round(max(0.0, $schedulePenalty), 2);
        }

        $schedulePenalty = round(max(0.0, $schedulePenalty), 2);
        $components = $this->computeForConsumers([$consumerZoneId]);
        $ledgerPenalty = round((float) ($components[$consumerZoneId]['penalty'] ?? 0), 2);
        $postedPenalty = Penalty::unpaidAmountForConsumer($consumerZoneId);

        // Ledger already has past DM + current PENALTY rows (minus payments).
        if ($ledgerPenalty + 0.009 >= $schedulePenalty) {
            return $ledgerPenalty;
        }

        // Schedule still has past that ledger has not fully reflected — add current posted on top.
        return round($schedulePenalty + $postedPenalty, 2);
    }

    /**
     * Ledger DM breakdown used by Meter Reading Preparation.
     * Arrears ← current_arrears; Penalty ← penalty (DM + PENALTY rows);
     * Meter Rental Arrears ← others on DM only; Prior Years ← prio_years (PY).
     *
     * @param  array<int, int>  $consumerZoneIds
     * @return array<int, array{current_arrears: float, penalty: float, others: float, prio_years: float}>
     */
    public function getLedgerDmComponentsByConsumer(array $consumerZoneIds): array
    {
        $consumerZoneIds = array_values(array_filter(array_map('intval', $consumerZoneIds)));
        if ($consumerZoneIds === []) {
            return [];
        }

        $hasPrioYears = Schema::hasColumn('consumer_ledgers', 'prio_years');
        $hasCurrentArrears = Schema::hasColumn('consumer_ledgers', 'current_arrears');

        $query = ConsumerLedger::query()
            ->whereIn(mr_col('consumer_zone_id'), $consumerZoneIds)
            ->whereRaw("UPPER(TRIM(trans)) IN ('DM', 'PENALTY')");

        ConsumerLedgerController::applyVisibleLedgerScope($query);

        $currentArrearsSql = $hasCurrentArrears
            ? "SUM(CASE WHEN UPPER(TRIM(trans)) = 'DM' THEN COALESCE(current_arrears, 0) ELSE 0 END)"
            : '0';
        $prioYearsSql = $hasPrioYears
            ? "SUM(CASE WHEN UPPER(TRIM(trans)) = 'DM' THEN COALESCE(prio_years, 0) ELSE 0 END)"
            : '0';
        $othersSql = "SUM(CASE WHEN UPPER(TRIM(trans)) = 'DM' THEN COALESCE(others, 0) ELSE 0 END)";
        $penaltySql = 'SUM(COALESCE(penalty, 0))';

        $rows = $query
            ->selectRaw('consumer_zone_id')
            ->selectRaw("{$currentArrearsSql} as current_arrears")
            ->selectRaw("{$penaltySql} as penalty")
            ->selectRaw("{$othersSql} as others")
            ->selectRaw("{$prioYearsSql} as prio_years")
            ->groupBy(mr_col('consumer_zone_id'))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->consumer_zone_id] = [
                'current_arrears' => round((float) ($row->current_arrears ?? 0), 2),
                'penalty' => round((float) ($row->penalty ?? 0), 2),
                'others' => round((float) ($row->others ?? 0), 2),
                'prio_years' => round((float) ($row->prio_years ?? 0), 2),
            ];
        }

        return $result;
    }

    /**
     * Next-month assign buckets:
     * - Subtract payments from the same column (Prior Years first via prio_years).
     * - Unpaid BILLING billamount → Current Arrears (not Prior Years).
     * - Unpaid BILLING others (WMC) → MR Arrears.
     *
     * @param  array<int, int>  $consumerZoneIds
     * @param  array<int, array{current_arrears: float, penalty: float, others: float, prio_years: float}>  $dmByConsumer
     * @return array<int, array{current_arrears: float, penalty: float, others: float, prio_years: float}>
     */
    public function applyPaymentAndBillingCarryToDmComponents(array $consumerZoneIds, array $dmByConsumer): array
    {
        $consumerZoneIds = array_values(array_filter(array_map('intval', $consumerZoneIds)));
        if ($consumerZoneIds === []) {
            return $dmByConsumer;
        }

        $latestDmIdByConsumer = ConsumerLedger::query()
            ->whereIn(mr_col('consumer_zone_id'), $consumerZoneIds)
            ->whereRaw("UPPER(TRIM(trans)) = 'DM'");
        ConsumerLedgerController::applyVisibleLedgerScope($latestDmIdByConsumer);
        $latestDmIdByConsumer = $latestDmIdByConsumer
            ->selectRaw('consumer_zone_id, MAX(id) as max_id')
            ->groupBy(mr_col('consumer_zone_id'))
            ->pluck('max_id', 'consumer_zone_id')
            ->all();
        $normalizedDmIds = [];
        foreach ($latestDmIdByConsumer as $cid => $maxId) {
            $normalizedDmIds[(int) $cid] = (int) $maxId;
        }
        $latestDmIdByConsumer = $normalizedDmIds;

        $dmCutoffByConsumer = [];
        if ($latestDmIdByConsumer !== []) {
            $dmRows = ConsumerLedger::query()
                ->whereIn(mr_col('id'), array_values($latestDmIdByConsumer))
                ->get(['id', 'consumer_zone_id', 'txtime', 'date', 'created_at']);
            foreach ($dmRows as $dmRow) {
                $cutoff = $dmRow->txtime ?? $dmRow->date ?? $dmRow->created_at;
                if ($cutoff) {
                    $dmCutoffByConsumer[(int) $dmRow->consumer_zone_id] = Carbon::parse($cutoff);
                }
            }
        }

        $billingCarryByConsumer = [];
        $billingQuery = ConsumerLedger::query()
            ->whereIn(mr_col('consumer_zone_id'), $consumerZoneIds)
            ->whereRaw("UPPER(TRIM(trans)) = 'BILLING'");
        ConsumerLedgerController::applyVisibleLedgerScope($billingQuery);
        if ($latestDmIdByConsumer !== []) {
            $billingQuery->where(function ($q) use ($latestDmIdByConsumer, $consumerZoneIds) {
                $withDm = [];
                foreach ($consumerZoneIds as $cid) {
                    if (isset($latestDmIdByConsumer[$cid])) {
                        $q->orWhere(function ($inner) use ($cid, $latestDmIdByConsumer) {
                            $inner->where(mr_col('consumer_zone_id'), $cid)
                                ->where(mr_col('id'), '>', $latestDmIdByConsumer[$cid]);
                        });
                        $withDm[] = $cid;
                    }
                }
                $withoutDm = array_values(array_diff($consumerZoneIds, $withDm));
                if ($withoutDm !== []) {
                    $q->orWhereIn(mr_col('consumer_zone_id'), $withoutDm);
                }
            });
        }
        $billingRows = $billingQuery
            ->selectRaw('consumer_zone_id')
            ->selectRaw('SUM(COALESCE(billamount, 0)) as billamount')
            ->selectRaw('SUM(COALESCE(others, 0)) as others')
            ->groupBy(mr_col('consumer_zone_id'))
            ->get();
        foreach ($billingRows as $row) {
            $billingCarryByConsumer[(int) $row->consumer_zone_id] = [
                'billamount' => round((float) ($row->billamount ?? 0), 2),
                'others' => round((float) ($row->others ?? 0), 2),
            ];
        }

        $paidByConsumer = [];
        if (Schema::hasTable('consumer_payments')) {
            $paymentQuery = ConsumerPayment::query()
                ->whereIn(ConsumerPayment::consumerZoneIdColumn(), $consumerZoneIds)
                ->where(mr_col('payment_amount'), '>', 0)
                ->where(function ($q) {
                    $q->whereNull(mr_col('remarks'))
                        ->orWhere(mr_col('remarks'), 'not like', 'Cancelled OR#%');
                });
            foreach ($paymentQuery->get() as $payment) {
                $cid = (int) ($payment->consumer_zone_id ?? $payment->consumer_id ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $cutoff = $dmCutoffByConsumer[$cid] ?? null;
                $paidAt = $payment->paid_at ?? $payment->created_at;
                if ($cutoff && $paidAt && Carbon::parse($paidAt)->lt($cutoff)) {
                    continue;
                }
                if (! isset($paidByConsumer[$cid])) {
                    $paidByConsumer[$cid] = [
                        'current_billing' => 0.0,
                        'current_mr' => 0.0,
                        'prio_years' => 0.0,
                        'current_arrears' => 0.0,
                        'current_penalty' => 0.0,
                        'mr_arrears' => 0.0,
                    ];
                }
                $paidByConsumer[$cid]['current_billing'] += (float) ($payment->current_billing ?? 0);
                $paidByConsumer[$cid]['current_mr'] += (float) ($payment->current_mr ?? 0);
                $paidByConsumer[$cid]['prio_years'] += (float) ($payment->prio_years ?? 0);
                $paidByConsumer[$cid]['current_arrears'] += (float) ($payment->current_arrears ?? 0);
                $paidByConsumer[$cid]['current_penalty'] += (float) ($payment->current_penalty ?? 0);
                $paidByConsumer[$cid]['mr_arrears'] += (float) ($payment->mr_arrears ?? 0);
            }
        }

        foreach ($consumerZoneIds as $cid) {
            $dm = $dmByConsumer[$cid] ?? [
                'current_arrears' => 0.0,
                'penalty' => 0.0,
                'others' => 0.0,
                'prio_years' => 0.0,
            ];
            $paid = $paidByConsumer[$cid] ?? [
                'current_billing' => 0.0,
                'current_mr' => 0.0,
                'prio_years' => 0.0,
                'current_arrears' => 0.0,
                'current_penalty' => 0.0,
                'mr_arrears' => 0.0,
            ];
            $billing = $billingCarryByConsumer[$cid] ?? [
                'billamount' => 0.0,
                'others' => 0.0,
            ];

            $unpaidCurrentBill = round(max(0.0, $billing['billamount'] - ($paid['current_billing'] ?? 0)), 2);
            $unpaidWmc = round(max(0.0, $billing['others'] - ($paid['current_mr'] ?? 0)), 2);

            $dmByConsumer[$cid] = [
                'prio_years' => round(max(0.0, (float) $dm['prio_years'] - ($paid['prio_years'] ?? 0)), 2),
                'current_arrears' => round(max(0.0, (float) $dm['current_arrears'] - ($paid['current_arrears'] ?? 0)) + $unpaidCurrentBill, 2),
                'penalty' => round(max(0.0, (float) $dm['penalty'] - ($paid['current_penalty'] ?? 0)), 2),
                'others' => round(max(0.0, (float) $dm['others'] - ($paid['mr_arrears'] ?? 0)) + $unpaidWmc, 2),
            ];
        }

        return $dmByConsumer;
    }
}
