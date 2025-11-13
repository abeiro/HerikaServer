# Simple Format Streaming: Complete Implementation Guide

## Document Purpose

This document provides complete context for implementing a robust "simple format" parser for streaming LLM responses. It replaces a complex offset-tracking approach that caused bugs like word concatenation ("Halfan" instead of "Half an") and message duplication.

**Status**: Design complete, ready for implementation
**Next Step**: Implement in `openrouterjsoncached.php`

---

## Background: The Problem We're Solving

### What Was Broken

The existing simple format parser used complex offset tracking:
- Separate `$sentenceBuffer` built incrementally
- Multiple offset variables: `$lastBufferLength`, `$messageStartPos`, `$consumed`
- State synchronization between buffer, offsets, and sentence tracking
- **Result**: "Halfan hour" instead of "Half an hour", duplicate text, missing messages

### Root Cause

Trying to track position in a growing buffer while also modifying a separate sentence buffer. Offset math would desync, causing:
- Characters skipped (creating "Halfan" from "Half" + "an hour")
- Characters re-added (creating duplication)
- Lost sentences (incomplete tracking)

### The Format Being Parsed

```
(mood)(listener)(action)(target): message content here.
```

**Rules**:
- Metadata is ALWAYS at the start (never mid-message)
- Each field is optional (configurable via globals)
- Metadata can contain punctuation: `(confused!)(Player)`
- Message preserves everything including leading colons
- Sentences end with: `...` (priority) > `.` > `!` > `?`

---

## Reasoning Token Preprocessing (Mandatory Step 0)

### Why This Is Necessary

The code sets `reasoning.exclude = true` in the API request (line 867), which should prevent reasoning tokens from appearing in the response stream. However, **provider heterogeneity** means this parameter isn't universally respected:

**Architecture 1: Internal Reasoning (OpenAI o1/o3/GPT-5)**
- Reasoning happens internally, never appears in output
- API's `exclude` parameter works correctly
- No preprocessing needed

**Architecture 2: Explicit Reasoning (DeepSeek-R1, Qwen3-Thinking)**
- Models output `<think>reasoning</think>` tags in actual text
- These are the model's trained output format
- API parameters cannot prevent these tags—they must be stripped client-side
- Some models use prefill for opening tag, returning only closing tag

**Conclusion**: Since OpenRouter routes to multiple providers, defensive preprocessing is mandatory. We cannot rely on API parameters alone.

### Complete List of Reasoning Markers

Based on research of production reasoning models, only these XML-style markers exist:

1. **`<think>...</think>`** - Most common (DeepSeek-R1, Qwen3)
2. **`<thinking>...</thinking>`** - Variant used by some models
3. **`<answer>...</answer>`** - Separates final answer (strip tags, keep content)

Note: Words like "Hmm", "Wait", "Therefore" are thinking indicators but not delimiters to strip.

### Three Scenarios to Handle

**Scenario 1: Normal opening tag detected**
```
Buffer: "<think>reasoning process here</think>(happy)(Player): Hello."
Action: Wait for </think>, strip entire block, proceed with "(happy)(Player): Hello."
```

**Scenario 2: Orphaned closing tag (prefill case)**
```
API prefilled: "<think>"
Model outputs: "reasoning process here</think>(happy)(Player): Hello."
Your buffer: "reasoning process here</think>(happy)(Player): Hello."
Action: Strip everything up to and including </think>, proceed with "(happy)(Player): Hello."
```

**Scenario 3: Answer tags**
```
Buffer: "<answer>Here's my response.</answer>"
Action: Strip <answer> and </answer> tags, keep content: "Here's my response."
```

### State Machine

**States**:
- `NORMAL` - No reasoning detected, process metadata normally
- `WAITING_FOR_REASONING_CLOSE` - Detected opening tag, accumulating until closing tag found

**Transitions**:
- `NORMAL → WAITING_FOR_REASONING_CLOSE`: When buffer starts with `<think` or `<thinking`
- `WAITING_FOR_REASONING_CLOSE → NORMAL`: When closing tag found, after stripping

**Critical**: Check for orphaned closing tags on EVERY `process()` call, not just initially. This prevents timeout conflicts.

**Important Note**: This preprocessing only applies to simple format. JSON format continues to use the existing `stripReasoningTokens()` function (defined in `tokenizer_helper_functions.php`) which handles reasoning tokens differently for structured JSON responses.

### Answer Tag Handling

Answer tags wrap the final response but shouldn't be displayed:
```php
// After stripping reasoning, also strip answer tags
$buffer = preg_replace('/<answer>(.*?)<\/answer>/s', '$1', $buffer);
```

This preserves content between tags while removing the tags themselves.

### Timeout Interaction

**CRITICAL**: Orphaned closing tag detection must run BEFORE the 100-character timeout check:

```
1. Check for orphaned </think> or </thinking> → strip immediately if found
2. Check if buffer > 100 chars without metadata+sentence → timeout fallback
```

Without this ordering, reasoning text with punctuation would trigger incorrect sentence splits during timeout fallback.

---

## The Solution: Ultra-Simple Architecture

### Core Principle

**After stripping reasoning tokens, don't separate metadata detection from sentence detection.**

Instead of:
1. Detect metadata boundaries → 2. Validate complete → 3. Search for sentences

Do this:
1. Search for "consecutive `(...)` groups at start + complete sentence after"
2. If found: extract both
3. If not: wait for next chunk

### Why This Works

- Ambiguous cases (like `(confused.`) naturally wait for clarification
- No "readiness" heuristics needed
- Edge cases become non-issues - they just wait
- Within ~20-50ms, next chunk resolves ambiguity
- Only one clear signal: complete sentence exists

---

## How OpenRouter Streaming Actually Works

**VERIFIED**: Each chunk contains ONLY new (delta) text, not cumulative.

```
Chunk 1: "(happy)("
Chunk 2: "Player): Hello"
Chunk 3: " world."
```

Accumulation: `$buffer .= $chunk` naturally preserves all characters including spaces.

**Trailing Spaces**: 
- Middle chunks: CAN have trailing spaces
- Last chunk: Probably DOESN'T
- Detection pattern must handle both: `/[.!?](?:\s+|$)/`

---

## Complete Algorithm

### State Variables (Instance Variables, Not Static)

```php
private $_buffer = '';              // Raw accumulated chunks
private $_reasoningState = 'NORMAL'; // Reasoning detection state: 'NORMAL' or 'WAITING_FOR_REASONING_CLOSE'
private $_reasoningTagType = '';    // Which tag we're waiting for: 'think' or 'thinking'
private $_metadataEnd = -1;         // Position where message starts (-1 = not found)
private $_sentencesSent = 0;        // Count of sentences already returned
private $_metadataGroups = [];      // Extracted field values
private $_usedPrefill = false;      // Did we send "(" as prefill?
```

**Why Instance Variables**: The existing codebase (`openrouterjsoncached.php`) uses instance variables throughout. Maintains consistency and prevents state pollution if connector is reused. The connector object persists for the entire stream, so instance variables naturally maintain state across `process()` calls without needing `static` declarations.

### process() Algorithm

```
0. REASONING PREPROCESSING (runs every call):
   a) Check for orphaned closing tag (prefill case):
      └─ Pattern: /<\/(think|thinking)>/
      └─ If found and $_reasoningState == 'NORMAL':
          ├─ Strip everything from buffer start to end of closing tag
          ├─ Continue to next step with cleaned buffer
   
   b) Handle reasoning state machine:
      └─ IF $_reasoningState == 'WAITING_FOR_REASONING_CLOSE':
          ├─ Search for closing tag: </$_reasoningTagType>
          ├─ If found:
          │   ├─ Strip everything from buffer start to end of closing tag
          │   ├─ $_reasoningState = 'NORMAL'
          │   └─ Continue to next step with cleaned buffer
          └─ If NOT found: return "" (wait for more chunks)
      
      └─ IF $_reasoningState == 'NORMAL':
          ├─ Check if buffer starts with <think or <thinking
          ├─ If YES:
          │   ├─ Extract tag type (think or thinking)
          │   ├─ $_reasoningState = 'WAITING_FOR_REASONING_CLOSE'
          │   ├─ $_reasoningTagType = extracted type
          │   └─ Continue to step (b) above (might complete in same iteration)
          └─ If NO: continue to step 1
   
   c) Strip answer tags (if present):
      └─ Pattern: /<answer>(.*?)<\/answer>/s
      └─ Replace with captured group (content between tags)

1. Read new chunk from SSE stream
   └─ If no data: return ""

2. Accumulate: $this->_buffer .= $chunk

3. Normalize for prefill:
   └─ If $_usedPrefill AND buffer doesn't start with "(": prepend "("
   └─ All operations use normalized buffer

4. Extract metadata section (if not already done):
   └─ If $_metadataEnd == -1:
       ├─ Check config: Are ALL metadata fields disabled?
       │  └─ If yes: $_metadataEnd = 0 (no metadata)
       │
       ├─ Pattern: /^\s*(?:\([^)]*\)\s*)+/
       │  └─ Matches: consecutive (...) groups at START
       │  └─ Stops at first non-metadata character
       │
       ├─ Take everything AFTER pattern match as potential message
       │
       ├─ Search message for sentence: /\.\.\.(?:\s+|$)|[.!?](?:\s+|$)/
       │  └─ Priority: ... > . > ! > ?
       │  └─ Matches end-of-buffer ($) for last chunk without trailing space
       │
       └─ If sentence found:
           ├─ $_metadataEnd = length of metadata section
           ├─ Extract groups: preg_match_all('/\(([^)]*)\)/', metadata)
           ├─ Map to fields: mood → listener → action → target (skip disabled)
           └─ Set globals: SCRIPTLINE_ANIMATION, SCRIPTLINE_EXPRESSION, etc.
       
       └─ If NO sentence found: return "" (wait for more chunks)

5. TIMEOUT FALLBACK (runs after Step 4):
   └─ If buffer > 100 characters AND $_metadataEnd still == -1:
       ├─ Log warning: "Simple format timeout - LLM didn't follow format"
       ├─ Split buffer by punctuation: /(?<=\.\.\.)\s+|(?<=[.!?])\s+/
       ├─ Filter: Keep only sentences ending with punctuation
       ├─ Set $_metadataEnd = 0 (no metadata found)
       ├─ Set $_sentencesSent = 0
       └─ Continue to Step 6 with split sentences

6. Extract message portion:
   └─ $message = substr($normalizedBuffer, $_metadataEnd)

7. Split message into sentences:
   └─ Pattern: /(?<=\.\.\.)\s+|(?<=[.!?])\s+/
   └─ Note: No $ here! Only splits on actual spaces, not end-of-buffer
   └─ Filter: Keep only sentences ending with /[.!?…]+$/

8. Return next unsent sentence:
   └─ If $_sentencesSent < count($sentences):
       ├─ $sentence = $sentences[$_sentencesSent]
       ├─ $_sentencesSent++
       └─ return $sentence (no stripReasoningTokens needed - already done in Step 0)
   └─ Else: return ""
```

### close() Algorithm

```
0. REASONING PREPROCESSING (same as process() Step 0):
   └─ Strip any remaining reasoning tags from buffer
   └─ Strip answer tags, preserving content
   └─ If reasoning state is still WAITING, complete reasoning was never received
       (log warning but continue)

1. Normalize buffer (same as process())

2. Extract full message from $_metadataEnd
   └─ If $_metadataEnd == -1: nothing to flush, exit

3. Split into complete sentences (same as process())

4. FLUSH UNSENT COMPLETE SENTENCES:
   └─ While $_sentencesSent < count($sentences):
       ├─ $sentence = $sentences[$_sentencesSent++]
       └─ [Send this sentence via whatever mechanism process() uses]

5. FIND TRAILING PARTIAL:
   └─ Search for last punctuation in message: /[.!?…]/
   └─ If found: $partial = trim(substr($message, $lastPunctPos + 1))
   └─ If not found: $partial = trim($message)

6. HANDLE PARTIAL:
   └─ If non-empty AND doesn't end with punctuation:
       ├─ $partial .= '.'
       └─ [Send as final sentence]
```

**Critical**: close() must flush in this order:
1. Unsent complete sentences FIRST
2. Then trailing partial
Otherwise complete-but-unsent sentences might be treated as partial.

---

## Key Design Decisions (All Resolved)

### 1. Metadata Detection: Start-Anchored ✓

**Pattern**: `/^\s*(?:\([^)]*\)\s*)+/`

**Plain English**: "Starting from the beginning, skip whitespace, then find one or more of: opening paren, content (anything except closing paren), closing paren, optional whitespace."

**Why**: 
- Metadata is ALWAYS at the start
- This prevents catching message parentheses like `(world)` or `(pauses)`
- Explicitly anchored with `^`

**Handles Mismatches**:
- More groups than enabled fields: extras ignored
- Fewer groups than enabled fields: later fields stay empty
- Empty fields like `()`: captured as empty strings

### 2. Two Regex Patterns Required ✓

**Detection Pattern** (finds IF sentence exists):
```regex
/\.\.\.(?:\s+|$)|[.!?](?:\s+|$)/
```
- Matches space OR end-of-buffer (`$`)
- Handles last chunk without trailing space

**Splitting Pattern** (divides into array):
```regex
/(?<=\.\.\.)\s+|(?<=[.!?])\s+/
```
- Matches space ONLY (no `$`)
- Using `$` here would incorrectly split at buffer end

**Why Both**: Detection asks "is there a sentence?" (boolean). Splitting asks "where do sentences break?" (positions). Different questions need different tools.

### 3. Ellipsis Priority ✓

In patterns, `...` appears FIRST (leftmost) in alternation: `/\.\.\.(?:\s+|$)|[.!?](?:\s+|$)/`

This ensures `"Wait... okay"` splits after `...`, not at the `.` inside.

### 4. Instance Variables, Not Static ✓

**Confirmed**: Existing code uses `private $_variable` throughout. We follow this pattern.

**Why**: Prevents state pollution if connector is reused across responses (though user reports no bleed-through observed in practice).

### 5. Normalization for Prefill ✓

Code sends `"("` as prefill. LLM may return `"happy)..."` or `"(happy)..."`.

**Solution**:
```php
$normalizedBuffer = $this->_buffer;
if ($this->_usedPrefill && !empty($normalizedBuffer) && $normalizedBuffer[0] !== '(') {
    $normalizedBuffer = '(' . $normalizedBuffer;
}
// ALL operations use $normalizedBuffer for consistency
```

### 6. No Metadata Case ✓

If ALL metadata fields disabled in config:
- Set `$_metadataEnd = 0` immediately
- Entire buffer is message
- Skip metadata extraction entirely

Check: `if (!$_includeMood && !$_includeListener && !$_includeActions && !$_includeTarget)`

---

## Edge Cases Verified

### Reasoning Token Edge Cases

| Scenario | Handling |
|----------|----------|
| `<think>reasoning</think>(happy): Done.` | Strips reasoning, processes `(happy): Done.` |
| `reasoning here</think>(happy): Done.` | Strips orphaned closing (prefill case) |
| `<thinking>long reasoning` (incomplete) | Waits for `</thinking>` across multiple chunks |
| `<answer>Final response</answer>` | Strips tags, keeps `Final response` |
| `<think>with. punctuation!</think>` | Strips entire block, punctuation inside doesn't trigger sentence detection |
| Reasoning never closes (stream ends) | close() logs warning, proceeds with whatever remains after unclosed tag |
| Mixed: `<think>...</think><answer>text</answer>` | Strips both correctly |

### Metadata and Sentence Edge Cases

| Scenario | Handling |
|----------|----------|
| `(confused.` (incomplete metadata) | Waits for next chunk (no sentence found) |
| `(happy)(Player)(Talk.` | Waits (no sentence after metadata) |
| `(confused!)(Player): Done.` | Processes correctly (metadata protected from split) |
| `(): I am here.` | Works (empty target field) |
| Multiple sentences in one chunk | Returns first, others wait for next call |
| Stream ends mid-sentence | close() adds period |
| No metadata (all disabled) | Entire buffer is message |
| Prefill not returned | Normalization adds it |
| `(happy): Hello (world).` | `(world)` stays in message (start-anchor) |
| Leading colons preserved | `: Hello` keeps the `:` |
| Buffer > 100 chars, no format found | Timeout: split by punctuation without metadata |

---

## Comparison: Old vs New

| Aspect | Old Approach | New Approach |
|--------|-------------|--------------|
| Buffer management | Separate `$sentenceBuffer` | Single `$_buffer` |
| Position tracking | Multiple offsets | One index counter |
| Metadata detection | Separate "readiness" logic | Combined with sentence detection |
| Reasoning handling | `stripReasoningTokens()` after parsing | `_preprocessReasoningTags()` before parsing (simple format only) |
| State complexity | 8+ variables to sync | 7 variables (5 core + 2 reasoning state) |
| Failure mode | Offset desync | Wait for next chunk |
| "Halfan" bug | Offset math error | Impossible (no offset math) |
| Duplication | Offset desync | Impossible (index-based) |
| Timeout fallback | None | 100-char threshold with punctuation split |

---

## Implementation Checklist

### Files to Modify

1. **`openrouterjsoncached.php`**: Main connector class
   - Replace simple format block in `_parseAndReturnContent()`
   - Update `close()` method
   - Add/verify instance variables

2. **`openrouterjsoncached_helpers.php`**: Helper functions
   - May need new helper for metadata extraction
   - Existing `stripReasoningTokens()` stays

### Step-by-Step Implementation

#### Step 1: Add Instance Variables

In class declaration, verify these exist:
```php
private $_buffer = '';
private $_reasoningState = 'NORMAL';
private $_reasoningTagType = '';
private $_metadataEnd = -1;
private $_sentencesSent = 0;
private $_metadataGroups = [];
private $_usedPrefill = false;
```

#### Step 2: Implement Reasoning Preprocessing

Create helper method (or inline in process()):
```php
private function _preprocessReasoningTags() {
    // Check for orphaned closing tag (prefill case)
    if ($this->_reasoningState === 'NORMAL') {
        if (preg_match('/<\/(think|thinking)>/', $this->_buffer, $matches, PREG_OFFSET_CAPTURE)) {
            $closePos = $matches[0][1];
            $closeLen = strlen($matches[0][0]);
            $this->_buffer = substr($this->_buffer, $closePos + $closeLen);
            // Continue to state machine check below
        }
    }
    
    // State machine for reasoning tags
    while (true) {
        if ($this->_reasoningState === 'WAITING_FOR_REASONING_CLOSE') {
            $closeTag = '</' . $this->_reasoningTagType . '>';
            $closePos = strpos($this->_buffer, $closeTag);
            
            if ($closePos !== false) {
                // Found closing tag - strip everything up to and including it
                $this->_buffer = substr($this->_buffer, $closePos + strlen($closeTag));
                $this->_reasoningState = 'NORMAL';
                $this->_reasoningTagType = '';
                // Continue loop - might have more reasoning or answer tags
            } else {
                // Still waiting for closing tag
                return false; // Signal: need more chunks
            }
        } else { // NORMAL state
            // Check if buffer starts with reasoning tag
            if (preg_match('/^<(think|thinking)>/i', $this->_buffer, $matches)) {
                $this->_reasoningTagType = strtolower($matches[1]);
                $this->_reasoningState = 'WAITING_FOR_REASONING_CLOSE';
                // Continue loop - might complete in same iteration
            } else {
                break; // No reasoning tag at start, exit loop
            }
        }
    }
    
    // Strip answer tags (preserve content)
    $this->_buffer = preg_replace('/<answer>(.*?)<\/answer>/is', '$1', $this->_buffer);
    
    return true; // Signal: ready to continue
}
```

#### Step 3: Implement Metadata Extraction

Create helper method (or inline in process()):
```php
private function _extractMetadata($normalizedBuffer) {
    // Check if all fields disabled
    if (!$this->_includeMood && !$this->_includeListener && 
        !$this->_includeActions && !$this->_includeTarget) {
        return [
            'found' => true,
            'metadataEnd' => 0,
            'groups' => []
        ];
    }
    
    // Find consecutive (...) at start
    if (!preg_match('/^\s*(?:\([^)]*\)\s*)+/', $normalizedBuffer, $match)) {
        return ['found' => false];
    }
    
    $metadataSection = $match[0];
    $metadataEnd = strlen($metadataSection);
    $potentialMessage = substr($normalizedBuffer, $metadataEnd);
    
    // Search for sentence in message
    if (!preg_match('/\.\.\.(?:\s+|$)|[.!?](?:\s+|$)/', $potentialMessage)) {
        return ['found' => false]; // No sentence yet, wait
    }
    
    // Extract groups
    preg_match_all('/\(([^)]*)\)/', $metadataSection, $matches);
    
    return [
        'found' => true,
        'metadataEnd' => $metadataEnd,
        'groups' => $matches[1]
    ];
}
```

#### Step 4: Implement Group Mapping

```php
private function _mapGroupsToFields($groups) {
    $idx = 0;
    
    if ($this->_includeMood && isset($groups[$idx])) {
        $mood = trim($groups[$idx++]);
        if ($mood !== '') {
            $GLOBALS["SCRIPTLINE_ANIMATION"] = function_exists('GetAnimationHex') 
                ? GetAnimationHex($mood) : '';
            $GLOBALS["SCRIPTLINE_EXPRESSION"] = function_exists('GetExpression') 
                ? GetExpression($mood) : '';
        }
    }
    
    if ($this->_includeListener && isset($groups[$idx])) {
        $listener = trim($groups[$idx++]);
        if ($listener !== '') {
            $GLOBALS["SCRIPTLINE_LISTENER"] = $listener;
        }
    }
    
    if ($this->_includeActions && isset($groups[$idx])) {
        $action = trim($groups[$idx++]);
        if ($action !== '' && strcasecmp($action, 'Talk') !== 0) {
            $action = validateActionName($action);
            $target = $this->_includeTarget && isset($groups[$idx]) 
                ? trim($groups[$idx]) 
                : $this->_defaultTarget;
            $character = $GLOBALS["HERIKA_NAME"] ?? 'Herika';
            $commandKey = md5("{$character}|command|{$action}@{$target}\r\n");
            if (!isset($GLOBALS['alreadysent'][$commandKey])) {
                $func = function_exists('getFunctionCodeName') 
                    ? getFunctionCodeName($action) 
                    : $action;
                $cmd = "{$character}|command|{$func}@{$target}\r\n";
                $this->_commandBuffer[] = $cmd;
                $GLOBALS['alreadysent'][$commandKey] = $cmd;
            }
        }
    }
}
```

#### Step 5: Implement Sentence Splitting

```php
private function _splitIntoSentences($text) {
    // Split on sentence endings (requires space, no end-of-buffer)
    $parts = preg_split('/(?<=\.\.\.)\s+|(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    // Filter: keep only those ending with punctuation
    $sentences = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/[.!?…]+$/', $part)) {
            $sentences[] = $part;
        }
    }
    
    return $sentences;
}
```

#### Step 5: Replace Simple Format Block in _parseAndReturnContent()

Find the `else` block for simple format (around line 1155-1250 based on document structure). Replace entirely with:

```php
} else {
    // Simple format parsing
    
    // Normalize for prefill
    $normalizedBuffer = $this->_buffer;
    if ($this->_usedPrefill && !empty($normalizedBuffer) && $normalizedBuffer[0] !== '(') {
        $normalizedBuffer = '(' . $normalizedBuffer;
    }
    
    // Extract metadata (one-time operation)
    if ($this->_metadataEnd === -1) {
        $result = $this->_extractMetadata($normalizedBuffer);
        
        if (!$result['found']) {
            return ""; // Wait for more chunks
        }
        
        $this->_metadataEnd = $result['metadataEnd'];
        $this->_metadataGroups = $result['groups'];
        $this->_mapGroupsToFields($result['groups']);
    }
    
    // Extract message portion
    $message = substr($normalizedBuffer, $this->_metadataEnd);
    
    // Split into sentences
    $sentences = $this->_splitIntoSentences($message);
    
    // Return next unsent sentence
    if ($this->_sentencesSent < count($sentences)) {
        $sentence = $sentences[$this->_sentencesSent];
        $this->_sentencesSent++;
        return stripReasoningTokens($sentence);
    }
    
    return "";
}
```

#### Step 7: Update close() Method

Find the `close()` method. Before the final cleanup, add:

```php
// Flush remaining content from simple format
if ($this->_responseFormat === 'simple') {
    
    // Step 0: Final reasoning cleanup
    if ($this->_reasoningState === 'WAITING_FOR_REASONING_CLOSE') {
        logMessage("[{$this->name}] Warning: Reasoning tag never closed, proceeding with partial buffer");
    }
    // Strip any remaining reasoning/answer tags
    $this->_preprocessReasoningTags();
    
    // Continue only if we have metadata
    if ($this->_metadataEnd === -1) {
        return ""; // Nothing to flush
    }
    
    // Normalize buffer
    $normalizedBuffer = $this->_buffer;
    if ($this->_usedPrefill && !empty($normalizedBuffer) && $normalizedBuffer[0] !== '(') {
        $normalizedBuffer = '(' . $normalizedBuffer;
    }
    
    // Extract full message
    $message = substr($normalizedBuffer, $this->_metadataEnd);
    
    // Split into sentences
    $sentences = $this->_splitIntoSentences($message);
    
    // Flush unsent complete sentences
    while ($this->_sentencesSent < count($sentences)) {
        $sentence = $sentences[$this->_sentencesSent];
        $this->_sentencesSent++;
        // TODO: How to send? Check how process() returns are handled
        // Might need to accumulate and return, or call a send method
    }
    
    // Find trailing partial
    if (preg_match_all('/[.!?…]/', $message, $matches, PREG_OFFSET_CAPTURE)) {
        $lastMatch = end($matches[0]);
        $lastPunctPos = $lastMatch[1];
        $partial = trim(substr($message, $lastPunctPos + 1));
    } else {
        $partial = trim($message);
    }
    
    // Handle partial (add period if needed)
    if (!empty($partial) && !preg_match('/[.!?…]$/', $partial)) {
        $partial .= '.';
        // TODO: Send $partial
    }
}
```

**⚠️ CRITICAL UNKNOWN**: How does close() actually send the flushed sentences? Need to examine how the caller handles process() returns to determine the mechanism. Might be:
- Accumulate in array and return
- Call a helper method
- Write to a buffer that's read elsewhere

---

## Testing Strategy

### Unit Tests

1. **Metadata Extraction**:
   - All fields enabled: `(happy)(Player)(Talk)(Lydia): Hello.`
   - Some disabled: `(happy): Hello.` with listener/action/target disabled
   - Empty fields: `()(Player)(): Hello.`
   - No metadata: `Hello.` with all disabled
   - Metadata with punctuation: `(confused!)(What now?)(Player): Hello.`

2. **Sentence Detection**:
   - Single sentence: `Hello world.`
   - Multiple sentences: `Hello. World. Goodbye.`
   - Ellipsis: `Wait... okay then.`
   - No trailing space: `Hello.` (buffer end)
   - Partial: `Hello wor` (no punctuation)

3. **Edge Cases**:
   - Incomplete metadata: `(happ`
   - Ambiguous: `(confused.`
   - Message parentheses: `(happy): Hello (world).`
   - Prefill variants: with/without opening `(`
   - Timeout: Buffer exceeds 100 chars without format detection

### Integration Tests

1. Stream a full response through actual connector
2. Verify no "Halfan" concatenation
3. Verify no duplication
4. Verify close() flushes everything
5. Check multiple sentences in one chunk
6. Verify metadata → globals mapping

---

## Known Limitations and Future Considerations

### Intentional Trade-offs

1. **Multi-sentence chunks**: If one chunk contains two complete sentences, only one is returned per process() call. The second waits for the next chunk to arrive (or close() to flush it). This adds ~20-50ms delay but eliminates state complexity.

2. **Conservative waiting**: Ambiguous cases like `(confused.` wait for the next chunk rather than trying to guess. This is safe but adds one chunk of latency in rare cases.

3. **Reasoning preprocessing overhead**: Every process() call in simple format checks for reasoning tags, adding microseconds of regex overhead. This is negligible compared to network latency but worth noting. JSON format continues to use `stripReasoningTokens()` at the end of parsing.

### Not Addressed Yet

1. **close() sending mechanism**: Implementation unclear from documentation. Need to examine caller to determine how to send the flushed sentences.

2. **Performance profiling**: Re-splitting on every process() call is assumed cheap (microseconds on small strings). Should profile if this becomes a bottleneck.

3. **Connector reuse verification**: User reports no state bleed-through, but actual connector lifecycle (new per response? reused?) not verified in code.

4. **Reasoning tag variants**: Currently handles `<think>`, `<thinking>`, and `<answer>`. If new models introduce different tags, add them to the preprocessing regex patterns.

---

## Instructions for Next Claude Instance

### Your Task

Implement the algorithm described above in `openrouterjsoncached.php` and verify it works correctly.

### What You Have

1. **This document**: Complete architecture, decisions, and pseudocode
2. **PHP files**: User will provide `openrouterjsoncached.php` and `openrouterjsoncached_helpers.php`
3. **Context**: The simple format parser needs to be replaced entirely

### What You Need to Do

1. **Read both PHP files carefully**
   - Understand the existing structure
   - Find where simple format parsing happens (search for `$this->_responseFormat === 'simple'`)
   - Identify how process() returns are handled by the caller
   - Determine close() sending mechanism

2. **Implement the algorithm**
   - Follow the pseudocode in "Implementation Checklist" section above
   - Create the helper methods:
     - `_preprocessReasoningTags()` - Handles reasoning tag preprocessing (renamed to avoid conflicts)
     - `_extractMetadata()` - Detects and extracts metadata fields
     - `_mapGroupsToFields()` - Maps extracted groups to global variables
     - `_splitIntoSentences()` - Splits message by sentence boundaries
   - Replace the simple format block in `_parseAndReturnContent()`
   - Update `close()` to flush remaining content

3. **Critical Questions to Answer First**:
   - How does close() send sentences? (Check how process() strings are consumed)
   - Are there any existing helper functions we should reuse?
   - Is there validation logic for actions/mood that we should preserve?
   - What's the actual connector lifecycle? (Search for `new openrouterjsoncached()`)

4. **Verify Against Edge Cases**
   - Test with the scenarios in "Testing Strategy"
   - Ensure no "Halfan" concatenation
   - Ensure no duplication
   - Verify close() flushes everything

5. **Maintain Existing Functionality**
   - Don't break JSON format (it works fine, including its `stripReasoningTokens()` calls)
   - Preserve all the reasoning token handling for JSON format
   - Keep the caching logic intact
   - Maintain the mood/listener/action globals setting
   - Simple format gets new `_preprocessReasoningTags()`, JSON format keeps existing `stripReasoningTokens()`

### What NOT to Do

- Don't add complexity beyond what's described here
- Don't try to optimize prematurely
- Don't change the JSON format code path (including its use of `stripReasoningTokens()`)
- Don't add new features (just fix simple format)
- Don't use static variables (use instance variables)
- Don't remove `stripReasoningTokens()` calls from JSON format - only simple format uses `_preprocessReasoningTags()`

### If You Get Stuck

1. **Missing information**: Ask user for clarification
2. **Ambiguous code**: Comment your assumptions and flag for review
3. **Trade-offs**: Choose simplicity over cleverness
4. **Uncertain behavior**: Add logging and test empirically

### Success Criteria

The implementation is complete when:
1. Reasoning tokens are stripped correctly (both normal and prefill cases) in simple format only
2. No "Halfan" concatenation bugs
3. No duplicate text
4. No lost sentences
5. Metadata correctly extracted and mapped
6. All edge cases handled gracefully (see Edge Cases tables)
7. close() flushes remaining content
8. Timeout fallback works when LLM ignores format (triggers at 100 chars)
9. Existing JSON format still works (including its `stripReasoningTokens()` calls)

### Final Note

This design conversation went through many iterations to reach this simplicity. The key insight is: **wait when ambiguous, only process when certain**. Don't second-guess this principle. If something seems too simple, that's because we already did the hard work of removing unnecessary complexity.

**Reasoning Preprocessing**: The addition of Step 0 (reasoning token stripping) is a defensive measure against provider heterogeneity. While the API's `reasoning.exclude = true` should work, some models output explicit `<think>` tags that must be stripped client-side. This preprocessing is mandatory for simple format only—JSON format continues to use the existing `stripReasoningTokens()` function which handles reasoning differently for structured responses.

**Timeout Threshold**: The 100-character threshold provides a reasonable balance—long enough to wait for format markers across 1-2 chunks, but short enough to fail fast if the LLM completely ignores format instructions.

**Instance Variables**: Confirmed that the existing codebase uses instance variables throughout, not static. The connector object persists for the entire stream, naturally maintaining state across `process()` calls.

Good luck! The architecture is sound. Focus on correct implementation of what's described here.

---

## Appendix: Regex Patterns Quick Reference

```regex
Reasoning tag detection (opening):
^<(think|thinking)>

Reasoning tag detection (orphaned closing):
</(think|thinking)>

Answer tag stripping (preserve content):
<answer>(.*?)</answer>

Metadata detection (start-anchored):
^\s*(?:\([^)]*\)\s*)+

Sentence detection (with end-of-buffer):
\.\.\.(?:\s+|$)|[.!?](?:\s+|$)

Sentence splitting (space only):
(?<=\.\.\.)\s+|(?<=[.!?])\s+

Completeness filter:
[.!?…]+$

Group extraction from metadata:
\(([^)]*)\)
```

Each serves a specific purpose - don't conflate them.