<?php 

$GLOBALS["TASKS"]["rolemaster"]=[];
$GLOBALS["TASKS"]["rolemaster"]["fn"]=function() {

    $enginePath = $GLOBALS["ENGINE_ROOT"];

    /* Connector to use */
    $file = $GLOBALS["ENGINE_ROOT"].'/data/CurrentModel.json';
    $modelContents = file_get_contents($file);
    logMsg("Current AI Model is set to $modelContents.");

    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
    require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");


    $FUNCTIONS_ARE_ENABLED=false;


    $profile=md5("default");

    if (file_exists($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php")) {
        logMsg("PROFILE: {$profile}");
        $GLOBALS["active_profile"]=$profile;
        require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php");

    } else 
        logMsg("Profile does not exists:  $enginePath" . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php",S_LOG_ERROR);

    $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];

    $GLOBALS["db"]=new sql();

    // Some functions need this setted */
    $res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog order by gamets desc limit 1 offset 0");
    $GLOBALS["gameRequest"]=["inputtext"];
    $GLOBALS["gameRequest"][2]=$res[0]["gamets"]+1;

    if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
        logMsg("Choose a LLM model and connector. Used '{$GLOBALS["CURRENT_CONNECTOR"]}'",S_LOG_CRITICAL);

    } else {
        logMsg("Using {$GLOBALS["CURRENT_CONNECTOR"]}");
        require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

        $contextDataHistoric = DataLastDataExpandedFor("", -50);    // Full context
        
        $contextDataHistoric =array_merge([["role"=>"user","content"=>"# HISTORIC DIALOGUE AND EVENTS IN CHRONOLOGICAL ORDER"]], $contextDataHistoric);

        $contextDataWorld = DataLastInfoFor("", -2,$addNPCDescriptions=true,$excludeBusy=true);
        $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
        $historyData="";

        foreach ($contextDataFull as $element) {
        
            $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
            
        }

        
        $GLOBALS["HERIKA_NAME"]="random present actor";
        $GLOBALS["HERIKA_PERS"]="";

        
        
        $prompt[] = array('role' => 'system', 'content' => "I want you to read this gameplay transcription in Skyrim universe.");
        $prompt[] = array('role' => 'user', 'content' => $historyData);
        $prompt[] = array('role' => 'user', 'content' =>"Now act as a movie director, give an instruction to a random actor to generate a dialogue. 
        {$GLOBALS["argv"][2]}
        ({$GLOBALS["PLAYER_NAME"]},busy actors and far away actors are EXCLUDED!)
        This dialogue can introduce a new topic, keep talking about same topics, say someting new, or point to a enviromental action that has happened...be creative but logical. 
        Just give the instruction! (example:'Instruction for X: X should talk to Y about ...')");
        
        $connectionHandler = new $GLOBALS["CURRENT_CONNECTOR"];
        
        $connectionHandler->open($prompt,["MAX_TOKENS"=>256]);

        $buffer="";
        $totalBuffer="";
        $breakFlag=false;
        
        while (true) {

            if ($breakFlag) {
                break;
            }

            $buffer=$connectionHandler->process();
            $totalBuffer.=$buffer;

            if ($connectionHandler->isDone()) {
                $breakFlag=true;
            }
            
        }
        
        $rawbuffer=$connectionHandler->close();
        
        function parseInstruction($instruction) {
            // Extract character name from the instruction
            preg_match('/Instruction for (.*?):/', $instruction, $matches);
            $characterName = $matches[1] ?? 'Unknown';
        
            // Generate task ID
            $taskId = uniqid();
            
            // Format action string with the actual instruction text
            !$action = make_replacements("rolecommand|Instruction@{$characterName}@{$instruction}@$taskId");
            //$action = make_replacements("rolecommand|Suggestion@{$characterName}@{$instruction}@$taskId");
        
            // Insert into the database
            $GLOBALS["db"]->insert(
                'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => '',
                    'action' => $action,
                    'tag' => ""
                )
            );
        }

        

        logMsg($totalBuffer);
        parseInstruction($totalBuffer);
    }

}
?>