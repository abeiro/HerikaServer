<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$distroLogPath = __DIR__ . '/../../log/apache_error.log'; 

// Function to read and filter the error log from a given path
function readErrorLog($errorLogPath, $logType) {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        $errorLog = file($errorLogPath);
        $errorLog = array_reverse($errorLog);
        echo "<h1>Reading $logType error.log (Filtered only for errors)</h1>";
        echo '<div style="max-height: 800px; overflow-y: scroll; background-color: black; color: white; font-size: 14px;">'; // Increase text size
        foreach ($errorLog as $line) {
            if (strpos($line, '[php:error]') !== false) {
                echo htmlspecialchars($line) . "<br>";
            }
        }
        echo '</div>';
        echo '<button onclick="window.location.href=\'downloadLog.php\'">Download Log</button>'; // Add download button
    } else {
        echo "Error log file not found or not readable at: " . $errorLogPath;
    }
}

// Read the Distro log
if (file_exists($distroLogPath) && is_readable($distroLogPath)) {
    readErrorLog($distroLogPath, "DwemerDistro");
}
?>
