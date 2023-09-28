<?php

/***

Example of plugin.

External/3rd party functions should start with prefix ExtCmd or WebCmd
ExtCdm is for functions whose return value is provided by a Papyrus plugin.
WebCmd is for functions which return value is provided by server plugin itself

In this case, function code name will be ExtCmdHeal because we need a papyrus script to do the actual command and reports back result.

For Papyrus plugin developers:
Just need to bind to SPG_CommandReceived event.

Event OnInit()
	RegisterForModEvent("SPG_CommandReceived", "HerikaHealTarget")
EndEvent

Event HerikaHealTarget(String  command, String parameter)

	if (command=="ExtCmdHeal") ; This is my function
        
        ; parse parameters and do stuff
        
        ; Finally, send request of type funcrect with the result. THis will make a request to LLM again.
		SPGPapFunctions.requestMessage("command@"+command+"@"+parameter+"@"+herikaActor.GetDisplayName()+" heals "+player.GetDisplayName()+ " using the spell 'healing hands'","funcret");	// Pass return function to LLM
    
	endif
	
EndEvent


***/


// Name of the function. This is what will be offered to LLM. Can be overwrited by LANG. 

$GLOBALS["F_NAMES"]["ExtCmdHeal"]="Heal";                           

// Description. This is what will be offered to LLM. Can be overwrited by LANG.

$GLOBALS["F_TRANSLATIONS"]["ExtCmdHeal"]="Heals target using magic spell";

// $FUNCTION_PARM_INSPECT will contain an enum of visible NPC
// $FUNCTION_PARM_MOVETO will contain an enum of visible places to move 

// Function definition (OpenaAI style)

$GLOBALS["FUNCTIONS"][] =
    [
        "name" => $GLOBALS["F_NAMES"]["ExtCmdHeal"],
        "description" => $GLOBALS["F_TRANSLATIONS"]["ExtCmdHeal"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                    "enum" => $GLOBALS["FUNCTION_PARM_INSPECT"]

                ]
            ],
            "required" => ["target"],
        ],
    ]
;

// Add this function to enabled array
$GLOBALS["ENABLED_FUNCTIONS"][]="ExtCmdHeal";


// From here, is stuff that will be needed once the papyrus plugin make a request of type funcret.

// Stuff to manage the return value of the call function.
// Custom prompt. This will overwrite default cue. This is what we are requesting the LLM to do.
// TEMPLATE_DIALOG is degined in global prompts.php.

$GLOBALS["PROMPTS"]["afterfunc"]["cue"]["ExtCmdHeal"]="{$GLOBALS["HERIKA_NAME"]} comments about {$GLOBALS["PLAYER_NAME"]}'s wounds. {$GLOBALS["TEMPLATE_DIALOG"]}";


// If function is a server function (we need to calculate the result value in web server using php code)
// add a callable to FUNCSERV array
// We should execute our code here.
// This example does not require to do anything.

$GLOBALS["FUNCSERV"]["ExtCmdHeal"]=function() {
    global $gameRequest,$returnFunction,$db,$request;
    // Probably we want to execute something, and put return value in $returnFunction[3] and $gameRequest[3];
    // We could overwrite also $request. 
    
    
    
};

// When preparing function return data to LLM, maybe we will need to alter request. return array should only contain argName,request,useFunctionsAgain
// argName is mandatory, is the name of the parameter this function uses
// request is optional, if we need to rewrite request to LLM 
// useFunctionsAgain is optional, if we need to expose functions again to LLM

$GLOBALS["FUNCRET"]["ExtCmdHeal"]=function($gameRequest) {

    // Example, if papyrus execution gives some error, we will need to rewrite request her.
    // BY default, request will be $GLOBALS["PROMPTS"]["afterfunc"]["cue"]["ExtCmdHeal"]
    // $gameRequest = [type of message,localts,gamets,data]
    
    $GLOBALS["FORCE_MAX_TOKENS"]=48;    // We can overwrite anything here using $GLOBALS;
   
    if (stripos($gameRequest[3],"error")!==false) // Papyrus returned error
        return ["argName"=>"target","request"=>"{$GLOBALS["HERIKA_NAME"]} says sorry about unable to heal. {$GLOBALS["TEMPLATE_DIALOG"]}"];
    else
        return ["argName"=>"target"];
    
};


