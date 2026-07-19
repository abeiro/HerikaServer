<?php

function chimPhysicalDiaryEscape($value)
{
    return $GLOBALS['db']->escape(trim((string)$value));
}

function chimPhysicalDiaryTitle($npcName)
{
    return trim((string)$npcName) . "'s Diary";
}

function chimPhysicalDiaryRefIdToSignedInt($refId)
{
    $value = trim((string)$refId);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/^0x/i', '', $value);
    if (!ctype_xdigit($value)) {
        return null;
    }

    $unsigned = hexdec($value) & 0xFFFFFFFF;
    if ($unsigned >= 0x80000000) {
        $unsigned -= 0x100000000;
    }

    return (int)$unsigned;
}

function chimPhysicalDiaryEntries($npcName, $limit = 5)
{
    $safeName = chimPhysicalDiaryEscape($npcName);
    $limit = max(1, min(10, (int)$limit));
    $rows = $GLOBALS['db']->fetchAll(
        "SELECT rowid, topic, content, location, gamets, localts
         FROM diarylog
         WHERE lower(trim(people)) = lower('{$safeName}')
         ORDER BY gamets DESC, rowid DESC
         LIMIT {$limit}"
    );

    return array_reverse(is_array($rows) ? $rows : []);
}

function chimPhysicalDiaryContent(array $entries, $maxCharacters = 1800)
{
    $sections = [];
    foreach ($entries as $entry) {
        $headingParts = [];
        $topic = trim((string)($entry['topic'] ?? ''));
        $location = trim((string)($entry['location'] ?? ''));
        if ($topic !== '') {
            $headingParts[] = $topic;
        }
        if ($location !== '' && stripos($topic, $location) === false) {
            $headingParts[] = $location;
        }

        $content = trim((string)($entry['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $heading = empty($headingParts) ? '' : '[' . implode(' - ', $headingParts) . "]\n";
        $sections[] = $heading . $content;
    }

    $text = trim(implode("\n\n", $sections));
    $maxCharacters = max(200, (int)$maxCharacters);
    if (mb_strlen($text, 'UTF-8') > $maxCharacters) {
        $text = mb_substr($text, -$maxCharacters, null, 'UTF-8');
        $firstBreak = mb_strpos($text, "\n\n", 0, 'UTF-8');
        if ($firstBreak !== false) {
            $text = mb_substr($text, $firstBreak + 2, null, 'UTF-8');
        }
        $text = "Earlier pages omitted.\n\n" . ltrim($text);
    }

    return $text;
}

function chimPhysicalDiaryRender($title, $content, $renderer = null)
{
    if ($renderer === null) {
        require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'rolemaster_helpers.php';
        $renderer = 'createLetter';
    }

    if (!is_callable($renderer)) {
        return false;
    }

    ob_start();
    try {
        call_user_func($renderer, $title, $content);
    } finally {
        ob_end_clean();
    }
    return true;
}

function chimPhysicalDiaryStoreBook($title, $content, $gamets)
{
    $safeTitle = chimPhysicalDiaryEscape($title);
    $existing = $GLOBALS['db']->fetchOne(
        "SELECT rowid FROM books WHERE sess = 'physical_diary' AND title = '{$safeTitle}' ORDER BY rowid DESC LIMIT 1"
    );

    if (!empty($existing['rowid'])) {
        $safeContent = chimPhysicalDiaryEscape($content);
        $GLOBALS['db']->execQuery(
            "UPDATE books SET content = '{$safeContent}', gamets = " . (int)$gamets . ", localts = " . time() .
            " WHERE rowid = " . (int)$existing['rowid']
        );
        return;
    }

    $GLOBALS['db']->insert('books', [
        'ts' => time(),
        'gamets' => (int)$gamets,
        'content' => $content,
        'sess' => 'physical_diary',
        'localts' => time(),
        'title' => $title,
    ]);
}

function chimPhysicalDiaryMaterialize($npcName, $refId, $gamets, $renderer = null, $queueSpawn = true)
{
    $npcName = trim((string)$npcName);
    $signedRefId = chimPhysicalDiaryRefIdToSignedInt($refId);
    if ($npcName === '' || ($queueSpawn && $signedRefId === null)) {
        return ['ok' => false, 'error' => 'missing_npc_reference'];
    }

    $entries = chimPhysicalDiaryEntries($npcName);
    if (empty($entries)) {
        return ['ok' => false, 'error' => 'no_diary_entries'];
    }

    $title = chimPhysicalDiaryTitle($npcName);
    $content = chimPhysicalDiaryContent($entries);
    if ($content === '' || !chimPhysicalDiaryRender($title, $content, $renderer)) {
        return ['ok' => false, 'error' => 'render_failed'];
    }

    $safeName = chimPhysicalDiaryEscape($npcName);
    $safeTitle = chimPhysicalDiaryEscape($title);
    $latestLocalts = max(array_map(static fn($entry) => (int)($entry['localts'] ?? 0), $entries));
    $now = time();
    $existing = $GLOBALS['db']->fetchOne(
        "SELECT npc_name FROM physical_npc_diaries WHERE lower(npc_name) = lower('{$safeName}') LIMIT 1"
    );

    if (empty($existing)) {
        $GLOBALS['db']->execQuery(
            "INSERT INTO physical_npc_diaries (npc_name, title, last_diary_localts, created_at, updated_at)
             VALUES ('{$safeName}', '{$safeTitle}', {$latestLocalts}, {$now}, {$now})"
        );
    } else {
        $GLOBALS['db']->execQuery(
            "UPDATE physical_npc_diaries
             SET title = '{$safeTitle}', last_diary_localts = {$latestLocalts}, updated_at = {$now}
             WHERE lower(npc_name) = lower('{$safeName}')"
        );
    }

    chimPhysicalDiaryStoreBook($title, $content, $gamets);

    if ($queueSpawn && empty($existing)) {
        $taskId = str_replace('@', '', (string)($GLOBALS['taskId'] ?? '0'));
        $safeCommandTitle = str_replace('@', '', $title);
        $GLOBALS['db']->insert('responselog', [
            'localts' => $now,
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|spawnBook@{$safeCommandTitle}@0@{$signedRefId}@{$taskId}@{$safeCommandTitle}",
            'tag' => '',
        ]);
    }

    return [
        'ok' => true,
        'created' => empty($existing),
        'title' => $title,
        'entry_count' => count($entries),
    ];
}

function chimPhysicalDiaryRefreshIfActive($npcName, $gamets, $renderer = null)
{
    $safeName = chimPhysicalDiaryEscape($npcName);
    $existing = $GLOBALS['db']->fetchOne(
        "SELECT npc_name FROM physical_npc_diaries WHERE lower(npc_name) = lower('{$safeName}') LIMIT 1"
    );
    if (empty($existing)) {
        return ['ok' => true, 'active' => false];
    }

    $result = chimPhysicalDiaryMaterialize($npcName, null, $gamets, $renderer, false);
    $result['active'] = true;
    return $result;
}
