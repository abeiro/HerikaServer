<?php
session_start();

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

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

// Get the 'action' parameter from the URL
$action = $_GET['action'] ?? '';

// Handle actions based on the 'action' parameter
switch ($action) {
    case 'stop':
        // Stop a quest
        if (isset($_GET['taskid'])) {
            $taskId = $db->escape($_GET['taskid']);
            $db->delete('aiquest', "taskid='{$taskId}'");
            header('Location: index.php');
            exit();
        }
        break;

    case 'start':
        // Start a quest
        if (isset($_GET['title'])) {
            $cn_title = $db->escape($_GET['title']);
            $newRunningQuest = $db->fetchAll("SELECT * FROM aiquests_template WHERE title='{$cn_title}'");
            $taskId = uniqid();
            $quest = json_decode($newRunningQuest[0]['data'], true);

            $db->insert(
                'aiquest',
                array(
                    'definition' => $newRunningQuest[0]['data'],
                    'updated' => time(),
                    'status' => 1,
                    'taskid' => $taskId
                )
            );
            header('Location: index.php');
            exit();
        }
        break;

    case 'start_alike':
        // Start a quest with alterations
        if (isset($_GET['title'])) {
            $cn_title = $db->escape($_GET['title']);
            $newRunningQuest = $db->fetchAll("SELECT * FROM aiquests_template WHERE title='{$cn_title}'");
            $taskId = uniqid();
            $quest = json_decode($newRunningQuest[0]['data'], true);

            // Add some randomness
            $notes=["dramatic story","romance story","comedy flair","rude cursed words story"];

            $newQuest=createQuestFromTemplate($quest,$notes[array_rand($notes)].", adapt characters to it.");
            

            if ($newQuest) {
                $pointer = null;

                if (isset($newQuest[0]['quest'])) {
                    $pointer = $newQuest[0];
                } elseif (isset($newQuest['quest'])) {
                    $pointer = $newQuest;
                }

                if ($pointer) {
                    $db->insert(
                        'aiquest',
                        array(
                            'definition' => json_encode($pointer),
                            'updated' => time(),
                            'status' => 1,
                            'taskid' => $taskId
                        )
                    );
                    header('Location: index.php');
                    exit();
                } else {
                    echo '<div class="message">Error: Invalid quest data.</div>';
                }
            } else {
                echo '<div class="message">Error: Failed to create quest from template.</div>';
            }
        }
        break;

    default:
        // No action; proceed to display content
        break;
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="../../images/favicon.ico">
    <title>CHIM - AI Adventures</title>
    <style>
/* Base Styling */
body {
    font-family: Arial, sans-serif;
    background-color: #2c2c2c; /* Dark grey background */
    color: #f8f9fa; /* Light grey text for readability */
}

h1, h2, h3 {
    color: #ffffff; /* White color for headings */
    font-weight: bold; /* Bold title headings */
    font-size: 32px;
}

/* Table Styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    background-color: #3a3a3a; /* Slightly lighter grey for table backgrounds */
    font-weight: bold;
}

th, td {
    border: 1px solid #555555; /* Darker borders */
    padding: 8px;
    text-align: left;
    color: #f8f9fa; /* Light text */
}

th {
    background-color: #444444; /* Darker background for headers */
    font-weight: bold;
}

/* Button Styling */
.button, a.button {
    display: inline-block;
    padding: 8px 8px;
    margin-top: 8px;
    cursor: pointer;
    background-color: #007bff;
    border: none;
    color: white;
    border-radius: 3px;
    font-size: 14px;
    text-decoration: none;
    font-weight: bold; /* Bold buttons */
}

.button:hover, a.button:hover {
    background-color: #0056b3;
}

/* Form Styling */
form {
    margin-bottom: 20px;
    background-color: #3a3a3a;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #555555;
    max-width: 600px;
}

label {
    font-weight: bold;
    color: #f8f9fa;
}

input[type="text"], input[type="file"], textarea {
    width: 100%;
    padding: 6px;
    margin-top: 5px;
    margin-bottom: 15px;
    border: 1px solid #555555;
    border-radius: 3px;
    background-color: #4a4a4a;
    color: #f8f9fa;
    resize: vertical;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

/* Submit Button Styling */
input[type="submit"] {
    background-color: #007bff;
    border: none;
    color: white;
    border-radius: 5px;
    cursor: pointer;
    padding: 5px 15px;
    font-size: 18px;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

input[type="submit"]:hover {
    background-color: #0056b3;
}

/* Message Styling */
.message {
    background-color: #444444;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #555555;
    max-width: 600px;
    margin-bottom: 20px;
    color: #f8f9fa;
}

.message p {
    margin: 0;
}

/* Response Container */
.response-container {
    margin-top: 20px;
}

/* Indentation Classes */
.indent {
    padding-left: 10ch; /* 10 character spaces */
}

.indent5 {
    padding-left: 5ch; /* 5 character spaces */
}


    </style>
</head>
<body>

<?php
// Fetch quest templates from the database
$results = $db->fetchAll("SELECT title, data, enabled FROM aiquests_template");

// Display quest templates
echo "<h3 class='my-2'>AI Adventure Manager</h3>";

$list = [];
foreach ($results as $n => $quest) {
    $questData = json_decode($quest['data'], true);

    $list[$n]['Title'] = htmlspecialchars($quest['title']);
    $list[$n]['Description'] = htmlspecialchars($questData['overview']);
    $list[$n]['Enabled'] = htmlspecialchars($quest['enabled']);

    // Create action buttons
    $startButton = "<a href='?action=start&title=" . urlencode($quest['title']) . "' class='button'>Start</a>";
    $startAlikeButton = "<a href='?action=start_alike&title=" . urlencode($quest['title']) . "' class='button'>Randomised Start</a>";

    $list[$n]['Action'] = $startButton . $startAlikeButton;
}

// Use the function from misc_ui_functions.php
print_array_as_table($list);

// Fetch running quests from the database
$results = $db->fetchAll("SELECT * FROM aiquest WHERE status=1");

echo "<h3 class='my-2'>Running Quests</h3>";

$list = [];
foreach ($results as $n => $quest) {
    $questData = json_decode($quest['definition'], true);

    $list[$n]['Title'] = htmlspecialchars($questData['quest']);

    // Find the current stage
    $currentStage = '';
    if (isset($questData['stages']) && is_array($questData['stages'])) {
        foreach ($questData['stages'] as $stage) {
            if ($stage['status'] == 1) {
                $currentStage = htmlspecialchars($stage['label']);
                break; // Assuming only one stage is active
            }
        }
    }
    $list[$n]['Current Stage'] = $currentStage;

    // Create stop button
    $stopButton = "<a href='?action=stop&taskid=" . htmlspecialchars($quest['taskid']) . "' class='button'>Stop</a>";

    $list[$n]['Action'] = $stopButton;
}

// Use the function from misc_ui_functions.php
print_array_as_table($list);
?>

</body>
</html>
