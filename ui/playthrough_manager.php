<?php

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "playthrough_storage.php");

// Determine web root (match other core pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$TITLE = "🎮 CHIM - Playthrough Manager";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">

<?php
// Embed mode and navbar (match Oghma style)
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');
$debugPaneLink = false;
if (!$isEmbed) {
    include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
}

// DB connection details (aligned with import_db.php)
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Ensure meta schema and tables exist (use a direct connection for admin tasks)
$adminConn = pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (!$adminConn) {
    echo '<div class="message"><p><strong>Error:</strong> Failed to connect to database: '.htmlspecialchars(pg_last_error()).'</p></div>';
} else {
    $initSQL = [];
    $initSQL[] = "CREATE SCHEMA IF NOT EXISTS chim_meta";
    $initSQL[] = "CREATE TABLE IF NOT EXISTS chim_meta.playthrough_profiles (\n        id SERIAL PRIMARY KEY,\n        name TEXT NOT NULL UNIQUE,\n        created_at TIMESTAMP NOT NULL DEFAULT NOW(),\n        size_bytes BIGINT NOT NULL,\n        storage_format TEXT NOT NULL DEFAULT 'plain_sql',\n        notes TEXT,\n        is_active BOOLEAN NOT NULL DEFAULT FALSE\n    )";
    $initSQL[] = "CREATE TABLE IF NOT EXISTS chim_meta.playthrough_blobs (\n        profile_id INT PRIMARY KEY REFERENCES chim_meta.playthrough_profiles(id) ON DELETE CASCADE,\n        dump_data TEXT\n    )";
    // Metadata columns (idempotent)
    $initSQL[] = "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS player_name TEXT";
    $initSQL[] = "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS game TEXT";
    $initSQL[] = "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS eventlog_count BIGINT";
    $initSQL[] = "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS oghma_count BIGINT";
    $initSQL[] = "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS last_gamets BIGINT";
    foreach ($initSQL as $qs) {
        @pg_query($adminConn, $qs);
    }
    // If an older deployment created dump_data as BYTEA, migrate it to TEXT (plain SQL dumps)
    $colTypeRes = @pg_query($adminConn, "SELECT data_type FROM information_schema.columns WHERE table_schema='chim_meta' AND table_name='playthrough_blobs' AND column_name='dump_data'");
    if ($colTypeRes) {
        $ct = pg_fetch_assoc($colTypeRes);
        if ($ct && isset($ct['data_type']) && strtolower($ct['data_type']) === 'bytea') {
            @pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_blobs ALTER COLUMN dump_data TYPE TEXT USING convert_from(dump_data,'UTF8')");
        }
    }
    // Auto-capture current DB as 'default' profile if none exists yet (GET-only)
    $needsDefault = false;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cntRes = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM chim_meta.playthrough_profiles");
        if ($cntRes && ($c = pg_fetch_assoc($cntRes)) && intval($c['c']) === 0) { $needsDefault = true; }
        // Guard against partial states: if a row named 'default' exists, skip
        $existsRes = @pg_query_params($adminConn, "SELECT 1 FROM chim_meta.playthrough_profiles WHERE name=$1 LIMIT 1", ['default']);
        if ($existsRes && pg_fetch_assoc($existsRes)) { $needsDefault = false; }
    }
    if ($needsDefault) {
        $playerName = (string)($GLOBALS['PLAYER_NAME'] ?? 'Unknown');
        $gameName = 'Skyrim';
        $eventlogCount = 0; $oghmaCount = 0; $lastGamets = 0;
        $r1 = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM {$schema}.eventlog");
        if ($r1 && ($rr = pg_fetch_assoc($r1))) { $eventlogCount = intval($rr['c']); }
        $rex = @pg_query_params($adminConn, "SELECT 1 FROM information_schema.tables WHERE table_schema=$1 AND table_name='oghma' LIMIT 1", [$schema]);
        $hasOghma = ($rex && pg_fetch_assoc($rex)) ? true : false;
        if ($hasOghma) {
            $r2 = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM {$schema}.oghma");
            if ($r2 && ($rr = pg_fetch_assoc($r2))) { $oghmaCount = intval($rr['c']); }
        }
        $r3 = @pg_query($adminConn, "SELECT MAX(gamets) AS mx FROM {$schema}.eventlog");
        if ($r3 && ($rr = pg_fetch_assoc($r3)) && !is_null($rr['mx'])) { $lastGamets = intval($rr['mx']); }

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('playthrough_default_'.time().'_'.mt_rand(1000,9999).'.sql');
        $cmd = "PGPASSWORD=".escapeshellarg($password)." pg_dump -h ".escapeshellarg($host)." -p ".escapeshellarg($port)." -U ".escapeshellarg($username)." -d ".escapeshellarg($dbname)." -N chim_meta > ".escapeshellarg($tmpFile)." 2>&1";
        $output = shell_exec($cmd);
        if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
            $resCap = ptm_create_profile_with_lob(
                $adminConn,
                'default',
                'Auto-captured default profile',
                true,
                $playerName,
                $gameName,
                (int)$eventlogCount,
                (int)$oghmaCount,
                (int)$lastGamets,
                $tmpFile
            );
        }
        @unlink($tmpFile);
    }
}

$db = new sql();
$message = '';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024; $sizes = ['Bytes','KB','MB','GB','TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($name === '') {
            $message .= '<p><strong>Error:</strong> Name is required.</p>';
        } else if (!$adminConn) {
            $message .= '<p><strong>Error:</strong> DB connection not available.</p>';
        } else {
            // Create a plain SQL dump excluding the meta schema
            $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('playthrough_dump_'.time().'_'.mt_rand(1000,9999).'.sql');
            $cmd = "PGPASSWORD=".escapeshellarg($password)." pg_dump -h ".escapeshellarg($host)." -p ".escapeshellarg($port)." -U ".escapeshellarg($username)." -d ".escapeshellarg($dbname)." -N chim_meta > ".escapeshellarg($tmpFile)." 2>&1";
            $output = shell_exec($cmd);

            if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
                $preview = $output ? '<pre>'.h(substr($output,0,2000)).'</pre>' : '';
                $message .= '<p><strong>Error:</strong> Failed to create database snapshot.</p>'.$preview;
            } else {
                // Collect metadata
                $playerName = (string)($GLOBALS['PLAYER_NAME'] ?? 'Unknown');
                $gameName = 'Skyrim';
                $eventlogCount = 0; $oghmaCount = 0; $lastGamets = 0;
                $r1 = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM {$schema}.eventlog");
                if ($r1 && ($rr = pg_fetch_assoc($r1))) { $eventlogCount = intval($rr['c']); }
                $rex = @pg_query_params($adminConn, "SELECT 1 FROM information_schema.tables WHERE table_schema=$1 AND table_name='oghma' LIMIT 1", [$schema]);
                $hasOghma = ($rex && pg_fetch_assoc($rex)) ? true : false;
                if ($hasOghma) {
                    $r2 = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM {$schema}.oghma");
                    if ($r2 && ($rr = pg_fetch_assoc($r2))) { $oghmaCount = intval($rr['c']); }
                }
                $r3 = @pg_query($adminConn, "SELECT MAX(gamets) AS mx FROM {$schema}.eventlog");
                if ($r3 && ($rr = pg_fetch_assoc($r3)) && !is_null($rr['mx'])) { $lastGamets = intval($rr['mx']); }

                $resCap = ptm_create_profile_with_lob(
                    $adminConn,
                    $name,
                    $notes,
                    false,
                    $playerName,
                    $gameName,
                    (int)$eventlogCount,
                    (int)$oghmaCount,
                    (int)$lastGamets,
                    $tmpFile
                );
                if ($resCap['success']) {
                    $message .= '<p><strong>✅ Snapshot created:</strong> '.h($name).' ('.h(formatFileSize((int)$resCap['size'])).')</p>';
                } else {
                    $message .= '<p><strong>Error:</strong> Failed to create snapshot.</p>';
                    $message .= '<pre>'.h($resCap['error']).'</pre>';
                }
                @unlink($tmpFile);
            }
        }
    }

    if ($action === 'switch') {
        $profileId = intval($_POST['profile_id'] ?? 0);
        if ($profileId <= 0) {
            $message .= '<p><strong>Error:</strong> Invalid profile selected.</p>';
        } else if (!$adminConn) {
            $message .= '<p><strong>Error:</strong> DB connection not available.</p>';
        } else {
            // 1) Auto-snapshot current active profile BEFORE switching, to avoid data loss
            $curRes = pg_query($adminConn, "SELECT id, name FROM chim_meta.playthrough_profiles WHERE is_active = true LIMIT 1");
            $curRow = $curRes ? pg_fetch_assoc($curRes) : null;
            if ($curRow) {
                $curProfileId = intval($curRow['id']);
                $tmpSnap = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('playthrough_autosave_'.time().'_'.mt_rand(1000,9999).'.sql');
                $dumpCmd = "PGPASSWORD=".escapeshellarg($password)." pg_dump -h ".escapeshellarg($host)." -p ".escapeshellarg($port)." -U ".escapeshellarg($username)." -d ".escapeshellarg($dbname)." -N chim_meta > ".escapeshellarg($tmpSnap)." 2>&1";
                $dumpOut = shell_exec($dumpCmd);
                if (!file_exists($tmpSnap) || filesize($tmpSnap) === 0) {
                    $preview = $dumpOut ? '<pre>'.h(substr($dumpOut,0,2000)).'</pre>' : '';
                    $message .= '<p><strong>Error:</strong> Failed to snapshot current database before switching. Aborting to avoid data loss.</p>'.$preview;
                    // Do not proceed with switch
                } else {
                    // Gather live metadata
                    $playerName = (string)($GLOBALS['PLAYER_NAME'] ?? 'Unknown');
                    $gameName = 'Skyrim';
                    $eventlogCount = 0; $oghmaCount = 0; $lastGamets = 0;
                    $r1 = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM {$schema}.eventlog");
                    if ($r1 && ($rr = pg_fetch_assoc($r1))) { $eventlogCount = intval($rr['c']); }
                    $rex = @pg_query_params($adminConn, "SELECT 1 FROM information_schema.tables WHERE table_schema=$1 AND table_name='oghma' LIMIT 1", [$schema]);
                    $hasOghma = ($rex && pg_fetch_assoc($rex)) ? true : false;
                    if ($hasOghma) {
                        $r2 = @pg_query($adminConn, "SELECT COUNT(*) AS c FROM {$schema}.oghma");
                        if ($r2 && ($rr = pg_fetch_assoc($r2))) { $oghmaCount = intval($rr['c']); }
                    }
                    $r3 = @pg_query($adminConn, "SELECT MAX(gamets) AS mx FROM {$schema}.eventlog");
                    if ($r3 && ($rr = pg_fetch_assoc($r3)) && !is_null($rr['mx'])) { $lastGamets = intval($rr['mx']); }

                    $upd = ptm_update_profile_blob_from_file(
                        $adminConn,
                        $curProfileId,
                        $tmpSnap,
                        $playerName,
                        $gameName,
                        (int)$eventlogCount,
                        (int)$oghmaCount,
                        (int)$lastGamets
                    );
                    if (!$upd['success']) {
                        $message .= '<p><strong>Error:</strong> Failed to save current snapshot before switching. Aborting.</p>';
                        @unlink($tmpSnap);
                        // Do not proceed with switch
                        goto SWITCH_ABORT;
                    }
                }
                @unlink($tmpSnap);
            }

            // Fetch snapshot to file (LOB or TEXT)
            $resName = pg_query_params($adminConn, 'SELECT name FROM chim_meta.playthrough_profiles WHERE id=$1', [$profileId]);
            $rowName = $resName ? pg_fetch_assoc($resName) : null;
            if (!$rowName) {
                $message .= '<p><strong>Error:</strong> Snapshot not found.</p>';
            } else {
                $name = $rowName['name'];
                $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('playthrough_restore_'.time().'_'.mt_rand(1000,9999).'.sql');
                $ff = ptm_fetch_snapshot_to_file($adminConn, $profileId, $tmpFile);
                if (!$ff['success']) {
                    $message .= '<p><strong>Error:</strong> Failed to materialize snapshot file.</p><pre>'.h($ff['error']).'</pre>';
                } else {
                    // Drop and recreate public schema and required extensions
                    $Q = array();
                    $Q[] = "DROP SCHEMA IF EXISTS {$schema} CASCADE";
                    $Q[] = "DROP EXTENSION IF EXISTS vector CASCADE";
                    $Q[] = "DROP EXTENSION IF EXISTS pg_trgm CASCADE";
                    $Q[] = "CREATE SCHEMA {$schema}";
                    $Q[] = "CREATE EXTENSION IF NOT EXISTS vector";
                    $Q[] = "CREATE EXTENSION IF NOT EXISTS pg_trgm";

                    $errorOccurred = false;
                    foreach ($Q as $QS) {
                        $r = pg_query($adminConn, $QS);
                        if (!$r) {
                            $message .= '<p>Error executing query: '.h(pg_last_error($adminConn)).'</p>';
                            $errorOccurred = true;
                            break;
                        }
                    }

                    if (!$errorOccurred) {
                        // Restore using psql
                        $psqlCommand = "PGPASSWORD=".escapeshellarg($password)." psql -h ".escapeshellarg($host)." -p ".escapeshellarg($port)." -U ".escapeshellarg($username)." -d ".escapeshellarg($dbname)." -f ".escapeshellarg($tmpFile);
                        $output = [];
                        $returnVar = 0;
                        exec($psqlCommand, $output, $returnVar);
                        if ($returnVar !== 0) {
                            $message .= '<p><strong>Error:</strong> Failed to restore snapshot.</p><pre>'.h(implode("\n", $output)).'</pre>';
                        } else {
                            // Mark active flag
                            pg_query($adminConn, 'BEGIN');
                            pg_query($adminConn, 'UPDATE chim_meta.playthrough_profiles SET is_active = false');
                            $resU = pg_query_params($adminConn, 'UPDATE chim_meta.playthrough_profiles SET is_active = true WHERE id=$1', [$profileId]);
                            if ($resU) {
                                pg_query($adminConn, 'COMMIT');
                                $message .= '<p><strong>✅ Switched to profile:</strong> '.h($name).'</p>';
                            } else {
                                pg_query($adminConn, 'ROLLBACK');
                                $message .= '<p><strong>Warning:</strong> Database restored but failed to mark profile active.</p>';
                            }
                        }
                    }
                }
                @unlink($tmpFile);
            }
        }
        SWITCH_ABORT:;
    }

    if ($action === 'delete') {
        $profileId = intval($_POST['profile_id'] ?? 0);
        if ($profileId <= 0) {
            $message .= '<p><strong>Error:</strong> Invalid profile selected.</p>';
        } else if (!$adminConn) {
            $message .= '<p><strong>Error:</strong> DB connection not available.</p>';
        } else {
            $row = $db->fetchOne("SELECT is_active, name FROM chim_meta.playthrough_profiles WHERE id = ".$profileId);
            if (!$row) {
                $message .= '<p><strong>Error:</strong> Profile not found.</p>';
            } else if ((int)$row['is_active'] === 1) {
                $message .= '<p><strong>Error:</strong> Cannot delete the active profile.</p>';
            } else if (strtolower((string)$row['name']) === 'default') {
                $message .= '<p><strong>Error:</strong> Cannot delete the default profile.</p>';
            } else {
                $ok = pg_query_params($adminConn, 'DELETE FROM chim_meta.playthrough_profiles WHERE id=$1', [$profileId]);
                if ($ok) {
                    $message .= '<p><strong>✅ Deleted:</strong> '.h($row['name']).'</p>';
                } else {
                    $message .= '<p><strong>Error:</strong> Failed to delete profile.</p>';
                }
            }
        }
    }
}

// Fetch profiles
$profiles = $db->fetchAll("SELECT id, name, created_at, size_bytes, storage_format, notes, is_active, player_name, game, eventlog_count, oghma_count, last_gamets FROM chim_meta.playthrough_profiles ORDER BY COALESCE(last_gamets,0) DESC, created_at DESC");

// Live stats for currently loaded (active) database; do not rely on metadata
$activeProfileName = '';
if ($adminConn) {
    $apr = @pg_query($adminConn, "SELECT name FROM chim_meta.playthrough_profiles WHERE is_active = true LIMIT 1");
    if ($apr && ($ar = pg_fetch_assoc($apr))) { $activeProfileName = (string)$ar['name']; }
}
$livePlayerName = (string)($GLOBALS['PLAYER_NAME'] ?? 'Unknown');
$liveGameName = 'Skyrim';
// Use metadata for initial render (fast), refresh via AJAX later
$liveEventlogCount = 0; $liveOghmaCount = 0; $liveLastGamets = 0;
foreach ($profiles as $p) {
    $nameStr = (string)($p['name'] ?? '');
    $isActive = ((int)($p['is_active'] ?? 0) === 1) || (strcasecmp($nameStr, (string)$activeProfileName) === 0);
    if ($isActive) {
        $liveEventlogCount = intval($p['eventlog_count'] ?? 0);
        $liveOghmaCount = intval($p['oghma_count'] ?? 0);
        $liveLastGamets = intval($p['last_gamets'] ?? 0);
        break;
    }
}
$liveSkyrimDate = ($liveLastGamets > 0) ? convert_gamets2skyrim_long_date($liveLastGamets) : '';

// Prepare timeline items based on last_gamets
$timelineItems = [];
foreach ($profiles as $p) {
    $nameStr = (string)($p['name'] ?? '');
    $isActive = ((int)($p['is_active'] ?? 0) === 1) || (strcasecmp($nameStr, (string)$activeProfileName) === 0);
    $lgMeta = isset($p['last_gamets']) ? intval($p['last_gamets']) : 0;
    $lg = $lgMeta;
    if ($lg <= 0 && $isActive && $liveLastGamets > 0) { $lg = $liveLastGamets; }
    if ($lg <= 0) { continue; }
    $timelineItems[] = [
        'id' => (int)$p['id'],
        'name' => $nameStr,
        'last_gamets' => $lg,
        'skyrim_date' => convert_gamets2skyrim_long_date($lg),
        'created_at' => (string)$p['created_at'],
        'size' => formatFileSize((int)$p['size_bytes']),
        'is_active' => $isActive
    ];
}

// Timeline ticks (static notches with labels)
$timelineTicks = [];
if (!empty($timelineItems)) {
    $values = array_map(function($i){ return (int)$i['last_gamets']; }, $timelineItems);
    $minGamets = min($values);
    $maxGamets = max($values);
    $segments = min(max(count($timelineItems) - 1, 4), 12); // 4..12 ticks based on data
    if ($maxGamets === $minGamets) {
        // Degenerate: place a center tick
        $timelineTicks[] = [
            'gamets' => $minGamets,
            'date' => convert_gamets2skyrim_long_date($minGamets)
        ];
    } else {
        for ($s = 0; $s <= $segments; $s++) {
            $g = (int)round($minGamets + ($s * ($maxGamets - $minGamets) / $segments));
            $timelineTicks[] = [
                'gamets' => $g,
                'date' => convert_gamets2skyrim_long_date($g)
            ];
        }
    }
}

?>

<style>
    main { padding-top: 80px; padding-bottom: 40px; padding-left: 10%; padding-right: 10%; width: 100%; margin: 0; }
    footer { position: fixed; bottom: 0; width: 100%; height: 20px; background: #031633; z-index: 100; }
    .page-header { text-align: center; margin-bottom: 30px; padding: 20px; background: #2a2a2a; border-radius: 8px; border: 1px solid #4a4a4a; }
    .page-header h1 { margin-bottom: 10px; font-family: 'MagicCards', serif; word-spacing: 8px; font-size: 2.0em; color: rgb(242, 124, 17); text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
    .content-section { background: #2a2a2a; padding: 25px; border-radius: 8px; border: 1px solid #4a4a4a; }
    .content-section h2 { font-family: 'MagicCards', serif; color: rgb(242, 124, 17); text-shadow: 1px 1px 2px rgba(0,0,0,0.5); word-spacing: 6px; margin-bottom: 15px; font-size: 1.4em; }
    .full-width-section { grid-column: 1 / -1; }
    .button-group { display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap; }
    @font-face { font-family: 'MagicCards'; src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype'); font-weight: normal; font-style: normal; }
    @media (max-width: 768px) { main { padding-left: 5%; padding-right: 5%; } .content-grid { grid-template-columns: 1fr; } .content-section { padding: 15px; } }
    /* Timeline */
    .timeline { position: relative; padding: 28px 8px 30px 8px; }
    .timeline-title { text-align:center; color:#e0e0e0; font-size: 13px; margin-bottom: 12px; }
    .timeline-track { position: relative; height: 4px; background: linear-gradient(90deg, rgba(138,155,182,0.5), rgba(242,124,17,0.6)); border-radius: 2px; }
    .timeline-nodes { position: relative; height: 0; }
    .timeline-node { position: absolute; top: -8px; width: 16px; height: 16px; border-radius: 50%; background: #ffb862; border: 2px solid #1a1a1a; box-shadow: 0 0 0 2px rgba(255,255,255,0.08); transform: translateX(-50%); cursor: pointer; }
    .timeline-node.active { background: #2ea8ff; box-shadow: 0 0 0 2px rgba(46,168,255,0.25), 0 0 12px rgba(46,168,255,0.35); }
    .timeline-tooltip { position: absolute; display: none; max-width: 280px; background: #111; border: 1px solid rgba(138,155,182,0.4); color: #e0e0e0; padding: 8px 10px; border-radius: 6px; font-size: 12px; z-index: 20; pointer-events: none; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
    .timeline-tooltip .name { color: #ffb862; font-weight: bold; }
    .timeline-legend { display:flex; justify-content:space-between; font-size: 12px; color:#9fb1c9; margin-top: 8px; }
    .timeline-notches { position: relative; height: 0; }
    .timeline-notch { position: absolute; top: -12px; width: 2px; height: 10px; background: #9fb1c9; opacity: 0.7; transform: translateX(-50%); }
    .timeline-notch.major { height: 14px; background:#e0e0e0; opacity: 0.9; }
    .timeline-tick-label { position: absolute; top: -30px; transform: translateX(-50%); color:#9fb1c9; font-size: 11px; white-space: nowrap; pointer-events: none; }
    .timeline-label { position: absolute; top: -28px; transform: translateX(-50%); color:#9fb1c9; font-size: 11px; white-space: nowrap; pointer-events: none; }
    .timeline-label.active { color:#eaee05; }
    /* Dragon Break styling */
    .backup-item.dragonbreak { background-color: #1e2a3a; }
    .backup-item.dragonbreak:hover { background-color: #223044; }
    /* Switch overlay */
    #switch-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 2000; display: none; align-items: center; justify-content: center; }
    #switch-overlay .loading-modal { background:#111; border:1px solid #444; border-radius:8px; padding:20px 24px; width: 340px; color:#e0e0e0; text-align:center; box-shadow: 0 12px 36px rgba(0,0,0,0.5); }
    #switch-overlay .loading-title { font-family: 'MagicCards', serif; color: rgb(242, 124, 17); margin-bottom: 8px; font-size: 1.2em; }
    #switch-overlay .loading-sub { font-size: 12px; color:#9fb1c9; margin-top: 6px; }
    .lds-ring { display:inline-block; position:relative; width:64px; height:64px; margin: 6px 0 2px 0; }
    .lds-ring div { box-sizing:border-box; display:block; position:absolute; width:51px; height:51px; margin:6px; border:6px solid #ffb862; border-radius:50%; animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite; border-color:#ffb862 transparent transparent transparent; }
    .lds-ring div:nth-child(1) { animation-delay:-0.45s; }
    .lds-ring div:nth-child(2) { animation-delay:-0.3s; }
    .lds-ring div:nth-child(3) { animation-delay:-0.15s; }
    @keyframes lds-ring { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }
</style>

<?php if ($isEmbed): ?>
<style> main { padding-top: 20px; } </style>
<?php endif; ?>

<main>
    <div id="toast" class="toast-notification"><span class="message"></span></div>
    <div id="switch-overlay" role="alert" aria-live="assertive" aria-modal="true">
        <div class="loading-modal">
            <div class="loading-title" id="loading-title">Restoring Snapshot…</div>
            <div class="lds-ring"><div></div><div></div><div></div><div></div></div>
            <div class="loading-sub">This can take a few minutes. Please keep this tab open.</div>
        </div>
    </div>

    <div class="page-header">
        <h1>Playthrough Manager (Beta)</h1>
        <div style="font-size: 0.95em; color: #ccc;">Create, switch, and manage full database playthrough snapshots.</div>
        <div style="font-size: 0.95em; color: #ccc;">Dragon Breaks (snapshots) are created automatically when you load a save 3 ingame days behind your previous save.</div>
        <div style="font-size: 0.95em; color: #ccc;">When loading a Dragon Break it is recommend to create a new snapshot from it and use that before you load back into your previous save.</div>

    </div>

    <div class="content-section full-width-section">
        <h2>🟢 Current Database Profile (Live)</h2>
        <div style="display:flex; gap:12px; flex-wrap:wrap; font-size: 14px; color:#ccc;">
                <div><strong style="color:#f8f9fa;">Active profile:</strong> <span id="live-active"><?php echo h($activeProfileName !== '' ? $activeProfileName : '(untracked)'); ?></span></div>
                <div><strong style="color:#f8f9fa;">Player:</strong> <span id="live-player"><?php echo h($livePlayerName); ?></span></div>
                <div><strong style="color:#f8f9fa;">Game:</strong> <span id="live-game"><?php echo h($liveGameName); ?></span></div>
                <div><strong style="color:#f8f9fa;">eventlog:</strong> <span id="live-eventlog"><?php echo intval($liveEventlogCount); ?></span></div>
                <div><strong style="color:#f8f9fa;">oghma:</strong> <span id="live-oghma"><?php echo intval($liveOghmaCount); ?></span></div>
                <div><strong style="color:#f8f9fa;">last in-game:</strong> <span id="live-last"><?php echo h($liveSkyrimDate !== '' ? $liveSkyrimDate : 'n/a'); ?></span></div>
        </div>
        <?php if (!empty($timelineItems)) { ?>
        <div class="timeline" id="pt-timeline">
            <div class="timeline-title" id="pt-title"></div>
            <div class="timeline-track"></div>
            <div class="timeline-notches" id="pt-timeline-notches"></div>
            <div class="timeline-nodes" id="pt-timeline-nodes"></div>
            <div class="timeline-legend"><span id="pt-min"></span><span id="pt-max"></span></div>
            <div class="timeline-tooltip" id="pt-tooltip"></div>
        </div>
        <?php } ?>
    </div>

    <?php if (!empty($message)) { echo '<div class="content-section">'.$message.'</div>'; } ?>

    <div class="content-grid">
        <div class="content-section">
            <h2>📦 Create Snapshot From Current</h2>
                <form method="post" class="create-form">
                    <input type="hidden" name="action" value="create">
                    <label for="name">Profile name</label><br>
                    <input type="text" id="name" name="name" required style="width: 100%; margin: 6px 0;">
                    <label for="notes">Notes (optional)</label><br>
                    <input type="text" id="notes" name="notes" style="width: 100%; margin: 6px 0;">
                    <div class="button-group">
                        <button type="submit" class="button" style="background-color: rgb(1 53 166 / 90%); color: #fff;">Create Snapshot</button>
                    </div>
                </form>
        </div>

        <div class="content-section">
            <h2>🎮 Saved Playthroughs</h2>
                <?php if (empty($profiles)) { ?>
                    <div style="text-align:center; color:#ccc; padding: 12px;">No profiles yet. Create one from the left panel.</div>
                <?php } else { ?>
                    <div class="backup-list" style="max-height: 420px; overflow-y:auto; padding: 0; margin: 0; border: 1px solid #333333; border-radius: 8px; background-color: #1a1a1a;">
                        <?php foreach ($profiles as $p) { 
                            $nm = strtolower((string)($p['name'] ?? ''));
                            $nt = strtolower((string)($p['notes'] ?? ''));
                            $isDragon = (strpos($nm,'dragon') !== false) || (strpos($nt,'dragon') !== false);
                        ?>
                        <div class="backup-item<?php echo $isDragon ? ' dragonbreak' : ''; ?>" style="padding: 12px; border-bottom: 1px solid #333333;">
                            <div style="display:flex; justify-content:space-between; gap: 10px;">
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:bold; font-size: 14px; word-break: break-all;">
                                        <?php echo h($p['name']); ?>
                                        <?php 
                                            $lg = isset($p['last_gamets']) ? intval($p['last_gamets']) : 0; 
                                            if ($liveLastGamets > 0 && $lg > 0) {
                                                if ($lg === $liveLastGamets) {
                                                    echo '<span style="color:#9fb1c9; margin-left:6px;">| Current Time</span>';
                                                } elseif ($lg < $liveLastGamets) {
                                                    $d = gamets2days_between($lg, $liveLastGamets);
                                                    $txt = $d.' '.($d===1?'day':'days').' behind';
                                                    echo '<span style="color:#dc2626; margin-left:6px;">| '.h($txt).'</span>';
                                                } else {
                                                    $d = gamets2days_between($liveLastGamets, $lg);
                                                    $txt = $d.' '.($d===1?'day':'days').' ahead';
                                                    echo '<span style="color:#16a34a; margin-left:6px;">| '.h($txt).'</span>'; #16a34a
                                                }
                                            }
                                        ?>
                                        <?php if ((int)$p['is_active'] === 1) { echo '<span style="color:#eaee05; font-weight:normal;"> (active)</span>'; } ?>
                                    </div>
                                    <div style="font-size: 12px; color:#ccc; display:flex; gap:10px; flex-wrap:wrap;">
                                        <span><?php echo h($p['created_at']); ?></span>
                                        <span>• <?php echo h(formatFileSize((int)$p['size_bytes'])); ?></span>
                                        <span>• Player: <?php echo h($p['player_name'] ?? ''); ?></span>
                                        <span>• Game: <?php echo h($p['game'] ?? 'Skyrim'); ?></span>
                                        <span>• eventlog: <?php echo intval($p['eventlog_count'] ?? 0); ?></span>
                                        <span>• oghma: <?php echo intval($p['oghma_count'] ?? 0); ?></span>
                                        <?php 
                                            $lg = isset($p['last_gamets']) ? intval($p['last_gamets']) : 0; 
                                            $skDate = $lg > 0 ? convert_gamets2skyrim_long_date($lg) : '';
                                        ?>
                                        <span>• last in-game: <?php echo h($skDate); ?></span>
                                    </div>
                                    <?php if (!empty($p['notes'])) { ?>
                                        <div style="font-size: 12px; color:#9fb1c9; margin-top: 4px; word-break: break-all;"><?php echo h($p['notes']); ?></div>
                                    <?php } ?>
                                </div>
                                <div style="display:flex; gap:6px; align-items:flex-start;">
                                    <form method="post" class="switch-form">
                                        <input type="hidden" name="action" value="switch">
                                        <input type="hidden" name="profile_id" value="<?php echo (int)$p['id']; ?>">
                                        <button type="submit" class="button" style="background-color: rgb(1 53 166 / 90%); color:#fff; padding:6px 10px;">Switch</button>
                                    </form>
                                    <?php $isDefault = (strtolower((string)$p['name']) === 'default'); ?>
                                    <?php if (!$isDefault) { ?>
                                    <form method="post" onsubmit="return confirm('Delete this snapshot? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="profile_id" value="<?php echo (int)$p['id']; ?>">
                                        <button type="submit" class="button" style="background-color: rgba(166, 53, 63, 0.9); color:#fff; padding:6px 10px;" <?php echo ((int)$p['is_active']===1? 'disabled':''); ?>>Delete</button>
                                    </form>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                <?php } ?>
        </div>
    </div>
</main>

<?php
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>

<script>
(function(){
    const overlay = document.getElementById('switch-overlay');
    const overlayTitle = document.getElementById('loading-title');
    function showOverlay(){ if (overlay) overlay.style.display='flex'; }
    function hideOverlay(){ if (overlay) overlay.style.display='none'; }
    // Intercept switch submit to show confirm + overlay
    document.addEventListener('submit', function(e){
        const form = e.target;
        if (form && form.classList && form.classList.contains('switch-form')) {
            const ok = window.confirm('This will replace the current database with this snapshot. Continue?');
            if (!ok) { e.preventDefault(); return false; }
            if (overlayTitle) overlayTitle.textContent = 'Restoring Snapshot…';
            showOverlay();
        }
        if (form && form.classList && form.classList.contains('create-form')) {
            if (overlayTitle) overlayTitle.textContent = 'Creating Snapshot…';
            showOverlay();
        }
    }, true);

    const items = <?php echo json_encode($timelineItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const ticks = <?php echo json_encode($timelineTicks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (!items || !items.length) return;
    const nodesEl = document.getElementById('pt-timeline-nodes');
    const notchesEl = document.getElementById('pt-timeline-notches');
    const trackEl = document.querySelector('#pt-timeline .timeline-track');
    const tooltip = document.getElementById('pt-tooltip');
    const minEl = document.getElementById('pt-min');
    const maxEl = document.getElementById('pt-max');
    const titleEl = document.getElementById('pt-title');
    if (!nodesEl || !trackEl) return;

    const values = items.map(i => i.last_gamets);
    const min = Math.min.apply(null, values);
    const max = Math.max.apply(null, values);
    const minItem = items.find(i => i.last_gamets === min);
    const maxItem = items.find(i => i.last_gamets === max);
    const minLabel = minItem ? minItem.skyrim_date : String(min);
    const maxLabel = maxItem ? maxItem.skyrim_date : String(max);
    minEl && (minEl.textContent = 'Earliest: ' + minLabel);
    maxEl && (maxEl.textContent = 'Latest: ' + maxLabel);

    function pct(x){
        if (max === min) return 50; // collapse to center if identical
        return ((x - min) / (max - min)) * 100;
    }

    function showTip(e, html){
        if (!tooltip) return;
        tooltip.innerHTML = html;
        tooltip.style.display = 'block';
        const rect = nodesEl.getBoundingClientRect();
        const x = e.clientX - rect.left + 10;
        const y = e.clientY - rect.top + 14;
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }
    function hideTip(){ if (tooltip) tooltip.style.display = 'none'; }

    items.sort((a,b) => a.last_gamets - b.last_gamets);
    items.forEach(it => {
        const node = document.createElement('div');
        node.className = 'timeline-node' + (it.is_active ? ' active' : '');
        node.style.left = pct(it.last_gamets) + '%';
        node.setAttribute('role','button');
        node.setAttribute('tabindex','0');
        const tip = `<div class="name">${escapeHtml(it.name)}</div>
            <div>Skyrim date: ${escapeHtml(it.skyrim_date)}</div>
            <div>Created: ${escapeHtml(it.created_at)}</div>
            <div>Size: ${escapeHtml(it.size)}</div>`;
        node.addEventListener('mouseenter', (e)=>showTip(e, tip));
        node.addEventListener('mousemove', (e)=>showTip(e, tip));
        node.addEventListener('mouseleave', hideTip);
        nodesEl.appendChild(node);
    });

    // Position "You are here" marker at the active profile's position
    // Blue active node styling remains; no arrow/label needed

    // Static ticks (major/minor) with labels aligned to gamets scale
    if (notchesEl && ticks && ticks.length) {
        const values = items.map(i => i.last_gamets);
        const min = Math.min.apply(null, values);
        const max = Math.max.apply(null, values);
        const isDegenerate = (max === min);
        const pct = (x) => isDegenerate ? 50 : ((x - min) / (max - min)) * 100;
        ticks.forEach((t, idx) => {
            const notch = document.createElement('div');
            notch.className = 'timeline-notch' + ((idx % 2 === 0) ? ' major' : '');
            notch.style.left = pct(t.gamets) + '%';
            notchesEl.appendChild(notch);
            // Add labels for major interior ticks only (skip first and last)
            if (idx % 2 === 0 && idx > 0 && idx < (ticks.length - 1)) {
                const lbl = document.createElement('div');
                lbl.className = 'timeline-tick-label';
                lbl.style.left = pct(t.gamets) + '%';
                lbl.textContent = t.date;
                notchesEl.appendChild(lbl);
            }
        });
    }

    document.addEventListener('scroll', hideTip, { passive:true });
    window.addEventListener('resize', hideTip, { passive:true });

    function escapeHtml(s){
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    // Async refresh of live stats to avoid heavy COUNT(*) on render
    try {
        fetch('<?php echo $webRoot; ?>/ui/playthrough_stats.php', { credentials:'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(j => {
                if (!j) return;
                const ev = document.getElementById('live-eventlog');
                const og = document.getElementById('live-oghma');
                const la = document.getElementById('live-last');
                if (typeof j.eventlog === 'number' && ev) ev.textContent = String(j.eventlog);
                if (typeof j.oghma === 'number' && og) og.textContent = String(j.oghma);
                if (typeof j.last_skyrim_date === 'string' && la) la.textContent = j.last_skyrim_date || 'n/a';
            })
            .catch(()=>{});
    } catch (_e) {}
})();
</script>


