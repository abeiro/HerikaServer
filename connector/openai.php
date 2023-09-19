<?php


class connector
{
    public $primary_handler;
    public $name;

    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;


    public function __construct()
    {
        $this->name="openai";
        $this->_commandBuffer=[];
    }


    public function open($contextData, $customParms)
    {
        $url = $GLOBALS["CONNECTOR"][$this->name]["url"];

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);



        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'gpt-3.5-turbo-0613',
            'messages' =>
                $contextData
            ,
            'stream' => true,
            'max_tokens'=>$MAX_TOKENS,
            'temperature' => ($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?: 1,
            'presence_penalty' => ($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 1,
        );

        // Override

        if (isset($GLOBALS["FORCE_MAX_TOKENS"]))
             if ($GLOBALS["FORCE_MAX_TOKENS"]==0) {
                unset($data["max_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"];
                
            }
        
        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==0) {
                unset($data["max_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_tokens"]=$customParms["MAX_TOKENS"];
            }
        }

        if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $data["functions"]=$GLOBALS["FUNCTIONS"];
            if (isset($GLOBALS["FUNCTIONS_FORCE_CALL"])) {
                $data["function_call"]=$GLOBALS["FUNCTIONS_FORCE_CALL"];
            }

        }

        $GLOBALS["DEBUG_DATA"]["full"]=($data);

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}"
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
