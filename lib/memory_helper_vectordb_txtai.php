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

$VECTORDB_URL = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"];
$VECTORDB_URL_COLLECTION_NAME = md5($GLOBALS["HERIKA_NAME"]);
$VECTORDB_URL_COLLECTION = "";
$VECTORDB_TIME_DELAY = isset($GLOBALS["FEATURES"]["MEMORY_TIME_DELAY"])? $GLOBALS["FEATURES"]["MEMORY_TIME_DELAY"]:10;
$VECTORDB_QUERY_SIZE = isset($GLOBALS["FEATURES"]["MEMORY_CONTEXT_SIZE"])? $GLOBALS["FEATURES"]["MEMORY_CONTEXT_SIZE"]:1;

function getCollectionUID()
{


}

function getElement($id)
{
	global $db;
	$results = $db->fetchAll("select summary as content,uid,gamets_truncated,classifier from memory_summary where rowid=$id order by uid asc");
	if (is_array($results)) {
		$row = $results[0];
		if (is_array($row))
			$dbResult = [
					"memory_id" => $row["uid"],
					"briefing" => $row["content"],
					"timestamp" => $row["gamets_truncated"],
					"classifier" => $row["classifier"],
					"score" => $row["score"]
			];

		return $dbResult;
	}
	return null;

}


function deleteElement($id,$onlyembedding=false)
{

	global $db;

	$results = $db->query("delete from memory_summary where rowid=$id");

	

}


function deleteCollection()
{



}


function countMemories()
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION_NAME, $VECTORDB_URL_COLLECTION,$db;

	$dbResults = $db->fetchAll("SELECT COUNT(*) AS total_records, COUNT(embedding)  AS embedded_vectors,COUNT(summary) AS summarized FROM memory_summary");
	
	
	$response = $dbResults;
	
	return json_encode($response);

}


function storeMemory($embeddings, $text, $id, $category='past dialogues' ,$forceCompanions="")
{

	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION,$db;


	$url =  $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"]."/embeddings?input=".urlencode($embeddings);
	
	

	$response = file_get_contents($url);
	
	$pattern = '/People:\s*(.*)$/m';
	$filteredArray=[];
	if (preg_match($pattern, strtr($text,["and"=>",","AND"=>","]), $matches)) {
		$people = $matches[1];  // The captured names
		$peopleArray = array_map('trim', explode(',', $people));  // Split and trim names into an array

		$filteredArray = array_filter($peopleArray, function($value) {
			return trim($value) !== '';
		});
		
		//print_r($filteredArray);  // Print the array of names
		
	}
	
	if ($category=="diary") {
		$filteredArray=[$forceCompanions];
		
	}
	
	if (is_array($filteredArray))
		$peopleS=implode(",",$filteredArray);
	else
		$peopleS="-";
	
	$vector=json_decode($response,true);
	
	if (sizeof($vector)=="384") {
		$db->update("memory_summary","embedding='$response',companions='$peopleS'","rowid=$id");
		error_log("Using 384 dim vectors".PHP_EOL);
	}
	else if (sizeof($vector)=="768") {
		$db->update("memory_summary","embedding768='$response',companions='$peopleS'","rowid=$id");
		error_log("Using 768 dim vectors".PHP_EOL);
	}
	
	
}

function queryMemory($embeddings,$category='past dialogues',$limitNpc="")
{
	global $VECTORDB_URL, $VECTORDB_URL_COLLECTION, $VECTORDB_TIME_DELAY,$db;

	$url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"]."/embeddings?input=".urlencode($embeddings);
	
	error_log(microtime(true));
	error_log("Get embedding from service");
	$response = file_get_contents($url, false);
	error_log(microtime(true));

	// Check if the response is successful
	if ($response === false) {
		// Handle error
		die("Error: Unable to fetch response.".__LINE__);
	}

	if ($limitNpc)
		$limitNpcFilter=" where companions like '%$limitNpc%'";
	
	error_log("Similarity Search");
	// Decode the JSON response
	$responseData = json_decode($response, true);
	
	if (sizeof($responseData)==384) {
		error_log("Using 384 dim vectors".PHP_EOL);
		$dbResults = $db->fetchAll("select rowid, embedding <-> '$response' as score, summary as content,uid,gamets_truncated,classifier,companions 
			from memory_summary $limitNpcFilter ORDER BY embedding <-> '$response' LIMIT 5 ");
	}
	else if (sizeof($responseData)==768) {
		error_log("Using 768 dim vectors".PHP_EOL);
		$dbResults = $db->fetchAll("select rowid, embedding768 <-> '$response' as score, summary as content,uid,gamets_truncated,classifier,companions 
			from memory_summary $limitNpcFilter ORDER BY embedding768 <-> '$response' LIMIT 5 ");
	}
	
	error_log(microtime(true));

	foreach ($dbResults as $n => $row) {

		if ($row["gamets_truncated"] > (time() - (60 * 20  * $VECTORDB_TIME_DELAY))) // Ten minutes to get things as memories
			continue;
		
		
		$dbResultsFinal[] = [
			"memory_id" => $row["rowid"],
			"briefing" => $row["content"],
			"timestamp" => $row["gamets_truncated"],
			"classifier" => $row["classifier"],
			"score" => $row["score"],
			"companions" => $row["companions"]
		];

	

	}
	error_log(microtime(true));

	// Lets sort by distance
	if (sizeof($dbResultsFinal) > 0) {
		if (!function_exists("cmp")) {
			function cmp($a, $b)
			{
				if ($a["score"] == $b["score"]) {
					return 0;
				}
				return ($a["score"] > $b["score"]) ? 1 : -1;
			}
		}
		uasort($dbResultsFinal, 'cmp');
		// Use $VECTORDB_QUERY_SIZE here
		//$GLOBALS["DEBUG_DATA"]["memory_system"][]=$responseData;
		return ["item" => "{$GLOBALS["HERIKA_NAME"]}'s memories", "content" => $dbResultsFinal];

	} else {
		return null;
	}
	
			
}



?>
