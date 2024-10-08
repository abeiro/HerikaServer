<?php

session_start();

$enginePath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;;
require($enginePath.DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.'conf_loader.php');

$confSchema=conf_loader_load_schema();

$buffer="<?php".PHP_EOL;

$oldGroup="";
$oldSubGroup="";

if (isset($_POST["profile"])) {
    $_SESSION["PROFILE"]=$_POST["profile"];
    unset($_POST["profile"]);
    unset($_POST["profileSelector"]);
}

$md5name=md5($_POST["HERIKA_NAME"]);
if (basename($_SESSION["PROFILE"])=="conf_{$md5name}.php") {
    @unlink($_SESSION["PROFILE"]);
    echo '<script>alert("Config file '.basename($_SESSION["PROFILE"]).' ('.addslashes($_POST["HERIKA_NAME"]).') has been deleted");parent.location.href="../conf_wizard.php?ts='.(time()."#".$_GET["sc"]).'"</script>';
} else if (basename($_SESSION["PROFILE"])=="conf.php")  {
    echo '<script>alert("Default profile cannot be deleted")</script>';

}


?>
