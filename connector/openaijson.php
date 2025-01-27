<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class openaijson
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
    private $_is_groq_com;
    public $_extractedbuffer;

    public function __construct()
    {
        $this->name="openaijson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        require_once(__DIR__."/__jpd.php");
    }


    public function open($contextData, $customParms)
    {
        $url = $GLOBALS["CONNECTOR"][$this->name]["url"];
        $this->_is_groq_com = (strpos($url, "groq.com") > 0 ); // https://api.groq.com/openai/v1/chat/completions

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);



        /***
            In the realm of perfection, the demand to tailor context for every language model would be nonexistent.

                                                                                                Tyler, 2023/11/09
        ****/
        
        if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] && isset($GLOBALS["MEMORY_STATEMENT"]) ) {
            foreach ($contextData as $n=>$contextline)  {
                if (is_array($contextline)) {

                    if (strpos($contextline["content"],"#MEMORY")===0) {
                        $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                    } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                        $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                    }
                }
            }
        }

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
            //$prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        }
        
        if (strpos($GLOBALS["HERIKA_PERS"],"#SpeechStyle")!==false) {
            $speechReinforcement="Check reference #SpeechStyle.";
        } else
            $speechReinforcement="";

        $contextData[]=[
            'role' => 'user',
            'content' => "{$prefix}. $speechReinforcement Use this JSON object to give your answer: ".json_encode($GLOBALS["responseTemplate"])
        ];

        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            // there is a double inclusion, part of command_prompt is already included in content from main.php
            if (isset($GLOBALS["COMMAND_PROMPT_FUNCTIONS"])) {
                $s_mark = $GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
                $s_cprompt = $GLOBALS["COMMAND_PROMPT"];
                $s_ctx = $contextData[0]["content"];
                if (!(stripos($s_ctx, $s_mark) === false)) {
                    $i_pos = stripos($s_cprompt, $s_mark); 
                    if ((!($i_pos === false)) && ($i_pos > 0)) {
                        $i_len = strlen($s_mark);
                        $s_cprompt = substr($GLOBALS["COMMAND_PROMPT"], $i_pos + $i_len );
                    }
                }
            }
            $contextData[0]["content"].=$s_cprompt;
        }

        $pb=[];
        $pb["user"]="";
        $pb["system"]=""; 
        
        $contextDataOrig=array_values($contextData);
        $lastrole="";
        $assistantRoleBuffer="";
        foreach ($contextDataOrig as $n=>$element) {
            
            if (!is_array($element)) {
                error_log("Warning: $n=>$element was not an array");
                continue;

            }

            if ($n>=(sizeof($contextDataOrig)-1) && $element["role"]!="tool") {
                // Last element
                $pb["user"].=$element["content"];
                $contextDataCopy[]=$element;
                
            } else {

                if ($lastrole=="assistant" && $lastrole!=$element["role"] && $element["role"]!="tool" ) {
                    $contextDataCopy[]=[
                        "role"=>"assistant",
                        "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"$lastTargetBuffer\", \"mood\": \"\", \"action\": \"Talk\",\"target\": \"\", \"message\":\"".trim($assistantRoleBuffer)."\"}"
                        
                    ];
                    $lastTargetBuffer="";
                    $assistantRoleBuffer="";
                    $lastrole=$element["role"];
                }

                if ($element["role"]=="system") {
                    
                    $pb["system"]=$element["content"]."\nThis is the script history for this story\n#CONTEXT_HISTORY\n";
                    $contextDataCopy[]=$element;
                    
                } else if ($element["role"]=="user") {
                    if (empty($element["content"])) {
                        error_log("Empty element[content]".__FILE__." ".__LINE__);
                        //unset($contextData[$n]);
                    } else
                        $contextDataCopy[]=$element;
                    
                    $pb["system"].=trim($element["content"])."\n";
                    
                } else if ($element["role"]=="assistant") {
                    $assistantAppearedInhistory=true;
                    if (isset($element["tool_calls"])) {
                        $pb["system"].="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$element["tool_calls"][0]["function"]["name"]}";
                        $lastAction="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$element["tool_calls"][0]["function"]["name"]} {$element["tool_calls"][0]["function"]["arguments"]}";
                        $lastActionName=$element["tool_calls"][0]["function"]["name"];
                        $localFuncCodeName=getFunctionCodeName($element["tool_calls"][0]["function"]["name"]);
                        $localArguments=json_decode($element["tool_calls"][0]["function"]["arguments"],true);
                        $lastAction=strtr($GLOBALS["F_RETURNMESSAGES"][$localFuncCodeName],[
                                        "#TARGET#"=>current($localArguments),
                                        ]);
                        
                        $contextDataCopy[]=[
                                "role"=>"assistant",
                                "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\",\"action\": \"$lastActionName\",\"target\": \"".current($localArguments)."\", \"message\": \"\"}"
                            ];
                            
                        $gameRequestCopy=$GLOBALS["gameRequest"];    
                        $gameRequestCopy[3]="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\",\"action\": \"$lastActionName\", \"target\": \"".current($localArguments)."\", \"message\": \"\"}";
                        $gameRequestCopy[0]="logaction";
                        logEvent($gameRequestCopy);   
                        
                        unset($contextData[$n]);
                    } else {
                        $alreadyJs=json_decode($element["content"],true);
                        if (is_array($alreadyJs)) {
                            $contextDataCopy[]=[
                                    "role"=>"assistant",
                                    "content"=>json_encode($alreadyJs)
                                ];
                            
                        } else {
                            //error_log("#### ".$element["content"]);
                            $pb["system"].=$element["content"]."\n";
                            $dialogueTarget=extractDialogueTarget($element["content"]);
                            // Trying to provide examples
                            if (true) {
                                $assistantRoleBuffer.=$dialogueTarget["cleanedString"];                                
                                $lastTargetBuffer=$dialogueTarget["target"];
                                unset($contextData[$n]);
                            } else {
                                
                                $contextData[$n]=[
                                        "role"=>"assistant",
                                        "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\", \"action\": \"Talk\",\"target\": \"\", \"message\":\"".trim($dialogueTarget["cleanedString"])."\"}"
                                        
                                    ];
                            }
                        }
                    }
                    
                } else if ($element["role"]=="tool") {
                    
                        if (!empty($element["content"])) {
                            $pb["system"].=$element["content"]."\n";
                            
                           
                            if (strpos($element["content"],"Error")===0) {
                                $GLOBALS["PATCH_STORE_FUNC_RES"]="{$GLOBALS["HERIKA_NAME"]} issued ACTION, but {$element["content"]}";
                                $contextDataCopy[]=[
                                    "role"=>"user",
                                    "content"=>"The Narrator: ({$GLOBALS["HERIKA_NAME"]} used action $lastActionName). {$GLOBALS["PATCH_STORE_FUNC_RES"]}"
                                    
                                ];
                            } else {
                                
                                $GLOBALS["PATCH_STORE_FUNC_RES"]=strtr($lastAction,["#RESULT#"=>$element["content"]]);
                                $contextDataCopy[]=[
                                    "role"=>"user",
                                    "content"=>"The Narrator: ({$GLOBALS["HERIKA_NAME"]} used action $lastActionName). {$GLOBALS["PATCH_STORE_FUNC_RES"]} ",
                                    
                                ];
                            }
                        } else {
                            ;
                            //unset($contextData[$n]);
                        }
                            
                }
                
            }

            

            // 
            $lastrole=$element["role"];
        }
        
        $contextData=$contextDataCopy;

        
        $contextData2=[];
        $contextData2[]= ["role"=>"system","content"=>$pb["system"]];
        $contextData2[]= ["role"=>"user","content"=>$pb["user"]];
        
        
        // Compacting */
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) 
            $contextDataCopy[]=$element;
        $contextData=$contextDataCopy;
        
        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 1.0);
        if ($temperature < 0.0) $temperature = 0.0;
        else if ($temperature > 2.0) $temperature = 2.0; 

        $presence_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ? : 0.0);
        if ($presence_penalty < -2.0) $presence_penalty = -2.0;
        else if ($presence_penalty > 2.0) $presence_penalty = 2.0; 


        $frequency_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ? : 0.0); 
        if ($frequency_penalty < -2.0) $frequency_penalty = -2.0;
        else if ($frequency_penalty > 2.0) $frequency_penalty = 2.0; 

        $top_p = floatval(($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? : 1.0);
        if ($top_p > 1) $top_p = 1.0;
        else if ($top_p < 0.0) $top_p = 0.0; 

        // Forcing JSON output

        if ($this->_is_groq_com) { // --- exception made for groq.com

            if ($temperature < 0.000001) $temperature = 0.000001; // groq.com want this > 1e-8, never 0.0

            $data = array(
                'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'llama-3.3-70b-versatile',
                'messages' => $contextData,
                'stream' => false, // groq can't stream for JSON
                'max_completion_tokens' => $MAX_TOKENS,
                'temperature' => $temperature,
                'top_p' => $top_p,
                'presence_penalty' => $presence_penalty, 
                'frequency_penalty' => $frequency_penalty, 
                'response_format'=>["type"=>"json_object"]
            );

            if (!(stripos($data["model"],"deepseek-r1") === false)) { 
            /*  deepseek r1 need "reasoning_format" parameter: 
                parsed  - Separates reasoning into a dedicated field while keeping the response concise.
                raw     - Includes reasoning within <think> tags in the content.
                hidden  - Returns only the final answer for maximum efficiency. ! <think> tag is generated and only hidden, tokens are counted ! */
                $data['reasoning_format'] = "hidden";  
                //error_log(" deepseek-r1: " . print_r($data,false));
            }
        
        } else { // --- normal flow (not groq)
        
            $data = array(
                'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gpt-4o-mini',
                'messages' =>
                    $contextData
                ,
                'stream' => true,
                'max_completion_tokens'=>$MAX_TOKENS,
                'temperature' => ($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?: 1,
                'top_p' => ($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ?: 1,
                'response_format'=>["type"=>"json_object"]

            );

            
            if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
                $data["response_format"]=$GLOBALS["structuredOutputTemplate"];
            }

            // Mistral AI API does not support penalty params
            if (strpos($url, "mistral") === false) {
                $data["presence_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 0;
                $data["frequency_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ?: 0;
            }
      
        } // --- endif groq

        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==0) {
                unset($data["max_completion_tokens"]);
            } elseif (isset($customParms["MAX_TOKENS"])) {
                $data["max_completion_tokens"]=$customParms["MAX_TOKENS"]+0;
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==0) {
                unset($data["max_completion_tokens"]);
            } else
                $data["max_completion_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"]+0;
            
        }


        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}"
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: 30,
                "ignore_errors" => true
            )
        );

        $context = stream_context_create($options);
        
        $this->primary_handler = fopen($url, 'r', false, $context);
        if (!$this->primary_handler) {
            $error=error_get_last();
            error_log(print_r($error,true));

            if ($GLOBALS["db"]) {
                $GLOBALS["db"]->insert(
                'audit_request',
                    array(
                        'request' => json_encode($data),
                        'result' => $error["message"]
                    ));
            }
            return null;
        } else {
            // Get HTTP response code
            $response_info = stream_get_meta_data($this->primary_handler);
            $status_line = $response_info['wrapper_data'][0];
            preg_match('/\d{3}/', $status_line, $matches); // get three digits (200, 300, 404, etc)
            $status_code = isset($matches[0]) ? intval($matches[0]) : null;

            if ($status_code >= 300) {
                $response = stream_get_contents($this->primary_handler);
                $error_message = "Request to openaijson connector failed: {$status_line}.\nResponse body: {$response}";
                trigger_error($error_message, E_USER_WARNING);

                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                        array(
                            'request' => json_encode($data),
                            'result' => $error_message
                        ));
                }

                $this->close();
                $this->primary_handler=false;
                return null;
            } else  {
                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                    array(
                        'request' => json_encode($data),
                        'result' => "Ok"
                    ));
                }

            }
        }


        $this->_dataSent=json_encode($data);    // Will use this data in tokenizer.

        
        return true;

    }


    public function process()
    {
        global $alreadysent;

        static $numOutputTokens=0;

        if (!$this->primary_handler) {
            $line = "";
        } else {
            $line = fgets($this->primary_handler);
        }

        $buffer="";
        $totalBuffer="";
        $finalData="";
        $mangledBuffer="";
        
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);

        if ($this->_is_groq_com) { // --- exception for groq.com

            $data=json_decode($line, true);

            if (isset($data["choices"][0]["message"]["content"])) {
                $msg = trim($data["choices"][0]["message"]["content"]); 
                if (strlen($msg) > 0) {
                    $buffer .= $msg;
                    $this->_buffer .= $msg;
                    $this->_numOutputTokens += 1;
                }
                $totalBuffer .= $msg;
            }
     
        } else { // --- normal flow not groq.com

            $data=json_decode(substr($line, 6), true);

            if (isset($data["choices"][0]["delta"]["content"])) {
                if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                    $buffer.=$data["choices"][0]["delta"]["content"];
                    $this->_buffer.=$data["choices"][0]["delta"]["content"];
                    $this->_numOutputTokens += 1;

                }
                $totalBuffer.=$data["choices"][0]["delta"]["content"];
            }
        } // --- endif groq.com

        $buffer="";

        if (!empty($this->_buffer))
            $finalData=__jpd_decode_lazy($this->_buffer, true);
            if (is_array($finalData)) {
                
                
                if (isset($finalData[0])&& is_array($finalData[0]))
                    $finalData=$finalData[0];
                
                
                if (is_array($finalData)&&isset($finalData["message"])) {   // The infamous array response
                        if (is_array($finalData["message"]))
                            $finalData["message"]=implode(",",$finalData["message"]);
                }

                if (isset($finalData["message"])) {
                    if (is_array($finalData)&&isset($finalData["message"])) {
                        $mangledBuffer = str_replace($this->_extractedbuffer, "", $finalData["message"]);
                        $this->_extractedbuffer=$finalData["message"];
                        if (isset($finalData["listener"])) {
                            $GLOBALS["SCRIPTLINE_LISTENER"]=$finalData["listener"];
                        }
                        
                        if (isset($finalData["lang"])) {
                            $GLOBALS["LLM_LANG"]=$finalData["lang"];
                        }
                        
                        if (isset($finalData["mood"])) {
                            $GLOBALS["SCRIPTLINE_ANIMATION"]=GetAnimationHex($finalData["mood"]);
                            $GLOBALS["SCRIPTLINE_EXPRESSION"]=GetExpression($finalData["mood"]);
                        }
                        
                    }
                }
                
            } else
                $buffer="";
        
        return $mangledBuffer;
    }

    // Method to close the data processing operation
    public function close()
    {
        if ($this->primary_handler) {
            fclose($this->primary_handler);
        }
        
        //file_put_contents(__DIR__."/../log/ouput_from_llm.log",$this->_buffer, FILE_APPEND | LOCK_EX);
        file_put_contents(__DIR__."/../log/output_from_llm.log",date(DATE_ATOM)."\n=\n".$this->_buffer."\n=\n", FILE_APPEND);


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
            $parsedResponse=__jpd_decode_lazy($this->_buffer);   // USE JPD_LAZY?
            if (is_array($parsedResponse)) {
                if (!empty($parsedResponse["action"])) {
                    if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")])) {
                        
                        $functionDef=findFunctionByName($parsedResponse["action"]);
                        if ($functionDef) {
                            $functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                            if (@strlen($functionDef["parameters"]["required"][0])>0) {
                                if (!empty($parsedResponse["target"])) {
                                    $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@{$parsedResponse["target"]}\r\n";
                                }
                                else {
                                    error_log("Missing required parameter");
                                }
                                    
                            } else {
                                $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@{$parsedResponse["target"]}\r\n";
                            }
                        } elseif ($parsedResponse["action"] != "Talk") {
                            error_log("Function not found for {$parsedResponse["action"]}");
                        }
                        
                        //$functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                        //$this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n";
                        //echo "Herika|command|$functionCodeName@$parameter\r\n";
                        $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")]=end($this->_commandBuffer);
                    
                    } 
                        
                }
                
                @ob_flush();    
            } else {
                error_log("No actions");
                return null;
            }
        }

        return $this->_commandBuffer;
    }

    public function isDone()
    {
        return !$this->primary_handler || feof($this->primary_handler);
    }

}
