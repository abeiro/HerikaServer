<?php


// Example of server plugin.
$GLOBALS["F_NAMES"]["ExtCmdHeal"]="Heal";
$GLOBALS["F_TRANSLATIONS"]["ExtCmdHeal"]="Heals target using magic spell";

// $FUNCTION_PARM_INSPECT will contain an enum of visible NPC
// Later reference: https://github.com/NightQuest/SKSE/blob/master/Data/Scripts/Source/ModEvent.psc


$GLOBALS["FUNCTIONS"][] =
    [
        "name" => $GLOBALS["F_NAMES"]["ExtCmdHeal"],
        "description" => $GLOBALS["F_TRANSLATIONS"]["ExtCmdHeal"],
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


$GLOBALS["ENABLED_FUNCTIONS"][]="ExtCmdHeal";
