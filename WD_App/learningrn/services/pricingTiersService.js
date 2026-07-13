/**
 * Cache pricing tiers for offline ReadAndBill (matches /api/pricing-tiers).
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { pricingTiersAPI } from './api';
import { tokenStorage } from './storage';

const CACHE_KEY = 'pricing_tiers_cache_v1';

export async function getCachedPricingTiers() {
  try {
    const raw = await AsyncStorage.getItem(CACHE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (_) {
    return [];
  }
}

export async function saveCachedPricingTiers(tiers) {
  try {
    await AsyncStorage.setItem(CACHE_KEY, JSON.stringify(Array.isArray(tiers) ? tiers : []));
    return true;
  } catch (e) {
    console.error('Error caching pricing tiers:', e);
    return false;
  }
}

/** Fetch from API when online; fall back to cache. */
export async function loadPricingTiers() {
  const cached = await getCachedPricingTiers();
  try {
    const token = await tokenStorage.getToken();
    const res = await pricingTiersAPI.getAll(token);
    const tiers = Array.isArray(res?.data) ? res.data : [];
    if (tiers.length > 0) {
      await saveCachedPricingTiers(tiers);
      return tiers;
    }
  } catch (e) {
    console.warn('pricing tiers fetch failed, using cache:', e?.message || e);
  }
  return cached;
}

export default {
  getCachedPricingTiers,
  saveCachedPricingTiers,
  loadPricingTiers,
};
