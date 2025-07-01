<?php
// Define base paths if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}
if (!defined('UI_PATH')) {
    define('UI_PATH', dirname(__DIR__));
}

// Get the relative web path from document root to our application if not already defined
if (!isset($webRoot)) {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $webRoot = dirname(dirname(dirname($scriptPath))); // Go up three levels from the script location
    if ($webRoot == '/') $webRoot = '';
    $webRoot = rtrim($webRoot, '/');
}

// Add link to navbar CSS
echo '<link rel="stylesheet" href="' . $webRoot . '/ui/css/navbar.css">';

?>
<div class="chim-navbar-wrapper">
    <nav class="navbar navbar-expand-lg chim-navbar">
        <div class="container-fluid mx-1">
            <!-- PLEASE LEAVE THIS LINK TO index.php, as database update checks are being made there -->
            <!--<a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/conf_wizard.php" title="CHIM Server :: Go to Home Page"><img src="images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> CHIM</a> -->
            <a class="navbar-brand mr-2 Title" href="<?php echo $webRoot; ?>/ui/home.php" title="Go to Home Page" style="text-decoration: none;">
                <img src="<?php echo $webRoot; ?>/ui/images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
                <img src="<?php echo $webRoot; ?>/ui/images/serverlogo.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
            </a> 
            
            <a class="navbar-brand mr-2 button" href="<?php echo $webRoot; ?>/ui/index.php?togglemodel=true" title="Click to change active connector" style="display:none">
            <!--[IGNORE THIS] Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel_.json'), true)); ?>-->
            </a>
            

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown mx-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Events & Memories</a>
                    <ul class="dropdown-menu">

                    <!-- Events Category -->
                    <li><h6 class="dropdown-header">Events and Objectives</h6></li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=eventlog">Event Log</a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=eventlog&autorefresh=true">Monitor Events</a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=quests">Active Quests</a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=currentmission">Dynamic AI Objective</a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <!-- Logs Category -->
                    <li><h6 class="dropdown-header">Logs</h6></li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=log">Response Log</a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=books">Book Log</a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Memories Category -->
                    <li><h6 class="dropdown-header">Memories</h6></li>
                    <!--<li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=memory">Memories (WIP)</a>
                    </li>-->
                    <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/index.php?table=memory_summary">Memory Summaries</a>
                    </li>

                    </ul>
                </li>
                <li class="nav-item dropdown mx-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server  Actions</a>
                    <ul class="dropdown-menu">

                        <li><h6 class="dropdown-header">Character Profiles</h6></li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/export_conf.php" target="_blank" title="Exports all current character profiles into a ZIP file.">
                            Backup Character Profiles
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/import_conf.php" title="Imports character profiles from a ZIP file.">
                            Restore Character Profiles
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/delete_conf.php" target="_blank" title="Deletes all character profiles apart from  locked ones or the default." onclick="return confirm('This will delete ALL profiles. We recommend you backup your profiles first. Locked profiles will not be deleted. You can not reverse this operation, ARE YOU SURE?')">
                            Delete All Character Profiles
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="#" onclick="regenerateCharacterMap(); return false;" title="Regenerates character map if profiles become out of sync.">
                            Regenerate Character Map
                        </a>
                        </li>

                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Database Controls</h6></li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/import_db.php" title="Complete database management - backup, restore, maintenance, and pgAdmin access.">
                            Database Manager
                        </a>
                        </li>
                        <!--<li>
                        <button class="dropdown-item" style="letter-spacing:0.5px" 
                        title="Maintenance operations - clean and optimize the database, reclaim unused space. Skyrim game must be stopped."
                        onclick="if (confirm('Vacuum operation will compact and optimize the database. \n- Make sure Skyrim game is stopped. \n- To reclaim unused space, free temporary space is required, do not start this if your disk is almost full. \n- During this operation tables will be locked, do not access the database and do not interrupt. \nThis could take some time, please wait until you see the confirmation.')) {
                            window.open('<?php echo $webRoot; ?>/ui/vacuum_db.php', 'Database_maintenance', 
                                'resizable=yes,scrollbars=yes,titlebar=no' ); 
                                return false;
                        }">Database Maintenance - vacuum</button>
                        </li>-->
                        <!--
                        <li>
                        <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Fully reinstalls the CHIM Database." 
                        onclick="return confirm('This will wipe and reinstall the entire database!!! If you want to delete configurations, delete conf.php and conf_*.php files from HerikaServer conf folder. ARE YOU SURE?')">
                            Factory Reset Server Database
                        </a>
                        </li>
                        -->

                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Utilities</h6></li>
                        <li>
                        <div style="
                            display: flex; 
                            justify-content: center; 
                            align-items: center; 
                            margin-top: 20px;">
                            <button style="
                                font-weight: bold;
                                font-family: 'Futura CondensedLight', Arial, sans-serif;
                                border: 1px solid;
                                transition: background-color 0.3s, color 0.3s;
                                border-radius: 4px;
                                text-align: center;
                                text-decoration: none;
                                background-color: #ffc107;
                                color: black;
                                padding: 6px 12px;
                                font-size: 14px;
                                cursor: pointer;
                            " 
                            onmouseover="this.style.backgroundColor='#e6ac00';"
                            onmouseout="this.style.backgroundColor='#ffc107';"
                            onclick="window.open('<?php echo $webRoot; ?>/ui/tests/ai_agent_ini.php', '_blank')" 
                            title="Generate AIAgent.ini file for the mod file.">
                                <strong>Create Custom AIAgent.ini Mod<br>(Install with mod manager, override AIAgent mod folder)</strong>
                            </button>
                        </div>
                        </li>
                    </ul>
                </li>

                <li class="nav-item dropdown mx-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                    <ul class="dropdown-menu">

                        
                        <li><h6 class="dropdown-header">Configuration Tools</h6></li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/conf_wizard.php">Configuration Wizard</a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/npc_upload.php" title="Edit NPC biographies entries.">
                            NPC Biography Management
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/oghma_upload.php" title="Edit Oghma Infinium entries.">
                            Oghma Infinium Management
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/customprompteditor.php">
                        Custom Prompt Editor
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/quickstart.php">
                            Quickstart Menu
                        </a>
                        </li>


                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">TTS Voice Management</h6></li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/xtts_clone.php" title="Manually manage XTTS FastAPI voices."rel="noopener noreferrer">
                            CHIM XTTS Management
                        </a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="http://localhost:59125" title="Find Mimic3 voices." target="_blank">
                            Mimic3 Management
                        </a>
                        </li>
                        <!-- li>
                        <a class="dropdown-item" href="<?= htmlspecialchars($GLOBALS["TTS"]["ZONOS_GRADIO"]["endpoint"]) ?>" title="Test Zonos Settings" target="_blank">
                            Zonos Gradio Management
                        </a>
                        </li -->
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Web Extensions</h6></li>
                        <li>
                        <a class="dropdown-item" href="#" onclick="window.open('/HerikaServer/ui/addons/pmstt', 'ChromeSTT', 'width=800,height=600,resizable=yes,scrollbars=yes'); return false;">Chrome Free Speech-to-Text</a>
                        </li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/addons/websocket" target="_blank">Websocket Configuration (WIP)</a>
                        </li>
                    </ul>
                </li>


                <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
                <ul class="dropdown-menu">
                    <!-- Connection Tests -->
                    <li><h6 class="dropdown-header">Connection Tests</h6></li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/tests.php">Current LLM/AI Connection Test</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/tests/tts-test.php">Current TTS Connection Test</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/tests/stt-test.php">Current STT Connection Test</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/tests/itt-test.php">Current ITT Connection Test</a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <!-- Logs & Cache -->
                    <li><h6 class="dropdown-header">Logs & Cache</h6></li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/tests/apache2err.php">Server Logs</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/dwemer-diagnostics.php">Dwemer AI Diagnostics</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/soundcache/" target="_blank">Audio & Image Cache</a>
                    </li>
                    <!--<li>
                    <a class="dropdown-item" href="updater.php" target="_blank">Update Server</a>
                    </li>-->
                </ul>
                </li>

                <li class="nav-item dropdown mx-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Immersion</a>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">Immersion Tools</h6></li>
                        <li><a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/diarylog.php">CHIM Diaries</a></li>
                        <li><a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/adventurelog.php">Adventure Log</a></li>
                        <li><a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/chat-testing.php">Chat Testing</a></li>
                        <!--<li><a class="dropdown-item" href="addons/scriptwriter" target="_blank">Script Writer</a></li>-->
                        <!--<li><a class="dropdown-item" href="addons/background" target="_blank">Background Story Generator</a></li>-->
                    </ul>
                </li>

                <li class="nav-item dropdown mx-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Plugins</a>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">CHIM Extensions</h6></li>
                        <li><a class="dropdown-item" href='<?php echo $webRoot; ?>/ui/index.php?plugins_show=true'>Plugin Manager</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Debugging</h6></li>
                        <li><a class="dropdown-item" href='<?php echo $webRoot; ?>/ui/index.php?table=responselog' title="">Response Queue</a></li>
                        <li><a class="dropdown-item" href='<?php echo $webRoot; ?>/ui/index.php?table=audit_request' title="">Request Logs</a></li>
                        <div style="
                        display: flex; 
                        justify-content: center; 
                        align-items: center; 
                        margin-top: 20px;">
                        <button style="
                            font-weight: bold;
                            border: 1px solid;
                            font-family: 'Futura CondensedLight', Arial, sans-serif;
                            transition: background-color 0.3s, color 0.3s;
                            border-radius: 4px;
                            text-align: center;
                            text-decoration: none;
                            background-color: #dc3545; /* Red background */
                            color: black;
                            padding: 6px 12px;
                            font-size: 14px;
                            cursor: pointer;
                        " 
                        onmouseover="this.style.backgroundColor='#c82333';"
                        onmouseout="this.style.backgroundColor='#dc3545';"
                        onclick="if (confirm('This will wipe and reinstall the entire database to the default configuration. ARE YOU SURE?')) { window.location.href = '<?php echo $webRoot; ?>/ui/index.php?reinstall=true&delete=true'; }"
                        title="Fully reinstalls the CHIM Database.">
                            <strong>Factory Reset Server Database</strong>
                        </button>
                    </div>

                    </li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Guides</a>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">GUIDES</h6></li>
                    <!--<li><a class="dropdown-item" href='<?php echo $webRoot; ?>/ui/index.php?notes=true'>CHIM 101 Quick Guide</a></li>-->
                    <li><a class="dropdown-item" href='https://dwemerdynamics.hostwiki.io/' target="_blank">CHIM Wiki</a></li>
                    <!--<li><a class="dropdown-item" href="https://docs.google.com/spreadsheets/d/1cLoJRT1AsjoICg8E4PzXylsWUSYzqlKvj32F6Q5clpg/edit?gid=0#gid=0" target="_blank">AI/LLM Supported Models List</a></li>-->
                    <li><a class="dropdown-item" href="https://docs.google.com/spreadsheets/d/1UtAR_r18wskmTMMsg8IlhVvr1Fn9tHvRJT8drH6RuzY/edit?gid=1257158105#gid=1257158105" target="_blank">AI/LLM Tier List</a></li>
                </ul>
            </li>
            <?php 
            // menu extension - last list element
            $plug_file = BASE_PATH . DIRECTORY_SEPARATOR . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar_custom.php";
            if (file_exists($plug_file)) 
                include($plug_file); 
            ?>                       
        </ul>
    </div>

    <?php
    // Ensure conf.php is loaded for $GLOBALS["DBDRIVER"] and other settings
    if (defined('BASE_PATH') && !isset($GLOBALS["DBDRIVER"])) {
        @include_once(BASE_PATH . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf.php");
    }

    // Function to validate plugin version format - just check it's not too long
    function isValidPluginVersion($version) {
        // Simple validation: version should be 10 characters or less
        return strlen($version) <= 10;
    }

    $pluginVersionDisplay = 'N/A'; // Default value

    // Attempt to use a global $db object if available and valid
    if (isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
        try {
            if (method_exists($GLOBALS['db'], 'fetchOne')) {
                $pluginVersionRow = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='plugin_dll_version'");
                if ($pluginVersionRow && isset($pluginVersionRow['value']) && trim($pluginVersionRow['value']) !== '') {
                    $version = trim($pluginVersionRow['value']);
                    // Validate that the version follows the expected format
                    if (isValidPluginVersion($version)) {
                        $pluginVersionDisplay = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
                    }
                }
            } elseif (method_exists($GLOBALS['db'], 'fetchAll')) {
                // Fallback to fetchAll on global $db if fetchOne not found
                $rows = $GLOBALS['db']->fetchAll("SELECT value FROM conf_opts WHERE id='plugin_dll_version' LIMIT 1");
                if ($rows && isset($rows[0]) && isset($rows[0]['value']) && trim($rows[0]['value']) !== '') {
                    $version = trim($rows[0]['value']);
                    // Validate that the version follows the expected format
                    if (isValidPluginVersion($version)) {
                        $pluginVersionDisplay = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        } catch (Exception $e) {
            // Just keep the default value and log the error
            error_log("Error fetching plugin version using global \$db: " . $e->getMessage());
        }
    } else {
        // Only attempt to create a new DB connection if we don't already have a global one
        // and only if we have all the required components
        try {
            if (isset($GLOBALS["DBDRIVER"]) && !empty($GLOBALS["DBDRIVER"])) {
                $dbDriverFile = BASE_PATH . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php";
                
                // Only try to load the SQL class if it doesn't already exist
                if (!class_exists('sql') && file_exists($dbDriverFile)) {
                    @require_once($dbDriverFile);
                }
                
                // Only create a new connection if the class was loaded successfully
                if (class_exists('sql')) {
                    // Suppress warnings/errors in this section as it's purely for UI decoration
                    @$localDb = new sql();
                    
                    if ($localDb && is_object($localDb)) {
                        if (method_exists($localDb, 'fetchOne')) {
                            $pluginVersionRow = @$localDb->fetchOne("SELECT value FROM conf_opts WHERE id='plugin_dll_version'");
                            if ($pluginVersionRow && isset($pluginVersionRow['value']) && trim($pluginVersionRow['value']) !== '') {
                                $version = trim($pluginVersionRow['value']);
                                // Validate that the version follows the expected format
                                if (isValidPluginVersion($version)) {
                                    $pluginVersionDisplay = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
                                }
                            }
                        } elseif (method_exists($localDb, 'fetchAll')) {
                            $rows = @$localDb->fetchAll("SELECT value FROM conf_opts WHERE id='plugin_dll_version' LIMIT 1");
                            if ($rows && isset($rows[0]) && isset($rows[0]['value']) && trim($rows[0]['value']) !== '') {
                                $version = trim($rows[0]['value']);
                                // Validate that the version follows the expected format
                                if (isValidPluginVersion($version)) {
                                    $pluginVersionDisplay = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Just continue with the default value
            error_log("Error in navbar fallback DB connection: " . $e->getMessage());
        }
    }
    ?>
    <div style="display: inline-flex; align-items: center; margin-right: 10px; color: #6c757d; font-size: 0.75em; font-family: 'MagicCardsNormal'; width: 120px;">
        Server: 1.3.5
        <br>
        Plugin: <?php echo $pluginVersionDisplay; ?>
    </div>

    <div class="social-links">
        <a href="https://www.youtube.com/@DwemerDynamics" target="_blank" class="social-link" title="Checkout our Youtube Channel">
            <img src="<?php echo $webRoot; ?>/ui/images/youtube.png" alt="YouTube">
        </a>
        <a href="https://discord.gg/NDn9qud2ug" target="_blank" class="social-link" title="Join us on Discord">
            <img src="<?php echo $webRoot; ?>/ui/images/discord.png" alt="Discord">
        </a>
        <a href="https://patreon.com/DwemerDynamics" target="_blank" class="social-link" title="Join our Patreon">
            <img src="<?php echo $webRoot; ?>/ui/images/patreon.png" alt="Patreon">
        </a>
    </div>

    </nav>

<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize favorites in session if not set
if (!isset($_SESSION['FAVORITES'])) {
    $_SESSION['FAVORITES'] = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile selection
    if (isset($_POST['profileSelector'])) {
        // Update the session with the selected profile
        $_SESSION['PROFILE'] = $_POST['profileSelector'];

        // Redirect back to the current page
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Handle favorite toggling
    if (isset($_POST['favoriteToggle'])) {
        $profileToToggle = $_POST['favoriteToggle'];
        if (in_array($profileToToggle, $_SESSION['FAVORITES'])) {
            // Remove from favorites
            $_SESSION['FAVORITES'] = array_filter($_SESSION['FAVORITES'], function($fav) use ($profileToToggle) {
                return $fav !== $profileToToggle;
            });
        } else {
            // Add to favorites
            $_SESSION['FAVORITES'][] = $profileToToggle;
        }

        // Redirect to avoid form resubmission
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '#'));
        exit();
    }
}
    // Initialize session variable if not set
    if (!isset($_SESSION["OPTION_TO_SHOW"])) {
        if (!isset($_COOKIE["OPTION_TO_SHOW"])) {
            $_SESSION["OPTION_TO_SHOW"] = "basic";
        } else {
            $_SESSION["OPTION_TO_SHOW"] = $_COOKIE["OPTION_TO_SHOW"];
        }
    } else {
        if (isset($_COOKIE["OPTION_TO_SHOW"])) {
            $_SESSION["OPTION_TO_SHOW"] = $_COOKIE["OPTION_TO_SHOW"];
        }
    }

    // Character Map file
    $characterMap = [];
    if (file_exists(__DIR__ . "/../../conf/character_map.json")) {
        $characterMap = json_decode(file_get_contents(__DIR__ . "/../../conf/character_map.json"), true);
    }

    // Prepare profile options
    $OPTIONS = [];
    $i = 0;
    foreach ($GLOBALS["PROFILES"] as $lProfkey => $lProfile) {
        $pattern = "/conf_([a-fA-F0-9]+)\.php/";
        if (preg_match($pattern, $lProfile, $matches)) {
            $hash = $matches[1];
            if (isset($characterMap["$hash"])) {
                $name = $characterMap["$hash"];
                $value = $lProfile;
                $OPTIONS[] = [
                    "value" => $value, 
                    "name"  => $name, 
                    "index" => $i 
                ];
                $i++; 
                $LOCAL_CHAR_NAME = $name;
            }
        } else if ($lProfkey) {
            $name = "* $lProfkey";
            $value = $lProfile;
            $OPTIONS[] = [
                "value" => $value, 
                "name"  => $name, 
                "index" => $i 
            ];
            $i++; 
            $LOCAL_CHAR_NAME = $lProfkey;
        }
        if (isset($_SESSION["PROFILE"]) && $_SESSION["PROFILE"] == $lProfile) {
            $GLOBALS["CURRENT_PROFILE_CHAR"] = $LOCAL_CHAR_NAME;
        }
    }

    // Sort options
    usort($OPTIONS, function ($a, $b) {
        if ($a['name'] == 'default') {
            return -1;
        }
        if ($b['name'] == 'default') {
            return 1;
        }
        return strcmp($a['name'], $b['name']);
    });
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Profile Selection Overlay</title>
        <style>
            @font-face {
                font-family: 'MagicCardsNormal';
                src: url('../css/font/MagicCardsNormal.ttf') format('truetype');
            }
        </style>
    </head>
    <body>
        <!-- Trigger Link to Open Overlay -->
        <button id="profileSelectorBtn" class="btn-npcprofile" onclick="event.preventDefault(); document.getElementById('overlay').style.display = 'block'; document.body.classList.add('overlay-active');">
            <?php echo isset($GLOBALS["CURRENT_PROFILE_CHAR"]) ? htmlspecialchars($GLOBALS["CURRENT_PROFILE_CHAR"], ENT_QUOTES, 'UTF-8') : 'Select Profile'; ?>
        </button>
        <!-- The Overlay -->
        <div id="overlay" class="overlay" style="display: none;">
            <!-- Overlay Content -->
            <div class="overlay-content">
                <a href="#" class="close-btn" onclick="closeOverlay(event)">&times;</a>
                <h1>Activated Character Profiles</h1>
                <i><p>Refresh page to see new characters.</p></i>
                <!-- A-Z and Favorites Filter Buttons -->
                <div class="filter-buttons">
                    <button class="filter-button" data-filter="all">All</button>
                    <button class="filter-button" data-filter="favorites">Favorites</button>
                    <?php foreach (range('A', 'Z') as $letter): ?>
                        <button class="filter-button" data-filter="<?php echo $letter; ?>"><?php echo $letter; ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Profile Selection Form -->
                <form action="<?php 
                    // Check if current page is index.php or home.php
                    $currentPage = basename($_SERVER['PHP_SELF']);
                    echo htmlspecialchars(($currentPage === 'index.php' || $currentPage === 'home.php') ? $webRoot . '/ui/conf_wizard.php' : $_SERVER['PHP_SELF']); 
                ?>" method="POST" id="formprofile">
                    <div class="options-container">
                        <?php foreach ($OPTIONS as $op): ?>
                            <?php
                                $value = htmlspecialchars($op['value']);
                                $name = htmlspecialchars($op['name']);
                                $firstLetter = strtoupper(substr($name, 0, 1));
                                if (!ctype_alpha($firstLetter)) {
                                    $firstLetter = '#'; // Non-alphabetic characters grouped under '#'
                                }
                                // Determine if the profile is favorited
                                $isFavorited = in_array($op['value'], $_SESSION['FAVORITES']);
                            ?>
                            <div class="dropdown-option" 
                                data-filter-letter="<?php echo $isFavorited ? 'favorites' : $firstLetter; ?>" 
                                data-import-order="<?php echo $op['index']; ?>"> 
                                <!-- Profile Selection Button -->
                                <button type="submit" name="profileSelector" value="<?php echo $value; ?>" class="profile-select-btn" aria-label="Select profile <?php echo $name; ?>">
                                    <?php echo $name; ?>
                                </button>
                                <!-- Favorite Toggle Form -->
                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="favorite-form">
                                    <input type="hidden" name="favoriteToggle" value="<?php echo $value; ?>">
                                    <button type="submit" class="favorite-btn <?php echo $isFavorited ? 'favorited' : ''; ?>" title="<?php echo $isFavorited ? 'Unfavorite' : 'Favorite'; ?>" aria-label="<?php echo $isFavorited ? 'Unfavorite profile ' . $name : 'Favorite profile ' . $name; ?>">
                                        <?php echo $isFavorited ? '★' : '☆'; ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="shorcutholder" id="shorcutholder" value="">
                </form>
            </div>
        </div>

        <script>
            // Function to close the overlay
            function closeOverlay(e) {
                if (e) e.preventDefault();
                document.getElementById('overlay').style.display = 'none';
                document.body.classList.remove('overlay-active');
            }

            // Add event listener to handle overlay display
            document.getElementById('profileSelectorBtn').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('overlay').style.display = 'block';
                document.body.classList.add('overlay-active');
            });

            // Close overlay when clicking outside content
            document.getElementById('overlay').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeOverlay(e);
                }
            });

            // Handle form submission
            document.getElementById('formprofile').addEventListener('submit', function(e) {
                // Don't prevent default - let the form submit
                // Close the overlay after a brief delay to ensure form submission
                setTimeout(closeOverlay, 100);
            });

            // Add filter functionality
            document.querySelectorAll('.filter-button').forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    document.querySelectorAll('.dropdown-option').forEach(option => {
                        if (filter === 'all') {
                            option.style.display = 'block';
                        } else if (filter === 'favorites' && option.dataset.filterLetter === 'favorites') {
                            option.style.display = 'block';
                        } else if (option.dataset.filterLetter === filter) {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                });
            });
        </script>
    </body>
</html>
            <div style="display: inline-block; font-size: 10px; height: 40px; padding-right: 10px; vertical-align: top;">
            <span style="margin-right: 5px; font-size: 14px; vertical-align: middle; font-weight: bold">Configuration Depth</span>
            
            <button
                class="config-depth-btn basic <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'basic') ? 'active' : ''; ?>"
                onclick="fetch('<?php echo $webRoot; ?>/ui/set_option_conf.php?c=basic').then(() => location.href='<?php echo $webRoot; ?>/ui/conf_wizard.php')">
                Basic
            </button>
            
            <button
                class="config-depth-btn advanced <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'pro') ? 'active' : ''; ?>"
                onclick="fetch('<?php echo $webRoot; ?>/ui/set_option_conf.php?c=pro').then(() => location.href='<?php echo $webRoot; ?>/ui/conf_wizard.php')">
                Advanced
            </button>
            
            <button
                class="config-depth-btn experimental <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'wip') ? 'active' : ''; ?>"
                onclick="fetch('<?php echo $webRoot; ?>/ui/set_option_conf.php?c=wip').then(() => location.href='<?php echo $webRoot; ?>/ui/conf_wizard.php')">
                Experimental
            </button>
        </div>

        <div style="display:inline-block; max-width:900px; font-size:small; height:50px; padding-right:10px; vertical-align: top;">

        <?php 
        // Update engine path to use BASE_PATH
        require_once(BASE_PATH . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
        require_once(BASE_PATH . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");

        if (isset($_SESSION["PROFILE"])) {
            require_once($_SESSION["PROFILE"]);
        }

        $GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);

        $currentModel=DMgetCurrentModel();
        // Convert arrays to strings or use print_r for debugging
        echo " <strong>AI/LLM Connectors:</strong> ";
        echo is_array($CONNECTORS) ? '<span style="color: yellow;">' . implode(",", $CONNECTORS) . '</span> | ' : '<span style="color: yellow;">' . $CONNECTORS . '</span>';
        echo '
        <form action="cmd/action_toogle_model.php" method="get" style="display:inline;">
            <input type="hidden" name="profile" value="' . htmlspecialchars($_SESSION["PROFILE"], ENT_QUOTES, 'UTF-8') . '">
            <button type="submit" class="ai-service-toggle">
                Current AI Service ➡ <span class="model-name">(' . htmlspecialchars($currentModel, ENT_QUOTES, 'UTF-8') . ')</span>
            </button>
        </form>';
        echo '
        <form action="cmd/action_copy_connector_to_all.php" method="get" style="display:inline;">
            <input type="hidden" name="profile" value="' . htmlspecialchars($_SESSION["PROFILE"], ENT_QUOTES, 'UTF-8') . '">
            <button type="submit" class="copy-to-all-profiles-btn">Copy to All Profiles</button>
        </form><br/>';
        echo " <strong>TTS:</strong> ";
        echo is_array($TTSFUNCTION) ?  print_r($TTSFUNCTION, true)  : '<strong style="color:rgb(242, 124, 17)">' . $TTSFUNCTION . '</strong>'; 
        echo " <strong>STT:</strong> ";
        echo is_array($STTFUNCTION) ?  print_r($STTFUNCTION, true)  : '<strong style="color:rgb(242, 124, 17)">' . $STTFUNCTION . '</strong>' ; 
        echo " <strong>ITT:</strong> ";
        echo is_array($ITTFUNCTION) ?  print_r($ITTFUNCTION, true)  : '<strong style="color:rgb(242, 124, 17)">'  .$ITTFUNCTION . '</strong>' ; 
        ?>
    </div>
    </div>

    </nav>
</div>

    <!-- Toast Notification Container -->
    <div id="toast-notification" class="toast-notification">
        <span class="message"></span>
    </div>

    <script>
    // Function to show toast notification
    function showToast(message, duration = 3000) {
        const toast = document.getElementById('toast-notification');
        toast.querySelector('.message').textContent = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    // Function to regenerate character map
    function regenerateCharacterMap() {
        fetch('<?php echo $webRoot; ?>/ui/cmd/action_regen_charmap.php', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
            } else {
                showToast('Error regenerating character map');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error regenerating character map');
        });
    }
    </script>
