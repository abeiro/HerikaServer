<?php
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

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Initialize message variable
$message = '';

/********************************************************************
 *  HANDLE AJAX REQUESTS BEFORE OUTPUT BUFFER
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    // UPDATE CUSTOM PROMPT
    if (isset($_POST['action']) && $_POST['action'] === 'update_custom') {
        $prompt_key = $_POST['prompt_key'] ?? '';
        $custom_prompt = $_POST['custom_prompt'] ?? '';
        
        if (!empty($prompt_key)) {
            // If custom_prompt is empty, set it to NULL to revert to default
            if (trim($custom_prompt) === '') {
                $custom_prompt = null;
            }
            
            $query = "
                UPDATE $schema.prompts 
                SET custom_prompt = $1, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE prompt_key = $2
            ";
            $result = pg_query_params($conn, $query, [$custom_prompt, $prompt_key]);
            
            if ($result) {
                $message = $custom_prompt === null ? "Custom prompt cleared" : "Custom prompt updated successfully";
                $success = true;
            } else {
                $message = "Error updating prompt: " . pg_last_error($conn);
                $success = false;
            }
        } else {
            $message = "No prompt key specified";
            $success = false;
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    
    // CLEAR CUSTOM PROMPT
    if (isset($_POST['action']) && $_POST['action'] === 'clear_custom') {
        $prompt_key = $_POST['prompt_key'] ?? '';
        
        if (!empty($prompt_key)) {
            $query = "
                UPDATE $schema.prompts 
                SET custom_prompt = NULL, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE prompt_key = $1
            ";
            $result = pg_query_params($conn, $query, [$prompt_key]);
            
            $success = (bool)$result;
            $message = $success ? "Custom prompt cleared" : "Error clearing prompt: " . pg_last_error($conn);
        } else {
            $success = false;
            $message = "No prompt key specified";
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
}

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "⚙️CHIM - Prompts Manager";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;

/********************************************************************
 *  HANDLE NON-AJAX POST REQUESTS
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_custom') {
    $prompt_key = $_POST['prompt_key'] ?? '';
    $custom_prompt = $_POST['custom_prompt'] ?? '';
    
    if (!empty($prompt_key)) {
        if (trim($custom_prompt) === '') {
            $custom_prompt = null;
        }
        
        $query = "
            UPDATE $schema.prompts 
            SET custom_prompt = $1, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE prompt_key = $2
        ";
        $result = pg_query_params($conn, $query, [$custom_prompt, $prompt_key]);
        
        if ($result) {
            $message = $custom_prompt === null ? "Custom prompt cleared. Using default prompt for <strong>$prompt_key</strong>." : "Custom prompt updated successfully for <strong>$prompt_key</strong>.";
        } else {
            $message = "Error updating prompt: " . pg_last_error($conn);
        }
    } else {
        $message = "No prompt key specified.";
    }
}

/********************************************************************
 *  2) CLEAR CUSTOM PROMPT (REVERT TO DEFAULT)
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_custom') {
    $prompt_key = $_POST['prompt_key'] ?? '';
    
    if (!empty($prompt_key)) {
        $query = "
            UPDATE $schema.prompts 
            SET custom_prompt = NULL, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE prompt_key = $1
        ";
        $result = pg_query_params($conn, $query, [$prompt_key]);
        
        if ($result) {
            $message = "Custom prompt cleared. Reverted to default prompt for <strong>$prompt_key</strong>.";
        } else {
            $message = "Error clearing prompt: " . pg_last_error($conn);
        }
    }
}

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 20px;
        padding-bottom: 40px;
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
        max-width: 100%;
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
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin: 0 0 10px 0;
        font-family: 'MagicCards', serif !important;
        word-spacing: 8px;
        font-size: 2.5em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        letter-spacing: 2px;
    }

    .page-header p {
        color: #b0b0b0;
        margin: 0;
    }

    /* Info boxes */
    .info-box, .warning-box {
        margin-bottom: 25px;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid;
    }

    .info-box {
        background: rgba(100, 149, 237, 0.1);
        border-color: rgb(100, 149, 237);
    }

    .warning-box {
        background: rgba(242, 124, 17, 0.1);
        border-color: rgb(242, 124, 17);
    }

    .info-box h3, .warning-box h3 {
        margin-top: 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
    }

    .info-box p, .warning-box p {
        margin: 8px 0;
        color: #d0d0d0;
    }

    /* Table container */
    .table-container {
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        max-height: 600px;
        overflow-y: auto;
        overflow-x: auto;
    }
    
    /* Sticky table header */
    .table-container thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    /* Table styles */
    .prompts-table {
        width: 100%;
        border-collapse: collapse;
    }

    .prompts-table thead {
        background: #1a1a1a;
        border-bottom: 2px solid rgb(242, 124, 17);
    }

    .prompts-table th {
        padding: 15px 12px;
        text-align: left;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.1em;
        font-weight: normal;
        letter-spacing: 1px;
    }

    .prompts-table tbody tr {
        border-bottom: 1px solid #3a3a3a;
        transition: background-color 0.2s ease;
    }

    .prompts-table tbody tr:hover {
        background: #333;
    }

    .prompts-table td {
        padding: 12px;
        color: #e0e0e0;
        vertical-align: top;
    }

    .prompt-key-cell {
        font-family: 'Courier New', monospace;
        color: rgb(100, 149, 237);
        font-size: 0.9em;
        min-width: 180px;
    }

    .prompt-description-cell {
        color: #b0b0b0;
        font-style: italic;
        font-size: 0.9em;
        min-width: 250px;
    }

    .prompt-content-cell {
        max-width: 400px;
    }

    .prompt-preview {
        background: #1a1a1a;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        font-size: 0.85em;
        white-space: pre-wrap;
        max-height: 100px;
        overflow-y: auto;
        color: #ccc;
        line-height: 1.4;
    }

    .prompt-preview.custom {
        border-color: rgb(242, 124, 17);
        background: rgba(242, 124, 17, 0.05);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        text-align: center;
    }

    .status-badge.custom {
        background: rgba(242, 124, 17, 0.2);
        color: rgb(242, 124, 17);
        border: 1px solid rgb(242, 124, 17);
    }

    .status-badge.default {
        background: rgba(100, 149, 237, 0.2);
        color: rgb(100, 149, 237);
        border: 1px solid rgb(100, 149, 237);
    }

    .actions-cell {
        white-space: nowrap;
        text-align: center;
        min-width: 120px;
    }

    .btn {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.2s ease;
        margin: 2px;
    }

    .btn-edit {
        background: rgb(100, 149, 237);
        color: white;
    }

    .btn-edit:hover {
        background: rgb(80, 129, 217);
    }

    .btn-clear {
        background: rgb(242, 124, 17);
        color: white;
    }

    .btn-clear:hover {
        background: rgb(222, 104, 0);
    }

    .btn-save {
        background: #4CAF50;
        color: white;
    }

    .btn-save:hover {
        background: #45a049;
    }

    .btn-cancel {
        background: #888;
        color: white;
    }

    .btn-cancel:hover {
        background: #666;
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.8);
    }

    .modal-content {
        background-color: #2a2a2a;
        margin: 2% auto;
        padding: 0;
        border: 2px solid rgb(242, 124, 17);
        border-radius: 8px;
        width: 90%;
        max-width: 1200px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        padding: 20px;
        background: #1a1a1a;
        border-bottom: 1px solid #4a4a4a;
        border-radius: 6px 6px 0 0;
    }

    .modal-header h2 {
        margin: 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.8em;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        padding: 15px 20px;
        background: #1a1a1a;
        border-top: 1px solid #4a4a4a;
        text-align: right;
        border-radius: 0 0 6px 6px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        line-height: 20px;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: rgb(242, 124, 17);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: rgb(242, 124, 17);
        font-weight: bold;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        background: #1a1a1a;
        border: 1px solid #4a4a4a;
        border-radius: 4px;
        color: #e0e0e0;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.5;
    }

    .form-control:focus {
        outline: none;
        border-color: rgb(242, 124, 17);
    }

    textarea.form-control {
        min-height: 300px;
        resize: vertical;
    }

    .readonly-content {
        background: #252525;
        padding: 15px;
        border-radius: 4px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        color: #999;
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
        line-height: 1.5;
    }

    /* Toast notification */
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #4CAF50;
        color: white;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        z-index: 2000;
        display: none;
        animation: slideIn 0.3s ease;
    }

    .toast-notification.show {
        display: block;
    }

    .toast-notification.error {
        background: #f44336;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .page-header h1 {
            font-size: 1.8em;
        }
        
        .prompts-table {
            font-size: 0.9em;
        }
        
        .modal-content {
            width: 95%;
            margin: 5% auto;
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
        <h1>⚙️ Prompts Manager</h1>
        <p>Manage system and custom prompts used throughout CHIM</p>
    </div>

    <div class="info-box">
        <p>Note: Recommend for advanced users only. Changing prompts can cause unexpected behavior that may worsen the roleplay experience.</p>
        <p><strong>Default Prompt:</strong> System-maintained baseline that updates with CHIM. <strong>Custom Prompt:</strong> Your personalized override that takes precedence when set.</p>
        <p>Click <strong>Edit</strong> to view and modify prompts. Click <strong>Clear</strong> to revert to default.</p>
    </div>

    <?php
    /********************************************************************
     *  3) DISPLAY ALL PROMPTS IN TABLE
     ********************************************************************/
    $query = "
        SELECT prompt_key, default_prompt, custom_prompt, description, 
               created_at, updated_at
        FROM $schema.prompts
        ORDER BY prompt_key ASC
    ";
    $result = pg_query($conn, $query);

    if ($result && pg_num_rows($result) > 0):
    ?>
    
    <div class="table-container">
        <table class="prompts-table">
            <thead>
                <tr>
                    <th>Prompt Key</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Preview</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = pg_fetch_assoc($result)): 
                    $prompt_key = htmlspecialchars($row['prompt_key']);
                    $default_prompt = $row['default_prompt'];
                    $custom_prompt = $row['custom_prompt'];
                    $description = htmlspecialchars($row['description'] ?? '');
                    $is_custom = !empty($custom_prompt);
                    $active_prompt = $is_custom ? $custom_prompt : $default_prompt;
                    $preview = strlen($active_prompt) > 150 ? substr($active_prompt, 0, 150) . '...' : $active_prompt;
                ?>
                <tr>
                    <td class="prompt-key-cell">
                        <code><?php echo $prompt_key; ?></code>
                    </td>
                    <td class="prompt-description-cell">
                        <?php echo $description; ?>
                    </td>
                    <td>
                        <?php if ($is_custom): ?>
                            <span class="status-badge custom">🎨 Custom</span>
                        <?php else: ?>
                            <span class="status-badge default">📋 Default</span>
                        <?php endif; ?>
                    </td>
                    <td class="prompt-content-cell">
                        <div class="prompt-preview <?php echo $is_custom ? 'custom' : ''; ?>">
                            <?php echo htmlspecialchars($preview); ?>
                        </div>
                    </td>
                    <td class="actions-cell">
                        <button class="btn btn-edit" onclick="openEditModal('<?php echo $prompt_key; ?>')">
                            ✏️ Edit
                        </button>
                        <?php if ($is_custom): ?>
                        <button class="btn btn-clear" onclick="clearCustomPrompt('<?php echo $prompt_key; ?>')">
                            🔄 Clear
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
        <div class="warning-box">
            <h3>⚠️ No Prompts Found</h3>
            <p>The prompts table appears to be empty. Please run the database migration to initialize the prompts system.</p>
        </div>
    <?php endif; ?>

</main>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>✏️ Edit Prompt: <span id="modalPromptKey"></span></h2>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>📝 Description & File Location</label>
                <p id="modalDescription" style="color: #b0b0b0;"></p>
            </div>
            
            <div class="form-group">
                <label>📋 Default Prompt (Read-Only)</label>
                <div id="modalDefaultPrompt" class="readonly-content"></div>
            </div>
            
            <div class="form-group">
                <label>🎨 Custom Prompt (Optional - Leave empty to use default)</label>
                <textarea id="modalCustomPrompt" class="form-control" placeholder="Enter your custom prompt here, or leave empty to use the default prompt..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn btn-save" onclick="savePrompt()">💾 Save Custom Prompt</button>
        </div>
    </div>
</div>

<script>
// Define webRoot for JavaScript
var webRoot = '<?php echo $webRoot; ?>';

// Store prompt data
var promptsData = {};

<?php
// Generate JavaScript data object
$result = pg_query($conn, $query);
if ($result) {
    echo "promptsData = {\n";
    $first = true;
    while ($row = pg_fetch_assoc($result)) {
        if (!$first) echo ",\n";
        $first = false;
        echo "    '" . $row['prompt_key'] . "': {\n";
        echo "        default_prompt: " . json_encode($row['default_prompt']) . ",\n";
        echo "        custom_prompt: " . json_encode($row['custom_prompt']) . ",\n";
        echo "        description: " . json_encode($row['description']) . "\n";
        echo "    }";
    }
    echo "\n};\n";
}
?>

function openEditModal(promptKey) {
    const data = promptsData[promptKey];
    if (!data) return;
    
    document.getElementById('modalPromptKey').textContent = promptKey;
    document.getElementById('modalDescription').innerHTML = data.description || 'No description available.';
    document.getElementById('modalDefaultPrompt').textContent = data.default_prompt;
    document.getElementById('modalCustomPrompt').value = data.custom_prompt || '';
    
    document.getElementById('editModal').style.display = 'block';
    
    // Store current prompt key
    document.getElementById('editModal').dataset.promptKey = promptKey;
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function savePrompt() {
    const promptKey = document.getElementById('editModal').dataset.promptKey;
    const customPrompt = document.getElementById('modalCustomPrompt').value;
    
    const formData = new FormData();
    formData.append('action', 'update_custom');
    formData.append('prompt_key', promptKey);
    formData.append('custom_prompt', customPrompt);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update the local data store
            promptsData[promptKey].custom_prompt = customPrompt || null;
            
            // Update the table row
            updateTableRow(promptKey);
            
            // Close the modal
            closeEditModal();
            
            showToast('Prompt saved successfully!', 'success');
        } else {
            showToast('Error saving prompt: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

function updateTableRow(promptKey) {
    const data = promptsData[promptKey];
    const isCustom = !!(data.custom_prompt && data.custom_prompt.trim());
    const activePrompt = isCustom ? data.custom_prompt : data.default_prompt;
    const preview = activePrompt.length > 150 ? activePrompt.substring(0, 150) + '...' : activePrompt;
    
    // Find the table row
    const rows = document.querySelectorAll('.prompts-table tbody tr');
    rows.forEach(row => {
        const codeElement = row.querySelector('.prompt-key-cell code');
        if (codeElement && codeElement.textContent === promptKey) {
            // Update status badge
            const statusCell = row.cells[2];
            if (isCustom) {
                statusCell.innerHTML = '<span class="status-badge custom">🎨 Custom</span>';
            } else {
                statusCell.innerHTML = '<span class="status-badge default">📋 Default</span>';
            }
            
            // Update preview
            const previewCell = row.cells[3];
            const previewDiv = previewCell.querySelector('.prompt-preview');
            if (previewDiv) {
                previewDiv.textContent = preview;
                if (isCustom) {
                    previewDiv.classList.add('custom');
                } else {
                    previewDiv.classList.remove('custom');
                }
            }
            
            // Update actions cell
            const actionsCell = row.cells[4];
            if (isCustom) {
                actionsCell.innerHTML = `
                    <button class="btn btn-edit" onclick="openEditModal('${promptKey}')">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-clear" onclick="clearCustomPrompt('${promptKey}')">
                        🔄 Clear
                    </button>
                `;
            } else {
                actionsCell.innerHTML = `
                    <button class="btn btn-edit" onclick="openEditModal('${promptKey}')">
                        ✏️ Edit
                    </button>
                `;
            }
        }
    });
}

function clearCustomPrompt(promptKey) {
    if (!confirm('Are you sure you want to clear the custom prompt and revert to the default? This cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'clear_custom');
    formData.append('prompt_key', promptKey);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text(); // Changed to text since this endpoint doesn't return JSON yet
    })
    .then(() => {
        // Update the local data store
        promptsData[promptKey].custom_prompt = null;
        
        // Update the table row
        updateTableRow(promptKey);
        
        // If modal is open for this prompt, update it
        const modal = document.getElementById('editModal');
        if (modal.style.display === 'block' && modal.dataset.promptKey === promptKey) {
            document.getElementById('modalCustomPrompt').value = '';
        }
        
        showToast('Custom prompt cleared. Reverted to default.', 'success');
    })
    .catch(error => {
        console.error('Clear error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    
    toast.className = 'toast-notification show';
    if (type === 'error') {
        toast.classList.add('error');
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.remove('error');
    }, 5000);
}

// Show PHP message if exists
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode(strip_tags($message)); ?>);
});
<?php endif; ?>

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
