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

<main>

<?php
$GLOBALS["db"] = new sql();
$npc = new NpcMaster();

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $npc->create($_POST);
    header("Location: npc_master.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
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
                'npc_name','npc_favorite','lock_profile','prompt_head','npc_static_bio','oghma_knowledge_tags','emote_moods','personality','relationships','occupation','skills','speechstyle','goals','voiceid','metadata','extended_data','gender','race','refid','profile_id','dynamic_profile','gamets_last_updated','base','core','tags'
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

$where = "1=1";
if ($q !== ''){
    $qEsc = "%".$GLOBALS['db']->escape($q)."%";
    // Match by name primarily; include a few related fields
    $where .= " and (npc_name ilike '".$qEsc."' or coalesce(race,'') ilike '".$qEsc."' or coalesce(voiceid,'') ilike '".$qEsc."' or coalesce(refid,'') ilike '".$qEsc."')";
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
      <span style="border:none; background:transparent; color:#9fb1c9;">Page <?= $page ?> / <?= $totalPages ?></span>
      <span style="border:none; background:transparent; color:#9fb1c9;">Total <?= $totalRows ?></span>
      <button id="npc_create_btn" type="button" style="margin-left:8px;">+ Create NPC</button>
    </div>
    <div class="npc-grid">
    <?php foreach ($data as $row): ?>
        <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
            <div class="npc-title"><span class="npc-name"><?= htmlspecialchars($row["npc_name"]) ?></span></div>
            <div class="npc-divider"></div>
            <div class="npc-fields">
                <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"><?= htmlspecialchars($row["gender"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"><?= htmlspecialchars($row["race"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"><?= htmlspecialchars($row["voiceid"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">RefID:</span> <span class="npc-refid"><?= htmlspecialchars($row["refid"] ?? "") ?></span></div>
                <div class="npc-line"><span class="npc-muted">OGHMA:</span> <span class="npc-oghma"><?= htmlspecialchars($row["oghma_knowledge_tags"] ?? "") ?></span></div>
            </div>
            <div class="npc-actions">
                <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
                <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>"><?php echo !empty($row["lock_profile"]) ? "🔒 Locked" : "🔓"; ?></a>
                <a class="btn btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');">Delete</a>
                <a class="btn" href="?tag=<?= $row["id"] ?>">Tag</a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
    exit;
}
?>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php endif; ?>

<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ob_end_clean(); ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>html,body{background:#0e1624;} main{background:#0e1624; padding:12px;} .form-container{background:#0e1624; border:1px solid rgba(138,155,182,0.35); border-radius:8px;}</style>
<form method="post" onsubmit='return false' style='display:block'>
<?php } else { ?>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
<?php } ?>
    <style>
    .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px 16px; }
    @media (max-width: 900px){ .form-grid { grid-template-columns: 1fr; } }
    .form-item { display:flex; flex-direction:column; gap:6px; }
    .form-item label { font-weight:700; color:#e9efff; }
    .form-item .hint { color:#9fb1c9; font-size:12px; line-height:1.35; }
    .form-item textarea { min-height:96px; }
    .form-item input[type="text"], .form-item textarea, .form-item select { background:#0e1624; color:#e9efff; border:1px solid rgba(138,155,182,0.35); border-radius:6px; padding:8px 10px; }
    .form-item input[type="checkbox"] { transform: scale(1.05); }
    .span-2 { grid-column: 1 / -1; }
    .checkbox-inline { display:flex; align-items:center; gap:8px; }
    </style>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <div class="form-grid">
        <div class="form-item span-2">
            <label for="npc_name">NPC Name</label>
            <input type="text" id="npc_name" name="npc_name" placeholder="e.g. Aela the Huntress" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">
            <small class="hint">Display name shown in UI and used to build prompts. Changing it will also update the MD5 key.</small>
        </div>

        <div class="form-item">
            <label for="npc_favorite">Favorite</label>
            <div class="checkbox-inline">
                <input type="checkbox" id="npc_favorite" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
                <span class="hint">Pin this NPC for quick access.</span>
            </div>
        </div>

        <div class="form-item">
            <label for="lock_profile">Lock Profile</label>
            <div class="checkbox-inline">
                <input type="checkbox" id="lock_profile" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
                <span class="hint">Prevents dynamic systems from modifying this NPC's profile.</span>
            </div>
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
            <label for="oghma_knowledge_tags">OGHMA Knowledge Tags</label>
            <textarea id="oghma_knowledge_tags" name="oghma_knowledge_tags" placeholder="Comma-separated keywords to fetch Oghma articles."><?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?></textarea>
            <small class="hint">Keywords used by Oghma Infinium for topic retrieval. Prefer lowercase with underscores.</small>
        </div>

        <div class="form-item span-2">
            <label for="emote_moods">Emote Moods</label>
            <textarea id="emote_moods" name="emote_moods" placeholder="Allowed mood/emote set (comma-separated).">
<?= htmlspecialchars($editItem["emote_moods"] ?? "") ?></textarea>
            <small class="hint">Whitelist of mood/emote cues the NPC may use (e.g., calm, angry, playful).</small>
        </div>

        <div class="form-item span-2">
            <label for="personality">Personality</label>
            <textarea id="personality" name="personality" placeholder="Personality traits and speaking characteristics."><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>
            <small class="hint">Concise traits that guide tone and behavior. Avoid contradictions with Core.</small>
        </div>

        <div class="form-item span-2">
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

        <div class="form-item">
            <label for="voiceid">Voice ID</label>
            <input type="text" id="voiceid" name="voiceid" placeholder="Matches TTS voice identifier" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">
            <small class="hint">Identifier for the TTS backend (e.g., ElevenLabs, XTTS, etc.).</small>
        </div>

        <div class="form-item">
            <label for="gender">Gender</label>
            <input type="text" id="gender" name="gender" placeholder="e.g. female, male, nonbinary" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">
            <small class="hint">Used for pronouns and voice selection guidance.</small>
        </div>

        <div class="form-item">
            <label for="base">Base</label>
            <input type="text" id="base" name="base" placeholder="Base actor/form ID if applicable" value="<?= htmlspecialchars($editItem["base"] ?? "") ?>">
            <small class="hint">Optional: base form identifier or template this NPC derives from.</small>
        </div>

        <div class="form-item">
            <label for="race">Race</label>
            <input type="text" id="race" name="race" placeholder="e.g. nord, dunmer, argonian" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">
            <small class="hint">Lore-accurate race label used in prompts.</small>
        </div>

        <div class="form-item">
            <label for="refid">Ref ID</label>
            <input type="text" id="refid" name="refid" placeholder="Game reference ID (000...)" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>">
            <small class="hint">Skyrim reference ID for in-game linkage (optional).</small>
        </div>

        <div class="form-item">
            <label for="profile_id">Profile</label>
            <?= renderSelect($npc, "profile_id", "Profile", $editItem["profile_id"] ?? "") ?>
            <small class="hint">Select which CHIM profile this NPC uses for AI behavior.</small>
        </div>

        <div class="form-item">
            <label for="dynamic_profile">Dynamic Profile</label>
            <div class="checkbox-inline">
                <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?= !empty($editItem["dynamic_profile"]) ? "checked" : "" ?>>
                <span class="hint">Allow systems to evolve the profile based on gameplay events.</span>
            </div>
        </div>

        <div class="form-item span-2">
            <label for="tags">Tags</label>
            <input type="text" id="tags" name="tags" placeholder="Comma-separated labels for search and grouping" value="<?= htmlspecialchars($editItem["tags"] ?? "") ?>">
            <small class="hint">Free-form labels to organize and filter NPCs.</small>
        </div>

        <div class="form-item span-2 metadata-block">
            <label for="metadata">Metadata (JSON)</label>
            <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
            <small class="hint">Advanced: arbitrary key/value metadata. Use the JSON editor below.</small>
            <div id="metadata"></div>
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
                    const newId = json.id || payload.id || <?= json_encode($editItem['id'] ?? '') ?>;
                    payload.id = newId;
                    window.parent.postMessage({ type:'npc_saved', id: newId, data: payload }, '*');
                } else {
                    alert('Save failed: '+((json && json.error) ? json.error : res.status));
                }
            });
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
.npc-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:14px; }
@media (max-width: 1100px){ .npc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px){ .npc-grid { grid-template-columns: 1fr; } }
.npc-card { background:linear-gradient(180deg, #101826 0%, #0d1117 100%); border:1px solid rgba(138,155,182,0.35); border-radius:14px; padding:14px; display:flex; flex-direction:column; gap:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); transition: transform .12s ease, box-shadow .12s ease; cursor:pointer; }
.npc-card:hover { transform: translateY(-2px) scale(1.01); box-shadow: 0 10px 24px rgba(0,0,0,0.45); }
.npc-title { font-weight:800; color:#e9efff; font-size:18px; text-align:center; letter-spacing:0.3px; }
.npc-divider { height:1px; background: linear-gradient(90deg, rgba(138,155,182,0), rgba(138,155,182,0.5), rgba(138,155,182,0)); margin:2px 0 6px; }
.npc-fields { display:flex; flex-direction:column; gap:8px; }
.npc-line { color:#cfd9ea; font-size:13px; line-height:1.35; }
.npc-muted { color:#9fb1c9; }
.npc-actions { display:flex; gap:8px; margin-top:6px; justify-content:center; }
.npc-actions .btn { padding:6px 10px; border-radius:6px; border:1px solid rgba(138,155,182,0.35); background:#1a2233; color:#e9efff; text-decoration:none; cursor:pointer; }
.npc-actions .btn:hover { background:#1f2a40; }
.npc-actions .btn-danger { background:#311; border-color:#633; }
.npc-actions .btn-danger:hover { background:#511; }
.npc-actions .btn-toggle { background:#21304a; }
.npc-actions .btn-toggle.active { background:#ffb862; color:#111; border-color:#ffb862; font-weight:700; }
</style>
<style>
/* Modal styling aligned with Oghma edit modal */
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:10000; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0; }
.modal-container { position:relative; top:auto; left:auto; transform:none; margin: 120px auto 40px auto; max-width:1000px; width:90%; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid rgba(138,155,182,0.2); background:#0e1624; position:sticky; top:0; z-index:2; }
.modal-title { margin:0; font-weight:700; color:#e9efff; }
.modal-body { max-height:calc(85vh - 100px); overflow-y:auto; background:#0e1624; }
.modal-close { background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; }
.modal-actions { display:flex; gap:8px; align-items:center; }
.modal-save { background:#ffb862; color:#111; border:1px solid #ffb862; border-radius:6px; padding:6px 12px; cursor:pointer; font-weight:700; }
</style>
<?php if ($totalPages > 1): ?>
<style>
.pagination { display:flex; gap:6px; align-items:center; justify-content:center; margin:10px 0 12px; flex-wrap:wrap; }
.pagination a, .pagination span { padding:6px 10px; border-radius:6px; border:1px solid rgba(138,155,182,0.35); background:#1a2233; color:#e9efff; text-decoration:none; }
.pagination a:hover { background:#1f2a40; }
.pagination .active { background:#ffb862; color:#111; border-color:#ffb862; font-weight:700; }
.pagination .disabled { opacity:0.5; pointer-events:none; }
.pagination button { padding:6px 10px; border-radius:6px; border:1px solid rgba(138,155,182,0.35); background:#1a2233; color:#e9efff; cursor:pointer; }
.pagination button:hover { background:#1f2a40; }
.filter-inline { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.filter-inline input[type="text"] { padding:4px 8px; border-radius:6px; border:1px solid rgba(138,155,182,0.35); background:#1a2233; color:#e9efff; height:28px; }
.filter-inline select { padding:4px 8px; border-radius:6px; border:1px solid rgba(138,155,182,0.35); background:#1a2233; color:#e9efff; height:28px; }
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
  <span style="border:none; background:transparent; color:#9fb1c9;">Page <?= $page ?> / <?= $totalPages ?></span>
  <span style="border:none; background:transparent; color:#9fb1c9;">Total <?= $totalRows ?></span>
  <button id="npc_create_btn" type="button" style="margin-left:8px;">+ Create NPC</button>
</div>
<?php endif; ?>
<div class="npc-grid">
<?php foreach ($data as $row): ?>
    <div class="npc-card" id="npc_card_<?= htmlspecialchars($row["id"]) ?>" data-id="<?= htmlspecialchars($row["id"]) ?>">
        <div class="npc-title"><span class="npc-name"><?= htmlspecialchars($row["npc_name"]) ?></span></div>
        <div class="npc-divider"></div>
        <div class="npc-fields">
            <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"><?= htmlspecialchars($row["gender"] ?? "") ?></span></div>
            <div class="npc-line"><span class="npc-muted">Race:</span> <span class="npc-race"><?= htmlspecialchars($row["race"] ?? "") ?></span></div>
            <div class="npc-line"><span class="npc-muted">Voice:</span> <span class="npc-voiceid"><?= htmlspecialchars($row["voiceid"] ?? "") ?></span></div>
            <div class="npc-line"><span class="npc-muted">RefID:</span> <span class="npc-refid"><?= htmlspecialchars($row["refid"] ?? "") ?></span></div>
            <div class="npc-line"><span class="npc-muted">OGHMA:</span> <span class="npc-oghma"><?= htmlspecialchars($row["oghma_knowledge_tags"] ?? "") ?></span></div>
        </div>
        <div class="npc-actions">
            <a class="btn btn-toggle <?= !empty($row["npc_favorite"]) ? "active" : "" ?>" href="#" data-favorite-id="<?= $row["id"] ?>"><?php echo !empty($row["npc_favorite"]) ? "★" : "☆"; ?></a>
            <a class="btn btn-toggle <?= !empty($row["lock_profile"]) ? "active" : "" ?>" href="#" data-lock-id="<?= $row["id"] ?>"><?php echo !empty($row["lock_profile"]) ? "🔒 Locked" : "🔓"; ?></a>
            <a class="btn btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');">Delete</a>
            <a class="btn" href="?tag=<?= $row["id"] ?>">Tag</a>
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
      <iframe id="npc_modal_iframe" src="about:blank" style="width:100%; height:75vh; border:0; background:#0e1624;"></iframe>
    </div>
  </div>
</div>

 

<script>
(function(){
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
  document.querySelectorAll('.npc-card').forEach(card=>{
    card.addEventListener('click', function(ev){
      if (ev.target.closest('.npc-actions')) return;
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
          if (ev.target.closest('.npc-actions')) return;
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
          if (json && json.ok){ const active = Number(json.locked||0)===1; this.classList.toggle('active', active); this.textContent = active ? '🔒 Locked' : '🔓'; }
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
        this.textContent = active ? '★ Favorited' : '☆ Favorite';
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
        this.textContent = active ? '🔒 Locked' : '🔓 Unlock';
        try { const toast=document.getElementById('toast'); if (toast){ toast.querySelector('.message').textContent= active?'Locked profile':'Unlocked profile'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1500); } } catch(_e){}
      }
    });
  });
  // Receive save events from iframe and update the card inline
  window.addEventListener('message', function(e){
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
            <div class="npc-title"><span class="npc-name"></span></div>
            <div class="npc-line"><span class="npc-muted">Gender:</span> <span class="npc-gender"></span>, <span class="npc-muted">Race:</span> <span class="npc-race"></span>, <span class="npc-muted">Voice:</span> <span class="npc-voiceid"></span>, <span class="npc-muted">RefID:</span> <span class="npc-refid"></span></div>
            <div class="npc-line"><span class="npc-muted">OGHMA:</span> <span class="npc-oghma"></span></div>
            <div class="npc-actions">
                <a class="btn btn-danger" href="?delete=${id}" onclick="return confirm('Delete this NPC?');">Delete</a>
                <a class="btn" href="?tag=${id}">Tag</a>
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
        setText('.npc-oghma', data.oghma_knowledge_tags);
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
