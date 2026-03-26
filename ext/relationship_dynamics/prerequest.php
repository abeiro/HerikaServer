<?php
/**
 * Relationship Dynamics — Prerequest Hook
 *
 * Runs after other extension prerequest hooks (alphabetical order).
 * Handles: passion decay, jealousy decay, reunion spike,
 * effective disposition calculation, blush multiplier.
 */

if (!isset($GLOBALS['gameRequest']) || !is_array($GLOBALS['gameRequest'])) {
    return;
}

// Skip non-dialogue events
$reqType = $GLOBALS['gameRequest'][0] ?? '';
if ($reqType === 'maras_sync') {
    return;
}

// Skip NPC-to-NPC radiant dialogue — player isn't involved.
// CHIM core's relationship_system handles NPC↔NPC affinity.
// Ambient trickle/decay would be wasted work since these NPCs
// aren't interacting with the player.
$radiantTypes = ['radiant', 'radiantsearchingfriend', 'radiantsearchinghostile',
    'radiantcombathostile', 'minai_force_rechat'];
if (in_array($reqType, $radiantTypes)) {
    return;
}

// Skip narrator
$npcName = $GLOBALS['HERIKA_NAME'] ?? '';
if (empty($npcName) || $npcName === 'The Narrator') {
    return;
}

require_once __DIR__ . '/relationship_dynamics.php';

if (!RelationshipDynamics::isEnabled()) {
    return;
}

// Load dynamics
$dynamics = RelationshipDynamics::getDynamics($npcName);

// Auto-generate love language if missing
RelationshipDynamics::ensureLoveLanguage($npcName, $dynamics);

// Auto-generate interest vector if missing (~8ms, fires once per NPC)
RelationshipDynamics::getInterestVector($dynamics);

// Load config toggles
$reldynCfg = RelationshipDynamics::getConfig();

// Apply time-based decays
if ($reldynCfg['passion_enabled'] ?? true) {
    RelationshipDynamics::decayPassion($dynamics);
}
if ($reldynCfg['jealousy_enabled'] ?? true) {
    RelationshipDynamics::decayJealousy($dynamics);
}

// Check reunion spike
$npcAffection = 0; // Default — no relationship means no reunion spike
try {
    $db = $GLOBALS['db'] ?? null;
    if ($db) {
        $playerName = trim($GLOBALS['PLAYER_NAME'] ?? 'Player');
        $escaped = $db->escape($npcName);
        $row = $db->fetchOne("SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}') LIMIT 1");
        if (is_array($row) && !empty($row['extended_data'])) {
            $ext = json_decode($row['extended_data'], true) ?: [];
            $playerRel = $ext['relationships'][$playerName] ?? null;
            if ($playerRel) {
                // Use raw CHIM affinity (-100..+100) directly
                // reunion_min_affection default 40 means CHIM aff >= 40 (Friendly+)
                $npcAffection = intval($playerRel['aff'] ?? 0);
            }
        }
    }
} catch (Throwable $e) {
    // Use default
}

// Snapshot current CHIM affinity for RPM→Speed delta in postrequest
$GLOBALS['RELDYN_PRE_AFF'] = null;
try {
    $db2 = $GLOBALS['db'] ?? null;
    if ($db2) {
        $playerName2 = trim($GLOBALS['PLAYER_NAME'] ?? 'Player');
        $escaped2 = $db2->escape($npcName);
        $row2 = $db2->fetchOne("SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped2}') LIMIT 1");
        if (is_array($row2) && !empty($row2['extended_data'])) {
            $ext2 = json_decode($row2['extended_data'], true) ?: [];
            $pRel = $ext2['relationships'][$playerName2] ?? null;
            if ($pRel) {
                $GLOBALS['RELDYN_PRE_AFF'] = intval($pRel['aff'] ?? 0);
            }
        }
    }
} catch (Throwable $e) {
    // Best effort
}

$reunionPassion = ($reldynCfg['reunion_enabled'] ?? true) ? RelationshipDynamics::checkReunion($dynamics, $npcAffection) : 0;
if ($reunionPassion > 0) {
    RelationshipDynamics::addPassion($dynamics, $reunionPassion, 'reunion');
}

// -------------------------------------------------------------------------
// Ambient presence: being in a location matching NPC interests builds
// warmth passively and resists decay. No interactions needed.
// -------------------------------------------------------------------------
$ambientResult = ($reldynCfg['ambient_enabled'] ?? true) ? RelationshipDynamics::detectCurrentInterest($dynamics) : null;
$ambientInterest = is_array($ambientResult) ? ($ambientResult['interest'] ?? null) : $ambientResult;
$ambientResonance = is_array($ambientResult) ? ($ambientResult['resonance'] ?? 0.0) : 0.0;
$ambientSource = is_array($ambientResult) ? ($ambientResult['source'] ?? 'none') : 'none';
$ambientLocation = is_array($ambientResult) ? ($ambientResult['location'] ?? '') : '';

// Store for context.php and postrequest.php to use
$GLOBALS['RELDYN_AMBIENT_INTEREST'] = $ambientInterest;
$GLOBALS['RELDYN_AMBIENT_RESONANCE'] = $ambientResonance;
$GLOBALS['RELDYN_AMBIENT_LOCATION'] = $ambientLocation;
$GLOBALS['RELDYN_AMBIENT_SOURCE'] = $ambientSource;

if ($ambientInterest && $ambientResonance >= 0.15) {
    // Use resonance score directly for vector path, or interest multiplier for keyword path
    if ($ambientSource === 'vector') {
        // Vector resonance: scale 0.15-0.65 → multiplier 1.1-2.0
        $ambientMult = 1.0 + min(1.0, ($ambientResonance - 0.15) * 2.0);
    } else {
        // Keyword fallback: use NPC's interest slider value
        $interests = RelationshipDynamics::getInterests($dynamics);
        $ambientMult = floatval($interests[$ambientInterest] ?? 1.0);
    }

    if ($ambientMult > 1.0) {
        // CEILING — trickle builds up to this, then stops
        // Aela in wilderness (resonance 0.61): ceiling ≈ 19
        // Ashe in Dwemer ruin (resonance 0.65): ceiling ≈ 20
        $ambientCeiling = 10.0 * $ambientMult;
        $currentPassion = floatval($dynamics['passion'] ?? 0);

        // TRICKLE — passive gain, ~0.3/min at high resonance, stops at ceiling
        $lastAmbient = intval($dynamics['_ambient_updated_at'] ?? 0);
        $minutesSince = $lastAmbient > 0 ? (time() - $lastAmbient) / 60.0 : 0;
        if ($minutesSince > 0.5 && $currentPassion < $ambientCeiling) {
            $trickle = min($ambientCeiling - $currentPassion, 0.3 * ($ambientMult - 1.0) * $minutesSince);
            $dynamics['passion'] = $currentPassion + $trickle;
            $dynamics['_ambient_updated_at'] = time();
            if ($trickle > 0.01) {
                RelationshipDynamics::log("Ambient trickle: {$npcName} @ '{$ambientLocation}' ({$ambientSource}, resonance=" . round($ambientResonance, 3) . ", mult={$ambientMult}x) +{" . round($trickle, 2) . "} passion=" . round($dynamics['passion'], 1) . " (ceiling=" . round($ambientCeiling, 0) . ")");
            }
        } elseif ($lastAmbient === 0) {
            $dynamics['_ambient_updated_at'] = time();
        }

        // DECAY RESIST — while in matching location, reduce decay rate
        $dynamics['_ambient_decay_resist'] = 1.0 / $ambientMult;
    }
} else {
    // Not in a matching location — clear decay resist
    unset($dynamics['_ambient_decay_resist']);
}

// Calculate effective disposition (overlay on existing sex_disposal)
$npcNameKey = "aiagent_nsfw_intimacy_" . strtolower(str_replace(' ', '_', $npcName));
$existingDisposal = 0;
try {
    if (isset($GLOBALS['db'])) {
        $escapedKey = $GLOBALS['db']->escape($npcNameKey);
        $confRow = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id = '{$escapedKey}' LIMIT 1");
        if (is_array($confRow) && !empty($confRow['value'])) {
            $intimacyData = json_decode($confRow['value'], true) ?: [];
            $existingDisposal = intval($intimacyData['sex_disposal'] ?? 0);
        }
    }
} catch (Throwable $e) {
    // Use 0
}

$effectiveDisposal = RelationshipDynamics::getEffectiveDisposition($existingDisposal, $dynamics);

// Snapshot NPC name AND player name for postrequest (processor/postrequest.php re-requires
// conf.php which resets HERIKA_NAME to 'The Narrator' and PLAYER_NAME to 'Prisoner')
$GLOBALS['RELDYN_NPC_NAME'] = $npcName;
$GLOBALS['RELDYN_PLAYER_NAME'] = $GLOBALS['PLAYER_NAME'] ?? 'Player';

// Store effective disposal for other extensions to read
$GLOBALS['RELDYN_EFFECTIVE_DISPOSAL'] = $effectiveDisposal;
$GLOBALS['RELDYN_PASSION'] = floatval($dynamics['passion']);
$GLOBALS['RELDYN_JEALOUSY'] = floatval($dynamics['jealousy_anger']);
$GLOBALS['RELDYN_STAGE'] = $dynamics['stage'] ?? 'early';
$GLOBALS['RELDYN_LOVE_LANG_PRIMARY'] = $dynamics['love_language_primary'];
$GLOBALS['RELDYN_LOVE_LANG_SECONDARY'] = $dynamics['love_language_secondary'];

// Set blush multiplier for love language match
// (maras_bridge reads this to scale blush duration)
$GLOBALS['RELDYN_BLUSH_MULTIPLIER'] = 1.0;

// Bridge to Sharmat: write effective disposal so Sharmat's scene gating reads it
// Only fires if Sharmat is installed — zero dependency otherwise
if (class_exists('NsfwNpcData')) {
    try {
        NsfwNpcData::setKey($npcName, 'sex_disposal', intval($effectiveDisposal));
    } catch (\Throwable $e) {
        // Sharmat not available — silently continue
    }
}

// Save dynamics (decay + reunion applied)
RelationshipDynamics::saveDynamics($npcName, $dynamics);
