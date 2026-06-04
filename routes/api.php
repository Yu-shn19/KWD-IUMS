<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MeterReadingApiController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\Api\RoutesImportController;
use App\Http\Controllers\Api\DisconnectorApiController;
use App\Http\Controllers\Api\PricingTierApiController;
use App\Http\Controllers\ConsumerController; // Mao ni akoang gi add

/*
|--------------------------------------------------------------------------
| API Routes for Mobile App
|--------------------------------------------------------------------------
|
| These routes are for the React Native mobile app used by meter readers
| to download their assigned schedules and submit meter readings.
|
*/

// Public routes (no authentication required)
Route::post('/reader/login', [MeterReadingApiController::class, 'login']);

// Test endpoint (public)
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => now(),
        'version' => '1.0'
    ]);
});

// Protected reader routes (require authentication via api.reader middleware)
Route::prefix('reader')->middleware('api.reader')->group(function () {
    Route::get('/schedules', [MeterReadingApiController::class, 'getAssignedSchedules']);
    Route::post('/submit-reading', [MeterReadingApiController::class, 'submitReading']);
    Route::post('/update-status', [MeterReadingApiController::class, 'updateStatus']);
    Route::get('/stats', [MeterReadingApiController::class, 'getReaderStats']);
    
     // Retrieve Zone: downloaded_readings by zone and reading_date for the logged-in reader
    Route::get('/downloaded-readings/filters', [ReaderController::class, 'downloadedReadingsFilters']);
    Route::get('/downloaded-readings', [ReaderController::class, 'downloadedReadings']);
    
    // Additional authenticated endpoints
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->input('auth_user')
        ]);
    });
});

// Reader-authenticated consumer utilities (same token as /api/reader/*) mao ni akoang gi add
Route::middleware('api.reader')->group(function () {
    Route::get('/consumer/suggestions', [ConsumerController::class, 'getSuggestions']);
    Route::get('/consumer/zone', [ReaderController::class, 'getConsumerZone']);
    Route::post('/consumer/coordinates', [ReaderController::class, 'saveConsumerCoordinates']);
});

// Pricing Tiers API (public for mobile app)
Route::prefix('pricing-tiers')->group(function () {
    Route::get('/', [PricingTierApiController::class, 'index']);
    Route::get('/by-category', [PricingTierApiController::class, 'getByCategoryAndRateCode']);
});

// Routes import for admin/backoffice tools (authentication can be added via middleware)
Route::post('/routes/import', [RoutesImportController::class, 'import']);

// Disconnector API routes
Route::prefix('disconnector')->group(function () {
    // Public endpoints (can be protected with middleware if needed)
    Route::get('/assignments', [DisconnectorApiController::class, 'getAssignments']);
    Route::get('/ar-aging-summary', [DisconnectorApiController::class, 'getArAgingSummary']);
    Route::get('/orders', [DisconnectorApiController::class, 'getOrders']);
    Route::get('/orders/{orderId}', [DisconnectorApiController::class, 'getOrder']);
    Route::get('/stats', [DisconnectorApiController::class, 'getStats']);
    Route::get('/cancelled-due-to-payment', [DisconnectorApiController::class, 'getCancelledDueToPayment']);
    Route::post('/assignments/status', [DisconnectorApiController::class, 'updateAssignmentStatus']);
    Route::post('/assignments/clear-all', [DisconnectorApiController::class, 'clearAllAssignments']);
    
    // Debug endpoint to check saved orders
    Route::get('/debug/all-orders', function() {
        $orders = \App\Models\DisconnectionOrder::with(['consumer', 'disconnector'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return response()->json([
            'success' => true,
            'total_orders' => \App\Models\DisconnectionOrder::count(),
            'recent_orders' => $orders->map(fn($order) => [
                'id' => $order->id,
                'account_no' => $order->account_no,
                'disconnector_id' => $order->disconnector_id,
                'disconnector_name' => $order->disconnector?->name ?? 'Unassigned',
                'status' => $order->status,
                'total_outstanding' => $order->total_outstanding,
                'disconnection_date' => $order->disconnection_date,
                'created_at' => $order->created_at,
            ]),
        ]);
    });
});
