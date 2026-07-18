<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

// Determine web root (match core pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');
$distroDashboardRoot = preg_replace('#/(HerikaServer|StobeServer)$#', '/Dwemer-Dashboard', $webRoot);
if (!is_string($distroDashboardRoot) || trim($distroDashboardRoot) === '' || $distroDashboardRoot === $webRoot) {
    $distroDashboardRoot = '/Dwemer-Dashboard';
}
$distroDebuggerChimEmbedUrl = rtrim($distroDashboardRoot, '/') . '/distro_debugger.php?embed=1&tab=chim';
$distroDatabaseManagerUrl = rtrim($distroDashboardRoot, '/') . '/database_manager.php';

$TITLE = "Control Panel";
$BODY_CLASS = 'hub-page';
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 80px; padding-left: 10px; padding-right: 10px; }
.tab-container { margin: 20px 0; }

.tab-buttons {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 20px;
    border-bottom: 2px solid rgba(242, 124, 17, 0.2);
    gap: 5px;
    word-spacing: 5px;
}

.tab-button {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.8), rgba(34, 34, 34, 0.9));
    border: 2px solid #3a3a3a;
    border-bottom: none;
    padding: 12px 18px;
    color: #f8f9fa;
    cursor: pointer;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    transition: all 0.3s ease;
    font-size: 1em;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: -2px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.tab-button .tab-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif;
    font-size: 1.1em;
    line-height: 1;
}

.tab-button .tab-label {
    font-family: 'MagicCards', sans-serif;
    word-spacing: 5px;
    letter-spacing: 1.5px;
}

.tab-button:hover {
    background: linear-gradient(180deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 1));
    color: rgb(242, 124, 17);
    border-color: rgba(242, 124, 17, 0.3);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.tab-button.active {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-color: rgba(242, 124, 17, 0.5);
    border-bottom: 2px solid rgba(42, 42, 42, 0.95);
    color: rgb(242, 124, 17);
    box-shadow: 0 4px 8px rgba(242, 124, 17, 0.2);
}

.tab-content {
    display: none;
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-radius: 8px;
    border-top-left-radius: 0;
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
}

.tab-content.active { display: block; }

.embed-wrap {
    height: calc(100vh - 220px);
    min-height: 520px;
    border: 1px solid #4a4a4a;
    border-radius: 8px;
    overflow: hidden;
    background: #2a2a2a;
}

.embed {
    width: 100%;
    height: 100%;
    border: 0;
    background: transparent;
}

@media (max-height: 800px) {
    .embed-wrap {
        min-height: 420px;
    }
}

</style>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/hub-navigation.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'hub-navigation.css'); ?>">

<main>
    <div class="config-navigation" aria-label="Control Panel sections">
        <div class="tab-groups">
            <section class="tab-group active" data-category="diagnostics">
                <div class="tab-group-label">Diagnostics</div>
                <div class="tab-buttons" role="tablist" aria-label="Diagnostics pages">
                    <button class="tab-button active" data-tab="srvlogs"><span class="tab-icon" aria-hidden="true">&#x1F332;</span><span class="tab-label">Server Logs</span></button>
                    <button class="tab-button" data-tab="requests"><span class="tab-icon" aria-hidden="true">&#x1F50D;</span><span class="tab-label">Request Logs</span></button>
                    <button class="tab-button" data-tab="oghmaaudit"><span class="tab-icon" aria-hidden="true">&#x1F4D6;</span><span class="tab-label">Oghma Audit</span></button>
                    <button class="tab-button" data-tab="rellogs"><span class="tab-icon" aria-hidden="true">&#x1F517;</span><span class="tab-label">Relationship Logs</span></button>
                </div>
            </section>
            <section class="tab-group" data-category="monitoring">
                <div class="tab-group-label">Monitoring</div>
                <div class="tab-buttons" role="tablist" aria-label="Monitoring pages">
                    <button class="tab-button" data-tab="audit"><span class="tab-icon" aria-hidden="true">&#x1F4CA;</span><span class="tab-label">Cost Breakdown</span></button>
                    <button class="tab-button" data-tab="responses"><span class="tab-icon" aria-hidden="true">&#x1F4AC;</span><span class="tab-label">Response Queue</span></button>
                </div>
            </section>
            <section class="tab-group" data-category="data-tools">
                <div class="tab-group-label">Data &amp; Tools</div>
                <div class="tab-buttons" role="tablist" aria-label="Data and tools pages">
                    <button class="tab-button" data-tab="cache"><span class="tab-icon" aria-hidden="true">&#x1F3BC;</span><span class="tab-label">Audio &amp; Image Cache</span></button>
                    <button class="tab-button" data-tab="playthrough"><span class="tab-icon" aria-hidden="true">&#x1F3AE;</span><span class="tab-label">Playthrough Manager</span></button>
                    <button class="tab-button" data-tab="dbmgr"><span class="tab-icon" aria-hidden="true">&#x1F5C4;&#xFE0F;</span><span class="tab-label">Database Manager</span></button>
                </div>
            </section>
        </div>
    </div>

    <div id="srvlogs" class="tab-content active">
        <div class="embed-wrap">
            <iframe class="embed" loading="eager" src="<?php echo htmlspecialchars($distroDebuggerChimEmbedUrl, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
        </div>
    </div>
    <div id="cache" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/cache_browser.php?embed=1"></iframe>
        </div>
    </div>
    <div id="requests" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/request_logs.php?embed=1"></iframe>
        </div>
    </div>
    <div id="oghmaaudit" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/oghma_audit.php?embed=1"></iframe>
        </div>
    </div>
    <div id="audit" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/audit.php?embed=1"></iframe>
        </div>
    </div>
    <div id="responses" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/index.php?table=responselog&embed=1"></iframe>
        </div>
    </div>
    <div id="rellogs" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/relationship_logs.php?embed=1"></iframe>
        </div>
    </div>
    <div id="playthrough" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo $webRoot; ?>/ui/playthrough_manager.php?embed=1"></iframe>
        </div>
    </div>
    <div id="dbmgr" class="tab-content">
        <div class="embed-wrap">
            <iframe class="embed" loading="lazy" src="about:blank" data-src="<?php echo htmlspecialchars($distroDatabaseManagerUrl, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
        </div>
    </div>
</main>

<script>
(function(){
    const buttons = document.querySelectorAll('.tab-button');
    const groups = document.querySelectorAll('.tab-group');
    const tabs = document.querySelectorAll('.tab-content');
    function activate(id){
        const selectedButton = Array.from(buttons).find(button => button.dataset.tab === id);
        if (!selectedButton) return;
        groups.forEach(group => group.classList.toggle('active', group.contains(selectedButton)));
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


