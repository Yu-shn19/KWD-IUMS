import { Alert } from 'react-native';
import { API_CONFIG, CURRENT_ENV, getApiConfig } from '../config/api';
import { getLocalDateYYYYMMDD } from '../utils/dateUtils';

// API Configuration
const config = getApiConfig();
const API_BASE_URL = config.baseURL;
const API_TIMEOUT = config.timeout;

// API Headers
const getHeaders = (token = null) => {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  
  return headers;
};

// Generic API request function
const apiRequest = async (endpoint, options = {}) => {
  const url = `${API_BASE_URL}${endpoint}`;
  
  const requestConfig = {
    method: 'GET',
    headers: getHeaders(options.token),
    timeout: API_TIMEOUT,
    ...options,
  };

  if (options.body) {
    requestConfig.body = JSON.stringify(options.body);
  }

  try {
    const response = await fetch(url, requestConfig);
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type');
    let data;
    
    if (contentType && contentType.includes('application/json')) {
      data = await response.json();
    } else {
      const text = await response.text();
      throw new Error(`Server returned non-JSON response: ${text.substring(0, 100)}`);
    }

    if (!response.ok) {
      throw new Error(data.message || data.error || `HTTP error! status: ${response.status}`);
    }

    return data;
  } catch (error) {
    console.error('API Error:', error);
    
    // Provide more helpful error messages
    if (error.message.includes('Network request failed') || error.message.includes('fetch')) {
      throw new Error(`Cannot connect to server. Please check:\n1. XAMPP Apache is running\n2. Laravel API is accessible at ${API_BASE_URL}\n3. Your device is on the same network`);
    }
    
    throw error;
  }
};

// Authentication API
export const authAPI = {
  // Login
  login: async (username, password) => {
    return apiRequest('/reader/login', {
      method: 'POST',
      body: { username, password }
    });
  },

  // Logout
  logout: async (token) => {
    return apiRequest('/auth/logout', {
      method: 'POST',
      token
    });
  },

  // Get user profile
  getProfile: async (token) => {
    return apiRequest('/auth/profile', {
      method: 'GET',
      token
    });
  },
};

// Customer API
export const customerAPI = {
  // Get all customers
  getCustomers: async (token) => {
    return apiRequest('/customers', {
      method: 'GET',
      token
    });
  },

  // Get customer by ID
  getCustomer: async (id, token) => {
    return apiRequest(`/customers/${id}`, {
      method: 'GET',
      token
    });
  },

  // Create new customer
  createCustomer: async (customerData, token) => {
    return apiRequest('/customers', {
      method: 'POST',
      body: customerData,
      token
    });
  },

  // Update customer
  updateCustomer: async (id, customerData, token) => {
    return apiRequest(`/customers/${id}`, {
      method: 'PUT',
      body: customerData,
      token
    });
  },

  // Delete customer
  deleteCustomer: async (id, token) => {
    return apiRequest(`/customers/${id}`, {
      method: 'DELETE',
      token
    });
  },
};

// Billing API
export const billingAPI = {
  // Get all bills
  getBills: async (token) => {
    return apiRequest('/bills', {
      method: 'GET',
      token
    });
  },

  // Get bill by ID
  getBill: async (id, token) => {
    return apiRequest(`/bills/${id}`, {
      method: 'GET',
      token
    });
  },

  // Create new bill
  createBill: async (billData, token) => {
    return apiRequest('/bills', {
      method: 'POST',
      body: billData,
      token
    });
  },

  // Update bill
  updateBill: async (id, billData, token) => {
    return apiRequest(`/bills/${id}`, {
      method: 'PUT',
      body: billData,
      token
    });
  },

  // Delete bill
  deleteBill: async (id, token) => {
    return apiRequest(`/bills/${id}`, {
      method: 'DELETE',
      token
    });
  },

  // Get customer bills
  getCustomerBills: async (customerId, token) => {
    return apiRequest(`/customers/${customerId}/bills`, {
      method: 'GET',
      token
    });
  },
};

// Payment API
export const paymentAPI = {
  // Get all payments
  getPayments: async (token) => {
    return apiRequest('/payments', {
      method: 'GET',
      token
    });
  },

  // Get payment by ID
  getPayment: async (id, token) => {
    return apiRequest(`/payments/${id}`, {
      method: 'GET',
      token
    });
  },

  // Create new payment
  createPayment: async (paymentData, token) => {
    return apiRequest('/payments', {
      method: 'POST',
      body: paymentData,
      token
    });
  },

  // Process mobile payment
  processMobilePayment: async (paymentData, token) => {
    return apiRequest('/payments/mobile', {
      method: 'POST',
      body: paymentData,
      token
    });
  },

  // Get payment history
  getPaymentHistory: async (customerId, token) => {
    return apiRequest(`/customers/${customerId}/payments`, {
      method: 'GET',
      token
    });
  },
};

// Meter Reading API
export const meterReadingAPI = {
  // Get all meter readings
  getMeterReadings: async (token) => {
    return apiRequest('/meter-readings', {
      method: 'GET',
      token
    });
  },

  // Get meter reading by ID
  getMeterReading: async (id, token) => {
    return apiRequest(`/meter-readings/${id}`, {
      method: 'GET',
      token
    });
  },

  // Create new meter reading
  createMeterReading: async (readingData, token) => {
    return apiRequest('/meter-readings', {
      method: 'POST',
      body: readingData,
      token
    });
  },

  // Update meter reading
  updateMeterReading: async (id, readingData, token) => {
    return apiRequest(`/meter-readings/${id}`, {
      method: 'PUT',
      body: readingData,
      token
    });
  },

  // Get customer meter readings
  getCustomerMeterReadings: async (customerId, token) => {
    return apiRequest(`/customers/${customerId}/meter-readings`, {
      method: 'GET',
      token
    });
  },
};

// Dashboard API
export const dashboardAPI = {
  // Get dashboard statistics
  getDashboardStats: async (token) => {
    return apiRequest('/dashboard/stats', {
      method: 'GET',
      token
    });
  },

  // Get recent activities
  getRecentActivities: async (token) => {
    return apiRequest('/dashboard/recent-activities', {
      method: 'GET',
      token
    });
  },

  // Get monthly revenue
  getMonthlyRevenue: async (token) => {
    return apiRequest('/dashboard/monthly-revenue', {
      method: 'GET',
      token
    });
  },
};

// Routes API (uploaded by admin from the website)
export const routesAPI = {
  // Get list of route customers for a reader
  getRoutes: async (params = {}, token) => {
    const query = new URLSearchParams(params).toString();
    const endpoint = `/reader/schedules${query ? `?${query}` : ''}`;

    return apiRequest(endpoint, {
      method: 'GET',
      token
    });
  },
  // Import/upload routes (JSON array) from web admin or app
  importRoutes: async (routesArray, token) => {
    return apiRequest('/routes/import', {
      method: 'POST',
      body: { routes: routesArray },
      token
    });
  },
  // Get consumer zone data including latitude/longitude by account number
  // This queries the consumer_zone table which is the source of truth for GPS coordinates
  getConsumerZoneByAccount: async (accountNumber, token) => {
    try {
      if (!accountNumber || accountNumber === '-' || accountNumber === 'N/A' || accountNumber === 'undefined') {
        console.warn('Invalid account number:', accountNumber);
        return null;
      }

      // Clean and normalize account number
      // Account numbers in consumer_zone are like "011-32-001" (with dashes)
      // Routes might have account_number without dashes or with different format
      let cleanAccountNo = String(accountNumber).trim();
      
      // Try multiple account number formats
      const accountVariations = [
        cleanAccountNo, // Original format
        cleanAccountNo.replace(/-/g, ''), // Remove dashes
        cleanAccountNo.replace(/\s/g, ''), // Remove spaces
      ];
      
      // Add formatted version if it looks like it might need dashes
      // e.g., "01132001" -> "011-32-001" (if 9+ digits)
      if (/^\d{9,}$/.test(cleanAccountNo.replace(/-/g, ''))) {
        const digits = cleanAccountNo.replace(/-/g, '');
        if (digits.length >= 9) {
          accountVariations.push(`${digits.substring(0, 3)}-${digits.substring(3, 5)}-${digits.substring(5)}`);
        }
      }

      console.log('Trying account number variations:', accountVariations);

      // Reader-authenticated API (see routes/api.php): GET /api/consumer/zone?account_no=
      for (const accountVar of accountVariations) {
        try {
          console.log(`🔍 Trying /consumer/zone endpoint with account_no=${accountVar}`);
          console.log(`🔗 Full URL will be: ${API_BASE_URL}/consumer/zone?account_no=${accountVar}`);
          
          const response = await apiRequest(`/consumer/zone?account_no=${encodeURIComponent(accountVar)}`, {
            method: 'GET',
            token
          });
          
          console.log('📥 Response from /consumer/zone:', JSON.stringify(response, null, 2));
          
          if (response?.consumer) {
            const consumer = response.consumer;
            const lat = parseFloat(consumer.latitude);
            const lng = parseFloat(consumer.longitude);
            
            console.log('📍 Parsed coordinates:', { lat, lng, rawLat: consumer.latitude, rawLng: consumer.longitude });
            
            if (consumer && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
              console.log('✅ Found consumer_zone via direct endpoint:', {
                account_no: consumer.account_no,
                searched_with: accountVar,
                latitude: lat,
                longitude: lng
              });
              return consumer;
            } else {
              console.warn('⚠️ Invalid coordinates in response:', { lat, lng, consumer });
            }
          } else {
            console.warn('⚠️ No consumer in response. Response structure:', Object.keys(response || {}));
            console.warn('⚠️ Full response:', response);
          }
        } catch (directError) {
          console.error(`❌ Direct endpoint failed for ${accountVar}:`, directError.message);
          console.error('❌ Error details:', directError);
          // Continue to next variation
          continue;
        }
      }

      console.warn('⚠️ Could not fetch consumer_zone data for account:', cleanAccountNo, 'Tried variations:', accountVariations);
      console.warn('⚠️ Ensure GET /api/consumer/zone is deployed and returns latitude/longitude for this account.');
      return null;
    } catch (error) {
      console.error('❌ Error fetching consumer zone data:', error);
      return null;
    }
  },
};

// Consumer API (suggestions for Edit Billing / account search)
export const consumerAPI = {
  getSuggestions: async (q, token) => {
    if (!q || String(q).trim().length < 2) return { success: false, data: [] };
    const response = await apiRequest(`/consumer/suggestions?q=${encodeURIComponent(String(q).trim())}`, {
      method: 'GET',
      token
    });
    return response || { success: false, data: [] };
  },

  getBillingSummary: async (accountNumber, token) => {
    if (!accountNumber || String(accountNumber).trim().length === 0) return { success: false, data: null };
    const response = await apiRequest(`/consumer/billing-summary?account_number=${encodeURIComponent(String(accountNumber).trim())}`, {
      method: 'GET',
      token
    });
    return response || { success: false, data: null };
  },

  saveLroLedger: async (payload, token) => {
    return apiRequest('/lro-ledger', {
      method: 'POST',
      body: payload,
      token
    });
  },

  saveConsumerCoordinates: async (accountNo, latitude, longitude, token) => {
    return apiRequest('/consumer/coordinates', {
      method: 'POST',
      body: { account_no: accountNo, latitude, longitude },
      token
    });
  },
};

// Reader – downloaded_readings (zones and reading_date for logged-in reader)
export const readerDownloadedReadingsAPI = {
  // Get distinct zones and reading_dates for the logged-in reader (from downloaded_readings table)
  // If readingDate is provided, returns only zones that have data on that date (previous zones assigned on that date).
  getFilters: async (readerId, token, readingDate = null) => {
    if (!readerId) return { zones: [], reading_dates: [] };
    const params = new URLSearchParams({ reader_id: readerId });
    if (readingDate) params.set('reading_date', readingDate);
    const response = await apiRequest(
      `/reader/downloaded-readings/filters?${params.toString()}`,
      { method: 'GET', token }
    );
    return {
      zones: response?.zones ?? [],
      reading_dates: response?.reading_dates ?? [],
    };
  },

  // Get downloaded_readings list for reader + zone + reading_date
  getList: async (readerId, zone, readingDate, token) => {
    if (!readerId) return { data: [] };
    const params = new URLSearchParams({ reader_id: readerId });
    if (zone) params.set('zone', zone);
    if (readingDate) params.set('reading_date', readingDate);
    const response = await apiRequest(
      `/reader/downloaded-readings?${params.toString()}`,
      { method: 'GET', token }
    );
    return { data: response?.data ?? response ?? [] };
  },

  // Adjust reading (only for latest month). Uses same submit-reading endpoint.
  updateReading: async (readerId, scheduleId, currentReading, readingDate, token) => {
    const response = await apiRequest('/reader/submit-reading', {
      method: 'POST',
      token,
      body: {
        schedule_id: scheduleId,
        current_reading: parseInt(currentReading, 10),
        reader_id: readerId,
        reading_date: readingDate || getLocalDateYYYYMMDD(),
      },
    });
    return response;
  },
};

// Disconnector API
export const disconnectorAPI = {
  // Fetch assigned disconnection tasks for a disconnector
  getAssignments: async (params = {}, token) => {
    const query = new URLSearchParams(params).toString();
    const endpoint = `/disconnector/assignments${query ? `?${query}` : ''}`;

    return apiRequest(endpoint, {
      method: 'GET',
      token
    });
  },

  // Fetch disconnection orders payload (contains DB-backed financial fields like last_reading)
  getOrders: async (params = {}, token) => {
    const query = new URLSearchParams(params).toString();
    const endpoint = `/disconnector/orders${query ? `?${query}` : ''}`;

    return apiRequest(endpoint, {
      method: 'GET',
      token
    });
  },

  // Fetch AR aging buckets per account for disconnector UI
  getArAgingSummary: async (params = {}, token) => {
    const query = new URLSearchParams(params).toString();
    const endpoint = `/disconnector/ar-aging-summary${query ? `?${query}` : ''}`;
    const url = `${API_BASE_URL}${endpoint}`;

    // Use direct fetch here so missing backend route (404) does not spam API Error logs.
    const response = await fetch(url, {
      method: 'GET',
      headers: getHeaders(token),
    });

    if (response.status === 404) {
      return { success: false, unavailable: true, data: [] };
    }

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      return { success: false, unavailable: true, data: [] };
    }

    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.message || data.error || `HTTP error! status: ${response.status}`);
    }

    return data;
  },

  // Update assignment status (e.g., mark as completed/reconnected)
  updateAssignmentStatus: async (payload = {}, token) => {
    const postStatus = async (baseUrl) => {
      const url = `${baseUrl}/disconnector/assignments/status`;
      const response = await fetch(url, {
        method: 'POST',
        headers: getHeaders(token),
        body: JSON.stringify(payload),
      });

      const contentType = response.headers.get('content-type') || '';
      const data = contentType.includes('application/json')
        ? await response.json()
        : { success: false, message: `HTTP ${response.status}` };

      if (!response.ok) {
        return {
          success: false,
          status: response.status,
          message: data?.message || data?.error || `HTTP error! status: ${response.status}`,
          error: data,
        };
      }

      return data;
    };

    // Primary write (current environment API).
    const primary = await postStatus(API_BASE_URL);
    if (!primary || primary.success === false) return primary;

    // In development testing, mirror the same disconnect/reconnect status to main system API.
    // This keeps test flow while ensuring the main system reflects the action.
    const mainBaseUrl = API_CONFIG.xampp?.baseURL || API_CONFIG.production?.baseURL;
    const shouldMirrorToMain =
      CURRENT_ENV === 'development' &&
      mainBaseUrl &&
      mainBaseUrl !== API_BASE_URL;

    if (shouldMirrorToMain) {
      const mirror = await postStatus(mainBaseUrl);
      if (!mirror || mirror.success === false) {
        return {
          success: false,
          message: `Updated in development, but failed to update main system: ${mirror?.message || 'Unknown error'}`,
          mirror_error: mirror,
          primary,
        };
      }
      return {
        ...primary,
        mirrored_to_main: true,
      };
    }

    return primary;
  },

  // Permanently clear active assignments for the current disconnector on server.
  clearAllAssignments: async (payload = {}, token) => {
    const disconnectorId = payload?.disconnector_id;
    const url = `${API_BASE_URL}/disconnector/assignments/clear-all`;
    const response = await fetch(url, {
      method: 'POST',
      headers: getHeaders(token),
      body: JSON.stringify(payload),
    });

    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('application/json')
      ? await response.json()
      : { success: false, message: `HTTP ${response.status}` };

    if (response.ok) {
      return data;
    }

    // Backward-compatible fallback when clear-all route is not yet deployed.
    if (response.status === 404 && disconnectorId) {
      const ordersRes = await disconnectorAPI.getOrders({ disconnector_id: disconnectorId }, token);
      const orders = Array.isArray(ordersRes?.orders) ? ordersRes.orders : [];
      const activeOrders = orders.filter((o) => {
        const st = (o?.status || '').toString().trim().toLowerCase();
        return st === 'pending' || st === 'assigned' || st === 'in-progress' || st === 'in progress';
      });
      let cleared = 0;

      for (const item of activeOrders) {
        const orderId = item?.order_id ?? item?.orderId ?? item?.id;
        if (!orderId) continue;
        const result = await disconnectorAPI.updateAssignmentStatus(
          { order_id: orderId, status: 'cancelled', notes: 'Cleared by disconnector from mobile app' },
          token
        );
        if (result && result.success !== false) cleared += 1;
      }

      return {
        success: true,
        message: 'Assignments cleared via fallback mode.',
        cleared_count: cleared,
        fallback_used: true,
      };
    }

    return {
      success: false,
      status: response.status,
      message: data?.message || data?.error || `HTTP error! status: ${response.status}`,
      error: data,
    };
  },

  // Update consumer zone status code
  updateConsumerZoneStatus: async (accountNumber, statusCode, token, accountName = null) => {
    console.log('Updating consumer zone status:', { accountNumber, statusCode, accountName });

    if (!accountNumber || accountNumber === 'N/A' || accountNumber === 'undefined' || accountNumber === 'null') {
      throw new Error('Cannot update consumer zone status: missing account number.');
    }

    // Existing backend route in routes/api.php:
    // POST /api/reader/update-status
    return apiRequest('/reader/update-status', {
      method: 'POST',
      body: {
        account_number: accountNumber,
        account_no: accountNumber,
        status_code: statusCode,
      },
      token,
    });
  },

  // Fetch orders cancelled because consumer paid (do not disconnect these)
  getCancelledDueToPayment: async (params = {}, token) => {
    const query = new URLSearchParams(params).toString();
    const endpoint = `/disconnector/cancelled-due-to-payment${query ? `?${query}` : ''}`;
    return apiRequest(endpoint, { method: 'GET', token });
  },
};

// Error handler
export const handleAPIError = (error, customMessage = null) => {
  const message = customMessage || error.message || 'An error occurred. Please try again.';
  
  Alert.alert('Error', message);
  
  return {
    success: false,
    message,
    error
  };
};

// Success handler
export const handleAPISuccess = (data, message = 'Operation completed successfully') => {
  Alert.alert('Success', message);
  
  return {
    success: true,
    message,
    data
  };
};

export default {
  authAPI,
  customerAPI,
  billingAPI,
  paymentAPI,
  meterReadingAPI,
  dashboardAPI,
  routesAPI,
  readerDownloadedReadingsAPI,
  disconnectorAPI,
  handleAPIError,
  handleAPISuccess
};
