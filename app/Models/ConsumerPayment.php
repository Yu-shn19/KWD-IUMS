<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ConsumerLedger;
use Illuminate\Support\Facades\Log;

class ConsumerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reading_id',
        'consumer_id',
        'account_name',
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

    /**
     * Get the related PAYMENT entry in consumer_ledgers
     * Uses direct relationship via consumer_payment_id, with fallback matching
     */
    public function ledgerEntry()
    {
        // First try: Use direct link via consumer_payment_id (most reliable)
        if ($this->id) {
            $ledgerEntry = ConsumerLedger::where('consumer_payment_id', $this->id)
                ->where('trans', 'PAYMENT')
                ->first();
            
            if ($ledgerEntry) {
                return $ledgerEntry;
            }
        }

        // Fallback: Try matching by consumer_id, OR number, and amount
        // (for older entries that might not have consumer_payment_id set)
        if (!$this->consumer_id) {
            return null;
        }

        $query = ConsumerLedger::where('consumer_zone_id', $this->consumer_id)
            ->where('trans', 'PAYMENT');

        // Try to match by OR number first (most reliable)
        if ($this->or_number) {
            $query->where('reference', $this->or_number);
        }

        // Also match by payment amount for additional accuracy
        if ($this->payment_amount) {
            $query->where('credit', $this->payment_amount);
        }

        return $query->orderBy('id', 'desc')->first();
    }

    /**
     * Relationship: Get the PAYMENT entry in consumer_ledgers via direct link
     */
    public function ledgerEntries()
    {
        return $this->hasMany(ConsumerLedger::class, 'consumer_payment_id')
            ->where('trans', 'PAYMENT');
    }

    /**
     * Boot the model and register event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // When a payment is being deleted, also delete the corresponding PAYMENT entry in consumer_ledgers
        static::deleting(function ($payment) {
            // First try: Use direct link via consumer_payment_id (most reliable)
            $ledgerEntry = ConsumerLedger::where('consumer_payment_id', $payment->id)
                ->where('trans', 'PAYMENT')
                ->first();

            // Second try: If not found by direct link, try matching by consumer_id, OR number, and payment amount
            if (!$ledgerEntry && $payment->consumer_id) {
                if ($payment->or_number && $payment->payment_amount) {
                    $ledgerEntry = ConsumerLedger::where('consumer_zone_id', $payment->consumer_id)
                        ->where('trans', 'PAYMENT')
                        ->where('reference', $payment->or_number)
                        ->where('credit', $payment->payment_amount)
                        ->orderBy('id', 'desc')
                        ->first();
                }

                // Third try: If not found, try by OR number only (in case amount was updated)
                if (!$ledgerEntry && $payment->or_number) {
                    $ledgerEntry = ConsumerLedger::where('consumer_zone_id', $payment->consumer_id)
                        ->where('trans', 'PAYMENT')
                        ->where('reference', $payment->or_number)
                        ->orderBy('id', 'desc')
                        ->first();
                }

                // Fourth try: If still not found and we have payment amount, try by amount and date
                if (!$ledgerEntry && $payment->payment_amount && $payment->paid_at) {
                    $paymentDate = \Carbon\Carbon::parse($payment->paid_at)->format('Y-m-d');
                    $ledgerEntry = ConsumerLedger::where('consumer_zone_id', $payment->consumer_id)
                        ->where('trans', 'PAYMENT')
                        ->where('credit', $payment->payment_amount)
                        ->where('date', $paymentDate)
                        ->orderBy('id', 'desc')
                        ->first();
                }
            }

            if ($ledgerEntry) {
                Log::info('Cascade deleting PAYMENT entry from consumer_ledgers', [
                    'consumer_payment_id' => $payment->id,
                    'consumer_ledger_id' => $ledgerEntry->id,
                    'or_number' => $payment->or_number,
                    'payment_amount' => $payment->payment_amount,
                    'consumer_id' => $payment->consumer_id,
                    'match_method' => $ledgerEntry->consumer_payment_id == $payment->id ? 'direct_link' :
                                     ($payment->or_number && $payment->payment_amount ? 'or_number_and_amount' : 
                                     ($payment->or_number ? 'or_number_only' : 'amount_and_date'))
                ]);

                $ledgerEntry->delete();
            } else {
                Log::warning('No matching PAYMENT entry found in consumer_ledgers for cascade delete', [
                    'consumer_payment_id' => $payment->id,
                    'or_number' => $payment->or_number,
                    'consumer_id' => $payment->consumer_id,
                    'payment_amount' => $payment->payment_amount,
                    'paid_at' => $payment->paid_at
                ]);
            }
        });
    }
}

