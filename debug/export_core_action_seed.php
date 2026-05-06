<?php

$root = dirname(__DIR__);
require_once($root . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.php');

$dbDriver = trim(strval($DBDRIVER ?? 'postgresql'));
require_once($root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . $dbDriver . '.class.php');

function exportSeedSqlText($value)
{
    return "'" . str_replace("'", "''", strval($value)) . "'";
}

function exportSeedSqlBool($value)
{
    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }

    if ($value === null) {
        return 'FALSE';
    }

    if (is_int($value) || is_float($value)) {
        return intval($value) !== 0 ? 'TRUE' : 'FALSE';
    }

    $normalized = strtolower(trim(strval($value)));
    if ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'f' || $normalized === 'no' || $normalized === 'off') {
        return 'FALSE';
    }

    return 'TRUE';
}

function exportSeedNormalizeJson($value, $default)
{
    if ($value === null) {
        return $default;
    }

    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode(strval($value), true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $default;
}

function exportSeedSqlJson($value, $default, $allowNull = false)
{
    if ($value === null && $allowNull) {
        return 'NULL';
    }

    $normalized = exportSeedNormalizeJson($value, $default);
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return exportSeedSqlText($json) . '::jsonb';
}

$outputPath = $argv[1] ?? ($root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'core_action_seed.sql');
$db = new sql();
$rows = $db->fetchAll("
    SELECT
        code_name,
        action_name,
        description,
        return_message,
        available_to_npc,
        available_to_followers,
        available_to_narrator,
        is_activated,
        parameters_json,
        metadata,
        game_function,
        import_version,
        script_proxy_program
    FROM public.core_action
    ORDER BY code_name
");

$lines = [];
$lines[] = "-- Auto-generated from public.core_action";
$lines[] = "-- Regenerate with: php debug/export_core_action_seed.php";
$lines[] = "INSERT INTO public.core_action (";
$lines[] = "    code_name,";
$lines[] = "    action_name,";
$lines[] = "    description,";
$lines[] = "    return_message,";
$lines[] = "    available_to_npc,";
$lines[] = "    available_to_followers,";
$lines[] = "    available_to_narrator,";
$lines[] = "    is_activated,";
$lines[] = "    parameters_json,";
$lines[] = "    metadata,";
$lines[] = "    game_function,";
$lines[] = "    import_version,";
$lines[] = "    script_proxy_program";
$lines[] = ") VALUES";

$valueLines = [];
foreach ($rows as $row) {
    $valueLines[] = "    ("
        . exportSeedSqlText($row['code_name'] ?? '') . ", "
        . exportSeedSqlText($row['action_name'] ?? '') . ", "
        . exportSeedSqlText($row['description'] ?? '') . ", "
        . exportSeedSqlText($row['return_message'] ?? '') . ", "
        . exportSeedSqlBool($row['available_to_npc'] ?? false) . ", "
        . exportSeedSqlBool($row['available_to_followers'] ?? false) . ", "
        . exportSeedSqlBool($row['available_to_narrator'] ?? false) . ", "
        . exportSeedSqlBool($row['is_activated'] ?? false) . ", "
        . exportSeedSqlJson($row['parameters_json'] ?? null, []) . ", "
        . exportSeedSqlJson($row['metadata'] ?? null, []) . ", "
        . exportSeedSqlBool($row['game_function'] ?? true) . ", "
        . intval($row['import_version'] ?? 0) . ", "
        . exportSeedSqlJson($row['script_proxy_program'] ?? null, [], true)
        . ")";
}

$lines[] = implode(",\n", $valueLines);
$lines[] = "ON CONFLICT (code_name) DO UPDATE SET";
$lines[] = "    action_name = EXCLUDED.action_name,";
$lines[] = "    description = EXCLUDED.description,";
$lines[] = "    return_message = EXCLUDED.return_message,";
$lines[] = "    available_to_npc = EXCLUDED.available_to_npc,";
$lines[] = "    available_to_followers = EXCLUDED.available_to_followers,";
$lines[] = "    available_to_narrator = EXCLUDED.available_to_narrator,";
$lines[] = "    is_activated = EXCLUDED.is_activated,";
$lines[] = "    parameters_json = EXCLUDED.parameters_json,";
$lines[] = "    metadata = EXCLUDED.metadata,";
$lines[] = "    game_function = EXCLUDED.game_function,";
$lines[] = "    import_version = EXCLUDED.import_version,";
$lines[] = "    script_proxy_program = EXCLUDED.script_proxy_program,";
$lines[] = "    updated_at = NOW();";
$lines[] = "";

file_put_contents($outputPath, implode(PHP_EOL, $lines));
echo "Wrote {$outputPath} (" . count($rows) . " rows)" . PHP_EOL;
