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
    private $_is_nanogpt_com;
    private $_is_openai_com;
    private $_is_x_ai;
    private $_is_mistral_ai;
    private $_is_cohere_ai;
    private $_is_cerebras_ai;
    private $_is_nvidia_com;
    private $_is_lm_studio;
    private $_is_streaming;
    private $_is_reasoning;
    private $_is_grok;
    private $_is_openai_model;
    private $_model;
    private $_url;
    private $_remove_cot;
    private $_disable_reasoning;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;
    private $_saw_reasoning_content;
    public $_extractedbuffer;
    private $_lastStreamedObject;

    public function __construct()
    {
        $this->name="openaijson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        $this->_is_groq_com=false;
        $this->_is_nanogpt_com=false;
        $this->_is_openai_com; //api.openai.com
        $this->_is_x_ai=false;
        $this->_is_mistral_ai=false;
        $this->_is_cohere_ai=false;
        $this->_is_cerebras_ai=false;
        $this->_is_nvidia_com=false;
        $this->_is_lm_studio=false;
        $this->_is_streaming=true;
        $this->_is_reasoning=false;
        $this->_is_grok=false;
        $this->_is_openai_model=false;
        $this->_model="";
        $this->_url="";
        $this->_remove_cot=true;
        $this->_disable_reasoning=false;
        $this->_cot_tag_base="think";
        $this->_output_buffer="";
        $this->_timeout=30;
        $this->_saw_reasoning_content=false;
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
            //if ($i_pos === false) 
            //    $i_pos = stripos($s_model, "qwen3-235b-a22b");
            //if ($i_pos === false) 
            //    $i_pos = stripos($s_model, "qwen3-30b-a3b");
            //if ($i_pos === false) 
            //    $i_pos = stripos($s_model, "qwen3-32b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o4");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "o1-preview");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "o1-mini");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "o4-mini");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "o3-mini");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "o3-pro");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-120b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-20b");
            if ($i_pos === false) {
                $i_pos = stripos($s_model, "gpt-5");
                if ($i_pos !== false) { //found gpt-5 model, need to check if is gpt-5-chat, chat is not a reasoning model, need to be excluded
                    $n_pos = stripos($s_model, "gpt-5-chat");
                    if ($n_pos !== false)  
                        $i_pos = false;
                }
            }

            $b_res = (!($i_pos === false));
        }
        error_log("[OPENAI] is reasoning $s_model / $i_pos ". ($b_res ? "Y" : "N") ); //debug
        return $b_res;
    }

    private function isOpenAIModel($s_model="") { //OpenAI models have different parameters
        $b_res = false;
        if (strlen($s_model) > 0) {
            // OpenRouter models
            $i_pos = stripos($s_model, "openai/o1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-5");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-120b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-oss-20b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o4");
            // Nano-GPT models
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "azure-o1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "azure-o3");
            // OpenAI model names
            if ($i_pos === false) { 
                if (($s_model == "o1") || ($s_model == "o1-mini") || ($s_model == "o1-preview") ||  ($s_model == "gpt-5-nano") || 
                    ($s_model == "o3") || (strpos($s_model, "o3-mini") === 0) || (strpos($s_model, "o3-pro") === 0) || 
                    (strpos($s_model, "o4-mini") === 0)) {
                    $i_pos = 9;
                }
            }
            $b_res = (!($i_pos === false));
        }
        error_log("[OPENAI] is openai $s_model / $i_pos ". ($b_res ? "Y" : "N") ); //debug
        return $b_res;
    }

    private function normalizeBooleanFlag($value, $default=false) {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default;
            }
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return (bool)$value;
    }

    private function isPrivateIpv4Host($host)
    {
        if (!is_string($host) || !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $octets = array_map('intval', explode('.', $host));
        if (count($octets) !== 4) {
            return false;
        }

        if ($octets[0] === 10 || $octets[0] === 127) {
            return true;
        }

        if ($octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31) {
            return true;
        }

        return ($octets[0] === 192 && $octets[1] === 168);
    }

    private function isLikelyLmStudioUrl($url)
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower(strval($parts['scheme'] ?? 'http'));
        $host = strtolower(strval($parts['host'] ?? ''));
        $path = strtolower(strval($parts['path'] ?? ''));
        $port = intval($parts['port'] ?? (($scheme === 'https') ? 443 : 80));

        if ($path !== '/v1/chat/completions' || $port !== 1234) {
            return false;
        }

        if (($host === 'localhost') || ($host === '::1') || ($host === '0.0.0.0')) {
            return true;
        }

        return $this->isPrivateIpv4Host($host) || (stripos($host, 'lmstudio') !== false);
    }

    private function buildLmStudioGenericJsonSchemaResponseFormat()
    {
        return array(
            'type' => 'json_schema',
            'json_schema' => array(
                'name' => 'generic_object_response',
                'strict' => false,
                'schema' => array(
                    'type' => 'object',
                    'additionalProperties' => true
                )
            )
        );
    }

    private function adaptLmStudioResponseFormat(array &$data)
    {
        if (!$this->_is_lm_studio) {
            return;
        }

        $responseFormat = $data['response_format'] ?? null;
        if (!is_array($responseFormat)) {
            return;
        }

        if (($responseFormat['type'] ?? '') !== 'json_object') {
            return;
        }

        $data['response_format'] = $this->buildLmStudioGenericJsonSchemaResponseFormat();
    }

    private function init_connector($customParms) {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? trim($GLOBALS["CONNECTOR"][$this->name]["url"]) : "";
        $this->_is_lm_studio = false;
        $this->_is_streaming = true;
        $this->_stopProc = false;
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        $this->_remove_cot = ($GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"] ?? true);
        $this->_disable_reasoning = ($GLOBALS["CONNECTOR"][$this->name]["disable_model_reasoning"] ?? false);

        $default_model = 'gpt-5-nano';

        $this->_is_groq_com = (stripos($this->_url, "groq.com") > 0 ); // https://api.groq.com/openai/v1/chat/completions
        if ($this->_is_groq_com) {
            $default_model = 'meta-llama/llama-4-scout-17b-16e-instruct';
            $this->_is_streaming = false; // groq can't do JSON with streaming
            $this->_remove_cot = false; // no need to clean output, reasoning models on groq won't output CoT if parameter reasoning_format = hidden
        } elseif (stripos($this->_url, "api.openai.com") > 0 ) {
            $this->_is_openai_com = true;
            $default_model = 'gpt-5-nano';            
        } elseif (stripos($this->_url, "nano-gpt.com") > 0 ) { //https://nano-gpt.com/api/v1/chat/completions
            $this->_is_nanogpt_com = true; 
            $default_model = 'meta-llama/llama-4-scout';
        } elseif (stripos($this->_url, "api.x.ai") > 0 ) { // https://api.x.ai/v1/chat/completions
            $this->_is_x_ai = true; 
            $default_model = 'grok-3-mini-beta';
        } elseif (stripos($this->_url, "mistral.ai") > 0 ) { //https://api.mistral.ai/v1/chat/completions
            $this->_is_mistral_ai = true; 
            $default_model = 'mistral-small-latest';
        } elseif (stripos($this->_url, "cohere.ai") > 0 ) { //https://api.cohere.ai/compatibility/v1/chat/completions
            $this->_is_cohere_ai = true; 
            $default_model = 'command-r-08-2024';
        } elseif (stripos($this->_url, "cerebras.ai") > 0 ) {  //api.cerebras.ai
            $this->_is_cerebras_ai = true;
        } elseif (stripos($this->_url, "api.nvidia.com") > 0 ) {  // https://integrate.api.nvidia.com/v1/chat/completions
            $this->_is_nvidia_com=true;     
        }

        $this->_model = $GLOBALS["CONNECTOR"][$this->name]["model"] ?? $default_model;
        // We shoud be able to overwrite model.
        $this->_model = isset($customParms["model"]) ? $customParms["model"] : $this->_model;
        
        $this->_is_grok = (stripos($this->_model, "grok") > 0 ); 
        $this->_is_openai_model = $this->isOpenAIModel($this->_model);
        $this->_is_lm_studio = $this->normalizeBooleanFlag($GLOBALS["CONNECTOR"][$this->name]["lmstudio_compat"] ?? false, false);
        if (!$this->_is_lm_studio) {
            $this->_is_lm_studio = $this->isLikelyLmStudioUrl($this->_url);
        }

        $this->_is_reasoning = $GLOBALS["CONNECTOR"][$this->name]["reasoning_model"] ?? false;  
        if (!$this->_is_reasoning)
            $this->_is_reasoning = $this->isReasoningModel($this->_model); // check if resoning model

        $disableStreaming = $this->normalizeBooleanFlag($GLOBALS["CONNECTOR"][$this->name]["disable_streaming"] ?? false, false);
        if ($disableStreaming && !$this->_is_groq_com) {
            $this->_is_streaming = false;
        }

        $extraParameters = chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []);
        if (!$this->_is_groq_com && array_key_exists("stream", $extraParameters)) {
            $this->_is_streaming = $this->normalizeBooleanFlag($extraParameters["stream"], $this->_is_streaming);
        }

        $this->_timeout = ($this->_is_reasoning) ? 90 : 30; // reasoning models could think more than 2 minutes
    }

    private function extractTextFromResponsePart($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return "";
        }

        $segments = [];
        foreach ($value as $part) {
            if (is_string($part)) {
                $segments[] = $part;
                continue;
            }

            if (!is_array($part)) {
                continue;
            }

            $segmentText = $part["text"] ?? "";
            if (is_string($segmentText) && $segmentText !== "") {
                $segments[] = $segmentText;
            }
        }

        return implode("", $segments);
    }

    private function extractMessageContentFromChoice($choice)
    {
        if (!is_array($choice)) {
            return "";
        }

        $message = $choice["message"] ?? [];
        if (!is_array($message)) {
            return "";
        }

        return $this->extractTextFromResponsePart($message["content"] ?? null);
    }

    private function extractDeltaContentFromChoice($choice)
    {
        if (!is_array($choice)) {
            return "";
        }

        $delta = $choice["delta"] ?? [];
        if (!is_array($delta)) {
            return "";
        }

        return $this->extractTextFromResponsePart($delta["content"] ?? null);
    }

    private function extractReasoningContentFromChoice($choice)
    {
        if (!is_array($choice)) {
            return "";
        }

        $delta = $choice["delta"] ?? [];
        if (is_array($delta)) {
            $deltaReasoning = $this->extractTextFromResponsePart($delta["reasoning_content"] ?? null);
            if ($deltaReasoning !== "") {
                return $deltaReasoning;
            }
        }

        $message = $choice["message"] ?? [];
        if (!is_array($message)) {
            return "";
        }

        return $this->extractTextFromResponsePart($message["reasoning_content"] ?? null);
    }

    private function decodeStreamingLine($line)
    {
        $line = trim((string)$line);
        if ($line === "") {
            return [null, false];
        }

        if (stripos($line, "data:") === 0) {
            $payload = trim(substr($line, 5));
            if ($payload === "") {
                return [null, false];
            }
            if ($payload === "[DONE]") {
                return [null, true];
            }

            $decoded = json_decode($payload, true);
            return [is_array($decoded) ? $decoded : null, false];
        }

        if (($line[0] !== "{") && ($line[0] !== "[")) {
            return [null, false];
        }

        $decoded = json_decode($line, true);
        return [is_array($decoded) ? $decoded : null, false];
    }

    private function appendVisibleContent($content, &$buffer, &$totalBuffer)
    {
        if (!is_string($content) || $content === "") {
            return;
        }

        $cleanContent = $this->removeChainOfThought($content);
        if (strlen($cleanContent) > 0) {
            $buffer .= $cleanContent;
            $this->_buffer .= $cleanContent;
            $this->_numOutputTokens += 1;
        }

        $totalBuffer .= $content;
    }
    
    public function open($contextData, $customParms)
    {
        $this->init_connector($customParms);
        $this->_saw_reasoning_content=false;
        $this->_stopProc=false;

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
            chimEnsureNarratorJsonResponseState('OPENAIJSON');
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
            'content' => "{$prefix} $speechReinforcement \nUse only this JSON object to give your answer and do not send any other characters outside of this JSON structure: \n".json_encode($GLOBALS["responseTemplate"],JSON_PRETTY_PRINT) // groq.com and others ask for pretty printed JSON
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
                // Last element
                $pb["user"].=$element["content"];
                $contextDataCopy[]=$element;
                
            } else {

                if ($lastrole=="assistant" && $lastrole!=$element["role"] && $element["role"]!="tool" ) {
                    $contextDataCopy[]=[
                        "role"=>"assistant",
                        "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"$lastTargetBuffer\", \"mood\": \"\", \"action\": \"Talk\",\"target\": \"\", \"message\":\"".trim($assistantRoleBuffer)."\"}"
                        // no json version:
                        //"content" => "{$GLOBALS["HERIKA_NAME"]}: ".trim($assistantRoleBuffer)
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
                    $dialogueTarget=extractDialogueTarget($element["content"]) ?? []; // moved here to be available in tool_calls
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
                                    //"content" => implode(' ', $alreadyJs) // no json 
                                ];
                        } else {
                            //error_log("#### ".$element["content"]);
                            $pb["system"].=$element["content"]."\n";
                            //$dialogueTarget=extractDialogueTarget($element["content"]); // moved up
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
                            //unset($contextData[$n]);
                        }
                            
                }
                
            }

            

            // 
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

        if (isset($customParms["MAX_TOKENS"])) {
            $MAX_TOKENS=intval($customParms["MAX_TOKENS"]); 
            unset($customParms["MAX_TOKENS"]);
        }
        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            $MAX_TOKENS=intval($GLOBALS["FORCE_MAX_TOKENS"]);
        }

        // Forcing JSON output

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

        $b_think = !$this->_disable_reasoning;
        if ($this->_is_openai_com) {
            // OpenAI safeguard: remove unsupported top_p for gpt-5 models regardless of reasoning flag

            /* api.openai.com:
            "reasoning": {"effort": "medium"},
            reasoning.effort parameter guides the model on how many reasoning tokens to generate before creating a response to the prompt.
            Specify low, medium, or high for this parameter, where low favors speed and economical token usage, and high favors more complete reasoning. The default value is medium, which is a balance between speed and reasoning accuracy.

            undocumented parameters rules:
            reasoning models do not accept top_p, temperature and other parameters
            top_p raise an error if not 1
            temperature raise an error if not 1
            other are ignored ??
            */
            //if (stripos($this->_model, "gpt-5") !== false) {
            //    unset($data["top_p"]);
            //}

            //unset($data['top_p']); // gpt-5-chat can handle temp and top_p

            if ($this->_is_reasoning) {
                unset($data['top_p']); 
                unset($data['temperature']);
                unset($data['presence_penalty']); 
                unset($data['frequency_penalty']);

                unset($data['enable_thinking']);
                unset($data['chat_format']); 
                unset($data['reasoning_effort']);
                unset($data['reasoning_format']);
                unset($data['include_reasoning']);

                unset($data['reasoning']); 
                //$data['reasoning'] = array('effort' => 'low'); 
            }
            if ($data['stream'])
                $data['stream_options']['include_usage']=true;

        } elseif ($this->_is_groq_com) { // --- exception made for groq.com

            if ($temperature < 0.000001) $temperature = 0.000001; // groq.com want this > 1e-8, never 0.0

            if ($this->_is_reasoning) { 
            /*  groq.com: 
                a reasoning model need "reasoning_format" parameter: 
                parsed  - Separates reasoning into a dedicated field while keeping the response concise.
                raw     - Includes reasoning within <think> tags in the content. (conflict with json - error 400)
                hidden  - Returns only the final answer for maximum efficiency. ! <think> tag is generated and only hidden, tokens are counted ! 

                reasoning_effort parameter controls the level of effort the model will put into reasoning. This is only supported by GPT-OSS 20B and GPT-OSS 120B.
                reasoning_effort: 
                low	Low effort reasoning. The model will use a small number of reasoning tokens.
                medium	Medium effort reasoning. The model will use a moderate number of reasoning tokens. (default)
                high	High effort reasoning. The model will use a large number of reasoning tokens.                
                */
                if ($this->_is_openai_model) {
                    $data['include_reasoning'] = false;
                    if ($this->_disable_reasoning)
                        $data['reasoning_effort'] = "low";
                    //else
                    //    $data['reasoning_effort'] = "high";
                } else {
                    $data['reasoning_format'] = "hidden";  
                }
            }
            //Logger::debug(" dbg resoning: " . var_export($this->_is_reasoning, true) . " - " . var_export($data, true));
        
        } elseif ($this->_is_x_ai) {
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
        } elseif ($this->_is_nvidia_com) {
            $data['max_tokens'] = $MAX_TOKENS;
            unset($data['max_completion_tokens']);

            if ($this->_is_reasoning) { // generic reasoning settings
                $data["chat_template_kwargs"] = array('thinking'=>$b_think, 'enable_thinking'=>$b_think); 
            }

            if (stripos($this->_model, "moonshotai/kimi-k2.5") !== false) { //moonshotai/kimi-k2.5
                if ($this->_is_reasoning) { 
                    $data["chat_template_kwargs"] = array('thinking'=>$b_think); 
                }
            } elseif (stripos($this->_model, "z-ai/glm4.7") !== false) { //z-ai/glm4.7
                if ($this->_is_reasoning) { 
                    if ($this->_disable_reasoning)
                        $data["chat_template_kwargs"] = ['enable_thinking'=>false,'clear_thinking' => true];
                    else
                        $data["chat_template_kwargs"] = ['enable_thinking'=>true,'clear_thinking' => true];
                }
            } elseif (stripos($this->_model, "deepseek-ai/deepseek-v3.2") !== false) { //deepseek-ai/deepseek-v3.2
                if ($this->_is_reasoning) { 
                    $data["chat_template_kwargs"] = array('thinking'=>$b_think); 
                }
            } elseif (stripos($this->_model, "nvidia/nemotron-3-nano-30b-a3b") !== false) { //nvidia/nemotron-3-nano-30b-a3b
                if ($this->_is_reasoning) { 
                    $data["chat_template_kwargs"] = array('enable_thinking'=>$b_think); 
                }
            }
            
        } else {
            if ($this->_is_reasoning) { 
                if ($this->_disable_reasoning)
                    $data["reasoning"] = array ('exclude' => true, 'enabled' => false); // exclude = true - Use reasoning but don't include it in the response; enabled = false - do not use reasoning
                else
                    $data["reasoning"] = array ('exclude' => true, 'enabled' => true); 

                $data["chat_format"]="tidy"; 
                $data["reasoning_effort"] = "low";
                
                if (!$this->_is_openai_model)
                    $data['reasoning_format'] = "hidden";
                if (!(stripos($this->_model, "qwen3-") === false)) {//is qwen3
                    //$data["enable_thinking"] = false;
                    $data["enable_thinking"] = $b_think;
                    unset($data["reasoning_effort"]);
                } else if (!(stripos($this->_model, "qwen3.5-") === false)) { // qwen/qwen3.5-397b-a17b
                    unset($data["reasoning_effort"]);
                    $data["enable_thinking"] = $b_think;
                } else if (!(stripos($this->_model, "zai-org/glm-4.7") === false) || !(stripos($this->_model, "z-ai/glm-4.7") === false)) {//is glm 4.7
                    if ($this->_disable_reasoning)
                        $data["thinking"] = array ('type' => 'disabled'); // "thinking":{"type": "disabled"}
                    else
                        $data["thinking"] = array ('type' => 'enabled'); 
                } else if (!(stripos($this->_model, "zai-org/glm-5") === false) || !(stripos($this->_model, "z-ai/glm-5") === false)) {//is glm 5
                    if ($this->_disable_reasoning)
                        $data["thinking"] = array ('type' => 'disabled'); // "thinking":{"type": "disabled"}
                    else
                        $data["thinking"] = array ('type' => 'enabled'); 
                }
            }

        } // --- endif provider
        
        if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
            $data["response_format"]=$GLOBALS["structuredOutputTemplate"];
        } else if (isset($GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) && $GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) {
            error_log("Enforcing JSON output for {$this->name} connector");
            ;//It's enabled by default
        } else 
            unset($data["response_format"]);

        if ($MAX_TOKENS<1) {
            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 
        }

        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
                $data[$k]=$v;
        }

        $this->adaptLmStudioResponseFormat($data);

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
            preg_match('/\d{3}/', $status_line, $matches); // get three digits (200, 300, 404, etc)
            $status_code = isset($matches[0]) ? intval($matches[0]) : 0;

            if ($status_code >= 300) {
                $response = stream_get_contents($this->primary_handler);
                $error_message = "Request to openaijson connector failed: {$this->_url} {$status_line}.\n Response body: {$response}.\n model: {$this->_model}";
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
            } else  {
                /*if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                    array(
                        'request' => json_encode($data),
                        'result' => "Ok",
                        'connector'=>$this->name,
                        'url'=>$this->_url
                    ));
                }*/
                //Will do later   

            }
        }


        $this->_dataSent=json_encode($data);    // Will use this data in tokenizer.

        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." START\n\n", FILE_APPEND);        
        return true;

    }

    private function removeChainOfThought($content) { 
    // remove content between CoT tags
        if (($this->_is_reasoning) && ($this->_remove_cot)) {
            $this->_output_buffer .= $content; // collect content in a buffer

            $crt_pos = 0;
            $inside_cot = false;
            $clean_buffer = "";
            $this->_cot_tag_base="think";
            $cot_tag = "<{$this->_cot_tag_base}>";
            $cot_end_tag = "</{$this->_cot_tag_base}>";
            
            while (true) {
                if (!$inside_cot) {
                    // CoT opening tag could be <think> <thinking> or <reasoning>
                    $cot_start = stripos($this->_output_buffer, $cot_tag, $crt_pos);
                    if ($cot_start === false) {
                        $cot_start = stripos($this->_output_buffer, "<thinking>", $crt_pos);
                        if ($cot_start === false) {
                            $cot_start = stripos($this->_output_buffer, "<reasoning>", $crt_pos);
                            if ($cot_start === false) { // No CoT tags
                                $clean_buffer .= substr($this->_output_buffer, $crt_pos);
                                break;
                            } else {
                                $this->_cot_tag_base="reasoning";
                                $cot_tag = "<reasoning>";
                                $cot_end_tag = "</reasoning>";
                            }
                        } else {
                            $this->_cot_tag_base="thinking";
                            $cot_tag = "<thinking>";
                            $cot_end_tag = "</thinking>";
                        }
                    }
                    $cot_tag_len = strlen($cot_tag);

                    // add content before the tag
                    $clean_buffer .= substr($this->_output_buffer, $crt_pos, ($cot_start - $crt_pos));
                    $crt_pos = $cot_start + $cot_tag_len; // move past CoT start tag
                    $inside_cot = true;
                } else {
                    // check CoT closing tag
                    $think_end = stripos($this->_output_buffer, $cot_end_tag, $crt_pos);
                    if ($think_end === false) {
                        // closing tag not found yet - need more chunks
                        break;
                    }
                    // skip content between tags
                    $crt_pos = $think_end + ($cot_tag_len + 1); // move past CoT end tag </...>
                    $inside_cot = false;
                }
            }

            if (!$inside_cot) { // if we've processed everything and nothing more is held in buffer
                $this->_output_buffer = ""; // reset buffer if we've processed all complete tags
                return $clean_buffer;
            }
            
            return ""; // if still inside CoT tag or haven't completed processing, return empty and wait for more chunks
        }

        return $content; // not a reasoning model, return content w/o processing
    }

    public function process()
    {
        global $alreadysent;

        static $numOutputTokens=0;

        if (!$this->primary_handler) {
            $line = "";
        } else if (!$this->_is_streaming) {
            $line = stream_get_contents($this->primary_handler);
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

            $choice = (isset($data["choices"][0]) && is_array($data["choices"][0])) ? $data["choices"][0] : [];
            $msg = $this->extractMessageContentFromChoice($choice);
            if (strlen($msg) > 0) {
                $this->appendVisibleContent($msg, $buffer, $totalBuffer);
            } else {
                $reasoningContent = $this->extractReasoningContentFromChoice($choice);
                if (strlen($reasoningContent) > 0) {
                    $this->_saw_reasoning_content = true;
                }
            }

            if (isset($data["usage"])) 
                $this->_lastStreamedObject=$data;     

        } else { // --- normal streaming flow 

            list($data, $streamDone) = $this->decodeStreamingLine($line);
            if ($streamDone) {
                $this->_stopProc = true;
                $data = ["choices" => [["finish_reason" => "stop"]]];
            }

            if (is_array($data)) {
                $choice = (isset($data["choices"][0]) && is_array($data["choices"][0])) ? $data["choices"][0] : [];
                $chunkContent = $this->extractDeltaContentFromChoice($choice);
                if (strlen($chunkContent) === 0) {
                    $chunkContent = $this->extractMessageContentFromChoice($choice);
                }

                if (strlen($chunkContent) > 0) {
                    $this->appendVisibleContent($chunkContent, $buffer, $totalBuffer);
                } else {
                    $reasoningContent = $this->extractReasoningContentFromChoice($choice);
                    if (strlen($reasoningContent) > 0) {
                        $this->_saw_reasoning_content = true;
                    }
                }
            }

            if (isset($data["usage"])) 
                $this->_lastStreamedObject=$data;
        } // --- endif is_streaming 

        // process any remaining reasoning content on stream completion
        if (is_array($data) && isset($data["choices"][0]["finish_reason"]) && $data["choices"][0]["finish_reason"] !== null) {
            $this->_stopProc = true;
            if (!empty($this->_output_buffer)) {
                $clean_remain = $this->removeChainOfThought("");
                if (!empty($clean_remain)) {
                    $buffer .= $clean_remain;
                    $this->_buffer .= $clean_remain;
                }
                $this->_output_buffer = ""; // clear the buffer
            }

            if ($this->_saw_reasoning_content && empty($this->_buffer)) {
                Logger::warn("openaijson: stream finished with reasoning-only chunks and no visible assistant content");
            }
        }

        $buffer="";

        if (!empty($this->_buffer))
            $finalData=__jpd_decode_lazy($this->_buffer);
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
                        if (isset($finalData["target"]) && !empty($finalData["target"]) && isset($finalData["action"]) && $finalData["action"]=="Talk") {
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
    public function close($callName='')
    {
        $this->_stopProc = true;
        
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
        
        // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n"."\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);
        //file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);

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
            if (isset($parsedResponse[0]) && is_array($parsedResponse[0]))
                $parsedResponse=$parsedResponse[0];
            if (is_array($parsedResponse)) {
                if (!empty($parsedResponse["action"])) {
                    if (!isset($parsedResponse["target"]))    
                        $parsedResponse["target"] = "";

                    $executionContext = buildFunctionExecutionContextFromResponse($parsedResponse);
                    queueFunctionExecutionCommand($this->_commandBuffer, $alreadysent, $executionContext, "openaijson");
                        
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
        return $this->_stopProc || !$this->primary_handler || feof($this->primary_handler);
    }

    public function fast_request($contextData, $customParms,$callName='')
    {
        
        $this->init_connector($customParms);

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('OPENAIJSON_FAST');
        }
        
        if (empty($callName))
            $callName=$this->name;
        else
            $callName=$this->name."/".$callName;

        $MAX_TOKENS=intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 512));

        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 0.7);
        if ($temperature < 0.0) $temperature = 0.0;
        else if ($temperature > 2.0) $temperature = 2.0; 

        $presence_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ? : 0.0);
        if ($presence_penalty < -2.0) $presence_penalty = -2.0;
        else if ($presence_penalty > 2.0) $presence_penalty = 2.0; 

        $frequency_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ? : 0.0); 
        if ($frequency_penalty < -2.0) $frequency_penalty = -2.0;
        else if ($frequency_penalty > 2.0) $frequency_penalty = 2.0; 

        $repetition_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]) ? : 1.0);
        if ($repetition_penalty < 0.0) $repetition_penalty = 0.0;
        else if ($repetition_penalty > 2.0) $repetition_penalty = 2.0; 

        $top_p = floatval(($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? : 1.0);
        if ($top_p > 1) $top_p = 1.0;
        else if ($top_p < 0.0) $top_p = 0.0; 

        $min_p = floatval(($GLOBALS["CONNECTOR"][$this->name]["min_p"]) ? : 0.0);
        if ($min_p > 1) $min_p = 1.0;
        else if ($min_p < 0.0) $min_p = 0.0; 

        $top_a = floatval(($GLOBALS["CONNECTOR"][$this->name]["top_a"]) ? : 0.0);
        if ($top_a > 1) $top_a = 1.0;
        else if ($top_a < 0.0) $top_a = 0.0; 

        $top_k = intval(($GLOBALS["CONNECTOR"][$this->name]["top_k"]) ? : 0);
        if ($top_k < 0) $top_k = 0; 


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

        if ($this->_is_openai_com) {
            // OpenAI safeguard: remove unsupported top_p for gpt-5 models regardless of reasoning flag

            /* api.openai.com:
            "reasoning": {"effort": "medium"},
            reasoning.effort parameter guides the model on how many reasoning tokens to generate before creating a response to the prompt.
            Specify low, medium, or high for this parameter, where low favors speed and economical token usage, and high favors more complete reasoning. The default value is medium, which is a balance between speed and reasoning accuracy.

            undocumented parameters rules:
            reasoning models do not accept top_p, temperature and other parameters
            top_p raise an error if not 1
            temperature raise an error if not 1
            other are ignored ??
            */
            //if (stripos($this->_model, "gpt-5") !== false) {
            //    unset($data["top_p"]);
            //}

            //unset($data["top_p"]); // gpt-5-chat use temp and top_p

            if ($this->_is_reasoning) {
                unset($data["top_p"]); 
                unset($data["temperature"]);

                unset($data['enable_thinking']);
                unset($data['chat_format']); 
                unset($data['reasoning_effort']);
                unset($data['reasoning_format']);
                unset($data['include_reasoning']);

                unset($data['reasoning']); 
                //$data['reasoning'] = array('effort' => 'low'); 
            }
            

        } elseif ($this->_is_groq_com) { // --- exception made for groq.com

            if ($temperature < 0.000001) $temperature = 0.000001; // groq.com want this > 1e-8, never 0.0

            if ($this->_is_reasoning) { 
            /*  groq.com: 
                a reasoning model need "reasoning_format" parameter: 
                parsed  - Separates reasoning into a dedicated field while keeping the response concise.
                raw     - Includes reasoning within <think> tags in the content. (conflict with json - error 400)
                hidden  - Returns only the final answer for maximum efficiency. ! <think> tag is generated and only hidden, tokens are counted ! 

                reasoning_effort parameter controls the level of effort the model will put into reasoning. This is only supported by GPT-OSS 20B and GPT-OSS 120B.
                reasoning_effort: 
                low	Low effort reasoning. The model will use a small number of reasoning tokens.
                medium	Medium effort reasoning. The model will use a moderate number of reasoning tokens. (default)
                high	High effort reasoning. The model will use a large number of reasoning tokens.                
                */
                if ($this->_is_openai_model) {
                    $data['include_reasoning'] = false;
                    if ($this->_disable_reasoning)
                        $data['reasoning_effort'] = "low";
                    //else
                    //    $data['reasoning_effort'] = "high";
                } else {
                    $data['reasoning_format'] = "hidden";  
                }
            }
            //error_log(" dbg resoning: " . var_export($this->_is_reasoning, true) . " - " . var_export($data, true));
        
        } elseif ($this->_is_x_ai) {
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
        } else {
            if ($this->_is_reasoning) { 
                if ($this->_disable_reasoning)
                    $data["reasoning"] = array ('exclude' => true, 'enabled' => false); // exclude = true - Use reasoning but don't include it in the response; enabled = false - do not use reasoning
                else
                    $data["reasoning"] = array ('exclude' => true, 'enabled' => true); 

                $data["chat_format"]="tidy"; 
                $data["reasoning_effort"] = "low";
                if (!$this->_is_openai_model)
                    $data['reasoning_format'] = "hidden";
                if (!(stripos($this->_model, "qwen3-") === false)) //is qwen3
                    $data["enable_thinking"] = false;
            }

        } // --- endif provider

        

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

        $this->adaptLmStudioResponseFormat($data);

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
                log_msg("Error in openai request '{$this->_url}':$json_response", 3);
                return "";
            }
        }
    }

    public function setDone()
    {
        $this->_forcedClose=true;
        
    }

}
