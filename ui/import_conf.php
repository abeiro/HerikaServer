<?php
session_start();

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");


ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
}

$pattern = '/conf_([a-f0-9]+)\.php/';
preg_match($pattern, basename($_SESSION["PROFILE"]), $matches);
$hash = $matches[1];    

$db=new sql();
$res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
$last_gamets=$res[0]["last_gamets"]+1;

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$enginePath = $rootPath . ".." . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

// Set the directory to the conf directory
$confDir = realpath($configFilepath);

if (!$confDir) {
    die('Conf directory not found.');
}

// Define the acceptable filename pattern
$acceptablePattern = '/^(conf_[a-f0-9]{32}\.php|conf\.php|character_map\.json|\.conf_[a-f0-9]{32}_[0-9]{10}\.php)$/i';

// Initialize message variable
$message = '';

// Define deleteDir() function at the top so it's always available
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        return;
    }
    $files = scandir($dirPath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                deleteDir($filePath);
            } else {
                unlink($filePath);
            }
        }
    }
    rmdir($dirPath);
}

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if a file was uploaded without errors
    if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
        // Validate the uploaded file
        $fileTmpPath = $_FILES['zip_file']['tmp_name'];
        $fileName = $_FILES['zip_file']['name'];
        $fileSize = $_FILES['zip_file']['size'];
        $fileType = $_FILES['zip_file']['type'];

        // Allowed file extensions
        $allowedfileExtensions = array('zip');

        // Get file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Create a temporary directory to extract the ZIP
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('conf_zip_', true);

            if (!mkdir($tempDir, 0755, true)) {
                $message .= '<p>Failed to create temporary directory.</p>';
                die();
            }

            // Open the ZIP file
            $zip = new ZipArchive();
            if ($zip->open($fileTmpPath) === TRUE) {
                // Extract files to temporary directory
                $zip->extractTo($tempDir);
                $zip->close();

                // Now, scan the extracted files and validate filenames
                $invalidFiles = array();
                $validFiles = array();

                // Function to recursively validate files
                function validateFiles($dir, &$invalidFiles, &$validFiles, $acceptablePattern) {
                    $files = scandir($dir);
                    foreach ($files as $file) {
                        if ($file == '.' || $file == '..') continue;
                        $filePath = $dir . DIRECTORY_SEPARATOR . $file;

                        // Check if it's a file or directory
                        if (is_dir($filePath)) {
                            // Recurse into subdirectory
                            validateFiles($filePath, $invalidFiles, $validFiles, $acceptablePattern);
                        } else {
                            // Check if filename matches the acceptable pattern
                            if (preg_match($acceptablePattern, $file)) {
                                $validFiles[] = $filePath;
                            } else {
                                $invalidFiles[] = $filePath;
                            }
                        }
                    }
                }

                // Start validation from the temp directory
                validateFiles($tempDir, $invalidFiles, $validFiles, $acceptablePattern);

                if (!empty($invalidFiles)) {
                    // Delete the temporary directory and its contents
                    deleteDir($tempDir);

                    $message .= '<p>Invalid files detected in the ZIP archive:</p><ul>';
                    foreach ($invalidFiles as $invalidFile) {
                        $message .= '<li>' . htmlspecialchars(basename($invalidFile)) . '</li>';
                    }
                    $message .= '</ul><p>Upload aborted.</p>';
                } else {
                    // All files are valid, proceed to copy them
                    function copyValidFiles($src, $dst, $acceptablePattern) {
                        $dir = opendir($src);
                        while(false !== ($file = readdir($dir))) {
                            if (($file != '.') && ($file != '..')) {
                                $srcFilePath = $src . DIRECTORY_SEPARATOR . $file;
                                $dstFilePath = $dst . DIRECTORY_SEPARATOR . $file;

                                if (is_dir($srcFilePath)) {
                                    if (!file_exists($dstFilePath)) {
                                        mkdir($dstFilePath);
                                    }
                                    copyValidFiles($srcFilePath, $dstFilePath, $acceptablePattern);
                                } else {
                                    // Check if filename matches the acceptable pattern
                                    if (preg_match($acceptablePattern, $file)) {
                                        if (!copy($srcFilePath, $dstFilePath)) {
                                            global $message;
                                            $message .= '<p>Failed to copy ' . htmlspecialchars($srcFilePath) . ' to ' . htmlspecialchars($dstFilePath) . '</p>';
                                        }
                                    }
                                }
                            }
                        }
                        closedir($dir);
                    }

                    // Copy valid files from temp directory to conf directory
                    copyValidFiles($tempDir, $confDir, $acceptablePattern);

                    // Delete the temporary directory and its contents
                    deleteDir($tempDir);

                    $message .= '<p>Configuration files imported successfully. Refresh the page to see the new profiles.</p>';
                }
            } else {
                $message .= '<p>Failed to open the ZIP file.</p>';
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
    <title>Import Configuration Files</title>
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


        label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
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
            margin: 0;
        }

        .message ul {
            margin: 0;
            padding-left: 20px;
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

    </style>
</head>
<body>
<div class="indent5">
    <h1>Restore Character Profiles</h1>
    <p>Upload the ZIP file containing your configuration files. <br>You can download this from <b>Server Actions - Backup Character Profiles</b></p>
    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="zip_file">Select ZIP file to upload:</label>
        <input type="file" name="zip_file" id="zip_file" accept=".zip" required>
        <br>
        <input type="submit" class="btn-save" value="Upload and Import">
        <p><strong>Note:</strong> Only files with names matching the pattern <code>conf_[32-character MD5 hash].php</code> will be imported.</p>
    </form>
</div>
</body>
</html>
