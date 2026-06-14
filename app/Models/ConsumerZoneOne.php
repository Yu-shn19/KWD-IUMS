<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ConsumerZoneOne extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'status_label',
    ];

    protected $table = 'consumer_zone';

    /**
     * Columns that exist on consumer_zone (see 2026_06_13_184411_create_consumer_zone_table).
     */
    protected $fillable = [
        'account_no',
        'account_name',
        'gender',
        'address',
        'latitude',
        'longitude',
        'zone_code',
        'sequence',
        'category_code',
        'meter_number',
        'meter_brand',
        'base_reading',
        'base_reading_date',
        'rate_code',
        'install_date',
        'transaction_date',
        'status_code',
        'pending_install_activation',
        'auto_activate_on',
        'bill_disc_percent',
        'osca_id_no',
        'bill_disc_updated_at',
        'cons_ctrl',
    ];

    protected $casts = [
        'install_date' => 'date',
        'transaction_date' => 'date',
        'pending_install_activation' => 'boolean',
        'auto_activate_on' => 'date',
        'bill_disc_percent' => 'string',
        'bill_disc_updated_at' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'base_reading_date' => 'date',
    ];

    public function getStatusLabelAttribute(): string
    {
        $code = strtoupper(trim((string) ($this->status_code ?? '')));

        return match ($code) {
            'A', 'ACTIVE' => 'Active',
            'P', 'PENDING' => 'Pending',
            'X', 'DISCONNECTED', 'D' => 'Disconnected',
            default => $code !== '' ? (string) $this->status_code : 'N/A',
        };
    }

    /** @deprecated Use address column on consumer_zone */
    public function getAddress1Attribute(): ?string
    {
        return $this->attributes['address'] ?? null;
    }

    /**
     * Running balance from consumer_ledgers (not stored on consumer_zone).
     */
    public function getLedgerBalance(): float
    {
        $latest = ConsumerLedger::where('consumer_zone_id', $this->id)
            ->whereNotNull('balance')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latest ? (float) ($latest->balance ?? 0) : 0.0;
    }

    /**
     * Virtual balance — always resolved from consumer_ledgers.
     */
    public function getBalanceAttribute(): float
    {
        return $this->getLedgerBalance();
    }

    /**
     * Keep only attributes that exist on consumer_zone.
     * Maps legacy address1 → address when present.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filterTableAttributes(array $data): array
    {
        if (!Schema::hasTable('consumer_zone')) {
            return $data;
        }

        if (isset($data['address1']) && !isset($data['address']) && Schema::hasColumn('consumer_zone', 'address')) {
            $data['address'] = $data['address1'];
        }
        unset($data['address1']);

        $payload = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn('consumer_zone', $key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Resolve consumer by account number (exact + normalized).
     */
    public static function findByAccountNo(?string $accountNo): ?self
    {
        $accountNo = trim((string) ($accountNo ?? ''));
        if ($accountNo === '') {
            return null;
        }

        $consumer = static::where('account_no', $accountNo)->first();
        if ($consumer) {
            return $consumer;
        }

        $normalized = str_replace('-', '', $accountNo);

        return static::where(function ($q) use ($accountNo, $normalized) {
            $q->whereRaw("REPLACE(account_no, '-', '') = ?", [$normalized])
              ->orWhereRaw("UPPER(TRIM(account_no)) = ?", [strtoupper($accountNo)]);
        })->first();
    }

    public function ledgers()
    {
        return $this->hasMany(ConsumerLedger::class, 'consumer_zone_id');
    }

    public function disconnectionOrders(): HasMany
    {
        return $this->hasMany(DisconnectionOrder::class, DisconnectionOrder::consumerZoneIdColumn());
    }

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
     * Latest disconnect time from disconnection_orders for this consumer_zone_id.
     */
    public function latestDisconnectedAtForDisplay(): ?Carbon
    {
        if (!$this->isDisconnectedStatus()) {
            return null;
        }

        $disconnectedAt = $this->disconnectionOrders()
            ->whereNotNull('disconnected_at')
            ->orderByDesc('disconnected_at')
            ->value('disconnected_at');

        return $disconnectedAt ? Carbon::parse($disconnectedAt) : null;
    }
}
