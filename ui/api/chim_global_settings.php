<?php

error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once LIB_PATH . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrap(BASE_PATH . DIRECTORY_SEPARATOR, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
]);
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'prisma_settings_catalog.php';

function chimGlobalSettingsRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimGlobalSettingsLabel(string $name): string
{
    $custom = [
        'PROMPT_HEAD' => 'Prompt Head', 'EMOTEMOODS' => 'Emote Moods', 'RECHAT_MODE' => 'Rechat Mode',
        'CORE_CONNECTOR_PLAYER' => 'Player Respeech', 'CORE_CONNECTOR_SUMMARY' => 'Summaries',
        'CORE_CONNECTOR_MEDIUMTERM' => 'Background & Memory Tasks', 'CORE_CONNECTOR_SCENECLASSIFIER' => 'Scene Classifier',
        'CORE_CONNECTOR_PROFILES' => 'Profile Tasks', 'CORE_CONNECTOR_DIRECTOR' => 'Director Mode',
        'CORE_CONNECTOR_BGL' => 'Background Life', 'RELLLM_CONNECTOR' => 'Relationship Manager',
        'PLAYER_RESPEECH' => 'Player Respeech Available', 'CORE_CONNECTOR_SUMMARY_ENABLED' => 'Summaries Available',
        'CORE_CONNECTOR_MEDIUMTERM_ENABLED' => 'Background & Memory Tasks Available',
        'CORE_CONNECTOR_PROFILES_ENABLED' => 'Profile Tasks Available',
        'CORE_CONNECTOR_DIRECTOR_ENABLED' => 'Director Mode Available',
        'CORE_CONNECTOR_BGL_ENABLED' => 'Background Life Available',
        'BGL_TRIGGER_HOURS' => 'Background Life Trigger Time', 'OGHMA_INFINIUM' => 'Oghma Infinium',
        'OGHMA_AMOUNT' => 'Oghma Articles Amount', 'RACIAL_OGHMA' => 'Force Racial Oghma',
        'LOCATION_OGHMA' => 'Force Location Oghma', 'DETECT_MAGIC_EVENT' => 'Detect Magic Events',
        'COMPACT_CHAT_ENABLED' => 'Compact Chat',
        'NEVER_CLEAR_RELATIONSHIP_DATA' => 'Never Clear Relationship Data',
        'PROMPT_HEAD_MARKDOWN_ENABLED' => 'Compact Prompt Info',
    ];
    if (isset($custom[$name])) {
        return $custom[$name];
    }
    $parts = explode('@', $name);
    return ucwords(strtolower(str_replace('_', ' ', end($parts) ?: $name)));
}

function chimGlobalSettingsFieldMap(): array
{
    $map = [];
    foreach (chimPrismaGlobalSettingsSections() as $section => $fields) {
        foreach ($fields as $field) {
            $field['section'] = $section;
            $map[$field['name']] = $field;
        }
    }
    return $map;
}

function chimGlobalSettingsNormalize($value, array $field)
{
    $type = strtolower((string)($field['type'] ?? 'string'));
    if ($type === 'boolean') {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
    if ($type === 'integer') {
        if (!is_numeric($value)) throw new InvalidArgumentException('Expected an integer.');
        $value = (int)$value;
    } elseif ($type === 'number') {
        if (!is_numeric($value)) throw new InvalidArgumentException('Expected a number.');
        $value = (float)$value;
    } elseif (strpos($type, 'foreign:') === 0) {
        if ($value === '' || $value === null) return '';
        if (!is_numeric($value) || (int)$value < 1) throw new InvalidArgumentException('Invalid connector.');
        $value = (int)$value;
    } else {
        $value = (string)$value;
    }
    if (isset($field['values']) && !in_array((string)$value, array_map('strval', $field['values']), true)) {
        throw new InvalidArgumentException('Value is not allowed.');
    }
    if (isset($field['min']) && $value < $field['min']) $value = $field['min'];
    if (isset($field['max']) && $value > $field['max']) $value = $field['max'];
    return $value;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) $body = $_POST;
        $settings = $body['settings'] ?? null;
        if (!is_array($settings)) chimGlobalSettingsRespond(['success' => false, 'error' => 'Settings payload is required.'], 400);

        $fields = chimGlobalSettingsFieldMap();
        $saved = [];
        foreach ($settings as $name => $value) {
            if (!isset($fields[$name])) {
                throw new InvalidArgumentException("Unknown setting: {$name}");
            }
            $normalized = chimGlobalSettingsNormalize($value, $fields[$name]);
            if (!chimSetGeneralSetting($name, $normalized)) {
                throw new RuntimeException("Could not save {$name}.");
            }
            $saved[] = $name;
        }
        if (isset($body['prompt_context_options'])) {
            $normalized = chimNormalizePromptContextOptions($body['prompt_context_options']);
            if (!chimSetGeneralSetting('PROMPT_CONTEXT_OPTIONS', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('Could not save prompt context selections.');
            }
            $saved[] = 'PROMPT_CONTEXT_OPTIONS';
        }
        chimGlobalSettingsRespond(['success' => true, 'saved' => $saved]);
    }

    $connectorRows = $GLOBALS['db']->fetchAll('SELECT id, COALESCE(NULLIF(label, \'\'), model, id::text) AS label FROM core_llm_connector ORDER BY label ASC, id ASC');
    $connectors = array_map(static fn($row) => ['value' => (int)$row['id'], 'label' => (string)$row['label']], (array)$connectorRows);
    $descriptionMap = chimGetManagedGeneralSettingDescriptions();
    $sections = [];
    foreach (chimPrismaGlobalSettingsSections() as $section => $fields) {
        $items = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $field['label'] = chimGlobalSettingsLabel($name);
            $field['description'] = (string)($descriptionMap[$name] ?? $field['description'] ?? $field['help'] ?? '');
            $field['value'] = chimReadLegacyGlobalValue($name, $field['default'] ?? '');
            if (($field['type'] ?? '') === 'boolean') {
                $field['value'] = filter_var($field['value'], FILTER_VALIDATE_BOOLEAN);
            }
            if (strpos((string)$field['type'], 'foreign:') === 0) $field['options'] = $connectors;
            $items[] = $field;
        }
        $sections[] = ['name' => $section, 'tab' => chimPrismaGlobalSettingsSectionTabs()[$section] ?? 'ai-memory', 'fields' => $items];
    }
    chimGlobalSettingsRespond(['success' => true, 'data' => [
        'tabs' => chimPrismaGlobalSettingsTabs(),
        'sections' => $sections,
        'prompt_context_catalog' => chimGetPromptContextOptionCatalog(),
        'prompt_context_options' => chimGetPromptContextOptions(),
    ]]);
} catch (Throwable $e) {
    chimGlobalSettingsRespond(['success' => false, 'error' => $e->getMessage()], $e instanceof InvalidArgumentException ? 400 : 500);
}
