<?php

$enginePath = __DIR__ . "/../../";
$GLOBALS["ENGINE_PATH"] = $enginePath;

require_once($enginePath . "lib/runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib/model_dynmodel.php");
require_once($enginePath . "lib/data_functions.php");
require_once($enginePath . "lib/logger.php");
require_once($enginePath . "lib/core/npc_master.class.php");
require_once($enginePath . "lib/core/core_profiles.class.php");
require_once($enginePath . "lib/core/llm_connector.class.php");

// Determine web root (match core pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__."/../profile_loader.php");

$TITLE = "CHIM - Migrate Legacy Profiles";
ob_start();
include(__DIR__."/../tmpl/head.html");
include(__DIR__."/../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 60px; padding-bottom: 40px; }
.log { background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:16px; color:#ddd; font-family:monospace; white-space:pre-wrap; }
.ok { color:#9BE564; }
.warn { color:#F2C14E; }
.err { color:#FF6B6B; }
</style>

<main>
    <h1 style="margin:0 0 16px 0;">Migrate Legacy Profiles</h1>
    <p>This tool migrates all legacy profile files found in <code>/conf</code> (matching <code>conf_*.php</code>) into the database and archives them to <code>conf/.old/</code>.</p>
    <div class="log">
<?php

$configDir = $enginePath . "conf" . DIRECTORY_SEPARATOR;
$archiveDir = $configDir . ".old" . DIRECTORY_SEPARATOR;
if (!is_dir($archiveDir)) @mkdir($archiveDir, 0777, true);

$npcMaster = new NpcMaster();

$files = glob($configDir . 'conf_????????????????????????????????.php');
if (!$files) $files = [];

if (count($files) === 0) {
    echo "<span class=\"warn\">No legacy profile files found in {$configDir}</span>\n";
} else {
    echo "Found ".count($files)." legacy profile file(s).\n\n";
}

foreach ($files as $mconf) {
    $filename = basename($mconf);
    if (!preg_match('/conf_([a-f0-9]{32})\.php$/', $filename, $matches)) {
        echo "<span class=\"warn\">Skipping unexpected file: {$filename}</span>\n";
        continue;
    }

    $hash = $matches[1];

    // Preserve select globals across includes
    $overrideKeys = [
        'BOOK_EVENT_ALWAYS_NARRATOR',
        'MINIME_T5',
        'STTFUNCTION',
        'TTSFUNCTION_PLAYER',
        'TTSFUNCTION_PLAYER_VOICE',
        'TTSFUNCTION_PLAYER_VOICE_ID',
        'TTSFUNCTION_PLAYER_LANGUAGE'
    ];
    $OVERRIDES = [];
    foreach ($overrideKeys as $k) {
        if (array_key_exists($k, $GLOBALS)) $OVERRIDES[$k] = $GLOBALS[$k];
    }

    echo "Processing {$filename} ... ";

    $currentNpcData = $npcMaster->getByMD5($hash);

    if (!$currentNpcData) {
        // Load legacy profile to populate $GLOBALS
        require($mconf);

        if (empty($GLOBALS['HERIKA_NAME'])) {
            echo "<span class=\"err\">FAILED</span> (HERIKA_NAME missing)\n";
            // Restore overrides and continue
            foreach ($OVERRIDES as $k=>$v) $GLOBALS[$k] = $v;
            continue;
        }

        $npcMaster->create(["npc_name" => $GLOBALS['HERIKA_NAME']]);
        $currentNpcData = $npcMaster->getByMD5($hash);

        if ($currentNpcData) {
            $newNpcData = $npcMaster->migrateFromOldProfile($currentNpcData, $GLOBALS);
            $ingameDataRef = getBaseDataForNpcFromLog($GLOBALS['HERIKA_NAME']);
            if (is_array($newNpcData) && is_array($ingameDataRef)) {
                $newNpcData = array_merge($newNpcData, $ingameDataRef);
            }
            if ($newNpcData) {
                $npcMaster->updateByArray($newNpcData);
            }

            // Archive legacy file after successful migration
            $target = $archiveDir . $filename;
            @rename($mconf, $target);

            echo "<span class=\"ok\">MIGRATED</span> → archived to .old/\n";
        } else {
            echo "<span class=\"err\">FAILED</span> (could not create DB row)\n";
        }
    } else {
        // Already present in DB; just archive legacy file
        $target = $archiveDir . $filename;
        @rename($mconf, $target);
        echo "<span class=\"ok\">ALREADY IN DB</span> → archived to .old/\n";
    }

    // Restore overrides
    foreach ($OVERRIDES as $k=>$v) $GLOBALS[$k] = $v;
}

// Also make backups on new system
$npcMaster->backupAllNpcs(0);

?>
    </div>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


