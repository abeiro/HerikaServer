<?php
// Minimal YAML parser for simple key: value pairs (no nesting, no arrays)
function parse_simple_yaml($yaml) {
    $result = array();
    $lines = preg_split('/\r?\n/', $yaml);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^([a-zA-Z0-9_\-]+):\s*(.*)$/', $line, $m)) {
            $key = $m[1];
            $val = $m[2];
            // Try to cast to int/float/bool/null
            if (is_numeric($val)) {
                $val = $val + 0;
            } elseif (strtolower($val) === 'true') {
                $val = true;
            } elseif (strtolower($val) === 'false') {
                $val = false;
            } elseif (strtolower($val) === 'null') {
                $val = null;
            }
            $result[$key] = $val;
        }
    }
    return $result;
}
