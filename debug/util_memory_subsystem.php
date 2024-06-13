<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");

$GLOBALS["DBDRIVER"]="postgresql";


require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb_txtai.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");


if (!isset($argv[1])) {
    die(
        "Use ".basename(__FILE__)." command parm

commands: 
	
	query		Query for a memory. Example: query 'What do you know about Saadia?'
	count		Count Memories, memories summarized and memories vectorized.
	sync 		Sync Summaries <> Vector embeddings. Needs TEXT2VEC active
	get 		Get memory. Example: get 56
	recreate	Recreate memory_summary table, 
	compact	    Recreate memory_summary table, and uses AI (LLM) to summarize data. Use 'compact noresync' to avoid TEXT2VEC sync.
	
Note: Memories are stored in memory_summary table, which holds info from events/dialogues... in a time packed format.

");
} else {

    if ($argv[1]=="get") {
        $db=new sql();
        echo "Get memory {$argv[2]}".PHP_EOL;
        $data=getElement($argv[2]);
        print_r($data);
        print_r($GLOBALS["DEBUG_DATA"]);

    } elseif ($argv[1]=="query") {
        echo "Query memory for '{$argv[2]}'".PHP_EOL;

        $db=new sql();
        $res=queryMemory($argv[2],'',$argv[3]);

        print_r($res["content"]);
        
        print_r($GLOBALS["DEBUG_DATA"]);

    } elseif ($argv[1]=="sync") {
        
        echo "Creating memories".PHP_EOL;
        ;
        $db = new sql();
        $results = $db->fetchAll("select summary as content,uid,classifier,rowid,companions from memory_summary where summary is not null");
        $counter=0;
        foreach ($results as $row) {
            
            $TEST_TEXT=$row["content"];
            storeMemory($TEST_TEXT, $TEST_TEXT, $row["rowid"], $row["classifier"],$row["companions"]); // JUST UPDATE vecotr in memory_summary

            $counter++;
            
            echo "Updated vector for  {$row["rowid"]} $counter\n";
        }
        


    } elseif ($argv[1]=="compact") {

        echo "Creating compact memories. Run a sync later".PHP_EOL;
        ;
        $db = new sql();

        $maxRow=PackIntoSummary();


        echo "Creating memories".PHP_EOL;
        $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];
		require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");
		
		
        $results = $db->query("select packed_message,uid,classifier,rowid,companions from memory_summary where gamets_truncated>$maxRow or summary is null  order by uid asc ");
        $counter=0;
		$toUpdate=[];
		
        while ($row = $db->fetchArray($results)) {


            if ($row["classifier"]=="diary") {
                $TEST_TEXT=$row["packed_message"];

            } else {
				$GLOBALS["COMMAND_PROMPT"]="";
				
				$gameRequest=["summary"];	// Fake a diary call.
				
				$CLFORMAT="
Location: {} 
People: {}
Mission: {}
Summary: {}
 ";
				$prompt=[];
                
                $prompt[] = array('role' => 'system', 
								  'content' => "Summarize chat information . Write a summary of this chat: \n#CHAT HISTORY#\n{$row["packed_message"]} \n#END OF CHAT HISTORY#\n");

				
                 
                $prompt[] = array('role' => 'user', 
								  'content' => "Summarize using this format:\n $CLFORMAT");

				
                $GLOBALS["FORCE_MAX_TOKENS"]=$GLOBALS["CONNECTOR"]["koboldcpp"]["MAX_TOKENS_MEMORY"];

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

                $toUpdate[]=["rowid"=>$row["rowid"],"summary"=>$buffer];
                $TEST_TEXT=$buffer;
            }

			
            error_log("$TEST_TEXT");
            if ($argv[2]!="noembed") {
                error_log("Getting embedding");
                storeMemory($TEST_TEXT, $TEST_TEXT, $row["rowid"], $row["classifier"],$row["companions"]);
            }
            

            $counter++;
            echo "\nMemory created $counter\n";
            
            foreach ($toUpdate as $uq) {
			 //echo "update memory_summary set summary='".SQLite3::escapeString($uq["summary"])."' where uid={$uq["uid"]}";
			 $db->execQuery("update memory_summary set summary='".SQLite3::escapeString($uq["summary"])."' where rowid={$uq["rowid"]}");
			
            }
            $toUpdate=[];
			
            
            if ($argv[3])
                if ( ($argv[3]+0)>=$counter)
                    break;
            
            sleep(1);
            //break;
			
        }



    } elseif ($argv[1]=="recreate") {
        echo "Deleting memory_summary".PHP_EOL;
        
        $db = new sql();
        $results = $db->query("delete from memory_summary");
        


        $maxRow=PackIntoSummary();
        
        echo "memory_summary created".PHP_EOL;
        


    } elseif ($argv[1]=="count") {
        $db=new sql();
        echo countMemories().PHP_EOL;

    } else {
        echo "Command not found: {$argv[1]}".PHP_EOL;
        echo "Use ".basename(__FILE__)." without args to see help".PHP_EOL;

    }

}
