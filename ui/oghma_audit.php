<?php

declare(strict_types=1);

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, ['load_general_settings' => true, 'load_player_name' => true, 'load_narrator' => true]);
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'oghma_parity.php';

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
    . "percentile_cont(0.95) WITHIN GROUP (ORDER BY latency_ms) AS p95, "
    . "count(*) FILTER (WHERE status IN ('grounded', 'fallback_succeeded')) AS grounded, "
    . "count(*) FILTER (WHERE status = 'fallback_succeeded') AS fallback_succeeded "
    . 'FROM public.oghma_audit'
);
$catalog = $db->fetchOne("SELECT catalog_version, manifest_sha256, row_count, activated_at FROM public.oghma_catalogs WHERE state = 'active'");
$projection = $db->fetchAll('SELECT source_type, count(*) AS count FROM public.oghma GROUP BY source_type ORDER BY source_type');

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
        h1,h2{color:rgb(242,124,17)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
        .panel,.card{background:#242424;border:1px solid #444;border-radius:10px;padding:14px;margin-bottom:12px}.metric{font-size:22px;font-weight:800}
        .muted{color:#9aa6b7}.badge{display:inline-block;padding:3px 9px;border-radius:99px;background:#163b26;color:#bdf4cb;border:1px solid #35704c}
        .filters a{display:inline-block;margin:3px;padding:6px 10px;border-radius:7px;background:#303030;color:#ddd;text-decoration:none;border:1px solid #4b4b4b}
        .filters a.active{border-color:rgb(242,124,17);color:#fff}.input{white-space:pre-wrap;background:#1b1b1b;padding:9px;border-radius:6px;margin:8px 0}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;color:#dce6f7;background:#181818;padding:10px;border-radius:6px}details{margin-top:8px}
        code{overflow-wrap:anywhere}.pager{display:flex;justify-content:space-between;margin:18px 0}.pager a{color:#ffb46f}
    </style>
</head>
<body><main class="wrap">
    <h1>CHIM Oghma Audit</h1>
    <div class="panel">
        <strong>Contract:</strong> <?= h(CHIM_OGHMA_PARITY_VERSION) ?> &nbsp;
        <strong>Catalog:</strong> <?= h($catalog['catalog_version'] ?? 'not activated') ?> &nbsp;
        <strong>Manifest:</strong> <code><?= h($catalog['manifest_sha256'] ?? 'unavailable') ?></code>
        <div class="muted">Factory/custom projection: <?= h(json_encode($projection, JSON_UNESCAPED_SLASHES)) ?></div>
    </div>
    <section class="grid">
        <div class="panel"><div class="metric"><?= intval($summary['total'] ?? 0) ?></div><div class="muted">Audited requests</div></div>
        <div class="panel"><div class="metric"><?= intval($summary['grounded'] ?? 0) ?></div><div class="muted">Grounded</div></div>
        <div class="panel"><div class="metric"><?= number_format(floatval($summary['p50'] ?? 0), 2) ?> ms</div><div class="muted">p50 total</div></div>
        <div class="panel"><div class="metric"><?= number_format(floatval($summary['p95'] ?? 0), 2) ?> ms</div><div class="muted">p95 total</div></div>
        <div class="panel"><div class="metric"><?= intval($summary['fallback_succeeded'] ?? 0) ?></div><div class="muted">Fallback successes</div></div>
    </section>
    <div class="panel filters"><strong>Status:</strong> <a class="<?= $statusFilter === '' ? 'active' : '' ?>" href="<?= h(auditUrl(1,$perPage,'')) ?>">All</a>
        <?php foreach ($statuses as $status): ?><a class="<?= $statusFilter === $status['status'] ? 'active' : '' ?>" href="<?= h(auditUrl(1,$perPage,(string)$status['status'])) ?>"><?= h($status['status']) ?> (<?= intval($status['count']) ?>)</a><?php endforeach; ?>
    </div>
    <?php if ($rows === []): ?><div class="panel muted">No parity audit rows yet.</div><?php endif; ?>
    <?php foreach ($rows as $row):
        $grounded = oghmaAuditJson($row['grounded'] ?? []); $access = oghmaAuditJson($row['access'] ?? []);
        $fallback = oghmaAuditJson($row['fallback'] ?? []); $settings = oghmaAuditJson($row['settings'] ?? []);
        $topics = is_array($grounded['topics'] ?? null) ? implode(', ', $grounded['topics']) : '';
    ?>
        <article class="card">
            <div><span class="badge"><?= h($row['status']) ?></span> <strong><?= h($topics !== '' ? $topics : 'No topic') ?></strong>
                <span class="muted"> · <?= h($row['created_at']) ?> · <?= number_format(floatval($row['latency_ms']), 3) ?> ms · <?= h($row['request_type']) ?></span></div>
            <div class="input"><?= h($row['input']) ?></div>
            <div><strong>Access:</strong> <?= h(implode(', ', array_map(static fn(array $item): string => ($item['topic'] ?? '?') . ':' . ($item['level'] ?? '?') . ':' . ($item['reason'] ?? '?'), array_filter($access, 'is_array')))) ?></div>
            <div><strong>Fallback:</strong> <?= h($fallback['status'] ?? 'not_attempted') ?><?php if (!empty($fallback['suggestions'])): ?> · suggestions <?= h(implode(', ', $fallback['suggestions'])) ?><?php endif; ?></div>
            <details><summary>Grounding and rejection trace</summary><pre><?= h(json_encode($grounded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre></details>
            <details><summary>Effective settings and sources</summary><pre><?= h(json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre></details>
            <details><summary>Checksums</summary><pre>catalog=<?= h($row['catalog_version']) ?>
manifest=<?= h($row['catalog_manifest_sha256']) ?>
prompt=<?= h($row['prompt_sha256']) ?></pre></details>
        </article>
    <?php endforeach; ?>
    <nav class="pager"><a href="<?= h(auditUrl(max(1,$page-1),$perPage,$statusFilter)) ?>">Previous</a><span>Page <?= $page ?> of <?= $pages ?> (<?= $total ?> rows)</span><a href="<?= h(auditUrl(min($pages,$page+1),$perPage,$statusFilter)) ?>">Next</a></nav>
</main></body></html>
