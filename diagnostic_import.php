<?php
/**
 * Diagnostic script to inspect Excel file structure
 * Place your Excel file in storage/uploads/test.xlsx then run this
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$testFile = __DIR__ . '/storage/app/test.xlsx';

if (!file_exists($testFile)) {
    echo "No test file found. Upload a file as storage/app/test.xlsx\n";
    exit;
}

try {
    $spreadsheet = IOFactory::load($testFile);
    $worksheet = $spreadsheet->getActiveSheet();
    
    echo "File loaded successfully!\n";
    echo "Spreadsheet name: " . $spreadsheet->getProperties()->getTitle() . "\n";
    echo "Sheet name: " . $worksheet->getTitle() . "\n";
    echo "Dimensions: " . $worksheet->calculateWorksheet()->dimension . "\n";
    echo "\n";
    
    echo "First 5 rows of data:\n";
    echo str_repeat("-", 150) . "\n";
    
    $rows = $worksheet->toArray();
    
    foreach (array_slice($rows, 0, 5) as $idx => $row) {
        echo "\nRow " . ($idx + 1) . ":\n";
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
    
    echo "\n" . str_repeat("-", 150) . "\n";
    echo "Total rows in file: " . count($rows) . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
