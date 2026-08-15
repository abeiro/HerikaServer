<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . "settings.php");

if (!function_exists('chimRuntimeNeedsDbUpdates')) {
    function chimRuntimeNeedsDbUpdates(): bool
    {
        static $decision = null;
        if ($decision !== null) {
            return $decision;
        }

        $db = $GLOBALS["db"] ?? null;
        if (!$db) {
            $decision = false;
            return $decision;
        }

        $requiredTables = [
            'database_versioning',
            'general_settings',
            'core_stt_connector',
            'core_itt_connector',
            'core_tts_connector',
        ];

        try {
            $tableRows = $db->fetchAll(
                "SELECT table_name
                 FROM information_schema.tables
                 WHERE table_schema='public'
                   AND table_name IN ('database_versioning','general_settings','core_stt_connector','core_itt_connector','core_tts_connector')"
            );
        } catch (\Throwable $e) {
            $decision = false;
            return $decision;
        }

        $existingTables = [];
        foreach ($tableRows as $row) {
            $tableName = strval($row['table_name'] ?? '');
            if ($tableName !== '') {
                $existingTables[$tableName] = true;
            }
        }

        foreach ($requiredTables as $requiredTable) {
            if (empty($existingTables[$requiredTable])) {
                $decision = true;
                return $decision;
            }
        }

        $requiredVersions = [
            'general_settings' => 20260720002,
            'core_stt_connector' => 20260502002,
            'core_itt_connector' => 20260502002,
            'descriptions_defaults' => 20260611005,
            'prompts' => 20260615001,
            'skyrim_quest_definitions' => 20260628003,
            'core_tts_connector_omnivoice' => 20260708001,
        ];

        try {
            $versionRows = $db->fetchAll(
                "SELECT tablename, version
                 FROM public.database_versioning
                 WHERE tablename IN ('general_settings','core_stt_connector','core_itt_connector','descriptions_defaults','prompts','skyrim_quest_definitions','core_tts_connector_omnivoice')"
            );
        } catch (\Throwable $e) {
            $decision = true;
            return $decision;
        }

        $versions = [];
        foreach ($versionRows as $row) {
            $tableName = strval($row['tablename'] ?? '');
            if ($tableName !== '') {
                $versions[$tableName] = intval($row['version'] ?? -1);
            }
        }

        foreach ($requiredVersions as $tableName => $requiredVersion) {
            if (intval($versions[$tableName] ?? -1) < $requiredVersion) {
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

        $updatesPath = $enginePath . "debug" . DIRECTORY_SEPARATOR . "db_updates.php";
        $db=$GLOBALS["db"] ?? null;
        if (file_exists($updatesPath)) {
            require_once($updatesPath);
        }
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
