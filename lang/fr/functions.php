<?php

// Fonctionnalités à fournir à OpenAI
$ENABLED_FUNCTIONS=[
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
    'TakeGoldFromPlayer',
    'FollowPlayer'
//    'WaitHere'
];

$F_TRANSLATIONS["Inspect"]="Inspecte l'ÉQUIPEMENT et les VÊTEMENTS de la cible. RÉPONDEZ SIMPLEMENT quelque chose comme « Laissez-moi voir » et attendez";
$F_TRANSLATIONS["LookAt"]="REGARDE ou inspecte l'ÉQUIPEMENT et les VÊTEMENTS du PNJ, de l'Acteur ou de l'être";
$F_TRANSLATIONS["InspectSurroundings"]="Cherche des êtres ou des ennemis à proximité";
$F_TRANSLATIONS["MoveTo"]= "Marche vers un bâtiment visible ou un acteur visible, également utilisé pour guider {$GLOBALS["PLAYER_NAME"]} vers un acteur ou un bâtiment";
$F_TRANSLATIONS["OpenInventory"]="Commence un échange ou un commerce d'objets avec {$GLOBALS["PLAYER_NAME"]}";
$F_TRANSLATIONS["OpenInventory2"]="Commence un échange, {$GLOBALS["PLAYER_NAME"]} doit donner des objets à {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS["Attack"]="Attaque un acteur, un PNJ ou un être";
$F_TRANSLATIONS["AttackHunt"]="Tente de chasser/tuer un animal";
$F_TRANSLATIONS["Follow"]="Se déplace et suit un PNJ, acteur ou être";
$F_TRANSLATIONS["CheckInventory"]="Cherche dans l'inventaire, le sac ou la poche de {$GLOBALS["HERIKA_NAME"]}. Affiche l'inventaire";
$F_TRANSLATIONS["SheatheWeapon"]="Rengaine l'arme actuelle";
$F_TRANSLATIONS["Relax"]="Arrête de chercher des missions. Détends-toi et repose-toi";
$F_TRANSLATIONS["LeadTheWayTo"]="À utiliser seulement si {$GLOBALS["PLAYER_NAME"]} le demande explicitement. Guide {$GLOBALS["PLAYER_NAME"]} vers un village ou une ville";
$F_TRANSLATIONS["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} s'assoit sur une chaise ou un meuble proche";
$F_TRANSLATIONS["ReadQuestJournal"]="À utiliser seulement si {$GLOBALS["PLAYER_NAME"]} demande explicitement une mission. Récupère les informations sur les missions en cours";
$F_TRANSLATIONS["IncreaseWalkSpeed"]="Augmente la vitesse de déplacement de {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS["DecreaseWalkSpeed"]="Réduit la vitesse de déplacement de {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS["GetDateTime"]="Obtient la date et l'heure actuelles";
$F_TRANSLATIONS["SearchDiary"]="Lit le journal de {$GLOBALS["HERIKA_NAME"]} pour l'aider à se souvenir de quelque chose. Cherche dans l'index du journal";
$F_TRANSLATIONS["SetCurrentTask"]="Définit le plan ou la tâche ou mission en cours";
$F_TRANSLATIONS["StopWalk"]="Interrompt immédiatement toutes les actions de {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS["TravelTo"]="À utiliser seulement si {$GLOBALS["PLAYER_NAME"]} le demande explicitement. Guide {$GLOBALS["PLAYER_NAME"]} vers un village ou une ville";
$F_TRANSLATIONS["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tente de se souvenir de quelque chose. RÉPOND SIMPLEMENT par exemple « Laisse-moi réfléchir » et attends";
$F_TRANSLATIONS["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} attend et reste à l'endroit actuel";
$F_TRANSLATIONS["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} donne l'objet (propriété target) à {$GLOBALS["PLAYER_NAME"]} (propriété listener)";
$F_TRANSLATIONS["TakeGoldFromPlayer"]="{$GLOBALS["HERIKA_NAME"]} prend la somme (propriété target) d'or de {$GLOBALS["PLAYER_NAME"]} (propriété listener)";
$F_TRANSLATIONS["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} suit {$GLOBALS["PLAYER_NAME"]}";

$F_RETURNMESSAGES["Inspect"]="{$GLOBALS["HERIKA_NAME"]} inspecte #TARGET# et voit ceci : #RESULT#";
$F_RETURNMESSAGES["LookAt"]="REGARDE ou inspecte l'ÉQUIPEMENT et les VÊTEMENTS du PNJ, de l'Acteur ou de l'être";
$F_RETURNMESSAGES["InspectSurroundings"]="{$GLOBALS["HERIKA_NAME"]} regarde autour d'elle et voit ceci : #RESULT#";
$F_RETURNMESSAGES["MoveTo"]= "Marche vers un bâtiment ou un acteur visible, aussi utilisé pour guider {$GLOBALS["PLAYER_NAME"]} vers un acteur ou un bâtiment";
$F_RETURNMESSAGES["OpenInventory"]="Commence un échange ou un commerce d'objets avec {$GLOBALS["PLAYER_NAME"]}. Accepte le cadeau";
$F_RETURNMESSAGES["OpenInventory2"]="{$GLOBALS["PLAYER_NAME"]} donne des objets à {$GLOBALS["HERIKA_NAME"]}";
$F_RETURNMESSAGES["Attack"]="{$GLOBALS["HERIKA_NAME"]} attaque #TARGET#";
$F_RETURNMESSAGES["AttackHunt"]="{$GLOBALS["HERIKA_NAME"]} attaque #TARGET#";
$F_RETURNMESSAGES["Follow"]="Se déplace et suit un PNJ, acteur ou être";
$F_RETURNMESSAGES["CheckInventory"]="INVENTAIRE de {$GLOBALS["HERIKA_NAME"]} :#RESULT#";
$F_RETURNMESSAGES["SheatheWeapon"]="Rengaine l'arme actuelle";
$F_RETURNMESSAGES["Relax"]="{$GLOBALS["HERIKA_NAME"]} est détendue. Temps de profiter de la vie";
$F_RETURNMESSAGES["LeadTheWayTo"]="À utiliser seulement si {$GLOBALS["PLAYER_NAME"]} le demande explicitement. Guide {$GLOBALS["PLAYER_NAME"]} vers un village ou une ville";
$F_RETURNMESSAGES["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} s'assoit sur une chaise ou un meuble proche";
$F_RETURNMESSAGES["ReadQuestJournal"]="";
$F_RETURNMESSAGES["IncreaseWalkSpeed"]="Augmente la vitesse/le pas de {$GLOBALS["HERIKA_NAME"]}";
$F_RETURNMESSAGES["DecreaseWalkSpeed"]="Réduit la vitesse/le pas de {$GLOBALS["HERIKA_NAME"]}";
$F_RETURNMESSAGES["GetDateTime"]="Obtient la date et l'heure actuelles";
$F_RETURNMESSAGES["SearchDiary"]="Lit le journal de {$GLOBALS["HERIKA_NAME"]} pour l'aider à se souvenir de quelque chose. Cherche dans l'index du journal";
$F_RETURNMESSAGES["SetCurrentTask"]="Définit le plan ou la tâche ou mission en cours";
$F_RETURNMESSAGES["ReadDiaryPage"]="Lit le journal de {$GLOBALS["HERIKA_NAME"]} pour accéder à un sujet spécifique";
$F_RETURNMESSAGES["StopWalk"]="Interrompt immédiatement toutes les actions de {$GLOBALS["HERIKA_NAME"]}";
$F_RETURNMESSAGES["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} commence à voyager vers #TARGET#";
$F_RETURNMESSAGES["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tente de se souvenir de quelque chose. RÉPOND simplement quelque chose comme « Laisse-moi réfléchir » et attends";
$F_RETURNMESSAGES["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} attend et reste sur place";
$F_RETURNMESSAGES["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} a donné #TARGET# à {$GLOBALS["PLAYER_NAME"]}. Si c’est une transaction, il faudra peut-être utiliser TakeGoldFromPlayer";
$F_RETURNMESSAGES["TakeGoldFromPlayer"]="{$GLOBALS["PLAYER_NAME"]} a donné #TARGET# pièces à {$GLOBALS["HERIKA_NAME"]}. Si c’est une transaction, il faudra peut-être utiliser GiveItemToPlayer";
$F_RETURNMESSAGES["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} suit {$GLOBALS["PLAYER_NAME"]}";


// Qu'est-ce que c'est? Nous pouvons traduire les fonctions ou leur donner un nom personnalisé.
// Ce tableau gérera les traductions. Le plugin doit toujours recevoir le nom de code.

$F_NAMES["Inspect"]="Inspecter";
$F_NAMES["LookAt"]="Regarder";
$F_NAMES["InspectSurroundings"]="InspecterLesEnvirons";
$F_NAMES["MoveTo"]= "AllerA";
$F_NAMES["OpenInventory"]="OuvrirLInventaire";
$F_NAMES["OpenInventory2"]="PrendeObjetsduJoueur";
$F_NAMES["Attack"]="Ataquer";
$F_NAMES["AttackHunt"]="Chasser";
$F_NAMES["Follow"]="Suivre";
$F_NAMES["CheckInventory"]="ChequerInventaire";
$F_NAMES["SheatheWeapon"]="RengainerLarme";
$F_NAMES["Relax"]="SeDeteindre";
$F_NAMES["TakeASeat"]="SAsseoir";
$F_NAMES["ReadQuestJournal"]="LireJournaldeQuetes";
$F_NAMES["IncreaseWalkSpeed"]="MarcherPlusVite";
$F_NAMES["DecreaseWalkSpeed"]="MarcherMoinsVite";
$F_NAMES["GetDateTime"]="ObtenirHoraire";
$F_NAMES["SearchDiary"]="RechercherJournal";
$F_NAMES["SetCurrentTask"]="DefinirTacheActuelle";
$F_NAMES["ReadDiaryPage"]="LirePageJournal";
$F_NAMES["StopWalk"]="ArreterMarcer";
$F_NAMES["TravelTo"]="VoyagerA";
$F_NAMES["SearchMemory"]="SeSouvenir";
$F_NAMES["WaitHere"]="AtteindreIci";
$F_NAMES["GiveItemToPlayer"]="DonnerObjetJoueur";
$F_NAMES["TakeGoldFromPlayer"]="PrendreObjetduJoueur";
$F_NAMES["FollowPlayer"]="SuivreJoueur";

