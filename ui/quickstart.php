<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AI Follower Framework Server</title>
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
        .btn-primary {
            background-color: #6200ea;
            border-color: #6200ea;
        }
        .btn-primary:hover {
            background-color: #7f39fb;
            border-color: #7f39fb;
        }
        .btn-danger {
            background-color: #b00020;
            border-color: #b00020;
        }
        .btn-danger:hover {
            background-color: #cf6679;
            border-color: #cf6679;
        }
        .input-group-append .btn-outline-secondary {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444;
        }
        .input-group-append .btn-outline-secondary:hover {
            background-color: #444;
            border-color: #555;
        }
        .btn-group-custom {
            margin-top: 30px;
        }
        /* Optional: Style for the Unhide button in API Key */
        .input-group-append .btn-outline-secondary {
            cursor: pointer;
        }
        /* Make Save Button Larger */
        .btn-lg {
            padding: 10px 24px;
            font-size: 1.25rem;
            line-height: 1.5;
            border-radius: 0.3rem;
        }
        /* Warning Text Styling */
        .warning-text {
            color: #ffcc00; /* Amber color for visibility */
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
            $parms["description"] = "Select either MeloTTS or XTTS if you installed it under Tools/Components/NVIDIA GPU. Recommend to use MeloTTS for most users.<br>Make sure you have installed the voice files!";
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
            $parms["description"] = "Copy and Paste your OpenRouter API Key <br><a href='https://openrouter.ai/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
        } elseif ($pname == "CONNECTOR openrouterjson API_KEY") {
            $parms["description"] = "Copy and Paste your OpenRouter API Key. <i>Yes we need to do it 2 times.</i> <br><a href='https://openrouter.ai/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
        } elseif ($pname == "STT WHISPER API_KEY") {
        $parms["description"] = "Copy and Paste your OpenAI API Key <br><a href='https://platform.openai.com/docs/overview/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
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
    <a href="https://www.nexusmods.com/skyrimspecialedition/mods/126330?tab=files/" target="_blank"> Download AI-FF Mod</a>
</p>
<div class="btn-group-custom text-center">
        <p class="warning-text">
    After you click <b>Save</b> we <b>HIGHLY RECOMMEND</b> to open the Troubleshooting menu and run the LLM/AI, TTS and STT tests to verify everything is setup correctly.
</p>
        <button type="button" class="btn btn-info btn-lg" name="aiagentdownload" value="aiagentdownload" onclick=\'formSubmitting=true;document.getElementById("top").target="_blank";document.getElementById("top").action="tests/ai_agent_ini.php";document.getElementById("top").submit();\'>Download AIAgent.ini</button>
        <button type="button" class="btn btn-primary btn-lg mr-2" name="save" value="Save" onclick=\'formSubmitting=true;document.getElementById("top").target="checker";document.getElementById("top").action="tools/conf_writer.php?save=true&sc="+getAnchorNH();document.getElementById("top").submit();\'>Save</button>
        
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
