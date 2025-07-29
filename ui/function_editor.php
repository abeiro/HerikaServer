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

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

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
    'FollowPlayer',
    'Brawl',
    'GiveGoldTo',
    'GiveItemTo',
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
    'Brawl',
    'GiveGoldTo',
    'GiveItemTo',
    'GoToSleep',
    'UseSoulGaze',
];

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

$currentList = $GLOBALS["ENABLED_FUNCTIONS"];

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
        padding-top: 160px; /* Space for navbar */
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
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    /* Function Grid Layout */
    .function-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .function-category {
        background: #1a1a1a;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        padding: 20px;
    }

    .function-category h3 {
        color: rgb(242, 124, 17);
        margin-bottom: 15px;
        font-size: 1.1em;
        border-bottom: 1px solid #4a4a4a;
        padding-bottom: 8px;
    }

    /* Function Item Styling */
    .function-item {
        display: flex;
        align-items: center;
        margin: 8px 0;
        padding: 8px 12px;
        background: #2a2a2a;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    .function-item:hover {
        background: #333;
    }

    .function-item input[type="checkbox"] {
        margin-right: 12px;
        transform: scale(1.2);
        accent-color: rgb(242, 124, 17);
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
        background: rgba(242, 124, 17, 0.2);
        color: rgb(242, 124, 17);
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.9em;
        font-weight: 600;
        margin-bottom: 20px;
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

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>AI Action Editor (Beta)</h1>
        <p>Configure which AI functions are available for AI actors in CHIM.</p>
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
                    $movementFunctions = ['TravelTo', 'Follow', 'FollowPlayer', 'ComeCloser', 'WaitHere', 'IncreaseWalkSpeed', 'DecreaseWalkSpeed'];
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
                    $combatFunctions = ['Attack', 'AttackHunt', 'Brawl', 'SheatheWeapon'];
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
                    $socialFunctions = ['Inspect', 'InspectSurroundings', 'Relax', 'TakeASeat', 'UseSoulGaze'];
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
                                        'UseSoulGaze' => 'Use ITT to visualize and describe the current scene'
                                    ];
                                    echo $descriptions[$func] ?? 'Social interaction function';
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
                    $inventoryFunctions = ['OpenInventory', 'OpenInventory2', 'CheckInventory', 'GiveGoldTo', 'GiveItemTo', 'TakeGoldFromPlayer'];
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
                                        'GiveGoldTo' => 'Give money to another character (no real money is transferred wip)',
                                        'GiveItemTo' => 'Give items to another character (no real items are transferred wip)',
                                        'TakeGoldFromPlayer' => 'Receive or take money from player (no real money is transferred wip)'
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
                <?php if (!$GLOBALS["IS_NPC"]): ?>
                <div class="function-category">
                    <h3>📖 Player-Specific</h3>
                    <?php
                    $playerOnlyFunctions = ['ReadQuestJournal', 'SetCurrentTask', 'GoToSleep'];
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
