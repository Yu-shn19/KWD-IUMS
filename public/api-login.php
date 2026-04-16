<?php
/**
 * SUPER SIMPLE LOGIN ENDPOINT - Direct file, no folders
 */

// CORS headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Load Laravel
require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    $username = $data['username'] ?? $data['email'] ?? null;
    $password = $data['password'] ?? null;
    
    if (!$username || !$password) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }
    
    $user = User::where('email', $username)->first();
    
    if (!$user || !Hash::check($password, $user->password)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    if (strtolower($user->role ?? '') !== 'reader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only readers can login']);
        exit;
    }
    
    $token = base64_encode($user->id . ':' . time());
    
    $name = strtoupper($user->last_name ?? '') . ', ' . strtoupper($user->first_name ?? '');
    if ($user->middle_name) {
        $name .= ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.';
    }
    if ($user->extension) {
        $name .= ' ' . strtoupper($user->extension);
    }
    
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
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

