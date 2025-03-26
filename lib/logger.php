<?php

class Logger {
    private const DEFAULT_LOG = '/var/www/html/HerikaServer/log/chim.log';
    private const LOG_LEVELS = [
        'trace' => 1,
        'debug' => 2,
        'info' => 3,
        'warn' => 4,
        'error' => 5,
    ];

    private static $_minLogLevel = 'trace'; // Default minimum log level

    public static function setLevel($level) {
        if (isset(self::LOG_LEVELS[$level])) {
            self::$_minLogLevel = $level;
        } else {
            error_log("[error] Invalid log level specified: {$level}");
        }
    }

    private static function shouldLog($level) {
        return self::LOG_LEVELS[$level] >= self::LOG_LEVELS[self::$_minLogLevel];
    }

    private static function log($level, $message, $logFile) {
        if (!self::shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d\TH:i:sP');
        $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
        error_log($logEntry, 3, $logFile);

        // also write to apache error log
        if (in_array(strtolower($level), ["warn", "error"])) {
            $logEntry = "[{$level}] {$message}";
            error_log($logEntry);
        }
    }

    public static function trace($message, $logFile = self::DEFAULT_LOG) {
        Logger::log("trace", $message, $logFile);
    }

    public static function debug($message, $logFile = self::DEFAULT_LOG) {
        Logger::log("debug", $message, $logFile);
    }

    public static function info($message, $logFile = self::DEFAULT_LOG) {
        Logger::log("info", $message, $logFile);
    }

    public static function warn($message, $logFile = self::DEFAULT_LOG) {
        Logger::log("warn", $message, $logFile);
    }

    public static function error($message, $logFile = self::DEFAULT_LOG) {
        Logger::log("error", $message, $logFile);
    }
}

?>