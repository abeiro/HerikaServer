<?php

require_once("dialogue_prompt.php");

$PROMPTS=array(
    "location"=>[
            "cue"=>["(Sprich als {$GLOBALS["HERIKA_NAME"]})"], // führt zu ...
            "player_request"=>["{$gameRequest[3]} Was wisst Ihr über diesen Ort?"]  //Anforderung
        ],
    // Datenbank-Prompt (Buch)
    "book"=>[
        "cue"=>["(Beachte, dass {$GLOBALS["HERIKA_NAME"]} sich trotz ihres schlechten Gedächtnisses an ganze Bücher erinnern kann)"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, fasst dieses Buch bitte kurz zusammen: "]  //Anforderung
        
    ],
    // Datenbank-Prompt (Ende vom Kampf)
    "combatend"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die Waffen von {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die besiegten Feinde) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} flucht über die besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} ist wütend und beleidigt die besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} macht einen Witz über die besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} äußert sich zum Typ der besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} bemerkt etwas Auffälliges am zuletzt besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "extra" => [
            "dontuse" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("combat_end", $GLOBALS["RPG_COMMENTS"]))
                ? (time() % 10 != 0)
                : true
        ],
    ],
    // Datenbank-Prompt (Ende vom mächtigen Kampf)
    "combatendmighty"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die Waffen von {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die besiegten Feinde) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} flucht über die besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} beleidigt die besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht einen Witz über die besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} äußert sich zum Typ der besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} bemerkt etwas Auffälliges am zuletzt besiegten Gegner) {$GLOBALS["TEMPLATE_DIALOG"]}"
    ],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("combat_end", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Aufgabe/Quest)
    "quest"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        //"player_request"=>"{$GLOBALS["HERIKA_NAME"]}, was sollen wir mit der Aufgabe/Quest '{$questName}' machen?"
		"player_request"=>["{$GLOBALS["HERIKA_NAME"]}, was sollen wir mit dieser neuen Aufgabe/Quest machen?"]
    ],

    "bleedout"=>[
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} beschwert sich darüber, im Kampf fast besiegt worden zu sein, {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("bleedout", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Gelangweilt)
    "bored"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über den aktuellen Ort) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über das aktuelle Wetter) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über den heutigen Tag) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über das, was du gerade denkst) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die Götter im Elder-Scrolls-Universum) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die eigenen Gefühle) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über ein geschichtliches Ereignis aus dem Elder-Scrolls-Universum) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über Vorlieben oder Abneigungen) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die letzte erledigte Aufgabe) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über ein kürzlich gehörtes Gerücht) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über etwas, das {$GLOBALS["PLAYER_NAME"]} betrifft und Neugier weckt) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über aktuelle Gedanken zu {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über eine zufällige Kreatur/Person in der Nähe) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung darüber, was als Nächstes passieren könnte) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die eigenen Gedanken zur bisherigen Reise) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über etwas, das schon länger geplant war) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über etwas völlig Unzusammenhängendes) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über etwas, das schwer zu erklären ist) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über das letzte Gefecht) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die aktuelle Stimmung) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über den Geruch der Umgebung) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über eine nahe Kreatur oder Figur) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung darüber, wie dieser Ort im Vergleich zu einem anderen wirkt) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über eine Lektion aus einem ähnlichen Ort) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die Atmosphäre dieses Ortes) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über etwas, worüber in letzter Zeit viel nachgedacht wurde) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über die Gefährlichkeit oder Sicherheit des Ortes) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über etwas, das zuvor aufgeschnappt wurde) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über persönliche Hoffnungen oder Wünsche) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung darüber, dass der letzte Kampf fast verloren worden wäre) {$GLOBALS["TEMPLATE_DIALOG"]}"
		]
        //,"extra"=>["dontuse"=>true]   //DEAKTIVIERT WÄHREND DER BETA-PHASE
        ,"extra" => ["dontuse" => (rand(0, 99) >= intval($GLOBALS["BORED_EVENT"]))]
    ],
    // Datenbank-Prompt (Guten Morgen)
	"goodmorning"=>[
		"cue"=>["({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über {$GLOBALS["PLAYER_NAME"]}s Schlafzeit) {$GLOBALS["TEMPLATE_DIALOG"]}"],
		"player_request"=>["{$GLOBALS["PLAYER_NAME"]} hat geschlafen und wacht auf. Ahhhh"],
		"extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("sleep", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
	],

	"inputtext"=>[
		"cue"=>[
			//"$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} antwortet {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}", // Die Antwort muss nicht unbedingt an {$GLOBALS["PLAYER_NAME"]} gerichtet sein, KI kann auch mit einem anderen NPC sprechen
			"$TEMPLATE_ACTION. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
		]
			// Prompt ist bereits enthalten

	],
	"inputtext_s"=>[
		"cue"=>["$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} antwortet {$GLOBALS["PLAYER_NAME"]} flüsternd. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"],
		"extra"=>["mood"=>"whispering"]
	],
	// Datenbank-Prompt (Erinnerung)
	"memory"=>[
		"cue"=>[
			"$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} erinnert sich daran. \"#MEMORY_INJECTION_RESULT#\" {$GLOBALS["TEMPLATE_DIALOG"]}"
		]
	],
    "afterfunc"=>[
        "extra"=>[],
        "cue"=>[
            "default"=>"{$GLOBALS["HERIKA_NAME"]} redet mit {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}",
			"TakeASeat"=>"({$GLOBALS["HERIKA_NAME"]} redet über den Ort, an dem sich hingesetzt wurde) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"GetDateTime"=>"({$GLOBALS["HERIKA_NAME"]} nennt Datum und Uhrzeit in einem kurzen Satz) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"MoveTo"=>"({$GLOBALS["HERIKA_NAME"]} sagt etwas über den Weg zum Zielort) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"CheckInventory"=>"({$GLOBALS["HERIKA_NAME"]} redet über Dinge im Rucksack oder Inventar) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"Inspect"=>"({$GLOBALS["HERIKA_NAME"]} kommentiert etwas, das gerade untersucht wurde, kurz und knapp) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"ReadQuestJournal"=>"({$GLOBALS["HERIKA_NAME"]} spricht über Aufgaben/Quests aus dem Tagebuch) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"TravelTo"=>"({$GLOBALS["HERIKA_NAME"]} redet über das Ziel der Reise) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"InspectSurroundings"=>"({$GLOBALS["HERIKA_NAME"]} redet über Personen/Wesen in der Nähe, oder über die gesuchten Personen/Wesen) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"GiveGoldTo"=>"({$GLOBALS["HERIKA_NAME"]} sagt etwas über das gegebene Gold) {$GLOBALS["TEMPLATE_DIALOG"]}",
			"Brawl"=>"({$GLOBALS["HERIKA_NAME"]} {$GLOBALS["TEMPLATE_DIALOG"]}"
            
            ]
    ],
    // Datenbank-Prompt (Aufgeschlossen)
    "lockpicked"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} macht eine Bemerkung über das, was aufgeschlossen wurde){$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} fragt {$GLOBALS["PLAYER_NAME"]}, was gefunden wurde){$GLOBALS["TEMPLATE_DIALOG"]}",
			"({$GLOBALS["HERIKA_NAME"]} bittet {$GLOBALS["PLAYER_NAME"]}, zu erzählen, was gefunden wurde){$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "player_request"=>["({$GLOBALS["PLAYER_NAME"]} hat etwas aufgeschlossen) {$gameRequest[3]})"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("lockpick", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Nach Angriff)
    "afterattack"=>[
        "cue"=>["(Versetze dich in die Rolle von {$GLOBALS["HERIKA_NAME"]} im Kampf und schreie einen Schlachtruf in GROSSBUCHSTABEN) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    // Wie Input Text, aber ohne Funktionsaufrufe. Wird wahrscheinlich in Papyrus-Skripten verwendet.
    "chatnf"=>[ 
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"] // Prompt ist bereits enthalten
        
    ],
    // Datenbank-Prompt (Gesprächsfortsetzung)
    "rechat"=>[ 
        "cue"=>[
            /*"({$GLOBALS['HERIKA_NAME']} reflektiert über das Thema mit dem letzten Sprecher.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} widerspricht dem letzten Sprecher höflich.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} bietet eine alternative Sichtweise zur Diskussion an.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} teilt eine persönliche Anekdote zum Thema.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} hinterfragt die Logik der Aussage des letzten Sprechers.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} hebt einen interessanten Punkt der Diskussion hervor.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} schlägt basierend auf dem Gespräch eine Handlung vor.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} äußert Bedenken zu den Folgen der Diskussion.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} macht einen lockeren Kommentar, um die Spannung zu lösen.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} teilt eine verwandte Tatsache oder Wissen.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} ermutigt den letzten Sprecher, mehr auszuführen.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} stellt die Sichtweise des letzten Sprechers infrage.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} lenkt das Gespräch auf einen anderen Aspekt des Themas.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} zeigt Neugier für das Thema.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} fasst die wichtigsten Punkte der Diskussion zusammen.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} kommentiert die Einsicht des letzten Sprechers.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} bringt Humor ein, um das Gespräch aufzulockern.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} knüpft an eine frühere Diskussion an.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} lenkt den Fokus der Diskussion subtil um.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} spekuliert über mögliche Ergebnisse des Themas.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS['HERIKA_NAME']} warnt vor möglichen Risiken im Zusammenhang mit der Diskussion.) {$GLOBALS["TEMPLATE_DIALOG"]}",*/
            "Dialog-/Aktionrunde für {$GLOBALS['HERIKA_NAME']}. Berücksichtige nur eine Antwort und/oder Aktion mit einer dritten Person/Wesen, ohne die Antwort für jede Person/Wesen zu wiederholen. Behalte das aktuelle Thema bei oder wechsle es. {$GLOBALS["TEMPLATE_DIALOG"]}",
            "Dialog-/Aktionrunde für {$GLOBALS['HERIKA_NAME']}. Überlege dir eine Antwort und/oder Aktion, behalte das Thema bei oder wechsle es. {$GLOBALS["TEMPLATE_DIALOG"]}",
            "Dialog-/Aktionrunde für {$GLOBALS['HERIKA_NAME']}. Konzentriere Rede und/oder Aktion nur auf eine Person/Wesen. {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
        
    ],
    // Datenbank-Prompt (Tagebuch)
    "diary"=>[ 
        "cue"=>["Schreibe eine kurze Zusammenfassung der letzten Dialoge und Ereignisse von {$GLOBALS["PLAYER_NAME"]} und {$GLOBALS["HERIKA_NAME"]} oben ins Tagebuch von {$GLOBALS["HERIKA_NAME"]} . SCHREIBE, ALS WÄRST DU {$GLOBALS["HERIKA_NAME"]}."],
        "extra"=>["force_tokens_max"=>0]
    ],
    // Datenbank-Prompt (Seelenschau)
    "vision"=>[ 
        "cue"=>["{$GLOBALS["ITT"][$GLOBALS["ITTFUNCTION"]]["AI_PROMPT"]}. "],
        //"player_request"=>["{$GLOBALS["PLAYER_NAME"]} : Schaut Euch das hier an, {$GLOBALS["HERIKA_NAME"]}.{$GLOBALS["HERIKA_NAME"]} betrachtet die AKTUELLE SZENE und sieht: '{$gameRequest[3]}'"],
        "player_request"=>["Der Erzähler: {$GLOBALS["HERIKA_NAME"]} betrachtet die aktuelle Szene und sieht: '{$gameRequest[3]}'"],
        "extra"=>["force_tokens_max"=>512]
    ],
    "chatsimfollow"=>[ 
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} mischt sich in das Gespräch ein.) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "im_alive"=> [
        "cue"=> ["{$GLOBALS["HERIKA_NAME"]} spricht darüber, dass das Erleben realer wird. Schreibe den Dialog von {$GLOBALS["HERIKA_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=> ["Der Erzähler: {$GLOBALS["HERIKA_NAME"]} fühlt einen plötzlichen Schock... und fühlt sich realer"],
        "extra"=> (!empty($GLOBALS["ALIVE_MESSAGE"]) && $GLOBALS["ALIVE_MESSAGE"]) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Spiel starten)
    "playerinfo"=>[ 
        "cue"=>["(Außerhalb des Rollenspiels, Spiel wurde geladen) Gib {$GLOBALS["PLAYER_NAME"]} eine kurze Zusammenfassung der letzten Ereignisse und erinnere {$GLOBALS["PLAYER_NAME"]} an die aktuelle Aufgabe/Quest/Plan.) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    // Datenbank-Prompt (Neues Spiel)
    "newgame"=>[ 
        "cue"=>["(Außerhalb des Rollenspiels, neues Spiel ) Begrüße {$GLOBALS["PLAYER_NAME"]}, ein neues Spiel hat begonnen. Erinnere an die Aufgaben/Quests.) {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra"=>["dontuse"=>true] 
    ],
    // Datenbank-Prompt (Reise abgeschlossen)
    "traveldone"=>[ 
        "cue"=>["Mache einen Kommentar zum erreichten Ziel. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["Der Erzähler: Die Gruppe hat das Ziel erreicht)"]
    ],
    // Datenbank-Prompt (RPG Levelaufstieg)
    "rpg_lvlup"=> [
        "cue"   => ["Kommentiere so, als wäre man hautnah dabei, welche Erfahrungen {$GLOBALS["PLAYER_NAME"]} gesammelt hat. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("levelup", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Schrei erlernt)
    "rpg_shout"=>[ 
        "cue"=>["Kommentiere/Frage nach dem neuen Schrei, den {$GLOBALS["PLAYER_NAME"]} gelernt hat. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("learn_shout", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Seele absorbiert)
    "rpg_soul"=>[ 
        "cue"=>["Kommentiere/Frage nach der Seele, die {$GLOBALS["PLAYER_NAME"]} absorbiert hat. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("absorb_soul", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Wort gelernt)
    "rpg_word"=>[ 
        "cue"=>["Kommentiere/Frage nach dem neuen Wort, das {$GLOBALS["PLAYER_NAME"]} gelernt hat. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("learn_word", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    // Datenbank-Prompt (Anweisung)
    "instruction"=>[ 
        "cue"=>["{$gameRequest[3]} Schreibe nur die Dialogzeilen von {$GLOBALS["HERIKA_NAME"]}, ohne Erzähltexte."],
        "player_request"=>["Der Erzähler: {$gameRequest[3]}"],
    ],
    // Datenbank-Prompt (Begrüßung)
    "welcome"=>[ 
        "cue"=>["{$gameRequest[3]}. {$GLOBALS["HERIKA_NAME"]} soll die Umgebung untersuchen, um zu sehen, wer sich hier aufhält. Schreibe nur die Dialogzeilen von {$GLOBALS["HERIKA_NAME"]}, ohne Erzähltexte."],
        "player_request"=>["Der Erzähler: {$gameRequest[3]}"],
    ],
);

if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");

// Prompts werden von Plugins bereitgestellt
    
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"prompts.php");

// Hier kannst du Prompts überschreiben
/*
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php"))
    require_once(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php");
*/
if (php_sapi_name()=="cli") {
    //print_r($PROMPTS);
}
?>
