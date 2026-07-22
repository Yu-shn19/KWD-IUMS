<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\ConsumerLedgerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BillingProcessController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DisconnectionController;
use App\Http\Controllers\DisconnectionNotificationController;
use App\Http\Controllers\PricingTierController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\BillingAdjustmentController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\LRO_ConsumerLedgerController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ConsumerActivationCronController;
use App\Http\Controllers\ActivityLogController;

// Shared-hosting cron (Hostinger hPanel → Cron Jobs). No login; requires secret token in .env.
Route::get('/cron/consumer-activate-pending', [ConsumerActivationCronController::class, 'activatePending'])
    ->name('cron.consumer-activate-pending');

// Public routes (no authentication required)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

// Protected routes (authentication and admin role required)
Route::middleware(['auth', 'role:admin', 'log.activity'])->group(function () {
    // Logout route
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Protected dashboards
    Route::get('/admin/dashboard', [AuthController::class, 'adminDashboard']);
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    // User Management routes
    Route::get('/user-management', [UserController::class, 'index'])->name('user-management');
    // Profile routes
    Route::post('/profile/update', [UserController::class, 'updateProfile'])->name('profile.update');
    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    
    // Storage diagnostic route (for debugging - remove in production)
    Route::get('/storage-check', function() {
        $checks = [
            'storage_dir_exists' => file_exists(storage_path('app/public/profile-pictures')),
            'symlink_exists' => file_exists(public_path('storage')),
            'symlink_is_link' => is_link(public_path('storage')),
            'storage_path' => storage_path('app/public/profile-pictures'),
            'public_storage_path' => public_path('storage/profile-pictures'),
            'files_count' => file_exists(storage_path('app/public/profile-pictures')) 
                ? count(glob(storage_path('app/public/profile-pictures/*'))) 
                : 0,
        ];
        
        if (file_exists(storage_path('app/public/profile-pictures'))) {
            $checks['sample_files'] = array_slice(scandir(storage_path('app/public/profile-pictures')), 2, 5);
        }
        
        return response()->json($checks);
    })->name('storage.check');

    // Consumer page routes
    Route::get('/consumer', [ConsumerController::class, 'index'])->name('consumer');
    Route::get('/consumer/search', [ConsumerController::class, 'search'])->name('consumer.search');
    Route::get('/consumer/suggestions', [ConsumerController::class, 'getSuggestions'])->name('consumer.suggestions');
    Route::get('/consumer/check-account-no', [ConsumerController::class, 'checkAccountNo'])->name('consumer.check-account-no');
    Route::post('/consumer', [ConsumerController::class, 'store'])->name('consumer.store');
    Route::put('/consumer/{consumer}', [ConsumerController::class, 'update'])->name('consumer.update');
    Route::delete('/consumer/{consumer}', [ConsumerController::class, 'destroy'])->name('consumer.destroy');
    Route::get('/consumer/import', [ConsumerController::class, 'importIndex'])->name('consumer.import');
    Route::post('/consumer/import', [ConsumerController::class, 'importStore'])->name('consumer.import.store');
    Route::get('/consumer/upload-base-reading', [ConsumerController::class, 'uploadBaseReadingIndex'])->name('consumer.upload-base-reading');
    Route::post('/consumer/upload-base-reading', [ConsumerController::class, 'uploadBaseReadingStore'])->name('consumer.upload-base-reading.store');

    // Ledger page route
   // Route::get('/ledger', [ConsumerController::class, 'ledger'])->name('ledger');
     // Route::get('/ledger', function () { $consumer = null; return view('consumer.ledger', compact('consumer')); })->name('ledger');
     Route::get('/ledger', function (Request $request) {$consumer = ConsumerController::resolveConsumerFromRequest($request, false);
     return view('consumer.ledger', compact('consumer')); })->name('ledger');
   
    // Get ledger data API
    Route::get('/ledger/data', [ConsumerLedgerController::class, 'getLedger'])->name('ledger.data');
    // Get consumption data API
    Route::get('/consumption/data', [ConsumerLedgerController::class, 'getConsumption'])->name('consumption.data');

    // Test route to check data
    Route::get('/test-ledger', function() {
    $consumer = \App\Models\ConsumerZone::first();
    $ledgers = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get();
    
    $latestWithReading = \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)
        ->whereNotNull('reading')
        ->where('reading', '>', 0)
        ->orderBy('id', 'DESC')
        ->first();
    
    return response()->json([
        'consumer' => [
            'account_no' => $consumer->account_no,
            'account_name' => $consumer->account_name
        ],
        'total_ledgers' => \App\Models\ConsumerLedger::where('consumer_zone_id', $consumer->id)->count(),
        'latest_5_ledgers' => $ledgers->map(function($l) {
            return [
                'id' => $l->id,
                'date' => $l->date,
                'reading' => $l->reading,
                'volume' => $l->volume,
                'trans' => $l->trans,
                'balance' => $l->balance
            ];
        }),
        'latest_with_reading' => $latestWithReading ? [
            'id' => $latestWithReading->id,
            'date' => $latestWithReading->date,
            'reading' => $latestWithReading->reading,
            'volume' => $latestWithReading->volume,
            'trans' => $latestWithReading->trans
        ] : null
    ]);
    });

    // API: Get consumers by zone for location map
    Route::get('/api/consumers-by-zone', function(\Illuminate\Http\Request $request) {
    $zoneCode = $request->input('zone_code');
    $limit = $request->input('limit', 10);
    
    if (!$zoneCode) {
        return response()->json([
            'success' => false,
            'message' => 'Zone code is required'
        ], 400);
    }
    
    $consumers = \App\Models\ConsumerZone::where('zone_code', $zoneCode)
        ->orderBy('sequence', 'asc')
        ->limit($limit)
        ->get();
    
    return response()->json([
        'success' => true,
        'consumers' => $consumers,
        'count' => $consumers->count()
    ]);
    });

    // API: Save GPS coordinates for consumer
    Route::post('/api/consumer/save-gps', function(\Illuminate\Http\Request $request) {
    $consumerId = $request->input('consumer_id');
    $latitude = $request->input('latitude');
    $longitude = $request->input('longitude');
    
    if (!$consumerId || !$latitude || !$longitude) {
        return response()->json([
            'success' => false,
            'message' => 'Consumer ID, latitude and longitude are required'
        ], 400);
    }
    
    $consumer = \App\Models\ConsumerZone::find($consumerId);
    
    if (!$consumer) {
        return response()->json([
            'success' => false,
            'message' => 'Consumer not found'
        ], 404);
    }
    
    $consumer->update(\App\Models\ConsumerZone::filterTableAttributes([
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]));
    
    return response()->json([
        'success' => true,
        'message' => 'GPS coordinates saved successfully',
        'consumer' => $consumer
    ]);
    });

    // Consumer Dashboard route
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // LRO Ledger page route
 //   Route::get('/lro-ledger', [ConsumerController::class, 'lroLedger'])->name('lro-ledger');
    Route::get('/lro-ledger', function (Request $request) {
        $consumer = ConsumerController::resolveConsumerFromRequest($request, false);

        return view('consumer.lro-ledger', compact('consumer'));
    })->name('lro-ledger');
    // Get LRO Ledger data API
    Route::get('/lro-ledger/data', [LRO_ConsumerLedgerController::class, 'getLROLedger'])->name('lro-ledger.data');

    // Service History page route
  //  Route::get('/service', [ConsumerController::class, 'service'])->name('service');
     Route::get('/service', function (Request $request) {
        $consumer = ConsumerController::resolveConsumerFromRequest($request, false);

        return view('consumer.service', compact('consumer'));
    })->name('service');
    // Meter Reading History page route
   // Route::get('/meter', [ConsumerController::class, 'meter'])->name('meter');
  Route::get('/meter', function (Request $request) {
        $consumer = ConsumerController::resolveConsumerFromRequest($request, false);

        return view('consumer.meter', compact('consumer'));
    })->name('meter');
    // Location Map page route
   // Route::get('/location', [ConsumerController::class, 'location'])->name('location');
   Route::get('/location', function (Request $request) {
        $consumer = ConsumerController::resolveConsumerFromRequest($request, false);

        return view('consumer.location', compact('consumer'));
    })->name('location');
    // Consumption Graph page route
  //  Route::get('/consumption', [ConsumerController::class, 'consumption'])->name('consumption');
  Route::get('/consumption', function (Request $request) {
        $consumer = ConsumerController::resolveConsumerFromRequest($request, false);

        return view('consumer.consumption', compact('consumer'));
    })->name('consumption');
    // System Reports page route
    Route::get('/systemreport', function () {
        return view('reports.system');
    })->name('systemreport');

    // Billing Status page route
    Route::get('/billing-status', [ReportController::class, 'billingStatus'])->name('billing-status');

    //  Billing processes page routes
    Route::get('/billing-processes', [BillingProcessController::class, 'index'])->name('billing-processes');
    Route::get('/billing-processes/zones', [BillingProcessController::class, 'getZones'])->name('billing-processes.zones');
    Route::post('/billing-processes/prepare-meter-reading', [BillingProcessController::class, 'prepareMeterReading'])->name('billing-processes.prepare-meter-reading');
    Route::post('/billing-processes/save-schedules', [BillingProcessController::class, 'saveMeterReadingSchedules'])->name('billing-processes.save-schedules');
    Route::get('/billing-processes/schedules', [BillingProcessController::class, 'getSchedules'])->name('billing-processes.get-schedules');
    Route::get('/billing-processes/schedule-batches', [BillingProcessController::class, 'getScheduleBatches'])->name('billing-processes.schedule-batches');
    Route::post('/billing-processes/assign-to-reader', [BillingProcessController::class, 'assignToReader'])->name('billing-processes.assign-to-reader');
    Route::delete('/billing-processes/delete-schedules', [BillingProcessController::class, 'deleteSchedules'])->name('billing-processes.delete-schedules');
    Route::post('/billing-processes/update-schedule-batch', [BillingProcessController::class, 'updateScheduleBatch'])->name('billing-processes.update-schedule-batch');
    Route::post('/billing-processes/search', [BillingProcessController::class, 'searchRecords'])->name('billing-processes.search');
    Route::post('/billing-processes/export', [BillingProcessController::class, 'exportData'])->name('billing-processes.export');
    Route::get('/billing-processes/debug-consumers', [BillingProcessController::class, 'debugConsumers'])->name('billing-processes.debug-consumers');
    Route::get('/billing-processes/account-lookup', [BillingProcessController::class, 'lookupAccountLatestBill'])->name('billing-processes.account-lookup');
    Route::post('/billing-processes/get-downloaded-readings', [BillingProcessController::class, 'getDownloadedReadings'])->name('billing-processes.get-downloaded-readings');
    Route::post('/billing-processes/surcharge-candidates', [BillingProcessController::class, 'getSurchargeCandidates'])->name('billing-processes.surcharge-candidates');
    Route::post('/billing-processes/apply-surcharge', [BillingProcessController::class, 'applySurcharge'])->name('billing-processes.apply-surcharge');
    Route::post('/billing-processes/mark-paid', [BillingProcessController::class, 'markDownloadedReadingPaid'])->name('billing-processes.mark-paid');
    Route::post('/billing-processes/single-penalty-candidate', [BillingProcessController::class, 'getSingleConsumerPenaltyCandidate'])->name('billing-processes.single-penalty-candidate');
    Route::get('/billing-processes/penalty-report', [BillingProcessController::class, 'penaltyReport'])->name('billing-processes.penalty-report');
    Route::get('/billing-processes/penalty-report/export', [BillingProcessController::class, 'exportPenaltyReport'])->name('billing-processes.penalty-report.export');

    // consumer-master-list page route
    Route::get('/consumer-master-list', [BillingProcessController::class, 'consumerMasterList'])->name('consumer-master-list');
    // Route::post('/consumer-master-list/bulk-dm', [BillingProcessController::class, 'storeBulkDmLedger'])->name('consumer-master-list.bulk-dm');
    // Route::post('/consumer-master-list/store-dm', [BillingProcessController::class, 'storeDmLedger'])->name('consumer-master-list.store-dm');
     Route::post('/consumer-master-list/bulk-dm', [BillingProcessController::class, 'storeBulkDmLedger'])->name('consumer-master-list.bulk-dm');
    Route::post('/consumer-master-list/store-dm', [BillingProcessController::class, 'storeDmLedger'])->name('consumer-master-list.store-dm');
    Route::post('/consumer-master-list/import-dm', [BillingProcessController::class, 'storeDmLedgerImport'])->name('consumer-master-list.import-dm');


    // monthly-billing-report page route
    Route::get('/monthly-billing-report', [ReportController::class, 'monthlyBillingReport'])->name('monthly-billing-report');
    Route::get('/monthly-billing-report/export', [ReportController::class, 'exportMonthlyBillingReport'])->name('monthly-billing-report.export');

    // ar-aging-summary page routes
    Route::get('/ar-aging-summary', [ReportController::class, 'arAgingSummary'])->name('ar-aging-summary');
    Route::get('/ar-aging-summary/export', [ReportController::class, 'exportArAgingSummary'])->name('ar-aging-summary.export'); 

    // Consumer Ledger Report (traditional ledger card; view at service-request-report path)
    Route::get('/service-request-report', [ReportController::class, 'consumerLedgerReport'])->name('service-request-report');
    Route::get('/service-request-report/export', [ReportController::class, 'exportConsumerLedgerReport'])->name('service-request-report.export');
     
    // consumers-for-disconnection page route
    Route::get('/consumer-for-disconnection', [ReportController::class, 'consumersForDisconnection'])->name('consumer-for-disconnection');

    // penalty-report page route
    Route::get('/penalty-report', function () {
        return view('reports.system-report.penalty-report', [
            'zones' => \App\Models\ConsumerZone::distinctZoneCodes(),
        ]);
    })->name('penalty-report');

    // Penalty routes
    Route::get('/penalty/{id}', [PenaltyController::class, 'show'])->name('penalty.show');
    Route::put('/penalty/{id}', [PenaltyController::class, 'update'])->name('penalty.update');

    // Meter Reading routes
    Route::get('/meter-reading', [MeterReadingController::class, 'index'])->name('meter-reading');
    Route::get('/meter-reading/assignments', [MeterReadingController::class, 'getReaderAssignments'])->name('meter-reading.assignments');
    Route::post('/meter-reading/assign', [MeterReadingController::class, 'assignSchedulesToReader'])->name('meter-reading.assign');
    Route::post('/meter-reading/unassign', [MeterReadingController::class, 'unassignSchedules'])->name('meter-reading.unassign');
    Route::get('/meter-reading/available-readers', [MeterReadingController::class, 'getAvailableReaders'])->name('meter-reading.available-readers');
    Route::get('/meter-reading/available-zones', [MeterReadingController::class, 'getAvailableZones'])->name('meter-reading.available-zones');
    Route::get('/meter-reading/download', [MeterReadingController::class, 'downloadSchedulesForMobile'])->name('meter-reading.download');
     Route::post('/meter-reading/upload-previous-reading', [MeterReadingController::class, 'uploadPreviousReading'])->name('meter-reading.upload-previous-reading');

    // Billing Adjustment routes
    Route::get('/billing-adjustment', [BillingAdjustmentController::class, 'index'])->name('billing-adjustment');
    Route::post('/billing-adjustment', [BillingAdjustmentController::class, 'store'])->name('billing-adjustment.store');
    Route::get('/billing-adjustment/lro/{id}/edit', [BillingAdjustmentController::class, 'editLro'])->name('billing-adjustment.lro.edit');
    Route::put('/billing-adjustment/lro/{id}', [BillingAdjustmentController::class, 'updateLro'])->name('billing-adjustment.lro.update');
    Route::delete('/billing-adjustment/lro/{id}', [BillingAdjustmentController::class, 'destroyLro'])->name('billing-adjustment.lro.destroy');
    Route::put('/billing-adjustment/ar/{id}', [BillingAdjustmentController::class, 'updateFromNewUi'])->name('billing-adjustment.ar.update');
    Route::delete('/billing-adjustment/ar/{id}', [BillingAdjustmentController::class, 'destroyAr'])->name('billing-adjustment.ar.destroy');
    Route::get('/billing-adjustment/{id}/edit', [BillingAdjustmentController::class, 'edit'])->name('billing-adjustment.edit');
    Route::get('/billing-adjustment/{id}', [BillingAdjustmentController::class, 'show'])->name('billing-adjustment.show');
    Route::put('/billing-adjustment/{id}', [BillingAdjustmentController::class, 'update'])->name('billing-adjustment.update');

    // Billing Payment page route
    Route::get('/billing-payment', [MeterReadingController::class, 'billingPayment'])->name('billing-payment');
    Route::get('/billing-payment/lookup', [MeterReadingController::class, 'lookupBillingRecord'])->name('billing-payment.lookup');
    Route::get('/billing-payment/generate-or', [MeterReadingController::class, 'generateOrNumber'])->name('billing-payment.generate-or');
    Route::get('/billing-payment/unpaid-months', [MeterReadingController::class, 'getUnpaidBillMonths'])->name('billing-payment.unpaid-months');
    Route::get('/billing-payment/bill-month-details', [MeterReadingController::class, 'getBillMonthDetails'])->name('billing-payment.bill-month-details');
    Route::get('/billing-payment/account-suggestions', [MeterReadingController::class, 'getAccountSuggestions'])->name('billing-payment.account-suggestions');
    Route::get('/billing-payment/bam-search', [MeterReadingController::class, 'lookupBamNo'])->name('billing-payment.bam-search');
    Route::post('/billing-payment/cancelled-or', [BillingProcessController::class, 'storeCancelledOr'])->name('billing-payment.cancelled-or');
    Route::delete('/billing-payment/delete', [BillingProcessController::class, 'deletePayment'])->name('billing-payment.delete');
    // Update consumer meter readings (previous/current) for main consumer page
    Route::post('/consumer/update-meter-reading', [MeterReadingController::class, 'updateConsumerMeterReading'])->name('consumer.update-meter-reading');
     // the first Meter Reading Preparation via BillingProcessController::getPreviousReading().
    Route::post('/consumer/update-base-reading', [MeterReadingController::class, 'updateConsumerBaseReading'])->name('consumer.update-base-reading');
    
    Route::post('/consumer/verify-edit-pin', [ConsumerController::class, 'verifyEditPin'])->name('consumer.verify-edit-pin');
    // Settings: Consumer edit PIN (for main-consumer page Edit/Delete/Save Previous Reading)
    Route::get('/settings/consumer-edit-pin', [SettingController::class, 'showConsumerEditPin'])->name('settings.consumer-edit-pin');
    Route::post('/settings/consumer-edit-pin', [SettingController::class, 'updateConsumerEditPin'])->name('settings.consumer-edit-pin.update');
    // Download Reading page
    Route::get('/download-reading', [MeterReadingController::class, 'downloadReadingPage'])->name('download-reading');
    Route::get('/download-reading/summary', [MeterReadingController::class, 'getAssignmentsSummary'])->name('download-reading.summary');

    // Visual Summary page route
    Route::get('/visual-summary', [ReportController::class, 'visualSummary'])->name('visual-summary');

    // Collection Report page routes
    Route::get('/collection-report', [ReportController::class, 'collectionReport'])->name('collection-report');
    Route::get('/collection-report/export', [ReportController::class, 'exportCollectionReport'])->name('collection-report.export');

    // Adjustment Report page route
    Route::get('/adjustment-report', function () {
        return view('reports.system-report.adjustment-report', [
            'zones' => \App\Models\ConsumerZone::distinctZoneCodes(),
        ]);
    })->name('adjustment-report');

    // User Management routes
    Route::post('/user-management', [UserController::class, 'store'])->name('user.store');
    Route::put('/user-management/{user}', [UserController::class, 'update'])->name('user.update');
    Route::delete('/user-management/{user}', [UserController::class, 'destroy'])->name('user.destroy');
     
    // Consumer Ledger routes
    Route::post('/ledger/import', [ConsumerLedgerController::class, 'import'])->name('ledger.import');

    // Collection routes
    Route::get('/collection/import', [CollectionController::class, 'index'])->name('collection.import');
    Route::post('/collection/import', [CollectionController::class, 'import'])->name('collection.import');
    Route::post('/collection/sync-to-ledger', [CollectionController::class, 'syncToLedger'])->name('collection.sync-to-ledger');
    Route::post('/collection/sync-sc-discounts', [CollectionController::class, 'syncScDiscountsOnly'])->name('collection.sync-sc-discounts');
    Route::post('/collection/generate-penalties', [CollectionController::class, 'generatePenalties'])->name('collection.generate-penalties');
    Route::post('/collection/auto-generate-december-penalties', [CollectionController::class, 'autoGenerateDecember2025Penalties'])->name('collection.auto-generate-december-penalties');
    Route::get('/collection/merged-data', [CollectionController::class, 'getMergedData'])->name('collection.merged-data');
    // Billing Adjustment: single page for AR (consumer ledger) and LRO (LRO ledger)
    Route::get('/edit-billing', function () {
        return redirect()->route('billing-adjustment');
    })->name('edit-billing');
    // Save: AR -> BillingAdjustmentController (consumer_ledgers + billing_adjustments); LRO -> lro_ledger
    Route::post('/edit-billing/save', [BillingAdjustmentController::class, 'saveFromNewUi'])->name('edit-billing.save');

    // LRO Ledger routes
    Route::get('/lro-ledger/import', [LRO_ConsumerLedgerController::class, 'index'])->name('lro-ledger.import');
    Route::post('/lro-ledger/import', [LRO_ConsumerLedgerController::class, 'import'])->name('lro-ledger.import');

    // Disconnection routes
    Route::get('/disconnection', [DisconnectionController::class, 'index'])->name('disconnection.index');
    Route::post('/disconnection/generate-notice', [DisconnectionController::class, 'generateNotice'])->name('disconnection.generate-notice');
    Route::post('/disconnection/print', [DisconnectionController::class, 'printNotice'])->name('disconnection.print');
    Route::post('/disconnection/save-and-assign', [DisconnectionController::class, 'saveAndAssign'])->name('disconnection.save-and-assign');
    Route::post('/disconnection/orders/{order}/update', [DisconnectionController::class, 'updateOrder'])->name('disconnection.orders.update');
    Route::get('/disconnection/assignments', [DisconnectionController::class, 'assignments'])->name('disconnection.assignments');
     Route::get('/disconnection/assignments/export', [DisconnectionController::class, 'exportAssignments'])->name('disconnection.assignments.export');
    Route::post('/disconnection/assign-orders', [DisconnectionController::class, 'assignOrders'])->name('disconnection.assign-orders');
     Route::get('/disconnection/notifications/newly-disconnected', [DisconnectionNotificationController::class, 'index'])
        ->name('disconnection.notifications.newly-disconnected');

    // Pricing Tiers Management routes
    Route::resource('pricing-tiers', PricingTierController::class);
    Route::post('/pricing-tiers/initialize', [PricingTierController::class, 'initializeDefaults'])->name('pricing-tiers.initialize');
});
