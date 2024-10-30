<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$distroLogPath = __DIR__ . '/../../log/apache_error.log';

// Function to read and filter the error log from a given path
function readErrorLog($errorLogPath, $logType) {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        $errorLog = file($errorLogPath);
        $errorLog = array_reverse($errorLog);
        echo "<h1>Reading $logType $errorLogPath (Filtered only for errors)</h1>";
        echo '<div class="log-container">';
        foreach ($errorLog as $line) {
            if (strpos($line, '[php:error]') !== false) {
                echo '<div class="log-line">' . htmlspecialchars($line) . '</div>';
            }
        }
        echo '</div>';
        echo '<button class="button" onclick="window.location.href=\'downloadLog.php\'">Download Log</button>';
    } else {
        echo '<p>Error log file not found or not readable at: ' . htmlspecialchars($errorLogPath) . '</p>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>DwemerDistro Error Logs</title>
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
        }

        h1, h2 {
            color: #ffffff; /* White color for headings */
        }

        .log-container {
            max-height: 1800px;
            overflow-y: scroll;
            background-color: #000000; /* Black background for log */
            color: #f8f9fa;
            font-size: 14px;
            padding: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
            max-width: 1600px;
            margin-bottom: 20px;
        }

        .log-line {
            font-family: monospace;
            white-space: pre-wrap;
            margin-bottom: 5px;
        }

        .indent {
            padding-left: 10ch; /* 10 character spaces */
        }

        .indent5 {
            padding-left: 5ch; /* 5 character spaces */
        }

        .button {
            padding: 8px 16px;
            margin-top: 10px;
            cursor: pointer;
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 3px;
            font-size: 18px;    /* Increased font size */
            font-weight: bold;  /* Bold text for better visibility */
            transition: background-color 0.3s ease; /* Smooth hover transition */
        }

        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div class="indent5">
    <?php
    // Read the Distro log
    if (file_exists($distroLogPath) && is_readable($distroLogPath)) {
        readErrorLog($distroLogPath, "DwemerDistro");
    } else {
        echo '<p>Error reading log ' . htmlspecialchars($distroLogPath) . '</p>';
    }
    ?>
</div>
</body>
</html>
