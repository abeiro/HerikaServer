<?php


$enginePath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;;
require($enginePath.DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.'conf_loader.php');

$confSchema=conf_loader_load_schema();

$buffer="<?php".PHP_EOL;

$oldGroup="";
$oldSubGroup="";

foreach ($_POST as $k=>$v) {
    
    $fullNameHierch=explode("@",$k);
    $plainNameHierch=strtr($k,array("@"=>" "));
    
    if (is_array($v))
        $value=json_encode($v,true);
    else if ($confSchema[$plainNameHierch]["type"]=="number")
        $value="".addcslashes($v,"'")."";
    else if ($confSchema[$plainNameHierch]["type"]=="boolean")
        $value=($v=="true")?"true":"false";
    else
        $value="'".addcslashes($v,"'")."'";
    
    
    
    if ($oldGroup!=$fullNameHierch[0]) {
           $buffer.=PHP_EOL.PHP_EOL;
           $oldGroup=$fullNameHierch[0];
    }
    
    if (isset($fullNameHierch[1]))
        if ($oldSubGroup!=$fullNameHierch[1]) {
            $buffer.=PHP_EOL;
            $oldSubGroup=$fullNameHierch[1];
        }
    
    if (sizeof($fullNameHierch)==1) {
        if (isset($confSchema[$plainNameHierch]["description"])) {
            $buffer.="//".$confSchema[$plainNameHierch]["description"].PHP_EOL;
        }
        $buffer.="\${$fullNameHierch[0]}=$value;".PHP_EOL;
    }
    
    if (sizeof($fullNameHierch)==2) {
        $inlineComment="";
        if (isset($confSchema[$plainNameHierch]["description"])) {
            $inlineComment="//".$confSchema[$plainNameHierch]["description"];
        }
        $buffer.="\${$fullNameHierch[0]}[\"$fullNameHierch[1]\"]=$value;\t$inlineComment".PHP_EOL;
    }
    
    
    if (sizeof($fullNameHierch)==3) {
        $inlineComment="";

        if (isset($confSchema[$plainNameHierch]["description"])) {
            $inlineComment.="//".$confSchema[$plainNameHierch]["description"];
        }
        $buffer.="\${$fullNameHierch[0]}[\"$fullNameHierch[1]\"][\"$fullNameHierch[2]\"]=$value;\t$inlineComment".PHP_EOL;
    }
    
    
}
$buffer.="?>".PHP_EOL;

if (isset($_GET["save"])) {
    $result=file_put_contents($enginePath."conf".DIRECTORY_SEPARATOR."conf.php",$buffer);
    echo '<!DOCTYPE html>
        <html lang="en" >
        <head>
        <style>
        body {
        background-color: black;
        color: white;
        font-size: small ; 
        font-family: Consolas,Monaco,Lucida Console,Liberation Mono,DejaVu Sans Mono,Bitstream Vera Sans Mono,Courier New, monospace;
        width: 100%;
        display: inline-block;
        }
        </style>
        </head>
        <body>
    ';
    
    
    if ($result!==false) {
        echo "Writing config file.....";
        echo '<script>alert("Config file has been written");parent.location.reload(true)</script>';
    } else {
        echo "Writing config file.....";
        echo "Some error ocurred.".PHP_EOL;
        
    }
    
    
    
} else {
    $_POST["text"]=$buffer;
    require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf_checker.php");
}

?>
