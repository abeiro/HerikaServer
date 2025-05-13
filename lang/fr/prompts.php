<?php 
$TEMPLATE_DIALOG="génère les lignes de dialogue suivantes pour {$GLOBALS["HERIKA_NAME"]}. Évite les narrations.";

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

    "bored"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur l'emplacement actuel) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur la météo actuelle) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur la journée d'aujourd'hui) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur ce à quoi tu penses actuellement) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur les Dieux de l'Univers Elder Scrolls) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur comment il/elle se sent actuellement) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur un événement historique de l'Univers Elder Scrolls) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur quelque chose qu'il/elle aime ou n'aime pas) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur la dernière tâche que nous avons accomplie) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur une rumeur récente) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur quelque chose qui s'est passé dans ton passé) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur quelque chose qui l'intrigue à propos de {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur ses pensées actuelles concernant {$GLOBALS["PLAYER_NAME"]}) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur une entité aléatoire dans la zone) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur ce qui pourrait arriver ensuite) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur ses réflexions sur le voyage jusqu'à présent) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur quelque chose qu'il/elle aime ou n'aime pas) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire sur quelque chose qu'il/elle a envie de faire depuis un moment) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire aléatoire sur quelque chose de complètement sans rapport) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire vague sur quelque chose qu'il/elle ne peut pas bien expliquer) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} fait un commentaire désinvolte sur la dernière rencontre de combat) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
        ,"extra" => ["dontuse" => (rand(0, 99) >= $GLOBALS["BORED_EVENT"])] 
    ],

    "goodmorning"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} commente la sieste de {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["(se réveillant après avoir dormi). ahhhh  "],
        "extra" => (!empty($GLOBALS["RPG_COMMENTS"]) && in_array("sleep", $GLOBALS["RPG_COMMENTS"])) ? [] : ["dontuse" => true]
    ],

    "inputtext"=>[
        "cue"=>[
            "$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} répond à {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
        ]
    ],
    "inputtext_s"=>[
        "cue"=>["$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} répond à {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"],
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
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["Le Narrateur : {$gameRequest[3]}"],
    ],
);
