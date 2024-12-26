<?php

class connector
{
    public $name;
    private $conn;

    // Instead of timestamps, we'll track the current message's ID
    private $last_message_id = null;

    // CHANGED: We will store all parsed LLM JSON responses here for actions
    private $_parsedData = [];
    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    private $_numOutputTokens;
    private $_dataSent;
    private $_fid;
    private $_buffer;
    private $_stopProc;
    public $_extractedbuffer;
    private $_actionBuffer = []; 
    private $_llmResponseBuffer = ""; 
    private $_rawJsonBuffer = [];  // Accumulate raw JSON for debugging

    private const FETCH_RESPONSE_STATEMENT = "fetch_new_responses";

    public function __construct()
    {
        $this->name = "web_connector";
        $this->connectDB();
        $this->prepareStatements();
        $this->_commandBuffer=[];
        $this->_extractedbuffer="";
        require_once(__DIR__."/__jpd.php");
    }

    private function connectDB()
    {
        $host = 'localhost';
        $port = '5432';
        $dbname = 'dwemer';
        $username = 'dwemer';
        $password = 'dwemer';

        $this->conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
        if (!$this->conn) {
            error_log("Failed to connect to PostgreSQL database!");
        }
    }

    private function prepareStatements()
    {
        /**
         * We only look for rows that match our current msg_id
         * e.g. SELECT id, msg_id, response FROM output_queue_websocket
         *      WHERE msg_id = $1 ORDER BY id ASC
         */
        $query = "SELECT id, msg_id, response FROM output_queue_websocket 
                  WHERE msg_id = $1
                  ORDER BY id ASC";
        $stmt = pg_prepare($this->conn, self::FETCH_RESPONSE_STATEMENT, $query);
        if (!$stmt) {
            error_log("Error preparing statement: " . pg_last_error($this->conn));
        }
    }

    /**
     * Combine system + user roles in $contextData,
     * then send a single JSON-encoded message to the input_queue_websocket.
     */
    public function open($contextData, $customParms)
    {
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");

        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix="";
        }
        
        if (strpos($GLOBALS["HERIKA_PERS"],"#SpeechStyle")!==false) {
            $speechReinforcement="Use #SpeechStyle.";
        } else
            $speechReinforcement="";

        $contextData[] = [
            'role' => 'user',
            'content' => "{$prefix}. $speechReinforcement Use this JSON object to give your answer: ".json_encode($GLOBALS["responseTemplate"])
        ];

        $contextData[0]["content"].=$GLOBALS["COMMAND_PROMPT"];

        $data = array(
            'model' => (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'Web Connector',
            'messages' => $contextData,
            'stream' => true,
            'response_format' => ["type"=>"json_object"]
        );

        $data["response_format"] = $GLOBALS["structuredOutputTemplate"];

        $GLOBALS["DEBUG_DATA"]["full"] = ($data);

        file_put_contents(__DIR__."/../log/context_sent_to_llm.log",
            date(DATE_ATOM)."\n=\n".print_r($data,true)."=\n",
            FILE_APPEND
        );

        // Encode the entire structure as JSON for the websocket message
        $message = json_encode($data);

        // Send the message and store the new message's ID
        $sentOk = $this->sendMessage($message);
        if (!$sentOk) {
            return false;
        }
        return true;
    }

    /**
     * Insert into input_queue_websocket and capture the newly generated ID
     */
    public function sendMessage($message)
    {
        if (!$this->conn) {
            $this->connectDB();
            if (!$this->conn) {
                return false;
            }
        }

        $escapedMessage = pg_escape_string($this->conn, $message);

        // Use RETURNING id so we can store it in $this->last_message_id
        $query = "INSERT INTO input_queue_websocket (message) VALUES ('$escapedMessage') RETURNING id";
        $result = pg_query($this->conn, $query);

        if (!$result) {
            error_log("Error sending message to websocket queue: " . pg_last_error($this->conn));
            return false;
        }

        $insertRow = pg_fetch_assoc($result);
        if ($insertRow && isset($insertRow['id'])) {
            $this->last_message_id = (int)$insertRow['id'];
        } else {
            error_log("Could not retrieve the newly inserted message's ID.");
            return false;
        }

        return true;
    }

    /**
     * 1) Grab all rows in output_queue_websocket for our last_message_id
     * 2) For each row, decode JSON, accumulate text, then delete it.
     */
    public function process()
    {
        global $DEBUG_DATA, $HERIKA_NAME;
        if (!$this->conn) {
            $this->connectDB();
            if (!$this->conn) {
                return "";
            }
        }

        if (empty($this->last_message_id)) {
            // We haven't actually sent a message yet
            return "";
        }

        // Fetch all responses that match last_message_id
        $params = [$this->last_message_id];
        $result = pg_execute($this->conn, self::FETCH_RESPONSE_STATEMENT, $params);

        if (!$result) {
            error_log("Error fetching messages from websocket queue: " . pg_last_error($this->conn));
            return "";
        }

        $collectedOutput = ""; // for returning new text to tests.php

        while ($row = pg_fetch_assoc($result)) {
            $response = $row['response'];
            if (empty(trim($response))) {
                continue;
            }

            $this->_rawJsonBuffer[] = $response;

            // Try to decode as JSON
            $decoded = json_decode($response, true);
            if (
                is_array($decoded) &&
                isset($decoded['character']) &&
                isset($decoded['message'])
            ) {
                // Well-formed JSON
                $this->_parsedData[] = $decoded; 
                $collectedOutput .= $decoded['message'] . "\n";
            } else {
                // Not valid JSON or missing fields, treat entire response as text
                $collectedOutput .= $response . "\n";
            }

            // We only store the newly read text in _llmResponseBuffer
            // Then remove this row from the DB
            $this->deleteMessage($row['id']);
        }

        if ($collectedOutput !== "") {
            $this->_llmResponseBuffer .= $collectedOutput;
            $DEBUG_DATA['RAW'] = implode("\n", $this->_rawJsonBuffer);
            return $collectedOutput;
        }

        return "";
    }

    private function deleteMessage($messageId)
    {
        if (!$this->conn) {
            $this->connectDB();
            if (!$this->conn) {
                return false;
            }
        }

        $escapedId = pg_escape_string($this->conn, $messageId);
        $query = "DELETE FROM output_queue_websocket WHERE id = '$escapedId'";
        $result = pg_query($this->conn, $query);

        return (bool) $result;
    }

    public function close()
    {
        if ($this->conn) {
            pg_close($this->conn);
            $this->conn = null;
        }
    }

    /**
     * We are "done" if:
     * 1) We have something in _llmResponseBuffer (meaning at least 1 response arrived)
     * 2) There are no more new rows in output_queue_websocket for this msg_id
     */
    public function isDone()
    {
        // If we haven't received anything yet, definitely not done
        if (empty(trim($this->_llmResponseBuffer))) {
            return false;
        }

        // Check if more rows remain for this message ID
        if (!$this->conn) {
            $this->connectDB();
            if (!$this->conn) {
                // If the DB can't connect now, let's say "true" to avoid infinite loop
                return true;
            }
        }

        if (empty($this->last_message_id)) {
            // We had a partial error? Then let's say done to break
            return true;
        }

        $params = [$this->last_message_id];
        $result = pg_execute($this->conn, self::FETCH_RESPONSE_STATEMENT, $params);

        if (!$result) {
            error_log("Error fetching messages from websocket queue in isDone: " . pg_last_error($this->conn));
            return true; 
        }

        // If no new rows remain, then we are done
        return (pg_num_rows($result) === 0);
    }

    /**
     * Convert each parsed JSON object into lines like:
     *   "The Narrator|command|Attack@Jesse"
     * ...based on the "action" + "target" fields.
     */
    public function processActions()
    {
        global $ALREADY_SENT_BUFFER, $HERIKA_NAME;
        if (!isset($ALREADY_SENT_BUFFER)) {
            $ALREADY_SENT_BUFFER = [];
        }

        foreach ($this->_parsedData as $parsed) {
            $action = isset($parsed['action']) ? $parsed['action'] : null;
            $target = isset($parsed['target']) ? $parsed['target'] : null;

            // If no action or "Talk", skip
            if (empty($action) || $action === 'Talk') {
                continue;
            }

            // Example: "The Narrator|command|Attack@Jesse"
            $commandString = "{$parsed['character']}|command|{$action}@{$target}";

            // Avoid duplicates if we’ve already “sent” it
            $hash = md5($commandString."\r\n");
            if (!isset($ALREADY_SENT_BUFFER[$hash])) {
                $this->_actionBuffer[] = $commandString."\r\n";
                $ALREADY_SENT_BUFFER[$hash] = $commandString."\r\n";
            }
        }

        return $this->_actionBuffer;
    }
}
