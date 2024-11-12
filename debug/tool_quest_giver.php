<?php 

//$ODATA=file_get_contents(__DIR__."/generated_quest.json");

define("SECOND_GAMETS_MULT",2000);

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
    $newRunningQuest=$db->delete("aiquest","status=1");
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



foreach ($quest["initial_data"] as $n=>$step) {
    $command=key($step);
    if ($command=="createCharacter") {
        
        $quest["initial_data"][$n]["taskid"]=$taskId;

        $characters[$step["id"]]=$step[$command]["character"];

    } else  if ($command=="spawnItem") {
        //echo "* $command ".PHP_EOL;
        //echo "spawnItem(\"{$step[$command]["item"]["name"]}\",\"{$step[$command]["item"]["type"]}\",\"{$step[$command]["item"]["location"]}\")".PHP_EOL;
        $items[$step["id"]]=$step[$command]["item"];
        if (!$quest["initial_data_done"]) {
            CreateItem($items[$step["id"]]["type"],$items[$step["id"]]["name"],strtolower($items[$step["id"]]["location"]));
            
        }

    } else  if ($command=="createTopic") {
        //echo "* $command ".PHP_EOL;
        //echo "createTopic(\"{$step[$command]["topic"]["name"]}\",\"{$step[$command]["topic"]["giver"]}\")".PHP_EOL;
        $topics[$step["id"]]=$step[$command]["topic"];


    }


}

$quest["initial_data_done"]=true;

$N_TOPIC_ELEMENTS=(sizeof($topics)+10)/24;
$allDone=true;
foreach ($quest["stages"] as $stage) {
    if (isset($stage["status"]))
        $allDone=$allDone&($stage["status"]>=2);
    else {
        $allDone=false;
        break;
    }

}

if ($allDone) {
    die("Quest completed!");
}




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

    if (isset($stage["parent_stage"])) {
        if ($quest["stages"][$stage["parent_stage"]-1]["status"]==2)    // Parent stage ended ok
            $localbranch=1;
        else
            $localbranch=2;    
    }

    if ($stage["label"]=="SpawnCharacter") {
        $character=$characters[$stage["char_ref"]];
        
        if (!isset($stage["status"])) {
            
            echo "spawnCharacter(\"{$character["name"]}\",\"{$character["gender"]}\",\"{$character["race"]}\",\"$taskId\")".PHP_EOL;
            $cn_gender=strtolower($character["gender"]);
            $cn_race=strtolower($character["race"]);
            $cn_location=strtolower($character["location"]);

            $pclass=$character["class"];

            npcProfileBase($character["name"],$pclass,$cn_race,$cn_gender,$cn_location,$taskId);
            
            error_log("DONE 2");
            $quest["stages"][$n]["status"]=1;
            break;

        } else if ($stage["status"]==1){

            $cn=$db->escape($character["name"]);
            echo "Check if character $cn {$stage["char_ref"]} has spawned ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
            $spawned=$db->fetchAll("select count(*) as n from eventlog where type='status_msg' and data like '%spawned@$cn%'");
            if (is_array($spawned)&& ($spawned[0]["n"]>0)) {
                echo "Character has spawned!".PHP_EOL;
                $quest["stages"][$n]["status"]=2;
                
                echo "spawnCharacter(\"{$character["name"]}\",\"{$character["gender"]}\",\"{$character["race"]}\",\"$taskId\")".PHP_EOL;
                $cn_gender=strtolower($character["gender"]);
                $cn_race=strtolower($character["race"]);

                $PARMS["HERIKA_PERS"]="Roleplay as {$character["name"]} ({$character["race"]} {$character["gender"]})\n".
                "{$character["appearance"]}\n".
                "{$character["background"]}\n".
                "{$character["speechStyle"]}\n";

                //$PARMS["EMOTEMOODS"]="drunk";

                foreach ($topics as $topic) {
                    if ($topic["giver"]==$character["name"]) {
                        $PARMS["HERIKA_PERS"].="\nThis character knows about this topic:{$topic["info"]}";
                        break;// Only first topic unveiled
                    }

                }
                
                $pclass=$character["class"];
                
                $PARMS["RECHAT_H"]=(sizeof($GLOBALS["characters"])*2)+1;

                if (in_array($character["disposition"],["drunk"])) {
                    $PARMS["EMOTEMOODS"]="drunk";
                } else if (in_array($character["disposition"],["high"])) {
                    $PARMS["EMOTEMOODS"]="high";
                }

                createProfile($character["name"],$PARMS,true);

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
                    'action' => $db->escape("rolecommand|moveToPlayer@{$character["name"]}@$taskId"),
                    'tag' => ""
                )
            );
            $quest["stages"][$n]["status"]=1;
            break;

        } else if ($stage["status"]==1){
            $character=$characters[$stage["char_ref"]];
            $cn=$db->escape($character["name"]);
            echo "Check if character {$stage["char_ref"]} has reached player ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
            $moved=$db->fetchAll("select count(*) as n from eventlog where type='status_msg' and data like '%reached_destination@{$cn}%'");
            
            if (is_array($moved)&& ($moved[0]["n"]>0)) {
                echo "Character has moved!".PHP_EOL;
                $quest["stages"][$n]["status"]=2;

                $db->delete("eventlog", "  type='status_msg' and data like '%reached_destination@{$cn}%' ");

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
                        'action' => $db->escape("rolecommand|stayAtPlace@{$character["name"]}@$follow@$taskId"),
                        'tag' => ""
                    )
                );
                
                

                
                break;
                // Load data from profile?
                $npcConfFile="conf_".(md5($characters[$stage["char_ref"]]["name"])).".php";
                require_once($enginePath."/conf/".$npcConfFile);
               
                returnLines(["Hello traveller!"],false);

                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "{$character["name"]}",
                        'text' => "",
                        'action' => "ScriptQueue|Hello traveller!/DialogueHappy/{$GLOBALS["PLAYER_NAME"]}/IdleDialogueExpressiveStart/",
                        'tag' => ""
                    )
                );
            }

            if ((isset($quest["stages"][$n+1])) && ($quest["stages"][$n+1]["label"]=="MoveToPlayer") && ($quest["stages"][$n+1]["char_ref"]!=$quest["stages"][$n]["char_ref"]) ) {   // Run next instruction if moveToPlayer too

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
            $quest["stages"][$n]["status"]=1;
            break;

        } else if ($stage["status"]==1){
            $cn=$db->escape($character["name"]);
            echo "Check if character {$stage["char_ref"]} has reached destination ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
            $moved=$db->fetchAll("select count(*)  as n from (select * from eventlog where type='infonpc' order by rowid desc limit 1) where people ilike '%{$cn}%'");
            
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
            }

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
            echo "Check if character {$stage["char_ref"]} has  died ".json_encode($characters[$stage["char_ref"]]["name"]).PHP_EOL;
            $moved=$db->fetchAll("select count(*) as n from eventlog where type='infonpc' and data like '%{$cn}(dead)%'");
            //error_log("select count(*) as n from eventlog where type='infonpc' and data like '%{$cn}(dead)%'");
            if (is_array($moved)&& ($moved[0]["n"]>0)) {
                echo "Character has died!!".PHP_EOL;
                $quest["stages"][$n]["status"]=2;
                
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
            if (isset($quest["stages"][$n]["last_check"]) && ($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_check"])> 120 * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS) {
                error_log("Enforcing ask for gold");

                if ($quest["stages"][$n]["checked_times"]>0) {
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
            $character2=["name"=>"player"];


            if (in_array($character["disposition"],["defiant","furious"])) {
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


                if (isset($quest["stages"][$n]["last_llm_call"]) && ($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])> 120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS)
                    $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                
                else  if (!isset($quest["stages"][$n]["last_llm_call"])) {
                    $quest["stages"][$n]["last_llm_call"]=isset($quest["GLOBAL_LAST_LLM_CALL_GAMETS"])?$quest["GLOBAL_LAST_LLM_CALL_GAMETS"]:0;// Last GAMETS 
                } else {
                    echo "Will check later ".($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])." -> ".(120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS).PHP_EOL;    
                    break;
                }

                
                if (!isset($quest["GLOBAL_LAST_LLM_CALL"])) {
                    $quest["GLOBAL_LAST_LLM_CALL"]=0;
                    $quest["GLOBAL_LAST_LLM_CALL_GAMETS"]=$GLOBALS["gameRequest"][2]; // Store last gamets , TopicRequest
                }

                $topiCall=askLLMForTopic($character["name"],$topics[$stage["topic_ref"]]["info"],$quest["GLOBAL_LAST_LLM_CALL"]);
                
                if ($topiCall["res"]) {
                    $quest["stages"][$n]["status"]=2;
                    $quest["stages"][$n]["last_llm_call"]=time();
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


                if (isset($quest["stages"][$n]["last_llm_call"]) && ($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])> 120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS)
                    $quest["stages"][$n]["last_llm_call"]=$GLOBALS["gameRequest"][2];
                
                else  if (!isset($quest["stages"][$n]["last_llm_call"])) {
                    $quest["stages"][$n]["last_llm_call"]=isset($quest["GLOBAL_LAST_LLM_CALL_GAMETS"])?$quest["GLOBAL_LAST_LLM_CALL_GAMETS"]:0;// Last GAMETS 
                } else {
                    echo "Will check later ".($GLOBALS["gameRequest"][2]-$quest["stages"][$n]["last_llm_call"])." -> ".(120  * SECOND_GAMETS_MULT * $N_TOPIC_ELEMENTS).PHP_EOL;    
                    break;
                }

                
                if (!isset($quest["GLOBAL_LAST_LLM_CALL"])) {
                    $quest["GLOBAL_LAST_LLM_CALL"]=0;
                    $quest["GLOBAL_LAST_LLM_CALL_GAMETS"]=$GLOBALS["gameRequest"][2]; // Store last gamets , TopicRequest
                }

                $topiCall=askLLMForTopic($character["name"],$topics[$stage["topic_ref"]]["info"],$quest["GLOBAL_LAST_LLM_CALL"]);
                
                if ($topiCall["res"]) {
                    $quest["stages"][$n]["status"]=2;
                    $quest["stages"][$n]["last_llm_call"]=time();
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
                
            } 
            break;
        } 
    }


    if ($stage["label"]=="WaitToItemBeTraded") {
        if (!isset($stage["status"])) {
            echo "Check if item {$stage["item_ref"]} has been traded by player  ".json_encode($items[$stage["item_ref"]]["name"]).PHP_EOL;
            $quest["stages"][$n]["status"]=1;
            
            break;
        }  else if ($stage["status"]==1){
            $character=$characters[$stage["char_ref"]];
            $cn_name=$db->escape($character["name"]);

            $itemname=$db->escape($items[$stage["item_ref"]]["name"]);
            echo "Check if item {$stage["item_ref"]} has been traded by player  ".json_encode($items[$stage["item_ref"]]["name"]).PHP_EOL;
            $cn_item=$db->escape($items[$stage["item_ref"]]["name"]);
            $moved=$db->fetchAll("select count(*) as n from eventlog where type='itemfound' and data like '%gave%$cn_item%$cn_name%'");
            if (is_array($moved)&& ($moved[0]["n"]>0)) {
                echo "Player has found $itemname!".PHP_EOL;
                $quest["stages"][$n]["status"]=2;
                
                
            } else {
                $quest["stages"][$n]["substatus"]++;
            }
            break;
        } 
    }
    
    
}

// print_r($quest["stages"]);

$allDone=true;
foreach ($quest["stages"] as $stage) {
    
    if (isset($stage["status"]))
        $allDone=$allDone&($stage["status"]>=2);
    else {
        $allDone=false;
        break;
    }

}

if ($allDone) {
    if (isset($character["name"])) {
       
    }

    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => $db->escape("rolecommand|EndQuest@{$quest["quest"]}@$taskId"),
            'tag' => ""
        )
    );
    echo "Quest completed!".PHP_EOL;
}

//updateRow
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

//file_put_contents(__DIR__."/generated_quest.json",json_encode($quest));
//print_r($quest);
?>