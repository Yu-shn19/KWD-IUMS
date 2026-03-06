<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ConsumerPayment;

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
        'debit',
        'credit',
        'balance',
        'username',
        'txtime',
        'cl_ctrl'
    ];

    protected $casts = [
        'due_date' => 'date',
        'txtime' => 'datetime',
    ];

    /**
     * Get the consumer zone that owns this ledger entry.
     */
    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZoneOne::class, 'consumer_zone_id');
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
     * Get related collection entries based on account_no and date matching
     */
    public function collections()
    {
        try {
            if (!$this->consumerZone || !$this->txtime) {
                return collect([]);
            }

            // Check if Collection class exists
            if (!class_exists(\App\Models\Collection::class)) {
                return collect([]);
            }

            return Collection::where('account_no', $this->consumerZone->account_no)
                ->whereDate('coll_date', $this->txtime->format('Y-m-d'))
                ->get();
        } catch (\Throwable $e) {
            return collect([]);
        }
    }

    /**
     * Get the collection entry that matches this ledger entry's date
     */
    public function matchingCollection()
    {
        try {
            if (!$this->consumerZone || !$this->txtime) {
                return null;
            }

            // Check if Collection class exists
            if (!class_exists(\App\Models\Collection::class)) {
                return null;
            }

            return Collection::where('account_no', $this->consumerZone->account_no)
                ->whereDate('coll_date', $this->txtime->format('Y-m-d'))
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
