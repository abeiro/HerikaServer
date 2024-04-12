<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class connector
{
    public $primary_handler;
    public $name;

    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    private $_numOutputTokens;
    private $_dataSent;
    private $_fid;
    private $_buffer;
    private $_stopProc;


    public function __construct()
    {
        $this->name="openaijson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;

    }


    public function open($contextData, $customParms)
    {
        $url = $GLOBALS["CONNECTOR"][$this->name]["url"];

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);



        /***
            In the realm of perfection, the demand to tailor context for every language model would be nonexistent.

                                                                                                Tyler, 2023/11/09
        ****/
        
        if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] && isset($GLOBALS["MEMORY_STATEMENT"]) ) {
            foreach ($contextData as $n=>$contextline)  {
                if (strpos($contextline["content"],"#MEMORY")===0) {
                    $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                    $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                }
            }
        }
        
        $contextData[]= [
            'role' => 'user', 
            'content' => "Use this JSON object to give your aswer: ".json_encode([
                "character"=>$GLOBALS["HERIKA_NAME"],
                "listener"=>"(who is talking to)",
                "message"=>'message',
                "voice_tone"=>'',
                "action"=>'a valid action, (refer to available actions list)',
                "target"=>"action's target",
                
        ])
        ];
        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gpt-3.5-turbo-1106',
            'messages' =>
                $contextData
            ,
            'stream' => true,
            'max_tokens'=>$MAX_TOKENS,
            'temperature' => ($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?: 1,
            'top_p' => ($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ?: 1,
            'response_format'=>["type"=>"json_object"]
        );
        // Mistral AI API does not support penalty params
        if (strpos($url, "mistral") === false) {
            $data["presence_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 0;
            $data["frequency_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ?: 0;
        }
  
        

        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==0) {
                unset($data["max_tokens"]);
            } elseif (isset($customParms["MAX_TOKENS"])) {
                $data["max_tokens"]=$customParms["MAX_TOKENS"]+0;
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==0) {
                unset($data["max_tokens"]);
            } else
                $data["max_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"]+0;
            
        }

        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            foreach ($GLOBALS["FUNCTIONS"] as $function) {
                //$data["tools"][]=["type"=>"function","function"=>$function];
                $data["messages"][0]["content"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";
            }
             

        }


        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}"
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: 30
            )
        );

        $context = stream_context_create($options);
        
        $this->primary_handler = fopen($url, 'r', false, $context);
        if (!$this->primary_handler) {
                error_log(print_r(error_get_last(),true));
                return null;
        }

        $this->_dataSent=json_encode($data);    // Will use this data in tokenizer.

        
        return true;


    }


    public function process()
    {
        global $alreadysent;

        static $numOutputTokens=0;

        $line = fgets($this->primary_handler);
        $buffer="";
        $totalBuffer="";
        $finalData="";
        
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);

        $data=json_decode(substr($line, 6), true);
        if (isset($data["choices"][0]["delta"]["content"])) {
            if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                $buffer.=$data["choices"][0]["delta"]["content"];
                $this->_buffer.=$data["choices"][0]["delta"]["content"];
                $this->_numOutputTokens += 1;

            }
            $totalBuffer.=$data["choices"][0]["delta"]["content"];

        }
        
        $buffer="";
        if (!empty($this->_buffer))
            $finalData=json_decode($this->_buffer, true);
            if (is_array($finalData)) {
                $buffer=$finalData["message"];
                if (isset($GLOBALS["ALREADY_SENT_BUFFER"][md5($buffer)])) {
                    $buffer="";
                } else {
                    $GLOBALS["ALREADY_SENT_BUFFER"][md5($buffer)][]=$buffer;
                    $GLOBALS["DEBUG_DATA"]["json_raw"]=$this->_buffer;
                    if (!empty($finalData["listener"]))
                            $GLOBALS["LISTENER"]=$finalData["listener"];
                    
                    if ($finalData["voice_tone"])
                        return "{$finalData["character"]} ({$finalData["voice_tone"]}): $buffer.";
                    else
                        return "{$finalData["character"]}: $buffer";
                }
                
            } else
                $buffer="";
        
        return $buffer;
    }

    // Method to close the data processing operation
    public function close()
    {

        fclose($this->primary_handler);
        if ($GLOBALS["FEATURES"]["COST_MONITOR"]["ENABLED"]) {
            // Call rest of tokenizer functions now, relevant data was sent

            TkTokenizePrompt($this->_dataSent, $GLOBALS["CONNECTOR"][$this->name]["model"]);
            TkTokenizeResponse($this->_numOutputTokens, $GLOBALS["CONNECTOR"][$this->name]["model"]);
        }
    }

    // Method to close the data processing operation
    public function processActions()
    {
        global $alreadysent;

        if ($this->_functionName) {
            $parameterArr = json_decode($this->_parameterBuff, true);
            if (is_array($parameterArr)) {
                $parameter = current($parameterArr); // Only support for one parameter

                if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$this->_functionName}@$parameter\r\n")])) {
                    $functionCodeName=getFunctionCodeName($this->_functionName);
                    $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@$parameter\r\n";
                    //echo "Herika|command|$functionCodeName@$parameter\r\n";

                }

                $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$this->_functionName}@$parameter\r\n")] = "{$GLOBALS["HERIKA_NAME"]}|command|{$this->_functionName}@$parameter\r\n";
                @ob_flush();
            } else 
                return null;
        } else {
            $GLOBALS["DEBUG_DATA"]["RAW"]=$this->_buffer;
            $parsedResponse=json_decode($this->_buffer,true);   // USE JPD_LAZY?
            if ($parsedResponse) {
                if (!empty($parsedResponse["action"])) {
                    if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")])) {
                        $functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                        $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n";
                        //echo "Herika|command|$functionCodeName@$parameter\r\n";
                    } else
                        $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")]=
                    "{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n";
                }
                
                @ob_flush();    
            } else 
                return null;
        }

        return $this->_commandBuffer;
    }

    public function isDone()
    {
        return feof($this->primary_handler);
    }

}
