<?php

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function safeFetchAll(sql $db, string $query): array
{
    try {
        return $db->fetchAll($query);
    } catch (Throwable $exception) {
        Logger::warn("request_logs fetchAll failed: " . $exception->getMessage());
        return [];
    }
}

function safeFetchOne(sql $db, string $query): array
{
    try {
        $row = $db->fetchOne($query);
        return is_array($row) ? $row : [];
    } catch (Throwable $exception) {
        Logger::warn("request_logs fetchOne failed: " . $exception->getMessage());
        return [];
    }
}

function safeExec(sql $db, string $query): bool
{
    try {
        return (bool)$db->execQuery($query);
    } catch (Throwable $exception) {
        Logger::warn("request_logs exec failed: " . $exception->getMessage());
        return false;
    }
}

function truncateText(string $value, int $maxLen = 140): string
{
    $trimmed = trim($value);
    if ($trimmed === "") {
        return "";
    }

    if (function_exists("grapheme_strlen") && function_exists("grapheme_substr")) {
        $length = grapheme_strlen($trimmed);
        if ($length !== false && $length > $maxLen) {
            return (string)grapheme_substr($trimmed, 0, $maxLen) . "...";
        }
    }

    if (function_exists("mb_strlen") && function_exists("mb_substr")) {
        if (mb_strlen($trimmed, "UTF-8") > $maxLen) {
            return mb_substr($trimmed, 0, $maxLen, "UTF-8") . "...";
        }
    }

    if (strlen($trimmed) > $maxLen) {
        return substr($trimmed, 0, $maxLen) . "...";
    }

    return $trimmed;
}

function requestLogsUrl(int $page, int $limit, bool $embedded): string
{
    $params = [
        "page" => max(1, $page),
        "limit" => max(10, $limit),
    ];

    if ($embedded) {
        $params["embed"] = "1";
    }

    return "request_logs.php?" . http_build_query($params);
}

function pickFirstAvailableColumn(array $availableColumns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (isset($availableColumns[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function getAuditRequestColumns(sql $db): array
{
    $driver = strtolower((string)($GLOBALS["DBDRIVER"] ?? ""));
    $rows = [];

    if ($driver === "sqlite3") {
        $rows = safeFetchAll($db, "PRAGMA table_info(audit_request)");
        $columnKey = "name";
    } else {
        $rows = safeFetchAll(
            $db,
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'audit_request'"
        );
        $columnKey = "column_name";
    }

    $columns = [];
    foreach ($rows as $row) {
        $name = strtolower(trim((string)($row[$columnKey] ?? "")));
        if ($name !== "") {
            $columns[$name] = true;
        }
    }

    return $columns;
}

function formatAuditTimestamp(array $row): string
{
    $localTs = intval($row["localts"] ?? 0);
    if ($localTs > 0) {
        $dt = new DateTime("@" . $localTs);
        $dt->setTimezone(new DateTimeZone("UTC"));
        return $dt->format("d-m-Y H:i:s");
    }

    $createdAt = trim((string)($row["created_at"] ?? ""));
    if ($createdAt !== "") {
        try {
            $dt = new DateTime($createdAt, new DateTimeZone("UTC"));
            $dt->setTimezone(new DateTimeZone("UTC"));
            return $dt->format("d-m-Y H:i:s");
        } catch (Throwable $exception) {
            return $createdAt;
        }
    }

    return "";
}

function parseUsageMetrics(mixed $usage): array
{
    if (is_array($usage)) {
        $decoded = $usage;
    } else {
        $usageText = trim((string)$usage);
        if ($usageText === "") {
            return ["prompt" => null, "completion" => null, "total" => null, "has_data" => false];
        }

        $decoded = json_decode($usageText, true);
        if (!is_array($decoded)) {
            return ["prompt" => null, "completion" => null, "total" => null, "has_data" => false];
        }
    }

    $prompt = $decoded["prompt_tokens"] ?? $decoded["input_tokens"] ?? $decoded["promptTokenCount"] ?? null;
    $completion = $decoded["completion_tokens"] ?? $decoded["output_tokens"] ?? $decoded["completionTokenCount"] ?? null;
    $total = $decoded["total_tokens"] ?? $decoded["totalTokenCount"] ?? null;

    if ($total === null && (is_numeric($prompt) || is_numeric($completion))) {
        $total = intval($prompt ?? 0) + intval($completion ?? 0);
    }

    $hasData = is_numeric($prompt) || is_numeric($completion) || is_numeric($total);

    return [
        "prompt" => is_numeric($prompt) ? intval($prompt) : null,
        "completion" => is_numeric($completion) ? intval($completion) : null,
        "total" => is_numeric($total) ? intval($total) : null,
        "has_data" => $hasData,
    ];
}

function getTokenBreakdown(array $row): array
{
    $prompt = $row["prompt_tokens"] ?? null;
    $completion = $row["completion_tokens"] ?? null;
    $total = $row["total_tokens"] ?? null;

    $hasExplicit = is_numeric($prompt) || is_numeric($completion) || is_numeric($total);
    if ($hasExplicit) {
        if ($total === null && (is_numeric($prompt) || is_numeric($completion))) {
            $total = intval($prompt ?? 0) + intval($completion ?? 0);
        }

        return [
            "prompt" => is_numeric($prompt) ? intval($prompt) : null,
            "completion" => is_numeric($completion) ? intval($completion) : null,
            "total" => is_numeric($total) ? intval($total) : null,
            "has_data" => true,
        ];
    }

    return parseUsageMetrics($row["usage"] ?? "");
}

function getStatusPresentation(array $row): array
{
    $statusText = strtolower(trim((string)($row["status"] ?? "")));
    $resultText = trim((string)($row["result"] ?? ""));
    $errorText = trim((string)($row["error"] ?? ""));

    if ($statusText === "") {
        $normalizedResult = strtoupper($resultText);
        if ($errorText !== "") {
            $statusText = "error";
        } elseif ($normalizedResult === "OK" || str_starts_with($normalizedResult, "OK|") || str_starts_with($normalizedResult, "OK ")) {
            $statusText = "ok";
        } elseif ($resultText === "") {
            $statusText = "unknown";
        } else {
            $statusText = "error";
        }
    }

    $statusClass = "status-unknown";
    if ($statusText === "ok") {
        $statusClass = "status-ok";
    } elseif ($statusText === "error" || $statusText === "failed" || $statusText === "warning") {
        $statusClass = "status-error";
    }

    return [
        "text" => $statusText,
        "class" => $statusClass,
    ];
}

function getErrorPresentation(array $row): string
{
    $errorText = trim((string)($row["error"] ?? ""));
    if ($errorText !== "") {
        return $errorText;
    }

    $resultText = trim((string)($row["result"] ?? ""));
    $normalizedResult = strtoupper($resultText);
    if ($resultText !== "" && $normalizedResult !== "OK" && !str_starts_with($normalizedResult, "OK|") && !str_starts_with($normalizedResult, "OK ")) {
        return $resultText;
    }

    return "";
}

$scriptPath = $_SERVER["SCRIPT_NAME"] ?? "";
$uiPos = strpos($scriptPath, "/ui/");
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = "";
}
if ($webRoot === "/") {
    $webRoot = "";
}
$webRoot = rtrim($webRoot, "/");

$db = new sql();
$GLOBALS["db"] = $db;

$isEmbedded = isset($_GET["embed"]) && strval($_GET["embed"]) === "1";
$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 50;
$limit = max(10, min(300, $limit));
$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

if (isset($_GET["cleanlog"]) && $_GET["cleanlog"] === "1") {
    safeExec($db, "DELETE FROM audit_request");
    header("Location: " . requestLogsUrl(1, $limit, $isEmbedded));
    exit;
}

$availableColumns = getAuditRequestColumns($db);

$columnCandidates = [
    "id" => ["id", "rowid"],
    "localts" => ["localts"],
    "created_at" => ["created_at"],
    "connector" => ["connector"],
    "model" => ["model"],
    "status" => ["status"],
    "prompt_tokens" => ["prompt_tokens"],
    "completion_tokens" => ["completion_tokens"],
    "total_tokens" => ["total_tokens"],
    "usage" => ["usage"],
    "url" => ["url"],
    "request" => ["request"],
    "result" => ["result"],
    "error" => ["error"],
];

$selectExpressions = [];
foreach ($columnCandidates as $alias => $candidates) {
    $sourceColumn = pickFirstAvailableColumn($availableColumns, $candidates);
    if ($sourceColumn !== null) {
        $selectExpressions[] = $sourceColumn . (($sourceColumn === $alias) ? "" : (" AS " . $alias));
    }
}

$orderParts = [];
if (isset($availableColumns["localts"])) {
    $orderParts[] = "localts DESC";
} elseif (isset($availableColumns["created_at"])) {
    $orderParts[] = "created_at DESC";
}

if (isset($availableColumns["id"])) {
    $orderParts[] = "id DESC";
} elseif (isset($availableColumns["rowid"])) {
    $orderParts[] = "rowid DESC";
}

if (count($selectExpressions) === 0) {
    $rows = [];
} else {
    $orderBy = count($orderParts) > 0 ? implode(", ", $orderParts) : "1 DESC";
    $rows = safeFetchAll(
        $db,
        "SELECT " . implode(", ", $selectExpressions) . "
         FROM audit_request
         ORDER BY {$orderBy}
         LIMIT {$limit} OFFSET {$offset}"
    );
}

$totalRow = safeFetchOne($db, "SELECT COUNT(*) AS total FROM audit_request");
$totalRecords = intval($totalRow["total"] ?? 0);
$totalPages = max(1, (int)ceil($totalRecords / $limit));

$TITLE = "CHIM - Request Logs";
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
if (!$isEmbedded) {
    include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo h($webRoot); ?>/ui/css/main.css">
<style>
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo h($webRoot); ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    main {
        padding-top: <?= $isEmbedded ? "20px" : "120px" ?>;
        padding-bottom: 40px;
        padding-left: 10px;
        padding-right: 10px;
    }

    footer {
        display: <?= $isEmbedded ? "none" : "block" ?>;
    }

    .tab-content {
        display: block;
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .page-title {
        margin: 0 0 14px 0;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        letter-spacing: 0;
        text-align: left;
    }

    .meta-line {
        color: #aaa;
        font-size: 0.95em;
        margin: 10px 0 0 0;
    }

    .btn-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-bottom: 6px;
    }

    .btn-base {
        cursor: pointer;
        padding: 7px 12px;
        border-radius: 6px;
        border: 1px solid #666;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }

    .btn-primary {
        background: #2f5d87;
        color: #fff;
        border-color: #4677a4;
    }

    .btn-danger {
        background: #8f1f2e;
        color: #fff;
        border-color: #a43846;
    }

    .btn-secondary {
        background: #3a3a3a;
        color: #f8f9fa;
        border-color: #595959;
    }

    .btn-linklike {
        background: rgba(242, 124, 17, 0.15);
        border: 1px solid rgba(242, 124, 17, 0.35);
        color: #f8f9fa;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        cursor: pointer;
    }

    .btn-linklike:hover {
        background: rgba(242, 124, 17, 0.22);
    }

    .table-container {
        max-height: calc(100vh - 450px);
        margin-top: 20px;
        width: 100%;
        overflow-x: auto;
        overflow-y: auto;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        padding: 12px;
    }

    .table-container table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        margin-bottom: 0;
        font-size: 12px;
    }

    .table-container th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 12px 10px;
        font-weight: bold;
        text-align: left;
        vertical-align: top;
        color: rgb(242, 124, 17);
        background: rgba(26, 26, 26, 0.6);
        border-bottom: 2px solid rgba(242, 124, 17, 0.3);
        font-size: 0.95em;
    }

    .table-container td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid rgba(74, 74, 74, 0.3);
        color: #d0d0d0;
        vertical-align: top;
        line-height: 1.5;
        overflow-wrap: break-word;
        word-wrap: break-word;
        hyphens: auto;
    }

    .table-container tr:hover td {
        background: rgba(242, 124, 17, 0.05);
    }

    .mono {
        font-family: Consolas, "Courier New", monospace;
        word-break: break-word;
    }

    .status-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        font-size: 11px;
        border: 1px solid transparent;
    }

    .status-ok {
        color: #9be49f;
        background: rgba(60, 133, 67, 0.2);
        border-color: rgba(60, 133, 67, 0.5);
    }

    .status-error {
        color: #ffb0b0;
        background: rgba(171, 58, 58, 0.2);
        border-color: rgba(171, 58, 58, 0.5);
    }

    .status-unknown {
        color: #f0dca4;
        background: rgba(125, 112, 66, 0.2);
        border-color: rgba(160, 143, 81, 0.4);
    }

    .empty-state {
        color: #aaa;
        padding: 12px 4px;
    }

    .cell-url {
        min-width: 220px;
        max-width: 320px;
        word-break: break-all;
    }

    .cell-preview {
        min-width: 260px;
        max-width: 420px;
    }

    .modal-backdrop-lite {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 10000;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.55);
        padding: 24px;
    }

    .modal-panel {
        width: min(1100px, 100%);
        max-height: calc(100vh - 48px);
        background: #212121;
        border: 1px solid #464646;
        border-radius: 12px;
        box-shadow: 0 14px 48px rgba(0, 0, 0, 0.45);
        overflow: hidden;
    }

    .modal-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 16px 18px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.25);
        background: rgba(0, 0, 0, 0.18);
    }

    .modal-title {
        margin: 0;
        color: rgb(242, 124, 17);
        font-size: 18px;
        font-family: 'MagicCards', serif;
        word-spacing: 6px;
    }

    .modal-body {
        padding: 18px;
        max-height: calc(100vh - 160px);
        overflow: auto;
    }

    .modal-pre {
        white-space: pre-wrap;
        word-break: break-word;
        color: #f8f9fa;
        margin: 0;
        font-size: 12px;
        line-height: 1.4;
        font-family: Consolas, "Courier New", monospace;
    }

    @media (max-width: 900px) {
        main {
            padding-top: <?= $isEmbedded ? "10px" : "100px" ?>;
        }

        .table-container {
            max-height: none;
        }

        td,
        th {
            padding: 8px;
        }

        .page-title {
            font-size: 1.7em;
        }
    }
</style>

<main class="container-fluid">
    <div class="tab-content">
        <h1 id="page-title" class="page-title">Request to LLM Services Log</h1>

        <div class="btn-row mt-3">
            <?php if ($page > 1): ?>
                <a class="btn-base btn-primary" href="<?= h(requestLogsUrl($page - 1, $limit, $isEmbedded)) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn-base btn-primary" href="<?= h(requestLogsUrl($page + 1, $limit, $isEmbedded)) ?>">Next</a>
            <?php endif; ?>
            <a class="btn-base btn-secondary" href="<?= h(requestLogsUrl(1, 100, $isEmbedded)) ?>">Limit 100</a>
            <a class="btn-base btn-secondary" href="<?= h(requestLogsUrl(1, 200, $isEmbedded)) ?>">Limit 200</a>
            <a class="btn-base btn-danger" href="<?= h(requestLogsUrl(1, $limit, $isEmbedded)) ?>&cleanlog=1" onclick="return confirm('Delete all request log rows?');">Clear Log</a>
        </div>

        <p class="meta-line">
            Showing <?= h((string)count($rows)) ?> of <?= h((string)$totalRecords) ?> records.
            Page <?= h((string)$page) ?> / <?= h((string)$totalPages) ?>.
        </p>

        <div class="table-container">
            <?php if (count($rows) === 0): ?>
                <div class="empty-state">No rows in <code>audit_request</code> yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Time (UTC)</th>
                        <th>Connector / Model</th>
                        <th>Status</th>
                        <th>Tokens</th>
                        <th>URL</th>
                        <th>Request</th>
                        <th>Result</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php
                        $id = trim((string)($row["id"] ?? ""));
                        $timeUtc = formatAuditTimestamp($row);
                        $connector = trim((string)($row["connector"] ?? ""));
                        $model = trim((string)($row["model"] ?? ""));
                        $status = getStatusPresentation($row);
                        $tokenBreakdown = getTokenBreakdown($row);
                        if ($tokenBreakdown["has_data"]) {
                            $tokenCell = sprintf(
                                "%s / %s / %s",
                                $tokenBreakdown["prompt"] === null ? "-" : (string)$tokenBreakdown["prompt"],
                                $tokenBreakdown["completion"] === null ? "-" : (string)$tokenBreakdown["completion"],
                                $tokenBreakdown["total"] === null ? "-" : (string)$tokenBreakdown["total"]
                            );
                        } else {
                            $tokenCell = "";
                        }
                        $url = trim((string)($row["url"] ?? ""));
                        $requestRaw = (string)($row["request"] ?? "");
                        $resultRaw = (string)($row["result"] ?? "");
                        $errorRaw = getErrorPresentation($row);
                        $requestPreview = truncateText($requestRaw, 140);
                        $resultPreview = truncateText($resultRaw, 140);
                        $modalKey = $id !== "" ? $id : (string)$index;
                        $requestModalId = "req_" . preg_replace("/[^A-Za-z0-9_\\-]/", "_", $modalKey);
                        $resultModalId = "res_" . preg_replace("/[^A-Za-z0-9_\\-]/", "_", $modalKey);
                        ?>
                        <tr>
                            <td class="mono"><?= h($id) ?></td>
                            <td><?= h($timeUtc) ?></td>
                            <td>
                                <div><?= h($connector) ?></div>
                                <div class="mono"><?= h($model) ?></div>
                            </td>
                            <td><span class="status-pill <?= h($status["class"]) ?>"><?= h($status["text"]) ?></span></td>
                            <td class="mono"><?= h($tokenCell) ?></td>
                            <td class="mono cell-url"><?= h($url) ?></td>
                            <td class="cell-preview">
                                <div class="mono"><?= h($requestPreview) ?></div>
                                <?php if ($requestRaw !== ""): ?>
                                    <button type="button" class="btn-linklike mt-1" data-open-modal="<?= h($requestModalId) ?>">View</button>
                                <?php endif; ?>
                            </td>
                            <td class="cell-preview">
                                <div class="mono"><?= h($resultPreview) ?></div>
                                <?php if ($resultRaw !== ""): ?>
                                    <button type="button" class="btn-linklike mt-1" data-open-modal="<?= h($resultModalId) ?>">View</button>
                                <?php endif; ?>
                            </td>
                            <td class="mono">
                                <?= h($errorRaw) ?>
                                <div id="<?= h($requestModalId) ?>" class="modal-backdrop-lite">
                                    <div class="modal-panel">
                                        <div class="modal-head">
                                            <h3 class="modal-title">Request Payload (ID <?= h($id) ?>)</h3>
                                            <button type="button" class="btn-base btn-secondary" data-close-modal="<?= h($requestModalId) ?>">Close</button>
                                        </div>
                                        <div class="modal-body"><pre class="modal-pre"><?= h($requestRaw) ?></pre></div>
                                    </div>
                                </div>
                                <div id="<?= h($resultModalId) ?>" class="modal-backdrop-lite">
                                    <div class="modal-panel">
                                        <div class="modal-head">
                                            <h3 class="modal-title">Result Payload (ID <?= h($id) ?>)</h3>
                                            <button type="button" class="btn-base btn-secondary" data-close-modal="<?= h($resultModalId) ?>">Close</button>
                                        </div>
                                        <div class="modal-body"><pre class="modal-pre"><?= h($resultRaw) ?></pre></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
(function () {
    const openButtons = document.querySelectorAll("[data-open-modal]");
    const closeButtons = document.querySelectorAll("[data-close-modal]");

    openButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const id = button.getAttribute("data-open-modal");
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = "flex";
            }
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const id = button.getAttribute("data-close-modal");
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = "none";
            }
        });
    });

    document.querySelectorAll(".modal-backdrop-lite").forEach(function (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    });
})();
</script>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace("/(<title>)(.*?)(<\\/title>)/i", '$1' . $title . '$3', $buffer);
echo $buffer;
?>
