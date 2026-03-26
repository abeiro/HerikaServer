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
        if (isset($_POST['bio'])) {
            $player->set('bio', $_POST['bio']);
        }
        $bioKnownByAll = (isset($_POST['bio_known_by_all']) && $_POST['bio_known_by_all'] === 'true') ? 'true' : 'false';
        $player->set('bio_known_by_all', $bioKnownByAll);
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
$bio = $allPlayerData['bio'] ?? '';
$bioKnownByAll = ($allPlayerData['bio_known_by_all'] ?? 'false') === 'true';
$speechStyle = $allPlayerData['speech_style'] ?? '';

// Load JSON data (equipment, inventory, skills, stats)
$equipment = $player->getJson('equipment') ?? [];
$inventory = $player->getJson('inventory') ?? [];
$skills = $player->getJson('skills') ?? [];
$stats = $player->getJson('stats') ?? [];

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
        max-width: 1400px;
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
        font-size: 0.95em;
        margin: 4px 0;
    }

    /* Content Layout */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .content-grid.two-col {
        grid-template-columns: 1fr 1fr;
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

    .content-grid + .full-width-section,
    .full-width-section + .content-grid,
    .full-width-section + .full-width-section,
    .content-grid + .content-grid {
        margin-top: 20px;
    }

    .full-width-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 18px;
        font-size: 1.5em;
        text-align: center;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }

    /* Form Styling */
    .content-section label {
        display: block;
        font-size: 13px;
        color: rgb(242, 124, 17);
        font-weight: 600;
        margin-bottom: 6px;
        margin-top: 14px;
    }

    .content-section label:first-of-type {
        margin-top: 0;
    }

    .content-section input[type="text"], 
    .content-section textarea { 
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
    .content-section textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    .content-section textarea { 
        min-height: 100px; 
        font-family: inherit; 
        resize: vertical; 
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

    /* Stats Grid */
    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(2, 1fr);
        gap: 12px; 
    }

    .stat-card { 
        padding: 14px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 8px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.02);
    }

    .stat-card:hover {
        border-color: #3a3a3a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255,255,255,0.03);
    }

    .stat-card-title { 
        font-size: 11px; 
        color: #888; 
        text-transform: uppercase; 
        margin-bottom: 8px;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .stat-card-value { 
        font-size: 20px; 
        color: #fff; 
        font-weight: 600; 
    }

    .stat-bar-container { 
        width: 100%; 
        height: 7px;
        background: rgba(20, 20, 20, 0.8);
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .stat-bar { 
        height: 100%; 
        background: linear-gradient(90deg, #207a4a, #2aa65e); 
        border-radius: 3px; 
        transition: width 0.3s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.2);
    }

    .stat-bar.health { 
        background: linear-gradient(90deg, #c03, #e04);
    }
    
    .stat-bar.magicka { 
        background: linear-gradient(90deg, #2070c0, #3090e0);
    }
    
    .stat-bar.stamina { 
        background: linear-gradient(90deg, #20a020, #30c030);
    }

    /* Equipment Grid */
    .equipment-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); 
        gap: 10px;
        max-width: 900px;
        margin: 0 auto;
    }

    .equipment-slot { 
        padding: 12px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 8px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.02);
    }

    .equipment-slot:hover {
        border-color: #3a3a3a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255,255,255,0.03);
    }

    .equipment-slot-name { 
        font-size: 11px; 
        color: #888;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .equipment-item-name { 
        font-size: 13px; 
        color: #e9efff;
        font-weight: 500;
    }

    .equipment-empty { 
        font-size: 13px; 
        color: #555;
        font-style: italic; 
    }

    /* Inventory List */
    .inventory-list { 
        max-height: 400px; 
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 4px;
    }
    
    .inventory-list::-webkit-scrollbar {
        width: 8px;
    }

    .inventory-list::-webkit-scrollbar-track {
        background: rgba(26, 26, 26, 0.5);
        border-radius: 4px;
    }

    .inventory-list::-webkit-scrollbar-thumb {
        background: #3a3a3a;
        border-radius: 4px;
    }

    .inventory-list::-webkit-scrollbar-thumb:hover {
        background: #4a4a4a;
    }
    
    .inventory-container {
        max-width: 100%;
    }

    .inventory-item { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 10px 12px;
        background: linear-gradient(90deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 6px;
        margin-bottom: 6px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
    }

    .inventory-item:hover {
        border-color: #3a3a3a;
        transform: translateX(4px);
        background: linear-gradient(90deg, rgba(30, 30, 30, 0.95), rgba(24, 24, 24, 0.98));
    }

    .inventory-item-name { 
        font-size: 13px; 
        color: #e9efff;
        font-weight: 500;
    }

    .inventory-item-count { 
        font-size: 12px; 
        color: #8a9bb6; 
        font-weight: 600; 
        background: rgba(20, 20, 20, 0.8);
        padding: 4px 10px;
        border-radius: 4px;
        border: 1px solid rgba(138, 155, 182, 0.2);
    }

    /* Skills Grid */
    .skills-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
        gap: 10px;
        max-width: 900px;
        margin: 0 auto;
    }

    .skill-item { 
        padding: 10px 12px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 8px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.02);
    }

    .skill-item:hover {
        border-color: #3a3a3a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255,255,255,0.03);
    }

    .skill-name { 
        font-size: 12px; 
        color: #888;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .skill-value { 
        font-size: 18px;
        color: #e9efff;
        font-weight: 600; 
    }

    .no-data { 
        padding: 30px 20px;
        text-align: center; 
        color: #666; 
        font-style: italic;
        line-height: 1.6;
    }

    .no-data ul {
        color: #888;
        font-size: 0.95em;
    }

    .no-data strong {
        color: #999;
    }

    .no-data ul {
        text-align: left;
        margin: 10px auto;
        display: inline-block;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .content-grid.two-col {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            padding: 18px 15px;
        }
        
        .content-section {
            padding: 18px;
        }

        .stats-grid,
        .equipment-grid,
        .skills-grid {
            grid-template-columns: 1fr;
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

        .page-header p {
            font-size: 0.85em;
        }

        .content-section {
            padding: 15px;
        }

        .stats-grid,
        .equipment-grid,
        .skills-grid {
            grid-template-columns: 1fr;
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
             👤 Player Management
        </h1>
        <p>Manage your character's information and view in-game statistics</p>
        <p>Changes made here will be used by AI NPCs to understand your character better</p>
    </div>

    <form method="post" action="">
        <button type="submit" class="btn-save" name="save_player" value="1">💾 Save Player Settings</button>

        <div class="content-grid">
            <!-- Player Info Section -->
            <div class="content-section">
                <h2>🏷️ Player Information</h2>
                <label for="player_name">Player Name</label>
                <input type="text" id="player_name" name="player_name" value="<?php echo htmlspecialchars($playerName); ?>">
                <span class="hint">Your character's name.</span>
            </div>

            <!-- Appearance Section -->
            <div class="content-section">
                <h2>👤 Player Appearance</h2>
                <label for="appearance">Physical Description</label>
                <textarea id="appearance" name="appearance" placeholder="Describe your character's appearance..."><?php echo htmlspecialchars($appearance); ?></textarea>
                <span class="hint">Physical description of your character used for AI context.</span>
            </div>

            <!-- Bio Section -->
            <div class="content-section">
                <h2>📜 Player Bio</h2>
                <label for="bio">Character Bio</label>
                <textarea id="bio" name="bio" placeholder="Describe your character's background and story..."><?php echo htmlspecialchars($bio); ?></textarea>
                <span class="hint">Backstory and character context. Empty by default.</span>
                <div style="margin-top: 10px;">
                    <input type="hidden" name="bio_known_by_all" value="false">
                    <label for="bio_known_by_all" style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                        <input
                            type="checkbox"
                            id="bio_known_by_all"
                            name="bio_known_by_all"
                            value="true"
                            <?php echo $bioKnownByAll ? 'checked' : ''; ?>
                        >
                        Player Biography Known by All
                    </label>
                </div>
                <span class="hint">If enabled, all NPCs know this bio. If disabled, only The Narrator knows it.</span>
            </div>

            <!-- Speech Style Section -->
            <div class="content-section">
                <h2>💬 Speech Style</h2>
                <label for="speech_style">How Your Character Speaks</label>
                <textarea id="speech_style" name="speech_style" placeholder="Describe how your character speaks and communicates..."><?php echo htmlspecialchars($speechStyle); ?></textarea>
                <span class="hint">Used by Auto Chat mode. The AI rewrites your input into dialogue that matches your character's voice and personality.</span>
            </div>
        </div>
    </form>

    <!-- Read-only Game Data Section -->
    <div class="full-width-section">
        <h2>📊 Player Statistics</h2>
    </div>

    <div class="content-grid two-col">
        <!-- Inventory Card -->
        <?php if (!empty($inventory)): ?>
        <div class="content-section">
            <h2>Inventory (<?php echo count($inventory); ?> items)</h2>
            <div class="inventory-container">
                <div class="inventory-list">
                    <?php 
                    // Sort inventory by name
                    usort($inventory, function($a, $b) {
                        return strcmp($a['name'] ?? '', $b['name'] ?? '');
                    });
                    foreach ($inventory as $item): 
                    ?>
                    <div class="inventory-item">
                        <span class="inventory-item-name"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Item'); ?></span>
                        <span class="inventory-item-count">×<?php echo intval($item['count'] ?? 1); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="content-section">
            <h2>Inventory</h2>
            <div class="no-data">No inventory data available. Play the game to sync your inventory.</div>
        </div>
        <?php endif; ?>

        <!-- Equipment Card -->
        <?php
        $equipmentSlots = [
            'helmet' => 'Helmet',
            'armor' => 'Armor',
            'boots' => 'Boots',
            'gloves' => 'Gloves',
            'amulet' => 'Amulet',
            'ring' => 'Ring',
            'cape' => 'Cape',
            'backpack' => 'Backpack',
            'left_hand' => 'Left Hand',
            'right_hand' => 'Right Hand'
        ];

        $hasEquipment = false;
        foreach ($equipmentSlots as $slot => $label) {
            $itemName = isset($equipment[$slot]) && !empty($equipment[$slot]) ? $equipment[$slot] : null;
            if ($itemName) {
                $hasEquipment = true;
                break;
            }
        }
        ?>
        <div class="content-section">
            <h2>Equipment</h2>
            <?php if (!empty($equipment)): ?>
                <?php if ($hasEquipment): ?>
                    <div class="equipment-grid">
                        <?php foreach ($equipmentSlots as $slot => $label):
                            $itemName = isset($equipment[$slot]) && !empty($equipment[$slot]) ? $equipment[$slot] : null;
                        ?>
                        <div class="equipment-slot">
                            <div class="equipment-slot-name"><?php echo $label; ?></div>
                            <?php if ($itemName): ?>
                                <div class="equipment-item-name"><?php echo htmlspecialchars($itemName); ?></div>
                            <?php else: ?>
                                <div class="equipment-empty">Empty</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p><strong>No equipment currently equipped.</strong></p>
                        <p>If you have items equipped in-game but they are not showing here:</p>
                        <ul>
                            <li>Make sure you are in-game (not in a menu)</li>
                            <li>Talk to any NPC to trigger a sync</li>
                            <li>Or wait a few seconds for auto-sync</li>
                            <li>Then refresh this page</li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">No equipment data available. Play the game to sync your equipment.</div>
            <?php endif; ?>
        </div>

        <!-- Stats Card -->
        <?php if (!empty($stats)): ?>
        <div class="content-section">
            <h2>Character Stats</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-title">Level</div>
                    <div class="stat-card-value"><?php echo intval($stats['level'] ?? 1); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Health</div>
                    <div class="stat-card-value"><?php 
                        $hp = floatval($stats['health'] ?? 0);
                        $hpMax = floatval($stats['health_max'] ?? 1);
                        echo round($hp) . ' / ' . round($hpMax);
                    ?></div>
                    <div class="stat-bar-container">
                        <div class="stat-bar health" style="width: <?php echo min(100, ($hp / max(1, $hpMax)) * 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Magicka</div>
                    <div class="stat-card-value"><?php 
                        $mp = floatval($stats['magicka'] ?? 0);
                        $mpMax = floatval($stats['magicka_max'] ?? 1);
                        echo round($mp) . ' / ' . round($mpMax);
                    ?></div>
                    <div class="stat-bar-container">
                        <div class="stat-bar magicka" style="width: <?php echo min(100, ($mp / max(1, $mpMax)) * 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Stamina</div>
                    <div class="stat-card-value"><?php 
                        $sp = floatval($stats['stamina'] ?? 0);
                        $spMax = floatval($stats['stamina_max'] ?? 1);
                        echo round($sp) . ' / ' . round($spMax);
                    ?></div>
                    <div class="stat-bar-container">
                        <div class="stat-bar stamina" style="width: <?php echo min(100, ($sp / max(1, $spMax)) * 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- Skills Section -->
    <?php if (!empty($skills)): ?>
    <div class="content-section full-width-section">
        <h2>⭐ Skills</h2>
            <div class="skills-grid">
                <?php 
                // Sort skills by value descending
                arsort($skills);
                foreach ($skills as $skillName => $skillValue): 
                    $displayName = ucwords(str_replace('_', ' ', $skillName));
                ?>
                <div class="skill-item">
                    <div class="skill-name"><?php echo htmlspecialchars($displayName); ?></div>
                    <div class="skill-value"><?php echo round($skillValue); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
    </div>
    <?php endif; ?>
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