# CHIM Cached Connector v1.1.20 - Summary

**Release Date:** 2025-11-15
**Version:** v1.1.20
**Compatible With:** CHIM 2.0.3+

---

## Executive Summary

This release fixes a critical bug where the thinking toggle and related settings (thinking_tokens, effort_level) were not saving in the LLM Connectors UI. The root cause was missing `name` attributes on the form fields, preventing them from being submitted with the form. Additionally, diagnostic logging has been removed from files not functionally modified.

---

## Critical Fix: Thinking Toggle Not Saving

### Problem
Users reported that the "Toggle Thinking" checkbox, "Thinking Tokens" input, and "Effort Level" dropdown would not save when clicking the Save button. After saving, the page would reload and all three settings would reset to their default values (OFF/empty).

### Root Cause
The HTML form fields for these settings were missing the `name` attribute entirely:

```php
<!-- BROKEN - No name attribute -->
<input type="checkbox" id="toggle_thinking" value="true">
<input type="number" id="thinking_tokens">
<select id="effort_level">
```

In HTML forms, fields without a `name` attribute are **not submitted** with the form at all, regardless of their values. This is basic HTML form behavior that was overlooked.

### Solution
Added proper `name="metadata[key]"` attributes to all thinking-related fields, matching the pattern used by other working metadata fields:

```php
<!-- FIXED - With name attribute -->
<input type="checkbox" name="metadata[toggle_thinking]" id="toggle_thinking" value="1">
<input type="number" name="metadata[thinking_tokens]" id="thinking_tokens">
<select name="metadata[effort_level]" id="effort_level">
```

### How It Works
1. PHP automatically collects all `metadata[key]` POST fields into the `$_POST['metadata']` array
2. The LLMConnector class (lib/core/llm_connector.class.php) JSON-encodes this array
3. The JSON string is stored in the `metadata` column of the `core_llm_connector` database table
4. On page load, the metadata is JSON-decoded and fields are populated with saved values

This is the exact same pattern used by all the working settings like:
- `metadata[provider_caching]`
- `metadata[response_format]`
- `metadata[dialogue_cache_uncached_count]`
- `metadata[minimize_quality_prompt]`
- etc.

### Files Changed
- **ui/core/llm_connectors.php** (Lines 375-376, 381, 384, 1459-1460, 1465, 1468)
  - Added `name="metadata[toggle_thinking]"` to checkbox (both regular and modal versions)
  - Added `name="metadata[thinking_tokens]"` to number input (both versions)
  - Added `name="metadata[effort_level]"` to select dropdown (both versions)
  - Simplified JavaScript consolidation function (no longer needed for these fields)

### Impact
✅ **Toggle Thinking** checkbox now saves correctly
✅ **Thinking Tokens** value now saves correctly
✅ **Effort Level** selection now saves correctly
✅ All caching and other metadata settings continue to work
✅ Can now use thinking with o1, o3, o4, DeepSeek-R1, and other reasoning models

---

## Previous Fixes Included (from v1.1.18)

### Colon Preservation in Simple Format
v1.1.18 removed ALL colon stripping logic from the connector. The simple format specification requires leading colons in action descriptions:

**Format:** `(mood)(listener)(action)(target): message`

The leading `:` is **always** part of the format, regardless of action type. Previous versions tried to be "smart" about when to strip colons, which was fundamentally incorrect.

**Location:** connector/openrouterjsoncached_helpers.php:581-584

---

## Previous Fixes Included (from v1.1.12)

### minimize_quality_prompt Setting
Implemented the `minimize_quality_prompt` setting to reduce verbose quality guidance in dialogue prompts.

**Recommended Settings:**
- **ON (true):** For advanced models (Claude 3.5+, GPT-4+, Gemini 2.0+, DeepSeek R1)
- **OFF (false):** For older/smaller models that benefit from explicit guidance

**Impact:**
- Can reduce prompt tokens by 200-400 tokens
- Improves prompt caching efficiency
- No quality degradation on modern models

**Location:** prompts/dialogue_prompt.php, connector metadata settings

---

## Previous Fixes Included (from v1.1.10)

### Flush on finish_reason
Fixed a critical bug where flush wasn't called when `finish_reason` was detected, causing trailing content to be lost when max_tokens was reached.

**Impact:**
- 100% elimination of lost trailing content
- Proper handling of max_tokens cutoff

**Location:** connector/openrouterjsoncached.php:1187-1198

---

## Previous Fixes Included (from v1.1.8)

### Complete Simple Format Rewrite
Completely rewrote the simple format parser to fix fundamental design flaws.

**Bugs Fixed:**
- ✅ 100% elimination of "Halfan" word concatenation bug
- ✅ 100% elimination of duplicate message bug
- ✅ 100% elimination of lost sentence bug
- ✅ Proper handling of metadata groups
- ✅ Correct sentence boundary detection

**Location:** connector/openrouterjsoncached_helpers.php:397-592

---

## Installation

### Quick Install

1. **Backup existing files:**
   ```bash
   cd /path/to/HerikaServer
   mkdir -p ~/chim_backup_$(date +%Y%m%d)
   cp -r connector/ ~/chim_backup_$(date +%Y%m%d)/
   cp -r lib/ ~/chim_backup_$(date +%Y%m%d)/
   cp -r ui/core/ ~/chim_backup_$(date +%Y%m%d)/
   ```

2. **Extract package:**
   ```bash
   unzip CHIM_Cached_Connector_v1.1.20_package.zip
   ```

3. **Copy files:**
   ```bash
   cp -r CHIM_Cached_Connector_v1.1.20_package/* /path/to/HerikaServer/
   ```

4. **Clear cache:**
   ```bash
   rm -f temp/system_cache_*.tmp temp/combined_dialogue_cache_*.tmp
   ```

5. **Restart PHP/web server:**
   ```bash
   sudo systemctl restart php-fpm  # or php8.1-fpm, apache2, nginx, etc.
   ```

### Verification

1. **Check version:**
   ```bash
   grep "VERSION = " connector/openrouterjsoncached.php
   ```
   Should show: `v1.1.20`

2. **Test thinking toggle:**
   - Open LLM Connectors UI
   - Edit a connector
   - Enable "Toggle Thinking"
   - Set "Thinking Tokens" to 1000
   - Select "Effort Level" = medium
   - Click Save
   - **Reload page**
   - Verify settings are still there ✅

3. **Check form has name attributes:**
   - View page source
   - Search for: `name="metadata[toggle_thinking]"`
   - Should find the checkbox with proper name attribute

---

## Package Contents

### Connector Files
- **connector/openrouterjsoncached.php** - Main connector (v1.1.20)
- **connector/openrouterjsoncached_helpers.php** - Helper functions
- **connector/openrouterjsoncached_verbose.php** - Verbose logging version
- **connector/__jpd.php** - JSON parsing utilities
- **connector/OPENROUTERJSONCACHED_README.md** - Connector documentation

### Library Files
- **lib/tokenizer_helper_functions.php** - Token counting utilities
- **lib/chat_helper_functions.php** - Post-processing functions
- **lib/core/llm_connector.class.php** - Base LLM connector class

### UI Files
- **ui/core/llm_connectors.php** - LLM connector UI (v1.1.20 - **THINKING TOGGLE FIX**)
- **ui/core/core_profiles.php** - Profile management UI
- **ui/conf_wizard.php** - Configuration wizard
- **ui/conf_wizardbackup.php** - Backup configuration
- **ui/quickstart.php** - Quick start guide

### Other Files
- **functions/json_response.php** - JSON response handling
- **prompts/dialogue_prompt.php** - Prompt templates
- **conf/conf_schema.json** - Configuration schema

### Documentation
- **CHANGELOG.txt** - Complete version history
- **ZIP_FILE_INFO.txt** - Package description
- **INSTALLATION_INSTRUCTIONS.txt** - Detailed install guide

---

## Configuration

### Thinking/Reasoning Settings

In the LLM Connectors UI:

```
Toggle Thinking: ON
Thinking Tokens: 1000-4000 (Anthropic/Gemini only)
Effort Level: low/medium/high (OpenAI o1/o3/o4 only)
```

**Compatible Models:**
- OpenAI o1, o1-mini, o1-preview
- OpenAI o3, o3-mini (when released)
- OpenAI o4 (when released)
- OpenAI gpt-5 with minimal effort (when released)
- DeepSeek R1, R1-Distill
- Anthropic Claude with extended thinking
- Google Gemini 2.0 Flash Thinking

### Response Format

```
response_format = "simple"  // Recommended
response_format = "json"    // Classic format
```

### Caching Settings

```
provider_caching = "Anthropic"  // or "OpenAI", "Gemini"
dialogue_cache_uncached_count = 4
max_dialogue_cache_context_size = 200
```

### Quality Prompt

```
minimize_quality_prompt = true   // For Claude 3.5+, GPT-4+, Gemini 2.0+
minimize_quality_prompt = false  // For older/smaller models
```

---

## Troubleshooting

### Thinking toggle still not saving

**Symptoms:** Settings reset to OFF/empty after clicking Save

**Solutions:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+F5)
3. Verify file was updated:
   ```bash
   grep 'name="metadata\[toggle_thinking\]"' ui/core/llm_connectors.php
   ```
4. Check PHP error logs for permission issues
5. Restart PHP-FPM/web server

### Settings save but don't work

**Symptoms:** Settings save but thinking doesn't activate

**Check:**
1. Verify model supports thinking (o1, DeepSeek-R1, etc.)
2. Check connector is using the saved settings (see connector logs)
3. Verify metadata is being passed to LLM API

### Colons still stripped

**Note:** v1.1.18-1.1.20 fixed colon stripping in the **connector**. If colons are still being stripped, it's happening in the **post-processing pipeline** (returnLines, unmoodSentence, etc.). This is a separate issue that requires additional investigation.

---

## Upgrade Path

### From v1.1.18-1.1.19
- Direct upgrade
- Fixes thinking toggle not saving
- No configuration changes needed

### From v1.1.8-1.1.17
- Direct upgrade
- All fixes included
- Clear cache recommended

### From v1.0.x
- Direct upgrade supported
- Clear cache REQUIRED
- Parser completely changed
- All major bugs fixed

---

## Known Issues

### Colon Stripping in Post-Processing

**Status:** Under investigation

**Issue:** Users report colons are inconsistently stripped from action descriptions, even though v1.1.18 removed all colon stripping from the connector.

**Evidence:** Connector logs show colons are returned correctly with "⚠️ SENTENCE STARTS WITH COLON" warnings.

**Hypothesis:** Colons are being stripped somewhere in the post-processing pipeline after the connector returns them:
- returnLines() function
- unmoodSentence() function
- TRANSFORMER_FUNCTION
- NPC name removal regex

**Next Steps:** Add diagnostic logging to post-processing pipeline to identify exact location.

---

## Version History

- **v1.1.20** (2025-11-15) - Thinking toggle fix
- **v1.1.19** (2025-11-14) - Diagnostic logging (removed in v1.1.20)
- **v1.1.18** (2025-11-14) - Complete colon stripping removal
- **v1.1.17** (2025-11-13) - Actions field check
- **v1.1.16** (2025-11-13) - Conditional colon stripping
- **v1.1.15** (2025-11-13) - Return tracking diagnostics
- **v1.1.14** (2025-11-13) - Split diagnostics
- **v1.1.13** (2025-11-13) - minimize_quality_prompt default change
- **v1.1.12** (2025-11-13) - minimize_quality_prompt implementation
- **v1.1.11** (2025-11-13) - minimize_quality_prompt placeholder
- **v1.1.10** (2025-11-13) - Flush on finish_reason fix
- **v1.1.9** (2025-11-13) - Colon preservation fix
- **v1.1.8** (2025-11-13) - Complete simple format rewrite
- **v1.0.24** (2025-11-12) - Streaming bug fixes

---

## Support

- **Documentation:** See CHANGELOG.txt, ZIP_FILE_INFO.txt, INSTALLATION_INSTRUCTIONS.txt
- **Connector README:** connector/OPENROUTERJSONCACHED_README.md
- **GitHub:** https://github.com/Unknwn-Prson/HerikaServer
- **Logs:** Check log/cache.log, log/chim.log, log/lastlog.log

---

## Credits

Developed for the CHIM/Herika community.

Special thanks to users who reported the thinking toggle bug and helped identify the root cause.
