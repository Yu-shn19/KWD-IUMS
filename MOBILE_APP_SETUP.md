# Mobile App Setup Guide

## 🔧 Configuration

### **Update API Base URL**
 
The mobile app needs to connect to your server. Update the base URL in these locations:

#### **Location 1: ReadAndBill.js**

Find and update this line (around line 65):

```javascript
let url = 'http://192.168.1.3/WD/public/api/routes.php';
```

Change to:
```javascript
let url = 'http://YOUR_SERVER_IP/WD/public/api/routes.php';
```

And this line (around line 431):

```javascript
const uploadResponse = await fetch('http://192.168.1.3/WD/public/api/reader/submit-reading', {
```

Change to:
```javascript
const uploadResponse = await fetch('http://YOUR_SERVER_IP/WD/public/api/reader/submit-reading', {
```

#### **Location 2: services/api.js** (if exists)

Update the base URL configuration:

```javascript
const BASE_URL = 'http://YOUR_SERVER_IP/WD/public/api';
```

---

## 🌐 Finding Your Server IP

### **Windows:**
1. Open Command Prompt (cmd)
2. Type: `ipconfig`
3. Look for "IPv4 Address" under your active network adapter
4. Example: `192.168.1.3`

### **Mac/Linux:**
1. Open Terminal
2. Type: `ifconfig` or `ip addr`
3. Look for inet address
4. Example: `192.168.1.3`

### **Using Same Computer (Development):**
- Use: `http://localhost/WD/public/api`
- Or: `http://127.0.0.1/WD/public/api`

---

## 📱 Mobile App Requirements

### **Required Packages:**

```json
{
  "dependencies": {
    "react": "^18.2.0",
    "react-native": "^0.72.0",
    "@react-native-async-storage/async-storage": "^1.19.0",
    "react-native-thermal-receipt-printer": "^1.8.0"
  }
}
```

### **Installation:**

```bash
# Install dependencies
npm install

# For Bluetooth printing (optional)
npm install react-native-thermal-receipt-printer

# Rebuild native modules (if using Expo)
npx expo prebuild
```

---

## 🔐 Test Credentials

Create test accounts in your system:

### **Admin Account:**
```
Email: admin@waterdistrict.com
Password: admin123
Role: admin
```

### **Reader Account:**
```
Email: reader@waterdistrict.com
Password: reader123
Role: reader
```

You can create these in the **User Management** page on the web interface.

---

## 🧪 Testing the System

### **1. Test API Connection**

Open browser and test:
```
http://YOUR_SERVER_IP/WD/public/api/test
```

Expected response:
```json
{
  "success": true,
  "message": "API is working",
  "timestamp": "2025-11-05 10:30:00",
  "version": "1.0"
}
```

### **2. Test Login**

Using Postman or curl:

```bash
curl -X POST http://YOUR_SERVER_IP/WD/public/api/reader/login \
  -H "Content-Type: application/json" \
  -d '{"email":"reader@waterdistrict.com","password":"reader123"}'
```

Expected response:
```json
{
  "success": true,
  "message": "Login successful",
  "token": "MTIzOjE3MzA4MDAw...",
  "user": {
    "id": 2,
    "name": "DOE, JOHN",
    "email": "reader@waterdistrict.com",
    "role": "reader"
  }
}
```

### **3. Test Download Schedules**

```bash
curl -X GET "http://YOUR_SERVER_IP/WD/public/api/reader/schedules?reader_id=2" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🔥 Common Issues & Solutions

### **Issue: Cannot connect to server**

**Solution:**
1. ✅ Check if XAMPP/server is running
2. ✅ Check firewall settings (allow port 80)
3. ✅ Verify IP address is correct
4. ✅ Make sure mobile device is on same WiFi network
5. ✅ Try pinging server from mobile device

### **Issue: "Invalid credentials" when logging in**

**Solution:**
1. ✅ Verify email and password are correct
2. ✅ Check user role is "reader" (case-insensitive)
3. ✅ Check users table in database
4. ✅ Try resetting password in User Management

### **Issue: No schedules found**

**Solution:**
1. ✅ Prepare schedules in Billing Processes first
2. ✅ Assign schedules to reader in Meter Reading page
3. ✅ Check `meter_reading_schedules` table in database
4. ✅ Verify `assigned_reader_id` matches the reader's user ID

### **Issue: Upload fails but reading is collected**

**Solution:**
- ✅ This is normal if offline
- ✅ Readings are saved locally
- ✅ They will upload when internet is available
- ✅ Or manually tap "Upload" button later

### **Issue: Bluetooth printing not working**

**Solution:**
1. ✅ Pair Bluetooth printer with device first
2. ✅ Install `react-native-thermal-receipt-printer` package
3. ✅ Rebuild app after installing package
4. ✅ Check printer is turned on and connected
5. ✅ Try printing test page from printer settings

---

## 📂 Project Structure

```
WD/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── MeterReadingController.php       # Web interface
│   │       └── Api/
│   │           └── MeterReadingApiController.php # Mobile API
│   └── Models/
│       ├── User.php
│       └── MeterReadingSchedule.php
├── routes/
│   ├── web.php                                   # Web routes
│   └── api.php                                   # Mobile API routes
├── resources/
│   └── views/
│       └── processes/
│           ├── billing-processes.blade.php       # Step 1: Prepare
│           ├── meter-reading.blade.php           # Step 2: Assign
│           └── download-reading.blade.php        # Step 3: Monitor
└── WD_App/
    └── learningrn/
        ├── App.js                                # Main app
        ├── ReadAndBill.js                        # Reading collection
        └── services/
            ├── api.js                            # API calls
            └── storage.js                        # Local storage
```

---

## 🚀 Quick Start Checklist

- [ ] XAMPP/Laravel server is running
- [ ] Database is migrated and seeded
- [ ] Users table has reader accounts
- [ ] API base URL is configured in mobile app
- [ ] Mobile device is on same WiFi network
- [ ] Test API connection works
- [ ] Test login works
- [ ] Schedules are prepared in Billing Processes
- [ ] Schedules are assigned to reader
- [ ] Reader can login to mobile app
- [ ] Reader can download schedules
- [ ] Reader can submit readings
- [ ] Readings appear in web interface

---

## 📞 Need Help?

1. **Check the logs:**
   - Laravel: `storage/logs/laravel.log`
   - Mobile: Use React Native Debugger or console

2. **Database issues:**
   - Check `meter_reading_schedules` table
   - Verify `users` table has readers
   - Check foreign key constraints

3. **Network issues:**
   - Use tools like Postman to test API
   - Check firewall settings
   - Verify WiFi connection

---

## 🎉 Success!

If you can:
- ✅ Login to mobile app as reader
- ✅ See assigned routes in the app
- ✅ Submit a reading
- ✅ See "Reading Uploaded" message
- ✅ View completed reading in web interface

Then your system is working perfectly! 🎊

---

**Last Updated:** November 5, 2025
**Version:** 1.0

