<?php

// Helper functions for openrouterjsoncached connector

/**
 * Logs messages to a specified log file
 */
function logMessage(string|array $message, ?string $context = null, string $level = 'INFO', string $logFile = 'cache.log'): bool
{
    $timestamp = date('Y-m-d H:i:s');
    if ($message == null) {
        $logEntry = "[{$timestamp}] {$level}: Null message\n";
        $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        return true;
    }

    $logFile = __DIR__ . "/../log/" . $logFile;
    $formattedMessage = '';

    if (is_array($message)) {
        $jsonMessage = json_encode($message, JSON_PRETTY_PRINT);
        if ($jsonMessage === false) {
            $jsonMessage = "Failed to encode array to JSON. Original: " . print_r($message, true);
        }

        if ($context !== null && $context !== '') {
            $formattedMessage = "{$context} \n {$jsonMessage}";
        } else {
            $formattedMessage = $jsonMessage;
        }
    } else {
        $formattedMessage = (string) $message;
    }

    $logEntry = "[{$timestamp}] {$level}: {$formattedMessage}\n";
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    if ($result === false) {
        error_log("Failed to write to log file: {$logFile}. Original message: " . (is_array($message) ? json_encode($message) : $message));
        return false;
    }

    return true;
}

// More helper functions will be added incrementally
