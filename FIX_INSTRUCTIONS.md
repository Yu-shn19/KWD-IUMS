# 🔧 FIX: Start MySQL Database

## The Problem
Your app is getting **"Server error (500)"** because **MySQL/MariaDB is not running** in XAMPP.

Error from logs:
```
SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it
```

## The Solution

### Step 1: Open XAMPP Control Panel
1. Open **XAMPP Control Panel** (xampp-control.exe)
2. Look for the **MySQL** or **MariaDB** module

### Step 2: Start MySQL
1. Click the **"Start"** button next to **MySQL**
2. Wait for the status to show **"Running"** (green background)
3. Port should show **3306**

### Step 3: Verify It's Running
You should see:
- ✅ Apache: **Running** (port 80, 443)
- ✅ MySQL: **Running** (port 3306)

### Step 4: Test the App
1. **Open your mobile app**
2. Tap **"🧪 Test API"** button - should say "✅ API Connected"
3. **Try to submit a reading** - should work now!

---

## If MySQL Won't Start

### Check if port 3306 is in use:
```powershell
netstat -ano | findstr :3306
```

### If something else is using port 3306:
1. **Option A**: Stop the other service using port 3306
2. **Option B**: Change MySQL port in XAMPP:
   - Click "Config" button next to MySQL
   - Select "my.ini"
   - Change port from 3306 to 3307
   - Update `.env` file in Laravel:
     ```
     DB_PORT=3307
     ```

---

## After Starting MySQL

1. ✅ MySQL should be running
2. ✅ Tap "🧪 Test API" in the app
3. ✅ Try submitting a reading
4. ✅ Check Laravel logs: `storage/logs/laravel.log` - should see success logs

The app will work once MySQL is running! 🚀
