<?php
session_start();

// Enable error reporting (for development/testing)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
if (!$conn) {
    echo "Failed to connect to the database: " . pg_last_error();
    exit;
}

// Delete all entries from memory_summary
$query = "DELETE FROM {$schema}.memory_summary;";
$result = pg_query($conn, $query);

if ($result) {
    echo "All entries in the memory_summary table have been deleted successfully.";
} else {
    echo "Error deleting entries from memory_summary: " . pg_last_error($conn);
}

// Close the connection
pg_close($conn);
?>
