<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

$TITLE = "🖼️CHIM - ITT Test - CHIM Server";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => true,
]);
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

$db=$GLOBALS['db'] ?? new sql();
$GLOBALS['db'] = $db;

// Load driver file only if valid; fallback to openrouter for preview
$activeItt = (string)($GLOBALS['ITTFUNCTION'] ?? '');
if ($activeItt === '' || strtolower($activeItt) === 'none') {
    $activeItt = 'openrouter';
}
$driverPath = $enginePath . "itt" . DIRECTORY_SEPARATOR . "itt-{$activeItt}.php";
if (file_exists($driverPath)) {
    require_once($driverPath);
}

$start_time = time();

$sampleImagePath = '../../debug/data/sample.jpg';
$driverFile = $driverPath;
$description = '';
try {
    if ($activeItt === '' || !file_exists($driverFile)) {
        $description = '';
    } else {
        $description = itt("$enginePath/debug/data/sample.jpg", '');
    }
} catch (Throwable $e) {
    $description = "Error: ".$e->getMessage();
}
$end_time = time();

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $TITLE; ?></title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.ico">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        /* Modal-friendly spacing (no navbar) */
        main { padding: 20px 10px 20px 10px; }
        footer { display: none; }

        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c;
            color: #f8f9fa;
            padding: 20px;
        }

        h1, h2, h3, .header {
            color: #ffffff;
        }

        .status {
            margin-bottom: 15px;
            background-color: #3a3a3a;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
        }

        .status .label {
            font-weight: bold;
            color: #f8f9fa;
        }

        .status .ok {
            color: #28a745;
        }

        .status .error {
            color: #dc3545;
        }

        .section {
            margin-bottom: 30px;
            overflow: auto;
        }

        .header {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .divider {
            border-bottom: 1px solid #555555;
            margin: 20px 0;
        }

        .message {
            background-color: #444444;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            max-width: 800px;
            margin-bottom: 20px;
            color: #f8f9fa;
        }

        .message p {
            margin: 0;
        }

        .image-container, .description-container {
            width: 48%;
            float: left;
            margin-right: 2%;
        }

        .description-container {
            margin-right: 0;
        }

        .image-container img {
            max-width: 100%;
            height: auto;
            border: 1px solid #555555;
            border-radius: 5px;
        }

        .response {
            background-color: #3a3a3a;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #555555;
            color: #f8f9fa;
            font-size: 16px;
            line-height: 1.5;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
<main>
<div class="indent5">
    <h1>🖼️CHIM Image-to-Text Test</h1>
    <div class="status"><span class="label">Active ITT:</span> <span class="ok"><?php echo htmlspecialchars($activeItt ?: 'none'); ?></span></div>
    <div class="status"><span class="label">Driver file:</span> <span class="ok"><?php echo htmlspecialchars($driverFile); ?></span></div>

    <div class="section clearfix">
        <div class="image-container">
            <h3>Sample Image Sent</h3>
            <img src="<?php echo htmlspecialchars($sampleImagePath); ?>" alt="Sample Image">
        </div>

        <div class="description-container">
            <h3>ITT Output</h3>
            <div class="response">
                <?php echo ($description !== '') ? nl2br(htmlspecialchars($description)) : '<span class="error">No output (check API key, model, or server logs)</span>'; ?>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="message">
            <?php
            $timeTaken = $end_time - $start_time;
            echo "<p><strong>Time taken for ITT call:</strong> {$timeTaken} seconds</p>";
            echo "<p><strong>Service used:</strong> {$GLOBALS['ITTFUNCTION']}</p>";
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
</body>
</html>
