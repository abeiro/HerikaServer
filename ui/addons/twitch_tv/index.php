<?php
$bot_script =   __DIR__."/service.php";      // Bot script path
$pid_file =     __DIR__."/bot_pid.txt";         // File to store PID
$log_file =     __DIR__."/bot_output.log";      // Log file for bot output

// Store environment variables
$env_file = __DIR__ . "/bot_env.json";
$env_vars = file_exists($env_file) ? json_decode(file_get_contents($env_file), true) : [];

if (!file_exists( __DIR__."/vendor/autoload.php")) {
    error_log("Addon not installed.... installing");
    $install_command = "cd ". __DIR__." && HOME=".__DIR__."  composer install";
    error_log("$install_command");

    shell_exec("$install_command");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tbot_username'], $_POST['tbot_oauth'], $_POST['tbot_channel'])) {
        $env_vars = [
            "TBOT_USERNAME" => trim($_POST['tbot_username']),
            "TBOT_OAUTH" => trim($_POST['tbot_oauth']),
            "TBOT_CHANNEL" => trim($_POST['tbot_channel']),
        ];
        file_put_contents($env_file, json_encode($env_vars));
    }

    if ($_POST['action'] === 'start') {
        if (file_exists($pid_file)) {
            echo "⚠️ Bot is already running!";
        } else {
            // Set environment variables and start the bot
            $env_command = "TBOT_USERNAME='{$env_vars["TBOT_USERNAME"]}' TBOT_OAUTH='{$env_vars["TBOT_OAUTH"]}' TBOT_CHANNEL='{$env_vars["TBOT_CHANNEL"]}'";
            $command = "$env_command php $bot_script > $log_file 2>&1 & echo $!";
            error_log($command);
            $pid = shell_exec($command);

            file_put_contents($pid_file, $pid);
            echo "✅ Bot started with PID: $pid";
        }
    } elseif ($_POST['action'] === 'stop') {
        if (file_exists($pid_file)) {
            $pid = file_get_contents($pid_file);
            shell_exec("kill $pid"); // Stop the bot
            unlink($pid_file);
            echo "❌ Bot stopped.";
        } else {
            echo "⚠️ Bot is not running.";
        }
    } elseif ($_POST['action'] === 'refresh') {
        header("Location: ".$_SERVER['PHP_SELF']); // Refresh the page
        exit;
    }
}

// Function to check if the bot is running by verifying both
// the existence of the pid file and if a process with that pid exists.
function is_bot_running($pid_file) {
    if (file_exists($pid_file)) {
        $pid = (int) trim(file_get_contents($pid_file));
        // Check if the process exists using posix_getpgid.
        if (function_exists('posix_getpgid')) {
            if (@posix_getpgid($pid) !== false) {
                return true;
            } else {
                // PID file exists but no process found, remove the stale PID file.
                unlink($pid_file);
                return false;
            }
        }
        // If posix_getpgid() isn't available, a fallback to just file existence.
        return true;
    }
    return false;
}

// Check if bot is running
$is_running = is_bot_running($pid_file);
$log_content = file_exists($log_file) ? array_slice(file($log_file), -25) : []; // Get last 15 lines
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Twitch Bot Control</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        .status { font-size: 20px; font-weight: bold; color: <?= $is_running ? 'green' : 'red' ?>; }
        .log-box {width: 80%;margin: auto;padding: 10px;background: #f4f4f4;border: 1px solid #ccc;text-align: left;background-color: black;font-family: monospace;color: greenyellow;}
        input { margin: 5px; padding: 5px; width: 300px; }
        button { margin: 5px; padding: 10px; }
    </style>
</head>
<body>
    <h1>🎮 Twitch Bot Control</h1>

    <form method="post">
        <h2>⚙️ Bot Configuration</h2>
        <input type="text" name="tbot_username" placeholder="Username" value="<?= htmlspecialchars($env_vars['TBOT_USERNAME'] ?? '') ?>" required> ::
        <input type="text" name="tbot_oauth" placeholder="OAUTH TOKEN " value="<?= htmlspecialchars($env_vars['TBOT_OAUTH'] ?? '') ?>" required> <a href="https://twitchtokengenerator.com/" target="_blank">Obtain</a> :: 
        <input type="text" name="tbot_channel" placeholder="TBOT_CHANNEL" value="<?= htmlspecialchars($env_vars['TBOT_CHANNEL'] ?? '') ?>" required><br>

        <p class="status">Status: <?= $is_running ? "🟢 Running" : "🔴 Stopped" ?></p>

        <button type="submit" name="action" value="start">▶️ Start Bot</button>
        <button type="submit" name="action" value="stop">⏹ Stop Bot</button>
        <button type="submit" name="action" value="refresh">🔄 Refresh</button>
    </form>

    <h2>📜 Bot Output (Last 25 Lines)</h2>
    <div class="log-box">
        <?= !empty($log_content) ? implode("<br>", $log_content) : "No logs yet." ?>
    </div>
</body>
</html>
