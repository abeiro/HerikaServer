<?php
/**
 * Relationship Dynamics — Per-NPC AJAX Save Endpoint
 *
 * Actions:
 *   save    — Save love language, warmth curve, temperament, interests
 *   autogen — Auto-generate preferences from NPC class + skills
 *   reset   — Reset passion, interactions, stage (keep config)
 */

header('Content-Type: application/json');

$enginePath = __DIR__ . "/../../";
require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/" . $GLOBALS["DBDRIVER"] . ".class.php");
$GLOBALS['db'] = new sql();

require_once __DIR__ . '/relationship_dynamics.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['npc'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing NPC name']);
    exit;
}

$npcName = trim($input['npc']);
$action = $input['action'] ?? 'save';
$db = $GLOBALS['db'];

try {
    switch ($action) {

        case 'save':
            // Load current dynamics
            $dynamics = RelationshipDynamics::getDynamics($npcName);

            // Update configurable fields (only if provided)
            $configFields = ['love_language_primary', 'love_language_secondary', 'warmth_curve', 'inferred_temperament', 'relationship_preference', 'openness'];
            foreach ($configFields as $field) {
                if (isset($input[$field])) {
                    $dynamics[$field] = $input[$field] !== '' ? $input[$field] : null;
                }
            }

            // Update interests
            if (isset($input['interests']) && is_array($input['interests'])) {
                $prefs = [];
                foreach ($input['interests'] as $act => $mult) {
                    if (in_array($act, RelationshipDynamics::INTEREST_TYPES)) {
                        $prefs[$act] = max(0.5, min(2.0, floatval($mult)));
                    }
                }
                $dynamics['interests'] = !empty($prefs) ? $prefs : null;
            }

            // Backward compat: accept old activity_preferences key
            if (!isset($input['interests']) && isset($input['activity_preferences']) && is_array($input['activity_preferences'])) {
                $dynamics['interests'] = RelationshipDynamics::migrateOldPreferences($input['activity_preferences']);
                unset($dynamics['activity_preferences']);
            }

            // Re-embed interest vector with updated sliders
            $interests = $dynamics['interests'] ?? RelationshipDynamics::generateInterests();
            RelationshipDynamics::embedInterestVector($npcName, $interests, $dynamics);

            // Save
            RelationshipDynamics::saveDynamics($npcName, $dynamics);
            RelationshipDynamics::clearConfigCache();

            echo json_encode(['ok' => true]);
            break;

        case 'autogen':
            // Load NPC data for auto-generation
            $npcRow = $db->fetchOne(
                "SELECT skills, extended_data FROM core_npc_master WHERE lower(npc_name) = lower("
                . $db->escapeLiteral($npcName) . ") LIMIT 1"
            );

            // Set GLOBALS so generateInterests() can read them
            $GLOBALS['HERIKA_NAME'] = $npcName;
            $GLOBALS['HERIKA_SKILLS'] = $npcRow['skills'] ?? '';

            // Generate interests from bio + class + skills
            $prefs = RelationshipDynamics::generateInterests();

            // Also auto-gen love language if not set
            $dynamics = RelationshipDynamics::getDynamics($npcName);
            $llPrimary = $dynamics['love_language_primary'] ?? null;
            $llSecondary = $dynamics['love_language_secondary'] ?? null;
            $warmth = $dynamics['warmth_curve'] ?? null;
            $temp = $dynamics['inferred_temperament'] ?? null;

            if (empty($llPrimary)) {
                RelationshipDynamics::ensureLoveLanguage($npcName, $dynamics);
                $llPrimary = $dynamics['love_language_primary'] ?? null;
                $llSecondary = $dynamics['love_language_secondary'] ?? null;
                $warmth = $dynamics['warmth_curve'] ?? null;
                $temp = $dynamics['inferred_temperament'] ?? null;
            }

            // Auto-embed interest vector (async-safe, ~8ms)
            RelationshipDynamics::embedInterestVector($npcName, $prefs, $dynamics);
            RelationshipDynamics::saveDynamics($npcName, $dynamics);

            echo json_encode([
                'ok' => true,
                'preferences' => $prefs,
                'love_language_primary' => $llPrimary,
                'love_language_secondary' => $llSecondary,
                'warmth_curve' => $warmth,
                'inferred_temperament' => $temp,
            ]);
            break;

        case 'reset':
            $dynamics = RelationshipDynamics::getDynamics($npcName);

            // Reset runtime state but keep configuration
            $dynamics['passion'] = 0.0;
            $dynamics['passion_updated_at'] = 0;
            $dynamics['passion_sources'] = ['love_match' => 0, 'reunion' => 0, 'dramatic' => 0, 'repair' => 0];
            $dynamics['jealousy_anger'] = 0.0;
            $dynamics['jealousy_updated_at'] = 0;
            $dynamics['jealousy_trigger_npc'] = null;
            $dynamics['in_conflict'] = false;
            $dynamics['conflict_entered_at'] = 0;
            $dynamics['conflict_positive_count'] = 0;
            $dynamics['interaction_count'] = 0;
            $dynamics['last_interaction_at'] = 0;
            $dynamics['total_positive_interactions'] = 0;
            $dynamics['stage'] = 'early';
            $dynamics['last_seen_at'] = 0;
            $dynamics['reunion_spike_given'] = false;
            $dynamics['love_language_hints_given'] = 0;

            RelationshipDynamics::saveDynamics($npcName, $dynamics);

            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
