<?php 

error_reporting(E_ALL);
session_start();



$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$configFilepath=realpath($enginePath."conf".DIRECTORY_SEPARATOR);

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$GLOBALS["PROFILES"]["default"]="$configFilepath/conf.php";
foreach (glob($configFilepath . '/conf_????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash]=$mconf;
    }
}

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"],$GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);

} else {
  echo "Profile in sesssion".$_SESSION["PROFILE"].PHP_EOL;
  echo "Availabel profiles".PHP_EOL;
  print_r($GLOBALS["PROFILES"]);
  die();
}
  

//print_r($GLOBALS);
$db = new sql();

$data=[];
$n=3; // Starting at page 3
$pageElements="";

echo <<<HEAD
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>
HEAD;

$cn=$db->escape($GLOBALS["HERIKA_NAME"]);
$results = $db->query("SElECT  topic,content,tags,people  FROM diarylog where people='$cn' order by gamets asc");
while ($row = $db->fetchArray($results)) {
  $data[] = $row;
  $pageElements.="
    <div class=\"page text-page\" onclick=\"movePage(this, $n)\"><h3>{$row["topic"]}</h3>{$row["content"]}
    <span class='readbutton' onclick='speak(document.querySelector(\"body > div.book > div:nth-child($n)\").innerHTML);event.stopPropagation()')>read</span></div>";
  $n++;  
}



  


$SUBSTITUTIONS=[
  "#BOOK_NAME#"=>"$HERIKA_NAME's diary",
  "#HERIKA_NAME#"=>"$HERIKA_NAME",
  "##PAGES##"=>"$pageElements"
];

$htmlData=file_get_contents("template.html");

$htmlDataMangled=str_replace(array_keys($SUBSTITUTIONS), array_values($SUBSTITUTIONS), $htmlData);

echo $htmlDataMangled;





?>
