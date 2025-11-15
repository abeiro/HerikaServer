# V1.0.12 toggle_thinking Complete Data Flow

## Document Version
- **Version**: v1.0.12
- **Branch**: claude/1.0.12-testing-01SD3JLwpdiGRsLvnUEQ9JEY
- **Date**: 2025-11-15
- **Purpose**: Complete trace of toggle_thinking from UI to API

---

## Overview

The `toggle_thinking` feature controls whether reasoning/thinking tokens are enabled for supported AI models. This document traces the complete flow of this setting through the system.

**Flow Summary**: UI Form → JavaScript Consolidation → Database Storage → Global Variables → Connector → API Payload

---

## 1. UI FORM DISPLAY

### File: `/home/user/HerikaServer/ui/core/llm_connectors.php`

#### Location 1: Partial Editor (Embedded View)
**Lines 352-364**

```php
// Parse metadata to get reasoning-related fields
$metadataArr = [];
if (isset($editItem["metadata"]) && !empty($editItem["metadata"])) {
    $tmpMeta = json_decode($editItem["metadata"], true);
    if (is_array($tmpMeta)) $metadataArr = $tmpMeta;
}
// Use same pattern as other checkboxes for proper boolean/string/int handling
$toggleThinking = isset($metadataArr["toggle_thinking"]) && $metadataArr["toggle_thinking"];
$thinkingTokens = $metadataArr["thinking_tokens"] ?? '';
$effortLevel = $metadataArr["effort_level"] ?? '';
?>
<div id="reasoning_details" style="margin-top:8px; margin-left:20px; padding:8px; border-left:2px solid #444;">
    <label class="label-with-toggle"><span class='tip-label' data-tip='Enable thinking/reasoning for supported models (like o1, DeepSeek-R1). Shows model internal reasoning process.'>Toggle Thinking</span>
        <input type="hidden" name="metadata[toggle_thinking]" value="0">
        <input type="checkbox" id="toggle_thinking" name="metadata[toggle_thinking]" value="1" <?= $toggleThinking ? "checked" : "" ?>>
        <span class="toggle-text">On</span>
    </label>
```

**Key Points:**
- Metadata is loaded from database as JSON string
- JSON is decoded into `$metadataArr`
- `toggle_thinking` is extracted as boolean: `isset($metadataArr["toggle_thinking"]) && $metadataArr["toggle_thinking"]`
- Form field uses hidden input pattern: value="0" for unchecked, value="1" for checked
- Field name: `metadata[toggle_thinking]`

#### Location 2: Main Editor (Full Page View)
**Lines 1263-1379**

```php
// Parse metadata for main editor form (same as partial editor)
$metadataArr = [];
if (isset($editItem["metadata"]) && !empty($editItem["metadata"])) {
    $tmpMeta = json_decode($editItem["metadata"], true);
    if (is_array($tmpMeta)) $metadataArr = $tmpMeta;
}
$toggleThinking = isset($metadataArr["toggle_thinking"]) && $metadataArr["toggle_thinking"];
$thinkingTokens = $metadataArr["thinking_tokens"] ?? '';
$effortLevel = $metadataArr["effort_level"] ?? '';
```

```html
<label class="label-with-toggle"><span class='tip-label' data-tip='Enable thinking/reasoning for supported models (like o1, DeepSeek-R1). Shows model internal reasoning process.'>Toggle Thinking</span>
    <input type="hidden" name="metadata[toggle_thinking]" value="0">
    <input type="checkbox" id="toggle_thinking_modal" name="metadata[toggle_thinking]" value="1" <?= $toggleThinking ? "checked" : "" ?>>
    <span class="toggle-text">On</span>
</label>
```

**Data Type Transformation:**
- **From Database**: JSON string → PHP array → boolean evaluation
- **Display**: Boolean true/false → HTML checked/unchecked

---

## 2. JAVASCRIPT CONSOLIDATION (Form Submission)

### File: `/home/user/HerikaServer/ui/core/llm_connectors.php`
**Lines 1899-1959**

```javascript
// Extend consolidation() to merge reasoning fields into metadata
(function(){
    const originalConsolidation = window.consolidation;
    window.consolidation = function() {
        // First run the original consolidation (from metadata_json_editor.php)
        const result = originalConsolidation ? originalConsolidation() : true;
        if (!result) return false;

        // Now merge our custom reasoning fields into metadata
        const form = document.querySelector('form[method="POST"]');
        if (!form || !form.metadata) return result;

        try {
            // Parse existing metadata
            let metadata = {};
            try {
                const metaStr = form.metadata.value || '{}';
                metadata = JSON.parse(metaStr);
            } catch (_e) {
                metadata = {};
            }

            // Collect reasoning field values (check both regular and modal IDs)
            const toggleThinkingEl = document.getElementById('toggle_thinking') || document.getElementById('toggle_thinking_modal');
            const thinkingTokensEl = document.getElementById('thinking_tokens') || document.getElementById('thinking_tokens_modal');
            const effortLevelEl = document.getElementById('effort_level') || document.getElementById('effort_level_modal');

            // Add toggle_thinking
            if (toggleThinkingEl) {
                metadata.toggle_thinking = toggleThinkingEl.checked;
            }

            // Add thinking_tokens (only if not empty)
            if (thinkingTokensEl) {
                const val = thinkingTokensEl.value.trim();
                if (val !== '') {
                    metadata.thinking_tokens = parseInt(val, 10);
                } else {
                    delete metadata.thinking_tokens;
                }
            }

            // Add effort_level (only if not empty)
            if (effortLevelEl) {
                const val = effortLevelEl.value.trim();
                if (val !== '') {
                    metadata.effort_level = val;
                } else {
                    delete metadata.effort_level;
                }
            }

            // Update form metadata field
            form.metadata.value = JSON.stringify(metadata);
        } catch (err) {
            console.error('Error merging reasoning fields into metadata:', err);
        }

        return result;
    };
})();
```

**Key Points:**
- Function runs when form is submitted (before POST)
- Reads existing metadata JSON from hidden `form.metadata` field
- Finds toggle_thinking element (checks both IDs: `toggle_thinking` and `toggle_thinking_modal`)
- Sets `metadata.toggle_thinking = toggleThinkingEl.checked` (JavaScript boolean)
- Converts entire metadata object to JSON string: `JSON.stringify(metadata)`
- Stores back in `form.metadata.value`

**Data Type Transformation:**
- **Input**: HTML checkbox checked state (boolean)
- **Processing**: JavaScript boolean (true/false)
- **Output**: JSON string with boolean value

---

## 3. DATABASE SAVE (POST Handler)

### File: `/home/user/HerikaServer/ui/core/llm_connectors.php`
**Lines 1060-1069**

```php
// Handle Save (update without leaving current connector)
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["save"]) || isset($_POST["update"])) ) {
    $id = $_POST["id"] ?? '';
    $llm->update($id, $_POST);
    $redir = 'llm_connectors.php' . ($id !== '' ? ('?edit=' . urlencode($id)) : '');
    if (isset($_POST['partial']) && $_POST['partial'] === 'editor') {
        $redir .= ($id !== '' ? '&' : '?') . 'partial=editor';
    }
    header("Location: $redir");
    exit;
}
```

**Key Points:**
- POST data includes metadata field as JSON string
- Calls `$llm->update($id, $_POST)`
- Entire `$_POST` array passed to update method

---

## 4. DATABASE UPDATE METHOD

### File: `/home/user/HerikaServer/lib/core/llm_connector.class.php`
**Lines 45-68**

```php
public function update($id, $data) {
    $fields = [
        "label", "metadata", "url", "model", "provider", "driver", "service", "reasoning_model",
        "max_tokens", "enforce_json", "prefill_json", "api_badge_id", "json_schema",
        "temperature", "presence_penalty", "frequency_penalty", "repetition_penalty",
        "top_p", "top_k", "min_p", "top_a"
    ];

    foreach ($data as $k => $v) {
        if (empty("$v") && $v!=="0") {
            $data[$k] = null;
        }
    }

    // JSON encode metadata if it's an array
    if (isset($data['metadata']) && is_array($data['metadata'])) {
        $data['metadata'] = json_encode($data['metadata']);
    }

    $id = intval($id);
    $where = "id = {$id}";
    $filtered = array_intersect_key($data, array_flip($fields));
    return $GLOBALS["db"]->updateRow($this->table, $filtered, $where);
}
```

**Key Points:**
- Metadata is already a JSON string from JavaScript consolidation
- Additional safety check: if metadata is array, encode it again
- Metadata stored in database as JSON string in `metadata` column
- Table: `core_llm_connector`

**Data Type Transformation:**
- **Input**: JSON string from POST (from JavaScript)
- **Storage**: JSON string in database TEXT/VARCHAR column

**Database Schema:**
```
core_llm_connector table:
- id: INT
- metadata: TEXT (stores JSON string)
- ... other fields ...
```

---

## 5. LOADING FROM DATABASE (setOldGlobals)

### File: `/home/user/HerikaServer/lib/core/llm_connector.class.php`
**Lines 214-243 (for openrouterjsoncached driver)**

```php
} else if ($currentConnectorData["driver"] == "openrouterjsoncached") {

    $apiBadge=new ApiBadge();
    $apiKeyData=$apiBadge->getById($currentConnectorData["api_badge_id"]);
    error_log("[CORE SYSTEM] Using new profile system CONNECTOR openrouterjsoncached {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["model"] = $currentConnectorData["model"] ?? 'anthropic/claude-3-haiku-20240307';
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["PROVIDER"] = $currentConnectorData["provider"] ?? 'Anthropic';
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '4096';
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["top_k"] = $currentConnectorData["top_k"] ?? 0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["min_p"] = $currentConnectorData["min_p"] ?? 0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["top_a"] = $currentConnectorData["top_a"] ?? 0;
    $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["API_KEY"] = $apiKeyData["api_key"];

    // Decode metadata and extended_data if available
    // Metadata should contain caching-specific settings like:
    // provider_caching, response_format, include_*, dialogue_cache_uncached_count, etc.
    $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
    if (is_array($metadata)) {
        foreach ($metadata as $key => $value) {
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value;
        }
    }

}
```

**Key Points:**
- Metadata loaded from database row: `$currentConnectorData['metadata']`
- JSON decoded: `json_decode($currentConnectorData['metadata'] ?? '{}', true)`
- Each metadata key is set as global: `$GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value`
- **toggle_thinking becomes**: `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["toggle_thinking"]`
- **thinking_tokens becomes**: `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["thinking_tokens"]`
- **effort_level becomes**: `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["effort_level"]`

**Data Type Transformation:**
- **From Database**: JSON string
- **Decoded**: PHP array
- **Extracted**: Individual values (toggle_thinking as boolean, thinking_tokens as integer, effort_level as string)
- **Stored**: In $GLOBALS array with original data types

---

## 6. CONNECTOR USAGE (openrouterjsoncached.php & openrouterjsoncached_verbose.php)

### Files:
- `/home/user/HerikaServer/connector/openrouterjsoncached.php`
- `/home/user/HerikaServer/connector/openrouterjsoncached_verbose.php`

**Note**: Both connectors use IDENTICAL logic for toggle_thinking. The verbose version adds additional logging but the data flow is the same.

#### Reading from GLOBALS
**Lines 293-295**

```php
$toggleThinking = isset($GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"]) ? $GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"] : false;
$thinkingTokens = isset($GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"] : 1000;
$effort_level = isset($GLOBALS["CONNECTOR"][$this->name]["effort_level"]) ? $GLOBALS["CONNECTOR"][$this->name]["effort_level"] : "low";
```

**Key Points:**
- `$this->name` = "openrouterjsoncached"
- Reads from `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]["toggle_thinking"]`
- Default value if not set: `false`
- Stores in local variable `$toggleThinking`

**Data Type:**
- **Expected**: Boolean (true/false)
- **Default**: Boolean false

#### Passing to Next Stage
**Lines 339-341**

```php
return $this->_openPart2($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                          $customInstruction, $lastCustomInstruction, $toggleThinking, $thinkingTokens,
                          $effort_level, $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time);
```

**Lines 453-456**

```php
return $this->_openPart3($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                          $lastCustomInstruction, $toggleThinking, $thinkingTokens, $effort_level,
                          $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time,
                          $finalMessagesToSend, $cacheCombinedDialogueFile, $cacheControlType, $dynamicEnvironment);
```

**Lines 598-599**

```php
return $this->_openPart4($customParms, $herikaName, $MAX_TOKENS, $toggleThinking, $thinkingTokens,
                          $effort_level, $start_time, $finalMessagesToSend);
```

#### Building Reasoning Configuration
**Lines 603-624**

```php
private function _openPart4($customParms, $herikaName, $MAX_TOKENS, $toggleThinking, $thinkingTokens,
                             $effort_level, $start_time, $finalMessagesToSend) {

    // Detect model capabilities
    $isOpenAIReasoning = $this->isOpenAIModel($this->_model);
    $isAlwaysReasoning = $this->isAlwaysReasoningModel($this->_model);

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

**Key Logic:**
1. `$reasoning["enabled"]` is set to `($toggleThinking || $isAlwaysReasoning)`
   - TRUE if toggle_thinking is checked OR model always uses reasoning
   - FALSE if toggle_thinking is unchecked AND model doesn't force reasoning

2. If OpenAI reasoning model AND enabled:
   - Adds `effort` field with value from `$effort_level`

3. If non-OpenAI model AND enabled:
   - Adds `max_tokens` field with value from `$thinkingTokens` (converted to integer)

**Data Type:**
- **Input**: `$toggleThinking` (boolean)
- **Processing**: Boolean OR operation with `$isAlwaysReasoning`
- **Output**: Array with "enabled" key (boolean)

---

## 7. API PAYLOAD CONSTRUCTION

### File: `/home/user/HerikaServer/connector/openrouterjsoncached.php`
**Lines 627-640**

```php
// Construct payload
$data = array(
    'model' => $this->_model,
    'messages' => $finalMessagesToSend,
    'stream' => true,
    'temperature' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["temperature"])) ? $GLOBALS["CONNECTOR"][$this->name]["temperature"] : 1),
    'top_k' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["top_k"])) ? $GLOBALS["CONNECTOR"][$this->name]["top_k"] : 0),
    'top_p' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["top_p"])) ? $GLOBALS["CONNECTOR"][$this->name]["top_p"] : 1),
    'frequency_penalty' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"])) ? $GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"] : 0),
    'presence_penalty' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"])) ? $GLOBALS["CONNECTOR"][$this->name]["presence_penalty"] : 0),
    'repetition_penalty' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"])) ? $GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"] : 1),
    'min_p' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["min_p"])) ? $GLOBALS["CONNECTOR"][$this->name]["min_p"] : 0),
    'top_a' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["top_a"])) ? $GLOBALS["CONNECTOR"][$this->name]["top_a"] : 0),
    'reasoning' => $reasoning
);
```

**Key Points:**
- Reasoning configuration added to payload as `'reasoning'` key
- Contains the `enabled`, `exclude`, and either `effort` or `max_tokens` fields

**Example Payload Structures:**

**Case 1: toggle_thinking = true, OpenAI model (o1)**
```json
{
  "model": "openai/o1-mini",
  "messages": [...],
  "stream": true,
  "reasoning": {
    "exclude": true,
    "enabled": true,
    "effort": "low"
  }
}
```

**Case 2: toggle_thinking = true, non-OpenAI model (DeepSeek)**
```json
{
  "model": "deepseek/deepseek-chat",
  "messages": [...],
  "stream": true,
  "reasoning": {
    "exclude": true,
    "enabled": true,
    "max_tokens": 1000
  }
}
```

**Case 3: toggle_thinking = false, regular model**
```json
{
  "model": "anthropic/claude-3-haiku",
  "messages": [...],
  "stream": true,
  "reasoning": {
    "exclude": true,
    "enabled": false
  }
}
```

**Data Type:**
- **Final Output**: JSON object sent to API
- **reasoning.enabled**: Boolean (true/false)
- **reasoning.exclude**: Boolean (always true)
- **reasoning.effort**: String ("minimal"/"low"/"medium"/"high")
- **reasoning.max_tokens**: Integer

---

## 8. SPECIAL HANDLING FOR OPENAI MODELS

### File: `/home/user/HerikaServer/connector/openrouterjsoncached.php`
**Lines 680-713**

```php
// Handle OpenAI reasoning models - they require special parameter handling
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

**Key Points:**
- OpenAI reasoning models (o1, o3, o4, gpt-5) have strict requirements
- When reasoning is enabled, strips ALL sampling parameters
- Only keeps: model, messages, stream, reasoning, max_completion_tokens, provider, transforms
- This prevents API errors from unsupported parameters

---

## 9. COMPLETE DATA FLOW SUMMARY

### Step-by-Step Transformation

1. **Database Storage** (TEXT/JSON string)
   ```json
   "{\"toggle_thinking\":true,\"thinking_tokens\":1000,\"effort_level\":\"low\"}"
   ```

2. **Load into PHP** (JSON decode)
   ```php
   $metadata = [
       "toggle_thinking" => true,
       "thinking_tokens" => 1000,
       "effort_level" => "low"
   ]
   ```

3. **Set as Global**
   ```php
   $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["toggle_thinking"] = true;
   $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["thinking_tokens"] = 1000;
   $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["effort_level"] = "low";
   ```

4. **Read in Connector**
   ```php
   $toggleThinking = true;  // boolean
   $thinkingTokens = 1000;  // integer
   $effort_level = "low";   // string
   ```

5. **Build Reasoning Config**
   ```php
   $reasoning = [
       "exclude" => true,
       "enabled" => true,  // from toggle_thinking
       "max_tokens" => 1000  // from thinking_tokens (or "effort" => "low" for OpenAI)
   ];
   ```

6. **Add to API Payload**
   ```json
   {
     "reasoning": {
       "exclude": true,
       "enabled": true,
       "max_tokens": 1000
     }
   }
   ```

7. **Send to API** (JSON.stringify)
   ```
   POST https://openrouter.ai/api/v1/chat/completions
   ```

---

## 10. DATA TYPE REFERENCE

| Stage | Location | Data Type | Example Value |
|-------|----------|-----------|---------------|
| Database | `core_llm_connector.metadata` | TEXT (JSON string) | `"{"toggle_thinking":true}"` |
| PHP Decode | `$metadata` array | Boolean in array | `["toggle_thinking" => true]` |
| Globals | `$GLOBALS["CONNECTOR"][...]` | Boolean | `true` |
| Connector Variable | `$toggleThinking` | Boolean | `true` |
| Reasoning Array | `$reasoning["enabled"]` | Boolean | `true` |
| API Payload | JSON object | Boolean | `{"enabled": true}` |

---

## 11. RELATED FIELDS

### thinking_tokens
- **Purpose**: Maximum tokens for reasoning output (non-OpenAI models)
- **Type**: Integer
- **Default**: 1000
- **Used When**: toggle_thinking is true AND not an OpenAI reasoning model

### effort_level
- **Purpose**: Reasoning effort level for OpenAI models
- **Type**: String
- **Values**: "minimal", "low", "medium", "high"
- **Default**: "low"
- **Used When**: toggle_thinking is true AND is an OpenAI reasoning model

---

## 12. MODEL DETECTION FUNCTIONS

### File: `/home/user/HerikaServer/connector/openrouterjsoncached.php`

#### isReasoningModel() - Lines 136-181
Detects if model supports reasoning at all (broad detection):
- deepseek-r*, qwq-*, *-thinking, *:thinking, *-reasoning
- grok-3-mini, sonar-deep-research, r1-1776
- dolphin3.0-r1-mistral, aion-1.0, reka-flash-3
- olympiccoder-*, MAI-DS-R1, qwen3-*, openai/o1, openai/o3, openai/o4

#### isOpenAIModel() - Lines 183-223
Detects OpenAI reasoning models that need special handling:
- openai/o1*, openai/o3*, openai/o4*
- azure-o1, azure-o3, azure-o4
- o1, o1-mini, o1-preview
- o3, o3-mini*, o3-pro*
- o4, o4-mini*
- gpt-5* (except gpt-5-chat)

#### isAlwaysReasoningModel() - Lines 225-244
Detects models that ALWAYS reason (cannot disable):
- All OpenAI reasoning models
- deepseek-r1, r1-1776

**Logic:**
```php
$reasoning["enabled"] = ($toggleThinking || $isAlwaysReasoning)
```
If model always reasons, it will be enabled regardless of toggle_thinking setting.

---

## 13. DEBUGGING TIPS

### To verify toggle_thinking is working:

1. **Check Database**
   ```sql
   SELECT id, label, metadata FROM core_llm_connector WHERE id = X;
   ```
   Look for: `"toggle_thinking":true` in metadata JSON

2. **Check PHP Globals**
   Add to connector code after line 295:
   ```php
   error_log("toggle_thinking value: " . var_export($toggleThinking, true));
   ```

3. **Check Reasoning Config**
   Add after line 624:
   ```php
   error_log("reasoning config: " . json_encode($reasoning));
   ```

4. **Check API Payload**
   Look at `/home/user/HerikaServer/log/context_sent_to_llm.log`
   Search for `'reasoning'` in the payload

5. **Check Form Submission**
   Browser console:
   ```javascript
   // Before submitting form
   console.log('metadata:', document.querySelector('form').metadata.value);
   ```

---

## 14. COMMON ISSUES

### Issue: toggle_thinking not saving
**Cause**: JavaScript consolidation() not running
**Fix**: Check browser console for errors, verify metadata field exists

### Issue: Always enabled even when unchecked
**Cause**: Model in `isAlwaysReasoningModel()` list
**Solution**: This is intentional - these models cannot disable reasoning

### Issue: Not working for specific model
**Cause**: Model not in `isReasoningModel()` detection list
**Fix**: Add model pattern to detection function

### Issue: API errors with reasoning enabled
**Cause**: Parameters not stripped for OpenAI models
**Check**: Verify `isOpenAIModel()` detection and parameter cleaning (lines 680-713)

---

## 15. VERSION HISTORY

### v1.0.12
- Initial implementation of toggle_thinking
- Replaces old reasoning_model boolean checkbox
- Adds thinking_tokens and effort_level fields
- Implements reasoning configuration in API payload

---

## CONCLUSION

The toggle_thinking feature follows a clean, consistent flow from UI to API:

1. **UI**: Checkbox in form (boolean checked/unchecked)
2. **JavaScript**: Consolidates into metadata JSON (boolean value)
3. **Database**: Stores as JSON string
4. **Load**: Decodes JSON, sets as PHP boolean in $GLOBALS
5. **Connector**: Reads boolean, builds reasoning config
6. **API**: Sends as JSON boolean in payload

The value maintains its boolean type throughout the entire flow, only being serialized to/from JSON for storage and transmission.
