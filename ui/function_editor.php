<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "⚙️ CHIM - AI Action Editor";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;
if (!$isEmbed) {
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
}

if (file_exists(__DIR__."/../functions/user_pref.json")) {
    $currentOnes=json_decode(file_get_contents(__DIR__."/../functions/user_pref.json"),true);
} else {
    $currentOnes=[];
}

// Arrays without the commented ones
$npcFunctions = [
    'Inspect',
    'InspectSurroundings',
    'OpenInventory',
    'OpenInventory2',
    'Attack',
    'AttackHunt',
    'TravelTo',
    'Follow',
    'CheckInventory',
    'Relax',
    'TakeASeat',
    'IncreaseWalkSpeed',
    'DecreaseWalkSpeed',
    'WaitHere',
    'ComeCloser',
    'TakeGoldFromPlayer',
    'RentRoom',
    'HireCarriage',
    'HireFerry',
    'FollowPlayer',
    'Brawl',
    'GiveGoldTo',
    'GiveItemTo',
    'PickupItem',
    'CastSpell',
    'MakeFollower',
    'Toast',
    'Drink',
    'Training',
    'Surrender',
    'EndConversation',
    'MoveTo',
];

$playerFunctions = [
    'Inspect',
    'InspectSurroundings',
    'OpenInventory',
    'OpenInventory2',
    'Attack',
    'AttackHunt',
    'TravelTo',
    'CheckInventory',
    'SheatheWeapon',
    'Relax',
    'TakeASeat',
    'ReadQuestJournal',
    'IncreaseWalkSpeed',
    'DecreaseWalkSpeed',
    'WaitHere',
    'SetCurrentTask',
    'ComeCloser',
    'TakeGoldFromPlayer',
    'RentRoom',
    'HireCarriage',
    'HireFerry',
    'Brawl',
    'GiveGoldTo',
    'GiveItemTo',
    'PickupItem',
    'GoToSleep',
    'UseSoulGaze',
    'CastSpell',
    'Toast',
    'Drink',
];

$socialFunctions = ['Inspect', 'InspectSurroundings', 'Relax', 'TakeASeat', 'UseSoulGaze','Toast', 'Drink', 'Training','EndRitualCeremony','StartRitualCeremony','Surrender','EndConversation','MoveTo'];
$movementFunctions = ['TravelTo', 'Follow', 'FollowPlayer', 'ComeCloser', 'WaitHere', 'IncreaseWalkSpeed', 'DecreaseWalkSpeed','MakeFollower'];
$combatFunctions = ['Attack', 'AttackHunt', 'Brawl', 'SheatheWeapon'];
$inventoryFunctions = ['OpenInventory', 'OpenInventory2', 'CheckInventory', 'GiveGoldTo', 'GiveItemTo', 'PickupItem', 'TakeGoldFromPlayer', 'RentRoom', 'HireCarriage', 'HireFerry', 'CastSpell'];
$playerOnlyFunctions = ['ReadQuestJournal', 'SetCurrentTask', 'GoToSleep'];


$currentList = array_unique(array_merge($npcFunctions, $playerFunctions));

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
$GLOBALS["db"]=new sql(); // if we delay creating new instance, functions.php inclusion in line 86 could trigger errors in plugins
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");
require_once($enginePath."lib/utils.php");
require_once($enginePath."functions/functions.php");

$currentList = $GLOBALS["DEFINED_FUNCTIONS"];
$alwaysVisibleFunctions = ['RentRoom', 'HireCarriage', 'HireFerry'];
$currentList = array_unique(array_merge($currentList, $alwaysVisibleFunctions));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedFunctions = $_POST['functions'] ?? [];
    $GLOBALS['ENABLED_FUNCTIONS'] = $selectedFunctions;
    
    file_put_contents(__DIR__."/../functions/user_pref.json",json_encode($selectedFunctions));
    $currentOnes=$selectedFunctions;
    $message = "Function preferences updated successfully! Selected " . count($selectedFunctions) . " functions.";
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
        padding-top: 80px; /* Space for navbar */
        padding-bottom: 40px;
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
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

    /* Page Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .page-header h1 {
        margin-bottom: 8px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }
    
    .page-subtitle {
        margin: 0;
        color: #bbb;
        font-size: 1.1em;
        line-height: 1.6;
    }

    /* Content Section Headers */
    .content-section h1 {
        font-family: 'MagicCards', serif;
        font-size: 1.8em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        word-spacing: 8px;
        text-align: center;
        margin-bottom: 20px;
    }

    /* Content sections */
    .content-section {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .content-section:hover {
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.05);
    }

    /* Function Grid Layout */
    .function-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .function-category {
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.02);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .function-category:hover {
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.04);
    }

    .function-category h3 {
        color: rgb(242, 124, 17);
        margin-bottom: 15px;
        font-size: 1.1em;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
        padding-bottom: 8px;
    }

    /* Function Item Styling */
    .function-item {
        display: flex;
        align-items: center;
        margin: 8px 0;
        padding: 8px 12px;
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.6), rgba(34, 34, 34, 0.7));
        border-radius: 6px;
        border: 1px solid rgba(58, 58, 58, 0.5);
        transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .function-item:hover {
        background: linear-gradient(135deg, rgba(51, 51, 51, 0.8), rgba(42, 42, 42, 0.9));
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .function-item input[type="checkbox"] {
        margin-right: 12px;
        transform: scale(1.2);
        accent-color: rgb(242, 124, 17);
        cursor: pointer;
    }

    .function-item label {
        cursor: pointer;
        color: #e0e0e0;
        font-weight: 500;
        user-select: none;
        flex-grow: 1;
    }

    .function-item label:hover {
        color: #ffffff;
    }

    /* Mode indicator */
    .mode-indicator {
        display: inline-block;
        background: linear-gradient(135deg, rgba(242, 124, 17, 0.2), rgba(242, 124, 17, 0.15));
        color: rgb(242, 124, 17);
        padding: 8px 16px;
        border-radius: 20px;
        border: 1px solid rgba(242, 124, 17, 0.3);
        font-size: 0.9em;
        font-weight: 600;
        margin-bottom: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }



    /* Action buttons container */
    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin: 25px 0;
    }

    /* Function descriptions */
    .function-description {
        font-size: 0.8em;
        color: #888;
        margin-top: 4px;
        font-style: italic;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .function-grid {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 1.8em;
        }

        .content-section h1 {
            font-size: 1.6em;
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

        .content-section h1 {
            font-size: 1.3em;
        }
        
        .function-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        
        .content-section {
            padding: 15px;
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
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>AI Action Editor</h1>
        <p class="page-subtitle">Configure which AI functions are available for AI actors in CHIM</p>
    </div>

    <div class="content-section">
        <h1>Function Selection</h1>
        
        <form method="post" id="functionForm">
            <!-- Quick Action Buttons -->
            <div class="action-buttons">
                <button type="button" onclick="selectAll()" class="btn-primary">Select All</button>
                <button type="button" onclick="selectNone()" class="btn-cancel">Clear All</button>
                <button type="submit" class="btn-save">💾 Save Function Configuration</button>
            </div>

            <!-- Function Grid -->
            <div class="function-grid">
                <!-- Movement & Navigation Functions -->
                <div class="function-category">
                    <h3>🚶 Movement & Navigation</h3>
                    <?php
                    
                    foreach ($movementFunctions as $func):
                        if (in_array($func, $currentList)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    $descriptions = [
                                        'TravelTo' => 'Move to specific fast travel locations',
                                        'Follow' => 'Follow another character or NPC',
                                        'FollowPlayer' => 'Follow the player character',
                                        'ComeCloser' => 'Move closer to the NPC partner',
                                        'WaitHere' => 'Stay in current location and wait',
                                        'IncreaseWalkSpeed' => 'Move faster during travel',
                                        'DecreaseWalkSpeed' => 'Move slower, more carefully'
                                    ];
                                    echo $descriptions[$func] ?? 'Movement-related function';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>

                <!-- Combat & Actions -->
                <div class="function-category">
                    <h3>⚔️ Combat & Actions</h3>
                    <?php

                    foreach ($combatFunctions as $func):
                        if (in_array($func, $currentList)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    $descriptions = [
                                        'Attack' => 'Engage in combat with hostile entities',
                                        'AttackHunt' => 'More conservative attack mode',
                                        'Brawl' => 'Engage in non-lethal combat or sparring',
                                        'SheatheWeapon' => 'Put away weapons to appear non-threatening'
                                    ];
                                    echo $descriptions[$func] ?? 'Combat-related function';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>

                <!-- Interaction & Social -->
                <div class="function-category">
                    <h3>👥 Interaction & Social</h3>
                    <?php
                    
                    foreach ($socialFunctions as $func):
                        if (in_array($func, $currentList)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    $descriptions = [
                                        'Inspect' => 'Examine objects, people, or items closely',
                                        'InspectSurroundings' => 'Look around and observe the environment',
                                        'Relax' => 'Take a break and sandbox',
                                        'TakeASeat' => 'Sit down at nearest chair',
                                        'UseSoulGaze' => 'Use ITT to visualize and describe the current scene',
                                        'Toast' => 'Raise a glass in celebration or honor',
                                        'Drink' => 'Drink a beverage to quench thirst',
                                        'Training' => 'Opens training menu for skill improvement (only available for trainer NPCs)',
                                        'Surrender' => 'Surrender to avoid conflict or harm',
                                    ];
                                    echo $descriptions[$func] ?? 'AI ends conversation';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>

                <!-- Inventory & Items -->
                <div class="function-category">
                    <h3>🎒 Inventory & Items</h3>
                    <?php
                    
                    foreach ($inventoryFunctions as $func):
                        if (in_array($func, $currentList)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    $descriptions = [
                                        'OpenInventory' => 'Regular menu trading',
                                        'OpenInventory2' => 'Gift Trading',
                                        'CheckInventory' => 'Check of inventory status',
                                        'GiveGoldTo' => 'Give gold to another character.',
                                        'GiveItemTo' => 'Give items to another character.',
                                        'PickupItem' => 'Pick up items from the ground using RefID from nearby_items',
                                        'TakeGoldFromPlayer' => 'Receive or take gold from player. Requires player confirmation.',
                                        'RentRoom' => 'Innkeeper-only: rent a room to the player for 10 gold and unlock the inn bed for 24 in-game hours.',
                                        'HireCarriage' => 'Carriage-driver-only: hire a carriage for player fast travel with vanilla costs (20 major cities, 50 minor towns).',
                                        'HireFerry' => 'Ferryman-only: hire a ferry for player fast travel with vanilla costs (50 standard routes, 500 Icewater Jetty, free Castle Volkihar).',
                                        'CastSpell' => 'Cast a spell on a target actor (use spell names from known spells).'
                                    ];
                                    echo $descriptions[$func] ?? 'Inventory management function';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>

                <!-- Player-Specific Functions -->
                <?php if (!isset($GLOBALS["IS_NPC"]) || !$GLOBALS["IS_NPC"]): ?>
                <div class="function-category">
                    <h3>📖 Player-Specific</h3>
                    <?php
                    foreach ($playerOnlyFunctions as $func):
                        if (in_array($func, $currentList)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    $descriptions = [
                                        'ReadQuestJournal' => 'Check current active quests',
                                        'SetCurrentTask' => 'Set or update current AI Dynamic Objective',
                                        'GoToSleep' => 'Rest and sleep to recover'
                                    ];
                                    echo $descriptions[$func] ?? 'Player-specific function';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <div class="function-category">
                    <h3>📖 Plugin functions</h3>
                    <?php
                    
                    foreach ($currentList as $func):
                        if ((strpos($func,"ExtCmd")!==false) || (strpos($func,"WebCmd")!==false)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    echo $GLOBALS["F_TRANSLATIONS"][$func] ?? 'Player-specific function';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>

                <!-- NPC-Specific Functions -->
                <?php if (isset($GLOBALS["IS_NPC"]) && $GLOBALS["IS_NPC"]): ?>
                <div class="function-category">
                    <h3>🎓 NPC-Specific</h3>
                    <?php
                    foreach ($npcOnlyFunctions as $func):
                        if (in_array($func, $currentList)):
                    ?>
                        <div class="function-item">
                            <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>" id="func_<?= $func ?>"
                                <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                            <label for="func_<?= $func ?>">
                                <?= htmlspecialchars($func) ?>
                                <div class="function-description">
                                    <?php
                                    $descriptions = [
                                        'Training' => 'Opens training menu for skill improvement (only for trainer NPCs)'
                                    ];
                                    echo $descriptions[$func] ?? 'NPC-specific function';
                                    ?>
                                </div>
                            </label>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</main>

<script>
function showToast(message, duration = 5000) {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}



function selectAll() {
    const checkboxes = document.querySelectorAll('input[name="functions[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function selectNone() {
    const checkboxes = document.querySelectorAll('input[name="functions[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}





// Show success message if functions were updated
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode($message); ?>);
});
<?php endif; ?>

// Form submission handling
document.getElementById('functionForm').addEventListener('submit', function(e) {
    const selectedCount = document.querySelectorAll('input[name="functions[]"]:checked').length;
    
    if (selectedCount === 0) {
        e.preventDefault();
        showToast('Please select at least one function before saving.', 3000);
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '💾 Saving...';
    submitBtn.disabled = true;
    
    // Re-enable after a short delay (form will submit)
    setTimeout(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }, 2000);
});
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
