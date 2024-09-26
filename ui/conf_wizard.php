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

require_once($rootPath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");

require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.sample.php");	// Should contain defaults
if (file_exists($rootPath."conf".DIRECTORY_SEPARATOR."conf.php"))
    require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.php");	// Should contain current ones

$TITLE = "Config Wizard";

require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.'conf_loader.php');

$configFilepath=realpath($configFilepath).DIRECTORY_SEPARATOR;

// Profile selection
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash]=$mconf;
    }
}


// Function to compare modification dates
function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Sort the profiles by modification date descending
if (is_array($GLOBALS["PROFILES"]))
    usort($GLOBALS["PROFILES"], 'compareFileModificationDate');
else
    $GLOBALS["PROFILES"]=[];

$GLOBALS["PROFILES"]=array_merge(["default"=>"$configFilepath/conf.php"],$GLOBALS["PROFILES"]);


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


if ($_SESSION["PROFILE"]=="$configFilepath/conf.php") {
    $DEFAULT_PROFILE=true;
} else 
    $DEFAULT_PROFILE=false;
    
 
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
    
    $access=["basic"=>0,"pro"=>1,"wip"=>2];
    if ( isset($parms["userlvl"]) && !($access[$parms["userlvl"]]<=$access[$_SESSION["OPTION_TO_SHOW"]]))  {
        $MAKE_NO_VISIBLE_MARK=" style='display:none' ";
    } else
        $MAKE_NO_VISIBLE_MARK="";
    
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
        
        echo "<fieldset  $MAKE_NO_VISIBLE_MARK><legend id='".md5($legend)."'>$legend</legend>";
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
        
       
        //echo "<legend id='".md5($legend)."'>$legend</legend><fieldset title='$legend'  id='f_".md5($legend)."' class='visible-fieldset' $MAKE_NO_VISIBLE_MARK>";
        echo "<legend id='".md5($legend)."'>$legend</legend><fieldset title='$legend'  id='f_".md5($legend)."' class='unvisible-fieldset' $MAKE_NO_VISIBLE_MARK>";
        
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
    
    
    if ($DEFAULT_PROFILE && $fieldName=="HERIKA_NAME") {
        $fieldValue="The Narrator";
        $FORCE_DISABLED=" readonly='true' ";
    } else {
        $FORCE_DISABLED="";
    }
    
    if (!$DEFAULT_PROFILE && $parms["scope"]=="global") {
        $FORCE_DISABLED=" readonly='true' disabled='true' title='This is a global parameter. Set it on default profile' ";
    }
    
    if ($parms["scope"]=="constant") {
        $FORCE_DISABLED=" readonly='true' disabled='true' title='This is a readonly parameter'";
    }
    
    
    if (true) {
        echo "<div $MAKE_NO_VISIBLE_MARK class='softdiv'>";
        if ($parms["type"]=="string") {
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input $FORCE_DISABLED type='text' value=\"$fieldValue\" name='$fieldName'><span> {$parms["description"]}</span></p>".PHP_EOL;

        } else if ($parms["type"]=="longstring") {
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><textarea $FORCE_DISABLED name='$fieldName'>$fieldValue</textarea><span>{$parms["description"]}</span></p> ".PHP_EOL;

        } else if ($parms["type"]=="url") {
            $checkButton="<button class='url' type='button' onclick=\"checkUrlFromServer('$fieldName')\">Check</button>";
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input  $FORCE_DISABLED class='url' type='url' value='{$parms["currentValue"]}' name='$fieldName'/>$checkButton<span> {$parms["description"]}</span></p>".PHP_EOL;

        } else if ($parms["type"]=="select") {
            $buffer="";
            /*if (!isset($parms["currentValue"]))
                $parms["currentValue"]=[];
            if (!is_array($parms["currentValue"]))
                print_r($parms);*/
            foreach ($parms["values"] as $item)
                $buffer.="<option value='$item' ".(($item==$parms["currentValue"])?"selected":"").">$item</option>";
            
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><select $FORCE_DISABLED name='$fieldName'>$buffer</select><span> {$parms["description"]}</span></p>".PHP_EOL;

        } else if ($parms["type"]=="selectmultiple") {
            $buffer = "";
            if (!isset($parms["currentValue"])) {
                $parms["currentValue"] = [];
            }
        
            foreach ($parms["values"] as $item) {
                $addnote="";
                if ($fieldName=="CONNECTORS") 
                    if (in_array($item,["openrouter","openai","koboldcpp"])) {
                        if ($access[$_SESSION["OPTION_TO_SHOW"]]<2)
                            continue;
                        else
                            $addnote="*";
                        
                    }
                
                $checked = in_array($item, $parms["currentValue"]) ? "checked" : "";
                $buffer .= "<input type='checkbox' name='{$fieldName}[]' value='$item' $checked> $item $addnote<br>";
            }
        
            echo "<p class='conf-item'><label>$pname</label>$buffer<span>{$parms["description"]}</span></p>".PHP_EOL;

        } else if ($parms["type"]=="boolean") {
            
            $buffer="";$rtrue="";$rfalse="";
            
            if ($parms["currentValue"])
                $rtrue="checked";
            else
                $rfalse="checked";
            
            $id=uniqid();
            $id2=uniqid();
            echo "<p class='conf-item' $FORCE_DISABLED>$pname<br/>
                <input $FORCE_DISABLED type='radio' name='$fieldName' value='true' $rtrue id='$id'/><label for='$id'>True</label>
                <input $FORCE_DISABLED type='radio' name='$fieldName' value='false' $rfalse id='$id2'/><label for='$id2'>False</label>
                <span $FORCE_DISABLED> {$parms["description"]}</span></p>".PHP_EOL;
    
            
        } else if ($parms["type"]=="integer") {
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input type='number' $FORCE_DISABLED inputmode='numeric' step='1' value='{$parms["currentValue"]}' name='$fieldName'><span>Integer: {$parms["description"]}</span></p>".PHP_EOL;

        } else if ($parms["type"]=="number") {
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input type='number' $FORCE_DISABLED inputmode='numeric' step='0.01' value='{$parms["currentValue"]}' name='$fieldName'><span>Decimal: {$parms["description"]}</span></p>".PHP_EOL;

        } else if ($parms["type"]=="apikey") {
            $jsid=strtr($fieldName,["@"=>"_"]);
            $checkButton="<button class='url' type='button' onclick=\"document.getElementById('$jsid').style.filter=''\">Unhide</button>";
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label>
            <input  style='filter: blur(3px);' $FORCE_DISABLED class='apikey' type='string'  id='$jsid' value='{$parms["currentValue"]}' name='$fieldName'>$checkButton<span>{$parms["description"]}</span>
            </p>".PHP_EOL;

        } else if ($parms["type"]=="file") {
            $availableFiles=getFilesByExtension($parms["path"],$parms["filter"]);
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label><select  $FORCE_DISABLED class='files' type='string' name='$fieldName'>".PHP_EOL;
            foreach ($availableFiles as $file)
                echo "<option ".(($file==$parms["currentValue"])?"selected":"")." value='$file'>$file</option>";
            echo "</select><span>{$parms["description"]}</span></p>";
            
        }  else if ($parms["type"]=="util") {
            $checkButton="<button class='' type='button' onclick=\"callHelper('{$parms["action"]}')\">{$parms["name"]}</button>";
            echo "<p class='conf-item'>$checkButton<span>{$parms["description"]}</span></p>".PHP_EOL;
            
        } else if ($parms["type"]=="ormodellist") {
            $jsid=strtr($fieldName,["@"=>"_"]);
            $checkButton="<button class='url' type='button' onclick=\"callHelperModel('choices$jsid','$jsid')\">Get Model List</button>";
            echo "<p class='conf-item'><label for='$fieldName'>$pname</label>";
            echo "<input list='choices$jsid' style='width:300px' id='$jsid' name='$fieldName' value='{$parms["currentValue"]}' />$checkButton";
            echo "<datalist id='choices$jsid'><option label=\"{$parms["currentValue"]}\" value=\"{$parms["currentValue"]}\"></datalist><span>{$parms["description"]}</span>
            </p>".PHP_EOL;

        } 
        if (!in_array($fieldName,["HERIKA_NAME","HERIKA_PERS","DBDRIVER","TTS@AZURE@voice","TTS@MIMIC3@voice",'TTS@ELEVEN_LABS@voice_id',"TTS@openai@voice","TTS@CONVAI@voiceid","TTS@XTTSFASTAPI@voiceid","TTS@MELOTTS@voiceid"]))
            if (!in_array($parms["type"],["util"]))
                if (!in_array($parms["scope"],["global","constant"]))
                    echo "<button title='Copy $fieldName to all profiles' style='color:#FFFFFF; cursor:pointer; font-size:9px; display:block; position:relative; background-color:#444444; border:1px solid #FFFFFF; padding:2px 6px; border-radius:4px; text-decoration:none;' onmouseover=\"this.style.backgroundColor='#666666'; this.style.borderColor='#FFD700';\" onmouseout=\"this.style.backgroundColor='#444444'; this.style.borderColor='#FFFFFF';\" onclick=\"copyToAllprofiles('$fieldName','$jsid')\">Copy to All Profiles</button>";
                echo "</div>";
    }

}
echo str_repeat("</fieldset>", $lvl1);
echo str_repeat("</fieldset>", $lvl2);

echo '</form>';

echo "<div style='position:fixed;top:0px;right:25px;background-color:black;font-size:1em;border:1px solid grey;margin:85px 5px;padding:5px;'>
<span><strong>Quick Access for <span style='color:green'>{$GLOBALS["CURRENT_PROFILE_CHAR"]}</span><br/><span style='font-size:7px'>".
    basename($_SESSION["PROFILE"])
."</span></strong></span><ul>";
//echo "<li><a href='#top'>Top</a></li>";


foreach ($summary as $k=>$item) {
    echo "<li>&nbsp;<a href='#$k'>{$item["main"]}</a></li>";
    
    foreach ($item["childs"] as $localhash=>$subtitle) {
        echo "<li class='subchild' id='mini_f_".md5($subtitle)."'>&nbsp;<a href='#" . md5($subtitle) . "'>$subtitle</a></li>";
    }
    
}

echo '<input
    style="margin-top:10px; font-weight:bold; border:1px solid; padding:5px;"
    class="btn btn-info"
    type="button"
    name="save"
    value="Save"
    onclick=\'formSubmitting=true;document.getElementById("top").target="checker";document.getElementById("top").action="tools/conf_writer.php?save=true&sc="+getAnchorNH();document.getElementById("top").submit();\' />';

echo ' :: ';

echo '<input
    style="margin-top:10px; font-weight:bold; border:1px solid; padding:5px; background-color:red;"
    class="btn btn-info"
    type="button"
    name="delete"
    value="Delete profile"
    onclick=\'formSubmitting=true;document.getElementById("top").target="checker";document.getElementById("top").action="tools/conf_deletion.php?save=true&sc="+getAnchorNH();document.getElementById("top").submit();\' /></p>';
echo "</ul></div>";


include("tmpl/footer.html");


$buffer = ob_get_contents();
ob_end_clean();
$title = "AI Follower Framework Server";
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;


