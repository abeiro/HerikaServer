<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php

// Specify the path to the SQLite database file
$originalDbFile = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "mysqlitedb.db";

// Download the existing SQLite database file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="mysqlitedb.db"');
readfile($originalDbFile);
exit;

?>
</body>
</html>
