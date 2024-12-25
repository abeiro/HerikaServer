<?php

class connector
{
    public $name;
    private $conn;
    private $last_processed_timestamp = null;

    // CHANGED: We will store all parsed LLM JSON responses here for actions
    private $_parsedData = [];

    private $_actionBuffer = []; 
    private $_llmResponseBuffer = ""; 
    private $_rawJsonBuffer = [];  // ADDED: Accumulate raw JSON for $DEBUG_DATA['RAW']

    private const FETCH_RESPONSE_STATEMENT = "fetch_new_responses";

    public function __construct()
    {
        $this->name = "web_connector";
        $this->connectDB();
        $this->prepareStatements();
    }

    private function connectDB()
    {
        $host = 'localhost';
        $port = '5432';
        $dbname = 'dwemer';
        $schema = 'public';
        $username = 'dwemer';
        $password = 'dwemer';

        $this->conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

        if (!$this->conn) {
            error_log("Failed to connect to PostgreSQL database!");
            // Handle the error more gracefully if needed
        }
    }

    private function prepareStatements()
    {
        // We'll fetch all new responses that have a timestamp > last_processed_timestamp
        $query = "SELECT id, response, timestamp FROM output_queue_websocket WHERE timestamp > $1 ORDER BY timestamp ASC";
        $stmt = pg_prepare($this->conn, self::FETCH_RESPONSE_STATEMENT, $query);
        if (!$stmt) {
            error_log("Error preparing statement: " . pg_last_error($this->conn));
        }
    }

    /**
     * When we open the connector, we merge the context data (system + user roles), 
     * then we append a user message instructing the LLM to return JSON 
     * in the format you requested for your mod server.
     */
    public function open($contextData, $customParms)
    {
        global $DEBUG_DATA;

        $DEBUG_DATA['full'] = $contextData; // store the context data as 'full'

        // ADDED: Append the JSON object instructions so the LLM knows how to format its answer
        // Feel free to swap in your existing code or template from your openaijson.php
        $jsonObject = [
            "character" => "The Narrator",
            "listener"  => "specify who The Narrator is talking to",
            "mood"      => "smirking|irritated|amused|playful|sarcastic|seductive|teasing|default|smug|lovely|neutral|sassy|sexy|kindly|mocking|assertive|sardonic|assisting",
            "action"    => "LeadTheWayTo|Heal|InspectSurroundings|Hunt|ListInventory|TakeASeat|ExchangeItems|ReadQuestJournal|WaitHere|DecreaseWalkSpeed|Talk|StopLooting|IncreaseWalkSpeed|SetCurrentTask|Inspect|StartLooting|BeginTrading|Attack|LetsRelax",
            "target"    => "action's target",
            "lang"      => "en|es",
            "message"   => "action's target|destination name"
        ];

        // Add a final user role instruction. 
        // This ensures the LLM sees it last and returns the JSON.
        $contextData[] = [
            'role'    => 'user',
            'content' => "Use this JSON object to give your answer: " . json_encode($jsonObject)
        ];

        // Now we send the entire conversation as one array
        $message = json_encode($contextData);

        if (!$this->sendMessage($message)) {
            return false;
        }
        return true;
    }

    public function sendMessage($message)
    {
        if (!$this->conn) {
            $this->connectDB();
            if (!$this->conn) {
                return false;
            }
        }

        $escapedMessage = pg_escape_string($this->conn, $message);
        $query = "INSERT INTO input_queue_websocket (message) VALUES ('$escapedMessage')";
        $result = pg_query($this->conn, $query);

        if (!$result) {
            error_log("Error sending message to websocket queue: " . pg_last_error($this->conn));
            return false;
        }

        return true;
    }

    /**
     * 1) Grab new rows from output_queue_websocket
     * 2) For each row: try JSON decoding to see if it matches our 
     *    expected {character, listener, mood, action, target, lang, message} structure
     * 3) Accumulate “message” into $_llmResponseBuffer
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

        $params = [$this->last_processed_timestamp ? $this->last_processed_timestamp : '1970-01-01 00:00:00'];
        $result = pg_execute($this->conn, self::FETCH_RESPONSE_STATEMENT, $params);

        if (!$result) {
            error_log("Error fetching messages from websocket queue: " . pg_last_error($this->conn));
            return "";
        }

        $new_last_timestamp = $this->last_processed_timestamp;
        $collectedOutput    = ""; // for returning new text to tests.php

        while ($row = pg_fetch_assoc($result)) {
            $response = $row['response'];

            // Skip empty or whitespace-only responses
            if (empty(trim($response))) {
                continue;
            }

            // Accumulate raw JSON (or raw text) in an array for debugging
            $this->_rawJsonBuffer[] = $response;

            // Try to decode as JSON
            $decoded = json_decode($response, true);
            if (
                is_array($decoded) &&
                isset($decoded['character']) &&
                isset($decoded['message'])
            ) {
                // This looks like a well-formed JSON response 
                // that has at least "character" and "message".
                $this->_parsedData[] = $decoded; // store for processActions()

                // Append the "message" part to the LLM Response buffer
                $collectedOutput .= $decoded['message'] . "\n";
            } else {
                // If not valid JSON or missing fields, just treat the entire response as text
                $collectedOutput .= $response . "\n";
            }

            $new_last_timestamp = $row['timestamp'];

            // Delete the processed message from output_queue_websocket
            $this->deleteMessage($row['id']);
        }

        if ($collectedOutput !== "") {
            // Add the newly collected chunk to our overall LLM Response buffer
            $this->_llmResponseBuffer .= $collectedOutput;

            // Store in debug data
            // We'll combine all raw JSON lines with newlines
            $DEBUG_DATA['RAW'] = implode("\n", $this->_rawJsonBuffer);

            // Update the last processed timestamp
            $this->last_processed_timestamp = $new_last_timestamp;

            // Return the newly collected text
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
     * If we haven’t received anything, not done.  
     * If we have, and there are no more new rows since last timestamp, we’re done.
     */
    public function isDone()
    {
        if (empty(trim($this->_llmResponseBuffer))) {
            return false;
        }

        if (!$this->conn) {
            $this->connectDB();
            if (!$this->conn) {
                return true; 
            }
        }

        $params = [$this->last_processed_timestamp ? $this->last_processed_timestamp : '1970-01-01 00:00:00'];
        $result = pg_execute($this->conn, self::FETCH_RESPONSE_STATEMENT, $params);

        if (!$result) {
            error_log("Error fetching messages from websocket queue in isDone: " . pg_last_error($this->conn));
            return true; 
        }

        return (pg_num_rows($result) === 0);
    }

    /**
     * Look at each parsed JSON object from the LLM 
     * and convert “action” + “target” into lines like:
     *   The Narrator|command|Attack@Jesse
     * 
     * This logic is borrowed from openaijson.php 
     * but simplified. Tweak as you see fit.
     */
    public function processActions()
    {
        global $ALREADY_SENT_BUFFER, $HERIKA_NAME;
        if (!isset($ALREADY_SENT_BUFFER)) {
            $ALREADY_SENT_BUFFER = [];
        }

        // Loop through all the parsed JSON objects we got
        foreach ($this->_parsedData as $parsed) {
            $action = isset($parsed['action']) ? $parsed['action'] : null;
            $target = isset($parsed['target']) ? $parsed['target'] : null;

            // If no action or "Talk", skip
            if (empty($action) || $action === 'Talk') {
                continue;
            }

            // Example: "The Narrator|command|Attack@Jesse"
            // We will keep it consistent with openaijson format:
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

/**
 * Example stub function to mirror openaijson’s approach. 
 * In practice, you’d want to use your real “findFunctionByName()”.
 */
if (!function_exists('findFunctionByName')) {
    function findFunctionByName($name)
    {
        // You can adapt a real lookup from your own action table:
        $validActions = [
            'Attack', 'Inspect', 'InspectSurroundings', 'Hunt', 'ListInventory', 'ExchangeItems',
            'Heal', 'BeginTrading', 'StopLooting', 'StartLooting', 'WaitHere', 'Talk',
            'LeadTheWayTo', 'LetsRelax', 'SetCurrentTask', 'IncreaseWalkSpeed', 'DecreaseWalkSpeed',
            'TakeASeat', 'ReadQuestJournal'
        ];
        if (in_array($name, $validActions)) {
            // Return something that indicates a recognized function. 
            // This can be more detailed in your real code.
            return ['parameters' => ['required'=>['target']]];
        }
        return null;
    }
}
