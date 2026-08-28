<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "stt_connector.class.php");

chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
]);

try {
    require_once($enginePath . "debug" . DIRECTORY_SEPARATOR . "db_updates.php");
} catch (Throwable $_e) {
}

$connector = new STTConnector();
$isEmbed = isset($_GET['embed']) && strval($_GET['embed']) === '1';

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$uiPos = strpos($scriptPath, '/ui/');
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : '';
if ($webRoot === '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

function h($value): string
{
    return htmlspecialchars(strval($value), ENT_QUOTES, 'UTF-8');
}

function sttPageUrl(array $params = []): string
{
    global $isEmbed;
    $base = 'stt_connectors.php';
    if ($isEmbed && !isset($params['embed'])) {
        $params['embed'] = '1';
    }
    $query = http_build_query($params);
    return $query !== '' ? ($base . '?' . $query) : $base;
}

function sttVisibleDriverOptions(STTConnector $connector): array
{
    $options = $connector->getDriverOptions();
    if (empty($options)) {
        return ['deepgram', 'parakeet', 'whisper', 'localwhisper', 'gemini', 'azure', 'inworld', 'none'];
    }
    return array_values(array_unique($options));
}

function sttPreferredDriver(STTConnector $connector, array $options): string
{
    foreach ($options as $option) {
        $candidate = $connector->normalizeDriverValue($option);
        if ($candidate !== '' && $candidate !== 'none') {
            return $candidate;
        }
    }

    return 'deepgram';
}

function sttGroupedDriverOptions(STTConnector $connector, array $driverOptions): array
{
    $normalized = [];
    foreach ($driverOptions as $driverOption) {
        $driverValue = $connector->normalizeDriverValue($driverOption);
        if ($driverValue !== '') {
            $normalized[$driverValue] = true;
        }
    }

    $groups = [
        'Recommended' => ['deepgram', 'parakeet'],
        'Other Services' => ['whisper', 'localwhisper', 'gemini', 'azure', 'inworld'],
        'System' => ['none'],
    ];

    $output = [];
    foreach ($groups as $groupLabel => $groupDrivers) {
        $output[$groupLabel] = [];
        foreach ($groupDrivers as $groupDriver) {
            if (isset($normalized[$groupDriver])) {
                $output[$groupLabel][] = $groupDriver;
                unset($normalized[$groupDriver]);
            }
        }
    }

    if (!empty($normalized)) {
        $output['Other Services'] = array_merge($output['Other Services'], array_keys($normalized));
    }

    return $output;
}

function sttCreateDefaultConnector(STTConnector $connector): int
{
    $options = sttVisibleDriverOptions($connector);
    $defaultDriver = sttPreferredDriver($connector, $options);

    return $connector->create([
        'driver' => $defaultDriver,
        'label' => 'Global STT Connector',
        'metadata' => json_encode([], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'api_badge_id' => $connector->getDefaultApiBadgeIdForDriver($defaultDriver),
        'url' => $connector->getDefaultUrlForDriver($defaultDriver),
    ]);
}

function sttEnsureActiveConnectorId(STTConnector $connector): int
{
    $activeId = chimGetGeneralSettingInt('GLOBAL_STT_CONNECTOR_ID', 0);
    if ($activeId > 0) {
        $row = $connector->getById($activeId);
        if ($row) {
            return $activeId;
        }
    }

    $migrated = $connector->ensureLegacySelectionFromGlobals();
    if ($migrated && !empty($migrated['id'])) {
        $activeId = intval($migrated['id']);
        chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $activeId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
        return $activeId;
    }

    $rows = $connector->readAll();
    if (!empty($rows)) {
        $activeId = intval($rows[0]['id'] ?? 0);
        if ($activeId > 0) {
            chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $activeId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
        }
        return $activeId;
    }

    $createdId = sttCreateDefaultConnector($connector);
    if ($createdId > 0) {
        chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $createdId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
    }
    return $createdId;
}

function sttFieldLabel(string $fieldName): string
{
    $special = [
        'API_KEY' => 'API Key',
        'URL' => 'URL',
        'url' => 'URL',
        'MODEL_ID' => 'Model ID',
    ];
    if (isset($special[$fieldName])) {
        return $special[$fieldName];
    }

    return ucwords(str_replace(['_', '-'], ' ', strtolower(trim($fieldName))));
}

function sttShouldRenderField(string $fieldName, $definition): bool
{
    if ($fieldName === '_title' || !is_array($definition)) {
        return false;
    }
    if (in_array($fieldName, ['API_KEY', 'url', 'URL', 'endpoint'], true)) {
        return false;
    }
    return true;
}

function sttParseMetadataFromPost(array $source, string $driver, array $existingMetadata, STTConnector $connector): array
{
    $schema = $connector->getProviderFieldSchema($driver);
    $metadata = $existingMetadata;
    foreach ($schema as $fieldName => $definition) {
        if (!sttShouldRenderField($fieldName, $definition)) {
            continue;
        }

        $postKey = 'meta__' . $driver . '__' . $fieldName;
        $type = $definition['type'] ?? 'string';
        if ($type !== 'boolean' && !array_key_exists($postKey, $source)) {
            continue;
        }

        if ($type === 'boolean') {
            $metadata[$fieldName] = isset($source[$postKey]) && strval($source[$postKey]) === 'true';
        } elseif ($type === 'integer' || $type === 'int') {
            $raw = trim(strval($source[$postKey] ?? ''));
            $metadata[$fieldName] = ($raw === '') ? 0 : intval($raw);
        } elseif ($type === 'number') {
            $raw = trim(strval($source[$postKey] ?? ''));
            $metadata[$fieldName] = ($raw === '') ? 0 : floatval($raw);
        } elseif ($type === 'selectmultiple') {
            $metadata[$fieldName] = isset($source[$postKey]) && is_array($source[$postKey]) ? array_values($source[$postKey]) : [];
        } else {
            $metadata[$fieldName] = is_array($source[$postKey] ?? null) ? [] : trim(strval($source[$postKey] ?? ''));
        }
    }

    return $metadata;
}

function sttApiBadgeHasConfiguredKey($value): bool
{
    $raw = trim(strval($value));
    if ($raw === '') {
        return false;
    }
    if (preg_match('/^(?:\*+|null|none|n\/a)$/i', $raw)) {
        return false;
    }
    if (preg_match('/^[^A-Za-z0-9]+$/', $raw)) {
        return false;
    }
    return true;
}

$activeConnectorId = sttEnsureActiveConnectorId($connector);
$editingRow = $activeConnectorId > 0 ? $connector->getById($activeConnectorId) : [];
$notice = trim(strval($_GET['notice'] ?? ''));
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

$driverOptions = sttVisibleDriverOptions($connector);
$groupedDriverOptions = sttGroupedDriverOptions($connector, $driverOptions);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_connector'])) {
    $editId = intval($_POST['id'] ?? 0);
    $driver = $connector->normalizeDriverValue($_POST['driver'] ?? sttPreferredDriver($connector, $driverOptions));

    $allowedDrivers = [];
    foreach ($driverOptions as $driverOption) {
        $allowedDrivers[$connector->normalizeDriverValue($driverOption)] = true;
    }
    if (!isset($allowedDrivers[$driver])) {
        $driver = sttPreferredDriver($connector, $driverOptions);
    }

    $existing = $editId > 0 ? $connector->getById($editId) : null;
    $existingDriver = $connector->normalizeDriverValue($existing['driver'] ?? '');
    $existingMetadata = ($existing && $existingDriver === $driver)
        ? $connector->decodeMetadata($existing['metadata'] ?? '{}')
        : [];
    $metadata = sttParseMetadataFromPost($_POST, $driver, $existingMetadata, $connector);

    $label = trim(strval($_POST['label'] ?? ''));
    if ($label === '') {
        $label = ($driver === 'none') ? 'Disabled STT' : ('Global ' . $connector->getDisplayName($driver));
    }

    $apiBadgeId = null;
    if ($connector->driverUsesApiBadge($driver)) {
        $postedApiBadgeId = intval($_POST['api_badge_id'] ?? 0);
        $apiBadgeId = $postedApiBadgeId > 0 ? $postedApiBadgeId : $connector->getDefaultApiBadgeIdForDriver($driver);
        if ($apiBadgeId <= 0) {
            $apiBadgeId = null;
        }
    }

    $url = null;
    if ($connector->driverSupportsEditableUrl($driver)) {
        $url = trim(strval($_POST['url'] ?? ''));
        if ($url === '') {
            $url = $connector->getDefaultUrlForDriver($driver);
        }
    }

    $payload = [
        'driver' => $driver,
        'label' => $label,
        'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'api_badge_id' => $apiBadgeId,
        'url' => $url,
    ];

    if ($editId > 0) {
        $savedId = $connector->update($editId, $payload);
    } else {
        $savedId = $connector->create($payload);
    }

    chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $savedId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
    header('Location: ' . sttPageUrl(['saved' => '1']));
    exit;
}

$activeConnectorId = sttEnsureActiveConnectorId($connector);
$editingRow = $activeConnectorId > 0 ? $connector->getById($activeConnectorId) : [];
if (!$editingRow) {
    $createdId = sttCreateDefaultConnector($connector);
    if ($createdId > 0) {
        chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $createdId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
        $activeConnectorId = $createdId;
        $editingRow = $connector->getById($createdId);
    }
}

$currentDriver = $connector->normalizeDriverValue($editingRow['driver'] ?? sttPreferredDriver($connector, $driverOptions));
if ($currentDriver === '') {
    $currentDriver = sttPreferredDriver($connector, $driverOptions);
}
$currentMetadata = $connector->decodeMetadata($editingRow['metadata'] ?? '{}');
$apiRows = $GLOBALS["db"]->fetchAll("SELECT id, label, api_key FROM core_api_badge ORDER BY LOWER(label) ASC");

if (!$isEmbed) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");
}

$TITLE = "STT Connector";
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
if (!$isEmbed) {
    include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main { padding: <?php echo $isEmbed ? '20px 5px 5px' : '30px 5px 5px'; ?>; }
.page-shell { max-width: 1450px; margin: 0 auto; }
.page-header { background: linear-gradient(180deg, rgba(42,42,42,.95), rgba(34,34,34,.98)); padding: 20px; border-radius: 10px; border: 1px solid #3a3a3a; box-shadow: 0 2px 8px rgba(0,0,0,.15), inset 0 1px rgba(255,255,255,.03); text-align: center; margin-bottom: 30px; }
.page-header h1.api-title { margin-bottom: 8px; }
h1.api-title { margin: 0 0 20px 0; font-family: 'MagicCards', serif; word-spacing: 8px; font-size: 2.2em; color: rgb(242,124,17); text-shadow: 2px 2px 4px rgba(0,0,0,.5); text-align: center; }
.page-subtitle { color: #bbb; font-size: 1.1em; margin: 0; }
.notice { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; border: 1px solid rgba(242,124,17,.25); background: rgba(42,42,42,.9); color: #e7cfac; }
.layout { display: grid; grid-template-columns: minmax(280px, 340px) 1fr; gap: 18px; align-items: start; }
.left-col, .right-col { background: linear-gradient(180deg, rgba(42,42,42,.95), rgba(34,34,34,.98)); border: 1px solid #3a3a3a; border-radius: 10px; padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.15), inset 0 1px rgba(255,255,255,.03); }
.left-col { position: sticky; top: 90px; max-height: calc(100vh - 110px); overflow: hidden; }
.list-wrap { display: flex; flex-direction: column; gap: 10px; overflow: auto; max-height: calc(100vh - 280px); padding-right: 4px; }
.group-title { color: #f3d6a8; font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.05em; margin: 8px 0 0; }
.conn-card { border: 1px solid #3a3a3a; border-radius: 10px; background: linear-gradient(135deg, rgba(42,42,42,.95), rgba(34,34,34,.98)); padding: 12px; cursor: pointer; transition: all .2s ease; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.conn-card:hover { background: linear-gradient(135deg, rgba(58,58,58,.95), rgba(48,48,48,.98)); transform: translateY(-2px); border-color: #4a4a4a; box-shadow: 0 3px 8px rgba(0,0,0,.2); }
.conn-card.active { outline: 2px solid rgb(242,124,17); background: linear-gradient(135deg, rgba(52,42,32,.95), rgba(44,34,24,.98)); box-shadow: 0 4px 12px rgba(242,124,17,.3); }
.conn-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
.conn-name { color: #e9efff; font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.05em; }
.conn-badge { color: #9fb1c9; font-size: 11px; border: 1px solid #4a4a4a; border-radius: 999px; padding: 2px 8px; }
.conn-sub { color: #9fb1c9; font-size: 12px; margin-top: 4px; overflow-wrap: anywhere; }
.summary-note { padding: 10px 12px; border: 1px dashed rgba(138,155,182,.25); border-radius: 8px; background: #0c0f14; color: #97a6ba; font-size: 12px; margin-bottom: 12px; line-height: 1.5; }
.orm-note { padding: 6px 10px; font-size: 12px; color: #97a6ba; border-bottom: 1px dashed rgba(138,155,182,.25); background: #0c0f14; border-radius: 8px; margin-bottom: 12px; }
.editor-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field-block { margin-bottom: 12px; }
.field-block label { display: block; color: #fff; font-weight: 700; margin-bottom: 6px; }
.field-block input[type=text], .field-block input[type=url], .field-block input[type=number], .field-block select, .field-block textarea { width: 100%; box-sizing: border-box; background: rgba(26,26,26,.82); color: #eef3ff; border: 1px solid #3a3a3a; border-radius: 6px; padding: 10px 12px; }
.field-block input:focus, .field-block select:focus, .field-block textarea:focus { outline: none; border-color: rgba(242,124,17,.45); box-shadow: 0 0 0 3px rgba(242,124,17,.09); }
.field-block textarea { min-height: 90px; resize: vertical; }
.field-help { color: #8fa0bb; font-size: 12px; margin-top: 5px; line-height: 1.45; }
.api-key-notice { margin-top: 6px; font-size: 12px; }
.api-key-notice.warn { color: #ffb862; }
.api-key-notice.ok { color: #6dd19c; }
.meta-group { display: none; border-top: 1px solid rgba(242,124,17,.12); margin-top: 8px; padding-top: 16px; }
.meta-group.active { display: block; }
.meta-group h3 { font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.2em; font-weight: normal; color: #f3d6a8; margin: 0 0 14px; }
.settings-empty-note { padding: 10px 12px; border: 1px dashed rgba(138,155,182,.25); border-radius: 8px; background: #0c0f14; color: #97a6ba; font-size: 12px; }
.inline-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
#stt_test_modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.66); z-index: 9999; }
#stt_test_modal .inner { width: min(1100px, 94vw); height: min(820px, 92vh); background: #0f1724; border: 1px solid #3a3a3a; border-radius: 10px; position: relative; overflow: hidden; }
#stt_test_modal iframe { width: 100%; height: 100%; border: 0; background: #111; }
#stt_test_close { position: absolute; top: 10px; right: 10px; z-index: 2; }
@media (max-width: 980px) {
    .layout { grid-template-columns: 1fr; }
    .left-col { position: relative; top: auto; max-height: none; }
    .list-wrap { max-height: 420px; }
    .editor-grid, .inline-two { grid-template-columns: 1fr; }
}
</style>

<main>
    <div class="page-shell">
        <div class="page-header">
            <h1 class="api-title">STT Connector</h1>
            <p class="page-subtitle">Speech-to-Text Setup Options.</p>
        </div>

        <?php if ($saved): ?>
            <div class="notice">STT connector saved.</div>
        <?php elseif ($notice !== ''): ?>
            <div class="notice"><?php echo h($notice); ?></div>
        <?php endif; ?>

        <div class="layout">
            <div class="left-col">
                <div class="summary-note">
                    This page edits the single global STT connector. Switching services updates the active runtime provider instead of creating extra connector records.
                </div>
                <div class="list-wrap" id="stt_driver_list">
                    <?php foreach ($groupedDriverOptions as $groupLabel => $groupDrivers): ?>
                        <?php if (empty($groupDrivers)) { continue; } ?>
                        <div class="group-title"><?php echo h($groupLabel); ?></div>
                        <?php foreach ($groupDrivers as $driverValue): ?>
                            <div class="conn-card<?php echo $currentDriver === $driverValue ? ' active' : ''; ?>" data-driver-card="<?php echo h($driverValue); ?>">
                                <div class="conn-head">
                                    <div class="conn-name"><?php echo h($connector->getDisplayName($driverValue)); ?></div>
                                    <div class="conn-badge"><?php echo h($connector->getProviderKeyFromDriver($driverValue) ?: 'SYSTEM'); ?></div>
                                </div>
                                <div class="conn-sub"><?php echo h($connector->getProviderTitle($driverValue)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="right-col">
                <?php if (!$editingRow): ?>
                    <div class="settings-empty-note">No STT connector is configured yet.</div>
                <?php else: ?>
                    <form method="post" action="<?php echo h(sttPageUrl()); ?>" id="stt_connector_form">
                        <input type="hidden" name="id" value="<?php echo h($editingRow['id'] ?? ''); ?>">
                        <input type="hidden" name="save_connector" value="1">

                        <div class="btn-row">
                            <button type="submit" class="btn-save">Save</button>
                            <button type="button" class="btn-primary" id="btn_test_connector_inline">Test</button>
                            <button type="button" class="btn-secondary" id="btn_google_free_stt_inline">Google Free STT</button>
                        </div>
                        <div class="orm-note">Testing saves the current connector first so the modal uses the latest settings.</div>

                        <div class="editor-grid">
                            <div class="field-block">
                                <label for="label">Name</label>
                                <input type="text" id="label" name="label" value="<?php echo h($editingRow['label'] ?? ''); ?>">
                                <div class="field-help">This label is kept for migration and internal reference, even though only one STT connector is used globally.</div>
                            </div>
                            <div class="field-block">
                                <label for="driver">Service</label>
                                <select id="driver" name="driver">
                                    <?php foreach ($groupedDriverOptions as $groupLabel => $groupDrivers): ?>
                                        <?php if (empty($groupDrivers)) { continue; } ?>
                                        <optgroup label="<?php echo h($groupLabel); ?>">
                                            <?php foreach ($groupDrivers as $driverValue): ?>
                                                <option value="<?php echo h($driverValue); ?>" <?php echo $currentDriver === $driverValue ? 'selected' : ''; ?>>
                                                    <?php echo h($connector->getDisplayName($driverValue)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-help">Choose the speech-to-text backend HerikaServer should load globally.</div>
                            </div>

                            <div class="field-block" id="url_block" style="<?php echo $connector->driverSupportsEditableUrl($currentDriver) ? '' : 'display:none;'; ?>">
                                <label for="url">URL</label>
                                <input type="url" id="url" name="url" value="<?php echo h($editingRow['url'] ?? $connector->getDefaultUrlForDriver($currentDriver)); ?>">
                                <div class="field-help">Used for local or remote STT endpoints such as Local Whisper.</div>
                            </div>

                            <div class="field-block" id="api_badge_block" style="<?php echo $connector->driverUsesApiBadge($currentDriver) ? '' : 'display:none;'; ?>">
                                <label for="api_badge_id">API Badge</label>
                                <?php
                                $selectedApi = $editingRow['api_badge_id'] ?? '';
                                $withKey = [];
                                $noKey = [];
                                foreach ($apiRows as $apiRow) {
                                    if (sttApiBadgeHasConfiguredKey($apiRow['api_key'] ?? '')) {
                                        $withKey[] = $apiRow;
                                    } else {
                                        $noKey[] = $apiRow;
                                    }
                                }
                                ?>
                                <select id="api_badge_id" name="api_badge_id">
                                    <option value="">-- None --</option>
                                    <?php foreach ($withKey as $apiRow): ?>
                                        <?php
                                        $apiRowId = intval($apiRow['id'] ?? 0);
                                        $selected = strval($selectedApi) === strval($apiRowId) ? 'selected' : '';
                                        $labelText = '🟢 ' . strval($apiRow['label'] ?? ('Key #' . $apiRowId));
                                        ?>
                                        <option value="<?php echo h($apiRowId); ?>" data-empty="0" <?php echo $selected; ?>>
                                            <?php echo h($labelText); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (!empty($noKey)): ?>
                                        <option value="" disabled>— Missing Key —</option>
                                        <?php foreach ($noKey as $apiRow): ?>
                                            <?php
                                            $apiRowId = intval($apiRow['id'] ?? 0);
                                            $selected = strval($selectedApi) === strval($apiRowId) ? 'selected' : '';
                                            $labelText = '🔴 ' . strval($apiRow['label'] ?? ('Key #' . $apiRowId)) . ' — No key';
                                            ?>
                                            <option value="<?php echo h($apiRowId); ?>" data-empty="1" <?php echo $selected; ?>>
                                                <?php echo h($labelText); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div id="api_key_notice" class="api-key-notice"></div>
                                <div class="field-help">Cloud STT services require an API key from the API Keys page.</div>
                            </div>
                        </div>

                        <?php foreach ($driverOptions as $driverOption): ?>
                            <?php
                            $groupDriver = $connector->normalizeDriverValue($driverOption);
                            $groupSchema = $connector->getProviderFieldSchema($groupDriver);
                            $visibleFieldNames = [];
                            foreach ($groupSchema as $fieldName => $definition) {
                                if (sttShouldRenderField($fieldName, $definition)) {
                                    $visibleFieldNames[] = $fieldName;
                                }
                            }
                            ?>
                            <div class="meta-group<?php echo $groupDriver === $currentDriver ? ' active' : ''; ?>" data-driver-fields="<?php echo h($groupDriver); ?>">
                                <h3><?php echo h($connector->getProviderTitle($groupDriver)); ?> Settings</h3>
                                <?php if (empty($visibleFieldNames)): ?>
                                    <div class="settings-empty-note">This STT provider does not have connector-level settings to configure here.</div>
                                <?php else: ?>
                                    <div class="inline-two">
                                        <?php foreach ($groupSchema as $fieldName => $definition): ?>
                                            <?php if (!sttShouldRenderField($fieldName, $definition)) { continue; } ?>
                                            <?php
                                            $fieldType = $definition['type'] ?? 'string';
                                            $fieldValue = ($groupDriver === $currentDriver)
                                                ? ($currentMetadata[$fieldName] ?? ($definition['default'] ?? ''))
                                                : ($definition['default'] ?? '');
                                            $fieldKey = 'meta__' . $groupDriver . '__' . $fieldName;
                                            ?>
                                            <div class="field-block">
                                                <label for="<?php echo h($fieldKey); ?>"><?php echo h(sttFieldLabel($fieldName)); ?></label>
                                                <?php if ($fieldType === 'boolean'): ?>
                                                    <input type="hidden" name="<?php echo h($fieldKey); ?>" value="false">
                                                    <select id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>">
                                                        <option value="true" <?php echo $fieldValue ? 'selected' : ''; ?>>Enabled</option>
                                                        <option value="false" <?php echo !$fieldValue ? 'selected' : ''; ?>>Disabled</option>
                                                    </select>
                                                <?php elseif ($fieldType === 'integer' || $fieldType === 'int'): ?>
                                                    <input type="number" step="1" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                                <?php elseif ($fieldType === 'number'): ?>
                                                    <input type="number" step="0.01" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                                <?php elseif ($fieldType === 'longstring'): ?>
                                                    <textarea id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>"><?php echo h($fieldValue); ?></textarea>
                                                <?php elseif ($fieldType === 'select'): ?>
                                                    <select id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>">
                                                        <?php foreach (($definition['values'] ?? []) as $valueOption): ?>
                                                            <option value="<?php echo h($valueOption); ?>" <?php echo strval($fieldValue) === strval($valueOption) ? 'selected' : ''; ?>>
                                                                <?php echo h($valueOption); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php elseif ($fieldType === 'selectmultiple'): ?>
                                                    <select id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>[]" multiple>
                                                        <?php $selectedValues = is_array($fieldValue) ? $fieldValue : []; ?>
                                                        <?php foreach (($definition['values'] ?? []) as $valueOption): ?>
                                                            <option value="<?php echo h($valueOption); ?>" <?php echo in_array($valueOption, $selectedValues, true) ? 'selected' : ''; ?>>
                                                                <?php echo h($valueOption); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="text" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                                <?php endif; ?>
                                                <?php if (!empty($definition['description'])): ?>
                                                    <div class="field-help"><?php echo $definition['description']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="stt_test_modal">
        <div class="inner">
            <button type="button" class="btn-secondary" id="stt_test_close">Close</button>
            <iframe id="stt_test_iframe" src="about:blank"></iframe>
        </div>
    </div>
</main>

<script>
(function(){
    const form = document.getElementById('stt_connector_form');
    const driverSelect = document.getElementById('driver');
    const driverCards = document.querySelectorAll('[data-driver-card]');
    const apiBadgeBlock = document.getElementById('api_badge_block');
    const apiBadgeSelect = document.getElementById('api_badge_id');
    const apiKeyNotice = document.getElementById('api_key_notice');
    const urlBlock = document.getElementById('url_block');
    const urlInput = document.getElementById('url');
    const modal = document.getElementById('stt_test_modal');
    const iframe = document.getElementById('stt_test_iframe');
    const closeBtn = document.getElementById('stt_test_close');
    const testButtons = [document.getElementById('btn_test_connector_inline')].filter(Boolean);
    const googleButtons = [document.getElementById('btn_google_free_stt_inline')].filter(Boolean);

    const apiDrivers = <?php echo json_encode(array_values(array_filter($driverOptions, function ($driverOption) use ($connector) { return $connector->driverUsesApiBadge($driverOption); })), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const urlDrivers = <?php echo json_encode(array_values(array_filter($driverOptions, function ($driverOption) use ($connector) { return $connector->driverSupportsEditableUrl($driverOption); })), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const defaultApiBadgeIds = <?php
        $defaultApiBadgeIds = [];
        foreach ($driverOptions as $driverOption) {
            $normalizedDriver = $connector->normalizeDriverValue($driverOption);
            $defaultApiBadgeIds[$normalizedDriver] = $connector->getDefaultApiBadgeIdForDriver($normalizedDriver);
        }
        echo json_encode($defaultApiBadgeIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>;
    const defaultUrls = <?php
        $defaultUrls = [];
        foreach ($driverOptions as $driverOption) {
            $normalizedDriver = $connector->normalizeDriverValue($driverOption);
            $defaultUrls[$normalizedDriver] = $connector->getDefaultUrlForDriver($normalizedDriver);
        }
        echo json_encode($defaultUrls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>;

    let previousDriver = driverSelect ? String(driverSelect.value || '') : '';

    function refreshDriverCards(selected) {
        driverCards.forEach(function(card){
            card.classList.toggle('active', card.getAttribute('data-driver-card') === selected);
        });
    }

    function updateApiBadgeNotice() {
        if (!apiBadgeSelect || !apiKeyNotice || !apiBadgeBlock) {
            return;
        }
        if (apiBadgeBlock.style.display === 'none') {
            apiKeyNotice.textContent = '';
            apiKeyNotice.className = 'api-key-notice';
            return;
        }
        const selectedOption = apiBadgeSelect.options[apiBadgeSelect.selectedIndex];
        const isEmpty = selectedOption ? selectedOption.getAttribute('data-empty') === '1' : true;
        if (!selectedOption || String(apiBadgeSelect.value || '') === '') {
            apiKeyNotice.className = 'api-key-notice warn';
            apiKeyNotice.textContent = 'No API key selected. Some STT services require one.';
            return;
        }
        if (isEmpty) {
            apiKeyNotice.className = 'api-key-notice warn';
            apiKeyNotice.textContent = 'Selected API badge does not have a configured key yet.';
            return;
        }
        apiKeyNotice.className = 'api-key-notice ok';
        apiKeyNotice.textContent = 'Selected API badge is configured.';
    }

    function syncDriverFields() {
        if (!driverSelect) {
            return;
        }
        const selected = String(driverSelect.value || '');
        document.querySelectorAll('[data-driver-fields]').forEach(function(group){
            group.classList.toggle('active', group.getAttribute('data-driver-fields') === selected);
        });

        if (apiBadgeBlock) {
            const usesApiBadge = apiDrivers.indexOf(selected) >= 0;
            apiBadgeBlock.style.display = usesApiBadge ? '' : 'none';
            if (apiBadgeSelect) {
                const currentValue = String(apiBadgeSelect.value || '').trim();
                const previousDefault = String(defaultApiBadgeIds[previousDriver] || '').trim();
                const nextDefault = String(defaultApiBadgeIds[selected] || '').trim();
                if (usesApiBadge) {
                    if (currentValue === '' || currentValue === previousDefault) {
                        apiBadgeSelect.value = nextDefault;
                    }
                } else if (currentValue === previousDefault) {
                    apiBadgeSelect.value = '';
                }
            }
        }

        if (urlBlock) {
            const supportsUrl = urlDrivers.indexOf(selected) >= 0;
            urlBlock.style.display = supportsUrl ? '' : 'none';
            if (urlInput) {
                const currentValue = String(urlInput.value || '').trim();
                const previousDefault = String(defaultUrls[previousDriver] || '').trim();
                const nextDefault = String(defaultUrls[selected] || '').trim();
                if (supportsUrl) {
                    if ((currentValue === '' || currentValue === previousDefault) && nextDefault) {
                        urlInput.value = nextDefault;
                    }
                } else if (currentValue === previousDefault) {
                    urlInput.value = '';
                }
            }
        }

        previousDriver = selected;
        refreshDriverCards(selected);
        updateApiBadgeNotice();
    }

    async function saveBeforeTest() {
        if (!form) {
            return true;
        }
        const fd = new FormData(form);
        fd.set('save_connector', '1');
        try {
            await fetch(form.getAttribute('action') || 'stt_connectors.php', { method: 'POST', body: fd });
            return true;
        } catch (_error) {
            return false;
        }
    }

    function openTestModal() {
        if (!modal || !iframe) {
            return;
        }
        iframe.src = <?php echo json_encode($webRoot . '/ui/tests/stt-test.php'); ?> + '?cb=' + Date.now() + <?php echo json_encode($isEmbed ? '&embed=1' : ''); ?>;
        modal.style.display = 'flex';
    }

    function closeModal() {
        if (!modal || !iframe) {
            return;
        }
        modal.style.display = 'none';
        iframe.src = 'about:blank';
    }

    if (driverSelect) {
        driverSelect.addEventListener('change', syncDriverFields);
        syncDriverFields();
    }

    driverCards.forEach(function(card){
        card.addEventListener('click', function(){
            if (!driverSelect) {
                return;
            }
            const value = card.getAttribute('data-driver-card') || '';
            driverSelect.value = value;
            syncDriverFields();
        });
    });

    if (apiBadgeSelect) {
        apiBadgeSelect.addEventListener('change', updateApiBadgeNotice);
        updateApiBadgeNotice();
    }

    testButtons.forEach(function(button){
        button.addEventListener('click', async function(){
            const saved = await saveBeforeTest();
            if (!saved) {
                return;
            }
            openTestModal();
        });
    });

    googleButtons.forEach(function(button){
        button.addEventListener('click', function(){
            if (!modal || !iframe) {
                return;
            }
            iframe.src = <?php echo json_encode($webRoot . '/ui/addons/pmstt/index.html'); ?> + '?cb=' + Date.now();
            modal.style.display = 'flex';
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', function(event){
            if (event.target === modal) {
                closeModal();
            }
        });
    }
})();
</script>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
