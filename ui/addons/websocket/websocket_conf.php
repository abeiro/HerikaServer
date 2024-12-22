<?php
$zipFile = __DIR__ . '/websocket.zip';
$sourceDir = __DIR__ . '/websocket_util';

function createZip($sourceDir, $zipFile) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    return $zip->close();
}

if (createZip($sourceDir, $zipFile)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="websocket.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);

    // Clean up the temporary zip file
    unlink($zipFile);
    exit;
} else {
    echo "Error: Failed to create websocket.zip.";
}
?>
