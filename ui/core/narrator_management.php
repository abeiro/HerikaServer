<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "core_profiles.class.php");

// Determine web root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$GLOBALS["db"] = new sql();
$narrator = new Narrator();

$saveSuccess = false;
$saveMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_narrator'])) {
    try {
        // Save boolean settings
        $narrator->set('enabled', isset($_POST['enabled']) && $_POST['enabled'] === '1' ? '1' : '0');
        $narrator->set('welcome_enabled', isset($_POST['welcome_enabled']) && $_POST['welcome_enabled'] === '1' ? '1' : '0');
        $narrator->set('random_enabled', isset($_POST['random_enabled']) && $_POST['random_enabled'] === '1' ? '1' : '0');
        $narrator->set('books_only_narrator', isset($_POST['books_only_narrator']) && $_POST['books_only_narrator'] === '1' ? '1' : '0');
        $narrator->set('hide_from_context', isset($_POST['hide_from_context']) && $_POST['hide_from_context'] === '1' ? '1' : '0');
        $narrator->set('inline_narration_enabled', isset($_POST['inline_narration_enabled']) && $_POST['inline_narration_enabled'] === '1' ? '1' : '0');
        $narrator->set('preserve_asterisks_in_context', isset($_POST['preserve_asterisks_in_context']) && $_POST['preserve_asterisks_in_context'] === '1' ? '1' : '0');
        $narrator->set('remove_asterisks_from_output', isset($_POST['remove_asterisks_from_output']) && $_POST['remove_asterisks_from_output'] === '1' ? '1' : '0');
        $narrator->set('diary_enabled', isset($_POST['diary_enabled']) && $_POST['diary_enabled'] === '1' ? '1' : '0');
        
        // Save integer settings
        if (isset($_POST['random_chance'])) {
            $chance = intval($_POST['random_chance']);
            $chance = max(1, min(100, $chance)); // Clamp to 1-100
            $narrator->set('random_chance', (string)$chance);
        }
        if (isset($_POST['random_cooldown'])) {
            $cooldown = intval($_POST['random_cooldown']);
            $cooldown = max(0, min(10, $cooldown)); // Clamp to 0-10
            $narrator->set('random_cooldown', (string)$cooldown);
        }
        if (isset($_POST['welcome_cooldown'])) {
            $cooldown = intval($_POST['welcome_cooldown']);
            $cooldown = max(1, min(1440, $cooldown)); // Clamp to 1-1440 (24 hours)
            $narrator->set('welcome_cooldown', (string)$cooldown);
        }
        if (isset($_POST['quest_comment_enabled'])) {
            $narrator->set('quest_comment_enabled', $_POST['quest_comment_enabled'] === '1' ? '1' : '0');
        }
        if (isset($_POST['quest_comment_chance'])) {
            $chance = intval($_POST['quest_comment_chance']);
            $chance = max(1, min(100, $chance)); // Clamp to 1-100
            $narrator->set('quest_comment_chance', (string)$chance);
        }
        if (isset($_POST['quest_comment_cooldown'])) {
            $cooldown = intval($_POST['quest_comment_cooldown']);
            $cooldown = max(1, min(60, $cooldown)); // Clamp to 1-60 minutes
            $narrator->set('quest_comment_cooldown', (string)$cooldown);
        }
        
        // Save dynamic profile settings
        $narrator->set('dynamic_profile', isset($_POST['dynamic_profile']) && $_POST['dynamic_profile'] === '1' ? '1' : '0');
        
        // Save dynamic profile fields array
        if (isset($_POST['dynamic_profile_fields']) && is_array($_POST['dynamic_profile_fields'])) {
            $fields = array_filter($_POST['dynamic_profile_fields'], function($v) {
                return in_array($v, ['personality', 'speechstyle', 'goals'], true);
            });
            $narrator->setDynamicProfileFields(array_values($fields));
        } else {
            $narrator->setDynamicProfileFields([]);
        }
        
        // Save profile_id
        if (isset($_POST['profile_id'])) {
            $profileId = intval($_POST['profile_id']);
            if ($profileId > 0) {
                $narrator->set('profile_id', (string)$profileId);
            }
        }
        
        // Save character fields
        if (isset($_POST['voiceid'])) {
            $narrator->set('voiceid', $_POST['voiceid']);
        }
        if (isset($_POST['core'])) {
            $narrator->set('core', $_POST['core']);
        }
        if (isset($_POST['background'])) {
            $narrator->set('background', $_POST['background']);
        }
        if (isset($_POST['personality'])) {
            $narrator->set('personality', $_POST['personality']);
        }
        if (isset($_POST['speechstyle'])) {
            $narrator->set('speechstyle', $_POST['speechstyle']);
        }
        if (isset($_POST['goals'])) {
            $narrator->set('goals', $_POST['goals']);
        }
        if (isset($_POST['oghma_knowledge'])) {
            $narrator->set('oghma_knowledge', $_POST['oghma_knowledge']);
        }
        if (isset($_POST['prompt_head'])) {
            $narrator->set('prompt_head', $_POST['prompt_head']);
        }
        
        $saveSuccess = true;
        $saveMessage = 'Narration settings saved successfully!';
    } catch (Exception $e) {
        $saveSuccess = false;
        $saveMessage = 'Error saving narration settings: ' . $e->getMessage();
    }
}

// Load all narrator settings
$allNarratorData = $narrator->getAll();

// Extract settings with defaults
$enabled = $narrator->getBool('enabled', true);
$welcomeEnabled = $narrator->getBool('welcome_enabled', false);
$randomEnabled = $narrator->getBool('random_enabled', false);
$randomChance = $narrator->getInt('random_chance', 15);
$randomCooldown = $narrator->getInt('random_cooldown', 2);
$welcomeCooldown = $narrator->getInt('welcome_cooldown', 10);
$questCommentEnabled = $narrator->getBool('quest_comment_enabled', false);
$questCommentChance = $narrator->getInt('quest_comment_chance', 10);
$questCommentCooldown = $narrator->getInt('quest_comment_cooldown', 3);
$booksOnlyNarrator = $narrator->getBool('books_only_narrator', false);
$hideFromContext = $narrator->getBool('hide_from_context', false);
$inlineNarrationEnabled = $narrator->getBool('inline_narration_enabled', false);
$preserveAsterisksInContext = $narrator->getBool('preserve_asterisks_in_context', false);
$removeAsterisksFromOutput = $narrator->getBool(
    'remove_asterisks_from_output',
    isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] : false
);
$diaryEnabled = $narrator->getBool('diary_enabled', true);
$dynamicProfileEnabled = $narrator->getBool('dynamic_profile', false);
$dynamicProfileFields = $narrator->getDynamicProfileFields();

// Extract character fields
$profileId = $narrator->getInt('profile_id', 1);
$voiceid = $narrator->get('voiceid') ?? 'TheNarrator';
$core = $narrator->get('core') ?? '';
$background = $narrator->get('background') ?? '';
$personality = $narrator->get('personality') ?? '';
$speechstyle = $narrator->get('speechstyle') ?? '';
$goals = $narrator->get('goals') ?? '';
$oghmaKnowledge = $narrator->get('oghma_knowledge') ?? 'knowall';
$promptHead = $narrator->get('prompt_head') ?? '';

// Load profiles for dropdown
$profileMgr = new CoreProfile();
$allProfiles = $profileMgr->readAll();

// Load connector data for display
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");
$connectorMgr = new LLMConnector();
$allConnectors = $connectorMgr->readAll();

// Build lookup maps
$llmById = [];
foreach ($allConnectors as $conn) {
    $llmById[$conn['id']] = $conn['label'] ?? 'Connector ' . $conn['id'];
}

// Build profile connector map
$profilesConnById = [];
foreach ($allProfiles as $prof) {
    $profilesConnById[$prof['id']] = $prof;
}

// Get current profile data
$currentProfileData = $profilesConnById[$profileId] ?? null;

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$isEmbed) {
    require_once(__DIR__."/../profile_loader.php");
    $TITLE = "Narrator Management";
    ob_start();
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Override main container styles */
    main {
        padding-top: <?php echo $isEmbed ? '20px' : '80px'; ?>;
        padding-bottom: 40px;
        padding-left: 5%;
        padding-right: 5%;
        /*width: 100%;*/
        margin: 0;
        display: flex;
        justify-content: center;
    }
    
    .page-container {
        width: 100%;
        max-width: 1200px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 28px;
        padding: 24px 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(28, 28, 28, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .page-header h1 {
        margin-bottom: 10px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header p {
        color: #aaa;
        font-size: 1em;
        margin: 0;
    }

    /* Content Layout */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 22px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.2s ease;
    }

    .content-section:hover {
        border-color: #4a4a4a;
    }

    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 18px;
        font-size: 1.35em;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    .content-grid + .content-grid,
    .content-grid + .content-section {
        margin-top: 20px;
    }

    .content-section.full-width-section + .content-section.full-width-section {
        margin-top: 20px;
    }

    /* Form Styling */
    .content-section > label:not(.toggle-row) {
        display: block;
        font-size: 13px;
        color: rgb(242, 124, 17);
        font-weight: 600;
        margin-bottom: 6px;
        margin-top: 14px;
    }

    .content-section > label:not(.toggle-row):first-of-type {
        margin-top: 0;
    }

    .content-section input[type="text"],
    .content-section input[type="number"] {
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 4px;
        transition: all 0.2s ease;
    }

    .content-section input[type="text"]:focus,
    .content-section input[type="number"]:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }
    
    .content-section textarea {
        min-height: 80px;
        font-family: inherit;
        resize: vertical;
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 4px;
        transition: all 0.2s ease;
    }

    .content-section textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }
    
    .content-section select {
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .content-section select:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    /* Toggle Switch Styling */
    .toggle-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: rgba(26, 26, 26, 0.6);
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.2s ease;
    }

    .toggle-row:hover {
        background: rgba(36, 36, 36, 0.8);
        border-color: #4a4a4a;
    }

    .toggle-switch {
        position: relative;
        width: 48px;
        height: 24px;
        flex-shrink: 0;
    }

    .toggle-switch input[type="checkbox"] {
        position: absolute;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        margin: 0;
        z-index: 2;
    }

    .toggle-slider {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #3a3a3a;
        border-radius: 24px;
        transition: all 0.3s ease;
        border: 1px solid #555;
    }

    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        top: 50%;
        transform: translateY(-50%);
        background-color: #888;
        border-radius: 50%;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .toggle-switch input[type="checkbox"]:checked + .toggle-slider {
        background-color: rgba(32, 122, 74, 0.9);
        border-color: rgba(72, 187, 120, 0.5);
    }

    .toggle-switch input[type="checkbox"]:checked + .toggle-slider::before {
        transform: translateY(-50%) translateX(22px);
        background-color: #fff;
    }

    .toggle-switch input[type="checkbox"]:focus + .toggle-slider {
        box-shadow: 0 0 0 3px rgba(32, 122, 74, 0.25);
    }

    .toggle-label {
        flex: 1;
        color: #e0e0e0;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        user-select: none;
    }

    .toggle-label:hover {
        color: #fff;
    }

    /* Legacy checkbox group - keep for dynamic profile fields */
    .checkbox-group {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }

    .checkbox-group label {
        margin: 0;
        font-weight: normal;
        color: #cfd8e3;
        cursor: pointer;
    }

    .checkbox-group input[type="checkbox"] {
        accent-color: #176529;
        transform: scale(1.4);
        margin-right: 8px;
        cursor: pointer;
    }

    /* Dynamic Profile Card */
    .dynamic-profile-card {
        margin-bottom: 20px;
        padding: 18px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(32, 32, 32, 0.6));
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        box-shadow: inset 0 1px rgba(255,255,255,0.03);
    }

    .dynamic-profile-card h3 {
        color: rgb(242, 124, 17);
        margin-bottom: 14px;
        font-size: 1.15em;
        font-weight: 600;
    }

    .field-selection-label {
        margin-top: 14px;
        display: block;
        color: rgb(242, 124, 17);
        font-weight: 600;
        font-size: 0.95em;
    }

    .field-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .field-chip {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(42, 42, 42, 0.8);
        border: 1px solid #4a4a4a;
        padding: 10px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .field-chip:hover {
        background: rgba(52, 52, 52, 0.9);
        border-color: #5a5a5a;
    }

    .field-chip:has(input:checked) {
        background: rgba(32, 122, 74, 0.25);
        border-color: rgba(72, 187, 120, 0.5);
    }

    .field-chip input[type="checkbox"] {
        accent-color: #176529;
        transform: scale(1.3);
        cursor: pointer;
    }

    .field-chip .chip-text {
        color: #cfd8e3;
        font-size: 0.95em;
        font-weight: 500;
    }

    .field-chips + .hint {
        margin-top: 10px;
    }

    .hint {
        font-size: 12px;
        color: #999;
        margin-top: 4px;
        margin-bottom: 6px;
        display: block;
        padding-left: 2px;
        line-height: 1.4;
    }

    .toggle-row + .hint {
        margin-left: 62px;
        margin-top: -2px;
        margin-bottom: 12px;
    }

    .toggle-row + .hint + label:not(.toggle-row) {
        margin-top: 8px;
    }

    /* Button Styling */
    .btn-save {
        background-color: #176529;
        color: #fff;
        border: 1px solid rgba(72, 187, 120, 0.3);
        border-radius: 8px;
        padding: 12px 28px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        letter-spacing: 0.3px;
        margin-bottom: 24px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255, 255, 255, 0.1);
    }

    .btn-save:hover {
        background-color: #1e8738;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                    inset 0 1px rgba(255, 255, 255, 0.15);
    }

    .btn-save:active {
        transform: translateY(0);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    /* Toast Notification */
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #207a4a;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: none;
        z-index: 9999;
    }
    
    .toast-notification.error {
        background: #c03;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            padding: 15px;
        }
        
        .content-section {
            padding: 15px;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .page-header h1 {
            font-size: 1.5em;
        }

        .toggle-row {
            padding: 10px 12px;
        }

        .toggle-label {
            font-size: 13px;
        }

        .field-chips {
            flex-direction: column;
        }

        .toggle-row + .hint {
            margin-left: 0;
        }
    }
</style>

<?php if ($isEmbed): ?>
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 20px; }
</style>
<?php endif; ?>

<main>
    <div class="page-container">
        <div id="toast" class="toast-notification <?php echo $saveSuccess ? '' : 'error'; ?>">
            <span class="message"><?php echo htmlspecialchars($saveMessage); ?></span>
        </div>

        <?php if ($saveSuccess || $saveMessage): ?>
            <script>
            setTimeout(function(){ 
                try{ 
                    const t=document.getElementById('toast'); 
                    if(t){ 
                        t.style.display='block'; 
                        setTimeout(()=>{ t.style.display='none'; }, 3000); 
                    } 
                }catch(_e){} 
            }, 50);
            </script>
        <?php endif; ?>

        <div class="page-header">
            <h1>
                Narrator Management
            </h1>
            <p>Configure narrator behavior and settings.</p>
        </div>

        <form method="post" action="">
            <button type="submit" class="btn-save" name="save_narrator" value="1">Save Narration Settings</button>

            <div class="content-grid">
                <!-- Core Settings Section -->
                <div class="content-section">
                    <h2>Core Settings</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="enabled" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Narrator</span>
                    </label>
                    <span class="hint">Enable or disable the narrator system entirely.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="books_only_narrator" name="books_only_narrator" value="1" <?php echo $booksOnlyNarrator ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Only Narrator Summarizes Books</span>
                    </label>
                    <span class="hint">The Narrator will be the only one to summarize books.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="hide_from_context" name="hide_from_context" value="1" <?php echo $hideFromContext ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Hide Narrator from NPC Context</span>
                    </label>
                    <span class="hint">Hide Narrator-spoken dialogue lines from NPC context.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="diary_enabled" name="diary_enabled" value="1" <?php echo $diaryEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Narrator Diary</span>
                    </label>
                    <span class="hint">Allow The Narrator to write diary entries. Will trigger on autodiary, all nearby npc diary hotkey & if you look up in the sky and press the diary hotkey.</span>
                </div>

                <!-- Narration Section -->
                <div class="content-section">
                    <h2>Inline Narration</h2>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="inline_narration_enabled" name="inline_narration_enabled" value="1" <?php echo $inlineNarrationEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Inline Narration</span>
                    </label>
                    <span class="hint">Enable inline narration in asterisks (e.g., *She smiles*). Narration is spoken by The Narrator voice and shown in subtitles, while the dialogue line remains in the NPC's voice.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="remove_asterisks_from_output" name="remove_asterisks_from_output" value="1" <?php echo $removeAsterisksFromOutput ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Remove Asterisks From Output</span>
                    </label>
                    <span class="hint">Remove text between ** when responding (*cough*, *smiles*, etc).</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="preserve_asterisks_in_context" name="preserve_asterisks_in_context" value="1" <?php echo $preserveAsterisksInContext ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Keep Asterisk Narration in Context</span>
                    </label>
                    <span class="hint">Keep *narration* intact in subtitles, chat events, and LLM context history (also works when inline narration is disabled).</span>
                </div>

                <!-- Welcome Message Section -->
                <div class="content-section">
                    <h2>Welcome Message</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="welcome_enabled" name="welcome_enabled" value="1" <?php echo $welcomeEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Welcome Message on Load</span>
                    </label>
                    <span class="hint">The Narrator will give you a quick recap of what happened previously after you have loaded a save game.</span>
                    
                    <label for="welcome_cooldown">Welcome Message Cooldown (minutes)</label>
                    <input type="number" id="welcome_cooldown" name="welcome_cooldown" value="<?php echo htmlspecialchars((string)$welcomeCooldown); ?>" min="1" max="1440">
                    <span class="hint">Minimum time in minutes between welcome messages. Range: 1-1440 (24 hours), Default: 10 minutes</span>
                </div>

                <!-- Random Narration Section -->
                <div class="content-section">
                    <h2>Random Narration</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="random_enabled" name="random_enabled" value="1" <?php echo $randomEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Random Narration</span>
                    </label>
                    <span class="hint">Enable random Narrator interjections. The Narrator will occasionally add visual scene descriptions during conversations.</span>
                    
                    <label for="random_chance">Random Narration Chance (%)</label>
                    <input type="number" id="random_chance" name="random_chance" value="<?php echo htmlspecialchars((string)$randomChance); ?>" min="1" max="100">
                    <span class="hint">Probability (1-100) that the Narrator will interject with a scene description. Default: 15%</span>
                    
                    <label for="random_cooldown">Random Narration Cooldown</label>
                    <input type="number" id="random_cooldown" name="random_cooldown" value="<?php echo htmlspecialchars((string)$randomCooldown); ?>" min="0" max="10">
                    <span class="hint">Minimum number of conversation rounds between Narrator interjections. Prevents narration spam. Range: 0-10, Default: 2</span>
                </div>

                <!-- Quest Comments Section -->
                <div class="content-section">
                    <h2>Quest Comments</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="quest_comment_enabled" name="quest_comment_enabled" value="1" <?php echo $questCommentEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Quest Comments</span>
                    </label>
                    <span class="hint">Narrator will comment on quest objective updates.</span>
                    
                    <label for="quest_comment_chance">Quest Comment Chance (%)</label>
                    <input type="number" id="quest_comment_chance" name="quest_comment_chance" value="<?php echo htmlspecialchars((string)$questCommentChance); ?>" min="1" max="100">
                    <span class="hint">Probability (1-100) that Narrator will comment on quest updates. Default: 10%</span>
                    
                    <label for="quest_comment_cooldown">Quest Comment Cooldown (minutes)</label>
                    <input type="number" id="quest_comment_cooldown" name="quest_comment_cooldown" value="<?php echo htmlspecialchars((string)$questCommentCooldown); ?>" min="1" max="60">
                    <span class="hint">Minimum time in minutes between quest comments. Prevents spam. Range: 1-60 minutes, Default: 3 minutes</span>
                </div>
            </div>
            
            <!-- Profile & Voice Section -->
            <div class="content-grid">
                <div class="content-section">
                    <h2>Profile & Voice</h2>
                    
                    <label for="profile_id">Profile</label>
                    <select id="profile_id" name="profile_id">
                        <?php foreach ($allProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars((string)$profile['id']); ?>" <?php echo ($profileId == $profile['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($profile['label'] ?? 'Profile ' . $profile['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">LLM connector profile for The Narrator.</span>
                    
                    <label for="voiceid">Voice ID</label>
                    <input type="text" id="voiceid" name="voiceid" value="<?php echo htmlspecialchars($voiceid); ?>" placeholder="TheNarrator">
                    <span class="hint">TTS voice identifier for The Narrator.</span>
                    
                    <label for="oghma_knowledge">Oghma Knowledge Tags</label>
                    <input type="text" id="oghma_knowledge" name="oghma_knowledge" placeholder="Comma-separated knowledge tags (e.g., knowall, knowsome, knownone)" value="<?php echo htmlspecialchars($oghmaKnowledge); ?>">
                    <span class="hint">Comma-separated knowledge tags used by Oghma systems for knowledge lookup restrictions.</span>
                </div>
                
                <div class="content-section">
                    <h2>Selected Profile Connectors</h2>
                    <?php
                    // Helper function to get connector label
                    $getConnectorLabel = function($id) use ($llmById) {
                        return htmlspecialchars($llmById[$id] ?? '—');
                    };
                    ?>
                    <div id="profile_llm_summary" style="display:grid; grid-template-columns: auto 1fr; gap:8px; color:#cfd9ea; font-size: 13px; line-height: 1.6;">
                        <div style="color:rgb(242,124,17); font-weight:600;">🕹️ Standard:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_primary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">🏃‍♂️‍➡️ Fast:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_secondary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">💪 Power:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_tertiary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">🧪 Experimental:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_quaternary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">📓 Diary:</div>
                        <div><?= $getConnectorLabel($currentProfileData['diary_connector_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">🧾 Formatter:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_formatter_id'] ?? null) ?></div>
                    </div>
                    <span class="hint" style="margin-top: 8px;">These connectors are configured in the selected profile and will be used for The Narrator's AI responses.</span>
                </div>
            </div>
            
            <script>
            (function(){
                const PROFILE_CONN = <?= json_encode($profilesConnById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                const LLM_LABELS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                
                function labelOf(id){ 
                    const k = String(id || ''); 
                    return (k && LLM_LABELS[k]) ? String(LLM_LABELS[k]) : '—'; 
                }
                
                function renderProfileConnectors(pid){
                    const box = document.getElementById('profile_llm_summary');
                    if (!box) return;
                    const pc = PROFILE_CONN[String(pid || '')] || null;
                    
                    const rows = [
                        ['🕹️ Standard:', labelOf(pc ? pc.llm_primary_id : null)],
                        ['🏃‍♂️‍➡️ Fast:', labelOf(pc ? pc.llm_secondary_id : null)],
                        ['💪 Power:', labelOf(pc ? pc.llm_tertiary_id : null)],
                        ['🧪 Experimental:', labelOf(pc ? pc.llm_quaternary_id : null)],
                        ['📓 Diary:', labelOf(pc ? pc.diary_connector_id : null)],
                        ['🧾 Formatter:', labelOf(pc ? pc.llm_formatter_id : null)]
                    ];
                    
                    let html = '';
                    rows.forEach(([k, v]) => {
                        html += '<div style="color:rgb(242,124,17); font-weight:600;">' + k + '</div>';
                        html += '<div>' + String(v || '—') + '</div>';
                    });
                    box.innerHTML = html;
                }
                
                // Update on profile change
                const profileSelect = document.getElementById('profile_id');
                if (profileSelect) {
                    profileSelect.addEventListener('change', function() {
                        renderProfileConnectors(this.value);
                    });
                }
            })();
            </script>
            
            <!-- Prompt Head Override Section -->
            <div class="content-section full-width-section">
                <h2>Prompt Head Override</h2>
                <label for="prompt_head">Custom Prompt Head</label>
                <textarea id="prompt_head" name="prompt_head" rows="5" placeholder="High-level system instructions injected before the core..."><?php echo htmlspecialchars($promptHead); ?></textarea>
                <span class="hint">System preamble inserted before other sections. This overrides the profile and global prompt head when The Narrator is active. Leave empty to use profile/global defaults.</span>
            </div>
            
            <!-- Character Fields Section -->
            <div class="content-section full-width-section">
                <h2>Character Description</h2>
                
                <!-- Dynamic Profile Section (inline) -->
                <div class="dynamic-profile-card">
                    <h3>♻️ Dynamic Profile Updates</h3>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?php echo $dynamicProfileEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Dynamic Profile</span>
                    </label>
                    <span class="hint">Allow systems to evolve the narrator profile based on gameplay events. Triggered by MCM Dynamic Profile Timer.</span>
                    
                    <label class="field-selection-label">Field Selection (choose 1-3)</label>
                    <span class="hint">Select which fields should be dynamically updated:</span>
                    
                    <div class="field-chips">
                        <label class="field-chip">
                            <input type="checkbox" name="dynamic_profile_fields[]" value="personality" <?php echo in_array('personality', $dynamicProfileFields) ? 'checked' : ''; ?>>
                            <span class="chip-text">Personality</span>
                        </label>
                        <label class="field-chip">
                            <input type="checkbox" name="dynamic_profile_fields[]" value="speechstyle" <?php echo in_array('speechstyle', $dynamicProfileFields) ? 'checked' : ''; ?>>
                            <span class="chip-text">Speech Style</span>
                        </label>
                        <label class="field-chip">
                            <input type="checkbox" name="dynamic_profile_fields[]" value="goals" <?php echo in_array('goals', $dynamicProfileFields) ? 'checked' : ''; ?>>
                            <span class="chip-text">Goals</span>
                        </label>
                    </div>
                    <span class="hint">Recommended: Select only 1-3 fields. Updates use DYNAMIC_PROMPT_* prompts from Global Settings.</span>
                </div>
                
                <label for="core">Core Summary</label>
                <textarea id="core" name="core" rows="3" placeholder="Quick summary of The Narrator's persona..."><?php echo htmlspecialchars($core); ?></textarea>
                <span class="hint">Brief summary of The Narrator's role and personality.</span>
                
                <label for="background">Background</label>
                <textarea id="background" name="background" rows="4" placeholder="Background description..."><?php echo htmlspecialchars($background); ?></textarea>
                <span class="hint">Detailed background and history of The Narrator.</span>
                
                <label for="personality">Personality</label>
                <textarea id="personality" name="personality" rows="3" placeholder="Personality traits..."><?php echo htmlspecialchars($personality); ?></textarea>
                <span class="hint">Behavioral traits and personality characteristics.</span>
                
                <label for="speechstyle">Speech Style</label>
                <textarea id="speechstyle" name="speechstyle" rows="2" placeholder="How The Narrator speaks..."><?php echo htmlspecialchars($speechstyle); ?></textarea>
                <span class="hint">How The Narrator communicates and speaks.</span>
                
                <label for="goals">Goals</label>
                <textarea id="goals" name="goals" rows="3" placeholder="Current objectives..."><?php echo htmlspecialchars($goals); ?></textarea>
                <span class="hint">Current goals and objectives for The Narrator.</span>
            </div>
        </form>
    </div>
</main>

<?php
if (!$isEmbed) {
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
    $buffer = ob_get_contents();
    ob_end_clean();
    $title = $TITLE;
    $buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
    echo $buffer;
}
?>

