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
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .content-grid.two-col {
        grid-template-columns: 1fr 1fr;
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

    .full-width-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.6em;
        text-align: center;
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
    .content-section textarea { 
        background-color: #333; 
        color: #fff; 
        border: 1px solid #444; 
        padding: 8px; 
        border-radius: 4px; 
        width: 100%; 
        margin-bottom: 8px;
    }

    .content-section textarea { 
        min-height: 100px; 
        font-family: inherit; 
        resize: vertical; 
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

    /* Stats Grid */
    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
        gap: 12px; 
    }

    .stat-card { 
        padding: 12px; 
        background: #1a1a1a; 
        border-radius: 4px; 
        border: 1px solid #333;
    }

    .stat-card-title { 
        font-size: 11px; 
        color: #999; 
        text-transform: uppercase; 
        margin-bottom: 6px; 
    }

    .stat-card-value { 
        font-size: 20px; 
        color: #fff; 
        font-weight: 600; 
    }

    .stat-bar-container { 
        width: 100%; 
        height: 6px; 
        background: #2a2a2a; 
        border-radius: 3px; 
        margin-top: 6px; 
        overflow: hidden; 
    }

    .stat-bar { 
        height: 100%; 
        background: linear-gradient(90deg, #207a4a, #2aa65e); 
        border-radius: 3px; 
        transition: width 0.3s; 
    }

    .stat-bar.health { background: linear-gradient(90deg, #c03, #e04); }
    .stat-bar.magicka { background: linear-gradient(90deg, #2070c0, #3090e0); }
    .stat-bar.stamina { background: linear-gradient(90deg, #20a020, #30c030); }

    /* Equipment Grid */
    .equipment-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); 
        gap: 8px; 
    }

    .equipment-slot { 
        padding: 8px; 
        background: #1a1a1a; 
        border-radius: 4px; 
        border: 1px solid #333;
    }

    .equipment-slot-name { 
        font-size: 11px; 
        color: #999; 
        text-transform: uppercase; 
        margin-bottom: 4px; 
    }

    .equipment-item-name { 
        font-size: 13px; 
        color: #fff; 
    }

    .equipment-empty { 
        font-size: 13px; 
        color: #666; 
        font-style: italic; 
    }

    /* Inventory List */
    .inventory-list { 
        max-height: 400px; 
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .inventory-container {
        max-width: 100%;
    }

    .inventory-item { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 6px 8px; 
        background: #1a1a1a; 
        border-radius: 4px; 
        margin-bottom: 4px; 
        border: 1px solid #333;
    }

    .inventory-item-name { 
        font-size: 13px; 
        color: #fff; 
    }

    .inventory-item-count { 
        font-size: 12px; 
        color: #8a9bb6; 
        font-weight: 600; 
        background: #2a2a2a; 
        padding: 2px 8px; 
        border-radius: 3px; 
    }

    /* Skills Grid */
    .skills-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
        gap: 8px; 
    }

    .skill-item { 
        padding: 6px 8px; 
        background: #1a1a1a; 
        border-radius: 4px; 
        border: 1px solid #333;
    }

    .skill-name { 
        font-size: 12px; 
        color: #cfd8e3; 
        margin-bottom: 2px; 
    }

    .skill-value { 
        font-size: 16px; 
        color: #fff; 
        font-weight: 600; 
    }

    .no-data { 
        padding: 20px; 
        text-align: center; 
        color: #666; 
        font-style: italic; 
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

            <!-- Speech Style Section -->
            <div class="content-section">
                <h2>💬 Speech Style</h2>
                <label for="speech_style">How Your Character Speaks</label>
                <textarea id="speech_style" name="speech_style" placeholder="Describe how your character speaks and communicates..."><?php echo htmlspecialchars($speechStyle); ?></textarea>
                <span class="hint">Used for Auto Chat mode to guide the AI to speak for your character.</span>
            </div>
        </div>
    </form>

    <!-- Read-only Game Data Section -->
    <div class="full-width-section">
        <h2>📊 Player Statistics</h2>
    </div>

    <div class="content-grid two-col">
        <!-- Stats Card -->
        <?php if (!empty($stats)): ?>
        <div class="content-section">
            <h2>📈 Character Stats</h2>
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

        <!-- Equipment Card -->
        <?php if (!empty($equipment)): ?>
        <div class="content-section full-width-section">
            <h2>⚔️ Equipment</h2>
            <?php
            $equipmentSlots = [
                'helmet' => '🪖 Helmet',
                'armor' => '🛡️ Armor',
                'boots' => '👢 Boots',
                'gloves' => '🧤 Gloves',
                'amulet' => '📿 Amulet',
                'ring' => '💍 Ring',
                'cape' => '🧥 Cape',
                'backpack' => '🎒 Backpack',
                'left_hand' => '🤚 Left Hand',
                'right_hand' => '✋ Right Hand'
            ];
            
            // Check if any equipment is actually equipped
            $hasEquipment = false;
            foreach ($equipmentSlots as $slot => $label) {
                $itemName = isset($equipment[$slot]) && !empty($equipment[$slot]) ? $equipment[$slot] : null;
                if ($itemName) {
                    $hasEquipment = true;
                    break;
                }
            }
            ?>
            
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
                    <p>If you have items equipped in-game but they're not showing here:</p>
                    <ul style="text-align: left; margin: 10px auto; display: inline-block;">
                        <li>Make sure you're in-game (not in a menu)</li>
                        <li>Talk to any NPC to trigger a sync</li>
                        <li>Or wait a few seconds for auto-sync</li>
                        <li>Then refresh this page</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="content-section full-width-section">
            <h2>⚔️ Equipment</h2>
            <div class="no-data">No equipment data available. Play the game to sync your equipment.</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Skills Section -->
    <?php if (!empty($skills)): ?>
    <div class="content-section full-width-section" style="margin-top: 16px;">
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

    <!-- Inventory Section -->
    <div class="content-grid two-col" style="margin-top: 16px;">
        <!-- Inventory Card -->
        <?php if (!empty($inventory)): ?>
        <div class="content-section">
            <h2>🎒 Inventory (<?php echo count($inventory); ?> items)</h2>
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
            <h2>🎒 Inventory</h2>
            <div class="no-data">No inventory data available. Play the game to sync your inventory.</div>
        </div>
        <?php endif; ?>
    </div>
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

