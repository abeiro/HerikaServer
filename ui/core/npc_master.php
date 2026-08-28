<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "eventlog_helper.php");

$GLOBALS["ENGINE_PATH"]=$enginePath;

require_once("{$enginePath}/lib/core/npc_master.class.php");


//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from below file
include(__DIR__."/tmpl/ui_utils.php");

// Determine web root and include site chrome like oghma_upload
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$TITLE = "🧙 CHIM - NPC Master";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/npc_event_history.css">
<style>
/* Core styling alignment */
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main {
    padding-top: 10px;
    padding-bottom: 24px;
}
.page-header {
    margin: 0 0 24px 0; 
    padding: 24px;
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(28, 28, 28, 0.98));
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    text-align: center;
}
h1.api-title { 
    margin: 0 0 8px 0; 
    font-family: 'MagicCards', serif; 
    word-spacing: 8px; 
    font-size: 2em; 
    color: rgb(242, 124, 17); 
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5); 
}
.page-subtitle {
    color: #aaa;
    font-size: 0.95em;
    line-height: 1.5;
    margin: 0;
}

/* Relationship Build Button - Gray/Orange theme to match UI */
.btn-rel-build {
    background: rgba(58, 58, 74, 0.8);
    color: rgb(242, 124, 17);
    border: 1px solid rgba(242, 124, 17, 0.5);
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
}
.btn-rel-build:hover {
    background: rgba(74, 74, 90, 0.9);
    border-color: rgb(242, 124, 17);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(242, 124, 17, 0.2);
}

/* Relationship Build Modal - Gray/Orange theme */
.rel-build-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.rel-build-modal-overlay.show { display: flex; }
.rel-build-modal {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
    border: 1px solid #3a3a3a;
    border-radius: 12px;
    padding: 32px;
    max-width: 600px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6), 
                0 0 30px rgba(242, 124, 17, 0.1);
}
.rel-build-modal h2 {
    margin: 0 0 22px 0;
    color: rgb(242, 124, 17);
    font-family: 'MagicCards', serif;
    font-size: 1.7em;
    text-align: center;
    text-shadow: 0 0 15px rgba(242, 124, 17, 0.4);
}
.rel-build-modal .modal-body {
    color: #e0e0e0;
    line-height: 1.6;
    margin-bottom: 20px;
    text-align: center;
}
.rel-build-modal .modal-body p { margin: 12px 0; }
.rel-build-modal .modal-body strong { color: rgb(242, 124, 17); }
.rel-build-modal .stats-box {
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
    display: flex;
    justify-content: space-around;
    text-align: center;
    box-shadow: inset 0 1px rgba(255, 255, 255, 0.03);
}
.rel-build-modal .stat-item { }
.rel-build-modal .stat-value { font-size: 2em; color: rgb(242, 124, 17); font-weight: bold; text-shadow: 0 0 10px rgba(242, 124, 17, 0.3); }
.rel-build-modal .stat-label { font-size: 0.85em; color: #999; margin-top: 4px; }
.rel-build-modal .progress-section { display: none; margin: 20px 0; }
.rel-build-modal .progress-section.show { display: block; }
.rel-build-modal .progress-bar-wrap {
    background: rgba(26, 26, 26, 0.9);
    border-radius: 8px;
    height: 26px;
    overflow: hidden;
    margin: 12px 0;
    border: 1px solid #3a3a3a;
}
.rel-build-modal .progress-bar {
    background: linear-gradient(90deg, rgb(200, 100, 10), rgb(242, 124, 17));
    height: 100%;
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 7px;
    box-shadow: 0 0 10px rgba(242, 124, 17, 0.5);
}
.rel-build-modal .progress-text {
    text-align: center;
    color: rgb(242, 124, 17);
    font-size: 0.9em;
    margin-top: 8px;
}
.rel-build-modal .progress-log {
    background: rgba(10, 10, 10, 0.9);
    border: 1px solid #333;
    border-radius: 8px;
    padding: 12px;
    max-height: 150px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 0.8em;
    color: #999;
    margin-top: 12px;
}
.rel-build-modal .progress-log .success { color: #4ade80; }
.rel-build-modal .progress-log .error { color: #f87171; }
.rel-build-modal .progress-log .skip { color: rgb(242, 124, 17); }
.rel-build-modal .modal-actions {
    display: flex;
    gap: 14px;
    justify-content: center;
    margin-top: 22px;
}
.rel-build-modal .btn-start {
    background: rgba(58, 58, 58, 0.9);
    color: rgb(242, 124, 17);
    border: 1px solid rgba(242, 124, 17, 0.5);
    padding: 12px 32px;
    border-radius: 8px;
    font-size: 1em;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}
.rel-build-modal .btn-start:hover { 
    background: rgba(74, 74, 74, 0.9); 
    border-color: rgb(242, 124, 17);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(242, 124, 17, 0.3);
}
.rel-build-modal .btn-start:disabled { 
    background: #222; 
    color: #555; 
    border-color: #444; 
    cursor: not-allowed; 
    transform: none;
    box-shadow: none;
}
.rel-build-modal .btn-cancel {
    background: rgba(58, 58, 58, 0.9);
    color: rgb(242, 124, 17);
    border: 1px solid rgba(242, 124, 17, 0.5);
    padding: 12px 32px;
    border-radius: 8px;
    font-size: 1em;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}
.rel-build-modal .btn-cancel:hover { 
    background: rgba(74, 74, 74, 0.9); 
    border-color: rgb(242, 124, 17);
    transform: translateY(-1px);
}
.rel-build-modal .connector-info {
    background: rgba(242, 124, 17, 0.1);
    border: 1px solid rgba(242, 124, 17, 0.5);
    border-radius: 8px;
    padding: 12px;
    margin: 12px 0;
    font-size: 0.9em;
    text-align: center;
    color: #34d399;
}
.rel-build-modal .connector-info .connector-model {
    color: #bbb;
    font-size: 0.9em;
}
.rel-build-modal .no-connector {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.5);
    color: #f87171;
}

.npc-metadata-collapse {
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    background: rgba(20, 20, 20, 0.7);
    overflow: hidden;
}
.npc-metadata-collapse > summary {
    list-style: none;
    cursor: pointer;
    padding: 10px 12px;
    color: rgb(242, 124, 17);
    font-weight: 700;
    user-select: none;
    border-bottom: 1px solid transparent;
}
.npc-metadata-collapse > summary::-webkit-details-marker {
    display: none;
}
.npc-metadata-collapse > summary::after {
    content: '▸';
    float: right;
    color: #9fb1c9;
    transition: transform 0.15s ease;
}
.npc-metadata-collapse[open] > summary::after {
    transform: rotate(90deg);
}
.npc-metadata-collapse[open] > summary {
    border-bottom-color: #3a3a3a;
}
.npc-metadata-collapse-body {
    padding: 12px;
}

</style>

<main>

<?php
$GLOBALS["db"] = new sql();
$npc = new NpcMaster();

// Check if The Narrator exists in core_npc_master (for informational note)
$narratorExistsInNpcMaster = false;
try {
    $narratorCheck = $GLOBALS["db"]->fetchOne("SELECT 1 FROM core_npc_master WHERE npc_name = 'The Narrator' LIMIT 1");
    $narratorExistsInNpcMaster = ($narratorCheck !== null && $narratorCheck !== false);
} catch (Exception $e) {
    // Ignore errors
}

$lastInfoRow=$GLOBALS["db"]->fetchOne("select max(gamets) as gamets from eventlog where type='infosave'");
$LAST_INFOSAVE_EVENT=$lastInfoRow["gamets"];

// Helper: resolve race icon web path if file exists
if (!function_exists('race_icon_web_path')) {
    function race_icon_web_path($race, $webRoot, $refid, $md5 = '', $npcName = '', $portraitRel = ''){
        // 0) If metadata specifies a portrait relative path under data/pictures, use it first
        $portraitRel = trim((string)$portraitRel);
        if ($portraitRel !== '') {
            $portraitRel = ltrim(str_replace(['\\'], '/', $portraitRel), '/');
            $picturesRootFs = rtrim("{$GLOBALS["ENGINE_PATH"]}/data/pictures/", '/\\') . DIRECTORY_SEPARATOR;
            $picturesRootUrl = rtrim($webRoot . '/data/pictures/', '/');
            $fs = realpath($picturesRootFs . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $portraitRel));
            $picturesRootFs=realpath($picturesRootFs);
            error_log("<$picturesRootFs> <$fs>");
            if ($fs !== false && strpos($fs, $picturesRootFs) === 0 && is_file($fs)) {
                return $picturesRootUrl . '/' . str_replace('%2F','/', rawurlencode($portraitRel));
            }
        }
        // 1) Prefer per-NPC portrait from data/pictures/profile
        $refid = strtoupper($refid);
        $profileDirFs = rtrim("{$GLOBALS["ENGINE_PATH"]}/data/pictures/profile/", '/\\') . DIRECTORY_SEPARATOR;
        $profileDirUrl = rtrim($webRoot . '/data/pictures/profile/', '/');
        $exts = ['png','jpg','jpeg','webp','gif'];
        $candidates = [];
        if (!empty($md5)) { $candidates[] = $md5; }
        if (!empty($refid)) { $candidates[] = $refid; }
        if (!empty($npcName)) {
            $in = strtolower((string)$npcName);
            $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($words)) {
                $candidates[] = implode('', $words);
                $candidates[] = implode('-', $words);
                $candidates[] = implode('_', $words);
            }
        }
        $seen = [];
        foreach ($candidates as $base){
            $base = trim((string)$base);
            if ($base === '' || isset($seen[$base])) continue;
            $seen[$base] = true;
            foreach ($exts as $ext){
                $fs = $profileDirFs . $base . '.' . $ext;
                if (file_exists($fs)) {
                    return $profileDirUrl . '/' . rawurlencode($base . '.' . $ext);
                }
            }
        }

        // 2) Fallback to race icon pack
        $in = strtolower((string)$race);
        $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
        $slug = implode('', $words);
        if ($slug === '') { $words = []; }
        $aliases = [
            'highelf'=>'altmer', 'altmer'=>'altmer',
            'woodelf'=>'bosmer', 'bosmer'=>'bosmer',
            'darkelf'=>'dunmer', 'dunmer'=>'dunmer',
            'orsimer'=>'orc', 'orc'=>'orc',
            'argonian'=>'argonian', 'khajiit'=>'khajiit', 'khajit'=>'khajiit',
            'breton'=>'breton', 'imperial'=>'imperial',
            'nord'=>'nord', 'redguard'=>'redguard',
            'oldpeople'=>'nord', 'oldpeoplerace'=>'nord',
        ];
        $base = $aliases[$slug] ?? $slug;
        $variants = [];
        $variants[] = $base;
        if (!empty($words)){
            $variants[] = implode('', $words);
            $variants[] = implode('-', $words);
            $variants[] = implode('_', $words);
        }
        // Synonyms/misspellings
        if ($base === 'khajiit') { $variants[] = 'khajit'; }
        if ($base === 'khajit') { $variants[] = 'khajiit'; }
        $variants = array_values(array_unique(array_filter($variants, function($v){ return $v !== ''; })));
        $fsDir = __DIR__ . '/../images/races/';
        $exts2 = ['png','jpg','jpeg','webp','gif','svg'];
        foreach ($variants as $name){
            foreach ($exts2 as $ext){
                $fs = $fsDir . $name . '.' . $ext;
                if (file_exists($fs)) return $webRoot . '/ui/images/races/' . $name . '.' . $ext;
            }
        }
        $defaultFs = $fsDir . 'default.png';
        if (file_exists($defaultFs)) { return $webRoot . '/ui/images/races/default.png'; }
        return '';
    }
}

// Helper: ordered source-mod chain for an actor.
// metadata.mods keeps game order: the first entry defines the actor, the last may override it.
if (!function_exists('npc_mod_chain')) {
    function npc_mod_chain($metadata){
        $mods = null;
        if (is_array($metadata)) {
            $mods = $metadata['mods'] ?? null;
        }
        if (is_string($mods)) {
            $mods = preg_split('/[#,\r\n]+/', $mods);
        }
        if (!is_array($mods)) return [];
        $chain = [];
        foreach ($mods as $mod) {
            if (!is_scalar($mod)) continue;
            $label = trim((string)$mod);
            if ($label !== '') $chain[] = $label;
        }
        return $chain;
    }
}

// Helper: the mod that defines this actor, or '' when nothing is recorded
if (!function_exists('npc_defining_mod')) {
    function npc_defining_mod($row, array $chain, $metadata = null){
        if (is_array($metadata)) {
            $identity = $metadata['actor_identity'] ?? null;
            if (is_array($identity)) {
                $recorded = trim((string)($identity['source_mod'] ?? ''));
                if ($recorded !== '') return $recorded;
            }
        }
        $explicit = '';
        if (is_array($row)) {
            $explicit = trim((string)($row['source_mod'] ?? ''));
        }
        if ($explicit !== '') return $explicit;
        return $chain === [] ? '' : $chain[0];
    }
}

// Helper: normalise a RefID for display. FF RefIDs are assigned at runtime.
if (!function_exists('npc_refid_display')) {
    function npc_refid_display($refid){
        $raw = strtoupper(preg_replace('/^0X/i', '', trim((string)$refid)));
        if (!preg_match('/^[0-9A-F]{1,8}$/', $raw)) {
            return ['text' => 'No RefID', 'runtime' => false, 'known' => false];
        }
        $padded = str_pad($raw, 8, '0', STR_PAD_LEFT);
        return ['text' => $padded, 'runtime' => strpos($padded, 'FF') === 0, 'known' => true];
    }
}

// Helper: how many stored profiles share this visible name
if (!function_exists('npc_duplicate_count')) {
    function npc_duplicate_count(array $counts, $name){
        $key = mb_strtolower(trim((string)$name));
        if ($key === '') return 1;
        $count = (int)($counts[$key] ?? 1);
        return $count > 1 ? $count : 1;
    }
}

// Helper: compact identity line plus the hover/focus mod-chain detail for one card
if (!function_exists('render_npc_identity_lines')) {
    function render_npc_identity_lines(array $row, array $metaTmp){
        $refid = npc_refid_display($row['refid'] ?? '');
        $chain = npc_mod_chain($metaTmp);
        $source = npc_defining_mod($row, $chain, $metaTmp);
        $chainId = 'npc_chain_' . (string)($row['id'] ?? '0');

        $refidTitle = $refid['runtime']
            ? 'Runtime RefID, assigned by the game and not stable across saves.'
            : 'Reference ID recorded for this actor.';

        echo '<div class="npc-line"><span class="npc-muted">RefID:</span> ';
        echo '<span class="npc-refid' . ($refid['known'] ? '' : ' npc-identity-unknown') . '" title="' . htmlspecialchars($refidTitle, ENT_QUOTES) . '">' . htmlspecialchars($refid['text']) . '</span>';
        if ($refid['runtime']) {
            echo ' <span class="npc-runtime-chip" title="FF RefIDs are assigned at runtime and can change between saves.">Runtime</span>';
        }
        echo '</div>';

        echo '<div class="npc-line npc-source-line"><span class="npc-muted">Source:</span> ';
        if (count($chain) > 1) {
            echo '<span class="npc-source-chip" tabindex="0" aria-describedby="' . htmlspecialchars($chainId, ENT_QUOTES) . '">'
                . htmlspecialchars($source) . '<span class="npc-source-more" aria-hidden="true">+' . (count($chain) - 1) . '</span></span>';
            echo '<span class="npc-source-chain" id="' . htmlspecialchars($chainId, ENT_QUOTES) . '" role="tooltip">';
            echo '<span class="npc-source-chain-title">Mod chain</span>';
            foreach ($chain as $index => $mod) {
                $role = $index === 0 ? 'defining' : ($index === count($chain) - 1 ? 'final override' : 'override');
                echo '<span class="npc-source-chain-entry"><span class="npc-source-chain-mod">' . htmlspecialchars($mod) . '</span>'
                    . '<span class="npc-source-chain-role">' . htmlspecialchars($role) . '</span></span>';
            }
            echo '</span>';
        } elseif ($source !== '') {
            echo '<span class="npc-source-single" title="' . htmlspecialchars($source, ENT_QUOTES) . '">' . htmlspecialchars($source) . '</span>';
        } else {
            echo '<span class="npc-source-single npc-identity-unknown">Unknown source</span>';
        }
        echo '</div>';
    }
}

// Helper: which rows share one merged profile. Resolved once per request from the batch map
// built below, so a card never issues its own lookup.
if (!function_exists('npc_profile_sharing_state')) {
    function npc_profile_sharing_state($sharing, $id){
        $rowId = (int)$id;
        $members = is_array($sharing) && isset($sharing['members']) && is_array($sharing['members']) ? $sharing['members'] : [];
        $owners = is_array($sharing) && isset($sharing['owners']) && is_array($sharing['owners']) ? $sharing['owners'] : [];
        $ownerId = (int)($members[$rowId] ?? 0);
        // A kept profile may or may not point at itself; either way it is the owner.
        if ($ownerId === $rowId) $ownerId = 0;
        $memberCount = (int)($owners[$rowId] ?? 0);
        if ($ownerId > 0) {
            return ['linked' => true, 'owner_id' => $ownerId, 'is_owner' => false, 'members' => 0];
        }
        if ($memberCount > 0) {
            return ['linked' => true, 'owner_id' => $rowId, 'is_owner' => true, 'members' => $memberCount];
        }
        return ['linked' => false, 'owner_id' => 0, 'is_owner' => false, 'members' => 0];
    }
}

// Helper: the "Shared profile" badge. Physical actor cards are never collapsed, so the badge is
// the only signal that two cards now read and write one profile.
if (!function_exists('render_npc_sharing_badge')) {
    function render_npc_sharing_badge(array $state){
        if (empty($state['linked'])) return;
        $detail = !empty($state['is_owner'])
            ? 'This profile is kept and shared with ' . (int)$state['members'] . ' other actor' . ((int)$state['members'] === 1 ? '' : 's') . '.'
            : 'This actor reads and writes the kept profile #' . (int)$state['owner_id'] . '.';
        echo '<span class="npc-shared-badge" title="' . htmlspecialchars($detail, ENT_QUOTES) . '">'
            . '<span aria-hidden="true">&#128279;</span> Shared profile'
            . '<span class="npc-sr-only">. ' . htmlspecialchars($detail) . '</span></span>';
    }
}

// Helper: the row action that opens the merge dialog. Offered whenever another profile carries the
// same visible name, and always for a row that is already sharing so Unlink stays reachable.
if (!function_exists('render_npc_merge_action')) {
    function render_npc_merge_action(array $row, array $state, $duplicateCount){
        if ((int)$duplicateCount < 2 && empty($state['linked'])) return;
        $name = trim((string)($row['npc_name'] ?? ''));
        if ($name === '') $name = 'this NPC';
        $title = !empty($state['linked'])
            ? 'Merge profiles - "' . $name . '" already shares a profile. Review or unlink it.'
            : 'Merge profiles - treat another actor named "' . $name . '" as the same character.';
        echo '<button type="button" class="btn btn-toggle npc-merge-btn' . (!empty($state['linked']) ? ' active' : '') . '"'
            . ' data-merge-id="' . htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES) . '"'
            . ' data-merge-linked="' . (!empty($state['linked']) ? '1' : '0') . '"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"'
            . ' aria-label="Merge profiles">'
            . '<span aria-hidden="true">&#128279;</span></button>';
    }
}

// Helper: the delete confirmation for a card. Cards can show several profiles that share one
// visible name, so the prompt names the exact row: name, normalised RefID and defining mod.
if (!function_exists('npc_delete_confirm_onclick')) {
    function npc_delete_confirm_onclick(array $row, $metadata = null){
        $name = trim((string)($row['npc_name'] ?? ''));
        if ($name === '') $name = 'this NPC';
        $refid = npc_refid_display($row['refid'] ?? '');
        $chain = npc_mod_chain($metadata);
        $source = npc_defining_mod($row, $chain, $metadata);

        $lines = ['Delete "' . $name . '"?', '', 'RefID: ' . $refid['text']];
        if ($source !== '') $lines[] = 'Defining mod: ' . $source;
        $lines[] = '';
        $lines[] = 'Only this profile is deleted. Other NPCs sharing this name are kept.';

        // JSON_HEX_* escapes every quote and angle bracket, so the literal is safe to drop
        // straight into the double-quoted onclick attribute.
        return 'return confirm(' . json_encode(
            implode("\n", $lines),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ');';
    }
}

// Helper: map gender text to an icon character
if (!function_exists('gender_icon_char')) {
    function gender_icon_char($gender){
        $g = strtolower(trim((string)$gender));
        if ($g === '') return '';
        if ($g === 'female' || $g === 'f' || $g === 'woman' || $g === 'girl') return '♀';
        if ($g === 'male' || $g === 'm' || $g === 'man' || $g === 'boy') return '♂';
        if ($g === 'nonbinary' || $g === 'non-binary' || $g === 'nb' || $g === 'enby' || $g === 'other' || $g === 'agender' || $g === 'genderfluid') return '⚧';
        return '';
    }
}

// Helper: map gender text to a CSS class suffix for coloring
if (!function_exists('gender_icon_class')) {
    function gender_icon_class($gender){
        $g = strtolower(trim((string)$gender));
        if ($g === 'female' || $g === 'f' || $g === 'woman' || $g === 'girl') return 'gender-female';
        if ($g === 'male' || $g === 'm' || $g === 'man' || $g === 'boy') return 'gender-male';
        if ($g === 'nonbinary' || $g === 'non-binary' || $g === 'nb' || $g === 'enby' || $g === 'other' || $g === 'agender' || $g === 'genderfluid') return 'gender-nb';
        return '';
    }
}

if (!function_exists('chimUiAutoLockProfileEnabled')) {
    function chimUiAutoLockProfileEnabled(): bool
    {
        if (!array_key_exists('AUTO_LOCK_PROFILE', $GLOBALS)) {
            return true;
        }
        $raw = $GLOBALS['AUTO_LOCK_PROFILE'];
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return ((int)$raw) !== 0;
        }
        if (is_string($raw)) {
            $value = strtolower(trim($raw));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }
        return !empty($raw);
    }
}

if (!function_exists('chimUiSaveAutoLockProfileSettingFromPost')) {
    function chimUiSaveAutoLockProfileSettingFromPost(): void
    {
        if (!array_key_exists('auto_lock_profile_present', $_POST)) {
            return;
        }

        $raw = $_POST['auto_lock_profile'] ?? '0';
        $enabled = in_array(strtolower(trim((string)$raw)), ['1', 'true', 'yes', 'on'], true);
        $description = 'When enabled, saving an NPC profile in CHIM NPC page automatically locks it to prevent history updates from overwriting manual edits.';
        if (!chimSetGeneralSetting('AUTO_LOCK_PROFILE', $enabled, $description)) {
            throw new RuntimeException('Failed to save Auto Lock Profile setting.');
        }

        $GLOBALS['AUTO_LOCK_PROFILE'] = $enabled;
    }
}

if (!function_exists('chimDecodeRelationshipSeedValue')) {
    function chimDecodeRelationshipSeedValue($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('chimMergeRelationshipSeedIntoExtendedData')) {
    function chimMergeRelationshipSeedIntoExtendedData(array &$data, $seedSource): void
    {
        $relationshipSeed = chimDecodeRelationshipSeedValue($seedSource);
        if (!is_array($relationshipSeed)) {
            return;
        }

        $extendedData = [];
        if (!empty($data['extended_data'])) {
            if (is_array($data['extended_data'])) {
                $extendedData = $data['extended_data'];
            } else {
                $decoded = json_decode((string)$data['extended_data'], true);
                if (is_array($decoded)) {
                    $extendedData = $decoded;
                }
            }
        }

        $extendedData['relationships'] = $relationshipSeed;
        $data['extended_data'] = json_encode($extendedData, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('chimNpcRelationshipSaveNeedsLock')) {
    function chimNpcRelationshipSaveNeedsLock(): bool
    {
        return isset($_POST['relationships_jsonb']) && trim((string)$_POST['relationships_jsonb']) !== '';
    }
}

if (!function_exists('chimMergeBackgroundLifeGoalsIntoPostedExtendedData')) {
    function chimMergeBackgroundLifeGoalsIntoPostedExtendedData(): void
    {
        if (!array_key_exists('background_life_goals', $_POST)) {
            return;
        }

        $extendedData = json_decode((string)($_POST['extended_data'] ?? '{}'), true);
        if (!is_array($extendedData)) {
            $extendedData = [];
        }

        $backgroundLifeGoals = trim((string)$_POST['background_life_goals']);
        if ($backgroundLifeGoals === '') {
            unset($extendedData['background_life_goals']);
        } else {
            $extendedData['background_life_goals'] = $backgroundLifeGoals;
        }

        $_POST['extended_data'] = json_encode($extendedData, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('chimAcquireNpcRelationshipLock')) {
    function chimAcquireNpcRelationshipLock($npcId): ?int
    {
        $npcId = (int)$npcId;
        if ($npcId <= 0 || !chimNpcRelationshipSaveNeedsLock()) {
            return null;
        }

        $ownerRow = $GLOBALS['db']->fetchOne("SELECT profile_owner_npc_id FROM core_npc_master WHERE id = {$npcId}");
        $lockId = 1001000000 + (int)($ownerRow['profile_owner_npc_id'] ?? $npcId);
        $GLOBALS['db']->execQuery("SELECT pg_advisory_lock({$lockId})");
        return $lockId;
    }
}

if (!function_exists('chimReleaseNpcRelationshipLock')) {
    function chimReleaseNpcRelationshipLock($lockId): void
    {
        if ($lockId === null) {
            return;
        }

        $GLOBALS['db']->execQuery("SELECT pg_advisory_unlock(" . (int)$lockId . ")");
    }
}

// sql::insert() (and therefore NpcMaster::create()) returns nothing, so the new row's id must be
// recovered by the md5(npc_name) key that create() itself writes.
if (!function_exists('chimResolveNpcIdAfterCreate')) {
    function chimResolveNpcIdAfterCreate($npcName): int
    {
        try {
            $row = $GLOBALS['db']->fetchOne(
                "SELECT id FROM core_npc_master WHERE md5 = '" . md5((string)$npcName) . "' ORDER BY id DESC LIMIT 1"
            );
            return (int)($row['id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

// RefID is read-only in management because Name + RefID is the profile lookup identity.
if (!function_exists('chimApplyStoredNpcIdentityToPost')) {
    function chimApplyStoredNpcIdentityToPost($npc, $id): void
    {
        $id = (int)$id;
        $existing = $id > 0 ? $npc->getById($id) : null;
        if (!is_array($existing)) {
            $_POST["md5"] = md5(trim((string)($_POST["npc_name"] ?? '')));
            return;
        }

        if (NpcMaster::isActorBound($existing)) {
            unset($_POST["refid"]);
        }
        if (chimNpcProfileBinding($existing) !== ':' &&
            ($_POST['_profile_binding'] ?? '') !== chimNpcProfileBinding($existing)) {
            throw new RuntimeException('Profile sharing changed. Reopen this NPC before saving.');
        }
        $_POST["md5"] = NpcMaster::identityMd5($existing, $_POST["npc_name"] ?? ($existing["npc_name"] ?? ''));
    }
}

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    if (chimUiAutoLockProfileEnabled()) {
        $_POST['lock_profile'] = 1;
    }
    chimMergeBackgroundLifeGoalsIntoPostedExtendedData();
    if (file_exists(__DIR__."/../../ext/relationship_system/npc_save_handler.php")) {
        include(__DIR__."/../../ext/relationship_system/npc_save_handler.php");
    }
    $npc->create($_POST);
    // Anchor editor-entered relationships to the last known game time. Freshly created rows have
    // gamets_last_updated NULL ("never saved"), so the init-time Dragon Break clear wiped manual
    // entries on the next reconnect. The stamp puts them on the timeline: same-save restarts keep
    // them, loading an OLDER save still clears them correctly (stamp is in that save's future).
    if (chimNpcRelationshipSaveNeedsLock() && function_exists('chimRelationshipTimelineStamp')) {
        $createdNpcId = chimResolveNpcIdAfterCreate($_POST["npc_name"] ?? '');
        if ($createdNpcId > 0) {
            chimRelationshipTimelineStamp($createdNpcId);
        }
    }
    header("Location: npc_master.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $relationshipLockId = chimAcquireNpcRelationshipLock($_POST["id"] ?? 0);
    try {
        if (chimUiAutoLockProfileEnabled()) {
            $_POST['lock_profile'] = 1;
        }
        chimMergeBackgroundLifeGoalsIntoPostedExtendedData();
        if (file_exists(__DIR__."/../../ext/relationship_system/npc_save_handler.php")) {
            include(__DIR__."/../../ext/relationship_system/npc_save_handler.php");
        }
        chimApplyStoredNpcIdentityToPost($npc, $_POST["id"] ?? 0);
        $saveNpc = function () use ($npc) {
            return $npc->update($_POST["id"], $_POST);
        };
        if (chimNpcRelationshipSaveNeedsLock()) {
            $saveResult = chimRunWithRelationshipExtendedDataWrite($saveNpc);
            // Anchor editor relationship saves to the last known game time (see Create branch) -
            // without this a NULL-stamped row loses its manual entries on the next reconnect.
            if ($saveResult !== false && function_exists('chimRelationshipTimelineStamp')) {
                $savedRow = $npc->getActorById((int)($_POST['id'] ?? 0));
                chimRelationshipTimelineStamp((int)($savedRow['profile_owner_npc_id'] ?? $savedRow['id']));
            }
        } else {
            $saveNpc();
        }
    } finally {
        chimReleaseNpcRelationshipLock($relationshipLockId);
    }
    header("Location: npc_master.php");
    exit;
}

// Inline update (AJAX) for modal save
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_npc"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $relationshipLockId = null;
    try {
        $id = intval($_POST['id'] ?? 0);
        $relationshipLockId = chimAcquireNpcRelationshipLock($id);
        chimMergeBackgroundLifeGoalsIntoPostedExtendedData();

        // Server-side: extended_data already has feature toggles synced by JS, just ensure it's valid JSON
        // The client-side JS only includes values that differ from profile defaults
        try {
            $postedExt = isset($_POST['extended_data']) ? (string)$_POST['extended_data'] : '';
            if ($postedExt !== '') {
                $tmp = json_decode($postedExt, true);
                if (!is_array($tmp)) {
                    $tmp = [];
                }
            } else {
                $tmp = [];
            }

            if (!empty($_POST["middle_term_enabled"])) { // If enabled on NPC form, but not present in extended_data
                $tmp["middle_term_enabled"] = 1;
            }

            if (!empty($_POST["individual_memory_enabled"])) {
                $tmp["individual_memory_enabled"] = 1;
            }

            // The UI exposes only the latest middle-term memory entry. Persist edits back into the
            // timestamp-keyed structure used by the middle-term generator, and allow clearing it.
            if (array_key_exists('middle_term_latest', $_POST)) {
                $editedMiddleTerm = trim((string)$_POST['middle_term_latest']);
                $middleTermMemory = [];

                if (isset($tmp['middle_term_memory']) && is_array($tmp['middle_term_memory'])) {
                    $middleTermMemory = $tmp['middle_term_memory'];
                }

                if (!empty($middleTermMemory)) {
                    $latestKey = array_key_last($middleTermMemory);
                    if ($latestKey !== null) {
                        if ($editedMiddleTerm === '') {
                            unset($middleTermMemory[$latestKey]);
                        } else {
                            $middleTermMemory[$latestKey] = $editedMiddleTerm;
                        }
                    }
                } elseif ($editedMiddleTerm !== '') {
                    // Preserve the generator's expected timestamp-keyed object shape without blocking
                    // future summaries. A synthetic 0 key behaves as "no previous summary" for regen.
                    $middleTermMemory = ['0' => $editedMiddleTerm];
                }

                if (!empty($middleTermMemory)) {
                    $tmp['middle_term_memory'] = $middleTermMemory;
                } else {
                    unset($tmp['middle_term_memory']);
                }

            }

            $_POST['extended_data'] = json_encode($tmp);
        } catch (Throwable $e) {
            $_POST['extended_data'] = '{}';
        }

        // Merge relationship editor data after all other extended_data processing.
        // The structured relationship editor must be the last writer for relationships.
        if (file_exists(__DIR__."/../../ext/relationship_system/npc_save_handler.php")) {
            include(__DIR__."/../../ext/relationship_system/npc_save_handler.php");
        }
        
        // Handle dynamic_profile: if empty string sent, set to NULL (inherit from profile)
        if (array_key_exists('dynamic_profile', $_POST)) {
            $dynVal = $_POST['dynamic_profile'];
            if ($dynVal === '' || $dynVal === null) {
                $_POST['dynamic_profile'] = null; // NULL means inherit from profile
            } else {
                $_POST['dynamic_profile'] = ($dynVal === '1' || $dynVal === 1 || $dynVal === true) ? 1 : 0;
            }
        }
        if (chimUiAutoLockProfileEnabled()) {
            $_POST['lock_profile'] = 1;
        }
        if ($id <= 0) {
            $npc->create($_POST);
            // NpcMaster::create() returns nothing (sql::insert is void), so the old `if (!$newId)`
            // check reported "Insert failed" on every SUCCESSFUL insert. Recover the real id.
            $newId = chimResolveNpcIdAfterCreate($_POST["npc_name"] ?? '');
            if ($newId <= 0) { echo json_encode(["ok"=>false, "error"=>"Insert failed"]); exit; }
            // Anchor editor relationship saves to the last known game time (see Create branch) -
            // NULL-stamped rows lose their manual entries to the init-time Dragon Break clear.
            if (chimNpcRelationshipSaveNeedsLock() && function_exists('chimRelationshipTimelineStamp')) {
                chimRelationshipTimelineStamp($newId);
            }
            echo json_encode(["ok"=>true, "id"=>$newId]);
        } else {
            chimApplyStoredNpcIdentityToPost($npc, $id);
            $saveNpc = function () use ($npc, $id) {
                return $npc->update($id, $_POST);
            };
            if (chimNpcRelationshipSaveNeedsLock()) {
                $ok = chimRunWithRelationshipExtendedDataWrite($saveNpc);
                // Stamp BEFORE the manual backup below so the snapshot captures the anchored row.
                if ($ok !== false && function_exists('chimRelationshipTimelineStamp')) {
                    $savedRow = $npc->getActorById($id);
                    chimRelationshipTimelineStamp((int)($savedRow['profile_owner_npc_id'] ?? $id));
                }
            } else {
                $ok = $saveNpc();
            }
            if ($ok !== false) {
                $savedRow = $npc->getActorById($id);
                $npc->backupNpcById((int)($savedRow['profile_owner_npc_id'] ?? $id));
            }
            if ($ok === false) {
                echo json_encode(["ok"=>false, "error"=>($npc->getLastError() ?? 'Update failed')]);
            } else {
                echo json_encode(["ok"=>true, "id"=>$id]);
            }
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    } finally {
        chimReleaseNpcRelationshipLock($relationshipLockId);
    }
    exit;
}

// Save global auto-lock preference (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["set_auto_lock_profile"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $_POST['auto_lock_profile_present'] = '1';
        chimUiSaveAutoLockProfileSettingFromPost();
        echo json_encode(["ok"=>true, "enabled"=>chimUiAutoLockProfileEnabled()]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Toggle favorite (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_favorite"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        $hasValue = array_key_exists('value', $_POST);
        if ($hasValue) {
            $v = ($_POST['value']==='1'||$_POST['value']===1||$_POST['value']===true)?1:0;
            $sql = "UPDATE core_npc_master SET npc_favorite = {$v} WHERE id = {$id} RETURNING npc_favorite";
        } else {
            $sql = "UPDATE core_npc_master SET npc_favorite = 1 - COALESCE(npc_favorite,0) WHERE id = {$id} RETURNING npc_favorite";
        }
        $row = $GLOBALS['db']->fetchOne($sql);
        $val = is_array($row) ? intval($row['npc_favorite'] ?? 0) : 0;
        echo json_encode(["ok"=>true, "favorite"=>$val]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Toggle lock (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_lock"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        $hasValue = array_key_exists('value', $_POST);
        if ($hasValue) {
            $v = ($_POST['value']==='1'||$_POST['value']===1||$_POST['value']===true)?1:0;
            $sql = "UPDATE core_npc_master SET lock_profile = {$v} WHERE id = {$id} RETURNING lock_profile";
        } else {
            $sql = "UPDATE core_npc_master SET lock_profile = 1 - COALESCE(lock_profile,0) WHERE id = {$id} RETURNING lock_profile";
        }
        $row = $GLOBALS['db']->fetchOne($sql);
        $val = is_array($row) ? intval($row['lock_profile'] ?? 0) : 0;
        echo json_encode(["ok"=>true, "locked"=>$val]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Bulk unlock all NPC profiles except The Narrator (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["bulk_unlock_npcs"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm !== 'Unlock') { echo json_encode(["ok"=>false, "error"=>"Confirmation text mismatch"]); exit; }

        $sql = "WITH upd AS (
                    UPDATE core_npc_master
                    SET lock_profile = 0
                    WHERE COALESCE(lock_profile, 0) = 1
                      AND id <> 1
                      AND trim(lower(npc_name)) <> 'the narrator'
                    RETURNING 1
                )
                SELECT COUNT(*) AS c FROM upd";
        $row = $GLOBALS['db']->fetchOne($sql);
        $unlocked = intval($row['c'] ?? 0);
        echo json_encode(["ok"=>true, "unlocked"=>$unlocked]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Bulk delete unlocked NPCs except The Narrator (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["bulk_delete_npcs"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm !== 'Delete') { echo json_encode(["ok"=>false, "error"=>"Confirmation text mismatch"]); exit; }
        // Delete all unlocked NPCs except The Narrator (by name or id=1)
        // Use trim and case-insensitive comparison for robustness, and ensure lock_profile is explicitly compared as integer
        $sql = "with del as (delete from core_npc_master c where (lock_profile is null or lock_profile = 0) and id <> 1 and trim(lower(npc_name)) <> 'the narrator' AND profile_owner_npc_id IS NULL AND NOT EXISTS (SELECT 1 FROM core_npc_master child WHERE child.profile_owner_npc_id = c.id) returning 1) select count(*) as c from del";
        $row = $GLOBALS['db']->fetchOne($sql);
        $deleted = intval($row['c'] ?? 0);
        echo json_encode(["ok"=>true, "deleted"=>$deleted]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Bulk switch NPC profile assignment by source profile (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["bulk_switch_profile"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm !== 'Switch') { echo json_encode(["ok"=>false, "error"=>"Confirmation text mismatch"]); exit; }

        $sourceProfileId = intval($_POST['source_profile_id'] ?? 0);
        $targetProfileId = intval($_POST['target_profile_id'] ?? 0);
        if ($sourceProfileId <= 0 || $targetProfileId <= 0) {
            echo json_encode(["ok"=>false, "error"=>"Invalid source or target profile"]);
            exit;
        }
        if ($sourceProfileId === $targetProfileId) {
            echo json_encode(["ok"=>false, "error"=>"Source and target profiles must be different"]);
            exit;
        }

        $includeLockedRaw = $_POST['include_locked'] ?? '';
        $includeLocked = (
            $includeLockedRaw === '1' ||
            $includeLockedRaw === 1 ||
            $includeLockedRaw === true ||
            $includeLockedRaw === 'true'
        );

        $sourceRow = $GLOBALS['db']->fetchOne("SELECT id, label FROM core_profiles WHERE id = {$sourceProfileId} LIMIT 1");
        $targetRow = $GLOBALS['db']->fetchOne("SELECT id, label FROM core_profiles WHERE id = {$targetProfileId} LIMIT 1");
        if (!is_array($sourceRow) || empty($sourceRow['id'])) {
            echo json_encode(["ok"=>false, "error"=>"Source profile not found"]);
            exit;
        }
        if (!is_array($targetRow) || empty($targetRow['id'])) {
            echo json_encode(["ok"=>false, "error"=>"Target profile not found"]);
            exit;
        }

        $baseWhere = "profile_owner_npc_id IS NULL AND profile_id = {$sourceProfileId} and id <> 1 and trim(lower(npc_name)) <> 'the narrator'";
        $countRow = $GLOBALS['db']->fetchOne("SELECT COUNT(*) AS c FROM core_npc_master WHERE {$baseWhere}");
        $totalMatched = intval($countRow['c'] ?? 0);

        $skippedLocked = 0;
        if (!$includeLocked) {
            $skippedRow = $GLOBALS['db']->fetchOne("SELECT COUNT(*) AS c FROM core_npc_master WHERE {$baseWhere} AND COALESCE(lock_profile,0)=1");
            $skippedLocked = intval($skippedRow['c'] ?? 0);
        }

        $lockClause = $includeLocked ? "1=1" : "COALESCE(lock_profile,0)=0";
        $sql = "WITH upd AS (
                    UPDATE core_npc_master
                    SET profile_id = {$targetProfileId}
                    WHERE {$baseWhere}
                      AND {$lockClause}
                    RETURNING 1
                )
                SELECT COUNT(*) AS c FROM upd";
        $row = $GLOBALS['db']->fetchOne($sql);
        $updated = intval($row['c'] ?? 0);

        echo json_encode([
            "ok" => true,
            "updated" => $updated,
            "total_matched" => $totalMatched,
            "skipped_locked" => $skippedLocked,
            "include_locked" => $includeLocked,
            "source_profile_id" => $sourceProfileId,
            "target_profile_id" => $targetProfileId,
            "source_profile_label" => (string)($sourceRow['label'] ?? ('Profile #'.$sourceProfileId)),
            "target_profile_label" => (string)($targetRow['label'] ?? ('Profile #'.$targetProfileId)),
        ]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Set portrait from gallery (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["set_portrait"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        $sourceUrl = (string)($_POST['source'] ?? '');
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        if ($sourceUrl === '') { echo json_encode(["ok"=>false, "error"=>"Missing source"]); exit; }

        $row = $npc->getById($id);
        if (!$row) { echo json_encode(["ok"=>false, "error"=>"NPC not found"]); exit; }

        $name = (string)($row['npc_name'] ?? '');
        $md5 = (string)($row['md5'] ?? '');
        if ($md5 === '' && $name !== '') { $md5 = md5($name); }
        $refid = strtoupper((string)($row['refid'] ?? ''));

        // Map web URL to filesystem path under gallery root
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        $webPrefix = rtrim($webRoot, '/');
        $galleryWeb = $webPrefix . '/data/pictures/gallery/';
        if (strpos($path, $galleryWeb) !== 0) { echo json_encode(["ok"=>false, "error"=>"Source not in gallery"]); exit; }
        $rel = substr($path, strlen($galleryWeb));
        $rel = str_replace(['\\'], '/', $rel);
        $rel = ltrim($rel, '/');
        $galleryRoot = realpath($GLOBALS["ENGINE_PATH"] . '/data/pictures/gallery');
        if ($galleryRoot === false) { echo json_encode(["ok"=>false, "error"=>"Gallery root missing"]); exit; }
        $srcFs = realpath($galleryRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel));
        if ($srcFs === false || strpos($srcFs, $galleryRoot) !== 0) { echo json_encode(["ok"=>false, "error"=>"Invalid source path"]); exit; }

        $ext = strtolower(pathinfo($srcFs, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed, true)) { echo json_encode(["ok"=>false, "error"=>"Unsupported format"]); exit; }

        $profileRoot = rtrim($GLOBALS["ENGINE_PATH"] . '/data/pictures/profile/', '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($profileRoot)) { @mkdir($profileRoot, 0775, true); }
        $base = $md5 !== '' ? $md5 : ($refid !== '' ? $refid : preg_replace('/[^a-z0-9_-]+/i','_', (string)$name));
        if ($base === '') { echo json_encode(["ok"=>false, "error"=>"Cannot determine filename"]); exit; }
        $dstFs = $profileRoot . $base . '.' . $ext;

        if (!@copy($srcFs, $dstFs)) { echo json_encode(["ok"=>false, "error"=>"Copy failed"]); exit; }

        // Update metadata.portrait to relative path under data/pictures
        $portraitRel = 'profile/' . $base . '.' . $ext;
        $meta = [];
        if (!empty($row['metadata'])) {
            $tmp = json_decode((string)$row['metadata'], true);
            if (is_array($tmp)) { $meta = $tmp; }
        }
        $meta['portrait'] = $portraitRel;
        $npc->update($id, ['metadata' => json_encode($meta)]);

        $url = rtrim($webRoot, '/') . '/data/pictures/' . str_replace('%2F','/', rawurlencode($portraitRel));
        echo json_encode(["ok"=>true, "url"=>$url, "portrait"=>$portraitRel]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["dev_visit"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['target_id'] ?? 0);
        require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "scriptproxy_papyrus.php");
        $npcData=$npc->getById($id);
        if (isset($npcData['refid']) && $npcData['refid']!=='') {
            $refid = $npcData['refid'];
            $skyrimCmd = new SkyrimCommandBuilder();
            $json = $skyrimCmd->ObjectReference->MoveTo("0x00000014","0x".$refid);
            $skyrimCmd->send(cmd: $json);
            echo json_encode(["ok"=>true]);
        } else {
            echo json_encode(["ok"=>false, "error"=>"NPC <{$id}> does not have a valid refid"]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["bgl_inception"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['target_id'] ?? 0);
        $npcData = $id > 0 ? $npc->getById($id) : null;
        if (!is_array($npcData)) {
            echo json_encode(["ok"=>false, "error"=>"NPC <{$id}> was not found"]);
            exit;
        }
        // Row-scoped: same-named actors keep separate profiles, so the thought must not fan out
        // to every row sharing this NPC's name.
        if (!$npc->updateExtendedKeysById($id, ['bgl_inception' => (string)($_POST['idea'] ?? '')])) {
            echo json_encode(["ok"=>false, "error"=>"Background Life thought could not be saved"]);
            exit;
        }
        echo json_encode(["ok"=>true]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["dev_teleport"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['target_id'] ?? 0);
        require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "scriptproxy_papyrus.php");
        $npcData=$npc->getById($id);
        if (isset($npcData['refid']) && $npcData['refid']!=='') {
            $refid = $npcData['refid'];
            $skyrimCmd = new SkyrimCommandBuilder();
            $json = $skyrimCmd->ObjectReference->MoveTo("0x".$refid,"0x00000014");
            $skyrimCmd->send(cmd: $json);
            echo json_encode(["ok"=>true]);
        } else {
            echo json_encode(["ok"=>false, "error"=>"NPC <{$id}> does not have a valid refid"]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}


// Handle Delete
if (isset($_GET["delete"])) {
    $toDel = intval($_GET["delete"]);
    $rowCheck = $npc->getById($toDel);
    
    if ($rowCheck && !empty($rowCheck['lock_profile'])) {
        header("Location: npc_master.php"); 
        exit; 
    }
    
    $npc->delete($toDel);
    header("Location: npc_master.php");
    exit;
}

// Handle Export NPC (download JSON)
if (isset($_GET["export"]) && is_numeric($_GET["export"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    
    $exportId = intval($_GET["export"]);
    $exportRow = $npc->getById($exportId);
    
    if (!$exportRow) {
        header("HTTP/1.1 404 Not Found");
        echo "NPC not found";
        exit;
    }
    
    // Build export data
    $exportData = [
        'export_version' => '1.0',
        'export_date' => date('c'),
        'npc_name' => $exportRow['npc_name'] ?? '',
        'npc_favorite' => !empty($exportRow['npc_favorite']) ? 1 : 0,
        'lock_profile' => !empty($exportRow['lock_profile']) ? 1 : 0,
        'prompt_head' => $exportRow['prompt_head'] ?? '',
        'npc_static_bio' => $exportRow['npc_static_bio'] ?? '',
        'oghma_knowledge_tags' => $exportRow['oghma_knowledge_tags'] ?? '',
        'emote_moods' => $exportRow['emote_moods'] ?? '',
        'personality' => $exportRow['personality'] ?? '',
        'occupation' => $exportRow['occupation'] ?? '',
        'appearance' => $exportRow['appearance'] ?? '',
        'skills' => $exportRow['skills'] ?? '',
        'speechstyle' => $exportRow['speechstyle'] ?? '',
        'goals' => $exportRow['goals'] ?? '',
        'voiceid' => $exportRow['voiceid'] ?? '',
        'gender' => $exportRow['gender'] ?? '',
        'race' => $exportRow['race'] ?? '',
        'dynamic_profile' => $exportRow['dynamic_profile'] ?? null,
        'base' => $exportRow['base'] ?? '',
        'core' => $exportRow['core'] ?? '',
        'tags' => $exportRow['tags'] ?? '',
        'metadata' => null,
        'extended_data' => null,
    ];
    
    // Parse JSON fields
    if (!empty($exportRow['metadata'])) {
        $tmp = json_decode((string)$exportRow['metadata'], true);
        if (is_array($tmp)) { $exportData['metadata'] = $tmp; }
    }
    if (!empty($exportRow['extended_data'])) {
        $tmp = json_decode((string)$exportRow['extended_data'], true);
        if (is_array($tmp)) { $exportData['extended_data'] = $tmp; }
    }
    
    $filename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($exportRow['npc_name'] ?? 'npc')) . '_export.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle Import NPC (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import_npc"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    
    try {
        $importJson = $_POST['import_data'] ?? '';
        $targetId = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;
        $newName = trim($_POST['new_name'] ?? '');
        
        $importData = json_decode($importJson, true);
        if (!is_array($importData)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON data']);
            exit;
        }
        
        // Build NPC data from import
        $npcData = [];
        $allowedFields = ['npc_favorite', 'lock_profile', 'prompt_head', 'npc_static_bio', 
            'oghma_knowledge_tags', 'emote_moods', 'personality',
            'occupation', 'appearance', 'skills', 'speechstyle', 'goals', 'voiceid',
            'gender', 'race', 'dynamic_profile', 'base', 'core', 'tags'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $importData)) {
                $npcData[$field] = $importData[$field];
            }
        }
        
        // Handle JSON fields
        if (isset($importData['metadata']) && is_array($importData['metadata'])) {
            $npcData['metadata'] = json_encode($importData['metadata']);
        }
        if (isset($importData['extended_data']) && is_array($importData['extended_data'])) {
            $npcData['extended_data'] = json_encode($importData['extended_data']);
        }
        chimMergeRelationshipSeedIntoExtendedData($npcData, $importData['relationships'] ?? null);
        
        if ($targetId > 0) {
            // Import to existing NPC
            $existingNpc = $npc->getById($targetId);
            if (!$existingNpc) {
                echo json_encode(['ok' => false, 'error' => 'Target NPC not found']);
                exit;
            }
            
            // Don't overwrite the name when importing to existing NPC
            unset($npcData['npc_name']);
            
            $npc->update($targetId, $npcData);
            echo json_encode(['ok' => true, 'message' => 'Biography imported to existing NPC', 'id' => $targetId]);
        } else {
            // Create new NPC
            if ($newName !== '') {
                $npcData['npc_name'] = $newName;
            } elseif (!empty($importData['npc_name'])) {
                $npcData['npc_name'] = $importData['npc_name'];
            } else {
                echo json_encode(['ok' => false, 'error' => 'NPC name is required']);
                exit;
            }
            
            // Check if name already exists
            $existingByName = $npc->getByName($npcData['npc_name']);
            if ($existingByName) {
                echo json_encode(['ok' => false, 'error' => 'An NPC with this name already exists. Use "Import to Existing" option instead.']);
                exit;
            }
            
            $npcData['md5'] = md5($npcData['npc_name']);
            $newId = $npc->create($npcData);
            echo json_encode(['ok' => true, 'message' => 'New NPC created from import', 'id' => $newId]);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch Data
$perPage = 12;
$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
if ($page < 1) $page = 1;

// Filters and sorting
$q = trim($_GET['q'] ?? '');
$alpha = strtolower($_GET['alpha'] ?? 'asc');
if (!in_array($alpha, ['asc','desc'], true)) { $alpha = 'asc'; }
$nameLetterFilter = strtoupper(trim((string)($_GET['letter'] ?? '')));
if (!preg_match('/^[A-Z]$/', $nameLetterFilter)) { $nameLetterFilter = ''; }
$profileIdFilter = isset($_GET['profile_id']) ? trim((string)$_GET['profile_id']) : '';
// New: checkbox filters
$favOnly = (isset($_GET['fav']) && $_GET['fav'] === '1');
$dynOnly = (isset($_GET['dyn']) && $_GET['dyn'] === '1');
$mtmOnly = (isset($_GET['mtm']) && $_GET['mtm'] === '1');
$lockOnly = (isset($_GET['lock']) && $_GET['lock'] === '1');
$salOnly = (isset($_GET['sal']) && $_GET['sal'] === '1');
$blcOnly = (isset($_GET['blc']) && $_GET['blc'] === '1');
$gpsOnly = (isset($_GET['gps']) && $_GET['gps'] === '1');
$createdOnly = (isset($_GET['created']) && $_GET['created'] === '1');

// Preload profiles for filter dropdown
$profileRows = $GLOBALS["db"]->fetchAll("SELECT id, label, metadata FROM core_profiles ORDER BY label ASC");
// Default to first profile id for new NPCs
$firstProfileId = '';
if (is_array($profileRows) && count($profileRows) > 0) {
    $firstProfileId = (string)($profileRows[0]['id'] ?? '');
}
// Preload profile connector mappings and LLM connector labels for modal summary
$profileConnRows = $GLOBALS["db"]->fetchAll("SELECT id, llm_primary_id, llm_secondary_id, llm_tertiary_id, llm_quaternary_id, llm_formatter_id, diary_connector_id, metadata FROM core_profiles ORDER BY id ASC");
$llmRows = $GLOBALS["db"]->fetchAll("SELECT id, COALESCE(NULLIF(label,''), model) AS label FROM core_llm_connector ORDER BY LOWER(COALESCE(NULLIF(label,''), model)) ASC");
$profilesById = [];
foreach (($profileRows ?? []) as $pr) {
    $pid = (string)($pr['id'] ?? '');
    if ($pid !== '') $profilesById[$pid] = $pr['label'] ?? ('Profile #'.$pid);
}
$profileOptions = [];
foreach (($profileRows ?? []) as $pr) {
    $pid = (string)($pr['id'] ?? '');
    if ($pid === '') continue;
    $profileOptions[] = [
        'id' => $pid,
        'label' => (string)($pr['label'] ?? ('Profile #'.$pid)),
    ];
}
// Build profile metadata lookup for inherited settings
$profileMetaById = [];
$profilePromptHeadsById = [];
$globalPromptHead = (string)($GLOBALS['PROMPT_HEAD'] ?? '');
foreach (($profileConnRows ?? []) as $prow) {
    $pid = (string)($prow['id'] ?? '');
    if ($pid === '') continue;
    $pmeta = [];
    try {
        if (!empty($prow['metadata'])) {
            $tmp = json_decode((string)$prow['metadata'], true);
            if (is_array($tmp)) $pmeta = $tmp;
        }
    } catch (Throwable $e) {}
    // Check for both string "1" and boolean true
    $dynVal = isset($pmeta['DYNAMIC_PROFILE_ENABLED']) ? $pmeta['DYNAMIC_PROFILE_ENABLED'] : null;
    $mtmVal = isset($pmeta['MIDDLE_TERM_MEMORY_ENABLED']) ? $pmeta['MIDDLE_TERM_MEMORY_ENABLED'] : null;
    $adVal = isset($pmeta['AUTO_DIARY_ENABLED']) ? $pmeta['AUTO_DIARY_ENABLED'] : null;
    $adWaitVal = isset($pmeta['AUTO_DIARY_WAIT_ENABLED']) ? $pmeta['AUTO_DIARY_WAIT_ENABLED'] : null;
    $salVal = isset($pmeta['SALUTATION_AFTER_A_WHILE']) ? $pmeta['SALUTATION_AFTER_A_WHILE'] : null;
    $blcVal = isset($pmeta['BACKGROUND_LIFE_COMMANDS']) ? $pmeta['BACKGROUND_LIFE_COMMANDS'] : null;
    $gpsVal = isset($pmeta['GPS_TRACK']) ? $pmeta['GPS_TRACK'] : null;
    $profilePromptHead = isset($pmeta['PROMPT_HEAD']) && is_scalar($pmeta['PROMPT_HEAD'])
        ? (string)$pmeta['PROMPT_HEAD']
        : '';
    $profilePromptHeadsById[$pid] = $profilePromptHead !== '' ? $profilePromptHead : $globalPromptHead;
    
    $profileMetaById[$pid] = [
        'dyn' => ($dynVal === '1' || $dynVal === 1 || $dynVal === true),
        'mtm' => ($mtmVal === '1' || $mtmVal === 1 || $mtmVal === true),
        'ad' => ($adVal === '1' || $adVal === 1 || $adVal === true),
        'adWait' => ($adWaitVal === '1' || $adWaitVal === 1 || $adWaitVal === true),
        'sal' => ($salVal === '1' || $salVal === 1 || $salVal === true),
        'blc' => ($blcVal === '1' || $blcVal === 1 || $blcVal === true),
        'gps' => ($gpsVal === '1' || $gpsVal === 1 || $gpsVal === true)
    ];
}
$profilesConnById = [];
foreach (($profileConnRows ?? []) as $prc) {
    $pid = (string)($prc['id'] ?? '');
    if ($pid !== '') $profilesConnById[$pid] = $prc;
}
$llmById = [];
foreach (($llmRows ?? []) as $lr) {
    $lid = (string)($lr['id'] ?? '');
    if ($lid !== '') $llmById[$lid] = $lr['label'] ?? ('Connector #'.$lid);
}

// Filters describe the effective character, while identity/favorite/lock filters remain physical.
$sharedFilterColumns = [];
foreach (['profile_id', 'voiceid', 'tags', 'dynamic_profile', 'extended_data'] as $column) {
    $sharedFilterColumns[$column] = "(CASE WHEN profile_owner_npc_id IS NULL THEN {$column} ELSE "
        . "(SELECT owner.{$column} FROM core_npc_master owner WHERE owner.id = core_npc_master.profile_owner_npc_id) END)";
}
$where = "1=1";
if ($q !== ''){
    $qEsc = "%".$GLOBALS['db']->escape($q)."%";
    // Match by name primarily; include a few related fields
    $clauses = [
        "npc_name ilike '".$qEsc."'",
        "coalesce(race,'') ilike '".$qEsc."'",
        "coalesce({$sharedFilterColumns['voiceid']},'') ilike '".$qEsc."'",
        "coalesce(refid,'') ilike '".$qEsc."'",
        "coalesce({$sharedFilterColumns['tags']},'') ilike '".$qEsc."'",
    ];
    // Same-named actors are told apart by RefID, so accept it typed with or without 0x
    $qRefid = preg_replace('/^0x/i', '', $q);
    if ($qRefid !== '' && $qRefid !== $q) {
        $clauses[] = "coalesce(refid,'') ilike '%".$GLOBALS['db']->escape($qRefid)."%'";
    }
    // ...and by the mods that define or override them (metadata is jsonb)
    $clauses[] = "exists (select 1 from jsonb_array_elements_text("
        ."case when jsonb_typeof(metadata->'mods') = 'array' then metadata->'mods' else '[]'::jsonb end"
        .") as chain_mod where chain_mod ilike '".$qEsc."')";
    $where .= " and (".implode(' or ', $clauses).")";
}
if ($nameLetterFilter !== '') {
    $letterEsc = $GLOBALS['db']->escape(strtolower($nameLetterFilter));
    $where .= " and lower(npc_name) like '".$letterEsc."%'";
}
if ($profileIdFilter !== ''){
    $where .= " and {$sharedFilterColumns['profile_id']} = ".intval($profileIdFilter);
}
// Apply favorites/dynamic/middle-term filters when checked
if ($favOnly) {
    $where .= " and coalesce(npc_favorite,0)=1";
}
if ($dynOnly) {
    $where .= " and coalesce({$sharedFilterColumns['dynamic_profile']},0)=1";
}
if ($mtmOnly) {
    // Robust match on JSON/text; tolerates whitespace and works for json/jsonb
    $where .= " and coalesce({$sharedFilterColumns['extended_data']}::text,'') ~ '\"middle_term_enabled\"\\s*:\\s*(true|1)'";
}
if ($lockOnly) {
    $where .= " and coalesce(lock_profile,0)=1";
}
if ($salOnly) {
    $where .= " and coalesce({$sharedFilterColumns['extended_data']}::text,'') ~ '\"salutation_after_a_while\"\\s*:\\s*(true|1)'";
}
if ($blcOnly) {
    $where .= " and coalesce(extended_data::text,'') ~ '\"background_life_commands\"\\s*:\\s*(true|1)'";
}
if ($gpsOnly) {
    $where .= " and coalesce(metadata::text,'') ~ '\"gps_track\"\\s*:\\s*(true|1)'";
}
if ($createdOnly) {
    $where .= " and coalesce(metadata::text,'') ~ '\"background_life_created\"\\s*:\\s*(true|1)'";
}

// Default: The Narrator first, then favorites, then alphabetical by name
$order = "order by (case when npc_name = 'The Narrator' then 0 else 1 end), coalesce(npc_favorite,0) desc, coalesce(gamets_last_updated,0) desc, lower(npc_name) ".$alpha.", id asc";

// Count with filters
$rowCountRow = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM core_npc_master where {$where}");
$totalRows = intval($rowCountRow['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
error_log("{$where} {$order} limit {$perPage} offset {$offset}");
$data = $npc->getAll("{$where} {$order} limit {$perPage} offset {$offset}");
$data = array_map('chimNpcEffectiveProfile', $data ?: []);

// Same-name profile counts across the whole table, so the badge is not page-local.
$npcNameCounts = [];
try {
    $dupRows = $GLOBALS["db"]->fetchAll("SELECT lower(npc_name) AS name_key, COUNT(*) AS total FROM core_npc_master GROUP BY 1 HAVING COUNT(*) > 1");
    foreach (($dupRows ?: []) as $dupRow) {
        $nameKey = (string)($dupRow['name_key'] ?? '');
        if ($nameKey !== '') $npcNameCounts[$nameKey] = (int)($dupRow['total'] ?? 0);
    }
} catch (Throwable $e) {
    $npcNameCounts = [];
}

// Profile sharing map for every row, resolved in one statement so a card never calls the merge API.
// The owner column belongs to the NPC API; a missing column just means no card is sharing yet.
$npcProfileSharing = ['members' => [], 'owners' => []];
try {
    $shareRows = $GLOBALS["db"]->fetchAll("SELECT id, profile_owner_npc_id FROM core_npc_master WHERE profile_owner_npc_id IS NOT NULL");
    foreach (($shareRows ?: []) as $shareRow) {
        $memberId = (int)($shareRow['id'] ?? 0);
        $ownerId = (int)($shareRow['profile_owner_npc_id'] ?? 0);
        if ($memberId <= 0 || $ownerId <= 0 || $memberId === $ownerId) continue;
        $npcProfileSharing['members'][$memberId] = $ownerId;
        $npcProfileSharing['owners'][$ownerId] = (int)($npcProfileSharing['owners'][$ownerId] ?? 0) + 1;
    }
} catch (Throwable $e) {
    $npcProfileSharing = ['members' => [], 'owners' => []];
}

$editItem = null;

if (!function_exists('renderNpcLetterFilter')) {
    function renderNpcLetterFilter($selectedLetter = '')
    {
        $selectedLetter = strtoupper(trim((string)$selectedLetter));
        if (!preg_match('/^[A-Z]$/', $selectedLetter)) {
            $selectedLetter = '';
        }
        echo '<div class="npc-letter-filter">';
        echo '<input type="hidden" id="npc_letter_filter" value="'.htmlspecialchars($selectedLetter, ENT_QUOTES).'" />';
        echo '<button type="button" class="npc-letter-btn'.($selectedLetter === '' ? ' active' : '').'" data-letter="">All</button>';
        foreach (range('A', 'Z') as $char) {
            $active = ($selectedLetter === $char) ? ' active' : '';
            echo '<button type="button" class="npc-letter-btn'.$active.'" data-letter="'.htmlspecialchars($char, ENT_QUOTES).'">'.htmlspecialchars($char).'</button>';
        }
        echo '</div>';
    }
}

if (!function_exists('renderNpcToolbar')) {
    function renderNpcToolbar($args = [])
    {
        $top = !empty($args['top']);
        $suffix = $top ? '_top' : '';
        $q = (string)($args['q'] ?? '');
        $profileRows = is_array($args['profileRows'] ?? null) ? $args['profileRows'] : [];
        $profileIdFilter = (string)($args['profileIdFilter'] ?? '');
        $page = max(1, (int)($args['page'] ?? 1));
        $totalPages = max(1, (int)($args['totalPages'] ?? 1));
        $totalRows = max(0, (int)($args['totalRows'] ?? 0));
        $nameLetterFilter = (string)($args['nameLetterFilter'] ?? '');
        $favOnly = !empty($args['favOnly']);
        $dynOnly = !empty($args['dynOnly']);
        $mtmOnly = !empty($args['mtmOnly']);
        $lockOnly = !empty($args['lockOnly']);
        $salOnly = !empty($args['salOnly']);
        $blcOnly = !empty($args['blcOnly']);
        $gpsOnly = !empty($args['gpsOnly']);
        $pageWindow = min(10, $totalPages);
        $pageStart = max(1, min($page - 4, $totalPages - $pageWindow + 1));
        $pageEnd = min($totalPages, $pageStart + $pageWindow - 1);

        ?>
        <div class="pagination npc-toolbar" data-current-page="<?= (int)$page ?>" data-total-pages="<?= (int)$totalPages ?>">
          <div class="npc-toolbar-main">
            <div class="npc-toolbar-actions">
              <button id="npc_create_btn" type="button" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-action">+ Create NPC</button>
              <button id="npc_import_btn" type="button" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-action" title="Import NPC from JSON file">📥 Import NPC</button>
              <button id="rel_bulk_build_btn" type="button" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-action" title="Build JSONB relationships from Oghma text data for all NPCs">🔗 Build Relationships</button>
              <button id="npc_bulk_switch_profile_btn" type="button" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-action npc-toolbar-btn-switch" title="Switch all NPCs from one profile to another">🔀 Mass Switch Profile</button>
              <button id="npc_bulk_unlock_btn" type="button" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-action" title="Unlock every NPC profile except The Narrator">🔓 Unlock All Profiles</button>
              <button id="npc_bulk_delete_btn" type="button" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-danger" title="Delete all unlocked NPCs (excludes The Narrator and locked)">❌ Delete All Profiles</button>
            </div>
            <div class="npc-toolbar-tools">
              <input id="npc_search" type="text" placeholder="Search..." value="<?= htmlspecialchars($q) ?>" />
              <select id="npc_profile_filter" title="Filter by profile">
                <option value="">All Profiles</option>
                <?php foreach ($profileRows as $pr): ?>
                  <?php $pid = (string)($pr['id'] ?? ''); $lbl = $pr['label'] ?? ('Profile #' . $pid); ?>
                  <option value="<?= htmlspecialchars($pid) ?>" <?= ($profileIdFilter !== '' && $profileIdFilter === $pid) ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="npc-toolbar-subrow">
            <div class="npc-toolbar-pager">
              <button type="button" class="npc-letter-btn npc-page-link<?= $page <= 1 ? ' disabled' : '' ?>" data-page="1" <?= $page <= 1 ? 'disabled aria-disabled="true"' : '' ?>>First</button>
              <button type="button" class="npc-letter-btn npc-page-link<?= $page <= 1 ? ' disabled' : '' ?>" data-page="<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'disabled aria-disabled="true"' : '' ?>>Prev</button>
              <?php for ($p = $pageStart; $p <= $pageEnd; $p++): ?>
                <button type="button" class="npc-letter-btn npc-page-link<?= $p === $page ? ' active' : '' ?>" data-page="<?= $p ?>" <?= $p === $page ? 'disabled aria-current="page"' : '' ?>><?= $p ?></button>
              <?php endfor; ?>
              <button type="button" class="npc-letter-btn npc-page-link<?= $page >= $totalPages ? ' disabled' : '' ?>" data-page="<?= min($totalPages, $page + 1) ?>" <?= $page >= $totalPages ? 'disabled aria-disabled="true"' : '' ?>>Next</button>
              <button type="button" class="npc-letter-btn npc-page-link<?= $page >= $totalPages ? ' disabled' : '' ?>" data-page="<?= $totalPages ?>" <?= $page >= $totalPages ? 'disabled aria-disabled="true"' : '' ?>>Last</button>
              <div class="npc-page-indicator" title="Current page"><?= $page ?>/<?= $totalPages ?></div>
            </div>
          </div>
          <div class="npc-toolbar-letter-row">
            <?php renderNpcLetterFilter($nameLetterFilter); ?>
            <label class="npc-auto-lock-profile" title="When enabled, saving an NPC profile automatically locks it to prevent history updates from overwriting manual edits.">
              <input id="npc_auto_lock_profile" type="checkbox" <?= chimUiAutoLockProfileEnabled() ? 'checked' : '' ?>>
              Auto Lock Profiles on Edit
            </label>
            <div class="npc-toolbar-summary">
              <div class="npc-filter-dropdown">
                <button type="button" id="npc_filter_btn<?= $suffix ?>" class="npc-toolbar-btn npc-toolbar-btn-uniform npc-toolbar-btn-action npc-toolbar-filter-btn" title="Filters" aria-label="Filters">▾ Filters</button>
                <div id="npc_filter_menu<?= $suffix ?>" class="npc-filter-menu" style="display:none;">
                  <label><input type="checkbox" id="npc_filter_fav<?= $suffix ?>" <?= $favOnly ? 'checked' : '' ?>> ⭐ Favorites</label>
                  <label><input type="checkbox" id="npc_filter_dyn<?= $suffix ?>" <?= $dynOnly ? 'checked' : '' ?>> ♻️ Dynamic profile</label>
                  <label><input type="checkbox" id="npc_filter_mtm<?= $suffix ?>" <?= $mtmOnly ? 'checked' : '' ?>> 📃 Middle-term memory</label>
                  <label><input type="checkbox" id="npc_filter_lock<?= $suffix ?>" <?= $lockOnly ? 'checked' : '' ?>> 🔒 Locked</label>
                  <label><input type="checkbox" id="npc_filter_sal<?= $suffix ?>" <?= $salOnly ? 'checked' : '' ?>> 👋 Auto Greeting</label>
                  <label><input type="checkbox" id="npc_filter_blc<?= $suffix ?>" <?= $blcOnly ? 'checked' : '' ?>> 🎮 BGL: Auto Actions</label>
                  <label><input type="checkbox" id="npc_filter_gps<?= $suffix ?>" <?= $gpsOnly ? 'checked' : '' ?>> 📍 BGL: GPS track</label>
                  <label><input type="checkbox" id="npc_filter_created<?= $suffix ?>" <?= $createdOnly ? 'checked' : '' ?>> 🧬 Created NPCs</label>
                </div>
              </div>
              <div class="npc-total-pill" title="Total NPC profiles">
                <div class="npc-total-pill-icon">👥</div>
                <div class="npc-total-pill-value"><?= $totalRows ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php
    }
}

if (isset($_GET["edit"])) {
    $editItem = $npc->getById($_GET["edit"]);

    if (!$editItem && isset($_GET['partial']) && $_GET['partial'] === '1') {
        try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>NPC profile unavailable</title>
            <style>
                html, body { margin:0; min-height:100%; background:#222; color:#e9efff; font-family:Arial, sans-serif; }
                main { min-height:65vh; display:flex; align-items:center; justify-content:center; padding:24px; text-align:center; }
                h2 { margin:0 0 8px; color:#f27c11; }
                p { margin:0; color:#cfd9ea; line-height:1.45; }
            </style>
        </head>
        <body>
            <main data-npc-load-error="1">
                <div>
                    <h2>NPC profile no longer exists</h2>
                    <p>The NPC list changed after this page loaded. Refresh the list and try again.</p>
                </div>
            </main>
        </body>
        </html>
        <?php
        exit;
    }
}

$npcRelationshipChanges = [];
if (is_array($editItem) && intval($editItem['id'] ?? 0) > 0) {
    try {
        $npcRelationshipChanges = chimFetchRecentRelationshipHistoryChanges(
            $GLOBALS['db'],
            10,
            intval($editItem['id'])
        );
    } catch (Throwable $e) {
        error_log('Unable to load NPC relationship change history: ' . $e->getMessage());
    }
}

// Partial list renderer for AJAX refresh of grid and pagination
if (isset($_GET['list']) && $_GET['list'] === '1') {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: text/html; charset=utf-8');
    renderNpcToolbar([
        'top' => false,
        'q' => $q,
        'profileRows' => $profileRows ?? [],
        'profileIdFilter' => $profileIdFilter,
        'page' => $page,
        'totalPages' => $totalPages,
        'totalRows' => $totalRows,
        'nameLetterFilter' => $nameLetterFilter,
        'favOnly' => $favOnly,
        'dynOnly' => $dynOnly,
        'mtmOnly' => $mtmOnly,
        'lockOnly' => $lockOnly,
        'salOnly' => $salOnly,
        'blcOnly' => $blcOnly,
        'gpsOnly' => $gpsOnly,
    ]);
    ?>
    <div class="npc-grid">
    <?php foreach ($data as $row): ?>
        <?php 
        $pid = (string)($row['profile_id'] ?? ''); 
        $profLabel = $profilesById[$pid] ?? ''; 
        $metaTmp = []; 
        if (!empty($row['metadata'])) { 
            $tmp = json_decode((string)$row['metadata'], true); 
            if (is_array($tmp)) { $metaTmp = $tmp; } 
        } 
        $portraitRel = (string)($metaTmp['portrait'] ?? ''); 
        $extTmp = []; 
        if (!empty($row['extended_data'])) { 
            $tmp2 = json_decode((string)$row['extended_data'], true); 
            if (is_array($tmp2)) { $extTmp = $tmp2; } 
        }
        
        // Check for inherited profile settings
        $profileMeta = isset($profileMetaById[$pid]) ? $profileMetaById[$pid] : ['dyn'=>false,'mtm'=>false,'ad'=>false];
        
        // Dynamic Profile: check NPC override, otherwise inherit from profile
        $dynEnabled = $profileMeta['dyn']; // default to profile
        if (isset($row['dynamic_profile']) && $row['dynamic_profile'] !== null && $row['dynamic_profile'] !== '') {
            $dynEnabled = !empty($row['dynamic_profile']);
        }
        
        // MTM: check extended_data override, otherwise inherit from profile
        $mtmEnabled = $profileMeta['mtm']; // default to profile
        if (array_key_exists('middle_term_enabled', $extTmp) && $extTmp['middle_term_enabled'] !== null && $extTmp['middle_term_enabled'] !== '') {
            $mtmEnabled = !empty($extTmp['middle_term_enabled']);
        }

        // Individual memory bank is NPC-only (no profile inheritance)
        $imbEnabled = false;
        if (array_key_exists('individual_memory_enabled', $extTmp) && $extTmp['individual_memory_enabled'] !== null && $extTmp['individual_memory_enabled'] !== '') {
            $imbEnabled = !empty($extTmp['individual_memory_enabled']);
        }
        
        // Auto Diary: check extended_data override, otherwise inherit from profile
        $adEnabled = $profileMeta['ad']; // default to profile
        if (array_key_exists('auto_diary_enabled', $extTmp) && $extTmp['auto_diary_enabled'] !== null && $extTmp['auto_diary_enabled'] !== '') {
            $adEnabled = !empty($extTmp['auto_diary_enabled']);
        }
        
        // Auto Greeting: check extended_data override, otherwise inherit from profile
        $salEnabled = $profileMeta['sal']; // default to profile
        if (array_key_exists('salutation_after_a_while', $extTmp) && $extTmp['salutation_after_a_while'] !== null && $extTmp['salutation_after_a_while'] !== '') {
            $salEnabled = !empty($extTmp['salutation_after_a_while']);
        }
        
        // Background Life Commands: check extended_data override, otherwise inherit from profile
        $blcEnabled = $profileMeta['blc']; // default to profile
        if (array_key_exists('background_life_commands', $extTmp) && $extTmp['background_life_commands'] !== null && $extTmp['background_life_commands'] !== '') {
            $blcEnabled = !empty($extTmp['background_life_commands']);
        }
        
        // GPS Track: check metadata override, otherwise inherit from profile
        $gpsEnabled = $profileMeta['gps']; // default to profile
        if (array_key_exists('gps_track', $metaTmp) && $metaTmp['gps_track'] !== null && $metaTmp['gps_track'] !== '') {
            $gpsEnabled = !empty($metaTmp['gps_track']);
        }
        
        $raceIcon = race_icon_web_path($row['race'] ?? '', $webRoot,$row["refid"] ?? '', $row['md5'] ?? '', $row['npc_name'] ?? '', $portraitRel); 
        $tagsVal = trim((string)($row['tags'] ?? '')); 
        $tagsDisp = ($tagsVal === '') ? '' : $tagsVal; 
        ?>
        <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
            <div class="npc-title">
                <div class="npc-title-left"><?php 
                    // Use already-parsed $metaTmp to avoid re-decoding
                    $levelDisp = '';
                    if (isset($metaTmp['stats']) && is_array($metaTmp['stats']) && isset($metaTmp['stats']['level'])) {
                        $levelDisp = ' ('.intval($metaTmp['stats']['level']).')';
                    }
            ?><span class="npc-name"><?= htmlspecialchars(($row["npc_name"] ?? '').$levelDisp) ?></span><?php $dupCount = npc_duplicate_count($npcNameCounts ?? [], $row['npc_name'] ?? ''); if ($dupCount > 1): ?><span class="npc-dup-badge" title="<?= htmlspecialchars($dupCount.' profiles share the name "'.($row['npc_name'] ?? '').'"', ENT_QUOTES) ?>"><span aria-hidden="true">&times;<?= (int)$dupCount ?></span><span class="npc-sr-only"><?= (int)$dupCount ?> profiles share this name</span></span><?php endif; ?><?php $shareState = npc_profile_sharing_state($npcProfileSharing ?? [], $row["id"] ?? 0); render_npc_sharing_badge($shareState); ?> <?php $gch = gender_icon_char($row['gender'] ?? ''); $gcl = gender_icon_class($row['gender'] ?? ''); if ($gch!==''): ?><span class="npc-gender-icon <?= htmlspecialchars($gcl) ?>" title="<?= htmlspecialchars($row['gender'] ?? '') ?>"><?= $gch ?></span><?php endif; ?><?php if (!empty($dynEnabled)): ?><span class="npc-dyn-icon" title="Dynamic profile enabled">♻️</span><?php endif; ?><?php if (!empty($mtmEnabled)): ?><span class="npc-mtm-icon" title="Middle-term memory enabled">📃</span><?php endif; ?><?php if (!empty($imbEnabled)): ?><span class="npc-imb-icon" title="Individual memory bank enabled">🧠</span><?php endif; ?><?php if (!empty($adEnabled)): ?><span class="npc-ad-icon" title="Auto diary enabled">📙</span><?php endif; ?><?php if (!empty($salEnabled)): ?><span class="npc-sal-icon" title="Auto Greeting enabled">👋</span><?php endif; ?><?php if (!empty($blcEnabled)): ?><span class="npc-blc-icon" title="Background life commands enabled">🎮</span><?php endif; ?><?php if (!empty($gpsEnabled)): ?><span class="npc-gps-icon" title="GPS track enabled">📍</span><?php endif; ?></div>
            <div class="npc-title-actions">
                    <?php if ($tagsDisp !== ''): ?>
                    <span class="npc-tags-top" title="<?= htmlspecialchars($tagsDisp) ?>"><?= htmlspecialchars($tagsDisp) ?></span>
                    <?php endif; ?>
                    <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>" title="Toggle favorite"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                <?php render_npc_merge_action($row, $shareState, $dupCount); ?><a class="btn btn-toggle" href="#" data-pick-picture-id="<?= $row["id"] ?>" title="Set picture">🖼️</a>
                <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>" title="Toggle lock - Locked profiles are protected from history pullback when loading saves"><?php echo !empty($row["lock_profile"]) ? "🔒" : "🔓"; ?></a>
                <a class="btn btn-trash<?= !empty($row['lock_profile']) ? ' disabled' : '' ?>" href="<?= !empty($row['lock_profile']) ? '#' : ('?delete='.$row['id']) ?>" onclick="<?= !empty($row['lock_profile']) ? 'alert(\'This NPC is locked and cannot be deleted.\'); return false;' : npc_delete_confirm_onclick($row, $metaTmp) ?>" title="<?= !empty($row['lock_profile']) ? 'Locked - cannot delete' : 'Delete' ?>">❌</a>
                </div>
            </div>
            <div class="npc-divider"></div>
            <div class="npc-row">
                <div class="npc-fields">
                    <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"><?= htmlspecialchars($row["gender"] ?? "") ?></span></div>
                    <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"><?= htmlspecialchars($row["race"] ?? "") ?></span></div>
                    <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"><?= htmlspecialchars($row["voiceid"] ?? "") ?></span></div>
                    <?php render_npc_identity_lines($row, $metaTmp); ?>
                    <?php $oghmaVal = trim((string)($row["oghma_knowledge_tags"] ?? "")); $oghmaDisp = ($oghmaVal === "") ? "none" : $oghmaVal; ?>
                    <div class="npc-line"><span class="npc-muted">Oghma Tags:</span> <span class="npc-oghma"><?= htmlspecialchars($oghmaDisp) ?></span></div>
                    <div class="npc-line"><span class="npc-muted">Profile:</span> <span class="npc-profile"><?= htmlspecialchars($profLabel) ?></span></div>
                    <?php $tagsVal = trim((string)($row["tags"] ?? "")); $tagsDisp = ($tagsVal === "") ? "none" : $tagsVal; ?>                </div>
                <div class="npc-right">
                    <?php if ($raceIcon !== ''): ?>
                        <img class="npc-race-art" src="<?= htmlspecialchars($raceIcon) ?>" alt="Race icon" />
                    <?php endif; ?>
                </div>
                <div class="npc-right-warn">
                    <?php 
                    if ($row["gamets_last_updated"] != $LAST_INFOSAVE_EVENT) {
                        echo "<span title='This NPC is out of sync, this means current NPC sheet has been modified after last save. If you edit this NPC, changes will be lost if you reload a previous savegame. '>⚠️</span>";
                    }

                    ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
    exit;
}

// Lightweight endpoint: resolve race icon URL for a given race label
if (isset($_GET['race_icon'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $race = (string)($_GET['race'] ?? '');
    $refid = (string)($_GET['refid'] ?? '');
    $name = (string)($_GET['name'] ?? '');
    
    $md5 = $name !== '' ? md5($name) : (string)($_GET['md5'] ?? '');
    $portraitRel = '';
    $id = intval($_GET['id'] ?? 0);
    if ($id) {
        $npcData=$npc->getById($id);
        $refid=$npcData["refid"];
    }
    if ($id > 0) {
        try { $row = $npc->getById($id); if ($row && !empty($row['metadata'])) { $tmp = json_decode((string)$row['metadata'], true); if (is_array($tmp)) { $portraitRel = (string)($tmp['portrait'] ?? ''); } } } catch (Throwable $e) {}
    }

    error_log("$race, $webRoot, $refid, $md5, $name, $portraitRel");

    $url = race_icon_web_path($race, $webRoot, $refid, $md5, $name, $portraitRel);
    echo json_encode(['url' => $url]);
    exit;
}

// NPC history: return timeline of snapshots for a given NPC (Tamrielic time)
if (isset($_GET['history'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false, 'error'=>'Invalid id']); exit; }
        // Skip the most recent snapshot (current state); show only historical entries
        $sel = "select history_id,npc_id,npc_name,npc_favorite,lock_profile,prompt_head,npc_static_bio,oghma_knowledge_tags,emote_moods,personality,relationships,occupation,appearance,skills,speechstyle,goals,voiceid,gender,race,refid,profile_id,dynamic_profile,md5,gamets_last_updated,created,core,base,tags from core_npc_master_history where npc_id = {$id} order by coalesce(gamets_last_updated,0) desc, created desc, history_id desc offset 1";
        $rows = $GLOBALS['db']->fetchAll($sel) ?: [];
        $entries = [];
        foreach ($rows as $r){
            $g = isset($r['gamets_last_updated']) ? floatval($r['gamets_last_updated']) : 0.0;
            $tam = $g > 0 ? convert_gamets2skyrim_long_date2($g) : '';
            $greg = $g > 0 ? gamets2str_format_gregorian_date($g, 'Y-m-d H:i') : '';
            $created = (string)($r['created'] ?? '');
            $entries[] = [
                'history_id' => (int)($r['history_id'] ?? 0),
                'gamets' => $g,
                'when_tamrielic' => $tam,
                'when_gregorian' => $greg,
                'created' => $created,
                'fields' => [
                    'npc_name' => $r['npc_name'] ?? '',
                    'profile_id' => isset($r['profile_id']) ? (string)$r['profile_id'] : '',
                    'gender' => $r['gender'] ?? '',
                    'race' => $r['race'] ?? '',
                    'voiceid' => $r['voiceid'] ?? '',
                    'refid' => $r['refid'] ?? '',
                    'core' => $r['core'] ?? '',
                    'npc_static_bio' => $r['npc_static_bio'] ?? '',
                    'personality' => $r['personality'] ?? '',
                    'relationships' => $r['relationships'] ?? '',
                    'occupation' => $r['occupation'] ?? '',
                    'skills' => $r['skills'] ?? '',
                    'speechstyle' => $r['speechstyle'] ?? '',
                    'goals' => $r['goals'] ?? '',
                    'oghma_knowledge_tags' => $r['oghma_knowledge_tags'] ?? '',
                    'emote_moods' => $r['emote_moods'] ?? '',
                    'prompt_head' => $r['prompt_head'] ?? '',
                    'dynamic_profile' => !empty($r['dynamic_profile']),
                    'npc_favorite' => !empty($r['npc_favorite']),
                    'lock_profile' => !empty($r['lock_profile']),
                    'tags' => $r['tags'] ?? '',
                    'base' => $r['base'] ?? ''
                ]
            ];
        }
        echo json_encode(['ok'=>true,'count'=>count($entries),'entries'=>$entries]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Restore NPC from history (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["restore_from_history"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $historyId = intval($_POST['history_id'] ?? 0);
        if ($historyId <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid history_id"]); exit; }
        
        // Fetch the historical record
        $histRow = $GLOBALS['db']->fetchOne("select * from core_npc_master_history where history_id = {$historyId}");
        if (!$histRow) { echo json_encode(["ok"=>false, "error"=>"Historical record not found"]); exit; }
        
        $npcId = intval($histRow['npc_id'] ?? 0);
        if ($npcId <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid NPC id in history"]); exit; }
        
        // Check if NPC is locked
        $current = $npc->getById($npcId);
        if ($current && count(chimNpcProfileMembers($current)) > 1) {
            echo json_encode(['ok' => false, 'error' => 'Unlink shared profiles before restoring an individual profile snapshot']);
            exit;
        }
        if ($current && !empty($current['lock_profile'])) {
            echo json_encode(["ok"=>false, "error"=>"Cannot restore: NPC is locked"]);
            exit;
        }
        
        $historyExtendedData = $histRow['extended_data'] ?? '';
        if (is_string($historyExtendedData) && trim($historyExtendedData) !== '') {
            $decodedHistoryExtendedData = json_decode($historyExtendedData, true);
            if (is_array($decodedHistoryExtendedData)) {
                unset($decodedHistoryExtendedData['_chim_history_source']);
                $historyExtendedData = json_encode($decodedHistoryExtendedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        // Prepare data for update (copy relevant fields from history)
        $updateData = [
            '_profile_binding' => $current['_profile_binding'] ?? ':',
            'npc_name' => $histRow['npc_name'] ?? '',
            'profile_id' => $histRow['profile_id'] ?? null,
            'gender' => $histRow['gender'] ?? '',
            'race' => $histRow['race'] ?? '',
            'voiceid' => $histRow['voiceid'] ?? '',
            'refid' => $histRow['refid'] ?? '',
            'core' => $histRow['core'] ?? '',
            'base' => $histRow['base'] ?? '',
            'npc_static_bio' => $histRow['npc_static_bio'] ?? '',
            'personality' => $histRow['personality'] ?? '',
            'relationships' => $histRow['relationships'] ?? '',
            'occupation' => $histRow['occupation'] ?? '',
            'appearance' => $histRow['appearance'] ?? '',
            'skills' => $histRow['skills'] ?? '',
            'speechstyle' => $histRow['speechstyle'] ?? '',
            'goals' => $histRow['goals'] ?? '',
            'oghma_knowledge_tags' => $histRow['oghma_knowledge_tags'] ?? '',
            'emote_moods' => $histRow['emote_moods'] ?? '',
            'prompt_head' => $histRow['prompt_head'] ?? '',
            'dynamic_profile' => !empty($histRow['dynamic_profile']) ? 1 : 0,
            'tags' => $histRow['tags'] ?? '',
            'metadata' => $histRow['metadata'] ?? '',
            'extended_data' => $historyExtendedData,
            'md5' => $histRow['md5'] ?? md5($histRow['npc_name'] ?? '')
        ];
        
        // Update the NPC
        $ok = chimRunWithRelationshipExtendedDataWrite(function () use ($npc, $npcId, $updateData) {
            return $npc->update($npcId, $updateData);
        });
        if ($ok === false) {
            echo json_encode(["ok"=>false, "error"=>($npc->getLastError() ?? 'Restore failed')]);
        } else {
            // Create a backup of the restored state
            $npc->backupNpcById($npcId);
            echo json_encode(["ok"=>true, "npc_id"=>$npcId]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Bio database: search existing templates (combined_bio_templates)
if (isset($_GET['bio_search'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $search = trim((string)($_GET['search'] ?? ''));
    $letter = trim((string)($_GET['letter'] ?? ''));
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? 20)));
    $where = [];
    if ($search !== '') {
        $q = '%'.$GLOBALS['db']->escape($search).'%';
        $where[] = "(lower(npc_name) like lower('{$q}') or lower(core) like lower('{$q}'))";
    }
    if ($letter !== '' && preg_match('/^[A-Za-z]$/', $letter)) {
        $l = $GLOBALS['db']->escape(strtolower($letter));
        $where[] = "lower(npc_name) like '{$l}%'";
    }
    $whereSql = count($where) ? ('where '.implode(' and ', $where)) : '';
    $cntRow = $GLOBALS['db']->fetchOne("select count(*) as c from combined_bio_templates {$whereSql}");
    $total = intval($cntRow['c'] ?? 0);
    $offset = ($page - 1) * $pageSize;
    $rows = $GLOBALS['db']->fetchAll("select npc_name, core, voiceid, gender, race, refid, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags from combined_bio_templates {$whereSql} order by lower(npc_name) asc limit {$pageSize} offset {$offset}");
    $items = [];
    foreach (($rows ?? []) as $r) {
        $extFields = ['npc_static_bio','personality','appearance','relationships','occupation','skills','speechstyle','goals'];
        $filled = 0; foreach ($extFields as $f) { $v = trim((string)($r[$f] ?? '')); if ($v !== '') $filled++; }
        $coreFull = (string)($r['core'] ?? '');
        if (function_exists('mb_strimwidth')) {
            $corePreview = mb_strimwidth($coreFull, 0, 160, '…', 'UTF-8');
        } else {
            $corePreview = (strlen($coreFull) > 160) ? (substr($coreFull, 0, 157).'…') : $coreFull;
        }
        $items[] = [
            'npc_name' => $r['npc_name'] ?? '',
            'core_preview' => $corePreview,
            'voiceid' => $r['voiceid'] ?? '',
            'gender' => $r['gender'] ?? '',
            'race' => $r['race'] ?? '',
            'refid' => $r['refid'] ?? '',
            'extended_filled' => $filled
        ];
    }
    echo json_encode(['ok'=>true,'total'=>$total,'page'=>$page,'pageSize'=>$pageSize,'items'=>$items]);
    exit;
}

// Bio database: detail of a specific template by npc_name
if (isset($_GET['bio_detail'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $name = trim((string)($_GET['name'] ?? ''));
    if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Missing name']); exit; }
    $esc = $GLOBALS['db']->escape($name);
    // Case-insensitive exact match on npc_name to tolerate capitalization differences
    $r = $GLOBALS['db']->fetchOne("select npc_name, core, voiceid, gender, race, refid, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags from combined_bio_templates where lower(npc_name) = lower('{$esc}') limit 1");
    if (!$r) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
    echo json_encode(['ok'=>true,'data'=>$r]);
    exit;
}

// Import from bio: server builds row and creates/updates NPC
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['import_from_bio'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Missing name']); exit; }
        $includeCore = ($_POST['include_core'] ?? '1') ? true : false;
        $includeExt  = ($_POST['include_extended'] ?? '1') ? true : false;
        $includeOgh  = ($_POST['include_oghma'] ?? '1') ? true : false;
        $includeVM   = ($_POST['include_voice_meta'] ?? '1') ? true : false;
        $profileId   = isset($_POST['profile_id']) && $_POST['profile_id']!=='' ? intval($_POST['profile_id']) : null;

        $esc = $GLOBALS['db']->escape($name);
        $r = $GLOBALS['db']->fetchOne("select npc_name, core, voiceid, gender, race, refid, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags from combined_bio_templates where npc_name = '{$esc}' limit 1");
        if (!$r) { echo json_encode(['ok'=>false,'error'=>'Template not found']); exit; }

        $data = [ 'npc_name' => $r['npc_name'] ?? $name ];
        if ($profileId !== null) $data['profile_id'] = $profileId;
        if ($includeCore) { $data['core'] = $r['core'] ?? null; }
        if ($includeExt) {
            foreach (['npc_static_bio','personality','appearance','occupation','skills','speechstyle','goals'] as $f) {
                $data[$f] = $r[$f] ?? null;
            }
            chimMergeRelationshipSeedIntoExtendedData($data, $r['relationships'] ?? null);
        }
        if ($includeOgh) { $data['oghma_knowledge_tags'] = $r['oghma_knowledge_tags'] ?? null; }
        if ($includeVM) {
            foreach (['voiceid','gender','race','refid'] as $f) { $data[$f] = $r[$f] ?? null; }
        }

        // Upsert by name
        $existing = $npc->getByName($data['npc_name']);
        if ($existing) {
            $data['md5'] = md5((string)$data['npc_name']);
            $ok = $npc->update((int)$existing['id'], $data);
            if ($ok === false) { echo json_encode(['ok'=>false,'error'=>'Update failed']); exit; }
            $newId = (int)$existing['id'];
        } else {
            $npc->create($data);
            // Fetch newly created row
            $row = $npc->getByName($data['npc_name']);
            $newId = (int)($row['id'] ?? 0);
        }
        if (!$newId) { echo json_encode(['ok'=>false,'error'=>'Insert failed']); exit; }
        $payload = $npc->getById($newId) ?: $data;
        echo json_encode(['ok'=>true,'id'=>$newId,'data'=>$payload]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
?>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php endif; ?>

<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ob_end_clean(); ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/npc_event_history.css">
<style>html,body{background:#2a2a2a;margin-bottom:50px;margin-right:5px;} main{background:#2a2a2a; padding:12px;} .form-container{background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px;}
.modal-inline-actions{display:flex; gap:6px; align-items:center; justify-content:flex-end; margin-bottom:8px;}
.modal-inline-actions .btn-toggle{background:transparent; border:none; padding:6px; color:#e9efff; font-size:22px; line-height:1; text-decoration:none; cursor:pointer;}
.modal-inline-actions .btn-toggle:hover{color: rgb(242, 124, 17); text-decoration:none;}
.modal-inline-actions .btn-toggle.active{color:#ffd700; font-weight:700;}
.modal-inline-actions .btn-toggle[data-favorite]:hover,
.modal-inline-actions .btn-toggle[data-favorite]:focus-visible{color:#ffd700 !important; text-shadow:0 0 8px rgba(255,215,0,.7),0 0 14px rgba(255,215,0,.45) !important;}
.modal-inline-actions .btn-toggle.active[data-favorite]{color:#ffd700 !important;}
</style>
<div data-npc-profile-loaded="1" data-npc-id="<?= htmlspecialchars((string)($editItem['id'] ?? '')) ?>" hidden></div>
<form method="post" onsubmit='return false' style='display:block'>
<?php } else { ?>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
<?php } ?>
    <script src="<?php echo $webRoot; ?>/ui/js/npc_event_history.js"></script>
    <style>
    .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px 16px; }
    @media (max-width: 900px){ .form-grid { grid-template-columns: 1fr; } }
    .form-item { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
    .form-item label { font-weight:700; color:rgb(242, 124, 17); }
    .form-item .hint { color:#e9efff; font-size:12px; line-height:1.35; }
    .form-item textarea { min-height:96px; }
    #prompt_head, #core, #npc_static_bio, #appearance,
    #personality, #relationships, #occupation, #skills {
        min-height: 134px; /* 96px * 1.4 ≈ 134 */
    }
    .form-item input[type="text"], .form-item textarea, .form-item select { background:#2a2a2a; color:#e9efff; border:1px solid #4a4a4a; border-radius:6px; padding:8px 10px; }
    .prompt-head-label-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .prompt-head-copy-btn { padding:4px 9px; border:1px solid #4a4a4a; border-radius:5px; background:#242424; color:#cfd9ea; cursor:pointer; font-size:11px; font-weight:600; }
    .prompt-head-copy-btn:hover { border-color:rgb(242,124,17); color:rgb(242,124,17); }
    /* Header-style checkbox next to label title */
    .label-with-toggle { display:flex; align-items:center; gap:10px; }
    .label-with-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
    .span-2 { grid-column: 1 / -1; margin-bottom:12px; }
    .checkbox-inline { display:flex; align-items:center; gap:8px; }
    </style>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
        <input type="hidden" name="_profile_binding" value="<?= htmlspecialchars($editItem['_profile_binding'] ?? ':', ENT_QUOTES) ?>">
        <?php $editSharing = chimNpcProfileSharing($editItem); if (!empty($editSharing['linked'])): ?>
            <p class="hint" role="note"><?= !empty($editSharing['automatic']) ? 'Shared profile, linked automatically' : 'Shared profile' ?>: character fields and personal memory are shared. Physical details, RefID, favourite and lock stay with this actor. Unlink before renaming or restoring a history snapshot. Unlinking separates every actor in the group.</p>
        <?php elseif (!empty($editSharing['auto_link_disabled'])): ?>
            <p class="hint" role="note">Automatic linking is switched off for this character for the rest of this playthrough. Same-name actors can still be merged by hand.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php $isPartial = (isset($_GET['partial']) && $_GET['partial']=='1'); $isFav = !empty($editItem['npc_favorite']); ?>
    <?php if ($isPartial): ?>
    <div class="modal-inline-actions">
        <p style="margin:0; color:rgb(242, 124, 17) ;">Tags:</p>
        <input type="text" id="modal_tags_input" name="tags" value="<?= htmlspecialchars($editItem['tags'] ?? '') ?>" placeholder="tags" style="max-width:240px; font-size:12px; padding:4px 6px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;" title="Tags help with searching and grouping" />
        <a id="modal_fav_btn" class="btn btn-toggle<?= $isFav? ' active':'' ?>" href="#" title="Toggle favorite" data-favorite><?= $isFav? '★' : '☆' ?></a>
    </div>
    <?php
    // Render LLM summary container (will live-update via JS)
    $curPid = (string)($editItem['profile_id'] ?? '');
    $pc = ($curPid !== '' && isset($profilesConnById[$curPid])) ? $profilesConnById[$curPid] : null;
    $m = function($id) use ($llmById){ $k = (string)($id ?? ''); return $k !== '' && isset($llmById[$k]) ? $llmById[$k] : '—'; };
    ?>
    <div id="profile_llm_summary" style="display:grid; grid-template-columns: 210px 1fr; gap:6px; color:#cfd9ea; border:1px solid #4a4a4a; border-radius:8px; padding:8px; margin-bottom:8px;">
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">LLMs</div>
        <div>
            🕹️ <?= htmlspecialchars($pc ? $m($pc['llm_primary_id'] ?? '') : '—') ?>
            | 🏃‍♂️‍➡️ <?= htmlspecialchars($pc ? $m($pc['llm_secondary_id'] ?? '') : '—') ?>
            | 💪 <?= htmlspecialchars($pc ? $m($pc['llm_tertiary_id'] ?? '') : '—') ?>
            | 🧪 <?= htmlspecialchars($pc ? $m($pc['llm_quaternary_id'] ?? '') : '—') ?>
            | 📓 <?= htmlspecialchars($pc ? $m($pc['diary_connector_id'] ?? '') : '—') ?>
            | 🧾 <?= htmlspecialchars($pc ? $m($pc['llm_formatter_id'] ?? '') : '—') ?>
        </div>
    </div>
    <script>
    (function(){
        const PROFILE_CONN = <?= json_encode($profilesConnById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        const LLM_LABELS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        function labelOf(id){ const k=String(id||''); return (k && LLM_LABELS[k]) ? String(LLM_LABELS[k]) : '—'; }
        function renderProfileSummary(pid){
            const box = document.getElementById('profile_llm_summary'); if (!box) return;
            const pc = PROFILE_CONN[String(pid||'')] || null;
            const combined = (function(){
                const std = pc ? labelOf(pc.llm_primary_id) : '—';
                const fast = pc ? labelOf(pc.llm_secondary_id) : '—';
                const pow = pc ? labelOf(pc.llm_tertiary_id) : '—';
                return '🕹️ ' + std + ' | 🏃‍♂️‍➡️ ' + fast + ' | 💪 ' + pow;
            })();
            const exp = pc ? labelOf(pc.llm_quaternary_id) : '—';
            const dia = pc ? labelOf(pc.diary_connector_id) : '—';
            const fmt = pc ? labelOf(pc.llm_formatter_id) : '—';
            const all = combined + ' | 🧪 ' + exp + ' | 📓 ' + dia + ' | 🧾 ' + fmt;
            const rows = [
                ['Profile LLMs', all]
            ];
            let html = '';
            rows.forEach(([k,v])=>{
                html += '<div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">'+k+'</div><div>'+String(v||'—')+'</div>';
            });
            box.innerHTML = html;
        }
        document.addEventListener('DOMContentLoaded', function(){
            const sel = document.getElementById('profile_id');
            if (sel){ 
                sel.addEventListener('change', function(){ 
                    renderProfileSummary(this.value||''); 
                    updateInheritedSettings(this.value||'');
                }); 
                renderProfileSummary(sel.value||''); 
            }
        });
        
        // Profile metadata for inherited settings
        const PROFILE_META = <?= json_encode(array_map(function($pr){
            $meta = [];
            try {
                if (!empty($pr['metadata'])) {
                    $tmp = json_decode((string)$pr['metadata'], true);
                    if (is_array($tmp)) $meta = $tmp;
                }
            } catch (Throwable $e) {}
            $dynVal = isset($meta['DYNAMIC_PROFILE_ENABLED']) ? $meta['DYNAMIC_PROFILE_ENABLED'] : null;
            $mtmVal = isset($meta['MIDDLE_TERM_MEMORY_ENABLED']) ? $meta['MIDDLE_TERM_MEMORY_ENABLED'] : null;
            $adVal = isset($meta['AUTO_DIARY_ENABLED']) ? $meta['AUTO_DIARY_ENABLED'] : null;
            $adWaitVal = isset($meta['AUTO_DIARY_WAIT_ENABLED']) ? $meta['AUTO_DIARY_WAIT_ENABLED'] : null;
            $salVal = isset($meta['SALUTATION_AFTER_A_WHILE']) ? $meta['SALUTATION_AFTER_A_WHILE'] : null;
            $blcVal = isset($meta['BACKGROUND_LIFE_COMMANDS']) ? $meta['BACKGROUND_LIFE_COMMANDS'] : null;
            $gpsVal = isset($meta['GPS_TRACK']) ? $meta['GPS_TRACK'] : null;
            return [
                'id' => (string)($pr['id'] ?? ''),
                'dyn' => ($dynVal === '1' || $dynVal === 1 || $dynVal === true),
                'mtm' => ($mtmVal === '1' || $mtmVal === 1 || $mtmVal === true),
                'ad' => ($adVal === '1' || $adVal === 1 || $adVal === true),
                'adWait' => ($adWaitVal === '1' || $adWaitVal === 1 || $adWaitVal === true),
                'sal' => ($salVal === '1' || $salVal === 1 || $salVal === true),
                'blc' => ($blcVal === '1' || $blcVal === 1 || $blcVal === true),
                'gps' => ($gpsVal === '1' || $gpsVal === 1 || $gpsVal === true)
            ];
        }, $profileConnRows ?? []), JSON_UNESCAPED_SLASHES) ?>;
        
        function updateInheritedSettings(profileId) {
            const profile = PROFILE_META.find(p => p.id === profileId);
            if (!profile) return;
            
            // Update dynamic_profile
            const dynCb = document.getElementById('dynamic_profile');
            if (dynCb) {
                dynCb.checked = profile.dyn;
                dynCb.setAttribute('data-profile-default', profile.dyn ? '1' : '0');
                const hint = dynCb.closest('.form-item').querySelector('.hint');
                if (hint) {
                    const base = 'Allow systems to evolve the profile based on gameplay events.';
                    hint.innerHTML = base + (profile.dyn ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '');
                }
            }
            
            // Update middle_term_enabled
            const mtmCb = document.getElementById('middle_term_enabled');
            if (mtmCb) {
                mtmCb.checked = profile.mtm;
                mtmCb.setAttribute('data-profile-default', profile.mtm ? '1' : '0');
                const hint = mtmCb.closest('.form-item').querySelector('.hint');
                if (hint) {
                    const base = 'Saves a list of recent events after every 10 memory summaries. Will be used for NPC context.';
                    hint.innerHTML = base + (profile.mtm ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '');
                }
            }
            
            // Update auto_diary_enabled
            const adCb = document.getElementById('auto_diary_enabled');
            if (adCb) {
                adCb.checked = profile.ad;
                adCb.setAttribute('data-profile-default', profile.ad ? '1' : '0');
                const hint = adCb.closest('.form-item').querySelector('.hint');
                if (hint) {
                    const base = 'Automatically generate diary entries for this NPC when they are nearby during sleep/wait events.';
                    hint.innerHTML = base + (profile.ad ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '');
                }
            }
            
            // Update auto_diary_wait_enabled
            const adWaitCb = document.getElementById('auto_diary_wait_enabled');
            if (adWaitCb) {
                adWaitCb.checked = profile.adWait;
                adWaitCb.setAttribute('data-profile-default', profile.adWait ? '1' : '0');
                const hint = adWaitCb.closest('.form-item').querySelector('.hint');
                if (hint) {
                    const base = 'When Auto Diary is enabled, this controls whether diary entries are created during wait events. If disabled, auto diary will only trigger on sleep events.';
                    hint.innerHTML = base + (profile.adWait ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '');
                }
            }
            
            // Update salutation_after_a_while
            const salCb = document.getElementById('salutation_after_a_while');
            if (salCb) {
                salCb.checked = profile.sal;
                salCb.setAttribute('data-profile-default', profile.sal ? '1' : '0');
                const hint = salCb.closest('.form-item').querySelector('.hint');
                if (hint) {
                    const base = 'NPC will greet you after you have been away for a while.';
                }
            }
        }
    })();
    </script>
    <?php endif; ?>

    <div class="npc-editor-tabs" role="tablist" aria-label="NPC editor categories" data-npc-editor-tabs data-storage-key="herika-npc-editor-tab">
    <button type="button" class="npc-editor-tab is-active" role="tab" aria-selected="true" data-npc-editor-tab="general">🧭 General</button>
    <button type="button" class="npc-editor-tab" role="tab" aria-selected="false" data-npc-editor-tab="bios">📖 Roleplay</button>
    <button type="button" class="npc-editor-tab" role="tab" aria-selected="false" data-npc-editor-tab="relationships">🤝 Relationships</button>
    <button type="button" class="npc-editor-tab" role="tab" aria-selected="false" data-npc-editor-tab="info">🛠️ Info</button>
    <button type="button" class="npc-editor-tab" role="tab" aria-selected="false" data-npc-editor-tab="actions">⚡ Actions</button>
    <button type="button" class="npc-editor-tab" role="tab" aria-selected="false" data-npc-editor-tab="background-life">🌍 Background Life</button>
    <button type="button" class="npc-editor-tab" role="tab" aria-selected="false" data-npc-editor-tab="history">📜 History</button>
</div>
<style>
.npc-editor-tabs {
    display:grid;
    grid-template-columns:repeat(7, minmax(0, 1fr));
    gap:8px;
    margin-bottom:14px;
    padding:8px;
    border:1px solid #3a3a3a;
    border-radius:10px;
    background:rgba(30, 30, 30, 0.92);
}
.npc-editor-tab {
    position:relative;
    min-height:40px;
    padding:8px 12px;
    border:1px solid #444;
    border-radius:7px;
    background:#303030;
    color:#ddd;
    font-weight:700;
    cursor:pointer;
    transition:border-color 0.15s ease, background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.npc-editor-tab:hover { border-color:rgba(242,124,17,0.55); background:#383838; }
.npc-editor-tabs .npc-editor-tab.is-active {
    border-color:rgb(242,124,17) !important;
    color:#fff !important;
    background:rgba(92,53,25,0.95) !important;
    box-shadow:inset 0 0 0 1px rgba(242,124,17,0.28), 0 0 12px rgba(242,124,17,0.24) !important;
    transform:translateY(-1px) !important;
}
.npc-editor-tab:focus-visible { outline:2px solid rgb(242,124,17); outline-offset:2px; }
.npc-editor-panels { display:block; }
.npc-editor-panel[hidden] { display:none !important; }
.npc-editor-panel[data-npc-editor-panel="bios"] { grid-template-columns:minmax(0, 1fr); }
.npc-editor-panel[data-npc-editor-panel="background-life"] { grid-template-columns:minmax(0, 1fr); }
.npc-editor-panel[data-npc-editor-panel="actions"] { grid-template-columns:minmax(0, 1fr); }
.npc-editor-action-note { margin:0; color:#aaa; font-size:0.86rem; }
.npc-editor-action-list { display:grid; gap:10px; }
.npc-editor-action-card {
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto;
    align-items:center;
    gap:14px;
    padding:12px;
    border:1px solid #444;
    border-radius:7px;
    background:#282828;
}
.npc-editor-action-card h3 { margin:0 0 4px; color:#f2bd7f; font-size:1rem; }
.npc-editor-action-card p { margin:0; color:#aaa; font-size:0.82rem; }
.npc-editor-action-card textarea { min-height:84px; resize:vertical; }
.npc-editor-action-status { min-height:1.2rem; margin:0; color:#aaa; font-size:0.82rem; }
.npc-editor-action-status.is-error { color:#ef8f96; }
.npc-editor-action-note.is-error { color:#ef8f96; }
.npc-bgl-dashboard { display:grid; gap:12px; }
.npc-bgl-section {
    padding:14px;
    border:1px solid #444;
    border-radius:8px;
    background:#252525;
}
.npc-bgl-section-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
}
.npc-bgl-section h3 { margin:0; color:#f2bd7f; font-size:1rem; }
.npc-bgl-section p { margin:3px 0 0; color:#aaa; font-size:0.82rem; }
.npc-bgl-summary {
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:8px;
}
.npc-bgl-summary-item {
    min-width:0;
    padding:10px;
    border:1px solid #3d3d3d;
    border-radius:6px;
    background:#1d1d1d;
}
.npc-bgl-summary-item span { display:block; margin-bottom:4px; color:#888; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; }
.npc-bgl-summary-item strong { display:block; overflow-wrap:anywhere; color:#e7e7e7; font-size:0.9rem; }
.npc-bgl-state-button,
.npc-bgl-control,
.npc-bgl-request {
    border:1px solid #4a4a4a;
    border-radius:6px;
    background:#303030;
    color:#eee;
    cursor:pointer;
}
.npc-bgl-state-button { padding:8px 12px; font-weight:700; white-space:nowrap; }
.npc-bgl-state-button.is-on { border-color:#4c9568; background:#294936; }
.npc-bgl-control-grid,
.npc-bgl-request-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px; }
.npc-bgl-control {
    display:flex;
    align-items:center;
    gap:9px;
    min-height:56px;
    padding:10px;
    text-align:left;
}
.npc-bgl-control-dot { width:9px; height:9px; flex:0 0 auto; border-radius:50%; background:#777; }
.npc-bgl-control.is-on .npc-bgl-control-dot { background:#63c685; box-shadow:0 0 0 3px rgba(99,198,133,0.12); }
.npc-bgl-control strong,
.npc-bgl-control small { display:block; }
.npc-bgl-control small { margin-top:2px; color:#999; font-size:0.72rem; }
.npc-bgl-request { min-height:38px; padding:8px 10px; font-weight:700; }
.npc-bgl-state-button:hover,
.npc-bgl-control:hover,
.npc-bgl-request:hover { border-color:rgba(242,124,17,0.75); background:#393939; }
.npc-bgl-state-button:disabled,
.npc-bgl-control:disabled,
.npc-bgl-request:disabled { opacity:0.5; cursor:not-allowed; }
.npc-bgl-inception { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px; margin-top:8px; }
.npc-bgl-inception textarea { min-height:70px; resize:vertical; }
.npc-bgl-events { display:grid; gap:7px; margin:0; padding:0; list-style:none; }
.npc-bgl-event { padding:9px 10px; border-left:3px solid #666; background:#1d1d1d; color:#ddd; font-size:0.84rem; }
.npc-bgl-event-time { display:block; margin-top:4px; color:#888; font-size:0.72rem; }
.npc-bgl-empty { margin:0; padding:10px; color:#888; text-align:center; }
.npc-relationship-history {
    grid-column:1 / -1;
    padding:14px;
    border:1px solid #444;
    border-radius:8px;
    background:#252525;
}
.npc-relationship-history-header { margin-bottom:10px; }
.npc-relationship-history h3 { margin:0; color:#f2bd7f; font-size:1rem; }
.npc-relationship-history-header p { margin:3px 0 0; color:#aaa; font-size:0.82rem; }
.npc-relationship-history-list { display:grid; gap:6px; margin:0; padding:0; list-style:none; }
.npc-relationship-history-item {
    padding:7px 10px;
    border-left:3px solid rgb(242,124,17);
    border-radius:0 4px 4px 0;
    background:#1d1d1d;
    /* Match the event-log row typography inherited by the shared relationship markup. */
    color:#dee2e6;
    font-size:0.8rem;
    font-weight:700;
}
/* Relationship history rows: compact per-change presentation.
   Same palette and density as the CHIM home dashboard widget. */
.relationship-change-cell {
    display: grid;
    gap: 5px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.relationship-change-entry {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 8px;
    align-items: baseline;
    min-width: 0;
}

.relationship-change-delta {
    min-width: 3.1em;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
    font-size: 0.92em;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    text-align: center;
    white-space: nowrap;
}

/* The sign carries the meaning, so colour is reinforcement only. */
.relationship-change-delta.is-up {
    color: #7ee08a;
    background: rgba(76, 175, 80, 0.14);
    border: 1px solid rgba(126, 224, 138, 0.35);
}

.relationship-change-delta.is-down {
    color: #ff8a80;
    background: rgba(244, 67, 54, 0.14);
    border: 1px solid rgba(255, 138, 128, 0.35);
}

.relationship-change-delta.is-type {
    color: #f2bd7f;
    background: rgba(242, 124, 17, 0.14);
    border: 1px solid rgba(242, 189, 127, 0.35);
    font-family: inherit;
    font-size: 0.72em;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.relationship-change-entry-body {
    display: block;
    min-width: 0;
}

.relationship-change-reason {
    display: block;
    color: #e2e2e2;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.relationship-change-entry-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 3px 6px;
    margin-top: 2px;
    font-size: 0.85em;
    color: #929292;
}

.relationship-change-target {
    color: #bdbdbd;
    overflow-wrap: anywhere;
}

.relationship-change-arrow {
    color: #6f6f6f;
}

.relationship-change-tier {
    padding: 0 4px;
    border: 1px solid #4a4033;
    border-radius: 3px;
    color: #d9c39a;
}

.relationship-change-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}
/* One snapshot time per history row, kept compact beneath the shared change list. */
.npc-relationship-history-time { display:block; margin-top:4px; color:#888; font-size:0.83em; }
.npc-relationship-history-empty { margin:0; padding:10px; color:#888; text-align:center; }
@media (max-width:700px) {
    .npc-editor-tabs { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .npc-relationship-history {
        width:calc(100vw - 16px);
        max-width:100%;
        box-sizing:border-box;
        justify-self:start;
    }
    .relationship-change-entry { gap:8px; }
}
@media (max-width:850px) {
    .npc-editor-action-card { grid-template-columns:minmax(0, 1fr); }
    .npc-bgl-summary,
    .npc-bgl-control-grid,
    .npc-bgl-request-grid { grid-template-columns:minmax(0, 1fr); }
    .npc-bgl-inception { grid-template-columns:minmax(0, 1fr); }
}
</style>
<script>
(function(){
    const fieldSections = {
        general: new Set(['npc_name','profile_id','lock_profile','npc_favorite','gender','race','base','refid','oghma_knowledge_tags','worldknowledge_tags','world_knowledge_tags','voiceid','faction','dynamic_profile','middle_term_enabled','individual_memory_enabled','auto_diary_enabled','auto_diary_wait_enabled','salutation_after_a_while','prompt_head']),
        bios: new Set(['core','npc_static_bio','appearance','personality','occupation','skills','speechstyle','goals']),
        relationships: new Set(['relationships','relationships_jsonb','middle_term_latest']),
        'background-life': new Set(['background_life_goals']),
        history: new Set(),
        info: new Set(['emote_moods','metadata','extended_data']),
        actions: new Set()
    };

    function initNpcEditorTabs(){
        document.querySelectorAll('[data-npc-editor-tabs]').forEach(function(tablist, index){
            if (tablist.dataset.initialized === '1') return;
            const form = tablist.closest('form');
            const grid = form ? form.querySelector('.form-grid') : null;
            if (!form || !grid) return;
            tablist.dataset.initialized = '1';

            const panels = {};
            ['general','bios','relationships','info','actions','background-life','history'].forEach(function(section){
                const panel = document.createElement('div');
                panel.className = 'npc-editor-panel form-grid';
                if (section === 'history') panel.classList.add('npc-editor-panel-history');
                panel.dataset.npcEditorPanel = section;
                panel.id = 'npc-editor-panel-' + section + '-' + index;
                panel.setAttribute('role', 'tabpanel');
                panels[section] = panel;
                const button = tablist.querySelector('[data-npc-editor-tab="' + section + '"]');
                if (button) button.setAttribute('aria-controls', panel.id);
            });

            function tokensFor(unit){
                const nodes = [];
                if (unit.matches('[id],[name]')) nodes.push(unit);
                unit.querySelectorAll('[id],[name]').forEach(function(node){ nodes.push(node); });
                const tokens = [];
                nodes.forEach(function(node){
                    if (node.id) tokens.push(node.id);
                    if (node.getAttribute('name')) tokens.push(node.getAttribute('name'));
                });
                return tokens;
            }

            function sectionFor(unit){
                if (unit.id === 'relationship-editor-section' || unit.querySelector('#relationship-editor-section')) return 'relationships';
                if (unit.id === 'relationship-change-history') return 'relationships';
                const label = unit.querySelector('label:not([for])');
                if (label && label.textContent.replace(/\s+/g, ' ').trim() === 'Relationships') return 'relationships';
                const tokens = tokensFor(unit);
                for (const section of ['relationships','general','bios','background-life','info']) {
                    if (tokens.some(function(token){ return fieldSections[section].has(token); })) return section;
                }
                return 'info';
            }

            function isFieldUnit(unit){
                if (!(unit instanceof Element)) return false;
                if (unit.matches('.form-item,#relationship-editor-section,input,textarea,select,details')) return true;
                return Boolean(unit.querySelector('input,textarea,select,details,#relationship-editor-section'));
            }

            function moveUnit(unit){
                if (!isFieldUnit(unit)) return;
                panels[sectionFor(unit)].appendChild(unit);
            }

            Array.from(grid.children).forEach(function(unit){
                if (unit.classList.contains('dynamic-profile-section')) {
                    Array.from(unit.children).forEach(moveUnit);
                    unit.hidden = true;
                    return;
                }
                moveUnit(unit);
            });

            grid.classList.remove('form-grid');
            grid.classList.add('npc-editor-panels');
            Object.values(panels).forEach(function(panel){ grid.appendChild(panel); });

            <?php
            $editorMetadata = json_decode((string)($editItem['metadata'] ?? '{}'), true);
            $editorReturnLocation = is_array($editorMetadata) && is_array($editorMetadata['npc_manager_return_location'] ?? null)
                ? $editorMetadata['npc_manager_return_location']
                : null;
            ?>
            const targetId = <?= (int)($editItem['id'] ?? 0) ?>;
            const targetName = <?= json_encode((string)($editItem['npc_name'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const targetRefId = <?= json_encode((string)($editItem['refid'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const savedReturnLocation = <?= json_encode($editorReturnLocation, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const initialTeleportAction = savedReturnLocation ? 'return' : 'teleport';
            const initialReturnName = savedReturnLocation && savedReturnLocation.name ? String(savedReturnLocation.name) : '';
            const historyController = window.chimNpcEventHistory
                ? window.chimNpcEventHistory.mount(panels.history, {
                    npcId: targetId,
                    npcName: targetName,
                    apiUrl: '../api/chim_npc_manager.php'
                })
                : null;

            // The event log identifies NPCs by name only, so a shared name makes this history
            // shared too. Say so up front instead of implying the events belong to one RefID.
            const historySharedNameCount = <?= (int)npc_duplicate_count($npcNameCounts ?? [], $editItem['npc_name'] ?? '') ?>;
            if (historyController && historySharedNameCount > 1) {
                const sharedNotice = document.createElement('p');
                sharedNotice.className = 'npc-editor-action-note is-error';
                sharedNotice.setAttribute('role', 'note');
                sharedNotice.textContent = historySharedNameCount + ' profiles share the name "' + targetName
                    + '". The event log identifies NPCs by name only, so this history is shared by all of them'
                    + ' and injecting or deleting events is unavailable here.';
                panels.history.prepend(sharedNotice);

                const sharedInjectButton = panels.history.querySelector('[data-history-inject]');
                const sharedInjectText = panels.history.querySelector('[data-history-event-text]');
                if (sharedInjectButton) {
                    sharedInjectButton.disabled = true;
                    sharedInjectButton.title = 'Unavailable while other profiles share this name';
                }
                if (sharedInjectText) {
                    sharedInjectText.disabled = true;
                    sharedInjectText.placeholder = 'Unavailable while other profiles share this name.';
                }
            }
            panels.actions.innerHTML = `
                <p class="npc-editor-action-note">The game must be running and unpaused for Visit, Teleport, and Return NPC.</p>
                <div class="npc-editor-action-list">
                    <article class="npc-editor-action-card">
                        <div><h3>Visit</h3><p>Move the player to this NPC's current position.</p></div>
                        <button type="button" class="btn-cancel" data-npc-action="visit">Visit</button>
                    </article>
                    <article class="npc-editor-action-card">
                        <div><h3 data-npc-teleport-title>${initialTeleportAction === 'return' ? 'Return NPC' : 'Teleport'}</h3><p data-npc-teleport-description>${initialTeleportAction === 'return' ? 'Send this NPC back to ' + (initialReturnName || 'their previous location') + '.' : "Move this NPC to the player's current position and save their previous location."}</p></div>
                        <button type="button" class="btn-cancel" data-npc-action="${initialTeleportAction}" data-npc-teleport-button>${initialTeleportAction === 'return' ? 'Return NPC' : 'Teleport'}</button>
                    </article>
                </div>
                <p class="npc-editor-action-status" data-npc-action-status role="status" aria-live="polite"></p>`;

            const actionStatus = panels.actions.querySelector('[data-npc-action-status]');
            const teleportButton = panels.actions.querySelector('[data-npc-teleport-button]');
            const teleportTitle = panels.actions.querySelector('[data-npc-teleport-title]');
            const teleportDescription = panels.actions.querySelector('[data-npc-teleport-description]');
            panels.actions.querySelectorAll('[data-npc-action]').forEach(function(button){
                button.disabled = targetId <= 0;
                button.addEventListener('click', async function(){
                    const action = button.dataset.npcAction;

                    button.disabled = true;
                    actionStatus.textContent = 'Sending action...';
                    actionStatus.classList.remove('is-error');
                    try {
                        const response = await fetch('../api/chim_npc_manager.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({operation:'action', action:action, id:targetId})
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload || !payload.success) {
                            throw new Error((payload && payload.error) || ('HTTP ' + response.status));
                        }
                        actionStatus.textContent = (payload.data && payload.data.message) || 'Action sent.';
                        if (button === teleportButton && payload.data && payload.data.next_action) {
                            const nextAction = payload.data.next_action;
                            const returnName = payload.data.return_location ? String(payload.data.return_location) : '';
                            button.dataset.npcAction = nextAction;
                            button.textContent = nextAction === 'return' ? 'Return NPC' : 'Teleport';
                            teleportTitle.textContent = nextAction === 'return' ? 'Return NPC' : 'Teleport';
                            teleportDescription.textContent = nextAction === 'return'
                                ? 'Send this NPC back to ' + (returnName || 'their previous location') + '.'
                                : "Move this NPC to the player's current position and save their previous location.";
                        }
                    } catch (error) {
                        actionStatus.textContent = 'Action failed: ' + (error.message || error);
                        actionStatus.classList.add('is-error');
                    } finally {
                        button.disabled = targetId <= 0;
                    }
                });
            });
            if (targetId <= 0) actionStatus.textContent = 'Save this NPC before using actions.';

            panels['background-life'].insertAdjacentHTML('beforeend', `
                <div class="npc-bgl-dashboard span-2" data-npc-bgl-dashboard>
                    <section class="npc-bgl-section">
                        <div class="npc-bgl-section-header">
                            <div><h3>Current Background Life</h3><p>Live status and most recently recorded activity for this NPC.</p></div>
                            <button type="button" class="npc-bgl-state-button" data-bgl-enrollment disabled>Loading...</button>
                        </div>
                        <div class="npc-bgl-summary">
                            <div class="npc-bgl-summary-item"><span>Status</span><strong data-bgl-summary="status">Loading...</strong></div>
                            <div class="npc-bgl-summary-item"><span>Current Location</span><strong data-bgl-summary="location">Loading...</strong></div>
                            <div class="npc-bgl-summary-item"><span>Latest Activity</span><strong data-bgl-summary="activity">Loading...</strong></div>
                        </div>
                    </section>
                    <section class="npc-bgl-section">
                        <div class="npc-bgl-section-header"><div><h3>Rules</h3><p>Per-NPC controls used by Background Life.</p></div></div>
                        <div class="npc-bgl-control-grid">
                            <button type="button" class="npc-bgl-control" data-bgl-setting="auto_actions" disabled><span class="npc-bgl-control-dot"></span><span><strong>Auto Actions</strong><small>Allow scheduled autonomous actions.</small></span></button>
                            <button type="button" class="npc-bgl-control" data-bgl-setting="send_letters" disabled><span class="npc-bgl-control-dot"></span><span><strong>Send Letters</strong><small>Allow this NPC to send BgL letters.</small></span></button>
                            <button type="button" class="npc-bgl-control" data-bgl-setting="hourly_tracking" disabled><span class="npc-bgl-control-dot"></span><span><strong>Hourly Tracking</strong><small>Keep the NPC's map location updated.</small></span></button>
                        </div>
                    </section>
                    <section class="npc-bgl-section">
                        <div class="npc-bgl-section-header"><div><h3>Immediate Actions</h3><p>The game should be running and unpaused when requesting live activity.</p></div></div>
                        <div class="npc-bgl-request-grid">
                            <button type="button" class="npc-bgl-request" data-bgl-request="action" disabled>Trigger Action</button>
                            <button type="button" class="npc-bgl-request" data-bgl-request="letter" disabled>Send Letter</button>
                            <button type="button" class="npc-bgl-request" data-bgl-request="track" disabled>Update Location</button>
                        </div>
                        <div class="npc-bgl-inception">
                            <textarea data-bgl-inception-idea placeholder="Give this NPC a one-time thought for their next Background Life decision." disabled></textarea>
                            <button type="button" class="npc-bgl-request" data-bgl-inception disabled>Set Thought</button>
                        </div>
                    </section>
                    <section class="npc-bgl-section">
                        <div class="npc-bgl-section-header"><div><h3>Recent Activity</h3><p>The five most recent Background Life events.</p></div></div>
                        <ul class="npc-bgl-events" data-bgl-events><li class="npc-bgl-empty">Loading activity...</li></ul>
                    </section>
                    <p class="npc-editor-action-status" data-bgl-message role="status" aria-live="polite"></p>
                </div>`);

            const bglDashboard = panels['background-life'].querySelector('[data-npc-bgl-dashboard]');
            const bglMessage = bglDashboard.querySelector('[data-bgl-message]');
            const enrollmentButton = bglDashboard.querySelector('[data-bgl-enrollment]');
            const settingButtons = Array.from(bglDashboard.querySelectorAll('[data-bgl-setting]'));
            const requestButtons = Array.from(bglDashboard.querySelectorAll('[data-bgl-request]'));
            const inceptionButton = bglDashboard.querySelector('[data-bgl-inception]');
            const inceptionIdea = bglDashboard.querySelector('[data-bgl-inception-idea]');

            async function npcBglRequest(url, options){
                const response = await fetch(url, options);
                let payload = null;
                try { payload = await response.json(); } catch (_error) {}
                if (!response.ok || !payload || !payload.success) {
                    throw new Error((payload && payload.error) || ('HTTP ' + response.status));
                }
                return payload;
            }

            function npcBglPost(url, values){
                const body = new FormData();
                Object.entries(values).forEach(function(entry){ body.append(entry[0], entry[1]); });
                return npcBglRequest(url, {method:'POST', body:body});
            }

            function setBglMessage(message, isError){
                bglMessage.textContent = message || '';
                bglMessage.classList.toggle('is-error', Boolean(isError));
            }

            function renderBglEvents(events){
                const list = bglDashboard.querySelector('[data-bgl-events]');
                list.replaceChildren();
                if (!events.length) {
                    const empty = document.createElement('li');
                    empty.className = 'npc-bgl-empty';
                    empty.textContent = 'No Background Life activity recorded yet.';
                    list.appendChild(empty);
                    return;
                }
                events.slice(0, 5).forEach(function(event){
                    const item = document.createElement('li');
                    item.className = 'npc-bgl-event';
                    item.textContent = event.activity || 'Background Life activity';
                    if (event.tamrielic_time) {
                        const time = document.createElement('span');
                        time.className = 'npc-bgl-event-time';
                        time.textContent = event.tamrielic_time;
                        item.appendChild(time);
                    }
                    list.appendChild(item);
                });
            }

            function renderBglStatus(status, events){
                const enabled = status.background_life_enabled === true;
                const latest = events[0] || null;
                bglDashboard.dataset.enabled = enabled ? '1' : '0';
                enrollmentButton.dataset.enabled = enabled ? '1' : '0';
                enrollmentButton.textContent = enabled ? 'Background Life On' : 'Background Life Off';
                enrollmentButton.classList.toggle('is-on', enabled);
                bglDashboard.querySelector('[data-bgl-summary="status"]').textContent = enabled ? 'Active' : 'Not active';
                bglDashboard.querySelector('[data-bgl-summary="location"]').textContent = status.location || 'No tracked location';
                bglDashboard.querySelector('[data-bgl-summary="activity"]').textContent = latest && latest.activity
                    ? latest.activity
                    : 'No activity recorded';

                settingButtons.forEach(function(button){
                    const active = status[button.dataset.bglSetting] === true;
                    button.dataset.enabled = active ? '1' : '0';
                    button.classList.toggle('is-on', active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                    button.disabled = !enabled;
                });
                requestButtons.forEach(function(button){ button.disabled = !enabled; });
                inceptionButton.disabled = !enabled;
                inceptionIdea.disabled = !enabled;
                renderBglEvents(events);
            }

            async function loadBglData(){
                if (targetId <= 0) {
                    enrollmentButton.disabled = true;
                    settingButtons.concat(requestButtons, [inceptionButton]).forEach(function(button){ button.disabled = true; });
                    inceptionIdea.disabled = true;
                    setBglMessage('Save this NPC before using Background Life controls.', false);
                    renderBglStatus({background_life_enabled:false}, []);
                    return;
                }

                const statusQuery = new URLSearchParams({refid:targetRefId, npc_name:targetName});
                try {
                    const statusPayload = await npcBglRequest('../api/background_life_npc.php?' + statusQuery.toString());
                    let events = [];
                    try {
                        const detailQuery = new URLSearchParams({npc:targetName});
                        const detailPayload = await npcBglRequest('../api/background_life_npc_detail.php?' + detailQuery.toString());
                        events = Array.isArray(detailPayload.data && detailPayload.data.events) ? detailPayload.data.events : [];
                    } catch (_error) {}
                    enrollmentButton.disabled = false;
                    renderBglStatus(statusPayload.data || {}, events);
                } catch (error) {
                    enrollmentButton.disabled = true;
                    setBglMessage('Could not load Background Life: ' + (error.message || error), true);
                }
            }

            enrollmentButton.addEventListener('click', async function(){
                const enable = enrollmentButton.dataset.enabled !== '1';
                enrollmentButton.disabled = true;
                setBglMessage(enable ? 'Adding NPC to Background Life...' : 'Removing NPC from Background Life...', false);
                try {
                    const payload = await npcBglPost('../api/background_life_npc.php', {
                        operation: enable ? 'enable' : 'disable',
                        refid: targetRefId,
                        npc_name: targetName
                    });
                    renderBglStatus(payload.data || {}, []);
                    setBglMessage(payload.message || 'Background Life status saved.', false);
                    await loadBglData();
                } catch (error) {
                    setBglMessage('Could not change Background Life: ' + (error.message || error), true);
                } finally {
                    enrollmentButton.disabled = false;
                }
            });

            settingButtons.forEach(function(button){
                button.addEventListener('click', async function(){
                    const value = button.dataset.enabled !== '1';
                    button.disabled = true;
                    setBglMessage('Saving Background Life rule...', false);
                    try {
                        const payload = await npcBglPost('../api/background_life_npc.php', {
                            operation:'toggle',
                            refid:targetRefId,
                            npc_name:targetName,
                            setting:button.dataset.bglSetting,
                            value:value ? '1' : '0'
                        });
                        renderBglStatus(payload.data || {}, []);
                        setBglMessage(payload.message || 'Background Life rule saved.', false);
                        await loadBglData();
                    } catch (error) {
                        setBglMessage('Could not save Background Life rule: ' + (error.message || error), true);
                    } finally {
                        button.disabled = bglDashboard.dataset.enabled !== '1';
                    }
                });
            });

            requestButtons.forEach(function(button){
                button.addEventListener('click', async function(){
                    button.disabled = true;
                    setBglMessage('Processing Background Life request...', false);
                    try {
                        const payload = await npcBglPost('../api/background_life_request.php', {
                            request_type:button.dataset.bglRequest,
                            refid:targetRefId,
                            npc_name:targetName
                        });
                        setBglMessage(payload.message || 'Background Life request processed.', false);
                        await loadBglData();
                    } catch (error) {
                        setBglMessage('Background Life request failed: ' + (error.message || error), true);
                    } finally {
                        button.disabled = bglDashboard.dataset.enabled !== '1';
                    }
                });
            });

            inceptionButton.addEventListener('click', async function(){
                const idea = inceptionIdea.value.trim();
                if (!idea) {
                    setBglMessage('Enter a thought before setting Background Life inception.', true);
                    return;
                }
                inceptionButton.disabled = true;
                setBglMessage('Setting Background Life thought...', false);
                try {
                    const payload = await npcBglRequest('../api/chim_npc_manager.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({operation:'action', action:'bgl_inception', id:targetId, idea:idea})
                    });
                    inceptionIdea.value = '';
                    setBglMessage((payload.data && payload.data.message) || 'Background Life thought saved.', false);
                } catch (error) {
                    setBglMessage('Could not set Background Life thought: ' + (error.message || error), true);
                } finally {
                    inceptionButton.disabled = bglDashboard.dataset.enabled !== '1';
                }
            });

            loadBglData();

            const storageKey = tablist.dataset.storageKey || 'npc-editor-tab';
            function activate(section){
                if (!panels[section]) section = 'general';
                tablist.querySelectorAll('[data-npc-editor-tab]').forEach(function(button){
                    const active = button.dataset.npcEditorTab === section;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                    button.tabIndex = active ? 0 : -1;
                });
                Object.entries(panels).forEach(function(entry){ entry[1].hidden = entry[0] !== section; });
                if (section === 'history' && historyController) historyController.load();
                try { window.localStorage.setItem(storageKey, section); } catch (_e) {}
            }

            tablist.addEventListener('click', function(event){
                const button = event.target.closest('[data-npc-editor-tab]');
                if (button) activate(button.dataset.npcEditorTab);
            });
            tablist.addEventListener('keydown', function(event){
                if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
                const buttons = Array.from(tablist.querySelectorAll('[data-npc-editor-tab]'));
                let next = buttons.indexOf(document.activeElement);
                if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = buttons.length - 1;
                else next = (next + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
                event.preventDefault();
                buttons[next].focus();
                activate(buttons[next].dataset.npcEditorTab);
            });

            let initial = 'general';
            const resetForModal = <?= isset($_GET['partial']) && $_GET['partial'] === '1' ? 'true' : 'false' ?>;
            if (!resetForModal) {
                try { initial = window.localStorage.getItem(storageKey) || initial; } catch (_e) {}
            }
            activate(initial);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNpcEditorTabs);
    window.setTimeout(initNpcEditorTabs, 0);
})();
</script>
<div class="form-grid">
        <div class="form-item span-2">
            <label for="npc_name">NPC Name</label>
            <input type="text" id="npc_name" name="npc_name" placeholder="e.g. Aela the Huntress" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">
            <small class="hint">The character's name. Must match their Skyrim in-game name!</small>
        </div>

        <div class="form-item">
            <label for="profile_id">Profile</label>
            <select id="profile_id" name="profile_id">
                <option value="">-- Select Profile --</option>
                <?php foreach (($profileRows ?? []) as $pr): $pid=(string)($pr['id']??''); $lbl=$pr['label']??('Profile #'.$pid); $sel = ((string)($editItem['profile_id'] ?? '') === $pid) ? ' selected' : ((empty($editItem) && $firstProfileId === $pid) ? ' selected' : ''); ?>
                    <option value="<?= htmlspecialchars($pid) ?>"<?= $sel ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="hint">Select which profile the NPC uses.</small>
        </div>

        <div class="form-item">
            <label for="lock_profile" class="label-with-toggle">Lock This NPC
                <input type="hidden" name="lock_profile" value="0">
                <input type="checkbox" id="lock_profile" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
            </label>
            <small class="hint">Prevents dynamic systems from modifying this NPC's profile.</small>
        </div>

        <div class="form-item" style='<?= (isset($_GET['partial']) && $_GET['partial']=='1')?"display:none":"" ?>'>
            <label for="npc_favorite" class="label-with-toggle">Favorite
                <input type="checkbox" id="npc_favorite" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
            </label>
            <small class="hint">Pin this NPC for quick access.</small>
        </div>

        <div class="form-item">
            <label for="gender">Gender</label>
            <input type="text" id="gender" name="gender" placeholder="female, male" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">
            <small class="hint">Used for prompts.</small>
        </div>

        <div class="form-item">
            <label for="race">Race</label>
            <input type="text" id="race" name="race" placeholder="nord, dunmer, farm tool" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">
            <small class="hint">Lore-accurate race label used in prompts.</small>
        </div>

        <div class="form-item">
            <label for="base">Base</label>
            <input type="text" id="base" name="base" placeholder="Bandit Reaver" value="<?= htmlspecialchars($editItem["base"] ?? "") ?>">
            <small class="hint">Optional: base form identifier or template this NPC derives from.</small>
        </div>

        <?php
        $editMeta = [];
        if (is_array($editItem ?? null) && !empty($editItem['metadata'])) {
            $tmpEditMeta = json_decode((string)$editItem['metadata'], true);
            if (is_array($tmpEditMeta)) $editMeta = $tmpEditMeta;
        }
        $editChain = npc_mod_chain($editMeta);
        $editSource = npc_defining_mod($editItem ?? [], $editChain, $editMeta);
        $editActorBound = NpcMaster::isActorBound($editItem ?? []);
        $editRefid = npc_refid_display($editItem['refid'] ?? '');
        $editDupCount = npc_duplicate_count($npcNameCounts ?? [], $editItem['npc_name'] ?? '');
        ?>
        <div class="form-item">
            <label for="refid">Ref ID</label>
            <input type="text" id="refid" name="refid" placeholder="Game reference ID (000A2C94)" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>" readonly aria-describedby="refid_hint">
            <small class="hint" id="refid_hint">Read-only. Skyrim reference ID for in-game linkage<?= $editRefid['runtime'] ? ' (Runtime: assigned by the game, not stable across saves).' : '.' ?><?= $editActorBound ? ' This profile is bound to a specific actor, so a changed value is never saved.' : '' ?></small>
        </div>

        <div class="form-item span-2">
            <span class="npc-identity-label" id="actor_identity_label">Actor Identity</span>
            <div class="npc-identity-readonly" role="group" aria-labelledby="actor_identity_label">
                <div class="npc-identity-row"><span class="npc-muted">RefID:</span> <span class="npc-refid<?= $editRefid['known'] ? '' : ' npc-identity-unknown' ?>"><?= htmlspecialchars($editRefid['text']) ?></span><?php if ($editRefid['runtime']): ?> <span class="npc-runtime-chip">Runtime</span><?php endif; ?></div>
                <div class="npc-identity-row"><span class="npc-muted">Defining Mod:</span> <span class="<?= $editSource !== '' ? 'npc-source-single' : 'npc-source-single npc-identity-unknown' ?>"><?= htmlspecialchars($editSource !== '' ? $editSource : 'Unknown source') ?></span></div>
                <div class="npc-identity-row"><span class="npc-muted">Name Sharing:</span> <span><?= $editDupCount > 1 ? htmlspecialchars($editDupCount.' profiles share this name') : 'This name is unique' ?></span></div>
                <?php if ($editChain !== []): ?>
                <div class="npc-identity-row npc-identity-chain-row"><span class="npc-muted">Mod Chain:</span>
                    <ol class="npc-identity-chain">
                        <?php foreach ($editChain as $chainIndex => $chainMod): ?>
                        <li><span class="npc-source-chain-mod"><?= htmlspecialchars($chainMod) ?></span> <span class="npc-source-chain-role"><?= $chainIndex === 0 ? 'defining' : ($chainIndex === count($editChain) - 1 ? 'final override' : 'override') ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
            <small class="hint">Read-only. These values bind this profile to one game actor and tell same-named NPCs apart.</small>
        </div>

        <div class="form-item">
            <label for="oghma_knowledge_tags">Oghma Tags</label>
            <input type="text" id="oghma_knowledge_tags" name="oghma_knowledge_tags" placeholder="Comma-separated knowledge tags" value="<?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?>">
            <small class="hint">Used by Oghma systems for knowledge lookup restrictions.</small>
        </div>

        <div class="form-item">
            <label for="voiceid">Voice ID</label>
            <input type="text" id="voiceid" name="voiceid" placeholder="malenord" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">
            <small class="hint">Voice ID for TTS.</small>
        </div>

        <?php
        // Check profile-level settings for these features
        $profileDynEnabled = false;
        $profileMtmEnabled = false;
        $profileAutoDiaryEnabled = false;
        $profileAutoDiaryWaitEnabled = false;
        $currentProfileId = (string)(is_array($editItem) ? ($editItem['profile_id'] ?? '') : '');
        if ($currentProfileId !== '') {
            foreach (($profileConnRows ?? []) as $prow) {
                if ((string)($prow['id'] ?? '') === $currentProfileId) {
                    $pmeta = [];
                    try {
                        if (!empty($prow['metadata'])) {
                            $tmp = json_decode((string)$prow['metadata'], true);
                            if (is_array($tmp)) $pmeta = $tmp;
                        }
                    } catch (Throwable $e) {}
                    $dynVal = isset($pmeta['DYNAMIC_PROFILE_ENABLED']) ? $pmeta['DYNAMIC_PROFILE_ENABLED'] : null;
                    $mtmVal = isset($pmeta['MIDDLE_TERM_MEMORY_ENABLED']) ? $pmeta['MIDDLE_TERM_MEMORY_ENABLED'] : null;
                    $adVal = isset($pmeta['AUTO_DIARY_ENABLED']) ? $pmeta['AUTO_DIARY_ENABLED'] : null;
                    $adWaitVal = isset($pmeta['AUTO_DIARY_WAIT_ENABLED']) ? $pmeta['AUTO_DIARY_WAIT_ENABLED'] : null;
                    $profileDynEnabled = ($dynVal === '1' || $dynVal === 1 || $dynVal === true);
                    $profileMtmEnabled = ($mtmVal === '1' || $mtmVal === 1 || $mtmVal === true);
                    $profileAutoDiaryEnabled = ($adVal === '1' || $adVal === 1 || $adVal === true);
                    $profileAutoDiaryWaitEnabled = ($adWaitVal === '1' || $adWaitVal === 1 || $adWaitVal === true);
                    break;
                }
            }
        }
        
        // Dynamic Profile: check NPC override or fall back to profile default
        $dynChecked = $profileDynEnabled;
        $dynFromProfile = false;
        if (is_array($editItem) && isset($editItem['dynamic_profile']) && $editItem['dynamic_profile'] !== null && $editItem['dynamic_profile'] !== '') {
            // NPC has explicit value (override)
            $dynChecked = !empty($editItem['dynamic_profile']);
        } else {
            // No NPC override, inherit from profile
            $dynFromProfile = true;
        }
        
        // Middle Term Memory: check extended_data override or fall back to profile default
        $mtmChecked = $profileMtmEnabled;
        $mtmFromProfile = false;
        try {
            $hasNpcOverride = false;
            if (is_array($editItem) && !empty($editItem['extended_data'])) {
                $tmpEd = json_decode((string)$editItem['extended_data'], true);
                if (is_array($tmpEd) && array_key_exists('middle_term_enabled', $tmpEd) && $tmpEd['middle_term_enabled'] !== null && $tmpEd['middle_term_enabled'] !== '') {
                    $mtmChecked = !empty($tmpEd['middle_term_enabled']);
                    $hasNpcOverride = true;
                }
            }
            if (!$hasNpcOverride) {
                $mtmFromProfile = true;
            }
        } catch (Throwable $e) { }

        // Individual Memory Bank: NPC-only (no profile inheritance)
        $imbChecked = false;
        try {
            if (is_array($editItem) && !empty($editItem['extended_data'])) {
                $tmpEd = json_decode((string)$editItem['extended_data'], true);
                if (is_array($tmpEd) && array_key_exists('individual_memory_enabled', $tmpEd) && $tmpEd['individual_memory_enabled'] !== null && $tmpEd['individual_memory_enabled'] !== '') {
                    $imbChecked = !empty($tmpEd['individual_memory_enabled']);
                }
            }
        } catch (Throwable $e) { }
        
        // Auto Diary: check extended_data override or fall back to profile default
        $adChecked = $profileAutoDiaryEnabled;
        $adFromProfile = false;
        try {
            $hasNpcOverride = false;
            if (is_array($editItem) && !empty($editItem['extended_data'])) {
                $tmpEd = json_decode((string)$editItem['extended_data'], true);
                if (is_array($tmpEd) && array_key_exists('auto_diary_enabled', $tmpEd) && $tmpEd['auto_diary_enabled'] !== null && $tmpEd['auto_diary_enabled'] !== '') {
                    $adChecked = !empty($tmpEd['auto_diary_enabled']);
                    $hasNpcOverride = true;
                }
            }
            if (!$hasNpcOverride) {
                $adFromProfile = true;
            }
        } catch (Throwable $e) { }
        
        // Auto Diary Wait: check extended_data override or fall back to profile default
        $adWaitChecked = $profileAutoDiaryWaitEnabled;
        $adWaitFromProfile = false;
        try {
            $hasNpcOverride = false;
            if (is_array($editItem) && !empty($editItem['extended_data'])) {
                $tmpEd = json_decode((string)$editItem['extended_data'], true);
                if (is_array($tmpEd) && array_key_exists('auto_diary_wait_enabled', $tmpEd) && $tmpEd['auto_diary_wait_enabled'] !== null && $tmpEd['auto_diary_wait_enabled'] !== '') {
                    $adWaitChecked = !empty($tmpEd['auto_diary_wait_enabled']);
                    $hasNpcOverride = true;
                }
            }
            if (!$hasNpcOverride) {
                $adWaitFromProfile = true;
            }
        } catch (Throwable $e) { }
        
        // Read profile-level settings for the toggles
        $profileSalEnabled = false;
        if ($currentProfileId !== '') {
            foreach (($profileConnRows ?? []) as $prow) {
                if ((string)($prow['id'] ?? '') === $currentProfileId) {
                    $pmeta = [];
                    try {
                        if (!empty($prow['metadata'])) {
                            $tmp = json_decode((string)$prow['metadata'], true);
                            if (is_array($tmp)) $pmeta = $tmp;
                        }
                    } catch (Throwable $e) {}
                    $salVal = isset($pmeta['SALUTATION_AFTER_A_WHILE']) ? $pmeta['SALUTATION_AFTER_A_WHILE'] : null;
                    $profileSalEnabled = ($salVal === '1' || $salVal === 1 || $salVal === true);
                    break;
                }
            }
        }
        
        // Auto Greeting: check extended_data override or fall back to profile default
        $salChecked = $profileSalEnabled;
        $salFromProfile = false;
        try {
            $hasNpcOverride = false;
            if (is_array($editItem) && !empty($editItem['extended_data'])) {
                $tmpEd = json_decode((string)$editItem['extended_data'], true);
                if (is_array($tmpEd) && array_key_exists('salutation_after_a_while', $tmpEd) && $tmpEd['salutation_after_a_while'] !== null && $tmpEd['salutation_after_a_while'] !== '') {
                    $salChecked = !empty($tmpEd['salutation_after_a_while']);
                    $hasNpcOverride = true;
                }
            }
            if (!$hasNpcOverride) {
                $salFromProfile = true;
            }
        } catch (Throwable $e) { }
        ?>
        <div class="form-item">
            <label for="dynamic_profile" class="label-with-toggle">♻️Dynamic Profile
                <input type="hidden" name="dynamic_profile" value="0">
                <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?= $dynChecked ? "checked" : "" ?> data-profile-default="<?= $profileDynEnabled ? '1' : '0' ?>">
            </label>
            <small class="hint">Allow systems to evolve the profile based on gameplay events.<?= $dynFromProfile ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '' ?></small>
        </div>

        <div class="form-item">
            <label for="middle_term_enabled" class="label-with-toggle">📃Middle Term Memory
                <input type="checkbox" id="middle_term_enabled" name="middle_term_enabled" value="1" <?= $mtmChecked ? "checked" : "" ?> data-profile-default="<?= $profileMtmEnabled ? '1' : '0' ?>">
            </label>
            <small class="hint">Saves a list of recent events after every 10 memory summaries. Will be used for NPC context.<?= $mtmFromProfile ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '' ?></small>
        </div>

        <div class="form-item">
            <label for="individual_memory_enabled" class="label-with-toggle">🧠Individual Memory Bank
                <input type="checkbox" id="individual_memory_enabled" name="individual_memory_enabled" value="1" <?= $imbChecked ? "checked" : "" ?> data-profile-default="0">
            </label>
            <small class="hint">When enabled, this NPC will build and recall memories from their own scoped memory bank instead of global memory summaries.</small>
        </div>

        <div class="form-item">
            <label for="auto_diary_enabled" class="label-with-toggle">📙Auto Diary
                <input type="checkbox" id="auto_diary_enabled" name="auto_diary_enabled" value="1" <?= $adChecked ? "checked" : "" ?> data-profile-default="<?= $profileAutoDiaryEnabled ? '1' : '0' ?>">
            </label>
            <small class="hint">Automatically generate diary entries for this NPC when they are nearby during sleep/wait events.<?= $adFromProfile ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '' ?></small>
        </div>

        <div class="form-item">
            <label for="auto_diary_wait_enabled" class="label-with-toggle">⏳Auto Diary Wait
                <input type="checkbox" id="auto_diary_wait_enabled" name="auto_diary_wait_enabled" value="1" <?= $adWaitChecked ? "checked" : "" ?> data-profile-default="<?= $profileAutoDiaryWaitEnabled ? '1' : '0' ?>">
            </label>
            <small class="hint">When Auto Diary is enabled, this controls whether diary entries are created during wait events. If disabled, auto diary will only trigger on sleep events.<?= $adWaitFromProfile ? ' <strong style="color:rgb(242,124,17);">(Inherited from profile)</strong>' : '' ?></small>
        </div>

        <div class="form-item">
            <label for="salutation_after_a_while" class="label-with-toggle">👋Auto Greeting
                <input type="checkbox" id="salutation_after_a_while" name="salutation_after_a_while" value="1" <?= $salChecked ? "checked" : "" ?> data-profile-default="<?= $profileSalEnabled ? '1' : '0' ?>">
            </label>
            <small class="hint">NPC will automatically greet you after you have been away for a while.</small>
        </div>

        <div class="form-item span-2">
            <div class="prompt-head-label-row">
                <label for="prompt_head">Prompt Head Override</label>
                <button type="button" id="copy_current_prompt_head" class="prompt-head-copy-btn" title="Copy the Prompt Head inherited from the selected profile or Global Settings into this override">Copy Current</button>
            </div>
            <textarea id="prompt_head" name="prompt_head" placeholder="High-level system instructions injected before the core."><?= htmlspecialchars($editItem["prompt_head"] ?? "") ?></textarea>
            <small class="hint">System preamble inserted before other sections. Leave empty to inherit it from the selected profile or Global Settings.</small>
            <script>
            (function(){
                const promptHeadsByProfile = <?= json_encode($profilePromptHeadsById, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
                const globalPromptHead = <?= json_encode($globalPromptHead, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
                const button = document.getElementById('copy_current_prompt_head');
                const textarea = document.getElementById('prompt_head');
                const profileSelect = document.getElementById('profile_id');
                if (!button || !textarea) return;

                button.addEventListener('click', function(){
                    const profileId = profileSelect ? String(profileSelect.value || '') : '';
                    const inheritedPromptHead = Object.prototype.hasOwnProperty.call(promptHeadsByProfile, profileId)
                        ? String(promptHeadsByProfile[profileId] || '')
                        : String(globalPromptHead || '');
                    textarea.value = inheritedPromptHead;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));

                    button.textContent = 'Copied';
                    window.setTimeout(function(){ button.textContent = 'Copy Current'; }, 1200);
                });
            })();
            </script>
        </div>

        <div class="form-item span-2">
            <label for="core">Core</label>
            <textarea id="core" name="core" placeholder="Unchanging rules, boundaries, and core identity."><?= htmlspecialchars($editItem["core"] ?? "") ?></textarea>
            <small class="hint">Core NPC description. 1-2 sentences describing the character.</small>
        </div>

        <div class="form-item span-2">
            <label for="npc_static_bio">Backstory</label>
            <textarea id="npc_static_bio" name="npc_static_bio" placeholder="Fixed background, history, and facts."><?= htmlspecialchars($editItem["npc_static_bio"] ?? "") ?></textarea>
            <small class="hint">Historical facts and background information.</small>
        </div>

        <div class="form-item span-2">
            <label for="appearance">Appearance</label>
            <textarea id="appearance" name="appearance" placeholder="Physical appearance."><?= htmlspecialchars($editItem["appearance"] ?? "") ?></textarea>
            <button id="small_update_appearance" type="button" class="btn-base" style="margin-top:6px; padding:6px 12px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; cursor:pointer; font-weight:500;" title="Will use AI to describe NPC's appearance using their profile picture. Will use ITT service.">Update From Profile Picture</button>
            <small class="hint">Physical appearance. Keep it limited to character cosmetics, not equipment.</small>
        </div>

        <div class="dynamic-profile-section span-2">
        <div class="form-item">
            <label for="personality">Personality</label>
            <textarea id="personality" name="personality" placeholder="Personality traits and speaking characteristics."><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>
            <small class="hint">Traits and quirks that guide tone and behavior.</small>
        </div>

        

        <style>
            #relationship-editor-section {
                margin-top: 0;
            }

            #relationship-editor-section > details.metadata-skills-view {
                border: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                background: transparent !important;
                margin-top: 0 !important;
                box-shadow: none !important;
            }

            #relationship-editor-section > details.metadata-skills-view > summary {
                display: none !important;
            }

            #relationship-editor-section > details.metadata-skills-view > small.hint {
                display: none !important;
            }
        </style>

        <div class="form-item">
            <label>Relationships</label>
        </div>

        <?php if (file_exists(__DIR__."/../../ext/relationship_system/relationship_editor.php")) {
            // The embedded editor uses $data for each relationship; preserve the NPC list in this parent scope.
            $npcListRows = $data;
            include(__DIR__."/../../ext/relationship_system/relationship_editor.php");
            $data = $npcListRows;
            unset($npcListRows);
        } ?>
        <section id="relationship-change-history" class="form-item span-2 npc-relationship-history" aria-labelledby="relationship-change-history-title">
            <div class="npc-relationship-history-header">
                <h3 id="relationship-change-history-title">Recent Relationship Changes</h3>
                <p>Read-only history for this NPC. The current relationships above remain editable.</p>
            </div>
            <?php if (empty($npcRelationshipChanges)): ?>
                <p class="npc-relationship-history-empty">No relationship changes recorded for this NPC yet.</p>
            <?php else: ?>
                <ol class="npc-relationship-history-list">
                    <?php foreach ($npcRelationshipChanges as $relationshipChange): ?>
                        <?php
                        $localTimestamp = intval($relationshipChange['localts'] ?? 0);
                        $gameTimestamp = intval($relationshipChange['gamets'] ?? 0);
                        $timeParts = [];
                        if ($gameTimestamp > 0) {
                            $timeParts[] = convert_gamets2skyrim_long_date2($gameTimestamp);
                        }
                        if ($localTimestamp > 0) {
                            $timeParts[] = gmdate('j M Y, H:i', $localTimestamp) . ' UTC';
                        }
                        $timeLabel = implode(' · ', $timeParts);
                        ?>
                        <li class="npc-relationship-history-item">
                            <?php
                            // The shared event-log renderer owns badge, stored reason, target and tier,
                            // including snapshots that moved several relationships at once. The prose
                            // summary is only the fallback for older rows with no structured detail.
                            echo chimRenderRelationshipChangeCellHtml(
                                $relationshipChange['changes'] ?? [],
                                $relationshipChange['data'] ?? ''
                            );
                            ?>
                            <?php if ($timeLabel !== ''): ?>
                                <?php /* The page is already scoped to this NPC, so the row only adds its time. */ ?>
                                <time class="npc-relationship-history-time" datetime="<?= $localTimestamp > 0 ? htmlspecialchars(gmdate('c', $localTimestamp), ENT_QUOTES, 'UTF-8') : '' ?>"><?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?></time>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
<div class="form-item">
            <label for="occupation">Occupation</label>
            <textarea id="occupation" name="occupation" placeholder="Role, job, affiliations."><?= htmlspecialchars($editItem["occupation"] ?? "") ?></textarea>
            <small class="hint">Primary role or job. Include relevant guilds or factions.</small>
        </div>

        <div class="form-item">
            <label for="skills">Skills</label>
            <textarea id="skills" name="skills" placeholder="Strengths, abilities, and specialties."><?= htmlspecialchars($editItem["skills"] ?? "") ?></textarea>
            <small class="hint">Highlight notable competencies of the NPC.</small>
        </div>

        

        <div class="form-item">
            <label for="speechstyle">Speech Style</label>
            <textarea id="speechstyle" name="speechstyle" placeholder="Dialect, cadence, verbal tics."><?= htmlspecialchars($editItem["speechstyle"] ?? "") ?></textarea>
            <small class="hint">How the NPC speaks their dialogue.</small>
        </div>

        <div class="form-item">
            <label for="goals">Goals</label>
            <textarea id="goals" name="goals" placeholder="Short and long-term objectives."><?= htmlspecialchars($editItem["goals"] ?? "") ?></textarea>
            <small class="hint">General motivations and goals used during regular dialogue and Background Life.</small>
        </div>

        <?php
        $backgroundLifeGoals = '';
        if (!empty($editItem['extended_data'])) {
            $backgroundLifeExtendedData = json_decode((string)$editItem['extended_data'], true);
            if (is_array($backgroundLifeExtendedData)) {
                $backgroundLifeGoals = trim((string)($backgroundLifeExtendedData['background_life_goals'] ?? ''));
            }
        }
        ?>
        <div class="form-item span-2">
            <label for="background_life_goals">Background Life Goals</label>
            <textarea id="background_life_goals" name="background_life_goals" placeholder="Goals, plans, or production rules for this NPC's Background Life."><?= htmlspecialchars($backgroundLifeGoals) ?></textarea>
            <small class="hint">Used only for Background Life decisions. This content is not included in regular dialogue prompts.</small>
        </div>


        <div class="form-item span-2">
            <label for="emote_moods">Emote Moods Override</label>
            <textarea id="emote_moods" name="emote_moods" placeholder="Allowed mood/emote set (comma-separated).">
            <?= htmlspecialchars($editItem["emote_moods"] ?? "") ?></textarea>
            <small class="hint">Whitelist of mood/emote cues the NPC may use (e.g., calm, angry, playful). <strong>Overrides</strong> the global EMOTEMOODS setting. Leave empty to use global default.</small>
        </div>

        <?php
        $mtmLatest = '';
        try {
            if (!empty($editItem['extended_data'])){
                $ed = json_decode((string)$editItem['extended_data'], true);
                if (is_array($ed) && !empty($ed['middle_term_memory']) && is_array($ed['middle_term_memory'])){
                    $arr = array_values($ed['middle_term_memory']);
                    if (!empty($arr)) { $mtmLatest = (string)end($arr); }
                }
            }
        } catch (Throwable $e) { $mtmLatest = ''; }
        ?>
        <div class="form-item span-2">
            <label for="middle_term_latest">Recent Middle Term Memory</label>
            <textarea id="middle_term_latest" name="middle_term_latest" placeholder="No middle term memory yet."><?= htmlspecialchars($mtmLatest) ?></textarea>
            <small class="hint">Manual edits save to the latest middle-term memory entry. Future auto-generated summaries continue appending after your edit.</small>
        </div>

        <?php
        // REINSERT Skills, Equipment, Stats, Inventory sections here (below Middle Term Memory)
        // Read metadata once
        $metaRaw = '';
        $metaObj = [];
        try {
            if (is_array($editItem ?? null) && !empty($editItem['metadata'])) {
                $metaRaw = (string)$editItem['metadata'];
                if ($metaRaw !== '') { $metaObj = json_decode($metaRaw, true) ?: []; }
            }
        } catch (Throwable $e) { $metaObj = []; }
        // Skills
        $metadataSkills = (isset($metaObj['skills']) && is_array($metaObj['skills'])) ? $metaObj['skills'] : [];
        ?>
        <div class="form-item span-2">
            <details class="metadata-skills-view" style="border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#262626;">
                <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17);">Skills</summary>
                <small class="hint">These will also be used for Skill context.</small>
                <div style="margin-top:8px; color:#cfd9ea;">
                    <?php if (!empty($metadataSkills)): ?>
                        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:6px 12px;">
                            <?php foreach ($metadataSkills as $sName => $sVal): $label = ucfirst((string)$sName); $disp = (is_numeric($sVal) ? (string)intval($sVal) : ((is_string($sVal) && trim($sVal)!=='') ? htmlspecialchars($sVal) : '—')); ?>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <div style="color:rgb(242, 124, 17); min-width:140px;"><?= htmlspecialchars($label) ?></div>
                                    <div style="border:1px solid #4a4a4a; border-radius:6px; padding:4px 8px; min-width:40px;"><?= $disp ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="color:#9fb1c9;">No in-game skills found in metadata.</div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php
        // Equipment
        $metadataEquipment = (isset($metaObj['equipment']) && is_array($metaObj['equipment'])) ? $metaObj['equipment'] : [];
        ?>
        <div class="form-item span-2">
            <details class="metadata-equipment-view" style="border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#262626;">
                <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17);">Current Equipment</summary>
                <small class="hint">Equipment NPC had when first added to AI system.</small>
                <div style="margin-top:8px; color:#cfd9ea;">
                    <?php if (!empty($metadataEquipment)): ?>
                        <?php 
                        $equipmentGroups = [
                            'Vanilla Slots' => chimEquipmentVanillaSlotLabels(),
                            'Modded Slots' => chimEquipmentModdedSlotLabels(),
                        ];
                        ?>
                        <?php foreach ($equipmentGroups as $groupLabel => $equipmentSlots): ?>
                            <div style="color:#f27c11; font-weight:700; margin:10px 0 6px;"><?= htmlspecialchars($groupLabel) ?></div>
                            <div style="border:1px solid #3d4654; border-radius:8px; background:#20242b; padding:10px; margin-bottom:10px;">
                                <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px;">
                                    <?php foreach ($equipmentSlots as $slot => $label):
                                        $item = isset($metadataEquipment[$slot]) ? trim((string)$metadataEquipment[$slot]) : '';
                                        $display = ($item !== '') ? htmlspecialchars($item) : '<span style="color:#666">None</span>';
                                    ?>
                                        <div style="color:rgb(242, 124, 17); font-weight:600;"><?= htmlspecialchars($label) ?></div>
                                        <div style="border:1px solid #4a4a4a; border-radius:6px; padding:4px 8px; background:#262626;"><?= $display ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color:#9fb1c9;">No equipment data found in metadata.</div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php
        // Stats
        $metadataStats = (isset($metaObj['stats']) && is_array($metaObj['stats'])) ? $metaObj['stats'] : [];
        ?>
        <div class="form-item span-2">
            <details class="metadata-stats-view" style="border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#262626;">
                <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17);">Character Stats</summary>
                <small class="hint">NPC stats when first added to AI system.</small>
                <div style="margin-top:8px; color:#cfd9ea;">
                    <?php if (!empty($metadataStats)): ?>
                        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px 24px;">
                            <div style="display:flex; gap:8px; align-items:center;">
                                <div style="color:rgb(242, 124, 17); min-width:100px; font-weight:700;">Level</div>
                                <div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px 12px; font-weight:700; background:#1a1a1a; font-size:16px;">
                                    <?= isset($metadataStats['level']) ? intval($metadataStats['level']) : '—' ?>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <div style="color:#f39c12; min-width:100px; font-weight:700;">📏 Scale</div>
                                <div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px 12px; font-weight:700; background:#1a1a1a; font-size:16px;">
                                    <?php 
                                    $scale = isset($metadataStats['scale']) ? floatval($metadataStats['scale']) : null;
                                    if ($scale !== null) {
                                        echo number_format($scale, 2);
                                        // Get height description if available
                                        require_once(__DIR__ . "/../../lib/data_functions.php");
                                        $heightDesc = getHeightDescription($scale);
                                        if (!empty($heightDesc)) {
                                            echo ' <span style="color:#999; font-size:12px; font-weight:400;">(' . htmlspecialchars($heightDesc) . ')</span>';
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <div style="color:#e74c3c; min-width:100px;">❤️ Health</div>
                                <div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px 12px; background:#1a1a1a; flex:1;">
                                    <?php 
                                    $health = isset($metadataStats['health']) ? floatval($metadataStats['health']) : 0;
                                    $healthMax = isset($metadataStats['health_max']) ? floatval($metadataStats['health_max']) : 0;
                                    $healthPercent = ($healthMax > 0) ? round(($health / $healthMax) * 100) : 0;
                                    ?>
                                    <?= intval($health) ?> / <?= intval($healthMax) ?> <span style="color:#999;">(<?= $healthPercent ?>%)</span>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <div style="color:#3498db; min-width:100px;">💧 Magicka</div>
                                <div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px 12px; background:#1a1a1a; flex:1;">
                                    <?php 
                                    $magicka = isset($metadataStats['magicka']) ? floatval($metadataStats['magicka']) : 0;
                                    $magickaMax = isset($metadataStats['magicka_max']) ? floatval($metadataStats['magicka_max']) : 0;
                                    $magickaPercent = ($magickaMax > 0) ? round(($magicka / $magickaMax) * 100) : 0;
                                    ?>
                                    <?= intval($magicka) ?> / <?= intval($magickaMax) ?> <span style="color:#999;">(<?= $magickaPercent ?>%)</span>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <div style="color:#2ecc71; min-width:100px;">⚡ Stamina</div>
                                <div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px 12px; background:#1a1a1a; flex:1;">
                                    <?php 
                                    $stamina = isset($metadataStats['stamina']) ? floatval($metadataStats['stamina']) : 0;
                                    $staminaMax = isset($metadataStats['stamina_max']) ? floatval($metadataStats['stamina_max']) : 0;
                                    $staminaPercent = ($staminaMax > 0) ? round(($stamina / $staminaMax) * 100) : 0;
                                    ?>
                                    <?= intval($stamina) ?> / <?= intval($staminaMax) ?> <span style="color:#999;">(<?= $staminaPercent ?>%)</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color:#9fb1c9;">No stats data found in metadata.</div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php
        // Inventory
        $metadataInventory = (isset($metaObj['inventory']) && is_array($metaObj['inventory'])) ? $metaObj['inventory'] : [];
        $inventoryUpdated = isset($metaObj['inventory_updated']) ? $metaObj['inventory_updated'] : null;
        ?>
        <div class="form-item span-2">
            <details class="metadata-inventory-view" style="border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#262626;">
                <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17);">
                    Inventory
                    <?php if ($inventoryUpdated): ?>
                        <span style="color:#999; font-weight:400; font-size:12px;">
                            Last updated: <?= date('Y-m-d H:i:s', $inventoryUpdated) ?>
                        </span>
                    <?php endif; ?>
                </summary>
                <small class="hint">NPC inventory updated in real-time as items are added/removed.</small>
                <div style="margin-top:8px; color:#cfd9ea;">
                    <?php if (!empty($metadataInventory)): ?>
                        <div style="max-height:400px; overflow-y:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:2px solid #4a4a4a; color:rgb(242, 124, 17);">
                                        <th style="text-align:left; padding:8px;">Item</th>
                                        <th style="text-align:right; padding:8px; width:100px;">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($metadataInventory, function($a, $b){ return strcmp($a['name']??'', $b['name']??''); });
                                    foreach ($metadataInventory as $item): 
                                        $itemName = isset($item['name']) ? htmlspecialchars($item['name']) : 'Unknown';
                                        $itemCount = isset($item['count']) ? intval($item['count']) : 0;
                                    ?>
                                        <tr style="border-bottom:1px solid #3a3a3a;">
                                            <td style="padding:6px 8px; color:#cfd9ea;"><?= $itemName ?></td>
                                            <td style="padding:6px 8px; text-align:right; color:#9fb1c9; font-weight:600;">×<?= $itemCount ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top:12px; padding:8px; background:#1a1a1a; border-radius:6px; border:1px solid #4a4a4a;">
                                <strong style="color:rgb(242, 124, 17);">Total Items:</strong> 
                                <span style="color:#cfd9ea;"><?= count($metadataInventory) ?> unique items</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color:#9fb1c9;">No inventory data found in metadata.</div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php
        // Spells
        $metadataSpells = (isset($metaObj['spells']) && is_array($metaObj['spells'])) ? $metaObj['spells'] : [];
        $spellsUpdated = isset($metaObj['spells_updated']) ? $metaObj['spells_updated'] : null;
        ?>
        <div class="form-item span-2">
            <details class="metadata-spells-view" style="border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#262626;">
                <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17);">
                    Spells
                    <?php if ($spellsUpdated): ?>
                        <span style="color:#999; font-weight:400; font-size:12px;">
                            Last updated: <?= date('Y-m-d H:i:s', $spellsUpdated) ?>
                        </span>
                    <?php endif; ?>
                </summary>
                <small class="hint">Magic spells this NPC knows. Note: Spells will only be placed into NPC context if it exists in the description database. This is to prevent
                  system spells from custom mods from diluting the context.
                </small>
                <div style="margin-top:8px; color:#cfd9ea;">
                    <?php if (!empty($metadataSpells)): ?>
                        <div style="max-height:400px; overflow-y:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:2px solid #4a4a4a; color:rgb(242, 124, 17);">
                                        <th style="text-align:left; padding:8px;">Spell Name</th>
                                        <th style="text-align:center; padding:8px; width:130px;">Casting</th>
                                        <th style="text-align:center; padding:8px; width:120px;">Delivery</th>
                                        <th style="text-align:right; padding:8px; width:100px;">Base ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Casting type labels
                                    $castingTypes = [
                                        0 => 'Concentration',
                                        1 => 'Fire & Forget',
                                        2 => 'Constant'
                                    ];
                                    // Delivery type labels
                                    $deliveryTypes = [
                                        0 => 'Self',
                                        1 => 'Contact',
                                        2 => 'Aimed',
                                        3 => 'Target Actor',
                                        4 => 'Target Location'
                                    ];
                                    
                                    usort($metadataSpells, function($a, $b){ return strcmp($a['name']??'', $b['name']??''); });
                                    foreach ($metadataSpells as $spell): 
                                        $spellName = isset($spell['name']) ? htmlspecialchars($spell['name']) : 'Unknown';
                                        $spellID = isset($spell['baseid']) ? htmlspecialchars($spell['baseid']) : '—';
                                        $castingType = isset($spell['casting_type']) ? intval($spell['casting_type']) : 0;
                                        $deliveryType = isset($spell['delivery']) ? intval($spell['delivery']) : 0;
                                        $castingLabel = $castingTypes[$castingType] ?? 'Unknown';
                                        $deliveryLabel = $deliveryTypes[$deliveryType] ?? 'Unknown';
                                    ?>
                                        <tr style="border-bottom:1px solid #3a3a3a;">
                                            <td style="padding:6px 8px; color:#cfd9ea;"><?= $spellName ?></td>
                                            <td style="padding:6px 8px; text-align:center; color:#9fb1c9; font-size:12px;"><?= $castingLabel ?></td>
                                            <td style="padding:6px 8px; text-align:center; color:#9fb1c9; font-size:12px;"><?= $deliveryLabel ?></td>
                                            <td style="padding:6px 8px; text-align:right; color:#9fb1c9; font-family:monospace; font-size:11px;"><?= $spellID ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top:12px; padding:8px; background:#1a1a1a; border-radius:6px; border:1px solid #4a4a4a;">
                                <strong style="color:rgb(242, 124, 17);">Total Spells:</strong> 
                                <span style="color:#cfd9ea;"><?= count($metadataSpells) ?> spells known</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color:#9fb1c9;">No spell data found in metadata.</div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <div class="form-item span-2">
            <details class="npc-metadata-collapse" id="npc_metadata_collapse">
                <summary>Metadata (JSON)</summary>
                <div class="npc-metadata-collapse-body">
                    <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
                    <small class="hint">General NPC metadata used by systems.</small>
                    <div id="metadata"></div>
                </div>
            </details>
        </div>

        <div class="form-item span-2">
            <label for="extended_data">Setting Overrides</label>
            <small class="hint">Override global and profile settings for this specific NPC. Changes here take precedence over all other configurations.</small>
            <?php
            // Configure override editor for NPC mode
            $reservedKeys = [
                'middle_term_enabled',
                'individual_memory_enabled',
                'auto_diary_enabled',
                'auto_diary_wait_enabled',
                'background_life_goals',
                'chim_core_migrated',
                'salutation_after_a_while',
                'relationships',
                'relationships_updated',
                'relationships_model',
                'relationships_inferred',
                'relationships_last_eval',
                'relationships_analyzed'
            ];
            $extendedDataRaw = isset($editItem["extended_data"]) ? $editItem["extended_data"] : '{}';
            $extendedDataObj = json_decode($extendedDataRaw, true) ?: [];
            $npcOverrideCatalog = chimGetOverrideableGeneralSettingsCatalog();
            $currentOverrides = [];
            $systemFields = [];
            foreach ($extendedDataObj as $key => $value) {
                if (in_array($key, $reservedKeys, true)) {
                    $systemFields[$key] = $value;
                } else {
                    $currentOverrides[$key] = $value;
                }
            }
            $overrideEditorConfig = [
                'mode' => 'npc',
                'fieldName' => 'extended_data',
                'settingsCatalog' => $npcOverrideCatalog,
                'reservedKeys' => $reservedKeys,
                'currentData' => $currentOverrides,
                'systemFields' => $systemFields,
            ];
            include(__DIR__."/tmpl/override_editor.php");
            ?>
            <textarea name="extended_data" style="display:none"><?= htmlspecialchars($editItem["extended_data"] ?? "") ?></textarea>
        </div>
    </div>

    <?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ?>
        <button type="button" id="npc_modal_save" class="btn-save" style="display:none"><?= $editItem ? "Update" : "Create" ?></button>
        <script>
        (function(){
            const save = document.getElementById('npc_modal_save');
            if (!save) return;
            save.addEventListener('click', async function(){
                let form = save.closest('form');
                
                // Sync extended data overrides from visual UI
                try {
                  if (typeof window.syncExtendedDataOverrides === 'function') {
                    window.syncExtendedDataOverrides();
                  }
                } catch(_e) { console.error('Failed to sync extended data overrides:', _e); }
                
                // Sync feature checkboxes into extended_data (only save if differs from profile default)
                try {
                  const mtm = form.querySelector('#middle_term_enabled');
                  const imb = form.querySelector('#individual_memory_enabled');
                  const ad = form.querySelector('#auto_diary_enabled');
                  const adWait = form.querySelector('#auto_diary_wait_enabled');
                  const sal = form.querySelector('#salutation_after_a_while');
                  const dyn = form.querySelector('#dynamic_profile');
                  if (form.extended_data){
                    let obj = {};
                    try { obj = JSON.parse(String(form.extended_data.value||'')||'{}')||{}; } catch(_e){ obj = {}; }
                    
                    // MTM: only save if differs from profile default
                    if (mtm) {
                      const profileDefault = mtm.getAttribute('data-profile-default') === '1';
                      if (mtm.checked !== profileDefault) {
                        obj.middle_term_enabled = mtm.checked ? 1 : 0;
                      } else {
                        delete obj.middle_term_enabled; // Remove to inherit from profile
                      }
                    }

                    // Individual memory bank is NPC-only (no profile inheritance)
                    if (imb) {
                      if (imb.checked) {
                        obj.individual_memory_enabled = 1;
                      } else {
                        delete obj.individual_memory_enabled;
                      }
                    }
                    
                    // Auto Diary: only save if differs from profile default
                    if (ad) {
                      const profileDefault = ad.getAttribute('data-profile-default') === '1';
                      if (ad.checked !== profileDefault) {
                        obj.auto_diary_enabled = ad.checked ? 1 : 0;
                      } else {
                        delete obj.auto_diary_enabled; // Remove to inherit from profile
                      }
                    }
                    
                    // Auto Diary Wait: only save if differs from profile default
                    if (adWait) {
                      const profileDefault = adWait.getAttribute('data-profile-default') === '1';
                      if (adWait.checked !== profileDefault) {
                        obj.auto_diary_wait_enabled = adWait.checked ? 1 : 0;
                      } else {
                        delete obj.auto_diary_wait_enabled; // Remove to inherit from profile
                      }
                    }
                    
                    // Auto Greeting: only save if differs from profile default
                    if (sal) {
                      const profileDefault = sal.getAttribute('data-profile-default') === '1';
                      if (sal.checked !== profileDefault) {
                        obj.salutation_after_a_while = sal.checked ? true : false;
                      } else {
                        delete obj.salutation_after_a_while; // Remove to inherit from profile
                      }
                    }
                    
                    form.extended_data.value = JSON.stringify(obj);
                  }
                  
                  // Dynamic Profile: handled separately in form POST
                  if (dyn) {
                    const profileDefault = dyn.getAttribute('data-profile-default') === '1';
                    const dynHidden = form.querySelector('input[type="hidden"][name="dynamic_profile"]');
                    if (dyn.checked !== profileDefault) {
                      // Override: set explicit value
                      if (dynHidden) dynHidden.value = dyn.checked ? '1' : '0';
                      dyn.value = dyn.checked ? '1' : '0';
                    } else {
                      // Inherit: send empty/null to clear override
                      if (dynHidden) dynHidden.value = '';
                      dyn.value = '';
                    }
                  }
                } catch(_e){ console.error('Failed to sync feature toggles:', _e); }

                // Sync edited middle_term_latest back into extended_data JSON
                /*
                try {
                  const mtmLatest = form.querySelector('#middle_term_latest');
                  if (mtmLatest && form.extended_data){
                    let obj = {};
                    try { obj = JSON.parse(String(form.extended_data.value||'')||'{}')||{}; } catch(_e){ obj = {}; }
                    const editedVal = String(mtmLatest.value||'').trim();
                    if (editedVal !== '') {
                      if (!Array.isArray(obj.middle_term_memory)) {
                        obj.middle_term_memory = [];
                      }
                      if (obj.middle_term_memory.length > 0) {
                        obj.middle_term_memory[obj.middle_term_memory.length - 1] = editedVal;
                      } else {
                        obj.middle_term_memory.push(editedVal);
                      }
                    }
                    form.extended_data.value = JSON.stringify(obj);
                    
                  }
                } catch(_e){ console.error('Failed to sync middle term memory:', _e); }
                */
                if (form.metadata!=undefined && typeof jsonEditor !== 'undefined' && jsonEditor && typeof jsonEditor.get === 'function') {
                  const content = jsonEditor.get()

                  try {
                    form.metadata.value=JSON.stringify(content.json, null, 0)
                    console.log("JSON editor values copied to form:",content.json)
                  } catch (idontcare) {}
        
                  // allow empty metadata without confirmation
                }

                if (typeof syncRelationshipsToHidden === 'function') {
                  syncRelationshipsToHidden();
                }

                const fd = new FormData(form);
                fd.append('inline_update_npc','1');
                if (!fd.has('id') && <?= json_encode(!empty($editItem['id'])) ?>){ fd.append('id', <?= json_encode($editItem['id'] ?? '') ?>); }
                const res = await fetch('npc_master.php', { method:'POST', body: fd });
                let json={}; try{ json=await res.json(); } catch(_e){}
                if (json && json.ok){
                    const payload = {};
                    form.querySelectorAll('input,textarea,select').forEach(el=>{ const n=el.name; if (!n) return; if (el.type==='checkbox'){ payload[n]=el.checked?1:0; } else { payload[n]=el.value; } });
                    // Ensure header tags input is captured
                    try { const ti = document.getElementById('modal_tags_input'); if (ti) payload['tags'] = ti.value; } catch(_){ }
                    const newId = json.id || payload.id || <?= json_encode($editItem['id'] ?? '') ?>;
                    payload.id = newId;
                    window.parent.postMessage({ type:'npc_saved', id: newId, data: payload }, '*');
                } else {
                    alert('Save failed: '+((json && json.error) ? json.error : res.status));
                }
            });
        })();
        </script>
        <script>
        (function(){
            const favBtn = document.getElementById('modal_fav_btn');
            const idVal = <?= json_encode($editItem['id'] ?? '') ?>;
            if (favBtn && idVal){
                favBtn.addEventListener('click', async function(e){
                    e.preventDefault();
                    try{
                        const fd = new FormData(); fd.append('toggle_favorite','1'); fd.append('id', idVal);
                        const res = await fetch('npc_master.php', { method:'POST', body: fd });
                        let json={}; try{ json=await res.json(); }catch(_e){}
                        if (json && json.ok){ const active = Number(json.favorite||0)===1; favBtn.classList.toggle('active', active); favBtn.textContent = active ? '★' : '☆'; }
                    }catch(_e){}
                });
            }
        })();
        </script>
    <?php } else { ?>
        <button type="submit" name="<?= $editItem ? "update" : "create" ?>" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
    <?php } ?>
</form>
<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ?>
    <?php include(__DIR__."/tmpl/metadata_json_editor.php"); ?>
    </div>
    <?php exit; } ?>
</div>

<style>
.npc-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; }
@media (max-width: 1400px){ .npc-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1100px){ .npc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px){ .npc-grid { grid-template-columns: 1fr; } }
.npc-card { 
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98)); 
    border: 1px solid #3a3a3a; 
    border-radius: 10px; 
    padding: 16px; 
    display: flex; 
    flex-direction: column; 
    gap: 10px; 
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                inset 0 1px rgba(255, 255, 255, 0.03); 
    transition: all 0.2s ease; 
    cursor: pointer; 
}
.npc-card:hover { 
    transform: translateY(-2px); 
    background: linear-gradient(180deg, rgba(48, 48, 48, 0.95), rgba(40, 40, 40, 0.98)); 
    border-color: #4a4a4a;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25),
                inset 0 1px rgba(255, 255, 255, 0.05);
}
.npc-title { font-weight:800; color:#e9efff; font-size:18px; text-align:center; letter-spacing:0.3px; display:flex; align-items:flex-start; justify-content:space-between; gap:8px; min-width:0; }
.npc-title-left { flex:1 1 auto; min-width:0; text-align:left; display:flex; align-items:center; flex-wrap:wrap; column-gap:4px; }
.npc-title-actions { display:flex; align-items:center; justify-content:flex-end; gap:6px; flex:0 0 auto; max-width:none; min-width:0; flex-wrap:nowrap; white-space:nowrap; }
.npc-title-actions > * { flex:0 0 auto; }
.npc-name { display:-webkit-box; max-width:min(100%, 22ch); overflow:hidden; overflow-wrap:anywhere; white-space:normal; -webkit-box-orient:vertical; -webkit-line-clamp:2; line-clamp:2; line-height:1.25; }
.npc-gender-icon { margin-left:6px; opacity:0.9; }
.npc-gender-icon.gender-female { color:#ff72d2; }
.npc-gender-icon.gender-male { color:#72a0ff; }
.npc-gender-icon.gender-nb { color:#ffd166; }
.npc-dyn-icon { margin-left:6px; color:#65d46e; opacity:0.95; }
.npc-mtm-icon { margin-left:6px; color:#9fb1ff; opacity:0.95; }
.npc-imb-icon { margin-left:6px; color:#7ec8ff; opacity:0.95; }
.npc-ad-icon { margin-left:6px; color:#f4d03f; opacity:0.95; }
.npc-sal-icon { margin-left:6px; color:#ffb347; opacity:0.95; }
.npc-blc-icon { margin-left:6px; color:#8db4e2; opacity:0.95; }
.npc-gps-icon { margin-left:6px; color:#ff6b6b; opacity:0.95; }
.npc-divider { height:1px; background: linear-gradient(90deg, transparent, rgba(242, 124, 17, 0.3) 50%, transparent); margin:6px 0 10px; }
.npc-fields { display:flex; flex-direction:column; gap:8px; min-width:0; }
.npc-line { color:#e0e0e0; font-size:13px; line-height:1.35; min-width:0; overflow-wrap:anywhere; }
.npc-muted { color:rgb(242, 124, 17); }
/* --- Same-named actor identity --- */
.npc-sr-only { position:absolute; width:1px; height:1px; margin:-1px; padding:0; overflow:hidden; clip:rect(0 0 0 0); clip-path:inset(50%); white-space:nowrap; border:0; }
.npc-dup-badge { display:inline-block; margin-left:6px; padding:1px 7px; border:1px solid #8a5a1d; border-radius:999px; background:#4a3418; color:#ffd39c; font-size:12px; font-weight:700; vertical-align:middle; }
.npc-refid { font-family:"Consolas","Courier New",monospace; letter-spacing:0.02em; }
.npc-identity-unknown { color:#9aa3ae; font-style:italic; font-family:inherit; }
.npc-runtime-chip { display:inline-block; margin-left:6px; padding:1px 6px; border-radius:999px; background:#2c3b46; color:#9fd6ef; font-size:10.5px; text-transform:uppercase; letter-spacing:0.05em; vertical-align:middle; }
.npc-source-line { position:relative; }
.npc-source-single { color:#c2b39b; }
.npc-source-chip { color:#c2b39b; border-bottom:1px dotted #7c6a53; cursor:help; }
.npc-source-chip:focus-visible { outline:2px solid rgb(242, 124, 17); outline-offset:2px; border-radius:3px; }
.npc-source-more { margin-left:5px; padding:0 5px; border-radius:999px; background:#3a3a40; color:#d5d3d0; font-size:11px; }
/* Full chain lives outside the card body; opacity (not display) keeps it announced by
   aria-describedby while it is only painted on hover or keyboard focus. */
.npc-source-chain {
    position:absolute; top:calc(100% + 5px); left:0; z-index:20;
    display:flex; flex-direction:column; gap:3px; width:max-content; max-width:min(320px, 90vw);
    padding:8px 10px; border:1px solid rgba(242, 124, 17, 0.45); border-radius:6px;
    background:#1b1b1f; box-shadow:0 10px 24px rgba(0, 0, 0, 0.55);
    color:#ded8d0; font-size:12px; line-height:1.35;
    opacity:0; pointer-events:none; transition:opacity .12s ease;
}
.npc-source-chip:hover + .npc-source-chain,
.npc-source-chip:focus + .npc-source-chain,
.npc-source-chip:focus-visible + .npc-source-chain { opacity:1; }
.npc-source-chain-title { color:rgb(242, 124, 17); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
.npc-source-chain-entry { display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
.npc-source-chain-mod { min-width:0; overflow-wrap:anywhere; }
.npc-source-chain-role { flex:0 0 auto; color:#9aa3ae; font-size:10.5px; text-transform:uppercase; letter-spacing:0.04em; }
/* --- Shared (merged) profiles --- */
.npc-shared-badge { display:inline-flex; align-items:center; gap:4px; margin-left:6px; padding:1px 8px; border:1px solid #2f6f57; border-radius:999px; background:#153228; color:#a7e8bc; font-size:11px; font-weight:700; letter-spacing:0.02em; vertical-align:middle; white-space:nowrap; }
.npc-merge-btn { background:transparent; border:none; padding:6px; color:#e9efff; font-size:20px; line-height:1; font-family:inherit; cursor:pointer; }
.npc-merge-btn:hover, .npc-merge-btn:focus-visible { color:rgb(242, 124, 17); text-shadow: 0 0 6px rgba(242, 124, 17, 0.6), 0 0 12px rgba(242, 124, 17, 0.35); }
.npc-merge-btn.active { color:#a7e8bc; }
.npc-merge-btn:focus-visible { outline:2px solid rgb(242, 124, 17); outline-offset:2px; border-radius:4px; }
.npc-merge-container { max-width:960px; }
.npc-merge-body { padding:14px 18px 18px; overflow-y:auto; max-height:calc(85vh - 110px); display:flex; flex-direction:column; gap:12px; }
.npc-merge-intro { margin:0; color:#cfd9ea; font-size:13px; line-height:1.5; }
.npc-merge-status { margin:0; color:#9fb1c9; font-size:12px; min-height:16px; }
.npc-merge-status:empty { display:none; }
.npc-merge-error { margin:0; padding:9px 11px; border:1px solid #7d2f2f; border-radius:6px; background:#2c1b1b; color:#ffb3b3; font-size:13px; line-height:1.45; }
.npc-merge-panel { display:flex; flex-direction:column; gap:10px; padding:12px; border:1px solid #3a3a3a; border-radius:8px; background:rgba(26, 26, 26, 0.6); }
.npc-merge-panel[hidden] { display:none; }
.npc-merge-panel h3 { margin:0; color:rgb(242, 124, 17); font-size:14px; letter-spacing:0.03em; }
.npc-merge-panel p { margin:0; color:#cfd9ea; font-size:12.5px; line-height:1.5; }
.npc-merge-current { display:flex; flex-wrap:wrap; align-items:baseline; gap:4px 10px; font-size:12.5px; color:#e0e0e0; }
.npc-merge-list { display:flex; flex-direction:column; gap:6px; margin:0; padding:0; list-style:none; max-height:34vh; overflow-y:auto; }
.npc-merge-option { display:flex; align-items:flex-start; gap:9px; padding:8px 10px; border:1px solid #4a4a4a; border-radius:6px; background:#242424; cursor:pointer; }
.npc-merge-option:hover { border-color:rgba(242, 124, 17, 0.55); }
.npc-merge-option:focus-within { outline:2px solid rgb(242, 124, 17); outline-offset:1px; }
.npc-merge-option input { margin-top:3px; flex:0 0 auto; accent-color:rgb(242, 124, 17); }
.npc-merge-option-copy { display:flex; flex-direction:column; gap:2px; min-width:0; }
.npc-merge-option-name { color:#e9efff; font-weight:700; font-size:13px; overflow-wrap:anywhere; }
.npc-merge-option-meta { display:flex; flex-wrap:wrap; align-items:baseline; gap:4px 8px; font-size:11.5px; color:#9fb1c9; }
.npc-merge-refid { font-family:"Consolas","Courier New",monospace; letter-spacing:0.02em; color:#dfe6f2; }
.npc-merge-origin { color:#c2b39b; overflow-wrap:anywhere; }
.npc-merge-unknown { color:#9aa3ae; font-style:italic; }
.npc-merge-empty { color:#9aa3ae; font-style:italic; font-size:12.5px; }
.npc-merge-option.is-blocked { border-style:dashed; background:#1f1f1f; cursor:not-allowed; }
.npc-merge-option.is-blocked:hover { border-color:#4a4a4a; }
.npc-merge-option.is-blocked .npc-merge-option-name { color:#9aa3ae; }
.npc-merge-blocked { color:#ffb3b3; font-size:11.5px; line-height:1.4; overflow-wrap:anywhere; }
.npc-merge-compare { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
.npc-merge-column { display:flex; flex-direction:column; gap:8px; padding:10px; border:1px solid #4a4a4a; border-radius:6px; background:#242424; min-width:0; }
.npc-merge-column.is-keeper { border-color:rgb(242, 124, 17); box-shadow:0 0 0 1px rgba(242, 124, 17, 0.35) inset; }
.npc-merge-keeper { display:flex; align-items:center; gap:8px; font-size:12.5px; font-weight:700; color:#e9efff; cursor:pointer; }
.npc-merge-keeper input { accent-color:rgb(242, 124, 17); }
.npc-merge-field { display:flex; flex-direction:column; gap:3px; min-width:0; }
.npc-merge-field dt { margin:0; color:rgb(242, 124, 17); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
.npc-merge-field dd { margin:0; color:#dfe4ec; font-size:12.5px; line-height:1.45; overflow-wrap:anywhere; white-space:pre-wrap; max-height:9.5em; overflow-y:auto; }
.npc-merge-field dd.is-empty { color:#9aa3ae; font-style:italic; white-space:normal; }
.npc-merge-fields { display:flex; flex-direction:column; gap:8px; margin:0; }
.npc-merge-warn { margin:0; padding:9px 11px; border:1px solid #8a5a1d; border-radius:6px; background:#33260f; color:#ffd39c; font-size:12.5px; line-height:1.5; }
/* Automatic-link status and the "auto-link is off" note. Both are copy only: no extra queries. */
.npc-merge-panel p.npc-merge-auto { align-self:flex-start; max-width:100%; padding:3px 10px; border:1px solid #2f6f57; border-radius:999px; background:#153228; color:#a7e8bc; font-size:11.5px; font-weight:600; line-height:1.45; }
.npc-merge-panel p.npc-merge-auto[hidden] { display:none; }
.npc-merge-note { margin:0; padding:8px 11px; border:1px solid #4a5a7d; border-radius:6px; background:#1b2333; color:#c6d4ee; font-size:12px; line-height:1.45; }
.npc-merge-note[hidden] { display:none; }
.npc-merge-confirm { display:flex; align-items:flex-start; gap:8px; font-size:12.5px; color:#e9efff; line-height:1.45; cursor:pointer; }
.npc-merge-confirm input { margin-top:2px; flex:0 0 auto; accent-color:rgb(242, 124, 17); }
.npc-merge-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
.npc-merge-actions button { border-radius:6px; padding:8px 14px; font-family:inherit; font-size:13px; cursor:pointer; border:1px solid #4a4a4a; background:#2f2f2f; color:#e9efff; }
.npc-merge-actions button:hover:not(:disabled) { border-color:rgba(242, 124, 17, 0.6); }
.npc-merge-actions button:focus-visible { outline:2px solid rgb(242, 124, 17); outline-offset:2px; }
.npc-merge-actions button:disabled { opacity:0.5; cursor:not-allowed; }
.npc-merge-actions .npc-merge-primary { background:linear-gradient(135deg, #176529, #125121); border-color:rgba(72,187,120,0.35); }
.npc-merge-actions .npc-merge-danger { background:#3a1f1f; border-color:#7d2f2f; color:#ffb3b3; }
@media (max-width: 720px) {
    .npc-merge-compare { grid-template-columns:1fr; }
    .npc-merge-actions { justify-content:stretch; }
    .npc-merge-actions button { flex:1 1 auto; }
}
.npc-identity-label { display:block; font-weight:700; color:rgb(242, 124, 17); }
.npc-identity-readonly { display:flex; flex-direction:column; gap:6px; padding:10px 12px; border:1px solid #3a3a3a; border-radius:8px; background:rgba(26, 26, 26, 0.6); }
.npc-identity-row { color:#e0e0e0; font-size:13px; line-height:1.4; overflow-wrap:anywhere; }
.npc-identity-chain-row { display:flex; flex-direction:column; gap:4px; }
.npc-identity-chain { display:flex; flex-direction:column; gap:4px; margin:0; padding-left:20px; }
.npc-identity-chain li::marker { color:#9aa3ae; font-size:11px; }
.npc-actions { display:flex; gap:8px; margin-top:6px; justify-content:center; }
.npc-actions .btn { padding:6px 10px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; text-decoration:none; cursor:pointer; }
.npc-actions .btn:hover { background:#3a3a3a; }
.npc-actions .btn-danger { background:#5a2a2a; border-color:#7a3a3a; }
.npc-actions .btn-danger:hover { background:#6a2a2a; }
.npc-title-actions a { text-decoration:none; border:none; }
.npc-title-actions a:hover { text-decoration:none; }
.btn-toggle { background:transparent; border:none; padding:6px; color:#e9efff; font-size:22px; line-height:1; text-decoration:none; transition: color .15s ease, text-shadow .15s ease; }
/* Navbar-like glow only for lock and gallery on cards */
.btn-toggle[data-lock-id]:hover,
.btn-toggle[data-lock-id]:focus-visible,
.btn-toggle[data-pick-picture-id]:hover,
.btn-toggle[data-pick-picture-id]:focus-visible { color: rgb(242, 124, 17); background:transparent; text-decoration:none; text-shadow: 0 0 6px rgba(242, 124, 17, 0.6), 0 0 12px rgba(242, 124, 17, 0.35); }
.npc-title-actions .btn-toggle[data-favorite-id]:hover,
.npc-title-actions .btn-toggle[data-favorite-id]:focus-visible { color:#ffd700 !important; text-shadow: 0 0 8px rgba(255, 215, 0, 0.7), 0 0 14px rgba(255, 215, 0, 0.45) !important; }
.btn-toggle.active { color: rgb(242, 124, 17); font-weight:700; text-decoration:none; }
.npc-title-actions .btn-toggle.active[data-favorite-id] { color:#ffd700 !important; }
.btn-trash { background:transparent; border:none; padding:6px; color:#e9efff; font-size:20px; line-height:1; text-decoration:none; transition: color .15s ease, text-shadow .15s ease; }
.btn-trash:hover, .btn-trash:focus-visible { color:#ff6b6b; text-shadow: 0 0 6px rgba(255, 107, 107, 0.7), 0 0 12px rgba(255, 107, 107, 0.45); }
.npc-tags-label { font-size:11px; color:#9fb1c9; margin-right:4px; }
.npc-tags-top { display:inline-block; font-size:11px; color:#9fb1c9; border:1px solid #4a4a4a; border-radius:999px; padding:2px 6px; max-width:120px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.npc-row { display:flex; gap:10px; align-items:flex-start; }
.npc-right { margin-left:auto; flex:0 0 auto; }
.npc-race-art { width:200px; height:200px; max-width:200px; max-height:200px; object-fit:cover; display:block; }
@media (max-width: 1100px){ .npc-race-art { width:160px; height:160px; } }
@media (max-width: 900px){ .npc-race-art { width:140px; height:140px; } }
@media (max-width: 720px){ .npc-right { display:none; } }
/* Dynamic profile grouping */
.dynamic-profile-section { 
    border:1px solid #3a3a3a; 
    border-radius:8px; 
    padding:12px; 
    margin:10px 0; 
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(32, 32, 32, 0.6)); 
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}
.dynamic-profile-section .section-title { 
    font-weight:700; 
    color:rgb(242, 124, 17); 
    margin-bottom:8px; 
    font-size: 1.05em;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}
.dynamic-profile-section > .form-item { margin-bottom:10px; }
</style>
<style>
/* Modal styling aligned with Oghma edit modal */
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:10000; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0; }
.modal-container { 
    position:relative; 
    top:auto; 
    left:auto; 
    transform:none; 
    max-width:1200px; 
    width:95%; 
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98)); 
    border: 1px solid #3a3a3a; 
    border-radius: 12px; 
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}
.modal-header { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    padding:16px 18px; 
    border-bottom: 1px solid rgba(242, 124, 17, 0.2); 
    background: rgba(42, 42, 42, 0.95); 
    position:sticky; 
    top:0; 
    z-index:2; 
    border-radius: 12px 12px 0 0;
}
.modal-title { 
    margin:0; 
    font-weight:700; 
    color: rgb(242, 124, 17); 
    font-family: 'MagicCards', serif; 
    word-spacing: 6px; 
    font-size: 1.4em;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}
.modal-body { 
    max-height:calc(85vh - 100px); 
    background: rgba(34, 34, 34, 0.95); 
}
.npc-modal-frame-wrap { position:relative; min-height:260px; }
.npc-modal-load-status {
    position:absolute;
    inset:0;
    z-index:3;
    display:none;
    align-items:center;
    justify-content:center;
    padding:24px;
    background:#222;
    color:#e9efff;
    text-align:center;
}
.npc-modal-load-status.is-visible { display:flex; }
.npc-modal-load-status strong { display:block; margin-bottom:8px; color:rgb(242,124,17); font-size:18px; }
.npc-modal-load-status p { margin:0; color:#cfd9ea; line-height:1.45; }
.npc-modal-load-status button { margin-top:14px; }
.modal-close { 
    background:#3a3a3a; 
    color:#fff; 
    border:1px solid #4a4a4a; 
    border-radius:6px; 
    padding:6px 12px; 
    cursor:pointer; 
    transition: all 0.2s ease;
}
.modal-close:hover {
    background:#4a4a4a;
    border-color:#5a5a5a;
}
.modal-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.modal-actions .btn-save { 
    background: linear-gradient(135deg, #176529, #125121); 
    color:#fff; 
    border:1px solid rgba(72,187,120,0.3); 
    border-radius:6px; 
    padding:10px 16px; 
    cursor:pointer; 
    font-weight:700; 
    font-size:13px; 
    transition:all 0.2s ease; 
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
.modal-actions .btn-save:hover { 
    background: linear-gradient(135deg, #125121, #0d3d19); 
    border-color:rgba(72,187,120,0.5); 
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}
.modal-actions .btn-cancel { 
    background:#3a3a3a; 
    color:#e9efff; 
    border:1px solid #4a4a4a; 
    border-radius:6px; 
    padding:10px 16px; 
    cursor:pointer; 
    font-weight:600; 
    font-size:13px; 
    transition:all 0.2s ease; 
}
.modal-actions .btn-cancel:hover { 
    background:#4a4a4a; 
    border-color:#5a5a5a; 
    color:rgb(242,124,17); 
    transform: translateY(-1px);
}
.modal-actions #npc_modal_regen { 
    background:rgba(242,124,17,0.15); 
    border-color:rgb(242,124,17); 
    color:rgb(242,124,17); 
}
.modal-actions #npc_modal_regen:hover { 
    background:rgba(242,124,17,0.3); 
    border-color:rgb(242,124,17);
    transform: translateY(-1px);
}
.modal-actions #npc_modal_close { 
    background: linear-gradient(135deg, #5a2a2a, #4a1a1a); 
    border-color:#7a3a3a; 
    color:#fff; 
}
.modal-actions #npc_modal_close:hover { 
    background: linear-gradient(135deg, #6a3a3a, #5a2a2a); 
    transform: translateY(-1px);
}
.modal-save { 
    background: rgb(242, 124, 17); 
    color:#111; 
    border:1px solid rgb(242, 124, 17); 
    border-radius:6px; 
    padding:8px 14px; 
    cursor:pointer; 
    font-weight:700; 
    transition: all 0.2s ease;
}
.modal-save:hover {
    background: rgb(212, 94, 0);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(242, 124, 17, 0.3);
}
/* Styled tabs to match button aesthetics */
#npc_modal_tabs .pf-tab { 
    padding:8px 14px; 
    border-radius:6px; 
    border:1px solid #3a3a3a; 
    background: rgba(42, 42, 42, 0.8); 
    color:#e9efff; 
    cursor:pointer; 
    font-weight:700; 
    transition: all 0.2s ease;
}
#npc_modal_tabs .pf-tab:hover { 
    background: rgba(58, 58, 58, 0.9); 
    border-color: #4a4a4a;
}
#npc_modal_tabs .pf-tab.active { 
    background: linear-gradient(135deg, rgba(242, 124, 17, 0.2), rgba(242, 124, 17, 0.1)); 
    color: rgb(242, 124, 17); 
    border-color: rgba(242, 124, 17, 0.5); 
    box-shadow: inset 0 -2px 0 rgb(242, 124, 17);
}
</style>
<?php if ($totalPages >= 1): ?>
<style>
.pagination { display:flex; gap:8px; align-items:center; justify-content:center; margin:16px 0 0 0; flex-wrap:wrap; }
.pagination:not(.npc-toolbar) a, .pagination:not(.npc-toolbar) span { 
    padding:8px 12px; 
    border-radius:6px; 
    border:1px solid #3a3a3a; 
    background: rgba(42, 42, 42, 0.8); 
    color:#e9efff; 
    text-decoration:none; 
    transition: all 0.2s ease;
}
.pagination:not(.npc-toolbar) a:hover { 
    background: rgba(58, 58, 58, 0.9); 
    border-color: #4a4a4a;
    transform: translateY(-1px);
}
.pagination:not(.npc-toolbar) .active { 
    background: linear-gradient(135deg, rgba(242, 124, 17, 0.2), rgba(242, 124, 17, 0.1)); 
    color: rgb(242, 124, 17); 
    border-color: rgba(242, 124, 17, 0.5); 
    font-weight:700; 
    box-shadow: inset 0 -2px 0 rgb(242, 124, 17);
}
.pagination:not(.npc-toolbar) .disabled { opacity:0.4; pointer-events:none; }
.pagination button { 
    padding:8px 14px; 
    border-radius:6px; 
    border:1px solid #3a3a3a; 
    background: rgba(42, 42, 42, 0.8); 
    color:#e9efff; 
    cursor:pointer; 
    transition: all 0.2s ease;
    font-weight: 600;
}
.pagination button:hover { 
    background: rgba(58, 58, 58, 0.9); 
    border-color: #4a4a4a;
    transform: translateY(-1px);
}
.filter-inline { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.filter-inline input[type="text"] { 
    padding:6px 10px; 
    border-radius:6px; 
    border:1px solid #3a3a3a; 
    background: rgba(26, 26, 26, 0.8); 
    color:#e9efff; 
    height:32px; 
    transition: all 0.2s ease;
}
.filter-inline input[type="text"]:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}
.filter-inline select { 
    padding:6px 10px; 
    border-radius:6px; 
    border:1px solid #3a3a3a; 
    background: rgba(26, 26, 26, 0.8); 
    color:#e9efff; 
    height:32px; 
    transition: all 0.2s ease;
}
.filter-inline select:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}
.npc-letter-filter {
    display:flex;
    gap:6px;
    align-items:center;
    justify-content:flex-start;
    flex-wrap:wrap;
    width:100%;
    margin-top:10px;
}
.npc-letter-btn {
    background:#2a2a2a;
    border:1px solid #4a4a4a;
    color:#cfd9ea;
    border-radius:6px;
    padding:6px 10px;
    min-width:34px;
    font-size:12px;
    font-weight:600;
    cursor:pointer;
    transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}
.npc-letter-btn:hover {
    background:#333333;
    border-color:#5a5a5a;
    transform: translateY(-1px);
}
.npc-letter-btn.active {
    background:rgba(242,124,17,0.16);
    border-color:rgba(242,124,17,0.7);
    color:rgb(242, 124, 17);
    box-shadow: inset 0 0 0 1px rgba(242,124,17,0.18);
}
.filter-inline .btn { 
    padding:8px 14px; 
    border-radius:6px; 
    border:1px solid #3a3a3a; 
    background: rgba(42, 42, 42, 0.8); 
    color:#e9efff; 
    cursor:pointer; 
    transition: all 0.2s ease;
    font-weight: 600;
}
.filter-inline .btn:hover { 
    background: rgba(58, 58, 58, 0.9); 
    border-color: #4a4a4a;
    transform: translateY(-1px);
}
.pagination.npc-toolbar {
    display:flex;
    flex-direction:column;
    align-items:stretch;
    justify-content:flex-start;
    gap:12px;
    padding:14px;
    margin:0;
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.pagination.npc-toolbar .npc-toolbar-main,
.pagination.npc-toolbar .npc-toolbar-subrow,
.pagination.npc-toolbar .npc-toolbar-letter-row {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}
.pagination.npc-toolbar .npc-toolbar-actions,
.pagination.npc-toolbar .npc-toolbar-tools,
.pagination.npc-toolbar .npc-toolbar-pager {
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}
.pagination.npc-toolbar .npc-toolbar-main {
    align-items:flex-start;
    flex-wrap:nowrap;
}
.pagination.npc-toolbar .npc-toolbar-actions {
    flex:1 1 0;
    min-width:0;
    flex-wrap:wrap;
}
.pagination.npc-toolbar .npc-toolbar-tools {
    flex:0 0 auto;
    width:auto;
    min-width:0;
    margin-left:auto;
    flex-direction:row;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:nowrap;
}
.pagination.npc-toolbar .npc-auto-lock-profile {
    display:flex;
    align-items:center;
    gap:8px;
    color:#e9efff;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
}
.pagination.npc-toolbar .npc-auto-lock-profile input {
    accent-color:rgb(242,124,17);
    cursor:pointer;
}
.pagination.npc-toolbar .npc-toolbar-pager {
    flex:1 1 auto;
}
.pagination.npc-toolbar .npc-toolbar-letter-row {
    align-items:flex-end;
}
.pagination.npc-toolbar .npc-toolbar-summary {
    margin-left:auto;
    display:flex;
    align-items:flex-end;
    gap:8px;
    flex-wrap:wrap;
}
.pagination.npc-toolbar .npc-toolbar-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
    margin:0;
    padding:8px 14px;
    border-radius:8px;
    border:1px solid rgba(242, 124, 17, 0.32);
    font-size:14px;
    font-weight:600;
    line-height:1.2;
    font-family:inherit;
    cursor:pointer;
    transition:background 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
    white-space:nowrap;
}
.pagination.npc-toolbar .npc-toolbar-btn:hover {
    transform:translateY(-1px);
}
.pagination.npc-toolbar .npc-toolbar-btn-uniform {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    flex:0 0 228px;
    width:228px;
    min-width:228px;
    max-width:228px;
    box-sizing:border-box;
    margin:0;
    padding:8px 14px;
    text-align:center;
}
.pagination.npc-toolbar .npc-toolbar-btn-action {
    background:rgba(42, 42, 42, 0.8);
    border-color:rgba(242, 124, 17, 0.48);
    color:#ffffff;
    box-shadow:0 4px 10px rgba(0, 0, 0, 0.18);
}
.pagination.npc-toolbar .npc-toolbar-btn-action:hover {
    background:rgba(54, 54, 54, 0.92);
    border-color:rgba(242, 124, 17, 0.72);
    color:#ffffff;
}
.pagination.npc-toolbar .npc-toolbar-btn-danger {
    background: linear-gradient(135deg, rgba(150, 36, 36, 0.96), rgba(110, 22, 22, 0.96));
    border-color: rgba(255, 115, 115, 0.45);
    color: #fff1f1;
    box-shadow: 0 4px 10px rgba(126, 24, 24, 0.22);
}
.pagination.npc-toolbar .npc-toolbar-btn-danger:hover {
    background: linear-gradient(135deg, rgba(168, 42, 42, 1), rgba(126, 26, 26, 1));
    border-color: rgba(255, 145, 145, 0.6);
}
.pagination.npc-toolbar .npc-filter-dropdown {
    position:relative;
    flex:0 0 228px;
    width:228px;
}
.pagination.npc-toolbar .npc-toolbar-filter-btn {
    width:100%;
}
.pagination.npc-toolbar .npc-filter-menu {
    position:absolute;
    left:0;
    top:calc(100% + 6px);
    min-width:220px;
    display:none;
    background:#2a2a2a;
    border:1px solid #4a4a4a;
    border-radius:8px;
    padding:8px;
    box-shadow:0 6px 18px rgba(0,0,0,0.35);
    z-index:15;
}
.pagination.npc-toolbar .npc-filter-menu label {
    display:flex;
    align-items:center;
    gap:8px;
    margin:4px 0;
    color:#e9efff;
    font-size:13px;
}
.pagination.npc-toolbar .npc-toolbar-tools input[type="text"] {
    flex:0 0 220px;
    width:220px;
    min-width:0;
    max-width:220px;
}
.pagination.npc-toolbar .npc-toolbar-tools select {
    flex:0 0 180px;
    width:180px;
    min-width:0;
    max-width:180px;
}
.pagination.npc-toolbar .npc-page-link {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
    margin:0;
    font-family:inherit;
    appearance:none;
    cursor:pointer;
}
.pagination.npc-toolbar .npc-page-link.disabled,
.pagination.npc-toolbar .npc-page-link:disabled {
    opacity:0.4;
    pointer-events:none;
    transform:none;
}
.pagination.npc-toolbar .npc-page-indicator {
    color:rgb(242, 124, 17);
    font-weight:700;
    padding:0 4px;
}
.pagination.npc-toolbar .npc-total-pill {
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 12px;
    border-radius:8px;
    border:1px solid #3a3a3a;
    background:rgba(26, 26, 26, 0.78);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.02);
}
.pagination.npc-toolbar .npc-toolbar-letter-row .npc-letter-filter {
    flex:1 1 auto;
    width:auto;
    margin:0;
}
.pagination.npc-toolbar .npc-total-pill-icon {
    font-size:15px;
    opacity:0.9;
}
.pagination.npc-toolbar .npc-total-pill-value {
    color:#e9efff;
    font-weight:700;
    font-size:22px;
    line-height:1;
}
@media (max-width: 1200px) {
    .pagination.npc-toolbar .npc-toolbar-actions {
        min-width:0;
        flex-wrap:wrap;
    }
    .pagination.npc-toolbar .npc-toolbar-tools {
        justify-content:flex-start;
    }
    .pagination.npc-toolbar .npc-total-pill {
        margin-left:0;
    }
}
@media (max-width: 780px) {
    .pagination.npc-toolbar .npc-toolbar-main {
        flex-wrap:wrap;
    }
    .pagination.npc-toolbar .npc-toolbar-tools,
    .pagination.npc-toolbar .npc-toolbar-actions,
    .pagination.npc-toolbar .npc-toolbar-pager,
    .pagination.npc-toolbar .npc-toolbar-summary,
    .pagination.npc-toolbar .npc-toolbar-letter-row {
        width:100%;
    }
    .pagination.npc-toolbar .npc-toolbar-tools input[type="text"],
    .pagination.npc-toolbar .npc-toolbar-tools select {
        flex:1 1 100%;
        min-width:0;
    }
    .pagination.npc-toolbar .npc-toolbar-subrow {
        align-items:flex-start;
    }
    .pagination.npc-toolbar .npc-toolbar-tools {
        flex:1 1 100%;
        width:100%;
        min-width:0;
        margin-left:0;
        flex-wrap:wrap;
        justify-content:flex-start;
    }
    .pagination.npc-toolbar .npc-toolbar-letter-row {
        align-items:flex-start;
    }
    .pagination.npc-toolbar .npc-toolbar-letter-row .npc-letter-filter {
        width:100%;
    }
    .pagination.npc-toolbar .npc-toolbar-summary {
        margin-left:0;
        align-items:flex-start;
    }
}
</style>
<?php renderNpcToolbar([
    'top' => true,
    'q' => $q,
    'profileRows' => $profileRows ?? [],
    'profileIdFilter' => $profileIdFilter,
    'page' => $page,
    'totalPages' => $totalPages,
    'totalRows' => $totalRows,
    'nameLetterFilter' => $nameLetterFilter,
    'favOnly' => $favOnly,
    'dynOnly' => $dynOnly,
    'mtmOnly' => $mtmOnly,
    'lockOnly' => $lockOnly,
    'salOnly' => $salOnly,
    'blcOnly' => $blcOnly,
    'gpsOnly' => $gpsOnly,
]); ?>
<div style="margin:8px 0 10px; padding:10px 14px; background:rgba(242,124,17,0.08); border:1px solid rgba(242,124,17,0.25); border-radius:8px; font-size:12.5px; color:#cfd9ea; line-height:1.5;">
  <strong style="color:rgb(242,124,17);">History Pullback:</strong>
  Every time a save game is loaded, CHIM snapshots all NPC profiles and restores <strong>unlocked</strong> NPCs to their state at the save's Tamrielic timestamp.
  Loading an older save will roll back unlocked profiles to that point in time. NPCs created <em>after</em> the save's timestamp may disappear entirely.
  <br>
  <span style="color:rgb(242,124,17);">Lock a profile (🔒) to protect it from pullback.</span>
  You can view and restore previous profile versions via the <strong>Profile Versions</strong> button in the edit modal.
</div>
<div class="npc-grid">
    <?php foreach ($data as $row): ?>
    <?php 
    $pid = (string)($row['profile_id'] ?? ''); 
    $profLabel = $profilesById[$pid] ?? ''; 
    $oghmaVal = trim((string)($row['oghma_knowledge_tags'] ?? '')); 
    $oghmaDisp = ($oghmaVal === '') ? 'none' : $oghmaVal; 
    $tagsVal = trim((string)($row['tags'] ?? '')); 
    $tagsDisp = ($tagsVal === '') ? 'none' : $tagsVal; 
    $metaTmp = []; 
    if (!empty($row['metadata'])) { 
        $tmp = json_decode((string)$row['metadata'], true); 
        if (is_array($tmp)) { $metaTmp = $tmp; } 
    } 
    $portraitRel = (string)($metaTmp['portrait'] ?? ''); 
    $extTmp = []; 
    if (!empty($row['extended_data'])) { 
        $tmp2 = json_decode((string)$row['extended_data'], true); 
        if (is_array($tmp2)) { $extTmp = $tmp2; } 
    }
    
    // Check for inherited profile settings
    $profileMeta = isset($profileMetaById[$pid]) ? $profileMetaById[$pid] : ['dyn'=>false,'mtm'=>false,'ad'=>false,'sal'=>false,'blc'=>false,'gps'=>false];
    
    // MTM: check extended_data override, otherwise inherit from profile
    $mtmEnabled = $profileMeta['mtm']; // default to profile
    if (array_key_exists('middle_term_enabled', $extTmp) && $extTmp['middle_term_enabled'] !== null && $extTmp['middle_term_enabled'] !== '') {
        $mtmEnabled = !empty($extTmp['middle_term_enabled']);
    }

    // Individual memory bank is NPC-only (no profile inheritance)
    $imbEnabled = false;
    if (array_key_exists('individual_memory_enabled', $extTmp) && $extTmp['individual_memory_enabled'] !== null && $extTmp['individual_memory_enabled'] !== '') {
        $imbEnabled = !empty($extTmp['individual_memory_enabled']);
    }
    
    // Auto Diary: check extended_data override, otherwise inherit from profile
    $adEnabled = $profileMeta['ad']; // default to profile
    if (array_key_exists('auto_diary_enabled', $extTmp) && $extTmp['auto_diary_enabled'] !== null && $extTmp['auto_diary_enabled'] !== '') {
        $adEnabled = !empty($extTmp['auto_diary_enabled']);
    }
    
    // Auto Greeting: check extended_data override, otherwise inherit from profile
    $salEnabled = $profileMeta['sal']; // default to profile
    if (array_key_exists('salutation_after_a_while', $extTmp) && $extTmp['salutation_after_a_while'] !== null && $extTmp['salutation_after_a_while'] !== '') {
        $salEnabled = !empty($extTmp['salutation_after_a_while']);
    }
    
    // Background Life Commands: check extended_data override, otherwise inherit from profile
    $blcEnabled = $profileMeta['blc']; // default to profile
    if (array_key_exists('background_life_commands', $extTmp) && $extTmp['background_life_commands'] !== null && $extTmp['background_life_commands'] !== '') {
        $blcEnabled = !empty($extTmp['background_life_commands']);
    }
    
    // GPS Track: check metadata override, otherwise inherit from profile
    $gpsEnabled = $profileMeta['gps']; // default to profile
    if (array_key_exists('gps_track', $metaTmp) && $metaTmp['gps_track'] !== null && $metaTmp['gps_track'] !== '') {
        $gpsEnabled = !empty($metaTmp['gps_track']);
    }
    
    $raceIcon = race_icon_web_path($row['race'] ?? '', $webRoot,$row['refid'] ?? '', $row['md5'] ?? '', $row['npc_name'] ?? '', $portraitRel); 
    ?>
    <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
            <div class="npc-title">
            <div class="npc-title-left"><?php 
                $levelDisp2 = '';
                if (isset($metaTmp['stats']) && is_array($metaTmp['stats']) && isset($metaTmp['stats']['level'])) {
                    $levelDisp2 = ' ('.intval($metaTmp['stats']['level']).')';
                }
            ?><span class="npc-name"><?= htmlspecialchars(($row["npc_name"] ?? '').$levelDisp2) ?></span><?php $dupCount = npc_duplicate_count($npcNameCounts ?? [], $row['npc_name'] ?? ''); if ($dupCount > 1): ?><span class="npc-dup-badge" title="<?= htmlspecialchars($dupCount.' profiles share the name "'.($row['npc_name'] ?? '').'"', ENT_QUOTES) ?>"><span aria-hidden="true">&times;<?= (int)$dupCount ?></span><span class="npc-sr-only"><?= (int)$dupCount ?> profiles share this name</span></span><?php endif; ?><?php $shareState = npc_profile_sharing_state($npcProfileSharing ?? [], $row["id"] ?? 0); render_npc_sharing_badge($shareState); ?> <?php $gch = gender_icon_char($row['gender'] ?? ''); $gcl = gender_icon_class($row['gender'] ?? ''); if ($gch!==''): ?><span class="npc-gender-icon <?= htmlspecialchars($gcl) ?>" title="<?= htmlspecialchars($row['gender'] ?? '') ?>"><?= $gch ?></span><?php endif; ?><?php if (!empty($row['dynamic_profile'])): ?><span class="npc-dyn-icon" title="Dynamic profile enabled">♻️</span><?php endif; ?><?php if (!empty($mtmEnabled)): ?><span class="npc-mtm-icon" title="Middle-term memory enabled">📃</span><?php endif; ?><?php if (!empty($imbEnabled)): ?><span class="npc-imb-icon" title="Individual memory bank enabled">🧠</span><?php endif; ?><?php if (!empty($adEnabled)): ?><span class="npc-ad-icon" title="Auto diary enabled">📙</span><?php endif; ?><?php if (!empty($salEnabled)): ?><span class="npc-sal-icon" title="Auto Greeting enabled">👋</span><?php endif; ?><?php if (!empty($blcEnabled)): ?><span class="npc-blc-icon" title="Background life commands enabled">🎮</span><?php endif; ?><?php if (!empty($gpsEnabled)): ?><span class="npc-gps-icon" title="GPS track enabled">📍</span><?php endif; ?></div>
            <div class="npc-title-actions">
                <?php if ($tagsDisp !== ''): ?>
                <span class="npc-tags-label">Tags:</span>
                <span class="npc-tags-top" title="Use Search to filter by these tags: <?= htmlspecialchars($tagsDisp) ?>"><?= htmlspecialchars($tagsDisp) ?></span>
                <?php endif; ?>
                <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>" title="Toggle favorite"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                <?php render_npc_merge_action($row, $shareState, $dupCount); ?><a class="btn btn-toggle" href="#" data-pick-picture-id="<?= $row["id"] ?>" title="Set picture">🖼️</a>
                <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>" title="Toggle lock - Locked profiles are protected from history pullback when loading saves"><?php echo !empty($row["lock_profile"]) ? "🔒" : "🔓"; ?></a>
                <a class="btn btn-trash<?= !empty($row['lock_profile']) ? ' disabled' : '' ?>" href="<?= !empty($row['lock_profile']) ? '#' : ('?delete='.$row['id']) ?>" onclick="<?= !empty($row['lock_profile']) ? 'alert(\'This NPC is locked and cannot be deleted.\'); return false;' : npc_delete_confirm_onclick($row, $metaTmp) ?>" title="<?= !empty($row['lock_profile']) ? 'Locked - cannot delete' : 'Delete' ?>">❌</a>
            </div>
        </div>
        <div class="npc-divider"></div>
        <div class="npc-row">
            <div class="npc-fields">
                <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"><?= htmlspecialchars($row["gender"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"><?= htmlspecialchars($row["race"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"><?= htmlspecialchars($row["voiceid"] ?? "") ?></span></div>
                <?php render_npc_identity_lines($row, $metaTmp); ?>
                <div class="npc-line"><span class="npc-muted">Oghma Tags:</span> <span class="npc-oghma"><?= htmlspecialchars($oghmaDisp) ?></span></div>
                <div class="npc-line"><span class="npc-muted">Profile:</span> <span class="npc-profile"><?= htmlspecialchars($profLabel) ?></span></div>
            </div>
            <div class="npc-right">
                <?php if ($raceIcon !== ''): ?>
                    <img class="npc-race-art" src="<?= htmlspecialchars($raceIcon) ?>" alt="Race icon" />
                <?php endif; ?>
            </div>
            <div class="npc-right-warn">
                    <?php 
                    if ($row["gamets_last_updated"] != $LAST_INFOSAVE_EVENT) {
                        echo "<span title='This NPC is out of sync, this means current NPC sheet has been modified after last save. If you edit this NPC, changes will be lost if you reload a previous savegame. '>⚠️</span>";
                    }
                    ?>
            </div>
        </div>
        
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div id="npc_modal" class="modal-backdrop">
  <div class="modal-container">
    <div class="modal-header">
      <h2 class="modal-title">Edit NPC</h2>
      <div class="modal-actions">
        <button id="npc_modal_save_header" class="btn-save">Save</button>
        <button id="npc_modal_export" class="btn-cancel" title="Export NPC biography to JSON file">Export Bio</button>
        <button id="npc_modal_import_to" class="btn-cancel" title="Import biography from another NPC's export file">Import Bio</button>
        <button id="npc_modal_reset" class="btn-cancel" title="Reimport bio template fields">Reset NPC</button>
        <button id="npc_modal_diary" class="btn-cancel">View Diary</button>
        <button id="npc_modal_history" class="btn-cancel">Profile Versions</button>
        <button id="npc_modal_regen" class="btn-cancel" title="Will use AI to regenerate this profile. Intended for custom NPCs without biography descriptions.">AI Generate Profile</button>
        <button id="npc_modal_close" class="btn-cancel">Close</button>
      </div>
    </div>
    <div class="modal-body">
      <div id="npc_modal_tabs" style="display:flex; gap:8px; padding:8px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; position:sticky; top:0; z-index:2;">
        <button type="button" class="pf-tab active" data-pane="pane_manual">✍️ Manual</button>
        <button type="button" class="pf-tab" data-pane="pane_bio">📚 NPC Biographies</button>
      </div>
      <div id="pane_manual" class="pf-pane active npc-modal-frame-wrap" style="padding:0;">
        <div id="npc_modal_load_status" class="npc-modal-load-status" role="status" aria-live="polite">
          <div>
            <strong id="npc_modal_load_title">Loading profile...</strong>
            <p id="npc_modal_load_message">Retrieving the latest NPC data.</p>
            <button id="npc_modal_retry" type="button" class="btn-cancel" style="display:none;">Retry</button>
          </div>
        </div>
        <iframe id="npc_modal_iframe" src="about:blank" style="width:100%; height:70vh; border:0; background:transparent;"></iframe>
      </div>
      <div id="pane_bio" class="pf-pane" style="display:none; padding:10px;">
        <div style="display:flex; gap:12px; align-items:flex-start;">
          <div style="flex: 0 0 340px; max-width:340px; border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#2a2a2a;">
            <div style="display:flex; flex-direction:column; gap:6px; align-items:stretch; margin-bottom:8px;">
              <select id="bio_letter" style="padding:6px 8px; border:1px solid #4a4a4a; border-radius:6px; background:#2a2a2a; color:#e9efff;">
                <option value="">All</option>
                <option>A</option><option>B</option><option>C</option><option>D</option><option>E</option><option>F</option><option>G</option><option>H</option><option>I</option><option>J</option><option>K</option><option>L</option><option>M</option><option>N</option><option>O</option><option>P</option><option>Q</option><option>R</option><option>S</option><option>T</option><option>U</option><option>V</option><option>W</option><option>X</option><option>Y</option><option>Z</option>
              </select>
              <input id="bio_search_input" type="text" placeholder="Search bio database..." style="padding:6px 8px; border:1px solid #4a4a4a; border-radius:6px; background:#2a2a2a; color:#e9efff;">
            </div>
            <div id="bio_list" style="height:58vh; overflow:auto; display:flex; flex-direction:column; gap:6px;"></div>
            <div id="bio_pager" style="display:flex; gap:6px; align-items:center; justify-content:center; margin-top:6px;"></div>
          </div>
          <div style="flex: 1 1 auto; min-width:0; border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#2a2a2a;">
            <div style="margin-bottom:8px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
              <label class="label-with-toggle"><input id="bio_inc_core" type="checkbox" checked> Core</label>
              <label class="label-with-toggle"><input id="bio_inc_ext" type="checkbox" checked> Extended Profile</label>
              <label class="label-with-toggle"><input id="bio_inc_oghma" type="checkbox" checked> Oghma Tags</label>
              <label class="label-with-toggle"><input id="bio_inc_vm" type="checkbox" checked> Voice & Meta</label>
              <select id="bio_profile_id" title="Assign Profile" style="margin-left:auto; padding:6px 8px; border:1px solid #4a4a4a; border-radius:6px; background:#2a2a2a; color:#e9efff;">
                <option value="">— Profile —</option>
                <?php foreach (($profileRows ?? []) as $pr): $pid=(string)($pr['id']??''); $lbl=$pr['label']??('Profile #'.$pid); $sel = ($firstProfileId === $pid) ? ' selected' : ''; ?>
                <option value="<?= htmlspecialchars($pid) ?>"<?= $sel ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <button id="bio_use_template" type="button" class="btn-base btn-primary">Use Template</button>
            </div>
            <div id="bio_detail" style="height:58vh; overflow:auto;">
              <div style="color:#9fb1c9">Select a template on the left</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Gallery picker overlay -->
<div id="gallery_picker" class="modal-backdrop" style="z-index:10001;">
  <div class="modal-container" style="max-width:1200px; width:95%;">
    <div class="modal-header">
      <h2 class="modal-title">Choose Picture</h2>
      <div class="modal-actions">
        <button id="gallery_picker_close" class="btn-cancel">Close</button>
      </div>
    </div>
    <div class="modal-body" style="height:80vh;">
      <iframe id="gallery_picker_iframe" src="about:blank" style="width:100%; height:100%; border:0; background:transparent;"></iframe>
    </div>
  </div>
  
</div>

<!-- NPC profile versions viewer overlay -->
<div id="history_viewer" class="modal-backdrop" style="z-index:10002;">
  <div class="modal-container" style="max-width:1100px; width:95%;">
    <div class="modal-header">
      <h2 class="modal-title">NPC Profile Versions</h2>
      <div class="modal-actions">
        <button id="history_close" class="btn-cancel">Close</button>
        <button id="history_generation" class="btn-cancel" title="Note: Will do a LLM request.">Evolution report (AI request)</button>
      </div>
    </div>
    <div class="modal-body" style="height:75vh; display:flex; gap:10px;">
      <div id="history_list" style="flex: 0 0 320px; max-width:320px; border-right:1px solid #4a4a4a; overflow:auto; padding:8px;">
      </div>
      <div id="history_detail" style="flex: 1 1 auto; min-width:0; overflow:auto; padding:8px;">
        <div style="color:#9fb1c9">Select a snapshot to view details</div>
      </div>
    </div>
  </div>
</div>


<!-- Profile sharing dialog. Reversible: every actor card survives a merge or an automatic link,
     and Unlink separates the whole group again and restores each actor's own profile data. -->
<div id="npc_merge_modal" class="modal-backdrop" style="z-index:10004;" role="dialog" aria-modal="true" aria-labelledby="npc_merge_title" aria-describedby="npc_merge_intro">
  <div class="modal-container npc-merge-container">
    <div class="modal-header">
      <h2 class="modal-title" id="npc_merge_title">Merge profiles</h2>
      <div class="modal-actions">
        <button type="button" id="npc_merge_close" class="btn-cancel">Close</button>
      </div>
    </div>
    <div class="modal-body npc-merge-body">
      <p class="npc-merge-intro" id="npc_merge_intro">Actors that are the same character can share one profile. A few well-known built-in characters are linked automatically; here you can also merge two actors that share a name. Every actor row stays in the list, and Unlink reverses either kind.</p>
      <p id="npc_merge_status" class="npc-merge-status" role="status" aria-live="polite"></p>
      <p id="npc_merge_error" class="npc-merge-error" role="alert" hidden></p>
      <p id="npc_merge_auto_note" class="npc-merge-note" role="note" hidden></p>

      <section id="npc_merge_shared_panel" class="npc-merge-panel" hidden aria-labelledby="npc_merge_shared_heading">
        <h3 id="npc_merge_shared_heading">Shared profile</h3>
        <p id="npc_merge_shared_kind" class="npc-merge-auto" hidden></p>
        <p>These actors read and write one profile. New memory is written once and every actor listed here sees it. Each actor keeps its own row, name and RefID.</p>
        <ul id="npc_merge_shared_list" class="npc-merge-list"></ul>
        <p class="npc-merge-warn" id="npc_merge_unlink_warn">Unlinking separates every actor in this group. Each other actor gets its own original character data back. The kept profile retains its current data, including memory written while shared. That shared-period memory cannot be split apart again.</p>
        <label class="npc-merge-confirm"><input type="checkbox" id="npc_merge_unlink_confirm"> <span id="npc_merge_unlink_confirm_label">I understand that memory written while shared stays with the kept profile.</span></label>
        <div class="npc-merge-actions">
          <button type="button" id="npc_merge_unlink" class="npc-merge-danger" disabled>Unlink shared profile</button>
        </div>
      </section>

      <section id="npc_merge_select_panel" class="npc-merge-panel" hidden aria-labelledby="npc_merge_select_heading">
        <h3 id="npc_merge_select_heading">Choose the other actor</h3>
        <p class="npc-merge-current">
          <span class="npc-muted">This actor:</span>
          <span id="npc_merge_current_name"></span>
          <span class="npc-merge-refid" id="npc_merge_current_refid"></span>
          <span class="npc-merge-origin" id="npc_merge_current_origin"></span>
        </p>
        <p>Pick the profile that belongs to the same character. Only available plugin-defined references that are not already shared are offered. A manual merge always pairs exactly two actors that share a name.</p>
        <ul id="npc_merge_candidates" class="npc-merge-list"></ul>
        <div class="npc-merge-actions">
          <button type="button" id="npc_merge_compare_btn" class="npc-merge-primary" disabled>Compare profiles</button>
        </div>
      </section>

      <section id="npc_merge_compare_panel" class="npc-merge-panel" hidden aria-labelledby="npc_merge_compare_heading">
        <h3 id="npc_merge_compare_heading">Choose the profile to keep</h3>
        <p>The kept profile wins every field below. The other profile is retained rather than deleted, which is what lets Unlink restore it.</p>
        <div id="npc_merge_compare" class="npc-merge-compare"></div>
        <p class="npc-merge-warn">After merging, both actors write new memory into the kept profile. Existing histories stay attached to the name they were recorded under and are not reassigned to the kept profile.</p>
        <label class="npc-merge-confirm"><input type="checkbox" id="npc_merge_same_character"> <span>I confirm these two actors are the same character.</span></label>
        <div class="npc-merge-actions">
          <button type="button" id="npc_merge_back">Back</button>
          <button type="button" id="npc_merge_submit" class="npc-merge-primary" disabled>Merge profiles</button>
        </div>
      </section>
    </div>
  </div>
</div>

<!-- Build Relationships Modal -->
<div id="rel_build_modal" class="modal-backdrop" style="z-index:10003; display:none;">
  <div class="modal-container rel-build-modal-container" style="max-width:500px;">
    <div class="modal-header" style="border-bottom:1px solid rgb(242, 124, 17); text-align:center; justify-content:center;">
      <h2 class="modal-title" style="color:rgb(242, 124, 17); margin:0; width:100%; text-align:center;">🔗 Build Relationships</h2>
    </div>
    <div class="modal-body" style="padding:24px; text-align:center;">
      <div id="rel_build_content">
        <!-- Info Box -->
        <div style="background:#2a2a3a; border:1px solid #5a5a6a; border-radius:8px; padding:12px; margin-bottom:16px; text-align:left;">
          <div style="color:#9fb1c9; font-size:0.85em; line-height:1.4;">
            💡 Building runs in the background while you play. You can adjust any NPC individually by clicking their profile and editing <strong>Relationship Affinities</strong>.
          </div>
        </div>

        <!-- Model Info -->
        <div style="background:#1a1a2a; border:1px solid #4a4a4a; border-radius:8px; padding:16px; margin-bottom:20px;">
          <div style="color:#9fb1c9; font-size:0.9em; margin-bottom:4px;">Relationship Model</div>
          <div id="rel_build_model" style="color:rgb(242, 124, 17); font-size:1.1em; font-weight:bold;">Loading...</div>
        </div>

        <!-- NPC Counts -->
        <div style="display:flex; gap:16px; justify-content:center; margin-bottom:20px;">
          <div style="background:#1e3f1e; border:1px solid #2d5a2d; border-radius:8px; padding:16px; min-width:120px;">
            <div style="color:#4ade80; font-size:2em; font-weight:bold;" id="rel_count_built">--</div>
            <div style="color:#9fb1c9; font-size:0.85em;">Already Built</div>
          </div>
          <div style="background:#3f2f1e; border:1px solid #5a4a2d; border-radius:8px; padding:16px; min-width:120px;">
            <div style="color:rgb(242, 124, 17); font-size:2em; font-weight:bold;" id="rel_count_pending">--</div>
            <div style="color:#9fb1c9; font-size:0.85em;">Need Building</div>
          </div>
        </div>

        <!-- Options -->
        <div style="text-align:left; background:#2a2a3a; border-radius:8px; padding:16px; margin-bottom:20px;">
          <label style="display:flex; align-items:flex-start; gap:10px; color:#cfd9ea; margin-bottom:12px; cursor:pointer;">
            <input type="checkbox" id="rel_build_force" style="width:16px; height:16px; min-width:16px; min-height:16px; accent-color:rgb(242, 124, 17); margin-top:2px;">
            <span>Include NPCs that were already built</span>
          </label>
          <label style="display:flex; align-items:flex-start; gap:10px; color:#cfd9ea; cursor:pointer;">
            <input type="checkbox" id="rel_build_infer" checked style="width:16px; height:16px; min-width:16px; min-height:16px; accent-color:rgb(242, 124, 17); margin-top:2px;">
            <div>
              <span>Build advanced relationship connections</span>
              <div style="font-size:0.75em; color:#7a8a9a; margin-top:4px; line-height:1.4;">
                Creates indirect opinions based on social networks.<br>
                <em>Example: If Eris loves Vivienne (+80) and Vivienne hates a bandit (-70), Eris becomes wary of that bandit too.</em>
              </div>
            </div>
          </label>
        </div>

        <!-- Buttons -->
        <div style="display:flex; gap:12px; justify-content:center;">
          <button id="rel_build_start" style="background:#1e3f1e; color:#fff; border:none; padding:12px 32px; border-radius:8px; font-size:1.1em; font-weight:bold; cursor:pointer; transition:background 0.2s;">
            Start Building
          </button>
          <button id="rel_build_close" style="background:#7a1e1e; color:#fff; border:none; padding:12px 32px; border-radius:8px; font-size:1.1em; font-weight:bold; cursor:pointer; transition:background 0.2s;">
            Cancel
          </button>
        </div>
      </div>

      <!-- Progress View -->
      <div id="rel_build_progress" style="display:none;">
        <div style="margin-bottom:20px;">
          <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
            <span id="rel_build_status" style="color:rgb(242, 124, 17); font-weight:bold;">Processing...</span>
            <span id="rel_build_count" style="color:#9fb1c9;">0 / 0</span>
          </div>
          <div style="background:#1a1a2a; border-radius:8px; height:28px; overflow:hidden; border:1px solid #4a4a4a;">
            <div id="rel_build_bar" style="background:linear-gradient(90deg, rgb(242, 124, 17), #f59e0b); height:100%; width:0%; transition:width 0.3s;"></div>
          </div>
        </div>
        <div id="rel_build_log" style="background:#1a1a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; height:200px; overflow-y:auto; font-family:monospace; font-size:12px; color:#9fb1c9; text-align:left;">
        </div>
        <div style="margin-top:16px;">
          <button id="rel_build_done" style="display:none; background:#3a3a4a; color:rgb(242, 124, 17); border:1px solid rgb(242, 124, 17); padding:12px 32px; border-radius:8px; font-size:1.1em; font-weight:bold; cursor:pointer;">
            Done
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const PROFILES_BY_ID = <?= json_encode($profilesById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  window.PROFILES_BY_ID = PROFILES_BY_ID;
  const PROFILE_OPTIONS = <?= json_encode($profileOptions ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  const modal = document.getElementById('npc_modal');
  const iframe = document.getElementById('npc_modal_iframe');
  const loadStatus = document.getElementById('npc_modal_load_status');
  const loadTitle = document.getElementById('npc_modal_load_title');
  const loadMessage = document.getElementById('npc_modal_load_message');
  const retryBtn = document.getElementById('npc_modal_retry');
  let modalUrl = '';
  let expectedNpcId = '';
  let modalRequestId = 0;

  function setModalLoadState(state, message){
    const ready = state === 'ready';
    if (iframe) iframe.style.visibility = ready ? 'visible' : 'hidden';
    if (loadStatus) loadStatus.classList.toggle('is-visible', !ready);
    if (loadTitle) loadTitle.textContent = state === 'error' ? 'Unable to load NPC profile' : 'Loading profile...';
    if (loadMessage) loadMessage.textContent = message || (state === 'error' ? 'Refresh the NPC list and try again.' : 'Retrieving the latest NPC data.');
    if (retryBtn) retryBtn.style.display = state === 'error' ? '' : 'none';
    document.querySelectorAll('#npc_modal .modal-actions button').forEach(function(button){
      if (button.id !== 'npc_modal_close') button.disabled = !ready;
    });
  }

  function loadModalUrl(){
    if (!modalUrl) return;
    const requestId = ++modalRequestId;
    setModalLoadState('loading');
    const separator = modalUrl.includes('?') ? '&' : '?';
    iframe.src = modalUrl + separator + '_modal_request=' + encodeURIComponent(String(requestId));
  }

  function openModal(url){
    modalUrl = url;
    const match = url.match(/[?&]edit=([^&]+)/);
    expectedNpcId = match ? String(decodeURIComponent(match[1])) : '';
    loadModalUrl();
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    try {
      // Track current editing id for portrait updates
      (function(){
        try { const m = url.match(/[?&]edit=([^&]+)/); window.CURRENT_NPC_ID = m ? String(decodeURIComponent(m[1])) : ''; } catch(_e){ window.CURRENT_NPC_ID=''; }
      })();
      const tabs = document.getElementById('npc_modal_tabs');
      const bioPane = document.getElementById('pane_bio');
      const manualPane = document.getElementById('pane_manual');
      const exportBtn = document.getElementById('npc_modal_export');
      const importBioBtn = document.getElementById('npc_modal_import_to');
      const isEdit = /[?&]edit=/.test(url);
      if (isEdit){
        if (tabs) tabs.style.display = 'none';
        if (bioPane) { bioPane.style.display = 'none'; bioPane.classList.remove('active'); }
        if (manualPane) { manualPane.style.display = 'block'; manualPane.classList.add('active'); }
        // Show export/import buttons only for existing NPCs
        if (exportBtn) exportBtn.style.display = '';
        if (importBioBtn) importBioBtn.style.display = '';
      } else {
        if (tabs) tabs.style.display = 'flex';
        // Hide export/import buttons for new NPCs
        if (exportBtn) exportBtn.style.display = 'none';
        if (importBioBtn) importBioBtn.style.display = 'none';
      }
    } catch(_e){}

     
  }
  function closeModal(){
    modalRequestId++;
    modalUrl = '';
    expectedNpcId = '';
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    setModalLoadState('ready');
    try { iframe.src='about:blank'; } catch(_){}
  }
  if (retryBtn) retryBtn.addEventListener('click', loadModalUrl);
  const headerSave = document.getElementById('npc_modal_save_header');
  if (headerSave){
    window.NPC_UPDATE_SAVE_STATE = function(){
      try {
        const doc = iframe && iframe.contentDocument;
        const nameEl = doc ? doc.getElementById('npc_name') : null;
        const val = nameEl ? String(nameEl.value||'').trim() : '';
        const disable = (val === '');
        headerSave.disabled = disable;
        if (disable) headerSave.title = 'Enter NPC Name to save'; else headerSave.removeAttribute('title');
      } catch(_e){}
    };
    // Watch for iframe content load and bind input listener
    try {
      iframe.addEventListener('load', function(){
        try {
          if (!modalUrl || iframe.src === 'about:blank') return;
          const doc = iframe && iframe.contentDocument;
          const nameEl = doc ? doc.getElementById('npc_name') : null;
          const loadedMarker = doc ? doc.querySelector('[data-npc-profile-loaded="1"]') : null;
          const loadError = doc ? doc.querySelector('[data-npc-load-error="1"]') : null;
          const loadedNpcId = loadedMarker ? String(loadedMarker.getAttribute('data-npc-id') || '') : '';
          if (loadError || !nameEl || (expectedNpcId && loadedNpcId !== expectedNpcId)) {
            setModalLoadState('error', loadError ? 'This NPC profile no longer exists. Refresh the NPC list and try again.' : 'The server returned incomplete or stale NPC data.');
            return;
          }
          if (nameEl){
            ['input','change','keyup'].forEach(evt=> nameEl.addEventListener(evt, window.NPC_UPDATE_SAVE_STATE));
          }
          setModalLoadState('ready');
        } catch(_e){
          setModalLoadState('error', 'The NPC profile response could not be read.');
          return;
        }
        window.NPC_UPDATE_SAVE_STATE();
      });
    } catch(_e){}
    headerSave.addEventListener('click', function(){
      try {
        // Guard: require NPC name
        window.NPC_UPDATE_SAVE_STATE(); if (headerSave.disabled) { return; }
        const btn = iframe && iframe.contentDocument ? iframe.contentDocument.getElementById('npc_modal_save') : null;
        if (btn){ btn.click(); }
        // else: nothing (no bio import submit anymore)
      } catch(_e){}
    });
  }
  // Reset NPC button wiring (reimport non-empty template fields by current name)
  (function(){
    const resetBtn = document.getElementById('npc_modal_reset');
    if (!resetBtn) return;
    resetBtn.addEventListener('click', async function(e){
      e.preventDefault();
      try {
        const doc = iframe && iframe.contentDocument;
        const nameEl = doc ? doc.getElementById('npc_name') : null;
        const npcName = nameEl ? String(nameEl.value||'').trim() : '';
        if (!npcName){ alert('Enter NPC Name to reset from template.'); return; }
        // Confirm overwrite of fields present in template
        const ok = window.confirm('Reset NPC "'+npcName+'" from bio template?\n\nThis will overwrite only fields present in the template. Other fields will remain unchanged.');
        if (!ok) return;
        const res = await fetch('npc_master.php?bio_detail=1&name='+encodeURIComponent(npcName));
        let j={}; try { j = await res.json(); } catch(_e) { j={ok:false}; }
        if (!j || !j.ok){ alert('No bio template found for "'+npcName+'"'); return; }
        const d = j.data || {};
        function setVal(id, val){ const el = doc ? doc.getElementById(id) : null; if (el) el.value = String(val); }
        function applyIfFilled(id, val){ if (val==null) return; const s=String(val).trim(); if (!s) return; setVal(id, s); }
        function applyRelationshipSeed(val){
          if (val == null || !doc) return;
          const raw = String(val).trim();
          if (!raw || raw.charAt(0) !== '{') return;
          let parsed = null;
          try { parsed = JSON.parse(raw); } catch(_e) { parsed = null; }
          if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return;
          const ext = doc.getElementById('extended_data');
          if (!ext) return;
          let obj = {};
          try { obj = JSON.parse(String(ext.value||'')||'{}')||{}; } catch(_e) { obj = {}; }
          obj.relationships = parsed;
          ext.value = JSON.stringify(obj);
        }
        applyIfFilled('core', d.core);
        applyIfFilled('npc_static_bio', d.npc_static_bio);
        applyIfFilled('personality', d.personality);
        applyIfFilled('appearance', d.appearance);
        applyIfFilled('occupation', d.occupation);
        applyIfFilled('skills', d.skills);
        applyIfFilled('speechstyle', d.speechstyle);
        applyIfFilled('goals', d.goals);
        applyRelationshipSeed(d.relationships);
        applyIfFilled('oghma_knowledge_tags', d.oghma_knowledge_tags);
        applyIfFilled('voiceid', d.voiceid);
        applyIfFilled('gender', d.gender);
        applyIfFilled('race', d.race);
        applyIfFilled('refid', d.refid);
        // Try to reflect middle_term_enabled if provided in template (rare)
        try { const mtm = (d && typeof d.middle_term_enabled!=='undefined') ? Number(d.middle_term_enabled) : null; if (mtm!==null){ const cb = doc.getElementById('middle_term_enabled'); if (cb) cb.checked = (Number(mtm)===1); } } catch(_e){}
        try { if (typeof window.NPC_UPDATE_SAVE_STATE === 'function') window.NPC_UPDATE_SAVE_STATE(); } catch(_e){}
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Template values applied'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      } catch(_e){}
    });
  })();
  // View Diary button wiring
  (function(){
    const btn = document.getElementById('npc_modal_diary');
    if (btn) {
      btn.addEventListener('click', function(e){
        e.preventDefault();
        try {
          const id = String(window.CURRENT_NPC_ID||'').trim();
          if (!id) { alert('No NPC selected'); return; }
          // Get NPC name from the form in iframe
          const doc = iframe && iframe.contentDocument;
          const nameEl = doc ? doc.getElementById('npc_name') : null;
          const npcName = nameEl ? String(nameEl.value||'').trim() : '';
          if (!npcName) { alert('Cannot determine NPC name'); return; }
          // Open diary book page in new tab with person filter
          const url = '<?php echo $webRoot; ?>/ui/diary_book.php?person=' + encodeURIComponent(npcName);
          window.open(url, '_blank');
        } catch(_e){
          console.error('Failed to open diary:', _e);
        }
      });
    }
  })();

   // Regenerate profile using AI
  // Global LLM connector data for AI generate dropdown
  const AI_GEN_LLM_CONNECTORS = <?= json_encode($llmRows ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  const AI_GEN_PROFILE_CONN = <?= json_encode($profilesConnById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  const AI_GEN_DEFAULT_CONNECTOR = <?= json_encode($GLOBALS["CORE_CONNECTOR_PROFILES"] ?? '', JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  
  (function(){
    const regenBtn = document.getElementById('npc_modal_regen');
    if (!regenBtn) return;

    const escapeHtml = function(value) {
      return String(value || '').replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
      });
    };

    const buildConnectorOptions = function(defaultConnectorId) {
      let llmOptions = '<option value="">-- Use Global Connector --</option>';
      AI_GEN_LLM_CONNECTORS.forEach(function(c){
        const cid = String(c.id || '');
        const lbl = c.label || ('Connector #' + cid);
        const sel = (cid === String(defaultConnectorId || '')) ? ' selected' : '';
        llmOptions += '<option value="' + cid + '"' + sel + '>' + escapeHtml(lbl) + '</option>';
      });
      return llmOptions;
    };

    const showAIGenerateResult = function(success, message, npcName) {
      const resultBox = document.createElement('div');
      resultBox.style.position = 'fixed';
      resultBox.style.inset = '0';
      resultBox.style.zIndex = '10050';
      resultBox.style.display = 'flex';
      resultBox.style.alignItems = 'center';
      resultBox.style.justifyContent = 'center';
      resultBox.style.background = 'rgba(0,0,0,0.65)';

      const iconColor = success ? '#4ade80' : '#f87171';
      const iconSymbol = success ? '✓' : '✕';
      const title = success ? 'Profile Generated Successfully' : 'Profile Generation Failed';

      resultBox.innerHTML = '<div style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:20px; max-width:500px; width:92%; color:#e9efff;">\
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">\
          <div style="width:32px; height:32px; border-radius:50%; background:' + iconColor + '; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:bold; color:#111;">' + iconSymbol + '</div>\
          <div style="font-weight:700; color:' + iconColor + '; font-size:18px;">' + title + '</div>\
        </div>\
        <div style="font-size:14px; color:#cfd9ea; margin-bottom:16px; line-height:1.5;">' + escapeHtml(message) + '</div>\
        <div style="display:flex; gap:8px; justify-content:flex-end;">\
          ' + (success ? '' : '<button id="ai_result_retry" style="padding:10px 20px; color:#fff; background:rgba(85,95,109,0.9); border:1px solid rgba(156,163,175,0.3); border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all 0.2s ease;">Try Again</button>') + '\
          <button id="ai_result_ok" style="padding:10px 20px; color:#111; background:' + (success ? 'rgb(242,124,17)' : 'rgba(85,95,109,0.9)') + '; border:1px solid ' + (success ? 'rgb(242,124,17)' : 'rgba(156,163,175,0.3)') + '; border-radius:8px; cursor:pointer; font-size:14px; font-weight:700; ' + (success ? 'color:#111;' : 'color:#fff;') + ' transition:all 0.2s ease;">' + (success ? 'Reload to View' : 'Close') + '</button>\
        </div></div>';
      document.body.appendChild(resultBox);

      const okBtn = resultBox.querySelector('#ai_result_ok');
      const retryBtn = resultBox.querySelector('#ai_result_retry');

      okBtn.addEventListener('click', function(){
        document.body.removeChild(resultBox);
        if (success) {
          document.location.reload();
        }
      });

      if (retryBtn) {
        retryBtn.addEventListener('click', function(){
          document.body.removeChild(resultBox);
          const retryTarget = document.getElementById('npc_modal_regen');
          if (retryTarget) retryTarget.click();
        });
      }

      resultBox.addEventListener('click', function(e){
        if (e.target === resultBox) {
          document.body.removeChild(resultBox);
        }
      });
    };

    regenBtn.addEventListener('click', async function(e){
      e.preventDefault();
      try {
        const doc = iframe && iframe.contentDocument;
        const nameEl = doc ? doc.getElementById('npc_name') : null;
        const npcName = nameEl ? String(nameEl.value||'').trim() : '';

        if (!npcName) { alert('Enter NPC Name to generate profile.'); return; }

        const profileIdEl = doc ? doc.getElementById('profile_id') : null;
        const profileId = profileIdEl ? String(profileIdEl.value||'').trim() : '';

        let defaultConnectorId = AI_GEN_DEFAULT_CONNECTOR;
        if (profileId && AI_GEN_PROFILE_CONN[profileId]) {
          const pc = AI_GEN_PROFILE_CONN[profileId];
          if (pc.llm_primary_id) defaultConnectorId = String(pc.llm_primary_id);
        }

        const llmOptions = buildConnectorOptions(defaultConnectorId);
        const promptBox = document.createElement('div');
        promptBox.style.position='fixed';
        promptBox.style.inset='0';
        promptBox.style.zIndex='10050';
        promptBox.style.display='flex';
        promptBox.style.alignItems='center';
        promptBox.style.justifyContent='center';
        promptBox.style.background='rgba(0,0,0,0.65)';
        promptBox.innerHTML = '<div style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:16px; max-width:900px; width:94%; color:#e9efff; max-height:92vh; overflow:auto;">\
          <div style="font-weight:700; color:rgb(242,124,17); margin-bottom:8px; font-size:18px;">AI Generate Profile for "' + escapeHtml(npcName) + '"</div>\
          <div style="font-size:13px; color:#cfd9ea; margin-bottom:12px;">Add any specific information or instructions for the AI to consider when generating this profile, then review the events that will be used before generating.</div>\
          <label style="display:block; font-size:13px; margin:6px 0 4px; color:#cfd9ea; font-weight:600;">LLM Connector:</label>\
          <select id="ai_llm_connector" style="width:100%; padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#333; color:#e9efff; font-family:inherit; margin-bottom:8px;">'+llmOptions+'</select>\
          <label style="display:block; font-size:13px; margin:6px 0 4px; color:#cfd9ea; font-weight:600;">Custom Instructions (optional):</label>\
          <textarea id="ai_user_prompt" placeholder="Example: This NPC should be a merchant specializing in enchanted weapons, with a mysterious past..." style="width:100%; min-height:96px; padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; resize:vertical; font-family:inherit;"></textarea>\
          <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:14px; flex-wrap:wrap;">\
            <div style="flex:1 1 260px;">\
              <label for="ai_event_limit" style="display:block; font-size:13px; margin:0 0 4px; color:#cfd9ea; font-weight:600;">Events To Use: <span id="ai_event_limit_value">100</span></label>\
              <input id="ai_event_limit" type="range" min="10" max="200" step="10" value="100" style="width:100%;">\
            </div>\
            <div id="ai_event_meta" style="font-size:12px; color:#9fb2cc; text-align:right; min-width:220px;">Loading event preview...</div>\
          </div>\
          <div id="ai_event_memory_note" style="display:none; font-size:12px; color:#9fb2cc; margin-top:6px;">Middle-term memory will also be included automatically if it exists for this NPC.</div>\
          <div style="margin-top:14px;">\
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:6px; flex-wrap:wrap;">\
              <label style="display:block; font-size:13px; color:#cfd9ea; font-weight:600;">Events That Will Be Used</label>\
              <button id="ai_event_reset" type="button" style="padding:6px 10px; color:#fff; background:rgba(85,95,109,0.9); border:1px solid rgba(156,163,175,0.3); border-radius:8px; cursor:pointer; font-size:12px; font-weight:600;">Reset To Slider Selection</button>\
            </div>\
            <div id="ai_event_list" style="max-height:330px; overflow:auto; border:1px solid #454545; border-radius:8px; background:#232323; padding:10px;">\
              <div style="font-size:13px; color:#9fb2cc;">Loading event preview...</div>\
            </div>\
          </div>\
          <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">\
            <button id="ai_prompt_cancel" style="padding:10px 20px; color:#fff; background:rgba(85,95,109,0.9); border:1px solid rgba(156,163,175,0.3); border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all 0.2s ease;">Cancel</button>\
            <button id="ai_prompt_ok" style="padding:10px 20px; color:#111; background:rgb(242,124,17); border:1px solid rgb(242,124,17); border-radius:8px; cursor:pointer; font-size:14px; font-weight:700; transition:all 0.2s ease;">Generate Profile</button>\
          </div></div>';
        document.body.appendChild(promptBox);

        const promptInput = promptBox.querySelector('#ai_user_prompt');
        const slider = promptBox.querySelector('#ai_event_limit');
        const sliderValue = promptBox.querySelector('#ai_event_limit_value');
        const eventMeta = promptBox.querySelector('#ai_event_meta');
        const eventList = promptBox.querySelector('#ai_event_list');
        const eventReset = promptBox.querySelector('#ai_event_reset');
        const memoryNote = promptBox.querySelector('#ai_event_memory_note');
        const okBtn = promptBox.querySelector('#ai_prompt_ok');
        const cancelBtn = promptBox.querySelector('#ai_prompt_cancel');
        let previewEvents = [];
        let selectedEvents = [];
        let previewTotalAvailable = 0;
        let previewMiddleTermIncluded = false;
        let previewRequestToken = 0;

        promptInput.focus();

        const closePromptBox = function() {
          if (promptBox.parentNode) {
            document.body.removeChild(promptBox);
          }
        };

        const updateEventMeta = function(totalAvailable, middleTermIncluded) {
          const currentCount = selectedEvents.length;
          eventMeta.textContent = currentCount + ' selected';
          if (typeof totalAvailable === 'number') {
            eventMeta.textContent += ' of ' + totalAvailable + ' available events';
          }
          memoryNote.style.display = middleTermIncluded ? 'block' : 'none';
        };

        const renderSelectedEvents = function() {
          updateEventMeta(previewTotalAvailable, previewMiddleTermIncluded);
          if (!selectedEvents.length) {
            eventList.innerHTML = '<div style="font-size:13px; color:#f3d38a;">No events selected. You can still generate a profile using the current character sheet, saved memory, and any custom instructions.</div>';
            okBtn.disabled = false;
            okBtn.style.opacity = '';
            okBtn.style.cursor = 'pointer';
            return;
          }

          okBtn.disabled = false;
          okBtn.style.opacity = '';
          okBtn.style.cursor = 'pointer';

          eventList.innerHTML = selectedEvents.map(function(evt, idx){
            const label = escapeHtml(evt.label || 'Event');
            const content = escapeHtml(evt.content || '');
            return '<div style="display:flex; gap:10px; align-items:flex-start; padding:10px 0; border-bottom:' + (idx === selectedEvents.length - 1 ? 'none' : '1px solid #383838') + ';">\
              <div style="flex:1 1 auto; min-width:0;">\
                <div style="font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#f1a54d; font-weight:700; margin-bottom:4px;">' + label + '</div>\
                <div style="font-size:13px; line-height:1.45; color:#e9efff; white-space:pre-wrap;">' + content + '</div>\
              </div>\
              <button type="button" class="ai-event-remove" data-event-id="' + escapeHtml(evt.id || String(idx)) + '" title="Remove event" style="flex:0 0 auto; width:30px; height:30px; border-radius:8px; border:1px solid rgba(248,113,113,0.35); background:rgba(127,29,29,0.25); color:#fca5a5; cursor:pointer; font-size:16px; line-height:1;">🗑</button>\
            </div>';
          }).join('');

          Array.from(eventList.querySelectorAll('.ai-event-remove')).forEach(function(btn){
            btn.addEventListener('click', function(){
              const eventId = String(btn.getAttribute('data-event-id') || '');
              selectedEvents = selectedEvents.filter(function(evt){ return String(evt.id || '') !== eventId; });
              renderSelectedEvents();
            });
          });
        };

        const loadPreview = async function() {
          const requestToken = ++previewRequestToken;
          const eventLimit = Number(slider.value || 100);
          sliderValue.textContent = String(eventLimit);
          eventMeta.textContent = 'Loading event preview...';
          eventList.innerHTML = '<div style="font-size:13px; color:#9fb2cc;">Loading event preview...</div>';
          okBtn.disabled = true;
          okBtn.style.opacity = '0.6';
          okBtn.style.cursor = 'wait';

          const params = new URLSearchParams();
          params.append('name', npcName);
          params.append('event_limit', String(eventLimit));

          let responseJson = null;
          try {
            const res = await fetch('../cmd/action_ai_profile_context_preview.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
              body: params.toString()
            });
            responseJson = await res.json();
          } catch (err) {
            if (requestToken !== previewRequestToken) return;
            eventMeta.textContent = 'Failed to load event preview';
            eventList.innerHTML = '<div style="font-size:13px; color:#f5b1b1;">' + escapeHtml(String((err && err.message) || err || 'Failed to load preview.')) + '</div>';
            okBtn.disabled = true;
            okBtn.style.cursor = 'not-allowed';
            return;
          }

          if (requestToken !== previewRequestToken) return;

          if (!responseJson || !responseJson.done) {
            eventMeta.textContent = 'Failed to load event preview';
            eventList.innerHTML = '<div style="font-size:13px; color:#f5b1b1;">' + escapeHtml((responseJson && responseJson.error) ? responseJson.error : 'Failed to load preview.') + '</div>';
            okBtn.disabled = true;
            okBtn.style.cursor = 'not-allowed';
            return;
          }

          previewEvents = Array.isArray(responseJson.events) ? responseJson.events.slice() : [];
          selectedEvents = previewEvents.slice();
          previewTotalAvailable = Number(responseJson.total_available || previewEvents.length);
          previewMiddleTermIncluded = !!responseJson.middle_term_memory_included;
          renderSelectedEvents();
        };

        cancelBtn.addEventListener('click', closePromptBox);

        promptBox.addEventListener('click', function(ev){
          if (ev.target === promptBox) {
            closePromptBox();
          }
        });

        slider.addEventListener('input', function() {
          sliderValue.textContent = String(slider.value || '100');
        });

        slider.addEventListener('change', function() {
          loadPreview();
        });

        eventReset.addEventListener('click', function() {
          selectedEvents = previewEvents.slice();
          renderSelectedEvents();
        });

        okBtn.addEventListener('click', async function(){
          const userPrompt = String(promptInput.value||'').trim();
          const connectorSelect = promptBox.querySelector('#ai_llm_connector');
          const selectedConnector = connectorSelect ? String(connectorSelect.value||'').trim() : '';
          const eventLimit = Number(slider.value || 100);
          closePromptBox();

          document.getElementById("npc_modal").style.cursor="wait";

          const processingMessage = document.createElement('div');
          processingMessage.innerHTML = '<div style="display:flex;align-items:center;gap:10px;"><div class="spinner" style="width:20px;height:20px;border:3px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;"></div><span>Generating profile with AI...</span></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
          processingMessage.style.position = 'fixed';
          processingMessage.style.top = '50%';
          processingMessage.style.left = '50%';
          processingMessage.style.transform = 'translate(-50%, -50%)';
          processingMessage.style.backgroundColor = 'rgba(0,0,0,0.9)';
          processingMessage.style.color = '#fff';
          processingMessage.style.padding = '16px 24px';
          processingMessage.style.borderRadius = '10px';
          processingMessage.style.zIndex = '10001';
          processingMessage.style.border = '1px solid #4a4a4a';
          processingMessage.id="processing_wheel";
          document.body.appendChild(processingMessage);

          const params = new URLSearchParams();
          params.append('name', npcName);
          params.append('event_limit', String(eventLimit));
          params.append('selected_events', JSON.stringify(selectedEvents));
          if (userPrompt) params.append('user_prompt', userPrompt);
          if (selectedConnector) params.append('connector_id', selectedConnector);

          let j = {};
          let fetchError = null;
          try {
            const res = await fetch('../cmd/action_ai_regen_profile.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
              body: params.toString()
            });
            if (!res.ok) {
              fetchError = 'Server returned status ' + res.status;
            } else {
              try { j = await res.json(); } catch(_e) { j = {done:false, error:'Invalid JSON response from server'}; }
            }
          } catch(e) {
            fetchError = 'Network error: ' + String(e.message || e);
          }
          
          // Remove processing message
          const procEl = document.getElementById('processing_wheel');
          if (procEl) procEl.remove();
          document.getElementById("npc_modal").style.cursor = "";

          if (fetchError) {
            showAIGenerateResult(false, fetchError, npcName);
            return;
          }

          if (j && j.done) {
            const eventsUsed = Number(j.events_used || selectedEvents.length || 0);
            showAIGenerateResult(true, 'Profile successfully generated with ' + (j.fields_updated || 'multiple') + ' fields updated using ' + eventsUsed + ' selected events.', npcName);
          } else {
            const errMsg = (j && j.error) ? j.error : 'Unknown error occurred. Check the server logs for details.';
            showAIGenerateResult(false, errMsg, npcName);
          }
        });

        loadPreview();

      } catch(_e){console.log(_e)}
    });
  })();

  
  // Profile Versions button wiring
  (function(){
    const btn = document.getElementById('npc_modal_history');
    const overlay = document.getElementById('history_viewer');
    const listBox = document.getElementById('history_list');
    const detailBox = document.getElementById('history_detail');
    const closeBtn = document.getElementById('history_close');
    const reportBtn = document.getElementById('history_generation');

    const LABELS = {

      npc_name: 'NPC Name',
      profile_id: 'Profile',
      gender: 'Gender',
      race: 'Race',
      voiceid: 'Voice ID',
      refid: 'Ref ID',
      core: 'Core',
      npc_static_bio: 'Backstory',
      appearance: 'Appearance',
      personality: 'Personality',
      relationships: 'Relationships',
      occupation: 'Occupation',
      skills: 'Skills',
      speechstyle: 'Speech Style',
      goals: 'Goals',
      oghma_knowledge_tags: 'Oghma Tags',
      emote_moods: 'Emote Moods',
      prompt_head: 'Prompt Head',
      dynamic_profile: 'Dynamic Profile',
      npc_favorite: 'Favorite',
      lock_profile: 'Lock Profile',
      tags: 'Tags',
      base: 'Base'
    };
    function close(){ if (overlay) overlay.style.display='none'; }
    if (closeBtn) closeBtn.addEventListener('click', function(e){ e.preventDefault(); close(); });
    if (reportBtn) reportBtn.addEventListener('click', function(e){ window.open("npc_report.php?npcid="+ String(window.CURRENT_NPC_ID||'').trim()) });
    if (overlay) overlay.addEventListener('click', function(e){ if (e.target===overlay) close(); });
    function renderDetail(entry, prev){
      if (!entry){ detailBox.innerHTML = '<div style="color:#9fb1c9">No data</div>'; return; }
      const f = entry.fields||{}; const prevF = (prev && prev.fields) ? prev.fields : {};
      const order = ['npc_name','profile_id','gender','race','voiceid','refid','core','npc_static_bio','appearance','personality','relationships','occupation','skills','speechstyle','goals','oghma_knowledge_tags','emote_moods','prompt_head','dynamic_profile','npc_favorite','lock_profile','tags','base'];
      let html = '';
      html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
      html += '<div style="color:#cfd9ea;">'+(entry.when_tamrielic || (entry.created?('Created '+entry.created):'Unknown time'))+(entry.created?(' <span style="color:#9fb1c9">('+entry.created+')</span>'):'')+'</div>';
      html += '<button class="btn-restore-history" data-history-id="'+String(entry.history_id||'')+'" style="background:rgb(242,124,17); color:#111; border:1px solid rgb(242,124,17); border-radius:6px; padding:6px 12px; cursor:pointer; font-weight:700;">Restore this version</button>';
      html += '</div>';
      html += '<div style="display:grid; grid-template-columns: 220px 1fr; gap:6px;">';
      order.forEach(k=>{
        let v = f[k]; const has = (v!==null && v!==undefined && String(v).trim()!=='');
        if (!has) return;
        const changed = (prevF && String(prevF[k]??'') !== String(v));
        const label = LABELS[k] || k.replace(/_/g,' ');
        if (k==='profile_id') { v = (PROFILES_BY_ID && PROFILES_BY_ID[String(v||'')]) ? PROFILES_BY_ID[String(v)] : v; }
        html += '<div style="color:rgb(242,124,17); font-weight:700;">'+label+'</div>';
        html += '<div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px;'+(changed?' background:#333333;':'')+'">'+String(v).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))+'</div>';
      });
      html += '</div>';
      detailBox.innerHTML = html;
      // Wire up restore button
      const restoreBtn = detailBox.querySelector('.btn-restore-history');
      if (restoreBtn) {
        restoreBtn.addEventListener('click', async function(){
          const histId = this.getAttribute('data-history-id');
          if (!histId) return;
          const ok = confirm('Restore this historical version?\n\nThis will replace the current NPC profile with the selected snapshot. This action creates a new backup before restoring.');
          if (!ok) return;
          try {
            this.disabled = true;
            this.textContent = 'Restoring...';
            const fd = new FormData();
            fd.append('restore_from_history', '1');
            fd.append('history_id', histId);
            const res = await fetch('npc_master.php', { method:'POST', body: fd });
            let j = {}; try { j = await res.json(); } catch(_e) { j = {ok:false}; }
            if (j && j.ok) {
              close();
              try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='NPC restored from history'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 2000); } } catch(_e){}
              // Refresh the page to show updated NPC
              window.location.reload();
            } else {
              alert('Restore failed: ' + (j && j.error ? j.error : 'Unknown error'));
              this.disabled = false;
              this.textContent = 'Restore this version';
            }
          } catch(_e) {
            alert('Restore failed: ' + String(_e));
            this.disabled = false;
            this.textContent = 'Restore this version';
          }
        });
      }

     
    

    }
    function openHistory(){
      try {
        const id = String(window.CURRENT_NPC_ID||'').trim();
        if (!id){ return; }
        if (overlay) { overlay.style.display='flex'; }
        if (listBox) { listBox.innerHTML = '<div style="color:#9fb1c9">Loading…</div>'; }
        if (detailBox) { detailBox.innerHTML = '<div style="color:#9fb1c9">Fetching history…</div>'; }
        fetch('npc_master.php?history=1&id='+encodeURIComponent(id))
          .then(r=>r.json()).then(j=>{
            if (!j || !j.ok){ listBox.innerHTML = '<div style="color:#ff6b6b">Failed to load history</div>'; detailBox.innerHTML=''; return; }
            const entries = j.entries||[];
            if (entries.length===0){ listBox.innerHTML = '<div style="color:#9fb1c9">No history yet</div>'; detailBox.innerHTML=''; return; }
            listBox.innerHTML = '';
            entries.forEach((e, idx)=>{
              const div = document.createElement('div');
              div.style.border='1px solid #4a4a4a'; div.style.borderRadius='8px'; div.style.padding='8px'; div.style.cursor='pointer'; div.style.marginBottom='6px';
              const label = e.when_tamrielic || (e.created?('Created '+e.created):('Snapshot #'+String(e.history_id||idx+1)));
              const second = e.created?('<div style="color:#9fb1c9; font-size:11px;">'+e.created+'</div>') : '';
              div.innerHTML = '<div style="font-weight:700; color:#e9efff;">'+label+'</div>'+second;
              div.addEventListener('click', function(){
                listBox.querySelectorAll('.active').forEach(n=>{ n.classList.remove('active'); n.style.background=''; });
                this.classList.add('active'); this.style.background='#333333';
                renderDetail(e, idx>0?entries[idx-1]:null);
              });
              listBox.appendChild(div);
            });
          })
          .catch(()=>{ listBox.innerHTML = '<div style="color:#ff6b6b">Failed to load history</div>'; detailBox.innerHTML=''; });
      } catch(_){}
    }
    if (btn){ btn.addEventListener('click', function(e){ e.preventDefault(); openHistory(); }); }
  })();
  document.addEventListener('click', function(e){ if (e.target && e.target.id==='npc_modal_close') closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
  // Tabs in modal
  (function(){
    const tabs = document.querySelectorAll('#npc_modal_tabs .pf-tab');
    function activate(id){
      tabs.forEach(t=>t.classList.toggle('active', t.getAttribute('data-pane')===id));
      document.querySelectorAll('.pf-pane').forEach(p=>{ p.style.display = (p.id===id) ? 'block' : 'none'; p.classList.toggle('active', p.id===id); });
    }
    tabs.forEach(tb=> tb.addEventListener('click', ()=> activate(tb.getAttribute('data-pane'))));
    activate('pane_manual');
  })();
  // Bio DB wiring
  (function(){
    const list = document.getElementById('bio_list');
    const pager = document.getElementById('bio_pager');
    const inp = document.getElementById('bio_search_input');
    const letter = document.getElementById('bio_letter');
    const detail = document.getElementById('bio_detail');
    const useBtn = document.getElementById('bio_use_template');
    const createBtn = document.getElementById('bio_use_create');
    const incCore = document.getElementById('bio_inc_core');
    const incExt = document.getElementById('bio_inc_ext');
    const incOgh = document.getElementById('bio_inc_oghma');
    const incVM  = document.getElementById('bio_inc_vm');
    const selProfile = document.getElementById('bio_profile_id');
    let currentName = '';
    let page = 1; let total = 0; let pageSize = 20;
    async function fetchList(){
      const params = new URLSearchParams({ bio_search:'1', search:(inp.value||''), letter:(letter.value||''), page:String(page), pageSize:String(pageSize) });
      const res = await fetch('npc_master.php?'+params.toString()); let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (!j.ok) { list.innerHTML = '<div style="color:#ff6b6b">Failed to load</div>'; return; }
      total = Number(j.total||0); page = Number(j.page||1); pageSize = Number(j.pageSize||20);
      list.innerHTML = '';
      (j.items||[]).forEach(it=>{
        const div = document.createElement('div');
        div.style.border = '1px solid #4a4a4a'; div.style.borderRadius='8px'; div.style.padding='8px'; div.style.cursor='pointer';
        div.innerHTML = `<div style="font-weight:700; color:#e9efff">${escapeHtml(it.npc_name)}</div>
          <div style="color:#9fb1c9; font-size:12px; margin:4px 0">${it.core_preview||''}</div>
          <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:12px; color:#cfd9ea;">
            ${it.voiceid?('<span>Voice: '+it.voiceid+'</span>'):''}
            ${it.gender?('<span>Gender: '+it.gender+'</span>'):''}
            ${it.race?('<span>Race: '+it.race+'</span>'):''}
            ${it.refid?('<span>RefID: '+it.refid+'</span>'):''}
            <span>Extended: ${String(it.extended_filled||0)}</span>
          </div>`;
        div.addEventListener('click', ()=> loadDetail(it.npc_name));
        list.appendChild(div);
      });
      // pager
      const pages = Math.max(1, Math.ceil(total / Math.max(1,pageSize)));
      pager.innerHTML='';
      const mk = (lab, p, dis)=>{ const b=document.createElement('button'); b.textContent=lab; b.disabled=!!dis; b.addEventListener('click', ()=>{ page=p; fetchList(); }); return b; };
      pager.appendChild(mk('«', 1, page<=1));
      pager.appendChild(mk('‹', Math.max(1,page-1), page<=1));
      const start = Math.max(1, page-2), end = Math.min(pages, page+2);
      for(let i=start;i<=end;i++){ const b=mk(String(i), i, i===page); pager.appendChild(b); }
      pager.appendChild(mk('›', Math.min(pages,page+1), page>=pages));
      pager.appendChild(mk('»', pages, page>=pages));
    }
    async function loadDetail(name){
      currentName = name;
      const params = new URLSearchParams({ bio_detail:'1', name:name });
      const res = await fetch('npc_master.php?'+params.toString()); let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (!j.ok){ detail.innerHTML = '<div style="color:#ff6b6b">Failed to load detail</div>'; return; }
      const d = j.data||{};
      detail.innerHTML = `
        <div style="font-size:18px; font-weight:700; color:#e9efff;">${escapeHtml(d.npc_name||'')}</div>
        <div style="margin-top:8px; color:#cfd9ea;"><b style="color:rgb(242,124,17)">Core:</b><br>${escapeHtml(d.core||'')}</div>
        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:10px; margin-top:8px;">
          ${kv('Backstory', d.npc_static_bio)}
          ${kv('Personality', d.personality)}
          ${kv('Appearance', d.appearance)}
          ${kv('Relationships', d.relationships)}
          ${kv('Occupation', d.occupation)}
          ${kv('Skills', d.skills)}
          ${kv('Speech Style', d.speechstyle)}
          ${kv('Goals', d.goals)}
        </div>
        <div style="margin-top:8px; color:#cfd9ea; display:flex; gap:10px; flex-wrap:wrap;">
          ${badge('VoiceID', d.voiceid)}
          ${badge('Gender', d.gender)}
          ${badge('Race', d.race)}
          ${badge('RefID', d.refid)}
        </div>
        <div style="margin-top:8px; color:#cfd9ea;"><b style="color:rgb(242,124,17)">Oghma Tags:</b> ${escapeHtml(d.oghma_knowledge_tags||'—')}</div>
      `;
    }
    function kv(title, val){ const v=(val||'').trim(); return `<div><div style="color:rgb(242,124,17); font-weight:700;">${title}</div><div style="white-space:pre-wrap;">${escapeHtml(v||'—')}</div></div>`; }
    function badge(k, v){ v=(v||'').trim(); if (!v) return ''; return `<span style="background:#3a3a3a; border:1px solid #4a4a4a; border-radius:999px; padding:3px 8px;">${k}: ${escapeHtml(v)}</span>`; }
    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    let deb = null; function refetch(){ if (deb) clearTimeout(deb); deb=setTimeout(()=>{ page=1; fetchList(); }, 250); }
    if (inp) inp.addEventListener('input', refetch);
    if (letter) letter.addEventListener('change', refetch);
    if (useBtn) useBtn.addEventListener('click', async ()=>{
      if (!currentName) return;
      // Load detail and fill manual form in iframe
      const params = new URLSearchParams({ bio_detail:'1', name:currentName });
      const res = await fetch('npc_master.php?'+params.toString()); let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (!j.ok) return;
      const d = j.data||{};
      const doc = iframe && iframe.contentDocument; if (!doc) return;
      function setVal(id, val){ const el = doc.getElementById(id); if (el) el.value = val==null?'':String(val); }
      function setChk(id, on){ const el = doc.getElementById(id); if (el && el.type==='checkbox') el.checked = !!on; }
      function applyRelationshipSeed(val){
        if (val == null) return;
        const raw = String(val).trim();
        if (!raw || raw.charAt(0) !== '{') return;
        let parsed = null;
        try { parsed = JSON.parse(raw); } catch(_e) { parsed = null; }
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return;
        const ext = doc.getElementById('extended_data');
        if (!ext) return;
        let obj = {};
        try { obj = JSON.parse(String(ext.value||'')||'{}')||{}; } catch(_e) { obj = {}; }
        obj.relationships = parsed;
        ext.value = JSON.stringify(obj);
      }
      setVal('npc_name', d.npc_name||'');
      if (incCore && incCore.checked) setVal('core', d.core||'');
      if (incExt && incExt.checked) {
        setVal('npc_static_bio', d.npc_static_bio||''); setVal('personality', d.personality||''); setVal('appearance', d.appearance||''); setVal('occupation', d.occupation||''); setVal('skills', d.skills||''); setVal('speechstyle', d.speechstyle||''); setVal('goals', d.goals||'');
        applyRelationshipSeed(d.relationships||'');
      }
      if (incOgh && incOgh.checked) setVal('oghma_knowledge_tags', d.oghma_knowledge_tags||'');
      if (incVM && incVM.checked) { setVal('voiceid', d.voiceid||''); setVal('gender', d.gender||''); setVal('race', d.race||''); setVal('refid', d.refid||''); }
      if (selProfile && selProfile.value) { const el = doc.getElementById('profile_id'); if (el) el.value = selProfile.value; }
      // Switch to manual tab
      document.querySelectorAll('#npc_modal_tabs .pf-tab').forEach(t=> t.classList.toggle('active', t.getAttribute('data-pane')==='pane_manual'));
      document.getElementById('pane_manual').style.display='block'; document.getElementById('pane_manual').classList.add('active');
      document.getElementById('pane_bio').style.display='none'; document.getElementById('pane_bio').classList.remove('active');
      // Update save button state after auto-filling name
      try { if (typeof window.NPC_UPDATE_SAVE_STATE === 'function') window.NPC_UPDATE_SAVE_STATE(); } catch(_e){}
    });
    if (createBtn) createBtn.addEventListener('click', async ()=>{
      if (!currentName) return;
      const fd = new FormData();
      fd.append('import_from_bio','1');
      fd.append('name', currentName);
      fd.append('include_core', (incCore && incCore.checked) ? '1':'');
      fd.append('include_extended', (incExt && incExt.checked) ? '1':'');
      fd.append('include_oghma', (incOgh && incOgh.checked) ? '1':'');
      fd.append('include_voice_meta', (incVM && incVM.checked) ? '1':'');
      if (selProfile && selProfile.value) fd.append('profile_id', selProfile.value);
      const res = await fetch('npc_master.php', { method:'POST', body: fd });
      let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (j && j.ok){
        window.postMessage({ type:'npc_saved', id: j.id, data: j.data }, '*');
      } else {
        alert('Import failed: '+(j && j.error ? j.error : 'Unknown'));
      }
    });
    // initial fetch
    try { fetchList(); } catch(_){}
  })();
  // Prevent browser history back/forward inside modal (mouse buttons/backspace)
  (function(){
    function blockNav(ev){ ev.preventDefault(); ev.stopPropagation(); return false; }
    window.addEventListener('popstate', blockNav, true);
    window.addEventListener('hashchange', blockNav, true);
    window.addEventListener('mousedown', function(e){ if (e.button===3 || e.button===4) { blockNav(e); } }, true);
    window.addEventListener('mouseup', function(e){ if (e.button===3 || e.button===4) { blockNav(e); } }, true);
    window.addEventListener('contextmenu', function(e){ /* noop */ }, true);
    // push a dummy state so back goes to same place
    try { history.pushState({modal:true}, document.title, location.href); } catch(_e){}
  })();
  document.querySelectorAll('.npc-card').forEach(card=>{
    card.addEventListener('click', function(ev){
      if (ev.target.closest('.npc-title-actions')) return;
      const id=this.getAttribute('data-id'); if (!id) return;
      ev.preventDefault();
      openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1');
    });
  });
  const createBtn = document.getElementById('npc_create_btn');
  if (createBtn){
    createBtn.addEventListener('click', function(){
      openModal('npc_master.php?partial=1');
    });
  }
  // Gallery picker wiring
  (function(){
    const picker = document.getElementById('gallery_picker');
    const pickerIframe = document.getElementById('gallery_picker_iframe');
    const pickerClose = document.getElementById('gallery_picker_close');
    function openPickerFor(id){
      window.CURRENT_NPC_ID = String(id||'');
      if (!window.CURRENT_NPC_ID) return;
      pickerIframe.src = '<?php echo $webRoot; ?>/ui/soulgaze_gallery.php?embed=1&picker=1';
      picker.style.display = 'flex';
    }
    function closePicker(){ picker.style.display = 'none'; try { pickerIframe.src='about:blank'; } catch(_){} }
    if (pickerClose) pickerClose.addEventListener('click', function(e){ e.preventDefault(); closePicker(); });
    picker.addEventListener('click', function(e){ if (e.target===picker) closePicker(); });
    window.addEventListener('keydown', function(e){ if (picker.style.display==='flex' && e.key==='Escape') closePicker(); });
    // Receive selection from gallery
    window.addEventListener('message', async function(e){
      const d = e.data || {};
      if (d.type === 'gallery_selected'){
        const url = String(d.url||'');
        if (!url || !window.CURRENT_NPC_ID) { closePicker(); return; }
        try {
          const fd = new FormData(); fd.append('set_portrait','1'); fd.append('id', String(window.CURRENT_NPC_ID)); fd.append('source', url);
          const res = await fetch('npc_master.php', { method:'POST', body: fd });
          let j={}; try { j = await res.json(); } catch(_e){}
          if (j && j.ok) {
            closePicker();
            try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Portrait updated'; toast.classList.add('show'); } } catch(_e){}
            window.location.reload();
            return;
          }
          // Fallback: refresh only the card image
          try {
            const u = 'npc_master.php?race_icon=1&id=' + encodeURIComponent(String(window.CURRENT_NPC_ID));
            const rr = await fetch(u); let rj={}; try { rj = await rr.json(); } catch(_e){}
            const card = document.getElementById('npc_card_'+String(window.CURRENT_NPC_ID));
            if (card){ const right = card.querySelector('.npc-right'); if (right){ right.innerHTML=''; if (rj && rj.url){ right.innerHTML = '<img class=\"npc-race-art\" alt=\"Race icon\" src=\"'+rj.url+'\" />'; } } }
          } catch(_e){}
        } catch(_e){}
        closePicker();
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Portrait updated'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
    // Bind card-level pick buttons (initial render)
    document.querySelectorAll('[data-pick-picture-id]').forEach(btn=>{
      btn.addEventListener('click', function(e){ e.preventDefault(); const id = this.getAttribute('data-pick-picture-id'); if (!id) return; openPickerFor(id); });
    });
    // Expose for rebind after list refresh
    window.OPEN_GALLERY_PICKER_FOR = openPickerFor;
  })();
  // Live search and alpha sort
  const searchInput = document.getElementById('npc_search');
  // Bulk delete wiring
  (function(){
    function bindBulk(btn){
      if (!btn) return;
      btn.addEventListener('click', function(){
        const box = document.createElement('div');
        box.style.position='fixed'; box.style.inset='0'; box.style.zIndex='10050'; box.style.display='flex'; box.style.alignItems='center'; box.style.justifyContent='center'; box.style.background='rgba(0,0,0,0.65)';
        box.innerHTML = '<div style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:16px; max-width:520px; width:92%; color:#e9efff;">\
          <div style="font-weight:700; color:#ff6b6b; margin-bottom:8px;">Danger: Delete ALL unlocked NPCs</div>\
          <div style="font-size:13px; color:#cfd9ea; margin-bottom:8px;">This will permanently delete every NPC that is not locked. The Narrator and any locked profiles will be preserved.</div>\
          <label style="display:block; font-size:13px; margin:6px 0; color:#cfd9ea;">Type <b style="color:#ffd166">Delete</b> to confirm:</label>\
          <input id="bulk_del_confirm" type="text" style="width:100%; padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;"/>\
          <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">\
            <button id="bulk_del_cancel" class="btn-cancel">Cancel</button>\
            <button id="bulk_del_ok" class="btn-danger" disabled>Delete</button>\
          </div></div>';
        document.body.appendChild(box);
        const inp = box.querySelector('#bulk_del_confirm');
        const ok  = box.querySelector('#bulk_del_ok');
        const cancel = box.querySelector('#bulk_del_cancel');
        function upd(){ ok.disabled = (String(inp.value||'').trim() !== 'Delete'); }
        inp.addEventListener('input', upd); upd(); inp.focus();
        cancel.addEventListener('click', function(){ document.body.removeChild(box); });
        ok.addEventListener('click', async function(){
          ok.disabled = true; try {
            const fd = new FormData(); fd.append('bulk_delete_npcs','1'); fd.append('confirm', String(inp.value||''));
            const res = await fetch('npc_master.php', { method:'POST', body: fd });
            let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
            document.body.removeChild(box);
            if (j && j.ok){
              try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Deleted '+String(j.deleted||0)+' NPCs'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 2000); } } catch(_){}
              refreshList(1);
            } else {
              alert('Bulk delete failed: '+(j && j.error ? j.error : 'Unknown'));
            }
          } catch(_e){ ok.disabled=false; }
        });
      });
    }
    // expose for rebind after AJAX refresh
    window.bindNpcBulkDelete = bindBulk;
    bindBulk(document.getElementById('npc_bulk_delete_btn'));
  })();
  // Bulk unlock wiring
  (function(){
    function bindBulkUnlock(btn){
      if (!btn) return;
      btn.addEventListener('click', function(){
        const box = document.createElement('div');
        box.style.position='fixed'; box.style.inset='0'; box.style.zIndex='10050'; box.style.display='flex'; box.style.alignItems='center'; box.style.justifyContent='center'; box.style.background='rgba(0,0,0,0.65)';
        box.innerHTML = '<div style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:16px; max-width:520px; width:92%; color:#e9efff;">\
          <div style="font-weight:700; color:rgb(242,124,17); margin-bottom:8px;">Unlock All NPC Profiles</div>\
          <div style="font-size:13px; color:#cfd9ea; margin-bottom:8px;">This unlocks every NPC profile except The Narrator.</div>\
          <div style="font-size:12px; color:#ffd166; margin-bottom:12px;">If Auto-lock Profile is enabled, a profile will lock again after it is edited and saved.</div>\
          <label style="display:block; font-size:13px; margin:6px 0; color:#cfd9ea;">Type <b style="color:#ffd166">Unlock</b> to confirm:</label>\
          <input id="bulk_unlock_confirm" type="text" style="width:100%; padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;"/>\
          <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">\
            <button id="bulk_unlock_cancel" class="btn-cancel">Cancel</button>\
            <button id="bulk_unlock_ok" class="btn-rel-build" disabled>Unlock All Profiles</button>\
          </div></div>';
        document.body.appendChild(box);
        const confirmEl = box.querySelector('#bulk_unlock_confirm');
        const okEl = box.querySelector('#bulk_unlock_ok');
        const cancelEl = box.querySelector('#bulk_unlock_cancel');
        function updateState(){ okEl.disabled = String(confirmEl.value || '').trim() !== 'Unlock'; }
        confirmEl.addEventListener('input', updateState);
        updateState();
        confirmEl.focus();
        cancelEl.addEventListener('click', function(){ document.body.removeChild(box); });
        okEl.addEventListener('click', async function(){
          okEl.disabled = true;
          try {
            const fd = new FormData();
            fd.append('bulk_unlock_npcs', '1');
            fd.append('confirm', String(confirmEl.value || ''));
            const res = await fetch('npc_master.php', { method:'POST', body: fd });
            let j = {};
            try { j = await res.json(); } catch(_){ j = { ok:false, error:'Invalid JSON response' }; }
            document.body.removeChild(box);
            if (j && j.ok) {
              try {
                const toast = document.getElementById('toast');
                if (toast) {
                  toast.querySelector('.message').textContent = 'Unlocked ' + String(j.unlocked || 0) + ' NPC profiles';
                  toast.classList.add('show');
                  setTimeout(() => toast.classList.remove('show'), 2400);
                }
              } catch(_){}
              refreshList(1);
            } else {
              alert('Bulk unlock failed: ' + (j && j.error ? j.error : 'Unknown'));
            }
          } catch(_e) {
            okEl.disabled = false;
          }
        });
      });
    }
    window.bindNpcBulkUnlock = bindBulkUnlock;
    bindBulkUnlock(document.getElementById('npc_bulk_unlock_btn'));
  })();
  // Bulk profile switch wiring
  (function(){
    const profileOptions = Array.isArray(PROFILE_OPTIONS) ? PROFILE_OPTIONS : [];
    function escHtml(v){
      return String(v ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[c]));
    }
    function buildOptions(selectedValue){
      const selected = String(selectedValue || '');
      let html = '';
      profileOptions.forEach(function(pr){
        const id = String(pr && pr.id ? pr.id : '');
        if (!id) return;
        const label = String(pr && pr.label ? pr.label : ('Profile #' + id));
        const sel = (id === selected) ? ' selected' : '';
        html += '<option value="' + escHtml(id) + '"' + sel + '>' + escHtml(label) + '</option>';
      });
      return html;
    }
    function bindBulkSwitch(btn){
      if (!btn) return;
      btn.addEventListener('click', function(){
        if (!profileOptions.length) { alert('No profiles found.'); return; }
        const filterSel = document.getElementById('npc_profile_filter');
        const sourcePref = (filterSel && filterSel.value) ? String(filterSel.value) : String((profileOptions[0] && profileOptions[0].id) || '');
        let targetPref = '';
        for (let i = 0; i < profileOptions.length; i++) {
          const pid = String(profileOptions[i] && profileOptions[i].id ? profileOptions[i].id : '');
          if (pid !== '' && pid !== sourcePref) { targetPref = pid; break; }
        }
        if (!targetPref) targetPref = sourcePref;

        const box = document.createElement('div');
        box.style.position='fixed'; box.style.inset='0'; box.style.zIndex='10050'; box.style.display='flex'; box.style.alignItems='center'; box.style.justifyContent='center'; box.style.background='rgba(0,0,0,0.65)';
        box.innerHTML = '<div style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:16px; max-width:560px; width:92%; color:#e9efff;">\
          <div style="font-weight:700; color:rgb(242,124,17); margin-bottom:8px;">Mass Switch NPC Profiles</div>\
          <div style="font-size:13px; color:#cfd9ea; margin-bottom:12px;">Move every NPC currently on one profile to another profile in one pass.</div>\
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:8px;">\
            <label style="display:flex; flex-direction:column; gap:6px; font-size:12px; color:#cfd9ea;">From profile\
              <select id="bulk_switch_source" style="padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;">' + buildOptions(sourcePref) + '</select>\
            </label>\
            <label style="display:flex; flex-direction:column; gap:6px; font-size:12px; color:#cfd9ea;">To profile\
              <select id="bulk_switch_target" style="padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;">' + buildOptions(targetPref) + '</select>\
            </label>\
          </div>\
          <label style="display:flex; align-items:center; gap:8px; margin:8px 0 12px 0; color:#cfd9ea; font-size:13px;"><input id="bulk_switch_include_locked" type="checkbox" /> Include locked NPCs</label>\
          <label style="display:block; font-size:13px; margin:6px 0; color:#cfd9ea;">Type <b style="color:#ffd166">Switch</b> to confirm:</label>\
          <input id="bulk_switch_confirm" type="text" style="width:100%; padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;"/>\
          <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">\
            <button id="bulk_switch_cancel" class="btn-cancel">Cancel</button>\
            <button id="bulk_switch_ok" class="btn-rel-build" disabled>Switch Profiles</button>\
          </div></div>';
        document.body.appendChild(box);

        const sourceEl = box.querySelector('#bulk_switch_source');
        const targetEl = box.querySelector('#bulk_switch_target');
        const includeLockedEl = box.querySelector('#bulk_switch_include_locked');
        const confirmEl = box.querySelector('#bulk_switch_confirm');
        const okEl = box.querySelector('#bulk_switch_ok');
        const cancelEl = box.querySelector('#bulk_switch_cancel');

        function updateState(){
          const confirmOk = String(confirmEl.value || '').trim() === 'Switch';
          const hasSource = !!(sourceEl && sourceEl.value);
          const hasTarget = !!(targetEl && targetEl.value);
          const different = hasSource && hasTarget && String(sourceEl.value) !== String(targetEl.value);
          okEl.disabled = !(confirmOk && hasSource && hasTarget && different);
        }
        confirmEl.addEventListener('input', updateState);
        sourceEl.addEventListener('change', updateState);
        targetEl.addEventListener('change', updateState);
        updateState();
        confirmEl.focus();

        cancelEl.addEventListener('click', function(){ document.body.removeChild(box); });
        okEl.addEventListener('click', async function(){
          okEl.disabled = true;
          try {
            const fd = new FormData();
            fd.append('bulk_switch_profile', '1');
            fd.append('source_profile_id', String(sourceEl.value || ''));
            fd.append('target_profile_id', String(targetEl.value || ''));
            fd.append('include_locked', includeLockedEl && includeLockedEl.checked ? '1' : '0');
            fd.append('confirm', String(confirmEl.value || ''));
            const res = await fetch('npc_master.php', { method:'POST', body: fd });
            let j = {};
            try { j = await res.json(); } catch(_){ j = { ok:false, error:'Invalid JSON response' }; }
            document.body.removeChild(box);
            if (j && j.ok){
              let msg = 'Switched ' + String(j.updated || 0) + ' NPCs';
              if (j.source_profile_label && j.target_profile_label) {
                msg += ' (' + String(j.source_profile_label) + ' → ' + String(j.target_profile_label) + ')';
              }
              if (!j.include_locked && Number(j.skipped_locked || 0) > 0) {
                msg += '; skipped ' + String(j.skipped_locked) + ' locked';
              }
              try {
                const toast = document.getElementById('toast');
                if (toast) {
                  toast.querySelector('.message').textContent = msg;
                  toast.classList.add('show');
                  setTimeout(() => toast.classList.remove('show'), 2400);
                }
              } catch(_){}
              refreshList(1);
            } else {
              alert('Mass switch failed: ' + (j && j.error ? j.error : 'Unknown'));
            }
          } catch(_e){
            okEl.disabled = false;
          }
        });
      });
    }
    window.bindNpcBulkSwitchProfile = bindBulkSwitch;
    bindBulkSwitch(document.getElementById('npc_bulk_switch_profile_btn'));
  })();
  let listAbort = null;
  let listRequestId = 0;
  // Explicit list state for the NPC Profiles page. The current page is seeded from the
  // server-rendered pagination so a clamped page survives reloads and save-adjacent refreshes.
  const LIST_STATE_KEYS = ['q','letter','profile_id','fav','dyn','mtm','lock','sal','blc','gps','created','alpha','embed'];
  const LIST_CHECKBOX_FILTERS = [['fav','npc_filter_fav'],['dyn','npc_filter_dyn'],['mtm','npc_filter_mtm'],['lock','npc_filter_lock'],['sal','npc_filter_sal'],['blc','npc_filter_blc'],['gps','npc_filter_gps'],['created','npc_filter_created']];
  function readServedPage(root){
    const pag = (root && root.matches && root.matches('.pagination')) ? root : (root || document).querySelector('.pagination[data-current-page]');
    const n = pag ? parseInt(pag.getAttribute('data-current-page') || '', 10) : NaN;
    return (Number.isFinite(n) && n > 0) ? n : null;
  }
  let currentListPage = readServedPage(document) || 1;
  function readListControl(id, key, current){
    const el = document.getElementById(id);
    if (el) return String(el.value || '');
    return String(current.get(key) || '');
  }
  function readListCheckbox(baseId, key, current){
    const el = document.getElementById(baseId + '_top') || document.getElementById(baseId);
    if (el) return el.checked ? '1' : '';
    return current.get(key) === '1' ? '1' : '';
  }
  // Effective list state: only the parameters that describe the list, never modal/transient ones.
  function buildListState(page){
    const current = new URLSearchParams(window.location.search);
    const params = new URLSearchParams();
    params.set('q', readListControl('npc_search', 'q', current));
    params.set('letter', readListControl('npc_letter_filter', 'letter', current).toUpperCase());
    params.set('profile_id', readListControl('npc_profile_filter', 'profile_id', current));
    LIST_CHECKBOX_FILTERS.forEach(function(pair){ params.set(pair[0], readListCheckbox(pair[1], pair[0], current)); });
    params.set('alpha', 'asc');
    if (current.get('embed') === '1') params.set('embed', '1');
    const n = parseInt(page, 10);
    params.set('page', String(Number.isFinite(n) && n > 0 ? n : 1));
    return params;
  }
  // Persist the server-confirmed list state in the visible URL so any reload rebuilds the same page.
  function persistListState(params, servedPage){
    const visible = new URLSearchParams();
    LIST_STATE_KEYS.forEach(function(key){
      const val = params.get(key);
      if (val !== null && val !== '') visible.set(key, val);
    });
    const n = parseInt(servedPage, 10);
    visible.set('page', String(Number.isFinite(n) && n > 0 ? n : 1));
    const url = window.location.pathname + '?' + visible.toString() + window.location.hash;
    try { history.replaceState(history.state, document.title, url); } catch(_e){}
  }
  // A requested page can outlive the data (last page shrinks); trust the page the server rendered.
  (function(){
    const current = new URLSearchParams(window.location.search);
    if (!current.has('page')) return;
    if (parseInt(current.get('page') || '', 10) === currentListPage) return;
    persistListState(buildListState(currentListPage), currentListPage);
  })();
  function bindNpcLetterButtons(root){
    const scope = root || document;
    scope.querySelectorAll('.npc-letter-btn[data-letter]').forEach(btn=>{
      btn.addEventListener('click', function(e){
        e.preventDefault();
        const nextLetter = String(this.getAttribute('data-letter') || '').toUpperCase();
        const hidden = document.getElementById('npc_letter_filter');
        if (hidden) hidden.value = nextLetter;
        document.querySelectorAll('.npc-letter-btn[data-letter]').forEach(other=>{
          other.classList.toggle('active', other === this);
        });
        refreshList(1);
      });
    });
  }
  function bindNpcPageButtons(root){
    const scope = root || document;
    scope.querySelectorAll('.npc-page-link[data-page]').forEach(btn=>{
      if (btn.disabled) return;
      btn.addEventListener('click', function(e){
        e.preventDefault();
        const nextPage = parseInt(this.getAttribute('data-page') || '1', 10);
        refreshList(Number.isFinite(nextPage) ? nextPage : 1);
      });
    });
  }
  function bindAutoLockProfile(control){
    if (!control || control.dataset.bound === '1') return;
    control.dataset.bound = '1';
    control.addEventListener('change', async function(){
      const requested = !!control.checked;
      control.disabled = true;
      try {
        const fd = new FormData();
        fd.append('set_auto_lock_profile', '1');
        fd.append('auto_lock_profile', requested ? '1' : '0');
        const res = await fetch('npc_master.php', { method:'POST', body:fd });
        let json = {};
        try { json = await res.json(); } catch(_e) { json = { ok:false, error:'Invalid response' }; }
        if (!json || !json.ok) {
          control.checked = !requested;
          alert('Failed to save Auto Lock Profile: ' + (json && json.error ? json.error : 'Unknown error'));
        }
      } catch(_e) {
        control.checked = !requested;
        alert('Failed to save Auto Lock Profile.');
      } finally {
        control.disabled = false;
      }
    });
  }
  bindAutoLockProfile(document.getElementById('npc_auto_lock_profile'));

  // Omitting page keeps the current list page; filter changes pass 1 explicitly.
  async function refreshList(page){
    const si = document.getElementById('npc_search');
    const wasFocused = document.activeElement && document.activeElement.id === 'npc_search';
    const caretStart = wasFocused && si && typeof si.selectionStart === 'number' ? si.selectionStart : null;
    const caretEnd = wasFocused && si && typeof si.selectionEnd === 'number' ? si.selectionEnd : null;
    const askedPage = parseInt(page, 10);
    const requestedPage = (Number.isFinite(askedPage) && askedPage > 0) ? askedPage : currentListPage;
    const params = buildListState(requestedPage);
    const requestParams = new URLSearchParams(params.toString());
    requestParams.set('list','1');
    if (listAbort) { try { listAbort.abort(); } catch(_){} }
    const requestId = ++listRequestId;
    listAbort = new AbortController();
    try {
      const res = await fetch('npc_master.php?'+requestParams.toString(), { signal: listAbort.signal });
      if (!res.ok) throw new Error('HTTP ' + String(res.status));
      const html = await res.text();
      if (requestId !== listRequestId) return;
      const temp = document.createElement('div'); temp.innerHTML = html;
      const newPag = temp.querySelector('.pagination');
      const newGrid = temp.querySelector('.npc-grid');
      if (!newPag || !newGrid) throw new Error('Incomplete NPC list response');
      const oldPag = document.querySelector('.pagination');
      const oldGrid = document.querySelector('.npc-grid');
      if (oldPag && oldPag.parentElement) oldPag.parentElement.replaceChild(newPag, oldPag);
      if (oldGrid && oldGrid.parentElement) oldGrid.parentElement.replaceChild(newGrid, oldGrid);
      // Trust the page the server actually rendered: it may be clamped when the last page shrinks.
      currentListPage = readServedPage(newPag) || requestedPage;
      persistListState(params, currentListPage);
      // rebind events on new elements
      document.querySelectorAll('.npc-card').forEach(card=>{
        card.addEventListener('click', function(ev){
          if (ev.target.closest('.npc-title-actions')) return;
          const id=this.getAttribute('data-id'); if (!id) return;
          ev.preventDefault();
          openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1');
        });
      });
      // Rebind filter dropdowns in refreshed DOM
      (function(){
        function bindDropdown(btnId, menuId){
          const btn = document.getElementById(btnId);
          const menu = document.getElementById(menuId);
          if (!btn || !menu) return;
          btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); menu.style.display = (menu.style.display==='none'||menu.style.display==='') ? 'block' : 'none'; });
          document.addEventListener('click', function(){ if (menu.style.display==='block') menu.style.display='none'; });
          menu.addEventListener('click', function(e){ e.stopPropagation(); });
          menu.querySelectorAll('input[type="checkbox"]').forEach(cb=> cb.addEventListener('change', function(){ refreshList(1); }));
        }
        bindDropdown('npc_filter_btn_top','npc_filter_menu_top');
        bindDropdown('npc_filter_btn','npc_filter_menu');
      })();
      document.querySelectorAll('[data-favorite-id]').forEach(btn=>{
        btn.addEventListener('click', async function(e){
          e.preventDefault(); const id = this.getAttribute('data-favorite-id');
          const fd = new FormData(); fd.append('toggle_favorite','1'); fd.append('id', id);
          const res = await fetch('npc_master.php', { method:'POST', body: fd }); let json={}; try{ json=await res.json(); }catch(_e){}
          if (json && json.ok){ const active = Number(json.favorite||0)===1; this.classList.toggle('active', active); this.textContent = active ? '★' : '☆'; }
        });
      });
      document.querySelectorAll('[data-lock-id]').forEach(btn=>{
        btn.addEventListener('click', async function(e){
          e.preventDefault(); const id = this.getAttribute('data-lock-id');
          const fd = new FormData(); fd.append('toggle_lock','1'); fd.append('id', id);
          const res = await fetch('npc_master.php', { method:'POST', body: fd }); let json={}; try{ json=await res.json(); }catch(_e){}
          if (json && json.ok){ const active = Number(json.locked||0)===1; this.classList.toggle('active', active); this.textContent = active ? '🔒' : '🔓'; }
        });
      });
      const newCreate = document.getElementById('npc_create_btn');
      if (newCreate){ newCreate.addEventListener('click', function(){ openModal('npc_master.php?partial=1'); }); }
      try { if (window.bindNpcImportButton) window.bindNpcImportButton(document.getElementById('npc_import_btn')); } catch(_){}
      // rebind bulk delete in refreshed DOM
      try { if (window.bindNpcBulkDelete) window.bindNpcBulkDelete(document.getElementById('npc_bulk_delete_btn')); } catch(_){}
      // rebind bulk unlock in refreshed DOM
      try { if (window.bindNpcBulkUnlock) window.bindNpcBulkUnlock(document.getElementById('npc_bulk_unlock_btn')); } catch(_){}
      // rebind mass switch in refreshed DOM
      try { if (window.bindNpcBulkSwitchProfile) window.bindNpcBulkSwitchProfile(document.getElementById('npc_bulk_switch_profile_btn')); } catch(_){}
      // rebind Build Relationships button in refreshed DOM
      try { if (window.bindRelBuildButton) window.bindRelBuildButton(document.getElementById('rel_bulk_build_btn')); } catch(_){}
      // Hook pagination links to AJAX
      // Bind pick-picture buttons after refresh
      document.querySelectorAll('[data-pick-picture-id]').forEach(btn=>{
        btn.addEventListener('click', function(e){ e.preventDefault(); const id = this.getAttribute('data-pick-picture-id'); if (!id) return; if (typeof window.OPEN_GALLERY_PICKER_FOR === 'function') window.OPEN_GALLERY_PICKER_FOR(id); });
      });
      bindNpcLetterButtons(newPag);
      bindNpcPageButtons(newPag);
      const newSearch = document.getElementById('npc_search');
      if (newSearch){
        // Rebind with debounce and restore focus/caret
        newSearch.addEventListener('input', function(){ refreshListDebounced(1); });
        if (wasFocused){
          try {
            newSearch.focus();
            if (caretStart!=null && caretEnd!=null) newSearch.setSelectionRange(caretStart, caretEnd);
          } catch(_e){}
        }
      }
      const newProfileSel = document.getElementById('npc_profile_filter');
      if (newProfileSel){ newProfileSel.addEventListener('change', function(){ refreshList(1); }); }
      bindAutoLockProfile(document.getElementById('npc_auto_lock_profile'));
    } catch(error) {
      if (error && error.name === 'AbortError') return;
      console.error('NPC profile list refresh failed:', error);
      try {
        const toast = document.getElementById('toast');
        if (toast) {
          toast.querySelector('.message').textContent = 'Unable to refresh NPC profiles. The current list was kept.';
          toast.classList.add('show');
          setTimeout(()=>toast.classList.remove('show'), 3000);
        }
      } catch(_e){}
    }
  }
  window.NPC_REFRESH_LIST = refreshList;
  // Simple debounce for input
  let debTimer = null;
  function refreshListDebounced(page){
    if (debTimer) clearTimeout(debTimer);
    debTimer = setTimeout(()=>refreshList(page), 500)
  }
  if (searchInput){ searchInput.addEventListener('input', function(){ refreshListDebounced(1); }); }
  const profileSel = document.getElementById('npc_profile_filter');
  if (profileSel){ profileSel.addEventListener('change', function(){ refreshList(1); }); }
  bindNpcLetterButtons(document);
  bindNpcPageButtons(document);
  // Removed alpha toggle; default remains ascending (favorites first)
  // Toggle buttons
  // Filter dropdown toggles
  (function(){
    function bindDropdown(btnId, menuId){
      const btn = document.getElementById(btnId);
      const menu = document.getElementById(menuId);
      if (!btn || !menu) return;
      btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); menu.style.display = (menu.style.display==='none'||menu.style.display==='') ? 'block' : 'none'; });
      document.addEventListener('click', function(){ if (menu.style.display==='block') menu.style.display='none'; });
      menu.addEventListener('click', function(e){ e.stopPropagation(); });
      // When any checkbox changes, refetch
      menu.querySelectorAll('input[type="checkbox"]').forEach(cb=> cb.addEventListener('change', function(){ refreshList(1); }));
    }
    bindDropdown('npc_filter_btn_top','npc_filter_menu_top');
    bindDropdown('npc_filter_btn','npc_filter_menu');
  })();
  document.querySelectorAll('[data-favorite-id]').forEach(btn=>{
    btn.addEventListener('click', async function(e){
      e.preventDefault();
      const id = this.getAttribute('data-favorite-id');
      const fd = new FormData(); fd.append('toggle_favorite','1'); fd.append('id', id);
      const res = await fetch('npc_master.php', { method:'POST', body: fd });
      let json={}; try{ json=await res.json(); }catch(_e){}
      if (json && json.ok){
        const active = Number(json.favorite||0)===1;
        this.classList.toggle('active', active);
        this.textContent = active ? '★' : '☆';
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent= active?'Marked favorite':'Unfavorited'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
  });
  document.querySelectorAll('[data-lock-id]').forEach(btn=>{
    btn.addEventListener('click', async function(e){
      e.preventDefault();
      const id = this.getAttribute('data-lock-id');
      const fd = new FormData(); fd.append('toggle_lock','1'); fd.append('id', id);
      const res = await fetch('npc_master.php', { method:'POST', body: fd });
      let json={}; try{ json=await res.json(); }catch(_e){}
      if (json && json.ok){
        const active = Number(json.locked||0)===1;
        this.classList.toggle('active', active);
        this.textContent = active ? '🔒' : '🔓';
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent= active?'Locked profile':'Unlocked profile'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
  });
  // Receive save events from iframe and update the card inline
  window.addEventListener('message', async function(e){
    const d = e.data || {};
    if (d.type === 'npc_saved'){
      const id = String(d.id||'');
      const data = d.data || {};
      let card = document.getElementById('npc_card_'+id);
      if (!card){
        // Create a new card at the start of the grid
        const grid = document.querySelector('.npc-grid');
        if (grid){
          const div = document.createElement('div');
          div.className = 'npc-card';
          div.id = 'npc_card_'+id;
          div.setAttribute('data-id', id);
          div.innerHTML = `
            <div class="npc-title">
              <div class="npc-title-left"><span class="npc-name"></span></div>
              <div class="npc-title-actions">
                <span class="npc-tags-top" style="display:none"></span>
                <a class="btn btn-toggle" href="#" data-favorite-id="${id}" title="Toggle favorite">☆</a>
                <button type="button" class="btn btn-toggle npc-merge-btn" data-merge-id="${id}" data-merge-linked="0" title="Merge profiles" aria-label="Merge profiles"><span aria-hidden="true">🔗</span></button>
                <a class="btn btn-toggle" href="#" data-lock-id="${id}" title="Toggle lock - Locked profiles are protected from history pullback when loading saves">🔓</a>
                <a class="btn btn-trash" href="?delete=${id}" title="Delete">❌</a>
              </div>
            </div>
            <div class="npc-divider"></div>
            <div class="npc-row">
              <div class="npc-fields">
                <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"></span></div>
                <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"></span></div>
                <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"></span></div>
                <div class="npc-line"><span class="npc-muted">RefID:</span> <span class="npc-refid"></span> <span class="npc-runtime-chip" style="display:none" title="FF RefIDs are assigned at runtime and can change between saves.">Runtime</span></div>
                <div class="npc-line npc-source-line"><span class="npc-muted">Source:</span> <span class="npc-source-single"></span></div>
                <div class="npc-line"><span class="npc-muted">Oghma Tags:</span> <span class="npc-oghma"></span></div>
                <div class="npc-line"><span class="npc-muted">Profile:</span> <span class="npc-profile"></span></div>
              </div>
              <div class="npc-right"></div>
            </div>
            `;
          grid.prepend(div);
          // Wire edit button
          div.addEventListener('click', function(ev){ if (ev.target.closest('.npc-title-actions')) return; ev.preventDefault(); openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1'); });
          card = div;
        }
      }
      if (card){
        const setText = (sel, val)=>{ const el = card.querySelector(sel); if (el) el.textContent = val==null?'':String(val); };
        setText('.npc-name', data.npc_name);
        setText('.npc-gender', data.gender);
        setText('.npc-race', data.race);
        setText('.npc-voiceid', data.voiceid);
        // Keep the new card's identity line consistent with the server-rendered ones.
        (function(){
          const raw = String(data.refid==null?'':data.refid).trim().replace(/^0x/i,'').toUpperCase();
          const known = /^[0-9A-F]{1,8}$/.test(raw);
          const text = known ? raw.padStart(8,'0') : 'No RefID';
          setText('.npc-refid', text);
          const refidEl = card.querySelector('.npc-refid');
          if (refidEl) refidEl.classList.toggle('npc-identity-unknown', !known);
          const runtime = card.querySelector('.npc-runtime-chip');
          if (runtime) runtime.style.display = (known && text.startsWith('FF')) ? 'inline-block' : 'none';
          let chain = [];
          try {
            const meta = JSON.parse(String(data.metadata||'{}')||'{}');
            let mods = meta && meta.mods;
            if (typeof mods === 'string') mods = mods.split(/[#,\r\n]+/);
            if (Array.isArray(mods)) chain = mods.map(m=>String(m==null?'':m).trim()).filter(Boolean);
          } catch(_e){}
          const source = card.querySelector('.npc-source-single');
          if (source){
            source.textContent = chain.length ? chain.join(' \u2192 ') : 'Unknown source';
            source.classList.toggle('npc-identity-unknown', chain.length === 0);
            if (chain.length) source.title = chain.join(' \u2192 '); else source.removeAttribute('title');
          }
          const deleteButton = card.querySelector('.btn-trash');
          if (deleteButton) {
            const deleteLines = [
              'Delete "' + String(data.npc_name || 'this NPC') + '"?',
              '',
              'RefID: ' + text
            ];
            if (chain.length) deleteLines.push('Defining mod: ' + chain[0]);
            deleteLines.push('', 'Only this profile is deleted. Other NPCs sharing this name are kept.');
            deleteButton.onclick = function(){ return confirm(deleteLines.join('\n')); };
          }
        })();
        setText('.npc-oghma', (data.oghma_knowledge_tags==null || String(data.oghma_knowledge_tags).trim()==='') ? 'none' : data.oghma_knowledge_tags);
        // Update title tags pill
        try {
          const top = card.querySelector('.npc-title-actions .npc-tags-top');
          const tval = (data.tags||'').trim();
          if (top){
            if (tval){ top.style.display='inline-block'; top.textContent = tval; top.title = 'Use Search to filter by these tags: ' + tval; }
            else { top.style.display='none'; top.textContent=''; top.removeAttribute('title'); }
          }
        } catch(_){}
        const profId = String(data.profile_id||'');
        setText('.npc-profile', PROFILES_BY_ID[profId] || '');
        // Toggle Middle-term memory icon (📃) based on extended_data.middle_term_enabled
        try {
          const mtm = (function(){
            const raw = String(data.extended_data||'').trim(); if (!raw) return 0;
            try { const o = JSON.parse(raw); return (o && Number(o.middle_term_enabled||0)===1) ? 1 : 0; } catch(_e){ return 0; }
          })();
          const left = card.querySelector('.npc-title-left');
          if (left){
            let icon = left.querySelector('.npc-mtm-icon');
            if (mtm){ if (!icon){ icon = document.createElement('span'); icon.className='npc-mtm-icon'; icon.title='Middle-term memory enabled'; icon.textContent='📃'; left.appendChild(icon); } }
            else { if (icon){ icon.remove(); } }
          }
        } catch(_e){}
        // Toggle Individual Memory Bank icon (🧠) based on extended_data.individual_memory_enabled
        try {
          const imb = (function(){
            const raw = String(data.extended_data||'').trim(); if (!raw) return 0;
            try { const o = JSON.parse(raw); return (o && Number(o.individual_memory_enabled||0)===1) ? 1 : 0; } catch(_e){ return 0; }
          })();
          const left = card.querySelector('.npc-title-left');
          if (left){
            let icon = left.querySelector('.npc-imb-icon');
            if (imb){ if (!icon){ icon = document.createElement('span'); icon.className='npc-imb-icon'; icon.title='Individual memory bank enabled'; icon.textContent='🧠'; left.appendChild(icon); } }
            else { if (icon){ icon.remove(); } }
          }
        } catch(_e){}
        // Toggle Auto Diary icon (📙) based on extended_data.auto_diary_enabled
        try {
          const ad = (function(){
            const raw = String(data.extended_data||'').trim(); if (!raw) return 0;
            try { const o = JSON.parse(raw); return (o && Number(o.auto_diary_enabled||0)===1) ? 1 : 0; } catch(_e){ return 0; }
          })();
          const left = card.querySelector('.npc-title-left');
          if (left){
            let icon = left.querySelector('.npc-ad-icon');
            if (ad){ if (!icon){ icon = document.createElement('span'); icon.className='npc-ad-icon'; icon.title='Auto diary enabled'; icon.textContent='📙'; left.appendChild(icon); } }
            else { if (icon){ icon.remove(); } }
          }
        } catch(_e){}
        // Toggle Auto Greeting icon (👋) based on extended_data.salutation_after_a_while
        try {
          const sal = (function(){
            const raw = String(data.extended_data||'').trim(); if (!raw) return 0;
            try { const o = JSON.parse(raw); return (o && Number(o.salutation_after_a_while||0)===1) ? 1 : 0; } catch(_e){ return 0; }
          })();
          const left = card.querySelector('.npc-title-left');
          if (left){
            let icon = left.querySelector('.npc-sal-icon');
            if (sal){ if (!icon){ icon = document.createElement('span'); icon.className='npc-sal-icon'; icon.title='Auto Greeting enabled'; icon.textContent='👋'; left.appendChild(icon); } }
            else { if (icon){ icon.remove(); } }
          }
        } catch(_e){}
        // Fetch and render race icon into the right container
        try {
          const race = (data.race||'');
          const res = await fetch('npc_master.php?race_icon=1&race='+encodeURIComponent(race)+"&id="+id);
          const j = await res.json();
          const right = card.querySelector('.npc-right');
          if (right){
            right.innerHTML = '';
            if (j && j.url){ right.innerHTML = '<img class="npc-race-art" alt="Race icon" src="'+j.url+'" />'; }
          }
        } catch(_e){}
      }
      closeModal();
      try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='NPC saved'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 2000); } } catch(_e){}
    }
  });
})();

// Build Relationships Modal functionality
(function(){
  async function loadModalStats(){
    // Load model info and NPC counts
    try {
      const res = await fetch('../api/relationship_batch_build.php?action=stats');
      const data = await res.json();
      if (data.ok){
        document.getElementById('rel_build_model').textContent = data.model || 'Not configured';
        document.getElementById('rel_count_built').textContent = data.built || 0;
        document.getElementById('rel_count_pending').textContent = data.pending || 0;
      }
    } catch(e){
      document.getElementById('rel_build_model').textContent = 'Error loading';
    }
  }

  function bindRelBuildButton(btn){
    if (!btn) return;
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const modal = document.getElementById('rel_build_modal');
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Reset state
        document.getElementById('rel_build_content').style.display = 'block';
        document.getElementById('rel_build_progress').style.display = 'none';
        document.getElementById('rel_build_log').innerHTML = '';
        document.getElementById('rel_build_bar').style.width = '0%';
        document.getElementById('rel_build_count').textContent = '0 / 0';
        document.getElementById('rel_build_status').textContent = 'Ready';
        const doneBtn = document.getElementById('rel_build_done');
        if (doneBtn) doneBtn.style.display = 'none';
        // Load stats
        loadModalStats();
      }
    });
  }

  // Bind both buttons (AJAX partial and main page)
  bindRelBuildButton(document.getElementById('rel_bulk_build_btn'));

  // Make it available for rebinding after AJAX refresh
  window.bindRelBuildButton = bindRelBuildButton;

  // Close button
  const closeBtn = document.getElementById('rel_build_close');
  if (closeBtn){
    closeBtn.addEventListener('click', function(){
      const modal = document.getElementById('rel_build_modal');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
      }
    });
  }

  // Start button
  const startBtn = document.getElementById('rel_build_start');
  if (startBtn){
    startBtn.addEventListener('click', async function(){
      const force = document.getElementById('rel_build_force').checked ? 1 : 0;
      const infer = document.getElementById('rel_build_infer').checked ? 1 : 0;

      // Show progress
      document.getElementById('rel_build_content').style.display = 'none';
      document.getElementById('rel_build_progress').style.display = 'block';

      const logEl = document.getElementById('rel_build_log');
      const barEl = document.getElementById('rel_build_bar');
      const countEl = document.getElementById('rel_build_count');
      const statusEl = document.getElementById('rel_build_status');

      function log(msg, type){
        const line = document.createElement('div');
        line.textContent = msg;
        if (type === 'error') line.style.color = '#ff6b6b';
        else if (type === 'success') line.style.color = '#69db7c';
        else if (type === 'info') line.style.color = 'rgb(242, 124, 17)';
        logEl.appendChild(line);
        logEl.scrollTop = logEl.scrollHeight;
      }

      try {
        log('Starting relationship build...', 'info');
        statusEl.textContent = 'Fetching NPC list...';

        // Fetch list of NPCs to process
        const listRes = await fetch('../api/relationship_batch_build.php?action=list&force=' + force);
        const listData = await listRes.json();

        if (!listData.ok){
          log('Error: ' + (listData.error || 'Failed to get NPC list'), 'error');
          statusEl.textContent = 'Failed';
          return;
        }

        const npcs = listData.npcs || [];
        const total = npcs.length;

        if (total === 0){
          log('No NPCs need processing.', 'info');
          statusEl.textContent = 'Complete';
          barEl.style.width = '100%';
          return;
        }

        log('Found ' + total + ' NPCs to process.', 'info');
        countEl.textContent = '0 / ' + total;

        let processed = 0;
        let success = 0;
        let failed = 0;

        // Process each NPC
        for (const npc of npcs){
          statusEl.textContent = 'Processing: ' + npc.name;

          try {
            const res = await fetch('../api/relationship_batch_build.php?action=process&id=' + npc.id + '&force=' + force);
            const data = await res.json();

            if (data.ok){
              success++;
              log('✓ ' + npc.name + ': ' + (data.count || 0) + ' relationships', 'success');
            } else {
              failed++;
              log('✗ ' + npc.name + ': ' + (data.error || 'Failed'), 'error');
            }
          } catch(e){
            failed++;
            log('✗ ' + npc.name + ': Network error', 'error');
          }

          processed++;
          countEl.textContent = processed + ' / ' + total;
          barEl.style.width = Math.round((processed / total) * 100) + '%';
        }

        // Run inference if requested
        if (infer && success > 0){
          statusEl.textContent = 'Running transitive inference...';
          log('Running transitive inference...', 'info');

          try {
            const infRes = await fetch('../api/relationship_batch_build.php?action=infer');
            const infData = await infRes.json();
            if (infData.ok){
              log('✓ Inference complete: ' + (infData.count || 0) + ' relationships updated', 'success');
            }
          } catch(e){
            log('Inference skipped due to error', 'error');
          }
        }

        statusEl.textContent = 'Complete';
        log('Done! ' + success + ' succeeded, ' + failed + ' failed.', 'info');

        // Show Done button
        const doneBtn = document.getElementById('rel_build_done');
        if (doneBtn) doneBtn.style.display = 'inline-block';

      } catch(e){
        log('Error: ' + e.message, 'error');
        statusEl.textContent = 'Failed';
        // Show Done button even on error
        const doneBtn = document.getElementById('rel_build_done');
        if (doneBtn) doneBtn.style.display = 'inline-block';
      }
    });
  }

  // Done button handler
  const doneBtn = document.getElementById('rel_build_done');
  if (doneBtn){
    doneBtn.addEventListener('click', function(){
      const modal = document.getElementById('rel_build_modal');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
      }
    });
  }
})();

// NPC Export/Import functionality
(function(){
  // Export button in modal header
  const exportBtn = document.getElementById('npc_modal_export');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(){
      const id = window.CURRENT_NPC_ID;
      if (!id) { alert('No NPC selected. Save the NPC first before exporting.'); return; }
      // Trigger download by navigating to export URL
      window.location.href = 'npc_master.php?export=' + id;
    });
  }
  
  // Import Bio to current NPC button in modal header (only for existing NPCs)
  const importToBtn = document.getElementById('npc_modal_import_to');
  if (importToBtn) {
    importToBtn.addEventListener('click', function(){
      const id = window.CURRENT_NPC_ID;
      if (!id) { alert('No NPC selected. Save the NPC first before importing.'); return; }
      
      // Create file input
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = '.json';
      input.style.display = 'none';
      document.body.appendChild(input);
      
      input.addEventListener('change', async function(){
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        const text = await file.text();
        
        try {
          const data = JSON.parse(text);
          if (!confirm('Import biography from "' + (data.npc_name || 'Unknown') + '" to this NPC?\n\nThis will overwrite the current NPC\'s biography fields (personality, appearance, skills, etc.) but keep the name.')) {
            return;
          }
          
          const formData = new FormData();
          formData.append('import_npc', '1');
          formData.append('import_data', text);
          formData.append('target_id', id);
          
          const res = await fetch('npc_master.php', { method: 'POST', body: formData });
          const result = await res.json();
          
          if (result.ok) {
            alert(result.message || 'Biography imported successfully');
            location.reload();
          } else {
            alert('Error: ' + (result.error || 'Import failed'));
          }
        } catch(e) {
          alert('Error parsing JSON file: ' + e.message);
        } finally {
          document.body.removeChild(input);
        }
      });
      
      input.click();
    });
  }
  
  // Import NPC button in toolbar (create new NPC from JSON)
  function bindNpcImportButton(btn){
    if (!btn) return;
    btn.addEventListener('click', function(){
      // Create file input
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = '.json';
      input.style.display = 'none';
      document.body.appendChild(input);
      
      input.addEventListener('change', async function(){
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        const text = await file.text();
        
        try {
          const data = JSON.parse(text);
          const originalName = data.npc_name || '';
          
          // Show dialog to confirm or change name
          const newName = prompt(
            'Import NPC from file.\n\n' +
            'Original name: ' + (originalName || '(none)') + '\n\n' +
            'Enter NPC name (leave as-is or change for renamed NPCs):',
            originalName
          );
          
          if (newName === null) {
            // User cancelled
            return;
          }
          
          if (!newName.trim()) {
            alert('NPC name is required');
            return;
          }
          
          const formData = new FormData();
          formData.append('import_npc', '1');
          formData.append('import_data', text);
          formData.append('new_name', newName.trim());
          
          const res = await fetch('npc_master.php', { method: 'POST', body: formData });
          const result = await res.json();
          
          if (result.ok) {
            alert(result.message || 'NPC imported successfully');
            location.reload();
          } else {
            alert('Error: ' + (result.error || 'Import failed'));
          }
        } catch(e) {
          alert('Error parsing JSON file: ' + e.message);
        } finally {
          document.body.removeChild(input);
        }
      });
      
      input.click();
    });
  }
  window.bindNpcImportButton = bindNpcImportButton;
  bindNpcImportButton(document.getElementById('npc_import_btn'));
})();

</script>

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 // Hide metadata editor in modal partial view
 if (!(isset($_GET['partial']) && $_GET['partial']=='1')) {
     include(__DIR__."/tmpl/metadata_json_editor.php");
 }
// Provides Datatables
 include(__DIR__."/tmpl/data_tables.php");
?>

    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <script src="<?php echo $webRoot; ?>/ui/js/npc_profile_merge.js" defer></script>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
