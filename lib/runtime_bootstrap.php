<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . "settings.php");
require_once(__DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "MigrationRunner.php");

if (!function_exists('chimRuntimeNeedsDbUpdates')) {
    function chimRuntimeNeedsDbUpdates(): bool
    {
        static $decision = null;
        if ($decision !== null) {
            return $decision;
        }

        $db = $GLOBALS["db"] ?? null;
        if (!$db) {
            return false;
        }

        try {
            $relations = $db->fetchAll(
                "SELECT to_regclass('public.database_versioning') AS legacy_ledger,
                        to_regclass('chim_meta.schema_migrations') AS migration_ledger"
            );
        } catch (\Throwable $e) {
            $decision = true;
            return $decision;
        }

        $legacyLedger = strval($relations[0]['legacy_ledger'] ?? '');
        $migrationLedger = strval($relations[0]['migration_ledger'] ?? '');
        if ($migrationLedger === '') {
            // An entirely empty database is allowed through to the installer.
            $decision = $legacyLedger !== '';
            return $decision;
        }

        try {
            $root = dirname(__DIR__);
            $manifest = \HerikaServer\Database\MigrationRunner::sourceManifest($root);
            $rows = $db->fetchAll('SELECT version, name, checksum FROM chim_meta.schema_migrations ORDER BY version');
        } catch (\Throwable $e) {
            $decision = true;
            return $decision;
        }

        $applied = [];
        foreach ($rows as $row) {
            $applied[intval($row['version'] ?? 0)] = [
                'name' => strval($row['name'] ?? ''),
                'checksum' => rtrim(strval($row['checksum'] ?? '')),
            ];
        }

        if (count($applied) !== count($manifest)) {
            $decision = true;
            return $decision;
        }
        foreach ($manifest as $version => $migration) {
            if (($applied[$version]['name'] ?? null) !== $migration['name']
                || !hash_equals($migration['checksum'], $applied[$version]['checksum'] ?? '')) {
                $decision = true;
                return $decision;
            }
        }

        $decision = false;
        return $decision;
    }
}

if (!function_exists('chimRuntimeImportConfigVariables')) {
    function chimRuntimeImportConfigVariables(array $variables): void
    {
        foreach ($variables as $name => $value) {
            if (!is_string($name) || $name === '' || $name[0] === '_') {
                continue;
            }
            if (!preg_match('/^[A-Z0-9_]+$/', $name)) {
                continue;
            }
            $GLOBALS[$name] = $value;
        }
    }
}

if (!function_exists('chimRuntimeEnsureDbUpdates')) {
    function chimRuntimeEnsureDbUpdates(string $enginePath): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        if (!chimRuntimeNeedsDbUpdates()) {
            return;
        }

        $message = "HerikaServer database schema is not synchronized with this code. "
            . "Back up the database, then run: php scripts/database.php legacy-bridge";
        error_log('[RuntimeBootstrap] ' . $message);

        if (PHP_SAPI === 'cli') {
            throw new \RuntimeException($message);
        }

        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message . "\n";
        exit;
    }
}

if (!function_exists('chimRuntimeEnsurePluginSchema')) {
    function chimRuntimeEnsurePluginSchema(): void
    {
        $db = $GLOBALS["db"] ?? null;
        if (!$db) {
            return;
        }

        try {
            $db->execQuery("CREATE SCHEMA IF NOT EXISTS plugins");
            $db->execQuery("SET search_path TO public");
        } catch (\Throwable $e) {
            error_log("[RuntimeBootstrap] Could not ensure plugins schema: " . $e->getMessage());
        }
    }
}

if (!function_exists('chimRuntimeApplyBootstrapOptions')) {
    function chimRuntimeApplyBootstrapOptions(string $enginePath, array $options = []): void
    {
        $runDbUpdates = !array_key_exists('run_db_updates', $options) || (bool)$options['run_db_updates'];
        $loadGeneralSettings = !array_key_exists('load_general_settings', $options) || (bool)$options['load_general_settings'];
        $loadSttConnector = !array_key_exists('load_stt_connector', $options) || (bool)$options['load_stt_connector'];
        $loadIttConnector = !array_key_exists('load_itt_connector', $options) || (bool)$options['load_itt_connector'];
        $loadTtsConnector = $options['load_tts_connector'] ?? false;
        $loadPlayerName = !empty($options['load_player_name']);
        $loadNarrator = !empty($options['load_narrator']);

        if ($runDbUpdates) {
            chimRuntimeEnsureDbUpdates($enginePath);
        }
        if ($loadGeneralSettings) {
            chimLoadGeneralSettingsIntoGlobals();
        }
        if ($loadSttConnector) {
            chimLoadActiveSttConnectorIntoGlobals();
        }
        if ($loadIttConnector) {
            chimLoadActiveIttConnectorIntoGlobals();
        }
        if (is_string($loadTtsConnector) && trim($loadTtsConnector) !== '') {
            chimLoadPreferredTtsConnectorIntoGlobals(trim($loadTtsConnector));
        } elseif ($loadTtsConnector) {
            chimLoadPreferredTtsConnectorIntoGlobals();
        }
        if ($loadPlayerName) {
            chimLoadPlayerNameIntoGlobals();
        }
        if ($loadNarrator) {
            chimLoadNarratorSettingsIntoGlobals();
        }
    }
}

if (!function_exists('chimRuntimeBootstrap')) {
    function chimRuntimeBootstrap(string $enginePath, array $options = []): void
    {
        $enginePath = rtrim($enginePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $GLOBALS["ENGINE_PATH"] = $enginePath;

        $confPath = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
        $confSamplePath = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php";

        if (file_exists($confSamplePath)) {
            require_once($confSamplePath);
        }
        if (file_exists($confPath)) {
            require_once($confPath);
        }

        chimRuntimeImportConfigVariables(get_defined_vars());

        if (empty($GLOBALS["DBDRIVER"])) {
            throw new \RuntimeException("DBDRIVER is not configured during runtime bootstrap.");
        }

        require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
        if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
            $GLOBALS["db"] = new sql();
        }

        chimRuntimeEnsurePluginSchema();
        chimRuntimeApplyBootstrapOptions($enginePath, $options);
    }
}

if (!function_exists('chimRuntimeBootstrapIfNeeded')) {
    function chimRuntimeBootstrapIfNeeded(string $enginePath, array $options = []): void
    {
        $enginePath = rtrim($enginePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (empty($GLOBALS["DBDRIVER"]) || !isset($GLOBALS["db"]) || !is_object($GLOBALS["db"])) {
            chimRuntimeBootstrap($enginePath, $options);
            return;
        }

        $GLOBALS["ENGINE_PATH"] = $enginePath;
        chimRuntimeApplyBootstrapOptions($enginePath, $options);
    }
}

if (!function_exists('chimRuntimeBindActiveProfileFromRequest')) {
    function chimRuntimeBindActiveProfileFromRequest(): ?string
    {
        if (!isset($_GET["profile"])) {
            return null;
        }

        $profile = trim(strval($_GET["profile"]));
        if ($profile === '') {
            return null;
        }

        $GLOBALS["active_profile"] = $profile;
        return $profile;
    }
}

?>
