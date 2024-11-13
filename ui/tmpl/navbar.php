<nav class="navbar navbar-expand-lg bg-primary-subtle">
    <div class="container-fluid mx-1">
        <!-- PLEASE LEAVE THIS LINK TO index.php, as database update checks are being made there -->
        <!--<a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/conf_wizard.php" title="CHIM Server :: Go to Home Page"><img src="images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> CHIM</a> -->
        <a class="navbar-brand mr-2 Title" href="/HerikaServer/ui/index.php" title="Go to Home Page">
            <img src="images/DwemerDynamics.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
            <img src="images/serverlogo.png" alt="CHIM Server" style="vertical-align:bottom;"/> 
        </a> 
        
        <a class="navbar-brand mr-2 button" href="./index.php?togglemodel=true" title="Click to change active connector" style="display:none">
        <!--[IGNORE THIS] Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel.json'), true)); ?>-->
        </a>
        

        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Events & Memories</a>
                <ul class="dropdown-menu">

                <!-- Events Category -->
                <li><h6 class="dropdown-header">Events</h6></li>
                <li>
                    <a class="dropdown-item" href="index.php?table=eventlog">Events</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=eventlog&autorefresh=true">Monitor Events</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=quests">Current Active Quests</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=currentmission">Current AI Objective</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <!-- Logs Category -->
                <li><h6 class="dropdown-header">Logs</h6></li>
                <li>
                    <a class="dropdown-item" href="index.php?table=log">AI Log</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=diarylog">Diary Log</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=books">Book Log</a>
                </li>
                <li><hr class="dropdown-divider"></li>

                <!-- Memories Category -->
                <li><h6 class="dropdown-header">Memories</h6></li>
                <li>
                    <a class="dropdown-item" href="index.php?table=memory">Memories (WIP)</a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?table=memory_summary">Memory Summaries</a>
                </li>

                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Actions</a>
                <ul class="dropdown-menu">

                    <!-- First Category Header -->
                    <li><h6 class="dropdown-header">Event Management</h6></li>
                    <li>
                    <a class="dropdown-item" href="index.php?clean=true&table=response" title="Delete sent events." onclick="return confirm('Sure?')">
                        Clean Sent Events
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?sendclean=true&table=response" title="Marks unsent events from queue." onclick="return confirm('Sure?')">
                        Reset Sent Events
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?reset=true&table=event" title="Delete all events." onclick="return confirm('Sure?')">
                        Delete All Events
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Second Category Header -->
                    <li><h6 class="dropdown-header">AI Log Management</h6></li>
                    <li>
                    <a class="dropdown-item" href="index.php?cleanlog=true" title="Clean AI Log table" onclick="return confirm('Sure?')">
                        Clean AI Log
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?export=log" title="Export AI Log table (debugging purposes)." target="_blank">
                        Export AI Log
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Third Category Header -->
                    <li><h6 class="dropdown-header">Database Operations</h6></li>
                    <li>
                    <a class="dropdown-item" href="/pgAdmin/" target="_blank" title="pgAdmin Database Manager. User/password is 'dwemer'">
                        <strong>Database Manager (Both User & Password = dwemer)</strong>
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="export_db.php" target="_blank" title="Exports current database into a file.">
                        Backup Current Database
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="import_db.php" target="_blank" title="Reimport an exported database file.">
                        Restore Current Database 
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Fully reinstalls the CHIM Database." 
                    onclick="return confirm('This will wipe and reinstall the entire database!!! If you want to delete configurations, delete conf.php and conf_*.php files from HerikaServer conf folder. ARE YOU SURE?')">
                        Factory Reset Server Database
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Fourth Category Header -->
                    <li><h6 class="dropdown-header">Character Profiles</h6></li>
                    <li>
                    <a class="dropdown-item" href="export_conf.php" target="_blank" title="Exports current character profiles into a ZIP file.">
                        Backup Character Profiles
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="import_conf.php" target="_blank" title="Imports character profiles from a ZIP file.">
                        Restore Character Profiles
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Fifth Category Header -->
                    <li><h6 class="dropdown-header">Utilities</h6></li>
                    <li>
                    <a class="dropdown-item" href="tests/vector-compact-chromadb.php" title="Compact and Sync Memories." onclick="return confirm('Will use up tokens from your current AI connector. May take a few minutes to process. DO NOT REFRESH THE WEBPAGE!')">
                        Compact & Sync Memories
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="tests/ai_agent_ini.php" title="Generate AIAgent.ini file for the mod file." target="_blank">
                        <strong>Create AIAgent.ini (Place in mod folder under SKSE\Plugins)</strong>
                    </a>
                    </li>

                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                <ul class="dropdown-menu">

                    <!-- First Category Header -->
                    <li><h6 class="dropdown-header">Configuration Tools</h6></li>
                    <li>
                    <a class="dropdown-item" href="conf_wizard.php">Configuration Wizard</a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="npc_upload.php" title="Upload NPC Biographies with a csv file" target="_blank">
                        Upload NPC Biographies
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="quickstart.php" target="_blank">
                        Quickstart Menu
                    </a>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <!-- Second Category Header -->
                    <li><h6 class="dropdown-header">AI Voice Management</h6></li>
                    <li>
                    <a class="dropdown-item" href="xtts_clone.php" title="Manually manage XTTS FastAPI voices" target="_blank" rel="noopener noreferrer">
                        XTTS Distro Management
                    </a>
                    </li>
                    <li>
                    <a class="dropdown-item" href="http://localhost:59125" title="Find Mimic3 voices" target="_blank">
                        Mimic3 Browser
                    </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>

                    <!-- Third Category Header -->
                    <li><h6 class="dropdown-header">Guides (PLEASE READ!)</h6></li>
                    <li><a class="dropdown-item" href='index.php?notes=true'>CHIM 101 Quick Guide</a></li>
                    <li><a class="dropdown-item" href='https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?usp=sharing' target="_blank">CHIM Manual</a></li>
                    <li>
                    <a class="dropdown-item" href="https://docs.google.com/spreadsheets/d/1cLoJRT1AsjoICg8E4PzXylsWUSYzqlKvj32F6Q5clpg/edit?gid=0#gid=0" target="_blank">
                        AI/LLM Supported Models List
                    </a>
                    </li>
                </ul>
            </li>


            <li class="nav-item dropdown mx-2">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
            <ul class="dropdown-menu">
                <!-- Connection Tests -->
                <li><h6 class="dropdown-header">Connection Tests</h6></li>
                <li>
                <a class="dropdown-item" href="tests.php" target="_blank">Current LLM/AI Connection Test</a>
                </li>
                <!-- Uncomment the following items if needed -->
                <!--
                <li>
                <a class="dropdown-item" href="tests/tts-test-azure.php" target="_blank">Test Azure TTS Connection</a>
                </li>
                <li>
                <a class="dropdown-item" href="tests/tts-test-mimic3.php" target="_blank">Test MIMIC3 TTS Connection</a>
                </li>
                <li>
                <a class="dropdown-item" href="tests/tts-test-11labs.php" target="_blank">Test ElevenLabs TTS Connection</a>
                </li>
                <li>
                <a class="dropdown-item" href="tests/tts-test-gcp.php" target="_blank">Test Google Cloud TTS Connection</a>
                </li>
                -->
                <li>
                <a class="dropdown-item" href="tests/tts-test.php" target="_blank">Current TTS Connection Test</a>
                </li>
                <li>
                <a class="dropdown-item" href="../debug/simple_stt_test.php" target="_blank">Current STT Connection Test</a>
                </li>
                <li>
                <a class="dropdown-item" href="tests/itt-test.php" target="_blank">Current ITT Connection Test</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <!-- Logs & Cache -->
                <li><h6 class="dropdown-header">Logs & Cache</h6></li>
                <li>
                <a class="dropdown-item" href="tests/apache2err.php" target="_blank">Server Error Logs</a>
                </li>
                <li>
                <a class="dropdown-item" href="../soundcache/" target="_blank">Audio & Image Cache</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <!-- Utilities -->
                <li><h6 class="dropdown-header">Utilities</h6></li>
                <li>
                <a class="dropdown-item" href="cmd/action_regen_charmap.php" title="Use only if you deleted character_map.json!" target="_blank">
                    Regenerate Character Map
                </a>
                </li>
                <!--<li>
                <a class="dropdown-item" href="updater.php" target="_blank">Update Server</a>
                </li>-->
            </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Immersion</a>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Immersion Tools</h6></li>
                    <li><a class="dropdown-item" href="addons/diary" target="_blank">AI Diary</a></li>
                    <li><a class="dropdown-item" href="addons/chatsim" target="_blank">Chat Simulation</a></li>
                    <!--<li><a class="dropdown-item" href="addons/scriptwriter" target="_blank">Script Writer</a></li>-->
                    <!--<li><a class="dropdown-item" href="addons/background" target="_blank">Background Story Generator</a></li>-->
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Plugins</a>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">CHIM Extensions</h6></li>
                    <li><a class="dropdown-item" href='index.php?plugins_show=true'>Installed Plugins</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Debugging</h6></li>
                    <li><a class="dropdown-item" href="index.php?table=responselog" title="">Responses</a></li>
                    <li><a class="dropdown-item" href="index.php?table=audit_request" title="">Requests logs</a></li>
                </ul>
            </li>
        </ul>
    </div>
    <a href="https://discord.gg/NDn9qud2ug" target="_blank" style="padding-right: 5px;">
    <img src="images/discord.png" alt="Join us on Discord">
    </a>
    <a href="https://patreon.com/DwemerDynamics" target="_blank" style="padding-right: 10px;">
    <img src="images/patreon.png" alt="Join our Patreon">
</a>

</nav>

<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize favorites in session if not set
if (!isset($_SESSION['FAVORITES'])) {
    $_SESSION['FAVORITES'] = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile selection
    if (isset($_POST['profileSelector'])) {
        // Update the session with the selected profile
        $_SESSION['PROFILE'] = $_POST['profileSelector'];

        // Redirect to conf_wizard.php
        header("Location: conf_wizard.php");
        exit();
    }

    // Handle favorite toggling
    if (isset($_POST['favoriteToggle'])) {
        $profileToToggle = $_POST['favoriteToggle'];
        if (in_array($profileToToggle, $_SESSION['FAVORITES'])) {
            // Remove from favorites
            $_SESSION['FAVORITES'] = array_filter($_SESSION['FAVORITES'], function($fav) use ($profileToToggle) {
                return $fav !== $profileToToggle;
            });
        } else {
            // Add to favorites
            $_SESSION['FAVORITES'][] = $profileToToggle;
        }

        // Redirect to avoid form resubmission
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '#'));
        exit();
    }
}
    // Initialize session variable if not set
    if (!isset($_SESSION["OPTION_TO_SHOW"])) {
        if (!isset($_COOKIE["OPTION_TO_SHOW"])) {
            $_SESSION["OPTION_TO_SHOW"] = "basic";
        } else {
            $_SESSION["OPTION_TO_SHOW"] = $_COOKIE["OPTION_TO_SHOW"];
        }
    } else {
        if (isset($_COOKIE["OPTION_TO_SHOW"])) {
            $_SESSION["OPTION_TO_SHOW"] = $_COOKIE["OPTION_TO_SHOW"];
        }
    }

    // Character Map file
    $characterMap = [];
    if (file_exists(__DIR__ . "/../../conf/character_map.json")) {
        $characterMap = json_decode(file_get_contents(__DIR__ . "/../../conf/character_map.json"), true);
    }

    // Prepare profile options
    $OPTIONS = [];
    foreach ($GLOBALS["PROFILES"] as $lProfkey => $lProfile) {
        $pattern = "/conf_([a-fA-F0-9]+)\.php/";
        if (preg_match($pattern, $lProfile, $matches)) {
            $hash = $matches[1];
            if (isset($characterMap["$hash"])) {
                $name = $characterMap["$hash"];
                $value = $lProfile;
                $OPTIONS[] = ["value" => $value, "name" => $name];
                $LOCAL_CHAR_NAME = $name;
            }
        } else if ($lProfkey) {
            $name = "* $lProfkey";
            $value = $lProfile;
            $OPTIONS[] = ["value" => $value, "name" => $name];
            $LOCAL_CHAR_NAME = $lProfkey;
        }
        if (isset($_SESSION["PROFILE"]) && $_SESSION["PROFILE"] == $lProfile) {
            $GLOBALS["CURRENT_PROFILE_CHAR"] = $LOCAL_CHAR_NAME;
        }
    }

    // Sort options
    usort($OPTIONS, function ($a, $b) {
        if ($a['name'] == 'default') {
            return -1;
        }
        if ($b['name'] == 'default') {
            return 1;
        }
        return strcmp($a['name'], $b['name']);
    });
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Profile Selection Overlay</title>
        <style>
            /* Overlay Background with Blur Effect */
            .overlay {
                position: fixed; /* Sit on top of the page content */
                display: none; /* Hidden by default */
                width: 100%; /* Full width (cover the whole page) */
                height: 100%; /* Full height (cover the whole page) */
                top: 0;
                left: 0;
                background: rgba(255, 255, 255, 0.1); /* Semi-transparent for backdrop-filter */
                backdrop-filter: blur(10px); /* Apply blur effect */
                -webkit-backdrop-filter: blur(10px); /* Safari support */
                z-index: 9999; /* Specify a stack order */
                cursor: pointer; /* Add a pointer on hover */
            }

            /* When the URL has #overlay, display the overlay */
            #overlay:target {
                display: block;
            }

            /* Overlay Content */
            .overlay-content {
                position: absolute;
                top: 10%; /* Position closer to the top */
                left: 50%;
                transform: translate(-50%, 0); /* Only center horizontally */
                width: 90%;
                max-width: 800px;
                max-height: 80vh; /* Adjusted to fit better near the top */
                background-color: rgb(32, 32, 32); /* Dark Gray for content */
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.5);
                overflow-y: auto; /* Enable vertical scrolling */
                cursor: default; /* Prevent cursor pointer inside content */
                color: #ffffff; /* White text for readability */
                font-weight: bold; /* Make all text bold */
            }

            /* Close Button */
            .close-btn {
                position: absolute;
                top: 15px;
                right: 20px;
                font-size: 30px;
                font-weight: bold;
                color: #ffffff; /* White color for visibility */
                text-decoration: none;
                cursor: pointer;
            }

            .close-btn:hover {
                color: rgb(255, 0, 0); /* Red on hover */
            }

            /* Grid Layout for Options */
            .options-container {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-top: 20px; /* Space for the filter buttons */
            }

            /* Option Buttons */
            .dropdown-option {
                position: relative; /* For positioning the favorite button */
                padding: 15px;
                background-color: #031633; /* Deep Navy Blue for option backgrounds */
                border: 2px solid #021b4d; /* Slightly darker navy blue border */
                border-radius: 6px;
                cursor: pointer;
                text-align: center;
                font-size: 16px;
                color: #ffffff; /* White text */
                transition: background-color 0.3s, border-color 0.3s;
                text-decoration: none;
                display: block;
                font-weight: bold; /* Make text bold */
            }

            .dropdown-option:hover {
                background-color: #022a6a; /* Slightly lighter navy blue on hover */
                border-color: #031633; /* Slightly lighter border on hover */
            }

            /* Favorite Button */
            .favorite-btn {
                position: absolute;
                top: 50%; /* Center vertically */
                right: 8px; /* Align to the right */
                transform: translateY(-50%); /* Adjusts for the button's height to truly center it */
                background: none;
                border: none;
                cursor: pointer;
                font-size: 36px; /* 2x the original size of 18px */
                color: #FFD700; /* Gold color for visibility */
                transition: color 0.3s;
                font-weight: bold; /* Make icon bold */
                z-index: 1; /* Ensure it stays on top */
                        }

            .favorite-btn.favorited {
                color: #FFD700; /* Gold color for favorites */
            }

            .favorite-btn:hover {
                color: #FFD700; /* Gold color on hover */
            }

            /* Open Overlay Button */
                        .open-overlay-btn {
                padding: 10px 20px;
                background-color: rgb(0, 48, 176); /* Deep Navy Blue */
                color: #ffffff; /* White text */
                border: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.65); /* Border with custom RGBA color */
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
                text-decoration: none;
                display: inline-block;
                transition: background-color 0.3s, color 0.3s;
                margin: 5px;
                font-weight: bold; /* Make text bold */
            }


            .open-overlay-btn:hover {
                background-color: #022a6a; /* Slightly lighter navy blue on hover */
                color: #ffffff; /* White text on hover */
            }

            /* A-Z and Favorites Filter Buttons */
            .filter-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-bottom: 20px;
                justify-content: center;
            }

            .filter-button {
                padding: 8px 12px;
                background-color: #031633; /* Deep Navy Blue */
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                color: #ffffff; /* White text */
                transition: background-color 0.3s, color 0.3s;
                font-weight: bold; /* Make text bold */
            }

            /* Specific Styling for "All" Filter Button */
            .filter-button[data-filter="all"] {
                background-color: #28a745; /* Green */
            }

            .filter-button[data-filter="all"]:hover,
            .filter-button[data-filter="all"].active {
                background-color: #218838; /* Darker Green on hover and active */
                color: #ffffff; /* White text on hover and active */
            }

            .filter-button:not([data-filter="all"]):hover,
            .filter-button:not([data-filter="all"]).active {
                background-color: #022a6a; /* Slightly lighter navy blue on hover and active */
                color: #ffffff; /* White text on hover and active */
            }

            /* Responsive Design */
            @media (max-width: 800px) {
                .options-container {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 500px) {
                .options-container {
                    grid-template-columns: 1fr;
                }
            }

            /* Ensure profile-select-btn occupies full space except favorite button */
            .profile-select-btn {
                width: 100%;
                height: 100%;
                background: none;
                border: none;
                padding: 0;
                margin: 0;
                text-align: center;
                cursor: pointer;
                font-size: 16px;
                color: inherit;
                font-weight: bold; /* Make text bold */
            }

            .profile-select-btn:focus {
                outline: none;
            }
        </style>
    </head>
    <body>
        <!-- Trigger Link to Open Overlay -->
        <a href="#overlay" class="open-overlay-btn">
            <?php echo isset($GLOBALS["CURRENT_PROFILE_CHAR"]) ? htmlspecialchars($GLOBALS["CURRENT_PROFILE_CHAR"], ENT_QUOTES, 'UTF-8') : 'Select Profile'; ?>
        </a>
        <!-- The Overlay -->
        <div id="overlay" class="overlay">
            <!-- Overlay Content -->
            <div class="overlay-content">
                <a href="#" class="close-btn">&times;</a>
                <h2>Character Profile</h2>

                <!-- A-Z and Favorites Filter Buttons -->
                <div class="filter-buttons">
                    <button class="filter-button" data-filter="all">All</button>
                    <button class="filter-button" data-filter="favorites">Favorites</button>
                    <?php foreach (range('A', 'Z') as $letter): ?>
                        <button class="filter-button" data-filter="<?php echo $letter; ?>"><?php echo $letter; ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Profile Selection Form -->
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="formprofile">
                    <div class="options-container">
                        <?php foreach ($OPTIONS as $op): ?>
                            <?php
                                $value = htmlspecialchars($op['value']);
                                $name = htmlspecialchars($op['name']);
                                $firstLetter = strtoupper(substr($name, 0, 1));
                                if (!ctype_alpha($firstLetter)) {
                                    $firstLetter = '#'; // Non-alphabetic characters grouped under '#'
                                }
                                // Determine if the profile is favorited
                                $isFavorited = in_array($op['value'], $_SESSION['FAVORITES']);
                            ?>
                            <div class="dropdown-option" data-filter-letter="<?php echo $isFavorited ? 'favorites' : $firstLetter; ?>">
                                <!-- Profile Selection Button -->
                                <button type="submit" name="profileSelector" value="<?php echo $value; ?>" class="profile-select-btn" aria-label="Select profile <?php echo $name; ?>">
                                    <?php echo $name; ?>
                                </button>
                                <!-- Favorite Toggle Form -->
                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="favorite-form">
                                    <input type="hidden" name="favoriteToggle" value="<?php echo $value; ?>">
                                    <button type="submit" class="favorite-btn <?php echo $isFavorited ? 'favorited' : ''; ?>" title="<?php echo $isFavorited ? 'Unfavorite' : 'Favorite'; ?>" aria-label="<?php echo $isFavorited ? 'Unfavorite profile ' . $name : 'Favorite profile ' . $name; ?>">
                                        <?php echo $isFavorited ? '★' : '☆'; ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="shorcutholder" id="shorcutholder" value="">
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const filterButtons = document.querySelectorAll('.filter-button');
                const profileContainers = document.querySelectorAll('.dropdown-option');

                filterButtons.forEach(button => {
                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        // Remove 'active' class from all buttons
                        filterButtons.forEach(btn => btn.classList.remove('active'));
                        // Add 'active' class to the clicked button
                        this.classList.add('active');

                        const filter = this.getAttribute('data-filter');

                        profileContainers.forEach(container => {
                            if (filter === 'all') {
                                container.style.display = 'block';
                            } else if (filter === 'favorites') {
                                // Show only favorited profiles
                                if (container.getAttribute('data-filter-letter') === 'favorites') {
                                    container.style.display = 'block';
                                } else {
                                    container.style.display = 'none';
                                }
                            } else {
                                const containerLetter = container.getAttribute('data-filter-letter');
                                if (containerLetter === filter) {
                                    container.style.display = 'block';
                                } else {
                                    container.style.display = 'none';
                                }
                            }
                        });
                    });
                });

                // Optionally, activate 'All' filter by default
                const allFilterBtn = document.querySelector('.filter-button[data-filter="all"]');
                if (allFilterBtn) {
                    allFilterBtn.click();
                }
            });
        </script>
    </body>
</html>

        <div style="display: inline-block; font-size: 10px; height: 40px; padding-right: 10px; vertical-align: top;">
        <span style="margin-right: 5px; font-size: 14px; vertical-align: middle; font-weight: bold">Configuration Depth</span>
        
        <button
            style="
                margin-top: 5px; 
                font-weight: bold; 
                border: 1px solid rgb(255, 255, 255); 
                padding: 5px 10px; 
                cursor: pointer; 
                border-radius: 4px; 
                font-size: 12px; 
                background-color: #ffc107; /* Yellow */
                color: black; 
                transition: background-color 0.3s, color 0.3s;
                <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'basic') ? 'border: 2px solid black;' : ''; ?>
            "
            onclick="location.href='set_option_conf.php?c=basic'"
            onmouseover="this.style.backgroundColor='#e0a800';" /* Darker Yellow */
            onmouseout="this.style.backgroundColor='#ffc107';">
            Basic
        </button>
        
        <button
            style="
                margin-top: 5px; 
                font-weight: bold; 
                border: 1px solid rgb(255, 255, 255); 
                padding: 5px 10px; 
                cursor: pointer; 
                border-radius: 4px; 
                font-size: 12px; 
                background-color: #fd7e14; /* Orange */
                color: black; 
                transition: background-color 0.3s, color 0.3s;
                <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'pro') ? 'border: 2px solid black;' : ''; ?>
            "
            onclick="location.href='set_option_conf.php?c=pro'"
            onmouseover="this.style.backgroundColor='#e06b0d';" /* Darker Orange */
            onmouseout="this.style.backgroundColor='#fd7e14';">
            Advanced
        </button>
        
        <button
            style="
                margin-top: 5px; 
                font-weight: bold; 
                border: 1px solid rgb(255, 255, 255); 
                padding: 5px 10px; 
                cursor: pointer; 
                border-radius: 4px; 
                font-size: 12px; 
                background-color: #dc3545; /* Red */
                color: black; 
                transition: background-color 0.3s, color 0.3s;
                <?php echo ($_SESSION['OPTION_TO_SHOW'] == 'wip') ? 'border: 2px solid black;' : ''; ?>
            "
            onclick="location.href='set_option_conf.php?c=wip'"
            onmouseover="this.style.backgroundColor='#c82333';" /* Darker Red */
            onmouseout="this.style.backgroundColor='#dc3545';">
            Experimental
        </button>
    </div>

    <div style="display:inline-block; max-width:900px; font-size:small; height:50px; padding-right:10px; vertical-align: top;">

    <?php 

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."../";
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");

    if (isset($_SESSION["PROFILE"])) {
        require_once($_SESSION["PROFILE"]);
    }

    $GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);

    $currentModel=DMgetCurrentModel();
    // Convert arrays to strings or use print_r for debugging
    echo " <strong>AI/LLM Service(s):</strong> ";
    echo is_array($CONNECTORS) ? '<span style="color: yellow;">' . implode(",", $CONNECTORS) . '</span> | ' : '<span style="color: yellow;">' . $CONNECTORS . '</span>';
    echo '
    <form action="cmd/action_toogle_model.php" method="get" style="display:inline;">
        <input type="hidden" name="profile" value="' . htmlspecialchars($_SESSION["PROFILE"], ENT_QUOTES, 'UTF-8') . '">
        <button type="submit" style="
            padding: 3px 8px; /* Reduced padding for smaller size */
            font-weight: bold;
            font-size: 12px; /* Reduced font size */
            border: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.65); /* Border with custom RGBA color */
            color: white;
            background-color: #0030b0; /* Darker Blue */
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        " onmouseover="this.style.backgroundColor=\'#0056b3\';" onmouseout="this.style.backgroundColor=\'#0030b0\';">
            Current AI Service ➡ <span style="color:yellow;">(' . htmlspecialchars($currentModel, ENT_QUOTES, 'UTF-8') . ')</span>
        </button>
    </form><br/>';
    echo " <strong>TTS Service:</strong> ";
    echo is_array($TTSFUNCTION) ?  print_r($TTSFUNCTION, true)  : '<strong style="color:#ff00c6">' . $TTSFUNCTION . '</strong>'; 
    echo " <strong>STT Service:</strong> ";
    echo is_array($STTFUNCTION) ?  print_r($STTFUNCTION, true)  : '<strong style="color:#ff00c6">' . $STTFUNCTION . '</strong>' ; 
    echo " <strong>ITT Service:</strong> ";
    echo is_array($ITTFUNCTION) ?  print_r($ITTFUNCTION, true)  : '<strong style="color:#ff00c6">'  .$ITTFUNCTION . '</strong>' ; 
    ?>
</div>
</div>

<main style="max-height:760px;overflow-y:scroll">
