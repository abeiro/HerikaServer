<?php
// Sync quest reference tables from rolemaster hardcoded arrays into one-array-row-per-key format.

error_reporting(E_ERROR);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootEnginePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . "..") . DIRECTORY_SEPARATOR;
$configFilepath = $rootEnginePath . "conf" . DIRECTORY_SEPARATOR;

$defaultConf = $configFilepath . "conf.php";
$sampleConf = $configFilepath . "conf.sample.php";
$confLoader = $configFilepath . "conf_loader.php";

if (file_exists($sampleConf)) {
    require_once($sampleConf);
}
if (file_exists($defaultConf)) {
    require_once($defaultConf);
}
if (file_exists($confLoader)) {
    require_once($confLoader);
}

if (empty($GLOBALS["DBDRIVER"])) {
    $GLOBALS["DBDRIVER"] = "postgresql";
}

$GLOBALS["PROFILES"] = is_array($GLOBALS["PROFILES"] ?? null) ? $GLOBALS["PROFILES"] : [];
$GLOBALS["PROFILES"]["default"] = file_exists($defaultConf) ? $defaultConf : $sampleConf;
foreach (glob($configFilepath . 'conf_????????????????????????????????????????????????.php') as $mconf) {
    if (!file_exists($mconf)) {
        continue;
    }
    $filename = basename($mconf);
    $pattern = '/conf_([a-f0-9]+)\.php/';
    preg_match($pattern, $filename, $matches);
    if (!empty($matches[1])) {
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash] = $mconf;
    }
}

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"], $GLOBALS["PROFILES"])) {
    if (file_exists($_SESSION["PROFILE"])) {
        require_once($_SESSION["PROFILE"]);
    } else {
        $_SESSION["PROFILE"] = $GLOBALS["PROFILES"]["default"];
    }
} else {
    $_SESSION["PROFILE"] = $GLOBALS["PROFILES"]["default"];
}

require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "quest_reference_data.php");

$GLOBALS["db"] = new sql();

require_once($rootEnginePath . "lib" . DIRECTORY_SEPARATOR . "rolemaster_helpers.php");

try {
    $connInfo = $GLOBALS["db"]->fetchOne("SELECT current_database() AS dbname, current_user AS dbuser");
    if (!empty($connInfo)) {
        echo "[sync] connected db=" . ($connInfo["dbname"] ?? "?") . " user=" . ($connInfo["dbuser"] ?? "?") . PHP_EOL;
    }
} catch (Exception $e) {
}

$datasetCfg = quest_reference_dataset_config();
$missingTables = [];
foreach ($datasetCfg as $cfg) {
    if (!quest_reference_table_exists($cfg["table"])) {
        $missingTables[] = $cfg["table"];
    }
}
if (!empty($missingTables)) {
    echo "[sync] warning: missing quest tables in current profile DB: " . implode(", ", $missingTables) . PHP_EOL;
    try {
        $baseSql = @file_get_contents($rootEnginePath . "data" . DIRECTORY_SEPARATOR . "quest_reference_data.sql");
        if ($baseSql !== false && strlen($baseSql) > 0) {
            $GLOBALS["db"]->execQuery($baseSql);
        }

        $arraySql = @file_get_contents($rootEnginePath . "data" . DIRECTORY_SEPARATOR . "quest_reference_data_arrays.sql");
        if ($arraySql !== false && strlen($arraySql) > 0) {
            $GLOBALS["db"]->execQuery($arraySql);
        }

        $hexSql = @file_get_contents($rootEnginePath . "data" . DIRECTORY_SEPARATOR . "quest_reference_data_hex.sql");
        if ($hexSql !== false && strlen($hexSql) > 0) {
            $GLOBALS["db"]->execQuery($hexSql);
        }

        $jsonOnlySql = @file_get_contents($rootEnginePath . "data" . DIRECTORY_SEPARATOR . "quest_reference_data_json_only.sql");
        if ($jsonOnlySql !== false && strlen($jsonOnlySql) > 0) {
            $GLOBALS["db"]->execQuery($jsonOnlySql);
        }
    } catch (Exception $e) {
        echo "[sync] warning: failed to auto-create missing tables: " . $e->getMessage() . PHP_EOL;
    }

    $missingTables = [];
    foreach ($datasetCfg as $cfg) {
        if (!quest_reference_table_exists($cfg["table"])) {
            $missingTables[] = $cfg["table"];
        }
    }
    if (!empty($missingTables)) {
        echo "[sync] warning: still missing after auto-create: " . implode(", ", $missingTables) . PHP_EOL;
    }
}

$datasetToGlobal = [
    "item_types" => "item_types",
    "npc_templates" => "npc_templates",
    "npc_own_templates" => "npc_own_templates",
    "outfit" => "outfit",
];

foreach ($datasetToGlobal as $datasetName => $globalName) {
    $cfg = $datasetCfg[$datasetName] ?? null;
    if (!$cfg || !quest_reference_table_exists($cfg["table"])) {
        echo "[sync] skip {$datasetName}: table not found" . PHP_EOL;
        continue;
    }

    $source = $GLOBALS[$globalName] ?? null;
    if (!is_array($source)) {
        echo "[sync] skip {$datasetName}: global \${$globalName} not found" . PHP_EOL;
        continue;
    }

    $ok = quest_reference_replace_dataset_with_arrays(
        $datasetName,
        $source,
        true,
        "synced from rolemaster hardcode"
    );

    if (!$ok) {
        echo "[sync] failed {$datasetName}" . PHP_EOL;
        continue;
    }

    $loaded = quest_reference_load_dataset($datasetName, true);
    $keyCount = count($loaded);
    $formIdCount = 0;
    foreach ($loaded as $items) {
        $formIdCount += count($items);
    }

    echo "[sync] {$datasetName}: keys={$keyCount}, formids={$formIdCount}" . PHP_EOL;
}

echo "[sync] complete" . PHP_EOL;
