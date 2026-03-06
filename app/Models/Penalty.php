<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'consumer_zone_id',
        'schedule_id',
        'downloaded_reading_id',
        'date',
        'due_date',
        'reference',
        'bill_amount',
        'penalty_amount',
        'balance',
        'username',
        'txtime',
        'paid_at',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'bill_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'txtime' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the consumer zone that owns this penalty
     */
    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZoneOne::class, 'consumer_zone_id');
    }

    /**
     * Get the schedule that this penalty is based on
     */
    public function schedule()
    {
        return $this->belongsTo(MeterReadingSchedule::class, 'schedule_id');
    }

    /**
     * Get the downloaded reading related to this penalty
     */
    public function downloadedReading()
    {
        return $this->belongsTo(DownloadedReading::class, 'downloaded_reading_id');
    }

    /**
     * Get the consumer ledger entries associated with this penalty
     */
    public function consumerLedgers()
    {
        return $this->hasMany(ConsumerLedger::class, 'penalty_id');
    }

    /**
     * Boot the model and set up cascade delete
     */
    protected static function boot()
    {
        parent::boot();

        // When a penalty is deleted, also delete related consumer ledger entries
        static::deleting(function ($penalty) {
            $penalty->consumerLedgers()->delete();
        });
    }
}
