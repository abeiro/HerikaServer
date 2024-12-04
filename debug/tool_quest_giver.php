<?php 


define("SECOND_GAMETS_MULT",2000);  // Timestamp multiplier

$TALK_SPEED=1;

$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."rolemaster_helpers.php");

$db=new sql();
$MUST_END=false;
$UPDATE_PROFILE=false;
// Currently supporting only one quest at a time
$runningQuest=$db->fetchAll("SELECT * FROM aiquest where status=1  ORDER BY updated LIMIT 1 OFFSET 0");

// Instantitate new quest
if (isset($argv[1])&&$argv[1]=="create") {
    $newRunningQuest=$db->fetchAll("SELECT * FROM aiquests_template where enabled>=1 ORDER BY enabled desc,RANDOM() LIMIT 1 OFFSET 0");
    $taskId=uniqid();
    $quest=json_decode($newRunningQuest[0]["data"],true);

    $db->insert(
        'aiquest',
        array(
            'definition' => $newRunningQuest[0]["data"],
            'updated' => time(),
            'status' => 1,
            'taskid' => $taskId

        )
    );
    die("Quest created");
} else if (isset($argv[1])&&$argv[1]=="template_summary") {
    $templates=$db->fetchAll("SELECT * FROM aiquests_template" );
    foreach ($templates as $template) {
        $tmpl=json_decode($template["data"],true);
        echo "Quest: {$tmpl["quest"]}.\nOverview: {$tmpl["overview"]}\n.Stages: ".json_encode($tmpl["stages"]).PHP_EOL.PHP_EOL;
    }
    
    die("");
}  else  if (isset($argv[1])&&$argv[1]=="delete") {
    $db->delete("aiquest","status=1");
    die("Delete running quests");
}  else if (isset($runningQuest[0])) {
    $quest=json_decode($runningQuest[0]["definition"],true);
    $taskId=$runningQuest[0]["taskid"];
} else {
    die("No running quests");
}


$characters=[];
$items=[];
$topics=[];
//print_r($quest);



// Fake request

$latsRid=$db->fetchAll("select *  from eventlog order by rowid desc LIMIT 1 OFFSET 0");
$res=$db->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts,max(localts)+1 as localts from eventlog where rowid={$latsRid[0]["rowid"]}");
$GLOBALS["gameRequest"][1]=$res[0]["ts"]+0;
$GLOBALS["gameRequest"][2]=$res[0]["gamets"]+0;
$GLOBALS["gameRequest"][0]="";
$GLOBALS["last_localts"]=$res[0]["localts"]+0;
$GLOBALS["last_gamets"]=$res[0]["gamets"]+0;
$GLOBALS["actors_present"]=DataBeingsInCloseRange();

// Create a initital timestamp
if (!isset($quest["start_ts"])) {
    $quest["start_ts"]=time();
    $quest["start_gamets"]=$GLOBALS["last_gamets"];

}

// Spawn required items
foreach ($quest["initial_data"] as $n=>$step) {
    $command=key($step);
    if ($command=="createCharacter") {
        
        $quest["initial_data"][$n]["taskid"]=$taskId;

        $characters[$step["id"]]=$step[$command]["character"];
        if ($characters[$step["id"]]["disposition"]=="drunk") {
            $TALK_SPEED=2;
        }
        if ($characters[$step["id"]]["disposition"]=="high") {
            $TALK_SPEED=0.75;
        } else {
            $TALK_SPEED=0.85;
        }

    } else  if (($command=="spawnItem")||( ($command=="createItem"))) {
        //echo "* $command ".PHP_EOL;
        //echo "spawnItem(\"{$step[$command]["item"]["name"]}\",\"{$step[$command]["item"]["type"]}\",\"{$step[$command]["item"]["location"]}\")".PHP_EOL;
        $items[$step["id"]]=$step[$command]["item"];
        if ((!$quest["initial_data_done"])||true) {
            
            if (!isset($items[$step["id"]]["char_ref"])) { // If item is on inventory, should create it when spawning character
                if (!isset($quest["items"][$step["id"]]["status"])) {
                    CreateItem($items[$step["id"]]["type"],$items[$step["id"]]["name"],strtolower($items[$step["id"]]["location"]),$items[$step["id"]]["description"]);
                    $quest["items"][$step["id"]]["status"]="sent";
                    $quest["items"][$step["id"]]["data"]=$items[$step["id"]];
                    $MUST_END=true;
                    break;
                } else {
                    $cn=$GLOBALS["db"]->escape($quest["items"][$step["id"]]["data"]["name"]);
                    $spawned=$GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='status_msg' and data like '%spawned%@$cn%success%' and localts>={$quest["start_ts"]}");
                    if (is_array($spawned)&& ($spawned[0]["n"]>0)) {
                        $quest["items"][$step["id"]]["status"]="spawned";
                    } else {
                        error_log("Item $cn still not spawned");
                        $MUST_END=true;
                        break;
                    }

                }
            } else {
                $delayedItems[$items[$step["id"]]["char_ref"]][]=$items[$step["id"]];

            }
        }
       
        

    } else  if ($command=="createTopic") {
        //echo "* $command ".PHP_EOL;
        //echo "createTopic(\"{$step[$command]["topic"]["name"]}\",\"{$step[$command]["topic"]["giver"]}\")".PHP_EOL;
        if (!isset($firstTopic)) {
            $step[$command]["topic"]["first_one"]=true;
            $firstTopic=true;
        }

        $topics[$step["id"]]=$step[$command]["topic"];
    }
}

$quest["initial_data_done"]=true;
//print_r($topics);
// Check if required items have been spawned
if (isset($quest["items"])) {
    foreach ($quest["items"] as $n=>$item) {
        // Check if item has spawned
        if ($item["status"]=="sent") {
            $cn=$GLOBALS["db"]->escape($item["data"]["name"]);

            $spawned=$GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='status_msg' and data like '%spawned%@$cn%error%' and localts>={$quest["start_ts"]}");
            if (is_array($spawned)&& ($spawned[0]["n"]>0)) {
                error_log("Items could not be spawned. MUST CANCEL NOW");
                $MUST_END=true;
            } 
        }

    }
}

// Formula to calculate wait times to check topics via LLM. Depends on number of topics
$N_TOPIC_ELEMENTS=(sizeof($topics)+10)/32;

// Check if all stages done

$allDone=true;
foreach ($quest["stages"] as $stage) {
    if (isset($stage["status"]))
        $allDone=$allDone&($stage["status"]>=2);
    else {
        $allDone=false;
        break;
    }

}

$failed=false;
if (($quest["start_gamets"]-$GLOBALS["last_gamets"])>15) {
    $allDone=true;
    $failed=true;
    echo "Time went backwards. Game reloaded?".PHP_EOL;
    $db->updateRow(
        'aiquest',
        array(
            'definition' => json_encode($quest),
            'updated' => time(),
            'status' => ($allDone)?2:1,
            'taskid' => $taskId
    
        ),
        "taskid='$taskId'"
    );
} else
    echo "{$quest["start_gamets"]}>{$GLOBALS["last_gamets"]}".PHP_EOL;

if ($allDone) {
    die("Quest completed!");
}


// Silence detector

if ((isset($quest["GLOBAL_LAST_LLM_CALL"])&&$quest["GLOBAL_LAST_LLM_CALL"]!=0)&&(time()-$quest["GLOBAL_LAST_LLM_CALL"]>15)) {
    $lastChat=$db->fetchAll("select max(localts) as m from speech");
    $lastEvent=$db->fetchAll("select max(localts) as n from eventlog ");
    if (($lastEvent[0]["n"]-$lastChat[0]["m"])>20) {  // 20 seconds of silence
        $quest["GLOBAL_LAST_LLM_CALL"]=0;
        $N_TOPIC_ELEMENTS=0;
        error_log("Silence detected {$lastEvent[0]["n"]}-{$lastChat[0]["m"]}");
    } else 
        error_log("Last talk {$lastEvent[0]["n"]}-{$lastChat[0]["m"]}\t".($lastEvent[0]["n"]-$lastChat[0]["m"])." secs");
}

// Parse current instantiated quest
if (!$MUST_END) {
    foreach ($quest["stages"] as $n=>$stage) {

        // Check here if character still alive.
        // If so, mark all status 0 stages depending on this char as done.

        if (isset($stage["char_ref"])) {
            $character=$characters[$stage["char_ref"]]["name"];
            $cn=$db->escape($character);
            $moved=$db->fetchAll("select count(*) as n from eventlog where type='infonpc' and data like '%{$cn}(dead)%'");
            //error_log("select count(*) as n from eventlog where type='infonpc' and data like '%{$cn}(dead)%'");
            if (is_array($moved)&& ($moved[0]["n"]>0)) {
                $quest["stages"][$n]["status"]=5;
                error_log($quest["stages"][$n]["label"]." skipped because NPC is dead");
                continue;

            }
        }

        // Branch control

        if (isset($stage["parent_stage"])) {
            if ($quest["stages"][$stage["parent_stage"]-1]["status"]==2)    // Parent stage ended ok status=2 is ok, status>2 is failed
                $localbranch=1;
            else
                $localbranch=2;    
        }

        // First stage, First run
        if ($n==0) {
            if (!isset($stage["status"])) {
                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|StartQuest@{$quest["quest"]}@$taskId",
                        'tag' => ""
                    )
                );
            }


        }

        if ($stage["label"]=="SpawnCharacter") {
            $character=$characters[$stage["char_ref"]];
            
            if (!isset($stage["status"])) {
                
                echo "spawnCharacter(\"{$character["name"]}\",\"{$character["gender"]}\",\"{$character["race"]}\",\"$taskId\")".PHP_EOL;
                $cn_gender=strtolower($character["gender"]);
                $cn_race=strtolower($character["race"]);
                $cn_location=strtolower($character["location"]);

                $pclass=$character["class"];

                // This will spawn character
                npcProfileBase($character["name"],$pclass,$cn_race,$cn_gender,$cn_location,$taskId);
                
                $namedKey="{$character["name"]}_is_rolemastered";
                $db->delete("conf_opts", "id='".$db->escape($namedKey)."'");
                $db->insert(
                    'conf_opts',
                    array(
                        'id' => $namedKey,
                        'value' => true
                    )
                );

                error_log("DONE 2");
                $quest["stages"][$n]["status"]=1;
                break;

            } else if ($stage["status"]==1){

                $cn=$db->escape($character["name"]);
                echo "Check if character $cn {$stage["char_ref"]} has spawned ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $spawned=$db->fetchAll("select 1 as n,data from eventlog where type='status_msg' and data like '%spawned@$cn%' order by localts desc");
                if (is_array($spawned)&& isset($spawned[0]) && ($spawned[0]["n"]>0)) {
                    echo "Character has spawned!".PHP_EOL;
                    $quest["stages"][$n]["status"]=2;
                    

                    $rowData=explode("@",$spawned[0]["data"]);
                    $formId=$rowData[2];
                    $quest["stages"][$n]["formid"]=$formId;
                    echo "spawnCharacter(\"{$character["name"]}\",\"{$character["gender"]}\",\"{$character["race"]}\",\"$taskId\")".PHP_EOL;
                    $cn_gender=strtolower($character["gender"]);
                    $cn_race=strtolower($character["race"]);

                    $PARMS["HERIKA_PERS"]="Roleplay as {$character["name"]} ({$character["race"]} {$character["gender"]})\n".
                    "{$character["appearance"]}\n".
                    "{$character["background"]}\n".
                    "#SpeechStyle\n{$character["speechStyle"]}\n";

                    //$PARMS["EMOTEMOODS"]="drunk";

                    $ntopic=0;

                    foreach ($topics as $topic) {
                        if ($topic["giver"]==$character["name"]) {
                            if ($ntopic==0) {
                                $PARMS["HERIKA_PERS"].="\nGenerate content based on the following contextual topics, but do not mention them directly or reveal any details
                                 about them. Use the contextual topics only to ensure the generated content remains consistent and does not contradict future information. Avoid spoilers at all times.\n
                                *{$topic["info"]}\n";
                                $ntopic++;
                            } else {
                                $PARMS["HERIKA_PERS"].="*{$topic["info"]}\n";
                            }
                        }
                    }
                    
                    $pclass=$character["class"];
                    
                    $PARMS["RECHAT_H"]=(sizeof($GLOBALS["characters"])*2)+1;// % for one character

                    if (in_array($character["disposition"],["drunk"])) {
                        $PARMS["EMOTEMOODS"]="drunk";
                    } else if (in_array($character["disposition"],["high"])) {
                        $PARMS["EMOTEMOODS"]="high";
                    }

                    

                    createProfile($character["name"],$PARMS,true);

                    // Dependant items:
                    if (isset($delayedItems[$stage["char_ref"]])) {
                        foreach ($delayedItems[$stage["char_ref"]] as $step_id=>$localItem) {
                            
                            CreateItem($localItem["type"],$localItem["name"],$formId,$localItem["description"]);
                            $quest["items"][$step_id]["status"]="sent";
                            $quest["items"][$step_id]["data"]=$localItem;
                            
                            break;
                            
                        }

                    }

                }
                break;
            }
        }

        if ($stage["label"]=="MoveToPlayer") {
            if (!isset($stage["status"])) {
                $character=$characters[$stage["char_ref"]];
                echo "MoveToPlayer(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;

                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|moveToPlayer@{$character["name"]}@$taskId",
                        'tag' => ""
                    )
                );
                $quest["stages"][$n]["status"]=1;
                $quest["stages"][$n]["status_gamets"]=$GLOBALS["last_gamets"];
                break;

            } else if ($stage["status"]==1){
                $character=$characters[$stage["char_ref"]];
                $cn=$db->escape($character["name"]);
                echo "Check if character {$stage["char_ref"]} has reached player ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $moved=$db->fetchAll("select count(*) as n from eventlog where type='status_msg' and data like '%reached_destination_player@{$cn}%'");// Add timestamp check here
                
                if (is_array($moved)&& ($moved[0]["n"]>0)) {
                    echo "Character has moved!".PHP_EOL;
                    $quest["stages"][$n]["status"]=2;

                    $db->delete("eventlog", "  type='status_msg' and data like '%reached_destination_player@{$cn}%' ");

                    if (isset($stage["follow"])&&($stage["follow"])) {
                        $follow=1;
                    } else 
                        $follow=0;

                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|stayAtPlace@{$character["name"]}@$follow@$taskId",
                            'tag' => ""
                        )
                    );
                    
                } else { 
                    
                    // Early detection
                    echo "Actors present: {$GLOBALS["actors_present"]} vs {$character["name"]}".PHP_EOL;
                    if (strpos($GLOBALS["actors_present"],$character["name"])!==false) {
                        // Actor present, if next stage is TellTopicToPlyer, call for attention
                        if ((isset($quest["stages"][$n+1])) && ($quest["stages"][$n+1]["label"]=="TellTopicToPlayer")) {
                            
                            if (!isset($quest["stages"][$n]["early_talk"])) {
                                echo "Actor aproaching, call for attention";
                                $quest["stages"][$n]["early_talk"]=true;
                                $db->insert(
                                    'responselog',
                                    array(
                                        'localts' => time(),
                                        'sent' => 0,
                                        'actor' => "rolemaster",
                                        'text' => "",
                                        'action' => make_replacements("rolecommand|Instruction@{$character["name"]}@{$character["name"]} greets/brawls #PLAYER#@$taskId"),
                                        'tag' => ""
                                    )
                                );

                            }

                        }
                    }
                    
                    // Send again after a while.
                    if (($GLOBALS["last_gamets"]-$quest["stages"][$n]["status_gamets"]) > 300 * SECOND_GAMETS_MULT * 10000)  {
                        $quest["stages"][$n]["status_gamets"]=$GLOBALS["last_gamets"];
                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|moveToPlayer@{$character["name"]}@$taskId",
                                'tag' => ""
                            )
                        );
                    }


                }

                // Run next instruction if moveToPlayer too
                if ((isset($quest["stages"][$n+1])) && ($quest["stages"][$n+1]["label"]=="MoveToPlayer") && ($quest["stages"][$n+1]["char_ref"]!=$quest["stages"][$n]["char_ref"]) ) {   
                    

                    if ($quest["stages"][$n+1]["status"]<1) {
                        $nextStage=$quest["stages"][$n+1];
                        $character=$characters[$nextStage["char_ref"]];
                        echo "MoveToPlayer(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;

                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => $db->escape("rolecommand|moveToPlayer@{$character["name"]}@$taskId"),
                                'tag' => ""
                            )
                        );
                        $quest["stages"][$n+1]["status"]=1;
                    }
                }

                break;
            }

        }

        if ($stage["label"]=="ToGoAway") {

            if (isset($stage["parent_stage"])) {
                error_log("Using branch {$localbranch} / {$stage["branch"]}");
                if ($localbranch!=$stage["branch"]) {
                    $quest["stages"][$n]["status"]=5;
                    error_log($quest["stages"][$n]["label"]." skipped");
                    continue;
                }
            }

            $character=$characters[$stage["char_ref"]];

            if (!isset($stage["status"])) {
                
                echo "TravelTo(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;

                if (sizeof($characters)>1) {
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => $db->escape("rolecommand|TravelTo@{$character["name"]}@WIDeadBodyCleanupCell@$taskId"),
                            'tag' => ""
                        )
                    );
                
                } else {
                
                    // Don't do it inmediately
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|Sandbox@{$character["name"]}@$taskId",
                            'tag' => ""
                        )
                    );
                }

                $quest["stages"][$n]["status"]=1;
                $quest["stages"][$n]["last_send_gamets"]=$GLOBALS["last_gamets"];
                break;

            } else if ($stage["status"]==1){
                $cn=$db->escape($character["name"]);
                echo "Check if character {$stage["char_ref"]} has reached destination ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $moved=$db->fetchAll("select count(*)  as n from (select * from eventlog where type='infonpc_close' order by rowid desc limit 1) where data ilike '%{$cn}%'");
                //error_log("select count(*)  as n from (select * from eventlog where type='infonpc_close' order by rowid desc limit 1) where people ilike '%{$cn}%'");
                if (is_array($moved)&& ($moved[0]["n"]==0)) {
                    echo "Character has reached destination!".PHP_EOL;
                    $quest["stages"][$n]["status"]=2;
                
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => $db->escape("rolecommand|Despawn@$cn@$taskId"),
                            'tag' => ""
                        )
                    );
                } else {
                    
                    if ($GLOBALS["last_gamets"]-$quest["stages"][$n]["last_send_gamets"]> 30 * SECOND_GAMETS_MULT) {
                        echo "Retrying ToGoAway";
                        $quest["stages"][$n]["last_send_gamets"]=$GLOBALS["last_gamets"];
                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => $db->escape("rolecommand|TravelTo@{$character["name"]}@WIDeadBodyCleanupCell@$taskId"),
                                'tag' => ""
                            )
                        );

                    }


                }
                // If next instructions is ToGoAway too. activate stage
                if ((isset($quest["stages"][$n+1])) && ($quest["stages"][$n+1]["label"]=="ToGoAway") && ($quest["stages"][$n+1]["char_ref"]!=$quest["stages"][$n]["char_ref"])) {   // Run next instruction if moveToPlayer too

                    if ($quest["stages"][$n+1]["status"]<1) {
                        $nextStage=$quest["stages"][$n+1];
                        $character=$characters[$nextStage["char_ref"]];
                        echo "MoveToPlayer(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;

                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => $db->escape("rolecommand|TravelTo@{$character["name"]}@WIDeadBodyCleanupCell@$taskId"),
                                'tag' => ""
                            )
                        );
                        $quest["stages"][$n+1]["status"]=1;
                    }
                }
                break;
            }

        }

        if ($stage["label"]=="CombatPlayer") {

            if (isset($stage["parent_stage"])) {
                error_log("{$localbranch} vs {$stage["branch"]}");
                if ($localbranch!=$stage["branch"]) {
                    $quest["stages"][$n]["status"]=5;
                    error_log($quest["stages"][$n]["label"]." skipped");
                    continue;
                }
            }

            $character=$characters[$stage["char_ref"]];

            if (!isset($stage["status"])) {
                
                echo "CombatPlayer(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;

                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|Instruction@{$character["name"]}@{$character["name"]} attacks {$GLOBALS["PLAYER_NAME"]}! ({$character["name"]} must use action Attack)@$taskId",
                        'tag' => ""
                    )
                );
                $quest["stages"][$n]["status"]=1;
                $quest["stages"][$n]["sub_status"]=time();
                break;

            } else if ($stage["status"]==1){
                $cn=$db->escape($character["name"]);
                echo "Check if character {$stage["char_ref"]} has started combat ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $moved=$db->fetchAll("select count(*) as n from eventlog where type='infoaction' and data like '%{$cn}%Attacks%'");
                if (is_array($moved)&& ($moved[0]["n"]>0)) {
                    echo "Character has started combat!".PHP_EOL;
                    $quest["stages"][$n]["status"]=1.5;
                    
                } else {

                    if (time()-$quest["stages"][$n]["sub_status"]>30) { // Repeat after 30 seconds
                        // Resend instruction
                        $quest["stages"][$n]["sub_status"]=time();
                        echo "CombatPlayer(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;

                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|Instruction@{$character["name"]}@{$character["name"]} attacks {$GLOBALS["PLAYER_NAME"]} ({$character["name"]} must use action Attack)@$taskId",
                                'tag' => ""
                            )
                        );
                        $quest["stages"][$n]["status"]=1;
                        
                    }
                }
            }

            if ($stage["status"]==1.5){
                $cn=$db->escape($character["name"]);
                echo "Check if character {$stage["char_ref"]} has died.{$quest["stages"][$n]["sub_status2"]} ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $moved=$db->fetchAll("select count(*) as n from eventlog where type='infonpc_close' and data like '%{$cn}(dead)%'");
                //error_log("select count(*) as n from eventlog where type='infonpc' and data like '%{$cn}(dead)%'");
                if (is_array($moved)&& ($moved[0]["n"]>0)) {
                    echo "Character has died!!".PHP_EOL;
                    $quest["stages"][$n]["status"]=2;
                    
                }

                $quest["stages"][$n]["sub_status2"]++;

                if (($quest["stages"][$n]["sub_status2"])>300) {
                    $quest["stages"][$n]["label"]="ToGoAway";       // Mutate his to ToGoAway, char_ref is the same
                    unset($quest["stages"][$n]["status"]);               // Status must be 0
                    unset($quest["stages"][$n]["parent_stage"]);    // Unset parent, so branch condition wont apply.
                    
                    break;

                }
            break;
            }

        }

        if ($stage["label"]=="WaitForCoins") {

        
            $character=$characters[$stage["char_ref"]];

            if (isset($stage["parent_stage"])) {
                error_log("{$localbranch} vs {$stage["branch"]}");
                if ($localbranch!=$stage["branch"]) {
                    $quest["stages"][$n]["status"]=5;
                    error_log($quest["stages"][$n]["label"]." skipped");
                    continue;
                }
            }

            if (!isset($stage["status"])) {
                
                echo "WaitForCoins(\"{$character["name"]}\",\"$taskId\")".PHP_EOL;
                $localAmount=$stage["amount"];
                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|Suggestion@{$character["name"]}@{$character["name"]} asks for {$localAmount} gold@$taskId",
                        'tag' => ""
                    )
                );
                $quest["stages"][$n]["status"]=1;
                $quest["stages"][$n]["checked_times"]=0;
                break;

            } else if ($stage["status"]==1){
                $cn=$db->escape($character["name"]);
                echo "Check if character {$stage["char_ref"]} has received gold ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $moved=$db->fetchAll("select count(*) as n from eventlog where type='itemfound' and (data like '%gave%Gold%to%{$cn}%' or data like '%gave%to%{$cn}%')");//Check for amount
                if (is_array($moved)&& ($moved[0]["n"]>0)) {
                    echo "Character has received gold!".PHP_EOL;
                    $quest["stages"][$n]["status"]=2;
                    
                }
                if (isset($quest["stages"][$n]["last_check"]) && ($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_check"])>= 120 * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS * $TALK_SPEED ) {
                    error_log("Enforcing ask for gold");

                    if ($quest["stages"][$n]["checked_times"]>3) {
                        $quest["stages"][$n]["status"]=4;
                        break;
                    } else {
                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|Suggestion@{$character["name"]}@{$character["name"]} asks for {$localAmount}  gold@$taskId",
                                'tag' => ""
                            )
                        );
                    }
                    $quest["stages"][$n]["last_check"]=$GLOBALS["gameRequest"][2];
                    $quest["stages"][$n]["checked_times"]=$quest["stages"][$n]["checked_times"]+1;
                } else if (!isset($quest["stages"][$n]["last_check"])) {
                    
                    $quest["stages"][$n]["last_check"]=$GLOBALS["gameRequest"][2];
                }

                
                
                
                
                break;
            }

        }

        if ($stage["label"]=="TellTopicToPlayer") {

            $character=$characters[$stage["char_ref"]];
            if ((strpos($GLOBALS["actors_present"],$character["name"])===false)&&(!isset($stage["status"]))) {
                // Actor not present. Wait
                echo "Actor not present. Wait...".PHP_EOL;
                break;
            }

            if (isset($stage["parent_stage"])) {
                error_log("{$localbranch} vs {$stage["branch"]}");
                if ($localbranch!=$stage["branch"]) {
                    $quest["stages"][$n]["status"]=5;
                    error_log($quest["stages"][$n]["label"]." skipped");
                    continue;
                }
            }
            // Stage 0
            if (!isset($stage["status"])) {
                $quest["stages"][$n]["status"]=1;
                $stage["status"]=1;
                $character=$characters[$stage["char_ref"]];
                $character2=["name"=>$GLOBALS["PLAYER_NAME"]];


                if (in_array($character["disposition"],["defiant","furious"])) {
                    $canCombat=false;
                    foreach ($quest["stages"] as $localstage) 
                        if ($localstage["label"]=="CombatPlayer")
                            $canCombat=true;
                    
                    
                    // Will draw weapon
                    if ($canCombat)
                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|Disposition@{$character["name"]}@{$character["disposition"]}@$taskId",
                                'tag' => ""
                            )
                        );

                }

                if (($character["location"]=="nearby") && (isset($topics[$stage["topic_ref"]]["first_one"])) ) { 
                    // If spawned nearby, character must talk to player,if first topic.  if not, will wait for player interaction
                    $sugggestionText=make_replacements("{$character["name"]} must talk to {$character2["name"]} about something like: {$topics[$stage["topic_ref"]]["info"]}");
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|Suggestion@{$character["name"]}@$sugggestionText@$taskId",
                            'tag' => ""
                        )
                    );
                    $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];// Dont make LLM call on next round
                    echo "First topic. Suggestion send".PHP_EOL;
                } else if (!isset($topics[$stage["topic_ref"]]["first_one"])) { // If not first topic, make suggestion.

                    $contextDataHistoric = checkHistory($character["name"]);
                    if (($contextDataHistoric)>=4) {
                        // But first, check if topic already has been covered
                        $quest["GLOBAL_LAST_LLM_CALL"]=1;
                        $topiCall=askLLMForTopic($character["name"],$topics[$stage["topic_ref"]]["info"],$quest["GLOBAL_LAST_LLM_CALL"]);
                        $quest["GLOBAL_LAST_LLM_CALL"]=time();
                        if ($topiCall["res"]) {
                            // NPC talked about topic
                            $quest["stages"][$n]["status"]=2;
                            $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                            $quest["GLOBAL_LAST_LLM_CALL"]=time();
                            
                            $db->upsertRow(
                                'currentmission',
                                array(
                                    'localts' => time(),
                                    'gamets' => $GLOBALS["gameRequest"][2],
                                    'ts' => $GLOBALS["gameRequest"][1],
                                    'description' => "{$character["name"]}:{$topics[$stage["topic_ref"]]["info"]}",
                                    'sess' => "$taskId",
                                    
                                ),
                                "sess='$taskId'"
                            );
                            $UPDATE_PROFILE=true;
                            echo "\nNPC talked about topic {$topics[$stage["topic_ref"]]["name"]}".PHP_EOL;

                        } else {
                            // Make suggestion, topic not covered
                            $sugggestionText=make_replacements("{$character["name"]} must talk to {$character2["name"]} about something like: {$topics[$stage["topic_ref"]]["info"]}");
                            if ($topiCall["missing"]) {
                                $sugggestionText=make_replacements("{$character["name"]} must talk to #PLAYER# about something like: {$topiCall["missing"]}");
                            }
                            $db->insert(
                                'responselog',
                                array(
                                    'localts' => time(),
                                    'sent' => 0,
                                    'actor' => "rolemaster",
                                    'text' => "",
                                    'action' => "rolecommand|Suggestion@{$character["name"]}@$sugggestionText@$taskId",
                                    'tag' => ""
                                )
                            );
                            echo "\nNPC dind't talk about topic {$topics[$stage["topic_ref"]]["name"]} suggestion sent".PHP_EOL;
                        }
                    } else {
                        // Make suggestion, dialogue is too small
                        $sugggestionText=make_replacements("{$character["name"]} must talk to {$character2["name"]} about something like: {$topics[$stage["topic_ref"]]["info"]}");
                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|Suggestion@{$character["name"]}@$sugggestionText@$taskId",
                                'tag' => ""
                            )
                        );
                        echo "dialogue is too small: {$topics[$stage["topic_ref"]]["info"]} suggestion sent".PHP_EOL;
                    }

                } else {
                    echo "No action. NPC will wait".PHP_EOL;
                    ;//Will wait for user to talk
                }
                break;
            }
            // Stage 1
            if ($stage["status"]==1){
                echo "Check if character {$stage["char_ref"]} has talked about topic {$stage["topic_ref"]} - {$topics[$stage["topic_ref"]]["name"]} to player ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $quest["stages"][$n]["status"]=1;

                $character=$characters[$stage["char_ref"]];
                $contextDataHistoric = checkHistory($character["name"]);
                if (($contextDataHistoric)<4) {
                    echo "Dialogue is too small ".(($contextDataHistoric)).PHP_EOL;
                } else {
                    
                    echo "Dialogue is no too small ".(($contextDataHistoric)).PHP_EOL;


                    if (isset($quest["stages"][$n]["last_llm_call"]) && ($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])>= 120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS *  $TALK_SPEED)
                        $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                    
                    else  if (!isset($quest["stages"][$n]["last_llm_call"])) {
                        $quest["stages"][$n]["last_llm_call"]=isset($quest["GLOBAL_LAST_LLM_CALL_GAMETS"])?$quest["GLOBAL_LAST_LLM_CALL_GAMETS"]:0;// Last GAMETS 
                    } else {
                        echo "Will check later ".($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])." -> ".(120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS * $TALK_SPEED).PHP_EOL;    
                        break;
                    }

                    
                    if (!isset($quest["GLOBAL_LAST_LLM_CALL"])) {
                        $quest["GLOBAL_LAST_LLM_CALL"]=0;
                        $quest["GLOBAL_LAST_LLM_CALL_GAMETS"]=$GLOBALS["gameRequest"][2]; // Store last gamets , TopicRequest
                    }

                    $topiCall=askLLMForTopic($character["name"],$topics[$stage["topic_ref"]]["info"],$quest["GLOBAL_LAST_LLM_CALL"]);
                    
                    if ($topiCall["res"]) {
                        // NPC talked about topic
                        $quest["stages"][$n]["status"]=2;
                        $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                        $quest["GLOBAL_LAST_LLM_CALL"]=time();
                        
                        $db->upsertRow(
                            'currentmission',
                            array(
                                'localts' => time(),
                                'gamets' => $GLOBALS["gameRequest"][2],
                                'ts' => $GLOBALS["gameRequest"][1],
                                'description' => "{$character["name"]}:{$topics[$stage["topic_ref"]]["info"]}",
                                'sess' => "$taskId",
                                
                            ),
                            "sess='$taskId'"
                        );
                        $UPDATE_PROFILE=true;
                        echo "NPC talked about topic {$topics[$stage["topic_ref"]]["info"]}".PHP_EOL;


                    } else if ($topiCall["missing"]=="skip"){ // Will jump to check later
                        error_log("Skip");
                    } else {
                        $quest["GLOBAL_LAST_LLM_CALL"]=time();
                        echo "Topic not covered yet {$topiCall["res"]}".PHP_EOL;
                        // Enforcing.

                        if (($quest["stages"][$n]["sub_status"]+0)==0) {
                            echo "Enforcing by Suggestion".PHP_EOL;
                            //$sugggestionText=make_replacements("{$character["name"]} must talk to #PLAYER# about something like: {$topics[$stage["topic_ref"]]["info"]}");
                            $sugggestionText=make_replacements("{$character["name"]} must talk to #PLAYER# about something like: {$topiCall["missing"]}");
                            $db->insert(
                                'responselog',
                                array(
                                    'localts' => time(),
                                    'sent' => 0,
                                    'actor' => "rolemaster",
                                    'text' => "",
                                    'action' => "rolecommand|Suggestion@{$character["name"]}@$sugggestionText@$taskId",
                                    'tag' => ""
                                )
                            );

                            $quest["stages"][$n]["sub_status"]=1;
                            break;
                        } else if (($quest["stages"][$n]["sub_status"]+0)<=2) {

                            echo "Enforcing by stronger Suggestion".PHP_EOL;
                            $sugggestionText=make_replacements("{$character["name"]} must talk to #PLAYER# about: {$topics[$stage["topic_ref"]]["info"]}");

                            $db->insert(
                                'responselog',
                                array(
                                    'localts' => time(),
                                    'sent' => 0,
                                    'actor' => "rolemaster",
                                    'text' => "",
                                    'action' => "rolecommand|Suggestion@{$character["name"]}@$sugggestionText.Hint:{$topiCall["missing"]}@$taskId",
                                    'tag' => ""
                                )
                            );

                            $quest["stages"][$n]["sub_status"]++;
                            break;

                        } else if (($quest["stages"][$n]["sub_status"]+0)>2) {

                            echo "Not accomplished".PHP_EOL;
                            $quest["stages"][$n]["sub_status"]++;
                            $quest["stages"][$n]["status"]=5;
                            break;

                        }

                    }

                }


                break;
            }  

            
        }

        if ($stage["label"]=="TellTopicToNPC") {

            if (isset($stage["parent_stage"])) {
                error_log("{$localbranch} vs {$stage["branch"]}");
                if ($localbranch!=$stage["branch"]) {
                    $quest["stages"][$n]["status"]=5;
                    error_log($quest["stages"][$n]["label"]." skipped");
                    continue;
                }
            }
            
            if (!isset($stage["status"])) {
                $quest["stages"][$n]["status"]=1;
                $stage["status"]=1;
                $character=$characters[$stage["char_ref"]];
                $character2=$characters[$stage["destination_ref"]];


                if (in_array($character["disposition"],["defiant","furious"])) {
                    $canCombat=false;
                    foreach ($quest["stages"] as $localstage) 
                        if ($localstage["label"]=="CombatPlayer")
                            $canCombat=true;
                    if ($canCombat)   
                        $db->insert(
                            'responselog',
                            array(
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|Disposition@{$character["name"]}@{$character["disposition"]}@$taskId",
                                'tag' => ""
                            )
                        );

                }

                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|Suggestion@{$character["name"]}@{$character["name"]} must talk to {$character2["name"]} about: {$topics[$stage["topic_ref"]]["info"]}@$taskId",
                        'tag' => ""
                    )
                );
                break;
            }

            if ($stage["status"]==1){
                echo "Check if character {$stage["char_ref"]} has talked about topic {$stage["topic_ref"]} - {$topics[$stage["topic_ref"]]["name"]} to player ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
                $quest["stages"][$n]["status"]=1;

                $character=$characters[$stage["char_ref"]];
                $contextDataHistoric = checkHistory($character["name"]);
                if (($contextDataHistoric)<4) {
                    echo "Dialogue is too small ".(($contextDataHistoric)).PHP_EOL;
                } else {
                    
                    echo "Dialogue is no too small ".(($contextDataHistoric)).PHP_EOL;


                    if (isset($quest["stages"][$n]["last_llm_call"]) && ($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])>= 120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS * $TALK_SPEED)
                        $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                    
                    else  if (!isset($quest["stages"][$n]["last_llm_call"])) {
                        $quest["stages"][$n]["last_llm_call"]=isset($quest["GLOBAL_LAST_LLM_CALL_GAMETS"])?$quest["GLOBAL_LAST_LLM_CALL_GAMETS"]:0;// Last GAMETS 
                    } else {
                        echo "Will check later ".($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])." -> ".(120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS * $TALK_SPEED).PHP_EOL;    
                        break;
                    }

                    
                    if (!isset($quest["GLOBAL_LAST_LLM_CALL"])) {
                        $quest["GLOBAL_LAST_LLM_CALL"]=0;
                        $quest["GLOBAL_LAST_LLM_CALL_GAMETS"]=$GLOBALS["gameRequest"][2]; // Store last gamets , TopicRequest
                    }

                    $topiCall=askLLMForTopic($character["name"],$topics[$stage["topic_ref"]]["info"],$quest["GLOBAL_LAST_LLM_CALL"]);
                    
                    if ($topiCall["res"]) {
                        $quest["stages"][$n]["status"]=2;
                        $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                        $quest["GLOBAL_LAST_LLM_CALL"]=time();    

                    } else if ($topiCall["missing"]=="skip"){ // Will jump to check later
                        error_log("Skip");
                    } else {
                        $quest["GLOBAL_LAST_LLM_CALL"]=time();
                        echo "Topic not covered yet {$topiCall["res"]}".PHP_EOL;
                        // Enforcing.

                        if (($quest["stages"][$n]["sub_status"]+0)==0) {
                            echo "Enforcing by instruction".PHP_EOL;
                        
                            $db->insert(
                                'responselog',
                                array(
                                    'localts' => time(),
                                    'sent' => 0,
                                    'actor' => "rolemaster",
                                    'text' => "",
                                    'action' => "rolecommand|Suggestion@{$character["name"]}@{$character["name"]} talks about {$topics[$stage["topic_ref"]]["info"]}@$taskId",
                                    'tag' => ""
                                )
                            );

                            $quest["stages"][$n]["sub_status"]=1;
                            break;
                        } else if (($quest["stages"][$n]["sub_status"]+0)<=2) {

                            echo "Enforcing by altering profile".PHP_EOL;
                            $db->insert(
                                'responselog',
                                array(
                                    'localts' => time(),
                                    'sent' => 0,
                                    'actor' => "rolemaster",
                                    'text' => "",
                                    'action' => "rolecommand|Suggestion@{$character["name"]}@{$character["name"]} talks about {$topics[$stage["topic_ref"]]["info"]}.{$topiCall["missing"]}@$taskId",
                                    'tag' => ""
                                )
                            );

                            $quest["stages"][$n]["sub_status"]++;
                            break;

                        } else if (($quest["stages"][$n]["sub_status"]+0)>2) {

                            echo "Not accomplished".PHP_EOL;
                            $quest["stages"][$n]["sub_status"]++;
                            $quest["stages"][$n]["status"]=5;
                            break;

                        }

                    }

                }


                break;
            }  
            
        }

        if ($stage["label"]=="WaitToItemBeRecovered") {
            if (!isset($stage["status"])) {
                echo "Check if item {$stage["item_ref"]} has been found by player  ".json_encode($items[$stage["item_ref"]]["name"]).PHP_EOL;
                $quest["stages"][$n]["status"]=1;
                if (isset($character["name"])) {
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|Sandbox@{$character["name"]}@$taskId",
                            'tag' => ""
                        )
                    );
                }
                break;
            }  else if ($stage["status"]==1){
                $itemname=$db->escape($items[$stage["item_ref"]]["name"]);
                echo "Check if item {$stage["item_ref"]} has been found by player  ".json_encode($items[$stage["item_ref"]]["name"]).PHP_EOL;
                $cn_item=$db->escape($items[$stage["item_ref"]]["name"]);
                $moved=$db->fetchAll("select count(*) as n from eventlog where type='itemfound' and data like '%$cn_item%'");
                if (is_array($moved)&& ($moved[0]["n"]>0)) {
                    echo "Player has found $itemname!".PHP_EOL;
                    $quest["stages"][$n]["status"]=2;
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|QuestNotifySound@$taskId",
                            'tag' => ""
                        )
                    );
                } 
                break;
            } 
        }

        if ($stage["label"]=="WaitToItemBeTraded") {

            if (!isset($stage["status"])) {
                echo "Check if item(s)".json_encode($stage["item_ref"])." has been traded by player  ".PHP_EOL;
                $quest["stages"][$n]["status"]=1;
                $quest["stages"][$n]["substatus"]=$GLOBALS["last_gamets"];
                $quest["stages"][$n]["retry"]=1;
                $quest["stages"][$n]["start_time"]=$GLOBALS["last_gamets"];
                break;
            }  else if ($stage["status"]==1){
                $character=$characters[$stage["char_ref"]];
                $cn_name=$db->escape($character["name"]);
                
                if (is_array($stage["item_ref"])) {
                    foreach ($stage["item_ref"] as $ref)
                        $localItems[]=$items[$ref];
                }
                else
                    $localItems[]=$items[$stage["item_ref"]];

                $globalstatus=0;

                foreach ($localItems as $localItem) {   
                    $itemname=$db->escape($localItem["name"]);

                    $localElapsed=$GLOBALS["last_gamets"]-$quest["stages"][$n]["substatus"];
                    $localLimit=60  * SECOND_GAMETS_MULT*$TALK_SPEED;

                    echo "Check if item {$itemname} has been traded by player ($localElapsed/$localLimit), retry {$quest["stages"][$n]["retry"]} / ".json_encode($localItem).PHP_EOL;
                    $cn_item=$db->escape($itemname);
                    $moved=$db->fetchAll("select count(*) as n from eventlog where type='itemfound' and data like '%gave%$cn_item%$cn_name%' 
                        and gamets>{$quest["start_gamets"]}");//After quest start gamets

                    
                    if (is_array($moved)&& ($moved[0]["n"]>0)) {
                        echo "Player has traded $itemname to $cn_name!".PHP_EOL;
                        $quest["stages"][$n]["status"]=1;
                        $globalstatus=(($globalstatus==0)||($globalstatus==2))?2:1;
                       
                       
                        
                    } else {
                        $globalstatus=1;
                        $quest["stages"][$n]["status"]=1;
                        if (strpos($GLOBALS["actors_present"],$character["name"])===false) {
                            // Actor not present, check later
                            echo "Will check later as {$character["name"]} not present";
                            $quest["stages"][$n]["substatus"]=$GLOBALS["last_gamets"];
                            $quest["stages"][$n]["retry"]=1;
                        } else {
                        
                            if ($GLOBALS["last_gamets"]-$quest["stages"][$n]["substatus"]> 60  *  SECOND_GAMETS_MULT *$TALK_SPEED ) {   // Should wait about two in-game minutes. If not, stage will fail
                                $quest["stages"][$n]["retry"]++;
                                echo "Enforcing trade request".PHP_EOL;
                                $db->insert(
                                    'responselog',
                                    array(
                                        'localts' => time(),
                                        'sent' => 0,
                                        'actor' => "rolemaster",
                                        'text' => "",
                                        'action' => "rolecommand|Suggestion@{$character["name"]}@{$character["name"]} persistently urges the player to hand over {$localItem["name"]}@$taskId",
                                        'tag' => ""
                                    )
                                );

                                if ($quest["stages"][$n]["retry"]>2) {
                                    echo "Timeout, mark as failed";
                                    $quest["stages"][$n]["status"]=5;
                                } else {
                                    $quest["stages"][$n]["substatus"]=$GLOBALS["last_gamets"];
                                }
                            }
                        }
                    }
                }

                if ($globalstatus==2) {    // All items traded
                    $quest["stages"][$n]["status"]=2;
                    $db->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|QuestNotifySound@$taskId",
                            'tag' => ""
                        )
                    );
                }
                break;
            } 
        }
        
        
    }
}

if ($UPDATE_PROFILE ) {
    // Topics revealed, should update profiles
    

    //print_r($topics);
    //print_r($quest["stages"]);

    foreach ($characters as $character) {

        $allowedTopics=[];
        $nonrevealedTopics=[];
        
        foreach ($topics as $id=>$topic) {
            if ($topic["giver"]==$character["name"]) {
                foreach ($quest["stages"] as $stage) {
                    if ($stage["label"]=="TellTopicToPlayer" && $stage["topic_ref"]==$id && isset($stage["status"]) && $stage["status"]==2) {
                        $allowedTopics[$id]=make_replacements($topic["info"]);

                    } else if ($stage["label"]=="TellTopicToPlayer" && $stage["topic_ref"]==$id){
                        $nonrevealedTopics[$id]=make_replacements($topic["info"]);
                    }
                }
            }
        }

        if (sizeof($allowedTopics)==0 && sizeof($nonrevealedTopics)==0) {
            continue;
        }
        $allowedTopicsText=implode("\n*",$allowedTopics);
        $nonrevealedTopicsText=implode("\n*",$nonrevealedTopics);
        $PARMS["HERIKA_PERS"]="Roleplay as {$character["name"]} ({$character["race"]} {$character["gender"]})\n".
        "{$character["appearance"]}\n".
        "{$character["background"]}\n".
        "#SpeechStyle\n{$character["speechStyle"]}\n";

        $PARMS["HERIKA_PERS"].="\n\nGenerate content based on the following allowed topics:\n* $allowedTopicsText.\n";
        if (sizeof($nonrevealedTopics)>0)  {
            $PARMS["HERIKA_PERS"].="\nAlso, consider the contextual topics:\n* $nonrevealedTopicsText\n".
            "but do not mention them directly or reveal any details about them.\n". 
            "Use the contextual topics only to ensure the generated content remains consistent and does not contradict future information. Avoid spoilers at all times.";
        }
        
        
        $pclass=$character["class"];
        
        $PARMS["RECHAT_H"]=(sizeof($GLOBALS["characters"])*2)+1;// % for one character

        if (in_array($character["disposition"],["drunk"])) {
            $PARMS["EMOTEMOODS"]="drunk";
        } else if (in_array($character["disposition"],["high"])) {
            $PARMS["EMOTEMOODS"]="high";
        }
    
        createProfile($character["name"],$PARMS,true);

    }
    
}
// print_r($quest["stages"]);

// Check if player died
if (($quest["start_gamets"]>$GLOBALS["last_gamets"])) {
    $allDone=true;
    $failed=true;
    echo "Time went backwards. Game reloaded?".PHP_EOL;
} else {

    // Check again if all stages done
    $allDone=true;
    foreach ($quest["stages"] as $stage) {
        
        if (isset($stage["status"]))
            $allDone=$allDone&($stage["status"]>=2);
        else {
            $allDone=false;
            break;
        }

    }
}
// If all done, send quest is done 
if ($allDone) {
    if (isset($character["name"])) {
       
    }
    $db->delete("currentmission","sess='$taskId'");
    foreach ($characters as $character) {
        $namedKey="{$character["name"]}_is_rolemastered";
        $db->delete("conf_opts", "id='".$db->escape($namedKey)."'");
    }
    if (!$failed) {
        $db->insert(
            'responselog',
            array(
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|EndQuest@{$quest["quest"]}@$taskId",
                'tag' => ""
            )
        );
    }
    echo "Quest completed!".PHP_EOL;
}

$quest["characters"]=$characters;
//updateRow. Store Instantiated quest with last updates.
$db->updateRow(
    'aiquest',
    array(
        'definition' => json_encode($quest),
        'updated' => time(),
        'status' => ($allDone)?2:1,
        'taskid' => $taskId

    ),
    "taskid='$taskId'"
);


?>