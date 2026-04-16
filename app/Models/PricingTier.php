<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'rate_code',
        'min_charge',
        'meter_rental',
        'tier1_rate',
        'tier1_max',
        'tier2_rate',
        'tier2_max',
        'tier3_rate',
        'tier3_max',
        'tier4_rate',
        'is_active',
        'description',
    ];

    protected $casts = [
        'min_charge' => 'decimal:2',
        'meter_rental' => 'decimal:2',
        'tier1_rate' => 'decimal:2',
        'tier2_rate' => 'decimal:2',
        'tier3_rate' => 'decimal:2',
        'tier4_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get pricing tier by category and rate code
     */
    public static function getByCategoryAndRateCode($categoryId, $rateCode = null)
    {
        $query = static::where('is_active', true)
            ->where('category_id', $categoryId);
        
        if ($rateCode) {
            $query->where('rate_code', strtoupper($rateCode));
        } else {
            $query->whereNull('rate_code');
        }
        
        return $query->first();
    }

    /**
     * Calculate bill based on consumption using this pricing tier
     */
    public function calculateBill($consumption)
    {
        $cu = (int) $consumption;
        if ($cu < 0) return 0;
        
        $total = $this->min_charge;
        
        if ($cu <= 10) {
            return $total;
        }
        
        // Tier 1: 11-20
        if ($cu <= $this->tier1_max && $this->tier1_rate) {
            $tier1Consumption = min($cu - 10, $this->tier1_max - 10);
            $total += $tier1Consumption * $this->tier1_rate;
            return $total;
        } else if ($this->tier1_rate) {
            $total += ($this->tier1_max - 10) * $this->tier1_rate;
        }
        
        // Tier 2: 21-30
        if ($cu <= $this->tier2_max && $this->tier2_rate) {
            $tier2Consumption = min($cu - $this->tier1_max, $this->tier2_max - $this->tier1_max);
            $total += $tier2Consumption * $this->tier2_rate;
            return $total;
        } else if ($this->tier2_rate) {
            $total += ($this->tier2_max - $this->tier1_max) * $this->tier2_rate;
        }
        
        // Tier 3: 31-40
        if ($cu <= $this->tier3_max && $this->tier3_rate) {
            $tier3Consumption = min($cu - $this->tier2_max, $this->tier3_max - $this->tier2_max);
            $total += $tier3Consumption * $this->tier3_rate;
            return $total;
        } else if ($this->tier3_rate) {
            $total += ($this->tier3_max - $this->tier2_max) * $this->tier3_rate;
        }
        
        // Tier 4: 41+
        if ($cu > $this->tier3_max && $this->tier4_rate) {
            $tier4Consumption = $cu - $this->tier3_max;
            $total += $tier4Consumption * $this->tier4_rate;
        }
        
        return $total;
    }
}
