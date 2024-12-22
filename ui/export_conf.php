<?php
// Set the directory to the conf directory
$dir = realpath('../conf');

if (!$dir) {
    die('Conf directory not found.');
}

// Files to exclude
$excludeFiles = array(
    'conf_loader.php',
    'conf_schema.json',
    'conf.sample.php'
);

// Create new ZIP archive
$zip = new ZipArchive();

// Create a temporary file for the ZIP
$zipFile = tempnam(sys_get_temp_dir(), 'conf_zip_');

if ($zip->open($zipFile, ZipArchive::OVERWRITE) !== TRUE) {
    die ('Cannot create a zip file');
}

// Function to recursively add files to zip, excluding specified files
function addFilesToZip($dir, $zip, $excludeFiles, $basePathLength) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (in_array($file, $excludeFiles)) {
            continue;
        }
        if (is_file($filePath)) {
            $localPath = substr($filePath, $basePathLength);
            $zip->addFile($filePath, $localPath);
        } elseif (is_dir($filePath)) {
            // Recurse into subdirectory
            addFilesToZip($filePath, $zip, $excludeFiles, $basePathLength);
        }
    }
}

// Calculate the length of the base path to strip from the file paths
$basePathLength = strlen($dir) + 1; // +1 to remove the directory separator

// Add files to the ZIP archive
addFilesToZip($dir, $zip, $excludeFiles, $basePathLength);

// Close the ZIP archive
$zip->close();

// Set the headers to download the ZIP file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="conf_files.zip"');
header('Content-Length: ' . filesize($zipFile));

// Output the ZIP file
readfile($zipFile);

// Delete the temporary ZIP file
unlink($zipFile);

exit();
?>
