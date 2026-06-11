<?php
if (!function_exists('chimActionShouldEmitDebugNotification')) {
	function chimActionShouldEmitDebugNotification($functionCodeName)
	{
		if (!function_exists('herikaGetActionCatalogRow')) {
			return false;
		}

		$row = herikaGetActionCatalogRow($functionCodeName);
		if (!is_array($row)) {
			return false;
		}

		$metadata = $row['metadata'] ?? [];
		if (!is_array($metadata)) {
			return false;
		}

		return !empty($metadata['debug_notification']) || !empty($metadata['top_left_notification']);
	}
}

if (!function_exists('chimSanitizeDebugNotificationText')) {
	function chimSanitizeDebugNotificationText($text)
	{
		$text = str_replace(["\r", "\n"], ' ', strval($text));
		$text = str_replace('@', ' at ', $text);
		$text = str_replace('|', '/', $text);
		$text = preg_replace('/\s+/', ' ', $text);
		return trim(strval($text));
	}
}

if (!function_exists('chimBuildMetadataFollowupRequest')) {
	function chimBuildMetadataFollowupRequest($requestText, $promptText)
	{
		$requestText = trim(strval($requestText));
		$promptText = trim(strval($promptText));
		if ($promptText === '') {
			return $requestText;
		}
		if ($requestText === '') {
			return "({$promptText})";
		}

		return "({$promptText}) {$requestText}";
	}
}

if (!function_exists('chimShouldSkipFuncretInfoActionLog')) {
	function chimShouldSkipFuncretInfoActionLog($functionCodeName)
	{
		$functionCodeName = trim(strval($functionCodeName));
		if ($functionCodeName === '') {
			return true;
		}

		if (function_exists('isNarratorPrivateActionName') && isNarratorPrivateActionName($functionCodeName)) {
			return true;
		}

		return function_exists('herikaActionCatalogMetadataFlagEnabled')
			&& herikaActionCatalogMetadataFlagEnabled($functionCodeName, 'suppress_placeholder_infoaction');
	}
}

if (!function_exists('chimLogFuncretResultInfoAction')) {
	function chimLogFuncretResultInfoAction($functionCodeName, $argName, $argValue, $resultText)
	{
		if (!function_exists('logEvent') || !function_exists('herikaBuildFuncretResultInfoActionMessage')) {
			return false;
		}

		if (chimShouldSkipFuncretInfoActionLog($functionCodeName)) {
			return false;
		}

		$message = herikaBuildFuncretResultInfoActionMessage($functionCodeName, $argName, $argValue, $resultText);
		if ($message === '') {
			return false;
		}

		$gameRequestCopy = $GLOBALS["gameRequest"] ?? [];
		if (!is_array($gameRequestCopy)) {
			return false;
		}

		$gameRequestCopy[0] = "infoaction";
		$gameRequestCopy[3] = $message;
		logEvent($gameRequestCopy);
		return true;
	}
}

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
$argName = "target";
$currentFollowupChainDepth = 0;
$followupConfig = function_exists('herikaActionCatalogGetResolvedFollowupConfig')
	? herikaActionCatalogGetResolvedFollowupConfig($functionCodeName)
	: [];
$argName = function_exists('herikaResolveFuncretArgumentName')
	? herikaResolveFuncretArgumentName($functionCodeName, $followupConfig)
	: trim(strval($followupConfig['arg_name'] ?? 'target'));
if ($argName === '') {
	$argName = 'target';
}

chimLogFuncretResultInfoAction($functionCodeName, $argName, $returnFunction[2] ?? '', $returnFunction[3] ?? '');

if (isset($returnFunction[2])) {
	// Patch. 
	$returnFunction[2] = trim($returnFunction[2]);

	error_log("[CHIM] Checking <$functionCodeName> <{$returnFunction[1]}>");

	$followupEnabled = !empty($followupConfig['enabled']);
	$followupPrompt = trim(strval($followupConfig['prompt'] ?? ''));

	if (!$followupEnabled) {
		terminate();

	}

	$useFunctionsAgain = !empty($followupConfig['use_functions_again']);
	$currentFollowupChainDepth = function_exists('herikaActionCatalogGetLastIssuedActionFollowupChainDepth')
		? intval(herikaActionCatalogGetLastIssuedActionFollowupChainDepth($functionCodeName))
		: 0;
	$followupChainLimit = function_exists('herikaActionCatalogGetFollowupChainLimit')
		? intval(herikaActionCatalogGetFollowupChainLimit())
		: 1;

	if ($useFunctionsAgain && $followupChainLimit > 0 && $currentFollowupChainDepth >= $followupChainLimit) {
		error_log("[CHIM] Follow-up action chaining disabled for {$functionCodeName}: depth {$currentFollowupChainDepth} reached limit {$followupChainLimit}");
		$useFunctionsAgain = false;
	}

	if ($followupPrompt !== '') {
		$request = chimBuildMetadataFollowupRequest($request, $followupPrompt);

	} else {
		terminate();
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

$debugNotificationText = chimSanitizeDebugNotificationText($returnFunction[3] ?? '');
if ($debugNotificationText !== '' && chimActionShouldEmitDebugNotification($functionCodeName)) {
	$notificationSpeaker = trim(strval($GLOBALS["HERIKA_NAME"] ?? ''));
	if ($notificationSpeaker === '') {
		$notificationSpeaker = 'The Narrator';
	}

	echo $notificationSpeaker . "|rolecommand|DebugNotification@" . $debugNotificationText . PHP_EOL;
}

$returnFunctionArray[] = array('role' => 'tool', 'content' => "{$returnFunction[3]}", 'tool_call_id' => "$lastCallId");

$returnFunctionArray[] = array('role' => $LAST_ROLE, 'content' => $request);


$contextData = array_merge($head, ($contextDataFull), $functionCalled, $returnFunctionArray);

file_put_contents(__DIR__ . "/../log/context_for_{$GLOBALS["HERIKA_NAME"]}_after_func.txt", print_r($contextData, true));

if ($useFunctionsAgain) {
	$GLOBALS["FUNCTIONS_ARE_ENABLED"] = true;
	$GLOBALS["FUNCTIONS"];
	$GLOBALS["FUNCTIONS_FORCE_CALL"] = "auto";
	$GLOBALS["FOLLOWUP_CHAIN_NEXT_DEPTH"] = $currentFollowupChainDepth + 1;
} else {
	$GLOBALS["FUNCTIONS_ARE_ENABLED"] = false;
	unset($GLOBALS["FOLLOWUP_CHAIN_NEXT_DEPTH"]);
}

?>
