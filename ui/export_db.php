<?php

// Initialize cURL session
$ch = curl_init();

// Set the URL for the login page
$login_url = 'http://localhost:8081/pgAdmin/redirect.php';

// Set the URL for the database export
$export_url = 'http://localhost:8081/pgAdmin/dbexport.php';

// Credentials for login
$username = 'dwemer';
$password = 'dwemer';

// Cookie file to store session cookies
$cookie_file = tempnam(sys_get_temp_dir(), 'pgadmin_cookie');

// Step 1: Log in to pgAdmin

// Prepare POST data for login
$login_fields = [
    'loginUsername' => $username,
    'loginPassword_451ff50a1b7f02422c818da4dd377d27' => $password,
    'loginServer'   => 'localhost:5432:allow',
    'loginSubmit'   => 'Login'
];

// Set options for the login request
curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($login_fields));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // Store cookies in a file
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

// Execute login request
$response = curl_exec($ch);

// Check for login errors
if (curl_errno($ch)) {
    echo 'Error during login: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

// Optional: Check if login was successful by searching for a string in the response
if (strpos($response, 'Login failed') !== false || strpos($response, 'Login to Dwemer PostgreSQL') !== false) {
    echo 'Login failed. Please check your credentials.';
    curl_close($ch);
    exit;
}

// Step 2: Request the database dump

// Prepare POST data for export
$export_fields = [
    'd_format'    => 'copy',
    'what'        => 'structureanddata',
    'sd_format'   => 'copy',
    'output'      => 'download',
    'action'      => 'export',
    'subject'     => 'database',
    'server'      => 'localhost:5432:allow',
    'database'    => 'dwemer'
];

// Set options for the export request
curl_setopt($ch, CURLOPT_URL, $export_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($export_fields));
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); // Use cookies from the login step
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Execute export request
$response = curl_exec($ch);

// Check for export errors
if (curl_errno($ch)) {
    echo 'Error during export: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Delete the temporary cookie file
unlink($cookie_file);

// Check if the response is the expected database dump
if (strpos($response, '<html') !== false) {
    echo 'Error: Received HTML content instead of database dump. Possible authentication issue.';
    exit;
}

// Serve the response as a file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="dwemer.sql"');
header('Content-Length: ' . strlen($response));
echo $response;

?>
