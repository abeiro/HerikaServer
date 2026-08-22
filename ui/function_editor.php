<?php
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath));
if ($webRoot == '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");

$TITLE = "Action Editor";
$isEmbed = isset($_GET['embed']) && strval($_GET['embed']) === '1';

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "action_catalog.php");

function h($value)
{
    return htmlspecialchars(strval($value), ENT_QUOTES, "UTF-8");
}

function functionEditorTrim($value)
{
    return trim(strval($value));
}

function functionEditorToBool($value)
{
    if (is_bool($value)) {
        return $value;
    }

    $text = strtolower(trim(strval($value)));
    return in_array($text, ["1", "true", "yes", "on", "t"], true);
}

function functionEditorPrettyJson($value)
{
    if (is_array($value)) {
        $decoded = $value;
    } else {
        $decoded = json_decode(strval($value), true);
    }

    if (!is_array($decoded)) {
        $text = trim(strval($value));
        return $text === "" ? "{}" : $text;
    }

    $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : "{}";
}

function functionEditorGetParameterSchemaVariableTokens()
{
    return [
        'player_name' => '#PLAYER_NAME#',
        'herika_name' => '#HERIKA_NAME#',
    ];
}

function functionEditorGetParameterSchemaVariableValues()
{
    return [
        'player_name' => trim(strval($GLOBALS['PLAYER_NAME'] ?? '')),
        'herika_name' => trim(strval($GLOBALS['HERIKA_NAME'] ?? '')),
    ];
}

function functionEditorReplaceParameterSchemaVariablesInString($text)
{
    $text = strval($text);
    if ($text === '') {
        return '';
    }

    $values = functionEditorGetParameterSchemaVariableValues();
    $tokens = functionEditorGetParameterSchemaVariableTokens();
    foreach ($tokens as $key => $token) {
        $value = strval($values[$key] ?? '');
        if ($value === '' || $value === $token) {
            continue;
        }

        $text = str_replace($value, $token, $text);
    }

    return $text;
}

function functionEditorRestoreParameterSchemaVariablesInString($text)
{
    $text = strval($text);
    if ($text === '') {
        return '';
    }

    $values = functionEditorGetParameterSchemaVariableValues();
    $tokens = functionEditorGetParameterSchemaVariableTokens();
    foreach ($tokens as $key => $token) {
        $value = strval($values[$key] ?? '');
        if ($value !== '') {
            $text = str_replace($token, $value, $text);
        }

        if ($key === 'player_name') {
            $text = str_replace(['#PLAYER_NAME#', '$PLAYER_NAME', '{$PLAYER_NAME}', '{$GLOBALS["PLAYER_NAME"]}'], $value, $text);
            continue;
        }

        if ($key === 'herika_name') {
            $text = str_replace(['#HERIKA_NAME#', '$HERIKA_NAME', '{$HERIKA_NAME}', '{$GLOBALS["HERIKA_NAME"]}'], $value, $text);
        }
    }

    return $text;
}

function functionEditorReplaceActionTextVariablesInString($text)
{
    $text = functionEditorReplaceParameterSchemaVariablesInString($text);
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/\b[Tt]he\s+PLAYER\b/u', '#PLAYER_NAME#', $text);
    $text = preg_replace('/\bPLAYER\b/u', '#PLAYER_NAME#', $text);
    $text = preg_replace('/\b[Tt]he\s+NPC\b/u', '#HERIKA_NAME#', $text);
    $text = preg_replace('/\bNPC\b/u', '#HERIKA_NAME#', $text);

    return strval($text);
}

function functionEditorNormalizeSubmittedActionTextForStorage($text)
{
    $text = functionEditorReplaceActionTextVariablesInString($text);
    return strval($text);
}

function functionEditorTransformParameterSchemaStrings($value, $mode = 'display')
{
    if (is_array($value)) {
        $transformed = [];
        foreach ($value as $key => $childValue) {
            $transformed[$key] = functionEditorTransformParameterSchemaStrings($childValue, $mode);
        }

        return $transformed;
    }

    if (!is_string($value)) {
        return $value;
    }

    if ($mode === 'storage') {
        return functionEditorRestoreParameterSchemaVariablesInString($value);
    }

    return functionEditorReplaceParameterSchemaVariablesInString($value);
}

function functionEditorNormalizeParameterSchema($value)
{
    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode(strval($value), true);
    return is_array($decoded) ? $decoded : [];
}

function functionEditorRenderParameterSchema($value)
{
    $schema = functionEditorTransformParameterSchemaStrings(functionEditorNormalizeParameterSchema($value), 'display');
    if (count($schema) === 0) {
        return '<span class="pricing-empty">No parameters</span>';
    }

    $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
    $required = [];
    foreach (($schema['required'] ?? []) as $requiredField) {
        $requiredField = trim(strval($requiredField));
        if ($requiredField !== '') {
            $required[$requiredField] = true;
        }
    }

    $parts = [];
    $rootType = trim(strval($schema['type'] ?? 'object'));
    if ($rootType !== '') {
        $parts[] = '<div class="helper-text" style="margin-bottom:8px;">Root type: <code>' . h($rootType) . '</code></div>';
    }

    if (count($properties) === 0) {
        $parts[] = '<div class="helper-text">No defined properties.</div>';
        $parts[] = '<details style="margin-top:10px;"><summary class="return-preview" style="cursor:pointer;">Raw schema</summary><pre class="json-preview">'
            . h(functionEditorPrettyJson($schema))
            . '</pre></details>';
        return implode('', $parts);
    }

    $parts[] = '<div class="param-list">';
    foreach ($properties as $propertyName => $propertySchema) {
        $propertySchema = is_array($propertySchema) ? $propertySchema : [];
        $propertyType = trim(strval($propertySchema['type'] ?? 'string'));
        $propertyDescription = trim(strval($propertySchema['description'] ?? ''));
        $isRequired = isset($required[$propertyName]);

        $parts[] = '<div class="config-field" style="margin-bottom:14px;">';
        $parts[] = '<div><code>' . h($propertyName) . '</code> <span class="status-pill scope">' . h($propertyType) . '</span>'
            . ($isRequired ? ' <span class="status-pill enabled">Required</span>' : ' <span class="status-pill disabled">Optional</span>')
            . '</div>';

        if ($propertyDescription !== '') {
            $parts[] = '<div class="helper-text" style="margin-top:6px;">' . nl2br(h($propertyDescription)) . '</div>';
        }

        $parts[] = '</div>';
    }
    $parts[] = '</div>';
    $parts[] = '<details style="margin-top:10px;"><summary class="return-preview" style="cursor:pointer;">Raw schema</summary><pre class="json-preview">'
        . h(functionEditorPrettyJson($schema))
        . '</pre></details>';

    return implode('', $parts);
}

function functionEditorNormalizeSubmittedParameterSchema($rawValue, &$errorMessage)
{
    $text = trim(strval($rawValue));
    if ($text === '') {
        $errorMessage = 'Parameters JSON cannot be blank.';
        return null;
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        $errorMessage = 'Parameters JSON must be a valid JSON object.';
        return null;
    }

    $decoded = functionEditorTransformParameterSchemaStrings($decoded, 'storage');

    if (!function_exists('herikaActionCatalogNormalizeParameterSchema')) {
        return $decoded;
    }

    return herikaActionCatalogNormalizeParameterSchema($decoded);
}

function functionEditorBuildUrl($params = [], $embed = false, $anchor = "")
{
    $base = basename($_SERVER["PHP_SELF"] ?? "function_editor.php");
    if ($embed) {
        $params["embed"] = "1";
    }
    $qs = http_build_query($params);
    $url = $base . ($qs !== "" ? ("?" . $qs) : "");
    if ($anchor !== "") {
        $url .= "#" . ltrim($anchor, "#");
    }
    return $url;
}

function functionEditorGetEditableConfigFieldsForRow($row)
{
    if (!function_exists('herikaActionCatalogGetEditorFields')) {
        return [];
    }

    return herikaActionCatalogGetEditorFields($row);
}

function functionEditorRowHasConfig($row)
{
    return count(functionEditorGetEditableConfigFieldsForRow($row)) > 0;
}

function functionEditorFormatConfigValue($field, $value)
{
    $field = function_exists('herikaActionCatalogNormalizeEditorField')
        ? herikaActionCatalogNormalizeEditorField($field)
        : $field;

    if (!is_array($field)) {
        return strval($value);
    }

    if (($field['format'] ?? '') === 'gold') {
        return functionEditorFormatGold($value);
    }

    if (($field['format'] ?? '') === 'name_list') {
        $parts = preg_split('/[\r\n,]+/', strval($value)) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $name = trim(strval($part));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return count($names) > 0 ? implode(', ', $names) : 'None';
    }

    if (($field['type'] ?? '') === 'boolean') {
        return functionEditorToBool($value) ? 'Enabled' : 'Disabled';
    }

    if (($field['type'] ?? '') === 'select') {
        foreach (($field['options'] ?? []) as $option) {
            if (strval($option['value'] ?? '') === strval($value)) {
                return strval($option['label'] ?? $value);
            }
        }
    }

    if (($field['type'] ?? '') === 'number') {
        return strval(floatval($value));
    }

    return strval($value);
}

function functionEditorShouldOnlyShowDefaultFallback($fieldKey)
{
    $fieldKey = trim(strval($fieldKey));
    return in_array($fieldKey, [
        'followup_enabled',
        'followup_arg_name',
        'followup_prompt',
        'followup_use_functions_again',
    ], true);
}

/**
 * Boolean settings that were promoted out of the Advanced Options modal and are
 * now edited directly in the action table's Behavior column. Ordered for display.
 */
function functionEditorGetPromotedBehaviorFieldKeys()
{
    return [
        'confirmation_required',
        'followup_enabled',
        'followup_use_functions_again',
    ];
}

function functionEditorIsPromotedBehaviorFieldKey($fieldKey)
{
    return in_array(trim(strval($fieldKey)), functionEditorGetPromotedBehaviorFieldKeys(), true);
}

function functionEditorFilterPromotedBehaviorFields($fields, $keepPromoted)
{
    $filtered = [];
    foreach ((array) $fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        if (functionEditorIsPromotedBehaviorFieldKey($field['key'] ?? '') === (bool) $keepPromoted) {
            $filtered[] = $field;
        }
    }

    return $filtered;
}

/**
 * Promoted behavior fields keyed by field key and ordered for display. Keys missing
 * from the result are unsupported for that action (for example, confirmation on
 * read-only or server-side actions).
 */
function functionEditorIndexPromotedBehaviorFields($fields)
{
    $available = [];
    foreach (functionEditorFilterPromotedBehaviorFields($fields, true) as $field) {
        $available[strval($field['key'])] = $field;
    }

    $ordered = [];
    foreach (functionEditorGetPromotedBehaviorFieldKeys() as $fieldKey) {
        if (isset($available[$fieldKey])) {
            $ordered[$fieldKey] = $available[$fieldKey];
        }
    }

    return $ordered;
}

function functionEditorGetPromotedBehaviorFieldsForRow($row)
{
    return functionEditorIndexPromotedBehaviorFields(functionEditorGetEditableConfigFieldsForRow($row));
}

function functionEditorNormalizeSubmittedConfigValue($field, $submittedConfig, &$errorMessage)
{
    $field = function_exists('herikaActionCatalogNormalizeEditorField')
        ? herikaActionCatalogNormalizeEditorField($field)
        : $field;

    if (!is_array($field)) {
        $errorMessage = 'Invalid field definition.';
        return null;
    }

    $fieldKey = strval($field['key'] ?? '');
    $fieldLabel = strval($field['label'] ?? $fieldKey);
    $fieldType = strval($field['type'] ?? 'text');
    $rawValue = is_array($submittedConfig) && array_key_exists($fieldKey, $submittedConfig)
        ? $submittedConfig[$fieldKey]
        : null;

    if ($fieldType === 'boolean') {
        return functionEditorToBool($rawValue);
    }

    if ($fieldType === 'integer') {
        $textValue = trim(strval($rawValue));
        if ($textValue === '' || !is_numeric($textValue)) {
            $errorMessage = "{$fieldLabel} must be a whole number.";
            return null;
        }

        $value = intval(round(floatval($textValue)));
        if (is_numeric($field['minimum']) && $value < intval($field['minimum'])) {
            $errorMessage = "{$fieldLabel} must be at least " . intval($field['minimum']) . ".";
            return null;
        }
        if (is_numeric($field['maximum']) && $value > intval($field['maximum'])) {
            $errorMessage = "{$fieldLabel} must be at most " . intval($field['maximum']) . ".";
            return null;
        }
        return $value;
    }

    if ($fieldType === 'number') {
        $textValue = trim(strval($rawValue));
        if ($textValue === '' || !is_numeric($textValue)) {
            $errorMessage = "{$fieldLabel} must be numeric.";
            return null;
        }

        $value = floatval($textValue);
        if (is_numeric($field['minimum']) && $value < floatval($field['minimum'])) {
            $errorMessage = "{$fieldLabel} must be at least " . floatval($field['minimum']) . ".";
            return null;
        }
        if (is_numeric($field['maximum']) && $value > floatval($field['maximum'])) {
            $errorMessage = "{$fieldLabel} must be at most " . floatval($field['maximum']) . ".";
            return null;
        }
        return $value;
    }

    if ($fieldType === 'select') {
        $value = trim(strval($rawValue));
        foreach (($field['options'] ?? []) as $option) {
            if ($value === strval($option['value'] ?? '')) {
                return $value;
            }
        }

        $errorMessage = "{$fieldLabel} has an invalid option.";
        return null;
    }

    return strval($rawValue ?? '');
}

function functionEditorGetCurrentFilterParams()
{
    $params = [];
    foreach (["search", "state", "scope", "game_function", "custom"] as $key) {
        if (!isset($_GET[$key])) {
            continue;
        }

        $params[$key] = trim(strval($_GET[$key]));
    }

    return $params;
}

function functionEditorRedirectWithNotice($message, $messageType, $embed = false, $anchor = "entries")
{
    $params = functionEditorGetCurrentFilterParams();
    $params["notice"] = strval($message);
    $params["notice_type"] = strval($messageType);
    header("Location: " . functionEditorBuildUrl($params, $embed, $anchor));
    exit;
}

function functionEditorFormatGold($value)
{
    $value = intval($value);
    return ($value === 1) ? "1 gold" : ("{$value} gold");
}

function functionEditorCreateActiveScopeGroups()
{
    return [
        "npc" => [
            "label" => "NPC",
            "actions" => [],
        ],
        "followers" => [
            "label" => "Followers",
            "actions" => [],
        ],
        "narrator" => [
            "label" => "Narrator",
            "actions" => [],
        ],
        "dynamic" => [
            "label" => "Dynamic",
            "actions" => [],
        ],
    ];
}

function functionEditorBuildActiveScopeGroups($rows)
{
    $groups = functionEditorCreateActiveScopeGroups();

    foreach ($rows as $row) {
        $entry = [
            "code_name" => strval($row["code_name"] ?? ""),
            "action_name" => strval($row["action_name"] ?? ""),
            "description" => trim(functionEditorReplaceActionTextVariablesInString(strval($row["description"] ?? ""))),
        ];

        $targets = [];
        if (functionEditorToBool($row["available_to_npc"] ?? false)) {
            $targets[] = "npc";
        }
        if (functionEditorToBool($row["available_to_followers"] ?? false)) {
            $targets[] = "followers";
        }
        if (functionEditorToBool($row["available_to_narrator"] ?? false)) {
            $targets[] = "narrator";
        }
        if (count($targets) === 0) {
            $targets[] = "dynamic";
        }

        foreach ($targets as $target) {
            if (isset($groups[$target])) {
                $groups[$target]["actions"][] = $entry;
            }
        }
    }

    return $groups;
}

function functionEditorNormalizeSubmittedActionTextValue($value, $fieldLabel, $allowBlank = true)
{
    $value = strval($value);
    if (!$allowBlank && trim($value) === '') {
        return [
            'value' => null,
            'error' => "{$fieldLabel} cannot be blank.",
        ];
    }

    return [
        'value' => $value,
        'error' => '',
    ];
}

$message = "";
$messageType = "ok";
$advancedOpenCode = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "toggle_action") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");
        $targetEnabled = functionEditorToBool($_POST["target_enabled"] ?? "0");

        if ($codeName === "") {
            $message = "Missing action code name.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (herikaActionCatalogUpsertCustomToggle($codeName, $targetEnabled)) {
            $message = sprintf("%s is now %s.", $codeName, $targetEnabled ? "enabled" : "disabled");
        } else {
            $message = "Could not update action toggle.";
            $messageType = "err";
        }
    } elseif ($_POST["action"] === "update_action_basic_fields") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");
        $row = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;

        if (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (!is_array($row)) {
            $message = "Unknown action code name.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogUpsertCustomTextFields")) {
            $message = "Action catalog text override support is not available in this build.";
            $messageType = "err";
        } else {
            $submittedName = functionEditorNormalizeSubmittedActionTextValue($_POST["action_name"] ?? "", "Action name", false);
            $submittedDescription = functionEditorNormalizeSubmittedActionTextValue($_POST["description"] ?? "", "Description", true);
            $errorMessage = trim(implode(' ', array_filter([
                $submittedName['error'] ?? '',
                $submittedDescription['error'] ?? '',
            ])));

            if ($errorMessage !== '') {
                $message = $errorMessage;
                $messageType = "err";
            } elseif (function_exists("herikaFindActionCatalogActionNameConflict")) {
                $conflictingRow = herikaFindActionCatalogActionNameConflict($submittedName['value'], $codeName);
                if (is_array($conflictingRow) && !empty($conflictingRow['code_name'])) {
                    $conflictingActionName = trim(strval($conflictingRow['action_name'] ?? ''));
                    $conflictingActionName = $conflictingActionName !== '' ? $conflictingActionName : trim(strval($conflictingRow['code_name'] ?? ''));
                    $message = "Action name conflicts with " . $conflictingActionName . " (" . trim(strval($conflictingRow['code_name'] ?? '')) . "). Keep action names unique.";
                    $messageType = "err";
                } elseif (herikaActionCatalogUpsertCustomTextFields($codeName, [
                    'action_name' => $submittedName['value'],
                    'description' => functionEditorNormalizeSubmittedActionTextForStorage($submittedDescription['value']),
                ])) {
                    functionEditorRedirectWithNotice($codeName . " updated.", "ok", $isEmbed);
                } else {
                    $message = "Could not update action name and description.";
                    $messageType = "err";
                }
            } elseif (herikaActionCatalogUpsertCustomTextFields($codeName, [
                'action_name' => $submittedName['value'],
                'description' => functionEditorNormalizeSubmittedActionTextForStorage($submittedDescription['value']),
            ])) {
                functionEditorRedirectWithNotice($codeName . " updated.", "ok", $isEmbed);
            } else {
                $message = "Could not update action name and description.";
                $messageType = "err";
            }
        }
    } elseif ($_POST["action"] === "update_action_advanced") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");
        $advancedOpenCode = $codeName;
        $row = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;
        // Promoted behavior toggles live in the table's Behavior column, so the advanced
        // form neither renders nor writes them and cannot clobber them with defaults.
        $configFields = functionEditorFilterPromotedBehaviorFields(
            functionEditorGetEditableConfigFieldsForRow($row),
            false
        );
        $submittedConfig = $_POST["config"] ?? [];

        if (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (!is_array($row)) {
            $message = "Unknown action code name.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogUpsertCustomTextFields")
            || !function_exists("herikaActionCatalogUpsertCustomParameters")
            || (count($configFields) > 0 && !function_exists("herikaActionCatalogUpsertCustomConfig"))) {
            $message = "Advanced action override support is not available in this build.";
            $messageType = "err";
        } else {
            $submittedReturnMessage = functionEditorNormalizeSubmittedActionTextValue($_POST["return_message"] ?? "", "Return message", true);
            $errorMessage = strval($submittedReturnMessage['error'] ?? '');
            $normalizedParameters = functionEditorNormalizeSubmittedParameterSchema($_POST["parameters_json"] ?? "", $errorMessage);
            $configValues = [];

            if ($errorMessage === "") {
                foreach ($configFields as $field) {
                    $normalizedValue = functionEditorNormalizeSubmittedConfigValue($field, $submittedConfig, $errorMessage);
                    if ($errorMessage !== "") {
                        break;
                    }
                    $configValues[strval($field['key'])] = $normalizedValue;
                }
            }

            if ($errorMessage !== "") {
                $message = $errorMessage;
                $messageType = "err";
            } else {
                $transactionStarted = $GLOBALS["db"]->execQuery("BEGIN") !== false;
                $saved = false;
                if ($transactionStarted) {
                    $saved = herikaActionCatalogUpsertCustomTextFields($codeName, [
                        'return_message' => functionEditorNormalizeSubmittedActionTextForStorage($submittedReturnMessage['value']),
                    ]);
                    if ($saved && count($configFields) > 0) {
                        $saved = herikaActionCatalogUpsertCustomConfig($codeName, $configValues);
                    }
                    if ($saved) {
                        $saved = herikaActionCatalogUpsertCustomParameters($codeName, $normalizedParameters);
                    }
                }

                if ($saved && $GLOBALS["db"]->execQuery("COMMIT") !== false) {
                    functionEditorRedirectWithNotice($codeName . " advanced options updated.", "ok", $isEmbed);
                } else {
                    if ($transactionStarted) {
                        $GLOBALS["db"]->execQuery("ROLLBACK");
                    }
                    $message = "Could not update advanced options.";
                    $messageType = "err";
                }
            }
        }
    } elseif ($_POST["action"] === "save_behavior_bulk") {
        // One JSON payload keeps the request under max_input_vars no matter how many rows changed.
        $decodedPayload = json_decode(strval($_POST["behavior_payload"] ?? ""), true);
        $submittedRows = is_array($decodedPayload) && is_array($decodedPayload["rows"] ?? null)
            ? $decodedPayload["rows"]
            : null;

        if (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogUpsertCustomConfig")) {
            $message = "Action catalog custom config support is not available in this build.";
            $messageType = "err";
        } elseif ($submittedRows === null) {
            $message = "Could not read the submitted behavior changes.";
            $messageType = "err";
        } elseif (count($submittedRows) === 0) {
            $message = "No behavior changes to save.";
            $messageType = "err";
        } elseif (count($submittedRows) > 2000) {
            $message = "Too many behavior changes were submitted at once.";
            $messageType = "err";
        } else {
            $errorMessage = "";
            $pendingUpdates = [];

            foreach ($submittedRows as $submittedCode => $submittedValues) {
                $candidateCode = functionEditorTrim($submittedCode);
                if ($candidateCode === "" || !is_array($submittedValues) || count($submittedValues) === 0) {
                    $errorMessage = "Received an invalid behavior change entry.";
                    break;
                }

                $candidateRow = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($candidateCode) : null;
                if (!is_array($candidateRow)) {
                    $errorMessage = "Unknown action code name: " . $candidateCode . ".";
                    break;
                }

                $promotedFields = functionEditorGetPromotedBehaviorFieldsForRow($candidateRow);
                $rowValues = [];
                foreach ($submittedValues as $submittedKey => $submittedValue) {
                    $candidateKey = functionEditorTrim($submittedKey);
                    if (!isset($promotedFields[$candidateKey])) {
                        $errorMessage = $candidateCode . " does not support the requested behavior setting.";
                        break 2;
                    }

                    $normalizedValue = functionEditorNormalizeSubmittedConfigValue(
                        $promotedFields[$candidateKey],
                        [$candidateKey => $submittedValue],
                        $errorMessage
                    );
                    if ($errorMessage !== "") {
                        break 2;
                    }

                    $rowValues[$candidateKey] = $normalizedValue;
                }

                // Use the catalog's canonical casing so the upsert targets the right row.
                $pendingUpdates[strval($candidateRow['code_name'] ?? $candidateCode)] = $rowValues;
            }

            if ($errorMessage !== "") {
                $message = $errorMessage;
                $messageType = "err";
            } elseif (count($pendingUpdates) === 0) {
                $message = "No behavior changes to save.";
                $messageType = "err";
            } else {
                $transactionStarted = $GLOBALS["db"]->execQuery("BEGIN") !== false;
                $saved = $transactionStarted;
                if ($transactionStarted) {
                    foreach ($pendingUpdates as $updateCode => $updateValues) {
                        // Upserts merge into metadata.custom_config, preserving unrelated keys.
                        if (!herikaActionCatalogUpsertCustomConfig($updateCode, $updateValues)) {
                            $saved = false;
                            break;
                        }
                    }
                }

                if ($saved && $GLOBALS["db"]->execQuery("COMMIT") !== false) {
                    $updatedCount = count($pendingUpdates);
                    functionEditorRedirectWithNotice(
                        sprintf("Behavior settings saved for %d action%s.", $updatedCount, $updatedCount === 1 ? "" : "s"),
                        "ok",
                        $isEmbed
                    );
                } else {
                    if ($transactionStarted) {
                        $GLOBALS["db"]->execQuery("ROLLBACK");
                    }
                    $message = "Could not save behavior changes.";
                    $messageType = "err";
                }
            }
        }
    } elseif ($_POST["action"] === "update_action_config") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");
        $row = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;
        $configFields = functionEditorGetEditableConfigFieldsForRow($row);
        $submittedConfig = $_POST["config"] ?? [];

        if (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (!is_array($row)) {
            $message = "Unknown action code name.";
            $messageType = "err";
        } elseif (count($configFields) === 0) {
            $message = "This action does not expose any editable configuration fields.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogUpsertCustomConfig")) {
            $message = "Action catalog custom config support is not available in this build.";
            $messageType = "err";
        } else {
            $configValues = [];
            foreach ($configFields as $field) {
                $errorMessage = "";
                $normalizedValue = functionEditorNormalizeSubmittedConfigValue($field, $submittedConfig, $errorMessage);
                if ($errorMessage !== "") {
                    $message = $errorMessage;
                    $messageType = "err";
                    break;
                }

                $configValues[strval($field['key'])] = $normalizedValue;
            }

            if ($messageType !== "err") {
                if (herikaActionCatalogUpsertCustomConfig($codeName, $configValues)) {
                    functionEditorRedirectWithNotice($codeName . " configuration updated.", "ok", $isEmbed);
                } else {
                    $message = "Could not update action configuration.";
                    $messageType = "err";
                }
            }
        }
    } elseif ($_POST["action"] === "update_action_parameters") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");
        $row = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;
        $rawParameters = $_POST["parameters_json"] ?? "";

        if (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (!is_array($row)) {
            $message = "Unknown action code name.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogUpsertCustomParameters")) {
            $message = "Action catalog parameter override support is not available in this build.";
            $messageType = "err";
        } else {
            $errorMessage = "";
            $normalizedParameters = functionEditorNormalizeSubmittedParameterSchema($rawParameters, $errorMessage);
            if ($errorMessage !== "") {
                $message = $errorMessage;
                $messageType = "err";
            } elseif (herikaActionCatalogUpsertCustomParameters($codeName, $normalizedParameters)) {
                functionEditorRedirectWithNotice($codeName . " parameters updated.", "ok", $isEmbed);
            } else {
                $message = "Could not update action parameters.";
                $messageType = "err";
            }
        }
    } elseif ($_POST["action"] === "update_action_text_fields") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");
        $row = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;

        if (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } elseif (!is_array($row)) {
            $message = "Unknown action code name.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogUpsertCustomTextFields")) {
            $message = "Action catalog text override support is not available in this build.";
            $messageType = "err";
        } else {
            $submittedName = functionEditorNormalizeSubmittedActionTextValue($_POST["action_name"] ?? "", "Action name", false);
            $submittedDescription = functionEditorNormalizeSubmittedActionTextValue($_POST["description"] ?? "", "Prompt", true);
            $submittedReturnMessage = functionEditorNormalizeSubmittedActionTextValue($_POST["return_message"] ?? "", "Return message", true);

            $errorMessage = trim(implode(' ', array_filter([
                $submittedName['error'] ?? '',
                $submittedDescription['error'] ?? '',
                $submittedReturnMessage['error'] ?? '',
            ])));

            if ($errorMessage !== '') {
                $message = $errorMessage;
                $messageType = "err";
            } elseif (function_exists("herikaFindActionCatalogActionNameConflict")) {
                $conflictingRow = herikaFindActionCatalogActionNameConflict($submittedName['value'], $codeName);
                if (is_array($conflictingRow) && !empty($conflictingRow['code_name'])) {
                    $conflictingActionName = trim(strval($conflictingRow['action_name'] ?? ''));
                    $conflictingActionName = $conflictingActionName !== '' ? $conflictingActionName : trim(strval($conflictingRow['code_name'] ?? ''));
                    $message = "Action name conflicts with " . $conflictingActionName . " (" . trim(strval($conflictingRow['code_name'] ?? '')) . "). Keep action names unique so renamed actions always resolve to the correct code.";
                    $messageType = "err";
                } elseif (herikaActionCatalogUpsertCustomTextFields($codeName, [
                    'action_name' => $submittedName['value'],
                    'description' => functionEditorNormalizeSubmittedActionTextForStorage($submittedDescription['value']),
                    'return_message' => functionEditorNormalizeSubmittedActionTextForStorage($submittedReturnMessage['value']),
                ])) {
                    functionEditorRedirectWithNotice($codeName . " text updated.", "ok", $isEmbed);
                } else {
                    $message = "Could not update action text.";
                    $messageType = "err";
                }
            } elseif (herikaActionCatalogUpsertCustomTextFields($codeName, [
                'action_name' => $submittedName['value'],
                'description' => functionEditorNormalizeSubmittedActionTextForStorage($submittedDescription['value']),
                'return_message' => functionEditorNormalizeSubmittedActionTextForStorage($submittedReturnMessage['value']),
            ])) {
                functionEditorRedirectWithNotice($codeName . " text updated.", "ok", $isEmbed);
            } else {
                $message = "Could not update action text.";
                $messageType = "err";
            }
        }
    } elseif ($_POST["action"] === "reset_action_override") {
        $codeName = functionEditorTrim($_POST["code_name"] ?? "");

        if ($codeName === "") {
            $message = "Missing action code name.";
            $messageType = "err";
        } elseif (!function_exists("herikaActionCatalogDbReady") || !herikaActionCatalogDbReady()) {
            $message = "Action catalog tables are not available yet. Run database updates first.";
            $messageType = "err";
        } else {
            $baseRow = $GLOBALS["db"]->fetchOne("
                SELECT 1 AS exists
                FROM public.core_action
                WHERE LOWER(code_name) = LOWER(" . herikaActionCatalogSqlText($codeName) . ")
                LIMIT 1
            ");
            $customRow = $GLOBALS["db"]->fetchOne("
                SELECT 1 AS exists
                FROM public.core_action_custom
                WHERE LOWER(code_name) = LOWER(" . herikaActionCatalogSqlText($codeName) . ")
                LIMIT 1
            ");

            $hasBaseAction = isset($baseRow["exists"]);
            $hasCustomOverride = isset($customRow["exists"]);

            if (!$hasBaseAction || !$hasCustomOverride) {
                $message = "This action does not have a resettable custom override.";
                $messageType = "err";
            } elseif (!function_exists("herikaActionCatalogDeleteCustomOverride")) {
                $message = "Action catalog override reset support is not available in this build.";
                $messageType = "err";
            } elseif (herikaActionCatalogDeleteCustomOverride($codeName)) {
                functionEditorRedirectWithNotice($codeName . " reset to base action.", "ok", $isEmbed);
            } else {
                $message = "Could not reset action override.";
                $messageType = "err";
            }
        }
    }
}

if ($message === "" && isset($_GET["notice"])) {
    $message = functionEditorTrim($_GET["notice"]);
    $messageType = functionEditorTrim($_GET["notice_type"] ?? "ok");
}

$search = functionEditorTrim($_GET["search"] ?? "");
$state = strtolower(functionEditorTrim($_GET["state"] ?? "all"));
$scope = strtolower(functionEditorTrim($_GET["scope"] ?? "all"));
$gameFilter = strtolower(functionEditorTrim($_GET["game_function"] ?? "all"));
$customFilter = strtolower(functionEditorTrim($_GET["custom"] ?? "all"));
if (!in_array($state, ["all", "enabled", "disabled"], true)) {
    $state = "all";
}
if (!in_array($scope, ["all", "npc", "followers", "narrator", "dynamic"], true)) {
    $scope = "all";
}
if (!in_array($gameFilter, ["all", "game", "server"], true)) {
    $gameFilter = "all";
}
if (!in_array($customFilter, ["all", "custom", "base"], true)) {
    $customFilter = "all";
}
$currentFilterParams = [
    "search" => $search,
    "state" => $state,
    "scope" => $scope,
    "game_function" => $gameFilter,
    "custom" => $customFilter,
];

$rows = [];
$countAll = 0;
$countEnabled = 0;
$countDisabled = 0;
$activeActionScopeGroups = functionEditorCreateActiveScopeGroups();
$catalogReady = function_exists("herikaActionCatalogDbReady") && herikaActionCatalogDbReady();

if ($catalogReady) {
    $whereParts = [];
    if ($search !== "") {
        $searchLiteral = herikaActionCatalogSqlText("%" . $search . "%");
        $whereParts[] = "(v.code_name ILIKE {$searchLiteral} OR v.action_name ILIKE {$searchLiteral} OR v.description ILIKE {$searchLiteral})";
    }
    if ($state === "enabled") {
        $whereParts[] = "v.is_activated = TRUE";
    } elseif ($state === "disabled") {
        $whereParts[] = "v.is_activated = FALSE";
    }
    if ($scope === "npc") {
        $whereParts[] = "v.available_to_npc = TRUE";
    } elseif ($scope === "followers") {
        $whereParts[] = "v.available_to_followers = TRUE";
    } elseif ($scope === "narrator") {
        $whereParts[] = "v.available_to_narrator = TRUE";
    } elseif ($scope === "dynamic") {
        $whereParts[] = "v.available_to_npc = FALSE AND v.available_to_followers = FALSE AND v.available_to_narrator = FALSE";
    }
    if ($gameFilter === "game") {
        $whereParts[] = "v.game_function = TRUE";
    } elseif ($gameFilter === "server") {
        $whereParts[] = "v.game_function = FALSE";
    }
    if ($customFilter === "custom") {
        $whereParts[] = "EXISTS (SELECT 1 FROM public.core_action_custom c WHERE LOWER(c.code_name) = LOWER(v.code_name))";
    } elseif ($customFilter === "base") {
        $whereParts[] = "NOT EXISTS (SELECT 1 FROM public.core_action_custom c WHERE LOWER(c.code_name) = LOWER(v.code_name))";
    }

    $whereSql = count($whereParts) > 0 ? ("WHERE " . implode(" AND ", $whereParts)) : "";
    $rows = $GLOBALS["db"]->fetchAll("
        SELECT
            v.code_name,
            v.action_name,
            v.description,
            v.return_message,
            v.available_to_npc,
            v.available_to_followers,
            v.available_to_narrator,
            v.is_activated,
            v.parameters_json,
            v.metadata,
            v.game_function,
            v.script_proxy_program,
            EXISTS (
                SELECT 1
                FROM public.core_action b
                WHERE LOWER(b.code_name) = LOWER(v.code_name)
            ) AS has_base,
            EXISTS (
                SELECT 1
                FROM public.core_action_custom c
                WHERE LOWER(c.code_name) = LOWER(v.code_name)
            ) AS is_custom
        FROM public.combined_core_action v
        {$whereSql}
        ORDER BY LOWER(v.action_name), LOWER(v.code_name)
        LIMIT 2000
    ");

    usort($rows, function ($left, $right) {
        $leftHasConfig = functionEditorRowHasConfig($left);
        $rightHasConfig = functionEditorRowHasConfig($right);
        if ($leftHasConfig !== $rightHasConfig) {
            return $leftHasConfig ? -1 : 1;
        }

        $nameCompare = strcasecmp(strval($left["action_name"] ?? ''), strval($right["action_name"] ?? ''));
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        return strcasecmp(strval($left["code_name"] ?? ''), strval($right["code_name"] ?? ''));
    });

    $countAll = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action")["c"] ?? 0);
    $countEnabled = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE is_activated = TRUE")["c"] ?? 0);
    $countDisabled = max(0, $countAll - $countEnabled);
    $activeActionRows = $GLOBALS["db"]->fetchAll("
        SELECT
            v.code_name,
            v.action_name,
            v.description,
            v.available_to_npc,
            v.available_to_followers,
            v.available_to_narrator
        FROM public.combined_core_action v
        WHERE v.is_activated = TRUE
        ORDER BY LOWER(v.action_name), LOWER(v.code_name)
    ");
    $activeActionScopeGroups = functionEditorBuildActiveScopeGroups($activeActionRows);
}

ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
if (!$isEmbed) {
    include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php");
}
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    @font-face {
        font-family: "MagicCards";
        src: url("<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf") format("truetype");
        font-weight: normal;
        font-style: normal;
    }
    main {
        padding-top: <?php echo $isEmbed ? "10px" : "80px"; ?>;
        padding-bottom: 24px;
        padding-left: 5px;
        padding-right: 5px;
        width: 100%;
        margin: 0;
    }
    /* Page header is the shared compact inline row (.chim-page-head in chim-theme.css). */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 12px;
    }
    /* Summary / How It Works cards sit directly under the header, so keep them low. */
    .content-grid > .content-section {
        padding: 12px 16px;
    }
    .content-grid > .content-section h2 {
        margin-bottom: 8px;
        font-size: 1.2em;
    }
    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .content-section:hover {
        border-color: #4a4a4a;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.05);
    }
    .content-section h2 {
        font-family: "MagicCards", serif;
        color: #e6b76c;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        margin-top: 0;
        font-size: 1.4em;
    }
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    .section-header h2 {
        margin: 0;
    }
    .full-width-section {
        grid-column: 1 / -1;
    }
    .full-width-section h2 {
        font-family: "MagicCards", serif;
        color: #e6b76c;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.6em;
        text-align: center;
    }
    .stat-line {
        margin: 8px 0;
        color: #d0d6df;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
        gap: 12px;
    }
    .summary-card {
        display: grid;
        gap: 8px;
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        background: linear-gradient(180deg, rgba(35, 35, 35, 0.96), rgba(26, 26, 26, 0.98));
        box-shadow: inset 0 1px rgba(255, 255, 255, 0.03);
    }
    .summary-card-label {
        color: #d0d6df;
        font-size: 0.9em;
        line-height: 1.35;
    }
    .summary-card-value {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #f6e5bf;
        font-size: 1.35em;
        font-weight: 700;
    }
    .summary-card-value .stat-pill {
        margin-left: 0;
        font-size: 0.72em;
    }
    .stat-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        margin-left: 8px;
        border: 1px solid #4a4a4a;
    }
    .stat-pill.enabled {
        color: #6dd19c;
        border-color: rgba(109, 209, 156, 0.45);
        background: rgba(25, 77, 50, 0.3);
    }
    .stat-pill.disabled {
        color: #ffb3b3;
        border-color: rgba(220, 110, 110, 0.45);
        background: rgba(96, 32, 32, 0.32);
    }
    .stat-pill.scope {
        color: #c9d3e5;
        border-color: rgba(138, 155, 182, 0.35);
        background: rgba(55, 66, 84, 0.28);
    }
    .action-container {
        display: grid;
        gap: 12px;
        margin-bottom: 14px;
    }
    .filter-toolbar {
        display: grid;
        gap: 14px;
        padding: 14px 16px;
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }
    .filter-toolbar-top {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .filter-toolbar-bottom {
        display: grid;
        gap: 12px;
    }
    .live-search-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 0 0 auto;
        min-width: 0;
    }
    .live-search-wrap input[type="text"] {
        width: min(320px, 24vw);
        min-width: 220px;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        background: rgba(28, 28, 28, 0.92);
        color: #f8f9fa;
        box-sizing: border-box;
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.25);
    }
    .live-search-wrap input[type="text"]:focus {
        outline: none;
        border-color: rgba(242, 124, 17, 0.45);
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.12), inset 0 1px 1px rgba(0, 0, 0, 0.2);
    }
    .filter-summary {
        color: #d2b078;
        font-size: 0.92em;
        white-space: nowrap;
    }
    .filter-groups {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 18px;
        align-items: start;
    }
    .filter-group {
        display: grid;
        gap: 8px;
        min-width: 0;
    }
    .filter-group-label {
        font-size: 0.78em;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #e6b76c;
        font-weight: 700;
    }
    .filter-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .filter-chip-row input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .filter-chip-row label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 12px;
        border-radius: 999px;
        border: 1px solid #3a3a3a;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.86), rgba(34, 34, 34, 0.94));
        color: #f8f9fa;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
        user-select: none;
        font-size: 0.92em;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.14);
    }
    .filter-chip-row label:hover {
        background: linear-gradient(180deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 1));
        border-color: rgba(242, 124, 17, 0.3);
        color: rgb(242, 124, 17);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    .filter-chip-row input[type="radio"]:checked + label {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-color: rgba(242, 124, 17, 0.5);
        color: rgb(242, 124, 17);
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.2);
    }
    .filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    .action-button.secondary {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.86), rgba(34, 34, 34, 0.94));
        border: 1px solid #3a3a3a;
        color: #f8f9fa;
    }
    .action-button.secondary:hover {
        background: linear-gradient(180deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 1));
        border-color: rgba(242, 124, 17, 0.3);
        color: rgb(242, 124, 17);
    }
    .summary-action-button {
        white-space: nowrap;
    }
    .action-modal[hidden] {
        display: none !important;
    }
    .action-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(5, 8, 12, 0.76);
        backdrop-filter: blur(3px);
    }
    .action-modal-panel {
        width: min(1120px, 100%);
        max-height: min(88vh, 900px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 14px;
        border: 1px solid #3a3a3a;
        background: linear-gradient(180deg, rgba(33, 33, 33, 0.98), rgba(20, 20, 20, 0.99));
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
    }
    .action-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px 18px;
        border-bottom: 1px solid #3a3a3a;
    }
    .action-modal-title {
        margin: 0 0 8px 0;
        font-family: "MagicCards", serif;
        font-size: 1.45em;
        color: #e6b76c;
        word-spacing: 6px;
    }
    .action-modal-subtitle {
        margin: 0;
        color: #c9d3e5;
        font-size: 0.95em;
        line-height: 1.45;
    }
    .action-modal-close {
        min-width: 44px;
        min-height: 44px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.9), rgba(34, 34, 34, 0.98));
        color: #f8f9fa;
        font-size: 1.2em;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .action-modal-close:hover {
        background: linear-gradient(180deg, rgba(58, 58, 58, 0.94), rgba(48, 48, 48, 1));
        border-color: rgba(242, 124, 17, 0.3);
        color: rgb(242, 124, 17);
        transform: translateY(-1px);
    }
    .action-modal-body {
        padding: 22px 24px 24px;
        overflow: auto;
    }
    .active-scope-list-wrap {
        display: grid;
        gap: 10px;
    }
    .active-scope-row {
        display: grid;
        grid-template-columns: minmax(120px, 150px) 1fr;
        gap: 14px;
        align-items: start;
        padding: 12px 0;
        border-bottom: 1px solid rgba(58, 58, 58, 0.85);
    }
    .active-scope-row:first-child {
        padding-top: 0;
    }
    .active-scope-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }
    .active-scope-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        padding-top: 4px;
    }
    .active-scope-title {
        margin: 0;
        font-size: 1em;
        color: #f0f4fb;
    }
    .active-scope-count {
        color: #e6b76c;
        font-size: 0.88em;
        font-weight: 700;
    }
    .active-scope-actions {
        display: grid;
        gap: 8px;
    }
    .active-scope-empty {
        color: #8f99aa;
        font-size: 0.93em;
        line-height: 1.45;
        padding-top: 4px;
    }
    .active-action-row {
        display: grid;
        grid-template-columns: minmax(220px, 280px) 1fr;
        gap: 12px;
        align-items: start;
        padding: 6px 0;
        border-bottom: 1px solid rgba(58, 58, 58, 0.55);
    }
    .active-action-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .active-action-label {
        color: #f6e5bf;
        font-size: 0.94em;
        line-height: 1.35;
    }
    .active-action-label code {
        color: #d7dfed;
        font-size: 0.9em;
    }
    .active-action-description {
        color: #c1cbdb;
        font-size: 0.92em;
        line-height: 1.45;
    }
    .empty-filter-state {
        display: none;
        padding: 14px 16px;
        border-top: 1px solid #3a3a3a;
        color: #d2b078;
        background: rgba(26, 26, 26, 0.94);
    }
    .table-container {
        width: 100%;
        margin-top: 20px;
        max-height: none;
        /* Let the document own vertical scrolling while retaining a horizontal
           fallback for intermediate viewport widths. */
        overflow-x: auto;
        overflow-y: visible;
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }
    .table-container table {
        width: 100%;
        border-collapse: collapse;
        background: transparent;
        table-layout: fixed;
    }
    .table-container thead {
        position: sticky;
        top: 0;
        z-index: 3;
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-bottom: 2px solid rgba(242, 124, 17, 0.5);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .table-container thead th {
        background: transparent;
        color: rgb(242, 124, 17);
        padding: 15px 12px;
        text-align: left;
        font-family: "MagicCards", serif;
        font-size: 1.1em;
        font-weight: normal;
        letter-spacing: 1px;
        border-bottom: 0;
        box-shadow: none;
    }
    .table-container tbody tr {
        border-bottom: 1px solid #3a3a3a;
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .table-container td {
        padding: 12px;
        vertical-align: top;
        word-wrap: break-word;
        overflow-wrap: break-word;
        color: #e0e0e0;
        background: transparent;
    }
    .table-container tbody tr:hover {
        background: rgba(242, 124, 17, 0.05);
        box-shadow: inset 0 0 10px rgba(242, 124, 17, 0.1);
    }
    .status-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        border: 1px solid #4a4a4a;
    }
    .status-pill.custom {
        color: #6dd19c;
        border-color: rgba(109, 209, 156, 0.45);
        background: rgba(25, 77, 50, 0.3);
    }
    .status-pill.base {
        color: #c9d3e5;
        border-color: rgba(138, 155, 182, 0.35);
        background: rgba(55, 66, 84, 0.28);
    }
    .status-pill.scope {
        color: #f3d8a0;
        border-color: rgba(216, 165, 76, 0.35);
        background: rgba(98, 73, 27, 0.3);
        margin-right: 6px;
        margin-bottom: 6px;
    }
    .status-pill.enabled {
        color: #6dd19c;
        border-color: rgba(109, 209, 156, 0.45);
        background: rgba(25, 77, 50, 0.3);
    }
    .status-pill.disabled {
        color: #ffb3b3;
        border-color: rgba(220, 110, 110, 0.45);
        background: rgba(96, 32, 32, 0.32);
    }
    .status-pill.game {
        color: #b4d9ff;
        border-color: rgba(120, 171, 219, 0.42);
        background: rgba(32, 55, 86, 0.3);
    }
    .status-pill.server {
        color: #d8ccff;
        border-color: rgba(165, 142, 220, 0.4);
        background: rgba(67, 42, 101, 0.28);
    }
    .state-enabled {
        color: #6dd19c;
        font-weight: 600;
    }
    .state-disabled {
        color: #ffb3b3;
        font-weight: 600;
    }
    .return-preview {
        display: block;
        margin-top: 8px;
        color: #9aa7bd;
        font-size: 0.92em;
        line-height: 1.45;
    }
    .parameter-schema-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: fit-content;
        margin-top: 12px;
        padding: 7px 10px;
        border: 1px solid rgba(216, 165, 76, 0.42);
        border-radius: 8px;
        background: rgba(98, 73, 27, 0.22);
        color: #f3d8a0;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.16s ease, border-color 0.16s ease, color 0.16s ease;
    }
    .parameter-schema-toggle:hover {
        background: rgba(98, 73, 27, 0.34);
        border-color: rgba(216, 165, 76, 0.7);
        color: #ffe3ad;
    }
    .parameter-schema-toggle::before {
        content: "▸";
        font-size: 0.9em;
        line-height: 1;
    }
    details[open] > .parameter-schema-toggle::before {
        content: "▾";
    }
    .table-container code {
        white-space: nowrap;
    }
    .json-preview {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 12px;
        line-height: 1.45;
        color: #d5deee;
        background: rgba(19, 24, 31, 0.72);
        border: 1px solid rgba(138, 155, 182, 0.22);
        border-radius: 6px;
        padding: 8px 10px;
    }
    .toast-notification {
        position: fixed;
        top: 24px;
        right: 24px;
        min-width: 280px;
        max-width: 560px;
        background: rgba(19, 24, 31, 0.96);
        color: #e9efff;
        border: 1px solid rgba(138, 155, 182, 0.38);
        border-radius: 10px;
        padding: 12px 14px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        transform: translateY(-6px);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
        z-index: 9999;
    }
    .toast-notification.show {
        opacity: 1;
        transform: translateY(0);
    }
    .inline-action-editor {
        display: grid;
        gap: 8px;
    }
    .inline-action-editor label {
        display: block;
        color: #d0d6df;
        font-size: 0.92em;
    }
    .inline-action-editor .editor-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .inline-action-editor input[type="number"] {
        width: 110px;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #555555;
        background-color: #4a4a4a;
        color: #f8f9fa;
    }
    .inline-action-editor input[type="text"],
    .inline-action-editor textarea,
    .inline-action-editor select {
        width: 100%;
        max-width: 100%;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #555555;
        background-color: #4a4a4a;
        color: #f8f9fa;
        box-sizing: border-box;
    }
    .command-code {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        color: #f0f4fb;
    }
    .command-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .pricing-cell {
        min-width: 250px;
    }
    .pricing-empty {
        color: #8f99aa;
        font-size: 0.92em;
    }
    .helper-text {
        color: #9aa7bd;
        font-size: 0.9em;
        line-height: 1.45;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .action-name-column {
        width: 25%;
    }
    .action-description-column {
        width: 41%;
    }
    .action-behavior-column {
        width: 16%;
    }
    .action-controls-column {
        width: 18%;
    }
    .behavior-fieldset {
        display: grid;
        gap: 4px;
        margin: 0;
        padding: 0;
        border: 0;
        min-inline-size: 0;
    }
    .behavior-toggle {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 5px 7px;
        border: 1px solid transparent;
        border-radius: 6px;
        transition: background 0.15s ease, border-color 0.15s ease;
    }
    .behavior-toggle:hover {
        background: rgba(242, 124, 17, 0.07);
        border-color: rgba(242, 124, 17, 0.22);
    }
    .behavior-toggle input[type="checkbox"] {
        flex: 0 0 auto;
        width: 17px;
        height: 17px;
        margin: 1px 0 0 0;
        accent-color: rgb(242, 124, 17);
        cursor: pointer;
    }
    .behavior-toggle input[type="checkbox"]:focus-visible {
        outline: 2px solid rgba(242, 124, 17, 0.7);
        outline-offset: 2px;
    }
    .behavior-toggle-text {
        color: #d0d6df;
        font-size: 0.86em;
        line-height: 1.32;
        cursor: pointer;
        overflow-wrap: anywhere;
    }
    .behavior-toggle.is-dirty {
        background: rgba(242, 124, 17, 0.11);
        border-color: rgba(242, 124, 17, 0.5);
    }
    .behavior-toggle.is-dirty .behavior-toggle-text {
        color: #f3d8a0;
        font-weight: 650;
    }
    .behavior-unavailable {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 5px 7px;
        color: #8f99aa;
        font-size: 0.84em;
        line-height: 1.32;
    }
    .behavior-unavailable-mark {
        flex: 0 0 auto;
        width: 17px;
        text-align: center;
    }
    .behavior-dirty-summary {
        color: #d2b078;
        font-size: 0.92em;
        line-height: 1.35;
    }
    #bulkBehaviorForm {
        display: inline-flex;
        margin: 0;
    }
    #bulkBehaviorSave {
        width: auto;
        white-space: nowrap;
    }
    #bulkBehaviorSave:focus-visible {
        outline: 2px solid rgba(242, 124, 17, 0.7);
        outline-offset: 2px;
    }
    /* Neutral while there is nothing to save, so the green "go" state only means "changes pending". */
    #bulkBehaviorSave:disabled,
    #bulkBehaviorSave:disabled:hover {
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.92), rgba(46, 46, 46, 0.96));
        border-color: #3a3a3a;
        color: #b9c1cd;
    }
    .table-container td[data-label="Actions"] {
        text-align: center;
    }
    .action-name-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .action-name-title .basic-action-input {
        flex: 1 1 auto;
        min-width: 0;
    }
    .table-container .basic-action-input,
    .table-container .basic-action-description,
    .advanced-options-form input[type="text"],
    .advanced-options-form input[type="number"],
    .advanced-options-form textarea,
    .advanced-options-form select {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        border: 1px solid #3a3a3a;
        border-radius: 4px;
        background: #1a1a1a;
        color: #cccccc;
        padding: 10px;
    }
    .table-container .basic-action-input:focus,
    .table-container .basic-action-description:focus,
    .advanced-options-form input:focus,
    .advanced-options-form textarea:focus,
    .advanced-options-form select:focus {
        outline: 2px solid rgba(242, 124, 17, 0.48);
        outline-offset: 1px;
        border-color: rgb(242, 124, 17);
    }
    .table-container .basic-action-description {
        min-height: 96px;
        resize: vertical;
        line-height: 1.42;
    }
    .action-code-hint {
        display: block;
        margin-top: 8px;
        color: rgb(100, 149, 237);
        font-family: "Courier New", monospace;
        font-size: 0.9em;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    .action-row-status {
        display: inline-block;
        flex: 0 0 auto;
        margin: 0;
        padding: 4px 10px;
        border: 1px solid currentColor;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 700;
    }
    .action-row-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .action-row-buttons form {
        display: inline-flex;
        width: auto;
        margin: 0;
    }
    .action-row-buttons button {
        width: auto;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.85em;
        margin: 2px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }
    .advanced-options-button,
    .action-button.secondary.advanced-options-button {
        background: linear-gradient(135deg, rgba(100, 149, 237, 0.9), rgba(80, 129, 217, 0.9));
        color: #ffffff;
        border-color: rgba(100, 149, 237, 0.3);
    }
    .advanced-options-button:hover,
    .action-button.secondary.advanced-options-button:hover {
        background: linear-gradient(135deg, rgba(80, 129, 217, 1), rgba(60, 109, 197, 1));
        color: #ffffff;
        border-color: rgba(100, 149, 237, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }
    .advanced-options-panel {
        width: min(920px, 100%);
    }
    .advanced-options-content,
    .advanced-options-form {
        display: grid;
        gap: 16px;
    }
    .advanced-section {
        padding: 16px;
        border: 1px solid rgba(138, 155, 182, 0.25);
        border-radius: 10px;
        background: rgba(25, 30, 38, 0.72);
    }
    .advanced-section h4,
    .advanced-section h5 {
        margin: 0 0 12px;
        color: #f3d8a0;
    }
    .advanced-section h5 {
        margin-top: 16px;
        font-size: 0.94em;
    }
    .advanced-section .config-field {
        display: grid;
        gap: 7px;
    }
    .advanced-section .config-field > label {
        color: #e4e9f2;
        font-weight: 650;
    }
    .advanced-field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .advanced-checkbox-row {
        display: flex;
        align-items: center;
        gap: 9px;
        min-height: 40px;
    }
    .advanced-checkbox-row input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: rgb(242, 124, 17);
    }
    .advanced-details > summary {
        cursor: pointer;
        color: #f3d8a0;
        font-weight: 700;
    }
    .advanced-details-body {
        margin-top: 14px;
    }
    .parameter-preview {
        margin-bottom: 14px;
    }
    .code-textarea {
        font-family: Consolas, "Courier New", monospace;
        line-height: 1.4;
    }
    .advanced-status-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 14px;
    }
    .advanced-modal-footer {
        position: sticky;
        bottom: -24px;
        display: flex;
        justify-content: flex-end;
        padding: 14px 0 0;
        background: linear-gradient(180deg, transparent, rgba(20, 20, 20, 0.98) 28%);
    }
    .advanced-modal-footer button {
        min-width: 210px;
    }
    .danger-zone {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        border-color: rgba(220, 110, 110, 0.42);
        background: rgba(96, 32, 32, 0.18);
    }
    .danger-zone h4 {
        color: #ffb3b3;
        margin-bottom: 5px;
    }
    .danger-zone p {
        margin: 0;
        color: #c8ced8;
    }
    @media (max-width: 1024px) {
        main {
            padding-left: 4%;
            padding-right: 4%;
        }
        .content-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        .live-search-wrap {
            width: 100%;
        }
        .live-search-wrap input[type="text"] {
            width: 100%;
            min-width: 0;
        }
        .filter-actions {
            justify-content: flex-start;
            margin-left: 0;
        }
        .action-modal {
            padding: 14px;
        }
        .action-modal-header,
        .action-modal-body {
            padding-left: 16px;
            padding-right: 16px;
        }
        .active-scope-row {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .active-action-row {
            grid-template-columns: 1fr;
            gap: 4px;
        }
        .advanced-field-grid {
            grid-template-columns: 1fr;
        }
        .action-name-column {
            width: 26%;
        }
        .action-description-column {
            width: 30%;
        }
        .action-behavior-column {
            width: 22%;
        }
        .action-controls-column {
            width: 22%;
        }
    }
    @media (max-width: 720px) {
        .table-container {
            overflow: visible;
            max-height: none;
            min-height: 0;
            border: 0;
            background: transparent;
        }
        .table-container table,
        .table-container tbody,
        .table-container tr,
        .table-container td {
            display: block;
            width: 100%;
        }
        .table-container colgroup,
        .table-container thead {
            display: none;
        }
        .table-container tbody tr {
            margin-bottom: 14px;
            border: 1px solid #3a3a3a;
            border-radius: 10px;
            overflow: hidden;
            background: rgba(28, 33, 40, 0.99);
        }
        .table-container td {
            box-sizing: border-box;
            border-bottom: 1px solid #3a3a3a;
            background: transparent !important;
        }
        .table-container td:last-child {
            border-bottom: 0;
        }
        .table-container td::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 7px;
            color: #e6b76c;
            font-family: "MagicCards", serif;
            letter-spacing: 1px;
        }
        .action-modal {
            align-items: stretch;
            padding: 8px;
        }
        .action-modal-panel {
            max-height: calc(100vh - 16px);
        }
        .danger-zone {
            align-items: stretch;
            flex-direction: column;
        }
        .danger-zone button,
        .advanced-modal-footer button {
            width: 100%;
        }
    }
</style>

<main>
    <div id="toast" class="toast-notification"><span class="message"></span></div>

    <div class="page-header chim-page-head">
        <h1 class="api-title chim-page-head-title">Action Editor</h1>
        <p class="page-subtitle chim-page-head-note">Configure available actions exposed to AI prompting and execution</p>
    </div>

    <?php if (!$catalogReady): ?>
        <div class="content-section">
            <h2>Action Catalog Unavailable</h2>
            <p style="margin:0; color:#d0d6df; line-height:1.45;">
                Action catalog tables are not available yet. Run HerikaServer database updates, then reload this page.
            </p>
        </div>
    <?php else: ?>
        <div class="content-grid">
            <div class="content-section">
                <div class="section-header">
                    <h2>Action Summary</h2>
                    <button type="button" class="action-button secondary summary-action-button" id="viewActiveActionsButton">View Active Actions</button>
                </div>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-card-label">Total Actions</div>
                        <div class="summary-card-value"><span class="stat-pill"><?php echo h($countAll); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Enabled</div>
                        <div class="summary-card-value"><span class="stat-pill enabled"><?php echo h($countEnabled); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Disabled</div>
                        <div class="summary-card-value"><span class="stat-pill disabled"><?php echo h($countDisabled); ?></span></div>
                    </div>
                </div>
            </div>
            <div class="content-section">
                <h2>How It Works</h2>
                <p style="margin:0; color:#d0d6df; line-height:1.45;">
                    Edit the action name or description, then press <strong>Save</strong>. Use <strong>Enable</strong> or
                    <strong>Disable</strong> to control whether the AI may use it. Tick the <strong>Behavior</strong>
                    checkboxes on as many rows as you like, then press <strong>Save all changes</strong> once. Return
                    messages, parameters, and technical settings are available under <strong>Advanced Options</strong>.
                </p>
            </div>

            <div id="activeActionsModal" class="action-modal" hidden>
                <div class="action-modal-panel" role="dialog" aria-modal="true" aria-labelledby="activeActionsModalTitle">
                    <div class="action-modal-header">
                        <div>
                            <h3 class="action-modal-title" id="activeActionsModalTitle">Currently Active Actions</h3>
                            <p class="action-modal-subtitle">
                                Enabled actions grouped by scope. Actions available in multiple scopes appear in each relevant section.
                            </p>
                        </div>
                        <button type="button" class="action-modal-close" data-modal-close aria-label="Close active actions modal">&times;</button>
                    </div>
                    <div class="action-modal-body">
                        <div class="active-scope-list-wrap">
                            <?php foreach ($activeActionScopeGroups as $scopeGroup): ?>
                                <?php
                                $scopeLabel = strval($scopeGroup["label"] ?? "");
                                $scopeActions = is_array($scopeGroup["actions"] ?? null) ? $scopeGroup["actions"] : [];
                                ?>
                                <section class="active-scope-row">
                                    <div class="active-scope-meta">
                                        <h4 class="active-scope-title"><?php echo h($scopeLabel); ?></h4>
                                        <div class="active-scope-count"><?php echo h(count($scopeActions)); ?></div>
                                    </div>
                                    <div class="active-scope-actions">
                                        <?php if (count($scopeActions) === 0): ?>
                                            <div class="active-scope-empty">No active actions in this scope.</div>
                                        <?php else: ?>
                                            <?php foreach ($scopeActions as $activeAction): ?>
                                                <?php
                                                $activeActionName = trim(strval($activeAction["action_name"] ?? ""));
                                                $activeCodeName = trim(strval($activeAction["code_name"] ?? ""));
                                                $activeDescription = trim(strval($activeAction["description"] ?? ""));
                                                if ($activeActionName === "") {
                                                    $activeActionName = $activeCodeName;
                                                }
                                                ?>
                                                <div class="active-action-row">
                                                    <div class="active-action-label">
                                                        <strong><?php echo h($activeActionName); ?></strong>
                                                        <code><?php echo h($activeCodeName); ?></code>
                                                    </div>
                                                    <div class="active-action-description">
                                                        <?php echo $activeDescription !== "" ? nl2br(h($activeDescription)) : '<em>No prompt provided.</em>'; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="full-width-section">
                <div class="action-container">
                    <div class="filter-toolbar" id="entries">
                        <div class="filter-toolbar-top">
                            <div class="live-search-wrap">
                                <input type="text" id="actionLiveSearch" value="<?php echo h($search); ?>" placeholder="Search">
                                <div class="filter-summary"><span id="actionVisibleCount"><?php echo h(count($rows)); ?></span> of <?php echo h(count($rows)); ?> shown</div>
                            </div>
                            <div class="filter-actions">
                                <span class="behavior-dirty-summary" id="bulkBehaviorStatus" role="status" aria-live="polite">No unsaved behavior changes</span>
                                <form id="bulkBehaviorForm" method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                    <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                    <input type="hidden" name="action" value="save_behavior_bulk">
                                    <input type="hidden" name="behavior_payload" id="bulkBehaviorPayload" value="">
                                    <button type="submit" class="btn-save" id="bulkBehaviorSave" aria-describedby="bulkBehaviorStatus" disabled>Save all changes</button>
                                </form>
                                <button type="button" class="action-button secondary" id="actionFilterReset">Reset Filters</button>
                            </div>
                        </div>
                        <div class="filter-toolbar-bottom">
                            <div class="filter-groups">
                                <div class="filter-group">
                                    <div class="filter-group-label">State</div>
                                    <div class="filter-chip-row">
                                        <input type="radio" name="live-state" id="live-state-all" value="all" <?php echo $state === "all" ? "checked" : ""; ?>>
                                        <label for="live-state-all">All</label>
                                        <input type="radio" name="live-state" id="live-state-enabled" value="enabled" <?php echo $state === "enabled" ? "checked" : ""; ?>>
                                        <label for="live-state-enabled">Enabled</label>
                                        <input type="radio" name="live-state" id="live-state-disabled" value="disabled" <?php echo $state === "disabled" ? "checked" : ""; ?>>
                                        <label for="live-state-disabled">Disabled</label>
                                    </div>
                                </div>
                                <div class="filter-group">
                                    <div class="filter-group-label">Scope</div>
                                    <div class="filter-chip-row">
                                        <input type="radio" name="live-scope" id="live-scope-all" value="all" <?php echo $scope === "all" ? "checked" : ""; ?>>
                                        <label for="live-scope-all">All</label>
                                        <input type="radio" name="live-scope" id="live-scope-npc" value="npc" <?php echo $scope === "npc" ? "checked" : ""; ?>>
                                        <label for="live-scope-npc">NPC</label>
                                        <input type="radio" name="live-scope" id="live-scope-followers" value="followers" <?php echo $scope === "followers" ? "checked" : ""; ?>>
                                        <label for="live-scope-followers">Followers</label>
                                        <input type="radio" name="live-scope" id="live-scope-narrator" value="narrator" <?php echo $scope === "narrator" ? "checked" : ""; ?>>
                                        <label for="live-scope-narrator">Narrator</label>
                                        <input type="radio" name="live-scope" id="live-scope-dynamic" value="dynamic" <?php echo $scope === "dynamic" ? "checked" : ""; ?>>
                                        <label for="live-scope-dynamic">Dynamic</label>
                                    </div>
                                </div>
                                <div class="filter-group">
                                    <div class="filter-group-label">Dispatch</div>
                                    <div class="filter-chip-row">
                                        <input type="radio" name="live-dispatch" id="live-dispatch-all" value="all" <?php echo $gameFilter === "all" ? "checked" : ""; ?>>
                                        <label for="live-dispatch-all">All</label>
                                        <input type="radio" name="live-dispatch" id="live-dispatch-game" value="game" <?php echo $gameFilter === "game" ? "checked" : ""; ?>>
                                        <label for="live-dispatch-game">Game</label>
                                        <input type="radio" name="live-dispatch" id="live-dispatch-server" value="server" <?php echo $gameFilter === "server" ? "checked" : ""; ?>>
                                        <label for="live-dispatch-server">Server</label>
                                    </div>
                                </div>
                                <div class="filter-group">
                                    <div class="filter-group-label">Source</div>
                                    <div class="filter-chip-row">
                                        <input type="radio" name="live-source" id="live-source-all" value="all" <?php echo $customFilter === "all" ? "checked" : ""; ?>>
                                        <label for="live-source-all">All</label>
                                        <input type="radio" name="live-source" id="live-source-base" value="base" <?php echo $customFilter === "base" ? "checked" : ""; ?>>
                                        <label for="live-source-base">Base</label>
                                        <input type="radio" name="live-source" id="live-source-custom" value="custom" <?php echo $customFilter === "custom" ? "checked" : ""; ?>>
                                        <label for="live-source-custom">Custom</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <colgroup>
                            <col class="action-name-column">
                            <col class="action-description-column">
                            <col class="action-behavior-column">
                            <col class="action-controls-column">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Behavior</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) === 0): ?>
                                <tr><td colspan="4">No actions found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $codeName = strval($row["code_name"] ?? "");
                                $enabled = herikaActionCatalogToBool($row["is_activated"] ?? false);
                                $isCustom = herikaActionCatalogToBool($row["is_custom"] ?? false);
                                $hasBase = herikaActionCatalogToBool($row["has_base"] ?? false);
                                $isNpc = herikaActionCatalogToBool($row["available_to_npc"] ?? false);
                                $isFollowers = herikaActionCatalogToBool($row["available_to_followers"] ?? false);
                                $isNarrator = herikaActionCatalogToBool($row["available_to_narrator"] ?? false);
                                $isGameFunction = herikaActionCatalogToBool($row["game_function"] ?? false);
                                $targetEnabled = $enabled ? "0" : "1";
                                $metadata = herikaActionCatalogDecodeJson($row["metadata"] ?? [], []);
                                $metadataPreview = functionEditorPrettyJson($metadata);
                                $parametersRendered = functionEditorRenderParameterSchema($row["parameters_json"] ?? "{}");
                                $parametersEditorValue = functionEditorPrettyJson(
                                    functionEditorTransformParameterSchemaStrings(
                                        functionEditorNormalizeParameterSchema($row["parameters_json"] ?? "{}"),
                                        'display'
                                    )
                                );
                                $scriptProxyPreview = functionEditorPrettyJson($row["script_proxy_program"] ?? "");
                                $configFields = functionEditorGetEditableConfigFieldsForRow($row);
                                $promotedBehaviorFields = functionEditorIndexPromotedBehaviorFields($configFields);
                                // Advanced Options only renders what the Behavior column does not own.
                                $advancedConfigFields = functionEditorFilterPromotedBehaviorFields($configFields, false);
                                $customConfig = is_array($metadata["custom_config"] ?? null) ? $metadata["custom_config"] : [];
                                $resolvedConfig = function_exists('herikaActionCatalogGetResolvedCustomConfig')
                                    ? herikaActionCatalogGetResolvedCustomConfig($codeName, $row)
                                    : $customConfig;
                                $actionNameValue = strval($row["action_name"] ?? "");
                                $descriptionValue = functionEditorReplaceActionTextVariablesInString(strval($row["description"] ?? ""));
                                $returnMessageValue = functionEditorReplaceActionTextVariablesInString(strval($row["return_message"] ?? ""));
                                if ($advancedOpenCode === $codeName && ($_POST["action"] ?? "") === "update_action_advanced") {
                                    $returnMessageValue = strval($_POST["return_message"] ?? $returnMessageValue);
                                    $parametersEditorValue = strval($_POST["parameters_json"] ?? $parametersEditorValue);
                                    if (is_array($_POST["config"] ?? null)) {
                                        $resolvedConfig = array_merge($resolvedConfig, $_POST["config"]);
                                    }
                                }
                                $searchBlob = strtolower(trim(implode(' ', array_filter([
                                    $codeName,
                                    $actionNameValue,
                                    $descriptionValue,
                                ]))));
                                $rowDomId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName) . '-' . substr(md5($codeName), 0, 8);
                                $basicFormId = 'basic-action-' . $rowDomId;
                                $advancedTemplateId = 'advanced-action-' . $rowDomId;
                                $rowScopes = [];
                                if ($isNpc) {
                                    $rowScopes[] = "npc";
                                }
                                if ($isFollowers) {
                                    $rowScopes[] = "followers";
                                }
                                if ($isNarrator) {
                                    $rowScopes[] = "narrator";
                                }
                                if (count($rowScopes) === 0) {
                                    $rowScopes[] = "dynamic";
                                }
                                ?>
                                <tr
                                    class="action-row"
                                    data-search="<?php echo h($searchBlob); ?>"
                                    data-state="<?php echo $enabled ? 'enabled' : 'disabled'; ?>"
                                    data-scope="<?php echo h(implode(' ', $rowScopes)); ?>"
                                    data-dispatch="<?php echo $isGameFunction ? 'game' : 'server'; ?>"
                                    data-source="<?php echo $isCustom ? 'custom' : 'base'; ?>"
                                >
                                    <td data-label="Name">
                                        <label class="sr-only" for="<?php echo h('name-' . $rowDomId); ?>">Action name for <?php echo h($codeName); ?></label>
                                        <div class="action-name-title">
                                            <input
                                                class="basic-action-input"
                                                type="text"
                                                id="<?php echo h('name-' . $rowDomId); ?>"
                                                name="action_name"
                                                form="<?php echo h($basicFormId); ?>"
                                                value="<?php echo h($actionNameValue); ?>"
                                                required
                                            >
                                            <span class="action-row-status <?php echo $enabled ? 'state-enabled' : 'state-disabled'; ?>">
                                                <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                                            </span>
                                        </div>
                                        <code class="action-code-hint"><?php echo h($codeName); ?></code>
                                    </td>
                                    <td data-label="Description">
                                        <label class="sr-only" for="<?php echo h('description-' . $rowDomId); ?>">Action description for <?php echo h($codeName); ?></label>
                                        <textarea
                                            class="basic-action-description"
                                            id="<?php echo h('description-' . $rowDomId); ?>"
                                            name="description"
                                            form="<?php echo h($basicFormId); ?>"
                                            rows="4"
                                        ><?php echo h($descriptionValue); ?></textarea>
                                    </td>
                                    <td data-label="Behavior">
                                        <fieldset class="behavior-fieldset">
                                            <legend class="sr-only">Behavior settings for <?php echo h($codeName); ?></legend>
                                            <?php foreach (functionEditorGetPromotedBehaviorFieldKeys() as $behaviorKey): ?>
                                                <?php if (isset($promotedBehaviorFields[$behaviorKey])): ?>
                                                    <?php
                                                    $behaviorField = $promotedBehaviorFields[$behaviorKey];
                                                    $behaviorFieldId = 'behavior-' . $rowDomId . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $behaviorKey);
                                                    $behaviorHelpId = $behaviorFieldId . '-help';
                                                    $behaviorHelp = trim(strval($behaviorField['help'] ?? ''));
                                                    $behaviorLabel = trim(strval($behaviorField['label'] ?? '')) !== ''
                                                        ? strval($behaviorField['label'])
                                                        : $behaviorKey;
                                                    $behaviorValue = functionEditorToBool(
                                                        array_key_exists($behaviorKey, $resolvedConfig)
                                                            ? $resolvedConfig[$behaviorKey]
                                                            : (function_exists('herikaActionCatalogGetEditorFieldDefaultValue')
                                                                ? herikaActionCatalogGetEditorFieldDefaultValue($behaviorField, $row)
                                                                : false)
                                                    );
                                                    ?>
                                                    <div class="behavior-toggle">
                                                        <input
                                                            type="checkbox"
                                                            class="behavior-checkbox"
                                                            id="<?php echo h($behaviorFieldId); ?>"
                                                            data-behavior-code="<?php echo h($codeName); ?>"
                                                            data-behavior-key="<?php echo h($behaviorKey); ?>"
                                                            data-behavior-initial="<?php echo $behaviorValue ? '1' : '0'; ?>"
                                                            <?php echo $behaviorValue ? 'checked' : ''; ?>
                                                            <?php if ($behaviorHelp !== ''): ?>aria-describedby="<?php echo h($behaviorHelpId); ?>" title="<?php echo h($behaviorHelp); ?>"<?php endif; ?>
                                                        >
                                                        <label class="behavior-toggle-text" for="<?php echo h($behaviorFieldId); ?>"><?php echo h($behaviorLabel); ?></label>
                                                        <?php if ($behaviorHelp !== ''): ?>
                                                            <span class="sr-only" id="<?php echo h($behaviorHelpId); ?>"><?php echo h($behaviorHelp); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($behaviorKey === 'confirmation_required'): ?>
                                                    <div class="behavior-unavailable">
                                                        <span class="behavior-unavailable-mark" aria-hidden="true">&ndash;</span>
                                                        <span>Confirmation unavailable<span class="sr-only"> for <?php echo h($codeName); ?>: this action cannot prompt before it runs.</span></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </fieldset>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-row-buttons">
                                            <form id="<?php echo h($basicFormId); ?>" method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                                <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                                <input type="hidden" name="action" value="update_action_basic_fields">
                                                <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                                <button type="submit" class="btn-save">Save</button>
                                            </form>
                                            <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                                <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                                <input type="hidden" name="action" value="toggle_action">
                                                <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                            <input type="hidden" name="target_enabled" value="<?php echo h($targetEnabled); ?>">
                                            <button type="submit" class="<?php echo $enabled ? "btn-danger" : "btn-save"; ?>">
                                                    <?php echo $enabled ? "Disable" : "Enable"; ?>
                                                </button>
                                            </form>
                                            <button
                                                type="button"
                                                class="action-button secondary advanced-options-button"
                                                data-advanced-template="<?php echo h($advancedTemplateId); ?>"
                                                data-action-name="<?php echo h($actionNameValue !== '' ? $actionNameValue : $codeName); ?>"
                                                data-code-name="<?php echo h($codeName); ?>"
                                            >Advanced Options</button>
                                        </div>

                                        <template id="<?php echo h($advancedTemplateId); ?>">
                                            <div class="advanced-options-content">
                                                <form class="advanced-options-form" method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                                    <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                                    <input type="hidden" name="action" value="update_action_advanced">
                                                    <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">

                                                    <section class="advanced-section">
                                                        <h4>Response</h4>
                                                        <div class="config-field">
                                                            <label for="<?php echo h('return-' . $rowDomId); ?>">Return Message</label>
                                                            <textarea id="<?php echo h('return-' . $rowDomId); ?>" name="return_message" rows="3"><?php echo h($returnMessageValue); ?></textarea>
                                                            <div class="helper-text">The default event text returned after this action runs.</div>
                                                        </div>
                                                    </section>

                                                    <?php if (count($advancedConfigFields) > 0): ?>
                                                        <section class="advanced-section">
                                                            <h4>Behavior</h4>
                                                            <div class="helper-text" style="margin:-4px 0 12px;">
                                                                Require Confirmation, Follow-up Enabled, and Allow Follow-up Actions are edited in the
                                                                <strong>Behavior</strong> column of the action table.
                                                            </div>
                                                            <div class="advanced-field-grid">
                                                                <?php foreach ($advancedConfigFields as $configField): ?>
                                                                    <?php
                                                                    $fieldKey = strval($configField['key'] ?? '');
                                                                    $fieldId = 'config-' . $rowDomId . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $fieldKey);
                                                                    $fieldType = strval($configField['type'] ?? 'text');
                                                                    $fieldValue = $resolvedConfig[$fieldKey] ?? (function_exists('herikaActionCatalogGetEditorFieldDefaultValue')
                                                                        ? herikaActionCatalogGetEditorFieldDefaultValue($configField, $row)
                                                                        : '');
                                                                    ?>
                                                                    <div class="config-field">
                                                                        <label for="<?php echo h($fieldId); ?>"><?php echo h($configField['label'] ?? $fieldKey); ?></label>
                                                                        <?php if ($fieldType === 'boolean'): ?>
                                                                            <div class="advanced-checkbox-row">
                                                                                <input type="hidden" name="config[<?php echo h($fieldKey); ?>]" value="0">
                                                                                <input type="checkbox" id="<?php echo h($fieldId); ?>" name="config[<?php echo h($fieldKey); ?>]" value="1" <?php echo functionEditorToBool($fieldValue) ? 'checked' : ''; ?>>
                                                                                <span><?php echo functionEditorToBool($fieldValue) ? 'Enabled' : 'Disabled'; ?></span>
                                                                            </div>
                                                                        <?php elseif ($fieldType === 'textarea'): ?>
                                                                            <textarea id="<?php echo h($fieldId); ?>" name="config[<?php echo h($fieldKey); ?>]" rows="3" placeholder="<?php echo h($configField['placeholder'] ?? ''); ?>"><?php echo h($fieldValue); ?></textarea>
                                                                        <?php elseif ($fieldType === 'select'): ?>
                                                                            <select id="<?php echo h($fieldId); ?>" name="config[<?php echo h($fieldKey); ?>]">
                                                                                <?php foreach (($configField['options'] ?? []) as $option): ?>
                                                                                    <?php $optionValue = strval($option['value'] ?? ''); ?>
                                                                                    <option value="<?php echo h($optionValue); ?>" <?php echo $optionValue === strval($fieldValue) ? 'selected' : ''; ?>><?php echo h($option['label'] ?? $optionValue); ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        <?php else: ?>
                                                                            <input
                                                                                type="<?php echo $fieldType === 'integer' || $fieldType === 'number' ? 'number' : 'text'; ?>"
                                                                                id="<?php echo h($fieldId); ?>"
                                                                                name="config[<?php echo h($fieldKey); ?>]"
                                                                                <?php if (is_numeric($configField['minimum'] ?? null)): ?>min="<?php echo h($configField['minimum']); ?>"<?php endif; ?>
                                                                                <?php if (is_numeric($configField['maximum'] ?? null)): ?>max="<?php echo h($configField['maximum']); ?>"<?php endif; ?>
                                                                                <?php if (is_numeric($configField['step'] ?? null)): ?>step="<?php echo h($configField['step']); ?>"<?php elseif ($fieldType === 'integer'): ?>step="1"<?php endif; ?>
                                                                                placeholder="<?php echo h($configField['placeholder'] ?? ''); ?>"
                                                                                value="<?php echo h($fieldValue); ?>"
                                                                            >
                                                                        <?php endif; ?>
                                                                        <?php if (trim(strval($configField['help'] ?? '')) !== ''): ?>
                                                                            <div class="helper-text"><?php echo h($configField['help']); ?></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </section>
                                                    <?php endif; ?>

                                                    <section class="advanced-section">
                                                        <details class="advanced-details">
                                                            <summary>Parameter Schema</summary>
                                                            <div class="advanced-details-body">
                                                                <div class="parameter-preview"><?php echo $parametersRendered; ?></div>
                                                                <div class="config-field">
                                                                    <label for="<?php echo h('parameters-' . $rowDomId); ?>">Parameter Schema JSON</label>
                                                                    <textarea id="<?php echo h('parameters-' . $rowDomId); ?>" name="parameters_json" rows="12" class="code-textarea" placeholder="{&quot;type&quot;:&quot;object&quot;,&quot;properties&quot;:{},&quot;required&quot;:[]}"><?php echo h($parametersEditorValue); ?></textarea>
                                                                    <div class="helper-text">Controls the fields and values the LLM may provide when issuing this action.</div>
                                                                </div>
                                                            </div>
                                                        </details>
                                                    </section>

                                                    <section class="advanced-section">
                                                        <details class="advanced-details">
                                                            <summary>Technical Details</summary>
                                                            <div class="advanced-details-body">
                                                                <div class="advanced-status-list">
                                                                    <?php foreach ($rowScopes as $rowScope): ?><span class="status-pill scope"><?php echo h(ucfirst($rowScope)); ?></span><?php endforeach; ?>
                                                                    <span class="status-pill <?php echo $isGameFunction ? 'game' : 'server'; ?>"><?php echo $isGameFunction ? 'Game' : 'Server'; ?></span>
                                                                    <span class="status-pill <?php echo $isCustom ? 'custom' : 'base'; ?>"><?php echo $isCustom ? 'Custom' : 'Base'; ?></span>
                                                                    <span class="status-pill <?php echo $enabled ? 'enabled' : 'disabled'; ?>"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
                                                                </div>
                                                                <h5>Metadata JSON</h5>
                                                                <pre class="json-preview"><?php echo h($metadataPreview); ?></pre>
                                                                <?php if (!in_array(trim($scriptProxyPreview), ["", "[]", "{}"], true)): ?>
                                                                    <h5>ScriptProxy Program</h5>
                                                                    <pre class="json-preview"><?php echo h($scriptProxyPreview); ?></pre>
                                                                <?php endif; ?>
                                                            </div>
                                                        </details>
                                                    </section>

                                                    <div class="advanced-modal-footer">
                                                        <button type="submit" class="btn-save">Save Advanced Options</button>
                                                    </div>
                                                </form>

                                                <?php if ($isCustom && $hasBase): ?>
                                                    <section class="advanced-section danger-zone">
                                                        <div>
                                                            <h4>Reset Override</h4>
                                                            <p>Restore this action to its shipped defaults.</p>
                                                        </div>
                                                        <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>" onsubmit="return confirm('Reset this action to its base definition? This will delete the custom override row for <?php echo h($codeName); ?>.');">
                                                            <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                                            <input type="hidden" name="action" value="reset_action_override">
                                                            <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                                            <button type="submit" class="btn-danger">Reset Override</button>
                                                        </form>
                                                    </section>
                                                <?php endif; ?>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="empty-filter-state" id="actionFilterEmptyState">No actions match the current filters.</div>
                </div>

                <div id="advancedOptionsModal" class="action-modal" hidden>
                    <div class="action-modal-panel advanced-options-panel" role="dialog" aria-modal="true" aria-labelledby="advancedOptionsModalTitle">
                        <div class="action-modal-header">
                            <div>
                                <h3 class="action-modal-title" id="advancedOptionsModalTitle">Advanced Options</h3>
                                <p class="action-modal-subtitle" id="advancedOptionsModalSubtitle"></p>
                            </div>
                            <button type="button" class="action-modal-close" data-advanced-modal-close aria-label="Close advanced options modal">&times;</button>
                        </div>
                        <div class="action-modal-body" id="advancedOptionsModalBody"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function showToast(message, duration = 5000) {
    const toast = document.getElementById("toast");
    const messageSpan = toast.querySelector(".message");
    messageSpan.textContent = message;
    toast.classList.add("show");
    setTimeout(() => {
        toast.classList.remove("show");
    }, duration);
}
<?php if ($message !== ""): ?>
document.addEventListener("DOMContentLoaded", function() {
    showToast(<?= json_encode($message) ?>);
});
<?php endif; ?>

const ACTION_EDITOR_VIEW_STATE_KEY = "chimActionEditorViewState";
const ACTION_EDITOR_FILTER_NAMES = ["live-state", "live-scope", "live-dispatch", "live-source"];

// Filters and search are client-side only, so stash them (plus scroll offset) in
// sessionStorage right before a POST and replay them on the redirected page load.
function actionEditorSaveViewState() {
    try {
        const searchInput = document.getElementById("actionLiveSearch");
        const filters = {};
        const behavior = {};
        ACTION_EDITOR_FILTER_NAMES.forEach((name) => {
            const checked = document.querySelector(`input[name="${name}"]:checked`);
            filters[name] = checked ? checked.value : "all";
        });

        document.querySelectorAll(".behavior-checkbox").forEach((checkbox) => {
            const codeName = checkbox.dataset.behaviorCode || "";
            const fieldKey = checkbox.dataset.behaviorKey || "";
            const initialValue = checkbox.dataset.behaviorInitial === "1";
            if (!codeName || !fieldKey || checkbox.checked === initialValue) {
                return;
            }
            if (!behavior[codeName]) {
                behavior[codeName] = {};
            }
            behavior[codeName][fieldKey] = checkbox.checked;
        });

        window.sessionStorage.setItem(ACTION_EDITOR_VIEW_STATE_KEY, JSON.stringify({
            search: searchInput ? searchInput.value : "",
            filters: filters,
            behavior: behavior,
            scrollY: Math.round(window.scrollY || window.pageYOffset || 0),
        }));
    } catch (error) {
        /* sessionStorage unavailable: fall back to the URL filters and default scroll. */
    }
}

// Read once and clear, so only a save round-trip restores state - not a manual reload.
function actionEditorConsumeViewState() {
    try {
        const raw = window.sessionStorage.getItem(ACTION_EDITOR_VIEW_STATE_KEY);
        window.sessionStorage.removeItem(ACTION_EDITOR_VIEW_STATE_KEY);
        const parsed = raw ? JSON.parse(raw) : null;
        return parsed && typeof parsed === "object" ? parsed : null;
    } catch (error) {
        return null;
    }
}

const ACTION_EDITOR_RESTORED_VIEW_STATE = actionEditorConsumeViewState();

document.addEventListener("submit", function(event) {
    if (event.defaultPrevented || !(event.target instanceof HTMLFormElement)) {
        return;
    }
    actionEditorSaveViewState();
});

document.addEventListener("DOMContentLoaded", function() {
    const restoredView = ACTION_EDITOR_RESTORED_VIEW_STATE;
    const rows = Array.from(document.querySelectorAll(".action-row"));
    if (!rows.length) {
        return;
    }

    const searchInput = document.getElementById("actionLiveSearch");
    const visibleCount = document.getElementById("actionVisibleCount");
    const emptyState = document.getElementById("actionFilterEmptyState");
    const resetButton = document.getElementById("actionFilterReset");

    function selectedValue(name) {
        const checked = document.querySelector(`input[name="${name}"]:checked`);
        return checked ? checked.value : "all";
    }

    function applyFilters() {
        const searchValue = (searchInput?.value || "").trim().toLowerCase();
        const stateValue = selectedValue("live-state");
        const scopeValue = selectedValue("live-scope");
        const dispatchValue = selectedValue("live-dispatch");
        const sourceValue = selectedValue("live-source");
        let shown = 0;

        rows.forEach((row) => {
            const matchesSearch = searchValue === "" || (row.dataset.search || "").includes(searchValue);
            const matchesState = stateValue === "all" || row.dataset.state === stateValue;
            const rowScopes = (row.dataset.scope || "").split(/\s+/).filter(Boolean);
            const matchesScope = scopeValue === "all" || rowScopes.includes(scopeValue);
            const matchesDispatch = dispatchValue === "all" || row.dataset.dispatch === dispatchValue;
            const matchesSource = sourceValue === "all" || row.dataset.source === sourceValue;
            const visible = matchesSearch && matchesState && matchesScope && matchesDispatch && matchesSource;

            row.style.display = visible ? "" : "none";
            if (visible) {
                shown += 1;
            }
        });

        if (visibleCount) {
            visibleCount.textContent = String(shown);
        }
        if (emptyState) {
            emptyState.style.display = shown === 0 ? "block" : "none";
        }
    }

    if (searchInput) {
        searchInput.addEventListener("input", applyFilters);
    }

    ACTION_EDITOR_FILTER_NAMES.forEach((name) => {
        document.querySelectorAll(`input[name="${name}"]`).forEach((input) => {
            input.addEventListener("change", applyFilters);
        });
    });

    if (resetButton) {
        resetButton.addEventListener("click", function() {
            if (searchInput) {
                searchInput.value = "";
            }

            ["live-state-all", "live-scope-all", "live-dispatch-all", "live-source-all"].forEach((id) => {
                const input = document.getElementById(id);
                if (input) {
                    input.checked = true;
                }
            });

            applyFilters();
        });
    }

    if (restoredView) {
        if (searchInput && typeof restoredView.search === "string") {
            searchInput.value = restoredView.search;
        }

        const restoredFilters = restoredView.filters && typeof restoredView.filters === "object"
            ? restoredView.filters
            : {};
        ACTION_EDITOR_FILTER_NAMES.forEach((name) => {
            const value = restoredFilters[name];
            if (typeof value !== "string") {
                return;
            }
            const input = document.querySelector(`input[name="${name}"][value="${value}"]`);
            if (input) {
                input.checked = true;
            }
        });
    }

    applyFilters();

    if (restoredView && typeof restoredView.scrollY === "number" && restoredView.scrollY > 0) {
        // Run after layout settles so the restored offset wins over the "#entries" anchor jump.
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                window.scrollTo(0, restoredView.scrollY);
            });
        });
    }
});

document.addEventListener("DOMContentLoaded", function() {
    const bulkForm = document.getElementById("bulkBehaviorForm");
    const payloadInput = document.getElementById("bulkBehaviorPayload");
    const saveButton = document.getElementById("bulkBehaviorSave");
    const statusLabel = document.getElementById("bulkBehaviorStatus");
    const checkboxes = Array.from(document.querySelectorAll(".behavior-checkbox"));

    if (!bulkForm || !payloadInput || !saveButton || !statusLabel || !checkboxes.length) {
        return;
    }

    const saveButtonLabel = saveButton.textContent.trim();

    function isDirty(checkbox) {
        return checkbox.checked !== (checkbox.dataset.behaviorInitial === "1");
    }

    // Only changed checkboxes are collected, so unchanged rows are never written.
    function collectChanges() {
        const changes = {};
        checkboxes.forEach((checkbox) => {
            const codeName = checkbox.dataset.behaviorCode || "";
            const fieldKey = checkbox.dataset.behaviorKey || "";
            if (!codeName || !fieldKey || !isDirty(checkbox)) {
                return;
            }
            if (!changes[codeName]) {
                changes[codeName] = {};
            }
            changes[codeName][fieldKey] = checkbox.checked;
        });
        return changes;
    }

    function refreshDirtyState() {
        const changes = collectChanges();
        const changedCodes = Object.keys(changes);
        const changedFieldCount = changedCodes.reduce(
            (total, codeName) => total + Object.keys(changes[codeName]).length,
            0
        );

        checkboxes.forEach((checkbox) => {
            const toggle = checkbox.closest(".behavior-toggle");
            if (toggle) {
                toggle.classList.toggle("is-dirty", isDirty(checkbox));
            }
        });

        saveButton.disabled = changedFieldCount === 0;
        saveButton.textContent = changedFieldCount === 0
            ? saveButtonLabel
            : `${saveButtonLabel} (${changedFieldCount})`;
        statusLabel.textContent = changedFieldCount === 0
            ? "No unsaved behavior changes"
            : `${changedFieldCount} unsaved change${changedFieldCount === 1 ? "" : "s"} across `
                + `${changedCodes.length} action${changedCodes.length === 1 ? "" : "s"}`;
    }

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", refreshDirtyState);
    });

    const restoredBehavior = ACTION_EDITOR_RESTORED_VIEW_STATE?.behavior;
    if (restoredBehavior && typeof restoredBehavior === "object") {
        checkboxes.forEach((checkbox) => {
            const codeName = checkbox.dataset.behaviorCode || "";
            const fieldKey = checkbox.dataset.behaviorKey || "";
            if (Object.prototype.hasOwnProperty.call(restoredBehavior[codeName] || {}, fieldKey)) {
                checkbox.checked = Boolean(restoredBehavior[codeName][fieldKey]);
            }
        });
    }

    bulkForm.addEventListener("submit", function(event) {
        const changes = collectChanges();
        if (!Object.keys(changes).length) {
            event.preventDefault();
            refreshDirtyState();
            return;
        }

        payloadInput.value = JSON.stringify({ rows: changes });
        saveButton.disabled = true;
    });

    // Any other submit reloads the page, which would silently drop pending toggles.
    document.addEventListener("submit", function(event) {
        if (event.defaultPrevented || event.target === bulkForm) {
            return;
        }
        if (!Object.keys(collectChanges()).length) {
            return;
        }
        if (!window.confirm("You have unsaved Behavior changes. Continue and discard them?")) {
            event.preventDefault();
        }
    }, true);

    refreshDirtyState();
});

document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("activeActionsModal");
    const openButton = document.getElementById("viewActiveActionsButton");
    if (!modal || !openButton) {
        return;
    }

    const closeButtons = modal.querySelectorAll("[data-modal-close]");

    function openModal() {
        modal.hidden = false;
        document.body.style.overflow = "hidden";
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = "";
    }

    openButton.addEventListener("click", openModal);

    closeButtons.forEach((button) => {
        button.addEventListener("click", closeModal);
    });

    modal.addEventListener("click", function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape" && !modal.hidden) {
            closeModal();
        }
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("advancedOptionsModal");
    const modalBody = document.getElementById("advancedOptionsModalBody");
    const modalTitle = document.getElementById("advancedOptionsModalTitle");
    const modalSubtitle = document.getElementById("advancedOptionsModalSubtitle");
    const closeButton = modal?.querySelector("[data-advanced-modal-close]");
    const openButtons = Array.from(document.querySelectorAll("[data-advanced-template]"));
    let previousFocus = null;

    if (!modal || !modalBody || !modalTitle || !modalSubtitle || !closeButton || !openButtons.length) {
        return;
    }

    function focusableElements() {
        return Array.from(modal.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'))
            .filter((element) => element.offsetParent !== null);
    }

    function openAdvancedOptions(button) {
        const template = document.getElementById(button.dataset.advancedTemplate || "");
        if (!(template instanceof HTMLTemplateElement)) {
            return;
        }

        previousFocus = button;
        modalBody.replaceChildren(template.content.cloneNode(true));
        modalTitle.textContent = "Advanced Options";
        modalSubtitle.textContent = `${button.dataset.actionName || button.dataset.codeName || "Action"} (${button.dataset.codeName || ""})`;
        modal.hidden = false;
        document.body.style.overflow = "hidden";

        window.requestAnimationFrame(() => {
            const focusable = focusableElements();
            (focusable[0] || closeButton).focus();
        });
    }

    function closeAdvancedOptions() {
        modal.hidden = true;
        modalBody.replaceChildren();
        document.body.style.overflow = "";
        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
        previousFocus = null;
    }

    openButtons.forEach((button) => {
        button.addEventListener("click", function() {
            openAdvancedOptions(button);
        });
    });

    closeButton.addEventListener("click", closeAdvancedOptions);
    modal.addEventListener("click", function(event) {
        if (event.target === modal) {
            closeAdvancedOptions();
        }
    });

    modalBody.addEventListener("change", function(event) {
        if (event.target instanceof HTMLInputElement && event.target.type === "checkbox") {
            const stateLabel = event.target.closest(".advanced-checkbox-row")?.querySelector("span");
            if (stateLabel) {
                stateLabel.textContent = event.target.checked ? "Enabled" : "Disabled";
            }
        }
    });

    document.addEventListener("keydown", function(event) {
        if (modal.hidden) {
            return;
        }
        if (event.key === "Escape") {
            event.preventDefault();
            closeAdvancedOptions();
            return;
        }
        if (event.key !== "Tab") {
            return;
        }

        const focusable = focusableElements();
        if (!focusable.length) {
            event.preventDefault();
            closeButton.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const reopenCode = <?php echo json_encode($advancedOpenCode); ?>;
    if (reopenCode) {
        const reopenButton = openButtons.find((button) => button.dataset.codeName === reopenCode);
        if (reopenButton) {
            openAdvancedOptions(reopenButton);
        }
    }
});
</script>

</body>
</html>
