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
	compact	    Recreate memories_summary database from memories

");
} else {

    if ($argv[1]=="get") {
        echo "Get memory {$argv[2]}".PHP_EOL;
        $data=getElement($argv[2]);
        print_r($data);
        print_r($GLOBALS["DEBUG_DATA"]);

    } elseif ($argv[1]=="query") {
        echo "Query memory for '{$argv[2]}'".PHP_EOL;
        $embeddings=getEmbedding("{$argv[2]}");
        //print_r($embeddings);
        $db=new sql();
        $res=queryMemory($embeddings);
        print_r($res);
        print_r($GLOBALS["DEBUG_DATA"]);

    } elseif ($argv[1]=="sync") {
        deleteCollection();
        echo "Creating memories".PHP_EOL;
        ;
        $link = new sql();
        $results = $link->query("select summary as content,uid,classifier from memory_summary");
        $counter=0;
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $TEST_TEXT=$row["content"];
            $embeddings=getEmbedding($TEST_TEXT);
            //print_r($embeddings);
            storeMemory($embeddings, $TEST_TEXT, $row["uid"], $row["classifier"]);
            

            $counter++;
            echo "Memory created in ChromaDB $counter\n";
        }
        //print_r($GLOBALS["DEBUG_DATA"]);

    } elseif ($argv[1]=="compact") {

        echo "Creating compact memories. Run a sync later".PHP_EOL;
        ;
        $db = new sql();

        $results = $db->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary");

        $maxRow=$results[0]["gamets_truncated"]+0;

        $results = $db->query("insert into memory_summary select * from ( 
								select min(gamets) as gamets_truncated,count(*) as n,
								GROUP_CONCAT(message,char(13) || char(10)|| char(13) || char(10)) as packed_message ,'','dialogue',min(uid) as uid
								from memory
								where message not like 'Dear Diary%'
								group by round(gamets/1000000 ,0) order by round(gamets/1000000 ,0) ASC
							  ) where gamets_truncated>$maxRow
							");

        $results = $db->query("insert into memory_summary 
								select gamets,1,message,message,'diary',uid
								from memory
								where message like 'Dear Diary%'
								and gamets>$maxRow
							");


        echo "Creating memories".PHP_EOL;
        
		require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");
		
		
        $results = $db->query("select packed_message,uid,classifier from memory_summary where gamets_truncated>$maxRow or summary=''");
        $counter=0;
		$toUpdate=[];
		
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {


            if ($row["classifier"]=="diary") {
                $TEST_TEXT=$row["packed_message"];

            } else {
				$GLOBALS["COMMAND_PROMPT"]="";
				
				$gameRequest=["summary"];	// Fake a diary call.
				
				$prompt=[];
                $prompt[] = array('role' => 'user', 
								  'content' => "write into {$GLOBALS["HERIKA_NAME"]}'s diary a summary of this: [... {$row["packed_message"]} ...]. Give special importance to the characters and their attitudes, as well as to the places. ");

				
                $GLOBALS["FORCE_MAX_TOKENS"]=165;

                $connectionHandler=new connector();
                $connectionHandler->open($prompt, []);

                $buffer="";
                $totalBuffer="";
                $breakFlag=false;

                while (true) {

                    if ($breakFlag) {
                        break;
                    }

                    $buffer.=$connectionHandler->process();
                    $totalBuffer.=$buffer;

                    if ($connectionHandler->isDone()) {
                        $breakFlag=true;
                    }

                }

                $connectionHandler->close();

                $toUpdate[]=["uid"=>$row["uid"],"summary"=>$buffer];
                $TEST_TEXT=$buffer;
            }


            $embeddings=getEmbedding($TEST_TEXT);
            //print_r($embeddings);
			echo $TEST_TEXT;
            
            storeMemory($embeddings, $TEST_TEXT, $row["uid"], $row["classifier"]);
            

            $counter++;
            echo "\nMemory created $counter\n";
			sleep(1);
			
        }

		foreach ($toUpdate as $uq) {
			 //echo "update memory_summary set summary='".SQLite3::escapeString($uq["summary"])."' where uid={$uq["uid"]}";
			 $db->execQuery("update memory_summary set summary='".SQLite3::escapeString($uq["summary"])."' where uid={$uq["uid"]}");
			
		}


        /*
         while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $db->insert(
                'memory',
            array(
                'localts' => time(),
                'speaker' => (SQLite3::escapeString($GLOBALS["HERIKA_NAME"])),
                'listener' => (SQLite3::escapeString($GLOBALS["HERIKA_NAME"])),
                'message' => (SQLite3::escapeString($row["packed_message"])),
                'gamets' => $row["gamets_truncated"],
                'session' => "pending",
                'momentum'=>time()
                )
            );
        }
        */

        //$db->delete("memory", "speaker<>listener and message not like '%Dear Diary%' ");

    } elseif ($argv[1]=="recreate") {
        deleteCollection();
        getCollectionUID();


    } elseif ($argv[1]=="count") {
        echo countMemories().PHP_EOL;

    } else {
        echo "Command not found: {$argv[1]}".PHP_EOL;
        echo "Use ".basename(__FILE__)." without args to see help".PHP_EOL;

    }

}
