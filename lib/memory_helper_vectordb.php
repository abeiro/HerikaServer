<?php






function storeMemory($embeddings, $text, $id, $category='past dialogues' )
{

	$url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"].'/embed';
	$data = [
		'text' => $text
	];

	// Convert to JSON
	$options = [
		'http' => [
			'method'  => 'POST',
			'header'  => "Content-Type: application/json\r\n" .
						"Accept: application/json\r\n",
			'content' => json_encode($data),
			'ignore_errors' => true // to capture error messages if any
		]
	];

	// Create context and send the request
	$context  = stream_context_create($options);
	$response = file_get_contents($url, false, $context);

	// Output the response
	if ($response === false) {
		Logger::error("Request failed.\n");
		return false;
	} else {
		Logger::info("Request done:\n");
	}

	$vector = json_decode($response, true);
	
	// Check if JSON decode was successful and embedding exists
	if ($vector === null || !isset($vector["embedding"])) {
		Logger::error("Invalid response format or missing embedding data: " . $response);
		return false;
	}

	// Handle both array and string formats for embedding
	$embedding_data = $vector["embedding"];
	if (is_string($embedding_data)) {
		// If it's already a string, use it directly (might be JSON string)
		$embedding_str = $embedding_data;
	} else if (is_array($embedding_data)) {
		// If it's an array, convert to comma-separated string
		$embedding_str = implode(",", $embedding_data);
	} else {
		Logger::error("Embedding data is neither string nor array: " . gettype($embedding_data));
		return false;
	}

	$GLOBALS["db"]->execQuery("update memory_summary set embedding='[" . $embedding_str . "]' where rowid=$id");
	return true;
}

function storeMemoryOghma($embeddings, $text, $id)
{

	$url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"].'/embed';
	$data = [
		'text' => $text
	];

	// Convert to JSON
	$options = [
		'http' => [
			'method'  => 'POST',
			'header'  => "Content-Type: application/json\r\n" .
						"Accept: application/json\r\n",
			'content' => json_encode($data),
			'ignore_errors' => true // to capture error messages if any
		]
	];

	// Create context and send the request
	$context  = stream_context_create($options);
	$response = file_get_contents($url, false, $context);

	// Output the response
	if ($response === false) {
		Logger::error("Request failed.\n");
		return false;
	} else {
		Logger::info("Request done:\n");
	}

	$vector = json_decode($response, true);
	
	// Check if JSON decode was successful and embedding exists
	if ($vector === null || !isset($vector["embedding"])) {
		Logger::error("Invalid response format or missing embedding data: " . $response);
		return false;
	}

	// Handle both array and string formats for embedding
	$embedding_data = $vector["embedding"];
	if (is_string($embedding_data)) {
		// If it's already a string, use it directly (might be JSON string)
		$embedding_str = $embedding_data;
	} else if (is_array($embedding_data)) {
		// If it's an array, convert to comma-separated string
		$embedding_str = implode(",", $embedding_data);
	} else {
		Logger::error("Embedding data is neither string nor array: " . gettype($embedding_data));
		return false;
	}

	$cleanedid = $GLOBALS["db"]->escape($id);
	$GLOBALS["db"]->execQuery("update oghma set vector384='[" . $embedding_str . "]' where topic='$cleanedid'");
	return true;
}



function queryMemory($embeddings,$category='past dialogues')
{

}



?>
