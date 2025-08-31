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
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

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
$rowCountRow = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM core_npc_master");
$totalRows = intval($rowCountRow['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$data = $npc->getAll("1=1 order by coalesce(gamets_last_updated,0) desc limit {$perPage} offset {$offset}");
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $npc->getById($_GET["edit"]);
}
?>

<h1>NPC Master</h1>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New NPC</h2>
<?php endif; ?>

<div class="form-container">
<?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ob_end_clean(); ?>
<form method="post" onsubmit='return false' style='display:block'>
<?php } else { ?>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
<?php } ?>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <label for="npc_name">NPC Name</label>
    <input type="text" id="npc_name" name="npc_name" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">

    <label for="npc_favorite">
        <input type="checkbox" id="npc_favorite" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
        Favorite
    </label><br>

    <label for="lock_profile">
        <input type="checkbox" id="lock_profile" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
        Lock Profile
    </label><br>

    <label for="prompt_head">Prompt Head</label>
    <textarea id="prompt_head" name="prompt_head"><?= htmlspecialchars($editItem["prompt_head"] ?? "") ?></textarea>

    <label for="core">Core </label>
    <textarea id="core" name="core"><?= htmlspecialchars($editItem["core"] ?? "") ?></textarea>

    <label for="npc_static_bio">Static Bio</label>
    <textarea id="npc_static_bio" name="npc_static_bio"><?= htmlspecialchars($editItem["npc_static_bio"] ?? "") ?></textarea>

    <label for="oghma_knowledge_tags">OGHMA Knowledge Tags</label>
    <textarea id="oghma_knowledge_tags" name="oghma_knowledge_tags"><?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?></textarea>

    <label for="emote_moods">Emote Moods</label>
    <textarea id="emote_moods" name="emote_moods"><?= htmlspecialchars($editItem["emote_moods"] ?? "") ?></textarea>

    <label for="personality">Personality</label>
    <textarea id="personality" name="personality"><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>

    <label for="relationships">Relationships</label>
    <textarea id="relationships" name="relationships"><?= htmlspecialchars($editItem["relationships"] ?? "") ?></textarea>

    <label for="occupation">Occupation</label>
    <textarea id="occupation" name="occupation"><?= htmlspecialchars($editItem["occupation"] ?? "") ?></textarea>

    <label for="skills">Skills</label>
    <textarea id="skills" name="skills"><?= htmlspecialchars($editItem["skills"] ?? "") ?></textarea>

    <label for="speechstyle">Speech Style</label>
    <textarea id="speechstyle" name="speechstyle"><?= htmlspecialchars($editItem["speechstyle"] ?? "") ?></textarea>

    <label for="goals">Goals</label>
    <textarea id="goals" name="goals"><?= htmlspecialchars($editItem["goals"] ?? "") ?></textarea>

    <label for="voiceid">Voice ID</label>
    <input type="text" id="voiceid" name="voiceid" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">

    <label for="gender">Gender</label>
    <input type="text" id="gender" name="gender" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">

    <label for="base">Base</label>
    <input type="text" id="base" name="base" value="<?= htmlspecialchars($editItem["base"] ?? "") ?>">

    <label for="race">Race</label>
    <input type="text" id="race" name="race" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">


    <label for="refid">Ref ID</label>
    <input type="text" id="refid" name="refid" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>">

    <label for="profile_id">Profile ID</label>
    <?= renderSelect($npc, "profile_id", "Profile", $editItem["profile_id"] ?? "") ?>

    <label for="dynamic_profile">
        <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?= !empty($editItem["dynamic_profile"]) ? "checked" : "" ?>>
        Dynamic Profile
    </label>

    <label for="tags">Tags</label>
    <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($editItem["tags"] ?? "") ?>">


    <br/>
    <label for="metadata">Metadata (JSON)</label>
    <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea><br>
    <div id="metadata"></div>

    <label for="extended_data">Extended data (JSON)</label>
    <textarea name="extended_data" style="display:none"><?= htmlspecialchars($editItem["extended_data"] ?? "") ?></textarea><br>
    <div id="extended_data"></div>

    <?php if (isset($_GET['partial']) && $_GET['partial']=='1') { ?>
        <button type="button" id="npc_modal_save" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
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
</div>

<h2>All NPCs</h2>
<style>
.npc-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:14px; }
@media (max-width: 1100px){ .npc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px){ .npc-grid { grid-template-columns: 1fr; } }
.npc-card { background:linear-gradient(180deg, #101826 0%, #0d1117 100%); border:1px solid rgba(138,155,182,0.35); border-radius:14px; padding:14px; display:flex; flex-direction:column; gap:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); transition: transform .12s ease, box-shadow .12s ease; }
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
</style>
<?php if ($totalPages > 1): ?>
<style>
.pagination { display:flex; gap:6px; align-items:center; justify-content:center; margin:10px 0 12px; flex-wrap:wrap; }
.pagination a, .pagination span { padding:6px 10px; border-radius:6px; border:1px solid rgba(138,155,182,0.35); background:#1a2233; color:#e9efff; text-decoration:none; }
.pagination a:hover { background:#1f2a40; }
.pagination .active { background:#ffb862; color:#111; border-color:#ffb862; font-weight:700; }
.pagination .disabled { opacity:0.5; pointer-events:none; }
</style>
<div class="pagination">
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
            <a class="btn" href="#" data-edit-id="<?= $row["id"] ?>">Edit</a>
            <a class="btn btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');">Delete</a>
            <a class="btn" href="?tag=<?= $row["id"] ?>">Tag</a>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div id="npc_modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:10000; align-items:center; justify-content:center;">
  <div style="width:90%; max-width:1000px; max-height:85vh; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; position:relative; overflow:auto;">
    <button id="npc_modal_close" style="position:absolute; top:8px; right:10px; background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;">Close</button>
    <iframe id="npc_modal_iframe" src="about:blank" style="width:100%; height:85vh; border:0; background:#0e1624;"></iframe>
  </div>
  <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
</div>

 

<script>
(function(){
  const modal = document.getElementById('npc_modal');
  const iframe = document.getElementById('npc_modal_iframe');
  function openModal(url){ iframe.src = url; modal.style.display = 'flex'; }
  function closeModal(){ modal.style.display = 'none'; try { iframe.src='about:blank'; } catch(_){} }
  document.addEventListener('click', function(e){ if (e.target && e.target.id==='npc_modal_close') closeModal(); });
  modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
  document.querySelectorAll('[data-edit-id]').forEach(btn=>{
    btn.addEventListener('click', function(ev){ ev.preventDefault(); const id=this.getAttribute('data-edit-id'); if (!id) return; openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1'); });
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
                <a class="btn" href="#" data-edit-id="${id}">Edit</a>
                <a class="btn btn-danger" href="?delete=${id}" onclick="return confirm('Delete this NPC?');">Delete</a>
                <a class="btn" href="?tag=${id}">Tag</a>
            </div>`;
          grid.prepend(div);
          // Wire edit button
          const btn = div.querySelector('[data-edit-id]');
          if (btn) btn.addEventListener('click', function(ev){ ev.preventDefault(); openModal('npc_master.php?edit='+encodeURIComponent(id)+'&partial=1'); });
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
 include(__DIR__."/tmpl/metadata_json_editor.php");
// Provides Datatables
 include(__DIR__."/tmpl/data_tables.php");
?>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
