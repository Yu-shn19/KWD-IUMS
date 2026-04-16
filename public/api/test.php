<?php
/**
 * Test endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'success' => true,
    'message' => 'API is working',
    'timestamp' => date('c'),
    'version' => '1.0'
]);

