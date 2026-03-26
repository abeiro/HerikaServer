<?php
/**
 * Relationship Dynamics — Global Settings Page
 *
 * Tuning knobs for passion, diminishing returns, warmth curves,
 * jealousy, conflict/repair, stages, and reunion.
 *
 * Embeddable in config_hub.php or standalone.
 * Reads/writes conf_opts key 'relationship_dynamics_config'.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bootstrap CHIM
$enginePath = realpath(__DIR__ . '/../../') . '/';
$GLOBALS['ENGINE_PATH'] = $enginePath;
require_once $enginePath . 'conf/conf.php';
require_once $enginePath . "lib/{$GLOBALS['DBDRIVER']}.class.php";
$GLOBALS['db'] = new sql();

require_once __DIR__ . '/relationship_dynamics.php';

// Web root for assets
$scriptPath = $_SERVER['SCRIPT_NAME'];
$extPos = strpos($scriptPath, '/ext/');
$webRoot = ($extPos !== false) ? substr($scriptPath, 0, $extPos) : '';
$webRoot = rtrim($webRoot, '/');

$embed = isset($_GET['embed']);

// =========================================================================
// Handle POST save
// =========================================================================
$saveMsg = '';
$saveOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reldyn'])) {
    $db = $GLOBALS['db'];

    $newConfig = [
        'enabled'                            => isset($_POST['enabled']),
        'log_enabled'                        => isset($_POST['log_enabled']),
        // Subsystem toggles
        'passion_enabled'                    => isset($_POST['passion_enabled']),
        'ambient_enabled'                    => isset($_POST['ambient_enabled']),
        'combat_enabled'                     => isset($_POST['combat_enabled']),
        'jealousy_enabled'                   => isset($_POST['jealousy_enabled']),
        'reunion_enabled'                    => isset($_POST['reunion_enabled']),
        'conflict_enabled'                   => isset($_POST['conflict_enabled']),
        'topic_bonus_enabled'                => isset($_POST['topic_bonus_enabled']),
        'flirt_bonus_enabled'                => isset($_POST['flirt_bonus_enabled']),
        'type_filter_enabled'                => isset($_POST['type_filter_enabled']),
        // Passion settings
        'base_passion_gain'                  => max(0.1, min(10.0, floatval($_POST['base_passion_gain'] ?? 2.0))),
        'passion_max'                        => max(10, min(200, floatval($_POST['passion_max'] ?? 100))),
        'decay_max_hours'                    => max(0, min(168, floatval($_POST['decay_max_hours'] ?? 0))),
        // Jealousy settings
        'jealousy_max'                       => max(10, min(200, floatval($_POST['jealousy_max'] ?? 100))),
        'jealousy_decay_per_hour'            => max(0.1, min(10.0, floatval($_POST['jealousy_decay_per_hour'] ?? 1.5))),
        // Conflict settings
        'conflict_threshold_affinity_drop'   => max(1, min(50, intval($_POST['conflict_threshold_affinity_drop'] ?? 10))),
        'conflict_threshold_jealousy'        => max(5, min(100, intval($_POST['conflict_threshold_jealousy'] ?? 40))),
        'conflict_resolution_positive_count' => max(1, min(20, intval($_POST['conflict_resolution_positive_count'] ?? 3))),
        'conflict_repair_passion_burst'      => max(1.0, min(50.0, floatval($_POST['conflict_repair_passion_burst'] ?? 20.0))),
        'conflict_repair_passion_mult'       => max(1.0, min(3.0, floatval($_POST['conflict_repair_passion_mult'] ?? 1.5))),
        // Reunion settings
        'reunion_min_hours'                  => max(1, min(48, intval($_POST['reunion_min_hours'] ?? 8))),
        'reunion_min_affection'              => max(0, min(100, intval($_POST['reunion_min_affection'] ?? 40))),
        // Stage thresholds
        'stage_established_threshold'        => max(10, min(500, intval($_POST['stage_established_threshold'] ?? 50))),
        'stage_deep_threshold'               => max(50, min(2000, intval($_POST['stage_deep_threshold'] ?? 200))),
    ];

    $jsonConfig = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $escaped = $db->escape($jsonConfig);

    // Upsert
    $existing = $db->fetchOne("SELECT id FROM conf_opts WHERE id = 'relationship_dynamics_config' LIMIT 1");
    if (!empty($existing)) {
        $db->execQuery("UPDATE conf_opts SET value = '{$escaped}' WHERE id = 'relationship_dynamics_config'");
    } else {
        $db->execQuery("INSERT INTO conf_opts (id, value) VALUES ('relationship_dynamics_config', '{$escaped}')");
    }

    // Clear cached config so re-read picks up changes
    RelationshipDynamics::clearConfigCache();

    $saveMsg = 'Settings saved.';
    $saveOk = true;
}

// Load current config
$cfg = RelationshipDynamics::getConfig();

// =========================================================================
// HTML
// =========================================================================
if (!$embed) {
    $TITLE = "Relationship Dynamics";
    ob_start();
    include $enginePath . 'ui/tmpl/head.html';
    include $enginePath . 'ui/tmpl/navbar.php';
}
?>

<?php if (!$embed): ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php endif; ?>

<style>
html, body {
    background: #1a1a1a;
}
.rd-wrap {
    padding: <?php echo $embed ? '20px' : '80px 10% 40px'; ?>;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #e0e0e0;
    max-width: 960px;
    margin: 0 auto;
    background: #1a1a1a;
}
.rd-header {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(28, 28, 28, 0.98));
    padding: 20px 24px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #3a3a3a;
}
.rd-header h1 {
    font-family: 'MagicCards', serif;
    color: rgb(242, 124, 17);
    margin: 0 0 4px;
    font-size: 1.6em;
    letter-spacing: 1px;
}
.rd-header p {
    color: #9fb1c9;
    margin: 0;
    font-size: 0.9em;
}
.rd-section {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    padding: 20px 24px;
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    margin-bottom: 16px;
}
.rd-section h2 {
    font-family: 'MagicCards', serif;
    color: rgb(242, 124, 17);
    font-size: 1.15em;
    margin: 0 0 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    letter-spacing: 1px;
}
.rd-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    gap: 12px;
}
.rd-row label {
    flex: 0 0 280px;
    font-size: 0.88em;
    color: #c8c8c8;
}
.rd-row input[type="number"],
.rd-row input[type="text"] {
    background: #1a1a1a;
    border: 1px solid #4a4a4a;
    color: #f0f0f0;
    padding: 6px 10px;
    border-radius: 6px;
    width: 120px;
    font-size: 0.9em;
}
.rd-row input[type="number"]:focus,
.rd-row input[type="text"]:focus {
    border-color: rgb(242, 124, 17);
    outline: none;
    box-shadow: 0 0 4px rgba(242, 124, 17, 0.3);
}
.rd-row .rd-hint {
    font-size: 0.78em;
    color: #777;
    flex: 1;
}
.rd-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.rd-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: rgb(242, 124, 17);
}
.rd-toggle label {
    font-size: 0.88em;
    color: #c8c8c8;
    cursor: pointer;
}
.rd-save-bar {
    position: sticky;
    bottom: 0;
    background: linear-gradient(180deg, rgba(28, 28, 28, 0.95), rgba(20, 20, 20, 0.98));
    padding: 14px 24px;
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 8px;
}
.rd-save-bar button {
    background: linear-gradient(180deg, rgb(242, 124, 17), rgb(200, 95, 5));
    color: #fff;
    border: none;
    padding: 10px 28px;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 0.5px;
}
.rd-save-bar button:hover {
    background: linear-gradient(180deg, rgb(255, 140, 30), rgb(242, 124, 17));
    box-shadow: 0 2px 8px rgba(242, 124, 17, 0.4);
}
.rd-msg {
    font-size: 0.9em;
    padding: 8px 14px;
    border-radius: 6px;
    margin-bottom: 16px;
}
.rd-msg.ok { background: rgba(40, 167, 69, 0.15); color: #5ddf7e; border: 1px solid rgba(40, 167, 69, 0.3); }
.rd-msg.err { background: rgba(220, 53, 69, 0.15); color: #f08090; border: 1px solid rgba(220, 53, 69, 0.3); }
.rd-curve-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
    font-size: 0.85em;
}
.rd-curve-table th {
    text-align: left;
    padding: 6px 10px;
    color: rgb(242, 124, 17);
    border-bottom: 1px solid #4a4a4a;
    font-weight: 600;
}
.rd-curve-table td {
    padding: 5px 10px;
    border-bottom: 1px solid #2a2a2a;
    color: #b0b0b0;
}
.rd-curve-table tr:hover td {
    color: #e0e0e0;
    background: rgba(242, 124, 17, 0.05);
}
.rd-formula {
    background: #1a1a1a;
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 10px 14px;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 0.85em;
    color: #d4d4d4;
    margin: 8px 0;
    white-space: pre-wrap;
}
</style>

<div class="rd-wrap">
    <div class="rd-header">
        <h1>Relationship Dynamics</h1>
        <p>RPM / Speed / Gears &mdash; passion drives affinity gain, diminishing returns enforce multi-day pacing</p>
    </div>

    <?php if ($saveMsg): ?>
        <div class="rd-msg <?php echo $saveOk ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($saveMsg); ?></div>
    <?php endif; ?>

    <form method="post" action="">

    <!-- General -->
    <div class="rd-section">
        <h2>General</h2>
        <div class="rd-toggle">
            <input type="hidden" name="enabled" value="">
            <input type="checkbox" name="enabled" id="rd_enabled" <?php if (!empty($cfg['enabled'])) echo 'checked'; ?>>
            <label for="rd_enabled">System Enabled</label>
        </div>
        <div class="rd-toggle">
            <input type="hidden" name="log_enabled" value="">
            <input type="checkbox" name="log_enabled" id="rd_log" <?php if (!empty($cfg['log_enabled'])) echo 'checked'; ?>>
            <label for="rd_log">Debug Logging</label>
        </div>
    </div>

    <!-- Subsystem Toggles -->
    <div class="rd-section">
        <h2>Subsystem Toggles</h2>
        <p style="color:#888; font-size:0.85em; margin-bottom:12px;">Enable or disable individual systems. All default to ON. Disabling a subsystem skips its processing entirely — zero overhead.</p>
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">
            <div class="rd-toggle">
                <input type="hidden" name="passion_enabled" value="">
                <input type="checkbox" name="passion_enabled" id="rd_passion" <?php if ($cfg['passion_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_passion" title="Passion accumulation from interactions (RPM engine)">Passion / RPM</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="ambient_enabled" value="">
                <input type="checkbox" name="ambient_enabled" id="rd_ambient" <?php if ($cfg['ambient_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_ambient" title="Environmental resonance — passive warmth in matching locations">Ambient / POI</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="combat_enabled" value="">
                <input type="checkbox" name="combat_enabled" id="rd_combat" <?php if ($cfg['combat_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_combat" title="Combat events classified as acts of service / combat failure">Combat Events</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="jealousy_enabled" value="">
                <input type="checkbox" name="jealousy_enabled" id="rd_jealousy" <?php if ($cfg['jealousy_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_jealousy" title="Jealousy triggers from flirting with others while committed NPC is nearby">Jealousy</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="reunion_enabled" value="">
                <input type="checkbox" name="reunion_enabled" id="rd_reunion" <?php if ($cfg['reunion_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_reunion" title="Passion spike when reuniting with an NPC after in-game separation">Reunion Spike</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="conflict_enabled" value="">
                <input type="checkbox" name="conflict_enabled" id="rd_conflict" <?php if ($cfg['conflict_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_conflict" title="Conflict entry from affinity drops or high jealousy, repair bonus from positive interactions">Conflict / Repair</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="topic_bonus_enabled" value="">
                <input type="checkbox" name="topic_bonus_enabled" id="rd_topic" <?php if ($cfg['topic_bonus_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_topic" title="Bonus passion when conversation topic matches NPC interests (via Oghma vector similarity)">Topic Talk Bonus</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="flirt_bonus_enabled" value="">
                <input type="checkbox" name="flirt_bonus_enabled" id="rd_flirt" <?php if ($cfg['flirt_bonus_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_flirt" title="Stacking bonus when NPC mood is flirty AND location or topic matches">Flirt-in-Context</label>
            </div>
            <div class="rd-toggle">
                <input type="hidden" name="type_filter_enabled" value="">
                <input type="checkbox" name="type_filter_enabled" id="rd_typefilter" <?php if ($cfg['type_filter_enabled'] ?? true) echo 'checked'; ?>>
                <label for="rd_typefilter" title="Relationship preference gates which relationship types are available (demisexual, asexual, etc)">Type Filter</label>
            </div>
        </div>
    </div>

    <!-- Passion / RPM -->
    <div class="rd-section">
        <h2>Passion (RPM)</h2>
        <div class="rd-formula">affinity_gain_mult = 0.3 + (passion / 100) x 1.7
  passion 0  = x0.3 (idling)    passion 50 = x1.15 (cruising)    passion 100 = x2.0 (redline)</div>
        <div class="rd-row">
            <label for="rd_bpg">Base Passion Gain</label>
            <input type="number" step="0.1" min="0.1" max="10" id="rd_bpg" name="base_passion_gain"
                   value="<?php echo htmlspecialchars($cfg['base_passion_gain']); ?>">
            <span class="rd-hint">Per interaction before multipliers (default: 2.0)</span>
        </div>
        <div class="rd-row">
            <label for="rd_pmax">Passion Maximum</label>
            <input type="number" step="1" min="10" max="200" id="rd_pmax" name="passion_max"
                   value="<?php echo htmlspecialchars($cfg['passion_max']); ?>">
            <span class="rd-hint">Hard cap (default: 100)</span>
        </div>
        <div class="rd-row">
            <label for="rd_dmh">Between-Session Decay (hours)</label>
            <input type="number" step="0.5" min="0" max="168" id="rd_dmh" name="decay_max_hours"
                   value="<?php echo htmlspecialchars($cfg['decay_max_hours'] ?? 0); ?>">
            <span class="rd-hint">0 = passion frozen when offline (default). Set > 0 for persistent server / sim scenarios.</span>
        </div>
    </div>

    <!-- Warmth Curves (read-only reference) -->
    <div class="rd-section">
        <h2>Warmth Curves</h2>
        <p style="font-size:0.85em; color:#888; margin:0 0 8px;">Per-NPC curve set in Sharmat NPC Editor. These are the built-in presets:</p>
        <table class="rd-curve-table">
            <thead>
                <tr>
                    <th>Curve</th>
                    <th>Temperaments</th>
                    <th>Decay Rate</th>
                    <th>Half-Life</th>
                    <th>Passion Decay</th>
                    <th>Target Days</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>slow_burn</td>
                    <td>Romantic, Jealous</td>
                    <td>0.10/int</td>
                    <td>10h</td>
                    <td>2.5/hr</td>
                    <td>3 IRL days</td>
                </tr>
                <tr>
                    <td>moderate</td>
                    <td>Humble</td>
                    <td>0.08/int</td>
                    <td>8h</td>
                    <td>3.0/hr</td>
                    <td>2 days</td>
                </tr>
                <tr>
                    <td>quick_warmth</td>
                    <td>(default)</td>
                    <td>0.06/int</td>
                    <td>6h</td>
                    <td>4.0/hr</td>
                    <td>1.5 days</td>
                </tr>
                <tr>
                    <td>guarded</td>
                    <td>Proud, Independent</td>
                    <td>0.12/int</td>
                    <td>12h</td>
                    <td>5.0/hr</td>
                    <td>4+ days</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Jealousy -->
    <div class="rd-section">
        <h2>Jealousy</h2>
        <div class="rd-row">
            <label for="rd_jmax">Jealousy Maximum</label>
            <input type="number" step="1" min="10" max="200" id="rd_jmax" name="jealousy_max"
                   value="<?php echo htmlspecialchars($cfg['jealousy_max']); ?>">
            <span class="rd-hint">Hard cap (default: 100)</span>
        </div>
        <div class="rd-row">
            <label for="rd_jdec">Jealousy Decay (per hour)</label>
            <input type="number" step="0.1" min="0.1" max="10" id="rd_jdec" name="jealousy_decay_per_hour"
                   value="<?php echo htmlspecialchars($cfg['jealousy_decay_per_hour']); ?>">
            <span class="rd-hint">Anger fades slower than joy (default: 1.5)</span>
        </div>
    </div>

    <!-- Conflict / Repair -->
    <div class="rd-section">
        <h2>Conflict / Repair Cycle</h2>
        <div class="rd-row">
            <label for="rd_ctad">Affinity Drop to Trigger Conflict</label>
            <input type="number" step="1" min="1" max="50" id="rd_ctad" name="conflict_threshold_affinity_drop"
                   value="<?php echo htmlspecialchars($cfg['conflict_threshold_affinity_drop']); ?>">
            <span class="rd-hint">Points of affinity lost in session (default: 10)</span>
        </div>
        <div class="rd-row">
            <label for="rd_ctj">Jealousy to Trigger Conflict</label>
            <input type="number" step="1" min="5" max="100" id="rd_ctj" name="conflict_threshold_jealousy"
                   value="<?php echo htmlspecialchars($cfg['conflict_threshold_jealousy']); ?>">
            <span class="rd-hint">Jealousy anger threshold (default: 40)</span>
        </div>
        <div class="rd-row">
            <label for="rd_crpc">Positive Interactions to Resolve</label>
            <input type="number" step="1" min="1" max="20" id="rd_crpc" name="conflict_resolution_positive_count"
                   value="<?php echo htmlspecialchars($cfg['conflict_resolution_positive_count']); ?>">
            <span class="rd-hint">Kind acts needed to end conflict (default: 3)</span>
        </div>
        <div class="rd-row">
            <label for="rd_crpb">Repair Passion Burst</label>
            <input type="number" step="1" min="1" max="50" id="rd_crpb" name="conflict_repair_passion_burst"
                   value="<?php echo htmlspecialchars($cfg['conflict_repair_passion_burst']); ?>">
            <span class="rd-hint">Passion gained on resolution (default: 20)</span>
        </div>
        <div class="rd-row">
            <label for="rd_crpm">Repair Passion Multiplier</label>
            <input type="number" step="0.1" min="1.0" max="3.0" id="rd_crpm" name="conflict_repair_passion_mult"
                   value="<?php echo htmlspecialchars($cfg['conflict_repair_passion_mult']); ?>">
            <span class="rd-hint">Bonus on positive acts during conflict (default: 1.5x)</span>
        </div>
    </div>

    <!-- Reunion -->
    <div class="rd-section">
        <h2>Reunion Spike</h2>
        <div class="rd-row">
            <label for="rd_rmh">Minimum Hours Apart</label>
            <input type="number" step="1" min="1" max="48" id="rd_rmh" name="reunion_min_hours"
                   value="<?php echo htmlspecialchars($cfg['reunion_min_hours']); ?>">
            <span class="rd-hint">Real-time hours before reunion fires (default: 8)</span>
        </div>
        <div class="rd-row">
            <label for="rd_rma">Minimum Affection</label>
            <input type="number" step="1" min="0" max="100" id="rd_rma" name="reunion_min_affection"
                   value="<?php echo htmlspecialchars($cfg['reunion_min_affection']); ?>">
            <span class="rd-hint">MARAS affection required (default: 40)</span>
        </div>
        <p style="font-size:0.82em; color:#777; margin:8px 0 0;">
            8-16h = +5 | 16-24h = +8 | 24-48h = +12 | 48-72h = +18 | 72h+ = +25 passion.
            Bypasses diminishing returns.
        </p>
    </div>

    <!-- Stages -->
    <div class="rd-section">
        <h2>Relationship Stages</h2>
        <div class="rd-row">
            <label for="rd_set">Established Threshold</label>
            <input type="number" step="1" min="10" max="500" id="rd_set" name="stage_established_threshold"
                   value="<?php echo htmlspecialchars($cfg['stage_established_threshold']); ?>">
            <span class="rd-hint">Positive interactions to reach Established (default: 50)</span>
        </div>
        <div class="rd-row">
            <label for="rd_sdt">Deep Threshold</label>
            <input type="number" step="1" min="50" max="2000" id="rd_sdt" name="stage_deep_threshold"
                   value="<?php echo htmlspecialchars($cfg['stage_deep_threshold']); ?>">
            <span class="rd-hint">Positive interactions to reach Deep (default: 200)</span>
        </div>
        <table class="rd-curve-table" style="margin-top:10px;">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Passion Floor</th>
                    <th>Passion Ceiling</th>
                    <th>Gain Mult</th>
                    <th>DR Mult</th>
                    <th>Character</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Early</td><td>0</td><td>100</td><td>1.3x</td><td>0.8x</td><td>Butterflies, high highs</td></tr>
                <tr><td>Established</td><td>5</td><td>70</td><td>1.0x</td><td>1.2x</td><td>Comfortable, routine</td></tr>
                <tr><td>Deep</td><td>15</td><td>50</td><td>0.8x</td><td>1.0x</td><td>Resilient, hard to break</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Save -->
    <div class="rd-save-bar">
        <button type="submit" name="save_reldyn" value="1">Save Settings</button>
        <span style="font-size:0.82em; color:#777;">Changes apply immediately to all NPCs.</span>
    </div>

    </form>
</div>

<?php
if (!$embed) {
    include $enginePath . 'ui/tmpl/footer.html';
    $buffer = ob_get_contents();
    ob_end_clean();
    $title = $TITLE;
    $buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
    echo $buffer;
}
?>
