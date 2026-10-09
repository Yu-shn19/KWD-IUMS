<?php
/**
 * Drop-in Retrieve Zone endpoint for the mobile app.
 *
 * URLs:
 *   .../mobile-retrieve-zone.php?action=filters&reader_id=ID
 *   .../mobile-retrieve-zone.php?action=filters&reader_id=ID&reading_date=YYYY-MM-DD
 *   .../mobile-retrieve-zone.php?action=list&reader_id=ID&zone=2A&reading_date=YYYY-MM-DD
 *
 * Auth: Bearer token (same as /api/reader/downloaded-readings*)
 * Thin wrapper around ReaderController so logic stays in one place.
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
function mrz_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function mrz_bearer_token(): ?string
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
    mrz_json(['success' => false, 'message' => 'Bootstrap failed: ' . $e->getMessage()], 500);
}

use App\Http\Controllers\ReaderController;
use App\Models\User;
use Illuminate\Http\Request;

$token = mrz_bearer_token();
if (!$token) {
    mrz_json(['success' => false, 'message' => 'Unauthorized. Please login.'], 401);
}

try {
    $decoded = base64_decode($token, true);
    $parts = $decoded !== false ? explode(':', $decoded) : [];
    if (count($parts) !== 2) {
        mrz_json(['success' => false, 'message' => 'Invalid token format'], 401);
    }
    $userId = (int) $parts[0];
    $timestamp = (int) $parts[1];
    if ($timestamp > 0 && (time() - $timestamp) > 86400) {
        mrz_json(['success' => false, 'message' => 'Token expired. Please login again.'], 401);
    }
    $reader = User::find($userId);
    $role = strtolower((string) ($reader->role ?? ''));
    if (!$reader || ($role !== 'reader' && $role !== 'disconnector')) {
        mrz_json(['success' => false, 'message' => 'Access denied.'], 403);
    }
} catch (Throwable $e) {
    mrz_json(['success' => false, 'message' => 'Invalid authentication token'], 401);
}

$action = strtolower((string) ($_GET['action'] ?? 'filters'));
$readerId = (int) $reader->id;
$query = array_filter([
    'reader_id' => $readerId,
    'zone' => $_GET['zone'] ?? null,
    'reading_date' => $_GET['reading_date'] ?? null,
], static fn ($v) => $v !== null && $v !== '');

try {
    $controller = app(ReaderController::class);
    if ($action === 'list') {
        $request = Request::create('/api/reader/downloaded-readings', 'GET', $query);
        $response = $controller->downloadedReadings($request);
    } else {
        $request = Request::create('/api/reader/downloaded-readings/filters', 'GET', $query);
        $response = $controller->downloadedReadingsFilters($request);
    }
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
} catch (Throwable $e) {
    mrz_json([
        'success' => false,
        'message' => 'Error loading retrieve zone: ' . $e->getMessage(),
    ], 500);
}
