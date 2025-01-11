<?php

$jsonPersonalitiesTableName = "json_personalities";
$filePath = realpath(__DIR__ . '/../data/personalities_json_11_18_2024.csv');
$query = "
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public'
    AND table_name = '$jsonPersonalitiesTableName';
";


$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]) {
    $db->execQuery("
    CREATE TABLE IF NOT EXISTS $jsonPersonalitiesTableName (
    npc_name character varying(256) PRIMARY KEY,
    personality JSONB
    )");
    echo "<script>alert('creating $jsonPersonalitiesTableName')</script>";
}

$query = "
    SELECT COUNT(*) 
    FROM $jsonPersonalitiesTableName;
";
$tableRowsCount = $db->fetchAll($query);

if($tableRowsCount[0]["count"] == 0) {
    $db->execQuery("
        COPY $jsonPersonalitiesTableName (personality, npc_name)
        FROM '$filePath'
        DELIMITER ','
        CSV HEADER;
    ");
}

?>
