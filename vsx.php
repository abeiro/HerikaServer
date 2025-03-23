<?php

require_once("lib/utils.php");

/* Voice Sample Extractor */


$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib".DIRECTORY_SEPARATOR."fuz_convert.php"); // API KEY must be there
require_once($path . "lib" .DIRECTORY_SEPARATOR."auditing.php");


// Put info into DB asap
$db=new sql();
$voicelogic = $GLOBALS["TTS"]["XTTSFASTAPI"]["voicelogic"]; 


if ($voicelogic === 'voicetype') {

  //db insert for name entry for data_functions.
  $codename = npcNameToCodename($_GET["codename"]);
  $db->delete("conf_opts", "id='" . $db->escape("Nametype/$codename") . "'");
  $db->insert(
      'conf_opts',
      array(
          'id' => $db->escape("Nametype/$codename"),
          'value' => $_GET["oname"]
      )
  );

  // new logic so codename is set to voicetype so it generates voicetype sample
  $voicetype = explode("\\", $_GET["oname"]); // Split the path
  $codename = strtolower($voicetype[3]); // Use the 4th part of the path
  // Delete and insert the database entry
  $db->delete("conf_opts", "id='" . $db->escape("Voicetype/$codename") . "'");
  $db->insert(
      'conf_opts',
      array(
          'id' => $db->escape("Voicetype/$codename"),
          'value' => $_GET["oname"]
      )
  );

  $db->close();

    // update voiceid in the conf file if it is still blank (because the npc was added before they spoke)
    $replaceBlankVoiceID = function($ttsName, $voiceid, $confFilePath) {
        $pattern = '/\$TTS\[\"'.$ttsName.'\"\]\[\"voiceid\"\]\s*=\s*(".*"|\'.*\');/';
        $confContent = file_get_contents($confFilePath);
        preg_match_all($pattern, $confContent, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        if (!empty($matches)) {
            $lastMatch = end($matches);

            // only replace if the last voiceid is blank
            if ($lastMatch[1][0] == "''" || $lastMatch[1][0] == '""') {
                $startPosition = $lastMatch[0][1];
                $length = strlen($lastMatch[0][0]);
                $replacement = "\$TTS[\"$ttsName\"][\"voiceid\"]='$voiceid';";
                $updatedContent = substr_replace($confContent, $replacement, $startPosition, $length);
                file_put_contents($confFilePath, $updatedContent);
                
                print_r($updatedContent);
            }
        }
    };

    $hashedname=md5($_GET["codename"]);
    $confFilePath = __DIR__.DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR."conf_$hashedname.php";
    if (file_exists($confFilePath)) {
        $replaceBlankVoiceID("XTTSFASTAPI", $codename, $confFilePath);
        $replaceBlankVoiceID("ZONOS_GRADIO", $codename, $confFilePath);
    }

} else {
  $codename = npcNameToCodename($_GET["codename"]);
    // Old name logic
  $db->delete("conf_opts", "id='" . $db->escape("Voicetype/$codename") . "'");
  $db->insert(
      'conf_opts',
      array(
          'id' => $db->escape("Voicetype/$codename"),
          'value' => $_GET["oname"]
      )
  );
  $db->close();
}


if (strpos($_GET["oname"],".fuz"))  {
    $ext="fuz";
} else if (strpos($_GET["oname"],".xwm")) {
    $ext="xwm";
} else if (strpos($_GET["oname"],".wav")) {
  $ext="wav";
}


$already=file_exists("{$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]}/sample/$codename.wav");
$finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_vsx_".md5($_FILES["file"]["tmp_name"]).".$ext";
@copy($_FILES["file"]["tmp_name"] ,$finalName);



if (!$already) {

  if (file_exists($path."data/voices/$codename.wav")) {
    // File exists in HS data/voices. Dont't convert again
    $finalFile=$path."data/voices/$codename.wav";

  } else {

    if (!$_FILES["file"]["tmp_name"])
        die("VSX error, no data given");

    if (filesize($_FILES["file"]["tmp_name"])==0) {
        error_log("Empty file {$_FILES["file"]["tmp_name"]}");
        die();
    }

    
    error_log("Received sample: {$_GET["oname"]}");

    if (strpos($_GET["oname"],".fuz")) {
        $finalFile=fuzToWav($finalName);
        
    } else if (strpos($_GET["oname"],".xwm")) {

        $finalFile=xwmToWav($finalName);

      } else if (strpos($_GET["oname"],".wav")) {

        $finalFile=wavToWav($finalName);
    }
  }
  if (!isset($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) || !($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) ) {
    die("Error");
  }

} else {
  error_log("Empty file {$_FILES["file"]["tmp_name"]} already exists at {$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]}/sample/$codename.wav");
  
}


if ($already) {
  die();
}

// Lets store voice files
@copy($finalFile,$path."data/voices/$codename.wav");

$url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"].'/upload_sample';
$curl = curl_init();

// Set cURL options
curl_setopt_array($curl, array(
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => array(
    'wavFile' => new CURLFile($finalFile, 'audio/wav', "$codename.wav")
  ),
  CURLOPT_HTTPHEADER => array(
    'Content-Type: multipart/form-data'
  )
));

// Execute cURL request and get response
$response = curl_exec($curl);

  
?>
