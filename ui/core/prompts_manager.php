<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }

// Run database migrations to ensure prompts table exists
try {
    require_once($enginePath . "debug" . DIRECTORY_SEPARATOR . "db_updates.php");
} catch (Exception $e) {
    error_log("Error running db_updates: " . $e->getMessage());
}

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "prompts.class.php");

// Determine web root (match other core pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$promptsManager = new Prompts();

// Check if table exists and has data
$tableExists = $promptsManager->tableExists();
$debugInfo = [];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($action) {
            case 'update':
                $name = $_POST['name'] ?? '';
                $cue = $_POST['cue'] ?? '';
                
                if (empty($name) || empty($cue)) {
                    $response['message'] = 'Name and cue are required';
                } else {
                    $success = $promptsManager->updatePrompt($name, $cue);
                    $response['success'] = $success;
                    $response['message'] = $success ? 'Prompt updated successfully' : 'Failed to update prompt';
                }
                break;
                
            case 'reset':
                $name = $_POST['name'] ?? '';
                
                if (empty($name)) {
                    $response['message'] = 'Name is required';
                } else {
                    $success = $promptsManager->resetToDefault($name);
                    if ($success) {
                        $prompt = $promptsManager->getPrompt($name);
                        $response['success'] = true;
                        $response['message'] = 'Prompt reset to default';
                        $response['cue'] = $prompt['cue'];
                    } else {
                        $response['message'] = 'Failed to reset prompt';
                    }
                }
                break;
                
            default:
                $response['message'] = 'Unknown action';
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Site chrome (only if not embedded)
$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
if (!$isEmbed) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "../profile_loader.php");
    $TITLE = "📝 CHIM - Prompts Manager";
    ob_start();
    include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/head.html");
}

// Get all prompts
$prompts = $promptsManager->getAllPrompts();

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}

main { 
    padding: 40px 10px 10px;
    <?php if ($isEmbed): ?>
    padding-top: 20px;
    <?php endif; ?>
}

h1.page-title {
    margin: 0 0 20px 0;
    font-family: 'MagicCards', sans-serif;
    font-size: 2.5em;
    color: rgb(212, 94, 0);
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    letter-spacing: 2px;
}

.prompts-container {
    background: #2a2a2a;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.search-box {
    width: 100%;
    max-width: 500px;
    padding: 10px 15px;
    margin-bottom: 20px;
    background: #1a1a1a;
    border: 1px solid #4a4a4a;
    border-radius: 5px;
    color: #f8f9fa;
    font-size: 1em;
}

.search-box:focus {
    outline: none;
    border-color: rgb(212, 94, 0);
}

.prompts-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.prompts-table thead {
    background: #1a1a1a;
}

.prompts-table th {
    padding: 12px 15px;
    text-align: left;
    font-weight: bold;
    color: rgb(212, 94, 0);
    border-bottom: 2px solid rgb(212, 94, 0);
}

.prompts-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #3a3a3a;
    vertical-align: top;
}

.prompts-table tr:hover {
    background: #333;
}

.prompt-name {
    font-family: 'Courier New', monospace;
    color: #9fb1c9;
    font-weight: bold;
    min-width: 150px;
}

.prompt-cue {
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    color: #f8f9fa;
    max-width: 600px;
}

.prompt-cue-multiline {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.prompt-description {
    color: #888;
    font-style: italic;
    font-size: 0.9em;
}

.prompt-actions {
    min-width: 180px;
    text-align: right;
}

.btn {
    padding: 6px 12px;
    margin: 0 2px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9em;
    transition: all 0.3s;
}

.btn-edit {
    background: #4a90e2;
    color: white;
}

.btn-edit:hover {
    background: #357abd;
}

.btn-reset {
    background: #e74c3c;
    color: white;
}

.btn-reset:hover {
    background: #c0392b;
}

.btn-save {
    background: #27ae60;
    color: white;
}

.btn-save:hover {
    background: #229954;
}

.btn-cancel {
    background: #7f8c8d;
    color: white;
}

.btn-cancel:hover {
    background: #5f6c6d;
}

.edit-mode {
    background: #1a1a1a;
}

.edit-textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    background: #0a0a0a;
    border: 1px solid rgb(212, 94, 0);
    border-radius: 4px;
    color: #f8f9fa;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    resize: vertical;
}

.edit-textarea:focus {
    outline: none;
    border-color: rgb(212, 94, 0);
    box-shadow: 0 0 5px rgba(212, 94, 0, 0.5);
}

.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    background: #27ae60;
    color: white;
    border-radius: 5px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    z-index: 10000;
    opacity: 0;
    transform: translateX(400px);
    transition: all 0.3s;
}

.toast-notification.show {
    opacity: 1;
    transform: translateX(0);
}

.toast-notification.error {
    background: #e74c3c;
}

.info-box {
    background: #1a3a5a;
    border-left: 4px solid rgb(212, 94, 0);
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info-box p {
    margin: 5px 0;
    color: #f8f9fa;
}

.info-box strong {
    color: rgb(212, 94, 0);
}

.json-badge {
    display: inline-block;
    background: #4a90e2;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.8em;
    margin-left: 8px;
}
</style>

<main>
    <div id="toast" class="toast-notification"></div>
    
    <?php if (!$isEmbed): ?>
    <h1 class="page-title">📝 Prompts Manager</h1>
    <?php endif; ?>
    
    <?php if (!empty($debugInfo)): ?>
    <div class="info-box" style="background: #5a3a1a; border-left-color: #e74c3c;">
        <?php foreach ($debugInfo as $info): ?>
        <p style="color: #ffcc00;">⚠️ <?php echo htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endforeach; ?>
        <?php if (!$tableExists): ?>
        <p><strong>Solution:</strong> Restart the CHIM server. The database migration will run automatically and create the prompts table.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="prompts-container">
        <input type="text" class="search-box" id="searchBox" placeholder="Search prompts by name or description...">
        
        <table class="prompts-table">
            <thead>
                <tr>
                    <th>Prompt Name</th>
                    <th>Cue Text</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="promptsTableBody">
                <?php foreach ($prompts as $prompt): 
                    $cueDisplay = $prompt['cue'];
                    $isJson = false;
                    
                    // Check if it's JSON
                    if (is_string($cueDisplay) && strlen($cueDisplay) > 0 && ($cueDisplay[0] === '[' || $cueDisplay[0] === '{')) {
                        $decoded = json_decode($cueDisplay, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $isJson = true;
                            $cueDisplay = count($decoded) . " variations:\n" . implode("\n", array_map(function($item, $idx) {
                                return ($idx + 1) . ". " . substr($item, 0, 80) . (strlen($item) > 80 ? '...' : '');
                            }, $decoded, array_keys($decoded)));
                        }
                    }
                    
                    // Truncate for display
                    $cuePreview = strlen($cueDisplay) > 150 ? substr($cueDisplay, 0, 150) . '...' : $cueDisplay;
                ?>
                <tr data-prompt-name="<?php echo htmlspecialchars($prompt['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <td class="prompt-name">
                        <?php echo htmlspecialchars($prompt['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($isJson): ?><span class="json-badge">JSON Array</span><?php endif; ?>
                    </td>
                    <td class="prompt-cue">
                        <div class="view-mode">
                            <span class="prompt-cue-multiline"><?php echo htmlspecialchars($cuePreview, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="edit-mode" style="display: none;">
                            <textarea class="edit-textarea" data-original="<?php echo htmlspecialchars($prompt['cue'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($prompt['cue'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </td>
                    <td class="prompt-description">
                        <?php echo htmlspecialchars($prompt['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="prompt-actions">
                        <div class="view-mode">
                            <button class="btn btn-edit" onclick="editPrompt(this)">Edit</button>
                            <button class="btn btn-reset" onclick="resetPrompt(this)">Reset</button>
                        </div>
                        <div class="edit-mode" style="display: none;">
                            <button class="btn btn-save" onclick="savePrompt(this)">Save</button>
                            <button class="btn btn-cancel" onclick="cancelEdit(this)">Cancel</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
// Search functionality
document.getElementById('searchBox').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#promptsTableBody tr');
    
    rows.forEach(row => {
        const name = row.querySelector('.prompt-name').textContent.toLowerCase();
        const description = row.querySelector('.prompt-description').textContent.toLowerCase();
        const cue = row.querySelector('.prompt-cue .view-mode').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || description.includes(searchTerm) || cue.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Toast notification
function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast-notification show' + (isError ? ' error' : '');
    
    setTimeout(() => {
        toast.className = 'toast-notification';
    }, 3000);
}

// Edit prompt
function editPrompt(btn) {
    const row = btn.closest('tr');
    const viewModes = row.querySelectorAll('.view-mode');
    const editModes = row.querySelectorAll('.edit-mode');
    
    viewModes.forEach(el => el.style.display = 'none');
    editModes.forEach(el => el.style.display = 'block');
    
    row.classList.add('edit-mode');
}

// Cancel edit
function cancelEdit(btn) {
    const row = btn.closest('tr');
    const viewModes = row.querySelectorAll('.view-mode');
    const editModes = row.querySelectorAll('.edit-mode');
    const textarea = row.querySelector('.edit-textarea');
    
    // Restore original value
    textarea.value = textarea.getAttribute('data-original');
    
    viewModes.forEach(el => el.style.display = '');
    editModes.forEach(el => el.style.display = 'none');
    
    row.classList.remove('edit-mode');
}

// Save prompt
function savePrompt(btn) {
    const row = btn.closest('tr');
    const name = row.getAttribute('data-prompt-name');
    const textarea = row.querySelector('.edit-textarea');
    const cue = textarea.value;
    
    // Disable button during save
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update&name=' + encodeURIComponent(name) + '&cue=' + encodeURIComponent(cue)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            
            // Update the stored original value
            textarea.setAttribute('data-original', cue);
            
            // Update the view mode display
            const viewSpan = row.querySelector('.prompt-cue .view-mode span');
            let displayText = cue;
            
            // Check if it's JSON and format accordingly
            if (cue.trim().startsWith('[')) {
                try {
                    const decoded = JSON.parse(cue);
                    if (Array.isArray(decoded)) {
                        displayText = decoded.length + ' variations:\n' + decoded.map((item, idx) => 
                            (idx + 1) + '. ' + item.substring(0, 80) + (item.length > 80 ? '...' : '')
                        ).join('\n');
                    }
                } catch (e) {
                    // Not valid JSON, use as-is
                }
            }
            
            if (displayText.length > 150) {
                displayText = displayText.substring(0, 150) + '...';
            }
            
            viewSpan.textContent = displayText;
            
            // Exit edit mode
            cancelEdit(btn);
        } else {
            showToast(data.message, true);
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, true);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Save';
    });
}

// Reset prompt
function resetPrompt(btn) {
    const row = btn.closest('tr');
    const name = row.getAttribute('data-prompt-name');
    
    if (!confirm('Are you sure you want to reset "' + name + '" to its default value? This cannot be undone.')) {
        return;
    }
    
    btn.disabled = true;
    btn.textContent = 'Resetting...';
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=reset&name=' + encodeURIComponent(name)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            
            // Update the textarea and view with the reset value
            const textarea = row.querySelector('.edit-textarea');
            const viewSpan = row.querySelector('.prompt-cue .view-mode span');
            
            textarea.value = data.cue;
            textarea.setAttribute('data-original', data.cue);
            
            let displayText = data.cue;
            
            // Check if it's JSON and format accordingly
            if (data.cue.trim().startsWith('[')) {
                try {
                    const decoded = JSON.parse(data.cue);
                    if (Array.isArray(decoded)) {
                        displayText = decoded.length + ' variations:\n' + decoded.map((item, idx) => 
                            (idx + 1) + '. ' + item.substring(0, 80) + (item.length > 80 ? '...' : '')
                        ).join('\n');
                    }
                } catch (e) {
                    // Not valid JSON, use as-is
                }
            }
            
            if (displayText.length > 150) {
                displayText = displayText.substring(0, 150) + '...';
            }
            
            viewSpan.textContent = displayText;
        } else {
            showToast(data.message, true);
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, true);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Reset';
    });
}
</script>

<?php
if (!$isEmbed) {
    include(__DIR__ . DIRECTORY_SEPARATOR . "../tmpl/footer.html");
    $buffer = ob_get_contents();
    ob_end_clean();
    $title = $TITLE;
    $buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
    echo $buffer;
}
?>

