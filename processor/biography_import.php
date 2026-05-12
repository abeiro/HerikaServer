<?php
/**
 * Safe Biography CSV Import Handler
 * Handles CSV data from game plugin or web upload
 * Supports UTF-8 (with/without BOM), UTF-16LE/BE, Windows-1252
 * Auto-detects delimiters: comma, semicolon, tab
 */

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

try {
    // Step 1: Detect and normalize encoding to UTF-8
    $detectedEncoding = 'UTF-8';
    
    // Check for UTF-16 (presence of NUL bytes)
    if (strpos($csvData, "\x00") !== false) {
        $bom = substr($csvData, 0, 2);
        if ($bom === "\xFF\xFE") {
            $csvData = mb_convert_encoding(substr($csvData, 2), 'UTF-8', 'UTF-16LE');
            $detectedEncoding = 'UTF-16LE';
        } elseif ($bom === "\xFE\xFF") {
            $csvData = mb_convert_encoding(substr($csvData, 2), 'UTF-8', 'UTF-16BE');
            $detectedEncoding = 'UTF-16BE';
        } else {
            $csvData = mb_convert_encoding($csvData, 'UTF-8', 'UTF-16');
            $detectedEncoding = 'UTF-16';
        }
    }
    
    // Strip UTF-8 BOM if present
    if (substr($csvData, 0, 3) === "\xEF\xBB\xBF") {
        $csvData = substr($csvData, 3);
        $detectedEncoding = 'UTF-8 with BOM';
    }
    
    // If not valid UTF-8, try Windows-1252
    if (!mb_check_encoding($csvData, 'UTF-8')) {
        $csvData = mb_convert_encoding($csvData, 'UTF-8', 'Windows-1252');
        $detectedEncoding = 'Windows-1252';
    }
    
    Logger::info("Biography Import: Detected encoding: $detectedEncoding");
    
    // Step 2: Split into lines
    $lines = preg_split('/\r\n|\r|\n/', $csvData);
    if (empty($lines) || count($lines) < 2) {
        Logger::error("Biography Import: CSV appears empty or has no data rows");
        die("X-CUSTOM-CLOSE");
    }
    
    // Step 3: Detect delimiter from first few lines
    $firstLine = $lines[0];
    $delimiter = ',';
    
    // Count delimiters in first line
    $delimiterCounts = [
        ',' => substr_count($firstLine, ','),
        ';' => substr_count($firstLine, ';'),
        "\t" => substr_count($firstLine, "\t")
    ];
    
    // If all counts are 0, check the second line too
    $maxCount = max($delimiterCounts);
    if ($maxCount === 0 && isset($lines[1])) {
        $secondLine = $lines[1];
        $delimiterCounts[','] += substr_count($secondLine, ',');
        $delimiterCounts[';'] += substr_count($secondLine, ';');
        $delimiterCounts["\t"] += substr_count($secondLine, "\t");
    }
    
    arsort($delimiterCounts);
    $delimiter = array_key_first($delimiterCounts);
    $delimiterName = ($delimiter === "\t") ? 'TAB' : $delimiter;
    Logger::info("Biography Import: Detected delimiter: $delimiterName (counts: comma={$delimiterCounts[',']}, semicolon={$delimiterCounts[';']}, tab={$delimiterCounts["\t"]})");
    
    // Step 4: Parse header row
    $header = str_getcsv($firstLine, $delimiter, '"');
    if (!$header || empty($header)) {
        Logger::error("Biography Import: Could not parse header row");
        die("X-CUSTOM-CLOSE");
    }
    
    // Step 5: Normalize headers and build map
    $headerMap = [];
    foreach ($header as $i => $colName) {
        $colNameSafe = is_string($colName) ? $colName : '';
        // Clean up: strip BOM, NBSP, ZWNBSP, normalize whitespace
        $colNameSafe = preg_replace('/[\x{FEFF}\x{FFFE}\x{00A0}]/u', ' ', $colNameSafe);
        $colNameSafe = preg_replace('/\s+/', ' ', $colNameSafe);
        $normalized = strtolower(trim($colNameSafe));
        if ($normalized !== '') {
            $headerMap[$normalized] = $i;
        }
    }
    
    // Step 6: Validate required headers
    $requiredHeaders = ['npc_name', 'core'];
    $missingHeaders = array_diff($requiredHeaders, array_keys($headerMap));
    if (!empty($missingHeaders)) {
        Logger::error("Biography Import: Missing required headers: " . implode(', ', $missingHeaders));
        die("X-CUSTOM-CLOSE");
    }
    
    // Step 7: Process data rows
    for ($lineIdx = 1; $lineIdx < count($lines); $lineIdx++) {
        $lineContent = $lines[$lineIdx];
        
        // Skip empty lines
        if (trim($lineContent) === '') {
            continue;
        }
        
        // Parse CSV line
        $data = str_getcsv($lineContent, $delimiter, '"');
        if (!$data || empty($data)) {
            continue;
        }
        
        // Skip duplicate header rows
        if (isset($data[0], $data[1])) {
            if (strtolower(trim($data[0])) === 'npc_name' && strtolower(trim($data[1])) === 'core') {
                continue;
            }
        }
        
        // Helper function to safely extract values
        $getValue = function($key) use ($headerMap, $data) {
            if (isset($headerMap[$key]) && isset($data[$headerMap[$key]])) {
                $temp = trim((string)$data[$headerMap[$key]]);
                return ($temp !== '') ? $temp : null;
            }
            return null;
        };
        
        // Extract required fields
        $npc_name = isset($headerMap['npc_name']) && isset($data[$headerMap['npc_name']]) 
            ? strtolower(trim((string)$data[$headerMap['npc_name']])) 
            : '';
        
        // Support both 'core' and legacy 'npc_pers'
        $core = $getValue('core') ?? $getValue('npc_pers') ?? '';
        
        // Skip if required fields are empty
        if (empty($npc_name) || empty($core)) {
            Logger::warn("Biography Import: Skipping row with missing npc_name or core");
            $errorCount++;
            continue;
        }
        
        // Truncate npc_name to 128 characters
        if (strlen($npc_name) > 128) {
            $npc_name = substr($npc_name, 0, 128);
        }
        
        // Extract optional fields (support both new and legacy headers)
        $oghma_knowledge_tags = $getValue('oghma_knowledge_tags') ?? $getValue('npc_misc') ?? '';
        $voiceid = $getValue('voiceid');
        $gender = $getValue('gender');
        $race = $getValue('race');
        $refid = $getValue('refid');
        
        // Extended profile fields (support both new and legacy npc_* prefixed headers)
        $npc_static_bio = $getValue('npc_static_bio') ?? $getValue('npc_background');
        $personality = $getValue('personality') ?? $getValue('npc_personality');
        $appearance = $getValue('appearance') ?? $getValue('npc_appearance');
        $relationships = $getValue('relationships') ?? $getValue('npc_relationships');
        $occupation = $getValue('occupation') ?? $getValue('npc_occupation');
        $skills = $getValue('skills') ?? $getValue('npc_skills');
        $speechstyle = $getValue('speechstyle') ?? $getValue('npc_speechstyle');
        $goals = $getValue('goals') ?? $getValue('npc_goals');

        $relationshipError = '';
        $relationships = chimNormalizeBiographyRelationshipSeed($relationships, $relationshipError);
        if ($relationships === false) {
            Logger::error(
                "Biography Import: NPC '$npc_name' has invalid relationships field: $relationshipError"
            );
            $errorCount++;
            continue;
        }
        
        // Insert or update record using upsertRowOnConflict
        try {
            $db->upsertRowOnConflict(
                'bio_templates_custom',
                array(
                    'npc_name' => $npc_name,
                    'core' => $core,
                    'oghma_knowledge_tags' => $oghma_knowledge_tags,
                    'npc_static_bio' => $npc_static_bio,
                    'personality' => $personality,
                    'appearance' => $appearance,
                    'relationships' => $relationships,
                    'occupation' => $occupation,
                    'skills' => $skills,
                    'speechstyle' => $speechstyle,
                    'goals' => $goals,
                    'voiceid' => $voiceid,
                    'gender' => $gender,
                    'race' => $race,
                    'refid' => $refid
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
    
    Logger::info("Biography Import: Processing complete. $processedCount records processed, $errorCount errors");
    Logger::info("Biography Import: Encoding: $detectedEncoding, Delimiter: $delimiterName");
    
} catch (Exception $e) {
    Logger::error("Biography Import: Fatal error processing CSV: " . $e->getMessage());
}

?>
