<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."logger.php");

/**
 * Initialize log trimming once per server restart (deferred after DB ready)
 * Keeps last $maxLines lines for known log files when AUTO_TRIM_LOGS_ENABLED is true
 */
function initializeLogTrimming($maxLines = 10000) {
    try {
        if (!isset($GLOBALS['db']) || !is_object($GLOBALS['db'])) {
            return;
        }

        $db = $GLOBALS['db'];
        // Ensure chim_meta.settings exists
        @$db->execQuery("CREATE SCHEMA IF NOT EXISTS chim_meta");
        @$db->execQuery("CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");

        // Read enabled flag (default true)
        $enabledRow = @$db->fetchOne("SELECT value FROM chim_meta.settings WHERE key='AUTO_TRIM_LOGS_ENABLED'");
        $enabled = true;
        if (is_array($enabledRow) && isset($enabledRow['value'])) {
            $enabled = in_array(strtolower(trim((string)$enabledRow['value'])), ['true','1','yes','on']);
        } else {
            // persist default true
            @$db->insert('chim_meta.settings', ['key'=>'AUTO_TRIM_LOGS_ENABLED','value'=>'true']);
            $enabled = true;
        }

        if (!$enabled) {
            Logger::info("Log trimming disabled by setting");
            return;
        }

        // Prevent frequent runs within the same day (or at most every 30 minutes)
        $lastRunRow = @$db->fetchOne("SELECT value FROM chim_meta.settings WHERE key='AUTO_TRIM_LOGS_LAST_RUN'");
        $now = time();
        if (is_array($lastRunRow) && isset($lastRunRow['value'])) {
            $lastRun = (int)$lastRunRow['value'];
            if ($now - $lastRun < 1800) { // 30 minutes
                Logger::info("Skipping log trimming (recently ran)");
                return;
            }
        }

        $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR;
        $logFiles = [
            'apache_error.log',
            'chim.log',
            'output_from_llm.log',
            'context_sent_to_llm.log',
            'context_sent_to_llm_fast.log',
            'output_to_plugin.log',
            'stt.log',
            'vision.log',
            'debugStream.log',
            'service.log',
            'monitor.log',
            'manager.log'
        ];

        $trimCount = 0;
        foreach ($logFiles as $file) {
            $path = $logDir . $file;
            if (is_file($path) && is_readable($path) && is_writable($path)) {
                trimFileToLastLines($path, $maxLines);
                $trimCount++;
            }
        }

        @$db->insert('chim_meta.settings', ['key'=>'AUTO_TRIM_LOGS_LAST_RUN','value'=> (string)$now]);
        // If already exists, update
        @$db->update('chim_meta.settings', "value='".$db->escape((string)$now)."'", "key='AUTO_TRIM_LOGS_LAST_RUN'");
        Logger::info("Log trimming run completed for $trimCount files");
    } catch (Throwable $e) {
        Logger::warn("Error during log trimming: ".$e->getMessage());
    }
}

/**
 * Tail a file and rewrite only the last $maxLines lines
 */
function trimFileToLastLines($filePath, $maxLines) {
    $fp = @fopen($filePath, 'r');
    if (!$fp) return;
    $buffer = 8192;
    $lines = [];
    $chunk = '';
    fseek($fp, 0, SEEK_END);
    $pos = ftell($fp);
    while ($pos > 0 && count($lines) <= $maxLines) {
        $read = ($pos >= $buffer) ? $buffer : $pos;
        $pos -= $read;
        fseek($fp, $pos);
        $chunk = fread($fp, $read) . $chunk;
        while (($nl = strrpos($chunk, "\n")) !== false) {
            $line = substr($chunk, $nl + 1);
            $chunk = substr($chunk, 0, $nl);
            array_unshift($lines, $line);
            if (count($lines) > $maxLines) break 2;
        }
    }
    if ($chunk !== '' && count($lines) < $maxLines) {
        array_unshift($lines, $chunk);
    }
    fclose($fp);

    $tmp = $filePath . '.tmp';
    $out = @fopen($tmp, 'w');
    if (!$out) return;
    $start = max(0, count($lines) - $maxLines);
    for ($i=$start; $i<count($lines); $i++) {
        $l = rtrim($lines[$i], "\r\n");
        fwrite($out, $l . "\n");
    }
    fclose($out);
    @rename($tmp, $filePath);
    // Keep logs readable/writable for owner+group; use octal literal (not decimal).
    @chmod($filePath, 0664);
}

/**
 * Deferred init similar to automatic backups
 */
function deferredLogTrimInit() {
    static $hasRun = false;
    if ($hasRun) return;
    $hasRun = true;
    initializeLogTrimming(10000);
}

?>


