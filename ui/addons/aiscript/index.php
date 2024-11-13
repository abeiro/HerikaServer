<?php 

error_reporting(E_ERROR);
session_start();

$rootEnginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($rootEnginePath . "conf".DIRECTORY_SEPARATOR."conf.php");

require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."misc_ui_functions.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."rolemaster_helpers.php");
require_once($rootEnginePath . "connector" .DIRECTORY_SEPARATOR."__jpd.php");


$db=new sql();


if ($_GET["action"]=="stop") {
    if ($_GET["taskid"]) {
        $db->delete("aiquest","taskid='{$_GET["taskid"]}'");
        header("Location: index.php");
        
    }
} else if ($_GET["action"]=="start") {
    $cn_title=$db->escape($_GET["title"]);
    $newRunningQuest=$db->fetchAll("SELECT * FROM aiquests_template where title='$cn_title'");
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
    header("Location: index.php");

} else if ($_GET["action"]=="start_alike") {
    $cn_title=$db->escape($_GET["title"]);
    $newRunningQuest=$db->fetchAll("SELECT * FROM aiquests_template where title='$cn_title'");
    $taskId=uniqid();
    $quest=json_decode($newRunningQuest[0]["data"],true);

    $notes=["dramatic story","romance story","comedy flair","rude cursed words story"];

    //$newquest=createQuestFromTemplate($quest,$notes[array_rand($notes)]);
    $newquest=createQuestFromTemplate($quest,"$notes. adapt characters to it.");
    
    if ($newquest) {
        $pointer=null;

        if (isset($newquest[0]["quest"]))
            $pointer=$newquest[0];
        else if (isset($newquest["quest"]))
            $pointer=$newquest;

        if ($pointer) {
            $db->insert(
                'aiquest',
                array(
                    'definition' => json_encode($pointer),
                    'updated' => time(),
                    'status' => 1,
                    'taskid' => $taskId

                )
            );
            header("Location: index.php");
        } else {
            echo "Error";    
        }
    } else 
        echo "Error";

}


$results = $db->fetchAll("select title,data,enabled from aiquests_template");
echo "<h3 class='my-2'>Quest templates</h3>";
$list=[];
foreach ($results as $n=>$quest) {
    $questdata=json_decode($quest["data"],true);
    
    $list[$n]["title"]=$quest["title"];
    $list[$n]["overview"]=$questdata["overview"];
    $list[$n]["enabled"]=$quest["enabled"];
    $list[$n]["action"]="<a href='?action=start&title=".urlencode($quest["title"])."'>start</a>";
    $list[$n]["action"].=":: <a href='?action=start_alike&title=".urlencode($quest["title"])."'>start alike</a>";

}

print_array_as_table($list);

$results = $db->fetchAll("select * from aiquest where status=1");
$list=[];
echo "<h3 class='my-2'>Running quests</h3>";
foreach ($results as $n=>$quest) {
    $questdata=json_decode($quest["definition"],true);
    
    $list[$n]["title"]=$questdata["quest"];

    foreach ($questdata["stages"] as $stage) {
        if ($stage["status"]==1) {
            $list[$n]["current_stage"]=$stage["label"];

        }
    }
    $list[$n]["action"]="<a href='?action=stop&taskid={$quest["taskid"]}'>stop</a>";

}
print_array_as_table($list);

?>