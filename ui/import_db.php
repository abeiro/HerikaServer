<?php
session_start();

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$enginePath = $rootPath . ".." . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Initialize message variable
$message = '';

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
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

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
                    $message .= "<p>Failed to connect to database: " . pg_last_error() . "</p>";
                } else {
                    // Drop and recreate database schema and extensions
                    $Q = array();
                    $Q[] = "DROP SCHEMA IF EXISTS $schema CASCADE";
                    $Q[] = "DROP EXTENSION IF EXISTS vector CASCADE";
                    $Q[] = "CREATE SCHEMA $schema";
                    $Q[] = "CREATE EXTENSION vector";

                    $errorOccurred = false;

                    foreach ($Q as $QS) {
                        $r = pg_query($conn, $QS);
                        if (!$r) {
                            $message .= "<p>Error executing query: " . pg_last_error($conn) . "</p>";
                            $errorOccurred = true;
                            break;
                        } else {
                            $message .= "<p>$QS executed successfully.</p>";
                        }
                    }

                    if (!$errorOccurred) {
                        // Path to SQL file to import
                        $sqlFile = $destPath;

                        // Command to import SQL file using psql
                        $psqlCommand = "PGPASSWORD=" . escapeshellarg($password) . " psql -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($username) . " -d " . escapeshellarg($dbname) . " -f " . escapeshellarg($sqlFile);

                        // Execute psql command
                        $output = [];
                        $returnVar = 0;
                        exec($psqlCommand, $output, $returnVar);

                        if ($returnVar !== 0) {
                            $message .= "<p>Failed to import SQL file.</p>";
                            $message .= '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                        } else {
                            $message .= "<p>SQL file imported successfully.</p>";
                            $message .= '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                            $message .= "<p>Import completed.</p>";

                            // Provide a clickable link and popup message
                            $redirectUrl = '/HerikaServer/ui/index.php?table=eventlog';
                            $message .= "<script type='text/javascript'>
                                            alert('Database restored successfully.');
                                         </script>";
                            $message .= "<p><a href='$redirectUrl'><b>Click here to go back!</b></a></p>";
                        }
                    }

                    // Close the database connection
                    pg_close($conn);
                }
            } else {
                $message .= '<p>There was an error moving the uploaded file.</p>';
            }
        } else {
            $message .= '<p>Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions) . '</p>';
        }
    } else {
        $message .= '<p>No file uploaded or there was an upload error.</p>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>Upload SQL File</title>
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
        }

        h1, h2 {
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
            padding: 5px 15px; /* Increased padding for larger button */
            font-size: 18px;    /* Increased font size */
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
            max-width: 600px;
            margin-bottom: 20px;
            color: #f8f9fa; /* Light text in messages */
        }

        .message p {
            margin: 0 0 10px 0;
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
            border-radius: 3px;
        }

        .button:hover {
            background-color: #0056b3;
        }

        pre {
            background-color: #2c2c2c;
            padding: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
            color: #f8f9fa;
            overflow: auto;
            max-width: 600px;
        }
    </style>
</head>
<body>
<div class="indent5">
    <h1>Restore Database</h1>
    <p>Upload the SQL backup file.<br>You can download this from <b>Server Actions - Backup Current Database</b></p>
    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="sql_file">Select SQL file to upload:</label>
        <input type="file" name="sql_file" id="sql_file" accept=".sql" required>
        <input type="submit" value="Upload and Restore">
    </form>
</div>
</body>
</html>
