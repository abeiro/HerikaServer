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

// Set headers to initiate a file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="AIAgent.ini"');

// Output the content
echo $content;
?>