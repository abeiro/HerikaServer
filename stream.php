<?php

/* Legacy entry point */

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
$FUNCTIONS_ARE_ENABLED=false;
require($path . "main.php");
die();


?>
