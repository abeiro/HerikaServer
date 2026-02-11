<?php

class Logger {
    private const DEFAULT_LOG = '/var/www/html/HerikaServer/log/chim.log';
    private static $CUSTOM_LOG;
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

    // minimum log level to backtrace (default = disabled)
    private static $_backtraceLevel = 9;

    // include arguments in backtrace (default no)
    private static $_backtraceArgs = false;

    // Set custom log file path
    public static function setCustomLog($logFile) {
        self::$CUSTOM_LOG = $logFile;
    }

    // Unset custom log file path
    public static function unsetCustomLog() {
        self::$CUSTOM_LOG = null;
    }

    // Ex: Logger::setLevel("warn") to suppress trace, debug, and info messages
    public static function setLevel($level) {
        if (isset(self::LOG_LEVELS[$level])) {
            self::$_minLogLevel = $level;
        } else {
            error_log("[error] Invalid log level specified: {$level}");
        }
    }

    // Ex: Logger::setBacktrace("disabled") to suppress backtrace, Logger::setBacktrace("warn") to show stack and  for warnings and errors
    public static function setBacktrace($level, $exposeArgs=false) {
        if (isset(self::LOG_LEVELS[$level])) {
            self::$_backtraceLevel = self::LOG_LEVELS[$level];
            self::$_backtraceArgs = $exposeArgs;
        } else {
            self::$_backtraceLevel = 9;
            self::$_backtraceArgs = false;
        }
    }

    // Can call with no parameter or an empty string to omit the timestamp from logs
    public static function setTimestampFormat($format = "") {
        self::$_timestampFormat = $format;
    }

    private static function shouldLog($level) {
        return self::LOG_LEVELS[$level] >= self::LOG_LEVELS[self::$_minLogLevel];
    }

    private static function shouldBacktrace($level) {
        $b_res = false;
        
        if (isset(self::LOG_LEVELS[$level])) {
            $b_res = (self::LOG_LEVELS[$level] >= self::$_backtraceLevel);
        }
        
        return $b_res; 
    }

    private static function log($level, $message, $logFile) {
        if (!self::shouldLog($level)) {
            return;
        }

        $timestamp = self::$_timestampFormat ? "[".date(self::$_timestampFormat)."] " : "";

        if (self::shouldBacktrace($level)) {
            ob_start();
            if (self::$_backtraceArgs)
                debug_print_backtrace(0, 7);
            else
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 7);
            $s_trace = ob_get_contents();
            ob_end_clean();
            if (str_contains($s_trace, '#1')) {
                $s_trace = strstr($s_trace, '#1');
            }
        } else $s_trace = "";
        
        $logEntry = "{$timestamp}[{$level}] {$message}\n{$s_trace}";
        

        if ($logFile == self::DEFAULT_LOG && isset(self::$CUSTOM_LOG) && !empty(self::$CUSTOM_LOG)) {
            $logFile=self::$CUSTOM_LOG;
        }

        error_log($logEntry, 3, $logFile);

        // also write to apache error log
        if (in_array(strtolower($level), ["warn", "error"])) {
            $logEntry = "[{$level}] {$message}";
            error_log($logEntry);
        }
    }

    public static function trace($message, $logFile = self::DEFAULT_LOG) {
        self::log("trace", $message, $logFile);
    }

    public static function debug($message, $logFile = self::DEFAULT_LOG) {
        self::log("debug", $message, $logFile);
    }

    public static function info($message, $logFile = self::DEFAULT_LOG) {
        self::log("info", $message, $logFile);
    }

    public static function warn($message, $logFile = self::DEFAULT_LOG) {
        self::log("warn", $message, $logFile);
    }

    public static function error($message, $logFile = self::DEFAULT_LOG) {
        self::log("error", $message, $logFile);
    }

    // write uncaught errors to the CHIM log in addition to the apache log
    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        
        if (error_reporting() === 0) {// when error reporting is suppressed
            return false;
        }

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

        // obey the minimum log level for the CHIM log (but still write uncaught errors to the apache log)
        if (self::shouldLog($level)) {
            $timestamp = self::$_timestampFormat ? "[".date(self::$_timestampFormat)."] " : "";
            $logEntry = "{$timestamp}[{$level}] %s in %s on line %d\n";
            $formattedMessage = sprintf($logEntry, $errstr, $errfile, $errline);

            // write to the default log file (avoid error_log here because it would duplicate the message)
            file_put_contents(self::DEFAULT_LOG, $formattedMessage, FILE_APPEND);
        }

        // return false to allow PHP's default error handler to run as well
        return false;
    }

    // Delete log file if its size is greater than 25Mb
    public static function deleteLogIfTooLarge($logFile = null, $maxSize = 26214400) {
        if ($logFile === null) {
            $logFile = isset(self::$CUSTOM_LOG) && !empty(self::$CUSTOM_LOG) ? self::$CUSTOM_LOG : self::DEFAULT_LOG;
        }
        if (file_exists($logFile) && filesize($logFile) > $maxSize) {
            copy("/dev/null",$logFile);// Truncate, don't create a new file.
        }
    }
}

// use the errorHandler provided here to write errors to the CHIM log in addition to the apache log
set_error_handler(["Logger", "errorHandler"]);

?>