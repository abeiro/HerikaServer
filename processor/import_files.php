<?php

// Biography CSV upload
if ($gameRequest[0]=="biography_import") {
    Logger::info("Biography Import: STARTED - Processing CSV data upload");
    
    // Parse the message format: biography|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Biography Import: No CSV data provided");
        die("X-CUSTOM-CLOSE");
    }
    
    $csvData = $gameRequest[4];
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'biography_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Biography Import: Could not open temporary CSV file");
            die("X-CUSTOM-CLOSE");
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Biography Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            die("X-CUSTOM-CLOSE");
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
                        'xvasynth_voiceid' => $xvasynth_voiceid
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
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
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
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
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
    
    die("X-CUSTOM-CLOSE");
}

// Oghma CSV upload
if ($gameRequest[0]=="oghma_import") {
    Logger::info("Oghma Import: STARTED - Processing CSV data upload");
    
    // Parse the message format: oghma_import|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Oghma Import: No CSV data provided");
        die("X-CUSTOM-CLOSE");
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
            die("X-CUSTOM-CLOSE");
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            die("X-CUSTOM-CLOSE");
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
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
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
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
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
    
    die("X-CUSTOM-CLOSE");
}

// Dynamic Oghma CSV upload
if ($gameRequest[0]=="dynamic_oghma_import") {
    Logger::info("Dynamic Oghma Import: STARTED - Processing CSV data upload");
    
    // Parse the message format: dynamic_oghma_import|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Dynamic Oghma Import: No CSV data provided");
        die("X-CUSTOM-CLOSE");
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
            die("X-CUSTOM-CLOSE");
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Dynamic Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            die("X-CUSTOM-CLOSE");
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
                        Logger::info("Dynamic Oghma Import: Updated existing record for quest '$id_quest' stage $stage topic '$topic'");
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
                    Logger::info("Dynamic Oghma Import: Inserted new record for quest '$id_quest' stage $stage topic '$topic'");
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
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
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
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
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
    
    die("X-CUSTOM-CLOSE");
}

?>
