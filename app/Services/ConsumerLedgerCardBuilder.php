<?php

namespace App\Services;

use App\Http\Controllers\ConsumerLedgerController;
use App\Models\ConsumerLedger;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZoneOne;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ConsumerLedgerCardBuilder
{
    /**
     * Build ledger card rows aligned with Account Ledger F10 (same order, dates, amounts, running balance).
     *
     * @param  array{as_of?: string}  $filters
     * @return array{beginning: array<string, mixed>|null, rows: Collection<int, array<string, mixed>>}
     */
    public function build(ConsumerZoneOne $consumer, array $filters): array
    {
        [, $periodEnd] = $this->resolvePeriod($filters);

        $ledgersQuery = ConsumerLedger::query()
            ->with('consumerPayment')
            ->where('consumer_zone_id', $consumer->id);

        ConsumerLedgerController::applyVisibleLedgerScope($ledgersQuery);
        ConsumerLedgerController::applyAccountLedgerDisplayOrder($ledgersQuery);

        $allLedgers = $ledgersQuery->get();

        $balSales = $balPenalty = $balMR = 0.0;
        $runningBalance = 0.0;
        $rows = collect();

        foreach ($allLedgers as $ledger) {
            $ledgerDate = $this->ledgerDate($ledger);

            if ($periodEnd && $ledgerDate && $ledgerDate->gt($periodEnd)) {
                continue;
            }

            $components = $this->mapComponents($ledger, $balSales, $balPenalty, $balMR);

            $balSales += $components['bill_sales'] - $components['pay_sales'];
            $balPenalty += $components['bill_penalty'] - $components['pay_penalty'];
            $balMR += $components['bill_mr'] - $components['pay_mr'];
            $balSales = round($balSales, 2);
            $balPenalty = round($balPenalty, 2);
            $balMR = round($balMR, 2);

            $runningBalance = ConsumerLedgerController::nextRunningBalance($runningBalance, $ledger);
            $this->reconcileComponentBalances($balSales, $balPenalty, $balMR, $runningBalance);

            $rows->push([
                'date' => $this->formatDisplayDate($ledger->date),
                'reference' => $this->formatBillReference($ledger),
                'reading' => $components['reading'],
                'consumption' => $components['consumption'],
                'bill_sales' => $components['bill_sales'],
                'bill_penalty' => $components['bill_penalty'],
                'bill_mr' => $components['bill_mr'],
                'pay_sales' => $components['pay_sales'],
                'pay_penalty' => $components['pay_penalty'],
                'pay_mr' => $components['pay_mr'],
                'bal_sales' => $balSales,
                'bal_penalty' => $balPenalty,
                'bal_mr' => $balMR,
                'total_balance' => $runningBalance,
            ]);
        }

        return [
            'beginning' => null,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{as_of?: string}  $filters
     * @return array{null, Carbon|null}
     */
    public function resolvePeriod(array $filters): array
    {
        $periodEnd = null;

        if (! empty($filters['as_of'])) {
            try {
                $periodEnd = Carbon::parse($filters['as_of'])->endOfDay();
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        return [null, $periodEnd];
    }

    private function ledgerDate(ConsumerLedger $ledger): ?Carbon
    {
        if (empty($ledger->date)) {
            return null;
        }

        try {
            return Carbon::parse($ledger->date)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array{reading: string, consumption: string, bill_sales: float, bill_penalty: float, bill_mr: float, pay_sales: float, pay_penalty: float, pay_mr: float}
     */
    private function mapComponents(ConsumerLedger $ledger, float $balSales, float $balPenalty, float $balMR): array
    {
        $trans = strtoupper(trim($ledger->trans ?? ''));

        if (in_array($trans, ['BILLING', 'BILL'], true)) {
            $billSales = round((float) ($ledger->billamount ?? 0), 2);
            $billMr = round((float) ($ledger->others ?? 0), 2);

            if ($billSales <= 0 && (float) ($ledger->debit ?? 0) > 0) {
                $debit = round((float) $ledger->debit, 2);
                $billMr = $billMr > 0 ? $billMr : 0.0;
                $billSales = round(max(0, $debit - $billMr), 2);
            }

            return [
                'reading' => $this->formatNumber($ledger->reading),
                'consumption' => $this->formatNumber($ledger->volume),
                'bill_sales' => $billSales,
                'bill_penalty' => 0.0,
                'bill_mr' => $billMr,
                'pay_sales' => 0.0,
                'pay_penalty' => 0.0,
                'pay_mr' => 0.0,
            ];
        }

        if ($trans === 'DM') {
            $debit = round((float) ($ledger->debit ?? 0), 2);

            return [
                'reading' => '',
                'consumption' => '',
                'bill_sales' => $debit,
                'bill_penalty' => 0.0,
                'bill_mr' => 0.0,
                'pay_sales' => 0.0,
                'pay_penalty' => 0.0,
                'pay_mr' => 0.0,
            ];
        }

        if ($trans === 'PENALTY') {
            $penalty = round((float) ($ledger->penalty ?: $ledger->debit ?: 0), 2);

            return [
                'reading' => '',
                'consumption' => '',
                'bill_sales' => 0.0,
                'bill_penalty' => $penalty,
                'bill_mr' => 0.0,
                'pay_sales' => 0.0,
                'pay_penalty' => 0.0,
                'pay_mr' => 0.0,
            ];
        }

        if ($trans === 'CM') {
            $credit = round((float) ($ledger->credit ?? 0), 2);
            $allocated = $this->resolvePaymentAllocation($credit, $ledger->consumerPayment, $balSales, $balPenalty, $balMR);

            return [
                'reading' => '',
                'consumption' => '',
                'bill_sales' => 0.0,
                'bill_penalty' => 0.0,
                'bill_mr' => 0.0,
                'pay_sales' => $allocated['pay_sales'],
                'pay_penalty' => $allocated['pay_penalty'],
                'pay_mr' => $allocated['pay_mr'],
            ];
        }

        if ($trans === 'PAYMENT') {
            $credit = round((float) ($ledger->credit ?? 0), 2);
            $allocated = $this->resolvePaymentAllocation($credit, $ledger->consumerPayment, $balSales, $balPenalty, $balMR);

            return [
                'reading' => '',
                'consumption' => '',
                'bill_sales' => 0.0,
                'bill_penalty' => 0.0,
                'bill_mr' => 0.0,
                'pay_sales' => $allocated['pay_sales'],
                'pay_penalty' => $allocated['pay_penalty'],
                'pay_mr' => $allocated['pay_mr'],
            ];
        }

        $debit = round((float) ($ledger->debit ?? 0), 2);
        $credit = round((float) ($ledger->credit ?? 0), 2);

        if ($debit > 0) {
            $billMr = round((float) ($ledger->others ?? 0), 2);
            $billPenalty = round((float) ($ledger->penalty ?? 0), 2);
            $billSales = round((float) ($ledger->billamount ?: max(0, $debit - $billMr - $billPenalty)), 2);

            return [
                'reading' => $this->formatNumber($ledger->reading),
                'consumption' => $this->formatNumber($ledger->volume),
                'bill_sales' => $billSales,
                'bill_penalty' => $billPenalty,
                'bill_mr' => $billMr,
                'pay_sales' => 0.0,
                'pay_penalty' => 0.0,
                'pay_mr' => 0.0,
            ];
        }

        if ($credit > 0) {
            $allocated = $this->resolvePaymentAllocation($credit, $ledger->consumerPayment, $balSales, $balPenalty, $balMR);

            return [
                'reading' => '',
                'consumption' => '',
                'bill_sales' => 0.0,
                'bill_penalty' => 0.0,
                'bill_mr' => 0.0,
                'pay_sales' => $allocated['pay_sales'],
                'pay_penalty' => $allocated['pay_penalty'],
                'pay_mr' => $allocated['pay_mr'],
            ];
        }

        return [
            'reading' => '',
            'consumption' => '',
            'bill_sales' => 0.0,
            'bill_penalty' => 0.0,
            'bill_mr' => 0.0,
            'pay_sales' => 0.0,
            'pay_penalty' => 0.0,
            'pay_mr' => 0.0,
        ];
    }

    /**
     * @return array{pay_sales: float, pay_penalty: float, pay_mr: float}
     */
    private function resolvePaymentAllocation(
        float $credit,
        ?ConsumerPayment $payment,
        float $balSales,
        float $balPenalty,
        float $balMR
    ): array {
        if ($credit <= 0) {
            return ['pay_sales' => 0.0, 'pay_penalty' => 0.0, 'pay_mr' => 0.0];
        }

        if ($payment instanceof ConsumerPayment) {
            $fromPayment = [
                'pay_sales' => round(
                    (float) ($payment->current_bill ?? 0)
                    + (float) ($payment->arrears_cy ?? 0)
                    + (float) ($payment->arrears_py ?? 0),
                    2
                ),
                'pay_penalty' => round((float) ($payment->penalty ?? 0), 2),
                'pay_mr' => round((float) ($payment->meter_maintenance ?? 0), 2),
            ];

            $sum = round($fromPayment['pay_sales'] + $fromPayment['pay_penalty'] + $fromPayment['pay_mr'], 2);

            if ($sum > 0 && abs($sum - $credit) <= 0.02) {
                return $fromPayment;
            }
        }

        return $this->allocatePaymentFifo($credit, $balSales, $balPenalty, $balMR);
    }

    /**
     * Apply payment to outstanding component balances (penalty → M.R. → water sales), same total as ledger credit.
     *
     * @return array{pay_sales: float, pay_penalty: float, pay_mr: float}
     */
    private function allocatePaymentFifo(float $credit, float $balSales, float $balPenalty, float $balMR): array
    {
        $remaining = $credit;

        $payPenalty = round(min($remaining, max(0, $balPenalty)), 2);
        $remaining = round($remaining - $payPenalty, 2);

        $payMr = round(min($remaining, max(0, $balMR)), 2);
        $remaining = round($remaining - $payMr, 2);

        $paySales = round(min($remaining, max(0, $balSales)), 2);
        $remaining = round($remaining - $paySales, 2);

        if ($remaining > 0.005) {
            $paySales = round($paySales + $remaining, 2);
        }

        return [
            'pay_sales' => $paySales,
            'pay_penalty' => $payPenalty,
            'pay_mr' => $payMr,
        ];
    }

    /**
     * Keep component buckets in sync with Account Ledger F10 running balance.
     */
    private function reconcileComponentBalances(float &$balSales, float &$balPenalty, float &$balMR, float $runningBalance): void
    {
        $runningBalance = round($runningBalance, 2);

        if (abs($runningBalance) < 0.005) {
            $balSales = $balPenalty = $balMR = 0.0;

            return;
        }

        $componentSum = round($balSales + $balPenalty + $balMR, 2);

        if (abs($componentSum - $runningBalance) <= 0.02) {
            return;
        }

        if ($componentSum <= 0) {
            $balSales = $runningBalance;
            $balPenalty = 0.0;
            $balMR = 0.0;

            return;
        }

        $balSales = round($balSales + ($runningBalance - $componentSum), 2);
    }

    private function formatBillReference(ConsumerLedger $ledger): string
    {
        $trans = strtoupper(trim($ledger->trans ?? ''));

        if ($trans === 'DM') {
            return 'DM';
        }

        if ($trans === 'CM') {
            return 'CM';
        }

        return trim((string) ($ledger->reference ?? ''));
    }

    private function formatDisplayDate(?string $date): string
    {
        if (! $date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('m-d-y');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $num = (float) $value;

        if ($num == 0.0) {
            return '';
        }

        return fmod($num, 1.0) === 0.0 ? (string) (int) $num : (string) $num;
    }

    public static function formatMoney(?float $amount, bool $blankIfZero = true): string
    {
        $value = round((float) ($amount ?? 0), 2);

        if ($blankIfZero && abs($value) < 0.005) {
            return '';
        }

        return number_format($value, 2, '.', ',');
    }
}
