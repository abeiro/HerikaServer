<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

require_once("{$enginePath}/lib/core/npc_master.class.php");

$CONF_SAMPLE_VARS=extract_assignments("$enginePath/conf/conf.php");

//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from below file
include(__DIR__."/tmpl/ui_utils.php");

// Determine web root and include site chrome like oghma_upload
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$TITLE = "🧙 CHIM - NPC Master";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
/* Core styling alignment */
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main { padding-top: 40px; padding-bottom: 40px; }
h1.api-title { margin: 0 0 20px 0; font-family: 'MagicCards', serif; word-spacing: 8px; font-size: 2.2em; color: rgb(242, 124, 17); text-shadow: 2px 2px 4px rgba(0,0,0,0.5); text-align: center; }
</style>

<main>

<h1 class="api-title">NPC Master</h1>

<?php
$GLOBALS["db"] = new sql();
$npc = new NpcMaster();

// Helper: resolve race icon web path if file exists
if (!function_exists('race_icon_web_path')) {
    function race_icon_web_path($race, $webRoot){
        $in = strtolower((string)$race);
        $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
        $slug = implode('', $words);
        if ($slug === '') return '';
        $aliases = [
            'highelf'=>'altmer', 'altmer'=>'altmer',
            'woodelf'=>'bosmer', 'bosmer'=>'bosmer',
            'darkelf'=>'dunmer', 'dunmer'=>'dunmer',
            'orsimer'=>'orc', 'orc'=>'orc',
            'argonian'=>'argonian', 'khajiit'=>'khajiit',
            'breton'=>'breton', 'imperial'=>'imperial',
            'nord'=>'nord', 'redguard'=>'redguard',
            'oldpeople'=>'nord', 'oldpeoplerace'=>'nord',
        ];
        $base = $aliases[$slug] ?? $slug;
        $variants = [];
        $variants[] = $base;
        if (!empty($words)){
            $variants[] = implode('', $words);
            $variants[] = implode('-', $words);
            $variants[] = implode('_', $words);
        }
        $variants = array_values(array_unique(array_filter($variants, function($v){ return $v !== ''; })));
        $fsDir = __DIR__ . '/../images/races/';
        $exts = ['png','jpg','jpeg','webp','gif','svg'];
        foreach ($variants as $name){
            foreach ($exts as $ext){
                $fs = $fsDir . $name . '.' . $ext;
                if (file_exists($fs)) return $webRoot . '/ui/images/races/' . $name . '.' . $ext;
            }
        }
        return '';
    }
}

// Helper: map gender text to an icon character
if (!function_exists('gender_icon_char')) {
    function gender_icon_char($gender){
        $g = strtolower(trim((string)$gender));
        if ($g === '') return '';
        if ($g === 'female' || $g === 'f' || $g === 'woman' || $g === 'girl') return '♀';
        if ($g === 'male' || $g === 'm' || $g === 'man' || $g === 'boy') return '♂';
        if ($g === 'nonbinary' || $g === 'non-binary' || $g === 'nb' || $g === 'enby' || $g === 'other' || $g === 'agender' || $g === 'genderfluid') return '⚧';
        return '';
    }
}

// Helper: map gender text to a CSS class suffix for coloring
if (!function_exists('gender_icon_class')) {
    function gender_icon_class($gender){
        $g = strtolower(trim((string)$gender));
        if ($g === 'female' || $g === 'f' || $g === 'woman' || $g === 'girl') return 'gender-female';
        if ($g === 'male' || $g === 'm' || $g === 'man' || $g === 'boy') return 'gender-male';
        if ($g === 'nonbinary' || $g === 'non-binary' || $g === 'nb' || $g === 'enby' || $g === 'other' || $g === 'agender' || $g === 'genderfluid') return 'gender-nb';
        return '';
    }
}

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $npc->create($_POST);
    header("Location: npc_master.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $_POST["md5"]=md5($_POST["npc_name"]);
    $npc->update($_POST["id"], $_POST);
    header("Location: npc_master.php");
    exit;
}

// Inline update (AJAX) for modal save
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_npc"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            // Create new NPC and return ID
            $allowed = [
                'npc_name','npc_favorite','lock_profile','prompt_head','npc_static_bio','oghma_knowledge_tags','emote_moods','personality','relationships','occupation','appearance','skills','speechstyle','goals','voiceid','metadata','extended_data','gender','race','refid','profile_id','dynamic_profile','gamets_last_updated','base','core','tags'
            ];
            $cols = [];
            $vals = [];
            foreach ($allowed as $k){
                if (!array_key_exists($k, $_POST)) continue;
                $v = $_POST[$k];
                if (in_array($k, ['npc_favorite','lock_profile','dynamic_profile'], true)) { $v = ($v==='1'||$v===1||$v===true)?1:0; }
                $cols[] = $k;
                $vals[] = "'".$GLOBALS['db']->escape((string)$v)."'";
            }
            // md5 of npc_name if present
            if (!empty($_POST['npc_name'])){
                $cols[] = 'md5';
                $vals[] = "'".md5((string)$_POST['npc_name'])."'";
            }
            if (empty($cols)) { echo json_encode(["ok"=>false, "error"=>"No fields to insert"]); exit; }
            $sql = "INSERT INTO core_npc_master (".implode(',', $cols).") VALUES (".implode(',', $vals).") RETURNING id";
            $row = $GLOBALS['db']->fetchOne($sql);
            $newId = is_array($row) ? ($row['id'] ?? null) : null;
            if (!$newId) { echo json_encode(["ok"=>false, "error"=>"Insert failed"]); exit; }
            echo json_encode(["ok"=>true, "id"=>$newId]);
        } else {
            $_POST["md5"]=md5($_POST["npc_name"]);
            $ok = $npc->update($id, $_POST);
            if ($ok === false) {
                echo json_encode(["ok"=>false, "error"=>($npc->getLastError() ?? 'Update failed')]);
            } else {
                echo json_encode(["ok"=>true, "id"=>$id]);
            }
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Toggle favorite (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_favorite"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        $hasValue = array_key_exists('value', $_POST);
        if ($hasValue) {
            $v = ($_POST['value']==='1'||$_POST['value']===1||$_POST['value']===true)?1:0;
            $sql = "UPDATE core_npc_master SET npc_favorite = {$v} WHERE id = {$id} RETURNING npc_favorite";
        } else {
            $sql = "UPDATE core_npc_master SET npc_favorite = 1 - COALESCE(npc_favorite,0) WHERE id = {$id} RETURNING npc_favorite";
        }
        $row = $GLOBALS['db']->fetchOne($sql);
        $val = is_array($row) ? intval($row['npc_favorite'] ?? 0) : 0;
        echo json_encode(["ok"=>true, "favorite"=>$val]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Toggle lock (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_lock"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        $hasValue = array_key_exists('value', $_POST);
        if ($hasValue) {
            $v = ($_POST['value']==='1'||$_POST['value']===1||$_POST['value']===true)?1:0;
            $sql = "UPDATE core_npc_master SET lock_profile = {$v} WHERE id = {$id} RETURNING lock_profile";
        } else {
            $sql = "UPDATE core_npc_master SET lock_profile = 1 - COALESCE(lock_profile,0) WHERE id = {$id} RETURNING lock_profile";
        }
        $row = $GLOBALS['db']->fetchOne($sql);
        $val = is_array($row) ? intval($row['lock_profile'] ?? 0) : 0;
        echo json_encode(["ok"=>true, "locked"=>$val]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    $npc->delete($_GET["delete"]);
    header("Location: npc_master.php");
    exit;
}

// Fetch Data
$perPage = 9;
$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
if ($page < 1) $page = 1;

// Filters and sorting
$q = trim($_GET['q'] ?? '');
$alpha = strtolower($_GET['alpha'] ?? 'asc');
if (!in_array($alpha, ['asc','desc'], true)) { $alpha = 'asc'; }
$profileIdFilter = isset($_GET['profile_id']) ? trim((string)$_GET['profile_id']) : '';

// Preload profiles for filter dropdown
$profileRows = $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_profiles ORDER BY label ASC");
$profilesById = [];
foreach (($profileRows ?? []) as $pr) {
    $pid = (string)($pr['id'] ?? '');
    if ($pid !== '') $profilesById[$pid] = $pr['label'] ?? ('Profile #'.$pid);
}

$where = "1=1";
if ($q !== ''){
    $qEsc = "%".$GLOBALS['db']->escape($q)."%";
    // Match by name primarily; include a few related fields
    $where .= " and (npc_name ilike '".$qEsc."' or coalesce(race,'') ilike '".$qEsc."' or coalesce(voiceid,'') ilike '".$qEsc."' or coalesce(refid,'') ilike '".$qEsc."' or coalesce(tags,'') ilike '".$qEsc."')";
}
if ($profileIdFilter !== ''){
    $where .= " and profile_id = ".intval($profileIdFilter);
}

// Default: favorites first, then alphabetical by name
$order = "order by coalesce(npc_favorite,0) desc, lower(npc_name) ".$alpha.", id asc";

// Count with filters
$rowCountRow = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM core_npc_master where {$where}");
$totalRows = intval($rowCountRow['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$data = $npc->getAll("{$where} {$order} limit {$perPage} offset {$offset}");
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $npc->getById($_GET["edit"]);
}

// Partial list renderer for AJAX refresh of grid and pagination
if (isset($_GET['list']) && $_GET['list'] === '1') {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <div class="pagination">
      <div class="filter-inline">
        <input id="npc_search" type="text" placeholder="Search..." value="<?= htmlspecialchars($q) ?>" />
        <select id="npc_profile_filter" title="Filter by profile">
          <option value="">All Profiles</option>
          <?php foreach (($profileRows ?? []) as $pr): $pid=(string)($pr['id']??''); $lbl=$pr['label']??('Profile #'.$pid); ?>
            <option value="<?= htmlspecialchars($pid) ?>" <?= ($profileIdFilter!=='' && (string)$profileIdFilter===$pid)?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php $qbase = strtok($_SERVER['REQUEST_URI'], '?'); $make = function($p) use ($qbase){ return htmlspecialchars($qbase.'?page='.$p); }; ?>
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $make(1) ?>">First</a>
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $make(max(1,$page-1)) ?>">Prev</a>
      <?php for ($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
        <?php if ($p === $page): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= $make($p) ?>"><?= $p ?></a><?php endif; ?>
      <?php endfor; ?>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= $make(min($totalPages,$page+1)) ?>">Next</a>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= $make($totalPages) ?>">Last</a>
      <span style="border:none; background:transparent; color:rgb(242, 124, 17);">Page <?= $page ?> / <?= $totalPages ?></span>
      <span style="border:none; background:transparent; color:rgb(242, 124, 17);">Total <?= $totalRows ?></span>
      <button id="npc_create_btn" type="button" style="margin-left:8px;">+ Create NPC</button>
    </div>
    <div class="npc-grid">
    <?php foreach ($data as $row): ?>
        <?php $pid = (string)($row['profile_id'] ?? ''); $profLabel = $profilesById[$pid] ?? ''; $raceIcon = race_icon_web_path($row['race'] ?? '', $webRoot); $tagsVal = trim((string)($row['tags'] ?? '')); $tagsDisp = ($tagsVal === '') ? '' : $tagsVal; ?>
        <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
            <div class="npc-title">
                <div class="npc-title-left"><span class="npc-name"><?= htmlspecialchars($row["npc_name"]) ?></span> <?php $gch = gender_icon_char($row['gender'] ?? ''); $gcl = gender_icon_class($row['gender'] ?? ''); if ($gch!==''): ?><span class="npc-gender-icon <?= htmlspecialchars($gcl) ?>" title="<?= htmlspecialchars($row['gender'] ?? '') ?>"><?= $gch ?></span><?php endif; ?></div>
                <div class="npc-title-actions">
                    <?php if ($tagsDisp !== ''): ?>
                    <span class="npc-tags-top" title="<?= htmlspecialchars($tagsDisp) ?>"><?= htmlspecialchars($tagsDisp) ?></span>
                    <?php endif; ?>
                    <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>" title="Toggle favorite"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                    <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>" title="Toggle lock"><?php echo !empty($row["lock_profile"]) ? "🔒" : "🔓"; ?></a>
                    <a class="btn btn-trash" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');" title="Delete">🗑️</a>
                </div>
            </div>
            <div class="npc-divider"></div>
            <div class="npc-row">
                <div class="npc-fields">
                    <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"><?= htmlspecialchars($row["gender"] ?? "") ?></span></div>
                    <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"><?= htmlspecialchars($row["race"] ?? "") ?></span></div>
                    <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"><?= htmlspecialchars($row["voiceid"] ?? "") ?></span></div>
                    <div class="npc-line"><span class="npc-muted">RefID:</span> <span class="npc-refid"><?= htmlspecialchars($row["refid"] ?? "") ?></span></div>
                    <?php $oghmaVal = trim((string)($row["oghma_knowledge_tags"] ?? "")); $oghmaDisp = ($oghmaVal === "") ? "none" : $oghmaVal; ?>
                    <div class="npc-line"><span class="npc-muted">Oghma Tags:</span> <span class="npc-oghma"><?= htmlspecialchars($oghmaDisp) ?></span></div>
                    <div class="npc-line"><span class="npc-muted">Profile:</span> <span class="npc-profile"><?= htmlspecialchars($profLabel) ?></span></div>
                    <?php $tagsVal = trim((string)($row["tags"] ?? "")); $tagsDisp = ($tagsVal === "") ? "none" : $tagsVal; ?>
                    <div class="npc-line"><span class="npc-muted">Tags:</span> <span class="npc-tags"><?= htmlspecialchars($tagsDisp) ?></span></div>
                </div>
                <div class="npc-right">
                    <?php if ($raceIcon !== ''): ?>
                        <img class="npc-race-art" src="<?= htmlspecialchars($raceIcon) ?>" alt="Race icon" />
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
    exit;
}

// Lightweight endpoint: resolve race icon URL for a given race label
if (isset($_GET['race_icon'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $race = (string)($_GET['race'] ?? '');
    $url = race_icon_web_path($race, $webRoot);
    echo json_encode(['url' => $url]);
    exit;
}
?>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php endif; ?>

<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ob_end_clean(); ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>html,body{background:#2a2a2a;} main{background:#2a2a2a; padding:12px;} .form-container{background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px;}
.modal-inline-actions{display:flex; gap:6px; align-items:center; justify-content:flex-end; margin-bottom:8px;}
.modal-inline-actions .btn-toggle{background:transparent; border:none; padding:6px; color:#e9efff; font-size:22px; line-height:1; text-decoration:none; cursor:pointer;}
.modal-inline-actions .btn-toggle:hover{color: rgb(242, 124, 17); text-decoration:none;}
.modal-inline-actions .btn-toggle.active{color:#ffd700; font-weight:700;}
.modal-inline-actions .btn-toggle[data-lock]{color:#e9efff;}
.modal-inline-actions .btn-toggle.active[data-lock]{color: rgb(242, 124, 17);}
</style>
<form method="post" onsubmit='return false' style='display:block'>
<?php } else { ?>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
<?php } ?>
    <style>
    .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px 16px; }
    @media (max-width: 900px){ .form-grid { grid-template-columns: 1fr; } }
    .form-item { display:flex; flex-direction:column; gap:6px; }
    .form-item label { font-weight:700; color:rgb(242, 124, 17); }
    .form-item .hint { color:#e9efff; font-size:12px; line-height:1.35; }
    .form-item textarea { min-height:96px; }
    .form-item input[type="text"], .form-item textarea, .form-item select { background:#2a2a2a; color:#e9efff; border:1px solid #4a4a4a; border-radius:6px; padding:8px 10px; }
    .form-item input[type="checkbox"] { transform: scale(1.05); }
    .span-2 { grid-column: 1 / -1; }
    .checkbox-inline { display:flex; align-items:center; gap:8px; }
    </style>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <?php $isPartial = (isset($_GET['partial']) && $_GET['partial']=='1'); $isFav = !empty($editItem['npc_favorite']); $isLock = !empty($editItem['lock_profile']); ?>
    <?php if ($isPartial): ?>
    <div class="modal-inline-actions">
        <p style="margin:0; color:rgb(242, 124, 17) ;">Tags:</p>
        <input type="text" id="modal_tags_input" name="tags" value="<?= htmlspecialchars($editItem['tags'] ?? '') ?>" placeholder="tags" style="max-width:240px; font-size:12px; padding:4px 6px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;" title="Tags help with searching and grouping" />
        <a id="modal_fav_btn" class="btn btn-toggle<?= $isFav? ' active':'' ?>" href="#" title="Toggle favorite" data-favorite><?= $isFav? '★' : '☆' ?></a>
        <a id="modal_lock_btn" class="btn btn-toggle<?= $isLock? ' active':'' ?>" href="#" title="Toggle lock" data-lock><?= $isLock? '🔒' : '🔓' ?></a>
    </div>
    <?php endif; ?>

    <div class="form-grid">
        <div class="form-item span-2">
            <label for="npc_name">NPC Name</label>
            <input type="text" id="npc_name" name="npc_name" placeholder="e.g. Aela the Huntress" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">
            <small class="hint">Display name shown in UI and used to build prompts. Changing it will also update the MD5 key.</small>
        </div>

        <div class="form-item" style='<?= (isset($_GET['partial']) && $_GET['partial']=='1')?"display:none":"" ?>'>
            <label for="lock_profile">Lock Profile</label>
            <div class="checkbox-inline">
                <input type="checkbox" id="lock_profile" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
                <span class="hint">Prevents dynamic systems from modifying this NPC's profile.</span>
            </div>
        </div>

        <div class="form-item" style='<?= (isset($_GET['partial']) && $_GET['partial']=='1')?"display:none":"" ?>'>
            <label for="npc_favorite">Favorite</label>
            <div class="checkbox-inline">
                <input type="checkbox" id="npc_favorite" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
                <span class="hint">Pin this NPC for quick access.</span>
            </div>
        </div>

        <div class="form-item">
            <label for="gender">Gender</label>
            <input type="text" id="gender" name="gender" placeholder="e.g. female, male, nonbinary" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">
            <small class="hint">Used for pronouns and voice selection guidance.</small>
        </div>

        <div class="form-item">
            <label for="race">Race</label>
            <input type="text" id="race" name="race" placeholder="e.g. nord, dunmer, argonian" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">
            <small class="hint">Lore-accurate race label used in prompts.</small>
        </div>

        <div class="form-item">
            <label for="base">Base</label>
            <input type="text" id="base" name="base" placeholder="Base actor/form ID if applicable" value="<?= htmlspecialchars($editItem["base"] ?? "") ?>">
            <small class="hint">Optional: base form identifier or template this NPC derives from.</small>
        </div>

        <div class="form-item">
            <label for="refid">Ref ID</label>
            <input type="text" id="refid" name="refid" placeholder="Game reference ID (000...)" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>">
            <small class="hint">Skyrim reference ID for in-game linkage (optional).</small>
        </div>

        <div class="form-item">
            <label for="oghma_knowledge_tags">Oghma Tags</label>
            <input type="text" id="oghma_knowledge_tags" name="oghma_knowledge_tags" placeholder="Comma-separated knowledge tags" value="<?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?>">
            <small class="hint">Used by Oghma systems for knowledge lookup and indexing.</small>
        </div>

        <div class="form-item">
            <label for="voiceid">Voice ID</label>
            <input type="text" id="voiceid" name="voiceid" placeholder="Matches TTS voice identifier" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">
            <small class="hint">Identifier for the TTS backend (e.g., ElevenLabs, XTTS, etc.).</small>
        </div>

        <div class="form-item span-2">
            <label for="prompt_head">Prompt Head</label>
            <textarea id="prompt_head" name="prompt_head" placeholder="High-level system instructions injected before the core."><?= htmlspecialchars($editItem["prompt_head"] ?? "") ?></textarea>
            <small class="hint">System preamble inserted before other sections. Keep concise and stable.</small>
        </div>

        <div class="form-item span-2">
            <label for="core">Core</label>
            <textarea id="core" name="core" placeholder="Unchanging rules, boundaries, and core identity."><?= htmlspecialchars($editItem["core"] ?? "") ?></textarea>
            <small class="hint">Canonical constraints and non-negotiable behavior. Keep evergreen and tightly scoped.</small>
        </div>

        <div class="form-item span-2">
            <label for="npc_static_bio">Static Bio</label>
            <textarea id="npc_static_bio" name="npc_static_bio" placeholder="Fixed background, history, and facts."><?= htmlspecialchars($editItem["npc_static_bio"] ?? "") ?></textarea>
            <small class="hint">Persistent biography that never changes during play. Good for canon facts.</small>
        </div>

        <div class="form-item span-2">
            <label for="appearance">Appearance</label>
            <textarea id="appearance" name="appearance" placeholder="Physical appearance."><?= htmlspecialchars($editItem["appearance"] ?? "") ?></textarea>
            <small class="hint">Physical appearance.</small>
        </div>

        <div class="form-item">
            <label for="dynamic_profile">Dynamic Profile</label>
            <div class="checkbox-inline">
                <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?= !empty($editItem["dynamic_profile"]) ? "checked" : "" ?>>
                <span class="hint">Allow systems to evolve the profile based on gameplay events.</span>
            </div>
        </div>

        <div class="dynamic-profile-section span-2">
        <div class="form-item">
            <label for="personality">Personality</label>
            <textarea id="personality" name="personality" placeholder="Personality traits and speaking characteristics."><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>
            <small class="hint">Concise traits that guide tone and behavior. Avoid contradictions with Core.</small>
        </div>

        

        <div class="form-item">
            <label for="relationships">Relationships</label>
            <textarea id="relationships" name="relationships" placeholder="Key allies, rivals, factions, and opinions."><?= htmlspecialchars($editItem["relationships"] ?? "") ?></textarea>
            <small class="hint">Named entities the NPC knows and how they feel about them.</small>
        </div>

        <div class="form-item">
            <label for="occupation">Occupation</label>
            <textarea id="occupation" name="occupation" placeholder="Role, job, affiliations."><?= htmlspecialchars($editItem["occupation"] ?? "") ?></textarea>
            <small class="hint">Primary role or job. Include relevant guilds or factions.</small>
        </div>

        <div class="form-item">
            <label for="skills">Skills</label>
            <textarea id="skills" name="skills" placeholder="Strengths, abilities, and specialties."><?= htmlspecialchars($editItem["skills"] ?? "") ?></textarea>
            <small class="hint">Highlight notable competencies that affect dialogue choices.</small>
        </div>

        <div class="form-item">
            <label for="speechstyle">Speech Style</label>
            <textarea id="speechstyle" name="speechstyle" placeholder="Dialect, cadence, verbal tics."><?= htmlspecialchars($editItem["speechstyle"] ?? "") ?></textarea>
            <small class="hint">How they speak: formal, curt, poetic, archaic, etc.</small>
        </div>

        <div class="form-item">
            <label for="goals">Goals</label>
            <textarea id="goals" name="goals" placeholder="Short and long-term objectives."><?= htmlspecialchars($editItem["goals"] ?? "") ?></textarea>
            <small class="hint">Motivations that drive decisions and quest hooks.</small>
        </div>


        <div class="form-item span-2">
            <label for="emote_moods">Emote Moods</label>
            <textarea id="emote_moods" name="emote_moods" placeholder="Allowed mood/emote set (comma-separated).">
            <?= htmlspecialchars($editItem["emote_moods"] ?? "") ?></textarea>
            <small class="hint">Whitelist of mood/emote cues the NPC may use (e.g., calm, angry, playful).</small>
        </div>


        

        <div class="form-item span-2">
            <label for="extended_data">Extended Data (JSON)</label>
            <textarea name="extended_data" style="display:none"><?= htmlspecialchars($editItem["extended_data"] ?? "") ?></textarea>
            <small class="hint">Advanced: large or structured data blocks consumed by integrations.</small>
            <div id="extended_data"></div>
        </div>
    </div>

    <?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ?>
        <button type="button" id="npc_modal_save" class="btn-save" style="display:none"><?= $editItem ? "Update" : "Create" ?></button>
        <script>
        (function(){
            const save = document.getElementById('npc_modal_save');
            if (!save) return;
            save.addEventListener('click', async function(){
                const form = save.closest('form');
                const fd = new FormData(form);
                fd.append('inline_update_npc','1');
                if (!fd.has('id') && <?= json_encode(!empty($editItem['id'])) ?>){ fd.append('id', <?= json_encode($editItem['id'] ?? '') ?>); }
                const res = await fetch('npc_master.php', { method:'POST', body: fd });
                let json={}; try{ json=await res.json(); } catch(_e){}
                if (json && json.ok){
                    const payload = {};
                    form.querySelectorAll('input,textarea,select').forEach(el=>{ const n=el.name; if (!n) return; if (el.type==='checkbox'){ payload[n]=el.checked?1:0; } else { payload[n]=el.value; } });
                    // Ensure header tags input is captured
                    try { const ti = document.getElementById('modal_tags_input'); if (ti) payload['tags'] = ti.value; } catch(_){ }
                    const newId = json.id || payload.id || <?= json_encode($editItem['id'] ?? '') ?>;
                    payload.id = newId;
                    window.parent.postMessage({ type:'npc_saved', id: newId, data: payload }, '*');
                } else {
                    alert('Save failed: '+((json && json.error) ? json.error : res.status));
                }
            });
        })();
        </script>
        <script>
        (function(){
            const favBtn = document.getElementById('modal_fav_btn');
            const lockBtn = document.getElementById('modal_lock_btn');
            const idVal = <?= json_encode($editItem['id'] ?? '') ?>;
            if (favBtn && idVal){
                favBtn.addEventListener('click', async function(e){
                    e.preventDefault();
                    try{
                        const fd = new FormData(); fd.append('toggle_favorite','1'); fd.append('id', idVal);
                        const res = await fetch('npc_master.php', { method:'POST', body: fd });
                        let json={}; try{ json=await res.json(); }catch(_e){}
                        if (json && json.ok){ const active = Number(json.favorite||0)===1; favBtn.classList.toggle('active', active); favBtn.textContent = active ? '★' : '☆'; }
                    }catch(_e){}
                });
            }
            if (lockBtn && idVal){
                lockBtn.addEventListener('click', async function(e){
                    e.preventDefault();
                    try{
                        const fd = new FormData(); fd.append('toggle_lock','1'); fd.append('id', idVal);
                        const res = await fetch('npc_master.php', { method:'POST', body: fd });
                        let json={}; try{ json=await res.json(); }catch(_e){}
                        if (json && json.ok){ const active = Number(json.locked||0)===1; lockBtn.classList.toggle('active', active); lockBtn.textContent = active ? '🔒' : '🔓'; }
                    }catch(_e){}
                });
            }
        })();
        </script>
    <?php } else { ?>
        <button type="submit" name="<?= $editItem ? "update" : "create" ?>" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
    <?php } ?>
</form>
<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ?>
    <?php include(__DIR__."/tmpl/metadata_json_editor.php"); ?>
    </div>
    <?php exit; } ?>
</div>

<style>
.npc-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; }
@media (max-width: 1400px){ .npc-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1100px){ .npc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px){ .npc-grid { grid-template-columns: 1fr; } }
.npc-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:8px; box-shadow:none; transition: transform .12s ease, background .12s ease; cursor:pointer; }
.npc-card:hover { transform: translateY(-1px); background:#333333; }
.npc-title { font-weight:800; color:#e9efff; font-size:18px; text-align:center; letter-spacing:0.3px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.npc-title-left { flex:1 1 auto; text-align:left; }
.npc-title-actions { display:flex; align-items:center; gap:6px; flex:0 0 auto; }
.npc-gender-icon { margin-left:6px; opacity:0.9; }
.npc-gender-icon.gender-female { color:#ff72d2; }
.npc-gender-icon.gender-male { color:#72a0ff; }
.npc-gender-icon.gender-nb { color:#ffd166; }
.npc-divider { height:1px; background:#4a4a4a; margin:2px 0 6px; }
.npc-fields { display:flex; flex-direction:column; gap:8px; }
.npc-line { color:#e0e0e0; font-size:13px; line-height:1.35; }
.npc-muted { color:rgb(242, 124, 17); }
.npc-actions { display:flex; gap:8px; margin-top:6px; justify-content:center; }
.npc-actions .btn { padding:6px 10px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; text-decoration:none; cursor:pointer; }
.npc-actions .btn:hover { background:#3a3a3a; }
.npc-actions .btn-danger { background:#5a2a2a; border-color:#7a3a3a; }
.npc-actions .btn-danger:hover { background:#6a2a2a; }
.npc-title-actions a { text-decoration:none; border:none; }
.npc-title-actions a:hover { text-decoration:none; }
.btn-toggle { background:transparent; border:none; padding:6px; color:#e9efff; font-size:22px; line-height:1; text-decoration:none; }
.btn-toggle:hover { color: rgb(242, 124, 17); background:transparent; text-decoration:none; }
.btn-toggle.active { color: rgb(242, 124, 17); font-weight:700; text-decoration:none; }
.btn-toggle.active[data-favorite-id] { color:#ffd700; }
.btn-trash { background:transparent; border:none; padding:6px; color:#e9efff; font-size:20px; line-height:1; text-decoration:none; }
.btn-trash:hover { color:#ff6b6b; }
.npc-tags-label { font-size:11px; color:#9fb1c9; margin-right:4px; }
.npc-tags-top { font-size:11px; color:#9fb1c9; border:1px solid #4a4a4a; border-radius:999px; padding:2px 6px; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.npc-row { display:flex; gap:10px; align-items:flex-start; }
.npc-right { margin-left:auto; flex:0 0 auto; }
.npc-race-art { width:200px; height:200px; max-width:200px; max-height:200px; object-fit:contain; display:block; }
@media (max-width: 1100px){ .npc-race-art { width:160px; height:160px; } }
@media (max-width: 900px){ .npc-race-art { width:140px; height:140px; } }
@media (max-width: 720px){ .npc-right { display:none; } }
/* Dynamic profile grouping */
.dynamic-profile-section { border:1px solid #4a4a4a; border-radius:8px; padding:10px; margin:8px 0; background:#262626; }
.dynamic-profile-section .section-title { font-weight:700; color:rgb(242, 124, 17); margin-bottom:6px; }
.dynamic-profile-section > .form-item { margin-bottom:8px; }
</style>
<style>
/* Modal styling aligned with Oghma edit modal */
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:10000; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0; }
.modal-container { position:relative; top:auto; left:auto; transform:none; margin: 120px auto 40px auto; max-width:1000px; width:90%; background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; position:sticky; top:0; z-index:2; }
.modal-title { margin:0; font-weight:700; color: rgb(242, 124, 17); font-family: 'MagicCards', serif; word-spacing: 6px; }
.modal-body { max-height:calc(85vh - 100px); overflow-y:auto; background:#2a2a2a; }
.modal-close { background:#3a3a3a; color:#fff; border:1px solid #4a4a4a; border-radius:6px; padding:4px 10px; cursor:pointer; }
.modal-actions { display:flex; gap:8px; align-items:center; }
.modal-save { background: rgb(242, 124, 17); color:#111; border:1px solid rgb(242, 124, 17); border-radius:6px; padding:6px 12px; cursor:pointer; font-weight:700; }
</style>
<?php if ($totalPages > 1): ?>
<style>
.pagination { display:flex; gap:6px; align-items:center; justify-content:center; margin:10px 0 12px; flex-wrap:wrap; }
.pagination a, .pagination span { padding:6px 10px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; text-decoration:none; }
.pagination a:hover { background:#3a3a3a; }
.pagination .active { background: rgb(242, 124, 17); color:#111; border-color: rgb(242, 124, 17); font-weight:700; }
.pagination .disabled { opacity:0.5; pointer-events:none; }
.pagination button { padding:6px 10px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; cursor:pointer; }
.pagination button:hover { background:#3a3a3a; }
.filter-inline { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.filter-inline input[type="text"] { padding:4px 8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; height:28px; }
.filter-inline select { padding:4px 8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; height:28px; }
</style>
<div class="pagination">
  <div class="filter-inline">
    <input id="npc_search" type="text" placeholder="Search..." value="<?= htmlspecialchars($q) ?>" />
    <select id="npc_profile_filter" title="Filter by profile">
      <option value="">All Profiles</option>
      <?php foreach (($profileRows ?? []) as $pr): $pid=(string)($pr['id']??''); $lbl=$pr['label']??('Profile #'.$pid); ?>
        <option value="<?= htmlspecialchars($pid) ?>" <?= ($profileIdFilter!=='' && (string)$profileIdFilter===$pid)?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php $qbase = strtok($_SERVER['REQUEST_URI'], '?'); $make = function($p) use ($qbase){ return htmlspecialchars($qbase.'?page='.$p); }; ?>
  <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $make(1) ?>">First</a>
  <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $make(max(1,$page-1)) ?>">Prev</a>
  <?php for ($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
    <?php if ($p === $page): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= $make($p) ?>"><?= $p ?></a><?php endif; ?>
  <?php endfor; ?>
  <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= $make(min($totalPages,$page+1)) ?>">Next</a>
  <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= $make($totalPages) ?>">Last</a>
  <span style="border:none; background:transparent; color:rgb(242, 124, 17);">Page <?= $page ?> / <?= $totalPages ?></span>
  <span style="border:none; background:transparent; color:rgb(242, 124, 17);">Total <?= $totalRows ?></span>
  <button id="npc_create_btn" type="button" style="margin-left:8px;">+ Create NPC</button>
</div>
<?php endif; ?>
<div class="npc-grid">
<?php foreach ($data as $row): ?>
    <?php $pid = (string)($row['profile_id'] ?? ''); $profLabel = $profilesById[$pid] ?? ''; $oghmaVal = trim((string)($row['oghma_knowledge_tags'] ?? '')); $oghmaDisp = ($oghmaVal === '') ? 'none' : $oghmaVal; $tagsVal = trim((string)($row['tags'] ?? '')); $tagsDisp = ($tagsVal === '') ? 'none' : $tagsVal; $raceIcon = race_icon_web_path($row['race'] ?? '', $webRoot); ?>
    <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
        <div class="npc-title">
            <div class="npc-title-left"><span class="npc-name"><?= htmlspecialchars($row["npc_name"]) ?></span> <?php $gch = gender_icon_char($row['gender'] ?? ''); $gcl = gender_icon_class($row['gender'] ?? ''); if ($gch!==''): ?><span class="npc-gender-icon <?= htmlspecialchars($gcl) ?>" title="<?= htmlspecialchars($row['gender'] ?? '') ?>"><?= $gch ?></span><?php endif; ?></div>
            <div class="npc-title-actions">
                <?php if ($tagsDisp !== ''): ?>
                <span class="npc-tags-label">Tags:</span>
                <span class="npc-tags-top" title="Use Search to filter by these tags: <?= htmlspecialchars($tagsDisp) ?>"><?= htmlspecialchars($tagsDisp) ?></span>
                <?php endif; ?>
                <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>" title="Toggle favorite"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>" title="Toggle lock"><?php echo !empty($row["lock_profile"]) ? "🔒" : "🔓"; ?></a>
                <a class="btn btn-trash" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');" title="Delete">🗑️</a>
            </div>
        </div>
        <div class="npc-divider"></div>
        <div class="npc-row">
            <div class="npc-fields">
                <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"><?= htmlspecialchars($row["gender"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"><?= htmlspecialchars($row["race"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"><?= htmlspecialchars($row["voiceid"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">RefID:</span> <span class="npc-refid"><?= htmlspecialchars($row["refid"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Oghma Tags:</span> <span class="npc-oghma"><?= htmlspecialchars($oghmaDisp) ?></span></div>
                <div class="npc-line"><span class="npc-muted">Profile:</span> <span class="npc-profile"><?= htmlspecialchars($profLabel) ?></span></div>
            </div>
            <div class="npc-right">
                <?php if ($raceIcon !== ''): ?>
                    <img class="npc-race-art" src="<?= htmlspecialchars($raceIcon) ?>" alt="Race icon" />
                <?php endif; ?>
            </div>
        </div>
        
    </div>
<?php endforeach; ?>
</div>

<div id="npc_modal" class="modal-backdrop">
  <div class="modal-container">
    <div class="modal-header">
      <h2 class="modal-title">Edit NPC</h2>
      <div class="modal-actions">
        <button id="npc_modal_save_header" class="modal-save">Save</button>
        <button id="npc_modal_close" class="modal-close">Close</button>
      </div>
    </div>
    <div class="modal-body">
      <iframe id="npc_modal_iframe" src="about:blank" style="width:100%; height:75vh; border:0; background:transparent;"></iframe>
    </div>
  </div>
</div>

 

<script>
(function(){
  const PROFILES_BY_ID = <?= json_encode($profilesById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  const modal = document.getElementById('npc_modal');
  const iframe = document.getElementById('npc_modal_iframe');
  function openModal(url){ iframe.src = url; modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  function closeModal(){ modal.style.display = 'none'; document.body.style.overflow = 'auto'; try { iframe.src='about:blank'; } catch(_){} }
  const headerSave = document.getElementById('npc_modal_save_header');
  if (headerSave){
    headerSave.addEventListener('click', function(){
      try {
        const btn = iframe && iframe.contentDocument ? iframe.contentDocument.getElementById('npc_modal_save') : null;
        if (btn){ btn.click(); }
      } catch(_e){}
    });
  }
  document.addEventListener('click', function(e){ if (e.target && e.target.id==='npc_modal_close') closeModal(); });
  modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
  // Prevent browser history back/forward inside modal (mouse buttons/backspace)
  (function(){
    function blockNav(ev){ ev.preventDefault(); ev.stopPropagation(); return false; }
    window.addEventListener('popstate', blockNav, true);
    window.addEventListener('hashchange', blockNav, true);
    window.addEventListener('mousedown', function(e){ if (e.button===3 || e.button===4) { blockNav(e); } }, true);
    window.addEventListener('mouseup', function(e){ if (e.button===3 || e.button===4) { blockNav(e); } }, true);
    window.addEventListener('contextmenu', function(e){ /* noop */ }, true);
    // push a dummy state so back goes to same place
    try { history.pushState({modal:true}, document.title, location.href); } catch(_e){}
  })();
  document.querySelectorAll('.npc-card').forEach(card=>{
    card.addEventListener('click', function(ev){
      if (ev.target.closest('.npc-title-actions')) return;
      const id=this.getAttribute('data-id'); if (!id) return;
      ev.preventDefault();
      openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1');
    });
  });
  const createBtn = document.getElementById('npc_create_btn');
  if (createBtn){
    createBtn.addEventListener('click', function(){
      openModal('npc_master.php?partial=1');
    });
  }
  // Live search and alpha sort
  const searchInput = document.getElementById('npc_search');
  let listAbort = null;
  async function refreshList(page){
    const params = new URLSearchParams(window.location.search);
    const si = document.getElementById('npc_search');
    const wasFocused = document.activeElement && document.activeElement.id === 'npc_search';
    const caretStart = wasFocused && si && typeof si.selectionStart === 'number' ? si.selectionStart : null;
    const caretEnd = wasFocused && si && typeof si.selectionEnd === 'number' ? si.selectionEnd : null;
    if (si) params.set('q', si.value || '');
    const pf = document.getElementById('npc_profile_filter');
    if (pf) params.set('profile_id', pf.value || '');
    params.set('alpha', 'asc');
    if (page) params.set('page', String(page));
    params.set('list','1');
    if (listAbort) { try { listAbort.abort(); } catch(_){} }
    listAbort = new AbortController();
    const res = await fetch('npc_master.php?'+params.toString(), { signal: listAbort.signal });
    const html = await res.text();
    const temp = document.createElement('div'); temp.innerHTML = html;
    const newPag = temp.querySelector('.pagination');
    const newGrid = temp.querySelector('.npc-grid');
    if (newPag && newGrid){
      const oldPag = document.querySelector('.pagination');
      const oldGrid = document.querySelector('.npc-grid');
      if (oldPag && oldPag.parentElement) oldPag.parentElement.replaceChild(newPag, oldPag);
      if (oldGrid && oldGrid.parentElement) oldGrid.parentElement.replaceChild(newGrid, oldGrid);
      // rebind events on new elements
      document.querySelectorAll('.npc-card').forEach(card=>{
        card.addEventListener('click', function(ev){
          if (ev.target.closest('.npc-title-actions')) return;
          const id=this.getAttribute('data-id'); if (!id) return;
          ev.preventDefault();
          openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1');
        });
      });
      document.querySelectorAll('[data-favorite-id]').forEach(btn=>{
        btn.addEventListener('click', async function(e){
          e.preventDefault(); const id = this.getAttribute('data-favorite-id');
          const fd = new FormData(); fd.append('toggle_favorite','1'); fd.append('id', id);
          const res = await fetch('npc_master.php', { method:'POST', body: fd }); let json={}; try{ json=await res.json(); }catch(_e){}
          if (json && json.ok){ const active = Number(json.favorite||0)===1; this.classList.toggle('active', active); this.textContent = active ? '★' : '☆'; }
        });
      });
      document.querySelectorAll('[data-lock-id]').forEach(btn=>{
        btn.addEventListener('click', async function(e){
          e.preventDefault(); const id = this.getAttribute('data-lock-id');
          const fd = new FormData(); fd.append('toggle_lock','1'); fd.append('id', id);
          const res = await fetch('npc_master.php', { method:'POST', body: fd }); let json={}; try{ json=await res.json(); }catch(_e){}
          if (json && json.ok){ const active = Number(json.locked||0)===1; this.classList.toggle('active', active); this.textContent = active ? '🔒' : '🔓'; }
        });
      });
      const newCreate = document.getElementById('npc_create_btn');
      if (newCreate){ newCreate.addEventListener('click', function(){ openModal('npc_master.php?partial=1'); }); }
      // Hook pagination links to AJAX
      document.querySelectorAll('.pagination a[href]').forEach(a=>{
        a.addEventListener('click', function(e){
          e.preventDefault();
          const m = this.href.match(/page=(\d+)/); const p = m?parseInt(m[1],10):1; refreshList(p);
        });
      });
      const newSearch = document.getElementById('npc_search');
      if (newSearch){
        // Rebind with debounce and restore focus/caret
        newSearch.addEventListener('input', function(){ refreshListDebounced(1); });
        if (wasFocused){
          try {
            newSearch.focus();
            if (caretStart!=null && caretEnd!=null) newSearch.setSelectionRange(caretStart, caretEnd);
          } catch(_e){}
        }
      }
      const newProfileSel = document.getElementById('npc_profile_filter');
      if (newProfileSel){ newProfileSel.addEventListener('change', function(){ refreshList(1); }); }
    }
  }
  // Simple debounce for input
  let debTimer = null;
  function refreshListDebounced(page){
    if (debTimer) clearTimeout(debTimer);
    debTimer = setTimeout(()=>refreshList(page), 180);
  }
  if (searchInput){ searchInput.addEventListener('input', function(){ refreshListDebounced(1); }); }
  const profileSel = document.getElementById('npc_profile_filter');
  if (profileSel){ profileSel.addEventListener('change', function(){ refreshList(1); }); }
  // Removed alpha toggle; default remains ascending (favorites first)
  // Hook existing pagination for AJAX
  document.querySelectorAll('.pagination a[href]').forEach(a=>{
    a.addEventListener('click', function(e){ e.preventDefault(); const m = this.href.match(/page=(\d+)/); const p = m?parseInt(m[1],10):1; refreshList(p); });
  });
  // Toggle buttons
  document.querySelectorAll('[data-favorite-id]').forEach(btn=>{
    btn.addEventListener('click', async function(e){
      e.preventDefault();
      const id = this.getAttribute('data-favorite-id');
      const fd = new FormData(); fd.append('toggle_favorite','1'); fd.append('id', id);
      const res = await fetch('npc_master.php', { method:'POST', body: fd });
      let json={}; try{ json=await res.json(); }catch(_e){}
      if (json && json.ok){
        const active = Number(json.favorite||0)===1;
        this.classList.toggle('active', active);
        this.textContent = active ? '★' : '☆';
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent= active?'Marked favorite':'Unfavorited'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
  });
  document.querySelectorAll('[data-lock-id]').forEach(btn=>{
    btn.addEventListener('click', async function(e){
      e.preventDefault();
      const id = this.getAttribute('data-lock-id');
      const fd = new FormData(); fd.append('toggle_lock','1'); fd.append('id', id);
      const res = await fetch('npc_master.php', { method:'POST', body: fd });
      let json={}; try{ json=await res.json(); }catch(_e){}
      if (json && json.ok){
        const active = Number(json.locked||0)===1;
        this.classList.toggle('active', active);
        this.textContent = active ? '🔒' : '🔓';
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent= active?'Locked profile':'Unlocked profile'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
  });
  // Receive save events from iframe and update the card inline
  window.addEventListener('message', async function(e){
    const d = e.data || {};
    if (d.type === 'npc_saved'){
      const id = String(d.id||'');
      const data = d.data || {};
      let card = document.getElementById('npc_card_'+id);
      if (!card){
        // Create a new card at the start of the grid
        const grid = document.querySelector('.npc-grid');
        if (grid){
          const div = document.createElement('div');
          div.className = 'npc-card';
          div.id = 'npc_card_'+id;
          div.setAttribute('data-id', id);
          div.innerHTML = `
            <div class="npc-title">
              <div class="npc-title-left"><span class="npc-name"></span></div>
              <div class="npc-title-actions">
                <span class="npc-tags-top" style="display:none"></span>
                <a class="btn btn-toggle" href="#" data-favorite-id="${id}" title="Toggle favorite">☆</a>
                <a class="btn btn-toggle" href="#" data-lock-id="${id}" title="Toggle lock">🔓</a>
                <a class="btn btn-trash" href="?delete=${id}" onclick="return confirm('Delete this NPC?');" title="Delete">🗑️</a>
              </div>
            </div>
            <div class="npc-divider"></div>
            <div class="npc-row">
              <div class="npc-fields">
                <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"></span></div>
                <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"></span></div>
                <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"></span></div>
                <div class="npc-line"><span class="npc-muted">RefID:</span> <span class="npc-refid"></span></div>
                <div class="npc-line"><span class="npc-muted">Oghma Tags:</span> <span class="npc-oghma"></span></div>
                <div class="npc-line"><span class="npc-muted">Profile:</span> <span class="npc-profile"></span></div>
                <div class="npc-line"><span class="npc-muted">Tags:</span> <span class="npc-tags"></span></div>
              </div>
              <div class="npc-right"></div>
            </div>
            <div class="npc-actions">
                <a class="btn btn-danger" href="?delete=${id}" onclick="return confirm('Delete this NPC?');">Delete</a>
            </div>`;
          grid.prepend(div);
          // Wire edit button
          div.addEventListener('click', function(ev){ if (ev.target.closest('.npc-actions')) return; ev.preventDefault(); openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1'); });
          card = div;
        }
      }
      if (card){
        const setText = (sel, val)=>{ const el = card.querySelector(sel); if (el) el.textContent = val==null?'':String(val); };
        setText('.npc-name', data.npc_name);
        setText('.npc-gender', data.gender);
        setText('.npc-race', data.race);
        setText('.npc-voiceid', data.voiceid);
        setText('.npc-refid', data.refid);
        setText('.npc-oghma', (data.oghma_knowledge_tags==null || String(data.oghma_knowledge_tags).trim()==='') ? 'none' : data.oghma_knowledge_tags);
        setText('.npc-tags', (data.tags==null || String(data.tags).trim()==='') ? 'none' : data.tags);
        // Update title tags pill
        try {
          const top = card.querySelector('.npc-title-actions .npc-tags-top');
          const tval = (data.tags||'').trim();
          if (top){
            if (tval){ top.style.display='inline-block'; top.textContent = tval; top.title = 'Use Search to filter by these tags: ' + tval; }
            else { top.style.display='none'; top.textContent=''; top.removeAttribute('title'); }
          }
        } catch(_){}
        const profId = String(data.profile_id||'');
        setText('.npc-profile', PROFILES_BY_ID[profId] || '');
        // Fetch and render race icon into the right container
        try {
          const race = (data.race||'');
          const res = await fetch('npc_master.php?race_icon=1&race='+encodeURIComponent(race));
          const j = await res.json();
          const right = card.querySelector('.npc-right');
          if (right){
            right.innerHTML = '';
            if (j && j.url){ right.innerHTML = '<img class="npc-race-art" alt="Race icon" src="'+j.url+'" />'; }
          }
        } catch(_e){}
      }
      closeModal();
      try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='NPC saved'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 2000); } } catch(_e){}
    }
  });
})();
</script>

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 // Hide metadata editor in modal partial view
 if (!(isset($_GET['partial']) && $_GET['partial']=='1')) {
     include(__DIR__."/tmpl/metadata_json_editor.php");
 }
// Provides Datatables
 include(__DIR__."/tmpl/data_tables.php");
?>

    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
