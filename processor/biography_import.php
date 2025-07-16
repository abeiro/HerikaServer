<?php

Logger::info("Processing biography CSV data upload");

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
            Logger::info("Biography Import: Successfully processed NPC: $npc_name");
        } catch (Exception $e) {
            Logger::error("Biography Import: Error processing NPC '$npc_name': " . $e->getMessage());
            $errorCount++;
        }
    }
    
    fclose($handle);
    unlink($tempFile);
    
    Logger::info("Biography Import: Processing complete. $processedCount records processed, $errorCount errors");
    
} catch (Exception $e) {
    Logger::error("Biography Import: Fatal error processing CSV: " . $e->getMessage());
    // Clean up temp file if it exists
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
}

?>