# Thinking Toggle Settings - Complete System Documentation

**Version:** 1.0.12
**Date:** 2025-11-15
**Branch:** claude/1.0.12-testing-01SD3JLwpdiGRsLvnUEQ9JEY

## Overview

This document traces the complete flow of the thinking/reasoning toggle settings through the HerikaServer system, from UI input to API execution and response processing.

The three settings are:
1. **Toggle Thinking** - Enable/disable reasoning for supported models
2. **Thinking Tokens** - Maximum tokens for reasoning output (Anthropic/Gemini)
3. **Effort Level** - Reasoning effort for OpenAI models (minimal/low/medium/high)

---

## Table of Contents

1. [UI Layer](#ui-layer)
2. [Database Storage Layer](#database-storage-layer)
3. [Configuration Layer](#configuration-layer)
4. [Connector Implementation](#connector-implementation)
5. [API Payload Construction](#api-payload-construction)
6. [Response Processing](#response-processing)
7. [Complete Data Flow](#complete-data-flow)
8. [Model-Specific Behavior](#model-specific-behavior)
9. [File Reference](#file-reference)

---

## UI Layer

### Location
`ui/core/llm_connectors.php`

### UI Fields

The settings are rendered in TWO places (main editor and modal):

#### Main Editor (lines 357-372)
```php
// Parse metadata to get reasoning-related fields
$metadataArr = [];
if (isset($editItem["metadata"]) && !empty($editItem["metadata"])) {
    $tmpMeta = json_decode($editItem["metadata"], true);
    if (is_array($tmpMeta)) $metadataArr = $tmpMeta;
}

$toggleThinking = isset($metadataArr["toggle_thinking"]) && $metadataArr["toggle_thinking"];
$thinkingTokens = $metadataArr["thinking_tokens"] ?? '';
$effortLevel = $metadataArr["effort_level"] ?? '';
```

#### HTML Form Fields
```html
<!-- Toggle Thinking Checkbox -->
<input type="hidden" name="metadata[toggle_thinking]" value="0">
<input type="checkbox" id="toggle_thinking" name="metadata[toggle_thinking]" value="1" <?= $toggleThinking ? "checked" : "" ?>>

<!-- Thinking Tokens Input -->
<input type="number" id="thinking_tokens" name="metadata[thinking_tokens]" value="<?= htmlspecialchars($thinkingTokens) ?>" min="0" step="1" placeholder="Optional">

<!-- Effort Level Dropdown -->
<select id="effort_level" name="metadata[effort_level]">
    <option value="">-- select --</option>
    <option value="minimal" <?= $effortLevel === 'minimal' ? 'selected' : '' ?>>Minimal</option>
    <option value="low" <?= $effortLevel === 'low' ? 'selected' : '' ?>>Low</option>
    <option value="medium" <?= $effortLevel === 'medium' ? 'selected' : '' ?>>Medium</option>
    <option value="high" <?= $effortLevel === 'high' ? 'selected' : '' ?>>High</option>
</select>
```

#### Modal Editor (lines 1372-1386)
Identical fields with `_modal` suffix for IDs to avoid conflicts.

### Form Submission

**Form Element** (line 250):
```php
<form method="post" onsubmit='if (window.isInIframe) { window.handleEmbeddedSave(); return false; } return consolidation();'>
```

**Field Names:**
- `metadata[toggle_thinking]` → boolean (0/1)
- `metadata[thinking_tokens]` → integer
- `metadata[effort_level]` → string (minimal/low/medium/high)

---

## Database Storage Layer

### Database Schema

**File:** `lib/core/database_schema/core_llm_connector.sql`

**Table:** `core_llm_connector`

**Metadata Column:**
```sql
CREATE TABLE public.core_llm_connector (
    id integer NOT NULL,
    label text,
    metadata jsonb,  -- ← Settings stored here
    url text,
    model text,
    -- ... other columns
);
```

The `metadata` column is type `jsonb` (PostgreSQL JSON binary), storing all metadata settings as JSON.

### Save Operation

**File:** `lib/core/llm_connector.class.php`

**Create Method** (lines 7-28):
```php
public function create($data) {
    $fields = [
        "label", "metadata", "url", "model", "provider", "driver", "service", "reasoning_model",
        "max_tokens", "enforce_json", "prefill_json", "api_badge_id", "json_schema",
        "temperature", "presence_penalty", "frequency_penalty", "repetition_penalty",
        "top_p", "top_k", "min_p", "top_a"
    ];

    // JSON encode metadata if it's an array
    if (isset($data['metadata']) && is_array($data['metadata'])) {
        $data['metadata'] = json_encode($data['metadata']);
    }

    $filtered = array_intersect_key($data, array_flip($fields));
    return $GLOBALS["db"]->insert($this->table, $filtered);
}
```

**Update Method** (lines 45-68):
```php
public function update($id, $data) {
    // Same fields and JSON encoding logic
    if (isset($data['metadata']) && is_array($data['metadata'])) {
        $data['metadata'] = json_encode($data['metadata']);
    }

    $id = intval($id);
    $where = "id = {$id}";
    $filtered = array_intersect_key($data, array_flip($fields));
    return $GLOBALS["db"]->updateRow($this->table, $filtered, $where);
}
```

### Stored JSON Format

When saved, the metadata looks like:
```json
{
    "toggle_thinking": true,
    "thinking_tokens": 2000,
    "effort_level": "medium",
    "provider_caching": "Anthropic",
    "response_format": "json",
    ...
}
```

---

## Configuration Layer

### Loading from Database

**File:** `lib/core/llm_connector.class.php`

**Method:** `setOldGlobals()` (lines 119-276)

This method loads connector data from the database and sets it into `$GLOBALS["CONNECTOR"]` for runtime use.

**For `openrouterjsoncached` driver** (lines 214-242):
```php
// Load basic connector settings
$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["url"] = $currentConnectorData["url"] ?? '...';
$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["model"] = $currentConnectorData["model"] ?? '...';
// ... other settings

// Decode metadata and spread into GLOBALS
$metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
if (is_array($metadata)) {
    foreach ($metadata as $key => $value) {
        $GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value;
    }
}
```

**Result:** Settings become available at:
- `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["toggle_thinking"]`
- `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["thinking_tokens"]`
- `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["effort_level"]`

---

## Connector Implementation

### Reading Settings

**File:** `connector/openrouterjsoncached.php`

**Method:** `open()` → `_openPart1()` (lines 293-295)

```php
$toggleThinking = isset($GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"])
    ? $GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"]
    : false;

$thinkingTokens = isset($GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"])
    ? $GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"]
    : 1000;

$effort_level = isset($GLOBALS["CONNECTOR"][$this->name]["effort_level"])
    ? $GLOBALS["CONNECTOR"][$this->name]["effort_level"]
    : "low";
```

**Default Values:**
- `toggle_thinking`: `false`
- `thinking_tokens`: `1000`
- `effort_level`: `"low"`

### Passing Through Processing Chain

The settings are passed through multiple methods:

1. **`_openPart1()`** → `_openPart2()` (line 341)
2. **`_openPart2()`** → `_openPart3()` (line 454)
3. **`_openPart3()`** → `_openPart4()` (line 599)
4. **`_openPart4()`** → Final payload construction (line 603)

---

## API Payload Construction

### Model Detection

**File:** `connector/openrouterjsoncached.php`

**Method:** `_openPart4()` (lines 606-608)

```php
// Detect model capabilities
$isOpenAIReasoning = $this->isOpenAIModel($this->_model);
$isAlwaysReasoning = $this->isAlwaysReasoningModel($this->_model);
```

#### `isOpenAIModel()` (lines 183-223)
Detects OpenAI reasoning models that require special parameter handling:
- `openai/o1`, `openai/o3`, `openai/o4`
- `azure-o1`, `azure-o3`, `azure-o4`
- `gpt-5` series (except `gpt-5-chat`)

#### `isAlwaysReasoningModel()` (lines 225-244)
Detects models that ALWAYS have reasoning enabled:
- All OpenAI reasoning models (from `isOpenAIModel()`)
- DeepSeek R1 variants (`deepseek-r1`, `r1-1776`)

### Reasoning Configuration

**Lines 613-624:**
```php
// Build reasoning configuration
// Always include exclude:true to strip reasoning tokens from output
// Enable reasoning if: toggle is on OR model always reasons
$reasoning = [
    "exclude" => true,
    "enabled" => ($toggleThinking || $isAlwaysReasoning),
];

// Add effort level for OpenAI models (supports minimal/low/medium/high)
if ($isOpenAIReasoning && $reasoning["enabled"]) {
    $reasoning["effort"] = $effort_level;
} else if ($reasoning["enabled"]) {
    // For non-OpenAI models, use max_tokens instead of effort
    $reasoning["max_tokens"] = intval($thinkingTokens);
}
```

**Logic:**
- `reasoning.exclude` is ALWAYS `true` (reasoning tokens stripped from output)
- `reasoning.enabled` is `true` if:
  - User enabled toggle, OR
  - Model always reasons (cannot be disabled)
- For OpenAI models: Use `reasoning.effort` (minimal/low/medium/high)
- For other models: Use `reasoning.max_tokens` (integer)

### Base Payload

**Lines 627-640:**
```php
$data = array(
    'model' => $this->_model,
    'messages' => $finalMessagesToSend,
    'stream' => true,
    'temperature' => floatval(...),
    'top_k' => floatval(...),
    'top_p' => floatval(...),
    'frequency_penalty' => floatval(...),
    'presence_penalty' => floatval(...),
    'repetition_penalty' => floatval(...),
    'min_p' => floatval(...),
    'top_a' => floatval(...),
    'reasoning' => $reasoning  // ← Added to payload
);
```

### OpenAI Parameter Stripping

**Lines 681-713:**

OpenAI reasoning models require parameter stripping when reasoning is enabled:

```php
if ($isOpenAIReasoning) {
    // OpenAI models use max_completion_tokens instead of max_tokens
    if (isset($data["max_tokens"])) {
        $data["max_completion_tokens"] = $data["max_tokens"];
        unset($data["max_tokens"]);
    }

    // If reasoning is enabled, OpenAI models ONLY accept these parameters
    // All other parameters (temperature, top_p, penalties, etc.) must be stripped
    if ($reasoning["enabled"]) {
        $cleanedData = [
            'model' => $data['model'],
            'messages' => $data['messages'],
            'stream' => $data['stream'],
            'reasoning' => $data['reasoning']
        ];

        // Only add max_completion_tokens if it was set
        if (isset($data['max_completion_tokens'])) {
            $cleanedData['max_completion_tokens'] = $data['max_completion_tokens'];
        }

        // Preserve provider and transforms if they exist
        if (isset($data['provider'])) {
            $cleanedData['provider'] = $data['provider'];
        }
        if (isset($data['transforms'])) {
            $cleanedData['transforms'] = $data['transforms'];
        }

        $data = $cleanedData;
    }
}
```

**Parameters REMOVED for OpenAI reasoning models:**
- `temperature`
- `top_p`, `top_k`, `top_a`, `min_p`
- `frequency_penalty`, `presence_penalty`, `repetition_penalty`
- Any other non-essential parameters

**Parameters KEPT:**
- `model`
- `messages`
- `stream`
- `reasoning` (with `exclude`, `enabled`, `effort`)
- `max_completion_tokens` (if set)
- `provider` (if set)
- `transforms` (if set)

### Final Payload Examples

#### Example 1: Anthropic Claude with Thinking Enabled
```json
{
    "model": "anthropic/claude-sonnet-4.5",
    "messages": [...],
    "stream": true,
    "temperature": 1.0,
    "top_p": 1.0,
    "reasoning": {
        "exclude": true,
        "enabled": true,
        "max_tokens": 2000
    },
    "provider": {"order": ["Anthropic"]}
}
```

#### Example 2: OpenAI o1 with Thinking Enabled
```json
{
    "model": "openai/o1",
    "messages": [...],
    "stream": true,
    "reasoning": {
        "exclude": true,
        "enabled": true,
        "effort": "medium"
    },
    "max_completion_tokens": 4096,
    "provider": {"order": ["OpenAI"]}
}
```
*Note: temperature and other parameters stripped*

#### Example 3: Thinking Disabled
```json
{
    "model": "anthropic/claude-sonnet-4.5",
    "messages": [...],
    "stream": true,
    "temperature": 1.0,
    "top_p": 1.0,
    "reasoning": {
        "exclude": true,
        "enabled": false
    },
    "provider": {"order": ["Anthropic"]}
}
```

---

## Response Processing

### Streaming Processing

**File:** `connector/openrouterjsoncached.php`

**Method:** `process()` (lines 801-945)

The connector processes streaming responses from the API, handling both Anthropic and OpenAI formats.

**Anthropic Format** (lines 840-904):
```php
if (isset($data['type'])) {
    switch ($data['type']) {
        case 'content_block_delta':
            if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta' &&
                isset($data['delta']['text'])) {
                $buffer = $data['delta']['text'];
                $this->_buffer .= $buffer;
            }
            break;
        // ... other cases
    }
}
```

**OpenAI Format** (lines 907-921):
```php
elseif (isset($data["choices"][0]["delta"])) {
    if (isset($data["choices"][0]["delta"]["content"])) {
        $buffer = $data["choices"][0]["delta"]["content"];
        $this->_buffer .= $buffer;
    }
    // ... finish_reason handling
}
```

### Reasoning Token Filtering

**Method:** `_parseAndReturnContent()` (lines 948-1010)

This is called every time content is ready to be returned.

**JSON Format** (lines 949-969):
```php
if ($this->_responseFormat === 'json') {
    $extracted_json_or_text = extractJson($this->_buffer);
    $tempJson = json_decode($extracted_json_or_text, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($tempJson['message']) && !empty($tempJson['message'])) {
        // Set mood, listener, etc.
        // ...

        // Strip any reasoning tokens from final message before returning
        return stripReasoningTokens($tempJson['message']);  // ← Line 968
    }
}
```

**Simple Format** (lines 970-1010):
```php
else {
    // Simple format parsing
    if (!$this->_simpleFormatParsed) {
        $parsed = extractSimpleFormatFromBuffer(...);

        if ($parsed['found']) {
            // ... set globals for mood, listener, etc.

            return stripReasoningTokens($parsed['message']);  // ← Filtering applied
        }
    } else {
        // Incremental streaming
        $newContent = mb_substr($this->_buffer, $this->_simpleFormatMessageStart + $this->_lastReturnedLength);

        if (!empty($newContent)) {
            $this->_lastReturnedLength += mb_strlen($newContent);
            return stripReasoningTokens($newContent);  // ← Filtering applied
        }
    }
}
```

### stripReasoningTokens() Function

**File:** `lib/chat_helper_functions.php`

**Lines:** 166-195

```php
/**
 * Strip reasoning/CoT tokens from text
 * Removes common reasoning markers: <think>, <thinking>, <reasoning>, <thought>, <reflection>
 * Also removes DeepSeek-style markers and other common patterns
 */
function stripReasoningTokens($text) {
    if (empty($text)) {
        return $text;
    }

    // Common reasoning markers (case-insensitive)
    $patterns = [
        '/<think>.*?<\/think>/is',           // <think>...</think>
        '/<thinking>.*?<\/thinking>/is',     // <thinking>...</thinking>
        '/<reasoning>.*?<\/reasoning>/is',   // <reasoning>...</reasoning>
        '/<thought>.*?<\/thought>/is',       // <thought>...</thought>
        '/<reflection>.*?<\/reflection>/is', // <reflection>...</reflection>
        '/<cot>.*?<\/cot>/is',               // <cot>...</cot> (chain of thought)
        '/<scratchpad>.*?<\/scratchpad>/is', // <scratchpad>...</scratchpad>
        '/\[THINK\].*?\[\/THINK\]/is',       // [THINK]...[/THINK]
        '/\[THINKING\].*?\[\/THINKING\]/is', // [THINKING]...[/THINKING]
    ];

    $cleaned = $text;
    foreach ($patterns as $pattern) {
        $cleaned = preg_replace($pattern, '', $cleaned);
    }

    // Clean up any resulting extra whitespace
    // Collapse ALL whitespace (including newlines) to single spaces for roleplay responses
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    $cleaned = trim($cleaned);

    return $cleaned;
}
```

**Supported Patterns:**
- `<think>...</think>`
- `<thinking>...</thinking>`
- `<reasoning>...</reasoning>`
- `<thought>...</thought>`
- `<reflection>...</reflection>`
- `<cot>...</cot>`
- `<scratchpad>...</scratchpad>`
- `[THINK]...[/THINK]`
- `[THINKING]...[/THINKING]`

**Regex Flags:**
- `i` = Case-insensitive
- `s` = Dot matches newlines
- `.*?` = Non-greedy matching

**Whitespace Handling:**
- All whitespace (spaces, tabs, newlines) collapsed to single spaces
- Result is trimmed
- Ensures roleplay responses are single-line for game engine

---

## Complete Data Flow

### 1. User Input (UI)
```
User fills form in llm_connectors.php:
├─ Toggle Thinking: [✓] On
├─ Thinking Tokens: 2000
└─ Effort Level: medium
```

### 2. Form Submission
```
POST data:
{
    "metadata[toggle_thinking]": "1",
    "metadata[thinking_tokens]": "2000",
    "metadata[effort_level]": "medium",
    ...
}
```

### 3. Database Save (LLMConnector class)
```php
// Convert array to JSON
$data['metadata'] = json_encode([
    "toggle_thinking" => 1,
    "thinking_tokens" => 2000,
    "effort_level" => "medium",
    ...
]);

// Store in PostgreSQL jsonb column
INSERT INTO core_llm_connector (metadata, ...) VALUES (...);
```

### 4. Load Configuration (setOldGlobals)
```php
// Read from database
$metadata = json_decode($row['metadata'], true);

// Spread into GLOBALS
foreach ($metadata as $key => $value) {
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value;
}

// Now available at:
// $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["toggle_thinking"] = 1
// $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["thinking_tokens"] = 2000
// $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["effort_level"] = "medium"
```

### 5. Connector Reads Settings
```php
// In openrouterjsoncached.php
$toggleThinking = $GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"];  // 1
$thinkingTokens = $GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"];  // 2000
$effort_level = $GLOBALS["CONNECTOR"][$this->name]["effort_level"];      // "medium"
```

### 6. Build Reasoning Configuration
```php
$isOpenAIReasoning = $this->isOpenAIModel($this->_model);  // false (Anthropic)
$isAlwaysReasoning = $this->isAlwaysReasoningModel($this->_model);  // false

$reasoning = [
    "exclude" => true,
    "enabled" => ($toggleThinking || $isAlwaysReasoning),  // true
];

// Not OpenAI, so use max_tokens
$reasoning["max_tokens"] = intval($thinkingTokens);  // 2000
```

### 7. API Request
```json
{
    "model": "anthropic/claude-sonnet-4.5",
    "messages": [...],
    "stream": true,
    "temperature": 1.0,
    "reasoning": {
        "exclude": true,
        "enabled": true,
        "max_tokens": 2000
    }
}
```

### 8. API Response Processing
```
Stream chunk 1: "data: {"delta":{"text":"<think>Analyzing...</think>Hello"}}"
  ↓
Buffer: "<think>Analyzing...</think>Hello"
  ↓
parseAndReturnContent():
  extractJson() → {"message": "<think>Analyzing...</think>Hello"}
  ↓
  stripReasoningTokens() → "Hello"
  ↓
  RETURN: "Hello"
```

### 9. Final Output
```
Game receives: "Hello" (reasoning tokens removed)
```

---

## Model-Specific Behavior

### Anthropic Models (Claude)
**Detection:** Not matched by `isOpenAIModel()`

**Payload:**
```json
{
    "reasoning": {
        "exclude": true,
        "enabled": true,
        "max_tokens": 2000  // ← Uses thinking_tokens setting
    },
    "temperature": 1.0,  // ← Other parameters KEPT
    "top_p": 1.0
}
```

**Characteristics:**
- Uses `reasoning.max_tokens` parameter
- Keeps all other parameters (temperature, top_p, etc.)
- Supports prompt caching
- Reasoning can be toggled on/off

---

### OpenAI Reasoning Models (o1, o3, o4, gpt-5)
**Detection:** Matched by `isOpenAIModel()`

**Payload (Reasoning Enabled):**
```json
{
    "reasoning": {
        "exclude": true,
        "enabled": true,
        "effort": "medium"  // ← Uses effort_level setting
    },
    "max_completion_tokens": 4096  // ← Renamed from max_tokens
}
```
*Note: temperature, top_p, and other parameters STRIPPED*

**Payload (Reasoning Disabled - NOT RECOMMENDED):**
```json
{
    "reasoning": {
        "exclude": true,
        "enabled": false
    },
    "max_completion_tokens": 4096,
    "temperature": 1.0  // ← Parameters kept when disabled
}
```

**Characteristics:**
- Uses `reasoning.effort` parameter (minimal/low/medium/high)
- Uses `max_completion_tokens` instead of `max_tokens`
- When reasoning enabled: ALL sampling parameters stripped
- Parameter stripping is REQUIRED by OpenAI API spec

**Detected Models:**
- `openai/o1`, `openai/o1-preview`, `openai/o1-mini`
- `openai/o3`, `openai/o3-mini`
- `openai/o4`
- `azure-o1`, `azure-o3`, `azure-o4`
- `gpt-5`, `gpt-5-pro`, `gpt-5-codex`, `gpt-5-mini`, `gpt-5-nano`
- **Excluded:** `gpt-5-chat` (not a reasoning model)

---

### DeepSeek R1 Models
**Detection:** Matched by `isAlwaysReasoningModel()`

**Payload:**
```json
{
    "reasoning": {
        "exclude": true,
        "enabled": true,  // ← ALWAYS true (cannot disable)
        "max_tokens": 2000
    },
    "temperature": 1.0
}
```

**Characteristics:**
- Reasoning ALWAYS enabled (forced by `isAlwaysReasoningModel()`)
- Uses `reasoning.max_tokens` parameter
- Toggle Thinking setting is IGNORED
- Uses custom reasoning markers: `<think>`, `<thought>`

**Detected Models:**
- `deepseek-r1`
- `r1-1776`

---

### Gemini Models
**Detection:** Not matched by `isOpenAIModel()`

**Payload:**
```json
{
    "reasoning": {
        "exclude": true,
        "enabled": true,
        "max_tokens": 2000
    },
    "temperature": 1.0,
    "top_p": 1.0
}
```

**Characteristics:**
- Uses `reasoning.max_tokens` parameter (same as Anthropic)
- Keeps all other parameters
- Reasoning can be toggled on/off
- Supports extended thinking mode

---

## File Reference

### Core Files Involved

| File | Purpose | Lines |
|------|---------|-------|
| `ui/core/llm_connectors.php` | UI form and field rendering | 352-372 (main), 1372-1386 (modal) |
| `lib/core/llm_connector.class.php` | Database operations and GLOBALS setup | 22-24 (create), 60-62 (update), 237-241 (setOldGlobals) |
| `lib/core/database_schema/core_llm_connector.sql` | Database schema definition | 30 (metadata column) |
| `connector/openrouterjsoncached.php` | Main connector implementation | 293-295 (read settings), 603-624 (reasoning config), 681-713 (parameter stripping), 968 (filtering) |
| `connector/openrouterjsoncached_verbose.php` | Verbose logging version | Same as main connector + logging |
| `lib/chat_helper_functions.php` | Helper functions | 166-195 (stripReasoningTokens) |

### Key Methods

| Method | File | Purpose |
|--------|------|---------|
| `create()` | llm_connector.class.php | Save new connector to database |
| `update()` | llm_connector.class.php | Update existing connector |
| `setOldGlobals()` | llm_connector.class.php | Load settings into GLOBALS |
| `_openPart1()` | openrouterjsoncached.php | Read settings from GLOBALS |
| `_openPart4()` | openrouterjsoncached.php | Build reasoning config & payload |
| `isOpenAIModel()` | openrouterjsoncached.php | Detect OpenAI reasoning models |
| `isAlwaysReasoningModel()` | openrouterjsoncached.php | Detect always-reasoning models |
| `process()` | openrouterjsoncached.php | Process streaming response |
| `_parseAndReturnContent()` | openrouterjsoncached.php | Parse and filter response |
| `stripReasoningTokens()` | chat_helper_functions.php | Remove reasoning markers |

---

## Three-Layer Filtering System

The system uses a defense-in-depth approach to ensure reasoning tokens never reach the game:

### Layer 1: API Request (reasoning.exclude=true)
- Added to API payload: `reasoning: {exclude: true, ...}`
- Tells API to exclude reasoning from normal output
- Most reliable method, supported by most providers
- **File:** `connector/openrouterjsoncached.php:614`

### Layer 2: Streaming Detection
- Monitors incoming stream for unclosed reasoning markers
- Extracts content BEFORE unclosed markers
- Prevents reasoning from being sent to game mid-stream
- **File:** `lib/data_functions.php` (streaming loop)

### Layer 3: Final Output Stripping (stripReasoningTokens)
- Applied to all return paths in connector
- Removes any reasoning that escaped Layers 1 & 2
- Failsafe guarantee that reasoning never reaches game
- Works for both JSON and simple response formats
- **File:** `lib/chat_helper_functions.php:166-195`

---

## Summary

The thinking toggle settings flow through the system as follows:

1. **UI Input** → Form fields in `llm_connectors.php`
2. **Database Storage** → JSON in `core_llm_connector.metadata` (jsonb)
3. **Configuration** → Loaded into `$GLOBALS["CONNECTOR"][driver][setting]`
4. **Connector** → Read settings and pass through processing chain
5. **API Payload** → Build `reasoning` object with appropriate parameters
6. **Model Detection** → Apply model-specific logic (OpenAI stripping, etc.)
7. **Response Processing** → Filter reasoning tokens from output
8. **Final Output** → Clean text sent to game engine

The system ensures:
- ✅ Settings persist in database as JSON
- ✅ Toggle can enable/disable reasoning for compatible models
- ✅ Thinking Tokens used for Anthropic/Gemini
- ✅ Effort Level used for OpenAI reasoning models
- ✅ Always-reasoning models force enable (DeepSeek R1)
- ✅ OpenAI models strip parameters when reasoning enabled
- ✅ Three-layer filtering prevents reasoning from reaching game
- ✅ Works with both JSON and simple response formats

---

**End of Documentation**
