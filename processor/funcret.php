<?php

	$lastCallId=file_get_contents(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.".last_tool_call_openai.id.txt");

	$returnFunction = explode("@", $gameRequest[3]); // Function returns here

	$functionLocaleName=getFunctionTrlName($functionCodeName);

	$useFunctionsAgain = false;
	
	$forceAttackingText = false;
	
	if (isset($returnFunction[2])) {
		if ($functionCodeName == "GetTopicInfo") {
			$argName = "topic";
			// Lets overwrite this
			// Get info about $returnFunction[2]}
			$returnFunction[3] = "";

			//
		} else if ($functionCodeName == "LeadTheWayTo") {
			$argName = "location";
			$GLOBALS["OPENAI_MAX_TOKENS"]="64";	// Force a short response, as IA here tends to simulate the whole travel
			
			$db->insert(
			'currentmission',
				array(
					'ts' => $gameRequest[1],
					'gamets' => $gameRequest[2],
					'description' => SQLite3::escapeString("Travel to {$returnFunction[2]}"),
					'sess' => 'pending',
					'localts' => time()
				)
			);
			

		} else if ($functionCodeName== "MoveTo") {
			if (strpos($gameRequest[3], "LeadTheWayTo") !== false) {// PatchHack. If Moving returning Shoud use TravelTo, enable functions again
				$useFunctionsAgain = true;
				$request="(use function call '".getFunctionTrlName("LeadTheWayTo")."' to travel) $request";
			}
			$argName = "target";


		} else if ($functionCodeName == "Attack") {
			//$useFunctionsAgain=true;
			$forceAttackingText = true;
			$argName = "target";
			$useFunctionsAgain=false;


		} else if ($functionCodeName == "ReadQuestJournal") {
			//$useFunctionsAgain=true;
			$request="(use action '".getFunctionTrlName("SetCurrentTask")."' to update current task) $request";
			$argName = "id_quest";
			$useFunctionsAgain=true;

		} else if ($functionCodeName == "SearchMemory") {
			//$useFunctionsAgain=true;

			$argName = "target";
			$useFunctionsAgain=false;

		} else if ($functionCodeName == "ReadDiaryPage") {
			//$useFunctionsAgain=true;
			$argName = "page";


		} else if ($functionCodeName== "SearchDiary") {
			//$useFunctionsAgain=true;
			$request="(use function ".getFunctionTrlName("ReadDiaryPage")." to access the specific page provided by SearchDiary) $request";
			$argName = "keyword";
			$useFunctionsAgain=true;
			$GLOBALS["FUNCTIONS"][]=$GLOBALS["FUNCTIONS_GHOSTED"];// We provide here the ReadDiaryPage function
			

		} else if ($functionCodeName == "GetTime") {
			//$useFunctionsAgain=true;
			$argName = "datestring";
			//$useFunctionsAgain=true;


		} else if ($functionCodeName == "get_current_mission") {		// Disabled, current task is always provided.
			//$useFunctionsAgain=true;
			$argName = "description";
			//$useFunctionsAgain=true;


		} else if ($functionCodeName == "SetCurrentTask") {
			//$useFunctionsAgain=true;
			$argName = "description";
			//$useFunctionsAgain=true;


		} else if ($functionCodeName == "CheckInventory") {
			//$useFunctionsAgain=true;
			$argName = "target";
			//$useFunctionsAgain=true;


		} else {
			
			if (isset($GLOBALS["FUNCRET"][$functionCodeName])) {
				
					$frResponse=call_user_func_array($GLOBALS["FUNCRET"][$functionCodeName],["gameRequest"=>$gameRequest]);
					
					if (isset($frResponse["argName"]))
						$argName = $frResponse["argName"];
					if (isset($frResponse["request"]))
						$request = $frResponse["request"];
					if (isset($frResponse["useFunctionsAgain"]))
						$useFunctionsAgain = $frResponse["useFunctionsAgain"];
				
				
			} else
				$argName = "target";

		}
		$functionCalled[] = array('role' => 'assistant', 'content' => null, 'tool_calls' => [array("id" => $lastCallId, "type"=>"function",
																								  "function"=>["name"=>$functionLocaleName,"arguments" => "{\"$argName\":\"{$returnFunction[2]}\"}"])]);

	} else
		$functionCalled[] = array('role' => 'assistant', 'content' => null, 'tool_calls' => [array("id" => $lastCallId, "function"=>["name"=>$functionLocaleName,"arguments" => "{\"$argName\":\"{$returnFunction[2]}\"}"])]);

	$returnFunctionArray[] = array('role' => 'tool', 'content' => "{$returnFunction[3]}",'tool_call_id'=>"$lastCallId");

	if ($forceAttackingText)
		$returnFunctionArray[] = array('role' => $LAST_ROLE, 'content' => selectRandomInArray($GLOBALS["PROMPTS"]["afterattack"]["cue"])." {$GLOBALS["HERIKA_NAME"]}: ");
	else
		$returnFunctionArray[] = array('role' => $LAST_ROLE, 'content' => $request);


	$contextData = array_merge($head, ($contextDataFull), $functionCalled, $returnFunctionArray);
	
	if ($useFunctionsAgain) {
		$GLOBALS["FUNCTIONS_ARE_ENABLED"]=true;
		$GLOBALS["FUNCTIONS"];
		$GLOBALS["FUNCTIONS_FORCE_CALL"]= "auto";
	}
	
?>
