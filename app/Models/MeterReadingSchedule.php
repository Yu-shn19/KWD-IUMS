<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterReadingSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_id',
        'assigned_reader_id',
        'zone',
        'account_number',
        'account_name',
        'address',
        'category',
        'meter_number',
        'bill_month',
        'bill_date',
        'due_date',
        'disconnection_date',
        'previous_reading_date',
        'previous_reading',
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
        'reading_date' => 'date',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_bill' => 'decimal:2',
        'arrears' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the consumer that owns the meter reading schedule
     */
    public function consumer()
    {
        return $this->belongsTo(Consumer::class);
    }

    /**
     * Get the reader assigned to this meter reading
     */
    public function assignedReader()
    {
        return $this->belongsTo(User::class, 'assigned_reader_id');
    }

    /**
     * Get the downloaded reading associated with this schedule
     */
    public function downloadedReading()
    {
        return $this->hasOne(DownloadedReading::class, 'schedule_id');
    }

    /**
     * Get the ledger entries associated with this schedule
     */
    public function ledgerEntries()
    {
        return $this->hasMany(ConsumerLedger::class, 'schedule_id');
    }

    /**
     * MySQL/MariaDB: ORDER BY numeric segment after last "-" in account_number (e.g. 081-32-625 → 625).
     * Pass table name when using DB::table() or joins (e.g. "meter_reading_schedules").
     */
    public static function accountTailNumericOrderExpression(?string $table = null): string
    {
        $col = $table
            ? "`{$table}`.`account_number`"
            : '`account_number`';

        return "CAST(SUBSTRING_INDEX(TRIM(COALESCE({$col}, '')), '-', -1) AS UNSIGNED)";
    }

    /**
     * Apply account-tail ordering (matches Meter Reading Preparation UI sort).
     */
    public function scopeOrderByAccountNumberTail(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();
        $table = $query->getModel()->getTable();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $query
                ->orderByRaw(static::accountTailNumericOrderExpression($table))
                ->orderBy("{$table}.account_number");
        }

        return $query->orderBy("{$table}.account_number");
    }
}
