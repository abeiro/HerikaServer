<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

@mkdir("tmp");
$fileName=__DIR__.DIRECTORY_SEPARATOR."tmp".DIRECTORY_SEPARATOR."conf.check.php";

file_put_contents($fileName,$_POST["text"]);

echo '<!DOCTYPE html>
<html lang="en" >
<head>
<style>
body {
  background-color: black;
  color: white;
  font-size: small ; 
  font-family: Consolas,Monaco,Lucida Console,Liberation Mono,DejaVu Sans Mono,Bitstream Vera Sans Mono,Courier New, monospace;
  width: 100%;
  display: inline-block;
}
</style>
</head>
<body>
';

echo "Checking syntax...<br/>";
try {
    require_once($fileName);
} catch (Exception $e) {
    echo $e->getMessage();
    echo "Errors";
    unlink($fileName);    
    die();
}

echo "Checking patterns...<br/>";
$input = file_get_contents($fileName);
$pattern = '/<\?php\s+(.*?)\?>/s';
preg_match($pattern, $input, $matches);

if (isset($matches[1])) {
    $matchedText = $matches[1];
} else {
    unlink($fileName);    
    die("Seems to be an unexpected general error. Check opening <strong>".htmlentities("<?php")."</strong> and <strong>".htmlentities("?>")."</strong> tags ");
}

echo "Checking needed vars...<br/>";


$NEEDED_VARS=["PLAYER_NAME","HERIKA_NAME","HERIKA_PERS","PROMPT_HEAD"];
$errorFlag=false;

foreach ($NEEDED_VARS as $var) {
    if (!isset($GLOBALS[$var])) {
            echo "Needed var $var not found<br/>";
            $errorFlag=true;
    } else
        echo "$var found <br/>";  
}

if (!$TTSFUNCTION) {
    echo 'Note: No TTS service configured $TTSFUNCTION missing<br/>';
} else
    echo "Using TTS service <strong>$TTSFUNCTION</strong> <br/>";
if (!$STTFUNCTION) {
        echo 'Note: No STT service configured $STTFUNCTION missing<br/>';
} else
    echo "Using  STT service <strong>$STTFUNCTION</strong> <br/>";

if ($TTSFUNCTION=="azure")
    if (!isset($TTS["AZURE"]["API_KEY"])) {
        echo 'Error: Azure is in use but $TTS["AZURE"]["API_KEY"] not found <br/>';
        $errorFlag=true;
    }
    
if ($TTSFUNCTION=="mimic3")
    if (!isset($TTS["MIMIC3"]["URL"])) {
        echo 'Error: MIMIC3 is in use but $TTS["MIMIC3"]["URL"]  not found <br/>';
        $errorFlag=true;
    }

if ($TTSFUNCTION=="gcp")
    if (!isset($TTS["GCP"]["GCP_SA_FILEPATH"])) {
        echo 'Error: Google Cloud Platform is in use but $TTS["GCP"]["GCP_SA_FILEPATH"] not found <br/>';
        $errorFlag=true;
    }
    
if ($TTSFUNCTION=="11labs")
    if (!isset($TTS["ELEVEN_LABS"]["API_KEY"])) {
        echo 'Error: Elevenlabs is in use but $TTS["ELEVEN_LABS"]["API_KEY"]  not found <br/>';
        $errorFlag=true;
    }

if ($STTFUNCTION=="azure")
    if (!isset($STT["AZURE"]["API_KEY"])) {
        echo 'Error: Azure is in use for speech to text but $STT["AZURE"]["API_KEY"] not found <br/>';
        $errorFlag=true;
    }
    
if ($STTFUNCTION=="whisper")
    if (!isset($STT["WHISPER"]["API_KEY"])) {
        echo 'Error: Whisper is in use but $STT["WHISPER"]["API_KEY"] not found <br/>';
        $errorFlag=true;
    }

if ($STTFUNCTION=="localwhisper")
    if (!isset($STT["LOCALWHISPER"]["URL"])) {
        echo 'Error: Local Whisper is in use but $STT["LOCALWHISPER"]["URL"] not found <br/>';
        $errorFlag=true;
    }
    
foreach ($CONNECTORS as $name) {
    if ($name=="openai") {
        if (!$CONNECTOR["openai"]["API_KEY"]) {
            echo 'Error: Connector openai in use, but $CONNECTOR["openai"]["API_KEY"] not found <br/>';
        }
    } else if ($name="koboldcpp") {
    
        if (!$CONNECTOR["koboldcpp"]["url"]) {
                echo 'Error: Connector koboldcpp in use, but $CONNECTOR["koboldcpp"]["url"] not found <br/>';
        }
    } else if ($name="openrouter") {
    
        if (!$CONNECTOR["openrouter"]["API_KEY"]) {
                echo 'Error: Connector openrouter in use, but $CONNECTOR["openrouter"]["API_KEY"] not found <br/>';
        }
    }
}
    
if (!$errorFlag)
    echo "<strong>Everything seems ok! You can safely save now</strong>\n";



?>
