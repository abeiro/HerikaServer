<?php


class connector
{
    public $primary_handler;
    public $name;

    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    public $_extractedbuffer;
    private $_buffer;


    public function __construct()
    {
        $this->name="openrouter";
        $this->_commandBuffer=[];
    }


    public function open($contextData, $customParms)
    {
        $url = $GLOBALS["CONNECTOR"][$this->name]["url"];

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);


         /***
            In the realm of perfection, the demand to tailor context for every language model would be nonexistent.

                                                                                                Tyler, 2023/11/09
        ****/
        
        if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
            foreach ($contextData as $n=>$contextline)  {
                if (strpos($contextline["content"],"#MEMORY")===0) {
                    $contextData[$n]["content"]=str_replace("#MEMORY","##\nMEMORY\n",$contextline["content"]."\n##\n");
                } else if (strpos($contextline["content"],$GLOBALS["MEMORY_STATEMENT"])!==false) {
                    $contextData[$n]["content"]=str_replace($GLOBALS["MEMORY_STATEMENT"],"(USE MEMORY reference)",$contextline["content"]);
                }
            }
        }
        

        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gpt-3.5-turbo-0613',
            'messages' =>
                $contextData
            ,
            'stream' => true,
            'max_tokens'=>$MAX_TOKENS,
            'temperature' => ($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?: 1,
            'top_k' => ($GLOBALS["CONNECTOR"][$this->name]["top_k"]) ?: 0,
            'top_p' => ($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ?: 1,
            'presence_penalty' => ($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 0,
            'frequency_penalty' => ($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ?: 0,
            'repetition_penalty' => ($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"]) ?: 1.15,
            'min_p' => ($GLOBALS["CONNECTOR"][$this->name]["min_p"]) ?: 0.1,
            'top_a' => ($GLOBALS["CONNECTOR"][$this->name]["top_a"]) ?: 0,   
            'transforms'=>[],
            
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
        
        $GLOBALS["FUNCTIONS_ARE_ENABLED"]=false;

        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            foreach ($GLOBALS["FUNCTIONS"] as $function)
                $data["tools"][]=["type"=>"function","function"=>$function];
            if (isset($GLOBALS["FUNCTIONS_FORCE_CALL"])) {
                $data["tool_choice"]=$GLOBALS["FUNCTIONS_FORCE_CALL"];
            }

        }

        $data["transforms"]=[];

        $GLOBALS["DEBUG_DATA"]["full"]=($data);
        
        
        
        $data["max_tokens"]+=0;
        
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
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: 30
            )
        );

        $context = stream_context_create($options);
        
        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".print_r($data,true)."=\n", FILE_APPEND);

        $this->primary_handler = fopen($url, 'r', false, $context);

        //tokenizePrompt(json_encode($data));

        return true;


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
            if (strlen(trim($data["choices"][0]["delta"]["content"]))>0) {
                $buffer.=$data["choices"][0]["delta"]["content"];
                //$numOutputTokens += 1;

            }
            $totalBuffer.=$data["choices"][0]["delta"]["content"];
            $this->_buffer.=$data["choices"][0]["delta"]["content"];

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
            $parameter = current($parameterArr); // Only support for one parameter

            if (!isset($alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")])) {
                $functionCodeName=getFunctionCodeName($this->_functionName);
                $this->_commandBuffer[]="Herika|command|$functionCodeName@$parameter\r\n";
                //echo "Herika|command|$functionCodeName@$parameter\r\n";

            }

            $alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")] = "Herika|command|{$this->_functionName}@$parameter\r\n";
            @ob_flush();

        }

        return $buffer;
    }

    // Method to close the data processing operation
    public function close()
    {
        fclose($this->primary_handler);
        file_put_contents(__DIR__."/../log/output_from_llm.log",date(DATE_ATOM)."\n=\n".$this->_buffer."\n=\n", FILE_APPEND);

    }

    // Method to close the data processing operation
    public function processActions()
    {
        global $alreadysent;

        if ($this->_functionName) {
            $parameterArr = json_decode($this->_parameterBuff, true);
            $parameter = current($parameterArr); // Only support for one parameter

            if (!isset($alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")])) {
                $functionCodeName=getFunctionCodeName($this->_functionName);
                $this->_commandBuffer[]="Herika|command|$functionCodeName@$parameter\r\n";
                //echo "Herika|command|$functionCodeName@$parameter\r\n";

            }

            $alreadysent[md5("Herika|command|{$this->_functionName}@$parameter\r\n")] = "Herika|command|{$this->_functionName}@$parameter\r\n";
            @ob_flush();
        }

        return $this->_commandBuffer;
    }

    public function isDone()
    {
        return feof($this->primary_handler);
    }

}
