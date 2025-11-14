<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "🗡️CHIM - Item Descriptions";

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

//
// ────────────────────────────────────────────────────────────────────
//   INDIVIDUAL UPLOAD
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    $baseid = trim($_POST['baseid'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($baseid)) {
        // Truncate baseid to 128 characters
        if (strlen($baseid) > 128) {
            $baseid = substr($baseid, 0, 128);
        }

        $query = "
            INSERT INTO {$schema}.item_description_custom
                (baseid, name, description)
            VALUES ($1, $2, $3)
            ON CONFLICT (baseid)
            DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description
        ";

        $params = [$baseid, $name, $description];
        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>Item data inserted/updated successfully!</p>";
        } else {
            $message .= "<p>Error inserting/updating item data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please fill in the required field: Base ID.</p>";
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   CSV UPLOAD
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $processedCount = 0;
            $errorCount = 0;
            
            try {
                $csvData = @file_get_contents($fileTmpPath);
                if ($csvData === false) {
                    $message .= '<p style="color:#ff6464;">Error reading the uploaded CSV file.</p>';
                } else {
                    $handle = fopen($fileTmpPath, 'r');
                    if ($handle === false) {
                        $message .= '<p style="color:#ff6464;">Error opening CSV file.</p>';
                    } else {
                        $header = fgetcsv($handle, 0, ',');
                        if ($header === false || empty($header)) {
                            $message .= '<p style="color:#ff6464;">Invalid CSV header.</p>';
                        } else {
                            $headerMap = [];
                            foreach ($header as $i => $colName) {
                                $normalized = strtolower(trim($colName));
                                $headerMap[$normalized] = $i;
                            }
                            
                            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                                if (empty($data) || count($data) < 1) {
                                    continue;
                                }
                                
                                $baseid = isset($headerMap['baseid']) && isset($data[$headerMap['baseid']]) 
                                    ? trim($data[$headerMap['baseid']]) 
                                    : '';
                                
                                $name = isset($headerMap['name']) && isset($data[$headerMap['name']]) 
                                    ? trim($data[$headerMap['name']]) 
                                    : '';
                                
                                $description = isset($headerMap['description']) && isset($data[$headerMap['description']]) 
                                    ? trim($data[$headerMap['description']]) 
                                    : '';
                                
                                if (empty($baseid)) {
                                    $errorCount++;
                                    continue;
                                }
                                
                                if (strlen($baseid) > 128) {
                                    $baseid = substr($baseid, 0, 128);
                                }
                                
                                $query = "
                                    INSERT INTO {$schema}.item_description_custom 
                                            (baseid, name, description)
                                    VALUES ($1, $2, $3)
                                    ON CONFLICT (baseid)
                                    DO UPDATE SET
                                        name = EXCLUDED.name,
                                        description = EXCLUDED.description
                                ";
                                
                                $params = [$baseid, $name, $description];
                                $result = pg_query_params($conn, $query, $params);
                                
                                if ($result) {
                                    $processedCount++;
                                } else {
                                    $errorCount++;
                                }
                            }
                            
                            $message .= '<p style="color:#90ee90;">CSV upload complete. ' . $processedCount . ' records processed, ' . $errorCount . ' errors.</p>';
                        }
                        fclose($handle);
                    }
                }
            } catch (Exception $e) {
                $message .= '<p style="color:#ff6464;">Error processing CSV: ' . $e->getMessage() . '</p>';
            }
        } else {
            $message .= '<p style="color:#ff6464;">Invalid file extension. Only .csv files are allowed.</p>';
        }
    } else {
        $message .= '<p style="color:#ff6464;">Error uploading file.</p>';
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   TRUNCATE TABLE
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_items'])) {
    $truncate_query = "TRUNCATE TABLE {$schema}.item_description_custom";
    $result = pg_query($conn, $truncate_query);

    if ($result) {
        $message .= "<p>All custom item entries have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error truncating table: " . pg_last_error($conn) . "</p>";
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   EXPORT CUSTOM ITEMS
// ────────────────────────────────────────────────────────────────────
//
if (isset($_GET['action']) && $_GET['action'] == 'export_custom_items') {
    $export_query = "SELECT baseid, name, description FROM {$schema}.item_description_custom ORDER BY baseid ASC";
    $export_result = pg_query($conn, $export_query);

    if ($export_result) {
        $filename = 'custom_items_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('baseid', 'name', 'description'));
        
        while ($row = pg_fetch_assoc($export_result)) {
            fputcsv($output, array(
                $row['baseid'],
                $row['name'],
                $row['description']
            ));
        }
        
        fclose($output);
        exit;
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   DOWNLOAD EXAMPLE CSV
// ────────────────────────────────────────────────────────────────────
//
if (isset($_GET['action']) && $_GET['action'] == 'download_example') {
    $filename = 'example_items.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('baseid', 'name', 'description'));
    fputcsv($output, array('0001397E', 'Iron Sword', 'A standard iron sword with a worn leather grip.'));
    fputcsv($output, array('00013948', 'Steel Dagger', 'A sharp steel dagger with a simple crossguard.'));
    
    fclose($output);
    exit;
}

//
// ────────────────────────────────────────────────────────────────────
//   UPDATE SINGLE ENTRY
// ────────────────────────────────────────────────────────────────────
//
$formAction = '?#table';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_single') {
    $baseid_original = $_POST['baseid_original'] ?? '';
    $baseid = trim($_POST['baseid'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($baseid)) {
        if (strlen($baseid) > 128) {
            $baseid = substr($baseid, 0, 128);
        }

        $query = "
            INSERT INTO {$schema}.item_description_custom
                (baseid, name, description)
            VALUES ($1, $2, $3)
            ON CONFLICT (baseid)
            DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description
        ";

        $params = [$baseid, $name, $description];
        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>Item entry updated successfully!</p>";
        } else {
            $message .= "<p>Error updating item entry: " . pg_last_error($conn) . "</p>";
        }
    }
}

if ($message) {
    echo "<div class='message' style='background-color: #4a4a4a; color: #f8f9fa; padding: 15px; margin: 20px; border-radius: 8px; border-left: 4px solid rgb(242, 124, 17);'>{$message}</div>";
}
?>

<style>
body {
    background-color: #1a1a1a;
    color: #f8f9fa;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

main {
    padding-left: 5%;
    padding-right: 5%;
    padding-top: 40px;
    padding-bottom: 60px;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid rgb(242, 124, 17);
}

.page-header h1 {
    margin: 0 0 15px 0;
    color: rgb(242, 124, 17);
    font-size: 2em;
}

.page-header p {
    margin: 5px 0;
    color: #d0d0d0;
    font-size: 1.1em;
}

.indent5 {
    margin-left: 5%;
    margin-right: 5%;
}

.content-section {
    background-color: #2a2a2a;
    padding: 25px;
    margin-bottom: 30px;
    border-radius: 8px;
    border: 1px solid #3a3a3a;
}

.content-section h1 {
    color: rgb(242, 124, 17);
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.8em;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 5px;
    color: #d0d0d0;
    font-weight: bold;
}

input[type="text"],
textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #555555;
    background-color: #4a4a4a;
    color: #f8f9fa;
    box-sizing: border-box;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

.button-group {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.action-button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1em;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: all 0.3s ease;
}

.action-button.upload-csv {
    background-color: rgba(74, 158, 255, 0.8);
    color: white;
}

.action-button.upload-csv:hover {
    background-color: rgba(74, 158, 255, 1);
}

.action-button.download-csv {
    background-color: rgba(144, 238, 144, 0.8);
    color: black;
}

.action-button.download-csv:hover {
    background-color: rgba(144, 238, 144, 1);
}

.action-button.edit {
    background-color: rgba(74, 158, 255, 0.8);
    color: white;
}

.action-button.edit:hover {
    background-color: rgba(74, 158, 255, 1);
}

.action-button.add-new {
    background-color: rgba(144, 238, 144, 0.8);
    color: black;
}

.action-button.add-new:hover {
    background-color: rgba(144, 238, 144, 1);
}

.btn-danger {
    background-color: rgba(255, 100, 100, 0.8);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1em;
}

.btn-danger:hover {
    background-color: rgba(255, 100, 100, 1);
}

table {
    width: 100%;
    border-collapse: collapse;
    background-color: #2a2a2a;
    margin-top: 20px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #3a3a3a;
}

th {
    background-color: #3a3a3a;
    color: rgb(242, 124, 17);
    font-weight: bold;
}

tr:hover {
    background-color: #333333;
}

.filter-buttons {
    margin: 20px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.alphabet-button {
    padding: 8px 12px;
    background-color: #3a3a3a;
    color: #f8f9fa;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.alphabet-button:hover {
    background-color: rgb(242, 124, 17);
}

.action-container {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-bottom: 20px;
}

.search-container {
    display: flex;
    gap: 10px;
    flex-grow: 1;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    overflow-y: auto;
    padding: 20px;
}

.modal-container {
    background-color: #2a2a2a;
    border-radius: 8px;
    max-width: 800px;
    margin: 0 auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.modal-header {
    background-color: #3a3a3a;
    padding: 20px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    border-bottom: 2px solid rgb(242, 124, 17);
}

.modal-title {
    margin: 0;
    color: rgb(242, 124, 17);
}

.modal-body {
    padding: 30px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #3a3a3a;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background-color: rgb(242, 124, 17);
    color: white;
    padding: 15px 25px;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    z-index: 2000;
    display: none;
}

@media (max-width: 768px) {
    .button-group {
        flex-direction: column;
    }
    
    .action-container {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<?php if ($isEmbed): ?>
<style>
    main { padding-top: 20px; }
</style>
<?php endif; ?>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>Item Description Management</h1>
        <p>The <b>Item Description System</b> allows you to create custom visual descriptions for items.</p>
        <p>Upload item descriptions individually or in bulk via CSV files. All custom entries override default templates.</p>
    </div>

    <div class="indent5">
        <div class="content-section">
            <h1>Individual Upload</h1>
            <form action="" method="post">
                <label for="baseid">Base ID (required):</label>
                <input type="text" name="baseid" id="baseid" required>

                <label for="name">Item Name:</label>
                <input type="text" name="name" id="name">

                <label for="description">Visual Description:</label>
                <textarea name="description" id="description" rows="4"></textarea>

                <div class="button-group">
                    <input type="submit" name="submit_individual" value="Upload Item" class="action-button upload-csv">
                </div>
            </form>
        </div>

        <div class="content-section">
            <h1>Batch Upload</h1>
            <form action="" method="post" enctype="multipart/form-data">
                <div>
                    <label for="csv_file">Select .csv file to upload:</label>
                    <br>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </div>
                <div class="button-group">
                    <input type="submit" name="submit_csv" value="Upload CSV" class="action-button upload-csv">
                    <a href="?action=download_example" class="action-button download-csv">Download Example CSV</a>
                    <a href="?action=export_custom_items" class="action-button" style="background: rgba(242, 124, 17, 0.8);">Export Custom Items</a>
                </div>
                <p>CSV format: baseid, name, description</p>
                <p>You can verify uploaded data at: 
                <b>Server Actions -> Database Manager -> dwemer -> public -> item_description_custom</b>.</p>
                <p>View merged data at: 
                <b>Server Actions -> Database Manager -> dwemer -> public -> Views -> combined_item_descriptions</b>.</p>
            </form>
            <form action="" method="post">
                <input 
                    type="submit" 
                    name="truncate_items" 
                    value="Factory Reset Item Override Table"
                    class="btn-danger"
                    onclick="return confirm('Are you sure you want to DELETE ALL ENTRIES in item_description_custom? This action is IRREVERSIBLE!');"
                >
            </form>
            <p>This will delete all custom item entries you have uploaded.</p>
        </div>
    </div>

    <br>
    <?php
    $letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';
    $searchTerm = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';

    // Build query based on filters
    if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
        if (!empty($searchTerm)) {
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_item_descriptions
                WHERE LOWER(baseid) LIKE LOWER($1) 
                AND (LOWER(baseid) LIKE LOWER($2) OR LOWER(name) LIKE LOWER($2))
                ORDER BY baseid ASC
            ";
            $params_combined = [$letter . '%', '%' . $searchTerm . '%'];
        } else {
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_item_descriptions
                WHERE LOWER(baseid) LIKE LOWER($1)
                ORDER BY baseid ASC
            ";
            $params_combined = [$letter . '%'];
        }
    } else {
        if (!empty($searchTerm)) {
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_item_descriptions
                WHERE LOWER(baseid) LIKE LOWER($1) OR LOWER(name) LIKE LOWER($1)
                ORDER BY baseid ASC
            ";
            $params_combined = ['%' . $searchTerm . '%'];
        } else {
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_item_descriptions
                ORDER BY baseid ASC
            ";
            $params_combined = [];
        }
    }

    $result_combined = !empty($params_combined) 
        ? pg_query_params($conn, $query_combined, $params_combined)
        : pg_query($conn, $query_combined);

    echo '<br>';
    echo '<div class="indent5" id="table">';
    echo '<h1>Item Descriptions Database</h1>';
    echo '<div class="action-container">';
    echo '<button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>';
    echo '<div class="search-container">';
    echo '<input type="text" id="searchBox" placeholder="Search items..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">';
    echo '<button onclick="applySearch()" class="action-button edit">Search</button>';
    echo '</div>';
    echo '</div>';

    echo '<br>';

    // Alphabetic filter
    echo '<div class="filter-buttons">';
    echo '<a href="?#table" class="alphabet-button">All</a>';
    foreach (range('A', 'Z') as $char) {
        echo '<a href="?letter=' . $char . '#table" class="alphabet-button">' . $char . '</a>';
    }
    foreach (range('0', '9') as $num) {
        echo '<a href="?letter=' . $num . '#table" class="alphabet-button">' . $num . '</a>';
    }
    echo '</div>';

    if ($result_combined) {
        echo '<div id="item-table-container" class="table-container">';
        echo '<table>';
        echo '<tr>';
        echo '  <th>Base ID</th>';
        echo '  <th>Name</th>';
        echo '  <th>Description</th>';
        echo '  <th>Actions</th>';
        echo '</tr>';

        $rowCountCombined = 0;
        while ($row = pg_fetch_assoc($result_combined)) {
            echo '<tr>';
            echo '  <td>' . htmlspecialchars($row['baseid'] ?? '') . '</td>';
            echo '  <td>' . htmlspecialchars($row['name'] ?? '') . '</td>';
            echo '  <td style="max-width: 400px; word-wrap: break-word;">' . nl2br(htmlspecialchars(substr($row['description'] ?? '', 0, 200))) . (strlen($row['description'] ?? '') > 200 ? '...' : '') . '</td>';
            
            echo '<td>';
            $jsData = [
                'baseid' => $row['baseid'],
                'name' => $row['name'] ?? '',
                'description' => $row['description'] ?? ''
            ];
            echo '<button onclick="openEditModal(' . 
                htmlspecialchars(str_replace(
                    ["\r", "\n", "'"],
                    [' ', ' ', "\\'"],
                    json_encode($jsData)
                ), ENT_QUOTES, 'UTF-8') . 
                ')" class="action-button edit" style="font-size: 0.8em; padding: 4px 8px;">Edit</button>';
            echo '</td>';
            echo '</tr>';
            
            $rowCountCombined++;
        }
        echo '</table>';
        echo '</div>';

        if ($rowCountCombined === 0) {
            echo '<p>No items found.</p>';
        }
    } else {
        echo '<p>Error fetching combined item descriptions: ' . pg_last_error($conn) . '</p>';
    }

    echo '</div>';
    ?>
</main>

<div id="editModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit Item Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="action" value="update_single">
                <input type="hidden" name="baseid_original" id="edit_baseid_original">

                <label for="edit_baseid">Base ID:</label>
                <small>Base IDs cannot be changed after creation. If you need to change an ID, create a new entry.</small>
                <input type="text" name="baseid" id="edit_baseid" readonly style="background-color: #2a2a2a; cursor: not-allowed;" required>

                <label for="edit_name">Item Name:</label>
                <input type="text" name="name" id="edit_name">

                <label for="edit_description">Visual Description:</label>
                <textarea name="description" id="edit_description" rows="6"></textarea>

                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="action-button" style="background-color: #555;">Cancel</button>
                    <button type="submit" class="action-button upload-csv">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New Item Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="action" value="update_single">

                <label for="new_baseid">Base ID (required):</label>
                <input type="text" name="baseid" id="new_baseid" required>
                <input type="hidden" name="baseid_original" value="">

                <label for="new_name">Item Name:</label>
                <input type="text" name="name" id="new_name">

                <label for="new_description">Visual Description:</label>
                <textarea name="description" id="new_description" rows="6"></textarea>

                <div class="modal-footer">
                    <button type="button" onclick="closeNewEntryModal()" class="action-button" style="background-color: #555;">Cancel</button>
                    <button type="submit" class="action-button upload-csv">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(data) {
    const modal = document.getElementById('editModal');
    document.getElementById('edit_baseid_original').value = data.baseid;
    document.getElementById('edit_baseid').value = data.baseid;
    document.getElementById('edit_name').value = data.name || '';
    document.getElementById('edit_description').value = data.description || '';
    modal.style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openNewEntryModal() {
    const modal = document.getElementById('newEntryModal');
    document.getElementById('new_baseid').value = '';
    document.getElementById('new_name').value = '';
    document.getElementById('new_description').value = '';
    modal.style.display = 'block';
}

function closeNewEntryModal() {
    document.getElementById('newEntryModal').style.display = 'none';
}

function applySearch() {
    const searchTerm = document.getElementById('searchBox').value;
    window.location.href = '?search=' + encodeURIComponent(searchTerm) + '#table';
}

document.getElementById('searchBox').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applySearch();
    }
});

window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const newEntryModal = document.getElementById('newEntryModal');
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === newEntryModal) {
        closeNewEntryModal();
    }
};
</script>

</body>
</html>

