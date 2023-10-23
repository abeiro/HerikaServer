<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>


<?php


$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

// HTML template
echo file_get_contents('template.html');

$db=new sql();
$res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
$last_gamets=$res[0]["last_gamets"]+1;



echo "

<p>Chat Window</p>
<div id='chatWindow' style='width:80%;height:300px;overflow-y:auto'></div>
<form action='index.php' method='post'>
<p>{$GLOBALS["PLAYER_NAME"]}</p>
<input type='text' name='inputText' id='inputText' size='100' />
<input type='hidden' name='localts' id='localts' value='".time()."' />
<input type='hidden' name='gamets' id='gamets' value='0' />
<input type='hidden' name='playerName' id='playerName' value='{$GLOBALS["PLAYER_NAME"]}' />
<input type='hidden' name='herikaName' id='herikaName' value='{$GLOBALS["HERIKA_NAME"]}' />
<input type='hidden' name='last_gamets' id='last_gamets' value='$last_gamets' />
<input type='button' name='send' value='Send' onclick='reqSend()'/>
</form>

<iframe src='../../' style='width:80%;min-height:300px;margin-top:50px;'></iframe>
</body>
</html>
";
?>
