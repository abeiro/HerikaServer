<?php

declare(strict_types=1);

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, ['load_general_settings' => true, 'load_player_name' => true, 'load_narrator' => true]);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oghmaAuditJson($value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

/** Plain-English label for an internal status; unknown values fall back to title case. */
function oghmaAuditStatusLabel($status): string
{
    $known = [
        'grounded' => 'Topic found',
        'no_match' => 'No topic found',
        'fallback_succeeded' => 'Found after a suggestion',
        'fallback_unresolved' => 'Suggestion did not match',
        'fallback_failed' => 'Suggestion lookup failed',
        'fallback_disabled' => 'Suggestions turned off',
        'fallback_unconfigured' => 'Suggestions not set up',
        'not_attempted' => 'Suggestions not needed',
        'disabled' => 'Oghma turned off',
        'ineligible' => 'Request not eligible',
        'unavailable' => 'Knowledge unavailable',
        'not_run' => 'Not checked',
        'legacy' => 'Older record',
    ];
    $key = strtolower(trim((string) $status));
    if ($key === '') return 'Unknown';
    return $known[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
}

/** Plain-English label for how much of an article was shared. */
function oghmaAuditLevelLabel($level): string
{
    $key = strtolower(trim((string) $level));
    if ($key === 'advanced') return 'Detailed';
    if ($key === 'basic') return 'Basic';
    if ($key === '') return 'Unknown';
    return ucwords(str_replace(['_', '-'], ' ', $key));
}

/** Successful outcomes keep the default green badge; everything else gets the neutral tone. */
function oghmaAuditStatusTone($status): string
{
    return in_array(strtolower(trim((string) $status)), ['grounded', 'fallback_succeeded'], true) ? '' : ' badge-note';
}

$db = $GLOBALS['db'];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, intval($_GET['page'] ?? 1));
$requestedPerPage = intval($_GET['per_page'] ?? 50);
$perPage = in_array($requestedPerPage, [25, 50, 100], true) ? $requestedPerPage : 50;
$where = $statusFilter === '' ? '' : 'WHERE status = ' . $db->escapeLiteral($statusFilter);
$count = $db->fetchOne('SELECT count(*) AS total FROM public.oghma_audit ' . $where);
$total = intval($count['total'] ?? 0);
$pages = max(1, intval(ceil($total / $perPage)));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$rows = $db->fetchAll(
    'SELECT id, created_at, request_type, input, status, grounded, access, fallback, settings, '
    . 'catalog_version, catalog_manifest_sha256, latency_ms, prompt_sha256 '
    . 'FROM public.oghma_audit ' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$statuses = $db->fetchAll('SELECT status, count(*) AS count FROM public.oghma_audit GROUP BY status ORDER BY status');
$summary = $db->fetchOne(
    "SELECT count(*) AS total, percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms) AS p50, "
    . "count(*) FILTER (WHERE status IN ('grounded', 'fallback_succeeded')) AS grounded, "
    . "count(*) FILTER (WHERE status = 'fallback_succeeded') AS fallback_succeeded "
    . 'FROM public.oghma_audit'
);

function auditUrl(int $page, int $perPage, string $status): string
{
    return '?' . http_build_query(array_filter(['page' => $page, 'per_page' => $perPage, 'status' => $status], static fn($v) => $v !== ''));
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CHIM Oghma Audit</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        body{background:#171717;color:#eee;font-family:Arial,sans-serif;margin:0}.wrap{max-width:1500px;margin:auto;padding:22px}
        h1,h2{color:rgb(242,124,17)}h1{margin-bottom:6px}.intro{color:#c8cfd9;margin:0 0 16px;max-width:720px;line-height:1.5}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
        .panel,.card{background:#242424;border:1px solid #444;border-radius:10px;padding:14px;margin-bottom:12px}.metric{font-size:22px;font-weight:800}
        .muted{color:#9aa6b7}.badge{display:inline-block;padding:3px 9px;border-radius:99px;background:#163b26;color:#bdf4cb;border:1px solid #35704c}
        .badge-note{background:#2c2a20;color:#f0d9a8;border-color:#6d5c31}
        .filters a{display:inline-block;margin:3px;padding:6px 10px;border-radius:7px;background:#303030;color:#ddd;text-decoration:none;border:1px solid #4b4b4b}
        .filters a.active{border-color:rgb(242,124,17);color:#fff}.input{white-space:pre-wrap;background:#1b1b1b;padding:9px;border-radius:6px;margin:4px 0 0}
        .cardhead{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px}.cardhead strong{overflow-wrap:anywhere}
        .field{margin-top:9px}.label{display:block;color:#9aa6b7;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
        .used{margin:2px 0 0;padding-left:18px}.used li{margin:1px 0;overflow-wrap:anywhere}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;color:#dce6f7;background:#181818;padding:10px;border-radius:6px}details{margin-top:10px}
        summary{cursor:pointer;color:rgb(242,124,17)}details h3{color:#ddd;font-size:13px;margin:10px 0 4px}
        a:focus-visible,summary:focus-visible{outline:2px solid rgb(242,124,17);outline-offset:2px}
        code{overflow-wrap:anywhere}.pager{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;margin:18px 0}.pager a{color:#ffb46f}
        @media(max-width:420px){.wrap{padding:14px}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel,.card{padding:11px}.metric{font-size:19px}}
    </style>
</head>
<body><main class="wrap">
    <h1>CHIM Oghma Audit</h1>
    <p class="intro">See which topics Oghma found and what knowledge it gave the NPC.</p>
    <section class="grid">
        <div class="panel"><div class="metric"><?= intval($summary['total'] ?? 0) ?></div><div class="muted">Audited requests</div></div>
        <div class="panel"><div class="metric"><?= intval($summary['grounded'] ?? 0) ?></div><div class="muted">Topics matched</div></div>
        <div class="panel"><div class="metric"><?= number_format(floatval($summary['p50'] ?? 0), 2) ?> ms</div><div class="muted">Typical lookup time</div></div>
        <div class="panel"><div class="metric"><?= intval($summary['fallback_succeeded'] ?? 0) ?></div><div class="muted">Suggestion matches</div></div>
    </section>
    <div class="panel filters"><strong>Show:</strong> <a class="<?= $statusFilter === '' ? 'active' : '' ?>"<?= $statusFilter === '' ? ' aria-current="true"' : '' ?> href="<?= h(auditUrl(1,$perPage,'')) ?>">All</a>
        <?php foreach ($statuses as $status): $isActive = $statusFilter === $status['status']; ?><a class="<?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="true"' : '' ?> href="<?= h(auditUrl(1,$perPage,(string)$status['status'])) ?>"><?= h(oghmaAuditStatusLabel($status['status'])) ?> (<?= intval($status['count']) ?>)</a><?php endforeach; ?>
    </div>
    <?php if ($rows === []): ?><div class="panel muted">No audit records yet.</div><?php endif; ?>
    <?php foreach ($rows as $row):
        $grounded = oghmaAuditJson($row['grounded'] ?? []); $access = oghmaAuditJson($row['access'] ?? []);
        $fallback = oghmaAuditJson($row['fallback'] ?? []); $settings = oghmaAuditJson($row['settings'] ?? []);
        $topics = is_array($grounded['topics'] ?? null) ? implode(', ', $grounded['topics']) : '';
        $used = [];
        foreach ($access as $item) {
            if (!is_array($item) || empty($item['selected'])) continue;
            $usedTopic = trim((string) ($item['topic'] ?? ''));
            $used[] = ['topic' => $usedTopic !== '' ? $usedTopic : 'Unnamed topic', 'level' => oghmaAuditLevelLabel($item['level'] ?? '')];
        }
        $fallbackStatus = strtolower(trim((string) ($fallback['status'] ?? 'not_attempted')));
        $suggestions = is_array($fallback['suggestions'] ?? null) ? array_filter($fallback['suggestions'], static fn($v): bool => trim((string) $v) !== '') : [];
        $showFallback = ($fallbackStatus !== '' && $fallbackStatus !== 'not_attempted') || $suggestions !== [];
    ?>
        <article class="card">
            <div class="cardhead"><span class="badge<?= h(oghmaAuditStatusTone($row['status'])) ?>"><?= h(oghmaAuditStatusLabel($row['status'])) ?></span> <strong><?= h($topics !== '' ? $topics : 'No topic detected') ?></strong>
                <span class="muted"><?= h($row['created_at']) ?> &middot; <?= number_format(floatval($row['latency_ms']), 3) ?> ms</span></div>
            <div class="field"><span class="label">Conversation input</span><div class="input"><?= h($row['input']) ?></div></div>
            <div class="field"><span class="label">Knowledge used</span>
                <?php if ($used === []): ?><span class="muted">None shared</span><?php else: ?>
                <ul class="used"><?php foreach ($used as $entry): ?><li><?= h($entry['topic']) ?> &mdash; <?= h($entry['level']) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
            <?php if ($showFallback): ?>
            <div class="field"><span class="label">Suggested match</span><?= h(oghmaAuditStatusLabel($fallbackStatus)) ?><?php if ($suggestions !== []): ?> &middot; suggested <?= h(implode(', ', $suggestions)) ?><?php endif; ?></div>
            <?php endif; ?>
            <details><summary>Technical details</summary>
                <h3>Grounding and rejection trace</h3><pre><?= h(json_encode($grounded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                <h3>Knowledge access decisions</h3><pre><?= h(json_encode($access, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                <h3>Fallback record</h3><pre><?= h(json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                <h3>Effective settings and sources</h3><pre><?= h(json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                <h3>Request and checksums</h3><pre>status=<?= h($row['status']) ?>
request_type=<?= h($row['request_type']) ?>
catalog=<?= h($row['catalog_version']) ?>
manifest=<?= h($row['catalog_manifest_sha256']) ?>
prompt=<?= h($row['prompt_sha256']) ?></pre>
            </details>
        </article>
    <?php endforeach; ?>
    <nav class="pager"><a href="<?= h(auditUrl(max(1,$page-1),$perPage,$statusFilter)) ?>">Previous</a><span>Page <?= $page ?> of <?= $pages ?> (<?= $total ?> rows)</span><a href="<?= h(auditUrl(min($pages,$page+1),$perPage,$statusFilter)) ?>">Next</a></nav>
</main></body></html>
