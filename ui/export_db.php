<?php

// Specify the path to the SQLite database file
$originalDbFile = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "mysqlitedb.db";

// Read the contents of the SQLite database file
$fileContents = file_get_contents($originalDbFile);

// Find the position of the string "SQLite format 3"
$startPosition = strpos($fileContents, "SQLite format 3");


// Set the appropriate headers for downloading the SQLite file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="mysqlitedb.db"');

// Output the SQLite content starting from the identified position
echo substr($fileContents, $startPosition);

// Terminate the script
exit;

?>
