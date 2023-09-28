<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");


if (!isset($argv[1])) {
	die(
"Use ".basename(__FILE__)." command parm

commands: 
	
	query		Query for a memory. Example: query 'What do you know about Saadia?'
	count		Count ChromaDB memories.
	sync 		Sync ChromaDB database. 
	get 		Get memory. Example: get 56
	recreate	Recreate collection.

");
} else {
	
	if ($argv[1]=="get") {
		echo "Get memory {$argv[2]}".PHP_EOL;
		$data=getElement($argv[2]);
		print_r($data);

		
	} else if ($argv[1]=="query") {
		echo "Query memory for {$argv[1]}".PHP_EOL;
		$embeddings=getEmbeddingLocal("{$argv[2]}");
		//print_r($embeddings);
		$res=queryMemory($embeddings);
		print_r($res["content"]);

		
	} else if ($argv[1]=="sync") {
		deleteCollection();
		echo "Creating memories".PHP_EOL;;
		$link = new sql();
		$results = $link->query("select message as content,uid from memory");
		$counter=0;
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			$TEST_TEXT=$row["content"];
			$embeddings=getEmbeddingLocal($TEST_TEXT);
			storeMemory($embeddings,$TEST_TEXT,$row["uid"]);
			$counter++;
			echo "Memory created $counter\n";
		}	
	} else if ($argv[1]=="recreate") {
		deleteCollection();
		getCollectionUID();
	
		
	} else if ($argv[1]=="count") {
		echo countMemories().PHP_EOL;
		
	} else {
		echo "Command not found: {$argv[1]}".PHP_EOL;
		echo "Use ".basename(__FILE__)." without args to see help".PHP_EOL;

	}
	
}

?>
