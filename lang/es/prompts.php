<?php

// Nueva estructura
// $PROMPTS["evento"]["señal"] => array que contiene señales. Este es el último texto enviado a LLM, debería ser una instrucción guiada
// $PROMPTS["evento"]["solicitud_jugador"] => array que contiene requisitos. Esto es lo que el jugador está solicitando (una pregunta, un comentario...)
// $PROMPTS["evento"]["extra"] => habilitar/deshabilitar, forzar modificación, cambiar límite de tokens o definir una función de transformador (no relacionada con IA).
// La Prompt completa es entonces $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requisito + señal

// Patrones comunes para usar en la mayoría de las funciones
$TEMPLATE_DIALOG = "escribe la siguiente línea de diálogo de {$GLOBALS["HERIKA_NAME"]}, escribe usando este formato \"{$GLOBALS["HERIKA_NAME"]}: ";

if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) && sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"]) > 0)
    if ($GLOBALS["TTSFUNCTION"] == "azure")
        $TEMPLATE_DIALOG .= "(forma opcional de hablar de esta lista [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";

$TEMPLATE_DIALOG .= " \"";

if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
    $GLOBALS["MEMORY_STATEMENT"] = ".USE #MEMORY.";
} else
    $GLOBALS["MEMORY_STATEMENT"] = "";

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION = "llamar a una función para controlar a {$GLOBALS["HERIKA_NAME"]} o";
    $TEMPLATE_ACTION = ".PUEDES USAR FUNCIONES.";    // WIP
} else {
    $TEMPLATE_ACTION = "";
}

$PROMPTS = array(
    "location" => [
        "cue" => ["(Chatear como {$GLOBALS["HERIKA_NAME"]})"], // dar paso a
        "player_request" => ["{$gameRequest[3]} ¿Qué sabes sobre este lugar?"]  //requisito
    ],

    "book" => [
        "cue" => ["(Aunque su memoria sea deficiente, {$GLOBALS["HERIKA_NAME"]} es capaz de recordar libros enteros)"],
        "player_request" => ["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, resume este libro brevemente: "]  //requisito

    ],

    "combatend" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre el último encuentro en combate) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} se ríe del estilo de combate de {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre las armas de {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre los enemigos derrotados) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} maldice a los enemigos derrotados.) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} insulta a los enemigos derrotados con enojo) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} nota algo peculiar) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} admira el estilo de combate de {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG"
        ],
        "extra" => ["force_tokens_max" => "50", "dontuse" => (time() % 5 != 0)]   //20% de probabilidad
    ],

    "quest" => [
        "cue" => ["$TEMPLATE_DIALOG"],
        //"player_request"=>"{$GLOBALS["HERIKA_NAME"]}, what should we do about this quest '{$questName}'?"
        "player_request" => "{$GLOBALS["HERIKA_NAME"]}, ¿qué deberíamos hacer con esta misión?"
    ],

    "bleedout" => [
        "cue" => ["{$GLOBALS["HERIKA_NAME"]} se queja de casi ser derrotada, $TEMPLATE_DIALOG"]
    ],

    "bored" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} hace un chiste sobre la ubicación actual) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre el clima actual) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre la hora y la fecha actuales) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre el último evento) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre un meme de Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre cualquiera de los dioses en Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre la política de Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre un evento histórico del universo de Elder Scrolls) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre un libro del universo de Elder Scrolls) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual comenzando con: Una vez tuve que ) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual comenzando con: ¿Escuchaste lo que pasó en) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual comenzando con: Un sabio hombre Akaviri una vez me dijo) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre el estado actual de la relación/amistad con {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG"
        ]
    ],

    "goodmorning" => [
        "cue" => ["({$GLOBALS["HERIKA_NAME"]} comenta sobre la siesta de {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG"],
        "player_request" => ["(despertando después de dormir). ahhhh  "]
    ],

    "inputtext" => [
        "cue" => [
            "$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} responde a la última frase de {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["MEMORY_STATEMENT"]} $TEMPLATE_DIALOG "
        ]
        // La Prompt es implícita

    ],
    "inputtext_s" => [
        "cue" => ["$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} responde a {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["MEMORY_STATEMENT"]} $TEMPLATE_DIALOG"], // La Prompt es implícita
        "extra" => ["mood" => "susurrando"]
    ],
    "afterfunc" => [
        "extra" => [],
        "cue" => [
            "default" => "{$GLOBALS["HERIKA_NAME"]} habla con {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG",
            "TakeASeat" => "({$GLOBALS["HERIKA_NAME"]} habla sobre la ubicación de estar sentado)$TEMPLATE_DIALOG",
            "GetDateTime" => "({$GLOBALS["HERIKA_NAME"]} responde con la fecha y hora actuales en una frase corta)$TEMPLATE_DIALOG",
            "MoveTo" => "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el destino del movimiento)$TEMPLATE_DIALOG"
        ]
    ],
    "lockpicked" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre el objeto forzado) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} pregunta a {$GLOBALS["PLAYER_NAME"]} qué encontró) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} recuerda a {$GLOBALS["PLAYER_NAME"]} compartir el botín) $TEMPLATE_DIALOG"
        ],
        "player_request" => ["({$GLOBALS["PLAYER_NAME"]} ha desbloqueado {$gameRequest[3]})"],
        "extra" => ["mood" => "susurrando"]
    ],
    "afterattack" => [
        "cue" => ["(interpreta a {$GLOBALS["HERIKA_NAME"]}, grita una frase pegajosa para el combate) $TEMPLATE_DIALOG"]
    ],
    // Como inputtext, pero sin la parte de las llamadas a funciones. Es probable que se use en scripts de papiro
    "chatnf" => [
        "cue" => ["$TEMPLATE_DIALOG"] // La Prompt es implícita

    ],
    "diary" => [
        "cue" => ["Escribe un breve resumen de los últimos diálogos y eventos de {$GLOBALS["PLAYER_NAME"]} y {$GLOBALS["HERIKA_NAME"]} escritos arriba en el diario de {$GLOBALS["HERIKA_NAME"]}. ESCRIBE COMO SI FUERAS {$GLOBALS["HERIKA_NAME"]}."],
        "extra" => ["force_tokens_max" => 0]
    ],
    "vision" => [
        "cue" => ["{$GLOBALS["HERIKA_NAME"]} describe lo que está viendo a {$GLOBALS["PLAYER_NAME"]}. Presta atención a los pequeños detalles de la escena. $TEMPLATE_DIALOG."],
        "player_request" => ["{$GLOBALS["PLAYER_NAME"]} : Mira esto, {$GLOBALS["HERIKA_NAME"]}.( {$GLOBALS["HERIKA_NAME"]} mira el ESCENARIO ACTUAL y ve esto: '{$gameRequest[3]}'"],
        "extra" => ["force_tokens_max" => 128]
    ]
);


?>
