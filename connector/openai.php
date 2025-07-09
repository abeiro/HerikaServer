<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class openai
{
    public $primary_handler;
    public $name;

    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    private $_numOutputTokens;
    private $_dataSent;
    private $_fid;
    private $_stopProc;
    private $_buffer;
    private $_is_groq_com;
    private $_is_nanogpt_com;
    private $_is_x_ai;
    private $_is_mistral_ai;
    private $_is_cohere_ai;
    private $_is_streaming;
    private $_is_reasoning;
    private $_use_tools;
    private $_is_grok;
    private $_is_openai;
    private $_model;
    private $_url;
    private $_remove_cot;
    private $_cot_tag_base;
    private $_output_buffer; 
    private $_timeout;


    public function __construct()
    {
        $this->name="openai";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_buffer="";
        $this->_is_groq_com=false;
        $this->_is_nanogpt_com=false;
        $this->_is_x_ai=false;
        $this->_is_mistral_ai=false;
        $this->_is_cohere_ai=false; 
        $this->_is_streaming=true;
        $this->_is_reasoning=false;
        $this->_is_grok=false;
        $this->_is_openai=false;
        $this->_use_tools=true;
        $this->_model="";
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

        $default_model = 'gpt-4o-mini';

        $this->_is_groq_com = (stripos($this->_url, "groq.com") > 0 ); // https://api.groq.com/openai/v1/chat/completions
        if ($this->_is_groq_com) {
            $default_model = 'meta-llama/llama-4-scout-17b-16e-instruct';
            $this->_use_tools = false; 
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
        
        $this->_is_grok = (stripos($this->_model, "grok") > 0 ); 
        //$this->_is_openai = $this->isOpenAIModel($this->_model);

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
                if (strpos($contextline["content"],"#MEMORY")===0) {
                    $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                    $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                }
            }
        }
        
        $contextDataOrig=array_values($contextData);
        $pb["user"]="";
        $pb["system"]=""; 
        foreach ($contextDataOrig as $n=>$element) {
            
            
            if ($n>=(sizeof($contextDataOrig)-2)) {
                // Last element
                $pb["user"].=$element["content"];
                
            } else {
                if ($element["role"]=="system") {
                    
                    $pb["system"]=$element["content"]."\nThis is the script history for this story\n#CONTEXT_HISTORY\n";
                    
                } else if ($element["role"]=="user") {
                    if (empty($element["content"])) {
                        unset($contextData[$n]);
                    }
                    
                    $pb["system"].=trim($element["content"])."\n";
                    
                } else if ($element["role"]=="assistant") {
                    
                    if (isset($element["tool_calls"])) {
                        $pb["system"].="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$element["tool_calls"][0]["function"]["name"]}";
                        $lastAction="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$element["tool_calls"][0]["function"]["name"]} {$element["tool_calls"][0]["function"]["arguments"]}";
                        
                        $localFuncCodeName=getFunctionCodeName($element["tool_calls"][0]["function"]["name"]);
                        $localArguments=json_decode($element["tool_calls"][0]["function"]["arguments"],true);
                        $lastAction=strtr($GLOBALS["F_RETURNMESSAGES"][$localFuncCodeName],[
                                        "#TARGET#"=>current($localArguments),
                                        ]);
                        
                        unset($contextData[$n]);
                    } else
                        $pb["system"].=$element["content"]."\n";
                    
                } else if ($element["role"]=="tool") {
                    
                     if (!empty($element["content"])) {
                            $pb["system"].=$element["content"]."\n";
                            if (stripos($element["content"],"Error")===false) {
                                $contextData[$n]=[
                                        "role"=>"user",
                                        "content"=>"The Narrator:".strtr($lastAction,["#RESULT#"=>$element["content"]]),
                                        
                                    ];
                                    
                                $GLOBALS["PATCH_STORE_FUNC_RES"]=strtr($lastAction,["#RESULT#"=>$element["content"]]);
                            } else {
                                $contextData[$n]=[
                                        "role"=>"user",
                                        "content"=>"The Narrator: NOTE, cannot go to that place:".current($localArguments),
                                        
                                ];
                            }
                        } /* else
                            unset($contextData[$n]); */
                }
            }
        }
        
        //$contextData2=[];
        //$contextData2[]= ["role"=>"system","content"=>$pb["system"]];
        //$contextData2[]= ["role"=>"user","content"=>$pb["user"]];
        
        // Compact and remove context elements with empty content
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) {
            if (!empty($element["content"])) {
                $contextDataCopy[]=$element;
            }
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

        $data = array(
            'model' => $this->_model,
            'messages' => $contextData,
            'stream' => $this->_is_streaming, 
            'max_completion_tokens' => $MAX_TOKENS,
            'temperature' => $temperature, 
            'top_p' => $top_p, 
            'presence_penalty' => $presence_penalty, 
            'frequency_penalty' => $frequency_penalty 
        );

        if ($this->_is_groq_com) { // --- exception made for groq.com provider
            // this sequence send only content for chat completion

            if ($temperature < 0.000001) {
                $temperature = 0.000001; // groq.com want temperature > 1e-8, never 0.0
                $data['temperature'] = $temperature;
            }

            if ($this->_is_reasoning) { 
            /*  a reasoning model need "reasoning_format" parameter: 
                parsed  - Separates reasoning into a dedicated field while keeping the response concise.
                raw     - Includes reasoning within <think> tags in the content.
                hidden  - Returns only the final answer for maximum efficiency. ! <think> tag is generated and only hidden, tokens are counted ! */
                $data['reasoning_format'] = "hidden";  
            }
           
        } else { // --- normal flow (not groq)

            if ($this->_is_x_ai) {
                unset($data["presence_penalty"]); 
                unset($data["frequency_penalty"]);
            } elseif ($this->_is_mistral_ai) {
                //unset($data["presence_penalty"]); 
                //unset($data["frequency_penalty"]);
                $this->_use_tools = false;
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

        if ($MAX_TOKENS<1) {
            unset($data["max_completion_tokens"]); 
            unset($data["max_tokens"]); 
        }

        $GLOBALS["FUNCTIONS_ARE_ENABLED"] = false;
        /*
        if ($this->_use_tools) 
        {
            if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
                foreach ($GLOBALS["FUNCTIONS"] as $function)
                    $data["tools"][]=["type"=>"function","function"=>$function];
                if (isset($GLOBALS["FUNCTIONS_FORCE_CALL"])) {
                    $data["tool_choice"]=$GLOBALS["FUNCTIONS_FORCE_CALL"];
                }
            }
        } */

        if (isset($GLOBALS["CONNECTOR"][$this->name]["extra_parameters"]) && is_array($GLOBALS["CONNECTOR"][$this->name]["extra_parameters"])) {
            foreach ($GLOBALS["CONNECTOR"][$this->name]["extra_parameters"] as $k=>$v) {
                $data[$k]=$v;
            }
        }

        /* foreach ($customParms as $k=>$v) {
            $data[$k]=$v;
        } */

        $GLOBALS["DEBUG_DATA"]["full"]=($data);

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
                'timeout' => $timeout
            )
        );

        $context = stream_context_create($options);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

                
        $this->primary_handler = fopen($this->_url, 'r', false, $context);
        if (!$this->primary_handler) {
            Logger::error(print_r(error_get_last(),true));
            return null;
        }

        $this->_dataSent=json_encode($data);    // Will use this data in tokenizer.


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
                    $this->_numOutputTokens += 1;
                    $this->_buffer .= $clean_content;
                }
            }
            $totalBuffer.=$data["choices"][0]["delta"]["content"];
        }

       
        if (isset($data["choices"][0]["delta"]["tool_calls"])) {

        
            if (isset($data["choices"][0]["delta"]["tool_calls"][0]["function"]["name"])) {
                if (!isset($this->_functionName))
                    $this->_functionName = $data["choices"][0]["delta"]["tool_calls"][0]["function"]["name"];
                else
                    $this->_stopProc=true;
            }

            if (isset($data["choices"][0]["delta"]["tool_calls"][0]["function"]["arguments"])) {
                if (!$this->_stopProc)
                    $this->_parameterBuff .= $data["choices"][0]["delta"]["tool_calls"][0]["function"]["arguments"];

            }
            
            if (isset($data["choices"][0]["delta"]["tool_calls"][0]["id"])) {

                $this->_fid = $data["choices"][0]["delta"]["tool_calls"][0]["id"];

            }
            
            
            
        }

        if (isset($data["choices"][0]["finish_reason"]) && $data["choices"][0]["finish_reason"] == "tool_calls") {

            $parameterArr = json_decode($this->_parameterBuff, true) ;
            file_put_contents(__DIR__."/../log/debugStreamParsed.log",print_r($this->_parameterBuff,true));

            if (is_array($parameterArr)) {
                $parameter = current($parameterArr); // Only support for one parameter

                if (!isset($alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")])) {
                    $functionCodeName=getFunctionCodeName($this->_functionName);
                    $this->_commandBuffer[]="Herika|command|$functionCodeName@$parameter\r\n";
                    file_put_contents(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.".last_tool_call_openai.id.txt",$this->_fid);
                    //echo "Herika|command|$functionCodeName@$parameter\r\n";

                }

                $alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")] = "Herika|command|{$this->_functionName}@$parameter\r\n";
                if (ob_get_level()) @ob_flush();
            }

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
        
         // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);

        fclose($this->primary_handler);
    }

    // Method to close the data processing operation
    public function processActions()
    {
        global $alreadysent;

        if ($this->_functionName) {
            $parameterArr = json_decode($this->_parameterBuff, true);
            if (is_array($parameterArr)) {
                $parameter = current($parameterArr); // Only support for one parameter

                if (!isset($alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")])) {
                    $functionCodeName=getFunctionCodeName($this->_functionName);
                    $this->_commandBuffer[]="Herika|command|$functionCodeName@$parameter\r\n";
                    //echo "Herika|command|$functionCodeName@$parameter\r\n";

                }

                $alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")] = "Herika|command|{$this->_functionName}@$parameter\r\n";
                if (ob_get_level()) @ob_flush();
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