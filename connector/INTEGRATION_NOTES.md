# OpenRouterJSONcached Integration Notes

## Current Architecture Understanding

### Configuration System

**1. Config Schema** (`/conf/conf_schema.json`)
- Defines all connector settings that appear in the WebUI
- Each connector has a section under `CONNECTOR` key
- Settings include: url, model, API parameters, custom options
- Currently has: `openrouterjson`, `openrouter` (SUMMARY), `openaijson`, `openai`, etc.
- **TODO**: Add `openrouterjsoncached` section with caching-specific settings

**2. Database Schema** (`core_llm_connector` table)
- Stores connector instances created by users
- Fixed columns: id, label, url, model, provider, driver, api_badge_id, max_tokens, temperature, etc.
- `metadata` column (JSONB): stores additional settings not in fixed columns
- Our caching settings will go in `metadata`:
  - `provider_caching` (Anthropic/OpenAI/Gemini)
  - `response_format` (json/simple)
  - `include_actions_list`
  - `include_mood_requirement`
  - `include_target_requirement`
  - `include_listener_requirement`
  - `dialogue_cache_uncached_count`
  - `max_dialogue_cache_context_size`

**3. Connector Setup** (`lib/core/llm_connector.class.php:203-233`)
- Already has case for `openrouterjsoncached` driver
- Reads from database and loads into `$GLOBALS["CONNECTOR"]["openrouterjsoncached"]`
- Decodes `metadata` field to add custom settings

### Template System

**1. JSON Response Template** (`functions/json_response.php`)
- Defines `$GLOBALS["responseTemplate"]` - the JSON structure LLMs must follow
- Standard fields: character, listener, message, mood, action, target, lang
- Can be customized via:
  - `JSON_DIALOGUE_FORMAT_REORDER` setting (changes field order)
  - Hook system: `$GLOBALS["HOOKS"]["JSON_TEMPLATE"]`
  - Extension files: `ext/*/json_response_custom.php`

**2. Actions/Commands** (`$GLOBALS["COMMAND_PROMPT"]`)
- Lists all available actions (Talk, Attack, Cast, etc.)
- Built in `setActions()` function
- Actions list: `$GLOBALS["FUNC_LIST"]`

**Current Integration Status**:
- The cached connector reads `$GLOBALS["responseTemplate"]` directly (line 320)
- It modifies the template based on config flags (removes mood/action/target/listener if disabled)
- Works but is "janky" - doesn't integrate cleanly

### What's Currently Working

✅ Connector file exists (`connector/openrouterjsoncached.php`)
✅ Helper functions exist (`connector/openrouterjsoncached_helpers.php`)
✅ Integration code exists (`lib/core/llm_connector.class.php`)
✅ All bug fixes applied (streaming, error handling, performance, etc.)
✅ Reads templates from CHIM's system
✅ Supports three providers (Anthropic, OpenAI, Gemini)
✅ Supports two response formats (JSON, simple)

### What's Missing for Full Integration

🔴 **CRITICAL - For Functionality**:
1. ❌ No entry in `conf/conf_schema.json` - users can't configure it in WebUI
2. ❌ No driver option in connector dropdowns

🟡 **NICE TO HAVE - For Polish**:
3. ❌ Template system doesn't know about caching
4. ❌ No hooks for cache-specific template modifications
5. ❌ Simple format instruction not in central template system
6. ❌ No UI indicators for which format/provider is optimal

---

## Next Steps

### Phase 1: Make It Configurable (IMMEDIATE)

**Goal**: Users can create and configure cached connectors in WebUI

**Tasks**:
1. Add `openrouterjsoncached` entry to `conf/conf_schema.json`
2. Test creating a connector in WebUI
3. Test all settings load correctly

**Settings to Add**:
```json
"openrouterjsoncached": {
  "_title":"OpenRouter API (JSON) with Caching",
  "url": {...},
  "model": {...},
  "provider_caching": {
    "type":"select",
    "values":["Anthropic","OpenAI","Gemini"],
    "description":"Which provider's caching system to use."
  },
  "response_format": {
    "type":"select",
    "values":["json","simple"],
    "description":"JSON for structured output, simple for natural language with parenthetical metadata."
  },
  "include_actions_list": {
    "type":"boolean",
    "description":"Include action selection in response. Required if you want NPCs to perform actions."
  },
  "include_mood_requirement": {
    "type":"boolean",
    "description":"Include mood/emotion in response. Used for animations."
  },
  "include_target_requirement": {
    "type":"boolean",
    "description":"Include action target in response. Automatically enabled if actions are enabled."
  },
  "include_listener_requirement": {
    "type":"boolean",
    "description":"Include listener (who NPC is talking to) in response."
  },
  "dialogue_cache_uncached_count": {
    "type":"integer",
    "description":"Number of most recent dialogue entries to keep uncached (for dynamic content). Default: 4"
  },
  "max_dialogue_cache_context_size": {
    "type":"integer",
    "description":"Maximum number of dialogue entries to cache. Older entries are purged. Default: 93"
  },
  // ... all standard OpenRouter settings (temperature, penalties, etc.) ...
}
```

### Phase 2: Template Integration (FUTURE - After Everything Works)

**Goal**: Clean integration with CHIM's template system

**Approach Options**:

**Option A: Hook-Based** (Least Invasive)
- Create hook in `json_response.php` that fires when cached connector is active
- Hook modifies `$responseTemplate` based on config flags
- Simple format instructions registered via hook
- Pros: No changes to core files, easy to maintain
- Cons: Still somewhat separate

**Option B: Template Variants** (Medium Integration)
- Add template "modes" to `json_response.php`
- Example: `setResponseTemplate($mode = 'full')`
- Modes: 'full', 'minimal', 'simple_format', 'custom'
- Cached connector requests appropriate mode
- Pros: Cleaner, more reusable
- Cons: Changes core file

**Option C: Full Refactor** (Most Integrated)
- Move all format logic to template system
- Connectors just declare capabilities
- Config system determines what fields to include
- Pros: Maximum flexibility, cleanest
- Cons: Major refactor, affects all connectors

**Recommendation**: Start with **Option A** (hooks), evaluate if **Option B** is worth it later.

---

## Integration Checklist

### Functionality (Do First)
- [ ] Add to `conf/conf_schema.json`
- [ ] Add to driver dropdown in WebUI
- [ ] Test creating connector via WebUI
- [ ] Test all settings persist correctly
- [ ] Test settings load on server restart
- [ ] Document configuration in user-facing docs

### Polish (Do Later)
- [ ] Add caching hooks to template system
- [ ] Move simple format instructions to centralized location
- [ ] Add UI tooltips explaining when to use each format
- [ ] Add UI tooltips explaining provider differences
- [ ] Add performance indicators (cache hit rate logging)
- [ ] Consider template variant system

---

## Notes for Implementation

**Metadata Fields**:
All custom settings go in the `metadata` JSONB column. The connector setup code already handles this (line 225-231 in `llm_connector.class.php`):
```php
$metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
if (is_array($metadata)) {
    foreach ($metadata as $key => $value) {
        $GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value;
    }
}
```

**Backwards Compatibility**:
When adding new settings to schema, provide sensible defaults so existing configs don't break.

**Testing Priority**:
1. Create connector via WebUI - does it save?
2. Restart server - does it load?
3. Change settings - do they persist?
4. Test all three providers (Anthropic, OpenAI, Gemini)
5. Test both formats (JSON, simple)
6. Test with actions enabled/disabled
7. Test with mood enabled/disabled

---

## Future Considerations

### Fast Request Method
The connector currently lacks `fast_request()` method used for:
- Memory creation (summaries)
- Diary entries
- Background tasks
- Dynamic profile updates

**Decision**: Document as limitation for now. These operations use SUMMARY connectors anyway, which don't need caching (short-lived, infrequent calls).

### Multi-Connector Support
Could we support caching for other connector types?
- `openaijsoncached` (direct OpenAI API with caching)
- `google_openaijsoncached` (Gemini with caching)

**Decision**: Wait until openrouterjsoncached is proven stable, then consider.

### Cache Management UI
Nice-to-have: WebUI page to:
- View cache stats (hit rate, size, age)
- Clear caches manually
- View cached content for debugging

**Decision**: Post-v1.0 feature.

---

**Last Updated**: 2025-11-04
**Status**: Bug fixes complete, awaiting conf_schema integration
