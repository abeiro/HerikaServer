<?php
shell_exec('echo "localhost:5432:dwemer:dwemer":dwemer > /tmp/.pgpass;');
shell_exec('chmod 600 /tmp/.pgpass;');
$filename=date("dMy").".sql";
$response=shell_exec('HOME=/tmp pg_dump -d dwemer -U dwemer  -h localhost > /var/www/html/HerikaServer/data/export_'.$filename);

header("Location: /HerikaServer/data/export_".$filename);

// Serve the response as a file download
//header('Content-Type: application/octet-stream');
//header('Content-Disposition: attachment; filename="dwemer.sql"');
//header('Content-Length: ' . strlen($response));
//echo $response;


?>
