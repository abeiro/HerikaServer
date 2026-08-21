<?php

if (($gameRequest[0] == "delete_event")) {
    // Do this ASAP
    $datacn = $db->escape($gameRequest[3]);
    $db->delete("eventlog", "type in ('chat','prechat') and data like '%$datacn%' and localts>" . (time() - 120));
    // audit_log(__FILE__);
    error_log("[DELETION] Deleted event with data: $datacn");
    terminate();
}

// Biography CSV upload
if ($gameRequest[0] == "biography_import") {
    require(__DIR__ . "/biography_import.php");

    terminate();
}




// Oghma CSV upload
// Move this to a processor file
if ($gameRequest[0] == "oghma_import") {
    Logger::info("Processing Oghma CSV data upload");

    // Parse the message format: oghma_import|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Oghma Import: No CSV data provided");
        terminate();
    }

    $csvData = $gameRequest[4];
    $processedCount = 0;
    $errorCount = 0;

    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'oghma_import_');
        file_put_contents($tempFile, $csvData);

        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Oghma Import: Could not open temporary CSV file");
            terminate();
        }

        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            terminate();
        }

        // Normalize header labels and create header map
        $headerMap = [];
        foreach ($header as $i => $colName) {
            $normalized = strtolower(trim($colName));
            $headerMap[$normalized] = $i;
        }

        // Process each data row
        while (($data = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
            if (empty($data) || count($data) < 2) {
                continue; // Skip empty or invalid rows
            }

            // Extract required fields
            $topic = '';
            if (isset($headerMap['topic']) && isset($data[$headerMap['topic']])) {
                $topic = strtolower(trim($data[$headerMap['topic']]));
            }

            $topic_desc = '';
            if (isset($headerMap['topic_desc']) && isset($data[$headerMap['topic_desc']])) {
                $topic_desc = trim($data[$headerMap['topic_desc']]);
            }

            // Extract optional fields
            $knowledge_class = '';
            if (isset($headerMap['knowledge_class']) && isset($data[$headerMap['knowledge_class']])) {
                $knowledge_class = trim($data[$headerMap['knowledge_class']]);
            }

            $topic_desc_basic = '';
            if (isset($headerMap['topic_desc_basic']) && isset($data[$headerMap['topic_desc_basic']])) {
                $topic_desc_basic = trim($data[$headerMap['topic_desc_basic']]);
            }

            $knowledge_class_basic = '';
            if (isset($headerMap['knowledge_class_basic']) && isset($data[$headerMap['knowledge_class_basic']])) {
                $knowledge_class_basic = trim($data[$headerMap['knowledge_class_basic']]);
            }

            $tags = '';
            if (isset($headerMap['tags']) && isset($data[$headerMap['tags']])) {
                $tags = trim($data[$headerMap['tags']]);
            }

            $category = '';
            if (isset($headerMap['category']) && isset($data[$headerMap['category']])) {
                $category = trim($data[$headerMap['category']]);
            }

            // Skip if required fields are missing
            if (empty($topic) || empty($topic_desc)) {
                Logger::warn("Oghma Import: Skipping row with missing topic or topic_desc");
                $errorCount++;
                continue;
            }

            // Insert or update record using upsertRowOnConflict
            try {
                $db->upsertRowOnConflict(
                    'oghma',
                    array(
                        'topic' => $topic,
                        'topic_desc' => $topic_desc,
                        'knowledge_class' => $knowledge_class,
                        'topic_desc_basic' => $topic_desc_basic,
                        'knowledge_class_basic' => $knowledge_class_basic,
                        'tags' => $tags,
                        'category' => $category
                    ),
                    'topic'
                );
                $processedCount++;
                Logger::info("Oghma Import: Successfully processed topic: $topic");
            } catch (Exception $e) {
                Logger::error("Oghma Import: Error processing topic '$topic': " . $e->getMessage());
                $errorCount++;
            }
        }

        fclose($handle);
        unlink($tempFile);

        Logger::info("Oghma Import: Processing complete. $processedCount records processed, $errorCount errors");

    } catch (Exception $e) {
        Logger::error("Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    terminate();
}


// Dynamic Oghma CSV upload
// Move this to a processor file
// Will insert data into database and will terminate.
if ($gameRequest[0] == "dynamic_oghma_import") {
    Logger::info("Processing Dynamic Oghma CSV data upload");

    // Parse the message format: dynamic_oghma_import|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Dynamic Oghma Import: No CSV data provided");
        terminate();
    }

    $csvData = $gameRequest[4];
    $processedCount = 0;
    $errorCount = 0;

    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'dynamic_oghma_import_');
        file_put_contents($tempFile, $csvData);

        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Dynamic Oghma Import: Could not open temporary CSV file");
            terminate();
        }

        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Dynamic Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            terminate();
        }

        // Normalize header labels and create header map
        $headerMap = [];
        foreach ($header as $i => $colName) {
            $normalized = strtolower(trim($colName));
            $headerMap[$normalized] = $i;
        }

        // Process each data row
        while (($data = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
            if (empty($data) || count($data) < 3) {
                continue; // Skip empty or invalid rows
            }

            // Extract required fields
            $id_quest = '';
            if (isset($headerMap['id_quest']) && isset($data[$headerMap['id_quest']])) {
                $id_quest = trim($data[$headerMap['id_quest']]);
            }

            $stage = 0;
            if (isset($headerMap['stage']) && isset($data[$headerMap['stage']])) {
                $stage = intval(trim($data[$headerMap['stage']]));
            }

            $topic = '';
            if (isset($headerMap['topic']) && isset($data[$headerMap['topic']])) {
                $topic = strtolower(trim($data[$headerMap['topic']]));
            }

            // Extract optional fields
            $topic_desc = '';
            if (isset($headerMap['topic_desc']) && isset($data[$headerMap['topic_desc']])) {
                $topic_desc = trim($data[$headerMap['topic_desc']]);
            }

            $knowledge_class = '';
            if (isset($headerMap['knowledge_class']) && isset($data[$headerMap['knowledge_class']])) {
                $knowledge_class = trim($data[$headerMap['knowledge_class']]);
            }

            $topic_desc_basic = '';
            if (isset($headerMap['topic_desc_basic']) && isset($data[$headerMap['topic_desc_basic']])) {
                $topic_desc_basic = trim($data[$headerMap['topic_desc_basic']]);
            }

            $knowledge_class_basic = '';
            if (isset($headerMap['knowledge_class_basic']) && isset($data[$headerMap['knowledge_class_basic']])) {
                $knowledge_class_basic = trim($data[$headerMap['knowledge_class_basic']]);
            }

            $tags = '';
            if (isset($headerMap['tags']) && isset($data[$headerMap['tags']])) {
                $tags = trim($data[$headerMap['tags']]);
            }

            $category = '';
            if (isset($headerMap['category']) && isset($data[$headerMap['category']])) {
                $category = trim($data[$headerMap['category']]);
            }

            // Skip if required fields are missing
            if (empty($id_quest) || empty($topic)) {
                Logger::warn("Dynamic Oghma Import: Skipping row with missing id_quest or topic");
                $errorCount++;
                continue;
            }

            // Insert record (dynamic oghma doesn't use upsert, it allows multiple entries)
            try {
                $db->insert(
                    'oghma_dynamic',
                    array(
                        'id_quest' => $id_quest,
                        'stage' => $stage,
                        'topic' => $topic,
                        'topic_desc' => $topic_desc,
                        'knowledge_class' => $knowledge_class,
                        'topic_desc_basic' => $topic_desc_basic,
                        'knowledge_class_basic' => $knowledge_class_basic,
                        'tags' => $tags,
                        'category' => $category
                    )
                );
                $processedCount++;
                Logger::info("Dynamic Oghma Import: Successfully processed quest '$id_quest' stage $stage topic '$topic'");
            } catch (Exception $e) {
                Logger::error("Dynamic Oghma Import: Error processing quest '$id_quest' topic '$topic': " . $e->getMessage());
                $errorCount++;
            }
        }

        fclose($handle);
        unlink($tempFile);

        Logger::info("Dynamic Oghma Import: Processing complete. $processedCount records processed, $errorCount errors");

    } catch (Exception $e) {
        Logger::error("Dynamic Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    terminate();
}


// Please explain what this function is use for in a comment.
function maybeQueueNpcVoiceRefresh($currentNpcData, $npcMaster)
{
    if (!$currentNpcData || !($npcMaster instanceof NpcMaster)) {
        return $currentNpcData;
    }

    $npcName = trim((string) ($currentNpcData["npc_name"] ?? ""));
    if ($npcName === "" || strcasecmp($npcName, "The Narrator") === 0) {
        return $currentNpcData;
    }

    $voiceId = trim((string) ($currentNpcData["voiceid"] ?? ""));
    if ($voiceId !== "") {
        return $currentNpcData;
    }

    $extended = $npcMaster->getExtendedData($currentNpcData);
    $lastRequestedAt = intval($extended["voice_refresh_requested_at"] ?? 0);
    $cooldownSeconds = 300;
    $now = time();

    if ($lastRequestedAt > 0 && ($now - $lastRequestedAt) < $cooldownSeconds) {
        return $currentNpcData;
    }

    $extended["voice_refresh_requested_at"] = $now;
    $extended["voice_refresh_attempts"] = intval($extended["voice_refresh_attempts"] ?? 0) + 1;
    $extended["voice_refresh_last_result"] = "requested";

    $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extended);
    $npcMaster->updateByArray($currentNpcData);

    $refId = trim((string) ($currentNpcData["refid"] ?? ""));
    if ($refId !== "" && stripos($refId, "0x") !== 0) {
        $refId = "0x{$refId}";
    }

    echo "{$npcName}|rolecommand|RefreshNPCVoice@{$refId}@{$npcName}\r\n";
    error_log("[NPCVOICE_REFRESH] Requested refresh for {$npcName} ({$refId})");

    return $currentNpcData;
}


function chimFormatPromptXmlSections($content)
{
    $content = str_replace(["\r\n", "\r"], "\n", (string) $content);
    $content = preg_replace("/[ \t]+\n/", "\n", $content);

    $lines = explode("\n", $content);
    $formatted = [];
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $line = rtrim($lines[$i]);
        $trimmed = trim($line);

        if ($trimmed === '') {
            if (!empty($formatted) && trim(end($formatted)) !== '') {
                $formatted[] = '';
            }
            continue;
        }

        $isBlockOpenTag = preg_match('/^<([A-Za-z0-9_]+)>$/', $trimmed) === 1;
        $isBlockCloseTag = preg_match('/^<\/([A-Za-z0-9_]+)>$/', $trimmed) === 1;

        if ($isBlockOpenTag && !empty($formatted) && trim(end($formatted)) !== '') {
            $formatted[] = '';
        }

        $formatted[] = $line;

        if ($isBlockCloseTag) {
            $nextNonEmpty = '';
            for ($j = $i + 1; $j < $lineCount; $j++) {
                $candidate = trim(rtrim($lines[$j]));
                if ($candidate !== '') {
                    $nextNonEmpty = $candidate;
                    break;
                }
            }

            if ($nextNonEmpty !== '' && trim(end($formatted)) !== '') {
                $formatted[] = '';
            }
        }
    }

    while (!empty($formatted) && trim($formatted[0]) === '') {
        array_shift($formatted);
    }
    while (!empty($formatted) && trim(end($formatted)) === '') {
        array_pop($formatted);
    }

    $content = implode("\n", $formatted);
    $content = preg_replace("/\n{3,}/", "\n\n", $content);

    return $content . "\n";
}

function chimRemovePromptXmlBlock($content, string $tag)
{
    $tagPattern = preg_quote($tag, '/');
    return preg_replace('/\n*<' . $tagPattern . '>\s*.*?\s*<\/' . $tagPattern . '>\n*/s', "\n", (string) $content);
}

function chimApplyPromptContextOptionsToSystemPrompt($content)
{
    if (!function_exists('chimGetPromptContextOptions') || !function_exists('chimGetPromptContextOptionCatalog')) {
        return chimFormatPromptXmlSections($content);
    }

    $options = chimGetPromptContextOptions();
    $catalog = chimGetPromptContextOptionCatalog();

    foreach ($catalog as $bucket => $bucketOptions) {
        $enabledTags = $options[$bucket] ?? array_keys($bucketOptions ?? []);
        foreach (array_keys($bucketOptions ?? []) as $tag) {
            if (!in_array($tag, $enabledTags, true)) {
                $content = chimRemovePromptXmlBlock($content, $tag);
            }
        }
    }

    if (!preg_match('/<character>\s*<\/character>/s', $content)) {
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
    }

    if (!preg_match('/<general_instructions>\s*<\/general_instructions>/s', $content)) {
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
    }

    return chimFormatPromptXmlSections($content);
}
?>