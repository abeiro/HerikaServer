<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>
    
<?php

error_reporting(E_ERROR);
session_start();

ob_start();

$url = 'conf_editor.php';
$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
$configFilepath =$rootPath."conf".DIRECTORY_SEPARATOR;

require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.sample.php");	// Should contain defaults
if (file_exists($rootPath."conf".DIRECTORY_SEPARATOR."conf.php"))
    require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.php");	// Should contain current ones

$TITLE = "Config Wizard";

require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.'conf_loader.php');

$configFilepath=realpath($configFilepath).DIRECTORY_SEPARATOR;

// Profile selection
$GLOBALS["PROFILES"]["default"]="$configFilepath/conf.php";
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf ) {
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

} else
    $_SESSION["PROFILE"]="$configFilepath/conf.php";
// End of profile selection

include("tmpl/head.html");
$debugPaneLink = false;
include("tmpl/navbar.php");

echo ' <form action="" method="post" name="mainC" class="confwizard" id="top">
<input type="hidden" name="profile" value="'.$_SESSION["PROFILE"].'" />
';


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

function getFilesByExtension($directory, $extension) {
    // Get all files in the directory
    $files = scandir($directory);

    // Filter files by extension
    $filteredFiles = array_filter($files, function($file) use ($extension) {
        return pathinfo($file, PATHINFO_EXTENSION) == $extension;
    });

    return $filteredFiles;
}

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
        if (trim($legend)) {
            $summary[md5($legend)]["main"]=$legend;
            $lastLegend=$legend;
        }
        
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
        
       
        echo "<fieldset><legend id='".md5($legend)."'>$legend</legend>";
        
         if (trim($legend))
            $summary[md5($lastLegend)]["childs"][]=$legend;
        
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
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input type='text' value=\"$fieldValue\" name='$fieldName'><span> {$parms["description"]}</span></p>".PHP_EOL;

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
        $buffer = "";
        if (!isset($parms["currentValue"])) {
            $parms["currentValue"] = [];
        }
    
        foreach ($parms["values"] as $item) {
            $checked = in_array($item, $parms["currentValue"]) ? "checked" : "";
            $buffer .= "<label><input type='checkbox' name='{$fieldName}[]' value='$item' $checked> $item</label><br>";
        }
    
        echo "<p class='conf-item'><label>$pname</label><div>$buffer</div><span>{$parms["description"]}</span></p>".PHP_EOL;

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

    } else if ($parms["type"]=="file") {
        $availableFiles=getFilesByExtension($parms["path"],$parms["filter"]);
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><select  class='files' type='string' name='$fieldName'>".PHP_EOL;
        foreach ($availableFiles as $file)
            echo "<option ".(($file==$parms["currentValue"])?"selected":"")." value='$file'>$file</option>";
        echo "</select><span>{$parms["description"]}</span></p>";
        
    }  else if ($parms["type"]=="util") {
        $checkButton="<button class='' type='button' onclick=\"callHelper('{$parms["action"]}')\">{$parms["name"]}</button>";
        echo "<p class='conf-item'>$checkButton<span>{$parms["description"]}</span></p>".PHP_EOL;
        
    }

}
echo str_repeat("</fieldset>", $lvl1);
echo str_repeat("</fieldset>", $lvl2);

echo '</form>';

echo "<div style='position:fixed;top:0px;right:5px;background-color:black;font-size:1em;border:1px solid grey;margin:85px 5px;padding:5px;'><span><strong>Quick Access for {$GLOBALS["CURRENT_PROFILE_CHAR"]}</strong></span><ul>";
echo "<li><a href='#top'>Character Configuration</a></li>";


foreach ($summary as $k=>$item) {
    echo "<li>&nbsp;<a href='#$k'>{$item["main"]}</a></li>";
    
    foreach ($item["childs"] as $localhash=>$subtitle) {
        echo "<li class='subchild'>&nbsp;<a href='#" . md5($subtitle) . "'>$subtitle</a></li>";
    }
    
}

//echo "<li><a href='#end'>Check & Save</a></li>";
echo '<input class="btn btn-info" type="button" name="save" value="Save" onclick=\'document.getElementById("top").target="checker";document.getElementById("top").action="tools/conf_writer.php?save=true&sc="+getAnchorNH();document.getElementById("top").submit();\' /></p>';
echo "</ul></div>";


echo '<p id="end"><input class="btn btn-info" type="button" name="check" value="Check [DOES NOT WORK]" onclick=\'document.forms[0].target="checker";document.forms[0].action="tools/conf_writer.php";document.forms[0].submit()\' />';
echo ' :: <input class="btn btn-info" type="button" name="save" value="Save" onclick=\'document.forms[0].target="checker";document.forms[0].action="tools/conf_writer.php?save=true";document.forms[0].submit();\' /></p>';
echo '<iframe class="w-75" name="checker" border="1" style="min-height:200px;" scrolling="no" src="tmpl/black.html"></iframe>';
echo '<script>window.onload = scrollToHash;</script>';



include("tmpl/footer.html");


$buffer = ob_get_contents();
ob_end_clean();
$title = "AI Follower Framework";
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;


