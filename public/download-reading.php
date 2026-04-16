<?php
/**
 * Download Reading Page - Direct access for XAMPP
 */

// Load Laravel
require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Create request for /download-reading
$request = \Illuminate\Http\Request::create('/download-reading', 'GET');

// Handle the request
$response = $kernel->handle($request);

// Send response
$response->send();

// Terminate
$kernel->terminate($request, $response);

