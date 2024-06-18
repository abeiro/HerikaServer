<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php

if (isset($_POST["submit"])) {
    // Specify the target directory for uploaded files
    $targetDirectory = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR;

    // Specify the target file path
    $targetFile = $targetDirectory . "mysqlitedb.db";

    // Try to move the uploaded file to the specified directory
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
        echo "Database successfully imported. PLEASE RESTART THE AI Follower Framework SERVER!";
    } else {
        echo "Error uploading file.";
    }
}

include("tmpl/head.html");

$debugPaneLink = false;
include("tmpl/navbar.php");

echo '
<form action="import_db.php" method="POST" enctype="multipart/form-data">
    <label for="file">Upload the mysqlitedb.db file:</label>
    <input type="file" name="file" id="file">
    <br>
    <input type="submit" name="submit" value="Import">
</form>
';

include("tmpl/footer.html");
$title = "Gateway Server for {$GLOBALS["PLAYER_NAME"]}";
echo "<title>$title</title>";

?>
</body>
</html>
