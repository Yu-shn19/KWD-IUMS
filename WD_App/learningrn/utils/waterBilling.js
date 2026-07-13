/**
 * Water bill calculation — mirrors Laravel:
 * - MeterReadingApiController::calculateWaterBill
 * - App\Services\WaterBillingService
 * - App\Models\PricingTiers::calculateBill
 *
 * Returns water charge only (meter rental / maintenance is added separately).
 */

export const METER_RENTAL = 20.0;
export const MINIMUM_CHARGE = 253.0;

function round2(n) {
  return Math.round((Number(n) + Number.EPSILON) * 100) / 100;
}

/** MeterReadingApiController::resolveCategoryId */
export function resolveCategoryId(category) {
  if (category == null || category === '') return null;
  const raw = String(category).trim().toUpperCase();
  if (!raw) return null;

  if (!Number.isNaN(Number(raw)) && raw === String(parseInt(raw, 10))) {
    return parseInt(raw, 10);
  }

  const mapped = {
    RES: 12,
    RESIDENTIAL: 12,
    GOVT: 22,
    'GOVT-LGU': 22,
    GOVERNMENT: 22,
    INDUSTRIAL: 32,
    'COM-A': 33,
    'COMMERCIAL A': 33,
    COMMERCIAL_A: 33,
    'COM-B': 34,
    'COMMERCIAL B': 34,
    COMMERCIAL_B: 34,
    'COM-C': 35,
    'COMMERCIAL C': 35,
    COMMERCIAL_C: 35,
  };
  if (mapped[raw] != null) return mapped[raw];

  if (raw.includes('RES')) return 12;
  if (raw.includes('GOV')) return 22;
  if (raw.includes('IND')) return 32;
  if (raw.includes('COMA')) return 33;
  if (raw.includes('COMB')) return 34;
  if (raw.includes('COMC') || raw.includes('COMD')) return 35;
  if (raw.includes('COM')) return 32;
  if (raw.includes('WHOLESALE') || raw.includes('BULK')) return 36;
  return null;
}

/** WaterBillingService::resolveClassification */
export function resolveClassification(category = null, rateCode = null) {
  const raw = String(category ?? '')
    .trim()
    .toUpperCase();

  if (raw) {
    const mapped = {
      'COM-A': 'COMMERCIAL A',
      'COM-B': 'COMMERCIAL B',
      'COM-C': 'COMMERCIAL C',
      INDUSTRIAL: 'INDUSTRIAL',
      'GOVT-LGU': 'GOVERNMENT',
      GOVT: 'GOVERNMENT',
      GOVERNMENT: 'GOVERNMENT',
      RES: 'RESIDENTIAL',
      RESIDENTIAL: 'RESIDENTIAL',
    };
    if (mapped[raw]) return mapped[raw];

    if (!Number.isNaN(Number(raw)) && String(parseInt(raw, 10)) === String(Number(raw))) {
      switch (parseInt(raw, 10)) {
        case 32:
          return 'INDUSTRIAL';
        case 33:
          return 'COMMERCIAL A';
        case 34:
          return 'COMMERCIAL B';
        case 35:
          return 'COMMERCIAL C';
        case 36:
          return 'WHOLESALE';
        case 22:
          return 'GOVERNMENT';
        default:
          return 'RESIDENTIAL';
      }
    }

    if (raw.includes('IND')) return 'INDUSTRIAL';
    if (raw.includes('WHOLESALE') || raw.includes('BULK')) return 'WHOLESALE';
    if (raw.includes('COMA') || raw.includes('COMMERCIAL A')) return 'COMMERCIAL A';
    if (raw.includes('COMB') || raw.includes('COMMERCIAL B')) return 'COMMERCIAL B';
    if (raw.includes('COMC') || raw.includes('COMMERCIAL C')) return 'COMMERCIAL C';
    if (raw.includes('COM')) return 'COMMERCIAL';
    if (raw.includes('GOV')) return 'GOVERNMENT';
    if (raw.includes('RES')) return 'RESIDENTIAL';
  }

  const rate = String(rateCode ?? '')
    .trim()
    .toUpperCase();
  if (rate) return rate;

  return 'RESIDENTIAL';
}

/** WaterBillingService::classificationMultiplier */
export function classificationMultiplier(classification) {
  const key = String(classification ?? '')
    .trim()
    .toUpperCase();
  switch (key) {
    case 'BULK':
    case 'WHOLESALE':
      return 3.0;
    case 'INDUSTRIAL':
    case 'COMMERCIAL':
      return 2.0;
    case 'COMMERCIAL A':
    case 'COMMERCIAL_A':
    case 'A':
      return 1.75;
    case 'COMMERCIAL B':
    case 'COMMERCIAL_B':
    case 'B':
      return 1.5;
    case 'COMMERCIAL C':
    case 'COMMERCIAL_C':
    case 'C':
      return 1.25;
    default:
      return 1.0;
  }
}

/** WaterBillingService::computeResidentialBase */
export function computeResidentialBase(cu) {
  const n = Math.max(0, parseInt(cu, 10) || 0);
  if (n <= 10) return MINIMUM_CHARGE;
  if (n <= 20) return MINIMUM_CHARGE + (n - 10) * 27.0;
  if (n <= 30) return MINIMUM_CHARGE + 10 * 27.0 + (n - 20) * 28.75;
  if (n <= 40) return MINIMUM_CHARGE + 10 * 27.0 + 10 * 28.75 + (n - 30) * 30.55;
  return MINIMUM_CHARGE + 10 * 27.0 + 10 * 28.75 + 10 * 30.55 + (n - 40) * 32.4;
}

/** WaterBillingService::computeCommercialIndustrial (meter rental excluded) */
export function computeCommercialIndustrial(cu, classification = 'INDUSTRIAL') {
  return computeResidentialBase(cu) * classificationMultiplier(classification);
}

function usesResidentialMultiplier(classification) {
  return classification === 'RESIDENTIAL' || classification === 'GOVERNMENT';
}

/** PricingTiers::calculateBill */
export function calculateBillFromPricingTier(consumption, tier) {
  const cu = parseInt(consumption, 10);
  if (Number.isNaN(cu) || cu < 0) return 0;

  let total = Number(tier.min_charge) || 0;
  const tier1Max = Number(tier.tier1_max);
  const tier2Max = Number(tier.tier2_max);
  const tier3Max = Number(tier.tier3_max);
  const tier1Rate = tier.tier1_rate != null ? Number(tier.tier1_rate) : null;
  const tier2Rate = tier.tier2_rate != null ? Number(tier.tier2_rate) : null;
  const tier3Rate = tier.tier3_rate != null ? Number(tier.tier3_rate) : null;
  const tier4Rate = tier.tier4_rate != null ? Number(tier.tier4_rate) : null;

  if (cu <= 10) return total;

  if (cu <= tier1Max && tier1Rate != null) {
    const tier1Consumption = Math.min(cu - 10, tier1Max - 10);
    total += tier1Consumption * tier1Rate;
    return total;
  } else if (tier1Rate != null && Number.isFinite(tier1Max)) {
    total += (tier1Max - 10) * tier1Rate;
  }

  if (cu <= tier2Max && tier2Rate != null) {
    const tier2Consumption = Math.min(cu - tier1Max, tier2Max - tier1Max);
    total += tier2Consumption * tier2Rate;
    return total;
  } else if (tier2Rate != null && Number.isFinite(tier2Max) && Number.isFinite(tier1Max)) {
    total += (tier2Max - tier1Max) * tier2Rate;
  }

  if (cu <= tier3Max && tier3Rate != null) {
    const tier3Consumption = Math.min(cu - tier2Max, tier3Max - tier2Max);
    total += tier3Consumption * tier3Rate;
    return total;
  } else if (tier3Rate != null && Number.isFinite(tier3Max) && Number.isFinite(tier2Max)) {
    total += (tier3Max - tier2Max) * tier3Rate;
  }

  if (cu > tier3Max && tier4Rate != null && Number.isFinite(tier3Max)) {
    total += (cu - tier3Max) * tier4Rate;
  }

  return total;
}

function findPricingTier(pricingTiers, categoryId, rateCode) {
  if (!Array.isArray(pricingTiers) || categoryId == null) return null;
  const cat = String(categoryId);
  const rate = rateCode != null && String(rateCode).trim() !== ''
    ? String(rateCode).trim().toUpperCase()
    : null;

  return (
    pricingTiers.find((t) => {
      if (String(t.category_id) !== cat) return false;
      const tierRate = t.rate_code != null ? String(t.rate_code).trim().toUpperCase() : null;
      if (rate) return tierRate === rate;
      return tierRate == null || tierRate === '';
    }) || null
  );
}

/**
 * MeterReadingApiController::calculateWaterBill
 * @returns {{ bill: number, includesMeterRental: false, source: 'pricing_tiers'|'fallback' }}
 */
export function calculateWaterBill(consumption, category = null, rateCode = null, pricingTiers = null) {
  const cu = parseInt(consumption, 10);
  if (Number.isNaN(cu) || cu < 0) {
    return { bill: 0, includesMeterRental: false, source: 'fallback' };
  }

  const catId = resolveCategoryId(category);
  const tier = findPricingTier(pricingTiers, catId, rateCode);
  if (tier) {
    return {
      bill: round2(calculateBillFromPricingTier(cu, tier)),
      includesMeterRental: false,
      source: 'pricing_tiers',
    };
  }

  const classification = resolveClassification(category, rateCode);
  let waterOnly;
  if (usesResidentialMultiplier(classification)) {
    waterOnly = computeResidentialBase(cu);
  } else {
    waterOnly = computeCommercialIndustrial(cu, classification);
  }

  return {
    bill: round2(waterOnly),
    includesMeterRental: false,
    source: 'fallback',
  };
}

/** @deprecated Prefer calculateWaterBill — kept for call sites expecting calculateBill name */
export function calculateBill(consumption, categoryRaw, rateCode = null, pricingTiers = null) {
  return calculateWaterBill(consumption, categoryRaw, rateCode, pricingTiers);
}

export default {
  METER_RENTAL,
  MINIMUM_CHARGE,
  resolveCategoryId,
  resolveClassification,
  classificationMultiplier,
  computeResidentialBase,
  computeCommercialIndustrial,
  calculateBillFromPricingTier,
  calculateWaterBill,
  calculateBill,
};
