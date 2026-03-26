<?php
/**
 * Relationship Dynamics — Context Hook
 *
 * Runs after other extension context hooks (alphabetical order).
 * Injects <emotional_dynamics> narrative block into LLM context.
 * Player never sees numbers — only behavioral descriptions.
 */

$npcName = $GLOBALS['HERIKA_NAME'] ?? '';
if (empty($npcName) || $npcName === 'The Narrator') {
    return;
}

require_once __DIR__ . '/relationship_dynamics.php';

if (!RelationshipDynamics::isEnabled()) {
    return;
}

$dynamics = RelationshipDynamics::getDynamics($npcName);

// Only inject if love language has been generated
if (empty($dynamics['love_language_primary'])) {
    return;
}

$passion = floatval($dynamics['passion'] ?? 0);
$jealousy = floatval($dynamics['jealousy_anger'] ?? 0);
$stage = $dynamics['stage'] ?? 'early';
$temperament = $dynamics['inferred_temperament'] ?? '';
$inConflict = !empty($dynamics['in_conflict']);
$reunionGiven = !empty($dynamics['reunion_spike_given']);

$parts = [];

// -------------------------------------------------------------------------
// Passion state (the core emotional temperature)
// -------------------------------------------------------------------------
$passionBand = RelationshipDynamics::getPassionBand($passion);
$passionContext = [
    'burning'  => "{$npcName} feels an electric tension with {$GLOBALS['PLAYER_NAME']} — barely able to keep composure, pulse racing, hyperaware of every word and movement.",
    'intense'  => "{$npcName} feels a palpable warmth and heightened awareness around {$GLOBALS['PLAYER_NAME']} — drawn to them, finding excuses to stay close.",
    'warm'     => "{$npcName} feels a growing excitement around {$GLOBALS['PLAYER_NAME']} — something is building between them, an undeniable pull.",
    'stirring' => "{$npcName} feels a pleasant warmth when {$GLOBALS['PLAYER_NAME']} is near — something faint stirring beneath the surface.",
    'faint'    => "{$npcName} feels the faintest spark of... something... when {$GLOBALS['PLAYER_NAME']} speaks.",
];
if (isset($passionContext[$passionBand])) {
    $parts[] = $passionContext[$passionBand];
}

// -------------------------------------------------------------------------
// Blush self-awareness (dynamic — only fires on passion spike)
// -------------------------------------------------------------------------
$lastDelta = floatval($dynamics['_last_passion_delta'] ?? 0);
$blushMult = floatval($dynamics['pending_blush_mult'] ?? 1.0);
if ($lastDelta >= 4.0 && $blushMult >= 1.5) {
    // High delta + love language match = strong involuntary response
    if ($lastDelta >= 7.0) {
        $parts[] = "<blush_awareness>Heat floods {$npcName}'s face unbidden — a deep, visible flush they cannot suppress. Their body is betraying something their words haven't admitted yet. They are acutely aware of it.</blush_awareness>";
    } else {
        $parts[] = "<blush_awareness>Unexpected warmth rises in {$npcName}'s cheeks — an involuntary response they didn't anticipate. They notice it happening and it catches them off guard.</blush_awareness>";
    }
    // Clear after injection — one-shot per spike
    $dynamics['_last_passion_delta'] = 0;
    $dynamics['pending_blush_mult'] = 1.0;
    RelationshipDynamics::saveDynamics($npcName, $dynamics);
} elseif ($lastDelta >= 2.0 && $blushMult >= 1.0) {
    // Moderate delta — subtle warmth, not full blush
    $parts[] = "<blush_awareness>A faint warmth touches {$npcName}'s skin — barely perceptible, but they feel it. Something about this moment landed differently than expected.</blush_awareness>";
    $dynamics['_last_passion_delta'] = 0;
    RelationshipDynamics::saveDynamics($npcName, $dynamics);
}

// -------------------------------------------------------------------------
// Relationship stage framing
// -------------------------------------------------------------------------
$stageContext = [
    'early'       => "{$npcName} and {$GLOBALS['PLAYER_NAME']} are still discovering each other — everything feels heightened, uncertain, full of possibility.",
    'established' => "{$npcName} and {$GLOBALS['PLAYER_NAME']} have settled into comfortable familiarity — they know each other's rhythms and patterns.",
    'deep'        => "{$npcName} and {$GLOBALS['PLAYER_NAME']} share something profound and resilient — a bond forged through shared time and experience that weathered storms.",
];
if (isset($stageContext[$stage])) {
    $parts[] = $stageContext[$stage];
}

// -------------------------------------------------------------------------
// Reunion warmth
// -------------------------------------------------------------------------
if ($reunionGiven) {
    $lastSeen = intval($dynamics['last_seen_at'] ?? 0);
    $hoursApart = $lastSeen > 0 ? (time() - $lastSeen) / 3600.0 : 0;
    $player = $GLOBALS['PLAYER_NAME'];

    // Temperament-aware reunion text
    $reunionText = RelationshipDynamics::getReunionText($npcName, $temperament, $hoursApart, $player);
    if ($reunionText) {
        $parts[] = $reunionText;
    }
}

// -------------------------------------------------------------------------
// Jealousy state
// -------------------------------------------------------------------------
$jealousyBand = RelationshipDynamics::getJealousyBand($jealousy);
$triggerNpc = $dynamics['jealousy_trigger_npc'] ?? null;
$jealousyContext = [
    'seething'  => "{$npcName} is seething — jaw clenched, barely containing fury. Something about " . ($triggerNpc ? "{$triggerNpc} and " : "") . "{$GLOBALS['PLAYER_NAME']} has deeply wounded them.",
    'hurt'      => "{$npcName} is visibly hurt and suspicious — their warmth has turned brittle, edged with accusation" . ($triggerNpc ? " about {$triggerNpc}" : "") . ".",
    'unsettled' => "{$npcName} seems unsettled — guarded, with flashes of hurt when certain topics arise.",
    'edgy'      => "{$npcName} has a slight edge — something is bothering them, a hint of insecurity lurking beneath the surface.",
];
if (isset($jealousyContext[$jealousyBand])) {
    $parts[] = $jealousyContext[$jealousyBand];
}

// -------------------------------------------------------------------------
// Conflict / Repair state
// -------------------------------------------------------------------------
if ($inConflict) {
    $repairCount = intval($dynamics['conflict_positive_count'] ?? 0);
    if ($repairCount >= 2) {
        $parts[] = "{$npcName} is cautiously warming again — the hurt isn't gone, but {$GLOBALS['PLAYER_NAME']}'s efforts are reaching through. Each kind word carries extra weight right now.";
    } elseif ($repairCount >= 1) {
        $parts[] = "{$npcName} is still hurt but watching {$GLOBALS['PLAYER_NAME']}'s actions closely — every positive gesture carries extra weight right now, like testing whether this person can be trusted again.";
    } else {
        $parts[] = "{$npcName} is wounded and wary — something {$GLOBALS['PLAYER_NAME']} did cut deep. They need to see genuine effort before the walls come down.";
    }
}

// -------------------------------------------------------------------------
// Love language discovery hints (pure behavioral — no labels)
// -------------------------------------------------------------------------
// The last interaction's love language match gets a hint in context
// so the LLM can describe the reaction differently
$lastLL = $GLOBALS['RELDYN_LAST_INTERACTION_LL'] ?? null;
$primaryLL = $dynamics['love_language_primary'] ?? null;
$secondaryLL = $dynamics['love_language_secondary'] ?? null;

if ($lastLL && $primaryLL) {
    if ($lastLL === $primaryLL) {
        // Strong resonance hint
        $hints = [
            'words_of_affirmation' => "{$npcName}'s eyes brighten noticeably — these words clearly reach something deep. Their whole demeanor softens.",
            'quality_time'         => "{$npcName} seems genuinely grateful for {$GLOBALS['PLAYER_NAME']}'s presence — as if their company alone is a precious gift.",
            'physical_touch'       => "{$npcName}'s breath catches slightly at the contact — their whole posture softens, leaning into it almost involuntarily.",
            'acts_of_service'      => "{$npcName} watches what {$GLOBALS['PLAYER_NAME']} did with quiet intensity — actions like this speak louder than any words could.",
            'gifts'                => "{$npcName} handles the offering with surprising tenderness — more moved than the gift's value alone would suggest.",
        ];
        if (isset($hints[$lastLL])) {
            $parts[] = $hints[$lastLL];
        }
    } elseif ($lastLL === $secondaryLL) {
        // Moderate resonance
        $parts[] = "{$npcName} appreciates the gesture warmly — it clearly means something to them, though perhaps not as deeply as some other form of affection might.";
    } else {
        // No match — contrast signal (this IS the discovery mechanic)
        $parts[] = "{$npcName} acknowledges the gesture with a polite smile — appreciative, but something tells you this isn't quite what moves them most.";
    }
}

// -------------------------------------------------------------------------
// Interest resonance (shared experience context)
// -------------------------------------------------------------------------
// Use cached ambient result from prerequest (avoids double-computing)
$currentInterest = $GLOBALS['RELDYN_AMBIENT_INTEREST'] ?? null;
$currentResonance = floatval($GLOBALS['RELDYN_AMBIENT_RESONANCE'] ?? 0.0);
$currentLocation = $GLOBALS['RELDYN_AMBIENT_LOCATION'] ?? '';
$currentSource = $GLOBALS['RELDYN_AMBIENT_SOURCE'] ?? 'none';

if ($currentInterest && $currentResonance >= 0.15) {
    if ($currentSource === 'vector' && $currentResonance >= 0.3) {
        // Rich vector-based resonance text — the NPC is responding to the specific place
        $intText = RelationshipDynamics::getEnvironmentalResonanceText($npcName, $currentInterest, $currentResonance, $currentLocation);
        if ($intText) $parts[] = $intText;
    } else {
        // Keyword-based or low resonance — use original interest category text
        $intPrefs = RelationshipDynamics::getInterests($dynamics);
        $rawMult = floatval($intPrefs[$currentInterest] ?? 1.0);
        $intText = RelationshipDynamics::getInterestResonanceText($npcName, $currentInterest, $rawMult);
        if ($intText) $parts[] = $intText;
    }
}

// -------------------------------------------------------------------------
// Topic resonance hint (from previous interaction's topic match)
// -------------------------------------------------------------------------
$lastTopicMatch = $dynamics['_last_topic_match'] ?? null;
if (!empty($lastTopicMatch)) {
    $parts[] = "<topic_resonance>{$npcName} was genuinely engaged by a recent conversation about {$lastTopicMatch} — this topic touched on something they truly care about. If the subject comes up again, they'll light up.</topic_resonance>";
}

// -------------------------------------------------------------------------
// NPC initiation context (LLM decides, we just provide the urge)
// -------------------------------------------------------------------------
if ($passion >= 40 && $primaryLL) {
    $initiationHints = [
        'words_of_affirmation' => "{$npcName} has a strong urge to express what they feel — the words are right there, wanting to come out.",
        'quality_time'         => "{$npcName} doesn't want this moment to end — they want to find reasons to keep {$GLOBALS['PLAYER_NAME']} close.",
        'physical_touch'       => "{$npcName} is acutely aware of the space between them and {$GLOBALS['PLAYER_NAME']} — wanting to close it.",
        'acts_of_service'      => "{$npcName} wants to DO something for {$GLOBALS['PLAYER_NAME']} — to show through action what words can't capture.",
        'gifts'                => "{$npcName} thinks about what they could give {$GLOBALS['PLAYER_NAME']} — something meaningful, something that says what they feel.",
    ];
    if (isset($initiationHints[$primaryLL])) {
        $parts[] = $initiationHints[$primaryLL];
    }
}


// -------------------------------------------------------------------------
// Combat awareness (shared danger / post-combat glow)
// -------------------------------------------------------------------------
$combatCtx = RelationshipDynamics::getCombatContext($npcName);
$player = $GLOBALS['PLAYER_NAME'] ?? 'Player';

if ($combatCtx) {
    if (!empty($combatCtx['bleeding_out'])) {
        $parts[] = "{$npcName} is critically wounded and barely conscious. The pain is overwhelming — every breath is a fight to stay awake.";
    } elseif ($combatCtx['in_combat']) {
        $hpPct = $combatCtx['health_pct'];
        if ($hpPct < 0.3) {
            $parts[] = "{$npcName} is badly hurt but still fighting alongside {$player}. The shared danger sharpens every sense.";
        } elseif ($hpPct < 0.6) {
            $parts[] = "{$npcName} is wounded but holding the line with {$player}. The adrenaline of shared combat bonds them.";
        } else {
            $parts[] = "{$npcName} fights alongside {$player}. The rhythm of shared combat — watching each other's backs, coordinating strikes — builds unspoken trust.";
        }
    } elseif ($combatCtx['in_combat']) {
        $parts[] = "{$npcName} is engaged in combat. Adrenaline sharpens focus and strips away social pretense.";
    }
}

// Post-combat glow — combat ended recently but NPC is no longer in active combat
if (!$combatCtx || !$combatCtx['in_combat']) {
    $recentCombat = RelationshipDynamics::getRecentCombatSummary($npcName);
    if ($recentCombat) {
        $parts[] = "The adrenaline from recent combat still lingers. {$npcName} and {$player} just survived a fight together — that shared experience hangs in the air.";
    }
}

// -------------------------------------------------------------------------
// Assemble and inject
// -------------------------------------------------------------------------
if (!empty($parts)) {
    $block = "<emotional_dynamics>\n" . implode("\n", $parts) . "\n</emotional_dynamics>";
    $GLOBALS['contextDataFull'][] = ['role' => 'system', 'content' => $block];
    RelationshipDynamics::log("CTX: Injected emotional_dynamics for {$npcName}: " . count($parts) . " parts, passion={$passion}");
} else {
    RelationshipDynamics::log("CTX: No parts for {$npcName}: passion={$passion} stage={$stage}");
}
