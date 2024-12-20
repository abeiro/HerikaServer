<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Delete Profiles</title>
<style>
    /* Updated CSS for Dark Grey Background Theme */
    body {
        font-family: Arial, sans-serif;
        background-color: #2c2c2c; /* Dark grey background */
        color: #f8f9fa; /* Light grey text for readability */
        margin: 20px;
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
        max-width: 600px;
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

    input[type="submit"] {
        background-color: #007bff;
        border: none;
        color: white;
        border-radius: 5px; /* Slightly larger border radius */
        cursor: pointer;
        padding: 5px 15px; /* Increased padding for larger button */
        font-size: 18px;    /* Increased font size */
        font-weight: bold;  /* Bold text for better visibility */
        transition: background-color 0.3s ease; /* Smooth hover transition */
    }

    input[type="submit"]:hover {
        background-color: #0056b3; /* Darker shade on hover */
    }

    .message {
        background-color: #444444; /* Darker background for messages */
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #555555;
        max-width: 600px;
        margin-bottom: 20px;
        color: #f8f9fa; /* Light text in messages */
        font-family: Arial, sans-serif;
    }

    .message p {
        margin: 0 0 5px 0;
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
</style>
</head>
<body>
<?php
// Adjust the path to point to the conf directory, one level above ui
$confDir = __DIR__ . '/../conf';

// Check if the directory exists
if (!is_dir($confDir)) {
    echo '<div class="message"><p>Directory ' . htmlspecialchars($confDir) . ' does not exist.</p></div>';
    exit;
}

// Patterns for the files we want to potentially delete
$patterns = [
    $confDir . '/conf_*.php',
    $confDir . '/character_map.json'
];

// Files to exclude from deletion
$exclusions = [
    $confDir . '/conf.sample.php',
    $confDir . '/conf_loader.php',
    $confDir . '/conf_schema.json',
    $confDir . '/conf.php'
];

echo '<div class="message">';
foreach ($patterns as $pattern) {
    foreach (glob($pattern) as $file) {
        // Skip the file if it's in the exclusion list
        if (in_array($file, $exclusions)) {
            continue;
        }

        // Attempt to delete only if it's a file
        if (is_file($file)) {
            if (unlink($file)) {
                echo "<p>Deleted: " . htmlspecialchars($file) . "</p>";
            } else {
                echo "<p>Failed to delete: " . htmlspecialchars($file) . "</p>";
            }
        }
    }
}
echo "<p>All profiles apart from default have been deleted</p>";
echo '</div>';
?>
</body>
</html>
