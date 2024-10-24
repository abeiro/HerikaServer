<nav class="navbar navbar-expand-lg bg-primary-subtle">
    <div class="container-fluid mx-1">
        <!-- PLEASE LEAVE THIS LINK TO index.php, as database update checks are being made there -->
        <!--<a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/conf_wizard.php" title="AI Follower Framework Server :: Go to Home Page"><img src="images/DwemerDynamics.png" alt="AI Follower Framework Server" style="vertical-align:bottom;"/> AI Follower Framework</a> -->
        <a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/index.php" title="AI Follower Framework Server :: Go to Home Page">
            <img src="images/DwemerDynamics.png" alt="AI Follower Framework Server" style="vertical-align:bottom;"/> 
        AI Follower Framework
        </a> 
        
        <a class="navbar-brand mr-2 button" href="./index.php?togglemodel=true" title="Click to change active connector" style="display:none">
        <!--[IGNORE THIS] Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel.json'), true)); ?>-->
        </a>
        

        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Events</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?table=eventlog">Events</a></li>
                    <li><a class="dropdown-item" href="index.php?table=log">AI Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=quests">Current Active Quests</a></li>
                    <li><a class="dropdown-item" href="index.php?table=eventlog&autorefresh=true">Monitor Events</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Memories</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?table=currentmission">Current AI Task/Goal</a></li>
                    <li><a class="dropdown-item" href="index.php?table=diarylog">Diary Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=books">Book Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=memory">Memories</a></li>
                    <li><a class="dropdown-item" href="index.php?table=memory_summary">Memories Summarized</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Actions</a>
                <ul class="dropdown-menu">
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
                    <!--<li>
                        <a class="dropdown-item" href="index.php?export=diary" title="Exports Diary Log to a csv file" target="_blank">
                            Export Diary
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="tests/vector-sync-chromadb.php" title="Sync VectorDB Memories. Use this if you have changed Memory Embeddings service." target="_blank">
                            Sync Memories
                        </a>
                    </li>
                    -->
                    <li>
                        <a class="dropdown-item" href="tests/vector-compact-chromadb.php" title="Compact and Sync VectorDB Memories if you have changed Memory Embeddings service" onclick="return confirm('Will cost Tokens to use if using OpenAI. MAY TAKE A FEW MINUTES TO PROCESS, DO NOT REFRESH THE WEBPAGE! Are you sure?')">
                            Compact & Sync Memories
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/pgAdmin/" target="_blank" title="pgAdmin Database Manager. User/password is 'dwemer'">
                            Database Manager (user&pass: dwemer)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="export_db.php" target="_blank" title="Exports current database into a file.">
                            Backup Current Database
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="import_db.php" target="_blank" title="Reimport an exported database file.">
                            Restore Database Backup
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Fully reinstalls the AI Follower Framework Database." 
                        onclick="return confirm('This will wipe and reinstall the entire database!!! If you want to delete configurations, delete conf.php and conf_*.php files from HerikaServer conf folder. ARE YOU SURE?')">
                            Factory Reset Server Database
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="tests/ai_agent_ini.php" title="Generate AIAgent.ini file for the mod file." target="_blank">
                            <strong>Create AIAgent.ini (PLACE IN MOD FOLDER UNDER SKSE\Plugins)</strong>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="conf_wizard.php">Configuration Wizard</a></li>
                    <li><a class="dropdown-item" href="npc_upload.php" title="Upload NPC Biographies with a csv file" target="_blank">Upload NPC Biographies</a></li>
                    <li><a class="dropdown-item" href="xtts_clone.php" title="Manually manage XTTS FastAPI voices" target="_blank">XTTS FastAPI Voice Management</a></li>
                    <li><a class="dropdown-item" href="http://localhost:59125" title="Find Mimic3 voices" target="_blank">Mimic3 Voice Menu</a></li>
                    <li><a class="dropdown-item" href='https://docs.google.com/spreadsheets/d/1cLoJRT1AsjoICg8E4PzXylsWUSYzqlKvj32F6Q5clpg/edit?gid=0#gid=0' target="_blank">AI/LLM Supported Models List</a></li>
                    <li><a class="dropdown-item" href='quickstart.php' target="_blank">Quickstart Menu</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="../soundcache/" target="_blank">Audio & Image Cache</a></li>
                    <li><a class="dropdown-item" href="updater.php">Update AI Follower Framework Server</a></li>
                    <li><a class="dropdown-item" href="tests.php" target="_blank">Current LLM/AI Connection Test</a></li>
                    <!--<li><a class="dropdown-item" href="tests/tts-test-azure.php" target="_blank">Test Azure TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-mimic3.php" target="_blank">Test MIMIC3 TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-11labs.php" target="_blank">Test ElevenLabs TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-gcp.php" target="_blank">Test Google Cloud TTS Connection</a></li>
                    -->
                    <li><a class="dropdown-item" href="tests/tts-test.php" target="_blank">Current TTS Connection Test</a></li>
                    <li><a class="dropdown-item" href="../debug/simple_stt_test.php" target="_blank">Current STT Connection Test</a></li>
                    <li><a class="dropdown-item" href="tests/itt-test.php" target="_blank">Current ITT Connection Test</a></li>
                    <li><a class="dropdown-item" href="tests/apache2err.php" target="_blank">Server Error Logs</a></li>
                    <li><a class="dropdown-item" href="cmd/action_regen_charmap.php" title="Use only if you deleted character_map.json!" target="_blank">Regenerate Character Map</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Immersion</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="addons/diary" target="_blank">AI Diary</a></li>
                    <li><a class="dropdown-item" href="addons/chatsim" target="_blank">Chat Simulation</a></li>
                    <li><a class="dropdown-item" href="addons/scriptwriter" target="_blank">Script Writer</a></li>
                    <li><a class="dropdown-item" href="addons/background" target="_blank">Background Story Generator</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Plugins</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?plugins_show=true'>Installed Plugins</a></li>
                    <span title="Touch only if you know what you are doing" style="display: inline-block;    width: 100%;    text-align: center;    font-style: italic;    border-bottom: 1px solid grey;">Development</span>
                    <li><a class="dropdown-item" href="index.php?table=responselog" title="">Responses</a></li>
                    <li><a class="dropdown-item" href="index.php?table=audit_request" title="">Requests logs</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Please Read!</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?notes=true'>AI-FF 101 Quick Guide</a></li>
                    <li><a class="dropdown-item" href='https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?usp=sharing' target="_blank">AI-FF Manual</a></li>
                </ul>
            </li>
            

        </ul>
    </div>
</nav>

<div style="display: flex;    flex-direction: row;    flex-wrap: nowrap;    align-content: center;    justify-content: flex-start;    align-items: stretch;">
    <div style="max-width: 30%; display: inline-block;border:1px solid black;height:40px;padding-right:10px">
    <form action='set_profile.php' method="POST" enctype="multipart/form-data" id="formprofile" onsubmit='document.getElementById("shorcutholder").value=getAnchor()'>
    <select name='profileSelector' ms-code-custom-select="select-with-search" style="min-width:250px" onchange='document.getElementById("shorcutholder").value=getAnchor();document.getElementById("formprofile").submit();'>

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

    $OPTIONS=[];
    foreach ($GLOBALS["PROFILES"] as $lProfkey=>$lProfile)  {
        $isSelected=($_SESSION["PROFILE"]==$lProfile)?"selected":"";
        
        $pattern = "/conf_([a-fA-F0-9]+)\.php/";
        if (preg_match($pattern, $lProfile, $matches)) {
            $hash = $matches[1];
            if (isset($characterMap["$hash"])) {
                $OPTIONS[]=["html"=>"<option value='$lProfile' $isSelected >{$characterMap["$hash"]}</option>","name"=>$characterMap["$hash"]];
                $LOCAL_CHAR_NAME=$characterMap["$hash"];
            }
        } else if ($lProfkey){
            
            $OPTIONS[]=["html"=>"<option value='$lProfile' $isSelected >* $lProfkey</option>","name"=>$lProfkey];
            $LOCAL_CHAR_NAME=$lProfkey;
        }
        if ($isSelected=="selected") {
            $GLOBALS["CURRENT_PROFILE_CHAR"]=$LOCAL_CHAR_NAME;
        }
        
    }
    
    usort($OPTIONS, function($a, $b) {
        if ($a['name'] == 'default') {
            return -1;
        }
        if ($b['name'] == 'default') {
            return 1;
        }
        return strcmp($a['name'], $b['name']);
    });
        
    foreach ($OPTIONS as $op) {
        echo $op["html"];
    }

    ?>
    </select>
    <input type='hidden' value="" name="shortcut" id="shorcutholder">
    <input type='submit' value="Change Profile">
    </form>
    </div>
        <div style="display: inline-block; font-size: 10px; border: 1px solid black; height: 40px; padding-right: 10px; vertical-align: middle;">
        <span style="margin-right: 5px; font-size: 14px; vertical-align: middle;">Configuration Depth</span>
        
        <button
            style="
                margin-top: 5px; 
                font-weight: bold; 
                border: 1px solid rgb(255, 255, 255); 
                padding: 5px 10px; 
                cursor: pointer; 
                border-radius: 4px; 
                font-size: 12px; 
                background-color: #ffc107; /* Yellow */
                color: black; 
                transition: background-color 0.3s, color 0.3s;
                <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'basic') ? 'border: 2px solid black;' : ''; ?>
            "
            onclick="location.href='set_option_conf.php?c=basic'"
            onmouseover="this.style.backgroundColor='#e0a800';" /* Darker Yellow */
            onmouseout="this.style.backgroundColor='#ffc107';">
            Basic
        </button>
        
        <button
            style="
                margin-top: 5px; 
                font-weight: bold; 
                border: 1px solid rgb(255, 255, 255); 
                padding: 5px 10px; 
                cursor: pointer; 
                border-radius: 4px; 
                font-size: 12px; 
                background-color: #fd7e14; /* Orange */
                color: black; 
                transition: background-color 0.3s, color 0.3s;
                <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'pro') ? 'border: 2px solid black;' : ''; ?>
            "
            onclick="location.href='set_option_conf.php?c=pro'"
            onmouseover="this.style.backgroundColor='#e06b0d';" /* Darker Orange */
            onmouseout="this.style.backgroundColor='#fd7e14';">
            Advanced
        </button>
        
        <button
            style="
                margin-top: 5px; 
                font-weight: bold; 
                border: 1px solid rgb(255, 255, 255); 
                padding: 5px 10px; 
                cursor: pointer; 
                border-radius: 4px; 
                font-size: 12px; 
                background-color: #dc3545; /* Red */
                color: black; 
                transition: background-color 0.3s, color 0.3s;
                <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'wip') ? 'border: 2px solid black;' : ''; ?>
            "
            onclick="location.href='set_option_conf.php?c=wip'"
            onmouseover="this.style.backgroundColor='#c82333';" /* Darker Red */
            onmouseout="this.style.backgroundColor='#dc3545';">
            Experimental
        </button>
    </div>
    <div style='display:inline-block;max-widh:350px;font-size:small;border:1px solid black;height:40px;padding-right:10px'>
    
    <?php 

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."../";
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");

    if (isset($_SESSION["PROFILE"])) {
        require_once($_SESSION["PROFILE"]);
    }

    $GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);

    $currentModel=DMgetCurrentModel();
    // Convert arrays to strings or use print_r for debugging
    echo " <strong>AI/LLM Service(s):</strong> ";
    echo is_array($CONNECTORS) ? implode(",", $CONNECTORS) . " | " : $CONNECTORS;
    echo '
    <form action="cmd/action_toogle_model.php" method="get" style="display:inline;">
        <input type="hidden" name="profile" value="' . htmlspecialchars($_SESSION["PROFILE"], ENT_QUOTES, 'UTF-8') . '">
        <button type="submit" style="
            padding: 3px 8px; /* Reduced padding for smaller size */
            font-weight: bold;
            font-size: 12px; /* Reduced font size */
            color: white;
            background-color: #0030b0; /* Darker Blue */
            border: 1px solid #0030b0; /* Darker Blue Border */
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        " onmouseover="this.style.backgroundColor=\'#0056b3\';" onmouseout="this.style.backgroundColor=\'#0030b0\';">
            Current AI Service ➡ <span style="color:yellow;">(' . htmlspecialchars($currentModel, ENT_QUOTES, 'UTF-8') . ')</span>
        </button>
    </form><br/>';
    echo " <strong>TTS Service:</strong> ";
    echo is_array($TTSFUNCTION) ?  print_r($TTSFUNCTION, true)  : '<strong style="color:#ff00c6">' . $TTSFUNCTION . '</strong>'; 
    echo " <strong>STT Service:</strong> ";
    echo is_array($STTFUNCTION) ?  print_r($STTFUNCTION, true)  : '<strong style="color:#ff00c6">' . $STTFUNCTION . '</strong>' ; 
    echo " <strong>ITT Service:</strong> ";
    echo is_array($ITTFUNCTION) ?  print_r($ITTFUNCTION, true)  : '<strong style="color:#ff00c6">'  .$ITTFUNCTION . '</strong>' ; 
    ?>
</div>
</div>

<main style="max-height:760px;overflow-y:scroll">
