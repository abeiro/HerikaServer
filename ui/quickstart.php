<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CHIM</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* Dark Mode Styles */
        body {
            background-color: #121212;
            color: #e0e0e0;
            padding: 20px;
        }
        .confwizard {
            background-color: #1e1e1e;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        }
        .conf-item label {
            color: #e0e0e0;
            font-weight: 500;
        }
        .form-control, .form-control:focus, .custom-select, .custom-select:focus, textarea.form-control {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444;
        }
        .form-control::placeholder {
            color: #888;
        }
        .form-text {
            color: #bbb;
        }
        /* Common Styles for All Buttons */
        .custom-button {
            margin-top: 10px;
            font-weight: bold;
            border: 1px solid;
            padding: 10px 20px;
            cursor: pointer; /* Changes cursor to pointer on hover */
            transition: background-color 0.3s, color 0.3s; /* Smooth transition for hover effects */
            border-radius: 4px; /* Rounded corners */
            font-size: 16px; /* Increased font size for better readability */
            display: inline-block; /* Aligns buttons properly */
            text-align: center; /* Centers text within the button */
            text-decoration: none; /* Removes underline from text */
        }

        /* Save Button Styles */
        .btn-save {
            background-color: #28a745; /* Green background */
            color: white; /* White text */
        }

        .btn-save:hover {
            background-color: #218838; /* Darker green on hover */
        }

        /* Delete Button Styles */
        .btn-delete {
            background-color: #dc3545; /* Red background */
            color: white; /* White text */
        }

        .btn-delete:hover {
            background-color: #c82333; /* Darker red on hover */
        }

        /* Download Button Styles */
        .btn-download {
            background-color: #ffc107; /* Yellow background */
            color: black; /* Black text for contrast */
        }

        .btn-download:hover {
            background-color: #e0a800; /* Darker yellow/orange on hover */
        }

        /* Optional: Additional Classes for Consistent Sizing */
        .btn-lg {
            padding: 12px 24px;
            font-size: 18px;
        }
        /* Warning Text Styling */
        .warning-text {
            color: #ffcc00; /* Amber color for visibility */
            font-weight: bold;
            margin-bottom: 15px;
        }
                .warning-text2 {
            color: #28a745; /* Amber color for visibility */
            font-weight: bold;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    
<?php

error_reporting(E_ERROR);
session_start();

ob_start();

$url = 'conf_editor.php';
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

$TITLE = "QUICKSTART MENU"; // Updated title

$configFilepath = realpath($configFilepath) . DIRECTORY_SEPARATOR;

// Include necessary files
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php"); // Defaults
if (file_exists($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php")) {
    require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php"); // Current configs
}
require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . 'conf_loader.php');

// Profile selection
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf) {
    if (file_exists($mconf)) {
        $filename = basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash] = $mconf;
    }
}

// Function to compare modification dates
function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Sort the profiles by modification date descending
if (is_array($GLOBALS["PROFILES"])) {
    usort($GLOBALS["PROFILES"], 'compareFileModificationDate');
} else {
    $GLOBALS["PROFILES"] = [];
}

$GLOBALS["PROFILES"] = array_merge(["default" => "$configFilepath/conf.php"], $GLOBALS["PROFILES"]);

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"], $GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
    require_once($_SESSION["PROFILE"]);
}
// End of profile selection

include("tmpl/head.html");
$debugPaneLink = false;
include("tmpl/navbar.php");

// Load current configurations
$currentConf = conf_loader_load();
$currentConfTitles = conf_loader_load_titles();

// Filter the configurations you want to display in the Quickstart Menu
$quickstartKeys = [
    "PLAYER_NAME",
    "CONNECTOR openrouter API_KEY",
    "CONNECTOR openrouterjson API_KEY",
    "TTSFUNCTION",
    "STT WHISPER API_KEY"
];

$quickstartConf = array_filter($currentConf, function($key) use ($quickstartKeys) {
    return in_array($key, $quickstartKeys);
}, ARRAY_FILTER_USE_KEY);

// Start of Form
echo '<div class="container">
        <form action="" method="post" name="mainC" class="confwizard" id="top">
            <input type="hidden" name="profile" value="' . htmlspecialchars($_SESSION["PROFILE"]) . '" />
      ';

// Main Heading
echo '<div class="container">
      <h1 class="text-center mb-4">QUICKSTART MENU</h1>
      <h2 class="text-center mb-4">This menu is only meant to be used for the initial setup.</h2>
      <h2 class="text-center mb-4">Please use the Configuration Wizard for any further changes.</h2>
    </div>';

if ($_SESSION["PROFILE"] == "$configFilepath/conf.php") {
    $DEFAULT_PROFILE = true;
} else {
    $DEFAULT_PROFILE = false;
}

$access = ["basic" => 0, "pro" => 1, "wip" => 2];

foreach ($quickstartConf as $pname => $parms) {

    if (isset($parms["helpurl"])) {
        $parms["description"] .= " <a target='_blank' href='" . htmlspecialchars($parms["helpurl"]) . "'>[help/doc]</a>";
    }

    if (isset($parms["userlvl"]) && !($access[$parms["userlvl"]] <= $access[$_SESSION["OPTION_TO_SHOW"]])) {
        $MAKE_NO_VISIBLE_MARK = " style='display:none' ";
    } else {
        $MAKE_NO_VISIBLE_MARK = "";
    }

    $fieldName = strtr($pname, array(" " => "@"));

    if (!is_array($parms["currentValue"])) {
        $fieldValue = htmlspecialchars(stripslashes($parms["currentValue"]));
    } else {
        $fieldValue = '';
    }

    $FORCE_DISABLED = "";

    // Handle scope and constant parameters
    if ($DEFAULT_PROFILE && $fieldName == "HERIKA_NAME") {
        $fieldValue = "The Narrator";
        $FORCE_DISABLED = " readonly='true' ";
    } else {
        $FORCE_DISABLED = "";
    }

    if (!$DEFAULT_PROFILE && isset($parms["scope"]) && $parms["scope"] == "global") {
        $FORCE_DISABLED = " readonly='true' disabled='true' title='This is a global parameter. Set it on default profile' ";
    }

    if (isset($parms["scope"]) && $parms["scope"] == "constant") {
        $FORCE_DISABLED = " readonly='true' disabled='true' title='This is a readonly parameter'";
    }

    echo "<div class='form-group' $MAKE_NO_VISIBLE_MARK>";

    // Label
    echo "<label for='$fieldName'>" . htmlspecialchars($pname) . "</label>";

    // Input Types
    if ($parms["type"] == "string") {
        echo "<input type='text' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value=\"$fieldValue\" $FORCE_DISABLED>";
    } else if ($parms["type"] == "longstring") {
        echo "<textarea class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' $FORCE_DISABLED>$fieldValue</textarea>";
    } else if ($parms["type"] == "url") {
        echo "<div class='input-group'>";
        echo "<input type='url' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' $FORCE_DISABLED>";
        echo "<div class='input-group-append'>";
        echo "<button class='btn btn-outline-secondary' type='button' onclick=\"checkUrlFromServer('$fieldName')\">Check</button>";
        echo "</div></div>";
    } else if ($parms["type"] == "select") {
        if ($pname == "TTSFUNCTION") {
            $parms["values"] = ["melotts","xtts-fastapi"];
            $parms["description"] = "Select either MeloTTS or XTTS. <br>You can install MeloTTS under Tools/Components/AMD or NVIDIA GPU in the DwemerDistro folder if you have not already.<br> You can install XTTS under Tools/Components/NVIDIA GPU in the DwemerDistro folder. <br><b>We recommend MeloTTS for most users.</b>";
        }
    
        echo "<select class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' $FORCE_DISABLED>";
        foreach ($parms["values"] as $item) {
            $selected = ($item == $parms["currentValue"]) ? "selected" : "";
            echo "<option value='" . htmlspecialchars($item) . "' $selected>" . htmlspecialchars($item) . "</option>";
        }
        echo "</select>";
    
    } else if ($parms["type"] == "boolean") {
        // Add a wrapper div to ensure radio buttons are on a new line
        echo "<div class='mt-2'>";

        // True Radio Button
        $checkedTrue = ($parms["currentValue"]) ? "checked" : "";
        $idTrue = uniqid("bool_true_");
        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='radio' name='" . htmlspecialchars($fieldName) . "' id='$idTrue' value='true' $checkedTrue $FORCE_DISABLED>";
        echo "<label class='form-check-label' for='$idTrue'>True</label>";
        echo "</div>";

        // False Radio Button
        $checkedFalse = (!$parms["currentValue"]) ? "checked" : "";
        $idFalse = uniqid("bool_false_");
        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='radio' name='" . htmlspecialchars($fieldName) . "' id='$idFalse' value='false' $checkedFalse $FORCE_DISABLED>";
        echo "<label class='form-check-label' for='$idFalse'>False</label>";
        echo "</div>";

        echo "</div>"; // End of mt-2 div
    } else if ($parms["type"] == "integer") {
        echo "<input type='number' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' step='1' $FORCE_DISABLED>";
    } else if ($parms["type"] == "number") {
        echo "<input type='number' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' step='0.01' $FORCE_DISABLED>";
    } else if ($parms["type"] == "apikey") {
        $jsid = strtr($fieldName, ["@" => "_"]);

        if ($pname == "CONNECTOR openrouter API_KEY") {
            $parms["description"] = "Copy and Paste THE EXACT SAME OpenRouter API Key. <i>Yes we need to do it 2 times.</i>";
        } elseif ($pname == "CONNECTOR openrouterjson API_KEY") {
            $parms["description"] = "Copy and Paste your OpenRouter API Key. <br><a href='https://openrouter.ai/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
        } elseif ($pname == "STT WHISPER API_KEY") {
        $parms["description"] = "Copy and Paste your OpenAI API Key. If you do not plan to use your microphone you can skip this. <br><a href='https://platform.openai.com/docs/overview/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
        }
        echo "<div class='input-group'>";
        echo "<input type='text' class='form-control' id='$jsid' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' style='filter: blur(3px);' $FORCE_DISABLED>";
        echo "<div class='input-group-append'>";
        echo "<button class='btn btn-outline-secondary' type='button' onclick=\"document.getElementById('$jsid').style.filter='blur(0px)'\">Unhide</button>";
        echo "</div></div>";
    }
    // Add other input types as needed

    // Description
    if (isset($parms["description"]) && !empty($parms["description"])) {
        echo "<small class='form-text'>" . $parms["description"] . "</small>";
    }

    echo "</div>";
}

echo '<div class="btn-group-custom text-center">
        <p class="warning-text">
    Click "Download AIAgent.ini" and place it in the AIAgent Skyrim mod folder under SKSE/Plugins 
    <a href="https://www.nexusmods.com/skyrimspecialedition/mods/126330?tab=files/" target="_blank"> Download CHIM Mod</a>
</p>
<div class="btn-group-custom text-center">
        <p class="warning-text2">
    After you click <b>Save</b> we <b>HIGHLY RECOMMEND</b> to open the Troubleshooting menu and run the LLM/AI, TTS and STT tests to verify everything is setup correctly.
</p>
<div class="btn-group-custom text-center">
    <p class="warning-text3">
        Also check out the <a href="/HerikaServer/ui/index.php?notes=true" target="_blank">CHIM 101</a> guide and the <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit#heading=h.22ert9k7wlm" target="_blank">CHIM Manual</a> to learn how to make the most out of this mod!
    </p>
</div>



<button
    type="button"
    class="custom-button btn-download btn-lg"
    name="aiagentdownload"
    value="aiagentdownload"
    onclick=\'
        formSubmitting = true;
        document.getElementById("top").target = "_self"; /* Ensures submission in the same tab */
        document.getElementById("top").action = "tests/ai_agent_ini.php";
        document.getElementById("top").submit();
    \'
>
    Download AIAgent.ini
</button>

<button
    type="button"
    class="custom-button btn-save btn-lg mr-2"
    name="save"
    value="Save"
    onclick=\'
        formSubmitting = true;
        document.getElementById("top").target = "_self"; /* Ensures submission in the same tab */
        document.getElementById("top").action = "tools/conf_writer.php?save=true&incomplete=true&sc=" + getAnchorNH();
        document.getElementById("top").submit();
    \'
>
    Save
</button>
</div>';

echo '</form>
      </div>'; // End of container

include("tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = "AI Follower Framework Server";
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;

?>
