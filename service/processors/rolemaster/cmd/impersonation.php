<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');

if ($GLOBALS["argv"][3]) {
    $speech=$GLOBALS["db"]->escape($GLOBALS["argv"][3]);
} else if ($_GET["speech"]) {
    $speech=$GLOBALS["db"]->escape($_GET["speech"]);
} else {
    Logger::error("No speech parameter provided for impersonation command");
    die("No speech");
}

Logger::info("Processing impersonation command with speech: " . $speech);

$GLOBALS["db"]->insert(
    'responselog',
    array(
        'localts' => time(),
        'sent' => 0,
        'actor' => "rolemaster",
        'text' => "",
        'action' => "rolecommand|ImpersonatePlayer@$speech@inputtext",
        'tag' => ""
    )
);

Logger::info("Successfully logged impersonation command to responselog");
?>