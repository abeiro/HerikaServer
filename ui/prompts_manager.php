<?php
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

/********************************************************************
 *  1) UPDATE CUSTOM PROMPT
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_custom') {
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
            if ($custom_prompt === null) {
                $message .= "<p>Custom prompt cleared. Using default prompt for <strong>$prompt_key</strong>.</p>";
            } else {
                $message .= "<p>Custom prompt updated successfully for <strong>$prompt_key</strong>.</p>";
            }
        } else {
            $message .= "<p>Error updating prompt: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>No prompt key specified.</p>";
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
            $message .= "<p>Custom prompt cleared for <strong>$prompt_key</strong>. Now using default prompt.</p>";
        } else {
            $message .= "<p>Error clearing custom prompt: " . pg_last_error($conn) . "</p>";
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
        margin-bottom: 15px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .prompt-card {
        background: #2a2a2a;
        padding: 25px;
        margin-bottom: 30px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        transition: border-color 0.3s ease;
    }

    .prompt-card:hover {
        border-color: rgb(242, 124, 17);
    }

    .prompt-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #4a4a4a;
    }

    .prompt-title {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        font-size: 1.5em;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .prompt-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.85em;
        font-weight: bold;
    }

    .status-custom {
        background: rgba(242, 124, 17, 0.2);
        color: rgb(242, 124, 17);
        border: 1px solid rgb(242, 124, 17);
    }

    .status-default {
        background: rgba(100, 149, 237, 0.2);
        color: rgb(100, 149, 237);
        border: 1px solid rgb(100, 149, 237);
    }

    .prompt-description {
        color: #b0b0b0;
        margin-bottom: 20px;
        font-style: italic;
    }

    .prompt-section {
        margin-bottom: 20px;
    }

    .prompt-section-title {
        font-weight: bold;
        color: rgb(242, 124, 17);
        margin-bottom: 8px;
        font-size: 1.1em;
    }

    .prompt-content {
        background: #1a1a1a;
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        white-space: pre-wrap;
        word-wrap: break-word;
        color: #e0e0e0;
        line-height: 1.6;
        max-height: 300px;
        overflow-y: auto;
    }

    .prompt-content.readonly {
        background: #252525;
        color: #999;
    }

    .prompt-textarea {
        width: 100%;
        min-height: 200px;
        background: #1a1a1a;
        color: #e0e0e0;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        padding: 15px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.6;
        resize: vertical;
    }

    .prompt-textarea:focus {
        outline: none;
        border-color: rgb(242, 124, 17);
        box-shadow: 0 0 8px rgba(242, 124, 17, 0.3);
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .info-box {
        background: rgba(100, 149, 237, 0.1);
        border-left: 4px solid rgb(100, 149, 237);
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
    }

    .info-box h3 {
        color: rgb(100, 149, 237);
        margin-bottom: 10px;
        font-size: 1.1em;
    }

    .warning-box {
        background: rgba(255, 193, 7, 0.1);
        border-left: 4px solid rgb(255, 193, 7);
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
    }

    .warning-box h3 {
        color: rgb(255, 193, 7);
        margin-bottom: 10px;
        font-size: 1.1em;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .page-header h1 {
            font-size: 1.5em;
        }
        
        .prompt-card {
            padding: 15px;
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
        <h1>
            <span>⚙️ Prompts Manager</span>
        </h1>
        <p>Manage system and custom prompts used throughout CHIM.</p>
    </div>

    <div class="info-box">
        <h3>ℹ️ How Prompts Work</h3>
        <p><strong>Default Prompt:</strong> The baseline prompt maintained by system updates. This is read-only and will be updated when you update CHIM.</p>
        <p><strong>Custom Prompt:</strong> Your personalized version. When set, this takes precedence over the default prompt. Leave empty to use the default.</p>
        <p><strong>Fallback:</strong> If the database is unavailable, the system will use hardcoded fallback prompts to ensure continuous operation.</p>
    </div>

    <div class="warning-box">
        <h3>⚠️ Important Notes</h3>
        <p>• Editing prompts can significantly affect AI behavior. Test changes carefully.</p>
        <p>• Clearing a custom prompt will revert to the default prompt immediately.</p>
        <p>• Default prompts may be updated in future CHIM updates without overwriting your custom prompts.</p>
    </div>

    <?php
    /********************************************************************
     *  3) DISPLAY ALL PROMPTS
     ********************************************************************/
    $query = "
        SELECT prompt_key, default_prompt, custom_prompt, description, 
               created_at, updated_at
        FROM $schema.prompts
        ORDER BY prompt_key ASC
    ";
    $result = pg_query($conn, $query);

    if ($result) {
        $promptCount = 0;
        while ($row = pg_fetch_assoc($result)) {
            $promptCount++;
            $prompt_key = htmlspecialchars($row['prompt_key']);
            $default_prompt = $row['default_prompt'];
            $custom_prompt = $row['custom_prompt'];
            $description = htmlspecialchars($row['description'] ?? '');
            $is_custom = !empty($custom_prompt);
            $active_prompt = $is_custom ? $custom_prompt : $default_prompt;
            
            // Format the prompt key for display
            $display_key = str_replace('_', ' ', ucwords($prompt_key, '_'));
            
            echo '<div class="prompt-card" id="prompt-' . $prompt_key . '">';
            
            // Header with title and status
            echo '<div class="prompt-header">';
            echo '<div class="prompt-title">' . $display_key . '</div>';
            if ($is_custom) {
                echo '<div class="prompt-status status-custom">🎨 Custom Active</div>';
            } else {
                echo '<div class="prompt-status status-default">📋 Using Default</div>';
            }
            echo '</div>';
            
            // Description
            if (!empty($description)) {
                echo '<div class="prompt-description">' . $description . '</div>';
            }
            
            // Display default prompt (read-only)
            echo '<div class="prompt-section">';
            echo '<div class="prompt-section-title">📋 Default Prompt (Read-Only)</div>';
            echo '<div class="prompt-content readonly">' . htmlspecialchars($default_prompt) . '</div>';
            echo '</div>';
            
            // Edit form for custom prompt
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="action" value="update_custom">';
            echo '<input type="hidden" name="prompt_key" value="' . $prompt_key . '">';
            
            echo '<div class="prompt-section">';
            echo '<div class="prompt-section-title">🎨 Custom Prompt (Optional)</div>';
            echo '<textarea name="custom_prompt" class="prompt-textarea" placeholder="Leave empty to use the default prompt...">' . htmlspecialchars($custom_prompt ?? '') . '</textarea>';
            echo '</div>';
            
            echo '<div class="button-group">';
            echo '<button type="submit" class="action-button upload-csv">💾 Save Custom Prompt</button>';
            
            if ($is_custom) {
                echo '<button type="button" onclick="clearCustomPrompt(\'' . $prompt_key . '\')" class="btn-danger">🔄 Clear Custom (Revert to Default)</button>';
            }
            
            echo '</div>';
            echo '</form>';
            
            // Metadata
            echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #3a3a3a; font-size: 0.85em; color: #888;">';
            echo '<strong>Prompt Key:</strong> <code>' . $prompt_key . '</code> | ';
            echo '<strong>Last Updated:</strong> ' . date('Y-m-d H:i:s', strtotime($row['updated_at']));
            echo '</div>';
            
            echo '</div>'; // End prompt-card
        }
        
        if ($promptCount === 0) {
            echo '<div class="warning-box">';
            echo '<h3>⚠️ No Prompts Found</h3>';
            echo '<p>The prompts table appears to be empty. Please run the database migration to initialize the prompts system.</p>';
            echo '</div>';
        }
    } else {
        echo '<div class="warning-box">';
        echo '<h3>⚠️ Database Error</h3>';
        echo '<p>Error fetching prompts: ' . htmlspecialchars(pg_last_error($conn)) . '</p>';
        echo '</div>';
    }
    ?>

</main>

<script>
// Define webRoot for JavaScript
var webRoot = '<?php echo $webRoot; ?>';

function clearCustomPrompt(promptKey) {
    if (confirm('Are you sure you want to clear the custom prompt and revert to the default? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '#prompt-' + promptKey;
        form.innerHTML = `
            <input type="hidden" name="action" value="clear_custom">
            <input type="hidden" name="prompt_key" value="${promptKey}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

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

// Auto-save warning on textarea changes
document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('.prompt-textarea');
    textareas.forEach(textarea => {
        let originalValue = textarea.value;
        textarea.addEventListener('change', function() {
            if (this.value !== originalValue) {
                showToast('Don\'t forget to save your changes!', 3000);
            }
        });
    });
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

