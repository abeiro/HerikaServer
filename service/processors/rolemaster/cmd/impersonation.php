<?php 
if ($GLOBALS["argv"][3]) {
    $speech=$GLOBALS["db"]->escape($GLOBALS["argv"][3]);
} else if ($_GET["speech"]) {
    $speech=$GLOBALS["db"]->escape($_GET["speech"]);
} else
    die("No speech");



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

?>