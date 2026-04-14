// Offline Mode Configuration
// This allows the app to work without database connection

export const OFFLINE_MODE = {
  enabled: false, // Set to false when you want to connect to database
  message: "App is running in offline mode - no database connection",
};

// Mock data for offline testing
export const MOCK_DATA = {
  // Mock user for testing
  user: {
    id: 1,
    name: 'Admin User',
    username: 'admin',
    email: 'admin@example.com',
    role: 'admin',
  },
  
  // Mock customers for testing
  customers: [
    {
      id: 1,
      account_number: 'ACC001',
      name: 'John Doe',
      email: 'john@example.com',
      phone: '1234567890',
      address: '123 Main St, City',
      meter_number: 'MTR001',
      status: 'active',
    },
    {
      id: 2,
      account_number: 'ACC002',
      name: 'Jane Smith',
      email: 'jane@example.com',
      phone: '0987654321',
      address: '456 Oak Ave, Town',
      meter_number: 'MTR002',
      status: 'active',
    },
  ],
  
  // Mock payments for testing
  payments: [
    {
      id: 1,
      customer_id: 1,
      payment_number: 'PAY001',
      amount: 1500.00,
      payment_method: 'gcash',
      status: 'completed',
      created_at: '2024-01-15T10:30:00Z',
    },
    {
      id: 2,
      customer_id: 2,
      payment_number: 'PAY002',
      amount: 2000.00,
      payment_method: 'palawan',
      status: 'pending',
      created_at: '2024-01-16T14:20:00Z',
    },
  ],
  
  // Mock dashboard stats
  dashboard: {
    total_customers: 2,
    total_payments: 2,
    monthly_revenue: 3500.00,
    recent_activities: [
      {
        id: 1,
        type: 'payment',
        description: 'Payment received from John Doe',
        amount: 1500.00,
        timestamp: '2024-01-15T10:30:00Z',
      },
      {
        id: 2,
        type: 'payment',
        description: 'Payment pending from Jane Smith',
        amount: 2000.00,
        timestamp: '2024-01-16T14:20:00Z',
      },
    ],
  },
};

// Offline API responses
export const getOfflineResponse = (endpoint) => {
  switch (endpoint) {
    case '/auth/login':
      return {
        success: true,
        message: 'Login successful (offline mode)',
        data: {
          user: MOCK_DATA.user,
          token: 'offline-token-12345',
        },
      };
      
    case '/auth/logout':
      return {
        success: true,
        message: 'Logout successful (offline mode)',
        data: null,
      };
      
    case '/auth/profile':
      return {
        success: true,
        message: 'Profile retrieved (offline mode)',
        data: MOCK_DATA.user,
      };
      
    case '/customers':
      return {
        success: true,
        message: 'Customers retrieved (offline mode)',
        data: MOCK_DATA.customers,
      };
      
    case '/payments':
      return {
        success: true,
        message: 'Payments retrieved (offline mode)',
        data: MOCK_DATA.payments,
      };
      
    case '/payments/mobile':
      return {
        success: true,
        message: 'Mobile payment processed (offline mode)',
        data: {
          payment_id: Math.floor(Math.random() * 1000),
          status: 'completed',
          timestamp: new Date().toISOString(),
        },
      };
      
    case '/dashboard/stats':
      return {
        success: true,
        message: 'Dashboard stats retrieved (offline mode)',
        data: MOCK_DATA.dashboard,
      };
      
    case '/dashboard/recent-activities':
      return {
        success: true,
        message: 'Recent activities retrieved (offline mode)',
        data: MOCK_DATA.dashboard.recent_activities,
      };
      
    case '/dashboard/monthly-revenue':
      return {
        success: true,
        message: 'Monthly revenue retrieved (offline mode)',
        data: {
          monthly_revenue: MOCK_DATA.dashboard.monthly_revenue,
        },
      };
      
    default:
      return {
        success: false,
        message: 'Endpoint not available in offline mode',
        data: null,
      };
  }
};

export default {
  OFFLINE_MODE,
  MOCK_DATA,
  getOfflineResponse,
};
