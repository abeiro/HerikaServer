<?php

session_start();


$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
}

$pattern = '/conf_([a-f0-9]+)\.php/';
preg_match($pattern, basename($_SESSION["PROFILE"]), $matches);
$hash = $matches[1];    

// HTML template
echo file_get_contents('template.html');

$db=new sql();
$res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
$last_gamets=$res[0]["last_gamets"]+1;



echo "
<!DOCTYPE html>
<html>
<head>
    <link rel=\"icon\" type=\"image/x-icon\" href=\"images/favicon.ico\">
</head>
<body>



<p>Chat Window ({$GLOBALS["HERIKA_NAME"]})</p>
<div id='chatWindow' style='width:80%;height:300px;overflow-y:auto'></div>
<form action='index.php' method='post'>
<p>{$GLOBALS["PLAYER_NAME"]}</p>
<input type='text' name='inputText' id='inputText' size='100' placeholder=\"Don't use enter. Use button send\"/>
<input type='hidden' name='localts' id='localts' value='".time()."' />
<input type='hidden' name='gamets' id='gamets' value='0' />
<input type='hidden' name='playerName' id='playerName' value='{$GLOBALS["PLAYER_NAME"]}' />
<input type='hidden' name='herikaName' id='herikaName' value='{$GLOBALS["HERIKA_NAME"]}' />
<input type='hidden' name='profile' id='profile' value='{$hash}' />
<input type='hidden' name='conf' id='profile' value='{$_SESSION["PROFILE"]}' />
<input type='hidden' name='last_gamets' id='last_gamets' value='$last_gamets' />
<input type='button' name='send' value='Send' onclick='reqSend()'/>
</form>

<iframe src='../../' style='width:80%;min-height:300px;margin-top:50px;'></iframe>
</body>
</html>
";
?>
