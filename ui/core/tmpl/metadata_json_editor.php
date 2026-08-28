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
        'description' => "Rechat Responses. Higher values will increase the amount of times AI NPC's will go back-and-forth during a conversation. 1 = 1 Response | 2 = 2 Responses | 3 = 3 Responses etc",
    ],
    'RECHAT_P' => [
        'type' => 'integer',
        'description' => 'Rechat Probability. Chance that an AI NPC will continue an ongoing conversation. 0 = Never | 50 = 50% | 100 = Always',
    ],
    'CORE_LANG' => [
        'type' => 'select',
        'description' => 'Custom Language. The lang folder is in the CHIM Server. Leave it blank for English.',
    ],
    'BORED_EVENT' => [
        'type' => 'integer',
        'description' => 'Bored Event Probability. Chance of an AI NPC starting a random conversation every couple of minutes. 0 = Never | 50 = 50% | 100 = Always. Note: Bored Event Chance can be configured ingame in the CHIM MCM menu',
    ],
    'DIARY_PROMPT' => [
        'type' => 'longstring',
        'description' => 'Instructions for generating diary entries for this profile. You can adjust max tokens by changing MAX_TOKENS_MEMORY for the DIARY connector you are using.',
    ],
    'LANG_LLM_XTTS' => [
        'type' => 'boolean',
        'description' => 'XTTS/Chatterbox Only! Will offer a language field to LLM, and will try match to XTTSv2 language.',
    ],
    'QUEST_COMMENT' => [
        'type' => 'boolean',
        'description' => 'Will trigger AI (NPCs and Narrator) to talk about new objectives in your current active quest. Will trigger a lot of events on a new character, so leave disabled until you complete the tutorial!',
    ],
    'DIARY_COOLDOWN' => [
        'type' => 'integer',
        'description' => 'Cooldown period in seconds between diary entries to prevent spam. Each NPC has their own independent cooldown timer.',
    ],
    'COMBAT_BARK_COOLDOWN' => [
        'type' => 'integer',
        'description' => 'Cooldown period in seconds between combat barks to prevent spam during combat. This cooldown is global across all NPCs in the party.',
    ],
    'CONTEXT_HISTORY' => [
        'type' => 'integer',
        'description' => 'Amount of context history (dialogue and events) that will be sent to LLM. Improves short term memory.Higher Context = more tokens used and slower response time.We recommend you do not go over 100',
    ],
    'MAX_WORDS_LIMIT' => [
        'type' => 'integer',
        'description' => "Attempt to enforce a word limit for AI's responses. Leave as 0 to have no limit.",
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
    'CONTEXT_HISTORY_DYNAMIC_PROFILE' => [
        'type' => 'integer',
        'description' => 'Amount of context history (dialogue and events) that will be sent to LLM specifically for dynamic profile updates. If set to 0, will use the regular CONTEXT_HISTORY value instead.',
    ],
];

// Visual keys to expose (can be expanded easily)
$visualKeys = isset($profileSyncableMetadataKeys) && is_array($profileSyncableMetadataKeys)
    ? $profileSyncableMetadataKeys
    : [
        "RECHAT_H","RECHAT_P","CORE_LANG","BORED_EVENT",
        "DIARY_PROMPT","LANG_LLM_XTTS","QUEST_COMMENT","DIARY_COOLDOWN","COMBAT_BARK_COOLDOWN",
        "CONTEXT_HISTORY","MAX_WORDS_LIMIT",
        "QUEST_COMMENT_CHANCE","RECHAT_ALLOW_ACTIONS","CONTEXT_HISTORY_DIARY",
        "CONTEXT_HISTORY_DYNAMIC_PROFILE"
    ];

// Organize visual keys into categories for display
$visualGroups = [
  'Language' => ["CORE_LANG","LANG_LLM_XTTS","MAX_WORDS_LIMIT"],
  'Rechat' => ["RECHAT_H","RECHAT_P","RECHAT_ALLOW_ACTIONS"],
  'Bored Event' => ["BORED_EVENT"],
  'Context' => ["CONTEXT_HISTORY","CONTEXT_HISTORY_DIARY","CONTEXT_HISTORY_DYNAMIC_PROFILE"],
  'Diary' => ["DIARY_PROMPT","DIARY_COOLDOWN"],
  'Combat' => ["COMBAT_BARK_COOLDOWN"],
  'Quest' => ["QUEST_COMMENT","QUEST_COMMENT_CHANCE"],
];

// Pair sections into aligned rows so the editor stays easy to scan.
$visualRows = [
  ['Language', 'Rechat'],
  ['Bored Event', 'Context'],
  ['Diary', 'Combat'],
  ['Quest'],
];

// Pretty label similar to global_settings General tab
function meta_pretty_label(string $name): string {
    // Custom label overrides
    $customLabels = [
        'RECHAT_H' => 'Rechat Response Rounds',
        'RECHAT_P' => 'Rechat Probaility',
        'BORED_EVENT' => 'Bored Event Chance',
        'CONTEXT_HISTORY' => 'Context History Event Count',
        'CONTEXT_HISTORY_DIARY' => 'Context History Diary Event Count',
        'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 'Context History Dynamic Profile Event Count'
    ];
    
    if (isset($customLabels[$name])) {
        return $customLabels[$name];
    }
    
    $p = str_replace('_', ' ', strtolower(trim($name)));
    return ucwords($p);
}

// Simple icon mapping for common groups/keys
function meta_icon_for(string $key): string {
    $u = strtoupper($key);
    if (strpos($u, 'DIARY') !== false) return '&#x1F4D9;';
    if (strpos($u, 'COMBAT_BARK') !== false) return '&#x2694;&#xFE0F;';
    if (strpos($u, 'RECHAT') === 0) return '&#x1F501;';
    if (strpos($u, 'CONTEXT_HISTORY') === 0) return '&#x1F9E0;';
    if (strpos($u, 'OGHMA') === 0) return '&#x1F9FE;';
    if (strpos($u, 'QUEST_') === 0) return '&#x1F9ED;';
    if (strpos($u, 'LANG_') === 0 || strpos($u, 'CORE_LANG') === 0) return '&#x1F310;';
    if ($u === 'HERIKA_ANIMATIONS') return '&#x1F39E;&#xFE0F;';
    return '&#x2699;&#xFE0F;';
}

$metadataCurrent = [];
if (isset($editItem["metadata"]) && !empty($editItem["metadata"])) {
    $tmp = json_decode($editItem["metadata"], true);
    if (is_array($tmp)) $metadataCurrent = $tmp;
}
// Deprecated: MiniMe-T5 is auto-detected at runtime.
unset($metadataCurrent['MINIME_T5']);
// Enforce actions prompt is always enabled in code and no longer user-editable.
unset($metadataCurrent['ENFORCE_ACTIONS_PROMPT']);

// Show visual controls only on core_profiles page
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$showVisual = ($currentScript === 'core_profiles.php');

$visualKeysLookup = array_flip($visualKeys);
$nonVisualCurrent = $showVisual ? array_diff_key($metadataCurrent, $visualKeysLookup) : $metadataCurrent;

function renderMetaInput($key, $schema, $value, $controlOnly = false) {
    $type = $schema["type"] ?? 'string';
    $desc = htmlspecialchars($schema["description"] ?? '');
    $values = $schema["values"] ?? [];
    $html = '';
    if ($type === 'boolean') {
        $isTrue = ($value === true || $value === 'true' || $value === 1 || $value === '1');
        $html .= "<input type=\"hidden\" name=\"meta_vis[$key]\" value=\"false\">";
        $html .= "<input class=\"meta-toggle\" type=\"checkbox\" name=\"meta_vis[$key]\" value=\"true\"" . ($isTrue ? ' checked' : '') . ">";
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
            'COMBAT_BARK_COOLDOWN' => ['min'=>10,'max'=>600,'step'=>1],
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
            $html .= "<div class=\"range-pair\">";
            $html .= "<input type=\"range\" id=\"$rid\" min=\"$min\" max=\"$max\" step=\"$step\" value=\"$safeVal\" oninput=\"document.getElementById('$nid').value=this.value\">";
            $html .= "<input type=\"number\" id=\"$nid\" name=\"meta_vis[$key]\" min=\"$min\" max=\"$max\" step=\"$step\" value=\"$safeVal\" oninput=\"metaClamp('$rid','$nid',$min,$max)\">";
            $html .= "</div>";
        } else if ($type === 'longstring') {
            $html .= "<textarea name=\"meta_vis[$key]\" rows=\"4\" placeholder=\"" . htmlspecialchars($ph) . "\">" . htmlspecialchars($val) . "</textarea>";
        } else if ($type==='integer' || $type==='number') {
            $html .= "<input type=\"number\" name=\"meta_vis[$key]\" value=\"" . htmlspecialchars($val) . "\" placeholder=\"" . htmlspecialchars($ph) . "\">";
        } else {
            $html .= "<input type=\"text\" name=\"meta_vis[$key]\" value=\"" . htmlspecialchars($val) . "\" placeholder=\"" . htmlspecialchars($ph) . "\">";
        }
    }
    return $html;
}

function renderMetaSettingRow(string $key, array $schemaEntry, $value): string {
    $type = $schemaEntry['type'] ?? 'string';
    $desc = htmlspecialchars($schemaEntry['description'] ?? '');
    $label = htmlspecialchars(meta_pretty_label($key));
    $icon = meta_icon_for($key);
    $safeKey = htmlspecialchars($key);
    $html = '<div class="setting-row">';
    $html .= '<div>';
    $html .= '<div class="setting-key"><span class="setting-icon">'.$icon.'</span><span>'.$label.'</span>';
    $html .= '<button type="button" class="profile-setting-sync-btn" data-setting-key="'.$safeKey.'" data-setting-label="'.$label.'" title="Copy this setting to every profile">Copy to all</button></div>';
    if (!empty($desc)) {
        $html .= '<div class="setting-desc">'.$desc.'</div>';
    }
    $html .= '</div>';
    $html .= '<div class="setting-control">';
    if ($type === 'boolean') {
        $isTrue = ($value === true || $value === 'true' || $value === 1 || $value === '1');
        $html .= '<input type="hidden" name="meta_vis['.$safeKey.']" value="false">';
        $html .= '<label class="meta-toggle-inline">';
        $html .= '<input class="meta-toggle" type="checkbox" value="true" name="meta_vis['.$safeKey.']"'.($isTrue ? ' checked' : '').'>';
        $html .= '<span class="toggle-text">'.($isTrue ? 'On' : 'Off').'</span>';
        $html .= '</label>';
    } else {
        $html .= renderMetaInput($key, $schemaEntry, $value, true);
    }
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
?>

<?php if ($showVisual): ?>
    <div class="content-section" style="margin-bottom:10px;">
        <?php
        $rendered = [];

        $renderVisualGroup = function(string $title, array $keys) use (&$rendered, $visualKeys, $metadataCurrent, $localSchemaOverrides, $confSchema): void {
            $keysInVisual = array_values(array_intersect($keys, $visualKeys));
            if (count($keysInVisual) === 0) return;

            echo '<section class="profile-settings-group">';
            echo '<h2 class="profile-settings-heading">'.htmlspecialchars($title).'</h2>';
            
            // Add Rechat Calculator before Rechat section
            if ($title === 'Rechat') {
                $rechatH = $metadataCurrent['RECHAT_H'] ?? 2;
                $rechatP = $metadataCurrent['RECHAT_P'] ?? 50;
                echo '<div class="rechat-calculator">';
                echo   '<div class="rechat-calculator-title"><span>&#x1F501;</span><span>Rechat Response Calculator</span></div>';
                echo   '<div id="rechat-calc-output" style="font-size: 13px; line-height: 1.6;"></div>';
                echo '</div>';
            }
            
            echo '<div class="provider-card profile-settings-group-card">';
            foreach ($keysInVisual as $k) {
                $schemaEntry = $localSchemaOverrides[$k] ?? ($confSchema[$k] ?? []);
                $val = $metadataCurrent[$k] ?? '';
                echo renderMetaSettingRow($k, $schemaEntry, $val);
                $rendered[$k] = true;
            }
            echo '</div>';
            echo '</section>';
        };

        echo '<div class="profile-settings-columns">';
        foreach ($visualRows as $rowTitles) {
            foreach ($rowTitles as $title) {
                if (!isset($visualGroups[$title])) continue;
                $renderVisualGroup($title, $visualGroups[$title]);
            }
        }
        echo '</div>';

        // Future groups not explicitly placed above remain visible.
        $placedGroups = [];
        foreach ($visualRows as $rowTitles) {
            foreach ($rowTitles as $title) $placedGroups[$title] = true;
        }
        foreach ($visualGroups as $title => $keys) {
            if (!isset($placedGroups[$title])) $renderVisualGroup($title, $keys);
        }

        $remaining = array_values(array_diff($visualKeys, array_keys($rendered)));
        if (count($remaining) > 0) {
            echo '<section class="profile-settings-other">';
            echo '<h2 class="profile-settings-heading">Other</h2>';
            echo '<div class="provider-card profile-settings-group-card">';
            foreach ($remaining as $k) {
                $schemaEntry = $localSchemaOverrides[$k] ?? ($confSchema[$k] ?? []);
                $val = $metadataCurrent[$k] ?? '';
                echo renderMetaSettingRow($k, $schemaEntry, $val);
            }
            echo '</div>';
            echo '</section>';
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
        
        // Rechat Calculator
        function updateRechatCalculator() {
            const output = document.getElementById('rechat-calc-output');
            if (!output) return;
            
            const rechatHNum = document.getElementById('meta_num_RECHAT_H');
            const rechatPNum = document.getElementById('meta_num_RECHAT_P');
            
            if (!rechatHNum || !rechatPNum) return;
            
            const rechatH = parseInt(rechatHNum.value) || 2;
            const rechatP = parseInt(rechatPNum.value) || 50;
            
            const maxResponses = Math.max(1, rechatH);
            const probability = Math.max(0, Math.min(100, rechatP)) / 100;
            
            let parts = [];
            for (let response = 1; response <= maxResponses; response++) {
                let responseProb = 0;
                let color = '#9fb1c9';
                
                if (response === 1) {
                    responseProb = 100;
                    color = '#6dd19c';
                } else {
                    responseProb = Math.pow(probability, response - 1) * 100;
                    
                    if (responseProb >= 50) color = '#6dd19c';
                    else if (responseProb >= 25) color = 'rgb(242, 124, 17)';
                    else if (responseProb >= 10) color = '#ffa500';
                    else color = '#ff6b6b';
                }
                
                parts.push('<span style="color: ' + color + '; font-weight: 600;">Response ' + response + ': ' + responseProb.toFixed(1) + '%</span>');
            }
            
            output.innerHTML = parts.join(' <span style="color: #4a4a4a;">|</span> ');
        }
        
        // Initialize calculator on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateRechatCalculator();
            
            // Listen to both range and number inputs
            const rechatHRange = document.getElementById('meta_range_RECHAT_H');
            const rechatHNum = document.getElementById('meta_num_RECHAT_H');
            const rechatPRange = document.getElementById('meta_range_RECHAT_P');
            const rechatPNum = document.getElementById('meta_num_RECHAT_P');
            
            if (rechatHRange) rechatHRange.addEventListener('input', updateRechatCalculator);
            if (rechatHNum) rechatHNum.addEventListener('input', updateRechatCalculator);
            if (rechatPRange) rechatPRange.addEventListener('input', updateRechatCalculator);
            if (rechatPNum) rechatPNum.addEventListener('input', updateRechatCalculator);
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
        // Ensure AUTO_DIARY is not stored in profile metadata (global-only)
        if ('AUTO_DIARY' in base) delete base['AUTO_DIARY']
    }
    // Narrator-managed settings should not live in profile metadata.
    if ('REMOVE_ASTERISKS_FROM_OUTPUT' in base) delete base['REMOVE_ASTERISKS_FROM_OUTPUT']
    if ('REMOVE_ASTERISKS_FROM_PLAYER_INPUT' in base) delete base['REMOVE_ASTERISKS_FROM_PLAYER_INPUT']
    if ('REMOVE_ASTERISKS_FROM_NPC_OUTPUT' in base) delete base['REMOVE_ASTERISKS_FROM_NPC_OUTPUT']
    if ('INLINE_NARRATION_ENABLED' in base) delete base['INLINE_NARRATION_ENABLED']
    if ('INLINE_NARRATION_MODE' in base) delete base['INLINE_NARRATION_MODE']
    if ('REMOVE_PLAYER_AUTOCHAT_ASTERISKS' in base) delete base['REMOVE_PLAYER_AUTOCHAT_ASTERISKS']
    if ('PLAYER_AUTOCHAT_ASTERISKS_ENABLED' in base) delete base['PLAYER_AUTOCHAT_ASTERISKS_ENABLED']
    if ('PRESERVE_ASTERISKS_IN_CONTEXT' in base) delete base['PRESERVE_ASTERISKS_IN_CONTEXT']
    if ('ENFORCE_ACTIONS_PROMPT' in base) delete base['ENFORCE_ACTIONS_PROMPT']

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
        
        // Ensure middle_term_enabled checkbox is persisted into extended_data JSON
        try {
            const mtm = document.getElementById('middle_term_enabled')
            const imb = document.getElementById('individual_memory_enabled')
            if (mtm || imb) {
                let obj = {}
                try { obj = JSON.parse(String(form.extended_data.value||'')||'{}')||{} } catch(_e){ obj = {} }
                if (mtm) {
                    obj.middle_term_enabled = mtm.checked ? 1 : 0
                }
                if (imb) {
                    if (imb.checked) {
                        obj.individual_memory_enabled = 1
                    } else {
                        delete obj.individual_memory_enabled
                    }
                }
                form.extended_data.value = JSON.stringify(obj)
            }
        } catch(_e) {}
    }

    try {
        if (typeof syncRelationshipsToHidden === 'function') {
            syncRelationshipsToHidden()
        }
    } catch(_e) {}

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

    document.addEventListener("DOMContentLoaded", function() {
        const miniUpdateAppearance = document.getElementById('small_update_appearance');
        if (!miniUpdateAppearance) return;
        miniUpdateAppearance.addEventListener('click', async function(e){
            
            e.preventDefault();
            try {
            const doc = document;
            const nameEl = doc ? doc.getElementById('npc_name') : null;
            const npcName = nameEl ? String(nameEl.value||'').trim() : '';
            
            showProcessing()
            const res = await fetch('../cmd/action_ai_update_appearance.php?name='+encodeURIComponent(npcName));
            let j = {}; try { 
                j = await res.json(); 
                if (j.done) {
                     doc.getElementById('appearance').value = j.appearance
                     hideProcessing()
                }
            } catch(_e) {
                 j = {ok:false};
                 }
            
            
            

            } catch(_e){console.log(_e)}
        });
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
        processingMessage.id="processing_wheel"
        document.body.appendChild(processingMessage);
      }
    function hideProcessing() {
        processingMessage.innerHTML=''
        processingMessage.style.zIndex = '-10001';

    }
    var processingMessage;


</script>
