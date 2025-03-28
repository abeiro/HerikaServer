<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

$TITLE = "🔧CHIM - AI/LLM Test";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
?>
<title>🔧CHIM - AI/LLM Test </title>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
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
</style>

<main>
    <div class="indent5">
        <h1>🔧CHIM AI/LLM Test</h1>

        <?php
        $enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        echo '<div class="section" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
        echo '<div class="status"><span class="label">Checking <code>conf.php</code>... </span>';

        if (!file_exists($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php")) {
            echo '<span class="error">Not found</span></div>';
        } else {
            echo '<span class="ok">Found</span></div>';
        }

        echo '</div>';

        echo '<div class="section" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
        echo '<div class="status"><span class="label">Initializing... </span>';

        require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
        require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
        require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php");
        require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");

        if (isset($_SESSION["PROFILE"])) {
            $overrides = [
                "BOOK_EVENT_ALWAYS_NARRATOR" => $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"],
                "MINIME_T5" => $GLOBALS["MINIME_T5"],
                "STTFUNCTION" => $GLOBALS["STTFUNCTION"]
            ];

            require_once($_SESSION["PROFILE"]);

            foreach ($overrides as $key => $value) {
                $GLOBALS[$key] = $overrides[$key];
            }
        } else {
            $GLOBALS["USING_DEFAULT_PROFILE"] = true;
        }

        $GLOBALS["active_profile"] = md5($GLOBALS["HERIKA_NAME"]);
        $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
        $FEATURES["MEMORY_EMBEDDING"]["ENABLED"] = false;

        echo '<span class="ok">Done</span></div>';
        echo '</div>';

        echo '<div class="section" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
        echo '<div class="status"><span class="label">Opening database connection... </span>';

        $db = new sql();
        if (!$db) {
            echo '<span class="error">Failed</span></div>';
        } else {
            echo '<span class="ok">Connected</span></div>';
        }

        echo '</div>';

        echo '<div class="section" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
        echo '<div class="status"><span class="label">Processing request...</span></div>';

        echo '<pre>';

        $FUNCTIONS_ARE_ENABLED = true;
        if ($FUNCTIONS_ARE_ENABLED) {
            $GLOBALS["TEMPLATE_DIALOG"] = "";
            $FUNCTION_PARM_MOVETO = [$GLOBALS["PLAYER_NAME"]];
            $FUNCTION_PARM_INSPECT = [$GLOBALS["PLAYER_NAME"], "monster"];

            require_once($enginePath . "prompts" . DIRECTORY_SEPARATOR . "command_prompt.php");
            require_once($enginePath . "functions" . DIRECTORY_SEPARATOR . "functions.php");
        }

        $gameRequest = ["inputtext"];

        if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS['CURRENT_CONNECTOR']}.php")) {
            die("{$GLOBALS['HERIKA_NAME']}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector." . PHP_EOL);
        } else {
            require($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS['CURRENT_CONNECTOR']}.php");

            $head = [
                ['role' => 'system', 'content' => strtr($GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"], ["#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"]])]
            ];
            $prompt = [
                ['role' => 'user', 'content' => "Hey, {$GLOBALS['HERIKA_NAME']}, attack that monster!!"]
            ];
            $contextData = array_merge($head, $prompt);

            $connectionHandler = new $GLOBALS["CURRENT_CONNECTOR"];
            $startTimeTrans = microtime(true);
            $connectionHandler->open($contextData, []);

            $buffer = "";
            $totalBuffer = "";
            $breakFlag = false;

            while (true) {
                if ($breakFlag) {
                    break;
                }

                $buffer .= $connectionHandler->process();
                $totalBuffer .= $buffer;

                if ($connectionHandler->isDone()) {
                    $breakFlag = true;
                }
            }

            $connectionHandler->close();
            $endTimeTrans = microtime(true)-$startTimeTrans;

            $actions = $connectionHandler->processActions();
            if (is_array($actions) && count($actions) > 0) {
                $GLOBALS["DEBUG_DATA"]["response"][] = $actions;
                echo implode("\r\n", $actions);
            }

            print_r($GLOBALS["DEBUG_DATA"]);
            if (isset($GLOBALS["ALREADY_SENT_BUFFER"])) {
                print_r($GLOBALS["ALREADY_SENT_BUFFER"]);
            }
        }

        echo '</pre>';
        echo '</div>';

        echo '<div class="section" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
        echo '<div class="divider"></div>';
        echo '<div class="status" style="border: 2px solid #ffc107; border-radius: 5px; padding: 15px; margin-bottom: 20px;">
                <span class="label" style="font-weight: bold; color: #ffffff; font-size: 1.5em;">LLM Response:</span>
                <div class="response" style="font-size: 1.2em; color: #ffffff;">' . nl2br(htmlspecialchars($buffer)) . '</div>
                <pre>';
        $endTimeTrans = $endTimeTrans;
        echo "<b>Response time:</b> $endTimeTrans secs. ";

        if ($endTimeTrans < 2) {
            echo "<span style='color: #28a745; font-weight: bold; font-size: 1.2em;'>FAST!</span>"; // Green
        } else if ($endTimeTrans < 5) {
            echo "<span style='color: #007bff; font-weight: bold; font-size: 1.2em;'>GOOD</span>"; // Blue
        } else if ($endTimeTrans < 10) {
            echo "<span style='color: #ffc107; font-weight: bold; font-size: 1.2em;'>NORMAL</span>"; // Yellow
        } else if ($endTimeTrans < 30) {
            echo "<span style='color: #fd7e14; font-weight: bold; font-size: 1.2em;'>SLOW</span>"; // Orange
        } else {
            echo "<span style='color: #dc3545; font-weight: bold; font-size: 1.2em;'>TOO CHIMMING SLOW</span>"; // Red
        }

        echo '</pre>';
        echo '</div>';
        echo '</div>';

        echo '<br>';
        echo '<div class="status" style="background-color: #3a3a3a; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #555555; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                <span class="label" style="font-weight: bold; color: yellow; padding: 5px; display: inline-block;">
                    TROUBLESHOOTING FIXES
                </span>
                <ul class="error-list" style="margin-top: 15px; list-style-type: none; padding-left: 0;">
                    <li style="margin-bottom: 20px;">
                        <strong>401 = Unauthorized</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>Check your API key.</li>
                            <li>Ensure you have enough credits on your account.</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 20px;">
                        <strong>402 = Payment Required</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>Make sure your account has credits.</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 20px;">
                        <strong>403 = Forbidden</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>Your prompt may be flagged for moderation.</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 20px;">
                        <strong>404 = Not Found</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>Check if your connector URL is correct.</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 20px;">
                        <strong>500 = Internal Server Error</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>The server is experiencing issues.</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 20px;">
                        <strong>LLM Response is Empty</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>Ensure your account has credits.</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 20px;">
                        <strong>Response fails in-game</strong>
                        <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                            <li>Check server logs for token limits.</li>
                        </ul>
                    </li>
                </ul>
            </div>';
        echo '</div>';
        ?>
    </div>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
