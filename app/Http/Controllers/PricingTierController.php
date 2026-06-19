<?php

namespace App\Http\Controllers;

use App\Models\PricingTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class PricingTierController extends Controller
{
    /**
     * Display a listing of pricing tiers
     */
    public function index()
    {
        $categoryIdColumn = mr_col('category_id');
        $rateCodeColumn = mr_col('rate_code');
        $nameColumn = mr_col('name');

        $pricingTiers = PricingTier::query()
            ->orderBy($categoryIdColumn)
            ->orderBy($rateCodeColumn)
            ->orderBy($nameColumn)
            ->get();
        
        return view('admin.pricing-tiers.index', compact('pricingTiers'));
    }

    /**
     * Show the form for creating a new pricing tier
     */
    public function create()
    {
        return view('admin.pricing-tiers.create');
    }

    /**
     * Store a newly created pricing tier
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|string|max:10',
            'rate_code' => 'nullable|string|max:10',
            'min_charge' => 'required|numeric|min:0',
            'meter_rental' => 'nullable|numeric|min:0',
            'tier1_rate' => 'nullable|numeric|min:0',
            'tier1_max' => 'nullable|integer|min:11',
            'tier2_rate' => 'nullable|numeric|min:0',
            'tier2_max' => 'nullable|integer|min:21',
            'tier3_rate' => 'nullable|numeric|min:0',
            'tier3_max' => 'nullable|integer|min:31',
            'tier4_rate' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['meter_rental'] = $validated['meter_rental'] ?? 20.00;
        $validated['is_active'] = $request->has('is_active');

        PricingTier::create($validated);

        return redirect()->route('pricing-tiers.index')
            ->with('success', 'Pricing tier created successfully.');
    }

    /**
     * Display the specified pricing tier
     */
    public function show(PricingTier $pricingTier)
    {
        return view('admin.pricing-tiers.show', compact('pricingTier'));
    }

    /**
     * Show the form for editing the specified pricing tier
     */
    public function edit(PricingTier $pricingTier)
    {
        return view('admin.pricing-tiers.edit', compact('pricingTier'));
    }

    /**
     * Update the specified pricing tier
     */
    public function update(Request $request, PricingTier $pricingTier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|string|max:10',
            'rate_code' => 'nullable|string|max:10',
            'min_charge' => 'required|numeric|min:0',
            'meter_rental' => 'nullable|numeric|min:0',
            'tier1_rate' => 'nullable|numeric|min:0',
            'tier1_max' => 'nullable|integer|min:11',
            'tier2_rate' => 'nullable|numeric|min:0',
            'tier2_max' => 'nullable|integer|min:21',
            'tier3_rate' => 'nullable|numeric|min:0',
            'tier3_max' => 'nullable|integer|min:31',
            'tier4_rate' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['meter_rental'] = $validated['meter_rental'] ?? 20.00;
        $validated['is_active'] = $request->has('is_active');

        $pricingTier->update($validated);

        return redirect()->route('pricing-tiers.index')
            ->with('success', 'Pricing tier updated successfully.');
    }

    /**
     * Remove the specified pricing tier
     */
    public function destroy(PricingTier $pricingTier)
    {
        $pricingTier->delete();

        return redirect()->route('pricing-tiers.index')
            ->with('success', 'Pricing tier deleted successfully.');
    }

    /**
     * Initialize default pricing tiers
     */
    public function initializeDefaults()
    {
        DB::transaction(function () {
            // Check if tiers already exist
            if (PricingTier::count() > 0) {
                return redirect()->route('pricing-tiers.index')
                    ->with('warning', 'Pricing tiers already exist. Delete existing tiers first to reinitialize.');
            }

            $defaultTiers = [
                // Residential (Category 12)
                [
                    'name' => 'Residential',
                    'category_id' => '12',
                    'rate_code' => null,
                    'min_charge' => 195.00,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 21.60,
                    'tier1_max' => 20,
                    'tier2_rate' => 23.75,
                    'tier2_max' => 30,
                    'tier3_rate' => 26.10,
                    'tier3_max' => 40,
                    'tier4_rate' => 28.50,
                    'is_active' => true,
                ],
                // Government (Category 22) - same as Residential
                [
                    'name' => 'Government',
                    'category_id' => '22',
                    'rate_code' => null,
                    'min_charge' => 195.00,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 21.60,
                    'tier1_max' => 20,
                    'tier2_rate' => 23.75,
                    'tier2_max' => 30,
                    'tier3_rate' => 26.10,
                    'tier3_max' => 40,
                    'tier4_rate' => 28.50,
                    'is_active' => true,
                ],
                // Rate Code C (for Categories 32 and 33)
                [
                    'name' => 'Rate Code C',
                    'category_id' => null,
                    'rate_code' => 'C',
                    'min_charge' => 390.00,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 43.20,
                    'tier1_max' => 20,
                    'tier2_rate' => 47.50,
                    'tier2_max' => 30,
                    'tier3_rate' => 52.20,
                    'tier3_max' => 40,
                    'tier4_rate' => 57.00,
                    'is_active' => true,
                ],
                // Rate Code D (for Categories 32 and 33)
                [
                    'name' => 'Rate Code D',
                    'category_id' => null,
                    'rate_code' => 'D',
                    'min_charge' => 243.75,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 27.00,
                    'tier1_max' => 20,
                    'tier2_rate' => 29.65,
                    'tier2_max' => 30,
                    'tier3_rate' => 32.60,
                    'tier3_max' => 40,
                    'tier4_rate' => 35.60,
                    'is_active' => true,
                ],
                // Commercial B (Category 34)
                [
                    'name' => 'Commercial B',
                    'category_id' => '34',
                    'rate_code' => null,
                    'min_charge' => 292.50,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 32.40,
                    'tier1_max' => 20,
                    'tier2_rate' => 35.60,
                    'tier2_max' => 30,
                    'tier3_rate' => 39.15,
                    'tier3_max' => 40,
                    'tier4_rate' => 42.75,
                    'is_active' => true,
                ],
                // Commercial D (Category 35)
                [
                    'name' => 'Commercial D',
                    'category_id' => '35',
                    'rate_code' => null,
                    'min_charge' => 243.75,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 27.00,
                    'tier1_max' => 20,
                    'tier2_rate' => 29.65,
                    'tier2_max' => 30,
                    'tier3_rate' => 32.60,
                    'tier3_max' => 40,
                    'tier4_rate' => 35.60,
                    'is_active' => true,
                ],
                // Wholesale (Category 36)
                [
                    'name' => 'Wholesale',
                    'category_id' => '36',
                    'rate_code' => null,
                    'min_charge' => 585.00,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 64.80,
                    'tier1_max' => 20,
                    'tier2_rate' => 71.25,
                    'tier2_max' => 30,
                    'tier3_rate' => 78.30,
                    'tier3_max' => 40,
                    'tier4_rate' => 85.50,
                    'is_active' => true,
                ],
                // Commercial Industrial (Category 32) - fallback when no rate code
                [
                    'name' => 'Commercial Industrial (No Rate Code)',
                    'category_id' => '32',
                    'rate_code' => null,
                    'min_charge' => 390.00,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 43.20,
                    'tier1_max' => 20,
                    'tier2_rate' => 47.50,
                    'tier2_max' => 30,
                    'tier3_rate' => 52.20,
                    'tier3_max' => 40,
                    'tier4_rate' => 57.00,
                    'is_active' => true,
                ],
                // Commercial A (Category 33) - fallback when no rate code
                [
                    'name' => 'Commercial A (No Rate Code)',
                    'category_id' => '33',
                    'rate_code' => null,
                    'min_charge' => 341.25,
                    'meter_rental' => 20.00,
                    'tier1_rate' => 37.80,
                    'tier1_max' => 20,
                    'tier2_rate' => 41.55,
                    'tier2_max' => 30,
                    'tier3_rate' => 45.65,
                    'tier3_max' => 40,
                    'tier4_rate' => 49.85,
                    'is_active' => true,
                ],
            ];

            foreach ($defaultTiers as $tier) {
                PricingTier::create($tier);
            }
        });

        return redirect()->route('pricing-tiers.index')
            ->with('success', 'Default pricing tiers initialized successfully.');
    }
}
