#!/usr/bin/php

<?php

error_reporting(E_ALL);
// Enable async signals (PHP 7.1+)
pcntl_async_signals(true);

require __DIR__ . '/vendor/autoload.php';

use Ratchet\Client\connect;

$TWITCH_IRC_URL = "wss://irc-ws.chat.twitch.tv";
$USERNAME       = getenv("TBOT_USERNAME");
$OAUTH_TOKEN    = "oauth:" . getenv("TBOT_OAUTH");
$CHANNEL        = getenv("TBOT_CHANNEL");

echo date(DATE_RFC2822) . PHP_EOL;
echo "Using config values:
TWITCH_IRC_URL: $TWITCH_IRC_URL
USERNAME: $USERNAME
OAUTH_TOKEN: $OAUTH_TOKEN
CHANNEL: $CHANNEL
" . PHP_EOL;

$child_pid = null;
$terminate = false;

// Handle termination signals
function shutdownHandler($signal)
{
    global $child_pid, $terminate;
    echo "⚠️ Received termination signal ($signal), stopping bot...\n";
    $terminate = true;
    
    if ($child_pid) {
        echo "🛑 Killing child process (PID: $child_pid)\n";
        // Send a SIGTERM (graceful) to the child process
        posix_kill($child_pid, SIGTERM);
        // Optionally, wait a short while for the child to shutdown
        $wait = 0;
        while ($wait < 10 && posix_kill($child_pid, 0)) {
            sleep(1);
            $wait++;
        }
    }
    echo "✅ Bot stopped cleanly.\n";
    exit(0);
}

// Register signal handlers
pcntl_signal(SIGTERM, "shutdownHandler");
pcntl_signal(SIGINT,  "shutdownHandler");

// Parent Process Loop: Create/restart child processes
while (true) {
    // Fork a child process
    $child_pid = pcntl_fork();
    if ($child_pid == -1) {
        die("❌ Fork failed!\n");
    } elseif ($child_pid) {
        // 👨 Parent process
        echo "🟢 Parent waiting for child (PID: $child_pid)\n";
        
        // Instead of blocking indefinitely, we loop waiting for the child to exit.
        do {
            // Non-blocking wait for the specific child PID
            $wait = pcntl_waitpid($child_pid, $status, WNOHANG);
            if ($wait === 0) {
                // Child still running, sleep briefly so we can dispatch signals
                sleep(1);
            }
            // Signal handlers are automatically dispatched due to pcntl_async_signals(true)
        } while ($wait === 0 && !$terminate);
        
        if ($terminate) {
            // We were asked to terminate. Break out of the loop.
            break;
        }
        
        echo "🔄 Child process (PID: $child_pid) died. Restarting...\n";
        sleep(2); // Avoid rapid restarting
    } else {
        // 👶 Child process: Connect to WebSocket
        runBot($TWITCH_IRC_URL, $USERNAME, $OAUTH_TOKEN, $CHANNEL);
        exit(0);   // Exit when disconnected
    }
}

// Function to handle WebSocket connection in the child process
function runBot($url, $username, $oauth, $channel)
{
    echo "🔌 Connecting to Twitch Chat...\n";

    \Ratchet\Client\connect($url)->then(
        function ($conn) use ($username, $oauth, $channel) {
            echo "✅ Connected to Twitch Chat\n";
            // Authenticate with Twitch IRC
            $conn->send("PASS $oauth");
            $conn->send("NICK $username");
            $conn->send("JOIN #$channel");
            echo "📡 Joined #$channel\n";

            // Listen for messages
            $conn->on('message', function ($msg) use ($conn, $channel) {
                echo "📩 Raw Message: $msg\n";

                if (preg_match('/:([^!]+)!.* PRIVMSG #\w+ :(.*)/', $msg, $matches)) {
                    $user    = $matches[1];
                    $message = trim($matches[2]);

                    echo "💬 $user: $message\n";

                    if (strpos($message, "Rolemaster:") === 0) {
                        $command = trim(substr($message, strlen("Rolemaster:")));
                        echo "🧙‍♂️ Command Detected: $command\n";
                        executeRolemasterCommand($conn, $channel, $user, $command);
                    }
                }
            });

            // Handle disconnection
            $conn->on('close', function ($code = null, $reason = null) {
                echo "❌ Disconnected from Twitch Chat: $reason\n";
                exit(1); // Exit child process to trigger parent restart
            });
        },
        function ($error) {
            echo "🚨 WebSocket Error: $error\n";
            exit(1); // Exit child process to trigger parent restart
        }
    );
}

// Function to execute a Rolemaster command
function executeRolemasterCommand($conn, $channel, $user, $command)
{
    echo "📝 Executing command from $user: $command\n";
    $escapedCommand = escapeshellarg($command);
    exec("php /var/www/html/HerikaServer/service/manager.php $escapedCommand", $output, $returnCode);
    $response = $returnCode === 0 ? "Command accepted" : "❌ Error executing command";
    sendMessage($conn, $channel, $response);
}

// Function to send a message to Twitch chat
function sendMessage($conn, $channel, $message)
{
    $conn->send("PRIVMSG #$channel :$message");
}