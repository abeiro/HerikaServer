<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__ . DIRECTORY_SEPARATOR . "../profile_loader.php");
$TITLE = "Configuration";
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/head.html");
include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding: 80px 10px 10px; height: 100vh; }
.tab-buttons { display: flex; flex-wrap: wrap; margin: 20px 0; border-bottom: 2px solid rgba(242, 124, 17, 0.2); gap: 5px; word-spacing: 5px; }
.tab-button { background: linear-gradient(180deg, rgba(42, 42, 42, 0.8), rgba(34, 34, 34, 0.9)); border: 2px solid #3a3a3a; border-bottom: none; padding: 12px 18px; color: #f8f9fa; cursor: pointer; border-top-left-radius: 8px; border-top-right-radius: 8px; transition: all 0.3s ease; font-size: 1em; white-space: nowrap; font-family: 'MagicCards', sans-serif; word-spacing: 5px; letter-spacing: 1.5px; margin-bottom: -2px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
.tab-button:hover { background: linear-gradient(180deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 1)); color: rgb(242, 124, 17); border-color: rgba(242, 124, 17, 0.3); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); }
.tab-button.active { background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98)); border-color: rgba(242, 124, 17, 0.5); border-bottom: 2px solid rgba(42, 42, 42, 0.95); color: rgb(242, 124, 17); box-shadow: 0 4px 8px rgba(242, 124, 17, 0.2); }
.tab-content { display: none; background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98)); border-radius: 8px; border-top-left-radius: 0; border: 1px solid #3a3a3a; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03); }
.tab-content.active { display: flex; flex-grow: 1; }
.embed-wrap { height: 100%; width: 100%; border: 1px solid #4a4a4a; border-radius: 8px; overflow: hidden; background: #2a2a2a; }
.embed { width: 100%; height: 100%; border: 0; background: transparent; }
@media (max-height: 800px) { .embed-wrap { min-height: 420px; } }
</style>

<main class="d-flex flex-column">
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="top-area">
        <div class="tab-buttons">
            <button class="tab-button active" data-tab="npc">🌟 CHIM NPCs</button>
            <button class="tab-button" data-tab="globals">🌐 Global Settings</button>
            <button class="tab-button" data-tab="profiles">🗃️ Profiles</button>
            <button class="tab-button" data-tab="llm">🧠 LLM Connectors</button>
            <button class="tab-button" data-tab="ttscfg">🔊 TTS Connectors</button>
            <button class="tab-button" data-tab="sttcfg">🎤 STT Connector</button>
            <button class="tab-button" data-tab="ittcfg">🖼️ ITT Connector</button>
            <button class="tab-button" data-tab="keys">🔑 API Keys</button>
            <button class="tab-button" data-tab="player">👤 Player</button>
            <button class="tab-button" data-tab="narrator">🗣️ Narration</button>
            <button class="tab-button" data-tab="oghma">📜 Oghma Infinium</button>
            <button class="tab-button" data-tab="npcbio">🪪 NPC Biographies</button>
            <button class="tab-button" data-tab="items">📜 Descriptions</button>
            <button class="tab-button" data-tab="actions">⚔️ Action Editor</button>
            <button class="tab-button" data-tab="prompts">💬 Prompts Manager</button>
            <button class="tab-button" data-tab="xtts">📢 TTS Studio</button>
            <button class="tab-button" data-tab="serverplugins">🧩 Server Plugins</button>
        </div>
    </div>

    <div class="content-area flex-grow-1 d-flex overflow-hidden" style="min-height: 0;">
        <div id="npc" class="tab-content active">
            <div class="embed-wrap">
                <iframe class="embed" loading="eager" src="<?php echo $webRoot; ?>/ui/core/npc_master.php?embed=1"></iframe>
            </div>
        </div>
        <div id="player" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/core/player_management.php?embed=1"></iframe>
            </div>
        </div>
        <div id="narrator" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/core/narrator_management.php?embed=1"></iframe>
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
        <div id="ttscfg" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/core/tts_connectors.php?embed=1"></iframe>
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
        <div id="items" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/description_upload.php?embed=1"></iframe>
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
        <div id="prompts" class="tab-content">
            <div class="embed-wrap">
                <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/prompts_manager.php?embed=1"></iframe>
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

    function reloadIframe(container) {
        const iframe = container.querySelector('iframe');
        if (!iframe) return;
        const base = iframe.getAttribute('data-src') || iframe.getAttribute('src');
        if (!base) return;
        const url = new URL(base, window.location.origin);
        url.searchParams.set('_', String(Date.now()));
        iframe.src = url.toString();
    }

    function activate(id) {
        buttons.forEach(function(button){
            button.classList.toggle('active', button.dataset.tab === id);
        });
        tabs.forEach(function(tab){
            const active = tab.id === id;
            tab.classList.toggle('active', active);
            if (active) {
                reloadIframe(tab);
            }
        });
        const url = new URL(window.location);
        url.searchParams.set('tab', id);
        window.history.replaceState({}, '', url);
    }

    buttons.forEach(function(button){
        button.addEventListener('click', function(){
            activate(button.dataset.tab);
        });
    });

    const initialTab = new URL(window.location).searchParams.get('tab');
    if (initialTab && document.getElementById(initialTab)) {
        activate(initialTab);
    }
})();
</script>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
