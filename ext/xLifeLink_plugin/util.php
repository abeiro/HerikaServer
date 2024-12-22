<?php

function getJSONPersonality($npcName) {
    $codename=addslashes(strtr(strtolower(trim($npcName)),[" "=>"_","'"=>"+"]));
    $jsonPersonality = $GLOBALS["db"]->fetchAll("SELECT personality FROM personalities_new where npc_name='$codename'");
    if(isset($jsonPersonality) && isset($jsonPersonality[0])) {
        return json_decode($jsonPersonality[0]["personality"], true);
    }

    return null;
}

function importPersonalitiesToDB($tableName, $folderName, $createQuery, $checkDuplicatesColumns = [])
{
    $maxLineLength = 10 * 1024 * 1024; // 10 MB
    $folder = __DIR__ . DIRECTORY_SEPARATOR . $folderName;
    $importedVersionsFile = "$folder/imported.txt";
    $db = $GLOBALS['db'];
    $db->execQuery($createQuery);

    if (!is_file($importedVersionsFile)) {
        file_put_contents($importedVersionsFile, "");
    }
    $importedVersions = file($importedVersionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $importedVersions = is_array($importedVersions) ? $importedVersions : [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    // Initialize an array to store files and their associated dates
    $filesWithDates = [];

    // Loop through all files
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile()) {
            $fileName = $fileInfo->getFileName();
            error_log("Processing $fileName");
            $extension = $fileInfo->getExtension();
            $filePath = $fileInfo->getRealPath();
            if ($extension !== "csv" || in_array($fileName, $importedVersions)) {
                error_log("Not processing");
                continue;
            }
            
            // Use regex to extract the date part from the filename (assuming date format is MM_DD_YYYY)
            if (preg_match('/_(\d{2})_(\d{2})_(\d{4})\.csv$/', $fileName, $matches)) {
                // Build the date string in YYYY-MM-DD format
                $dateString = $matches[3] . '-' . $matches[1] . '-' . $matches[2];

                // Convert the date string into a DateTime object for sorting
                $date = DateTime::createFromFormat('Y-m-d', $dateString);

                // Store the file and the associated date
                $filesWithDates[] = ['fileName' => $fileName, 'filePath' => $filePath, 'date' => $date];
            }
        }
    }

    // Sort the files by the date, from earliest to latest
    usort($filesWithDates, function($a, $b) {
        return $a['date'] <=> $b['date'];
    });

    // Loop through each item in the iterator
    foreach ($filesWithDates as $fileWithDate) {
        $fileName = $fileWithDate['fileName'];
        $filePath = $fileWithDate['filePath'];
        // Check if the current item is a file (not a directory)
        error_log("Opening $filePath");                

        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, $maxLineLength, ",");
            while (($data = fgetcsv($handle, $maxLineLength, ",")) !== FALSE) {
                $insertData = [];

                foreach ($headers as $index => $header) {
                    $value = $data[$index];
                    if (!$value || $value === "\N") {
                        $value = null;
                    }
                    $insertData[$header] = $db->escape($value);
                }
                
                if (!empty($checkDuplicatesColumns)) {
                    $whereClause = [];
                    
                    foreach ($checkDuplicatesColumns as $column) {
                        $value = $db->escape($insertData[$column]);
                        if($value) {
                            $whereClause[] = "$column = '{$value}'";
                        } else {
                            $whereClause[] = "$column IS NULL";
                        }
                    }
                    
                    $whereQuery = implode(' AND ', $whereClause);
                    $checkQuery = "SELECT COUNT(*) FROM $tableName WHERE $whereQuery";
                    // $params = array_intersect_key($insertData, array_flip($checkDuplicatesColumns));
                    $result = $db->query($checkQuery);
                    $row = $db->fetchArray($result);

                    if ($row["count"] > 0) {
                        
                        // Update the row if it exists
                        $updateQuery = "UPDATE $tableName SET ";
                        $setClause = [];
                        foreach ($insertData as $column => $value) {
                            $value = $db->escape($insertData[$column]);
                            $setClause[] = "$column = '{$value}'";
                        }
                        $updateQuery .= implode(', ', $setClause);
                        $updateQuery .= " WHERE $whereQuery";
                        $db->query($updateQuery, $insertData);
                    } else {
                        // Insert the row if it does not exist
                        $db->insert($tableName, $insertData);
                    }
                } else {
                    // If no columns to check for duplicates, simply insert
                    $db->insert($tableName, $insertData);
                }
            }

            file_put_contents($importedVersionsFile, $fileName . PHP_EOL, FILE_APPEND);
        }
    }

}

function updatePhpVariable($fileContents, $variableName, $newValue) {
    error_log("updatePhpVariable: $variableName -> $newValue");
    error_log("updatePhpVariable:content: $fileContents");
    // Escape special characters for the new value
    $escapedValue = addslashes($newValue);

    // Create a regex pattern to match the variable assignment
    $pattern = '/\$' . preg_quote($variableName) . '\s*=\s*(\'(?:\\\'|[^\'])*\'|"(?:\"|[^"])*");/';

    error_log("updatePhpVariable:pattern: $pattern");

    if (preg_match($pattern, $fileContents, $matches)) {
        error_log("Match found.");
        error_log(json_encode($matches));
    } else {
        PREG_NO_ERROR;
        $errorCode = preg_last_error();
        error_log("No match found. ErrorCode: $errorCode");
    }

    // Replace the matched variable with the new value
    $newContents = preg_replace($pattern, "\$$variableName='$escapedValue';", $fileContents) ?? $fileContents;

    // error_log("updatePhpVariable:replace: $newContents");
    // error_log(json_encode(preg_replace($pattern, "\$$variableName='$escapedValue';", $fileContents)));

    return $newContents;
}

function buildPersonality($dynamicParts, $static) {
    return "$static\n{$dynamicParts["needs"]}\n{$dynamicParts["desires"]}\n{$dynamicParts["relationships"]}";
}

function parseUpdate($npcName, $updatedPers) {
    error_log("parseUpdate");
    $data = [
        "relationships" => [],
        "needs" => "",
        "desires" => ""
    ];

    $lines = explode("\n", trim($updatedPers));
    $currentSection = "";

    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) {
            continue;
        }

        // Detect the current section
        if (stripos($line, 'RELATIONSHIPS') !== false) {
            $currentSection = "relationships";
            continue;
        } elseif (stripos($line, 'NEEDS') !== false) {
            $currentSection = "needs";
            continue;
        } elseif (stripos($line, 'DESIRES') !== false) {
            $currentSection = "desires";
            continue;
        }
    
        // Parse based on the current section
        if ($currentSection === "relationships") {
            // Extract name and description for relationships
            $parts = explode(":", $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                // sometimes AI decides to put a line with updated npc name and semicolon, so we want to skip to not add relationship npc to themself
                // ignore The Narrator
                if($name === $npcName || $name === "The Narrator") {
                    continue;
                }
                $description = trim($parts[1]);
                $data["relationships"][] = ["name" => $name, "description" => $description];
            }
        } elseif ($currentSection === "needs") {
            $data["needs"] = $line;
        } elseif ($currentSection === "desires") {
            $data["desires"] = $line;
        }
    }

    $relationshipsString = "";
    foreach ($data["relationships"] as $relationship) {
        $relationshipsString .= "- {$relationship['name']}: {$relationship['description']}\n";
    }

    // Prepare new sections for needs, desires, and relationships
    $needsString = $data["needs"] ? "$npcName's needs: " . $data["needs"] : "";
    $desiresString = $data["desires"] ? "$npcName's desires: " . $data["desires"] : "";
    $relationshipsString = $relationshipsString ? "$npcName's relationships:\n" . $relationshipsString : "";
    
    return [
        "needs"=>$needsString,
        "desires"=>$desiresString,
        "relationships"=>$relationshipsString
    ];
}
