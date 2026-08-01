<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");

class player2json
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
    private $_model;
    private $_url;
    private $_game_key;
    private $_remove_cot;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;
    public $_extractedbuffer;

    public function __construct()
    {
        $this->name="player2json";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        $this->_is_groq_com=false;
        $this->_is_nanogpt_com=false;
        $this->_is_x_ai=false;
        $this->_is_mistral_ai=false;
        $this->_is_cohere_ai=false;
        $this->_is_streaming=true;
        $this->_is_reasoning=false;
        $this->_model="";
        $this->_url="";
        $this->_game_key="CHIM";
        $this->_remove_cot=true;
        $this->_cot_tag_base="think";
        $this->_output_buffer="";
        $this->_timeout=30;
        require_once(__DIR__."/__jpd.php");
    }

    private function isReasoningModel($s_model) {
        $b_res = false;
        if (strlen($s_model) > 0) {
            $i_pos = stripos($s_model, "deepseek-r");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwq-32b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwq-max");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "aion-1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "grok-3-mini");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, ":thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-reasoning");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "MAI-DS-R1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwen3-235b-a22b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwen3-30b-a3b");
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function normalizeConnectorUrl($url) {
        $url = trim((string)$url);
        if ($url === "") {
            return "http://127.0.0.1:4315/v1/chat/completions";
        }

        if (preg_match('#^https?://[^/]+/?$#i', $url)) {
            return rtrim($url, "/") . "/v1/chat/completions";
        }

        if (preg_match('#/docs/?$#i', $url)) {
            return preg_replace('#/docs/?$#i', '/v1/chat/completions', $url);
        }

        if (preg_match('#/v1/?$#i', $url)) {
            return rtrim($url, "/") . "/chat/completions";
        }

        return $url;
    }

    private function buildHeaders() {
        return array(
            'Content-Type: application/json',
            'player2-game-key: ' . $this->_game_key
        );
    }

    private function buildRequestPayload($contextData, $customParms, $forceJson = true) {
        $maxTokens = intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 250));
        if (isset($customParms["MAX_TOKENS"])) {
            $maxTokens = intval($customParms["MAX_TOKENS"]);
            unset($customParms["MAX_TOKENS"]);
        }
        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            $maxTokens = intval($GLOBALS["FORCE_MAX_TOKENS"]);
        }

        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?? 1.0);
        if ($temperature < 0.0) {
            $temperature = 0.0;
        } else if ($temperature > 2.0) {
            $temperature = 2.0;
        }

        $data = array(
            'messages' => $contextData,
            'stream' => false,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        );

        if ($forceJson) {
            if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"] && isset($GLOBALS["structuredOutputTemplate"])) {
                $data["response_format"] = $GLOBALS["structuredOutputTemplate"];
            } else if (!isset($GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) || $GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) {
                $data["response_format"] = array("type" => "json_object");
            }
        }

        foreach ($customParms as $k => $v) {
            $data[$k] = $v;
        }

        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
            $data[$k] = $v;
        }

        if ($maxTokens < 1) {
            unset($data["max_tokens"]);
        }

        return $data;
    }

    private function init_connector() {
        $this->_url = $this->normalizeConnectorUrl((isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "");
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        // Check for hardcoded IP address override in conf_opts table
        if (isset($GLOBALS["db"]) && is_object($GLOBALS["db"])) {
            try {
                $hostIpRecord = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id='Network/HOST_IP'");
                if (!empty($hostIpRecord) && !empty(trim($hostIpRecord[0]['value']))) {
                    $hardcodedIp = trim($hostIpRecord[0]['value']);
                    
                    // Parse the original URL and replace the host with hardcoded IP
                    $urlParts = parse_url($this->_url);
                    
                    if ($urlParts !== false && isset($urlParts['scheme']) && isset($urlParts['host'])) {
                        // Reconstruct URL with hardcoded IP
                        $newUrl = $urlParts['scheme'] . '://' . $hardcodedIp;
                        
                        // Handle port - use explicit port or default based on scheme
                        if (isset($urlParts['port']) && !empty($urlParts['port'])) {
                            $newUrl .= ':' . $urlParts['port'];
                        } elseif ($urlParts['scheme'] === 'https') {
                            $newUrl .= ':443';
                        } elseif ($urlParts['scheme'] === 'http') {
                            $newUrl .= ':80';
                        }
                        
                        // Add path
                        if (isset($urlParts['path']) && !empty($urlParts['path'])) {
                            $newUrl .= $urlParts['path'];
                        }
                        
                        // Add query string
                        if (isset($urlParts['query']) && !empty($urlParts['query'])) {
                            $newUrl .= '?' . $urlParts['query'];
                        }
                        
                        // Validate the reconstructed URL
                        if (filter_var($newUrl, FILTER_VALIDATE_URL)) {
                            // Test the connection with the new URL
                            $testContext = stream_context_create([
                                'http' => [
                                    'method' => 'GET',
                                    'timeout' => 5,
                                    'ignore_errors' => true
                                ]
                            ]);
                            
                            $testHandle = @fopen($newUrl, 'r', false, $testContext);
                            if ($testHandle !== false) {
                                fclose($testHandle);
                                $this->_url = $newUrl;
                            } else {
                                Logger::warn("{$this->name} connector - Failed to connect to hardcoded IP {$hardcodedIp}, falling back to original URL: {$this->_url}");
                            }
                        } else {
                            Logger::warn("{$this->name} connector - Invalid reconstructed URL, falling back to original");
                        }
                    } else {
                        Logger::warn("{$this->name} connector - Failed to parse original URL, using original: {$this->_url}");
                    }
                }
            } catch (Exception $e) {
                Logger::warn("{$this->name} connector - Error checking Network/HOST_IP: " . $e->getMessage());
            }
        }

        $this->_model = (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? trim((string)$GLOBALS["CONNECTOR"][$this->name]["model"]) : "";
        $this->_game_key = trim((string)($GLOBALS["CONNECTOR"][$this->name]["player2_game_key"] ?? $GLOBALS["CONNECTOR"][$this->name]["API_KEY"] ?? "CHIM"));
        if ($this->_game_key === "") {
            $this->_game_key = "CHIM";
        }
        $this->_is_streaming = false; 

        require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "player2_health.php");
        chimPlayer2HealthMarkUsed($this->_url);

    }
    
    public function open($contextData, $customParms)
    {
        $this->init_connector();

        $MAX_TOKENS=intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48));



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
            chimEnsureNarratorJsonResponseState('PLAYER2JSON');
        }
        
        
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

        if ($this->_is_groq_com) { // --- exception made for groq.com - JSON need pretty print
            $contextData[]=[
                'role' => 'user',
                'content' => "{$prefix}. $speechReinforcement Use only this JSON object to give your answer and do not send any other characters outside of this JSON structure: ".json_encode($GLOBALS["responseTemplate"],JSON_PRETTY_PRINT) 
            ];
        } else {
            $contextData[]=[
                'role' => 'user',
                'content' => "{$prefix}. $speechReinforcement Use only this JSON object to give your answer and do not send any other characters outside of this JSON structure: ".json_encode($GLOBALS["responseTemplate"])
            ];
        }
    
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].=$GLOBALS["COMMAND_PROMPT"];
        }

        $pb=[];
        $pb["user"]="";
        $pb["system"]=""; 
        
        $contextDataOrig=array_values($contextData);
        $lastrole="";
        $assistantRoleBuffer="";
        foreach ($contextDataOrig as $n=>$element) {
            
            if (!is_array($element)) {
                Logger::debug("Warning: $n=>$element was not an array");
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
                    // We should start chaging this to role=>"developer"
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
                    $dialogueTarget=extractDialogueTarget($element["content"]) ?? "none"; // moved here to be available in tool_calls
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
                            //error_log("#### ".$element["content"]);
                            $pb["system"].=$element["content"]."\n";
                            //$dialogueTarget=extractDialogueTarget($element["content"]); // moved up
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

        $data = $this->buildRequestPayload($contextData, $customParms, true);

        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $headers = $this->buildHeaders();
        
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: $this->_timeout,
                "ignore_errors" => true
            )
        );

        $context = stream_context_create($options);
        
        // Add delay to prevent rate limiting (100ms)
        usleep(100000);
        
        $this->primary_handler = fopen($this->_url, 'r', false, $context);
        if (!$this->primary_handler) {
            $error=error_get_last();
            Logger::error(trim(print_r($error,true)));

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
                $error_message = "Request to player2json connector failed: {$status_line}.\n Response body: {$response}.\n model: {$this->_model}";
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

        if (!$this->_is_streaming) { // --- not streaming, catch all 

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
     
        } else { // --- normal streaming flow 

            $data=json_decode(substr($line, 6), true);

            if (isset($data["choices"][0]["delta"]["content"])) {
                if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                    $clean_content = $this->removeChainOfThought($data["choices"][0]["delta"]["content"]); // remove CoT tags and thinking content
                    if (strlen($clean_content) > 0) {
                        $buffer .= $clean_content;
                        $this->_buffer .= $clean_content;
                        $this->_numOutputTokens += 1;
                    }
                }
                $totalBuffer.=$data["choices"][0]["delta"]["content"];
            }
        } // --- endif is_streaming 

        // process any remaining reasoning content on stream completion
        if (isset($data["choices"][0]["finish_reason"]) && $data["choices"][0]["finish_reason"] !== null) {
            if (!empty($this->_output_buffer)) {
                $clean_remain = $this->removeChainOfThought("");
                if (!empty($clean_remain)) {
                    $buffer .= $clean_remain;
                    $this->_buffer .= $clean_remain;
                }
                $this->_output_buffer = ""; // clear the buffer
            }
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
                        if (isset($finalData["target"]) && !empty($finalData["target"]) && $finalData["action"]=="Talk") {
                            // Cover the case where action is talk, and LLM hast pointed a target
                            $GLOBALS["SCRIPTLINE_LISTENER"]=$finalData["target"];
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
        
        // process any remaining content in the reasoning buffer before closing
        if ($this->_is_reasoning && !empty($this->_output_buffer)) {
            // need another pass to clean up any remaining CoT tags
            $pattern = '/<{$this->_cot_tag_base}>.*?<\/{$this->_cot_tag_base}>/is';
            $cleaned_buffer = preg_replace($pattern, '', $this->_output_buffer);
            $this->_buffer .= $cleaned_buffer;
            $this->_output_buffer = "";
        }
        
        if ($this->primary_handler) {
            fclose($this->primary_handler);
        }
        
        // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);

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
                    if (!isset($parsedResponse["target"]))    
                        $parsedResponse["target"] = "";

                    $executionContext = buildFunctionExecutionContextFromResponse($parsedResponse);
                    queueFunctionExecutionCommand($this->_commandBuffer, $alreadysent, $executionContext, "player2json");
                        
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
        $this->init_connector();

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('PLAYER2JSON_FAST');
        }

        if (empty($callName))
            $callName=$this->name;
        else
            $callName=$this->name."/".$callName;

        $forceJson = false;
        if (!empty($callName) && stripos($callName, "formatter") !== false) {
            $forceJson = true;
        }

        $data = $this->buildRequestPayload($contextData, $customParms, $forceJson);
        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        $headers = $this->buildHeaders();
        $timeout = max(intval(($GLOBALS["HTTP_TIMEOUT"]) ?? 30), $this->_timeout);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => $timeout,
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($options);

        file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);
        $json_response=file_get_contents($this->_url, false, $context);
        file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\n{$json_response}\n=\n", FILE_APPEND);

        if ($json_response) {
            $text_response=json_decode($json_response,true);
            if (is_valid_array($text_response) && isset($text_response["choices"][0]["message"]["content"])) {
                return $text_response["choices"][0]["message"]["content"];
            } else {
                $errorMessage = "Error in player2 request '{$this->_url}':$json_response";
                if (function_exists('log_msg')) {
                    log_msg($errorMessage, 3);
                } else {
                    error_log($errorMessage);
                }
                return "";
            }
        }

        return "";
    }

}
