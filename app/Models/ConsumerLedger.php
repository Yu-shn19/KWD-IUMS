<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Models\ConsumerPayment;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class ConsumerLedger extends Model
{
    protected $fillable = [
        'consumer_zone_id',
        'consumer_payment_id',
        'schedule_id',
        'downloaded_reading_id',
        'penalty_id',
        'billing_adjustment_id',
        'trans',
        'date',
        'due_date',
        'reference',
        'reading',
        'volume',
        'billamount',
        'penalty',
        'others',
        'prio_years',
        'current_arrears',
        'debit',
        'credit',
        'balance',
        'username',
        'txtime',
        'cl_ctrl'
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'txtime' => 'datetime',
        'paid_at' => 'datetime',
        'prio_years' => 'decimal:2',
        'current_arrears' => 'decimal:2',
    ];

    /**
     * Prior-years charge stored on this row (0 when the column is absent).
     */
    public function prioYearsAmount(): float
    {
        static $hasColumn = null;
        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn($this->getTable(), 'prio_years');
        }
        if (! $hasColumn) {
            return 0.0;
        }

        return round((float) ($this->prio_years ?? 0), 2);
    }

    /**
     * Current-year arrears stored on this row (0 when the column is absent).
     * This is not Prior Years — PY lives in prio_years.
     */
    public function currentArrearsAmount(): float
    {
        static $hasColumn = null;
        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn($this->getTable(), 'current_arrears');
        }
        if (! $hasColumn) {
            return 0.0;
        }

        return round((float) ($this->current_arrears ?? 0), 2);
    }

    /**
     * Get the consumer zone that owns this ledger entry.
     */
    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZone::class, 'consumer_zone_id');
    }

    /**
     * Get the meter reading schedule associated with this ledger entry.
     */
    public function schedule()
    {
        return $this->belongsTo(MeterReadingSchedule::class, 'schedule_id');
    }

    /**
     * Get the downloaded reading associated with this ledger entry.
     */
    public function downloadedReading()
    {
        return $this->belongsTo(DownloadedReading::class, 'downloaded_reading_id');
    }

    /**
     * Get the consumer payment associated with this ledger entry (for PAYMENT transactions).
     */
    public function consumerPayment()
    {
        return $this->belongsTo(ConsumerPayment::class, 'consumer_payment_id');
    }

    /**
     * Get the penalty associated with this ledger entry (for PENALTY transactions).
     */
    public function penaltyRecord()
    {
        return $this->belongsTo(Penalty::class, 'penalty_id');
    }

    /**
     * Get the billing adjustment associated with this ledger entry (for TRANS transactions).
     */
    public function billingAdjustment()
    {
        return $this->belongsTo(BillingAdjustment::class, 'billing_adjustment_id');
    }

    /**
     * Get the account number from the relationship.
     * Accessor to get account_no from consumer_zone relationship
     */
    public function getAccountNoAttribute()
    {
        // Always get account_no from the relationship
        return $this->consumerZone ? $this->consumerZone->account_no : null;
    }

    /**
     * Get related consumer payment entries based on account and paid_at date.
     */
    public function collections()
    {
        try {
            if (!$this->consumer_zone_id || !$this->txtime) {
                return collect([]);
            }

            return ConsumerPayment::query()
                ->where(mr_col('consumer_zone_id'), $this->consumer_zone_id)
                ->whereDate(mr_col('paid_at'), $this->txtime->format('Y-m-d'))
                ->get();
        } catch (\Throwable $e) {
            return collect([]);
        }
    }

    /**
     * Get the consumer payment entry that matches this ledger entry's date.
     */
    public function matchingCollection()
    {
        try {
            if (!$this->consumer_zone_id || !$this->txtime) {
                return null;
            }

            return ConsumerPayment::query()
                ->where(mr_col('consumer_zone_id'), $this->consumer_zone_id)
                ->whereDate(mr_col('paid_at'), $this->txtime->format('Y-m-d'))
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
