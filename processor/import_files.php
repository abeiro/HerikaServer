<?php

/* CSV Import Processor - Called by csv_import.php endpoint
 * Handles three types of CSV imports:
 * - biography_import: NPC character data
 * - oghma_import: Knowledge base entries
 * - dynamic_oghma_import: Quest-specific knowledge entries
 */
if (isset($_POST['csv_import']) && $_POST['csv_import'] == '1' && isset($_POST['type'])) {
    $import_type = $_POST['type'];
    $timestamp = $_POST['ts'] ?? time();
    $game_timestamp = $_POST['gamets'] ?? 0;
    
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
        default:
            Logger::error("CSV Import: Unknown import type: $import_type");
            return false;
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
        $header = fgetcsv($handle, 0, ',', '"', '"');
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
        
        // Process each data row
        while (($data = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
            if (empty($data) || count($data) < 2) {
                continue; // Skip empty or invalid rows
            }
            
            // Extract required fields
            $npc_name = '';
            if (isset($headerMap['npc_name']) && isset($data[$headerMap['npc_name']])) {
                $npc_name = strtolower(trim($data[$headerMap['npc_name']]));
            }
            
            $npc_pers = '';
            if (isset($headerMap['npc_pers']) && isset($data[$headerMap['npc_pers']])) {
                $npc_pers = trim($data[$headerMap['npc_pers']]);
            }
            
            // Extract optional fields
            $npc_dynamic = null;
            if (isset($headerMap['npc_dynamic']) && isset($data[$headerMap['npc_dynamic']])) {
                $temp = trim($data[$headerMap['npc_dynamic']]);
                $npc_dynamic = ($temp !== '') ? $temp : null;
            }
            
            $npc_misc = '';
            if (isset($headerMap['npc_misc']) && isset($data[$headerMap['npc_misc']])) {
                $npc_misc = trim($data[$headerMap['npc_misc']]);
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
            $npc_background = null;
            if (isset($headerMap['npc_background']) && isset($data[$headerMap['npc_background']])) {
                $temp = trim($data[$headerMap['npc_background']]);
                $npc_background = ($temp !== '') ? $temp : null;
            }
            
            $npc_personality = null;
            if (isset($headerMap['npc_personality']) && isset($data[$headerMap['npc_personality']])) {
                $temp = trim($data[$headerMap['npc_personality']]);
                $npc_personality = ($temp !== '') ? $temp : null;
            }
            
            $npc_appearance = null;
            if (isset($headerMap['npc_appearance']) && isset($data[$headerMap['npc_appearance']])) {
                $temp = trim($data[$headerMap['npc_appearance']]);
                $npc_appearance = ($temp !== '') ? $temp : null;
            }
            
            $npc_relationships = null;
            if (isset($headerMap['npc_relationships']) && isset($data[$headerMap['npc_relationships']])) {
                $temp = trim($data[$headerMap['npc_relationships']]);
                $npc_relationships = ($temp !== '') ? $temp : null;
            }
            
            $npc_occupation = null;
            if (isset($headerMap['npc_occupation']) && isset($data[$headerMap['npc_occupation']])) {
                $temp = trim($data[$headerMap['npc_occupation']]);
                $npc_occupation = ($temp !== '') ? $temp : null;
            }
            
            $npc_skills = null;
            if (isset($headerMap['npc_skills']) && isset($data[$headerMap['npc_skills']])) {
                $temp = trim($data[$headerMap['npc_skills']]);
                $npc_skills = ($temp !== '') ? $temp : null;
            }
            
            $npc_speechstyle = null;
            if (isset($headerMap['npc_speechstyle']) && isset($data[$headerMap['npc_speechstyle']])) {
                $temp = trim($data[$headerMap['npc_speechstyle']]);
                $npc_speechstyle = ($temp !== '') ? $temp : null;
            }
            
            $npc_goals = null;
            if (isset($headerMap['npc_goals']) && isset($data[$headerMap['npc_goals']])) {
                $temp = trim($data[$headerMap['npc_goals']]);
                $npc_goals = ($temp !== '') ? $temp : null;
            }
            
            // Skip if required fields are missing
            if (empty($npc_name) || empty($npc_pers)) {
                Logger::warn("Biography Import: Skipping row with missing npc_name or npc_pers");
                $errorCount++;
                continue;
            }
            
            // Insert or update record using upsertRowOnConflict
            try {
                $db->upsertRowOnConflict(
                    'npc_templates_custom',
                    array(
                        'npc_name' => $npc_name,
                        'npc_pers' => $npc_pers,
                        'npc_dynamic' => $npc_dynamic,
                        'npc_misc' => $npc_misc,
                        'melotts_voiceid' => $melotts_voiceid,
                        'xtts_voiceid' => $xtts_voiceid,
                        'xvasynth_voiceid' => $xvasynth_voiceid,
                        'npc_background' => $npc_background,
                        'npc_personality' => $npc_personality,
                        'npc_appearance' => $npc_appearance,
                        'npc_relationships' => $npc_relationships,
                        'npc_occupation' => $npc_occupation,
                        'npc_skills' => $npc_skills,
                        'npc_speechstyle' => $npc_speechstyle,
                        'npc_goals' => $npc_goals
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
        
        // Log the event for audit purposes
        $db->insert(
            'eventlog',
            array(
                'ts' => $timestamp,
                'gamets' => $game_timestamp,
                'type' => 'biography_import',
                'data' => "CSV upload: $processedCount records processed, $errorCount errors",
                'sess' => 'web',
                'localts' => time(),
                'people' => '',
                'location' => '',
                'party' => ''
            )
        );
        
    } catch (Exception $e) {
        Logger::error("Biography Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        // Log the error event
        $db->insert(
            'eventlog',
            array(
                'ts' => $timestamp,
                'gamets' => $game_timestamp,
                'type' => 'biography_import',
                'data' => "CSV upload failed: " . $e->getMessage(),
                'sess' => 'web',
                'localts' => time(),
                'people' => '',
                'location' => '',
                'party' => ''
            )
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
                
                // Update native_vector for search functionality
                $vectorUpdateSql = "
                    UPDATE oghma
                    SET native_vector = 
                          setweight(to_tsvector(coalesce(topic, '')), 'A')
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
        
        // Log the event for audit purposes
        $db->insert(
            'eventlog',
            array(
                'ts' => $timestamp,
                'gamets' => $game_timestamp,
                'type' => 'oghma_import',
                'data' => "CSV upload: $processedCount records processed, $errorCount errors",
                'sess' => 'web',
                'localts' => time(),
                'people' => '',
                'location' => '',
                'party' => ''
            )
        );
        
    } catch (Exception $e) {
        Logger::error("Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        // Log the error event
        $db->insert(
            'eventlog',
            array(
                'ts' => $timestamp,
                'gamets' => $game_timestamp,
                'type' => 'oghma_import',
                'data' => "CSV upload failed: " . $e->getMessage(),
                'sess' => 'web',
                'localts' => time(),
                'people' => '',
                'location' => '',
                'party' => ''
            )
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
        
        // Log the event for audit purposes
        $db->insert(
            'eventlog',
            array(
                'ts' => $timestamp,
                'gamets' => $game_timestamp,
                'type' => 'dynamic_oghma_import',
                'data' => "CSV upload: $processedCount records processed, $errorCount errors",
                'sess' => 'web',
                'localts' => time(),
                'people' => '',
                'location' => '',
                'party' => ''
            )
        );
        
    } catch (Exception $e) {
        Logger::error("Dynamic Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        // Log the error event
        $db->insert(
            'eventlog',
            array(
                'ts' => $timestamp,
                'gamets' => $game_timestamp,
                'type' => 'dynamic_oghma_import',
                'data' => "CSV upload failed: " . $e->getMessage(),
                'sess' => 'web',
                'localts' => time(),
                'people' => '',
                'location' => '',
                'party' => ''
            )
        );
    }
    
    return true;
}

?>
