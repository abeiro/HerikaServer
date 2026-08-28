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
    'settings' => [
        'label' => 'Settings',
        'tabs' => [
            'globals' => 'Global Settings',
            'profiles' => 'Profiles',
            'npc' => 'CHIM NPCs',
            'player' => 'Player',
            'narration' => 'Narration',
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
            'npcbio' => 'NPC Biographies',
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
    'narration' => '&#x1F5E3;&#xFE0F;',
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

// Player and Narration are separate Settings buttons that share the combined
// Player & Narration content shell, so both embedded forms stay mounted and
// unsaved values survive a switch between them. Each button also labels the
// panel it controls, so the shell needs no inner tab strip of its own.
$tabTargets = [
    'player' => [
        'content' => 'player_narration',
        'section' => 'player',
        'panel' => 'player_narration_player_panel',
        'button' => 'player_narration_player_button',
    ],
    'narration' => [
        'content' => 'player_narration',
        'section' => 'narration',
        'panel' => 'player_narration_narration_panel',
        'button' => 'player_narration_narration_button',
    ],
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
.player-narration-shell { display: flex; flex-direction: column; width: 100%; height: 100%; min-width: 0; min-height: 0; }
.player-narration-panel { flex: 1 1 auto; min-height: 0; border: 0; border-radius: 8px; }
.player-narration-panel[hidden] { display: none; }
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
                    <section class="tab-group <?php echo $sectionId === 'settings' ? 'active' : ''; ?>" data-category="<?php echo htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="tab-group-label"><?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="tab-buttons" role="tablist" aria-label="<?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?> configuration pages">
                            <?php foreach ($section['tabs'] as $tabId => $tabLabel): ?>
                                <?php
                                    $tabTarget = $tabTargets[$tabId] ?? null;
                                    $tabContentId = $tabTarget['content'] ?? $tabId;
                                    $tabExtraAttrs = '';
                                    if ($tabTarget !== null) {
                                        $tabExtraAttrs = ' id="' . htmlspecialchars($tabTarget['button'], ENT_QUOTES, 'UTF-8') . '"'
                                            . ' data-section="' . htmlspecialchars($tabTarget['section'], ENT_QUOTES, 'UTF-8') . '"'
                                            . ' aria-controls="' . htmlspecialchars($tabTarget['panel'], ENT_QUOTES, 'UTF-8') . '"';
                                    }
                                ?>
                                <button
                                    class="tab-button <?php echo $tabId === 'npc' ? 'active' : ''; ?>"
                                    type="button"
                                    data-tab="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-content="<?php echo htmlspecialchars($tabContentId, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-category="<?php echo htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $tabExtraAttrs; ?>>
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
        <div id="player_narration" class="tab-content">
            <div class="player-narration-shell">
                <div
                    id="player_narration_player_panel"
                    class="embed-wrap player-narration-panel"
                    role="tabpanel"
                    aria-labelledby="player_narration_player_button">
                    <iframe
                        id="player_narration_player_frame"
                        class="embed"
                        title="Player settings"
                        loading="lazy"
                        src="about:blank"
                        data-src="<?php echo $webRoot; ?>/ui/core/player_management.php?embed=1"></iframe>
                </div>
                <div
                    id="player_narration_narration_panel"
                    class="embed-wrap player-narration-panel"
                    role="tabpanel"
                    aria-labelledby="player_narration_narration_button"
                    hidden>
                    <iframe
                        id="player_narration_narration_frame"
                        class="embed"
                        title="Narration settings"
                        loading="lazy"
                        src="about:blank"
                        data-src="<?php echo $webRoot; ?>/ui/core/narrator_management.php?embed=1"></iframe>
                </div>
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
    const buttons = Array.from(document.querySelectorAll('.tab-button'));
    const groups = document.querySelectorAll('.tab-group');
    const tabs = document.querySelectorAll('.tab-content');
    const playerNarrationContainer = document.getElementById('player_narration');
    const playerNarrationPanels = {
        player: document.getElementById('player_narration_player_panel'),
        narration: document.getElementById('player_narration_narration_panel')
    };
    const PLAYER_NARRATION_TAB = 'player_narration';
    // ?tab= values that resolve into the combined shell, including the legacy
    // ?tab=player / ?tab=narrator links.
    const playerNarrationAliases = ['player', 'narrator', 'narration', PLAYER_NARRATION_TAB];
    let playerNarrationSection = 'player';

    function normalizeSection(section) {
        return section === 'narration' ? 'narration' : 'player';
    }

    function playerNarrationVisible() {
        return !!playerNarrationContainer && playerNarrationContainer.classList.contains('active');
    }

    // Maps an outer button id (or a legacy ?tab= value) onto the content shell it
    // opens plus, for the combined shell, which inner section to select.
    function resolveTarget(id, requestedSection) {
        if (id === 'player') return { tab: PLAYER_NARRATION_TAB, section: 'player' };
        if (id === 'narrator' || id === 'narration') return { tab: PLAYER_NARRATION_TAB, section: 'narration' };
        if (id === PLAYER_NARRATION_TAB) {
            return { tab: PLAYER_NARRATION_TAB, section: normalizeSection(requestedSection || playerNarrationSection) };
        }
        return { tab: id, section: null };
    }

    function findButton(tabId, section) {
        return buttons.find(function(button){
            if (button.dataset.content !== tabId) return false;
            return !section || !button.dataset.section || button.dataset.section === section;
        });
    }

    // Keeps the two Settings Player / Narration buttons mirroring the open panel.
    function syncPlayerNarrationButtons(section) {
        const visible = playerNarrationVisible();
        buttons.forEach(function(button){
            if (button.dataset.content !== PLAYER_NARRATION_TAB) return;
            button.classList.toggle('active', visible && button.dataset.section === section);
        });
    }

    function reloadIframe(container) {
        const iframe = container.querySelector('.player-narration-panel:not([hidden]) iframe') || container.querySelector('iframe');
        if (!iframe) return;
        const base = iframe.getAttribute('data-src') || iframe.getAttribute('src');
        if (!base) return;
        const url = new URL(base, window.location.origin);
        url.searchParams.set('_', String(Date.now()));
        iframe.src = url.toString();
    }

    function ensureIframeLoaded(container) {
        const iframe = container ? container.querySelector('iframe') : null;
        if (!iframe) return;
        const currentSource = iframe.getAttribute('src') || '';
        if (currentSource === '' || currentSource === 'about:blank') {
            reloadIframe(container);
        }
    }

    function setPlayerNarrationSection(section, reload, updateUrl) {
        const normalized = normalizeSection(section);
        playerNarrationSection = normalized;

        Object.keys(playerNarrationPanels).forEach(function(panelSection){
            const panel = playerNarrationPanels[panelSection];
            if (panel) panel.hidden = panelSection !== normalized;
        });

        syncPlayerNarrationButtons(normalized);

        if (reload && playerNarrationPanels[normalized]) {
            ensureIframeLoaded(playerNarrationPanels[normalized]);
        }
        if (updateUrl) {
            const url = new URL(window.location);
            url.searchParams.set('tab', PLAYER_NARRATION_TAB);
            url.searchParams.set('section', normalized);
            window.history.replaceState({}, '', url);
        }
    }

    function activate(id, requestedPlayerNarrationSection) {
        const target = resolveTarget(id, requestedPlayerNarrationSection);
        const selectedButton = findButton(target.tab, target.section);
        if (!selectedButton) return;

        // Moving between the Player and Narration buttons only swaps the visible
        // panel, so neither embedded form reloads and unsaved values survive.
        if (target.tab === PLAYER_NARRATION_TAB && playerNarrationVisible()) {
            setPlayerNarrationSection(target.section, true, true);
            return;
        }

        if (target.tab === PLAYER_NARRATION_TAB) {
            setPlayerNarrationSection(target.section, false, false);
        }

        const category = selectedButton.dataset.category;
        groups.forEach(function(group){
            group.classList.toggle('active', group.dataset.category === category);
        });
        buttons.forEach(function(button){
            button.classList.toggle('active', button === selectedButton);
        });
        tabs.forEach(function(tab){
            const active = tab.id === target.tab;
            tab.classList.toggle('active', active);
            if (active) {
                reloadIframe(tab);
            }
        });
        const url = new URL(window.location);
        url.searchParams.set('tab', target.tab);
        if (target.tab === PLAYER_NARRATION_TAB) {
            url.searchParams.set('section', playerNarrationSection);
        } else {
            url.searchParams.delete('section');
        }
        window.history.replaceState({}, '', url);
    }

    buttons.forEach(function(button){
        button.addEventListener('click', function(){
            activate(button.dataset.tab);
        });
    });

    const initialUrl = new URL(window.location);
    const initialTab = initialUrl.searchParams.get('tab');
    if (initialTab && (document.getElementById(initialTab) || playerNarrationAliases.includes(initialTab))) {
        const initialSection = initialUrl.searchParams.get('section');
        activate(initialTab, initialSection);
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
