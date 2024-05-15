<?php

/*
curl -X 'POST' \
  'http://127.0.0.1:8000/add' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '[
  {"id":1,"text":"Location: Saarthal, Hold: Winterhold.People: Agabur, Stenvar, Lydia, Jenassa.Mission: Reforge the Gauldur Amulet.Agabur and his companions, Stenvar, Lydia, and Jenassa, continue their journey in Saarthal. Agabur relies on his trusty journal to navigate their next steps. They decide to reforging the Gauldur Amulet and see what else fate has in store for them. Lydia remarks that Stenvar should start carrying a journal as well to keep track of important details, and Jenassa expresses her desire to return to Morrowind, stating that the endless winter is getting to her. Meanwhile, Stenvar jokes about missing the familiar scents and sights of their homeland. The group remains focused, knowing that they must work together to defeat Alduin, the World-Eater, and prevent destruction upon the land",
"tags":"Saarthal, Hold: Winterhold,"}
]'

*/



// Should move this to conf.

$VECTORDB_URL = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["CHROMADB_URL"];
$VECTORDB_URL_COLLECTION_NAME = "herika_memories";
$VECTORDB_URL_COLLECTION = "";
$VECTORDB_TIME_DELAY = isset($GLOBALS["FEATURES"]["MEMORY_TIME_DELAY"])? $GLOBALS["FEATURES"]["MEMORY_TIME_DELAY"]:10;
$VECTORDB_QUERY_SIZE = isset($GLOBALS["FEATURES"]["MEMORY_CONTEXT_SIZE"])? $GLOBALS["FEATURES"]["MEMORY_CONTEXT_SIZE"]:1;

function getCollectionUID()
{


}

function getElement($id)
{

	

}


function deleteElement($id)
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

		// URL to send the request to
	$url = 'http://127.0.0.1:8000/delete';

	// Headers for the request
	$headers = [
		'Accept: application/json',
		'Content-Type: application/json'
	];

	// Data to send in the request
	$data = json_encode([
		$id
	]);

	// Create a stream context with the headers and data
	$options = [
		'http' => [
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $data
		]
	];
	$context = stream_context_create($options);

	// Send the request and get the response
	$response = file_get_contents($url, false, $context);

	// Output the response
	return  $response;

}


function deleteCollection()
{



}


function countMemories()
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION;

	
	$response = file_get_contents("http://127.0.0.1:8000/count");
	
	return json_decode($response,true);

}


function storeMemory($embeddings, $text, $id, $category='past dialogues' ,$autosync=false)
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION;


	$url = 'http://127.0.0.1:8000/add';

	// Headers for the request
	$headers = [
		'Accept: application/json',
		'Content-Type: application/json'
	];

	// Data to send in the request
	$data = json_encode([
		[
			"id" => $id,
			"text" => $text,
			"tags" => $category
		]
	]);

	// Create a stream context with the headers and data
	$options = [
		'http' => [
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $data
		]
	];
	$context = stream_context_create($options);
	
	$response = file_get_contents($url, false, $context);
	if ($autosync)
		file_get_contents("http://127.0.0.1:8000/index");
	
	$GLOBALS["DEBUG_DATA"]["chromadb_element"]=$response;
}

function flushVectorDB() {
	file_get_contents("http://127.0.0.1:8000/index");
}



function queryMemory($embeddings,$category='past dialogues')
{
	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION, $VECTORDB_TIME_DELAY,$db;

	$url = 'http://127.0.0.1:8000/search?query='.urlencode($embeddings);
	$response = file_get_contents($url, false);
	

	// Check if the response is successful
	if ($response === false) {
		// Handle error
		die("Error: Unable to fetch response.".__LINE__);
	}

	// Decode the JSON response
	$responseData = json_decode($response, true);

	$dbResults = [];
	
	foreach ($responseData as $n => $element) {
		$results = $db->query("select summary as content,uid,gamets_truncated,classifier from memory_summary where uid={$element["id"]} order by uid asc");
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {

			if ($row["gamets_truncated"] > (time() - (60 * 20  * $VECTORDB_TIME_DELAY))) // Ten minutes to get things as memories
				continue;

			$dbResults[] = [
				"memory_id" => $row["uid"],
				"briefing" => $row["content"],
				"timestamp" => $row["gamets_truncated"],
				"classifier" => $row["classifier"],
				"score" => $element["score"]
			];

		}

	}

	// Lets sort by distance
	if (sizeof($dbResults) > 0) {
		if (!function_exists("cmp")) {
			function cmp($a, $b)
			{
				if ($a["score"] == $b["score"]) {
					return 0;
				}
				return ($a["score"] > $b["score"]) ? -1 : 1;
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
