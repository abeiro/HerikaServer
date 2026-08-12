<?php

$GLOBALS["OGHMA_HINT"] = "";
$GLOBALS["OGHMA_INJECTED_TOPICS"] = [];
$GLOBALS["OGHMA_INJECTED_PAYLOADS"] = [];

require_once(__DIR__ . "/../lib/oghma_retrieval.php");

// Helper function to properly check boolean values (handles string "false" from form submissions)
// Guard against redeclaration when oghma.php is included multiple times (e.g., during rechat)
if (!function_exists('isOghmaEnabled')) {
    function isOghmaEnabled($value) {
        if ($value === null) return false;
        if ($value === false || $value === 'false' || $value === '0' || $value === 0) return false;
        if ($value === true || $value === 'true' || $value === '1' || $value === 1) return true;
        return (bool)$value;
    }
}

$minimeEnabled = isMinimeT5Enabled();
$oghmaCustomEnabled = isOghmaEnabled($GLOBALS["OGHMA_CUSTOM"] ?? false);
$oghmaInfiniumEnabled = isOghmaEnabled($GLOBALS["OGHMA_INFINIUM"] ?? false);
$oghmaRequestEligible = in_array(
    $gameRequest[0] ?? '',
    ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "rechat", "continue", "instruction", "suggestion"],
    true
);

if ($oghmaInfiniumEnabled && $oghmaRequestEligible) {
    require_once(__DIR__ . "/../lib/oghma_forced_context.php");
    $forcedNpcMaster = isset($npcMaster) && $npcMaster instanceof NpcMaster
        ? $npcMaster
        : (class_exists('NpcMaster') ? new NpcMaster() : null);
    chimOghmaInjectForcedContext($GLOBALS['db'] ?? null, $forcedNpcMaster);
}

// Debug: Log the actual values being checked
error_log("[OGHMA DEBUG] MINIME_T5(auto)=" . ($minimeEnabled ? 'Y' : 'N')
    . " | OGHMA_CUSTOM=" . var_export($GLOBALS["OGHMA_CUSTOM"] ?? 'NOT SET', true)
    . " (enabled=" . ($oghmaCustomEnabled ? 'Y' : 'N') . ")"
    . " | OGHMA_INFINIUM=" . var_export($GLOBALS["OGHMA_INFINIUM"] ?? 'NOT SET', true)
    . " (enabled=" . ($oghmaInfiniumEnabled ? 'Y' : 'N') . ")");

if ($oghmaInfiniumEnabled && $oghmaRequestEligible) {
            
            if ($gameRequest[0] === "rechat") {
                $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..)
                $replacement = "";
                // Get last chat event for rechat context
                $lastChat = $db->fetchOne("SELECT data FROM eventlog WHERE type IN ('chat') ORDER BY gamets DESC LIMIT 1");
                $INPUT_TEXT = $lastChat ? preg_replace($pattern, $replacement, $lastChat["data"]) : "";
                // Remove NPC name prefix pattern (e.g., "Irileth: ")
                $INPUT_TEXT = preg_replace('/^[^:]+:\s*/', '', $INPUT_TEXT);
                // Remove talking to pattern
                $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
                $INPUT_TEXT = preg_replace($pattern, '', $INPUT_TEXT);
                
            } else {
                $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..)
                $replacement = "";
                $INPUT_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]);
                $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
                $INPUT_TEXT = preg_replace($pattern, '', $INPUT_TEXT);
                $INPUT_TEXT = strtr($INPUT_TEXT, ["."=>" ", "{$GLOBALS["PLAYER_NAME"]}:"=>""]);
            }


            // Build the user's knowledge array
            $oghmaKnowledgeString = isset($GLOBALS["OGHMA_KNOWLEDGE"])
                ? $GLOBALS["OGHMA_KNOWLEDGE"]
                : '';

            $oghmaKnowledgeArray = array_map('trim', explode(',', $oghmaKnowledgeString));
            $oghmaKnowledgeArray = array_filter($oghmaKnowledgeArray);
            $oghmaKnowledgeArray[] = $GLOBALS["HERIKA_NAME"];

            $remainingText = $INPUT_TEXT;
            $oghmaAmount = isset($GLOBALS["OGHMA_AMOUNT"]) ? intval($GLOBALS["OGHMA_AMOUNT"]) : 1;
            $firstTopic = null; // Track the first topic we find
            $extractionStarted = hrtime(true);
            $groundedTopics = chimOghmaExtractTopics($db, $INPUT_TEXT, $oghmaAmount);
            $groundedElapsed = (hrtime(true) - $extractionStarted) / 1_000_000;

            // Extract topics up to OGHMA_AMOUNT times
            for ($i = 0; $i < $oghmaAmount; $i++) {
                $currentInputTopic = $groundedTopics[$i] ?? null;
                $topic_res = ['elapsed_time' => $groundedElapsed];

                // Preserve the existing extractor as a single fallback for explicit topics
                // that the deterministic catalog pass could not ground directly.
                if ($currentInputTopic === null
                    && $i === 0
                    && empty($groundedTopics)
                    && ($oghmaCustomEnabled || chimOghmaShouldUseTopicFallback($INPUT_TEXT))) {
                    if ($oghmaCustomEnabled) {
                        require_once(__DIR__."/../lib/oghma_llm_service.php");
                        $lang = isset($GLOBALS["CORE_LANG"]) && !empty($GLOBALS["CORE_LANG"]) ? $GLOBALS["CORE_LANG"] : 'en';
                        $topic_req = LLMTopic($remainingText, $lang);
                    } elseif ($minimeEnabled) {
                        $topic_req = minimeTopic($remainingText);
                    } else {
                        $topic_req = null;
                    }
                    $fallback = $topic_req ? json_decode($topic_req, true) : null;
                    $suggestedTopic = is_array($fallback) ? getArrayKey($fallback, "generated_tags") : null;
                    $currentInputTopic = $suggestedTopic
                        ? chimOghmaResolveTopicName($db, (string) $suggestedTopic)
                        : null;
                    if (is_array($fallback)) {
                        $topic_res = $fallback;
                    }
                }

                if (!empty($currentInputTopic)) {
                        // Store the first topic we find
                        if ($firstTopic === null) {
                            $firstTopic = $currentInputTopic;
                        }
                        
                        // The extractor returns a canonical database topic, so retrieval is
                        // an exact lookup instead of another fuzzy body-ranking pass.
                        $groundedTopic = chimOghmaFetchTopic($db, $currentInputTopic);
                        $oghmaTopics = $groundedTopic ? [$groundedTopic] : [];

                        if (!empty($oghmaTopics)) {
                            $topTopic = $oghmaTopics[0];
                            $msg = 'oghma keyword offered';

                            // If rank is good enough, we try to see if user can access advanced or basic lore
                            $hintLengthBeforeTopic = strlen($GLOBALS["OGHMA_HINT"]);
                            if (!chimOghmaTopicWasInjected($topTopic["topic"] ?? '')) {
                                // -----------------------------
                                // 1) Check advanced article
                                // -----------------------------
                                $advancedAllowed = false;
                                $advClassesStr   = trim($topTopic["knowledge_class"] ?? '');
                                if ($advClassesStr === '') {
                                    // Empty => no restriction
                                    $advancedAllowed = true;
                                } else {
                                    // Convert advanced classes to array and separate anti-categories
                                    $advClassesArr   = array_map('trim', explode(',', $advClassesStr));
                                    $advClassesArr   = array_filter($advClassesArr);

                                    // Separate positive and negative (anti) categories
                                    $positiveClasses = array_filter($advClassesArr, fn($c) => !str_starts_with($c, '!'));
                                    $antiClasses = array_map(fn($c) => substr($c, 1), array_filter($advClassesArr, fn($c) => str_starts_with($c, '!')));

                                    // First check if any anti-categories match (these deny access)
                                    $hasAntiMatch = !empty(array_intersect($antiClasses, $oghmaKnowledgeArray));
                                    
                                    if ($hasAntiMatch) {
                                        // Anti-category matched, deny access
                                        $advancedAllowed = false;
                                    } else {
                                        // No anti-match, check positive categories
                                        $hasAdvancedKnowledge = array_intersect($positiveClasses, $oghmaKnowledgeArray);
                                        if (!empty($hasAdvancedKnowledge)) {
                                            $advancedAllowed = true;
                                        }
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
                                    $description = trim((string) ($topTopic["topic_desc"] ?? ''));
                                    if (!chimOghmaPayloadWasInjected($description)) {
                                        $GLOBALS["OGHMA_HINT"] .= " \n#Lore Information (You have advanced knowledge on this subject, you can use it in your dialogue):{$topTopic["topic"]}\n\"{$description}\"";
                                        chimOghmaMarkPayloadInjected($description);
                                    } else {
                                        $msg = "oghma keyword duplicate content already injected";
                                        chimOghmaMarkTopicInjected($topTopic["topic"] ?? '');
                                    }
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
                                        // Convert basic classes to array and separate anti-categories
                                        $basicClassesArr = array_map('trim', explode(',', $basicClassesStr));
                                        $basicClassesArr = array_filter($basicClassesArr);

                                        // Separate positive and negative (anti) categories
                                        $positiveClasses = array_filter($basicClassesArr, fn($c) => !str_starts_with($c, '!'));
                                        $antiClasses = array_map(fn($c) => substr($c, 1), array_filter($basicClassesArr, fn($c) => str_starts_with($c, '!')));

                                        // First check if any anti-categories match (these deny access)
                                        $hasAntiMatch = !empty(array_intersect($antiClasses, $oghmaKnowledgeArray));
                                        
                                        if ($hasAntiMatch) {
                                            // Anti-category matched, deny access
                                            $basicAllowed = false;
                                        } else {
                                            // No anti-match, check positive categories
                                            $hasBasicKnowledge = array_intersect($positiveClasses, $oghmaKnowledgeArray);
                                            if (!empty($hasBasicKnowledge)) {
                                                $basicAllowed = true;
                                            }
                                        }
                                    }

                                    if ($basicAllowed) {
                                        $description = trim((string) ($topTopic["topic_desc_basic"] ?? ''));
                                        if (!chimOghmaPayloadWasInjected($description)) {
                                            $GLOBALS["OGHMA_HINT"] .= " \n#Lore Information (You only have basic knowledge on this subject, you can use it in your dialogue): {$topTopic["topic"]}\n\"{$description}\"";
                                            chimOghmaMarkPayloadInjected($description);
                                        } else {
                                            $msg = "oghma keyword duplicate content already injected";
                                            chimOghmaMarkTopicInjected($topTopic["topic"] ?? '');
                                        }
                                    } else {
                                        $GLOBALS["OGHMA_HINT"] .= " \n#Lore Information\nYou do not know ANYTHING about {$topTopic["topic"]}";
                                    }
                                }
                                if (strlen($GLOBALS["OGHMA_HINT"]) > $hintLengthBeforeTopic) {
                                    chimOghmaMarkTopicInjected($topTopic["topic"] ?? '');
                                }
                            } elseif (chimOghmaTopicWasInjected($topTopic["topic"] ?? '')) {
                                $msg = "oghma keyword already injected from scene context";
                            } else {
                                $msg = "oghma keyword NOT offered (no good results in search)";
                            }

                            // Log to audit_memory immediately after processing this topic
                            $GLOBALS["db"]->insert(
                                'audit_memory',
                                array(
                                    'input'    => $INPUT_TEXT,
                                    'keywords' => $msg,
                                    'rank_any' => $topTopic["combined_rank"],
                                    'rank_all' => $topTopic["combined_rank"],
                                    'memory'   => "$currentInputTopic => {$topTopic["topic"]}",
                                    'time'     => $topic_res["elapsed_time"] ?? $groundedElapsed
                                )
                            );
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
} elseif (!$oghmaInfiniumEnabled) {
    error_log("[OGHMA] OGHMA_INFINIUM disabled: {$GLOBALS["OGHMA_INFINIUM"]}");
}
?>
