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

// Ensure runtime globals are available for UI chrome even when included directly.
if (defined('BASE_PATH') && (!isset($GLOBALS["DBDRIVER"]) || !isset($GLOBALS["db"]))) {
    $enginePath = BASE_PATH . DIRECTORY_SEPARATOR;
    @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
    if (function_exists('chimRuntimeBootstrapIfNeeded')) {
        @chimRuntimeBootstrapIfNeeded($enginePath, [
            'load_general_settings' => true,
            'load_stt_connector' => false,
            'load_itt_connector' => false,
            'load_tts_connector' => false,
            'load_player_name' => false,
            'load_narrator' => false,
        ]);
    }
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

// Add link to navbar CSS
echo '<link rel="stylesheet" href="' . $webRoot . '/ui/css/navbar.css">';

// Add custom CSS for centered navbar layout
echo '<style>
.chim-navbar .container-fluid {
    display: flex !important;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

/* Fixed navbar height */
.chim-navbar {
    height: 64px;
}
.chim-navbar .container-fluid > * {
    align-items: center;
}
.chim-navbar .navbar-brand,
.chim-navbar .navbar-center button.navbar-brand {
    padding: 0;
    line-height: 1;
}

/* Hide inline nav links to keep single-line navbar */
.navbar-left,
.navbar-right,
.chim-navbar .nav-item.mx-2,
.chim-navbar .nav-item.dropdown.mx-2 {
    display: none !important;
}

.server-version-info {
    display: flex;
    align-items: center;
    color: #6c757d;
    font-size: 0.75em;
    font-family: Arial, sans-serif;
    width: 120px;
    flex-shrink: 0;
}

.navbar-content-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    flex: 1;
    max-width: 1000px;
    margin: 0 auto;
}

.social-links {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 120px;
    flex-shrink: 0;
    justify-content: flex-end;
}

.social-link img {
    width: 24px;
    height: 24px;
    transition: transform 0.3s ease;
}

.social-link:hover img {
    transform: scale(1.1);
}

.navbar-left {
    display: flex;
    flex: 0 0 auto;
    justify-content: flex-end;
    margin: 0 15px 0 0 !important;
}

.navbar-center {
    display: flex;
    justify-content: center;
    flex: 0 0 auto;
    margin: 0 20px;
}

.navbar-right {
    display: flex;
    flex: 0 0 auto;
    justify-content: flex-start;
    margin: 0 0 0 15px !important;
}

.navbar-center .navbar-brand {
    margin: 0;
    padding: 0;
}

/* Dropdown positioning */
.nav-item.dropdown .dropdown-menu {
    min-width: 280px;
}

@media (max-width: 992px) {
    .container-fluid {
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }
    
    .server-version-info,
    .social-links {
        order: 2;
        width: auto;
    }
    
    .navbar-content-wrapper {
        flex-direction: column;
        gap: 10px;
        order: 1;
    }
    
    .navbar-left,
    .navbar-right {
        justify-content: center;
        flex: none;
        margin: 0 !important;
    }
    
    .navbar-center {
        order: -1;
        margin: 0;
    }
    
    /* Center dropdowns on mobile */
    .dropdown-menu {
        left: 50%;
        transform: translateX(-50%);
    }
}
</style>';

// Determine whether to show the secondary status navbar
$currentPageName = basename($_SERVER['PHP_SELF'] ?? '');
$SHOW_STATUS_NAV = in_array($currentPageName, ['conf_wizard.php','configuration_wizard.php']);

$topNavSection = $currentPageName === 'home.php' ? 'home' : '';
$roleplayPages = [
    'events-memories.php',
    'ai-response.php',
    'adventurelog.php',
    'diarylog.php',
    'soulgaze_gallery.php',
    'mapview.php',
    'hub.php',
    'traditional_quests.php',
];
$configurationPages = [
    'config_hub.php',
    'npc_master.php',
    'player_management.php',
    'narrator_management.php',
    'core_profiles.php',
    'llm_connectors.php',
    'tts_connectors.php',
    'stt_connectors.php',
    'itt_connectors.php',
    'api_badge.php',
    'global_settings.php',
    'oghma_upload.php',
    'npc_upload.php',
    'description_upload.php',
    'function_editor.php',
    'xtts_clone.php',
    'prompts_manager.php',
    'server_plugins.php',
    'conf_wizard.php',
    'configuration_wizard.php',
    'quickstart.php',
    'customprompteditor.php',
    'import_conf.php',
];
$controlPanelPages = [
    'control_panel.php',
    'request_logs.php',
    'oghma_audit.php',
    'audit.php',
    'relationship_logs.php',
    'playthrough_manager.php',
    'import_db.php',
    'tests.php',
    'tts-test.php',
    'stt-test.php',
    'itt-test.php',
    'apache2err.php',
    'dwemer-diagnostics.php',
    'server_plugin_installer.php',
    'index.php',
];

if (in_array($currentPageName, $roleplayPages, true)) {
    $topNavSection = 'roleplay';
} elseif (in_array($currentPageName, $configurationPages, true)) {
    $topNavSection = 'configuration';
} elseif (in_array($currentPageName, $controlPanelPages, true)) {
    $topNavSection = 'control';
}

// Server version and dev-build detection
// Read version from .version_number.txt
$versionFile = dirname(__DIR__, 2) . '/.version_number.txt';
$serverVersionRaw = '3.2.6'; // fallback
if (file_exists($versionFile)) {
    $versionContent = trim(file_get_contents($versionFile));
    if ($versionContent !== '') {
        $serverVersionRaw = $versionContent;
    }
}
$isDevBuild = (stripos($serverVersionRaw, 'dev') !== false);
$serverVersionDisplay = trim(str_ireplace('dev', '', $serverVersionRaw));
$serverLogoFile = $isDevBuild ? 'serverlogodev.png' : 'serverlogo.png';

?>
<div class="chim-navbar-wrapper">
    <nav class="navbar navbar-expand-lg chim-navbar">
        <div class="container-fluid mx-1">
            
            
            <div class="navbar-content-wrapper">
                <!-- Left Navigation -->
                

                <!-- Center Logo -->
                <div class="navbar-center dropdown">
                    <button class="navbar-brand Title btn btn-link p-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" data-bs-display="static" aria-expanded="false" title="Open menu" style="text-decoration: none;">
                        <img src="<?php echo $webRoot; ?>/ui/images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
                        <img src="<?php echo $webRoot; ?>/ui/images/<?php echo htmlspecialchars($serverLogoFile, ENT_QUOTES, 'UTF-8'); ?>" alt="CHIM Server" style="vertical-align:bottom;"/> 
                    </button>
                    <ul class="dropdown-menu brand-menu">
                        <li><a class="dropdown-item<?php echo $topNavSection === 'home' ? ' active' : ''; ?>" href="<?php echo $webRoot; ?>/ui/home.php"<?php echo $topNavSection === 'home' ? ' aria-current="page"' : ''; ?>>Home</a></li>
                        <li><a class="dropdown-item<?php echo $topNavSection === 'roleplay' ? ' active' : ''; ?>" href="<?php echo $webRoot; ?>/ui/events-memories.php"<?php echo $topNavSection === 'roleplay' ? ' aria-current="page"' : ''; ?>>Roleplay</a></li>
                        <li><a class="dropdown-item<?php echo $topNavSection === 'configuration' ? ' active' : ''; ?>" href="<?php echo $webRoot; ?>/ui/core/config_hub.php"<?php echo $topNavSection === 'configuration' ? ' aria-current="page"' : ''; ?>>Configuration</a></li>
                        <li><a class="dropdown-item<?php echo $topNavSection === 'control' ? ' active' : ''; ?>" href="<?php echo $webRoot; ?>/ui/control_panel.php"<?php echo $topNavSection === 'control' ? ' aria-current="page"' : ''; ?>>Control Panel</a></li>
                        <li><a class="dropdown-item" href="/Dwemer-Dashboard/index.php">DwemerDistro Home</a></li>
                    </ul>
                </div>

                
                
                <!-- <ul class="navbar-nav navbar-right">
                <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
                <ul class="dropdown-menu">
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
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Database Controls</h6></li> <li>
                    <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/import_db.php" title="Complete database management - backup, restore, maintenance, and pgAdmin access.">
                        Database Manager
                    </a>
                    </li>
                                         <li><hr class="dropdown-divider"></li>
                     <li><h6 class="dropdown-header">Debugging</h6></li>
                     <li><a class="dropdown-item" href='<?php echo $webRoot; ?>/ui/index.php?table=responselog' title="">Response Queue</a></li>
                     <li><a class="dropdown-item" href='<?php echo $webRoot; ?>/ui/request_logs.php' title="">Request Logs</a></li>

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
                     <li>
                     <a class="dropdown-item" href="updater.php" target="_blank">Update Server</a>
                     </li>-->
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
            
            <li class="nav-item dropdown mx-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">(Old)Configuration</a>
                    <ul class="dropdown-menu">

                        
                        <li><h6 class="dropdown-header">Configuration Tools</h6></li>
                        <li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/core/config_hub.php" title="Unified configuration hub with tabs.">
                            Config Hub
                        </a>
                        </li>
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
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/function_editor.php">
                        Action Editor
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
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/xtts_clone.php" title="Manually manage XTTS/Chatterbox voices."rel="noopener noreferrer">
                            XTTS/Chatterbox Management
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
                        <!--<li>
                        <a class="dropdown-item" href="<?php echo $webRoot; ?>/ui/addons/websocket" target="_blank">Websocket Configuration (WIP)</a>
                        </li>-->

                        <li><hr class="dropdown-divider"></li>
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
                    </ul>
                </li>
                </ul>
            
            
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
    $profiles = is_array($GLOBALS["PROFILES"] ?? null) ? $GLOBALS["PROFILES"] : [];
    foreach ($profiles as $lProfkey => $lProfile) {
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
        <?php if ($SHOW_STATUS_NAV): ?>
        <button id="profileSelectorBtn" class="btn-npcprofile" onclick="event.preventDefault(); document.getElementById('overlay').style.display = 'block'; document.body.classList.add('overlay-active');">
            <?php echo isset($GLOBALS["CURRENT_PROFILE_CHAR"]) ? htmlspecialchars($GLOBALS["CURRENT_PROFILE_CHAR"], ENT_QUOTES, 'UTF-8') : 'Select Profile'; ?>
        </button>
        <?php endif; ?>
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
            try {
            document.getElementById('profileSelectorBtn').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('overlay').style.display = 'block';
                document.body.classList.add('overlay-active');
            });
            } catch (err) {
                //console.error("Error attaching event listener to profile selector button:", err);
            }

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
            <?php if ($SHOW_STATUS_NAV): ?>
            <div class="chim-status-nav">
            <div class="chim-status-container">
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
    </div>
            <?php endif; ?>

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
