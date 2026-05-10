<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReaderController;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    
    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/bills', [CustomerController::class, 'bills']);
    Route::get('/customers/{customer}/payments', [CustomerController::class, 'payments']);
    Route::get('/customers/{customer}/meter-readings', [CustomerController::class, 'meterReadings']);
    
    // Bills
    Route::apiResource('bills', BillController::class);
    
    // Payments
    Route::apiResource('payments', PaymentController::class);
    Route::post('/payments/mobile', [PaymentController::class, 'processMobilePayment']);
    
    // Meter Readings
    Route::apiResource('meter-readings', MeterReadingController::class);
    
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/recent-activities', [DashboardController::class, 'recentActivities']);
    Route::get('/dashboard/monthly-revenue', [DashboardController::class, 'monthlyRevenue']);
    
    // Routes (customer routes uploaded by admin)
    Route::get('/routes', [CustomerController::class, 'getRoutes']);
    Route::post('/routes/import', [CustomerController::class, 'importRoutes']);

    // Reader: downloaded_readings by zone and reading_date (for logged-in reader)
    Route::get('/reader/downloaded-readings/filters', [ReaderController::class, 'downloadedReadingsFilters']);
    Route::get('/reader/downloaded-readings', [ReaderController::class, 'downloadedReadings']);
});
