<?php
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath));
if ($webRoot == '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");

$TITLE = "Action Editor";
$isEmbed = isset($_GET['embed']) && strval($_GET['embed']) === '1';

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");

if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
    $GLOBALS["db"] = new sql();
}

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
            $submittedDescription = functionEditorNormalizeSubmittedActionTextValue($_POST["description"] ?? "", "Description", true);
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
$countNpc = 0;
$countFollowers = 0;
$countNarrator = 0;
$countDynamic = 0;
$countGameFunction = 0;
$countServerAction = 0;
$countCustom = 0;
$countBase = 0;
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
    $countNpc = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_npc = TRUE")["c"] ?? 0);
    $countFollowers = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_followers = TRUE")["c"] ?? 0);
    $countNarrator = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_narrator = TRUE")["c"] ?? 0);
    $countDynamic = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_npc = FALSE AND available_to_followers = FALSE AND available_to_narrator = FALSE")["c"] ?? 0);
    $countGameFunction = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE game_function = TRUE")["c"] ?? 0);
    $countServerAction = max(0, $countAll - $countGameFunction);
    $countCustom = intval($GLOBALS["db"]->fetchOne("
        SELECT COUNT(*) AS c
        FROM public.combined_core_action v
        WHERE EXISTS (
            SELECT 1
            FROM public.core_action_custom c
            WHERE LOWER(c.code_name) = LOWER(v.code_name)
        )
    ")["c"] ?? 0);
    $countBase = max(0, $countAll - $countCustom);
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
        padding-top: <?php echo $isEmbed ? "30px" : "80px"; ?>;
        padding-bottom: 40px;
        padding-left: 5px;
        padding-right: 5px;
        width: 100%;
        margin: 0;
    }
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }
    .page-header h1.api-title {
        margin-bottom: 8px;
    }
    h1.api-title {
        margin: 0 0 20px 0;
        font-family: "MagicCards", serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: #e6b76c;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        text-align: center;
    }
    .page-subtitle {
        margin: 0;
        color: #bbb;
        font-size: 1.1em;
        line-height: 1.6;
    }
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
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
        gap: 16px;
        margin-bottom: 20px;
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
        gap: 10px;
        flex-wrap: wrap;
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
    .empty-filter-state {
        display: none;
        padding: 14px 16px;
        border-top: 1px solid #3a3a3a;
        color: #d2b078;
        background: rgba(26, 26, 26, 0.94);
    }
    .table-container {
        width: 100%;
        overflow-x: auto;
        margin-top: 20px;
        min-height: 72vh;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        background: rgba(18, 18, 18, 0.82);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
    }
    .table-container table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: transparent;
        table-layout: fixed;
    }
    .table-container thead {
        position: sticky;
        top: 0;
        z-index: 3;
    }
    .table-container thead th {
        background: linear-gradient(135deg, rgb(58, 58, 58), rgb(48, 48, 48));
        color: #e6b76c;
        padding: 12px 10px;
        text-align: left;
        font-family: "MagicCards", serif;
        letter-spacing: 1px;
        border-bottom: 2px solid rgba(230, 183, 108, 0.3);
        box-shadow: inset 0 -1px 0 rgba(18, 18, 18, 0.8);
    }
    .table-container td {
        padding: 10px;
        border-bottom: 1px solid #3a3a3a;
        vertical-align: top;
        word-wrap: break-word;
        overflow-wrap: break-word;
        background: rgba(33, 38, 46, 0.98);
    }
    .table-container tbody tr:nth-child(even) td {
        background: rgba(28, 33, 40, 0.99);
    }
    .table-container tbody tr:hover td {
        background: rgba(58, 58, 58, 0.78);
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
        .table-container {
            min-height: 60vh;
            max-height: calc(100vh - 100px);
        }
    }
</style>

<main>
    <div id="toast" class="toast-notification"><span class="message"></span></div>

    <div class="page-header">
        <h1 class="api-title">Action Editor</h1>
        <p class="page-subtitle">Configure available actions exposed to AI prompting and execution</p>
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
                <h2>Action Summary</h2>
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
                    <div class="summary-card">
                        <div class="summary-card-label">NPC Scope</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countNpc); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Follower Scope</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countFollowers); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Narrator Scope</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countNarrator); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Dynamic Only</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countDynamic); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Game Functions</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countGameFunction); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Server Only</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countServerAction); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Custom Actions</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countCustom); ?></span></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-card-label">Base Actions</div>
                        <div class="summary-card-value"><span class="stat-pill scope"><?php echo h($countBase); ?></span></div>
                    </div>
                </div>
            </div>
            <div class="content-section">
                <h2>How It Works</h2>
                <p style="margin:0; color:#d0d6df; line-height:1.45;">
                    Toggling an action writes a persistent override into <code>core_action_custom</code>.
                    Built-in defaults in <code>core_action</code> remain untouched. Scope flags decide whether an action
                    is available to NPC mode, follower mode, or only as a dynamic runtime action. Parameter schemas mirror
                    the exported JSON function definition, metadata holds dispatch details such as <code>plugin_command</code>
                    versus <code>script_proxy</code>, and the pricing column can override selected gold costs per action
                    without changing the shipped base catalog.
                </p>
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
                        <thead>
                            <tr>
                                <th>Command</th>
                                <th>Info</th>
                                <th>Config</th>
                                <th>Parameters</th>
                                <th>Metadata</th>
                                <th>Toggle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) === 0): ?>
                                <tr><td colspan="6">No actions found.</td></tr>
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
                                $customConfig = is_array($metadata["custom_config"] ?? null) ? $metadata["custom_config"] : [];
                                $resolvedConfig = function_exists('herikaActionCatalogGetResolvedCustomConfig')
                                    ? herikaActionCatalogGetResolvedCustomConfig($codeName, $row)
                                    : $customConfig;
                                $actionNameValue = strval($row["action_name"] ?? "");
                                $descriptionValue = functionEditorReplaceActionTextVariablesInString(strval($row["description"] ?? ""));
                                $returnMessageValue = functionEditorReplaceActionTextVariablesInString(strval($row["return_message"] ?? ""));
                                $searchBlob = strtolower(trim(implode(' ', array_filter([
                                    $codeName,
                                    $actionNameValue,
                                ]))));
                                $rowScope = "dynamic";
                                if ($isNarrator) {
                                    $rowScope = "narrator";
                                } elseif ($isFollowers) {
                                    $rowScope = "followers";
                                } elseif ($isNpc) {
                                    $rowScope = "npc";
                                }
                                ?>
                                <tr
                                    class="action-row"
                                    data-search="<?php echo h($searchBlob); ?>"
                                    data-state="<?php echo $enabled ? 'enabled' : 'disabled'; ?>"
                                    data-scope="<?php echo h($rowScope); ?>"
                                    data-dispatch="<?php echo $isGameFunction ? 'game' : 'server'; ?>"
                                    data-source="<?php echo $isCustom ? 'custom' : 'base'; ?>"
                                >
                                    <td style="min-width: 220px;">
                                        <code class="command-code"><?php echo h($codeName); ?></code>
                                        <div class="command-meta">
                                            <?php if ($isNpc): ?>
                                                <span class="status-pill scope">NPC</span>
                                            <?php endif; ?>
                                            <?php if ($isFollowers): ?>
                                                <span class="status-pill scope">Followers</span>
                                            <?php endif; ?>
                                            <?php if ($isNarrator): ?>
                                                <span class="status-pill scope">Narrator</span>
                                            <?php endif; ?>
                                            <?php if (!$isNpc && !$isFollowers && !$isNarrator): ?>
                                                <span class="status-pill scope">Dynamic</span>
                                            <?php endif; ?>
                                            <span class="status-pill <?php echo $isGameFunction ? "game" : "server"; ?>"><?php echo $isGameFunction ? "Game" : "Server"; ?></span>
                                            <span class="status-pill <?php echo $isCustom ? "custom" : "base"; ?>"><?php echo $isCustom ? "Custom" : "Base"; ?></span>
                                            <span class="status-pill <?php echo $enabled ? "enabled" : "disabled"; ?>"><?php echo $enabled ? "Enabled" : "Disabled"; ?></span>
                                        </div>
                                    </td>
                                    <td style="max-width: 360px;">
                                        <div style="margin-bottom:8px;"><strong><?php echo h($actionNameValue); ?></strong></div>
                                        <?php echo nl2br(h($descriptionValue)); ?>
                                        <span class="return-preview">
                                            Return: <?php echo trim($returnMessageValue) !== "" ? nl2br(h($returnMessageValue)) : '<em>None</em>'; ?>
                                        </span>
                                        <div class="inline-action-editor" style="margin-top: 12px;">
                                            <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                                <input type="hidden" name="action" value="update_action_text_fields">
                                                <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                                <div class="config-field" style="margin-bottom: 12px;">
                                                    <label for="<?php echo h('text-name-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>">Name</label>
                                                    <div class="editor-controls">
                                                        <input
                                                            type="text"
                                                            id="<?php echo h('text-name-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>"
                                                            name="action_name"
                                                            value="<?php echo h($actionNameValue); ?>"
                                                        >
                                                    </div>
                                                </div>
                                                <div class="config-field" style="margin-bottom: 12px;">
                                                    <label for="<?php echo h('text-description-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>">Description</label>
                                                    <div class="editor-controls">
                                                        <textarea
                                                            id="<?php echo h('text-description-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>"
                                                            name="description"
                                                            rows="4"
                                                        ><?php echo h($descriptionValue); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="config-field">
                                                    <label for="<?php echo h('text-return-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>">Return Message</label>
                                                    <div class="editor-controls">
                                                        <textarea
                                                            id="<?php echo h('text-return-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>"
                                                            name="return_message"
                                                            rows="3"
                                                        ><?php echo h($returnMessageValue); ?></textarea>
                                                    </div>
                                                    <div class="helper-text">
                                                        Edit the visible action name, the prompt description, and the default return text from this row.
                                                    </div>
                                                </div>
                                                <div class="editor-controls">
                                                    <button type="submit" class="btn-save">Save Text</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="pricing-cell">
                                        <?php if (count($configFields) > 0): ?>
                                            <div class="inline-action-editor">
                                                <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                                    <input type="hidden" name="action" value="update_action_config">
                                                    <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                                    <?php foreach ($configFields as $configField): ?>
                                                        <?php
                                                        $fieldKey = strval($configField['key'] ?? '');
                                                        $fieldId = 'config-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName . '-' . $fieldKey);
                                                        $fieldType = strval($configField['type'] ?? 'text');
                                                        $fieldValue = $resolvedConfig[$fieldKey] ?? (function_exists('herikaActionCatalogGetEditorFieldDefaultValue')
                                                            ? herikaActionCatalogGetEditorFieldDefaultValue($configField, $row)
                                                            : '');
                                                        ?>
                                                        <div class="config-field" style="margin-bottom: 14px;">
                                                            <label for="<?php echo h($fieldId); ?>"><?php echo h($configField['label'] ?? $fieldKey); ?></label>
                                                            <div class="editor-controls">
                                                                <?php if ($fieldType === 'boolean'): ?>
                                                                    <input type="hidden" name="config[<?php echo h($fieldKey); ?>]" value="0">
                                                                    <input
                                                                        type="checkbox"
                                                                        id="<?php echo h($fieldId); ?>"
                                                                        name="config[<?php echo h($fieldKey); ?>]"
                                                                        value="1"
                                                                        <?php echo functionEditorToBool($fieldValue) ? 'checked' : ''; ?>
                                                                    >
                                                                <?php elseif ($fieldType === 'textarea'): ?>
                                                                    <textarea
                                                                        id="<?php echo h($fieldId); ?>"
                                                                        name="config[<?php echo h($fieldKey); ?>]"
                                                                        rows="3"
                                                                        placeholder="<?php echo h($configField['placeholder'] ?? ''); ?>"
                                                                    ><?php echo h($fieldValue); ?></textarea>
                                                                <?php elseif ($fieldType === 'select'): ?>
                                                                    <select
                                                                        id="<?php echo h($fieldId); ?>"
                                                                        name="config[<?php echo h($fieldKey); ?>]"
                                                                    >
                                                                        <?php foreach (($configField['options'] ?? []) as $option): ?>
                                                                            <?php $optionValue = strval($option['value'] ?? ''); ?>
                                                                            <option value="<?php echo h($optionValue); ?>" <?php echo $optionValue === strval($fieldValue) ? 'selected' : ''; ?>>
                                                                                <?php echo h($option['label'] ?? $optionValue); ?>
                                                                            </option>
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
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <div class="editor-controls">
                                                        <button type="submit" class="btn-save">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="pricing-empty">No editable config</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="min-width: 420px;">
                                        <?php echo $parametersRendered; ?>
                                        <div class="inline-action-editor" style="margin-top: 12px;">
                                            <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                                <input type="hidden" name="action" value="update_action_parameters">
                                                <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                                <div class="config-field">
                                                    <label for="<?php echo h('parameters-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>">Edit Parameter Schema</label>
                                                    <div class="editor-controls">
                                                        <textarea
                                                            id="<?php echo h('parameters-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $codeName)); ?>"
                                                            name="parameters_json"
                                                            rows="12"
                                                            style="font-family: Consolas, monospace;"
                                                            placeholder="{&quot;type&quot;:&quot;object&quot;,&quot;properties&quot;:{},&quot;required&quot;:[]}"
                                                        ><?php echo h($parametersEditorValue); ?></textarea>
                                                    </div>
                                                    <div class="helper-text">
                                                        Save a full <code>parameters_json</code> override. This updates enums, properties, descriptions, and required fields for built-in or custom actions.
                                                    </div>
                                                </div>
                                                <div class="editor-controls">
                                                    <button type="submit" class="btn-save">Save Parameters</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                    <td style="min-width: 320px;">
                                        <details>
                                            <summary class="return-preview" style="cursor:pointer;">Metadata JSON</summary>
                                            <pre class="json-preview"><?php echo h($metadataPreview); ?></pre>
                                        </details>
                                        <?php if (!in_array(trim($scriptProxyPreview), ["", "[]", "{}"], true)): ?>
                                            <details style="margin-top:10px;">
                                                <summary class="return-preview" style="cursor:pointer;">ScriptProxy Program</summary>
                                                <pre class="json-preview"><?php echo h($scriptProxyPreview); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>">
                                            <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                            <input type="hidden" name="action" value="toggle_action">
                                            <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                            <input type="hidden" name="target_enabled" value="<?php echo h($targetEnabled); ?>">
                                            <button type="submit" class="<?php echo $enabled ? "btn-danger" : "btn-save"; ?>">
                                                <?php echo $enabled ? "Disable" : "Enable"; ?>
                                            </button>
                                        </form>
                                        <?php if ($isCustom && $hasBase): ?>
                                            <form method="post" action="<?php echo h(functionEditorBuildUrl($currentFilterParams, $isEmbed, "entries")); ?>" style="margin-top: 8px;" onsubmit="return confirm('Reset this action to its base definition? This will delete the custom override row for <?php echo h($codeName); ?>.');">
                                                <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                                <input type="hidden" name="action" value="reset_action_override">
                                                <input type="hidden" name="code_name" value="<?php echo h($codeName); ?>">
                                                <button type="submit" class="action-button secondary">
                                                    Reset Override
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="empty-filter-state" id="actionFilterEmptyState">No actions match the current filters.</div>
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

document.addEventListener("DOMContentLoaded", function() {
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
            const matchesScope = scopeValue === "all" || row.dataset.scope === scopeValue;
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

    document.querySelectorAll('input[name="live-state"], input[name="live-scope"], input[name="live-dispatch"], input[name="live-source"]').forEach((input) => {
        input.addEventListener("change", applyFilters);
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

    applyFilters();
});
</script>

</body>
</html>
