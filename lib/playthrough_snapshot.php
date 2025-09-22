<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'logger.php');

/**
 * Dragon Break autosnapshot helper.
 * Creates a Playthrough Manager snapshot in chim_meta when a large rollback is detected.
 */

function dragon_break_is_enabled() {
	if (!isset($GLOBALS["DRAGON_BREAK_AUTOSNAPSHOT"])) {
		$GLOBALS["DRAGON_BREAK_AUTOSNAPSHOT"] = true;
	}
	return !!$GLOBALS["DRAGON_BREAK_AUTOSNAPSHOT"];
}

function dragon_break_min_days() {
	if (!isset($GLOBALS["DRAGON_BREAK_MIN_DAYS"])) {
		$GLOBALS["DRAGON_BREAK_MIN_DAYS"] = 3;
	}
	return intval($GLOBALS["DRAGON_BREAK_MIN_DAYS"]);
}

/**
 * Ensure chim_meta schema and requisite tables/columns exist.
 */
function dragon_break_ensure_meta_schema($adminConn) {
	@pg_query($adminConn, "CREATE SCHEMA IF NOT EXISTS chim_meta");
	@pg_query($adminConn, "CREATE TABLE IF NOT EXISTS chim_meta.playthrough_profiles (\n        id SERIAL PRIMARY KEY,\n        name TEXT NOT NULL UNIQUE,\n        created_at TIMESTAMP NOT NULL DEFAULT NOW(),\n        size_bytes BIGINT NOT NULL,\n        storage_format TEXT NOT NULL DEFAULT 'plain_sql',\n        notes TEXT,\n        is_active BOOLEAN NOT NULL DEFAULT FALSE\n    )");
	@pg_query($adminConn, "CREATE TABLE IF NOT EXISTS chim_meta.playthrough_blobs (\n        profile_id INT PRIMARY KEY REFERENCES chim_meta.playthrough_profiles(id) ON DELETE CASCADE,\n        dump_data TEXT NOT NULL\n    )");
	// Metadata columns (idempotent)
	@pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS player_name TEXT");
	@pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS game TEXT");
	@pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS eventlog_count BIGINT");
	@pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS oghma_count BIGINT");
	@pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_profiles ADD COLUMN IF NOT EXISTS last_gamets BIGINT");

	// If an older deployment created dump_data as BYTEA, migrate it to TEXT
	$colTypeRes = @pg_query($adminConn, "SELECT data_type FROM information_schema.columns WHERE table_schema='chim_meta' AND table_name='playthrough_blobs' AND column_name='dump_data'");
	if ($colTypeRes) {
		$ct = pg_fetch_assoc($colTypeRes);
		if ($ct && isset($ct['data_type']) && strtolower($ct['data_type']) === 'bytea') {
			@pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_blobs ALTER COLUMN dump_data TYPE TEXT USING convert_from(dump_data,'UTF8')");
		}
	}
}

/**
 * Create a snapshot profile and blob using pg_dump (excluding chim_meta).
 * Returns the created profile id, or existing id on name collision, or 0 on failure.
 */
function dragon_break_create_snapshot($name, $notes) {
	$host = 'localhost';
	$port = '5432';
	$dbname = 'dwemer';
	$username = 'dwemer';
	$password = 'dwemer';
	$schema = 'public';

	$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
	if (!$adminConn) {
		Logger::error("DragonBreak: Failed to connect to database for snapshot: " . @pg_last_error());
		return 0;
	}

	dragon_break_ensure_meta_schema($adminConn);

	// Idempotency: if a profile with the same name exists, return its id
	$existsRes = @pg_query_params($adminConn, 'SELECT id FROM chim_meta.playthrough_profiles WHERE name=$1 LIMIT 1', [$name]);
	if ($existsRes) {
		$er = pg_fetch_assoc($existsRes);
		if ($er && isset($er['id'])) {
			return intval($er['id']);
		}
	}

	$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('dragon_break_' . time() . '_' . mt_rand(1000,9999) . '.sql');
	$cmd = "PGPASSWORD=" . escapeshellarg($password) . " pg_dump -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($username) . " -d " . escapeshellarg($dbname) . " -N chim_meta > " . escapeshellarg($tmpFile) . " 2>&1";
	$output = shell_exec($cmd);
	if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
		Logger::error("DragonBreak: Failed to create snapshot dump. Output: " . substr((string)$output, 0, 1000));
		@unlink($tmpFile);
		return 0;
	}

	$size = filesize($tmpFile);
	$data = file_get_contents($tmpFile);
	@unlink($tmpFile);
	if ($data === false) {
		Logger::error("DragonBreak: Could not read temporary dump file.");
		return 0;
	}

	// Collect live metadata
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

	$playerName = (string)($GLOBALS['PLAYER_NAME'] ?? 'Unknown');
	$gameName = 'Skyrim';

	@pg_query($adminConn, 'BEGIN');
	$res1 = @pg_query_params(
		$adminConn,
		'INSERT INTO chim_meta.playthrough_profiles (name, size_bytes, storage_format, notes, is_active, player_name, game, eventlog_count, oghma_count, last_gamets) VALUES ($1,$2,$3,$4,false,$5,$6,$7,$8,$9) RETURNING id',
		[$name, (string)$size, 'plain_sql', $notes, $playerName, $gameName, (string)$eventlogCount, (string)$oghmaCount, (string)$lastGamets]
	);
	if ($res1 && ($row = pg_fetch_assoc($res1))) {
		$pid = intval($row['id']);
		$res2 = @pg_query_params($adminConn, 'INSERT INTO chim_meta.playthrough_blobs (profile_id, dump_data) VALUES ($1,$2)', [$pid, $data]);
		if ($res2) {
			@pg_query($adminConn, 'COMMIT');
			Logger::info("DragonBreak: Snapshot created with id {$pid} and name '{$name}'");
			return $pid;
		}
	}
	@pg_query($adminConn, 'ROLLBACK');
	Logger::error("DragonBreak: Failed to store snapshot data: " . @pg_last_error($adminConn));
	return 0;
}

/**
 * Compose a Dragon Break snapshot name and create it if not present.
 * Returns snapshot id (existing or newly created), or 0.
 */
function dragon_break_snapshot_if_needed($prevGamets, $incomingGamets) {
	if (!dragon_break_is_enabled()) {
		return 0;
	}
	$prev = intval($prevGamets);
	$incoming = intval($incomingGamets);
	if ($prev <= 0 || $incoming <= 0) {
		return 0;
	}
	if ($incoming >= $prev) {
		return 0;
	}
	$daysRollback = gamets2days_between($incoming, $prev);
	if ($daysRollback < dragon_break_min_days()) {
		return 0;
	}
	$dateNew = convert_gamets2skyrim_long_date_no_time($incoming);
	$dateOld = convert_gamets2skyrim_long_date_no_time($prev);
	$name = "Dragon Break (" . $dateNew . " - " . $dateOld . ")";
	$notes = "Auto snapshot due to rollback of {$daysRollback} in-game days ({$incoming} -> {$prev}).";
	return dragon_break_create_snapshot($name, $notes);
}

?>


