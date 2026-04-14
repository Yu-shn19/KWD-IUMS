@echo off
echo 🌐 Remote XAMPP Setup for React Native + Laravel
echo ================================================
echo.

echo 📋 Step 1: Find XAMPP Laptop IP Address
echo =======================================
echo.
echo Please run this command on your XAMPP laptop:
echo ipconfig
echo.
echo Look for "IPv4 Address" - it will be something like 192.168.1.100
echo.

set /p XAMPP_IP="Enter your XAMPP laptop's IP address: "

echo.
echo 🔧 Step 2: Testing Network Connectivity
echo ======================================
echo.

echo Testing connection to %XAMPP_IP%...
ping -n 1 %XAMPP_IP% >nul
if %errorlevel% equ 0 (
    echo ✅ Network connection successful!
) else (
    echo ❌ Cannot reach %XAMPP_IP%
    echo Please check:
    echo 1. Both computers are on the same network
    echo 2. XAMPP laptop is turned on
    echo 3. IP address is correct
    pause
    exit /b 1
)

echo.
echo 🌐 Step 3: Testing Apache Connection
echo ===================================
echo.

echo Testing Apache on %XAMPP_IP%...
curl -s -o nul -w "HTTP Status: %%{http_code}\n" http://%XAMPP_IP%/WD_App/public/api/auth/login
if %errorlevel% equ 0 (
    echo ✅ Apache is accessible!
) else (
    echo ⚠️  Apache might not be accessible
    echo Please check XAMPP_SETUP.md for configuration steps
)

echo.
echo 🗄️ Step 4: Testing Database Connection
echo =====================================
echo.

echo Testing MySQL connection to %XAMPP_IP%...
php test-database-connection.php

echo.
echo 📱 Step 5: React Native Configuration
echo ====================================
echo.

echo Updating API configuration...
echo Current API URL will be: http://%XAMPP_IP%/WD_App/public/api

echo.
echo 📖 Configuration Summary:
echo ========================
echo.
echo XAMPP Laptop IP: %XAMPP_IP%
echo API Base URL: http://%XAMPP_IP%/WD_App/public/api
echo Database Host: %XAMPP_IP%
echo.
echo 📋 Next Steps:
echo 1. Configure XAMPP laptop for remote access (see REMOTE_XAMPP_SETUP.md)
echo 2. Update Laravel .env file on XAMPP laptop
echo 3. Test React Native app connection
echo 4. Start development!
echo.

echo 🎉 Remote XAMPP setup check complete!
echo 📖 For detailed instructions, see: REMOTE_XAMPP_SETUP.md
echo.

pause
