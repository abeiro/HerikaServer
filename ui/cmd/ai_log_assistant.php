<?php
/**
 * AI Log Analysis Assistant API Endpoint
 * Provides AI-powered log analysis using Claude via OpenRouter
 */

header('Content-Type: application/json');

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

$db = $GLOBALS["db"];

// Get request data
$requestData = json_decode(file_get_contents('php://input'), true);

if (!$requestData || !isset($requestData['message'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$userMessage = $requestData['message'];
$model = $requestData['model'] ?? 'anthropic/claude-sonnet-4';
$conversationHistory = $requestData['history'] ?? [];

// Fetch OpenRouter API key
$apiBadge = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE label = 'OpenRouter' LIMIT 1");
if (!$apiBadge || empty($apiBadge['api_key'])) {
    echo json_encode(['error' => 'OpenRouter API key not configured']);
    exit;
}

$apiKey = $apiBadge['api_key'];

/**
 * Execute a safe read-only database query
 */
function executeSafeQuery($db, $query) {
    // Only allow SELECT queries
    $query = trim($query);
    if (!preg_match('/^\s*SELECT/i', $query)) {
        return ['error' => 'Only SELECT queries are allowed'];
    }
    
    // Forbidden keywords
    $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'GRANT', 'REVOKE'];
    foreach ($forbidden as $keyword) {
        if (stripos($query, $keyword) !== false) {
            return ['error' => 'Query contains forbidden keyword: ' . $keyword];
        }
    }
    
    // Add LIMIT if not present
    if (stripos($query, 'LIMIT') === false) {
        $query .= ' LIMIT 100';
    }
    
    try {
        $results = $db->fetchAll($query);
        return $results;
    } catch (Exception $e) {
        return ['error' => 'Query error: ' . $e->getMessage()];
    }
}

/**
 * Read log file with size limit
 */
function readLogFile($filename, $maxLines = 500) {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR . $filename;
    
    if (!file_exists($logPath)) {
        return "Log file not found: $filename";
    }
    
    $lines = file($logPath);
    if ($lines === false) {
        return "Error reading log file: $filename";
    }
    
    // Get last N lines
    $lines = array_slice($lines, -$maxLines);
    return implode('', $lines);
}

/**
 * Get available database context
 */
function getDatabaseContext($db) {
    $context = "# Database Schema Information\n\n";
    
    // Recent audit_request entries
    $context .= "## audit_request table (LLM service logs)\n";
    $context .= "Columns: created_at, request, result, usage, url, connector\n";
    $recentRequests = $db->fetchAll("SELECT created_at, result, connector, url FROM audit_request ORDER BY created_at DESC LIMIT 10");
    $context .= "Recent entries: " . json_encode($recentRequests, JSON_PRETTY_PRINT) . "\n\n";
    
    // Recent eventlog entries
    $context .= "## eventlog table (Game events)\n";
    $context .= "Columns: ts, gamets, type, data, sess, localts, people, location, party\n";
    $recentEvents = $db->fetchAll("SELECT ts, gamets, type, data FROM eventlog ORDER BY ts DESC LIMIT 10");
    $context .= "Recent entries: " . json_encode($recentEvents, JSON_PRETTY_PRINT) . "\n\n";
    
    // LLM connectors
    $context .= "## core_llm_connector table (LLM configurations)\n";
    $context .= "Columns: id, label, metadata, url, model, provider, driver, max_tokens, temperature\n";
    
    return $context;
}

/**
 * Build comprehensive system prompt for CHIM AI assistant
 */
function buildSystemPrompt($db) {
    $prompt = <<<'SYSTEM_PROMPT'
# CHIM - AI Log Analysis Assistant

You are CHIM, an expert AI assistant for debugging and analyzing the CHIM/Herika server. You have deep knowledge of the entire system architecture and can help users diagnose issues, analyze logs, and query the database.

---

## SYSTEM OVERVIEW

**CHIM (Contextual Herika Interaction Module)** is a PHP-based server that provides AI-powered NPCs for Skyrim (and other games). It integrates with a Skyrim mod that sends game events to the server, which then uses LLMs to generate contextual NPC dialogue and actions.

### High-Level Architecture

```
[Skyrim Game + Mod Plugin] 
         ↓ (HTTP requests with game events)
[main.php - Request Router]
         ↓
[Event Processing Pipeline]
         ↓
[LLM Connector → OpenRouter/OpenAI/Local LLMs]
         ↓
[TTS Service → Voice synthesis]
         ↓
[Response Queue → back to Skyrim]
```

---

## CORE REQUEST FLOW

### 1. Game Events (main.php)
The game mod sends base64-encoded events to `main.php` in this format:
```
eventType|timestamp|gamets|eventData
```

**Event Types:**
- `inputtext` / `inputtext_s` - Player speech (voice or text input)
- `ginputtext` / `ginputtext_s` - Player speech to everyone (group conversation)
- `narrator_inputtext` - Player talking to narrator
- `init` - NPC initialization event
- `rechat` - NPC-to-NPC conversation continuation
- `funcret` - Function return (after NPC performs action)
- `diary` - Diary entry generation
- `book` - Book reading/summarization
- `bored` - Idle NPC commentary
- `combatend` - Post-combat commentary
- `quest` - Quest-related commentary
- `memory` - Memory retrieval
- `narration` - Narrator atmospheric descriptions

### 2. CHIM Modes (processor/chim_modes.php)
User can switch interaction modes:
- `STANDARD` - Normal conversation mode
- `DIRECTOR` - Game director instructions
- `CHEATMODE` - All input processed as cheat commands
- `AUTOCHAT` - Auto-generates Skyrim lore-appropriate text
- `INJECTION_LOG` - Injects events without response
- `INJECTION_CHAT` - Injects events with response

### 3. Context Building
For each request, the system builds a context including:
- Recent event history (CONTEXT_HISTORY setting, default 50 events)
- NPC biography and personality from `core_npc_master`
- Current location, party members, nearby NPCs
- Player information from `core_player`
- Memory/knowledge retrieval from vector database
- Current game time (converted from Skyrim timestamps)

### 4. LLM Request
Context is sent to configured LLM via connectors in `connector/` folder:
- `openrouterjson.php` - OpenRouter API (most common)
- `openaijson.php` - OpenAI API
- `koboldcppjson.php` - Local KoboldCPP
- `google_openaijson.php` - Google AI via OpenAI-compatible API

### 5. Response Processing
LLM returns JSON with:
```json
{
  "character": "NPC_Name",
  "listener": "Player_Name",
  "message": "dialogue text",
  "mood": "emotional_state",
  "action": "Talk|Follow|Attack|etc",
  "target": "action_target",
  "emotion": "detailed_emotion",
  "emotion_intensity": 0.0-1.0
}
```

### 6. TTS Generation
Text is sent to TTS service (configured in `core_tts_connector`):
- XTTS, MeloTTS, ElevenLabs, Azure, etc.
- Audio cached in `/soundcache/`

### 7. Response Delivery
Response queued in `responselog` table, fetched by game mod via `comm.php`

---

## DATABASE TABLES

### Core System Tables

| Table | Purpose |
|-------|---------|
| `core_npc_master` | NPC profiles (personality, bio, voice, relationships, goals, skills) |
| `core_player` | Player character configuration |
| `core_narrator` | Narrator settings and prompts |
| `core_profiles` | LLM/TTS profile configurations |
| `core_llm_connector` | LLM service configurations (model, API keys, parameters) |
| `core_tts_connector` | TTS service configurations |
| `core_api_badge` | API keys for services (OpenRouter, OpenAI, etc.) |
| `conf_opts` | Runtime configuration options |

### Event & Log Tables

| Table | Purpose |
|-------|---------|
| `eventlog` | All game events (dialogue, location changes, combat, etc.) |
| `audit_request` | LLM API request/response logs with timing and usage |
| `responselog` | Response queue for game mod to fetch |
| `log` | Detailed prompt/response logging |
| `actions_issued` | Actions NPCs have performed |
| `moods_issued` | Mood/emotion tracking |

### Content Tables

| Table | Purpose |
|-------|---------|
| `memory` | Long-term memory storage (vector embeddings) |
| `memory_summary` | Summarized memories by time period |
| `diarylog` | NPC diary entries |
| `books` | Book content encountered in game |
| `quests` | Quest information and objectives |
| `oghma` | Knowledge base (Skyrim lore) |
| `oghma_dynamic` | Dynamic knowledge learned during gameplay |
| `locations` | Location descriptions and information |
| `descriptions` | Item/NPC descriptions by FormID |
| `npc_templates` | Pre-made NPC biography templates |

---

## KEY LOG FILES

| File | Contents |
|------|----------|
| `apache_error.log` | PHP errors, Apache errors |
| `chim.log` | CHIM application logs (info, debug, errors) |
| `output_from_llm.log` | Raw LLM responses with timestamps |
| `context_sent_to_llm.log` | Full context sent to LLM |
| `context_sent_to_llm_fast.log` | Context for fast/helper calls |
| `output_to_plugin.log` | Data sent back to game mod |
| `stt.log` | Speech-to-text processing |
| `vision.log` | Vision/image processing |
| `debugStream.log` | Streaming response debug data |
| `monitor.log` | Service monitor logs |
| `service.log` | Background service logs |

---

## COMMON ISSUES & DEBUGGING

### 1. LLM Connection Issues
**Symptoms:** No response, timeout errors
**Check:**
- `audit_request` table for result != 'OK'
- `apache_error.log` for PHP errors
- `core_api_badge` for valid API key
- `core_llm_connector` for correct model/URL

**SQL:**
```sql
SELECT created_at, result, connector, url FROM audit_request WHERE result != 'OK' ORDER BY created_at DESC LIMIT 20;
```

### 2. Empty/Malformed Responses
**Symptoms:** NPC doesn't speak, garbled output
**Check:**
- `output_from_llm.log` for raw responses
- JSON parsing errors in `chim.log`
- Model compatibility (some models struggle with JSON output)

### 3. TTS Issues
**Symptoms:** No audio, distorted voice
**Check:**
- `core_tts_connector` configuration
- `/soundcache/` for generated audio files
- TTS service connectivity

### 4. Context/Memory Issues
**Symptoms:** NPC forgets things, wrong personality
**Check:**
- `core_npc_master` for NPC profile
- `eventlog` for context history
- `CONTEXT_HISTORY` setting in `conf_opts`

**SQL:**
```sql
SELECT npc_name, personality, relationships, goals FROM core_npc_master WHERE npc_name ILIKE '%name%';
```

### 5. Event Processing Issues
**Symptoms:** Events not triggering, wrong NPC responding
**Check:**
- `eventlog` for incoming events
- Event type routing in `main.php`
- NPC distance/party configuration

**SQL:**
```sql
SELECT ts, type, data, people, location FROM eventlog ORDER BY ts DESC LIMIT 50;
```

---

## CONFIGURATION SYSTEM

### Profile System
NPCs are linked to profiles (`core_profiles`) which define:
- LLM connector for dialogue
- TTS connector for voice
- Custom parameters (temperature, context size)

### Key Settings (conf_opts)
- `PLAYER_NAME` - Player character name
- `CONTEXT_HISTORY` - Number of events to include in context
- `chim_mode` - Current interaction mode
- `COMPACT_CHAT_ENABLED` - Compact conversation history mode

### Dynamic Profiles
NPCs can have `dynamic_profile` enabled, which:
- Periodically updates personality, relationships, goals
- Uses LLM to summarize recent interactions
- Stores updates in NPC's profile fields

---

## YOUR CAPABILITIES

### 1. Database Queries
You can execute **read-only** SQL queries. Always use SELECT with LIMIT.

Format your query as:
```sql
SELECT columns FROM table WHERE conditions LIMIT N;
```

### 2. Log File Reading
Request log files to analyze raw data:
```
READ_LOG: filename.log
```

### 3. Common Analysis Queries

**Recent failed requests:**
```sql
SELECT created_at, result, request, url FROM audit_request WHERE result != 'OK' ORDER BY created_at DESC LIMIT 10;
```

**Recent events:**
```sql
SELECT ts, type, data, location FROM eventlog ORDER BY ts DESC LIMIT 20;
```

**NPC lookup:**
```sql
SELECT npc_name, personality, voiceid FROM core_npc_master WHERE npc_name ILIKE '%search%';
```

**LLM connector config:**
```sql
SELECT label, model, url, max_tokens, temperature FROM core_llm_connector;
```

**API keys configured:**
```sql
SELECT label FROM core_api_badge;
```

---

## RESPONSE GUIDELINES

1. **Be specific** - Quote exact log entries, SQL results, error messages
2. **Be actionable** - Provide concrete steps to fix issues
3. **Use SQL** - Query the database to verify hypotheses
4. **Check logs** - Request relevant log files when debugging
5. **Explain context** - Help users understand how the system works
6. **Format clearly** - Use markdown, tables, and code blocks

SYSTEM_PROMPT;

    // Append live database context
    $prompt .= "\n\n---\n\n## CURRENT DATABASE CONTEXT\n\n" . getDatabaseContext($db);

    return $prompt;
}

/**
 * Process AI function calls (tool use)
 */
function processToolCalls($toolCalls, $db) {
    $results = [];
    
    foreach ($toolCalls as $toolCall) {
        $toolName = $toolCall['name'] ?? '';
        $arguments = $toolCall['arguments'] ?? '';
        
        if ($toolName === 'execute_query') {
            $args = json_decode($arguments, true);
            $query = $args['query'] ?? '';
            $results[] = [
                'tool_call_id' => $toolCall['id'],
                'content' => json_encode(executeSafeQuery($db, $query))
            ];
        } elseif ($toolName === 'read_log') {
            $args = json_decode($arguments, true);
            $filename = $args['filename'] ?? '';
            $results[] = [
                'tool_call_id' => $toolCall['id'],
                'content' => readLogFile($filename)
            ];
        }
    }
    
    return $results;
}

// Check for tool call requests in message
$toolCallRequested = false;
if (preg_match('/```sql\s+(.*?)\s+```/s', $userMessage, $matches)) {
    $query = trim($matches[1]);
    $queryResult = executeSafeQuery($db, $query);
    $userMessage .= "\n\n[Query Result: " . json_encode($queryResult, JSON_PRETTY_PRINT) . "]";
    $toolCallRequested = true;
}

if (preg_match('/READ_LOG:\s+(\S+)/', $userMessage, $matches)) {
    $filename = trim($matches[1]);
    $logContent = readLogFile($filename, 200);
    $userMessage .= "\n\n[Log Content: " . substr($logContent, -5000) . "]";
    $toolCallRequested = true;
}

// Build messages array
$messages = [];
$messages[] = [
    'role' => 'system',
    'content' => buildSystemPrompt($db)
];

// Add conversation history
foreach ($conversationHistory as $msg) {
    $messages[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

// Add current user message
$messages[] = [
    'role' => 'user',
    'content' => $userMessage
];

// Call OpenRouter API
$data = [
    'model' => $model,
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'stream' => false
];

$headers = [
    'Content-Type: application/json',
    "Authorization: Bearer {$apiKey}",
    "HTTP-Referer: https://dwemerdynamics.com/",
    "X-Title: CHIM Log Assistant"
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => json_encode($data),
        'timeout' => 60
    ]
];

$context = stream_context_create($options);

try {
    $response = file_get_contents('https://openrouter.ai/api/v1/chat/completions', false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        echo json_encode(['error' => 'API request failed: ' . $error['message']]);
        exit;
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['error'])) {
        echo json_encode(['error' => 'API error: ' . json_encode($responseData['error'])]);
        exit;
    }
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        echo json_encode(['error' => 'Invalid API response']);
        exit;
    }
    
    $assistantMessage = $responseData['choices'][0]['message']['content'];
    
    // Return response
    echo json_encode([
        'success' => true,
        'message' => $assistantMessage,
        'model' => $model,
        'usage' => $responseData['usage'] ?? null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
}
