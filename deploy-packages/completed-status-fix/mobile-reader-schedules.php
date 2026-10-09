<?php
/**
 * Drop-in reader schedules endpoint for the mobile app.
 *
 * URL: https://YOUR-DOMAIN/mobile-reader-schedules.php?reader_id=ID
 * Auth: Bearer token (same as /api/reader/schedules)
 *
 * Thin wrapper — schedule payload comes from MeterReadingApiController
 * so logic stays in one place (DM breakdown, completed status, etc.).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * @param  array<string, mixed>  $payload
 */
function mrs_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function mrs_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }

    return isset($_GET['api_token']) ? (string) $_GET['api_token'] : null;
}

try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) {
    mrs_json(['success' => false, 'message' => 'Bootstrap failed: ' . $e->getMessage()], 500);
}

use App\Http\Controllers\Api\MeterReadingApiController;
use App\Models\User;
use Illuminate\Http\Request;

$token = mrs_bearer_token();
if (!$token) {
    mrs_json(['success' => false, 'message' => 'Unauthorized. Please login.'], 401);
}

try {
    $decoded = base64_decode($token, true);
    $parts = $decoded !== false ? explode(':', $decoded) : [];
    if (count($parts) !== 2) {
        mrs_json(['success' => false, 'message' => 'Invalid token format'], 401);
    }
    $userId = (int) $parts[0];
    $timestamp = (int) $parts[1];
    if ($timestamp > 0 && (time() - $timestamp) > 86400) {
        mrs_json(['success' => false, 'message' => 'Token expired. Please login again.'], 401);
    }
    $reader = User::find($userId);
    $role = strtolower((string) ($reader->role ?? ''));
    if (!$reader || ($role !== 'reader' && $role !== 'disconnector')) {
        mrs_json(['success' => false, 'message' => 'Access denied.'], 403);
    }
} catch (Throwable $e) {
    mrs_json(['success' => false, 'message' => 'Invalid authentication token'], 401);
}

$readerId = (int) $reader->id;
$query = array_filter([
    'reader_id' => $readerId,
    'bill_month' => $_GET['bill_month'] ?? null,
    'zone' => $_GET['zone'] ?? null,
], static fn ($v) => $v !== null && $v !== '');

try {
    $request = Request::create('/api/reader/schedules', 'GET', $query);
    $response = app(MeterReadingApiController::class)->getAssignedSchedules($request);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
} catch (Throwable $e) {
    mrs_json([
        'success' => false,
        'message' => 'Error loading schedules: ' . $e->getMessage(),
    ], 500);
}
