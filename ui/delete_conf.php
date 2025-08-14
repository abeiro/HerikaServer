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

    .locked {
        color: #ffd700; /* Gold color for locked profiles */
    }

    .deleted {
        color: #dc3545; /* Red color for deleted profiles */
    }

    .error {
        color: #dc3545; /* Red color for errors */
    }

    .profile-name {
        color: #f8f9fa; /* Light grey for the profile name */
        margin-left: 10px;
        font-style: italic;
    }
</style>
</head>
<body>
<?php
// Adjust the path to point to the conf directory, one level above ui
$confDir = __DIR__ . '/../conf';

// Check if the directory exists
if (!is_dir($confDir)) {
    echo '<div class="message"><p class="error">Directory ' . htmlspecialchars($confDir) . ' does not exist.</p></div>';
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

$lockedProfiles = [];
$deletedProfiles = [];
$errorProfiles = [];
$profileNames = []; // Array to store HERIKA_NAMEs
$availableProfiles = []; // Array to store all available profiles

foreach ($patterns as $pattern) {
    foreach (glob($pattern) as $file) {
        // Skip the file if it's in the exclusion list
        if (in_array($file, $exclusions)) {
            continue;
        }

        // Check if it's a PHP configuration file
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            // Include the file to check for LOCK_PROFILE and get HERIKA_NAME
            $LOCK_PROFILE = false;
            $HERIKA_NAME = '';
            
            // Safely include the file with error handling for syntax errors
            $includeError = false;
            set_error_handler(function($severity, $message, $file, $line) {
                // Convert errors to exceptions to catch them
                throw new ErrorException($message, 0, $severity, $file, $line);
            });
            
            try {
                include($file);
            } catch (ParseError $e) {
                // PHP syntax error in the config file
                $includeError = true;
                $HERIKA_NAME = '[SYNTAX ERROR - ' . basename($file) . ']';
            } catch (ErrorException $e) {
                // Other include errors
                $includeError = true;
                $HERIKA_NAME = '[ERROR - ' . basename($file) . ']';
            } catch (Throwable $e) {
                // Any other errors
                $includeError = true;
                $HERIKA_NAME = '[UNKNOWN ERROR - ' . basename($file) . ']';
            }
            
            restore_error_handler();
            
            $filename = basename($file);
            $profileNames[$filename] = $HERIKA_NAME;
            $availableProfiles[] = $filename;
            
            // If there was an error, mark it as problematic but still allow deletion
            if ($includeError) {
                $errorProfiles[] = $filename . ' (syntax error detected)';
            }
            
            // Only check for LOCK_PROFILE if file included successfully
            if (!$includeError && $LOCK_PROFILE === true) {
                $lockedProfiles[] = $filename;
                continue;
            }
        }
    }
}

// If no profiles found at all
if (empty($availableProfiles)) {
    echo '<div class="message">';
    echo "<h1>Profile Status</h1>";
    echo "<p>No profiles detected (apart from default).</p>";
    echo '</div>';
    exit;
}

// If there are only locked profiles or no deletable profiles
if (count($availableProfiles) === count($lockedProfiles)) {
    echo '<div class="message">';
    echo "<h1>No profiles available for deletion.</h1>";
    echo "<h1>Current Locked Profiles:</h1>";
    foreach ($lockedProfiles as $profile) {
        $displayName = isset($profileNames[$profile]) ? " - {$profileNames[$profile]}" : "";
        echo "<p class='locked'>🔒 " . htmlspecialchars($profile) . 
             "<span class='profile-name'>" . htmlspecialchars($displayName) . "</span></p>";
    }
    echo '</div>';
    exit;
}

// Process deletions for non-locked profiles
foreach ($patterns as $pattern) {
    foreach (glob($pattern) as $file) {
        if (in_array($file, $exclusions) || in_array(basename($file), $lockedProfiles)) {
            continue;
        }

        if (is_file($file)) {
            if (unlink($file)) {
                $deletedProfiles[] = basename($file);
            } else {
                $errorProfiles[] = basename($file);
            }
        }
    }
}

echo '<div class="message">';

// Display results
if (!empty($lockedProfiles)) {
    echo "<h2>Locked Profiles (Not Deleted):</h2>";
    foreach ($lockedProfiles as $profile) {
        $displayName = isset($profileNames[$profile]) ? " - {$profileNames[$profile]}" : "";
        echo "<p class='locked'>🔒 " . htmlspecialchars($profile) . 
             "<span class='profile-name'>" . htmlspecialchars($displayName) . "</span></p>";
    }
}

if (!empty($deletedProfiles)) {
    echo "<h2>Deleted Profiles:</h2>";
    foreach ($deletedProfiles as $profile) {
        $displayName = isset($profileNames[$profile]) ? " - {$profileNames[$profile]}" : "";
        echo "<p class='deleted'>✓ " . htmlspecialchars($profile) . 
             "<span class='profile-name'>" . htmlspecialchars($displayName) . "</span></p>";
    }
}

if (!empty($errorProfiles)) {
    echo "<h2>Profiles with Issues:</h2>";
    foreach ($errorProfiles as $profile) {
        if (strpos($profile, 'syntax error detected') !== false) {
            echo "<p class='error'>⚠️ " . htmlspecialchars($profile) . " - <strong>This file had syntax errors and needs to be manually delted.</strong></p>";
        } else {
            $displayName = isset($profileNames[$profile]) ? " - {$profileNames[$profile]}" : "";
            echo "<p class='error'>❌ " . htmlspecialchars($profile) . 
                 "<span class='profile-name'>" . htmlspecialchars($displayName) . "</span></p>";
        }
    }
}

echo '</div>';

// Run the character map regeneration only if files were deleted
if (!empty($deletedProfiles)) {
    echo '<div class="message">';
    echo "<h2>Updating Character Map</h2>";
    ob_start();
    require_once(__DIR__ . '/cmd/action_regen_charmap.php');
    $result = ob_get_clean();
    echo "<p>Character map has been updated.</p>";
    echo '</div>';
}
?>
</body>
</html>
