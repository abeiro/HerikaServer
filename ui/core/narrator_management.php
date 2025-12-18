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
        $saveMessage = 'Narrator settings saved successfully!';
    } catch (Exception $e) {
        $saveSuccess = false;
        $saveMessage = 'Error saving narrator settings: ' . $e->getMessage();
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
$booksOnlyNarrator = $narrator->getBool('books_only_narrator', false);
$hideFromContext = $narrator->getBool('hide_from_context', false);

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
        width: 100%;
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
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin-bottom: 15px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header p {
        color: #e0e0e0;
        font-size: 1.1em;
        margin: 10px 0;
    }

    /* Content Layout */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .content-section {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.4em;
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    /* Form Styling */
    .content-section label {
        display: block;
        font-size: 13px;
        color: rgb(242, 124, 17);
        font-weight: bold;
        margin-bottom: 4px;
        margin-top: 12px;
    }

    .content-section input[type="text"],
    .content-section input[type="number"] {
        background-color: #333;
        color: #fff;
        border: 1px solid #444;
        padding: 8px;
        border-radius: 4px;
        width: 100%;
        margin-bottom: 8px;
    }
    
    .content-section textarea {
        min-height: 80px;
        font-family: inherit;
        resize: vertical;
        background-color: #333;
        color: #fff;
        border: 1px solid #444;
        padding: 8px;
        border-radius: 4px;
        width: 100%;
        margin-bottom: 8px;
    }
    
    .content-section select {
        background-color: #333;
        color: #fff;
        border: 1px solid #444;
        padding: 8px;
        border-radius: 4px;
        width: 100%;
        margin-bottom: 8px;
    }

    .content-section input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-right: 8px;
        cursor: pointer;
    }

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

    .hint {
        font-size: 12px;
        color: #bbb;
        margin-top: 4px;
        display: block;
    }

    /* Button Styling */
    .btn-save {
        background-color: rgba(32, 122, 74, 0.8);
        color: #fff;
        border: 1px solid rgba(138, 155, 182, 0.3);
        border-radius: 8px;
        padding: 12px 24px;
        cursor: pointer;
        font-size: 15px;
        margin-bottom: 20px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-save:hover {
        background-color: rgba(42, 142, 94, 0.9);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
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
            <button type="submit" class="btn-save" name="save_narrator" value="1">Save Narrator Settings</button>

            <div class="content-grid">
                <!-- Core Settings Section -->
                <div class="content-section">
                    <h2>Core Settings</h2>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                        <label for="enabled">Enable Narrator</label>
                    </div>
                    <span class="hint">Enable or disable the narrator system entirely.</span>
                    
                    <div class="checkbox-group" style="margin-top: 16px;">
                        <input type="checkbox" id="welcome_enabled" name="welcome_enabled" value="1" <?php echo $welcomeEnabled ? 'checked' : ''; ?>>
                        <label for="welcome_enabled">Welcome Message on Load</label>
                    </div>
                    <span class="hint">The Narrator will give you a quick recap of what happened previously after you have loaded a save game. Has a 10 minute IRL cooldown so it's not annoying.</span>
                    
                    <div class="checkbox-group" style="margin-top: 16px;">
                        <input type="checkbox" id="books_only_narrator" name="books_only_narrator" value="1" <?php echo $booksOnlyNarrator ? 'checked' : ''; ?>>
                        <label for="books_only_narrator">Only Narrator Summarizes Books</label>
                    </div>
                    <span class="hint">The Narrator will be the only one to summarize books.</span>
                    
                    <div class="checkbox-group" style="margin-top: 16px;">
                        <input type="checkbox" id="hide_from_context" name="hide_from_context" value="1" <?php echo $hideFromContext ? 'checked' : ''; ?>>
                        <label for="hide_from_context">Hide Narrator Dialogue from NPC Context</label>
                    </div>
                    <span class="hint">Hide Narrator-spoken dialogue lines from NPC context.</span>
                </div>

                <!-- Random Narration Section -->
                <div class="content-section">
                    <h2>Random Narration</h2>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="random_enabled" name="random_enabled" value="1" <?php echo $randomEnabled ? 'checked' : ''; ?>>
                        <label for="random_enabled">Enable Random Narration</label>
                    </div>
                    <span class="hint">Enable random Narrator interjections. The Narrator will occasionally add visual scene descriptions during conversations.</span>
                    
                    <label for="random_chance" style="margin-top: 16px;">Random Narration Chance (%)</label>
                    <input type="number" id="random_chance" name="random_chance" value="<?php echo htmlspecialchars((string)$randomChance); ?>" min="1" max="100">
                    <span class="hint">Probability (1-100) that the Narrator will interject with a scene description. Default: 15%</span>
                    
                    <label for="random_cooldown" style="margin-top: 16px;">Random Narration Cooldown</label>
                    <input type="number" id="random_cooldown" name="random_cooldown" value="<?php echo htmlspecialchars((string)$randomCooldown); ?>" min="0" max="10">
                    <span class="hint">Minimum number of conversation rounds between Narrator interjections. Prevents narration spam. Range: 0-10, Default: 2</span>
                </div>
            </div>
            
            <!-- Profile & Voice Section -->
            <div class="content-grid" style="margin-top: 20px;">
                <div class="content-section">
                    <h2>Profile & Voice</h2>
                    
                    <label for="profile_id">Profile</label>
                    <select id="profile_id" name="profile_id" style="background-color: #333; color: #fff; border: 1px solid #444; padding: 8px; border-radius: 4px; width: 100%; margin-bottom: 8px;">
                        <?php foreach ($allProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars((string)$profile['id']); ?>" <?php echo ($profileId == $profile['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($profile['label'] ?? 'Profile ' . $profile['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">LLM connector profile for The Narrator.</span>
                    
                    <label for="voiceid" style="margin-top: 16px;">Voice ID</label>
                    <input type="text" id="voiceid" name="voiceid" value="<?php echo htmlspecialchars($voiceid); ?>" placeholder="TheNarrator">
                    <span class="hint">TTS voice identifier for The Narrator.</span>
                    
                    <label for="oghma_knowledge" style="margin-top: 16px;">Oghma Knowledge Tags</label>
                    <input type="text" id="oghma_knowledge" name="oghma_knowledge" placeholder="Comma-separated knowledge tags (e.g., knowall, knowsome, knownone)" value="<?php echo htmlspecialchars($oghmaKnowledge); ?>" style="background-color: #333; color: #fff; border: 1px solid #444; padding: 8px; border-radius: 4px; width: 100%; margin-bottom: 8px;">
                    <span class="hint">Comma-separated knowledge tags used by Oghma systems for knowledge lookup restrictions.</span>
                </div>
            </div>
            
            <!-- Prompt Head Override Section -->
            <div class="content-section full-width-section" style="margin-top: 20px;">
                <h2>Prompt Head Override</h2>
                <label for="prompt_head">Custom Prompt Head</label>
                <textarea id="prompt_head" name="prompt_head" rows="5" placeholder="High-level system instructions injected before the core..."><?php echo htmlspecialchars($promptHead); ?></textarea>
                <span class="hint">System preamble inserted before other sections. This overrides the profile and global prompt head when The Narrator is active. Leave empty to use profile/global defaults.</span>
            </div>
            
            <!-- Character Fields Section -->
            <div class="content-section full-width-section" style="margin-top: 20px;">
                <h2>Character Description</h2>
                
                <label for="core">Core Summary</label>
                <textarea id="core" name="core" rows="3" placeholder="Quick summary of The Narrator's persona..."><?php echo htmlspecialchars($core); ?></textarea>
                <span class="hint">Brief summary of The Narrator's role and personality.</span>
                
                <label for="background" style="margin-top: 16px;">Background</label>
                <textarea id="background" name="background" rows="4" placeholder="Background description..."><?php echo htmlspecialchars($background); ?></textarea>
                <span class="hint">Detailed background and history of The Narrator.</span>
                
                <label for="personality" style="margin-top: 16px;">Personality</label>
                <textarea id="personality" name="personality" rows="3" placeholder="Personality traits..."><?php echo htmlspecialchars($personality); ?></textarea>
                <span class="hint">Behavioral traits and personality characteristics.</span>
                
                <label for="speechstyle" style="margin-top: 16px;">Speech Style</label>
                <textarea id="speechstyle" name="speechstyle" rows="2" placeholder="How The Narrator speaks..."><?php echo htmlspecialchars($speechstyle); ?></textarea>
                <span class="hint">How The Narrator communicates and speaks.</span>
                
                <label for="goals" style="margin-top: 16px;">Goals</label>
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

