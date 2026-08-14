<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."oghma_parity.php");

$TITLE = "ðŸ“CHIM - NPC Biography";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;
// Navbar hidden on this page

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Initialize message variable
$message = '';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

if (!function_exists('chimNormalizeBiographyRelationshipSeed')) {
    function chimNormalizeBiographyRelationshipSeed($value, &$errorMessage = '')
    {
        $errorMessage = '';

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $trimmed = trim((string)$value);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '{') {
            $errorMessage = 'expected a JSON object with per-target relationship seeds';
            return false;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            $errorMessage = 'invalid JSON object';
            return false;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

//
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//   INDIVIDUAL UPLOAD
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    $npc_name   = strtolower(trim($_POST['npc_name'] ?? ''));
    $core       = $_POST['npc_pers'] ?? '';
    $oghma_knowledge_tags = chimOghmaNpcKnowledgeTags($_POST['npc_misc'] ?? '');
    $voiceid    = (!empty($_POST['voiceid'])) ? trim($_POST['voiceid']) : null;
    $gender     = (!empty($_POST['gender'])) ? trim($_POST['gender']) : null;
    $race       = (!empty($_POST['race'])) ? trim($_POST['race']) : null;
    $refid      = (!empty($_POST['refid'])) ? trim($_POST['refid']) : null;

    // Extended profile fields (bio schema)
    $npc_static_bio   = (!empty($_POST['npc_background']))    ? trim($_POST['npc_background'])    : null;
    $personality      = (!empty($_POST['npc_personality']))   ? trim($_POST['npc_personality'])   : null;
    $appearance       = (!empty($_POST['npc_appearance']))    ? trim($_POST['npc_appearance'])    : null;
    $relationshipError = '';
    $relationships    = chimNormalizeBiographyRelationshipSeed($_POST['npc_relationships'] ?? null, $relationshipError);
    $occupation       = (!empty($_POST['npc_occupation']))    ? trim($_POST['npc_occupation'])    : null;
    $skills           = (!empty($_POST['npc_skills']))        ? trim($_POST['npc_skills'])        : null;
    $speechstyle      = (!empty($_POST['npc_speechstyle']))   ? trim($_POST['npc_speechstyle'])   : null;
    $goals            = (!empty($_POST['npc_goals']))         ? trim($_POST['npc_goals'])         : null;

    if ($relationships === false) {
        $message .= "<p style='color:#ff6464;'>Relationships must be a valid JSON object seed. "
            . htmlspecialchars($relationshipError, ENT_QUOTES, 'UTF-8')
            . ".</p>";
    } elseif (!empty($npc_name) && !empty($core)) {
        $query = "
            INSERT INTO {$schema}.bio_templates_custom
                (npc_name, core, oghma_knowledge_tags, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, voiceid, gender, race, refid)
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15)
            ON CONFLICT (npc_name)
            DO UPDATE SET
                core = EXCLUDED.core,
                oghma_knowledge_tags = EXCLUDED.oghma_knowledge_tags,
                npc_static_bio = EXCLUDED.npc_static_bio,
                personality = EXCLUDED.personality,
                appearance = EXCLUDED.appearance,
                relationships = EXCLUDED.relationships,
                occupation = EXCLUDED.occupation,
                skills = EXCLUDED.skills,
                speechstyle = EXCLUDED.speechstyle,
                goals = EXCLUDED.goals,
                voiceid = EXCLUDED.voiceid,
                gender = EXCLUDED.gender,
                race = EXCLUDED.race,
                refid = EXCLUDED.refid
        ";

        $params = [
            $npc_name,
            $core,
            $oghma_knowledge_tags,
            $npc_static_bio,
            $personality,
            $appearance,
            $relationships,
            $occupation,
            $skills,
            $speechstyle,
            $goals,
            $voiceid,
            $gender,
            $race,
            $refid
        ];

        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>NPC data inserted/updated successfully!</p>";
        } else {
            $message .= "<p>Error inserting/updating NPC data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please fill in all required fields: NPC Name and Summary Bio.</p>";
    }
}

//
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//   CSV UPLOAD - Safe Biography Import (same logic as biography_import.php)
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csv'])) {
    // Check if a file was uploaded without errors
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];

        // Allowed file extensions
        $allowedfileExtensions = array('csv');

        // Get file extension
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Process CSV using the same logic as biography_import.php
            $processedCount = 0;
            $errorCount = 0;
            
            try {
                // Step 1: Read the entire file
                $csvData = @file_get_contents($fileTmpPath);
                if ($csvData === false) {
                    $message .= '<p style="color:#ff6464;">Error reading the uploaded CSV file.</p>';
                } else {
                    // Step 2: Detect and normalize encoding to UTF-8
                    $detectedEncoding = 'UTF-8';
                    
                    // Check for UTF-16 (presence of NUL bytes)
                    if (strpos($csvData, "\x00") !== false) {
                        $bom = substr($csvData, 0, 2);
                        if ($bom === "\xFF\xFE") {
                            $csvData = mb_convert_encoding(substr($csvData, 2), 'UTF-8', 'UTF-16LE');
                            $detectedEncoding = 'UTF-16LE with BOM';
                        } elseif ($bom === "\xFE\xFF") {
                            $csvData = mb_convert_encoding(substr($csvData, 2), 'UTF-8', 'UTF-16BE');
                            $detectedEncoding = 'UTF-16BE with BOM';
                        } else {
                            $csvData = mb_convert_encoding($csvData, 'UTF-8', 'UTF-16');
                            $detectedEncoding = 'UTF-16';
                        }
                    }
                    
                    // Strip UTF-8 BOM if present
                    if (substr($csvData, 0, 3) === "\xEF\xBB\xBF") {
                        $csvData = substr($csvData, 3);
                        $detectedEncoding = 'UTF-8 with BOM';
                    }
                    
                    // If not valid UTF-8, try Windows-1252
                    if (!mb_check_encoding($csvData, 'UTF-8')) {
                        $csvData = mb_convert_encoding($csvData, 'UTF-8', 'Windows-1252');
                        $detectedEncoding = 'Windows-1252';
                    }
                    
                    // Step 3: Parse CSV using stream (handles multi-line fields properly)
                    $stream = fopen('php://memory', 'r+');
                    fwrite($stream, $csvData);
                    rewind($stream);
                    
                    // Auto-detect delimiter from first non-empty line
                    $delimiter = ',';
                    $tempPos = ftell($stream);
                    while (($line = fgets($stream)) !== false) {
                        if (trim($line) !== '') {
                            $delimiterCounts = [
                                ',' => substr_count($line, ','),
                                ';' => substr_count($line, ';'),
                                "\t" => substr_count($line, "\t")
                            ];
                            arsort($delimiterCounts);
                            $delimiter = array_key_first($delimiterCounts);
                            break;
                        }
                    }
                    rewind($stream);
                    $delimiterName = ($delimiter === "\t") ? 'TAB' : $delimiter;
                    
                    if (feof($stream) && $tempPos === ftell($stream)) {
                        $message .= '<p style="color:#ff6464;">CSV file appears to be empty.</p>';
                        fclose($stream);
                    } else {
                        // Step 4: Process all rows flexibly (no header validation needed)
                        $errors = [];
                        
                        while (($data = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
                            // Skip empty rows
                            if (empty($data) || (count($data) === 1 && trim((string)($data[0] ?? '')) === '')) {
                                continue;
                            }
                            
                            // Skip header rows (any row where first cell is "npc_name")
                            if (count($data) >= 1 && strtolower(trim((string)($data[0] ?? ''))) === 'npc_name') {
                                continue;
                            }
                            
                            // Must have at least 2 columns
                            if (count($data) < 2) {
                                continue;
                            }
                            
                            // Extract fields by position (flexible - works with or without headers)
                            $npc_name = isset($data[0]) ? strtolower(trim((string)$data[0])) : '';
                            $core = isset($data[1]) ? trim((string)$data[1]) : '';
                            
                            // Skip if required fields are empty
                            if (empty($npc_name) || empty($core)) {
                                continue;
                            }

                            // Truncate npc_name to 128 characters
                            if (strlen($npc_name) > 128) {
                                $npc_name = substr($npc_name, 0, 128);
                            }
                            
                            // Extract fields by position (based on example_bios_format.csv column order)
                            // Order: npc_name, core, oghma_knowledge_tags, npc_static_bio, personality, 
                            //        appearance, relationships, occupation, skills, speechstyle, goals, 
                            //        voiceid, gender, race, refid
                            $getValue = function($index) use ($data) {
                                if (isset($data[$index])) {
                                    $temp = trim((string)$data[$index]);
                                    return ($temp !== '') ? $temp : null;
                                }
                                return null;
                            };
                            
                            $oghma_knowledge_tags = chimOghmaNpcKnowledgeTags($getValue(2) ?? '');
                            $npc_static_bio = $getValue(3);
                            $personality = $getValue(4);
                            $appearance = $getValue(5);
                            $relationships = $getValue(6);
                            $occupation = $getValue(7);
                            $skills = $getValue(8);
                            $speechstyle = $getValue(9);
                            $goals = $getValue(10);
                            $voiceid = $getValue(11);
                            $gender = $getValue(12);
                            $race = $getValue(13);
                            $refid = $getValue(14);

                            $relationshipError = '';
                            $relationships = chimNormalizeBiographyRelationshipSeed($relationships, $relationshipError);
                            if ($relationships === false) {
                                $errors[] = "NPC '$npc_name': relationships must be a valid JSON object seed ($relationshipError)";
                                $errorCount++;
                                continue;
                            }
                                    
                            // Insert into database
                        $query = "
                            INSERT INTO $schema.bio_templates_custom 
                                    (npc_name, core, oghma_knowledge_tags, npc_static_bio, personality, appearance, 
                                     relationships, occupation, skills, speechstyle, goals, voiceid, gender, race, refid)
                            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15)
                            ON CONFLICT (npc_name)
                            DO UPDATE SET
                                core = EXCLUDED.core,
                                oghma_knowledge_tags = EXCLUDED.oghma_knowledge_tags,
                                npc_static_bio = EXCLUDED.npc_static_bio,
                                personality = EXCLUDED.personality,
                                appearance = EXCLUDED.appearance,
                                relationships = EXCLUDED.relationships,
                                occupation = EXCLUDED.occupation,
                                skills = EXCLUDED.skills,
                                speechstyle = EXCLUDED.speechstyle,
                                goals = EXCLUDED.goals,
                                voiceid = EXCLUDED.voiceid,
                                gender = EXCLUDED.gender,
                                race = EXCLUDED.race,
                                refid = EXCLUDED.refid
                        ";

                        $params = [
                            $npc_name,
                            $core,
                            $oghma_knowledge_tags,
                                $npc_static_bio,
                                $personality,
                                $appearance,
                                $relationships,
                                $occupation,
                                $skills,
                                $speechstyle,
                                $goals,
                            $voiceid,
                            $gender,
                            $race,
                            $refid
                        ];

                        $result = pg_query_params($conn, $query, $params);

                        if ($result) {
                                $processedCount++;
                        } else {
                                $errors[] = "NPC '$npc_name': " . pg_last_error($conn);
                                $errorCount++;
                            }
                        }
                        
                        // Close the stream
                        fclose($stream);
                        
                        // Build success message
                        if ($processedCount > 0) {
                            $toastMessage = "âœ“ Successfully imported $processedCount NPC record" . ($processedCount > 1 ? 's' : '');
                            if ($errorCount > 0) {
                                $toastMessage .= " ($errorCount error" . ($errorCount > 1 ? 's' : '') . ")";
                            }
                        } else {
                            $toastMessage = "âš ï¸ No NPC records were imported";
                        }
                        
                        $message .= '<div class="form-container" style="margin-top:10px; border:2px solid #4ade80;">'
                            . '<h3 style="color:#4ade80;">âœ“ CSV Import Successful</h3>'
                            . '<p><strong>' . $processedCount . '</strong> NPC records imported successfully.</p>'
                            . '<p style="font-size:0.9em; color:#888;">Encoding: ' . htmlspecialchars($detectedEncoding) 
                            . ' | Delimiter: ' . htmlspecialchars($delimiterName) . '</p>';
                        
                        if (!empty($errors)) {
                            $message .= '<details style="margin-top:10px;"><summary style="cursor:pointer; color:#ff6464;">âš ï¸ ' 
                                . count($errors) . ' errors occurred</summary>'
                                . '<pre style="white-space:pre-wrap; background:#1f1f1f; padding:10px; border-radius:4px; margin-top:10px;">'
                                . htmlspecialchars(implode("\n", $errors))
                                . '</pre></details>';
                        }
                        
                        $message .= '</div>';
                    }
                }
            } catch (Exception $e) {
                $message .= '<p style="color:#ff6464;">Fatal error processing CSV: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        } else {
            $message .= '<p>Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions) . '</p>';
        }
    } else {
        $message .= '<p>No file uploaded or there was an upload error.</p>';
    }
}

//
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//   TRUNCATE NPC TABLE
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_npc'])) {
    $truncateQuery = "TRUNCATE TABLE $schema.bio_templates_custom RESTART IDENTITY CASCADE";
    $truncateResult = pg_query($conn, $truncateQuery);

    if ($truncateResult) {
        $message .= "<p style='color: #ff6464; font-weight: bold;'>The bio_templates_custom table has been emptied successfully.</p>";
    } else {
        $message .= "<p>Error emptying bio_templates_custom table: " . pg_last_error($conn) . "</p>";
    }
}

//
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//   DOWNLOAD EXAMPLE
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//
if (isset($_GET['action']) && $_GET['action'] === 'download_example') {
    // Define the path to the example CSV file
    $filePath = realpath(__DIR__ . '/../data/example_bios_format.csv');

    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="example_bios.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        ob_end_clean();
        flush();
        readfile($filePath);
        exit;
    } else {
        $message .= '<p>Example CSV file not found.</p>';
    }
}

//
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//   EXPORT CUSTOM NPC DATA
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//
if (isset($_GET['action']) && $_GET['action'] === 'export_custom_npcs') {
    // Query to get all custom NPC data
    $export_query = "
        SELECT 
            npc_name, core, oghma_knowledge_tags, 
            npc_static_bio, personality, appearance, 
            relationships, occupation, skills, 
            speechstyle, goals, voiceid, gender, race, refid
        FROM {$schema}.bio_templates_custom 
        ORDER BY npc_name ASC
    ";
    
    $export_result = pg_query($conn, $export_query);
    
    if ($export_result) {
        // Set headers for CSV download
        $filename = 'custom_npc_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Clean any existing output
        ob_end_clean();
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        $csv_headers = [
            'npc_name', 'core', 'oghma_knowledge_tags',
            'npc_static_bio', 'personality', 'appearance',
            'relationships', 'occupation', 'skills',
            'speechstyle', 'goals', 'voiceid', 'gender', 'race', 'refid'
        ];
        fputcsv($output, $csv_headers);
        
        // Write data rows
        while ($row = pg_fetch_assoc($export_result)) {
            $csv_row = [
                $row['npc_name'] ?? '',
                $row['core'] ?? '',
                $row['oghma_knowledge_tags'] ?? '',
                $row['npc_static_bio'] ?? '',
                $row['personality'] ?? '',
                $row['appearance'] ?? '',
                $row['relationships'] ?? '',
                $row['occupation'] ?? '',
                $row['skills'] ?? '',
                $row['speechstyle'] ?? '',
                $row['goals'] ?? '',
                $row['voiceid'] ?? '',
                $row['gender'] ?? '',
                $row['race'] ?? '',
                $row['refid'] ?? ''
            ];
            fputcsv($output, $csv_row);
        }
        
        fclose($output);
        exit;
    } else {
        $message .= '<p>Error exporting custom NPC data: ' . pg_last_error($conn) . '</p>';
    }
}

// 1. Update the edit modal form to match the Oghma styling:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_single') {
    $npc_name_original = $_POST['npc_name_original'] ?? '';
    $npc_name = strtolower(trim($_POST['npc_name'] ?? ''));
    $npc_pers = $_POST['npc_pers'] ?? '';
    $npc_dynamic = (isset($_POST['npc_dynamic']) && trim($_POST['npc_dynamic']) !== '') 
        ? trim($_POST['npc_dynamic']) 
        : null;
    $npc_misc = (isset($_POST['npc_misc']) && trim($_POST['npc_misc']) !== '') 
        ? trim($_POST['npc_misc']) 
        : '';
    $melotts_voiceid = (!empty($_POST['melotts_voiceid'])) ? trim($_POST['melotts_voiceid']) : null;
    $xtts_voiceid = (!empty($_POST['xtts_voiceid'])) ? trim($_POST['xtts_voiceid']) : null;
    $xvasynth_voiceid = (!empty($_POST['xvasynth_voiceid'])) ? trim($_POST['xvasynth_voiceid']) : null;
    
    // New extended profile fields
    $npc_background    = (!empty($_POST['npc_background']))    ? trim($_POST['npc_background'])    : null;
    $npc_personality   = (!empty($_POST['npc_personality']))   ? trim($_POST['npc_personality'])   : null;
    $npc_appearance    = (!empty($_POST['npc_appearance']))    ? trim($_POST['npc_appearance'])    : null;
    $npc_relationships = (!empty($_POST['npc_relationships'])) ? trim($_POST['npc_relationships']) : null;
    $npc_occupation    = (!empty($_POST['npc_occupation']))    ? trim($_POST['npc_occupation'])    : null;
    $npc_skills        = (!empty($_POST['npc_skills']))        ? trim($_POST['npc_skills'])        : null;
    $npc_speechstyle   = (!empty($_POST['npc_speechstyle']))   ? trim($_POST['npc_speechstyle'])   : null;
    $npc_goals         = (!empty($_POST['npc_goals']))         ? trim($_POST['npc_goals'])         : null;

    if (!empty($npc_name) && !empty($npc_pers)) {
        $query = "
            UPDATE {$schema}.npc_templates_custom 
            SET 
                npc_name = $1,
                npc_pers = $2,
                npc_dynamic = $3,
                npc_misc = $4,
                melotts_voiceid = $5,
                xtts_voiceid = $6,
                xvasynth_voiceid = $7,
                npc_background = $8,
                npc_personality = $9,
                npc_appearance = $10,
                npc_relationships = $11,
                npc_occupation = $12,
                npc_skills = $13,
                npc_speechstyle = $14,
                npc_goals = $15
            WHERE npc_name = $16
        ";

        $params = [
            $npc_name,
            $npc_pers,
            $npc_dynamic,
            $npc_misc,
            $melotts_voiceid,
            $xtts_voiceid,
            $xvasynth_voiceid,
            $npc_background,
            $npc_personality,
            $npc_appearance,
            $npc_relationships,
            $npc_occupation,
            $npc_skills,
            $npc_speechstyle,
            $npc_goals,
            $npc_name_original
        ];

        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>NPC data updated successfully!</p>";
        } else {
            $message .= "<p>Error updating NPC data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please fill in all required fields: NPC Name and NPC Static Bio.</p>";
    }
}

// 1. Update the edit modal form action to include the current letter:
$currentLetter = isset($_GET['letter']) ? htmlspecialchars($_GET['letter']) : '';
$formAction = $currentLetter ? "?letter={$currentLetter}#table" : "?#table";
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
        padding-top: 80px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
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
        height: 20px; /* Reduced footer height */
        background: #031633;
        z-index: 100;
    }

    /* Page Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .page-header h1 {
        margin-bottom: 8px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-subtitle {
        margin: 0;
        color: #bbb;
        font-size: 1.1em;
        line-height: 1.6;
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

    /* Modal specific overrides */
    .modal-backdrop {
        overflow-y: auto !important;
        padding: 20px 0;
    }

    .modal-container {
        position: relative !important;
        top: auto !important;
        left: auto !important;
        transform: none !important;
        margin: 80px auto 40px auto !important;
        max-width: 800px !important;
        width: 90% !important;
    }

    .modal-body {
        max-height: calc(100vh - 300px);
        overflow-y: auto;
        padding-right: 15px;
    }

    /* Form field spacing */
    .modal-body label {
        display: block;
        margin-top: 15px;
        color: rgb(242, 124, 17);
        font-weight: bold;
    }

    .modal-body small {
        display: block;
        color: #888;
        margin-bottom: 5px;
    }

    .modal-body input[type="text"],
    .modal-body textarea {
        width: 100%;
        margin-bottom: 15px;
    }

    .modal-footer {
        position: sticky;
        bottom: 0;
        background: #3a3a3a;
        padding: 15px 0;
        margin-top: 20px;
        border-top: 1px solid #4a4a4a;
    }

    /* Table container and styling improvements */
    .table-container {
        max-height: calc(100vh - 450px) !important;
        margin-top: 20px;
        width: 100%;
        overflow-x: auto;
    }

    /* Table styling improvements */
    .table-container table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    }

    /* Column width optimization */
    .table-container th:nth-child(1), /* Name */
    .table-container td:nth-child(1) {
        width: 12%;
        min-width: 100px;
    }

    .table-container th:nth-child(2), /* Summary Bio */
    .table-container td:nth-child(2) {
        width: 30%;
        min-width: 200px;
    }

    .table-container th:nth-child(3), /* Extended Profiles */
    .table-container td:nth-child(3) {
        width: 18%;
        min-width: 130px;
    }

    .table-container th:nth-child(4), /* Voice Overrides */
    .table-container td:nth-child(4) {
        width: 15%;
        min-width: 120px;
    }

    .table-container th:nth-child(5), /* Oghma Tags */
    .table-container td:nth-child(5) {
        width: 15%;
        min-width: 120px;
    }

    .table-container th:nth-child(6), /* Actions */
    .table-container td:nth-child(6) {
        width: 12%;
        min-width: 120px;
    }

    /* Text wrapping and overflow handling */
    .table-container td {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        vertical-align: top;
        padding: 8px;
        line-height: 1.4;
    }

    .table-container th {
        padding: 10px 8px;
        font-weight: bold;
        text-align: left;
        vertical-align: top;
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9));
        color: rgb(242, 124, 17);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        font-family: 'MagicCards', serif;
    }
    
    .table-container tbody tr {
        border-bottom: 1px solid #3a3a3a;
        transition: background 0.2s ease;
    }
    
    .table-container tbody tr:hover {
        background: rgba(58, 58, 58, 0.5);
    }

    /* Action container styling */
    .action-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }

    .search-container {
        display: flex;
        gap: 10px;
        min-width: 300px;
    }

    /* Filter section styling */
    .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin: 15px 0;
        justify-content: center;
    }

    /* Content sections */
    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .content-section:hover {
        border-color: #4a4a4a;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.05);
    }

    /* Responsive table for smaller screens */
    @media (max-width: 1200px) {
        .table-container {
            font-size: 0.9em;
        }
        
        .table-container th:nth-child(2), /* Summary Bio */
        .table-container td:nth-child(2) {
            width: 35%;
        }
        
        .table-container th:nth-child(3), /* Extended Profiles */
        .table-container td:nth-child(3) {
            width: 20%;
        }

        .table-container th:nth-child(4), /* Voice Overrides */
        .table-container td:nth-child(4) {
            width: 12%;
        }

        .table-container th:nth-child(5), /* Oghma Tags */
        .table-container td:nth-child(5) {
            width: 13%;
        }

        .table-container th:nth-child(6), /* Actions */
        .table-container td:nth-child(6) {
            width: 10%;
        }
    }

    @media (max-width: 900px) {
        .table-container {
            font-size: 0.8em;
        }
        
        .table-container th,
        .table-container td {
            padding: 6px 4px;
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .search-container {
            min-width: 200px;
        }
        
        .action-container {
            flex-direction: column;
            align-items: stretch;
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
    }
</style>

<?php if ($isEmbed): ?>
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 20px; }
</style>
<?php endif; ?>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>NPC Biography Management 
        </h1>
        <p class="page-subtitle">Create custom character profiles for AI NPCs during roleplay</p>
    </div>

    <div class="indent5">
        <div class="content-section">
            <h1>Batch Upload</h1>
            <form action="" method="post" enctype="multipart/form-data">
            <h3><strong>Please user underscores instead of spaces.</strong></h3>
            <h4>Example: Mjoll the Lioness becomes mjoll_the_lioness</h4>
                <div>
                    <label for="csv_file">Select .csv file to upload:</label>
                    <br>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </div>
                <div class="button-group">
                    <input type="submit" name="submit_csv" value="Upload CSV" class="action-button upload-csv">
                    <a href="?action=download_example" class="action-button download-csv">Download Example CSV</a>
                    <a href="?action=export_custom_npcs" class="action-button" style="background: rgba(242, 124, 17, 0.8);">Export Custom NPCs</a>
                </div>
                <p><strong>Relationships column:</strong> Use a JSON object seed such as <code>{"Player":{"aff":25,"type":"professional"}}</code>. Legacy prose no longer seeds the relationship affinity system.</p>
                <p>You can verify that NPC data has been uploaded successfully by going to 
                <b>Server Actions -> Database Manager -> dwemer -> public -> bio_templates_custom</b>.</p>
                <p>All uploaded biographies will be saved into the <code>bio_templates_custom</code> table. This overwrites any entries in the regular table.</p>
                <p>Also you can check the merged view at 
                <b>Server Actions -> Database Manager -> dwemer -> public -> Views (Top bar) -> combined_bio_templates</b>.</p>
                <p><strong>Export Custom NPCs:</strong> Download all your custom NPC entries as a CSV file for backup or sharing purposes. The exported file will include all custom entries with their extended profiles and voice overrides.</p>
            </form>
            <form action="" method="post">
                <input 
                    type="submit" 
                    name="truncate_npc" 
                    value="Factory Reset NPC Override Table"
                    class="btn-danger"
                    onclick="return confirm('Are you sure you want to DELETE ALL ENTRIES in npc_templates_custom? This action is IRREVERSIBLE!');"
                >
            </form>
            <p>This will just delete any custom NPC entires you have uploaded.</p>
            <p>You can download a backup of the full character database in the 
            <a href="https://discord.gg/NDn9qud2ug" target="_blank" rel="noopener">
                csv files channel in our discord
            </a>.
            </p>
        </div>
    </div>

    <br>
    <?php
    $letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';
    $searchTerm = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';

    // Build query based on filters
    if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
        if (!empty($searchTerm)) {
            // Filter by both letter and search term
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_bio_templates
                WHERE LOWER(npc_name) LIKE LOWER($1) 
                AND LOWER(npc_name) LIKE LOWER($2)
                ORDER BY npc_name ASC
            ";
            $params_combined = [$letter . '%', '%' . $searchTerm . '%'];
        } else {
            // Filter by letter only
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_bio_templates
                WHERE LOWER(npc_name) LIKE LOWER($1)
                ORDER BY npc_name ASC
            ";
            $params_combined = [$letter . '%'];
        }
    } else {
        if (!empty($searchTerm)) {
            // Filter by search term only
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_bio_templates
                WHERE LOWER(npc_name) LIKE LOWER($1)
                ORDER BY npc_name ASC
            ";
            $params_combined = ['%' . $searchTerm . '%'];
        } else {
            // No filters
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_bio_templates
                ORDER BY npc_name ASC
            ";
            $params_combined = [];
        }
    }

    $result_combined = !empty($params_combined) 
        ? pg_query_params($conn, $query_combined, $params_combined)
        : pg_query($conn, $query_combined);

    echo '<br>';
    // Wrap the NPC Templates Database section in a div for indentation
    echo '<div class="indent5" id="table">';
    echo '<h1>NPC Bio Templates Database</h1>';
    echo '<div class="action-container">';
    echo '<button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>';
    echo '<div class="search-container">';
    echo '<input type="text" id="searchBox" placeholder="Search NPC names..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">';
    echo '<button onclick="applySearch()" class="action-button edit">Search</button>';
    echo '</div>';
    echo '</div>';
    echo '<h3>Note: This is just for editing an NPC entry before they are activated ingame. Any further edits should be done in the configuration wizard.</h3>';
    echo '<p>You can not delete an NPC entry. You can simply make another one with the correct name if you make a mistake.</p>';

    echo '<br>';

    // Alphabetic filter
    echo '<div class="filter-buttons">';
    echo '<a href="?#table" class="alphabet-button">All</a>';
    foreach (range('A', 'Z') as $char) {
        echo '<a href="?letter=' . $char . '#table" class="alphabet-button">' . $char . '</a>';
    }
    echo '</div>';

    if ($result_combined) {
        echo '<div id="npc-table-container" class="table-container">';
        echo '<table>';
        echo '<tr>';
        echo '  <th>Name</th>';
        echo '  <th>Summary Bio</th>';
        echo '  <th>Extended Profiles</th>';
        echo '  <th>Voice Overrides</th>';
        echo '  <th>Oghma Tags</th>';
        echo '  <th>Actions</th>';
        echo '</tr>';

        $rowCountCombined = 0;
        while ($row = pg_fetch_assoc($result_combined)) {
            echo '<tr>';
            echo '  <td>' . htmlspecialchars($row['npc_name'] ?? '') . '</td>';
            echo '  <td style="max-width: 250px; word-wrap: break-word;">' . nl2br(htmlspecialchars(substr($row['core'] ?? '', 0, 200))) . (strlen($row['core'] ?? '') > 200 ? '...' : '') . '</td>';
            
            // Extended Profile summary
            $extendedFields = [
                'Appearance' => $row['appearance'] ?? '',
                'Static' => $row['npc_static_bio'] ?? '',
                'Personality' => $row['personality'] ?? '',
                'Relationships' => $row['relationships'] ?? '',
                'Occupation' => $row['occupation'] ?? '',
                'Skills' => $row['skills'] ?? '',
                'Speech Style' => $row['speechstyle'] ?? '',
                'Goals' => $row['goals'] ?? ''
            ];
            // Count fields that have actual content (not just empty strings or whitespace)
            $extendedCount = count(array_filter($extendedFields, function($value) {
                return !empty(trim($value));
            }));
            $totalExtendedFields = count($extendedFields);
            echo '  <td style="cursor: pointer; color: #4a9eff;" onclick="showExtendedProfile(\'' . 
                htmlspecialchars($row['npc_name'], ENT_QUOTES) . '\', ' . 
                htmlspecialchars(json_encode($extendedFields), ENT_QUOTES) . ')">';
            echo '<span style="color: #888; font-size: 0.9em;">' . $extendedCount . ' of ' . $totalExtendedFields . ' fields completed</span>';
            echo '<br><small style="color: #4a9eff; font-size: 0.8em;">Click to view details</small>';
            echo '</td>';
            
            // Voice Overrides summary
            $voiceFields = [
                'VoiceID' => $row['voiceid'] ?? '',
                'Gender' => $row['gender'] ?? '',
                'Race' => $row['race'] ?? '',
                'RefID' => $row['refid'] ?? ''
            ];
            echo '  <td style="font-size: 0.85em; line-height: 1.4;">';
            foreach ($voiceFields as $type => $voice) {
                $displayValue = !empty(trim($voice)) ? htmlspecialchars($voice) : '<span style="color: #888; font-style: italic;">Automatic</span>';
                echo '<div style="margin-bottom: 2px;"><strong>' . $type . ':</strong> ' . $displayValue . '</div>';
            }
            echo '</td>';
            
            // Oghma Tags (npc_misc) column
            $oghmaTagsValue = $row['oghma_knowledge_tags'] ?? '';
            echo '  <td style="font-size: 1.5em; line-height: 1.4;">';
            if (!empty(trim($oghmaTagsValue))) {
                // Split by commas and display as badges/tags
                $tags = array_map('trim', explode(',', $oghmaTagsValue));
                foreach ($tags as $tag) {
                    if (!empty($tag)) {
                        echo '<span style="display: inline-block; background: rgba(242, 124, 17, 0.2); color: rgb(242, 124, 17); padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 0.85em; font-weight: 500;">' . htmlspecialchars($tag) . '</span>';
                    }
                }
            } else {
                echo '<span style="color: #888; font-style: italic;">None</span>';
            }
            echo '</td>';
            
            // Add Edit and Oghma buttons
            echo '<td>';
            echo '<div class="button-group" style="display: flex; flex-direction: column; gap: 5px;">';
            $jsData = [
                'npc_name' => $row['npc_name'],
                'npc_pers' => $row['core'],
                'npc_dynamic' => '',
                'npc_misc' => $row['oghma_knowledge_tags'] ?? '',
                'voiceid' => $row['voiceid'] ?? '',
                'gender' => $row['gender'] ?? '',
                'race' => $row['race'] ?? '',
                'refid' => $row['refid'] ?? '',
                'npc_background' => $row['npc_static_bio'] ?? '',
                'npc_personality' => $row['personality'] ?? '',
                'npc_appearance' => $row['appearance'] ?? '',
                'npc_relationships' => $row['relationships'] ?? '',
                'npc_occupation' => $row['occupation'] ?? '',
                'npc_skills' => $row['skills'] ?? '',
                'npc_speechstyle' => $row['speechstyle'] ?? '',
                'npc_goals' => $row['goals'] ?? ''
            ];
            echo '<button onclick="openEditModal(' . 
                htmlspecialchars(str_replace(
                    ["\r", "\n", "'"],
                    [' ', ' ', "\\'"],
                    json_encode($jsData)
                ), ENT_QUOTES, 'UTF-8') . 
                ')" class="action-button edit" style="font-size: 0.8em; padding: 4px 8px;">Edit</button>';
            echo '<button onclick="openOghmaModal(\'' . 
                htmlspecialchars($row['npc_name'], ENT_QUOTES) . '\', \'' . 
                htmlspecialchars($row['oghma_knowledge_tags'] ?? '', ENT_QUOTES) . 
                '\')" class="action-button" style="background: rgba(242, 124, 17, 0.8); font-size: 0.8em; padding: 4px 8px;">Oghma</button>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
            
            $rowCountCombined++;
        }
        echo '</table>';
        echo '</div>';

        if ($rowCountCombined === 0) {
            echo '<p>No NPCs found.</p>';
        }
    } else {
        echo '<p>Error fetching combined NPC templates: ' . pg_last_error($conn) . '</p>';
    }

    echo '</div>';
    ?>
</main>

<div id="editModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit NPC Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="action" value="update_single">
                <input type="hidden" name="npc_name_original" id="edit_npc_name_original">

                <label for="edit_npc_name">NPC Name:</label>
                <small>NPC names cannot be changed after creation. If you need to change a name, create a new entry.</small>
                <input type="text" name="npc_name" id="edit_npc_name" readonly style="background-color: #2a2a2a; cursor: not-allowed;" required>

                <label for="edit_npc_misc">Oghma Tags:</label>
                <small>Optional: Oghma Knowledge Tags. Make sure to seperate with commas. <a href="https://dwemerdynamics.com/chim/roleplay-settings.html#OghmaInfinium" target="_blank" rel="noopener">Read more here!</a></small>
                <input type="text" name="npc_misc" id="edit_npc_misc">

                <label for="edit_npc_pers">Core:</label>
                <small>1-2 sentences about the character.</small>
                <textarea name="npc_pers" id="edit_npc_pers" rows="3" required></textarea>

                <!-- Extended Profile Fields -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Extended Profile</h3>
                
                <label for="edit_npc_background">Static (Details):</label>
                <small>Detailed history, origins, and past experiences that shaped this character.</small>
                <textarea name="npc_background" id="edit_npc_background" rows="4"></textarea>

                <label for="edit_npc_appearance">Appearance:</label>
                <small>Detailed description of physical features and distinguishing characteristics.</small>
                <textarea name="npc_appearance" id="edit_npc_appearance" rows="4"></textarea>

                <label for="edit_npc_personality">Personality:</label>
                <small>Detailed character traits, behavioral patterns, and psychological characteristics.</small>
                <textarea name="npc_personality" id="edit_npc_personality" rows="4"></textarea>

                <label for="edit_npc_relationships">Relationships:</label>
                <small>Must be a JSON object seed. New NPC imports copy this into <code>extended_data.relationships</code> for the relationship affinity system.</small>
                <textarea name="npc_relationships" id="edit_npc_relationships" rows="4"></textarea>

                <label for="edit_npc_occupation">Occupation:</label>
                <small>Current job, profession, duties, and position in society or organizations.</small>
                <textarea name="npc_occupation" id="edit_npc_occupation" rows="3"></textarea>

                <label for="edit_npc_skills">Skills:</label>
                <small>Special talents, combat abilities, magical knowledge, and areas of expertise.</small>
                <textarea name="npc_skills" id="edit_npc_skills" rows="3"></textarea>

                <label for="edit_npc_speechstyle">Speech Style:</label>
                <small>How this character speaks, including vocabulary, accent, mannerisms, and communication patterns.</small>
                <textarea name="npc_speechstyle" id="edit_npc_speechstyle" rows="3"></textarea>

                <label for="edit_npc_goals">Goals:</label>
                <small>Long-term objectives, personal ambitions, and life goals</small>
                <textarea name="npc_goals" id="edit_npc_goals" rows="3"></textarea>

                <!-- Voice & Meta Section -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Voice & Meta</h3>

                <label for="edit_voiceid">Voice ID:</label>
                <small>Optional: Unified voice identifier.</small>
                <input type="text" name="voiceid" id="edit_voiceid">

                <label for="edit_gender">Gender:</label>
                <small>Optional: Gender for reference.</small>
                <input type="text" name="gender" id="edit_gender">

                <label for="edit_race">Race:</label>
                <small>Optional: Race for reference.</small>
                <input type="text" name="race" id="edit_race">

                <label for="edit_refid">RefID:</label>
                <small>Optional: In-game reference ID.</small>
                <input type="text" name="refid" id="edit_refid">

                <div class="modal-footer">
                    <button type="submit" name="submit_individual" value="1" class="btn-save">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New NPC Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="submit_individual" value="1">

                <label for="new_npc_name">NPC Name:</label>
                <small>Please use underscores instead of spaces.</small>
                <input type="text" name="npc_name" id="new_npc_name" required>

                <label for="new_npc_misc">Oghma Tags:</label>
                <small>Optional: Oghma Knowledge Tags. Make sure to seperate with commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" target="_blank" rel="noopener">Read more here!</a></small>
                <input type="text" name="npc_misc" id="new_npc_misc">

                <label for="new_npc_pers">Core:</label>
                <small>1-2 sentences about the character.</small> 
                <textarea name="npc_pers" id="new_npc_pers" rows="3" required></textarea>

                <!-- Removed legacy dynamic bio field -->

                

                <!-- Extended Profile Fields -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Extended Profile</h3>
                
                <label for="new_npc_background">Static (Details):</label>
                <small>Detailed history, origins, and past experiences that shaped this character.</small>
                <textarea name="npc_background" id="new_npc_background" rows="4"></textarea>

                <label for="new_npc_appearance">Appearance:</label>
                <small>Detailed description of physical features and distinguishing characteristics.</small>
                <textarea name="npc_appearance" id="new_npc_appearance" rows="4"></textarea>

                <label for="new_npc_personality">Personality:</label>
                <small>Detailed character traits, behavioral patterns, and psychological characteristics.</small>
                <textarea name="npc_personality" id="new_npc_personality" rows="4"></textarea>

                <label for="new_npc_relationships">Relationships:</label>
                <small>Must be a JSON object seed. New NPC imports copy this into <code>extended_data.relationships</code> for the relationship affinity system.</small>
                <textarea name="npc_relationships" id="new_npc_relationships" rows="4"></textarea>
    
                <label for="new_npc_occupation">Occupation & Role:</label>
                <small>Current job, profession, duties, and position in society or organizations.</small>
                <textarea name="npc_occupation" id="new_npc_occupation" rows="3"></textarea>

                <label for="new_npc_skills">Skills & Abilities:</label>
                <small>Special talents, combat abilities, magical knowledge, and areas of expertise.</small>
                <textarea name="npc_skills" id="new_npc_skills" rows="3"></textarea>

                <label for="new_npc_speechstyle">Speech Style:</label>
                <small>How this character speaks, including vocabulary, accent, mannerisms, and communication patterns.</small>
                <textarea name="npc_speechstyle" id="new_npc_speechstyle" rows="3"></textarea>

                <label for="new_npc_goals">Goals & Aspirations:</label>
                <small>Long-term objectives, personal ambitions, and life goals</small>
                <textarea name="npc_goals" id="new_npc_goals" rows="3"></textarea>

                <!-- Voice & Meta Section -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Voice & Meta</h3>

                <label for="new_voiceid">Voice ID:</label>
                <small>Optional: Unified voice identifier.</small>
                <input type="text" name="voiceid" id="new_voiceid">

                <label for="new_gender">Gender:</label>
                <small>Optional: Gender for reference.</small>
                <input type="text" name="gender" id="new_gender">

                <label for="new_race">Race:</label>
                <small>Optional: Race for reference.</small>
                <input type="text" name="race" id="new_race">

                <label for="new_refid">RefID:</label>
                <small>Optional: In-game reference ID.</small>
                <input type="text" name="refid" id="new_refid">

                <div class="modal-footer">
                    <button type="submit" name="submit_individual" value="1" class="btn-save">Save</button>
                    <button type="button" onclick="closeNewEntryModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extended Profile View Modal -->
<div id="extendedProfileModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Extended Profiles: <span id="extended-profile-npc-name"></span></h2>
        </div>
        <div class="modal-body">
            <div class="extended-profile-grid" style="display: grid; gap: 20px;">
                
                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Static</h4>
                    <div id="profile-background" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Personality</h4>
                    <div id="profile-personality" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Appearance</h4>
                    <div id="profile-appearance" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Relationships</h4>
                    <div id="profile-relationships" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Occupation & Role</h4>
                    <div id="profile-occupation" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Skills & Abilities</h4>
                    <div id="profile-skills" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Speech Style</h4>
                    <div id="profile-speechstyle" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Goals & Aspirations</h4>
                    <div id="profile-goals" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeExtendedProfileModal()" class="btn-base btn-cancel">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Oghma Knowledge Modal -->
<div id="oghmaModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container" style="max-width: 1000px;">
        <div class="modal-header">
            <h2 class="modal-title">Oghma Knowledge: <span id="oghma-npc-name"></span></h2>
        </div>
        <div class="modal-body">
            <div id="oghma-loading" style="text-align: center; padding: 40px; display: none;">
                <p>Loading Oghma knowledge...</p>
            </div>
            <div id="oghma-content">
                <!-- Search and Filter Section -->
                <div class="oghma-filters" style="background: #2a2a2a; padding: 15px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #4a4a4a;">
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label for="oghma-search" style="display: block; margin-bottom: 5px; color: rgb(242, 124, 17); font-size: 0.9em; font-weight: bold;">Search Topics & Descriptions:</label>
                            <input 
                                type="text" 
                                id="oghma-search" 
                                placeholder="Search knowledge articles..." 
                                style="width: 100%; padding: 8px 12px; border: 1px solid #555; background: #1a1a1a; color: #f8f9fa; border-radius: 4px; font-size: 0.9em;"
                            >
                        </div>
                        <div style="min-width: 150px;">
                            <label for="oghma-category" style="display: block; margin-bottom: 5px; color: rgb(242, 124, 17); font-size: 0.9em; font-weight: bold;">Category:</label>
                            <select 
                                id="oghma-category" 
                                style="width: 100%; padding: 8px 12px; border: 1px solid #555; background: #1a1a1a; color: #f8f9fa; border-radius: 4px; font-size: 0.9em;"
                            >
                                <option value="">All Categories</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: end;">
                            <button 
                                id="oghma-apply-filters" 
                                style="padding: 8px 16px; background: rgb(242, 124, 17); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; font-weight: 500;"
                            >
                                Apply Filters
                            </button>
                            <button 
                                id="oghma-clear-filters" 
                                style="padding: 8px 16px; background: #555; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <div class="oghma-table-container" style="max-height: 50vh; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="position: sticky; top: 0; background: #3a3a3a; z-index: 10;">
                            <tr style="border-bottom: 2px solid rgb(242, 124, 17);">
                                <th style="padding: 12px 8px; text-align: left; color: rgb(242, 124, 17); width: 25%;">Topic</th>
                                <th style="padding: 12px 8px; text-align: left; color: rgb(242, 124, 17); width: 15%;">Knowledge Level</th>
                                <th style="padding: 12px 8px; text-align: left; color: rgb(242, 124, 17); width: 60%;">Description</th>
                            </tr>
                        </thead>
                        <tbody id="oghma-knowledge-list">
                            <!-- Content will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div id="oghma-no-access" style="text-align: center; padding: 40px; color: #888; display: none;">
                    <p>This NPC has no Oghma knowledge tags or no access to any knowledge articles.</p>
                    <p><small>Knowledge access is determined by the tags in the "Oghma Tags" field.</small></p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeOghmaModal()" class="btn-base btn-cancel">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showToast(message, duration = 5000) {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

function openEditModal(data) {
    try {
        const decodeHTML = (html) => {
            const txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_npc_name_original").value = decodeHTML(data.npc_name);
        document.getElementById("edit_npc_name").value = decodeHTML(data.npc_name);
        document.getElementById("edit_npc_pers").value = decodeHTML(data.npc_pers);
        const dynEl = document.getElementById("edit_npc_dynamic");
        if (dynEl) { dynEl.value = decodeHTML(data.npc_dynamic); }
        document.getElementById("edit_npc_misc").value = decodeHTML(data.npc_misc);
        
        // Extended profile fields
        document.getElementById("edit_npc_background").value = decodeHTML(data.npc_background || '');
        document.getElementById("edit_npc_personality").value = decodeHTML(data.npc_personality || '');
        document.getElementById("edit_npc_appearance").value = decodeHTML(data.npc_appearance || '');
        document.getElementById("edit_npc_relationships").value = decodeHTML(data.npc_relationships || '');
        document.getElementById("edit_npc_occupation").value = decodeHTML(data.npc_occupation || '');
        document.getElementById("edit_npc_skills").value = decodeHTML(data.npc_skills || '');
        document.getElementById("edit_npc_speechstyle").value = decodeHTML(data.npc_speechstyle || '');
        document.getElementById("edit_npc_goals").value = decodeHTML(data.npc_goals || '');
        
        // Voice & Meta
        const vEl = document.getElementById("edit_voiceid"); if (vEl) vEl.value = decodeHTML(data.voiceid || '');
        const gEl = document.getElementById("edit_gender"); if (gEl) gEl.value = decodeHTML(data.gender || '');
        const rEl = document.getElementById("edit_race"); if (rEl) rEl.value = decodeHTML(data.race || '');
        const refEl = document.getElementById("edit_refid"); if (refEl) refEl.value = decodeHTML(data.refid || '');
        
        document.getElementById("editModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error("Error in openEditModal:", error);
        alert("There was an error opening the edit form. Please try again.");
    }
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function openNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function showExtendedProfile(npcName, profileData) {
    try {
        // Set the NPC name in the modal title
        document.getElementById("extended-profile-npc-name").textContent = npcName;
        
        // Populate each field, showing "Not specified" for empty fields
        const fields = {
            'Static': 'profile-background',
            'Personality': 'profile-personality', 
            'Appearance': 'profile-appearance',
            'Relationships': 'profile-relationships',
            'Occupation': 'profile-occupation',
            'Skills': 'profile-skills',
            'Speech Style': 'profile-speechstyle',
            'Goals': 'profile-goals'
        };
        
        Object.keys(fields).forEach(fieldName => {
            const elementId = fields[fieldName];
            const content = profileData[fieldName] || '';
            const element = document.getElementById(elementId);
            
            if (content.trim()) {
                element.textContent = content;
                element.style.color = '#d4d4d4';
                element.style.fontStyle = 'normal';
            } else {
                element.textContent = 'Not specified';
                element.style.color = '#888';
                element.style.fontStyle = 'italic';
            }
        });
        
        // Show the modal
        document.getElementById("extendedProfileModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error("Error in showExtendedProfile:", error);
        alert("There was an error displaying the extended profile. Please try again.");
    }
}

function closeExtendedProfileModal() {
    document.getElementById("extendedProfileModal").style.display = "none";
    document.body.style.overflow = "auto";
}

// Global variables to store the current Oghma data
let currentOghmaData = null;
let currentNpcName = '';
let currentOghmaTags = '';

function openOghmaModal(npcName, oghmaTagsString) {
    try {
        // Store current NPC info for filtering
        currentNpcName = npcName;
        currentOghmaTags = oghmaTagsString;
        
        // Set the NPC name in the modal title
        document.getElementById("oghma-npc-name").textContent = npcName;
        
        // Clear filters
        document.getElementById("oghma-search").value = '';
        document.getElementById("oghma-category").value = '';
        
        // Show loading state
        document.getElementById("oghma-loading").style.display = "block";
        document.getElementById("oghma-content").style.display = "none";
        
        // Show the modal
        document.getElementById("oghmaModal").style.display = "block";
        document.body.style.overflow = "hidden";
        
        // Load initial data
        loadOghmaData();
        
    } catch (error) {
        console.error("Error in openOghmaModal:", error);
        alert("There was an error opening the Oghma knowledge viewer. Please try again.");
    }
}

function loadOghmaData(searchTerm = '', categoryFilter = '') {
    // Fetch Oghma knowledge data
    const formData = new URLSearchParams();
    formData.append('npc_name', currentNpcName);
    formData.append('oghma_tags', currentOghmaTags);
    if (searchTerm) formData.append('search', searchTerm);
    if (categoryFilter) formData.append('category', categoryFilter);
    
    fetch('oghma_knowledge.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        // Store the data globally
        currentOghmaData = data;
        
        // Hide loading state
        document.getElementById("oghma-loading").style.display = "none";
        document.getElementById("oghma-content").style.display = "block";
        
        // Populate category dropdown if it's the first load
        if (!searchTerm && !categoryFilter) {
            populateCategoryDropdown(data.categories || []);
        }
        
        // Populate the knowledge table
        populateKnowledgeTable(data.knowledge || []);
        
    })
    .catch(error => {
        console.error('Error fetching Oghma knowledge:', error);
        document.getElementById("oghma-loading").style.display = "none";
        document.getElementById("oghma-content").style.display = "block";
        document.getElementById("oghma-no-access").style.display = "block";
        document.getElementById("oghma-no-access").innerHTML = '<p style="color: #ff6464;">Error loading Oghma knowledge. Please try again.</p>';
    });
}

function populateCategoryDropdown(categories) {
    const categorySelect = document.getElementById("oghma-category");
    
    // Clear existing options except "All Categories"
    while (categorySelect.children.length > 1) {
        categorySelect.removeChild(categorySelect.lastChild);
    }
    
    // Add category options
    categories.forEach(category => {
        if (category && category.trim()) {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        }
    });
}

function populateKnowledgeTable(knowledge) {
    const knowledgeList = document.getElementById("oghma-knowledge-list");
    knowledgeList.innerHTML = '';
    
    if (knowledge && knowledge.length > 0) {
        knowledge.forEach(item => {
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid #4a4a4a';
            
            const levelColor = item.level === 'Advanced' ? 'rgb(242, 124, 17)' : '#4a9eff';
            const levelBg = item.level === 'Advanced' ? 'rgba(242, 124, 17, 0.2)' : 'rgba(74, 158, 255, 0.2)';
            
            // Add category, knowledge classes, and tags info if available
            let extraInfo = '';
            if (item.category || item.knowledge_class || item.knowledge_class_basic || item.tags) {
                extraInfo = '<div style="margin-top: 8px; font-size: 0.8em; color: #888;">';
                
                // Category
                if (item.category) {
                    extraInfo += `<span style="background: rgba(242, 124, 17, 0.15); color: rgb(242, 124, 17); padding: 2px 6px; border-radius: 3px; margin-right: 5px; font-size: 1.5em;"><span aria-hidden="true">&#128193;</span> ${item.category}</span>`;
                }
                
                // Knowledge Class (Advanced) - Orange tags
                if (item.knowledge_class) {
                    const knowledgeClasses = item.knowledge_class.split(',').map(tag => tag.trim()).filter(tag => tag);
                    knowledgeClasses.forEach(knowledgeClass => {
                        extraInfo += `<span style="background: rgba(242, 124, 17, 0.2); color: rgb(242, 124, 17); padding: 2px 6px; border-radius: 3px; margin-right: 3px; font-size: 1.5em; font-weight: 500;"><span aria-hidden="true">&#128312;</span> ${knowledgeClass}</span>`;
                    });
                }
                
                // Knowledge Class Basic - Orange tags with different opacity
                if (item.knowledge_class_basic) {
                    const basicClasses = item.knowledge_class_basic.split(',').map(tag => tag.trim()).filter(tag => tag);
                    basicClasses.forEach(basicClass => {
                        extraInfo += `<span style="background: rgba(242, 124, 17, 0.15); color: rgb(242, 124, 17); padding: 2px 6px; border-radius: 3px; margin-right: 3px; font-size: 1.5em;"><span aria-hidden="true">&#128313;</span> ${basicClass}</span>`;
                    });
                }
                
                // Tags - Blue tags
                if (item.tags) {
                    const tags = item.tags.split(',').map(tag => tag.trim()).filter(tag => tag);
                    tags.forEach(tag => {
                        extraInfo += `<span style="background: rgba(74, 158, 255, 0.15); color: #4a9eff; padding: 2px 6px; border-radius: 3px; margin-right: 3px; font-size: 0.75em;"><span aria-hidden="true">&#127991;&#65039;</span> ${tag}</span>`;
                    });
                }
                
                extraInfo += '</div>';
            }
            
            row.innerHTML = `
                <td style="padding: 12px 8px; vertical-align: top; word-wrap: break-word;">
                    <strong>${item.topic}</strong>
                    ${extraInfo}
                </td>
                <td style="padding: 12px 8px; vertical-align: top;">
                    <span style="display: inline-block; background: ${levelBg}; color: ${levelColor}; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 500;">${item.level}</span>
                </td>
                <td style="padding: 12px 8px; vertical-align: top; word-wrap: break-word; line-height: 1.4;">
                    ${item.description}
                </td>
            `;
            
            knowledgeList.appendChild(row);
        });
        
        document.getElementById("oghma-no-access").style.display = "none";
    } else {
        document.getElementById("oghma-no-access").style.display = "block";
        document.getElementById("oghma-no-access").innerHTML = `
            <p>No knowledge articles found matching the current filters.</p>
            <p><small>Try adjusting your search terms or category filter.</small></p>
        `;
    }
}

function closeOghmaModal() {
    document.getElementById("oghmaModal").style.display = "none";
    document.body.style.overflow = "auto";
}

// Add event listeners for Oghma filters when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Apply Filters button
    const applyFiltersBtn = document.getElementById('oghma-apply-filters');
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', function() {
            const searchTerm = document.getElementById('oghma-search').value.trim();
            const categoryFilter = document.getElementById('oghma-category').value;
            
            // Show loading state
            document.getElementById("oghma-loading").style.display = "block";
            document.getElementById("oghma-content").style.display = "none";
            
            // Load filtered data
            loadOghmaData(searchTerm, categoryFilter);
        });
    }
    
    // Clear Filters button
    const clearFiltersBtn = document.getElementById('oghma-clear-filters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            document.getElementById('oghma-search').value = '';
            document.getElementById('oghma-category').value = '';
            
            // Show loading state
            document.getElementById("oghma-loading").style.display = "block";
            document.getElementById("oghma-content").style.display = "none";
            
            // Load all data
            loadOghmaData();
        });
    }
    
    // Enter key support for search box
    const searchBox = document.getElementById('oghma-search');
    if (searchBox) {
        searchBox.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('oghma-apply-filters').click();
            }
        });
    }
});

// Show toast notification for CSV uploads
<?php if (!empty($toastMessage)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode($toastMessage); ?>);
});
<?php endif; ?>

// Add new AJAX filtering function
function filterByLetter(letter) {
    fetch(`npc_table.php?letter=${letter}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('npc-table-container').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading data. Please try again.');
        });
}

// Replace the existing applySearch function with this updated version
function applySearch() {
    const searchTerm = document.getElementById("searchBox").value.trim();
    const currentUrl = new URL(window.location.href);
    const urlParams = new URLSearchParams(currentUrl.search);
    
    // Update or add search parameter
    if (searchTerm) {
        urlParams.set("search", searchTerm);
    } else {
        urlParams.delete("search");
    }
    
    // Preserve existing parameters if they exist
    const currentLetter = urlParams.get("letter");
    if (currentLetter) {
        urlParams.set("letter", currentLetter);
    }
    
    // Create the new URL with the base path and updated parameters
    const newUrl = `${window.location.pathname}?${urlParams.toString()}#table`;
    window.location.href = newUrl;
}

// Add enter key support for the search box
document.getElementById("searchBox").addEventListener("keypress", function(e) {
    if (e.key === "Enter") {
        e.preventDefault();
        applySearch();
    }
});

// Set initial search box value from URL
window.addEventListener("load", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get("search");
    if (searchTerm) {
        document.getElementById("searchBox").value = decodeURIComponent(searchTerm);
    }
});

// Close modals when clicking outside of them
window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const newEntryModal = document.getElementById('newEntryModal');
    const extendedProfileModal = document.getElementById('extendedProfileModal');
    const oghmaModal = document.getElementById('oghmaModal');
    
    if (event.target == editModal) {
        closeEditModal();
    } else if (event.target == newEntryModal) {
        closeNewEntryModal();
    } else if (event.target == extendedProfileModal) {
        closeExtendedProfileModal();
    } else if (event.target == oghmaModal) {
        closeOghmaModal();
    }
}
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
