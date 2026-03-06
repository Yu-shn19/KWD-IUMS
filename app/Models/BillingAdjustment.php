<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAdjustment extends Model
{
    protected $fillable = [
        'type',
        'type_ar',
        'date',
        'bam_no',
        'account_no',
        'consumer_zone_id',
        'amount',
        'acct_code',
        'reference',
        'current_bill',
        'penalty',
        'arrears',
        'sc_discount',
        'loans',
        'others',
        'remarks',
        'status',
        'connect_reading',
        'username',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'current_bill' => 'decimal:2',
        'penalty' => 'decimal:2',
        'arrears' => 'decimal:2',
        'sc_discount' => 'decimal:2',
        'loans' => 'decimal:2',
        'others' => 'decimal:2',
    ];

    /**
     * Get the consumer zone associated with this billing adjustment
     */
    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZoneOne::class, 'consumer_zone_id');
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
