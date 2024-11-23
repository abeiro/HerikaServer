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
    if (file_exists($_SESSION["PROFILE"]))
        require_once($_SESSION["PROFILE"]);
    else {
        $_SESSION["PROFILE"]="$configFilepath/conf.php";
        
    }

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
        $results = $db->fetchAll("select type,data,gamets,localts,ts,ROWID FROM eventlog a where type not in ('prechat','rechat','infonpc','request') order by gamets desc,ts  desc,localts desc,rowid desc LIMIT 50");
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
        $results = $db->fetchAll("select  A.*,ROWID FROM log a order by localts desc,rowid desc  LIMIT 50");
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

    if ($_GET["table"] == "audit_request") {
        $results = $db->fetchAll("select  SUBSTRING(request,1,150) as request,result,created_at,rowid FROM audit_request A order by created_at desc limit 50 offset 0");
        echo "<h3 class='my-2'>Request to LLM services Log</h3><span>Go to database manager, table audit_request for full detail</span>";
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
        echo "<h3 class='my-2'>Summarized Memories Log (Enable AUTO_CREATE_SUMMARYS in the default profile) </h3>";
        print_array_as_table($results);
    }
      
    if ($_GET["notes"]) {
        echo file_get_contents(__DIR__."/notes.html");
    }
    
    if ($_GET["plugins_show"]) {
        $pluginFoldersRoot = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "ext" . DIRECTORY_SEPARATOR;
        $pluginFolders = scandir($pluginFoldersRoot);
        foreach ($pluginFolders as $n => $folder)
            if (!is_dir($pluginFoldersRoot . $folder) || substr($folder, 0, 1) === '.')
                unset($pluginFolders[$n]);
    
        // Add custom styles
        echo '<style>
        .open-overlay-btn {
            padding: 10px 20px;
            background-color: rgb(0, 48, 176);
            color: #ffffff;
            border: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.65);
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            transition: background-color 0.3s, color 0.3s;
            margin: 5px;
            font-weight: bold;
        }
        .delete-plugin-btn {
            padding: 10px 20px;
            background-color: rgb(176, 0, 0);
            color: #ffffff;
            border: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.65);
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            transition: background-color 0.3s, color 0.3s;
            margin: 5px;
            font-weight: bold;
        }
        .configure-plugin-btn {
            padding: 10px 20px;
            background-color: rgb(0, 176, 80);
            color: #ffffff;
            border: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.65);
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            transition: background-color 0.3s, color 0.3s;
            margin: 5px;
            font-weight: bold;
        }
        table {
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            padding: 10px;
        }
        table th {
            background-color: var(--bs-primary-bg-subtle) !important;
        }
        .title-with-button {
            display: flex;
            align-items: center;
        }
        .title-with-button h2 {
            margin-right: 10px;
            margin-bottom: 0;
        }
        </style>';
    
        // Add a title for installed plugins with Refresh button
        echo '<br>';
        echo '<div class="title-with-button">';
        echo '<h2>Installed CHIM Plugins</h2>';
        echo '<form method="post" style="margin: 0;">
        <input type="hidden" name="refresh_plugins" value="1">
        <button type="submit" class="open-overlay-btn">Refresh Plugins</button>
        </form>';
        echo '</div>';
    
        // Display installed plugins in a table
        echo '<table border="1">';
        echo '<tr>
                <th>Plugin</th>
                <th>Description</th>
                <th>Plugin Menu</th>
                <th>Delete Plugin</th>
            </tr>';
        foreach ($pluginFolders as $folder) {
            $manifestPath = $pluginFoldersRoot . $folder . '/manifest.json';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $name = $manifest['name'] ?? $folder;
                $description = $manifest['description'] ?? 'No description available';
                $configUrl = $manifest['config_url'] ?? '';
    
                echo '<tr>';
                echo '<td>' . htmlspecialchars($name) . '</td>';
                echo '<td>' . htmlspecialchars($description) . '</td>';
                echo '<td>';
                if (!empty($configUrl)) {
                    echo '<a href="' . htmlspecialchars($configUrl) . '" class="configure-plugin-btn">Configure Plugin</a>';
                } else {
                    echo 'No Plugin Page';
                }
                echo '</td>';
                // Add delete button conditionally
                echo '<td>';
                if ($folder !== 'herika_heal') {
                    echo '<form method="post" style="margin:0;" onsubmit="return confirm(\'Are you sure you want to delete the ' . htmlspecialchars($name) . ' plugin?\');">
                            <input type="hidden" name="delete_plugin" value="' . htmlspecialchars($folder) . '">
                            <button type="submit" class="delete-plugin-btn">Delete Plugin</button>
                          </form>';
                } else {
                    echo 'Cannot be deleted';
                }
                echo '</td>';
                echo '</tr>';
            } else {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($folder) . '</td>';
                echo '<td colspan="2">No manifest.json found</td>';
                // Add delete button conditionally
                echo '<td>';
                if ($folder !== 'herika_heal') {
                    echo '<form method="post" style="margin:0;" onsubmit="return confirm(\'Are you sure you want to delete the ' . htmlspecialchars($folder) . ' plugin?\');">
                            <input type="hidden" name="delete_plugin" value="' . htmlspecialchars($folder) . '">
                            <button type="submit" class="delete-plugin-btn">Delete Plugin</button>
                          </form>';
                } else {
                    echo 'Protected';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    
        // Add the "CHIM Plugins" title
        echo '<br>';
        echo '<div style="display: flex; align-items: center; margin-top: 20px;">';
        echo '<h2 style="margin-right: 10px;">CHIM Plugins Repository</h2>';
        echo '</div>';
    
        // Add basic information paragraph
        echo '<p>Here you can download extensions that add extra AI features to CHIM.</p>';
        echo '<ul>';
        echo '<li>Download a plugin by clicking the <b>[Download PLUGIN NAME]</b> button.</li>';
        echo '<li>Click the associated <b>[Mod Download]</b> button for the plugin. Install it with your mod manager of choice.</li>';
        echo '<li>If the plugin allows it, click the <b>[Configure Plugin]</b> button to make any changes.</li>';
        echo '<li>Start up the game and open the MCM menu if present to make any further changes.</li>';
        echo '<li>Then you are good to go!</li>';
        echo '</ul>';
  
        // Check if the MinAI plugin is already installed
        $minaiInstalled = is_dir($pluginFoldersRoot . 'minai_plugin');
    
        // Display the MinAI plugin download section in a table
        echo '<table border="1">';
        echo '<tr>
                <th>Plugin</th>
                <th>Description</th>
                <th>Mod Page</th>
                <th>Skyrim Mod Download</th>
            </tr>';
        echo '<tr>';
    
        // Download cell
        echo '<td style="text-align: center;">';
        if ($minaiInstalled) {
            // Show that plugin is already installed
            echo '<button class="open-overlay-btn" disabled>MinAI Installed</button>';
        } else {
            echo '<form method="post" style="margin:0;">
                    <input type="hidden" name="download_minai" value="1">
                    <button type="submit" class="open-overlay-btn">Download MinAI</button>
                  </form>';
        }
        echo '</td>';
    
        // Description cell
        echo '<td>Adds more AI actions, Sapience, and NSFW integrations.</td>';
    
        // More Info cell with button
        echo '<td><a href="https://github.com/MinLL/MinAI" target="_blank" class="configure-plugin-btn">More Info</a></td>';
    
        // Skyrim Mod Download cell with button
        echo '<td><a href="https://github.com/MinLL/MinAI/releases" target="_blank" class="configure-plugin-btn">Mod Download</a></td>';
    
        echo '</tr></table>';
    }
    echo '<br>';
    echo '<p>If you are a mod developer you can make your own plugin quite easily!</p>';
    echo '<p>Making a plugin will allow your mod events and actions to be seen by the AI NPCs.</p>';
    echo '<p>You can even add scripted events that can be triggered by the AI.</p>';
    echo '';
    echo '<p>The herika_heal plugin provides an example of how our API works.</p>';
    echo '<button type="button" class="open-overlay-btn" onclick="window.location.href=\'herika_heal_download.php\'">Download herika_heal</button>';
        
    if (isset($_POST['download_minai'])) {
        // URL of the MinAI stable branch zip file
        $zipUrl = 'https://github.com/MinLL/MinAI/archive/refs/heads/stable.zip';
        $zipFile = tempnam(sys_get_temp_dir(), 'minai_') . '.zip';
    
        // Download the zip file
        $zipContent = file_get_contents($zipUrl);
        if ($zipContent === false) {
            $errorMessage = 'Failed to download the zip file.';
        } else {
            file_put_contents($zipFile, $zipContent);
    
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                $destination = __DIR__ . '/../ext/';
                $extracted = $zip->extractTo($destination);
                if ($extracted) {
                    // Move the minai_plugin folder from MinAI-stable to ext
                    $sourcePath = $destination . 'MinAI-stable/minai_plugin';
                    $targetPath = $destination . 'minai_plugin';
    
                    // Remove existing minai_plugin directory if it exists
                    if (is_dir($targetPath)) {
                        rrmdir($targetPath);
                    }
    
                    // Move the plugin directory
                    rename($sourcePath, $targetPath);
    
                    // Clean up the extracted files
                    rrmdir($destination . 'MinAI-stable');
    
                    $zip->close();
                    unlink($zipFile);
    
                    // Recursively set permissions to 0777 and change owner and group to 'dwemer'
                    function chmod_chown_chgrp_r($path, $filemode, $user, $group) {
                        if (is_dir($path)) {
                            // Change permissions, owner, and group for the directory
                            if (!chmod($path, $filemode)) {
                                echo "Failed to chmod directory $path<br>";
                            }
                            if (!chown($path, $user)) {
                                echo "Failed to chown directory $path<br>";
                            }
                            if (!chgrp($path, $group)) {
                                echo "Failed to chgrp directory $path<br>";
                            }
    
                            // Process contents of the directory
                            $objects = scandir($path);
                            foreach ($objects as $file) {
                                if ($file != '.' && $file != '..') {
                                    $fullpath = $path . '/' . $file;
                                    chmod_chown_chgrp_r($fullpath, $filemode, $user, $group);
                                }
                            }
                        } else {
                            // Change permissions, owner, and group for the file
                            if (!chmod($path, $filemode)) {
                                echo "Failed to chmod file $path<br>";
                            }
                            if (!chown($path, $user)) {
                                echo "Failed to chown file $path<br>";
                            }
                            if (!chgrp($path, $group)) {
                                echo "Failed to chgrp file $path<br>";
                            }
                        }
                    }
    
                    // Set permissions and ownership
                    chmod_chown_chgrp_r($targetPath, 0777, 'dwemer', 'www-data');
    
                    $successMessage = 'MinAI plugin downloaded and installed successfully.';
                } else {
                    $zip->close();
                    unlink($zipFile);
                    $errorMessage = 'Failed to extract the zip file.';
                }
            } else {
                unlink($zipFile);
                $errorMessage = 'Failed to open the zip file.';
            }
        }
    
        // Store messages in session and redirect to refresh the page
        if (!empty($errorMessage)) {
            $_SESSION['errorMessage'] = $errorMessage;
        } elseif (!empty($successMessage)) {
            $_SESSION['successMessage'] = $successMessage;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    
    // Handle the Delete Plugin button click
    if (isset($_POST['delete_plugin'])) {
        $pluginToDelete = $_POST['delete_plugin'];
        $pluginPath = __DIR__ . '/../ext/' . $pluginToDelete;
    
        if (is_dir($pluginPath)) {
            rrmdir($pluginPath);
            $successMessage = "Plugin '$pluginToDelete' has been deleted.";
        } else {
            $errorMessage = "Plugin '$pluginToDelete' not found.";
        }
    
        // Store messages in session and redirect to refresh the page
        if (!empty($errorMessage)) {
            $_SESSION['errorMessage'] = $errorMessage;
        } elseif (!empty($successMessage)) {
            $_SESSION['successMessage'] = $successMessage;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Handle the Refresh Plugins button click
    if (isset($_POST['refresh_plugins'])) {
        // Redirect back to the same page
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Handle messages from the session
    if (isset($_SESSION['errorMessage'])) {
        $errorMessage = $_SESSION['errorMessage'];
        unset($_SESSION['errorMessage']);
    }
    
    if (isset($_SESSION['successMessage'])) {
        $successMessage = $_SESSION['successMessage'];
        unset($_SESSION['successMessage']);
    }
    
    // Recursive function to delete a directory and its contents
    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    $path = $dir . DIRECTORY_SEPARATOR . $object;
                    if (is_dir($path)) {
                        rrmdir($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
    ?>
</div> <!-- close main container -->
<?php

include("tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = "CHIM";
$title .= (($_GET["autorefresh"]) ? " (autorefreshes every 5 secs)" : "");
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;

?>
