<?php
// Directory containing the configuration files
$config_dir = '../conf/';

// Primary configuration file
$main_conf_file = $config_dir . 'conf.php';

// Attempt to read and parse the main conf.php for $PLAYER_NAME
$PLAYER_EXPORT = 'Configuration'; // default fallback
if (file_exists($main_conf_file)) {
    $config_content = file_get_contents($main_conf_file);
    if (preg_match('/\$PLAYER_NAME\s*=\s*\'(.*?)\';/', $config_content, $matches)) {
        $PLAYER_EXPORT = $matches[1];
    }
}

// Prepare a list of files to export
$files_to_export = [];

// Function to decide if a file is valid (not a zone identifier file)
function is_valid_conf_file($filename) {
    // Exclude files with Zone.Identifier
    if (strpos($filename, 'Zone.Identifier') !== false) {
        return false;
    }
    return true;
}

// Include conf.php if it exists and not a zone identifier file
if (file_exists($main_conf_file) && is_valid_conf_file($main_conf_file)) {
    $files_to_export[] = $main_conf_file;
}

// Include character_map.json if it exists and not a zone identifier file
$character_map_file = $config_dir . 'character_map.json';
if (file_exists($character_map_file) && is_valid_conf_file($character_map_file)) {
    $files_to_export[] = $character_map_file;
}

// Include any conf_*.php files that are not zone identifier files
$conf_pattern_files = glob($config_dir . 'conf_*.php');
if (!empty($conf_pattern_files)) {
    foreach ($conf_pattern_files as $file) {
        if (is_valid_conf_file($file)) {
            $files_to_export[] = $file;
        }
    }
}

// If no files found, terminate
if (empty($files_to_export)) {
    die('No configuration files found to export.');
}

// Create a temporary ZIP file
$zip_filename = $PLAYER_EXPORT . '-Configuration.zip';
$zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_filename;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die('Could not create ZIP archive.');
}

// Add each configuration file to the zip
foreach ($files_to_export as $file) {
    $localname = basename($file);
    $zip->addFile($file, $localname);
}

$zip->close();

// Set headers to initiate file download of the ZIP
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($zip_path));

// Output the ZIP file
readfile($zip_path);

// Delete the temporary ZIP file (optional cleanup)
unlink($zip_path);
exit;
