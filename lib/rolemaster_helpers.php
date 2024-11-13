<?php 

$AIQUEST_TEMPLATE=<<<EOI
You are a Skyrim quest creator. You can create quest using this tools

* Initialization data.

* Spawn Item (Spawn and item on a location, must describe item and give it a name)

 "spawnItem": {
        "item": {
          "name": "",
          "type": "sword|armor|helmet|ring|amulet|book|note|axe|long sword|staff|great axe|bow",
          "location": "nearby|major city",
          "description": ""
        }
      }
      
* Creates character (Spawn character on a location, must give it a name and a background and a speech style.
 Should be a NEW character name.  Pick class and race only from choices offered.

"createCharacter": {
        "character": {
          "name": "",
          "gender": "",
          "class":"beggar|warrior|assassin|mage|farmer|soldier|merchant|noble",
          "race": "Nord|Imperial|Argonian|RedGuard|Orc|Breton",
          "location": "location name|nearby",
          "appearance": "",
          "background": "",
          "speechStyle": "",
          "disposition": "defiant|submissive|friendly|serious|sad|aggressive|cheerful|distrustful|furious|drunk|high",
        }
      }

* Create Topic (a secret info a character must reveal to player)

"createTopic": {
        "topic": {
          "name": "",
          "type": "Lore|Item|Location",
          "item": ""
          "giver": "",
          "info": "",
          "target":"char_ref"
        }
      }

* Workflow definition

Issue actions to make the workflow of the quest. This actions will be stored on property "stages":

Some stages are branched. we use parent_stage property to specify if a stage is a branch and should be executed if parent branch ends successfully or not (fails)

# Example

"stages": [
        { "id": "1", "label": "SpawnCharacter", "char_ref": 1 },
        { "id": "2", "label": "MoveToPlayer", "char_ref": 1 ,"follow":true},
        { "id": "3", "label": "TellTopicToPlayer", "char_ref": 1, "topic_ref": 2 },
        { "id": "4", "label": "WaitForCoins", "char_ref": 1 },
        { "id": "5", "label": "TellTopicToPlayer", "char_ref": 1, "topic_ref": 3 ,"parent_stage":4,"branch":1},
        { "id": "6", "label": "CombatPlayer", "char_ref": 1 ,"parent_stage":4,"branch":2}
    ],

* SpawnCharacter (needs a char_ref)
* SpawnItem (needs a item_ref, and optionally a char_ref if we want the item be spawned in NPC inventory)
* MoveToPlayer (needs a char_ref) NPC will move to player. Set follow=true if NPC follows player
* TellTopicToPlayer (needs char_ref and topic_ref) NPC must talk to player about a topic. if after a while NPC doesn't talk about the subject, will fail.
* TellTopicToNPC (needs char_ref,topic_ref and destination_ref) NPC must talk to destination_ref NPC about a topic. if after a while NPC doesn't talk about the subject, will fail. 
* WaitToItemBeRecovered (needs item_ref) Pauses quest execution until player finds an item
* ToGoAway(needs a char_ref)
* CombatPlayer(needs a char_ref) Only use if NPC is hostile or got furious
* WaitForCoins(needs a char_ref and an amount) Pauses quest execution until player gives gold to an NPC
* WaitToItemBeTraded (needs item_ref) Pauses quest execution until player gives an item to an NPC
EOI;


function checkHistory($npc) {

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        return false;
    }
    
    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

    $historyData="";
    $lastPlace="";
    $lastListener="";
    $n=0;
    foreach (json_decode(DataSpeechJournal($npc,50),true) as $element) {
    
        if ($lastListener!=$element["listener"]) {
            if ($element["listener"]!="The Narrator")
                $listener=" (talking to {$element["listener"]})";
            $lastListener=$element["listener"];
        }
        else
            $listener="";

        if ($lastPlace!=$element["location"]){
            $place=" (at {$element["location"]})";
            $lastPlace=$element["location"];
        }
        else
            $place="";

        if (strpos($element["speaker"],$npc)!==false)  // Only NPC lines
            $n++;
    
    }

    return $n;
}

function askLLMForTopic($npc,$topic,$last_llm_call) {

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        return false;
    }
    if ((time()-$last_llm_call)<60) {
        error_log("Skipping askLLMForTopic: ".((time()-$last_llm_call)));
        return ["res"=>false,"missing"=>"skip"];
    }

    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

    $historyData="";
    $lastPlace="";
    $lastListener="";
    foreach (json_decode(DataSpeechJournal($npc,50),true) as $element) {
    
        if ($lastListener!=$element["listener"]) {
            if ($element["listener"]!="The Narrator")
                $listener=" (talking to {$element["listener"]})";
            $lastListener=$element["listener"];
        }
        else
            $listener="";

        if ($lastPlace!=$element["location"]){
            $place=" (at {$element["location"]})";
            $lastPlace=$element["location"];
        }
        else
            $place="";

        if (strpos($element["speaker"],$npc)!==false)  // Only NPC lines
            $historyData.=trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place").PHP_EOL;
    
    }

    $partyConf=DataGetCurrentPartyConf();
    $partyConfA=json_decode($partyConf,true);
    
    if (isset($partyConfA["{$npc}"])) {
        $charDesc=print_r($partyConfA["{$npc}"],true).PHP_EOL.$GLOBALS["HERIKA_PERS"];
        $currentProfile=$charDesc;
    } else
        $currentProfile=$GLOBALS["HERIKA_PERS"];

    $head[]   = ["role"	=> "system", "content"	=> "You are an assistant. You will analyze a dialogue and determine if a topic has been fully or partially covered. ", ];
    $prompt[] = ["role"	=> "user", "content"	=> "* Dialogue history:\n" .$historyData ];
    $prompt[] = ["role"=> "user", "content"	=> "is this topic fully or partially covered in the dialogue history? \"$topic\".\n". 
    "Answer yes, or give a score from 1/, (not covered) to 10 (fully covered), and then write a dialogue sentence as the speaker (hint) to provide the missing info. Use a JSON object to give a response {\"score\":[0-9],\"hint\":\"\"}"];
    $contextData       = array_merge($head, $prompt);

    print_r($contextData);
    $connectionHandler = new connector();

    $connectionHandler->open($contextData, ["max_tokens"=>500]);
    $buffer      = "";
    $totalBuffer = "";
    $breakFlag   = false;
    while (true) {
        
        if ($breakFlag) {
            break;
        }
        
        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }
        
        $buffer.= $connectionHandler->process();
        $totalBuffer.= $buffer;
        //$bugBuffer[]=$buffer;
        
        
    }
    $connectionHandler->close();

    $actions = $connectionHandler->processActions();


    $res=false;
    $originalBuffer=$buffer;
    $parsedbuffer=json_decode($buffer,true);

    if (is_array($parsedbuffer)) {
        $score=$parsedbuffer["score"];
        $hint=$parsedbuffer["hint"];
        if ($score>=6)
            $res=true;
        $buffer=$hint;

    } else {
        if (preg_match('/Score:\s*(\d+)\//i', $buffer, $matches)) {
            // Extracted score is in $matches[1]
            $score = $matches[1];
            echo "Extracted Score: " . $score.PHP_EOL;
        } else {
            echo "Score not found.".PHP_EOL;
        }
        if (strpos(strtoupper($buffer),"YES")===0)
        $res=true;
        if (strpos(strtoupper($buffer),"MOSTLY YES")===0)
            $res=true;
        if ($score>=6)
            $res=true;

        $buffer=strtr($buffer,["Partially"=>""]);
    }

    
    error_log($originalBuffer);
    
    //$res=true;
    return ["res"=>$res,"missing"=>$buffer];
    
}

function npcProfileBase($name,$class,$race,$gender,$location,$taskId) {

    /*
    SELECT STRING_AGG(formid,',') FROM "public"."npc_skyrim_data" where gender ilike 'male%' and race ilike 'nord%' and name='' and class ilike '%bandit%' and edid like 'Enc%' and achr='' and (not formid ilike '%0xDG%')  and (not  edid ilike '%magic%') 
    */
    $masterData=[

        "male_nord_beggar"=>[0x0003de8a,0x0003de6f,0x0003cf5d,0x00037c00,0x00039cfd,0x00037c2c,0x0003dee1,0x0003dee4,0x00039d01,0x0003de91,0x0003dea5,0x0003de56,0x00037c05,0x0003deea,0x0003deed,0x00039d09,0x0003de98,0x0003de74,0x0003de5b,0x00037c32,0x0003def5,0x0003def8,0x00039d11,0x0003dea0,0x0003de79,0x0003de60,0x00037c39,0x0003deff,0x0003df02,0x00039d19,0x0003deac,0x0003de7e,0x0003de65,0x00037c40,0x0003df09,0x0003df0c,0x00039d21,0x0003deb3,0x0003de83,0x0003de6a,0x00037c47,0x00073fbf],
        "male_nord_bard"=>[0x0003de8a,0x0003de6f,0x0003cf5d,0x00037c00,0x00039cfd,0x00037c2c,0x0003dee1,0x0003dee4,0x00039d01,0x0003de91,0x0003dea5,0x0003de56,0x00037c05,0x0003deea,0x0003deed,0x00039d09,0x0003de98,0x0003de74,0x0003de5b,0x00037c32,0x0003def5,0x0003def8,0x00039d11,0x0003dea0,0x0003de79,0x0003de60,0x00037c39,0x0003deff,0x0003df02,0x00039d19,0x0003deac,0x0003de7e,0x0003de65,0x00037c40,0x0003df09,0x0003df0c,0x00039d21,0x0003deb3,0x0003de83,0x0003de6a,0x00037c47,0x00073fbf],
        "male_nord_warrior"=>[0x0003de8a,0x0003de6f,0x0003cf5d,0x00039cfd,0x0003dee1,0x0003dee4,0x00039d01,0x0003de91,0x0003dea5,0x0003de56,0x0003deea,0x0003deed,0x00039d09,0x0003de98,0x0003de74,0x0003de5b,0x0003def5,0x0003def8,0x00039d11,0x0003dea0,0x0003de79,0x0003de60,0x0003deff,0x0003df02,0x00039d19,0x0003deac,0x0003de7e,0x0003de65,0x0003df09,0x0003df0c,0x00039d21,0x0003deb3,0x0003de83,0x0003de6a,0x00073fbf],
        "male_nord_rogue"=>[0x00037c00,0x00037c2c,0x00037c05,0x00037c32,0x00037c39,0x00037c40,0x00037c47],

        "female_imperial_bard"=>[0x0006d238,0x0006d241,0x0006d249,0x0006d252,0x0006d259,0x0006d261,0x000551b6,0x000551be,0x000551c6,0x000551ce,0x000551d6,0x000551de],
        "female_imperial_mage"=>[0x00044cea,0x00045c68,0x00045c83,0x00045ca9,0x00045cc6,0x00045ccf,0x00045c55,0x00045c6e,0x00045c89,0x00045caf,0x00045cd5,0x00045cdd,0x00045c5d,0x00045c74,0x00045c95,0x00045cb9,0x00045ce7,0x00045cef,0x00074f7a,0x00074f89,0x00074f7d,0x00074f8c,0x00074f82,0x00074f91],
        "female_imperial_noble"=>[0x00039cf7,0x0003de87,0x00037bfc,0x0003dede,0x00039cfe,0x0003de8e,0x00037c01,0x0003dee7,0x00039d06,0x0003de95,0x00037c2f,0x0003def2,0x00039d0e,0x0003de9d,0x00037c36,0x0003defc,0x00039d16,0x0003dea9,0x00037c3d,0x0003df06,0x00039d1e,0x0003deb0,0x00037c44,0x000bfb45],
        "female_imperial_merchant"=>[0x00039cf7,0x0003de87,0x00037bfc,0x0003dede,0x00039cfe,0x0003de8e,0x00037c01,0x0003dee7,0x00039d06,0x0003de95,0x00037c2f,0x0003def2,0x00039d0e,0x0003de9d,0x00037c36,0x0003defc,0x00039d16,0x0003dea9,0x00037c3d,0x0003df06,0x00039d1e,0x0003deb0,0x00037c44,0x000bfb45],
        "female_imperial_assassin"=>[0x00037bfc,0x00037c01,0x00037c2f,0x00037c36,0x00037c3d,0x00037c44],

        "male_imperial_merchant"=>[0x00039cf6,0x0003de88,0x00037bfe,0x0003dedf,0x0003def0,0x00039cff,0x0003de8f,0x00037c02,0x0003dee8,0x0003def1,0x00039d07,0x0003de96,0x00037c30,0x0003def3,0x0003defb,0x00039d0f,0x0003de9e,0x00037c37,0x0003defd,0x0003df05,0x00039d17,0x0003deaa,0x00037c3e,0x0003df07,0x0003df0f,0x00039d1f,0x0003deb1,0x00037c45,0x00073fbd],
        "male_imperial_soldier"=>[0x000f6f37,0x00041b30],

        "male_argonian_assassin"=>[0x00103512],
        "male_redguard_assassin"=>[0x00039cf9,0x0003de8d,0x0003de72,0x0003de54,0x0003dee3,0x0003dee6,0x00039d05,0x0003de94,0x0003dea8,0x0003de59,0x0003deec,0x0003deef,0x00039d0d,0x0003de9b,0x0003de77,0x0003de5e,0x0003def7,0x0003defa,0x00039d15,0x0003dea3,0x0003de7c,0x0003de63,0x0003df01,0x0003df04,0x00039d1d,0x0003deaf,0x0003de81,0x0003de68,0x0003df0b,0x0003df0e,0x00039d25,0x0003deb6,0x0003de86,0x0003de6d,0x00073fc0],

        "female_nord_noble"=>[0x00017167,0x00017168,0x00017169],
        "female_khajiit_assassin"=>[0x00103516],
        

        "male_breton_mage"=>[0x00039d33,0x00039d3a,0x00039d45,0x00039d4c,0x00039d53,0x00039d5a],
        "male_breton_merchant"=>[0x000f9616,0x00043bdd,0x00043bde,0x00043bdf,0x000ad7b4,0x00043be7,0x00043be8,0x00043be9,0x000ad7b5,0x00023aa9,0x00043be3,0x000442d7,0x000442d8,0x000442d9,0x000f9617,0x00044259,0x0004425a,0x0004425b,0x000ad7b6,0x0004425f,0x00044260,0x00044261,0x000ad7b7,0x000442dd,0x000442de,0x000442df,0x000f9618,0x0004426b,0x0004426c,0x0004426d,0x000ad7b8,0x00044271,0x00044272,0x00044273,0x000ad7b9,0x000442e3,0x000442e4,0x000442e5,0x000f9619,0x0004427d,0x0004427e,0x0004427f,0x000ad7bd,0x00044283,0x00044284,0x00044285,0x000ad7be,0x000442e9,0x000442ea,0x000442eb,0x000f961a,0x0004428f,0x00044290,0x00044291,0x000ad7bf,0x00044295,0x00044296,0x00044297,0x000ad7c0,0x000442ef,0x000442f0,0x000442f1,0x000f961b,0x000442a1,0x000442a2,0x000442a3,0x000ad7c2,0x000442a7,0x000442a8,0x000442a9,0x000ad7c3,0x00017145,0x00017146,0x0002e1dc,0x0002e1f1,0x0002e509,0x0002ea9b,0x0002eabe,0x0006d235,0x000e0fe5,0x0006d23c,0x000e0fe9,0x0006d244,0x000e0fed,0x0006d24c,0x000e0ff1,0x0006d254,0x000e0ff5,0x0006d25c,0x00044cda,0x00045c63,0x00045c7e,0x00045ca4,0x00045cc2,0x00045cc9,0x00045c52,0x00045c6b,0x00045c86,0x00045cac,0x00045cd2,0x00045cda,0x000551b1,0x000e1036,0x000551b9,0x000e103a,0x000551c1,0x000e103e,0x000551c9,0x000e1042,0x000551d1,0x000e1046,0x000551d9,0x00045c58,0x000e1052,0x00045c71,0x000e1056,0x00045c8e,0x000e105a,0x00045cb4,0x000e105e,0x00045ce2,0x000e1062,0x00045cea]

    ];

    $outfit=[
        "beggar"=>[0x000a1983],
        "mage"=>[0x0006e26f,0x001034ef,0x000a199c,0x000d504c,0x0007eab5,0x0001703a,0x000f3e7d,0x00106114,0x000fba59,0x000e9ac4,0x000b7a3e,0x000b7a3f],
        "barbarian"=>[0x00057a26],
        "warrior"=>[0x00028b44],
        "soldier"=>[0x00039f22],
        "assassin"=>[0x000e1ec2,0x0010350b,0x00065c53],
        "rogue"=>[0x000e1ec2,0x0010350b,0x00065c53],
        "farmer"=>[0x0002d75e],
        "citizen"=>[0x000a1983],
        "bard"=>[0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
        "noble"=>[0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
        "merchant"=>[0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718]
    ];

    $weapon=[
        "sword"=>[0x00013989]
    ];
    
    $locations=[
        "morthal"=>[0x000177b0]

    ];

    $parm1 = $masterData["{$gender}_{$race}_{$class}"][array_rand($masterData["{$gender}_{$race}_{$class}"])];
    $parm2=$outfit["{$class}"][array_rand($outfit["{$class}"])];

    //$parm3=$weapon["{$weapon}"][0];
    $parm3=$weapon["sword"][0];
    if ($location!="nearby")
        $parm4 = $locations[$location][array_rand($locations[$location])];
    else
        $parm4=0;
    
    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnCharacter@{$name}@$parm1@$parm2@$parm3@$parm4@$taskId",
            'tag' => ""
        )
    );

}




function CreateItem($basetype,$name,$location) {

    

    $masterData=[
        "potion"=>[0x0026921],
        "necklace"=>[0x9171b],
        "ring"=>[0x0003b97c],
    ];

    $masterDataLocations=[
        "helgen"=>[0x00055e4f],
        "morthal"=>[0x000177b0]
    ];


    $localItemName=$GLOBALS["db"]->escape($name);
    $localItemPlace=$GLOBALS["db"]->escape($location);
    
    $localItemType=$masterData[$basetype][array_rand($masterData[$basetype])];

    if ($localItemPlace=="nearby") {
        $localItemPlace=0;
    } else {

        $localItemPlace=$masterDataLocations[$location][array_rand($masterDataLocations[$location])];

    }

    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnItem@$localItemName@$localItemType@$localItemPlace@{$GLOBALS["taskId"]}",
            //'action' => "rolecommand|spawnItem@The Necklace of the Gods@necklace@Helgen@1",
            'tag' => ""
        )
    );

}


function CreateItemNpc($basetype,$name,$npc) {
    $masterData=[
        "potion"=>[0x0026921],
        "necklace"=>[0x000b8149]
    ];

    $localItemName=$GLOBALS["db"]->escape($name);
    $$localItemNPC=$GLOBALS["db"]->escape($npc);
    $localItemType=$masterData["type"][0];

    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnItemNPC@$localItemName@$localItemType@$localItemNPC@$taskId",
            //'action' => "rolecommand|spawnItem@The Necklace of the Gods@necklace@Helgen@1",
            'tag' => ""
        )
    );

}


function createQuestFromTemplate($template) {

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        return false;
    }

    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

    $head[]   = ["role"	=> "system", "content"	=> $GLOBALS["AIQUEST_TEMPLATE"]];
    $prompt[] = ["role"	=> "user", "content"	=> json_encode($template)];
    $prompt[] = ["role"=> "user", "content"	=> 
        "Change this quest's characters and topics but keep same stage structure. Characters must adhere to the definition of createCharacter. Output only JSON data"
    ];
    $contextData       = array_merge($head, $prompt);

    //print_r($contextData);
    $connectionHandler = new connector();
    $GLOBALS["FORCE_MAX_TOKENS"]=2048;
    $connectionHandler->open($contextData, ["MAX_TOKENS"=>2048]);
    $buffer      = "";
    $totalBuffer = "";
    $breakFlag   = false;
    while (true) {
        
        if ($breakFlag) {
            break;
        }
        
        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }
        
        $buffer.= $connectionHandler->process();
        $totalBuffer.= $buffer;
        //$bugBuffer[]=$buffer;
        
        
    }
    $connectionHandler->close();

    $originalBuffer=$buffer;
    $parsedbuffer=__jpd_decode_lazy($buffer);

    error_log($originalBuffer);

    if (is_array($parsedbuffer)) {
        return $parsedbuffer;

    } else
        return false;

    

    
    
}

?>