<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "ui" . DIRECTORY_SEPARATOR . "profile_loader.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chim_quest_engine.php");
require_once($enginePath . "processor" . DIRECTORY_SEPARATOR . "import_files.php");

$db = $GLOBALS["db"];
$isEmbed = (isset($_GET["embed"]) && $_GET["embed"] === "1");

$scriptPath = $_SERVER["SCRIPT_NAME"];
$uiPos = strpos($scriptPath, "/ui/");
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : "";
$webRoot = rtrim($webRoot, "/");

function traditional_quests_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function traditional_quests_js_string($value)
{
    $json = json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return ($json === false) ? '""' : $json;
}

function traditional_quests_bool($value)
{
    if (is_bool($value)) {
        return $value;
    }

    $cn = strtolower(trim((string) $value));
    return in_array($cn, ["1", "true", "t", "yes", "y", "on"], true);
}

function traditional_quests_pretty_json($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
    } elseif (is_array($value)) {
        $decoded = $value;
    } else {
        $decoded = [];
    }

    if (!is_array($decoded)) {
        $decoded = [];
    }

    $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return ($json === false) ? "{}" : $json;
}

function traditional_quests_fetch_rows($db)
{
    if (!chimQuestEngineReady()) {
        return [];
    }

    chimQuestEngineMaybeBootstrapBundledDefinitions();

    return $db->fetchAll("
        SELECT
            d.quest_key,
            d.quest_editor_id,
            d.title,
            d.source_plugin,
            d.source_form_id,
            d.source_path,
            d.active,
            d.updated_at,
            COALESCE(
                jsonb_array_length(
                    CASE
                        WHEN jsonb_typeof(d.skeleton->'beats') = 'array' THEN d.skeleton->'beats'
                        ELSE '[]'::jsonb
                    END
                ),
                0
            ) AS beat_count,
            i.run_state,
            i.current_stage,
            i.last_gamets,
            COALESCE(bs.fired_count, 0) AS fired_count
        FROM public.skyrim_quest_definitions d
        LEFT JOIN public.skyrim_quest_instances i ON i.quest_key = d.quest_key
        LEFT JOIN (
            SELECT quest_key, COUNT(*) AS fired_count
            FROM public.skyrim_quest_beat_state
            WHERE fired = true
            GROUP BY quest_key
        ) bs ON bs.quest_key = d.quest_key
        ORDER BY COALESCE(NULLIF(d.source_plugin, ''), 'Unknown'), d.title, d.quest_editor_id
    ");
}

function traditional_quests_fetch_detail($db, $questKey)
{
    if (!chimQuestEngineReady()) {
        return null;
    }

    $questKeyCn = $db->escape($questKey);
    $row = $db->fetchOne("
        SELECT
            d.quest_key,
            d.quest_editor_id,
            d.title,
            d.source_plugin,
            d.source_form_id,
            d.source_path,
            d.skeleton::text AS skeleton_json,
            d.active,
            d.created_at,
            d.updated_at,
            i.run_state,
            i.current_stage,
            i.last_gamets,
            i.state_json::text AS state_json
        FROM public.skyrim_quest_definitions d
        LEFT JOIN public.skyrim_quest_instances i ON i.quest_key = d.quest_key
        WHERE d.quest_key = '{$questKeyCn}'
        LIMIT 1
    ");

    if (empty($row) || empty($row["quest_key"])) {
        return null;
    }

    $skeleton = chimQuestEngineJsonDecode($row["skeleton_json"] ?? "{}", []);
    $state = chimQuestEngineJsonDecode($row["state_json"] ?? "{}", []);
    $beats = [];

    foreach (($skeleton["beats"] ?? []) as $beat) {
        if (!is_array($beat)) {
            continue;
        }

        $beats[] = [
            "id" => (string) ($beat["id"] ?? ""),
            "focus_npc" => (string) ($beat["focus_npc"] ?? ""),
            "comment" => (string) ($beat["comment"] ?? ""),
            "trigger_mode" => (string) ($beat["trigger_mode"] ?? ""),
            "action" => $beat["action"] ?? [],
            "triggers" => $beat["triggers"] ?? [],
            "prerequisites" => $beat["prerequisites"] ?? [],
            "downstream" => $beat["downstream"] ?? [],
            "conditions" => $beat["conditions"] ?? [],
            "allow_natural_start" => !empty($beat["allow_natural_start"]),
        ];
    }

    return [
        "quest_key" => (string) ($row["quest_key"] ?? ""),
        "quest_editor_id" => (string) ($row["quest_editor_id"] ?? ""),
        "title" => (string) ($row["title"] ?? ""),
        "source_plugin" => (string) ($row["source_plugin"] ?? ""),
        "source_form_id" => (string) ($row["source_form_id"] ?? ""),
        "source_path" => (string) ($row["source_path"] ?? ""),
        "active" => traditional_quests_bool($row["active"] ?? true),
        "created_at" => (string) ($row["created_at"] ?? ""),
        "updated_at" => (string) ($row["updated_at"] ?? ""),
        "run_state" => (string) ($row["run_state"] ?? ""),
        "current_stage" => (string) ($row["current_stage"] ?? ""),
        "last_gamets" => (string) ($row["last_gamets"] ?? ""),
        "description" => (string) ($skeleton["description"] ?? ""),
        "natural_start" => $skeleton["natural_start"] ?? [],
        "beats" => $beats,
        "state_json" => traditional_quests_pretty_json($state),
        "raw_json" => traditional_quests_pretty_json($skeleton),
    ];
}

function traditional_quests_reset_one($db, $questKey)
{
    if (!chimQuestEngineReady()) {
        return false;
    }

    $questKeyCn = $db->escape($questKey);
    $row = $db->fetchOne("
        SELECT quest_key
        FROM public.skyrim_quest_definitions
        WHERE quest_key = '{$questKeyCn}'
        LIMIT 1
    ");

    if (empty($row) || empty($row["quest_key"])) {
        throw new Exception("Quest not found.");
    }

    $defaultState = $db->escape(chimQuestEngineJsonEncode(chimQuestEngineDefaultState()));
    $db->execQuery("DELETE FROM public.skyrim_quest_action_outbox WHERE quest_key = '{$questKeyCn}'");
    $db->execQuery("DELETE FROM public.skyrim_quest_events WHERE quest_key = '{$questKeyCn}'");
    $db->execQuery("DELETE FROM public.skyrim_quest_beat_state WHERE quest_key = '{$questKeyCn}'");
    $db->execQuery("
        UPDATE public.skyrim_quest_instances
        SET run_state = 'inactive',
            current_stage = NULL,
            last_gamets = NULL,
            state_json = '{$defaultState}'::jsonb,
            updated_at = now()
        WHERE quest_key = '{$questKeyCn}'
    ");

    return true;
}

function traditional_quests_download_example_csv()
{
    $definition = [
        "quest_key" => "example_mod_recover_relic",
        "skeleton_type" => "quest",
        "quest_form_id" => "0x000800",
        "quest_plugin" => "ExampleMod.esp",
        "quest_editor_id" => "EXQ01",
        "title" => "Recover the Lost Relic",
        "description" => "An example traditional quest definition imported from CSV.",
        "npc_facts" => [
            "Example Quest Giver" => [
                "base_facts" => [
                    "My family relic was stolen by bandits.",
                    "I need capable help recovering it."
                ],
                "beat_facts" => [
                    "QUEST_ACCEPTED" => [
                        "The player agreed to recover the lost relic."
                    ],
                    "RELIC_RETURNED" => [
                        "The relic has been returned."
                    ]
                ]
            ]
        ],
        "beats" => [
            [
                "id" => "QUEST_ACCEPTED",
                "focus_npc" => "Example Quest Giver",
                "comment" => "Player accepts the quest from the quest giver.",
                "action" => ["type" => "gate"],
                "triggers" => [
                    [
                        "type" => "quest_stage",
                        "quest_editor_id" => "EXQ01",
                        "min_stage" => 10
                    ],
                    [
                        "type" => "dialogue",
                        "keywords" => ["recover the relic", "i will help", "lost relic"],
                        "min_matches" => 1
                    ]
                ],
                "trigger_mode" => "any",
                "prerequisites" => [],
                "allow_natural_start" => true
            ],
            [
                "id" => "RELIC_RETURNED",
                "focus_npc" => "Example Quest Giver",
                "comment" => "Player returns the relic to complete the quest.",
                "action" => ["type" => "gate"],
                "triggers" => [
                    [
                        "type" => "quest_stage",
                        "quest_editor_id" => "EXQ01",
                        "min_stage" => 100
                    ]
                ],
                "trigger_mode" => "any",
                "prerequisites" => ["QUEST_ACCEPTED"]
            ]
        ],
        "natural_start" => [
            "enabled" => true,
            "beats" => ["QUEST_ACCEPTED"],
            "requires" => []
        ]
    ];

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"example_tradquest.csv\"");

    $output = fopen("php://output", "w");
    if ($output === false) {
        exit;
    }

    fputcsv($output, ["quest_key", "quest_editor_id", "title", "quest_plugin", "quest_form_id", "active", "skeleton_json"]);
    fputcsv($output, [
        "example_mod_recover_relic",
        "EXQ01",
        "Recover the Lost Relic",
        "ExampleMod.esp",
        "0x000800",
        "true",
        chimQuestEngineJsonEncode($definition),
    ]);
    fclose($output);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "GET" && ($_GET["action"] ?? "") === "download_example_tradquest_csv") {
    traditional_quests_download_example_csv();
}

if ($_SERVER["REQUEST_METHOD"] === "GET" && ($_GET["action"] ?? "") === "quest_detail") {
    header("Content-Type: application/json");
    $questKey = trim((string) ($_GET["quest_key"] ?? ""));
    $detail = ($questKey !== "") ? traditional_quests_fetch_detail($db, $questKey) : null;

    if ($detail === null) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Quest not found."]);
        exit;
    }

    echo json_encode(["success" => true, "quest" => $detail], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$message = "";
$messageType = "success";
$tableReady = chimQuestEngineReady();

if ($tableReady) {
    chimQuestEngineMaybeBootstrapBundledDefinitions();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_tradquest_csv"])) {
    if (!$tableReady) {
        $message = "Quest tables are not ready. Run the database update first.";
        $messageType = "error";
    } elseif (!isset($_FILES["tradquest_csv_file"]) || intval($_FILES["tradquest_csv_file"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $message = "No CSV file uploaded, or upload failed.";
        $messageType = "error";
    } else {
        $fileName = (string) ($_FILES["tradquest_csv_file"]["name"] ?? "");
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== "csv") {
            $message = "Upload failed. Allowed file type: csv.";
            $messageType = "error";
        } else {
            $csvData = @file_get_contents((string) $_FILES["tradquest_csv_file"]["tmp_name"]);
            if ($csvData === false || trim($csvData) === "") {
                $message = "Uploaded CSV file is empty or could not be read.";
                $messageType = "error";
            } else {
                try {
                    $summary = traditionalQuestImportFromCsvData($csvData, $fileName);
                    $message = "Traditional quest CSV import complete: "
                        . intval($summary["processed"] ?? 0) . " row(s) imported, "
                        . intval($summary["errors"] ?? 0) . " error(s).";
                    if (!empty($summary["messages"]) && is_array($summary["messages"])) {
                        $message .= " " . implode(" ", array_slice($summary["messages"], 0, 3));
                    }
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Traditional quest CSV import failed: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if (!$tableReady) {
        $message = "Quest tables are not ready. Run the database update first.";
        $messageType = "error";
    } else {
        $action = trim((string) $_POST["action"]);
        $questKey = trim((string) ($_POST["quest_key"] ?? ""));

        try {
            if ($action === "reset_all_state") {
                if (!chimQuestEngineResetRuntime(true)) {
                    throw new Exception("Failed to reset quest runtime state.");
                }

                $message = "Reset all quest runtime state.";
                $messageType = "success";
            } elseif ($questKey === "") {
                throw new Exception("Quest key is required.");
            } else {
                $questKeyCn = $db->escape($questKey);
                if ($action === "toggle_active") {
                    $db->execQuery("
                        UPDATE public.skyrim_quest_definitions
                        SET active = NOT active,
                            updated_at = now()
                        WHERE quest_key = '{$questKeyCn}'
                    ");
                    $message = "Updated quest enabled state.";
                    $messageType = "success";
                } elseif ($action === "reset_quest_state") {
                    traditional_quests_reset_one($db, $questKey);
                    $message = "Reset quest runtime state for '{$questKey}'.";
                    $messageType = "success";
                } else {
                    throw new Exception("Unknown action.");
                }
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

$rows = [];
if ($tableReady) {
    try {
        $rows = traditional_quests_fetch_rows($db);
    } catch (Exception $e) {
        $message = "Failed to load traditional quests.";
        $messageType = "error";
        $rows = [];
    }
}

$questCount = count($rows);
$activeCount = 0;
$plugins = [];
foreach ($rows as $row) {
    if (traditional_quests_bool($row["active"] ?? true)) {
        $activeCount++;
    }

    $plugin = trim((string) ($row["source_plugin"] ?? ""));
    if ($plugin === "") {
        $plugin = "Unknown";
    }
    $plugins[$plugin] = true;
}
$pluginNames = array_keys($plugins);
natcasesort($pluginNames);

$TITLE = "AI Quest Manager - Traditional Quests";

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
    padding-left: 5%;
    padding-right: 5%;
    width: 100%;
    margin: 0;
}

footer {
    position: fixed;
    bottom: 0;
    width: 100%;
    height: 20px;
    background: #031633;
    z-index: 100;
}

.page-header,
.panel {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
}

.page-header {
    padding: 18px 20px;
    margin-bottom: 18px;
}

.page-header h1 {
    margin: 0 0 8px 0;
    font-family: "MagicCards", serif;
    color: rgb(242, 124, 17);
    letter-spacing: 1.2px;
    word-spacing: 8px;
    font-size: 2em;
}

.page-subtitle {
    margin: 0;
    color: #c3cad4;
}

.summary-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 14px;
}

.summary-pill {
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 8px 10px;
    color: #f8f9fa;
    background: rgba(26, 26, 26, 0.55);
}

.summary-pill strong {
    color: rgb(242, 124, 17);
}

.toast {
    margin-bottom: 18px;
    border-radius: 8px;
    border: 1px solid #3a3a3a;
    padding: 12px 16px;
}

.toast.success {
    background: rgba(25, 90, 45, 0.35);
    border-color: rgba(45, 155, 80, 0.5);
}

.toast.error {
    background: rgba(120, 30, 30, 0.35);
    border-color: rgba(210, 80, 80, 0.6);
}

.panel {
    padding: 16px;
}

.import-panel {
    margin-bottom: 18px;
}

.import-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.toolbar {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) minmax(180px, 260px) minmax(140px, 180px) auto;
    gap: 10px;
    margin-bottom: 14px;
    align-items: center;
}

input[type="text"],
select {
    width: 100%;
    padding: 10px 12px;
    border-radius: 6px;
    border: 1px solid #3a3a3a;
    background: rgba(26, 26, 26, 0.85);
    color: #e9efff;
    box-sizing: border-box;
}

input[type="text"]:focus,
select:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 2px rgba(242, 124, 17, 0.15);
}

.table-container {
    max-height: calc(100vh - 330px);
    overflow: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #3a3a3a;
    vertical-align: top;
}

th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: linear-gradient(135deg, rgba(58, 58, 58, 0.98), rgba(48, 48, 48, 0.98));
    color: rgb(242, 124, 17);
    font-family: "MagicCards", serif;
    word-spacing: 4px;
}

tr:hover {
    background: rgba(58, 58, 58, 0.45);
}

.quest-title {
    color: #f8f9fa;
    font-weight: bold;
}

.muted {
    color: #9fa4b0;
    font-size: 0.9em;
}

.state-pill {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 0.85em;
    font-weight: bold;
    white-space: nowrap;
}

.state-pill.active {
    background: rgba(45, 155, 80, 0.25);
    border: 1px solid rgba(45, 155, 80, 0.6);
}

.state-pill.inactive {
    background: rgba(180, 70, 70, 0.2);
    border: 1px solid rgba(180, 70, 70, 0.55);
}

.state-pill.runtime {
    background: rgba(80, 115, 190, 0.22);
    border: 1px solid rgba(105, 145, 220, 0.5);
}

.row-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-button.small {
    padding: 6px 10px;
    font-size: 0.85em;
}

.tradquest-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.72);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 24px;
}

.tradquest-modal-backdrop.open {
    display: flex !important;
}

.tradquest-modal {
    display: block !important;
    position: relative;
    width: min(1180px, 96vw);
    max-height: 90vh;
    background: #202020;
    border: 1px solid rgba(242, 124, 17, 0.45);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.45);
}

.tradquest-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid #3a3a3a;
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
}

.tradquest-modal-title {
    margin: 0;
    color: rgb(242, 124, 17);
    font-family: "MagicCards", serif;
    letter-spacing: 1px;
    word-spacing: 6px;
}

.tradquest-modal-body {
    padding: 16px 18px;
    overflow: auto;
    max-height: calc(90vh - 132px);
}

.tradquest-modal-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
}

.tradquest-modal-tab {
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    background: rgba(26, 26, 26, 0.85);
    color: #e9efff;
    padding: 8px 12px;
    cursor: pointer;
}

.tradquest-modal-tab.active {
    color: rgb(242, 124, 17);
    border-color: rgba(242, 124, 17, 0.55);
}

.tradquest-modal-pane {
    display: none;
}

.tradquest-modal-pane.active {
    display: block;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}

.detail-card {
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 10px;
    background: rgba(26, 26, 26, 0.55);
    min-width: 0;
}

.detail-card .label {
    color: #9fa4b0;
    font-size: 0.85em;
    margin-bottom: 4px;
}

.detail-card .value {
    color: #f8f9fa;
    overflow-wrap: anywhere;
}

.beat-list {
    display: grid;
    gap: 12px;
}

.beat-card {
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 12px;
    background: rgba(26, 26, 26, 0.55);
}

.beat-card h3 {
    margin: 0 0 8px 0;
    color: #f8f9fa;
}

.kv {
    margin-top: 8px;
    color: #c3cad4;
}

.kv strong {
    color: rgb(242, 124, 17);
}

pre {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 12px;
    background: rgba(12, 12, 12, 0.75);
    color: #e9efff;
    max-height: 58vh;
    overflow: auto;
}

@media (max-width: 900px) {
    main {
        padding-left: 3%;
        padding-right: 3%;
    }

    .toolbar {
        grid-template-columns: 1fr;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .table-container {
        max-height: none;
    }
}
</style>
<?php if (!$isEmbed): ?>
<?php include($enginePath . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php"); ?>
<?php endif; ?>

<main>
    <div class="page-header">
        <h1>Traditional Quests</h1>
        <p class="page-subtitle">Inspect and enable or disable the imported non-radiant Skyrim quest definitions used by CHIM AI quest progression.</p>
        <div class="summary-row">
            <div class="summary-pill"><strong><?php echo intval($questCount); ?></strong> imported quests</div>
            <div class="summary-pill"><strong><?php echo intval($activeCount); ?></strong> enabled</div>
            <div class="summary-pill"><strong><?php echo intval($questCount - $activeCount); ?></strong> disabled</div>
            <div class="summary-pill"><strong><?php echo intval(count($pluginNames)); ?></strong> plugins</div>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="toast <?php echo $messageType === "error" ? "error" : "success"; ?>">
            <?php echo traditional_quests_h($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!$tableReady): ?>
        <div class="toast error">Quest tables are not ready. Run the database update before using this dashboard.</div>
    <?php else: ?>
        <section class="panel import-panel">
            <h2 style="margin: 0; color: rgb(242, 124, 17); font-family: 'MagicCards', serif; word-spacing: 6px;">Import Traditional Quest CSV</h2>
            <p class="muted">Manual upload uses the same format as auto imports from <code>Data/CHIM/*_tradquest.csv</code>. Required format: <code>quest_key, quest_editor_id, title, quest_plugin, quest_form_id, active, skeleton_json</code>.</p>
            <form method="post" enctype="multipart/form-data" class="import-row">
                <input type="file" name="tradquest_csv_file" accept=".csv" required>
                <button type="submit" name="submit_tradquest_csv" value="1" class="action-button upload-csv">Upload Quest CSV</button>
                <a class="action-button download-csv" href="?embed=<?php echo $isEmbed ? "1" : "0"; ?>&action=download_example_tradquest_csv">Download Example CSV</a>
            </form>
        </section>

        <section class="panel">
            <div class="toolbar">
                <input id="quest-search" type="text" placeholder="Search by title, editor ID, plugin, or form ID...">
                <select id="plugin-filter">
                    <option value="">All plugins</option>
                    <?php foreach ($pluginNames as $pluginName): ?>
                        <option value="<?php echo traditional_quests_h(strtolower($pluginName)); ?>"><?php echo traditional_quests_h($pluginName); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="active-filter">
                    <option value="">All states</option>
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
                <form method="post" onsubmit="return confirm('Reset runtime state for every imported quest? This preserves definitions and enabled states.');">
                    <input type="hidden" name="action" value="reset_all_state">
                    <button type="submit" class="btn-danger action-button">Reset All Quest States</button>
                </form>
            </div>

            <div class="table-container">
                <table id="quests-table">
                    <thead>
                        <tr>
                            <th>Enabled</th>
                            <th>Quest</th>
                            <th>Plugin</th>
                            <th>Form ID</th>
                            <th>Runtime</th>
                            <th>Beats</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $active = traditional_quests_bool($row["active"] ?? true);
                        $plugin = trim((string) ($row["source_plugin"] ?? ""));
                        if ($plugin === "") {
                            $plugin = "Unknown";
                        }
                        $runState = trim((string) ($row["run_state"] ?? ""));
                        if ($runState === "") {
                            $runState = "not tracked";
                        }
                        $currentStage = trim((string) ($row["current_stage"] ?? ""));
                        $lastGamets = trim((string) ($row["last_gamets"] ?? ""));
                        $searchBlob = strtolower(implode(" ", [
                            $row["quest_key"] ?? "",
                            $row["quest_editor_id"] ?? "",
                            $row["title"] ?? "",
                            $plugin,
                            $row["source_form_id"] ?? "",
                        ]));
                        ?>
                        <tr
                            data-search="<?php echo traditional_quests_h($searchBlob); ?>"
                            data-plugin="<?php echo traditional_quests_h(strtolower($plugin)); ?>"
                            data-active="<?php echo $active ? "enabled" : "disabled"; ?>"
                        >
                            <td>
                                <span class="state-pill <?php echo $active ? "active" : "inactive"; ?>">
                                    <?php echo $active ? "Enabled" : "Disabled"; ?>
                                </span>
                            </td>
                            <td>
                                <div class="quest-title"><?php echo traditional_quests_h($row["title"] ?? ""); ?></div>
                                <div class="muted"><?php echo traditional_quests_h($row["quest_editor_id"] ?? ""); ?> / <?php echo traditional_quests_h($row["quest_key"] ?? ""); ?></div>
                            </td>
                            <td><?php echo traditional_quests_h($plugin); ?></td>
                            <td><?php echo traditional_quests_h($row["source_form_id"] ?? ""); ?></td>
                            <td>
                                <span class="state-pill runtime"><?php echo traditional_quests_h($runState); ?></span>
                                <?php if ($currentStage !== ""): ?>
                                    <div class="muted">Stage <?php echo traditional_quests_h($currentStage); ?></div>
                                <?php endif; ?>
                                <?php if ($lastGamets !== ""): ?>
                                    <div class="muted">Game TS <?php echo traditional_quests_h($lastGamets); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo intval($row["fired_count"] ?? 0); ?> / <?php echo intval($row["beat_count"] ?? 0); ?>
                            </td>
                            <td><?php echo traditional_quests_h($row["updated_at"] ?? ""); ?></td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="action-button edit small"
                                        data-quest-key="<?php echo traditional_quests_h($row["quest_key"] ?? ""); ?>"
                                        onclick="openQuestModal(this)"
                                    >View</button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="quest_key" value="<?php echo traditional_quests_h($row["quest_key"] ?? ""); ?>">
                                        <button type="submit" class="action-button small"><?php echo $active ? "Disable" : "Enable"; ?></button>
                                    </form>
                                    <?php $resetConfirm = "Reset runtime state for " . (string) ($row["title"] ?? $row["quest_key"] ?? "this quest") . "? This preserves the quest definition and enabled state."; ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm(<?php echo traditional_quests_js_string($resetConfirm); ?>);">
                                        <input type="hidden" name="action" value="reset_quest_state">
                                        <input type="hidden" name="quest_key" value="<?php echo traditional_quests_h($row["quest_key"] ?? ""); ?>">
                                        <button type="submit" class="btn-danger action-button small">Reset State</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8">No imported quest definitions found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<div id="quest-modal-backdrop" class="tradquest-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="quest-modal-title">
    <div class="tradquest-modal">
        <div class="tradquest-modal-header">
            <div>
                <h2 id="quest-modal-title" class="tradquest-modal-title">Quest</h2>
                <div id="quest-modal-subtitle" class="muted"></div>
            </div>
            <button type="button" class="btn-danger action-button small" onclick="closeQuestModal()">Close</button>
        </div>
        <div class="tradquest-modal-body">
            <div class="tradquest-modal-tabs">
                <button type="button" class="tradquest-modal-tab active" data-pane="steps" onclick="setModalPane('steps')">Steps</button>
                <button type="button" class="tradquest-modal-tab" data-pane="raw" onclick="setModalPane('raw')">Raw JSON</button>
                <button type="button" class="tradquest-modal-tab" data-pane="state" onclick="setModalPane('state')">Runtime State</button>
            </div>

            <div id="modal-pane-steps" class="tradquest-modal-pane active">
                <div id="quest-detail-summary" class="detail-grid"></div>
                <div id="quest-description" class="muted" style="margin-bottom: 14px;"></div>
                <div id="quest-beat-list" class="beat-list"></div>
            </div>

            <div id="modal-pane-raw" class="tradquest-modal-pane">
                <pre id="quest-raw-json">Loading...</pre>
            </div>

            <div id="modal-pane-state" class="tradquest-modal-pane">
                <pre id="quest-state-json">Loading...</pre>
            </div>
        </div>
    </div>
</div>

<script>
var detailUrlBase = "<?php echo $webRoot; ?>/ui/addons/snqe/traditional_quests.php?embed=1&action=quest_detail&quest_key=";

function jsonSummary(value) {
    if (value === null || value === undefined) {
        return "";
    }

    if (Array.isArray(value)) {
        return value.map(function(item) {
            return jsonSummary(item);
        }).filter(Boolean).join(", ");
    }

    if (typeof value === "object") {
        return JSON.stringify(value);
    }

    return String(value);
}

function escapeHtml(value) {
    if (value === null || value === undefined) {
        value = "";
    }

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function renderDetailCard(label, value) {
    return ""
        + "<div class=\"detail-card\">"
        + "<div class=\"label\">" + escapeHtml(label) + "</div>"
        + "<div class=\"value\">" + escapeHtml(value || "-") + "</div>"
        + "</div>";
}

function renderBeat(beat, index) {
    var action = jsonSummary(beat.action || {});
    var triggers = jsonSummary(beat.triggers || []);
    var prereqs = jsonSummary(beat.prerequisites || []);
    var downstream = jsonSummary(beat.downstream || []);
    var conditions = jsonSummary(beat.conditions || []);
    var html = "";

    html += "<article class=\"beat-card\">";
    html += "<h3>" + (index + 1) + ". " + escapeHtml(beat.id || "Unnamed Beat") + "</h3>";
    if (beat.comment) {
        html += "<div class=\"muted\">" + escapeHtml(beat.comment) + "</div>";
    }
    html += "<div class=\"kv\"><strong>Focus NPC:</strong> " + escapeHtml(beat.focus_npc || "-") + "</div>";
    html += "<div class=\"kv\"><strong>Trigger Mode:</strong> " + escapeHtml(beat.trigger_mode || "-") + "</div>";
    html += "<div class=\"kv\"><strong>Natural Start:</strong> " + (beat.allow_natural_start ? "Yes" : "No") + "</div>";
    html += "<div class=\"kv\"><strong>Action:</strong> " + escapeHtml(action || "-") + "</div>";
    html += "<div class=\"kv\"><strong>Triggers:</strong> " + escapeHtml(triggers || "-") + "</div>";
    html += "<div class=\"kv\"><strong>Prerequisites:</strong> " + escapeHtml(prereqs || "-") + "</div>";
    html += "<div class=\"kv\"><strong>Downstream:</strong> " + escapeHtml(downstream || "-") + "</div>";
    if (conditions) {
        html += "<div class=\"kv\"><strong>Conditions:</strong> " + escapeHtml(conditions) + "</div>";
    }
    html += "</article>";

    return html;
}

function setModalPane(pane) {
    var tabs = document.querySelectorAll(".tradquest-modal-tab");
    var panes = document.querySelectorAll(".tradquest-modal-pane");
    var i;

    for (i = 0; i < tabs.length; i++) {
        tabs[i].classList.toggle("active", tabs[i].getAttribute("data-pane") === pane);
    }

    for (i = 0; i < panes.length; i++) {
        panes[i].classList.toggle("active", panes[i].id === "modal-pane-" + pane);
    }
}

function renderQuestModal(payload) {
    var quest = payload.quest;
    var beatHtml = "";
    var i;

    document.getElementById("quest-modal-title").textContent = quest.title || quest.quest_editor_id || quest.quest_key;
    document.getElementById("quest-modal-subtitle").textContent = (quest.quest_editor_id || "") + " / " + (quest.quest_key || "");
    document.getElementById("quest-detail-summary").innerHTML = [
        renderDetailCard("Enabled", quest.active ? "Yes" : "No"),
        renderDetailCard("Plugin", quest.source_plugin),
        renderDetailCard("Form ID", quest.source_form_id),
        renderDetailCard("Runtime", quest.run_state || "not tracked"),
        renderDetailCard("Current Stage", quest.current_stage),
        renderDetailCard("Last Game TS", quest.last_gamets),
        renderDetailCard("Source Path", quest.source_path),
        renderDetailCard("Updated", quest.updated_at)
    ].join("");

    document.getElementById("quest-description").textContent = quest.description || "";
    if (quest.beats && quest.beats.length) {
        for (i = 0; i < quest.beats.length; i++) {
            beatHtml += renderBeat(quest.beats[i], i);
        }
    } else {
        beatHtml = "<div class=\"muted\">No beats are defined for this quest.</div>";
    }

    document.getElementById("quest-beat-list").innerHTML = beatHtml;
    document.getElementById("quest-raw-json").textContent = quest.raw_json || "{}";
    document.getElementById("quest-state-json").textContent = quest.state_json || "{}";
}

function showQuestModalError(message) {
    document.getElementById("quest-modal-title").textContent = "Could not load quest";
    document.getElementById("quest-beat-list").innerHTML = "<div class=\"toast error\">" + escapeHtml(message || "Unknown error.") + "</div>";
    document.getElementById("quest-raw-json").textContent = "{}";
    document.getElementById("quest-state-json").textContent = "{}";
}

function openQuestModal(button) {
    var questKey = button.getAttribute("data-quest-key") || "";
    var backdrop;
    var xhr;

    if (!questKey) {
        return;
    }

    backdrop = document.getElementById("quest-modal-backdrop");
    backdrop.classList.add("open");
    setModalPane("steps");
    document.getElementById("quest-modal-title").textContent = "Loading...";
    document.getElementById("quest-modal-subtitle").textContent = "";
    document.getElementById("quest-detail-summary").innerHTML = "";
    document.getElementById("quest-description").textContent = "";
    document.getElementById("quest-beat-list").innerHTML = "";
    document.getElementById("quest-raw-json").textContent = "Loading...";
    document.getElementById("quest-state-json").textContent = "Loading...";

    xhr = new XMLHttpRequest();
    xhr.open("GET", detailUrlBase + encodeURIComponent(questKey), true);
    xhr.onreadystatechange = function() {
        var payload;

        if (xhr.readyState !== 4) {
            return;
        }

        try {
            payload = JSON.parse(xhr.responseText || "{}");
        } catch (error) {
            showQuestModalError("Quest detail returned invalid JSON.");
            return;
        }

        if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
            showQuestModalError(payload.error || "Quest could not be loaded.");
            return;
        }

        renderQuestModal(payload);
    };
    xhr.onerror = function() {
        showQuestModalError("Quest detail request failed.");
    };
    xhr.send();
}

function closeQuestModal() {
    document.getElementById("quest-modal-backdrop").classList.remove("open");
}

function applyQuestFilters() {
    var searchEl = document.getElementById("quest-search");
    var pluginEl = document.getElementById("plugin-filter");
    var activeEl = document.getElementById("active-filter");
    var search = searchEl ? (searchEl.value || "").toLowerCase().trim() : "";
    var plugin = pluginEl ? (pluginEl.value || "") : "";
    var active = activeEl ? (activeEl.value || "") : "";
    var rows = document.querySelectorAll("#quests-table tbody tr[data-search]");
    var i;

    for (i = 0; i < rows.length; i++) {
        var row = rows[i];
        var matchesSearch = !search || (row.getAttribute("data-search") || "").indexOf(search) !== -1;
        var matchesPlugin = !plugin || row.getAttribute("data-plugin") === plugin;
        var matchesActive = !active || row.getAttribute("data-active") === active;
        row.style.display = (matchesSearch && matchesPlugin && matchesActive) ? "" : "none";
    }
}

if (document.getElementById("quest-search")) {
    document.getElementById("quest-search").addEventListener("input", applyQuestFilters);
}
if (document.getElementById("plugin-filter")) {
    document.getElementById("plugin-filter").addEventListener("change", applyQuestFilters);
}
if (document.getElementById("active-filter")) {
    document.getElementById("active-filter").addEventListener("change", applyQuestFilters);
}
if (document.getElementById("quest-modal-backdrop")) {
    document.getElementById("quest-modal-backdrop").addEventListener("click", function(event) {
        if (event.target === this) {
            closeQuestModal();
        }
    });
}
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeQuestModal();
    }
});
</script>

<?php
include($enginePath . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
