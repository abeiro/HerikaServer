<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class google_openaijson
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
    private $_is_nanogpt_com;
    private $_is_x_ai;
    private $_is_mistral_ai;
    private $_is_cohere_ai;
    private $_is_streaming;
    private $_is_reasoning;
    private $_is_grok;
    private $_is_openai;
    private $_model;
    private $_url;
    private $_remove_cot;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;
    public $_extractedbuffer;

    public function __construct()
    {
        $this->name="google_openaijson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        require_once(__DIR__."/__jpd.php");
    }


     private function init_connector($customParms) {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        $this->_model =  (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gemini-1.5-flash';

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

        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('GOOGLE_OPENAIJSON');
        }
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
            //$prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        }
        
        if (strpos($GLOBALS["HERIKA_PERS"],"#SpeechStyle")!==false) {
            $speechReinforcement="Use #SpeechStyle.";
        } else
            $speechReinforcement="";

        $contextData[]=[
            'role' => 'user',
            'content' => "{$prefix}. $speechReinforcement Use this JSON object to give your answer: ".json_encode($GLOBALS["responseTemplate"])
        ];

        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].=$GLOBALS["COMMAND_PROMPT"];
        }

        $pb=[];
        $pb["user"]="";
        
        $contextDataOrig=array_values($contextData);
        $lastrole="";
        $assistantRoleBuffer="";
        foreach ($contextDataOrig as $n=>$element) {
            
            if (!is_array($element)) {
                Logger::debug("$n=>$element was not an array");
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
                        Logger::debug("Empty element[content]".__FILE__." ".__LINE__);
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
                        if (!is_array($localArguments)) {
                            $localArguments = [];
                        }
                        $actionTargetValue = herikaExtractActionArgumentTargetValue($localArguments);
                        $lastAction=herikaFormatReturnMessageTemplate($localFuncCodeName, $localArguments);
                        
                        $contextDataCopy[]=[
                                "role"=>"assistant",
                                "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\",\"action\": \"$lastActionName\",\"target\": \"".$actionTargetValue."\", \"message\": \"\"}"
                            ];
                            
                        $gameRequestCopy=$GLOBALS["gameRequest"];    
                        $gameRequestCopy[3]="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\",\"action\": \"$lastActionName\", \"target\": \"".$actionTargetValue."\", \"message\": \"\"}";
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
                            
                           
                             $GLOBALS["PATCH_STORE_FUNC_RES_ACTION"] = $localFuncCodeName;
                             if (strpos($element["content"],"Error")===0) {
                                $GLOBALS["PATCH_STORE_FUNC_RES"]="{$GLOBALS["HERIKA_NAME"]} issued ACTION, but {$element["content"]}";
                                $contextDataCopy[]=[
                                    "role"=>"user",
                                    "content"=>chimBuildNarratorContextLine("({$GLOBALS["HERIKA_NAME"]} used action $lastActionName). {$GLOBALS["PATCH_STORE_FUNC_RES"]}")
                                    
                                ];
                            } else {
                                
                                $GLOBALS["PATCH_STORE_FUNC_RES"]=strtr($lastAction,["#RESULT#"=>$element["content"]]);
                                $contextDataCopy[]=[
                                    "role"=>"user",
                                    "content"=>chimBuildNarratorContextLine("({$GLOBALS["HERIKA_NAME"]} used action $lastActionName). {$GLOBALS["PATCH_STORE_FUNC_RES"]}"),
                                    
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
        

        // Forcing JSON output
        
        
        
        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gemini-1.5-flash',
            'messages' =>
                $contextData
            ,
            'stream' => true,
            'max_completion_tokens'=>$MAX_TOKENS,
            'temperature' => ($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?: 1,
            'top_p' => ($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ?: 0.95,
            'response_format'=>["type"=>"json_object"]

        );

        
        if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
            $data["response_format"]=$GLOBALS["structuredOutputTemplate"];
        }  

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
            Logger::error(print_r($error,true));

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
                $error_message = "Request to google_openaijson connector failed: {$status_line}.\nResponse body: {$response}";
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
                            // Sanitize language code - remove extra chars from LLM parsing artifacts
                            $GLOBALS["LLM_LANG"]=preg_replace('/[^a-z\-]/i', '', strtolower(trim($finalData["lang"])));
                        }
                        
                        if (isset($finalData["mood"])) {
                            $finalData["mood"] = extractFirstEmoteMood($finalData["mood"]);
                            $GLOBALS["SCRIPTLINE_ANIMATION"]=GetAnimationHex($finalData["mood"]);
                            $GLOBALS["SCRIPTLINE_EXPRESSION"]=GetExpression($finalData["mood"]);
                        }
                        
                        // Store the entire response for TTS systems that need additional data like emotions
                        $GLOBALS["LAST_LLM_RESPONSE"] = $finalData;
                        
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
        
        // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log","{$this->_buffer}\n\n".date(DATE_ATOM)." END\n==\n", FILE_APPEND);
        return $this->_buffer;
    }

    // Method to close the data processing operation
    public function processActions()
    {
        global $alreadysent;

        if ($this->_functionName) {
            $parameterArr = json_decode($this->_parameterBuff, true);
            if (is_array($parameterArr)) {
                $parameter = $parameterArr;
                $functionCodeName = getFunctionCodeName($this->_functionName);
                $parameter = buildFunctionExecutionParameter($functionCodeName, $parameter);
                $commandStr = "{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@$parameter\r\n";

                if (!isset($alreadysent[md5($commandStr)])) {
                    $this->_commandBuffer[] = $commandStr;
                    //echo "Herika|command|$functionCodeName@$parameter\r\n";

                }

                $alreadysent[md5($commandStr)] = $commandStr;
                if (ob_get_level()) @ob_flush();
            } else 
                return null;
        } else {
            $GLOBALS["DEBUG_DATA"]["RAW"]=$this->_buffer;
            $parsedResponse=__jpd_decode_lazy($this->_buffer);   // USE JPD_LAZY?
            if (is_array($parsedResponse)) {
                if (!empty($parsedResponse["action"])) {
                    $executionContext = buildFunctionExecutionContextFromResponse($parsedResponse);
                    queueFunctionExecutionCommand($this->_commandBuffer, $alreadysent, $executionContext, "google_openaijson");
                }
                
                if (ob_get_level()) @ob_flush();
            } else {
                Logger::info("No actions");
                return null;
            }
        }

        return $this->_commandBuffer;
    }

    public function fast_request($contextData, $customParms)
    {
        
        $this->init_connector($customParms);

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('GOOGLE_OPENAIJSON_FAST');
        }
        

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);

        if ($this->_is_groq_com) { // --- exception made for groq.com

            if ($temperature < 0.000001) $temperature = 0.000001; // groq.com want this > 1e-8, never 0.0

            if ($this->_is_reasoning) { 
            /*  a reasoning model need "reasoning_format" parameter: 
                parsed  - Separates reasoning into a dedicated field while keeping the response concise.
                raw     - Includes reasoning within <think> tags in the content.
                hidden  - Returns only the final answer for maximum efficiency. ! <think> tag is generated and only hidden, tokens are counted ! */
                $data['reasoning_format'] = "hidden";  
            }
            //error_log(" dbg resoning: " . var_export($this->_is_reasoning, true) . " - " . var_export($data, true));
        
        } else { // --- normal flow (not groq)
        
            if ($this->_is_x_ai) {
                unset($data["presence_penalty"]); 
                unset($data["frequency_penalty"]);
            } elseif ($this->_is_mistral_ai) {
                //unset($data["presence_penalty"]); 
                //unset($data["frequency_penalty"]);
                unset($data["max_completion_tokens"]);
                $data['max_tokens'] = $MAX_TOKENS;
            } elseif ($this->_is_cohere_ai) {
                unset($data["max_completion_tokens"]);
                $data['max_tokens'] = $MAX_TOKENS;
            } 

            if (($this->_is_reasoning) && (!$this->_is_mistral_ai) && (!$this->_is_cohere_ai)) { // there is no rule accepted by all providers
                $data["chat_format"]="tidy"; 
                $data["reasoning_effort"] = "low";
                $data['reasoning_format'] = "hidden";
                if (!(stripos($this->_model, "qwen3-") === false)) //qwen3
                    $data["enable_thinking"] = false;
            }

      
        } // --- endif provider
       

        if (isset($customParms["MAX_TOKENS"])) {
            $MAX_TOKENS=intval($customParms["MAX_TOKENS"]);
            unset($customParms["MAX_TOKENS"]);
        }
        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            $MAX_TOKENS=intval($GLOBALS["FORCE_MAX_TOKENS"]);
        }
        
        $data = array(
            'model' => $this->_model,
            'messages' => $contextData,
            'stream' => false, 
            'max_completion_tokens' => $MAX_TOKENS
        );


        if (isset($GLOBALS["CONNECTOR"][$this->name]["temperature"])) {
            $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 0.7);
            if ($temperature < 0.0) $temperature = 0.0;
            else if ($temperature > 2.0) $temperature = 2.0; 

            $data["temperature"]=$temperature;
        }

        /*
        if (isset($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"])) {
            $presence_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ? : 0.0);
            if ($presence_penalty < -2.0) $presence_penalty = -2.0;
            else if ($presence_penalty > 2.0) $presence_penalty = 2.0; 
            $data["presence_penalty"] = $presence_penalty;
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"])) {
            $frequency_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ? : 0.0);
            if ($frequency_penalty < -2.0) $frequency_penalty = -2.0;
            else if ($frequency_penalty > 2.0) $frequency_penalty = 2.0;
            $data["frequency_penalty"] = $frequency_penalty;
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"])) {
            $repetition_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]) ? : 0.0);
            if ($repetition_penalty < 0.0) $repetition_penalty = 0.0;
            else if ($repetition_penalty > 2.0) $repetition_penalty = 2.0;
            $data["repetition_penalty"] = $repetition_penalty;
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["top_p"])) {
            $top_p = floatval(($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? : 1.0);
            if ($top_p > 1) $top_p = 1.0;
            else if ($top_p < 0.0) $top_p = 0.0;
            $data["top_p"] = $top_p;
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["min_p"])) {
            $min_p = floatval(($GLOBALS["CONNECTOR"][$this->name]["min_p"]) ? : 0.0);
            if ($min_p > 1) $min_p = 1.0;
            else if ($min_p < 0.0) $min_p = 0.0;
            $data["min_p"] = $min_p;
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["top_a"])) {
            $top_a = floatval(($GLOBALS["CONNECTOR"][$this->name]["top_a"]) ? : 0.0);
            if ($top_a > 1) $top_a = 1.0;
            else if ($top_a < 0.0) $top_a = 0.0;
            $data["top_a"] = $top_a;
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["top_k"])) {
            $top_k = intval(($GLOBALS["CONNECTOR"][$this->name]["top_k"]) ? : 0);
            if ($top_k < 0) $top_k = 0;
            $data["top_k"] = $top_k;
        } 

        if (isset($GLOBALS["CONNECTOR"][$this->name]["stop"])&&sizeof($GLOBALS["CONNECTOR"][$this->name]["stop"])>0) {
            $data["stop"]=$GLOBALS["CONNECTOR"][$this->name]["stop"];
        }
        // Override

       */

        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==0) {
                unset($data["max_completion_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_completion_tokens"]=$customParms["MAX_TOKENS"];
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==0) {
                unset($data["max_completion_tokens"]);
            } else {
                $data["max_completion_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"];

            }
        }
        
        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
                $data[$k]=$v;
        }


        foreach ($customParms as $parm=>$value) {
            $data[$parm]=$value;
        }

        $GLOBALS["DEBUG_DATA"]["full"]=($data);
     
        $data["max_completion_tokens"]+=0;
        
        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}",
            "HTTP-Referer:  https://dwemerdynamics.com/",
            "X-Title: Dwemer Dynamics"
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
        
        file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $json_response=file_get_contents($this->_url, false, $context);
        file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\n{$json_response}\n=\n", FILE_APPEND);

        if ($json_response) {
            $text_response=json_decode($json_response,true);
            if (is_valid_array($text_response)) {
               
                return $text_response["choices"][0]["message"]["content"];    
            }
            else {
                log_msg("Error in openai request '$url':$json_response", 3);
                return "";
                
            }
            
        }
            


    }

    public function setDone()
    {
        $this->_forcedClose=true;
        
    }

    public function isDone()
    {
        return !$this->primary_handler || feof($this->primary_handler);
    }

}
