<?php

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");

header('Content-Type: application/json');
http_response_code(200);

$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (!$adminConn) {
    echo json_encode([ 'ok'=>false ]);
    exit;
}

$eventlog = 0; $oghma = 0; $last = 0; $skyrim = '';
// Use fast estimates for counts where possible
$q1 = @pg_query_params($adminConn, "SELECT COALESCE(n_live_tup,0)::bigint AS est FROM pg_stat_all_tables WHERE schemaname=$1 AND relname='eventlog'", [$schema]);
if ($q1 && ($r = @pg_fetch_assoc($q1))) { $eventlog = (int)$r['est']; }
$rex = @pg_query_params($adminConn, "SELECT 1 FROM information_schema.tables WHERE table_schema=$1 AND table_name='oghma' LIMIT 1", [$schema]);
$hasO = ($rex && @pg_fetch_assoc($rex)) ? true : false;
if ($hasO) {
    $q2 = @pg_query_params($adminConn, "SELECT COALESCE(n_live_tup,0)::bigint AS est FROM pg_stat_all_tables WHERE schemaname=$1 AND relname='oghma'", [$schema]);
    if ($q2 && ($r2 = @pg_fetch_assoc($q2))) { $oghma = (int)$r2['est']; }
}
$q3 = @pg_query($adminConn, "SELECT MAX(gamets) AS mx FROM {$schema}.eventlog");
if ($q3 && ($r3 = @pg_fetch_assoc($q3)) && !is_null($r3['mx'])) { $last = (int)$r3['mx']; }
if ($last > 0) { $skyrim = convert_gamets2skyrim_long_date($last); }

echo json_encode([
    'ok' => true,
    'eventlog' => $eventlog,
    'oghma' => $oghma,
    'last_gamets' => $last,
    'last_skyrim_date' => $skyrim
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

?>


