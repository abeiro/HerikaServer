<?php

function lazyEmpty($string) {
 
    if (empty(trim($string)))
        return true;
    
    if (trim($string)=="Null")
        return true;
    
    if (trim($string)=="null")
        return true;
    
    if (trim($string)=="None")
        return true;
    
    if (trim($string)=="none")
        return true;
    
    
}
function __jpd__extractContentBetweenBraces($inputString) {
    // Find the position of the first {
    $startPos = strpos($inputString, '{');
    
    // Find the position of the last }
    $endPos = strrpos($inputString, '}');
    
    // Check if both { and } are found
    if ($startPos !== false && $endPos !== false) {
        // Extract the content between { and }
        $result = substr($inputString, $startPos + 1, $endPos - $startPos - 1);
        return $result;
    } else {
        
    }
}

function returnObject($arr) {
 
    
    if (!is_array($arr))
        return [];
    
    if(sizeof($arr)==1) {
        if (is_array(current($arr)))
            return returnObject(current($arr));
    }
    
    return $arr;
}
function __jpd_close_left_open($cadena) {
    $pilaComillas = [];
    $pilaLlaves = [];
    $pilaCorchetes = [];

    for ($i = 0; $i < strlen($cadena); $i++) {
        $caracter = $cadena[$i];

        if ($caracter == '{') {
            array_push($pilaLlaves, '}');
        } elseif ($caracter == '}') {
            if (empty($pilaLlaves)) {
                ;
            } else {
                array_pop($pilaLlaves);
            }
        } elseif ($caracter == '[') {
            array_push($pilaCorchetes, ']');
        } elseif ($caracter == ']') {
            if (empty($pilaCorchetes)) {
                ;
            } else {
                array_pop($pilaCorchetes);
            }
        } elseif ($caracter == '"') {
            if (empty($pilaComillas)) {
                array_push($pilaComillas, $caracter);
            } else {
                array_pop($pilaComillas);
            }
        }
    }

    // Agregamos los caracteres de cierre faltantes a la cadena
    while (!empty($pilaComillas)) {
        $cadena .= array_pop($pilaComillas);
    }
    
    while (!empty($pilaLlaves)) {
        $cadena .= array_pop($pilaLlaves);
    }

    while (!empty($pilaCorchetes)) {
        $cadena .= array_pop($pilaCorchetes);
    }

 
    //error_log($cadena);
    return $cadena;

}

function __jpd_hash($object) {
    
    return md5(json_encode($object));
    
}

function __jpd_decode_lazy($inputString) {
 
    $realData = json_decode($inputString, true);
    if (is_array($realData))
        return $realData;

    $realData = json_decode(strtr($inputString,["\n"=>""]), true);
    if (is_array($realData))
        return $realData;
    
    $pattern = '/``json(.+?)```/s';
    // Extract the JSON code using the regular expression
    preg_match($pattern, $inputString, $matches);
    $result=[];
    if (isset($matches[1])) {
        $jsonCode = __jpd_decode_lazy($matches[1]);
        if (!isset($GLOBALS["_JSON_BUFFER"][__jpd_hash($jsonCode)])) {
                $GLOBALS["_JSON_BUFFER"][
                    __jpd_hash($jsonCode)
                ] = $jsonCode;
                //echo "Found: " . md5($unit["message"]) . PHP_EOL;
                $result[]=$jsonCode;
            } else
                ;
                
        return $result;
    }

    $pattern = '/``json(.+?)```/s';
    // Extract the JSON code using the regular expression
    preg_match($pattern, $inputString.'"}```', $matches);   // ** Match uncompleted objects
    $result=[];
    if (isset($matches[1])) {
        $jsonCode = json_decode($matches[1],true);
        if (!isset($GLOBALS["_JSON_BUFFER"][__jpd_hash($jsonCode)])) {
                $GLOBALS["_JSON_BUFFER"][
                    __jpd_hash($jsonCode)
                ] = $jsonCode;
                //echo "Found: " . md5($unit["message"]) . PHP_EOL;
                $result[]=$jsonCode;
            } else
                ;
                
        return $result;
    }
    
   
    $jsonCode = json_decode($inputString,true);
    if (is_array($jsonCode)) {
        if (!isset($GLOBALS["_JSON_BUFFER"][__jpd_hash($jsonCode)])) {
                $GLOBALS["_JSON_BUFFER"][
                    __jpd_hash($jsonCode)
                ] = $jsonCode;
                //echo "Found: " . md5($unit["message"]) . PHP_EOL;
                $result[]=$jsonCode;
            } else
                ;
                
        return isset($result)?$result:[];
    }
    
    // Common errors
    $realData = json_decode("$inputString }", true);
    if (is_array($realData)) {
        $result=$realData;
        //error_log("Case 1");
        return $result;
    }
    
    $realData = json_decode("$inputString\"}", true);
    if (is_array($realData)) {
        $result[]=$realData;
        //error_log("Case 2");
        return $result;
    }
    
    $realData = json_decode( strtr($inputString,['message":" "'=>'message":" ']), true);    // COMMON GEMMA2 ERRORS
    if (is_array($realData)) {
        $result=returnObject($realData);
        return $result;
    }
    
    $realData = json_decode( strtr($inputString,['message":""'=>'message":"']), true);    // COMMON GEMMA2 ERRORS
    if (is_array($realData)) {
        $result=returnObject($realData);
        return $result;
    }
    
    if (strpos($inputString,'"msg"')!==false) {
        $realData = __jpd_decode_lazy( strtr($inputString,['"msg"'=>'"message"']), true);    // COMMON GEMMA2 ERRORS
        if (is_array($realData)) {
            $result=returnObject($realData);
            return $result;
        }
    }
    
    
    if (strpos($inputString,',listener"')!==false) {
        $realData = __jpd_decode_lazy( strtr($inputString,[',listener"'=>',"listener"']), true);    // COMMON meta-llama/llama-3-8b-instruct erros
        if (is_array($realData)) {
            $result=returnObject($realData);
            return $result;
        }
    }
    
    if (strpos($inputString,',{"listener"')!==false) {
        $realData = __jpd_decode_lazy( strtr($inputString,[',{"listener"'=>',"listener"']), true);    // COMMON meta-llama/llama-3-8b-instruct erros
        if (is_array($realData)) {
            $result=returnObject($realData);
            return $result;
        }
    }
    
    if (strpos($inputString,'{"listener"')!==false) {
        $realData = __jpd_decode_lazy( strtr($inputString,['{"listener"'=>'"listener"']), true);    // COMMON meta-llama/llama-3-8b-instruct erros
        if (is_array($realData)) {
            $result=returnObject($realData);
            return $result;
        }
    }
    
    if (strpos($inputString,', {"listener"')!==false) {
        $realData = __jpd_decode_lazy( strtr($inputString,[', {"listener"'=>',"listener"']), true);    // COMMON meta-llama/llama-3-8b-instruct erros
        if (is_array($realData)) {
            $result=returnObject($realData);
            return $result;
        }
    }

    if (strpos($inputString,',listener"')!==false) {

        $realData = __jpd_decode_lazy(strtr($inputString,[',listener"'=>',"listener"'])."}", true);    // COMMON meta-llama/llama-3-8b-instruct erros
        if (is_array($realData)) {
            $result=returnObject($realData);
            return $result;
        }
    }
    
    
        
    $re = '/\{.*\}$/m';

    $inputStringMangled=trim(preg_replace('/\}(?!.*\})/', '}$1'."\n", $inputString));
    
    if (preg_match($re, $inputStringMangled, $matches, PREG_OFFSET_CAPTURE, 0)) {

        $extractedContent = $matches[0][0];
        $realData = json_decode($extractedContent, true);
         if (is_array($realData)) {
            $result[]=$realData;
            //error_log("Case 3");
            return $result;
        } 
    }
    
    //echo __jpd_close_left_open($inputString); 
    $realData = json_decode(__jpd_close_left_open($inputString), true);
    if (is_array($realData)) {
        $result[]=$realData;
        //error_log("Case 4");
        return $result;
    }
    
    $realData=json_decode("$inputString \"_fakeprop\":\"\"}",true);
    if (is_array($realData)) {
        $result=$realData;
        //error_log("Case 6");
        return $result;
    }
    
            
    $realData=json_decode("{".__jpd__extractContentBetweenBraces($inputString)."}",true);
    if (is_array($realData)) {
        $result[]=$realData;
        //error_log("Case 5");
        return $result;
    }

    return null;
    
}

function __jpd_decode($inputString)
{
    
    $realData = json_decode($inputString, true);

    if (is_array($realData)) {
        // Full object returned.
        if (sizeof($realData) > 0) {
            $result=[];
            if (!is_array($realData)) {
                return null;    // Data is not well formed
            }
            
            if (is_array(current($realData)))   // Retake this
                $pointer=current($realData);
            else
                $pointer=$realData;
            
            foreach ($pointer as $message) {
                if (!isset($GLOBALS["_JSON_BUFFER"][__jpd_hash($message)])) {
                    $GLOBALS["_JSON_BUFFER"][__jpd_hash($message)] = $message;
                    //echo "Full object {$message["message"]}".PHP_EOL;
                    $result[]=$message;
                }
            }
            return $result;
        }
    } else {
        // Partial object.
        // Incomplete JSON object.
        $partialJsonString = preg_replace("/(.*)\[/s", "", $inputString, 1); // Remove until first unit {

        //$partialJsonString = preg_replace('/\}(?=[^}]*$)/s', '}', $partialJsonString);      }

        // Remove until last unit
        $lastBracePosition = strrpos($partialJsonString, "}");
        if ($lastBracePosition !== false) {
            $partialJsonString = substr(
                $partialJsonString,
                0,
                $lastBracePosition + 1
            );
        }

        $partialJsonString = preg_replace(
            '/(?<=[^"])\n|\r/',
            "",
            $partialJsonString
        ); // Remove new line characters outside of double quotes

        $partialJsonString = str_replace(
            ["\r", "\n"],
            ['\\r', '\\n'],
            trim($partialJsonString)
        ); // Remove

        
        
        $partialJson = json_decode("[" . $partialJsonString . "]", true);

        //echo "\n#$partialJsonString#\n";
        
        if (is_array($partialJson)) {
            if (sizeof($partialJson) > 0) {
                $result=[];
                foreach ($partialJson as $unit) {
                    if (!isset($GLOBALS["_JSON_BUFFER"][__jpd_hash($unit)])) {
                        $GLOBALS["_JSON_BUFFER"][
                            __jpd_hash($unit)
                        ] = $unit;
                        //echo "Found: " . md5($unit["message"]) . PHP_EOL;
                        $result[]=$unit;
                    } else
                        ;//echo "Skipped: " . md5($unit["message"]) . PHP_EOL;
                }
                return $result;
            }
        } else {
            // Common errors.
            
            $realData = json_decode("$inputString }", true);
            if (is_array($realData)) {
                $result[]=$realData;
                return $result;
            }
            
            $realData = json_decode("$inputString \"}", true);
            if (is_array($realData)) {
                $result[]=$realData;
                return $result;
            }
            
          
           
            
            //die("here");
            
        }
        
        
    }
}

/*
$FATA = <<<EOIN
{"character":"Laufey the Tiefling","listener":"Nubidia","mood":"amused","action":"Talk","target":"Nubidia","message":"Oh, Nubidia, always so *concerned*. 
Don't worry, darling, I'm sure Volkur will find a way to amuse himself. 
He's quite the resourceful one, isn't he?"} 
EOIN;

//$res= __jpd__extractContentBetweenBraces($FATA);
//echo $res;

print_r(__jpd_decode_lazy($FATA));
*/

?>
