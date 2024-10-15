<?php
if ($argc < 3) {
    die("Error: Invalid arguments.\n");
}

require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$db=new sql();

$conversationId = $argv[1];
$dialogue = $argv[2];

// Log the conversation ID and the dialogue
logMessage("Processing conversation ID: $conversationId");
logMessage("Dialogue: \n$dialogue");

// Prepare the prompt for summarization
$myPrompt = preparePrompt($dialogue);

// Log the prompt
logMessage("Prompt: " . json_encode($myPrompt));

// Process the summarization
$summary = processSummarization($myPrompt);
$participants = getParticipantsFromDialogue($dialogue);

// Log the summarization result
logMessage("Participants: ".json_encode($participants)." \nSummary: $summary");

if (!isset($db)) {
    logMessage("Database connection is not initialized.");
}

$db->insert('conversations_summaries', [
    'conversationid' => $conversationId,
    'summary' => $summary,
    'participants' => implode(', ', $participants)
]);

$db->updateRow('speech',[
    'summarized' => true
]," conversationid='{$conversationId}' ");

logMessage("Finished updating tables");
// logMessage("UPDATE speech SET liste = TRUE WHERE conversationid = $conversationId");

function getParticipantsFromDialogue($dialogue) {
    // Adjust the regex to match names with spaces.
    preg_match_all('/^(.+) says:/m', $dialogue, $matches);
    return array_unique(array_map('trim', $matches[1])); // Trim spaces from the names.
}
// Prepare the prompt for the summarization
function preparePrompt($dialogue)
{
    return [
        [
            'role' => 'system',
            'content' => "This is a playthrough in Skyrim universe. Analyze provided dialogue. Summarize it as if to remember anything significant happened. If nothing happened just return \"Nothing happened.\" Provide short summary(1-2 paragraphs). Don't narrate just provide summary. Start with \"(list names of participants without parentheses) had encounter:\""
        ],
        [
            'role' => 'user',
            'content' => $dialogue
        ]
    ];
}

// Simulate the summarization process (replace with actual stream processing logic)
function processSummarization($myPrompt)
{
    file_put_contents("my_logs.txt", "\nStarted request...", FILE_APPEND);

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists("connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        file_put_contents("my_logs.txt", "\nChoose a LLM model and connector.", FILE_APPEND);
        die("Choose a LLM model and connector.".PHP_EOL);    
    } else {    
        require_once "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";
        // Simulate stream connection and summarization processing
        if (class_exists('connector')) {
            logMessage("The 'connector' class is available.");
        } else {
            logMessage("The 'connector' class does not exist.");
        }
        $connectionHandler = new connector();
        if ($connectionHandler) {
            logMessage("Connector instantiated successfully.");
        } else {
            logMessage("Failed to instantiate connector.");
        }

        try {
            $connectionHandler->open($myPrompt, ["MAX_TOKENS" => 500]);
            logMessage("Connection opened successfully.");
        } catch (Exception $e) {
            logMessage("Failed to open connection: " . $e->getMessage());
        } catch (Error $e) {
            logMessage("Error occurred while opening connection: " . $e->getMessage());
        }
        // $connectionHandler->open($myPrompt);

        // file_put_contents("my_logs.txt", "\nOpened stream...", FILE_APPEND);
    }

    $buffer = "";
    $totalBuffer = "";
    $breakFlag = false;

    while (true) {
        if ($breakFlag) {
            break;
        }

        $buffer .= $connectionHandler->process(); // Simulate the process of receiving data
        $totalBuffer .= $buffer;
        // file_put_contents("my_logs.txt", "\nProcessing...", FILE_APPEND);

        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }
    }

    $connectionHandler->close();
    $buffer = strtr($buffer, ["**" => ""]);

    file_put_contents("my_logs.txt", "Summary: " . $buffer, FILE_APPEND);

    return $buffer;
}

// Log a message to the log file
function logMessage($message)
{
    file_put_contents("my_logs.txt", "\n$message", FILE_APPEND);
}
