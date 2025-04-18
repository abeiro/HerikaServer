<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Global configuration

$PACKAGE_NAME = $_GET["PACKAGE_NAME"];
$GITHUB_REPO = $_GET["GITHUB_REPO"];
$DOWNLOAD_URL = "https://github.com/" . $GITHUB_REPO . "/releases/latest/download/" . $PACKAGE_NAME . ".tar.gz";
$TARGET_DIR = __DIR__ . "/" . $PACKAGE_NAME;
$TEMP_DIR = "/tmp/";

/**
 * Ensures the target directory exists and is writable
 * 
 * @param string $targetDir The directory to check/create
 * @return bool True if directory exists and is writable, false otherwise
 */
function ensureTargetDirectory($targetDir) {
    if (!file_exists($targetDir)) {
        echo "Creating target directory: " . $targetDir . "\n";
        if (!mkdir($targetDir, 0755, true)) {
            throw new Exception("Failed to create target directory: " . $targetDir);
        }
    }
    
    if (!is_writable($targetDir)) {
        throw new Exception("Target directory is not writable: " . $targetDir);
    }
    
    return true;
}

/**
 * Checks if a package is installed and gets its version
 * 
 * @param string $targetDir The directory where the package is installed
 * @return array|false Returns array with version info or false if not installed
 */
function checkLocalVersion($targetDir) {
    // Ensure the target directory exists
    if (!file_exists($targetDir)) {
        return false;
    }

    $manifestPath = $targetDir . "/manifest.json";
    
    if (!file_exists($manifestPath)) {
        return false;
    }
    
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid manifest.json file in " . $targetDir);
    }
    
    if (!isset($manifest['version'])) {
        throw new Exception("Version not found in manifest.json in " . $targetDir);
    }
    
    return [
        'installed' => true,
        'version' => $manifest['version']
    ];
}

/**
 * Gets the latest version from GitHub
 * 
 * @param string $githubRepo Repository in format owner/repo
 * @return string|false Returns version string or false on failure
 * uses release name for version control.
 */
function getRemoteVersion($githubRepo) {
    $apiUrl = "https://api.github.com/repos/" . $githubRepo . "/releases/latest";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Version Checker');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return false;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    return $data['tag_name'];
}

/**
 * Checks version information for a package
 * 
 * @param string $targetDir The directory where the package is installed
 * @param string $githubRepo Repository in format owner/repo
 * @return array Version information
 */
function checkVersion($targetDir, $githubRepo) {
    $result = [
        'installed' => false,
        'current_version' => null,
        'latest_version' => null,
        'update_available' => false
    ];
    
    try {
        // Check local version
        $localInfo = checkLocalVersion($targetDir);
        if ($localInfo !== false) {
            $result['installed'] = true;
            $result['current_version'] = $localInfo['version'];
        }
        
        // Check remote version
        $remoteVersion = getRemoteVersion($githubRepo);
        if ($remoteVersion !== false) {
            $result['latest_version'] = $remoteVersion;
            
            if ($result['installed']) {
                $result['update_available'] = version_compare($remoteVersion, $result['current_version'], '>');
            }
        }
    } catch (Exception $e) {
        echo "Error checking versions: " . $e->getMessage() . "\n";
    }
    
    return $result;
}

/**
 * Installs a package from a remote archive
 * 
 * @param string $downloadUrl The URL to download the package from
 * @param string $targetDir The directory where files will be installed
 * @param string $tempDir The temporary directory for operations
 * @param string $packageName The name of the package (used for downloaded file)
 * @return bool True if installation was successful, false otherwise
 */
function installPackage($downloadUrl, $targetDir, $tempDir, $packageName) {
    try {
        // Ensure target directory exists
        ensureTargetDirectory($targetDir);
        
        // Download
        echo "Downloading...\n";
        $downloadFile = $targetDir . "/" . $packageName . "-latest.tar.gz";
        
        $downloadContent = @file_get_contents($downloadUrl);
        if ($downloadContent === false) {
            throw new Exception("Failed to download from " . $downloadUrl);
        }
        
        if (@file_put_contents($downloadFile, $downloadContent) === false) {
            throw new Exception("Failed to write download file to " . $downloadFile);
        }

        // Extract
        echo "Extracting...\n";
        $extractCmd = "cd " . escapeshellarg($targetDir) . " && HOME=" . $tempDir . " tar xvfz " . escapeshellarg($downloadFile) . " --strip-components=1";
        $extractResult = system($extractCmd, $extractStatus);
        
        if ($extractStatus !== 0) {
            throw new Exception("Failed to extract archive. Command returned status: " . $extractStatus);
        }

        // Install dependencies if composer.json exists
        $composerJson = $targetDir . "/composer.json";
        if (file_exists($composerJson)) {
            echo "Installing dependencies...\n";
            $installCmd = "cd " . escapeshellarg($targetDir) . " && COMPOSER_HOME=" . $tempDir . " /usr/bin/composer --no-ansi -v install";
            $installResult = system($installCmd, $installStatus);
            
            if ($installStatus !== 0) {
                throw new Exception("Failed to install dependencies. Command returned status: " . $installStatus);
            }
        } else {
            echo "No composer.json found, skipping dependency installation.\n";
        }

        // Cleanup
        if (file_exists($downloadFile)) {
            unlink($downloadFile);
        }

        // Verify installation
        if (file_exists($targetDir . "/vendor")) {
            echo "Package successfully installed!\n";
            return true;
        } else {
            echo "Package installed, but no vendor directory found.\n";
            return true;
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        echo "Installation failed.\n";
        return false;
    }
}

// Example usage of version check
echo "<pre>";
$versionInfo = checkVersion($TARGET_DIR, $GITHUB_REPO);
echo "Version Information:\n";
echo "Installed: " . ($versionInfo['installed'] ? "Yes" : "No") . "\n";
if ($versionInfo['installed']) {
    echo "Current Version: " . $versionInfo['current_version'] . "\n";
}
if ($versionInfo['latest_version']) {
    echo "Latest Version: " . $versionInfo['latest_version'] . "\n";
    if ($versionInfo['installed']) {
        echo "Update Available: " . ($versionInfo['update_available'] ? "Yes" : "No") . "\n";
    }
}
echo "</pre>";

// Execute installation if needed
if (!$versionInfo['installed'] || $versionInfo['update_available']) {
    echo "<pre>";
    $success = installPackage($DOWNLOAD_URL, $TARGET_DIR, $TEMP_DIR, $PACKAGE_NAME);
    echo "</pre>";

    if (!$success) {
        exit(1);
    }
}
?>