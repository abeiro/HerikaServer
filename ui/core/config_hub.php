<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

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

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$TITLE = "Configuration";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding: 80px 10px 10px; height: 100vh; }
.tab-container { margin: 20px 0; }
.tab-buttons { display:flex; flex-wrap:wrap; gap:5px; word-spacing:5px; }
.tab-button { background:#2a2a2a; border:none; padding:12px 18px; color:#f8f9fa; cursor:pointer; border-top-left-radius:8px; border-top-right-radius:8px; transition: all 0.3s ease; font-size:1em; white-space:nowrap; font-family:'MagicCards', sans-serif; word-spacing:5px; letter-spacing:1.5px; }
.tab-button:hover { background:#3a3a3a; }
.tab-button.active { background:#1a1a1a; border-bottom:2px solid rgb(212, 94, 0); margin-bottom:-2px; }
.tab-content { display:none; background:#2a2a2a; border-radius:8px; border-top-left-radius:0; }
.tab-content.active { display:flex; flex-grow: 1}
.embed-wrap { height: 100%; width: 100%; border:1px solid #4a4a4a; border-radius:8px; overflow:hidden; background:#2a2a2a; }
.embed { width: 100%; height:100%; border:0; background:transparent; }
@media (max-height: 800px){ .embed-wrap { min-height: 420px; } }
.tab-divider { padding: 10px 8px; color:#9fb1c9; font-weight:700; user-select:none; pointer-events:none; }
</style>

<main class="d-flex flex-column">
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="top-area">
        <h1>Configuration</h1>
        <div class="tab-buttons">
            <button class="tab-button active" data-tab="npc">🌟CHIM NPCs</button>
            <button class="tab-button" data-tab="globals">🌐Global Settings</button>
            <button class="tab-button" data-tab="profiles">🏗️Profile Builder</button>
            <button class="tab-button" data-tab="llm">🔌LLM Connectors</button>
            <button class="tab-button" data-tab="keys">🔑API Keys</button>
            <button class="tab-button" data-tab="oghma">🐙Oghma Infium</button>
            <button class="tab-button" data-tab="npcbio">📚NPC Biographies</button>
            <button class="tab-button" data-tab="actions">⚔️Action Editor</button>
            <button class="tab-button" data-tab="xtts">🗣️XTTS Management</button>
            <button class="tab-button" data-tab="serverplugins">🔌Server Plugins</button>
        </div>
    </div>

    <div class="content-area flex-grow-1 d-flex overflow-hidden" style="min-height: 0;">
        <div id="npc" class="tab-content active">
            <div class="embed-wrap">
                <iframe class="embed" loading="eager" src="<?php echo $webRoot; ?>/ui/core/npc_master.php?embed=1"></iframe>
            </div>
        </div>
        <div id="profiles" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/core/core_profiles.php?embed=1"></iframe>
            </div>
        </div>
        <div id="llm" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/core/llm_connectors.php?embed=1"></iframe>
            </div>
        </div>
        <div id="oghma" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/oghma_upload.php?embed=1"></iframe>
            </div>
        </div>
        <div id="npcbio" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/npc_upload.php?embed=1"></iframe>
            </div>
        </div>
        <div id="actions" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/function_editor.php?embed=1"></iframe>
            </div>
        </div>
        <div id="xtts" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/xtts_clone.php?embed=1"></iframe>
            </div>
        </div>
        <div id="playthrough" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/playthrough_manager.php?embed=1"></iframe>
            </div>
        </div>
        <div id="dbmgr" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/import_db.php?embed=1"></iframe>
            </div>
        </div>
        <div id="ttscfg" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/tts_connectors.php?embed=1"></iframe>
            </div>
        </div>
        <div id="sttcfg" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/stt_connectors.php?embed=1"></iframe>
            </div>
        </div>
        <div id="ittcfg" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/itt_connectors.php?embed=1"></iframe>
            </div>
        </div>
        <div id="keys" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/core/api_badge.php?embed=1"></iframe>
            </div>
        </div>
        <div id="globals" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/global_settings.php?embed=1"></iframe>
            </div>
        </div>
        <div id="serverplugins" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/server_plugins.php?embed=1"></iframe>
            </div>
        </div>
    </div>
</main>

<script>
(function(){
    const buttons = document.querySelectorAll('.tab-button');
    const tabs = document.querySelectorAll('.tab-content');
    function activate(id){
        buttons.forEach(b=>{ b.classList.toggle('active', b.dataset.tab===id); });
        tabs.forEach(t=>{
            const on = (t.id===id);
            t.classList.toggle('active', on);
            if (on){
                const iframe = t.querySelector('iframe[data-src]');
                if (iframe && (!iframe.src || iframe.src==='about:blank')){
                    iframe.src = iframe.getAttribute('data-src');
                }
            }
        });
        const url = new URL(window.location);
        url.searchParams.set('tab', id);
        window.history.replaceState({}, '', url);
    }
    buttons.forEach(b=> b.addEventListener('click', ()=> activate(b.dataset.tab)));
    // Init from URL param
    const qp = new URL(window.location).searchParams.get('tab');
    if (qp && document.getElementById(qp)) activate(qp);
})();
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


