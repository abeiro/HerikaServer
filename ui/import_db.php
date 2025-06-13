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

$TITLE = "💬 CHIM Chat Testing";

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
    shell_exec('echo "localhost:5432:dwemer:dwemer:dwemer" > /tmp/.pgpass;');
    shell_exec('chmod 600 /tmp/.pgpass;');
    $filename = date("dMy") . ".sql";
    $response = shell_exec('HOME=/tmp pg_dump -d dwemer -U dwemer -h localhost > ' . $rootPath . 'data/export_' . $filename);
    
    $backupFile = $rootPath . 'data/export_' . $filename;
    if (file_exists($backupFile) && filesize($backupFile) > 0) {
        // Force download of the backup file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="dwemer_backup_' . $filename . '"');
        header('Content-Length: ' . filesize($backupFile));
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
    } else {
        $message = "<p><strong>Error:</strong> Backup creation failed or file is empty.</p>";
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
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
        }

        h1, h2 {
            color: #ffffff; /* White color for headings */
        }


        label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
        }

    

        .message {
            background-color: #444444; /* Darker background for messages */
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            margin-bottom: 20px;
            color: #f8f9fa; /* Light text in messages */
            height: fit-content;
        }

        .message p {
            margin: 0 0 10px 0;
        }

        .response-container {
            margin-top: 20px;
        }

        .indent {
            padding-left: 10ch; /* 10 character spaces */
        }

        .indent5 {
            padding-left: 5ch; /* 5 character spaces */
            padding-right: 20px; /* Right side margin */
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

        pre {
            background-color: #2c2c2c;
            padding: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
            color: #f8f9fa;
            overflow: auto;
            max-width: 600px;
        }
    </style>
</head>
<body>
<div class="indent5">
    <h1>Database Manager</h1>
    
    <!-- Main Grid Container -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
        
        <!-- Database Manager Section -->
        <div class="message" style="background-color: #1a1a5c; border: 1px solid #2d2d8f;">
            <h3>🗄️ Database Access</h3>
            <p>Access the pgAdmin database manager for advanced database management.</p>
            <p><strong>Login:</strong> Both username = dwemer and password = dwemer</p>
            <a href="/pgAdmin/" target="_blank" class="button" style="background-color: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Open Database Manager
            </a>
        </div>
        
        <!-- Backup Section -->
        <div class="message" style="background-color: #1a5c1a; border: 1px solid #2d8f2d;">
            <h3>📦 Manual Backup</h3>
            <p>Create a backup of your current database. This will generate an SQL file you can download.</p>
            <a href="?action=backup" class="button" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Create Backup
            </a>
        </div>
        
        <!-- Maintenance Section -->
        <div class="message" style="background-color: #5c3c1a; border: 1px solid #8f5f2d;">
            <h3>🔧 Database Maintenance</h3>
            <p>Optimize and clean your database. This will compact the database and reclaim unused space.</p>
            <p><strong>⚠️ Important:</strong> Make sure Skyrim is stopped before running maintenance.</p>
            <button onclick="if (confirm('Database maintenance will optimize and compact the database.\n\n- Make sure Skyrim game is stopped\n- To reclaim unused space, free temporary space is required\n- During this operation tables will be locked, do not interrupt\n- This could take some time, please wait until you see the confirmation\n\nContinue?')) { window.open('<?php echo $webRoot; ?>/ui/vacuum_db.php', 'Database_maintenance', 'resizable=yes,scrollbars=yes,titlebar=no,width=800,height=600'); return false; }" 
                    class="button" style="background-color: #fd7e14; color: white; padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
                Run Database Maintenance
            </button>
        </div>
        
    </div>
    
    <!-- Second Row -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
        
        <!-- Automatic Backups Section -->
        <?php
        $autoBackup = new AutomaticBackup();
        $automaticBackups = $autoBackup->getBackups();
        ?>
        <div class="message" style="background-color: #2d1a5c; border: 1px solid #4a2d8f;">
            <h3>🤖 Automatic Backups</h3>
            <p>System-generated backups created automatically when the server starts only once a day. Keeps a maximum of 5 backups, automatically deleting the oldest when the limit is reached.</p>
            <p><strong>Status:</strong> <?php echo $autoBackup->isEnabled() ? '<span style="color: #28a745;">Enabled</span>' : '<span style="color: #dc3545;">Disabled</span>'; ?></p>
            
            <?php if (!empty($automaticBackups)): ?>
                <h4>Available Automatic Backups:</h4>
                <div style="max-height: 200px; overflow-y: auto; background-color: #1a1a1a; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <?php foreach ($automaticBackups as $backup): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #444; margin-bottom: 5px;">
                            <div style="flex-grow: 1;">
                                <strong><?php echo htmlspecialchars($backup['filename']); ?></strong><br>
                                <small style="color: #ccc;">
                                    📁 <?php echo AutomaticBackup::formatFileSize($backup['size']); ?>
                                </small>
                            </div>
                            <div style="display: flex; gap: 5px;">
                                <button onclick="window.location.href='?action=download_auto&filename=<?php echo urlencode($backup['filename']); ?>'" 
                                        class="button" style="background-color: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 3px; font-size: 12px; cursor: pointer;" 
                                        title="Download backup file">
                                    📥 Download
                                </button>
                                <button onclick="if (confirm('⚠️ RESTORE DATABASE\\n\\nRestore from: <?php echo htmlspecialchars($backup['filename']); ?>\\n\\nThis will COMPLETELY REPLACE your current database with this backup.\\n\\n❌ All current data will be lost!\\n✅ Database will be restored to backup state\\n\\nAre you absolutely sure you want to continue?')) { window.location.href='?action=restore_auto&filename=<?php echo urlencode($backup['filename']); ?>'; }" 
                                        class="button" style="background-color: #007bff; color: white; padding: 5px 10px; border: none; border-radius: 3px; font-size: 12px; cursor: pointer;" 
                                        title="Restore database from this backup">
                                    🔄 Restore
                                </button>
                                <button onclick="if (confirm('⚠️ DELETE BACKUP\\n\\nDelete: <?php echo htmlspecialchars($backup['filename']); ?>\\n\\nThis action cannot be undone!\\n\\nAre you sure you want to permanently delete this backup?')) { window.location.href='?action=delete_auto&filename=<?php echo urlencode($backup['filename']); ?>'; }" 
                                        class="button" style="background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; font-size: 12px; cursor: pointer;" 
                                        title="Delete this backup file">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #888; font-style: italic;">No automatic backups available yet.</p>
            <?php endif; ?>
            
            <?php if (!$autoBackup->isEnabled()): ?>
                <p><em>Enable automatic backups in the Configuration Wizard under "AUTOMATIC_DATABASE_BACKUPS"</em></p>
            <?php endif; ?>
        </div>
        
        <!-- Restore Section -->
        <div class="message" style="background-color: #1a3c5c; border: 1px solid #2d5f8f;">
            <h3>📥 Restore Database</h3>
            <p>Upload an SQL backup file to restore your database.</p>
            
            <form id="uploadForm" action="" method="post" enctype="multipart/form-data" style="margin-top: 15px;">
                <label for="sql_file" style="color: #f8f9fa; font-weight: bold;">Select SQL file to upload:</label><br>
                <input type="file" name="sql_file" id="sql_file" accept=".sql" required style="margin: 10px 0; padding: 8px; background-color: #444; color: #f8f9fa; border: 1px solid #666; border-radius: 4px;">
                <br>
                <input type="submit" class="button" value="Upload and Restore" style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; margin-top: 10px;">
            </form>
            
            <div id="uploadProgress" style="display: none; margin-top: 15px;">
                <h4>Upload Progress</h4>
                <div style="background-color: #333; border-radius: 10px; padding: 4px; margin: 10px 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
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
                <p id="progressText" style="text-align: center; margin: 10px 0; font-weight: bold;">Preparing upload...</p>
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
