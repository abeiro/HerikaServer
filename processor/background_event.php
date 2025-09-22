<?php 
$currentConnectorData=$GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"];
$connector=new LLMConnector();
$connectionHandler = $connector->getConnector($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]);
$sqlfilter=" and type<>'prechat' "; // Will dismiss prechat entries by default. prechat are LLM responses still not displayed in-game

error_log("[BACKGROUND EVENT] Using  model: {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
$contextDataHistoric = DataLastDataExpandedFor("{$GLOBALS["HERIKA_NAME"]}", 20 * -1,$sqlfilter);
$minimalContext=[];
        foreach ($contextDataHistoric as $ele) {
            if (strpos($ele["content"],"#MEMORY")===false) {
                $minimalContext[]="{$ele["content"]}";
            }
        }

$locaContextData=[
            array('role' => 'system', 'content' => "
You are a narrative generator for NPCs in a role-playing game (Skyrim).  
The NPCs sometimes leave the player's view to perform background tasks.  
Your job is to create a short, immersive description of what the {$GLOBALS["HERIKA_NAME"]} did while off-screen, based on the last dialogue or intention they expressed before leaving.  

### Instructions:
- Read the {$GLOBALS["HERIKA_NAME"]}’s last dialogue or stated goal.  
- Imagine what they realistically could have done during their absence.  
- Write a concise but vivid description (2–4 sentences) of the outcome of their actions.  
- Keep the style consistent with fantasy RPG storytelling.  
- Avoid inventing things unrelated to their stated intentions.  
- The description should feel like something the player could later hear as gossip, rumor, or a casual report.  
- Last line should be the {$GLOBALS["HERIKA_NAME"]} returning to its origin place.

### Input (example):
NPC last dialogue: \"I’ll head to the forge and see if I can mend my sword before nightfall.\"  

### Output (example):
\"During his time away, the blacksmith spent hours by the forge, hammering sparks into the air until the blade gleamed anew. By dusk, he returned with a sword sharpened and tempered, though his hands bore the marks of a long day’s toil. Finally, he went back to here it came from.\"

"),
            array('role' => 'user', 'content' => "# Historic context information:\n".implode("\n",$minimalContext)),
            array('role' => 'user', 'content' => "Generate a short, immersive description of what the {$GLOBALS["HERIKA_NAME"]} did while off-screen"),
        ];
$buffer=$connectionHandler->fast_request($locaContextData,$overrideParameters);
$gameRequest[0]="infaction";
$gameRequest[3]=$buffer;

logEvent($gameRequest,$GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor

error_log("[BACKGROUND EVENT] $buffer");
terminate();
?>