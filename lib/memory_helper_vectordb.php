<?php


// Should move this to conf.

$VECTORDB_URL = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["CHROMADB_URL"];
$VECTORDB_URL_COLLECTION_NAME = "herika_memories";
$VECTORDB_URL_COLLECTION = "";
$VECTORDB_TIME_DELAY = isset($GLOBALS["FEATURES"]["MEMORY_TIME_DELAY"])? $GLOBALS["FEATURES"]["MEMORY_TIME_DELAY"]:10;
$VECTORDB_QUERY_SIZE = isset($GLOBALS["FEATURES"]["MEMORY_CONTEXT_SIZE"])? $GLOBALS["FEATURES"]["MEMORY_CONTEXT_SIZE"]:1;

function getCollectionUID()
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

	$responseData = @file_get_contents("$VECTORDB_URL/api/v1/collections/herika_memories");
	if ($responseData === false) {
		$headers = array(
			'Content-Type: application/json',
		);

		$requestData = array(
			'name' => $VECTORDB_URL_COLLECTION_NAME,
			'metadata' => ["hnsw:space" => "cosine"]
		);

		$jsonData = json_encode($requestData);
		$context = stream_context_create(
			array(
				'http' => array(
					'method' => 'POST',
					'header' => implode("\r\n", $headers),
					'content' => $jsonData,
				)
			)
		);


		$response = file_get_contents("$VECTORDB_URL/api/v1/collections", false, $context);
		$responseData = @file_get_contents("$VECTORDB_URL/api/v1/collections/herika_memories");

	}

	$jsonDataRes = json_decode($responseData, true);


	$VECTORDB_URL_COLLECTION = $jsonDataRes["id"];

	return $VECTORDB_URL_COLLECTION;

}

function getElement($id)
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$requestData = array(
		'ids' => [$id]
	);

	// Convert the request data to JSON
	$jsonData = json_encode($requestData);

	//echo "$jsonData";
	// Set the HTTP headers
	$headers = array(
		'Content-Type: application/json',
	);

	// Create a stream context for the HTTP request
	$context = stream_context_create(
		array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $jsonData,
			)
		)
	);

	// Perform the HTTP POST request
	$response = file_get_contents($VECTORDB_URL . "/api/v1/collections/$VECTORDB_URL_COLLECTION/get", false, $context);

	// Check if the response is successful
	if ($response === false) {
		// Handle error
		die("Error: Unable to fetch response.");
	}

	// Decode the JSON response
	$responseData = json_decode($response, true);

	// Handle the response data as needed
	// var_dump($responseData);
	return $responseData["documents"][0];

}


function deleteElement($id)
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$requestData = array(
		'ids' => [$id]
	);

	// Convert the request data to JSON
	$jsonData = json_encode($requestData);

	//echo "$jsonData";
	// Set the HTTP headers
	$headers = array(
		'Content-Type: application/json',
	);

	// Create a stream context for the HTTP request
	$context = stream_context_create(
		array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $jsonData,
			)
		)
	);

	// Perform the HTTP POST request
	$response = file_get_contents($VECTORDB_URL . "/api/v1/collections/$VECTORDB_URL_COLLECTION/delete", false, $context);

	// Check if the response is successful
	if ($response === false) {
		// Handle error
		die("Error: Unable to fetch response.");
	}

	// Decode the JSON response
	$responseData = json_decode($response, true);

	// Handle the response data as needed
	// var_dump($responseData);
	if (isset($responseData["documents"][0]))
		return $responseData["documents"][0];
	else
		return "";

}


function deleteCollection()
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$data = array('name' => $VECTORDB_URL_COLLECTION_NAME);
	$options = array(
		'http' => array(
			'header' => "Content-type: application/json\r\n",
			'method' => 'DELETE',
			'content' => json_encode($data),
		),
	);

	$context = stream_context_create($options);

	// Perform the HTTP POST request
	$response = file_get_contents($VECTORDB_URL . "/api/v1/collections/$VECTORDB_URL_COLLECTION_NAME", false, $context);

}


function countMemories()
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$response = file_get_contents($VECTORDB_URL . "/api/v1/collections/$VECTORDB_URL_COLLECTION/count", false);
	
	return json_decode($response,true);

}


function storeMemory($embeddings, $text, $id)
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION;

	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$requestData = array(
		'documents' => [$text],
		'metadatas' => [["category" => "background story"]],
		'embeddings' => [$embeddings],
		'ids' => [$id]
	);

	// Convert the request data to JSON
	$jsonData = json_encode($requestData);

	//echo "$jsonData";
	// Set the HTTP headers
	$headers = array(
		'Content-Type: application/json',
	);

	// Create a stream context for the HTTP request
	$context = stream_context_create(
		array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $jsonData,
			)
		)
	);

	// Perform the HTTP POST request
	$response = file_get_contents($VECTORDB_URL . "/api/v1/collections/$VECTORDB_URL_COLLECTION/add", false, $context);

	// Check if the response is successful
	if ($response === false) {
		// Handle error
		die("Error: Unable to fetch response.");
	}

	// Decode the JSON response
	$responseData = json_decode($response, true);

	// Handle the response data as needed
	// var_dump($responseData);

}



function queryMemory($embeddings)
{
	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION, $VECTORDB_TIME_DELAY,$db;


	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$requestData = array(
		'query_embeddings' => [$embeddings],
		'n_results' => 5
	);

	// Convert the request data to JSON
	$jsonData = json_encode($requestData);

	//echo "$jsonData";
	// Set the HTTP headers
	$headers = array(
		'Content-Type: application/json',
	);

	// Create a stream context for the HTTP request
	$context = stream_context_create(
		array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $jsonData,
			)
		)
	);

	// Perform the HTTP POST request
	$response = file_get_contents($VECTORDB_URL . "/api/v1/collections/$VECTORDB_URL_COLLECTION/query", false, $context);

	// Check if the response is successful
	if ($response === false) {
		// Handle error
		die("Error: Unable to fetch response.".__LINE__);
	}

	// Decode the JSON response
	$responseData = json_decode($response, true);

	$dbResults = [];
	
	foreach ($responseData["ids"][0] as $n => $id) {
		$results = $db->query("select message as content,uid,localts,momentum from memory where uid=$id order by uid asc");
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {

			if ($row["localts"] > (time() - 60 * $VECTORDB_TIME_DELAY)) // Ten minutes to get things as memories
				continue;
			$dbResults[] = [
				"memory_id" => $row["uid"],
				"briefing" => $row["content"],
				"timestamp" => $row["localts"],
				"distance" => $responseData["distances"][0][$n]
			];

		}

	}

	if (sizeof($dbResults) > 0) {
		function cmp($a, $b)
		{
			if ($a["distance"] == $b["distance"]) {
				return 0;
			}
			return ($a["distance"] < $b["distance"]) ? -1 : 1;
		}
		uasort($dbResults, 'cmp');
		// Use $VECTORDB_QUERY_SIZE here
		$GLOBALS["DEBUG_DATA"]["memory_system"][]=$responseData;
		return ["item" => "{$GLOBALS["HERIKA_NAME"]}'s memories", "content" => $dbResults[0]];

	} else {
		return null;
	}
}



?>
