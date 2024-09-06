<?php 

$currentGithub=trim(file_get_contents("https://raw.githubusercontent.com/abeiro/HerikaServer/aiagent/.version.txt"));
$currentLocal=trim(file_get_contents(__DIR__."/../../.version.txt"));

echo json_encode(["last"=>$currentGithub,"local"=>$currentLocal]);

?>