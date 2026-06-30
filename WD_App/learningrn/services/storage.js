import AsyncStorage from '@react-native-async-storage/async-storage';
import * as routesLocalService from './routesLocalService';

// Storage Keys
const STORAGE_KEYS = {
  AUTH_TOKEN: 'auth_token',
  USER_DATA: 'user_data',
  REMEMBER_ME: 'remember_me',
  LAST_LOGIN: 'last_login',
  DISCONNECTOR_PAID_LIST: 'disconnector_paid_list',
  RECEIPT_LAST: 'receipt_last',
  SELECTED_PRINTER: 'selected_printer',
  RECEIPT_FORMAT: 'receipt_format',
  RECEIPT_LOGO: 'receipt_logo',
};

// Token Management
export const tokenStorage = {
  // Save authentication token
  saveToken: async (token) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.AUTH_TOKEN, token);
      return true;
    } catch (error) {
      console.error('Error saving token:', error);
      return false;
    }
  },

  // Get authentication token
  getToken: async () => {
    try {
      const token = await AsyncStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
      return token;
    } catch (error) {
      console.error('Error getting token:', error);
      return null;
    }
  },

  // Remove authentication token
  removeToken: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
      return true;
    } catch (error) {
      console.error('Error removing token:', error);
      return false;
    }
  },

  // Check if user is authenticated
  isAuthenticated: async () => {
    try {
      const token = await AsyncStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
      return !!token;
    } catch (error) {
      console.error('Error checking authentication:', error);
      return false;
    }
  },
};

// User Data Management
export const userStorage = {
  // Save user data
  saveUserData: async (userData) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.USER_DATA, JSON.stringify(userData));
      return true;
    } catch (error) {
      console.error('Error saving user data:', error);
      return false;
    }
  },

  // Get user data
  getUserData: async () => {
    try {
      const userData = await AsyncStorage.getItem(STORAGE_KEYS.USER_DATA);
      return userData ? JSON.parse(userData) : null;
    } catch (error) {
      console.error('Error getting user data:', error);
      return null;
    }
  },

  // Remove user data
  removeUserData: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.USER_DATA);
      return true;
    } catch (error) {
      console.error('Error removing user data:', error);
      return false;
    }
  },

  // Update user data
  updateUserData: async (newData) => {
    try {
      const currentData = await userStorage.getUserData();
      const updatedData = { ...currentData, ...newData };
      await AsyncStorage.setItem(STORAGE_KEYS.USER_DATA, JSON.stringify(updatedData));
      return true;
    } catch (error) {
      console.error('Error updating user data:', error);
      return false;
    }
  },
};

// Remember Me Management
export const rememberMeStorage = {
  // Save remember me preference
  saveRememberMe: async (remember) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.REMEMBER_ME, JSON.stringify(remember));
      return true;
    } catch (error) {
      console.error('Error saving remember me:', error);
      return false;
    }
  },

  // Get remember me preference
  getRememberMe: async () => {
    try {
      const remember = await AsyncStorage.getItem(STORAGE_KEYS.REMEMBER_ME);
      return remember ? JSON.parse(remember) : false;
    } catch (error) {
      console.error('Error getting remember me:', error);
      return false;
    }
  },

  // Remove remember me preference
  removeRememberMe: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.REMEMBER_ME);
      return true;
    } catch (error) {
      console.error('Error removing remember me:', error);
      return false;
    }
  },
};

// Last Login Management
export const lastLoginStorage = {
  // Save last login time
  saveLastLogin: async () => {
    try {
      const now = new Date().toISOString();
      await AsyncStorage.setItem(STORAGE_KEYS.LAST_LOGIN, now);
      return true;
    } catch (error) {
      console.error('Error saving last login:', error);
      return false;
    }
  },

  // Get last login time
  getLastLogin: async () => {
    try {
      const lastLogin = await AsyncStorage.getItem(STORAGE_KEYS.LAST_LOGIN);
      return lastLogin;
    } catch (error) {
      console.error('Error getting last login:', error);
      return null;
    }
  },

  // Remove last login time
  removeLastLogin: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.LAST_LOGIN);
      return true;
    } catch (error) {
      console.error('Error removing last login:', error);
      return false;
    }
  },
};

// Disconnector: consumers who already paid (do not disconnect) – cached for offline
export const disconnectorPaidStorage = {
  savePaidList: async (list) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.DISCONNECTOR_PAID_LIST, JSON.stringify(Array.isArray(list) ? list : []));
      return true;
    } catch (error) {
      console.error('Error saving disconnector paid list:', error);
      return false;
    }
  },
  getPaidList: async () => {
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEYS.DISCONNECTOR_PAID_LIST);
      return raw ? JSON.parse(raw) : [];
    } catch (error) {
      console.error('Error getting disconnector paid list:', error);
      return [];
    }
  },
};

/** Reader meter routes (default bucket). Disconnector uses ROUTES_BUCKET_DISCONNECTOR. */
export const ROUTES_BUCKET_READER = routesLocalService.ROUTES_BUCKET_READER;
export const ROUTES_BUCKET_DISCONNECTOR = routesLocalService.ROUTES_BUCKET_DISCONNECTOR;

// Routes list: SQLite `routes_cache` table (same DB as pending readings). Legacy AsyncStorage migrates once.
export const routesStorage = {
  saveRoutes: async (routes, bucket = ROUTES_BUCKET_READER) => {
    try {
      await routesLocalService.saveRoutes(bucket, routes);
      return true;
    } catch (error) {
      console.error('Error saving routes list:', error);
      return false;
    }
  },
  getRoutes: async (bucket = ROUTES_BUCKET_READER) => {
    try {
      return await routesLocalService.getRoutes(bucket);
    } catch (error) {
      console.error('Error getting routes list:', error);
      return [];
    }
  },
  clearRoutes: async (bucket = ROUTES_BUCKET_READER) => {
    try {
      await routesLocalService.clearRoutes(bucket);
      return true;
    } catch (error) {
      console.error('Error clearing routes list:', error);
      return false;
    }
  },
};

// Receipt storage (for viewing last generated receipt in app)
export const receiptStorage = {
  saveLastReceipt: async (receipt) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.RECEIPT_LAST, JSON.stringify(receipt || {}));
      return true;
    } catch (error) {
      console.error('Error saving last receipt:', error);
      return false;
    }
  },
  getLastReceipt: async () => {
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEYS.RECEIPT_LAST);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      console.error('Error getting last receipt:', error);
      return null;
    }
  },
  clearLastReceipt: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.RECEIPT_LAST);
      return true;
    } catch (error) {
      console.error('Error clearing last receipt:', error);
      return false;
    }
  }
};

// Printer storage (for one-time pairing)
export const printerStorage = {
  savePrinter: async (printer) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.SELECTED_PRINTER, JSON.stringify(printer || {}));
      return true;
    } catch (error) {
      console.error('Error saving printer:', error);
      return false;
    }
  },
  getPrinter: async () => {
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEYS.SELECTED_PRINTER);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      console.error('Error getting printer:', error);
      return null;
    }
  },
  clearPrinter: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.SELECTED_PRINTER);
      return true;
    } catch (error) {
      console.error('Error clearing printer:', error);
      return false;
    }
  }
};

// Receipt format storage
export const receiptFormatStorage = {
  saveFormat: async (format) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.RECEIPT_FORMAT, JSON.stringify(format || {}));
      return true;
    } catch (error) {
      console.error('Error saving receipt format:', error);
      return false;
    }
  },
  getFormat: async () => {
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEYS.RECEIPT_FORMAT);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      console.error('Error getting receipt format:', error);
      return null;
    }
  },
  clearFormat: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.RECEIPT_FORMAT);
      return true;
    } catch (error) {
      console.error('Error clearing receipt format:', error);
      return false;
    }
  }
};

// Receipt logo storage
export const receiptLogoStorage = {
  saveLogo: async (logoUri) => {
    try {
      await AsyncStorage.setItem(STORAGE_KEYS.RECEIPT_LOGO, logoUri || '');
      return true;
    } catch (error) {
      console.error('Error saving receipt logo:', error);
      return false;
    }
  },
  getLogo: async () => {
    try {
      const logoUri = await AsyncStorage.getItem(STORAGE_KEYS.RECEIPT_LOGO);
      return logoUri || null;
    } catch (error) {
      console.error('Error getting receipt logo:', error);
      return null;
    }
  },
  clearLogo: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.RECEIPT_LOGO);
      return true;
    } catch (error) {
      console.error('Error clearing receipt logo:', error);
      return false;
    }
  }
};

// Clear all data (logout)
export const clearAllData = async () => {
  try {
    await AsyncStorage.multiRemove([
      STORAGE_KEYS.AUTH_TOKEN,
      STORAGE_KEYS.USER_DATA,
      STORAGE_KEYS.LAST_LOGIN,
    ]);
    return true;
  } catch (error) {
    console.error('Error clearing all data:', error);
    return false;
  }
};

// Get all stored data (for debugging)
export const getAllStoredData = async () => {
  try {
    const keys = await AsyncStorage.getAllKeys();
    const data = await AsyncStorage.multiGet(keys);
    return data;
  } catch (error) {
    console.error('Error getting all stored data:', error);
    return [];
  }
};

export default {
  tokenStorage,
  userStorage,
  rememberMeStorage,
  lastLoginStorage,
  routesStorage,
  receiptStorage,
  printerStorage,
  receiptFormatStorage,
  receiptLogoStorage,
  clearAllData,
  getAllStoredData,
};
