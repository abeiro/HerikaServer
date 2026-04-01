<?php
/**
 * Pipeline Status Tracking
 * 
 * Lightweight file-based status tracking for STT/LLM/TTS pipeline stages.
 * Used by the Prisma Status HUD overlay to show real-time processing states.
 */

define('PIPELINE_STATUS_FILE', __DIR__ . '/../data/pipeline_status.json');

/**
 * Set a pipeline stage as active or inactive
 * 
 * @param string $stage Stage name: 'stt', 'player_tts', 'llm', 'tts'
 * @param bool $active True to activate, false to deactivate
 * @return bool Success status
 */
function pipeline_status_set($stage, $active) {
    try {
        $status = pipeline_status_get();
        
        // Validate stage
        $validStages = ['stt', 'player_tts', 'llm', 'tts'];
        if (!in_array($stage, $validStages)) {
            error_log("Invalid pipeline stage: {$stage}");
            return false;
        }
        
        // Update stage
        $status[$stage] = (bool)$active;
        $status['updated'] = time();
        
        // Write atomically
        $tempFile = PIPELINE_STATUS_FILE . '.tmp';
        if (file_put_contents($tempFile, json_encode($status, JSON_PRETTY_PRINT)) !== false) {
            return rename($tempFile, PIPELINE_STATUS_FILE);
        }
        
        return false;
    } catch (Throwable $e) {
        error_log("Pipeline status set error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current pipeline status
 * 
 * @return array Status array with stage states
 */
function pipeline_status_get() {
    try {
        // Check if file exists
        if (!file_exists(PIPELINE_STATUS_FILE)) {
            return pipeline_status_init();
        }
        
        // Read status file
        $contents = @file_get_contents(PIPELINE_STATUS_FILE);
        if ($contents === false) {
            return pipeline_status_init();
        }
        
        $status = json_decode($contents, true);
        if (!is_array($status)) {
            return pipeline_status_init();
        }
        
        // Merge with defaults to ensure all keys exist
        return array_merge(pipeline_status_init(), $status);
        
    } catch (Throwable $e) {
        error_log("Pipeline status get error: " . $e->getMessage());
        return pipeline_status_init();
    }
}

/**
 * Clear all pipeline stages (set all to inactive)
 * 
 * @return bool Success status
 */
function pipeline_status_clear() {
    try {
        $status = pipeline_status_init();
        $status['updated'] = time();
        
        $tempFile = PIPELINE_STATUS_FILE . '.tmp';
        if (file_put_contents($tempFile, json_encode($status, JSON_PRETTY_PRINT)) !== false) {
            return rename($tempFile, PIPELINE_STATUS_FILE);
        }
        
        return false;
    } catch (Throwable $e) {
        error_log("Pipeline status clear error: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize default pipeline status structure
 * 
 * @return array Default status
 */
function pipeline_status_init() {
    return [
        'stt' => false,
        'player_tts' => false,
        'llm' => false,
        'tts' => false,
        'mode' => 'STANDARD',
        'connector_label' => '',
        'connector_model' => '',
        'updated' => time()
    ];
}

/**
 * Update mode and connector information
 * 
 * @param string $mode Current CHIM mode
 * @param string $connectorLabel Connector label
 * @param string $connectorModel Model name
 * @return bool Success status
 */
function pipeline_status_set_context($mode, $connectorLabel = '', $connectorModel = '') {
    try {
        $status = pipeline_status_get();
        
        $status['mode'] = strtoupper(trim($mode));
        $status['connector_label'] = trim($connectorLabel);
        $status['connector_model'] = trim($connectorModel);
        $status['updated'] = time();
        
        $tempFile = PIPELINE_STATUS_FILE . '.tmp';
        if (file_put_contents($tempFile, json_encode($status, JSON_PRETTY_PRINT)) !== false) {
            return rename($tempFile, PIPELINE_STATUS_FILE);
        }
        
        return false;
    } catch (Throwable $e) {
        error_log("Pipeline status set context error: " . $e->getMessage());
        return false;
    }
}
