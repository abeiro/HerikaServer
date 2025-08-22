<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel_72dc4b1c501563d149fec99eb45b45f1.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");

if (!isset($GLOBALS["DBDRIVER"])) {
    $GLOBALS["DBDRIVER"]="postgresql";
}

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."minimet5_service.php");


if (!isset($argv[1])) {
    die(
        "Use ".basename(__FILE__)." command parm

commands: 
	
	query		Query for a memory. Example: query 'What do you know about Saadia?'
    query_oghma	Query for a oghma entry.
	count		Count Memories, memories summarized and memories vectorized.
	sync 		Sync Summaries <> Vector embeddings. Needs TEXT2VEC active
    sync_oghma	Sync oghma <> Vector embeddings. Needs TEXT2VEC active
	get 		Get memory. Example: get 56
	recreate	Recreate memory_summary table, 
	compact	    Recreate memory_summary table, and uses AI (LLM) to summarize data. Use 'compact noembed' to avoid TEXT2VEC sync.
	
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
   
        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Using pgvector search (text2vec)".PHP_EOL;
            $res=DataSearchMemoryByVector($argv[2],$argv[3]);
        }
        else {
            echo "Using fts search";
            $res=DataSearchMemory($argv[2],'');
        }

        print_r($res);
        
        

    }  elseif ($argv[1]=="query_oghma") {
        echo "Query memory for '{$argv[2]}'".PHP_EOL;

        $db=new sql();
   
        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Using pgvector search".PHP_EOL;
            
            $currentOghmaTopic_req = $db->fetchOne("SELECT value FROM conf_opts WHERE id='current_oghma_topic'");
            $currentOghmaTopic     = getArrayKey($currentOghmaTopic_req, "value");
        
            // Get location and context keywords
            $locationCtx      = DataLastKnownLocationHuman(false);
            $contextKeywords  = implode(" ", lastKeyWordsContext(5, $GLOBALS["HERIKA_NAME"]));
            error_log("DataSearchOghmaByVector Expanded keywords: <$currentOghmaTopic> <$locationCtx> <$contextKeywords>");

            $res=DataSearchOghmaByVector($argv[2],$currentOghmaTopic,$locationCtx,$contextKeywords);

        } else {
            die("FTS oghma search still not supported in this script");
        }
        

        print_r($res[0]);
        
        

    } elseif ($argv[1]=="sync") {
        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Starting memory vector synchronization...".PHP_EOL;
            $db = new sql();

            // Count items to sync first
            $count_query_sync = "select COUNT(*) as count from memory_summary where summary is not null and (embedding is null or native_vec is null)";
            $count_result_res_sync = $db->query($count_query_sync);
            $count_result_sync_arr = $db->fetchArray($count_result_res_sync);
            $memories_to_sync_count = $count_result_sync_arr ? (int)$count_result_sync_arr['count'] : 0;

            if ($memories_to_sync_count == 0) {
                echo "No memories found requiring vector synchronization.".PHP_EOL;
            } else {
                echo "Found {$memories_to_sync_count} memories to sync. Starting process...".PHP_EOL;
                // Fetch all results for processing, as original script did.
                $results = $db->fetchAll("select summary as content,uid,classifier,rowid,companions from memory_summary where summary is not null and (embedding is null or native_vec is null)");
                $processed_counter = 0;
                foreach ($results as $row) {
                    $TEST_TEXT=$row["content"];
                    storeMemory($TEST_TEXT, $TEST_TEXT, $row["rowid"], $row["classifier"],$row["companions"]); // JUST UPDATE embedding in memory_summary
                    $db->execQuery("update memory_summary SET native_vec = setweight(to_tsvector(coalesce(tags, '')),'A')||setweight(to_tsvector(coalesce(summary, '')),'B') where rowid={$row["rowid"]}");
                    $processed_counter++;
                    echo "Updated vector for memory ID {$row["rowid"]}. (Processed {$processed_counter} of {$memories_to_sync_count})".PHP_EOL;
                }
                if ($processed_counter > 0) {
                    echo "Successfully synchronized {$processed_counter} memories.".PHP_EOL;
                }
            }
        } else {
            echo "TEXT2VEC feature is not enabled. Skipping memory synchronization.".PHP_EOL;
        }
        echo "Memory synchronization process finished.".PHP_EOL;
    } elseif ($argv[1]=="sync_oghma") {
        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Creating vectors for memories".PHP_EOL;
            ;
            $db = new sql();
            $results = $db->fetchAll("select topic,topic_desc from oghma where vector384 is null");
            $counter=0;
            foreach ($results as $row) {
                
                $TEST_TEXT=$row["topic_desc"];
                storeMemoryOghma($TEST_TEXT, $TEST_TEXT, $row["topic"]); // JUST UPDATE embedding in memory_summary

                $counter++;
                
                echo "Updated vector for  {$row["topic"]} $counter\n";
            }
        }
        


    } elseif ($argv[1]=="compact") {

        echo "Starting memory compaction process...".PHP_EOL;
        $db = new sql();

        echo "Packing existing data into summary...".PHP_EOL;
        $maxRow=PackIntoSummary();
        echo "Packing complete. Max gamets_truncated row ID from existing summaries: {$maxRow}".PHP_EOL;

        echo "Checking for new entries to summarize and compact...".PHP_EOL;
        $count_query = "select COUNT(*) as count from memory_summary where (gamets_truncated>{$maxRow} or summary is null)";
        $count_result_res_compact = $db->query($count_query);
        $count_result_arr = $db->fetchArray($count_result_res_compact);
        $entries_to_process_count = $count_result_arr ? (int)$count_result_arr['count'] : 0;
        
        $processed_in_loop_counter=0;

        if ($entries_to_process_count == 0) {
            echo "No new entries found to summarize and compact.".PHP_EOL;
        } else {
            echo "Found {$entries_to_process_count} entries to process. Starting summarization...".PHP_EOL;
            $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];
            require_once($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");
            
            Logger::info("Using connector {$GLOBALS["CURRENT_CONNECTOR"]}");
            
            $results_query = "select gamets_truncated,packed_message,uid,classifier,rowid,companions from memory_summary where (gamets_truncated>{$maxRow} or summary is null) order by rowid asc ";
            $results = $db->query($results_query);
            
            $toUpdate=[];
            
            while ($row = $db->fetchArray($results)) {

                if (isset($argv[3])) { // User-defined limit for number of entries to process
                    if ( $processed_in_loop_counter >= ($argv[3]+0)) {
                        echo "Reached processing limit of {$argv[3]} entries.".PHP_EOL;
                        break; 
                    }
                }

                // Summarization logic begins
                if ($row["classifier"]=="diary") {
                    $TEST_TEXT=$row["packed_message"];
                } else {
                    $GLOBALS["COMMAND_PROMPT"]="";
                    $gameRequest=["summary"];
                    $CLFORMAT="#Summary: {summary of events and dialogues}\\r\\n#Tags: {list of relevant twitter-like hashtags, include location names, enemies names, other NPC names}";
                    IF (isset($GLOBALS["CORE_LANG"])) {
                        if ($GLOBALS["CORE_LANG"]=="es") {
                            $CLFORMAT.=" GENERA EL CONTENIDO Y LOS TAGS EN ESPAÑOL";
                        }
                    }
                    // Database Prompt (Memory Compaction)
                    $prompt=[];
                    $prompt[] = array('role' => 'system', 
                                      'content' => "This is a playthrough in Skyrim. 
{$GLOBALS["PLAYER_NAME"]} is the player.
{$row["companions"]} are nearby NPCs.
You must write a memory summary by analyzing the chat history.
Pay attention to details that can change character's behavior, feelings, and also tag names and locations.
Here are additional instructions: {$GLOBALS["SUMMARY_PROMPT"]}
");
                    $prompt[] = array('role' => 'user', 'content' =>"#CHAT HISTORY#\\n{$row["packed_message"]}\\n#END OF CHAT HISTORY#\\n");
                    $prompt[] = array('role' => 'user', 
                                      'content' => "Read #CHAT HISTORY# and write a memory record using about events and conversations. Use this format:\\n$CLFORMAT");
                    
                    $GLOBALS["FORCE_MAX_TOKENS"]=$GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["MAX_TOKENS_MEMORY"];
                    $connectionHandler = new $GLOBALS["CURRENT_CONNECTOR"];
                    $connectionHandler->open($prompt, []);
                    $buffer="";
                    $totalBuffer=""; // totalBuffer was not used later, buffer holds the final result
                    $breakFlag=false;
                    while (true) {
                        if ($breakFlag) break;
                        $current_chunk = $connectionHandler->process(); // Renamed to avoid conflict
                        $buffer.=$current_chunk; 
                        // $totalBuffer.=$buffer; // This line seems to accumulate exponentially, $totalBuffer wasn't used.
                        if ($connectionHandler->isDone()) $breakFlag=true;
                    }
                    $connectionHandler->close();
                    $TEST_TEXT=strtr($buffer,["**"=>""]); // Use the final buffer

                    // if the llm repeats tags we tidy them up
                    $pattern = '/#Tags:/';
                    $split = preg_split($pattern, $TEST_TEXT, 2);
                    if (isset($split[1])) {
                        // make data consistent (copied from tagsCol creation below)
                        $tagsString = strtr($split[1],["*"=>""]);
                        $tagsArray = array_map('trim', explode(',', $tagsString));
                        $tagsCol=implode(" ",$tagsArray);
                        $tagsArray = array_map('trim', explode(' ', $tagsCol));

                        // Remove duplicates and last tag if duplicates found
                        $uniqueTagsArray = array_unique($tagsArray);
                        if (count($uniqueTagsArray) < count($tagsArray)) {
                            // Duplicates found - remove last tag as it may be truncated
                            array_pop($uniqueTagsArray);
                            Logger::debug("Corrected duplicate tags:\nOriginal tags: [" . implode(' ', $tagsArray) . "]\nUnique tags: [" . implode(' ', $uniqueTagsArray) . "]");
                            $tagsCol = implode(" ", array_values($uniqueTagsArray));
                            // Reconstruct TEST_TEXT with corrected tags
                            $TEST_TEXT = trim($split[0]) . "\n#Tags: " . $tagsCol;
                        }
                    }

                    $toUpdate[]=["rowid"=>$row["rowid"],"summary"=>$TEST_TEXT];
                }
                // Summarization logic ends, $TEST_TEXT contains the summary or packed_message

                $processed_in_loop_counter++; // Increment after deciding to process/summarize this item
                echo "Summarized entry for ID {$row["rowid"]}. (Processed attempt {$processed_in_loop_counter} of {$entries_to_process_count})".PHP_EOL;
                
                Logger::debug("$TEST_TEXT");
                
                // Original script's embedding logic: if (($argv[2]!="noembed")&& false)
                // This condition `&& false` means embedding inside compact was effectively disabled.
                // The "Run a sync later" message aligns with this.
                // So, no embedding happens here. We'll add a message about 'noembed' argument after the loop.

                $pattern = '/#Tags:(.+)/';
                preg_match($pattern, $TEST_TEXT, $matches);
                $tagsCol=''; // Initialize tagsCol
                if (isset($matches[1])) {
                    $tagsString = strtr($matches[1],["*"=>""]);
                    $tagsArray = array_map('trim', explode(',', $tagsString));
                    $tagsCol=implode(" ",$tagsArray);
                } else {
                    Logger::info("No tags found for entry ID {$row["rowid"]}.");
                    // The original script had 'continue' here. If we continue, the update for this summary won't happen.
                    // Depending on desired behavior, this might need adjustment.
                    // For now, keeping it to update summary even if no tags.
                }

                // Update database for the current item (original script did this for $toUpdate, which would be just one item here)
                // The original script iterates $toUpdate but $toUpdate is reset at end of loop, effectively processing one by one from $toUpdate array.
                // Let's simplify to process current $row directly since $toUpdate[] was used to store the current item's summary.
                
                $current_summary_to_save = $TEST_TEXT; // This is the actual summary content
                if ($row["classifier"] != "diary") { // For non-diary, $TEST_TEXT is from LLM
                   // Find the current summary from $toUpdate if it exists
                   foreach($toUpdate as $upd_item) {
                       if ($upd_item["rowid"] == $row["rowid"]) {
                           $current_summary_to_save = $upd_item["summary"];
                           break;
                       }
                   }
                }


                $db->execQuery("update memory_summary set summary='".SQLite3::escapeString($current_summary_to_save)."',tags='".SQLite3::escapeString($tagsCol)."' where rowid={$row["rowid"]}");
                $db->execQuery("update memory_summary SET native_vec = setweight(to_tsvector(coalesce(tags, '')),'A')||setweight(to_tsvector(coalesce(summary, '')),'B') where rowid={$row["rowid"]}");
                
                // Original embedding call within loop (conditionally for $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"])
                // This was outside the 'noembed' check but was also within the $toUpdate loop which processed one item.
                // Given the outer "Run a sync later" and the `&& false` on explicit embedding, this part might be redundant or only for `native_vec`.
                // The `native_vec` is already updated above. Explicit `storeMemory` for full embedding is best left to the `sync` command.

                $toUpdate=[]; // Reset for next iteration as in original script
                sleep(1); // Kept from original
            } // End while loop

            if ($processed_in_loop_counter > 0) {
                 echo "Attempted summarization for {$processed_in_loop_counter} entries (out of {$entries_to_process_count} found needing update).".PHP_EOL;
            } elseif ($entries_to_process_count > 0) { 
                echo "Found {$entries_to_process_count} entries, but 0 were processed in this run (check logs for details or processing limit if set via third argument).".PHP_EOL;
            }
        }

        if (isset($argv[2]) && $argv[2] == "noembed") {
            //echo "Embedding step was skipped as per 'noembed' argument. Run sync later if embeddings are required.".PHP_EOL;
        }
        
        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Starting memory vector synchronization...".PHP_EOL;
            

            // Count items to sync first
            $count_query_sync = "select COUNT(*) as count from memory_summary where summary is not null and (embedding is null or native_vec is null)";
            $count_result_res_sync = $db->query($count_query_sync);
            $count_result_sync_arr = $db->fetchArray($count_result_res_sync);
            $memories_to_sync_count = $count_result_sync_arr ? (int)$count_result_sync_arr['count'] : 0;

            if ($memories_to_sync_count == 0) {
                echo "No memories found requiring vector synchronization.".PHP_EOL;
            } else {
                echo "Found {$memories_to_sync_count} memories to sync. Starting process...".PHP_EOL;
                // Fetch all results for processing, as original script did.
                $results = $db->fetchAll("select summary as content,uid,classifier,rowid,companions from memory_summary where summary is not null and (embedding is null or native_vec is null)");
                $processed_counter = 0;
                foreach ($results as $row) {
                    $TEST_TEXT=$row["content"];
                    storeMemory($TEST_TEXT, $TEST_TEXT, $row["rowid"], $row["classifier"],$row["companions"]); // JUST UPDATE embedding in memory_summary
                    $db->execQuery("update memory_summary SET native_vec = setweight(to_tsvector(coalesce(tags, '')),'A')||setweight(to_tsvector(coalesce(summary, '')),'B') where rowid={$row["rowid"]}");
                    $processed_counter++;
                    echo "Updated vector for memory ID {$row["rowid"]}. (Processed {$processed_counter} of {$memories_to_sync_count})".PHP_EOL;
                }
                if ($processed_counter > 0) {
                    echo "Successfully synchronized {$processed_counter} memories.".PHP_EOL;
                }
            }
        } else {
            echo "TEXT2VEC feature is not enabled. Skipping memory synchronization.".PHP_EOL;
        }
        echo "Memory compaction process finished.".PHP_EOL;

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
