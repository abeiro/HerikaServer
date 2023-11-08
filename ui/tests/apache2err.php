<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$distroLogPath = './error.log'; 
$uwampLogPath = '..\..\..\..\bin\apache\logs\error.log'; 

// Function to read and filter the error log from a given path
function readErrorLog($errorLogPath, $logType) {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        $errorLog = file($errorLogPath);
        $errorLog = array_reverse($errorLog);
        echo "<h1>Reading $logType log</h1>";
        echo '<div style="max-height: 400px; overflow-y: scroll; background-color: black; color: white; font-size: 16px;">'; // Increase text size
        foreach ($errorLog as $line) {
            if (strpos($line, '[php:error]') !== false) {
                echo htmlspecialchars($line) . "<br>";
            }
        }
        echo '</div>';
    } else {
        echo "Error log file not found or not readable at: " . $errorLogPath;
    }
}

// Try to read the Distro log, and if it's not available, read the UWAMP log
if (file_exists($distroLogPath) && is_readable($distroLogPath)) {
    readErrorLog($distroLogPath, "DwemerDistro");
} elseif (file_exists($uwampLogPath) && is_readable($uwampLogPath)) {
    readErrorLog($uwampLogPath, "UWAMP");
}
?>
