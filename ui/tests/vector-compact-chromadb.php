<?php

require_once(__DIR__ . '/../../conf/conf.php');
$embedding = $FEATURES["MEMORY_EMBEDDING"]["TEXT2VEC_PROVIDER"];

//Run the Compact Command
$commandcompact = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php compact noembed';
$commandcompact = shell_exec($commandcompact);

echo "<h1>Compact Memories</h1>";
echo"<pre>$commandcompact</pre>";

echo "<ul>";
$lines = explode("\n", $commandcompact);
foreach ($lines as $line) {
    $line = trim($line);
    if (!empty($line)) {
        echo "<li>$line</li>";
    }
}
echo "</ul>";

// Run sync command // Disabled
/*
$commandsync = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php sync';
//$outputsync = shell_exec($commandsync);

// Output sync command
if ($embedding == 'local') {
    echo "<h1>Memory Sync for Local Text2Vec</h1>";
} else {
    echo "<h1>Memory Sync for OpenAI's ADA2</h1>";
}
*/


?>
