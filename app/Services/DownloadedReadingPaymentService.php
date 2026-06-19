<?php

namespace App\Services;

use App\Http\Controllers\ConsumerLedgerController;
use App\Models\ConsumerLedger;
use App\Models\ConsumerPayment;
use App\Models\ConsumerZone;
use App\Support\SundryLedgerRemarks;
use App\Models\DisconnectionOrder;
use App\Models\DownloadedReading;
use App\Models\LROLedger;
use App\Models\MeterReadingSchedule;
use App\Models\Penalty;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    function mr_col(string $name): string
    {
        return $name;
    }
}

class DownloadedReadingPaymentService
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function process(array $validated): array
    {
        DB::beginTransaction();

        try {
            $context = $this->resolveContext($validated);
            $isUpdate = (bool) ($validated['is_update'] ?? false);

            $this->assertOrNumberAvailable($validated, $context, $isUpdate);

            $change = max(0, round($validated['amount_tendered'] - $validated['amount_due'], 2));
            $now = Carbon::now();
            $paidAt = !empty($validated['transaction_date'])
                ? Carbon::parse($validated['transaction_date'])->setTimeFromTimeString($now->format('H:i:s'))
                : $now;

            $paymentData = $this->buildPaymentData($validated, $context, $change, $paidAt);
            $consumerPayment = $this->upsertConsumerPayment($validated, $context, $paymentData, $isUpdate);

            if (!$isUpdate && !empty($validated['sundries']) && $context->consumerId) {
                $this->validateSundryCharges($validated, $context->consumerId);
            }

            $this->cancelDisconnectionOrdersIfNeeded($context, $consumerPayment);

            $billPaymentAmount = $this->computeBillPaymentAmount($validated);

            if ($context->consumerId) {
                $this->applyPaymentToUnpaidCharges(
                    $context->consumerId,
                    $billPaymentAmount,
                    $paidAt,
                    isset($validated['pay_months']) ? (int) $validated['pay_months'] : null
                );
            }

            $shouldMarkAsPaid = $this->shouldMarkReadingAsPaid(
                $context,
                $validated,
                $context->outstandingBalance
            );

            if ($context->downloaded) {
                $this->syncDownloadedReadingStatus(
                    $context->downloaded,
                    $shouldMarkAsPaid,
                    $paidAt,
                    $now,
                    $context->consumerId
                );
            }

            if ($context->consumerId && $billPaymentAmount > 0) {
                $this->syncPaymentLedgerRows(
                    $validated,
                    $context,
                    $consumerPayment,
                    $billPaymentAmount,
                    $paidAt,
                    $isUpdate
                );
            }

            if (!$isUpdate && !empty($validated['sundries']) && $context->consumerId) {
                $this->createSundryCredits($validated, $context->consumerId, $paidAt);
            }

            $result = [
                'downloaded_id' => $context->downloaded?->id,
                'status' => 'Paid',
                'status_code' => 'paid',
                'paid_at' => $consumerPayment->paid_at?->format('Y-m-d H:i:s'),
                'payment_method' => $consumerPayment->payment_method,
                'payment_amount' => (float) $consumerPayment->payment_amount,
                'amount_tendered' => (float) $consumerPayment->amount_tendered,
                'change_amount' => (float) $consumerPayment->change_amount,
                'payment_reference' => null,
                'payment_remarks' => $consumerPayment->remarks,
                'official_receipt_number' => $consumerPayment->or_number,
            ];

            DB::commit();

            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveContext(array $validated): DownloadedReadingPaymentContext
    {
        $context = new DownloadedReadingPaymentContext();
        $context->accountNumber = $validated['account_number'] ?? null;

        if (!empty($validated['downloaded_id'])) {
            $context->downloaded = DownloadedReading::lockForUpdate()
                ->with('schedule')
                ->findOrFail($validated['downloaded_id']);
            $context->accountNumber = $context->downloaded->account_number ?? $context->accountNumber;
        }

        if ($context->accountNumber) {
            $context->consumer = $this->findConsumerByAccountNumber($context->accountNumber);
            if (!$context->consumer && $context->downloaded && $context->downloaded->schedule) {
                $consumerZoneId = $context->downloaded->schedule->consumer_zone_id ?? null;
                if ($consumerZoneId) {
                    $context->consumer = ConsumerZone::find($consumerZoneId);
                }
            }
            if ($context->consumer) {
                $context->consumerId = $context->consumer->id;
                $latestLedgerEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $context->consumer->id)
                    ->whereNotNull(mr_col('balance'))
                    ->orderBy(mr_col('date'), 'desc')
                    ->orderBy(mr_col('id'), 'desc')
                    ->first();
                $context->outstandingBalance = $latestLedgerEntry
                    ? (float) ($latestLedgerEntry->balance ?? 0)
                    : (float) ($context->consumer->balance ?? 0);
            }
        }

        if (!$context->downloaded && $context->consumerId) {
            $context->consumer = $context->consumer ?? ConsumerZone::find($context->consumerId);
            if ($context->consumer && !empty(trim($context->consumer->account_no ?? ''))) {
                $context->downloaded = DownloadedReading::lockForUpdate()
                    ->with('schedule')
                    ->forConsumerZone($context->consumerId)
                    ->orderBy(mr_col('reading_date'), 'desc')
                    ->orderBy(mr_col('id'), 'desc')
                    ->first();
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertOrNumberAvailable(
        array $validated,
        DownloadedReadingPaymentContext $context,
        bool $isUpdate
    ): void {
        if (empty($validated['official_receipt_number'])) {
            return;
        }

        $q = ConsumerPayment::query()->where(mr_col('or_number'), $validated['official_receipt_number']);

        if ($isUpdate) {
            $paymentBeingUpdated = null;
            if ($context->downloaded) {
                $paymentBeingUpdated = ConsumerPayment::query()->where(mr_col('reading_id'), $context->downloaded->id)
                    ->where(mr_col('or_number'), $validated['official_receipt_number'])
                    ->first();
            }
            if (!$paymentBeingUpdated) {
                $byOr = ConsumerPayment::query()->where(mr_col('or_number'), $validated['official_receipt_number']);
                if ($context->consumerId) {
                    $byOr->where(ConsumerPayment::consumerZoneIdColumn(), $context->consumerId);
                }
                $paymentBeingUpdated = $byOr->first();
            }
            if ($paymentBeingUpdated) {
                $q->where(mr_col('id'), '!=', $paymentBeingUpdated->id);
            } elseif ($context->downloaded) {
                $q->where(mr_col('reading_id'), '!=', $context->downloaded->id);
            }
        }

        if ($q->exists()) {
            throw new \Exception(
                "Official Receipt Number {$validated['official_receipt_number']} is already in use by another payment. Please use a different OR number."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPaymentData(
        array $validated,
        DownloadedReadingPaymentContext $context,
        float $change,
        Carbon $paidAt
    ): array {
        return ConsumerPayment::filterTableAttributes([
            'consumer_zone_id' => $context->consumerId,
            'payment_method' => $validated['payment_method'],
            'payment_amount' => round($validated['amount_due'], 2),
            'amount_tendered' => round($validated['amount_tendered'], 2),
            'change_amount' => $change,
            'senior_citizen_discount' => round($validated['senior_citizen_discount'] ?? 0, 2),
            'current_bill' => round($validated['current_bill'] ?? 0, 2),
            'penalty' => round($validated['penalty'] ?? 0, 2),
            'meter_maintenance' => round($validated['meter_maintenance'] ?? 0, 2),
            'arrears_cy' => round($validated['arrears_cy'] ?? 0, 2),
            'arrears_py' => round($validated['arrears_py'] ?? 0, 2),
            'advances' => round($validated['advances'] ?? 0, 2),
            'others' => round($validated['others'] ?? 0, 2),
            'materials' => round($validated['materials'] ?? 0, 2),
            'fees_charges' => round($validated['fees_charges'] ?? 0, 2),
            'inspection_fee' => round($validated['inspection_fee'] ?? 0, 2),
            'or_number' => $validated['official_receipt_number'] ?? null,
            'paid_at' => $paidAt,
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $this->getFormattedUserName(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $paymentData
     */
    private function upsertConsumerPayment(
        array $validated,
        DownloadedReadingPaymentContext $context,
        array $paymentData,
        bool $isUpdate
    ): ConsumerPayment {
        if ($context->downloaded) {
            if ($isUpdate) {
                if (empty($validated['official_receipt_number'])) {
                    throw new \Exception('OR number is required to update an existing payment. Enter the OR number of the payment you want to update.');
                }
                $consumerPayment = ConsumerPayment::query()->where(mr_col('reading_id'), $context->downloaded->id)
                    ->where(mr_col('or_number'), $validated['official_receipt_number'])
                    ->first();
                if (!$consumerPayment && $context->consumerId) {
                    $consumerPayment = ConsumerPayment::forConsumerZone($context->consumerId)
                        ->where(mr_col('or_number'), $validated['official_receipt_number'])
                        ->first();
                }
                if ($consumerPayment) {
                    $consumerPayment->update($paymentData);

                    return $consumerPayment;
                }
                throw new \Exception(
                    'No payment found with OR #' . $validated['official_receipt_number'] . '.' .
                    ($context->consumerId ? ' Ensure the account number matches the payment.' : ' Load the record by OR number or enter account number first.')
                );
            }

            return ConsumerPayment::create(array_merge($paymentData, ['reading_id' => $context->downloaded->id]));
        }

        if ($isUpdate && !empty($validated['official_receipt_number'])) {
            $byOr = ConsumerPayment::query()->where(mr_col('or_number'), $validated['official_receipt_number']);
            if ($context->consumerId) {
                $byOr->where(ConsumerPayment::consumerZoneIdColumn(), $context->consumerId);
            }
            $consumerPayment = $byOr->first();
            if ($consumerPayment) {
                if (!$context->consumerId && ($consumerPayment->consumer_zone_id ?? $consumerPayment->consumer_id)) {
                    $context->consumerId = $consumerPayment->consumer_zone_id ?? $consumerPayment->consumer_id;
                }
                $consumerPayment->update($paymentData);

                return $consumerPayment;
            }
            throw new \Exception(
                'No payment found with OR #' . $validated['official_receipt_number'] . '.' .
                ($context->consumerId ? ' Ensure the account number matches the payment.' : ' Enter account number and try again.')
            );
        }

        return ConsumerPayment::create(array_merge($paymentData, ['reading_id' => null]));
    }

    private function cancelDisconnectionOrdersIfNeeded(
        DownloadedReadingPaymentContext $context,
        ConsumerPayment $consumerPayment
    ): void {
        if ($context->consumerId && $consumerPayment->paid_at) {
            $cancelled = DisconnectionOrder::cancelActiveOrdersForConsumerDueToPayment(
                $context->consumerId,
                $consumerPayment->paid_at
            );
            if ($cancelled > 0) {
                Log::info('Disconnection orders cancelled due to payment', [
                    'consumer_id' => $context->consumerId,
                    'account' => $context->accountNumber,
                    'cancelled_count' => $cancelled,
                ]);
            }

            return;
        }

        if ($consumerPayment->paid_at && !$context->consumerId) {
            Log::warning('Disconnection cancel skipped: payment saved but consumer not resolved', [
                'account_number' => $context->accountNumber,
                'downloaded_id' => $context->downloaded?->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function computeBillPaymentAmount(array $validated): float
    {
        $sundriesTotal = 0;
        foreach ($validated['sundries'] ?? [] as $s) {
            $sundriesTotal += round((float) ($s['amount'] ?? 0), 2);
        }

        return max(0, round(($validated['amount_due'] ?? 0) - $sundriesTotal, 2));
    }

    private function applyPaymentToUnpaidCharges(
        int $consumerId,
        float $billPaymentAmount,
        Carbon $paidAt,
        ?int $payMonths
    ): void {
        $remaining = $billPaymentAmount;
        $maxBillingRowsToMark = ($payMonths >= 1 && $payMonths <= 3) ? $payMonths : PHP_INT_MAX;

        $unpaidBillingRows = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->whereIn(mr_col('trans'), ['BILLING', 'BILL'])
            ->where(mr_col('billamount'), '>', 0)
            ->whereNull(mr_col('paid_at'))
            ->with('schedule:id,bill_month,due_date')
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc')
            ->get();

        $currentYear = $this->resolveCurrentYearFromBillingRows($unpaidBillingRows);
        $pyBilling = $this->filterBillingRowsByYear($unpaidBillingRows, $currentYear, false);
        $cyBilling = $this->filterBillingRowsByYear($unpaidBillingRows, $currentYear, true);

        $remaining = $this->markBillingRowsPaid($pyBilling, $paidAt, $remaining);
        $remaining = $this->markPenaltyLedgerRowsPaid($consumerId, $paidAt, $remaining);
        $remaining = $this->markPenaltyModelRowsPaid($consumerId, $paidAt, $remaining);
        $this->markBillingRowsPaid($cyBilling, $paidAt, $remaining, $maxBillingRowsToMark);
    }

    /**
     * @param  Collection<int, ConsumerLedger>  $rows
     */
    private function resolveCurrentYearFromBillingRows(Collection $rows): int
    {
        if ($rows->isEmpty()) {
            return (int) Carbon::now()->format('Y');
        }

        return (int) $rows->max(function ($row) {
            if ($row instanceof ConsumerLedger) {
                $billMonth = $this->getBillMonthFromRow($row);

                return $billMonth ? (int) $billMonth->format('Y') : (int) date('Y');
            }

            return (int) date('Y');
        });
    }

    /**
     * @param  Collection<int, ConsumerLedger>  $rows
     * @return Collection<int, ConsumerLedger>
     */
    private function filterBillingRowsByYear(Collection $rows, int $currentYear, bool $currentYearOnly): Collection
    {
        return $rows->filter(function ($row) use ($currentYear, $currentYearOnly) {
            if (!($row instanceof ConsumerLedger)) {
                return false;
            }
            $rowYear = (int) (($this->getBillMonthFromRow($row)?->format('Y')) ?? $currentYear);

            return $currentYearOnly ? $rowYear >= $currentYear : $rowYear < $currentYear;
        });
    }

    /**
     * @param  Collection<int, ConsumerLedger>  $billingRows
     */
    private function markBillingRowsPaid(
        Collection $billingRows,
        Carbon $paidAt,
        float $remaining,
        int $maxRows = PHP_INT_MAX
    ): float {
        $rowsMarked = 0;
        foreach ($billingRows as $billingRow) {
            if (!($billingRow instanceof ConsumerLedger)) {
                continue;
            }
            if ($rowsMarked >= $maxRows) {
                break;
            }
            $principal = (float) ($billingRow->billamount ?? 0);
            $wmc = (float) ($billingRow->others ?? 0);
            $totalCharge = $principal + $wmc;
            if ($totalCharge <= 0) {
                continue;
            }
            if ($remaining + 0.009 >= $totalCharge) {
                $billingRow->paid_at = $paidAt;
                $billingRow->save();
                $remaining -= $totalCharge;
                $rowsMarked++;
            } else {
                break;
            }
        }

        return $remaining;
    }

    private function markPenaltyLedgerRowsPaid(int $consumerId, Carbon $paidAt, float $remaining): float
    {
        $unpaidPenaltyRows = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->where(mr_col('trans'), 'PENALTY')
            ->where(function ($q) {
                $q->where(mr_col('penalty'), '>', 0)->orWhere(mr_col('debit'), '>', 0);
            })
            ->whereNull(mr_col('paid_at'))
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc')
            ->get();

        foreach ($unpaidPenaltyRows as $penaltyRow) {
            if (!($penaltyRow instanceof ConsumerLedger)) {
                continue;
            }
            $amt = (float) ($penaltyRow->penalty ?? 0);
            if ($amt <= 0) {
                $amt = (float) ($penaltyRow->debit ?? 0);
            }
            if ($amt <= 0) {
                continue;
            }
            if ($remaining + 0.009 >= $amt) {
                $penaltyRow->paid_at = $paidAt;
                $penaltyRow->save();
                $remaining -= $amt;
            } else {
                break;
            }
        }

        return $remaining;
    }

    private function markPenaltyModelRowsPaid(int $consumerId, Carbon $paidAt, float $remaining): float
    {
        $unpaidPenaltyModels = Penalty::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->whereNull(mr_col('paid_at'))
            ->orderBy(mr_col('date'), 'asc')
            ->orderBy(mr_col('id'), 'asc')
            ->get();

        foreach ($unpaidPenaltyModels as $p) {
            if (!($p instanceof Penalty)) {
                continue;
            }
            $amt = (float) ($p->penalty_amount ?? 0);
            if ($amt <= 0) {
                continue;
            }
            if ($remaining + 0.009 >= $amt) {
                Penalty::query()->where(mr_col('id'), $p->id)->update(['paid_at' => $paidAt]);
                $remaining -= $amt;
            } else {
                break;
            }
        }

        return $remaining;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function shouldMarkReadingAsPaid(
        DownloadedReadingPaymentContext $context,
        array $validated,
        ?float $outstandingBalance
    ): bool {
        $accountForBalance = $context->downloaded
            ? $context->downloaded->account_number
            : ($validated['account_number'] ?? null);

        if (!$context->consumer || !$accountForBalance) {
            return true;
        }

        $balanceAfterPayment = 0;
        try {
            $ledgerController = new ConsumerLedgerController();
            $ledgerRequest = new Request();
            $ledgerRequest->merge([
                'account_no' => $accountForBalance,
                'year' => '',
            ]);

            $ledgerResponse = $ledgerController->getLedger($ledgerRequest);
            $ledgerData = json_decode($ledgerResponse->getContent(), true);

            if (isset($ledgerData['summary']['balance'])) {
                $balanceAfterPayment = (float) $ledgerData['summary']['balance'];
            } else {
                $balanceAfterPayment = ($outstandingBalance ?? 0) - $validated['amount_due'];
            }
        } catch (\Exception $e) {
            Log::error('Error getting balance from ledger in payment processing: ' . $e->getMessage());
            $balanceAfterPayment = ($outstandingBalance ?? 0) - $validated['amount_due'];
        }

        return $balanceAfterPayment <= 0.01;
    }

    private function syncDownloadedReadingStatus(
        DownloadedReading $downloaded,
        bool $shouldMarkAsPaid,
        Carbon $paidAt,
        Carbon $now,
        ?int $consumerId
    ): void {
        if ($shouldMarkAsPaid) {
            $downloaded->status = 'paid';
            $downloaded->paid_at = $paidAt;
        } elseif ($downloaded->status !== 'completed') {
            $downloaded->status = 'completed';
        }

        $downloaded->prepared_by = $this->getFormattedUserName();
        if (Schema::hasColumn('downloaded_readings', 'completed_at') && !$downloaded->completed_at) {
            $downloaded->completed_at = $now;
        }
        $downloaded->save();

        if (!$downloaded->schedule) {
            return;
        }

        $schedule = $downloaded->schedule;
        $schedule->update(MeterReadingSchedule::filterTableAttributes([
            'status' => 'Completed',
        ]));

        $dueDate = $schedule->due_date ? Carbon::parse($schedule->due_date) : null;
        if (!$dueDate || !$paidAt->lte($dueDate->endOfDay())) {
            return;
        }

        $billAmount = (float) ($schedule->current_bill ?? $downloaded->current_bill ?? 0);
        $totalPaid = ConsumerPayment::query()->where(mr_col('reading_id'), $downloaded->id)
            ->whereNotNull(mr_col('paid_at'))
            ->whereDate(mr_col('paid_at'), '<=', $dueDate->format('Y-m-d'))
            ->selectRaw('COALESCE(SUM(payment_amount + COALESCE(senior_citizen_discount, 0)), 0) as total')
            ->value('total');

        if ($billAmount <= 0 || $totalPaid + 0.01 < $billAmount || !$consumerId) {
            return;
        }

        $penaltiesToDelete = Penalty::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->where(mr_col('schedule_id'), $schedule->id)
            ->pluck(mr_col('id'));

        if ($penaltiesToDelete->isEmpty()) {
            return;
        }

        Penalty::query()->whereIn(mr_col('id'), $penaltiesToDelete)->delete();
        ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->where(mr_col('trans'), 'PENALTY')
            ->whereIn(mr_col('schedule_id'), [$schedule->id])
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncPaymentLedgerRows(
        array $validated,
        DownloadedReadingPaymentContext $context,
        ConsumerPayment $consumerPayment,
        float $billPaymentAmount,
        Carbon $paidAt,
        bool $isUpdate
    ): void {
        $consumerId = $context->consumerId;
        $downloaded = $context->downloaded;
        $outstandingBalance = $context->outstandingBalance;

        $newBalance = ($outstandingBalance ?? 0) - $billPaymentAmount;

        if ($isUpdate) {
            $newBalance = $this->resolveBalanceForPaymentUpdate($consumerId, $consumerPayment, $billPaymentAmount, $newBalance);
        }

        $orNumber = $validated['official_receipt_number'] ?? (
            $downloaded ? 'Payment DR#' . $downloaded->id : 'Payment ACCT#' . ($validated['account_number'] ?? '')
        );
        $ledgerDate = $paidAt;
        $readingId = $downloaded?->id;
        $scheduleId = $downloaded?->schedule_id;

        if ($downloaded && !$isUpdate) {
            ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
                ->where(mr_col('trans'), 'PAYMENT')
                ->where(mr_col('downloaded_reading_id'), $downloaded->id)
                ->where(mr_col('reference'), 'like', '%-SC')
                ->whereNull(mr_col('consumer_payment_id'))
                ->delete();
        }

        $ledgerPayload = [
            'consumer_zone_id' => $consumerId,
            'consumer_payment_id' => $consumerPayment->id,
            'schedule_id' => $scheduleId,
            'date' => $ledgerDate->format('Y-m-d'),
            'due_date' => null,
            'reference' => $orNumber,
            'reading' => 0,
            'volume' => 0,
            'billamount' => 0,
            'penalty' => round((float) ($validated['penalty'] ?? 0), 2),
            'others' => 0,
            'debit' => 0,
            'credit' => $billPaymentAmount,
            'balance' => round($newBalance, 2),
            'username' => $this->getFormattedUserName(),
            'txtime' => $ledgerDate->format('Y-m-d H:i:s'),
            'paid_at' => $ledgerDate,
        ];

        $this->upsertMainPaymentLedgerRow($ledgerPayload, $consumerPayment, $readingId, $isUpdate);

        $scDiscount = round($validated['senior_citizen_discount'] ?? 0, 2);
        if ($scDiscount <= 0) {
            return;
        }

        $this->upsertSeniorCitizenLedgerRow(
            $consumerId,
            $consumerPayment,
            $downloaded,
            $readingId,
            $orNumber,
            $ledgerDate,
            $scDiscount,
            $newBalance,
            $isUpdate
        );
    }

    private function resolveBalanceForPaymentUpdate(
        int $consumerId,
        ConsumerPayment $consumerPayment,
        float $billPaymentAmount,
        float $defaultBalance
    ): float {
        $existingPaymentRow = ConsumerLedger::query()->where(mr_col('consumer_payment_id'), $consumerPayment->id)
            ->where(mr_col('trans'), 'PAYMENT')
            ->where(function ($q) {
                $q->whereNull(mr_col('reference'))->orWhere(mr_col('reference'), 'not like', '%-SC');
            })
            ->first();

        if (!$existingPaymentRow) {
            $existingPaymentRow = ConsumerLedger::query()->where(mr_col('consumer_payment_id'), $consumerPayment->id)
                ->where(mr_col('trans'), 'PAYMENT')
                ->first();
        }

        if (!$existingPaymentRow) {
            return $defaultBalance;
        }

        $previousEntry = ConsumerLedger::query()->where(mr_col('consumer_zone_id'), $consumerId)
            ->whereNotNull(mr_col('balance'))
            ->where(function ($q) use ($existingPaymentRow) {
                $q->where(mr_col('date'), '<', $existingPaymentRow->date)
                    ->orWhere(function ($q2) use ($existingPaymentRow) {
                        $q2->where(mr_col('date'), $existingPaymentRow->date)
                            ->where(mr_col('id'), '<', $existingPaymentRow->id);
                    });
            })
            ->orderBy(mr_col('date'), 'desc')
            ->orderBy(mr_col('id'), 'desc')
            ->first();

        $balanceBeforeThisPayment = $previousEntry ? (float) ($previousEntry->balance ?? 0) : 0;

        return round($balanceBeforeThisPayment - $billPaymentAmount, 2);
    }

    /**
     * @param  array<string, mixed>  $ledgerPayload
     */
    private function upsertMainPaymentLedgerRow(
        array $ledgerPayload,
        ConsumerPayment $consumerPayment,
        ?int $readingId,
        bool $isUpdate
    ): void {
        if ($readingId !== null) {
            if ($isUpdate) {
                $ledgerRow = ConsumerLedger::query()->where(mr_col('consumer_payment_id'), $consumerPayment->id)
                    ->where(mr_col('trans'), 'PAYMENT')
                    ->first();
                if ($ledgerRow) {
                    $ledgerRow->update($ledgerPayload);
                } else {
                    ConsumerLedger::create(array_merge($ledgerPayload, [
                        'trans' => 'PAYMENT',
                        'downloaded_reading_id' => $readingId,
                    ]));
                }

                return;
            }

            ConsumerLedger::create(array_merge($ledgerPayload, [
                'trans' => 'PAYMENT',
                'downloaded_reading_id' => $readingId,
            ]));

            return;
        }

        if ($isUpdate) {
            $ledgerRow = ConsumerLedger::query()->where(mr_col('consumer_payment_id'), $consumerPayment->id)
                ->where(mr_col('trans'), 'PAYMENT')
                ->first();
            if ($ledgerRow) {
                $ledgerRow->update($ledgerPayload);
            } else {
                ConsumerLedger::create(array_merge($ledgerPayload, [
                    'trans' => 'PAYMENT',
                    'downloaded_reading_id' => null,
                ]));
            }

            return;
        }

        ConsumerLedger::create(array_merge($ledgerPayload, [
            'trans' => 'PAYMENT',
            'downloaded_reading_id' => null,
        ]));
    }

    private function upsertSeniorCitizenLedgerRow(
        int $consumerId,
        ConsumerPayment $consumerPayment,
        ?DownloadedReading $downloaded,
        ?int $readingId,
        string $orNumber,
        Carbon $ledgerDate,
        float $scDiscount,
        float $newBalance,
        bool $isUpdate
    ): void {
        $scPayload = [
            'consumer_payment_id' => $consumerPayment->id,
            'schedule_id' => $downloaded?->schedule_id,
            'date' => $ledgerDate->format('Y-m-d'),
            'due_date' => null,
            'reading' => 0,
            'volume' => 0,
            'billamount' => 0,
            'penalty' => 0,
            'others' => 0,
            'debit' => 0,
            'credit' => $scDiscount,
            'balance' => round($newBalance - $scDiscount, 2),
            'username' => $this->getFormattedUserName(),
            'txtime' => $ledgerDate->format('Y-m-d H:i:s'),
            'paid_at' => $ledgerDate,
        ];

        if ($isUpdate && $downloaded && $readingId !== null) {
            ConsumerLedger::updateOrCreate(
                [
                    'consumer_zone_id' => $consumerId,
                    'trans' => 'PAYMENT',
                    'downloaded_reading_id' => $readingId,
                    'reference' => $orNumber . '-SC',
                ],
                array_merge($scPayload, [
                    'schedule_id' => $downloaded->schedule_id,
                ])
            );

            return;
        }

        ConsumerLedger::create(array_merge($scPayload, [
            'consumer_zone_id' => $consumerId,
            'downloaded_reading_id' => $downloaded?->id,
            'trans' => 'PAYMENT',
            'reference' => $orNumber . '-SC',
        ]));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateSundryCharges(array $validated, int $consumerId): void
    {
        foreach ($validated['sundries'] ?? [] as $sundry) {
            $sundryAmount = round((float) ($sundry['amount'] ?? 0), 2);
            if ($sundryAmount <= 0) {
                continue;
            }

            $chargeId = (int) ($sundry['lro_ledger_id'] ?? 0);
            if ($chargeId <= 0) {
                throw new \InvalidArgumentException('Each sundry payment must reference an unpaid LRO charge (lro_ledger_id). Reload the account or select from unpaid LRO charges.');
            }

            $charge = LROLedger::query()
                ->where('id', $chargeId)
                ->where('consumer_zone_id', $consumerId)
                ->first();

            if (!$charge) {
                throw new \InvalidArgumentException('Sundry LRO charge not found for this account.');
            }

            if (strtoupper(trim((string) ($charge->ledger ?? ''))) !== 'LRO') {
                throw new \InvalidArgumentException('Sundry charge must belong to the LRO ledger.');
            }

            if (strtoupper(trim((string) ($charge->type ?? ''))) !== 'DM') {
                throw new \InvalidArgumentException('Sundry charge must be an LRO debit memo (DM).');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createSundryCredits(array $validated, int $consumerId, Carbon $paidAt): void
    {
        $orNumber = trim((string) ($validated['official_receipt_number'] ?? ''));

        foreach ($validated['sundries'] as $sundry) {
            $sundryAmount = round((float) ($sundry['amount'] ?? 0), 2);
            if ($sundryAmount <= 0) {
                continue;
            }

            $chargeId = (int) ($sundry['lro_ledger_id'] ?? 0);
            if ($chargeId <= 0) {
                continue;
            }

            $charge = LROLedger::query()
                ->where('id', $chargeId)
                ->where('consumer_zone_id', $consumerId)
                ->first();

            if (!$charge || strtoupper(trim((string) ($charge->ledger ?? ''))) !== 'LRO') {
                continue;
            }

            LROLedger::create(LROLedger::filterTableAttributes([
                'consumer_zone_id' => $consumerId,
                'type' => 'CM',
                'date' => $paidAt->format('Y-m-d'),
                'bam_no' => $charge->bam_no,
                'amount' => $sundryAmount,
                'ledger' => 'LRO',
                'acct_code' => $charge->acct_code,
                'remarks' => SundryLedgerRemarks::paymentRemark($orNumber, $chargeId),
                'status' => 'Posted',
                'paid_at' => $paidAt,
            ]));
        }
    }

    private function findConsumerByAccountNumber(?string $accountNumber): ?ConsumerZone
    {
        if (empty($accountNumber)) {
            return null;
        }

        $consumer = ConsumerZone::query()->where(mr_col('account_no'), $accountNumber)->first();
        if ($consumer) {
            return $consumer;
        }

        $normalized = str_replace('-', '', $accountNumber);

        return ConsumerZone::whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])->first();
    }

    private function getBillMonthFromRow(ConsumerLedger $row): ?Carbon
    {
        if ($row->schedule && $row->schedule->bill_month) {
            return Carbon::parse($row->schedule->bill_month);
        }
        if (!empty($row->due_date)) {
            return Carbon::parse($row->due_date);
        }
        if (!empty($row->date)) {
            return Carbon::parse($row->date);
        }

        return null;
    }

    private function getFormattedUserName(): string
    {
        $user = Auth::user();

        if (!$user) {
            return 'SYSTEM';
        }

        $formattedName = strtoupper($user->last_name ?? '') . ', ' . strtoupper($user->first_name ?? '');

        if (!empty($user->middle_name)) {
            $formattedName .= ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.';
        }

        if (!empty($user->extension)) {
            $formattedName .= ' ' . strtoupper($user->extension);
        }

        return trim($formattedName) ?: ($user->name ?? 'UNKNOWN');
    }
}
