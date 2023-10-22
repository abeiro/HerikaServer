<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>
    
<?php

error_reporting(E_ERROR);

ob_start();

$url = 'conf_editor.php';
$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.sample.php");	// Should contain defaults
if (file_exists($rootPath."conf".DIRECTORY_SEPARATOR."conf.php"))
    require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.php");	// Should contain current ones

$TITLE = "Config Wizard";

require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.'conf_loader.php');

include("tmpl/head.html");
$debugPaneLink = false;
include("tmpl/navbar.php");

echo ' <form action="" method="post" name="mainC" class="confwizard" id="top">';


$currentConf=conf_loader_load();
$currentConfTitles=conf_loader_load_titles();

$currentGroup="";
$currentSubGroup="";
$confDepth=0;
$primaryGroups=[

];

$primarySubGroups=[

];

$lvl1=0;
$lvl2=0;

foreach ($currentConf as $pname=>$parms) {

    /* Groupping stuff */
    $pnameA=explode(" ", $pname);

    if (isset($parms["helpurl"])) {
        $parms["description"].=" <a target='_blank' href='{$parms["helpurl"]}'>[help/doc]</a>";
    }
    
    if (!isset($pSeparator["{$pnameA[0]}"])) {
        echo str_repeat("</fieldset>", $lvl1);
        echo str_repeat("</fieldset>", $lvl2);
        
        if (isset($currentConfTitles["{$pnameA[0]}"])) {
            $legend=$currentConfTitles["{$pnameA[0]}"];
        }
        else {
            $legend=$primaryGroups[$pnameA[0]];
            
        }
        if (trim($legend))
            $summary[md5($legend)]=$legend;
        echo "<fieldset><legend id='".md5($legend)."'>$legend</legend>";
        $lvl1=1;
        $lvl2=0;


    }

    if ((!isset($sSeparator["{$pnameA[0]}{$pnameA[1]}"]))&&(sizeof($pnameA)>2)) {
        echo str_repeat("</fieldset>", $lvl2);
        
        if (isset($currentConfTitles["{$pnameA[0]} {$pnameA[1]}"])) {
            $legend=$currentConfTitles["{$pnameA[0]} {$pnameA[1]}"];
            
        }
        else {
            
            $legend=$primarySubGroups[$pnameA[1]];
        }
        
       
        echo "<fieldset><legend>$legend</legend>";
        
        if (!isset($pSeparator["{$pnameA[0]}"])) {
            $lvl2=1;
        }

    }

    $sSeparator["{$pnameA[0]}{$pnameA[1]}"]=true;
    $pSeparator["{$pnameA[0]}"]=true;

    $fieldName=strtr($pname,array(" "=>"@"));

    if (!is_array($parms["currentValue"]))
        $fieldValue=stripslashes($parms["currentValue"]);
    
    if ($parms["type"]=="string") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input type='text' value='$fieldValue' name='$fieldName'><span> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="longstring") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><textarea width='200px' name='$fieldName'>$fieldValue</textarea><span>{$parms["description"]}</span></p> ".PHP_EOL;

    } else if ($parms["type"]=="url") {
        $checkButton="<button class='url' type='button' onclick=\"checkUrlFromServer('$fieldName')\">Check</button>";
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input class='url' type='url' value='{$parms["currentValue"]}' name='$fieldName'/>$checkButton<span> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="select") {
        $buffer="";
        /*if (!isset($parms["currentValue"]))
            $parms["currentValue"]=[];
        if (!is_array($parms["currentValue"]))
            print_r($parms);*/
        foreach ($parms["values"] as $item)
            $buffer.="<option value='$item' ".(($item==$parms["currentValue"])?"selected":"").">$item</option>";
        
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><select name='$fieldName'>$buffer</select><span> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="selectmultiple") {
        $buffer="";
        if (!isset($parms["currentValue"]))
            $parms["currentValue"]=[];
        
        foreach ($parms["values"] as $item)
            $buffer.="<option value='$item' ".((in_array($item,$parms["currentValue"]))?"selected":"").">$item</option>";
        
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><select multiple='true' name='{$fieldName}[]'>$buffer</select><span> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="boolean") {
        
        $buffer="";$rtrue="";$rfalse="";
        
        if ($parms["currentValue"])
            $rtrue="checked";
        else
            $rfalse="checked";
        
        $id=uniqid();
        $id2=uniqid();
        echo "<p class='conf-item'>$pname<br/>
            <input type='radio' name='$fieldName' value='true' $rtrue id='$id'/><label for='$id'>True</label>
            <input type='radio' name='$fieldName' value='false' $rfalse id='$id2'/><label for='$id2'>False</label>
            <span> {$parms["description"]}</span></p>".PHP_EOL;
   
        
    } else if ($parms["type"]=="integer") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input type='number' inputmode='numeric' step='1' value='{$parms["currentValue"]}' name='$fieldName'><span>Integer: {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="number") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input type='number' inputmode='numeric' step='0.01' value='{$parms["currentValue"]}' name='$fieldName'><span>Decimal: {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="apikey") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input  class='apikey' type='string'  value='{$parms["currentValue"]}' name='$fieldName'><span>{$parms["description"]}</span></p>".PHP_EOL;

    }

}
echo str_repeat("</fieldset>", $lvl1);
echo str_repeat("</fieldset>", $lvl2);

echo '</form>';

echo "<div style='position:fixed;top:0px;right:5px;background-color:black;font-size:1em;border:1px solid grey;margin:85px 5px;padding:5px;'><span><strong>Quick access</strong></span><ul>";
echo "<li><a href='#top'>Character Configuration</a></li>";
foreach ($summary as $item) {
    if (strpos($item,"::")!==false)
        continue;
    echo "<li><a href='#".md5($item)."'>$item</a></li>";
}
   echo "<li><a href='#end'>Check & Save</a></li>";
echo "</ul></div>";


echo '<p id="end"><input class="btn btn-info" type="button" name="check" value="Check" onclick=\'document.forms[0].target="checker";document.forms[0].action="tools/conf_writer.php";document.forms[0].submit()\' />';
echo ' :: <input class="btn btn-info" type="button" name="save" value="Save" onclick=\'document.forms[0].target="checker";document.forms[0].action="tools/conf_writer.php?save=true";document.forms[0].submit();\' /></p>';
echo '<iframe class="w-75" name="checker" border="1" style="min-height:200px;" scrolling="no" src="tmpl/black.html"></iframe>';




include("tmpl/footer.html");


$buffer = ob_get_contents();
ob_end_clean();
$title = "Herika Server";
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;


