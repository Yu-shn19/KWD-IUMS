<?php
/**
 * Standalone API endpoint for login
 * Works directly with XAMPP without routing issues
 */

// CORS headers MUST come first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Load Laravel
require_once __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Use Laravel's DB and Models
use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    $username = $data['username'] ?? $data['email'] ?? null;
    $password = $data['password'] ?? null;
    
    if (!$username || !$password) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Username/email and password are required'
        ]);
        exit;
    }
    
    // Find user by email or username
    $user = User::where('email', $username)->first();
    
    if (!$user || !Hash::check($password, $user->password)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
        exit;
    }
    
    // Check if user is a reader or disconnector
    $userRole = strtolower($user->role ?? '');
    if ($userRole !== 'reader' && $userRole !== 'disconnector') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Only readers and disconnectors can use this app.'
        ]);
        exit;
    }
    
    // Generate token
    $token = base64_encode($user->id . ':' . time());
    
    // Format name
    $name = strtoupper($user->last_name ?? '') . ', ' . strtoupper($user->first_name ?? '');
    if ($user->middle_name) {
        $name .= ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.';
    }
    if ($user->extension) {
        $name .= ' ' . strtoupper($user->extension);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

