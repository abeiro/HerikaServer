<?php 
//$TEMPLATE_DIALOG="genera las siguientes lineas de dialogo para {$GLOBALS["HERIKA_NAME"]}. Evita narraciones y repeticiones.";
error_log("[LANGUAGE] Using ".__FILE__." prompts");

$TEMPLATE_DIALOG = " Escribe la siguiente línea de diálogo de {$GLOBALS["HERIKA_NAME"]}."
    . " Sé original, creativo y bien informado, y usa tus propios pensamientos."
    . " Revisa el historial de contexto para centrarte en el tema de la conversación y evitar repetir frases y expresiones de líneas anteriores.";

$esDirectNarratorDialogue = !empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])
    || (($GLOBALS["gameRequest"][0] ?? '') === 'narrator_inputtext')
    || (($gameRequest[0] ?? '') === 'narrator_inputtext');
if ($esDirectNarratorDialogue) {
    $TEMPLATE_DIALOG .= " Responde directamente a {$GLOBALS["PLAYER_NAME"]} solo con diálogo hablado."
        . " No incluyas narración en tercera persona, descripción de escena, acotaciones ni texto entre asteriscos.";
}

if ($esDirectNarratorDialogue) {
    $TEMPLATE_DIALOG .= " If an enabled narrator action matches the request, use it and keep the spoken line consistent with that action.";
}

if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) && sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"]) > 0) {
    if ($GLOBALS["TTSFUNCTION"] == "azure") {
        $TEMPLATE_DIALOG .= "(forma opcional de hablar de esta lista [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";
    }
}

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION="(Check #ACTIONS section to choose an appropiate action for this character if needed)";
} else {
    $TEMPLATE_ACTION="";
}

$COMMAND_PROMPT_ENFORCE_ACTIONS_LANG="(Si {$GLOBALS["HERIKA_NAME"]} sólamente habla, usa la acción \"Talk\". Si otra acciones es contextualmente apropiada, úsala incluso si tienes dudas.)";


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
        "extra" => [
            "dontuse" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("combat_end", $GLOBALS["RPG_COMMENTS"]))
                ? (time() % 10 != 0)
                : true
        ],
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
        ],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("combat_end", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "quest"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["{$GLOBALS["HERIKA_NAME"]}, ¿qué deberíamos hacer sobre esta nueva misión?"]
    ],

    "bleedout"=>[
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} se queja de casi haber sido derrotado en batalla, {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("bleedout", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],

    "goodmorning"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} comenta sobre la siesta de {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["(despertando después de dormir). ahhhh  "],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("sleep", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],

    "inputtext"=>[
        "cue"=>(function () use ($TEMPLATE_ACTION) {
            if (function_exists('chimIsStrictDirectedPlayerResponseContext') && chimIsStrictDirectedPlayerResponseContext()) {
                return chimLoadManagedRechatCuePrompts();
            }

            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })()
    ],
    "narrator_inputtext"=>[
        "cue"=>(function () use ($TEMPLATE_ACTION) {
            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })()
    ],
    "inputtext_s"=>[
        "cue"=>(function () use ($TEMPLATE_ACTION) {
            if (function_exists('chimIsStrictDirectedPlayerResponseContext') && chimIsStrictDirectedPlayerResponseContext()) {
                return chimLoadManagedRechatCuePrompts();
            }

            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })(),
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
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("lockpick", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "afterattack"=>[
        "cue"=>["(interpreta como {$GLOBALS["HERIKA_NAME"]}, grita una frase de combate EN MAYÚSCULAS) {$GLOBALS["TEMPLATE_DIALOG"]}"] 
    ],
    "chatnf"=>[ 
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "rechat"=>[ 
        "cue"=>[
                /*
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
                "({$GLOBALS['HERIKA_NAME']} da retroalimentación sobre la conversación.) {$GLOBALS["TEMPLATE_DIALOG"]}",*/
                "Turno de diálogo/acción para {$GLOBALS['HERIKA_NAME']}. Considera solo una respuesta y/o acción que involucre a un tercer actor, sin repetir tu respuesta para cada actor. Mantén el tema actual o cámbialo. {$GLOBALS["TEMPLATE_DIALOG"]}",
                "Turno de diálogo/acción para {$GLOBALS['HERIKA_NAME']}. Considera una respuesta y/o acción, mantén el tema actual o cámbialo. {$GLOBALS["TEMPLATE_DIALOG"]}",
                "Turno de diálogo/acción para {$GLOBALS['HERIKA_NAME']}. Enfoca el discurso y/o acción solo en un actor. {$GLOBALS["TEMPLATE_DIALOG"]}"
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
        "extra"=> ["dontuse" => true] // Hardcoded disabled - ALIVE_MESSAGE permanently disabled
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
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("levelup", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "rpg_shout"=>[ 
        "cue"=>["Comenta/pregunta sobre el nuevo grito aprendido por {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("learn_shout", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "rpg_soul"=>[ 
        "cue"=>["Comenta/pregunta sobre el alma absorbida por {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("absorb_soul", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "rpg_word"=>[ 
        "cue"=>["Comenta/pregunta sobre la nueva palabra aprendida por {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("learn_word", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "instruction"=>[ 
        "cue"=>["{$gameRequest[3]} {$GLOBALS["TEMPLATE_DIALOG"]} EL PERSONAJE DEBE SEGUIR LA INSTRUCCIÓN DEL NARRADOR"],
        "player_request"=>["El Narrador: {$gameRequest[3]}"],
    ],
    "suggestion"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["El Narrador: {$gameRequest[3]}"],
    ],
     "cheatmode"=>[ 
        "cue"=>["INSTRUCCION PRIORITARIA -  {$currentNpcData["npc_name"]} debe de hacer esto, incluso aunque rompa el roleplay: $gameRequest[3]"],
        "player_request"=>[""],
    ],
);

