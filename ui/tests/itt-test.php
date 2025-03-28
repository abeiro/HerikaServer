<?php
session_start();
error_reporting(E_ALL);

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

$TITLE = "🖼️CHIM - ITT Test - CHIM Server";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "$DBDRIVER.class.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php"); // API KEY must be there
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

$db=new sql();

require_once($enginePath . "itt" . DIRECTORY_SEPARATOR . "itt-{$GLOBALS['ITTFUNCTION']}.php");

$start_time = time();

$sampleImagePath = '../../debug/data/sample.jpg';
$description = itt("$enginePath/debug/data/sample.jpg", '');
$end_time = time();

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $TITLE; ?></title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.ico">
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

    <div class="section clearfix">
        <div class="image-container">
            <h3>Sample Image Sent</h3>
            <img src="<?php echo htmlspecialchars($sampleImagePath); ?>" alt="Sample Image">
        </div>

        <div class="description-container">
            <h3>ITT Output</h3>
            <div class="response">
                <?php echo nl2br(htmlspecialchars($description)); ?>
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
