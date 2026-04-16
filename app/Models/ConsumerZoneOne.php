<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumerZoneOne extends Model
{
    use HasFactory;

    protected $table = 'consumer_zone';

    protected $fillable = [
        'account_no',
        'account_name',
        'address1',
        'latitude',
        'longitude',
        'zone_code',
        'sequence',
        'category_code',
        'meter_number',
        'meter_brand',
        'rate_code',
        'install_date',
        'status_code',
        'consumer_deposit',
        'installation_fee',
        'installation_balance',
        'balance',
        'cons_ctrl',
         'bill_disc_percent',
        'bill_disc_amount',
        'bill_disc_updated_at',
        'osca_id_no',
        'remark',
    ];

    protected $casts = [
        'install_date' => 'date',
        'consumer_deposit' => 'decimal:2',
        'installation_fee' => 'decimal:2',
        'installation_balance' => 'decimal:2',
        'balance' => 'decimal:2',
           'bill_disc_percent' => 'string',
        'bill_disc_amount' => 'decimal:2',
        'bill_disc_updated_at' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

      public function getStatusLabelAttribute(): string
    {
        $code = strtoupper(trim((string) ($this->status_code ?? '')));

        return match ($code) {
            'A', 'ACTIVE' => 'Active',
            'P', 'PENDING' => 'Pending',
            'X', 'DISCONNECTED', 'D' => 'Disconnected', // D: legacy stored code for disconnected
            default => $code !== '' ? (string) $this->status_code : 'N/A',
        };
    }

    /**
     * Get the ledger entries for this consumer zone.
     */
    public function ledgers()
    {
        return $this->hasMany(ConsumerLedger::class, 'consumer_zone_id');
    }
}

