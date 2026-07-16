export const API_CONFIG = {
  // Remote main system
  development: {
    baseURL: 'http://187.127.206.191/api',
    timeout: 20000,
  },

  production: {
    baseURL: 'https://hagonoywaterdistrict.com/api',
    timeout: 15000,
  },

  // Local php artisan serve (PC LAN IP)
  artisan: {
    baseURL: 'http://192.168.1.8:8000/api',
    timeout: 15000,
  },

  // Android emulator → host machine
  android_emulator: {
    baseURL: 'http://10.0.2.2:8000/api',
    timeout: 15000,
  },
};

// Active API for the app
export const CURRENT_ENV = 'development';

export const getApiConfig = () => {
  return API_CONFIG[CURRENT_ENV];
};

export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/reader/login',
    LOGOUT: '/auth/logout',
    PROFILE: '/auth/profile',
    REGISTER: '/auth/register',
  },

  CUSTOMERS: {
    LIST: '/customers',
    SHOW: (id) => `/customers/${id}`,
    CREATE: '/customers',
    UPDATE: (id) => `/customers/${id}`,
    DELETE: (id) => `/customers/${id}`,
    BILLS: (id) => `/customers/${id}/bills`,
    PAYMENTS: (id) => `/customers/${id}/payments`,
    METER_READINGS: (id) => `/customers/${id}/meter-readings`,
  },

  BILLS: {
    LIST: '/bills',
    SHOW: (id) => `/bills/${id}`,
    CREATE: '/bills',
    UPDATE: (id) => `/bills/${id}`,
    DELETE: (id) => `/bills/${id}`,
  },

  PAYMENTS: {
    LIST: '/payments',
    SHOW: (id) => `/payments/${id}`,
    CREATE: '/payments',
    MOBILE: '/payments/mobile',
  },

  METER_READINGS: {
    LIST: '/meter-readings',
    SHOW: (id) => `/meter-readings/${id}`,
    CREATE: '/meter-readings',
    UPDATE: (id) => `/meter-readings/${id}`,
  },

  DASHBOARD: {
    STATS: '/dashboard/stats',
    RECENT_ACTIVITIES: '/dashboard/recent-activities',
    MONTHLY_REVENUE: '/dashboard/monthly-revenue',
  },
};

export default {
  API_CONFIG,
  CURRENT_ENV,
  getApiConfig,
  API_ENDPOINTS,
};
