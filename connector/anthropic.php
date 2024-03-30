<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");


class connector
{
    public $primary_handler;
    public $name;

    private $_functionName;
    private $_parameterBuff;
    private $_numOutputTokens;
    private $_dataSent;
    private $_fid;

    public function __construct()
    {
        $this->name="anthropic";
    }

    public function open($contextData, $customParms)
    {
        $url = $GLOBALS["CONNECTOR"][$this->name]["url"];

        $MAX_TOKENS = ((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48) + 0);

        // Preprocess contextData to ensure Anthropic API compatibility
        $systemMessage = null;
        $messages = [];

        foreach ($contextData as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $messages[] = $message;
            }
        }

        // Remove leading assistant messages
        while (!empty($messages) && $messages[0]['role'] === 'assistant') {
            array_shift($messages);
        }

        // Ensure turn-chat by joining consecutive messages of the same role
        $processedMessages = [];
        $prevRole = null;
        foreach ($messages as $message) {
            if ($message['role'] === $prevRole) {
                $processedMessages[count($processedMessages) - 1]['content'] .= " " . $message['content'];
            } else {
                $processedMessages[] = $message;
                $prevRole = $message['role'];
            }
        }

        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'claude-3-haiku-20240307',
            'messages' => $processedMessages,
            'stream' => true,
            'max_tokens' => $MAX_TOKENS,
            'temperature' => ($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ?: 1,
            'top_p' => ($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ?: 1,
        );

        if ($systemMessage !== null) {
            $data['system'] = $systemMessage;
        }
        // Mistral AI API does not support penalty params
        if (strpos($url, "mistral") === false && strpos($url, "anthropic") === false) {
            $data["presence_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"]) ?: 0;
            $data["frequency_penalty"]=($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"]) ?: 0;
        }
        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"] == 0) {
                unset($data["max_tokens"]);
            } elseif (isset($customParms["MAX_TOKENS"])) {
                $data["max_tokens"] = $customParms["MAX_TOKENS"] + 0;
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"] == 0) {
                unset($data["max_tokens"]);
            } else {
                $data["max_tokens"] = $GLOBALS["FORCE_MAX_TOKENS"] + 0;
            }
        }

        $GLOBALS["DEBUG_DATA"]["full"] = ($data);

        $headers = array(
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01',
            'anthropic-beta: messages-2023-12-15',
            "x-api-key: {$GLOBALS["CONNECTOR"][$this->name]["API_KEY"]}"
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

        if (!$this->primary_handler) {
            error_log(print_r(error_get_last(), true));
            return null;
        }

        $this->_dataSent = json_encode($data);
        return true;
    }

    public function process()
    {
        global $alreadysent;
        static $numOutputTokens = 0;
        $line = fgets($this->primary_handler);
        $buffer = "";
        $totalBuffer = "";
        file_put_contents(__DIR__ . "/../log/debugStream.log", $line, FILE_APPEND);
        $data = json_decode(substr($line, strpos($line, '{'))); // Anthropic uses SSE format

        if (isset($data->type) && $data->type === 'content_block_delta' && isset($data->delta->text)) {
            $buffer .= $data->delta->text;
            $this->_numOutputTokens += 1;
            $totalBuffer .= $data->delta->text;
        }

        return $buffer;
    }
    // Method to close the data processing operation
    public function close()
    {

        fclose($this->primary_handler);
        //if ($GLOBALS["FEATURES"]["COST_MONITOR"]["ENABLED"]) {
        //    // Call rest of tokenizer functions now, relevant data was sent

        //    TkTokenizePrompt($this->_dataSent, $GLOBALS["CONNECTOR"][$this->name]["model"]);
        //    TkTokenizeResponse($this->_numOutputTokens, $GLOBALS["CONNECTOR"][$this->name]["model"]);
        //}
    }
    public function isDone()
    {
        return feof($this->primary_handler);
    }
}