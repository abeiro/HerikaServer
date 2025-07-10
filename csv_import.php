<?php

/* CSV Import entry point - handles file uploads for CSV imports */

error_reporting(E_ALL);

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."auditing.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."logger.php");

$startTime = microtime(true);
Logger::info("CSV Import endpoint started: " . $startTime);
$GLOBALS["AUDIT_RUNID_REQUEST"] = "CSV_IMPORT";

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate required parameters
$import_type = $_GET['type'] ?? '';
$timestamp = $_GET['ts'] ?? time();
$game_timestamp = $_GET['gamets'] ?? 0;
$filename = $_GET['filename'] ?? '';

if (!in_array($import_type, ['biography_import', 'oghma_import', 'dynamic_oghma_import'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid import type']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$fileInfo = pathinfo($_FILES['file']['name']);
$fileExtension = strtolower($fileInfo['extension'] ?? '');
if ($fileExtension !== 'csv') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only CSV files are allowed']);
    exit;
}

// File size validation (10MB limit)
$maxFileSize = 10 * 1024 * 1024; // 10MB
if ($_FILES['file']['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'csv_imports' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename for storage
$storedFilename = $import_type . '_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.csv';
$storedFilePath = $uploadDir . $storedFilename;

// Move uploaded file to permanent location
if (!move_uploaded_file($_FILES['file']['tmp_name'], $storedFilePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    exit;
}

Logger::info("CSV file uploaded: $storedFilePath");

try {
    $db = new sql();
    
    // Set up environment for processor
    $_POST['csv_import'] = '1';
    $_POST['type'] = $import_type;
    $_POST['ts'] = $timestamp;
    $_POST['gamets'] = $game_timestamp;
    $_POST['filename'] = $filename;
    
    // Create file structure for processor
    $_FILES['file'] = array(
        'name' => $_FILES['file']['name'],
        'type' => 'text/csv',
        'size' => filesize($storedFilePath),
        'tmp_name' => $storedFilePath,
        'error' => UPLOAD_ERR_OK
    );
    
    // Capture output from processor
    ob_start();
    require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."import_files.php");
    $processorOutput = ob_get_clean();
    
    // If we reach here, processing was successful
    echo json_encode([
        'success' => true, 
        'message' => 'CSV import processed successfully',
        'file' => $storedFilename,
        'type' => $import_type,
        'processing_time' => round(microtime(true) - $startTime, 3)
    ]);
    
} catch (Exception $e) {
    Logger::error("CSV Import error: " . $e->getMessage());
    
    // Clean up file on error
    if (file_exists($storedFilePath)) {
        unlink($storedFilePath);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Processing failed: ' . $e->getMessage()
    ]);
}

?> 