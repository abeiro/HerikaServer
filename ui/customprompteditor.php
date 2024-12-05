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

    if (isset($_POST['save'])) {
        // Save the new content back to the file
        if (file_put_contents($file_path, $content) !== false) {
            $message = 'File saved successfully.';
        } else {
            $message = 'Error saving the file.';
        }
    } elseif (isset($_POST['validate'])) {
        // Perform validation
    $validation_steps = [];
    $errors = [];

    // Check if it starts with <?php
    if (strpos($content, '<?php') !== 0) {
        $errors[] = 'The file must start with <?php';
    } else {
        $validation_steps[] = 'File is in PHP format';
    }

    if (substr(trim($content), -2) !== '?>') {
        $errors[] = 'The file must end with ?>';
    } else {
        $validation_steps[] = 'File ends with ?>';
    }

    if (empty($errors)) {
        // Save the content to a temporary file
        $tmpfname = tempnam(sys_get_temp_dir(), "phptest");
        file_put_contents($tmpfname, $content);

        // Execute PHP lint check
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($tmpfname), $output, $return_var);

        // Remove the temporary file
        unlink($tmpfname);

        if ($return_var !== 0) {
            $errors[] = 'PHP syntax error: ' . implode("\n", $output);
        } else {
            $validation_steps[] = 'PHP code syntax is valid';
        }
    }

    if (empty($errors)) {
        $message = 'Validation successful. The following checks passed:<br>' . implode('<br>', $validation_steps);
    } else {
        $message = 'Validation failed:<br>' . implode('<br>', $errors);
    }
    } elseif (isset($_POST['view_prompts'])) {
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
    <title>CHIM - Custom Prompts</title>
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
            padding: 5px 15px; /* Increased padding for larger button */
            font-size: 18px;    /* Increased font size */
            font-weight: bold;  /* Bold text for better visibility */
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

        /* New Styles for <pre> and <code> Elements */
        pre {
            background-color: #1e1e1e; /* Distinct dark background for code blocks */
            border: 1px solid #555555; /* Border to make it stand out */
            padding: 15px; /* Padding inside the code block */
            border-radius: 5px; /* Rounded corners */
            overflow-x: auto; /* Horizontal scroll if content overflows */
            margin-bottom: 20px; /* Space below code blocks */
        }

        pre code {
            background-color: #2a2a2a; /* Slightly lighter background for code */
            border: none; /* Remove border from code to rely on pre's border */
            padding: 0; /* Remove padding from code */
            color: #f8f9fa; /* Ensure code text is readable */
            font-family: 'Courier New', Courier, monospace; /* Monospace font for code */
            font-size: 14px; /* Consistent font size */
        }

        /* Optional: Syntax Highlighting Colors (Example for PHP) */
        .language-php {
            color: #89e051; /* Keyword color */
        }

        /* You can add more syntax highlighting rules as needed */
    </style>
</head>
<body>
    <h1>📝 Custom Prompt Editor</h1>
    <p>By making your own <b>prompts_custom.php</b> file you can make edits to how AI NPCs respond to triggered events.</p>
    <p>For example you can adjust how AI NPC's write their diary entries, what they say during bored events, how to summarize books and more!</p>
    <p>This file overwrites whatever is in the standard prompts.php, meaning you can safely make edits to this without breaking it if CHIM updates.</p>
    <p>The file requires proper JSON formatting!</p>
    <?php if (!empty($message)): ?>
        <div class="message"><p><?php echo nl2br($message); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <label for="content">prompts_custom.php Editor:</label>
        <textarea name="content" id="content" rows="30"><?php echo htmlspecialchars($content); ?></textarea>
        <br>
        <input type="submit" name="save" value="Save">
        <input type="submit" name="validate" value="Validate File">
    </form>

    <!-- New form for viewing prompts.php -->
    <form method="post">
        <input type="submit" name="view_prompts" value="View prompts.php file">
    </form>

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
    "extra"=>["dontuse"=>true]   //10% chance
];
    </code></pre>
</body>
</html>

