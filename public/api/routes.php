<?php
/**
 * Standalone API endpoint for getting routes/consumers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Laravel
require_once __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use App\Models\User;

try {
    // Get reader_id from query parameter or Authorization header
    $readerId = $_GET['reader_id'] ?? null;
    
    // If no reader_id provided, try to get from Authorization token
    if (!$readerId) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            // Decode token (format: base64(user_id:timestamp))
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            if (count($parts) === 2) {
                $readerId = $parts[0];
            }
        }
    }
    
    // Get the latest bill_month for this reader (to avoid mixing old and new assignments)
    $latestBillMonth = null;
    if ($readerId) {
        $latestBillMonth = MeterReadingSchedule::where('assigned_reader_id', $readerId)
            ->whereIn('status', ['Prepared', 'Assigned', 'In Progress', 'Completed'])
            ->orderBy('bill_month', 'DESC')
            ->value('bill_month');
    }
    
    $query = MeterReadingSchedule::select(
            'id',
            'sedr_number',
            'account_number',
            'account_name as name',
            'zone',
            'category',
            'address',
            'meter_number',
            'previous_reading',
            'current_reading',
            'consumption',
            'status',
            'bill_month',
            'bill_date',
            'due_date',
            'assigned_reader_id'
        )
        ->whereIn('status', ['Prepared', 'Assigned', 'In Progress', 'Completed']);
    
    // Filter by assigned reader if reader_id is provided
    if ($readerId) {
        $query->where('assigned_reader_id', $readerId);
        
        // Filter by latest bill_month only (don't mix old and new assignments)
        if ($latestBillMonth) {
            $query->where('bill_month', $latestBillMonth);
        }
    }
    
    $routes = $query->orderBy('sedr_number')->get()->toArray();
    
    // Get completed readings from downloaded_readings table (mobile app tracking)
    // Only get readings for schedules in the current bill_month
    $downloadedReadings = [];
    if ($readerId && !empty($routes)) {
        $scheduleIds = array_column($routes, 'id');
        $downloaded = DownloadedReading::where('reader_id', $readerId)
            ->where('status', 'completed')
            ->whereIn('schedule_id', $scheduleIds)
            ->get()
            ->keyBy('schedule_id')
            ->toArray();
        $downloadedReadings = $downloaded;
    }
    
    // Merge downloaded readings with routes (preserve completed status)
    foreach ($routes as &$route) {
        $scheduleId = $route['id'];
        
        // If this schedule has a completed reading in downloaded_readings table
        if (isset($downloadedReadings[$scheduleId])) {
            $downloaded = $downloadedReadings[$scheduleId];
            
            // Override with completed data from downloaded_readings
            $route['status'] = 'completed';
            $route['current_reading'] = $downloaded['current_reading'];
            $route['consumption'] = $downloaded['consumption'];
            
            // Add reading_date if available
            if (isset($downloaded['reading_date'])) {
                $route['reading_date'] = $downloaded['reading_date'];
            }
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'bill_month' => $latestBillMonth,
        'total_routes' => count($routes),
        'data' => $routes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

