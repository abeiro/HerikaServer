<?php

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
    'load_player_name' => false,
    'load_narrator' => false,
]);

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$uiPosition = strpos($scriptPath, '/ui/');
$webRoot = $uiPosition === false ? '' : substr($scriptPath, 0, $uiPosition);
$webRoot = rtrim($webRoot === '/' ? '' : $webRoot, '/');

$TITLE = 'CHIM UI Theme Lab';
$debugPaneLink = false;
$BODY_CLASS = 'design-showcase-page ds-compact';

ob_start();
include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'head.html';
include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'navbar.php';
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/design-showcase.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'design-showcase.css'); ?>">

<main class="ds-page-shell">
    <section class="ds-hero">
        <div class="ds-hero-copy">
            <span class="ds-eyebrow">Temporary component study</span>
            <h1>CHIM UI Theme Lab</h1>
            <p>A proposed shared visual language for buttons, panels, settings, and feedback across HerikaServer.</p>
        </div>
    </section>

    <section class="ds-audit-strip" aria-label="Current UI audit summary">
        <article class="ds-metric">
            <span class="ds-metric-value">7+</span>
            <span class="ds-metric-label">Competing button families</span>
        </article>
        <article class="ds-metric">
            <span class="ds-metric-value">10</span>
            <span class="ds-metric-label">Direct primary definitions</span>
        </article>
        <article class="ds-metric">
            <span class="ds-metric-value">3</span>
            <span class="ds-metric-label">Recommended surface depths</span>
        </article>
        <article class="ds-metric ds-metric-accent">
            <span class="ds-metric-value">1</span>
            <span class="ds-metric-label">Proposed component language</span>
        </article>
    </section>

    <div class="ds-layout">
        <nav class="ds-index" aria-label="Theme lab sections">
            <span class="ds-index-label">On this page</span>
            <a href="#buttons">Buttons</a>
            <a href="#surfaces">Surfaces</a>
            <a href="#settings">Settings</a>
            <a href="#feedback">Feedback</a>
            <a href="#guidance">Usage rules</a>
        </nav>

        <div class="ds-content">
            <section class="chim-panel" id="buttons">
                <header class="chim-panel-header">
                    <div>
                        <span class="chim-kicker">Actions</span>
                        <h2>Button hierarchy</h2>
                    </div>
                    <span class="chim-badge chim-badge-neutral">Candidate system</span>
                </header>
                <p class="chim-panel-intro">Color communicates intent. Size communicates prominence. Every variant keeps the same geometry and interaction states.</p>

                <div class="ds-component-block">
                    <span class="ds-component-label">Standard actions</span>
                    <div class="ds-button-row">
                        <button class="chim-btn chim-btn-primary" type="button"><i class="bi bi-stars" aria-hidden="true"></i> Primary action</button>
                        <button class="chim-btn chim-btn-success" type="button"><i class="bi bi-check2" aria-hidden="true"></i> Save changes</button>
                        <button class="chim-btn chim-btn-secondary" type="button"><i class="bi bi-sliders" aria-hidden="true"></i> Advanced options</button>
                        <button class="chim-btn chim-btn-danger" type="button"><i class="bi bi-trash3" aria-hidden="true"></i> Delete profile</button>
                        <button class="chim-btn chim-btn-ghost" type="button">Cancel</button>
                    </div>
                </div>

                <div class="ds-component-grid">
                    <div class="ds-component-block">
                        <span class="ds-component-label">Sizes</span>
                        <div class="ds-button-row ds-align-center">
                            <button class="chim-btn chim-btn-secondary chim-btn-sm" type="button">Small</button>
                            <button class="chim-btn chim-btn-primary" type="button">Default</button>
                            <button class="chim-btn chim-btn-primary chim-btn-lg" type="button">Large action</button>
                        </div>
                    </div>
                    <div class="ds-component-block">
                        <span class="ds-component-label">Utility and states</span>
                        <div class="ds-button-row ds-align-center">
                            <button class="chim-btn chim-btn-icon" type="button" aria-label="Refresh"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                            <button class="chim-btn chim-btn-icon chim-btn-danger-soft" type="button" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                            <button class="chim-btn chim-btn-secondary" type="button" disabled>Disabled</button>
                            <button class="chim-btn chim-btn-secondary is-loading" type="button"><span class="chim-spinner" aria-hidden="true"></span> Processing</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="chim-panel" id="surfaces">
                <header class="chim-panel-header">
                    <div>
                        <span class="chim-kicker">Structure</span>
                        <h2>Surface depth</h2>
                    </div>
                </header>
                <p class="chim-panel-intro">Three depths are enough for most pages: page panel, content card, and inset region.</p>

                <div class="ds-surface-grid">
                    <article class="chim-card">
                        <div class="chim-card-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></div>
                        <div>
                            <span class="chim-kicker">Level 2</span>
                            <h3>Content card</h3>
                            <p>Use for a coherent object, connector, profile, or tool.</p>
                        </div>
                        <button class="chim-btn chim-btn-secondary chim-btn-sm" type="button">Configure</button>
                    </article>

                    <article class="chim-card chim-card-selected">
                        <div class="chim-card-icon"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i></div>
                        <div>
                            <span class="chim-kicker">Selected</span>
                            <h3>Active card</h3>
                            <p>Orange is reserved for selection, focus, and primary navigation.</p>
                        </div>
                        <span class="chim-badge chim-badge-accent">Active</span>
                    </article>
                </div>

                <div class="chim-inset">
                    <div>
                        <span class="chim-kicker">Level 3</span>
                        <h3>Inset region</h3>
                        <p>Use inside a panel for metadata, advanced options, previews, and secondary controls.</p>
                    </div>
                    <div class="chim-tag-list" aria-label="Example metadata">
                        <span class="chim-tag">Slot 1</span>
                        <span class="chim-tag">Random LLM</span>
                        <span class="chim-tag">Auto diary</span>
                    </div>
                </div>
            </section>

            <section class="chim-panel" id="settings">
                <header class="chim-panel-header">
                    <div>
                        <span class="chim-kicker">Configuration</span>
                        <h2>Setting rows</h2>
                    </div>
                    <button class="chim-btn chim-btn-secondary chim-btn-sm" type="button"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset section</button>
                </header>

                <div class="chim-setting-list">
                    <div class="chim-setting-row">
                        <div class="chim-setting-copy">
                            <label for="profile-name">Profile name</label>
                            <span>Short labels remain readable and aligned across pages.</span>
                        </div>
                        <input class="chim-input" id="profile-name" type="text" value="Default Profile">
                    </div>
                    <div class="chim-setting-row">
                        <div class="chim-setting-copy">
                            <label for="connector-mode">Connector mode</label>
                            <span>Inputs use the same surface, border, and focus treatment.</span>
                        </div>
                        <select class="chim-input chim-select" id="connector-mode">
                            <option>Profile default</option>
                            <option>Random selection</option>
                            <option>Global override</option>
                        </select>
                    </div>
                    <div class="chim-setting-row">
                        <div class="chim-setting-copy">
                            <span class="chim-setting-title">Dynamic profile</span>
                            <span>Allow gameplay events to evolve this NPC profile.</span>
                        </div>
                        <label class="chim-switch">
                            <input type="checkbox" checked>
                            <span aria-hidden="true"></span>
                            <b>On</b>
                        </label>
                    </div>
                </div>

                <footer class="chim-panel-footer">
                    <span class="chim-muted">Unsaved changes are kept local until saved.</span>
                    <div class="ds-button-row">
                        <button class="chim-btn chim-btn-ghost" type="button">Cancel</button>
                        <button class="chim-btn chim-btn-success" type="button"><i class="bi bi-check2" aria-hidden="true"></i> Save changes</button>
                    </div>
                </footer>
            </section>

            <section class="chim-panel" id="feedback">
                <header class="chim-panel-header">
                    <div>
                        <span class="chim-kicker">System feedback</span>
                        <h2>Callouts and status</h2>
                    </div>
                </header>

                <div class="ds-feedback-grid">
                    <div class="chim-callout chim-callout-info">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <div><strong>Information</strong><span>This connector inherits the current profile settings.</span></div>
                    </div>
                    <div class="chim-callout chim-callout-success">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <div><strong>Saved</strong><span>Your profile changes were applied successfully.</span></div>
                    </div>
                    <div class="chim-callout chim-callout-warning">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div><strong>Review needed</strong><span>This action can change gameplay state.</span></div>
                    </div>
                    <div class="chim-callout chim-callout-danger">
                        <i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
                        <div><strong>Connection failed</strong><span>Check the endpoint and credentials, then try again.</span></div>
                    </div>
                </div>
            </section>

            <section class="chim-panel" id="guidance">
                <header class="chim-panel-header">
                    <div>
                        <span class="chim-kicker">Migration guidance</span>
                        <h2>Usage rules</h2>
                    </div>
                </header>
                <div class="ds-rule-grid">
                    <article><span>01</span><h3>One primary action</h3><p>Use at most one orange primary action per decision area.</p></article>
                    <article><span>02</span><h3>Green means commit</h3><p>Reserve green for save, apply, install, and successful completion.</p></article>
                    <article><span>03</span><h3>Red means consequence</h3><p>Use red only for destructive or irreversible actions.</p></article>
                    <article><span>04</span><h3>Panels show hierarchy</h3><p>Use depth and spacing rather than unrelated colors to group content.</p></article>
                </div>
            </section>
        </div>
    </div>
</main>

</body>
</html>
<?php
$buffer = ob_get_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
