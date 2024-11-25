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

                    // Display the speakers list in a 4-column grid
                    $speakersMessage .= '<div class="response-container">';
                    $speakersMessage .= '<h3><b>Current Voices:</b></h3>';
                    $speakersMessage .= '<div class="speakers-grid">';
                    foreach ($speakersList as $speaker) {
                        $speakersMessage .= '<div class="speaker-item">' . htmlspecialchars($speaker) . '</div>';
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
    <title>CHIM - XTTS Voice Upload</title>
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
        }

        h1, h2, h3, h4 {
            color: #ffffff; /* White color for headings */
        }

        form {
            margin-bottom: 20px;
            background-color: #3a3a3a; /* Slightly lighter grey for form backgrounds */
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #555555; /* Darker border for contrast */
            max-width: 600px;
        }

        label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
        }

        input[type="text"], input[type="file"], textarea {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #555555; /* Darker borders */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input backgrounds */
            color: #f8f9fa; /* Light text inside inputs */
            resize: vertical; /* Allows users to resize vertically if needed */
            font-family: Arial, sans-serif; /* Ensures consistent font */
            font-size: 14px; /* Sets a readable font size */
        }

        input[type="submit"] {
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px; /* Slightly larger border radius */
            cursor: pointer;
            padding: 8px 16px; /* Increased padding for larger button */
            font-size: 16px;    /* Increased font size */
            font-weight: bold;  /* Bold text for better visibility */
            transition: background-color 0.3s ease; /* Smooth hover transition */
        }

        input[type="submit"]:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }

        .message {
            background-color: #444444; /* Darker background for messages */
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            max-width: 1000px; /* Increased max-width for a wider message box */
            width: 100%; /* Ensures it uses full width */
            margin-bottom: 20px;
            color: #f8f9fa; /* Light text in messages */
        }

        .message p {
            margin: 0;
        }

        .response-container {
            margin-top: 20px;
        }

        .indent {
            padding-left: 10ch; /* 10 character spaces */
        }

        .indent5 {
            padding-left: 5ch; /* 5 character spaces */
        }

        .button {
            padding: 8px 16px;
            margin-top: 10px;
            cursor: pointer;
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #0056b3;
        }

        a {
            color: #007bff;
            text-decoration: none;
        }

        a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* New styles for the speakers grid */
        .speakers-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 columns */
            gap: 10px;
            margin-top: 10px;
        }

        .speaker-item {
            background-color: #4a4a4a;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #555555;
            color: #f8f9fa;
            text-align: center;
        }

        /* Loading overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(44, 44, 44, 0.95); /* Semi-transparent background */
            z-index: 9999;
            display: none;
        }

        #loading-overlay p {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #f8f9fa;
            font-size: 20px;
            text-align: center;
        }

        #ellipsis {
            display: inline-block;
            width: 1em;
            text-align: left;
        }
    </style>
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
    </script>
</head>
<body>

<div id="loading-overlay">
    <p>Syncing voice cache to CHIM XTTS server, this can take a couple minutes. <br><b>Do not refresh the page<span id="ellipsis"></span></b></p>
</div>

<div class="indent5">
    <h1>🎙 CHIM XTTS Voice Management</h1>
    <h3><strong>This page is only for the CHIM XTTS Server!</strong></h3>
    <h3>It works differently from other TTS services! <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.ojs1hcgp0qwl" target="_blank">Click here for more info on how it works.</a></h3>
    <br>
    <h4>Make sure that all names with spaces are replaced with underscores (_) and all names are lowercase!</h4>
    <h4>Example: Mjoll the Lioness becomes <code>mjoll_the_lioness.wav</code></h4>
    <h4>If you are replacing an existing voice you will need to restart the CHIM XTTS server.</h4>

    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>

    <h2>Upload Voice Sample</h2>
    <label for="file">This will upload a .wav file to the running CHIM XTTS server and cache it in the CHIM server.</label>
    <form action="xtts_clone.php" method="post" enctype="multipart/form-data">
        <label for="file">Select a .wav file:</label>
        <input type="file" name="file[]" id="file" accept=".wav" multiple="multiple" required>

        <input type="submit" name="submit" value="Upload">
    </form>

    <h2>List Current Voices in CHIM XTTS</h2>
    <label for="file">This is a list of all the available voices in the CHIM XTTS server.</label>
    <form action="xtts_clone.php" method="post">
        <input type="submit" name="get_speakers" value="Current Voices List">
    </form>

    <h2>Sync Voices to Cloud CHIM XTTS</h2>
    <label for="file">If you are using a cloud based solution for CHIM XTTS, such as vast.ai, you will need to press the [Sync Voice Cache] button.</label>
    <br>
    <label for="file">You only need to Sync once you have setup the CHIM XTTS server. You do not need to press it again until you build a new instance.</label>
    <br>
    <label for="file">If you have no voices in your cache, that is fine! Any new NPC's will have their voice cached in the future.</label>
    <br>
    <br>
    <label for="file"><a href="https://www.nexusmods.com/skyrimspecialedition/articles/7673" target="_blank">Here is a guide for running CHIM XTTS on the cloud.</a></label>
        <br>
    <label for="file">Cached voices are saved in the server under data/voices. <a class="dropdown-item" href="../data/voices" target="_blank">View CHIM XTTS Cache</a></label>
    <form action="xtts_clone.php" method="post" onsubmit="showLoadingMessage();">
        <input type="submit" name="upload_all" value="Sync Voice Cache">
    </form>
    <?php
    // Display the speakers list message here
    if (!empty($speakersMessage)) {
        echo '<div class="message">';
        echo $speakersMessage;
        echo '</div>';
    }
    ?>

    <h4>Link to advanced XTTS configuration menu: <a href="<?php echo $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]; ?>/docs#" target="_blank"><?php echo $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]; ?>/docs#</a></h4>

    <h4>Recommended .wav file specifications for uploading a voice:</h4>
    <ul>
        <li>.wav format</li>
        <li>PCM</li>
        <li>16 bit</li>
        <li>Mono</li>
        <li>20500Hz</li>
    </ul>
</div>

</body>
</html>
