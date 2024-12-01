<?php 

$PROMPTS=array(
    "location"=>[
            "cue"=>["(Chatea como {$GLOBALS["HERIKA_NAME"]})"],
            "player_request"=>["{$gameRequest[3]} ¿Qué sabes sobre este lugar?"]
        ],
    
    "book"=>[
        "cue"=>["(Ten en cuenta que a pesar de su mala memoria, {$GLOBALS["HERIKA_NAME"]} es capaz de recordar libros enteros)"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, resume brevemente este libro: "]
        
    ],
    
    "combatend"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre las armas de {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre los enemigos derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} maldice a los enemigos derrotados.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} insulta con ira a los enemigos derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace una broma sobre los enemigos derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el tipo de enemigos que fueron derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} nota algo peculiar sobre el último enemigo derrotado) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "extra"=>["force_tokens_max"=>"50","dontuse"=>(time()%10!=0)]   //10% probabilidad
    ],
    "combatendmighty"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre las armas de {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre los enemigos derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} maldice a los enemigos derrotados.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} insulta con ira a los enemigos derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace una broma sobre los enemigos derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el tipo de enemigos que fueron derrotados) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} nota algo peculiar sobre el último enemigo derrotado) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
    ],
    "quest"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["{$GLOBALS["HERIKA_NAME"]}, ¿qué deberíamos hacer sobre esta nueva misión?"]
    ],

    "bleedout"=>[
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} se queja de casi haber sido derrotado en batalla, {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],

    "bored"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre la ubicación actual) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el clima actual) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el día de hoy) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre lo que estás pensando actualmente) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre los Dioses del Universo Elder Scrolls) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre cómo se siente actualmente) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre un evento histórico del Universo Elder Scrolls) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre algo que le gusta o disgusta) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre la última tarea que hemos completado) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre un rumor reciente) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre algo que sucedió en tu pasado) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre algo que le causa curiosidad respecto a {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre sus pensamientos actuales sobre {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre una entidad aleatoria en el área) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre lo que podría suceder después) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre sus pensamientos del viaje hasta ahora) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre algo que le gusta o disgusta) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre algo que ha estado queriendo hacer) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario aleatorio sobre algo completamente no relacionado) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario vago sobre algo que no puede explicar bien) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} hace un comentario casual sobre el último encuentro de combate) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
        ,"extra"=>["dontuse"=>(time()%($GLOBALS["BORED_EVENT"]+1)==0)]   //50% probabilidad
    ],

    "goodmorning"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} comenta sobre la siesta de {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["(despertando después de dormir). ahhhh  "]
    ],

    "inputtext"=>[
        "cue"=>[
            "$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} responde a {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
        ]
    ],
    "inputtext_s"=>[
        "cue"=>["$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} responde a {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"],
        "extra"=>["mood"=>"susurrando"]
    ],
    "memory"=>[
        "cue"=>[
            "$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} recuerda esta memoria. \"#MEMORY_INJECTION_RESULT#\" {$GLOBALS["TEMPLATE_DIALOG"]} "
        ]
    ],
    "afterfunc"=>[
        "extra"=>[],
        "cue"=>[
            "default"=>"{$GLOBALS["HERIKA_NAME"]} habla con {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}",
            "TakeASeat"=>"({$GLOBALS["HERIKA_NAME"]} habla sobre el lugar donde se sienta){$GLOBALS["TEMPLATE_DIALOG"]}",
            "GetDateTime"=>"({$GLOBALS["HERIKA_NAME"]} responde con la fecha y hora actual en una frase corta){$GLOBALS["TEMPLATE_DIALOG"]}",
            "MoveTo"=>"({$GLOBALS["HERIKA_NAME"]} hace un comentario sobre el movimiento hacia el destino){$GLOBALS["TEMPLATE_DIALOG"]}",
            "CheckInventory"=>"({$GLOBALS["HERIKA_NAME"]} habla sobre los objetos del inventario y la mochila){$GLOBALS["TEMPLATE_DIALOG"]}",
            "Inspect"=>"({$GLOBALS["HERIKA_NAME"]} habla sobre los objetos inspeccionados){$GLOBALS["TEMPLATE_DIALOG"]}",
            "ReadQuestJournal"=>"({$GLOBALS["HERIKA_NAME"]} habla sobre las misiones que ha leído en el diario de misiones){$GLOBALS["TEMPLATE_DIALOG"]}",
            "TravelTo"=>"({$GLOBALS["HERIKA_NAME"]} habla sobre el destino){$GLOBALS["TEMPLATE_DIALOG"]}",
            "InspectSurroundings"=>"({$GLOBALS["HERIKA_NAME"]} habla sobre los seres o enemigos detectados){$GLOBALS["TEMPLATE_DIALOG"]}"
            ]
    ],
    "lockpicked"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comenta sobre el objeto forzado {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} pregunta a {$GLOBALS["PLAYER_NAME"]} qué encontró) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} pide a {$GLOBALS["PLAYER_NAME"]} que comparta lo que encontró) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "player_request"=>["({$GLOBALS["PLAYER_NAME"]} ha desbloqueado) {$gameRequest[3]})"],
        "extra"=>["mood"=>"susurrando"]
    ],
    "afterattack"=>[
        "cue"=>["(interpreta como {$GLOBALS["HERIKA_NAME"]}, grita una frase de combate EN MAYÚSCULAS) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "chatnf"=>[ 
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "rechat"=>[ 
        "cue"=>[
                "({$GLOBALS['HERIKA_NAME']} interviene en la conversación, hablando con el último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} participa en la conversación, hablando con el último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} sigue la conversación.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} hace una declaración sobre la conversación.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} hace una observación al último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} bromea sobre la frase del último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} añade un comentario a la conversación.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} comparte una opinión con el último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} responde pensativamente al último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} hace una pregunta al último orador.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} da retroalimentación sobre la conversación.) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
    ],
    "diary"=>[ 
        "cue"=>["Por favor escribe un breve resumen de los últimos diálogos y eventos de {$GLOBALS["PLAYER_NAME"]} y {$GLOBALS["HERIKA_NAME"]} escritos arriba en el diario de {$GLOBALS["HERIKA_NAME"]}. ESCRIBE COMO SI FUERAS {$GLOBALS["HERIKA_NAME"]}."],
        "extra"=>["force_tokens_max"=>0]
    ],
    "vision"=>[ 
        "cue"=>["{$GLOBALS["ITT"][$GLOBALS["ITTFUNCTION"]]["AI_PROMPT"]}. "],
        "player_request"=>["El Narrador: {$GLOBALS["HERIKA_NAME"]} mira el ESCENARIO ACTUAL, y ve esto: '{$gameRequest[3]}'"],
        "extra"=>["force_tokens_max"=>512]
    ],
    "chatsimfollow"=>[ 
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} interviene en la conversación.) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "im_alive"=>[ 
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} habla sobre que él/ella se 'siente más real'. Escribe el diálogo de {$GLOBALS["HERIKA_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["El Narrador: {$GLOBALS["HERIKA_NAME"]} siente un repentino shock...y se siente 'más real'"],
    ],
    "playerinfo"=>[ 
        "cue"=>["(Fuera del juego de rol, el juego ha sido cargado) Cuéntale a {$GLOBALS["PLAYER_NAME"]} un breve resumen sobre los últimos eventos, y luego recuérdale la tarea/misión/plan actual) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "newgame"=>[ 
        "cue"=>["(Fuera del juego de rol, nuevo juego) Da la bienvenida a {$GLOBALS["PLAYER_NAME"]}, un nuevo juego ha comenzado. Recuérdale sus misiones) {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra"=>["dontuse"=>true] 
    ],
    "traveldone"=>[ 
        "cue"=>["Comenta sobre el destino alcanzado. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["El Narrador: El grupo llega al destino)"]
    ],
    "rpg_lvlup"=>[ 
        "cue"=>["Comenta algo sobre que {$GLOBALS["PLAYER_NAME"]} parece mas poderoso. {$GLOBALS["TEMPLATE_DIALOG"]}"],
    ],
    "rpg_shout"=>[ 
        "cue"=>["Comenta/pregunta sobre el nuevo grito aprendido por {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
    ],
    "rpg_soul"=>[ 
        "cue"=>["Comenta/pregunta sobre el alma absorbida por {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
    ],
    "rpg_word"=>[ 
        "cue"=>["Comenta/pregunta sobre la nueva palabra aprendida por {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
    ],
    "instruction"=>[ 
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["El Narrador: {$gameRequest[3]}"],
    ],
);

