#!/bin/bash

echo "🚀 React Native + Laravel Integration Setup"
echo "=========================================="

# Check if Laravel project exists
if [ ! -d "../laravel-project" ]; then
    echo "❌ Laravel project not found in ../laravel-project"
    echo "Please create a Laravel project first:"
    echo "composer create-project laravel/laravel laravel-project"
    exit 1
fi

echo "✅ Laravel project found"

# Navigate to Laravel project
cd ../laravel-project

echo "📦 Installing Laravel Sanctum..."
composer require laravel/sanctum

echo "📋 Publishing Sanctum configuration..."
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

echo "🗄️ Running migrations..."
php artisan migrate

echo "📁 Copying API files..."
# Copy API routes
cp ../WD_App/learningrn/laravel-setup/routes/api.php routes/api.php

# Copy controllers
cp ../WD_App/learningrn/laravel-setup/controllers/*.php app/Http/Controllers/

# Copy models
cp ../WD_App/learningrn/laravel-setup/models/*.php app/Models/

# Copy migrations
cp ../WD_App/learningrn/laravel-setup/migrations/*.php database/migrations/

echo "🗄️ Running additional migrations..."
php artisan migrate

echo "👤 Creating test user..."
php artisan tinker --execute="
use App\Models\User;
User::create([
    'name' => 'Admin User',
    'username' => 'admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password123')
]);
"

echo "👥 Creating test customer..."
php artisan tinker --execute="
use App\Models\Customer;
Customer::create([
    'account_number' => 'ACC001',
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '1234567890',
    'address' => '123 Main St, City',
    'meter_number' => 'MTR001',
    'status' => 'active'
]);
"

echo "✅ Laravel setup complete!"
echo ""
echo "🔧 Next steps:"
echo "1. Update your React Native API configuration"
echo "2. Start Laravel server: php artisan serve"
echo "3. Start React Native app: npx expo start"
echo "4. Test login with: admin / password123"
echo ""
echo "📖 For detailed instructions, see: INTEGRATION_GUIDE.md"
