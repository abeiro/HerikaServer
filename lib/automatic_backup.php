<?php
/**
 * Automatic Database Backup Management
 * Handles automatic backup creation and cleanup
 */

require_once(__DIR__ . DIRECTORY_SEPARATOR . "logger.php");

class AutomaticBackup {
    
    private $backupDir;
    private $maxBackups = 5;
    
    public function __construct() {
        $this->backupDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "ui" . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "databasebackups" . DIRECTORY_SEPARATOR;
        
        // Ensure backup directory exists
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
            Logger::info("Created automatic backup directory: " . $this->backupDir);
        }

        // Ensure chim_meta.settings exists and load retention count if available
        try {
            $db = $this->getDatabaseConnection();
            if ($db) {
                // Create schema/table if missing
                $db->execQuery("CREATE SCHEMA IF NOT EXISTS chim_meta");
                $db->execQuery("CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");
                $rows = $db->fetchAll("SELECT value FROM chim_meta.settings WHERE key = 'AUTOMATIC_BACKUP_MAX_COUNT'");
                $row = is_array($rows) && count($rows) > 0 ? $rows[0] : null;
                if ($row && isset($row['value'])) {
                    $v = intval($row['value']);
                    if ($v > 0 && $v < 100) { $this->maxBackups = $v; }
                }
            }
        } catch (Exception $e) {
            // ignore, keep default
        }
    }
    
    /**
     * Check if automatic backups are enabled
     */
    public function isEnabled() {
        // Prefer conf_opts override if present
        try {
            $db = $this->getDatabaseConnection();
            if ($db) {
                // Ensure settings table exists
                $db->execQuery("CREATE SCHEMA IF NOT EXISTS chim_meta");
                $db->execQuery("CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");
                $rows = $db->fetchAll("SELECT value FROM chim_meta.settings WHERE key = 'AUTOMATIC_DATABASE_BACKUPS'");
                $row = is_array($rows) && count($rows) > 0 ? $rows[0] : null;
                if ($row && isset($row['value'])) {
                    $val = strtolower(trim((string)$row['value']));
                    return ($val === 'true' || $val === '1' || $val === 'yes' || $val === 'on');
                }
            }
        } catch (Exception $e) {
            // fall through
        }
        // Fallback to global
        if (isset($GLOBALS['AUTOMATIC_DATABASE_BACKUPS'])) {
            return $GLOBALS['AUTOMATIC_DATABASE_BACKUPS'] === true || $GLOBALS['AUTOMATIC_DATABASE_BACKUPS'] === "true";
        }
        return false;
    }
    
    /**
     * Create an automatic backup
     */
    public function createBackup() {
        if (!$this->isEnabled()) {
            Logger::info("Automatic backup skipped - feature is disabled");
            return false;
        }
        
        Logger::info("Starting automatic database backup creation");
        
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "auto_backup_{$timestamp}.sql";
            $filepath = $this->backupDir . $filename;
            
            Logger::info("Creating backup file: " . $filename);
            
            // Database connection details
            $host = 'localhost';
            $port = '5432';
            $dbname = 'dwemer';
            $username = 'dwemer';
            $password = 'dwemer';
            
            // Create .pgpass file for authentication
            $pgpassResult = shell_exec('echo "localhost:5432:dwemer:dwemer:dwemer" > /tmp/.pgpass; echo $?');
            $chmodResult = shell_exec('chmod 600 /tmp/.pgpass; echo $?');
            
            Logger::info("Authentication setup complete");
            
            // Execute pg_dump with direct file output; exclude environment-local settings
            $command = "HOME=/tmp pg_dump -d dwemer -U dwemer -h localhost -T chim_meta.settings > " . escapeshellarg($filepath) . " 2>&1";
            $result = shell_exec($command);
            
            // pg_dump writes directly to file, so we don't need to handle output in memory
            
            // Check if backup was created successfully
            if (file_exists($filepath) && filesize($filepath) > 0) {
                $fileSize = filesize($filepath);
                Logger::info("Automatic database backup created successfully: " . $filename . " (Size: " . self::formatFileSize($fileSize) . ")");
                
                // Clean up old backups
                $this->cleanupOldBackups();
                
                return true;
            } else {
                Logger::warn("Automatic database backup failed: file not created or empty. Command result: " . substr($result, 0, 500));
                return false;
            }
            
        } catch (Exception $e) {
            Logger::warn("Automatic database backup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get list of automatic backups
     */
    public function getBackups() {
        $backups = [];
        
        if (!is_dir($this->backupDir)) {
            return $backups;
        }
        
        $files = scandir($this->backupDir);
        foreach ($files as $file) {
            if (strpos($file, 'auto_backup_') === 0 && substr($file, -4) === '.sql') {
                $filepath = $this->backupDir . $file;
                $backups[] = [
                    'filename' => $file,
                    'filepath' => $filepath,
                    'size' => filesize($filepath),
                    'date' => filemtime($filepath),
                    'formatted_date' => date('Y-m-d H:i:s', filemtime($filepath))
                ];
            }
        }
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return $backups;
    }
    
    /**
     * Clean up old backups, keeping only the newest maxBackups
     */
    private function cleanupOldBackups() {
        // Refresh maxBackups from conf_opts in case it changed at runtime
        try {
            $db = $this->getDatabaseConnection();
            if ($db) {
                $rows = $db->fetchAll("SELECT value FROM chim_meta.settings WHERE key = 'AUTOMATIC_BACKUP_MAX_COUNT'");
                $row = is_array($rows) && count($rows) > 0 ? $rows[0] : null;
                if ($row && isset($row['value'])) {
                    $v = intval($row['value']);
                    if ($v > 0 && $v < 100) { $this->maxBackups = $v; }
                }
            }
        } catch (Exception $e) {}

        $backups = $this->getBackups();
        
        if (count($backups) > $this->maxBackups) {
            // Remove excess backups (oldest ones)
            $toDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($toDelete as $backup) {
                if (unlink($backup['filepath'])) {
                    Logger::info("Deleted old automatic backup: " . $backup['filename']);
                } else {
                    Logger::warn("Failed to delete old automatic backup: " . $backup['filename']);
                }
            }
        }
    }
    
    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes) {
        if ($bytes == 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * Delete a specific backup file
     */
    public function deleteBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        // Security check - ensure filename starts with auto_backup_
        if (strpos($filename, 'auto_backup_') !== 0) {
            Logger::warn("Attempted to delete non-automatic backup file: " . $filename);
            return false;
        }
        
        if (file_exists($filepath)) {
            if (unlink($filepath)) {
                Logger::info("Manual deletion of automatic backup: " . $filename);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Decide if an automatic backup should be created.
     * To avoid generating many backups on consecutive requests right after startup,
     * we enforce a short cooldown based on the last successful backup timestamp
     * and the latest backup file in the backup directory.
     */
    public function shouldCreateBackup() {
        // Cooldown window (seconds). Only one auto-backup allowed within this window.
        $cooldownSeconds = 600; // 10 minutes

        try {
            // 1) If a recent auto backup file already exists, skip
            $backups = $this->getBackups();
            if (!empty($backups)) {
                $latest = $backups[0];
                if (isset($latest['date']) && (time() - (int)$latest['date'] < $cooldownSeconds)) {
                    Logger::info("Skipping automatic backup: recent backup exists within cooldown window");
                    return false;
                }
            }

            // 2) Also check persisted timestamp in DB (if available)
            $db = $this->getDatabaseConnection();
            if ($db) {
                $db->execQuery("CREATE SCHEMA IF NOT EXISTS chim_meta");
                $db->execQuery("CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");
                $row = $db->fetchOne("SELECT value FROM chim_meta.settings WHERE key='AUTOMATIC_BACKUP_LAST_TIMESTAMP'");
                if (is_array($row) && isset($row['value'])) {
                    $last = strtotime((string)$row['value']);
                    if ($last !== false && (time() - $last) < $cooldownSeconds) {
                        Logger::info("Skipping automatic backup: last backup timestamp within cooldown window");
                        return false;
                    }
                }
            }

            // 3) No recent backup detected: allow backup creation
            return true;

        } catch (Exception $e) {
            Logger::warn("Error checking backup requirement: " . $e->getMessage());
            // If we can't check, err on the side of creating a backup
            return true;
        }
    }
    
    /**
     * Update the backup completion timestamp
     * Called after successful backup creation for logging purposes
     */
    public function updateBackupCheckDate() {
        try {
            // Try to get a working database connection
            $db = $this->getDatabaseConnection();
            if (!$db) {
                Logger::warn("No database connection available for updating backup timestamp");
                return;
            }
            
            $timestamp = date('Y-m-d H:i:s');
            
            // Update the backup timestamp for reference (not used for backup decisions)
            // Ensure table exists then upsert timestamp
            $db->execQuery("CREATE SCHEMA IF NOT EXISTS chim_meta");
            $db->execQuery("CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");
            $db->upsertRowOnConflict('chim_meta.settings', ['key'=>'AUTOMATIC_BACKUP_LAST_TIMESTAMP','value'=>$timestamp], 'key');
            
            Logger::info("Updated last backup timestamp to: {$timestamp}");
            
        } catch (Exception $e) {
            Logger::warn("Error updating backup timestamp: " . $e->getMessage());
        }
    }
    
    /**
     * Get a working database connection
     * Tries global connection first, creates new one if needed
     */
    private function getDatabaseConnection() {
        try {
            // First, try to use existing global database connection
            if (isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
                // Test if the connection is actually working
                try {
                    $GLOBALS['db']->fetchAll("SELECT 1");
                    return $GLOBALS['db'];
                } catch (Exception $e) {
                    Logger::warn("Global database connection exists but not working: " . $e->getMessage());
                }
            }
            
            // If global connection doesn't work, try to create a new one
            if (!class_exists('sql')) {
                require_once(__DIR__ . DIRECTORY_SEPARATOR . "postgresql.class.php");
            }
            
            $db = new sql();
            
            // Test the new connection
            try {
                $db->fetchAll("SELECT 1");
                return $db;
            } catch (Exception $e) {
                Logger::warn("New database connection failed: " . $e->getMessage());
                return null;
            }
            
        } catch (Exception $e) {
            Logger::warn("Error getting database connection: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Initialize automatic backup on server restart
 * Creates a backup every time the server starts up
 * Keeps a maximum of 5 backups, automatically deleting the oldest when the limit is reached
 */
function initializeAutomaticBackup() {
    try {
        $backup = new AutomaticBackup();
        Logger::info("Automatic backup system initialized");
        
        if (!$backup->isEnabled()) {
            Logger::info("Automatic backups are disabled - skipping backup creation");
            return;
        }
        
        // Create a backup on server restart
        if ($backup->shouldCreateBackup()) {
            Logger::info("Server restart backup: creating backup");
            $result = $backup->createBackup();
            if ($result) {
                Logger::info("Server restart backup created successfully");
                $backup->updateBackupCheckDate();
            } else {
                Logger::warn("Server restart backup creation failed");
            }
        }
        
    } catch (Exception $e) {
        Logger::warn("Error initializing automatic backup: " . $e->getMessage());
    }
}

/**
 * Deferred initialization - only run when database is ready
 * This prevents running too early in the initialization process
 */
function deferredAutomaticBackupInit() {
    // Only run once per server restart
    static $hasRun = false;
    if ($hasRun) {
        return;
    }
    $hasRun = true;
    
    // Only run if database connection is available
    if (!isset($GLOBALS['db']) || !is_object($GLOBALS['db'])) {
        return;
    }
    
    initializeAutomaticBackup();
}

// Don't auto-initialize immediately - wait for proper database setup
// The initialization will be called from pages that have database ready
?> 