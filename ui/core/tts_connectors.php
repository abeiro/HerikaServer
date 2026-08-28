<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
    'load_tts_connector' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$uiPos = strpos($scriptPath, '/ui/');
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : '';
if ($webRoot === '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');
$isEmbed = isset($_GET['embed']) && strval($_GET['embed']) === '1';

try {
    require_once($enginePath . "debug" . DIRECTORY_SEPARATOR . "db_updates.php");
} catch (Throwable $_e) {
}

$ttsConnector = new TTSConnector();

function h($value): string
{
    return htmlspecialchars(strval($value), ENT_QUOTES, 'UTF-8');
}

function ttsPageUrl(array $params = []): string
{
    global $isEmbed;
    $base = 'tts_connectors.php';
    if ($isEmbed && !isset($params['embed'])) {
        $params['embed'] = '1';
    }
    $query = http_build_query($params);
    return $query !== '' ? ($base . '?' . $query) : $base;
}

function ttsNoticeUrl(string $notice, array $params = []): string
{
    $params['notice'] = $notice;
    return ttsPageUrl($params);
}

function ttsVisibleDriverOptions(TTSConnector $ttsConnector): array
{
    return array_values(array_filter(
        $ttsConnector->getDriverOptions(),
        function ($driverOption) use ($ttsConnector) {
            return $ttsConnector->normalizeDriverValue($driverOption) !== 'none';
        }
    ));
}

function ttsPreferredNewDriver(TTSConnector $ttsConnector, array $driverOptions): string
{
    foreach ($driverOptions as $driverOption) {
        if ($ttsConnector->normalizeDriverValue($driverOption) === 'pockettts') {
            return 'pockettts';
        }
    }

    foreach ($driverOptions as $driverOption) {
        $candidate = $ttsConnector->normalizeDriverValue($driverOption);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return 'pockettts';
}

function ttsGroupedDriverOptions(TTSConnector $ttsConnector, array $driverOptions): array
{
    $recommendedOrder = ['pockettts', 'chatterbox', 'xtts-fastapi', 'inworld', 'cartesia', 'omnivoice'];
    $available = [];
    foreach ($driverOptions as $driverOption) {
        $normalized = $ttsConnector->normalizeDriverValue($driverOption);
        if ($normalized !== '') {
            $available[$normalized] = true;
        }
    }

    $recommended = [];
    foreach ($recommendedOrder as $driverValue) {
        if (isset($available[$driverValue])) {
            $recommended[] = $driverValue;
            unset($available[$driverValue]);
        }
    }

    $others = array_keys($available);
    return [
        'Recommended' => $recommended,
        'Others' => $others,
    ];
}

function ttsShouldRenderField(string $fieldName, $definition, TTSConnector $ttsConnector, string $driver): bool
{
    if ($fieldName === '_title' || !is_array($definition)) {
        return false;
    }
    if (in_array($fieldName, ['endpoint', 'url', 'URL', 'API_KEY'], true)) {
        return false;
    }
    if ($ttsConnector->isDriverVoiceMetadataField($driver, $fieldName)) {
        return false;
    }
    $normalizedDriver = $ttsConnector->normalizeDriverValue($driver);
    if (in_array($normalizedDriver, ['xtts-fastapi', 'chatterbox', 'pockettts'], true)
        && in_array($fieldName, ['language', 'voicelogic'], true)) {
        return false;
    }
    if ($normalizedDriver === 'omnivoice' && $fieldName === 'voicelogic') {
        return false;
    }
    return true;
}

function ttsParseMetadataFromPost(array $source, string $driver, array $existingMetadata, TTSConnector $ttsConnector): array
{
    $metadata = $existingMetadata;
    $sharedSchema = $ttsConnector->getConnectorMetadataFieldSchema();
    foreach ($sharedSchema as $fieldName => $definition) {
        $postKey = 'meta__shared__' . $fieldName;
        $type = $definition['type'] ?? 'string';
        if ($type !== 'boolean' && !array_key_exists($postKey, $source)) {
            continue;
        }

        if ($type === 'boolean') {
            $metadata[$fieldName] = isset($source[$postKey]) && strval($source[$postKey]) === 'true';
        } elseif ($type === 'integer') {
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

    $schema = $ttsConnector->getProviderFieldSchema($driver);
    foreach ($schema as $fieldName => $definition) {
        if (!ttsShouldRenderField($fieldName, $definition, $ttsConnector, $driver)) {
            continue;
        }

        $postKey = 'meta__' . $driver . '__' . $fieldName;
        $type = $definition['type'] ?? 'string';
        if ($type !== 'boolean' && !array_key_exists($postKey, $source)) {
            continue;
        }

        if ($type === 'boolean') {
            $metadata[$fieldName] = isset($source[$postKey]) && strval($source[$postKey]) === 'true';
        } elseif ($type === 'integer') {
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

    return $ttsConnector->applyForcedMetadataDefaults(
        $driver,
        $ttsConnector->stripVoiceMetadataForDriver($driver, $metadata)
    );
}

function ttsShouldShowUrlField(TTSConnector $ttsConnector, string $driver): bool
{
    return $ttsConnector->driverSupportsEditableUrl($driver);
}

function ttsFormatFieldLabel(string $fieldName): string
{
    $label = str_replace(['_', '-'], ' ', trim($fieldName));
    $label = preg_replace('/\s+/', ' ', $label ?? '');
    return ucwords(strtolower($label));
}

function ttsOmniVoiceLanguageLabel(string $languageId): string
{
    static $fallbackLabels = [
        'cs' => 'Czech',
        'en' => 'English',
        'es' => 'Spanish',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sk' => 'Slovak',
    ];

    $languageId = strtolower(trim($languageId));
    if ($languageId === '') {
        return '';
    }

    $profilePath = '/home/dwemer/omnivoice-tts/languages/' . $languageId . '.json';
    if (is_file($profilePath) && is_readable($profilePath)) {
        $profile = json_decode(strval(@file_get_contents($profilePath)), true);
        if (is_array($profile)) {
            $label = trim(strval($profile['display_name'] ?? $profile['omnivoice_language'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }
    }

    return $fallbackLabels[$languageId] ?? strtoupper($languageId);
}

function ttsOmniVoicePreparedLanguages(): array
{
    $profilesPath = '/home/dwemer/omnivoice-tts/languages';
    $voicesPath = '/home/dwemer/omnivoice-tts/voices';
    if (!is_dir($profilesPath) || !is_readable($profilesPath)) {
        return [];
    }

    $entries = @scandir($profilesPath);
    if (!is_array($entries)) {
        return [];
    }

    $options = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || substr($entry, -5) !== '.json') {
            continue;
        }
        $profilePath = $profilesPath . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($profilePath) || !is_readable($profilePath)) {
            continue;
        }
        $rawProfile = strval(@file_get_contents($profilePath));
        if ($rawProfile === '' || stripos($rawProfile, 'REPLACE THIS') !== false) {
            continue;
        }
        $profile = json_decode($rawProfile, true);
        if (!is_array($profile)) {
            continue;
        }
        $languageId = strtolower(trim(strval($profile['id'] ?? basename($entry, '.json'))));
        if ($languageId === '' || !preg_match('/^[a-z][a-z0-9-]*$/', $languageId)) {
            continue;
        }

        $voiceCount = 0;
        $totalVoiceFolders = 0;
        $languagePath = $voicesPath . DIRECTORY_SEPARATOR . $languageId;
        $voiceEntries = @scandir($languagePath);
        if (is_array($voiceEntries)) {
            foreach ($voiceEntries as $voiceEntry) {
                if ($voiceEntry === '.' || $voiceEntry === '..') {
                    continue;
                }
                $voicePath = $languagePath . DIRECTORY_SEPARATOR . $voiceEntry;
                if (is_dir($voicePath)) {
                    $totalVoiceFolders++;
                    if (is_file($voicePath . DIRECTORY_SEPARATOR . 'reference.wav')
                        && is_file($voicePath . DIRECTORY_SEPARATOR . 'reference.txt')) {
                        $voiceCount++;
                    }
                }
            }
        }

        $options[$languageId] = [
            'id' => $languageId,
            'label' => trim(strval($profile['display_name'] ?? $profile['omnivoice_language'] ?? '')) ?: ttsOmniVoiceLanguageLabel($languageId),
            'voice_count' => $voiceCount,
            'total_voice_folders' => $totalVoiceFolders,
        ];
    }

    uasort($options, function ($a, $b) {
        return strcasecmp(strval($a['label'] ?? ''), strval($b['label'] ?? ''));
    });

    return array_values($options);
}

function ttsApiBadgeHasConfiguredKey($value): bool
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

if (isset($_GET['export'])) {
    $exportId = intval($_GET['export']);
    $row = $ttsConnector->getById($exportId);
    if (!$row) {
        header('HTTP/1.1 404 Not Found');
        echo 'Not found';
        exit;
    }

    $columns = ['id', 'label', 'driver', 'metadata', 'api_badge_id', 'url', 'voice_field'];
    $filenameBase = trim(strval($row['label'] ?? ('tts_connector_' . $exportId)));
    if ($filenameBase === '') {
        $filenameBase = 'tts_connector_' . $exportId;
    }
    $filename = $filenameBase . '.csv';
    $asciiName = str_replace(["\r", "\n", '"'], '', $filename);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace(['\\', '"'], '', $asciiName) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));

    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    $values = [];
    foreach ($columns as $column) {
        $value = $row[$column] ?? '';
        if ($column === 'metadata' && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $values[] = $value;
    }
    fputcsv($out, $values);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $redirectUrl = ttsPageUrl();
    $importedIds = [];

    try {
        if (!isset($_FILES['import_file']) || !isset($_FILES['import_file']['tmp_name'])) {
            header('Location: ' . $redirectUrl);
            exit;
        }

        $files = $_FILES['import_file'];
        $fileCount = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;

        for ($fileIndex = 0; $fileIndex < $fileCount; $fileIndex++) {
            $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$fileIndex] : $files['tmp_name'];
            if (!is_uploaded_file($tmp)) {
                continue;
            }

            $fh = fopen($tmp, 'r');
            if (!$fh) {
                continue;
            }

            $header = false;
            while (($line = fgetcsv($fh)) !== false) {
                if (!empty(array_filter($line, function ($v) { return trim((string)$v) !== ''; }))) {
                    $header = $line;
                    break;
                }
            }
            if ($header === false) {
                fclose($fh);
                continue;
            }

            $columns = array_map(function ($value) {
                return strtolower(trim((string)$value));
            }, $header);

            $row = false;
            while (($line = fgetcsv($fh)) !== false) {
                if (!empty(array_filter($line, function ($v) { return trim((string)$v) !== ''; }))) {
                    $row = $line;
                    break;
                }
            }
            fclose($fh);
            if ($row === false) {
                continue;
            }

            $dataMap = [];
            for ($i = 0; $i < count($columns); $i++) {
                $key = $columns[$i] ?? '';
                if ($key === '') {
                    continue;
                }
                $dataMap[$key] = $row[$i] ?? '';
            }

            $driver = $ttsConnector->normalizeDriverValue($dataMap['driver'] ?? '');
            $visibleOptions = ttsVisibleDriverOptions($ttsConnector);
            $visibleMap = [];
            foreach ($visibleOptions as $visibleOption) {
                $visibleMap[$ttsConnector->normalizeDriverValue($visibleOption)] = true;
            }
            if ($driver === '' || !isset($visibleMap[$driver])) {
                $driver = ttsPreferredNewDriver($ttsConnector, $visibleOptions);
            }

            $metadataRaw = trim(strval($dataMap['metadata'] ?? '{}'));
            $metadata = json_decode($metadataRaw, true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $payload = [
                'driver' => $driver,
                'label' => $ttsConnector->uniqueLabel(trim(strval($dataMap['label'] ?? '')) !== '' ? trim(strval($dataMap['label'])) : 'Imported TTS Connector'),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'api_badge_id' => intval($dataMap['api_badge_id'] ?? 0) > 0 ? intval($dataMap['api_badge_id']) : null,
                'url' => trim(strval($dataMap['url'] ?? '')) !== '' ? trim(strval($dataMap['url'])) : null,
                'voice_field' => trim(strval($dataMap['voice_field'] ?? '')) !== '' ? trim(strval($dataMap['voice_field'])) : $ttsConnector->getVoiceFieldForDriver($driver),
            ];

            $newId = $ttsConnector->create($payload);
            if ($newId > 0) {
                $importedIds[] = $newId;
            }
        }

        if (!empty($importedIds)) {
            $redirectUrl = ttsPageUrl(['edit' => $importedIds[0]]);
        }
    } catch (Throwable $e) {
        error_log('[TTS CSV Import Error] ' . $e->getMessage());
        error_log('[TTS CSV Import Error] Stack trace: ' . $e->getTraceAsString());
        $redirectUrl = ttsNoticeUrl('Import failed: ' . $e->getMessage());
    }

    header('Location: ' . $redirectUrl);
    exit;
}

if (isset($_GET['create_blank'])) {
    $options = ttsVisibleDriverOptions($ttsConnector);
    $newDriver = ttsPreferredNewDriver($ttsConnector, $options);

    $newId = $ttsConnector->create([
        'driver' => $newDriver,
        'label' => $ttsConnector->uniqueLabel('New TTS Connector'),
        'metadata' => json_encode([], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'api_badge_id' => $ttsConnector->getDefaultApiBadgeIdForDriver($newDriver),
        'url' => $ttsConnector->getDefaultUrlForDriver($newDriver),
        'voice_field' => $ttsConnector->getVoiceFieldForDriver($newDriver),
    ]);

    header('Location: ' . ttsPageUrl(['edit' => $newId]));
    exit;
}

if (isset($_GET['clone'])) {
    $newId = $ttsConnector->clone(intval($_GET['clone']));
    header('Location: ' . ttsPageUrl($newId > 0 ? ['edit' => $newId] : []));
    exit;
}

if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $inUseProfiles = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM core_profiles WHERE tts_connector_id = {$deleteId}");
    $inUsePlayer = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM core_player WHERE id = 'tts_connector_id' AND value = '" . $GLOBALS["db"]->escape(strval($deleteId)) . "'");
    $totalUse = intval($inUseProfiles['c'] ?? 0) + intval($inUsePlayer['c'] ?? 0);
    if ($totalUse > 0) {
        header('Location: ' . ttsNoticeUrl('Cannot delete a connector that is still assigned.', ['edit' => $deleteId]));
        exit;
    }
    $ttsConnector->delete($deleteId);
    header('Location: ' . ttsPageUrl());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_connector'])) {
    $editId = intval($_POST['id'] ?? 0);
    $driver = $ttsConnector->normalizeDriverValue($_POST['driver'] ?? 'none');
    $visibleDriverOptions = ttsVisibleDriverOptions($ttsConnector);
    $visibleDriverMap = [];
    foreach ($visibleDriverOptions as $visibleDriverOption) {
        $visibleDriverMap[$ttsConnector->normalizeDriverValue($visibleDriverOption)] = true;
    }
    if ($driver === '' || !isset($visibleDriverMap[$driver])) {
        $driver = ttsPreferredNewDriver($ttsConnector, $visibleDriverOptions);
    }

    $existing = $editId > 0 ? $ttsConnector->getById($editId) : null;
    $existingDriver = $ttsConnector->normalizeDriverValue($existing['driver'] ?? '');
    $existingMetadata = ($existing && $existingDriver === $driver)
        ? $ttsConnector->decodeMetadata($existing['metadata'] ?? '{}')
        : [];
    $metadata = ttsParseMetadataFromPost($_POST, $driver, $existingMetadata, $ttsConnector);
    $apiBadgeId = $ttsConnector->driverUsesApiBadge($driver)
        ? $ttsConnector->getDefaultApiBadgeIdForDriver($driver)
        : 0;
    if ($ttsConnector->driverUsesApiBadge($driver)) {
        $postedApiBadgeId = intval($_POST['api_badge_id'] ?? 0);
        if ($postedApiBadgeId > 0) {
            $apiBadgeId = $postedApiBadgeId;
        }
    }
    $label = trim(strval($_POST['label'] ?? ''));
    if ($label === '') {
        $label = 'Default ' . $ttsConnector->getDisplayName($driver);
    }

    $payload = [
        'driver' => $driver,
        'label' => $ttsConnector->uniqueLabel($label, $editId),
        'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'api_badge_id' => $apiBadgeId > 0 ? $apiBadgeId : null,
        'url' => ttsShouldShowUrlField($ttsConnector, $driver)
            ? (trim(strval($_POST['url'] ?? '')) !== '' ? trim(strval($_POST['url'])) : null)
            : null,
        'voice_field' => $ttsConnector->getVoiceFieldForDriver($driver),
    ];

    if ($editId > 0) {
        $savedId = $ttsConnector->update($editId, $payload);
    } else {
        $savedId = $ttsConnector->create($payload);
    }

    header('Location: ' . ttsNoticeUrl('Connector saved.', ['edit' => $savedId]));
    exit;
}

$rows = $ttsConnector->readAll();
$usageRows = $GLOBALS["db"]->fetchAll("SELECT tts_connector_id, COUNT(*) AS c FROM core_profiles WHERE tts_connector_id IS NOT NULL GROUP BY tts_connector_id");
$usageMap = [];
foreach ($usageRows as $row) {
    $usageMap[strval($row['tts_connector_id'] ?? '')] = intval($row['c'] ?? 0);
}
$playerTtsValue = $GLOBALS["db"]->fetchOne("SELECT value FROM core_player WHERE id = 'tts_connector_id' LIMIT 1");
$playerConnectorId = trim(strval($playerTtsValue['value'] ?? ''));
if ($playerConnectorId !== '') {
    if (!isset($usageMap[$playerConnectorId])) {
        $usageMap[$playerConnectorId] = 0;
    }
    $usageMap[$playerConnectorId] += 1;
}

$apiRows = $GLOBALS["db"]->fetchAll("SELECT id, label, api_key FROM core_api_badge ORDER BY LOWER(label) ASC");
$editId = intval($_GET['edit'] ?? 0);
$editItem = $editId > 0 ? $ttsConnector->getById($editId) : false;
$currentDriver = $ttsConnector->normalizeDriverValue($editItem['driver'] ?? '');
$currentMetadata = $ttsConnector->decodeMetadata($editItem['metadata'] ?? '{}');
$driverOptions = ttsVisibleDriverOptions($ttsConnector);
$groupedDriverOptions = ttsGroupedDriverOptions($ttsConnector, $driverOptions);
if ($currentDriver === '' || $currentDriver === 'none') {
    $currentDriver = ttsPreferredNewDriver($ttsConnector, $driverOptions);
}

if (!$isEmbed) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "profile_loader.php");
}

$TITLE = "TTS Connectors";
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
if (!$isEmbed) {
    include(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/css/main.css'); ?>">
<style>
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main { padding: <?php echo $isEmbed ? '10px 5px 5px' : '30px 5px 5px'; ?>; }
.page-shell { max-width: 1450px; margin: 0 auto; }
/* Page header is the shared compact inline row (.chim-page-head in chim-theme.css). */
.notice { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; border: 1px solid rgba(242,124,17,.25); background: rgba(42,42,42,.9); color: #e7cfac; }
.layout { display: grid; grid-template-columns: minmax(280px, 340px) 1fr; gap: 18px; align-items: start; }
.left-col, .right-col { background: linear-gradient(180deg, rgba(42,42,42,.95), rgba(34,34,34,.98)); border: 1px solid #3a3a3a; border-radius: 10px; padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.15), inset 0 1px rgba(255,255,255,.03); }
.left-col { position: sticky; top: 90px; max-height: calc(100vh - 110px); overflow: hidden; }
.list-wrap { display: flex; flex-direction: column; gap: 10px; overflow: auto; max-height: calc(100vh - 190px); padding-right: 4px; }
.conn-card { border: 1px solid #3a3a3a; border-radius: 10px; background: linear-gradient(135deg, rgba(42,42,42,.95), rgba(34,34,34,.98)); padding: 12px; cursor: pointer; transition: all .2s ease; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.conn-card:hover { background: linear-gradient(135deg, rgba(58,58,58,.95), rgba(48,48,48,.98)); transform: translateY(-2px); border-color: #4a4a4a; box-shadow: 0 3px 8px rgba(0,0,0,.2); }
.conn-card.active { outline: 2px solid rgb(242,124,17); background: linear-gradient(135deg, rgba(52,42,32,.95), rgba(44,34,24,.98)); box-shadow: 0 4px 12px rgba(242,124,17,.3); }
.conn-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
.conn-name { color: #e9efff; font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.05em; }
.conn-badge { color: #9fb1c9; font-size: 11px; border: 1px solid #4a4a4a; border-radius: 999px; padding: 2px 8px; }
.conn-sub { color: #9fb1c9; font-size: 12px; margin-top: 4px; overflow-wrap: anywhere; }
.conn-usage { color: #9fb1c9; font-size: 12px; margin-top: 8px; }
.btn-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.btn-row .btn-save, .btn-row .btn-primary, .btn-row .btn-secondary, .btn-row .btn-danger { margin: 0; }
.placeholder { padding: 24px; border: 1px dashed #4a4a4a; border-radius: 10px; background: rgba(20,20,20,.65); color: #9fb1c9; }
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
.orm-note { padding: 6px 10px; font-size: 12px; color: #97a6ba; border-bottom: 1px dashed rgba(138,155,182,.25); background: #0c0f14; border-radius: 8px; margin-bottom: 12px; }
.meta-group { display: none; border-top: 1px solid rgba(242,124,17,.12); margin-top: 8px; padding-top: 16px; }
.meta-group.active { display: block; }
.meta-group h3 { font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.2em; font-weight: normal; }
.settings-empty-note { padding: 10px 12px; border: 1px dashed rgba(138,155,182,.25); border-radius: 8px; background: #0c0f14; color: #97a6ba; font-size: 12px; }
.inline-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
#tts_test_modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.66); z-index: 9999; }
#tts_test_modal .inner { width: min(1100px, 94vw); height: min(820px, 92vh); background: #0f1724; border: 1px solid #3a3a3a; border-radius: 10px; position: relative; overflow: hidden; }
#tts_test_modal iframe { width: 100%; height: 100%; border: 0; background: #111; }
#tts_test_close { position: absolute; top: 10px; right: 10px; z-index: 2; }
@media (max-width: 980px) {
    .layout { grid-template-columns: 1fr; }
    .left-col { position: relative; top: auto; max-height: none; }
    .list-wrap { max-height: 420px; }
    .editor-grid, .inline-two { grid-template-columns: 1fr; }
}
</style>

<main>
    <div class="page-shell">
        <div class="page-header chim-page-head">
            <h1 class="api-title chim-page-head-title">TTS Connectors</h1>
            <p class="page-subtitle chim-page-head-note">Text-to-Speech Setup Options.</p>
        </div>

        <?php if (!empty($_GET['notice'])): ?>
            <div class="notice"><?php echo h($_GET['notice']); ?></div>
        <?php endif; ?>

        <div class="layout">
            <div class="left-col">
                <div class="btn-row sidebar-action-grid">
                    <a class="btn-save" href="<?php echo h(ttsPageUrl(['create_blank' => 1])); ?>">New</a>
                    <form method="post" action="<?php echo h(ttsPageUrl()); ?>" enctype="multipart/form-data" id="tts_import_form">
                        <input type="hidden" name="import" value="1">
                        <input type="file" name="import_file[]" id="tts_import_file" accept=".csv" multiple style="display:none;">
                        <button type="button" class="btn-primary" id="tts_import_btn">Import</button>
                    </form>
                </div>
                <div class="list-wrap" id="tts_connector_list">
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $rowId = intval($row['id'] ?? 0);
                            $rowDriver = $ttsConnector->normalizeDriverValue($row['driver'] ?? 'none');
                            $rowActive = ($editItem && intval($editItem['id'] ?? 0) === $rowId) ? ' active' : '';
                            $rowUseCount = intval($usageMap[strval($rowId)] ?? 0);
                        ?>
                        <div class="conn-card<?php echo $rowActive; ?>" data-edit-id="<?php echo h($rowId); ?>">
                            <div class="conn-head">
                                <div class="conn-name"><?php echo h($row['label'] ?? ('Connector #' . $rowId)); ?></div>
                                <div class="conn-badge"><?php echo h($ttsConnector->getDisplayName($rowDriver)); ?></div>
                            </div>
                            <div class="conn-sub"><?php echo h($row['url'] ?? ''); ?></div>
                            <div class="conn-usage"><?php echo h($rowUseCount); ?> assignment<?php echo $rowUseCount === 1 ? '' : 's'; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="right-col">
                <?php if (!$editItem): ?>
                    <div class="placeholder">
                        Select a connector from the left to edit it. New installs will already have the currently selected legacy TTS provider migrated into this table.
                    </div>
                <?php else: ?>
                    <form method="post" action="<?php echo h(ttsPageUrl()); ?>" id="tts_connector_form">
                        <input type="hidden" name="id" value="<?php echo h($editItem['id']); ?>">

                        <div class="btn-row">
                            <button type="submit" class="btn-save" name="save_connector" value="1">Save</button>
                            <button type="button" class="btn-primary" id="btn_test_connector">Test</button>
                            <button type="submit" class="btn-primary" formmethod="get" formaction="<?php echo h(ttsPageUrl()); ?>" name="export" value="<?php echo h($editItem['id']); ?>">Export</button>
                            <a class="btn-primary" href="<?php echo h(ttsPageUrl(['clone' => $editItem['id']])); ?>">Clone</a>
                            <a class="btn-danger" href="<?php echo h(ttsPageUrl(['delete' => $editItem['id']])); ?>" onclick="return confirm('Delete this TTS connector?');">Delete</a>
                        </div>
                        <div class="orm-note">Please save any changes before testing to ensure the latest settings are used.</div>

                        <div class="editor-grid">
                            <div class="field-block">
                                <label for="label">Name</label>
                                <input type="text" id="label" name="label" value="<?php echo h($editItem['label'] ?? ''); ?>">
                                <div class="field-help">This label appears in profile and player connector pickers.</div>
                            </div>
                            <div class="field-block">
                                <label for="driver">Service</label>
                                <select id="driver" name="driver">
                                    <?php foreach ($groupedDriverOptions as $groupLabel => $groupDrivers): ?>
                                        <?php if (empty($groupDrivers)) { continue; } ?>
                                        <optgroup label="<?php echo h($groupLabel); ?>">
                                            <?php foreach ($groupDrivers as $driverValue): ?>
                                                <option value="<?php echo h($driverValue); ?>" <?php echo $currentDriver === $driverValue ? 'selected' : ''; ?>>
                                                    <?php echo h($ttsConnector->getDisplayName($driverValue)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-help">The provider driver this connector loads at runtime.</div>
                            </div>
                            <?php $showUrlField = ttsShouldShowUrlField($ttsConnector, $currentDriver); ?>
                            <div class="field-block" id="url_block" style="<?php echo $showUrlField ? '' : 'display:none;'; ?>">
                                <label for="url">URL</label>
                                <input type="url" id="url" name="url" value="<?php echo h($editItem['url'] ?? ''); ?>">
                                <div class="field-help">Used for providers that expose a local or remote HTTP endpoint.</div>
                            </div>
                            <div class="field-block" id="api_badge_block" style="<?php echo $ttsConnector->driverUsesApiBadge($currentDriver) ? '' : 'display:none;'; ?>">
                                <label for="api_badge_id">API Badge</label>
                                <?php
                                    $selectedApi = $editItem['api_badge_id'] ?? '';
                                    $withKey = [];
                                    $noKey = [];
                                    foreach ($apiRows as $apiRow) {
                                        if (ttsApiBadgeHasConfiguredKey($apiRow['api_key'] ?? '')) {
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
                                <div class="field-help">Cloud TTS providers require an API key.</div>
                            </div>
                        </div>

                        <?php $sharedMetadataSchema = $ttsConnector->getConnectorMetadataFieldSchema(); ?>
                        <div class="meta-group active">
                            <h3 style="margin:0 0 14px;color:#f3d6a8;">NPC Fallbacks</h3>
                            <div class="inline-two">
                                <?php foreach ($sharedMetadataSchema as $fieldName => $definition): ?>
                                    <?php
                                        $fieldType = $definition['type'] ?? 'string';
                                        $fieldValue = $currentMetadata[$fieldName] ?? '';
                                        $fieldKey = 'meta__shared__' . $fieldName;
                                    ?>
                                    <div class="field-block">
                                        <label for="<?php echo h($fieldKey); ?>"><?php echo h(ttsFormatFieldLabel($fieldName)); ?></label>
                                        <?php if ($fieldType === 'boolean'): ?>
                                            <input type="hidden" name="<?php echo h($fieldKey); ?>" value="false">
                                            <select id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>">
                                                <option value="true" <?php echo $fieldValue ? 'selected' : ''; ?>>Enabled</option>
                                                <option value="false" <?php echo !$fieldValue ? 'selected' : ''; ?>>Disabled</option>
                                            </select>
                                        <?php elseif ($fieldType === 'integer'): ?>
                                            <input type="number" step="1" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                        <?php elseif ($fieldType === 'number'): ?>
                                            <input type="number" step="0.01" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                        <?php elseif ($fieldType === 'longstring'): ?>
                                            <textarea id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>"><?php echo h($fieldValue); ?></textarea>
                                        <?php else: ?>
                                            <input type="text" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($definition['description'])): ?>
                                            <div class="field-help"><?php echo $definition['description']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php foreach ($driverOptions as $driverOption): ?>
                            <?php
                                $groupDriver = $ttsConnector->normalizeDriverValue($driverOption);
                                $groupSchema = $ttsConnector->getProviderFieldSchema($groupDriver);
                                $groupMetadata = $ttsConnector->applyForcedMetadataDefaults(
                                    $groupDriver,
                                    ($groupDriver === $currentDriver) ? $currentMetadata : []
                                );
                                $visibleFieldNames = [];
                                foreach ($groupSchema as $fieldName => $definition) {
                                    if (ttsShouldRenderField($fieldName, $definition, $ttsConnector, $groupDriver)) {
                                        $visibleFieldNames[] = $fieldName;
                                    }
                                }
                            ?>
                            <div class="meta-group<?php echo $groupDriver === $currentDriver ? ' active' : ''; ?>" data-driver-fields="<?php echo h($groupDriver); ?>">
                                <h3 style="margin:0 0 14px;color:#f3d6a8;"><?php echo h($ttsConnector->getProviderTitle($groupDriver)); ?> Settings</h3>
                                <?php if (empty($visibleFieldNames)): ?>
                                    <div class="settings-empty-note">This TTS provider does not have any connector-level settings to configure here.</div>
                                <?php else: ?>
                                    <div class="inline-two">
                                        <?php foreach ($groupSchema as $fieldName => $definition): ?>
                                            <?php if (!ttsShouldRenderField($fieldName, $definition, $ttsConnector, $groupDriver)) { continue; } ?>
                                            <?php
                                                $fieldType = $definition['type'] ?? 'string';
                                                $fieldValue = $groupMetadata[$fieldName] ?? '';
                                                $fieldKey = 'meta__' . $groupDriver . '__' . $fieldName;
                                            ?>
                                            <div class="field-block">
                                                <label for="<?php echo h($fieldKey); ?>"><?php echo h(ttsFormatFieldLabel($fieldName)); ?></label>
                                                <?php if ($groupDriver === 'omnivoice' && $fieldName === 'language'): ?>
                                                    <?php
                                                        $omniLanguages = ttsOmniVoicePreparedLanguages();
                                                        $currentOmniLanguage = strtolower(trim(strval($fieldValue)));
                                                        $preparedLanguageIds = array_map(function ($option) {
                                                            return strval($option['id'] ?? '');
                                                        }, $omniLanguages);
                                                        $currentLanguagePrepared = $currentOmniLanguage !== '' && in_array($currentOmniLanguage, $preparedLanguageIds, true);
                                                        $selectedOmniLanguage = $currentLanguagePrepared || empty($omniLanguages)
                                                            ? $currentOmniLanguage
                                                            : strval($omniLanguages[0]['id'] ?? '');
                                                    ?>
                                                    <?php if (!empty($omniLanguages)): ?>
                                                        <select id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>">
                                                            <?php foreach ($omniLanguages as $languageOption): ?>
                                                                <?php
                                                                    $languageId = strval($languageOption['id'] ?? '');
                                                                    $languageLabel = strval($languageOption['label'] ?? strtoupper($languageId));
                                                                    $optionLabel = $languageLabel . ' (' . $languageId . ')';
                                                                ?>
                                                                <option value="<?php echo h($languageId); ?>" <?php echo $selectedOmniLanguage === $languageId ? 'selected' : ''; ?>>
                                                                    <?php echo h($optionLabel); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if ($currentOmniLanguage !== '' && !$currentLanguagePrepared): ?>
                                                            <div class="field-help">Saved language <?php echo h($currentOmniLanguage); ?> is not available as an OmniVoice profile.</div>
                                                        <?php elseif (!empty($definition['description'])): ?>
                                                            <div class="field-help">Saving this connector will prepare the selected language automatically if needed.</div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <input type="text" id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" value="<?php echo h($fieldValue); ?>">
                                                        <div class="field-help">No OmniVoice language profiles were found in /home/dwemer/omnivoice-tts/languages.</div>
                                                    <?php endif; ?>
                                                <?php elseif ($fieldType === 'boolean'): ?>
                                                    <input type="hidden" name="<?php echo h($fieldKey); ?>" value="false">
                                                    <select id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>">
                                                        <option value="true" <?php echo $fieldValue ? 'selected' : ''; ?>>Enabled</option>
                                                        <option value="false" <?php echo !$fieldValue ? 'selected' : ''; ?>>Disabled</option>
                                                    </select>
                                                <?php elseif ($fieldType === 'integer'): ?>
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
                                                <?php if (!($groupDriver === 'omnivoice' && $fieldName === 'language') && !empty($definition['description'])): ?>
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

    <div id="tts_test_modal">
        <div class="inner">
            <button type="button" class="btn-secondary" id="tts_test_close">Close</button>
            <iframe id="tts_test_iframe" src="about:blank"></iframe>
        </div>
    </div>
</main>

<script>
(function(){
    const importBtn = document.getElementById('tts_import_btn');
    const importFile = document.getElementById('tts_import_file');
    const importForm = document.getElementById('tts_import_form');
    if (importBtn && importFile && importForm) {
        importBtn.addEventListener('click', function() {
            importFile.click();
        });
        importFile.addEventListener('change', function() {
            if (importFile.files && importFile.files.length > 0) {
                importForm.submit();
            }
        });
    }

    const editCards = document.querySelectorAll('.conn-card[data-edit-id]');
    editCards.forEach(function(card){
        card.addEventListener('click', function(){
            const id = card.getAttribute('data-edit-id');
            if (!id) return;
            window.location.href = <?php echo json_encode(ttsPageUrl()); ?>.replace(/(\?.*)?$/, '') + '?edit=' + encodeURIComponent(id) + <?php echo json_encode($isEmbed ? '&embed=1' : ''); ?>;
        });
    });

    const driverSelect = document.getElementById('driver');
    const apiBadgeBlock = document.getElementById('api_badge_block');
    const apiBadgeSelect = document.getElementById('api_badge_id');
    const apiKeyNotice = document.getElementById('api_key_notice');
    const urlBlock = document.getElementById('url_block');
    const apiDrivers = <?php echo json_encode(array_values(array_filter($driverOptions, function ($driver) use ($ttsConnector) { return $ttsConnector->driverUsesApiBadge($driver); })), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const urlDrivers = <?php echo json_encode(array_values(array_filter($driverOptions, function ($driver) use ($ttsConnector) { return $ttsConnector->driverSupportsEditableUrl($driver); })), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const defaultApiBadgeIds = <?php
        $defaultApiBadgeIds = [];
        foreach ($driverOptions as $driverOption) {
            $normalizedDriver = $ttsConnector->normalizeDriverValue($driverOption);
            $defaultApiBadgeIds[$normalizedDriver] = $ttsConnector->getDefaultApiBadgeIdForDriver($normalizedDriver);
        }
        echo json_encode($defaultApiBadgeIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>;
    const defaultUrls = <?php
        $defaultUrls = [];
        foreach ($driverOptions as $driverOption) {
            $normalizedDriver = $ttsConnector->normalizeDriverValue($driverOption);
            $defaultUrls[$normalizedDriver] = $ttsConnector->getDefaultUrlForDriver($normalizedDriver);
        }
        echo json_encode($defaultUrls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>;
    const urlInput = document.getElementById('url');
    let previousDriver = driverSelect ? String(driverSelect.value || '') : '';

    function syncDriverFields() {
        if (!driverSelect) return;
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
                if (supportsUrl) {
                    const currentValue = String(urlInput.value || '').trim();
                    const previousDefault = String(defaultUrls[previousDriver] || '').trim();
                    const nextDefault = String(defaultUrls[selected] || '').trim();
                    if ((currentValue === '' || currentValue === previousDefault) && nextDefault) {
                        urlInput.value = nextDefault;
                    }
                } else {
                    urlInput.value = '';
                }
            }
        }
        previousDriver = selected;
        updateApiBadgeNotice();
    }

    function updateApiBadgeNotice() {
        if (!apiBadgeSelect || !apiKeyNotice) return;
        const selectedOption = apiBadgeSelect.options[apiBadgeSelect.selectedIndex];
        const isEmpty = selectedOption ? selectedOption.getAttribute('data-empty') === '1' : true;
        if (!selectedOption || String(apiBadgeSelect.value || '') === '') {
            apiKeyNotice.className = 'api-key-notice warn';
            apiKeyNotice.textContent = 'No API key selected. Some services require a key.';
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

    if (driverSelect) {
        driverSelect.addEventListener('change', syncDriverFields);
        syncDriverFields();
    }
    if (apiBadgeSelect) {
        apiBadgeSelect.addEventListener('change', updateApiBadgeNotice);
        updateApiBadgeNotice();
    }

    const modal = document.getElementById('tts_test_modal');
    const iframe = document.getElementById('tts_test_iframe');
    const closeBtn = document.getElementById('tts_test_close');
    const testBtn = document.getElementById('btn_test_connector');
    if (testBtn && modal && iframe) {
        testBtn.addEventListener('click', function(){
            const idInput = document.querySelector('input[name="id"]');
            const connectorId = idInput ? idInput.value : '';
            if (!connectorId) {
                return;
            }
            iframe.src = <?php echo json_encode($webRoot . '/ui/tests/tts-test.php'); ?> + '?connector_id=' + encodeURIComponent(connectorId) + <?php echo json_encode($isEmbed ? '&embed=1' : ''); ?>;
            modal.style.display = 'flex';
        });
    }
    if (closeBtn && modal && iframe) {
        closeBtn.addEventListener('click', function(){
            modal.style.display = 'none';
            iframe.src = 'about:blank';
        });
        modal.addEventListener('click', function(event){
            if (event.target === modal) {
                modal.style.display = 'none';
                iframe.src = 'about:blank';
            }
        });
    }
})();
</script>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
