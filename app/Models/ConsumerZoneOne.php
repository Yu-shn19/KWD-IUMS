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
    ];

    protected $casts = [
        'install_date' => 'date',
        'consumer_deposit' => 'decimal:2',
        'installation_fee' => 'decimal:2',
        'installation_balance' => 'decimal:2',
        'balance' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match (strtoupper((string) $this->status_code)) {
            'A', 'ACTIVE' => 'Active',
            'I', 'INACTIVE' => 'Inactive',
            'S', 'SUSPENDED' => 'Suspended',
            'D', 'DISCONNECTED' => 'Disconnected',
            default => $this->status_code ?? 'N/A',
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

