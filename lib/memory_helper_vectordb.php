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
	} else {
		Logger::info("Request done:\n");

	}
	$vector=json_decode($response,true);
	$GLOBALS["db"]->execQuery("update memory_summary set embedding='[".implode(",",$vector["embedding"])."]' where rowid=$id");


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
	} else {
		Logger::info("Request done:\n");

	}
	$vector=json_decode($response,true);
	$cleanedid=$GLOBALS["db"]->escape($id);
	$GLOBALS["db"]->execQuery("update oghma set vector384='[".implode(",",$vector["embedding"])."]' where topic='$cleanedid'");


}



function queryMemory($embeddings,$category='past dialogues')
{

}



?>
