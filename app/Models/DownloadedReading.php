<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DownloadedReading extends Model
{
    protected $fillable = [
        'consumer_zone_id',
        'schedule_id',
        'reader_id',
        'previous_reading',
        'current_reading',
        'consumption',
        'current_bill',
        'reading_date',
        'status',
        'reader_notes',
        'prepared_by',
        'paid_at',
        'completed_at',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'current_bill' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'account_number',
        'account_name',
        'zone',
    ];

    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZone::class, 'consumer_zone_id');
    }

    public function schedule()
    {
        return $this->belongsTo(MeterReadingSchedule::class, 'schedule_id');
    }

    public function reader()
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(ConsumerLedger::class, 'downloaded_reading_id');
    }

    public function getAccountNumberAttribute(): ?string
    {
        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->account_no;
        }
        if ($this->consumer_zone_id) {
            return $this->consumerZone()->value('account_no');
        }
        if ($this->relationLoaded('schedule') && $this->schedule) {
            return $this->schedule->account_number;
        }

        return null;
    }

    public function getAccountNameAttribute(): ?string
    {
        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->account_name;
        }
        if ($this->consumer_zone_id) {
            return $this->consumerZone()->value('account_name');
        }
        if ($this->relationLoaded('schedule') && $this->schedule) {
            return $this->schedule->account_name;
        }

        return null;
    }

    public function getZoneAttribute(): ?string
    {
        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->zone_code;
        }
        if ($this->consumer_zone_id) {
            return $this->consumerZone()->value('zone_code');
        }
        if ($this->relationLoaded('schedule') && $this->schedule) {
            return $this->schedule->zone;
        }

        return null;
    }

    public function scopeForConsumerZone(Builder $query, int $consumerZoneId): Builder
    {
        return $query->where(function (Builder $q) use ($consumerZoneId) {
            $q->where('consumer_zone_id', $consumerZoneId)
                ->orWhereHas('schedule', function (Builder $sq) use ($consumerZoneId) {
                    $sq->where('consumer_zone_id', $consumerZoneId);
                });
        });
    }

    public function scopeForAccountNo(Builder $query, string $accountNo): Builder
    {
        $trimmed = trim($accountNo);
        $normalized = str_replace('-', '', $trimmed);

        return $query->where(function (Builder $q) use ($trimmed, $normalized) {
            $q->whereHas('consumerZone', function (Builder $cz) use ($trimmed, $normalized) {
                $cz->where('account_no', $trimmed)
                    ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])
                    ->orWhereRaw('UPPER(TRIM(account_no)) = ?', [strtoupper($trimmed)]);
            })->orWhereHas('schedule.consumerZone', function (Builder $cz) use ($trimmed, $normalized) {
                $cz->where('account_no', $trimmed)
                    ->orWhereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])
                    ->orWhereRaw('UPPER(TRIM(account_no)) = ?', [strtoupper($trimmed)]);
            });
        });
    }

    public function scopeJoinConsumerZone(Builder $query, string $alias = 'cz'): Builder
    {
        $table = $query->getModel()->getTable();

        if (!$this->queryHasJoin($query, $alias)) {
            $query->leftJoin("consumer_zone as {$alias}", "{$table}.consumer_zone_id", '=', "{$alias}.id");
        }

        return $query;
    }

    private function queryHasJoin(Builder $query, string $alias): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if (isset($join->table) && str_contains((string) $join->table, " as {$alias}")) {
                return true;
            }
            if (isset($join->table) && (string) $join->table === $alias) {
                return true;
            }
        }

        return false;
    }
}
