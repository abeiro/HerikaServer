<?php
// Set the path to the folder you want to zip
$folderPath = realpath(__DIR__ . '/../ext/herika_heal');

// Check if the folder exists
if (!file_exists($folderPath) || !is_dir($folderPath)) {
    die('Error: Folder does not exist.');
}

// Name of the zip file to be downloaded
$zipName = 'herika_heal.zip';

// Initialize ZipArchive
$zip = new ZipArchive();

// Create a temporary file for the zip
$tempZip = tempnam(sys_get_temp_dir(), 'zip');

// Open the temporary zip file
if ($zip->open($tempZip, ZipArchive::OVERWRITE) !== TRUE) {
    die("Error: Cannot create zip file.");
}

// Function to recursively add files to the zip
function addFolderToZip($folder, $zip, $parentFolder = '') {
    $handle = opendir($folder);
    if (!$handle) {
        return;
    }

    while (($file = readdir($handle)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        $filePath = $folder . DIRECTORY_SEPARATOR . $file;
        $localPath = $parentFolder ? $parentFolder . '/' . $file : $file;

        if (is_dir($filePath)) {
            // Add empty directories
            $zip->addEmptyDir($localPath);
            addFolderToZip($filePath, $zip, $localPath);
        } else {
            // Add files
            $zip->addFile($filePath, $localPath);
        }
    }

    closedir($handle);
}

// Add the folder to the zip
addFolderToZip($folderPath, $zip);

// Close the zip archive
$zip->close();

// Set headers to initiate file download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tempZip));

// Clear output buffer to avoid any unexpected output
ob_clean();
flush();

// Read the temporary zip file and send it to the user
readfile($tempZip);

// Delete the temporary zip file
unlink($tempZip);
exit;
?>
