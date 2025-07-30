<?php


$method = $_SERVER['REQUEST_METHOD'];

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."../";
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");

$configFilepath=$enginePath."conf".DIRECTORY_SEPARATOR;

if ($method === 'POST') {

  // Read JSON data from the request
    $jsonDataInput = json_decode(file_get_contents("php://input"), true);
    $files=glob($configFilepath . 'conf_????????????????????????????????.php');
    $files[]=$configFilepath. 'conf.php';

    $updated = [];
    $skipped = [];

    foreach ($files as $mconf ) {

        if (file_exists($mconf)) {
          
            // First, check if this profile is locked and get character name
            $isLocked = false;
            $original=file_get_contents($mconf);
            
            // Extract character name
            $characterName = "Unknown";
            if (preg_match('/\$HERIKA_NAME\s*=\s*[\'"]([^\'"]+)[\'"];/', $original, $nameMatches)) {
                $characterName = $nameMatches[1];
            } else if (basename($mconf) === "conf.php") {
                $characterName = "The Narrator";
            }

            // Skip lock check for default profile
            if (basename($mconf) !== "conf.php") {
                // Look for LOCK_PROFILE setting only for non-default profiles
                if (preg_match('/\$LOCK_PROFILE\s*=\s*true\s*;/', $original)) {
                    $skipped[] = $characterName;
                    Logger::trace("Skipping locked profile: " . $characterName);
                    continue;
                }
            }

            /* $pattern = '/<\?php(.*?)\?>/s';   */
            $pattern = '/<\?php(.*?)(?:\?>|$)/s'; /* sometime the file ends without PHP end mark '?>'. In this case read till EOF */

            // Use preg_match to find the content between the PHP tags
            if (preg_match($pattern, $original, $matches)) {
                // $matches[1] contains the content between the tags
                $php_code = trim($matches[1]);

            } else {
                Logger::error("No PHP code found in the file. {$characterName} {$mconf} ");
                continue;
            }

            // Split the string by '@'
            $parts = explode('@', $jsonDataInput["name"]);

            // Construct the PHP array notation
            $result = '$' . array_shift($parts);
            foreach ($parts as $part) {
                $result .= '["' . $part . '"]';
            }

            $value=$jsonDataInput["value"];
            Logger::trace("copying {$jsonDataInput["name"]} to profile: " . $characterName);
            $new_php_code="";
            if (!is_array($value))
                if ($value=='false')
                    $new_php_code.="$result=false;".PHP_EOL;
                else if ($value=='true')
                    $new_php_code.="$result=true;".PHP_EOL;
                else
                    $new_php_code.="$result='".addslashes($value)."';".PHP_EOL;
            else {
                $vv=[];
                foreach ($value as $v) {
                    $vv[]=addslashes($v);
                }
                $new_php_code.="$result=['".implode("','",$vv)."'];".PHP_EOL;
            }
            
            $updated[] = $characterName;
            file_put_contents($mconf,"<?php".PHP_EOL.$php_code.PHP_EOL.$new_php_code."?>");
            Logger::trace("Written to " . $characterName . "'s profile");

        } else {
            Logger::warn("$mconf file does not exists!");
        }
    }

    echo json_encode([
        "updated" => $updated,
        "skipped" => $skipped
    ]);
}


?>
