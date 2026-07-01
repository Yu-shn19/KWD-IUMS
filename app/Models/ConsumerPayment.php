<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ConsumerLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class ConsumerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reading_id',
        'consumer_zone_id',
        'lro_ledger_id',
        'payment_method',
        'payment_amount',
        'senior_citizen_discount',
        'current_bill',
        'penalty',
        'meter_maintenance',
        'arrears_cy',
        'arrears_py',
        'advances',
        'others',
        'amount_tendered',
        'change_amount',
        'or_number',
        'paid_at',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function scopeImportable($query)
    {
        return $query
            ->whereNotNull(mr_col('consumer_zone_id'))
            ->whereNotNull(mr_col('paid_at'))
            ->where(mr_col('payment_amount'), '>', 0);
    }

    public function scopeForAccountNo($query, string $accountNo)
    {
        return $query->whereHas('consumerZone', function ($q) use ($accountNo) {
            $q->where(mr_col('account_no'), $accountNo);
        });
    }

    /** Legacy alias: collection import/sync used coll_date (date portion of paid_at). */
    public function getCollDateAttribute(): ?\Carbon\Carbon
    {
        return $this->paid_at ? \Carbon\Carbon::parse($this->paid_at)->copy()->startOfDay() : null;
    }

    /** Legacy alias: collection import/sync used coll_time (time portion of paid_at). */
    public function getCollTimeAttribute(): ?string
    {
        return $this->paid_at ? \Carbon\Carbon::parse($this->paid_at)->format('H:i:s') : null;
    }

    /** Legacy alias for payment_amount. */
    public function getPayAmountAttribute()
    {
        return $this->payment_amount;
    }

    /** Legacy alias for senior_citizen_discount. */
    public function getScDiscountAttribute()
    {
        return $this->senior_citizen_discount;
    }

    /** Legacy alias for created_by. */
    public function getUsernameAttribute(): ?string
    {
        return $this->created_by;
    }

    /** Account number via consumer_zone relationship. */
    public function getAccountNoAttribute(): ?string
    {
        return $this->consumerZone?->account_no;
    }

    /** Legacy alias for account_no. */
    public function getAccountNumberAttribute(): ?string
    {
        return $this->account_no;
    }

    /** Account name via consumer_zone, or lro_ledger for Others payments. */
    public function getAccountNameAttribute(): ?string
    {
        if ($this->lro_ledger_id) {
            $ledger = $this->relationLoaded('lroLedger')
                ? $this->lroLedger
                : $this->lroLedger()->first();

            return $ledger?->account_name;
        }

        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->account_name;
        }

        if ($this->consumer_zone_id) {
            return $this->consumerZone()->value('account_name');
        }

        return null;
    }

    public function lroLedger()
    {
        return $this->belongsTo(LROLedger::class, 'lro_ledger_id');
    }

    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZone::class, 'consumer_zone_id');
    }

    /** @deprecated Use consumer_zone_id */
    public function getConsumerIdAttribute(): ?int
    {
        if (array_key_exists('consumer_zone_id', $this->attributes)) {
            return $this->attributes['consumer_zone_id'] !== null ? (int) $this->attributes['consumer_zone_id'] : null;
        }

        return isset($this->attributes['consumer_id']) ? (int) $this->attributes['consumer_id'] : null;
    }

    public function scopeForConsumerZone($query, ?int $consumerZoneId)
    {
        if (!$consumerZoneId) {
            return $query->whereRaw('0 = 1');
        }

        if (Schema::hasColumn($this->getTable(), 'consumer_zone_id')) {
            return $query->where(mr_col('consumer_zone_id'), $consumerZoneId);
        }

        if (Schema::hasColumn($this->getTable(), 'consumer_id')) {
            return $query->where(mr_col('consumer_id'), $consumerZoneId);
        }

        return $query->whereRaw('0 = 1');
    }

    public static function consumerZoneIdColumn(): string
    {
        if (Schema::hasTable('consumer_payments') && Schema::hasColumn('consumer_payments', 'consumer_zone_id')) {
            return 'consumer_zone_id';
        }

        return 'consumer_id';
    }

    /**
     * Keep only attributes that exist on consumer_payments; map legacy consumer_id → consumer_zone_id.
     */
    public static function filterTableAttributes(array $data): array
    {
        if (!Schema::hasTable('consumer_payments')) {
            return $data;
        }

        if (isset($data['consumer_id']) && !isset($data['consumer_zone_id'])) {
            $data['consumer_zone_id'] = $data['consumer_id'];
        }
        unset($data['consumer_id'], $data['account_name'], $data['materials'], $data['fees_charges'], $data['inspection_fee']);

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('consumer_payments', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Get the related PAYMENT entry in consumer_ledgers
     * Uses direct relationship via consumer_payment_id, with fallback matching
     */
    public function ledgerEntry()
    {
        if ($this->id) {
            $ledgerEntry = ConsumerLedger::query()
                ->where(mr_col('consumer_payment_id'), $this->id)
                ->where(mr_col('trans'), 'PAYMENT')
                ->first();

            if ($ledgerEntry) {
                return $ledgerEntry;
            }
        }

        $consumerZoneId = $this->consumer_zone_id ?? $this->consumer_id;
        if (!$consumerZoneId) {
            return null;
        }

        $query = ConsumerLedger::query()
            ->where(mr_col('consumer_zone_id'), $consumerZoneId)
            ->where(mr_col('trans'), 'PAYMENT');

        if ($this->or_number) {
            $query->where(mr_col('reference'), $this->or_number);
        }

        if ($this->payment_amount) {
            $query->where(mr_col('credit'), $this->payment_amount);
        }

        return $query->orderBy(mr_col('id'), 'desc')->first();
    }

    public function ledgerEntries()
    {
        return $this->hasMany(ConsumerLedger::class, 'consumer_payment_id')
            ->where(mr_col('trans'), 'PAYMENT');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($payment) {
            $ledgerEntry = ConsumerLedger::query()
                ->where(mr_col('consumer_payment_id'), $payment->id)
                ->where(mr_col('trans'), 'PAYMENT')
                ->first();

            $consumerZoneId = $payment->consumer_zone_id ?? $payment->consumer_id;

            if (!$ledgerEntry && $consumerZoneId) {
                if ($payment->or_number && $payment->payment_amount) {
                    $ledgerEntry = ConsumerLedger::query()
                        ->where(mr_col('consumer_zone_id'), $consumerZoneId)
                        ->where(mr_col('trans'), 'PAYMENT')
                        ->where(mr_col('reference'), $payment->or_number)
                        ->where(mr_col('credit'), $payment->payment_amount)
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                }

                if (!$ledgerEntry && $payment->or_number) {
                    $ledgerEntry = ConsumerLedger::query()
                        ->where(mr_col('consumer_zone_id'), $consumerZoneId)
                        ->where(mr_col('trans'), 'PAYMENT')
                        ->where(mr_col('reference'), $payment->or_number)
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                }

                if (!$ledgerEntry && $payment->payment_amount && $payment->paid_at) {
                    $paymentDate = \Carbon\Carbon::parse($payment->paid_at)->format('Y-m-d');
                    $ledgerEntry = ConsumerLedger::query()
                        ->where(mr_col('consumer_zone_id'), $consumerZoneId)
                        ->where(mr_col('trans'), 'PAYMENT')
                        ->where(mr_col('credit'), $payment->payment_amount)
                        ->where(mr_col('date'), $paymentDate)
                        ->orderBy(mr_col('id'), 'desc')
                        ->first();
                }
            }

            if ($ledgerEntry) {
                Log::info('Cascade deleting PAYMENT entry from consumer_ledgers', [
                    'consumer_payment_id' => $payment->id,
                    'consumer_ledger_id' => $ledgerEntry->id,
                    'or_number' => $payment->or_number,
                    'payment_amount' => $payment->payment_amount,
                    'consumer_zone_id' => $consumerZoneId,
                ]);

                $ledgerEntry->delete();
            } else {
                Log::warning('No matching PAYMENT entry found in consumer_ledgers for cascade delete', [
                    'consumer_payment_id' => $payment->id,
                    'or_number' => $payment->or_number,
                    'consumer_zone_id' => $consumerZoneId,
                    'payment_amount' => $payment->payment_amount,
                    'paid_at' => $payment->paid_at,
                ]);
            }
        });
    }
}
