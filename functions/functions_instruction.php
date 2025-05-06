<?php

// Override some descriptions when in instruction mode

// We must use translated named keys here.

$GLOBALS["F_TRANSLATIONS"]["LeadTheWayTo"]="Long distance travel command. Use it to move to major locations and landmarks.";

foreach ($GLOBALS["FUNCTIONS"] as $n=>$f) {
    $GLOBALS["FUNCTIONS"][$n]["description"]=$GLOBALS["F_TRANSLATIONS"][$f["name"]];


}

?>
