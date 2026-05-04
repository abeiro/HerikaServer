<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;

$sampleConf = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php";
$defaultConf = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
$confLoader = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php";

if (file_exists($sampleConf)) {
    require_once $sampleConf;
}
if (file_exists($defaultConf)) {
    require_once $defaultConf;
}
if (file_exists($confLoader)) {
    require_once $confLoader;
}

if (empty($GLOBALS["DBDRIVER"])) {
    $GLOBALS["DBDRIVER"] = "postgresql";
}

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";

if (!isset($GLOBALS["db"])) {
    $GLOBALS["db"] = new sql();
}

function helperExit(array $payload, int $code = 0): void
{
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($code);
}

function helperIsAssocArray(array $value): bool
{
    if ($value === []) {
        return true;
    }

    return array_keys($value) !== range(0, count($value) - 1);
}

function helperNormalizeJsonObject($value): ?string
{
    if (is_array($value)) {
        if (!helperIsAssocArray($value)) {
            return null;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '' || $trimmed[0] !== '{') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded) || !helperIsAssocArray($decoded)) {
        return null;
    }

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$options = getopt("", [
    "mode:",
    "npc::",
    "limit::",
    "offset::",
]);

$mode = strtolower(trim((string)($options["mode"] ?? "")));
if ($mode === "") {
    helperExit(["ok" => false, "error" => "Missing --mode"], 1);
}

if ($mode === "list") {
    $limit = max(1, min(500, intval($options["limit"] ?? 100)));
    $offset = max(0, intval($options["offset"] ?? 0));
    $npcName = trim((string)($options["npc"] ?? ""));

    $where = [];
    if ($npcName !== "") {
        $where[] = "lower(t.npc_name) = lower('" . $GLOBALS["db"]->escape($npcName) . "')";
    }

    $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";
    $query = "
        SELECT
            t.npc_name,
            t.oghma_knowledge_tags,
            t.core,
            t.npc_static_bio,
            t.appearance,
            t.personality,
            t.relationships,
            t.occupation,
            t.skills,
            t.speechstyle,
            t.goals,
            t.voiceid,
            t.gender,
            t.race,
            t.refid,
            EXISTS(
                SELECT 1
                FROM public.bio_templates_custom c
                WHERE lower(c.npc_name) = lower(t.npc_name)
            ) AS custom_exists
        FROM public.combined_bio_templates t
        {$whereSql}
        ORDER BY lower(t.npc_name) ASC
        LIMIT {$limit} OFFSET {$offset}";

    $rows = $GLOBALS["db"]->fetchAll($query);
    helperExit([
        "ok" => true,
        "count" => count($rows),
        "rows" => $rows,
    ]);
}

if ($mode === "update") {
    $stdin = stream_get_contents(STDIN);
    $payload = json_decode((string)$stdin, true);
    if (!is_array($payload)) {
        helperExit(["ok" => false, "error" => "Expected JSON payload on stdin"], 1);
    }

    $npcName = trim((string)($payload["npc_name"] ?? ""));
    $relationships = helperNormalizeJsonObject($payload["relationships"] ?? null);
    if ($npcName === "") {
        helperExit(["ok" => false, "error" => "Missing npc_name"], 1);
    }
    if ($relationships === null) {
        helperExit(["ok" => false, "error" => "relationships must be a JSON object"], 1);
    }

    $GLOBALS["db"]->upsertRowOnConflict(
        "bio_templates_custom",
        [
            "npc_name" => $npcName,
            "relationships" => $relationships,
        ],
        "npc_name"
    );

    helperExit([
        "ok" => true,
        "npc_name" => $npcName,
        "relationships" => $relationships,
    ]);
}

helperExit(["ok" => false, "error" => "Unsupported mode '{$mode}'"], 1);
