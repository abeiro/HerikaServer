<?php

define("S_LOG_DISABLED", -1);
define("S_LOG_DEFAULT", 1);
define("S_LOG_NOTICE", 2);
define("S_LOG_WARNING", 3);
define("S_LOG_ERROR", 4);
define("S_LOG_CRITICAL", 5);
define("S_LOG_CUSTOM", 10);
define("S_LOG_INIT", 50);
define("S_LOG_END", 51);


$GLOBALS["AUDIT_MANAGER_RUNID"] = strrev(uniqid("di_",true));
$GLOBALS["AUDIT_MANAGER_START_TIME"] = microtime(true);
register_shutdown_function('_manager_audit_end');
$GLOBALS["MANAGER_CONF_SAVE"]=json_encode($GLOBALS["ENGINE_ROOT"]."/data/.manager.state.json",true);


if (!isValidArray($GLOBALS["MANAGER_CONF_SAVE"])) {
    $GLOBALS["MANAGER_CONF_SAVE"]=[];
}

function _manager_audit_end() {
    $endTime = microtime(true);
    $startTime = $GLOBALS["AUDIT_MANAGER_START_TIME"];
    $elapsedTime = $endTime - $startTime;

    $GLOBALS["AUDIT_MANAGER_RUNID_REQUEST"]=isset($GLOBALS["AUDIT_MANAGER_RUNID_REQUEST"])?$GLOBALS["AUDIT_MANAGER_RUNID_REQUEST"]:"";
    
    logMsg("Audit {$GLOBALS["AUDIT_MANAGER_RUNID"]}, {$GLOBALS["AUDIT_MANAGER_RUNID_REQUEST"]}, elapsed time: " . $elapsedTime . " seconds",S_LOG_END);
    
    // Save configuration vars
    $CONF=[];
    foreach ($GLOBALS["MANAGER_CONF_SAVE"] as $v) {
        $CONF[$v]=$GLOBALS[$v];
    }

    file_put_contents($GLOBALS["ENGINE_ROOT"]."/data/.manager.state.json",json_encode($CONF),LOCK_EX);

}

function logMsg($text, $lvl = S_LOG_DEFAULT) {
    $colors = [
        'WHITE'   => "\033[1;37m", // Default
        'CYAN'    => "\033[0;36m", // Notice
        'YELLOW'  => "\033[1;33m", // Warning
        'RED'     => "\033[1;31m", // Error
        'MAGENTA' => "\033[0;35m", // Critical
        'BLUE'    => "\033[0;34m", // Custom level
        'NC'      => "\033[0m",    // No Color.
        'YELLOW'  => "\033[1;33m", // Log related

    ];

    if (isset($GLOBALS["LOG_LEVEL"]) && $GLOBALS["LOG_LEVEL"] > $lvl ) {
        return;
    }

    ob_start();
    switch ($lvl) {
        case S_LOG_DEFAULT:
            echo "{$colors['WHITE']}[INFO    ] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        case S_LOG_NOTICE:
            echo "{$colors['CYAN']}[NOTICE  ] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        case S_LOG_WARNING:
            echo "{$colors['YELLOW']}[WARNING  ] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        case S_LOG_ERROR:
            echo "{$colors['RED']}[ERROR   ] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        case S_LOG_CRITICAL:
            echo "{$colors['MAGENTA']}[CRITICAL] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        case S_LOG_INIT:
            echo PHP_EOL."{$colors['YELLOW']}[INIT    ] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        case S_LOG_END:
            echo "{$colors['YELLOW']}[END     ] {$text}{$colors['NC']}" . PHP_EOL;
            break;
        default:
            if (isset($GLOBALS["LOG_LEVEL"]) && $GLOBALS["LOG_LEVEL"] == $lvl) {
                echo "{$colors['BLUE']}[CUSTOM  ] {$text}{$colors['NC']}" . PHP_EOL;
            } else if (isset($GLOBALS["LOG_LEVEL"]) && $GLOBALS["LOG_LEVEL"] > $lvl) {
                echo "{$text}" . PHP_EOL;
            } else {
                echo "{$colors['WHITE']}[UNKNOWN ] {$text}{$colors['NC']}" . PHP_EOL;
            }
            break;
    }
    
    $buffer = ob_get_contents();
    ob_end_clean();
    
    file_put_contents($GLOBALS["ENGINE_ROOT"] . "/log/monitor.log", $buffer, FILE_APPEND);
}


function implodeArraySafe($array_of_strings) {
	if (isValidArray($array_of_strings)) {
		return implode(PHP_EOL,$array_of_strings);
	} else {
		return null;
	}
}

function isValidArray($mixdata) {
	if (isset($mixdata))
		if (is_array($mixdata))
			return $mixdata;
	return false;
}

function printMsg($text, $role, $color, $newline = true) {
    $GREEN  = "\033[0;32m";
    $YELLOW = "\033[1;33m";
    $RED    = "\033[1;31m";
    $PURPLE = "\033[0;35m"; // Purple color
    $NC     = "\033[0m";    // No Color

    $message = "";
    switch ($color) {
        case "green":
            $message = "{$GREEN}{$role}: {$text}{$NC}";
            break;
        case "yellow":
            $message = "{$YELLOW}{$role}: {$text}{$NC}";
            break;
        case "red":
            $message = "{$RED}{$role}: {$text}{$NC}";
            break;
        case "purple":
            $message = "{$PURPLE}{$role}: {$text}{$NC}";
            break;
        default:
            $message = "$text";
            break;
    }
    if (php_sapi_name()!="cli")
        logMsg($text,1);
    else
        echo $message . ($newline ? PHP_EOL : "");
}

function requireFilesRecursivelyByPattern($dir, $pattern) {
    $files = scandir($dir);
    natsort($files); // Sort files naturally by name
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            requireFilesRecursivelyByPattern($path, $pattern);
        } elseif (is_file($path) && preg_match($pattern, $file)) {
            logMsg("Requiring $path", 1);
            require_once($path);
        } 
    }
}

?>