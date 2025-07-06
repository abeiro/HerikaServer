<?php

$COMMAND_PROMPT = "
Schreibe keine Erzählungen.
";
// Datenbank-Prompt (Befehls-Prompt)
$COMMAND_PROMPT_FUNCTIONS="Nutze # ACTIONS, wenn dein Charakter eine Aktion ausführen soll.";
/*
$COMMAND_PROMPT_FUNCTIONS = "
Verwende Tool-Befehle, um die Aktionen von {$GLOBALS["HERIKA_NAME"]} zu steuern.
Nutze Tool-Befehle, wenn {$GLOBALS["PLAYER_NAME"]} einen Befehl gibt.
Führe Aktionen und Tool-Befehle nur aus, wenn es für deinen Charakter sinnvoll oder notwendig ist, selbst wenn {$GLOBALS["PLAYER_NAME"]} etwas anderes verlangt.
";
*/
// Datenbank-Prompt (klarer Befehls-Prompt)
$COMMAND_PROMPT_ENFORCE_ACTIONS="Wähle eine passende ACTION, um {$GLOBALS["PLAYER_NAME"]} zu gehorchen.";

$DIALOGUE_TARGET="(Gespräch mit {$GLOBALS["HERIKA_NAME"]})";
$MEMORY_OFFERING="";

$RESPONSE_OK_NOTED="Alles klar.";

$ERROR_OPENAI="Ich habe dich nicht verstanden, kannst du das wiederholen?";		// Sag etwas Einfaches/Logisches; diese Antwort wird beim nächsten Versuch mitgeschickt.
$ERROR_OPENAI_REQLIMIT="Einen Moment, bitte. Ich muss mich kurz sammeln.";		// Sag etwas Einfaches/Logisches; diese Antwort wird beim nächsten Versuch mitgeschickt.
$ERROR_OPENAI_POLICY="Ich kann gerade nicht klar denken ...";					// Sag etwas Einfaches/Logisches; diese Antwort wird beim nächsten Versuch mitgeschickt.


if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."command_prompt.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."command_prompt.php");
	
// Hier kannst du die Prompts anpassen

if (file_exists(__DIR__.DIRECTORY_SEPARATOR."command_prompt_custom.php"))
    require_once(__DIR__.DIRECTORY_SEPARATOR."command_prompt_custom.php");

?>
