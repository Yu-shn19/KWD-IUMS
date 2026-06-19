<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class DisconnectionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_zone_id',
        'consumer_id',
        'disconnector_id',
        'account_no',
        'account_name',
        'address',
        'zone_code',
        'meter_number',
        'card_number',
        'this_month_arrears',
        'last_month_arrears',
        'others_ar',
        'total_outstanding',
        'current_bill_with_maintenance',
        'last_reading',
        'unpaid_months',
        'oldest_unpaid_date',
        'latest_unpaid_date',
        'disconnection_date',
        'list_billing_month',
        'list_billing_date',
        'list_filter_type',
        'status',
        'assigned_at',
        'disconnected_at',
        'reconnected_at',
        'notes',
    ];

    protected $casts = [
        'this_month_arrears' => 'decimal:2',
        'last_month_arrears' => 'decimal:2',
        'others_ar' => 'decimal:2',
        'total_outstanding' => 'decimal:2',
        'current_bill_with_maintenance' => 'decimal:2',
        'last_reading' => 'decimal:2',
        'oldest_unpaid_date' => 'date',
        'latest_unpaid_date' => 'date',
        'disconnection_date' => 'date',
        'list_billing_date' => 'date',
        'assigned_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'reconnected_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'assigned_at',
        'disconnected_at',
        'reconnected_at',
    ];

    public static function consumerZoneIdColumn(): string
    {
        if (Schema::hasTable('disconnection_orders') && Schema::hasColumn('disconnection_orders', 'consumer_zone_id')) {
            return 'consumer_zone_id';
        }

        return 'consumer_id';
    }

    public function getConsumerZoneIdAttribute(): ?int
    {
        $column = static::consumerZoneIdColumn();

        return isset($this->attributes[$column]) && $this->attributes[$column] !== null
            ? (int) $this->attributes[$column]
            : null;
    }

    /** @deprecated Use consumer_zone_id */
    public function getConsumerIdAttribute(): ?int
    {
        return $this->consumer_zone_id;
    }

    public function consumerZone(): BelongsTo
    {
        return $this->belongsTo(ConsumerZone::class, static::consumerZoneIdColumn());
    }

    /** @deprecated Use consumerZone() */
    public function consumer(): BelongsTo
    {
        return $this->consumerZone();
    }

    public function scopeForConsumerZone($query, ?int $consumerZoneId)
    {
        if (!$consumerZoneId) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(static::consumerZoneIdColumn(), $consumerZoneId);
    }

    /**
     * Keep only attributes that exist on disconnection_orders; map legacy consumer_id → consumer_zone_id.
     */
    public static function filterTableAttributes(array $data): array
    {
        if (!Schema::hasTable('disconnection_orders')) {
            return $data;
        }

        if (isset($data['consumer_id']) && !isset($data['consumer_zone_id'])) {
            $data['consumer_zone_id'] = $data['consumer_id'];
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if ($key === 'consumer_id' && Schema::hasColumn('disconnection_orders', 'consumer_zone_id')) {
                continue;
            }
            if (Schema::hasColumn('disconnection_orders', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    public function disconnector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disconnector_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', '!=', 'pending')
            ->whereNotNull('disconnector_id');
    }

    public function scopeForDisconnector($query, $disconnectorId)
    {
        return $query->where('disconnector_id', $disconnectorId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'assigned', 'in-progress']);
    }

    public function assignTo($disconnectorId)
    {
        $this->update([
            'disconnector_id' => $disconnectorId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    public function markAsDisconnected($notes = null)
    {
        $this->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        $consumerZoneId = $this->consumer_zone_id;
        if ($consumerZoneId) {
            $consumer = $this->consumerZone ?? $this->consumerZone()->first();
            if ($consumer) {
                $consumer->update([
                    'status_code' => 'X',
                ]);
            }
        }
    }

    public function markAsReconnected($notes = null)
    {
        $this->update([
            'status' => 'reconnected',
            'reconnected_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        $consumerZoneId = $this->consumer_zone_id;
        if ($consumerZoneId) {
            $consumer = $this->consumerZone ?? $this->consumerZone()->first();
            if ($consumer) {
                $consumer->update([
                    'status_code' => 'A',
                ]);
            }
        }
    }

    public const CANCELLED_DUE_TO_PAYMENT_NOTE = 'Cancelled - payment received. Do not disconnect.';

    public const CANCELLED_DUE_TO_PAYMENT_NOTE_SUFFIX = 'Do not disconnect.';

    public static function cancelActiveOrdersForConsumerDueToPayment(int $consumerZoneId, $paidAt = null): int
    {
        $note = $paidAt
            ? 'Cancelled - consumer paid on ' . (\Carbon\Carbon::parse($paidAt)->format('Y-m-d')) . '. ' . self::CANCELLED_DUE_TO_PAYMENT_NOTE_SUFFIX
            : self::CANCELLED_DUE_TO_PAYMENT_NOTE;

        return static::forConsumerZone($consumerZoneId)
            ->whereIn('status', ['pending', 'assigned', 'in-progress'])
            ->update([
                'status' => 'cancelled',
                'notes' => $note,
            ]);
    }
}
