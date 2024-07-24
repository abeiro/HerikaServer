<?php


session_start();
$_SESSION["OPTION_TO_SHOW"]=$_GET["c"];

setcookie("OPTION_TO_SHOW", $_GET["c"]);

header("Location: {$_SERVER["HTTP_REFERER"]}");
die();
?>
