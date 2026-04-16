<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingTier;
use Illuminate\Http\Request;

class PricingTierApiController extends Controller
{
    /**
     * Get all active pricing tiers for mobile app
     */
    public function index()
    {
        $pricingTiers = PricingTier::where('is_active', true)
            ->orderBy('category_id')
            ->orderBy('rate_code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pricingTiers->map(function ($tier) {
                return [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'category_id' => $tier->category_id,
                    'rate_code' => $tier->rate_code,
                    'min_charge' => (float) $tier->min_charge,
                    'meter_rental' => (float) $tier->meter_rental,
                    'tier1_rate' => $tier->tier1_rate ? (float) $tier->tier1_rate : null,
                    'tier1_max' => $tier->tier1_max,
                    'tier2_rate' => $tier->tier2_rate ? (float) $tier->tier2_rate : null,
                    'tier2_max' => $tier->tier2_max,
                    'tier3_rate' => $tier->tier3_rate ? (float) $tier->tier3_rate : null,
                    'tier3_max' => $tier->tier3_max,
                    'tier4_rate' => $tier->tier4_rate ? (float) $tier->tier4_rate : null,
                ];
            })
        ]);
    }

    /**
     * Get pricing tier by category and rate code
     */
    public function getByCategoryAndRateCode(Request $request)
    {
        $request->validate([
            'category_id' => 'required|string',
            'rate_code' => 'nullable|string',
        ]);

        $tier = PricingTier::getByCategoryAndRateCode(
            $request->category_id,
            $request->rate_code
        );

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Pricing tier not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tier->id,
                'name' => $tier->name,
                'category_id' => $tier->category_id,
                'rate_code' => $tier->rate_code,
                'min_charge' => (float) $tier->min_charge,
                'meter_rental' => (float) $tier->meter_rental,
                'tier1_rate' => $tier->tier1_rate ? (float) $tier->tier1_rate : null,
                'tier1_max' => $tier->tier1_max,
                'tier2_rate' => $tier->tier2_rate ? (float) $tier->tier2_rate : null,
                'tier2_max' => $tier->tier2_max,
                'tier3_rate' => $tier->tier3_rate ? (float) $tier->tier3_rate : null,
                'tier3_max' => $tier->tier3_max,
                'tier4_rate' => $tier->tier4_rate ? (float) $tier->tier4_rate : null,
            ]
        ]);
    }
}
