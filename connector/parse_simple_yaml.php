<?php
// YAML parser supporting simple key-value pairs and nested objects (via indentation)
function parse_simple_yaml($yaml) {
    $result = array();
    $lines = preg_split('/\r?\n/', $yaml);
    $stack = array(&$result);  // Stack of references for nested structures
    $indentStack = array(0);   // Stack of indentation levels
    
    foreach ($lines as $line) {
        // Count leading spaces for indentation
        $indent = strlen($line) - strlen(ltrim($line));
        $trimmed = trim($line);
        
        // Skip empty lines and comments
        if ($trimmed === '' || $trimmed[0] === '#') continue;
        
        // Parse key: value pattern
        if (preg_match('/^([a-zA-Z0-9_\-]+):\s*(.*)$/', $trimmed, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            
            // Adjust stack based on indentation
            while (count($indentStack) > 1 && $indent <= $indentStack[count($indentStack) - 1]) {
                array_pop($stack);
                array_pop($indentStack);
            }
            
            // Parse the value
            $parsedVal = null;
            if ($val === '') {
                // Empty value means this is a parent object, value will be set to array
                $parsedVal = array();
            } elseif ($val[0] === '[' && $val[strlen($val)-1] === ']') {
                // Parse as inline array/list
                $arrayStr = substr($val, 1, -1);
                $arrayItems = array_map('trim', explode(',', $arrayStr));
                $parsedVal = array();
                foreach ($arrayItems as $item) {
                    if (is_numeric($item)) {
                        $parsedVal[] = $item + 0;
                    } elseif (strtolower($item) === 'true') {
                        $parsedVal[] = true;
                    } elseif (strtolower($item) === 'false') {
                        $parsedVal[] = false;
                    } elseif (strtolower($item) === 'null') {
                        $parsedVal[] = null;
                    } else {
                        // Remove quotes if present and unescape
                        if ((($item[0] === '"' && $item[strlen($item)-1] === '"') || 
                             ($item[0] === "'" && $item[strlen($item)-1] === "'"))) {
                            $unquoted = substr($item, 1, -1);
                            $parsedVal[] = stripslashes($unquoted);
                        } else {
                            $parsedVal[] = $item;
                        }
                    }
                }
            } elseif (is_numeric($val)) {
                $parsedVal = $val + 0;
            } elseif (strtolower($val) === 'true') {
                $parsedVal = true;
            } elseif (strtolower($val) === 'false') {
                $parsedVal = false;
            } elseif (strtolower($val) === 'null') {
                $parsedVal = null;
            } else {
                // Remove quotes if present and unescape
                if ((($val[0] === '"' && $val[strlen($val)-1] === '"') || 
                     ($val[0] === "'" && $val[strlen($val)-1] === "'"))) {
                    $unquoted = substr($val, 1, -1);
                    $parsedVal = stripslashes($unquoted);
                } else {
                    $parsedVal = $val;
                }
            }
            
            // Set the value in the current context
            $stack[count($stack) - 1][$key] = $parsedVal;
            
            // If value is an array (parent object), prepare for nested children
            if (is_array($parsedVal)) {
                $stack[] = &$stack[count($stack) - 1][$key];
                array_push($indentStack, $indent);
            }
        }
    }
    
    return $result;
}
