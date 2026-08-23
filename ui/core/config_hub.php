<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

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

$configSections = [
    'characters' => [
        'label' => 'Characters',
        'tabs' => [
            'npc' => 'CHIM NPCs',
            'profiles' => 'Profiles',
            'player' => 'Player',
            'narrator' => 'Narration',
            'npcbio' => 'NPC Biographies',
        ],
    ],
    'ai-voice' => [
        'label' => 'AI & Voice',
        'tabs' => [
            'llm' => 'LLM',
            'ttscfg' => 'TTS',
            'xtts' => 'TTS Studio',
            'sttcfg' => 'STT',
            'ittcfg' => 'ITT',
            'keys' => 'API Keys',
        ],
    ],
    'world-behavior' => [
        'label' => 'World & Behavior',
        'tabs' => [
            'globals' => 'Global Settings',
            'oghma' => 'Oghma Infinium',
            'items' => 'Descriptions',
            'actions' => 'Action Editor',
            'prompts' => 'Prompts Manager',
            'serverplugins' => 'Server Plugins',
        ],
    ],
];

$tabIcons = [
    'npc' => '&#x1F31F;',
    'profiles' => '&#x1F5C3;&#xFE0F;',
    'player' => '&#x1F464;',
    'narrator' => '&#x1F5E3;&#xFE0F;',
    'npcbio' => '&#x1F6AA;',
    'llm' => '&#x1F9E0;',
    'ttscfg' => '&#x1F50A;',
    'xtts' => '&#x1F4E2;',
    'sttcfg' => '&#x1F3A4;',
    'ittcfg' => '&#x1F5BC;&#xFE0F;',
    'keys' => '&#x1F511;',
    'globals' => '&#x1F310;',
    'oghma' => '&#x1F4DC;',
    'items' => '&#x1F4D6;',
    'actions' => '&#x2694;&#xFE0F;',
    'prompts' => '&#x1F4AC;',
    'serverplugins' => '&#x1F9E9;',
];

$BODY_CLASS = 'hub-page';
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/head.html");
include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/hub-navigation.css?v=<?php echo filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'hub-navigation.css'); ?>">
<style>
main { padding: 0 10px 8px; height: calc(100vh - var(--hub-navbar-offset)); }
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
        <div class="config-navigation" aria-label="Configuration sections">
            <div class="tab-groups">
                <?php foreach ($configSections as $sectionId => $section): ?>
                    <section class="tab-group <?php echo $sectionId === 'characters' ? 'active' : ''; ?>" data-category="<?php echo htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="tab-group-label"><?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="tab-buttons" role="tablist" aria-label="<?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?> configuration pages">
                            <?php foreach ($section['tabs'] as $tabId => $tabLabel): ?>
                                <button
                                    class="tab-button <?php echo $tabId === 'npc' ? 'active' : ''; ?>"
                                    type="button"
                                    data-tab="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-category="<?php echo htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="tab-icon" aria-hidden="true"><?php echo $tabIcons[$tabId] ?? ''; ?></span>
                                    <span><?php echo htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
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
    const groups = document.querySelectorAll('.tab-group');
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
        const selectedButton = Array.from(buttons).find(function(button){
            return button.dataset.tab === id;
        });
        if (!selectedButton) return;

        const category = selectedButton.dataset.category;
        groups.forEach(function(group){
            group.classList.toggle('active', group.dataset.category === category);
        });
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
    } else {
        activate('npc');
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
