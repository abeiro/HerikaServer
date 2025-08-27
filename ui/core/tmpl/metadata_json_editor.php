<!-- Javascript stuff to bring up a json editor for the metadata/override -->
<?php
// Build a visual editor for common metadata keys using conf schema
$schemaPath = __DIR__ . "/../../../conf/conf_schema.json";
$confSchema = [];
if (file_exists($schemaPath)) {
    $confSchema = json_decode(file_get_contents($schemaPath), true);
}

// Visual keys to expose (can be expanded easily)
$visualKeys = [
  "RECHAT_H","RECHAT_P","CORE_LANG","MINIME_T5","AUTO_DIARY","BORED_EVENT","CURRENT_TASK",
  "DIARY_PROMPT","OGHMA_AMOUNT","ALIVE_MESSAGE","LANG_LLM_XTTS","QUEST_COMMENT","DIARY_COOLDOWN",
  "OGHMA_INFINIUM","TIME_AWARENESS","AUTO_DIARY_WAIT","CONTEXT_HISTORY","MAX_WORDS_LIMIT","HERIKA_ANIMATIONS",
  "QUEST_COMMENT_CHANCE","RECHAT_ALLOW_ACTIONS","CONTEXT_HISTORY_DIARY","BORED_EVENT_SERVERSIDE","ENFORCE_ACTIONS_PROMPT",
  "REMOVE_ASTERISKS_FROM_OUTPUT","CONTEXT_HISTORY_DYNAMIC_PROFILE"
];

$metadataCurrent = [];
if (isset($editItem["metadata"]) && !empty($editItem["metadata"])) {
    $tmp = json_decode($editItem["metadata"], true);
    if (is_array($tmp)) $metadataCurrent = $tmp;
}

// Show visual controls only on core_profiles page
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$showVisual = ($currentScript === 'core_profiles.php');

$visualKeysLookup = array_flip($visualKeys);
$nonVisualCurrent = $showVisual ? array_diff_key($metadataCurrent, $visualKeysLookup) : $metadataCurrent;

function renderMetaInput($key, $schema, $value) {
    $type = $schema["type"] ?? 'string';
    $desc = htmlspecialchars($schema["description"] ?? '');
    $values = $schema["values"] ?? [];
    $html = "<div class=\"conf-item\" style=\"max-width:900px;\">";
    $html .= "<label style=\"color: rgb(242,124,17)\">" . htmlspecialchars($key) . "</label>";
    if ($desc) $html .= "<span>" . $desc . "</span>";
    if ($type === 'boolean') {
        $isTrue = ($value === true || $value === 'true' || $value === 1 || $value === '1');
        $idTrue = 'meta_'.$key.'_true';
        $idFalse = 'meta_'.$key.'_false';
        $html .= "<div>";
        $html .= "<input type=\"radio\" id=\"$idTrue\" name=\"meta_vis[$key]\" value=\"true\"" . ($isTrue ? ' checked' : '') . "> <label for=\"$idTrue\">True</label> ";
        $html .= "<input type=\"radio\" id=\"$idFalse\" name=\"meta_vis[$key]\" value=\"false\"" . (!$isTrue ? ' checked' : '') . "> <label for=\"$idFalse\">False</label>";
        $html .= "</div>";
    } elseif ($type === 'select' && is_array($values) && count($values)>0) {
        $html .= "<select name=\"meta_vis[$key]\">";
        $html .= "<option value=\"\">-- select --</option>";
        foreach ($values as $opt) {
            $sel = ((string)$value === (string)$opt) ? 'selected' : '';
            $html .= "<option value=\"" . htmlspecialchars($opt) . "\" $sel>" . htmlspecialchars($opt) . "</option>";
        }
        $html .= "</select>";
    } elseif ($type === 'select' && $key === 'CORE_LANG') {
        // Fallback values for CORE_LANG when not provided in schema
        $opts = ['en','de','es','fr','jp'];
        $html .= "<select name=\"meta_vis[$key]\">";
        $html .= "<option value=\"\">-- select --</option>";
        foreach ($opts as $opt) {
            $sel = ((string)$value === (string)$opt) ? 'selected' : '';
            $html .= "<option value=\"$opt\" $sel>" . htmlspecialchars($opt) . "</option>";
        }
        $html .= "</select>";
    } else {
        // integer/number/longstring/string with optional sliders for known ranges
        $ph = ($type==='integer' || $type==='number') ? 'Enter number' : 'Enter value';
        $val = is_bool($value) ? ($value? 'true':'false') : (string)$value;

        // Known ranges from conf_wizard
        $ranges = [
            'RECHAT_P' => ['min'=>0,'max'=>100,'step'=>1],
            'BORED_EVENT' => ['min'=>0,'max'=>100,'step'=>1],
            'CONTEXT_HISTORY' => ['min'=>0,'max'=>200,'step'=>1],
            'RECHAT_H' => ['min'=>1,'max'=>10,'step'=>1],
            'DIARY_COOLDOWN' => ['min'=>10,'max'=>1200,'step'=>1],
            'CONTEXT_HISTORY_DIARY' => ['min'=>0,'max'=>400,'step'=>1],
            'CONTEXT_HISTORY_DYNAMIC_PROFILE' => ['min'=>0,'max'=>400,'step'=>1]
        ];

        if (($type==='integer' || $type==='number') && isset($ranges[$key])) {
            $rid = 'meta_range_'.$key;
            $nid = 'meta_num_'.$key;
            $min = $ranges[$key]['min'];
            $max = $ranges[$key]['max'];
            $step = $ranges[$key]['step'];
            $safeVal = htmlspecialchars($val);
            $html .= "<div>";
            $html .= "<input type=\"range\" id=\"$rid\" min=\"$min\" max=\"$max\" step=\"$step\" value=\"$safeVal\" oninput=\"document.getElementById('$nid').value=this.value\">";
            $html .= "<div style=\"margin-top:6px;\"><input type=\"number\" id=\"$nid\" name=\"meta_vis[$key]\" min=\"$min\" max=\"$max\" step=\"$step\" value=\"$safeVal\" style=\"width:80px;\" oninput=\"metaClamp('$rid','$nid',$min,$max)\"></div>";
            $html .= "</div>";
        } else if ($type === 'longstring') {
            $html .= "<textarea name=\"meta_vis[$key]\" rows=\"4\" placeholder=\"" . htmlspecialchars($ph) . "\">" . htmlspecialchars($val) . "</textarea>";
        } else if ($type==='integer' || $type==='number') {
            $html .= "<input type=\"number\" name=\"meta_vis[$key]\" value=\"" . htmlspecialchars($val) . "\" placeholder=\"" . htmlspecialchars($ph) . "\">";
        } else {
            $html .= "<input type=\"text\" name=\"meta_vis[$key]\" value=\"" . htmlspecialchars($val) . "\" placeholder=\"" . htmlspecialchars($ph) . "\">";
        }
    }
    $html .= "</div>";
    return $html;
}
?>

<?php if ($showVisual): ?>
    <div class="content-section" style="margin-bottom:10px;">
        <h3 style="margin-top:0;">Profile Settings</h3>
        <p style="margin-bottom:10px; color:#bbb">These common options are saved into Metadata automatically. Use the JSON editor below for advanced/custom edits.</p>
        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
            <?php
            foreach ($visualKeys as $k) {
                $schemaEntry = $confSchema[$k] ?? [];
                echo renderMetaInput($k, $schemaEntry, $metadataCurrent[$k] ?? '');
            }
            ?>
        </div>
    </div>
<?php endif; ?>
 
<script>
let jsonEditor ;
let jsonEditor2 ;

// Clamp helper for sliders
function metaClamp(rangeId, numberId, min, max){
    const r = document.getElementById(rangeId)
    const n = document.getElementById(numberId)
    if (!r || !n) return
    let v = parseFloat(n.value)
    if (isNaN(v)) v = min
    if (v < min) v = min
    if (v > max) v = max
    n.value = v
    r.value = v
}

function consolidation() {
    const SHOW_VISUAL = <?= $showVisual ? 'true' : 'false' ?>
    const VISUAL_KEYS = <?= json_encode($visualKeys, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>
    const content = jsonEditor.get()
    let base = {}
    try {
        base = content.json || {}
    } catch (idontcare) { base = {} }

    // Remove any visual keys from JSON editor content to avoid duplication (only on core_profiles page)
    if (SHOW_VISUAL) {
        VISUAL_KEYS.forEach(k => { if (k in base) delete base[k] })
    }

    // Collect visual fields (explicitly iterate over known keys to capture false for checkboxes)
    const form = document.getElementById('core_profile_form') || document.forms[0]
    const visual = {}
    if (SHOW_VISUAL) {
        VISUAL_KEYS.forEach(key => {
            let inp = form.querySelector(`[name="meta_vis[${key}]"]`)
            if (!inp) return
            const tag = (inp.tagName || '').toLowerCase()
            if (tag === 'input' && (inp.type === 'hidden' || inp.type === 'text' || inp.type === 'number' || inp.type === 'radio')){
                let v
                if (inp.type === 'radio') {
                    const chk = form.querySelector(`[name="meta_vis[${key}]"]:checked`)
                    v = chk ? chk.value : ''
                } else {
                    v = inp.value
                }
                if (v !== '') visual[key] = (v === 'true') ? true : (v === 'false' ? false : v)
            } else if (tag === 'textarea' || tag === 'select') {
                const v = inp.value
                if (v !== '') visual[key] = v
            }
        })
    }

    // Build ordered object: base first (non-visual), then visual keys appended
    const merged = {}
    Object.keys(base).forEach(k => { merged[k] = base[k] })
    if (SHOW_VISUAL) {
        VISUAL_KEYS.forEach(k => { if (k in visual) merged[k] = visual[k] })
    }

    try {
        form.metadata.value = JSON.stringify(merged, null, 0)
    } catch (idontcare) {}
    
    if (form.metadata.value=='')  {
        return confirm("Metadata is empty. You sure?");
    }

    
    if (form.extended_data!=undefined) {
        const content2 = jsonEditor2.get()

        try {
            form.extended_data.value=JSON.stringify(content2.json, null, 0)
        } catch (idontcare) {}
        
        if (form.extended_data.value=='')  {
            return confirm("Extended data is empty. You sure?");
        }
    }

    return true;
}


</script>

<script type="module">
    import { createJSONEditor } from 'https://cdn.jsdelivr.net/npm/vanilla-jsoneditor/standalone.js'
    document.addEventListener("DOMContentLoaded", function() {
        let content = {
            text: undefined,
            json: <?php echo json_encode($nonVisualCurrent ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>
            
        }


        jsonEditor = createJSONEditor({
        target: document.getElementById('metadata'),
        props: {
            content,
            onChange: (updatedContent, previousContent, { contentErrors, patchResult }) => {
            // content is an object { json: JSONData } | { text: string }
            console.log('onChange', { updatedContent, previousContent, contentErrors, patchResult })
            content = updatedContent
            }
        }
        })
        console.log("javascript init done");

    });


    document.addEventListener("DOMContentLoaded", function() {
        let content = {
            text: undefined,
            json: <?php echo (!empty($editItem["extended_data"])?$editItem["extended_data"]:"{}") ?>
            
        }

        if (document.getElementById('extended_data')) {
            jsonEditor2 = createJSONEditor({
            target: document.getElementById('extended_data'),
            props: {
                content,
                onChange: (updatedContent, previousContent, { contentErrors, patchResult }) => {
                // content is an object { json: JSONData } | { text: string }
                console.log('onChange', { updatedContent, previousContent, contentErrors, patchResult })
                content = updatedContent
                }
            }
            })
        }
        console.log("javascript init done");

    });

</script>