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
    private $_is_x_ai;
    private $_is_mistral_ai;
    private $_is_cohere_ai;
    private $_is_streaming;
    private $_is_reasoning;
    private $_model;
    private $_url;
    private $_remove_cot;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;
    public $_extractedbuffer;

    public function __construct()
    {
        $this->name="openaijson";
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
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwen3-32b");
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function init_connector($customParms) {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        $this->_remove_cot = (isset($GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"])) ? $GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"] : true;

        $default_model = 'gpt-4o-mini';

        $this->_is_groq_com = (stripos($this->_url, "groq.com") > 0 ); // https://api.groq.com/openai/v1/chat/completions
        if ($this->_is_groq_com) {
            $default_model = 'meta-llama/llama-4-scout-17b-16e-instruct';
            $this->_is_streaming = false; // groq can't do JSON with streaming
            $this->_remove_cot = false; // no need to clean output, reasoning models on groq won't output CoT if parameter reasoning_format = hidden
        } else {
            $this->_is_nanogpt_com = (stripos($this->_url, "nano-gpt.com") > 0 ); //https://nano-gpt.com/api/v1/chat/completions
            if ($this->_is_nanogpt_com) {    
                $default_model = 'meta-llama/llama-4-scout';
            } else {
                $this->_is_x_ai = (stripos($this->_url, "x.ai") > 0 ); // https://api.x.ai/v1/chat/completions
                if ($this->_is_x_ai) {    
                    $default_model = 'grok-3-mini-beta';
                } else {
                    $this->_is_mistral_ai = (stripos($this->_url, "mistral.ai") > 0 ); //https://api.mistral.ai/v1/chat/completions
                    if ($this->_is_mistral_ai) {
                        $default_model = 'mistral-small-latest';
                    } else { 
                        $this->_is_cohere_ai = (stripos($this->_url, "cohere.ai") > 0 ); //https://api.cohere.ai/compatibility/v1/chat/completions
                        if ($this->_is_cohere_ai)    
                            $default_model = 'command-r-08-2024';
                    }
                }
            }
        }

        $this->_model = $GLOBALS["CONNECTOR"][$this->name]["model"] ?? $default_model;
        // We shoud be able to overwrite model.
        $this->_model = isset($customParms["model"]) ? $customParms["model"] : $this->_model;
        
        $this->_is_reasoning = $GLOBALS["CONNECTOR"][$this->name]["reasoning_model"] ?? false;  
        if (!$this->_is_reasoning)
            $this->_is_reasoning = $this->isReasoningModel($this->_model); // check if resoning model
        $this->_timeout = ($this->_is_reasoning) ? 90 : 30; // reasoning models could think more than 2 minutes
    }
    
    public function open($contextData, $customParms)
    {
        $this->init_connector($customParms);

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
        
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
            //$prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        }
        
        if (stripos($GLOBALS["HERIKA_PERS"],"#SpeechStyle")!==false) {
            $speechReinforcement="Check reference #SpeechStyle.";
        } else
            $speechReinforcement="";

        if ($this->_is_groq_com) { // --- exception made for groq.com - JSON need pretty print
            $contextData[]=[
                'role' => 'user',
                'content' => "{$prefix}. $speechReinforcement \nUse only this JSON object to give your answer and do not send any other characters outside of this JSON structure: \n".json_encode($GLOBALS["responseTemplate"],JSON_PRETTY_PRINT) 
            ];
        } else {
            $contextData[]=[
                'role' => 'user',
                'content' => "{$prefix}. $speechReinforcement \nUse only this JSON object to give your answer and do not send any other characters outside of this JSON structure: \n".json_encode($GLOBALS["responseTemplate"])
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
                        if (isset($GLOBALS["F_RETURNMESSAGES"][$localFuncCodeName])) {
                            $lastAction=strtr($GLOBALS["F_RETURNMESSAGES"][$localFuncCodeName],[
                                            "#TARGET#"=>current($localArguments),
                                            ]);
                        }
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
                            
                           
                            if (stripos($element["content"],"error")===0) {
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
        
        // Compact and remove context elements with empty content
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) {
            if (!empty($element["content"])) {
                $contextDataCopy[]=$element;
            }
        }
        
        if ((isset($GLOBALS["CONNECTOR"][$this->name]["PREFILL_JSON"])) && ($GLOBALS["CONNECTOR"][$this->name]["PREFILL_JSON"])) {
            $GLOBALS["PATCH"]["PREAPPEND"]="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\",";
            $contextDataCopy[]= ["role"=>"assistant","content"=>$GLOBALS["PATCH"]["PREAPPEND"]];
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
            
        if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
            $data["response_format"]=$GLOBALS["structuredOutputTemplate"];
        }

        if ($MAX_TOKENS<1) {
            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 
        }

        if (isset($GLOBALS["CONNECTOR"][$this->name]["extra_parameters"]) && is_array($GLOBALS["CONNECTOR"][$this->name]["extra_parameters"])) {
            foreach ($GLOBALS["CONNECTOR"][$this->name]["extra_parameters"] as $k=>$v) {
                $data[$k]=$v;
            }
        }

        /* foreach ($customParms as $k=>$v) {
            $data[$k]=$v;
        } */

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
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: $this->_timeout,
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
            $status_code = isset($matches[0]) ? intval($matches[0]) : null;

            if ($status_code >= 300) {
                $response = stream_get_contents($this->primary_handler);
                $error_message = "Request to openaijson connector failed: {$status_line}.\n Response body: {$response}.\n model: {$this->_model}";
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
                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                    array(
                        'request' => json_encode($data),
                        'result' => "Ok",
                        'connector'=>$this->name,
                        'url'=>$this->_url
                    ));
                }

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
                $parameter = current($parameterArr); // Only support for one parameter

                if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$this->_functionName}@$parameter\r\n")])) {
                    $functionCodeName=getFunctionCodeName($this->_functionName);
                    $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@$parameter\r\n";
                    //echo "Herika|command|$functionCodeName@$parameter\r\n";

                }

                $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$this->_functionName}@$parameter\r\n")] = "{$GLOBALS["HERIKA_NAME"]}|command|{$this->_functionName}@$parameter\r\n";
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
                    if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")])) {
                        
                        $functionDef=findFunctionByName($parsedResponse["action"]);
                        if (isset($functionDef)) {
                            $functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                            if (strlen($functionDef["parameters"]["required"][0] ?? '')>0) {
                                if (!empty($parsedResponse["target"])) {
                                    $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@{$parsedResponse["target"]}\r\n";
                                }
                                else {
                                    Logger::warn("openaijson: Missing required parameter: target");
                                    $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@\r\n";
                                    // Change. we allow this. Post filter maybe can fix.

                                }
                                    
                            } else {
                                $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@{$parsedResponse["target"]}\r\n";
                            }
                        } elseif ($parsedResponse["action"] != "Talk") {
                            Logger::warn("openaijson: Function not found for {$parsedResponse["action"]}");
                        }
                        
                        //$functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                        //$this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n";
                        //echo "Herika|command|$functionCodeName@$parameter\r\n";
                        $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")]=end($this->_commandBuffer);
                    
                    } 
                        
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

}
