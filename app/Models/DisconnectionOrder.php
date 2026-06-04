<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisconnectionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
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

    /**
     * Get the consumer associated with this disconnection order
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(ConsumerZoneOne::class, 'consumer_id');
    }

    /**
     * Get the disconnector assigned to this order
     */
    public function disconnector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disconnector_id');
    }

    /**
     * Scope to get pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get assigned orders
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', '!=', 'pending')
                     ->whereNotNull('disconnector_id');
    }

    /**
     * Scope to get orders for a specific disconnector
     */
    public function scopeForDisconnector($query, $disconnectorId)
    {
        return $query->where('disconnector_id', $disconnectorId);
    }

    /**
     * Scope to get active orders (pending, assigned, in-progress)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'assigned', 'in-progress']);
    }

    /**
     * Assign order to a disconnector
     */
    public function assignTo($disconnectorId)
    {
        $this->update([
            'disconnector_id' => $disconnectorId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    /**
     * Mark as disconnected
     */
    public function markAsDisconnected($notes = null)
    {
        $this->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        // Update consumer status_code to 'X' (Disconnected)
        if ($this->consumer_id) {
            $consumer = $this->consumer ?? $this->consumer()->first();
            if ($consumer) {
                $consumer->update([
                    'status_code' => 'X'
                ]);
            }
        }
    }

    /**
     * Mark as reconnected
     */
    public function markAsReconnected($notes = null)
    {
        $this->update([
            'status' => 'reconnected',
            'reconnected_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        // Update consumer status_code to 'A' (Active)
        if ($this->consumer_id) {
            $consumer = $this->consumer ?? $this->consumer()->first();
            if ($consumer) {
                $consumer->update([
                    'status_code' => 'A'
                ]);
            }
        }
    }

    /** Note used when order is cancelled because consumer paid - balance fully cleared (for API filtering). */
    public const CANCELLED_DUE_TO_PAYMENT_NOTE = 'Cancelled - payment received. Do not disconnect.';

    /** Note pattern: any note ending with this is treated as "cancelled due to payment" for API. */
    public const CANCELLED_DUE_TO_PAYMENT_NOTE_SUFFIX = 'Do not disconnect.';

    /**
     * Cancel all active disconnection orders for a consumer when they have a payment in consumer_payments.
     * Called from BillingProcessController after any payment is recorded (paid_at); balance does not need to be cleared.
     *
     * @param int $consumerId consumer_zone id
     * @param \Carbon\Carbon|string|null $paidAt payment date (e.g. from consumer_payments.paid_at) for the note; if null, uses "payment received"
     */
    public static function cancelActiveOrdersForConsumerDueToPayment(int $consumerId, $paidAt = null): int
    {
        $note = $paidAt
            ? 'Cancelled - consumer paid on ' . (\Carbon\Carbon::parse($paidAt)->format('Y-m-d')) . '. ' . self::CANCELLED_DUE_TO_PAYMENT_NOTE_SUFFIX
            : self::CANCELLED_DUE_TO_PAYMENT_NOTE;

        return static::where('consumer_id', $consumerId)
            ->whereIn('status', ['pending', 'assigned', 'in-progress'])
            ->update([
                'status' => 'cancelled',
                'notes' => $note,
            ]);
    }
}
