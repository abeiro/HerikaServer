<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."online_translation.php");

$TITLE = "🔊CHIM - TTS Test - CHIM Server";

ob_start();

include("../tmpl/head.html");

$debugPaneLink = false;
include("../tmpl/navbar.php");

$startTime = microtime(true);

$localPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$enginePath = $localPath;

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "$DBDRIVER.class.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php"); // API KEY must be there
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

requireFilesRecursively($enginePath . "ext" . DIRECTORY_SEPARATOR, "globals.php");

if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
}

error_reporting(E_ALL);

$testString = "In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";

if (isset($_POST["customstring"]) && $_POST["customstring"]) {
    $testString = $_POST["customstring"];
}

$db = new sql();

require_once($enginePath . "prompt.includes.php");

$GLOBALS["AVOID_TTS_CACHE"] = true;
$DEBUG_DATA = [];
$cleanString = $testString;

Translation::translate($cleanString);
Translation::$sentences = [Translation::$response];

$melotts_pronunciation_file = $enginePath . "tts" . DIRECTORY_SEPARATOR ."tts-melotts_pronunciation.php";
$b_conf_melotts = file_exists($melotts_pronunciation_file);
if ($b_conf_melotts) {
    include_once($melotts_pronunciation_file);
}

$b_melotts = (strtolower($TTSFUNCTION) == 'melotts');
if ($b_melotts) {
    if ($b_conf_melotts && (pronunciation_adjust_enabled())) {
        $testString .= $s_pronunciation_test;
        $cleanString = adjust_pronunciation($testString);
    }
}

$soundFile = returnLines([$cleanString], false); 

$s_sample = $db->escape(trim(substr($cleanString, 14, 92)));
if (strlen($s_sample) > 48) { 
    $s_time = (time() - 180);
    $s_SQL = "DELETE FROM eventlog WHERE (data LIKE '%" .$s_sample. "%') AND (type in ('chat','prechat')) AND (localts > " .$s_time. ") ";
    $db->query($s_SQL);
    $s_SQL = "DELETE FROM log WHERE (response LIKE '%" .$db->escape($s_sample). "%') AND (localts > " .$s_time. ") ";
    $db->query($s_SQL);
}

$db->close();
unset($db);

$file = basename($GLOBALS["TRACK"]["FILES_GENERATED"][0]);
$ts = time();

if (Translation::isTextEnabled()) {
    $testString = Translation::$response;;
}

?>

<link rel="stylesheet" href="../css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 160px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
        padding-left: 10px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px; /* Reduced footer height */
        background: #031633;
        z-index: 100;
    }

    /* Custom styles for TTS test */
    textarea {
        background-color:rgb(255, 255, 255);
        border: 1px solid #555555;
        color:rgb(0, 0, 0);
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    audio {
        width: 100%;
        max-width: 500px;
        margin: 20px 0;
        color:rgb(255, 255, 255);
    }
</style>

<main>
    <div class="indent5">
        <h1>🔊CHIM Text-to-Speech Test</h1>

        <div class="section" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
            <?php
            if ($file) {
                echo '<h2>Current Speaker: <b>' . htmlspecialchars($GLOBALS['CURRENT_PROFILE_CHAR']) . '</b></h2>';
                echo '<h3><i>Output: ' . htmlspecialchars($testString) . '</i></h3>';
                if ($b_melotts && $b_conf_melotts && pronunciation_debug_enabled()) 
                    echo '<h3>' . htmlspecialchars($cleanString) . '</h3>'; 
                echo '<audio controls>';
                echo '<source src="../../soundcache/' . htmlspecialchars($file) . '?ts=' . htmlspecialchars($ts) . '" type="audio/wav">';
                echo 'Your browser does not support the audio element.';
                echo '</audio>';
                echo '<p>Debug Info:<pre>'.print_r($GLOBALS["DEBUG_DATA"],true).'</pre></p>';
            } else {
                echo '<div class="error-message"><strong>Error:</strong><br/>';
                $errorFilePath = $enginePath . 'soundcache' . DIRECTORY_SEPARATOR . md5(trim($testString)) . '.err';
                if (file_exists($errorFilePath)) {
                    echo nl2br(htmlspecialchars(file_get_contents($errorFilePath)));
                } else {
                    echo 'An unknown error occurred.';
                }
                echo '</div>';
            }
            ?>

            <form action="" method="POST">
                <textarea name="customstring" placeholder="Write your own text" style="width:100%; max-width:500px; height:100px;"><?=($_POST["customstring"] ?? "")?></textarea><br/>
                <input type="submit" class="action-button edit" value="Test TTS" />
                <?php if ($file): ?>
                    <a href="../../soundcache/<?php echo htmlspecialchars($file); ?>?ts=<?php echo htmlspecialchars($ts); ?>" 
                       download="<?php echo htmlspecialchars($GLOBALS['CURRENT_PROFILE_CHAR'] . '_' . substr(preg_replace('/[^a-zA-Z0-9]+/', '_', $testString), 0, 50) . '.wav'); ?>" 
                       class="action-button download-csv" 
                       style="text-decoration: none;">
                        Download WAV
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="status" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
            <span class="label" style="font-weight: bold; color: yellow; padding: 5px; display: inline-block;">
                TROUBLESHOOTING FIXES
            </span>
            <ul class="error-list" style="margin-top: 15px; list-style-type: none; padding-left: 0;">
                <li style="margin-bottom: 20px;">
                    <strong>500 = Internal Server Error</strong>
                    <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                        <li>The audio file for the voice ID does not exist</li>
                        <li>CHIM XTTS = Sync Voices in XTTS Management page</li>
                        <li>MeloTTS= Use one of the 
                            <a href="https://dwemerdynamics.hostwiki.io/en/TTS-Options#melotts-voice-ids" target="_blank">approved voice IDs</a>
                        </li>
                        <li>xVASynth = Make sure you have the voice ID installed</li>
                    </ul>
                </li>
                <li style="margin-bottom: 20px;">
                    <strong>404 = Not Found</strong>
                    <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                        <li>The URL is not valid for your TTS server</li>
                        <li>CHIM XTTS = If locally installed make sure it is http://127.0.0.1:8020. If its on the cloud verify the URL from the cloud provider </li>
                        <li>MeloTTS= Make sure it is http://127.0.0.1:8084</li>
                        <li>xVASynth = Make sure you have the URL pointed to your PC's IP address. 
                            <a href="https://dwemerdynamics.hostwiki.io/en/TTS-Options" target="_blank">Read this guide.</a>
                        </li>
                        <li>Using a 2nd PC = Make sure your local network and firewall is not blocking the connections. 
                            <a href="https://dwemerdynamics.hostwiki.io/en/2nd-PC-Guide" target="_blank">Read this guide.</a>
                        </li>
                    </ul>
                </li>
                <li style="margin-bottom: 20px;">
                    <strong>If it's not the voice you expected</strong>
                    <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                        <li>Change the profile (Blue Button) in the top left on the CHIM server page. Select the NPC you want to hear</li>
                        <li>If their voice is still wrong, check their voiceID field and the TTSFUNCTION you have selected</li>
                    </ul>
                </li>
                <li style="margin-bottom: 20px;">
                    <strong>Error: An Unknown error occurred</strong>
                    <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                        <li>Make sure the TTS service is installed and running correctly.</li>
                    </ul>
                </li>
                <li style="margin-bottom: 20px;">
                    <strong>The audio test works here but you hear nothing ingame</strong>
                    <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                        <li>Make sure AIAgent.ini is in SKSE/Plugins </li>
                        <li>Make sure you Windows "System Sounds" is not muted. All the AI dialogue audio is actually played through here. </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</main>

<?php
include("../tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
