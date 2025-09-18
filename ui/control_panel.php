<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

// Determine web root (match core pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$TITLE = "CHIM - Control Panel";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 80px; }
.tab-container { margin: 20px 0; }
.tab-buttons { display:flex; flex-wrap:wrap; gap:5px; word-spacing:5px; }
.tab-button { background:#2a2a2a; border:none; padding:12px 18px; color:#f8f9fa; cursor:pointer; border-top-left-radius:8px; border-top-right-radius:8px; transition: all 0.3s ease; font-size:1em; white-space:nowrap; font-family:'MagicCards', sans-serif; word-spacing:5px; letter-spacing:1.5px; }
.tab-button:hover { background:#3a3a3a; }
.tab-button.active { background:#1a1a1a; border-bottom:2px solid rgb(212, 94, 0); margin-bottom:-2px; }
.tab-content { display:none; background:#2a2a2a; border-radius:8px; border-top-left-radius:0; }
.tab-content.active { display:block; }
.embed-wrap { height: calc(100vh - 220px); min-height: 520px; border:1px solid #4a4a4a; border-radius:8px; overflow:hidden; background:#2a2a2a; }
.embed { width:100%; height:100%; border:0; background:transparent; }
@media (max-height: 800px){ .embed-wrap { min-height: 420px; } }
</style>

<main>
    <div class="cp-title" style="display:flex; align-items:center; justify-content:space-between; margin: 4px 0 8px 0;">
        <h1 style="margin:0;">Control Panel</h1>
        <a href="<?php echo $webRoot; ?>/ui/tests/ai_agent_ini.php" target="_blank" class="btn-base btn-primary" title="Generate AIAgent.ini file for the mod file.">Create Custom AIAgent.ini</a>
    </div>
    <div class="tab-buttons">
        <button class="tab-button active" data-tab="srvlogs">🌲Server Logs</button>
        <button class="tab-button" data-tab="cache">🎼Audio & Image Cache</button>
        <button class="tab-button" data-tab="requests">🔍Request Logs</button>
        <button class="tab-button" data-tab="responses">💬Response Queue</button>
        <button class="tab-button" data-tab="playthrough">🎮Playthrough Manager</button>
        <button class="tab-button" data-tab="dbmgr">🗄️Database Manager</button>
        <a href="<?php echo $webRoot; ?>/ui/core/migrate_profiles.php" class="btn-base btn-primary" style="margin-left:auto" title="Migrate all legacy conf_*.php profiles to the database and archive them.">⚙️ Migrate Legacy Profiles</a>
    </div>

    <div id="srvlogs" class="tab-content active">
        <div class="embed-wrap">
            <iframe class="embed" loading="eager" src="<?php echo $webRoot; ?>/ui/tests/apache2err.php?embed=1"></iframe>
        </div>
    </div>
    <div id="cache" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/soundcache/"></iframe>
        </div>
    </div>
    <div id="requests" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/index.php?table=audit_request&embed=1"></iframe>
        </div>
    </div>
    <div id="responses" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/index.php?table=responselog&embed=1"></iframe>
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
    const qp = new URL(window.location).searchParams.get('tab');
    if (qp && document.getElementById(qp)) activate(qp);
})();
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


