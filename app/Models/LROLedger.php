<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for Billing Adjustment Memo LRO ledger entries.
 * Uses the singular legacy table name `lro_ledger`.
 */
class LROLedger extends Model
{
    protected $table = 'lro_ledger';

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
        'correct_reading',
        'paid_at',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'correct_reading' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'account_no',
        'account_name',
    ];

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

    public function getAccountNameAttribute(): ?string
    {
        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->account_name;
        }

        if ($this->consumer_zone_id) {
            return $this->consumerZone?->account_name;
        }

        return null;
    }

    /** @deprecated Use account_no via consumer_zone_id */
    public function getAccountAttribute(): ?string
    {
        return $this->account_no;
    }

    /** @deprecated Use account_name via consumer_zone_id */
    public function getNameAttribute(): ?string
    {
        return $this->account_name;
    }

    /** @deprecated Use ledger column */
    public function getArTypeAttribute(): ?string
    {
        return $this->attributes['ledger'] ?? null;
    }

    /** @deprecated Use bam_no */
    public function getReferenceAttribute(): ?string
    {
        return $this->bam_no;
    }

    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZone::class, 'consumer_zone_id');
    }

    public function scopeForConsumerZone($query, ?int $consumerZoneId)
    {
        if ($consumerZoneId) {
            return $query->where('consumer_zone_id', $consumerZoneId);
        }

        return $query->whereRaw('0 = 1');
    }

    /**
     * Keep only attributes that exist on lro_ledger (account/name live on consumer_zone).
     */
    public static function filterTableAttributes(array $data): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('lro_ledger')) {
            return $data;
        }

        if (isset($data['ar_type']) && !isset($data['ledger']) && \Illuminate\Support\Facades\Schema::hasColumn('lro_ledger', 'ledger')) {
            $data['ledger'] = $data['ar_type'];
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('lro_ledger', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
