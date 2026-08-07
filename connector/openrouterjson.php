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
    private $_is_nanogpt_com;
    private $_is_mistral_ai;
    private $_is_streaming;
    private $_is_reasoning;
    private $_is_openai;
    private $_model="";
    private $_fallback_models;
    private $_providers_sort;
    private $_provider_quantizations;
    private $_providers2ignore;
    private $_provider_max_price;
    private $_url;
    private $_websearch=false;
    private $_websearch_text="";
    private $_websearch_index=0;
    private $_webbackup_func=false;
    private $_remove_cot;
    private $_disable_reasoning;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;
    private $_is_grok;
    private $_lastStreamedObject;
    
    
    public function __construct()
    {
        $this->name="openrouterjson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        $this->_buffer="";
        $this->_forcedClose=false;
        $this->_is_nanogpt_com=false;
        $this->_is_mistral_ai=false;
        $this->_model="";
        $this->_fallback_models=null;
        $this->_providers_sort="";
        $this->_provider_quantizations=null;
        $this->_providers2ignore=null;
        $this->_provider_max_price=null;
        $this->_url="";
        $this->_is_streaming=true;
        $this->_is_reasoning=false;
        $this->_remove_cot=true;
        $this->_disable_reasoning=true;
        $this->_cot_tag_base="think";
        $this->_output_buffer="";
        $this->_timeout=30;
        $this->_is_grok=false;
        $this->_is_openai=false;
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
                $i_pos = stripos($s_model, "qwq-max");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, ":thinking");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "-reasoning");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "grok-3-mini"); 
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "sonar-deep-research");
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
                $i_pos = stripos($s_model, "MAI-DS-R1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwen3-235b-a22b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwen3-30b-a3b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "qwen3-32b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o4");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/gpt-oss-120b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/gpt-oss-20b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "gpt-5-mini");
            //openai/gpt-5-nano ???
            if ($i_pos === false) { //openai/gpt-5
                if (($s_model == "openai/gpt-5")) {
                    $i_pos = 9;
                }
            }
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function isOpenAIModel($s_model="") { //OpenAI models have different parameters
        $b_res = false;
        if (strlen($s_model) > 0) {
            // OpenRouter models
            $i_pos = stripos($s_model, "openai/o1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/gpt-5");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/gpt-oss-120b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/gpt-oss-20b");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "openai/o4-mini");
            // Nano-GPT models
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "azure-o1");
            if ($i_pos === false) 
                $i_pos = stripos($s_model, "azure-o3");
            // OpenAI model names
            if ($i_pos === false) { 
                if (($s_model == "o1") || ($s_model == "o1-mini") || ($s_model == "o1-preview") || 
                    ($s_model == "o3") || (strpos($s_model, "o3-mini") === 0) || (strpos($s_model, "o3-pro") === 0) || 
                    (strpos($s_model, "o4-mini") === 0)) {
                    $i_pos = 9;
                }
            }
            $b_res = (!($i_pos === false));
        }
        //Logger::debug("[OPENROUTER] is openai $s_model / $i_pos ". ($b_res ? "Y" : "N") ); //debug
        return $b_res;
    }

    private function normalizeOpenRouterModel($s_model="") {
        $normalizedModel = trim((string)$s_model);
        if ($normalizedModel === "") {
            return $normalizedModel;
        }

        $deprecatedModels = [
            "mistralai/ministral-8b" => "mistralai/ministral-8b-2512",
        ];

        if (isset($deprecatedModels[$normalizedModel])) {
            $replacementModel = $deprecatedModels[$normalizedModel];
            Logger::info("openrouterjson: remapping deprecated model {$normalizedModel} to {$replacementModel}");
            return $replacementModel;
        }

        return $normalizedModel;
    }

    private function normalizeOpenRouterModelList($models) {
        if (!is_array($models)) {
            return $models;
        }

        $normalizedModels = [];
        foreach ($models as $model) {
            $normalizedModel = $this->normalizeOpenRouterModel($model);
            if ($normalizedModel !== "") {
                $normalizedModels[] = $normalizedModel;
            }
        }

        return $normalizedModels;
    }
   
    private function init_connector($customParms) {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        $this->_remove_cot = (isset($GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"])) ? $GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"] : true;
        $this->_disable_reasoning = ($GLOBALS["CONNECTOR"][$this->name]["disable_model_reasoning"] ?? true);

        $default_model = 'meta-llama/llama-3.3-70b-instruct';

        $s_fallback = trim($GLOBALS["CONNECTOR"][$this->name]["fallback_models"] ?? ""); //"google/gemini-2.0-flash-001,google/gemini-2.5-flash-lite,meta-llama/llama-4-scout"
        if (strlen($s_fallback) > 1)
            $this->_fallback_models = $this->normalizeOpenRouterModelList(explode(",",$s_fallback)); 

        $this->_providers_sort = strtolower(trim($GLOBALS["CONNECTOR"][$this->name]["providers_sort"] ?? ""));

        $s_providers2ignore = trim($GLOBALS["CONNECTOR"][$this->name]["providers_to_ignore"] ?? ""); //"Nebius AI Studio, Together, Infermatic";
        if (strlen($s_providers2ignore) > 1)
            $this->_providers2ignore = explode(",", $s_providers2ignore);
        
        $s_quantizations = trim($GLOBALS["CONNECTOR"][$this->name]["provider_quantizations"] ?? "");  //'fp8,fp16,bf16,fp32,unknown';
        if (strlen($s_quantizations) > 1)
            $this->_provider_quantizations = explode(",", $s_quantizations); 

        $f_max_price_prompt = floatval($GLOBALS["CONNECTOR"][$this->name]["provider_max_price_input"] ?? 0.0); //0.50
        $f_max_price_completition = floatval($GLOBALS["CONNECTOR"][$this->name]["provider_max_price_output"] ?? 0.0); //2.99
        if ($f_max_price_completition < 0.001 )
            $f_max_price_completition = 0.0;
        if ($f_max_price_prompt < 0.001 )
            $f_max_price_prompt = 0.0;
        else {
            if ($f_max_price_completition < 0.001 )
                $f_max_price_completition = 9999.0;
            $this->_provider_max_price = ['prompt' => $f_max_price_prompt, 'completion' => $f_max_price_completition];
        }

        $this->_is_nanogpt_com = (stripos($this->_url, "nano-gpt.com") > 0 ); //https://nano-gpt.com/api/v1/chat/completions
        if ($this->_is_nanogpt_com) {    
            $default_model = 'meta-llama/llama-4-scout';
        } else {
            $this->_is_mistral_ai = (stripos($this->_url, "mistral.ai") > 0 ); //https://api.mistral.ai/v1/chat/completions
            if ($this->_is_mistral_ai)    
                $default_model = 'mistral-small-latest';
        }

        $this->_model = $GLOBALS["CONNECTOR"][$this->name]["model"] ?? $default_model;
        
        // We shoud be able to overwrite model.
        $this->_model = isset($customParms["model"]) ?$customParms["model"] :  $this->_model;
        $this->_model = $this->normalizeOpenRouterModel($this->_model);

        $this->_is_grok = (stripos($this->_model, "grok") > 0 ); 
        $this->_is_openai = $this->isOpenAIModel($this->_model);
        
        $this->_is_reasoning = $GLOBALS["CONNECTOR"][$this->name]["reasoning_model"] ?? false;  
        if (!$this->_is_reasoning)
            $this->_is_reasoning = $this->isReasoningModel($this->_model); // check if resoning model, use list of known reasoning models
        
        $this->_timeout = intval(($this->_is_reasoning) ? 90 : 30); // reasoning models could think more than 2 minutes
    }   
    
    public function open($contextData, $customParms)
    {

        $this->init_connector($customParms);

        if (isset($customParms["model"])) {
            $customParms["model"] = $this->normalizeOpenRouterModel($customParms["model"]);
        }
        if (isset($customParms["models"])) {
            $customParms["models"] = $this->normalizeOpenRouterModelList($customParms["models"]);
        }

        $MAX_TOKENS=intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48));


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

        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('OPENROUTERJSON');
        }

        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].=$GLOBALS["COMMAND_PROMPT"];
        }
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
        }

        if (isset($GLOBALS["HERIKA_SPEECHSTYLE"]) && (!empty($GLOBALS["HERIKA_SPEECHSTYLE"]))) {
            $speechReinforcement="Use <speech_style> for reference.";
        } else
            $speechReinforcement="";

        $zonosTones = $GLOBALS["TTSFUNCTION"] == "zonos_gradio" ? " (Response tones are mandatory in the response)" : "";
        $contextData[]=[
            'role' => 'user',
            'content' => "{$prefix}. $speechReinforcement \nUse ONLY this JSON object to give your answer. Do not send any other characters outside of this JSON structure $zonosTones: \n".json_encode($GLOBALS["responseTemplate"])
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
        $this->_webbackup_func = $GLOBALS["FUNCTIONS_ARE_ENABLED"] ?: false;

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
                    $dialogueTarget=extractDialogueTarget($element["content"]) ?? "none"; // moved here to be available in tool_calls 
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
                        
                        // Seems we were missing this case
                        if (isset($assistantRoleBuffer) && !empty($assistantRoleBuffer)) {
                            $contextDataCopy[]=[
                                "role"=>"assistant",
                                "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"$lastTargetBuffer\", \"mood\": \"\", \"action\": \"Talk\",\"target\": \"\", \"message\":\"".trim($assistantRoleBuffer)."\"}"
                                
                            ];
                            $lastTargetBuffer="";
                            $assistantRoleBuffer="";
                            $lastrole=$element["role"];

                        }                        
                        
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
                                /*
                                $contextData[$n]=[
                                    "role"=>"assistant",
                                    "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\", \"action\": \"Talk\",\"target\": \"\", \"message\":\"".trim($dialogueTarget["cleanedString"])."\"}"
                                    
                                ];
                                */

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
        
        if (!$assistantAppearedInhistory) { // is this still needed?
            
            if (isset($GLOBALS["CHIM_NO_EXAMPLES"]) && $GLOBALS["CHIM_NO_EXAMPLES"]) {
                $contextExamples=[];
            } else {
                // EXAMPLES
                $contextExamples[]= [
                    'role' => 'user', 
                    'content' => chimBuildNarratorContextLine("{$GLOBALS["PLAYER_NAME"]} looks at {$GLOBALS["HERIKA_NAME"]}")
                ];
                
                $contextExamples[]= [
                    "role"=>"assistant",
                    "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\",\"listener\": \"{$GLOBALS["PLAYER_NAME"]}\", \"mood\": \"default\", \"action\": \"Talk\",\"target\": \"\", \"message\": \"What are you looking at?\"}"
                        
                ];
                
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
        }

        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 0.7);
        if ($temperature < 0.0) $temperature = 0.0;
        else if ($temperature > 2.0) $temperature = 2.0; 

        $presence_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ? : 0.0);
        if ($presence_penalty < -2.0) $presence_penalty = -2.0;
        else if ($presence_penalty > 2.0) $presence_penalty = 2.0; 

        $frequency_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ? : 0.0); 
        if ($frequency_penalty < -2.0) $frequency_penalty = -2.0;
        else if ($frequency_penalty > 2.0) $frequency_penalty = 2.0; 

        $repetition_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]) ? : 0.0);
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
            'stream' => $this->_is_streaming, 
            'max_tokens' => $MAX_TOKENS,
            'temperature' => $temperature, 
            'top_k' => $top_k,
            'top_p' => $top_p, 
            'min_p' => $min_p,
            'top_a' => $top_a,
            'presence_penalty' => $presence_penalty, 
            'frequency_penalty' => $frequency_penalty, 
            'repetition_penalty' => $repetition_penalty,
            'stop'=>[
                    'USER',
                ],
            'transforms'=>[]
        );

        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
                $data[$k]=$v;
        }
        
        if ($GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) {
            if (isset($GLOBALS["CONNECTOR"][$this->name]["json_schema"]) && $GLOBALS["CONNECTOR"][$this->name]["json_schema"]) {
                $data["response_format"]=$GLOBALS["structuredOutputTemplate"];
            } else {
                $data["response_format"]=["type"=>"json_object"];
            }
        }
        
        // Add Google safety settings if block_none is enabled in metadata (for Google models via OpenRouter)
        if (isset($GLOBALS["CONNECTOR"][$this->name]["block_none"]) && $GLOBALS["CONNECTOR"][$this->name]["block_none"]) {
            // Only add safety settings if this is a Google/Gemini model
            if (preg_match('/google|gemini/i', $this->_model)) {
                $data["safety_settings"] = [
                    ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
                ];
            }
        }
            
        if ($this->_is_reasoning) { // add parameter to hide <think> content

            if (!(stripos($this->_model, "x-ai/grok-4.1-fast") === false)) { //x-ai/grok-4.1-fast
                $data["reasoning"] = array ('exclude' => true, 'enabled' => false); // disable reasoning by default, if _disable_reasoning = false, will be overwritten below
            }
            
            //$data["reasoning"] = array ('exclude' => true, 'enabled' => true); // exclude = true - Use reasoning but don't include it in the response; enabled = false - do not use reasoning
            if ($this->_disable_reasoning)
                $data["reasoning"] = array ('exclude' => true, 'enabled' => false); // default value, CoT removed, resoning disabled, fast response
            else
                $data["reasoning"] = array ('exclude' => true, 'enabled' => true); // CoT removed, resoning enabled, slow
            
            //Logger::debug("[OPENROUTER]  Excluding reasoning");
            //$data["reasoning"] = array ('exclude' => true, 'effort' => 'low'); // reduce reasoning tokens - OpenAI
            //$data["reasoning"] = array ('exclude' => true, 'max_tokens' => 64 ); // reduce reasoning tokens - Anthropic 
            //Logger::debug("reasoning " . $this->_model);
            if (!(stripos($this->_model, "qwen3-") === false)) {//qwen3
                $data["enable_thinking"] = false;
            } elseif (stripos($this->_model, "grok-3-mini") != false) {//grok-3-mini needs reasoning and cannot be disabled
                $data["reasoning"]["enabled"] = true;
            } elseif (stripos($this->_model, "qwen3-235b-a22b-thinking-2507") != false) {//qwen/qwen3-235b-a22b-thinking-2507 needs reasoning cand cannot be disabled
                $data["reasoning"]["enabled"] = true;
            } elseif ($this->_model=="x-ai/grok-4") {// needs reasoning and cannot be disabled 
                $data["reasoning"]["enabled"] = true;
            } elseif ($this->_model=="google/gemini-3-pro-preview") {// needs reasoning and cannot be disabled 
                $data["reasoning"] = array ('exclude' => true, 'enabled' => true, 'effort' => 'low');
            }   
        }
        
        if ($this->_is_mistral_ai) { // Mistral AI API does not support penalty params
            unset($data["presence_penalty"]); 
            unset($data["frequency_penalty"]);
        } elseif ($this->_is_grok) { //Argument not supported on this model: stop
            unset($data["stop"]); 
        } elseif ($this->_is_openai) {
            // OpenAI models use max_completion_tokens
            //Logger::debug("[OPENROUTER] Excluding reasoning this->_is_openai");
            $data['max_completion_tokens'] = $MAX_TOKENS;
            unset($data['max_tokens']); 
            if ($this->_is_reasoning) {
                $data["reasoning"] = array ('exclude' => true, 'effort' => 'low'); // reduce reasoning tokens - OpenAI
            }
        }

        if ($MAX_TOKENS<1) {
            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 
        }

        if (!empty($GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"])) {
            $providers=explode(",",$GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"]);
            $data["provider"]=["order"=>$providers];
        } 

        if (isset($this->_fallback_models) && (is_array($this->_fallback_models)) && (count($this->_fallback_models) > 0)) {
            $data['models'] = $this->_fallback_models;
        }

        if (isset($this->_providers_sort) && (in_array($this->_providers_sort,['price','throughput','latency']))) {
            $data['provider']['sort'] = $this->_providers_sort; 
        }

        if (isset($this->_providers2ignore) && (is_array($this->_providers2ignore)) && (count($this->_providers2ignore) > 0)) {
            $data['provider']['ignore'] = $this->_providers2ignore; 
        }

        if (isset($this->_provider_quantizations) && (is_array($this->_provider_quantizations)) && (count($this->_provider_quantizations) > 0)) {
            $data['provider']['quantizations'] = $this->_provider_quantizations; 
        }

        if (isset($this->_provider_max_price) && (is_array($this->_provider_max_price)) && (count($this->_provider_max_price) == 2)) {
            $json_price = json_encode($this->_provider_max_price); 
            if (isset($json_price))
                $data['provider']['max_price'] = json_decode($json_price);
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
                $search_text = preg_replace(
                    '/\s*\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+[^()]+(?:\s+from\s+far\s+away)?\s*\)\s*$/i',
                    '',
                    $search_text
                );
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

        foreach (chimGetEnabledConnectorExtraParameters($GLOBALS["CONNECTOR"][$this->name] ?? []) as $k => $v) {
                $data[$k]=$v;
        }

        if (stripos($data["model"], "openai/gpt-5-nano")===0) {
            unset($data["temperature"]);
            unset($data["top_p"]);
        }


        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}",
            "HTTP-Referer:  https://dwemerdynamics.com/",
            "X-Title: Dwemer Dynamics"
        );

        $data["usage"]=["include"=>true];

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
        
        $this->primary_handler = $this->send($this->_url, $context);
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
                        'url'=>$this->_url,
                        'response'=>$error["message"],
                    ));
            }
            return null;
        } else {
            $status_code = $this->getHttpStatusCode();
            if ($status_code >= 300) {
                $response = stream_get_contents($this->primary_handler);
                //$error_message = "Request to openrouterjson connector failed: {$status_line}.\nResponse body: {$response}";
                $error_message = "Request to openrouterjson connector failed: {$status_code}.\n Response body: {$response}.\n model: {$this->_model}";
                trigger_error($error_message, E_USER_WARNING);

                if ($GLOBALS["db"]) {
                    $GLOBALS["db"]->insert(
                    'audit_request',
                        array(
                            'request' => json_encode($data),
                            'result' => $error_message,
                            'connector'=>$this->name,
                            'url'=>$this->_url,
                            'response'=>$response,
                        ));
                }

                $this->close();
                $this->primary_handler=false;
                return null;
            } else  {
                // Will do later
                /*
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
                */
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
                    Logger::error("{$this->name} Error decoding JSON from LLM {$this->_model} output: can't find JSON start mark after reading {$buffer_preamble} characters. LLM didn't output proper JSON object or there is a long non-JSON preamble. url:{$this->_url} buffer:{$this->_buffer} ");
                    return -1;
                }

            }

            $totalBuffer.=$data["choices"][0]["delta"]["content"];

            $this->_lastStreamedObject=$data;
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
        if ($this->primary_handler) {
            fclose($this->primary_handler);
        }
        // Use callName just for logging purposes.
        if (empty($callName))
            $callName=$this->name;
        else
            $callName=$this->name."/".$callName;

        $json_response=$this->_lastStreamedObject;

        if (!isset($json_response["usage"]))
            $json_response["usage"]=[];
        
        $rowid=0;

        if ($json_response) {
                if ($GLOBALS["db"]) {
                    $rowid = $GLOBALS["db"]->insertReturningId(
                    'audit_request',
                        array(
                            'request' => json_encode($this->_dataSent),
                            'result' => "Ok",
                            'usage'=>json_encode($json_response["usage"]),
                            'connector'=>$callName,
                            'url'=>$this->_url,
                            'response'=>$this->_buffer
                        ),"rowid");
                }
                
        }
        else {
                if ($GLOBALS["db"]) {
                    $rowid = $GLOBALS["db"]->insertReturningId(
                    'audit_request',
                        array(
                            'request' => json_encode($this->_dataSent),
                            'result' => "ERROR|INVALID JSON RESPONSE",
                            'connector'=>$this->name,
                            'url'=>$this->_url,
                            'response'=>$this->_buffer
                        ),"rowid");
                }
        }
        // Write the buffer to the log file without timestamp separators
        if ($rowid > 0) {
            file_put_contents(__DIR__."/../log/output_from_llm.log", "Request ID: $rowid\n", FILE_APPEND);
        }
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);
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

                $executionContext = buildFunctionExecutionContextFromResponse($parsedResponse);
                Logger::info("openrouterjson: Prepared command payload for " . strval($executionContext["function_code_name"] ?? ""));
                queueFunctionExecutionCommand($this->_commandBuffer, $alreadysent, $executionContext, "openrouterjson");
                
                if (ob_get_level()) @ob_flush();
            } else {
                Logger::info("No actions");
                return [];
            }
        }

        //print_r($parsedResponse);
        Logger::info("openrouterjson: Returning command buffer with " . count($this->_commandBuffer) . " commands");
        if (!empty($this->_commandBuffer)) {
            foreach ($this->_commandBuffer as $cmd) {
                Logger::info("openrouterjson: Buffer contains: {$cmd}");
            }
        }
        return $this->_commandBuffer;
    }

    public function isDone()
    {
        if ($this->_forcedClose)
            return true;
        return !$this->primary_handler || feof($this->primary_handler);
    }

    public function setDone()
    {
        $this->_forcedClose=true;
        
    }

    public function fast_request($contextData, $customParms,$callName='')
    {
        
        $this->init_connector($customParms);

        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
        if (function_exists('chimEnsureNarratorJsonResponseState')) {
            chimEnsureNarratorJsonResponseState('OPENROUTERJSON_FAST');
        }
        
        if (empty($callName))
            $callName=$this->name;
        else
            $callName=$this->name."/".$callName;


        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);

        $temperature = floatval(($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? : 0.7);
        if ($temperature < 0.0) $temperature = 0.0;
        else if ($temperature > 2.0) $temperature = 2.0; 

        $presence_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ? : 0.0);
        if ($presence_penalty < -2.0) $presence_penalty = -2.0;
        else if ($presence_penalty > 2.0) $presence_penalty = 2.0; 

        $frequency_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ? : 0.0); 
        if ($frequency_penalty < -2.0) $frequency_penalty = -2.0;
        else if ($frequency_penalty > 2.0) $frequency_penalty = 2.0; 

        $repetition_penalty = floatval(($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]) ? : 0.0);
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
            'usage'=> ["include"=>true],
            'max_tokens' => $MAX_TOKENS,
            'temperature' => $temperature, 
            'top_k' => $top_k,
            'top_p' => $top_p, 
            'min_p' => $min_p,
            'top_a' => $top_a,
            'presence_penalty' => $presence_penalty, 
            'frequency_penalty' => $frequency_penalty, 
            'repetition_penalty' => $repetition_penalty,
            'stop'=>[
                    'USER',
                ],
            'transforms'=>[]
        );

        if (isset($GLOBALS["CONNECTOR"][$this->name]["stop"])&&sizeof($GLOBALS["CONNECTOR"][$this->name]["stop"])>0) {
            $data["stop"]=$GLOBALS["CONNECTOR"][$this->name]["stop"];
        }
        // Override

       

        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==0) {
                unset($data["max_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_tokens"]=$customParms["MAX_TOKENS"];
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==0) {
                unset($data["max_tokens"]);
            } else {
                $data["max_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"];

            }
        }
        

        // Mistral AI API does not support penalty params
        if ($this->_is_mistral_ai) {
            unset($data["presence_penalty"]); 
            unset($data["frequency_penalty"]);
        } 
        
        if ($this->_is_grok) { //Argument not supported on this model: stop
            unset($data["stop"]); 
        }  

        if ($this->_is_reasoning) { // add parameter to hide <think> content
            $data["reasoning"] = array ('exclude' => true,'enabled'=>false); // Use reasoning but don't include it in the response
            //$data["reasoning"] = array ('exclude' => true, 'effort' => 'low'); // reduce reasoning tokens - OpenAI
            //$data["reasoning"] = array ('exclude' => true, 'max_tokens' => 64 ); // reduce reasoning tokens - Anthropic 
            //Logger::debug("reasoning " . $this->_model);
            if (!(stripos($this->_model, "qwen3-") === false)) {//qwen3
                $data["enable_thinking"] = false;
            }       
            if (stripos($this->_model, "grok-3-mini") != false) {//grok-3-mini needs reasoning cand cannot be disabled
                $data["reasoning"]["enabled"] = true;
            }        
    }
        
        if ($this->_is_openai) {
            // OpenAI models use max_completion_tokens
            $data['max_completion_tokens'] = $MAX_TOKENS;
            unset($data['max_tokens']); 
            if ($this->_is_reasoning) {
                $data["reasoning"] = array ('exclude' => true, 'effort' => 'low'); // reduce reasoning tokens - OpenAI
            }
        }

        if ($MAX_TOKENS<1) {
            $data["max_tokens"]+=0;

            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 

        }

        if (!empty($GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"])) {
            $providers=explode(",",$GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"]);
            $data["provider"]=["order"=>$providers];
        } 

        if (isset($this->_fallback_models) && (is_array($this->_fallback_models)) && (count($this->_fallback_models) > 0)) {
            $data['models'] = $this->_fallback_models;
        }


        if (isset($this->_providers_sort) && (in_array($this->_providers_sort,['price','throughput','latency']))) {
            $data['provider']['sort'] = $this->_providers_sort; 
        }

        if (isset($this->_providers2ignore) && (is_array($this->_providers2ignore)) && (count($this->_providers2ignore) > 0)) {
            $data['provider']['ignore'] = $this->_providers2ignore; 
        }

        if (isset($this->_provider_quantizations) && (is_array($this->_provider_quantizations)) && (count($this->_provider_quantizations) > 0)) {
            $data['provider']['quantizations'] = $this->_provider_quantizations; 
        }

        if (isset($this->_provider_max_price) && (is_array($this->_provider_max_price)) && (count($this->_provider_max_price) == 2)) {
            $json_price = json_encode($this->_provider_max_price); 
            if (isset($json_price))
                $data['provider']['max_price'] = json_decode($json_price);
        }
        
        
        foreach ($customParms as $parm=>$value) {
            $data[$parm]=$value;
        }
        
        // Add Google safety settings if block_none is enabled in metadata (for Google models via OpenRouter)
        if (isset($GLOBALS["CONNECTOR"][$this->name]["block_none"]) && $GLOBALS["CONNECTOR"][$this->name]["block_none"]) {
            // Only add safety settings if this is a Google/Gemini model
            if (preg_match('/google|gemini/i', $this->_model)) {
                $data["safety_settings"] = [
                    ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
                ];
            }
        }
        
        $data["transforms"]=[];

        $GLOBALS["DEBUG_DATA"]["full"]=($data);
     
        
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
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"] ?? null) ?: 30
            )
        );

        $context = stream_context_create($options);
        
        file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        try {
            $json_response = file_get_contents($this->_url, false, $context);
            if ($json_response === false) {
               $error = error_get_last();
              error_log("Error fetching response from URL: " . $this->_url . ". Error: " . $error['message']);
            }
        } catch (Exception $e) {
            error_log("Exception occurred while fetching response from URL: " . $this->_url . ". Exception: " . $e->getMessage());
            $json_response = false;
        }

        $rowid=0;
        if ($json_response) {
            $text_response=json_decode($json_response,true);
            
            // Check for API error response (e.g., {"error": {"message": "..."}})
            if (is_array($text_response) && isset($text_response["error"])) {
                
                $errorMsg = "ERROR|API_ERROR";
                if (is_array($text_response["error"]) && isset($text_response["error"]["message"])) {
                    $errorMsg .= "|" . substr($text_response["error"]["message"], 0, 200);
                } else if (is_string($text_response["error"])) {
                    $errorMsg .= "|" . substr($text_response["error"], 0, 200);
                }
                error_log("[fast_request] API error from '{$this->_url}': " . json_encode($text_response["error"]));
                if ($GLOBALS["db"]) {
                    $rowid = $GLOBALS["db"]->insertReturningId(
                    'audit_request',
                        array(
                            'request' => json_encode($data),
                            'result' => $errorMsg,
                            'connector'=>$callName,
                            'url'=>$this->_url,
                            'response'=>$json_response,
                        ), "rowid");
                }
                file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
                file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\n{$json_response}\n=\n", FILE_APPEND);
                file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);


                return "";
            }
           
            if (is_valid_array($text_response)) {
                
                if ($GLOBALS["db"]) {
                    $rowid = $GLOBALS["db"]->insertReturningId(
                    'audit_request',
                        array(
                            'request' => json_encode($data),
                            'result' => "Ok",
                            'usage'=>json_encode($text_response["usage"]),
                            'connector'=>$callName,
                            'url'=>$this->_url,
                            'response'=>$json_response,
                        ), "rowid");
                }
                file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
                file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\n".json_encode($text_response,JSON_PRETTY_PRINT)."\n=\n", FILE_APPEND);
                file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
                return $text_response["choices"][0]["message"]["content"];    
            }
            else {
                
                if ($GLOBALS["db"]) {
                    $rowid = $GLOBALS["db"]->insertReturningId(
                    'audit_request',
                        array(
                            'request' => json_encode($data),
                            'result' => "ERROR|INVALID JSON RESPONSE|" . substr($json_response, 0, 200),
                            'connector'=>$callName,
                            'url'=>$this->_url,
                            'response'=>$json_response,
                        ), "rowid");
                }
                file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
                file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\n{$json_response}\n=\n", FILE_APPEND);
                file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
                error_log("[fast_request] Invalid JSON from '{$this->_url}': " . substr($json_response, 0, 500));
                return "";
                
            }
            
        } else {
            
            $lastError = error_get_last();
            $errorDetail = $lastError ? $lastError['message'] : 'unknown';
            error_log("[fast_request] No response from '{$this->_url}': {$errorDetail}");
            if ($GLOBALS["db"]) {
                $rowid = $GLOBALS["db"]->insertReturningId(
                'audit_request',
                    array(
                        'request' => json_encode($data),
                        'result' => "ERROR|NO RESPONSE|" . substr($errorDetail, 0, 200),
                        'connector'=>$this->name,
                        'url'=>$this->_url,
                        'response'=>$errorDetail,
                    ), "rowid");
            }
            file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
            file_put_contents(__DIR__."/../log/output_from_llm_fast.log",date(DATE_ATOM)."\n=\n{$errorDetail}\n=\n", FILE_APPEND);
            file_put_contents(__DIR__."/../log/context_sent_to_llm_fast.log",date(DATE_ATOM)."\n=\nRequest id:{$rowid}\n=\n", FILE_APPEND);
        }
            
    }

}

