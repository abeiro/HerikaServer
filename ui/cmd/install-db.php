<?php

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "Failed to connect to database.\n";
    die();
}

// Drop and recreate database
$Q[]="DROP SCHEMA IF EXISTS $schema CASCADE";
$Q[]="DROP SCHEMA IF EXISTS plugins CASCADE";
$Q[]="DROP EXTENSION IF EXISTS vector CASCADE";
$Q[]="CREATE SCHEMA $schema";
$Q[]="CREATE SCHEMA plugins";
$Q[]="CREATE EXTENSION vector";

foreach ($Q as $QS) {
  $r = pg_query($conn, $QS);
  if (!$r) {
    echo pg_last_error($conn);
    die();
  } else {
    echo "$QS ok<br/>";
  }
  
}

// Path to SQL file to import
$sqlFile = $enginePath.'/data/database_default.sql';

// Command to import SQL file using psql
$psqlCommand = "PGPASSWORD=$password psql -h $host -p $port -U $username -d $dbname -f $sqlFile";

// Execute psql command
$output = [];
$returnVar = 0;
exec($psqlCommand, $output, $returnVar);

if ($returnVar !== 0) {
    echo "Failed to import SQL file.\n";
    echo implode("\n", $output) . "\n";
    exit;
}

echo "SQL file imported successfully.\n";
echo implode("\n", $output) . "\n";

$coreActionSeedFile = $enginePath.'/data/core_action_seed.sql';
if (file_exists($coreActionSeedFile) && trim(strval(file_get_contents($coreActionSeedFile))) !== '') {
    $seedCommand = "PGPASSWORD=$password psql -h $host -p $port -U $username -d $dbname -f $coreActionSeedFile";
    $seedOutput = [];
    $seedReturnVar = 0;
    exec($seedCommand, $seedOutput, $seedReturnVar);

    if ($seedReturnVar !== 0) {
        echo "Failed to import core_action seed SQL file.\n";
        echo implode("\n", $seedOutput) . "\n";
        exit;
    }

    echo "core_action seed SQL imported successfully.\n";
    echo implode("\n", $seedOutput) . "\n";
}

echo "Import completed.\n";



?>
