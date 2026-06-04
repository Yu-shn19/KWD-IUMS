<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ConsumerZoneOne extends Model
{
    use HasFactory;
      /**
     * Include human-readable status in JSON (e.g. search + sessionStorage) so the UI is not stuck on raw codes like "X".
     *
     * @var array<int, string>
     */
    protected $appends = [
        'status_label',
    ];


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
        'transaction_date',
        'status_code',
         'pending_install_activation',
        'auto_activate_on',
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
         'base_reading',
        'base_reading_date',
        'base_reading_at',
        'base_reading_by',
    ];

    protected $casts = [
        'install_date' => 'date',
          'transaction_date' => 'date',
           'pending_install_activation' => 'boolean',
        'auto_activate_on' => 'date',
        'consumer_deposit' => 'decimal:2',
        'installation_fee' => 'decimal:2',
        'installation_balance' => 'decimal:2',
        'balance' => 'decimal:2',
           'bill_disc_percent' => 'string',
        'bill_disc_amount' => 'decimal:2',
        'bill_disc_updated_at' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
         'base_reading_date' => 'date',
        'base_reading_at' => 'datetime',
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
    
    
    /**
     * Disconnection workflow orders tied to this consumer (consumer_id = consumer_zone.id).
     */
    public function disconnectionOrders(): HasMany
    {
        return $this->hasMany(DisconnectionOrder::class, 'consumer_id');
    }

    /**
     * Raw status codes that mean disconnected service (matches status_label / header UI).
     */
    public function isDisconnectedStatus(): bool
    {
        $code = strtoupper(trim((string) ($this->status_code ?? '')));

        return in_array($code, ['X', 'D', 'DISCONNECTED'], true);
    }

  
    public static function isPendingStatus(?string $statusCode): bool
    {
        $code = strtoupper(trim((string) $statusCode));

        return in_array($code, ['P', 'PENDING'], true);
    }

    /**
     * Enroll or clear auto-activation for new-install Pending consumers (Add/Edit consumer only).
     *
     * @param  array<string, mixed>  $data
     */
    public static function syncInstallActivationFields(array &$data, ?self $existing = null): void
    {
        $statusCode = array_key_exists('status_code', $data)
            ? $data['status_code']
            : $existing?->status_code;

        $installDate = array_key_exists('install_date', $data)
            ? $data['install_date']
            : $existing?->install_date;

        if (static::isPendingStatus(is_string($statusCode) ? $statusCode : (string) $statusCode) && $installDate) {
            $days = (int) config('consumer.days_before_active', 15);
            $data['pending_install_activation'] = true;
            $data['auto_activate_on'] = Carbon::parse($installDate)->addDays($days)->toDateString();

            return;
        }

        $data['pending_install_activation'] = false;
        $data['auto_activate_on'] = null;
    }


    /**
     * Latest actual disconnect time from disconnection orders (by consumer_id, then by matching account_no).
     */
    public function latestDisconnectedAtForDisplay(): ?Carbon
    {
        if (! $this->isDisconnectedStatus()) {
            return null;
        }

        $byConsumer = $this->disconnectionOrders()
            ->whereNotNull('disconnected_at')
            ->orderByDesc('disconnected_at')
            ->first();

        if ($byConsumer?->disconnected_at) {
            return $byConsumer->disconnected_at;
        }

        return DisconnectionOrder::query()
            ->where('account_no', $this->account_no)
            ->whereNotNull('disconnected_at')
            ->orderByDesc('disconnected_at')
            ->first()?->disconnected_at;
    }
}

