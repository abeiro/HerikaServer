<?php

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oghmaAuditWebRoot(): string
{
    $scriptPath = strval($_SERVER['SCRIPT_NAME'] ?? '');
    $root = dirname(dirname($scriptPath));
    if ($root === '/' || $root === '\\') {
        $root = '';
    }
    return rtrim($root, '/');
}

function oghmaAuditParseKeyValueString(string $raw, string $separator): array
{
    $pairs = [];
    foreach (explode($separator, $raw) as $part) {
        $piece = trim($part);
        if ($piece === '') {
            continue;
        }
        $pos = strpos($piece, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($piece, 0, $pos));
        $value = trim(substr($piece, $pos + 1));
        if ($key === '') {
            continue;
        }
        $pairs[$key] = $value;
    }
    return $pairs;
}

function oghmaAuditBuildWhereClause(bool $matchedOnly): string
{
    if ($matchedOnly) {
        return "WHERE COALESCE(memory, '') LIKE '%selected=%'";
    }
    return '';
}

function oghmaAuditCountRows(bool $matchedOnly = false): int
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        return 0;
    }

    try {
        $whereSql = oghmaAuditBuildWhereClause($matchedOnly);
        $row = $db->fetchOne('SELECT COUNT(*) AS total FROM audit_memory ' . $whereSql);
        return intval($row['total'] ?? 0);
    } catch (Throwable $exception) {
        Logger::warn("oghma_audit count failed: " . $exception->getMessage());
        return 0;
    }
}

function oghmaAuditFetchRows(int $limit = 50, int $offset = 0, bool $matchedOnly = false): array
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        return [];
    }

    $safeLimit = max(10, min(100, $limit));
    $safeOffset = max(0, $offset);
    try {
        $whereSql = oghmaAuditBuildWhereClause($matchedOnly);
        return $db->fetchAll(
            'SELECT created_at, input, keywords, rank_any, rank_all, memory, "time"
             FROM audit_memory
             ' . $whereSql . '
             ORDER BY created_at DESC
             LIMIT ' . intval($safeLimit) . '
             OFFSET ' . intval($safeOffset)
        );
    } catch (Throwable $exception) {
        Logger::warn("oghma_audit fetch failed: " . $exception->getMessage());
        return [];
    }
}

function oghmaAuditBuildQuery(array $params): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $filtered[$key] = strval($value);
    }
    if (count($filtered) === 0) {
        return '';
    }
    return '?' . http_build_query($filtered);
}

function oghmaAuditSelectedTopic(array $memoryMap, string $memory): string
{
    $selected = trim(strval($memoryMap['selected'] ?? ''));
    if ($selected !== '') {
        return $selected;
    }

    if (preg_match('/=>\s*([^\/]+?)\s*$/', $memory, $matches)) {
        return trim(strval($matches[1]));
    }

    return '';
}

function oghmaAuditSignalTrace(string $signals, string $memory): string
{
    if (trim($signals) !== '') {
        return $signals;
    }

    $arrowPos = strrpos($memory, '=>');
    if ($arrowPos !== false) {
        return trim(substr($memory, 0, $arrowPos));
    }

    return '';
}

$isEmbed = (isset($_GET['embed']) && strval($_GET['embed']) === '1');
$webRoot = oghmaAuditWebRoot();
$matchedOnly = isset($_GET['matched']) && strval($_GET['matched']) === '1';
$page = max(1, intval($_GET['page'] ?? 1));
$perPageAllowed = [25, 50, 100];
$perPageRaw = intval($_GET['per_page'] ?? 50);
$perPage = in_array($perPageRaw, $perPageAllowed, true) ? $perPageRaw : 50;

$totalRows = oghmaAuditCountRows($matchedOnly);
$totalPages = max(1, intval(ceil($totalRows / $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$rows = oghmaAuditFetchRows($perPage, $offset, $matchedOnly);

$baseParams = [];
if ($isEmbed) {
    $baseParams['embed'] = '1';
}
$baseParams['per_page'] = strval($perPage);
$paginationBaseParams = $baseParams;
if ($matchedOnly) {
    $paginationBaseParams['matched'] = '1';
}

$rangeStart = $totalRows > 0 ? ($offset + 1) : 0;
$rangeEnd = min($offset + $perPage, $totalRows);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oghma Audit</title>
    <link rel="icon" type="image/x-icon" href="/HerikaServer/ui/images/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/chim-theme.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'chim-theme.css'); ?>">
    <?php if (!$isEmbed): ?>
        <link rel="stylesheet" href="css/navbar.css">
    <?php endif; ?>
    <style>
        body { background:#1f1f1f; color:#e7e7e7; }
        main.page-wrap { padding: <?= $isEmbed ? '20px' : '110px' ?> 12px 32px; }
        .page-header, .audit-card {
            background: linear-gradient(180deg, rgba(42,42,42,.96), rgba(30,30,30,.98));
            border: 1px solid #3b3b3b;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .page-header { padding: 18px; margin-bottom: 18px; text-align: center; }
        .audit-card { padding: 14px; margin-bottom: 14px; }
        .meta-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        .meta-pill {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(242,124,17,.22);
            border-radius: 8px;
            padding: 8px 10px;
        }
        .meta-label { color:rgb(242,124,17); font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; }
        .meta-value { font-size:.92rem; word-break: break-word; }
        .section-label { color:rgb(242,124,17); font-weight:700; margin-bottom:4px; }
        .trace-box {
            background: rgba(0,0,0,.22);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 8px;
            padding: 10px;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: Consolas, Monaco, monospace;
            font-size: .85rem;
        }
        .toolbar-wrap {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 14px;
        }
        .search-input {
            width: 100%; background:#111; color:#f2f2f2; border:1px solid #4a4a4a; border-radius:8px; padding:10px 12px;
        }
        .quick-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(242,124,17,.25);
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            user-select: none;
        }
        .quick-toggle input { accent-color: rgb(242,124,17); }
        .pager-wrap {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .pager-meta { color: #b8b8b8; font-size: .9rem; }
        .pager-links {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pager-link {
            display: inline-block;
            color: #efefef;
            text-decoration: none;
            border: 1px solid rgba(242,124,17,.28);
            border-radius: 8px;
            padding: 6px 10px;
            background: rgba(255,255,255,.02);
        }
        .pager-link[aria-disabled="true"] {
            opacity: .45;
            pointer-events: none;
        }
        .per-page-select {
            background:#111; color:#f2f2f2; border:1px solid #4a4a4a; border-radius:8px; padding:6px 8px;
        }
        .empty-state { padding: 20px; text-align:center; color:#aaa; }
        @media (max-width: 850px) {
            .toolbar-wrap { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php if (!$isEmbed): ?>
    <?php include(__DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'navbar.php'); ?>
<?php endif; ?>
<main class="page-wrap container-fluid">
    <div class="page-header">
        <h1>Oghma Audit</h1>
        <div>Review Oghma retrieval attempts, selected topics, ranks, and captured search signals.</div>
    </div>

    <div class="toolbar-wrap">
        <div>
            <input id="auditSearch" class="search-input" type="text" placeholder="Filter current page by input, selected topic, signals, notes...">
        </div>
        <label class="quick-toggle" for="matchedOnlyToggle">
            <input id="matchedOnlyToggle" type="checkbox" <?= $matchedOnly ? 'checked' : '' ?>>
            <span>Only Matched</span>
        </label>
        <form method="get" action="" style="margin:0;">
            <?php if ($isEmbed): ?>
                <input type="hidden" name="embed" value="1">
            <?php endif; ?>
            <?php if ($matchedOnly): ?>
                <input type="hidden" name="matched" value="1">
            <?php endif; ?>
            <input type="hidden" name="page" value="1">
            <label style="display:inline-flex; align-items:center; gap:8px; margin:0;">
                <span style="font-size:.9rem; color:#b8b8b8;">Per page</span>
                <select class="per-page-select" name="per_page" onchange="this.form.submit()">
                    <?php foreach ($perPageAllowed as $option): ?>
                        <option value="<?= h(strval($option)) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= h(strval($option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <div class="pager-wrap">
        <div class="pager-meta">
            Showing <?= h(strval($rangeStart)) ?>-<?= h(strval($rangeEnd)) ?> of <?= h(strval($totalRows)) ?> rows
            <?php if ($matchedOnly): ?>
                (matched only)
            <?php endif; ?>
        </div>
        <div class="pager-links">
            <?php
                $prevParams = $paginationBaseParams;
                $prevParams['page'] = strval(max(1, $page - 1));
                $nextParams = $paginationBaseParams;
                $nextParams['page'] = strval(min($totalPages, $page + 1));
            ?>
            <a class="pager-link" href="<?= h(oghmaAuditBuildQuery($prevParams)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Prev</a>
            <span class="pager-meta">Page <?= h(strval($page)) ?> / <?= h(strval($totalPages)) ?></span>
            <a class="pager-link" href="<?= h(oghmaAuditBuildQuery($nextParams)) ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
        </div>
    </div>

    <?php if (count($rows) === 0): ?>
        <div class="audit-card empty-state">No rows in audit_memory yet.</div>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <?php
                $input = strval($row['input'] ?? '');
                $keywords = strval($row['keywords'] ?? '');
                $memory = strval($row['memory'] ?? '');
                $rank = strval($row['rank_any'] ?? '0');
                $elapsed = strval($row['time'] ?? '');
                $created = strval($row['created_at'] ?? '');
                $keywordMap = oghmaAuditParseKeyValueString($keywords, ' | ');
                $memoryMap = oghmaAuditParseKeyValueString($memory, ' / ');
                $selected = oghmaAuditSelectedTopic($memoryMap, $memory);
                $selectedMode = strval($memoryMap['mode'] ?? '');
                $entryId = strval($memoryMap['entry_id'] ?? '');
                $npcName = strval($keywordMap['npc'] ?? ($memoryMap['npc'] ?? ''));
                $eventType = strval($keywordMap['event'] ?? ($memoryMap['event'] ?? ''));
                $topics = strval($keywordMap['topics'] ?? '');
                $notes = strval($keywordMap['notes'] ?? '');
                $signals = oghmaAuditSignalTrace(strval($keywordMap['signals'] ?? ($memoryMap['signals'] ?? '')), $memory);
                $context = strval($memoryMap['context'] ?? '');
                $location = strval($memoryMap['location'] ?? '');
                $status = $selected !== '' ? 'Matched' : 'No Match';
                $searchBlob = strtolower(implode(' ', [$input, $selected, $topics, $notes, $signals, $context, $location, $created, $npcName, $eventType]));
            ?>
            <section class="audit-card" data-search="<?= h($searchBlob) ?>">
                <div class="meta-grid">
                    <div class="meta-pill"><div class="meta-label">Status</div><div class="meta-value"><?= h($status) ?></div></div>
                    <div class="meta-pill"><div class="meta-label">NPC</div><div class="meta-value"><?= h($npcName !== '' ? $npcName : '(unknown)') ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Event</div><div class="meta-value"><?= h($eventType !== '' ? $eventType : '(unknown)') ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Selected Topic</div><div class="meta-value"><?= h($selected !== '' ? $selected : '(none)') ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Rank</div><div class="meta-value"><?= h($rank) ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Mode</div><div class="meta-value"><?= h($selectedMode !== '' ? $selectedMode : '(n/a)') ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Entry ID</div><div class="meta-value"><?= h($entryId !== '' ? $entryId : '(n/a)') ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Created</div><div class="meta-value"><?= h($created) ?></div></div>
                    <div class="meta-pill"><div class="meta-label">Elapsed</div><div class="meta-value"><?= h($elapsed) ?></div></div>
                </div>

                <div class="section-label">Input</div>
                <div class="trace-box"><?= h($input) ?></div>

                <div class="section-label" style="margin-top:10px;">Extracted Topics</div>
                <div class="trace-box"><?= h($topics !== '' ? $topics : '(none)') ?></div>

                <div class="section-label" style="margin-top:10px;">Signals Used For Ranking</div>
                <div class="trace-box"><?= h($signals !== '' ? $signals : '(not captured)') ?></div>

                <div class="section-label" style="margin-top:10px;">Ranking Notes</div>
                <div class="trace-box"><?= h($notes !== '' ? $notes : '(none)') ?></div>

                <div class="section-label" style="margin-top:10px;">Context Snapshot</div>
                <div class="trace-box"><?php
                    $contextParts = [];
                    if ($location !== '') { $contextParts[] = 'location=' . $location; }
                    if ($context !== '') { $contextParts[] = 'context=' . $context; }
                    if (isset($memoryMap['before'])) { $contextParts[] = 'before=' . strval($memoryMap['before']); }
                    if (isset($memoryMap['after'])) { $contextParts[] = 'after=' . strval($memoryMap['after']); }
                    if (isset($memoryMap['tags'])) { $contextParts[] = 'tags=' . strval($memoryMap['tags']); }
                    echo h(count($contextParts) > 0 ? implode("\n", $contextParts) : '(none)');
                ?></div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<script>
const matchedToggle = document.getElementById('matchedOnlyToggle');
if (matchedToggle) {
    matchedToggle.addEventListener('change', () => {
        const url = new URL(window.location.href);
        if (matchedToggle.checked) {
            url.searchParams.set('matched', '1');
        } else {
            url.searchParams.delete('matched');
        }
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    });
}

const searchInput = document.getElementById('auditSearch');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const needle = String(searchInput.value || '').trim().toLowerCase();
    document.querySelectorAll('[data-search]').forEach((card) => {
      const hay = String(card.getAttribute('data-search') || '').toLowerCase();
      card.style.display = (needle === '' || hay.includes(needle)) ? '' : 'none';
    });
  });
}
</script>
</body>
</html>
