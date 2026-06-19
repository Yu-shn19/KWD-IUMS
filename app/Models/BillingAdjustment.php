<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAdjustment extends Model
{
    protected $fillable = [
        'consumer_zone_id',
        'type',
        'ledger',
        'date',
        'bam_no',
        'amount',
        'acct_code',
        'remarks',
        'status',
        'connect_reading',
        'username',
    ];

    protected $appends = [
        'account_no',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Account number is stored on consumer_zone; resolve via consumer_zone_id.
     */
    public function getAccountNoAttribute(): ?string
    {
        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->account_no;
        }

        if ($this->consumer_zone_id) {
            return $this->consumerZone?->account_no;
        }

        return null;
    }

    /**
     * Get the consumer zone associated with this billing adjustment
     */
    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZone::class, 'consumer_zone_id');
    }

    /**
     * Get the consumer ledger entries associated with this billing adjustment
     */
    public function consumerLedgers()
    {
        return $this->hasMany(ConsumerLedger::class, 'billing_adjustment_id');
    }

    /**
     * Boot the model and set up cascade delete
     */
    protected static function boot()
    {
        parent::boot();

        // When a billing adjustment is deleted, also delete related consumer ledger entries
        static::deleting(function ($billingAdjustment) {
            $billingAdjustment->consumerLedgers()->delete();
        });
    }
}
