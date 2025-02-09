<?php

$GLOBALS["OGHMA_HINT"] = "";

if ($GLOBALS["MINIME_T5"]) {
    if (isset($GLOBALS["OGHMA_INFINIUM"]) && ($GLOBALS["OGHMA_INFINIUM"])) {
        if (in_array($gameRequest[0], ["inputtext","inputtext_s","ginputtext","ginputtext_s"])) {

            $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..)
            $replacement = "";
            $INPUT_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]);

            $pattern = '/\(talking to [^()]+\)/i';
            $INPUT_TEXT = preg_replace($pattern, '', $INPUT_TEXT);
            $INPUT_TEXT = strtr($INPUT_TEXT, ["."=>" ", "{$GLOBALS["PLAYER_NAME"]}:"=>""]);

            // $INPUT_TEXT=lastSpeech($GLOBALS["HERIKA_NAME"]);

            $currentOghmaTopic_req = $db->fetchOne("SELECT value FROM conf_opts WHERE id='current_oghma_topic'");
            $currentOghmaTopic     = getArrayKey($currentOghmaTopic_req, "value");

            $topic_req = file_get_contents("http://127.0.0.1:8082/topic?text=".urlencode($INPUT_TEXT));
            if ($topic_req) {
                $topic_res         = json_decode($topic_req, true);
                $currentInputTopic = getArrayKey($topic_res, "generated_tags");
            } else {
                $currentInputTopic = "";
            }

            $locationCtx      = DataLastKnownLocationHuman(true);
            $contextKeywords  = implode(" ", lastKeyWordsContext(5, $GLOBALS["HERIKA_NAME"]));

            // Helper function to convert a string to tsquery format
            function prepareTsQuery($string, $operator = '|') {
                // Remove all non-alphanumeric chars except spaces
                $cleanedString = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
                // Split words by whitespace
                $words = preg_split('/\s+/', $cleanedString);
                // Remove empty elements
                $words = array_filter($words);
                // Join words with the specified operator
                return implode(" $operator ", $words);
            }

            // Prepare tsquery strings
            $currentInputTopicQuery = prepareTsQuery($currentInputTopic);
            $currentOghmaTopicQuery = prepareTsQuery($currentOghmaTopic);
            $locationCtxQuery       = prepareTsQuery($locationCtx);
            $contextKeywordsQuery   = prepareTsQuery($contextKeywords);

            // --------------------------------------------------
            // Build the user’s knowledge array
            // --------------------------------------------------
            // 1. Fetch the global string
            $oghmaKnowledgeString = isset($GLOBALS["OGHMA_KNOWLEDGE"])
                ? $GLOBALS["OGHMA_KNOWLEDGE"]
                : '';

            // 2. Convert the comma-separated string into an array and trim each element
            $oghmaKnowledgeArray = array_map('trim', explode(',', $oghmaKnowledgeString));

            // 3. Remove any empty elements
            $oghmaKnowledgeArray = array_filter($oghmaKnowledgeArray);

            // 4. Append HERIKA_NAME to the end of that array
            $oghmaKnowledgeArray[] = $GLOBALS["HERIKA_NAME"];

            // --------------------------------------------------
            // Query to find the top matching Oghma entry
            // --------------------------------------------------
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
                ORDER BY combined_rank DESC;
            ";

            $oghmaTopics = $GLOBALS["db"]->fetchAll($query);

            if (!empty($oghmaTopics)) {
                // We'll demonstrate logic using the top-ranked item
                $topTopic = $oghmaTopics[0];
                $msg = 'oghma keyword offered';

                // If rank is good enough, we try to see if user can access advanced or basic lore
                if ($topTopic["combined_rank"] > 3.5) {
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
                        $GLOBALS["OGHMA_HINT"] .= "#Lore (You have advanced knowledge on this subject): {$topTopic["topic_desc"]}";
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
                            $GLOBALS["OGHMA_HINT"] .= "#Lore (You only have basic knowledge on this subject): {$topTopic["topic_desc_basic"]}";
                        } else {
                            $GLOBALS["OGHMA_HINT"] .= "You do not know ANYTHING about {$topTopic["topic"]}";
                        }
                    }
                } else {
                    // Not a good match
                    $msg = "oghma keyword NOT offered (no good results in search)";
                }

                // Logging to audit_memory
                $GLOBALS["db"]->insert(
                    'audit_memory',
                    [
                        'input'    => $INPUT_TEXT,
                        'keywords' => $msg,
                        'rank_any' => $topTopic["combined_rank"],
                        'rank_all' => $topTopic["combined_rank"],
                        'memory'   => "$currentInputTopic / $currentOghmaTopic / $locationCtxQuery / $contextKeywordsQuery => {$topTopic["topic"]}",
                        'time'     => $topic_res["elapsed_time"]
                    ]
                );
            } else {
                // No results
                $msg = 'oghma keyword not offered, no results';
                $GLOBALS["db"]->insert(
                    'audit_memory',
                    [
                        'input'    => $INPUT_TEXT,
                        'keywords' => $msg,
                        'rank_any' => -1,
                        'rank_all' => -1,
                        'memory'   => "$currentInputTopic / $currentOghmaTopic / $locationCtxQuery / $contextKeywordsQuery => ",
                        'time'     => $topic_res["elapsed_time"]
                    ]
                );
            }
        }
    }
}
?>
