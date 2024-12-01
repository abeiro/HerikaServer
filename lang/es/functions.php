<?php

// Funciones que se proporcionarán a OpenAI
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

$F_TRANSLATIONS["Inspect"]="Inspecciona el EQUIPO y VESTIMENTA del objetivo. SOLO RESPONDE algo como 'Déjame ver' y espera";
$F_TRANSLATIONS["LookAt"]="MIRA o Inspecciona el EQUIPO y VESTIMENTA del PNJ, Actor o ser";
$F_TRANSLATIONS["InspectSurroundings"]="Busca seres o enemigos cercanos";
$F_TRANSLATIONS["MoveTo"]= "Camina hacia un edificio visible o actor visible, también usado para guiar a {$GLOBALS["PLAYER_NAME"]} hacia un actor o edificio";
$F_TRANSLATIONS["OpenInventory"]="Inicia comercio o intercambio de objetos con {$GLOBALS["PLAYER_NAME"]}";
$F_TRANSLATIONS["OpenInventory2"]="Inicia comercio, {$GLOBALS["PLAYER_NAME"]} debe dar objetos a {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS["Attack"]="Ataca a un actor, PNJ o ser";
$F_TRANSLATIONS["AttackHunt"]="Intenta cazar/matar un animal";
$F_TRANSLATIONS["Follow"]="Se mueve y sigue a un PNJ, actor o ser";
$F_TRANSLATIONS["CheckInventory"]="Busca en el inventario, mochila o bolsillo de {$GLOBALS["HERIKA_NAME"]}. Lista el inventario";
$F_TRANSLATIONS["SheatheWeapon"]="Envaina el arma actual";
$F_TRANSLATIONS["Relax"]="Deja de buscar misiones. Relájate y descansa";
$F_TRANSLATIONS["LeadTheWayTo"]="Solo usar si {$GLOBALS["PLAYER_NAME"]} lo ordena explícitamente. Guía a {$GLOBALS["PLAYER_NAME"]} a un Pueblo o Ciudad";
$F_TRANSLATIONS["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} se sienta en una silla o mueble cercano";
$F_TRANSLATIONS["ReadQuestJournal"]="Solo usar si {$GLOBALS["PLAYER_NAME"]} pregunta explícitamente por una misión. Obtiene información sobre misiones actuales";
$F_TRANSLATIONS["IncreaseWalkSpeed"]="Aumenta la velocidad de {$GLOBALS["HERIKA_NAME"]} al moverse o viajar";
$F_TRANSLATIONS["DecreaseWalkSpeed"]="Disminuye la velocidad de {$GLOBALS["HERIKA_NAME"]} al moverse o viajar";
$F_TRANSLATIONS["GetDateTime"]="Obtiene la Fecha y Hora Actual";
$F_TRANSLATIONS["SearchDiary"]="Lee el diario de {$GLOBALS["HERIKA_NAME"]} para hacerle recordar algo. Busca en el índice del diario";
$F_TRANSLATIONS["SetCurrentTask"]="Establece el plan actual de acción o tarea o misión";
$F_TRANSLATIONS["StopWalk"]="Detiene todas las acciones de {$GLOBALS["HERIKA_NAME"]} inmediatamente";
$F_TRANSLATIONS["TravelTo"]="Solo usar si {$GLOBALS["PLAYER_NAME"]} lo ordena explícitamente. Guía a {$GLOBALS["PLAYER_NAME"]} a un Pueblo o Ciudad";
$F_TRANSLATIONS["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} intenta recordar información. RESPONDE con hashtags";
$F_TRANSLATIONS["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} espera y se mantiene en el lugar actual";
$F_TRANSLATIONS["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} da el objeto (propiedad target) a {$GLOBALS["PLAYER_NAME"]} (propiedad listener)";
$F_TRANSLATIONS["TakeGoldFromPlayer"]="{$GLOBALS["HERIKA_NAME"]} toma la cantidad (propiedad target) de oro de {$GLOBALS["PLAYER_NAME"]} (propiedad listener)";
$F_TRANSLATIONS["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} sigue a {$GLOBALS["PLAYER_NAME"]}";

$F_RETURNMESSAGES["Inspect"]="{$GLOBALS["HERIKA_NAME"]} inspecciona a #TARGET# y ve esto: #RESULT#";
$F_RETURNMESSAGES["LookAt"]="MIRA o Inspecciona el EQUIPO y VESTIMENTA del PNJ, Actor o ser";
$F_RETURNMESSAGES["InspectSurroundings"]="{$GLOBALS["HERIKA_NAME"]} mira alrededor y ve esto: #RESULT#";
$F_RETURNMESSAGES["MoveTo"]= "Camina hacia un edificio visible o actor visible, también usado para guiar a {$GLOBALS["PLAYER_NAME"]} hacia un actor o edificio";
$F_RETURNMESSAGES["OpenInventory"]="Inicia comercio o intercambio de objetos con {$GLOBALS["PLAYER_NAME"]}. Acepta regalo";
$F_RETURNMESSAGES["OpenInventory2"]="{$GLOBALS["PLAYER_NAME"]} da objetos a {$GLOBALS["HERIKA_NAME"]}";
$F_RETURNMESSAGES["Attack"]="{$GLOBALS["HERIKA_NAME"]} Ataca a #TARGET#";
$F_RETURNMESSAGES["AttackHunt"]="{$GLOBALS["HERIKA_NAME"]} Ataca a #TARGET#";
$F_RETURNMESSAGES["Follow"]="Se mueve y sigue a un PNJ, actor o ser";
$F_RETURNMESSAGES["CheckInventory"]="INVENTARIO de {$GLOBALS["HERIKA_NAME"]}:#RESULT#";
$F_RETURNMESSAGES["SheatheWeapon"]="Envaina el arma actual";
$F_RETURNMESSAGES["Relax"]="{$GLOBALS["HERIKA_NAME"]} está relajado. Tiempo de disfrutar la vida";
$F_RETURNMESSAGES["LeadTheWayTo"]="Solo usar si {$GLOBALS["PLAYER_NAME"]} lo ordena explícitamente. Guía a {$GLOBALS["PLAYER_NAME"]} a un Pueblo o Ciudad";
$F_RETURNMESSAGES["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} se sienta en una silla o mueble cercano";
$F_RETURNMESSAGES["ReadQuestJournal"]="";
$F_RETURNMESSAGES["IncreaseWalkSpeed"]="Aumenta la velocidad/paso de {$GLOBALS["HERIKA_NAME"]} al moverse o viajar";
$F_RETURNMESSAGES["DecreaseWalkSpeed"]="Disminuye la velocidad/paso de {$GLOBALS["HERIKA_NAME"]} al moverse o viajar";
$F_RETURNMESSAGES["GetDateTime"]="Obtiene la Fecha y Hora Actual";
$F_RETURNMESSAGES["SearchDiary"]="Lee el diario de {$GLOBALS["HERIKA_NAME"]} para hacerle recordar algo. Busca en el índice del diario";
$F_RETURNMESSAGES["SetCurrentTask"]="Establece el plan actual de acción o tarea o misión";
$F_RETURNMESSAGES["ReadDiaryPage"]="Lee el diario de {$GLOBALS["HERIKA_NAME"]} para acceder a un tema específico";
$F_RETURNMESSAGES["StopWalk"]="Detiene todas las acciones de {$GLOBALS["HERIKA_NAME"]} inmediatamente";
$F_RETURNMESSAGES["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} comienza a viajar hacia #TARGET#";
$F_RETURNMESSAGES["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} intenta recordar información. SOLO RESPONDE algo como 'Déjame pensar' y espera";
$F_RETURNMESSAGES["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} espera y se mantiene en el lugar";
$F_RETURNMESSAGES["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} dio #TARGET# a {$GLOBALS["PLAYER_NAME"]}. Si esto es una transacción, tal vez se necesite TakeGoldFromPlayer";
$F_RETURNMESSAGES["TakeGoldFromPlayer"]="{$GLOBALS["PLAYER_NAME"]} dio #TARGET# monedas a {$GLOBALS["HERIKA_NAME"]}. Si esto es una transacción, tal vez se necesite GiveItemToPlayer";
$F_RETURNMESSAGES["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} sigue a {$GLOBALS["PLAYER_NAME"]}";

// ¿Qué es esto? Podemos traducir funciones o darles un nombre personalizado.
// Este array manejará las traducciones. El plugin debe recibir siempre el nombre código.

$F_NAMES["Inspect"]="Inspeccionar";
$F_NAMES["LookAt"]="MirarA";
$F_NAMES["InspectSurroundings"]="InspeccionarAlrededores";
$F_NAMES["MoveTo"]= "MoverseA";
$F_NAMES["OpenInventory"]="IntercambiarObjetos";
$F_NAMES["OpenInventory2"]="TomarObjetosDelJugador";
$F_NAMES["Attack"]="Atacar";
$F_NAMES["AttackHunt"]="Cazar";
$F_NAMES["Follow"]="Seguir";
$F_NAMES["CheckInventory"]="ListarInventario";
$F_NAMES["SheatheWeapon"]="EnvainarArma";
$F_NAMES["Relax"]="Relajarse";
$F_NAMES["TakeASeat"]="Sentarse";
$F_NAMES["ReadQuestJournal"]="LeerDiarioMisiones";
$F_NAMES["IncreaseWalkSpeed"]="AumentarVelocidad";
$F_NAMES["DecreaseWalkSpeed"]="DisminuirVelocidad";
$F_NAMES["GetDateTime"]="ObtenerFechaHora";
$F_NAMES["SearchDiary"]="BuscarEnDiario";
$F_NAMES["SetCurrentTask"]="EstablecerTareaActual";
$F_NAMES["ReadDiaryPage"]="LeerPaginaDiario";
$F_NAMES["StopWalk"]="DetenerCaminata";
$F_NAMES["TravelTo"]="GuiarHacia";
$F_NAMES["SearchMemory"]="IntentarRecordar";
$F_NAMES["WaitHere"]="EsperarAqui";
$F_NAMES["GiveItemToPlayer"]="DarObjetoAJugador";
$F_NAMES["TakeGoldFromPlayer"]="TomarOroDeJugador";
$F_NAMES["FollowPlayer"]="SeguirJugador";

