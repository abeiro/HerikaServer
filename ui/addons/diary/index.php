<?php

error_reporting(E_ALL);
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf/conf.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib/sql.class.php");


$db = new sql();

$data=[];
$n=3; // Starting at page 3
$pageElements="";

$results = $db->query("SElECT  topic,content,tags,people  FROM diarylog order by gamets asc");
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
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
