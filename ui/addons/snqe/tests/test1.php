<?php

$quest_id = "bandit_ambush";

// Create NPCs first
CreateNPC($quest_id, "ixora", "Ixora the Shadowseer", "Female", "mage", "Breton", "nearby", "Shadowy figure with glowing eyes", "A seer who sees into the shadows.", "mysterious and cryptic", "serious");
CreateNPC($quest_id, "gorm", "Gorm the Unyielding", "Male", "warrior", "Nord", "Yorgrim Overlook", "", "A ruthless bandit leader, feared by travelers and locals alike.", "Sneers and snarls, commanding his men with authority.", "aggressive");
CreateNPC($quest_id, "bandit1", "Bandit 1", "Male", "warrior", "Nord", "Yorgrim Overlook", "", "A hardened bandit, loyal to Gorm.", "Growls and grunts, following orders without question.", "aggressive");
CreateNPC($quest_id, "bandit2", "Bandit 2", "Male", "warrior", "Nord", "Yorgrim Overlook", "", "Another bandit, equally ruthless.", "Sneers, ready to strike.", "aggressive");

// Create item
CreateItem($quest_id, "ancient_artifact", "Ancient Artifact", "amulet", "Yorgrim Overlook", "A relic of ancient power, rumored to grant strength to its wearer.", "gorm");

// Spawn bandits at Yorgrim Overlook
SpawnNPC($quest_id, "gorm", "Yorgrim Overlook");
if (CheckNPCSpawn($quest_id, "gorm") != "done") {
    error_log("Gorm not spawned");
    return;
}
SpawnNPC($quest_id, "bandit1", "Yorgrim Overlook");
if (CheckNPCSpawn($quest_id, "bandit1") != "done") {
    error_log("Bandit1 not spawned");
    return;
}
SpawnNPC($quest_id, "bandit2", "Yorgrim Overlook");
if (CheckNPCSpawn($quest_id, "bandit2") != "done") {
    error_log("Bandit2 not spawned");
    return;
}

// Spawn item in Gorm's inventory
SpawnItem($quest_id, "ancient_artifact", "gorm");
if (CheckItemSpawn($quest_id, "ancient_artifact") != "done") {
    error_log("Item not spawned");
    return;
}

// Ixora travels to Yorgrim Overlook
if (WaitAtLocation($quest_id, "Yorgrim Overlook", 1000, "ixora") != "done") {
    error_log("Ixora not at location");
    return;
}

// Bandits combat player
CombatPlayer($quest_id, "gorm");
CombatPlayer($quest_id, "bandit1");
CombatPlayer($quest_id, "bandit2");

// Ixora combats Gorm
CombatNPC($quest_id, "ixora", "gorm");

// Wait for player combats to end
if (WaitforCombatEnd($quest_id, "gorm") != "done") {
    error_log("Gorm combat not ended");
    return;
}
if (WaitforCombatEnd($quest_id, "bandit1") != "done") {
    error_log("Bandit1 combat not ended");
    return;
}
if (WaitforCombatEnd($quest_id, "bandit2") != "done") {
    error_log("Bandit2 combat not ended");
    return;
}

// Wait for Ixora vs Gorm combat to end
if (WaitForNPCCombatEnd($quest_id, "ixora", "gorm") != "done") {
    error_log("Ixora vs Gorm combat not ended");
    return;
}

// Complete quest
CompleteQuest($quest_id);

?>