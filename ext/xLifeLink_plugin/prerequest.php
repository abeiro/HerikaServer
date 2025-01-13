<?php
require_once("util.php");

if (!$GLOBALS["FEATURES"]["MISC"]["LIFE_LINK_PLUGIN"]) {
    return;
}

/**
 * Shortcut for HERIKA_NAME
 * @var mixed
 */
$npcName = $GLOBALS["HERIKA_NAME"];

if ($npcName === "The Narrator") {
    return;
}

/**
 * This one is to suport realnames mod, which is used in MinAI. It doesn't require MinAI though. If no MinAI it will just skip this part.
 */
if (isset($GLOBALS["realnames_support"]) && $GLOBALS["realnames_support"]) {
    $matches = [];
    if (preg_match('/^(.+?) \[(.+)\]$/', $GLOBALS["HERIKA_NAME"], $matches)) {
        $npcName = $matches[2];
    }
}

/**
 * Same as in MinAI
 * @param mixed $npcName
 * @return string
 */
if (!function_exists('GetConfigPath')) {
    Function GetConfigPath($npcName) {
        // If use symlink, php code is actually in repo folder but included in wsl php server
        // with just dirname((__FILE__)) it was getting directory of repo not php server 
        $path = getcwd().DIRECTORY_SEPARATOR;
        $newConfFile=md5($npcName);
        return $path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php";
    }
}

$configPath = GetConfigPath($npcName);

$npcPersonalityJSON = getJSONPersonality($npcName);

if (!$configPath) {
    error_log("LifeLink: Can't find config file for $npcName");
}

// file_put_contents("my_logs.txt", "\n$npcName - PersonalityJSON: " . json_encode($npcPersonalityJSON[0]) . "\n", FILE_APPEND);

$alreadyJson = array_key_exists('IS_HERIKA_PERS_JSON', $GLOBALS) && $GLOBALS["IS_HERIKA_PERS_JSON"];

$bool = $alreadyJson ? "true" : "false";

/**
 * Each time npc is requested it checks:
 * - npc has json personality
 * - npc's config file isn't already converted to use json personality
 * - if both conditions are true:
 *   - it will build new php variables for npc config file
 *   - it will add those variables to npc's config file
 */
if (isset($npcPersonalityJSON) && !$alreadyJson) {
    // static data
    $age = $npcPersonalityJSON["age"] ?? null;
    $race = $npcPersonalityJSON["race"] ?? null;
    $beastfolk = $npcPersonalityJSON["beastfolk"] ?? null;
    $gender = $npcPersonalityJSON["gender"] ?? null;
    $origin = $npcPersonalityJSON["origin"] ?? null;
    $occupation = $npcPersonalityJSON["occupation"] ?? null;
    $backgroundSummary = $npcPersonalityJSON["backgroundSummary"] ?? null;
    $coreValuesBeliefs = $npcPersonalityJSON["coreValuesBeliefs"] ?? null;
    $communicationStyle = $npcPersonalityJSON["communicationStyle"] ?? null;
    $corePersonalityTraits = $npcPersonalityJSON["corePersonalityTraits"] ?? null;

    // file_put_contents("my_logs.txt", "\n$npcName - Age: " . json_encode($npcPersonalityJSON["age"]) . "\n", FILE_APPEND);

    // dynamic
    $desires = $npcPersonalityJSON["desires"] ?? null;
    $needsRequests = $npcPersonalityJSON["needsRequests"] ?? null;
    $relationships = $npcPersonalityJSON["relationships"] ?? null;

    $speakStyleLine = "";

    if (isset($communicationStyle)) {
        $speakStyleLine = "#SpeechStyle:\n" .
            "- tone: {$communicationStyle["tone"]}\n" .
            "- mannerisms: {$communicationStyle["mannerisms"]}\n";
    }

    $staticPersonality = addslashes(trim("Roleplay as $npcName:\n" .
        buildPersonalityLine("age", $age) .
        buildPersonalityLine("gender", $gender) .
        buildPersonalityLine("race", $race) .
        buildPersonalityLine("beastfolk", $beastfolk) .
        buildPersonalityLine("origin", $origin) .
        buildPersonalityLine("background", $backgroundSummary) .
        buildPersonalityLine("beastfolk", $beastfolk) .
        buildPersonalityLineList("core traits", $corePersonalityTraits) .
        buildPersonalityLineList("core beliefs", $coreValuesBeliefs) .
        "$speakStyleLine"));

    $relationshipsPersonality = "$npcName's relationships:\n";

    if (isset($relationships) && count($relationships) > 0) {
        foreach ($relationships as $rel) {
            $relationshipsPersonality .= "- {$rel["name"]}: {$rel["description"]}\n";
        }
    }

    $relationshipsPersonality = addslashes($relationshipsPersonality);

    $desiresPersonality = "";

    if (isset($desires) && count($desires) > 0) {
        $desiresPersonality = addslashes(buildPersonalityLineList("$npcName's desires", $desires));
    }

    $needsPersonality = "";

    if (isset($needsRequests) && count($needsRequests) > 0) {
        $needsPersonality = addslashes(buildPersonalityLineList("$npcName's needs", $needsRequests));
    }

    $fileContent = file_get_contents($configPath);
    $fullPersonality = buildPersonality([
        "needs" => $needsPersonality,
        "desires" => $desiresPersonality,
        "relationships" => $relationshipsPersonality,
    ], $staticPersonality);
    $newContent = "\n\$IS_HERIKA_PERS_JSON=true;\n" .
        "\n// static personality part\n" .
        "\$HERIKA_PERS_STATIC='" . $staticPersonality . "';\n\n" .
        "\n// combined personality\n" .
        "\$HERIKA_PERS='" . $fullPersonality . "';" .
        "\n\n?>";

    $GLOBALS["HERIKA_PERS"] = $fullPersonality;

    if ($fileContent !== false) {
        $updatedContent = str_replace('?>', $newContent, $fileContent);

        file_put_contents($configPath, $updatedContent);
    }
}

