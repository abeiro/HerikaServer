<?php 

/*

We use 4 sources for ranking:

* Subject from the user's current input – This is given the highest weight: 10.
* Topic inferred from recent context – This topic is derived after the LLM processes the input. It attempts to extract the main topic from the last 5 dialogue sentences spoken by the player and NPC. Weight: 5.
* Location context – Information about the current location or setting. Weight: 2.
* Names extracted from recent dialogues – Relevant names mentioned in the recent conversation. Weight: 1.
    
For example:
    
If you mention "Akatosh" in your input, it will likely match the Akatosh entry in Oghma (source 1). If the next sentence is "Is he good or bad?", source 1 loses value because it lacks new information for the search. However, Akatosh remains in source 2 (inferred topic) and probably source 4 (recently mentioned names). As a result, the search will still return Akatosh, but with a lower ranking.
    
This system allows the main topic to persist in subsequent requests, even if it isn’t explicitly mentioned.
    
If the user changes the subject (e.g., "And what do you know about Mara?"), Mara becomes the primary focus in source 1 with the highest weight. The search then becomes a mix of Mara and Akatosh, but Mara will take precedence due to its higher weight

*/

$GLOBALS["OGHMA_HINT"]="";

if ($GLOBALS["MINIME_T5"]) {
    if (isset($FEATURES["MISC"]["OGHMA_INFINIUM"])&&($FEATURES["MISC"]["OGHMA_INFINIUM"])) {
        if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"])) {

            $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
            $replacement = "";
            $INPUT_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]); 
            
            $pattern = '/\(talking to [^()]+\)/i';
            $INPUT_TEXT = preg_replace($pattern, '', $INPUT_TEXT);
            $INPUT_TEXT=strtr($INPUT_TEXT,["."=>" ","{$GLOBALS["PLAYER_NAME"]}:"=>""]);
            
            //$INPUT_TEXT=lastSpeech($GLOBALS["HERIKA_NAME"]);
            
            $currentOghmaTopic_req=$db->fetchOne("select value from conf_opts where id='current_oghma_topic'");
            $currentOghmaTopic=getArrayKey($currentOghmaTopic_req,"value");
           
            $topic_req=file_get_contents("http://127.0.0.1:8082/topic?text=".urlencode($INPUT_TEXT));
            
            if ($topic_req) {
                $topic_res=json_decode($topic_req,true);
                $currentInputTopic=getArrayKey($topic_res,"generated_tags");
            } else {
                $currentInputTopic="";
            }

            $locationCtx=DataLastKnownLocationHuman(true);
               
            $contextKeywords=implode(" ",lastKeyWordsContext(5,$GLOBALS["HERIKA_NAME"]));

                    
            // Helper function to convert a string to tsquery format
            function prepareTsQuery($string, $operator = '|') {
                // Remove all non-alphanumeric characters except spaces
                $cleanedString = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
                // Split words by whitespace
                $words = preg_split('/\s+/', $cleanedString);
                // Remove empty elements (in case of multiple spaces)
                $words = array_filter($words);
                // Join words with the specified operator
                return implode(" $operator ", $words);
            }
            
            // Prepare tsquery strings
            $currentInputTopicQuery = prepareTsQuery($currentInputTopic);
            $currentOghmaTopicQuery = prepareTsQuery($currentOghmaTopic);
            $locationCtxQuery = prepareTsQuery($locationCtx);
            $contextKeywordsQuery = prepareTsQuery($contextKeywords);
            
            // Build the query
            $query = "
                SELECT 
                    topic_desc,
                    topic,
                    ts_rank(native_vector, to_tsquery('$currentInputTopicQuery')) * 
                        CASE WHEN native_vector @@ to_tsquery('$currentInputTopicQuery') THEN 10.0 ELSE 1.0 END +
                    ts_rank(native_vector, to_tsquery('$currentOghmaTopicQuery')) * 
                        CASE WHEN native_vector @@ to_tsquery('$currentOghmaTopicQuery') THEN 5.0 ELSE 1.0 END +
                    ts_rank(native_vector, to_tsquery('$locationCtxQuery')) * 
                        CASE WHEN native_vector @@ to_tsquery('$locationCtxQuery') THEN 2.0 ELSE 1.0 END +
                    ts_rank(native_vector, to_tsquery('$contextKeywordsQuery')) * 
                        CASE WHEN native_vector @@ to_tsquery('$contextKeywordsQuery') THEN 1.0 ELSE 0.0 END 
                    AS combined_rank
                FROM oghma
                WHERE 
                    native_vector @@ to_tsquery('$currentInputTopicQuery') OR
                    native_vector @@ to_tsquery('$currentOghmaTopicQuery') OR
                    native_vector @@ to_tsquery('$locationCtxQuery') OR
                    native_vector @@ to_tsquery('$contextKeywordsQuery')
                ORDER BY combined_rank DESC;
            ";
            
            // error_log($query);


            $oghmaTopics=$GLOBALS["db"]->fetchAll($query);
            $msg='oghma keyword offered';

            if (isset($oghmaTopics[0]) && isset($oghmaTopics[0]["topic_desc"])) {

                if ($oghmaTopics[0]["combined_rank"] > 3.5 ) {
                    $GLOBALS["OGHMA_HINT"].="#Lore related info: {$oghmaTopics[0]["topic_desc"]}";

                    // Search with location matched all. Use it.
                } else {
                    // Dont offer
                    
                    $msg="oghma keyword NOT offered (not good results in  search)";
                }

                $GLOBALS["db"]->insert(
                    'audit_memory',
                    array(
                        'input' => $INPUT_TEXT,
                        'keywords' =>$msg,
                        'rank_any'=> $oghmaTopics[0]["combined_rank"],
                        'rank_all'=>$oghmaTopics[0]["combined_rank"],
                        'memory'=>"$currentInputTopic / $currentOghmaTopic / $locationCtxQuery / $contextKeywordsQuery => {$oghmaTopics[0]["topic"]}",
                        'time'=>$topic_res["elapsed_time"]
                    )
                );
                
                
            } else {
                $msg='oghma keyword not offered, no results';
                $GLOBALS["db"]->insert(
                    'audit_memory',
                    array(
                        'input' => $INPUT_TEXT,
                        'keywords' =>$msg,
                        'rank_any'=> -1,
                        'rank_all'=>-1,
                        'memory'=>"$currentInputTopic / $currentOghmaTopic / $locationCtxQuery / $contextKeywordsQuery => ",
                        'time'=>$topic_res["elapsed_time"]
                    )
                );
            }
                

            
        }
    }
}
?>