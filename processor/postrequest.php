<?php
/*

Post tasks.

*/


if ($FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]) {
    $results = $db->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary");

    $maxRow=$results[0]["gamets_truncated"]+0;

    if (($gameRequest[2]-$maxRow)<1000000) {

    } else {
        $results = $db->query("insert into memory_summary select * from ( 
								select max(gamets) as gamets_truncated,count(*) as n,
								GROUP_CONCAT(message,char(13) || char(10)|| char(13) || char(10)) as packed_message ,'','dialogue',max(uid) as uid
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

                $prompt=[];
                $prompt[] = array('role' => 'user',
                                  'content' => "write into {$GLOBALS["HERIKA_NAME"]}'s diary a short summary of this: [... {$row["packed_message"]} ...]. mark down characters and places.");


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
