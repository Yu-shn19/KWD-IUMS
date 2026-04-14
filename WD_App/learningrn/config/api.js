export const API_CONFIG = {
  
  development: {
    baseURL: 'http://localhost:8000/api',
    timeout: 10000,
  },
  
  production: {
    baseURL: 'https://hagonoywaterdistrict.com/api',
    timeout: 10000,
  },
  
  xampp: {
    baseURL: 'https://hagonoywaterdistrict.com/api',
    timeout: 10000,
  },
  
  artisan: {
    baseURL: 'https://hagonoywaterdistrict.com/api', 
    timeout: 10000,
  },
};

export const CURRENT_ENV = 'production'; 


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
