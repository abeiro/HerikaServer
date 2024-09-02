<?php

session_start();

error_reporting(E_ALL);

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$enginePath = $localPath;

require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");



require_once($enginePath."/itt/itt-{$GLOBALS["ITTFUNCTION"]}.php");

echo "<div style='width:50%;float:left'><b>Sample image sent</b><img style='width:-webkit-fill-available' src='../../debug/data/sample.jpg' /></div>";
$description=itt("$enginePath/debug/data/sample.jpg",'');
//$description="In this Skyrim image, we see two characters standing in a snowy landscape. The woman, dressed in a silver armor suit, has a determined look on her face. She is holding a sword, ready for action. Beside her, a man in a fur-lined cloak and leather armor is also armed with a sword. They are both mounted on horses, suggesting they are on a journey or quest. The environment around them is cold and wintry, with snow-covered trees and a misty atmosphere. The dialogue between the characters hints at a narrative where the woman, named Lydia, is questioning the man about his intentions and the importance of their mission. The overall scene is one of adventure and camaraderie in a fantasy setting";
echo "<div style='width:49%;float:right'><b>ITT output</b> <br/>$description</div>";






?>
