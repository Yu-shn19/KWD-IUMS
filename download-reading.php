<?php
/**
 * Download Reading Page - Direct PHP for XAMPP
 * This bypasses Laravel routing to work directly with XAMPP
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

// Load Laravel bootstrap
try {
    require_once __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    
    // Set the correct URL for asset generation
    \Illuminate\Support\Facades\URL::forceRootUrl('http://' . $_SERVER['HTTP_HOST'] . '/WD/public');

    // Get all readers using full namespace
    $readers = \App\Models\User::where(function($query) {
        $query->where('role', 'reader')
              ->orWhere('role', 'Reader')
              ->orWhere('role', 'READER');
    })
    ->orderBy('last_name')
    ->orderBy('first_name')
    ->get();

    // Get summary of assignments using full namespace
    $assignmentsSummary = \App\Models\MeterReadingSchedule::select(
            'assigned_reader_id',
            \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_routes'),
            \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "Assigned" THEN 1 ELSE 0 END) as pending'),
            \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress'),
            \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed')
        )
        ->whereNotNull('assigned_reader_id')
        ->groupBy('assigned_reader_id')
        ->get()
        ->keyBy('assigned_reader_id');

    // Render the view
    echo view('processes.download-reading', compact('readers', 'assignmentsSummary'))->render();

} catch (Exception $e) {
    // If there's an error, display a user-friendly message
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "<h1>Error Loading Page</h1>";
    echo "<p>There was an error loading the Download Reading page.</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='/WD/'>Return to Home</a></p>";
    echo "</body></html>";
}

