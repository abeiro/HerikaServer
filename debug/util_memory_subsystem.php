<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

$GLOBALS["ENGINE_ROOT"] = $enginePath;
$GLOBALS["ENGINE_PATH"] = $GLOBALS["ENGINE_ROOT"]; // Todo, make this uniform

require_once $enginePath . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib/logger.php";
require_once $enginePath . "lib/model_dynmodel.php";
require_once $enginePath . "lib/chat_helper_functions.php";
require_once $enginePath . "lib/memory_helper_vectordb.php";
require_once $enginePath . "lib/data_functions.php";
require_once $enginePath . "lib/minimet5_service.php";

require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";
require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_SUMMARY')) {
    echo "Summaries are disabled globally; memory summary work was skipped." . PHP_EOL;
    exit(0);
}

function chimResolveSummaryConnectorRuntime(): ?array
{
    if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_SUMMARY')) {
        return null;
    }

    $connectorId = intval($GLOBALS["CORE_CONNECTOR_SUMMARY"] ?? 0);
    if ($connectorId <= 0) {
        return null;
    }

    $connector = new LLMConnector();
    $currentConnectorData = $connector->getById($connectorId);
    if (!$currentConnectorData) {
        return null;
    }

    $connector->setOldGlobals($currentConnectorData);
    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
    $GLOBALS["CURRENT_CONNECTOR"] = $currentConnectorData["driver"];

    return [$connector, $currentConnectorData];
}

function resyncMemorySummaries($db, $forceAll = false, $onlyFix = false)
{

    $processed_counter=0;
    // Determine query based on whether we're syncing all or just missing vectors
    if ($onlyFix == false) {
        if ($forceAll) {
            $countQuery = "SELECT COUNT(*) AS n FROM memory_summary WHERE summary IS NOT NULL";
            $fetchQuery = "SELECT summary AS content, uid, classifier, rowid, companions FROM memory_summary WHERE summary IS NOT NULL";
        } else {
            $countQuery = "SELECT COUNT(*) AS count FROM memory_summary WHERE summary IS NOT NULL AND (embedding IS NULL OR native_vec IS NULL)";
            $fetchQuery = "SELECT summary AS content, uid, classifier, rowid, companions FROM memory_summary WHERE summary IS NOT NULL AND (embedding IS NULL OR native_vec IS NULL)";
        }

        $results                = $db->fetchOne($countQuery);
        $memories_to_sync_count = isset($results['n']) ? (int) $results['n'] : (isset($results['count']) ? (int) $results['count'] : 0);

        if ($memories_to_sync_count == 0) {
            echo "No memories found requiring synchronization." . PHP_EOL;
            return 0;
        }

        echo "Found {$memories_to_sync_count} memories to sync. Starting process..." . PHP_EOL;

        $results           = $db->fetchAll($fetchQuery);
        $processed_counter = 0;

        foreach ($results as $row) {
            $TEST_TEXT = $row["content"];
            $tagsCol   = "";

            // Extract and expand tags from #Tags: block
            $pattern = '/#Tags:\s*(.+)/s';
            if (preg_match($pattern, $TEST_TEXT, $matches)) {
                preg_match_all('/#(\w+)/', $matches[1], $tagMatches);
                $tags = $tagMatches[1] ?? [];

                $expandedTags = array_map(function ($tag) {
                    return trim(preg_replace('/(?<!^)([A-Z])/', ' $1', $tag));
                }, $tags);

                $tagsCol = implode(', ', $expandedTags);
                //error_log(" * Using tags: $tagsCol");
            } else {
                error_log(" * No tags found; using body text ({$row["classifier"]})");
            }

            // Update embedding using storeMemory
            if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
                storeMemory($TEST_TEXT, $TEST_TEXT, $row["rowid"], $row["classifier"], $row["companions"]);
            }

            // Save extracted tags and update native_vec (FTS vector)
            $db->update("memory_summary", "tags='" . $db->escape($tagsCol) . "'", "rowid={$row["rowid"]}");
            $db->execQuery("UPDATE memory_summary SET scope='global' WHERE rowid={$row["rowid"]} AND scope IS NULL");
            $db->execQuery("UPDATE memory_summary SET native_vec = setweight(to_tsvector(coalesce(tags, '')), 'A') || setweight(to_tsvector(coalesce(summary, '')), 'B') WHERE rowid={$row["rowid"]}");

            $processed_counter++;
            echo "Updated vector for memory ID {$row["rowid"]}. (Processed {$processed_counter} of {$memories_to_sync_count})" . PHP_EOL;
        }

        if ($processed_counter > 0) {
            echo "Successfully synchronized {$processed_counter} memories." . PHP_EOL;
        }
    }
    // --- Fix companions field (first method) ---
    echo "Completing companions field (method 1)..." . PHP_EOL;
    $pfi      = ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"] + 0) * 100000;

    $missingCompanions = $db->fetchAll("SELECT * FROM memory_summary WHERE (companions IS NULL OR companions = '') and classifier<>'diary' ORDER BY gamets_truncated ASC");
    $n=0;
    foreach ($missingCompanions as $row) {
        $peopleRows = $db->fetchAll("SELECT CASE WHEN party='[]' THEN people ELSE COALESCE(people, party) END AS people FROM eventlog WHERE gamets > {$row["gamets_truncated"]}::bigint - $pfi AND gamets <= {$row["gamets_truncated"]}::bigint + $pfi");
        $npcs       = [];
        foreach ($peopleRows as $p) {
            if (! empty($p["people"])) {
                foreach (explode("|", $p["people"]) as $npc) {
                    $npc = trim(preg_replace('/\([^)]*\)/', '', $npc));
                    if ($npc !== '') {
                        $npcs[$npc] = ($npcs[$npc] ?? 0) + 1;
                    }
                }
            }
        }

        $npcInMemory = [];
        foreach ($npcs as $name => $occurrences) {
            $cleanName = trim((string)$name);
            if ($cleanName === '' || $cleanName === 'unknown' || $cleanName === '--' || $cleanName === '-') {
                continue;
            }
            if ($occurrences > 1) {
                if (strpos((string)$row["packed_message"], $cleanName) === false) {
                    continue;
                }
                $npcInMemory[] = $cleanName;
            }
        }

        $peopleFmt = $db->escape("|" . implode("|", $npcInMemory) . "|");
        $db->query("UPDATE memory_summary SET companions='$peopleFmt' WHERE rowid={$row["rowid"]}");
        if ($n++ % 100 == 0) {
            echo "Completed $n/".sizeof($missingCompanions).PHP_EOL;
        }
    }

    // --- Fix companions field (second method) ---
    echo "Completing companions field (method 2)..." . PHP_EOL;
    $n=0;
    $missingCompanions2 = $db->fetchAll("SELECT * FROM memory_summary WHERE (companions IS NULL OR companions = '') and classifier<>'diary' ORDER BY gamets_truncated ASC");
    foreach ($missingCompanions2 as $row) {
        $peopleRow = $db->fetchOne("SELECT STRING_AGG(DISTINCT speaker || '|' || listener, '|') AS people FROM public.memory_v WHERE gamets > {$row["gamets_truncated"]}::bigint - $pfi AND gamets <= {$row["gamets_truncated"]}::bigint + $pfi");
        $npcs      = [];
        if (! empty($peopleRow["people"])) {
            foreach (explode("|", $peopleRow["people"]) as $npc) {
                $npc = trim($npc);
                if ($npc && $npc !== "unknown" && $npc !== "--" && $npc !== "-" && $npc !== "The Narrator") {
                    $npcs[$npc] = ($npcs[$npc] ?? 0) + 1;
                }
            }
        }

        $npcInMemory = [];
        foreach ($npcs as $name => $occurrences) {
            if ($occurrences > 1) {
                if (strpos((string)$row["packed_message"], $name) === false) {
                    continue;
                }
                $npcInMemory[] = $name;
            }
        }

        $peopleFmt = $db->escape(implode(",", $npcInMemory));
        $db->query("UPDATE memory_summary SET companions='$peopleFmt' WHERE rowid={$row["rowid"]}");
        if ($n++ % 100 == 0) {
            echo "Completed $n/".sizeof($missingCompanions2).PHP_EOL;
        }
    }

    return $processed_counter;
}

function isNpcIndividualMemoryEnabled($npcRow)
{
    if (empty($npcRow["extended_data"])) {
        return false;
    }

    $extendedData = json_decode($npcRow["extended_data"], true);
    if (!is_array($extendedData)) {
        return false;
    }

    if (!array_key_exists('individual_memory_enabled', $extendedData)) {
        return false;
    }

    return !empty($extendedData['individual_memory_enabled']);
}

function buildNpcIndividualMemoryBioContext($npcRow)
{
    $fields = [
        "core"           => "Core",
        "npc_static_bio" => "Static Bio",
        "personality"    => "Personality",
        "goals"          => "Goals",
        "speechstyle"    => "Speech Style",
    ];

    $lines = [];
    foreach ($fields as $key => $label) {
        $value = trim((string)($npcRow[$key] ?? ''));
        if ($value !== '') {
            $lines[] = "- {$label}: {$value}";
        }
    }

    return implode(PHP_EOL, $lines);
}

function extractMemoryTagsColumn($text)
{
    $tagsCol = '';
    $pattern = '/#Tags:\s*(.+)/is';
    preg_match($pattern, $text, $matches);
    if (isset($matches[1])) {
        $tagsString = strtr($matches[1], ["*" => ""]);
        $tagsArray  = array_map('trim', preg_split('/[\s,]+/', $tagsString));
        $tagsArray  = array_values(array_unique(array_filter($tagsArray, function($tag) {
            return $tag !== '';
        })));
        $tagsCol = implode(" ", $tagsArray);
    }

    return $tagsCol;
}

function syncIndividualMemorySummaries($db, $connectionHandler = null, $maxBatchesPerNpc = 3)
{
    $threshold = intval($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["INDIVIDUAL_MEMORY_SUMMARY_THRESHOLD"] ?? 3);
    if ($threshold < 2) {
        $threshold = 2;
    }
    $pfi = intval($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"] ?? 10) * 100000;
    if ($pfi < 1000) {
        $pfi = 100000;
    }

    $allNpcs = $db->fetchAll("SELECT id, npc_name, core, npc_static_bio, personality, goals, speechstyle, extended_data FROM core_npc_master WHERE npc_name IS NOT NULL ORDER BY npc_name ASC");
    $enabledNpcs = [];
    foreach ($allNpcs as $npcRow) {
        if (($npcRow["npc_name"] ?? '') === "The Narrator") {
            continue;
        }
        if (isNpcIndividualMemoryEnabled($npcRow)) {
            $enabledNpcs[] = $npcRow;
        }
    }

    if (sizeof($enabledNpcs) === 0) {
        echo "No NPCs have individual memory bank enabled." . PHP_EOL;
        return 0;
    }

    if (!$connectionHandler) {
        $summaryConnectorState = chimResolveSummaryConnectorRuntime();
        if (!$summaryConnectorState) {
            echo "No summary connector configured. Skipping individual memory sync." . PHP_EOL;
            return 0;
        }

        [$connector, $currentConnectorData] = $summaryConnectorState;
        $connectionHandler = $connector->getConnector($currentConnectorData);
    }

    $summaryPromptValue = '';
    try {
        $summaryPromptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'summary_prompt'");
        if ($summaryPromptData) {
            $summaryPromptValue = (!empty($summaryPromptData['custom_prompt'])) ? $summaryPromptData['custom_prompt'] : $summaryPromptData['default_prompt'];
        }
    } catch (Exception $e) {
        // keep fallback
    }
    if ($summaryPromptValue === '') {
        $summaryPromptValue = $GLOBALS["SUMMARY_PROMPT"] ?? '';
    }

    $individualPromptTemplate = null;
    try {
        $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'memory_subsystem_summary_individual'");
        if ($promptData) {
            $individualPromptTemplate = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
        }
    } catch (Exception $e) {
        // keep fallback
    }
    if (!$individualPromptTemplate) {
        $individualPromptTemplate =
            "You are writing an individual memory bank summary for NPC {NPC_NAME} in Skyrim roleplay.\n".
            "Write from {NPC_NAME}'s perspective and values.\n".
            "Only include events where {NPC_NAME} is directly involved.\n".
            "Ignore game-engine/system messages and focus on roleplay events, people, locations, and motivations.\n".
            "Character reference:\n{NPC_BIO}\n\n".
            "Additional instructions: {SUMMARY_PROMPT}";
    }

    $createdCount = 0;
    foreach ($enabledNpcs as $npcRow) {
        $npcName = trim((string)($npcRow["npc_name"] ?? ''));
        if ($npcName === '') {
            continue;
        }

        $npcEsc = $db->escape($npcName);
        $lastScoped = $db->fetchOne("SELECT COALESCE(MAX(gamets_truncated), 0) AS max_gamets FROM memory_summary WHERE scope='$npcEsc'");
        $lastScopedGamets = intval($lastScoped["max_gamets"] ?? 0);

        // Individual summaries are selected by NPC presence in source events/dialogue windows,
        // not by text mention checks in global packed summaries.
        $pendingRows = $db->fetchAll("
            SELECT ms.rowid, ms.uid, ms.gamets_truncated, ms.summary, ms.packed_message
            FROM memory_summary ms
            WHERE ms.summary IS NOT NULL
              AND (ms.scope IS NULL OR ms.scope='global')
              AND ms.gamets_truncated > $lastScopedGamets
              AND (
                    EXISTS (
                        SELECT 1
                        FROM eventlog ev
                        WHERE ev.gamets > ms.gamets_truncated - $pfi
                          AND ev.gamets <= ms.gamets_truncated + $pfi
                          AND (
                                (CASE
                                    WHEN COALESCE(ev.party, '[]') = '[]' THEN COALESCE(ev.people, '')
                                    ELSE COALESCE(ev.people, ev.party)
                                 END) ILIKE '%|$npcEsc|%'
                                OR
                                (CASE
                                    WHEN COALESCE(ev.party, '[]') = '[]' THEN COALESCE(ev.people, '')
                                    ELSE COALESCE(ev.people, ev.party)
                                 END) ILIKE '%$npcEsc%'
                              )
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM memory_v mv
                        WHERE mv.gamets > ms.gamets_truncated - $pfi
                          AND mv.gamets <= ms.gamets_truncated + $pfi
                          AND (
                                LOWER(COALESCE(mv.speaker, '')) = LOWER('$npcEsc')
                                OR LOWER(COALESCE(mv.listener, '')) = LOWER('$npcEsc')
                              )
                    )
                  )
            ORDER BY ms.gamets_truncated ASC, ms.rowid ASC
            LIMIT 500
        ");

        if (sizeof($pendingRows) < $threshold) {
            continue;
        }

        $npcBioContext = buildNpcIndividualMemoryBioContext($npcRow);
        $systemPrompt = str_replace(
            ['{NPC_NAME}', '{NPC_BIO}', '{SUMMARY_PROMPT}'],
            [$npcName, $npcBioContext, $summaryPromptValue],
            $individualPromptTemplate
        );

        $batchCounter = 0;
        while (sizeof($pendingRows) >= $threshold && $batchCounter < $maxBatchesPerNpc) {
            $batch = array_slice($pendingRows, 0, $threshold);
            $maxGamets = 0;
            $maxUid = 0;
            $history = "";
            foreach ($batch as $entry) {
                $entryGamets = intval($entry["gamets_truncated"] ?? 0);
                if ($entryGamets > $maxGamets) {
                    $maxGamets = $entryGamets;
                }
                $entryUid = intval($entry["uid"] ?? 0);
                if ($entryUid > $maxUid) {
                    $maxUid = $entryUid;
                }

                // Individual bank should be grounded in raw packed event slices (global-style),
                // not in already-compressed global summaries.
                $entryRawSlice = trim((string)($entry["packed_message"] ?? ''));
                if ($entryRawSlice === '') {
                    $entryRawSlice = trim((string)($entry["summary"] ?? ''));
                    if ($entryRawSlice !== '') {
                        $entryRawSlice = preg_replace('/#Tags:.*$/mi', '', $entryRawSlice);
                    }
                }
                if ($entryRawSlice === '') {
                    continue;
                }

                $history .= "===\nMemory entry, date " . convert_gamets2skyrim_date($entryGamets) . PHP_EOL;
                $history .= trim($entryRawSlice) . PHP_EOL . PHP_EOL;
            }

            if (trim($history) === '') {
                break;
            }

            $previousMemoryReq = $db->fetchOne("SELECT summary FROM memory_summary WHERE scope='$npcEsc' AND summary IS NOT NULL ORDER BY gamets_truncated DESC, rowid DESC LIMIT 1");
            $previousMemory = trim((string)($previousMemoryReq["summary"] ?? ''));

            $prompt = [];
            $prompt[] = ['role' => 'system', 'content' => $systemPrompt];
            if ($previousMemory !== '') {
                $prompt[] = ['role' => 'user', 'content' => "#PREVIOUS NPC MEMORY SUMMARY#\n{$previousMemory}\n#END OF PREVIOUS NPC MEMORY SUMMARY#"];
            }
            $prompt[] = ['role' => 'user', 'content' => "#NPC EVENT HISTORY#\n{$history}\n#END OF NPC EVENT HISTORY#"];
            $prompt[] = ['role' => 'user', 'content' => "Write one memory summary for {$npcName} using this format:\n#Summary: {summary from {$npcName}'s viewpoint}\n\n#Tags: {hashtags for people, places, and events}"];

            $buffer = $connectionHandler->fast_request($prompt, [], "summary");
            $summaryText = trim(strtr((string)$buffer, ["**" => ""]));
            if ($summaryText === '') {
                break;
            }
            if (stripos($summaryText, '#Summary:') === false) {
                $summaryText = "#Summary: " . $summaryText;
            }

            $tagsCol = extractMemoryTagsColumn($summaryText);
            $uidToUse = $maxUid > 0 ? $maxUid : intval($batch[sizeof($batch) - 1]["rowid"] ?? 1);

            $db->insert(
                'memory_summary',
                array(
                    'gamets_truncated' => $maxGamets,
                    'n' => sizeof($batch),
                    'packed_message' => $history,
                    'summary' => $summaryText,
                    'classifier' => 'individual',
                    'uid' => $uidToUse,
                    'companions' => "|{$npcName}|",
                    'scope' => $npcName,
                    'tags' => $tagsCol
                )
            );

            $createdCount++;
            $batchCounter++;

            echo "Created individual summary for {$npcName} at gamets {$maxGamets} using " . sizeof($batch) . " events." . PHP_EOL;

            $pendingRows = array_values(array_filter($pendingRows, function($entry) use ($maxGamets) {
                return intval($entry["gamets_truncated"] ?? 0) > $maxGamets;
            }));
        }
    }

    return $createdCount;
}

if (! isset($argv[1])) {
    die(
        "Use " . basename(__FILE__) . " command parm

commands:

	query		Query for a memory. Example: query 'What do you know about Saadia?'
    query_oghma	Query for a oghma entry.
	count		Count Memories, memories summarized and memories vectorized.
	sync 		Sync Summaries <> Vector embeddings. Needs TEXT2VEC active
    resync 		Sync Summaries <> Vector embeddings, people and Tags. Needs TEXT2VEC active
    sync_oghma	Sync oghma <> Vector embeddings. Needs TEXT2VEC active
	get 		Get memory. Example: get 56
	recreate	Recreate memory_summary table,
	compact	    Recreate memory_summary table, and uses AI (LLM) to summarize data. Also builds per-NPC scoped summaries for NPCs with individual memory enabled. Use 'compact noembed' to avoid TEXT2VEC sync.
    query_raw   Query for a memory. Only embedding will be used. (For testing)
    fixcomp     Fix companions field

Note: Memories are stored in memory_summary table, which holds info from events/dialogues... in a time packed format.

");
} else {

    if ($argv[1] == "get") {
        $db = new sql();
        echo "Get memory {$argv[2]}" . PHP_EOL;
        $data = getElement($argv[2]);
        print_r($data);
        print_r($GLOBALS["DEBUG_DATA"]);

    } elseif ($argv[1] == "query") {
        echo "Query memory for '{$argv[2]}'" . PHP_EOL;

        $db             = new sql();
        $localStartTime = microtime(true);

        if (isset($argv[3]) && (! empty($argv[3]))) {
            $npcMaster      = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($argv[3]);

            $profile            = new CoreProfile();
            $currentProfileData = $profile->getById($currentNpcData["profile_id"]);

            $summaryConnectorState = chimResolveSummaryConnectorRuntime();
            if (!$summaryConnectorState) {
                die("No summary connector configured." . PHP_EOL);
            }

            [$connector, $currentConnectorData] = $summaryConnectorState;
            $profile->setOldGlobals($currentProfileData);
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
        } else {
            $GLOBALS["MINIME_T5"]   = true;
            $GLOBALS["HERIKA_NAME"] = "%";
        }

        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Using pgvector search (text2vec) DataSearchMemoryByVector({$argv[2]}, {$argv[3]}, true,{$argv[4]}" . PHP_EOL;

            error_log("[DataSearchMemoryByVector calling]  : " . (microtime(true) - $localStartTime) . " seconds");
            $res = DataSearchMemoryByVector($argv[2], $argv[3], true, $argv[4]);
            error_log("[DataSearchMemoryByVector called]  : " . (microtime(true) - $localStartTime) . " seconds");
            $res2 = DataSearchMemoryByVector($argv[2], $argv[3], false, $argv[4]);
            error_log("[DataSearchMemoryByVector called]  : " . (microtime(true) - $localStartTime) . " seconds");

            if (isset($res[0]) && isset($res2[0])) {
                $resFinal = ($res[0]['rank_any'] >= $res2[0]['rank_any']) ? $res : $res2;
            } else {
                $resFinal = isset($res[0]['rank_any']) ? $res : (isset($res2[0]['rank_any']) ? $res2 : []);
            }
            $res = $resFinal;
            print_r($res2);
        } else {
            echo "Using fts search";
            $res = DataSearchMemory($argv[2], $argv[3]);
        }

        if (isset($res[0])) {
            Logger::trace(print_r($res[0], true));

            if (($res[0]["rank_any"] == $res[0]["rank_all"]) && ($res[0]["rank_any"] > 0.25) && ! isset($res[0]["mixed_distance"])) {

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else if (((($res[0]["rank_all"] + $res[0]["rank_any"]) / 2) > 0.25) && ! isset($res[0]["mixed_distance"])) {

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else if ((($res[0]["rank_all"] + $res[0]["rank_any"]) / 2) > 0.05 && false) { //This is too low

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else if (($res[0]["rank_any"] > 0.5) && isset($res[0]["mixed_distance"])) { // Search by mixed vector/fts .

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else {
                error_log("Memory discarded by scoring");

            }
            print_r($res[0]);
        } else {
            error_log("Memory not found");
        }

    } elseif ($argv[1] == "query_raw") {
        echo "Query memory for '{$argv[2]}'" . PHP_EOL;

        $db             = new sql();
        $localStartTime = microtime(true);
        $url            = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"] . '/embed';

        $data = [

            'text' => $argv[2],
        ];

        // Convert to JSON
        $options = [
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n" .
                "Accept: application/json\r\n",
                'content'       => json_encode($data),
                'ignore_errors' => true, // to capture error messages if any
            ],
        ];

        // Create context and send the request
        $context = stream_context_create($options);

        error_log("[DataSearchMemoryByVector Embedding start] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");
        $response = file_get_contents($url, false, $context);
        error_log("[DataSearchMemoryByVector Embedding end] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");

        // Output the response
        if ($response === false) {
            Logger::error("Request failed.\n");
        } else {
            Logger::info("Request done:\n");

        }
        $vector = json_decode($response, true);

        $resultNormalized = explode(" ", $argv[2]);
        $kwStringAny      = implode(" | ", $resultNormalized);
        $kwStringAll      = implode(" & ", $resultNormalized);

        if (is_array($vector) && isset($vector["embedding"])) {

            $vectorString = "'[" . implode(",", $vector["embedding"]) . "]'";
            $finalQuery   = "
                SELECT rowid,summary, gamets_truncated,
                        embedding <-> $vectorString as distance,
                         ts_rank(native_vec, to_tsquery('$kwStringAny')) AS rank_any_fts,
                         ts_rank(native_vec, to_tsquery('$kwStringAll')) AS rank_all_fts,
                         (embedding <-> $vectorString) - ts_rank(native_vec, to_tsquery('$kwStringAny')) AS mixed_distance
                    FROM public.memory_summary
                    WHERE embedding IS NOT NULL
                    ORDER BY
                        embedding <-> $vectorString ASC
                    LIMIT 5 OFFSET 0
                ";
            $memory = $GLOBALS["db"]->fetchAll($finalQuery);
        }
        print_r(array_reverse($memory));

    } elseif ($argv[1] == "query_oghma") {
        echo "Query memory for '{$argv[2]}'" . PHP_EOL;

        $db = new sql();

        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Using pgvector search" . PHP_EOL;

            $currentOghmaTopic_req = $db->fetchOne("SELECT value FROM conf_opts WHERE id='current_oghma_topic'");
            $currentOghmaTopic     = getArrayKey($currentOghmaTopic_req, "value");

            // Get location and context keywords
            $locationCtx     = DataLastKnownLocationHuman(false);
            $contextKeywords = implode(" ", lastKeyWordsContext(5, $GLOBALS["HERIKA_NAME"]));
            error_log("DataSearchOghmaByVector Expanded keywords: <$currentOghmaTopic> <$locationCtx> <$contextKeywords>");

            $res = DataSearchOghmaByVector($argv[2], $currentOghmaTopic, $locationCtx, $contextKeywords);

        } else {
            die("FTS oghma search still not supported in this script");
        }

        print_r($res[0]);

    } elseif ($argv[1] == "query_ckw") {
        echo "Query memory for '{$argv[2]}'" . PHP_EOL;

        $db             = new sql();
        $localStartTime = microtime(true);

        if (isset($argv[3]) && (! empty($argv[3]))) {
            $npcMaster      = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($argv[3]);

            $profile            = new CoreProfile();
            $currentProfileData = $profile->getById($currentNpcData["profile_id"]);

            $summaryConnectorState = chimResolveSummaryConnectorRuntime();
            if (!$summaryConnectorState) {
                die("No summary connector configured." . PHP_EOL);
            }

            [$connector, $currentConnectorData] = $summaryConnectorState;
            $profile->setOldGlobals($currentProfileData);
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
        } else {
            $GLOBALS["MINIME_T5"]   = true;
            $GLOBALS["HERIKA_NAME"] = "%";
        }

        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Using pgvector search (text2vec) DataSearchMemoryByVector({$argv[2]}, {$argv[3]}, true,{$argv[4]}" . PHP_EOL;

            error_log("[DataSearchMemoryByVectorFromContextKeywords calling]  : " . (microtime(true) - $localStartTime) . " seconds");
            $res = DataSearchMemoryByVectorFromContextKeywords($argv[2], $argv[3],  $argv[4]);
            error_log("[DataSearchMemoryByVector called]  : " . (microtime(true) - $localStartTime) . " seconds");
            $res2 = DataSearchMemoryByVectorFromContextKeywords($argv[2], $argv[3],  $argv[4]);
            error_log("[DataSearchMemoryByVectorFromContextKeywords called]  : " . (microtime(true) - $localStartTime) . " seconds");

            if (isset($res[0]) && isset($res2[0])) {
                $resFinal = ($res[0]['rank_any'] >= $res2[0]['rank_any']) ? $res : $res2;
            } else {
                $resFinal = isset($res[0]['rank_any']) ? $res : (isset($res2[0]['rank_any']) ? $res2 : []);
            }
            $res = $resFinal;
            
        } else {
            echo "Using fts search";
            $res = DataSearchMemory($argv[2], $argv[3]);
        }

        if (isset($res[0])) {
            Logger::trace(print_r($res[0], true));

            if (($res[0]["rank_any"] == $res[0]["rank_all"]) && ($res[0]["rank_any"] > 0.25) && ! isset($res[0]["mixed_distance"])) {

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else if (((($res[0]["rank_all"] + $res[0]["rank_any"]) / 2) > 0.25) && ! isset($res[0]["mixed_distance"])) {

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else if ((($res[0]["rank_all"] + $res[0]["rank_any"]) / 2) > 0.05 && false) { //This is too low

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else if (($res[0]["rank_any"] > 0.5) && isset($res[0]["mixed_distance"])) { // Search by mixed vector/fts .

                $memory = (isset($memories[0]["summary"]) ? $memories[0]["summary"] : "");

            } else {
                error_log("Memory discarded by scoring");

            }
            print_r($res[0]);
        } else {
            error_log("Memory not found");
        }

    } elseif ($argv[1] == "sync") {

        echo "Starting memory vector synchronization..." . PHP_EOL;
        $db = new sql();
        resyncMemorySummaries($db, false); // Only sync missing embeddings

        echo "Memory synchronization process finished." . PHP_EOL;

    } elseif ($argv[1] == "fixcomp") {

        echo "Starting fixing..." . PHP_EOL;
        $db = new sql();
        $db->execQuery("update memory_summary set companions=null where classifier='dialogue'");

        resyncMemorySummaries($db, false,true);

        echo "Fixing process ended." . PHP_EOL;

    }  elseif ($argv[1] == "resync") {
        echo "Starting full memory resync..." . PHP_EOL;
        $db = new sql();
        resyncMemorySummaries($db, true);
        echo "Full resync completed." . PHP_EOL;

    } elseif ($argv[1] == "sync_oghma") {
        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
            echo "Creating vectors for memories" . PHP_EOL;

            $db      = new sql();
            $results = $db->fetchAll("select topic,topic_desc from oghma where vector384 is null");
            $counter = 0;
            foreach ($results as $row) {

                $TEST_TEXT = $row["topic_desc"];
                storeMemoryOghma($TEST_TEXT, $TEST_TEXT, $row["topic"]); // JUST UPDATE embedding in memory_summary

                $counter++;

                echo "Updated vector for  {$row["topic"]} $counter\n";
            }
        }

    } elseif ($argv[1] == "compact") {

        echo "Starting memory compaction process..." . PHP_EOL;
        $db = new sql();

        echo "Packing existing data into summary..." . PHP_EOL;
        $maxRow = PackIntoSummary();
        echo "Packing complete. Max gamets_truncated row ID from existing summaries: {$maxRow}" . PHP_EOL;

        echo "Packing missing diary entries into summary..." . PHP_EOL;
        $maxRowIgnored = PackIntoSummary(true);
        echo "Packing complete. Max gamets_truncated row ID from existing summaries: {$maxRow}" . PHP_EOL;

        echo "Checking for new entries to summarize and compact..." . PHP_EOL;
        $count_query              = "select COUNT(*) as count from memory_summary where (gamets_truncated>{$maxRow} or summary is null)  ";
        $count_result_res_compact = $db->query($count_query);
        $count_result_arr         = $db->fetchArray($count_result_res_compact);
        $entries_to_process_count = $count_result_arr ? (int) $count_result_arr['count'] : 0;

        $processed_in_loop_counter = 0;
        $connectionHandler = null;

        if ($entries_to_process_count == 0) {
            echo "No new entries found to summarize and compact." . PHP_EOL;
        } else {
            echo "Found {$entries_to_process_count} entries to process. Starting summarization..." . PHP_EOL;

            $summaryConnectorState = chimResolveSummaryConnectorRuntime();
            if (!$summaryConnectorState) {
                echo "No summary connector configured. Skipping summarization." . PHP_EOL;
            } else {
                [$connector, $currentConnectorData] = $summaryConnectorState;
                $connectionHandler = $connector->getConnector($currentConnectorData);

                error_log("Using connector {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");

                $results_query = "select gamets_truncated,packed_message,uid,classifier,rowid,companions from memory_summary where (gamets_truncated>{$maxRow} or summary is null)  order by gamets_truncated asc ";
                $results       = $db->query($results_query);

                $toUpdate = [];

                while ($row = $db->fetchArray($results)) {

                if (isset($argv[3])) { // User-defined limit for number of entries to process
                    if ($processed_in_loop_counter >= ($argv[3] + 0)) {
                        echo "Reached processing limit of {$argv[3]} entries." . PHP_EOL;
                        break;
                    }
                }

                $prevMemory = $db->fetchOne("SELECT gamets_truncated, packed_message,  uid, classifier, rowid,companions,summary FROM memory_summary WHERE gamets_truncated < {$row["gamets_truncated"]} ORDER BY gamets_truncated DESC LIMIT 1");
                // Summarization logic begins
                if ($row["classifier"] == "diary") {
                    $TEST_TEXT = $row["packed_message"];
                } else {
                    $GLOBALS["COMMAND_PROMPT"] = "";
                    $gameRequest               = ["summary"];
                    $CLFORMAT                  = "#Summary: {summary of events and dialogues}\n\n#Tags: {list of relevant twitter-like hashtags, include location names, enemies names, other NPC names}";
                    if (isset($GLOBALS["CORE_LANG"])) {
                        if ($GLOBALS["CORE_LANG"] == "es") {
                            $CLFORMAT .= "\n\nGENERA EL CONTENIDO Y LOS TAGS EN ESPAÑOL";
                        }
                    }
                    // Database Prompt (Memory Compaction)
                    $companionsLine = ! empty($row["companions"]) ? "{$row["companions"]} are nearby characters.\n" : "";

                    // Load memory subsystem summary prompt from database with fallback to hardcoded default
                    $memoryPrompt = null;
                    try {
                        $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'memory_subsystem_summary'");
                        if ($promptData) {
                            // Use custom_prompt if set, otherwise use default_prompt
                            $memoryPrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
                        }
                    } catch (Exception $e) {
                        Logger::warn("Failed to load memory subsystem prompt from database, using hardcoded fallback: " . $e->getMessage());
                    }

                    // Hardcoded fallback if database query failed or returned no results
                    if (!$memoryPrompt) {
                        $memoryPrompt = 
                            "{PLAYER_NAME} is the player.\n".
                            "{COMPANIONS_LINE}\n".
                            "You must write a memory summary from the narrator's point of view by analyzing the chat history. Focus only on roleplay elements: character behavior, feelings, relationships, decisions, dialogue, and locations relevant to the story. Ignore any references to game engine mechanics, menus, stats, or system messages.\n".
                            "Pay close attention to details that could influence a character's behavior or emotions, as well as tag names and locations. Include quotes from character dialogue in the summary if they are relevant to understanding actions, motivations, or relationships\n\n".
                            "Here are additional instructions: {SUMMARY_PROMPT}";
                    }

                    // Load SUMMARY_PROMPT from database with fallback to $GLOBALS
                    $summaryPromptValue = null;
                    try {
                        $summaryPromptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'summary_prompt'");
                        if ($summaryPromptData) {
                            $summaryPromptValue = (!empty($summaryPromptData['custom_prompt'])) ? $summaryPromptData['custom_prompt'] : $summaryPromptData['default_prompt'];
                        }
                    } catch (Exception $e) {
                        // Silent fallback to $GLOBALS
                    }
                    
                    // Fallback to $GLOBALS if database load failed
                    if (empty($summaryPromptValue)) {
                        $summaryPromptValue = $GLOBALS["SUMMARY_PROMPT"] ?? '';
                    }
                    
                    // Replace placeholders with actual values
                    $memoryPromptProcessed = str_replace(
                        ['{PLAYER_NAME}', '{COMPANIONS_LINE}', '{SUMMARY_PROMPT}'],
                        [$GLOBALS["PLAYER_NAME"], $companionsLine, $summaryPromptValue],
                        $memoryPrompt
                    );

                    $prompt   = [];
                    $prompt[] = ['role' => 'system',
                        'content' => "This is a playthrough in Skyrim.\n" . $memoryPromptProcessed
                    ];
                    if (! empty($prevMemory["summary"])) {
                        $prompt[] = ['role' => 'user', 'content' => "#PREVIOUS MEMORY (for reference only)#\n{$prevMemory["summary"]}\n#END OF PREVIOUS MEMORY#"];
                    }
                    $prompt[] = ['role' => 'user', 'content' => "#CHAT HISTORY#\n{$row["packed_message"]}\n#END OF CHAT HISTORY#"];
                    $prompt[] = ['role' => 'user',
                        'content'           => "Read #CHAT HISTORY# and write a extensive memory record about events and conversations. Use this format:\n$CLFORMAT"];

                    $GLOBALS["FORCE_MAX_TOKENS"] = $GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["MAX_TOKENS_MEMORY"];

                    $buffer = $connectionHandler->fast_request($prompt, [], "summary");

                    $TEST_TEXT = strtr($buffer, ["**" => ""]); // Use the final buffer

                    // if the llm repeats tags we tidy them up
                    $pattern = '/#Tags:/';
                    $split   = preg_split($pattern, $TEST_TEXT, 2);
                    if (isset($split[1])) {
                        // make data consistent (copied from tagsCol creation below)
                        $tagsString = strtr($split[1], ["*" => ""]);
                        $tagsArray  = array_map('trim', explode(',', $tagsString));
                        $tagsCol    = implode(" ", $tagsArray);
                        $tagsArray  = array_map('trim', explode(' ', $tagsCol));

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

                    $toUpdate[] = ["rowid" => $row["rowid"], "summary" => $TEST_TEXT];
                }
                // Summarization logic ends, $TEST_TEXT contains the summary or packed_message

                $processed_in_loop_counter++; // Increment after deciding to process/summarize this item
                echo "Summarized entry for ID {$row["rowid"]}. (Processed attempt {$processed_in_loop_counter} of {$entries_to_process_count})" . PHP_EOL;

                Logger::debug("$TEST_TEXT");

                // Original script's embedding logic: if (($argv[2]!="noembed")&& false)
                // This condition `&& false` means embedding inside compact was effectively disabled.
                // The "Run a sync later" message aligns with this.
                // So, no embedding happens here. We'll add a message about 'noembed' argument after the loop.

                $pattern = '/Tags:(.+)/';
                preg_match($pattern, $TEST_TEXT, $matches);
                $tagsCol = ''; // Initialize tagsCol
                if (isset($matches[1])) {
                    $tagsString = strtr($matches[1], ["*" => ""]);
                    $tagsArray  = array_map('trim', explode(',', $tagsString));
                    $tagsCol    = implode(" ", $tagsArray);
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
                if ($row["classifier"] != "diary") {   // For non-diary, $TEST_TEXT is from LLM
                                                           // Find the current summary from $toUpdate if it exists
                    foreach ($toUpdate as $upd_item) {
                        if ($upd_item["rowid"] == $row["rowid"]) {
                            $current_summary_to_save = $upd_item["summary"];
                            break;
                        }
                    }
                }
                if ($current_summary_to_save) {
                    $db->execQuery("update memory_summary set summary='" . $db->escape($current_summary_to_save) . "',tags='" . $db->escape($tagsCol) . "',scope=COALESCE(scope,'global') where rowid={$row["rowid"]}");
                    $db->execQuery("update memory_summary SET native_vec = setweight(to_tsvector(coalesce(tags, '')),'A')||setweight(to_tsvector(coalesce(summary, '')),'B') where rowid={$row["rowid"]}");
                }

                // Original embedding call within loop (conditionally for $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"])
                // This was outside the 'noembed' check but was also within the $toUpdate loop which processed one item.
                // Given the outer "Run a sync later" and the `&& false` on explicit embedding, this part might be redundant or only for `native_vec`.
                // The `native_vec` is already updated above. Explicit `storeMemory` for full embedding is best left to the `sync` command.

                $toUpdate = []; // Reset for next iteration as in original script

                } // End while loop

                if ($processed_in_loop_counter > 0) {
                    echo "Attempted summarization for {$processed_in_loop_counter} entries (out of {$entries_to_process_count} found needing update)." . PHP_EOL;
                } elseif ($entries_to_process_count > 0) {
                    echo "Found {$entries_to_process_count} entries, but 0 were processed in this run (check logs for details or processing limit if set via third argument)." . PHP_EOL;
                }
            }
        }

        echo "Starting global pre-sync for companions/tags..." . PHP_EOL;
        resyncMemorySummaries($db, false, true);
        echo "Global pre-sync finished." . PHP_EOL;

        echo "Starting individual memory bank sync..." . PHP_EOL;
        $individualCreatedCount = syncIndividualMemorySummaries($db, $connectionHandler);
        echo "Individual memory bank sync finished. Created {$individualCreatedCount} scoped summaries." . PHP_EOL;

        echo "Starting memory vector synchronization..." . PHP_EOL;
        resyncMemorySummaries($db, false); // Only sync missing embeddings

        echo "Memory synchronization process finished." . PHP_EOL;
        echo "Memory compaction process finished." . PHP_EOL;

    } elseif ($argv[1] == "recreate") {
        echo "Deleting memory_summary" . PHP_EOL;

        $db      = new sql();
        $results = $db->query("delete from memory_summary");

        $maxRow = PackIntoSummary();

        echo "memory_summary created" . PHP_EOL;

    } elseif ($argv[1] == "count") {
        $db = new sql();
        echo countMemories() . PHP_EOL;

    } else {
        echo "Command not found: {$argv[1]}" . PHP_EOL;
        echo "Use " . basename(__FILE__) . " without args to see help" . PHP_EOL;

    }

}
