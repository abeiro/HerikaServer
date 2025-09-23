<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");

$GLOBALS["ENGINE_PATH"]=$enginePath;

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

<h1 class="api-title">CHIM NPCs</h1>

<?php
$GLOBALS["db"] = new sql();
$npc = new NpcMaster();

// Helper: resolve race icon web path if file exists
if (!function_exists('race_icon_web_path')) {
    function race_icon_web_path($race, $webRoot, $refid, $md5 = '', $npcName = '', $portraitRel = ''){
        // 0) If metadata specifies a portrait relative path under data/pictures, use it first
        $portraitRel = trim((string)$portraitRel);
        if ($portraitRel !== '') {
            $portraitRel = ltrim(str_replace(['\\'], '/', $portraitRel), '/');
            $picturesRootFs = rtrim("{$GLOBALS["ENGINE_PATH"]}/data/pictures/", '/\\') . DIRECTORY_SEPARATOR;
            $picturesRootUrl = rtrim($webRoot . '/data/pictures/', '/');
            $fs = realpath($picturesRootFs . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $portraitRel));
            if ($fs !== false && strpos($fs, $picturesRootFs) === 0 && is_file($fs)) {
                return $picturesRootUrl . '/' . str_replace('%2F','/', rawurlencode($portraitRel));
            }
        }
        // 1) Prefer per-NPC portrait from data/pictures/profile
        $refid = strtoupper($refid);
        $profileDirFs = rtrim("{$GLOBALS["ENGINE_PATH"]}/data/pictures/profile/", '/\\') . DIRECTORY_SEPARATOR;
        $profileDirUrl = rtrim($webRoot . '/data/pictures/profile/', '/');
        $exts = ['png','jpg','jpeg','webp','gif'];
        $candidates = [];
        if (!empty($md5)) { $candidates[] = $md5; }
        if (!empty($refid)) { $candidates[] = $refid; }
        if (!empty($npcName)) {
            $in = strtolower((string)$npcName);
            $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($words)) {
                $candidates[] = implode('', $words);
                $candidates[] = implode('-', $words);
                $candidates[] = implode('_', $words);
            }
        }
        $seen = [];
        foreach ($candidates as $base){
            $base = trim((string)$base);
            if ($base === '' || isset($seen[$base])) continue;
            $seen[$base] = true;
            foreach ($exts as $ext){
                $fs = $profileDirFs . $base . '.' . $ext;
                if (file_exists($fs)) {
                    return $profileDirUrl . '/' . rawurlencode($base . '.' . $ext);
                }
            }
        }

        // 2) Fallback to race icon pack
        $in = strtolower((string)$race);
        $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
        $slug = implode('', $words);
        if ($slug === '') { $words = []; }
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
        $exts2 = ['png','jpg','jpeg','webp','gif','svg'];
        foreach ($variants as $name){
            foreach ($exts2 as $ext){
                $fs = $fsDir . $name . '.' . $ext;
                if (file_exists($fs)) return $webRoot . '/ui/images/races/' . $name . '.' . $ext;
            }
        }
        $defaultFs = $fsDir . 'default.png';
        if (file_exists($defaultFs)) { return $webRoot . '/ui/images/races/default.png'; }
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

// Bulk delete unlocked NPCs except The Narrator (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["bulk_delete_npcs"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm !== 'Delete') { echo json_encode(["ok"=>false, "error"=>"Confirmation text mismatch"]); exit; }
        // Delete all unlocked NPCs except The Narrator (by name or id=1)
        $sql = "with del as (delete from core_npc_master where coalesce(lock_profile,0)=0 and not (npc_name='The Narrator' or id=1) returning 1) select count(*) as c from del";
        $row = $GLOBALS['db']->fetchOne($sql);
        $deleted = intval($row['c'] ?? 0);
        echo json_encode(["ok"=>true, "deleted"=>$deleted]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Set portrait from gallery (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["set_portrait"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        $sourceUrl = (string)($_POST['source'] ?? '');
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        if ($sourceUrl === '') { echo json_encode(["ok"=>false, "error"=>"Missing source"]); exit; }

        $row = $npc->getById($id);
        if (!$row) { echo json_encode(["ok"=>false, "error"=>"NPC not found"]); exit; }

        $name = (string)($row['npc_name'] ?? '');
        $md5 = (string)($row['md5'] ?? '');
        if ($md5 === '' && $name !== '') { $md5 = md5($name); }
        $refid = strtoupper((string)($row['refid'] ?? ''));

        // Map web URL to filesystem path under gallery root
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        $webPrefix = rtrim($webRoot, '/');
        $galleryWeb = $webPrefix . '/data/pictures/gallery/';
        if (strpos($path, $galleryWeb) !== 0) { echo json_encode(["ok"=>false, "error"=>"Source not in gallery"]); exit; }
        $rel = substr($path, strlen($galleryWeb));
        $rel = str_replace(['\\'], '/', $rel);
        $rel = ltrim($rel, '/');
        $galleryRoot = realpath($GLOBALS["ENGINE_PATH"] . '/data/pictures/gallery');
        if ($galleryRoot === false) { echo json_encode(["ok"=>false, "error"=>"Gallery root missing"]); exit; }
        $srcFs = realpath($galleryRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel));
        if ($srcFs === false || strpos($srcFs, $galleryRoot) !== 0) { echo json_encode(["ok"=>false, "error"=>"Invalid source path"]); exit; }

        $ext = strtolower(pathinfo($srcFs, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed, true)) { echo json_encode(["ok"=>false, "error"=>"Unsupported format"]); exit; }

        $profileRoot = rtrim($GLOBALS["ENGINE_PATH"] . '/data/pictures/profile/', '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($profileRoot)) { @mkdir($profileRoot, 0775, true); }
        $base = $md5 !== '' ? $md5 : ($refid !== '' ? $refid : preg_replace('/[^a-z0-9_-]+/i','_', (string)$name));
        if ($base === '') { echo json_encode(["ok"=>false, "error"=>"Cannot determine filename"]); exit; }
        $dstFs = $profileRoot . $base . '.' . $ext;

        if (!@copy($srcFs, $dstFs)) { echo json_encode(["ok"=>false, "error"=>"Copy failed"]); exit; }

        // Update metadata.portrait to relative path under data/pictures
        $portraitRel = 'profile/' . $base . '.' . $ext;
        $meta = [];
        if (!empty($row['metadata'])) {
            $tmp = json_decode((string)$row['metadata'], true);
            if (is_array($tmp)) { $meta = $tmp; }
        }
        $meta['portrait'] = $portraitRel;
        $npc->update($id, ['metadata' => json_encode($meta)]);

        $url = rtrim($webRoot, '/') . '/data/pictures/' . str_replace('%2F','/', rawurlencode($portraitRel));
        echo json_encode(["ok"=>true, "url"=>$url, "portrait"=>$portraitRel]);
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    // Prevent deleting The Narrator from UI
    $toDel = intval($_GET["delete"]);
    if ($toDel === 1) { header("Location: npc_master.php"); exit; }
    $rowCheck = $npc->getById($toDel);
    if ($rowCheck && (($rowCheck['npc_name'] ?? '') === 'The Narrator' || !empty($rowCheck['lock_profile']))) { header("Location: npc_master.php"); exit; }
    $npc->delete($toDel);
    header("Location: npc_master.php");
    exit;
}

// Fetch Data
$perPage = 12;
$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
if ($page < 1) $page = 1;

// Filters and sorting
$q = trim($_GET['q'] ?? '');
$alpha = strtolower($_GET['alpha'] ?? 'asc');
if (!in_array($alpha, ['asc','desc'], true)) { $alpha = 'asc'; }
$profileIdFilter = isset($_GET['profile_id']) ? trim((string)$_GET['profile_id']) : '';

// Preload profiles for filter dropdown
$profileRows = $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_profiles ORDER BY label ASC");
// Default to first profile id for new NPCs
$firstProfileId = '';
if (is_array($profileRows) && count($profileRows) > 0) {
    $firstProfileId = (string)($profileRows[0]['id'] ?? '');
}
// Preload profile connector mappings and LLM connector labels for modal summary
$profileConnRows = $GLOBALS["db"]->fetchAll("SELECT id, llm_primary_id, llm_secondary_id, llm_tertiary_id, llm_quaternary_id, llm_formatter_id, diary_connector_id FROM core_profiles ORDER BY id ASC");
$llmRows = $GLOBALS["db"]->fetchAll("SELECT id, COALESCE(NULLIF(label,''), model) AS label FROM core_llm_connector ORDER BY id ASC");
$profilesById = [];
foreach (($profileRows ?? []) as $pr) {
    $pid = (string)($pr['id'] ?? '');
    if ($pid !== '') $profilesById[$pid] = $pr['label'] ?? ('Profile #'.$pid);
}
$profilesConnById = [];
foreach (($profileConnRows ?? []) as $prc) {
    $pid = (string)($prc['id'] ?? '');
    if ($pid !== '') $profilesConnById[$pid] = $prc;
}
$llmById = [];
foreach (($llmRows ?? []) as $lr) {
    $lid = (string)($lr['id'] ?? '');
    if ($lid !== '') $llmById[$lid] = $lr['label'] ?? ('Connector #'.$lid);
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
// Ensure The Narrator is always first, then favorites, then alpha
$order = "order by (case when npc_name='The Narrator' then 0 else 1 end) asc, coalesce(npc_favorite,0) desc, lower(npc_name) ".$alpha.", id asc";

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
      <button id="npc_bulk_delete_btn" type="button" class="btn-danger" title="Delete all unlocked NPCs (excludes The Narrator and locked)">Delete All Profiles</button>
    </div>
    <div class="npc-grid">
    <?php foreach ($data as $row): ?>
        <?php $pid = (string)($row['profile_id'] ?? ''); $profLabel = $profilesById[$pid] ?? ''; $metaTmp = []; if (!empty($row['metadata'])) { $tmp = json_decode((string)$row['metadata'], true); if (is_array($tmp)) { $metaTmp = $tmp; } } $portraitRel = (string)($metaTmp['portrait'] ?? ''); $raceIcon = race_icon_web_path($row['race'] ?? '', $webRoot,$row["refid"] ?? '', $row['md5'] ?? '', $row['npc_name'] ?? '', $portraitRel); $tagsVal = trim((string)($row['tags'] ?? '')); $tagsDisp = ($tagsVal === '') ? '' : $tagsVal; ?>
        <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
            <div class="npc-title">
                <div class="npc-title-left"><span class="npc-name"><?= htmlspecialchars($row["npc_name"]) ?></span> <?php $gch = gender_icon_char($row['gender'] ?? ''); $gcl = gender_icon_class($row['gender'] ?? ''); if ($gch!==''): ?><span class="npc-gender-icon <?= htmlspecialchars($gcl) ?>" title="<?= htmlspecialchars($row['gender'] ?? '') ?>"><?= $gch ?></span><?php endif; ?><?php if (!empty($row['dynamic_profile'])): ?><span class="npc-dyn-icon" title="Dynamic profile enabled">♻️</span><?php endif; ?></div>
            <div class="npc-title-actions">
                    <?php if ($tagsDisp !== ''): ?>
                    <span class="npc-tags-top" title="<?= htmlspecialchars($tagsDisp) ?>"><?= htmlspecialchars($tagsDisp) ?></span>
                    <?php endif; ?>
                    <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>" title="Toggle favorite"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                <a class="btn btn-toggle" href="#" data-pick-picture-id="<?= $row["id"] ?>" title="Set picture">🖼️</a>
                <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>" title="Toggle lock"><?php echo !empty($row["lock_profile"]) ? "🔒" : "🔓"; ?></a>
                <?php if ((int)$row['id'] !== 1 && ($row['npc_name'] ?? '') !== 'The Narrator'): ?>
                <a class="btn btn-trash<?= !empty($row['lock_profile']) ? ' disabled' : '' ?>" href="<?= !empty($row['lock_profile']) ? '#' : ('?delete='.$row['id']) ?>" onclick="<?= !empty($row['lock_profile']) ? 'alert(\'This NPC is locked and cannot be deleted.\'); return false;' : "return confirm('Delete this NPC?');" ?>" title="<?= !empty($row['lock_profile']) ? 'Locked - cannot delete' : 'Delete' ?>">🗑️</a>
                <?php endif; ?>
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
                    <?php $tagsVal = trim((string)($row["tags"] ?? "")); $tagsDisp = ($tagsVal === "") ? "none" : $tagsVal; ?>                </div>
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
    $refid = (string)($_GET['refid'] ?? '');
    $name = (string)($_GET['name'] ?? '');
    $md5 = $name !== '' ? md5($name) : (string)($_GET['md5'] ?? '');
    $portraitRel = '';
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        try { $row = $npc->getById($id); if ($row && !empty($row['metadata'])) { $tmp = json_decode((string)$row['metadata'], true); if (is_array($tmp)) { $portraitRel = (string)($tmp['portrait'] ?? ''); } } } catch (Throwable $e) {}
    }
    $url = race_icon_web_path($race, $webRoot, $refid, $md5, $name, $portraitRel);
    echo json_encode(['url' => $url]);
    exit;
}

// NPC history: return timeline of snapshots for a given NPC (Tamrielic time)
if (isset($_GET['history'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false, 'error'=>'Invalid id']); exit; }
        // Skip the most recent snapshot (current state); show only historical entries
        $sel = "select history_id,npc_id,npc_name,npc_favorite,lock_profile,prompt_head,npc_static_bio,oghma_knowledge_tags,emote_moods,personality,relationships,occupation,appearance,skills,speechstyle,goals,voiceid,gender,race,refid,profile_id,dynamic_profile,md5,gamets_last_updated,created,core,base,tags from core_npc_master_history where npc_id = {$id} order by coalesce(gamets_last_updated,0) desc, created desc, history_id desc offset 1";
        $rows = $GLOBALS['db']->fetchAll($sel) ?: [];
        $entries = [];
        foreach ($rows as $r){
            $g = isset($r['gamets_last_updated']) ? floatval($r['gamets_last_updated']) : 0.0;
            $tam = $g > 0 ? convert_gamets2skyrim_long_date2($g) : '';
            $greg = $g > 0 ? gamets2str_format_gregorian_date($g, 'Y-m-d H:i') : '';
            $created = (string)($r['created'] ?? '');
            $entries[] = [
                'history_id' => (int)($r['history_id'] ?? 0),
                'gamets' => $g,
                'when_tamrielic' => $tam,
                'when_gregorian' => $greg,
                'created' => $created,
                'fields' => [
                    'npc_name' => $r['npc_name'] ?? '',
                    'profile_id' => isset($r['profile_id']) ? (string)$r['profile_id'] : '',
                    'gender' => $r['gender'] ?? '',
                    'race' => $r['race'] ?? '',
                    'voiceid' => $r['voiceid'] ?? '',
                    'refid' => $r['refid'] ?? '',
                    'core' => $r['core'] ?? '',
                    'npc_static_bio' => $r['npc_static_bio'] ?? '',
                    'personality' => $r['personality'] ?? '',
                    'relationships' => $r['relationships'] ?? '',
                    'occupation' => $r['occupation'] ?? '',
                    'skills' => $r['skills'] ?? '',
                    'speechstyle' => $r['speechstyle'] ?? '',
                    'goals' => $r['goals'] ?? '',
                    'oghma_knowledge_tags' => $r['oghma_knowledge_tags'] ?? '',
                    'emote_moods' => $r['emote_moods'] ?? '',
                    'prompt_head' => $r['prompt_head'] ?? '',
                    'dynamic_profile' => !empty($r['dynamic_profile']),
                    'npc_favorite' => !empty($r['npc_favorite']),
                    'lock_profile' => !empty($r['lock_profile']),
                    'tags' => $r['tags'] ?? '',
                    'base' => $r['base'] ?? ''
                ]
            ];
        }
        echo json_encode(['ok'=>true,'count'=>count($entries),'entries'=>$entries]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Bio database: search existing templates (combined_bio_templates)
if (isset($_GET['bio_search'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $search = trim((string)($_GET['search'] ?? ''));
    $letter = trim((string)($_GET['letter'] ?? ''));
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? 20)));
    $where = [];
    if ($search !== '') {
        $q = '%'.$GLOBALS['db']->escape($search).'%';
        $where[] = "(lower(npc_name) like lower('{$q}') or lower(core) like lower('{$q}'))";
    }
    if ($letter !== '' && preg_match('/^[A-Za-z]$/', $letter)) {
        $l = $GLOBALS['db']->escape(strtolower($letter));
        $where[] = "lower(npc_name) like '{$l}%'";
    }
    $whereSql = count($where) ? ('where '.implode(' and ', $where)) : '';
    $cntRow = $GLOBALS['db']->fetchOne("select count(*) as c from combined_bio_templates {$whereSql}");
    $total = intval($cntRow['c'] ?? 0);
    $offset = ($page - 1) * $pageSize;
    $rows = $GLOBALS['db']->fetchAll("select npc_name, core, voiceid, gender, race, refid, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags from combined_bio_templates {$whereSql} order by lower(npc_name) asc limit {$pageSize} offset {$offset}");
    $items = [];
    foreach (($rows ?? []) as $r) {
        $extFields = ['npc_static_bio','personality','appearance','relationships','occupation','skills','speechstyle','goals'];
        $filled = 0; foreach ($extFields as $f) { $v = trim((string)($r[$f] ?? '')); if ($v !== '') $filled++; }
        $coreFull = (string)($r['core'] ?? '');
        if (function_exists('mb_strimwidth')) {
            $corePreview = mb_strimwidth($coreFull, 0, 160, '…', 'UTF-8');
        } else {
            $corePreview = (strlen($coreFull) > 160) ? (substr($coreFull, 0, 157).'…') : $coreFull;
        }
        $items[] = [
            'npc_name' => $r['npc_name'] ?? '',
            'core_preview' => $corePreview,
            'voiceid' => $r['voiceid'] ?? '',
            'gender' => $r['gender'] ?? '',
            'race' => $r['race'] ?? '',
            'refid' => $r['refid'] ?? '',
            'extended_filled' => $filled
        ];
    }
    echo json_encode(['ok'=>true,'total'=>$total,'page'=>$page,'pageSize'=>$pageSize,'items'=>$items]);
    exit;
}

// Bio database: detail of a specific template by npc_name
if (isset($_GET['bio_detail'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $name = trim((string)($_GET['name'] ?? ''));
    if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Missing name']); exit; }
    $esc = $GLOBALS['db']->escape($name);
    $r = $GLOBALS['db']->fetchOne("select npc_name, core, voiceid, gender, race, refid, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags from combined_bio_templates where npc_name = '{$esc}' limit 1");
    if (!$r) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
    echo json_encode(['ok'=>true,'data'=>$r]);
    exit;
}

// Import from bio: server builds row and creates/updates NPC
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['import_from_bio'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Missing name']); exit; }
        $includeCore = ($_POST['include_core'] ?? '1') ? true : false;
        $includeExt  = ($_POST['include_extended'] ?? '1') ? true : false;
        $includeOgh  = ($_POST['include_oghma'] ?? '1') ? true : false;
        $includeVM   = ($_POST['include_voice_meta'] ?? '1') ? true : false;
        $profileId   = isset($_POST['profile_id']) && $_POST['profile_id']!=='' ? intval($_POST['profile_id']) : null;

        $esc = $GLOBALS['db']->escape($name);
        $r = $GLOBALS['db']->fetchOne("select npc_name, core, voiceid, gender, race, refid, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags from combined_bio_templates where npc_name = '{$esc}' limit 1");
        if (!$r) { echo json_encode(['ok'=>false,'error'=>'Template not found']); exit; }

        $data = [ 'npc_name' => $r['npc_name'] ?? $name ];
        if ($profileId !== null) $data['profile_id'] = $profileId;
        if ($includeCore) { $data['core'] = $r['core'] ?? null; }
        if ($includeExt) {
            foreach (['npc_static_bio','personality','appearance','relationships','occupation','skills','speechstyle','goals'] as $f) {
                $data[$f] = $r[$f] ?? null;
            }
        }
        if ($includeOgh) { $data['oghma_knowledge_tags'] = $r['oghma_knowledge_tags'] ?? null; }
        if ($includeVM) {
            foreach (['voiceid','gender','race','refid'] as $f) { $data[$f] = $r[$f] ?? null; }
        }

        // Upsert by name
        $existing = $npc->getByName($data['npc_name']);
        if ($existing) {
            $data['md5'] = md5((string)$data['npc_name']);
            $ok = $npc->update((int)$existing['id'], $data);
            if ($ok === false) { echo json_encode(['ok'=>false,'error'=>'Update failed']); exit; }
            $newId = (int)$existing['id'];
        } else {
            $npc->create($data);
            // Fetch newly created row
            $row = $npc->getByName($data['npc_name']);
            $newId = (int)($row['id'] ?? 0);
        }
        if (!$newId) { echo json_encode(['ok'=>false,'error'=>'Insert failed']); exit; }
        $payload = $npc->getById($newId) ?: $data;
        echo json_encode(['ok'=>true,'id'=>$newId,'data'=>$payload]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
?>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php endif; ?>

<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ob_end_clean(); ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>html,body{background:#2a2a2a;margin-bottom:50px;margin-right:5px;} main{background:#2a2a2a; padding:12px;} .form-container{background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px;}
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
    /* Header-style checkbox next to label title */
    .label-with-toggle { display:flex; align-items:center; gap:10px; }
    .label-with-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
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
    <?php
    // Render LLM summary container (will live-update via JS)
    $curPid = (string)($editItem['profile_id'] ?? '');
    $pc = ($curPid !== '' && isset($profilesConnById[$curPid])) ? $profilesConnById[$curPid] : null;
    $m = function($id) use ($llmById){ $k = (string)($id ?? ''); return $k !== '' && isset($llmById[$k]) ? $llmById[$k] : '—'; };
    ?>
    <div id="profile_llm_summary" style="display:grid; grid-template-columns: 210px 1fr; gap:6px; color:#cfd9ea; border:1px solid #4a4a4a; border-radius:8px; padding:8px; margin-bottom:8px;">
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">🕹️ Standard LLM</div><div><?= htmlspecialchars($pc ? $m($pc['llm_primary_id'] ?? '') : '—') ?></div>
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">🏃‍♂️‍➡️ Fast LLM</div><div><?= htmlspecialchars($pc ? $m($pc['llm_secondary_id'] ?? '') : '—') ?></div>
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">💪 Powerful LLM</div><div><?= htmlspecialchars($pc ? $m($pc['llm_tertiary_id'] ?? '') : '—') ?></div>
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">🧪 Experimental LLM</div><div><?= htmlspecialchars($pc ? $m($pc['llm_quaternary_id'] ?? '') : '—') ?></div>
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">📓 Diary LLM</div><div><?= htmlspecialchars($pc ? $m($pc['diary_connector_id'] ?? '') : '—') ?></div>
        <div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">🧾 Formatter LLM</div><div><?= htmlspecialchars($pc ? $m($pc['llm_formatter_id'] ?? '') : '—') ?></div>
    </div>
    <script>
    (function(){
        const PROFILE_CONN = <?= json_encode($profilesConnById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        const LLM_LABELS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        function labelOf(id){ const k=String(id||''); return (k && LLM_LABELS[k]) ? String(LLM_LABELS[k]) : '—'; }
        function renderProfileSummary(pid){
            const box = document.getElementById('profile_llm_summary'); if (!box) return;
            const pc = PROFILE_CONN[String(pid||'')] || null;
            const rows = [
                ['🕹️ Standard LLM', pc ? labelOf(pc.llm_primary_id) : '—'],
                ['🏃‍♂️‍➡️ Fast LLM', pc ? labelOf(pc.llm_secondary_id) : '—'],
                ['💪 Powerful LLM', pc ? labelOf(pc.llm_tertiary_id) : '—'],
                ['🧪 Experimental LLM', pc ? labelOf(pc.llm_quaternary_id) : '—'],
                ['📓 Diary LLM', pc ? labelOf(pc.diary_connector_id) : '—'],
                ['🧾 Formatter LLM', pc ? labelOf(pc.llm_formatter_id) : '—']
            ];
            let html = '';
            rows.forEach(([k,v])=>{
                html += '<div style="color:rgb(242,124,17); font-weight:700; white-space:nowrap;">'+k+'</div><div>'+String(v||'—')+'</div>';
            });
            box.innerHTML = html;
        }
        document.addEventListener('DOMContentLoaded', function(){
            const sel = document.getElementById('profile_id');
            if (sel){ sel.addEventListener('change', function(){ renderProfileSummary(this.value||''); }); renderProfileSummary(sel.value||''); }
        });
    })();
    </script>
    <?php endif; ?>

    <div class="form-grid">
        <div class="form-item span-2">
            <label for="npc_name">NPC Name</label>
            <input type="text" id="npc_name" name="npc_name" placeholder="e.g. Aela the Huntress" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">
            <small class="hint">The character's name. Must match their Skyrim in-game name!</small>
        </div>

        <div class="form-item">
            <label for="profile_id">Profile</label>
            <select id="profile_id" name="profile_id">
                <option value="">-- Select Profile --</option>
                <?php foreach (($profileRows ?? []) as $pr): $pid=(string)($pr['id']??''); $lbl=$pr['label']??('Profile #'.$pid); $sel = ((string)($editItem['profile_id'] ?? '') === $pid) ? ' selected' : ((empty($editItem) && $firstProfileId === $pid) ? ' selected' : ''); ?>
                    <option value="<?= htmlspecialchars($pid) ?>"<?= $sel ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="hint">Select which profile the NPC uses.</small>
        </div>

        <div class="form-item" style='<?= (isset($_GET['partial']) && $_GET['partial']=='1')?"display:none":"" ?>'>
            <label for="lock_profile" class="label-with-toggle">Lock Profile
                <input type="checkbox" id="lock_profile" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
            </label>
            <small class="hint">Prevents dynamic systems from modifying this NPC's profile.</small>
        </div>

        <div class="form-item" style='<?= (isset($_GET['partial']) && $_GET['partial']=='1')?"display:none":"" ?>'>
            <label for="npc_favorite" class="label-with-toggle">Favorite
                <input type="checkbox" id="npc_favorite" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
            </label>
            <small class="hint">Pin this NPC for quick access.</small>
        </div>

        <div class="form-item">
            <label for="gender">Gender</label>
            <input type="text" id="gender" name="gender" placeholder="female, male" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">
            <small class="hint">Used for prompts.</small>
        </div>

        <div class="form-item">
            <label for="race">Race</label>
            <input type="text" id="race" name="race" placeholder="nord, dunmer, farm tool" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">
            <small class="hint">Lore-accurate race label used in prompts.</small>
        </div>

        <div class="form-item">
            <label for="base">Base</label>
            <input type="text" id="base" name="base" placeholder="Bandit Reaver" value="<?= htmlspecialchars($editItem["base"] ?? "") ?>">
            <small class="hint">Optional: base form identifier or template this NPC derives from.</small>
        </div>

        <div class="form-item">
            <label for="refid">Ref ID</label>
            <input type="text" id="refid" name="refid" placeholder="Game reference ID (000A2C94)" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>">
            <small class="hint">Skyrim reference ID for in-game linkage.</small>
        </div>

        <div class="form-item">
            <label for="oghma_knowledge_tags">Oghma Tags</label>
            <input type="text" id="oghma_knowledge_tags" name="oghma_knowledge_tags" placeholder="Comma-separated knowledge tags" value="<?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?>">
            <small class="hint">Used by Oghma systems for knowledge lookup restrictions.</small>
        </div>

        <div class="form-item">
            <label for="voiceid">Voice ID</label>
            <input type="text" id="voiceid" name="voiceid" placeholder="malenord" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">
            <small class="hint">Voice ID for TTS.</small>
        </div>

        <div class="form-item">
            <label for="dynamic_profile" class="label-with-toggle">Dynamic Profile
                <input type="hidden" name="dynamic_profile" value="0">
                <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?= !empty($editItem["dynamic_profile"]) ? "checked" : "" ?>>
            </label>
            <small class="hint">Allow systems to evolve the profile based on gameplay events.</small>
        </div>

        <div class="form-item span-2">
            <label for="prompt_head">Prompt Head Override</label>
            <textarea id="prompt_head" name="prompt_head" placeholder="High-level system instructions injected before the core."><?= htmlspecialchars($editItem["prompt_head"] ?? "") ?></textarea>
            <small class="hint">System preamble inserted before other sections. Do not worry if it is empty, as will pull from global settings prompt head.</small>
        </div>

        <div class="form-item span-2">
            <label for="core">Core</label>
            <textarea id="core" name="core" placeholder="Unchanging rules, boundaries, and core identity."><?= htmlspecialchars($editItem["core"] ?? "") ?></textarea>
            <small class="hint">Core NPC description. 1-2 sentences describing the character.</small>
        </div>

        <div class="form-item span-2">
            <label for="npc_static_bio">Static Bio</label>
            <textarea id="npc_static_bio" name="npc_static_bio" placeholder="Fixed background, history, and facts."><?= htmlspecialchars($editItem["npc_static_bio"] ?? "") ?></textarea>
            <small class="hint">Historical facts and background information.</small>
        </div>

        <div class="form-item span-2">
            <label for="appearance">Appearance</label>
            <textarea id="appearance" name="appearance" placeholder="Physical appearance."><?= htmlspecialchars($editItem["appearance"] ?? "") ?></textarea>
            <small class="hint">Physical appearance. Keep it limited to character cosmetics, not equipment.</small>
        </div>

        <div class="dynamic-profile-section span-2">
        <div class="form-item">
            <label for="personality">Personality</label>
            <textarea id="personality" name="personality" placeholder="Personality traits and speaking characteristics."><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>
            <small class="hint">Traits and quirks that guide tone and behavior.</small>
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
            <small class="hint">Highlight notable competencies of the NPC.</small>
        </div>

        <div class="form-item">
            <label for="speechstyle">Speech Style</label>
            <textarea id="speechstyle" name="speechstyle" placeholder="Dialect, cadence, verbal tics."><?= htmlspecialchars($editItem["speechstyle"] ?? "") ?></textarea>
            <small class="hint">How the NPC speaks their dialogue.</small>
        </div>

        <div class="form-item">
            <label for="goals">Goals</label>
            <textarea id="goals" name="goals" placeholder="Short and long-term objectives."><?= htmlspecialchars($editItem["goals"] ?? "") ?></textarea>
            <small class="hint">Motivations and goals for the NPC.</small>
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
                let form = save.closest('form');
                // Needed to actually update json data. Was done before at consolidation()
                if (form.extended_data!=undefined) {
                  const content2 = jsonEditor2.get()

                  try {
                    form.extended_data.value=JSON.stringify(content2.json, null, 0)
                    console.log("JSON editor values copied to form")
                  } catch (idontcare) {}
        
                  if (form.extended_data.value=='')  {
                    return confirm("Extended data is empty. You sure?");
                  }
                }

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
.npc-dyn-icon { margin-left:6px; color:#65d46e; opacity:0.95; }
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
.btn-toggle { background:transparent; border:none; padding:6px; color:#e9efff; font-size:22px; line-height:1; text-decoration:none; transition: color .15s ease, text-shadow .15s ease; }
/* Navbar-like glow only for lock and gallery on cards */
.btn-toggle[data-lock-id]:hover,
.btn-toggle[data-lock-id]:focus-visible,
.btn-toggle[data-pick-picture-id]:hover,
.btn-toggle[data-pick-picture-id]:focus-visible { color: rgb(242, 124, 17); background:transparent; text-decoration:none; text-shadow: 0 0 6px rgba(242, 124, 17, 0.6), 0 0 12px rgba(242, 124, 17, 0.35); }
.btn-toggle[data-favorite-id]:hover,
.btn-toggle[data-favorite-id]:focus-visible { color:#ffd700; text-shadow: 0 0 8px rgba(255, 215, 0, 0.7), 0 0 14px rgba(255, 215, 0, 0.45); }
.btn-toggle.active { color: rgb(242, 124, 17); font-weight:700; text-decoration:none; }
.btn-toggle.active[data-favorite-id] { color:#ffd700; }
.btn-trash { background:transparent; border:none; padding:6px; color:#e9efff; font-size:20px; line-height:1; text-decoration:none; transition: color .15s ease, text-shadow .15s ease; }
.btn-trash:hover, .btn-trash:focus-visible { color:#ff6b6b; text-shadow: 0 0 6px rgba(255, 107, 107, 0.7), 0 0 12px rgba(255, 107, 107, 0.45); }
.npc-tags-label { font-size:11px; color:#9fb1c9; margin-right:4px; }
.npc-tags-top { font-size:11px; color:#9fb1c9; border:1px solid #4a4a4a; border-radius:999px; padding:2px 6px; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.npc-row { display:flex; gap:10px; align-items:flex-start; }
.npc-right { margin-left:auto; flex:0 0 auto; }
.npc-race-art { width:200px; height:200px; max-width:200px; max-height:200px; object-fit:cover; display:block; }
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
.modal-container { position:relative; top:auto; left:auto; transform:none; /*margin: 120px auto 40px auto*/; max-width:1000px; width:90%; background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; position:sticky; top:0; z-index:2; }
.modal-title { margin:0; font-weight:700; color: rgb(242, 124, 17); font-family: 'MagicCards', serif; word-spacing: 6px; }
.modal-body { max-height:calc(85vh - 100px); /*overflow-y:auto;*/ background:#2a2a2a; }
.modal-close { background:#3a3a3a; color:#fff; border:1px solid #4a4a4a; border-radius:6px; padding:4px 10px; cursor:pointer; }
.modal-actions { display:flex; gap:8px; align-items:center; }
.modal-save { background: rgb(242, 124, 17); color:#111; border:1px solid rgb(242, 124, 17); border-radius:6px; padding:6px 12px; cursor:pointer; font-weight:700; }
</style>
<?php if ($totalPages >= 1): ?>
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
  <button id="npc_bulk_delete_btn" type="button" class="btn-danger" title="Delete all unlocked NPCs (excludes The Narrator and locked)">Delete All Profiles</button>
</div>
<?php endif; ?>
<div class="npc-grid">
<?php foreach ($data as $row): ?>
    <?php $pid = (string)($row['profile_id'] ?? ''); $profLabel = $profilesById[$pid] ?? ''; $oghmaVal = trim((string)($row['oghma_knowledge_tags'] ?? '')); $oghmaDisp = ($oghmaVal === '') ? 'none' : $oghmaVal; $tagsVal = trim((string)($row['tags'] ?? '')); $tagsDisp = ($tagsVal === '') ? 'none' : $tagsVal; $metaTmp = []; if (!empty($row['metadata'])) { $tmp = json_decode((string)$row['metadata'], true); if (is_array($tmp)) { $metaTmp = $tmp; } } $portraitRel = (string)($metaTmp['portrait'] ?? ''); $raceIcon = race_icon_web_path($row['race'] ?? '', $webRoot,$row['refid'] ?? '', $row['md5'] ?? '', $row['npc_name'] ?? '', $portraitRel); ?>
    <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
            <div class="npc-title">
            <div class="npc-title-left"><span class="npc-name"><?= htmlspecialchars($row["npc_name"]) ?></span> <?php $gch = gender_icon_char($row['gender'] ?? ''); $gcl = gender_icon_class($row['gender'] ?? ''); if ($gch!==''): ?><span class="npc-gender-icon <?= htmlspecialchars($gcl) ?>" title="<?= htmlspecialchars($row['gender'] ?? '') ?>"><?= $gch ?></span><?php endif; ?><?php if (!empty($row['dynamic_profile'])): ?><span class="npc-dyn-icon" title="Dynamic profile enabled">♻️</span><?php endif; ?></div>
            <div class="npc-title-actions">
                <?php if ($tagsDisp !== ''): ?>
                <span class="npc-tags-label">Tags:</span>
                <span class="npc-tags-top" title="Use Search to filter by these tags: <?= htmlspecialchars($tagsDisp) ?>"><?= htmlspecialchars($tagsDisp) ?></span>
                <?php endif; ?>
                <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>" title="Toggle favorite"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                <a class="btn btn-toggle" href="#" data-pick-picture-id="<?= $row["id"] ?>" title="Set picture">🖼️</a>
                <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>" title="Toggle lock"><?php echo !empty($row["lock_profile"]) ? "🔒" : "🔓"; ?></a>
                <?php if ((int)$row['id'] !== 1 && ($row['npc_name'] ?? '') !== 'The Narrator'): ?>
                <a class="btn btn-trash<?= !empty($row['lock_profile']) ? ' disabled' : '' ?>" href="<?= !empty($row['lock_profile']) ? '#' : ('?delete='.$row['id']) ?>" onclick="<?= !empty($row['lock_profile']) ? 'alert(\'This NPC is locked and cannot be deleted.\'); return false;' : "return confirm('Delete this NPC?');" ?>" title="<?= !empty($row['lock_profile']) ? 'Locked - cannot delete' : 'Delete' ?>">🗑️</a>
                <?php endif; ?>
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
        <button id="npc_modal_save_header" class="btn-save">Save</button>
        <button id="npc_modal_history" class="btn-cancel">View History</button>
        <button id="npc_modal_close" class="btn-cancel">Close</button>
      </div>
    </div>
    <div class="modal-body">
      <div id="npc_modal_tabs" style="display:flex; gap:8px; padding:8px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; position:sticky; top:0; z-index:2;">
        <button type="button" class="pf-tab active" data-pane="pane_manual">✍️ Manual</button>
        <button type="button" class="pf-tab" data-pane="pane_bio">📚 From Bio Database</button>
      </div>
      <div id="pane_manual" class="pf-pane active" style="padding:0;">
        <iframe id="npc_modal_iframe" src="about:blank" style="width:100%; height:70vh; border:0; background:transparent;"></iframe>
      </div>
      <div id="pane_bio" class="pf-pane" style="display:none; padding:10px;">
        <div style="display:flex; gap:12px; align-items:flex-start;">
          <div style="flex: 0 0 340px; max-width:340px; border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#2a2a2a;">
            <div style="display:flex; gap:6px; align-items:center; margin-bottom:8px;">
              <input id="bio_search_input" type="text" placeholder="Search bio database..." style="flex:1; padding:6px 8px; border:1px solid #4a4a4a; border-radius:6px; background:#2a2a2a; color:#e9efff;">
              <select id="bio_letter" style="padding:6px 8px; border:1px solid #4a4a4a; border-radius:6px; background:#2a2a2a; color:#e9efff;">
                <option value="">All</option>
                <option>A</option><option>B</option><option>C</option><option>D</option><option>E</option><option>F</option><option>G</option><option>H</option><option>I</option><option>J</option><option>K</option><option>L</option><option>M</option><option>N</option><option>O</option><option>P</option><option>Q</option><option>R</option><option>S</option><option>T</option><option>U</option><option>V</option><option>W</option><option>X</option><option>Y</option><option>Z</option>
              </select>
            </div>
            <div id="bio_list" style="height:58vh; overflow:auto; display:flex; flex-direction:column; gap:6px;"></div>
            <div id="bio_pager" style="display:flex; gap:6px; align-items:center; justify-content:center; margin-top:6px;"></div>
          </div>
          <div style="flex: 1 1 auto; min-width:0; border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#2a2a2a;">
            <div style="margin-bottom:8px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
              <label class="label-with-toggle"><input id="bio_inc_core" type="checkbox" checked> Core</label>
              <label class="label-with-toggle"><input id="bio_inc_ext" type="checkbox" checked> Extended Profile</label>
              <label class="label-with-toggle"><input id="bio_inc_oghma" type="checkbox" checked> Oghma Tags</label>
              <label class="label-with-toggle"><input id="bio_inc_vm" type="checkbox" checked> Voice & Meta</label>
              <select id="bio_profile_id" title="Assign Profile" style="margin-left:auto; padding:6px 8px; border:1px solid #4a4a4a; border-radius:6px; background:#2a2a2a; color:#e9efff;">
                <option value="">— Profile —</option>
                <?php foreach (($profileRows ?? []) as $pr): $pid=(string)($pr['id']??''); $lbl=$pr['label']??('Profile #'.$pid); $sel = ($firstProfileId === $pid) ? ' selected' : ''; ?>
                <option value="<?= htmlspecialchars($pid) ?>"<?= $sel ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <button id="bio_use_template" type="button" class="btn-base btn-primary">Use Template</button>
            </div>
            <div id="bio_detail" style="min-height:58vh;">
              <div style="color:#9fb1c9">Select a template on the left</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Gallery picker overlay -->
<div id="gallery_picker" class="modal-backdrop" style="z-index:10001;">
  <div class="modal-container" style="max-width:1200px; width:95%;">
    <div class="modal-header">
      <h2 class="modal-title">Choose Picture</h2>
      <div class="modal-actions">
        <button id="gallery_picker_close" class="btn-cancel">Close</button>
      </div>
    </div>
    <div class="modal-body" style="height:80vh;">
      <iframe id="gallery_picker_iframe" src="about:blank" style="width:100%; height:100%; border:0; background:transparent;"></iframe>
    </div>
  </div>
  
</div>

<!-- NPC History viewer overlay -->
<div id="history_viewer" class="modal-backdrop" style="z-index:10002;">
  <div class="modal-container" style="max-width:1100px; width:95%;">
    <div class="modal-header">
      <h2 class="modal-title">NPC History</h2>
      <div class="modal-actions">
        <button id="history_close" class="btn-cancel">Close</button>
      </div>
    </div>
    <div class="modal-body" style="height:75vh; display:flex; gap:10px;">
      <div id="history_list" style="flex: 0 0 320px; max-width:320px; border-right:1px solid #4a4a4a; overflow:auto; padding:8px;">
      </div>
      <div id="history_detail" style="flex: 1 1 auto; min-width:0; overflow:auto; padding:8px;">
        <div style="color:#9fb1c9">Select a snapshot to view details</div>
      </div>
    </div>
  </div>
</div>

 

<script>
(function(){
  const PROFILES_BY_ID = <?= json_encode($profilesById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  const modal = document.getElementById('npc_modal');
  const iframe = document.getElementById('npc_modal_iframe');
  function openModal(url){
    iframe.src = url;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    try {
      // Track current editing id for portrait updates
      (function(){
        try { const m = url.match(/[?&]edit=([^&]+)/); window.CURRENT_NPC_ID = m ? String(decodeURIComponent(m[1])) : ''; } catch(_e){ window.CURRENT_NPC_ID=''; }
      })();
      const tabs = document.getElementById('npc_modal_tabs');
      const bioPane = document.getElementById('pane_bio');
      const manualPane = document.getElementById('pane_manual');
      const isEdit = /[?&]edit=/.test(url);
      if (isEdit){
        if (tabs) tabs.style.display = 'none';
        if (bioPane) { bioPane.style.display = 'none'; bioPane.classList.remove('active'); }
        if (manualPane) { manualPane.style.display = 'block'; manualPane.classList.add('active'); }
      } else {
        if (tabs) tabs.style.display = 'flex';
      }
    } catch(_e){}
  }
  function closeModal(){ modal.style.display = 'none'; document.body.style.overflow = 'auto'; try { iframe.src='about:blank'; } catch(_){} }
  const headerSave = document.getElementById('npc_modal_save_header');
  if (headerSave){
    window.NPC_UPDATE_SAVE_STATE = function(){
      try {
        const doc = iframe && iframe.contentDocument;
        const nameEl = doc ? doc.getElementById('npc_name') : null;
        const val = nameEl ? String(nameEl.value||'').trim() : '';
        const disable = (val === '');
        headerSave.disabled = disable;
        if (disable) headerSave.title = 'Enter NPC Name to save'; else headerSave.removeAttribute('title');
      } catch(_e){}
    };
    // Watch for iframe content load and bind input listener
    try {
      iframe.addEventListener('load', function(){
        try {
          const doc = iframe && iframe.contentDocument;
          const nameEl = doc ? doc.getElementById('npc_name') : null;
          if (nameEl){
            ['input','change','keyup'].forEach(evt=> nameEl.addEventListener(evt, window.NPC_UPDATE_SAVE_STATE));
          }
        } catch(_e){}
        window.NPC_UPDATE_SAVE_STATE();
      });
    } catch(_e){}
    headerSave.addEventListener('click', function(){
      try {
        // Guard: require NPC name
        window.NPC_UPDATE_SAVE_STATE(); if (headerSave.disabled) { return; }
        const btn = iframe && iframe.contentDocument ? iframe.contentDocument.getElementById('npc_modal_save') : null;
        if (btn){ btn.click(); }
        // else: nothing (no bio import submit anymore)
      } catch(_e){}
    });
  }
  // View History button wiring
  (function(){
    const btn = document.getElementById('npc_modal_history');
    const overlay = document.getElementById('history_viewer');
    const listBox = document.getElementById('history_list');
    const detailBox = document.getElementById('history_detail');
    const closeBtn = document.getElementById('history_close');
    const LABELS = {
      npc_name: 'NPC Name',
      profile_id: 'Profile',
      gender: 'Gender',
      race: 'Race',
      voiceid: 'Voice ID',
      refid: 'Ref ID',
      core: 'Core',
      npc_static_bio: 'Static Bio',
      appearance: 'Appearance',
      personality: 'Personality',
      relationships: 'Relationships',
      occupation: 'Occupation',
      skills: 'Skills',
      speechstyle: 'Speech Style',
      goals: 'Goals',
      oghma_knowledge_tags: 'Oghma Tags',
      emote_moods: 'Emote Moods',
      prompt_head: 'Prompt Head',
      dynamic_profile: 'Dynamic Profile',
      npc_favorite: 'Favorite',
      lock_profile: 'Lock Profile',
      tags: 'Tags',
      base: 'Base'
    };
    function close(){ if (overlay) overlay.style.display='none'; }
    if (closeBtn) closeBtn.addEventListener('click', function(e){ e.preventDefault(); close(); });
    if (overlay) overlay.addEventListener('click', function(e){ if (e.target===overlay) close(); });
    function renderDetail(entry, prev){
      if (!entry){ detailBox.innerHTML = '<div style="color:#9fb1c9">No data</div>'; return; }
      const f = entry.fields||{}; const prevF = (prev && prev.fields) ? prev.fields : {};
      const order = ['npc_name','profile_id','gender','race','voiceid','refid','core','npc_static_bio','appearance','personality','relationships','occupation','skills','speechstyle','goals','oghma_knowledge_tags','emote_moods','prompt_head','dynamic_profile','npc_favorite','lock_profile','tags','base'];
      let html = '';
      html += '<div style="color:#cfd9ea; margin-bottom:8px;">'+(entry.when_tamrielic || (entry.created?('Created '+entry.created):'Unknown time'))+(entry.created?(' <span style="color:#9fb1c9">('+entry.created+')</span>'):'')+'</div>';
      html += '<div style="display:grid; grid-template-columns: 220px 1fr; gap:6px;">';
      order.forEach(k=>{
        let v = f[k]; const has = (v!==null && v!==undefined && String(v).trim()!=='');
        if (!has) return;
        const changed = (prevF && String(prevF[k]??'') !== String(v));
        const label = LABELS[k] || k.replace(/_/g,' ');
        if (k==='profile_id') { v = (PROFILES_BY_ID && PROFILES_BY_ID[String(v||'')]) ? PROFILES_BY_ID[String(v)] : v; }
        html += '<div style="color:rgb(242,124,17); font-weight:700;">'+label+'</div>';
        html += '<div style="border:1px solid #4a4a4a; border-radius:6px; padding:6px;'+(changed?' background:#333333;':'')+'">'+String(v).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))+'</div>';
      });
      html += '</div>';
      detailBox.innerHTML = html;
    }
    function openHistory(){
      try {
        const id = String(window.CURRENT_NPC_ID||'').trim();
        if (!id){ return; }
        if (overlay) { overlay.style.display='flex'; }
        if (listBox) { listBox.innerHTML = '<div style="color:#9fb1c9">Loading…</div>'; }
        if (detailBox) { detailBox.innerHTML = '<div style="color:#9fb1c9">Fetching history…</div>'; }
        fetch('npc_master.php?history=1&id='+encodeURIComponent(id))
          .then(r=>r.json()).then(j=>{
            if (!j || !j.ok){ listBox.innerHTML = '<div style="color:#ff6b6b">Failed to load history</div>'; detailBox.innerHTML=''; return; }
            const entries = j.entries||[];
            if (entries.length===0){ listBox.innerHTML = '<div style="color:#9fb1c9">No history yet</div>'; detailBox.innerHTML=''; return; }
            listBox.innerHTML = '';
            entries.forEach((e, idx)=>{
              const div = document.createElement('div');
              div.style.border='1px solid #4a4a4a'; div.style.borderRadius='8px'; div.style.padding='8px'; div.style.cursor='pointer'; div.style.marginBottom='6px';
              const label = e.when_tamrielic || (e.created?('Created '+e.created):('Snapshot #'+String(e.history_id||idx+1)));
              const second = e.created?('<div style="color:#9fb1c9; font-size:11px;">'+e.created+'</div>') : '';
              div.innerHTML = '<div style="font-weight:700; color:#e9efff;">'+label+'</div>'+second;
              div.addEventListener('click', function(){
                listBox.querySelectorAll('.active').forEach(n=>{ n.classList.remove('active'); n.style.background=''; });
                this.classList.add('active'); this.style.background='#333333';
                renderDetail(e, idx>0?entries[idx-1]:null);
              });
              listBox.appendChild(div);
            });
          })
          .catch(()=>{ listBox.innerHTML = '<div style="color:#ff6b6b">Failed to load history</div>'; detailBox.innerHTML=''; });
      } catch(_){}
    }
    if (btn){ btn.addEventListener('click', function(e){ e.preventDefault(); openHistory(); }); }
  })();
  document.addEventListener('click', function(e){ if (e.target && e.target.id==='npc_modal_close') closeModal(); });
  modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
  // Tabs in modal
  (function(){
    const tabs = document.querySelectorAll('#npc_modal_tabs .pf-tab');
    function activate(id){
      tabs.forEach(t=>t.classList.toggle('active', t.getAttribute('data-pane')===id));
      document.querySelectorAll('.pf-pane').forEach(p=>{ p.style.display = (p.id===id) ? 'block' : 'none'; p.classList.toggle('active', p.id===id); });
    }
    tabs.forEach(tb=> tb.addEventListener('click', ()=> activate(tb.getAttribute('data-pane'))));
    activate('pane_manual');
  })();
  // Bio DB wiring
  (function(){
    const list = document.getElementById('bio_list');
    const pager = document.getElementById('bio_pager');
    const inp = document.getElementById('bio_search_input');
    const letter = document.getElementById('bio_letter');
    const detail = document.getElementById('bio_detail');
    const useBtn = document.getElementById('bio_use_template');
    const createBtn = document.getElementById('bio_use_create');
    const incCore = document.getElementById('bio_inc_core');
    const incExt = document.getElementById('bio_inc_ext');
    const incOgh = document.getElementById('bio_inc_oghma');
    const incVM  = document.getElementById('bio_inc_vm');
    const selProfile = document.getElementById('bio_profile_id');
    let currentName = '';
    let page = 1; let total = 0; let pageSize = 20;
    async function fetchList(){
      const params = new URLSearchParams({ bio_search:'1', search:(inp.value||''), letter:(letter.value||''), page:String(page), pageSize:String(pageSize) });
      const res = await fetch('npc_master.php?'+params.toString()); let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (!j.ok) { list.innerHTML = '<div style="color:#ff6b6b">Failed to load</div>'; return; }
      total = Number(j.total||0); page = Number(j.page||1); pageSize = Number(j.pageSize||20);
      list.innerHTML = '';
      (j.items||[]).forEach(it=>{
        const div = document.createElement('div');
        div.style.border = '1px solid #4a4a4a'; div.style.borderRadius='8px'; div.style.padding='8px'; div.style.cursor='pointer';
        div.innerHTML = `<div style="font-weight:700; color:#e9efff">${it.npc_name}</div>
          <div style="color:#9fb1c9; font-size:12px; margin:4px 0">${it.core_preview||''}</div>
          <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:12px; color:#cfd9ea;">
            ${it.voiceid?('<span>Voice: '+it.voiceid+'</span>'):''}
            ${it.gender?('<span>Gender: '+it.gender+'</span>'):''}
            ${it.race?('<span>Race: '+it.race+'</span>'):''}
            ${it.refid?('<span>RefID: '+it.refid+'</span>'):''}
            <span>Extended: ${String(it.extended_filled||0)}</span>
          </div>`;
        div.addEventListener('click', ()=> loadDetail(it.npc_name));
        list.appendChild(div);
      });
      // pager
      const pages = Math.max(1, Math.ceil(total / Math.max(1,pageSize)));
      pager.innerHTML='';
      const mk = (lab, p, dis)=>{ const b=document.createElement('button'); b.textContent=lab; b.disabled=!!dis; b.addEventListener('click', ()=>{ page=p; fetchList(); }); return b; };
      pager.appendChild(mk('«', 1, page<=1));
      pager.appendChild(mk('‹', Math.max(1,page-1), page<=1));
      const start = Math.max(1, page-2), end = Math.min(pages, page+2);
      for(let i=start;i<=end;i++){ const b=mk(String(i), i, i===page); pager.appendChild(b); }
      pager.appendChild(mk('›', Math.min(pages,page+1), page>=pages));
      pager.appendChild(mk('»', pages, page>=pages));
    }
    async function loadDetail(name){
      currentName = name;
      const params = new URLSearchParams({ bio_detail:'1', name:name });
      const res = await fetch('npc_master.php?'+params.toString()); let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (!j.ok){ detail.innerHTML = '<div style="color:#ff6b6b">Failed to load detail</div>'; return; }
      const d = j.data||{};
      detail.innerHTML = `
        <div style="font-size:18px; font-weight:700; color:#e9efff;">${d.npc_name||''}</div>
        <div style="margin-top:8px; color:#cfd9ea;"><b style="color:rgb(242,124,17)">Core:</b><br>${escapeHtml(d.core||'')}</div>
        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:10px; margin-top:8px;">
          ${kv('Static', d.npc_static_bio)}
          ${kv('Personality', d.personality)}
          ${kv('Appearance', d.appearance)}
          ${kv('Relationships', d.relationships)}
          ${kv('Occupation', d.occupation)}
          ${kv('Skills', d.skills)}
          ${kv('Speech Style', d.speechstyle)}
          ${kv('Goals', d.goals)}
        </div>
        <div style="margin-top:8px; color:#cfd9ea; display:flex; gap:10px; flex-wrap:wrap;">
          ${badge('VoiceID', d.voiceid)}
          ${badge('Gender', d.gender)}
          ${badge('Race', d.race)}
          ${badge('RefID', d.refid)}
        </div>
        <div style="margin-top:8px; color:#cfd9ea;"><b style="color:rgb(242,124,17)">Oghma Tags:</b> ${escapeHtml(d.oghma_knowledge_tags||'—')}</div>
      `;
    }
    function kv(title, val){ const v=(val||'').trim(); return `<div><div style="color:rgb(242,124,17); font-weight:700;">${title}</div><div style="white-space:pre-wrap;">${escapeHtml(v||'—')}</div></div>`; }
    function badge(k, v){ v=(v||'').trim(); if (!v) return ''; return `<span style="background:#3a3a3a; border:1px solid #4a4a4a; border-radius:999px; padding:3px 8px;">${k}: ${escapeHtml(v)}</span>`; }
    function escapeHtml(s){ return String(s).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
    let deb = null; function refetch(){ if (deb) clearTimeout(deb); deb=setTimeout(()=>{ page=1; fetchList(); }, 250); }
    if (inp) inp.addEventListener('input', refetch);
    if (letter) letter.addEventListener('change', refetch);
    if (useBtn) useBtn.addEventListener('click', async ()=>{
      if (!currentName) return;
      // Load detail and fill manual form in iframe
      const params = new URLSearchParams({ bio_detail:'1', name:currentName });
      const res = await fetch('npc_master.php?'+params.toString()); let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (!j.ok) return;
      const d = j.data||{};
      const doc = iframe && iframe.contentDocument; if (!doc) return;
      function setVal(id, val){ const el = doc.getElementById(id); if (el) el.value = val==null?'':String(val); }
      function setChk(id, on){ const el = doc.getElementById(id); if (el && el.type==='checkbox') el.checked = !!on; }
      setVal('npc_name', d.npc_name||'');
      if (incCore && incCore.checked) setVal('core', d.core||'');
      if (incExt && incExt.checked) {
        setVal('npc_static_bio', d.npc_static_bio||''); setVal('personality', d.personality||''); setVal('appearance', d.appearance||''); setVal('relationships', d.relationships||''); setVal('occupation', d.occupation||''); setVal('skills', d.skills||''); setVal('speechstyle', d.speechstyle||''); setVal('goals', d.goals||'');
      }
      if (incOgh && incOgh.checked) setVal('oghma_knowledge_tags', d.oghma_knowledge_tags||'');
      if (incVM && incVM.checked) { setVal('voiceid', d.voiceid||''); setVal('gender', d.gender||''); setVal('race', d.race||''); setVal('refid', d.refid||''); }
      if (selProfile && selProfile.value) { const el = doc.getElementById('profile_id'); if (el) el.value = selProfile.value; }
      // Switch to manual tab
      document.querySelectorAll('#npc_modal_tabs .pf-tab').forEach(t=> t.classList.toggle('active', t.getAttribute('data-pane')==='pane_manual'));
      document.getElementById('pane_manual').style.display='block'; document.getElementById('pane_manual').classList.add('active');
      document.getElementById('pane_bio').style.display='none'; document.getElementById('pane_bio').classList.remove('active');
      // Update save button state after auto-filling name
      try { if (typeof window.NPC_UPDATE_SAVE_STATE === 'function') window.NPC_UPDATE_SAVE_STATE(); } catch(_e){}
    });
    if (createBtn) createBtn.addEventListener('click', async ()=>{
      if (!currentName) return;
      const fd = new FormData();
      fd.append('import_from_bio','1');
      fd.append('name', currentName);
      fd.append('include_core', (incCore && incCore.checked) ? '1':'');
      fd.append('include_extended', (incExt && incExt.checked) ? '1':'');
      fd.append('include_oghma', (incOgh && incOgh.checked) ? '1':'');
      fd.append('include_voice_meta', (incVM && incVM.checked) ? '1':'');
      if (selProfile && selProfile.value) fd.append('profile_id', selProfile.value);
      const res = await fetch('npc_master.php', { method:'POST', body: fd });
      let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
      if (j && j.ok){
        window.postMessage({ type:'npc_saved', id: j.id, data: j.data }, '*');
      } else {
        alert('Import failed: '+(j && j.error ? j.error : 'Unknown'));
      }
    });
    // initial fetch
    try { fetchList(); } catch(_){}
  })();
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
  // Gallery picker wiring
  (function(){
    const picker = document.getElementById('gallery_picker');
    const pickerIframe = document.getElementById('gallery_picker_iframe');
    const pickerClose = document.getElementById('gallery_picker_close');
    function openPickerFor(id){
      window.CURRENT_NPC_ID = String(id||'');
      if (!window.CURRENT_NPC_ID) return;
      pickerIframe.src = '<?php echo $webRoot; ?>/ui/soulgaze_gallery.php?embed=1&picker=1';
      picker.style.display = 'flex';
    }
    function closePicker(){ picker.style.display = 'none'; try { pickerIframe.src='about:blank'; } catch(_){} }
    if (pickerClose) pickerClose.addEventListener('click', function(e){ e.preventDefault(); closePicker(); });
    picker.addEventListener('click', function(e){ if (e.target===picker) closePicker(); });
    window.addEventListener('keydown', function(e){ if (picker.style.display==='flex' && e.key==='Escape') closePicker(); });
    // Receive selection from gallery
    window.addEventListener('message', async function(e){
      const d = e.data || {};
      if (d.type === 'gallery_selected'){
        const url = String(d.url||'');
        if (!url || !window.CURRENT_NPC_ID) { closePicker(); return; }
        try {
          const fd = new FormData(); fd.append('set_portrait','1'); fd.append('id', String(window.CURRENT_NPC_ID)); fd.append('source', url);
          const res = await fetch('npc_master.php', { method:'POST', body: fd });
          let j={}; try { j = await res.json(); } catch(_e){}
          if (j && j.ok) {
            closePicker();
            try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Portrait updated'; toast.classList.add('show'); } } catch(_e){}
            window.location.reload();
            return;
          }
          // Fallback: refresh only the card image
          try {
            const u = 'npc_master.php?race_icon=1&id=' + encodeURIComponent(String(window.CURRENT_NPC_ID));
            const rr = await fetch(u); let rj={}; try { rj = await rr.json(); } catch(_e){}
            const card = document.getElementById('npc_card_'+String(window.CURRENT_NPC_ID));
            if (card){ const right = card.querySelector('.npc-right'); if (right){ right.innerHTML=''; if (rj && rj.url){ right.innerHTML = '<img class=\"npc-race-art\" alt=\"Race icon\" src=\"'+rj.url+'\" />'; } } }
          } catch(_e){}
        } catch(_e){}
        closePicker();
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Portrait updated'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
    // Bind card-level pick buttons (initial render)
    document.querySelectorAll('[data-pick-picture-id]').forEach(btn=>{
      btn.addEventListener('click', function(e){ e.preventDefault(); const id = this.getAttribute('data-pick-picture-id'); if (!id) return; openPickerFor(id); });
    });
    // Expose for rebind after list refresh
    window.OPEN_GALLERY_PICKER_FOR = openPickerFor;
  })();
  // Live search and alpha sort
  const searchInput = document.getElementById('npc_search');
  // Bulk delete wiring
  (function(){
    function bindBulk(btn){
      if (!btn) return;
      btn.addEventListener('click', function(){
        const box = document.createElement('div');
        box.style.position='fixed'; box.style.inset='0'; box.style.zIndex='10050'; box.style.display='flex'; box.style.alignItems='center'; box.style.justifyContent='center'; box.style.background='rgba(0,0,0,0.65)';
        box.innerHTML = '<div style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; padding:16px; max-width:520px; width:92%; color:#e9efff;">\
          <div style="font-weight:700; color:#ff6b6b; margin-bottom:8px;">Danger: Delete ALL unlocked NPCs</div>\
          <div style="font-size:13px; color:#cfd9ea; margin-bottom:8px;">This will permanently delete every NPC that is not locked. The Narrator and any locked profiles will be preserved.</div>\
          <label style="display:block; font-size:13px; margin:6px 0; color:#cfd9ea;">Type <b style="color:#ffd166">Delete</b> to confirm:</label>\
          <input id="bulk_del_confirm" type="text" style="width:100%; padding:8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff;"/>\
          <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">\
            <button id="bulk_del_cancel" class="btn-cancel">Cancel</button>\
            <button id="bulk_del_ok" class="btn-danger" disabled>Delete</button>\
          </div></div>';
        document.body.appendChild(box);
        const inp = box.querySelector('#bulk_del_confirm');
        const ok  = box.querySelector('#bulk_del_ok');
        const cancel = box.querySelector('#bulk_del_cancel');
        function upd(){ ok.disabled = (String(inp.value||'').trim() !== 'Delete'); }
        inp.addEventListener('input', upd); upd(); inp.focus();
        cancel.addEventListener('click', function(){ document.body.removeChild(box); });
        ok.addEventListener('click', async function(){
          ok.disabled = true; try {
            const fd = new FormData(); fd.append('bulk_delete_npcs','1'); fd.append('confirm', String(inp.value||''));
            const res = await fetch('npc_master.php', { method:'POST', body: fd });
            let j={}; try{ j=await res.json(); }catch(_){ j={ok:false}; }
            document.body.removeChild(box);
            if (j && j.ok){
              try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent='Deleted '+String(j.deleted||0)+' NPCs'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 2000); } } catch(_){}
              refreshList(1);
            } else {
              alert('Bulk delete failed: '+(j && j.error ? j.error : 'Unknown'));
            }
          } catch(_e){ ok.disabled=false; }
        });
      });
    }
    // expose for rebind after AJAX refresh
    window.bindNpcBulkDelete = bindBulk;
    bindBulk(document.getElementById('npc_bulk_delete_btn'));
  })();
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
      // rebind bulk delete in refreshed DOM
      try { if (window.bindNpcBulkDelete) window.bindNpcBulkDelete(document.getElementById('npc_bulk_delete_btn')); } catch(_){}
      // Hook pagination links to AJAX
      // Bind pick-picture buttons after refresh
      document.querySelectorAll('[data-pick-picture-id]').forEach(btn=>{
        btn.addEventListener('click', function(e){ e.preventDefault(); const id = this.getAttribute('data-pick-picture-id'); if (!id) return; if (typeof window.OPEN_GALLERY_PICKER_FOR === 'function') window.OPEN_GALLERY_PICKER_FOR(id); });
      });
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
    debTimer = setTimeout(()=>refreshList(page), 500)
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
              </div>
              <div class="npc-right"></div>
            </div>
            `;
          grid.prepend(div);
          // Wire edit button
          div.addEventListener('click', function(ev){ if (ev.target.closest('.npc-title-actions')) return; ev.preventDefault(); openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1'); });
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
