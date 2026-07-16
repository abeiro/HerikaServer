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
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "quest_reference_data.php");

$db = $GLOBALS["db"];

$isEmbed = (isset($_GET["embed"]) && $_GET["embed"] === "1");

$scriptPath = $_SERVER["SCRIPT_NAME"];
$uiPos = strpos($scriptPath, "/ui/");
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : "";
$webRoot = rtrim($webRoot, "/");

function quest_ref_bool($value)
{
    if (is_bool($value)) {
        return $value;
    }
    $cn = strtolower(trim((string) $value));
    return in_array($cn, ["1", "true", "t", "yes", "y", "on"], true);
}

function quest_ref_normalize_key($value)
{
    return strtolower(trim((string) $value));
}

function quest_ref_parse_formids_input($rawInput, $datasetName = '', $keyName = '')
{
    $raw = trim((string) $rawInput);
    if ($raw === "") {
        return [[], [], []];
    }

    $tokens = [];
    $trimmed = $raw;
    if (strlen($trimmed) >= 2 && $trimmed[0] === "[" && substr($trimmed, -1) === "]") {
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $tokens = $decoded;
        }
    }

    if (empty($tokens)) {
        $tokens = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }

    $valid = [];
    $invalid = [];
    $unresolved = [];
    $seen = [];
    foreach ($tokens as $token) {
        $tokenCn = trim((string) $token, " \t\n\r\0\x0B\"'");
        if ($tokenCn === "") {
            continue;
        }

        $classified = quest_reference_classify_dataset_formid_for_text_storage(
            $datasetName,
            $keyName,
            $tokenCn
        );
        $canonical = $classified['value'];
        if ($canonical === null || $canonical === '') {
            $invalid[] = $tokenCn;
            continue;
        }
        if ($classified['status'] === 'unresolved') {
            $unresolved[] = $tokenCn;
        }

        $dedupeKey = strtolower($canonical);

        if (!isset($seen[$dedupeKey])) {
            $seen[$dedupeKey] = true;
            $valid[] = $canonical;
        }
    }

    return [$valid, $invalid, $unresolved];
}

function quest_ref_decode_formids_json($value, $datasetName = '', $keyName = '')
{
    if (is_array($value)) {
        $arr = $value;
    } else {
        $arr = [];
        $cn = trim((string) $value);
        if ($cn !== "") {
            $decoded = json_decode($cn, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $arr = $decoded;
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($arr as $item) {
        $itemCn = trim((string) $item);
        if ($itemCn === "") {
            continue;
        }

        $canonical = quest_reference_canonicalize_dataset_formid_for_text_storage(
            $datasetName,
            $keyName,
            $itemCn
        );
        if ($canonical === null || $canonical === '') {
            $canonical = $itemCn;
        }

        $dedupeKey = strtolower($canonical);
        if (!isset($seen[$dedupeKey])) {
            $seen[$dedupeKey] = true;
            $normalized[] = $canonical;
        }
    }

    return $normalized;
}

function quest_ref_upsert_entry($db, $tableName, $keyColumn, $keyName, $formids, $active, $note)
{
    $keyCn = $db->escape($keyName);
    $jsonCn = $db->escape(json_encode(array_values($formids)));
    $noteCn = $db->escape((string) $note);
    $activeSql = $active ? "true" : "false";

    $ok = $db->execQuery("
        INSERT INTO public.{$tableName} ({$keyColumn}, formids_json, active, note)
        VALUES ('{$keyCn}', '{$jsonCn}'::jsonb, {$activeSql}, '{$noteCn}')
        ON CONFLICT ({$keyColumn})
        DO UPDATE SET
            formids_json = EXCLUDED.formids_json,
            active = EXCLUDED.active,
            note = EXCLUDED.note,
            updated_at = now()
    ");

    if (!$ok) {
        throw new Exception("Failed to save entry '{$keyName}'.");
    }
}

function quest_ref_example_rows($datasetName)
{
    $examples = [
        "item_types" => [
            ["key_name" => "weapon", "formids" => ["Skyrim.esm|0001397E", "Skyrim.esm|00013989"], "active" => "true", "note" => "weapons"],
            ["key_name" => "armor", "formids" => ["Skyrim.esm|00012E49", "Skyrim.esm|00013952"], "active" => "true", "note" => "armor pieces"],
        ],
        "npc_templates" => [
            ["key_name" => "male_redguard", "formids" => ["Skyrim.esm|0006762E", "Skyrim.esm|00058B3F", "Skyrim.esm|0010F5A1"], "active" => "true", "note" => "grouped template sample"],
            ["key_name" => "female_nord", "formids" => ["Skyrim.esm|00013BBF", "Skyrim.esm|00013BBE"], "active" => "true", "note" => "grouped template sample"],
        ],
        "npc_own_templates" => [
            ["key_name" => "balgruuf_the_greater", "formids" => ["Skyrim.esm|00013BAA"], "active" => "true", "note" => "single NPC override"],
            ["key_name" => "farengar_secret_fire", "formids" => ["Skyrim.esm|00013BBB"], "active" => "false", "note" => "inactive example"],
        ],
        "outfit" => [
            ["key_name" => "warrior", "formids" => ["Skyrim.esm|000D191F", "Skyrim.esm|000D1920"], "active" => "true", "note" => "heavy outfit sample"],
            ["key_name" => "mage", "formids" => ["Skyrim.esm|000D1922", "Skyrim.esm|000D1923"], "active" => "true", "note" => "robe outfit sample"],
        ],
    ];

    return $examples[$datasetName] ?? [];
}

function quest_ref_download_example_csv($datasetName, $datasetLabel, $keyColumn)
{
    $filename = "quest_reference_" . $datasetName . "_example.csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen("php://output", "w");
    if ($output === false) {
        exit;
    }

    fputcsv($output, [$keyColumn, "formids_json", "active", "note"]);
    foreach (quest_ref_example_rows($datasetName) as $row) {
        $formids = isset($row["formids"]) && is_array($row["formids"]) ? $row["formids"] : [];
        fputcsv($output, [
            $row["key_name"] ?? "",
            json_encode($formids),
            $row["active"] ?? "true",
            $row["note"] ?? ("example row for " . $datasetLabel),
        ]);
    }

    fclose($output);
    exit;
}

function quest_ref_csv_get_map($headerCells, $keyColumn)
{
    $map = ["key" => 0, "formids" => 1, "active" => 2, "note" => 3];
    $foundAlias = false;

    $keyAliases = ["key", "key_name", $keyColumn, "location_key", "type_key", "template_key", "class_key"];
    $formidsAliases = ["formids_json", "formids", "formids_input", "formid_list", "form_ids"];
    $activeAliases = ["active", "enabled", "is_active"];
    $noteAliases = ["note", "description", "comment"];

    foreach ($headerCells as $idx => $cell) {
        $name = strtolower(trim((string) $cell));
        if ($name === "") {
            continue;
        }

        if (in_array($name, $keyAliases, true)) {
            $map["key"] = $idx;
            $foundAlias = true;
            continue;
        }
        if (in_array($name, $formidsAliases, true)) {
            $map["formids"] = $idx;
            $foundAlias = true;
            continue;
        }
        if (in_array($name, $activeAliases, true)) {
            $map["active"] = $idx;
            $foundAlias = true;
            continue;
        }
        if (in_array($name, $noteAliases, true)) {
            $map["note"] = $idx;
            $foundAlias = true;
            continue;
        }
    }

    return $foundAlias ? $map : null;
}

function quest_ref_csv_cell($row, $index)
{
    if ($index === null || !is_int($index) || $index < 0) {
        return "";
    }

    return isset($row[$index]) ? trim((string) $row[$index]) : "";
}

$datasetLabels = [
    "item_types" => "Item Types",
    "npc_templates" => "NPC Templates",
    "npc_own_templates" => "NPC Own Templates",
    "outfit" => "Outfits",
];

$datasetCfg = quest_reference_dataset_config();
$datasetName = isset($_GET["dataset"]) ? trim((string) $_GET["dataset"]) : "item_types";
if (!isset($datasetCfg[$datasetName])) {
    $datasetName = "item_types";
}

$cfg = $datasetCfg[$datasetName];
$tableName = $cfg["table"];
$keyColumn = $cfg["key_column"];
$datasetLabel = $datasetLabels[$datasetName] ?? $datasetName;
$tableReady = quest_reference_table_exists($tableName)
    && quest_reference_column_exists($tableName, $keyColumn)
    && quest_reference_column_exists($tableName, "formids_json");

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["action"]) && $_GET["action"] === "download_example_csv") {
    quest_ref_download_example_csv($datasetName, $datasetLabel, $keyColumn);
}

$message = "";
$messageType = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_csv"])) {
    if (!$tableReady) {
        $messageType = "error";
        $message = "Table {$tableName} is not ready. Expected columns: {$keyColumn}, formids_json.";
    } elseif (!isset($_FILES["csv_file"]) || intval($_FILES["csv_file"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messageType = "error";
        $message = "No CSV file uploaded, or upload failed.";
    } else {
        $tmpPath = (string) $_FILES["csv_file"]["tmp_name"];
        $fileName = (string) $_FILES["csv_file"]["name"];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($ext !== "csv") {
            $messageType = "error";
            $message = "Upload failed. Allowed file type: csv.";
        } else {
            $handle = fopen($tmpPath, "r");
            if ($handle === false) {
                $messageType = "error";
                $message = "Unable to read uploaded CSV file.";
            } else {
                $line = 0;
                $imported = 0;
                $skipped = 0;
                $warnInvalid = 0;
                $warnUnresolved = 0;
                $warnPreview = [];
                $map = ["key" => 0, "formids" => 1, "active" => 2, "note" => 3];
                $headerChecked = false;

                while (($row = fgetcsv($handle)) !== false) {
                    $line++;
                    if (!$headerChecked) {
                        $headerChecked = true;
                        $headerMap = quest_ref_csv_get_map($row, $keyColumn);
                        if ($headerMap !== null) {
                            $map = $headerMap;
                            continue;
                        }
                    }

                    if (!is_array($row) || count($row) === 0) {
                        $skipped++;
                        continue;
                    }

                    $keyName = quest_ref_normalize_key(quest_ref_csv_cell($row, $map["key"]));
                    $formidsInput = quest_ref_csv_cell($row, $map["formids"]);
                    $activeRaw = quest_ref_csv_cell($row, $map["active"]);
                    $note = quest_ref_csv_cell($row, $map["note"]);

                    if ($keyName === "") {
                        $skipped++;
                        continue;
                    }

                    [$formids, $invalidFormids, $unresolvedFormids] = quest_ref_parse_formids_input(
                        $formidsInput,
                        $datasetName,
                        $keyName
                    );
                    if (!empty($invalidFormids)) {
                        $warnInvalid += count($invalidFormids);
                        if (count($warnPreview) < 5) {
                            $warnPreview[] = $keyName . ": " . implode(", ", array_slice($invalidFormids, 0, 3));
                        }
                    }
                    $warnUnresolved += count($unresolvedFormids);

                    $active = ($activeRaw === "") ? true : quest_ref_bool($activeRaw);

                    try {
                        quest_ref_upsert_entry($db, $tableName, $keyColumn, $keyName, $formids, $active, $note);
                        $imported++;
                    } catch (Exception $e) {
                        $skipped++;
                    }
                }

                fclose($handle);

                $message = "CSV import complete for {$datasetLabel}: {$imported} row(s) imported/updated, {$skipped} skipped.";
                if ($warnInvalid > 0) {
                    $message .= " {$warnInvalid} invalid form ID value(s) were ignored";
                    if (!empty($warnPreview)) {
                        $message .= " (" . implode(" | ", $warnPreview) . ")";
                    }
                    $message .= ".";
                }
                if ($warnUnresolved > 0) {
                    $message .= " {$warnUnresolved} raw FormID value(s) could not be matched to the loaded plugin manifest and remain load-order dependent.";
                }
                $messageType = "success";
            }
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if (!$tableReady) {
        $messageType = "error";
        $message = "Table {$tableName} is not ready. Expected columns: {$keyColumn}, formids_json.";
    } else {
        $action = trim((string) $_POST["action"]);
        $rawKey = trim((string) ($_POST["key_name"] ?? ""));
        $keyName = quest_ref_normalize_key($rawKey);

        try {
            if ($action === "save_entry") {
                $formidsInput = (string) ($_POST["formids_input"] ?? "");
                $note = trim((string) ($_POST["note"] ?? ""));
                $active = isset($_POST["active"]) ? quest_ref_bool($_POST["active"]) : false;

                if ($keyName === "") {
                    throw new Exception("Key is required.");
                }

                [$formids, $invalidFormids, $unresolvedFormids] = quest_ref_parse_formids_input(
                    $formidsInput,
                    $datasetName,
                    $keyName
                );
                if (!empty($invalidFormids)) {
                    $preview = implode(", ", array_slice($invalidFormids, 0, 8));
                    if (count($invalidFormids) > 8) {
                        $preview .= ", ...";
                    }
                    throw new Exception("Invalid form IDs: {$preview}");
                }
                quest_ref_upsert_entry($db, $tableName, $keyColumn, $keyName, $formids, $active, $note);

                $message = "Entry saved for key '{$keyName}' with " . count($formids) . " form IDs.";
                if (!empty($unresolvedFormids)) {
                    $message .= " " . count($unresolvedFormids) . " raw FormID value(s) could not be matched to the loaded plugin manifest and remain load-order dependent.";
                }
                $messageType = "success";
            } elseif ($action === "delete_entry") {
                if ($keyName === "") {
                    throw new Exception("Key is required for delete.");
                }

                $keyCn = $db->escape($keyName);
                $db->execQuery("DELETE FROM public.{$tableName} WHERE {$keyColumn} = '{$keyCn}'");
                $message = "Deleted entry '{$keyName}'.";
                $messageType = "success";
            } elseif ($action === "toggle_active") {
                if ($keyName === "") {
                    throw new Exception("Key is required to toggle active.");
                }

                $keyCn = $db->escape($keyName);
                $db->execQuery("
                    UPDATE public.{$tableName}
                    SET active = NOT COALESCE(active, true),
                        updated_at = now()
                    WHERE {$keyColumn} = '{$keyCn}'
                ");
                $message = "Toggled active state for '{$keyName}'.";
                $messageType = "success";
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
        $rows = $db->fetchAll("
            SELECT
                {$keyColumn} AS key_name,
                active,
                note,
                formids_json,
                created_at,
                updated_at
            FROM public.{$tableName}
            ORDER BY {$keyColumn} ASC
        ");
    } catch (Exception $e) {
        $messageType = "error";
        $message = "Failed to load {$datasetLabel} data.";
        $rows = [];
    }
}

$normalizedRows = [];
$entryCount = 0;
$activeCount = 0;
$formidCount = 0;
foreach ($rows as $row) {
    $key = quest_ref_normalize_key($row["key_name"] ?? "");
    if ($key === "") {
        continue;
    }

    $active = quest_ref_bool($row["active"] ?? true);
    $formids = quest_ref_decode_formids_json(
        $row["formids_json"] ?? "[]",
        $datasetName,
        $key
    );
    $note = trim((string) ($row["note"] ?? ""));
    $updated = trim((string) ($row["updated_at"] ?? ""));

    $normalizedRows[] = [
        "key_name" => $key,
        "active" => $active,
        "formids" => $formids,
        "formids_text" => implode(", ", $formids),
        "note" => $note,
        "updated_at" => $updated,
    ];

    $entryCount++;
    if ($active) {
        $activeCount++;
    }
    $formidCount += count($formids);
}

$TITLE = "AI Quest Manager - {$datasetLabel}";

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
    padding-left: 10%;
    padding-right: 10%;
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

.page-header {
    text-align: center;
    margin-bottom: 20px;
    padding: 20px;
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
}

.page-header h1 {
    margin-bottom: 8px;
    font-family: "MagicCards", serif;
    word-spacing: 8px;
    letter-spacing: 1.2px;
    font-size: 2em;
    color: rgb(242, 124, 17);
}

.page-subtitle {
    margin: 0;
    color: #bbb;
    font-size: 1em;
}

.toast {
    margin-bottom: 20px;
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

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.content-section {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #3a3a3a;
}

.content-section h2 {
    margin-top: 0;
    margin-bottom: 12px;
    font-family: "MagicCards", serif;
    color: rgb(242, 124, 17);
    letter-spacing: 1px;
    word-spacing: 6px;
}

label {
    display: block;
    margin-top: 12px;
    margin-bottom: 6px;
    color: rgb(242, 124, 17);
    font-weight: bold;
}

input[type="text"],
textarea {
    width: 100%;
    padding: 10px 12px;
    border-radius: 6px;
    border: 1px solid #3a3a3a;
    background: rgba(26, 26, 26, 0.8);
    color: #e9efff;
    box-sizing: border-box;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

input[type="text"]:focus,
textarea:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 2px rgba(242, 124, 17, 0.15);
}

.checkbox-line {
    margin-top: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #ddd;
}

.button-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 14px;
}

.button-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.table-section {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    padding: 16px;
}

.table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.search-box {
    min-width: 260px;
}

.table-container {
    max-height: calc(100vh - 430px);
    overflow: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #3a3a3a;
    vertical-align: top;
}

th {
    background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9));
    color: rgb(242, 124, 17);
    font-family: "MagicCards", serif;
    word-spacing: 4px;
}

tr:hover {
    background: rgba(58, 58, 58, 0.45);
}

.state-pill {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 0.85em;
    font-weight: bold;
}

.state-pill.active {
    background: rgba(45, 155, 80, 0.25);
    border: 1px solid rgba(45, 155, 80, 0.6);
}

.state-pill.inactive {
    background: rgba(180, 70, 70, 0.2);
    border: 1px solid rgba(180, 70, 70, 0.55);
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

.muted {
    color: #9fa4b0;
    font-size: 0.9em;
}

@media (max-width: 900px) {
    main {
        padding-left: 4%;
        padding-right: 4%;
    }

    .content-grid {
        grid-template-columns: 1fr;
    }

    .table-container {
        max-height: calc(100vh - 380px);
    }
}
</style>
<?php if (!$isEmbed): ?>
<?php include($enginePath . "ui" . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php"); ?>
<?php endif; ?>

<main>
    <div class="page-header">
        <h1><?php echo htmlspecialchars($datasetLabel); ?></h1>
        <p class="page-subtitle">Manage quest reference entries stored in <code><?php echo htmlspecialchars($tableName); ?></code>. Stable <code>Plugin.esp|LocalFormId</code> references survive load-order changes. Raw FormIDs are converted automatically when their loaded plugin can be identified.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="toast <?php echo $messageType === "error" ? "error" : "success"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="content-grid">
        <section class="content-section">
            <h2>Add / Update Entry</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_entry">

                <label for="entry-key">Key</label>
                <input type="text" id="entry-key" name="key_name" placeholder="male_redguard" required>

                <label for="entry-formids">Form IDs</label>
                <textarea id="entry-formids" name="formids_input" placeholder='["Skyrim.esm|0006762E", "MyMod.esp|000058B3"]'></textarea>
                <p class="muted">Accepts JSON arrays or comma/newline-separated values. Use <code>Plugin.esp|LocalFormId</code> when possible. Raw runtime IDs are matched against the loaded plugin manifest; unresolved values are retained with a warning. Dynamic <code>FFxxxxxx</code> references are not supported.</p>

                <label for="entry-note">Note (optional)</label>
                <input type="text" id="entry-note" name="note" placeholder="synced from rolemaster hardcode">

                <label class="checkbox-line">
                    <input type="checkbox" id="entry-active" name="active" value="1" checked>
                    Entry is active (included in prompt)
                </label>

                <div class="button-row">
                    <button type="submit" class="action-button add-new">Save Entry</button>
                    <button type="button" class="action-button edit" onclick="resetEntryForm()">Clear</button>
                </div>
            </form>
        </section>

        <section class="content-section">
            <h2>Batch Upload</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <label for="csv_file">Select .csv file to upload:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="margin-top: 8px;">
                <div class="button-group">
                    <input type="submit" name="submit_csv" value="Upload CSV" class="action-button upload-csv">
                    <a
                        class="action-button download-csv"
                        href="?embed=<?php echo $isEmbed ? "1" : "0"; ?>&dataset=<?php echo urlencode($datasetName); ?>&action=download_example_csv"
                    >Download Example CSV</a>
                </div>
            </form>
            <p class="muted" style="margin-top: 12px;">
                CSV columns: <code><?php echo htmlspecialchars($keyColumn); ?></code>, <code>formids_json</code>, <code>active</code>, <code>note</code>.
            </p>
            <p class="muted">
                Quote JSON arrays in CSV, for example <code>"[""Skyrim.esm|0006762E"",""MyMod.esp|000058B3""]"</code>. Empty <code>active</code> defaults to <code>true</code>.
            </p>
            <p class="muted">
                Current rows: <?php echo intval($entryCount); ?> (active: <?php echo intval($activeCount); ?>, form IDs: <?php echo intval($formidCount); ?>).
            </p>
        </section>
    </div>

    <section class="table-section">
        <div class="table-toolbar">
            <h2 style="margin: 0; color: rgb(242, 124, 17); font-family: 'MagicCards', serif;">Entries</h2>
            <input id="table-search" class="search-box" type="text" placeholder="Search by key or form ID...">
        </div>

        <div class="table-container">
            <table id="entries-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Form IDs</th>
                        <th>Active</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($normalizedRows as $row): ?>
                    <?php
                    $encodedEntry = htmlspecialchars(json_encode([
                        "key_name" => $row["key_name"],
                        "formids_text" => $row["formids_text"],
                        "note" => $row["note"],
                        "active" => $row["active"] ? 1 : 0,
                    ]), ENT_QUOTES, "UTF-8");
                    $searchBlob = strtolower($row["key_name"] . " " . $row["formids_text"] . " " . $row["note"]);
                    ?>
                    <tr data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, "UTF-8"); ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($row["key_name"]); ?></strong><br>
                            <?php if ($row["note"] !== ""): ?>
                                <span class="muted"><?php echo htmlspecialchars($row["note"]); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row["formids_text"] !== "" ? $row["formids_text"] : "[]"); ?><br>
                            <span class="muted"><?php echo intval(count($row["formids"])); ?> entries</span>
                        </td>
                        <td>
                            <span class="state-pill <?php echo $row["active"] ? "active" : "inactive"; ?>">
                                <?php echo $row["active"] ? "Active" : "Inactive"; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row["updated_at"]); ?></td>
                        <td>
                            <div class="row-actions">
                                <button type="button" class="action-button edit small" data-entry="<?php echo $encodedEntry; ?>" onclick="fillEntryForm(this)">Edit</button>

                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="key_name" value="<?php echo htmlspecialchars($row["key_name"]); ?>">
                                    <button type="submit" class="action-button small"><?php echo $row["active"] ? "Deactivate" : "Activate"; ?></button>
                                </form>

                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete entry <?php echo htmlspecialchars($row["key_name"], ENT_QUOTES, "UTF-8"); ?>?');">
                                    <input type="hidden" name="action" value="delete_entry">
                                    <input type="hidden" name="key_name" value="<?php echo htmlspecialchars($row["key_name"]); ?>">
                                    <button type="submit" class="btn-danger action-button small">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($normalizedRows)): ?>
                    <tr>
                        <td colspan="5">No rows found for this dataset.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
function fillEntryForm(button) {
    const payload = button.getAttribute("data-entry");
    if (!payload) {
        return;
    }

    let data = null;
    try {
        data = JSON.parse(payload);
    } catch (e) {
        return;
    }

    document.getElementById("entry-key").value = data.key_name || "";
    document.getElementById("entry-formids").value = data.formids_text || "";
    document.getElementById("entry-note").value = data.note || "";
    document.getElementById("entry-active").checked = !!Number(data.active || 0);
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function resetEntryForm() {
    document.getElementById("entry-key").value = "";
    document.getElementById("entry-formids").value = "";
    document.getElementById("entry-note").value = "";
    document.getElementById("entry-active").checked = true;
}

document.getElementById("table-search").addEventListener("input", function() {
    const needle = (this.value || "").toLowerCase().trim();
    const rows = document.querySelectorAll("#entries-table tbody tr[data-search]");
    rows.forEach((row) => {
        const haystack = row.getAttribute("data-search") || "";
        row.style.display = haystack.indexOf(needle) !== -1 ? "" : "none";
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
