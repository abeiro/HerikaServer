<?php

$enginePath = __DIR__ . '/../../';
require_once($enginePath . 'lib/runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
]);

$embedding = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TEXT2VEC_PROVIDER"] ?? '';

// Run sync command
$commandsync = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($enginePath . 'debug/util_memory_subsystem.php') . ' sync';
$outputsync = shell_exec($commandsync);

// Output sync command
if ($embedding == 'local') {
    echo "<h1>Memory Sync for Local Text2Vec</h1>";
} else {
    echo "<h1>Memory Sync for OpenAI's ADA2</h1>";
}

echo "<ul>";
$lines = explode("\n", $outputsync);
foreach ($lines as $line) {
    $line = trim($line);
    if (!empty($line)) {
        echo "<li>$line</li>";
    }
}
echo "</ul>";
?>
