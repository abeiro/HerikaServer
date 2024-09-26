<?php


$method = $_SERVER['REQUEST_METHOD'];

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."../";
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");

$configFilepath=$enginePath."conf".DIRECTORY_SEPARATOR;

if ($method === 'POST') {

  // Read JSON data from the request
    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    $files=glob($configFilepath . 'conf_????????????????????????????????.php');
    $files[]=$configFilepath. 'conf.php';

    foreach ($files as $mconf ) {
        if (file_exists($mconf)) {
            $original=file_get_contents($mconf);

            $pattern = '/<\?php(.*?)\?>/s';

            // Use preg_match to find the content between the PHP tags
            if (preg_match($pattern, $original, $matches)) {
                // $matches[1] contains the content between the tags
                $php_code = trim($matches[1]);

            } else {
                error_log("No PHP code found in the file.");
            }

            // Split the string by '@'
            $parts = explode('@', $jsonDataInput["name"]);

            // Construct the PHP array notation
            $result = '$' . array_shift($parts);
            foreach ($parts as $part) {
                $result .= '["' . $part . '"]';
            }

            $value=$jsonDataInput["value"];
            $new_php_code="";
            if (!is_array($value))
                $new_php_code.="$result='".addslashes($value)."';".PHP_EOL;
            else {
                $vv=[];
                foreach ($value as $v) {
                    $vv[]=addslashes($v);
                }
                $new_php_code.="$result=['".implode("','",$vv)."'];".PHP_EOL;
            }
            $fres[]=$mconf;
            file_put_contents($mconf,"<?php".PHP_EOL.$php_code.PHP_EOL.$new_php_code."?>");
            error_log("Written $mconf");

        } else {
            error_log("Does not exists $mconf");
        }
    }


    echo json_encode($fres);
}


?>
