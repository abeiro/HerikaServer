<?php
/**
 * Relationship Dynamics — Per-NPC Editor Section
 * Included inside npc_master.php partial form (inside <details> block).
 * Expects $editItem to be in scope from npc_master.php.
 */

if (empty($editItem['npc_name'])) return;

$rdNpcName = $editItem['npc_name'];

// Load relationship dynamics from core_npc_master (CHIM-native)
$rdDynamics = [];
try {
    $rdRow = $GLOBALS['db']->fetchOne(
        "SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower("
        . $GLOBALS['db']->escapeLiteral($rdNpcName) . ") LIMIT 1"
    );
    if ($rdRow) {
        $rdExt = json_decode($rdRow['extended_data'] ?? '{}', true) ?: [];
        $rdDynamics = $rdExt['relationship_dynamics'] ?? [];
    }
} catch (Throwable $e) {
    // Silently fail — section will show defaults
}

// Current values (with defaults)
$rdLLPrimary    = $rdDynamics['love_language_primary'] ?? '';
$rdLLSecondary  = $rdDynamics['love_language_secondary'] ?? '';
$rdWarmth       = $rdDynamics['warmth_curve'] ?? '';
$rdTemperament  = $rdDynamics['inferred_temperament'] ?? '';
$rdRelPref      = $rdDynamics['relationship_preference'] ?? '';
$rdOpenness     = $rdDynamics['openness'] ?? '';
$rdPassion      = floatval($rdDynamics['passion'] ?? 0);
$rdJealousy     = floatval($rdDynamics['jealousy_anger'] ?? 0);
$rdStage        = $rdDynamics['stage'] ?? 'early';
$rdTotalPos     = intval($rdDynamics['total_positive_interactions'] ?? 0);
$rdInConflict   = !empty($rdDynamics['in_conflict']);
$rdInterests    = $rdDynamics['interests'] ?? ($rdDynamics['activity_preferences'] ?? []);
$rdInteractions = intval($rdDynamics['interaction_count'] ?? 0);

// Love language options
$rdLLOptions = [
    '' => '— Auto-generate —',
    'words_of_affirmation' => 'Words of Affirmation',
    'quality_time' => 'Quality Time',
    'physical_touch' => 'Physical Touch',
    'acts_of_service' => 'Acts of Service',
    'gifts' => 'Gifts',
];

// Warmth curve options
$rdCurveOptions = [
    '' => '— Auto-generate —',
    'slow_burn' => 'Slow Burn (10h half-life, trust earned slowly)',
    'moderate' => 'Moderate (8h half-life, open but paces)',
    'quick_warmth' => 'Quick Warmth (6h half-life, warms in 1-2 days)',
    'guarded' => 'Guarded (12h half-life, hardest to crack)',
];

// Temperament options (11 types)
$rdTempOptions = [
    '' => '— Auto-generate —',
    'Romantic' => 'Romantic (passion ×1.3, falls fast)',
    'Anxious' => 'Anxious (reunion ×1.8, fears abandonment)',
    'Bold' => 'Bold (passion ×1.1, confident & direct)',
    'Playful' => 'Playful (passion ×1.4, flirty & volatile)',
    'Humble' => 'Humble (passion ×1.1, steady & modest)',
    'Nurturing' => 'Nurturing (reunion ×1.2, caretaker)',
    'Jealous' => 'Jealous (jealousy ×2.0, possessive)',
    'Proud' => 'Proud (passion ×0.8, demands respect)',
    'Guarded' => 'Guarded (passion ×0.6, slow burn, huge payoff)',
    'Independent' => 'Independent (passion ×0.7, autonomy-first)',
    'Stoic' => 'Stoic (passion ×0.5, duty-first, deep quiet loyalty)',
];

// Relationship preference options
$rdRelPrefOptions = [
    '' => '— Not set —',
    'monogamous' => 'Monogamous',
    'polyamorous' => 'Polyamorous',
    'uncommitted' => 'Uncommitted',
    'demisexual' => 'Demisexual',
    'asexual' => 'Asexual',
    'not_interested' => 'Not Interested',
];

// Openness options
$rdOpennessOptions = [
    '' => '— Auto from temperament —',
    'high' => 'High (tolerant, effort compensates)',
    'medium' => 'Medium (soft blocks, 2x effort needed)',
    'low' => 'Low (hard blocks, strict standards)',
];

// Interest types and labels
$rdInterestTypes = [
    'combat'     => ['label' => 'Combat',     'icon' => '⚔️'],
    'crafting'   => ['label' => 'Crafting',   'icon' => '⚒️'],
    'alchemy'    => ['label' => 'Alchemy',    'icon' => '⚗️'],
    'enchanting' => ['label' => 'Enchanting', 'icon' => '✨'],
    'scholarly'  => ['label' => 'Scholarly',  'icon' => '📖'],
    'nature'     => ['label' => 'Nature',     'icon' => '🌲'],
    'social'     => ['label' => 'Social',     'icon' => '🍺'],
    'domestic'   => ['label' => 'Domestic',   'icon' => '🏠'],
    'adventure'  => ['label' => 'Adventure',  'icon' => '🗺️'],
    'spiritual'  => ['label' => 'Spiritual',  'icon' => '🙏'],
    'wealth'     => ['label' => 'Wealth',     'icon' => '💎'],
];

// Stage thresholds
$rdStageThresholds = ['early' => 0, 'established' => 50, 'deep' => 200];
$rdNextThreshold = ($rdStage === 'early') ? 50 : (($rdStage === 'established') ? 200 : null);

// API base URL
$rdApiUrl = '';
$rdScriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$rdUiPos = strpos($rdScriptPath, '/ui/');
if ($rdUiPos !== false) {
    $rdApiUrl = substr($rdScriptPath, 0, $rdUiPos);
}
?>

<div class="form-item span-2" id="reldyn-editor-section">
<details style="border:1px solid #4a4a4a; border-radius:8px; padding:12px; background:#262626; margin-top:4px;">
    <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17); font-size:1.05em; letter-spacing:0.5px;">
        💕 Relationship Dynamics
    </summary>

    <div style="margin-top:14px;" id="reldyn-content">

        <!-- Row 1: Love Languages + Warmth Curve -->
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px;">
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;">
                    Primary Love Language
                </label>
                <select id="reldyn_ll_primary" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 8px; width:100%; font-size:0.9em;">
                    <?php foreach ($rdLLOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= $rdLLPrimary === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;">
                    Secondary Love Language
                </label>
                <select id="reldyn_ll_secondary" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 8px; width:100%; font-size:0.9em;">
                    <?php foreach ($rdLLOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= $rdLLSecondary === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;">
                    Warmth Curve
                </label>
                <select id="reldyn_warmth" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 8px; width:100%; font-size:0.9em;">
                    <?php foreach ($rdCurveOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= $rdWarmth === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 2: Temperament + Stage + Status -->
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-top:12px;">
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;">
                    Temperament
                </label>
                <select id="reldyn_temperament" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 8px; width:100%; font-size:0.9em;">
                    <?php foreach ($rdTempOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= $rdTemperament === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;">
                    Stage
                </label>
                <div style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; padding:6px 8px; color:#e9efff; font-size:0.9em;">
                    <?php
                    $stageEmoji = ['early' => '🌱', 'established' => '🌿', 'deep' => '🌳'];
                    echo ($stageEmoji[$rdStage] ?? '') . ' ' . ucfirst($rdStage);
                    if ($rdNextThreshold !== null) {
                        echo " <span style='color:#888;'>({$rdTotalPos}/{$rdNextThreshold} interactions)</span>";
                    } else {
                        echo " <span style='color:#888;'>({$rdTotalPos} interactions)</span>";
                    }
                    ?>
                </div>
            </div>
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;">
                    Status
                </label>
                <div style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; padding:6px 8px; color:#e9efff; font-size:0.9em;">
                    <?php if ($rdInConflict): ?>
                        <span style="color:#f87171;">⚠️ In Conflict</span>
                    <?php elseif ($rdPassion >= 40): ?>
                        <span style="color:#fbbf24;">🔥 High Passion</span>
                    <?php elseif ($rdPassion >= 15): ?>
                        <span style="color:#86efac;">✨ Warming</span>
                    <?php else: ?>
                        <span style="color:#9fb1c9;">◽ Neutral</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Row 2b: Relationship Preference + Openness -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px;">
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;"
                    title="How this NPC approaches exclusivity. Affects jealousy triggers and commitment gating.">
                    Relationship Preference
                </label>
                <select id="reldyn_rel_pref" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 8px; width:100%; font-size:0.9em;">
                    <?php foreach ($rdRelPrefOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= $rdRelPref === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:4px; font-size:0.85em;"
                    title="How tolerant this NPC is of the player failing attraction checks. High = forgiving, Low = strict gatekeeping.">
                    Openness
                </label>
                <select id="reldyn_openness" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 8px; width:100%; font-size:0.9em;">
                    <?php foreach ($rdOpennessOptions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= $rdOpenness === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 3: Passion + Jealousy bars -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px;">
            <div>
                <label style="font-weight:600; color:#9fb1c9; display:block; margin-bottom:4px; font-size:0.8em;">
                    Passion (RPM) — <?= round($rdPassion, 1) ?>/100
                </label>
                <div style="background:#1a1a1a; border:1px solid #3a3a3a; border-radius:4px; height:20px; overflow:hidden;">
                    <div style="height:100%; width:<?= min(100, $rdPassion) ?>%; background:linear-gradient(90deg, #22c55e, #fbbf24 50%, #ef4444); border-radius:4px; transition:width 0.3s;"></div>
                </div>
            </div>
            <div>
                <label style="font-weight:600; color:#9fb1c9; display:block; margin-bottom:4px; font-size:0.8em;">
                    Jealousy — <?= round($rdJealousy, 1) ?>/100
                </label>
                <div style="background:#1a1a1a; border:1px solid #3a3a3a; border-radius:4px; height:20px; overflow:hidden;">
                    <div style="height:100%; width:<?= min(100, $rdJealousy) ?>%; background:linear-gradient(90deg, #fbbf24, #ef4444); border-radius:4px; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        <!-- Row 4: Interests -->
        <div style="margin-top:16px;">
            <label style="font-weight:700; color:rgb(242, 124, 17); display:block; margin-bottom:8px; font-size:0.85em;">
                Interests <span style="color:#888; font-weight:400;">(passion multiplier across all love languages)</span>
            </label>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px 12px;">
                <?php foreach ($rdInterestTypes as $intKey => $intInfo):
                    $intVal = $rdInterests[$intKey] ?? 1.0;
                    $barColor = $intVal >= 1.3 ? '#22c55e' : ($intVal <= 0.85 ? '#ef4444' : '#4a4a4a');
                ?>
                <div style="background:#1a1a1a; border:1px solid #3a3a3a; border-radius:4px; padding:6px 8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <small style="color:#cfd9ea; font-size:0.8em;"><?= $intInfo['icon'] ?> <?= $intInfo['label'] ?></small>
                        <small id="reldyn_int_val_<?= $intKey ?>" style="color:<?= $barColor ?>; font-weight:700; font-size:0.8em;"><?= number_format($intVal, 1) ?>x</small>
                    </div>
                    <input type="range" id="reldyn_int_<?= $intKey ?>"
                        min="0.5" max="2.0" step="0.1" value="<?= $intVal ?>"
                        style="width:100%; accent-color:rgb(242, 124, 17); height:6px;"
                        oninput="reldynUpdateSlider('<?= $intKey ?>', this.value)">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions bar -->
        <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <button type="button" onclick="reldynSave()" id="reldyn-save-btn"
                title="Saves current settings and re-embeds the interest vector for location/topic matching."
                style="background:linear-gradient(180deg, #1a472a, #0d3320); border:1px solid #22c55e; border-radius:4px; color:#86efac; padding:7px 16px; cursor:pointer; font-size:0.85em; font-weight:600;">
                💾 Apply Changes
            </button>
            <button type="button" onclick="reldynAutoGen()"
                title="Reads NPC bio, personality, relationships, occupation, and class to generate interests, love language, and warmth curve. Also embeds an interest vector for location/topic matching."
                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#fde68a; padding:7px 12px; cursor:pointer; font-size:0.85em;">
                🔄 Auto-Generate from Profile
            </button>
            <button type="button" onclick="reldynReset()"
                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#f87171; padding:7px 12px; cursor:pointer; font-size:0.85em;">
                ↺ Reset Dynamics
            </button>
            <span id="reldyn-status" style="color:#86efac; font-size:0.85em; margin-left:8px;"></span>
        </div>

    </div>
</details>
</div>

<script>
(function(){
    const RELDYN_NPC = <?= json_encode($rdNpcName) ?>;
    const RELDYN_API = <?= json_encode($rdApiUrl . '/ext/relationship_dynamics/api_save_npc.php') ?>;
    const INTERESTS = <?= json_encode(array_keys($rdInterestTypes)) ?>;

    window.reldynUpdateSlider = function(act, val) {
        const el = document.getElementById('reldyn_int_val_' + act);
        if (!el) return;
        val = parseFloat(val);
        el.textContent = val.toFixed(1) + 'x';
        el.style.color = val >= 1.3 ? '#22c55e' : (val <= 0.85 ? '#ef4444' : '#9fb1c9');
    };

    function collectData() {
        const data = {
            npc: RELDYN_NPC,
            love_language_primary: document.getElementById('reldyn_ll_primary')?.value || '',
            love_language_secondary: document.getElementById('reldyn_ll_secondary')?.value || '',
            warmth_curve: document.getElementById('reldyn_warmth')?.value || '',
            inferred_temperament: document.getElementById('reldyn_temperament')?.value || '',
            relationship_preference: document.getElementById('reldyn_rel_pref')?.value || '',
            openness: document.getElementById('reldyn_openness')?.value || '',
            interests: {}
        };
        INTERESTS.forEach(int => {
            const slider = document.getElementById('reldyn_int_' + int);
            if (slider) data.interests[int] = parseFloat(slider.value);
        });
        return data;
    }

    function showStatus(msg, color) {
        const el = document.getElementById('reldyn-status');
        if (el) { el.textContent = msg; el.style.color = color || '#86efac'; }
        if (msg) setTimeout(() => { if (el) el.textContent = ''; }, 4000);
    }

    window.reldynSave = async function() {
        const data = collectData();
        data.action = 'save';
        showStatus('Saving...', '#fde68a');
        try {
            const resp = await fetch(RELDYN_API, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const json = await resp.json();
            if (json.ok) {
                showStatus('Saved ✓', '#86efac');
            } else {
                showStatus('Error: ' + (json.error || 'Unknown'), '#f87171');
            }
        } catch(e) {
            showStatus('Network error: ' + e.message, '#f87171');
        }
    };

    window.reldynAutoGen = async function() {
        showStatus('Generating...', '#fde68a');
        try {
            const resp = await fetch(RELDYN_API, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ npc: RELDYN_NPC, action: 'autogen' })
            });
            const json = await resp.json();
            if (json.ok && json.preferences) {
                // Update sliders
                Object.entries(json.preferences).forEach(([act, val]) => {
                    const slider = document.getElementById('reldyn_int_' + act);
                    if (slider) {
                        slider.value = val;
                        reldynUpdateSlider(act, val);
                    }
                });
                // Update dropdowns if auto-generated
                if (json.love_language_primary) {
                    const llp = document.getElementById('reldyn_ll_primary');
                    if (llp) llp.value = json.love_language_primary;
                }
                if (json.love_language_secondary) {
                    const lls = document.getElementById('reldyn_ll_secondary');
                    if (lls) lls.value = json.love_language_secondary;
                }
                if (json.warmth_curve) {
                    const wc = document.getElementById('reldyn_warmth');
                    if (wc) wc.value = json.warmth_curve;
                }
                if (json.inferred_temperament) {
                    const t = document.getElementById('reldyn_temperament');
                    if (t) t.value = json.inferred_temperament;
                }
                showStatus('Generated and saved from profile ✓', '#4ade80');
            } else {
                showStatus('Error: ' + (json.error || 'Unknown'), '#f87171');
            }
        } catch(e) {
            showStatus('Network error: ' + e.message, '#f87171');
        }
    };

    window.reldynReset = async function() {
        if (!confirm('Reset all dynamics for ' + RELDYN_NPC + '? This clears passion, interactions, stage, and conflict state. Love language and interests are kept.')) return;
        showStatus('Resetting...', '#fde68a');
        try {
            const resp = await fetch(RELDYN_API, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ npc: RELDYN_NPC, action: 'reset' })
            });
            const json = await resp.json();
            if (json.ok) {
                showStatus('Dynamics reset ✓', '#86efac');
                // Reload section to reflect new state
                setTimeout(() => location.reload(), 1000);
            } else {
                showStatus('Error: ' + (json.error || 'Unknown'), '#f87171');
            }
        } catch(e) {
            showStatus('Network error: ' + e.message, '#f87171');
        }
    };
})();
</script>
