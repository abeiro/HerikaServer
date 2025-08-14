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
        
        // Ensure MAX_TOKENS_MEMORY has a default value for diary operations
        if (!isset($GLOBALS["CONNECTOR"][$this->name]["MAX_TOKENS_MEMORY"])) {
            $GLOBALS["CONNECTOR"][$this->name]["MAX_TOKENS_MEMORY"] = "1024";
        }
    }

    private function init_connector() {
        $this->_url = (isset($GLOBALS["CONNECTOR"][$this->name]["url"])) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : "";
        if (strlen($this->_url) < 6)
            Logger::error("{$this->name} connector - missing url!");

        // Check for hardcoded IP address override in conf_opts table
        if (isset($GLOBALS["db"]) && is_object($GLOBALS["db"])) {
            try {
                $hostIpRecord = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id='Network/HOST_IP'");
                if (!empty($hostIpRecord) && !empty(trim($hostIpRecord[0]['value']))) {
                    $hardcodedIp = trim($hostIpRecord[0]['value']);
                    
                    // Parse the original URL and replace the host with hardcoded IP
                    $urlParts = parse_url($this->_url);
                    
                    if ($urlParts !== false && isset($urlParts['scheme']) && isset($urlParts['host'])) {
                        // Reconstruct URL with hardcoded IP
                        $newUrl = $urlParts['scheme'] . '://' . $hardcodedIp;
                        
                        // Handle port - use explicit port or default based on scheme
                        if (isset($urlParts['port']) && !empty($urlParts['port'])) {
                            $newUrl .= ':' . $urlParts['port'];
                        } elseif ($urlParts['scheme'] === 'https') {
                            $newUrl .= ':443';
                        } elseif ($urlParts['scheme'] === 'http') {
                            $newUrl .= ':80';
                        }
                        
                        // Add path
                        if (isset($urlParts['path']) && !empty($urlParts['path'])) {
                            $newUrl .= $urlParts['path'];
                        }
                        
                        // Add query string
                        if (isset($urlParts['query']) && !empty($urlParts['query'])) {
                            $newUrl .= '?' . $urlParts['query'];
                        }
                        
                        // Validate the reconstructed URL
                        if (filter_var($newUrl, FILTER_VALIDATE_URL)) {
                            // Test the connection with the new URL
                            $testContext = stream_context_create([
                                'http' => [
                                    'method' => 'GET',
                                    'timeout' => 5,
                                    'ignore_errors' => true
                                ]
                            ]);
                            
                            $testHandle = @fopen($newUrl, 'r', false, $testContext);
                            if ($testHandle !== false) {
                                fclose($testHandle);
                                $this->_url = $newUrl;
                                Logger::info("{$this->name} connector - Successfully using hardcoded IP: {$hardcodedIp}");
                            } else {
                                Logger::warn("{$this->name} connector - Failed to connect to hardcoded IP {$hardcodedIp}, falling back to original URL: {$this->_url}");
                            }
                        } else {
                            Logger::warn("{$this->name} connector - Invalid reconstructed URL, falling back to original");
                        }
                    } else {
                        Logger::warn("{$this->name} connector - Failed to parse original URL, using original: {$this->_url}");
                    }
                }
            } catch (Exception $e) {
                Logger::warn("{$this->name} connector - Error checking Network/HOST_IP: " . $e->getMessage());
            }
        }

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
            'Content-Type: application/json',
            'player2-game-key: CHIM'
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

        // Add small delay to prevent rate limiting (50ms)
        usleep(50000);
        
        $this->primary_handler = $this->send($this->_url, $context);
        
        if (!$this->primary_handler) {
            $error = error_get_last();
            Logger::error("{$this->name} connector - Failed to connect: " . ($error["message"] ?? "Unknown error"));
            return false;
        }

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
        if (!$this->primary_handler) {
            return "";
        }
        
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
        if ($this->primary_handler) {
            fclose($this->primary_handler);
        }
        // Write the buffer to the log file without timestamp separators
        file_put_contents(__DIR__."/../log/output_from_llm.log", $this->_buffer . "\n", FILE_APPEND);
        file_put_contents(__DIR__."/../log/output_from_llm.log","\n== ".date(DATE_ATOM)." END\n\n", FILE_APPEND);
        
        return $this->_buffer;
    }

    // Diary connectors don't process actions
    public function processActions()
    {
        return [];
    }

    public function isDone()
    {
        return !$this->primary_handler || feof($this->primary_handler);
    }
} 