<?php
$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "ui" . DIRECTORY_SEPARATOR . "profile_loader.php");

$isEmbed = (isset($_GET["embed"]) && $_GET["embed"] == "1");

// Determine web root
$scriptPath = $_SERVER["SCRIPT_NAME"];
$uiPos = strpos($scriptPath, "/ui/");
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : "";
$webRoot = rtrim($webRoot, "/");

$TITLE = "AI Quest Manager";

$tabs = [
    "manager" => [
        "label" => "AI Quest Manager",
        "src" => $webRoot . "/ui/addons/snqe/index.php?embed=1",
    ],
    "item_types" => [
        "label" => "Item Types",
        "src" => $webRoot . "/ui/addons/snqe/quest_reference_table.php?embed=1&dataset=item_types",
    ],
    "npc_templates" => [
        "label" => "NPC Templates",
        "src" => $webRoot . "/ui/addons/snqe/quest_reference_table.php?embed=1&dataset=npc_templates",
    ],
    "npc_own_templates" => [
        "label" => "NPC Own Templates",
        "src" => $webRoot . "/ui/addons/snqe/quest_reference_table.php?embed=1&dataset=npc_own_templates",
    ],
    "outfit" => [
        "label" => "Outfits",
        "src" => $webRoot . "/ui/addons/snqe/quest_reference_table.php?embed=1&dataset=outfit",
    ],
    "server_logs" => [
        "label" => "Server Logs",
        "src" => $webRoot . "/ui/addons/snqe/index.php?embed=1&view=logs",
    ],
    "traditional_quests" => [
        "label" => "Traditional Quests",
        "src" => $webRoot . "/ui/addons/snqe/traditional_quests.php?embed=1",
    ],
];

$activeTab = isset($_GET["tab"]) ? (string) $_GET["tab"] : "manager";
if (!isset($tabs[$activeTab])) {
    $activeTab = "manager";
}

ob_start();
include($enginePath . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
@font-face {
    font-family: "MagicCards";
    src: url("<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf") format("truetype");
    font-weight: normal;
    font-style: normal;
}

main {
    padding-top: <?php echo $isEmbed ? "20px" : "80px"; ?>;
    padding-bottom: 40px;
    padding-left: 10px;
    padding-right: 10px;
}

footer {
    position: fixed;
    bottom: 0;
    width: 100%;
    height: 20px;
    background: #031633;
    z-index: 100;
}

.tab-container {
    margin: 20px 0;
}

.hub-title {
    margin: 0 0 16px 0;
    padding: 14px 16px;
    border-radius: 8px;
    border: 1px solid #3a3a3a;
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    color: rgb(242, 124, 17);
    font-family: "MagicCards", sans-serif;
    letter-spacing: 1.5px;
    word-spacing: 8px;
    font-size: 1.5em;
}

.hub-subtitle {
    margin: -8px 0 16px 0;
    padding: 0 4px;
    color: #c3cad4;
    font-size: 0.95em;
}

.tab-buttons {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 20px;
    border-bottom: 2px solid rgba(242, 124, 17, 0.2);
    gap: 5px;
}

.tab-button {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.8), rgba(34, 34, 34, 0.9));
    border: 2px solid #3a3a3a;
    border-bottom: none;
    padding: 12px 16px;
    color: #f8f9fa;
    cursor: pointer;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    transition: all 0.3s ease;
    font-size: 0.95em;
    white-space: nowrap;
    font-family: "MagicCards", sans-serif;
    word-spacing: 8px;
    letter-spacing: 1.5px;
    margin-bottom: -2px;
}

.tab-button:hover {
    background: linear-gradient(180deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 1));
    color: rgb(242, 124, 17);
    border-color: rgba(242, 124, 17, 0.3);
}

.tab-button.active {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-color: rgba(242, 124, 17, 0.5);
    border-bottom: 2px solid rgba(42, 42, 42, 0.95);
    color: rgb(242, 124, 17);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.embed-frame {
    width: 100%;
    min-height: 78vh;
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
}

@media (max-width: 900px) {
    .tab-button {
        padding: 10px 12px;
        font-size: 0.88em;
    }

    .embed-frame {
        min-height: 76vh;
    }
}
</style>
<?php if (!$isEmbed): ?>
<?php include($enginePath . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php"); ?>
<?php endif; ?>

<main>
    <div class="tab-container">
        <h1 class="hub-title">AI Quest Manager</h1>
        <p class="hub-subtitle">Generate AI quests and maintain legacy reference tables for compatibility.</p>
        <div class="tab-buttons">
            <?php foreach ($tabs as $tabKey => $tabCfg): ?>
                <button class="tab-button <?php echo ($tabKey === $activeTab) ? "active" : ""; ?>" data-tab="<?php echo htmlspecialchars($tabKey); ?>">
                    <?php echo htmlspecialchars($tabCfg["label"]); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($tabs as $tabKey => $tabCfg): ?>
            <?php $isActive = ($tabKey === $activeTab); ?>
            <div id="tab-<?php echo htmlspecialchars($tabKey); ?>" class="tab-content <?php echo $isActive ? "active" : ""; ?>">
                <iframe
                    class="embed-frame"
                    src="<?php echo $isActive ? htmlspecialchars($tabCfg["src"]) : "about:blank"; ?>"
                    data-src="<?php echo htmlspecialchars($tabCfg["src"]); ?>"
                    loading="<?php echo $isActive ? "eager" : "lazy"; ?>"
                ></iframe>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
function activateTab(tabKey) {
    const buttons = document.querySelectorAll(".tab-button");
    const contents = document.querySelectorAll(".tab-content");

    buttons.forEach((btn) => {
        btn.classList.toggle("active", btn.dataset.tab === tabKey);
    });

    contents.forEach((content) => {
        const active = content.id === ("tab-" + tabKey);
        content.classList.toggle("active", active);
        if (active) {
            const iframe = content.querySelector("iframe");
            if (iframe && iframe.src === "about:blank") {
                iframe.src = iframe.dataset.src;
            }
        }
    });

    const url = new URL(window.location.href);
    url.searchParams.set("tab", tabKey);
    window.history.replaceState({}, "", url.toString());
}

document.querySelectorAll(".tab-button").forEach((btn) => {
    btn.addEventListener("click", function() {
        activateTab(this.dataset.tab);
    });
});
</script>

<?php
include($enginePath . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
