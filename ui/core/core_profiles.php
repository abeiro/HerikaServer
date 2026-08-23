<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."profile_llm_mode.php");

require_once "{$enginePath}/lib/core/core_profiles.class.php";
require_once "{$enginePath}/lib/core/llm_connector.class.php";
require_once "{$enginePath}/lib/core/tts_connector.class.php";
require_once "{$enginePath}/lib/core/itt_connector.class.php";
require_once "{$enginePath}/lib/core/api_badge.class.php";
require_once "{$enginePath}/lib/core/import_rules.class.php";
require_once "{$enginePath}/lib/core/prisma_settings_catalog.php";

//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from below file
include(__DIR__."/tmpl/ui_utils.php");

// Determine web root similar to oghma_upload
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Site chrome
require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$TITLE = "CHIM - Profiles";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/css/main.css'); ?>">
<style>
/* Match Oghma/Connectors spacing and title styling */
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main { padding-top: 10px; padding-bottom: 24px; }

/* Page header is the shared compact inline row (.chim-page-head in chim-theme.css). */
.wide-centered { max-width: 1300px; margin: 0 auto; }
.two-col-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.connector-card { 
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98)); 
    border: 1px solid #3a3a3a; 
    border-radius: 8px; 
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.connector-card:hover {
    border-color: #4a4a4a;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.05);
}
.connector-title { 
    font-family: 'MagicCards', serif; 
    color: #fff; 
    margin-bottom: 12px; 
    font-size: 1.2em; 
    letter-spacing: 0.6px; 
    word-spacing: 10px;
    font-weight: 600;
}
.connector-subtitle { color:#fff; font-size:12px; line-height:1.35; margin-top:-4px; margin-bottom:10px; }
@media (max-width: 1000px) { .two-col-grid { grid-template-columns: 1fr; } }
/* Split layout like LLM Connectors */
.llm-layout { display:grid; grid-template-columns: minmax(240px, 340px) 1fr; gap:16px; align-items:stretch; }
@media (max-width: 1100px) { .llm-layout { grid-template-columns: minmax(220px, 300px) 1fr; } }
@media (max-width: 860px) { .llm-layout { grid-template-columns: minmax(200px, 260px) 1fr; } }
.llm-left { 
    display:flex; 
    flex-direction:column; 
    height:800px; 
    overflow:hidden; 
    padding:12px; 
    padding-right:12px; 
    border:1px solid #3a3a3a; 
    border-radius:10px; 
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
}
.llm-right { min-width: 0; container-type:inline-size; }
.list-filters { display:flex; gap:8px; align-items:center; margin:6px 0 10px; flex-wrap:wrap; }
.list-filters input[type="text"]{ 
    width: 100%; 
    max-width: 260px;
    background: rgba(26, 26, 26, 0.8); 
    color: #e9efff; 
    border: 1px solid #3a3a3a; 
    border-radius: 6px; 
    padding: 8px 12px;
    transition: all 0.2s ease;
}
.list-filters input[type="text"]:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}
.list-filters select { 
    max-width: 200px;
    background: rgba(26, 26, 26, 0.8); 
    color: #e9efff; 
    border: 1px solid #3a3a3a; 
    border-radius: 6px; 
    padding: 8px 12px;
    transition: all 0.2s ease;
}
.list-filters select:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}
.conn-list { display:flex; flex-direction:column; gap:8px; flex:1 1 auto; overflow:auto; }
.llm-left .llm-title { font-family: 'MagicCards', serif; letter-spacing: 0.6px; word-spacing: 10px; }
.conn-li { 
    border:1px solid #3a3a3a; 
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98)); 
    border-radius:10px; 
    padding:12px; 
    cursor:pointer; 
    transition: all .2s ease;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}
.conn-li:hover { 
    background: linear-gradient(135deg, rgba(58, 58, 58, 0.95), rgba(48, 48, 48, 0.98)); 
    transform: translateY(-2px);
    border-color: #4a4a4a;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
}
.conn-li.active { 
    outline:2px solid rgb(242,124,17); 
    background: linear-gradient(135deg, rgba(52, 42, 32, 0.95), rgba(44, 34, 24, 0.98));
    box-shadow: 0 4px 12px rgba(242, 124, 17, 0.3);
}
.conn-li .head { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.conn-li .title { font-weight:600; color:#e9efff; }
.conn-li .badge { font-size:11px; padding:2px 6px; border:1px solid #4a4a4a; border-radius:999px; color:#9fb1c9; }
.conn-li .sub { font-size:12px; color:#9fb1c9; margin-top:3px; overflow-wrap:anywhere; }
.conn-li .actions { display:flex; gap:6px; margin-top:6px; justify-content:flex-end; }
.pf-badges { display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
.pf-badges-row { display:flex; gap:10px; align-items:center; flex-wrap:nowrap; }
.pf-flag { display:inline-flex; align-items:center; font-size:12px; padding:2px 8px; border:1px solid #4a4a4a; border-radius:0; color:#cfd9ea; background:rgba(255,255,255,0.04); line-height:1.4; }
/* Active Slots block rows */
.slot-row { display:flex; align-items:center; justify-content:flex-start; gap:10px; padding:2px 0; }
.slot-key { color:#fff; font-weight:700; min-width:70px; white-space:nowrap; }
.slot-val { color:#cfd9ea; overflow-wrap:anywhere; }
.profile-test-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.74); z-index:10000; align-items:center; justify-content:center; padding:24px; }
.profile-test-shell { width:min(1120px, 96vw); max-height:90vh; overflow:hidden; display:flex; flex-direction:column; background:#1e1e1e; border:1px solid #4a4a4a; border-radius:14px; color:#e9efff; box-shadow:0 18px 48px rgba(0,0,0,.45); }
.profile-test-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:18px 20px; border-bottom:1px solid #343434; }
.profile-test-title { font-size:18px; font-weight:800; color:rgb(242,124,17); }
.profile-test-subtitle { color:#9fb1c9; font-size:13px; margin-top:4px; }
.profile-test-body { overflow:auto; padding:16px 20px 20px; }
.profile-test-summary { display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); gap:8px; margin-bottom:12px; }
.profile-test-card { background:#262626; border:1px solid #393939; border-radius:10px; padding:10px; }
.profile-test-card .num { font-size:20px; font-weight:800; color:#fff; }
.profile-test-card .lbl { color:#9fb1c9; font-size:12px; margin-top:2px; }
.profile-test-progress { height:8px; background:#2e2e2e; border-radius:99px; overflow:hidden; border:1px solid #3c3c3c; margin-bottom:14px; }
.profile-test-progress > div { height:100%; width:0%; background:linear-gradient(90deg, rgb(242,124,17), #ffd089); transition:width .2s ease; }
.profile-test-profile { border:1px solid #383838; border-radius:10px; background:#242424; margin-bottom:10px; overflow:hidden; }
.profile-test-profile-title { padding:10px 12px; display:flex; justify-content:space-between; gap:12px; background:#2a2a2a; border-bottom:1px solid #383838; font-weight:700; }
.profile-test-slots { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; padding:10px; }
@media (max-width: 840px) { .profile-test-summary { grid-template-columns:repeat(2, minmax(0, 1fr)); } .profile-test-slots { grid-template-columns:1fr; } }
.profile-test-slot { display:grid; grid-template-columns:115px 78px 1fr; gap:8px; align-items:start; border:1px solid #363636; background:#202020; border-radius:8px; padding:8px; font-size:12px; }
.profile-test-slot .slot-name { color:#f0f4ff; font-weight:700; }
.profile-test-badge { display:inline-flex; justify-content:center; align-items:center; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:800; text-transform:uppercase; border:1px solid transparent; }
.profile-test-badge.pending { color:#d7dfef; border-color:#626262; background:#333; }
.profile-test-badge.pass { color:#bdf4cb; border-color:#2f8050; background:#16351f; }
.profile-test-badge.warn { color:#ffe2a3; border-color:#9c6a18; background:#3f2c0d; }
.profile-test-badge.fail { color:#ffb6b6; border-color:#923232; background:#421616; }
.profile-test-badge.skipped { color:#9fb1c9; border-color:#465164; background:#252b35; }
.profile-test-message { color:#cfd9ea; overflow-wrap:anywhere; }
.profile-test-detail { color:#8390a6; margin-top:3px; overflow-wrap:anywhere; }
.pf-tabs { display:flex; gap:6px; flex-wrap:wrap; margin: 8px 0 10px; border-bottom: 2px solid #3a3a3a; }
.pf-tab { 
    background: rgba(42, 42, 42, 0.8); 
    border:none; 
    padding:10px 16px; 
    color:#e9efff; 
    cursor:pointer; 
    border-top-left-radius:8px; 
    border-top-right-radius:8px; 
    transition: all .2s ease; 
    font-size:0.95em;
    font-weight: 600;
    border: 1px solid #3a3a3a;
    border-bottom: none;
}
.pf-tab:hover { 
    background: rgba(58, 58, 58, 0.9);
    transform: translateY(-1px);
}
.pf-tab.active { 
    background: linear-gradient(180deg, rgba(52, 42, 32, 0.95), rgba(42, 34, 24, 0.98)); 
    border-color: rgb(242,124,17); 
    border-bottom: 2px solid rgb(242,124,17); 
    margin-bottom:-2px;
    color: rgb(242,124,17);
}
.pf-pane { display:none; }
.pf-pane.active { display:block; }
.pf-lines { display:flex; flex-direction:column; gap:4px; margin-top:6px; }
.pf-line { display:grid; grid-template-columns: 18px 150px 1fr; align-items:center; gap:6px; font-size:12px; color:#cfd9ea; }
.pf-icon { width:18px; text-align:center; opacity:0.9; }
.pf-key { color:#9fb1c9; font-weight:600; white-space:nowrap; text-align:left; }
.pf-val { overflow-wrap:anywhere; }
/* Ensure inputs fit within card borders */
.connector-card input[type="text"],
.connector-card input[type="number"],
.connector-card input[type="password"],
.connector-card select,
.connector-card textarea { 
    width: 100%; 
    max-width: 100%; 
    box-sizing: border-box;
    background: rgba(26, 26, 26, 0.8); 
    color: #e9efff; 
    border: 1px solid #3a3a3a; 
    border-radius: 6px; 
    padding: 10px 12px;
    font-size: 14px;
    transition: all 0.2s ease;
}
.connector-card input[type="text"]:focus,
.connector-card input[type="number"]:focus,
.connector-card input[type="password"]:focus,
.connector-card select:focus,
.connector-card textarea:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    background: rgba(34, 34, 34, 0.9);
}
.connector-card textarea {
    min-height: 100px;
    resize: vertical;
    font-family: inherit;
}
.connector-card label {
    color: #fff;
    font-weight: 600;
    display: block;
    margin-bottom: 6px;
}
.connector-card .hint,
.connector-card small.hint {
    color: #9fb1c9;
    font-size: 12px;
    display: block;
    margin-top: 4px;
    line-height: 1.4;
}
/* Connector help text */
.connector-help { color:#cfd9ea; font-size:12px; margin-top:6px; }
.connector-help ul { margin:6px 0 0 16px; padding:0; }
.connector-help li { margin:2px 0; }
/* Profile Settings: provider-style cards (match Global Settings look) */
.provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; align-items:start; }
.provider-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
.provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.profile-global-overrides > summary { cursor:pointer; list-style:none; margin-bottom:0; }
.profile-global-overrides > summary::-webkit-details-marker { display:none; }
.profile-global-overrides > summary::after { content:'\25B8'; color:rgb(242, 124, 17); font-size:18px; }
.profile-global-overrides[open] > summary { margin-bottom:8px; }
.profile-global-overrides[open] > summary::after { content:'\25BE'; }
.provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
.provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
.provider-body { display:flex; gap:8px; align-items:center; }
.provider-body.grid { display:grid; grid-template-columns: 220px 1fr; gap:8px 12px; align-items:center; }
.provider-body.grid .help { grid-column: 1 / -1; margin-top:6px; color:#bbb; font-size:12px; }
.provider-title .provider-toggle { margin-left: 10px; display:flex; align-items:center; }
.provider-title .provider-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
.profile-settings-group-card { padding-top: 6px; padding-bottom: 6px; }
.setting-row { display:grid; grid-template-columns: minmax(260px, 1fr) minmax(280px, 420px); gap:10px 14px; align-items:center; padding:8px 0; border-top:1px solid rgba(255,255,255,0.05); }
.setting-row:first-child { border-top: 0; }
.setting-key { font-size: 12px; color:#f0f5ff; font-weight:700; margin-bottom:2px; display:flex; align-items:center; gap:8px; }
.setting-icon { width:20px; text-align:center; color: rgb(242, 124, 17); }
.setting-desc { font-size: 12px; color:#9fb1c9; line-height:1.35; }
.setting-control { justify-self:end; width:100%; max-width:420px; }
.setting-control-wide { max-width:620px; }
.setting-control input[type="text"],
.setting-control input[type="number"],
.setting-control select,
.setting-control textarea { width:100%; }
.setting-control textarea { min-height: 88px; }
body .setting-key .profile-setting-sync-btn {
    display:inline-flex;
    flex:0 0 auto;
    min-height:18px !important;
    margin:0 !important;
    padding:2px 5px !important;
    border:1px solid #4b4b4b !important;
    border-radius:4px !important;
    background:#303030 !important;
    color:#f3f3f3 !important;
    font-size:9px !important;
    font-weight:600;
    line-height:1.1;
    cursor:pointer;
}
body .setting-key .profile-setting-sync-btn:hover { border-color:#f27c11 !important; background:#383838 !important; }
.range-pair { display:flex; align-items:center; gap:8px; }
.range-pair input[type="range"] { flex:1; accent-color: rgb(242,124,17); }
.range-pair input[type="number"] { width:86px; min-width:86px; text-align:right; }
.meta-toggle-inline { display:inline-flex; align-items:center; justify-content:flex-end; gap:10px; width:100%; color:#e9efff; font-weight:600; }
.meta-toggle-inline input[type="checkbox"] { transform: scale(1.2); transform-origin:center; }
.profile-setting-chips { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
.profile-setting-chip { display:inline-flex; align-items:center; gap:6px; background:#1f2a36; border:1px solid #33485f; padding:6px 10px; border-radius:8px; color:#dfe6f4; font-size:12px; }
.profile-setting-chip input[type="checkbox"] { transform: scale(1.0); transform-origin:center; }
@media (max-width: 980px) {
    .setting-row { grid-template-columns: 1fr; }
    .setting-control,
    .setting-control-wide { max-width: none; justify-self: stretch; }
    .profile-setting-chips { justify-content:flex-start; }
}
/* Toast notification */
.toast-notification { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: 500; z-index: 10000; opacity: 0; transform: translateX(400px); transition: all 0.3s ease; max-width: 400px; }
.toast-notification.show { opacity: 1; transform: translateX(0); }
.toast-notification:not(.error) { background: linear-gradient(135deg, #6dd19c, #5bb377); border: 1px solid rgba(109, 209, 156, 0.3); }
.toast-notification.error { background: linear-gradient(135deg, #ff6b6b, #e55a5a); border: 1px solid rgba(255, 107, 107, 0.3); }
/* Compact select row with Set button */
.select-row { display:flex; gap:8px; align-items:center; }
.select-row select { max-width: 480px; }
.btn-apply { white-space: nowrap; padding: 6px 10px; }
/* Collapsible block for Metadata */
.collapsible { margin-top: 8px; border:1px solid #4a4a4a; border-radius:10px; background:#2a2a2a; }
.collapsible-header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px; cursor:pointer; user-select:none; color:#e9efff; font-weight:600; }
.collapsible-header::after { content:'\25BE'; font-size:12px; color:#9fb1c9; transition: transform .12s ease; }
.collapsible[open] .collapsible-header { border-bottom:1px solid #4a4a4a; }
.collapsible[open] .collapsible-header::after { transform: rotate(180deg); }
.collapsible-content { padding:10px; }
.section-title { font-weight:800; color:#e9efff; border-bottom:1px solid #4a4a4a; padding-bottom:4px; margin:10px 0 6px; }
/* Inline title + toggle styling */
.label-with-toggle { display:flex; align-items:center; gap:10px; color:#fff; }
.label-with-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; margin-left:8px; }
/* Profile Settings (metadata editor) checkbox enhancement */
.profile-settings-card input[type="checkbox"] { accent-color:#176529; transform: scale(1.6); transform-origin:center; cursor:pointer; }
/* Profile Core compact rows for Name/Slot */
.profile-core-compact-field { margin-bottom: 6px; }
.profile-core-compact-field > label { margin-bottom: 3px; line-height: 1.25; }
.profile-core-compact-field > .hint,
.profile-core-compact-field > small.hint { margin-top: 3px; line-height: 1.3; }

/* Compact profile editor */
.profile-editor-toolbar {
    position:sticky;
    top:0;
    z-index:50;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
    padding:10px 12px;
    background:rgba(31,31,31,0.97);
    border:1px solid #444;
    border-radius:8px;
    box-shadow:0 4px 14px rgba(0,0,0,0.28);
    backdrop-filter:blur(4px);
}
.profile-editor-toolbar-label { color:#9fb1c9; font-size:11px; text-transform:uppercase; letter-spacing:0.08em; }
.profile-editor-toolbar-name { color:#f3f5fa; font-size:16px; font-weight:700; margin-top:2px; }
.profile-core-grid { display:grid; grid-template-columns:minmax(0, 2fr) 120px 190px; gap:10px; align-items:start; }
.profile-core-grid .profile-core-compact-field { margin:0; }
.profile-default-card {
    min-height:68px;
    display:flex !important;
    flex-direction:column;
    justify-content:center;
    padding:9px 10px;
    border:1px solid #414141;
    border-radius:7px;
    background:#242424;
    cursor:pointer;
}
.profile-prompt-field { margin-top:10px; }
.profile-prompt-field textarea { min-height:82px; }
.profile-toggle-groups { display:grid; gap:9px; margin-top:10px; }
.profile-toggle-group { padding:9px; border:1px solid #3f3f3f; border-radius:7px; background:#202020; }
.profile-toggle-group-title { margin:0 0 7px; color:#f27c11; font-family:'MagicCards', serif; font-size:1em; letter-spacing:0.4px; word-spacing:4px; }
.profile-toggle-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:8px; }
.profile-toggle-card {
    display:block !important;
    min-height:78px;
    margin:0 !important;
    padding:9px 10px;
    border:1px solid #414141;
    border-radius:7px;
    background:#242424;
    cursor:pointer;
}
.profile-toggle-heading { display:flex; align-items:center; justify-content:space-between; gap:8px; color:#f0f5ff; font-size:12px; font-weight:700; }
.profile-toggle-control { display:inline-flex; align-items:center; gap:7px; white-space:nowrap; }
.profile-toggle-card input[type="checkbox"],
.profile-default-card input[type="checkbox"] { accent-color:#176529; transform:scale(1.25); cursor:pointer; }
.profile-toggle-card .toggle-text,
.profile-default-card .toggle-text { min-width:20px; color:#dce5f4; font-size:11px; text-align:right; }
.profile-toggle-description { display:block; margin-top:6px; color:#9fb1c9; font-size:11px; font-weight:400; line-height:1.3; }
.connector-selection-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; align-items:stretch; }
.connector-group-card { min-width:0; height:100%; padding:11px; border:1px solid #414141; border-radius:7px; background:#202020; box-sizing:border-box; }
.connector-group-title { margin:0; color:#f27c11; font-family:'MagicCards', serif; font-size:1.05em; line-height:1.25; letter-spacing:0.4px; word-spacing:5px; }
.connector-group-subtitle { min-height:30px; margin:4px 0 8px; color:#9fb1c9; font-size:11px; line-height:1.3; }
.connector-group-fields { display:grid; grid-template-columns:1fr; gap:8px; }
.connector-option-card { min-width:0; padding:10px; border:1px solid #414141; border-radius:7px; background:#242424; }
.connector-option-card .setting-key { margin-bottom:2px; }
.connector-option-card .setting-desc { min-height:32px; }
.connector-option-card .setting-control { max-width:none; margin-top:7px; }
.profile-feature-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:9px; align-items:stretch; margin-bottom:9px; }
.profile-feature-grid .provider-card { height:100%; margin:0 !important; box-sizing:border-box; }
.profile-feature-grid .setting-row,
.profile-settings-columns .setting-row { grid-template-columns:1fr; gap:7px; }
.profile-feature-grid .setting-control,
.profile-feature-grid .setting-control-wide,
.profile-settings-columns .setting-control,
.profile-settings-columns .setting-control-wide { max-width:none; justify-self:stretch; }
.profile-feature-grid .profile-setting-chips,
.profile-settings-columns .profile-setting-chips { justify-content:flex-start; }
.profile-settings-columns { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; align-items:stretch; }
.profile-settings-group { display:flex; min-width:0; flex-direction:column; }
.profile-settings-group > .provider-card { flex:1 1 auto; box-sizing:border-box; }
.profile-settings-heading {
    margin:0 0 6px;
    padding:0;
    color:#f27c11;
    font-family:'MagicCards', serif;
    font-size:1.05em;
    line-height:1.25;
    letter-spacing:0.4px;
    word-spacing:5px;
    text-shadow:1px 1px 2px rgba(0,0,0,0.5);
}
.rechat-calculator { margin-bottom:7px; padding:8px 10px; border:1px solid #3c3c3c; border-radius:7px; background:#1d1d1d; }
.rechat-calculator-title { display:flex; align-items:center; gap:7px; margin-bottom:5px; color:#f27c11; font-size:12px; font-weight:700; }
.profile-settings-other { margin-top:10px; }

@container (max-width: 760px) {
    .profile-core-grid { grid-template-columns:minmax(0, 1fr) 110px; }
    .profile-default-card { grid-column:1 / -1; min-height:auto; }
    .profile-toggle-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .connector-selection-grid { grid-template-columns:1fr; }
    .connector-group-fields { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .profile-settings-columns,
    .profile-feature-grid { grid-template-columns:1fr; }
}
@container (max-width: 540px) {
    .profile-editor-toolbar { position:static; }
    .profile-core-grid,
    .profile-toggle-grid { grid-template-columns:1fr; }
    .connector-group-fields { grid-template-columns:1fr; }
    .profile-default-card { grid-column:auto; }
}
</style>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>
<?php
$GLOBALS["db"]=new sql();

$profiles = new CoreProfile();

// Populate arrays for connector labels at the beginning of the file
$ttsOptions = getSelectOptions($profiles, "tts_connector_id");
$ittOptions = getSelectOptions($profiles, "itt_connector_id");
$diaryOptions = getSelectOptions($profiles, "diary_connector_id");
$llmPrimaryOptions = getSelectOptions($profiles, "llm_primary_id");
$llmSecondaryOptions = getSelectOptions($profiles, "llm_secondary_id");
$llmTertiaryOptions = getSelectOptions($profiles, "llm_tertiary_id");
$llmQuaternaryOptions = getSelectOptions($profiles, "llm_quaternary_id");
$llmFormatterOptions = getSelectOptions($profiles, "llm_formatter_id");

// Load RPG Comments schema options for per-profile control
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
$__confSchema = conf_loader_load_schema();
$__rpgOptionsRaw = is_array($__confSchema['RPG_COMMENTS']['values'] ?? null) ? $__confSchema['RPG_COMMENTS']['values'] : [];
$__rpgHelp = (string)($__confSchema['RPG_COMMENTS']['description'] ?? '');
$__rpgOptions = array_values(array_filter($__rpgOptionsRaw, function($v){ return strtolower((string)$v) !== 'keepmechecked'; }));
// Load Dynamic Profile Fields options for per-profile control
$__dynOptions = is_array($__confSchema['DYNAMIC_PROFILE_FIELDS']['values'] ?? null) ? $__confSchema['DYNAMIC_PROFILE_FIELDS']['values'] : [];
$__dynHelp = (string)($__confSchema['DYNAMIC_PROFILE_FIELDS']['description'] ?? '');

// Only profile metadata fields rendered by the visual editor may be copied in bulk.
$profileSyncableMetadataKeys = chimPrismaProfileSyncableMetadataKeys();

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
        header('Content-Type: application/json');
    }
    // Server-side merge of visual metadata with JSON editor content
    if (isset($_POST['meta_vis'])) {
        $base = [];
        if (!empty($_POST['metadata'])) {
            $tmp = json_decode($_POST['metadata'], true);
            if (is_array($tmp)) $base = $tmp;
        }
        foreach ((array)$_POST['meta_vis'] as $k=>$v) unset($base[$k]);
        foreach ((array)$_POST['meta_vis'] as $k=>$v) {
            if (is_array($v)) {
                $v = array_values(array_filter($v, function($x){ return $x !== '' && $x !== null && strtolower((string)$x) !== 'keepmechecked'; }));
            }
            // Skip scalar values that are empty (indicates deletion)
            if (!is_array($v) && ($v === '' || $v === null)) {
                continue;
            }
            $base[$k] = $v;
        }
        $_POST['metadata'] = json_encode($base, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    $newId = $profiles->create($_POST);
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        echo json_encode(['ok'=>true,'id'=>$newId]);
        exit;
    } else {
        header("Location: core_profiles.php");
        exit;
    }
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
        header('Content-Type: application/json');
    }
    $requestedSyncSetting = $_POST['sync_profile_setting'] ?? (isset($_POST['sync_diary_prompt']) ? 'DIARY_PROMPT' : null);
    $syncSettingKey = is_string($requestedSyncSetting) ? trim($requestedSyncSetting) : null;
    $syncRequestInvalid = $syncSettingKey !== null && !in_array($syncSettingKey, $profileSyncableMetadataKeys, true);

    // Server-side merge of visual metadata with JSON editor content
    if (isset($_POST['meta_vis'])) {
        $base = [];
        if (!empty($_POST['metadata'])) {
            $tmp = json_decode($_POST['metadata'], true);
            if (is_array($tmp)) $base = $tmp;
        }
        foreach ((array)$_POST['meta_vis'] as $k=>$v) unset($base[$k]);
        foreach ((array)$_POST['meta_vis'] as $k=>$v) {
            if (is_array($v)) {
                $v = array_values(array_filter($v, function($x){ return $x !== '' && $x !== null && strtolower((string)$x) !== 'keepmechecked'; }));
            }
            // Skip scalar values that are empty (indicates deletion)
            if (!is_array($v) && ($v === '' || $v === null)) {
                continue;
            }
            $base[$k] = $v;
        }
        $_POST['metadata'] = json_encode($base, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    $updated = $syncRequestInvalid ? false : $profiles->update($_POST["id"], $_POST);
    $syncedProfiles = null;
    $syncError = $syncRequestInvalid ? 'This profile setting cannot be copied to all profiles.' : null;

    if ($updated !== false && $syncSettingKey !== null) {
        $metadata = json_decode($_POST['metadata'] ?? '{}', true);
        $metadata = is_array($metadata) ? $metadata : [];

        if (array_key_exists($syncSettingKey, $metadata)) {
            $encodedValue = json_encode($metadata[$syncSettingKey], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $escapedValue = $GLOBALS['db']->escape($encodedValue);
            $syncResult = $GLOBALS['db']->execQuery(
                "UPDATE core_profiles
                 SET metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb), '{{$syncSettingKey}}', '{$escapedValue}'::jsonb, true)"
            );
        } else {
            $syncResult = $GLOBALS['db']->execQuery(
                "UPDATE core_profiles
                 SET metadata = COALESCE(metadata, '{}'::jsonb) - '{$syncSettingKey}'"
            );
        }

        if ($syncResult === false) {
            $updated = false;
            $syncError = 'The profile was saved, but the selected setting could not be copied to all profiles.';
        } else {
            $syncedProfiles = $profiles->getProfileCount();
        }
    }

    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        echo json_encode([
            'ok' => $updated !== false,
            'id' => $_POST['id'] ?? null,
            'synced_profiles' => $syncedProfiles,
            'synced_setting' => $syncSettingKey,
            'error' => $updated === false ? ($syncError ?? $profiles->getLastError()) : null,
        ]);
        exit;
    } else {
        header("Location: core_profiles.php");
        exit;
    }
}

// Handle Delete
if (isset($_GET["delete"])) {
    $deleteId = intval($_GET["delete"]);
    $profileToDelete = $profiles->readOne($deleteId);

    if (!$profileToDelete) {
        header("Location: core_profiles.php");
        exit;
    }

    $isDefaultNpc = $profileToDelete['default_npc'] == '1';
    $isDefaultNarrator = $profileToDelete['default_narrator'] == '1';

    // If a replacement was selected, promote it first then delete
    if (isset($_GET["replace_with"]) && is_numeric($_GET["replace_with"])) {
        $replaceId = intval($_GET["replace_with"]);
        if ($replaceId <= 0 || $replaceId === $deleteId || !$profiles->readOne($replaceId)) {
            header("Location: core_profiles.php?error=" . urlencode("Invalid replacement profile selected."));
            exit;
        }

        $promoteNpcOk = true;
        $promoteNarratorOk = true;
        if ($isDefaultNpc) {
            $promoteNpcOk = (bool)$profiles->promoteToDefaultNpc($replaceId);
        }
        if ($isDefaultNarrator) {
            $promoteNarratorOk = (bool)$profiles->promoteToDefaultNarrator($replaceId);
        }

        if (!$promoteNpcOk || !$promoteNarratorOk) {
            header("Location: core_profiles.php?error=" . urlencode("Failed to promote replacement profile before delete."));
            exit;
        }

        $deleted = $profiles->delete($deleteId);
        if (!$deleted) {
            header("Location: core_profiles.php?error=" . urlencode($profiles->getLastError()));
            exit;
        }

        header("Location: core_profiles.php");
        exit;
    }

    // If it's a default profile, redirect to the picker instead of deleting
    if ($isDefaultNpc || $isDefaultNarrator) {
        header("Location: core_profiles.php?pick_replacement={$deleteId}");
        exit;
    }

    // Non-default profile: delete directly
    $result = $profiles->delete($deleteId);
    if (!$result) {
        header("Location: core_profiles.php?error=" . urlencode($profiles->getLastError()));
        exit;
    }
    header("Location: core_profiles.php");
    exit;
}

// Handle Export Profile (download JSON with all connectors)
if (isset($_GET["export"]) && is_numeric($_GET["export"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    
    $exportId = intval($_GET["export"]);
    $profileRow = $profiles->readOne($exportId);
    
    if (!$profileRow) {
        header("HTTP/1.1 404 Not Found");
        echo "Profile not found";
        exit;
    }
    
    // Gather all referenced connectors
    $llmConnector = new LLMConnector();
    $ttsConnector = new TTSConnector();
    $apiBadge = new ApiBadge();
    
    // Collect all LLM connectors referenced by this profile
    $llmConnectorIds = array_filter([
        $profileRow['llm_primary_id'],
        $profileRow['llm_secondary_id'],
        $profileRow['llm_tertiary_id'],
        $profileRow['llm_quaternary_id'],
        $profileRow['llm_formatter_id'],
        $profileRow['llm_fallback_id'],
        $profileRow['diary_connector_id']
    ], function($id) { return !empty($id); });
    
    $llmConnectors = [];
    $apiBadgeIds = [];
    foreach ($llmConnectorIds as $llmId) {
        $conn = $llmConnector->readOne($llmId);
        if ($conn) {
            // Track API badge IDs
            if (!empty($conn['api_badge_id'])) {
                $apiBadgeIds[] = $conn['api_badge_id'];
            }
            $llmConnectors[] = $conn;
        }
    }
    
    // Get TTS connector
    $ttsConn = null;
    if (!empty($profileRow['tts_connector_id'])) {
        $ttsConn = $ttsConnector->readOne($profileRow['tts_connector_id']);
        if ($ttsConn && !empty($ttsConn['api_badge_id'])) {
            $apiBadgeIds[] = $ttsConn['api_badge_id'];
        }
    }
    
    // Get ITT connector (if ITT connector class exists)
    $ittConn = null;
    if (!empty($profileRow['itt_connector_id'])) {
        // ITT connector table exists but class may not be implemented yet
        $ittData = $GLOBALS["db"]->fetchOne("SELECT * FROM core_itt_connector WHERE id = " . intval($profileRow['itt_connector_id']));
        if ($ittData) {
            $ittConn = $ittData;
        }
    }
    
    // Get all referenced API badges (WITHOUT KEYS for security)
    $apiBadges = [];
    foreach (array_unique($apiBadgeIds) as $badgeId) {
        $badge = $apiBadge->getById($badgeId);
        if ($badge) {
            // SECURITY: Exclude API key from export
            $apiBadges[] = [
                'id' => $badge['id'],
                'label' => $badge['label'],
                'api_key' => '' // Intentionally blank for security
            ];
        }
    }
    
    // Build export data
    $exportData = [
        'export_version' => '1.0',
        'export_date' => date('c'),
        'profile' => $profileRow,
        'llm_connectors' => $llmConnectors,
        'tts_connector' => $ttsConn,
        'itt_connector' => $ittConn,
        'api_badges' => $apiBadges
    ];
    
    $filename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($profileRow['label'] ?? 'profile')) . '_export.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Inline update handler for LLM connectors (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_connector"])) {
    // Ensure no buffered HTML leaks into JSON response
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $llm = new LLMConnector();
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }

        $allowed = [
            'label','service','url','model','provider','driver','max_tokens','temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a','enforce_json','prefill_json','reasoning_model','json_schema','api_badge_id','extra_parameters_yaml','extra_parameters_enabled','disable_streaming'
        ];
        $data = [];
        $readConnectorMetadata = function() use (&$data, $llm, $id) {
            if (isset($data['metadata'])) {
                $metadata = json_decode(strval($data['metadata']), true);
                if (is_array($metadata)) {
                    return $metadata;
                }
            }
            $row = $llm->getById($id);
            $metadata = is_string($row['metadata'] ?? '') ? json_decode($row['metadata'], true) : ($row['metadata'] ?? []);
            return is_array($metadata) ? $metadata : [];
        };
        $writeConnectorMetadata = function(array $metadata) use (&$data) {
            $data['metadata'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $_POST)) continue;
            $v = $_POST[$k];
            if (in_array($k, ['enforce_json','prefill_json','reasoning_model','json_schema'], true)) {
                $data[$k] = ($v==='1' || $v==='true' || $v===1) ? 1 : 0;
            } else if (in_array($k, ['max_tokens','top_k'], true)) {
                $data[$k] = ($v === '' ? null : intval($v));
            } else if (in_array($k, ['temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','min_p','top_a'], true)) {
                $data[$k] = ($v === '' ? null : floatval($v));
            } else if ($k === 'api_badge_id') {
                $data[$k] = ($v === '' ? null : intval($v));
            } else if ($k === 'extra_parameters_yaml') {
                // Parse YAML and store as metadata.extra_parameters
                require_once __DIR__ . '/../../connector/parse_simple_yaml.php';
                $extra_parameters = parse_simple_yaml($v);
                $metadata = $readConnectorMetadata();
                if (is_array($extra_parameters)) {
                    $metadata['extra_parameters'] = $extra_parameters;
                } else {
                    unset($metadata['extra_parameters']);
                }
                $writeConnectorMetadata($metadata);
                // Don't add extra_parameters_yaml to $data directly
                continue;
            } else if ($k === 'extra_parameters_enabled') {
                $metadata = $readConnectorMetadata();
                $metadata['extra_parameters_enabled'] = ($v === '1' || $v === 'true' || $v === 1);
                $writeConnectorMetadata($metadata);
                continue;
            } else if ($k === 'disable_streaming') {
                $metadata = $readConnectorMetadata();
                $metadata['disable_streaming'] = ($v === '1' || $v === 'true' || $v === 1);
                $writeConnectorMetadata($metadata);
                continue;
            } else {
                $data[$k] = ($v === '' ? null : $v);
            }
        }
        $ok = $llm->update($id, $data);
        if ($ok === false) {
            echo json_encode(["ok"=>false, "error"=>($llm->getLastError() ?: 'Update failed')]);
        } else {
            echo json_encode(["ok"=>true]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Inline update handler for Core Profile fields (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_profile"])) {
    // Ensure no buffered HTML leaks into JSON response
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        $field = (string)($_POST['field'] ?? '');
        $allowed = [
            'llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id','llm_formatter_id','llm_fallback_id',
            'diary_connector_id','tts_connector_id','itt_connector_id'
        ];
        if (!in_array($field, $allowed, true)) { echo json_encode(["ok"=>false, "error"=>"Invalid field"]); exit; }
        $raw = $_POST['value'] ?? '';
        $val = ($raw === '' ? null : intval($raw));
        $data = [ $field => $val ];
        // Fallback direct update for llm_formatter_id to ensure persistence
        if ($field === 'llm_formatter_id') {
            $ok = $GLOBALS["db"]->updateRow('core_profiles', ['llm_formatter_id' => $val], 'id = '.$id);
        } else {
            $ok = $profiles->update($id, $data);
        }
        if ($ok === false) {
            echo json_encode(["ok"=>false, "error"=>($profiles->getLastError() ?: 'Update failed')]);
        } else {
            echo json_encode(["ok"=>true]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Add a new action for cloning a connector
if (isset($_GET["clone"])) {
    $newId = $profiles->clone($_GET["clone"]);
    if ($newId) {
        header("Location: core_profiles.php?edit=".urlencode((string)$newId));
    } else {
        header("Location: core_profiles.php");
    }
    exit;
}

if (!function_exists('chimNormalizeImportedProfileLabel')) {
    function chimNormalizeImportedProfileLabel($rawLabel, $fileName = '')
    {
        $label = trim((string)$rawLabel);
        $label = preg_replace('/(?:\s*\(Imported\))+$/i', '', $label);
        $label = trim($label);

        if ($label === '') {
            $baseName = trim((string)$fileName);
            if ($baseName !== '') {
                $baseName = basename(str_replace('\\', '/', $baseName));
                $baseName = preg_replace('/\.json$/i', '', $baseName);
                $baseName = preg_replace('/[_-]+/', ' ', $baseName);
                $label = trim($baseName);
            }
        }

        return $label !== '' ? $label : 'Imported Profile';
    }
}

if (!function_exists('chimUniqueProfileLabel')) {
    function chimUniqueProfileLabel($label)
    {
        $label = trim((string)$label);
        if ($label === '') {
            $label = 'Imported Profile';
        }

        $escaped = $GLOBALS["db"]->escape($label);
        $existing = $GLOBALS["db"]->fetchOne("SELECT id FROM core_profiles WHERE label = '{$escaped}' LIMIT 1");
        if (!$existing) {
            return $label;
        }

        $base = $label . ' (Imported)';
        $candidate = $base;
        $counter = 2;
        while (true) {
            $escapedCandidate = $GLOBALS["db"]->escape($candidate);
            $existingCandidate = $GLOBALS["db"]->fetchOne("SELECT id FROM core_profiles WHERE label = '{$escapedCandidate}' LIMIT 1");
            if (!$existingCandidate) {
                return $candidate;
            }
            $candidate = $base . ' ' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('chimNullIfBlank')) {
    function chimNullIfBlank($value)
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

// Handle Import Profile (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import_profile"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');

    $importTransactionStarted = false;
    try {
        $importJson = $_POST['import_data'] ?? '';
        
        $importData = json_decode($importJson, true);
        if (!is_array($importData)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON data']);
            exit;
        }
        
        // Validate export version
        if (empty($importData['export_version']) || $importData['export_version'] !== '1.0') {
            echo json_encode(['ok' => false, 'error' => 'Unsupported export version']);
            exit;
        }
        
        // Extract components
        $profileData = $importData['profile'] ?? [];
        $llmConnectors = $importData['llm_connectors'] ?? [];
        $ttsConnector = $importData['tts_connector'] ?? null;
        $ittConnector = $importData['itt_connector'] ?? null;
        $apiBadges = $importData['api_badges'] ?? [];
        $makeDefaultNpc = ($_POST['make_default_npc'] ?? '') === '1';
        $migrateOldDefaultNpcs = ($_POST['migrate_old_default_npcs'] ?? '') === '1';
        $assignSlotRaw = trim((string)($_POST['assign_slot'] ?? ''));
        $assignSlot = $assignSlotRaw === '' ? null : intval($assignSlotRaw);
        if ($assignSlot !== null && ($assignSlot < 1 || $assignSlot > 4)) {
            echo json_encode(['ok' => false, 'error' => 'Profile slot must be 1-4 or empty']);
            exit;
        }

        if ($GLOBALS["db"]->query("BEGIN") === false) {
            throw new Exception('Could not start profile import transaction');
        }
        $importTransactionStarted = true;

        $previousDefaultNpc = $profiles->getDefaultNpc();
        $previousDefaultNpcId = is_array($previousDefaultNpc) && !empty($previousDefaultNpc['id'])
            ? (int)$previousDefaultNpc['id']
            : 0;
        $importFileName = $_POST['import_filename'] ?? '';
        $profileLabel = chimUniqueProfileLabel(chimNormalizeImportedProfileLabel($profileData['label'] ?? '', $importFileName));
        
        // Map old IDs to new IDs
        $apiBadgeIdMap = [];
        $llmConnectorIdMap = [];
        $ttsConnectorId = null;
        $ittConnectorId = null;
        
        $llmConn = new LLMConnector();
        $ttsConn = new TTSConnector();
        $ittConn = new ITTConnector();
        $apiBadgeObj = new ApiBadge();
        
        // Step 1: Create or match API badges
        foreach ($apiBadges as $badge) {
            $oldId = $badge['id'];
            $label = $badge['label'];
            
            // Try to find existing badge by label
            $existing = $apiBadgeObj->getByLabel($label);
            if ($existing) {
                $apiBadgeIdMap[$oldId] = $existing['id'];
            } else {
                // Create new badge (without API key - user must set it manually)
                $newBadgeId = $apiBadgeObj->create([
                    'label' => $label,
                    'api_key' => '' // Empty, user must fill in
                ]);
                if (!$newBadgeId) {
                    throw new Exception($apiBadgeObj->getLastError() ?: "Could not import API key entry '{$label}'");
                }
                $apiBadgeIdMap[$oldId] = $newBadgeId;
            }
        }
        
        // Step 2: Create or match LLM connectors
        foreach ($llmConnectors as $conn) {
            $oldId = $conn['id'];
            $label = $conn['label'];
            $driver = $conn['driver'];
            $model = $conn['model'];
            
            // Try to find existing connector by label + driver + model
            $existingConn = $GLOBALS["db"]->fetchOne(
                "SELECT id FROM core_llm_connector WHERE label = '" . 
                $GLOBALS["db"]->escape($label) . "' AND driver = '" . 
                $GLOBALS["db"]->escape($driver) . "' AND model = '" . 
                $GLOBALS["db"]->escape($model) . "' LIMIT 1"
            );
            
            if ($existingConn) {
                $llmConnectorIdMap[$oldId] = $existingConn['id'];
            } else {
                // Remap API badge ID if present
                $connData = $conn;
                unset($connData['id']); // Remove old ID
                if (!empty($connData['api_badge_id']) && isset($apiBadgeIdMap[$connData['api_badge_id']])) {
                    $connData['api_badge_id'] = $apiBadgeIdMap[$connData['api_badge_id']];
                } else {
                    $connData['api_badge_id'] = null;
                }
                
                // Create new connector
                $newConnId = $llmConn->create($connData);
                if (!$newConnId) {
                    throw new Exception($llmConn->getLastError() ?: "Could not import LLM connector '{$label}'");
                }
                $llmConnectorIdMap[$oldId] = $newConnId;
            }
        }
        
        // Step 3: Create or match TTS connector
        if ($ttsConnector) {
            $label = $ttsConnector['label'];
            $driver = $ttsConnector['driver'];
            
            $existingTts = $GLOBALS["db"]->fetchOne(
                "SELECT id FROM core_tts_connector WHERE label = '" . 
                $GLOBALS["db"]->escape($label) . "' AND driver = '" . 
                $GLOBALS["db"]->escape($driver) . "' LIMIT 1"
            );
            
            if ($existingTts) {
                $ttsConnectorId = $existingTts['id'];
            } else {
                $ttsData = $ttsConnector;
                unset($ttsData['id']);
                if (!empty($ttsData['api_badge_id']) && isset($apiBadgeIdMap[$ttsData['api_badge_id']])) {
                    $ttsData['api_badge_id'] = $apiBadgeIdMap[$ttsData['api_badge_id']];
                } else {
                    $ttsData['api_badge_id'] = null;
                }
                
                $ttsConnectorId = $ttsConn->create($ttsData);
                if (!$ttsConnectorId) {
                    throw new Exception($ttsConn->getLastError() ?: "Could not import TTS connector '{$label}'");
                }
            }
        }
        
        // Step 4: Create or match ITT connector (if present)
        if ($ittConnector) {
            $label = $ittConnector['label'];
            $driver = $ittConnector['driver'];
            
            $existingItt = $GLOBALS["db"]->fetchOne(
                "SELECT id FROM core_itt_connector WHERE label = '" . 
                $GLOBALS["db"]->escape($label) . "' AND driver = '" . 
                $GLOBALS["db"]->escape($driver) . "' LIMIT 1"
            );
            
            if ($existingItt) {
                $ittConnectorId = $existingItt['id'];
            } else {
                $ittData = $ittConnector;
                unset($ittData['id']);
                if (!empty($ittData['api_badge_id']) && isset($apiBadgeIdMap[$ittData['api_badge_id']])) {
                    $ittData['api_badge_id'] = $apiBadgeIdMap[$ittData['api_badge_id']];
                } else {
                    $ittData['api_badge_id'] = null;
                }
                $ittConnectorId = $ittConn->create($ittData);
                if (!$ittConnectorId) {
                    throw new Exception($ittConn->getLastError() ?: "Could not import ITT connector '{$label}'");
                }
            }
        }
        
        // Step 5: Create new profile with remapped connector IDs
        $newProfileData = [
            'label' => $profileLabel,
            'default_npc' => 0, // Don't set as default
            'default_narrator' => 0,
            'tts_connector_id' => $ttsConnectorId,
            'itt_connector_id' => $ittConnectorId,
            'llm_primary_id' => !empty($profileData['llm_primary_id']) && isset($llmConnectorIdMap[$profileData['llm_primary_id']]) 
                ? $llmConnectorIdMap[$profileData['llm_primary_id']] : null,
            'llm_secondary_id' => !empty($profileData['llm_secondary_id']) && isset($llmConnectorIdMap[$profileData['llm_secondary_id']]) 
                ? $llmConnectorIdMap[$profileData['llm_secondary_id']] : null,
            'llm_tertiary_id' => !empty($profileData['llm_tertiary_id']) && isset($llmConnectorIdMap[$profileData['llm_tertiary_id']]) 
                ? $llmConnectorIdMap[$profileData['llm_tertiary_id']] : null,
            'llm_quaternary_id' => !empty($profileData['llm_quaternary_id']) && isset($llmConnectorIdMap[$profileData['llm_quaternary_id']]) 
                ? $llmConnectorIdMap[$profileData['llm_quaternary_id']] : null,
            'llm_formatter_id' => !empty($profileData['llm_formatter_id']) && isset($llmConnectorIdMap[$profileData['llm_formatter_id']]) 
                ? $llmConnectorIdMap[$profileData['llm_formatter_id']] : null,
            'llm_fallback_id' => !empty($profileData['llm_fallback_id']) && isset($llmConnectorIdMap[$profileData['llm_fallback_id']]) 
                ? $llmConnectorIdMap[$profileData['llm_fallback_id']] : null,
            'diary_connector_id' => !empty($profileData['diary_connector_id']) && isset($llmConnectorIdMap[$profileData['diary_connector_id']]) 
                ? $llmConnectorIdMap[$profileData['diary_connector_id']] : null,
            'metadata' => $profileData['metadata'] ?? null,
            'slot' => null, // Don't assign slot automatically
            'prompt' => $profileData['prompt'] ?? null
        ];
        
        $newProfileId = $profiles->create($newProfileData);
        if (!$newProfileId) {
            throw new Exception($profiles->getLastError() ?: 'Could not create imported profile');
        }

        if ($assignSlot !== null) {
            if ($GLOBALS["db"]->query("UPDATE core_profiles SET slot = NULL WHERE slot = {$assignSlot}") === false) {
                throw new Exception('Could not clear the selected profile slot');
            }
            $slotOk = $profiles->update($newProfileId, ['slot' => $assignSlot]);
            if ($slotOk === false) {
                throw new Exception($profiles->getLastError() ?: 'Could not assign imported profile slot');
            }
        }

        if ($makeDefaultNpc) {
            if ($profiles->promoteToDefaultNpc($newProfileId) === false) {
                throw new Exception($profiles->getLastError() ?: 'Could not set imported profile as the default');
            }
        }

        $migratedNpcCount = 0;
        if ($migrateOldDefaultNpcs) {
            $whereParts = ["profile_id IS NULL"];
            if ($previousDefaultNpcId > 0) {
                $whereParts[] = "profile_id = {$previousDefaultNpcId}";
            }
            $where = implode(' OR ', $whereParts);
            $countRow = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM core_npc_master WHERE {$where}");
            $migratedNpcCount = (int)($countRow['c'] ?? 0);
            if ($GLOBALS["db"]->query("UPDATE core_npc_master SET profile_id = {$newProfileId} WHERE {$where}") === false) {
                throw new Exception('Could not move current default NPCs to the imported profile');
            }
        }

        $messageParts = ['Profile imported successfully'];
        if ($makeDefaultNpc) {
            $messageParts[] = 'set as default NPC profile';
        }
        if ($assignSlot !== null) {
            $messageParts[] = "assigned to slot {$assignSlot}";
        }
        if ($migrateOldDefaultNpcs) {
            $messageParts[] = "migrated {$migratedNpcCount} NPCs from old default/empty profile";
        }

        if ($GLOBALS["db"]->query("COMMIT") === false) {
            throw new Exception('Could not commit imported profile');
        }
        $importTransactionStarted = false;

        echo json_encode([
            'ok' => true, 
            'id' => $newProfileId,
            'message' => implode('; ', $messageParts) . '. Please review connector settings and API keys.'
        ]);
        
    } catch (Throwable $e) {
        if ($importTransactionStarted) {
            $GLOBALS["db"]->query("ROLLBACK");
        }
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============= Profile Rules AJAX Handlers =============
$importRules = new ImportRules();

function chimDecodeRuleSelection($raw)
{
    if (is_array($raw)) {
        $values = $raw;
    } else {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid simple rule selection');
        }
        $values = $decoded;
    }

    return array_values(array_unique(array_filter(array_map(static function ($value) {
        return trim((string)$value);
    }, $values), static function ($value) {
        return $value !== '';
    })));
}

function chimImportRuleDataFromPost(array $post)
{
    $rawAction = isset($post['action']) ? trim((string)$post['action']) : '';
    $decodedAction = null;
    if ($rawAction !== '') {
        $decodedAction = json_decode($rawAction, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Action must be valid JSON');
        }
    }

    if (($post['editor_mode'] ?? 'advanced') === 'simple') {
        $matchName = ImportRules::buildExactRegex(chimDecodeRuleSelection($post['simple_match_name'] ?? '[]'));
        $matchRace = ImportRules::buildExactRegex(chimDecodeRuleSelection($post['simple_match_race'] ?? '[]'));
        $matchGender = ImportRules::buildExactRegex(chimDecodeRuleSelection($post['simple_match_gender'] ?? '[]'));
        $matchFaction = ImportRules::buildExactRegex(chimDecodeRuleSelection($post['simple_match_faction'] ?? '[]'));
        $modsArr = chimDecodeRuleSelection($post['simple_match_mods'] ?? '[]');
        $matchBase = null;
    } else {
        $matchName = chimNullIfBlank($post['match_name'] ?? '');
        $matchRace = chimNullIfBlank($post['match_race'] ?? '');
        $matchGender = chimNullIfBlank($post['match_gender'] ?? '');
        $matchBase = chimNullIfBlank($post['match_base'] ?? '');
        $matchFaction = chimNullIfBlank($post['match_faction'] ?? '');
        $modsStr = isset($post['match_mods']) ? (string)$post['match_mods'] : '';
        $modsArr = $modsStr !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $modsStr)), static function ($value) {
                return $value !== '';
            }))
            : null;
    }

    return [
        'description' => trim($post['description'] ?? ''),
        'match_name' => $matchName,
        'match_race' => $matchRace,
        'match_gender' => $matchGender,
        'match_base' => $matchBase,
        'match_faction' => $matchFaction,
        'match_mods' => empty($modsArr) ? null : $modsArr,
        'action' => $decodedAction,
        'profile' => !empty($post['profile']) ? (int)$post['profile'] : null,
        'priority' => isset($post['priority']) ? (int)$post['priority'] : 0,
        'enabled' => isset($post['enabled']) && $post['enabled'] === '1',
    ];
}

// Fetch all import rules (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["get_import_rules"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $rules = $importRules->getAll();
    foreach ($rules as &$rule) {
        $rule['_simple'] = [];
        $rule['_has_custom_regex'] = false;
        foreach ([
            'match_name' => 'name',
            'match_race' => 'race',
            'match_gender' => 'gender',
            'match_faction' => 'faction',
        ] as $column => $simpleKey) {
            $parsed = ImportRules::parseExactRegex($rule[$column] ?? null);
            if ($parsed === null) {
                $rule['_has_custom_regex'] = true;
                $parsed = [];
            }
            $rule['_simple'][$simpleKey] = $parsed;
        }
        if (!empty($rule['match_base'])) {
            $rule['_has_custom_regex'] = true;
        }
    }
    unset($rule);

    echo json_encode([
        'ok' => true,
        'data' => $rules,
        'options' => $importRules->getEditorOptions(),
    ]);
    exit;
}

// Create import rule (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_import_rule"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $importRules->create(chimImportRuleDataFromPost($_POST));
        $last = $GLOBALS['db']->fetchOne("SELECT id FROM import_rules ORDER BY id DESC LIMIT 1");
        echo json_encode(['ok' => true, 'id' => $last['id'] ?? '']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Update import rule (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_import_rule"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $importRules->update((int)$_POST['id'], chimImportRuleDataFromPost($_POST));
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Delete import rule (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_import_rule"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id'];
    $importRules->delete($id);
    echo json_encode(['ok' => true]);
    exit;
}

// Create a blank profile and open it for editing
if (isset($_GET["create_blank"])) {
    try {
        $defaultMeta = json_encode([
            'RPG_COMMENTS'=>['levelup','combat_end','bleedout'],
            'DYNAMIC_PROFILE_FIELDS'=>['personality','speechstyle','goals'],
            'RPG_COMMENTS_CHANCE'=>50
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $row = $GLOBALS["db"]->fetchOne("INSERT INTO core_profiles (label, metadata) VALUES ('New Profile', '".pg_escape_string($defaultMeta)."') RETURNING id");
        $newId = is_array($row) ? ($row['id'] ?? '') : '';
        $redir = 'core_profiles.php' . ($newId !== '' ? ('?edit=' . urlencode($newId)) : '');
        header("Location: $redir");
        exit;
    } catch (Throwable $e) {
        header("Location: core_profiles.php");
        exit;
    }
}

// Fetch Data
$data = $profiles->readAll();
$npcCountRows = $GLOBALS["db"]->fetchAll("SELECT profile_id, COUNT(*) AS c FROM core_npc_master GROUP BY profile_id");
$profileIdToNpcCount = [];
foreach ($npcCountRows as $r) {
    $pid = (string)($r['profile_id'] ?? '');
    $cnt = (int)($r['c'] ?? 0);
    if ($pid !== '') $profileIdToNpcCount[$pid] = $cnt;
}
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $profiles->getById($_GET["edit"]);
}
// Preload connector details for left list and editors
$llmRows = $GLOBALS["db"]->fetchAll("SELECT c.*, b.label AS api_badge_label FROM core_llm_connector c LEFT JOIN core_api_badge b ON b.id=c.api_badge_id ORDER BY LOWER(COALESCE(NULLIF(c.label,''), c.model)) ASC");
$ttsRows = $GLOBALS["db"]->fetchAll("SELECT t.*, b.label AS api_badge_label FROM core_tts_connector t LEFT JOIN core_api_badge b ON b.id=t.api_badge_id ORDER BY t.id ASC");
$ittRows = $GLOBALS["db"]->fetchAll("SELECT * FROM core_itt_connector ORDER BY id ASC");
$apiBadgeRows = $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_api_badge ORDER BY id ASC");

$byId = function($rows){
    $out = [];
    foreach ($rows as $r) { $out[(string)($r['id']??'')] = $r; }
    return $out;
};
$llmById = $byId($llmRows);
$ttsById = $byId($ttsRows);
$ittById = $byId($ittRows);

?>

<div class="page-header chim-page-head">
    <h1 class="api-title chim-page-head-title">CHIM Profiles</h1>
    <p class="page-subtitle chim-page-head-note">Manage NPC profiles with LLM, TTS, and ITT connectors</p>
</div>

<div class="llm-layout">
    <div class="llm-left">
        <div class="sidebar-action-grid">
            <form method="get" action="core_profiles.php">
                <input type="hidden" name="create_blank" value="1">
                <button type="submit" class="btn-save">New</button>
            </form>
            <button type="button" id="import_profile_btn" class="btn-primary">Import</button>
            <button type="button" id="open_import_rules_btn" class="btn-primary">Rules</button>
            <button type="button" id="profile_test_all_btn" class="btn-primary">Test</button>
        </div>
        <div id="profiles_list" class="conn-list"></div>
        <script>
        (function(){
            const RAW = <?= json_encode($data ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            // Expose rows for global handlers (e.g., inline delete action).
            window.CORE_PROFILES_ROWS = Array.isArray(RAW) ? RAW : [];
            const ACTIVE_ID = <?= json_encode($_GET['edit'] ?? '') ?>;
            const list = document.getElementById('profiles_list');
            const NPC_COUNT = <?= json_encode($profileIdToNpcCount ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const LLM = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const TTS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const ITT = <?= json_encode($ittById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
            function escapeJsSingleQuoted(s){
                return (s==null?'':String(s))
                    .replace(/\\/g, '\\\\')
                    .replace(/'/g, "\\'")
                    .replace(/\r/g, '\\r')
                    .replace(/\n/g, '\\n');
            }
            function labelOf(map, id){ if (!id) return ''; const k=String(id); const row=map[k]; return row && (row.label||row.model||row.driver) ? (row.label||'') : ''; }
            function pass(_row){ return true; }
            function render(){
                const rows=(RAW||[]).filter(pass);
                let html='';
                // Active Slots summary (always render slots 1-4, even if empty)
                {
                    const slotToProfile = { 1: null, 2: null, 3: null, 4: null };
                    (rows||[]).forEach(r=>{
                        const s = Number(r && r.slot);
                        if (s>=1 && s<=4 && slotToProfile[s]===null) slotToProfile[s] = r;
                    });
                    html += '<div class="connector-card" style="padding:8px;">';
                    html += '<div class="connector-title" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">Profile Slots <span style="margin-left:6px; color:#9fb1c9; cursor:help;" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">&#x24D8;</span></div>';
                    [1,2,3,4].forEach(s=>{
                        const r = slotToProfile[s];
                        if (r){
                            const title = escapeHtml(r.label||('Profile #'+r.id));
                            html += `<div class=\"slot-row\" style=\"cursor:pointer;\" data-jump-id=\"${String(r.id)}\" title=\"Can be assigned to NPCs ingame with the Settings Wheel hotkey\"><span class=\"slot-key\">Slot ${String(s)}</span><span class=\"slot-val\">${title}</span></div>`;
                        } else {
                            html += `<div class=\"slot-row\" style=\"opacity:.75;\" title=\"Can be assigned to NPCs ingame with the Settings Wheel hotkey\"><span class=\"slot-key\">Slot ${String(s)}</span><span class=\"slot-val\">&mdash; Empty &mdash;</span></div>`;
                        }
                    });
                    html += '</div>';
                }
                rows.forEach(r=>{
                    const deleteLabel = escapeJsSingleQuoted(r.label||('Profile #'+r.id));
                    const active = String(r.id)===String(ACTIVE_ID) ? ' active' : '';
                    const llm1 = escapeHtml(labelOf(LLM, r.llm_primary_id));
                    const llm2 = escapeHtml(labelOf(LLM, r.llm_secondary_id));
                    const llm3 = escapeHtml(labelOf(LLM, r.llm_tertiary_id));
                    const llm4 = escapeHtml(labelOf(LLM, r.llm_quaternary_id));
                    const tts = escapeHtml(labelOf(TTS, r.tts_connector_id));
                    const itt = escapeHtml(labelOf(ITT, r.itt_connector_id));
                    const diary = escapeHtml(labelOf(LLM, r.diary_connector_id));
                    const formatter = escapeHtml(labelOf(LLM, r.llm_formatter_id));
                    const npcCount = Number((NPC_COUNT||{})[String(r.id)]||0);
                    const row1 = [];
                    if (String(r.default_npc)==='1') row1.push('<span class="pf-flag">&#x1F464; NPC</span>');
                    const row2 = [];
                    // Slot badge removed from list items
                    if (npcCount > 0) row2.push('<span class="pf-flag">'+npcCount+' NPCs</span>');
                    html += `
                        <div class="conn-li${active}" data-id="${String(r.id)}">
                            <div class="head">
                                <div class="title">${escapeHtml(r.label||('Profile #'+r.id))}</div>
                                <div class="pf-badges">`+
                                    (row1.length?('<div class=\"pf-badges-row\">'+row1.join(' ')+'</div>'):'')+
                                    (row2.length?('<div class=\"pf-badges-row\">'+row2.join(' ')+'</div>'):'')+
                                `</div>
                            </div>
                            <div class="pf-lines">
                                <div class="pf-line"><span class="pf-icon">&#x1F50A;</span><span class="pf-key">TTS Connector</span><span class="pf-val">${tts||'&mdash;'}</span></div>
                                <div class="pf-line"><span class="pf-icon">&#x1F579;&#xFE0F;</span><span class="pf-key">Standard LLM</span><span class="pf-val">${llm1||'&mdash;'}</span></div>
                                <div class="pf-line"><span class="pf-icon">&#x1F3C3;&#x200D;&#x2642;&#xFE0F;&#x200D;&#x27A1;&#xFE0F;</span><span class="pf-key">Fast LLM</span><span class="pf-val">${llm2||'&mdash;'}</span></div>
                                <div class="pf-line"><span class="pf-icon">&#x1F4AA;</span><span class="pf-key">Powerful LLM</span><span class="pf-val">${llm3||'&mdash;'}</span></div>
                                <div class="pf-line"><span class="pf-icon">&#x1F9EA;</span><span class="pf-key">Experimental LLM</span><span class="pf-val">${llm4||'&mdash;'}</span></div>
                                <div class="pf-line"><span class="pf-icon">&#x1F4D3;</span><span class="pf-key">Diary LLM</span><span class="pf-val">${diary||'&mdash;'}</span></div>
                                <div class="pf-line"><span class="pf-icon">&#x1F9FE;</span><span class="pf-key">Formatter LLM</span><span class="pf-val">${formatter||'&mdash;'}</span></div>
                            </div>
                            <div class="actions">
                                <form method="get" action="core_profiles.php" style="display:inline">
                                    <input type="hidden" name="export" value="${r.id}">
                                    <button type="submit" class="btn-primary">Export</button>
                                </form>
                                <button type="button" class="btn-danger" onclick="handleProfileDelete(${r.id}, ${String(r.default_npc)==='1'}, ${String(r.default_narrator)==='1'}, '${deleteLabel}')">Delete</button>
                                <form method="get" action="core_profiles.php" style="display:inline">
                                    <input type="hidden" name="clone" value="${r.id}">
                                    <button type="submit" class="btn-primary">Clone</button>
                                </form>
                            </div>
                        </div>`;
                });
                list.innerHTML = html || '<div class="conn-li"><em>No profiles match filters.</em></div>';
                // wire slot jump links
                list.querySelectorAll('[data-jump-id]').forEach(el=>{
                    el.addEventListener('click', ()=>{
                        const id = el.getAttribute('data-jump-id');
                        if (id) window.location.href = `?edit=${id}`;
                    });
                });
                list.querySelectorAll('.conn-li').forEach(li => {
                    li.addEventListener('click', (ev) => {
                        if (ev.target.closest('a') || ev.target.closest('button') || ev.target.closest('form')) return;
                        const id = li.getAttribute('data-id');
                        if (id) window.location.href = `?edit=${id}`;
                    });
                });
            }
            render();
        })();

        function handleProfileDelete(id, isDefaultNpc, isDefaultNarrator, label) {
            const rows = Array.isArray(window.CORE_PROFILES_ROWS) ? window.CORE_PROFILES_ROWS : [];
            if (rows.length <= 1) {
                alert('Cannot delete the last remaining profile.');
                return;
            }

            if (isDefaultNpc || isDefaultNarrator) {
                const others = rows.filter(r => String(r.id) !== String(id));
                if (others.length === 0) {
                    alert('Cannot delete the last remaining profile.');
                    return;
                }

                let defaultTypes = [];
                if (isDefaultNpc) defaultTypes.push('NPC');
                if (isDefaultNarrator) defaultTypes.push('Narrator');

                const modal = document.getElementById('replace-profile-modal');
                document.getElementById('replace-modal-title').textContent =
                    'Replace Default ' + defaultTypes.join(' & ') + ' Profile';
                document.getElementById('replace-modal-desc').textContent =
                    '"' + label + '" is the default ' + defaultTypes.join(' & ').toLowerCase() +
                    ' profile. Choose which profile should become the new default before deleting it.';

                const select = document.getElementById('replace-profile-select');
                select.innerHTML = '';
                others.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.label || ('Profile #' + r.id);
                    select.appendChild(opt);
                });

                document.getElementById('replace-confirm-btn').onclick = function() {
                    const replaceWith = select.value;
                    if (!replaceWith) return;
                    window.location.href = 'core_profiles.php?delete=' + id + '&replace_with=' + replaceWith;
                };

                modal.style.display = 'flex';
                return;
            }

            if (confirm('Delete profile "' + label + '"? NPCs using this profile will be unassigned.')) {
                window.location.href = 'core_profiles.php?delete=' + id;
            }
        }

        function closeReplaceModal() {
            document.getElementById('replace-profile-modal').style.display = 'none';
        }
        </script>

        <div id="replace-profile-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#1e1e1e; border:1px solid #4a4a4a; border-radius:12px; padding:24px; max-width:420px; width:90%; color:#e9efff;">
                <div id="replace-modal-title" style="font-size:16px; font-weight:700; margin-bottom:8px;"></div>
                <div id="replace-modal-desc" style="font-size:13px; color:#9fb1c9; margin-bottom:16px;"></div>
                <label style="font-size:13px; font-weight:600; margin-bottom:4px; display:block;">New default profile:</label>
                <select id="replace-profile-select" style="width:100%; padding:8px; background:#2a2a2a; color:#e9efff; border:1px solid #4a4a4a; border-radius:6px; margin-bottom:16px;"></select>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeReplaceModal()">Cancel</button>
                    <button type="button" id="replace-confirm-btn" class="btn-danger">Delete & Replace</button>
                </div>
            </div>
        </div>

        <div id="profile-test-modal" class="profile-test-modal" aria-hidden="true">
            <div class="profile-test-shell" role="dialog" aria-modal="true" aria-labelledby="profile-test-title">
                <div class="profile-test-head">
                    <div>
                        <div id="profile-test-title" class="profile-test-title">Test All Profiles</div>
                        <div id="profile-test-subtitle" class="profile-test-subtitle">Testing every selected connector once, then applying shared connector results to each profile.</div>
                    </div>
                    <button type="button" id="profile-test-close" class="btn-secondary">Close</button>
                </div>
                <div class="profile-test-body">
                    <div id="profile-test-summary" class="profile-test-summary"></div>
                    <div class="profile-test-progress"><div id="profile-test-progress-fill"></div></div>
                    <div id="profile-test-results"></div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            const API_URL = <?= json_encode($webRoot . '/ui/api/profile_connector_tests.php') ?>;
            const modal = document.getElementById('profile-test-modal');
            const openBtn = document.getElementById('profile_test_all_btn');
            const closeBtn = document.getElementById('profile-test-close');
            const summaryEl = document.getElementById('profile-test-summary');
            const resultsEl = document.getElementById('profile-test-results');
            const progressFill = document.getElementById('profile-test-progress-fill');
            let cancelled = false;

            function esc(value) {
                return (value == null ? '' : String(value)).replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[c]));
            }

            function attr(value) {
                return esc(value).replace(/`/g, '&#096;');
            }

            function statusLabel(status) {
                const s = String(status || 'pending').toLowerCase();
                if (s === 'pass') return 'Pass';
                if (s === 'warn') return 'Warn';
                if (s === 'fail') return 'Fail';
                if (s === 'skipped') return 'Skipped';
                return 'Pending';
            }

            function renderSummary() {
                const counts = { pass: 0, warn: 0, fail: 0, skipped: 0, pending: 0 };
                document.querySelectorAll('#profile-test-results .profile-test-slot').forEach(slot => {
                    const status = String(slot.getAttribute('data-status') || 'pending').toLowerCase();
                    if (Object.prototype.hasOwnProperty.call(counts, status)) counts[status]++;
                    else counts.pending++;
                });
                summaryEl.innerHTML = [
                    ['pass', 'Passed'],
                    ['warn', 'Warnings'],
                    ['fail', 'Failed'],
                    ['skipped', 'Skipped'],
                    ['pending', 'Pending']
                ].map(([key, label]) => `
                    <div class="profile-test-card">
                        <div class="num">${counts[key]}</div>
                        <div class="lbl">${label}</div>
                    </div>
                `).join('');
            }

            function setProgress(done, total) {
                const pct = total > 0 ? Math.round((done / total) * 100) : 100;
                progressFill.style.width = pct + '%';
            }

            function renderPlan(plan) {
                const profiles = Array.isArray(plan.profiles) ? plan.profiles : [];
                let html = '';
                profiles.forEach(profile => {
                    const flags = [];
                    if (profile.default_npc) flags.push('Default NPC');
                    if (profile.default_narrator) flags.push('Default Narrator');
                    html += `
                        <div class="profile-test-profile">
                            <div class="profile-test-profile-title">
                                <span>${esc(profile.label || ('Profile #' + profile.id))}</span>
                                <span style="color:#9fb1c9; font-size:12px;">${esc(flags.join(' / '))}</span>
                            </div>
                            <div class="profile-test-slots">
                    `;
                    (profile.slots || []).forEach(slot => {
                        const status = slot.status || 'pending';
                        html += `
                            <div class="profile-test-slot" data-status="${attr(status)}" data-job-key="${attr(slot.job_key || '')}">
                                <div class="slot-name">${esc(slot.label || slot.field || 'Connector')}</div>
                                <div><span class="profile-test-badge ${attr(status)}">${statusLabel(status)}</span></div>
                                <div>
                                    <div class="profile-test-message">${esc(slot.message || '')}</div>
                                    <div class="profile-test-detail"></div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div></div>';
                });
                resultsEl.innerHTML = html || '<div class="profile-test-card">No profiles found.</div>';
                renderSummary();
            }

            function detailFromResult(result) {
                const details = result && result.details ? result.details : {};
                const chunks = [];
                if (details.label) chunks.push(details.label);
                if (details.driver) chunks.push('driver: ' + details.driver);
                if (details.model) chunks.push('model: ' + details.model);
                if (details.url) chunks.push('url: ' + details.url);
                if (Number(result.elapsed_ms || 0) > 0) chunks.push(String(result.elapsed_ms) + 'ms');
                if (details.response_preview) chunks.push('response: ' + details.response_preview);
                if (details.generated_file) chunks.push('audio: ' + details.generated_file);
                return chunks.join(' | ');
            }

            function applyJobResult(result) {
                const jobKey = result && result.job_key ? String(result.job_key) : '';
                if (!jobKey) return;
                document.querySelectorAll('#profile-test-results .profile-test-slot').forEach(slot => {
                    if (String(slot.getAttribute('data-job-key') || '') !== jobKey) return;
                    const status = String(result.status || 'fail').toLowerCase();
                    slot.setAttribute('data-status', status);
                    const badge = slot.querySelector('.profile-test-badge');
                    if (badge) {
                        badge.className = 'profile-test-badge ' + status;
                        badge.textContent = statusLabel(status);
                    }
                    const message = slot.querySelector('.profile-test-message');
                    if (message) message.textContent = result.message || '';
                    const detail = slot.querySelector('.profile-test-detail');
                    if (detail) detail.textContent = detailFromResult(result);
                });
                renderSummary();
            }

            async function fetchJson(url) {
                const response = await fetch(url, { credentials: 'same-origin' });
                const text = await response.text();
                let json = null;
                try {
                    json = JSON.parse(text);
                } catch (_error) {
                    throw new Error('Invalid JSON response: ' + text.slice(0, 160));
                }
                if (!response.ok || !json || json.ok !== true) {
                    throw new Error((json && json.error) ? json.error : ('HTTP ' + response.status));
                }
                return json;
            }

            async function testJob(job) {
                const url = API_URL + '?action=test&type=' + encodeURIComponent(job.type) + '&id=' + encodeURIComponent(job.id) + '&_=' + Date.now();
                try {
                    const json = await fetchJson(url);
                    return json.result;
                } catch (error) {
                    return {
                        job_key: String(job.type) + ':' + String(job.id),
                        type: job.type,
                        id: job.id,
                        status: 'fail',
                        message: error.message || 'Connector test failed',
                        details: {},
                        elapsed_ms: 0
                    };
                }
            }

            async function runJobs(jobs) {
                let completed = 0;
                const total = jobs.length;
                setProgress(0, total);
                const queue = jobs.slice();
                const workers = Array.from({ length: Math.min(2, Math.max(1, total)) }, async () => {
                    while (!cancelled && queue.length > 0) {
                        const job = queue.shift();
                        const result = await testJob(job);
                        applyJobResult(result);
                        completed++;
                        setProgress(completed, total);
                    }
                });
                await Promise.all(workers);
            }

            async function openProfileTestModal() {
                if (!modal) return;
                cancelled = false;
                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
                summaryEl.innerHTML = '';
                resultsEl.innerHTML = '<div class="profile-test-card">Building profile connector test plan...</div>';
                setProgress(0, 1);

                try {
                    const planJson = await fetchJson(API_URL + '?action=plan&_=' + Date.now());
                    const plan = planJson.plan || {};
                    const jobs = Array.isArray(plan.jobs) ? plan.jobs : [];
                    renderPlan(plan);
                    if (jobs.length === 0) {
                        setProgress(1, 1);
                        return;
                    }
                    await runJobs(jobs);
                } catch (error) {
                    resultsEl.innerHTML = '<div class="profile-test-card"><span style="color:#ff9898;">' + esc(error.message || 'Failed to run profile tests') + '</span></div>';
                    renderSummary();
                    setProgress(1, 1);
                }
            }

            function closeProfileTestModal() {
                cancelled = true;
                if (!modal) return;
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            }

            if (openBtn) openBtn.addEventListener('click', openProfileTestModal);
            if (closeBtn) closeBtn.addEventListener('click', closeProfileTestModal);
            if (modal) {
                modal.addEventListener('click', event => {
                    if (event.target === modal) closeProfileTestModal();
                });
            }
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && modal && modal.style.display === 'flex') closeProfileTestModal();
            });
        })();
        </script>
    </div>
    <div class="llm-right">
        <div class="form-container wide-centered">
        <?php if (!$editItem): ?>
            <div class="connector-placeholder" style="border:1px dashed #4a4a4a; background:#2a2a2a; color:#9fb1c9; border-radius:10px; padding:18px; margin-bottom:10px;">
                <div style="font-weight:600; color:#e9efff; margin-bottom:6px;">No profile selected</div>
                <div>Select a profile from the list on the left to view and edit its settings.</div>
            </div>
        <?php endif; ?>
        <form id="core_profile_form" method="post" onsubmit='return saveProfileAjax(event, "core_profile_form")' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    

    <?php
        $profileMetadata = [];
        try {
            if (!empty($editItem["metadata"])) {
                $decodedProfileMetadata = json_decode($editItem["metadata"], true);
                if (is_array($decodedProfileMetadata)) $profileMetadata = $decodedProfileMetadata;
            }
        } catch (Throwable $e) {}

        $dynamicProfileEnabled = !empty($profileMetadata['DYNAMIC_PROFILE_ENABLED']);
        $mtmEnabled = !empty($profileMetadata['MIDDLE_TERM_MEMORY_ENABLED']);
        $autoDiaryEnabled = !empty($profileMetadata['AUTO_DIARY_ENABLED']);
        $autoDiaryWaitEnabled = !empty($profileMetadata['AUTO_DIARY_WAIT_ENABLED']);
        $physicalDiaryEnabled = !empty($profileMetadata['MATERIALIZE_DIARY_ENABLED']);
        $latestDiaryContextEnabled = !empty($profileMetadata['LATEST_DIARY_CONTEXT_ENABLED']);
        $randomizerEnabled = !empty($profileMetadata['LLM_RANDOMIZER_ENABLED']);
        $fallbackEnabled = !empty($profileMetadata['LLM_FALLBACK_ENABLED']);

        $usedSlotsRows = $GLOBALS["db"]->fetchAll("SELECT id, slot FROM core_profiles WHERE slot IS NOT NULL ORDER BY slot ASC");
        $usedSlots = [];
        foreach ($usedSlotsRows as $r){ $s=intval($r['slot']??0); if ($s>=1 && $s<=4) $usedSlots[$s]=(int)$r['id']; }
        $currentId = (int)($editItem['id'] ?? 0);
        $currentSlot = isset($editItem['slot']) ? (int)$editItem['slot'] : 0;

        $profileToggleGroups = [
            [
                'title' => 'Profiles & Memories',
                'cards' => [
                    ['key' => 'DYNAMIC_PROFILE_ENABLED', 'icon' => '&#x267B;&#xFE0F;', 'title' => 'Dynamic Profile', 'enabled' => $dynamicProfileEnabled, 'short' => 'Allow gameplay events to evolve NPC profiles.', 'help' => 'Allow systems to evolve NPC profiles based on gameplay events. NPCs using this profile will have dynamic profile enabled by default.'],
                    ['key' => 'MIDDLE_TERM_MEMORY_ENABLED', 'icon' => '&#x1F4C3;', 'title' => 'Middle Term Memory', 'enabled' => $mtmEnabled, 'short' => 'Include periodic middle-term memory summaries.', 'help' => 'Saves a list of recent events after every 10 memory summaries. NPCs using this profile will have MTM enabled by default.'],
                ],
            ],
            [
                'title' => 'Diary',
                'cards' => [
                    ['key' => 'AUTO_DIARY_ENABLED', 'icon' => '&#x1F4D9;', 'title' => 'Auto Diary', 'enabled' => $autoDiaryEnabled, 'short' => 'Generate nearby NPC diaries during sleep or wait.', 'help' => 'Automatically generate diary entries when NPCs are nearby during sleep/wait events. NPCs using this profile will have auto diary enabled by default.'],
                    ['key' => 'AUTO_DIARY_WAIT_ENABLED', 'icon' => '&#x23F3;', 'title' => 'Auto Diary Wait', 'enabled' => $autoDiaryWaitEnabled, 'short' => 'Include wait events when Auto Diary is enabled.', 'help' => 'When Auto Diary is enabled, this controls whether diary entries are created during wait events. If disabled, auto diary will only trigger on sleep events.'],
                    ['key' => 'MATERIALIZE_DIARY_ENABLED', 'icon' => '&#x1F4D5;', 'title' => 'Physical Diary', 'enabled' => $physicalDiaryEnabled, 'short' => 'Create a physical ingame diary that can be read.', 'help' => 'Automatically creates one physical diary in each NPC\'s inventory when they write a diary entry, then refreshes that same book after future entries.'],
                    ['key' => 'LATEST_DIARY_CONTEXT_ENABLED', 'icon' => '&#x1F4D6;', 'title' => 'Include Latest Diary Entry', 'enabled' => $latestDiaryContextEnabled, 'short' => 'Include the NPC\'s latest diary entry in response context.', 'help' => 'Adds the latest diary entry written by an NPC to the character section of every response prompt.'],
                ],
            ],
            [
                'title' => 'LLM',
                'cards' => [
                    ['key' => 'LLM_RANDOMIZER_ENABLED', 'icon' => '&#x1F3B2;', 'title' => 'LLM Randomizer', 'enabled' => $randomizerEnabled, 'short' => 'Rotate among the four profile LLM connectors.', 'help' => 'Randomly switches between the 4 LLM connectors for NPCs using this profile. Will roughly switch every 2-3 responses per NPC.'],
                    ['key' => 'LLM_FALLBACK_ENABLED', 'icon' => '&#x1F504;', 'title' => 'LLM Fallback', 'enabled' => $fallbackEnabled, 'short' => 'Retry failed requests with the fallback connector.', 'help' => 'Automatically retry with the fallback connector when the primary connector fails. Response time will be longer when fallback is used.'],
                ],
            ],
        ];
    ?>

    <div class="profile-editor-toolbar">
        <div>
            <div class="profile-editor-toolbar-label">Editing Profile</div>
            <div class="profile-editor-toolbar-name"><?= htmlspecialchars($editItem["label"] ?? "Profile") ?></div>
        </div>
        <button type="button" id="btn_save_all" class="btn-save">Save All</button>
    </div>

    <div class="connector-card" style="margin-bottom:12px;">
        <div class="connector-title">Profile Core</div>
        <div class="connector-subtitle">&#x24D8; Core identity and runtime options for this profile.</div>

        <div class="profile-core-grid">
            <div class="profile-core-compact-field">
                <label for="label">Name</label>
                <input type="text" id="label" name="label" placeholder="Name" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>">
                <small class="hint">Name shown when assigning this profile.</small>
            </div>

            <div class="profile-core-compact-field">
                <label for="slot" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">Slot <span style="margin-left:4px; color:#9fb1c9; cursor:help;" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">&#x24D8;</span></label>
                <select name="slot" id="slot" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">
                    <option value="">&mdash;</option>
                    <?php for($s=1;$s<=4;$s++):
                        $takenBy = $usedSlots[$s] ?? null;
                        $disabled = ($takenBy !== null && $takenBy !== $currentId) ? ' disabled' : '';
                        $sel = ($currentSlot === $s) ? ' selected' : '';
                    ?>
                    <option value="<?= $s ?>"<?= $sel.$disabled ?>><?= $s ?></option>
                    <?php endfor; ?>
                </select>
                <small class="hint">Settings Wheel shortcut.</small>
            </div>

            <label class="profile-default-card" title="When enabled, new NPCs will default to using this profile. Only one profile can be default.">
                <span class="profile-toggle-heading">
                    <span>&#x1F464; Default NPC</span>
                    <span class="profile-toggle-control">
                        <input type="hidden" name="default_npc" value="0">
                        <input type="checkbox" name="default_npc" value="1" <?= isset($editItem["default_npc"]) && $editItem["default_npc"] == 1 ? "checked" : "" ?>>
                        <span class="toggle-text">Off</span>
                    </span>
                </span>
                <span class="profile-toggle-description">Use for newly discovered NPCs.</span>
            </label>
        </div>

        <div class="profile-prompt-field">
            <label for="prompt">Profile Prompt</label>
            <textarea id="prompt" name="prompt"><?= htmlspecialchars($editItem["prompt"] ?? "") ?></textarea>
            <small class="hint">Optional profile-specific system instructions appended to requests.</small>
        </div>

        <div class="profile-toggle-groups">
            <?php foreach ($profileToggleGroups as $toggleGroup): ?>
                <section class="profile-toggle-group">
                    <h3 class="profile-toggle-group-title"><?= htmlspecialchars($toggleGroup['title']) ?></h3>
                    <div class="profile-toggle-grid">
                        <?php foreach ($toggleGroup['cards'] as $toggleCard): ?>
                            <label class="profile-toggle-card" title="<?= htmlspecialchars($toggleCard['help']) ?>">
                                <span class="profile-toggle-heading">
                                    <span><?= $toggleCard['icon'] ?> <?= htmlspecialchars($toggleCard['title']) ?></span>
                                    <span class="profile-toggle-control">
                                        <input type="hidden" name="meta_vis[<?= htmlspecialchars($toggleCard['key']) ?>]" value="">
                                        <input type="checkbox" name="meta_vis[<?= htmlspecialchars($toggleCard['key']) ?>]" value="1" <?= $toggleCard['enabled'] ? "checked" : "" ?>>
                                        <span class="toggle-text"><?= $toggleCard['enabled'] ? 'On' : 'Off' ?></span>
                                    </span>
                                </span>
                                <span class="profile-toggle-description"><?= htmlspecialchars($toggleCard['short']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const names = ['default_npc','meta_vis[LLM_RANDOMIZER_ENABLED]','meta_vis[LLM_FALLBACK_ENABLED]','meta_vis[DYNAMIC_PROFILE_ENABLED]','meta_vis[MIDDLE_TERM_MEMORY_ENABLED]','meta_vis[AUTO_DIARY_ENABLED]','meta_vis[AUTO_DIARY_WAIT_ENABLED]','meta_vis[MATERIALIZE_DIARY_ENABLED]','meta_vis[LATEST_DIARY_CONTEXT_ENABLED]'];
        names.forEach(n=>{
            const cb = document.querySelector(`input[type="checkbox"][name="${n}"]`);
            if (!cb) return;
            const label = cb.closest('label');
            const span = label ? label.querySelector('.toggle-text') : null;
            function sync(){ if (span) span.textContent = cb.checked ? 'On' : 'Off'; }
            sync();
            cb.addEventListener('change', sync);
        });
        const basicBtn = document.getElementById('btn_save_profile_settings');
        if (basicBtn){ basicBtn.addEventListener('click', function(ev){ try{ if (typeof showToast==='function') showToast('Saving...'); saveProfileAjax(ev, 'core_profile_form'); }catch(_e){} }); }
const saveAllBtn = document.getElementById('btn_save_all');
        if (saveAllBtn){ saveAllBtn.addEventListener('click', function(ev){ try{ if (typeof showToast==='function') showToast('Saving all settings...'); saveProfileAjax(ev, 'core_profile_form'); }catch(_e){} }); }
        document.querySelectorAll('.profile-setting-sync-btn').forEach(btn => {
            btn.addEventListener('click', function(ev){
                const settingKey = btn.dataset.settingKey || '';
                const settingLabel = btn.dataset.settingLabel || 'this setting';
                if (!settingKey) return;
                if (!window.confirm('Copy "' + settingLabel + '" from this profile to all profiles? Other profile settings will not change.')) return;
                try {
                    if (typeof showToast === 'function') showToast('Copying ' + settingLabel + '...');
                    saveProfileAjax(ev, 'core_profile_form', settingKey, settingLabel);
                } catch(_e){}
            });
        });
        const backTopBtn = document.getElementById('btn_back_to_top');
        if (backTopBtn){ backTopBtn.addEventListener('click', function(){
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
            catch(_) { window.scrollTo(0, 0); }
        }); }
});
    </script>

    <?php /* connector details preloaded above for both panes */ ?>

    <div class="connector-card">
        <div class="connector-title">Connector Selection</div>
        <div class="connector-subtitle">&#x24D8; Choose connector assignments for each role. Saved with Save All.</div>

        <?php
            $connectorGroups = [
                [
                    'title' => 'Response Connectors',
                    'description' => 'Connectors used for live NPC dialogue and response modes.',
                    'rows' => [
                        ['field' => 'llm_primary_id',    'icon' => '&#x1F579;&#xFE0F;', 'title' => 'Standard LLM',     'desc' => 'General purpose connector for normal roleplay responses.', 'options' => 'llm'],
                        ['field' => 'llm_secondary_id',  'icon' => '&#x1F3C3;&#x200D;&#x2642;&#xFE0F;&#x200D;&#x27A1;&#xFE0F;', 'title' => 'Fast LLM',         'desc' => 'Lower-latency connector for quick reactions and lightweight dialogue.', 'options' => 'llm'],
                        ['field' => 'llm_tertiary_id',   'icon' => '&#x1F4AA;',         'title' => 'Powerful LLM',     'desc' => 'Higher-quality connector for deeper or more complex responses.', 'options' => 'llm'],
                        ['field' => 'llm_quaternary_id', 'icon' => '&#x1F9EA;',         'title' => 'Experimental LLM', 'desc' => 'Optional wildcard connector for experimentation and variety.', 'options' => 'llm'],
                    ],
                ],
                [
                    'title' => 'Other Connectors',
                    'description' => 'Voice, diary, formatting, and fallback services used by this profile.',
                    'rows' => [
                        ['field' => 'tts_connector_id',  'icon' => '&#x1F50A;',         'title' => 'TTS Connector',   'desc' => 'Voice synthesis connector used for spoken output.', 'options' => 'tts'],
                        ['field' => 'diary_connector_id','icon' => '&#x1F4D3;',         'title' => 'Diary LLM',        'desc' => 'Connector used for diary generation.', 'options' => 'llm'],
                        ['field' => 'llm_formatter_id',  'icon' => '&#x1F9FE;',         'title' => 'Formatter LLM',    'desc' => 'Connector used for JSON formatting and structured background tasks.', 'options' => 'llm'],
                        ['field' => 'llm_fallback_id',   'icon' => '&#x1F504;',         'title' => 'Fallback LLM',     'desc' => 'Backup connector used when primary requests fail.', 'options' => 'llm'],
                    ],
                ],
            ];
        ?>
        <div class="connector-selection-grid">
            <?php foreach ($connectorGroups as $groupCfg): ?>
                <section class="connector-group-card">
                    <h3 class="connector-group-title"><?= htmlspecialchars($groupCfg['title']) ?></h3>
                    <div class="connector-group-subtitle"><?= htmlspecialchars($groupCfg['description']) ?></div>
                    <div class="connector-group-fields">
                        <?php foreach (($groupCfg['rows'] ?? []) as $rowCfg): ?>
                            <?php $selectedId = (string)($editItem[$rowCfg['field']] ?? ''); ?>
                            <div class="connector-option-card">
                                <div class="setting-key"><span class="setting-icon"><?= $rowCfg['icon'] ?></span><span><?= htmlspecialchars($rowCfg['title']) ?></span></div>
                                <div class="setting-desc"><?= htmlspecialchars($rowCfg['desc']) ?></div>
                                <div class="setting-control">
                                    <select name="<?= htmlspecialchars($rowCfg['field']) ?>" id="<?= htmlspecialchars($rowCfg['field']) ?>">
                                        <option value="">-- None --</option>
                                        <?php $optionType = $rowCfg['options'] ?? 'llm'; ?>
                                        <?php $optionRows = ($optionType === 'tts') ? ($ttsRows ?? []) : ($llmRows ?? []); ?>
                                        <?php foreach ($optionRows as $opt): ?>
                                            <?php
                                                $optLabel = trim((string)($opt['label'] ?? '')) !== ''
                                                    ? (string)$opt['label']
                                                    : (string)($opt['model'] ?? ($optionType === 'tts' ? ('TTS #' . ($opt['id'] ?? '')) : ('LLM #' . ($opt['id'] ?? ''))));
                                            ?>
                                            <option value="<?= htmlspecialchars((string)$opt['id']) ?>" <?= ((string)$opt['id'] === $selectedId ? 'selected' : '') ?>><?= htmlspecialchars($optLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Visual Profile Settings (first chunk) -->
    <div class="connector-card profile-settings-card" style="margin-bottom:10px;">
        <div class="connector-title">Profile Settings</div>
        <?php
            // Resolve current selected RPG comments from metadata
            $rpgSelected = [];
            try {
                $metaObj = [];
                if (!empty($editItem["metadata"])) {
                    $tmp = json_decode($editItem["metadata"], true);
                    if (is_array($tmp)) $metaObj = $tmp;
                }
                $arr = $metaObj['RPG_COMMENTS'] ?? [];
                if (is_array($arr)) { $rpgSelected = array_values(array_map('strval', $arr)); }
            } catch (Throwable $_e) { $rpgSelected = []; }
            // Resolve current selected Dynamic Profile Fields from metadata
            $dynSelected = [];
            try {
                $metaObj2 = [];
                if (!empty($editItem["metadata"])) {
                    $tmp2 = json_decode($editItem["metadata"], true);
                    if (is_array($tmp2)) $metaObj2 = $tmp2;
                }
                $arr2 = $metaObj2['DYNAMIC_PROFILE_FIELDS'] ?? [];
                if (is_array($arr2)) { $dynSelected = array_values(array_map('strval', $arr2)); }
            } catch (Throwable $_e) { $dynSelected = []; }
        ?>
        <div class="profile-feature-grid">
        <?php if (!empty($__dynOptions)): ?>
        <div class="provider-card">
            <div class="provider-head">
                <div class="provider-title">
                    <div class="provider-icon">&#x1F6E0;&#xFE0F;</div>
                    <div>Dynamic Profile Fields</div>
                </div>
            </div>
            <div class="provider-body" style="display:block;">
                <div class="setting-row">
                    <div>
                        <div class="setting-key"><span class="setting-icon">&#x1F6E0;&#xFE0F;</span><span>Editable Fields</span></div>
                        <?php if (!empty($__dynHelp)): ?>
                            <div class="setting-desc"><?= htmlspecialchars($__dynHelp) ?></div>
                        <?php else: ?>
                            <div class="setting-desc">Choose which profile fields dynamic updates are allowed to rewrite.</div>
                        <?php endif; ?>
                    </div>
                    <div class="setting-control setting-control-wide">
                        <input type="hidden" name="meta_vis[DYNAMIC_PROFILE_FIELDS][]" value="">
                        <div class="profile-setting-chips">
                            <?php foreach ($__dynOptions as $opt): $val=(string)$opt; $checked = in_array($val, $dynSelected, true) ? ' checked' : ''; ?>
                                <label class="profile-setting-chip">
                                    <input type="checkbox" name="meta_vis[DYNAMIC_PROFILE_FIELDS][]" value="<?= htmlspecialchars($val) ?>"<?= $checked ?>>
                                    <span><?= htmlspecialchars($val) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($__rpgOptions)): ?>
        <div class="provider-card">
            <div class="provider-head">
                <div class="provider-title">
                    <div class="provider-icon">&#x1F3B2;</div>
                    <div>RPG Comments</div>
                </div>
            </div>
            <div class="provider-body" style="display:block;">
                <div class="setting-row">
                    <div>
                        <div class="setting-key"><span class="setting-icon">&#x1F3B2;</span><span>Comment Types</span></div>
                        <?php if (!empty($__rpgHelp)): ?>
                            <div class="setting-desc"><?= htmlspecialchars($__rpgHelp) ?></div>
                        <?php else: ?>
                            <div class="setting-desc">Choose when RPG-style comments are allowed to trigger.</div>
                        <?php endif; ?>
                    </div>
                    <div class="setting-control setting-control-wide">
                        <input type="hidden" name="meta_vis[RPG_COMMENTS][]" value="">
                        <div class="profile-setting-chips">
                            <?php foreach ($__rpgOptions as $opt): $val=(string)$opt; $checked = in_array($val, $rpgSelected, true) ? ' checked' : ''; ?>
                                <label class="profile-setting-chip">
                                    <input type="checkbox" name="meta_vis[RPG_COMMENTS][]" value="<?= htmlspecialchars($val) ?>"<?= $checked ?>>
                                    <span><?= htmlspecialchars($val) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php
                    // Get current RPG_Comments_Chance value from metadata
                    $rpgChance = 50; // Default to 50%
                    try {
                        if (!empty($editItem["metadata"])) {
                            $tmpMeta = json_decode($editItem["metadata"], true);
                            if (is_array($tmpMeta) && isset($tmpMeta['RPG_COMMENTS_CHANCE'])) {
                                $rpgChance = intval($tmpMeta['RPG_COMMENTS_CHANCE']);
                            }
                        }
                    } catch (Throwable $_e) { $rpgChance = 50; }
                ?>
                <div class="setting-row">
                    <div>
                        <div class="setting-key"><span class="setting-icon">&#x1F501;</span><span>RPG Comment Trigger Chance</span></div>
                        <div class="setting-desc">Probability that enabled RPG comments trigger when their conditions are met. 0 = Never | 50 = 50% | 100 = Always. Hard cooldown: 60 seconds between RPG comment events.</div>
                    </div>
                    <div class="setting-control">
                        <div class="range-pair">
                            <input type="range" id="rpg_chance_range" min="0" max="100" step="1" value="<?= htmlspecialchars($rpgChance) ?>" oninput="document.getElementById('rpg_chance_num').value=this.value">
                            <input type="number" id="rpg_chance_num" name="meta_vis[RPG_COMMENTS_CHANCE]" min="0" max="100" step="1" value="<?= htmlspecialchars($rpgChance) ?>" oninput="metaClamp('rpg_chance_range','rpg_chance_num',0,100)">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>
        
        <?php include(__DIR__."/tmpl/metadata_json_editor.php");?>
        <div style="margin-top:8px; display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" id="btn_back_to_top" class="btn-primary" title="Scroll to top">Back to top</button>
        </div>
    </div>
    
    <!-- Global Settings Overrides -->
    <details class="provider-card profile-global-overrides" style="margin-bottom:8px;">
        <summary class="provider-head">
            <div class="provider-title">
                <div class="provider-icon">&#x1F310;</div>
                <div>Global Settings Overrides</div>
            </div>
        </summary>
        <div class="provider-body" style="display:block;">
            <small style="color:#9fb1c9; display:block; margin-bottom:8px;">Override global settings for this profile. Changes here take precedence over global configurations.</small>
            <?php
            // Configure override editor for Profile mode
            $profileOverrideCatalog = chimGetOverrideableGeneralSettingsCatalog();
            $currentProfileOverrides = [];
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        foreach (array_keys($profileOverrideCatalog) as $key) {
                            if (array_key_exists($key, $metaData)) {
                                $currentProfileOverrides[$key] = $metaData[$key];
                            }
                        }
                        foreach (['TTSFUNCTION'] as $legacyKey) {
                            if (array_key_exists($legacyKey, $metaData)) {
                                $currentProfileOverrides[$legacyKey] = $metaData[$legacyKey];
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $currentProfileOverrides = [];
            }
            $overrideEditorConfig = [
                'mode' => 'profile',
                'fieldName' => 'metadata',
                'settingsCatalog' => $profileOverrideCatalog,
                'reservedKeys' => ['DYNAMIC_PROFILE_ENABLED', 'MIDDLE_TERM_MEMORY_ENABLED', 'AUTO_DIARY_ENABLED', 'AUTO_DIARY_WAIT_ENABLED', 'MATERIALIZE_DIARY_ENABLED', 'LATEST_DIARY_CONTEXT_ENABLED', 'LLM_RANDOMIZER_ENABLED', 'RPG_COMMENTS', 'RPG_COMMENTS_CHANCE', 'DYNAMIC_PROFILE_FIELDS'],
                'currentData' => $currentProfileOverrides,
                'systemFields' => [],
            ];
            include(__DIR__."/tmpl/override_editor.php");
            ?>
        </div>
    </details>
    
    <!-- JSON Editor (second chunk) in collapsible -->
    <details id="metadata_section" class="collapsible">
        <summary class="collapsible-header">Metadata (Advanced JSON)</summary>
        <div class="collapsible-content">
            <textarea name="metadata" style="display:none" placeholder="Metadata"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
            <div id="metadata"></div>
        </div>
    </details>

    
    <script>
    // Sticky save bar styles
    (function(){
        const css = `
            .save-bar{position:sticky; bottom:0; z-index:999;}
            .save-bar-inner{display:flex; justify-content:flex-end; gap:8px; padding:8px; background:rgba(13,17,23,0.9); border-top:1px solid rgba(138,155,182,0.35); backdrop-filter: blur(3px);}
        `;
        const styleTag = document.createElement('style');
        styleTag.textContent = css;
        document.head.appendChild(styleTag);
    })();

    // Save handler that keeps the user on the same profile and shows a toast
    async function saveProfileAjax(ev, formId, syncSettingKey, syncSettingLabel){
        try {
            if (typeof consolidation === 'function') {
                const ok = consolidation(ev, formId);
                if (ok === false) return false;
            }
        } catch(_e){}
        if (ev && typeof ev.preventDefault==='function') ev.preventDefault();
        const form = document.getElementById(formId) || ev.target;
        if (!form) return false;

        // First, attempt to persist any inline connector editors and embedded LLM editors
        try {
            await saveAllConnectorEditors();
        } catch (e) {
            // Surface connector save failure but continue to save profile data as well
            try { if (typeof showToast === 'function') showToast('Connector save failed: ' + e.message, true); } catch(_){ }
        }

        // Sync profile global overrides to metadata
        try {
            if (typeof window.syncProfileGlobalOverrides === 'function') {
                window.syncProfileGlobalOverrides();
            }
        } catch(_e) { console.error('Failed to sync profile global overrides:', _e); }

        const fd = new FormData(form);
        const hasId = !!(form.querySelector('input[name="id"]') && form.querySelector('input[name="id"]').value);
        if (hasId) {
            if (!fd.has('update')) fd.append('update','1');
        } else {
            if (!fd.has('create')) fd.append('create','1');
        }
        if (typeof syncSettingKey === 'string' && syncSettingKey !== '') {
            fd.append('sync_profile_setting', syncSettingKey);
        }
        try {
            const res = await fetch('core_profiles.php', { method:'POST', headers:{ 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            let json = {};
            try { json = await res.json(); } catch(_){ json = { ok:false, error:'Invalid response' }; }
            if (json && json.ok){
                if (json.id) {
                    // In case of create, set id and update URL
                    let idEl = form.querySelector('input[name="id"]');
                    if (!idEl){ idEl = document.createElement('input'); idEl.type='hidden'; idEl.name='id'; form.appendChild(idEl); }
                    idEl.value = String(json.id);
                    try { history.replaceState({}, '', 'core_profiles.php?edit='+encodeURIComponent(String(json.id))); } catch(_){ }
                }
                if (typeof showToast === 'function') {
                    const synced = Number(json.synced_profiles || 0);
                    const label = syncSettingLabel || 'Setting';
                    showToast(synced > 0 ? (label + ' copied to ' + synced + ' profiles') : 'Profile saved');
                }
            } else {
                if (typeof showToast === 'function') showToast('Save failed: ' + (json && json.error ? json.error : 'Unknown error'), true);
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('Save failed: ' + e.message, true);
        }
        return false;
    }

    // Save only profile basics (label, default flags, prompt)
    async function saveProfileBasics(){
        try {
            const form = document.getElementById('core_profile_form');
            if (!form) return;
            const idEl = form.querySelector('input[name="id"]');
            const pid = idEl ? (idEl.value||'') : '';
            if (!pid){ if (typeof showToast==='function') showToast('Save failed: create the profile first', true); return; }
            const label = (form.querySelector('input[name="label"]').value||'');
            const defNpc = !!(form.querySelector('input[type=\"checkbox\"][name=\"default_npc\"]').checked) ? '1' : '0';
            const prompt = (form.querySelector('textarea[name="prompt"]').value||'');
            const slotSel = form.querySelector('select[name="slot"]');
            const slotVal = slotSel ? (slotSel.value||'') : '';
            const fmtSel = form.querySelector('select[name="llm_formatter_id"]');
            const fmtVal = fmtSel ? (fmtSel.value||'') : '';
            const fd = new FormData();
            fd.append('update','1');
            fd.append('id', pid);
            fd.append('label', label);
            fd.append('default_npc', defNpc);
            fd.append('prompt', prompt);
            fd.append('slot', slotVal);
            if (fmtSel) fd.append('llm_formatter_id', fmtVal);
            const res = await fetch('core_profiles.php', { method:'POST', headers:{ 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            let json={}; try { json = await res.json(); } catch(_){ json = { ok:false, error:'Invalid response' }; }
            if (json && json.ok){
                if (typeof showToast==='function') showToast('Profile settings saved');
                try { updateLeftListBasics(label, defNpc==='1'); } catch(_e){}
            } else {
                if (typeof showToast==='function') showToast('Save failed: ' + (json && json.error ? json.error : 'Unknown error'), true);
            }
        } catch(e){ if (typeof showToast==='function') showToast('Save failed: ' + e.message, true); }
    }

    function updateLeftListBasics(newLabel, isDefaultNpc){
        const li = document.querySelector('.llm-left .conn-li[data-id="'+String(CURRENT_PROFILE_ID)+'"]');
        if (!li) return;
        const title = li.querySelector('.title');
        if (title) title.textContent = newLabel || title.textContent;
        const badges = li.querySelector('.pf-badges');
        if (badges){
            // Preserve existing NPC count badge if present
            const countBadge = Array.from(badges.children).find(el => /NPCs$/.test(el.textContent||''));
            badges.innerHTML='';
            if (countBadge) badges.appendChild(countBadge);
            if (isDefaultNpc){ const b=document.createElement('span'); b.className='pf-flag'; b.textContent='NPC'; badges.appendChild(b); }
        }
    }

    // Persist inline TTS/ITT editors and embedded LLM editors before saving profile
    async function saveAllConnectorEditors(){
        const tasks = [];
        function enqueueInline(containerId, selectId){
            const container = document.getElementById(containerId);
            const sel = document.getElementById(selectId);
            if (!container || !sel) return;
            const id = sel.value || '';
            if (!id) return;
            const fd = new FormData();
            fd.append('inline_update_connector','1');
            fd.append('id', id);
            container.querySelectorAll('input,select,textarea').forEach(inp=>{
                const n = inp.name; if (!n) return;
                if (inp.type === 'checkbox') fd.append(n, inp.checked ? '1':'0'); else fd.append(n, inp.value);
            });
            tasks.push(
                fetch('core_profiles.php', { method:'POST', body: fd })
                    .then(r=>r.json())
                    .then(j=>{ if (!j || j.ok!==true) throw new Error((j && j.error) || 'Inline save failed'); })
            );
        }
        // (Inline editors for TTS/ITT removed; now embedded full pages are used)
        // Attempt to trigger embedded LLM editor saves (if available)
        const frameIds = ['frame_llm_primary_id','frame_llm_secondary_id','frame_llm_tertiary_id','frame_llm_quaternary_id','frame_diary_connector_id','frame_llm_formatter_id','frame_llm_fallback_id'];
        frameIds.forEach(fid => {
            const f = document.getElementById(fid);
            try {
                if (f && f.contentWindow && typeof f.contentWindow.handleEmbeddedSave === 'function'){
                    const p = f.contentWindow.handleEmbeddedSave();
                    // If the child returned a promise, await it
                    if (p && typeof p.then === 'function') tasks.push(p);
                }
            } catch(_){ }
        });
        if (tasks.length>0){ await Promise.allSettled(tasks); }
    }
    // Connector details passed from PHP
    const LLM_DETAILS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const TTS_DETAILS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const ITT_DETAILS = <?= json_encode($ittById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const CURRENT_PROFILE_ID = <?= json_encode($editItem["id"] ?? '') ?>;

    function renderKVList(obj, keys, labels){
        if (!obj) return '<em style="color:#888">No connector selected.</em>';
        let html = '<div style="display:grid; grid-template-columns: 220px 1fr; gap:6px;">';
        for (let i=0;i<keys.length;i++){
            const k = keys[i], lab = labels[i];
            const val = (obj[k]===null||obj[k]===undefined||obj[k]==='' ) ? '<span style="color:#888">&mdash;</span>' : String(obj[k]);
            html += `<div style="color:rgb(242,124,17); font-weight:bold;">${lab}</div><div style="overflow-wrap:anywhere;">${val}</div>`;
        }
        html += '</div>';
        return html;
    }

    function renderEditor(containerId, conn, type){
        const container = document.getElementById(containerId);
        if (!container) return;
        if (!conn) { container.innerHTML = '<em style="color:#888">No connector selected.</em>'; return; }
        const bool = v => (v==1||v===true||v==='1') ? 'checked' : '';
        const val = k => (conn[k]===null||conn[k]===undefined? '' : String(conn[k]));
        if (type==='itt' || type==='diary'){
            container.innerHTML = `
                <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:6px; align-items:center;\">
                    <div>Label</div><input name=\"label\" value=\"${escapeHtml(val('label'))}\">
                    <div>Driver</div><input name=\"driver\" value=\"${escapeHtml(val('driver'))}\">
                    <div>Metadata</div><input name=\"metadata\" value=\"${escapeHtml(val('metadata'))}\">
                </div>
                <div style=\"margin-top:8px; display:flex; gap:8px;\">
                    <button type=\"button\" class=\"btn-save save\">Save</button>
                </div>
            `;
        } else if (type==='tts'){
            container.innerHTML = `
                <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:6px; align-items:center;\">
                    <div>Label</div><input name=\"label\" value=\"${escapeHtml(val('label'))}\">
                    <div>Driver</div><input name=\"driver\" value=\"${escapeHtml(val('driver'))}\">
                    <div>URL</div><input name=\"url\" value=\"${escapeHtml(val('url'))}\">
                    <div>Voice Field</div><input name=\"voice_field\" value=\"${escapeHtml(val('voice_field'))}\">
                    <div>API Badge</div>${renderApiBadgeSelect('api_badge_id', val('api_badge_id'))}
                </div>
                <div style=\"margin-top:8px; display:flex; gap:8px;\">
                    <button type=\"button\" class=\"btn-save save\">Save</button>
                </div>
            `;
        }
    }

    function renderApiBadgeSelect(name, selected){
        const options = <?= json_encode($apiBadgeRows ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        let html = `<select name="${name}"><option value="">-- Select API Badge --</option>`;
        options.forEach(o => {
            const sel = String(o.id) === String(selected) ? ' selected' : '';
            html += `<option value="${String(o.id)}"${sel}>${escapeHtml(o.label)}</option>`;
        });
        html += '</select>';
        return html;
    }

    function wireEditorSave(editorEl, id, type){
        const saveBtn = editorEl.querySelector('button.save');
        if (!saveBtn) return;
        saveBtn.addEventListener('click', async ()=>{
            const formData = new FormData();
            formData.append('inline_update_connector','1');
            formData.append('id', id);
            editorEl.querySelectorAll('input,select,textarea').forEach(inp=>{
                const n = inp.name; if (!n) return;
                if (inp.type==='checkbox') formData.append(n, inp.checked ? '1' : '0'); else formData.append(n, inp.value);
            });
            const res = await fetch('core_profiles.php', { method:'POST', body: formData });
            let json = {}; try { json = await res.json(); } catch(_){}
            if (!json.ok){ alert('Save failed: '+(json.error||res.status)); return; }
            const updated = Object.fromEntries(Array.from(formData.entries()).filter(([k])=>k!=='inline_update_connector'&&k!=='id'));
            // Merge back into correct cache
            if (type==='llm') Object.assign(LLM_DETAILS[id] = (LLM_DETAILS[id]||{}), updated);
            if (type==='tts') Object.assign(TTS_DETAILS[id] = (TTS_DETAILS[id]||{}), updated);
            if (type==='itt') Object.assign(ITT_DETAILS[id] = (ITT_DETAILS[id]||{}), updated);
            // No separate preview; editor is the source of truth
        });
    }

    function refreshEditorFor(selectId, containerId, type){
        const sel = document.getElementById(selectId);
        const id = sel ? (sel.value||'') : '';
        let conn = null;
        if (type==='tts') conn = TTS_DETAILS[id];
        else if (type==='itt') conn = ITT_DETAILS[id];
        renderEditor(containerId, conn, type);
        const editorEl = document.getElementById(containerId);
        if (editorEl) wireEditorSave(editorEl, id, type);
    }

    // Embedded LLM editor via iframe (mirrors connectors UI 1:1)
    function refreshEmbeddedEditor(selectId, frameId){
        const sel = document.getElementById(selectId);
        const frame = document.getElementById(frameId);
        if (!frame) return;
        const id = sel ? (sel.value||'') : '';
        if (!id) { frame.src = 'about:blank'; return; }
        const base = <?= json_encode($webRoot) ?> + '/ui/core/llm_connectors.php?partial=editor&edit=' + encodeURIComponent(id);
        frame.src = base;
    }

    function initInlineEditors(){
        
        refreshEmbeddedEditor('diary_connector_id','frame_diary_connector_id');
        refreshEmbeddedEditor('llm_primary_id','frame_llm_primary_id');
        refreshEmbeddedEditor('llm_secondary_id','frame_llm_secondary_id');
        refreshEmbeddedEditor('llm_tertiary_id','frame_llm_tertiary_id');
        refreshEmbeddedEditor('llm_quaternary_id','frame_llm_quaternary_id');
        refreshEmbeddedEditor('llm_formatter_id','frame_llm_formatter_id');
        refreshEmbeddedEditor('llm_fallback_id','frame_llm_fallback_id');

        ['diary_connector_id','llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id','llm_formatter_id','llm_fallback_id'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', ()=>{
                if (id==='diary_connector_id') refreshEmbeddedEditor(id,'frame_diary_connector_id');
                else if (id==='llm_primary_id') refreshEmbeddedEditor(id,'frame_llm_primary_id');
                else if (id==='llm_secondary_id') refreshEmbeddedEditor(id,'frame_llm_secondary_id');
                else if (id==='llm_tertiary_id') refreshEmbeddedEditor(id,'frame_llm_tertiary_id');
                else if (id==='llm_quaternary_id') refreshEmbeddedEditor(id,'frame_llm_quaternary_id');
                else if (id==='llm_formatter_id') refreshEmbeddedEditor(id,'frame_llm_formatter_id');
                else if (id==='llm_fallback_id') refreshEmbeddedEditor(id,'frame_llm_fallback_id');
            });
        });

        function updateLeftList(connectorField, connId){
            const li = document.querySelector('.llm-left .conn-li[data-id="'+String(CURRENT_PROFILE_ID)+'"]');
            if (!li) return;
            let label = '';
            if (connectorField==='tts_connector_id') label = (TTS_DETAILS[connId] && (TTS_DETAILS[connId].label||'')) || '';
            else if (connectorField==='itt_connector_id') label = (ITT_DETAILS[connId] && (ITT_DETAILS[connId].label||'')) || '';
            else label = (LLM_DETAILS[connId] && (LLM_DETAILS[connId].label||'')) || '';
            let key = '';
            if (connectorField==='llm_primary_id') key = 'LLM Standard';
            else if (connectorField==='llm_secondary_id') key = 'LLM Fast';
            else if (connectorField==='llm_tertiary_id') key = 'LLM Powerful';
            else if (connectorField==='llm_quaternary_id') key = 'LLM Experimental';
            else if (connectorField==='tts_connector_id') key = 'TTS Connector';
            else if (connectorField==='itt_connector_id') key = 'ITT';
            else if (connectorField==='diary_connector_id') key = 'Diary';
            else if (connectorField==='llm_formatter_id') key = 'Formatter LLM';
            if (!key) return;
            const lines = li.querySelectorAll('.pf-line');
            lines.forEach(line=>{
                const k = line.querySelector('.pf-key');
                const v = line.querySelector('.pf-val');
                if (k && v && (k.textContent||'').trim()===key){ v.textContent = label || '&mdash;'; }
            });
        }

        document.querySelectorAll('.btn-apply[data-apply-select]').forEach(btn => {
            btn.addEventListener('click', ()=>{
                const selId = btn.getAttribute('data-apply-select');
                const sel = document.getElementById(selId);
                if (!sel) return;
                const value = sel.value || '';
                const formData = new FormData();
                formData.append('inline_update_profile','1');
                formData.append('id', <?= json_encode($editItem["id"] ?? 0) ?>);
                formData.append('field', selId);
                formData.append('value', value);
                fetch('core_profiles.php', { method:'POST', body: formData })
                    .then(r=>r.json()).then(json=>{
                        if (json && json.ok) {
                            showToast('Profile updated');
                            updateLeftList(selId, String(value));
                            if (selId==='llm_primary_id') refreshEmbeddedEditor(selId,'frame_llm_primary_id');
                            else if (selId==='llm_secondary_id') refreshEmbeddedEditor(selId,'frame_llm_secondary_id');
                            else if (selId==='llm_tertiary_id') refreshEmbeddedEditor(selId,'frame_llm_tertiary_id');
                            else if (selId==='llm_quaternary_id') refreshEmbeddedEditor(selId,'frame_llm_quaternary_id');
                            else if (selId==='diary_connector_id') refreshEmbeddedEditor(selId,'frame_diary_connector_id');
                            else if (selId==='llm_formatter_id') refreshEmbeddedEditor(selId,'frame_llm_formatter_id');
                            else if (selId==='llm_fallback_id') refreshEmbeddedEditor(selId,'frame_llm_fallback_id');
                        } else {
                            showToast('Update failed: ' + (json && json.error ? json.error : 'Unknown error'), true);
                        }
                    }).catch(e=>{
                        showToast('Update failed: ' + e.message, true);
                    });
            });
        });

        // Tabs wiring
        const tabs = document.querySelectorAll('.pf-tab');
        tabs.forEach(tb=>tb.addEventListener('click', ()=>{
            const target = tb.getAttribute('data-pane');
            document.querySelectorAll('.pf-tab').forEach(t=>t.classList.remove('active'));
            document.querySelectorAll('.pf-pane').forEach(p=>p.classList.remove('active'));
            tb.classList.add('active');
            const pane = document.getElementById(target);
            if (pane) pane.classList.add('active');
        }));
    }

    document.addEventListener('DOMContentLoaded', initInlineEditors);
    
    // Handle postMessage from embedded LLM connector iframes
    window.addEventListener('message', async function(event) {
        if (event.data && event.data.type === 'llm_connector_save') {
            try {
                const formData = new FormData();
                for (const [key, value] of Object.entries(event.data.data)) {
                    formData.append(key, value);
                }
                
                const response = await fetch('core_profiles.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                let result = {};
                try {
                    result = await response.json();
                } catch (e) {
                    result = { ok: false, error: 'Invalid response' };
                }
                
                // Send result back to iframe
                event.source.postMessage({
                    type: 'llm_connector_save_result',
                    success: result.ok === true,
                    error: result.error || null
                }, '*');
                
                // Show toast notification in parent
                if (result.ok === true) {
                    showToast('LLM connector saved successfully');
                    // Reload the originating iframe to reflect saved values
                    try {
                        const frames = document.querySelectorAll('iframe');
                        frames.forEach(f => { if (f && f.contentWindow === event.source) { f.src = f.src; } });
                    } catch(_e){}
                } else {
                    showToast('Save failed: ' + (result.error || 'Unknown error'), true);
                }
                
            } catch (error) {
                // Send error back to iframe
                event.source.postMessage({
                    type: 'llm_connector_save_result',
                    success: false,
                    error: error.message
                }, '*');
                
                showToast('Save failed: ' + error.message, true);
            }
        }
    });
    
    // Toast notification function
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        
        const messageEl = toast.querySelector('.message');
        if (messageEl) messageEl.textContent = message;
        
        toast.className = 'toast-notification show' + (isError ? ' error' : '');
        setTimeout(() => {
            toast.className = 'toast-notification';
        }, 3000);
    }
    
    // Collapse metadata by default and trigger resize like connectors
    (function(){
        var d = document.getElementById('metadata_section');
        if (!d) return;
        try { d.open = false; } catch(e){}
        d.addEventListener('toggle', function(){
            if (d.open) {
                try { window.dispatchEvent(new Event('resize')); } catch(e){}
                try { setTimeout(function(){ window.dispatchEvent(new Event('resize')); }, 50); } catch(e){}
            }
        });
    })();

    // Inline editor for LLM connectors
    function buildInlineEditorHTML(conn){
        if (!conn) return '<em style="color:#888">No connector selected.</em>';
        const bool = v => (v==1||v===true||v==='1') ? 'checked' : '';
        const val = k => (conn[k]===null||conn[k]===undefined? '' : String(conn[k]));
        return `
            <div style="display:grid; grid-template-columns: 80px 1fr; gap:6px; align-items:center;">
                <div>Label</div><input name="label" value="${escapeHtml(val('label'))}">
                <div>URL</div><input name="url" value="${escapeHtml(val('url'))}">
                <div>Provider</div><input name="provider" value="${escapeHtml(val('provider'))}">
                <div>Model</div><input name="model" value="${escapeHtml(val('model'))}">
                <div>Driver</div><input name="driver" value="${escapeHtml(val('driver'))}">
                <div>Temperature</div><input name="temperature" type="number" step="0.01" value="${escapeHtml(val('temperature'))}">
                <div>Max Tokens</div><input name="max_tokens" type="number" step="1" value="${escapeHtml(val('max_tokens'))}">
                <div>Presence Penalty</div><input name="presence_penalty" type="number" step="0.01" value="${escapeHtml(val('presence_penalty'))}">
                <div>Frequency Penalty</div><input name="frequency_penalty" type="number" step="0.01" value="${escapeHtml(val('frequency_penalty'))}">
                <div>Repetition Penalty</div><input name="repetition_penalty" type="number" step="0.01" value="${escapeHtml(val('repetition_penalty'))}">
                <div>top_p</div><input name="top_p" type="number" step="0.01" value="${escapeHtml(val('top_p'))}">
                <div>top_k</div><input name="top_k" type="number" step="1" value="${escapeHtml(val('top_k'))}">
                <div>min_p</div><input name="min_p" type="number" step="0.01" value="${escapeHtml(val('min_p'))}">
                <div>top_a</div><input name="top_a" type="number" step="0.01" value="${escapeHtml(val('top_a'))}">
                <div>Enforce JSON</div><input name="enforce_json" type="checkbox" value="1" ${bool(conn.enforce_json)}>
                <div>Prefill JSON</div><input name="prefill_json" type="checkbox" value="1" ${bool(conn.prefill_json)}>
                <div>Reasoning Model</div><input name="reasoning_model" type="checkbox" value="1" ${bool(conn.reasoning_model)}>
                <div>JSON Schema</div><input name="json_schema" type="checkbox" value="1" ${bool(conn.json_schema)}>
            </div>
            <div style="margin-top:8px; display:flex; gap:8px;">
                <button type="button" class="btn-save save">Save</button>
                <button type="button" class="btn-secondary cancel">Cancel</button>
            </div>
        `;
    }

    function escapeHtml(s){
        return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    }

    function openInlineEditor(selectId){
        const sel = document.getElementById(selectId);
        const editor = document.getElementById('inline_'+selectId);
        if (!sel || !editor) return;
        const id = sel.value || '';
        const conn = LLM_DETAILS[id];
        editor.innerHTML = buildInlineEditorHTML(conn);
        editor.style.display = 'block';

        const saveBtn = editor.querySelector('button.save');
        const cancelBtn = editor.querySelector('button.cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', ()=>{ editor.style.display='none'; editor.innerHTML=''; });
        if (saveBtn) saveBtn.addEventListener('click', async ()=>{
            const formData = new FormData();
            formData.append('inline_update_connector','1');
            formData.append('id', id);
            editor.querySelectorAll('input').forEach(inp=>{
                const n = inp.name; if (!n) return;
                if (inp.type==='checkbox') formData.append(n, inp.checked ? '1' : '0'); else formData.append(n, inp.value);
            });
            const res = await fetch('core_profiles.php', { method:'POST', body: formData });
            let json = {};
            try { json = await res.json(); } catch (_) {}
            if (!json.ok){ alert('Save failed: '+(json.error||res.status)); return; }
            // Update local cache and preview
            const updated = Object.fromEntries(Array.from(formData.entries()).filter(([k])=>k!=='inline_update_connector'&&k!=='id'));
            Object.assign(LLM_DETAILS[id] = (LLM_DETAILS[id]||{}), updated);
            updatePreview(selectId, 'preview_'+selectId, 'llm');
            editor.style.display='none'; editor.innerHTML='';
        });
    }

    document.querySelectorAll('button[data-edit-for]').forEach(btn=>{
        btn.addEventListener('click', ()=> openInlineEditor(btn.getAttribute('data-edit-for')));
    });
    </script>
    </script>

    </form>
    </div>
    </div>


<?php /* bottom table removed; use left list instead */ ?>

<!-- Profile Rules Modal -->
<div id="import_rules_modal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Profile Rules</h2>
            <div class="modal-actions">
                <button type="button" id="add_rule_btn" class="btn-save" style="margin-right: 10px;">+ New Rule</button>
                <button type="button" class="modal-close" id="close_rules_modal">Close</button>
            </div>
        </div>
        <div class="modal-body" style="padding: 16px; max-height: calc(85vh - 100px); overflow-y: auto;">
            <div class="connector-help rule-help">
                <strong>Profile Rules automatically assign profiles when NPCs are first activated.</strong>
                <span>Choose one or more values inside a field to match any of them. Different fields must all match. Use Advanced Rules only for custom regex, base IDs, priority, or metadata actions.</span>
            </div>
            <div id="rules_list" style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Rules will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Profile Import Modal -->
<div id="import_profile_modal" class="modal-backdrop">
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title">Import Profile</h2>
            <div class="modal-actions">
                <button type="button" class="modal-close" id="close_import_modal">Close</button>
            </div>
        </div>
        <div class="modal-body" style="padding: 16px;">
            <div class="connector-help" style="margin-bottom: 16px; padding: 12px; background: #1a1a1a; border: 1px solid #4a4a4a; border-radius: 8px;">
                <strong>About Profile Import:</strong>
                <ul style="margin: 6px 0 0 16px; padding: 0;">
                    <li><strong>Connectors:</strong> Referenced connectors will be created or matched by label+driver</li>
                    <li><strong>API Keys:</strong> API keys are NOT included in exports for security.</li>
                    <li><strong>Settings:</strong> All profile settings and metadata will be imported</li>
                </ul>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label for="import_file" style="display: block; font-weight: 700; color: rgb(242, 124, 17); margin-bottom: 8px;">
                    Select Profile Export File (JSON)
                </label>
                <input type="file" id="import_file" accept=".json" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #4a4a4a; border-radius: 6px; color: #e9efff; cursor: pointer;">
            </div>

            <div style="margin-bottom: 16px; padding: 12px; background: #141414; border: 1px solid #3a3a3a; border-radius: 8px;">
                <div style="font-weight: 700; color: rgb(242, 124, 17); margin-bottom: 8px;">Import Assignment Options</div>
                <label style="display:flex; gap:8px; align-items:flex-start; margin-bottom: 8px; cursor:pointer;">
                    <input type="checkbox" id="import_make_default_npc" value="1" style="margin-top: 3px;">
                    <span>Make Default Profile</span>
                </label>
                <label style="display:flex; gap:8px; align-items:flex-start; margin-bottom: 8px; cursor:pointer;">
                    <input type="checkbox" id="import_migrate_old_default_npcs" value="1" style="margin-top: 3px;">
                    <span>Move current default NPCs to this profile</span>
                </label>
                <label for="import_assign_slot" style="display:block; font-weight: 700; margin-bottom: 6px;">Assign quick slot</label>
                <select id="import_assign_slot" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #4a4a4a; border-radius: 6px; color: #e9efff;">
                    <option value="">Do not assign a slot</option>
                    <option value="1">Slot 1</option>
                    <option value="2">Slot 2</option>
                    <option value="3">Slot 3</option>
                    <option value="4">Slot 4</option>
                </select>
                <div style="color:#9fb1c9; font-size:12px; margin-top:6px;">If a slot is already used, importing with that slot will move the old profile out of the slot.</div>
            </div>
            
            <div id="import_preview" style="display: none; margin-bottom: 16px;">
                <div style="font-weight: 700; color: rgb(242, 124, 17); margin-bottom: 8px;">Preview:</div>
                <div id="import_preview_content" style="background: #1a1a1a; border: 1px solid #4a4a4a; border-radius: 6px; padding: 12px; max-height: 300px; overflow-y: auto; font-size: 13px; color: #e9efff;">
                </div>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" id="cancel_import_btn">Cancel</button>
                <button type="button" class="btn-save" id="confirm_import_btn" disabled>Import Profile</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal styling for Profile Import */
#import_profile_modal.modal-backdrop { display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.75); z-index:10000; }
#import_profile_modal.modal-backdrop.show { display:block; }
#import_profile_modal.modal-backdrop { opacity: 1 !important; backdrop-filter: none !important; filter: none !important; }
#import_profile_modal .modal-container {
    position:fixed;
    left:50%;
    top:50%;
    transform:translate(-50%, -50%);
    background:#2a2a2a;
    border:1px solid #4a4a4a;
    border-radius:10px;
    width: min(95vw, 700px);
    max-height: 90vh;
    overflow: hidden;
    z-index: 10001;
}
#import_profile_modal .modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; }
#import_profile_modal .modal-title { margin:0; font-weight:700; color: rgb(242, 124, 17); font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.6em; }
#import_profile_modal .modal-body { background:#2a2a2a; overflow:auto; max-height: calc(90vh - 60px); }
#import_profile_modal .modal-close { background:#3a3a3a; color:#fff; border:1px solid rgba(138,155,182,.35); border-radius:8px; padding:8px 14px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; }
#import_profile_modal .modal-close:hover { background:#4a4a4a; }
</style>

<style>
/* Modal styling for Profile Rules */
#import_rules_modal.modal-backdrop { display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.75); z-index:10000; }
#import_rules_modal.modal-backdrop.show { display:block; }
#import_rules_modal.modal-backdrop { opacity: 1 !important; backdrop-filter: none !important; filter: none !important; }
#import_rules_modal .modal-container {
    position:fixed;
    left:50%;
    top:50%;
    transform:translate(-50%, -50%);
    background:#2a2a2a;
    border:1px solid #4a4a4a;
    border-radius:10px;
    width: min(95vw, 1400px);
    max-height: 90vh;
    overflow: hidden;
    z-index: 10001;
}
#import_rules_modal .modal-header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; position:sticky; top:0; z-index:2; }
#import_rules_modal .modal-title { margin:0; font-weight:700; color: rgb(242, 124, 17); font-family: 'MagicCards', serif; word-spacing: 8px; font-size: 2em; }
#import_rules_modal .modal-body { background:#2a2a2a; overflow:auto; max-height: calc(90vh - 68px); font-family:'Futura CondensedLight', Arial, sans-serif; font-size:16px; }
#import_rules_modal .modal-close { background:#3a3a3a; color:#fff; border:1px solid rgba(138,155,182,.35); border-radius:8px; padding:10px 16px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:7px; font-size:15px; font-weight:700; }
#import_rules_modal .modal-close:hover { background:#4a4a4a; }
#import_rules_modal .modal-actions { display:flex; gap:8px; align-items:center; }

/* Rule card styling */
.rule-help { display:flex; gap:10px; align-items:baseline; margin-bottom:18px; padding:14px 16px; background:#202020; border:1px solid #424242; border-radius:8px; color:#cfd5df; font-size:15px; line-height:1.45; }
.rule-help strong { color:#fff; white-space:nowrap; }
.rule-card { background: #242424; border: 1px solid #454545; border-radius: 8px; padding: 16px; }
.rule-card.editing { border-color: rgb(242, 124, 17); }
.rule-header { display: flex; justify-content: space-between; align-items: center; gap:12px; }
.rule-title-row { display:flex; align-items:center; flex-wrap:wrap; gap:8px; min-width:0; }
.rule-title { font-family:'Futura CondensedLight', Arial, sans-serif; font-weight:700; color:#fff; font-size:20px; letter-spacing:.2px; }
.rule-actions { display: flex; gap: 6px; }
.rule-status, .rule-advanced-badge { border:1px solid #505050; border-radius:999px; padding:3px 9px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.rule-status.enabled { color:#8ee2aa; border-color:#2f7749; background:#193323; }
.rule-status.disabled { color:#aaa; }
.rule-advanced-badge { color:#ffbf76; border-color:#795122; background:#382613; }
.rule-summary { display:flex; align-items:center; flex-wrap:wrap; gap:7px; margin-top:11px; color:#cfd5df; font-size:14px; }
.rule-summary-target { color:#fff; font-weight:700; margin-right:4px; }
.rule-chip { display:inline-flex; align-items:center; gap:4px; border:1px solid #4b4b4b; background:#1c1c1c; border-radius:999px; padding:4px 9px; font-size:13px; }
.rule-edit-core { display:grid; grid-template-columns:minmax(240px, 1.4fr) minmax(240px, 1fr) auto; gap:14px; align-items:end; margin-top:14px; }
.rule-field label, .rule-picker-label { display:block; color:#d5dbe4; font-size:14px; font-weight:700; margin-bottom:7px; text-transform:uppercase; letter-spacing:.04em; }
.rule-picker-label { display:flex; align-items:center; gap:7px; }
.rule-picker-icon { font-size:17px; line-height:1; text-transform:none; }
.rule-enabled-field { min-width:92px; padding-bottom:7px; }
.rule-enabled-field label { display:flex; align-items:center; gap:9px; margin:0; color:#fff; text-transform:none; font-size:15px; }
.rule-simple-heading { margin:18px 0 10px; color:#fff; font-family:'MagicCards', serif; font-size:20px; word-spacing:5px; }
.rule-simple-note { color:#aab3c0; font-family:'Futura CondensedLight', Arial, sans-serif; font-size:13px; font-weight:400; margin-left:7px; word-spacing:normal; }
.rule-simple-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
.rule-picker { background:#1d1d1d; border:1px solid #404040; border-radius:8px; padding:12px; min-width:0; }
.rule-picker-controls { display:flex; gap:6px; }
.rule-picker-select, .rule-picker-custom { min-width:0; width:100%; background:#343434; border:1px solid #555; border-radius:6px; color:#f8f9fa; padding:9px 10px; font-family:'Futura CondensedLight', Arial, sans-serif; font-size:15px; }
.rule-picker-select { cursor:pointer; }
.rule-picker-custom:focus, .rule-picker-select:focus { border-color:rgb(242, 124, 17); outline:none; }
.rule-picker-tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:9px; min-height:24px; }
.rule-picker-empty { color:#818b99; font-size:13px; padding:3px 0; }
.rule-picker-tag { display:inline-flex; align-items:center; gap:6px; border:1px solid #5a4a35; background:#30271d; color:#f2d1aa; border-radius:999px; padding:4px 9px; font-size:13px; }
.rule-picker-remove { border:0; background:transparent; color:#f2d1aa; cursor:pointer; padding:0; line-height:1; }
.rule-custom-row { display:flex; gap:7px; margin-top:7px; }
.rule-small-btn, .rule-advanced-toggle { background:#333; color:#fff; border:1px solid #505050; border-radius:7px; padding:8px 12px; cursor:pointer; font-size:14px; font-weight:700; white-space:nowrap; }
.rule-small-btn:hover, .rule-advanced-toggle:hover { background:#414141; }
.rule-advanced-toggle { margin-top:14px; }
.rule-advanced-panel { display:none; margin-top:12px; padding:14px; border:1px solid #51422e; border-radius:8px; background:#201d19; }
.rule-advanced-panel.open { display:block; }
.rule-advanced-note { color:#d3b995; font-size:14px; line-height:1.4; margin-bottom:12px; }
.rule-grid { display: grid; grid-template-columns: 180px 1fr; gap: 8px 12px; align-items: start; }
.rule-label { color:#c9d0da; font-size:14px; font-weight:700; padding-top:9px; }
.rule-value { color: #e9efff; }
.rule-value.empty { color: #666; font-style: italic; }
.rule-input { width:100%; background:#343434; border:1px solid #555; border-radius:6px; padding:9px 10px; color:#f8f9fa; font-family:'Futura CondensedLight', Arial, sans-serif; font-size:15px; }
.rule-input:focus { border-color: rgb(242, 124, 17); outline: none; }
.rule-input.json { font-family: monospace; min-height: 60px; }
.rule-checkbox { accent-color: #2f9e58; transform: scale(1.2); cursor: pointer; }
.btn-rule-edit, .btn-rule-save, .btn-rule-cancel, .btn-rule-delete { color:#fff; border:1px solid rgba(138,155,182,.35); border-radius:8px; padding:9px 14px; cursor:pointer; font-weight:700; font-size:14px; display:inline-flex; align-items:center; justify-content:center; gap:7px; }
.btn-rule-edit { background:#204e7a; }
.btn-rule-edit:hover { background:#285c8f; }
.btn-rule-save { background:#247a49; }
.btn-rule-save:hover { background:#2c9258; }
.btn-rule-cancel { background:#3a3a3a; }
.btn-rule-cancel:hover { background:#4a4a4a; }
.btn-rule-delete { background: #8b0000; border-color: #660000; }
.btn-rule-delete:hover { background: #a00000; }
@media (max-width: 850px) {
    .rule-help { display:block; }
    .rule-help strong { display:block; margin-bottom:4px; white-space:normal; }
    .rule-edit-core, .rule-simple-grid { grid-template-columns:1fr; }
    .rule-grid { grid-template-columns:1fr; }
    .rule-label { padding-top:2px; }
}
</style>

<script>
(function() {
    const modal = document.getElementById('import_rules_modal');
    const openBtn = document.getElementById('open_import_rules_btn');
    const closeBtn = document.getElementById('close_rules_modal');
    const addBtn = document.getElementById('add_rule_btn');
    const rulesList = document.getElementById('rules_list');
    
    const PROFILES = <?= json_encode($data ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    
    let rulesData = [];
    let editingId = null;
    let editorOptions = { names: [], races: [], genders: [], factions: [], mods: [] };
    
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    
    function openModal() {
        modal.classList.add('show');
        loadRules();
    }
    
    function closeModal() {
        modal.classList.remove('show');
        editingId = null;
    }

    function parsePgTextArray(value) {
        if (Array.isArray(value)) return value;
        if (typeof value !== 'string') return [];
        const source = value.trim();
        if (!(source.startsWith('{') && source.endsWith('}'))) return [];
        const body = source.slice(1, -1);
        if (!body) return [];

        try {
            const parsed = JSON.parse('[' + body + ']');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_error) {
            const values = [];
            let current = '';
            let quoted = false;
            let escaped = false;
            for (const character of body) {
                if (escaped) {
                    current += character;
                    escaped = false;
                } else if (character === '\\') {
                    escaped = true;
                } else if (character === '"') {
                    quoted = !quoted;
                } else if (character === ',' && !quoted) {
                    values.push(current.trim());
                    current = '';
                } else {
                    current += character;
                }
            }
            if (current !== '') values.push(current.trim());
            return values.filter(Boolean);
        }
    }

    function ruleMods(rule) {
        return parsePgTextArray(rule.match_mods);
    }

    function simpleValues(rule, key) {
        return Array.isArray(rule?._simple?.[key]) ? rule._simple[key] : [];
    }

    function buildExactRegex(values) {
        const unique = Array.from(new Set((values || []).map(value => String(value).trim()).filter(Boolean)));
        if (!unique.length) return '';
        const escaped = unique.map(value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        return `^(?:${escaped.join('|')})$`;
    }

    function renderProfileOptions(selectedValue) {
        let options = '<option value="">-- Select Profile --</option>';
        PROFILES.forEach(profile => {
            const selected = String(profile.id) === String(selectedValue) ? ' selected' : '';
            options += `<option value="${escapeHtml(profile.id)}"${selected}>${escapeHtml(profile.label || 'Profile #' + profile.id)}</option>`;
        });
        return options;
    }

    function renderPicker(pickerId, name, label, selectedValues, availableValues, customHint, note = '', icon = '', explicitDropdown = false) {
        const selected = Array.from(new Set((selectedValues || []).map(String).filter(Boolean)));
        const available = Array.from(new Set([...(availableValues || []), ...selected]))
            .filter(Boolean)
            .sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));
        const remaining = available
            .filter(value => !selected.includes(value))
        const choices = remaining.map(value => `<option value="${escapeHtml(value)}"></option>`).join('');
        const selectOptions = remaining.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
        const tags = selected.length
            ? selected.map(value => renderPickerTag(value)).join('')
            : '<span class="rule-picker-empty">Any</span>';
        const listId = `rule-options-${String(pickerId).replace(/[^a-zA-Z0-9_-]/g, '-')}-${name}`;
        const inputControls = explicitDropdown
            ? `<div class="rule-picker-controls">
                    <select class="rule-picker-select" aria-label="Select detected ${escapeHtml(label)}">
                        <option value="">Select a detected ${escapeHtml(label.toLowerCase())}</option>
                        ${selectOptions}
                    </select>
                    <button type="button" class="rule-small-btn" data-action="add-selected">＋ Add</button>
               </div>
               <div class="rule-custom-row">
                    <input type="text" class="rule-picker-custom" aria-label="Type custom ${escapeHtml(label)}" placeholder="${escapeHtml(customHint)}">
                    <button type="button" class="rule-small-btn" data-action="add-custom">＋ Add Typed</button>
               </div>`
            : `<div class="rule-custom-row">
                    <input type="text" class="rule-picker-custom" list="${listId}" aria-label="${escapeHtml(customHint)}" placeholder="${escapeHtml(customHint)}">
                    <button type="button" class="rule-small-btn" data-action="add-custom">＋ Add</button>
               </div>
               <datalist id="${listId}">${choices}</datalist>`;

        return `<div class="rule-picker" data-picker-name="${name}">
            <span class="rule-picker-label">${icon ? `<span class="rule-picker-icon" aria-hidden="true">${icon}</span>` : ''}${escapeHtml(label)}${note ? `<span class="rule-simple-note">(${escapeHtml(note)})</span>` : ''}</span>
            ${inputControls}
            <div class="rule-picker-tags">${tags}</div>
        </div>`;
    }

    function renderPickerTag(value) {
        return `<span class="rule-picker-tag" data-value="${escapeHtml(value)}">
            <span>${escapeHtml(value)}</span>
            <button type="button" class="rule-picker-remove" data-action="remove-tag" title="Remove">&times;</button>
        </span>`;
    }

    function renderAdvancedField(label, name, value, type = 'text') {
        const normalized = value && typeof value === 'object'
            ? JSON.stringify(value, null, 2)
            : (value || '');
        const control = type === 'json'
            ? `<textarea class="rule-input rule-input-${name} json">${escapeHtml(normalized)}</textarea>`
            : `<input type="${type}" class="rule-input rule-input-${name}" value="${escapeHtml(normalized)}">`;
        return `<div class="rule-label">${escapeHtml(label)}</div><div>${control}</div>`;
    }

    function renderSummary(rule, profileLabel) {
        const chips = [];
        if (rule._has_custom_regex) {
            chips.push('<span class="rule-chip">Custom advanced matching</span>');
        } else {
            [
                ['Name', simpleValues(rule, 'name')],
                ['Race', simpleValues(rule, 'race')],
                ['Gender', simpleValues(rule, 'gender')],
                ['Faction', simpleValues(rule, 'faction')],
                ['Requires mods', ruleMods(rule)],
            ].forEach(([label, values]) => {
                if (values.length) {
                    chips.push(`<span class="rule-chip"><strong>${escapeHtml(label)}:</strong> ${escapeHtml(values.join(' or '))}</span>`);
                }
            });
        }
        if (!chips.length) chips.push('<span class="rule-chip">All NPCs</span>');

        return `<div class="rule-summary">
            <span class="rule-summary-target">${escapeHtml(profileLabel || 'No profile assigned')}</span>
            ${chips.join('')}
        </div>`;
    }

    async function loadRules() {
        try {
            const res = await fetch('core_profiles.php?get_import_rules=1');
            const json = await res.json();
            if (json.ok && json.data) {
                rulesData = json.data;
                editorOptions = Object.assign(editorOptions, json.options || {});
                renderRules();
            } else {
                showToast(json.error || 'Failed to load profile rules', true);
            }
        } catch (e) {
            console.error('Failed to load rules:', e);
            showToast('Failed to load profile rules', true);
        }
    }

    function renderRules() {
        if (rulesData.length === 0) {
            rulesList.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">No profile rules yet. Click "New Rule" to create one.</div>';
            return;
        }

        let html = '';
        rulesData.forEach(rule => {
            const isEditing = String(editingId) === String(rule.id);
            const profileLabel = PROFILES.find(p => String(p.id) === String(rule.profile))?.label || '';
            const enabled = rule.enabled === true || rule.enabled === 't' || rule.enabled === '1' || rule.enabled === 1;
            const editorMode = rule._editor_mode || (rule._has_custom_regex ? 'advanced' : 'simple');
            const isAdvanced = editorMode === 'advanced';

            html += `<div class="rule-card${isEditing ? ' editing' : ''}" data-id="${rule.id}" data-editor-mode="${editorMode}">
                <div class="rule-header">
                    <div class="rule-title-row">
                        <div class="rule-title">${escapeHtml(rule.description || 'Untitled Rule')}</div>
                        <span class="rule-status ${enabled ? 'enabled' : 'disabled'}">${enabled ? 'Enabled' : 'Disabled'}</span>
                        ${rule._has_custom_regex ? '<span class="rule-advanced-badge">Advanced</span>' : ''}
                    </div>
                    <div class="rule-actions">
                        ${!isEditing ? `
                            <button type="button" class="btn-rule-edit" data-action="edit">✎ Edit</button>
                            <button type="button" class="btn-rule-delete" data-action="delete">× Delete</button>
                        ` : `
                            <button type="button" class="btn-rule-save" data-action="save">✓ Save</button>
                            <button type="button" class="btn-rule-cancel" data-action="cancel">× Cancel</button>
                        `}
                    </div>
                </div>
                ${!isEditing ? renderSummary(rule, profileLabel) : `
                    <div class="rule-edit-core">
                        <div class="rule-field">
                            <label>Rule Name</label>
                            <input type="text" class="rule-input rule-input-description" value="${escapeHtml(rule.description || '')}" required>
                        </div>
                        <div class="rule-field">
                            <label>Assign Profile</label>
                            <select class="rule-input rule-input-profile">${renderProfileOptions(rule.profile || '')}</select>
                        </div>
                        <div class="rule-field rule-enabled-field">
                            <label><input type="checkbox" class="rule-checkbox rule-input-enabled"${enabled ? ' checked' : ''}> Enabled</label>
                        </div>
                    </div>
                    <div class="rule-simple-section"${isAdvanced ? ' style="display:none;"' : ''}>
                        <div class="rule-simple-heading">Match NPCs When <span class="rule-simple-note">Any value inside a field; all populated fields must match.</span></div>
                        <div class="rule-simple-grid">
                            ${renderPicker(rule.id, 'name', 'NPC Name', simpleValues(rule, 'name'), editorOptions.names, 'Search or enter an NPC name', '', '👤')}
                            ${renderPicker(rule.id, 'race', 'Race', simpleValues(rule, 'race'), editorOptions.races, 'Search or enter a race', '', '🧬')}
                            ${renderPicker(rule.id, 'gender', 'Gender', simpleValues(rule, 'gender'), editorOptions.genders, 'Search or enter a gender', '', '⚧')}
                            ${renderPicker(rule.id, 'faction', 'Faction', simpleValues(rule, 'faction'), editorOptions.factions, 'Type a custom faction name', '', '⚔', true)}
                            ${renderPicker(rule.id, 'mods', 'Source Mods', ruleMods(rule), editorOptions.mods, 'Type PluginName.esp', 'all selected are required', '🧩', true)}
                        </div>
                        <button type="button" class="rule-advanced-toggle" data-action="open-advanced">⚙ Advanced Rules</button>
                    </div>
                    <div class="rule-advanced-panel${isAdvanced ? ' open' : ''}">
                        <div class="rule-advanced-note">Raw regex and action fields are for advanced users. Blank match fields match every NPC. Higher priority rules override lower priority rules.</div>
                        <div class="rule-grid">
                            ${renderAdvancedField('Name Regex', 'match_name', rule.match_name)}
                            ${renderAdvancedField('Race Regex', 'match_race', rule.match_race)}
                            ${renderAdvancedField('Gender Regex', 'match_gender', rule.match_gender)}
                            ${renderAdvancedField('Base / RefID Regex', 'match_base', rule.match_base)}
                            ${renderAdvancedField('Faction Regex', 'match_faction', rule.match_faction)}
                            ${renderAdvancedField('Required Mods', 'match_mods', ruleMods(rule).join(', '))}
                            ${renderAdvancedField('Priority', 'priority', rule.priority || 0, 'number')}
                            ${renderAdvancedField('Action JSON', 'action', rule.action, 'json')}
                        </div>
                    </div>
                `}
            </div>`;
        });

        rulesList.innerHTML = html;
    }

    function getInputValue(card, name) {
        const element = card.querySelector(`.rule-input-${name}`);
        if (!element) return '';
        if (element.type === 'checkbox') return element.checked ? '1' : '0';
        return element.value.trim();
    }

    function getPickerValues(card, name) {
        const picker = card.querySelector(`.rule-picker[data-picker-name="${name}"]`);
        if (!picker) return [];
        return Array.from(picker.querySelectorAll('.rule-picker-tag'))
            .map(tag => tag.dataset.value || '')
            .filter(Boolean);
    }

    function addPickerValue(picker, value) {
        value = String(value || '').trim();
        if (!value) return;
        const existing = Array.from(picker.querySelectorAll('.rule-picker-tag'))
            .some(tag => tag.dataset.value.toLocaleLowerCase() === value.toLocaleLowerCase());
        if (existing) return;

        const empty = picker.querySelector('.rule-picker-empty');
        if (empty) empty.remove();

        const tag = document.createElement('span');
        tag.className = 'rule-picker-tag';
        tag.dataset.value = value;
        const text = document.createElement('span');
        text.textContent = value;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'rule-picker-remove';
        remove.dataset.action = 'remove-tag';
        remove.title = 'Remove';
        remove.innerHTML = '&times;';
        tag.append(text, remove);
        picker.querySelector('.rule-picker-tags').appendChild(tag);
    }

    function appendRuleData(formData, card) {
        const editorMode = card.dataset.editorMode || 'simple';
        const action = getInputValue(card, 'action');
        if (action) {
            try {
                const parsed = JSON.parse(action);
                formData.append('action', JSON.stringify(parsed));
            } catch (_error) {
                throw new Error('Action must be valid JSON');
            }
        } else {
            formData.append('action', '');
        }

        formData.append('description', getInputValue(card, 'description'));
        formData.append('profile', getInputValue(card, 'profile'));
        formData.append('enabled', getInputValue(card, 'enabled'));
        formData.append('priority', getInputValue(card, 'priority') || '0');
        formData.append('editor_mode', editorMode);

        if (editorMode === 'simple') {
            formData.append('simple_match_name', JSON.stringify(getPickerValues(card, 'name')));
            formData.append('simple_match_race', JSON.stringify(getPickerValues(card, 'race')));
            formData.append('simple_match_gender', JSON.stringify(getPickerValues(card, 'gender')));
            formData.append('simple_match_faction', JSON.stringify(getPickerValues(card, 'faction')));
            formData.append('simple_match_mods', JSON.stringify(getPickerValues(card, 'mods')));
        } else {
            formData.append('match_name', getInputValue(card, 'match_name'));
            formData.append('match_race', getInputValue(card, 'match_race'));
            formData.append('match_gender', getInputValue(card, 'match_gender'));
            formData.append('match_base', getInputValue(card, 'match_base'));
            formData.append('match_faction', getInputValue(card, 'match_faction'));
            formData.append('match_mods', getInputValue(card, 'match_mods'));
        }
    }

    window.IMPORT_RULES = {
        editRule: function(id) {
            const rule = rulesData.find(item => String(item.id) === String(id));
            if (rule) rule._editor_mode = rule._has_custom_regex ? 'advanced' : 'simple';
            editingId = id;
            renderRules();
        },

        cancelEdit: function() {
            if (String(editingId) === '__new__') {
                rulesData = rulesData.filter(rule => String(rule.id) !== '__new__');
            }
            editingId = null;
            renderRules();
        },

        saveRule: async function(id) {
            const card = document.querySelector(`.rule-card[data-id="${id}"]`);
            if (!card) return;
            if (!getInputValue(card, 'description')) {
                showToast('Rule name is required', true);
                return;
            }
            if ((card.dataset.editorMode || 'simple') === 'simple' && !getInputValue(card, 'profile')) {
                showToast('Select a profile for this rule', true);
                return;
            }
            if ((card.dataset.editorMode || 'simple') === 'simple') {
                const hasCondition = ['name', 'race', 'gender', 'faction', 'mods']
                    .some(name => getPickerValues(card, name).length > 0);
                if (!hasCondition && !confirm('This rule will match every newly activated NPC. Continue?')) {
                    return;
                }
            }

            const formData = new FormData();
            if (String(id) === '__new__') {
                formData.append('create_import_rule', '1');
            } else {
                formData.append('update_import_rule', '1');
                formData.append('id', id);
            }

            try {
                appendRuleData(formData, card);
                const res = await fetch('core_profiles.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.ok) {
                    showToast(String(id) === '__new__' ? 'Rule created' : 'Rule updated');
                    editingId = null;
                    loadRules();
                } else {
                    showToast(json.error || 'Failed to save rule', true);
                }
            } catch (e) {
                console.error('Save failed:', e);
                showToast(e.message || 'Error saving rule', true);
            }
        },

        deleteRule: async function(id) {
            if (!confirm('Delete this profile rule?')) return;

            const formData = new FormData();
            formData.append('delete_import_rule', '1');
            formData.append('id', id);

            try {
                const res = await fetch('core_profiles.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.ok) {
                    showToast('Rule deleted');
                    loadRules();
                } else {
                    showToast('Failed to delete rule', true);
                }
            } catch (e) {
                console.error('Delete failed:', e);
                showToast('Error deleting rule', true);
            }
        },

        createRule: function() {
            if (editingId !== null) {
                showToast('Save or cancel the current rule first', true);
                return;
            }
            rulesData.unshift({
                id: '__new__',
                description: 'New Profile Rule',
                profile: '',
                priority: 0,
                enabled: true,
                match_name: null,
                match_race: null,
                match_gender: null,
                match_base: null,
                match_faction: null,
                match_mods: null,
                action: null,
                _simple: { name: [], race: [], gender: [], faction: [] },
                _has_custom_regex: false,
                _editor_mode: 'simple',
            });
            editingId = '__new__';
            renderRules();
        }
    };

    function showToast(message, isError = false) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, isError);
        } else {
            alert(message);
        }
    }
    
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (addBtn) addBtn.addEventListener('click', () => window.IMPORT_RULES.createRule());

    rulesList.addEventListener('click', (ev) => {
        const btn = ev.target.closest('button[data-action]');
        if (!btn) return;
        const card = btn.closest('.rule-card');
        if (!card) return;
        const id = card.getAttribute('data-id');
        const action = btn.getAttribute('data-action');
        if (action === 'edit') return window.IMPORT_RULES.editRule(id);
        if (action === 'delete') return window.IMPORT_RULES.deleteRule(id);
        if (action === 'save') return window.IMPORT_RULES.saveRule(id);
        if (action === 'cancel') return window.IMPORT_RULES.cancelEdit();
        if (action === 'open-advanced') {
            card.querySelector('.rule-input-match_name').value = buildExactRegex(getPickerValues(card, 'name'));
            card.querySelector('.rule-input-match_race').value = buildExactRegex(getPickerValues(card, 'race'));
            card.querySelector('.rule-input-match_gender').value = buildExactRegex(getPickerValues(card, 'gender'));
            card.querySelector('.rule-input-match_faction').value = buildExactRegex(getPickerValues(card, 'faction'));
            card.querySelector('.rule-input-match_mods').value = getPickerValues(card, 'mods').join(', ');
            card.dataset.editorMode = 'advanced';
            card.querySelector('.rule-simple-section').style.display = 'none';
            card.querySelector('.rule-advanced-panel').classList.add('open');
            return;
        }
        if (action === 'add-selected') {
            const picker = btn.closest('.rule-picker');
            const select = picker.querySelector('.rule-picker-select');
            addPickerValue(picker, select.value);
            select.value = '';
            return;
        }
        if (action === 'add-custom') {
            const picker = btn.closest('.rule-picker');
            const input = picker.querySelector('.rule-picker-custom');
            addPickerValue(picker, input.value);
            input.value = '';
            return;
        }
        if (action === 'remove-tag') {
            const tags = btn.closest('.rule-picker-tags');
            btn.closest('.rule-picker-tag').remove();
            if (!tags.querySelector('.rule-picker-tag')) {
                tags.innerHTML = '<span class="rule-picker-empty">Any</span>';
            }
        }
    });

    rulesList.addEventListener('keydown', event => {
        const input = event.target.closest('.rule-picker-custom');
        if (!input || event.key !== 'Enter') return;
        event.preventDefault();
        addPickerValue(input.closest('.rule-picker'), input.value);
        input.value = '';
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
})();

// Profile Import Modal Handler
(function() {
    const modal = document.getElementById('import_profile_modal');
    const openBtn = document.getElementById('import_profile_btn');
    const closeBtn = document.getElementById('close_import_modal');
    const cancelBtn = document.getElementById('cancel_import_btn');
    const fileInput = document.getElementById('import_file');
    const previewDiv = document.getElementById('import_preview');
    const previewContent = document.getElementById('import_preview_content');
    const confirmBtn = document.getElementById('confirm_import_btn');
    const makeDefaultNpcInput = document.getElementById('import_make_default_npc');
    const migrateOldDefaultNpcsInput = document.getElementById('import_migrate_old_default_npcs');
    const assignSlotInput = document.getElementById('import_assign_slot');
    
    let importData = null;
    
    function showToast(message, isError) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        const msgSpan = toast.querySelector('.message');
        if (msgSpan) msgSpan.textContent = message;
        toast.className = 'toast-notification' + (isError ? ' error' : '');
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
    
    function openModal() {
        modal.classList.add('show');
        resetModal();
    }
    
    function closeModal() {
        modal.classList.remove('show');
        resetModal();
    }
    
    function resetModal() {
        fileInput.value = '';
        previewDiv.style.display = 'none';
        previewContent.innerHTML = '';
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Import Profile';
        if (makeDefaultNpcInput) makeDefaultNpcInput.checked = false;
        if (migrateOldDefaultNpcsInput) migrateOldDefaultNpcsInput.checked = false;
        if (assignSlotInput) assignSlotInput.value = '';
        importData = null;
    }
    
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function normalizePreviewLabel(label, filename) {
        let value = String(label || '').trim().replace(/(?:\s*\(Imported\))+$/gi, '').trim();
        if (!value && filename) {
            value = String(filename).replace(/^.*[\\/]/, '').replace(/\.json$/i, '').replace(/[_-]+/g, ' ').trim();
        }
        return value || 'Imported Profile';
    }
    
    fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) {
            resetModal();
            return;
        }
        
        try {
            const text = await file.text();
            const data = JSON.parse(text);
            
            // Validate structure
            if (!data.export_version || !data.profile) {
                showToast('Invalid profile export file', true);
                resetModal();
                return;
            }
            
            importData = data;
            
            // Show preview
            const profile = data.profile || {};
            const llmConnectors = data.llm_connectors || [];
            const ttsConnector = data.tts_connector;
            const ittConnector = data.itt_connector;
            const apiBadges = data.api_badges || [];
            const normalizedProfileLabel = normalizePreviewLabel(profile.label, file.name);
            
            let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
            html += '<div><strong>Profile:</strong> ' + escapeHtml(normalizedProfileLabel) + '</div>';
            if (String(profile.label || '').trim() !== normalizedProfileLabel) {
                html += '<div style="color:#9fb1c9;"><strong>Original Label:</strong> ' + escapeHtml(profile.label || 'Unnamed') + '</div>';
            }
            html += '<div><strong>Export Date:</strong> ' + escapeHtml(data.export_date || 'Unknown') + '</div>';
            html += '<div><strong>LLM Connectors:</strong> ' + llmConnectors.length + '</div>';
            html += '<div><strong>API Badges:</strong> ' + apiBadges.length + ' (keys must be set manually)</div>';
            
            if (llmConnectors.length > 0) {
                html += '<div style="margin-top: 8px;"><strong>LLM Connectors:</strong></div>';
                html += '<ul style="margin: 4px 0 0 16px; padding: 0;">';
                llmConnectors.forEach(conn => {
                    html += '<li>' + escapeHtml(conn.label || conn.model || 'Unnamed') + ' (' + escapeHtml(conn.driver || 'unknown') + ')</li>';
                });
                html += '</ul>';
            }
            
            html += '</div>';
            
            previewContent.innerHTML = html;
            previewDiv.style.display = 'block';
            confirmBtn.disabled = false;
            
        } catch (err) {
            showToast('Error reading file: ' + err.message, true);
            resetModal();
        }
    });
    
    confirmBtn.addEventListener('click', async () => {
        if (!importData) return;
        
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Importing...';
        
        try {
            const formData = new FormData();
            formData.append('import_profile', '1');
            formData.append('import_data', JSON.stringify(importData));
            formData.append('import_filename', fileInput.files && fileInput.files[0] ? fileInput.files[0].name : '');
            formData.append('make_default_npc', makeDefaultNpcInput && makeDefaultNpcInput.checked ? '1' : '0');
            formData.append('migrate_old_default_npcs', migrateOldDefaultNpcsInput && migrateOldDefaultNpcsInput.checked ? '1' : '0');
            formData.append('assign_slot', assignSlotInput ? assignSlotInput.value : '');
            
            const res = await fetch('core_profiles.php', {
                method: 'POST',
                body: formData
            });
            
            const json = await res.json();
            
            if (json.ok) {
                showToast(json.message || 'Profile imported successfully!', false);
                closeModal();
                // Redirect to the new profile
                setTimeout(() => {
                    window.location.href = 'core_profiles.php?edit=' + json.id;
                }, 1000);
            } else {
                showToast('Import failed: ' + (json.error || 'Unknown error'), true);
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Import Profile';
            }
            
        } catch (err) {
            showToast('Import error: ' + err.message, true);
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Import Profile';
        }
    });
    
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
})();
</script>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
