<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php

// Path to the script file
$scriptFile = 'scriptconf.php';

$SCRIPTANIMATIONS = explode("\n", $SCRIPTANIMATIONS);

error_reporting(E_ALL);
$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

echo file_get_contents('template.html');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $script = $_POST["script"];
  // You can optionally trim the script content here.
  // $script = trim($script);

  // Specify the file you want to save the script to
  $scriptFileName = 'script.json';

  // Write the script content to the file
  if (file_put_contents($scriptFileName, $script) !== false) {
      echo "Script saved successfully!";
  } else {
      echo "Error saving the script.";
  }
}

?>

<h1>Herika Script Writer</h1>
<br>
<form method="post">
    <h2>Herika Script</h2>
    <textarea name="script" rows="30" cols="200"><?php echo trim(file_get_contents('script.json')); ?></textarea><br>
    <input type="submit" value="Save Script">
</form>


<br>

<div id="playButtonContainer">
    <button type="button" class="play-button" onclick="executeScriptInBackground();">Play Script</button>
</div>

<script>
function executeScriptInBackground() {
    // Make an AJAX request to execute the script
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "HerikaScriptWriter.php", true);
    xhr.send();

    // Handle the response (optional)
    xhr.onload = function() {
        if (xhr.status == 200) {
            alert("Script executed in the background.");
        } else {
            alert("Error: Script execution failed.");
        }
    };
}
</script>

<!-- Divider -->
<hr class="divider">

<h1>ChatGPT Script Generator, Not Complete</h1>

<br>

<form method="post" action="save_script.php">
        <h2>Script Rules</h2>
        <textarea name="script" rows="10" cols="100">
            <?php
            include('scriptconf.php');
            echo trim($SCRIPTRULES);
            ?>
        </textarea><br>
    </form>

<br>

<form method="post" action="save_script.php">
        <h2>Script Topic</h2>
        <textarea name="script" rows="5" cols="100">
            <?php
            include('scriptconf.php');
            echo trim($SCRIPTTOPIC);
            ?>
        </textarea><br>
    </form>

    <br>

<form method="post" action="save_script.php">
        <h2>Script Length (In Seconds)</h2>
        <textarea name="script" rows="2" cols="6">
            <?php
            include('scriptconf.php');
            echo trim($SCRIPTLENGTH);
            ?>
        </textarea><br>
    </form>

<h2>Script Animations</h2>
    <textarea rows="10" cols="50" readonly>
        <?php
        // Assuming $SCRIPTANIMATIONS is an array
        foreach ($SCRIPTANIMATIONS as $animation) {
            echo $animation . "\n";
        }
        ?>
    </textarea>




