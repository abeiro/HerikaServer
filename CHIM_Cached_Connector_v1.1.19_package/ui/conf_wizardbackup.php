<?php 

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "Config Wizard";

include("tmpl/head.html");

// Add link to main CSS if not already included in head.html
echo '<link rel="stylesheet" href="css/main.css">';

$debugPaneLink = false;
include("tmpl/navbar.php");

// Quick Access Bar
echo '<div class="quick-access-bar">
    <div class="profile-info">
        <h3 class="profile-name">' . htmlspecialchars($GLOBALS["CURRENT_PROFILE_CHAR"]) . '</h3>
        <p class="profile-path">' . basename($_SESSION["PROFILE"]) . '</p>
        <div class="action-buttons">';

// Save button
echo '<input
    type="button"
    name="save"
    value="Save"
    id="saveProfileButton"
    class="btn-save"
    onclick=\'if (validateForm()) {
        formSubmitting=true;
        document.getElementById("top").target="_self";
        document.getElementById("top").action="tools/conf_writer.php?save=true&sc=" + getAnchorNH();
        document.getElementById("top").submit();
    }\'
/>';

// Delete button
$isDefaultProfile = basename($_SESSION["PROFILE"]) === "conf.php";
$isLocked = (isset($LOCK_PROFILE) && $LOCK_PROFILE === true) || $isDefaultProfile;
$disabledStyle = $isLocked ? "disabled" : "";
$onclickEvent = $isLocked ? 
    'onclick="alert(\'' . ($isDefaultProfile ? "The default profile cannot be deleted." : "This profile is locked and cannot be deleted.") . '\');"' : 
    'onclick=\'if (confirm("Are you sure you want to delete your profile?")) {
        formSubmitting = true;
        document.getElementById("top").target = "_self";
        document.getElementById("top").action = "tools/conf_deletion.php?save=true&sc=" + getAnchorNH();
        document.getElementById("top").submit();
    }\'';

echo '<input
    type="button"
    name="delete"
    value="Delete Profile"
    id="deleteProfileButton"
    class="btn-danger ' . $disabledStyle . '"
    ' . $onclickEvent . '
/>';

echo '</div></div>';

// Section links
echo '<div class="section-links"><ul>';
foreach ($summary as $k=>$item) {
    echo "<li><a href='#$k'>{$item["main"]}</a>";
    if (!empty($item["childs"])) {
        echo "<ul>";
        foreach ($item["childs"] as $localhash=>$subtitle) {
            echo "<li class='subchild'><a href='#" . md5($subtitle) . "'>$subtitle</a></li>";
        }
        echo "</ul>";
    }
    echo "</li>";
}
echo '</ul></div></div>';

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
$primaryGroups=[];
$primarySubGroups=[];
$lvl1=0;
$lvl2=0;
$summary = [];  // Initialize summary array

function getFilesByExtension($directory, $extension) {
    // Get all files in the directory
    $files = scandir($directory);
    // Filter files by extension
    $filteredFiles = array_filter($files, function($file) use ($extension) {
        return pathinfo($file, PATHINFO_EXTENSION) == $extension;
    });
    return $filteredFiles;
}

echo "<script>
function syncInputs(rangeId, numberId, source) {
    const rangeInput = document.getElementById(rangeId);
    const numberInput = document.getElementById(numberId);
    
    if (!rangeInput || !numberInput) return;
    
    if (source === 'range') {
        numberInput.value = rangeInput.value;
    } else {
        rangeInput.value = numberInput.value;
    }
}

// Update quick access bar active states
function updateQuickAccessStates() {
    const subLinks = document.querySelectorAll('.quick-access-bar .section-links .subchild a');
    
    subLinks.forEach(link => {
        const targetId = link.getAttribute('href').substring(1);
        const targetFieldset = document.getElementById('f_' + targetId);
        const parentLi = link.closest('li.subchild');
        
        if (targetFieldset && targetFieldset.classList.contains('visible-fieldset')) {
            link.classList.add('active');
            link.style.opacity = '1';
            // Update parent section
            if (parentLi) {
                const parentSection = parentLi.parentElement.parentElement.querySelector('a');
                if (parentSection) {
                    parentSection.style.opacity = '1';
                }
            }
        } else {
            link.classList.remove('active');
            link.style.opacity = '0.6';
        }
    });
}

// Handle fieldset toggle
function toggleFieldset(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const legend = event.currentTarget;
    const fieldset = legend.nextElementSibling;
    
    if (!fieldset || !fieldset.classList) return;
    
    if (fieldset.classList.contains('unvisible-fieldset')) {
        fieldset.classList.remove('unvisible-fieldset');
        fieldset.classList.add('visible-fieldset');
        legend.classList.remove('arrow');
        legend.classList.add('unarrow');
    } else {
        fieldset.classList.remove('visible-fieldset');
        fieldset.classList.add('unvisible-fieldset');
        legend.classList.remove('unarrow');
        legend.classList.add('arrow');
    }
    
    // Force immediate update
    requestAnimationFrame(() => {
        updateQuickAccessStates();
    });
}

// Initialize legends and fieldsets
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers to legends
    document.querySelectorAll('.confwizard fieldset fieldset legend').forEach(legend => {
        legend.addEventListener('click', toggleFieldset);
    });
    
    // Initial state update
    updateQuickAccessStates();
});
</script>";

foreach ($currentConf as $pname=>$parms) {

    /* Groupping stuff */
    $pnameA=explode(" ", $pname);

    if (isset($parms["helpurl"])) {
        $parms["description"].=" <a target='_blank' href='{$parms["helpurl"]}'>[help/doc]</a>";
    }
    
    $access=["basic"=>0,"pro"=>1,"wip"=>2];
    if ( isset($parms["userlvl"]) && !($access[$parms["userlvl"]]<=$access[$_SESSION["OPTION_TO_SHOW"]]))  {
        $MAKE_NO_VISIBLE_MARK=" style='display:none' ";
    } else {
        $MAKE_NO_VISIBLE_MARK="";
    }
    
    if (!isset($pSeparator["{$pnameA[0]}"])) {
        echo str_repeat("</fieldset>", $lvl1);
        echo str_repeat("</fieldset>", $lvl2);
        
        if (isset($currentConfTitles["{$pnameA[0]}"])) {
            $legend=$currentConfTitles["{$pnameA[0]}"];
        } else {
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
            
        } else {
            $legend=$primarySubGroups[$pnameA[1]];
        }
        
        echo "<legend id='".md5($legend)."'>$legend</legend><fieldset title='$legend'  id='f_".md5($legend)."' class='unvisible-fieldset' $MAKE_NO_VISIBLE_MARK>";
        
        if (trim($legend))
            $summary[md5($lastLegend)]["childs"][]=$legend;
        
        if (!isset($pSeparator["{$pnameA[0]}"])) {
            $lvl2=1;
        }
    }

    $sSeparator["{$pnameA[0]}{$pnameA[1]}"]=true;
    $pSeparator["{$pnameA[0]}"]=true;

    // Start grid container if it doesn't exist
    if (!isset($gridStarted)) {
        echo "<div class='conf-grid'>";
        $gridStarted = true;
    }

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
    
    if ($DEFAULT_PROFILE && $fieldName === "LOCK_PROFILE") {
        $FORCE_DISABLED = " readonly='true' disabled='true' title='This option cannot be changed for the default profile' ";
        $parms["currentValue"] = false;  // Force false for default profile
    }
    
    echo "<div $MAKE_NO_VISIBLE_MARK class='softdiv' style='margin:0;padding:0;'>";
    if ($parms["type"]=="string") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input $FORCE_DISABLED type='text' value=\"".htmlspecialchars($fieldValue,ENT_QUOTES)."\" name='$fieldName'><span> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="longstring") {
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><textarea $FORCE_DISABLED name='$fieldName'>".htmlspecialchars($fieldValue,ENT_QUOTES)."</textarea><span>{$parms["description"]}</span></p> ".PHP_EOL;

    } else if ($parms["type"]=="url") {
        $checkButton="<button class='btn-primary' type='button' onclick=\"checkUrlFromServer('$fieldName')\">Check</button>";
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><input  $FORCE_DISABLED class='url' type='url' value='".htmlspecialchars($fieldValue,ENT_QUOTES)."' name='$fieldName'/>$checkButton<span> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="select") {
        $buffer="";
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
            $buffer .= "<input type='checkbox' name='{$fieldName}[]' value='$item' $checked> " .
            "<span style='"
                . "color-scheme: dark; "
                . "line-height: var(--bs-body-line-height); "
                . "text-align: var(--bs-body-text-align); "
                . "color: yellow; "
                . "-webkit-text-size-adjust: 100%; "
                . "-webkit-tap-highlight-color: transparent; "
                . "box-sizing: border-box; "
                . "margin-top: 0; "
                . "display: inline-block; "
                . "min-width: 480px; "
                . "font-size: 16px; "
                . "font-weight: bold; "
                . "font-family: 'Arial', Times, serif; "
                . "max-width: 100%;"
            . "'>"
            . "$item $addnote</span><br>";

        }
    
        echo "<p class='conf-item'><label>$pname</label>$buffer<span>{$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"]=="boolean") {
        $rtrue = $parms["currentValue"] ? "checked" : "";
        $rfalse = $parms["currentValue"] ? "" : "checked";
        
        $id=uniqid();
        $id2=uniqid();
        echo "<p class='conf-item' data-type='boolean' $FORCE_DISABLED>$pname<br/>
            <input $FORCE_DISABLED type='radio' name='$fieldName' value='true' $rtrue id='$id'/><label for='$id'>True</label>
            <input $FORCE_DISABLED type='radio' name='$fieldName' value='false' $rfalse id='$id2'/><label for='$id2'>False</label>
            <span $FORCE_DISABLED> {$parms["description"]}</span></p>".PHP_EOL;

    } else if ($parms["type"] == "integer") {

        if ($pname === "RECHAT_P") {
        // RECHAT_P: 0-100
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='0' max='100' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='0' max='100' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:60px;'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                    id='{$fieldName}_number'>
                
                <span>{$parms["description"]}</span>
                </p>" . PHP_EOL;
    
    } else if ($pname === "BORED_EVENT") {
        // BORED_EVENT: 0-100
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='0' max='100' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='0' max='100' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:60px;'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                    id='{$fieldName}_number'>
                
                <span>{$parms["description"]}</span>
                </p>" . PHP_EOL;
    
    } else if ($pname === "CONTEXT_HISTORY") {
        // CONTEXT_HISTORY: 0-100
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='0' max='200' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='0' max='200' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:60px;'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                    id='{$fieldName}_number'>
                
                <span>{$parms["description"]}</span>
                </p>" . PHP_EOL;
    
    } else if ($pname === "RECHAT_H") {
        // RECHAT_H: 1-10
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='1' max='10' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='1' max='10' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:60px;'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                    id='{$fieldName}_number'>
                
                <span>{$parms["description"]}</span>
                </p>" . PHP_EOL;
    
    } else {
        // Default integer handling
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='number' $FORCE_DISABLED inputmode='numeric' step='1' 
                        value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                        name='$fieldName'>
                <span>Integer: {$parms["description"]}</span>
                </p>" . PHP_EOL;
    }
    
    } else if ($parms["type"] == "number") {
        // Extract the final parameter name segment
        $pnameSegments = explode(" ", $pname);
        $lastParam = end($pnameSegments);
        
        // Check if this parameter matches our slider rules
        if (in_array($lastParam, ["temperature", "repetition_penalty", "rep_pen"])) {
            echo "<p class='conf-item'>
                    <label for='$fieldName'>$pname</label>
                    <input type='range' min='0' max='2' step='0.01' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            name='$fieldName'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                            id='{$fieldName}_range'
                            $FORCE_DISABLED>
                    
                    <input type='number' min='0' max='2' step='0.01' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            style='width:60px;'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                            id='{$fieldName}_number'>
                    
                    <span>{$parms["description"]}</span>
                    </p>" . PHP_EOL;
    
        } else if (in_array($lastParam, ["presence_penalty", "frequency_penalty"])) {
            echo "<p class='conf-item'>
                    <label for='$fieldName'>$pname</label>
                    <input type='range' min='-2' max='2' step='0.01' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            name='$fieldName'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                            id='{$fieldName}_range'
                            $FORCE_DISABLED>
                    
                    <input type='number' min='-2' max='2' step='0.01' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            style='width:60px;'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                            id='{$fieldName}_number'>
                    
                    <span>{$parms["description"]}</span>
                    </p>" . PHP_EOL;
        
        } else if (in_array($lastParam, ["top_p", "min_p", "top_a"])) {
            echo "<p class='conf-item'>
                    <label for='$fieldName'>$pname</label>
                    <input type='range' min='0' max='1' step='0.01' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            name='$fieldName'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                            id='{$fieldName}_range'
                            $FORCE_DISABLED>
                    
                    <input type='number' min='0' max='1' step='0.01' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            style='width:60px;'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                            id='{$fieldName}_number'>
                    
                    <span>{$parms["description"]}</span>
                    </p>" . PHP_EOL;
    
        } else if ($lastParam === "top_k") {
            echo "<p class='conf-item'>
                    <label for='$fieldName'>$pname</label>
                    <input type='range' min='0' max='100' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            name='$fieldName'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                            id='{$fieldName}_range'
                            $FORCE_DISABLED>
                    
                    <input type='number' min='0' max='100' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                            style='width:60px;'
                            oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                            id='{$fieldName}_number'>
                    
                    <span>{$parms["description"]}</span>
                    </p>" . PHP_EOL;
    
        } else {
            // Default decimal handling
            echo "<p class='conf-item'>
                    <label for='$fieldName'>$pname</label>
                    <input type='number' $FORCE_DISABLED inputmode='numeric' step='0.01'
                            value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "'
                            name='$fieldName'>
                    <span>Decimal: {$parms["description"]}</span>
                    </p>" . PHP_EOL;
        }
    } else if ($parms["type"]=="apikey") {
        $jsid=strtr($fieldName,["@"=>"_"]);
        $checkButton="<button class='btn-primary' type='button' onclick=\"document.getElementById('$jsid').style.filter=''\">Unhide</button>";
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label>
        <input  style='filter: blur(3px);' $FORCE_DISABLED class='apikey' type='string'  id='$jsid' value='".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."' name='$fieldName'>$checkButton<span>{$parms["description"]}</span>
        </p>".PHP_EOL;

    } else if ($parms["type"]=="file") {
        $availableFiles=getFilesByExtension($parms["path"],$parms["filter"]);
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label><select  $FORCE_DISABLED class='files' type='string' name='$fieldName'>".PHP_EOL;
        foreach ($availableFiles as $file)
            echo "<option ".(($file==$parms["currentValue"])?"selected":"")." value='$file'>$file</option>";
        echo "</select><span>{$parms["description"]}</span></p>";
        
    }  else if ($parms["type"]=="util") {
        $checkButton="<button class='btn-primary' type='button' onclick=\"callHelper('{$parms["action"]}')\">{$parms["name"]}</button>";
        echo "<p class='conf-item'>$checkButton<span>{$parms["description"]}</span></p>".PHP_EOL;
        
    } else if ($parms["type"]=="ormodellist") {
        $jsid=strtr($fieldName,["@"=>"_"]);
        $checkButton="<button class='btn-primary' type='button' onclick=\"callHelperModel('choices$jsid','$jsid')\">Get Model List</button>";
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label>";
        echo "<input list='choices$jsid' style='width:300px' id='$jsid' name='$fieldName' value='".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."' />$checkButton";
        echo "<datalist id='choices$jsid'><option label=\"".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."\" value=\"".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."\"></datalist><span>{$parms["description"]}</span>
        </p>".PHP_EOL;

    } 
    if (!in_array($fieldName,["HERIKA_NAME","LOCK_PROFILE","HERIKA_PERS","HERIKA_DYNAMIC","DBDRIVER","TTS@AZURE@voice","TTS@MIMIC3@voice",'TTS@ELEVEN_LABS@voice_id',"TTS@openai@voice","TTS@CONVAI@voiceid","TTS@XTTSFASTAPI@voiceid","TTS@MELOTTS@voiceid", "OGHMA_KNOWLEDGE"]))
        if (!in_array($parms["type"],["util"]))
            if (!in_array($parms["scope"],["global","constant"]))
                echo "<button class='copy-to-all-btn' title='Copy $fieldName to all profiles' onclick=\"copyToAllprofiles(event,'$fieldName','$jsid')\">Copy to All Profiles</button>";
    echo "</div>";

}
echo str_repeat("</fieldset>", $lvl1);
echo str_repeat("</fieldset>", $lvl2);

// Quick Access Bar - moved here after summary is populated
echo '<div class="quick-access-bar">
    <div class="profile-info">
        <h3 class="profile-name">' . htmlspecialchars($GLOBALS["CURRENT_PROFILE_CHAR"]) . '</h3>
        <p class="profile-path">' . basename($_SESSION["PROFILE"]) . '</p>
        <div class="action-buttons">';

// Save button
echo '<input
    type="button"
    name="save"
    value="Save"
    id="saveProfileButton"
    class="btn-save"
    onclick=\'if (validateForm()) {
        formSubmitting=true;
        document.getElementById("top").target="_self";
        document.getElementById("top").action="tools/conf_writer.php?save=true&sc=" + getAnchorNH();
        document.getElementById("top").submit();
    }\'
/>';

// Delete button
$isDefaultProfile = basename($_SESSION["PROFILE"]) === "conf.php";
$isLocked = (isset($LOCK_PROFILE) && $LOCK_PROFILE === true) || $isDefaultProfile;
$disabledStyle = $isLocked ? "disabled" : "";
$onclickEvent = $isLocked ? 
    'onclick="alert(\'' . ($isDefaultProfile ? "The default profile cannot be deleted." : "This profile is locked and cannot be deleted.") . '\');"' : 
    'onclick=\'if (confirm("Are you sure you want to delete your profile?")) {
        formSubmitting = true;
        document.getElementById("top").target = "_self";
        document.getElementById("top").action = "tools/conf_deletion.php?save=true&sc=" + getAnchorNH();
        document.getElementById("top").submit();
    }\'';

echo '<input
    type="button"
    name="delete"
    value="Delete Profile"
    id="deleteProfileButton"
    class="btn-danger ' . $disabledStyle . '"
    ' . $onclickEvent . '
/>';

echo '</div></div>';

// Section links
echo '<div class="section-links"><ul>';
if (!empty($summary)) {
    foreach ($summary as $k=>$item) {
        echo "<li><a href='#$k'>{$item["main"]}</a>";
        if (!empty($item["childs"])) {
            echo "<ul>";
            foreach ($item["childs"] as $subtitle) {
                echo "<li class='subchild'><a href='#" . md5($subtitle) . "'>$subtitle</a></li>";
            }
            echo "</ul>";
        }
        echo "</li>";
    }
}
echo '</ul></div></div>';

echo '</form>';

include("tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = "CHIM";
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;

?>

<!-- PHP VALIDATION SCRIPT -->
<script>
function validateForm() {
    var inputs = document.querySelectorAll('#top input[type=text], #top input[type=string], #top input[type=url], #top input[type=number], #top textarea');
    var invalid = [];
    for (var i = 0; i < inputs.length; i++) {
        var val = inputs[i].value;
        var trimmedVal = val.trim();

        if (trimmedVal.endsWith('\\')) {
            invalid.push(inputs[i].name + " ends with a backslash. Unable to save due to invalid configuration!");
        }

        if (val.indexOf("\\'") !== -1) {
            invalid.push(inputs[i].name + " contains a backslash followed by a single quote. Unable to save due to invalid configuration!");
        }

        if (val.indexOf("<?php") !== -1 || val.indexOf("<?") !== -1 || val.indexOf("?>") !== -1) {
            invalid.push(inputs[i].name + " contains PHP code patterns. Unable to save due to invalid configuration!");
        }
    }

    if (invalid.length > 0) {
        alert("Error: Some input fields contain invalid patterns:\n" + invalid.join("\n"));
        return false;
    }
    return true;
}
</script>
<!-- END VALIDATION SCRIPT -->

</body>
</html>
