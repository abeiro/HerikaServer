<!-- Javascript stuff to bring up a json editor for the metadata/override -->
<?php
// Build a visual editor for common metadata keys using conf schema
$schemaPath = __DIR__ . "/../../../conf/conf_schema.json";
$confSchema = [];
if (file_exists($schemaPath)) {
    $confSchema = json_decode(file_get_contents($schemaPath), true);
}

// Local overrides for visual metadata schema (decouple UI copy from conf_schema)
// These entries will be used instead of conf_schema when rendering visual controls
$localSchemaOverrides = [
    'RECHAT_H' => [
        'type' => 'integer',
        'description' => "Rechat Rounds. Higher values will increase the amount of times AI NPC's will go back-and-forth during a conversation. 1 = 1 Round | 2 = 2 Rounds | 3 = 3 Rounds etc",
    ],
    'RECHAT_P' => [
        'type' => 'integer',
        'description' => 'Rechat Probability. Chance that an AI NPC will continue an ongoing conversation. 0 = Never | 50 = 50% | 100 = Always',
    ],
    'CORE_LANG' => [
        'type' => 'select',
        'description' => 'Custom Language. The lang folder is in the CHIM Server. Leave it blank for English.',
    ],
    'MINIME_T5' => [
        'type' => 'boolean',
        'description' => "Enable Minime-T5 LLM. Helps dumber LLM's be more accurate with action and memory functions. Must be installed in the CHIM Launcher. Only works for English!",
    ],
    'AUTO_DIARY' => [
        'type' => 'boolean',
        'description' => 'Automatically create diary entries for all current followers when sleeping. Wait events are controlled by AUTO_DIARY_WAIT setting.',
    ],
    'BORED_EVENT' => [
        'type' => 'integer',
        'description' => 'Bored Event Probability. Chance of an AI NPC starting a random conversation every couple of minutes.0 = Never | 50 = 50% | 100 = Always',
    ],
    'DIARY_PROMPT' => [
        'type' => 'longstring',
        'description' => 'Default profile only! Instructions for generating diary entries. You can adjust max tokens by changing MAX_TOKENS_MEMORY for the DIARY connector you are using.',
    ],
    'OGHMA_AMOUNT' => [
        'type' => 'select',
        'values' => ['1','2','3'],
        'description' => 'Number of Oghma keywords to extract from each response. More keyword extraction will mean longer response times.',
    ],
    'LANG_LLM_XTTS' => [
        'type' => 'boolean',
        'description' => 'XTTS Only! Will offer a language field to LLM, and will try match to XTTSv2 language.',
    ],
    'QUEST_COMMENT' => [
        'type' => 'boolean',
        'description' => 'Will trigger AI (NPCs and Narrator) to talk about new objectives in your current active quest. Will trigger a lot of events on a new character, so leave disabled until you complete the tutorial!',
    ],
    'DIARY_COOLDOWN' => [
        'type' => 'integer',
        'description' => 'Cooldown period in seconds between diary entries to prevent spam. Each NPC has their own independent cooldown timer.',
    ],
    'OGHMA_INFINIUM' => [
        'type' => 'boolean',
        'description' => "Needs Minime-T5 enabled and running. Tamriel lore information will be added to the prompt, enhancing their understanding on specific topics.",
    ],
    'AUTO_DIARY_WAIT' => [
        'type' => 'boolean',
        'description' => 'When AUTO_DIARY is enabled, this controls whether diary entries are created during wait events. If false, auto diary will only trigger on sleep events.',
    ],
    'CONTEXT_HISTORY' => [
        'type' => 'integer',
        'description' => 'Amount of context history (dialogue and events) that will be sent to LLM. Improves short term memory.Higher Context = more tokens used and slower response time.We recommend you do not go over 100',
    ],
    'MAX_WORDS_LIMIT' => [
        'type' => 'integer',
        'description' => "Enforce a word limit for AI's responses. Leave as 0 to have no limit.",
    ],
    'HERIKA_ANIMATIONS' => [
        'type' => 'boolean',
        'description' => 'Will issue animations for the NPC to play',
    ],
    'QUEST_COMMENT_CHANCE' => [
        'type' => 'select',
        'values' => ['10%','25%','50%','75%','100%'],
        'description' => 'Chance that an AI Quest Comment will happen every time a quest updates.',
    ],
    'RECHAT_ALLOW_ACTIONS' => [
        'type' => 'boolean',
        'description' => 'Allow AI NPCs to trigger actions between eachother during Rechat. This can cause some chaos...',
    ],
    'CONTEXT_HISTORY_DIARY' => [
        'type' => 'integer',
        'description' => 'Amount of context history (dialogue and events) that will be sent to LLM specifically for diary entries. If set to 0, will use the regular CONTEXT_HISTORY value instead.',
    ],
    'BORED_EVENT_SERVERSIDE' => [
        'type' => 'boolean',
        'description' => 'Smart Bored Events. Will use the director to generate dynamic bored event topics. It is slower but topics will improve the quality of bored event topics.',
    ],
    'ENFORCE_ACTIONS_PROMPT' => [
        'type' => 'boolean',
        'description' => 'Eencourage AI NPCs to use actions more often.',
    ],
    'REMOVE_ASTERISKS_FROM_OUTPUT' => [
        'type' => 'boolean',
        'description' => 'Remove text between ** when the AI responds, such as *couch*, *smiles*, "claps, etc',
    ],
    'CONTEXT_HISTORY_DYNAMIC_PROFILE' => [
        'type' => 'integer',
        'description' => 'Amount of context history (dialogue and events) that will be sent to LLM specifically for dynamic profile updates. If set to 0, will use the regular CONTEXT_HISTORY value instead.',
    ],
];

// Visual keys to expose (can be expanded easily)
$visualKeys = [
  "RECHAT_H","RECHAT_P","CORE_LANG","MINIME_T5","AUTO_DIARY","BORED_EVENT",
  "DIARY_PROMPT","OGHMA_AMOUNT","LANG_LLM_XTTS","QUEST_COMMENT","DIARY_COOLDOWN",
  "OGHMA_INFINIUM","AUTO_DIARY_WAIT","CONTEXT_HISTORY","MAX_WORDS_LIMIT","HERIKA_ANIMATIONS",
  "QUEST_COMMENT_CHANCE","RECHAT_ALLOW_ACTIONS","CONTEXT_HISTORY_DIARY","BORED_EVENT_SERVERSIDE","ENFORCE_ACTIONS_PROMPT",
  "REMOVE_ASTERISKS_FROM_OUTPUT","CONTEXT_HISTORY_DYNAMIC_PROFILE"
];

// Organize visual keys into categories for display
$visualGroups = [
  'Core' => ["CORE_LANG","ENFORCE_ACTIONS_PROMPT","REMOVE_ASTERISKS_FROM_OUTPUT","MAX_WORDS_LIMIT"],
  'Rechat' => ["RECHAT_H","RECHAT_P","RECHAT_ALLOW_ACTIONS"],
  'Diary' => ["AUTO_DIARY","DIARY_PROMPT","DIARY_COOLDOWN","AUTO_DIARY_WAIT"],
  'Oghma' => ["OGHMA_INFINIUM","OGHMA_AMOUNT","MINIME_T5"],
  'Context' => ["CONTEXT_HISTORY","CONTEXT_HISTORY_DIARY","CONTEXT_HISTORY_DYNAMIC_PROFILE"],
  'Quest' => ["QUEST_COMMENT","QUEST_COMMENT_CHANCE"],
  'Behavior' => ["BORED_EVENT","BORED_EVENT_SERVERSIDE","HERIKA_ANIMATIONS"],
  'Language/Voice' => ["LANG_LLM_XTTS"],
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
        $html .= "<div>";
        $html .= "<input type=\"hidden\" name=\"meta_vis[$key]\" value=\"false\">";
        $html .= "<label><input class=\"meta-toggle\" type=\"checkbox\" name=\"meta_vis[$key]\" value=\"true\"" . ($isTrue ? ' checked' : '') . "> <span class=\"toggle-text\">" . ($isTrue ? 'On' : 'Off') . "</span></label>";
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
        <?php
        // Track which keys were rendered via groups
        $rendered = [];
        foreach ($visualGroups as $title => $keys) {
            // Filter to only keys present in visualKeys (safety)
            $keysInVisual = array_values(array_intersect($keys, $visualKeys));
            if (count($keysInVisual) === 0) continue;
            echo '<div style="margin:10px 0 6px; font-weight:800; color:#e9efff; border-bottom:1px solid rgba(138,155,182,0.35); padding-bottom:4px;">'.htmlspecialchars($title).'</div>';
            echo '<div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">';
            foreach ($keysInVisual as $k) {
                $schemaEntry = $localSchemaOverrides[$k] ?? ($confSchema[$k] ?? []);
                echo renderMetaInput($k, $schemaEntry, $metadataCurrent[$k] ?? '');
                $rendered[$k] = true;
            }
            echo '</div>';
        }
        // Render any remaining visual keys not in groups under "Other"
        $remaining = array_values(array_diff($visualKeys, array_keys($rendered)));
        if (count($remaining) > 0) {
            echo '<div style="margin:10px 0 6px; font-weight:800; color:#e9efff; border-bottom:1px solid rgba(138,155,182,0.35); padding-bottom:4px;">Other</div>';
            echo '<div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">';
            foreach ($remaining as $k) {
                $schemaEntry = $localSchemaOverrides[$k] ?? ($confSchema[$k] ?? []);
                echo renderMetaInput($k, $schemaEntry, $metadataCurrent[$k] ?? '');
            }
            echo '</div>';
        }
        ?>
    </div>
    <script>
    (function(){
        // Sync On/Off labels for metadata boolean checkboxes
        document.querySelectorAll('.meta-toggle').forEach(cb => {
            const label = cb.closest('label');
            const span = label ? label.querySelector('.toggle-text') : null;
            function sync(){ if (span) span.textContent = cb.checked ? 'On' : 'Off'; }
            cb.addEventListener('change', sync);
        });
    })();
    </script>
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
    const SHOW_VISUAL = <?= $showVisual ? 'true' : 'false' ?>;
    const VISUAL_KEYS = <?= json_encode($visualKeys, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
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

    debugger;
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