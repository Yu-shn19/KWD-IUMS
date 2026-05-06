# Mobile App API Documentation
## Water District Meter Reading System

### Overview
This API allows the React Native mobile app to receive meter reading schedules assigned to readers and submit meter readings back to the system.

---

## Base URL
```
Production: https://your-domain.com/api
Development: http://localhost:8000/api
```

---

## Authentication

⚠️ **IMPORTANT: All API endpoints (except login and test) require authentication!**

### 1. Reader Login
**Endpoint:** `POST /reader/login`

**Description:** Readers MUST login first to get an authentication token. This token is required for all subsequent API calls.

**Request Body:**
```json
{
  "email": "reader@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "token": "NToxNzMwNjE2MDY0",
  "user": {
    "id": 5,
    "name": "DELA CRUZ, JUAN M.",
    "email": "reader@example.com",
    "role": "reader"
  }
}
```

**Error Responses:**

**401 - Invalid Credentials:**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**403 - Not a Reader:**
```json
{
  "success": false,
  "message": "Access denied. Only readers can use this app."
}
```

### Token Information

- **Format:** Base64 encoded string containing `{user_id}:{timestamp}`
- **Expiration:** 24 hours from generation
- **Storage:** Must be stored securely in mobile app (AsyncStorage/SecureStore)
- **Usage:** Include in all authenticated requests

### How to Use Token

**Option 1: Authorization Header (Recommended)**
```javascript
fetch('http://your-domain.com/api/reader/schedules?reader_id=5', {
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  }
});
```

**Option 2: Query Parameter**
```javascript
fetch('http://your-domain.com/api/reader/schedules?reader_id=5&api_token=' + token);
```

---

## Meter Reading Schedules

### 2. Get Assigned Schedules
**Endpoint:** `GET /reader/schedules`

**Query Parameters:**
- `reader_id` (required) - The reader's user ID
- `zone` (optional) - Filter by specific zone
- `bill_month` (optional) - Filter by bill month (YYYY-MM-DD)

**Example Request:**
```
GET /api/reader/schedules?reader_id=5&zone=031&bill_month=2025-08-01
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Schedules retrieved successfully",
  "reader": {
    "id": 5,
    "name": "DELA CRUZ, JUAN M."
  },
  "total_schedules": 25,
  "schedules": [
    {
      "id": 1,
      "sedr_number": 1,
      "account_number": "031-12-0001",
      "account_name": "Santos, Maria T.",
      "address": "Purok 1, Barangay Centro",
      "zone": "031",
      "category": "Residential",
      "meter_number": "MTR-001",
      "previous_reading": 150,
      "previous_reading_date": "2025-07-01",
      "current_reading": null,
      "reading_date": null,
      "consumption": 0,
      "bill_month": "2025-08-01",
      "bill_date": "2025-08-02",
      "due_date": "2025-08-15",
      "status": "Assigned",
      "reader_notes": null
    }
    // ... more schedules
  ]
}
```

---

### 3. Submit Meter Reading
**Endpoint:** `POST /reader/submit-reading`

**Request Body:**
```json
{
  "schedule_id": 1,
  "reader_id": 5,
  "current_reading": 165,
  "reading_date": "2025-08-10",
  "reader_notes": "Meter is accessible, no issues"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Meter reading submitted successfully",
  "schedule": {
    "id": 1,
    "account_number": "031-12-0001",
    "current_reading": 165,
    "consumption": 15,
    "status": "Completed"
  }
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "You are not authorized to update this schedule"
}
```

---

### 4. Update Schedule Status
**Endpoint:** `POST /reader/update-status`

**Request Body:**
```json
{
  "schedule_id": 1,
  "reader_id": 5,
  "status": "In Progress"
}
```

**Allowed Status Values:**
- `Assigned` - Not yet started
- `In Progress` - Reader is working on it
- `Completed` - Reading submitted

**Success Response (200):**
```json
{
  "success": true,
  "message": "Status updated successfully",
  "schedule": {
    "id": 1,
    "status": "In Progress"
  }
}
```

---

### 5. Get Reader Statistics
**Endpoint:** `GET /reader/stats`

**Query Parameters:**
- `reader_id` (required) - The reader's user ID

**Example Request:**
```
GET /api/reader/stats?reader_id=5
```

**Success Response (200):**
```json
{
  "success": true,
  "stats": {
    "total_assigned": 50,
    "pending": 20,
    "in_progress": 15,
    "completed": 15
  }
}
```

---

## Error Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 401 | Unauthorized (invalid credentials) |
| 403 | Forbidden (not a reader or not authorized) |
| 404 | Not found (no schedules found) |
| 422 | Validation error |
| 500 | Server error |

---

## Mobile App Workflow

### Step 1: Login (REQUIRED)
**🔐 Readers MUST login to get authentication token**

```javascript
// React Native Example
const login = async (email, password) => {
  const response = await fetch('http://your-domain.com/api/reader/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  if (data.success) {
    // ✅ IMPORTANT: Store token securely
    await AsyncStorage.setItem('authToken', data.token);
    await AsyncStorage.setItem('userId', data.user.id.toString());
    await AsyncStorage.setItem('userName', data.user.name);
    await AsyncStorage.setItem('userEmail', data.user.email);
    
    console.log('✅ Login successful!');
    console.log('Token:', data.token);
    console.log('Reader:', data.user.name);
  } else {
    console.error('❌ Login failed:', data.message);
  }
  return data;
};
```

### Step 2: Download Schedules (REQUIRES TOKEN)
**⚠️ All subsequent calls must include the authentication token**

```javascript
const downloadSchedules = async (readerId) => {
  // Get stored token
  const token = await AsyncStorage.getItem('authToken');
  
  if (!token) {
    console.error('No token found. Please login first.');
    return { success: false, message: 'Not authenticated' };
  }

  const response = await fetch(
    `http://your-domain.com/api/reader/schedules?reader_id=${readerId}`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,  // ✅ Include token
        'Content-Type': 'application/json'
      }
    }
  );
  
  const data = await response.json();
  
  if (data.success) {
    // Store schedules locally for offline access
    await AsyncStorage.setItem('schedules', JSON.stringify(data.schedules));
    console.log('✅ Downloaded', data.total_schedules, 'schedules');
  } else {
    console.error('❌ Download failed:', data.message);
    
    // Check if token expired
    if (response.status === 401) {
      console.error('Token expired. Redirecting to login...');
      // Navigate to login screen
    }
  }
  return data;
};
```

### Step 3: Submit Reading (REQUIRES TOKEN)
```javascript
const submitReading = async (scheduleId, readerId, currentReading, notes) => {
  // Get stored token
  const token = await AsyncStorage.getItem('authToken');
  
  if (!token) {
    return { success: false, message: 'Not authenticated' };
  }

  const response = await fetch('http://your-domain.com/api/reader/submit-reading', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,  // ✅ Include token
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      schedule_id: scheduleId,
      reader_id: readerId,
      current_reading: currentReading,
      reading_date: new Date().toISOString().split('T')[0],
      reader_notes: notes
    })
  });
  
  const data = await response.json();
  
  if (data.success) {
    console.log('✅ Reading submitted successfully');
    // Update local storage
    // Remove submitted schedule or update its status
  } else {
    console.error('❌ Submission failed:', data.message);
  }
  
  return data;
};
```

### Step 4: Handle Token Expiration
```javascript
// Create a helper function to check and refresh authentication
const checkAuth = async () => {
  const token = await AsyncStorage.getItem('authToken');
  
  if (!token) {
    // Navigate to login screen
    return false;
  }
  
  // Optionally, test the token
  try {
    const response = await fetch('http://your-domain.com/api/reader/profile', {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    
    if (response.status === 401) {
      // Token expired, need to login again
      await AsyncStorage.clear();
      // Navigate to login screen
      return false;
    }
    
    return true;
  } catch (error) {
    console.error('Auth check failed:', error);
    return false;
  }
};

// Use before any API call
const safeApiCall = async (apiFunction) => {
  const isAuthenticated = await checkAuth();
  if (!isAuthenticated) {
    throw new Error('Please login first');
  }
  return await apiFunction();
};
```

---

## Data Models

### Schedule Object
```typescript
interface Schedule {
  id: number;
  sedr_number: number;
  account_number: string;
  account_name: string;
  address: string;
  zone: string;
  category: string;
  meter_number: string;
  previous_reading: number;
  previous_reading_date: string | null;
  current_reading: number | null;
  reading_date: string | null;
  consumption: number;
  bill_month: string;
  bill_date: string;
  due_date: string;
  status: 'Assigned' | 'In Progress' | 'Completed';
  reader_notes: string | null;
}
```

---

## Authentication Errors

### Common Authentication Issues

**1. Missing Token**
```json
{
  "success": false,
  "message": "Unauthorized. Please login to access this resource."
}
```
**Solution:** Reader needs to login first

**2. Invalid Token**
```json
{
  "success": false,
  "message": "Invalid authentication token"
}
```
**Solution:** Token is corrupted, login again

**3. Expired Token (24 hours)**
```json
{
  "success": false,
  "message": "Token expired. Please login again."
}
```
**Solution:** Login again to get new token

**4. Not a Reader Role**
```json
{
  "success": false,
  "message": "Access denied. Only readers can access this resource."
}
```
**Solution:** User account must have 'reader' role

---

## Testing

### Test API Connection (No Auth Required)
```bash
curl http://localhost:8000/api/test
```

Expected Response:
```json
{
  "success": true,
  "message": "API is working",
  "timestamp": "2025-11-03 10:30:00",
  "version": "1.0"
}
```

### Test Login
```bash
curl -X POST http://localhost:8000/api/reader/login \
  -H "Content-Type: application/json" \
  -d '{"email":"reader@example.com","password":"password123"}'
```

Expected Response:
```json
{
  "success": true,
  "message": "Login successful",
  "token": "NToxNzMwNjE2MDY0",
  "user": { ... }
}
```

### Test Get Schedules (With Authentication)
```bash
# Replace YOUR_TOKEN with actual token from login
curl http://localhost:8000/api/reader/schedules?reader_id=5 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Submit Reading (With Authentication)
```bash
curl -X POST http://localhost:8000/api/reader/submit-reading \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "schedule_id": 1,
    "reader_id": 5,
    "current_reading": 165,
    "reader_notes": "Test submission"
  }'
```

---

## Notes for Mobile App Developers

1. **🔐 Authentication is REQUIRED**: All endpoints except login and test require valid authentication token
2. **💾 Store token securely**: Use AsyncStorage or SecureStore for token storage
3. **⏰ Handle token expiration**: Tokens expire after 24 hours, redirect to login when expired
4. **📱 Store schedules locally**: After downloading, store schedules in AsyncStorage or SQLite for offline access
5. **🔄 Sync when online**: Submit readings when internet connection is available
6. **📋 Queue submissions**: If offline, queue readings and submit when back online
7. **✨ Update UI in real-time**: Update schedule status locally and sync with server
8. **❌ Handle errors gracefully**: Show user-friendly messages for API errors
9. **👤 Role verification**: Only users with 'reader' role can login to mobile app
10. **🔒 Secure credentials**: Never store password, only store token

---

## React Native Login Screen Example

```javascript
import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, Alert, ActivityIndicator } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const LoginScreen = ({ navigation }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Please enter email and password');
      return;
    }

    setLoading(true);

    try {
      const response = await fetch('http://your-domain.com/api/reader/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      const data = await response.json();

      if (data.success) {
        // Store authentication data
        await AsyncStorage.setItem('authToken', data.token);
        await AsyncStorage.setItem('userId', data.user.id.toString());
        await AsyncStorage.setItem('userName', data.user.name);
        await AsyncStorage.setItem('userEmail', data.user.email);

        Alert.alert('Success', 'Login successful!', [
          { text: 'OK', onPress: () => navigation.replace('Home') }
        ]);
      } else {
        Alert.alert('Login Failed', data.message);
      }
    } catch (error) {
      Alert.alert('Error', 'Network error. Please check your connection.');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={{ flex: 1, justifyContent: 'center', padding: 20, backgroundColor: '#f5f5f5' }}>
      <View style={{ backgroundColor: 'white', padding: 20, borderRadius: 10, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.25, shadowRadius: 3.84, elevation: 5 }}>
        <Text style={{ fontSize: 24, fontWeight: 'bold', marginBottom: 20, textAlign: 'center' }}>
          Meter Reader Login
        </Text>

        <TextInput
          style={{ borderWidth: 1, borderColor: '#ddd', padding: 10, marginBottom: 15, borderRadius: 5 }}
          placeholder="Email"
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
        />

        <TextInput
          style={{ borderWidth: 1, borderColor: '#ddd', padding: 10, marginBottom: 20, borderRadius: 5 }}
          placeholder="Password"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
        />

        <TouchableOpacity
          style={{ backgroundColor: '#007bff', padding: 15, borderRadius: 5, alignItems: 'center' }}
          onPress={handleLogin}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="white" />
          ) : (
            <Text style={{ color: 'white', fontWeight: 'bold', fontSize: 16 }}>Login</Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
};

export default LoginScreen;
```

---

## Security Best Practices

### For Mobile App:

1. **Never store passwords** - Only store the authentication token
2. **Use HTTPS** - Always use HTTPS in production for API calls
3. **Secure storage** - Use `@react-native-async-storage/async-storage` or `expo-secure-store`
4. **Token validation** - Check token validity before making API calls
5. **Logout functionality** - Provide logout button that clears AsyncStorage
6. **Handle 401 errors** - Automatically redirect to login when authentication fails
7. **Network error handling** - Handle offline scenarios gracefully

### For Backend:

1. **✅ Authentication Required** - All schedules/submit endpoints require valid token
2. **✅ Role Verification** - Only 'reader' role can access mobile API
3. **✅ Token Expiration** - Tokens expire after 24 hours
4. **✅ User Validation** - Each request validates the authenticated user
5. **✅ Authorization Check** - Readers can only access their own assigned schedules

---

## API Endpoint Summary

### Public Endpoints (No Authentication)
- `POST /api/reader/login` - Get authentication token
- `GET /api/test` - Test API connectivity

### Protected Endpoints (Requires Authentication Token)
- `GET /api/reader/schedules` - Download assigned schedules
- `POST /api/reader/submit-reading` - Submit meter reading
- `POST /api/reader/update-status` - Update schedule status
- `GET /api/reader/stats` - Get reader statistics
- `GET /api/reader/profile` - Get reader profile (for token validation)

---

## Support

For API issues or questions, contact the system administrator.

**Version:** 1.0  
**Last Updated:** November 3, 2025  
**Security:** Token-based authentication with 24-hour expiration

