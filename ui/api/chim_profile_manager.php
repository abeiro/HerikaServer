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
chimRuntimeBootstrap(BASE_PATH . DIRECTORY_SEPARATOR, ['load_general_settings' => true]);
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core_profiles.class.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'prisma_settings_catalog.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'settings_presets.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf_loader.php';

$profiles = new CoreProfile();

function chimProfileManagerRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimProfileManagerJson($value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function chimProfileManagerBool($value): bool
{
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function chimProfileManagerLabel(string $name): string
{
    $custom = [
        'DYNAMIC_PROFILE_ENABLED' => 'Dynamic Profile', 'DYNAMIC_PROFILE_FIELDS' => 'Dynamic Profile Fields',
        'MIDDLE_TERM_MEMORY_ENABLED' => 'Middle Term Memory', 'SHORT_TERM_MEMORY_ENABLED' => 'Short Term Memory', 'SHORT_TERM_MEMORY_MAX' => 'Short Term Memory Max', 'AUTO_DIARY_ENABLED' => 'Auto Diary',
        'AUTO_DIARY_WAIT_ENABLED' => 'Auto Diary Wait', 'MATERIALIZE_DIARY_ENABLED' => 'Physical In-game Diary',
        'LATEST_DIARY_CONTEXT_ENABLED' => 'Include Latest Diary Entry', 'LLM_RANDOMIZER_ENABLED' => 'Random LLM Selection',
        'LLM_FALLBACK_ENABLED' => 'Fallback LLM', 'RECHAT_H' => 'Rechat Probability',
        'RECHAT_P' => 'Player Rechat Probability', 'RECHAT_ALLOW_ACTIONS' => 'Allow Rechat Actions',
        'CORE_LANG' => 'Language', 'LANG_LLM_XTTS' => 'LLM/TTS Language',
        'BORED_EVENT' => 'Bored Event',
        'CONTEXT_HISTORY' => 'Context History', 'CONTEXT_HISTORY_DIARY' => 'Diary Context History',
        'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 'Dynamic Profile Context History', 'MAX_WORDS_LIMIT' => 'Maximum Words',
        'QUEST_COMMENT' => 'Quest Commentary', 'QUEST_COMMENT_CHANCE' => 'Quest Commentary Chance',
        'COMBAT_BARK_COOLDOWN' => 'Combat Bark Cooldown', 'DIARY_PROMPT' => 'Diary Prompt',
        'DIARY_COOLDOWN' => 'Diary Cooldown', 'RPG_COMMENTS' => 'RPG Comments',
        'RPG_COMMENTS_CHANCE' => 'RPG Comments Chance',
    ];
    return $custom[$name] ?? ucwords(strtolower(str_replace('_', ' ', $name)));
}

function chimProfileManagerOptions(string $table): array
{
    $rows = $GLOBALS['db']->fetchAll("SELECT id, COALESCE(NULLIF(label, ''), id::text) AS label FROM {$table} ORDER BY label ASC, id ASC");
    return array_map(static fn($row) => ['value' => (int)$row['id'], 'label' => (string)$row['label']], (array)$rows);
}

function chimProfileManagerList(CoreProfile $profiles): array
{
    $rows = [];
    foreach ((array)$profiles->readAll() as $row) {
        $metadata = chimProfileManagerJson($row['metadata'] ?? '{}');
        $rows[] = [
            'id' => (int)$row['id'], 'label' => (string)$row['label'],
            'slot' => $row['slot'] === null ? null : (int)$row['slot'],
            'default_npc' => chimProfileManagerBool($row['default_npc'] ?? false),
            'npc_count' => (int)($GLOBALS['db']->fetchOne('SELECT COUNT(*) AS c FROM core_npc_master WHERE profile_id=' . (int)$row['id'])['c'] ?? 0),
            'dynamic_profile' => chimProfileManagerBool($metadata['DYNAMIC_PROFILE_ENABLED'] ?? false),
            'auto_diary' => chimProfileManagerBool($metadata['AUTO_DIARY_ENABLED'] ?? false),
        ];
    }
    return $rows;
}

function chimProfileManagerDetail(CoreProfile $profiles, int $id): array
{
    $row = $profiles->readOne($id);
    if (!$row) throw new RuntimeException('Profile not found.');
    $metadata = chimProfileManagerJson($row['metadata'] ?? '{}');
    $schema = conf_loader_load_schema();
    $sections = [];
    foreach (chimPrismaProfileMetadataCatalog() as $section => $fields) {
        $items = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $field['label'] = chimProfileManagerLabel($name);
            $field['value'] = $metadata[$name] ?? ($field['type'] === 'boolean' ? false : '');
            if (($field['type'] ?? '') === 'boolean') {
                $field['value'] = chimProfileManagerBool($field['value']);
            }
            if (!isset($field['description']) && isset($schema[$name]['description'])) {
                $field['description'] = (string)$schema[$name]['description'];
            }
            if (($field['type'] ?? '') === 'multiselect') {
                $values = $schema[$field['schema']]['values'] ?? [];
                $field['options'] = array_values(array_filter((array)$values, static fn($value) => strtolower((string)$value) !== 'keepmechecked'));
            }
            $items[] = $field;
        }
        $sections[] = ['name' => $section, 'fields' => $items];
    }
    $reservedOverrideKeys = [];
    foreach (chimPrismaProfileMetadataCatalog() as $fields) {
        foreach ($fields as $field) $reservedOverrideKeys[$field['name']] = true;
    }
    $overrideSections = [];
    foreach (chimGetOverrideableGeneralSettingsCatalog() as $name => $definition) {
        if (isset($reservedOverrideKeys[$name])) continue;
        $category = trim((string)($definition['category'] ?? 'Other')) ?: 'Other';
        $field = [
            'name' => $name,
            'label' => (string)($definition['ui_label'] ?? chimProfileManagerLabel($name)),
            'type' => (string)($definition['type'] ?? 'string'),
            'description' => (string)($definition['description'] ?? ''),
            'enabled' => array_key_exists($name, $metadata),
            'value' => $metadata[$name] ?? '',
            'global_value' => $definition['global_value'] ?? '',
        ];
        if ($field['type'] === 'boolean' && $field['enabled']) $field['value'] = chimProfileManagerBool($field['value']);
        if (!empty($definition['values'])) {
            $labels = (array)($definition['value_labels'] ?? []);
            $field['options'] = array_map(static fn($value) => [
                'value' => (string)$value,
                'label' => (string)($labels[(string)$value] ?? $value),
            ], (array)$definition['values']);
        }
        $overrideSections[$category][] = $field;
    }
    return [
        'core' => [
            'id' => (int)$row['id'], 'label' => (string)$row['label'],
            'slot' => $row['slot'] === null ? '' : (int)$row['slot'],
            'default_npc' => chimProfileManagerBool($row['default_npc'] ?? false),
            'prompt' => (string)($row['prompt'] ?? ''),
        ],
        'connectors' => array_intersect_key($row, array_flip([
            'llm_primary_id', 'llm_secondary_id', 'llm_tertiary_id', 'llm_quaternary_id',
            'tts_connector_id', 'diary_connector_id', 'llm_formatter_id', 'llm_fallback_id',
        ])),
        'connector_catalog' => chimPrismaProfileConnectorCatalog(),
        'metadata_sections' => $sections,
        'override_sections' => array_map(static fn($name, $fields) => ['name' => $name, 'fields' => $fields], array_keys($overrideSections), $overrideSections),
        'metadata' => $metadata,
    ];
}

function chimProfileManagerSave(CoreProfile $profiles, array $body): array
{
    $id = (int)($body['id'] ?? 0);
    $existing = $profiles->readOne($id);
    if (!$existing) throw new RuntimeException('Profile not found.');
    $core = is_array($body['core'] ?? null) ? $body['core'] : [];
    $connectors = is_array($body['connectors'] ?? null) ? $body['connectors'] : [];
    $metadata = array_key_exists('metadata', $body) ? $body['metadata'] : chimProfileManagerJson($existing['metadata'] ?? '{}');
    if (!is_array($metadata)) throw new InvalidArgumentException('Profile metadata must be an object.');

    $data = [
        'label' => trim((string)($core['label'] ?? $existing['label'])),
        'slot' => ($core['slot'] ?? '') === '' ? null : (int)$core['slot'],
        'default_npc' => 0,
        'prompt' => (string)($core['prompt'] ?? $existing['prompt']),
        'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
    if ($data['label'] === '') throw new InvalidArgumentException('Profile name is required.');
    foreach (['llm_primary_id', 'llm_secondary_id', 'llm_tertiary_id', 'llm_quaternary_id', 'tts_connector_id', 'diary_connector_id', 'llm_formatter_id', 'llm_fallback_id'] as $field) {
        if (array_key_exists($field, $connectors)) {
            $data[$field] = ($connectors[$field] === '' || $connectors[$field] === null)
                ? null
                : (int)$connectors[$field];
        }
    }
    if (!$profiles->update($id, $data)) throw new RuntimeException($profiles->getLastError() ?: 'Could not save profile.');
    if (chimProfileManagerBool($core['default_npc'] ?? false)) $profiles->promoteToDefaultNpc($id);
    return chimProfileManagerDetail($profiles, $id);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) $body = $_POST;
        $operation = (string)($body['operation'] ?? 'save');
        if ($operation === 'save') {
            chimProfileManagerRespond(['success' => true, 'data' => chimProfileManagerSave($profiles, $body)]);
        }
        if ($operation === 'create') {
            $id = $profiles->create(['label' => trim((string)($body['label'] ?? 'New Profile'))]);
            if (!$id) throw new RuntimeException($profiles->getLastError() ?: 'Could not create profile.');
            chimProfileManagerRespond(['success' => true, 'data' => chimProfileManagerDetail($profiles, (int)$id)]);
        }
        if ($operation === 'delete') {
            if (!$profiles->delete((int)($body['id'] ?? 0))) throw new RuntimeException($profiles->getLastError() ?: 'Could not delete profile.');
            chimProfileManagerRespond(['success' => true, 'data' => ['profiles' => chimProfileManagerList($profiles)]]);
        }
        if ($operation === 'copy_setting') {
            $id = (int)($body['id'] ?? 0);
            $key = trim((string)($body['key'] ?? ''));
            if (!in_array($key, chimPrismaProfileSyncableMetadataKeys(), true)) throw new InvalidArgumentException('This profile setting cannot be copied.');
            $source = $profiles->readOne($id);
            if (!$source) throw new RuntimeException('Profile not found.');
            $sourceMetadata = chimProfileManagerJson($source['metadata'] ?? '{}');
            foreach ((array)$profiles->readAll() as $target) {
                if ((int)$target['id'] === $id) continue;
                $targetMetadata = chimProfileManagerJson($target['metadata'] ?? '{}');
                if (array_key_exists($key, $sourceMetadata)) $targetMetadata[$key] = $sourceMetadata[$key];
                else unset($targetMetadata[$key]);
                if (!$profiles->update((int)$target['id'], ['metadata' => json_encode($targetMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)])) {
                    throw new RuntimeException('Could not copy setting to all profiles.');
                }
            }
            chimProfileManagerRespond(['success' => true, 'data' => ['copied' => $key]]);
        }
        if ($operation === 'apply_preset') {
            $id = (int)($body['id'] ?? 0);
            $presetId = trim((string)($body['preset_id'] ?? ''));
            $result = chimProfileSettingsPresetApply($id, $presetId);
            $result['detail'] = chimProfileManagerDetail($profiles, $id);
            chimProfileManagerRespond(['success' => true, 'data' => $result]);
        }
        if ($operation === 'save_preset_new' || $operation === 'overwrite_preset') {
            $id = (int)($body['id'] ?? 0);
            $metadata = array_key_exists('metadata', $body) ? $body['metadata'] : null;
            if ($metadata !== null && !is_array($metadata)) {
                throw new InvalidArgumentException('Profile metadata must be an object.');
            }
            $snapshot = chimProfileSettingsPresetCapture($id, $metadata);
            if ($operation === 'save_preset_new') {
                $preset = chimProfileSettingsPresetSaveNew((string)($body['name'] ?? ''), $snapshot);
            } else {
                $preset = chimProfileSettingsPresetOverwrite((string)($body['preset_id'] ?? ''), $snapshot);
            }
            chimProfileManagerRespond(['success' => true, 'data' => [
                'preset' => $preset,
                'profile_presets' => chimProfileSettingsPresetCatalog(),
            ]]);
        }
        if ($operation === 'export_preset') {
            chimProfileManagerRespond(['success' => true, 'data' => chimProfileSettingsPresetExport(
                trim((string)($body['preset_id'] ?? ''))
            )]);
        }
        if ($operation === 'import_preset') {
            $document = $body['document'] ?? null;
            if (!is_array($document)) {
                throw new InvalidArgumentException('Profile preset document is required.');
            }
            $encodedDocument = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encodedDocument === false || strlen($encodedDocument) > 262144) {
                throw new InvalidArgumentException('Profile preset files must be under 256 KB.');
            }
            $preset = chimProfileSettingsPresetImport($document);
            chimProfileManagerRespond(['success' => true, 'data' => [
                'preset' => $preset,
                'profile_presets' => chimProfileSettingsPresetCatalog(),
            ]]);
        }
        throw new InvalidArgumentException('Unknown operation.');
    }

    $id = (int)($_GET['id'] ?? 0);
    $data = [
        'profiles' => chimProfileManagerList($profiles),
        'connector_options' => [
            'llm' => chimProfileManagerOptions('core_llm_connector'),
            'tts' => chimProfileManagerOptions('core_tts_connector'),
        ],
        'profile_presets' => chimProfileSettingsPresetCatalog(),
    ];
    if ($id > 0) $data['detail'] = chimProfileManagerDetail($profiles, $id);
    chimProfileManagerRespond(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    chimProfileManagerRespond(['success' => false, 'error' => $e->getMessage()], $e instanceof InvalidArgumentException ? 400 : 500);
}
