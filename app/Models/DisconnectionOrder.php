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
        'disconnector_id',
        'total_outstanding',
        'unpaid_months',
        'disconnection_date',
        'disconnected_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_outstanding' => 'decimal:2',
        'disconnection_date' => 'date',
        'disconnected_at' => 'datetime',
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
     * Identity fields (account_no, account_name, etc.) live on consumer_zone — not duplicated here.
     */
    public static function filterTableAttributes(array $data): array
    {
        if (!Schema::hasTable('disconnection_orders')) {
            return $data;
        }

        if (isset($data['consumer_id']) && !isset($data['consumer_zone_id'])) {
            $data['consumer_zone_id'] = $data['consumer_id'];
        }

        unset(
            $data['consumer_id'],
            $data['account_no'],
            $data['account_name'],
            $data['address'],
            $data['zone_code'],
            $data['meter_number'],
            $data['card_number'],
            $data['this_month_arrears'],
            $data['last_month_arrears'],
            $data['others_ar'],
            $data['current_bill_with_maintenance'],
            $data['last_reading'],
            $data['oldest_unpaid_date'],
            $data['latest_unpaid_date'],
            $data['list_billing_month'],
            $data['list_billing_date'],
            $data['list_filter_type'],
            $data['assigned_at'],
            $data['reconnected_at']
        );

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('disconnection_orders', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /** Resolve a consumer_zone column when not stored on disconnection_orders. */
    private function consumerZoneValue(string $column): mixed
    {
        if ($this->relationLoaded('consumerZone') && $this->consumerZone) {
            return $this->consumerZone->{$column};
        }

        if ($this->consumer_zone_id) {
            return $this->consumerZone()->value($column);
        }

        return null;
    }

    public function getAccountNoAttribute(): ?string
    {
        return $this->consumerZoneValue('account_no');
    }

    public function getAccountNameAttribute(): ?string
    {
        return $this->consumerZoneValue('account_name');
    }

    public function getAddressAttribute(): ?string
    {
        return $this->consumerZoneValue('address');
    }

    public function getZoneCodeAttribute(): ?string
    {
        return $this->consumerZoneValue('zone_code');
    }

    public function getMeterNumberAttribute(): ?string
    {
        return $this->consumerZoneValue('meter_number');
    }

    public function getCardNumberAttribute(): ?int
    {
        $sequence = $this->consumerZoneValue('sequence');

        return is_numeric($sequence) ? (int) $sequence : null;
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
