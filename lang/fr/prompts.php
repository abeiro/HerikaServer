<?php 
$TEMPLATE_DIALOG = " Écris la prochaine réplique de {$GLOBALS["HERIKA_NAME"]}."
    . " Sois original, créatif et pertinent, en t'appuyant sur tes propres idées."
    . " Consulte l'historique du contexte pour rester sur le sujet de la conversation et éviter de répéter des phrases ou des tournures déjà utilisées.";

$frDirectNarratorDialogue = !empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])
    || (($GLOBALS["gameRequest"][0] ?? '') === 'narrator_inputtext')
    || (($gameRequest[0] ?? '') === 'narrator_inputtext');
if ($frDirectNarratorDialogue) {
    $TEMPLATE_DIALOG .= " Réponds directement à {$GLOBALS["PLAYER_NAME"]} uniquement avec du dialogue parlé."
        . " N'ajoute pas de narration à la troisième personne, de description de scène, d'indications scéniques ni de texte entre astérisques.";
}

if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) && sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"]) > 0) {
    if ($GLOBALS["TTSFUNCTION"] == "azure") {
        $TEMPLATE_DIALOG .= "(manière facultative de parler parmi cette liste [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";
    }
}

$PROMPTS=array(
    "location"=>[
            "cue"=>["(Discute comme {$GLOBALS["HERIKA_NAME"]})"],
            "player_request"=>["{$gameRequest[3]} Que sais-tu à propos de cet endroit ?"]
        ],
    
    "book"=>[
        "cue"=>["(Tiens compte du fait que malgré sa mauvaise mémoire, {$GLOBALS["HERIKA_NAME"]} est capable de se souvenir de livres entiers)"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, résume brièvement ce livre : "]
        
    ],
    
    "combatend"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} commente les armes de {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} commente les ennemis vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} maudit les ennemis vaincus.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} insulte avec colère les ennemis vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait une blague sur les ennemis vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur le type d'ennemis qui ont été vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} remarque quelque chose de particulier chez le dernier ennemi vaincu) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "extra" => [
            "dontuse" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("combat_end", $GLOBALS["RPG_COMMENTS"]))
                ? (time() % 10 != 0)
                : true
        ],
    ],
    "combatendmighty"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} commente les armes de {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} commente les ennemis vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} maudit les ennemis vaincus.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} insulte avec colère les ennemis vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait une blague sur les ennemis vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur le type d'ennemis qui ont été vaincus) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} remarque quelque chose de particulier chez le dernier ennemi vaincu) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("combat_end", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "quest"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["{$GLOBALS["HERIKA_NAME"]}, que devrions-nous faire concernant cette nouvelle mission ?"]
    ],

    "bleedout"=>[
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} se plaint d'avoir failli être vaincu(e) au combat, {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("bleedout", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],

    "goodmorning"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} commente la sieste de {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["(se réveillant après avoir dormi). ahhhh  "],
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
        "extra"=>["mood"=>"chuchotant"]
    ],
    "memory"=>[
        "cue"=>[
            "$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} se souvient de ce souvenir. \"#MEMORY_INJECTION_RESULT#\" {$GLOBALS["TEMPLATE_DIALOG"]} "
        ]
    ],
    "afterfunc"=>[
        "extra"=>[],
        "cue"=>[
            "default"=>"{$GLOBALS["HERIKA_NAME"]} parle avec {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}",
            "TakeASeat"=>"({$GLOBALS["HERIKA_NAME"]} parle de l'endroit où il/elle s'assoit){$GLOBALS["TEMPLATE_DIALOG"]}",
            "GetDateTime"=>"({$GLOBALS["HERIKA_NAME"]} répond avec la date et l'heure actuelles en une phrase courte){$GLOBALS["TEMPLATE_DIALOG"]}",
            "MoveTo"=>"({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur le déplacement vers la destination){$GLOBALS["TEMPLATE_DIALOG"]}",
            "CheckInventory"=>"({$GLOBALS["HERIKA_NAME"]} parle des objets de l'inventaire et du sac à dos){$GLOBALS["TEMPLATE_DIALOG"]}",
            "Inspect"=>"({$GLOBALS["HERIKA_NAME"]} parle des objets inspectés){$GLOBALS["TEMPLATE_DIALOG"]}",
            "ReadQuestJournal"=>"({$GLOBALS["HERIKA_NAME"]} parle des quêtes qu'il/elle a lues dans le journal des quêtes){$GLOBALS["TEMPLATE_DIALOG"]}",
            "TravelTo"=>"({$GLOBALS["HERIKA_NAME"]} parle de la destination){$GLOBALS["TEMPLATE_DIALOG"]}",
            "InspectSurroundings"=>"({$GLOBALS["HERIKA_NAME"]} parle des êtres ou ennemis détectés){$GLOBALS["TEMPLATE_DIALOG"]}"
            ]
    ],
    "lockpicked"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} commente l'objet crocheté {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} demande à {$GLOBALS["PLAYER_NAME"]} ce qu'il/elle a trouvé) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} demande à {$GLOBALS["PLAYER_NAME"]} de partager ce qu'il/elle a trouvé) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "player_request"=>["({$GLOBALS["PLAYER_NAME"]} a crocheté) {$gameRequest[3]})"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("lockpick", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "afterattack"=>[
        "cue"=>["(interprète comme {$GLOBALS["HERIKA_NAME"]}, crie une phrase de combat EN MAJUSCULES) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "chatnf"=>[ 
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "rechat"=>[ 
        "cue"=>[
                "({$GLOBALS['HERIKA_NAME']} intervient dans la conversation, parlant au dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} participe à la conversation, parlant au dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} suit la conversation.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} fait une déclaration sur la conversation.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} fait une observation au dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} plaisante sur la phrase du dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} ajoute un commentaire à la conversation.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} partage une opinion avec le dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} répond de manière réfléchie au dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} pose une question au dernier interlocuteur.) {$GLOBALS["TEMPLATE_DIALOG"]}",
                "({$GLOBALS['HERIKA_NAME']} donne un retour sur la conversation.) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
    ],
    "diary"=>[ 
        "cue"=>["Veuillez écrire un bref résumé des derniers dialogues et événements de {$GLOBALS["PLAYER_NAME"]} et {$GLOBALS["HERIKA_NAME"]} écrits ci-dessus dans le journal de {$GLOBALS["HERIKA_NAME"]}. ÉCRIS COMME SI TU ÉTAIS {$GLOBALS["HERIKA_NAME"]}."],
        "extra"=>["force_tokens_max"=>0]
    ],
    "vision"=>[ 
        "cue"=>["{$GLOBALS["ITT"][$GLOBALS["ITTFUNCTION"]]["AI_PROMPT"]}. "],
        "player_request"=>["Le Narrateur : {$GLOBALS["HERIKA_NAME"]} regarde le SCÉNARIO ACTUEL, et voit ceci : '{$gameRequest[3]}'"],
        "extra"=>["force_tokens_max"=>512]
    ],
    "chatsimfollow"=>[ 
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} intervient dans la conversation.) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "im_alive"=>[ 
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} parle du fait qu'il/elle se 'sent plus réel(le)'. Écris le dialogue de {$GLOBALS["HERIKA_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["Le Narrateur : {$GLOBALS["HERIKA_NAME"]} ressent un choc soudain... et se sent 'plus réel(le)'"],
        "extra"=> ["dontuse" => true] // Hardcoded disabled - ALIVE_MESSAGE permanently disabled
    ],
    "playerinfo"=>[ 
        "cue"=>["(Hors du jeu de rôle, la partie a été chargée) Raconte à {$GLOBALS["PLAYER_NAME"]} un bref résumé des derniers événements, puis rappelle-lui la tâche/mission/plan actuel(le)) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "newgame"=>[ 
        "cue"=>["(Hors du jeu de rôle, nouvelle partie) Souhaite la bienvenue à {$GLOBALS["PLAYER_NAME"]}, une nouvelle partie a commencé. Rappelle-lui ses missions) {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra"=>["dontuse"=>true] 
    ],
    "traveldone"=>[ 
        "cue"=>["Commente la destination atteinte. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["Le Narrateur : Le groupe arrive à destination)"]
    ],
    "rpg_lvlup"=>[ 
        "cue"=>["Commente quelque chose sur le fait que {$GLOBALS["PLAYER_NAME"]} semble plus puissant. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("levelup", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "rpg_shout"=>[ 
        "cue"=>["Commente/demande à propos du nouveau cri appris par {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("learn_shout", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "rpg_soul"=>[ 
        "cue"=>["Commente/demande à propos de l'âme absorbée par {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("absorb_soul", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "rpg_word"=>[ 
        "cue"=>["Commente/demande à propos du nouveau mot appris par {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("learn_word", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],
    "instruction"=>[ 
        "cue"=>["{$gameRequest[3]} {$GLOBALS["TEMPLATE_DIALOG"]} LE PERSONNAGE DOIT SUIVRE L'INSTRUCTION DU NARRATEUR"],
        "player_request"=>["Le Narrateur : {$gameRequest[3]}"],
    ],
    "suggestion"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["Le Narrateur : {$gameRequest[3]}"],
    ],
);
