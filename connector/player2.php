<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

class player2
{
    public $primary_handler;
    public $name;

    private $_commandBuffer;
    public $_extractedbuffer;
    private $_buffer;
    private $_is_streaming;
    private $_model;
    private $_url;
    private $_timeout;

    public function __construct()
    {
        $this->name="player2";
        $this->_commandBuffer=[];
        $this->_extractedbuffer="";
        $this->_buffer="";
        $this->_is_streaming=true;
        $this->_model="";
        $this->_url="";
        $this->_timeout=30;
    }

    private function init_connector() {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        $default_model = 'gpt-4o-mini';
        $this->_model = $GLOBALS["CONNECTOR"][$this->name]["model"] ?? $default_model;
        
        // Ensure MAX_TOKENS_MEMORY has a default value for diary operations
        if (!isset($GLOBALS["CONNECTOR"][$this->name]["MAX_TOKENS_MEMORY"])) {
            $GLOBALS["CONNECTOR"][$this->name]["MAX_TOKENS_MEMORY"] = "1024";
        }
    }

    public function open($contextData, $customParms)
    {
        $this->init_connector();

        $MAX_TOKENS=intval((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 1024));

        // Remove context elements with empty content
        $contextDataCopy=[];
        foreach ($contextData as $n=>$element) {
            if (!empty($element["content"])) {
                $contextDataCopy[]=$element;
            }
        }
        
        $contextData=$contextDataCopy;

        $data = array(
            'model' => $this->_model,
            'messages' => $contextData,
            'stream' => $this->_is_streaming, 
            'max_tokens' => $MAX_TOKENS
        );

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
        
        // Diary connectors don't use functions
        $GLOBALS["FUNCTIONS_ARE_ENABLED"]=false;

        $GLOBALS["DEBUG_DATA"]["full"]=($data);
        
        $headers = array(
            'Content-Type: application/json'
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: $this->_timeout
            )
        );

        $context = stream_context_create($options);
        
        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",date(DATE_ATOM)."\n=\n".var_export($data,true)."\n=\n", FILE_APPEND);

        $this->primary_handler = $this->send($this->_url, $context);

        return true;
    }

    public function send($s_url, $context) {
        if (isset($GLOBALS['mockConnectorSend'])) {
            return call_user_func($GLOBALS['mockConnectorSend'], $s_url, $context);
        }
        return fopen($s_url, 'r', false, $context);
    }

    public function process()
    {
        $line = fgets($this->primary_handler);
        $buffer="";

        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);

        $data=json_decode(substr($line, 6), true);
        if (isset($data["choices"][0]["delta"]["content"])) {
            if (strlen(($data["choices"][0]["delta"]["content"]))>0) {
                $buffer .= $data["choices"][0]["delta"]["content"];
                $this->_buffer .= $data["choices"][0]["delta"]["content"];
            }
        }

        return $buffer;
    }

    // Method to close the data processing operation
    public function close()
    {
        fclose($this->primary_handler);
        // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);
    }

    // Diary connectors don't process actions
    public function processActions()
    {
        return [];
    }

    public function isDone()
    {
        return feof($this->primary_handler);
    }
} 