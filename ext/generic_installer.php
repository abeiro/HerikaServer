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
 ******************************************************************************/

// Global configuration

$PACKAGE_NAME = $_GET["PACKAGE_NAME"];
$GITHUB_REPO = $_GET["GITHUB_REPO"];
$DOWNLOAD_URL = "https://github.com/" . $GITHUB_REPO . "/releases/latest/download/" . $PACKAGE_NAME . ".tar.gz";
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
        echo "No migrations directory found, skipping database migrations.\n";
        return true;
    }

    try {
        $conn = connectToDatabase();
        
        // Create migrations table if it doesn't exist
        $createTableSql = "
            CREATE TABLE IF NOT EXISTS plugin_migrations (
                plugin_name VARCHAR(255),
                migration_name VARCHAR(255),
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (plugin_name, migration_name)
            )
        ";
        pg_query($conn, $createTableSql);

        // Get list of migration files
        $migrations = glob($migrationsDir . "/*.sql");
        if (empty($migrations)) {
            echo "No migration files found in $migrationsDir\n";
            return true;
        }

        // Sort migrations by filename
        sort($migrations);
        
        global $PACKAGE_NAME;
        foreach ($migrations as $migrationFile) {
            $migrationName = basename($migrationFile);
            
            // Check if migration has been executed
            $checkSql = "
                SELECT 1 FROM plugin_migrations 
                WHERE plugin_name = $1 AND migration_name = $2
            ";
            $result = pg_query_params($conn, $checkSql, [$PACKAGE_NAME, $migrationName]);
            
            if (pg_num_rows($result) === 0) {
                echo "Running migration: $migrationName\n";
                
                // Read and execute migration file
                $sql = file_get_contents($migrationFile);
                pg_query($conn, $sql);
                
                // Record migration
                $recordSql = "
                    INSERT INTO plugin_migrations (plugin_name, migration_name)
                    VALUES ($1, $2)
                ";
                pg_query_params($conn, $recordSql, [$PACKAGE_NAME, $migrationName]);
                
                echo "Migration completed: $migrationName\n";
            } else {
                echo "Skipping already executed migration: $migrationName\n";
            }
        }
        
        pg_close($conn);
        return true;
    } catch (Exception $e) {
        echo "Error running migrations: " . $e->getMessage() . "\n";
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
    
    // GitHub API returns file content as base64 encoded
    $manifestContent = base64_decode($data['content']);
    $manifest = json_decode($manifestContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($manifest['version'])) {
        return false;
    }
    
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

        // Run database migrations if they exist
        echo "Checking for database migrations...\n";
        if (!runDatabaseMigrations($targetDir)) {
            throw new Exception("Failed to run database migrations");
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