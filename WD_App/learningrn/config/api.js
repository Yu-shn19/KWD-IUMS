export const API_CONFIG = {
  
  development: {
    baseURL: 'https://teschies.com/api',
    timeout: 10000,
  },
  
  production: {
    baseURL: 'https://hagonoywaterdistrict.com/api',
    timeout: 10000,
  },
  
  // Laragon Apache (virtual host / http://kwd-iums.test or similar)
  laragon: {
    baseURL: 'http://127.0.0.1/api',
    timeout: 10000,
  },
  
  // php artisan serve on this PC — use LAN IP so phones/emulators can reach it
  artisan: {
    baseURL: 'http://192.168.1.8:8000/api',
    timeout: 15000,
  },

  // Android emulator → host machine localhost
  android_emulator: {
    baseURL: 'http://10.0.2.2:8000/api',
    timeout: 15000,
  },
};

// Point WD_App at the local Laravel main system (artisan serve)
export const CURRENT_ENV = 'artisan';


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
