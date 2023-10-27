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
		'ids' => [$id],
		'include'=>["metadatas", "documents", "distances"]
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
	$GLOBALS["DEBUG_DATA"]["chromadb_element"]=$responseData;
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


function storeMemory($embeddings, $text, $id, $category='past dialogues' )
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION;

	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$requestData = array(
		'documents' => [$text],
		'metadatas' => [["category" => "$category"]],
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
	$GLOBALS["DEBUG_DATA"]["chromadb_element"]=$responseData;
}



function queryMemory($embeddings,$category='past dialogues')
{
	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION, $VECTORDB_TIME_DELAY,$db;


	$VECTORDB_URL_COLLECTION = getCollectionUID();

	$requestData = array(
		'query_embeddings' => [$embeddings],
		'n_results' => 5
		//'where'=>["category" => "$category"]
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
		$results = $db->query("select summary as content,uid,gamets_truncated,classifier from memory_summary where uid=$id order by uid asc");
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {

			/*
			if ($responseData["metadatas"][0][$n]["category"]=="diary") {
				$responseData["distances"][0][$n]=$responseData["distances"][0][$n]/1.1;
			}
			*/
			
			if ($row["gamets_truncated"] > (time() - (60 * 20  * $VECTORDB_TIME_DELAY))) // Ten minutes to get things as memories
				continue;
			$dbResults[] = [
				"memory_id" => $row["uid"],
				"briefing" => $row["content"],
				"timestamp" => $row["gamets_truncated"],
				"classifier" => $row["classifier"],
				"distance" => $responseData["distances"][0][$n]
			];

		}

	}

	// Lets sort by distance
	if (sizeof($dbResults) > 0) {
		if (!function_exists("cmp")) {
			function cmp($a, $b)
			{
				if ($a["distance"] == $b["distance"]) {
					return 0;
				}
				return ($a["distance"] < $b["distance"]) ? -1 : 1;
			}
		}
		uasort($dbResults, 'cmp');
		// Use $VECTORDB_QUERY_SIZE here
		$GLOBALS["DEBUG_DATA"]["memory_system"][]=$responseData;
		return ["item" => "{$GLOBALS["HERIKA_NAME"]}'s memories", "content" => $dbResults];

	} else {
		return null;
	}
}



?>
