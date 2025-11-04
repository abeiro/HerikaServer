<?php

// Helper functions for openrouterjsoncached connector

/**
 * Logs messages to a specified log file
 */
function logMessage(string|array $message, ?string $context = null, string $level = 'INFO', string $logFile = 'cache.log'): bool
{
    $timestamp = date('Y-m-d H:i:s');
    if ($message == null) {
        $logEntry = "[{$timestamp}] {$level}: Null message\n";
        $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        return true;
    }

    $logFile = __DIR__ . "/../log/" . $logFile;
    $formattedMessage = '';

    if (is_array($message)) {
        $jsonMessage = json_encode($message, JSON_PRETTY_PRINT);
        if ($jsonMessage === false) {
            $jsonMessage = "Failed to encode array to JSON. Original: " . print_r($message, true);
        }

        if ($context !== null && $context !== '') {
            $formattedMessage = "{$context} \n {$jsonMessage}";
        } else {
            $formattedMessage = $jsonMessage;
        }
    } else {
        $formattedMessage = (string) $message;
    }

    $logEntry = "[{$timestamp}] {$level}: {$formattedMessage}\n";
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    if ($result === false) {
        error_log("Failed to write to log file: {$logFile}. Original message: " . (is_array($message) ? json_encode($message) : $message));
        return false;
    }

    return true;
}

/**
 * Remove duplicate memory entries from array
 */
function removeDuplicateMemories($array) {
    $seenMemories = [];
    $filteredArray = [];

    foreach ($array as $index => $item) {
        if (isset($item['text']) && strpos($item['text'], '#MEMORY:') === 0) {
            $memoryText = $item['text'];
            $normalizedMemory = preg_replace('/\s+/', ' ', trim($memoryText));

            if (!isset($seenMemories[$normalizedMemory])) {
                $seenMemories[$normalizedMemory] = true;
                $filteredArray[] = $item;
            }
        } else {
            $filteredArray[] = $item;
        }
    }

    logMessage("Memories:");
    logMessage($seenMemories);
    return $filteredArray;
}

/**
 * Count tokens by word count (rough estimation)
 */
function countTokensByWords($array) {
    $totalTokens = 0;

    foreach ($array as $item) {
        if (isset($item['text'])) {
            $words = preg_split('/\s+/', trim($item['text']), -1, PREG_SPLIT_NO_EMPTY);
            $totalTokens += count($words);
        }
    }

    return $totalTokens;
}

/**
 * Remove neighboring duplicate entries
 */
function removeNeighboringDuplicates($array, &$duplicatesRemoved)
{
    if (empty($array)) {
        return $array;
    }

    $result = [$array[0]];
    $duplicatesRemoved = 0;

    for ($i = 1; $i < count($array); $i++) {
        if (!arraysEqual($array[$i], $array[$i - 1])) {
            $result[] = $array[$i];
        } else {
            $duplicatesRemoved++;
        }
    }

    return $result;
}

/**
 * Compare two arrays for equality
 */
function arraysEqual($array1, $array2)
{
    return json_encode($array1) === json_encode($array2);
}

/**
 * Check if string contains only symbols (no text)
 */
function containsOnlySymbols(string $str): bool
{
    return (bool) preg_match('/^[\n\t\r\f\v!@#$%^&*()_+\-=\[\]{};\':"|,.<>\/?`~]+$/', $str);
}

/**
 * Check if a value is "lazy empty" (empty, null, none, etc.)
 */
function lazyEmpty($string)
{
    if (empty(trim($string)))
        return true;

    $trimmed = trim($string);
    if ($trimmed == "Null" || $trimmed == "null" || $trimmed == "None" || $trimmed == "none")
        return true;

    return false;
}

/**
 * Write array to file with cache checking (for system prompts)
 */
function writeArrayToFileWithCache($array, $filename, $cacheHours = 1)
{
    $filename = __DIR__ . "/../temp/" . $filename;
    $directory = dirname($filename);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception("Failed to create directory: " . $directory);
        }
    }

    if (file_exists($filename)) {
        $fileModTime = filemtime($filename);
        $currentTime = time();
        $cacheExpiry = $cacheHours * 3600;

        if (($currentTime - $fileModTime) < $cacheExpiry) {
            $fileContents = file_get_contents($filename);
            if ($fileContents !== false) {
                $cachedArray = unserialize($fileContents);
                if ($cachedArray !== false) {
                    touch($filename);
                    logMessage("Return cached System entry.");
                    return $cachedArray;
                }
            }
        }
    }

    $serializedArray = serialize($array);
    $result = file_put_contents($filename, $serializedArray);

    if ($result === false) {
        throw new Exception("Failed to write array to file: " . $filename);
    }

    logMessage("Return un-cached System entry.");
    return $array;
}

/**
 * Manage character event list with caching (for dialogue history)
 */
function manageCharacterEventList($newList, $filename = 'conversation_list.json', $maxLength = 93, $maxAge = 3600)
{
    logMessage("Max length of cached event history: $maxLength");
    $filename = __DIR__ . "/../temp/" . $filename;
    $directory = dirname($filename);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception("Failed to create directory: " . $directory);
        }
    }

    $existingList = [];
    if (file_exists($filename)) {
        $fileContent = file_get_contents($filename);
        if ($fileContent !== false) {
            $decoded = json_decode($fileContent, true);
            if ($decoded !== null) {
                $existingList = $decoded;
            }
        }

        $fileModTime = filemtime($filename);
        $currentTime = time();
        $fileAge = $currentTime - $fileModTime;
        if ($fileAge >= $maxAge) {
            logMessage("cleared cache because it is older than one hour");
            $existingList = [];
        }
    }

    if (count($existingList) >= $maxLength) {
        if (file_exists($filename)) {
            unlink($filename);
        }
        logMessage("cleared cached dialogue");
        $existingList = [];
    }

    $newElements = [];
    foreach ($newList as $newItem) {
        $found = false;
        foreach ($existingList as $existingItem) {
            if (arraysEqual($newItem, $existingItem)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $newElements[] = $newItem;
        }
    }

    $updatedList = array_merge($existingList, $newElements);

    $duplicatesRemoved = 0;
    $updatedList = removeNeighboringDuplicates($updatedList, $duplicatesRemoved);
    logMessage("Duplicates removed: $duplicatesRemoved");

    $updatedListCount = count($updatedList);

    file_put_contents($filename, json_encode($updatedList, JSON_PRETTY_PRINT));
    logMessage("Current length of cached event history: $updatedListCount");

    return [
        'updated_list' => $updatedList,
        'existing_list' => $existingList,
        'new_elements' => $newElements,
        'duplicatesRemoved' => $duplicatesRemoved,
        'new_count' => count($newElements)
    ];
}

/**
 * Extract and remove a section from text (markdown-style headers)
 */
function extract_and_remove_section(&$text, $section_name)
{
    $pattern = '/(^# ' . preg_quote($section_name, '/') . '.*?)(?=^# |\z)/msi';

    if (preg_match($pattern, $text, $matches)) {
        $text = preg_replace($pattern, '', $text, 1);
        return trim($matches[1]);
    } else {
        return '';
    }
}

/**
 * Extract any subsection (### level headers)
 */
function extract_any_subsection(&$source, $subsectionName, $extractAll = false) {
    $pattern = '/(^### ' . preg_quote($subsectionName, '/') . '.*?)(?=^###|^##|^# |\z)/msi';

    if ($extractAll) {
        if (preg_match_all($pattern, $source, $matches)) {
            $extracted = [];
            foreach ($matches[1] as $match) {
                $extracted[] = trim($match);
            }
            $source = preg_replace($pattern, '', $source);
            $combinedContent = implode("\n\n", $extracted);
            return $subsectionName . ":\n" . $combinedContent;
        }
    } else {
        if (preg_match($pattern, $source, $matches)) {
            $extracted = trim($matches[1]);
            $source = preg_replace($pattern, '', $source, 1);
            return $subsectionName . ":\n" . $extracted;
        }
    }
}

/**
 * Extract specific section (## level headers)
 */
function extract_specific_section(&$source, $sectionHeader)
{
    $pattern = '/##+#\s*' . preg_quote($sectionHeader) . '\s*\R[\s\S]*?(?=\R#|$)/';

    if (preg_match($pattern, $source, $matches)) {
        $extracted = $matches[0];
        $source = preg_replace($pattern, '', $source, 1);
        return $extracted;
    }
    return null;
}

/**
 * Extract JSON from text buffer
 */
function extractJson($text)
{
    $start = strpos($text, '{');
    if ($start === false) {
        return $text;
    }

    $braceCount = 0;
    $inString = false;
    $escaped = false;

    for ($i = $start; $i < strlen($text); $i++) {
        $char = $text[$i];

        if (!$inString) {
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            } elseif ($char === '"') {
                $inString = true;
            }
        } else {
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === '"') {
                $inString = false;
            }
        }
    }

    return $text;
}

/**
 * Get the last user message speaker from context
 */
function getLastUserMessageSpeaker($contextData) {
    for ($i = count($contextData) - 1; $i >= 0; $i--) {
        if (!isset($contextData[$i]['role']) || $contextData[$i]['role'] !== 'user') {
            continue;
        }

        $content = '';
        if (is_string($contextData[$i]['content'])) {
            $content = $contextData[$i]['content'];
        } elseif (is_array($contextData[$i]['content']) &&
                  isset($contextData[$i]['content'][0]['text'])) {
            $content = $contextData[$i]['content'][0]['text'];
        }

        if (preg_match('/^([^:]+):/', trim($content), $matches)) {
            return trim($matches[1]);
        }
    }

    return isset($GLOBALS["PLAYER_NAME"]) ? $GLOBALS["PLAYER_NAME"] : 'Player';
}

/**
 * Build simple format instruction based on enabled features
 */
function buildSimpleFormatInstruction($includeMood, $includeListener, $includeActions, $includeTarget, $customInstruction = '') {
    $parts = [];

    if ($includeMood) $parts[] = 'mood';
    if ($includeListener) $parts[] = 'listener';
    if ($includeActions) $parts[] = 'action';
    if ($includeTarget) $parts[] = 'target';

    if (empty($parts)) {
        return $customInstruction . " Respond naturally with your dialogue.";
    }

    $formatExample = '(' . implode(')(', $parts) . ')';

    $instruction = "Begin your response by noting your ";
    $descriptions = [];
    if ($includeMood) $descriptions[] = "emotional state";
    if ($includeListener) $descriptions[] = "who you're speaking to";
    if ($includeActions) $descriptions[] = "intended action";
    if ($includeTarget) $descriptions[] = "action target";

    $instruction .= implode(", ", $descriptions);
    $instruction .= " in parentheses like this: {$formatExample}, then provide your dialogue naturally. ";

    if ($includeMood && isset($GLOBALS["EMOTEMOODS"]) && !empty($GLOBALS["EMOTEMOODS"])) {
        $instruction .= "Valid moods: " . $GLOBALS["EMOTEMOODS"] . ". ";
    }

    $exampleParts = [];
    if ($includeMood) $exampleParts[] = "neutral";
    if ($includeListener) $exampleParts[] = "Player";
    if ($includeActions) $exampleParts[] = "Talk";
    if ($includeTarget) $exampleParts[] = "Lydia";

    $exampleFormat = '(' . implode(')(', $exampleParts) . ')';
    $instruction .= "Example: {$exampleFormat} I'm worried about that cave we passed.";

    return $instruction;
}

/**
 * Extract simple format from buffer
 */
function extractSimpleFormatFromBuffer($buffer, $includeMood, $includeListener, $includeActions, $includeTarget) {
    $groupCount = 0;
    if ($includeMood) $groupCount++;
    if ($includeListener) $groupCount++;
    if ($includeActions) $groupCount++;
    if ($includeTarget) $groupCount++;

    if ($groupCount === 0) {
        return [
            'mood' => '',
            'listener' => '',
            'action' => 'Talk',
            'target' => '',
            'message' => $buffer,
            'found' => true
        ];
    }

    $groupPattern = str_repeat('\(([^)]+)\)', $groupCount);
    $pattern = '/^\s*' . $groupPattern . '\s*(.*)$/s';

    if (preg_match($pattern, $buffer, $matches)) {
        $groups = [];
        for ($i = 1; $i <= $groupCount; $i++) {
            $groups[] = $matches[$i];
        }
        $message = $matches[$groupCount + 1];

        $result = [
            'mood' => '',
            'listener' => '',
            'action' => 'Talk',
            'target' => '',
            'message' => trim($message),
            'found' => true
        ];

        $groupIndex = 0;
        if ($includeMood && isset($groups[$groupIndex])) {
            $result['mood'] = trim($groups[$groupIndex]);
            $groupIndex++;
        }
        if ($includeListener && isset($groups[$groupIndex])) {
            $result['listener'] = trim($groups[$groupIndex]);
            $groupIndex++;
        }
        if ($includeActions && isset($groups[$groupIndex])) {
            $result['action'] = trim($groups[$groupIndex]);
            $groupIndex++;
        }
        if ($includeTarget && isset($groups[$groupIndex])) {
            $result['target'] = trim($groups[$groupIndex]);
            $groupIndex++;
        }

        return $result;
    }

    return ['found' => false];
}

/**
 * Validate action name against known actions
 */
function validateActionName($action) {
    $validActions = [
        'Talk', 'Think', 'Attack', 'Cast', 'CastSpell', 'Use', 'UseItem',
        'Equip', 'Unequip', 'Follow', 'Wait', 'Trade', 'Give', 'Take',
        'Open', 'Close', 'Activate', 'Search', 'Sit', 'Sleep', 'Eat', 'Drink'
    ];

    $action = trim($action);

    foreach ($validActions as $valid) {
        if (strcasecmp($action, $valid) === 0) {
            return $valid;
        }
    }

    logMessage("Invalid action '$action' detected, defaulting to 'Talk'", null, 'ERROR');
    return 'Talk';
}
