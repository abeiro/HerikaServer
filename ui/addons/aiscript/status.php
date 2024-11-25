<?php
session_start();

// Enable error reporting (for development purposes)
error_reporting(E_ERROR);
ini_set('display_errors', '0');

// Paths
$rootEnginePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR; // Three levels up from the current directory
$configFilepath = $rootEnginePath . "conf" . DIRECTORY_SEPARATOR;

// Include configuration and required libraries
require_once($configFilepath . "conf.php");

// Include database class based on the DBDRIVER global variable
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php");

// Include miscellaneous UI functions
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "misc_ui_functions.php");

// Include chat helper functions
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

// Include rolemaster helper functions
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "rolemaster_helpers.php");

// Include the JPD connector
require_once($rootEnginePath . "connector" . DIRECTORY_SEPARATOR . "__jpd.php");

// Create a new database connection
$db = new sql();

// Fetch running quests from the database
$results = $db->fetchAll("SELECT * FROM aiquest WHERE status=1");

$data =  json_decode($results[0]['definition'], true);
$stages = $data['stages'];

/*
$json = '{
    "stages": [
        {"id":"1","label":"SpawnCharacter","char_ref":1,"status":2},
        {"id":"2","label":"MoveToPlayer","char_ref":1,"follow":true,"status":2},
        {"id":"3","label":"TellTopicToPlayer","char_ref":1,"topic_ref":2,"status":2,"last_llm_call":1731426025},
        {"id":"4","label":"WaitForCoins","char_ref":1,"amount":10,"status":2,"checked_times":1,"last_check":58348369},
        {"id":"5","label":"TellTopicToPlayer","char_ref":1,"topic_ref":3,"parent_stage":4,"branch":1,"status":2,"last_llm_call":1731426282,"sub_status":2},
        {"id":"6","label":"TellTopicToPlayer","char_ref":1,"topic_ref":4,"parent_stage":5,"branch":1,"status":2,"last_llm_call":1731426557,"sub_status":2},
        {"id":"7","label":"ToGoAway","char_ref":1,"parent_stage":6,"branch":1,"status":2},
        {"id":"8","label":"ToGoAway","char_ref":1,"parent_stage":4,"branch":2,"status":5}
    ]
}';

$data = json_decode($json, true);
$stages = $data['stages'];
*/
// Initialize Graphviz Dot format
$graph = "digraph Flowchart {\n";
$graph .= "rankdir=LR;\n"; // Left-to-Right flow
$graph .= "node [shape=box, style=filled, fontname=\"Arial\"];\n";

// Define Nodes
foreach ($stages as $stage) {
    $id = $stage['id'];
    $label = $stage['label'];
    $status = $stage['status'];

    // Determine node color based on status
    if ($status == 2) {
        $color = "green"; // Done
    } elseif ($status == 1) {
        $color = "yellow"; // Running
    } elseif ($status >2) {
        $color = "red"; // Cancelled
    } else {
        $color = "grey"; // Cancelled
    }

    // Add the node
    $graph .= "  \"$id\" [label=\"$label\", fillcolor=$color];\n";
}

// Define Edges (Connections)
$previousStageId = null; // To keep track of the previous stage
foreach ($stages as $stage) {
    $id = $stage['id'];

    if (isset($stage['parent_stage'])) {
        $parent = $stage['parent_stage'];
        $branch = isset($stage['branch']) ? " [label=\"Branch: {$stage['branch']}\"]" : "";

        // Add the edge
        $graph .= "  \"$parent\" -> \"$id\"$branch;\n";
    } elseif ($previousStageId !== null) {
        // No parent_stage, link to the previous stage
        $graph .= "  \"$previousStageId\" -> \"$id\" [style=dotted, color=blue, label=\"Sequence\"];\n";
    }

    // Update the previousStageId for the next iteration
    $previousStageId = $id;
}

$graph .= "}\n";

// Save Graphviz Dot file
$dotFile = __DIR__ . '/flowchart.dot';
file_put_contents($dotFile, $graph);

// Generate the flowchart image using Graphviz
$imageFile = __DIR__ . '/flowchart.png';
exec("dot -Tpng $dotFile -o $imageFile");

header("Location: flowchart.png");
?>


