<?php
// Start the session to handle any messages if needed
session_start();

$enginePath = __DIR__ . DIRECTORY_SEPARATOR .  ".." . DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if a file was uploaded without errors
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        // Validate the uploaded file
        $fileTmpPath = $_FILES['sql_file']['tmp_name'];
        $fileName = $_FILES['sql_file']['name'];
        $fileSize = $_FILES['sql_file']['size'];
        $fileType = $_FILES['sql_file']['type'];

        // Allowed file extensions
        $allowedfileExtensions = array('sql');

        // Get file extension
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory where the uploaded file will be moved
            $uploadFileDir = $enginePath . 'data' . DIRECTORY_SEPARATOR;
            $destPath = $uploadFileDir . 'dwemer.sql';

            // Ensure the upload directory exists
            if (!file_exists($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Move the file to the destination directory with the new name
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Proceed to restore the database
                // Connect to the database
                $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

                if (!$conn) {
                    echo "Failed to connect to database.\n";
                    die();
                }

                // Drop and recreate database schema and extensions
                $Q = array();
                $Q[] = "DROP SCHEMA IF EXISTS $schema CASCADE";
                $Q[] = "DROP EXTENSION IF EXISTS vector CASCADE";
                $Q[] = "CREATE SCHEMA $schema";
                $Q[] = "CREATE EXTENSION vector";

                foreach ($Q as $QS) {
                    $r = pg_query($conn, $QS);
                    if (!$r) {
                        echo pg_last_error($conn);
                        die();
                    } else {
                        echo "$QS ok<br/>";
                    }
                }

                // Path to SQL file to import
                $sqlFile = $destPath;

                // Command to import SQL file using psql
                $psqlCommand = "PGPASSWORD=" . escapeshellarg($password) . " psql -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($username) . " -d " . escapeshellarg($dbname) . " -f " . escapeshellarg($sqlFile);

                // Execute psql command
                $output = [];
                $returnVar = 0;
                exec($psqlCommand, $output, $returnVar);

                if ($returnVar !== 0) {
                    echo "Failed to import SQL file.<br/>";
                    echo nl2br(htmlspecialchars(implode("\n", $output))) . "<br/>";
                    exit;
                }
                
                echo "<br>";
                echo "SQL file imported successfully.<br/>";
                echo nl2br(htmlspecialchars(implode("\n", $output))) . "<br/>";
                echo "Import completed.<br/><br>";

                // Provide a clickable link and popup message
                $redirectUrl = '/HerikaServer/ui/index.php?table=eventlog';
                echo "<script type='text/javascript'>
                        alert('Database restored successfully.');
                      </script>";
                echo "<a href='$redirectUrl'><b>Click me to go back!</b></a>";
            } else {
                echo 'There was an error moving the uploaded file.<br/>';
            }
        } else {
            echo 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions) . '<br/>';
        }
    } else {
        echo 'No file uploaded or there was an upload error.<br/>';
    }
} else {
    // Display the upload form
    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <title>Upload SQL File</title>
    </head>
    <body>
        <h1>Upload the SQL file backup you downloaded from the DwemerDistro</h1>
        <form action="" method="post" enctype="multipart/form-data">
            <label for="sql_file">Select SQL file to upload:</label>
            <input type="file" name="sql_file" id="sql_file" accept=".sql" required>
            <br><br>
            <input type="submit" value="Upload and Restore">
        </form>
    </body>
    </html>

    <?php
}
?>
