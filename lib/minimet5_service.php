<?php
/**
 * MiniMeT5 Service Functions
 *
 * These functions interface with the MiniMeT5/TXT2VEC service configured by
 * FEATURES.MEMORY_EMBEDDING.TXTAI_URL (local port 8082 by default).
 * If the service is not running or not configured, they fail gracefully
 * by returning null/empty instead of blocking on connection timeouts.
 *
 * The service availability is cached per-request to avoid repeated
 * connection attempts when the service is down.
 */

function _minimeServiceBaseUrl() {
    $defaultUrl = 'http://127.0.0.1:8082';
    $configuredUrl = $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['TXTAI_URL'] ?? $defaultUrl;
    $configuredUrl = rtrim(trim((string)$configuredUrl), '/');

    if ($configuredUrl === '' || filter_var($configuredUrl, FILTER_VALIDATE_URL) === false) {
        return $defaultUrl;
    }

    $parts = parse_url($configuredUrl);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
        return $defaultUrl;
    }

    return $configuredUrl;
}

function _minimeServiceEndpoint($path, $query = []) {
    $url = _minimeServiceBaseUrl() . '/' . ltrim((string)$path, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    return $url;
}

/**
 * Check if MiniMeT5 service is available (cached per request)
 * Uses a quick socket check instead of waiting for HTTP timeout
 */
function _minimeServiceAvailable() {
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $parts = parse_url(_minimeServiceBaseUrl());
    $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
    $host = (string)($parts['host'] ?? '127.0.0.1');
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    $socketHost = ($scheme === 'https' ? 'ssl://' : '') . $host;

    // Quick socket check - 100ms timeout
    $socket = @fsockopen($socketHost, $port, $errno, $errstr, 0.1);
    if ($socket) {
        fclose($socket);
        $available = true;
    } else {
        $available = false;
        // Only log once per request
        error_log("[MiniMeT5] Service not available at {$host}:{$port} - skipping MiniMe calls this request");
    }

    return $available;
}

/**
 * MiniMe auto-mode:
 * - Enabled by default when service is reachable.
 * - Automatically considered off when service is unavailable.
 * - In tests, mock handlers imply enabled.
 */
function isMinimeT5Enabled() {
    if (isset($GLOBALS["mockMinimeCommand"]) ||
        isset($GLOBALS["mockMinimeExtract"]) ||
        isset($GLOBALS["mockMinimePostTopic"]) ||
        isset($GLOBALS["mockMinimeTask"]) ||
        isset($GLOBALS["mockMinimeTopic"]) ||
        isset($GLOBALS["mockMinimePostScene"])) {
        return true;
    }
    return _minimeServiceAvailable();
}

function minimeCommand($text) {
    error_log("[DEPRECATED] minimeCommand called with text: " . $text);
    return null; // Deprecated - no longer functional
    
    if (isset($GLOBALS["mockMinimeCommand"])) {
        return call_user_func($GLOBALS["mockMinimeCommand"], $text);
    }

    // Skip if service not available
    if (!_minimeServiceAvailable()) {
        return null;
    }

    $url = _minimeServiceEndpoint('command', ['text' => $text]);
    $result = @file_get_contents($url);
    return $result !== false ? $result : null;
}

function minimeExtract($text,$useOnlyDetector=false) {
    if (isset($GLOBALS["mockMinimeExtract"])) {
        return call_user_func($GLOBALS["mockMinimeExtract"], $text);
    }

    // Skip if service not available
    if (!_minimeServiceAvailable()) {
        return null;
    }

    if (isset($GLOBALS["MINIME_CACHE"][md5($text)]))
        return $GLOBALS["MINIME_CACHE"][md5($text)];

    if ($useOnlyDetector)
        $url = _minimeServiceEndpoint('detectMemory', ['text' => $text]);
    else
        $url = _minimeServiceEndpoint('extract', ['text' => $text]);

    $result = @file_get_contents($url);
    $GLOBALS["MINIME_CACHE"][md5($text)] = $result !== false ? $result : null;
    return $GLOBALS["MINIME_CACHE"][md5($text)];
}

function minimePostTopic($text) {
    if (isset($GLOBALS["mockMinimePostTopic"])) {
        return call_user_func($GLOBALS["mockMinimePostTopic"], $text);
    }

    // Skip if service not available
    if (!_minimeServiceAvailable()) {
        return null;
    }

    $url = _minimeServiceEndpoint('posttopic', ['text' => $text]);
    $result = @file_get_contents($url);
    return $result !== false ? $result : null;
}

function minimeTask($text) {
    if (isset($GLOBALS["mockMinimeTask"])) {
        return call_user_func($GLOBALS["mockMinimeTask"], $text);
    }

    // Skip if service not available
    if (!_minimeServiceAvailable()) {
        return null;
    }

    $url = _minimeServiceEndpoint('task', ['text' => $text]);
    $result = @file_get_contents($url);
    return $result !== false ? $result : null;
}

function minimeTopic($text) {
    if (isset($GLOBALS["mockMinimeTopic"])) {
        return call_user_func($GLOBALS["mockMinimeTopic"], $text);
    }

    // Skip if service not available
    if (!_minimeServiceAvailable()) {
        return null;
    }

    $url = _minimeServiceEndpoint('topic', ['text' => $text]);
    $result = @file_get_contents($url);
    return $result !== false ? $result : null;
}

function minimePostScene($text) {
    if (isset($GLOBALS["mockMinimePostScene"])) {
        return call_user_func($GLOBALS["mockMinimePostScene"], $text);
    }

    // Skip if service not available
    if (!_minimeServiceAvailable()) {
        return null;
    }

    $url = _minimeServiceEndpoint('ambient', ['text' => $text]);
    $result = @file_get_contents($url);
    return $result !== false ? $result : null;
}

?>
