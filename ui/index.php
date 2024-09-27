<?php
error_reporting(E_ERROR);
session_start();

$configFilepath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR;
$rootEnginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

if (!file_exists($configFilepath."conf.php")) {
    @copy($configFilepath."conf.sample.php", $configFilepath."conf.php");   // Defaults
    /*if (!file_exists($rootEnginePath."data".DIRECTORY_SEPARATOR."mysqlitedb.db")) {
        require($rootEnginePath."ui".DIRECTORY_SEPARATOR."cmd".DIRECTORY_SEPARATOR."install-db.php");
        
    }*/
    die(header("Location: quickstart.php"));
}


require_once($rootEnginePath . "conf".DIRECTORY_SEPARATOR."conf.php");

$configFilepath=realpath($configFilepath).DIRECTORY_SEPARATOR;

// Profile selection
$GLOBALS["PROFILES"]["default"]="$configFilepath/conf.php";
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash]=$mconf;
    }
}

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"],$GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);

} else
    $_SESSION["PROFILE"]="$configFilepath/conf.php";
// End of profile selection




require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."misc_ui_functions.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");


ob_start();
include("tmpl/head.html");
$db = new sql();


/* Check for database updates */
require_once(__DIR__."/../debug/db_updates.php");
require_once(__DIR__."/../debug/npc_removal.php");
/* END of check database for updates */

/* Actions */
if ($_GET["clean"]) {
    $db->delete("responselog", "sent=1");
}
if ($_GET["reset"]) {
    $db->delete("eventlog", "true");
    header("Location: index.php");
}

if ($_GET["sendclean"]) {
    $db->update("responselog", "sent=0", "sent=1 ");
}

if ($_GET["cleanlog"]) {
    $db->delete("log", "true");
}

if ($_GET["togglemodel"]) {
    require_once(__DIR__ .DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."model_dynmodel.php");
    $newModel=DMtoggleModel();
    while (@ob_end_clean());
    header("Location: index.php");
    die();
}


if ($_GET["export"] && $_GET["export"] == "log") {
    while (@ob_end_clean());

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=log.csv");

    $data = $db->fetchAll("select response,url,prompt,rowid from log order by rowid desc");
    $n = 0;
    foreach ($data as $row) {
        if ($n == 0) {
            echo "'" . implode("'\t'", array_keys($row)) . "'\n";
            $n++;
        }
        $rowCleaned = [];
        foreach ($row as $cellname => $cell) {
            if ($cellname == "prompt")
                $cell = base64_encode(br2nl($cell));
            $rowCleaned[] = strtr($cell, array("\n" => " ", "\r" => " ", "'" => "\""));
        }

        echo "'" . implode("'\t'", ($rowCleaned)) . "'\n";
    }
    die();
}

if ($_GET["export"] && $_GET["export"] == "diary") {
    while (@ob_end_clean());

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=diarylog.txt");

    $data = $db->fetchAll("select topic,content from diarylogv2 order by rowid desc");
    $n = 1;
    foreach ($data as $row) {
        if ($n == 0) {
            echo "'" . implode("'\t'", array_keys($row)) . "'\n";
            $n++;
        }
        $rowCleaned = [];
        foreach ($row as $cellname => $cell) {
            if ($cellname == "prompt")
                $cell = base64_encode(br2nl($cell));
            $rowCleaned[] = strtr($cell, array("\n" => " ", "\r" => " ", "'" => "\""));
        }

        echo "'" . implode("'\t'", ($rowCleaned)) . "'\n";
    }
    die();
}

if ($_GET["reinstall"]) {
    require_once("cmd/install-db.php");
    header("Location: index.php?table=response");
}

if ($_POST["command"]) {
    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => $_POST["command"] . "@" . $_POST["parameter"],
            'actor' => "{$GLOBALS["HERIKA_NAME"]}",
            'action' => 'command'
        )
    );
    header("Location: index.php?table=response");
}

if ($_POST["animation"]) {
    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => trim($_POST["animation"]),
            'actor' => "{$GLOBALS["HERIKA_NAME"]}",
            'action' => 'animation'
        )
    );
    header("Location: index.php?table=response");
}

?>

<!-- navbar -->
<?php
$debugPaneLink = true;
include("tmpl/navbar.php");
?>
<!--<a href='index.php?openai=true'  >OpenAI API Usage</a> -->

<div class="clearfix"></div>

<div class="container-fluid">

    <!-- debug pane -->
    <div class="debugpane d-none">
        <?php
        include("tmpl/debug-pane.php");
        ?>
    </div>

    <!-- auto info -->
    <?php
    if ($_GET["autorefresh"]) {
    ?>
    <p class="my-2">
        <small class='text-body-secondary fs-5'>Autorefreshes every 5 secs</small>
    </p>
    <?php
    }

    /* Actions */
    if ($_GET["table"] == "responselog") {
        $results = $db->fetchAll("select  A.*,ROWID FROM responselog a order by ROWID asc");
        echo "<h3 class='my-2'>Response Queue</h3>";
        print_array_as_table($results);
    }

    if ($_GET["table"] == "eventlog") {
        $results = $db->fetchAll("select type,data,gamets,localts,ts,ROWID FROM eventlog a where type not in ('prechat','rechat') order by gamets desc,ts  desc,localts desc,rowid desc LIMIT 50");
        echo "<h3 class='my-2'>Event Log</h3>";
        print_array_as_table($results);
        if ($_GET["autorefresh"]) {
            header("Refresh:5");
        }
    }
    if ($_GET["table"] == "cache") {
        $results = $db->fetchAll("select  A.*,ROWID FROM eventlog a order by ts  desc");
        echo "<h3 class='my-2'>Event Log</h3>";
        print_array_as_table($results);
    }
    if ($_GET["table"] == "log") {
        $results = $db->fetchAll("select  A.*,ROWID FROM log a order by localts desc,rowid desc");
        echo "<h3 class='my-2'>AI Log</h3>";
        print_array_as_table($results);
    }

    if ($_GET["table"] == "quests") {
        $results = $db->fetchAll("SElECT  name,id_quest,briefing,briefing2,data from quests");
        $finalRow = [];
        foreach ($results as $row) {
            if (isset($finalRow[$row["id_quest"]]))
                continue;
            else
                $finalRow[$row["id_quest"]] = $row;
        }
        echo "<h3 class='my-2'>Current Active Quests</h3>";

        print_array_as_table(array_values($finalRow));
    }

    if ($_GET["table"] == "currentmission") {
        $results = $db->fetchAll("select  A.*,ROWID FROM currentmission A order by gamets desc,localts desc,rowid desc limit 150 offset 0");
        echo "<h3 class='my-2'>Current AI Task/Goal</h3>";
        print_array_as_table($results);
    }

    if ($_GET["table"] == "diarylog") {

        $results = $db->fetchAll("select  A.*,ROWID FROM diarylog A order by gamets desc,rowid desc limit 150 offset 0");
        echo "<h3 class='my-2'>Diary Entries</h3>";
        print_array_as_table($results);
    }

    if ($_GET["table"] == "books") {
        $results = $db->fetchAll("select  A.*,ROWID FROM books A order by gamets desc,rowid desc limit 150 offset 0");
        echo "<h3 class='my-2'>Book Log</h3>";
        print_array_as_table($results);
    } 


    if ($_GET["table"] == "openai_token_count") {
        $results = $db->fetchAll("select  A.*,ROWID FROM openai_token_count A order by rowid desc limit 150 offset 0");
        echo "<h3 class='my-2'>OpenAI Token Pricing</h3>";
        echo ($results);
    }

    
    if ($_GET["table"] == "memory") {
        $results = $db->fetchAll("select  A.*,ROWID as rowid FROM memory A order by gamets desc,rowid desc limit 150 offset 0");
        echo "<h3 class='my-2'>Memories Log</h3>";
        print_array_as_table($results);
    }
    
    if ($_GET["table"] == "memory_summary") {
        $results = $db->fetchAll("select  gamets_truncated,n,packed_message,summary,companions,classifier,tags,uid,ROWID as rowid FROM memory_summary A order by gamets_truncated desc,rowid desc limit 150 offset 0");
        echo "<h3 class='my-2'>Summarized Memories Log</h3>";
        print_array_as_table($results);
    }
      
    if ($_GET["notes"]) {
        echo file_get_contents(__DIR__."/notes.html");
    }
    
    if ($_GET["plugins_show"]) {
        $pluginFoldersRoot=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR;
        $pluginFolders=scandir($pluginFoldersRoot);
        foreach ($pluginFolders as $n=>$folder)
		if (!is_dir($pluginFoldersRoot.$folder))
			unset($pluginFolders[$n]);
		else if (strpos($folder,".")===0)
			unset($pluginFolders[$n]);
        
        echo "<ul>";
        foreach ($pluginFolders as $folder) {
            if (file_exists($pluginFoldersRoot.$folder.DIRECTORY_SEPARATOR."manifest.json")) {
                $manifest=json_decode(file_get_contents($pluginFoldersRoot.$folder.DIRECTORY_SEPARATOR."manifest.json"),true);
                $description=$manifest["description"];
            }
            else
                $description="description not available";
            
            echo "<li>$folder: $description</li>";
            
        }
        echo "</ul>";
        
    }
    
    ?>
</div> <!-- close main container -->
<?php

include("tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = "AI Follower Framework Server";
$title .= (($_GET["autorefresh"]) ? " (autorefreshes every 5 secs)" : "");
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;

?>
