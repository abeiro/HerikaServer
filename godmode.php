<?php


// Developer wish: migrate this to json post requests dll side
error_log(__FILE__." start");

if (strpos($_SERVER["QUERY_STRING"],"&")===false)
    $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5)));
else
    $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5,strpos($_SERVER["QUERY_STRING"],"&")-4)));


ignore_user_abort(true);

// Expected format input|ts|gamets|PLAYER_NAME::
$gameRequest = explode("|", $receivedData);

$userWish=explode(":",$gameRequest[3]);
$output='';
$instruction=escapeshellarg("{$userWish[1]}");
exec("php /var/www/html/HerikaServer/service/manager.php rolemaster instruction \"$instruction\" notify", $output, $returnCode);

?>