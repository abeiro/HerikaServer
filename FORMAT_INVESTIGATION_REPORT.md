# JSON vs Simple Format Investigation Report

## Executive Summary

After examining commit history from v1.0.8 through v1.0.24, **the format handling logic has been consistent across all versions**. The issue is NOT in the connector code itself, but likely in:
1. Configuration not being saved/loaded correctly from the database
2. Wrong connector being used by the active profile
3. Metadata JSON encoding/decoding issues

## Detailed Findings

### Format Loading Mechanism (Consistent Across All Versions)

**Initialization** (in constructor):
```php
$this->_responseFormat = 'json';  // Default value
```

**Configuration Loading** (in open() method):
```php
$this->_responseFormat = isset($GLOBALS["CONNECTOR"][$this->name]["response_format"])
    && in_array($GLOBALS["CONNECTOR"][$this->name]["response_format"], ['json', 'simple'])
    ? $GLOBALS["CONNECTOR"][$this->name]["response_format"]
    : 'json';
```

This logic has been **identical** since the earliest version (v1.0.8).

### Format Instruction Building (Consistent Across All Versions)

```php
if ($this->_responseFormat === 'json') {
    // Build JSON template with fields
    $formatInstruction = "Use ONLY this JSON object...";
} else {
    // Build simple format instruction
    $formatInstruction = buildSimpleFormatInstruction(...);
}
```

This branching logic has been **identical** across all versions.

### Where Metadata Comes From

The `$GLOBALS["CONNECTOR"][$this->name]` array is populated by:

**lib/core/llm_connector.class.php** (lines 237-242):
```php
$metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
if (is_array($metadata)) {
    foreach ($metadata as $key => $value) {
        $GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value;
    }
}
```

### UI Storage

**ui/core/llm_connectors.php** (lines 436-438 and 1521-1523):
```html
<select name="metadata[response_format]" id="response_format">
    <option value="json" <?= ($metadata['response_format'] ?? 'json') === 'json' ? 'selected' : '' ?>>JSON (structured)</option>
    <option value="simple" <?= ($metadata['response_format'] ?? '') === 'simple' ? 'selected' : '' ?>>Simple (natural language)</option>
</select>
```

The form field name is `metadata[response_format]`, which should serialize as JSON in the metadata column.

## Conceptual Analysis

### The Data Flow

1. **UI → Database**:
   - User selects format in dropdown
   - Form submits with `metadata[response_format] = "json"` or `"simple"`
   - PHP serializes this to JSON: `{"response_format": "json"}`
   - Stored in `connectors.metadata` column

2. **Database → Profile Load**:
   - Profile system loads connector data
   - `llm_connector.class.php` reads `metadata` column
   - JSON decodes to PHP array
   - Loops through array and sets each key in GLOBALS

3. **Connector Runtime**:
   - Connector reads `$GLOBALS["CONNECTOR"][$this->name]["response_format"]`
   - Sets internal `$this->_responseFormat`
   - Uses this to determine which instruction to build

### Potential Break Points

**Break Point 1: Form Submission**
- Form might not be submitting the field correctly
- JavaScript might be interfering with form data

**Break Point 2: Database Storage**
- Metadata might not be saved as JSON
- Field might be getting corrupted
- Wrong connector record being updated

**Break Point 3: Profile Loading**
- Wrong profile being loaded
- Wrong connector assigned to profile
- Metadata JSON decode failing silently

**Break Point 4: Metadata Unpacking**
- JSON decode returns null/false instead of array
- foreach loop skips or fails
- Key name mismatch (response_format vs responseFormat)

### Version Comparison Results

| Version | Format Loading | Instruction Building | Notes |
|---------|---------------|---------------------|-------|
| v1.0.8-v1.0.13 | Same logic | Same logic | Initial implementation |
| v1.0.14-v1.0.18 | Same logic | Same logic | UI improvements added |
| v1.0.19-v1.0.22 | Same logic | Same logic | Bug fixes, no format logic changes |
| v1.0.23-v1.0.24 | Same logic + debug logs | Same logic | Added logging |

**Conclusion**: The connector code has NEVER changed the way it handles format selection.

## Why Simple Format Might Be Selected When JSON is Shown

### Hypothesis 1: Database Has Wrong Value
- UI shows "json" (from current form state or default)
- Database actually contains "simple"
- When connector loads, it gets "simple" from database

### Hypothesis 2: Wrong Connector Being Used
- You're editing Connector A (set to json)
- Active profile uses Connector B (set to simple)
- Connector B is what actually runs

### Hypothesis 3: Metadata Not Being Saved
- Form submits correctly
- Database save fails or updates wrong record
- Old value (simple) remains in database

### Hypothesis 4: Profile System Issue
- Correct connector has correct setting
- Profile loader picks wrong connector
- Or loads connector but skips metadata

### Hypothesis 5: JSON Encoding Issue
- Metadata saves as malformed JSON
- JSON decode fails, returns null
- `if (is_array($metadata))` check fails
- GLOBALS never gets set
- Connector uses default: "json"
- BUT: This would make it use JSON, not simple!

**Therefore: Hypothesis 5 is ELIMINATED**

## The Debug Logs Will Reveal

The debug logs added in v1.0.24 will show:

```
[connector] CRITICAL DEBUG - Response Format Setting: simple
[connector] CRITICAL DEBUG - Raw metadata value: simple
[connector] CRITICAL DEBUG - Building format instruction for: simple
[connector] CRITICAL DEBUG - Simple format instruction created
```

**OR**

```
[connector] CRITICAL DEBUG - Response Format Setting: json
[connector] CRITICAL DEBUG - Raw metadata value: json
[connector] CRITICAL DEBUG - Building format instruction for: json
[connector] CRITICAL DEBUG - JSON format instruction created
```

This will tell us EXACTLY what value the connector is receiving at runtime.

## Recommended Investigation Steps

1. **Check the debug logs** to see what format is actually being loaded
2. **Verify which connector is active**:
   - Go to Profiles page
   - Check which profile is active
   - See what connector ID it uses
3. **Check that connector's database record**:
   - Look at the metadata column
   - Verify it contains `"response_format": "json"`
4. **Verify form submission**:
   - Use browser dev tools → Network tab
   - Submit the form
   - Check the POST data includes `metadata[response_format]=json`
5. **Check for multiple connectors**:
   - You might have two connectors with similar names
   - One set to JSON, one to Simple
   - Using the wrong one

## Conclusion

The connector code is working correctly. The bug is in:
- Configuration storage (database)
- Profile/connector selection
- Form submission/saving

The debug logs will pinpoint exactly where the issue is.
