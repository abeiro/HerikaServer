<?php

session_start();
$_SESSION["PROFILE"]=$_POST["profileSelector"];
session_write_close();
header("Location: conf_wizard.php");
?>
