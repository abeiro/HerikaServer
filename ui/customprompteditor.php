<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📝CHIM - Custom Prompts";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Prevent browser caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Define the file path
$file_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . 'prompts_custom.php';

// Initialize content variable
$content = '';

// Check if the file exists
if (!file_exists($file_path)) {
    // Create a minimal file if none exists
    $content = "<?php\n?>";
    file_put_contents($file_path, $content);
} else {
    // Read the contents of the file
    $content = file_get_contents($file_path);
}

// Initialize message variable
$message = '';
// Initialize prompts_content variable
$prompts_content = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If 'content' is in POST data, update $content
    if (isset($_POST['content'])) {
        $content = $_POST['content'];
    }

    // Save button
    if (isset($_POST['save'])) {
        // Save the new content back to the file
        if (file_put_contents($file_path, $content) !== false) {
            $message = '<div class="success-message">File saved successfully.</div>';
        } else {
            $message = '<div class="error-message">Error saving the file.</div>';
        }
    }
    // Validate button
    elseif (isset($_POST['validate'])) {
        // Perform validation
        $validation_steps = [];
        $errors = [];

        // Check if it starts with <?php
        if (strpos($content, '<?php') !== 0) {
            $errors[] = 'The file must start with <?php';
        } else {
            $validation_steps[] = 'The file starts with the correct PHP syntax';
        }

        if (substr(trim($content), -2) !== '?>') {
            $errors[] = 'The file must end with ?>';
        } else {
            $validation_steps[] = 'The file ends with ?>';
        }

        if (empty($errors)) {
            // Save the content to a temporary file
            $tmpfname = tempnam(sys_get_temp_dir(), "phptest");
            file_put_contents($tmpfname, $content);

            // Execute PHP lint check (php -l)
            $output = [];
            $return_var = 0;
            exec("php -l " . escapeshellarg($tmpfname), $output, $return_var);

            // Remove the temporary file
            unlink($tmpfname);

            if ($return_var !== 0) {
                $errors[] = 'JSON syntax error detected. Check for errors below (the little red boxes).';
            } else {
                $validation_steps[] = 'JSON code syntax is valid';
            }
        }

        if (empty($errors)) {
            $message = '<div class="success-message">Validation successful. The following checks passed:<br>' . 
                      implode('<br>', $validation_steps) . '</div>';
        } else {
            $message = '<div class="error-message">Validation failed:<br>' . 
                      implode('<br>', $errors) . '</div>';
        }
    }
    // Reset button
    elseif (isset($_POST['reset'])) {
        // Reset the content to basic configuration
        $content = "<?php\n?>";
        
        // Save the new content back to the file
        if (file_put_contents($file_path, $content) !== false) {
            // Instead of reloading, redirect to the same page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $message = '<div class="error-message">Error resetting the file.</div>';
        }
    }
    // View prompts button
    elseif (isset($_POST['view_prompts'])) {
        // Handle view_prompts
        $prompts_file_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . 'prompts.php';
        if (file_exists($prompts_file_path)) {
            $prompts_content = file_get_contents($prompts_file_path);
        } else {
            $message = '<div class="error-message">prompts.php file not found.</div>';
        }
    }

}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <title>📝CHIM - Custom Prompts</title>
    <link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
    <style>
        /* Override main container styles */
        main {
            padding-top: 160px; /* Space for navbar */
            padding-bottom: 40px; /* Reduced space for footer */
            padding-left: 10px;
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

        /* ACE Editor specific styles */
        #editor {
            height: 700px;
            background-color: #1e1e1e;
            margin-top: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
            width: 600px;
        }

        /* Code block styling */
        pre {
            background-color: #1e1e1e;
            border: 1px solid #555555;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        pre code {
            border: none;
            padding: 0;
            color: #f8f9fa;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
        }

        /* Example section styling */
        .example-section {
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #4a4a4a;
            margin: 20px 0;
            width: 1200px;
        }

        /* Success message styling */
        .success-message {
            color: #32CD32;
            font-weight: bold;
        }

        /* Error message styling */
        .error-message {
            color: red;
            font-weight: bold;
        }

        /* Form container width */


        /* Readonly textarea for prompts.php view */
        textarea[readonly] {
            height: 500px;
            background-color: #1e1e1e;
            color: #f8f9fa;
            border: 1px solid #555555;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            margin-top: 20px;
            display: none; /* Hidden by default */
        }
    </style>
</head>
<main> 
<body>
    <div class="indent5">
        <h1>📝CHIM Custom Prompt Editor</h1>

        <div id="toast" class="toast-notification">
            <span class="message"></span>
        </div>
        <h4 style="color: yellow;"><b>Warning:</b> For Advanced Users Only! Misconfigurations can cause CHIM to break in unexpected ways...</h4>
        <p>
            By making your own <b>prompts_custom.php</b> file you can make edits to how AI NPCs respond to triggered events.
        </p>
        <p>
            The contents of this file overwrites whatever is in the standard <code>prompts.php</code>, meaning you can safely make edits to it without breaking it when CHIM updates.
        </p>
        <p>
            <b>The file must be in valid PHP format!</b> Make sure it starts with <code>&lt;?php</code> and ends with <code>?&gt;</code>.
        </p>

        <!-- Main form for editing and saving the prompts_custom.php file -->
        <form method="post" onsubmit="return syncAceContent()">
            <label for="editor">prompts_custom.php Editor:</label>
            <div id="editor"></div>
            <textarea name="content" id="hiddenContent" style="display:none;"></textarea>
            <div class="button-group">
                <input type="submit" name="save" value="Save" class="action-button upload-csv">
                <input type="submit" name="validate" value="Validate" class="action-button edit">
                <input type="submit" name="reset" value="Reset" class="action-button btn-danger" onclick="return confirmReset()">
            </div>
            <p>
            Click the <b>Validate</b> button to confirm the file is in proper format. Then click <b>Save</b>. 
        </p>
        <p>
            <i>Use an LLM chatbot if you need help fixing syntax errors.</i>
        </p>
        </form>

        <!-- Form to view prompts.php -->
        <form method="post" id="viewPromptsForm">
            <input type="submit" name="view_prompts" value="View prompts.php file" class="action-button download-csv" id="viewPromptsBtn">
        </form>

        <!-- Container for prompts.php content -->
        <div id="promptsContainer" style="max-width: 1200px;">
            <?php if (isset($prompts_content) && !empty($prompts_content)): ?>
                <textarea readonly id="promptsViewer"><?php echo htmlspecialchars($prompts_content); ?></textarea>
            <?php endif; ?>
        </div>
        <br>
        <div class="example-section">
            <h3>How to Adjust the AI Prompts</h3>
            <p>We have this in <b>prompts.php</b></p>
            <pre><code class="language-php">
    "combatend" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} comments about the last combat encounter) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} laughs at {$GLOBALS["PLAYER_NAME"]}'s combat style) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} comments about {$GLOBALS["PLAYER_NAME"]}'s weapons) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} admires {$GLOBALS["PLAYER_NAME"]}'s combat style) $TEMPLATE_DIALOG"
        ],
        "extra" => [
            "mood" => "whispering",
            "force_tokens_max" => "50",
            "dontuse" => (time() % 5 != 0) // 20% chance
        ]
    ],</code></pre>

            <p>We can edit the <b>prompts_custom.php</b> with this new definition:</p>
            <pre><code class="language-php">
// These are comments, you do not need to add them to the custom prompt file.
// $TEMPLATE_DIALOG is in prompts.php and is the standard cue.
// Cue is the last instruction sent to the LLM.
// If cue is an array, a random cue will be chosen from that array.

// You can disable some events by adding this named key ["extra"]["dontuse"], so:
// * "dontuse" = true -> will disable the event
// * "dontuse" = (time() % 5 != 0) -> will disable the event 4 out of 5 times (20% chance)
// * "force_tokens_max" => will change token limit for this event
// * "mood" => will force mood
// End of comments.

$PROMPTS["combatend"] = [
    "cue" => [
        "({$GLOBALS["HERIKA_NAME"]} boasts that they have defeated all the enemies.) $TEMPLATE_DIALOG"
    ],
    "extra" => [
        "mood" => "whispering",
        "force_tokens_max" => "50",
        "dontuse" => (time() % 5 != 0) // 20% chance
    ]
],</code></pre>
        </div>

        <h3>Custom Prompt Examples:</h3>
        <div class="example-section">
            <p><b>Make diary entries more emotional and private (credit to Larrek)</b></p>
            <pre><code class="language-php">
$PROMPTS["diary"]=[ 
    "cue"=>["Please write a short summary of {$GLOBALS["PLAYER_NAME"]} and {$GLOBALS["HERIKA_NAME"]}'s last dialogues and events written above into {$GLOBALS["HERIKA_NAME"]}'s diary, add {$GLOBALS["HERIKA_NAME"]}'s emotions and private thoughts on people and events . WRITE AS IF YOU WERE {$GLOBALS["HERIKA_NAME"]}."],
    "extra"=>["force_tokens_max"=>0]
];</code></pre>
        </div>
    </div>

    <!-- Include Ace Editor scripts -->
    <script src="https://cdn.jsdelivr.net/npm/ace-builds@1.5.0/src-min-noconflict/ace.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ace-builds@1.5.0/src-min-noconflict/ext-language_tools.js"></script>
    <script>
    let editor;
    let isPromptsVisible = <?php echo isset($prompts_content) ? 'true' : 'false' ?>;

    // Confirmation function for reset
    function confirmReset() {
        return confirm("Warning: All custom prompts configurations will be lost. Are you sure you want to continue?");
    }

    // Toast notification function
    function showToast(message, duration = 5000) {
        const toast = document.getElementById('toast');
        const messageSpan = toast.querySelector('.message');
        messageSpan.innerHTML = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    window.addEventListener('DOMContentLoaded', function() {
        editor = ace.edit("editor", {
            mode: "ace/mode/php",
            theme: "ace/theme/monokai",
            wrap: true,
            autoScrollEditorIntoView: true,
        });

        editor.setValue(<?php echo json_encode($content); ?>, -1);

        // Initialize prompts viewer state
        const promptsViewer = document.getElementById('promptsViewer');
        const viewPromptsBtn = document.getElementById('viewPromptsBtn');
        const viewPromptsForm = document.getElementById('viewPromptsForm');

        if (promptsViewer) {
            promptsViewer.style.display = isPromptsVisible ? 'block' : 'none';
            if (viewPromptsBtn) {
                viewPromptsBtn.value = isPromptsVisible ? 'Hide prompts.php file' : 'View prompts.php file';
            }
        }

        // Show toast message if there is one
        <?php if (!empty($message)): ?>
        showToast(<?php echo json_encode($message); ?>);
        <?php endif; ?>

        // Add event listener to the form
        if (viewPromptsForm) {
            viewPromptsForm.addEventListener('submit', function(e) {
                if (promptsViewer && promptsViewer.textContent) {
                    // If we already have content, just toggle visibility
                    e.preventDefault();
                    isPromptsVisible = !isPromptsVisible;
                    promptsViewer.style.display = isPromptsVisible ? 'block' : 'none';
                    viewPromptsBtn.value = isPromptsVisible ? 'Hide prompts.php file' : 'View prompts.php file';
                }
                // Otherwise, let the form submit to load content
            });
        }
    });

    function syncAceContent() {
        const code = editor.getValue().trim();
        const phpStartTag = "<?php echo '<?php'; ?>";
        const phpEndTag = "<?php echo '?>'; ?>";

        if (!code.startsWith(phpStartTag)) {
            showToast('<div class="error-message">Error: File must start with &lt;?php</div>', 3000);
            return false;
        }
        if (!code.endsWith(phpEndTag)) {
            showToast('<div class="error-message">Error: File must end with ?&gt;</div>', 3000);
            return false;
        }

        document.getElementById('hiddenContent').value = code;
        return true;
    }
    </script>
</body>
</main>
</html>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
