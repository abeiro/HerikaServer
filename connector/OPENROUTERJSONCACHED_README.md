# OpenRouter JSON Cached Connector

A fully-featured cached version of the OpenRouter JSON connector for CHIM 2.0, with support for multiple caching providers, flexible response formats, and granular content controls.

## Features

### Core Caching
- **Multi-Provider Support**: Anthropic, OpenAI (o1/o3/o4-mini models), and Gemini
- **File-Based Caching**: Separate caching for system prompts and dialogue history
- **Dynamic Content Extraction**: Automatically separates static character info from dynamic environment data
- **Cache Performance Logging**: Track cache efficiency with detailed metrics

### Response Format Flexibility
- **JSON Mode** (default): Structured JSON responses with full validation
- **Simple Mode**: Natural language format with parenthetical metadata like `(mood)(listener)(action)(target) message`

### Granular Content Controls
- Toggle individual response components on/off:
  - Actions list
  - Mood requirement
  - Target requirement
  - Listener requirement
- Fine-tune uncached dialogue count for optimal performance

## Configuration

Add this connector to your CHIM configuration:

```php
$GLOBALS["CONNECTOR"]["openrouterjsoncached"] = [
    // Basic Configuration
    "url" => "https://openrouter.ai/api/v1/chat/completions",
    "API_KEY" => "your-api-key-here",
    "model" => "anthropic/claude-3-5-sonnet-20241022",

    // Caching Configuration
    "provider_caching" => "Anthropic",  // Options: "Anthropic", "OpenAI", "Gemini"
    "max_dialogue_cache_context_size" => 200,  // Max dialogue history items to cache
    "dialogue_cache_uncached_count" => 4,  // Keep last N messages uncached (for freshness)

    // Response Format
    "response_format" => "json",  // Options: "json", "simple"

    // Granular Content Controls
    "include_actions_list" => true,  // Include available actions in prompt
    "include_mood_requirement" => true,  // Require mood in response
    "include_target_requirement" => true,  // Require action target in response
    "include_listener_requirement" => true,  // Require listener in response

    // Standard OpenRouter Options
    "max_tokens" => 4096,
    "temperature" => 1.0,
    "top_p" => 1.0,
    "top_k" => 0,
    "frequency_penalty" => 0,
    "presence_penalty" => 0,
    "repetition_penalty" => 1,
    "min_p" => 0,
    "top_a" => 0,

    // Reasoning/Thinking Support
    "toggle_thinking" => false,
    "thinking_tokens" => 1000,
    "effort_level" => "low",  // For OpenAI models: "low", "medium", "high"

    // Custom Instructions
    "custom_last_instruction" => "",
    "custom_last_user_instruction" => "",

    // Provider Settings
    "PROVIDER" => "Anthropic",  // Comma-separated list for fallbacks
];
```

## Cache Provider Details

### Anthropic Caching
- Uses `cache_control` with ephemeral cache (1 hour TTL)
- Caches system prompts automatically
- Places cache breakpoint based on `dialogue_cache_uncached_count`
- Requires `anthropic-beta: extended-cache-ttl-2025-04-11` header
- Best for Claude models

### OpenAI Caching
- Compatible with o1, o3, o4-mini reasoning models
- Uses model-native caching (automatically handled)
- Set `provider_caching` to "OpenAI" to disable manual cache control markers
- Effort-based reasoning: "low", "medium", "high"

### Gemini Caching
- Uses batch-based caching strategy
- Calculates cache index based on `CONTEXT_HISTORY` global
- Optimal for long conversation contexts
- Automatically adjusts cache placement

## Response Format Modes

### JSON Mode (Default)
```json
{
    "mood": "concerned",
    "listener": "Player",
    "action": "Talk",
    "target": "Player",
    "message": "I'm worried about that cave we just passed."
}
```

**When to use**: Maximum structure, easier debugging, full validation

### Simple Mode
```
(concerned)(Player)(Talk)(Player) I'm worried about that cave we just passed.
```

**When to use**: More natural LLM output, lower token usage, faster responses

**Configuration**: Set `response_format` to `"simple"` and configure which components to include

## Content Control Examples

### Minimal Configuration (Dialogue Only)
```php
"response_format" => "simple",
"include_actions_list" => false,
"include_mood_requirement" => false,
"include_target_requirement" => false,
"include_listener_requirement" => false,
```
**Result**: Pure dialogue with no metadata

### Action-Focused Configuration
```php
"response_format" => "json",
"include_actions_list" => true,
"include_mood_requirement" => false,
"include_target_requirement" => true,  // Required with actions
"include_listener_requirement" => false,
```
**Result**: Character performs actions without mood/listener requirements

### Full Featured (Default)
```php
"response_format" => "json",
"include_actions_list" => true,
"include_mood_requirement" => true,
"include_target_requirement" => true,
"include_listener_requirement" => true,
```
**Result**: All features enabled

## Caching Behavior

### System Prompt Caching
- Stored in: `temp/system_cache_json_{character}.tmp`
- Cache duration: 1 hour
- **What's cached**: Character personality, backstory, game rules, action definitions
- **What's excluded**: Environmental context, equipment, combat vitals (dynamic data)

### Dialogue History Caching
- Stored in: `temp/combined_dialogue_cache_json_{character}.tmp`
- Max entries: Configured by `max_dialogue_cache_context_size`
- Auto-clears: After 1 hour or when max length exceeded
- **Fresh messages**: Last N messages (configured by `dialogue_cache_uncached_count`) remain uncached
- **Deduplication**: Removes neighboring duplicates and duplicate memories

### Cache Performance
Monitor caching efficiency in `connector/_cached_perf.log`:
```
[2025-01-15 10:30:45] CACHE_PERF Lydia: Read:5240 Create:0 New:120 Total:5360 Efficiency:97.8%
```
- **Read**: Tokens loaded from cache (cheap)
- **Create**: Tokens written to cache (one-time cost)
- **New**: Fresh tokens (not cached)
- **Efficiency**: Percentage of input from cache

## Integration with CHIM 2.0

### Connector Assignment Flow
```
Global Settings → LLM Connectors → Profiles → NPC
```

1. **Define Connector**: Add to globals with configuration
2. **Create Profile**: Assign connector to profile
3. **Assign to NPC**: Link profile to specific NPCs

Settings cascade from NPC → Profile → Connector → Global, with NPC settings taking highest priority.

### Example Profile Setup
```php
$GLOBALS["PROFILES"]["cached_claude"] = [
    "connector" => "openrouterjsoncached",
    "provider_caching" => "Anthropic",
    "response_format" => "json",
    // Profile-specific overrides
];
```

## Troubleshooting

### Cache Not Working
1. Check `temp/` directory exists and is writable
2. Verify `provider_caching` matches your model provider
3. Check logs in `log/cache.log` for errors
4. Ensure API key has cache access (Anthropic tier requirements)

### Low Cache Efficiency
- Increase `max_dialogue_cache_context_size`
- Decrease `dialogue_cache_uncached_count` (but keep >2 for freshness)
- Check if dynamic content is being re-cached (should be extracted)

### Simple Format Not Parsing
- Ensure LLM is using parentheses: `(value)(value)`
- Check `log/cache.log` for parsing errors
- Try enabling fewer components initially
- Verify `include_*` settings match expected format

### Actions Not Triggering
- Ensure `include_actions_list` is `true`
- Verify `include_target_requirement` is `true` (required for actions)
- Check `processActions()` logs in `log/cache.log`
- Confirm action name is in valid actions list

## Performance Tips

1. **Optimal Cache Settings**:
   - `max_dialogue_cache_context_size`: 150-250 for most uses
   - `dialogue_cache_uncached_count`: 3-5 for balance

2. **Format Selection**:
   - Use JSON for complex interactions, debugging
   - Use Simple for faster responses, lower costs

3. **Content Controls**:
   - Disable unused features to reduce prompt size
   - Actions list is largest component (~500-1000 tokens)

4. **Provider Selection**:
   - Anthropic: Best cache hit rates, longest TTL
   - OpenAI: Use for o1/o3 reasoning models only
   - Gemini: Best for very long conversations

## Files Created

- `connector/openrouterjsoncached.php` - Main connector class
- `connector/openrouterjsoncached_helpers.php` - Helper functions
- `log/cache.log` - General caching logs
- `log/_cached_perf.log` - Cache performance metrics
- `temp/system_cache_json_*.tmp` - Cached system prompts
- `temp/combined_dialogue_cache_json_*.tmp` - Cached dialogue history

## Credits

Based on:
- Original CHIM Anthropic cache connector
- OpenRouter JSON connector (CHIM 2.0)
- Enhanced with additional features and multi-provider support
