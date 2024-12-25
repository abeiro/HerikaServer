<?php
if (isset($_GET['reset_tables'])) {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'dwemer';
    $username = 'dwemer';
    $password = 'dwemer';

    try {
        $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

        if (!$conn) {
            throw new Exception("Failed to connect to database.");
        }

        $queries = [
            "DROP TABLE IF EXISTS input_queue_websocket CASCADE",
            "DROP TABLE IF EXISTS output_queue_websocket CASCADE",
            "CREATE TABLE input_queue_websocket (
                id SERIAL PRIMARY KEY,
                message TEXT NOT NULL,
                timestamp TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE output_queue_websocket (
                id SERIAL PRIMARY KEY,
                response TEXT NOT NULL,
                timestamp TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $query) {
            $result = pg_query($conn, $query);

            if (!$result) {
                throw new Exception("Error resetting tables: " . pg_last_error($conn));
            }
        }

        echo "Tables reset successfully.";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        if ($conn) {
            pg_close($conn);
        }
    }
    exit;
}

if (isset($_GET['check_tables'])) {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'dwemer';
    $username = 'dwemer';
    $password = 'dwemer';

    try {
        $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

        if (!$conn) {
            throw new Exception("Failed to connect to database.");
        }

        $output = "";
        $tables = ["input_queue_websocket", "output_queue_websocket"];

        foreach ($tables as $table) {
            $result = pg_query($conn, "SELECT * FROM $table");

            if (!$result) {
                $output .= "Error fetching from $table: " . pg_last_error($conn) . "\n";
                continue;
            }

            $output .= "Contents of $table:\n";
            while ($row = pg_fetch_assoc($result)) {
                $output .= print_r($row, true) . "\n";
            }
        }

        echo nl2br($output);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        if ($conn) {
            pg_close($conn);
        }
    }
    exit;
}

$zipFile = __DIR__ . '/websocket.zip';
$sourceDir = __DIR__ . '/websocket_util';

function createZip($sourceDir, $zipFile) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    return $zip->close();
}

if (createZip($sourceDir, $zipFile)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="websocket.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);

    unlink($zipFile);
    exit;
} else {
    echo "Error: Failed to create websocket.zip.";
}
?>