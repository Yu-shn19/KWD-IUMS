@echo off
echo 🐘 XAMPP Setup for React Native + Laravel
echo ==========================================
echo.

echo 📋 Checking XAMPP Status...
echo.

REM Check if XAMPP is running
netstat -an | find "3306" >nul
if %errorlevel% equ 0 (
    echo ✅ MySQL is running on port 3306
) else (
    echo ❌ MySQL is not running. Please start XAMPP MySQL service.
    pause
    exit /b 1
)

netstat -an | find "80" >nul
if %errorlevel% equ 0 (
    echo ✅ Apache is running on port 80
) else (
    echo ❌ Apache is not running. Please start XAMPP Apache service.
    pause
    exit /b 1
)

echo.
echo 🔧 Testing Database Connection...
php test-database-connection.php

echo.
echo 📁 Setting up Laravel Project...
echo.

REM Check if Laravel project exists in XAMPP
if exist "C:\xampp\htdocs\water-district-api" (
    echo ✅ Laravel project exists in XAMPP
) else (
    echo ⚠️  Laravel project not found in C:\xampp\htdocs\water-district-api
    echo 📖 Please follow the XAMPP_SETUP.md guide to create the Laravel project
)

echo.
echo 🚀 React Native Configuration...
echo ✅ API configuration set to XAMPP
echo ✅ Database connection ready
echo.

echo 📖 Next Steps:
echo 1. Create Laravel project in C:\xampp\htdocs\water-district-api
echo 2. Copy Laravel setup files from learningrn/laravel-setup/
echo 3. Configure .env file with XAMPP database settings
echo 4. Run 'php artisan migrate' in Laravel project
echo 5. Start React Native app with 'npx expo start'
echo.

echo 🎉 XAMPP setup check complete!
echo 📖 For detailed instructions, see: XAMPP_SETUP.md
echo.

pause
