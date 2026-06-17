<?php

// Override some descriptions when in instruction mode

// We must use internal named keys here.

$GLOBALS["F_TRANSLATIONS_NEW"]["TravelTo"]="Travel long distance to a building, city, door or other location. Also known as lead the way.";
$GLOBALS["F_NAMES_NEW"]["TravelTo"] = function_exists('herikaNormalizeActionCatalogDisplayActionName')
    ? herikaNormalizeActionCatalogDisplayActionName("TravelTo")
    : "TravelTo";

foreach ($GLOBALS["FUNCTIONS"] as $n=>$f) {
    $internalCode=getFunctionByTrlName($f["name"]);
    if (isset($GLOBALS["F_TRANSLATIONS_NEW"][$internalCode]))
        $GLOBALS["FUNCTIONS"][$n]["description"]=$GLOBALS["F_TRANSLATIONS_NEW"][$internalCode];

    if (isset($GLOBALS["F_NAMES_NEW"][$internalCode]))
        $GLOBALS["FUNCTIONS"][$n]["name"]=$GLOBALS["F_NAMES_NEW"][$internalCode];


}

foreach ($GLOBALS["F_TRANSLATIONS_NEW"] as $k=>$v) 
    $GLOBALS["F_TRANSLATIONS"][$k]=$v;

foreach ($GLOBALS["F_NAMES_NEW"] as $k=>$v) 
    $GLOBALS["F_NAMES"][$k]=$v;


unsetFunction("ComeCloser");
unsetFunction("IncreaseWalkSpeed");
unsetFunction("DecreaseWalkSpeed");
//unsetFunction("Relax");



$GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=false;
$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="";

?>
