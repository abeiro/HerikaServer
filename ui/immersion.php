<?php
session_start();

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "Immersion";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 80px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
        padding-left: 10px;
        padding-right: 10px;
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

    /* Tabs styling aligned with Events & Memories */
    .tab-container { margin: 20px 0; }
    .tab-buttons {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 2px solid #3a3a3a;
        gap: 5px;
        word-spacing: 5px;
    }
    .tab-button {
        background: #2a2a2a;
        border: none;
        padding: 12px 18px;
        color: #f8f9fa;
        cursor: pointer;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        transition: all 0.3s ease;
        font-size: 1em;
        white-space: nowrap;
        font-family: 'MagicCards', sans-serif;
        word-spacing: 5px;
        letter-spacing: 1.5px;
    }
    .tab-button:hover { background: #3a3a3a; }
    .tab-button.active {
        background: #1a1a1a;
        border-bottom: 2px solid rgb(212, 94, 0);
        margin-bottom: -2px;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .embed-frame {
        width: 100%;
        height: 75vh;
        border: 1px solid #444;
        border-radius: 6px;
        background: #1a1a1a;
    }

    @media (max-width: 768px) {
        .tab-button { padding: 10px 14px; font-size: 0.9em; }
        .embed-frame { height: 75vh; }
    }
</style>
<?php

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Get active tab from URL parameter, default to 'diaries'
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'diaries';
?>

<div class="container-fluid">
    <h1 class='my-2'>Immersion</h1>

    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-button <?php echo $activeTab === 'diaries' ? 'active' : ''; ?>" onclick="switchTab('diaries')">📔 CHIM Diaries</button>
            <button class="tab-button <?php echo $activeTab === 'adventure' ? 'active' : ''; ?>" onclick="switchTab('adventure')">📆 Adventure Log</button>
            <!-- <button class="tab-button <?php echo $activeTab === 'chat' ? 'active' : ''; ?>" onclick="switchTab('chat')">💬 Chat Testing</button> -->
            <button class="tab-button <?php echo $activeTab === 'soulgaze' ? 'active' : ''; ?>" onclick="switchTab('soulgaze')">🖼️ Soulgaze Gallery</button>
        </div>

        <div id="diaries-tab" class="tab-content <?php echo $activeTab === 'diaries' ? 'active' : ''; ?>">
            <iframe class="embed-frame" src="<?php echo $webRoot; ?>/ui/diarylog.php?embed=1"></iframe>
        </div>

        <div id="adventure-tab" class="tab-content <?php echo $activeTab === 'adventure' ? 'active' : ''; ?>">
            <iframe class="embed-frame" src="<?php echo $webRoot; ?>/ui/adventurelog.php?embed=1"></iframe>
        </div>

        <div id="chat-tab" class="tab-content <?php echo $activeTab === 'chat' ? 'active' : ''; ?>">
            <iframe class="embed-frame" src="<?php echo $webRoot; ?>/ui/chat-testing.php?embed=1"></iframe>
        </div>
        <div id="soulgaze-tab" class="tab-content <?php echo $activeTab === 'soulgaze' ? 'active' : ''; ?>">
            <iframe class="embed-frame" src="<?php echo $webRoot; ?>/ui/soulgaze_gallery.php?embed=1"></iframe>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));

    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => button.classList.remove('active'));

    const target = document.getElementById(tabName + '-tab');
    if (target) target.classList.add('active');

    // Add active class to clicked button
    let clickedButton = null;
    if (typeof event !== 'undefined' && event && event.currentTarget && event.currentTarget.classList) {
        clickedButton = event.currentTarget;
    } else {
        clickedButton = document.querySelector('.tab-button[onclick*="' + tabName + '"]');
    }
    if (clickedButton) clickedButton.classList.add('active');

    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


