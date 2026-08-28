<?php
/**
 * RELATIONSHIP EDITOR - Embeddable UI Component
 *
 * This file provides a relationship editing interface that can be embedded
 * in npc_master.php. It reads/writes to extended_data.relationships (JSONB).
 *
 * Features:
 * - Visual table editor for relationships
 * - "Build with AI" button to infer relationships from recent event history
 * - Manual add/edit/delete
 *
 * INSTALLATION:
 * Add this line in npc_master.php after the relationships textarea (around line 1407):
 *
 *   <?php if (file_exists(__DIR__."/../../ext/relationship_system/relationship_editor.php")) {
 *       include(__DIR__."/../../ext/relationship_system/relationship_editor.php");
 *   } ?>
 */

// This file expects $editItem to be available from the parent npc_master.php
if (!isset($editItem) || !is_array($editItem)) {
    return;
}

require_once $GLOBALS["ENGINE_PATH"] . "lib/relationship_manager.php";

// Get existing JSONB relationships
$extendedData = json_decode($editItem['extended_data'] ?? '{}', true) ?: [];
$jsonbRelationships = RelationshipManager::normalizeRelationshipMap($extendedData['relationships'] ?? []);

// Keep legacy text available as fallback input for AI analysis.
$textRelationships = $editItem['relationships'] ?? '';
$npcName = $editItem['npc_name'] ?? 'Unknown';

// Check if there's TEXT data but no JSONB data (candidate for AI analysis)
$hasTextNoJsonb = empty($jsonbRelationships) && !empty(trim($textRelationships));

// NOTE: Auto-initialization is handled via the "Build with AI" button
// or during gameplay in postrequest.php - NOT on UI load
// (Loading RelationshipLLM in UI context causes missing class errors)
$autoInitMessage = '';

// Tier labels and colors for display (11 tiers with expanded ranges)
$tierColors = [
    'Bonded'      => '#22c55e',    // Bright green
    'Devoted'     => '#4ade80',    // Green
    'Fond'        => '#86efac',    // Light green
    'Friendly'    => '#a7f3d0',    // Pale green
    'Acquaintance'=> '#d9f99d',    // Yellow-green
    'Neutral'     => '#e5e7eb',    // Gray
    'Wary'        => '#fde68a',    // Yellow
    'Cold'        => '#fed7aa',    // Light orange
    'Resentful'   => '#fca5a5',    // Light red
    'Hateful'     => '#f87171',    // Red
    'Hostile'     => '#ef4444'     // Dark red
];

// Default types with emojis - extended list
$defaultTypes = [
    // Classic types
    'romantic'     => '💘',
    'platonic'     => '🤝',
    'familial'     => '👨‍👩‍👧',
    'professional' => '💼',
    'rival'        => '⚔️',
    'enemy'        => '🗡️',
    'neutral'      => '➖',
    // Extended types
    'nemesis'      => '☠️',
    'estranged'    => '💔',
    'transactional'=> '💰',
    'protective'   => '🛡️',
    'indebted'     => '🙏',
    'fanatical'    => '🔥',
    'mentor'       => '📚',
    'student'      => '🎓',
    'servant'      => '🧹',
    'client'       => '🛒',
    'patron'       => '👑',
    'crush'        => '💗',
    'ex'           => '💢',
    'betrayed'     => '🔪',
    'suspicious'   => '👀',
    'admirer'      => '⭐',
    'jealous'      => '💚',
    'fearful'      => '😨',
    'obsessed'     => '🌀',
    'awed'         => '😲',
    'contempt'     => '😤',
    'pitying'      => '😢',
    'grateful'     => '🥹',
    'curious'      => '🧐',
    'dismissive'   => '🙄'
];

// Collect any custom types from existing relationships
$customTypes = [];
foreach ($jsonbRelationships as $target => $data) {
    $type = $data['type'] ?? 'neutral';
    if (!isset($defaultTypes[$type])) {
        $customTypes[$type] = '🏷️'; // Default emoji for custom types
    }
}

// Merge default + custom types
$typeIcons = array_merge($defaultTypes, $customTypes);
?>

<div class="form-item span-2" id="relationship-editor-section">
    <details class="metadata-skills-view" style="border:1px solid #4a4a4a; border-radius:8px; padding:8px; background:#262626; margin-top:16px;" open>
        <summary style="cursor:pointer; font-weight:700; color:rgb(242, 124, 17);">
            Relationship Affinities
        </summary>
        <small class="hint" style="display:block; margin:8px 0; color:#888;">
            Tracked relationships with affinity scores (-100 to +100) and types.
            <?php if ($hasTextNoJsonb): ?>
                <br><strong style="color:#fde68a;">Tip:</strong> Click "Build with AI" to analyze recent event history into scored affinities.
            <?php endif; ?>
            <?php if (!empty($autoInitMessage)): ?>
                <br><strong style="color:#4ade80;">✓ <?= htmlspecialchars($autoInitMessage) ?></strong>
            <?php endif; ?>
            <br><span style="color:#b8860b;">⚠️ Dynamic relationship data is subject to CHIM Paradox Prevention. Save your game to preserve changes.</span>
        </small>

        <label style="display:flex; align-items:center; gap:8px; margin:4px 0 10px; color:#fcd34d; font-size:0.9em; cursor:pointer;">
            <input type="checkbox" id="rel-lock-checkbox" <?= !empty($extendedData['relationships_locked']) ? 'checked' : '' ?>>
            🔒 Lock these relationships — stop the AI relationship model from changing them (your manual edits stay put)
        </label>
        <input type="hidden" name="relationships_locked" id="relationships_locked_hidden" value="<?= !empty($extendedData['relationships_locked']) ? '1' : '0' ?>">

        <div id="rel-editor-container" style="margin-top:12px;">
            <?php if (empty($jsonbRelationships)): ?>
                <p id="rel-empty-msg" style="color:#666; font-style:italic;">No relationships tracked yet. Use "Build with AI" or add manually below.</p>
            <?php else: ?>
                <table id="rel-table" style="width:100%; border-collapse:collapse; font-size:0.9em;">
                    <thead>
                        <tr style="border-bottom:1px solid #4a4a4a;">
                            <th style="text-align:left; padding:6px; color:#888;">Target</th>
                            <th style="text-align:center; padding:6px; color:#888;">Affinity</th>
                            <th style="text-align:center; padding:6px; color:#888;">Tier</th>
                            <th style="text-align:center; padding:6px; color:#888;">Type</th>
                            <th style="text-align:left; padding:6px; color:#888;">Signals</th>
                            <th style="text-align:center; padding:6px; color:#888; width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody id="rel-tbody">
                        <?php foreach ($jsonbRelationships as $target => $data):
                            $aff = $data['aff'] ?? 0;
                            $type = $data['type'] ?? 'neutral';
                            $relation = $data['relation'] ?? '';
                            $note = $data['note'] ?? '';
                            $best = $data['best'] ?? '';
                            $worst = $data['worst'] ?? '';
                            $customInfo = $data['custom_info'] ?? '';
                            $bestDelta = $data['best_delta'] ?? 0;
                            $worstDelta = $data['worst_delta'] ?? 0;
                            $tier = RelationshipManager::getTierLabel($aff);
                            $tierColor = $tierColors[$tier] ?? '#e5e7eb';
                            $typeIcon = $typeIcons[$type] ?? '➖';
                            $hasExtended = !empty($relation) || !empty($note) || !empty($best) || !empty($worst) || !empty($customInfo);
                        ?>
                        <tr class="rel-row" data-target="<?= htmlspecialchars($target) ?>"
                            data-relation="<?= htmlspecialchars($relation) ?>"
                            data-note="<?= htmlspecialchars($note) ?>"
                            data-best="<?= htmlspecialchars($best) ?>"
                            data-worst="<?= htmlspecialchars($worst) ?>"
                            data-best-delta="<?= $bestDelta ?>"
                            data-worst-delta="<?= $worstDelta ?>"
                            style="border-bottom:1px solid #333;">
                            <td style="padding:8px;">
                                <input type="text" class="rel-target" value="<?= htmlspecialchars($target) ?>"
                                       style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px 8px; width:120px;">
                            </td>
                            <td style="padding:8px; text-align:center;">
                                <input type="number" class="rel-aff" value="<?= $aff ?>" min="-100" max="100"
                                       style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px; width:60px; text-align:center;"
                                       onchange="updateRelTier(this)">
                            </td>
                            <td style="padding:8px; text-align:center;">
                                <span class="rel-tier" style="color:<?= $tierColor ?>; font-weight:500;"><?= $tier ?></span>
                            </td>
                            <td style="padding:8px; text-align:center;">
                                <select class="rel-type" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px;">
                                    <?php foreach ($typeIcons as $t => $icon): ?>
                                        <option value="<?= $t ?>" <?= $t === $type ? 'selected' : '' ?>><?= $icon ?> <?= ucfirst($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="padding:8px;">
                                <?php if (!empty($note)): ?>
                                    <div style="color:#d1d5db; font-size:0.82em; margin-bottom:3px;" title="<?= htmlspecialchars($note) ?>">
                                        Last: <?= htmlspecialchars(mb_strimwidth($note, 0, 56, '...')) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($best)): ?>
                                    <div style="color:#4ade80; font-size:0.82em; margin-bottom:3px;" title="<?= htmlspecialchars($best) ?>">
                                        Best <?= $bestDelta > 0 ? '+' . intval($bestDelta) : intval($bestDelta) ?>: <?= htmlspecialchars(mb_strimwidth($best, 0, 48, '...')) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($worst)): ?>
                                    <div style="color:#f87171; font-size:0.82em;" title="<?= htmlspecialchars($worst) ?>">
                                        Worst <?= intval($worstDelta) ?>: <?= htmlspecialchars(mb_strimwidth($worst, 0, 48, '...')) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($note) && empty($best) && empty($worst)): ?>
                                    <span style="color:#666; font-size:0.82em;">No signals</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px; text-align:center; white-space:nowrap;">
                                <textarea class="rel-custom-info" hidden><?= htmlspecialchars($customInfo) ?></textarea>
                                <button type="button" class="rel-details" title="Edit details (relation, notes, events)"
                                        style="background:transparent; border:none; color:<?= $hasExtended ? '#fde68a' : '#666' ?>; cursor:pointer; font-size:1em; margin-right:4px;"
                                        onclick="openDetailsModal(this)">✏️</button>
                                <button type="button" class="rel-delete" title="Remove relationship"
                                        style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.2em;"
                                        onclick="removeRelRow(this)">×</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Add new relationship -->
            <div style="margin-top:12px; padding-top:12px; border-top:1px solid #333;">
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="text" id="new-rel-target" placeholder="Target name (e.g., Player, Lydia)"
                           style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px 10px; flex:1; min-width:150px;">
                    <input type="number" id="new-rel-aff" value="0" min="-100" max="100" placeholder="Affinity"
                           style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px; width:70px; text-align:center;">
                    <select id="new-rel-type" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:6px;">
                        <?php foreach ($typeIcons as $t => $icon): ?>
                            <option value="<?= $t ?>"><?= $icon ?> <?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="addRelRow()"
                            style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:rgb(242, 124, 17); padding:6px 12px; cursor:pointer; font-weight:500;">
                        + Add
                    </button>
                </div>
            </div>

            <!-- Quick actions -->
            <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" id="btn-build-ai" onclick="openBuildModal()"
                        style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#86efac; padding:6px 12px; cursor:pointer; font-size:0.85em;">
                    🤖 Build with AI
                </button>
                <button type="button" onclick="openCustomTypeModal()"
                        style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#fde68a; padding:6px 12px; cursor:pointer; font-size:0.85em;">
                    🏷️ Add Custom Type
                </button>
                <button type="button" onclick="clearAllRelationships()"
                        style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#ef4444; padding:6px 12px; cursor:pointer; font-size:0.85em;">
                    🗑️ Clear All
                </button>
            </div>

            <!-- Build with AI Modal -->
            <div id="build-ai-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000; align-items:center; justify-content:center;">
                <div style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:8px; padding:20px; max-width:500px; width:90%;">
                    <h3 style="margin:0 0 12px 0; color:rgb(242, 124, 17);">🤖 Build Relationships with AI</h3>
                    <p style="color:#888; font-size:0.9em; margin-bottom:12px;">
                        Uses recent event history involving this NPC to infer affinity scores, relationship types, and optional notes.
                    </p>
                    <div style="background:#3a2a0a; border:1px solid #b8860b; border-radius:6px; color:#fde68a; padding:10px 12px; margin-bottom:12px; font-size:0.88em; line-height:1.35;">
                        <strong>Merge warning:</strong> AI results are merged into the current table. Existing entries with the same target name may be overwritten.
                    </div>
                    <label style="color:#ccc; font-size:0.85em;">Direction (optional):</label>
                    <textarea id="build-ai-direction" placeholder="e.g., 'Focus on military hierarchy', 'Assume hostility toward Stormcloaks', 'This NPC is suspicious of strangers'"
                              style="width:100%; height:80px; margin-top:4px; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px; resize:vertical;"></textarea>
                    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" onclick="closeBuildModal()"
                                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#888; padding:8px 16px; cursor:pointer;">
                            Cancel
                        </button>
                        <button type="button" id="btn-build-confirm" onclick="buildWithAI()"
                                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#86efac; padding:8px 16px; cursor:pointer; font-weight:500;">
                            🤖 Build
                        </button>
                    </div>
                </div>
            </div>

            <!-- Custom Type Modal -->
            <div id="custom-type-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000; align-items:center; justify-content:center;">
                <div style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:8px; padding:20px; max-width:400px; width:90%;">
                    <h3 style="margin:0 0 12px 0; color:rgb(242, 124, 17);">🏷️ Add Custom Relationship Type</h3>
                    <p style="color:#888; font-size:0.9em; margin-bottom:12px;">
                        Create a custom type (e.g., "client", "mentor", "servant"). The AI can later change to this type using #TYPE: commands.
                    </p>
                    <div style="margin-bottom:12px;">
                        <label style="color:#ccc; font-size:0.85em;">Type Name (one word):</label>
                        <input type="text" id="custom-type-name" placeholder="e.g., client"
                               style="width:100%; margin-top:4px; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="color:#ccc; font-size:0.85em;">Emoji:</label>
                        <div id="emoji-picker" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; max-height:120px; overflow-y:auto;">
                            <?php
                            $emojis = ['💰', '👑', '🎭', '🗡️', '🛡️', '📜', '🍺', '💀', '🔥', '❄️', '⚡', '🌙', '☀️', '🏹', '🧙', '👸', '🤴', '🧝', '🐉', '🦊', '🐺', '🦅', '🏰', '⚔️', '🎪', '🎵', '📿', '💎', '🗝️', '🏆'];
                            foreach ($emojis as $emoji):
                            ?>
                            <button type="button" class="emoji-btn" onclick="selectEmoji('<?= $emoji ?>')"
                                    style="background:#262626; border:1px solid #4a4a4a; border-radius:4px; padding:8px; cursor:pointer; font-size:1.2em;">
                                <?= $emoji ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="custom-type-emoji" value="🏷️">
                        <div style="margin-top:8px; color:#888; font-size:0.85em;">Selected: <span id="selected-emoji">🏷️</span></div>
                    </div>
                    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" onclick="closeCustomTypeModal()"
                                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#888; padding:8px 16px; cursor:pointer;">
                            Cancel
                        </button>
                        <button type="button" onclick="addCustomType()"
                                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#fde68a; padding:8px 16px; cursor:pointer; font-weight:500;">
                            Add Type
                        </button>
                    </div>
                </div>
            </div>

            <!-- Relationship Details Modal -->
            <div id="rel-details-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000; align-items:center; justify-content:center;">
                <div style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:8px; padding:20px; max-width:500px; width:90%; max-height:90vh; overflow-y:auto;">
                    <h3 style="margin:0 0 8px 0; color:rgb(242, 124, 17);">✏️ Details: <span id="details-target-name"></span></h3>
                    <p style="color:#888; font-size:0.8em; margin-bottom:12px;">
                        ⚠️ Relationship details and memories are used by the AI. Custom Info is player-only and is never sent to or changed by relationship AI.
                    </p>

                    <!-- Relationship Detail (specific role) -->
                    <div style="margin-bottom:10px;">
                        <label style="color:#ccc; font-size:0.85em;">Relationship Detail</label>
                        <div style="display:flex; gap:6px; margin-top:4px;">
                            <input type="text" id="details-relation" placeholder="son, ex-wife, employer"
                                   style="flex:1; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px;">
                            <button type="button" onclick="showRelationSuggestions()" title="Common suggestions"
                                    style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#888; padding:4px 8px; cursor:pointer; font-size:0.9em;">
                                +
                            </button>
                        </div>
                        <div id="relation-suggestions" style="display:none; margin-top:6px; flex-wrap:wrap; gap:4px;">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Recent Note -->
                    <div style="margin-bottom:10px;">
                        <label style="color:#ccc; font-size:0.85em;">Recent Interaction</label>
                        <input type="text" id="details-note" placeholder="shared a drink, had argument"
                               style="width:100%; margin-top:4px; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px;">
                    </div>

                    <!-- Best Event -->
                    <div style="margin-bottom:10px;">
                        <label style="color:#86efac; font-size:0.85em;">Best Memory</label>
                        <input type="text" id="details-best" placeholder="opened gate for Ulfric, saved life"
                               style="width:100%; margin-top:4px; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px;">
                    </div>

                    <!-- Worst Event -->
                    <div style="margin-bottom:10px;">
                        <label style="color:#ef4444; font-size:0.85em;">Worst Memory</label>
                        <input type="text" id="details-worst" placeholder="killed his brother, betrayed trust"
                               style="width:100%; margin-top:4px; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px;">
                    </div>

                    <!-- Player-only custom notes -->
                    <div style="margin-bottom:10px;">
                        <label for="details-custom-info" style="color:#ccc; font-size:0.85em;">Custom Info</label>
                        <textarea id="details-custom-info" rows="3" placeholder="Write any player notes for this relationship"
                                  style="width:100%; margin-top:4px; resize:vertical; background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:8px;"></textarea>
                    </div>

                    <!-- Hidden field to track which row we're editing -->
                    <input type="hidden" id="details-row-target">

                    <div style="margin-top:14px; display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" onclick="closeDetailsModal()"
                                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#ef4444; padding:8px 16px; cursor:pointer;">
                            Cancel
                        </button>
                        <button type="button" onclick="saveDetails()"
                                style="background:#2a2a2a; border:1px solid #4a4a4a; border-radius:4px; color:#86efac; padding:8px 16px; cursor:pointer; font-weight:500;">
                            Save
                        </button>
                    </div>
                </div>
            </div>

            <!-- Status message -->
            <div id="rel-status" style="margin-top:8px; font-size:0.85em; display:none;"></div>
        </div>

        <!-- Hidden fields -->
        <input type="hidden" name="relationships_jsonb" id="relationships_jsonb" value="<?= htmlspecialchars(json_encode($jsonbRelationships)) ?>">
        <input type="hidden" id="rel-npc-name" value="<?= htmlspecialchars($npcName) ?>">
        <input type="hidden" id="rel-npc-id" value="<?= htmlspecialchars($editItem['id'] ?? '') ?>">
    </details>
</div>

<script>
// Tier calculation (matches PHP RelationshipManager::getTierLabel)
// 11 Tiers with BELL CURVE distribution - extremes are hard to reach
function getTierLabel(score) {
    if (score >= 91) return "Bonded";      // +91 to +100 (10 pts)
    if (score >= 76) return "Devoted";     // +76 to +90  (15 pts)
    if (score >= 56) return "Fond";        // +56 to +75  (20 pts)
    if (score >= 31) return "Friendly";    // +31 to +55  (25 pts)
    if (score >= 6) return "Acquaintance"; // +6 to +30   (25 pts)
    if (score >= -5) return "Neutral";     // -5 to +5    (11 pts)
    if (score >= -30) return "Wary";       // -30 to -6   (25 pts)
    if (score >= -55) return "Cold";       // -55 to -31  (25 pts)
    if (score >= -75) return "Resentful";  // -75 to -56  (20 pts)
    if (score >= -90) return "Hateful";    // -90 to -76  (15 pts)
    return "Hostile";                      // -100 to -91 (10 pts)
}

const tierColors = {
    'Bonded': '#22c55e',
    'Devoted': '#4ade80',
    'Fond': '#86efac',
    'Friendly': '#a7f3d0',
    'Acquaintance': '#d9f99d',
    'Neutral': '#e5e7eb',
    'Wary': '#fde68a',
    'Cold': '#fed7aa',
    'Resentful': '#fca5a5',
    'Hateful': '#f87171',
    'Hostile': '#ef4444'
};

const typeIcons = {
    // Classic types
    'romantic': '💘',
    'platonic': '🤝',
    'familial': '👨‍👩‍👧',
    'professional': '💼',
    'rival': '⚔️',
    'enemy': '🗡️',
    'neutral': '➖',
    // Extended types
    'nemesis': '☠️',
    'estranged': '💔',
    'transactional': '💰',
    'protective': '🛡️',
    'indebted': '🙏',
    'fanatical': '🔥',
    'mentor': '📚',
    'student': '🎓',
    'servant': '🧹',
    'client': '🛒',
    'patron': '👑',
    'crush': '💗',
    'ex': '💢',
    'betrayed': '🔪',
    'suspicious': '👀',
    'admirer': '⭐',
    'jealous': '💚',
    'fearful': '😨',
    'obsessed': '🌀',
    'awed': '😲',
    'contempt': '😤',
    'pitying': '😢',
    'grateful': '🥹',
    'curious': '🧐',
    'dismissive': '🙄'
};

function showStatus(msg, color = '#888') {
    const status = document.getElementById('rel-status');
    status.textContent = msg;
    status.style.color = color;
    status.style.display = 'block';
}

function hideStatus() {
    document.getElementById('rel-status').style.display = 'none';
}

function updateRelTier(input) {
    const row = input.closest('tr');
    const tierSpan = row.querySelector('.rel-tier');
    const aff = parseInt(input.value) || 0;
    const tier = getTierLabel(aff);
    tierSpan.textContent = tier;
    tierSpan.style.color = tierColors[tier] || '#e5e7eb';
    syncRelationshipsToHidden();
}

function removeRelRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    syncRelationshipsToHidden();
}

function addRelRow() {
    const target = document.getElementById('new-rel-target').value.trim();
    const aff = parseInt(document.getElementById('new-rel-aff').value) || 0;
    const type = document.getElementById('new-rel-type').value;

    if (!target) {
        alert('Please enter a target name');
        return;
    }

    // Check if target already exists
    const existing = document.querySelector(`.rel-row[data-target="${target}"]`);
    if (existing) {
        alert(`Relationship with ${target} already exists. Edit it in the table above.`);
        return;
    }

    const tier = getTierLabel(aff);
    const tierColor = tierColors[tier] || '#e5e7eb';

    // Create table if it doesn't exist
    let tbody = document.getElementById('rel-tbody');
    if (!tbody) {
        const container = document.getElementById('rel-editor-container');
        const emptyMsg = document.getElementById('rel-empty-msg');
        if (emptyMsg) emptyMsg.remove();

        const tableHtml = `
            <table id="rel-table" style="width:100%; border-collapse:collapse; font-size:0.9em;">
                <thead>
                    <tr style="border-bottom:1px solid #4a4a4a;">
                        <th style="text-align:left; padding:6px; color:#888;">Target</th>
                        <th style="text-align:center; padding:6px; color:#888;">Affinity</th>
                        <th style="text-align:center; padding:6px; color:#888;">Tier</th>
                        <th style="text-align:center; padding:6px; color:#888;">Type</th>
                        <th style="text-align:center; padding:6px; color:#888; width:40px;"></th>
                    </tr>
                </thead>
                <tbody id="rel-tbody"></tbody>
            </table>
        `;
        container.insertAdjacentHTML('afterbegin', tableHtml);
        tbody = document.getElementById('rel-tbody');
    }

    const row = document.createElement('tr');
    row.className = 'rel-row';
    row.dataset.target = target;
    row.dataset.relation = '';
    row.dataset.note = '';
    row.dataset.best = '';
    row.dataset.worst = '';
    row.dataset.bestDelta = '0';
    row.dataset.worstDelta = '0';
    row.style.borderBottom = '1px solid #333';
    row.innerHTML = `
        <td style="padding:8px;">
            <input type="text" class="rel-target" value="${escapeHtml(target)}"
                   style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px 8px; width:120px;">
        </td>
        <td style="padding:8px; text-align:center;">
            <input type="number" class="rel-aff" value="${aff}" min="-100" max="100"
                   style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px; width:60px; text-align:center;"
                   onchange="updateRelTier(this)">
        </td>
        <td style="padding:8px; text-align:center;">
            <span class="rel-tier" style="color:${tierColor}; font-weight:500;">${tier}</span>
        </td>
        <td style="padding:8px; text-align:center;">
            <select class="rel-type" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px;">
                ${Object.entries(typeIcons).map(([t, icon]) =>
                    `<option value="${t}" ${t === type ? 'selected' : ''}>${icon} ${t.charAt(0).toUpperCase() + t.slice(1)}</option>`
                ).join('')}
            </select>
        </td>
        <td style="padding:8px; text-align:center; white-space:nowrap;">
            <textarea class="rel-custom-info" hidden></textarea>
            <button type="button" class="rel-details" title="Edit details (relation, notes, events)"
                    style="background:transparent; border:none; color:#666; cursor:pointer; font-size:1em; margin-right:4px;"
                    onclick="openDetailsModal(this)">✏️</button>
            <button type="button" class="rel-delete" title="Remove relationship"
                    style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.2em;"
                    onclick="removeRelRow(this)">×</button>
        </td>
    `;
    tbody.appendChild(row);

    // Clear inputs
    document.getElementById('new-rel-target').value = '';
    document.getElementById('new-rel-aff').value = '0';
    document.getElementById('new-rel-type').value = 'neutral';

    syncRelationshipsToHidden();
}

function syncRelationshipsToHidden() {
    const rows = document.querySelectorAll('.rel-row');
    const relationships = {};

    rows.forEach(row => {
        const target = row.querySelector('.rel-target').value.trim();
        const aff = parseInt(row.querySelector('.rel-aff').value) || 0;
        const type = row.querySelector('.rel-type').value;

        if (target) {
            const rel = { aff: aff, type: type };

            // Include extended fields if they exist
            const relation = row.dataset.relation || '';
            const note = row.dataset.note || '';
            const best = row.dataset.best || '';
            const worst = row.dataset.worst || '';
            const customInfo = row.querySelector('.rel-custom-info')?.value.trim() || '';
            const bestDelta = parseInt(row.dataset.bestDelta) || 0;
            const worstDelta = parseInt(row.dataset.worstDelta) || 0;

            if (relation) rel.relation = relation;
            if (note) rel.note = note;
            if (best) {
                rel.best = best;
                if (bestDelta) rel.best_delta = bestDelta;
            }
            if (worst) {
                rel.worst = worst;
                if (worstDelta) rel.worst_delta = worstDelta;
            }
            if (customInfo) rel.custom_info = customInfo;

            relationships[target] = rel;
        }
    });

    document.getElementById('relationships_jsonb').value = JSON.stringify(relationships);

    // Keep the relationship-lock hidden field in sync with the checkbox so it always submits 0/1 (even when unchecked).
    const lockCb = document.getElementById('rel-lock-checkbox');
    const lockHidden = document.getElementById('relationships_locked_hidden');
    if (lockCb && lockHidden) { lockHidden.value = lockCb.checked ? '1' : '0'; }
}

function getCurrentRelationshipsFromHidden() {
    const hidden = document.getElementById('relationships_jsonb');
    if (!hidden || !hidden.value.trim()) {
        return {};
    }

    try {
        const parsed = JSON.parse(hidden.value);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (e) {
        console.warn('Failed to parse current relationships JSON before AI merge:', e);
        return {};
    }
}

// Details Modal Functions
const relationSuggestions = [
    // Family
    'son', 'daughter', 'father', 'mother', 'brother', 'sister', 'spouse',
    'uncle', 'aunt', 'cousin', 'grandparent', 'in-law', 'stepchild',
    // Professional
    'employer', 'employee', 'apprentice', 'partner', 'supplier', 'client',
    // Social
    'ex-wife', 'ex-husband', 'betrothed', 'ward', 'guardian', 'liege', 'vassal'
];

function showRelationSuggestions() {
    const container = document.getElementById('relation-suggestions');
    if (container.style.display === 'flex') {
        container.style.display = 'none';
        return;
    }

    container.innerHTML = relationSuggestions.map(s =>
        `<button type="button" onclick="selectRelationSuggestion('${s}')"
                 style="background:#262626; border:1px solid #4a4a4a; border-radius:4px; color:#ccc; padding:4px 8px; cursor:pointer; font-size:0.8em;">${s}</button>`
    ).join('');
    container.style.display = 'flex';
}

function selectRelationSuggestion(value) {
    document.getElementById('details-relation').value = value;
    document.getElementById('relation-suggestions').style.display = 'none';
}

function openDetailsModal(btn) {
    const row = btn.closest('tr');
    const target = row.querySelector('.rel-target').value;

    // Populate modal with current values from data attributes
    document.getElementById('details-target-name').textContent = target;
    document.getElementById('details-row-target').value = target;
    document.getElementById('details-relation').value = row.dataset.relation || '';
    document.getElementById('details-note').value = row.dataset.note || '';
    document.getElementById('details-best').value = row.dataset.best || '';
    document.getElementById('details-worst').value = row.dataset.worst || '';
    document.getElementById('details-custom-info').value = row.querySelector('.rel-custom-info')?.value || '';

    // Hide suggestions when opening
    document.getElementById('relation-suggestions').style.display = 'none';

    document.getElementById('rel-details-modal').style.display = 'flex';
}

function closeDetailsModal() {
    document.getElementById('rel-details-modal').style.display = 'none';
}

function saveDetails() {
    const targetName = document.getElementById('details-row-target').value;
    const row = document.querySelector(`.rel-row input.rel-target[value="${targetName}"]`)?.closest('tr');

    if (!row) {
        // Try finding by current target input value
        const rows = document.querySelectorAll('.rel-row');
        for (const r of rows) {
            if (r.querySelector('.rel-target').value === targetName) {
                saveDetailsToRow(r);
                return;
            }
        }
        alert('Could not find the relationship row to update');
        return;
    }

    saveDetailsToRow(row);
}

function saveDetailsToRow(row) {
    // Get values from modal
    const relation = document.getElementById('details-relation').value;
    const note = document.getElementById('details-note').value.trim();
    const best = document.getElementById('details-best').value.trim();
    const worst = document.getElementById('details-worst').value.trim();
    const customInfo = document.getElementById('details-custom-info').value.trim();

    // Update row data attributes
    row.dataset.relation = relation;
    row.dataset.note = note;
    row.dataset.best = best;
    row.dataset.worst = worst;
    row.querySelector('.rel-custom-info').value = customInfo;

    // Update the details button color to indicate data exists
    const detailsBtn = row.querySelector('.rel-details');
    const hasData = relation || note || best || worst || customInfo;
    detailsBtn.style.color = hasData ? '#fde68a' : '#666';

    // Sync to hidden field
    syncRelationshipsToHidden();

    closeDetailsModal();
    showStatus('✅ Details saved. Click "Save NPC" to store permanently.', '#86efac');
}

// Modal functions
function openBuildModal() {
    document.getElementById('build-ai-modal').style.display = 'flex';
}

function closeBuildModal() {
    document.getElementById('build-ai-modal').style.display = 'none';
    document.getElementById('build-ai-direction').value = '';
}

function openCustomTypeModal() {
    document.getElementById('custom-type-modal').style.display = 'flex';
}

function closeCustomTypeModal() {
    document.getElementById('custom-type-modal').style.display = 'none';
    document.getElementById('custom-type-name').value = '';
    document.getElementById('custom-type-emoji').value = '🏷️';
    document.getElementById('selected-emoji').textContent = '🏷️';
}

function selectEmoji(emoji) {
    document.getElementById('custom-type-emoji').value = emoji;
    document.getElementById('selected-emoji').textContent = emoji;
    // Highlight selected
    document.querySelectorAll('.emoji-btn').forEach(btn => {
        btn.style.border = btn.textContent.trim() === emoji ? '2px solid #fde68a' : '1px solid #4a4a4a';
    });
}

function addCustomType() {
    const name = document.getElementById('custom-type-name').value.trim().toLowerCase();
    const emoji = document.getElementById('custom-type-emoji').value;

    if (!name) {
        alert('Please enter a type name');
        return;
    }

    // Validate single word
    if (name.includes(' ')) {
        alert('Type name should be a single word (no spaces)');
        return;
    }

    // Check if already exists
    if (typeIcons[name]) {
        alert(`Type "${name}" already exists`);
        return;
    }

    // Add to typeIcons
    typeIcons[name] = emoji;

    // Update all dropdowns on the page
    document.querySelectorAll('.rel-type, #new-rel-type').forEach(select => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = `${emoji} ${name.charAt(0).toUpperCase() + name.slice(1)}`;
        select.appendChild(option);
    });

    showStatus(`✅ Added custom type: ${emoji} ${name}`, '#fde68a');
    closeCustomTypeModal();
}

async function buildWithAI() {
    const direction = document.getElementById('build-ai-direction').value.trim();
    const npcName = document.getElementById('rel-npc-name').value;
    const npcId = document.getElementById('rel-npc-id').value;
    const btn = document.getElementById('btn-build-confirm');
    const originalText = btn.textContent;

    closeBuildModal();

    try {
        document.getElementById('btn-build-ai').disabled = true;
        document.getElementById('btn-build-ai').textContent = '⏳ Building...';
        showStatus('Analyzing recent event history...', '#86efac');

        const formData = new FormData();
        formData.append('npc_id', npcId);
        formData.append('npc_name', npcName);
        formData.append('source', 'events');
        formData.append('event_limit', '200');
        if (direction) {
            formData.append('direction', direction);
        }
        // Send custom types so AI knows about them
        const customTypes = Object.keys(typeIcons).filter(t => !['romantic', 'platonic', 'familial', 'professional', 'rival', 'enemy', 'neutral'].includes(t));
        if (customTypes.length > 0) {
            formData.append('custom_types', JSON.stringify(customTypes));
        }

        const response = await fetch('../../ext/relationship_system/analyze_relationships.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.ok && result.relationships) {
            const currentRelationships = getCurrentRelationshipsFromHidden();
            const mergedRelationships = { ...currentRelationships };
            for (const [target, relationship] of Object.entries(result.relationships)) {
                const customInfo = currentRelationships[target]?.custom_info || '';
                const aiRelationship = { ...relationship };
                delete aiRelationship.custom_info;
                mergedRelationships[target] = aiRelationship;
                if (customInfo) mergedRelationships[target].custom_info = customInfo;
            }

            // Update the hidden field
            document.getElementById('relationships_jsonb').value = JSON.stringify(mergedRelationships);

            // Rebuild the table
            rebuildRelTable(mergedRelationships);

            // Show success with model info
            let statusMsg = `✅ AI built ${result.count} relationship(s) from ${result.event_count || 0} event(s)`;
            if (result.model) {
                statusMsg += ` using ${result.model}`;
            }
            if (result.player_name && result.player_name !== 'the Player') {
                statusMsg += ` (Player: ${result.player_name})`;
            }
            statusMsg += `. Matching existing targets may have been overwritten. Click "Save NPC" to store.`;
            showStatus(statusMsg, '#86efac');
        } else {
            showStatus(`❌ Error: ${result.error || 'Unknown error'}`, '#ef4444');
            if (result.raw_response) {
                console.log('Raw AI response:', result.raw_response);
            }
        }
    } catch (e) {
        showStatus(`❌ Request failed: ${e.message}`, '#ef4444');
        console.error('AI build error:', e);
    } finally {
        document.getElementById('btn-build-ai').disabled = false;
        document.getElementById('btn-build-ai').textContent = '🤖 Build with AI';
    }
}

function rebuildRelTable(relationships) {
    const container = document.getElementById('rel-editor-container');

    // Remove old table/message
    const oldTable = document.getElementById('rel-table');
    const oldMsg = document.getElementById('rel-empty-msg');
    if (oldTable) oldTable.remove();
    if (oldMsg) oldMsg.remove();

    if (Object.keys(relationships).length === 0) {
        container.insertAdjacentHTML('afterbegin',
            '<p id="rel-empty-msg" style="color:#666; font-style:italic;">No relationships tracked yet.</p>');
        return;
    }

    let html = `
        <table id="rel-table" style="width:100%; border-collapse:collapse; font-size:0.9em;">
            <thead>
                <tr style="border-bottom:1px solid #4a4a4a;">
                    <th style="text-align:left; padding:6px; color:#888;">Target</th>
                    <th style="text-align:center; padding:6px; color:#888;">Affinity</th>
                    <th style="text-align:center; padding:6px; color:#888;">Tier</th>
                    <th style="text-align:center; padding:6px; color:#888;">Type</th>
                    <th style="text-align:left; padding:6px; color:#888;">Signals</th>
                    <th style="text-align:center; padding:6px; color:#888; width:70px;"></th>
                </tr>
            </thead>
            <tbody id="rel-tbody">
    `;

    for (const [target, data] of Object.entries(relationships)) {
        const aff = data.aff || 0;
        const type = data.type || 'neutral';
        const relation = data.relation || '';
        const note = data.note || '';
        const best = data.best || '';
        const worst = data.worst || '';
        const customInfo = data.custom_info || '';
        const bestDelta = data.best_delta || 0;
        const worstDelta = data.worst_delta || 0;
        const tier = getTierLabel(aff);
        const tierColor = tierColors[tier] || '#e5e7eb';
        const hasExtended = relation || note || best || worst || customInfo;
        const detailsColor = hasExtended ? '#fde68a' : '#666';
        const signalHtml = buildRelationshipSignalHtml(note, best, worst, bestDelta, worstDelta);

        html += `
            <tr class="rel-row" data-target="${escapeHtml(target)}"
                data-relation="${escapeHtml(relation)}"
                data-note="${escapeHtml(note)}"
                data-best="${escapeHtml(best)}"
                data-worst="${escapeHtml(worst)}"
                data-best-delta="${bestDelta}"
                data-worst-delta="${worstDelta}"
                style="border-bottom:1px solid #333;">
                <td style="padding:8px;">
                    <input type="text" class="rel-target" value="${escapeHtml(target)}"
                           style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px 8px; width:120px;">
                </td>
                <td style="padding:8px; text-align:center;">
                    <input type="number" class="rel-aff" value="${aff}" min="-100" max="100"
                           style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px; width:60px; text-align:center;"
                           onchange="updateRelTier(this)">
                </td>
                <td style="padding:8px; text-align:center;">
                    <span class="rel-tier" style="color:${tierColor}; font-weight:500;">${tier}</span>
                </td>
                <td style="padding:8px; text-align:center;">
                    <select class="rel-type" style="background:#1a1a1a; border:1px solid #4a4a4a; border-radius:4px; color:#e9efff; padding:4px;">
                        ${Object.entries(typeIcons).map(([t, icon]) =>
                            `<option value="${t}" ${t === type ? 'selected' : ''}>${icon} ${t.charAt(0).toUpperCase() + t.slice(1)}</option>`
                        ).join('')}
                    </select>
                </td>
                <td style="padding:8px;">
                    ${signalHtml}
                </td>
                <td style="padding:8px; text-align:center; white-space:nowrap;">
                    <textarea class="rel-custom-info" hidden>${escapeHtml(customInfo)}</textarea>
                    <button type="button" class="rel-details" title="Edit details (relation, notes, events)"
                            style="background:transparent; border:none; color:${detailsColor}; cursor:pointer; font-size:1em; margin-right:4px;"
                            onclick="openDetailsModal(this)">✏️</button>
                    <button type="button" class="rel-delete" title="Remove relationship"
                            style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.2em;"
                            onclick="removeRelRow(this)">×</button>
                </td>
            </tr>
        `;
    }

    html += '</tbody></table>';
    container.insertAdjacentHTML('afterbegin', html);
}

function clearAllRelationships() {
    if (!confirm('Are you sure you want to clear all relationships? This will take effect when you save the NPC.')) {
        return;
    }

    document.getElementById('relationships_jsonb').value = '{}';
    rebuildRelTable({});
    hideStatus();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncateSignal(text, maxLen) {
    text = String(text || '');
    return text.length > maxLen ? text.slice(0, Math.max(0, maxLen - 3)) + '...' : text;
}

function buildRelationshipSignalHtml(note, best, worst, bestDelta, worstDelta) {
    const parts = [];
    if (note) {
        parts.push(`<div style="color:#d1d5db; font-size:0.82em; margin-bottom:3px;" title="${escapeHtml(note)}">Last: ${escapeHtml(truncateSignal(note, 56))}</div>`);
    }
    if (best) {
        const bestNum = Number(bestDelta) || 0;
        const bestSign = bestNum > 0 ? '+' + bestNum : String(bestNum);
        parts.push(`<div style="color:#4ade80; font-size:0.82em; margin-bottom:3px;" title="${escapeHtml(best)}">Best ${bestSign}: ${escapeHtml(truncateSignal(best, 48))}</div>`);
    }
    if (worst) {
        const worstNum = Number(worstDelta) || 0;
        parts.push(`<div style="color:#f87171; font-size:0.82em;" title="${escapeHtml(worst)}">Worst ${worstNum}: ${escapeHtml(truncateSignal(worst, 48))}</div>`);
    }
    return parts.length ? parts.join('') : '<span style="color:#666; font-size:0.82em;">No signals</span>';
}

// Sync while editing and again at submit time so the hidden JSON cannot go stale.
['input', 'change'].forEach(function(evtName) {
    document.addEventListener(evtName, function(e) {
        if (e.target.closest('#relationship-editor-section')) {
            syncRelationshipsToHidden();
        }
    });
});

document.addEventListener('submit', function(e) {
    if (e.target && e.target.querySelector && e.target.querySelector('#relationship-editor-section')) {
        syncRelationshipsToHidden();
    }
}, true);

// Initial sync
syncRelationshipsToHidden();
</script>
