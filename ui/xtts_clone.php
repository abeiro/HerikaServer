<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Load necessary files for returnLines() function
$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "online_translation.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

// XTTS voice test handler - MUST be before output buffering starts
if (isset($_GET['action']) && $_GET['action'] === 'test_xtts' && isset($_GET['voice'])) {
    // Set up logging
    $logFile = $enginePath . 'log.txt';
    $logMsg = "\n=== XTTS Test Handler Started at " . date('Y-m-d H:i:s') . " ===\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);
    
    $voice = $_GET['voice'];
    @file_put_contents($logFile, "Voice requested: " . $voice . "\n", FILE_APPEND);
    
    // Set up TTS test environment (same as global_settings)
    try { @set_time_limit(30); } catch (Throwable $_) {}
    try { @ini_set('default_socket_timeout', '20'); } catch (Throwable $_) {}
    
    // Initialize database if needed
    try {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }
        @file_put_contents($logFile, "Database initialized\n", FILE_APPEND);
    } catch (Throwable $e) {
        @file_put_contents($logFile, "Database init error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    $GLOBALS["TTSFUNCTION"] = 'xtts-fastapi';
    $GLOBALS["HERIKA_NAME"] = "The Narrator";
    $GLOBALS["AVOID_TTS_CACHE"] = true;
    $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
    $GLOBALS["HERIKA_ANIMATIONS"] = false;
    $GLOBALS["SCRIPTLINE_LISTENER"] = '';
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
    $GLOBALS["DEBUG_DATA"] = [];
    if (!isset($GLOBALS["HTTP_TIMEOUT"]) || (int)$GLOBALS["HTTP_TIMEOUT"] <= 0) { $GLOBALS["HTTP_TIMEOUT"] = 20; }
    $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
    if (!isset($GLOBALS["FEATURES"]["MISC"])) $GLOBALS["FEATURES"]["MISC"] = [];
    if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
    if (!isset($GLOBALS["PLAYER_NAME"])) $GLOBALS["PLAYER_NAME"] = 'Player';
    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $GLOBALS["PATCH_OVERRIDE_VOICE"] = $voice;
    
    @file_put_contents($logFile, "Globals configured\n", FILE_APPEND);
    
    $testText = "CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis";
    
    header('Content-Type: application/json');
    @file_put_contents($logFile, "Headers sent\n", FILE_APPEND);
    
    try {
        @file_put_contents($logFile, "Calling returnLines...\n", FILE_APPEND);
        
        // Check if returnLines exists
        if (!function_exists('returnLines')) {
            @file_put_contents($logFile, "ERROR: returnLines function not found!\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => 'returnLines function not available']);
            exit;
        }
        
        returnLines([$testText], false);
        @file_put_contents($logFile, "returnLines completed\n", FILE_APPEND);
        
        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0]) : '';
        @file_put_contents($logFile, "Generated file: " . ($file ?: 'NONE') . "\n", FILE_APPEND);
        
        if ($file !== '') {
            $url = $webRoot . '/soundcache/' . $file . '?ts=' . time();
            @file_put_contents($logFile, "URL: " . $url . "\n", FILE_APPEND);
            @file_put_contents($logFile, "Sending JSON response...\n", FILE_APPEND);
            echo json_encode(['url' => $url]);
            @file_put_contents($logFile, "JSON sent successfully\n", FILE_APPEND);
        } else {
            @file_put_contents($logFile, "ERROR: No file generated\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate audio file']);
        }
    } catch (Throwable $e) {
        $errMsg = "EXCEPTION: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString() . "\n";
        @file_put_contents($logFile, $errMsg, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    @file_put_contents($logFile, "=== Test Handler Complete ===\n\n", FILE_APPEND);
    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    exit;
}

// Handle test voice requests (GET) - MUST be before any output buffering
if (isset($_GET['action']) && ($_GET['action'] === 'test_cartesia' || $_GET['action'] === 'test_inworld') && isset($_GET['voice'])) {
    // Initialize database connection for test handlers
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
    }
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }
    
    // Helper function for test handlers
    function getClonedVoicesForTest($provider) {
        global $db;
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            return [];
        }
        $db = $GLOBALS["db"];
        $prefix = $provider . '_voice_id_';
        $prefixEscaped = $db->escape($prefix);
        $rows = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
        $cloned = [];
        foreach ($rows as $row) {
            $voiceName = str_replace($prefix, '', $row['id']);
            $cloned[$voiceName] = $row['value'];
        }
        return $cloned;
    }
    
    if ($_GET['action'] === 'test_cartesia') {
        $voice = $_GET['voice'];
        $clonedVoices = getClonedVoicesForTest('cartesia');
        
        if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Voice not generated yet. Please sync this voice first.']);
            exit;
        }
        
        require_once(__DIR__ . '/../tts/tts-cartesia.php');
        $testText = 'CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis';
        $audioData = generateCartesiaTTS($testText, $clonedVoices[$voice]);
        
        if ($audioData !== false && !empty($audioData)) {
            // Save to soundcache like other TTS functions
            $soundcacheDir = __DIR__ . '/../soundcache/';
            if (!is_dir($soundcacheDir)) {
                mkdir($soundcacheDir, 0777, true);
            }
            $testHash = md5('test_cartesia_' . $voice . '_' . $testText);
            $audioFile = $soundcacheDir . $testHash . '.wav';
            file_put_contents($audioFile, $audioData);
            
            // Return URL to the audio file
            $webRoot = dirname(dirname($_SERVER['SCRIPT_NAME']));
            if ($webRoot == '/') $webRoot = '';
            $webRoot = rtrim($webRoot, '/');
            $audioUrl = $webRoot . '/soundcache/' . $testHash . '.wav?ts=' . time();
            
            header('Content-Type: application/json');
            echo json_encode(['url' => $audioUrl]);
            exit;
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate test audio. The voice ID may be invalid or expired.']);
        exit;
    }
    
    if ($_GET['action'] === 'test_inworld') {
        $voice = $_GET['voice'];
        $clonedVoices = getClonedVoicesForTest('inworld');
        
        if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Voice not generated yet. Please sync this voice first.']);
            exit;
        }
        
        require_once(__DIR__ . '/../tts/tts-inworld.php');
        $testText = 'CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis';
        
        // Generate raw PCM audio data (no output file = returns raw PCM)
        $audioData = generateInworldTTS($testText, $clonedVoices[$voice]);
        
        if ($audioData !== false && !empty($audioData)) {
            // Save to soundcache like other TTS functions
            $soundcacheDir = __DIR__ . '/../soundcache/';
            if (!is_dir($soundcacheDir)) {
                mkdir($soundcacheDir, 0777, true);
            }
            $testHash = md5('test_inworld_' . $voice . '_' . $testText);
            
            // Write raw PCM to temporary file
            $pcmFile = $soundcacheDir . $testHash . '_temp.pcm';
            file_put_contents($pcmFile, $audioData);
            
            // Convert raw PCM to WAV using FFmpeg (Inworld returns LINEAR16 PCM at 22050 Hz, mono)
            $audioFile = $soundcacheDir . $testHash . '.wav';
            $ffmpegCmd = "ffmpeg -y -f s16le -ar 22050 -ac 1 -i \"$pcmFile\" -c:a pcm_s16le -ar 22050 -ac 1 \"$audioFile\" 2>&1";
            $ffmpegOutput = shell_exec($ffmpegCmd);
            
            // Clean up temporary PCM file
            if (file_exists($pcmFile)) {
                unlink($pcmFile);
            }
            
            // Check if WAV file was created successfully
            if (file_exists($audioFile) && filesize($audioFile) > 0) {
                // Return URL to the audio file
                $webRoot = dirname(dirname($_SERVER['SCRIPT_NAME']));
                if ($webRoot == '/') $webRoot = '';
                $webRoot = rtrim($webRoot, '/');
                $audioUrl = $webRoot . '/soundcache/' . $testHash . '.wav?ts=' . time();
                
                header('Content-Type: application/json');
                echo json_encode(['url' => $audioUrl]);
                exit;
            } else {
                // FFmpeg conversion failed
                error_log("Inworld test audio FFmpeg conversion failed: " . substr($ffmpegOutput, 0, 500));
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Failed to convert audio to WAV format. Check server logs.']);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate test audio. The voice ID may be invalid or expired.']);
        exit;
    }
}

$TITLE = "🔊 Voice Management";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

// Add meta tag for API endpoint
echo '<meta name="api-endpoint" content="' . htmlspecialchars($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '">';

$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;

// Enable error reporting (for development purposes)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tab state from URL parameter
$activeTab = $_GET['tab'] ?? 'xtts';

// Initialize database connection
if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
    require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
}
if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
    $GLOBALS["db"] = new sql();
}

// Load TTS functions for Cartesia and Inworld
require_once(__DIR__ . '/../tts/tts-cartesia.php');
require_once(__DIR__ . '/../tts/tts-inworld.php');

// Helper functions
function getLocalVoices() {
    $voiceDir = __DIR__ . '/../data/voices/';
    $voices = [];
    if (is_dir($voiceDir)) {
        foreach (glob($voiceDir . '*.wav') as $file) {
            $voices[] = pathinfo($file, PATHINFO_FILENAME);
        }
    }
    sort($voices);
    return $voices;
}

function getClonedVoices($provider) {
    global $db;
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        return [];
    }
    $db = $GLOBALS["db"];
    $prefix = $provider . '_voice_id_';
    $prefixEscaped = $db->escape($prefix);
    $rows = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
    $cloned = [];
    foreach ($rows as $row) {
        $voiceName = str_replace($prefix, '', $row['id']);
        $cloned[$voiceName] = $row['value'];
    }
    return $cloned;
}

function isProviderConfigured($provider) {
    global $db;
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        return false;
    }
    $db = $GLOBALS["db"];
    $providerLower = strtolower($provider);
    $row = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='{$providerLower}' LIMIT 1");
    return (is_array($row) && !empty($row['api_key']));
}

if (!function_exists('normalize_endpoint_url')) {
    function normalize_endpoint_url($url) {
        // Remove trailing slashes
        $url = rtrim($url, '/');
        return $url;
    }
}

// Define the endpoint for the XTTS API
if (!isset($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]))
    $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] = 'http://127.0.0.1:8020';

// Normalize the endpoint URL
$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"] = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]);

// Initialize message variables
$message = '';
$speakersMessage = '';
$cartesiaMessage = '';
$inworldMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cartesia sync handler (all missing)
    if (isset($_POST['action']) && $_POST['action'] === 'sync_cartesia') {
        $localVoices = getLocalVoices();
        $clonedVoices = getClonedVoices('cartesia');
        $syncedCount = 0;
        $errorCount = 0;
        $rateLimitCount = 0;
        $firstVoice = true;
        
        foreach ($localVoices as $voice) {
            if (!isset($clonedVoices[$voice])) {
                $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
                if (file_exists($voiceSamplePath)) {
                    // Add delay between requests to avoid rate limiting (2 seconds)
                    if (!$firstVoice) {
                        sleep(2);
                    }
                    $firstVoice = false;
                    
                    $result = getOrCreateCartesiaVoice($voice);
                    if ($result !== false) {
                        $syncedCount++;
                    } else {
                        $errorCount++;
                        // Check if it's a rate limit error by checking error logs
                        $errorMsg = error_get_last();
                        if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                            $rateLimitCount++;
                            $cartesiaMessage .= "<p style='color:orange;'>Rate limit hit while generating voice: {$voice}. Please wait a moment and try syncing remaining voices.</p>";
                            // Stop syncing if we hit rate limit
                            break;
                        } else {
                            $cartesiaMessage .= "<p>Error generating voice: {$voice}</p>";
                        }
                    }
                }
            }
        }
        
        if ($syncedCount > 0) {
            $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully synced {$syncedCount} voice(s) to Cartesia.</strong></p>";
        }
        if ($rateLimitCount > 0) {
            $cartesiaMessage .= "<p style='color:orange;'><strong>Hit rate limit after {$syncedCount} voices. Please wait a few minutes before syncing remaining voices.</strong></p>";
        }
        if ($errorCount > 0 && $rateLimitCount === 0) {
            $cartesiaMessage .= "<p style='color:red;'><strong>Failed to sync {$errorCount} voice(s).</strong></p>";
        }
        if ($syncedCount === 0 && $errorCount === 0 && $rateLimitCount === 0) {
            $cartesiaMessage .= "<p>All voices are already synced to Cartesia.</p>";
        }
    }
    
    // Cartesia single voice sync handler
    if (isset($_POST['action']) && $_POST['action'] === 'sync_cartesia_single' && isset($_POST['voice'])) {
        $voice = $_POST['voice'];
        $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
        if (file_exists($voiceSamplePath)) {
            $result = getOrCreateCartesiaVoice($voice);
            if ($result !== false && !empty($result)) {
                // Redirect to refresh the page and show updated status
                header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=cartesia&synced=' . urlencode($voice));
                exit;
            } else {
                $cartesiaMessage .= "<p style='color:red;'><strong>Failed to generate voice '{$voice}' for Cartesia. Please check API configuration and logs.</strong></p>";
            }
        } else {
            $cartesiaMessage .= "<p style='color:red;'><strong>Voice file not found: {$voice}</strong></p>";
        }
    }
    
    // Cartesia clear cache handler
    if (isset($_POST['action']) && $_POST['action'] === 'clear_cartesia_cache') {
        $db = $GLOBALS["db"];
        $prefixEscaped = $db->escape('cartesia_voice_id_');
        $db->execQuery("DELETE FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
        $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Cartesia voice cache cleared.</strong></p>";
    }
    
    // Cartesia upload handler
    if (isset($_POST["submit_cartesia"])) {
        $total = count($_FILES['file']['name']);
        $uploadedCount = 0;
        $clonedCount = 0;
        $errorCount = 0;
        $rateLimitHit = false;
        
        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $cartesiaMessage .= '<p style="color:red;">Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                $errorCount++;
                continue;
            }

            // Get the uploaded file details
            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileType = mime_content_type($fileTmpPath);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Directory where you want to save the uploaded file
            $saveDir = __DIR__ . '/../data/voices/';

            // Ensure the directory exists
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0777, true);
            }

            // Ensure the file is a .wav file
            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $cartesiaMessage .= "<p style='color:red;'>Error: Please upload a valid .wav file. ($fileName)</p>";
                $errorCount++;
            } else {
                // Save the file to the specified directory
                $destinationPath = $saveDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $uploadedCount++;
                    $voiceName = pathinfo($fileName, PATHINFO_FILENAME);
                    
                    // Automatically generate the voice for Cartesia
                    $result = getOrCreateCartesiaVoice($voiceName);
                    if ($result !== false && !empty($result)) {
                        $clonedCount++;
                        $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully uploaded and generated voice '{$voiceName}' for Cartesia.</strong></p>";
                    } else {
                        // Check if it's a rate limit error
                        $errorMsg = error_get_last();
                        if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                            $rateLimitHit = true;
                            $cartesiaMessage .= "<p style='color:orange;'>Voice '{$voiceName}' uploaded but rate limit reached. Please wait before uploading more.</p>";
                        } else {
                            $cartesiaMessage .= "<p style='color:orange;'>Voice '{$voiceName}' uploaded but failed to generate. Please check API configuration and logs.</p>";
                        }
                    }
                } else {
                    $cartesiaMessage .= "<p style='color:red;'>Error: File could not be saved to $destinationPath.</p>";
                    $errorCount++;
                }
            }
        }
        
        // Summary message
        if ($uploadedCount > 0 && $clonedCount === $uploadedCount) {
            $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully uploaded and generated {$uploadedCount} voice(s) for Cartesia.</strong></p>";
        } elseif ($uploadedCount > 0 && $clonedCount < $uploadedCount) {
            $cartesiaMessage .= "<p style='color:orange;'><strong>Uploaded {$uploadedCount} voice(s), but only {$clonedCount} were successfully generated.</strong></p>";
        }
    }
    
    // Inworld sync handler (all missing)
    if (isset($_POST['action']) && $_POST['action'] === 'sync_inworld') {
        $localVoices = getLocalVoices();
        $clonedVoices = getClonedVoices('inworld');
        $syncedCount = 0;
        $errorCount = 0;
        $rateLimitCount = 0;
        $firstVoice = true;
        
        foreach ($localVoices as $voice) {
            if (!isset($clonedVoices[$voice])) {
                $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
                if (file_exists($voiceSamplePath)) {
                    // Add delay between requests to avoid rate limiting (3 seconds for Inworld)
                    if (!$firstVoice) {
                        sleep(3);
                    }
                    $firstVoice = false;
                    
                    $result = getOrCreateInworldVoice($voice);
                    if ($result !== false) {
                        $syncedCount++;
                    } else {
                        $errorCount++;
                        // Check if it's a rate limit error
                        $errorMsg = error_get_last();
                        if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                            $rateLimitCount++;
                            $inworldMessage .= "<p style='color:orange;'>Rate limit hit while generating voice: {$voice}. Please wait a moment and try syncing remaining voices.</p>";
                            // Stop syncing if we hit rate limit
                            break;
                        } else {
                            $inworldMessage .= "<p>Error generating voice: {$voice}</p>";
                        }
                    }
                }
            }
        }
        
        if ($syncedCount > 0) {
            $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully synced {$syncedCount} voice(s) to Inworld.</strong></p>";
        }
        if ($rateLimitCount > 0) {
            $inworldMessage .= "<p style='color:orange;'><strong>Hit rate limit after {$syncedCount} voices. Please wait a few minutes before syncing remaining voices.</strong></p>";
        }
        if ($errorCount > 0 && $rateLimitCount === 0) {
            $inworldMessage .= "<p style='color:red;'><strong>Failed to sync {$errorCount} voice(s).</strong></p>";
        }
        if ($syncedCount === 0 && $errorCount === 0 && $rateLimitCount === 0) {
            $inworldMessage .= "<p>All voices are already synced to Inworld.</p>";
        }
    }
    
    // Inworld single voice sync handler
    if (isset($_POST['action']) && $_POST['action'] === 'sync_inworld_single' && isset($_POST['voice'])) {
        $voice = $_POST['voice'];
        $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
        if (file_exists($voiceSamplePath)) {
            $result = getOrCreateInworldVoice($voice);
            if ($result !== false && !empty($result)) {
                // Redirect to refresh the page and show updated status
                header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=inworld&synced=' . urlencode($voice));
                exit;
            } else {
                $inworldMessage .= "<p style='color:red;'><strong>Failed to generate voice '{$voice}' for Inworld. Please check API configuration and logs.</strong></p>";
            }
        } else {
            $inworldMessage .= "<p style='color:red;'><strong>Voice file not found: {$voice}</strong></p>";
        }
    }
    
    // Inworld clear cache handler
    if (isset($_POST['action']) && $_POST['action'] === 'clear_inworld_cache') {
        $db = $GLOBALS["db"];
        $prefixEscaped = $db->escape('inworld_voice_id_');
        $db->execQuery("DELETE FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
        $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Inworld voice cache cleared.</strong></p>";
    }
    
    // Inworld upload handler
    if (isset($_POST["submit_inworld"])) {
        $total = count($_FILES['file']['name']);
        $uploadedCount = 0;
        $clonedCount = 0;
        $errorCount = 0;
        $rateLimitHit = false;
        
        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $inworldMessage .= '<p style="color:red;">Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                $errorCount++;
                continue;
            }

            // Get the uploaded file details
            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileType = mime_content_type($fileTmpPath);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Directory where you want to save the uploaded file
            $saveDir = __DIR__ . '/../data/voices/';

            // Ensure the directory exists
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0777, true);
            }

            // Ensure the file is a .wav file
            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $inworldMessage .= "<p style='color:red;'>Error: Please upload a valid .wav file. ($fileName)</p>";
                $errorCount++;
            } else {
                // Save the file to the specified directory
                $destinationPath = $saveDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $uploadedCount++;
                    $voiceName = pathinfo($fileName, PATHINFO_FILENAME);
                    
                    // Automatically generate the voice for Inworld
                    $result = getOrCreateInworldVoice($voiceName);
                    if ($result !== false && !empty($result)) {
                        $clonedCount++;
                        $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully uploaded and generated voice '{$voiceName}' for Inworld.</strong></p>";
                    } else {
                        // Check if it's a rate limit error
                        $errorMsg = error_get_last();
                        if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                            $rateLimitHit = true;
                            $inworldMessage .= "<p style='color:orange;'>Voice '{$voiceName}' uploaded but rate limit reached. Please wait before uploading more.</p>";
                        } else {
                            $inworldMessage .= "<p style='color:orange;'>Voice '{$voiceName}' uploaded but failed to generate. Please check API configuration and logs.</p>";
                        }
                    }
                } else {
                    $inworldMessage .= "<p style='color:red;'>Error: File could not be saved to $destinationPath.</p>";
                    $errorCount++;
                }
            }
        }
        
        // Summary message
        if ($uploadedCount > 0 && $clonedCount === $uploadedCount) {
            $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully uploaded and generated {$uploadedCount} voice(s) for Inworld.</strong></p>";
        } elseif ($uploadedCount > 0 && $clonedCount < $uploadedCount) {
            $inworldMessage .= "<p style='color:orange;'><strong>Uploaded {$uploadedCount} voice(s), but only {$clonedCount} were successfully generated.</strong></p>";
        }
    }
    
    
    // XTTS single voice sync handler
    if (isset($_POST['action']) && $_POST['action'] === 'sync_xtts_single' && isset($_POST['voice'])) {
        $voice = $_POST['voice'];
        $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
        if (file_exists($voiceSamplePath)) {
            $fileName = $voice . '.wav';
            $fileType = mime_content_type($voiceSamplePath);
            
            // Prepare the cURL request
            $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/upload_sample';
            $cfile = new CURLFile($voiceSamplePath, $fileType, $fileName);
            
            $postFields = array('wavFile' => $cfile);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'accept: application/json',
                'Content-Type: multipart/form-data'
            ));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $message .= '<p style="color:red;">Error syncing voice to XTTS/Chatterbox server: ' . curl_error($ch) . '</p>';
            } else {
                if ($httpCode == 200) {
                    // Refresh speakers list and redirect to show updated status
                    $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/speakers_list';
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, $url);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, array('accept: application/json'));
                    $response2 = curl_exec($ch2);
                    
                    if (!curl_errno($ch2) && curl_getinfo($ch2, CURLINFO_HTTP_CODE) == 200) {
                        $speakersList = json_decode($response2, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($speakersList)) {
                            $_SESSION['xtts_speakers_list'] = $speakersList;
                        }
                    }
                    curl_close($ch2);
                    
                    header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=xtts&synced=' . urlencode($voice));
                    exit;
                } else {
                    $message .= '<p style="color:red;">Error syncing voice to XTTS/Chatterbox server (HTTP code ' . $httpCode . '): ' . htmlspecialchars($response) . '</p>';
                }
            }
            curl_close($ch);
        } else {
            $message .= "<p style='color:red;'><strong>Voice file not found: {$voice}</strong></p>";
        }
    }
    
    // Get speakers list for POST request - store in session for display
    if (isset($_POST["get_speakers"])) {
        $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/speakers_list';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json'));
        $response = curl_exec($ch);
        
        if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
            $speakersList = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($speakersList)) {
                $_SESSION['xtts_speakers_list'] = $speakersList;
            }
        }
        curl_close($ch);
    }
    if (isset($_POST["submit"])) {
        $total = count($_FILES['file']['name']);
        for( $i=0 ; $i < $total ; $i++ ) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $message .= '<p>Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                continue;
            }

            // Get the uploaded file details
            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileType = mime_content_type($fileTmpPath);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Directory where you want to save the uploaded file
            $saveDir = __DIR__ . '/../data/voices/';  // Adjust the path if needed

            // Ensure the directory exists
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0777, true);
            }

            // Ensure the file is a .wav file
            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $message .= "<p>Error: Please upload a valid .wav file.</p>";
            } else {
                // Save the file to the specified directory
                $destinationPath = $saveDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $message .= "<p>.wav file has been uploaded to $destinationPath</p>";

                    // Prepare the cURL request
                    $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/upload_sample';
                    $cfile = new CURLFile($destinationPath, $fileType, $fileName);

                    $postFields = array('wavFile' => $cfile);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'accept: application/json',
                        'Content-Type: multipart/form-data'
                    ));

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    if (curl_errno($ch)) {
                        $message .= '<p>cURL Error: ' . curl_error($ch) . '</p>';
                    } else {
                        if ($httpCode == 200) {
                            $message .= "<p>.wav file has been cached to the CHIM server</p>";
                        } else {
                            $message .= '<p>Response from server (HTTP code ' . $httpCode . '): ' . htmlspecialchars($response) . '</p>';
                        }
                    }
                    curl_close($ch);
                } else {
                    $message .= "<p>Error: File could not be saved to $destinationPath.</p>";
                }
            }
        }
    } elseif (isset($_POST["upload_all"])) {
        // Upload all .wav files in ../data/voices
        $saveDir = __DIR__ . '/../data/voices/';
        $files = glob($saveDir . '*.wav');
        $numFiles = count($files);
        $numUploaded = 0;

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $fileType = mime_content_type($filePath);

            // Ensure the file is a .wav file
            if ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav') {
                $message .= "<p>Error: $fileName is not a valid .wav file.</p>";
            } else {
                // Prepare the cURL request
                $url = normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '/upload_sample';
                $cfile = new CURLFile($filePath, $fileType, $fileName);

                $postFields = array('wavFile' => $cfile);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'accept: application/json',
                    'Content-Type: multipart/form-data'
                ));

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    $message .= '<p>cURL Error while uploading ' . htmlspecialchars($fileName) . ': ' . curl_error($ch) . '</p>';
                } else {
                    if ($httpCode == 200) {
                        $numUploaded++;
                    } else {
                        $message .= '<p>Error uploading ' . htmlspecialchars($fileName) . ' (HTTP code ' . $httpCode . '): ' . htmlspecialchars($response) . '</p>';
                    }
                }
                curl_close($ch);
            }
        }
        $message .= "<p><h3 style='color:rgb(247, 231, 16);'>$numUploaded out of $numFiles voice files have been uploaded.</h3></p>";
    }
}


// Add the JavaScript functions
?>
<script>
    const API_ENDPOINT = <?php echo json_encode(normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"])); ?>;
    const WEB_ROOT = <?php echo json_encode($webRoot); ?>;

    // Clean up URL after showing success message
    window.addEventListener('DOMContentLoaded', function() {
        const url = new URL(window.location);
        if (url.searchParams.has('synced')) {
            // Remove the 'synced' parameter from URL without refreshing
            url.searchParams.delete('synced');
            window.history.replaceState({}, '', url);
        }
    });

    function normalizeUrl(url) {
        return url.replace(/\/+$/, '');
    }

    function showLoadingMessage(customMessage) {
        const overlay = document.getElementById('loading-overlay');
        const messageEl = document.getElementById('loading-message');
        if (customMessage && messageEl) {
            messageEl.innerHTML = customMessage + ' <br><b>Do not refresh the page<span id="ellipsis"></span></b>';
        }
        overlay.style.display = 'block';
        animateEllipsis();
    }

    function animateEllipsis() {
        var ellipsis = document.getElementById('ellipsis');
        var dots = 0;
        window.ellipsisInterval = setInterval(function() {
            dots = (dots + 1) % 4;
            var dotStr = '';
            for (var i = 0; i < dots; i++) {
                dotStr += '.';
            }
            ellipsis.innerHTML = dotStr;
        }, 500);
    }

    function showToast(message, duration = 1500) {
        const toast = document.getElementById('toast');
        const messageSpan = toast.querySelector('.message');
        messageSpan.textContent = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard');
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    }

    function testVoice(voiceName) {
        const testUrl = WEB_ROOT + '/ui/xtts_clone.php?action=test_xtts&voice=' + encodeURIComponent(voiceName);
        
        fetch(testUrl)
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.error || 'Failed to generate test audio');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.url) {
                    const audio = new Audio(data.url);
                    audio.play().catch(function(err) {
                        console.error('Error playing audio:', err);
                        alert('Error playing audio: ' + err.message);
                    });
                } else {
                    throw new Error(data.error || 'No audio URL returned');
                }
            })
            .catch(function(err) {
                console.error('Error testing voice:', err);
                alert('Error: ' + err.message);
            });
    }

    function switchTab(tabName) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('tab', tabName);
        window.location.href = currentUrl.toString();
    }

    function testCartesiaVoice(voiceName) {
        const testUrl = WEB_ROOT + '/ui/xtts_clone.php?action=test_cartesia&voice=' + encodeURIComponent(voiceName);
        
        fetch(testUrl)
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.error || 'Failed to generate test audio');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.url) {
                    const audio = new Audio(data.url);
                    audio.play().catch(function(err) {
                        console.error('Error playing audio:', err);
                        alert('Error playing test audio. Please check the console for details.');
                    });
                } else {
                    throw new Error(data.error || 'No audio URL returned');
                }
            })
            .catch(function(err) {
                console.error('Error testing voice:', err);
                alert('Error: ' + err.message);
            });
    }

    function testInworldVoice(voiceName) {
        const testUrl = WEB_ROOT + '/ui/xtts_clone.php?action=test_inworld&voice=' + encodeURIComponent(voiceName);
        
        fetch(testUrl)
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.error || 'Failed to generate test audio');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.url) {
                    const audio = new Audio(data.url);
                    audio.play().catch(function(err) {
                        console.error('Error playing audio:', err);
                        alert('Error playing test audio. Please check the console for details.');
                    });
                } else {
                    throw new Error(data.error || 'No audio URL returned');
                }
            })
            .catch(function(err) {
                console.error('Error testing voice:', err);
                alert('Error: ' + err.message);
            });
    }

    function syncSingleVoice(provider, voiceName) {
        const actionText = provider === 'xtts' ? 'Syncing voice to XTTS server' : 'Generating voice for ' + provider;
        showLoadingMessage(actionText + ', please wait...');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = WEB_ROOT + '/ui/xtts_clone.php?tab=' + provider;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'sync_' + provider + '_single';
        
        const voiceInput = document.createElement('input');
        voiceInput.type = 'hidden';
        voiceInput.name = 'voice';
        voiceInput.value = voiceName;
        
        form.appendChild(actionInput);
        form.appendChild(voiceInput);
        document.body.appendChild(form);
        form.submit();
    }
</script>
<?php

?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Override main container styles */
    main {
        padding-top: 80px;
        padding-bottom: 40px;
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* Page Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin-bottom: 15px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header h3 {
        text-align: center;
        margin-bottom: 15px;
    }

    .page-header h4 {
        text-align: center;
        margin-bottom: 25px;
    }

    /* Content Section Headers */
    .content-section h1, .indent5 h1 {
        font-family: 'MagicCards', serif;
        font-size: 1.8em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        word-spacing: 8px;
        text-align: center;
        margin-bottom: 20px;
    }

    /* Form Container Styling */
    .form-container {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Content Layout Improvements */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    /* Voice list styling */
    .response-container {
        margin-top: 15px;
        padding: 15px;
        background-color: #2c2c2c;
        border: 1px solid #4a4a4a;
        border-radius: 5px;
    }

    .voice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 8px;
        margin-top: 10px;
    }

    .speaker-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background-color: #3a3a3a;
        border: 1px solid #555555;
        border-radius: 4px;
        color: #f8f9fa;
    }

    .copy-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 12px;
        transition: all 0.2s ease;
        margin-left: 8px;
    }

    .speaker-item:hover .copy-btn {
        opacity: 0.8;
    }

    .copy-btn:hover {
        opacity: 1 !important;
        transform: scale(1.1);
    }

    .button-container {
        display: flex;
        gap: 4px;
        align-items: center;
    }

    .play-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 10px;
        transition: all 0.2s ease;
    }

    .speaker-item:hover .play-btn {
        opacity: 0.8;
    }

    .play-btn:hover {
        opacity: 1 !important;
        transform: scale(1.1);
    }

    .sync-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .voice-status-item:hover .sync-btn {
        opacity: 0.8;
    }

    .sync-btn:hover:not(:disabled) {
        opacity: 1 !important;
        transform: scale(1.1);
        color: rgb(242, 124, 17);
    }

    .sync-btn:disabled {
        opacity: 0.2;
        cursor: not-allowed;
    }

    /* Voice list container styling */
    .voice-list-container {
        background: #1a1a1a;
        border-radius: 8px;
        border: 2px solid rgb(242, 124, 17);
        padding: 20px;
        margin-top: 15px;
    }

    .voice-list-header h3 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        text-align: center;
    }

    .voice-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        background-color: #3a3a3a;
        border: 1px solid #555555;
        border-radius: 6px;
        color: #f8f9fa;
        transition: all 0.2s ease;
    }

    .voice-item:hover {
        background-color: #4a4a4a;
        border-color: rgb(242, 124, 17);
    }

    /* Tab Navigation */
    .tab-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #4a4a4a;
        padding-bottom: 10px;
    }

    .tab-btn {
        padding: 12px 24px;
        background: #2a2a2a;
        border: 2px solid #4a4a4a;
        border-bottom: none;
        border-radius: 8px 8px 0 0;
        color: #cfd8e3;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        margin-bottom: -2px;
    }

    .tab-btn:hover {
        background: #3a3a3a;
        color: rgb(242, 124, 17);
    }

    .tab-btn.active {
        background: #2a2a2a;
        border-color: rgb(242, 124, 17);
        border-bottom: 2px solid #2a2a2a;
        color: rgb(242, 124, 17);
        position: relative;
        z-index: 1;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .voice-status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 12px;
        margin-top: 20px;
    }

    .voice-status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background-color: #3a3a3a;
        cursor: pointer;
        border: 1px solid #555555;
        border-radius: 6px;
        color: #f8f9fa;
        transition: all 0.2s ease;
    }

    .voice-status-item:hover {
        background-color: #4a4a4a;
        border-color: rgb(242, 124, 17);
    }

    .voice-status-item .voice-name {
        flex: 1;
        font-weight: 500;
    }

    .voice-status-item .status-icon {
        margin: 0 12px;
        font-size: 18px;
    }

    .voice-status-item .status-icon.cloned {
        color: #4caf50;
    }

    .voice-status-item .status-icon.not-cloned {
        color: #f44336;
    }

    .voice-status-item .voice-id {
        font-size: 11px;
        color: #aaa;
        margin-right: 8px;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .form-container {
            padding: 15px;
        }
        
        .content-section {
            padding: 15px;
        }

        .page-header {
            padding: 15px;
        }

        .page-header h1 {
            font-size: 1.8em;
        }

        .content-section h1, .indent5 h1 {
            font-size: 1.6em;
        }

        .voice-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .page-header h1 {
            font-size: 1.5em;
        }

        .content-section h1, .indent5 h1 {
            font-size: 1.3em;
        }
        
        .button-group {
            flex-direction: column;
        }

        .voice-grid {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .voice-item {
            padding: 8px 12px;
        }
    }
</style>

<?php if ($isEmbed): ?>
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 20px; }
</style>
<?php endif; ?>

<main>
    <div id="loading-overlay">
        <p id="loading-message">Processing, please wait... <br><b>Do not refresh the page<span id="ellipsis"></span></b></p>
    </div>

    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>Voice Management 
            <a href="https://dwemerdynamics.hostwiki.io/en/Auto-TTS-Voices" target="_blank" rel="noopener" 
               style="display: inline-block; margin-left: 15px; color: rgb(242, 124, 17); text-decoration: none; font-size: 0.7em; vertical-align: top; border: 2px solid rgb(242, 124, 17); border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 20px; transition: all 0.3s ease;" 
               title="View detailed documentation about TTS Voice Configuration"
               onmouseover="this.style.background='rgb(242, 124, 17)'; this.style.color='white';" 
               onmouseout="this.style.background='transparent'; this.style.color='rgb(242, 124, 17)';">ℹ</a>
        </h1>
        <p>Manage voice samples across multiple TTS providers: XTTS/Chatterbox, Cartesia, and Inworld.</p>
        <p style="color: #f0ad4e; font-size: 0.9em; margin-top: 10px;"><strong>Note:</strong> If you switch between XTTS and Chatterbox, you must resync your voices by clicking "Sync All Voices" below.</p>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn <?php echo $activeTab === 'xtts' ? 'active' : ''; ?>" 
                onclick="switchTab('xtts')">XTTS/Chatterbox</button>
        <button class="tab-btn <?php echo $activeTab === 'cartesia' ? 'active' : ''; ?>" 
                onclick="switchTab('cartesia')">Cartesia</button>
        <button class="tab-btn <?php echo $activeTab === 'inworld' ? 'active' : ''; ?>" 
                onclick="switchTab('inworld')">Inworld</button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (!empty($cartesiaMessage)): ?>
        <div class="message"><?php echo $cartesiaMessage; ?></div>
    <?php endif; ?>
    <?php if (!empty($inworldMessage)): ?>
        <div class="message"><?php echo $inworldMessage; ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['synced']) && $activeTab === 'xtts'): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Successfully synced voice '<?php echo htmlspecialchars($_GET['synced']); ?>' to XTTS server.</strong></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['synced']) && $activeTab === 'cartesia'): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Successfully generated voice '<?php echo htmlspecialchars($_GET['synced']); ?>' for Cartesia.</strong></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['synced']) && $activeTab === 'inworld'): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Successfully generated voice '<?php echo htmlspecialchars($_GET['synced']); ?>' for Inworld.</strong></p>
        </div>
    <?php endif; ?>

    <!-- XTTS Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'xtts' ? 'active' : ''; ?>">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be cached and uploaded to the XTTS/Chatterbox server.</p>
            
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=xtts" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <label for="file" style="display: block; margin-bottom: 8px;">Select .wav file(s) to upload:</label>
                    <input type="file" name="file[]" id="file" accept=".wav" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                    <li><b>Chatterbox:</b> Audio clips must be at least <b>5 seconds</b> long for voice generation</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> If replacing an existing voice, restart the XTTS/Chatterbox server after upload.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>XTTS/Chatterbox Voice Cache</h1>
            <p>Manage voice samples for XTTS/Chatterbox. Voices are uploaded from local .wav files in <code>data/voices</code> to the XTTS/Chatterbox server.</p>
            
            <?php
            $localVoices = getLocalVoices();
            $xttsVoices = [];
            if (isset($_SESSION['xtts_speakers_list']) && is_array($_SESSION['xtts_speakers_list'])) {
                foreach ($_SESSION['xtts_speakers_list'] as $speaker) {
                    $displayName = basename($speaker, '.wav');
                    $xttsVoices[$displayName] = true;
                }
            }
            ?>
            
            <div class="button-group" style="margin-top: 20px;">
                <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=xtts" method="post" style="display: inline;">
                    <input type="hidden" name="get_speakers" value="1">
                    <input type="submit" value="Refresh XTTS Server Voices" class="action-button download-csv">
                </form>
            </div>

            <div class="voice-status-grid" style="margin-top: 20px;">
                <?php foreach ($localVoices as $voice): ?>
                    <?php $isOnServer = isset($xttsVoices[$voice]); ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isOnServer ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isOnServer ? '✓' : '✗'; ?>
                        </span>
                        <div class="button-container">
                            <?php if ($isOnServer): ?>
                                <button onclick="event.stopPropagation(); testVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" 
                                        class="play-btn" title="Test voice">▶</button>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('xtts', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" 
                                        class="sync-btn" title="Sync this voice to XTTS server">↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples above first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Cloud XTTS/Chatterbox Sync</h1>
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=xtts" method="post" onsubmit="showLoadingMessage('Syncing voice cache to XTTS/Chatterbox server, this can take a couple minutes...');">
                <p><strong>Only required for online XTTS/Chatterbox instances.</strong></p>
                <p>Sync just needs to be ran ONE TIME after initial setup of a new instance.</p>
                <p style="color: #f0ad4e;"><strong>Important:</strong> If you switch between XTTS and Chatterbox, run "Sync All Voices" to resync your voice cache.</p>
                <p>Empty voice cache is acceptable - new NPC voices will be cached automatically.</p>
                <p>For cloud setup instructions, see our <a href="https://dwemerdynamics.hostwiki.io/en/Vast-AI" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cloud XTTS Guide</a>.</p>
                <p>Cached voices are stored in <code>data/voices</code>. <a href="<?php echo $webRoot; ?>/data/voices" style="color: yellow;" target="_blank">View Cache Directory</a></p>
                <div class="button-group">
                    <input type="submit" name="upload_all" value="Sync Voice Cache" class="action-button edit">
                </div>
            </form>
            <p>Advanced XTTS configuration: <a href="<?php echo normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]); ?>/docs" style="color: yellow;" target="_blank"><?php echo normalize_endpoint_url($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]); ?>/docs</a></p>
        </div>
    </div>

    <!-- Cartesia Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'cartesia' ? 'active' : ''; ?>">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be available for generating voices in Cartesia.</p>
            
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=cartesia" method="post" enctype="multipart/form-data" style="margin-top: 20px;" onsubmit="showLoadingMessage('Uploading and generating voice for Cartesia, please wait...');">
                <div style="margin-bottom: 15px;">
                    <label for="file_cartesia" style="display: block; margin-bottom: 8px;">Select .wav file(s) to upload:</label>
                    <input type="file" name="file[]" id="file_cartesia" accept=".wav" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit_cartesia" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> Files will be saved to data/voices and automatically generated for Cartesia.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Cartesia Voice Cacje</h1>
            <p>Manage voice generation for Cartesia TTS. Voices are generated from local .wav files in <code>data/voices</code>.</p>
            <p>For detailed information, see our <a href="https://dwemerdynamics.hostwiki.io/en/TTS-Options#cartesia" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cartesia TTS Guide</a>.</p>
            
            <?php
            $cartesiaConfigured = isProviderConfigured('cartesia');
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('cartesia');
            $missingVoices = array_diff($localVoices, array_keys($clonedVoices));
            ?>
            
            <?php if (!$cartesiaConfigured): ?>
                <div style="background: rgba(244, 67, 54, 0.1); border: 2px solid #f44336; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                    <p style="margin: 0; font-weight: 600; color: #f44336;">⚠️ Cartesia API not configured</p>
                    <p style="margin: 8px 0 0 0;">Please configure your Cartesia API key in the <a href="<?php echo $webRoot; ?>/ui/core/api_badge.php" style="color: yellow;">API Badge</a> page before syncing voices.</p>
                </div>
            <?php endif; ?>
            
            <div style="background: rgba(74, 138, 182, 0.1); border: 2px solid #4a8ab6; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                <p style="margin: 0; font-weight: 600; color: #4a8ab6;">ℹ️ Automatic Voice Generation</p>
                <p style="margin: 8px 0 0 0;">Voices are automatically generated when you speak to an NPC using that voice in-game, if Cartesia TTS is selected as your TTS provider. You don't need to manually sync all voices upfront.</p>
            </div>

            <div class="voice-status-grid">
                <?php foreach ($localVoices as $voice): ?>
                    <?php $isCloned = isset($clonedVoices[$voice]); ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isCloned ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isCloned ? '✓' : '✗'; ?>
                        </span>
                        <?php if ($isCloned): ?>
                            <span class="voice-id" title="<?php echo htmlspecialchars($clonedVoices[$voice]); ?>">
                                <?php echo htmlspecialchars(substr($clonedVoices[$voice], 0, 15)) . (strlen($clonedVoices[$voice]) > 15 ? '...' : ''); ?>
                            </span>
                        <?php endif; ?>
                        <div class="button-container">
                            <?php if ($isCloned): ?>
                                <button onclick="event.stopPropagation(); testCartesiaVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" 
                                        class="play-btn" title="Test voice">▶</button>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('cartesia', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" 
                                        class="sync-btn" title="Sync this voice" <?php echo !$cartesiaConfigured ? 'disabled' : ''; ?>>↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples in the XTTS tab first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Inworld Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'inworld' ? 'active' : ''; ?>">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be available for generating voices in Inworld.</p>
            
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=inworld" method="post" enctype="multipart/form-data" style="margin-top: 20px;" onsubmit="showLoadingMessage('Uploading and generating voice for Inworld, please wait...');">
                <div style="margin-bottom: 15px;">
                    <label for="file_inworld" style="display: block; margin-bottom: 8px;">Select .wav file(s) to upload:</label>
                    <input type="file" name="file[]" id="file_inworld" accept=".wav" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit_inworld" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> Files will be saved to data/voices and automatically generated for Inworld.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Inworld Voice Cache</h1>
            <p>Manage voice generation for Inworld TTS. Voices are generated from local .wav files in <code>data/voices</code>.</p>
            <p>For detailed information, see our <a href="https://dwemerdynamics.hostwiki.io/en/TTS-Options#inworld" style="color: yellow;" target="_blank" rel="noopener noreferrer">Inworld TTS Guide</a>.</p>
            
            <?php
            $inworldConfigured = isProviderConfigured('inworld');
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('inworld');
            $missingVoices = array_diff($localVoices, array_keys($clonedVoices));
            ?>
            
            <?php if (!$inworldConfigured): ?>
                <div style="background: rgba(244, 67, 54, 0.1); border: 2px solid #f44336; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                    <p style="margin: 0; font-weight: 600; color: #f44336;">⚠️ Inworld API not configured</p>
                    <p style="margin: 8px 0 0 0;">Please configure your Inworld API credential in the <a href="<?php echo $webRoot; ?>/ui/core/api_badge.php" style="color: yellow;">API Badge</a> page before syncing voices.</p>
                </div>
            <?php endif; ?>
            
            <div style="background: rgba(74, 138, 182, 0.1); border: 2px solid #4a8ab6; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                <p style="margin: 0; font-weight: 600; color: #4a8ab6;">ℹ️ Automatic Voice Generation</p>
                <p style="margin: 8px 0 0 0;">Voices are automatically generated when you speak to an NPC using that voice in-game, if Inworld TTS is selected as your TTS provider. You don't need to manually sync all voices upfront.</p>
            </div>

            <div class="voice-status-grid">
                <?php foreach ($localVoices as $voice): ?>
                    <?php $isCloned = isset($clonedVoices[$voice]); ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isCloned ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isCloned ? '✓' : '✗'; ?>
                        </span>
                        <?php if ($isCloned): ?>
                            <span class="voice-id" title="<?php echo htmlspecialchars($clonedVoices[$voice]); ?>">
                                <?php echo htmlspecialchars(substr($clonedVoices[$voice], 0, 15)) . (strlen($clonedVoices[$voice]) > 15 ? '...' : ''); ?>
                            </span>
                        <?php endif; ?>
                        <div class="button-container">
                            <?php if ($isCloned): ?>
                                <button onclick="event.stopPropagation(); testInworldVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" 
                                        class="play-btn" title="Test voice">▶</button>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('inworld', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" 
                                        class="sync-btn" title="Sync this voice" <?php echo !$inworldConfigured ? 'disabled' : ''; ?>>↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples in the XTTS tab first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
