<?php

// Funktionen, die OpenAI bereitgestellt werden


$ENABLED_FUNCTIONS_LOCAL=[
    'Inspect',
    'LookAt',
    'InspectSurroundings',
    'MoveTo',
    'OpenInventory',
    'OpenInventory2',
    'Attack',
    'AttackHunt',
    'Follow',
    'CheckInventory',
    'SheatheWeapon',
    'Relax',
    'LeadTheWayTo',
    'TakeASeat',
    'ReadQuestJournal',
    'IncreaseWalkSpeed',
    'DecreaseWalkSpeed',
    'GetDateTime',
    'SearchDiary',
    'SetCurrentTask',
    'StopWalk',
    'TravelTo',
    'SearchMemory',
    'GiveItemToPlayer',
    'FollowPlayer',
    'ComeCloser',
    'Brawl',
    'ReturnBackHome',
    'GiveGoldTo',
    'GiveItemTo',
    'GoToSleep',
    'UseSoulGaze'
//    'WaitHere'
];

$GLOBALS["ENABLED_FUNCTIONS"]=$ENABLED_FUNCTIONS_LOCAL;

// Hier müssen interne Schlüssel verwendet werden.

$F_TRANSLATIONS_LOCAL["Inspect"]="Untersucht NUR eine Person/einen NPC. Warte auf das Ergebnis, um eine Dialognachricht auszugeben.";
$F_TRANSLATIONS_LOCAL["LookAt"]="Schaut sich NUR eine Person/einen NPC genauer an. Warte auf das Ergebnis, um eine Dialognachricht auszugeben.";
$F_TRANSLATIONS_LOCAL["InspectSurroundings"]="Sucht nach Personen/NPCs in der Umgebung. Warte auf das Ergebnis, um eine Dialognachricht auszugeben.";
$F_TRANSLATIONS_LOCAL["MoveTo"]="Geht zu einem sichtbaren Gebäude/einer sichtbaren Person. Kann auch genutzt werden, um {$GLOBALS["PLAYER_NAME"]} dorthin zu führen.";
$F_TRANSLATIONS_LOCAL["OpenInventory"]="Startet einen Handel oder Austausch von Gegenständen mit {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["OpenInventory2"]="Startet einen Handel. {$GLOBALS["PLAYER_NAME"]} muss dabei Gegenstände an {$GLOBALS["HERIKA_NAME"]} übergeben.";
$F_TRANSLATIONS_LOCAL["Attack"]="Greift eine Person/einen NPC/ein Wesen an, um zu töten.";
$F_TRANSLATIONS_LOCAL["AttackHunt"]="Jagt eine Person/einen NPC/ein Wesen, um zu töten";
$F_TRANSLATIONS_LOCAL["Follow"]="Geht zu einer bestimmten Zielperson und folgt ihr.";
$F_TRANSLATIONS_LOCAL["CheckInventory"]="Durchsucht das Inventar, den Rucksack oder die Taschen von {$GLOBALS["HERIKA_NAME"]}. Listet alle Inhalte auf.";
$F_TRANSLATIONS_LOCAL["SheatheWeapon"]="Steckt die aktuelle Waffe weg.";
$F_TRANSLATIONS_LOCAL["Relax"]="Beendet alle Aktivitäten und entspannt sich am aktuellen Ort. Wird genutzt zum Abschalten, Runterkommen oder einfach mal Pause machen.";
$F_TRANSLATIONS_LOCAL["TravelTo"]="Wird verwendet, um zu bekannten Orten, Sehenswürdigkeiten oder wichtigen Punkten zu reisen.";
$F_TRANSLATIONS_LOCAL["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} setzt sich auf einen nahegelegenen Platz.";
$F_TRANSLATIONS_LOCAL["ReadQuestJournal"]="Nur verwenden, wenn {$GLOBALS["PLAYER_NAME"]} ausdrücklich nach einer Quest fragt. Holt Informationen zu aktuellen Quests.";
$F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"]="Erhöht das Lauftempo von {$GLOBALS["HERIKA_NAME"]}.";
$F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"]="Verringert das Lauftempo von {$GLOBALS["HERIKA_NAME"]}.";
$F_TRANSLATIONS_LOCAL["GetDateTime"]="Gibt aktuelles Datum und Uhrzeit zurück.";
$F_TRANSLATIONS_LOCAL["SearchDiary"]="Liest im Tagebuch von {$GLOBALS["HERIKA_NAME"]}, um sich an etwas zu erinnern. Sucht dabei im Tagebuch-Index";
$F_TRANSLATIONS_LOCAL["SetCurrentTask"]="Legt die aktuelle Aufgabe, Handlung oder Quest fest.";
$F_TRANSLATIONS_LOCAL["ReadDiaryPage"]="Liest im Tagebuch von {$GLOBALS["HERIKA_NAME"]} zu einem bestimmten Thema nach.";
$F_TRANSLATIONS_LOCAL["StopWalk"]="Stoppt sofort alle Handlungen von {$GLOBALS["HERIKA_NAME"]}.";
$F_TRANSLATIONS_LOCAL["TravelTo"]="Nur verwenden, wenn {$GLOBALS["PLAYER_NAME"]} es ausdrücklich anordnet. Führt {$GLOBALS["PLAYER_NAME"]} in eine Stadt.";
$F_TRANSLATIONS_LOCAL["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} versucht sich zu erinnern. Antwort erfolgt in Hashtags.";
$F_TRANSLATIONS_LOCAL["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} bleibt an der aktuellen Stelle stehen und wartet.";
$F_TRANSLATIONS_LOCAL["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} gibt {$GLOBALS["PLAYER_NAME"]} einen Gegenstand (Ziel = Gegenstand).";
$F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"]="{$GLOBALS["HERIKA_NAME"]} nimmt eine bestimmte Menge Gold von {$GLOBALS["PLAYER_NAME"]}, sobald {$GLOBALS["PLAYER_NAME"]} zustimmt.";
$F_TRANSLATIONS_LOCAL["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} folgt {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["ComeCloser"]="{$GLOBALS["HERIKA_NAME"]} nähert sich {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["Brawl"]="{$GLOBALS["HERIKA_NAME"]} startet einen nicht-tödlichen Kampf mit einer anderen Person und nutzt dabei Waffen.";
$F_TRANSLATIONS_LOCAL["ReturnBackHome"]="{$GLOBALS["HERIKA_NAME"]} reist zurück nach Hause bzw. an ihren Ursprungsort.";
$F_TRANSLATIONS_LOCAL["GiveGoldTo"]="{$GLOBALS["HERIKA_NAME"]} gibt einer einzelnen Person/einem NPC etwas Gold. Die Menge ergibt sich aus dem Dialog und muss nicht angegeben werden.";
$F_TRANSLATIONS_LOCAL["GiveItemTo"]="{$GLOBALS["HERIKA_NAME"]} gibt einer einzelnen Person/einem NPC einen Gegenstand. Was genau gegeben wird, ergibt sich aus dem Dialog.";
$F_TRANSLATIONS_LOCAL["GoToSleep"]="{$GLOBALS["HERIKA_NAME"]} legt sich schlafen.";
$F_TRANSLATIONS_LOCAL["UseSoulGaze"]="Verwendet den Zauber Seelenschau. Dadurch kann {$GLOBALS["HERIKA_NAME"]} die Umgebung durch die Augen von {$GLOBALS["PLAYER_NAME"]} sehr genau wahrnehmen. Der Zauber stört allerdings den Anwender etwas.";

$GLOBALS["F_TRANSLATIONS"]=$F_TRANSLATIONS_LOCAL;



$F_RETURNMESSAGES_LOCAL["Inspect"]="{$GLOBALS["HERIKA_NAME"]} untersucht #TARGET# und sieht Folgendes: #RESULT#";
$F_RETURNMESSAGES_LOCAL["LookAt"]="Schaut sich die Kleidung und Ausrüstung einer Person/eines NPCs genauer an.";
$F_RETURNMESSAGES_LOCAL["InspectSurroundings"]="{$GLOBALS["HERIKA_NAME"]} schaut sich um und sieht Folgendes: #RESULT#";
$F_RETURNMESSAGES_LOCAL["MoveTo"]="Geht zu einem sichtbaren Gebäude/einer sichtbaren Person. Wird auch genutzt, um {$GLOBALS["PLAYER_NAME"]} dorthin zu führen.";
$F_RETURNMESSAGES_LOCAL["OpenInventory"]="Startet einen Handel oder Austausch von Gegenständen mit {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["OpenInventory2"]="{$GLOBALS["PLAYER_NAME"]} gibt Gegenstände an {$GLOBALS["HERIKA_NAME"]}. Nimmt die Gegenstände an.";
$F_RETURNMESSAGES_LOCAL["Attack"]="{$GLOBALS["HERIKA_NAME"]} greift #TARGET# an.";
$F_RETURNMESSAGES_LOCAL["AttackHunt"]="{$GLOBALS["HERIKA_NAME"]} jagt und greift #TARGET# an.";
$F_RETURNMESSAGES_LOCAL["Follow"]="{$GLOBALS["HERIKA_NAME"]} folgt #TARGET#.";
$F_RETURNMESSAGES_LOCAL["CheckInventory"]="Inventar von {$GLOBALS["HERIKA_NAME"]}: #RESULT#";
$F_RETURNMESSAGES_LOCAL["SheatheWeapon"]="Steckt die aktuelle Waffe weg.";
$F_RETURNMESSAGES_LOCAL["Relax"]="{$GLOBALS["HERIKA_NAME"]} entspannt sich und genießt den Moment.";
$F_RETURNMESSAGES_LOCAL["LeadTheWayTo"]="Nur verwenden, wenn {$GLOBALS["PLAYER_NAME"]} dies ausdrücklich befiehlt. Führt {$GLOBALS["PLAYER_NAME"]} zu einer Stadt.";
$F_RETURNMESSAGES_LOCAL["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} setzt sich auf einen nahen Stuhl oder ein Möbelstück.";
$F_RETURNMESSAGES_LOCAL["ReadQuestJournal"]="";
$F_RETURNMESSAGES_LOCAL["IncreaseWalkSpeed"]="Erhöht das Lauftempo von {$GLOBALS["HERIKA_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["DecreaseWalkSpeed"]="Verringert das Lauftempo von {$GLOBALS["HERIKA_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["GetDateTime"]="Gibt aktuelles Datum und Uhrzeit zurück.";
$F_RETURNMESSAGES_LOCAL["SearchDiary"]="Liest im Tagebuch von {$GLOBALS["HERIKA_NAME"]}, um Erinnerungen zu wecken. Sucht dazu im Tagebuch-Index.";
$F_RETURNMESSAGES_LOCAL["SetCurrentTask"]="Legt die aktuelle Aufgabe, Handlung oder Quest fest.";
$F_RETURNMESSAGES_LOCAL["ReadDiaryPage"]="Liest im Tagebuch von {$GLOBALS["HERIKA_NAME"]} über ein bestimmtes Thema nach.";
$F_RETURNMESSAGES_LOCAL["StopWalk"]="Stoppt sofort alle Handlungen von {$GLOBALS["HERIKA_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} reist nach #TARGET#.";
$F_RETURNMESSAGES_LOCAL["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} versucht sich zu erinnern. Antworte nur kurz wie 'Lasst mich nachdenken' und warte dann.";
$F_RETURNMESSAGES_LOCAL["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} bleibt an dieser Stelle stehen und wartet.";
$F_RETURNMESSAGES_LOCAL["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} hat #TARGET# an {$GLOBALS["PLAYER_NAME"]} gegeben. Wenn das ein Handel war, könnte TakeGoldFromPlayer nötig sein.";
$F_RETURNMESSAGES_LOCAL["TakeGoldFromPlayer"]="{$GLOBALS["PLAYER_NAME"]} hat #TARGET# Goldstücke an {$GLOBALS["HERIKA_NAME"]} gegeben. Wenn das ein Handel war, könnte GiveItemToPlayer nötig sein.";
$F_RETURNMESSAGES_LOCAL["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} folgt {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["Brawl"]="{$GLOBALS["HERIKA_NAME"]} greift #TARGET# in einem nicht-tödlichen Kampf an.";
$F_RETURNMESSAGES_LOCAL["ReturnBackHome"]="{$GLOBALS["HERIKA_NAME"]} kehrt nach Hause zurück.";
$F_RETURNMESSAGES_LOCAL["GiveGoldTo"]="{$GLOBALS["HERIKA_NAME"]} gibt Gold an #TARGET#.";
$F_RETURNMESSAGES_LOCAL["GiveItemTo"]="{$GLOBALS["HERIKA_NAME"]} gibt Gegenstände an #TARGET#.";
$F_RETURNMESSAGES_LOCAL["GoToSleep"]="{$GLOBALS["HERIKA_NAME"]} legt sich schlafen.";
$F_RETURNMESSAGES_LOCAL["UseSoulGaze"]="{$GLOBALS["HERIKA_NAME"]} nutzt den Zauber Seelenschau.";

$GLOBALS["F_RETURNMESSAGES"]=$F_RETURNMESSAGES_LOCAL;



// Was das ist? Wir können hier Funktionen übersetzen oder ihnen einen eigenen Namen geben.
// Dieses Array enthält die Übersetzungen. Das Plugin benötigt immer den originalen Codenamen.

$F_NAMES_LOCAL["Inspect"]="Untersuchen";
$F_NAMES_LOCAL["LookAt"]="Anschauen";
$F_NAMES_LOCAL["InspectSurroundings"]="UmgebungUntersuchen";
$F_NAMES_LOCAL["MoveTo"]= "Hingehen";
$F_NAMES_LOCAL["OpenInventory"]="GegenständeTauschen";
$F_NAMES_LOCAL["OpenInventory2"]="GeschenkAnnehmen";
$F_NAMES_LOCAL["Attack"]="Angreifen";
$F_NAMES_LOCAL["AttackHunt"]="Jagen";
$F_NAMES_LOCAL["Follow"]="Folgen";
$F_NAMES_LOCAL["CheckInventory"]="InventarAuflisten";
$F_NAMES_LOCAL["SheatheWeapon"]="WaffeWegstecken";
$F_NAMES_LOCAL["Relax"]="Entspannen";
//$F_NAMES_LOCAL["LeadTheWayTo"]="Hinführen";
$F_NAMES_LOCAL["TakeASeat"]="Hinsetzen";
$F_NAMES_LOCAL["ReadQuestJournal"]="QuestTagebuchLesen";
$F_NAMES_LOCAL["IncreaseWalkSpeed"]="SchnellerGehen";
$F_NAMES_LOCAL["DecreaseWalkSpeed"]="LangsamerGehen";
$F_NAMES_LOCAL["GetDateTime"]="DatumUhrzeitAbrufen";
$F_NAMES_LOCAL["SearchDiary"]="TagebuchDurchsuchen";
$F_NAMES_LOCAL["SetCurrentTask"]="AktuelleAufgabeFestlegen";
$F_NAMES_LOCAL["ReadDiaryPage"]="TagebuchseiteLesen";
$F_NAMES_LOCAL["StopWalk"]="StehenBleiben";
$F_NAMES_LOCAL["TravelTo"]="ReisenNach";
$F_NAMES_LOCAL["SearchMemory"]="Erinnern";
$F_NAMES_LOCAL["WaitHere"]="HierWarten";
$F_NAMES_LOCAL["GiveItemToPlayer"]="GegenstandAnSpielerGeben";
$F_NAMES_LOCAL["TakeGoldFromPlayer"]="GoldVonSpielerNehmen";
$F_NAMES_LOCAL["FollowPlayer"]="SpielerFolgen";
$F_NAMES_LOCAL["ComeCloser"]="NäherKommen";
$F_NAMES_LOCAL["Brawl"]="Kämpfen";
$F_NAMES_LOCAL["ReturnBackHome"]="NachHauseGehen";
$F_NAMES_LOCAL["GiveGoldTo"]="GoldGeben";
$F_NAMES_LOCAL["GiveItemTo"]="GegenstandGeben";
$F_NAMES_LOCAL["GoToSleep"]="SchlafenGehen";
$F_NAMES_LOCAL["UseSoulGaze"]="SeelenschauNutzen";


$GLOBALS["F_NAMES"]=$F_NAMES_LOCAL;

if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."functions.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."functions.php");
    
    
    
$GLOBALS["FUNCTIONS"] = [
    [
        "name" => $F_NAMES_LOCAL["Inspect"],
        "description" => $F_TRANSLATIONS_LOCAL["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                    "enum" => $GLOBALS['FUNCTION_PARM_INSPECT']

                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["InspectSurroundings"],
        "description" => $F_TRANSLATIONS_LOCAL["InspectSurroundings"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => []
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["LookAt"],
        "description" => $F_TRANSLATIONS_LOCAL["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                    "enum" => $GLOBALS['FUNCTION_PARM_INSPECT']

                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["MoveTo"],
        "description" => $F_TRANSLATIONS_LOCAL["MoveTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Sichtbares Ziel: NPC, Wesen oder Gebäude",
                    "enum" => $GLOBALS['FUNCTION_PARM_MOVETO']
                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["OpenInventory"],
        "description" => $F_TRANSLATIONS_LOCAL["OpenInventory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => []
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["OpenInventory2"],
        "description" => $F_TRANSLATIONS_LOCAL["OpenInventory2"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => []
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Attack"],
        "description" => $F_TRANSLATIONS_LOCAL["Attack"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["AttackHunt"],
        "description" => $F_TRANSLATIONS_LOCAL["AttackHunt"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: Tier",
                ]
            ],
            "required" => ["target"],
        ]
        ],
    [
        "name" => $F_NAMES_LOCAL["Follow"],
        "description" => $F_TRANSLATIONS_LOCAL["Follow"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["CheckInventory"],
        "description" => $F_TRANSLATIONS_LOCAL["CheckInventory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Gegenstand, nach dem gesucht wird. Leer lassen, um alle Gegenstände anzuzeigen",
                ]
            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["SheatheWeapon"],
        "description" => $F_TRANSLATIONS_LOCAL["SheatheWeapon"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["Relax"],
        "description" => $F_TRANSLATIONS_LOCAL["Relax"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => []
        ]
    ],
    /*[
        "name" => $F_NAMES_LOCAL["LeadTheWayTo"],
        "description" => $F_TRANSLATIONS_LOCAL["LeadTheWayTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "location" => [
                    "type" => "string",
                    "description" => "Stadt oder Ort, zu dem gereist werden soll. Nur auf ausdrücklichen Befehl von {$GLOBALS["PLAYER_NAME"]}"
                    
                ]
            ],
            "required" => ["location"]
        ]
    ],*/
    [
        "name" => $F_NAMES_LOCAL["TravelTo"],
        "description" => $F_TRANSLATIONS_LOCAL["TravelTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "location" => [
                    "type" => "string",
                    "description" => "Stadt oder Ort, zu dem gereist werden soll. Nur auf ausdrücklichen Befehl von {$GLOBALS["PLAYER_NAME"]}"
                    
                ]
            ],
            "required" => ["location"]
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["TakeASeat"],
        "description" => $F_TRANSLATIONS_LOCAL["TakeASeat"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => [""]
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["ReadQuestJournal"],
        "description" => $F_TRANSLATIONS_LOCAL["ReadQuestJournal"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "id_quest" => [
                    "type" => "string",
                    "description" => "Bestimmte Aufgabe/Quest, zu der Informationen abgerufen werden. Leer lassen, um alle anzuzeigen",
                ]
            ],
            "required" => [""]
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["IncreaseWalkSpeed"],
        "description" => $F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Geschwindigkeit",
                    "enum" => ["run",  "jog"]
                ]

            ],
            "required" => []
        ]
    ],
     [
        "name" => $F_NAMES_LOCAL["DecreaseWalkSpeed"],
        "description" => $F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Geschwindigkeit",
                    "enum" => [ "jog", "walk"]
                ]

            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["GetDateTime"],
        "description" => $F_TRANSLATIONS_LOCAL["GetDateTime"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "datestring" => [
                    "type" => "string",
                    "description" => "Formatiertes Datum und Uhrzeit",
                ]

            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["SearchDiary"],
        "description" => $F_TRANSLATIONS_LOCAL["SearchDiary"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "keyword" => [
                    "type" => "string",
                    "description" => "Suchbegriff für Volltextsuche",
                ]
            ],
            "required" => [""]
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["SetCurrentTask"],
        "description" => $F_TRANSLATIONS_LOCAL["SetCurrentTask"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "description" => [
                    "type" => "string",
                    "description" => "Kurze Beschreibung der aktuellen Aufgabe, über die die Gruppe spricht",
                ]
            ],
            "required" => ["description"]
        ]
    ], 
    [
        "name" => $F_NAMES_LOCAL["StopWalk"],
        "description" => $F_TRANSLATIONS_LOCAL["StopWalk"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Aktion",
                ]
            ],
            "required" =>[""]
        ]
    ],
     [
        "name" => $F_NAMES_LOCAL["SearchMemory"],
        "description" => $F_TRANSLATIONS_LOCAL["SearchMemory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ]
            ],
            "required" =>[""]
        ]
    ],
    [
            "name" => $F_NAMES_LOCAL["WaitHere"],
            "description" => $F_TRANSLATIONS_LOCAL["WaitHere"],
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "",
                    ]
                ],
                "required" =>[""]
            ]
    ],
    [
            "name" => $F_NAMES_LOCAL["GiveItemToPlayer"],
            "description" => $F_TRANSLATIONS_LOCAL["GiveItemToPlayer"],
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "",
                    ]
                ],
                "required" =>["target"]
            ]
    ],
    [
        "name" => $F_NAMES_LOCAL["TakeGoldFromPlayer"],
        "description" => $F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ]
            ],
            "required" =>["target"]
        ]
    ],
    [
            "name" => $F_NAMES_LOCAL["FollowPlayer"],
            "description" => $F_TRANSLATIONS_LOCAL["FollowPlayer"],
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "",
                    ]
                ],
                "required" =>[""]
            ]
            ],
    [
            "name" => $F_NAMES_LOCAL["ComeCloser"],
            "description" => $F_TRANSLATIONS_LOCAL["ComeCloser"],
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => [""]
    ],
    [
        "name" => $F_NAMES_LOCAL["Brawl"],
        "description" => $F_TRANSLATIONS_LOCAL["Brawl"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["ReturnBackHome"],
        "description" => $F_TRANSLATIONS_LOCAL["ReturnBackHome"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => [""],
        ]
    ],
    [
    "name" => $F_NAMES_LOCAL["GiveGoldTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveGoldTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["GiveItemTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveItemTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Ziel: NPC oder Wesen",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["GoToSleep"],
        "description" => $F_TRANSLATIONS_LOCAL["GoToSleep"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => [""],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["UseSoulGaze"],
        "description" => $F_TRANSLATIONS_LOCAL["UseSoulGaze"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Freilassen",
                ]
            ],
            "required" => []
        ],
    ]
];

// Behalte eine Kopie aller hier definierten Funktionen
foreach ($GLOBALS["FUNCTIONS"] as $n=>$functionEntry)
    $GLOBALS["BASE_FUNCTIONS"][getFunctionCodeName($functionEntry["name"])]=$GLOBALS["FUNCTIONS"][$n];

// Diese Funktion wird nur angeboten, wenn SearchDiary aktiv ist
$FUNCTIONS_GHOSTED_LOCAL =  [
        "name" => $F_NAMES_LOCAL["ReadDiaryPage"],
        "description" => $F_TRANSLATIONS_LOCAL["ReadDiaryPage"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "page" => [
                    "type" => "string",
                    "description" => "Thema zum Suchen in Volltext-Suchsyntax",
                ]
            ],
            "required" => ["topic"]
        ]
    ]
    ;

$GLOBALS["FUNCTIONS_GHOSTED"]=$FUNCTIONS_GHOSTED_LOCAL;

function getFunctionCodeName($key) {
    
    $functionCode=array_search($key, $GLOBALS["F_NAMES"]);
    return $functionCode;
    
}

function getFunctionTrlName($key) {
    return $GLOBALS["F_NAMES"][$key];
    
}

function findFunctionByName($name) {
    foreach ($GLOBALS["FUNCTIONS"] as $function) {
        if ($function['name'] === $name) {
            return $function;
        }
    }
    return null; // Gibt null zurück, wenn Funktion nicht gefunden wird
}

function getFunctionByTrlName($searchValue) {
    $keys = [];

    foreach ($GLOBALS["F_NAMES"] as $key => $value) {
        if ($value === $searchValue) {
            return $key;
        }
    }
    
}

function requireFunctionFilesRecursively($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            requireFunctionFilesRecursively($path);
        } elseif (is_file($path) && $file === 'functions.php') {
            require_once $path;
        } 
    }
}

function unsetFunction($functionCodename) {
    if (($key = array_search($functionCodename, $GLOBALS["ENABLED_FUNCTIONS"])) !== false) {
        unset($GLOBALS["ENABLED_FUNCTIONS"][$key]);
        
    }

    foreach ($GLOBALS["FUNCTIONS"] as $n=>$v)
        if (!in_array(getFunctionCodeName($v["name"]),$GLOBALS["ENABLED_FUNCTIONS"])) {
            // error_log("Removing {$GLOBALS["FUNCTIONS"][$n]["name"]}");
            unset($GLOBALS["FUNCTIONS"][$n]);
        }
}

if (isset($GLOBALS["IS_NPC"])&&$GLOBALS["IS_NPC"]) { 
    $GLOBALS["ENABLED_FUNCTIONS"]=[
        'Inspect',
        //'LookAt',
        'InspectSurroundings',
        //'MoveTo',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'AttackHunt',
        'TravelTo',
        'Follow',
        'CheckInventory',
        //'SheatheWeapon',
        'Relax',
        //'LeadTheWayTo',
        'TakeASeat',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        //'GetDateTime',
        //'SearchDiary',
        //'SetCurrentTask',
        //'SearchMemory',
        //'StopWalk'
        'WaitHere',
        'ComeCloser',
        //'GiveItemToPlayer',
        'TakeGoldFromPlayer',
        'FollowPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
    ];
} else {
    $GLOBALS["ENABLED_FUNCTIONS"]=[
        'Inspect',
        //'LookAt',
        'InspectSurroundings',
        //'MoveTo',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'AttackHunt',
        'TravelTo',
        //'Follow',
        'CheckInventory',
        'SheatheWeapon',
        'Relax',
        //'LeadTheWayTo',
        'TakeASeat',
        'ReadQuestJournal',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        'SetCurrentTask',
        'ComeCloser',
        //'GiveItemToPlayer',
        'TakeGoldFromPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'GoToSleep',
        'UseSoulGaze'
        //'GetDateTime',
        //'SearchDiary',
        //'SearchMemory',
        //'StopWalk'
    ];
}


$folderPath = __DIR__.DIRECTORY_SEPARATOR."../ext/";
requireFunctionFilesRecursively($folderPath);

// Warum ist das hier?
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");
}

if (file_exists(__DIR__.DIRECTORY_SEPARATOR."../prompts/prompts_custom.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."../prompts/prompts_custom.php");
}

// Entfernt nicht aktivierte Funktionen   

foreach ($GLOBALS["FUNCTIONS"] as $n=>$v)
    if (!in_array(getFunctionCodeName($v["name"]),$GLOBALS["ENABLED_FUNCTIONS"])) {
            unset($GLOBALS["FUNCTIONS"][$n]);
    }

$GLOBALS["FUNCTIONS"]=array_values($GLOBALS["FUNCTIONS"]); // Array-Schlüssel bereinigen


?>
