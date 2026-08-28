<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oghma_aliases.php');
require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oghma_catalog.php');

$TITLE = "&#x1F4D9; CHIM - Oghma Infinium";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$enginePath = dirname($rootPath) . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';


// Initialize message variable
$message = '';
if (isset($_GET['message']) && is_string($_GET['message']) && trim($_GET['message']) !== '') {
    $message .= '<p>' . htmlspecialchars($_GET['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
}

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}
$catalogManager = new ChimOghmaCatalogManager($GLOBALS['db'], dirname(__DIR__));

function oghma_filter_alias_input($conn, string $topic, string $aliases): array
{
    static $canonicalOwners = null;
    static $aliasOwners = null;
    if ($canonicalOwners === null || $aliasOwners === null) {
        $result = pg_query($conn, "SELECT topic, coalesce(aliases, '') AS aliases FROM public.oghma");
        $rows = [];
        if ($result) {
            while ($row = pg_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        [$canonicalOwners, $aliasOwners] = chimOghmaBuildAliasOwnerMaps($rows);
    }

    $filtered = chimOghmaFilterAliases($topic, $aliases, $canonicalOwners, $aliasOwners);
    $canonicalOwners[chimOghmaComparableAliasKey($topic)] = $topic;
    foreach (chimOghmaSplitAliases($filtered['aliases']) as $alias) {
        $aliasOwners[chimOghmaComparableAliasKey($alias)][$topic] = true;
    }
    return $filtered;
}

function oghma_row_checksum(array $row): string
{
    $ordered = [];
    foreach (['topic', 'aliases', 'topic_desc', 'knowledge_class', 'topic_desc_basic', 'knowledge_class_basic', 'tags', 'category'] as $field) {
        $ordered[$field] = (string) ($row[$field] ?? '');
    }
    return hash('sha256', json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/********************************************************************
 *  1) SINGLE TOPIC UPLOAD
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    // Collect and sanitize form inputs
    $topic                = htmlspecialchars($_POST['topic']                ?? '');
    $aliases              = trim((string) ($_POST['aliases']                ?? ''));
    $topic_desc           = htmlspecialchars($_POST['topic_desc']           ?? '');
    $knowledge_class      = htmlspecialchars($_POST['knowledge_class']      ?? '');
    $topic_desc_basic     = htmlspecialchars($_POST['topic_desc_basic']     ?? '');
    $knowledge_class_basic= htmlspecialchars($_POST['knowledge_class_basic']?? '');
    $tags                 = htmlspecialchars($_POST['tags']                 ?? '');
    $category             = htmlspecialchars($_POST['category']             ?? '');
    $filteredAliases      = oghma_filter_alias_input($conn, $topic, $aliases);
    $aliases              = $filteredAliases['aliases'];
    foreach ($filteredAliases['rejected'] as $rejectedAlias) {
        $message .= '<p>Alias skipped: ' . htmlspecialchars($rejectedAlias['alias'])
            . ' (' . htmlspecialchars($rejectedAlias['reason']) . ')</p>';
    }

    if (!empty($topic) && !empty($topic_desc)) {
        $query = "
            INSERT INTO $schema.oghma (
                topic,
                aliases,
                topic_desc, 
                knowledge_class, 
                topic_desc_basic, 
                knowledge_class_basic, 
                tags, 
                category,
                source_type,
                source_catalog_version,
                source_checksum,
                updated_at
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, 'custom', NULL, $9, CURRENT_TIMESTAMP)
            ON CONFLICT (topic)
            DO UPDATE SET
                aliases              = EXCLUDED.aliases,
                topic_desc           = EXCLUDED.topic_desc,
                knowledge_class      = EXCLUDED.knowledge_class,
                topic_desc_basic     = EXCLUDED.topic_desc_basic,
                knowledge_class_basic= EXCLUDED.knowledge_class_basic,
                tags                 = EXCLUDED.tags,
                category             = EXCLUDED.category,
                source_type          = 'custom',
                source_catalog_version = NULL,
                source_checksum      = EXCLUDED.source_checksum,
                updated_at           = CURRENT_TIMESTAMP
        ";
        $checksum = oghma_row_checksum(compact(
            'topic', 'aliases', 'topic_desc', 'knowledge_class', 'topic_desc_basic',
            'knowledge_class_basic', 'tags', 'category'
        ));
        $result = pg_query_params($conn, $query, [
            $topic,
            $aliases,
            $topic_desc,
            $knowledge_class,
            $topic_desc_basic,
            $knowledge_class_basic,
            $tags,
            $category,
            $checksum
        ]);

        if ($result) {
            $message .= "<p>Data inserted/updated successfully!</p>";

            // Update native_vector
            $update_query = "
                UPDATE $schema.oghma
                SET native_vector = 
                      setweight(to_tsvector('simple', coalesce(topic, '')), 'A')
                    || setweight(to_tsvector('simple', coalesce(aliases, '')), 'A')
                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                WHERE topic = $1
            ";
            $update_result = pg_query_params($conn, $update_query, [$topic]);

            if ($update_result) {
                $message .= "<p>Vectors updated successfully.</p>";
            } else {
                $message .= "<p>Error updating vectors: " . pg_last_error($conn) . "</p>";
            }
        } else {
            $message .= "<p>An error occurred while inserting/updating data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Please fill in at least the "topic" and "topic_desc" fields.</p>';
    }
}

/********************************************************************
 *  2) CSV UPLOAD (BATCH)
 ********************************************************************/
function oghma_normalize_csv_header($value) {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value);
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}

function oghma_csv_value($row, $headerMap, $name, $fallbackIndex = null) {
    if (isset($headerMap[$name])) {
        return $row[$headerMap[$name]] ?? '';
    }
    if ($fallbackIndex !== null) {
        return $row[$fallbackIndex] ?? '';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName    = $_FILES['csv_file']['name'];

        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                $header = fgetcsv($handle, 0, ',');
                if ($header === false) {
                    $message .= '<p>CSV file is empty.</p>';
                    $header = [];
                }

                $headerMap = [];
                foreach ($header as $index => $columnName) {
                    $normalized = oghma_normalize_csv_header($columnName);
                    if ($normalized !== '' && !isset($headerMap[$normalized])) {
                        $headerMap[$normalized] = $index;
                    }
                }

                $hasNamedColumns = isset($headerMap['topic']);
                $hasAliasesColumn = isset($headerMap['aliases']);
                $requiredColumns = ['topic', 'topic_desc', 'knowledge_class', 'topic_desc_basic', 'knowledge_class_basic'];
                $missingColumns = array_values(array_filter($requiredColumns, static function ($column) use ($headerMap) {
                    return !isset($headerMap[$column]);
                }));
                if ($hasNamedColumns && !empty($missingColumns)) {
                    $message .= '<p>CSV warning: Missing expected header(s): ' . htmlspecialchars(implode(', ', $missingColumns)) . '. Missing optional fields will import as blank.</p>';
                }

                $rowCount = 0;
                $skippedCount = 0;
                while (($data = fgetcsv($handle, 0, ',')) !== false) {
                    if (count(array_filter($data, static function ($value) { return trim((string)$value) !== ''; })) === 0) {
                        continue;
                    }

                    $topic                = strtolower(trim(oghma_csv_value($data, $headerMap, 'topic', 0)));
                    $topic_desc           = oghma_csv_value($data, $headerMap, 'topic_desc', 1);
                    $knowledge_class      = oghma_csv_value($data, $headerMap, 'knowledge_class', 2);
                    $topic_desc_basic     = oghma_csv_value($data, $headerMap, 'topic_desc_basic', 3);
                    $knowledge_class_basic= oghma_csv_value($data, $headerMap, 'knowledge_class_basic', 4);
                    $tags                 = oghma_csv_value($data, $headerMap, 'tags', 5);
                    $category             = oghma_csv_value($data, $headerMap, 'category', 6);
                    $aliases              = $hasAliasesColumn
                        ? oghma_csv_value($data, $headerMap, 'aliases')
                        : '';
                    if ($hasAliasesColumn) {
                        $filteredAliases = oghma_filter_alias_input($conn, $topic, $aliases);
                        $aliases = $filteredAliases['aliases'];
                    }

                    if (!empty($topic) && !empty($topic_desc)) {
                        $query = "
                            INSERT INTO $schema.oghma (
                                topic,
                                aliases,
                                topic_desc,
                                knowledge_class,
                                topic_desc_basic,
                                knowledge_class_basic,
                                tags,
                                category,
                                source_type,
                                source_catalog_version,
                                source_checksum,
                                updated_at
                            )
                            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, 'custom', NULL, $10, CURRENT_TIMESTAMP)
                            ON CONFLICT (topic)
                            DO UPDATE SET
                                aliases              = CASE
                                    WHEN $9::boolean THEN EXCLUDED.aliases
                                    ELSE oghma.aliases
                                END,
                                topic_desc           = EXCLUDED.topic_desc,
                                knowledge_class      = EXCLUDED.knowledge_class,
                                topic_desc_basic     = EXCLUDED.topic_desc_basic,
                                knowledge_class_basic= EXCLUDED.knowledge_class_basic,
                                tags                 = EXCLUDED.tags,
                                category             = EXCLUDED.category,
                                source_type          = 'custom',
                                source_catalog_version = NULL,
                                source_checksum      = EXCLUDED.source_checksum,
                                updated_at           = CURRENT_TIMESTAMP
                        ";
                        $checksum = oghma_row_checksum(compact(
                            'topic', 'aliases', 'topic_desc', 'knowledge_class', 'topic_desc_basic',
                            'knowledge_class_basic', 'tags', 'category'
                        ));
                        $result = pg_query_params($conn, $query, [
                            $topic,
                            $aliases,
                            $topic_desc,
                            $knowledge_class,
                            $topic_desc_basic,
                            $knowledge_class_basic,
                            $tags,
                            $category,
                            $hasAliasesColumn ? 'true' : 'false',
                            $checksum
                        ]);

                        if ($result) {
                            $rowCount++;
                            // Update the native_vector for this single row
                            $update_query = "
                                UPDATE $schema.oghma
                                SET native_vector = 
                                      setweight(to_tsvector('simple', coalesce(topic, '')), 'A')
                                    || setweight(to_tsvector('simple', coalesce(aliases, '')), 'A')
                                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                                WHERE topic = $1
                            ";
                            pg_query_params($conn, $update_query, [$topic]);
                        } else {
                            $message .= "<p>Error processing row with topic '$topic': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $skippedCount++;
                    }
                }
                fclose($handle);

                $message .= "<p>$rowCount records inserted/updated successfully from the CSV file.</p>";
                if ($skippedCount > 0) {
                    $message .= "<p>$skippedCount row(s) skipped because topic or topic_desc was missing.</p>";
                }
            } else {
                $message .= '<p>Error opening the CSV file.</p>';
            }
        } else {
            $message .= '<p>Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions) . '</p>';
        }
    } else {
        $message .= '<p>No file uploaded or there was an upload error.</p>';
    }
}

/********************************************************************
 *  3) DOWNLOAD EXAMPLE CSV
 ********************************************************************/
if (isset($_GET['action']) && $_GET['action'] === 'download_example') {
    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'oghma_example.csv');
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="oghma_example.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        if (ob_get_length()) ob_end_clean();
        flush();
        readfile($filePath);
        exit;
    } else {
        $message .= '<p>Example CSV file not found.</p>';
    }
}

/********************************************************************
 *  3.5) DYNAMIC CSV UPLOAD AND EXAMPLE
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dynamic_csv'])) {
    if (isset($_FILES['dynamic_csv_file']) && $_FILES['dynamic_csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['dynamic_csv_file']['tmp_name'];
        $fileName    = $_FILES['dynamic_csv_file']['name'];

        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                // Skip header row
                fgetcsv($handle, 1000, ',');

                $rowCount = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $id_quest             = trim($data[0] ?? '');
                    $stage                = intval($data[1] ?? 0);
                    $topic                = strtolower(trim($data[2] ?? ''));
                    $topic_desc           = $data[3] ?? '';
                    $knowledge_class      = $data[4] ?? '';
                    $topic_desc_basic     = $data[5] ?? '';
                    $knowledge_class_basic= $data[6] ?? '';
                    $tags                 = $data[7] ?? '';
                    $category             = $data[8] ?? '';

                    if (!empty($id_quest) && !empty($topic)) {
                        // Check if record with same id_quest, stage, and topic already exists
                        $checkQuery = "
                            SELECT id FROM $schema.oghma_dynamic 
                            WHERE id_quest = $1 AND stage = $2 AND topic = $3
                        ";
                        $checkResult = pg_query_params($conn, $checkQuery, [$id_quest, $stage, $topic]);
                        
                        if ($checkResult && pg_num_rows($checkResult) > 0) {
                            // Update existing record
                            $existingRow = pg_fetch_assoc($checkResult);
                            $updateQuery = "
                                UPDATE $schema.oghma_dynamic 
                                SET topic_desc = $1,
                                    knowledge_class = $2,
                                    topic_desc_basic = $3,
                                    knowledge_class_basic = $4,
                                    tags = $5,
                                    category = $6
                                WHERE id = $7
                            ";
                            
                            $result = pg_query_params($conn, $updateQuery, [
                                $topic_desc,
                                $knowledge_class,
                                $topic_desc_basic,
                                $knowledge_class_basic,
                                $tags,
                                $category,
                                $existingRow['id']
                            ]);
                        } else {
                            // Insert new record
                            $query = "
                                INSERT INTO $schema.oghma_dynamic (
                                    id_quest,
                                    stage,
                                    topic,
                                    topic_desc,
                                    knowledge_class,
                                    topic_desc_basic,
                                    knowledge_class_basic,
                                    tags,
                                    category
                                )
                                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
                            ";
                            
                            $result = pg_query_params($conn, $query, [
                                $id_quest,
                                $stage,
                                $topic,
                                $topic_desc,
                                $knowledge_class,
                                $topic_desc_basic,
                                $knowledge_class_basic,
                                $tags,
                                $category
                            ]);
                        }

                        if ($result) {
                            $rowCount++;
                        } else {
                            $message .= "<p>Error processing row with quest ID '$id_quest': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $message .= "<p>Skipping empty or invalid row (Quest ID/Topic missing).</p>";
                    }
                }
                fclose($handle);

                $message .= "<p>$rowCount dynamic entries inserted successfully from the CSV file.</p>";
            } else {
                $message .= '<p>Error opening the CSV file.</p>';
            }
        } else {
            $message .= '<p>Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions) . '</p>';
        }
    } else {
        $message .= '<p>No file uploaded or there was an upload error.</p>';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'download_dynamic_example') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="oghma_dynamic_example.csv"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Example content with header and two sample entries
    echo "id_quest,stage,topic,topic_desc,knowledge_class,topic_desc_basic,knowledge_class_basic,tags,category\n";
    echo "TutorialBlacksmithing,1,blacksmithing,The art of blacksmithing involves crafting weapons and armor at a forge.,blacksmith;craftsman,Basic knowledge about forging metal items.,,,Skills\n";
    echo "MQ101,10,helgen_attack,A dragon attacked the town of Helgen during an Imperial execution.,guard;soldier,A dragon destroyed Helgen.,,,Events\n";
    exit;
}

/********************************************************************
 *  4) DELETE ALL
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    try {
        $deleteResult = $catalogManager->deleteAllCustomOverrides();
        $message .= "<p style='color: #ff6464; font-weight: bold;'>Deleted "
            . intval($deleteResult['deleted'] ?? 0) . ' custom Oghma entries and restored '
            . intval($deleteResult['factory_restored'] ?? 0) . ' factory articles.</p>';
    } catch (Throwable $error) {
        $message .= '<p>Error deleting entries: ' . htmlspecialchars($error->getMessage()) . '</p>';
    }
}

/********************************************************************
 *  4.5) DELETE SINGLE TOPIC
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single') {
    $topic = $_POST['topic'] ?? '';
    
    if (!empty($topic)) {
        try {
            $sourceResult = pg_query_params(
                $conn,
                "SELECT source_type FROM {$schema}.oghma WHERE topic = $1",
                [$topic]
            );
            $sourceRow = $sourceResult ? pg_fetch_assoc($sourceResult) : false;
            if (!$sourceRow) throw new InvalidArgumentException('Oghma entry not found.');

            if (($sourceRow['source_type'] ?? '') === 'custom') {
                $deleteResult = $catalogManager->deleteCustomOverride((string) $topic);
                $message .= intval($deleteResult['factory_restored'] ?? 0) > 0
                    ? "<p>Custom override '$topic' was deleted and the factory article was restored.</p>"
                    : "<p>Custom entry '$topic' was deleted.</p>";
            } else {
                pg_query($conn, 'BEGIN');
                $hideQuery = "
                    INSERT INTO {$schema}.oghma_factory_overrides (topic, action, updated_at)
                    SELECT topic, 'hide', CURRENT_TIMESTAMP FROM {$schema}.oghma
                    WHERE topic = $1 AND source_type = 'factory'
                    ON CONFLICT (topic) DO UPDATE SET action = 'hide', updated_at = CURRENT_TIMESTAMP
                ";
                $hideResult = pg_query_params($conn, $hideQuery, [$topic]);
                $deleteQuery = "DELETE FROM {$schema}.oghma WHERE topic = $1 AND source_type = 'factory'";
                $deleteResult = $hideResult ? pg_query_params($conn, $deleteQuery, [$topic]) : false;
                if (!$deleteResult || pg_affected_rows($deleteResult) !== 1) {
                    throw new RuntimeException(pg_last_error($conn) ?: 'Factory article could not be hidden.');
                }
                pg_query($conn, 'COMMIT');
                $message .= "<p>Factory entry '$topic' has been hidden.</p>";
            }
            
            // Redirect to maintain filters
            $redirectUrl = '?' . http_build_query([
                'cat' => $_GET['cat'] ?? '',
                'letter' => $_GET['letter'] ?? '',
                'order' => $_GET['order'] ?? 'asc'
            ]) . '#entries';
            header('Location: ' . $redirectUrl);
            exit;
        } catch (Throwable $error) {
            @pg_query($conn, 'ROLLBACK');
            $message .= '<p>Error deleting entry: ' . htmlspecialchars($error->getMessage()) . '</p>';
        }
    } else {
        $message .= "<p>No topic specified for deletion.</p>";
    }
}

/********************************************************************
 * (A) UPDATE SINGLE ROW (SAVE after Edit)
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_single') {
    // Sanitize and read posted fields - use htmlspecialchars_decode to convert HTML entities back
    $topic_original       = $_POST['topic_original'] ?? '';
    $topic_new           = htmlspecialchars_decode($_POST['topic_new'] ?? '');
    $aliases_new         = htmlspecialchars_decode($_POST['aliases_new'] ?? '');
    $topic_desc_new      = htmlspecialchars_decode($_POST['topic_desc_new'] ?? '');
    $knowledge_class_new = htmlspecialchars_decode($_POST['knowledge_class_new'] ?? '');
    $topic_desc_basic_new = htmlspecialchars_decode($_POST['topic_desc_basic_new'] ?? '');
    $knowledge_class_basic_new = htmlspecialchars_decode($_POST['knowledge_class_basic_new'] ?? '');
    $tags_new            = htmlspecialchars_decode($_POST['tags_new'] ?? '');
    $category_new        = htmlspecialchars_decode($_POST['category_new'] ?? '');
    if (!empty($topic_original) && !empty($topic_new) && !empty($topic_desc_new)) {
        pg_query($conn, 'BEGIN');
        $sourceQuery = "
            SELECT current_row.source_type,
                   EXISTS (
                       SELECT 1
                       FROM {$schema}.oghma_catalog_entries entry
                       JOIN {$schema}.oghma_catalogs catalog
                         ON catalog.catalog_version = entry.catalog_version
                        AND catalog.state = 'active'
                       WHERE entry.topic = current_row.topic
                   ) AS factory_origin
            FROM {$schema}.oghma current_row
            WHERE current_row.topic = $1
            FOR UPDATE
        ";
        $sourceResult = pg_query_params($conn, $sourceQuery, [$topic_original]);
        $sourceRow = $sourceResult ? pg_fetch_assoc($sourceResult) : false;
        if (!$sourceRow) {
            pg_query($conn, 'ROLLBACK');
            $message .= '<p>The Oghma entry no longer exists. Refresh and try again.</p>';
        } else {
            // Catalog-backed topics remain canonical so the custom row always overrides its factory source.
            if (($sourceRow['factory_origin'] ?? 'f') === 't') $topic_new = $topic_original;
            $filteredAliases = oghma_filter_alias_input($conn, $topic_new, $aliases_new);
            $aliases_new = $filteredAliases['aliases'];
            foreach ($filteredAliases['rejected'] as $rejectedAlias) {
                $message .= '<p>Alias skipped: ' . htmlspecialchars($rejectedAlias['alias'])
                    . ' (' . htmlspecialchars($rejectedAlias['reason']) . ')</p>';
            }

            $checksum = oghma_row_checksum([
                'topic' => $topic_new,
                'aliases' => $aliases_new,
                'topic_desc' => $topic_desc_new,
                'knowledge_class' => $knowledge_class_new,
                'topic_desc_basic' => $topic_desc_basic_new,
                'knowledge_class_basic' => $knowledge_class_basic_new,
                'tags' => $tags_new,
                'category' => $category_new,
            ]);

            // The effective row becomes custom while the immutable factory source remains in the active catalog.
            $updateSql = "
                UPDATE $schema.oghma
                SET topic = $1,
                    aliases = $2,
                    topic_desc = $3,
                    knowledge_class = $4,
                    topic_desc_basic = $5,
                    knowledge_class_basic = $6,
                    tags = $7,
                    category = $8,
                    source_type = 'custom',
                    source_catalog_version = NULL,
                    source_checksum = $10,
                    updated_at = CURRENT_TIMESTAMP
                WHERE topic = $9
            ";
            $updateResult = pg_query_params($conn, $updateSql, [
                $topic_new,
                $aliases_new,
                $topic_desc_new,
                $knowledge_class_new,
                $topic_desc_basic_new,
                $knowledge_class_basic_new,
                $tags_new,
                $category_new,
                $topic_original,
                $checksum,
            ]);

            $vectorSql = "
                UPDATE $schema.oghma
                SET native_vector =
                      setweight(to_tsvector('simple', coalesce(topic, '')), 'A')
                    || setweight(to_tsvector('simple', coalesce(aliases, '')), 'A')
                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                WHERE topic = $1
            ";
            $vectorResult = $updateResult && pg_affected_rows($updateResult) === 1
                ? pg_query_params($conn, $vectorSql, [$topic_new])
                : false;

            if ($vectorResult) {
                pg_query($conn, 'COMMIT');
                $redirectUrl = '?' . http_build_query([
                    'cat' => $_GET['cat'] ?? '',
                    'letter' => $_GET['letter'] ?? '',
                    'order' => $_GET['order'] ?? 'asc'
                ]) . '#entries';
                header('Location: ' . $redirectUrl);
                exit;
            }

            pg_query($conn, 'ROLLBACK');
            $message .= '<p>Error updating row: ' . htmlspecialchars(pg_last_error($conn)) . '</p>';
        }
    } else {
        $message .= '<p>Topic and Topic Description cannot be empty when saving.</p>';
    }
}

/********************************************************************
 *  ADD NEW DYNAMIC ENTRY
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dynamic'])) {
    // Collect and sanitize form inputs
    $id_quest             = htmlspecialchars($_POST['id_quest']             ?? '');
    $stage                = intval($_POST['stage']                          ?? 0);
    $topic                = htmlspecialchars($_POST['dynamic_topic']        ?? '');
    $topic_desc           = htmlspecialchars($_POST['dynamic_topic_desc']   ?? '');
    $knowledge_class      = htmlspecialchars($_POST['dynamic_knowledge_class']      ?? '');
    $topic_desc_basic     = htmlspecialchars($_POST['dynamic_topic_desc_basic']     ?? '');
    $knowledge_class_basic= htmlspecialchars($_POST['dynamic_knowledge_class_basic']?? '');
    $tags                 = htmlspecialchars($_POST['dynamic_tags']                 ?? '');
    $category             = htmlspecialchars($_POST['dynamic_category']             ?? '');

    if (!empty($id_quest) && !empty($topic)) {
        // Check if record with same id_quest, stage, and topic already exists
        $checkQuery = "
            SELECT id FROM $schema.oghma_dynamic 
            WHERE id_quest = $1 AND stage = $2 AND topic = $3
        ";
        $checkResult = pg_query_params($conn, $checkQuery, [$id_quest, $stage, $topic]);
        
        if ($checkResult && pg_num_rows($checkResult) > 0) {
            $message .= "<p style='color: orange;'>A dynamic entry with Quest ID '$id_quest', Stage '$stage', and Topic '$topic' already exists. Please edit the existing entry or use different values.</p>";
        } else {
            // Insert new record
            $query = "
                INSERT INTO $schema.oghma_dynamic (
                    id_quest,
                    stage,
                    topic,
                    topic_desc,
                    knowledge_class,
                    topic_desc_basic,
                    knowledge_class_basic,
                    tags,
                    category
                )
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
            ";
            
            $result = pg_query_params($conn, $query, [
                $id_quest,
                $stage,
                $topic,
                $topic_desc,
                $knowledge_class,
                $topic_desc_basic,
                $knowledge_class_basic,
                $tags,
                $category
            ]);

            if ($result) {
                $message .= "<p>Dynamic entry added successfully!</p>";
            } else {
                $message .= "<p>Error adding dynamic entry: " . pg_last_error($conn) . "</p>";
            }
        }
    } else {
        $message .= '<p>Please fill in at least the "Quest ID" and "Topic" fields.</p>';
    }
}

/********************************************************************
 *  DELETE DYNAMIC ENTRY
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dynamic') {
    $id = intval($_POST['dynamic_id'] ?? 0);
    
    if ($id > 0) {
        $query = "DELETE FROM {$schema}.oghma_dynamic WHERE id = $1";
        $result = pg_query_params($conn, $query, [$id]);

        if ($result) {
            $message .= "<p>Dynamic entry has been deleted successfully.</p>";
        } else {
            $message .= "<p>Error deleting dynamic entry: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Invalid dynamic entry ID specified for deletion.</p>";
    }
}

/********************************************************************
 *  UPDATE DYNAMIC ENTRY
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_dynamic') {
    $id = intval($_POST['dynamic_id'] ?? 0);
    $id_quest = htmlspecialchars($_POST['dynamic_quest_new'] ?? '');
    $stage = intval($_POST['dynamic_stage_new'] ?? 0);
    $topic = htmlspecialchars($_POST['dynamic_topic_new'] ?? '');
    $topic_desc = htmlspecialchars($_POST['dynamic_topic_desc_new'] ?? '');
    $knowledge_class = htmlspecialchars($_POST['dynamic_knowledge_class_new'] ?? '');
    $topic_desc_basic = htmlspecialchars($_POST['dynamic_topic_desc_basic_new'] ?? '');
    $knowledge_class_basic = htmlspecialchars($_POST['dynamic_knowledge_class_basic_new'] ?? '');
    $tags = htmlspecialchars($_POST['dynamic_tags_new'] ?? '');
    $category = htmlspecialchars($_POST['dynamic_category_new'] ?? '');

    if ($id > 0 && !empty($id_quest) && !empty($topic)) {
        $query = "
            UPDATE $schema.oghma_dynamic 
            SET id_quest = $1,
                stage = $2,
                topic = $3,
                topic_desc = $4,
                knowledge_class = $5,
                topic_desc_basic = $6,
                knowledge_class_basic = $7,
                tags = $8,
                category = $9
            WHERE id = $10
        ";

        $result = pg_query_params($conn, $query, [
            $id_quest,
            $stage,
            $topic,
            $topic_desc,
            $knowledge_class,
            $topic_desc_basic,
            $knowledge_class_basic,
            $tags,
            $category,
            $id
        ]);

        if ($result) {
            $message .= "<p>Dynamic entry updated successfully!</p>";
        } else {
            $message .= "<p>Error updating dynamic entry: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please ensure all required fields are filled in.</p>";
    }
}

/********************************************************************
 *  DELETE ALL DYNAMIC ENTRIES
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all_dynamic') {
    $truncateQuery = "TRUNCATE TABLE {$schema}.oghma_dynamic RESTART IDENTITY";
    $truncateResult = pg_query($conn, $truncateQuery);

    if ($truncateResult) {
        $message .= "<p style='color: #ff6464; font-weight: bold;'>All Dynamic Oghma entries have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error deleting dynamic entries: " . pg_last_error($conn) . "</p>";
    }
}

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 20px; /* Top padding */
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

    /* Tab Navigation */
    .tab-navigation {
        display: flex;
        border-bottom: 2px solid #4a4a4a;
        margin-bottom: 28px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px 10px 0 0;
        border: 1px solid #3a3a3a;
        border-bottom: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .tab-button {
        flex: 1;
        padding: 16px 20px;
        background: transparent;
        color: rgb(242, 124, 17);
        border: none;
        cursor: pointer;
        font-family: 'MagicCards', serif;
        font-size: 17px;
        font-weight: bold;
        word-spacing: 8px;
        transition: all 0.3s ease;
        border-radius: 10px 10px 0 0;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        position: relative;
    }

    .tab-button:first-child {
        border-right: 1px solid rgba(74, 74, 74, 0.5);
    }

    .tab-button.active {
        background: linear-gradient(180deg, rgba(242, 124, 17, 0.15), rgba(242, 124, 17, 0.05));
        color: rgb(242, 124, 17);
        font-weight: bold;
        text-shadow: 1px 1px 3px rgba(242, 124, 17, 0.3);
        box-shadow: inset 0 -3px 0 rgb(242, 124, 17);
    }

    .tab-button:hover:not(.active) {
        background: rgba(74, 74, 74, 0.3);
        color: rgb(255, 140, 30);
    }

    /* Tab Content */
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease-in;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Content Layout Improvements */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 22px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.2s ease;
    }

    .content-section:hover {
        border-color: #4a4a4a;
    }

    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 18px;
        font-size: 1.35em;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    .full-width-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 18px;
        font-size: 1.5em;
        text-align: center;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }

    /* Form Improvements */
    .form-container {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 22px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .content-section label,
    .form-container label {
        display: block;
        font-size: 13px;
        color: rgb(242, 124, 17);
        font-weight: 600;
        margin-bottom: 8px;
        margin-top: 14px;
    }

    .content-section label:first-of-type,
    .form-container label:first-of-type {
        margin-top: 0;
    }

    .content-section input[type="text"],
    .content-section input[type="file"],
    .content-section textarea,
    .form-container input[type="text"],
    .form-container input[type="file"],
    .form-container textarea {
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 8px;
        transition: all 0.2s ease;
    }

    .content-section input:focus,
    .content-section textarea:focus,
    .form-container input:focus,
    .form-container textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    .content-section p {
        color: #aaa;
        font-size: 0.95em;
        line-height: 1.5;
        margin: 8px 0;
    }

    .content-section code {
        background: rgba(26, 26, 26, 0.8);
        padding: 2px 6px;
        border-radius: 3px;
        color: #ffeb3b;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 28px;
        padding: 24px 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(28, 28, 28, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .page-header h1 {
        margin-bottom: 10px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header p {
        color: #aaa;
        font-size: 0.95em;
        margin: 8px 0;
        line-height: 1.5;
    }

    .page-header h3 {
        color: rgb(242, 124, 17);
        font-size: 1.1em;
        margin-top: 20px;
        margin-bottom: 8px;
    }

    .page-header h4 {
        color: #ccc;
        font-size: 1em;
        margin-bottom: 12px;
    }

    #title-text {
        font-family: 'MagicCards', serif;
    }

    /* Header content transitions */
    #header-content > div {
        transition: opacity 0.3s ease-in-out;
        opacity: 1;
    }

    #title-text {
        transition: all 0.3s ease-in-out;
    }

    #dynamic-header-content {
        opacity: 0;
    }

    .page-header code {
        background: rgba(26, 26, 26, 0.8);
        padding: 2px 6px;
        border-radius: 3px;
        color: #ffeb3b;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
        overflow-wrap: break-word;
    }

    /* Compact header intro */
    #oghma-header-content > p {
        max-width: 720px;
        margin-left: auto;
        margin-right: auto;
    }

    .header-note {
        max-width: 720px;
        margin: 12px auto 0;
        padding: 8px 12px;
        text-align: left;
        background: rgba(26, 26, 26, 0.6);
        border: 1px solid #3a3a3a;
        border-left: 3px solid rgb(242, 124, 17);
        border-radius: 4px;
    }

    .header-note > summary {
        cursor: pointer;
        color: rgb(242, 124, 17);
        font-size: 0.95em;
    }

    .header-note > summary:focus-visible {
        outline: 2px solid rgb(242, 124, 17);
        outline-offset: 2px;
    }

    .header-note p {
        margin: 8px 0 0;
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
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
        border-radius: 12px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    }

    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }

    .modal-title {
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.4em;
        margin: 0;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
    }

    .modal-subtitle {
        margin: 6px 0 0;
        color: #b8b8b8;
        font-size: 12.5px;
        line-height: 1.4;
    }

    /* Inline explanation shown when a factory article is opened for editing */
    .modal-notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 0 0 16px;
        padding: 12px 14px;
        border: 1px solid rgba(242, 124, 17, 0.35);
        border-left: 3px solid rgb(242, 124, 17);
        border-radius: 8px;
        background: rgba(242, 124, 17, 0.08);
        color: #d8d8d8;
        font-size: 12.5px;
        line-height: 1.5;
    }

    .modal-notice strong {
        color: rgb(242, 124, 17);
    }

    .modal-notice p {
        margin: 6px 0 0;
    }

    .modal-notice-icon {
        flex: 0 0 auto;
        font-size: 15px;
        line-height: 1.3;
    }

    .modal-body {
        max-height: calc(100vh - 300px);
        overflow-y: auto;
        padding: 20px 24px;
        padding-right: 20px;
    }

    /* Form field spacing */
    .modal-body label {
        display: block;
        margin-top: 16px;
        color: rgb(242, 124, 17);
        font-weight: 600;
        font-size: 13px;
    }

    .modal-body label:first-of-type {
        margin-top: 0;
    }

    .modal-body small {
        display: block;
        color: #999;
        margin-bottom: 6px;
        font-size: 12px;
        line-height: 1.4;
    }

    .modal-body input[type="text"],
    .modal-body input[type="number"],
    .modal-body textarea {
        width: 100%;
        margin-bottom: 12px;
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .modal-body input:focus,
    .modal-body textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    /* Read-only canonical topic on a factory article */
    .modal-body input.is-readonly {
        background-color: rgba(20, 20, 20, 0.85);
        color: #bdbdbd;
        border-style: dashed;
        cursor: default;
    }

    .modal-footer {
        position: sticky;
        bottom: 0;
        background: rgba(42, 42, 42, 0.98);
        padding: 16px 24px;
        margin-top: 20px;
        border-top: 1px solid rgba(242, 124, 17, 0.2);
        border-radius: 0 0 12px 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .modal-footer-note {
        margin: 0 auto 0 0;
        align-self: center;
        max-width: 55%;
        color: #999;
        font-size: 12px;
        line-height: 1.4;
    }

    .modal-container [hidden] {
        display: none !important;
    }

    /* Source column badges */
    .source-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: rgba(255, 255, 255, 0.06);
        color: #b8b8b8;
        font-size: 0.85em;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .source-badge--factory {
        border-color: rgba(242, 124, 17, 0.4);
        background: rgba(242, 124, 17, 0.14);
        color: rgb(242, 124, 17);
    }

    .source-badge--custom {
        border-color: rgba(126, 200, 255, 0.4);
        background: rgba(126, 200, 255, 0.12);
        color: #7ec8ff;
    }

    /* Table container height adjustment */
    .table-container {
        max-height: calc(100vh - 450px) !important;
        margin-top: 20px;
        width: 100%;
        overflow-x: auto;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
        padding: 12px;
    }

    /* Table styling improvements */
    .table-container table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .table-container th {
        padding: 12px 10px;
        font-weight: bold;
        text-align: left;
        vertical-align: top;
        color: rgb(242, 124, 17);
        background: rgba(26, 26, 26, 0.6);
        border-bottom: 2px solid rgba(242, 124, 17, 0.3);
        font-size: 0.95em;
    }

    .table-container td {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        vertical-align: top;
        padding: 10px;
        line-height: 1.5;
        border-bottom: 1px solid rgba(74, 74, 74, 0.3);
        color: #d0d0d0;
    }

    .table-container tr:hover td {
        background: rgba(242, 124, 17, 0.05);
    }

    /* Column width optimization */
    .table-container th:nth-child(1), /* Topic */
    .table-container td:nth-child(1) {
        width: 12%;
        min-width: 120px;
    }

    .table-container th:nth-child(2), /* Topic Description */
    .table-container td:nth-child(2) {
        width: 25%;
        min-width: 200px;
    }

    .table-container th:nth-child(3), /* Knowledge Class */
    .table-container td:nth-child(3) {
        width: 12%;
        min-width: 120px;
    }

    .table-container th:nth-child(4), /* Topic Description (Basic) */
    .table-container td:nth-child(4) {
        width: 20%;
        min-width: 180px;
    }

    .table-container th:nth-child(5), /* Knowledge Class (Basic) */
    .table-container td:nth-child(5) {
        width: 12%;
        min-width: 120px;
    }

    .table-container th:nth-child(6), /* Tags */
    .table-container td:nth-child(6) {
        width: 8%;
        min-width: 80px;
    }

    .table-container th:nth-child(7), /* Category */
    .table-container td:nth-child(7) {
        width: 8%;
        min-width: 80px;
    }

    .table-container th:nth-child(8), /* Action */
    .table-container td:nth-child(8) {
        width: 8%;
        min-width: 80px;
    }

    /* Text wrapping and overflow handling */
    .table-container td {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        vertical-align: top;
        padding: 10px;
        line-height: 1.5;
        border-bottom: 1px solid rgba(74, 74, 74, 0.3);
        color: #d0d0d0;
    }

    .table-container th {
        padding: 12px 10px;
        font-weight: bold;
        text-align: left;
        vertical-align: top;
        color: rgb(242, 124, 17);
        background: rgba(26, 26, 26, 0.6);
        border-bottom: 2px solid rgba(242, 124, 17, 0.3);
        font-size: 0.95em;
    }

    /* Responsive table for smaller screens */
    @media (max-width: 1200px) {
        .table-container {
            font-size: 0.9em;
        }
        
        .table-container th:nth-child(2), /* Topic Description */
        .table-container td:nth-child(2) {
            width: 30%;
        }
        
        .table-container th:nth-child(4), /* Topic Description (Basic) */
        .table-container td:nth-child(4) {
            width: 25%;
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

    /* Filter improvements */
    .filter-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .filter-section strong {
        color: rgb(242, 124, 17);
        font-size: 1.05em;
    }

    .action-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        padding: 16px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .search-container {
        display: flex;
        gap: 10px;
        min-width: 300px;
    }

    .search-container input[type="text"] {
        flex-grow: 1;
        padding: 10px 12px;
        border-radius: 6px;
        border: 1px solid #3a3a3a;
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        transition: all 0.2s ease;
    }

    .search-container input:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    /* Catalog pagination */
    .catalog-pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 15px;
        font-size: 0.9em;
        color: #b8b8b8;
    }

    .catalog-pagination-bar strong {
        color: rgb(242, 124, 17);
        font-weight: 600;
    }

    .catalog-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 15px;
    }

    .catalog-pagination a,
    .catalog-pagination span {
        display: inline-block;
        min-width: 36px;
        padding: 6px 10px;
        text-align: center;
        border-radius: 6px;
        border: 1px solid #3a3a3a;
        background: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        text-decoration: none;
        font-size: 0.9em;
    }

    .catalog-pagination a:hover {
        border-color: rgba(242, 124, 17, 0.5);
        color: rgb(242, 124, 17);
        text-decoration: none;
    }

    .catalog-pagination a:focus-visible {
        outline: 2px solid rgb(242, 124, 17);
        outline-offset: 2px;
    }

    .catalog-pagination [aria-current="page"] {
        background: rgba(242, 124, 17, 0.2);
        border-color: rgba(242, 124, 17, 0.6);
        color: rgb(242, 124, 17);
        font-weight: 600;
    }

    .catalog-pagination [aria-disabled="true"] {
        opacity: 0.45;
        cursor: default;
    }

    .catalog-pagination .catalog-pagination-gap {
        border-color: transparent;
        background: none;
        min-width: 0;
        padding: 6px 2px;
        color: #888;
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
        
        .tab-button {
            padding: 12px 15px;
            font-size: 16px;
            color: rgb(242, 124, 17);
        }
        
        .search-container {
            min-width: 200px;
        }
        
        .action-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .page-header {
            padding: 15px;
        }
        
        .content-section {
            padding: 15px;
        }
        
        .header-note {
            padding: 8px 10px;
        }

        .modal-footer-note {
            margin: 0;
            max-width: 100%;
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
        
        .tab-button {
            padding: 10px 12px;
            font-size: 15px;
            color: rgb(242, 124, 17);
        }
        
        .header-note {
            padding: 8px;
            margin-top: 10px;
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
        <h1 id="page-title">
            <img src="<?php echo $webRoot; ?>/ui/images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32"> 
            <span id="title-text">Oghma Infinium</span>
        </h1>
        
        <div id="header-content">
            <!-- Regular Oghma Content -->
            <div id="oghma-header-content">
                <p>Oghma matches conversation topics to articles. NPCs receive the most detailed version they are allowed to know; if no version matches their knowledge, they know nothing about the topic.</p>
            </div>
            
            <!-- Dynamic Oghma Content -->
            <div id="dynamic-header-content" style="display: none;">
                <p>Entries in the <b>Dynamic Oghma</b> table will update the Oghma table above whenever the quest ID & stage ID for a quest is reached.</p>
                <p>Any changes from a topic in this table will override whatever is in the Oghma table.</p>
                <p>You can leave cells empty so they do not overwrite specific cells from the Oghma table.</p>
                <p>If a cell has the text <b>"clearall"</b> in it, it will clear that cell in the Oghma table.</p>
                <p>You also can introduce new topics to the Oghma table as well.</p>
                <p>It is currently empty by default. We need your help adding more entries!</p>
                <p><a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?gid=243486711#gid=243486711" style="color: yellow;" target="_blank" rel="noopener noreferrer">Would you like to know more?</a></p>
            </div>

        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button active" onclick="switchTab('oghma-tab')">
            &#x1F4DA; Oghma Infinium
        </button>
        <button class="tab-button" onclick="switchTab('dynamic-tab')">
            &#x26A1; Dynamic Oghma
        </button>
    </div>

    <!-- Regular Oghma Tab -->
    <div id="oghma-tab" class="tab-content active">
        <div class="content-grid">
            <div class="content-section">
                <h2>Batch Upload</h2>
                <form action="" method="post" enctype="multipart/form-data">
                    <div>
                        <label for="csv_file">Select .csv file to upload:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="margin-top: 10px;">
                    </div>
                    <div class="button-group">
                        <input type="submit" name="submit_csv" value="Upload CSV" class="action-button upload-csv">
                        <a href="?action=download_example" class="action-button download-csv">Download Example CSV</a>
                    </div>
                </form>
                
                <p style="margin-top: 15px;">Uploads are stored as <strong>custom</strong> entries. Updating a factory topic creates a protected custom override that future catalog activations will not overwrite.</p>

                <details class="header-note">
                    <summary>Article editing tips</summary>
                    <p>Use lowercase topic titles with underscores instead of spaces &mdash; "Fishy Stick" becomes <code>fishy_stick</code>.</p>
                    <p><code>common</code> marks an article as public basic knowledge. Use it only on articles, not NPC tags. An empty basic class is also unrestricted.</p>
                </details>
            </div>

            <div class="content-section">
                <h2>Database Management</h2>
                <p>Verify uploads: <br><b>Server Actions &rarr; Database Manager &rarr; dwemer &rarr; public &rarr; oghma</b></p>
                <p>View grounded, rejected, access, and fallback decisions in <a href="oghma_audit.php">Oghma Audit</a>.</p>

                <div class="button-group" style="margin-top: 20px;">
                    <form action="" method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete_all">
                        <input type="submit" class="btn-danger" value="Delete All Custom Entries"
                               onclick="return confirm('Delete every custom Oghma entry? Factory entries will be preserved.');">
                    </form>
                    
                    <a class="btn-danger" href="<?php echo $webRoot; ?>/ui/oghma_reset.php">Sync Factory Catalog</a>
                </div>
            </div>
        </div>
        <div class="full-width-section">
            <?php
            /********************************************************************
             *  5) DISPLAY THE OGHMA ENTRIES
             ********************************************************************/
            // Fetch categories
            $catQuery = "SELECT DISTINCT category FROM $schema.oghma WHERE category IS NOT NULL AND category <> '' ORDER BY category";
            $catResult = pg_query($conn, $catQuery);
            $categories = [];
            if ($catResult) {
                while ($row = pg_fetch_assoc($catResult)) {
                    $categories[] = $row['category'];
                }
            }

            // Grab filters
            $selectedCategory = $_GET['cat']   ?? '';
            $letter          = strtoupper($_GET['letter'] ?? '');

            // Sorting
            $order = 'ASC';
            if (isset($_GET['order'])) {
                $requestedOrder = strtolower($_GET['order']);
                if ($requestedOrder === 'asc' || $requestedOrder === 'desc') {
                    $order = strtoupper($requestedOrder);
                }
            }
            ?>
            
            <h2 id="entries">&#x1F4CB; Oghma Infinium Entries</h2>
            
            <div class="action-container">
                <button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>
                <div class="search-container">
                    <input type="text" id="searchBox" placeholder="Search topics, aliases, or tags..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">
                    <button onclick="applySearch()" class="action-button edit">Search</button>
                </div>
            </div>

            <div class="filter-section">
                <div style="margin-bottom: 15px;">
                    <strong>Filter by Category:</strong><br>
                    <div class="filter-buttons" style="margin-top: 10px;">
                        <a class="alphabet-button" href="?#entries">All Categories</a>
                        <?php
                        foreach ($categories as $cat) {
                            $catEncoded = urlencode($cat);
                            $style = ($selectedCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
                            echo "<a class=\"alphabet-button\" $style href=\"?cat=$catEncoded#entries\">" . htmlspecialchars($cat) . "</a>";
                        }
                        ?>
                    </div>
                </div>
                
                <div>
                    <strong>Sort Order:</strong><br>
                    <?php
                    $baseUrl = '?';
                    if ($selectedCategory) $baseUrl .= 'cat=' . urlencode($selectedCategory) . '&';
                    if ($letter) $baseUrl .= 'letter=' . urlencode($letter) . '&';
                    ?>
                    <div style="margin-top: 10px;">
                        <a class="alphabet-button" href="<?php echo $baseUrl; ?>order=asc#entries">&#x1F53C; Ascending</a>
                        <a class="alphabet-button" href="<?php echo $baseUrl; ?>order=desc#entries">&#x1F53D; Descending</a>
                    </div>
                </div>
            </div>

            <?php
            // Build query. Letter filters remain canonical-topic based; free text searches identity and related metadata.
            $searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
            $conditions = [];
            $params = [];
            if ($selectedCategory) {
                $params[] = $selectedCategory;
                $conditions[] = 'category = $' . count($params);
            }
            if ($letter) {
                $params[] = $letter . '%';
                $conditions[] = 'topic ILIKE $' . count($params);
            }
            if ($searchTerm !== '') {
                $params[] = '%' . $searchTerm . '%';
                $placeholder = '$' . count($params);
                $conditions[] = "(topic ILIKE {$placeholder} OR coalesce(aliases, '') ILIKE {$placeholder} OR coalesce(tags, '') ILIKE {$placeholder})";
            }
            $whereSql = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

            // Fixed page size: the catalog is far too large to render in one page.
            $perPage = 500;

            // Count with the exact same filters as the row query so the page math matches the results.
            $totalEntries = 0;
            $countResult = pg_query_params($conn, "SELECT COUNT(*) FROM $schema.oghma $whereSql", $params);
            if ($countResult) {
                $totalEntries = (int) pg_fetch_result($countResult, 0, 0);
            }

            $totalPages = max(1, (int) ceil($totalEntries / $perPage));
            $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $offset = ($currentPage - 1) * $perPage;

            $query = "
                SELECT article.topic, article.aliases, article.topic_desc, article.knowledge_class, article.topic_desc_basic,
                       article.knowledge_class_basic, article.tags, article.category, article.source_type, article.source_catalog_version,
                       EXISTS (
                           SELECT 1
                           FROM $schema.oghma_catalog_entries entry
                           JOIN $schema.oghma_catalogs catalog
                             ON catalog.catalog_version = entry.catalog_version
                            AND catalog.state = 'active'
                           WHERE entry.topic = article.topic
                       ) AS factory_origin
                FROM $schema.oghma article
                $whereSql
                ORDER BY article.topic $order
                LIMIT $perPage OFFSET $offset
            ";

            $result = pg_query_params($conn, $query, $params);

            // Pagination links keep every active filter so paging never silently widens the result set.
            $pageLinkParams = [];
            if ($selectedCategory !== '') $pageLinkParams['cat'] = $selectedCategory;
            if ($letter !== '')           $pageLinkParams['letter'] = $letter;
            if ($searchTerm !== '')       $pageLinkParams['search'] = $searchTerm;
            $pageLinkParams['order'] = strtolower($order);

            $oghmaPageUrl = static function ($page) use ($pageLinkParams) {
                return '?' . http_build_query($pageLinkParams + ['page' => (int) $page]) . '#entries';
            };

            echo '<a id="entries"></a>';

            if ($totalEntries > 0) {
                $firstShown = $offset + 1;
                $lastShown  = min($offset + $perPage, $totalEntries);
                echo '<div class="catalog-pagination-bar">';
                echo '<span>Showing <strong>' . number_format($firstShown) . '&ndash;' . number_format($lastShown)
                    . '</strong> of <strong>' . number_format($totalEntries) . '</strong> articles</span>';
                echo '<span>Page <strong>' . number_format($currentPage) . '</strong> of <strong>'
                    . number_format($totalPages) . '</strong></span>';
                echo '</div>';
            }
            echo '<div class="table-container">';
            echo '<table>';
            echo '<tr>
                    <th>Topic</th>
                    <th>Aliases</th>
                    <th>Topic Description (Advanced)</th>
                    <th>Knowledge Class (Advanced)</th>
                    <th>Topic Description (Basic)</th>
                    <th>Knowledge Class (Basic)</th>
                    <th>Tags</th>
                    <th>Category</th>
                    <th>Source</th>
                    <th>Action</th> 
                  </tr>';

            if ($result) {
                $rowCount = 0;
                while ($row = pg_fetch_assoc($result)) {
                    $topic                = htmlspecialchars($row['topic']                ?? '');
                    $aliases              = htmlspecialchars($row['aliases']              ?? '');
                    $topic_desc           = htmlspecialchars($row['topic_desc']           ?? '');
                    $knowledge_class      = htmlspecialchars($row['knowledge_class']      ?? '');
                    $topic_desc_basic     = htmlspecialchars($row['topic_desc_basic']     ?? '');
                    $knowledge_class_basic= htmlspecialchars($row['knowledge_class_basic']?? '');
                    $tags                 = htmlspecialchars($row['tags']                 ?? '');
                    $category             = htmlspecialchars($row['category']             ?? '');
                    $sourceType            = htmlspecialchars($row['source_type']          ?? 'legacy');
                    $sourceVersion         = htmlspecialchars($row['source_catalog_version'] ?? '');
                    // Presentation-only hint: the POST handlers re-check ownership server side.
                    $sourceKind            = strtolower(trim((string) ($row['source_type'] ?? 'legacy')));
                    if ($sourceKind === '') $sourceKind = 'legacy';
                    $sourceKindClass       = preg_replace('/[^a-z0-9_-]/', '', $sourceKind);
                    $isFactoryRow          = ($sourceKind === 'factory');
                    $hasFactoryOrigin      = (($row['factory_origin'] ?? 'f') === 't');

                    // Normal row display
                    echo '<tr>';
                    echo '<td>' . $topic . '</td>';
                    echo '<td>' . ($aliases !== '' ? $aliases : '<span style="color:#888;">None</span>') . '</td>';
                    echo '<td>' . nl2br($topic_desc) . '</td>';
                    
                    // Knowledge Class column with badge styling
                    echo '<td style="font-size: 1.5em; line-height: 1.4;">';
                    if (!empty(trim($knowledge_class))) {
                        $knowledgeClasses = array_map('trim', explode(',', $knowledge_class));
                        foreach ($knowledgeClasses as $class) {
                            if (!empty($class)) {
                                echo '<span style="display: inline-block; background: rgba(242, 124, 17, 0.2); color: rgb(242, 124, 17); padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 0.85em; font-weight: 500;">' . htmlspecialchars($class) . '</span>';
                            }
                        }
                    } else {
                        echo '<span style="color: #888; font-style: italic;">Everyone</span>';
                    }
                    echo '</td>';
                    
                    echo '<td>' . nl2br($topic_desc_basic) . '</td>';
                    
                    // Knowledge Class Basic column with badge styling
                    echo '<td style="font-size: 1.5em; line-height: 1.4;">';
                    if (!empty(trim($knowledge_class_basic))) {
                        $knowledgeClassesBasic = array_map('trim', explode(',', $knowledge_class_basic));
                        foreach ($knowledgeClassesBasic as $class) {
                            if (!empty($class)) {
                                echo '<span style="display: inline-block; background: rgba(242, 124, 17, 0.15); color: rgb(242, 124, 17); padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 0.85em; font-weight: 400;">' . htmlspecialchars($class) . '</span>';
                            }
                        }
                    } else {
                        echo '<span style="color: #888; font-style: italic;">Everyone</span>';
                    }
                    echo '</td>';
                    
                    echo '<td>' . nl2br($tags) . '</td>';
                    echo '<td>' . nl2br($category) . '</td>';
                    echo '<td><span class="source-badge source-badge--' . $sourceKindClass . '">' . $sourceType . '</span>'
                        . ($sourceVersion !== '' ? '<br><small>' . $sourceVersion . '</small>' : '') . '</td>';

                    // Action column
                    echo '<td style="white-space: nowrap;">';
                    echo '<div style="display: flex; gap: 4px;">';

                    // Edit button only
                    $editPayload = htmlspecialchars(json_encode([
                        'topic' => $topic,
                        'aliases' => $aliases,
                        'topic_desc' => $topic_desc,
                        'knowledge_class' => $knowledge_class,
                        'topic_desc_basic' => $topic_desc_basic,
                        'knowledge_class_basic' => $knowledge_class_basic,
                        'tags' => $tags,
                        'category' => $category,
                        'source_type' => $sourceKind,
                        'factory_origin' => $hasFactoryOrigin
                    ]), ENT_QUOTES, 'UTF-8');
                    echo '<button type="button" onclick="openEditModal(' . $editPayload . ')" class="action-button edit"'
                        . ($isFactoryRow
                            ? ' title="Editing this factory article saves a separate custom override. The factory article stays unchanged."'
                                . ' aria-label="Edit factory article ' . $topic . ' and save a custom override"'
                            : ' aria-label="Edit article ' . $topic . '"')
                        . '>Edit</button>';

                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';

                    $rowCount++;
                }

                echo '</table>';
                echo '</div>';

                if ($rowCount === 0) {
                    echo '<p>No entries found.</p>';
                }

                if ($totalPages > 1) {
                    // Compact window: first/last always reachable, neighbours around the current page.
                    $windowStart = max(1, $currentPage - 2);
                    $windowEnd   = min($totalPages, $currentPage + 2);
                    $pageItems = range($windowStart, $windowEnd);
                    if ($windowStart > 1) {
                        if ($windowStart > 2) array_unshift($pageItems, 'gap');
                        array_unshift($pageItems, 1);
                    }
                    if ($windowEnd < $totalPages) {
                        if ($windowEnd < $totalPages - 1) $pageItems[] = 'gap';
                        $pageItems[] = $totalPages;
                    }

                    echo '<nav class="catalog-pagination" aria-label="Oghma Infinium entries pagination">';

                    if ($currentPage > 1) {
                        echo '<a href="' . htmlspecialchars($oghmaPageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8')
                            . '" rel="prev">&laquo; Previous</a>';
                    } else {
                        echo '<span aria-disabled="true">&laquo; Previous</span>';
                    }

                    foreach ($pageItems as $item) {
                        if ($item === 'gap') {
                            echo '<span class="catalog-pagination-gap" aria-hidden="true">&hellip;</span>';
                        } elseif ($item === $currentPage) {
                            echo '<span aria-current="page">' . $item . '</span>';
                        } else {
                            echo '<a href="' . htmlspecialchars($oghmaPageUrl($item), ENT_QUOTES, 'UTF-8')
                                . '" aria-label="Go to page ' . $item . '">' . $item . '</a>';
                        }
                    }

                    if ($currentPage < $totalPages) {
                        echo '<a href="' . htmlspecialchars($oghmaPageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8')
                            . '" rel="next">Next &raquo;</a>';
                    } else {
                        echo '<span aria-disabled="true">Next &raquo;</span>';
                    }

                    echo '</nav>';
                }
            } else {
                echo '<p>Error fetching Oghma entries: ' . pg_last_error($conn) . '</p>';
            }
            ?>
        </div>
    </div>

    <!-- Dynamic Oghma Tab -->
    <div id="dynamic-tab" class="tab-content">
        <div class="content-grid">
            <div class="content-section">
                <h2>Batch Upload</h2>
                <form action="" method="post" enctype="multipart/form-data">
                    <div>
                        <label for="dynamic_csv_file">Select .csv file to upload dynamic entries:</label>
                        <br>
                        <input type="file" name="dynamic_csv_file" id="dynamic_csv_file" accept=".csv" required>
                    </div>
                    <div class="button-group">
                        <input type="submit" name="submit_dynamic_csv" value="Upload CSV" class="action-button upload-csv">
                        <a href="../data/oghma_dynamic_example.csv" class="action-button download-csv">Download Example CSV</a>
                    </div>
                </form>
                <p>You can verify that the entries have been uploaded successfully by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> oghma_dynamic</b></p>
                <p>You see what quests CHIM have detected by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> questlog</b></p>
                <p>All uploaded entries will be saved into the <code>oghma_dynamic</code> table.</p>
            </div>

            <div class="content-section">
                <h2>Database Management</h2>
                <p>Verify uploads: <br><b>Server Actions &rarr; Database Manager &rarr; dwemer &rarr; public &rarr; oghma_dynamic</b></p>
                <p>View conversation usage: <br><b>Server Actions &rarr; Database Manager &rarr; dwemer &rarr; public &rarr; audit_memory</b></p>
                
                <div class="button-group" style="margin-top: 20px;">
                    <form action="" method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete_all_dynamic">
                        <input type="submit" class="btn-danger" value="Delete All Dynamic Entries" onclick="return confirm('Are you sure you want to delete ALL dynamic entries? This cannot be undone!');">
                    </form>
                </div>
            </div>
        </div>
        <div class="full-width-section">
            <h2 id="dynamic">&#x1F4CB; Dynamic Oghma Entries</h2>
            
            <div class="action-container">
                <button onclick="openNewDynamicEntryModal()" class="action-button add-new">Add New Dynamic Entry</button>
            </div>
            
            <?php
            // Fetch categories for dynamic entries
            $dynamicCatQuery = "SELECT DISTINCT category FROM $schema.oghma_dynamic WHERE category IS NOT NULL AND category <> '' ORDER BY category";
            $dynamicCatResult = pg_query($conn, $dynamicCatQuery);
            $dynamicCategories = [];
            if ($dynamicCatResult) {
                while ($row = pg_fetch_assoc($dynamicCatResult)) {
                    $dynamicCategories[] = $row['category'];
                }
            }

            // Get selected category for dynamic entries
            $selectedDynamicCategory = $_GET['dynamic_cat'] ?? '';
            ?>
            
            <div class="filter-section">
                <div>
                    <strong>Filter by Category:</strong><br>
                    <div class="filter-buttons" style="margin-top: 10px;">
                        <a class="alphabet-button" href="?#dynamic">All Categories</a>
                        <?php
                        foreach ($dynamicCategories as $cat) {
                            $catEncoded = urlencode($cat);
                            $style = ($selectedDynamicCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
                            echo "<a class=\"alphabet-button\" $style href=\"?dynamic_cat=$catEncoded#dynamic\">" . htmlspecialchars($cat) . "</a>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <?php

            // Query for dynamic entries with category filter
            $dynamicQuery = "
                SELECT id, id_quest, stage, topic, topic_desc, knowledge_class, topic_desc_basic,
                       knowledge_class_basic, tags, category
                FROM $schema.oghma_dynamic
            ";

            if ($selectedDynamicCategory) {
                $dynamicQuery .= " WHERE category = $1
                ORDER BY id_quest, stage ASC";
                $dynamicResult = pg_query_params($conn, $dynamicQuery, [$selectedDynamicCategory]);
            } else {
                $dynamicQuery .= " ORDER BY id_quest, stage ASC";
                $dynamicResult = pg_query($conn, $dynamicQuery);
            }

            echo '<div class="table-container">';
            echo '<table>';
            echo '<tr>
                    <th>Quest ID</th>
                    <th>Stage</th>
                    <th>Topic</th>
                    <th>Topic Description</th>
                    <th>Knowledge Class</th>
                    <th>Topic Description (Basic)</th>
                    <th>Knowledge Class (Basic)</th>
                    <th>Tags</th>
                    <th>Category</th>
                    <th>Action</th>
                  </tr>';

            if ($dynamicResult) {
                $rowCount = 0;
                while ($row = pg_fetch_assoc($dynamicResult)) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id_quest'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['stage'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['topic'] ?? '') . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['topic_desc'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['knowledge_class'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['topic_desc_basic'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['knowledge_class_basic'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['tags'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['category'] ?? '')) . '</td>';
                    
                    // Add edit button column
                    echo '<td style="white-space: nowrap;">';
                    echo '<div style="display: flex; gap: 4px;">';
                    echo '<button onclick="openDynamicEditModal(' . 
                        htmlspecialchars(json_encode([
                            'id' => $row['id'],
                            'id_quest' => $row['id_quest'],
                            'stage' => $row['stage'],
                            'topic' => $row['topic'],
                            'topic_desc' => $row['topic_desc'],
                            'knowledge_class' => $row['knowledge_class'],
                            'topic_desc_basic' => $row['topic_desc_basic'],
                            'knowledge_class_basic' => $row['knowledge_class_basic'],
                            'tags' => $row['tags'],
                            'category' => $row['category']
                        ]), ENT_QUOTES, 'UTF-8') . 
                        ')" class="action-button edit">Edit</button>';
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                    $rowCount++;
                }

                echo '</table>';
                echo '</div>';

                if ($rowCount === 0) {
                    echo '<p>No dynamic entries found.</p>';
                }
            } else {
                echo '<p>Error fetching Dynamic Oghma entries: ' . pg_last_error($conn) . '</p>';
            }
            ?>
        </div>
    </div>

<div id="editModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit_modal_title" aria-hidden="true">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title" id="edit_modal_title">Edit Oghma Entry</h2>
            <p class="modal-subtitle" id="edit_modal_subtitle" hidden></p>
        </div>
        <div class="modal-body" id="edit_modal_body">
            <form action="" method="post">
                <input type="hidden" name="action" value="update_single">
                <input type="hidden" name="topic_original" id="edit_topic_original">

                <div class="modal-notice" id="edit_factory_notice" role="note" hidden>
                    <span class="modal-notice-icon" aria-hidden="true">&#x1F4D8;</span>
                    <div>
                        <strong id="edit_factory_notice_title">This is a factory article &mdash; saving will not change it.</strong>
                        <p id="edit_factory_notice_text">Your edits are saved as a separate <strong>custom override</strong> for this topic. The factory article stays exactly as shipped, and future catalog updates will not overwrite your override.</p>
                        <p>The factory topic is canonical and cannot be renamed, so the override always replaces the right article.</p>
                    </div>
                </div>

                <label for="edit_topic">Topic:</label>
                <small id="edit_topic_hint">Topic name for keyword searching.</small>
                <input type="text" name="topic_new" id="edit_topic" aria-describedby="edit_topic_hint" required>

                <label for="edit_aliases">Aliases:</label>
                <small>Alternate names that should find this article. Separate aliases with commas.</small>
                <input type="text" name="aliases_new" id="edit_aliases">

                <label for="edit_topic_desc">Topic Description:</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="topic_desc_new" id="edit_topic_desc" rows="8" required></textarea>
                

                <label for="edit_knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Separate tags by commas. Do not use <code>common</code> here &mdash; it only marks public access on the basic article. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_new" id="edit_knowledge_class">

                <label for="edit_topic_desc_basic">Topic Description (Basic):</label>
                <small>Who should have basic information on the subject.</small>
                <textarea name="topic_desc_basic_new" id="edit_topic_desc_basic" rows="8"></textarea>
                

                <label for="edit_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Separate tags by commas. Use <code>common</code> to make this basic article public to every NPC &mdash; it is an article marker only, never an NPC tag. Leaving this empty is also unrestricted. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_basic_new" id="edit_knowledge_class_basic">

                <label for="edit_tags">Tags:</label>
                <small>Related concepts for catalog search and contextual retrieval. Separate tags by commas.</small>
                <input type="text" name="tags_new" id="edit_tags">

                <label for="edit_category">Category:</label>
                <small>Category for database searching.</small>
                <input type="text" name="category_new" id="edit_category">

                <div class="modal-footer">
                    <p class="modal-footer-note" id="edit_delete_note" hidden>No custom override exists yet, so there is nothing to delete.</p>
                    <button type="submit" name="submit" value="update" class="btn-save" id="edit_save_button">Save Changes</button>
                    <button type="button" onclick="deleteEntry()" class="btn-danger" id="edit_delete_button">Delete</button>
                    <button type="button" onclick="closeEditModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New Oghma Entry</h2>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="submit_individual" value="1">

                <label for="topic">Topic (required):</label>
                <small>Topic name for keyword searching.</small>
                <input type="text" name="topic" id="topic" required>

                <label for="aliases">Aliases:</label>
                <small>Alternate names that should find this article. Separate aliases with commas.</small>
                <input type="text" name="aliases" id="aliases">

                <label for="topic_desc">Topic Description (required):</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="topic_desc" id="topic_desc" rows="5" required></textarea>

                <label for="knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Separate tags by commas. Do not use <code>common</code> here &mdash; it only marks public access on the basic article. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class" id="knowledge_class">

                <label for="topic_desc_basic">Topic Description (Basic):</label>
                <small>Who should have basic information on the subject.</small>
                <textarea name="topic_desc_basic" id="topic_desc_basic" rows="5"></textarea>

                <label for="knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Separate tags by commas. Use <code>common</code> to make this basic article public to every NPC &mdash; it is an article marker only, never an NPC tag, and most basic articles should use it. Leaving this empty is also unrestricted. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_basic" id="knowledge_class_basic">

                <label for="tags">Tags:</label>
                <small>Related concepts for catalog search and contextual retrieval. Separate tags by commas.</small>
                <input type="text" name="tags" id="tags">

                <label for="category">Category:</label>
                <small>Category for database searching.</small>
                <input type="text" name="category" id="category">

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save</button>
                    <button type="button" onclick="closeNewEntryModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newDynamicEntryModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New Dynamic Oghma Entry</h2>
            <p>You can leave sections blank so it does not overwrite the existing info in the Oghma Table!</p>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="submit_dynamic" value="1">

                <label for="id_quest">Quest ID (required):</label>
                <small>The quest ID to trigger the dynamic entry.</small>
                <input type="text" name="id_quest" id="id_quest" required>

                <label for="stage">Quest Stage (required):</label>
                <small>The stage ID from the quest to trigger the dynamic entry.</small>
                <input type="number" name="stage" id="stage" value="0">

                <label for="dynamic_topic">Topic (required):</label>
                <small>Topic that will be updated or added in the main Oghma table.</small>
                <input type="text" name="dynamic_topic" id="dynamic_topic" required>

                <label for="dynamic_topic_desc">Topic Description:</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="dynamic_topic_desc" id="dynamic_topic_desc" rows="5"></textarea>

                <label for="dynamic_knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Must be comma seperated. Do not use <code>common</code> here &mdash; it only marks public access on the basic article.</small>
                <input type="text" name="dynamic_knowledge_class" id="dynamic_knowledge_class">

                <label for="dynamic_topic_desc_basic">Topic Description (Basic):</label>
                <small>Basic information about the subject.</small>
                <textarea name="dynamic_topic_desc_basic" id="dynamic_topic_desc_basic" rows="5"></textarea>

                <label for="dynamic_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Must be comma seperated. Use <code>common</code> to make this basic article public to every NPC &mdash; it is an article marker only, never an NPC tag. Leaving this blank is also unrestricted.</small>
                <input type="text" name="dynamic_knowledge_class_basic" id="dynamic_knowledge_class_basic">

                <label for="dynamic_tags">Tags:</label>
                <small>Additional search tags.</small>
                <input type="text" name="dynamic_tags" id="dynamic_tags">

                <label for="dynamic_category">Category:</label>
                <small>Category for organization.</small>
                <input type="text" name="dynamic_category" id="dynamic_category">

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save</button>
                    <button type="button" onclick="closeNewDynamicEntryModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editDynamicModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit Dynamic Oghma Entry</h2>
            <p>You can leave sections blank so it does not overwrite the existing info in the Oghma Table!</p>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="action" value="update_dynamic">
                <input type="hidden" name="dynamic_id" id="edit_dynamic_id">

                <label for="edit_dynamic_quest">Quest ID (required):</label>
                <small>The quest ID to trigger the dynamic entry.</small>
                <input type="text" name="dynamic_quest_new" id="edit_dynamic_quest" required>

                <label for="edit_dynamic_stage">Quest Stage (required):</label>
                <small>The stage ID from the quest to trigger the dynamic entry.</small>
                <input type="number" name="dynamic_stage_new" id="edit_dynamic_stage" value="0" required>

                <label for="edit_dynamic_topic">Topic (required):</label>
                <small>Topic that will be updated or added in the main Oghma table.</small>
                <input type="text" name="dynamic_topic_new" id="edit_dynamic_topic" required>

                <label for="edit_dynamic_topic_desc">Topic Description:</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="dynamic_topic_desc_new" id="edit_dynamic_topic_desc" rows="5"></textarea>

                <label for="edit_dynamic_knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Must be comma separated. Do not use <code>common</code> here &mdash; it only marks public access on the basic article.</small>
                <input type="text" name="dynamic_knowledge_class_new" id="edit_dynamic_knowledge_class">

                <label for="edit_dynamic_topic_desc_basic">Topic Description (Basic):</label>
                <small>Basic information about the subject.</small>
                <textarea name="dynamic_topic_desc_basic_new" id="edit_dynamic_topic_desc_basic" rows="5"></textarea>

                <label for="edit_dynamic_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Must be comma separated. Use <code>common</code> to make this basic article public to every NPC &mdash; it is an article marker only, never an NPC tag. Leaving this blank is also unrestricted.</small>
                <input type="text" name="dynamic_knowledge_class_basic_new" id="edit_dynamic_knowledge_class_basic">

                <label for="edit_dynamic_tags">Tags:</label>
                <small>Additional search tags.</small>
                <input type="text" name="dynamic_tags_new" id="edit_dynamic_tags">

                <label for="edit_dynamic_category">Category:</label>
                <small>Category for organization.</small>
                <input type="text" name="dynamic_category_new" id="edit_dynamic_category">

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="button" onclick="deleteDynamicEntry()" class="btn-danger">Delete</button>
                    <button type="button" onclick="closeDynamicEditModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Define webRoot for JavaScript
var webRoot = '<?php echo $webRoot; ?>';

// Tab switching functionality
function switchTab(tabId) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    const clickedButton = event.target;
    clickedButton.classList.add('active');
    
    // Update header content based on active tab
    updateHeaderContent(tabId);
    
    // Store active tab in localStorage
    localStorage.setItem('activeOghmaTab', tabId);
}

// Function to update header content based on tab
function updateHeaderContent(tabId) {
    const titleText = document.getElementById('title-text');
    const oghmaContent = document.getElementById('oghma-header-content');
    const dynamicContent = document.getElementById('dynamic-header-content');
    
    // Fade out current content
    oghmaContent.style.opacity = '0';
    dynamicContent.style.opacity = '0';
    
    setTimeout(() => {
        if (tabId === 'dynamic-tab') {
            // Switch to Dynamic Oghma
            titleText.textContent = 'Dynamic Oghma';
            oghmaContent.style.display = 'none';
            dynamicContent.style.display = 'block';
            
            // Fade in new content
            setTimeout(() => {
                dynamicContent.style.opacity = '1';
            }, 50);
        } else {
            // Switch to regular Oghma
            titleText.textContent = 'Oghma Infinium';
            oghmaContent.style.display = 'block';
            dynamicContent.style.display = 'none';
            
            // Fade in new content
            setTimeout(() => {
                oghmaContent.style.opacity = '1';
            }, 50);
        }
    }, 150);
}

// Restore active tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTab = localStorage.getItem('activeOghmaTab');
    if (savedTab && document.getElementById(savedTab)) {
        // Manually switch to saved tab
        switchTabDirectly(savedTab);
    } else {
        // Default to oghma tab
        localStorage.setItem('activeOghmaTab', 'oghma-tab');
        updateHeaderContent('oghma-tab');
    }
});

// Function to switch tab without event dependency
function switchTabDirectly(tabId) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Find and activate the corresponding button
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        if (button.getAttribute('onclick') && button.getAttribute('onclick').includes(tabId)) {
            button.classList.add('active');
        }
    });
    
    // Update header content
    updateHeaderContent(tabId);
}

// Copy for the edit modes. The source hints are presentation only - the POST
// handlers re-check ownership server side before writing anything.
const EDIT_MODAL_MODES = {
    factory: {
        title: 'Edit Factory Article',
        subtitle: 'Saving creates a separate custom override. The factory article is left unchanged.',
        topicHint: 'The factory topic is canonical and cannot be renamed. Your override is saved under this exact topic.',
        saveLabel: 'Save as Custom Article'
    },
    override: {
        title: 'Edit Custom Override',
        subtitle: 'This custom article replaces the factory version for the same topic.',
        topicHint: 'This topic remains locked to its factory article.',
        saveLabel: 'Save Custom Override'
    },
    custom: {
        title: 'Edit Oghma Entry',
        subtitle: '',
        topicHint: 'Topic name for keyword searching.',
        saveLabel: 'Save Changes'
    }
};

let editModalTrigger = null;

function handleEditModalKeydown(event) {
    if (event.key === 'Escape' || event.key === 'Esc') {
        event.preventDefault();
        closeEditModal();
    }
}

function openEditModal(data) {
    try {
        const decodeHTML = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_topic_original").value = decodeHTML(data.topic);
        document.getElementById("edit_topic").value = decodeHTML(data.topic);
        document.getElementById("edit_aliases").value = decodeHTML(data.aliases || '');
        document.getElementById("edit_topic_desc").value = decodeHTML(data.topic_desc);
        document.getElementById("edit_knowledge_class").value = decodeHTML(data.knowledge_class);
        document.getElementById("edit_topic_desc_basic").value = decodeHTML(data.topic_desc_basic);
        document.getElementById("edit_knowledge_class_basic").value = decodeHTML(data.knowledge_class_basic);
        document.getElementById("edit_tags").value = decodeHTML(data.tags);
        document.getElementById("edit_category").value = decodeHTML(data.category);

        const isFactory = String(data.source_type || '').trim().toLowerCase() === 'factory';
        const isOverride = !isFactory && data.factory_origin === true;
        const isCatalogBacked = isFactory || isOverride;
        const mode = isFactory ? EDIT_MODAL_MODES.factory : (isOverride ? EDIT_MODAL_MODES.override : EDIT_MODAL_MODES.custom);

        document.getElementById("edit_modal_title").textContent = mode.title;

        const subtitle = document.getElementById("edit_modal_subtitle");
        subtitle.textContent = mode.subtitle;
        subtitle.hidden = !isCatalogBacked;

        document.getElementById("edit_factory_notice").hidden = !isCatalogBacked;
        document.getElementById("edit_factory_notice_title").textContent = isOverride
            ? 'This custom override is active instead of the factory article.'
            : 'This is a factory article - saving will not change it.';
        document.getElementById("edit_factory_notice_text").innerHTML = isOverride
            ? 'Deleting this override immediately restores the unchanged <strong>factory article</strong>.'
            : 'Your edits are saved as a separate <strong>custom override</strong>. The factory article stays exactly as shipped, and catalog updates will not overwrite your override.';

        // Catalog-backed rows keep the canonical topic locked so the override targets the right article.
        const topicField = document.getElementById("edit_topic");
        topicField.readOnly = isCatalogBacked;
        topicField.classList.toggle('is-readonly', isCatalogBacked);
        topicField.setAttribute('aria-readonly', isCatalogBacked ? 'true' : 'false');
        document.getElementById("edit_topic_hint").textContent = mode.topicHint;

        document.getElementById("edit_save_button").textContent = mode.saveLabel;

        // No custom override exists for a factory article yet, so there is nothing to delete.
        const deleteButton = document.getElementById("edit_delete_button");
        deleteButton.disabled = isFactory;
        deleteButton.hidden = isFactory;
        document.getElementById("edit_delete_note").hidden = !isFactory;

        editModalTrigger = document.activeElement;

        const modal = document.getElementById("editModal");
        modal.style.display = "block";
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = "hidden";
        document.getElementById("edit_modal_body").scrollTop = 0;
        document.addEventListener('keydown', handleEditModalKeydown);

        // Land on the first field the user can actually change.
        const firstField = isCatalogBacked ? document.getElementById("edit_aliases") : topicField;
        firstField.focus();
    } catch (error) {
        console.error('Error in openEditModal:', error);
        alert('There was an error opening the edit form. Please try again.');
    }
}

function closeEditModal() {
    const modal = document.getElementById("editModal");
    modal.style.display = "none";
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = "auto";
    document.removeEventListener('keydown', handleEditModalKeydown);

    if (editModalTrigger && typeof editModalTrigger.focus === 'function') {
        editModalTrigger.focus();
    }
    editModalTrigger = null;
}

function openNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function deleteEntry() {
    const deleteButton = document.getElementById('edit_delete_button');
    if (deleteButton && deleteButton.disabled) {
        return;
    }

    const topic = document.getElementById('edit_topic_original').value;
    if (confirm("Are you sure you want to delete: " + topic + "?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        const currentCategory = new URLSearchParams(window.location.search).get('cat');
        form.action = currentCategory ? `?cat=${currentCategory}#entries` : '?#entries';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_single">
            <input type="hidden" name="topic" value="${topic}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function applySearch() {
    const searchTerm = document.getElementById("searchBox").value.trim();
    let url = new URL(window.location.href);
    const urlParams = new URLSearchParams(window.location.search);
    
    // Update or add search parameter
    if (searchTerm) {
        urlParams.set("search", searchTerm);
    } else {
        urlParams.delete("search");
    }
    
    // A new search changes the result set, so start again at the first page
    urlParams.delete("page");

    // Preserve existing parameters if they exist
    const currentCategory = urlParams.get("cat");
    const currentLetter = urlParams.get("letter");
    const currentOrder = urlParams.get("order");
    
    if (currentCategory) urlParams.set("cat", currentCategory);
    if (currentLetter) urlParams.set("letter", currentLetter);
    if (currentOrder) urlParams.set("order", currentOrder);
    
    // Create the new URL
    window.location.href = "?" + urlParams.toString() + "#entries";
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
        document.getElementById("searchBox").value = searchTerm;
    }
});

// Add toast notification JavaScript function
function showToast(message, duration = 5000) {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

// Update PHP message handling
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode(strip_tags($message)); ?>);
});
<?php endif; ?>

function openNewDynamicEntryModal() {
    document.getElementById("newDynamicEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewDynamicEntryModal() {
    document.getElementById("newDynamicEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function openDynamicEditModal(data) {
    try {
        const decodeHTML = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_dynamic_id").value = decodeHTML(data.id);
        document.getElementById("edit_dynamic_quest").value = decodeHTML(data.id_quest);
        document.getElementById("edit_dynamic_stage").value = decodeHTML(data.stage);
        document.getElementById("edit_dynamic_topic").value = decodeHTML(data.topic);
        document.getElementById("edit_dynamic_topic_desc").value = decodeHTML(data.topic_desc);
        document.getElementById("edit_dynamic_knowledge_class").value = decodeHTML(data.knowledge_class);
        document.getElementById("edit_dynamic_topic_desc_basic").value = decodeHTML(data.topic_desc_basic);
        document.getElementById("edit_dynamic_knowledge_class_basic").value = decodeHTML(data.knowledge_class_basic);
        document.getElementById("edit_dynamic_tags").value = decodeHTML(data.tags);
        document.getElementById("edit_dynamic_category").value = decodeHTML(data.category);
        
        document.getElementById("editDynamicModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error('Error in openDynamicEditModal:', error);
        alert('There was an error opening the edit form. Please try again.');
    }
}

function closeDynamicEditModal() {
    document.getElementById("editDynamicModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function deleteDynamicEntry() {
    const id = document.getElementById('edit_dynamic_id').value;
    if (confirm("Are you sure you want to delete this dynamic entry?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_dynamic">
            <input type="hidden" name="dynamic_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
