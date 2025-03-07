<?php
// Enable error reporting (for development purposes)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the endpoint for the XTTS API
require_once(__DIR__."/../conf/conf.php");
if (!isset($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]))
    $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] = 'http://127.0.0.1:8020';

// Initialize message variables
$message = '';
$speakersMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["submit"])) {
        $total = count($_FILES['file']['name']);
        for( $i=0 ; $i < $total ; $i++ ) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $message .= '<p>Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                continue;
            }

            // Get the uploaded file details
            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileType = mime_content_type($fileTmpPath);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Directory where you want to save the uploaded file
            $saveDir = __DIR__ . '/../data/voices/';  // Adjust the path if needed

            // Ensure the directory exists
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0777, true);
            }

            // Ensure the file is a .wav file
            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $message .= "<p>Error: Please upload a valid .wav file.</p>";
            } else {
                // Save the file to the specified directory
                $destinationPath = $saveDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $message .= "<p>.wav file has been uploaded to $destinationPath</p>";

                    // Prepare the cURL request
                    $url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] . '/upload_sample';
                    $cfile = new CURLFile($destinationPath, $fileType, $fileName);

                    $postFields = array('wavFile' => $cfile);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'accept: application/json',
                        'Content-Type: multipart/form-data'
                    ));

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    if (curl_errno($ch)) {
                        $message .= '<p>cURL Error: ' . curl_error($ch) . '</p>';
                    } else {
                        if ($httpCode == 200) {
                            $message .= "<p>.wav file has been cached to the CHIM server</p>";
                        } else {
                            $message .= '<p>Response from server (HTTP code ' . $httpCode . '): ' . htmlspecialchars($response) . '</p>';
                        }
                    }
                    curl_close($ch);
                } else {
                    $message .= "<p>Error: File could not be saved to $destinationPath.</p>";
                }
            }
        }
    } elseif (isset($_POST["get_speakers"])) {
        // Prepare the cURL request for getting the speakers list
        $url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] . '/speakers_list';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json'
        ));

        $response = curl_exec($ch);

        // Debug: Check for cURL errors
        if (curl_errno($ch)) {
            $speakersMessage .= '<p>cURL Error: ' . curl_error($ch) . '</p>';
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode == 200) {
                // Decode the JSON response
                $speakersList = json_decode($response, true);

                // Debug: Check for JSON errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $speakersMessage .= '<p>JSON Error: ' . json_last_error_msg() . '</p>';
                } else {
                    // Sort the speakers list alphabetically
                    sort($speakersList);
                    $totalVoices = count($speakersList);

                    // Display the speakers list in a 6-column grid with header
                    $speakersMessage .= '<div class="voice-list-container">';
                    $speakersMessage .= '<div class="voice-list-header">';
                    $speakersMessage .= '<h3 style="color: #fff; margin: 0 0 15px 0;">Available Voices (' . $totalVoices . ' total)</h3>';
                    $speakersMessage .= '</div>';
                    $speakersMessage .= '<div class="voice-grid">';
                    foreach ($speakersList as $speaker) {
                        $displayName = basename($speaker, '.wav');
                        $speakersMessage .= '<div class="voice-item">' . 
                            '<span title="' . htmlspecialchars($speaker) . '">' . htmlspecialchars($displayName) . '</span>' .
                            '<button onclick="copyToClipboard(\'' . htmlspecialchars($displayName) . '\')" ' .
                            'class="copy-btn" title="Copy voice name">⎘</button>' .
                        '</div>';
                    }
                    $speakersMessage .= '</div>';
                    $speakersMessage .= '</div>';
                }
            } else {
                $speakersMessage .= '<p>Error: Received HTTP code ' . $httpCode . '</p>';
                $speakersMessage .= '<p>Response: ' . htmlspecialchars($response) . '</p>';
            }
        }

        curl_close($ch);
    } elseif (isset($_POST["upload_all"])) {
        // Upload all .wav files in ../data/voices
        $saveDir = __DIR__ . '/../data/voices/';
        $files = glob($saveDir . '*.wav');
        $numFiles = count($files);
        $numUploaded = 0;

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $fileType = mime_content_type($filePath);

            // Ensure the file is a .wav file
            if ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav') {
                $message .= "<p>Error: $fileName is not a valid .wav file.</p>";
            } else {
                // Prepare the cURL request
                $url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] . '/upload_sample';
                $cfile = new CURLFile($filePath, $fileType, $fileName);

                $postFields = array('wavFile' => $cfile);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'accept: application/json',
                    'Content-Type: multipart/form-data'
                ));

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    $message .= '<p>cURL Error while uploading ' . htmlspecialchars($fileName) . ': ' . curl_error($ch) . '</p>';
                } else {
                    if ($httpCode == 200) {
                        $numUploaded++;
                        $message .= "<p>$fileName has been uploaded to the XTTS server</p>";
                    } else {
                        $message .= '<p>Error uploading ' . htmlspecialchars($fileName) . ' (HTTP code ' . $httpCode . '): ' . htmlspecialchars($response) . '</p>';
                    }
                }
                curl_close($ch);
            }
        }
        $message .= "<p>$numUploaded out of $numFiles voice files have been uploaded. </p>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>🔊 CHIM - XTTS Voice Management</title>
    <link rel="stylesheet" href="css/management.css">
    <script>
        function showLoadingMessage() {
            document.getElementById('loading-overlay').style.display = 'block';
            animateEllipsis();
        }

        function animateEllipsis() {
            var ellipsis = document.getElementById('ellipsis');
            var dots = 0;
            window.ellipsisInterval = setInterval(function() {
                dots = (dots + 1) % 4;
                var dotStr = '';
                for (var i = 0; i < dots; i++) {
                    dotStr += '.';
                }
                ellipsis.innerHTML = dotStr;
            }, 500);
        }

        function toggleVoiceList() {
            const voiceList = document.getElementById('voiceList');
            const toggleBtn = document.getElementById('toggleVoices');
            if (voiceList.style.display === 'none') {
                voiceList.style.display = 'block';
                toggleBtn.textContent = 'Hide Available Voices';
            } else {
                voiceList.style.display = 'none';
                toggleBtn.textContent = 'Show Available Voices';
            }
        }

        // If we have voice data and it came from a form submission, show the list
        <?php if (!empty($speakersMessage) && isset($_POST['get_speakers'])) { ?>
            document.getElementById('voiceList').style.display = 'block';
            document.getElementById('toggleVoices').textContent = 'Hide Available Voices';
        <?php } ?>

        document.addEventListener('DOMContentLoaded', function() {
            // Get initial voice list
            <?php if (empty($speakersMessage)) { ?>
                document.querySelector('form').submit();
            <?php } ?>
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Visual feedback for the button
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓';
                btn.style.opacity = '1';
                
                // Show toast notification
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                
                // Hide toast and reset button after delay
                setTimeout(() => {
                    toast.classList.remove('show');
                    btn.textContent = originalText;
                    btn.style.opacity = '';
                }, 1500);
            }).catch(function(err) {
                console.error('Failed to copy text: ', err);
            });
        }
    </script>
</head>
<body>

<div id="loading-overlay">
    <p>Syncing voice cache to CHIM XTTS server, this can take a couple minutes. <br><b>Do not refresh the page<span id="ellipsis"></span></b></p>
</div>

<div class="toast-notification" id="toast">
    <span class="check-icon">✓</span>
    <span>Copied to clipboard</span>
</div>

<div class="indent5">
    <h1><alt="Voice" style="vertical-align:bottom;" width="32" height="32">🔊 XTTS Voice Management</h1>
    <p>The <b>XTTS Voice Management</b> system allows you to manage custom voice samples for NPCs using the CHIM XTTS Server.</p>
    <p>This works differently from other TTS services - it requires voice samples to be uploaded and cached on the server.</p>
    <p>For detailed information on how it works, please read our <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.ojs1hcgp0qwl" style="color: yellow;" target="_blank" rel="noopener noreferrer">XTTS Voice Guide</a>.</p>
    <h3><strong>Ensure all voice sample filenames are lowercase and spaces are replaced with underscores (_).</strong></h3>
    <h4>Example: "Mjoll the Lioness" becomes "mjoll_the_lioness.wav"</h4>

    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>

    <h2>Voice Sample Upload</h2>
    <div style="
        background-color: #3a3a3a;
        padding: 15px;
        border-radius: 5px;
        border: 1px solid #4a4a4a;
        max-width: 600px;
    ">
        <form action="xtts_clone.php" method="post" enctype="multipart/form-data" style="
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 0;
            padding: 0;
            background: none;
            border: none;
        ">
            <div>
                <label for="file" style="display: block; margin-bottom: 5px; font-weight: bold;">Select .wav file(s) to upload:</label>
                <input type="file" name="file[]" id="file" accept=".wav" multiple="multiple" required style="
                    width: 100%;
                    padding: 6px;
                    margin-bottom: 10px;
                    border: 1px solid #555555;
                    border-radius: 3px;
                    background-color: #4a4a4a;
                    color: #f8f9fa;
                ">
            </div>
            <div style="display: flex; gap: 10px;">
                <input type="submit" name="submit" value="Upload Voice Sample" class="action-button upload-csv">
            </div>
        </form>
        <p>Voice samples will be cached in the CHIM server and uploaded to the running CHIM XTTS server.</p>
        <p><b>Note: If you are replacing an existing voice, you will need to restart the CHIM XTTS server.</b></p>
        <p>Recommended .wav file specifications:</p>
        <ul>
            <li>Format: WAV (PCM)</li>
            <li>Bit Depth: 16-bit</li>
            <li>Channels: Mono</li>
            <li>Sample Rate: 20500Hz</li>
        </ul>
        <br>
        <h3>Current Voice List</h3>
        <div style="display: flex; gap: 10px;">
            <button onclick="toggleVoiceList()" id="toggleVoices" class="action-button" style="background-color: var(--blue-base);">Show Available Voices</button>
        </div>
        <div id="voiceList" style="display: none; margin-top: 15px;">
            <?php
            // Get the voice list on initial page load
            if (empty($speakersMessage)) {
                $url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] . '/speakers_list';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json'));
                $response = curl_exec($ch);
                
                if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
                    $speakersList = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        sort($speakersList);
                        $totalVoices = count($speakersList);
                        
                        echo '<div class="voice-list-container">';
                        echo '<div class="voice-list-header">';
                        echo '<h3 style="color: #fff; margin: 0 0 15px 0;">Available Voices (' . $totalVoices . ' total)</h3>';
                        echo '</div>';
                        echo '<div class="voice-grid">';
                        foreach ($speakersList as $speaker) {
                            $displayName = basename($speaker, '.wav');
                            echo '<div class="voice-item">' . 
                                '<span title="' . htmlspecialchars($speaker) . '">' . htmlspecialchars($displayName) . '</span>' .
                                '<button onclick="copyToClipboard(\'' . htmlspecialchars($displayName) . '\')" ' .
                                'class="copy-btn" title="Copy voice name">⎘</button>' .
                            '</div>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                }
                curl_close($ch);
            } else {
                echo $speakersMessage;
            }
            ?>
        </div>
        <br>
        <br>
        <h3>Cloud XTTS Sync</h3>
        <form action="xtts_clone.php" method="post" onsubmit="showLoadingMessage();" style="
            border: none;
            padding: 0;
            margin: 0;
            background: none;
        ">
            <p><strong>Only required for online CHIM XTTS instances.</strong></p>
            <p>Sync just needs to be ran ONE TIME after initial setup of a new instance.</p>
            <p>Empty voice cache is acceptable - new NPC voices will be cached automatically.</p>
            <p>For cloud setup instructions, see our <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.jl2x2nswa7az" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cloud XTTS Guide</a>.</p>
            <p>Cached voices are stored in <code>data/voices</code>. <a href="../data/voices" style="color: yellow;" target="_blank">View Cache Directory</a></p>
            <input type="submit" name="upload_all" value="Sync Voice Cache" class="action-button">
        </form>
    </div>

    <p>Advanced XTTS configuration: <a href="<?php echo $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]; ?>/docs#" style="color: yellow;" target="_blank"><?php echo $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]; ?>/docs#</a></p>

</div>

</body>
</html>
