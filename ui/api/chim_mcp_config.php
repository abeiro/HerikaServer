<?php

define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load configuration
if (!file_exists(CONFIG_PATH . DIRECTORY_SEPARATOR . 'conf.php')) {
    echo json_encode([
        'success' => false,
        'error' => 'Configuration file not found',
    ]);
    exit(1);
}

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . "profile_loader.php");
require_once(LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");

$db = new sql();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if requesting API badges list
    if (isset($_GET['badges']) && $_GET['badges'] == '1') {
        try {
            $badges = $db->fetchAll("SELECT id, label FROM core_api_badge ORDER BY label");
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'badges' => $badges ?: [],
                ],
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load API badges: ' . $e->getMessage(),
            ]);
        }
        exit(0);
    }

    // Check if requesting supported LLM connectors for MCP routing
    if (isset($_GET['connectors']) && $_GET['connectors'] == '1') {
        try {
            $connectors = $db->fetchAll("
                SELECT
                    c.id,
                    c.label,
                    c.service,
                    c.driver,
                    c.url,
                    c.model,
                    c.api_badge_id,
                    b.label AS api_badge_label
                FROM core_llm_connector c
                LEFT JOIN core_api_badge b ON b.id = c.api_badge_id
                WHERE LOWER(COALESCE(c.service, '')) IN ('openrouter', 'openai', 'google', 'nanogpt')
                ORDER BY LOWER(COALESCE(c.service, '')), LOWER(COALESCE(c.label, '')), c.id
            ");

            echo json_encode([
                'success' => true,
                'data' => [
                    'connectors' => $connectors ?: [],
                ],
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load connectors: ' . $e->getMessage(),
            ]);
        }
        exit(0);
    }
    
    // Get MCP configuration
    try {
        $configKeys = [
            'MCP/enabled',
            'MCP/port',
            'MCP/model',
            'MCP/temperature',
            'MCP/api_badge_id',
            'MCP/llm_connector_id',
            'MCP/system_prompt',
            'MCP/max_tool_rounds',
        ];
        
        $config = [];
        foreach ($configKeys as $key) {
            $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '" . $db->escape($key) . "' LIMIT 1");
            
            // Convert key to underscore format for JSON
            $jsonKey = str_replace('MCP/', '', $key);
            $jsonKey = strtolower(str_replace('/', '_', $jsonKey));
            
            $config[$jsonKey] = isset($row['value']) ? $row['value'] : null;
        }
        
        // Set defaults if not configured
        if ($config['enabled'] === null) {
            $config['enabled'] = 'true';
        }
        if ($config['port'] === null) {
            $config['port'] = '3100';
        }
        if ($config['model'] === null || trim((string)$config['model']) === '') {
            $config['model'] = 'anthropic/claude-sonnet-4.5';
        }
        if ($config['temperature'] === null || trim((string)$config['temperature']) === '') {
            $config['temperature'] = '0.7';
        }
        if ($config['max_tool_rounds'] === null || trim((string)$config['max_tool_rounds']) === '') {
            $config['max_tool_rounds'] = '20';
        }
        
        echo json_encode([
            'success' => true,
            'data' => $config,
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to load configuration: ' . $e->getMessage(),
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update MCP configuration
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON input',
            ]);
            exit(1);
        }
        
        // Map of frontend keys to database keys
        $keyMap = [
            'api_badge_id' => 'MCP/api_badge_id',
            'llm_connector_id' => 'MCP/llm_connector_id',
            'model' => 'MCP/model',
            'temperature' => 'MCP/temperature',
            'system_prompt' => 'MCP/system_prompt',
            'max_tool_rounds' => 'MCP/max_tool_rounds',
        ];
        
        foreach ($keyMap as $inputKey => $dbKey) {
            if (isset($input[$inputKey])) {
                $value = $input[$inputKey];

                // Skip empty system prompt (use default)
                if ($inputKey === 'system_prompt' && trim($value) === '') {
                    // Delete the key so it uses the default
                    $db->delete('conf_opts', "id = '" . $db->escape($dbKey) . "'");
                    continue;
                }

                // Allow clearing connector selection
                if ($inputKey === 'llm_connector_id' && trim((string)$value) === '') {
                    $db->delete('conf_opts', "id = '" . $db->escape($dbKey) . "'");
                    continue;
                }

                if ($inputKey === 'model' && trim((string)$value) === '') {
                    $value = 'anthropic/claude-sonnet-4.5';
                }

                if ($inputKey === 'max_tool_rounds') {
                    $rounds = intval($value);
                    $value = $rounds >= 1 ? strval($rounds) : '20';
                }

                if ($inputKey === 'temperature') {
                    if (trim((string)$value) === '') {
                        $value = '0.7';
                    } else {
                        $temp = floatval($value);
                        if ($temp < 0.0) {
                            $temp = 0.0;
                        } elseif ($temp > 2.0) {
                            $temp = 2.0;
                        }
                        $value = strval($temp);
                    }
                }
                
                // Upsert the value
                $db->upsertRowOnConflict(
                    'conf_opts',
                    ['id' => $dbKey, 'value' => $value],
                    'id'
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuration saved successfully',
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save configuration: ' . $e->getMessage(),
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);
}
