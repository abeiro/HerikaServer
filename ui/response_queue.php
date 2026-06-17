<?php

$params = [];

if (isset($_GET["page"])) {
    $params["page"] = max(1, intval($_GET["page"]));
}

if (isset($_GET["limit"])) {
    $params["limit"] = max(10, intval($_GET["limit"]));
}

if (isset($_GET["cleanlog"]) && $_GET["cleanlog"]) {
    $params["cleanlog"] = "true";
}

if (isset($_GET["export"]) && $_GET["export"]) {
    $params["export"] = "1";
}

$target = "ai-response.php";
if (!empty($params)) {
    $target .= "?" . http_build_query($params);
}

header("Location: " . $target);
exit;
