# Complete LLM Context Construction Flow

This document traces EVERY component that constructs the final context sent to the LLM.

## Overview

The final context is built in three stages:
1. **main.php** - Builds system prompt and dialogue history
2. **connector/openrouterjsoncached.php** - Adds actions list, format instructions, and processes caching
3. **API Request** - Final payload with all parameters

---

## STAGE 1: main.php - Initial Context Building

### 1.1 System Prompt Components (`$head[]`)

Built at **main.php lines 1499-1513**:

```php
$head[] = array('role' => 'system', 'content' =>
    strtr(
        $GLOBALS["PROMPT_HEAD"] .
        "\n\n<character>\n" . $GLOBALS["HERIKA_PERS"] . $dynamicBiography . "\n</character>\n\n" .
        "<knowledge>\n" . $GLOBALS["OGHMA_HINT"] . "\n</knowledge>\n\n" .
        "<general_instructions>\n" . $GLOBALS["COMMAND_PROMPT"] . "</general_instructions>\n" .
        $rumors . "\n",
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"], "#HERIKA_NAME#"=>$GLOBALS["HERIKA_NAME"]]
    )
);
```

#### Components:

**A. `$GLOBALS["PROMPT_HEAD"]`** (from `conf.php`)
- Default: "Let's roleplay in the Universe of Skyrim. If the game director gives you an instruction, you must follow it."
- Can be customized in configuration
- **Optional**: Player BIOS added if `ADD_PLAYER_BIOS` enabled (line 1455):
  ```
  <player_character>
  {$GLOBALS["PLAYER_BIOS"]}
  </player_character>
  ```

**B. `<character>` section**:
- `$GLOBALS["HERIKA_PERS"]` - NPC personality from config/profile
- `$dynamicBiography` - Built by `buildDynamicBiography()` from:
  - `$GLOBALS["HERIKA_DYNAMIC"]` (if set)
  - Dynamic profile fields (relationships, goals, occupation, etc.)
  - Formatted sections:
    ```
    <relationships>...</relationships>
    <goals>...</goals>
    <occupation>...</occupation>
    ```

**C. `<knowledge>` section**:
- `$GLOBALS["OGHMA_HINT"]` - Knowledge/context hints from Oghma system
- Only included if not empty

**D. `<general_instructions>` section**:
- `$GLOBALS["COMMAND_PROMPT"]` - Built by `prompts/command_prompt.php`:
  - Default: "Don't write narrations."
  - **Plus actions list** (see Stage 2 for details) - added by `functions/json_response.php`

**E. `$rumors`**:
- Rumors/breaking news for current hold/location (lines 1479-1495)
- Format: `<{tag}>\n{content}\n</{tag}>`

**F. `$GLOBALS["PROFILE_PROMPT"]`** (if set):
- Added to dynamicBiography as `<group>#Part of a group\n{...}\n</group>`

**G. Middle-term memory** (lines 1473-1477):
- If NPC has extended data with middle_term_memory
- Added as `<middle_term_memory>#Past events\n{...}\n</middle_term_memory>`

---

### 1.2 Dialogue History Components

Built at **main.php lines 1416-1443**:

```php
$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
```

**A. `$contextDataWorld`** (line 1188):
- Built by `DataLastInfoFor("", -2, true)`
- Last 2 world/environmental info entries
- Contains location, time, nearby NPCs, etc.

**B. `$contextDataHistoric`** (lines 1178-1182):
- Built by `DataLastDataExpandedFor()`
- Last N dialogue/event entries (amount controlled by `CONTEXT_HISTORY`)
- Includes:
  - Player speech
  - NPC speech
  - Game events
  - Actions performed
  - Memory retrievals

**C. Narrator filtering** (lines 1419-1442):
- If `HIDE_NARRATOR_DIALOGUE` enabled and NPC is not The Narrator:
  - Removes narrator dialogue lines (but keeps narrator context like location changes)

**D. Book content** (lines 1447-1450):
- If event is `chatnf_book` and `BOOK_EVENT_FULL` enabled:
  - Adds full book text via `DataGetLastReadedBook()`

---

### 1.3 Final User Prompt (`$prompt[]`)

Built based on event type:

**For funcret** (function return):
```php
$prompt[] = array('role' => 'assistant', 'content' => $request);
// Then processed in processor/funcret.php
```

**For cheatmode/dialogue**:
```php
$prompt[] = array('role' => $LAST_ROLE, 'content' => $request);
```

Where `$request` contains the current user input/game event.

---

### 1.4 Final Assembly (main.php)

```php
$contextData = array_merge($head, $contextDataFull, $prompt);
```

Then passed to connector:
```php
$connectionHandler->open($contextData, $overrideParameters);
```

---

## STAGE 2: Connector Processing

### 2.1 Actions List Building

**File**: `functions/json_response.php` lines 28-59

Called by `setActions()` function:

```php
$GLOBALS["COMMAND_PROMPT"] .= "\n<available_actions_list>\n";
$GLOBALS["COMMAND_PROMPT"] .= $GLOBALS["COMMAND_PROMPT_FUNCTIONS"]; // "Use if your character needs to perform an action:"

foreach ($GLOBALS["FUNCTIONS"] as $function) {
    if (in_array($fname, $GLOBALS["ENABLED_FUNCTIONS"])) {
        $GLOBALS["COMMAND_PROMPT"] .= "\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";

        // For Attack/Brawl actions, also includes available targets:
        if ($function["name"] == Attack/Brawl/AttackHunt) {
            $GLOBALS["COMMAND_PROMPT"] .= "(available targets: {$GLOBALS["FUNCTION_PARM_INSPECT"]})";
        }
    }
}

$GLOBALS["COMMAND_PROMPT"] .= "\nAVAILABLE ACTION: Talk\n</available_actions_list>";
```

**Components**:
- `$GLOBALS["COMMAND_PROMPT_FUNCTIONS"]` - From `prompts/command_prompt.php`:
  - Default: "\n\n#Available Actions\nUse if your character needs to perform an action:"
- List of all enabled functions with descriptions
- "Talk" action always included

---

### 2.2 COMMAND_PROMPT_ENFORCE_ACTIONS Prefix

**File**: `connector/openrouterjsoncached.php` lines 375-379

```php
if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
    $prefix = isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]) ? "{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}" : "";
} else {
    $prefix = "";
}
```

**Source**: `main.php` lines 1237-1243:
```php
if (isset($GLOBALS["ENFORCE_ACTIONS_PROMPT"]) && $GLOBALS["ENFORCE_ACTIONS_PROMPT"]) {
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"] = true;

    if (isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"])) {
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"] = $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"];
    } else {
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"] = "(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
    }
}
```

**Default text**:
```
"(If {NPC_NAME} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)"
```

**Custom text** (if you have it):
- Set via `$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"]`
- Likely in your `conf.php` or language file
- **THIS IS WHERE YOUR MYSTERY TEXT IS**

---

### 2.3 Speech Style

**File**: `connector/openrouterjsoncached.php` lines 381-385

```php
if (isset($GLOBALS["HERIKA_SPEECHSTYLE"]) && !empty($GLOBALS["HERIKA_SPEECHSTYLE"])) {
    $speechReinforcement = "Use #SpeechStyle.";
} else {
    $speechReinforcement = "";
}
```

References `$GLOBALS["HERIKA_SPEECHSTYLE"]` from character profile.

---

### 2.4 Custom System Instruction

**File**: `connector/openrouterjsoncached.php` line 306

```php
$customInstruction = isset($GLOBALS["CONNECTOR"][$this->name]["custom_system_instruction"])
    ? $GLOBALS["CONNECTOR"][$this->name]["custom_system_instruction"]
    : '';
```

Set in connector configuration UI or database.

---

### 2.5 Format Instruction

**File**: `connector/openrouterjsoncached.php` lines 415-428

Combines prefix + speechReinforcement + customInstruction + format template:

**For JSON format**:
```php
"{$prefix} {$speechReinforcement} {$customInstruction} Use ONLY this JSON object to give your answer. Do not send any other characters outside of this JSON structure: " . json_encode($template)
```

**For Simple format**:
```php
buildSimpleFormatInstruction(
    $includeMood, $includeListener, $includeActions, $includeTarget,
    "{$prefix} {$speechReinforcement} {$customInstruction}"
)
```

The `buildSimpleFormatInstruction()` returns (from `connector/openrouterjsoncached_helpers.php`):
```
"{prefix} {speech} {custom} Begin your response by noting your {fields} in parentheses like this: (mood)(listener)(action)(target), then provide your dialogue naturally. Valid moods: {moods}. Example: (neutral)(Player)(Talk)(Lydia) I'm worried about that cave we passed."
```

---

### 2.6 Actions Text Assembly

**File**: `connector/openrouterjsoncached.php` lines 389-434

```php
// Build actions list if enabled
$availableActions = "";
if ($this->_includeActions && isset($GLOBALS["COMMAND_PROMPT"])) {
    $availableActions = preg_replace('/\(available targets:[^\n]*/', '', $GLOBALS["COMMAND_PROMPT"]);
}

$actionsText = "";
if (!empty($availableActions)) {
    $actionsText .= "\n" . $availableActions . "\n";
}
$actionsText .= $formatInstruction;
```

**Final structure**:
```
<available_actions_list>
AVAILABLE ACTION: Follow (...)
AVAILABLE ACTION: Attack (...)
AVAILABLE ACTION: Talk
</available_actions_list>

{$prefix} {$speechReinforcement} {$customInstruction} {format template}
```

---

### 2.7 System Message Final Assembly

**File**: `connector/openrouterjsoncached.php` line 466

```php
$finalSend = $systemContentCurrent . "\n" . $actionsText;
```

Where `$systemContentCurrent` is the original system prompt from main.php.

---

### 2.8 Dynamic Environment Context

**File**: `connector/openrouterjsoncached.php` lines 452-464, 589-600

Extracted sections:
- Environmental Context
- Additional Information
- Equipment
- Physical Appearance
- Cleanliness
- Additional Character Information
- Combat Vitals
- Arousal Status

Inserted near the end of dialogue history:
```php
$dynamicEnvironment = "ASSISTANT: Environmental Context: {extracted_text}";
// Inserted at position: count($completeEventList) - 2
```

---

### 2.9 Custom Last Instruction

**File**: `connector/openrouterjsoncached.php` lines 530-534

```php
if (!empty($lastCustomInstruction)) {
    $completeEventList[] = ['type' => 'text', 'text' => $lastCustomInstruction];
}
```

Set via `$GLOBALS["CONNECTOR"][model]["custom_last_instruction"]`

---

### 2.10 Prefill (Simple Format Only)

**File**: `connector/openrouterjsoncached.php` lines 609-616

```php
if ($this->_responseFormat === 'simple') {
    $finalMessagesToSend[] = array('role' => 'user', 'content' => $completeEventList);
    $finalMessagesToSend[] = array('role' => 'assistant', 'content' => array(
        array('type' => 'text', 'text' => '(')
    ));
}
```

Prefills the response with `(` to encourage the LLM to start with metadata.

---

## STAGE 3: Final API Payload

**File**: `connector/openrouterjsoncached.php` lines 652-703

```php
$data = array(
    'model' => $this->_model,
    'messages' => $finalMessagesToSend,
    'stream' => true,
    'temperature' => ...,
    'top_k' => ...,
    'top_p' => ...,
    'frequency_penalty' => ...,
    'presence_penalty' => ...,
    'repetition_penalty' => ...,
    'min_p' => ...,
    'top_a' => ...,
    'max_tokens' => ...,
    'reasoning' => [
        'exclude' => true,
        'enabled' => ($toggleThinking || $isAlwaysReasoning),
        'effort' => ...,
        'max_tokens' => ...
    ],
    'provider' => ['order' => [...]],
    'transforms' => []
);
```

---

## FINAL MESSAGE STRUCTURE

```json
{
  "model": "anthropic/claude-3-5-sonnet",
  "messages": [
    {
      "role": "system",
      "content": [
        {
          "type": "text",
          "text": "{PROMPT_HEAD}\n\n<character>{HERIKA_PERS}{dynamicBiography}</character>\n\n<knowledge>{OGHMA_HINT}</knowledge>\n\n<general_instructions>{COMMAND_PROMPT}</general_instructions>\n{rumors}\n\n<available_actions_list>\nAVAILABLE ACTION: Follow (...)\nAVAILABLE ACTION: Attack (...)\nAVAILABLE ACTION: Talk\n</available_actions_list>\n\n{COMMAND_PROMPT_ENFORCE_ACTIONS} {speechStyle} {customInstruction} {formatInstruction}",
          "cache_control": {"type": "ephemeral", "ttl": "1h"}
        }
      ]
    },
    {
      "role": "user",
      "content": [
        {"type": "text", "text": "{world context 1}"},
        {"type": "text", "text": "{world context 2}"},
        {"type": "text", "text": "{dialogue history 1}"},
        {"type": "text", "text": "{dialogue history 2}"},
        ...
        {"type": "text", "text": "{dialogue history N}", "cache_control": {"type": "ephemeral", "ttl": "1h"}},
        {"type": "text", "text": "ASSISTANT: Environmental Context: {dynamic environment}"},
        {"type": "text", "text": "{lastCustomInstruction}"},
        {"type": "text", "text": "{current user input}"}
      ]
    },
    {
      "role": "assistant",
      "content": [
        {"type": "text", "text": "("}
      ]
    }
  ],
  "stream": true,
  ...
}
```

---

## WHERE YOUR MYSTERY TEXT IS

Based on this analysis, your text:
> "Select the most contextually appropriate ACTION to respond to Alethia..."

**MUST be in one of these locations:**

1. **`$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"]`** - Most likely
   - Check your `conf.php` file
   - Check language files in `lang/{CORE_LANG}/prompts.php`

2. **`$GLOBALS["CONNECTOR"][model]["custom_system_instruction"]`**
   - Check connector settings in UI
   - Check database table for connector configuration

3. **`$GLOBALS["CONNECTOR"][model]["custom_last_instruction"]`**
   - Same as above

To find it, search your `conf.php` for:
```bash
grep -n "Select the most\|BeginTrading\|personality and profession" conf/conf.php
```

Or check the database:
```sql
SELECT * FROM conf_opts WHERE value LIKE '%Select the most%';
```
