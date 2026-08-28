<?php

/**
 * Playthrough Schema Management Library
 * 
 * Fast schema-based playthrough system using PostgreSQL schema cloning.
 * Replaces slow pg_dump/restore with instant in-database operations.
 */

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'logger.php');

/**
 * Check for identity/sequence support and the removal of snapshot view cloning.
 */
function pts_clone_function_is_current($definition): bool {
    return is_string($definition)
        && stripos($definition, 'OVERRIDING SYSTEM VALUE') !== false
        && stripos($definition, 'sync_schema_sequences(dest_schema)') !== false
        && stripos($definition, 'CREATE OR REPLACE VIEW') === false;
}

/**
 * Ensure the clone_schema SQL functions exist in the database.
 * Safe to call multiple times (idempotent).
 */
function pts_ensure_functions($conn): bool {
    // Refresh older definitions that mishandle identity values or copy views
    // whose unqualified table references can point back to the live schema.
    $checkQuery = "SELECT pg_get_functiondef(p.oid) AS function_definition FROM pg_proc p JOIN pg_namespace n ON p.pronamespace = n.oid WHERE p.proname = 'clone_schema' AND n.nspname = 'chim_meta' LIMIT 1";
    $checkResult = @pg_query($conn, $checkQuery);
    if ($checkResult && pg_num_rows($checkResult) > 0) {
        $row = pg_fetch_assoc($checkResult);
        if (pts_clone_function_is_current($row['function_definition'] ?? null)) {
            return true;
        }

        Logger::info("Schema clone functions are outdated; refreshing definitions");
    }
    
    $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'schema_clone_function.sql';
    if (!file_exists($sqlFile)) {
        Logger::error("schema_clone_function.sql not found at: " . $sqlFile);
        return false;
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        Logger::error("Failed to read schema_clone_function.sql");
        return false;
    }
    
    // Execute the SQL to create functions
    $result = @pg_query($conn, $sql);
    if (!$result) {
        $error = pg_last_error($conn);
        Logger::error("Failed to create schema clone functions: " . $error);
        return false;
    }
    
    // Verify the current function definition was installed
    $verifyResult = @pg_query($conn, $checkQuery);
    $verifyRow = $verifyResult ? pg_fetch_assoc($verifyResult) : false;
    if (!$verifyRow || !pts_clone_function_is_current($verifyRow['function_definition'] ?? null)) {
        Logger::error("Schema clone functions were not installed successfully");
        return false;
    }
    
    Logger::info("Schema clone functions installed successfully");
    return true;
}

/**
 * Sanitize a profile name to create a valid PostgreSQL schema name.
 * Rules: lowercase, alphanumeric + underscore only, max 63 chars
 */
function pts_sanitize_profile_name(string $name): string {
    // Convert to lowercase
    $name = strtolower($name);
    
    // Replace spaces and special chars with underscores
    $name = preg_replace('/[^a-z0-9_]/', '_', $name);
    
    // Remove consecutive underscores
    $name = preg_replace('/_+/', '_', $name);
    
    // Remove leading/trailing underscores
    $name = trim($name, '_');
    
    // Ensure it doesn't start with a number
    if (preg_match('/^[0-9]/', $name)) {
        $name = 'p_' . $name;
    }
    
    // Truncate to safe length (PostgreSQL allows 63, leave room for prefix)
    $name = substr($name, 0, 40);
    
    // Prefix with chim_profile_
    return 'chim_profile_' . $name;
}

/**
 * Check if a schema exists in the database.
 */
function pts_schema_exists($conn, string $schemaName): bool {
    $result = @pg_query_params(
        $conn,
        "SELECT 1 FROM information_schema.schemata WHERE schema_name = $1",
        [$schemaName]
    );
    
    if (!$result) {
        return false;
    }
    
    return pg_num_rows($result) > 0;
}

/**
 * Clone a schema (source) to another schema (destination).
 * Returns ['success' => bool, 'error' => string]
 */
function pts_clone_schema($conn, string $sourceSchema, string $destSchema): array {
    // Validate schema names
    if (empty($sourceSchema) || empty($destSchema)) {
        return ['success' => false, 'error' => 'Invalid schema names'];
    }
    
    if (!pts_schema_exists($conn, $sourceSchema)) {
        return ['success' => false, 'error' => "Source schema '{$sourceSchema}' does not exist"];
    }
    
    // Drop destination if it exists (functions are in chim_meta schema)
    $dropResult = @pg_query(
        $conn,
        "SELECT chim_meta.drop_schema_safe('" . pg_escape_string($conn, $destSchema) . "'::text)"
    );
    
    if (!$dropResult) {
        Logger::warn("Could not drop existing schema {$destSchema}: " . pg_last_error($conn));
    }
    
    // Clone the schema (functions are in chim_meta schema)
    $result = @pg_query(
        $conn,
        "SELECT chim_meta.clone_schema('" . pg_escape_string($conn, $sourceSchema) . "'::text, '" . pg_escape_string($conn, $destSchema) . "'::text)"
    );
    
    if (!$result) {
        $error = pg_last_error($conn);
        Logger::error("Schema clone failed: {$error}");
        return ['success' => false, 'error' => $error];
    }
    
    Logger::info("Successfully cloned schema {$sourceSchema} to {$destSchema}");
    return ['success' => true, 'error' => ''];
}

/**
 * Drop a schema safely (with protection for system schemas).
 * Returns ['success' => bool, 'error' => string]
 */
function pts_drop_schema($conn, string $schemaName): array {
    if (empty($schemaName)) {
        return ['success' => false, 'error' => 'Invalid schema name'];
    }
    
    // Additional PHP-level protection
    $protected = ['public', 'pg_catalog', 'information_schema', 'pg_toast', 'chim_meta'];
    if (in_array(strtolower($schemaName), $protected)) {
        return ['success' => false, 'error' => 'Cannot drop protected schema'];
    }
    
    $result = @pg_query(
        $conn,
        "SELECT chim_meta.drop_schema_safe('" . pg_escape_string($conn, $schemaName) . "'::text)"
    );
    
    if (!$result) {
        $error = pg_last_error($conn);
        Logger::error("Schema drop failed: {$error}");
        return ['success' => false, 'error' => $error];
    }
    
    $row = pg_fetch_assoc($result);
    if ($row && isset($row['drop_schema_safe']) && $row['drop_schema_safe'] === 't') {
        Logger::info("Successfully dropped schema {$schemaName}");
        return ['success' => true, 'error' => ''];
    }
    
    return ['success' => false, 'error' => 'Drop operation returned false'];
}

/**
 * Get the total size of a schema in bytes.
 * Returns 0 if schema doesn't exist or on error.
 */
function pts_get_schema_size($conn, string $schemaName): int {
    if (!pts_schema_exists($conn, $schemaName)) {
        return 0;
    }
    
    $result = @pg_query(
        $conn,
        "SELECT chim_meta.get_schema_size('" . pg_escape_string($conn, $schemaName) . "'::text) as size"
    );
    
    if (!$result) {
        Logger::warn("Failed to get schema size: " . pg_last_error($conn));
        return 0;
    }
    
    $row = pg_fetch_assoc($result);
    if ($row && isset($row['size'])) {
        return (int)$row['size'];
    }
    
    return 0;
}

/**
 * Recreate the public schema cleanly with required extensions.
 * This is used before cloning a profile schema back to public.
 */
function pts_recreate_public_schema($conn): bool {
    $queries = [
        "DROP SCHEMA IF EXISTS public CASCADE",
        "CREATE SCHEMA public",
        // Extensions are database-level, not schema-level, so just ensure they exist
        "CREATE EXTENSION IF NOT EXISTS vector",
        "CREATE EXTENSION IF NOT EXISTS pg_trgm"
    ];
    
    foreach ($queries as $query) {
        $result = @pg_query($conn, $query);
        if (!$result) {
            Logger::error("Failed to execute: {$query} - " . pg_last_error($conn));
            return false;
        }
    }
    
    return true;
}

/**
 * Get metadata about a profile schema (table counts, record counts, etc.)
 * Returns array with stats or empty array on error.
 */
function pts_get_schema_metadata($conn, string $schemaName): array {
    if (!pts_schema_exists($conn, $schemaName)) {
        return [];
    }
    
    $metadata = [
        'player_name' => 'Unknown',
        'game' => 'Skyrim',
        'eventlog_count' => 0,
        'oghma_count' => 0,
        'last_gamets' => 0
    ];
    
    // Get eventlog count
    $result = @pg_query_params(
        $conn,
        "SELECT COUNT(*) as c FROM {$schemaName}.eventlog",
        []
    );
    if ($result && ($row = pg_fetch_assoc($result))) {
        $metadata['eventlog_count'] = (int)$row['c'];
    }
    
    // Check if oghma table exists
    $result = @pg_query_params(
        $conn,
        "SELECT 1 FROM information_schema.tables WHERE table_schema = $1 AND table_name = 'oghma'",
        [$schemaName]
    );
    if ($result && pg_num_rows($result) > 0) {
        $result = @pg_query_params($conn, "SELECT COUNT(*) as c FROM {$schemaName}.oghma", []);
        if ($result && ($row = pg_fetch_assoc($result))) {
            $metadata['oghma_count'] = (int)$row['c'];
        }
    }
    
    // Get last gamets
    $result = @pg_query_params(
        $conn,
        "SELECT MAX(gamets) as mx FROM {$schemaName}.eventlog",
        []
    );
    if ($result && ($row = pg_fetch_assoc($result)) && !is_null($row['mx'])) {
        $metadata['last_gamets'] = (int)$row['mx'];
    }
    
    return $metadata;
}

?>

