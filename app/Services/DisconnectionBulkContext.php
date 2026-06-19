<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DisconnectionBulkContext
{
    public function __construct(
        public Collection $consumers,
        public Collection $latestBalances,
        public Collection $allSchedules,
        public Collection $latestScheduleReadingsByAccount,
        public Collection $latestCurrentBillsByAccount,
        public Collection $allBillings,
        public Collection $allPayments,
        public Collection $penaltyLedgersByConsumer,
        public Collection $arAgingBucketsByConsumer,
    ) {}
}
