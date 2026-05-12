<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

// Determine web root (match other pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// DB and helpers
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "misc_ui_functions.php");

$db = $GLOBALS["db"];

$TITLE = "CHIM - Response Queue";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");

$isEmbedded = (isset($_GET['embed']) && $_GET['embed']);
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 40px; padding-bottom: 40px; padding-left: 10px; }
footer { display: <?php echo $isEmbedded? 'none' : 'block'; ?>; }
.btn-base { cursor:pointer; padding:6px 10px; border-radius:4px; border:1px solid #666; }
.btn-primary { background:#204e7a; color:#fff; }
.btn-danger { background:#b00020; color:#fff; }
.pagination-buttons { margin: 10px 0; }
.response-log-actions { margin: 15px 0; }
</style>

<main>
    <h1 class="my-2">Response Log</h1>
    <div class="response-log-actions">
        <button onclick="if(confirm('This will clear all the entries in the Response Log. ARE YOU SURE?')) window.location.href='?cleanlog=true'" class="btn-base btn-danger" style="margin-right: 10px;">Clean Response Log</button>
        <button onclick="window.open('?export=log', '_blank')" class="btn-base btn-primary">Export Response Log</button>
    </div>
    <?php
    if (isset($_GET["cleanlog"])) { $db->delete("log", "true"); }

    $limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 50;
    $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
    $offset = ($page - 1) * $limit;

    $results = $db->fetchAll(
        "SELECT A.*, ROWID 
         FROM log a 
         ORDER BY localts DESC, rowid DESC 
         LIMIT $limit OFFSET $offset"
    );

    function getTimeColor($time) {
        if ($time <= 2) return "#88cc88"; // green
        if ($time <= 5) return "#ffff00"; // yellow
        if ($time <= 8) return "#ffa500"; // orange
        return "#ff6666"; // red
    }

    $columnHeaders = [
        'localts' => 'Time (UTC)',
        'response' => 'AI Response',
        'prompt' => 'Prompt',
        'url' => 'HTTP Request'
    ];

    $mappedResults = array_map(function ($row) use ($columnHeaders) {
        $mappedRow = [];
        foreach ($row as $key => $value) {
            if ($key === 'prompt') {
                $mappedRow[$columnHeaders[$key] ?? $key] = '<div class="full-content">' . nl2br(htmlspecialchars($value)) . '</div>';
            } else if ($key === 'response') {
                $mappedRow[$columnHeaders[$key] ?? $key] = '<div class="full-content">' . nl2br(htmlspecialchars($value)) . '</div>';
            } else if ($key === 'localts' && !empty($value)) {
                $dt = new DateTime('@'.$value);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $mappedRow[$columnHeaders[$key]] = $dt->format('d-m-Y H:i:s');
            } else if ($key === 'url') {
                if (strpos($row['response'], 'Array') === 0) {
                    $mappedRow[$columnHeaders[$key] ?? $key] = preg_replace('/ in \d+\.?\d* secs$/', '', $value);
                } else if (strpos($value, '[AI secs]') !== false) {
                    $pattern = '/\[AI secs\]\s+([\d.]+)\s+\[TTS secs\]\s+([\d.]+)/';
                    if (preg_match($pattern, $value, $matches)) {
                        $aiTime = floatval($matches[1]);
                        $totalTtsTime = floatval($matches[2]);
                        $actualTtsTime = $totalTtsTime - $aiTime;
                        $aiTimeFormatted = number_format($aiTime, 2);
                        $ttsTimeFormatted = number_format($actualTtsTime, 2);
                        $aiColor = getTimeColor($aiTime);
                        $ttsColor = getTimeColor($actualTtsTime);
                        $totalColor = getTimeColor($totalTtsTime);
                        $baseText = substr($value, 0, strpos($value, '[AI secs]'));
                        $mappedRow[$columnHeaders[$key] ?? $key] = 
                            $baseText . 
                            "<br>[LLM] <span style='color: " . $aiColor . "'>" . $aiTimeFormatted . "</span>" .
                            " [TTS] <span style='color: " . $ttsColor . "'>" . $ttsTimeFormatted . "</span>" .
                            " [Total]: <span style='color: " . $totalColor . "'>" . $totalTtsTime . "</span>";
                    } else {
                        $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                    }
                } else {
                    $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                }
            } else {
                $mappedRow[$columnHeaders[$key] ?? $key] = $value;
            }
        }
        return $mappedRow;
    }, $results);

    $prevPage = max(1, $page - 1);
    $nextPage = $page + 1;
    echo "<div class='pagination-buttons'>";
    if ($page > 1) {
        echo "<button onclick=\"window.location.href='?page=$prevPage&limit=$limit'\" class='btn-base btn-primary'>Previous</button> ";
    }
    echo "<button onclick=\"window.location.href='?page=$nextPage&limit=$limit'\" class='btn-base btn-primary'>Next</button>";
    echo "</div>";

    print_array_as_table($mappedResults);
    ?>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


