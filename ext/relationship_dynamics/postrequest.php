<?php
/**
 * Relationship Dynamics — Postrequest Hook
 *
 * Runs after other extension postrequest hooks (alphabetical order).
 * Handles: interaction classification, passion gain, diminishing returns,
 * affinity multiplier (RPM→Speed), jealousy scan, conflict, stages.
 */

if (!isset($GLOBALS['gameRequest']) || !is_array($GLOBALS['gameRequest'])) {
    return;
}

$reqType = $GLOBALS['gameRequest'][0] ?? '';
if ($reqType === 'maras_sync') {
    return;
}

// IMPORTANT: $GLOBALS['HERIKA_NAME'] is clobbered to 'The Narrator' by
// processor/postrequest.php (which re-requires conf.php before ext/ hooks run).
// Use the NPC name snapshot saved by prerequest.php, falling back to
// GetOriginalHerikaName() (CHIM utility) then HERIKA_NAME as last resort.
$npcName = $GLOBALS['RELDYN_NPC_NAME']
    ?? (function_exists('GetOriginalHerikaName') ? GetOriginalHerikaName() : null)
    ?? $GLOBALS['HERIKA_NAME']
    ?? '';
// === COMBAT EVENT ROUTING ===
// Combat events must ALWAYS go through the combat handler, even when
// RELDYN_NPC_NAME is set to a real NPC (e.g. minai_bleedoutself sends npcName="Ashe").
$combatNarratorTypes = ['radiantcombatfriend', 'death', 'bleedout', 'combatend', 'combatendmighty',
    'minai_bleedoutself', 'info_minai_bleedoutself', 'minai_combatendvictory', 'minai_combatenddefeat'];
$isCombatEvent = in_array($reqType, $combatNarratorTypes);

if ($isCombatEvent || empty($npcName) || $npcName === 'The Narrator') {
    if ($isCombatEvent) {
        require_once __DIR__ . '/relationship_dynamics.php';
        if (!RelationshipDynamics::isEnabled()) { return; }

        $reldynCfg = RelationshipDynamics::getConfig();
        if (empty($reldynCfg['combat_enabled'] ?? true)) { return; }

        $eventData = $GLOBALS['gameRequest'][3] ?? '';
        $playerName = $GLOBALS['RELDYN_PLAYER_NAME'] ?? $GLOBALS['PLAYER_NAME'] ?? 'Player';

        // Find which NPC(s) in CACHE_PEOPLE are involved
        // CACHE_PEOPLE is a pipe-delimited string: "|Ashe|Lydia|Faendal|"
        $cachePeopleRaw = $GLOBALS['CACHE_PEOPLE'] ?? '';
        $cachePeople = array_values(array_filter(array_map('trim', explode('|', $cachePeopleRaw))));
        $involvedNpcs = [];

        // Method 1: Parse NPC name from event data
        // "NpcName is teamed up with Kaida..." (radiantcombatfriend)
        if (preg_match('/^(?:The Narrator:\s*)?(.+?)\s+is teamed up with\s+' . preg_quote($playerName, '/') . '/i', $eventData, $m)) {
            $involvedNpcs[] = trim($m[1]);
        }
        // "Kaida has defeated Enemy..." or "NpcName has defeated Enemy..."
        if (preg_match('/^(?:\(.*?\))?(.+?)\s+has defeated\s+/i', $eventData, $m)) {
            $killer = trim($m[1]);
            if (strcasecmp($killer, $playerName) !== 0) {
                $involvedNpcs[] = $killer; // NPC got the kill
            }
        }
        // "NpcName falls to the ground..." (bleedout)
        if (preg_match('/^(?:\(.*?\))?(.+?)\s+falls to the ground/i', $eventData, $m)) {
            $involvedNpcs[] = trim($m[1]);
        }

        // Method 2: Check CACHE_PEOPLE for any loaded NPCs during combat
        // If we couldn't parse a name but combat happened, check who's nearby
        if (empty($involvedNpcs) && !empty($cachePeople)) {
            foreach ($cachePeople as $pName) {
                if (!empty($pName) && strcasecmp($pName, $playerName) !== 0) {
                    // Only include if they have RelDyn dynamics (avoid random bystanders)
                    $testDyn = RelationshipDynamics::getDynamics($pName);
                    if (!empty($testDyn['love_language_primary'])) {
                        $involvedNpcs[] = $pName;
                    }
                }
            }
        }

        // -- Method 3: CACHE_PEOPLE + CACHE_PARTY witness fallback for death events --
        // Death events people column only contains the dead NPC, not nearby followers.
        // For kill events, check CACHE_PEOPLE and CACHE_PARTY to credit companions
        // who witnessed the kill, at a reduced multiplier (0.5x).
        $witnessNpcs = [];
        if ($reqType === 'death') {
            $witnessCandidates = [];
            // Gather from CACHE_PEOPLE (pipe-delimited nearby NPCs)
            if (!empty($cachePeople)) {
                foreach ($cachePeople as $pName) {
                    if (!empty($pName) && strcasecmp($pName, $playerName) !== 0) {
                        $witnessCandidates[$pName] = true;
                    }
                }
            }
            // Also check CACHE_PARTY (JSON object keyed by NPC name -- definitive followers)
            $cachePartyRaw = $GLOBALS['CACHE_PARTY'] ?? '';
            if (!empty($cachePartyRaw)) {
                $partyData = json_decode($cachePartyRaw, true);
                if (is_array($partyData)) {
                    foreach (array_keys($partyData) as $partyNpc) {
                        if (!empty($partyNpc) && strcasecmp($partyNpc, $playerName) !== 0) {
                            $witnessCandidates[$partyNpc] = true;
                        }
                    }
                }
            }
            // Filter: only NPCs with RelDyn dynamics, exclude already-found direct participants
            $directNpcs = array_map('strtolower', $involvedNpcs);
            foreach (array_keys($witnessCandidates) as $wName) {
                if (in_array(strtolower($wName), $directNpcs)) {
                    continue; // Already credited as direct participant
                }
                $wDyn = RelationshipDynamics::getDynamics($wName);
                if (!empty($wDyn['love_language_primary'])) {
                    $witnessNpcs[] = $wName;
                }
            }
            if (!empty($witnessNpcs)) {
                RelationshipDynamics::log("DEATH WITNESSES: " . implode(', ', $witnessNpcs) . " (from CACHE_PEOPLE+CACHE_PARTY)");
            }
        }

        // Apply combat passion to each involved NPC
        // Direct participants get full credit; witnesses get 0.5x
        $allCombatNpcs = array_unique(array_merge($involvedNpcs, $witnessNpcs));
        $witnessSet = array_map('strtolower', $witnessNpcs);
        foreach ($allCombatNpcs as $combatNpc) {
            $dynamics = RelationshipDynamics::getDynamics($combatNpc);
            if (empty($dynamics['love_language_primary'])) continue;

            // Determine combat classification
            $isWitness = in_array(strtolower($combatNpc), $witnessSet);
            $combatLL = RelationshipDynamics::LL_SERVICE; // default: positive combat
            if ($reqType === 'bleedout' || $reqType === 'minai_bleedoutself') {
                $combatLL = 'combat_bleedout';
            }

            // Get enriched combat context (MinAI vitals if available)
            $combatCtx = RelationshipDynamics::getCombatContext($combatNpc);

            // Calculate passion change -- bleedout is a DRAIN, positive combat is a GAIN
            if ($combatLL === 'combat_bleedout') {
                // Bleedout drain: temperament-scaled negative passion
                $temperament = $dynamics['inferred_temperament'] ?? null;
                $gain = RelationshipDynamics::TEMPERAMENT_BLEEDOUT_DRAIN[$temperament] ?? -1.5;
                RelationshipDynamics::log("Bleedout drain: {$combatNpc} temperament={$temperament} base_drain={$gain}");
            } else {
                // Positive combat: use standard passion gain
                $gain = RelationshipDynamics::calculatePassionGain($dynamics, $combatLL);

                // Witnesses get reduced credit (0.5x) -- they saw the kill but didn't make it
                $isWitness = in_array(strtolower($combatNpc), $witnessSet);
                if ($isWitness) {
                    $gain *= 0.5;
                }

                // Kill streak bonus: multiple shared kills in 5 min window
                if ($combatCtx && $combatCtx['recent_kills'] > 1 && $gain > 0) {
                    $streakBonus = min(2.0, ($combatCtx['recent_kills'] - 1) * 0.5);
                    $gain += $streakBonus;
                    RelationshipDynamics::log("Kill streak bonus: +{$streakBonus} ({$combatCtx['recent_kills']} kills)");
                }

                // MinAI shared danger bonus: low HP while fighting together
                if ($combatCtx && $combatCtx['in_combat'] && $gain > 0) {
                    $combatInterest = floatval($dynamics['interests']['combat'] ?? 1.0);
                    $dangerThreshold = max(0.0, 0.30 - ($combatInterest * 0.15));
                    if ($combatCtx['health_pct'] <= $dangerThreshold && $combatCtx['health_pct'] > 0) {
                        $gain *= 1.5; // shared danger intensity boost
                        RelationshipDynamics::log("Shared danger boost: HP={$combatCtx['health_pct']} threshold={$dangerThreshold}");
                    }
                    // Confirmed fighting together upgrades all gains
                    $gain *= 1.3;
                    RelationshipDynamics::log("Shared combat confirmed (source={$combatCtx['source']}): 1.3x multiplier");
                }
            }

            // Apply the passion change
            if (abs($gain) > 0.01) {
                if ($gain > 0) {
                    RelationshipDynamics::addPassion($dynamics, $gain, 'combat');
                    $dynamics['total_positive_interactions'] = intval($dynamics['total_positive_interactions'] ?? 0) + 1;
                } else {
                    // Negative drain (bleedout): clamp at zero, don't use addPassion
                    $dynamics['passion'] = max(0, floatval($dynamics['passion']) + $gain);
                    $dynamics['passion_updated_at'] = time();
                }
                $dynamics['interaction_count'] = intval($dynamics['interaction_count'] ?? 0) + 1;
                $dynamics['last_interaction_at'] = time();
                $dynamics['passion_sources']['combat'] = floatval($dynamics['passion_sources']['combat'] ?? 0) + $gain;
                RelationshipDynamics::saveDynamics($combatNpc, $dynamics);
                $witnessTag = $isWitness ? ' [WITNESS 0.5x]' : '';
                RelationshipDynamics::log("COMBAT EVENT: {$combatNpc} type={$reqType} LL={$combatLL} gain=" . round($gain, 2) . " passion=" . round($dynamics['passion'], 2) . " source=" . ($combatCtx['source'] ?? 'basic') . $witnessTag);
            }
        }
    }
    // Non-combat narrator events or combat events that didn't match — nothing more to do
    return;
}

require_once __DIR__ . '/relationship_dynamics.php';

if (!RelationshipDynamics::isEnabled()) {
    return;
}

$dynamics = RelationshipDynamics::getDynamics($npcName);
$reldynCfg = RelationshipDynamics::getConfig();

// ── NPC-TO-NPC FILTER ──
// Radiant dialogue is NPC-to-NPC — player isn't involved.
// Passion/affinity between those NPCs is handled by CHIM core's relationship_system.
// Skip RelDyn player↔NPC passion math to prevent parasitism.
$radiantTypes = ['radiant', 'radiantsearchingfriend', 'radiantsearchinghostile',
    'radiantcombathostile', 'minai_force_rechat'];
if (in_array($reqType, $radiantTypes)) {
    return;
}

// ── BYSTANDER FILTER ──
// Only the active conversation target gets full passion math.
// Bystanders (NPCs in CACHE_PEOPLE but not being spoken to) already received
// ambient trickle in prerequest.php — no interaction-based passion for them.
$activeNpc = $GLOBALS['RELDYN_NPC_NAME']
    ?? (function_exists('GetOriginalHerikaName') ? GetOriginalHerikaName() : null)
    ?? '';
if (!empty($activeNpc) && strtolower($npcName) !== strtolower($activeNpc)) {
    // Bystander — skip full passion math, ambient trickle already applied
    return;
}

// Ensure love language exists (in case prerequest was skipped)
RelationshipDynamics::ensureLoveLanguage($npcName, $dynamics);

// -------------------------------------------------------------------------
// 1. Classify interaction
// -------------------------------------------------------------------------
$lastMood = null;
try {
    if (isset($GLOBALS['db'])) {
        $db = $GLOBALS['db'];
        $moodRow = $db->fetchOne(
            "SELECT mood FROM moods_issued WHERE lower(speaker) = lower('" . $db->escape($npcName) . "') ORDER BY localts DESC LIMIT 1"
        );
        $lastMood = $moodRow['mood'] ?? null;
    }
} catch (Throwable $e) {
    // Mood query failed, continue without
}

$interactionLL = RelationshipDynamics::classifyInteraction($GLOBALS['gameRequest'], $lastMood);
$GLOBALS['RELDYN_LAST_INTERACTION_LL'] = $interactionLL;
RelationshipDynamics::log("POST classify: npc={$npcName} type={$reqType} mood={$lastMood} LL=" . ($interactionLL ?? 'NULL'));

// Detect interest context for passion weighting (all love languages)
$currentInterest = RelationshipDynamics::detectInterestContext($interactionLL);
$GLOBALS['RELDYN_CURRENT_INTEREST'] = $currentInterest;
if ($currentInterest) {
    $intMult = RelationshipDynamics::getInterestMultiplier($dynamics, $interactionLL);
    $GLOBALS['RELDYN_INTEREST_MULT'] = $intMult;
    $dynamics['last_interest'] = $currentInterest;
    $dynamics['last_interest_mult'] = $intMult;
    $dynamics['last_interest_ll'] = $interactionLL;
}

// -------------------------------------------------------------------------
// 1b. Topic Talk Bonus — conversation topic matches NPC interests
// -------------------------------------------------------------------------
$topicBonus = 1.0;
if ($reldynCfg['topic_bonus_enabled'] ?? true) {
$topicMatch = null;
try {
    $db_topic = $GLOBALS['db'] ?? null;
    if ($db_topic) {
        // Read the current Oghma topic from this conversation
        $oghmaTopicRow = $db_topic->fetchOne("SELECT value FROM conf_opts WHERE id = 'current_oghma_topic' LIMIT 1");
        $oghmaTopic = ($oghmaTopicRow && !empty($oghmaTopicRow['value'])) ? trim($oghmaTopicRow['value']) : null;

        if ($oghmaTopic) {
            // Look up the Oghma article to get its knowledge_class and vector
            $topicEscaped = $db_topic->escape(strtolower($oghmaTopic));
            $articleRow = $db_topic->fetchOne(
                "SELECT knowledge_class, vector384 FROM oghma "
                . "WHERE lower(topic) = '{$topicEscaped}' LIMIT 1"
            );

            if ($articleRow) {
                // Method 1: Vector similarity (preferred — uses the NPC's interest vector)
                $npcVector = $dynamics['_interest_vector'] ?? null;
                if (!empty($npcVector) && is_array($npcVector) && !empty($articleRow['vector384'])) {
                    $articleVecStr = trim($articleRow['vector384'], '[]');
                    $articleVec = array_map('floatval', explode(',', $articleVecStr));
                    $topicSimilarity = RelationshipDynamics::cosineSimilarity($npcVector, $articleVec);

                    if ($topicSimilarity >= 0.35) {
                        $topicBonus = 1.0 + min(0.5, ($topicSimilarity - 0.35) * 1.67); // 0.35→1.0x, 0.65→1.5x
                        $topicMatch = $oghmaTopic;
                        RelationshipDynamics::log("TopicBonus: {$npcName} topic='{$oghmaTopic}' sim=" . round($topicSimilarity, 3) . " bonus={$topicBonus}x");
                    }
                }
                // Method 2: Knowledge class keyword fallback
                elseif (!empty($articleRow['knowledge_class'])) {
                    $klasses = array_map('trim', explode(',', strtolower($articleRow['knowledge_class'])));
                    $interests = RelationshipDynamics::getInterests($dynamics);
                    $klassMap = RelationshipDynamics::KNOWLEDGE_CLASS_TO_INTEREST ?? [];
                    foreach ($klasses as $klass) {
                        $mappedInterest = $klassMap[$klass] ?? null;
                        if ($mappedInterest && isset($interests[$mappedInterest]) && $interests[$mappedInterest] >= 1.3) {
                            $topicBonus = 1.3;
                            $topicMatch = $oghmaTopic;
                            RelationshipDynamics::log("TopicBonus(keyword): {$npcName} topic='{$oghmaTopic}' class={$klass} => {$mappedInterest} bonus=1.3x");
                            break;
                        }
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Topic bonus is optional — don't crash on failure
}
} // end topic_bonus_enabled
$GLOBALS['RELDYN_TOPIC_BONUS'] = $topicBonus;
$GLOBALS['RELDYN_TOPIC_MATCH'] = $topicMatch;

// -------------------------------------------------------------------------
// 1c. Flirt-in-Context Bonus — flirty mood + (topic match OR location match)
// -------------------------------------------------------------------------
$flirtBonus = 1.0;
if ($reldynCfg['flirt_bonus_enabled'] ?? true) {
$flirtyMoods = ['flirty', 'romantic', 'playful', 'teasing', 'amused', 'charmed',
                'smitten', 'coy', 'seductive', 'affectionate', 'bashful', 'flustered'];
if (!empty($lastMood) && in_array(strtolower($lastMood), $flirtyMoods)) {
    $hasLocationMatch = floatval($GLOBALS['RELDYN_AMBIENT_RESONANCE'] ?? 0) >= 0.3;
    $hasTopicMatch = ($topicBonus > 1.0);
    if ($hasLocationMatch || $hasTopicMatch) {
        $flirtBonus = 1.2;
        RelationshipDynamics::log("FlirtBonus: {$npcName} mood={$lastMood} location=" . ($hasLocationMatch ? 'yes' : 'no') . " topic=" . ($hasTopicMatch ? 'yes' : 'no') . " bonus=1.2x");
    }
}
} // end flirt_bonus_enabled
$GLOBALS['RELDYN_FLIRT_BONUS'] = $flirtBonus;

// -------------------------------------------------------------------------
// 2. Calculate and apply passion gain
// -------------------------------------------------------------------------
$passionGain = 0.0;
if (($reldynCfg['passion_enabled'] ?? true) && $interactionLL !== null) {
    $rawPassionGain = RelationshipDynamics::calculatePassionGain($dynamics, $interactionLL);
    // Apply topic and flirt bonuses on top of base passion gain
    $passionGain = $rawPassionGain * $topicBonus * $flirtBonus;
    error_log("[RelDyn-POST] passionGain: npc={$npcName} LL={$interactionLL} raw={$rawPassionGain} topic={$topicBonus}x flirt={$flirtBonus}x final={$passionGain} currentPassion={$dynamics['passion']}");
    if ($passionGain > 0) {
        RelationshipDynamics::addPassion($dynamics, $passionGain, 'love_match');
    }

    if ($passionGain > 0) {
        // Store blush multiplier for next prerequest cycle
        // (maras_bridge runs before relationship_dynamics in prerequest,
        //  so we persist it for the next cycle's blush trigger to read)
        if ($interactionLL === $dynamics['love_language_primary']) {
            $dynamics['pending_blush_mult'] = 2.0;
            $GLOBALS['RELDYN_BLUSH_MULTIPLIER'] = 2.0;
        } elseif ($interactionLL === $dynamics['love_language_secondary']) {
            $dynamics['pending_blush_mult'] = 1.5;
            $GLOBALS['RELDYN_BLUSH_MULTIPLIER'] = 1.5;
        } else {
            $dynamics['pending_blush_mult'] = 1.0;
        }

        // Store passion delta for blush self-awareness in next context.php cycle
        $dynamics['_last_passion_delta'] = round($passionGain, 2);
    }
}

// Store topic match for next context.php cycle (topic resonance hint)
if ($topicMatch) {
    $dynamics['_last_topic_match'] = $topicMatch;
} else {
    // Clear stale topic match after one conversation without a match
    unset($dynamics['_last_topic_match']);
}

// -------------------------------------------------------------------------
// 3. Diminishing returns — record interaction
// -------------------------------------------------------------------------
RelationshipDynamics::recordInteraction($dynamics);

// -------------------------------------------------------------------------
// 4. RPM → Speed: Apply passion-weighted affinity change
// -------------------------------------------------------------------------
// RelDyn owns aff. The relationship_system's LLM eval provides type/note
// but does NOT write aff when RelDyn is active.
// Base delta: +1 per positive interaction, scaled by passion multiplier.
// Formula: aff_delta = base × passion_multiplier
//   passion 0   → ×0.3  (idling — affinity barely moves)
//   passion 50  → ×1.15 (cruising — normal pace)
//   passion 100 → ×2.0  (redline — maximum)
$affinityGainMult = RelationshipDynamics::getAffinityGainMultiplier($dynamics);
$baseDelta = ($passionGain > 0) ? 1 : 0; // +1 per positive interaction

try {
    $db = $GLOBALS['db'] ?? null;
    $playerName = $GLOBALS['RELDYN_PLAYER_NAME'] ?? trim($GLOBALS['PLAYER_NAME'] ?? 'Player');

    if ($db && !empty($playerName) && $baseDelta != 0) {
        $escaped = $db->escape($npcName);
        $row = $db->fetchOne("SELECT id, extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}') LIMIT 1");

        if (is_array($row) && !empty($row['extended_data'])) {
            $extData = json_decode($row['extended_data'], true) ?: [];
            $relationships = $extData['relationships'] ?? [];
            $playerRel = $relationships[$playerName] ?? null;

            if ($playerRel !== null) {
                $currentAff = intval($playerRel['aff'] ?? 0);
                $modifiedDelta = round($baseDelta * $affinityGainMult, 2);

                // Accumulate fractional deltas — only apply when they round to ≥1
                $pendingAff = floatval($dynamics['_pending_aff_delta'] ?? 0);
                $pendingAff += $modifiedDelta;
                $intDelta = intval(floor($pendingAff));
                $dynamics['_pending_aff_delta'] = $pendingAff - $intDelta;

                if ($intDelta != 0) {
                    $newAff = max(-100, min(100, $currentAff + $intDelta));
                    $playerRel['aff'] = $newAff;
                    $relationships[$playerName] = $playerRel;
                    $extData['relationships'] = $relationships;

                    $extJson = json_encode($extData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $extEscaped = $db->escape($extJson);
                    $npcId = intval($row['id']);
                    $db->execQuery("UPDATE core_npc_master SET extended_data = '{$extEscaped}'::jsonb WHERE id = {$npcId}");

                    RelationshipDynamics::log("RPM→Speed: base={$baseDelta} × mult=" . round($affinityGainMult, 2) . " = +{$intDelta} aff (passion=" . intval($dynamics['passion']) . ", aff {$currentAff}→{$newAff})");
                } else {
                    RelationshipDynamics::log("RPM→Speed: base={$baseDelta} × mult=" . round($affinityGainMult, 2) . " = pending " . round($pendingAff + $intDelta, 2) . " (passion=" . intval($dynamics['passion']) . ", not enough for +1 yet)");
                }

                // Check for conflict from negative delta
                if ($intDelta < 0) {
                    RelationshipDynamics::checkAffinityDropConflict($dynamics, $intDelta);
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log("[RelDyn] RPM→Speed error: " . $e->getMessage());
}

// -------------------------------------------------------------------------
// 5. Jealousy scan — check nearby NPCs
// -------------------------------------------------------------------------
if (!($reldynCfg['jealousy_enabled'] ?? true)) goto skip_jealousy;
$romanticInteraction = in_array($interactionLL, [
    RelationshipDynamics::LL_TOUCH,
    RelationshipDynamics::LL_WORDS,
]);

if ($romanticInteraction) {
    // CACHE_PEOPLE is pipe-delimited: "|Ashe|Lydia|Faendal|"
    $nearbyNpcs = array_values(array_filter(array_map('trim', explode('|', $GLOBALS['CACHE_PEOPLE'] ?? ''))));

    foreach ($nearbyNpcs as $nearbyNpc) {
        if (empty($nearbyNpc) || strtolower($nearbyNpc) === strtolower($npcName)) {
            continue;
        }

        // Load nearby NPC's dynamics
        $nearbyDynamics = RelationshipDynamics::getDynamics($nearbyNpc);
        if (empty($nearbyDynamics['love_language_primary'])) {
            continue; // Not initialized — skip
        }

        // Check if they have reason to be jealous
        $relPref = null;
        $marasStatus = null;
        $marasAff = 0;

        // Read relationship_preference: RelDyn first, Sharmat fallback
        $nearbyDynamics = RelationshipDynamics::getDynamics($nearbyNpc);
        $relPref = $nearbyDynamics['relationship_preference'] ?? null;
        if (empty($relPref) && class_exists('NsfwNpcData')) {
            $relPref = NsfwNpcData::getKey($nearbyNpc, 'relationship_preference');
        }

        try {
            $db2 = $GLOBALS['db'] ?? null;
            if ($db2) {
                $playerName2 = $GLOBALS['RELDYN_PLAYER_NAME'] ?? trim($GLOBALS['PLAYER_NAME'] ?? 'Player');
                $nearbyEsc = $db2->escape($nearbyNpc);
                $nRow = $db2->fetchOne("SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$nearbyEsc}') LIMIT 1");
                if (is_array($nRow) && !empty($nRow['extended_data'])) {
                    $nExt = json_decode($nRow['extended_data'], true) ?: [];
                    $nRel = $nExt['relationships'][$playerName2] ?? null;
                    if ($nRel && isset($nRel['maras'])) {
                        $marasStatus = $nRel['maras']['status'] ?? null;
                        $marasAff = intval($nRel['maras']['affection'] ?? 0);
                    }
                }
            }
        } catch (Throwable $e) {
            continue;
        }

        $jealousyGain = RelationshipDynamics::calculateJealousyGain(
            $nearbyNpc, $npcName, $nearbyDynamics,
            $relPref, $marasStatus, $marasAff
        );

        if ($jealousyGain > 0) {
            RelationshipDynamics::addJealousy($nearbyDynamics, $jealousyGain, $npcName);
            RelationshipDynamics::saveDynamics($nearbyNpc, $nearbyDynamics);
        }
    }
}

skip_jealousy:

// -------------------------------------------------------------------------
// 6. Conflict resolution check
// -------------------------------------------------------------------------
if (!($reldynCfg['conflict_enabled'] ?? true)) goto skip_conflict;
if ($passionGain > 0 && !empty($dynamics['in_conflict'])) {
    $repairBurst = RelationshipDynamics::recordConflictPositive($dynamics);
    if ($repairBurst > 0) {
        RelationshipDynamics::addPassion($dynamics, $repairBurst, 'repair');
    }
}

skip_conflict:

// -------------------------------------------------------------------------
// 7. Track positive interactions + stage advancement
// -------------------------------------------------------------------------
if ($passionGain > 0) {
    $dynamics['total_positive_interactions'] = intval($dynamics['total_positive_interactions'] ?? 0) + 1;
    RelationshipDynamics::checkStageAdvancement($dynamics);
}

// -------------------------------------------------------------------------
// 8. Save
// -------------------------------------------------------------------------
RelationshipDynamics::saveDynamics($npcName, $dynamics);
