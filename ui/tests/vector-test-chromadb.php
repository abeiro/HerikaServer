<?php
//Run count command
$commandcount = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php count';
$outputcount = shell_exec($commandcount);

// Check if $commandcount is less than or equal to 1
if ($outputcount <= 1) {
    $randomnumber = 1;
} else {
    // Generate a random number between 1 and $outputcount
    $randomnumber = rand(1, $outputcount);
}

//Run get command
$commandid = "php /var/www/html/HerikaServer/debug/util_memory_subsystem.php get $randomnumber";
$outputid = shell_exec($commandid);

//Run query Command
$commandquery = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php query "What do you know about Skyrim?"';
$outputquery = shell_exec($commandquery);

//Output count command
echo "<h1>Memory Count</h1>";
echo "<h2>$outputcount</h2>";

echo "<br><br>------------------------------------------------------------------------------------------------------------------------------------------------------------------";

//Output get command with formatting
echo "<h1>Get ID $randomnumber Memory Contents</h1>";
$outputid = trim($outputid);
$lines = explode("\n", $outputid);

foreach ($lines as $line) {
    echo trim($line) . "<br>";
}

echo "<br><br>------------------------------------------------------------------------------------------------------------------------------------------------------------------";

//Output query command with formatting
echo "<h1>Query for: What do you know about Skyrim?</h1>";
$outputquery = trim($outputquery);
$lines = explode("\n", $outputquery);

foreach ($lines as $line) {
    echo trim($line) . "<br>";
}
?>
