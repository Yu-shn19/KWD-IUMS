<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterReadingSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_zone_id',
        'assigned_reader_id',
        'bill_month',
        'bill_date',
        'due_date',
        'disconnection_date',
        'previous_reading_date',
        'previous_reading',
        'previous_reading_override',
        'previous_reading_override_at',
        'previous_reading_override_by',
        'current_reading',
        'reading_date',
        'consumption',
        'current_bill',
        'arrears',
        'total_amount',
        'status',
        'reader_notes',
        'remarks',
        'sedr_number',
        'prepared_by',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'bill_month' => 'date',
        'bill_date' => 'date',
        'due_date' => 'date',
        'disconnection_date' => 'date',
        'previous_reading_date' => 'date',
        'previous_reading_override_at' => 'datetime',
        'reading_date' => 'date',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_bill' => 'decimal:2',
        'arrears' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = [
        'account_number',
        'account_name',
        'address',
        'category',
        'meter_number',
        'zone',
    ];

    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZoneOne::class, 'consumer_zone_id');
    }

    /** @deprecated Use consumerZone() */
    public function consumer()
    {
        return $this->consumerZone();
    }

    public function assignedReader()
    {
        return $this->belongsTo(User::class, 'assigned_reader_id');
    }

    public function downloadedReading()
    {
        return $this->hasOne(DownloadedReading::class, 'schedule_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(ConsumerLedger::class, 'schedule_id');
    }

    public function getAccountNumberAttribute(): ?string
    {
        return $this->relationLoaded('consumerZone')
            ? ($this->consumerZone->account_no ?? null)
            : ($this->consumerZone()->value('account_no'));
    }

    public function getAccountNameAttribute(): ?string
    {
        return $this->relationLoaded('consumerZone')
            ? ($this->consumerZone->account_name ?? null)
            : ($this->consumerZone()->value('account_name'));
    }

    public function getAddressAttribute(): ?string
    {
        return $this->relationLoaded('consumerZone')
            ? ($this->consumerZone->address ?? null)
            : ($this->consumerZone()->value('address'));
    }

    public function getCategoryAttribute(): ?string
    {
        return $this->relationLoaded('consumerZone')
            ? ($this->consumerZone->category_code ?? null)
            : ($this->consumerZone()->value('category_code'));
    }

    public function getMeterNumberAttribute(): ?string
    {
        return $this->relationLoaded('consumerZone')
            ? ($this->consumerZone->meter_number ?? null)
            : ($this->consumerZone()->value('meter_number'));
    }

    public function getZoneAttribute(): ?string
    {
        return $this->relationLoaded('consumerZone')
            ? ($this->consumerZone->zone_code ?? null)
            : ($this->consumerZone()->value('zone_code'));
    }

    public function scopeForZoneCode(Builder $query, string $zone): Builder
    {
        return $query->whereHas('consumerZone', function (Builder $q) use ($zone) {
            $q->where('zone_code', $zone)
                ->orWhereRaw('LPAD(TRIM(COALESCE(zone_code, "")), 3, "0") = ?', [$zone])
                ->orWhereRaw('TRIM(LEADING "0" FROM zone_code) = TRIM(LEADING "0" FROM ?)', [$zone]);
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

    /**
     * MySQL/MariaDB: ORDER BY numeric segment after last "-" in account_no.
     */
    public static function accountTailNumericOrderExpression(string $accountColumn = 'cz.account_no'): string
    {
        return "CAST(SUBSTRING_INDEX(TRIM(COALESCE({$accountColumn}, '')), '-', -1) AS UNSIGNED)";
    }

    public function scopeOrderByAccountNumberTail(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();
        $table = $query->getModel()->getTable();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->joinConsumerZone();

            return $query
                ->orderByRaw(static::accountTailNumericOrderExpression())
                ->orderBy('cz.account_no');
        }

        return $query->orderBy("{$table}.consumer_zone_id");
    }

    private function queryHasJoin(Builder $query, string $alias): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if (isset($join->table) && str_contains((string) $join->table, $alias)) {
                return true;
            }
        }

        return false;
    }
}
