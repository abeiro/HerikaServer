<?php

/**
 * LLM-based topic extraction for Japanese language support
 * This replaces the T5-based minimeTopic function for multilingual support
 */

function LLMTopic($text, $language = 'ja') {
    $startTime = microtime(true);
    
    // Check if we have a valid Oghma connector configured
    if (!isset($GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"]) || empty($GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"])) {
        error_log("[OGHMA LLM] CORE_CONNECTOR_OGHMA_CUSTOM not configured");
        return false;
    }
    
    // Check cache first
    $cacheKey = md5("topic_extraction_" . $text);
    if (isset($GLOBALS["MINIME_TOPIC_CACHE"][$cacheKey])) {
        error_log("[OGHMA LLM] Using cached topic for: " . substr($text, 0, 50));
        return $GLOBALS["MINIME_TOPIC_CACHE"][$cacheKey];
    }
    
    try {
        // Get the Oghma-specific connector
        $connector = new LLMConnector();
        $connectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"]);
        
        if (!$connectorData) {
            error_log("[OGHMA LLM] Failed to load connector ID: " . $GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"]);
            return false;
        }
        
        $connectionHandler = $connector->getConnector($connectorData);
        
        // Load prompt from database (with fallback to global setting for backward compatibility)
        $systemPrompt = null;
        
        // First check if there's a custom prompt from global settings (backward compatibility)
        if (isset($GLOBALS["OGHMA_LLM_TOPIC_PROMPT"]) && !empty($GLOBALS["OGHMA_LLM_TOPIC_PROMPT"])) {
            $systemPrompt = $GLOBALS["OGHMA_LLM_TOPIC_PROMPT"];
            error_log("[OGHMA LLM] Using prompt from OGHMA_LLM_TOPIC_PROMPT global setting");
        } else {
            // Load from prompts table
            try {
                global $db;
                $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'custom_oghma'");
                if ($promptData) {
                    $systemPrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
                    error_log("[OGHMA LLM] Loaded prompt from database (custom: " . (!empty($promptData['custom_prompt']) ? 'yes' : 'no') . ")");
                }
            } catch (Exception $e) {
                error_log("[OGHMA LLM] Failed to load prompt from database: " . $e->getMessage());
            }
        }
        
        // Final fallback to default prompt if nothing was loaded (matches database default)
        if (empty($systemPrompt)) {
            $systemPrompt = <<<PROMPT
You are an expert at extracting important topics from text.
Follow these rules strictly:

1. Extract only ONE most important topic (person, place, item, concept, etc.) from the text
2. Ensure the output is in the **singular form** (e.g., dragons→dragon, cities→city)
3. Return ONLY the word or phrase (no explanations, no extra text)
4. If multiple candidates exist, choose the most important one
5. Keep the topic in the same language as the input text

Examples:
Input: 'I heard about dragons'
Output: dragon

Input: 'Going to Whiterun today'
Output: Whiterun

Input: 'Met with the Greybeards'
Output: Greybeard

Input: 'Used magic in combat'
Output: magic
PROMPT;
            error_log("[OGHMA LLM] Using hardcoded fallback prompt (database unavailable)");
        }
        
        $userPrompt = "Text: " . $text;
        
        // Prepare context for the connector
        $contextData = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];
        
        // Custom parameters for fast, simple extraction
        $customParms = [
            'max_tokens' => 50,  // Short response
            'temperature' => 0.3, // Low temperature for consistency
            'stream' => false     // No streaming needed
        ];
        
        // Call the LLM using the fast method
        $response = callLLMFast($contextData, $customParms);
        
        if (!$response) {
            error_log("[OGHMA LLM] Failed to get response from LLM");
            return false;
        }
        
        // Extract the topic from response
        $topic = trim($response);
        
        // Clean up the response (remove quotes, extra whitespace, etc.)
        $topic = preg_replace('/^["\']|["\']$/', '', $topic);
        $topic = preg_replace('/\s+/', ' ', $topic);
        $topic = trim($topic);
        
        // Validate the topic (should be a simple word or phrase)
        if (empty($topic) || strlen($topic) > 100) {
            error_log("[OGHMA LLM] Invalid topic extracted: " . $topic);
            return false;
        }
        
        $elapsedTime = microtime(true) - $startTime;
        
        $result = json_encode([
            'generated_tags' => $topic,
            'elapsed_time' => $elapsedTime
        ]);
        
        // Cache the result
        $GLOBALS["MINIME_TOPIC_CACHE"][$cacheKey] = $result;
        
        error_log("[OGHMA LLM] Extracted topic: '$topic' in {$elapsedTime}s from: " . substr($text, 0, 50));
        
        return $result;
        
    } catch (Exception $e) {
        error_log("[OGHMA LLM] Exception during topic extraction: " . $e->getMessage());
        return false;
    }
}

/**
 * Fast LLM call for simple tasks like topic extraction
 * Uses the Oghma-specific connector with minimal overhead
 */
function callLLMFast($contextData, $customParms = []) {
    if (!isset($GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"]) || empty($GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"])) {
        error_log("[OGHMA LLM] CORE_CONNECTOR_OGHMA_CUSTOM not configured in callLLMFast");
        return false;
    }
    
    $connector = new LLMConnector();
    $connectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_OGHMA_CUSTOM"]);
    
    if (!$connectorData) {
        error_log("[OGHMA LLM] Failed to load connector in callLLMFast");
        return false;
    }
    
    // Build the request
    $url = $connectorData["url"];
    $model = $connectorData["model"];
    $apiKeyId = $connectorData["api_badge_id"];
    
    // Get API key
    $apiBadge = new ApiBadge();
    $apiKeyData = $apiBadge->getById($apiKeyId);
    $apiKey = $apiKeyData["api_key"];
    
    // Prepare request data
    $data = [
        'model' => $model,
        'messages' => $contextData,
        'max_tokens' => $customParms['max_tokens'] ?? 50,
        'temperature' => $customParms['temperature'] ?? 0.3,
        'stream' => false
    ];
    
    // Prepare headers
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'HTTP-Referer: https://dwemerdynamics.com/',
        'X-Title: Dwemer Dynamics - Oghma Topic Extraction'
    ];
    
    // Add provider-specific headers if needed
    if (isset($connectorData["provider"]) && !empty($connectorData["provider"])) {
        $headers[] = 'X-Provider: ' . $connectorData["provider"];
    }
    
    // Make the request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Short timeout for fast extraction
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("[OGHMA LLM] HTTP error $httpCode: $response");
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        error_log("[OGHMA LLM] Invalid response format: " . substr($response, 0, 200));
        return false;
    }
    
    return $responseData['choices'][0]['message']['content'];
}

?>
