<?php

/**
 * Depends on
 * @var array $GLOBALS
 */

?>
<form action="index.php" method="post">
    <div class="row g-1 my-2">
        <div class="col-3">
            <input type="text" class="form-control form-control-sm" name="prompt" value='(Chat as <?php echo $GLOBALS["HERIKA_NAME"]; ?>)' />
        </div>
        <div class="col-5">
            <input type="text" class="form-control form-control-sm" name="preprompt" value="What...?" />
        </div>
        <div class="col-2">
            <select name="queue" class="form-select form-select-sm">
                <option value="AASPGDialogueHerika1WhatTopic">What do you think about?</option>
                <option value="AASPGDialogueHerika2Branch1Topic">What we should do?</option>
                <option value="AASPGDialogueHerika3Branch1Topic">What do you know about this place?</option>
                <option value="AASPGQuestDialogue2Topic1B1Topic">Tell me something (priority)</option>
                <option value="Simchat" selected="true">Simulate input text</option>
            </select>
        </div>
        <div class="col-1">
            <input class="btn btn-primary btn-sm" type="submit" value="Request Chat" />
        </div>
    </div>
</form>

<form action="index.php" method="post">
    <div class="row g-1 my-2">
        <div class="col-3">
            <select name="command" class="form-select form-select-sm">
                <option value="MoveTo">MoveTo</option>
                <option value="SneakTo">SneakTo</option>
                <option value="Attack">Attack</option>
                <option value="Follow">Follow</option>
                <option value="StopCurrent">StopCurrent</option>
                <option value="Inspect">Inspect</option>
                <option value="Relax">Relax</option>
                <option value="StopAll">StopAll</option>
                <option value="OpenInventory">OpenInventory</option>
                <option value="SheatheWeapon">SheatheWeapon</option>
            </select>
        </div>
        <div class="col-6">
            <input type="text" class="form-control form-control-sm" value="" name="parameter" placeholder="parameter" />
        </div>
        <div class="col-1">
            <input class="btn btn-primary btn-sm" type="submit" value="Post command" />
        </div>
    </div>
</form>

<form action="index.php" method="post">
    <div class="row g-1 my-2">
        <div class="col-3">
            <input type="text" class="form-control form-control-sm" name="animation" value="" />
        </div>
        <div class="col-1">
            <input class="btn btn-primary btn-sm" type="submit" value="Post animation" />
        </div>
    </div>
</form>
