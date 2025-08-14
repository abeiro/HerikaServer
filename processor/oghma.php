<?php

$GLOBALS["OGHMA_HINT"] = "";

if ($GLOBALS["MINIME_T5"]) {
    if (isset($GLOBALS["OGHMA_INFINIUM"]) && ($GLOBALS["OGHMA_INFINIUM"])) {
        if (in_array($gameRequest[0], ["inputtext","inputtext_s","ginputtext","ginputtext_s","rechat", "instruction", "suggestion"])) {
            
            if ($gameRequest[0] === "rechat") {
                $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..)
                $replacement = "";
                // Get last chat event for rechat context
                $lastChat = $db->fetchOne("SELECT data FROM eventlog WHERE type IN ('chat') ORDER BY gamets DESC LIMIT 1");
                $INPUT_TEXT = $lastChat ? preg_replace($pattern, $replacement, $lastChat["data"]) : "";
                // Remove NPC name prefix pattern (e.g., "Irileth: ")
                $INPUT_TEXT = preg_replace('/^[^:]+:\s*/', '', $INPUT_TEXT);
                // Remove talking to pattern
                $pattern = '/\(talking to [^()]+\)/i';
                $INPUT_TEXT = preg_replace($pattern, '', $INPUT_TEXT);
                
            } else {
                $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..)
                $replacement = "";
                $INPUT_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]);
                $pattern = '/\(talking to [^()]+\)/i';
                $INPUT_TEXT = preg_replace($pattern, '', $INPUT_TEXT);
                $INPUT_TEXT = strtr($INPUT_TEXT, ["."=>" ", "{$GLOBALS["PLAYER_NAME"]}:"=>""]);
            }


            $currentOghmaTopic_req = $db->fetchOne("SELECT value FROM conf_opts WHERE id='current_oghma_topic'");
            $currentOghmaTopic     = getArrayKey($currentOghmaTopic_req, "value");

            // Get location and context keywords
            $locationCtx      = DataLastKnownLocationHuman(false);
            $contextKeywords  = implode(" ", lastKeyWordsContext(5, $GLOBALS["HERIKA_NAME"]));

            // Build the user's knowledge array
            $oghmaKnowledgeString = isset($GLOBALS["OGHMA_KNOWLEDGE"])
                ? $GLOBALS["OGHMA_KNOWLEDGE"]
                : '';

            $oghmaKnowledgeArray = array_map('trim', explode(',', $oghmaKnowledgeString));
            $oghmaKnowledgeArray = array_filter($oghmaKnowledgeArray);
            $oghmaKnowledgeArray[] = $GLOBALS["HERIKA_NAME"];

            // Helper function to convert a string to tsquery format
            $prepareTsQuery = function ($string, $operator = '|') {
                // 1) Convert underscores to spaces and remove apostrophes
                $string = str_replace('_', ' ', $string);
                $string = preg_replace('/[\'\']/u', '', $string);
            
                // 2) Remove all other non-alphanumeric or space characters
                $cleanedString = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
                $cleanedString = strtolower($cleanedString);
            
                // 3) Split into tokens
                $words = preg_split('/\s+/', $cleanedString);
                $words = array_filter($words); // remove empty
            
                // 4) Append :* for prefix-based partial matches
                $words = array_map(fn($w) => $w . ':*', $words);
            
                // 5) Join with | (OR) or & (AND) as needed
                return implode(" $operator ", $words);
            };

            // Helper function to normalize text for comparison
            $normalizeText = function ($text) {
                // Convert to lowercase
                $text = strtolower($text);
                // Replace underscores with spaces
                $text = str_replace('_', ' ', $text);
                // Remove all non-alphanumeric characters
                $text = preg_replace('/[^a-z0-9\s]/', '', $text);
                // Remove extra spaces
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
            };

            // Store all found topics
            $foundTopics = [];
            $remainingText = $INPUT_TEXT;
            $oghmaAmount = isset($GLOBALS["OGHMA_AMOUNT"]) ? intval($GLOBALS["OGHMA_AMOUNT"]) : 1;
            $firstTopic = null; // Track the first topic we find
            $processedTopics = []; // Track normalized topics we've already processed

            // Extract topics up to OGHMA_AMOUNT times
            for ($i = 0; $i < $oghmaAmount; $i++) {
                $topic_req = minimeTopic($remainingText);
                if ($topic_req) {
                    $topic_res = json_decode($topic_req, true);
                    $currentInputTopic = getArrayKey($topic_res, "generated_tags");
                    
                    if (!empty($currentInputTopic)) {
                        // Normalize the current topic for comparison
                        $normalizedCurrentTopic = $normalizeText($currentInputTopic);
                        
                        // Check if we've already processed this topic
                        if (in_array($normalizedCurrentTopic, $processedTopics)) {
                            Logger::info("[OGHMA] Skipping duplicate topic: $currentInputTopic");
                            break;
                        }
                        
                        // Add to processed topics
                        $processedTopics[] = $normalizedCurrentTopic;
                        
                        $foundTopics[] = $currentInputTopic;
                        
                        // Store the first topic we find
                        if ($firstTopic === null) {
                            $firstTopic = $currentInputTopic;
                        }
                        
                        // Process this topic immediately
                        $currentInputTopicQuery = $prepareTsQuery($currentInputTopic);
                        $currentOghmaTopicQuery = $prepareTsQuery($currentOghmaTopic);
                        $locationCtxQuery       = $prepareTsQuery($locationCtx);
                        $contextKeywordsQuery   = $prepareTsQuery($contextKeywords);

                        // Query to find the top matching Oghma entry for this topic
                        $query = "
                            SELECT 
                                topic_desc,
                                topic,
                                knowledge_class,
                                knowledge_class_basic,
                                topic_desc_basic,
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
                            ORDER BY combined_rank DESC
                            LIMIT 1;
                        ";

                        //error_log(print_r($query,true));
                        if (false && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) 
                            $oghmaTopics=DataSearchOghmaByVector($currentInputTopic,$currentOghmaTopic,$locationCtx,$contextKeywords);
                        else
                            $oghmaTopics = $GLOBALS["db"]->fetchAll($query);

                        if (!empty($oghmaTopics)) {
                            $topTopic = $oghmaTopics[0];
                            $msg = 'oghma keyword offered';

                            // If rank is good enough, we try to see if user can access advanced or basic lore
                            if ($topTopic["combined_rank"] > 3.3) {
                                // -----------------------------
                                // 1) Check advanced article
                                // -----------------------------
                                $advancedAllowed = false;
                                $advClassesStr   = trim($topTopic["knowledge_class"] ?? '');
                                if ($advClassesStr === '') {
                                    // Empty => no restriction
                                    $advancedAllowed = true;
                                } else {
                                    // Convert advanced classes to array
                                    $advClassesArr   = array_map('trim', explode(',', $advClassesStr));
                                    $advClassesArr   = array_filter($advClassesArr);

                                    // Intersect with user's known classes
                                    $hasAdvancedKnowledge = array_intersect($advClassesArr, $oghmaKnowledgeArray);
                                    if (!empty($hasAdvancedKnowledge)) {
                                        $advancedAllowed = true;
                                    }
                                }

                                // -----------------------------------------------
                                // ADD knowall OVERRIDE HERE
                                // -----------------------------------------------
                                // If 'knowall' is in the user's knowledge array, 
                                // automatically allow advanced article.
                                if (in_array('knowall', array_map('strtolower', $oghmaKnowledgeArray))) {
                                    $advancedAllowed = true;
                                }

                                if ($advancedAllowed) {
                                    // The user can access advanced lore
                                    $GLOBALS["OGHMA_HINT"] .= " \n# Lore Information (You have advanced knowledge on this subject, you can use it in your dialogue):{$topTopic["topic"]}  \"{$topTopic["topic_desc"]}\"";
                                } else {
                                    // -----------------------------
                                    // 2) Check basic article
                                    // -----------------------------
                                    $basicAllowed = false;
                                    $basicClassesStr = trim($topTopic["knowledge_class_basic"] ?? '');
                                    if ($basicClassesStr === '') {
                                        // Empty => no restriction
                                        $basicAllowed = true;
                                    } else {
                                        // Convert basic classes to array
                                        $basicClassesArr = array_map('trim', explode(',', $basicClassesStr));
                                        $basicClassesArr = array_filter($basicClassesArr);

                                        // Intersect with user's known classes
                                        $hasBasicKnowledge = array_intersect($basicClassesArr, $oghmaKnowledgeArray);
                                        if (!empty($hasBasicKnowledge)) {
                                            $basicAllowed = true;
                                        }
                                    }

                                    if ($basicAllowed) {
                                        $GLOBALS["OGHMA_HINT"] .= " \n# Lore Information (You only have basic knowledge on this subject, you can use it in your dialogue):{$topTopic["topic"]}  \"{$topTopic["topic_desc_basic"]}\"";
                                    } else {
                                        $GLOBALS["OGHMA_HINT"] .= " \nYou do not know ANYTHING about {$topTopic["topic"]}";
                                    }
                                }
                            } else {
                                $msg = "oghma keyword NOT offered (no good results in search)";
                            }

                            // Log to audit_memory immediately after processing this topic
                            $GLOBALS["db"]->insert(
                                'audit_memory',
                                array(
                                    'input'    => $remainingText,
                                    'keywords' => $msg,
                                    'rank_any' => $topTopic["combined_rank"],
                                    'rank_all' => $topTopic["combined_rank"],
                                    'memory'   => "$currentInputTopic / $currentOghmaTopic / $locationCtxQuery / $contextKeywordsQuery => {$topTopic["topic"]}",
                                    'time'     => $topic_res["elapsed_time"]
                                )
                            );
                        }

                        // Remove the found topic from remaining text using normalized comparison
                        $normalizedTopic = $normalizeText($currentInputTopic);
                        $normalizedRemaining = $normalizeText($remainingText);
                        
                        // Try to find the topic in the remaining text using fuzzy matching
                        $pattern = '/\b' . preg_quote($normalizedTopic, '/') . '\b/i';
                        if (preg_match($pattern, $normalizedRemaining, $matches)) {
                            // Get the actual matched text from the original remaining text
                            $startPos = stripos($remainingText, $matches[0]);
                            $endPos = $startPos + strlen($matches[0]);
                            
                            // Remove the matched text and any surrounding "and"
                            $beforeMatch = substr($remainingText, 0, $startPos);
                            $afterMatch = substr($remainingText, $endPos);
                            
                            // Clean up any "and" before or after the match
                            $beforeMatch = preg_replace('/\s+and\s*$/', '', $beforeMatch);
                            $afterMatch = preg_replace('/^\s*and\s+/', '', $afterMatch);
                            
                            $remainingText = $beforeMatch . $afterMatch;
                            
                            // Additional cleanup to remove any remaining references to the topic
                            $remainingText = preg_replace('/\b' . preg_quote($currentInputTopic, '/') . '\b/i', '', $remainingText);
                        }
                        
                        // Clean up any remaining artifacts
                        $remainingText = preg_replace('/\s+/', ' ', $remainingText);
                        $remainingText = preg_replace('/^\s*and\s+/', '', $remainingText);
                        $remainingText = preg_replace('/\s+and\s*$/', '', $remainingText);
                        $remainingText = preg_replace('/,\s*,/', ',', $remainingText); // Remove double commas
                        $remainingText = preg_replace('/^\s*,\s*/', '', $remainingText); // Remove leading comma
                        $remainingText = preg_replace('/\s*,\s*$/', '', $remainingText); // Remove trailing comma
                        $remainingText = trim($remainingText);
                        
                        Logger::info("[OGHMA] Remaining text after extraction $i: '$remainingText'");
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }

            // After the loop, update current Oghma topic in database with the first topic we found
            if ($firstTopic !== null) {
                Logger::info("[OGHMA] Setting first topic as current: $firstTopic");
                $GLOBALS["db"]->upsertRowOnConflict(
                    'conf_opts',
                    array(
                        'id' => 'current_oghma_topic',
                        'value' => $firstTopic
                    ),
                    'id'
                );
            }
        }
    }
}
?>