<?php
// Define constants
define('PATH', '/HerikaServer/comm.php');
define('POLINT', 1);

// Get server address and port from the $_SERVER array
$server = $_SERVER['SERVER_ADDR'];
$port = $_SERVER['SERVER_PORT'];

// Prepare the content for the ini file
$content = "SERVER=$server\n";
$content .= "PORT=$port\n";
$content .= "PATH=" . PATH . "\n";
$content .= "POLINT=" . POLINT . "\n";

// Define the folder structure and filename within the zip
$iniFilePathInZip = 'AIAgent Custom ini/SKSE/Plugins/AIAgent.ini';
$zipFileName = 'AIAgentCustom.zip';

// Create a new ZipArchive instance
$zip = new ZipArchive();
$tempZipFile = tempnam(sys_get_temp_dir(), 'AIAgent') . '.zip';

if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Add the INI file content to the zip archive with the specified path
    $zip->addFromString($iniFilePathInZip, $content);
    $zip->close();

    // Set headers to initiate a file download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . filesize($tempZipFile));

    // Output the content of the zip file
    readfile($tempZipFile);

    // Delete the temporary zip file
    unlink($tempZipFile);
    exit;
} else {
    // Handle error: Unable to create zip file
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Could not create the zip file.";
    exit;
}
?>