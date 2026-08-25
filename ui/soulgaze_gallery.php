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
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."visual_context.php");

$TITLE = "🖼️ Soulgaze Gallery - CHIM";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    main { padding-top: 60px; padding-bottom: 40px; }
    @font-face { font-family: 'MagicCards'; src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype'); font-weight: normal; font-style: normal; }
    .gallery-header { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; margin:10px 0 16px; }
    .gallery-header h1 { margin:0; font-family:'MagicCards', serif; word-spacing:8px; color:rgb(242,124,17); font-size:2.2em; text-align:center; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
    .gallery-meta { color:#cfd9ea; font-size:13px; text-align:center; }
    .grid { display:grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap:10px; }
    @media (max-width: 1600px){ .grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    @media (max-width: 1300px){ .grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 1000px){ .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 720px){ .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 480px){ .grid { grid-template-columns: 1fr; } }
    .card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; }
    .thumb { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; cursor:pointer; background:#1a1a1a; }
    .info { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 8px; }
    .visual-editor { border-top:1px solid #4a4a4a; padding:8px; display:grid; gap:7px; }
    .visual-editor textarea { width:100%; min-height:76px; resize:vertical; box-sizing:border-box; border:1px solid #4a4a4a; border-radius:5px; background:#171717; color:#e9efff; padding:7px; font:inherit; font-size:12px; }
    .visual-editor-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; color:#9fb1c9; font-size:11px; }
    .visual-editor-options { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .visual-editor-options label { display:flex; gap:5px; align-items:center; color:#cfd9ea; font-size:11px; }
    .name { color:#e9efff; font-size:12px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .actions { display:flex; gap:6px; }
    .btn { padding:4px 8px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; text-decoration:none; cursor:pointer; font-size:12px; }
    .btn:hover { background:#3a3a3a; }
    .empty { color:#9fb1c9; text-align:center; margin:30px 0; }
    /* Lightbox */
    .lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:10000; align-items:center; justify-content:center; padding:20px; }
    .lightbox .inner { position:relative; max-width:95vw; max-height:92vh;min-width: 1024px;margin-left: auto;margin-right: auto;text-align: center;background-color: black; }
    .lightbox img { max-width:95vw; max-height:92vh; display:block; border:1px solid #4a4a4a; border-radius:8px; background:#111; margin-left: auto;margin-right: auto;}
    .lightbox .tools { position:absolute; top:8px; right:8px; display:flex; gap:6px;z-index:10 }
    .lightbox .tools a, .lightbox .tools button { font-size:14px;padding:6px 10px; border-radius:6px; border:1px solid #4a4a4a; background:#2a2a2a; color:#e9efff; cursor:pointer; text-decoration:none; font-weight:700; }
    .lightbox .tools a:hover, .lightbox .tools button:hover { background:#3a3a3a; }
    video {object-fit: scale-down; max-height: 480px;}
    body {min-height:auto}
    .vthumb {position: relative;display: inline-block;}
    .dropdown { position: relative; display: inline-block; }
    .dropdown-content { display: none; position: absolute; background-color: #2a2a2a; min-width: 200px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); padding: 0; z-index: 1; border: 1px solid #4a4a4a; border-radius: 6px; top: 100%; right: 0; margin-top: 6px; }
    .dropdown-content a { color: #e9efff; padding: 12px 16px; text-decoration: none; display: block; font-size: 12px; border: none; background: none; cursor: pointer; text-align: left; width: 100%; box-sizing: border-box; }
    .dropdown-content a:hover { background-color: #3a3a3a; }
    .dropdown-content a:first-child { border-radius: 5px 5px 0 0; }
    .dropdown-content a:last-child { border-radius: 0 0 5px 5px; }
    .dropdown-toggle:after { content: ' ▼'; font-size: 10px; }
 
</style>
<?php

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
$isPicker = isset($_GET['picker']) && $_GET['picker'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visual_context_action'])) {
    $recordId = intval($_POST['visual_context_id'] ?? 0);
    $action = strval($_POST['visual_context_action']);
    if ($action === 'save') {
        chimVisualContextUpdate($recordId, [
            'description' => $_POST['description'] ?? '',
            'locked' => !empty($_POST['locked']),
        ]);
    } elseif ($action === 'delete') {
        chimVisualContextDelete($recordId);
    }

    $qs = [];
    if ($isEmbed) $qs['embed'] = '1';
    if ($isPicker) $qs['picker'] = '1';
    header('Location: soulgaze_gallery.php' . (count($qs) ? ('?' . http_build_query($qs)) : ''));
    exit;
}

// Handle direct uploads into the gallery
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_image'])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    $file = $_FILES['upload_image'] ?? null;
    if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadError = 'Upload failed';
    } else {
        $extAllowed = ['jpg','jpeg','png'];
        $maxBytes = 20 * 1024 * 1024; // 20MB
        $name = (string)($file['name'] ?? '');
        $tmp  = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $extAllowed, true)) {
            $uploadError = 'Only JPG/PNG are allowed';
        } elseif ($size <= 0 || $size > $maxBytes) {
            $uploadError = 'File too large';
        } else {
            $galleryRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data/pictures/gallery');
            if ($galleryRoot === false) {
                $uploadError = 'Gallery folder not found';
            } else {
                $uploadsDir = $galleryRoot . DIRECTORY_SEPARATOR . 'uploads';
                if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }
                $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME));
                if ($safeBase === '' || $safeBase === '_' ) { $safeBase = 'image'; }
                $fname = date('Ymd_His') . '_' . uniqid($safeBase . '_', true) . '.' . $ext;
                $dst = $uploadsDir . DIRECTORY_SEPARATOR . $fname;
                if (@move_uploaded_file($tmp, $dst)) {
                    // Redirect to avoid resubmission; preserve picker/embed flags
                    $qs = [];
                    if ($isEmbed) $qs['embed'] = '1';
                    if ($isPicker) $qs['picker'] = '1';
                    $loc = 'soulgaze_gallery.php' . (count($qs)?('?'.http_build_query($qs)):'');
                    header('Location: ' . $loc);
                    exit;
                } else {
                    $uploadError = 'Could not save file';
                }
            }
        }
    }
}

if (!$isEmbed) {
    include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
}

// Scan soundcache recursively for image files
$rootFs = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data/pictures/gallery/');
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
            if (!in_array($ext, ['jpg','jpeg','png','mp4'], true)) continue;
            $absPath = $fi->getPathname();
            $rel = substr($absPath, strlen($rootFs) + 1);
            $rel = str_replace('\\', '/', $rel);
            $url = $webRoot . '/data/pictures/gallery/' . $rel;
            $images[] = [
                'name' => $fi->getFilename(),
                'url' => $url,
                'rel' => $rel,
                'mtime' => $fi->getMTime(),
                'size' => $fi->getSize(),
                'type' =>$ext
            ];
        }
    } catch (Throwable $e) {
        // Render error box below
    }
}
// Sort newest first
usort($images, function($a, $b){ return $b['mtime'] <=> $a['mtime']; });
$visualByRel = [];
foreach (chimVisualContextList(1000) as $record) {
    $relativePath = str_replace('\\', '/', strval($record['image_path'] ?? ''));
    $marker = 'data/pictures/gallery/';
    $markerPosition = strpos($relativePath, $marker);
    if ($markerPosition !== false) {
        $relativePath = substr($relativePath, $markerPosition + strlen($marker));
    }
    if ($relativePath !== '') {
        $visualByRel[$relativePath] = $record;
    }
}
?>

<div class="container-fluid">
    <div class="gallery-header">
        <h1>🖼️ Soulgaze Gallery</h1>
        <div class="gallery-meta"><?php echo number_format(count($images)); ?> image(s)</div>
        <form method="post" enctype="multipart/form-data" class="upload-inline" style="display:flex; gap:6px; align-items:center; justify-content:center; flex-wrap:wrap; margin-top:6px;">
            <input type="hidden" name="embed" value="<?= $isEmbed? '1':'' ?>">
            <input type="hidden" name="picker" value="<?= $isPicker? '1':'' ?>">
            <input type="file" name="upload_image" accept="image/jpeg,image/png" style="background:#2a2a2a; color:#e9efff; border:1px solid #4a4a4a; border-radius:6px; padding:6px 8px;" />
            <button type="submit" class="btn">Upload</button>
            <?php if (!empty($uploadError)): ?><span style="color:#ff6b6b; font-size:12px;"><?= htmlspecialchars($uploadError) ?></span><?php endif; ?>
        </form>
    </div>
    <?php if (empty($images)): ?>
        <div class="empty">No images found. Make sure to use the Soulgaze hotkey ingame to take pictures!</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($images as $img): $n = $img['name']; $u = $img['url']; $visualRecord = $visualByRel[$img['rel']] ?? null; ?>
                <div class="card" data-url="<?php echo htmlspecialchars($u); ?>">
                    <?php if ($img['type']=="mp4") {?>
                    <video class="vthumb thumb" controls src="<?php echo htmlspecialchars($u); ?>" alt="<?php echo htmlspecialchars($n); ?>" loading="lazy" ></video>
                    <?php } else {?>
                    <img class="thumb" src="<?php echo htmlspecialchars($u); ?>" alt="<?php echo htmlspecialchars($n); ?>" loading="lazy" />
                    <?php } ?>
                    <div class="info">
                        <div class="name" title="<?php echo htmlspecialchars($img['rel']); ?>"><?php echo htmlspecialchars($n); ?></div>
                        <div class="actions">
                            <?php if (!$isPicker): ?>
                            <a class="btn" href="<?php echo htmlspecialchars($u); ?>" download>Download</a>
                            <?php else: ?>
                            <button class="btn pick" type="button" data-pick="<?php echo htmlspecialchars($u); ?>">Use</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($visualRecord): ?>
                    <form method="post" class="visual-editor">
                        <input type="hidden" name="visual_context_id" value="<?= intval($visualRecord['id']) ?>">
                        <textarea name="description" aria-label="Visual description"><?= htmlspecialchars(strval($visualRecord['description'] ?? ''), ENT_QUOTES) ?></textarea>
                        <div class="visual-editor-meta">
                            <span><?= htmlspecialchars(ucfirst(strval($visualRecord['subject_type'] ?? 'scene'))) ?><?= !empty($visualRecord['subject_name']) ? ': ' . htmlspecialchars(strval($visualRecord['subject_name'])) : '' ?></span>
                            <span><?= htmlspecialchars(strval($visualRecord['location_name'] ?? '')) ?></span>
                        </div>
                        <div class="visual-editor-options">
                            <input type="hidden" name="locked" value="0">
                            <label><input type="checkbox" name="locked" value="1" <?= !empty($visualRecord['locked']) && $visualRecord['locked'] !== 'f' ? 'checked' : '' ?>> Lock as Location Description</label>
                            <button class="btn" type="submit" name="visual_context_action" value="save">Save</button>
                            <button class="btn" type="submit" name="visual_context_action" value="delete" onclick="return confirm('Remove this visual context record? The image will remain in the gallery.');">Remove Record</button>
                        </div>
                    </form>
                    <?php endif; ?>
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
            <div class="dropdown">
                <button class="btn dropdown-toggle" id="lb_reimagine_toggle" type="button">Reimagine</button>
                <div class="dropdown-content" id="lb_reimagine_menu">
                    <a id="lb_reimage1" href="#" title="Will send image to GPTImage to create a reimagined version, needs an OpenAI API key">GPT Reimagine ($)</a>
                    <a id="lb_reimage2" href="#" title="Will send image to Replicate to create a reimagined version, needs a Replicate API key">Replicate Reimagine ($)</a>
                    <a id="lb_reimage3" href="#" title="Will send image to OpenRouter to create a reimagined version, needs a OpenRouter API key">OR-Gemini Reimagine ($)</a>
                    <a id="lb_reimage4" href="#" title="Will send image to Replicate to create a short video (7secs), needs a Replicate API key">Replicate Animate ($)</a>
                    <a id="lb_reimage5" href="#" title="Will send image to OpenRouter to create a reimagined version, needs a OpenRouter API key">OR FLUX-2 ($$)</a>
                    <a id="lb_reimage6" href="#" title="Will send image to Replicate to create a reimagined version, needs a Replicate API key">Replicate Kontext NSFW ($)</a>
                    <a id="lb_reimage7" href="#" title="Will send image to Replicate to create a reimagined version, needs a Replicate API key">Replicate Gemini($)</a>
                    <a id="lb_reimage8" href="#" title="Will send image to Replicate to create a reimagined version, needs a Replicate API key">Replicate KLEIN9B</a>
                    <a id="lb_reimage9" href="#" title="Will send image to Replicate to create a reimagined version, needs a Replicate API key">Replicate GrokImagine</a>
                </div>
            </div>
            <button id="lb_close" type="button">Close</button>
            <button id="lb_del" type="button">Delete</button>
        </div>
        <img id="lb_img" src="" alt="preview">
        <video id="lb_video" src="" alt="preview" controls></video>
    </div>
    
    
</div>

<script>
(function(){
  const lb = document.getElementById('lightbox');
  const lbImg = document.getElementById('lb_img');
  const lbVideo = document.getElementById('lb_video');
  const lbOpen = document.getElementById('lb_open');
  const lbDl = document.getElementById('lb_dl');
  const lbClose = document.getElementById('lb_close');
  const lb_reimage1 = document.getElementById('lb_reimage1');
  const lb_reimage2 = document.getElementById('lb_reimage2');
  const lb_reimage3 = document.getElementById('lb_reimage3');
  const lb_reimage4 = document.getElementById('lb_reimage4');
  const lb_reimage5 = document.getElementById('lb_reimage5');
  const lb_reimage6 = document.getElementById('lb_reimage6');
  const lb_reimage7 = document.getElementById('lb_reimage7');
  const lb_reimage8 = document.getElementById('lb_reimage8');
  const lb_reimage9 = document.getElementById('lb_reimage9');

  const lb_del = document.getElementById('lb_del');
  const lb_reimagine_toggle = document.getElementById('lb_reimagine_toggle');
  const lb_reimagine_menu = document.getElementById('lb_reimagine_menu');
  
  // Dropdown toggle
  if (lb_reimagine_toggle) {
    lb_reimagine_toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      lb_reimagine_menu.style.display = lb_reimagine_menu.style.display === 'block' ? 'none' : 'block';
    });
  }
  
  // Close dropdown when clicking outside
  document.addEventListener('click', function() {
    if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
  });
  
  function showProcessing() {
    processingMessage = document.createElement('div');
    processingMessage.textContent = 'Processing...';
    processingMessage.style.position = 'fixed';
    processingMessage.style.top = '50%';
    processingMessage.style.left = '50%';
    processingMessage.style.transform = 'translate(-50%, -50%)';
    processingMessage.style.backgroundColor = '#000';
    processingMessage.style.color = '#fff';
    processingMessage.style.padding = '10px 20px';
    processingMessage.style.borderRadius = '8px';
    processingMessage.style.zIndex = '10001';
    document.body.appendChild(processingMessage);
  }
  function hideProcessing() {
    processingMessage.innerHTML=''
  }
  var processingMessage;
function open(url) {
  if (!lb) return;

  const isVideo = url.endsWith('.mp4'); // You can extend this to support more video types if needed

  if (isVideo) {
    lbImg.src = '';        // Clear image source
    lbVideo.src = url;     // Set video source
    lbVideo.style.display = 'inline-block';
    lbImg.style.display = 'none';
  } else {
    lbImg.src = url;       // Set image source
    lbVideo.src = '';      // Clear video source
    lbVideo.style.display = 'none';
    lbImg.style.display = 'block';
  }

  lbOpen.href = url;
  lbDl.href = url;
  lb.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function close(){ if (!lb) return; lb.style.display='none'; lbImg.removeAttribute('src'); document.body.style.overflow='auto'; }
  document.addEventListener('click', function(e){
    const card = e.target && e.target.closest && e.target.closest('.card');
    if (!card) return;
    if (e.target && e.target.closest && e.target.closest('.visual-editor')) return;
    const useBtn = e.target && e.target.classList && e.target.classList.contains('pick');
    const img = card.querySelector('.thumb');
    if (!img) return;
    if (useBtn) {
      // Picker mode: send selection to parent
      try { window.parent.postMessage({ type:'gallery_selected', url: e.target.getAttribute('data-pick') || img.src }, '*'); } catch(_e){}
      return;
    }
    if (e.target.tagName === 'A') return;
    e.preventDefault();
    open(img.src);
  });
  if (lbClose) lbClose.addEventListener('click', close);
  if (lb) lb.addEventListener('click', function(e){
     if (e.target === lb) close(); 
     if (e.target === lb_reimage1) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint');
        fetch('cmd/gallery_tool_convert_style_gpt.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status=="success") {
                console.log('Success:', data);
                close()
                window.location.reload();
            } else {
                alert(data.message)
                close()
            }
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage2) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint','Convert image-0 to a semi-realistic style, like a high-quality CGI render. Reimagine the whole picture, while preserving  details like tattos, skin color, eye color, hair style, hair color, clothing, make-up , body proportions and environment.');
        fetch('cmd/gallery_tool_convert_style_replicate.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            
            close()
            window.location.reload();
            
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage3) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint');
        fetch('cmd/gallery_tool_convert_style_or.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage4) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Movement Hint');

        fetch('cmd/gallery_tool_animate_replicate.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage5) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint');
        fetch('cmd/gallery_tool_convert_style_or_flux2.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage6) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint','Convert image-0 to a semi-realistic style, like a high-quality CGI render. Reimagine the whole picture, while preserving  details like tattos, skin color, eye color, hair style, hair color, clothing, make-up , body proportions and environment.');
        fetch('cmd/gallery_tool_convert_style_replicate_nsfw.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage7) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint','Convert image-0 to a semi-realistic style, like a high-quality CGI render. Reimagine the whole picture, while preserving  details like tattos, skin color, eye color, hair style, hair color, clothing, make-up , body proportions and environment.');
        fetch('cmd/gallery_tool_convert_style_replicate_gemini.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     } else if (e.target === lb_reimage8) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint','Skin color/race ... ');
        fetch('cmd/gallery_tool_convert_style_replicate_klein.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     }  else if (e.target === lb_reimage9) {
        showProcessing();
        if (lb_reimagine_menu) lb_reimagine_menu.style.display = 'none';
        uHint=prompt('Hint','Skin color/race ... ');
        fetch('cmd/gallery_tool_convert_style_replicate_grok.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json'
            },
            body: JSON.stringify({ source: lbImg.src,userhint:uHint })

        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            close()
            // Reload the current document to reflect changes
            window.location.reload();
            // Handle the response data here
        })
        .catch((error) => {
            console.error('Error:', error);
        });
     }  else if (e.target === lb_del) {
        if (confirm('Sure thing?. No recycle bin here')) {
            showProcessing();

            fetch('cmd/gallery_delete.php', {
                method: 'POST',
                headers: {
                'Content-Type': 'application/json'
                },
                body: JSON.stringify({ source: lbImg.src,sourceVid: lbVideo?lbVideo.src:null })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                close()
                // Reload the current document to reflect changes
                window.location.reload();
                // Handle the response data here
            })
            .catch((error) => {
                console.error('Error:', error);
            });
        }
     }
    });

    
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') close(); });

  document.querySelectorAll('.vthumb').forEach(video => {
    video.addEventListener('mouseenter', () => {
      video.play().catch(e => {
        console.warn('Autoplay prevented:', e);
      });
    });

    video.addEventListener('mouseleave', () => {
      video.pause();
      video.currentTime = 0; // Optional: reset to start
    });
  });

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


