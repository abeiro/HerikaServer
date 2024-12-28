<?php
require_once("ext/minai_plugin/prerequest.php");
require_once("util.php");

$npcName = $GLOBALS["HERIKA_NAME"];

if ($npcName === "The Narrator") {
    return;
}

if (isset($GLOBALS["realnames_support"]) && $GLOBALS["realnames_support"]) {
    $matches = [];
    if (preg_match('/^(.+?) \[(.+)\]$/', $GLOBALS["HERIKA_NAME"], $matches)) {
        $npcName = $matches[2];
    }
}


// will create new table and will try to import csv data, if csv data was already imported it will skip this step
$tableName = "test_personalities";
importPersonalitiesToDB($tableName, "personalities_json", "CREATE TABLE IF NOT EXISTS $tableName (
    npc_name character varying(256) PRIMARY KEY,
    personality JSONB
    )", ["npc_name"]);

function buildPersonalityLine($name, $value)
{
    return $value ? "$name: $value;\n" : "";
}

function buildPersonalityLineList($name, $stringsArr)
{
    if (!isset($stringsArr) || count($stringsArr) === 0) {
        return "";
    }

    return "$name: " . implode(", ", $stringsArr) . ";\n";
}

$configPath = GetConfigPath($npcName);

$npcPersonalityJSON = getJSONPersonality($npcName);

if (!$configPath) {
    error_log("LifeLink: Can't find config file for $npcName");
}

// file_put_contents("my_logs.txt", "\n$npcName - PersonalityJSON: " . json_encode($npcPersonalityJSON[0]) . "\n", FILE_APPEND);

$alreadyJson = array_key_exists('IS_HERIKA_PERS_JSON', $GLOBALS) && $GLOBALS["IS_HERIKA_PERS_JSON"];

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

    $desiresPersonality = "";

    if (isset($desires) && count($desires) > 0) {
        $desiresPersonality = buildPersonalityLineList("$npcName's desires", $desires);
    }

    $needsPersonality = "";

    if (isset($needsRequests) && count($needsRequests) > 0) {
        $needsPersonality = buildPersonalityLineList("$npcName's needs", $needsRequests);
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

