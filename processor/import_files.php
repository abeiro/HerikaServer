<?php

require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."action_catalog.php");
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chim_quest_engine.php");
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."oghma_aliases.php");

/* CSV Import Processor - Called by csv_import.php endpoint
 * Handles CSV imports:
 * - biography_import: NPC character data
 * - oghma_import: Knowledge base entries
 * - dynamic_oghma_import: Quest-specific knowledge entries
 * - description_import: Item/entity description data
 * - custom_action_import: DB-backed custom action definitions
 * - traditional_quest_import: CHIM AI quest definitions
 */
if (isset($_POST['csv_import']) && $_POST['csv_import'] == '1' && isset($_POST['type'])) {
    $import_type = $_POST['type'];
    $timestamp = $_POST['ts'] ?? time();
    $game_timestamp = $_POST['gamets'] ?? 0;
    $filename = $_POST['filename'] ?? '';
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Logger::error("CSV Import ($import_type): No file uploaded or upload error occurred");
        return false;
    }
    
    $csvData = file_get_contents($_FILES['file']['tmp_name']);
    if (empty($csvData)) {
        Logger::error("CSV Import ($import_type): Empty CSV file uploaded");
        die("X-CUSTOM-CLOSE");
    }
    
    // Route to appropriate handler
    switch ($import_type) {
        case 'biography_import':
            handleBiographyImport($csvData, $timestamp, $game_timestamp);
            break;
        case 'oghma_import':
            handleOghmaImport($csvData, $timestamp, $game_timestamp);
            break;
        case 'dynamic_oghma_import':
            handleDynamicOghmaImport($csvData, $timestamp, $game_timestamp);
            break;
        case 'description_import':
            handleDescriptionImport($csvData, $timestamp, $game_timestamp);
            break;
        case 'custom_action_import':
            handleCustomActionImport($csvData, $timestamp, $game_timestamp, $filename);
            break;
        case 'traditional_quest_import':
            handleTraditionalQuestImport($csvData, $timestamp, $game_timestamp, $filename);
            break;
        default:
            Logger::error("CSV Import: Unknown import type: $import_type");
            return false;
    }
}

function herikaLogCsvImportAuditEvent($eventType, $message, $timestamp, $game_timestamp)
{
    global $db;

    $normalizedEventType = strtolower(trim(strval($eventType)));
    $normalizedMessage = trim(strval($message));
    if ($normalizedEventType === '' || $normalizedMessage === '') {
        return;
    }

    // Use status_msg so CSV upload audits remain visible in eventlog without ever entering prompt context.
    $db->insert(
        'eventlog',
        array(
            'ts' => $timestamp,
            'gamets' => $game_timestamp,
            'type' => 'status_msg',
            'data' => "csv_import@{$normalizedEventType}@{$normalizedMessage}",
            'sess' => 'web',
            'localts' => time(),
            'people' => '',
            'location' => '',
            'party' => ''
        )
    );
}

if (!function_exists('chimNormalizeBiographyRelationshipSeed')) {
    function chimNormalizeBiographyRelationshipSeed($value, &$errorMessage = '')
    {
        $errorMessage = '';

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $trimmed = trim((string)$value);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '{') {
            $errorMessage = 'expected a JSON object with per-target relationship seeds';
            return false;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            $errorMessage = 'invalid JSON object';
            return false;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

function handleBiographyImport($csvData, $timestamp, $game_timestamp) {
    global $db;
    
    Logger::info("Biography Import: STARTED - Processing CSV data upload");
    
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'biography_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Biography Import: Could not open temporary CSV file");
            return false;
        }
        
        // Read and process header
        // Use default enclosure '"' and escape '\\' to correctly parse commas within quoted fields
        $header = fgetcsv($handle, 0, ',');
        if ($header === false || empty($header)) {
            Logger::error("Biography Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            return false;
        }
        
        // Normalize header labels and create header map
        $headerMap = [];
        foreach ($header as $i => $colName) {
            $normalized = strtolower(trim($colName));
            $headerMap[$normalized] = $i;
        }
        $hasAliasesColumn = isset($headerMap['aliases']);
        $databaseRows = $db->fetchAll("SELECT topic, coalesce(aliases, '') AS aliases FROM oghma");
        [$canonicalOwners, $aliasOwners] = chimOghmaBuildAliasOwnerMaps(
            is_array($databaseRows) ? $databaseRows : []
        );
        
        // Process each data row
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (empty($data) || count($data) < 2) {
                continue; // Skip empty or invalid rows
            }
            
            // Extract required fields
            $npc_name = '';
            if (isset($headerMap['npc_name']) && isset($data[$headerMap['npc_name']])) {
                $npc_name = strtolower(trim($data[$headerMap['npc_name']]));
            }
            
            // core (summary) - support new and legacy header
            $core = '';
            if (isset($headerMap['core']) && isset($data[$headerMap['core']])) {
                $core = trim($data[$headerMap['core']]);
            } elseif (isset($headerMap['npc_pers']) && isset($data[$headerMap['npc_pers']])) {
                $core = trim($data[$headerMap['npc_pers']]);
            }
            
            // Extract optional fields
            $npc_dynamic = null;
            if (isset($headerMap['npc_dynamic']) && isset($data[$headerMap['npc_dynamic']])) {
                $temp = trim($data[$headerMap['npc_dynamic']]);
                $npc_dynamic = ($temp !== '') ? $temp : null;
            }
            
            // Oghma tags (new and legacy)
            $oghma_knowledge_tags = '';
            if (isset($headerMap['oghma_knowledge_tags']) && isset($data[$headerMap['oghma_knowledge_tags']])) {
                $oghma_knowledge_tags = trim($data[$headerMap['oghma_knowledge_tags']]);
            } elseif (isset($headerMap['npc_misc']) && isset($data[$headerMap['npc_misc']])) {
                $oghma_knowledge_tags = trim($data[$headerMap['npc_misc']]);
            }
            
            $melotts_voiceid = null;
            if (isset($headerMap['melotts_voiceid']) && isset($data[$headerMap['melotts_voiceid']])) {
                $temp = trim($data[$headerMap['melotts_voiceid']]);
                $melotts_voiceid = ($temp !== '') ? $temp : null;
            }
            
            $xtts_voiceid = null;
            if (isset($headerMap['xtts_voiceid']) && isset($data[$headerMap['xtts_voiceid']])) {
                $temp = trim($data[$headerMap['xtts_voiceid']]);
                $xtts_voiceid = ($temp !== '') ? $temp : null;
            }
            
            $xvasynth_voiceid = null;
            if (isset($headerMap['xvasynth_voiceid']) && isset($data[$headerMap['xvasynth_voiceid']])) {
                $temp = trim($data[$headerMap['xvasynth_voiceid']]);
                $xvasynth_voiceid = ($temp !== '') ? $temp : null;
            }

            // Extract extended biography fields
            $npc_static_bio = null;
            if (isset($headerMap['npc_static_bio']) && isset($data[$headerMap['npc_static_bio']])) {
                $temp = trim($data[$headerMap['npc_static_bio']]);
                $npc_static_bio = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['npc_background']) && isset($data[$headerMap['npc_background']])) {
                $temp = trim($data[$headerMap['npc_background']]);
                $npc_static_bio = ($temp !== '') ? $temp : null;
            }
            
            $npc_personality = null;
            if (isset($headerMap['npc_personality']) && isset($data[$headerMap['npc_personality']])) {
                $temp = trim($data[$headerMap['npc_personality']]);
                $npc_personality = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['personality']) && isset($data[$headerMap['personality']])) {
                $temp = trim($data[$headerMap['personality']]);
                $npc_personality = ($temp !== '') ? $temp : null;
            }
            
            $npc_appearance = null;
            if (isset($headerMap['npc_appearance']) && isset($data[$headerMap['npc_appearance']])) {
                $temp = trim($data[$headerMap['npc_appearance']]);
                $npc_appearance = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['appearance']) && isset($data[$headerMap['appearance']])) {
                $temp = trim($data[$headerMap['appearance']]);
                $npc_appearance = ($temp !== '') ? $temp : null;
            }
            
            $npc_relationships = null;
            if (isset($headerMap['npc_relationships']) && isset($data[$headerMap['npc_relationships']])) {
                $temp = trim($data[$headerMap['npc_relationships']]);
                $npc_relationships = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['relationships']) && isset($data[$headerMap['relationships']])) {
                $temp = trim($data[$headerMap['relationships']]);
                $npc_relationships = ($temp !== '') ? $temp : null;
            }
            
            $npc_occupation = null;
            if (isset($headerMap['npc_occupation']) && isset($data[$headerMap['npc_occupation']])) {
                $temp = trim($data[$headerMap['npc_occupation']]);
                $npc_occupation = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['occupation']) && isset($data[$headerMap['occupation']])) {
                $temp = trim($data[$headerMap['occupation']]);
                $npc_occupation = ($temp !== '') ? $temp : null;
            }
            
            $npc_skills = null;
            if (isset($headerMap['npc_skills']) && isset($data[$headerMap['npc_skills']])) {
                $temp = trim($data[$headerMap['npc_skills']]);
                $npc_skills = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['skills']) && isset($data[$headerMap['skills']])) {
                $temp = trim($data[$headerMap['skills']]);
                $npc_skills = ($temp !== '') ? $temp : null;
            }
            
            $npc_speechstyle = null;
            if (isset($headerMap['npc_speechstyle']) && isset($data[$headerMap['npc_speechstyle']])) {
                $temp = trim($data[$headerMap['npc_speechstyle']]);
                $npc_speechstyle = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['speechstyle']) && isset($data[$headerMap['speechstyle']])) {
                $temp = trim($data[$headerMap['speechstyle']]);
                $npc_speechstyle = ($temp !== '') ? $temp : null;
            }
            
            $npc_goals = null;
            if (isset($headerMap['npc_goals']) && isset($data[$headerMap['npc_goals']])) {
                $temp = trim($data[$headerMap['npc_goals']]);
                $npc_goals = ($temp !== '') ? $temp : null;
            } elseif (isset($headerMap['goals']) && isset($data[$headerMap['goals']])) {
                $temp = trim($data[$headerMap['goals']]);
                $npc_goals = ($temp !== '') ? $temp : null;
            }
            
            // Skip if required fields are missing
            if (empty($npc_name) || empty($core)) {
                Logger::warn("Biography Import: Skipping row with missing npc_name or core");
                $errorCount++;
                continue;
            }

            $relationshipError = '';
            $npc_relationships = chimNormalizeBiographyRelationshipSeed($npc_relationships, $relationshipError);
            if ($npc_relationships === false) {
                Logger::error(
                    "Biography Import: NPC '$npc_name' has invalid relationships field: $relationshipError"
                );
                $errorCount++;
                continue;
            }

            // Insert or update record using upsertRowOnConflict (bio schema)
            try {
                $db->upsertRowOnConflict(
                    'bio_templates_custom',
                    array(
                        'npc_name' => $npc_name,
                        'core' => $core,
                        'oghma_knowledge_tags' => $oghma_knowledge_tags,
                        'npc_static_bio' => $npc_static_bio,
                        'personality' => $npc_personality,
                        'appearance' => $npc_appearance,
                        'relationships' => $npc_relationships,
                        'occupation' => $npc_occupation,
                        'skills' => $npc_skills,
                        'speechstyle' => $npc_speechstyle,
                        'goals' => $npc_goals
                    ),
                    'npc_name'
                );
                $processedCount++;
            } catch (Exception $e) {
                Logger::error("Biography Import: Error processing NPC '$npc_name': " . $e->getMessage());
                $errorCount++;
            }
        }
        
        fclose($handle);
        unlink($tempFile);
        
        Logger::info("Biography Import: Processing complete. $processedCount records processed, $errorCount errors");
        
        herikaLogCsvImportAuditEvent(
            'biography_import',
            "$processedCount records processed, $errorCount errors",
            $timestamp,
            $game_timestamp
        );
        
    } catch (Exception $e) {
        Logger::error("Biography Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        herikaLogCsvImportAuditEvent(
            'biography_import',
            "failed: " . $e->getMessage(),
            $timestamp,
            $game_timestamp
        );
    }
    
    return true;
}

function handleOghmaImport($csvData, $timestamp, $game_timestamp) {
    global $db;
    
    Logger::info("Oghma Import: STARTED - Processing CSV data upload");
    
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'oghma_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Oghma Import: Could not open temporary CSV file");
            return false;
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            return false;
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

            $aliases = '';
            if ($hasAliasesColumn && isset($data[$headerMap['aliases']])) {
                $filtered = chimOghmaFilterAliases(
                    $topic,
                    trim($data[$headerMap['aliases']]),
                    $canonicalOwners,
                    $aliasOwners
                );
                $aliases = $filtered['aliases'];
            }
            
            // Skip if required fields are missing
            if (empty($topic) || empty($topic_desc)) {
                Logger::warn("Oghma Import: Skipping row with missing topic or topic_desc");
                $errorCount++;
                continue;
            }
            
            // Insert or update record using upsertRowOnConflict
            try {
                $values = array(
                        'topic' => $topic,
                        'topic_desc' => $topic_desc,
                        'knowledge_class' => $knowledge_class,
                        'topic_desc_basic' => $topic_desc_basic,
                        'knowledge_class_basic' => $knowledge_class_basic,
                        'tags' => $tags,
                        'category' => $category
                );
                if ($hasAliasesColumn) {
                    $values['aliases'] = $aliases;
                }
                $db->upsertRowOnConflict(
                    'oghma',
                    $values,
                    'topic'
                );

                $canonicalOwners[chimOghmaComparableAliasKey($topic)] = $topic;
                foreach (chimOghmaSplitAliases($aliases) as $alias) {
                    $aliasOwners[chimOghmaComparableAliasKey($alias)][$topic] = true;
                }
                
                // Update native_vector for search functionality
                $vectorUpdateSql = "
                    UPDATE oghma
                    SET native_vector = 
                          setweight(to_tsvector('simple', coalesce(topic, '')), 'A')
                        || setweight(to_tsvector('simple', coalesce(aliases, '')), 'A')
                        || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                        || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                    WHERE topic = '" . $db->escape($topic) . "'
                ";
                $db->query($vectorUpdateSql);
                
                $processedCount++;
            } catch (Exception $e) {
                Logger::error("Oghma Import: Error processing topic '$topic': " . $e->getMessage());
                $errorCount++;
            }
        }
        
        fclose($handle);
        unlink($tempFile);
        
        Logger::info("Oghma Import: Processing complete. $processedCount records processed, $errorCount errors");
        
        herikaLogCsvImportAuditEvent(
            'oghma_import',
            "$processedCount records processed, $errorCount errors",
            $timestamp,
            $game_timestamp
        );
        
    } catch (Exception $e) {
        Logger::error("Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        herikaLogCsvImportAuditEvent(
            'oghma_import',
            "failed: " . $e->getMessage(),
            $timestamp,
            $game_timestamp
        );
    }
    
    return true;
}

function handleDynamicOghmaImport($csvData, $timestamp, $game_timestamp) {
    global $db;
    
    Logger::info("Dynamic Oghma Import: STARTED - Processing CSV data upload");
    
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'dynamic_oghma_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Dynamic Oghma Import: Could not open temporary CSV file");
            return false;
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Dynamic Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            return false;
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
            
            // Check if record with same id_quest, stage, and topic already exists
            try {
                $escapedIdQuest = $db->escape($id_quest);
                $escapedTopic = $db->escape($topic);
                $existingRecord = $db->fetchAll("SELECT id FROM oghma_dynamic WHERE id_quest='$escapedIdQuest' AND stage=$stage AND topic='$escapedTopic'");
                
                if (!empty($existingRecord)) {
                    // Update existing record
                    $recordId = $existingRecord[0]['id'];
                    $updateSql = "UPDATE oghma_dynamic SET topic_desc='" . $db->escape($topic_desc) . "', " .
                                "knowledge_class='" . $db->escape($knowledge_class) . "', " .
                                "topic_desc_basic='" . $db->escape($topic_desc_basic) . "', " .
                                "knowledge_class_basic='" . $db->escape($knowledge_class_basic) . "', " .
                                "tags='" . $db->escape($tags) . "', " .
                                "category='" . $db->escape($category) . "' " .
                                "WHERE id=$recordId";
                    
                    if ($db->query($updateSql)) {
                        $processedCount++;
                    } else {
                        Logger::error("Dynamic Oghma Import: Error updating existing record for quest '$id_quest' topic '$topic'");
                        $errorCount++;
                    }
                } else {
                    // Insert new record
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
                }
            } catch (Exception $e) {
                Logger::error("Dynamic Oghma Import: Error processing quest '$id_quest' topic '$topic': " . $e->getMessage());
                $errorCount++;
            }
        }
        
        fclose($handle);
        unlink($tempFile);
        
        Logger::info("Dynamic Oghma Import: Processing complete. $processedCount records processed, $errorCount errors");
        
        herikaLogCsvImportAuditEvent(
            'dynamic_oghma_import',
            "$processedCount records processed, $errorCount errors",
            $timestamp,
            $game_timestamp
        );
        
    } catch (Exception $e) {
        Logger::error("Dynamic Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        herikaLogCsvImportAuditEvent(
            'dynamic_oghma_import',
            "failed: " . $e->getMessage(),
            $timestamp,
            $game_timestamp
        );
    }
    
    return true;
}

function handleDescriptionImport($csvData, $timestamp, $game_timestamp) {
    global $db;
    
    Logger::info("Description Import: STARTED - Processing CSV data upload");
    
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'description_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Description Import: Could not open temporary CSV file");
            return false;
        }
        
        $header = fgetcsv($handle, 1000, ',');
        $hasPluginColumn = is_array($header) && strtolower(trim($header[0] ?? '')) === 'plugin';
        if (!$hasPluginColumn) {
            Logger::error("Description Import: Invalid CSV header. Expected plugin, baseid, name, description");
            fclose($handle);
            unlink($tempFile);
            herikaLogCsvImportAuditEvent(
                'description_import',
                "failed: invalid CSV header",
                $timestamp,
                $game_timestamp
            );
            return false;
        }
        
        // Process each data row: plugin, baseid, name, description.
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $plugin      = trim($data[0] ?? '');
            $baseid      = trim($data[1] ?? '');
            $name        = $data[2] ?? '';
            $description = $data[3] ?? '';
            
            if (!empty($baseid)) {
                if (preg_match('/^(XX[0-9A-Fa-f]{6}|FEXXX[0-9A-Fa-f]{3}|[0-9A-Fa-f]{8})$/', $baseid)) {
                    $baseid = strtoupper($baseid);
                }

                // Truncate baseid to 128 characters
                if (strlen($baseid) > 128) {
                    $baseid = substr($baseid, 0, 128);
                }
                
                // Insert or update record using upsertRowOnConflict
                try {
                    $db->upsertRowOnConflict(
                        'descriptions_custom',
                        array(
                            'plugin' => $plugin,
                            'baseid' => $baseid,
                            'name' => $name,
                            'description' => $description
                        ),
                        'plugin, baseid'
                    );
                    $processedCount++;
                    Logger::debug("Description Import: Successfully processed: $baseid");
                } catch (Exception $e) {
                    Logger::error("Description Import: Error processing '$baseid': " . $e->getMessage());
                    $errorCount++;
                }
            } else {
                Logger::debug("Description Import: Skipping empty or invalid row (baseid missing)");
            }
        }
        
        fclose($handle);
        unlink($tempFile);
        
        Logger::info("Description Import: Processing complete. $processedCount records processed, $errorCount errors");
        
        herikaLogCsvImportAuditEvent(
            'description_import',
            "$processedCount records processed, $errorCount errors",
            $timestamp,
            $game_timestamp
        );
        
    } catch (Exception $e) {
        Logger::error("Description Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        herikaLogCsvImportAuditEvent(
            'description_import',
            "failed: " . $e->getMessage(),
            $timestamp,
            $game_timestamp
        );
    }
    
    return true;
}

function traditionalQuestImportCsvGetValue($headerMap, $data, $columnName, $default = '')
{
    $columnName = strtolower(trim($columnName));
    if (!isset($headerMap[$columnName])) {
        return $default;
    }

    $index = $headerMap[$columnName];
    if (!isset($data[$index])) {
        return $default;
    }

    return trim(strval($data[$index]));
}

function traditionalQuestImportCsvToBool($value, $default = true)
{
    $text = strtolower(trim(strval($value)));
    if ($text === '') {
        return (bool) $default;
    }

    return in_array($text, ['1', 'true', 't', 'yes', 'y', 'on', 'enabled'], true);
}

function traditionalQuestImportDecodeJson($rawValue, $fieldName, &$errorMessage)
{
    $errorMessage = '';
    $text = trim(strval($rawValue));
    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        $errorMessage = "Invalid JSON for {$fieldName}: " . json_last_error_msg();
        return null;
    }

    return $decoded;
}

function traditionalQuestImportApplyTextOverride(&$definition, $fieldName, $value)
{
    $text = trim(strval($value));
    if ($text !== '') {
        $definition[$fieldName] = $text;
    }
}

function traditionalQuestImportFromCsvData($csvData, $filename = '')
{
    global $db;

    if (!chimQuestEngineReady()) {
        throw new Exception("Quest tables are not ready.");
    }

    $summary = [
        'processed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'messages' => [],
    ];

    $tempFile = tempnam(sys_get_temp_dir(), 'traditional_quest_import_');
    file_put_contents($tempFile, $csvData);

    $handle = fopen($tempFile, 'r');
    if ($handle === false) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        throw new Exception("Could not open temporary CSV file.");
    }

    $header = fgetcsv($handle, 0, ',');
    if ($header === false || empty($header)) {
        fclose($handle);
        unlink($tempFile);
        throw new Exception("Invalid CSV header.");
    }

    $headerMap = [];
    foreach ($header as $i => $colName) {
        $headerMap[strtolower(trim($colName))] = $i;
    }

    $hasSkeletonJson = isset($headerMap['skeleton_json']) || isset($headerMap['definition_json']) || isset($headerMap['raw_json']);
    $hasBeatsJson = isset($headerMap['beats_json']);
    if (!$hasSkeletonJson && !$hasBeatsJson) {
        fclose($handle);
        unlink($tempFile);
        throw new Exception("Invalid CSV header. Expected skeleton_json or beats_json.");
    }

    $line = 1;
    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $line++;
        if (empty($data)) {
            continue;
        }

        $errorMessage = '';
        $skeletonRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'skeleton_json', '');
        if ($skeletonRaw === '') {
            $skeletonRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'definition_json', '');
        }
        if ($skeletonRaw === '') {
            $skeletonRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'raw_json', '');
        }

        $definition = traditionalQuestImportDecodeJson($skeletonRaw, 'skeleton_json', $errorMessage);
        if ($definition === null && $skeletonRaw !== '') {
            Logger::error("Traditional Quest Import: line {$line} - {$errorMessage}");
            $summary['errors']++;
            $summary['messages'][] = "Line {$line}: {$errorMessage}";
            continue;
        }

        if ($definition === null) {
            $definition = [];
        }

        traditionalQuestImportApplyTextOverride($definition, 'quest_key', traditionalQuestImportCsvGetValue($headerMap, $data, 'quest_key', ''));
        traditionalQuestImportApplyTextOverride($definition, 'quest_editor_id', traditionalQuestImportCsvGetValue($headerMap, $data, 'quest_editor_id', ''));
        traditionalQuestImportApplyTextOverride($definition, 'title', traditionalQuestImportCsvGetValue($headerMap, $data, 'title', ''));
        traditionalQuestImportApplyTextOverride($definition, 'quest_plugin', traditionalQuestImportCsvGetValue($headerMap, $data, 'quest_plugin', ''));
        traditionalQuestImportApplyTextOverride($definition, 'quest_form_id', traditionalQuestImportCsvGetValue($headerMap, $data, 'quest_form_id', ''));
        traditionalQuestImportApplyTextOverride($definition, 'description', traditionalQuestImportCsvGetValue($headerMap, $data, 'description', ''));

        $beatsRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'beats_json', '');
        if ($beatsRaw !== '') {
            $beats = traditionalQuestImportDecodeJson($beatsRaw, 'beats_json', $errorMessage);
            if ($beats === null) {
                Logger::error("Traditional Quest Import: line {$line} - {$errorMessage}");
                $summary['errors']++;
                $summary['messages'][] = "Line {$line}: {$errorMessage}";
                continue;
            }
            $definition['beats'] = $beats;
        }

        $npcFactsRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'npc_facts_json', '');
        if ($npcFactsRaw !== '') {
            $npcFacts = traditionalQuestImportDecodeJson($npcFactsRaw, 'npc_facts_json', $errorMessage);
            if ($npcFacts === null) {
                Logger::error("Traditional Quest Import: line {$line} - {$errorMessage}");
                $summary['errors']++;
                $summary['messages'][] = "Line {$line}: {$errorMessage}";
                continue;
            }
            $definition['npc_facts'] = $npcFacts;
        }

        $naturalStartRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'natural_start_json', '');
        if ($naturalStartRaw !== '') {
            $naturalStart = traditionalQuestImportDecodeJson($naturalStartRaw, 'natural_start_json', $errorMessage);
            if ($naturalStart === null) {
                Logger::error("Traditional Quest Import: line {$line} - {$errorMessage}");
                $summary['errors']++;
                $summary['messages'][] = "Line {$line}: {$errorMessage}";
                continue;
            }
            $definition['natural_start'] = $naturalStart;
        }

        if (empty($definition['quest_key']) && empty($definition['quest_editor_id'])) {
            Logger::warn("Traditional Quest Import: line {$line} missing quest_key and quest_editor_id");
            $summary['errors']++;
            $summary['messages'][] = "Line {$line}: missing quest_key or quest_editor_id.";
            continue;
        }

        if (empty($definition['title'])) {
            $definition['title'] = $definition['quest_editor_id'] ?? $definition['quest_key'];
        }
        if (empty($definition['skeleton_type'])) {
            $definition['skeleton_type'] = 'quest';
        }
        if (!isset($definition['beats']) || !is_array($definition['beats'])) {
            Logger::warn("Traditional Quest Import: line {$line} missing beats array");
            $summary['errors']++;
            $summary['messages'][] = "Line {$line}: missing beats array.";
            continue;
        }

        $sourcePath = 'csv:' . ($filename !== '' ? $filename : 'traditional_quest_import') . ':line' . $line;
        if (!chimQuestEngineUpsertDefinition($definition, $sourcePath)) {
            $summary['errors']++;
            $summary['messages'][] = "Line {$line}: failed to upsert quest definition.";
            continue;
        }

        $definition['quest_key'] = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? $definition['quest_editor_id'] ?? '');
        chimQuestEngineEnsureInstanceRow($definition);

        $activeRaw = traditionalQuestImportCsvGetValue($headerMap, $data, 'active', '');
        if ($activeRaw !== '') {
            $active = traditionalQuestImportCsvToBool($activeRaw, true);
            $questKeyCn = $db->escape($definition['quest_key']);
            $activeSql = $active ? 'true' : 'false';
            $db->execQuery("
                UPDATE public.skyrim_quest_definitions
                SET active = {$activeSql},
                    updated_at = now()
                WHERE quest_key = '{$questKeyCn}'
            ");
        }

        $summary['processed']++;
    }

    fclose($handle);
    unlink($tempFile);

    return $summary;
}

function handleTraditionalQuestImport($csvData, $timestamp, $game_timestamp, $filename = '')
{
    Logger::info("Traditional Quest Import: STARTED - Processing CSV data upload");

    try {
        $summary = traditionalQuestImportFromCsvData($csvData, $filename);
        Logger::info(
            "Traditional Quest Import: Processing complete. {$summary['processed']} records processed, {$summary['errors']} errors"
        );

        $summaryPrefix = ($filename !== '') ? "file={$filename}; " : '';
        herikaLogCsvImportAuditEvent(
            'traditional_quest_import',
            $summaryPrefix . "{$summary['processed']} records processed, {$summary['errors']} errors",
            $timestamp,
            $game_timestamp
        );

        return true;
    } catch (Exception $e) {
        Logger::error("Traditional Quest Import: Fatal error processing CSV: " . $e->getMessage());
        herikaLogCsvImportAuditEvent(
            'traditional_quest_import',
            "failed: " . $e->getMessage(),
            $timestamp,
            $game_timestamp
        );
        return false;
    }
}

function customActionImportCsvGetValue($headerMap, $data, $columnName, $default = '')
{
    $columnName = strtolower(trim($columnName));
    if (!isset($headerMap[$columnName])) {
        return $default;
    }

    $index = $headerMap[$columnName];
    if (!isset($data[$index])) {
        return $default;
    }

    return trim(strval($data[$index]));
}

function customActionImportCsvToBool($value, $default = false)
{
    $text = strtolower(trim(strval($value)));
    if ($text === '') {
        return (bool) $default;
    }

    return in_array($text, ['1', 'true', 't', 'yes', 'y', 'on'], true);
}

function customActionImportDecodeJsonField($rawValue, $default, &$errorMessage, $fieldName, $allowBlank = true)
{
    if (is_array($rawValue)) {
        return $rawValue;
    }

    $text = trim(strval($rawValue));
    if ($text === '') {
        if ($allowBlank) {
            return $default;
        }

        $errorMessage = "Missing JSON value for {$fieldName}";
        return null;
    }

    $decoded = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMessage = "Invalid JSON for {$fieldName}: " . json_last_error_msg();
        return null;
    }

    return $decoded;
}

function handleCustomActionImport($csvData, $timestamp, $game_timestamp, $filename = '')
{
    global $db;

    Logger::info("Custom Action Import: STARTED - Processing CSV data upload");

    if (!herikaActionCatalogDbReady()) {
        Logger::error("Custom Action Import: core_action_custom tables/view are not ready");
        return false;
    }

    $processedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    $hadFatalError = false;
    $processedCodeNames = [];

    try {
        $tempFile = tempnam(sys_get_temp_dir(), 'custom_action_import_');
        file_put_contents($tempFile, $csvData);

        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Custom Action Import: Could not open temporary CSV file");
            return false;
        }

        $header = fgetcsv($handle, 0, ',');
        if ($header === false || empty($header)) {
            Logger::error("Custom Action Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            return false;
        }

        $headerMap = [];
        foreach ($header as $i => $colName) {
            $headerMap[strtolower(trim($colName))] = $i;
        }

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (empty($data)) {
                continue;
            }

            $codeName = customActionImportCsvGetValue($headerMap, $data, 'code_name');
            $actionName = customActionImportCsvGetValue($headerMap, $data, 'action_name');
            if ($codeName === '' || $actionName === '') {
                Logger::warn("Custom Action Import: Skipping row with missing code_name or action_name");
                $errorCount++;
                continue;
            }

            $errorMessage = '';
            $parameters = customActionImportDecodeJsonField(
                customActionImportCsvGetValue($headerMap, $data, 'parameters_json', ''),
                ['type' => 'object', 'properties' => [], 'required' => []],
                $errorMessage,
                'parameters_json'
            );
            if ($parameters === null) {
                Logger::error("Custom Action Import: {$codeName} - {$errorMessage}");
                $errorCount++;
                continue;
            }

            $metadata = customActionImportDecodeJsonField(
                customActionImportCsvGetValue($headerMap, $data, 'metadata', ''),
                [],
                $errorMessage,
                'metadata'
            );
            if ($metadata === null) {
                Logger::error("Custom Action Import: {$codeName} - {$errorMessage}");
                $errorCount++;
                continue;
            }

            $scriptProxyRaw = customActionImportCsvGetValue($headerMap, $data, 'script_proxy_program', '');
            $scriptProxyProgram = customActionImportDecodeJsonField(
                $scriptProxyRaw,
                null,
                $errorMessage,
                'script_proxy_program'
            );
            if ($scriptProxyProgram === null && trim($scriptProxyRaw) !== '') {
                Logger::error("Custom Action Import: {$codeName} - {$errorMessage}");
                $errorCount++;
                continue;
            }

            $incomingImportVersion = herikaActionCatalogNormalizeImportVersion(
                customActionImportCsvGetValue($headerMap, $data, 'import_version', '0')
            );

            $metadataSource = trim(strval($metadata['source'] ?? ''));
            if ($metadataSource === '') {
                $metadataSource = trim(strval($metadata['bridge_script'] ?? ''));
            }
            if ($metadataSource === '' && $filename !== '') {
                $metadataSource = pathinfo($filename, PATHINFO_FILENAME);
            }
            if ($metadataSource === '') {
                $metadataSource = 'custom_action_import';
            }
            $metadata['source'] = $metadataSource;
            $metadata['import_type'] = 'custom_action_import';
            $metadata['import_version'] = $incomingImportVersion;
            if ($filename !== '') {
                $metadata['import_filename'] = $filename;
            }
            if (!isset($metadata['dispatch']) || trim(strval($metadata['dispatch'])) === '') {
                $metadata['dispatch'] = $scriptProxyProgram !== null ? 'script_proxy' : 'plugin_command';
            }
            if (!array_key_exists('builtin', $metadata)) {
                $metadata['builtin'] = false;
            }
            if (!isset($metadata['status']) || trim(strval($metadata['status'])) === '') {
                $metadata['status'] = 'active';
            }

            if (herikaIsDeprecatedCampfireActionImport($codeName, $metadata, $filename)) {
                herikaDeleteDeprecatedCampfireActionRows($codeName);
                Logger::warn("Custom Action Import: Skipping deprecated CHIM-Campfire action '{$codeName}'");
                $skippedCount++;
                continue;
            }

            $row = [
                'code_name' => $codeName,
                'action_name' => $actionName,
                'description' => customActionImportCsvGetValue($headerMap, $data, 'description', ''),
                'return_message' => customActionImportCsvGetValue($headerMap, $data, 'return_message', ''),
                'available_to_npc' => customActionImportCsvToBool(
                    customActionImportCsvGetValue($headerMap, $data, 'available_to_npc', '0'),
                    false
                ),
                'available_to_followers' => customActionImportCsvToBool(
                    customActionImportCsvGetValue($headerMap, $data, 'available_to_followers', '0'),
                    false
                ),
                'available_to_narrator' => customActionImportCsvToBool(
                    customActionImportCsvGetValue($headerMap, $data, 'available_to_narrator', '0'),
                    false
                ),
                'is_activated' => customActionImportCsvToBool(
                    customActionImportCsvGetValue($headerMap, $data, 'is_activated', '1'),
                    true
                ),
                'game_function' => customActionImportCsvToBool(
                    customActionImportCsvGetValue($headerMap, $data, 'game_function', '1'),
                    true
                ),
                'parameters_json' => $parameters,
                'metadata' => $metadata,
                'import_version' => $incomingImportVersion,
                'script_proxy_program' => $scriptProxyProgram,
            ];

            $existingImportVersion = herikaActionCatalogGetExistingCustomImportVersion($codeName);
            if ($existingImportVersion !== null
                && !herikaActionCatalogShouldOverwriteImportVersion($incomingImportVersion, $existingImportVersion)
            ) {
                Logger::info(
                    "Custom Action Import: Skipping '{$codeName}' because import_version {$incomingImportVersion} is not newer than existing {$existingImportVersion}"
                );
                $skippedCount++;
                $processedCodeNames[] = $codeName;
                continue;
            }

            if (herikaActionCatalogUpsertCustomRow($row)) {
                $processedCount++;
                $processedCodeNames[] = $codeName;
            } else {
                Logger::error("Custom Action Import: Failed to upsert action '{$codeName}'");
                $errorCount++;
            }
        }

        if ($filename !== '' && $errorCount === 0) {
            $literalFilename = herikaActionCatalogSqlText($filename);
            $staleFilter = '';
            if (count($processedCodeNames) > 0) {
                $literalCodes = array_map('herikaActionCatalogSqlText', array_values(array_unique($processedCodeNames)));
                $staleFilter = ' AND code_name NOT IN (' . implode(',', $literalCodes) . ')';
            }

            $db->execQuery("
                DELETE FROM public.core_action_custom
                WHERE COALESCE(metadata->>'import_type', '') = 'custom_action_import'
                  AND COALESCE(metadata->>'import_filename', '') = {$literalFilename}
                  {$staleFilter}
            ");
        }

        if (strcasecmp($filename, 'campfire_actions.csv') === 0) {
            herikaDeleteDeprecatedCampfireActionRows();
        }

        fclose($handle);
        unlink($tempFile);

        Logger::info("Custom Action Import: Processing complete. $processedCount records processed, $skippedCount skipped, $errorCount errors");

        $summaryPrefix = ($filename !== '') ? "file={$filename}; " : '';
        herikaLogCsvImportAuditEvent(
            'custom_action_import',
            $summaryPrefix . "$processedCount records processed, $skippedCount skipped, $errorCount errors",
            $timestamp,
            $game_timestamp
        );
    } catch (Exception $e) {
        $hadFatalError = true;
        Logger::error("Custom Action Import: Fatal error processing CSV: " . $e->getMessage());
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }

        herikaLogCsvImportAuditEvent(
            'custom_action_import',
            "failed: " . $e->getMessage(),
            $timestamp,
            $game_timestamp
        );
    }

    return !$hadFatalError;
}

function herikaIsDeprecatedCampfireActionImport($codeName, array $metadata, $filename = '')
{
    $normalizedCodeName = strtolower(trim(strval($codeName)));
    if (str_starts_with($normalizedCodeName, 'extcmdchimcampfire_')) {
        return true;
    }

    $normalizedFilename = strtolower(trim(strval($filename)));
    if ($normalizedFilename === 'campfire_actions.csv') {
        return true;
    }

    $source = strtolower(trim(strval($metadata['source'] ?? '')));
    if ($source === 'chim-campfire') {
        return true;
    }

    $integration = strtolower(trim(strval($metadata['integration'] ?? '')));
    if ($integration === 'campfire') {
        return true;
    }

    $bridgeScript = strtolower(trim(strval($metadata['bridge_script'] ?? '')));
    return $bridgeScript === 'chimcampfire';
}

function herikaDeleteDeprecatedCampfireActionRows($codeName = null)
{
    global $db;

    $codeNameFilter = '';
    if ($codeName !== null && trim(strval($codeName)) !== '') {
        $literalCodeName = herikaActionCatalogSqlText($codeName);
        $codeNameFilter = " OR LOWER(code_name) = LOWER({$literalCodeName})";
    }

    $db->execQuery("
        DELETE FROM public.core_action_custom
        WHERE LOWER(code_name) LIKE 'extcmdchimcampfire_%'
           OR COALESCE(LOWER(metadata->>'source'), '') = 'chim-campfire'
           OR COALESCE(LOWER(metadata->>'integration'), '') = 'campfire'
           OR COALESCE(LOWER(metadata->>'bridge_script'), '') = 'chimcampfire'
           OR COALESCE(LOWER(metadata->>'import_filename'), '') = 'campfire_actions.csv'
           {$codeNameFilter}
    ");
}

?>
