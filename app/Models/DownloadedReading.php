<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadedReading extends Model
{
    protected $fillable = [
        'schedule_id',
        'reader_id',
        'meter_reader_name',
        'account_number',
        'account_name',
        'zone',
        'previous_reading',
        'current_reading',
        'consumption',
        'current_bill',
        'reading_date',
        'status',
        'reader_notes',
        'completed_at',
        'payment_method',
        'payment_amount',
        'amount_tendered',
        'change_amount',
        'payment_reference',
        'payment_remarks',
        'paid_at',
        'official_receipt_number',
        'prepared_by',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'completed_at' => 'datetime',
        'current_bill' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Relationship: Downloaded reading belongs to a schedule
     */
    public function schedule()
    {
        return $this->belongsTo(MeterReadingSchedule::class, 'schedule_id');
    }

    /**
     * Relationship: Downloaded reading belongs to a reader
     */
    public function reader()
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    /**
     * Get the ledger entries associated with this downloaded reading
     */
    public function ledgerEntries()
    {
        return $this->hasMany(ConsumerLedger::class, 'downloaded_reading_id');
    }
}
