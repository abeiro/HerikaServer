<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");

class groqjson
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
    private $_is_streaming;
    private $_is_reasoning;
    private $_is_openai_model;
    private $_model;
    private $_url;
    private $_remove_cot;
    private $_disable_reasoning;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;
    public $_extractedbuffer;
    private $_lastStreamedObject;

    public function __construct()
    {
        $this->name="groqjson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        $this->_is_streaming=false; // Groq doesn't support streaming with JSON mode
        $this->_is_reasoning=false;
        $this->_is_openai_model=false;
        $this->_model="";
        $this->_url="";
        $this->_remove_cot=false; // Groq handles reasoning internally with reasoning_format=hidden
        $this->_disable_reasoning=false;
        $this->_cot_tag_base="think";
        $this->_output_buffer="";
        $this->_timeout=30;
        require_once(__DIR__."/__jpd.php");
    }

    private function isReasoningModel($s_model) {
        $b_res = false;
        if (strlen($s_model) > 0) {
            // Groq reasoning models
            $i_pos = stripos($s_model, "deepseek-r");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwq-32b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwq-max");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-120b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-20b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, ":thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-reasoning");

            $b_res = (!($i_pos === false));
        }
        error_log("[GROQ] is reasoning $s_model / $i_pos ". ($b_res ? "Y" : "N") );
        return $b_res;
    }

    private function isOpenAIModel($s_model="") {
        // Check if this is an OpenAI o-series reasoning model on Groq
        $b_res = false;
        if (strlen($s_model) > 0) {
            $i_pos = stripos($s_model, "gpt-oss-120b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-20b");
            
            $b_res = (!($i_pos === false));
        }
        error_log("[GROQ] is openai model $s_model / $i_pos ". ($b_res ? "Y" : "N") );
        return $b_res;
    }

    private function init_connector($customParms) {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        $this->_remove_cot = ($GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"] ?? false);
        $this->_disable_reasoning = ($GLOBALS["CONNECTOR"][$this->name]["disable_model_reasoning"] ?? false);

        $default_model = 'llama-3.3-70b-versatile';

        $this->_model = $GLOBALS["CONNECTOR"][$this->name]["model"] ?? $default_model;
        // Allow model override from custom parameters
        $this->_model = isset($customParms["model"]) ? $customParms["model"] : $this->_model;
        
        $this->_is_openai_model = $this->isOpenAIModel($this->_model);

        $this->_is_reasoning = $GLOBALS["CONNECTOR"][$this->name]["reasoning_model"] ?? false;  
        if (!$this->_is_reasoning)
            $this->_is_reasoning = $this->isReasoningModel($this->_model);
        $this->_timeout = ($this->_is_reasoning) ? 90 : 30;
    }
    
    public function open($contextData, $customParms)
    {
        $this->init_connector($customParms);

        $MAX_TOKENS=intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48));

        // Memory embedding handling
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
            chimEnsureNarratorJsonResponseState('GROQJSON');
        }
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
        }
        
        $b_speech_style = (
            (isset($GLOBALS["HERIKA_SPEECHSTYLE"]) && (!empty($GLOBALS["HERIKA_SPEECHSTYLE"]))) || 
            (stripos($GLOBALS["HERIKA_PERS"],"#SpeechStyle")!==false)
        );
        if ($b_speech_style) {
            $speechReinforcement="Check reference #SpeechStyle.";
        } else
            $speechReinforcement="";
        
        $contextData[]=[
            'role' => 'user',
            'content' => "{$prefix}. $speechReinforcement \nUse only this JSON object to give your answer and do not send any other characters outside of this JSON structure: \n".json_encode($GLOBALS["responseTemplate"],JSON_PRETTY_PRINT)
        ];
    
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].=$GLOBALS["COMMAND_PROMPT"];
        }

        $pb=[];
        $pb["user"]="";
        $pb["system"]=""; 
        
        $contextDataOrig=array_values($contextData);
        $n_last_context = count($contextDataOrig) - 1;
        $lastrole="";
        $assistantRoleBuffer="";
        
        foreach ($contextDataOrig as $n=>$element) {
            
            if (!is_array($element)) {
                Logger::debug("Warning: $n=>$element was not an array");
                continue;
            }

            if (($n >= $n_last_context) && ($element["role"] != "tool")) {
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
                    } else
                        $contextDataCopy[]=$element;
                    
                    $pb["system"].=trim($element["content"])."\n";
                    
                } else if ($element["role"]=="assistant") {
                    $assistantAppearedInhistory=true;
                    $dialogueTarget=extractDialogueTarget($element["content"]) ?? [];
                    if (isset($element["tool_calls"])) {
                        $pb["system"].="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$element["tool_calls"][0]["function"]["name"]}";
                        $lastAction="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$element["tool_calls"][0]["function"]["name"]} {$element["tool_calls"][0]["function"]["arguments"]}, #RESULT#";
                        $lastActionName=$element["tool_calls"][0]["function"]["name"];
                        $localFuncCodeName=getFunctionCodeName($element["tool_calls"][0]["function"]["name"]);
                        $localArguments=json_decode($element["tool_calls"][0]["function"]["arguments"],true);
                        if (!is_array($localArguments)) {
                            $localArguments = [];
                        }
                        $actionTargetValue = herikaExtractActionArgumentTargetValue($localArguments);
                        if (isset($GLOBALS["F_RETURNMESSAGES"][$localFuncCodeName])) {
                            $lastAction=herikaFormatReturnMessageTemplate($localFuncCodeName, $localArguments);
                        }
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
                            $pb["system"].=$element["content"]."\n";
                            $assistantRoleBuffer.=$dialogueTarget["cleanedString"];                                
                            $lastTargetBuffer=$dialogueTarget["target"];
                            unset($contextData[$n]);
                        }
                    }
                    
                } else if ($element["role"]=="tool") {
                    
                        if (!empty($element["content"])) {
                            $pb["system"].=$element["content"]."\n";
                            
                            $GLOBALS["PATCH_STORE_FUNC_RES_ACTION"] = $localFuncCodeName;
                            if (stripos($element["content"],"error")===0) {
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
                        }
                            
                }
                
            }
            
            $lastrole=$element["role"];
        }
        
        $contextData=$contextDataCopy;

        // Compact and remove context elements with empty content
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) {
            if (!empty($element["content"])) {
                $contextDataCopy[]=$element;
            }
        }
        
        if ((isset($GLOBALS["CONNECTOR"][$this->name]["PREFILL_JSON"])) && ($GLOBALS["CONNECTOR"][$this->name]["PREFILL_JSON"])) {
            $GLOBALS["PATCH"]["PREAPPEND"]="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\",";
            $contextDataCopy[]=["role"=>"assistant","content"=>$GLOBALS["PATCH"]["PREAPPEND"]];
        }
        
        $contextData=$contextDataCopy;
        
        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 1.0);
        // Groq requirement: temperature must be > 1e-8
        if ($temperature < 0.000001) $temperature = 0.000001;
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

        if (isset($customParms["MAX_TOKENS"])) {
            $MAX_TOKENS=intval($customParms["MAX_TOKENS"]); 
            unset($customParms["MAX_TOKENS"]);
        }
        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            $MAX_TOKENS=intval($GLOBALS["FORCE_MAX_TOKENS"]);
        }

        // Build request data - Groq uses OpenAI format
        $data = array(
            'model' => $this->_model,
            'messages' => $contextData,
            'stream' => $this->_is_streaming, 
            'max_completion_tokens' => $MAX_TOKENS,
            'temperature' => $temperature, 
            'top_p' => $top_p, 
            'presence_penalty' => $presence_penalty, 
            'frequency_penalty' => $frequency_penalty, 
            'response_format'=>["type"=>"json_object"]
        );

        // Handle reasoning models
        if ($this->_is_reasoning) { 
            /* Groq reasoning model parameters:
               - reasoning_format: parsed/raw/hidden
               - reasoning_effort: low/medium/high (GPT-OSS models only)
            */
            if ($this->_is_openai_model) {
                $data['include_reasoning'] = false;
                if ($this->_disable_reasoning)
                    $data['reasoning_effort'] = "low";
            } else {
                $data['reasoning_format'] = "hidden";  
            }
        }
        
        // Groq doesn't support json_schema response format on most models
        // Keep using json_object for compatibility
        if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
            // Note: json_schema is not widely supported on Groq, so we stick with json_object
            // Only specific models support structured outputs: https://console.groq.com/docs/structured-outputs#supported-models
            error_log("[GROQ] json_schema setting is enabled but not supported on most Groq models - using json_object instead");
        }

        if ($MAX_TOKENS<1) {
            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 
        }

        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
                $data[$k]=$v;
        }

        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}"
        );

        $timeout = max(intval(($GLOBALS["HTTP_TIMEOUT"]) ?? 30), $this->_timeout);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => $timeout,
                "ignore_errors" => true
            )
        );

        $context = stream_context_create($options);
        
        $this->primary_handler = fopen($this->_url, 'r', false, $context);
        if (!$this->primary_handler) {
            $error=error_get_last();
            Logger::error(trim(print_r($error,true)));

            if ($GLOBALS["db"]) {
                $GLOBALS["db"]->insert(
                'audit_request',
                    array(
                        'request' => json_encode($data),
                        'result' => $error["message"],
                        'connector'=>$this->name,
                        'url'=>$this->_url
                    ));
            }
            return null;
        } else {
            // Get HTTP response code
            $response_info = stream_get_meta_data($this->primary_handler);
            $status_line = $response_info['wrapper_data'][0];
            preg_match('/\d{3}/', $status_line, $matches);
            $status_code = isset($matches[0]) ? intval($matches[0]) : 0;

            if ($status_code >= 300) {
                $response = stream_get_contents($this->primary_handler);
                $error_message = "Request to groqjson connector failed: {$this->_url} {$status_line}.\n Response body: {$response}.\n model: {$this->_model}";
                trigger_error($error_message, E_USER_WARNING);

                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                        array(
                            'request' => json_encode($data),
                            'result' => $error_message,
                            'connector'=>$this->name,
                            'url'=>$this->_url
                        ));
                }

                $this->close();
                $this->primary_handler=false;
                return null;
            }
        }

        $this->_dataSent=json_encode($data);

        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." START\n\n", FILE_APPEND);        
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

        // Groq doesn't stream with JSON mode, so we get the full response
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

        if (isset($data["usage"])) 
            $this->_lastStreamedObject=$data;     

        $buffer="";

        if (!empty($this->_buffer))
            $finalData=__jpd_decode_lazy($this->_buffer, true);
            if (is_array($finalData)) {
                
                if (isset($finalData[0])&& is_array($finalData[0]))
                    $finalData=$finalData[0];
                
                if (is_array($finalData)&&isset($finalData["message"])) {
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
                        if (isset($finalData["target"]) && !empty($finalData["target"]) && $finalData["action"]=="Talk") {
                            $GLOBALS["SCRIPTLINE_LISTENER"]=$finalData["target"];
                        }
                        
                        if (isset($finalData["lang"])) {
                            $GLOBALS["LLM_LANG"]=preg_replace('/[^a-z\-]/i', '', strtolower(trim($finalData["lang"])));
                        }
                        
                        if (isset($finalData["mood"])) {
                            $finalData["mood"] = extractFirstEmoteMood($finalData["mood"]);
                            $GLOBALS["SCRIPTLINE_ANIMATION"]=GetAnimationHex($finalData["mood"]);
                            $GLOBALS["SCRIPTLINE_EXPRESSION"]=GetExpression($finalData["mood"]);
                        }
                        
                        $GLOBALS["LAST_LLM_RESPONSE"] = $finalData;
                        
                    }
                }
                
            } else
                $buffer="";
        
        return $mangledBuffer;
    }

    public function close($callName='')
    {
        if ($this->primary_handler) {
            fclose($this->primary_handler);
        }

        if (empty($callName))
            $callName=$this->name;
        else
            $callName=$this->name."/".$callName;

        $json_response=$this->_lastStreamedObject;

        if ($json_response) {
                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                        array(
                            'request' => json_encode($this->_dataSent),
                            'result' => "Ok",
                            'usage'=>json_encode($json_response["usage"]),
                            'connector'=>$callName,
                            'url'=>$this->_url
                        ));
                }
                
        }
        else {
                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                        array(
                            'request' => json_encode($this->_dataSent),
                            'result' => (!empty($this->_buffer))?"Ok":"ERROR|INVALID JSON RESPONSE",
                            'connector'=>$this->name,
                            'url'=>$this->_url
                        ));
                }
        }
        
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n"."\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);

        return $this->_buffer;
        
    }

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
                }

                $alreadysent[md5($commandStr)] = $commandStr;
                if (ob_get_level()) @ob_flush();
            } else 
                return null;
        } else {
            $GLOBALS["DEBUG_DATA"]["RAW"]=$this->_buffer;
            $parsedResponse=__jpd_decode_lazy($this->_buffer);
            if (is_array($parsedResponse)) {
                if (!empty($parsedResponse["action"])) {
                    if (!isset($parsedResponse["target"]))    
                        $parsedResponse["target"] = "";

                    $executionContext = buildFunctionExecutionContextFromResponse($parsedResponse);
                    queueFunctionExecutionCommand($this->_commandBuffer, $alreadysent, $executionContext, "groqjson");
                }
                
                if (ob_get_level()) @ob_flush();
            } else {
                Logger::info("No actions");
                return null;
            }
        }

        return $this->_commandBuffer;
    }

    public function isDone()
    {
        return !$this->primary_handler || feof($this->primary_handler);
    }

    public function fast_request($contextData, $customParms,$callName='')
    {
        $this->init_connector($customParms);

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('GROQJSON_FAST');
        }
        
        if (empty($callName))
            $callName=$this->name;
        else
            $callName=$this->name."/".$callName;

        $MAX_TOKENS=intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 512));

        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 0.7);
        // Groq requirement: temperature must be > 1e-8
        if ($temperature < 0.000001) $temperature = 0.000001;
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
            'max_completion_tokens' => $MAX_TOKENS,
            'temperature' => $temperature, 
            'top_p' => $top_p, 
            'presence_penalty' => $presence_penalty, 
            'frequency_penalty' => $frequency_penalty
        );

        if ($this->_is_reasoning) { 
            if ($this->_is_openai_model) {
                $data['include_reasoning'] = false;
                if ($this->_disable_reasoning)
                    $data['reasoning_effort'] = "low";
            } else {
                $data['reasoning_format'] = "hidden";  
            }
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["stop"])&&sizeof($GLOBALS["CONNECTOR"][$this->name]["stop"])>0) {
            $data["stop"]=$GLOBALS["CONNECTOR"][$this->name]["stop"];
        }

        if ($MAX_TOKENS<1) {
            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 
        }

        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
                $data[$k]=$v;
        }

        $GLOBALS["DEBUG_DATA"]["full"]=($data);
     
        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}",
            "HTTP-Referer:  https://dwemerdynamics.com/",
            "X-Title: Dwemer Dynamics"
        );
        
        $timeout = max(intval(($GLOBALS["HTTP_TIMEOUT"]) ?? 30), $this->_timeout);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => $timeout
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
                log_msg("Error in groq request '$url':$json_response", 3);
                return "";
            }
        }
    }

    public function setDone()
    {
        $this->_forcedClose=true;
    }

}

