<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>AI Follower Framework - File Upload</title>
    <style>
        .response-container {
            margin-top: 20px;
        }
        .indent {
            padding-left: 10ch; /* 10 character spaces */
        }
    </style>
</head>
<body>

<?php

if (isset($_POST["submit"])) {
    ob_start();

    // Get the uploaded file details
    $fileTmpPath = $_FILES["file"]["tmp_name"];
    $fileName = $_FILES["file"]["name"];
    $fileType = $_FILES["file"]["type"];

    // Ensure the file is a .wav file
    if ($fileType !== 'audio/wav') {
        echo "Error: Please upload a .wav file." . PHP_EOL;
    } else {
        // Prepare the cURL request
        $url = 'http://127.0.0.1:8020/upload_sample';
        $cfile = new CURLFile($fileTmpPath, $fileType, $fileName);

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
            echo 'cURL Error: ' . curl_error($ch) . PHP_EOL;
        } else {
            if ($httpCode == 200) {
                echo "File has been successfully uploaded." . PHP_EOL;
            } else {
                echo 'Response from server (HTTP code ' . $httpCode . '): ' . $response . PHP_EOL;
            }
        }
        curl_close($ch);
    }

    $result = ob_get_clean();
} elseif (isset($_POST["get_speakers"])) {
    ob_start();

    // Prepare the cURL request for getting the speakers list
    $url = 'http://127.0.0.1:8020/speakers_list';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json'
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo 'cURL Error: ' . curl_error($ch) . PHP_EOL;
    } else {
        if ($httpCode == 200) {
            // Decode the JSON response
            $speakersList = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // Sort the speakers list alphabetically
                sort($speakersList);
                
                // Display the speakers list in a vertical format with indentation
                echo '<div class="response-container">';
                echo '<h3><b>     Current Voices:</b></h3>';
                echo '<div class="indent">';
                foreach ($speakersList as $speaker) {
                    echo htmlspecialchars($speaker) . '<br>';
                }
                echo '</div>';
                echo '</div>';
            } else {
                echo 'Error decoding JSON response.' . PHP_EOL;
            }
        } else {
            echo 'Response from server (HTTP code ' . $httpCode . '): ' . $response . PHP_EOL;
        }
    }

    curl_close($ch);

    $result = ob_get_clean();
}

include("tmpl/head.html");

$debugPaneLink = false;
include("tmpl/navbar.php");

echo "<pre>$result</pre>";

echo '
<form action="xtts_manager.php" method="POST" enctype="multipart/form-data">
    <label><b>XTTS Voice Generation</b></label>
    <br>
    <label for="file">Select a .wav file and make sure it is named after the character\'s voice you want to generate.</label>
    <br>
    <label>Examples: herika.wav, lydia.wav, mjoll_the_lioness.wav etc.</label>
    <br>
    <label>YOU MUST RESTART THE SERVER IF YOU ARE REPLACING AN ALREADY EXISITNG VOICE FILE!</label>
    <br>
    <input type="file" name="file" id="file">
    <br>
    <br>
    <input type="submit" name="submit" value="Upload">
</form>
';

echo '
<form action="xtts_manager.php" method="POST">
    <br>
    <label><b>List Current Voices in XTTS</b></label>
    <br>
    <input type="submit" name="get_speakers" value="Current Voices List">
    <br>
    <br>
    <label>Link to advanced XTTS configuration menu: <a href="http://127.0.0.1:8020/docs#" target="_blank">http://127.0.0.1:8020/docs#</a></label>
</form>
';

include("tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = "AI Follower Framework";
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>

</body>
</html>
