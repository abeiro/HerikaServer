<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

class openrouter
{
    public $primary_handler;
    public $name;

    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    public $_extractedbuffer;
    private $_buffer;
    private $_is_nanogpt_com;
    private $_is_mistral_ai;
    private $_is_streaming;
    private $_is_reasoning;
    private $_use_tools;
    private $_is_grok;
    private $_is_openai;
    private $_model;
    private $_fallback_models;
    private $_providers_sort;
    private $_provider_quantizations;
    private $_providers2ignore;
    private $_provider_max_price;
    private $_url;
    private $_remove_cot;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;


    public function __construct()
    {
        $this->name="openrouter";
        $this->_commandBuffer=[];
        $this->_extractedbuffer="";
        $this->_buffer="";
        $this->_is_nanogpt_com=false;
        $this->_is_mistral_ai=false;
        $this->_is_streaming=true;
        $this->_is_reasoning=false;
        $this->_is_grok=false;
        $this->_is_openai=false;
        $this->_use_tools=true;
        $this->_model="";
        $this->_fallback_models=null;
        $this->_providers_sort="";
        $this->_provider_quantizations=null;
        $this->_providers2ignore=null;
        $this->_provider_max_price=null;
        $this->_url="";
        $this->_remove_cot=true;
        $this->_cot_tag_base="think";
        $this->_output_buffer="";
        $this->_timeout=30;
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
                if (($s_model == "o1") || ($s_model == "o1-mini") || ($s_model == "o1-preview") || 
                    ($s_model == "o3") || (strpos($s_model, "o3-mini") == 0) || (strpos($s_model, "o3-pro") == 0) || 
                    (strpos($s_model, "o4-mini") == 0)) {
                    $i_pos = 1;
                }
            }
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function init_connector($customParms) {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        $this->_use_tools = (isset($GLOBALS["CONNECTOR"][$this->name]["use_tools"])) ? $GLOBALS["CONNECTOR"][$this->name]["use_tools"] : true;
        $this->_remove_cot = (isset($GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"])) ? $GLOBALS["CONNECTOR"][$this->name]["remove_chain_of_thought"] : true;

        $default_model = 'meta-llama/llama-3.3-70b-instruct';

        $s_fallback = trim($GLOBALS["CONNECTOR"][$this->name]["fallback_models"] ?? ""); //"google/gemini-2.0-flash-001,google/gemini-2.5-flash-lite,meta-llama/llama-4-scout"
        if (strlen($s_fallback) > 1)
            $this->_fallback_models = explode(",",$s_fallback); 

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

        $this->_is_grok = (stripos($this->_model, "grok") > 0 ); 
        $this->_is_openai = $this->isOpenAIModel($this->_model);

        $this->_is_reasoning = $GLOBALS["CONNECTOR"][$this->name]["reasoning_model"] ?? false;  
        if (!$this->_is_reasoning)
            $this->_is_reasoning = $this->isReasoningModel($this->_model); // check if resoning model, use list of known reasoning models
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
        /*
        if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] && false) {
            // This is deprecated
            foreach ($contextData as $n=>$contextline)  {
                if (strpos($contextline["content"],"#MEMORY")===0) {
                    $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                    $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                }
            }
        }*/

        // Remove context elements with empty content
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) {
            if (!empty($element["content"])) {
                $contextDataCopy[]=$element;
            }
        }
        
        $contextData=$contextDataCopy;


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
            'transforms'=>[]
        );

        if (isset($GLOBALS["CONNECTOR"][$this->name]["stop"])&& sizeof($GLOBALS["CONNECTOR"][$this->name]["stop"])>0) {
            $data["stop"]=$GLOBALS["CONNECTOR"][$this->name]["stop"];
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
            $data["reasoning"] = array ('exclude' => true); // Use reasoning but don't include it in the response
            //$data["reasoning"] = array ('exclude' => true, 'effort' => 'low'); // reduce reasoning tokens - OpenAI
            //$data["reasoning"] = array ('exclude' => true, 'max_tokens' => 64 ); // reduce reasoning tokens - Anthropic 
            //Logger::debug("reasoning " . $this->_model);
            if (!(stripos($this->_model, "qwen3-") === false)) {//qwen3
                $data["enable_thinking"] = false;
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
        
        $GLOBALS["FUNCTIONS_ARE_ENABLED"]=false;
        /* if FUNCTIONS_ARE_ENABLED is false, this is not used
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            foreach ($GLOBALS["FUNCTIONS"] as $function)
                $data["tools"][]=["type"=>"function","function"=>$function];
            if (isset($GLOBALS["FUNCTIONS_FORCE_CALL"])) {
                $data["tool_choice"]=$GLOBALS["FUNCTIONS_FORCE_CALL"];
            }

        }
        */
        
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
        
        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $this->primary_handler = $this->send($this->_url, $context);

        //tokenizePrompt(json_encode($data));

        return true;


    }

    public function send($s_url, $context) {
        if (isset($GLOBALS['mockConnectorSend'])) {
            return call_user_func($GLOBALS['mockConnectorSend'], $s_url, $context);
        }
        return fopen($s_url, 'r', false, $context);
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

        $line = fgets($this->primary_handler);
        $buffer="";
        $totalBuffer="";

        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);

        $data=json_decode(substr($line, 6), true);
        if (isset($data["choices"][0]["delta"]["content"])) {
            if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                $clean_content = $this->removeChainOfThought($data["choices"][0]["delta"]["content"]); // remove CoT tags and thinking content
                if (strlen($clean_content) > 0) {
                    $buffer .= $clean_content;
                    //$this->_numOutputTokens += 1;
                    $this->_buffer .= $clean_content;
                }
            }
            $totalBuffer.=$data["choices"][0]["delta"]["content"];
        }

        if (isset($data["choices"][0]["delta"]["function_call"])) {

            if (isset($data["choices"][0]["delta"]["function_call"]["name"])) {
                $this->_functionName = $data["choices"][0]["delta"]["function_call"]["name"];
            }

            if (isset($data["choices"][0]["delta"]["function_call"]["arguments"])) {

                $this->_parameterBuff .= $data["choices"][0]["delta"]["function_call"]["arguments"];

            }
        }

        if (isset($data["choices"][0]["finish_reason"]) && $data["choices"][0]["finish_reason"] == "function_call") {

            $parameterArr = json_decode($this->_parameterBuff, true);
                $parameter = $parameterArr;
            $functionCodeName = getFunctionCodeName($this->_functionName);
            $executionResolution = resolveFunctionExecutionAction($functionCodeName, $parameter);
            if (empty($executionResolution["valid"])) {
                Logger::warn("openrouter: Invalid grouped action parameters: " . implode(", ", $executionResolution["missing_required"]));
                return $this->_commandBuffer;
            }
            $functionCodeName = $executionResolution["code_name"];
            $parameter = $executionResolution["parameter_value"];
            $parameter = buildFunctionExecutionParameter($functionCodeName, $parameter);
            $commandStr = "Herika|command|$functionCodeName@$parameter\r\n";

            if (!isset($alreadysent[md5($commandStr)])) {
                $this->_commandBuffer[] = $commandStr;
                //echo "Herika|command|$functionCodeName@$parameter\r\n";

            }

            $alreadysent[md5($commandStr)] = $commandStr;
            if (ob_get_level()) @ob_flush();

        }

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

        return $buffer;
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

        fclose($this->primary_handler);
        // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);

    }

    // Method to close the data processing operation
    public function processActions()
    {
        global $alreadysent;

        if ($this->_functionName) {
            $parameterArr = json_decode($this->_parameterBuff, true);
                $parameter = $parameterArr;
            $functionCodeName = getFunctionCodeName($this->_functionName);
            $executionResolution = resolveFunctionExecutionAction($functionCodeName, $parameter);
            if (empty($executionResolution["valid"])) {
                Logger::warn("openrouter: Invalid grouped action parameters: " . implode(", ", $executionResolution["missing_required"]));
                return $this->_commandBuffer;
            }
            $functionCodeName = $executionResolution["code_name"];
            $parameter = $executionResolution["parameter_value"];
            $parameter = buildFunctionExecutionParameter($functionCodeName, $parameter);
            $commandStr = "Herika|command|$functionCodeName@$parameter\r\n";

            if (!isset($alreadysent[md5($commandStr)])) {
                $this->_commandBuffer[] = $commandStr;
                //echo "Herika|command|$functionCodeName@$parameter\r\n";

            }

            $alreadysent[md5($commandStr)] = $commandStr;
            if (ob_get_level()) @ob_flush();
        }

        return $this->_commandBuffer;
    }

    public function isDone()
    {
        return feof($this->primary_handler);
    }

}
