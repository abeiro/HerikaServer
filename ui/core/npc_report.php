<?php 

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");

$GLOBALS["ENGINE_PATH"]=$enginePath;

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";

// Minimal Markdown -> HTML renderer for bold and bullets
function render_simple_markdown($text){
    $safe = htmlspecialchars($text);
    $lines = preg_split("/\r?\n/", $safe);
    $html = '';
    $inList = false;
    $paragraphLines = [];

    $flushParagraph = function() use (&$paragraphLines, &$html){
        if (!empty($paragraphLines)){
            $p = implode('<br>', $paragraphLines);
            $p = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $p);
            $html .= '<p>'.$p.'</p>';
            $paragraphLines = [];
        }
    };
    $closeList = function() use (&$inList, &$html){
        if ($inList){ $html .= '</ul>'; $inList = false; }
    };

    foreach ($lines as $line){
        $trim = trim($line);
        if ($trim === ''){ $closeList(); $flushParagraph(); continue; }
        if (preg_match('/^\*\s+(.+)/', $trim, $m)){
            $flushParagraph();
            if (!$inList){ $html .= '<ul>'; $inList = true; }
            $item = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $m[1]);
            $html .= '<li>'.$item.'</li>';
        } else {
            if ($inList){ $closeList(); }
            $paragraphLines[] = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $trim);
        }
    }
    $closeList();
    $flushParagraph();
    return $html;
}

// Prepare report
$renderedReport = '';
$pageError = '';
$npcName = '';

$npc = new NpcMaster();
$npcData=$npc->getById($_GET["npcid"]);
if ($npcData && !chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_MEDIUMTERM')) {
    $npcName = $npcData["npc_name"] ?? '';
    $pageError = 'Background & Memory Tasks are turned off in Global Settings.';
} elseif ($npcData) {
    $npcName = $npcData["npc_name"] ?? '';
    $query=
    "SELECT coalesce(npc_static_bio,'')||'\n'||personality as personality, created
    FROM (
    SELECT personality,npc_static_bio,
            created,
            ROW_NUMBER() OVER (PARTITION BY personality ORDER BY created) AS rn
    FROM core_npc_master_history
    WHERE npc_name = '".$GLOBALS["db"]->escape($npcName)."'
    ) AS sub
    WHERE rn = 1
    AND coalesce(npc_static_bio,'')||'\n'||personality is not null
    ORDER BY created";

    
    $hdata=$GLOBALS["db"]->fetchAll($query);

    $connector = new LLMConnector();
    $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);

    $connector->setOldGlobals($currentConnectorData);

    $reportSource=[];
    foreach ($hdata as $row) {
        if ($row["personality"]) {
            $reportSource[]=$row["personality"];
        }
    }

    $COMMAND_PROMPT = '';

    $head = [];
    $prompt = [];
    $head[] = ['role' => 'system', 'content' => "You are a character assistant. Carefully read the evolution of the character’s personality and write a report."];
    $prompt[] = ['role' => 'user', 'content' => implode("\n=====\n",$reportSource)];
    $prompt[] = ['role' => 'user', 'content' => "Write a report showing {$npcName}’s evolution"];
    
    $contextData = array_merge($head, $prompt);

    Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

    $connectionHandler =$connector->getConnector($currentConnectorData);
    $buffer=$connectionHandler->fast_request($contextData,["MAX_TOKENS"=>4096]);
    
    $renderedReport = render_simple_markdown($buffer);
} else {
    $pageError = 'NPC not found.';
}

// Web root detection (match other pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Site chrome
require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$TITLE = "🔎 CHIM - NPC Report";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Match oghma_upload/api_badge page layout and colors */
    main {
        padding-top: 40px;
        padding-bottom: 40px;
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
    }

    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Title styling to match Oghma */
    h1.api-title {
        margin: 0 0 20px 0;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        text-align: center;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    .content-section {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }
    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.4em;
    }
    .report-body { color:#e0e0e0; line-height:1.6; font-size:15px; }
    .report-body p { margin: 10px 0; }
    .report-body ul { margin: 10px 0 10px 22px; padding: 0; }
    .report-body li { margin: 6px 0; }
    .report-body strong { color: #ffb862; }
</style>

<main>
    <h1 class="api-title">NPC Report<?php if ($npcName) { echo ' - '.htmlspecialchars($npcName); } ?></h1>
    <div class="content-grid">
        <div class="content-section">
            <h2>Report</h2>
            <div class="report-body">
                <?php 
                if ($pageError) {
                    echo htmlspecialchars($pageError);
                } else {
                    echo $renderedReport ?: 'No report available.';
                }
                ?>
            </div>
        </div>
    </div>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
