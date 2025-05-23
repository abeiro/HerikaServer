<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/*******************************************************************************
 * CHIM Plugin Database Migration System
 * ===================================
 * 
 * This installer includes an automatic database migration system for plugins.
 * It allows plugins to manage their own database schemas and updates safely.
 * 
 * How to Use Migrations in Your Plugin:
 * -----------------------------------
 * 1. Create a 'migrations' directory in your plugin's root folder:
 *    my_plugin/
 *    ├── migrations/
 *    ├── manifest.json
 *    └── other files...
 * 
 * 2. Add SQL migration files in the migrations directory:
 *    - Name files with a numeric prefix for ordering
 *    - Use .sql extension
 *    - Example names:
 *      001_initial_schema.sql
 *      002_add_indexes.sql
 *      003_add_new_feature.sql
 * 
 * 3. Write your SQL migrations:
 *    -- Example migration file (001_initial_schema.sql):
 *    CREATE TABLE IF NOT EXISTS my_plugin_data (
 *        id SERIAL PRIMARY KEY,
 *        name VARCHAR(255) NOT NULL,
 *        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *    );
 * 
 * How It Works:
 * ------------
 * 1. During plugin installation/update, the system:
 *    - Checks for a 'migrations' directory
 *    - Creates plugin_migrations table if it doesn't exist
 *    - Reads all .sql files in the migrations directory
 *    - Executes migrations in alphabetical order
 * 
 * 2. Migration tracking:
 *    - Each executed migration is recorded in plugin_migrations table
 *    - Migrations are tracked per plugin
 *    - Migrations only run once, even on reinstall
 * 
 * Best Practices:
 * -------------
 * 1. Always use IF EXISTS/IF NOT EXISTS in your DDL statements
 * 2. Make migrations idempotent (safe to run multiple times)
 * 3. Never modify existing migration files after release
 * 4. Add new migrations for schema changes
 * 5. Use descriptive names for migration files
 * 6. Test migrations on a development database first
 * 
 * Example Migration Files:
 * ----------------------
 * -- 001_initial_schema.sql
 * CREATE TABLE IF NOT EXISTS my_plugin_users (
 *     id SERIAL PRIMARY KEY,
 *     username VARCHAR(255) NOT NULL
 * );
 * 
 * -- 002_add_email_field.sql
 * ALTER TABLE my_plugin_users 
 * ADD COLUMN IF NOT EXISTS email VARCHAR(255);
 * 
 * -- 003_add_indexes.sql
 * CREATE INDEX IF NOT EXISTS idx_username 
 * ON my_plugin_users(username);
 * 
 * Notes on github plugin download
 * 
 * * Expects a file called <PACKAGE_NAME>.tar.gz on every release (ex. twitch-bot.tar.gz)
 * * Work on github as usual. Once coding is done, update manifest.json version and push to github
 * * Create a new release, tag must be a number (ex 1.0.3)
 * * Make sure release has file <PACKAGE_NAME>.tar.gz. Download source code and upload it with that name.
 * * Generic installer should care about the updating
 * 
 * ----------------------
 ******************************************************************************/

// Global configuration

$PACKAGE_NAME = $_GET["PACKAGE_NAME"];
$GITHUB_REPO = $_GET["GITHUB_REPO"];
// We'll try both formats, starting with .tar.gz
$DOWNLOAD_URL_GZ = "https://github.com/" . $GITHUB_REPO . "/releases/latest/download/" . $PACKAGE_NAME . ".tar.gz";
$DOWNLOAD_URL_TAR = "https://github.com/" . $GITHUB_REPO . "/releases/latest/download/" . $PACKAGE_NAME . ".tar";
$TARGET_DIR = __DIR__ . "/" . $PACKAGE_NAME;
$TEMP_DIR = "/tmp/";

// Database configuration
$DB_CONFIG = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => 'dwemer',
    'schema' => 'public',
    'username' => 'dwemer',
    'password' => 'dwemer'
];

/**
 * Establishes a database connection
 * 
 * @return resource|false PostgreSQL connection resource or false on failure
 */
function connectToDatabase() {
    global $DB_CONFIG;
    
    $connStr = sprintf(
        "host=%s port=%s dbname=%s user=%s password=%s",
        $DB_CONFIG['host'],
        $DB_CONFIG['port'],
        $DB_CONFIG['dbname'],
        $DB_CONFIG['username'],
        $DB_CONFIG['password']
    );
    
    $conn = pg_connect($connStr);
    if (!$conn) {
        // For HTML output, avoid die() or direct echo if possible, throw exception
        throw new Exception("Failed to connect to database: " . pg_last_error());
    }
    return $conn;
}

/**
 * Runs database migrations for a plugin
 * 
 * @param string $targetDir Directory containing the plugin
 * @return bool True if migrations were successful, false otherwise
 */
function runDatabaseMigrations($targetDir) {
    $migrationsDir = $targetDir . "/migrations";
    if (!is_dir($migrationsDir)) {
        echo "<p class='log-info'>ℹ️ No migrations directory found, skipping database migrations.</p>\n";
        return true;
    }

    try {
        $conn = connectToDatabase();
        
        $createTableSql = "
            CREATE TABLE IF NOT EXISTS plugin_migrations (
                plugin_name VARCHAR(255),
                migration_name VARCHAR(255),
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (plugin_name, migration_name)
            )
        ";
        pg_query($conn, $createTableSql);

        $migrations = glob($migrationsDir . "/*.sql");
        if (empty($migrations)) {
            echo "<p class='log-info'>ℹ️ No migration files found in $migrationsDir</p>\n";
            return true;
        }

        sort($migrations);
        
        global $PACKAGE_NAME;
        foreach ($migrations as $migrationFile) {
            $migrationName = basename($migrationFile);
            
            $checkSql = "
                SELECT 1 FROM plugin_migrations 
                WHERE plugin_name = $1 AND migration_name = $2
            ";
            $result = pg_query_params($conn, $checkSql, [$PACKAGE_NAME, $migrationName]);
            
            if (pg_num_rows($result) === 0) {
                echo "<p class='log-running'>⚙️ Running migration: $migrationName</p>\n";
                $sql = file_get_contents($migrationFile);
                pg_query($conn, $sql);
                
                $recordSql = "
                    INSERT INTO plugin_migrations (plugin_name, migration_name)
                    VALUES ($1, $2)
                ";
                pg_query_params($conn, $recordSql, [$PACKAGE_NAME, $migrationName]);
                echo "<p class='log-completed'>✅ Migration completed: $migrationName</p>\n";
            } else {
                echo "<p class='log-skipped'>ℹ️ Skipping already executed migration: $migrationName</p>\n";
            }
        }
        
        pg_close($conn);
        return true;
    } catch (Exception $e) {
        echo "<p class='log-error'>❌ Error running migrations: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        return false;
    }
}

/**
 * Ensures the target directory exists and is writable
 * 
 * @param string $targetDir The directory to check/create
 * @return bool True if directory exists and is writable, false otherwise
 */
function ensureTargetDirectory($targetDir) {
    if (!file_exists($targetDir)) {
        echo "<p class='log-info'>ℹ️ Creating target directory: " . htmlspecialchars($targetDir) . "</p>\n";
        if (!mkdir($targetDir, 0755, true)) {
            throw new Exception("Failed to create target directory: " . htmlspecialchars($targetDir));
        }
    }
    
    if (!is_writable($targetDir)) {
        throw new Exception("Target directory is not writable: " . htmlspecialchars($targetDir));
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
    if (!file_exists($targetDir)) {
        return false;
    }
    $manifestPath = $targetDir . "/manifest.json";
    if (!file_exists($manifestPath)) {
        return false;
    }
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid manifest.json file in " . htmlspecialchars($targetDir));
    }
    if (!isset($manifest['version'])) {
        throw new Exception("Version not found in manifest.json in " . htmlspecialchars($targetDir));
    }
    return ['installed' => true, 'version' => $manifest['version']];
}

/**
 * Gets the latest version from GitHub by checking manifest.json
 * 
 * @param string $githubRepo Repository in format owner/repo
 * @return string|false Returns version string or false on failure
 */
function getRemoteVersion($githubRepo) {
    $apiUrl = "https://api.github.com/repos/" . $githubRepo . "/contents/manifest.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CHIM Plugin Installer');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) { return false; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return false; }
    $manifestContent = base64_decode($data['content']);
    $manifest = json_decode($manifestContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($manifest['version'])) { return false; }
    return $manifest['version'];
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
        'update_available' => false,
        'error' => null
    ];
    try {
        $localInfo = checkLocalVersion($targetDir);
        if ($localInfo !== false) {
            $result['installed'] = true;
            $result['current_version'] = $localInfo['version'];
        }
        $remoteVersion = getRemoteVersion($githubRepo);
        if ($remoteVersion !== false) {
            $result['latest_version'] = $remoteVersion;
            if ($result['installed']) {
                $result['update_available'] = version_compare($remoteVersion, $result['current_version'], '>');
            }
        }
    } catch (Exception $e) {
        // Instead of echoing, store the error message
        $result['error'] = "Error checking versions: " . $e->getMessage();
    }
    return $result;
}

/**
 * Installs a package from a remote archive
 * 
 * @param string $downloadUrlGz The URL to download the tar.gz package from
 * @param string $downloadUrlTar The URL to download the tar package from (fallback)
 * @param string $targetDir The directory where files will be installed
 * @param string $tempDir The temporary directory for operations
 * @param string $packageName The name of the package (used for downloaded file)
 * @return bool True if installation was successful, false otherwise
 */
function installPackage($downloadUrlGz, $downloadUrlTar, $targetDir, $tempDir, $packageName) {
    try {
        ensureTargetDirectory($targetDir);
        
        // Try to download .tar.gz first
        echo "<p class='log-action'>⚙️ Trying to download .tar.gz package...</p>\n";
        $isGzipped = true;
        $downloadFile = $targetDir . "/" . $packageName . "-latest.tar.gz";
        $downloadContent = @file_get_contents($downloadUrlGz);
        
        // If .tar.gz fails, try .tar
        if ($downloadContent === false) {
            echo "<p class='log-info'>ℹ️ .tar.gz not found, trying .tar format...</p>\n";
            $isGzipped = false;
            $downloadFile = $targetDir . "/" . $packageName . "-latest.tar";
            $downloadContent = @file_get_contents($downloadUrlTar);
            
            if ($downloadContent === false) {
                throw new Exception("Failed to download from both " . htmlspecialchars($downloadUrlGz) . " and " . htmlspecialchars($downloadUrlTar));
            }
        }
        
        if (@file_put_contents($downloadFile, $downloadContent) === false) {
            throw new Exception("Failed to write download file to " . htmlspecialchars($downloadFile));
        }
        
        echo "<p class='log-action'>⚙️ Extracting " . ($isGzipped ? "gzipped" : "non-gzipped") . " package...</p>\n";
        
        // Use the appropriate tar flags based on the format
        $tarFlags = $isGzipped ? "xvfz" : "xvf";
        $extractCmd = "cd " . escapeshellarg($targetDir) . " && HOME=" . escapeshellarg($tempDir) . " tar " . $tarFlags . " " . escapeshellarg($downloadFile) . " --strip-components=1";
        
        // Capture system command output
        ob_start();
        system($extractCmd, $extractStatus);
        $extractOutput = ob_get_clean();
        echo "<div class='system-command-output'>" . nl2br(htmlspecialchars($extractOutput)) . "</div>";

        if ($extractStatus !== 0) {
            throw new Exception("Failed to extract archive. Command returned status: " . $extractStatus);
        }
        
        echo "<p class='log-info'>ℹ️ Checking for database migrations...</p>\n";
        if (!runDatabaseMigrations($targetDir)) {
            throw new Exception("Failed to run database migrations");
        }
        $composerJson = $targetDir . "/composer.json";
        if (file_exists($composerJson)) {
            echo "<p class='log-action'>⚙️ Installing dependencies with Composer...</p>\n";
            $installCmd = "cd " . escapeshellarg($targetDir) . " && COMPOSER_HOME=" . escapeshellarg($tempDir) . " /usr/bin/composer --no-ansi -v install";
            ob_start();
            system($installCmd, $installStatus);
            $installOutput = ob_get_clean();
            echo "<div class='system-command-output'>" . nl2br(htmlspecialchars($installOutput)) . "</div>";

            if ($installStatus !== 0) {
                throw new Exception("Failed to install dependencies. Composer returned status: " . $installStatus);
            }
        } else {
            echo "<p class='log-info'>ℹ️ No composer.json found, skipping dependency installation.</p>\n";
        }
        if (file_exists($downloadFile)) {
            unlink($downloadFile);
        }
        echo "<p class='log-success'>✅ Package successfully installed/updated!</p>\n";
        return true;
    } catch (Exception $e) {
        echo "<p class='log-error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "<p class='log-failed'>❌ Installation failed.</p>\n";
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHIM Plugin Installer: <?php echo htmlspecialchars($PACKAGE_NAME); ?></title>
    <link rel="icon" type="image/x-icon" href="../ui/images/favicon.ico">
    <link rel="stylesheet" href="../ui/css/main.css">
    <style>
        body {
            padding: 20px;
            background-color: #2c2c2c; /* Matches main.css body */
            color: #f8f9fa; /* Matches main.css body */
            font-family: 'Futura CondensedLight', Arial, sans-serif; /* Base font from main.css */
        }
        .installer-container {
            max-width: 900px;
            margin: 40px auto; /* Added top/bottom margin for spacing */
            background-color: #1a1a1a; /* Darker panel background */
            padding: 30px; /* Increased padding */
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            border: 1px solid #3a3a3a;
        }
        /* Main page title - styled like h1 from oghma_upload.php and main.css */
        .installer-container > h1 {
            font-family: 'MagicCards', sans-serif; /* Consistent with main.css h1 */
            font-size: 32px; /* Consistent with main.css h1 */
            font-weight: normal;
            letter-spacing: 0.5px;
            word-spacing: 8px;
            color:rgb(255, 255, 255); /* Retain prominent yellow for main title text */
            margin-bottom: 30px; /* Increased margin */
            text-align: center;
            border-bottom: none; /* Cleaner look */
            padding-bottom: 0;
            display: flex; /* For aligning image and text */
            align-items: center; /* Vertically align image and text */
            justify-content: center; /* Center content */
        }
        .installer-container > h1 img {
            vertical-align: bottom; /* Align image with text */
            width: 32px; /* Adjust as needed */
            height: 32px; /* Adjust as needed */
            margin-right: 10px; /* Space between image and text */
        }

        /* Section titles - using orange-yellow from oghma_upload labels */
        .installer-container h3 {
            font-family: 'Futura CondensedLight', Arial, sans-serif; /* Consistent with main.css h3 */
            color: rgb(242, 124, 17); /* Orange-yellow from oghma labels */
            margin-top: 30px; /* Increased margin */
            margin-bottom: 15px;
            font-size: 22px; /* Consistent with main.css h3 */
            border-bottom: 1px solid #3a3a3a; /* Add a light separator for sections */
            padding-bottom: 8px;
        }
        .version-info-block {
            background-color: #2d2d2d; /* Slightly lighter than container for contrast */
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
            border: 1px solid #4a4a4a;
        }
        .version-info-block p {
            margin: 10px 0; /* Consistent paragraph spacing */
            font-size: 16px; /* Retain for dense info */
            line-height: 1.6;
        }
        .version-info-block strong {
            color: #f0ad4e; /* Retain amber highlight */
            font-weight: bold; /* Ensure boldness */
        }
        .installer-log {
            background-color: #111; /* Dark background for logs */
            color: #ccc;
            padding: 20px;
            border-radius: 6px;
            font-family: 'Spline Sans Mono', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid #333;
            margin-top: 15px;
        }
        .installer-log p { margin: 6px 0; padding: 3px 0; line-height: 1.5; }
        .log-info { color: #5bc0de; } 
        .log-action { color: #f0ad4e; }
        .log-running { color: #f0ad4e; }
        .log-completed { color: #28a745; }
        .log-success { color: #28a745; font-weight: bold; }
        .log-skipped { color: #888; } /* Adjusted for better visibility */
        .log-error { color: #d9534f; font-weight: bold; }
        .log-failed { color: #d9534f; font-weight: bold; font-size: 1.1em; }
        .system-command-output {
            border-left: 3px solid #444;
            padding-left: 10px;
            margin: 8px 0 12px 15px; /* Adjusted margin */
            font-size: 0.85em; /* Slightly smaller for dense output */
            color: #aaa; /* Lighter grey for visibility */
        }
        .status-message {
            padding: 15px 20px; /* Adjusted padding */
            margin-top: 25px;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
            font-size: 1.05em; /* Slightly larger status messages */
        }
        .status-success { background-color: #28a745; color: white; border: 1px solid #1e7e34; }
        .status-error { background-color: #d9534f; color: white; border: 1px solid #c9302c; }
        .back-button {
            margin-bottom: 30px !important; /* Increased margin */
            /* Leverage btn-primary from main.css, but ensure specificity if needed */
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <h1>CHIM Plugin Installer</h1>
        <a href="../ui/index.php?plugins_show=true" class="button btn-primary back-button">&laquo; Back to Plugin Manager</a>

        <?php
        $versionInfo = checkVersion($TARGET_DIR, $GITHUB_REPO);

        echo '<div class="version-info-block">';
        echo '<h3>Version Information</h3>';
        if ($versionInfo['error']) {
            echo '<p class="log-error">' . htmlspecialchars($versionInfo['error']) . '</p>';
        }
        echo '<p><strong>Package:</strong> ' . htmlspecialchars($PACKAGE_NAME) . '</p>';
        echo '<p><strong>GitHub Repo:</strong> ' . htmlspecialchars($GITHUB_REPO) . '</p>';
        if ($versionInfo['installed'] && $versionInfo['current_version']) {
            echo '<p><strong>Current Version:</strong> ' . htmlspecialchars($versionInfo['current_version']) . '</p>';
        }
        if ($versionInfo['latest_version']) {
            echo '<p><strong>Latest Version:</strong> ' . htmlspecialchars($versionInfo['latest_version']) . '</p>';
            if ($versionInfo['installed'] && $versionInfo['current_version']) {
                 $update_color = $versionInfo['update_available'] ? '#28a745' : '#6c757d'; // Green if update, gray if not
                 $update_text = $versionInfo['update_available'] ? 'Yes' : 'No';
                echo '<p><strong>Update Available:</strong> <span style="color: ' . $update_color . ';">' . $update_text . '</span></p>';
            }
        } else if (!$versionInfo['error']) {
            echo '<p class="log-error">Could not retrieve latest version information from GitHub.</p>';
        }
        echo '</div>';

        if ($versionInfo['error']) {
            echo '<div class="status-message status-error">Could not proceed due to version check errors.</div>';
        } elseif (!$versionInfo['installed'] || $versionInfo['update_available']) {
            echo '<h3>Installation Log</h3>';
            echo '<div class="installer-log">';
            // Updated to pass both download URLs
            $success = installPackage($DOWNLOAD_URL_GZ, $DOWNLOAD_URL_TAR, $TARGET_DIR, $TEMP_DIR, $PACKAGE_NAME);
            echo '</div>'; // Close installer-log

            if ($success) {
                echo '<div class="status-message status-success">Installation/Update process completed! Check log for details.</div>';
            } else {
                echo '<div class="status-message status-error">Installation/Update process failed. Please check the log above.</div>';
            }
        } else {
            echo '<div class="status-message status-success">Plugin is already installed and up to date.</div>';
        }
        ?>
    </div>
</body>
</html>