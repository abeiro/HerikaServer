<?php
session_start();

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "🔍 Dwemer Diagnostics";
$debugPaneLink = false;

// Start output buffering
ob_start();

// Include head template first
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

// Include navbar after head
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any existing output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    // Database connection details
    $host = 'localhost';
    $port = '5432';
    $dbname = 'dwemer';
    $schema = 'public';
    $username = 'dwemer';
    $password = 'dwemer';

    // Connect to the database
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

    if (!$conn) {
        die(json_encode([
            'error' => 'Failed to connect to database: ' . pg_last_error()
        ]));
    }

    // Get the POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $query = strtolower(trim($data['query'] ?? ''));

    if (empty($query)) {
        die(json_encode([
            'error' => 'No query provided'
        ]));
    }

    // Process help command
    if ($query === 'help') {
        die(json_encode([
            'response' => "Available commands:\n" .
                "- show tables: List all available tables\n" .
                "- describe [table_name]: Show structure of a table\n" .
                "- list [table_name] [count]: Show latest entries in a table (default 10)\n" .
                "You can also ask natural language questions!"
        ]));
    }

    // Process list command
    if (preg_match('/^list\s+(\w+)(?:\s+(\d+))?$/', $query, $matches)) {
        $table_name = pg_escape_string($matches[1]);
        $limit = isset($matches[2]) ? intval($matches[2]) : 10;
        
        // Verify table exists
        $table_check = pg_query($conn, "
            SELECT 1 
            FROM information_schema.tables 
            WHERE table_schema = '$schema' 
            AND table_name = '$table_name'
        ");
        
        if (!$table_check || pg_num_rows($table_check) === 0) {
            die(json_encode([
                'error' => "Table '$table_name' not found"
            ]));
        }
        
        // Get the primary key or first column for ordering
        $pk_result = pg_query($conn, "
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = '$schema'
            AND table_name = '$table_name'
            AND is_identity = 'YES'
            LIMIT 1
        ");
        
        $order_by = '1'; // Default to first column if no identity column found
        if ($pk_result && pg_num_rows($pk_result) > 0) {
            $pk_row = pg_fetch_assoc($pk_result);
            $order_by = pg_escape_string($pk_row['column_name']);
        }
        
        // Execute the query
        $sql = "SELECT * FROM $table_name ORDER BY $order_by DESC LIMIT $limit";
        $result = pg_query($conn, $sql);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error executing query: ' . pg_last_error($conn)
            ]));
        }
        
        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        
        echo json_encode([
            'response' => "Latest $limit entries from table '$table_name':",
            'table_data' => $rows
        ]);
        exit;
    }

    // Process show tables command
    if ($query === 'show tables') {
        $sql = "
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = '$schema' 
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ";
        
        $result = pg_query($conn, $sql);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error fetching tables: ' . pg_last_error($conn)
            ]));
        }
        
        $tables = [];
        while ($row = pg_fetch_assoc($result)) {
            $tables[] = $row;
        }
        
        echo json_encode([
            'response' => 'Available tables in the database:',
            'table_data' => $tables
        ]);
        exit;
    }

    // Process describe table command
    if (preg_match('/^describe\s+(\w+)$/', $query, $matches)) {
        $table_name = pg_escape_string($matches[1]);
        
        $sql = "
            SELECT 
                column_name,
                data_type,
                character_maximum_length,
                column_default,
                is_nullable
            FROM information_schema.columns
            WHERE table_schema = '$schema'
            AND table_name = '$table_name'
            ORDER BY ordinal_position
        ";
        
        $result = pg_query($conn, $sql);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error describing table: ' . pg_last_error($conn)
            ]));
        }
        
        $columns = [];
        while ($row = pg_fetch_assoc($result)) {
            $columns[] = $row;
        }
        
        if (empty($columns)) {
            die(json_encode([
                'error' => "Table '$table_name' not found"
            ]));
        }
        
        echo json_encode([
            'response' => "Structure of table '$table_name':",
            'table_data' => $columns
        ]);
        exit;
    }

    // Process direct SQL query
    if (preg_match('/^SELECT\s+.*FROM\s+(\w+)/i', $query, $matches)) {
        $table_name = pg_escape_string($matches[1]);
        
        // Verify table exists
        $table_check = pg_query($conn, "
            SELECT 1 
            FROM information_schema.tables 
            WHERE table_schema = '$schema' 
            AND table_name = '$table_name'
        ");
        
        if (!$table_check || pg_num_rows($table_check) === 0) {
            die(json_encode([
                'error' => "Table '$table_name' not found"
            ]));
        }
        
        // Execute the query
        $result = pg_query($conn, $query);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error executing query: ' . pg_last_error($conn)
            ]));
        }
        
        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        
        echo json_encode([
            'table_data' => $rows
        ]);
        exit;
    }

    // If no specific command matched, use OpenRouter for natural language query
    try {
        // Get settings from the request
        $settings = $data['settings'] ?? null;
        if (!$settings || empty($settings['apiKey'])) {
            http_response_code(500);
            die(json_encode([
                'error' => 'OpenRouter API key not provided'
            ]));
        }

        // Disable error output to response
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        
        // Set error handler to catch any PHP errors
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
            throw new Exception($errstr);
        });

        // Prepare context
        $context = [];
        $context[] = [
            'role' => 'system',
            'content' => "You are the Dwemer Bot, an AI assistant helping users understand and analyze the PSQL data for a Skyrim mod called CHIM.
                         CHIM is a mod that makes NPC's alive using AI. It allows for users to interact with these AI's in the game of Skyrim.
                         You have access to information about the database schema and can help interpret data patterns and relationships.
                         Always be precise and technical in your responses, but explain things in a way that's easy to understand.
                         
                         IMPORTANT: When answering questions about data in the database:
                         1. If the user's query is vague or unclear (like single words or very short phrases), ALWAYS ask for clarification
                         2. Only proceed with queries when you have a clear understanding of what the user wants to know
                         3. When you do have a clear query, start with a basic query to get initial information
                         4. Based on those results, make additional queries to gather more specific details
                         5. Use the results from earlier queries to inform later queries
                         6. Continue making queries until you have gathered all relevant information
                         7. ALWAYS provide a final analysis that combines insights from all queries
                         
                         For NPCs and characters:
                         - Location information is stored in the npc_pers column
                         - Personal details and background are in npc_pers
                         - Current state and dynamic information in npc_dynamic

                         For Context and events:
                         - eventlog table contains all context for everything that has happended ingame.
                         - currentmission table is the Dynamic AI objective.
                         - questlog table showcases all the quests that have happended.
                         - quests tableshowcases the current active quests.
                         - diarylog tablehas all diary entries for NPCs.
                         - books table has all the current books read through CHIM.

                         System Context:
                         - conf_opts is for misclenaious options
                         - audit_memory is for Minime-T5 output. It include info for Oghma (RAG world info for Skyrim) and memory requests.

                         Example format for multiple queries:
                         ```sql
                         -- First query to get basic count
                         SELECT COUNT(*) as total FROM npc_templates WHERE npc_pers LIKE '%Whiterun%';
                         ```
                         Initial findings: There are X NPCs from Whiterun.
                         
                         ```sql
                         -- Second query to get details about these NPCs
                         SELECT npc_name, npc_pers 
                         FROM npc_templates 
                         WHERE npc_pers LIKE '%Whiterun%';
                         ```
                         Additional findings: Here are the specific NPCs and their roles...
                         
                         Final analysis: [Comprehensive explanation combining all findings]
                         
                         Remember:
                         - Each query should build on information from previous queries
                         - Don't stop at just one query unless you're absolutely sure you have all needed information
                         - Use the executeQuery() function with proper parameter escaping to prevent SQL injection
                         - ALWAYS explain your thought process between queries
                         - ALWAYS provide a final analysis after all queries
                         - You can query log files to get additional context about the system's behavior
                         - If the query is unclear, ask for clarification instead of making assumptions"
        ];

        // Add database schema info
        $tables_result = pg_query($conn, "
            SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '$schema'
            ORDER BY table_name, ordinal_position
        ");

        if (!$tables_result) {
            throw new Exception('Error fetching schema information: ' . pg_last_error($conn));
        }

        $schema_info = "Database Schema:\n";
        $current_table = '';
        while ($row = pg_fetch_assoc($tables_result)) {
            if ($current_table != $row['table_name']) {
                $schema_info .= "\nTable: {$row['table_name']}\n";
                $current_table = $row['table_name'];
            }
            $schema_info .= "  - {$row['column_name']} ({$row['data_type']})\n";
        }

        $context[] = [
            'role' => 'system',
            'content' => $schema_info
        ];

        // Add the user's query
        $context[] = [
            'role' => 'user',
            'content' => $query
        ];

        // Add helper function for executing SQL queries
        function executeQuery($conn, $sql, $params = []) {
            // If no parameters provided, just execute the query directly
            if (empty($params)) {
                // Execute query
                $result = pg_query($conn, $sql);
                if (!$result) {
                    throw new Exception("Query failed: " . pg_last_error($conn));
                }
                
                // Fetch results
                $rows = [];
                while ($row = pg_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                
                return $rows;
            }
            
            // If parameters are provided, use vsprintf
            // Escape parameters
            foreach ($params as $key => $value) {
                $params[$key] = pg_escape_string($value);
            }
            
            // Replace placeholders with escaped values
            $sql = vsprintf($sql, $params);
            
            // Execute query
            $result = pg_query($conn, $sql);
            if (!$result) {
                throw new Exception("Query failed: " . pg_last_error($conn));
            }
            
            // Fetch results
            $rows = [];
            while ($row = pg_fetch_assoc($result)) {
                $rows[] = $row;
            }
            
            return $rows;
        }

        // Add query execution example to system prompt
        $context[] = [
            'role' => 'system',
            'content' => "Example of executing a query:
                         ```php
                         \$rows = executeQuery(\$conn, 'SELECT * FROM %s WHERE location LIKE \'%%%s%%\'', ['eventlog', 'Whiterun']);
                         ```
                         This will safely escape parameters and return an array of results."
        ];

        // Prepare the API request
        $request_data = [
            'model' => $settings['model'],
            'messages' => $context,
            'temperature' => floatval($settings['temperature']),
            'max_tokens' => intval($settings['maxTokens']),
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'top_p' => 0.95,
            'stream' => false
        ];

        // Add debug logging for model ID
        error_log("Using model ID: " . $settings['model']);

        // Add OpenRouter specific parameters
        if (strpos($settings['model'], 'anthropic/claude-3') !== false) {
            $request_data['safe_mode'] = false;
            $request_data['top_k'] = 50;
            $request_data['top_p'] = 0.95;
        }

        // Initialize cURL session
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        
        // Set cURL options with improved security headers and OpenRouter specific headers
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $settings['apiKey'],
                'HTTP-Referer: https://www.nexusmods.com/skyrimspecialedition/mods/126330',
                'X-Title: Dwemer Diagnostics',
                'Accept: application/json',
                'Origin: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        // Add debug logging for request data
        error_log("OpenRouter Request Data: " . json_encode($request_data, JSON_PRETTY_PRINT));
        
        // Execute the request
        $response = curl_exec($ch);
        
        // Get HTTP status code and response info for debugging
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_info = curl_getinfo($ch);
        
        // Log response for debugging
        error_log("OpenRouter Response Code: " . $http_code);
        error_log("OpenRouter Response: " . $response);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: $error");
        }
        
        // Close cURL session
        curl_close($ch);

        // Check for non-200 HTTP response with detailed error
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "Received status code $http_code";
            throw new Exception("OpenRouter API Error: $error_message");
        }

        // Decode the response with error checking
        $response_data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        // Check for API errors with detailed message
        if (isset($response_data['error'])) {
            $error_msg = isset($response_data['error']['message']) 
                ? $response_data['error']['message'] 
                : json_encode($response_data['error']);
            throw new Exception('API Error: ' . $error_msg);
        }

        // Extract and validate the response text
        $response_text = $response_data['choices'][0]['message']['content'] ?? null;
        if ($response_text === null) {
            throw new Exception('Unexpected API response format: Missing response text');
        }
        
        if (empty($response_text)) {
            throw new Exception('Empty response from AI');
        }

        // Extract SQL queries and execute them sequentially
        $queries = [];
        $current_position = 0;
        $combined_results = [];
        $thinking_texts = [];
        
        while (preg_match('/```sql\s*(.*?)\s*```/s', $response_text, $matches, PREG_OFFSET_CAPTURE, $current_position)) {
            $sql_query = trim($matches[1][0]);
            $query_start = $matches[0][1];
            
            // Get the thinking text before this query (after the previous query or from the start)
            $thinking_text = "";
            if (count($combined_results) === 0) {
                // For the first query, get text from start to this query
                $thinking_text = trim(substr($response_text, 0, $query_start));
            } else {
                // For subsequent queries, get text between previous query and this one
                $thinking_text = trim(substr($response_text, $current_position, $query_start - $current_position));
            }
            
            if (!empty($thinking_text)) {
                $thinking_texts[] = $thinking_text;
            }
            
            try {
                // Execute the query
                $result = executeQuery($conn, $sql_query);
                $combined_results[] = [
                    'query' => $sql_query,
                    'results' => $result,
                    'thinking' => end($thinking_texts) // Attach the thinking text to this query
                ];
            } catch (Exception $e) {
                throw new Exception("Error executing SQL query: " . $e->getMessage());
            }
            
            $current_position = $matches[0][1] + strlen($matches[0][0]);
        }
        
        // Get the final explanation (after the last SQL block)
        $final_explanation = trim(substr($response_text, $current_position));
        
        // If no final explanation was found or it's empty, extract it from the last thinking text
        if (empty($final_explanation) && !empty($thinking_texts)) {
            $final_explanation = end($thinking_texts);
        }
        
        // Ensure we always have a final explanation
        if (empty($final_explanation)) {
            $final_explanation = "Based on the query results above, here's what we found...";
        }
        
        // Prepare the response payload with all queries, results, and thinking texts
        $response_payload = [
            'response' => $response_text,
            'queries' => $combined_results,
            'final_explanation' => $final_explanation
        ];
        
        if (isset($_ENV['DEVELOPMENT']) && $_ENV['DEVELOPMENT'] === true) {
            $response_payload['debug'] = [
                'model' => $settings['model'],
                'status_code' => $http_code,
                'response_length' => strlen($response_text)
            ];
        }

        echo json_encode($response_payload);
        
    } catch (Exception $e) {
        error_log("Dwemer Diagnostics Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'error' => 'AI Processing Error: ' . $e->getMessage()
        ]);
    } finally {
        // Restore error handler
        restore_error_handler();
    }
    exit;
}

// For non-AJAX requests, continue with the page content
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 40px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
        padding-left: 10px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px; /* Reduced footer height */
        background: #031633;
        z-index: 100;
    }

    /* Rest of your existing styles */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #1e1e1e;
        color: #d4d4d4;
    }
    .container {
        display: flex;
        gap: 20px;
        height: calc(100vh - 100px);
        padding: 160px 10px 0 10px; /* Added back top padding */
        width: 95%;
        margin-left: auto;
        margin-right: auto;
    }
    .tables-section {
        flex: 0 0 300px;
        background-color: #2d2d2d;
        border-radius: 8px;
        padding: 15px;
        overflow-y: auto;
    }
    .table-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .table-item {
        padding: 8px 12px;
        margin-bottom: 5px;
        background-color: #1e1e1e;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .table-item:hover {
        background-color: #3e3e3e;
    }
    .table-item.active {
        background-color: #0e639c;
        color: white;
    }
    .table-item .table-name {
        flex: 1;
    }
    .table-item .table-icon {
        margin-left: 10px;
        opacity: 0.7;
    }
    .chat-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        background-color: #2d2d2d;
        border-radius: 8px;
        padding: 15px;
        max-width: 65%;
        min-width: 50%;
        position: relative; /* Added for absolute positioning of loading overlay */
    }
    #chatWindow {
        flex: 1;
        overflow-y: auto;
        margin-bottom: 15px;
        padding: 10px;
        background-color: #1e1e1e;
        border-radius: 4px;
        font-family: 'Consolas', 'Courier New', monospace;
        white-space: pre-wrap;
    }
    .input-container {
        display: flex;
        gap: 10px;
        padding: 10px 0;
        position: relative;
    }
    #inputText {
        flex: 1;
        padding: 10px;
        padding-right: 40px; /* Make room for the spinner */
        border: 1px solid #3e3e3e;
        border-radius: 4px;
        background-color: #1e1e1e;
        color: #d4d4d4;
    }
    #inputText:disabled {
        background-color: #2d2d2d;
        cursor: not-allowed;
        opacity: 0.7;
    }
    .loading-overlay {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    .loading-spinner {
        width: 80px;
        height: 80px;
        border: 8px solid #1e1e1e;
        border-top: 8px solid #0e639c;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    button {
        padding: 10px 20px;
        background-color: #0e639c;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    button:hover {
        background-color: #1177bb;
    }
    .help-section {
        margin-bottom: 15px;
        padding: 10px;
        background-color: #3e3e3e;
        border-radius: 4px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        background-color: #1e1e1e;
    }
    th, td {
        padding: 8px;
        text-align: left;
        border: 1px solid #3e3e3e;
    }
    th {
        background-color: #2d2d2d;
    }
    .loading {
        display: none;
        margin: 10px 0;
        color: #0e639c;
        align-items: center;
        gap: 10px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .ai-response {
        background-color: #2d2d2d;
        padding: 10px;
        margin-top: 10px;
        border-left: 3px solid #0e639c;
    }
    .sql-query {
        background-color: #1e1e1e;
        padding: 10px;
        margin-top: 10px;
        border-left: 3px solid #569cd6;
        font-family: 'Consolas', 'Courier New', monospace;
        white-space: pre;
    }
    .settings-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
    }
    .settings-content {
        position: relative;
        background-color: #2d2d2d;
        margin: 15% auto;
        padding: 20px;
        width: 50%;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .settings-close {
        position: absolute;
        right: 20px;
        top: 10px;
        font-size: 24px;
        cursor: pointer;
        color: #d4d4d4;
    }
    .settings-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .form-group label {
        font-weight: bold;
    }
    .form-group input, .form-group select {
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #3e3e3e;
        background-color: #1e1e1e;
        color: #d4d4d4;
    }
    .settings-button {
        padding: 10px 20px;
        background-color: #0e639c;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 14px;
    }
    .settings-button:hover {
        background-color: #1177bb;
    }
    .table-data-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
    }
    .table-data-content {
        position: relative;
        background-color: #2d2d2d;
        margin: 5% auto;
        padding: 20px;
        width: 80%;
        max-height: 80vh;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
    }
    .table-data-close {
        position: absolute;
        right: 20px;
        top: 10px;
        font-size: 24px;
        cursor: pointer;
        color: #d4d4d4;
    }
    .table-data-title {
        margin-top: 0;
        margin-bottom: 20px;
        color: #0e639c;
    }
    .table-data-table {
        width: 100%;
        margin-top: 10px;
        background-color: #1e1e1e;
    }
    .table-data-table th {
        position: sticky;
        top: 0;
        background-color: #2d2d2d;
        z-index: 1;
    }
    .toast-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
    }
    .toast {
        background-color: #2d2d2d;
        color: #d4d4d4;
        padding: 12px 24px;
        border-radius: 4px;
        margin-top: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        max-width: 300px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
    }
    .toast .toast-icon {
        color: #0e639c;
    }
    .toast .toast-content {
        flex: 1;
    }
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    .system-message {
        background-color: #2d2d2d;
        padding: 10px;
        margin: 5px 0;
        border-radius: 4px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .system-message .user-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .system-message .message-content {
        flex: 1;
    }
    .user-message {
        background-color: #1e1e1e;
        padding: 10px;
        margin: 5px 0;
        border-radius: 4px;
        text-align: left;
        max-width: 80%;
        margin-right: auto;
    }
</style>

<main>
    <div class="container">
        <div class="tables-section">
            <h3>Database Tables</h3>
            <ul class="table-list" id="tableList">
                <!-- Tables will be populated here -->
            </ul>
        </div>
        <div class="chat-section">
            <div class="help-section">
                <h3>🔍 Dwemer AI Diagnostics (WIP)</h3>
                <p>A tool to have an AI scan through the CHIM database. Just ask it a question!</p>
                <p>OpenRouter only, configure in settings.</p>
            </div>
            <div id="chatWindow"></div>
            <div class="loading-overlay" id="loadingIndicator">
                <div class="loading-spinner"></div>
            </div>
            <div class="input-container">
                <input type="text" id="inputText" placeholder="Enter your question or type 'help' for available commands">
                <button onclick="sendQuery()" id="sendButton">Send</button>
                <button class="settings-button" onclick="openSettings()">⚙️</button>
                <button class="settings-button" onclick="clearChat()">🗑️</button>
            </div>
        </div>
    </div>
</main>

<div id="settingsModal" class="settings-modal">
    <div class="settings-content">
        <span class="settings-close" onclick="closeSettings()">&times;</span>
        <h2>Dwemer Diagnostics Settings</h2>
        <form id="settingsForm" class="settings-form">
            <div class="form-group">
                <label for="apiKey">OpenRouter API Key (Pulled from CONNECTOR openrouterjson API_KEY)</label>
                <input type="password" id="apiKey" name="apiKey" required>
            </div>
            <div class="form-group">
                <label for="model">Model</label>
                <select id="model" name="model">
                    <option value="anthropic/claude-3-sonnet">Claude 3 Sonnet</option>
                    <option value="deepseek/deepseek-r1">DeepSeek R1</option>
                    <option value="openai/gpt-4o-search-preview">GPT 4o</option>
                </select>
            </div>
            <div class="form-group">
                <label for="temperature">Temperature (0-1)</label>
                <input type="number" id="temperature" name="temperature" min="0" max="1" step="0.1" value="0.7">
            </div>
            <div class="form-group">
                <label for="maxTokens">Max Tokens</label>
                <input type="number" id="maxTokens" name="maxTokens" min="100" max="4000" value="500">
            </div>
            <button type="submit" class="button">Save Settings</button>
        </form>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
    const chatWindow = document.getElementById('chatWindow');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const input = document.getElementById('inputText');

    function clearChat() {
        chatWindow.innerHTML = '';
    }

    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendQuery();
        }
    });

    function appendMessage(text, isUser = false, type = 'message') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `${type} ${isUser ? 'user-message' : 'system-message'}`;
        
        if (isUser) {
            messageDiv.textContent = text;
        } else {
            const iconDiv = document.createElement('div');
            iconDiv.className = 'user-icon';
            iconDiv.style.backgroundImage = 'url(images/DwemerDynamics.png)';
            iconDiv.style.backgroundSize = 'cover';
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            
            if (type === 'sql-query') {
                contentDiv.innerHTML = '<strong>SQL Query:</strong><br>' + text;
            } else {
                contentDiv.textContent = text;
            }
            
            messageDiv.appendChild(iconDiv);
            messageDiv.appendChild(contentDiv);
        }
        
        chatWindow.appendChild(messageDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function displayResults(data) {
        if (data.queries) {
            data.queries.forEach((queryData, index) => {
                if (queryData.thinking) {
                    appendMessage(queryData.thinking, false, 'ai-response');
                }
                
                appendMessage(queryData.query, false, 'sql-query');
                
                if (queryData.results && queryData.results.length > 0) {
                    const table = createTable(queryData.results);
                    const tableDiv = document.createElement('div');
                    tableDiv.className = 'query-section';
                    tableDiv.appendChild(table);
                    chatWindow.appendChild(tableDiv);
                    chatWindow.scrollTop = chatWindow.scrollHeight;
                }
            });
            
            if (data.final_explanation) {
                appendMessage(data.final_explanation, false, 'ai-response');
            }
        } else if (data.table_data) {
            // Extract table name from the response
            const tableName = data.response.match(/Structure of table '(\w+)':/)?.[1];
            if (tableName && tableDescriptions[tableName]) {
                appendMessage(tableDescriptions[tableName], false, 'ai-response');
            }
            
            const table = createTable(data.table_data);
            const tableDiv = document.createElement('div');
            tableDiv.className = 'query-section';
            tableDiv.appendChild(table);
            chatWindow.appendChild(tableDiv);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        } else if (data.response) {
            appendMessage(data.response, false);
        }
    }

    function createTable(data) {
        const table = document.createElement('table');
        
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        Object.keys(data[0]).forEach(key => {
            const th = document.createElement('th');
            th.textContent = key;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.forEach(row => {
            const tr = document.createElement('tr');
            Object.values(row).forEach(value => {
                const td = document.createElement('td');
                td.textContent = value;
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        
        return table;
    }

    let aiSettings = {
        apiKey: localStorage.getItem('dwemer_api_key') || '',
        model: localStorage.getItem('dwemer_model') || 'anthropic/claude-3-sonnet',
        temperature: parseFloat(localStorage.getItem('dwemer_temperature')) || 0.7,
        maxTokens: parseInt(localStorage.getItem('dwemer_max_tokens')) || 500
    };

    function openSettings() {
        document.getElementById('settingsModal').style.display = 'block';
        document.getElementById('apiKey').value = aiSettings.apiKey;
        document.getElementById('model').value = aiSettings.model;
        document.getElementById('temperature').value = aiSettings.temperature;
        document.getElementById('maxTokens').value = aiSettings.maxTokens;
    }

    function closeSettings() {
        document.getElementById('settingsModal').style.display = 'none';
    }

    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        aiSettings = {
            apiKey: document.getElementById('apiKey').value,
            model: document.getElementById('model').value,
            temperature: parseFloat(document.getElementById('temperature').value),
            maxTokens: parseInt(document.getElementById('maxTokens').value)
        };
        
        localStorage.setItem('dwemer_api_key', aiSettings.apiKey);
        localStorage.setItem('dwemer_model', aiSettings.model);
        localStorage.setItem('dwemer_temperature', aiSettings.temperature);
        localStorage.setItem('dwemer_max_tokens', aiSettings.maxTokens);
        
        closeSettings();
        appendMessage('Settings saved successfully', false);
    });

    async function sendQuery() {
        const input = document.getElementById('inputText');
        const sendButton = document.getElementById('sendButton');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const query = input.value.trim();
        
        if (!query) return;
        if (!aiSettings.apiKey) {
            appendMessage('Error: Please configure your OpenRouter API key in settings', false);
            return;
        }

        appendMessage(query, true);
        input.value = '';
        input.disabled = true;
        sendButton.disabled = true;
        loadingIndicator.style.display = 'flex';

        try {
            const response = await fetch('dwemer-diagnostics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    query: query,
                    settings: aiSettings
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const errorText = await response.text();
                let errorMessage;
                try {
                    const errorJson = JSON.parse(errorText);
                    errorMessage = errorJson.error || 'Unknown error occurred';
                } catch (e) {
                    errorMessage = `Server error: ${response.status} ${response.statusText}`;
                }
                throw new Error(errorMessage);
            }

            const data = await response.json();
            
            if (data.error) {
                appendMessage('Error: ' + data.error, false);
            } else {
                displayResults(data);
            }
        } catch (error) {
            console.error('Error details:', error);
            appendMessage('Error: ' + error.message, false);
        } finally {
            input.disabled = false;
            sendButton.disabled = false;
            loadingIndicator.style.display = 'none';
            input.focus();
        }
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', () => {
        loadTables();
    });

    // Add table descriptions
    const tableDescriptions = {
        'animations': 'Animation table, still WIP.',
        'animations_custom': 'Custom animations for custom animation mods for CHIM.',
        'audit_memory': 'Minime-T5 Output log. Showcase memeory and Oghma extraction attempts.',
        'audit_request': 'LLM Context.',
        'books': 'All the book content extracted by CHIM.',
        'conf_opts': 'Custom table used for misclenaious options.',
        'currentmission': 'The current Dynamic AI Objectives.',
        'database_versioning': 'Used for automatic database updates.',
        'diarylog': 'All the NPC diary entries.',
        'eventlog': 'All the events and current context from CHIM.',
        'log': 'Response Log. Useful for examining prompts sent to LLM.',
        'memory': 'Basic memory entries.',
        'memory_summary': 'Summarized memory entries.',
        'npc_profile_backup': 'Backup of NPC profiles.',
        'npc_templates': 'Vanilla CHIM NPC templates. Gets overwritten by custom templates.',
        'npc_templates_custom': 'User-modified NPC templates with custom attributes and behaviors.',
        'npc_templates_trl': 'Translation-specific NPC templates for different language versions.',
        'npc_templates_v2': 'Not activly used, is transfered over to npc_templates during updates.',
        'oghma': 'Knowledge base containing game lore, quest information, and world data that gets injected into prompts using RAG/Minime-T5.',
        'questlog': 'Comprehensive log of every quest and stage you have completed.',
        'quests': 'Current active quests in your quest journal.',
        'responselog': 'Usually empty, used temporaily for inserting responses.',
        'speech': 'Raw speech output from NPCs.'
    };

    // Toast notification function
    function showToast(message, duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
            <div class="toast-icon">ℹ️</div>
            <div class="toast-content">${message}</div>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                container.removeChild(toast);
            }, 300);
        }, duration);
    }

    // Modify the table list creation
    async function loadTables() {
        try {
            const response = await fetch('dwemer-diagnostics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    query: 'show tables',
                    settings: aiSettings
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load tables');
            }

            const data = await response.json();
            if (data.error) {
                console.error('Error loading tables:', data.error);
                return;
            }

            const tableList = document.getElementById('tableList');
            if (data.table_data) {
                data.table_data.forEach(row => {
                    const tableName = row.table_name;
                    const li = document.createElement('li');
                    li.className = 'table-item';
                    li.innerHTML = `
                        <span class="table-name">${tableName}</span>
                    `;
                    
                    li.addEventListener('click', () => {
                        document.querySelectorAll('.table-item').forEach(item => {
                            item.classList.remove('active');
                        });
                        li.classList.add('active');
                        
                        // Set the input text to the list command
                        const input = document.getElementById('inputText');
                        input.value = `list ${tableName}`;
                    });
                    
                    tableList.appendChild(li);
                });
            }
        } catch (error) {
            console.error('Error loading tables:', error);
        }
    }
</script>

<?php
// Include footer template
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

// Get the buffered content
$buffer = ob_get_contents();
ob_end_clean();

// Replace the title if needed
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);

// Output the final content
echo $buffer;
?> 