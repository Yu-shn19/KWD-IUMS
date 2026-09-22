<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\ConsumerPayment;

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
     * Current Penalty from the penalties table (same source as Penalty Report).
     * Payments dated before a surcharge do not reduce that surcharge.
     */
    public static function unpaidAmountForConsumer(int $consumerZoneId): float
    {
        if ($consumerZoneId <= 0 || ! Schema::hasTable('penalties')) {
            return 0.0;
        }

        $query = static::query()->where('consumer_zone_id', $consumerZoneId);
        if (Schema::hasColumn('penalties', 'penalty_amount')) {
            $query->where('penalty_amount', '>', 0);
        }

        $rows = $query->orderBy('date')->orderBy('id')->get();
        $total = 0.0;
        $earliest = null;
        foreach ($rows as $row) {
            $amt = (float) ($row->penalty_amount ?? 0);
            if ($amt <= 0.009) {
                continue;
            }
            $total += $amt;
            try {
                $raw = $row->date ?? $row->due_date ?? null;
                if ($raw) {
                    $d = Carbon::parse($raw)->startOfDay();
                    if ($earliest === null || $d->lt($earliest)) {
                        $earliest = $d;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if ($total <= 0.009) {
            return 0.0;
        }

        $paidQuery = ConsumerPayment::forConsumerZone($consumerZoneId)
            ->whereNotNull('paid_at');
        if ($earliest) {
            $paidQuery->whereDate('paid_at', '>=', $earliest->format('Y-m-d'));
        }
        $paid = (float) $paidQuery->sum('current_penalty');

        return round(max(0.0, $total - $paid), 2);
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
