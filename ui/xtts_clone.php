<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "🔊 CHIM XTTS Voice Management";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

// Add meta tag for API endpoint
echo '<meta name="api-endpoint" content="' . htmlspecialchars($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '">';

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Enable error reporting (for development purposes)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function normalize_endpoint_url($url) {
    // Remove trailing slashes
    $url = rtrim($url, '/');
    return $url;
}

// Define the endpoint for the XTTS API
if (!isset($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]))
    $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] = 'http://127.0.0.1:8020';

// Normalize the endpoint URL
$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]);

// Initialize message variables
$message = '';
$speakersMessage = '';

// Get speakers list for POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["get_speakers"])) {
    // Remove this entire first handler as it's duplicated
    $speakersMessage = '';
}

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
                    $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/upload_sample';
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
        $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/speakers_list';

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
                            '<div class="button-container">' .
                            '<button onclick="copyToClipboard(\'' . htmlspecialchars($displayName) . '\')" ' .
                            'class="copy-btn" title="Copy voice name">⎘</button>' .
                            '<button onclick="testVoice(\'' . htmlspecialchars($displayName) . '\')" ' .
                            'class="play-btn" title="Test voice">▶</button>' .
                            '</div>' .
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
                $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/upload_sample';
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
                    } else {
                        $message .= '<p>Error uploading ' . htmlspecialchars($fileName) . ' (HTTP code ' . $httpCode . '): ' . htmlspecialchars($response) . '</p>';
                    }
                }
                curl_close($ch);
            }
        }
        $message .= "<p><h3 style='color:rgb(247, 231, 16);'>$numUploaded out of $numFiles voice files have been uploaded.</h3></p>";
    }
}


// Add the JavaScript functions
?>
<script>
    const API_ENDPOINT = <?php echo json_encode(normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"])); ?>;
    const WEB_ROOT = <?php echo json_encode($webRoot); ?>;

    function normalizeUrl(url) {
        return url.replace(/\/+$/, '');
    }

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
        const isHidden = voiceList.style.display === 'none' || !voiceList.style.display;
        
        if (isHidden) {
            // Create and submit form to get speakers
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = WEB_ROOT + '/ui/xtts_clone.php#voiceList';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'get_speakers';
            input.value = '1';
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        } else {
            voiceList.style.display = 'none';
            toggleBtn.textContent = 'Show Available Voices';
        }
    }

    function showToast(message, duration = 1500) {
        const toast = document.getElementById('toast');
        const messageSpan = toast.querySelector('.message');
        messageSpan.textContent = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard');
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    }

    function testVoice(voiceName) {
        const url = `${normalizeUrl(API_ENDPOINT)}/tts_to_audio`;
        const data = {
            text: 'CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis',
            speaker_wav: voiceName,
            language: 'en'
        };

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'audio/wav'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.blob();
        })
        .then(blob => {
            const audio = new Audio(URL.createObjectURL(blob));
            audio.play();
        })
        .catch(error => {
            console.error('Error testing voice:', error);
            alert('Error testing voice. Please check the console for details.');
        });
    }

    // Initialize voice list state
    document.addEventListener('DOMContentLoaded', function() {
        const voiceList = document.getElementById('voiceList');
        const toggleBtn = document.getElementById('toggleVoices');
        
        // Set initial state
        if (!voiceList.innerHTML.trim()) {
            voiceList.style.display = 'none';
            toggleBtn.textContent = 'Show Available Voices';
        } else {
            voiceList.style.display = 'block';
            toggleBtn.textContent = 'Hide Available Voices';
        }
    });
</script>
<?php

?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Override main container styles */
    main {
        padding-top: 160px;
        padding-bottom: 40px;
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* Page Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin-bottom: 15px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header h3 {
        text-align: center;
        margin-bottom: 15px;
    }

    .page-header h4 {
        text-align: center;
        margin-bottom: 25px;
    }

    /* Content Section Headers */
    .content-section h1, .indent5 h1 {
        font-family: 'MagicCards', serif;
        font-size: 1.8em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        word-spacing: 8px;
        text-align: center;
        margin-bottom: 20px;
    }

    /* Form Container Styling */
    .form-container {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Content Layout Improvements */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    /* Voice list styling */
    .response-container {
        margin-top: 15px;
        padding: 15px;
        background-color: #2c2c2c;
        border: 1px solid #4a4a4a;
        border-radius: 5px;
    }

    .voice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 8px;
        margin-top: 10px;
    }

    .speaker-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background-color: #3a3a3a;
        border: 1px solid #555555;
        border-radius: 4px;
        color: #f8f9fa;
    }

    .copy-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 12px;
        transition: all 0.2s ease;
        margin-left: 8px;
    }

    .speaker-item:hover .copy-btn {
        opacity: 0.8;
    }

    .copy-btn:hover {
        opacity: 1 !important;
        transform: scale(1.1);
    }

    .button-container {
        display: flex;
        gap: 4px;
        align-items: center;
    }

    .play-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 10px;
        transition: all 0.2s ease;
    }

    .speaker-item:hover .play-btn {
        opacity: 0.8;
    }

    .play-btn:hover {
        opacity: 1 !important;
        transform: scale(1.1);
    }

    /* Voice list container styling */
    .voice-list-container {
        background: #1a1a1a;
        border-radius: 8px;
        border: 2px solid rgb(242, 124, 17);
        padding: 20px;
        margin-top: 15px;
    }

    .voice-list-header h3 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        text-align: center;
    }

    .voice-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        background-color: #3a3a3a;
        border: 1px solid #555555;
        border-radius: 6px;
        color: #f8f9fa;
        transition: all 0.2s ease;
    }

    .voice-item:hover {
        background-color: #4a4a4a;
        border-color: rgb(242, 124, 17);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .form-container {
            padding: 15px;
        }
        
        .content-section {
            padding: 15px;
        }

        .page-header {
            padding: 15px;
        }

        .page-header h1 {
            font-size: 1.8em;
        }

        .content-section h1, .indent5 h1 {
            font-size: 1.6em;
        }

        .voice-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .page-header h1 {
            font-size: 1.5em;
        }

        .content-section h1, .indent5 h1 {
            font-size: 1.3em;
        }
        
        .button-group {
            flex-direction: column;
        }

        .voice-grid {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .voice-item {
            padding: 8px 12px;
        }
    }
</style>

<main>
    <div id="loading-overlay">
        <p>Syncing voice cache to CHIM XTTS server, this can take a couple minutes. <br><b>Do not refresh the page<span id="ellipsis"></span></b></p>
    </div>

    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>CHIM XTTS Voice Management 
            <a href="https://dwemerdynamics.hostwiki.io/en/Auto-TTS-Voices" target="_blank" rel="noopener" 
               style="display: inline-block; margin-left: 15px; color: rgb(242, 124, 17); text-decoration: none; font-size: 0.7em; vertical-align: top; border: 2px solid rgb(242, 124, 17); border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 20px; transition: all 0.3s ease;" 
               title="View detailed documentation about TTS Voice Configuration"
               onmouseover="this.style.background='rgb(242, 124, 17)'; this.style.color='white';" 
               onmouseout="this.style.background='transparent'; this.style.color='rgb(242, 124, 17)';">ℹ</a>
        </h1>
        <p>The <b>CHIM XTTS Voice Management</b> system allows you to manage custom voice samples for NPCs using the CHIM XTTS Server.</p>
        <p>This works differently from other TTS services - it requires voice samples to be uploaded and cached on the server.</p>
        <p>For detailed information on how it works, please read our <a href="https://dwemerdynamics.hostwiki.io/en/TTS-Options#chim-xtts" style="color: yellow;" target="_blank" rel="noopener noreferrer">CHIM XTTS Voice Guide</a>.</p>
        <h3><strong>Ensure all voice sample filenames are lowercase and spaces are replaced with underscores (_).</strong></h3>
        <h4>Example: "Mjoll the Lioness" becomes "mjoll_the_lioness.wav"</h4>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="content-grid">
        <div class="content-section">
            <h1>Voice Sample Upload</h1>
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php" method="post" enctype="multipart/form-data">
                <div>
                    <label for="file">Select .wav file(s) to upload:</label>
                    <br>
                    <input type="file" name="file[]" id="file" accept=".wav" multiple="multiple" required>
                </div>
                <div class="button-group">
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
                <li>Size: 5MB or less</li>
            </ul>
        </div>

        <div class="content-section">
            <h1>Current Voice List</h1>
            <div class="button-group">
                <button onclick="toggleVoiceList()" id="toggleVoices" class="action-button download-csv">Show Available Voices</button>
            </div>
            <div id="voiceList" style="display: none; margin-top: 15px;">
                <?php echo $speakersMessage; ?>
            </div>
        </div>
    </div>

    <div class="content-section full-width-section">
        <h1>Cloud XTTS Sync</h1>
        <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php" method="post" onsubmit="showLoadingMessage();">
            <p><strong>Only required for online CHIM XTTS instances.</strong></p>
            <p>Sync just needs to be ran ONE TIME after initial setup of a new instance.</p>
            <p>Empty voice cache is acceptable - new NPC voices will be cached automatically.</p>
            <p>For cloud setup instructions, see our <a href="https://dwemerdynamics.hostwiki.io/en/Vast-AI" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cloud XTTS Guide</a>.</p>
            <p>Cached voices are stored in <code>data/voices</code>. <a href="<?php echo $webRoot; ?>/data/voices" style="color: yellow;" target="_blank">View Cache Directory</a></p>
            <div class="button-group">
                <input type="submit" name="upload_all" value="Sync Voice Cache" class="action-button edit">
            </div>
        </form>
        <p>Advanced XTTS configuration: <a href="<?php echo normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]); ?>/docs" style="color: yellow;" target="_blank"><?php echo normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]); ?>/docs</a></p>
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
