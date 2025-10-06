<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'logger.php');

/**
 * Playthrough storage utilities using PostgreSQL Large Objects (LOB) for streaming.
 * All functions below keep PHP memory usage low by avoiding full in-memory dumps.
 */

/**
 * Ensure LOB column and cleanup trigger exist alongside existing meta schema.
 * Safe to call multiple times.
 */
function ptm_ensure_lob_schema($adminConn) {
    // Add LOB column if missing
    @pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_blobs ADD COLUMN IF NOT EXISTS dump_lob OID");
    // Ensure dump_data can be NULL for LOB-based rows
    @pg_query($adminConn, "ALTER TABLE chim_meta.playthrough_blobs ALTER COLUMN dump_data DROP NOT NULL");

    // Create cleanup function (idempotent via CREATE OR REPLACE)
    $fn = <<<SQL
CREATE OR REPLACE FUNCTION chim_meta.delete_playthrough_lob() 
RETURNS TRIGGER AS $$
BEGIN
  IF OLD.dump_lob IS NOT NULL THEN
    PERFORM lo_unlink(OLD.dump_lob);
  END IF;
  RETURN OLD;
END;
$$ LANGUAGE plpgsql;
SQL;
    @pg_query($adminConn, $fn);

    // Create trigger if not present
    $createTrig = <<<SQL
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_trigger 
    WHERE tgname = 'playthrough_blob_cleanup'
  ) THEN
    CREATE TRIGGER playthrough_blob_cleanup
      BEFORE DELETE ON chim_meta.playthrough_blobs
      FOR EACH ROW
      EXECUTE FUNCTION chim_meta.delete_playthrough_lob();
  END IF;
END $$;
SQL;
    @pg_query($adminConn, $createTrig);
}

/**
 * Stream a file on disk into a new PostgreSQL Large Object.
 * Returns created OID on success, or 0 on failure.
 */
function ptm_stream_file_to_lob($conn, string $filePath, int $chunkSize = 262144): int {
    if (!file_exists($filePath)) return 0;
    $fh = @fopen($filePath, 'rb');
    if ($fh === false) return 0;

    // Large object operations must be inside a transaction
    $oid = 0;
    $ok = @pg_query($conn, 'BEGIN');
    if (!$ok) { fclose($fh); return 0; }
    try {
        $oid = @pg_lo_create($conn);
        if (!$oid) throw new Exception('pg_lo_create failed');
        $lo = @pg_lo_open($conn, $oid, 'w');
        if (!$lo) throw new Exception('pg_lo_open failed');
        while (!feof($fh)) {
            $buf = fread($fh, $chunkSize);
            if ($buf === false) throw new Exception('fread failed');
            if ($buf === '') continue;
            $w = @pg_lo_write($lo, $buf);
            if ($w === false) throw new Exception('pg_lo_write failed');
        }
        @pg_lo_close($lo);
        @pg_query($conn, 'COMMIT');
    } catch (Throwable $e) {
        @pg_query($conn, 'ROLLBACK');
        if ($oid) { @pg_lo_unlink($conn, $oid); }
        $oid = 0;
    }
    fclose($fh);
    return (int)$oid;
}

/**
 * Stream a PostgreSQL Large Object to a file on disk.
 * Returns true on success.
 */
function ptm_stream_lob_to_file($conn, int $oid, string $destFile, int $chunkSize = 262144): bool {
    $dir = dirname($destFile);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $fh = @fopen($destFile, 'wb');
    if ($fh === false) return false;
    $okAll = false;
    $ok = @pg_query($conn, 'BEGIN');
    if (!$ok) { fclose($fh); return false; }
    try {
        $lo = @pg_lo_open($conn, $oid, 'r');
        if (!$lo) throw new Exception('pg_lo_open failed');
        while (true) {
            $buf = @pg_lo_read($lo, $chunkSize);
            if ($buf === false) throw new Exception('pg_lo_read failed');
            if ($buf === '' || $buf === null) break;
            $w = fwrite($fh, $buf);
            if ($w === false) throw new Exception('fwrite failed');
        }
        @pg_lo_close($lo);
        @pg_query($conn, 'COMMIT');
        $okAll = true;
    } catch (Throwable $e) {
        @pg_query($conn, 'ROLLBACK');
        $okAll = false;
    }
    fclose($fh);
    return $okAll;
}

/**
 * Create a new profile row and store dump file into LOB.
 * Returns ['success'=>bool, 'id'=>int, 'size'=>int, 'error'=>string].
 */
function ptm_create_profile_with_lob(
    $adminConn,
    string $name,
    string $notes,
    bool $markActive,
    string $playerName,
    string $gameName,
    int $eventlogCount,
    int $oghmaCount,
    int $lastGamets,
    string $dumpFile
) : array {
    ptm_ensure_lob_schema($adminConn);
    if (!file_exists($dumpFile)) {
        return ['success'=>false,'id'=>0,'size'=>0,'error'=>'Dump file not found'];
    }
    $size = (int)filesize($dumpFile);
    if ($size < 0) $size = 0;

    $res = ['success'=>false,'id'=>0,'size'=>$size,'error'=>''];
    // Insert profile first, then write LOB and blob row
    $ok = @pg_query($adminConn, 'BEGIN');
    if (!$ok) return ['success'=>false,'id'=>0,'size'=>$size,'error'=>'DB BEGIN failed'];
    try {
        $isActive = $markActive ? 'true' : 'false';
        $q1 = @pg_query_params(
            $adminConn,
            "INSERT INTO chim_meta.playthrough_profiles (name, size_bytes, storage_format, notes, is_active, player_name, game, eventlog_count, oghma_count, last_gamets) VALUES ($1,$2,$3,$4,{$isActive},$5,$6,$7,$8,$9) RETURNING id",
            [$name, (string)$size, 'lob_sql', $notes, $playerName, $gameName, (string)$eventlogCount, (string)$oghmaCount, (string)$lastGamets]
        );
        if (!$q1) throw new Exception(@pg_last_error($adminConn) ?: 'Insert profile failed');
        $row = @pg_fetch_assoc($q1);
        if (!$row || !isset($row['id'])) throw new Exception('Insert profile: missing id');
        $pid = (int)$row['id'];

        $oid = ptm_stream_file_to_lob($adminConn, $dumpFile);
        if ($oid <= 0) throw new Exception('Failed to write LOB');

        $q2 = @pg_query_params(
            $adminConn,
            'INSERT INTO chim_meta.playthrough_blobs (profile_id, dump_lob, dump_data) VALUES ($1,$2,NULL)',
            [$pid, (string)$oid]
        );
        if (!$q2) throw new Exception(@pg_last_error($adminConn) ?: 'Insert blob failed');

        @pg_query($adminConn, 'COMMIT');
        $res['success'] = true;
        $res['id'] = $pid;
    } catch (Throwable $e) {
        @pg_query($adminConn, 'ROLLBACK');
        $res['success'] = false;
        $res['error'] = $e->getMessage();
    }
    return $res;
}

/**
 * Update an existing profile's blob with a new dump file and refresh metadata.
 * Returns ['success'=>bool, 'size'=>int, 'error'=>string].
 */
function ptm_update_profile_blob_from_file(
    $adminConn,
    int $profileId,
    string $dumpFile,
    string $playerName,
    string $gameName,
    int $eventlogCount,
    int $oghmaCount,
    int $lastGamets
) : array {
    ptm_ensure_lob_schema($adminConn);
    if (!file_exists($dumpFile)) {
        return ['success'=>false,'size'=>0,'error'=>'Dump file not found'];
    }
    $size = (int)filesize($dumpFile);
    if ($size < 0) $size = 0;

    $ok = @pg_query($adminConn, 'BEGIN');
    if (!$ok) return ['success'=>false,'size'=>$size,'error'=>'DB BEGIN failed'];
    $err = '';
    try {
        // Unlink previous LOB if exists
        $prev = @pg_query_params($adminConn, 'SELECT dump_lob FROM chim_meta.playthrough_blobs WHERE profile_id=$1', [(string)$profileId]);
        $prevOid = 0;
        if ($prev && ($pr = @pg_fetch_assoc($prev)) && isset($pr['dump_lob']) && $pr['dump_lob'] !== null) {
            $prevOid = (int)$pr['dump_lob'];
        }

        $oid = ptm_stream_file_to_lob($adminConn, $dumpFile);
        if ($oid <= 0) throw new Exception('Failed to write LOB');

        $u1 = @pg_query_params(
            $adminConn,
            'UPDATE chim_meta.playthrough_blobs SET dump_lob=$2, dump_data=NULL WHERE profile_id=$1',
            [(string)$profileId, (string)$oid]
        );
        $affected = $u1 ? @pg_affected_rows($u1) : 0;
        if ($affected === 0) {
            $u1 = @pg_query_params($adminConn, 'INSERT INTO chim_meta.playthrough_blobs (profile_id, dump_lob, dump_data) VALUES ($1,$2,NULL)', [(string)$profileId, (string)$oid]);
        }
        if (!$u1) throw new Exception(@pg_last_error($adminConn) ?: 'Upsert blob failed');

        if ($prevOid > 0) { @pg_lo_unlink($adminConn, $prevOid); }

        $u2 = @pg_query_params(
            $adminConn,
            'UPDATE chim_meta.playthrough_profiles SET size_bytes=$2, storage_format=$3, player_name=$4, game=$5, eventlog_count=$6, oghma_count=$7, last_gamets=$8 WHERE id=$1',
            [(string)$profileId, (string)$size, 'lob_sql', $playerName, $gameName, (string)$eventlogCount, (string)$oghmaCount, (string)$lastGamets]
        );
        if (!$u2) throw new Exception(@pg_last_error($adminConn) ?: 'Update profile failed');

        @pg_query($adminConn, 'COMMIT');
        return ['success'=>true,'size'=>$size,'error'=>''];
    } catch (Throwable $e) {
        @pg_query($adminConn, 'ROLLBACK');
        $err = $e->getMessage();
        return ['success'=>false,'size'=>$size,'error'=>$err];
    }
}

/**
 * Fetch snapshot by profile id to a file. Prefers LOB; falls back to TEXT.
 * Returns ['success'=>bool, 'used'=>'lob'|'text', 'error'=>string].
 */
function ptm_fetch_snapshot_to_file($adminConn, int $profileId, string $destFile, int $chunkSize = 262144): array {
    $res = ['success'=>false, 'used'=>'', 'error'=>''];
    $q = @pg_query_params($adminConn, 'SELECT dump_lob, dump_data FROM chim_meta.playthrough_blobs WHERE profile_id=$1', [(string)$profileId]);
    if (!$q) {
        return ['success'=>false,'used'=>'','error'=>(@pg_last_error($adminConn) ?: 'Query failed')];
    }
    $row = @pg_fetch_assoc($q);
    if (!$row) {
        return ['success'=>false,'used'=>'','error'=>'Snapshot not found'];
    }
    $lob = isset($row['dump_lob']) ? (int)$row['dump_lob'] : 0;
    if ($lob > 0) {
        $ok = ptm_stream_lob_to_file($adminConn, $lob, $destFile, $chunkSize);
        return ['success'=>$ok, 'used'=>'lob', 'error'=>$ok ? '' : 'LOB stream failed'];
    }
    if (!isset($row['dump_data']) || $row['dump_data'] === null) {
        return ['success'=>false,'used'=>'','error'=>'Empty snapshot data'];
    }
    $ok = (@file_put_contents($destFile, $row['dump_data']) !== false);
    return ['success'=>$ok, 'used'=>'text', 'error'=>$ok ? '' : 'Write file failed'];
}

?>


