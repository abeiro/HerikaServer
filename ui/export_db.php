<?php
shell_exec('echo "localhost:5432:dwemer:dwemer":dwemer > /tmp/.pgpass;');
shell_exec('chmod 600 /tmp/.pgpass;');
$response=shell_exec('HOME=/tmp pg_dump -d dwemer -U dwemer  -h localhost');


// Serve the response as a file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="dwemer.sql"');
header('Content-Length: ' . strlen($response));
echo $response;


?>
