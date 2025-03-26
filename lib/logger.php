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

    // minimum log level to write to the log (default = write everything)
    private static $_minLogLevel = 'trace';

    // timestamp format (default = ISO 8601)
    private static $_timestampFormat = 'Y-m-d\TH:i:sP';

    // Ex: Logger::setLevel("warn") to suppress trace, debug, and info messages
    public static function setLevel($level) {
        if (isset(self::LOG_LEVELS[$level])) {
            self::$_minLogLevel = $level;
        } else {
            error_log("[error] Invalid log level specified: {$level}");
        }
    }

    // Can call with no parameter or an empty string to omit the timestamp from logs
    public static function setTimestampFormat($format = "") {
        self::$_timestampFormat = $format;
    }

    private static function shouldLog($level) {
        return self::LOG_LEVELS[$level] >= self::LOG_LEVELS[self::$_minLogLevel];
    }

    private static function log($level, $message, $logFile) {
        if (!self::shouldLog($level)) {
            return;
        }

        $timestamp = self::$_timestampFormat ? "[".date(self::$_timestampFormat)."] " : "";
        $logEntry = "{$timestamp}[{$level}] {$message}\n";
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

    // write uncaught errors to the CHIM log in addition to the apache log
    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        switch ($errno) {
            case E_USER_ERROR:
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                $level = 'error';
                break;
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                $level = 'warn';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $level = 'info';
                break;
            default:
                $level = 'debug'; // For other less critical errors
                break;
        }

        $timestamp = self::$_timestampFormat ? "[".date(self::$_timestampFormat)."] " : "";
        $logEntry = "{$timestamp}[{$level}] %s in %s on line %d\n";
        $formattedMessage = sprintf($logEntry, $errstr, $errfile, $errline);

        // write to the default log file (avoid error_log here because it would duplicate the message)
        file_put_contents(self::DEFAULT_LOG, $formattedMessage, FILE_APPEND);

        // return false to allow PHP's default error handler to run as well
        return false;
    }
}

// use the errorHandler provided here to write errors to the CHIM log in addition to the apache log
set_error_handler(["Logger", "errorHandler"]);

?>