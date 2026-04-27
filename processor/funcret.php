<?php
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . ".last_tool_call_openai.id.txt")) {
	$lastCallId = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . ".last_tool_call_openai.id.txt");
} else {
	$lastCallId = "";
}

$returnFunction = explode("@", $gameRequest[3]); // Function returns here


$functionLocaleName = getFunctionTrlName($returnFunction[1]);

$functionCodeName = $functionLocaleName;

$functionCodeName = $returnFunction[1];

$useFunctionsAgain = false;

$forceAttackingText = false;

if (isset($returnFunction[2])) {
	// Patch. 
	$returnFunction[2] = trim($returnFunction[2]);

	error_log("[CHIM] Checking <$functionCodeName> <{$returnFunction[1]}>");

	if ($functionCodeName == "GetTopicInfo") {
		$argName = "topic";
		// Lets overwrite this
		// Get info about $returnFunction[2]}
		$returnFunction[3] = "";

		//
	} else if ($functionCodeName == "LeadTheWayTo") {
		$argName = "location";
		$GLOBALS["OPENAI_MAX_TOKENS"] = "64";	// Force a short response, as IA here tends to simulate the whole travel

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


	} else if ($functionCodeName == "MoveTo") {
		if (strpos($gameRequest[3], "LeadTheWayTo") !== false) {// PatchHack. If Moving returning Shoud use TravelTo, enable functions again
			$useFunctionsAgain = true;
			$request = "(use function call '" . getFunctionTrlName("LeadTheWayTo") . "' to travel) $request";
		}
		$argName = "target";


	} else if ($functionCodeName == "Attack") {
		//$useFunctionsAgain=true;
		$forceAttackingText = true;
		$argName = "target";
		$useFunctionsAgain = false;


	} else if ($functionCodeName == "GetTime") {
		//$useFunctionsAgain=true;
		$argName = "datestring";
		//$useFunctionsAgain=true;


	} else if ($functionCodeName == "get_current_mission") {		// Disabled, current task is always provided.
		//$useFunctionsAgain=true;
		$argName = "description";
		//$useFunctionsAgain=true;


	} else if ($functionCodeName == "CheckInventory") {
		//$useFunctionsAgain=true;
		$argName = "target";
		//$useFunctionsAgain=true;


	} else if ($functionCodeName == "GiveItemTo") {
		// Parse item info from function arguments
		$argName = "target";
		$useFunctionsAgain = false;

		// Item transfer is logged separately, so we just acknowledge
		// The actual inventory sync will happen via container events

	} else if ($functionCodeName == "GiveGoldTo") {
		// Parse gold amount from function arguments
		$argName = "target";
		$useFunctionsAgain = false;

	} else if ($functionCodeName == "GiveItemToPlayer") {
		$useFunctionsAgain = true;
		$request = "(use action '" . getFunctionTrlName("TakeGoldFromPlayer") . "' if needed) $request";


	} else if ($functionCodeName == "TakeGoldFromPlayer") {
		$useFunctionsAgain = true;
		die();

	} else if ($functionCodeName == "RentRoom") {
		$argName = "target";
		$useFunctionsAgain = false;
	} else if ($functionCodeName == "HireCarriage") {
		$argName = "target";
		$useFunctionsAgain = false;
		$GLOBALS["OPENAI_MAX_TOKENS"] = "64";
		$request = "(reply with one short line accepting payment and ending the conversation. Do not ask follow-up questions.) $request";
	} else if ($functionCodeName == "HireFerry") {
		$argName = "target";
		$useFunctionsAgain = false;
		$GLOBALS["OPENAI_MAX_TOKENS"] = "64";
		$request = "(reply with one short line accepting payment and ending the conversation. Do not ask follow-up questions.) $request";
	} else if ($functionCodeName == "AddBounty") {
		$argName = "target";
		$useFunctionsAgain = true;
		$request = "(You just added a bounty to the player. You may follow up with ArrestPlayer or PayBounty if appropriate. React in character.) $request";
	} else if ($functionCodeName == "PayBounty") {
		$argName = "target";
		$useFunctionsAgain = false;
		$GLOBALS["OPENAI_MAX_TOKENS"] = "64";
		$request = "(The player has already paid the bounty and stolen items were removed from inventory. This action is fully complete. Reply with one short confirmation line, do not ask follow-up questions, and end the conversation.) $request";
	} else if ($functionCodeName == "ArrestPlayer") {
		$argName = "target";
		$useFunctionsAgain = false;
		$request = "(You attempted to arrest the player. They get a submit/resist prompt; resist starts combat. Deliver a stern final line.) $request";
	} else if ($functionCodeName == "ForgiveCrime") {
		$argName = "target";
		$useFunctionsAgain = false;
		$request = "(You forgave the player's crimes and cleared their bounty. Acknowledge this with a warning or blessing.) $request";

	} else if ($functionCodeName == "FollowPlayer") {
		terminate();

	} else {
		error_log("[CHIM] Checking <$functionCodeName> in external declarations");

		if (isset($GLOBALS["FUNCRET"][$functionCodeName])) {

			$reflection = new ReflectionFunction($GLOBALS["FUNCRET"][$functionCodeName]);
			// Get number of required parameters
			$required = $reflection->getNumberOfRequiredParameters();
			if ($required == 1)
				$frResponse = call_user_func_array($GLOBALS["FUNCRET"][$functionCodeName], ["gameRequest" => $gameRequest]);
			else
				$frResponse = call_user_func_array($GLOBALS["FUNCRET"][$functionCodeName], []);



			if (isset($frResponse["argName"]))
				$argName = $frResponse["argName"];
			if (isset($frResponse["request"]))
				$request = $frResponse["request"];
			if (isset($frResponse["useFunctionsAgain"]))
				$useFunctionsAgain = $frResponse["useFunctionsAgain"];


		} else if (isset($GLOBALS["FUNCRET"][$returnFunction[1]])) {	// Patch, search also by codename

			$frResponse = call_user_func_array($GLOBALS["FUNCRET"][$returnFunction[1]], []);

			if (isset($frResponse["argName"]))
				$argName = $frResponse["argName"];
			if (isset($frResponse["request"]))
				$request = $frResponse["request"];
			if (isset($frResponse["useFunctionsAgain"]))
				$useFunctionsAgain = $frResponse["useFunctionsAgain"];


		} else
			$argName = "target";

	}
	$functionCalled[] = array(
		'role' => 'assistant',
		'content' => null,
		'tool_calls' => [
			array(
				"id" => $lastCallId,
				"type" => "function",
				"function" => ["name" => $functionLocaleName, "arguments" => "{\"$argName\":\"{$returnFunction[2]}\"}"]
			)
		]
	);

} else
	//$functionCalled[] = array('role' => 'assistant', 'content' => null, 'tool_calls' => [array("id" => $lastCallId, "function"=>["name"=>$functionLocaleName,"arguments" => "{\"$argName\":\"{$returnFunction[2]}\"}"])]);
	$functionCalled[] = array('role' => 'assistant', 'content' => null, 'tool_calls' => [array("id" => $lastCallId, "function" => ["name" => $functionLocaleName, "arguments" => "{\"$argName\":\"\"}"])]); // $returnFunction[2] is not set here

$returnFunctionArray[] = array('role' => 'tool', 'content' => "{$returnFunction[3]}", 'tool_call_id' => "$lastCallId");

if ($forceAttackingText)
	$returnFunctionArray[] = array('role' => $LAST_ROLE, 'content' => selectRandomInArray($GLOBALS["PROMPTS"]["afterattack"]["cue"]) . " {$GLOBALS["HERIKA_NAME"]}: ");
else
	$returnFunctionArray[] = array('role' => $LAST_ROLE, 'content' => $request);


$contextData = array_merge($head, ($contextDataFull), $functionCalled, $returnFunctionArray);

file_put_contents(__DIR__ . "/../log/context_for_{$GLOBALS["HERIKA_NAME"]}_after_func.txt", print_r($contextData, true));

if ($useFunctionsAgain) {
	$GLOBALS["FUNCTIONS_ARE_ENABLED"] = true;
	$GLOBALS["FUNCTIONS"];
	$GLOBALS["FUNCTIONS_FORCE_CALL"] = "auto";
} else {
	$GLOBALS["FUNCTIONS_ARE_ENABLED"] = false;
}

?>
