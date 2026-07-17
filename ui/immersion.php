<?php
// Compatibility endpoint retained for bookmarks and links from older CHIM builds.
$legacyTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'diaries';
$mergedTabs = ['diaries', 'adventure', 'soulgaze', 'questgen', 'backgroundlife'];

$query = $_GET;
unset($query['tab']);

if ($legacyTab === 'chat') {
    $target = 'chat-testing.php';
} else {
    $query['tab'] = in_array($legacyTab, $mergedTabs, true) ? $legacyTab : 'diaries';
    $target = 'events-memories.php';
}

if (!empty($query)) {
    $target .= '?' . http_build_query($query);
}

header('Location: ' . $target, true, 302);
exit;
