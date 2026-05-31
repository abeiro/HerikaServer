<?php

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "online_translation.php");
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

$GLOBALS["db"] = $GLOBALS["db"] ?? new sql();
$ttsConnector = new TTSConnector();

if (function_exists('requireFilesRecursively')) {
    requireFilesRecursively($enginePath . "ext" . DIRECTORY_SEPARATOR, "globals.php");
}
require_once($enginePath . "prompt.includes.php");

function h($value): string
{
    return htmlspecialchars(strval($value), ENT_QUOTES, 'UTF-8');
}

$connectorId = intval($_GET['connector_id'] ?? $_POST['connector_id'] ?? 0);
$connector = $connectorId > 0 ? $ttsConnector->getById($connectorId) : null;
$connectorDriver = $ttsConnector->normalizeDriverValue($connector['driver'] ?? 'none');
$connectorMetadata = $ttsConnector->decodeMetadata($connector['metadata'] ?? '{}');
if (isset($connectorMetadata['API_KEY']) && trim(strval($connectorMetadata['API_KEY'])) !== '') {
    $connectorMetadata['API_KEY'] = '***redacted***';
}
$testStringDefault = "In Skyrim's land of snow and ice, where dragons soar and snowstorms bind the roads, a steady voice can still cut through the cold.";
$testString = trim(strval($_POST['customstring'] ?? $_GET['customstring'] ?? $testStringDefault));
$voiceId = trim(strval($_POST['voiceid'] ?? $_GET['voiceid'] ?? $_POST['voice_override'] ?? $_GET['voice_override'] ?? 'TheNarrator'));
$audioUrl = '';
$debugData = [];
$errorText = '';
$requestPreview = [];

if (!$isEmbed) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "profile_loader.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connector) {
    $originalTtsFunction = $GLOBALS["TTSFUNCTION"] ?? '';
    $originalName = $GLOBALS["HERIKA_NAME"] ?? '';
    $hadVoiceOverride = array_key_exists("PATCH_OVERRIDE_VOICE", $GLOBALS);
    $oldVoiceOverride = $GLOBALS["PATCH_OVERRIDE_VOICE"] ?? null;
    $hadVoiceIdGlobal = array_key_exists("PATCH_OVERRIDE_VOICE_ID", $GLOBALS);
    $hadLanguageGlobal = array_key_exists("PATCH_OVERRIDE_TTS_LANGUAGE", $GLOBALS);
    $oldVoiceIdGlobal = $GLOBALS["PATCH_OVERRIDE_VOICE_ID"] ?? null;
    $oldLanguageGlobal = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] ?? null;

    try {
        $ttsConnector->setOldGlobals($connector);
        $GLOBALS["HERIKA_NAME"] = "The Narrator";
        $GLOBALS["AVOID_TTS_CACHE"] = true;
        $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
        $GLOBALS["HERIKA_ANIMATIONS"] = false;
        $GLOBALS["SCRIPTLINE_LISTENER"] = '';
        $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
        $GLOBALS["DEBUG_DATA"] = [];
        $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
        if (!isset($GLOBALS["FEATURES"]["MISC"])) {
            $GLOBALS["FEATURES"]["MISC"] = [];
        }
        if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) {
            $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
        }
        $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;

        if ($voiceId !== '') {
            $GLOBALS["PATCH_OVERRIDE_VOICE"] = $voiceId;
        } else {
            unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
        }
        unset($GLOBALS["PATCH_OVERRIDE_VOICE_ID"]);
        unset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);

        Translation::translate($testString);
        Translation::$sentences = [Translation::$response];
        $cleanString = Translation::$response ?: $testString;

        returnLines([$cleanString], false);
        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename(strval($GLOBALS["TRACK"]["FILES_GENERATED"][0])) : '';
        if ($file !== '') {
            $audioUrl = $webRoot . '/soundcache/' . $file . '?ts=' . time();
        } else {
            $errorText = 'No audio was produced. Check the connector settings, API badge, endpoint, and provider logs.';
        }

        $debugData = $GLOBALS["DEBUG_DATA"] ?? [];
        $requestPreview = [
            'connector_id' => $connectorId,
            'connector_label' => strval($connector['label'] ?? ''),
            'driver' => $connectorDriver,
            'url' => strval($connector['url'] ?? ''),
            'voiceid' => $voiceId,
            'metadata' => $connectorMetadata,
        ];
        Translation::reset();
    } catch (Throwable $e) {
        $errorText = $e->getMessage();
    } finally {
        if ($hadVoiceOverride) {
            $GLOBALS["PATCH_OVERRIDE_VOICE"] = $oldVoiceOverride;
        } else {
            unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
        }
        if ($hadVoiceIdGlobal) {
            $GLOBALS["PATCH_OVERRIDE_VOICE_ID"] = $oldVoiceIdGlobal;
        } else {
            unset($GLOBALS["PATCH_OVERRIDE_VOICE_ID"]);
        }
        if ($hadLanguageGlobal) {
            $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] = $oldLanguageGlobal;
        } else {
            unset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
        }
        $GLOBALS["TTSFUNCTION"] = $originalTtsFunction;
        $GLOBALS["HERIKA_NAME"] = $originalName;
        unset($GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"]);
        unset($GLOBALS["SCRIPTLINE_ANIMATION_SENT"]);
    }
}

$TITLE = "TTS Test";
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
if (!$isEmbed) {
    include(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding: <?php echo $isEmbed ? '20px' : '80px'; ?> 10px 30px; }
.shell { max-width: 1100px; margin: 0 auto; }
.card { background: linear-gradient(180deg, rgba(42,42,42,.95), rgba(34,34,34,.98)); border: 1px solid #3a3a3a; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.card h1, .card h2 { margin-top: 0; color: rgb(242,124,17); }
.field { margin-bottom: 12px; }
.field label { display: block; margin-bottom: 6px; color: rgb(242,124,17); font-weight: 600; }
.field input[type=text], .field input[type=number], .field textarea { width: 100%; box-sizing: border-box; background: rgba(26,26,26,.82); color: #eef3ff; border: 1px solid #3a3a3a; border-radius: 6px; padding: 10px 12px; }
.field textarea { min-height: 120px; resize: vertical; }
.field-help { color: #8fa0bb; font-size: 12px; margin-top: 4px; }
.btn-save { padding: 10px 14px; color: #fff; border-radius: 8px; border: 1px solid rgba(72,187,120,.35); background: #176529; cursor: pointer; }
.error { color: #ff9898; }
.ok { color: #9be29b; }
pre { white-space: pre-wrap; word-wrap: break-word; background: rgba(18,18,18,.9); border: 1px solid #2f2f2f; border-radius: 8px; padding: 12px; color: #d7dfef; }
audio { width: 100%; margin-top: 10px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
</style>

<main>
    <div class="shell">
        <div class="card">
            <h1>TTS Connector Test</h1>
            <?php if ($connector): ?>
                <div><strong>Connector:</strong> <?php echo h($connector['label'] ?? ('Connector #' . $connectorId)); ?></div>
                <div><strong>Provider:</strong> <?php echo h($ttsConnector->getDisplayName($connectorDriver)); ?></div>
                <div><strong>Endpoint:</strong> <?php echo h($connector['url'] ?? ''); ?></div>
            <?php else: ?>
                <div class="error">Connector not found.</div>
            <?php endif; ?>
        </div>

        <?php if ($connector): ?>
            <form method="post" class="card">
                <input type="hidden" name="connector_id" value="<?php echo h($connectorId); ?>">
                <div class="field">
                    <label for="customstring">Text To Synthesize</label>
                    <textarea id="customstring" name="customstring"><?php echo h($testString); ?></textarea>
                </div>

                <div class="field">
                    <label for="voiceid">VoiceId</label>
                    <input type="text" id="voiceid" name="voiceid" value="<?php echo h($voiceId); ?>">
                    <div class="field-help">Connector-specific voice ID for this test run. Defaults to <code>TheNarrator</code>.</div>
                </div>

                <button type="submit" class="btn-save">Run Test</button>
            </form>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connector): ?>
            <div class="card">
                <h2>Status</h2>
                <?php if ($audioUrl !== ''): ?>
                    <div class="ok">Synthesis completed.</div>
                    <audio controls autoplay>
                        <source src="<?php echo h($audioUrl); ?>" type="audio/wav">
                    </audio>
                <?php else: ?>
                    <div class="error"><?php echo h($errorText !== '' ? $errorText : 'The test did not return audio.'); ?></div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Request Preview</h2>
                <pre><?php echo h(json_encode($requestPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>

            <div class="card">
                <h2>Debug Output</h2>
                <pre><?php echo h(json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
