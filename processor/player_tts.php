<?php

$cleaned_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);

audit_log(__FILE__ . " " . __LINE__);
pipeline_status_set('player_tts', true);

if (!class_exists('Player')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
}
if (!class_exists('ApiBadge')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");
}
if (!class_exists('TTSConnector')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
}

$origTTS = $GLOBALS["TTSFUNCTION"] ?? '';
$origName = $GLOBALS["HERIKA_NAME"] ?? '';
$hadPatchOverrideVoice = array_key_exists("PATCH_OVERRIDE_VOICE", $GLOBALS);
$hadPatchOverrideVoiceId = array_key_exists("PATCH_OVERRIDE_VOICE_ID", $GLOBALS);
$hadPatchOverrideLanguage = array_key_exists("PATCH_OVERRIDE_TTS_LANGUAGE", $GLOBALS);
$oldPatchOverrideVoice = $GLOBALS["PATCH_OVERRIDE_VOICE"] ?? null;
$oldPatchOverrideVoiceId = $GLOBALS["PATCH_OVERRIDE_VOICE_ID"] ?? null;
$oldPatchOverrideLanguage = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] ?? null;

try {
    $player = new Player();
    $ttsConnector = new TTSConnector();

    $connectorId = intval($player->get('tts_connector_id') ?? 0);
    $currentConnector = $connectorId > 0 ? $ttsConnector->getById($connectorId) : null;

    if (!$currentConnector || strtolower(trim(strval($currentConnector['driver'] ?? 'none'))) === 'none') {
        pipeline_status_set('player_tts', false);
        return;
    }

    $ttsConnector->setOldGlobals($currentConnector);
    $GLOBALS["TTSFUNCTION_PLAYER"] = strval($currentConnector['driver'] ?? '');

    $playerVoiceId = trim(strval($player->get('tts_voice_override') ?? ''));
    $voiceIdOverride = trim(strval($player->get('tts_voice_id_override') ?? ''));
    $languageOverride = trim(strval($player->get('tts_language_override') ?? ''));

    $GLOBALS["TTSFUNCTION_PLAYER_VOICE"] = $playerVoiceId;
    $GLOBALS["TTSFUNCTION_PLAYER_VOICE_ID"] = $voiceIdOverride;
    $GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"] = $languageOverride;

    if ($playerVoiceId !== '') {
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $playerVoiceId;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    }

    if ($voiceIdOverride !== '') {
        $GLOBALS["PATCH_OVERRIDE_VOICE_ID"] = $voiceIdOverride;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_VOICE_ID"]);
    }

    if ($languageOverride !== '') {
        $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] = $languageOverride;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    }

    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $GLOBALS["HERIKA_NAME"] = "Player";

    Translation::translate($cleaned_dialogue);
    Translation::$sentences = [Translation::$response];

    $writeOutput = isset($GLOBALS["PLAYER_TTS_WRITE_OUTPUT"]) ? (bool)$GLOBALS["PLAYER_TTS_WRITE_OUTPUT"] : true;
    $ownspeech = returnlines([$cleaned_dialogue], $writeOutput);

    pipeline_status_set('player_tts', false);

    if (Translation::isSavePlayerTranslationEnabled()) {
        $gameRequest[3] = $GLOBALS["PLAYER_NAME"] . ":" . Translation::$response;
    }
    Translation::reset();
} finally {
    if ($hadPatchOverrideVoice) {
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $oldPatchOverrideVoice;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    }

    if ($hadPatchOverrideVoiceId) {
        $GLOBALS["PATCH_OVERRIDE_VOICE_ID"] = $oldPatchOverrideVoiceId;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_VOICE_ID"]);
    }

    if ($hadPatchOverrideLanguage) {
        $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] = $oldPatchOverrideLanguage;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    }

    $GLOBALS["TTSFUNCTION"] = $origTTS;
    unset($GLOBALS["SCRIPTLINE_ANIMATION_SENT"]);
    $GLOBALS["HERIKA_NAME"] = $origName;
    unset($GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"]);
    pipeline_status_set('player_tts', false);
}

audit_log(__FILE__ . " " . __LINE__);
$startTimeAfterPlayerTTTS = microtime(true);

?>
