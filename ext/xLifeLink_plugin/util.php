<?php

/**
 * Read from new table with json personalities 
 * @param mixed $npcName
 * @return mixed
 */
function getJSONPersonality($npcName) {
    $codename=addslashes(strtr(strtolower(trim($npcName)),[" "=>"_","'"=>"+"]));
    $jsonPersonality = $GLOBALS["db"]->fetchAll("SELECT personality FROM json_personalities where npc_name='$codename'");
    if(isset($jsonPersonality) && isset($jsonPersonality[0])) {
        return json_decode($jsonPersonality[0]["personality"], true);
    }

    return null;
}

/**
 * Alter in php file variable with name $variableName assigning $newValue
 * @param mixed $fileContents
 * @param mixed $variableName
 * @param mixed $newValue
 * @return mixed
 */
function updatePhpVariable($fileContents, $variableName, $newValue) {
    // Escape special characters for the new value
    $escapedValue = addslashes($newValue);

    // Regex pattern to match the variable assignment
    $pattern = '/\$' . preg_quote($variableName) . '\s*=\s*(\'(?:\\\'|[^\'])*\'|"(?:\"|[^"])*");/';

    // Replace the matched variable with the new value
    $newContents = preg_replace($pattern, "\$$variableName='$escapedValue';", $fileContents) ?? $fileContents;

    return $newContents;
}

function buildPersonalityLine($name, $value) {
    return $value ? "$name: $value;\n" : "";
}

function buildPersonalityLineList($name, $stringsArr) {
    if (!isset($stringsArr) || count($stringsArr) === 0) {
        return "";
    }

    return "$name: " . implode(", ", $stringsArr) . ";\n";
}

/**
 * Combine all parts of json personality properties in correct order
 * @param mixed $dynamicParts
 * @param mixed $static
 * @return string
 */
function buildPersonality($dynamicParts, $static) {
    return "$static\n{$dynamicParts["needs"]}\n{$dynamicParts["desires"]}\n{$dynamicParts["relationships"]}";
}

/**
 * Parse LLM return for update personality prompt. Analyze 3 parts of possible changes in relationships, needs and desires.
 * @param mixed $npcName
 * @param mixed $updatedPers
 * @return array
 */
function parseUpdate($npcName, $updatedPers) {
    $data = [
        "relationships" => [],
        "needs" => "",
        "desires" => ""
    ];

    $lines = explode("\n", trim($updatedPers));
    $currentSection = "";
    $currentRelationships = getCurrentRelationships();

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

        // replace any formatting characters from LLM response
        $line = preg_replace('/[*#]/u', '', $line);
    
        // Parse based on the current section
        if ($currentSection === "relationships") {
            // todo for relationships names refine logic to handle partial names like Lynly Star-Sung - Lynly, or Sibbi Black-Briar - Sibbi
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

                /**
                 * take out entry from current relationships array if it was updated
                 */
                if(array_key_exists($name, $currentRelationships) && strpos(strtolower($description), "no change") == false) {
                    unset($currentRelationships[$name]);
                }
                $data["relationships"][] = ["name" => $name, "description" => $description];
            }
        } elseif ($currentSection === "needs") {
            $data["needs"] = $line;
        } elseif ($currentSection === "desires") {
            $data["desires"] = $line;
        }
    }

    // add remaining current relationships which weren't updated and for some reason not included in LLM response or were added with some nonsense like "no change"
    foreach ($currentRelationships as $name => $description) {
        error_log("Added from previous relationships $name");
        $data["relationships"][] = ["name" => $name, "description" => $description];
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

function getCurrentRelationships() {
    $personality = $GLOBALS["HERIKA_PERS"];

    /**
     * Extract just the part under the "relationships:" heading (including the hyphen lines).
     * The pattern tries to capture everything after "relationships:" up until the next blank line
     * or the end of the text.
     */
    $patternBlock = '/relationships:\s*((?:-[^\r\n]*[\r\n])+)/i';
    if (preg_match($patternBlock, $personality, $blockMatch)) {
        $relationshipsBlock = $blockMatch[1];
        
        /**
         * For each line that starts with a dash (e.g. "- Name: Description"),
         * capture everything up to the first colon as "name" and whatever follows as "description".
         */
        $patternLine = '/-\s*([^:]+)\s*:\s*(.*)/';
        if (preg_match_all($patternLine, $relationshipsBlock, $lineMatches, PREG_SET_ORDER)) {
            $relationships = [];
            foreach ($lineMatches as $m) {
                $name = trim($m[1]);
                $description = trim($m[2]);
                
                $relationships[$name] = $description;
            }
        }
    }

    return $relationships;
}
