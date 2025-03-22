<?php

session_start();

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "💬 CHIM Chat Testing";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

$db=new sql();
$res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
$last_gamets=$res[0]["last_gamets"]+1;

// Extract hash from profile filename if it exists
$hash = '';
if (isset($_SESSION["PROFILE"])) {
    $pattern = '/conf_([a-f0-9]+)\.php/';
    if (preg_match($pattern, basename($_SESSION["PROFILE"]), $matches)) {
        $hash = $matches[1];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <meta charset="utf-8">
    <title>Chat Simulation</title>
    <script>
        // Add event listener for Enter key
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('inputText').addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !this.disabled) {
                    e.preventDefault(); // Prevent form submission
                    reqSend();
                }
            });
        });

        function setLoadingState(loading) {
            const form = document.getElementById('chatForm');
            const input = document.getElementById('inputText');
            const button = document.getElementById('sendButton');
            
            if (loading) {
                form.classList.add('loading');
                input.disabled = true;
                button.disabled = true;
            } else {
                form.classList.remove('loading');
                input.disabled = false;
                button.disabled = false;
                input.focus(); // Return focus to input
            }
        }

        function parseReq(inputString) {
            var lines = inputString.split("\n");
            lines.forEach(function(line) {
                var parts = line.split("|");
                if (parts.length > 2) {
                    var newline = "<p class='llm'>" + 
                      document.getElementById("herikaName").value + ": " + parts[2] + "</p>";
                    document.getElementById("chatWindow").innerHTML += newline;
                    logChat(parts[2]);
                }
            });
            setLoadingState(false);
            document.getElementById('inputText').value = '';
            
            // Scroll chat window to bottom
            const chatWindow = document.getElementById('chatWindow');
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        function reqSend() {
            const input = document.getElementById('inputText');
            if (!input.value.trim()) return; // Don't send empty messages
            
            setLoadingState(true);
            document.getElementById("chatWindow").innerHTML += 
                "<p class='player'>" + 
                document.getElementById('playerName').value + ': ' + 
                input.value + "</p>";
            
            var currentDate = new Date();
            var timestampInSeconds = parseInt(document.getElementById('last_gamets').value)+1;
            var profile = document.getElementById('profile').value;
            var xhr = new XMLHttpRequest();

            var urlDataRaw = 'inputtext|' + 
                document.getElementById('gamets').value + '|' + 
                timestampInSeconds + '|' + 
                document.getElementById('playerName').value + ': ' + 
                input.value;
            var urlData = btoa(urlDataRaw);
            document.getElementById('gamets').value = parseInt(document.getElementById('gamets').value)+10;
            document.getElementById('last_gamets').value = parseInt(timestampInSeconds)+10;

            // Clear input immediately after sending
            input.value = '';

            if (profile)
                xhr.open('GET', '/HerikaServer/stream.php?DATA=' + urlData + "&profile=" + profile, true);
            else
                xhr.open('GET', '/HerikaServer/stream.php?DATA=' + urlData, true);

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    parseReq(xhr.responseText);
                } else {
                    console.error('Request failed with status code: ' + xhr.status);
                    setLoadingState(false); // Make sure to re-enable on error
                }
            };
            xhr.onerror = function() {
                console.error('Network error occurred');
                setLoadingState(false); // Make sure to re-enable on error
            };
            xhr.send();
            
            // Scroll chat window to bottom
            const chatWindow = document.getElementById('chatWindow');
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        function logChat(chatline) {
            // Implementation left as-is or adjust as needed
            return;
        }
    </script>
    <style>
        /* Override main container styles */
        main {
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

        /* Loading state styles */
        .loading {
            position: relative;
            opacity: 0.7;
            pointer-events: none;
        }

        .loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            right: 120px; /* Space for send button */
            top: 50%;
            transform: translateY(-50%);
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        /* Chat window styling */
        #chatWindow {
            width: 80%;
            height: 300px;
            overflow-y: auto;
            background-color: #3a3a3a;
            border: 1px solid #4a4a4a;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            scroll-behavior: smooth;
        }

    
        input[type="text"] {
            width: calc(100% - 120px);
            background-color: #3a3a3a;
            border: 1px solid #4a4a4a;
            color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 14px;
        }

        input[type="text"]:disabled {
            background-color: #2c2c2c;
            color: #888;
        }

        .btn-primary:disabled {
            background-color: #2c2c2c;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Player and LLM chat text classes */
        p.llm {
            color: #00ff7f;
            margin: 1px;
        }
        p.player {
            color: #00bfff;
            margin: 3px 0;
        }

        /* iframe container styling */
        iframe {
            width: 80%;
            min-height: 700px;
            margin-top: 50px;
            border: 1px solid #4a4a4a;
            border-radius: 5px;
        }

        input[type="text"], input[type="number"], input[type="url"], textarea, select {
            width: 75%;
        }
    </style>
</head>
<body>
    <main class="container">
        <h2>💬 CHIM Chat Testing</h2>
        <h3>Current Character: <b><?php echo $GLOBALS["HERIKA_NAME"]; ?></b><h3>
        <h3>This is just for testing AI responses, do not use this as an indication of roleplay quality.</h3>
        <h4>Currently with the default profile, use any other character instead.</h4>
        <div id='chatWindow'></div>

        <form action='index.php' method='post' id="chatForm">
            <p>Player: <b><?php echo $GLOBALS["PLAYER_NAME"]; ?></b></p>
            <input type='text' name='inputText' id='inputText' placeholder="Type your message and press Enter or Send"/>

            <input type='hidden' name='localts'   id='localts'   value='<?php echo time(); ?>' />
            <input type='hidden' name='gamets'    id='gamets'    value='0' />
            <input type='hidden' name='playerName' id='playerName' value='<?php echo $GLOBALS["PLAYER_NAME"]; ?>' />
            <input type='hidden' name='herikaName' id='herikaName' value='<?php echo $GLOBALS["HERIKA_NAME"]; ?>' />
            <input type='hidden' name='profile'    id='profile'    value='<?php echo $hash; ?>' />
            <input type='hidden' name='conf'       id='profile'    value='<?php echo $_SESSION["PROFILE"]; ?>' />
            <input type='hidden' name='last_gamets' id='last_gamets' value='<?php echo $last_gamets; ?>' />
            <input type='button' name='send' id='sendButton' value='Send' onclick='reqSend()' class='btn-primary'/>
        </form>
    </main>
</body>
<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
</html>
