<?php

$cleaned_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);

if (!function_exists('emitPlayerTextOnlyScriptQueueLine')) {
    function emitPlayerTextOnlyScriptQueueLine($line)
    {
        $subtitle = (string)$line;
        if (function_exists('formatPlayerSubtitleText')) {
            $subtitle = formatPlayerSubtitleText($subtitle, $GLOBALS["PLAYER_NAME"] ?? null);
        } else {
            $subtitle = str_replace(["\r", "\n", "|"], " ", $subtitle);
            $subtitle = trim(preg_replace('/\s+/', ' ', $subtitle));
        }

        if ($subtitle === '') {
            return;
        }

        $outputLine = "Player|ScriptQueue|{$subtitle}//__player_text_only///1.0\r\n";
        echo $outputLine;
        $GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"] = $outputLine;
        @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR . "output_to_plugin.log", $outputLine, FILE_APPEND | LOCK_EX);
        if (ob_get_level()) {
            @ob_flush();
        }
        @flush();
    }
}

audit_log(__FILE__ . " " . __LINE__);
pipeline_status_set('player_tts', true);

if (!class_exists('Player')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
}
if (!class_exists('TTSConnector')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
}

$origTTS = $GLOBALS["TTSFUNCTION"] ?? '';
$hadTtsFunctionAlias = array_key_exists("TTS_FUNCTION", $GLOBALS);
$origTtsFunctionAlias = $GLOBALS["TTS_FUNCTION"] ?? null;
$hadCurrentTtsConnectorId = array_key_exists("CHIM_CORE_CURRENT_TTS_CONNECTOR_ID", $GLOBALS);
$origCurrentTtsConnectorId = $GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] ?? null;
$origName = $GLOBALS["HERIKA_NAME"] ?? '';
$hadPatchOverrideVoice = array_key_exists("PATCH_OVERRIDE_VOICE", $GLOBALS);
$hadPatchOverrideVoiceId = array_key_exists("PATCH_OVERRIDE_VOICE_ID", $GLOBALS);
$hadPatchOverrideLanguage = array_key_exists("PATCH_OVERRIDE_TTS_LANGUAGE", $GLOBALS);
$hadPatchOverrideTtsOptions = array_key_exists("PATCH_OVERRIDE_TTS_OPTIONS", $GLOBALS);
$oldPatchOverrideVoice = $GLOBALS["PATCH_OVERRIDE_VOICE"] ?? null;
$oldPatchOverrideVoiceId = $GLOBALS["PATCH_OVERRIDE_VOICE_ID"] ?? null;
$oldPatchOverrideLanguage = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] ?? null;
$oldPatchOverrideTtsOptions = $GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"] ?? null;

try {
    $player = new Player();
    $ttsConnector = new TTSConnector();
    $writeOutput = isset($GLOBALS["PLAYER_TTS_WRITE_OUTPUT"]) ? (bool)$GLOBALS["PLAYER_TTS_WRITE_OUTPUT"] : true;

    $connectorId = intval($player->get('tts_connector_id') ?? 0);
    $currentConnector = $connectorId > 0 ? $ttsConnector->getById($connectorId) : null;
    $hasPlayerTtsConnector = $currentConnector && strtolower(trim(strval($currentConnector['driver'] ?? 'none'))) !== 'none';

    if ($hasPlayerTtsConnector) {
        $ttsConnector->setOldGlobals($currentConnector);
        $GLOBALS["TTSFUNCTION_PLAYER"] = strval($currentConnector['driver'] ?? '');
        $playerDriver = strtolower(trim(strval($currentConnector['driver'] ?? '')));
        $GLOBALS["TTSFUNCTION"] = $playerDriver;
        $GLOBALS["TTS_FUNCTION"] = $playerDriver;

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

        if ($playerDriver === '11labs') {
            $playerElevenLabsOverrides = [];
            foreach ([
                'model_id' => 'tts_elevenlabs_model_id',
                'speed' => 'tts_elevenlabs_speed',
                'stability' => 'tts_elevenlabs_stability',
                'similarity_boost' => 'tts_elevenlabs_similarity_boost',
                'style' => 'tts_elevenlabs_style',
                'v3_audio_tags' => 'tts_elevenlabs_v3_audio_tags',
            ] as $metadataKey => $playerKey) {
                $rawValue = trim(strval($player->get($playerKey) ?? ''));
                if ($rawValue !== '') {
                    $playerElevenLabsOverrides[$metadataKey] = $rawValue;
                }
            }

            $speakerBoostValue = trim(strval($player->get('tts_elevenlabs_use_speaker_boost') ?? ''));
            if ($speakerBoostValue !== '') {
                $playerElevenLabsOverrides['use_speaker_boost'] = strtolower($speakerBoostValue) === 'true';
            }

            if (!empty($playerElevenLabsOverrides)) {
                $GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"] = [
                    'driver' => $playerDriver,
                    'metadata' => $playerElevenLabsOverrides,
                ];
            } else {
                unset($GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"]);
            }
        } else {
            unset($GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"]);
        }
    }

    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $GLOBALS["HERIKA_NAME"] = "Player";

    Translation::translate($cleaned_dialogue);
    Translation::$sentences = [Translation::$response];

    if ($hasPlayerTtsConnector) {
        $ownspeech = returnlines([$cleaned_dialogue], $writeOutput);
    } elseif ($writeOutput) {
        emitPlayerTextOnlyScriptQueueLine($cleaned_dialogue);
    }

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

    if ($hadPatchOverrideTtsOptions) {
        $GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"] = $oldPatchOverrideTtsOptions;
    } else {
        unset($GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"]);
    }

    $GLOBALS["TTSFUNCTION"] = $origTTS;
    if ($hadTtsFunctionAlias) {
        $GLOBALS["TTS_FUNCTION"] = $origTtsFunctionAlias;
    } else {
        unset($GLOBALS["TTS_FUNCTION"]);
    }
    if ($hadCurrentTtsConnectorId) {
        $GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] = $origCurrentTtsConnectorId;
    } else {
        unset($GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"]);
    }
    unset($GLOBALS["SCRIPTLINE_ANIMATION_SENT"]);
    $GLOBALS["HERIKA_NAME"] = $origName;
    unset($GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"]);
    pipeline_status_set('player_tts', false);
}

audit_log(__FILE__ . " " . __LINE__);
$startTimeAfterPlayerTTTS = microtime(true);

?>
