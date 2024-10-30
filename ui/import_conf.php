<?php
session_start();

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
$acceptablePattern = '/^conf_[a-f0-9]{32}\.php$/i';

// Initialize message variable
$message = '';

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

                    $message .= '<p>Configuration files imported successfully.</p>';
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
        <input type="submit" value="Upload and Import">
    </form>
    <p><strong>Note:</strong> Only files with names matching the pattern <code>conf_[32-character MD5 hash].php</code> will be imported.</p>
</div>
</body>
</html>
