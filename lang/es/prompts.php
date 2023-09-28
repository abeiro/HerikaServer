<?php 



// Patrones comunes para usar en la mayoría de las funciones
$TEMPLATE_DIALOG = "interpreta a {$GLOBALS["HERIKA_NAME"]} completando el diálogo de {$GLOBALS["HERIKA_NAME"]} usando este formato '{$GLOBALS["HERIKA_NAME"]}: (estado de ánimo opcional de esta lista [" . implode(",", (@is_array($GLOBALS["AZURETTS_CONF"]["validMoods"])) ? $GLOBALS["AZURETTS_CONF"]["validMoods"] : array()) . "]) ...'";

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION = "llama a una función para controlar a {$GLOBALS["HERIKA_NAME"]} o";
} else {
    $TEMPLATE_ACTION = "";
}

$PROMPTS = array(
    "location" => [
        "cue" => ["(Chatea como {$GLOBALS["HERIKA_NAME"]})"], // dar paso a
        "player_request" => ["{$gameRequest[3]} ¿Qué sabes de este lugar?"]  // requerimiento
    ],

    "book" => [
        "cue" => ["(Nota que a pesar de su mala memoria, {$GLOBALS["HERIKA_NAME"]} es capaz de recordar libros enteros)"],
        "player_request" => ["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, resume brevemente este libro: "]  // requerimiento

    ],

    "combatend" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre el último encuentro en combate) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} se ríe del estilo de combate de {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre las armas de {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} admira el estilo de combate de {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG"
        ],
        "extra" => ["force_tokens_max" => "50", "dontuse" => (time() % 5 != 0)]   // 20% de probabilidad
    ],

    "quest" => [
        "cue" => ["$TEMPLATE_DIALOG"],
        "player_request" => "{$GLOBALS["HERIKA_NAME"]}, ¿qué debemos hacer acerca de esta misión?"
    ],

    "bleedout" => [
        "cue" => ["{$GLOBALS["HERIKA_NAME"]} se queja de casi ser derrotada, $TEMPLATE_DIALOG"]
    ],

    "bored" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual o una broma sobre la ubicación actual) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre el clima actual) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre la hora y la fecha) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre el último evento) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre un meme de Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre cualquiera de los Dioses en Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre la política de Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre un evento histórico del Universo de Elder Scrolls) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre un libro del Universo de Elder Scrolls) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual que comienza con: Una vez tuve que... )$TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual que comienza con: ¿Has oído lo que sucedió en... )$TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual que comienza con: Un sabio hombre Akaviri una vez me dijo... )$TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre la relación/amistad actual con {$GLOBALS["PLAYER_NAME"]})$TEMPLATE_DIALOG"
        ]
    ],

    "goodmorning" => [
        "cue" => ["({$GLOBALS["HERIKA_NAME"]} comenta sobre la siesta de {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG"],
        "player_request" => ["(despertando después de dormir). ahhhh  "]
    ],

    "inputtext" => [
        "cue" => ["$TEMPLATE_ACTION $TEMPLATE_DIALOG "] // La sugerencia es implícita

    ],
    "inputtext_s" => [
        "cue" => ["$TEMPLATE_ACTION $TEMPLATE_DIALOG"], // La sugerencia es implícita
        "extra" => ["mood" => "whispering"]
    ],
    "afterfunc" => [
        "extra" => [],
        "cue" => [
            "default" => "{$GLOBALS["HERIKA_NAME"]} habla con {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG",
            "TakeASeat" => "({$GLOBALS["HERIKA_NAME"]} habla sobre la ubicación para sentarse)$TEMPLATE_DIALOG",
            "GetDateTime" => "({$GLOBALS["HERIKA_NAME"]} responde con la fecha y hora actual en una oración corta)$TEMPLATE_DIALOG",
            "MoveTo" => "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el destino del movimiento)$TEMPLATE_DIALOG"
        ]
    ],
    "lockpicked" => [
        "cue" => ["({$GLOBALS["HERIKA_NAME"]} comenta sobre el objeto abierto con ganzúa) $TEMPLATE_DIALOG"],
        "player_request" => ["({$GLOBALS["PLAYER_NAME"]} ha desbloqueado {$gameRequest[3]})"],
        "extra" => ["mood" => "whispering"]
    ],
    "afterattack" => [
        "cue" => ["(interpreta a {$GLOBALS["HERIKA_NAME"]}, ella grita una frase de combate) $TEMPLATE_DIALOG"]
    ],
    // Como inputtext, pero sin la parte de las llamadas a funciones. Es probable que se use en scripts de papiro
    "chatnf" => [
        "cue" => ["$TEMPLATE_DIALOG"] // La sugerencia es implícita

    ],
    "diary" => [
        "cue" => ["Por favor, escribe en tu diario personal un breve resumen del último diálogo y los eventos de {$GLOBALS["PLAYER_NAME"]} y {$GLOBALS["HERIKA_NAME"]} escritos arriba. Escribe solo como {$GLOBALS["HERIKA_NAME"]}."],
        "extra" => ["force_tokens_max" => 0]
    ],

);


?>
