<?php
$logPath = __DIR__ . '/../../log/error.log'; // Adjust the path as necessary

if (file_exists($logPath) && is_readable($logPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="error.log"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($logPath));
    readfile($logPath);
    exit;
}
?>