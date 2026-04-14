<?php
/**
 * Database Connection Test Script
 * Use this to test connection to your website database
 */

// Database configuration - Local XAMPP Settings (Not Connected)
$host = '127.0.0.1';              // Local XAMPP
$port = 3306;
$database = 'water_district_app'; // Replace with your database name
$username = 'root';               // XAMPP default username
$password = '';                   // XAMPP default password (empty)

echo "🔗 Testing Database Connection\n";
echo "==============================\n\n";

try {
    // Create PDO connection
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful!\n";
    echo "📊 Database: $database\n";
    echo "🌐 Host: $host:$port\n";
    echo "👤 User: $username\n\n";
    
    // Test if required tables exist
    $requiredTables = ['users', 'customers', 'payments'];
    $existingTables = [];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists\n";
                $existingTables[] = $table;
            } else {
                echo "❌ Table '$table' does not exist\n";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📋 Summary:\n";
    echo "Found " . count($existingTables) . " out of " . count($requiredTables) . " required tables\n";
    
    if (count($existingTables) === count($requiredTables)) {
        echo "🎉 All required tables exist! Your database is ready for the React Native app.\n";
    } else {
        echo "⚠️  Some tables are missing. You may need to run Laravel migrations.\n";
    }
    
    // Test data access
    if (in_array('users', $existingTables)) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "👥 Users in database: " . $result['count'] . "\n";
        } catch (Exception $e) {
            echo "❌ Error counting users: " . $e->getMessage() . "\n";
        }
    }
    
    if (in_array('customers', $existingTables)) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "🏠 Customers in database: " . $result['count'] . "\n";
        } catch (Exception $e) {
            echo "❌ Error counting customers: " . $e->getMessage() . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🔧 Troubleshooting Tips:\n";
    echo "1. Check if MySQL/MariaDB is running\n";
    echo "2. Verify database credentials\n";
    echo "3. Ensure database exists\n";
    echo "4. Check user permissions\n";
    echo "5. Verify host and port settings\n";
}

echo "\n📖 Next Steps:\n";
echo "1. Update your Laravel .env file with these database settings\n";
echo "2. Run 'php artisan migrate' to create missing tables\n";
echo "3. Test the React Native app connection\n";
?>
