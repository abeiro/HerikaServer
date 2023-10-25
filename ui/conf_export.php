<?php
$file_path = '../conf/conf.php'; // Relative path from the location of conf_export.php


$config_content = file_get_contents($file_path);
// Parse the content to extract the value of $PLAYER_NAME
if (preg_match('/\$PLAYER_NAME\s*=\s*\'(.*?)\';/', $config_content, $matches))
    $PLAYER_EXPORT = $matches[1]; // $PLAYER_EXPORT now contains the value of $PLAYER_NAME
else {
    $PLAYER_EXPORT = 'Configuration'; // Set a default filename
}

if (file_exists($file_path)) {
    // Read the content of the file
    $file_content = file_get_contents($file_path);

    // Find the position of the "<?php" tag
    $php_tag_position = strpos($file_content, '<?php');

    if ($php_tag_position !== false) {
        // Extract and output the content after "<?php"
        $php_code = substr($file_content, $php_tag_position);
        
        // Use $PLAYER_EXPORT as the filename
        $output_filename = $PLAYER_EXPORT . '-Configuration.php';


        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $output_filename);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($php_code));
        echo $php_code;
        exit;
    } else {
        // "<?php" tag not found
        die('The "<?php" tag was not found in the file.');
    }
} else {
    // File not found
    die('The file you requested does not exist.');
}
?>
