<?php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedFunctions = $_POST['functions'] ?? [];
    $GLOBALS['ENABLED_FUNCTIONS'] = $selectedFunctions;
    
    file_put_contents(__DIR__."/../functions/user_pref.json",json_encode($selectedFunctions));
    $currentOnes=$selectedFunctions;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit ENABLED_FUNCTIONS</title>
</head>
<body>
    <h1>Edit ENABLED_FUNCTIONS (<?= $GLOBALS["IS_NPC"] ? "NPC" : "Player" ?> Mode)</h1>
    <form method="post">
        <?php foreach ($currentList as $func): ?>
            <label>
                <input type="checkbox" name="functions[]" value="<?= htmlspecialchars($func) ?>"
                    <?= in_array($func, $currentOnes ?? []) ? 'checked' : '' ?>>
                <?= htmlspecialchars($func) ?>
            </label><br>
        <?php endforeach; ?>
        <br>
        <input type="submit" value="Update Functions">
    </form>
</body>
</html>
