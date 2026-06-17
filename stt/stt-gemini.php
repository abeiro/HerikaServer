<?php

// Gemini 2.5 Flash STT — transcription + player emotion detection in one call
// Drop-in replacement for Deepgram. Detects player vocal tone and caches it
// for context injection so NPCs react to HOW the player speaks.

$localPath = dirname(__FILE__) . '/../';
require_once($localPath . "lib/runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);
require_once($localPath . "lib/chat_helper_functions.php");

// Cache file for player tone — read by context builders
define('PLAYER_TONE_CACHE', dirname(__FILE__) . '/../soundcache/_player_tone.json');

function stt($filePath)
{
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"])
        $GLOBALS["db"] = new sql();

    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) {
        error_log("STT Gemini: warning - input file missing or unreadable! {$filePath}");
        return null;
    }

    $i_sz = filesize($filePath);
    if ($i_sz == 144046) {
        error_log("STT Gemini: warning input file {$filePath} probably contains silence.");
    }

    // Resolve API key: prefer STT conf, fallback to API Badge 'gemini' or 'google'
    $apiKey = trim($GLOBALS['STT']['GEMINI']['API_KEY'] ?? '');
    if ($apiKey === '') {
        try {
            if (isset($GLOBALS["db"]) && $GLOBALS["db"]) {
                $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='gemini' LIMIT 1");
                if (is_array($row) && !empty($row['api_key'])) $apiKey = trim($row['api_key']);
            }
        } catch (Throwable $_e) {
            error_log("STT Gemini: DB error looking up gemini badge: " . $_e->getMessage());
        }
    }
    if ($apiKey === '') {
        try {
            if (isset($GLOBALS["db"]) && $GLOBALS["db"]) {
                $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='google' LIMIT 1");
                if (is_array($row) && !empty($row['api_key'])) $apiKey = trim($row['api_key']);
            }
        } catch (Throwable $_e) {
            error_log("STT Gemini: DB error looking up google badge: " . $_e->getMessage());
        }
    }

    if (empty($apiKey)) {
        error_log("STT Gemini: No API key found. Set in STT config or add API badge with label 'gemini' or 'google'.");
        return null;
    }

    $model = !empty($GLOBALS['STT']['GEMINI']['MODEL']) ? $GLOBALS['STT']['GEMINI']['MODEL'] : 'gemini-2.5-flash';
    $lang = !empty($GLOBALS['STT']['GEMINI']['LANG']) ? $GLOBALS['STT']['GEMINI']['LANG'] : 'en';
    error_log("STT Gemini: Using model={$model} lang={$lang} key=" . substr($apiKey, 0, 10) . "...");

    // Build keyword hints from recent context (NPC names, locations, etc.)
    $keywordHints = '';
    try {
        $keywords = lastKeyWordsNew(30);
        if (!empty($keywords)) {
            $keywordHints = "\n\nThe following proper nouns may appear in the audio (use these for spelling): " . implode(', ', $keywords);
        }
    } catch (Throwable $_e) {}

    // Base64 encode the audio
    $audioBase64 = base64_encode($fileContent);

    // Build request — ask for both transcription and emotion in one shot
    $requestData = [
        'contents' => [[
            'parts' => [
                ['text' => "You are a speech-to-text system for a fantasy RPG game. Transcribe this audio accurately and detect the speaker's emotional tone from their voice.\n\nLanguage: {$lang}{$keywordHints}\n\nReturn ONLY valid JSON with exactly these two fields:\n{\"transcript\": \"the transcribed text\", \"tone\": \"one or two words describing the emotional tone of the speaker's voice\"}\n\nFor tone, use descriptive words like: calm, angry, excited, nervous, sad, happy, sarcastic, scared, commanding, playful, seductive, desperate, bored, suspicious, threatening, gentle, annoyed, confused, etc.\n\nIf the audio is silent or unintelligible, return: {\"transcript\": \"\", \"tone\": \"neutral\"}"],
                ['inline_data' => [
                    'mime_type' => 'audio/wav',
                    'data' => $audioBase64
                ]]
            ]
        ]],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 1024,
            'responseMimeType' => 'application/json'
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("STT Gemini: cURL request failed");
        return null;
    }

    if ($httpCode !== 200) {
        error_log("STT Gemini: API returned HTTP {$httpCode} for model={$model}: " . substr($response, 0, 500));
        return null;
    }

    $responseParsed = json_decode($response, true);
    error_log("STT Gemini: raw response: " . substr($response, 0, 1000));

    // Extract the generated text
    $generatedText = $responseParsed['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (empty($generatedText)) {
        error_log("STT Gemini: no generated text in response");
        return null;
    }

    // Parse the JSON response from Gemini
    $result = json_decode($generatedText, true);
    if (!is_array($result)) {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $generatedText, $m)) {
            $result = json_decode($m[1], true);
        }
        // Last resort — treat entire response as plain transcript
        if (!is_array($result)) {
            error_log("STT Gemini: could not parse JSON, using raw text as transcript");
            return trim($generatedText);
        }
    }

    $transcript = trim($result['transcript'] ?? '');
    $tone = trim($result['tone'] ?? 'neutral');

    // Cache the player's vocal tone for context injection
    if (!empty($tone) && $tone !== 'neutral') {
        $toneData = [
            'tone' => $tone,
            'timestamp' => time(),
            'transcript_preview' => substr($transcript, 0, 100)
        ];
        @file_put_contents(PLAYER_TONE_CACHE, json_encode($toneData));
        error_log("STT Gemini: Detected player tone: {$tone}");
    } else {
        // Clear stale tone cache
        @unlink(PLAYER_TONE_CACHE);
    }

    error_log("STT Gemini: transcript=\"{$transcript}\" tone=\"{$tone}\"");

    // Inject tone directly into transcript so the LLM sees it in conversation history
    if (!empty($tone) && $tone !== 'neutral' && !empty($transcript)) {
        return "({$tone}) {$transcript}";
    }
    return $transcript;
}

/**
 * Read the cached player tone (called by context builders)
 * Returns the tone string if fresh (within $maxAge seconds), null otherwise.
 *
 * @param int $maxAge Maximum age in seconds (default 60)
 * @return string|null
 */
function getPlayerTone($maxAge = 60) {
    if (!file_exists(PLAYER_TONE_CACHE)) {
        return null;
    }
    $data = json_decode(@file_get_contents(PLAYER_TONE_CACHE), true);
    if (!is_array($data) || empty($data['tone'])) {
        return null;
    }
    if ((time() - ($data['timestamp'] ?? 0)) > $maxAge) {
        return null;
    }
    return $data['tone'];
}
