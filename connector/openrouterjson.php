<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class openrouterjson
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
    public $_extractedbuffer;
    private $_rawbuffer;
    private $_forcedClose=false;
    private $_model="";
    private $_is_reasoning=false;
    private $_websearch=false;
    private $_websearch_text="";
    private $_websearch_index=0;
    private $_webbackup_func=false;

    public function __construct()
    {
        $this->name="openrouterjson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        $this->_forcedClose=false;
        $this->_model="";
        $this->_is_reasoning=false;
        $this->_websearch=false;
        $this->_websearch_text="";
        $this->_websearch_index=0;
        $this->_webbackup_func=false;
        require_once(__DIR__."/__jpd.php");
    }


    private function isWebSearchInMessage($s_msg="") {
        $b_res = false;
        if (strlen($s_msg) > 7) {
            $i_pos = stripos($s_msg, "Skyrim search");
            if ($i_pos === false) 
                $i_pos = stripos($s_msg, "Search Skyrim");
            if ($i_pos === false) 
                $i_pos = stripos($s_msg, "Find knowledge in Skyrim");
            if ($i_pos === false) 
                $i_pos = stripos($s_msg, "Search Elder Scrolls");
            if ($i_pos === false) 
                $i_pos = stripos($s_msg, "Find knowledge in Elder Scrolls");
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }


    private function isReasoningModel($s_model="") { //recognize a reasoning model that can hide <think> cot part with dedicated parameters
        $b_res = false;
        if (strlen($s_model) > 0) {
            $i_pos = stripos($s_model, "deepseek-r"); 
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwq-32b"); 
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "sonar-reasoning");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "sonar-deep-research");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "claude-3.7-sonnet");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "r1-1776");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "dolphin3.0-r1-mistral");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "aion-1.0");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "reka-flash-3");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "olympiccoder-");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "grok-3-mini"); 
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, ":thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-reasoning");
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }
   
    
    public function open($contextData, $customParms)
    {
        $url = $GLOBALS["CONNECTOR"][$this->name]["url"];
        $this->_model = (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'meta-llama/llama-3.3-70b-instruct';
        $this->_is_reasoning = $this->isReasoningModel($this->_model); // check if resoning model

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);


        /***
            In the realm of perfection, the demand to tailor context for every language model would be nonexistent.

                                                                                                Tyler, 2023/11/09
        ****/
        
        if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] && isset($GLOBALS["MEMORY_STATEMENT"]) ) {
            foreach ($contextData as $n=>$contextline)  {
                if (is_array($contextline) && isset($contextline["content"])) {
                    if (strpos($contextline["content"],"#MEMORY")===0) {
                        $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                    } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                        $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                    }
                }
            }
        }

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");

        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].=$GLOBALS["COMMAND_PROMPT"];
        }
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
        }

        if (strpos($GLOBALS["HERIKA_PERS"],"#SpeechStyle")!==false) {
            $speechReinforcement="Use #SpeechStyle.";
        } else
            $speechReinforcement="";

        $zonosTones = $GLOBALS["TTSFUNCTION"] == "zonos_gradio" ? " (Response tones are mandatory in the response)" : "";
        $contextData[]=[
            'role' => 'user',
            'content' => "{$prefix}. $speechReinforcement Use ONLY this JSON object to give your answer. Do not send any other characters outside of this JSON structure$zonosTones: ".json_encode($GLOBALS["responseTemplate"])
        ];
        $pb=[];
        $pb["user"]="";
        $pb["system"]="";
        
        $contextDataOrig=array_values($contextData);
        $lastrole="";
        $assistantAppearedInhistory=false;
        $lastTargetBuffer="";
        $assistantRoleBuffer="";
        $n_ctxsize = sizeof($contextDataOrig); 
        $this->_webbackup_func = $GLOBALS["FUNCTIONS_ARE_ENABLED"];

        foreach ($contextDataOrig as $n=>$element) {
            
            if (!is_array($element)) {
                Logger::debug("$n=>$element was not an array");
                continue;

            }

            if (isset($element["content"]) && ($element["role"]!="tool") && ($n < ($n_ctxsize-2)) && ($n > ($n_ctxsize-6)) ) { // start online search request check
                //$s_msg = $element["content"];
                $i_pos = $this->isWebSearchInMessage($element["content"]); //check search trigger

                if ($this->_websearch && ($this->_websearch_index < $n) && ($element["role"] == "user")) {
                    if($i_pos === false) {
                        if (strpos($element["content"], "##") === false) { //is not memory mark
                            $this->_websearch = false; //previous web search was found in context history, do not repeat the search 
                            $GLOBALS["FUNCTIONS_ARE_ENABLED"] = $this->_webbackup_func;
                            Logger::debug("online FALSE, {$n}/{$n_ctxsize} line: ".$element["content"]);
                        }
                    }
                }

                if(!($i_pos === false)) { // found search trigger
                    $this->_websearch_text = $element["content"];
                    $this->_websearch_index = $n;
                    $this->_websearch = true;
                    $GLOBALS["FUNCTIONS_ARE_ENABLED"] = false;
                    $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] = false;
                    Logger::debug("online TRUE, {$n}/{$n_ctxsize} src: " . $this->_websearch_text);
                }
            } // --- end online search 
            
            if ($n>=($n_ctxsize-1) && $element["role"]!="tool") {
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

        //print_r($contextData);
        $contextData2=[];
        $contextData2[]= ["role"=>"system","content"=>$pb["system"]];
        $contextData2[]= ["role"=>"user","content"=>$pb["user"]];
        
        // Compacting */
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) 
            $contextDataCopy[]=$element;
        
        if ($GLOBALS["CONNECTOR"][$this->name]["PREFILL_JSON"]) {
            $GLOBALS["PATCH"]["PREAPPEND"]="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\",";
            $contextDataCopy[]= ["role"=>"assistant","content"=>$GLOBALS["PATCH"]["PREAPPEND"]];
        }
        
        
        $contextData=$contextDataCopy;
        
        if (!$assistantAppearedInhistory) { // is this still needed?
            // EXAMPLES
            $contextExamples[]= [
                'role' => 'user', 
                'content' => "The Narrator: {$GLOBALS["PLAYER_NAME"]} looks at {$GLOBALS["HERIKA_NAME"]}"
            ];
            
            $contextExamples[]= [
                "role"=>"assistant",
                "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\",\"listener\": \"{$GLOBALS["PLAYER_NAME"]}\", \"mood\": \"default\", \"action\": \"Talk\",\"target\": \"\", \"message\": \"What are you looking at?\"}"
                    
            ];
            
            if (isset($GLOBALS["CHIM_NO_EXAMPLES"]) && $GLOBALS["CHIM_NO_EXAMPLES"]) {
                $contextExamples=[];
            }

            $finalContextDataWithExamples=[];
            foreach ($contextData as $n=>$final) {
                if ($final["role"]=="system") {
                    $finalContextDataWithExamples[]=$final;
                    foreach ($contextExamples as $example)
                        $finalContextDataWithExamples[]=$example;
                    }
                else
                    $finalContextDataWithExamples[]=$final;
            }
            
            $contextData=$finalContextDataWithExamples;
        }

        
        
        $data = array(
            'model' => $this->_model, 
            'messages' => $contextData,
            'stream' => true,
            'max_tokens'=>$MAX_TOKENS,
            'stop'=>[
                    'USER',
                ],
            //'response_format'=>["type"=>"json_object"]
            
        );
        
        
        $data["temperature"]=floatval($GLOBALS["CONNECTOR"][$this->name]["temperature"]+0);
        $data["frequency_penalty"]=floatval($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]+0);
        $data["presence_penalty"]=floatval($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]+0);
        $data["repetition_penalty"]=floatval($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]+0);
        $data["min_p"]=floatval($GLOBALS["CONNECTOR"][$this->name]["min_p"]+0);
        $data["top_a"]=floatval($GLOBALS["CONNECTOR"][$this->name]["top_a"]+0);
        $data["top_k"]=floatval($GLOBALS["CONNECTOR"][$this->name]["top_k"]+0);
        $data["top_p"]=floatval($GLOBALS["CONNECTOR"][$this->name]["top_p"]+0);
         
        if ($GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) {
            if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
                $data["response_format"]=$GLOBALS["structuredOutputTemplate"];
            } else {
                $data["response_format"]=["type"=>"json_object"];
            }
        }
        
            
        // Mistral AI API does not support penalty params
        if (strpos($url, "mistral") === false) {
            $data["presence_penalty"]=floatval(($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 0);
            $data["frequency_penalty"]=floatval(($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ?: 0);
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

       
        if (!empty($GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"])) {
            $providers=explode(",",$GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"]);
            
            $data["provider"]=["order"=>$providers];

        }
            
        $data["transforms"]=[];

        if ($this->_is_reasoning) { // add parameter to hide <think> content
            $data["reasoning"] = array ('exclude' => true); // Use reasoning but don't include it in the response
            //$data["reasoning"] = array ('exclude' => true, 'effort' => 'low'); // reduce reasoning tokens - OpenAI
            //$data["reasoning"] = array ('exclude' => true, 'max_tokens' => 64 ); // reduce reasoning tokens - Anthropic 
            //Logger::debug("reasoning " . $this->_model);
        }

        if ($this->_websearch) { // online search request 

            $sx = $this->_model;
            if (strpos($sx, ":online") === false) 
                $sx = $sx . ":online";   
            $this->_model = $sx;

            $data["model"] = $this->_model;
            
            $search_text = $this->_websearch_text;
            $target = "";
            $i_pos = strpos($search_text, ":");
            if (!($i_pos === false)) {
                $target = substr($this->_websearch_text, 0, $i_pos);
                $search_text = substr($this->_websearch_text,strlen($target)+1);
                $i_pos2 = strripos($search_text, "(Talking to");
                if (!($i_pos2 === false)) {
                    $search_text = substr($search_text, 0, $i_pos2); 
                }
            }
            if (stripos($search_text, "Skyrim") === false) 
                $s_prefix = "Skyrim lore ";
            else
                $s_prefix = "";

            $data["response_format"] = array ('type' => 'json_object');
            $data["stream"] = true;

            $data["messages"] = array(); //clean everything 
            $data["messages"] = [
                ['role' => 'system', 
                 'content' => "" // "Role-play in Skyrim universe. "
                 ."You are an expert with extensive knowledge about Skyrim lore focusing on puzzle solutions, quests, places and people." 
                 ." Use web sources like gamerant.com, en.uesp.net, elderscrolls.fandom.com, gaming.stackexchange.com and avoid video sources like youtube.com "
                ],
                ['role' => 'user',
                 'content' => $s_prefix . trim($search_text)
                ],
                ['role' => 'user',
                 'content' => trim(" {$speechReinforcement} Always use this JSON object to give your answer: ".json_encode($GLOBALS["responseTemplate"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ))
                ]
            ];

            $data["plugins"] = array();
            $data["plugins"] = [
                ['id' => 'web', 
                 'search_prompt' => "Search the web to find relevant information related to Skyrim universe. "
                    . "Include relevant search results to provide most informative response. "
                    . "Write your answer from first person point of view. "
                    //. "IMPORTANT: avoid markdown and any text formatting, lists, numbered lists, step by step instructions. " 
                    . "Never mention web sources. ", // production
                 'max_results' => 2 
                ]
            ];

        } // --- end online search request

        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

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
                'timeout' => 30,
                "ignore_errors" => true
            )
        );

        $context = stream_context_create($options);
        
        $this->primary_handler = $this->send($url, $context);
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
            $status_code = $this->getHttpStatusCode();
            if ($status_code >= 300) {
                $response = stream_get_contents($this->primary_handler);
                //$error_message = "Request to openrouterjson connector failed: {$status_line}.\nResponse body: {$response}";
                $error_message = "Request to openrouterjson connector failed: {$status_code}.\nResponse body: {$response}";
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
        $this->_rawbuffer="";
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." START\n\n", FILE_APPEND);
        return true;


    }

    public function send($url, $context) {
        if (isset($GLOBALS['mockConnectorSend'])) {
            return call_user_func($GLOBALS['mockConnectorSend'], $url, $context);
        }
        return fopen($url, 'r', false, $context);
    }

    public function getHttpStatusCode() {
        if (isset($GLOBALS['mockConnectorResponseMetaData'])) {
            $responseInfo = call_user_func($GLOBALS['mockConnectorResponseMetaData']);
        } else {
            $responseInfo = stream_get_meta_data($this->primary_handler);
        }

        $statusLine = $responseInfo['wrapper_data'][0];
        preg_match('/\d{3}/', $statusLine, $matches); // get three digits (200, 300, 404, etc)
        return isset($matches[0]) ? intval($matches[0]) : null;
    }
    

    public function process()
    {
        global $alreadysent;

        static $numOutputTokens=0;

        if (!isset($GLOBALS["patch_openrouter_timeout"]))
            $GLOBALS["patch_openrouter_timeout"]=time();

        $buffer = "";
        $totalBuffer = "";
        $mangledBuffer = "";
        $finalData = "";

        if ($this->isDone()) {
            if (!$this->_buffer || empty(trim($this->_buffer))) {
                $line = "";    
                Logger::warn("LLM didn't output anything");
            }
        } else {
            if ((time()-$GLOBALS["patch_openrouter_timeout"])>60) {
                $this->_rawbuffer.="Error, timeout when receiving data from LLM";
                Logger::error("Error, timeout when receiving data from LLM");
                $this->_forcedClose=true;
                return -1;
            }
            $line = fgets($this->primary_handler);
        }
        
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);
        $this->_rawbuffer.=$line;
        
        // Check for error response
        if (strpos($line, '"error"') !== false) {
            Logger::error("Error response from LLM: $line");
            return -1;
        }
        
        $data=json_decode(substr($line, 6), true);

        if ($this->_is_reasoning)
            $buffer_preamble=4096; // some reasoning models output CoT part before JSON
        elseif ($this->_websearch)
            $buffer_preamble=256; 
        else
            $buffer_preamble=64; //was 10, 10 is not enough, some LLMs output a prefix tag/markup before JSON or "here is your JSON ..."

        if (isset($data["choices"][0]["delta"]["content"])) {
            if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                $buffer.=$data["choices"][0]["delta"]["content"];
                $this->_buffer.=$data["choices"][0]["delta"]["content"];
                // Check to see if we've received something that looks like it starts with a JSON object
                if (strlen($this->_buffer)>$buffer_preamble && strpos($this->_buffer, '{') === false) { 
                    Logger::error("Error decoding JSON from LLM output: can't find JSON start mark after reading {$buffer_preamble} characters. LLM didn't output proper JSON object or there is a long non-JSON preamble.");
                    return -1;
                }

            }

            $totalBuffer.=$data["choices"][0]["delta"]["content"];


        }
        
        if (isset($GLOBALS["PATCH"]["PREAPPEND"])) {
            $this->_buffer=$GLOBALS["PATCH"]["PREAPPEND"];
            unset($GLOBALS["PATCH"]["PREAPPEND"]);
        }
        
        $buffer="";
        if (!empty($this->_buffer))
            $finalData=__jpd_decode_lazy($this->_buffer, true);
            if (is_array($finalData)) {
                
                
                if (isset($finalData[0])&& is_array($finalData[0]))
                    $finalData=$finalData[0];
                
                if (isset($finalData["message"])) {
                    // Check first if action was issued
                    if (is_array($finalData)&&isset($finalData["action"])) {
                        if (($finalData["action"]=="Inspect")&&(!empty($finalData["target"]))) {
                            return "";
                            
                        }
                        
                    } 
                    
                    if (is_array($finalData)&&isset($finalData["message"])) {
                        if (is_array($finalData["message"]))
                            $finalData["message"]=implode(",",$finalData["message"]);
                        
                        $mangledBuffer = str_replace($this->_extractedbuffer, "", $finalData["message"]);
                        $this->_extractedbuffer=$finalData["message"];
                        if (isset($finalData["listener"])) {
                            if (isset($finalData["action"])&&($finalData["action"]=="Talk")&& lazyEmpty($finalData["listener"]) && !lazyEmpty($finalData["target"]))
                                $GLOBALS["SCRIPTLINE_LISTENER"]=$finalData["target"];
                            else
                                $GLOBALS["SCRIPTLINE_LISTENER"]=$finalData["listener"];
                        }
                        
                        if (isset($finalData["lang"])) {
                            $GLOBALS["LLM_LANG"]=$finalData["lang"];
                        }
                        
                        if (isset($finalData["mood"])) {
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
            Logger::info("Old function scheme");
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
            unset($GLOBALS["_JSON_BUFFER"]);
            $parsedResponse=__jpd_decode_lazy($this->_buffer);   // USE JPD_LAZY?
            //error_log("New function scheme");
            if (is_array($parsedResponse)) {
                //error_log("New function scheme: ".print_r($this->_buffer,true));

                if (isset($parsedResponse[0]["action"])) {
                    $parsedResponse=$parsedResponse[0];
                }

                if (!isset($parsedResponse["target"]))    
                    $parsedResponse["target"] = "";
                
                if (!empty($parsedResponse["action"])) {
                    if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")])) {
                        
                        $functionDef=findFunctionByName(trim($parsedResponse["action"]));
                        if ($functionDef) {
                            $functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                            if (strlen($functionDef["parameters"]["required"][0] ?? '')>0) {
                                if (!empty($parsedResponse["target"])) {
                                    $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@{$parsedResponse["target"]}\r\n";
                                }
                                else {
                                    Logger::warn("Missing required parameter: target");
                                }
                                    
                            } else {
                                $this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|$functionCodeName@{$parsedResponse["target"]}\r\n";
                            }
                        } elseif ($parsedResponse["action"] != "Talk") {
                            Logger::warn("Function not found for {$parsedResponse["action"]}");
                        }
                        
                        //$functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                        //$this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n";
                        //echo "Herika|command|$functionCodeName@$parameter\r\n";
                        $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")]=end($this->_commandBuffer);
                    
                    } else {
                         Logger::warn("Function not found for {$parsedResponse["action"]} already sent");
                    }
                        
                }
                
                if (ob_get_level()) @ob_flush();
            } else {
                Logger::info("No actions");
                return [];
            }
        }

        //print_r($parsedResponse);
        return $this->_commandBuffer;
    }

    public function isDone()
    {
        if ($this->_forcedClose)
            return true;
        return !$this->primary_handler || feof($this->primary_handler);
    }

}
