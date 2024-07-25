<nav class="navbar navbar-expand-lg bg-primary-subtle">
    <div class="container-fluid mx-1">
        <a class="navbar-brand mr-2 Title" href="./index.php?notes=true" title="AI Follower Framework Server :: Go to Home Page"><img src="images/DwemerDynamics.png" alt="AI Follower Framework Server" style="vertical-align:bottom;"/> AI Follower Framework
        
        <a class="navbar-brand mr-2 button" href="./index.php?togglemodel=true" title="Click to change active connector" style="display:none">
        <!--[IGNORE THIS] Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel.json'), true)); ?>-->
        </a>
        

        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Events</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?table=eventlog">Events</a></li>
                    <li><a class="dropdown-item" href="index.php?table=responselog" title="">Responses</a></li>
                    <li><a class="dropdown-item" href="index.php?table=log">AI Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=quests">Current Active Quests</a></li>
                    <li><a class="dropdown-item" href="index.php?table=eventlog&autorefresh=true">Monitor events</a></li>
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
                            Delete Events
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
                    <li>
                        <a class="dropdown-item" href="index.php?export=diary" title="Exports Diary Log to a csv file" target="_blank">
                            Export Diary
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="tests/vector-sync-chromadb.php" title="Sync VectorDB Memories. Use this if you have changed Memory Embeddings service." target="_blank">
                            Sync Memories
                        </a>
                    </li>
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
                        <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Fully reinstalls the AI Follower Framework Database." 
                        onclick="return confirm('This will wipe and reinstall the entire database!!! If you want to delete configurations, delete conf.php and conf_*.php files from HerikaServer conf folder. ARE YOU SURE?')">
                            Factory Reset Server Database
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="tests/ai_agent_ini.php" title="Generate AIAgent.ini file for the mod file." target="_blank">
                            Create AIAgent.ini (PLACE IN MOD FOLDER UNDER SKSE\Plugins)
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="../soundcache/" target="_blank">Audio & Image Cache</a></li>
                    <li><a class="dropdown-item" href="updater.php">Update AI Follower Framework Server</a></li>
                    <li><a class="dropdown-item" href="tests.php" target="_blank">Test ChatGPT/KoboldCPP Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-azure.php" target="_blank">Test Azure TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-mimic3.php" target="_blank">Test MIMIC3 TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-11labs.php" target="_blank">Test ElevenLabs TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-gcp.php" target="_blank">Test Google Cloud TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test.php" target="_blank">Current TTS Connection Test</a></li>
                    <li><a class="dropdown-item" href="tests/apache2err.php" target="_blank">Server Error Logs</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="conf_editor.php" target="_blank">Configuration Editor</a></li>
                    <li><a class="dropdown-item" href="conf_wizard.php">Configuration Wizard</a></li>
                    <li><a class="dropdown-item" href="conf_export.php" title="Export Configuration WIP" target="_blank">Export Configuration</a></li>
                    <li><a class="dropdown-item" href="conf_import.php" title="Import Configuration WIP" target="_blank">Import Configuration</a></li>
                    <li><a class="dropdown-item" href="xtts_clone.php" title="Export Configuration WIP" target="_blank">XTTS FastAPI Voice Management</a></li>
                    <li><a class="dropdown-item" href="index.php?table=openai_token_count">OpenAI Token Pricing</a></li>
                    <li><a class="dropdown-item" href='https://docs.google.com/spreadsheets/d/1cLoJRT1AsjoICg8E4PzXylsWUSYzqlKvj32F6Q5clpg/edit?gid=0#gid=0' target="_blank">AI/LLM Supported Models List</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Immersion</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="addons/background" target="_blank">Background Story Generator</a></li>
                    <li><a class="dropdown-item" href="addons/diary" target="_blank">AI Diary</a></li>
                    <li><a class="dropdown-item" href="addons/chatsim" target="_blank">Chat Simulation</a></li>
                    <li><a class="dropdown-item" href="addons/scriptwriter" target="_blank">Script Writer</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Plugins</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?plugins_show=true'>Installed Plugins</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Please Read!</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?notes=true'>Developer's Note</a></li>
                </ul>
            </li>
            

        </ul>
    </div>
</nav>

<div style="display: flex;    flex-direction: row;    flex-wrap: nowrap;    align-content: center;    justify-content: flex-start;    align-items: stretch;">
    <div style="max-width: 30%; display: inline-block;border:1px solid black;height:40px;padding-right:10px">
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

    $OPTIONS=[];
    foreach ($GLOBALS["PROFILES"] as $lProfkey=>$lProfile)  {
        $isSelected=($_SESSION["PROFILE"]==$lProfile)?"selected":"";
        
        $pattern = "/conf_([a-fA-F0-9]+)\.php/";
        if (preg_match($pattern, $lProfile, $matches)) {
            $hash = $matches[1];
            if (isset($characterMap["$hash"])) {
                echo 
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
    <div style="display:inline-block;font-size:10px;border:1px solid black;height:40px;padding-right:10px">
        <span>Options/features to show</span>
        <select onchange="location.href='set_option_conf.php?c='+this.value">
        <option type="radio" value="basic" label="BASIC" title="Show only basic options" <?php echo ($_SESSION["OPTION_TO_SHOW"]=="basic")?'selected':''; ?> />
        <option type="radio" value="pro" label="ADVANCED" title="Show advanced options" <?php echo ($_SESSION["OPTION_TO_SHOW"]=="pro")?'selected':''; ?> />
        <option type="radio" value="wip" label="WIP" title="Show WIP options" <?php echo ($_SESSION["OPTION_TO_SHOW"]=="wip")?'selected':''; ?> />
        </select>
    </div>

    <div style='display:inline-block;max-widh:350px;font-size:small;border:1px solid black;height:40px;padding-right:10px'>
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
</div>
</div>

<main style="max-height:760px;overflow-y:scroll">
