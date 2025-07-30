<?php
session_start();
error_reporting(E_ALL);

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

$TITLE = "🎤CHIM - STT Test - CHIM Server";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "$DBDRIVER.class.php");

// Include the appropriate STT function based on configuration
if ($STTFUNCTION == "azure") {
    require_once($enginePath . "stt" . DIRECTORY_SEPARATOR . "stt-azure.php");
} else if ($STTFUNCTION == "whisper") {
    require_once($enginePath . "stt" . DIRECTORY_SEPARATOR . "stt-whisper.php");
} else if ($STTFUNCTION == "localwhisper") {
    require_once($enginePath . "stt" . DIRECTORY_SEPARATOR . "stt-localwhisper.php");
} else if ($STTFUNCTION == "deepgram") {
    require_once($enginePath . "stt" . DIRECTORY_SEPARATOR . "stt-deepgram.php");
} else {
    require_once($enginePath . "stt" . DIRECTORY_SEPARATOR . "stt-none.php");
    //$error_message = "Unknown STT function: $STTFUNCTION";
}

ini_set('display_errors', 1);

$transcription = '';
$error_message = '';

if (php_sapi_name() != "cli") {
    // Not running from CLI, so display as web page
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

            pre {
                background-color: #3a3a3a;
                padding: 15px;
                border: 1px solid #555555;
                overflow: auto;
                color: #f8f9fa;
                border-radius: 5px;
            }

            .response {
                font-weight: bold;
                color: #f8f9fa;
                background-color: #3a3a3a;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #555555;
                margin-top: 10px;
            }

            .section {
                margin-bottom: 30px;
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

            .error-message {
                color: #dc3545;
                font-weight: bold;
            }

            .info-message {
                color: #17a2b8;
                font-weight: bold;
            }

            .accuracy {
                color: #ffc107;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
    <main>
    <div class="indent5">
        <h1>🎤CHIM Speech-to-Text Test</h1>

        <div class="section">
        <?php
        echo '<div class="status"><span class="label">Sending test audio file...</span></div>';

        $testFile = $enginePath . "debug" . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "test.wav";
        echo '<div class="message">Sending <code>' . htmlspecialchars($testFile) . '</code></div>';

        // Define the expected transcription
        $expected_transcription = "The Great War is long past. It's time the Empire and the Aldmeri Dominion put aside their differences. Prosperity is good for everyone.";
        echo '<div class="message">Expected result: "<em>' . htmlspecialchars($expected_transcription) . '</em>" <button onclick="playAudio()" style="background: none; color: #4CAF50; border: none; padding: 0; cursor: pointer; margin-left: 10px; font-size: 20px;">▶</button></div>';
        echo '<audio id="testAudio" preload="auto"><source src="../../debug/data/test.wav" type="audio/wav"></audio>';
        echo '<script>
            function playAudio() {
                var audio = document.getElementById("testAudio");
                audio.currentTime = 0;
                audio.play();
            }
        </script>';

        echo '<div class="status"><span class="label">Obtaining transcription from STT service...</span></div>';

        $transcription = stt($testFile);

        if (!empty($transcription)) {
            echo '<div class="status"><span class="label ok">Transcription Successful!</span></div>';
            echo '<div class="response">Output: ' . nl2br(htmlspecialchars($transcription)) . '</div>';            

            // Calculate similarity percentage using levenshtein
            $lev_distance = levenshtein(strtolower($expected_transcription), strtolower($transcription));
            $max_len = max(strlen($expected_transcription), strlen($transcription));
            if ($max_len > 0) {
                $similarity = (1 - ($lev_distance / $max_len)) * 100;
            } else {
                $similarity = 0;
            }

            // Display similarity percentage
            echo '<div class="message accuracy">Similarity: ' . number_format($similarity, 2) . '%</div>';
        } else {
            if ($STTFUNCTION == "none") {
                echo '<div class="status"><span class="label error">Transcription not possible</span></div>';
                echo '<div class="error-message">Transcription service is not selected.</div>';
            } else {
                echo '<div class="status"><span class="label error">Transcription Failed</span></div>';
                echo '<div class="error-message">Transcription failed or returned an empty result.</div>';
            }
        }

        echo '<div class="message">Service used: <strong>' . htmlspecialchars($GLOBALS['STTFUNCTION']) . '</strong></div>';

        echo '</div>'; // End of section

        echo '<div class="section">';
        echo '<div class="divider"></div>';
        echo '<div style="font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                <span style="font-weight: bold; color: yellow; font-size: 18px;">
                    IF TRANSCRIPTION IS SUCCESSFUL, THEN THE STT SERVICE WORKS!
                </span><br>
                <b>If you are still having issues in-game, then check that your microphone is set as default in your system settings.</b>
                <br>
                <b>If that does not work then try the Chrome Free Speech-to-Text under Configuration.</b>
            </div>'; 
        echo '</div>';
        ?>
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
    <?php
} else {
    // Running from CLI
    if (!isset($argv[1])) {
        echo "Sending " . __DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "test.wav" . PHP_EOL;
        $expected_transcription = "The Great War is long past. It's time the Empire and the Aldmeri Dominion put aside their differences. Prosperity is good for everyone.";
        echo "Expected result: '" . $expected_transcription . "'" . PHP_EOL;
        echo "Obtaining transcription from STT service.... ";

        $transcription = stt(__DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "test.wav");
        echo $transcription . PHP_EOL;
        echo "Service used: ";
        print_r($GLOBALS["STTFUNCTION"] . PHP_EOL);

        // Calculate similarity percentage using levenshtein
        $lev_distance = levenshtein(strtolower($expected_transcription), strtolower($transcription));
        $max_len = max(strlen($expected_transcription), strlen($transcription));
        if ($max_len > 0) {
            $similarity = (1 - ($lev_distance / $max_len)) * 100;
        } else {
            $similarity = 0;
        }

        echo "Similarity: " . number_format($similarity, 2) . "%" . PHP_EOL;

        echo PHP_EOL;
        echo "IF EXPECTED RESULT AND TRANSCRIPT FROM STT ARE THE SAME, THEN STT WORKS!" . PHP_EOL;
        echo "If you are still having issues in-game, then check that your microphone is set as default in your system settings." . PHP_EOL;
    } else {
        echo stt($argv[1]) . PHP_EOL;
    }
}
?>
