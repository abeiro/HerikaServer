<?php
$file_path = '../conf/conf.php'; // Relative path from the location of conf_export.php

if (file_exists($file_path)) {
    // Set the headers to force a download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file_path));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));

    // Read the file and output it to the browser
    readfile($file_path);
    exit;
} else {
    // File not found
    die('The file you requested does not exist.');
}
?>
