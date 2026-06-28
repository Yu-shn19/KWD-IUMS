<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Schema;

class Penalty extends Model
{
    protected $fillable = [
        'consumer_zone_id',
        'schedule_id',
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
        return $this->belongsTo(ConsumerZone::class, 'consumer_zone_id');
    }

    /**
     * Get the schedule that this penalty is based on
     */
    public function schedule()
    {
        return $this->belongsTo(MeterReadingSchedule::class, 'schedule_id');
    }

    /**
     * Get the downloaded reading related to this penalty (when column exists).
     */
    public function downloadedReading()
    {
        if (!Schema::hasColumn($this->getTable(), 'downloaded_reading_id')) {
            return $this->belongsTo(DownloadedReading::class, 'downloaded_reading_id')->whereRaw('0 = 1');
        }

        return $this->belongsTo(DownloadedReading::class, 'downloaded_reading_id');
    }

    /**
     * Keep only attributes that exist on penalties.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filterTableAttributes(array $data): array
    {
        if (!Schema::hasTable('penalties')) {
            return $data;
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('penalties', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
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
