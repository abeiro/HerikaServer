<?php
// Prevent browser caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Define the file path
$file_path = '../prompts/prompts_custom.php';

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
            $message = 'File saved successfully.';
        } else {
            $message = 'Error saving the file.';
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
            $message = '<div style="color: #32CD32; font-weight: bold;">Validation successful. The following checks passed:<br></div>' . 
            '<div style="color: #32CD32;">' . implode('<br>', $validation_steps) . '</div>';
 

        } else {
            $message = '<div style="color: red; font-weight: bold;">Validation failed:<br></div>' . 
            '<div style="color: red;">' . implode('<br>', $errors) . '</div>';

        }
    }
    // View prompts button
    elseif (isset($_POST['view_prompts'])) {
        // Handle view_prompts
        $prompts_file_path = '../prompts/prompts.php';
        if (file_exists($prompts_file_path)) {
            $prompts_content = file_get_contents($prompts_file_path);
        } else {
            $message = 'prompts.php file not found.';
        }
    }

}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>📝CHIM - Custom Prompts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
        }

        h1, h2 {
            color: #ffffff; /* White color for headings */
        }

        form {
            margin-bottom: 20px;
            background-color: #3a3a3a; /* Slightly lighter grey for form backgrounds */
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #555555; /* Darker border for contrast */
            max-width: 800px;
        }

        label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
        }

        input[type="text"], input[type="file"], textarea {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #555555; /* Darker borders */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input backgrounds */
            color: #f8f9fa; /* Light text inside inputs */
            resize: vertical; /* Allows users to resize vertically if needed */
            font-family: Arial, sans-serif; /* Ensures consistent font */
            font-size: 14px; /* Sets a readable font size */
        }

        input[type="submit"], .button {
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px; /* Slightly larger border radius */
            cursor: pointer;
            padding: 8px 16px; /* Increased padding for a larger button */
            font-size: 18px;   /* Increased font size */
            font-weight: bold; /* Bold text for better visibility */
            transition: background-color 0.3s ease; /* Smooth hover transition */
            margin-right: 10px;
        }

        input[type="submit"]:hover, .button:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }

        .message {
            background-color: #444444; /* Darker background for messages */
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            max-width: 800px;
            margin-bottom: 20px;
            color: #f8f9fa; /* Light text in messages */
        }

        .message p {
            margin: 0;
        }

        .response-container {
            margin-top: 20px;
        }

        .indent {
            padding-left: 10ch; /* 10 character spaces */
        }

        .indent5 {
            padding-left: 5ch; /* 5 character spaces */
        }

        .button {
            padding: 8px 16px;
            margin-top: 10px;
            cursor: pointer;
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 3px;
        }

        .button:hover {
            background-color: #0056b3;
        }

        textarea[readonly] {
            background-color: #4a4a4a;
            color: #f8f9fa;
            border: 1px solid #555555;
        }

        /* Code blocks styling */
        pre {
            background-color: #1e1e1e; /* Distinct dark background for code blocks */
            border: 1px solid #555555; /* Border to make it stand out */
            padding: 15px; /* Padding inside the code block */
            border-radius: 5px; /* Rounded corners */
            overflow-x: auto; /* Horizontal scroll if content overflows */
            margin-bottom: 20px; /* Space below code blocks */
        }

        pre code {
            border: none;
            padding: 0;
            color: #f8f9fa; /* Ensure code text is readable */
            font-family: 'Courier New', Courier, monospace; /* Monospace font for code */
            font-size: 14px; /* Consistent font size */
        }

        /* ACE Editor container */
        #editor {
            width: 100%;
            height: 700px; /* Force the main textbox to be large */
            background-color: #1e1e1e; /* Dark background for Ace Editor */
            margin-top: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>📝CHIM Custom Prompt Editor</h1>
    <p>
        By making your own <b>prompts_custom.php</b> file you can make edits to how AI NPCs respond to triggered events.
        For example, you can adjust how AI NPCs write their diary entries, what they say during bored events, and more!
    </p>
    <p>
        The contents of this file overwrites whatever is in the standard <code>prompts.php</code>, meaning you can safely make edits to it without breaking it when CHIM updates.
    </p>
    <p>
        <b>The file must be in valid PHP format!</b> Make sure it starts with <code>&lt;?php</code> and ends with <code>?&gt;</code>.
    </p>

    <?php if (!empty($message)): ?>
        <div class="message"><p><?php echo nl2br($message); ?></p></div>
    <?php endif; ?>

    <!-- Main form for editing and saving the prompts_custom.php file -->
    <form method="post" onsubmit="return syncAceContent()">
        <label for="editor">prompts_custom.php Editor:</label>

        <!-- Ace Editor replaces the traditional <textarea> -->
        <div id="editor"></div>
        <!-- Hidden textarea to store final text from Ace Editor -->
        <textarea name="content" id="hiddenContent" style="display:none;"></textarea>
        
        <br>
        <input type="submit" name="save" value="Save">
        <input type="submit" name="validate" value="Validate">
    </form>

    <p>
        Click the <b>Validate</b> button to confirm the file is in proper format. Then click <b>Save</b>. 
    </p>
    <p>
        <i>Use an LLM chatbot if you need help fixing syntax errors.</i>
    </p>

    <!-- Form to view prompts.php -->
    <form method="post">
        <input type="submit" name="view_prompts" value="View prompts.php file">
    </form>

    <!-- If user clicked "View prompts" and found content, show it -->
    <?php if (isset($prompts_content) && !empty($prompts_content)): ?>
        <textarea rows="30" readonly><?php echo htmlspecialchars($prompts_content); ?></textarea>
    <?php endif; ?>

    <div class="ms-2 me-auto">
        <h3 class="fw-bold">How to Adjust the AI Prompts</h3>
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
    ],
        </code></pre>

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
],
        </code></pre>
    </div>
    <br>
    <h3>Custom Prompt Examples:</h3>
    <p><b>Remove the "I am alive" message when an AI NPC activates</b></p>
    <pre><code class="language-php">
$PROMPTS["im_alive"]=[ 
    "cue"=>["{$GLOBALS["HERIKA_NAME"]} A short saying about the situation. Write {$GLOBALS["HERIKA_NAME"]} dialogue. $TEMPLATE_DIALOG"],
    "player_request"=>["The Narrator:  {$GLOBALS["HERIKA_NAME"]} feels a sudden shock...and feels 'more real'"],
    "extra"=>["dontuse"=>true] 
];
    </code></pre>

    <p><b>Make diary entries more emotional and private (credit to Larrek)</b></p>
    <pre><code class="language-php">
$PROMPTS["diary"]=[ 
    "cue"=>["Please write a short summary of {$GLOBALS["PLAYER_NAME"]} and {$GLOBALS["HERIKA_NAME"]}'s last dialogues and events written above into {$GLOBALS["HERIKA_NAME"]}'s diary, add {$GLOBALS["HERIKA_NAME"]}'s emotions and private thoughts on people and events . WRITE AS IF YOU WERE {$GLOBALS["HERIKA_NAME"]}."],
    "extra"=>["force_tokens_max"=>0]
];
    </code></pre>

    <!-- Include Ace Editor scripts from jsDelivr -->
    <script src="https://cdn.jsdelivr.net/npm/ace-builds@1.5.0/src-min-noconflict/ace.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ace-builds@1.5.0/src-min-noconflict/ext-language_tools.js"></script>
    <script>
    let editor;

    // Initialize Ace Editor after DOM is loaded
    window.addEventListener('DOMContentLoaded', function() {
        editor = ace.edit("editor", {
            mode: "ace/mode/php",
            theme: "ace/theme/monokai",
            wrap: true,
            autoScrollEditorIntoView: true,
        });

        editor.setValue(<?php echo json_encode($content); ?>, -1);
    });

    // On form submission, copy Ace Editor content to hidden textarea
    function syncAceContent() {
    const code = editor.getValue().trim();

    // Quick check for start/end tags:
    if (!code.startsWith('<' + '?php')) {
        alert('Error: File must start with "<php?"');
        return false;
    }
    if (!code.endsWith('?' + '>')) {
        alert('Error: File must end with "?>"');
        return false;
    }

    document.getElementById('hiddenContent').value = code;
    return true; // Allow form submission
}
</script>
</body>
</html>
