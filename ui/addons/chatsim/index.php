<?php

session_start();


$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
}

$pattern = '/conf_([a-f0-9]+)\.php/';
preg_match($pattern, basename($_SESSION["PROFILE"]), $matches);
$hash = $matches[1];    

// HTML template
echo file_get_contents('template.html');

$db=new sql();
$res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
$last_gamets=$res[0]["last_gamets"]+1;




echo "
<!DOCTYPE html>
<html>
<head>
    <link rel=\"icon\" type=\"image/x-icon\" href=\"images/favicon.ico\">
    <meta charset=\"utf-8\">
    <title>Chat Simulation</title>
    <style>
        /* Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
            margin: 0;
            padding: 20px;
        }

        h1, h2, p {
            color: #ffffff; /* White color for headings and default paragraph text */
        }

        /* Chat window styling */
        #chatWindow {
            width: 80%;
            height: 300px;
            overflow-y: auto;
            background-color: #3a3a3a; /* Slightly lighter grey area */
            border: 1px solid #4a4a4a;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
        }

        /* Form styling */
        form {
            background-color: #3a3a3a; /* Slightly lighter grey for form backgrounds */
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #4a4a4a; /* Darker border for contrast */
            max-width: 800px;
        }

        label {
            font-weight: bold;
            color: #f8f9fa;
            display: block;
            margin-top: 10px;
        }

        input[type=\"text\"],
        input[type=\"file\"],
        textarea {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #4a4a4a; /* Darker border */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input background */
            color: #f8f9fa;
            font-family: Arial, sans-serif;
            font-size: 16px;
        }

        /* Buttons */
        input[type='button'] {
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            padding: 8px 16px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 5px;
        }

        input[type='button']:hover {
            background-color: #0056b3;
        }

        /* Player and LLM chat text classes (used by your JS) */
        p.llm {
            color: #00ff7f; /* Bright green on dark background */
            margin: 1px;
        }
        p.player {
            color: #00bfff; /* Bright blue on dark background */
            margin: 3px 0;
        }

        /* iframe container styling (optional) */
        iframe {
            width: 80%;
            min-height: 700px;
            margin-top: 50px;
            border: 1px solid #4a4a4a;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <h2>Chat Testing ({$GLOBALS["HERIKA_NAME"]})</h2>
    <h3>This is just for testing AI responses, do not use this as an indication of roleplay quality.</h3>
    <div id='chatWindow'></div>

    <form action='index.php' method='post'>
        <!-- You could add labels if you like; for now, we keep your existing structure. -->
        <p>Player: <b>{$GLOBALS["PLAYER_NAME"]}</b></p>
        <input type='text' name='inputText' id='inputText' size='100' placeholder=\"Don't use enter. Use button send\"/>

        <input type='hidden' name='localts'   id='localts'   value='" . time() . "' />
        <input type='hidden'   name='gamets'    id='gamets'    value='0' />
        <input type='hidden' name='playerName' id='playerName' value='{$GLOBALS["PLAYER_NAME"]}' />
        <input type='hidden' name='herikaName' id='herikaName' value='{$GLOBALS["HERIKA_NAME"]}' />
        <input type='hidden' name='profile'    id='profile'    value='{$hash}' />
        <input type='hidden' name='conf'       id='profile'    value='{$_SESSION["PROFILE"]}' />
        <input type='hidden' name='last_gamets' id='last_gamets' value='$last_gamets' />
        <!-- The Send button calls reqSend() in your JavaScript -->
        <input type='button' name='send' value='Send' onclick='reqSend()'/>
        
    </form>
    <p>If you make any changes down below make sure to refresh the page!</p>
    <iframe src='../../'></iframe>

</body>
</html>
";
?>
