<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

// Determine web root (match other pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// DB and helpers
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "misc_ui_functions.php");

$db = new sql();

$TITLE = "CHIM - Request Logs";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");

$isEmbedded = (isset($_GET['embed']) && $_GET['embed']);
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 40px; padding-bottom: 40px; padding-left: 10px; }
footer { display: <?php echo $isEmbedded? 'none' : 'block'; ?>; }
.response-log-actions { margin: 15px 0; }
.btn-base { cursor:pointer; padding:6px 10px; border-radius:4px; border:1px solid #666; }
.btn-primary { background:#204e7a; color:#fff; }
.btn-danger { background:#b00020; color:#fff; }
.pagination-buttons { margin: 10px 0; }
</style>

<main>
    <h1 class="my-2">Request to LLM Services Log</h1>
    <p>This table shows requests made to LLM services and their responses.</p>
    <?php
    $limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 50;
    $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
    $offset = ($page - 1) * $limit;

    // Ensure modal CSS/JS parity if you decide to add modals later

    $results = $db->fetchAll(
        "SELECT created_at, request, result, usage, url, rowid 
         FROM audit_request 
         ORDER BY created_at DESC 
         LIMIT $limit OFFSET $offset"
    );

    $columnHeaders = [
        'created_at' => 'Time (UTC)',
        'request' => 'Request',
        'result' => 'Result',
        'usage' => 'Usage',
        'rowid' => 'Row ID',
        'url' => 'URL'
    ];

    $mappedResults = array_map(function ($row) use ($columnHeaders) {
        $mappedRow = [];
        foreach ($row as $key => $value) {
            if ($key === 'request') {
                $escapedContent = htmlspecialchars($value, ENT_QUOTES);
                $preview = htmlspecialchars(substr($value, 0, 400)) . (strlen($value) > 400 ? '...' : '');
                $mappedRow[$columnHeaders[$key] ?? $key] = 
                    '<div style="display: flex; align-items: center; gap: 10px;">' .
                    '<span style="flex-grow: 1;">' . $preview . '</span>' .
                    '</div>';
            } else if ($key === 'created_at' && !empty($value)) {
                $dt = new DateTime($value);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $mappedRow[$columnHeaders[$key]] = $dt->format('d-m-Y H:i:s');
            } else if ($key === 'result') {
                $resultColor = (strtoupper(trim($value)) === 'OK') ? '#4CAF50' : '#f44336';
                $mappedRow[$columnHeaders[$key] ?? $key] = '<div class="full-content" style="color: ' . $resultColor . '; font-weight: bold;">' . nl2br(htmlspecialchars($value)) . '</div>';
            } else if ($key === 'usage') {
                $jsonText = is_string($value) ? $value : json_encode($value);
                $preview = htmlspecialchars(substr($jsonText, 0, 400)) . (strlen($jsonText) > 400 ? '...' : '');
                $mappedRow[$columnHeaders[$key] ?? $key] = '<div class="full-content">' . $preview . '</div>';
            } else if ($key === 'url') {
                $mappedRow[$columnHeaders[$key] ?? $key] = htmlspecialchars($value);
            } else {
                $mappedRow[$columnHeaders[$key] ?? $key] = htmlspecialchars($value);
            }
        }
        return $mappedRow;
    }, $results);

    // Pagination
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


