<?php 
// Use preg_replace to remove the name and colon before the dialogue
    $cleaned_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);
    
    
    audit_log(__FILE__." ".__LINE__);
    $GLOBALS["PATCH_OVERRIDE_VOICE"]=$GLOBALS["TTSFUNCTION_PLAYER_VOICE"];
    $GLOBALS["PATCH_OVERRIDE_VOICE_ID"]=$GLOBALS["TTSFUNCTION_PLAYER_VOICE_ID"];
    $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]=$GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"];
    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"]=true;
    $origTTS=$GLOBALS["TTSFUNCTION"];
    $origName=$GLOBALS["HERIKA_NAME"];

    $GLOBALS["TTSFUNCTION"]=$GLOBALS["TTSFUNCTION_PLAYER"];
    $GLOBALS["HERIKA_NAME"]="Player";

    // error_log("$cleaned_dialogue {$GLOBALS["TTSFUNCTION_PLAYER"]} {$GLOBALS["TTSFUNCTION"]} {$GLOBALS["PATCH_OVERRIDE_VOICE"]} override:{$OVERRIDES["TTSFUNCTION_PLAYER"]}");
    
    Translation::translate($cleaned_dialogue);
    Translation::$sentences = [Translation::$response];

    $ownspeech=returnlines([$cleaned_dialogue]);
    
    if (Translation::isSavePlayerTranslationEnabled()) {
        $gameRequest[3]=$GLOBALS["PLAYER_NAME"].":".Translation::$response;
    }
    Translation::reset();
    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    unset($GLOBALS["PATCH_OVERRIDE_VOICE_ID"]);
    unset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    $GLOBALS["TTSFUNCTION"]=$origTTS;
    unset($GLOBALS["SCRIPTLINE_ANIMATION_SENT"]);
    $GLOBALS["HERIKA_NAME"]=$origName;
    unset($GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"]);
    audit_log(__FILE__." ".__LINE__);
    $startTimeAfterPlayerTTTS = microtime(true);

?>