<?php
/*

Post tasks.

*/


if ($FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]) {
    $results = $db->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary");

    $maxRow=$results[0]["gamets_truncated"]+0;
    
    $pfi=($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]+0)*100000;
    

    if (($gameRequest[2]-$maxRow)<$pfi) {

    } else {
        
        $maxRow=PackIntoSummary();
         
        $results = $db->fetchAll("select packed_message,uid,classifier from memory_summary where gamets_truncated>$maxRow or summary=''");
        $counter=0;
        $toUpdate=[];

        $db->close();   // This operation will be time consuming, so we must close db to keep it unlocked.
        
        unset($db);     // Destroy object
        
        foreach ($results as $row) { 


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
                $prompt[] = array('role' => 'user', 
								  'content' => "write into {$GLOBALS["HERIKA_NAME"]}'s diary a summary of this: \n[... {$row["packed_message"]} ...]. \nUse this format:\n $CLFORMAT");

				
                $GLOBALS["FORCE_MAX_TOKENS"]=256;

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

            storeMemory($embeddings, $TEST_TEXT, $row["uid"], $row["classifier"]);


            $counter++;
            break;	// Only one at a time

        }

        $db = new sql();    // Reopen db.
        
        foreach ($toUpdate as $uq) {
            //echo "update memory_summary set summary='".SQLite3::escapeString($uq["summary"])."' where uid={$uq["uid"]}";
            $db->execQuery("update memory_summary set summary='".SQLite3::escapeString($uq["summary"])."' where uid={$uq["uid"]}");

        }

    }

}
