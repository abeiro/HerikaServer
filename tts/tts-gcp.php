<?php

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

require_once($path . 'conf.php'); // API KEY must be there
require_once($path . 'vendor/autoload.php');

use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;

function tts($textString, $mood = 'default', $stringforhash)
{
  $startTime = microtime(true);

  // Path to the service account key JSON file
  $serviceAccountKeyFile = $GLOBALS['GCP_SA_FILEPATH'];
  if (!file_exists($serviceAccountKeyFile)) {
    // Handle the error when the service account key file is missing
    error_log('Service account key file not found.');
    return false;
  }

  // Initialize the client with authentication using the service account key file
  $client = new TextToSpeechClient([
      'credentials' => $serviceAccountKeyFile,
  ]);

  // Configure the synthesis input
  $voiceName = $GLOBALS['GCP_CONF']['voice']['name'];
  $languageCode = $GLOBALS['GCP_CONF']['voice']['languageCode'];

  $input = new SynthesisInput();
      if(!in_array($voiceName, ['en-US-Studio-O', 'en-US-Studio-M']))
      {
        $input->setSsml('<speak>'
            . '<prosody rate="' . $GLOBALS['GCP_CONF']['ssml']['rate']
            . '" pitch="' . $GLOBALS['GCP_CONF']['ssml']['pitch'] . '">'
            . $textString
            . '</prosody>' . '<break time="500ms"/>'
            . '</speak>');
      }
      else
      {
        $input->setSsml('<speak>'
            . $textString
            . '<break time="500ms"/>'
            . '</speak>');
      }

  $voice = new VoiceSelectionParams();
  $voice->setLanguageCode($languageCode);
  $voice->setName($voiceName);

  // Configure the audio settings
  $audioConfig = new AudioConfig();
  $audioConfig->setAudioEncoding(AudioEncoding::LINEAR16); // WAV format

  // Perform the text-to-speech synthesis
  $response = $client->synthesizeSpeech($input, $voice, $audioConfig);

  // Trying to avoid sync problems when saving
  $audioContent = $response->getAudioContent();

  $filename = dirname((__FILE__)) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'soundcache/' . md5(trim($stringforhash));
  $fileHandle = fopen($filename . '.wav', 'wb');
  $fileSize = fwrite($fileHandle, $audioContent);
  fflush($fileHandle);
  fclose($fileHandle);

  file_put_contents($filename . '.txt', trim($textString) . "\n\rsize of wav ($fileSize)\n\r" .
      'execution time: ' . (microtime(true) - $startTime) . ') secs ' .
      " function tts($textString,$mood,$stringforhash)");
}

?>
