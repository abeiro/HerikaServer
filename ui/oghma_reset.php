<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Connect to database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
if (!$conn) {
    die("Failed to connect to database: " . pg_last_error());
}

try {
    // Start transaction
    pg_query($conn, "BEGIN");

    // First, truncate the oghma table
    $truncateQuery = "TRUNCATE TABLE {$schema}.oghma RESTART IDENTITY";
    $truncateResult = pg_query($conn, $truncateQuery);

    if (!$truncateResult) {
        throw new Exception("Error truncating table: " . pg_last_error($conn));
    }

    // Find the latest oghma SQL file
    $dataDir = $rootPath . 'data';
    $files = glob($dataDir . DIRECTORY_SEPARATOR . 'oghma_*.sql');
    
    if (empty($files)) {
        throw new Exception("No oghma SQL files found in data directory");
    }

    // Sort files by version number
    usort($files, function($a, $b) {
        preg_match('/oghma_(\d+)\.sql$/', $a, $matchesA);
        preg_match('/oghma_(\d+)\.sql$/', $b, $matchesB);
        
        if (empty($matchesA[1]) || empty($matchesB[1])) {
            return 0; // Invalid format, treat as equal
        }
        
        return $matchesB[1] - $matchesA[1]; // Sort descending
    });

    // Get the latest file
    $sqlFile = $files[0];
    $filename = basename($sqlFile);
    
    // Extract version for message
    preg_match('/oghma_(\d{8})(\d{3})\.sql$/', $filename, $matches);
    if (!empty($matches)) {
        $date = date_create_from_format('Ymd', $matches[1]);
        $version = $matches[2];
        $versionInfo = date_format($date, 'Y-m-d') . " (v" . intval($version) . ")";
    } else {
        $versionInfo = $filename;
    }

    // Verify file exists (redundant but safe)
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at: " . $sqlFile);
    }

    // Read SQL file
    $sqlContent = file_get_contents($sqlFile);
    if ($sqlContent === false) {
        throw new Exception("Could not read SQL file");
    }


    // Debug: Output first part of SQL content
    $debugSqlPreview = substr($sqlContent, 0, 500);
    Logger::debug("SQL Preview: " . $debugSqlPreview);

    // Execute the SQL directly
    $result = pg_query($conn, $sqlContent);
    if (!$result) {
        throw new Exception("Error executing SQL: " . pg_last_error($conn) . "\nFirst 500 chars of SQL: " . $debugSqlPreview);
    }

    // Update the native_vector for all entries
    $vectorUpdateQuery = "
        UPDATE $schema.oghma
        SET native_vector = 
              setweight(to_tsvector(coalesce(topic, '')), 'A')
            || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
            || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
    ";

    $vectorResult = pg_query($conn, $vectorUpdateQuery);
    if (!$vectorResult) {
        throw new Exception("Error updating vectors: " . pg_last_error($conn));
    }

    // Verify data was imported
    $countQuery = "SELECT COUNT(*) FROM $schema.oghma";
    $countResult = pg_query($conn, $countQuery);
    if (!$countResult) {
        throw new Exception("Error checking row count: " . pg_last_error($conn));
    }
    
    $rowCount = pg_fetch_result($countResult, 0, 0);
    if ($rowCount == 0) {
        throw new Exception("No data was imported into the oghma table");
    }

    // Commit transaction
    pg_query($conn, "COMMIT");

    // Close database connection
    pg_close($conn);

    // Redirect back to oghma_upload.php with success message
    header("Location: oghma_upload.php?message=Factory+reset+completed+successfully.+Imported+$rowCount+entries+from+version+$versionInfo");
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    pg_query($conn, "ROLLBACK");
    pg_close($conn);
    die("Reset failed: " . $e->getMessage());
}
?> 