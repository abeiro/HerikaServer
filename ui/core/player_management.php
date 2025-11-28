<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");

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
$player = new Player();

$saveSuccess = false;
$saveMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_player'])) {
    try {
        // Save player name
        if (isset($_POST['player_name'])) {
            $player->set('player_name', $_POST['player_name']);
        }
        // Save player info
        if (isset($_POST['appearance'])) {
            $player->set('appearance', $_POST['appearance']);
        }
        if (isset($_POST['speech_style'])) {
            $player->set('speech_style', $_POST['speech_style']);
        }
        
        // Save any editable stats if provided
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'stat_') === 0) {
                $statKey = substr($key, 5); // Remove 'stat_' prefix
                $player->set($statKey, $value);
            }
        }
        
        $saveSuccess = true;
        $saveMessage = 'Player settings saved successfully!';
    } catch (Exception $e) {
        $saveSuccess = false;
        $saveMessage = 'Error saving player settings: ' . $e->getMessage();
    }
}

// Load all player data
$allPlayerData = $player->getAll();

// Extract main fields
$playerName = $allPlayerData['player_name'] ?? 'Unknown';
$appearance = $allPlayerData['appearance'] ?? '';
$speechStyle = $allPlayerData['speech_style'] ?? '';

// Organize Skyrim stats into categories
$statCategories = [
    'Core Stats' => [
        'Days Passed', 'Hours Slept', 'Hours Waited'
    ],
    'Exploration' => [
        'Locations Discovered', 'Dungeons Cleared', 'Standing Stones Found'
    ],
    'Economy' => [
        'Gold Found', 'Most Gold Carried', 'Chests Looted',
        'Barters', 'Stores Invested In'
    ],
    'Character Development' => [
        'Skill Increases', 'Skill Books Read', 'Training Sessions', 'Books Read'
    ],
    'Lifestyle' => [
        'Food Eaten', 'Horses Owned', 'Houses Owned'
    ],
    'Social' => [
        'Persuasions', 'Bribes', 'Intimidations'
    ],
    'Supernatural' => [
        'Werewolf Transformations', 'Days As Werewolf',
        'Necks Bitten', 'Days As Vampire', 'Diseases Contracted'
    ],
    'Quests - Main' => [
        'Quests Completed', 'Main Quests Completed', 'Side Quests Completed',
        'Misc Objectives Completed', 'Questlines Completed'
    ],
    'Quests - Factions' => [
        'The Companions Quests Completed',
        'College of Winterhold Quests Completed',
        'Thieves\' Guild Quests Completed',
        'Thieves\' Guild Special Jobs Completed',
        'The Dark Brotherhood Quests Completed',
        'Dark Brotherhood Contracts Completed',
        'Bard\'s College Quests Completed',
        'Blades Quests Completed'
    ],
    'Quests - Political' => [
        'Civil War Quests Completed',
        'Imperial Legion Quests Completed',
        'Stormcloaks Quests Completed',
        'Forsworn Quests Completed'
    ],
    'Quests - DLC' => [
        'Daedric Quests Completed',
        'Dragonborn Quests Completed DB',
        'Dawnguard Quests Completed DG'
    ],
    'Combat' => [
        'Mauls'
    ]
];

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$isEmbed) {
    require_once(__DIR__."/../profile_loader.php");
    $TITLE = "Player Management";
    ob_start();
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding: <?php echo $isEmbed ? '10px' : '80px 10px 10px'; ?>; }
.player-title { margin: 0 0 16px 0; font-size: 28px; color: #e9efff; }
.provider-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 16px; margin-bottom: 16px; }
.provider-card { background: #2a2a2a; border: 1px solid #4a4a4a; border-radius: 8px; padding: 16px; }
.provider-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.provider-title { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 600; color: #e9efff; }
.provider-icon { width: 28px; height: 28px; border-radius: 6px; background: #3a3a3a; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.provider-body { display: flex; flex-direction: column; gap: 8px; }
.provider-body label { font-size: 13px; color: #cfd8e3; margin-bottom: 4px; }
.provider-body input[type="text"], 
.provider-body textarea { 
    background-color: #333; 
    color: #fff; 
    border: 1px solid #444; 
    padding: 8px; 
    border-radius: 4px; 
    width: 100%; 
}
.provider-body textarea { min-height: 100px; font-family: inherit; resize: vertical; }
.hint { font-size: 12px; color: #bbb; margin-top: 4px; }
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
.toast-notification.error { background: #c03; }
.btn-save { 
    background-color: rgba(32, 122, 74, 0.8); 
    color: #fff; 
    border: 1px solid rgba(138, 155, 182, 0.3); 
    border-radius: 8px; 
    padding: 10px 20px; 
    cursor: pointer; 
    font-size: 14px; 
    margin-bottom: 16px; 
}
.btn-save:hover { background-color: rgba(42, 142, 94, 0.9); }
.stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
.stat-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 8px; background: #1a1a1a; border-radius: 4px; }
.stat-label { font-size: 12px; color: #cfd8e3; }
.stat-value { font-size: 13px; color: #fff; font-weight: 600; }
.read-only-note { 
    background: #3a3a3a; 
    border-left: 3px solid #8a9bb6; 
    padding: 8px 12px; 
    margin-bottom: 12px; 
    border-radius: 4px; 
    font-size: 13px; 
    color: #cfd8e3; 
}

@media (max-width: 900px) {
    .provider-grid { grid-template-columns: 1fr; }
}
</style>

<main>
    <h1 class="player-title">👤 Player Management</h1>
    
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

    <form method="post" action="">
        <button type="submit" class="btn-save" name="save_player" value="1">💾 Save Player Settings</button>

        <div class="provider-grid">
            <!-- Player Info Card -->
            <div class="provider-card">
                <div class="provider-head">
                    <div class="provider-title">
                        <div class="provider-icon">🏷️</div>
                        <div>Player Information</div>
                    </div>
                </div>
                <div class="provider-body">
                    <label for="player_name">Player Name</label>
                    <input type="text" id="player_name" name="player_name" value="<?php echo htmlspecialchars($playerName); ?>">
                    <div class="hint">Your character's name. This is automatically updated when you load a save in Skyrim, but you can also set it manually here.</div>
                </div>
            </div>

            <!-- Appearance Card -->
            <div class="provider-card">
                <div class="provider-head">
                    <div class="provider-title">
                        <div class="provider-icon">👤</div>
                        <div>Player Appearance</div>
                    </div>
                </div>
                <div class="provider-body">
                    <label for="appearance">Physical Description</label>
                    <textarea id="appearance" name="appearance" placeholder="Describe your character's appearance..."><?php echo htmlspecialchars($appearance); ?></textarea>
                    <div class="hint">Physical description of your character use for AI context..</div>
                </div>
            </div>

            <!-- Speech Style Card -->
            <div class="provider-card">
                <div class="provider-head">
                    <div class="provider-title">
                        <div class="provider-icon">💬</div>
                        <div>Speech Style</div>
                    </div>
                </div>
                <div class="provider-body">
                    <label for="speech_style">How Your Character Speaks</label>
                    <textarea id="speech_style" name="speech_style" placeholder="Describe how your character speaks and communicates..."><?php echo htmlspecialchars($speechStyle); ?></textarea>
                    <div class="hint">Used for Auto Chat mode to guide the AI to speak for your character.</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-save" name="save_player" value="1">💾 Save Player Settings</button>
    </form>
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

