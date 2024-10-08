<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class connector
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

    public function __construct()
    {
        $this->name="openrouterjson";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        require_once(__DIR__."/__jpd.php");
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
                if (strpos($contextline["content"],"#MEMORY")===0) {
                    $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                    $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                }
            }
        }
        
        
        
         $FUNC_LIST[]="Talk";
         if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].="\nAVAILABLE ACTION: Talk";
            foreach ($GLOBALS["FUNCTIONS"] as $function) {
                //$data["tools"][]=["type"=>"function","function"=>$function];
                if (strpos($function["name"],"Attack")!==false) {   // Every command starting with Attack
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]} : {$function["description"]}";
                    $contextData[0]["content"].="(available targets: ".implode(",",$GLOBALS["FUNCTION_PARM_INSPECT"]).")";
                } /*else if ($function["name"]==$GLOBALS["F_NAMES"]["SetSpeed"]) {
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]}(available speeds: run|fastwalk|jog|walk) ";
                    $contextData[0]["content"].="({$function["description"]})";
                }*/  else if ($function["name"]==$GLOBALS["F_NAMES"]["SearchMemory"]) {
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]} : {$function["description"]})";
                 
                } else
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]} : {$function["description"]}";
                
                $FUNC_LIST[]=$function["name"];
            }
            
            

        }
        
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        }
        $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";

        //$FUNC_LIST[]="None";
        shuffle($FUNC_LIST);
        
        $moods=explode(",",$GLOBALS["EMOTEMOODS"]);
        shuffle($moods);
        
        
        if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
            $formatJsonTemplate= [
            'role' => 'user', 
            'content' => "{$prefix}Use this JSON object to give your answer: ".json_encode([
                "character"=>$GLOBALS["HERIKA_NAME"],
                "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                "mood"=>implode("|",$moods),
                "action"=>implode("|",$FUNC_LIST),
                "target"=>"action's target|destination name",
                "lang"=>"en|es",
                "message"=>'dialogues lines',
                
            ])
            ];
            
        } else {
        
            $formatJsonTemplate= [
                'role' => 'user', 
                'content' => "{$prefix}Use this JSON object to give your answer: ".json_encode([
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$FUNC_LIST),
                    "target"=>"action's target|destination name",
                    "message"=>'dialogues lines',
                    
                    
            ])
            ];
        }
       
        
        $contextData[]=$formatJsonTemplate;
        $pb=[];
        $pb["user"]="";
      
        
        $contextDataOrig=array_values($contextData);
        $lastrole="";
        $assistantAppearedInhistory=false;
        foreach ($contextDataOrig as $n=>$element) {
            
            
            if ($n>=(sizeof($contextDataOrig)-2)) {
                // Last element
                $pb["user"].=$element["content"];
                $contextDataCopy[]=$element;
                
            } else {

                if ($lastrole=="assistant" && $lastrole!=$element["role"]) {
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
                        
                        $contextData[$n]=[
                                "role"=>"assistant",
                                "content"=>"{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\", 
                                \"action\": \"$lastActionName\", 
                                \"target\": \"".current($localArguments)."\", \"message\": \"\"}"
                            ];
                            
                        $gameRequestCopy=$GLOBALS["gameRequest"];    
                        $gameRequestCopy[3]="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\", 
                        \"action\": \"$lastActionName\", \"target\": \"".current($localArguments)."\", \"message\": \"\"}";
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
                            $contextDataCopy[]=[
                                    "role"=>"user",
                                    "content"=>"The Narrator: ({$GLOBALS["HERIKA_NAME"]} used action $lastActionName)".strtr($lastAction,["#RESULT#"=>$element["content"]]),
                                    
                                ];
                                
                            $GLOBALS["PATCH_STORE_FUNC_RES"]=strtr($lastAction,["#RESULT#"=>$element["content"]]);
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
        
        if (!$assistantAppearedInhistory) {
            // EXAMPLES
            $contextExamples[]= [
                'role' => 'user', 
                'content' => "The Narrator: {$GLOBALS["PLAYER_NAME"]} looks at {$GLOBALS["HERIKA_NAME"]}"
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

        
        
        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gpt-3.5-turbo-1106',
            'messages' =>
                $contextData
            ,
            'stream' => true,
            'max_tokens'=>$MAX_TOKENS,
            'stop'=>[
                    'USER',
                ],
            //'response_format'=>["type"=>"json_object"]
            
        );
        
        
         $data["temperature"]=$GLOBALS["CONNECTOR"][$this->name]["temperature"]+0;
         $data["frequency_penalty"]=$GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]+0;
         $data["presence_penalty"]=$GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]+0;
         $data["repetition_penalty"]=$GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]+0;
         $data["min_p"]=$GLOBALS["CONNECTOR"][$this->name]["min_p"]+0;
         $data["top_a"]=$GLOBALS["CONNECTOR"][$this->name]["top_a"]+0;
         $data["top_k"]=$GLOBALS["CONNECTOR"][$this->name]["top_k"]+0;
         $data["top_p"]=$GLOBALS["CONNECTOR"][$this->name]["top_p"]+0;
         
         if ($GLOBALS["CONNECTOR"][$this->name]["ENFORCE_JSON"]) {
            $data["response_format"]=["type"=>"json_object"];
         }
        
            
        // Mistral AI API does not support penalty params
        if (strpos($url, "mistral") === false) {
            $data["presence_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 0;
            $data["frequency_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ?: 0;
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

        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".print_r($data,true)."=\n", FILE_APPEND);

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}",
            "HTTP-Referer:  {$GLOBALS["CONNECTOR"][$this->name]["xreferer"]}",
            "X-Title: {$GLOBALS["CONNECTOR"][$this->name]["xtitle"]}"
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => 30
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

        $this->_dataSent=json_encode($data);    // Will use this data in tokenizer.
        $this->_rawbuffer="";
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." START\n\n", FILE_APPEND);
        return true;


    }

    

    public function process()
    {
        global $alreadysent;

        static $numOutputTokens=0;

        if (!isset($GLOBALS["patch_openrouter_timeout"]))
            $GLOBALS["patch_openrouter_timeout"]=time();

        if ($this->isDone()) {//  Didn't output anything?
            if (empty(trim($this->_buffer))) {
                $line="";    
                error_log("LLM didn't output anything");
            }
        } else {
            if ((time()-$GLOBALS["patch_openrouter_timeout"])>60) {
                $this->_rawbuffer.="Error, timeout when receiving data from LLM";
                error_log("Error, timeout when receiving data from LLM");
                $this->_forcedClose=true;
                
                return;
            }
            //error_log("Performing fgets(this->primary_handler) START {$GLOBALS["patch_openrouter_timeout"]}");
            $line = fgets($this->primary_handler);
            //error_log("Performing fgets(this->primary_handler) DONE {$GLOBALS["patch_openrouter_timeout"]}");
        }
        
        $buffer="";
        $totalBuffer="";
        $finalData="";
        $mangledBuffer="";
        
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);
        $this->_rawbuffer.=$line;
        $data=json_decode(substr($line, 6), true);
        if (isset($data["choices"][0]["delta"]["content"])) {
            if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                $buffer.=$data["choices"][0]["delta"]["content"];
                $this->_buffer.=$data["choices"][0]["delta"]["content"];
                $this->_numOutputTokens += 1;

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
                        
                    }
                }
                
            } else
                $buffer="";
        
        return $mangledBuffer;
    }

    // Method to close the data processing operation
    public function close()
    {

        fclose($this->primary_handler);
        
        if (empty(trim($this->_buffer))) {

            if ($GLOBALS["db"]) {
                $GLOBALS["db"]->insert(
                'audit_request',
                    array(
                        'request' => $this->_dataSent,
                        'result' => $this->_rawbuffer
                    ));
            }
        }
        file_put_contents(__DIR__."/../log/output_from_llm.log","{$this->_buffer}\n\n".date(DATE_ATOM)." END\n==\n", FILE_APPEND);


    }

   

    // Method to close the data processing operation
    public function processActions()
    {
        global $alreadysent;

        if ($this->_functionName) {
            error_log("Old function scheme");
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
            unset($GLOBALS["_JSON_BUFFER"]);
            $parsedResponse=__jpd_decode_lazy($this->_buffer);   // USE JPD_LAZY?
            //error_log("New function scheme");
            if (is_array($parsedResponse)) {
                //error_log("New function scheme: ".print_r($this->_buffer,true));

                if (isset($parsedResponse[0]["action"])) {
                    $parsedResponse=$parsedResponse[0];
                }
                
                if (!empty($parsedResponse["action"])) {
                    if (!isset($alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")])) {
                        
                        $functionDef=findFunctionByName(trim($parsedResponse["action"]));
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
                        } else {
                            error_log("Function not found for {$parsedResponse["action"]}");
                        }
                        
                        //$functionCodeName=getFunctionCodeName($parsedResponse["action"]);
                        //$this->_commandBuffer[]="{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n";
                        //echo "Herika|command|$functionCodeName@$parameter\r\n";
                        $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|{$parsedResponse["action"]}@{$parsedResponse["target"]}\r\n")]=end($this->_commandBuffer);
                    
                    } else {
                          error_log("Function not found for {$parsedResponse["action"]} already sent");
                    }
                        
                }
                
                @ob_flush();    
            } else {
                error_log("No actions");
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
        return feof($this->primary_handler);
    }

}
