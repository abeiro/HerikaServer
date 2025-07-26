<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies


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

$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

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

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<style>
    /* Override main container styles */
    main {
        padding-top: 160px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
        padding-left: 10px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px; /* Reduced footer height */
        background: #031633;
        z-index: 100;
    }

    /* Additional index-specific styles */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    label {
        color: rgb(242, 124, 17);
    }

    p.conf-item {
        color: rgb(242, 124, 17);
    }

    p.conf-item input[type=radio] + label {
        color: white;
    }

    span {
        color: white;
    }

    button {
        padding: 5px 10px;
        background-color: rgb(0, 48, 176);
        color: #ffffff;
        border: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.65);
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        text-decoration: none;
        display: inline-block;
        transition: background-color 0.3s, color 0.3s;
        margin: 2px;
        font-weight: bold;
    }

    button:hover {
        background-color: rgb(0, 38, 156);
    }
</style>
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

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

echo "<script>
function syncInputs(rangeId, numberId, source) {
    const rangeInput = document.getElementById(rangeId);
    const numberInput = document.getElementById(numberId);
    
    // If either input is not found, just bail out
    if (!rangeInput || !numberInput) return;
    
    if (source === 'range') {
        numberInput.value = rangeInput.value;
    } else {
        rangeInput.value = numberInput.value;
    }
}

// Toast notification system
function showToast(message, type = 'info', duration = 5000) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification toast-' + type;
    toast.innerHTML = message;
    
    // Add toast styles
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        font-size: 14px;
        z-index: 10000;
        max-width: 400px;
        word-wrap: break-word;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        animation: slideInRight 0.3s ease-out;
    `;
    
    // Set background color based on type
    switch(type) {
        case 'success':
            toast.style.backgroundColor = 'rgba(34, 197, 94, 0.9)';
            toast.style.border = '1px solid rgba(34, 197, 94, 0.5)';
            break;
        case 'error':
            toast.style.backgroundColor = 'rgba(239, 68, 68, 0.9)';
            toast.style.border = '1px solid rgba(239, 68, 68, 0.5)';
            break;
        case 'warning':
            toast.style.backgroundColor = 'rgba(245, 158, 11, 0.9)';
            toast.style.border = '1px solid rgba(245, 158, 11, 0.5)';
            break;
        default: // info
            toast.style.backgroundColor = 'rgba(59, 130, 246, 0.9)';
            toast.style.border = '1px solid rgba(59, 130, 246, 0.5)';
    }
    
    document.body.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

// Enhanced callHelper function for dynamic profile updates
function callDynamicProfileHelper(actionFile, fieldLabel) {
    showToast(fieldLabel + '...', 'info', 10000);
    
    // Collect all form data like the regular callHelper function does
    var formData = new FormData(document.getElementById('top'));
    var jsonData = {};
    
    // Convert FormData to JSON object
    for (var pair of formData.entries()) {
        if (pair[0].endsWith('[]')) {
            // Handle array fields (like selectmultiple)
            var key = pair[0].slice(0, -2);
            if (!jsonData[key]) {
                jsonData[key] = [];
            }
            jsonData[key].push(pair[1]);
        } else {
            jsonData[pair[0]] = pair[1];
        }
    }
    
    fetch('cmd/' + actionFile, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message || (fieldLabel + ' updated successfully!'), 'success');
            // Reload the page immediately to show updated field values
            setTimeout(() => {
                window.location.reload(true);
            }, 1500);
        } else {
            showToast(data.message || ('Failed to update ' + fieldLabel), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating ' + fieldLabel + ': ' + error.message, 'error');
    });
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
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
            $legend = isset($primaryGroups[$pnameA[0]]) ? $primaryGroups[$pnameA[0]] : "";
        }
        
        // Ensure $legend is a string to avoid null parameter warnings
        $legend = $legend ?? "";
        
        if (trim($legend)) {
            $summary[md5($legend)]["main"]=$legend;
            $lastLegend=$legend;
        }
        
        echo "<fieldset  $MAKE_NO_VISIBLE_MARK><legend id='".md5($legend)."'>$legend</legend>";
        $lvl1=1;
        $lvl2=0;
    }

    if ((!isset($sSeparator["{$pnameA[0]}" . (isset($pnameA[1]) ? $pnameA[1] : "")]))&&(sizeof($pnameA)>2)) {
        echo str_repeat("</fieldset>", $lvl2);
        
        if (isset($pnameA[1]) && isset($currentConfTitles["{$pnameA[0]} {$pnameA[1]}"])) {
            $legend=$currentConfTitles["{$pnameA[0]} {$pnameA[1]}"];
            
        } else {
            $legend = isset($pnameA[1]) && isset($primarySubGroups[$pnameA[1]]) ? $primarySubGroups[$pnameA[1]] : "";
        }
        
        // Ensure $legend is a string to avoid null parameter warnings
        $legend = $legend ?? "";
        
        echo "<legend id='".md5($legend)."'>$legend</legend><fieldset title='$legend'  id='f_".md5($legend)."' class='unvisible-fieldset' $MAKE_NO_VISIBLE_MARK>";
        
        if (trim($legend))
            $summary[md5($lastLegend ?? "")]["childs"][]=$legend;
        
        if (!isset($pSeparator["{$pnameA[0]}"])) {
            $lvl2=1;
        }
    }

    $sSeparator["{$pnameA[0]}" . (isset($pnameA[1]) ? $pnameA[1] : "")] = true;
    $pSeparator["{$pnameA[0]}"] = true;

    $fieldName=strtr($pname,array(" "=>"@"));

    if (!is_array($parms["currentValue"] ?? null))
        $fieldValue=stripslashes($parms["currentValue"] ?? "");
    else 
        $fieldValue = "";
    
    
    if ($DEFAULT_PROFILE && $fieldName=="HERIKA_NAME") {
        $fieldValue="The Narrator";
        $FORCE_DISABLED=" readonly='true' ";
    } else {
        $FORCE_DISABLED="";
    }
    
    if (!$DEFAULT_PROFILE && isset($parms["scope"]) && $parms["scope"]=="global") {
        $FORCE_DISABLED=" readonly='true' disabled='true' title='This is a global parameter. Set it on default profile' ";
    }
    
    if (isset($parms["scope"]) && $parms["scope"]=="constant") {
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
        
        // Add individual dynamic profile update buttons for relevant bio fields
        $dynamicFields = [
            "HERIKA_PERSONALITY" => ["field" => "personality", "label" => "Update Personality"],
            "HERIKA_RELATIONSHIPS" => ["field" => "relationships", "label" => "Update Relationships"], 
            "HERIKA_OCCUPATION" => ["field" => "occupation", "label" => "Update Occupation"],
            "HERIKA_SKILLS" => ["field" => "skills", "label" => "Update Skills"],
            "HERIKA_SPEECHSTYLE" => ["field" => "speechstyle", "label" => "Update Speech Style"],
            "HERIKA_GOALS" => ["field" => "goals", "label" => "Update Goals"]
        ];
        
        if (isset($dynamicFields[$fieldName]) && !$DEFAULT_PROFILE) {
            $fieldInfo = $dynamicFields[$fieldName];
            $actionFile = "action_dynamic_profile_{$fieldInfo['field']}.php";
            
            echo "<button class='dynamic-profile-btn' type='button' style='
                padding: 6px 12px;
                background-color: rgba(139, 69, 19, 0.8);
                color: #ffffff;
                border: 1px solid rgba(160, 82, 45, 0.5);
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                text-decoration: none;
                display: inline-block;
                transition: all 0.2s ease-in-out;
                font-weight: 500;
                letter-spacing: 0.3px;
                backdrop-filter: blur(5px);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2),
                            inset 0 1px rgba(255, 255, 255, 0.1);
                margin: 5px 0;
                ' 
                onclick=\"callDynamicProfileHelper('$actionFile', '{$fieldInfo['label']}')\"
                onmouseover=\"this.style.backgroundColor='rgba(160, 82, 45, 0.9)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)';\"
                onmouseout=\"this.style.backgroundColor='rgba(139, 69, 19, 0.8)'; this.style.transform='none'; this.style.boxShadow='0 2px 4px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)';\">
                {$fieldInfo['label']}</button>";
        }

    } else if ($parms["type"]=="url") {
        $checkButton="<button class='url' type='button' style='
            padding: 6px 12px;
            background-color: rgba(37, 99, 235, 0.8);
            color: #ffffff;
            border: 1px solid rgba(138, 155, 182, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease-in-out;
            font-weight: 500;
            letter-spacing: 0.3px;
            backdrop-filter: blur(5px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                        inset 0 1px rgba(255, 255, 255, 0.1);
            margin-left: 5px;'
            onclick=\"checkUrlFromServer('$fieldName')\"
            onmouseover=\"this.style.backgroundColor='rgba(47, 109, 245, 0.9)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)';\"
            onmouseout=\"this.style.backgroundColor='rgba(37, 99, 235, 0.8)'; this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)';\">
            Check</button>";
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
        echo "<p class='conf-item' $FORCE_DISABLED>$pname<br/>
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
    
    } else if ($pname === "DIARY_COOLDOWN") {
        // DIARY_COOLDOWN: 10-1200
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='10' max='1200' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='10' max='1200' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:80px;'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                    id='{$fieldName}_number'>
                
                <span>{$parms["description"]}</span>
                </p>" . PHP_EOL;
    
    } else if ($pname === "CONTEXT_HISTORY_DIARY") {
        // CONTEXT_HISTORY_DIARY: 0-400
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='0' max='400' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='0' max='400' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:70px;'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"number\")'
                    id='{$fieldName}_number'>
                
                <span>{$parms["description"]}</span>
                </p>" . PHP_EOL;
    
    } else if ($pname === "CONTEXT_HISTORY_DYNAMIC_PROFILE") {
        // CONTEXT_HISTORY_DYNAMIC_PROFILE: 0-400
        echo "<p class='conf-item'>
                <label for='$fieldName'>$pname</label>
                <input type='range' min='0' max='400' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    name='$fieldName'
                    oninput='syncInputs(\"{$fieldName}_range\", \"{$fieldName}_number\", \"range\")'
                    id='{$fieldName}_range'
                    $FORCE_DISABLED>
                
                <input type='number' min='0' max='400' step='1' value='" . htmlspecialchars($parms["currentValue"], ENT_QUOTES) . "' 
                    style='width:70px;'
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
        $checkButton="<button class='url' type='button' onclick=\"document.getElementById('$jsid').style.filter=''\">Unhide</button>";
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
        // Skip rendering the autofill parameter buttons
        if (strpos($fieldName, "get_parms") === false) {
            $checkButton="<button class='' type='button' onclick=\"callHelper('{$parms["action"]}')\">{$parms["name"]}</button>";
            echo "<p class='conf-item'>$checkButton<span>{$parms["description"]}</span></p>".PHP_EOL;
        }
    } else if ($parms["type"]=="ormodellist") {
        $jsid=strtr($fieldName,["@"=>"_"]);
        $checkButton="<button class='url' type='button' onclick=\"callHelperModel('choices$jsid','$jsid')\">Get Model List</button>";
        echo "<p class='conf-item'><label for='$fieldName'>$pname</label>";
        echo "<input list='choices$jsid' style='width:300px' id='$jsid' name='$fieldName' value='".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."' />$checkButton";
        echo "<datalist id='choices$jsid'><option label=\"".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."\" value=\"".htmlspecialchars($parms["currentValue"],ENT_QUOTES)."\"></datalist><span>{$parms["description"]}</span>
        </p>".PHP_EOL;

    } 
    
    // Initialize jsid if not set for the copy to profiles button
    if (!isset($jsid)) {
        $jsid = strtr($fieldName,["@"=>"_"]);
    }
    
    if (!in_array($fieldName,["HERIKA_NAME","LOCK_PROFILE","HERIKA_PERS","HERIKA_DYNAMIC","HERIKA_BACKGROUND","HERIKA_PERSONALITY","HERIKA_APPEARANCE","HERIKA_RELATIONSHIPS","HERIKA_OCCUPATION","HERIKA_SKILLS","HERIKA_SPEECHSTYLE","HERIKA_GOALS","DBDRIVER","TTS@AZURE@voice","TTS@MIMIC3@voice",'TTS@ELEVEN_LABS@voice_id',"TTS@openai@voice","TTS@CONVAI@voiceid","TTS@XTTSFASTAPI@voiceid","TTS@MELOTTS@voiceid","TTS@PIPERTTS@voiceid", "OGHMA_KNOWLEDGE"]))
        if (!in_array($parms["type"],["util"]))
            if (!isset($parms["scope"]) || !in_array($parms["scope"],["global","constant"]))
                echo "<button class='ctapb' title='Copy $fieldName to all profiles' style='
                    color: #FFFFFF; 
                    cursor: pointer; 
                    font-size: 13px; 
                    display: block; 
                    position: relative; 
                    background-color: rgba(75, 85, 99, 0.8);
                    border: 1px solid rgba(138, 155, 182, 0.3);
                    padding: 6px 12px; 
                    border-radius: 8px;
                    text-decoration: none;
                    letter-spacing: 0.3px;
                    backdrop-filter: blur(5px);
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                                inset 0 1px rgba(255, 255, 255, 0.1);
                    transition: all 0.2s ease-in-out;
                    margin: 5px 0;
                    ' 
                    onclick=\"copyToAllprofiles(event,'$fieldName','$jsid')\"
                    onmouseover=\"this.style.backgroundColor='rgba(85, 95, 109, 0.9)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)';\"
                    onmouseout=\"this.style.backgroundColor='rgba(75, 85, 99, 0.8)'; this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)';\">
                    Copy to All Profiles</button>";
    echo "</div>";

}
echo str_repeat("</fieldset>", $lvl1);
echo str_repeat("</fieldset>", $lvl2);

echo '</form>';

echo "<div style='position:fixed;top:70px;right:25px;background-color:black;font-size:1em;border:1px solid grey;margin:85px 5px;padding:5px; z-index: 100000; max-width:300px; max-height:80vh; overflow-y:auto;'>
<span><strong>Quick Access for <span style='color:yellow'>{$GLOBALS["CURRENT_PROFILE_CHAR"]}</span><br><span style='font-size:11px'>You must click save before using 'Copy to All Profiles'</span><br/><span style='font-size:7px'>".
    basename($_SESSION["PROFILE"])
."</span></strong></span><ul>";

// Save and delete buttons
echo '<input
    type="button"
    name="save"
    value="Save"
    id="saveProfileButton"
    style="
        margin-top: 10px;
        font-weight: 500;
        border: 1px solid rgba(138, 155, 182, 0.3);
        padding: 10px 20px;
        cursor: pointer;
        border-radius: 8px;
        font-size: 15px;
        background-color: rgba(32, 122, 74, 0.8);
        color: white;
        transition: all 0.2s ease-in-out;
        letter-spacing: 0.3px;
        backdrop-filter: blur(5px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255, 255, 255, 0.1);
        background-image: linear-gradient(180deg, 
                          rgba(255, 255, 255, 0.08) 0%,
                          rgba(255, 255, 255, 0) 100%);
    "
    onclick=\'if (validateForm()) {
        formSubmitting=true;
        document.getElementById("top").target="_self";
        document.getElementById("top").action="tools/conf_writer.php?save=true&sc=" + getAnchorNH();
        document.getElementById("top").submit();
    }\'
    onmouseover=\'this.style.backgroundColor="rgba(42, 142, 94, 0.9)"; this.style.transform="translateY(-1px)"; this.style.boxShadow="0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)";\'
    onmouseout=\'this.style.backgroundColor="rgba(32, 122, 74, 0.8)"; this.style.transform="none"; this.style.boxShadow="0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)";\'
/>';

echo ' :: ';

// Check if profile is locked or is default profile
$isDefaultProfile = basename($_SESSION["PROFILE"]) === "conf.php";
$isLocked = (isset($LOCK_PROFILE) && $LOCK_PROFILE === true) || $isDefaultProfile;
$disabledStyle = $isLocked ? 'opacity: 0.5; cursor: not-allowed;' : '';
$baseStyle = '
    margin-top: 10px;
    font-weight: 500;
    border: 1px solid rgba(138, 155, 182, 0.3);
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 8px;
    font-size: 15px;
    background-color: rgba(146, 43, 53, 0.8);
    color: white;
    transition: all 0.2s ease-in-out;
    letter-spacing: 0.3px;
    backdrop-filter: blur(5px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                inset 0 1px rgba(255, 255, 255, 0.1);
    background-image: linear-gradient(180deg, 
                      rgba(255, 255, 255, 0.08) 0%,
                      rgba(255, 255, 255, 0) 100%);
    ' . $disabledStyle;

$onclickEvent = $isLocked ? 
    'onclick="alert(\'' . ($isDefaultProfile ? 'The default profile cannot be deleted.' : 'This profile is locked and cannot be deleted.') . '\');"' : 
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
    aria-label="Delete your profile"
    style="' . $baseStyle . '"
    ' . $onclickEvent . ' 
    onmouseover=\'if (!this.style.opacity || this.style.opacity === "1") { 
        this.style.backgroundColor="rgba(166, 53, 63, 0.9)"; 
        this.style.transform="translateY(-1px)"; 
        this.style.boxShadow="0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)";
    }\'
    onmouseout=\'if (!this.style.opacity || this.style.opacity === "1") { 
        this.style.backgroundColor="rgba(146, 43, 53, 0.8)"; 
        this.style.transform="none"; 
        this.style.boxShadow="0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)";
    }\'
/>';

foreach ($summary as $k=>$item) {
    echo "<li>&nbsp;<a href='#$k'>{$item["main"]}</a></li>";
    
    if (isset($item["childs"]) && is_array($item["childs"])) {
        foreach ($item["childs"] as $localhash=>$subtitle) {
            echo "<li class='subchild' id='mini_f_".md5($subtitle)."'>&nbsp;<a href='#" . md5($subtitle) . "'>$subtitle</a></li>";
        }
    }
}



echo "</ul></div>";


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

        if (val.indexOf('<?') !== -1 || val.indexOf('?>') !== -1) {
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
