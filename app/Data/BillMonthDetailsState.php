<?php

namespace App\Data;

use App\Models\ConsumerPayment;
use App\Models\ConsumerZone;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Mutable working state for bill-month details computation.
 */
class BillMonthDetailsState
{
    public string $accountNumber = '';

    public string $normalizedAccount = '';

    public string $normalizedAccountNoLeadingZero = '';

    public ?string $fromDateInput = null;

    public ?string $toDateInput = null;

    public bool $dateRangeMode = false;

    public bool $methodIsA = false;

    public ?string $billMonthFromKey = null;

    public ?string $billMonthToKey = null;

    public ConsumerZone $consumer;

    public float $currentBalance = 0.0;

    /** @var Collection<int, object> */
    public Collection $ledgerEntries;

    /** @var list<object> */
    public array $matchingEntries = [];

    public Carbon $fromMonthDate;

    public Carbon $toMonthDate;

    public float $currentBill = 0.0;

    public float $penaltyAmount = 0.0;

    public float $maintenance = 0.0;

    public float $others = 0.0;

    public float $arrears = 0.0;

    public float $arrearsCy = 0.0;

    public float $arrearsPy = 0.0;

    public float $seniorCitizenDiscount = 0.0;

    public float $principalFromBilling = 0.0;

    public ?Carbon $dueDateForOverdue = null;

    public bool $isRange = false;

    /** @var list<array{billamount: float, due_date: ?Carbon}> */
    public array $billingEntriesWithDueDate = [];

    public bool $noBillingInViewedMonth = false;

    public bool $usePyFormula = true;

    public bool $hasBillingEntriesInRange = true;

    public string $paymentStatus = 'unpaid';

    /** @var \Illuminate\Support\Collection<int, int> */
    public Collection $schedulesInRange;

    public bool $isFirstMonthMethodA = false;

    public bool $isNoBillingInMonth = false;

    /** @var array<string, mixed>|null */
    public ?array $currentBillEntry = null;

    public string $orNumberInput = '';

    public ?ConsumerPayment $orPayment = null;

    public function __construct()
    {
        $this->ledgerEntries = collect();
        $this->schedulesInRange = collect();
        $this->fromMonthDate = Carbon::today();
        $this->toMonthDate = Carbon::today();
    }
}
