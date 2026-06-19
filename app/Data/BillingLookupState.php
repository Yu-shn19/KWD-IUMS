<?php

namespace App\Data;

use App\Models\ConsumerPayment;
use Carbon\Carbon;

/**
 * Mutable lookup context passed between BillingLookupService resolution steps.
 */
class BillingLookupState
{
    public function __construct(
        public ?string $accountNumber = null,
        public ?string $accountName = null,
        public ?string $normalizedAccount = null,
        public ?Carbon $billMonthDate = null,
        public string $orNumberInput = '',
        public ?BillingReadingRecord $reading = null,
        public ?ConsumerPayment $orLookupPayment = null,
        public ?string $lookupSuccessMessage = null,
    ) {
    }
}
