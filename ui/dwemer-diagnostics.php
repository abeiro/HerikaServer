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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                "- search [table_name] for [text]: Search in a table\n" .
                "- show recent events: Display recent events\n" .
                "- show npcs: List all NPCs\n" .
                "You can also ask natural language questions!"
        ]));
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

    // Process search command
    if (preg_match('/^search\s+(\w+)\s+for\s+(.+)$/', $query, $matches)) {
        $table_name = pg_escape_string($matches[1]);
        $search_term = pg_escape_string($matches[2]);
        
        // First verify the table exists
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
        
        // Get all columns for the table
        $columns_result = pg_query($conn, "
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '$schema'
            AND table_name = '$table_name'
        ");
        
        if (!$columns_result) {
            die(json_encode([
                'error' => 'Error getting table structure: ' . pg_last_error($conn)
            ]));
        }
        
        // Build the search query
        $search_conditions = [];
        while ($col = pg_fetch_assoc($columns_result)) {
            if (strpos($col['data_type'], 'character') !== false || 
                strpos($col['data_type'], 'text') !== false) {
                $search_conditions[] = "{$col['column_name']}::text ILIKE '%$search_term%'";
            }
        }
        
        if (empty($search_conditions)) {
            die(json_encode([
                'error' => 'No text columns found to search in'
            ]));
        }
        
        $search_sql = "
            SELECT *
            FROM $schema.$table_name
            WHERE " . implode(' OR ', $search_conditions) . "
            LIMIT 100
        ";
        
        $result = pg_query($conn, $search_sql);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error searching table: ' . pg_last_error($conn)
            ]));
        }
        
        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        
        echo json_encode([
            'response' => count($rows) . " results found in '$table_name' for '$search_term':",
            'table_data' => $rows
        ]);
        exit;
    }

    // Process show recent events command
    if ($query === 'show recent events') {
        $sql = "
            SELECT type, data, people, location, to_timestamp(localts::double precision) as timestamp
            FROM $schema.eventlog
            ORDER BY localts DESC
            LIMIT 50
        ";
        
        $result = pg_query($conn, $sql);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error fetching recent events: ' . pg_last_error($conn)
            ]));
        }
        
        $events = [];
        while ($row = pg_fetch_assoc($result)) {
            $events[] = $row;
        }
        
        echo json_encode([
            'response' => 'Most recent events:',
            'table_data' => $events
        ]);
        exit;
    }

    // Process show NPCs command
    if ($query === 'show npcs' || $query === 'how many npcs' || $query === 'count npcs') {
        // First get the total count
        $count_sql = "SELECT COUNT(DISTINCT npc_name) as npc_count FROM $schema.combined_npc_templates";
        $count_result = pg_query($conn, $count_sql);
        
        if (!$count_result) {
            die(json_encode([
                'error' => 'Error counting NPCs: ' . pg_last_error($conn)
            ]));
        }
        
        $count_row = pg_fetch_assoc($count_result);
        $npc_count = $count_row['npc_count'];
        
        // Then get the list of NPCs if requested
        $sql = "
            SELECT npc_name, npc_pers, npc_dynamic
            FROM $schema.combined_npc_templates
            ORDER BY npc_name
        ";
        
        $result = pg_query($conn, $sql);
        
        if (!$result) {
            die(json_encode([
                'error' => 'Error fetching NPCs: ' . pg_last_error($conn)
            ]));
        }
        
        $npcs = [];
        while ($row = pg_fetch_assoc($result)) {
            $npcs[] = $row;
        }
        
        echo json_encode([
            'response' => "There are $npc_count unique NPCs in the database:",
            'table_data' => $npcs
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

    // Process read log command
    if (preg_match('/^read\s+log\s+(\w+\.log)$/', $query, $matches)) {
        $log_file = $matches[1];
        $log_path = __DIR__ . '/../log/' . $log_file;
        
        if (!file_exists($log_path)) {
            die(json_encode([
                'error' => "Log file '$log_file' not found"
            ]));
        }
        
        if (!is_readable($log_path)) {
            die(json_encode([
                'error' => "Log file '$log_file' is not readable"
            ]));
        }
        
        // Read the entire log file
        $content = file_get_contents($log_path);
        if ($content === false) {
            die(json_encode([
                'error' => "Error reading log file '$log_file'"
            ]));
        }
        
        // Format the response
        echo json_encode([
            'response' => $content
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
            'content' => "You are a Dwemer Diagnostics AI assistant, helping users understand and analyze their Skyrim database. 
                         You have access to information about the database schema and can help interpret data patterns and relationships.
                         Always be precise and technical in your responses, but explain things in a way that's easy to understand.
                         
                         IMPORTANT: When answering questions about data in the database:
                         1. ALWAYS start with a basic query to get initial information
                         2. Based on those results, make additional queries to gather more specific details
                         3. Use the results from earlier queries to inform later queries
                         4. Continue making queries until you have gathered all relevant information
                         5. ALWAYS provide a final analysis that combines insights from all queries
                         
                         For NPCs:
                         - Location information is stored in the npc_pers column
                         - Personal details and background are in npc_pers
                         - Current state and dynamic information in npc_dynamic
                         
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
                         - ALWAYS provide a final analysis after all queries"
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

// Start output buffering for the HTML response
ob_start();

$TITLE = "🔍 Dwemer Diagnostics";
?>
    <?php 
    $debugPaneLink = false;
    include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo $TITLE; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
    <?php include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html"); ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding-top: 100px;
        }
        .container {
            display: flex;
            gap: 20px;
            height: calc(100vh - 200px);
            margin-top: 80px;
            padding: 0 20px;
        }
        .tables-section {
            flex: 0 0 200px;
            background-color: #2d2d2d;
            border-radius: 8px;
            padding: 15px;
            overflow-y: auto;
        }
        .tables-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #0e639c;
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
        }
        .table-item:hover {
            background-color: #3e3e3e;
        }
        .table-item.active {
            background-color: #0e639c;
            color: white;
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
        }
        .logs-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #2d2d2d;
            border-radius: 8px;
            padding: 15px;
            max-width: 50%;
            min-width: 40%;
        }
        .log-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
            border-bottom: 1px solid #3e3e3e;
            padding-bottom: 5px;
        }
        .log-tab {
            padding: 8px 15px;
            background-color: #1e1e1e;
            border: none;
            border-radius: 4px 4px 0 0;
            color: #d4d4d4;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .log-tab.active {
            background-color: #0e639c;
        }
        .log-tab-checkbox {
            margin: 0;
            cursor: pointer;
        }
        .log-content {
            flex: 1;
            background-color: #1e1e1e;
            border-radius: 4px;
            padding: 10px;
            overflow-y: auto;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            display: none;
        }
        .log-content.active {
            display: block;
        }
        .log-text {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            overflow-y: auto;
            max-height: calc(100vh - 400px);
            height: 100%;
            padding: 10px;
            background-color: #1e1e1e;
            border-radius: 4px;
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
        }
        #inputText {
            flex: 1;
            padding: 10px;
            border: 1px solid #3e3e3e;
            border-radius: 4px;
            background-color: #1e1e1e;
            color: #d4d4d4;
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
        .context-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #2d2d2d;
            padding: 10px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .context-count {
            background-color: #0e639c;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
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
    </style>
</head>
<body>


    <div class="context-indicator">
        <span>Context Logs:</span>
        <span class="context-count" id="contextCount">0</span>
    </div>

    <div id="settingsModal" class="settings-modal">
        <div class="settings-content">
            <span class="settings-close" onclick="closeSettings()">&times;</span>
            <h2>Dwemer Diagnostics Settings</h2>
            <form id="settingsForm" class="settings-form">
                <div class="form-group">
                    <label for="apiKey">OpenRouter API Key</label>
                    <input type="password" id="apiKey" name="apiKey" required>
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <select id="model" name="model">
                        <option value="anthropic/claude-3-sonnet">Claude 3 Sonnet</option>
                        <option value="anthropic/claude-3-opus">Claude 3 Opus</option>
                        <option value="gpt-4-turbo-preview">GPT-4 Turbo</option>
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

    <div id="tableDataModal" class="table-data-modal">
        <div class="table-data-content">
            <span class="table-data-close" onclick="closeTableData()">&times;</span>
            <h2 class="table-data-title" id="tableDataTitle"></h2>
            <div id="tableDataContent"></div>
        </div>
    </div>

    <div class="container">
        <div class="tables-section">
            <h3>Database Tables</h3>
            <ul class="table-list" id="tableList">
                <!-- Tables will be populated here -->
            </ul>
        </div>
        <div class="chat-section">
            <div class="help-section">
                <h3>🔍 Dwemer Diagnostics Help</h3>
                <p>Available commands:</p>
                <ul>
                    <li><code>show tables</code> - List all available tables</li>
                    <li><code>describe [table_name]</code> - Show structure of a table</li>
                    <li><code>search [table_name] for [text]</code> - Search in a table</li>
                    <li><code>show recent events</code> - Display recent events</li>
                    <li><code>show npcs</code> - List all NPCs</li>
                </ul>
                <p>You can also ask natural language questions about the database!</p>
            </div>
            <div id="chatWindow"></div>
            <div class="loading" id="loadingIndicator">Processing query...</div>
            <div class="input-container">
                <input type="text" id="inputText" placeholder="Enter your query or type 'help' for available commands">
                <button onclick="sendQuery()">Send</button>
                <button class="settings-button" onclick="openSettings()">⚙️ Settings</button>
            </div>
        </div>
        <div class="logs-section">
            <div class="log-tabs" id="logTabs"></div>
            <div id="logContents"></div>
        </div>
    </div>

    <script>
        const chatWindow = document.getElementById('chatWindow');
        const resultsContent = document.getElementById('resultsContent');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const input = document.getElementById('inputText');

        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendQuery();
            }
        });

        function appendMessage(text, isUser = false, type = 'message') {
            const messageDiv = document.createElement('div');
            messageDiv.className = `${type} ${isUser ? 'user-message' : 'system-message'}`;
            
            if (type === 'sql-query') {
                // Format SQL query with syntax highlighting
                messageDiv.innerHTML = '<strong>SQL Query:</strong><br>' + text;
            } else {
                messageDiv.textContent = text;
            }
            
            chatWindow.appendChild(messageDiv);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        function displayResults(data) {
            if (data.queries) {
                data.queries.forEach((queryData, index) => {
                    // Display thinking text before query
                    if (queryData.thinking) {
                        appendMessage(queryData.thinking, false, 'ai-response');
                    }
                    
                    // Display SQL query
                    appendMessage(queryData.query, false, 'sql-query');
                    
                    // Display results if any
                    if (queryData.results && queryData.results.length > 0) {
                        const table = createTable(queryData.results);
                        const tableDiv = document.createElement('div');
                        tableDiv.className = 'query-section';
                        tableDiv.appendChild(table);
                        chatWindow.appendChild(tableDiv);
                        chatWindow.scrollTop = chatWindow.scrollHeight;
                    }
                });
                
                // Display final explanation if present
                if (data.final_explanation) {
                    appendMessage(data.final_explanation, false, 'ai-response');
                }
            } else if (data.table_data) {
                // Handle single query results (backward compatibility)
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
            
            // Create header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            Object.keys(data[0]).forEach(key => {
                const th = document.createElement('th');
                th.textContent = key;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);

            // Create body
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
            
            // Save to localStorage
            localStorage.setItem('dwemer_api_key', aiSettings.apiKey);
            localStorage.setItem('dwemer_model', aiSettings.model);
            localStorage.setItem('dwemer_temperature', aiSettings.temperature);
            localStorage.setItem('dwemer_max_tokens', aiSettings.maxTokens);
            
            closeSettings();
            appendMessage('Settings saved successfully', false);
        });

        // Log window management
        const logFiles = [
            { name: 'debugStream.log', title: 'Debug Stream' },
            { name: 'context_sent_to_llm.log', title: 'Context to LLM' },
            { name: 'output_from_llm.log', title: 'LLM Output' },
            { name: 'output_to_plugin.log', title: 'Plugin Output' },
            { name: 'apache_error.log', title: 'Apache Errors' }
        ];

        let selectedContext = new Set();

        function createLogTab(logFile) {
            const tab = document.createElement('button');
            tab.className = 'log-tab';
            tab.dataset.logFile = logFile.name;
            
            // Create checkbox
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'log-tab-checkbox';
            checkbox.title = 'Use as context';
            
            // Create title span
            const title = document.createElement('span');
            title.textContent = logFile.title;
            
            // Add elements to tab
            tab.appendChild(checkbox);
            tab.appendChild(title);
            
            // Add event listeners
            tab.addEventListener('click', (e) => {
                // Don't toggle active state if clicking checkbox
                if (e.target !== checkbox) {
                    // Remove active class from all tabs and contents
                    document.querySelectorAll('.log-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.log-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.querySelector(`.log-content[data-log-file="${logFile.name}"]`).classList.add('active');
                }
            });
            
            // Add checkbox event listener
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    selectedContext.add(logFile.name);
                } else {
                    selectedContext.delete(logFile.name);
                }
                updateContextCount();
            });
            
            return tab;
        }

        function createLogContent(logFile) {
            const content = document.createElement('div');
            content.className = 'log-content';
            content.dataset.logFile = logFile.name;
            content.innerHTML = `
                <div class="log-text">Loading...</div>
            `;
            return content;
        }

        async function loadLogContent(logFile, content) {
            try {
                const response = await fetch('dwemer-diagnostics.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ 
                        query: `read log ${logFile}`,
                        settings: aiSettings
                    }),
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to load log file');
                }

                const data = await response.json();
                if (data.error) {
                    content.querySelector('.log-text').textContent = `Error: ${data.error}`;
                } else if (data.response) {
                    // Special handling for context log
                    if (logFile === 'context_sent_to_llm.log') {
                        try {
                            // Try to parse the response as JSON
                            const jsonContent = JSON.parse(data.response);
                            let formattedContent = '';
                            
                            // Format each entry
                            Object.entries(jsonContent).forEach(([key, value]) => {
                                if (typeof value === 'object' && value !== null) {
                                    formattedContent += `${key} =>\n`;
                                    formattedContent += `array (\n`;
                                    Object.entries(value).forEach(([k, v]) => {
                                        formattedContent += `  '${k}' => '${v}',\n`;
                                    });
                                    formattedContent += `),\n\n`;
                                }
                            });
                            
                            content.querySelector('.log-text').textContent = formattedContent;
                        } catch (e) {
                            // If parsing fails, show original content
                            content.querySelector('.log-text').textContent = data.response;
                        }
                    } else {
                        content.querySelector('.log-text').textContent = data.response;
                    }
                } else if (data.table_data && Array.isArray(data.table_data)) {
                    const logText = data.table_data.map(row => row.line || row.content || JSON.stringify(row)).join('\n');
                    content.querySelector('.log-text').textContent = logText;
                } else {
                    content.querySelector('.log-text').textContent = JSON.stringify(data, null, 2);
                }
            } catch (error) {
                content.querySelector('.log-text').textContent = `Error loading log: ${error.message}`;
            }
        }

        // Initialize log windows
        function initializeLogWindows() {
            const tabsContainer = document.getElementById('logTabs');
            const contentsContainer = document.getElementById('logContents');
            
            logFiles.forEach((logFile, index) => {
                const tab = createLogTab(logFile);
                const content = createLogContent(logFile);
                
                tabsContainer.appendChild(tab);
                contentsContainer.appendChild(content);
                
                // Make first tab active by default
                if (index === 0) {
                    tab.classList.add('active');
                    content.classList.add('active');
                }
                
                // Load log content
                loadLogContent(logFile.name, content);
            });
        }

        // Modify sendQuery to include selected log context
        async function sendQuery() {
            const input = document.getElementById('inputText');
            const query = input.value.trim();
            
            if (!query) return;
            if (!aiSettings.apiKey) {
                appendMessage('Error: Please configure your OpenRouter API key in settings', false);
                return;
            }

            appendMessage(query, true);
            input.value = '';
            loadingIndicator.style.display = 'block';

            try {
                // Get context from selected logs
                const context = [];
                for (const logFile of selectedContext) {
                    const content = document.querySelector(`.log-content[data-log-file="${logFile}"]`);
                    if (content) {
                        const logText = content.querySelector('.log-text').textContent;
                        context.push(`Log ${logFile}:\n${logText}`);
                    }
                }

                const response = await fetch('dwemer-diagnostics.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ 
                        query: query,
                        settings: {
                            ...aiSettings,
                            context: context
                        }
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
                loadingIndicator.style.display = 'none';
            }
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', () => {
            initializeLogWindows();
            loadTables();
        });

        // Function to load and display tables
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
                        li.textContent = tableName;
                        
                        // Add click handler to show table data
                        li.addEventListener('click', () => {
                            // Remove active class from all items
                            document.querySelectorAll('.table-item').forEach(item => {
                                item.classList.remove('active');
                            });
                            // Add active class to clicked item
                            li.classList.add('active');
                            // Show table data
                            showTableData(tableName);
                        });
                        
                        tableList.appendChild(li);
                    });
                }
            } catch (error) {
                console.error('Error loading tables:', error);
            }
        }

        // Function to show table data in modal
        async function showTableData(tableName) {
            try {
                const response = await fetch('dwemer-diagnostics.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ 
                        query: `SELECT * FROM ${tableName} ORDER BY id DESC LIMIT 10`,
                        settings: aiSettings
                    }),
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to load table data');
                }

                const data = await response.json();
                if (data.error) {
                    console.error('Error loading table data:', data.error);
                    return;
                }

                // Update modal title
                document.getElementById('tableDataTitle').textContent = `Latest 10 rows from ${tableName}`;
                
                // Create and display table
                const tableDataContent = document.getElementById('tableDataContent');
                tableDataContent.innerHTML = ''; // Clear previous content
                
                if (data.table_data && data.table_data.length > 0) {
                    const table = createTable(data.table_data);
                    table.classList.add('table-data-table');
                    tableDataContent.appendChild(table);
                } else {
                    tableDataContent.textContent = 'No data available';
                }

                // Show modal
                document.getElementById('tableDataModal').style.display = 'block';
            } catch (error) {
                console.error('Error loading table data:', error);
                // Show error in modal
                document.getElementById('tableDataTitle').textContent = `Error loading ${tableName}`;
                document.getElementById('tableDataContent').textContent = `Error: ${error.message}`;
                document.getElementById('tableDataModal').style.display = 'block';
            }
        }

        // Function to close table data modal
        function closeTableData() {
            document.getElementById('tableDataModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const tableDataModal = document.getElementById('tableDataModal');
            if (event.target === tableDataModal) {
                closeTableData();
            }
        }
    </script>

    <?php include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html"); ?>
</body>
</html>
<?php
$buffer = ob_get_clean();
echo $buffer;
?> 