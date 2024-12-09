<nav class="navbar navbar-expand-lg bg-primary-subtle">
    <div class="container-fluid mx-1">
        <!-- PLEASE LEAVE THIS LINK TO index.php, as database update checks are being made there -->
        <!--<a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/conf_wizard.php" title="CHIM Server :: Go to Home Page"><img src="images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> CHIM</a> -->
        <a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/index.php" title="Go to Home Page">
            <img src="images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
            <img src="images/serverlogo.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
        </a> 
        
        <a class="navbar-brand mr-2 button" href="./index.php?togglemodel=true" title="Click to change active connector" style="display:none">
        <!--[IGNORE THIS] Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel.json'), true)); ?>-->
        </a>
        

        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Events & Memories</a>
                <ul class="dropdown-menu">

                <!-- Events Category -->
                <li><h6 class="dropdown-header">Events</h6></li>
                <li>
                    <a class="dropdown-item" href="index.php?table=eventlog">Events</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=eventlog&autorefresh=true">Monitor Events</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=quests">Current Active Quests</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=currentmission">Current AI Objective</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <!-- Logs Category -->
                <li><h6 class="dropdown-header">Logs</h6></li>
                <li>
                    <a class="dropdown-item" href="index.php?table=log">AI Log</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=diarylog">Diary Log</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=books">Book Log</a>
                </li>
                <li><hr class="dropdown-divider"></li>

                <!-- Memories Category -->
                <li><h6 class="dropdown-header">Memories</h6></li>
                <li>
                    <a class="dropdown-item" href="index.php?table=memory">Memories (WIP)</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=memory_summary">Memory Summaries</a>
                </li>

                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Actions</a>
                <ul class="dropdown-menu">

                    <!-- First Category Header -->
                    <li><h6 class="dropdown-header">Event Management</h6></li>
                    <li>
                    <a class="dropdown-item" href="index.php?clean=true&table=response" title="Delete sent events." onclick="return confirm('Sure?')">
                        Clean Sent Events
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?sendclean=true&table=response" title="Marks unsent events from queue." onclick="return confirm('Sure?')">
                        Reset Sent Events
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?reset=true&table=event" title="Delete all events." onclick="return confirm('Sure?')">
                        Delete All Events
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Second Category Header -->
                    <li><h6 class="dropdown-header">AI Log Management</h6></li>
                    <li>
                    <a class="dropdown-item" href="index.php?cleanlog=true" title="Clean AI Log table" onclick="return confirm('Sure?')">
                        Clean AI Log
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?export=log" title="Export AI Log table (debugging purposes)." target="_blank">
                        Export AI Log
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Third Category Header -->
                    <li><h6 class="dropdown-header">Database Operations</h6></li>
                    <li>
                    <a class="dropdown-item" href="/pgAdmin/" target="_blank" title="pgAdmin Database Manager. User/password is 'dwemer'">
                        <strong>Database Manager (Both User & Password = dwemer)</strong>
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="export_db.php" target="_blank" title="Exports current database into a file.">
                        Backup Current Database
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="import_db.php" target="_blank" title="Reimport an exported database file.">
                        Restore Current Database 
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Fully reinstalls the CHIM Database." 
                    onclick="return confirm('This will wipe and reinstall the entire database!!! If you want to delete configurations, delete conf.php and conf_*.php files from HerikaServer conf folder. ARE YOU SURE?')">
                        Factory Reset Server Database
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Fourth Category Header -->
                    <li><h6 class="dropdown-header">Character Profiles</h6></li>
                    <li>
                    <a class="dropdown-item" href="export_conf.php" target="_blank" title="Exports current character profiles into a ZIP file.">
                        Backup Character Profiles
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="import_conf.php" target="_blank" title="Imports character profiles from a ZIP file.">
                        Restore Character Profiles
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Fifth Category Header -->
                    <li><h6 class="dropdown-header">Utilities</h6></li>
                    <li>
                    <a class="dropdown-item" href="tests/vector-compact-chromadb.php" title="Compact and Sync Memories." onclick="return confirm('Will use up tokens from your current AI connector. May take a few minutes to process. DO NOT REFRESH THE WEBPAGE!')">
                        Compact & Sync Memories
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="tests/ai_agent_ini.php" title="Generate AIAgent.ini file for the mod file." target="_blank">
                        <strong>Create AIAgent.ini (Place in mod folder under SKSE\Plugins)</strong>
                    </a>
                    </li>

                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                <ul class="dropdown-menu">

                    
                    <li><h6 class="dropdown-header">Configuration Tools</h6></li>
                    <li>
                    <a class="dropdown-item" href="conf_wizard.php">Configuration Wizard</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="npc_upload.php" title="Upload NPC Biographies with a csv file" target="_blank">
                        Upload NPC Biographies
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="customprompteditor.php" target="_blank">
                    Custom Prompt Editor
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="quickstart.php" target="_blank">
                        Quickstart Menu
                    </a>
                    </li>


                    <li><hr class="dropdown-divider"></li>

                    
                    <li><h6 class="dropdown-header">AI Voice Management</h6></li>
                    <li>
                    <a class="dropdown-item" href="xtts_clone.php" title="Manually manage XTTS FastAPI voices" target="_blank" rel="noopener noreferrer">
                        CHIM XTTS Management
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="http://localhost:59125" title="Find Mimic3 voices" target="_blank">
                        Mimic3 Browser
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    
                    <li><h6 class="dropdown-header">Guides (PLEASE READ!)</h6></li>
                    <li><a class="dropdown-item" href='index.php?notes=true'>CHIM 101 Quick Guide</a></li>
                    <li><a class="dropdown-item" href='https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?usp=sharing' target="_blank">CHIM Manual</a></li>
                    <li>
                    <a class="dropdown-item" href="https://docs.google.com/spreadsheets/d/1cLoJRT1AsjoICg8E4PzXylsWUSYzqlKvj32F6Q5clpg/edit?gid=0#gid=0" target="_blank">
                        AI/LLM Supported Models List
                    </a>
                    </li>
                </ul>
            </li>


            <li class="nav-item dropdown mx-2">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
            <ul class="dropdown-menu">
                <!-- Connection Tests -->
                <li><h6 class="dropdown-header">Connection Tests</h6></li>
                <li>
                <a class="dropdown-item" href="tests.php" target="_blank">Current LLM/AI Connection Test</a>
                </li>
                <li>
                <a class="dropdown-item" href="tests/tts-test.php" target="_blank">Current TTS Connection Test</a>
                </li>
                <li>
                <a class="dropdown-item" href="../debug/simple_stt_test.php" target="_blank">Current STT Connection Test</a>
                </li>
                <li>
                <a class="dropdown-item" href="tests/itt-test.php" target="_blank">Current ITT Connection Test</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <!-- Logs & Cache -->
                <li><h6 class="dropdown-header">Logs & Cache</h6></li>
                <li>
                <a class="dropdown-item" href="tests/apache2err.php" target="_blank">Server Error Logs</a>
                </li>
                <li>
                <a class="dropdown-item" href="../soundcache/" target="_blank">Audio & Image Cache</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <!-- Utilities -->
                <li><h6 class="dropdown-header">Utilities</h6></li>
                <li>
                <a class="dropdown-item" href="cmd/action_regen_charmap.php" title="Use only if you deleted character_map.json!" target="_blank">
                    Regenerate Character Map
                </a>
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
                    <li><a class="dropdown-item" href="addons/diary" target="_blank">AI Diary</a></li>
                    <li><a class="dropdown-item" href="addons/chatsim" target="_blank">Chat Simulation</a></li>
                    <!--<li><a class="dropdown-item" href="addons/scriptwriter" target="_blank">Script Writer</a></li>-->
                    <!--<li><a class="dropdown-item" href="addons/background" target="_blank">Background Story Generator</a></li>-->
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Plugins</a>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">CHIM Extensions</h6></li>
                    <li><a class="dropdown-item" href='index.php?plugins_show=true'>Plugin Manager</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Debugging</h6></li>
                    <li><a class="dropdown-item" href="index.php?table=responselog" title="">Responses</a></li>
                    <li><a class="dropdown-item" href="index.php?table=audit_request" title="">Requests logs</a></li>
                </ul>
            </li>
        </ul>
    </div>
    <a href="https://www.youtube.com/@DwemerDynamics" target="_blank" style="padding-right: 5px;">
    <img src="images/youtube.png" alt="Checkout our Youtube Channel">
    </a>
    <a href="https://discord.gg/NDn9qud2ug" target="_blank" style="padding-right: 5px;">
    <img src="images/discord.png" alt="Join us on Discord">
    </a>
    <a href="https://patreon.com/DwemerDynamics" target="_blank" style="padding-right: 10px;">
    <img src="images/patreon.png" alt="Join our Patreon">
</a>

</nav>
<div style="width: 50%; display: inline-block;">
<form action='set_profile.php' method="POST" enctype="multipart/form-data" id="formprofile" onsubmit='document.getElementById("shorcutholder").value=getAnchor()'>
<select name='profileSelector' style="min-width:250px" onchange='document.getElementById("shorcutholder").value=getAnchor();document.getElementById("formprofile").submit();'>

<?php

if (!isset($_SESSION["OPTION_TO_SHOW"])) {
    if (!isset($_COOKIE["OPTION_TO_SHOW"]))
        $_SESSION["OPTION_TO_SHOW"]="basic";
    else
        if (isset($_COOKIE["OPTION_TO_SHOW"]))
            $_SESSION["OPTION_TO_SHOW"]=$_COOKIE["OPTION_TO_SHOW"];
} else {
    if (isset($_COOKIE["OPTION_TO_SHOW"]))
            $_SESSION["OPTION_TO_SHOW"]=$_COOKIE["OPTION_TO_SHOW"];
}

 // Character Map file
if (file_exists(__DIR__ . "/../../conf/character_map.json"))
    $characterMap=json_decode(file_get_contents(__DIR__ . "/../../conf/character_map.json"),true);

foreach ($GLOBALS["PROFILES"] as $lProfkey=>$lProfile)  {
    $isSelected=($_SESSION["PROFILE"]==$lProfile)?"selected":"";
    
    $pattern = "/conf_([a-fA-F0-9]+)\.php/";
    if (preg_match($pattern, $lProfile, $matches)) {
        $hash = $matches[1];
        if (isset($characterMap["$hash"])) {
            echo "<option value='$lProfile' $isSelected >* {$characterMap["$hash"]}</option>";
            $LOCAL_CHAR_NAME=$characterMap["$hash"];
        }
    } else if ($lProfkey){
        echo "<option value='$lProfile' $isSelected >$lProfkey</option>";
        $LOCAL_CHAR_NAME=$lProfkey;
    }
    if ($isSelected=="selected") {
        $GLOBALS["CURRENT_PROFILE_CHAR"]=$LOCAL_CHAR_NAME;
    }
    
}

?>
</select>
<input type='hidden' value="" name="shortcut" id="shorcutholder">
<input type='submit' value="Change Profile">
<?php 
// Convert arrays to strings or use print_r for debugging
echo "AI/LLM Service: ";
echo is_array($CONNECTORS) ? '<strong>' . print_r($CONNECTORS, true) . '</strong>' : $CONNECTORS; 
echo " |   TTS Service: ";
echo is_array($TTSFUNCTION) ?  print_r($TTSFUNCTION, true)  : '<strong>' . $TTSFUNCTION . '</strong>'; 
echo " |   STT Service: ";
echo is_array($STTFUNCTION) ?  print_r($STTFUNCTION, true) : '<strong>' . $STTFUNCTION . '</strong>' ; 
echo " |   ITT Service: ";
echo is_array($ITTFUNCTION) ?  print_r($ITTFUNCTION, true) : '<strong>' .$ITTFUNCTION . '</strong>' ; 
?>
</form>
</div>
<div style="display:inline-block;font-size:10px">
    <span>Options/features to show</span>
    <select onchange="location.href='set_option_conf.php?c='+this.value">
    <option type="radio" value="basic" label="BASIC" title="Show only basic options" <?php echo ($_SESSION["OPTION_TO_SHOW"]=="basic")?'selected':''; ?> />
    <option type="radio" value="pro" label="ADVANCED" title="Show advanced options" <?php echo ($_SESSION["OPTION_TO_SHOW"]=="pro")?'selected':''; ?> />
    <option type="radio" value="wip" label="WIP" title="Show WIP options" <?php echo ($_SESSION["OPTION_TO_SHOW"]=="wip")?'selected':''; ?> />
    </select>
</div>
<main style="max-height:800px;overflow-y:scroll">
