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
require_once($enginePath."lib".DIRECTORY_SEPARATOR."logger.php");


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
$hash = isset($matches[1]) ? $matches[1] : 'default';    

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

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Initialize message variable
$message = '';

// PHP function to format file sizes
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}



// Include automatic backup management
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "automatic_backup.php");

// Handle download automatic backup
if (isset($_GET['action']) && $_GET['action'] === 'download_auto' && isset($_GET['filename'])) {
    $autoBackup = new AutomaticBackup();
    $filename = $_GET['filename'];
    
    // Security check
    if (strpos($filename, 'auto_backup_') === 0 && substr($filename, -4) === '.sql') {
        $backups = $autoBackup->getBackups();
        $validFile = false;
        
        foreach ($backups as $backup) {
            if ($backup['filename'] === $filename) {
                $validFile = true;
                $backupPath = $backup['filepath'];
                break;
            }
        }
        
        if ($validFile && file_exists($backupPath)) {
            // Force download of the backup file
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($backupPath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Clear any output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Output the file
            readfile($backupPath);
            exit();
        } else {
            $message = "<p><strong>Error:</strong> Backup file not found.</p>";
        }
    } else {
        $message = "<p><strong>Error:</strong> Invalid filename format.</p>";
    }
}

// Handle delete automatic backup
if (isset($_GET['action']) && $_GET['action'] === 'delete_auto' && isset($_GET['filename'])) {
    $autoBackup = new AutomaticBackup();
    $filename = $_GET['filename'];
    
    // Security check
    if (strpos($filename, 'auto_backup_') === 0 && substr($filename, -4) === '.sql') {
        if ($autoBackup->deleteBackup($filename)) {
            $message = "<p><strong>✅ Automatic backup deleted successfully!</strong></p>";
            $message .= "<p>Deleted: <strong>$filename</strong></p>";
        } else {
            $message = "<p><strong>Error:</strong> Failed to delete backup file.</p>";
        }
    } else {
        $message = "<p><strong>Error:</strong> Invalid filename format.</p>";
    }
}

// Handle restore from automatic backup
if (isset($_GET['action']) && $_GET['action'] === 'restore_auto' && isset($_GET['filename'])) {
    $autoBackup = new AutomaticBackup();
    $filename = $_GET['filename'];
    
    // Security check
    if (strpos($filename, 'auto_backup_') === 0 && substr($filename, -4) === '.sql') {
        $backups = $autoBackup->getBackups();
        $validFile = false;
        
        foreach ($backups as $backup) {
            if ($backup['filename'] === $filename) {
                $validFile = true;
                $backupPath = $backup['filepath'];
                break;
            }
        }
        
        if ($validFile && file_exists($backupPath)) {
            // Proceed with database restore using the automatic backup
            $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
            
            if (!$conn) {
                $message .= "<p><strong>Error:</strong> Failed to connect to database: " . pg_last_error() . "</p>";
            } else {
                // Drop and recreate database schema and extensions
                $Q = array();
                $Q[] = "DROP SCHEMA IF EXISTS $schema CASCADE";
                $Q[] = "DROP EXTENSION IF EXISTS vector CASCADE";
                $Q[] = "DROP EXTENSION IF EXISTS pg_trgm CASCADE";
                $Q[] = "CREATE SCHEMA $schema";
                $Q[] = "CREATE EXTENSION vector";
                $Q[] = "CREATE EXTENSION IF NOT EXISTS pg_trgm";

                $errorOccurred = false;

                foreach ($Q as $QS) {
                    $r = pg_query($conn, $QS);
                    if (!$r) {
                        $message .= "<p>Error executing query: " . pg_last_error($conn) . "</p>";
                        $errorOccurred = true;
                        break;
                    } else {
                        $message .= "<p>$QS executed successfully.</p>";
                    }
                }

                if (!$errorOccurred) {
                    // Command to import SQL file using psql
                    $psqlCommand = "PGPASSWORD=" . escapeshellarg($password) . " psql -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($username) . " -d " . escapeshellarg($dbname) . " -f " . escapeshellarg($backupPath);

                    // Execute psql command
                    $output = [];
                    $returnVar = 0;
                    exec($psqlCommand, $output, $returnVar);

                    if ($returnVar !== 0) {
                        $message .= "<p>Failed to restore from automatic backup.</p>";
                        $message .= '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                    } else {
                        $message .= "<p><strong>✅ Database restored successfully from automatic backup!</strong></p>";
                        $message .= "<p>Restored from: <strong>$filename</strong></p>";
                        $message .= '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';

                        // Provide a clickable link and popup message
                        $redirectUrl = '/HerikaServer/ui/home.php';
                        $message .= "<script type='text/javascript'>
                                        alert('Database restored successfully from automatic backup.');
                                     </script>";
                    }
                }
                pg_close($conn);
            }
        } else {
            $message = "<p><strong>Error:</strong> Invalid backup file specified.</p>";
        }
    } else {
        $message = "<p><strong>Error:</strong> Invalid filename format.</p>";
    }
}

// Handle backup database request
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    try {
        // Create authentication setup (same as AutomaticBackup class)
        $pgpassResult = shell_exec('echo "localhost:5432:dwemer:dwemer:dwemer" > /tmp/.pgpass; echo $?');
        $chmodResult = shell_exec('chmod 600 /tmp/.pgpass; echo $?');
        
        $filename = "manual_backup_" . date("Y-m-d_H-i-s") . ".sql";
        $backupFile = $rootPath . 'data/export_' . $filename;
        
        // Execute pg_dump with direct file output to avoid memory issues
        $command = "HOME=/tmp pg_dump -d dwemer -U dwemer -h localhost > " . escapeshellarg($backupFile) . " 2>&1";
        $result = shell_exec($command);
        
        // pg_dump writes directly to file, so we don't need to handle output in memory
        
        // Check if backup was created successfully
        if (file_exists($backupFile) && filesize($backupFile) > 0) {
            $fileSize = filesize($backupFile);
            
            // Check if the file contains error messages instead of actual backup data
            $firstLine = file_get_contents($backupFile, false, null, 0, 100);
            if (strpos($firstLine, 'pg_dump: error:') !== false || strpos($firstLine, 'FATAL:') !== false) {
                $message = "<p><strong>Error:</strong> Database backup failed.</p>";
                $message .= "<pre>" . htmlspecialchars(substr($firstLine, 0, 500)) . "</pre>";
                if (file_exists($backupFile)) {
                    unlink($backupFile);
                }
            } else {
                // Successful backup - force download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="dwemer_backup_' . $filename . '"');
                header('Content-Length: ' . $fileSize);
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                
                // Clear any output buffer
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Output the file
                readfile($backupFile);
                
                // Clean up - delete the temporary file
                unlink($backupFile);
                
                exit();
            }
        } else {
            $message = "<p><strong>Error:</strong> Backup creation failed or file is empty.</p>";
            if ($result) {
                $message .= "<p><strong>pg_dump output:</strong></p>";
                $message .= "<pre>" . htmlspecialchars(substr($result, 0, 1000)) . "</pre>";
            }
        }
        
    } catch (Exception $e) {
        $message = "<p><strong>Error:</strong> Exception during backup creation: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if a file was uploaded without errors
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        // Validate the uploaded file
        $fileTmpPath = $_FILES['sql_file']['tmp_name'];
        $fileName = $_FILES['sql_file']['name'];
        $fileSize = $_FILES['sql_file']['size'];
        $fileType = $_FILES['sql_file']['type'];

        // Allowed file extensions
        $allowedfileExtensions = array('sql');

        // Get file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory where the uploaded file will be moved
            $uploadFileDir = $rootPath . 'data' . DIRECTORY_SEPARATOR;
            $destPath = $uploadFileDir . 'dwemer.sql';

            // Ensure the upload directory exists
            if (!file_exists($uploadFileDir)) {
                Logger::info("Creating $uploadFileDir");
                mkdir($uploadFileDir, 0755, true);
            }

            // Move the file to the destination directory with the new name
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Proceed to restore the database
                // Connect to the database
                $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

                if (!$conn) {
                    $message .= "<p>Failed to connect to database: " . pg_last_error() . "</p>";
                } else {
                    // Drop and recreate database schema and extensions
                    $Q = array();
                    $Q[] = "DROP SCHEMA IF EXISTS $schema CASCADE";
                    $Q[] = "DROP EXTENSION IF EXISTS vector CASCADE";
                    $Q[] = "DROP EXTENSION IF EXISTS pg_trgm CASCADE";
                    $Q[] = "CREATE SCHEMA $schema";
                    $Q[] = "CREATE EXTENSION vector";
                    $Q[] = "CREATE EXTENSION IF NOT EXISTS pg_trgm";

                    $errorOccurred = false;

                    foreach ($Q as $QS) {
                        $r = pg_query($conn, $QS);
                        if (!$r) {
                            $message .= "<p>Error executing query: " . pg_last_error($conn) . "</p>";
                            $errorOccurred = true;
                            break;
                        } else {
                            $message .= "<p>$QS executed successfully.</p>";
                        }
                    }

                    if (!$errorOccurred) {
                        // Path to SQL file to import
                        $sqlFile = $destPath;

                        // Command to import SQL file using psql
                        $psqlCommand = "PGPASSWORD=" . escapeshellarg($password) . " psql -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($username) . " -d " . escapeshellarg($dbname) . " -f " . escapeshellarg($sqlFile);

                        // Execute psql command
                        $output = [];
                        $returnVar = 0;
                        exec($psqlCommand, $output, $returnVar);

                        if ($returnVar !== 0) {
                            $message .= "<p>Failed to import SQL file.</p>";
                            $message .= '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                        } else {
                            $message .= "<p>SQL file imported successfully.</p>";
                            $message .= '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                            $message .= "<p>Import completed.</p>";

                            // Provide a clickable link and popup message
                            $redirectUrl = '/HerikaServer/ui/home.php';
                            $message .= "<script type='text/javascript'>
                                            alert('Database restored successfully.');
                                         </script>";
                        }
                    }

                    // Close the database connection
                    pg_close($conn);
                }
            } else {
                $message .= '<p>There was an error moving the uploaded file.</p>';
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
    <title>Database Manager</title>
    <style>
        /* Database Manager - Using main.css consistent color scheme */
        body {
            font-family: 'Futura CondensedLight', Arial, sans-serif;
            background-color: #2c2c2c;
            color: #f8f9fa;
            font-size: 18px;
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #ffffff;
            font-family: 'Futura CondensedLight', Arial, sans-serif;
            margin-bottom: 15px;
        }

        h1 {
            font-size: 32px;
            font-family: 'MagicCards', sans-serif;
            font-weight: normal;
            letter-spacing: 0.5px;
            word-spacing: 8px;
        }

        label {
            font-weight: bold;
            color: #f8f9fa;
        }

        .message {
            background-color: rgba(30, 35, 45, 0.8);
            border: 1px solid rgba(138, 155, 182, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            color: #f8f9fa;
            height: fit-content;
            backdrop-filter: blur(5px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                        inset 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease-in-out;
        }

        .message:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px rgba(255, 255, 255, 0.15);
        }

        .message p {
            margin: 0 0 10px 0;
            line-height: 150%;
            font-size: 16px;
        }

        .response-container {
            margin-top: 20px;
        }

        .indent {
            padding-left: 10ch;
        }

        .indent5 {
            padding-left: 5ch;
            padding-right: 20px;
        }

        .button {
            padding: 10px 20px;
            color: #ffffff;
            background-color: rgba(30, 35, 45, 0.8);
            border: 1px solid rgba(138, 155, 182, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease-in-out;
            margin: 5px;
            font-weight: 500;
            letter-spacing: 0.3px;
            backdrop-filter: blur(5px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                        inset 0 1px rgba(255, 255, 255, 0.1);
        }

        .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px rgba(255, 255, 255, 0.15);
            text-decoration: none;
            color: #ffffff;
        }

        .button:active {
            transform: translateY(1px);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2),
                        inset 0 1px rgba(255, 255, 255, 0.1);
        }

        /* Form elements using main.css styling */
        input[type="text"],
        input[type="file"],
        select {
            background-color: #4a4a4a;
            color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #555555;
            cursor: pointer;
            width: auto;
        }

        input[type="file"]::-webkit-file-upload-button {
            background-color: #6c757d;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.3s ease;
            font-size: 14px;
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background-color: #5a6268;
        }

        pre {
            background-color: #2c2c2c;
            padding: 10px;
            border: 1px solid #4a4a4a;
            border-radius: 8px;
            color: #f8f9fa;
            overflow: auto;
        }

        code {
            background-color: #000000;
            padding: 2px 4px;
            border-radius: 3px;
            color: #f8f9fa;
        }

        /* Progress bar styling */
        #progressBar {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            border: 1px solid #4a4a4a;
        }

        /* Backup list container */
        .backup-list {
            background-color: #1a1a1a;
            border: 1px solid #333333;
            border-radius: 8px;
        }

        .backup-item {
            border-bottom: 1px solid #333333;
        }

        .backup-item:hover {
            background-color: #1f1f1f;
        }
    </style>
</head>
<body>
<div class="indent5">
    <h1>Database Manager</h1>
    

    
    <!-- Main Grid Container -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; min-height: 220px;">
        
        <!-- Database Manager Section -->
        <div class="message" style="background-color: #2c3440; border: 1px solid #4a4a4a; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3>🗄️ Database Access</h3>
                <p>Access the pgAdmin database manager for advanced database management.</p>
                <p><strong>Login:</strong> username = dwemer & password = dwemer</p>
            </div>
            <div style="margin-top: auto;">
                <a href="/pgAdmin/" target="_blank" class="button" style="background-color: rgb(1 53 166 / 90%); color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; width: 100%; text-align: center;">
                    Open Database Manager
                </a>
            </div>
        </div>
        
        <!-- Backup Section -->
        <div class="message" style="background-color: #374151; border: 1px solid #4a4a4a; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3>📦 Manual Backup</h3>
                <p>Create a backup of your current database. This will generate an SQL file you can download.</p>
                <p style="color: #ccc; font-size: 14px; margin-top: auto;">Creates a one-time downloadable backup file.</p>
            </div>
            <div style="margin-top: auto;">
                <a href="?action=backup" class="button" style="background-color: #176529; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; width: 100%; text-align: center;">
                    Create Backup
                </a>
            </div>
        </div>
        
        <!-- Maintenance Section -->
        <div class="message" style="background-color: #2d3748; border: 1px solid #4a4a4a; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3>🔧 Database Maintenance</h3>
                <p>Optimize and clean your database. This will compact the database and reclaim unused space.</p>
                <p><strong>⚠️ Important:</strong> Make sure Skyrim is stopped before running maintenance.</p>
            </div>
            <div style="margin-top: auto;">
                <button onclick="if (confirm('Database maintenance will optimize and compact the database.\n\n- Make sure Skyrim game is stopped\n- To reclaim unused space, free temporary space is required\n- During this operation tables will be locked, do not interrupt\n- This could take some time, please wait until you see the confirmation\n\nContinue?')) { window.open('<?php echo $webRoot; ?>/ui/vacuum_db.php', 'Database_maintenance', 'resizable=yes,scrollbars=yes,titlebar=no,width=800,height=600'); return false; }" 
                        class="button" style="background-color: #fd7e14; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%;">
                    Run Database Maintenance
                </button>
            </div>
        </div>
        
        <!-- Factory Reset Section -->
        <div class="message" style="background-color: #481f1f; border: 1px solid #dc3545; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3>💥 Factory Reset Database</h3>
                <p>Completely wipe and reinstall the entire database to the default configuration.</p>
                <p><strong>⚠️ DANGER:</strong> This will permanently delete data including events, diaries, and memories.</p>
            </div>
            <div style="margin-top: auto;">
                <button onclick="if (confirm('⚠️ FACTORY RESET DATABASE\n\nThis will wipe and reinstall the entire database to the default configuration.\n\n❌ ALL DATA WILL BE PERMANENTLY LOST:\n- All event logs\n- All diaries and memories\n- All custom Oghama and NPC Biography management profiles\n\n✅ Database will be reset to fresh installation state\n\nThis action CANNOT be undone!\n\nAre you absolutely sure you want to continue?')) { window.location.href = '<?php echo $webRoot; ?>/ui/index.php?reinstall=true&delete=true'; }" 
                        class="button" style="background-color: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%;">
                    Factory Reset Database
                </button>
            </div>
        </div>
        
    </div>
    
    <!-- Second Row - Automatic Backups and Manual Restore Side by Side -->
    <?php
    $autoBackup = new AutomaticBackup();
    $automaticBackups = $autoBackup->getBackups();
    $totalBackupsSize = 0;
    foreach ($automaticBackups as $backup) {
        $totalBackupsSize += $backup['size'];
    }
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        
        <!-- Left Column: Automatic Backups -->
        <div class="message" style="background-color: #3a2d48; border: 1px solid #4a4a4a;">
            <h3>🤖 Automatic Backup System</h3>
            <p>System-generated backups created automatically every time the server starts up. Keeps a maximum of 5 backups, automatically deleting the oldest when the limit is reached.</p>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 15px 0;">
                <div style="background-color: #2c2c2c; border: 1px solid #4a4a4a; padding: 10px; border-radius: 8px; text-align: center;">
                    <h5 style="margin: 0 0 5px 0; color: #f8f9fa; font-size: 14px;">Status</h5>
                    <p style="margin: 0; font-size: 16px; font-weight: bold;">
                        <?php echo $autoBackup->isEnabled() ? '<span style="color: #176529;">✅ On</span>' : '<span style="color: #dc3545;">❌ Off</span>'; ?>
                    </p>
                </div>
                
                <div style="background-color: #2c2c2c; border: 1px solid #4a4a4a; padding: 10px; border-radius: 8px; text-align: center;">
                    <h5 style="margin: 0 0 5px 0; color: #f8f9fa; font-size: 14px;">Available</h5>
                    <p style="margin: 0; font-size: 16px; font-weight: bold; color: #f8f9fa;">
                        <?php echo count($automaticBackups); ?> / 5
                    </p>
                </div>
                
                <div style="background-color: #2c2c2c; border: 1px solid #4a4a4a; padding: 10px; border-radius: 8px; text-align: center;">
                    <h5 style="margin: 0 0 5px 0; color: #f8f9fa; font-size: 14px;">Total Size</h5>
                    <p style="margin: 0; font-size: 16px; font-weight: bold; color: #eaee05;">
                        <?php echo AutomaticBackup::formatFileSize($totalBackupsSize); ?>
                    </p>
                </div>
            </div>
            
            <?php if (!$autoBackup->isEnabled()): ?>
                <div style="background-color: rgba(166, 53, 63, 0.1); border: 1px solid rgba(166, 53, 63, 0.9); border-radius: 8px; padding: 15px; margin: 15px 0;">
                    <h4 style="color: #dc3545; margin: 0 0 10px 0;">⚠️ Automatic Backups Disabled</h4>
                    <p style="margin: 0; color: #f8f9fa;">To enable automatic backups, go to the <strong>Configuration Wizard</strong> and set <code>AUTOMATIC_DATABASE_BACKUPS</code> to <strong>true</strong>.</p>
                </div>
            <?php endif; ?>
            
            <h4 style="margin: 15px 0 10px 0;">📂 Backup Management</h4>
            
            <?php if (!empty($automaticBackups)): ?>
                <div class="backup-list" style="max-height: 300px; overflow-y: auto; padding: 0; margin: 0; border: 1px solid #333333; border-radius: 8px; background-color: #1a1a1a;">
                    <?php foreach ($automaticBackups as $index => $backup): ?>
                        <div class="backup-item" style="padding: 12px; border-bottom: 1px solid #333333; transition: all 0.2s ease-in-out; <?php echo $index === count($automaticBackups) - 1 ? 'border-bottom: none;' : ''; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="flex-grow: 1; min-width: 0;">
                                    <div style="font-weight: bold; font-size: 13px; margin-bottom: 4px; word-break: break-all;">
                                        <?php echo htmlspecialchars($backup['filename']); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #ccc; display: flex; justify-content: space-between;">
                                        <span>📁 <?php echo AutomaticBackup::formatFileSize($backup['size']); ?></span>                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                <button onclick="window.location.href='?action=download_auto&filename=<?php echo urlencode($backup['filename']); ?>'" 
                                        class="button" style="background-color: #176529; color: white; padding: 4px 8px; border: none; border-radius: 3px; font-size: 11px; cursor: pointer; flex: 1; min-width: 70px;" 
                                        title="Download backup file">
                                    📥
                                </button>
                                <button onclick="if (confirm('⚠️ RESTORE DATABASE\\n\\nRestore from: <?php echo htmlspecialchars($backup['filename']); ?>\\n\\nThis will COMPLETELY REPLACE your current database with this backup.\\n\\n❌ All current data will be lost!\\n✅ Database will be restored to backup state\\n\\nAre you absolutely sure you want to continue?')) { window.location.href='?action=restore_auto&filename=<?php echo urlencode($backup['filename']); ?>'; }" 
                                        class="button" style="background-color: rgb(1 53 166 / 90%); color: white; padding: 4px 8px; border: none; border-radius: 3px; font-size: 11px; cursor: pointer; flex: 1; min-width: 70px;" 
                                        title="Restore database from this backup">
                                    🔄
                                </button>
                                <button onclick="if (confirm('⚠️ DELETE BACKUP\\n\\nDelete: <?php echo htmlspecialchars($backup['filename']); ?>\\n\\nThis action cannot be undone!\\n\\nAre you sure you want to permanently delete this backup?')) { window.location.href='?action=delete_auto&filename=<?php echo urlencode($backup['filename']); ?>'; }" 
                                        class="button" style="background-color: rgba(166, 53, 63, 0.9); color: white; padding: 4px 8px; border: none; border-radius: 3px; font-size: 11px; cursor: pointer; flex: 1; min-width: 70px;" 
                                        title="Delete this backup file">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px 20px; color: #888; font-style: italic; background-color: #2c2c2c; border-radius: 8px; border: 1px dashed #4a4a4a;">
                    <div style="font-size: 24px; margin-bottom: 10px;">📂</div>
                    <p style="margin: 0;">No automatic backups available yet.</p>
                    <?php if ($autoBackup->isEnabled()): ?>
                        <small style="color: #ffffff; display: block; margin-top: 8px;">Backups will be created on server restart.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Manual Database Restore -->
        <div class="message" style="background-color: #283344; border: 1px solid #4a4a4a;">
            <h3>📥 Manual Database Restore</h3>
            <p>Upload an SQL backup file to restore your database. This will completely replace your current database with the uploaded backup.</p>
            
            <div style="background-color: rgba(166, 53, 63, 0.1); border: 1px solid rgba(166, 53, 63, 0.9); border-radius: 8px; padding: 15px; margin: 15px 0;">
                <h4 style="color: #dc3545; margin: 0 0 10px 0;">⚠️ Important Warning</h4>
                <ul style="color: #f8f9fa; margin: 0; padding-left: 20px;">
                    <li>This will <strong>completely replace</strong> your current database</li>
                    <li>All current data will be <strong>permanently lost</strong></li>
                    <li>Make sure to create a backup before proceeding</li>
                    <li>Only upload trusted SQL files</li>
                </ul>
            </div>
            
            <form id="uploadForm" action="" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <label for="sql_file" style="color: #f8f9fa; font-weight: bold; display: block; margin-bottom: 8px;">Select SQL file to upload:</label>
                <input type="file" name="sql_file" id="sql_file" accept=".sql" required 
                       style="margin: 10px 0; padding: 12px; background-color: #4a4a4a; color: #f8f9fa; border: 1px solid #555555; border-radius: 4px; width: 100%;">
                
                <input type="submit" class="button" value="🚀 Upload and Restore Database" 
                       style="background-color: rgb(1 53 166 / 90%); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 15px; width: 100%; font-size: 16px;">
            </form>
            
            <div id="uploadProgress" style="display: none; margin-top: 15px; background-color: #2c2c2c; border: 1px solid #4a4a4a; padding: 15px; border-radius: 8px;">
                <h4 style="color: #eaee05; margin: 0 0 10px 0;">🔄 Upload Progress</h4>
                <div style="background-color: #1a1a1a; border-radius: 10px; padding: 4px; margin: 10px 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
                    <div id="progressBar" style="
                        background: linear-gradient(90deg, #007bff 0%, #0056b3 100%); 
                        height: 25px; 
                        border-radius: 8px; 
                        width: 0%; 
                        transition: width 0.3s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-weight: bold;
                        font-size: 12px;
                        box-shadow: 0 2px 4px rgba(0,123,255,0.3);
                    ">
                        <span id="progressPercent">0%</span>
                    </div>
                </div>
                <p id="progressText" style="text-align: center; margin: 10px 0; font-weight: bold; color: #f8f9fa;">Preparing upload...</p>
                <div id="progressDetails" style="text-align: center; font-size: 12px; color: #ccc; margin: 5px 0;"></div>
            </div>
        </div>
        
    </div>
    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>
</div>
</body>
</html>
