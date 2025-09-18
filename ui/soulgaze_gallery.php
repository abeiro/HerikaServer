<?php
session_start();

// Determine web root for URL building (same logic used across UI pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "🖼️ Soulgaze Gallery - CHIM";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    main { padding-top: 60px; padding-bottom: 40px; }
    .gallery-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:6px 0 12px; }
    .gallery-header h1 { margin:0; font-family:'MagicCards', serif; word-spacing:6px; color:rgb(242,124,17); font-size:1.8em; }
    .gallery-meta { color:#cfd9ea; font-size:13px; }
    .grid { display:grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap:10px; }
    @media (max-width: 1600px){ .grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    @media (max-width: 1300px){ .grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 1000px){ .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 720px){ .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 480px){ .grid { grid-template-columns: 1fr; } }
    .card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; }
    .thumb { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; cursor:pointer; background:#1a1a1a; }
    .info { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 8px; }
    .name { color:#e9efff; font-size:12px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .actions { display:flex; gap:6px; }
    .btn { padding:4px 8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; text-decoration:none; cursor:pointer; font-size:12px; }
    .btn:hover { background:#3a3a3a; }
    .empty { color:#9fb1c9; text-align:center; margin:30px 0; }
    /* Lightbox */
    .lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:10000; align-items:center; justify-content:center; padding:20px; }
    .lightbox .inner { position:relative; max-width:95vw; max-height:92vh; }
    .lightbox img { max-width:95vw; max-height:92vh; display:block; border:1px solid #4a4a4a; border-radius:8px; background:#111; }
    .lightbox .tools { position:absolute; top:8px; right:8px; display:flex; gap:6px; }
    .lightbox .tools a, .lightbox .tools button { padding:6px 10px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; cursor:pointer; text-decoration:none; font-weight:700; }
    .lightbox .tools a:hover, .lightbox .tools button:hover { background:#3a3a3a; }
</style>
<?php

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$isEmbed) {
    include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
}

// Scan soundcache recursively for image files
$rootFs = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'soundcache');
$images = [];
if ($rootFs && is_dir($rootFs)) {
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootFs, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $fi) {
            if (!$fi->isFile()) continue;
            $ext = strtolower(pathinfo($fi->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'], true)) continue;
            $absPath = $fi->getPathname();
            $rel = substr($absPath, strlen($rootFs) + 1);
            $rel = str_replace('\\', '/', $rel);
            $url = $webRoot . '/soundcache/' . $rel;
            $images[] = [
                'name' => $fi->getFilename(),
                'url' => $url,
                'rel' => $rel,
                'mtime' => $fi->getMTime(),
                'size' => $fi->getSize()
            ];
        }
    } catch (Throwable $e) {
        // Render error box below
    }
}
// Sort newest first
usort($images, function($a, $b){ return $b['mtime'] <=> $a['mtime']; });
?>

<div class="container-fluid">
    <div class="gallery-header">
        <h1>🖼️ Soulgaze Gallery</h1>
        <div class="gallery-meta">Found <?php echo number_format(count($images)); ?> image(s)</div>
    </div>
    <?php if (empty($images)): ?>
        <div class="empty">No images found in <code>/soundcache</code>.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($images as $img): $n = $img['name']; $u = $img['url']; ?>
                <div class="card" data-url="<?php echo htmlspecialchars($u); ?>">
                    <img class="thumb" src="<?php echo htmlspecialchars($u); ?>" alt="<?php echo htmlspecialchars($n); ?>" loading="lazy">
                    <div class="info">
                        <div class="name" title="<?php echo htmlspecialchars($img['rel']); ?>"><?php echo htmlspecialchars($n); ?></div>
                        <div class="actions">
                            <a class="btn" href="<?php echo htmlspecialchars($u); ?>" download>Download</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="lightbox" class="lightbox">
    <div class="inner">
        <div class="tools">
            <a id="lb_open" href="#" target="_blank" rel="noopener">Open</a>
            <a id="lb_dl" href="#" download>Download</a>
            <button id="lb_close" type="button">Close</button>
        </div>
        <img id="lb_img" src="" alt="preview">
    </div>
    
    
</div>

<script>
(function(){
  const lb = document.getElementById('lightbox');
  const lbImg = document.getElementById('lb_img');
  const lbOpen = document.getElementById('lb_open');
  const lbDl = document.getElementById('lb_dl');
  const lbClose = document.getElementById('lb_close');
  function open(url){ if (!lb) return; lbImg.src=url; lbOpen.href=url; lbDl.href=url; lb.style.display='flex'; document.body.style.overflow='hidden'; }
  function close(){ if (!lb) return; lb.style.display='none'; lbImg.removeAttribute('src'); document.body.style.overflow='auto'; }
  document.addEventListener('click', function(e){ const card = e.target && e.target.closest && e.target.closest('.card'); if (!card) return; const img = card.querySelector('.thumb'); if (!img) return; if (e.target.tagName === 'A') return; e.preventDefault(); open(img.src); });
  if (lbClose) lbClose.addEventListener('click', close);
  if (lb) lb.addEventListener('click', function(e){ if (e.target === lb) close(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') close(); });
})();
</script>

<?php
if (!$isEmbed) {
    include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");
}

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


