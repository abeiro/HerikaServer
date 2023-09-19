<?php


// Example of server plugin.
$GLOBALS["F_NAMES"]["Heal"]="Heal";
$GLOBALS["F_TRANSLATIONS"]["Heal"]="Heals target using magic spell";

// $FUNCTION_PARM_INSPECT will contain an enum of visible NPC
// Later reference: https://github.com/NightQuest/SKSE/blob/master/Data/Scripts/Source/ModEvent.psc


$GLOBALS["FUNCTIONS"][] =
    [
        "name" => $GLOBALS["F_NAMES"]["Heal"],
        "description" => $GLOBALS["F_TRANSLATIONS"]["Heal"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                    "enum" => $GLOBALS["FUNCTION_PARM_INSPECT"]

                ]
            ],
            "required" => ["target"],
        ],
    ]
;


$GLOBALS["ENABLED_FUNCTIONS"][]="Heal";
