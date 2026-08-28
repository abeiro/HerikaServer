<?php
/**
 * CHIM Relationship Manager
 *
 * Handles NPC relationship tracking, context injection, and AI output parsing.
 * See docs/RELATIONSHIP_SYSTEM_SPEC.md for full specification.
 *
 * Usage:
 *   - prerequest: RelationshipManager::buildContext($npcName, $nearbyNpcs)
 *   - postrequest: RelationshipManager::parseChanges($aiResponse, $npcName)
 *   - queries: RelationshipManager::getRelationship($npcName, $targetName)
 */

class RelationshipManager {

    // Valid relationship types (the "flavor" of the relationship)
    // Classic types
    const TYPES_CLASSIC = ['romantic', 'platonic', 'familial', 'professional', 'rival', 'enemy', 'neutral'];
    // Extended types for richer relationships
    const TYPES_EXTENDED = [
        'nemesis',      // Personal vendetta, obsessive hatred
        'estranged',    // Broken familial/platonic bond
        'transactional',// Business relationship, mercenary
        'protective',   // Guardian, mentor
        'indebted',     // Owes a debt of gratitude/obligation
        'fanatical',    // Blind loyalty (cultist, housecarl)
        'mentor',       // Teacher relationship
        'student',      // Learning from target
        'servant',      // Serves the target
        'client',       // Target is a customer
        'patron',       // Target supports/sponsors
        'crush',        // Unrequited romantic interest
        'ex',           // Former romantic partner
        'betrayed',     // Was betrayed by target
        'suspicious',   // Doesn't trust target's motives
        'admirer',      // Looks up to target
        'jealous',      // Envies the target
        'fearful',      // Afraid of target
        'obsessed',     // Unhealthy fixation
        'awed',         // Intimidated by power/status
        'contempt',     // Looks down on target
        'pitying',      // Feels sorry for target
        'grateful',     // Thankful for past help
        'curious',      // Wants to know more
        'dismissive',   // Considers target beneath notice
    ];
    // All valid types combined
    const TYPES = [
        'romantic', 'platonic', 'familial', 'professional', 'rival', 'enemy', 'neutral',
        'nemesis', 'estranged', 'transactional', 'protective', 'indebted', 'fanatical',
        'mentor', 'student', 'servant', 'client', 'patron', 'crush', 'ex',
        'betrayed', 'suspicious', 'admirer', 'jealous', 'fearful', 'obsessed',
        'awed', 'contempt', 'pitying', 'grateful', 'curious', 'dismissive'
    ];

    // Common model wording mapped onto canonical relationship types. These aliases
    // are inputs only; the canonical value is what is persisted.
    const TYPE_ALIASES = [
        'romance' => 'romantic',
        'marriage' => 'romantic',
        'married' => 'romantic',
        'lover' => 'romantic',
        'lovers' => 'romantic',
        'betrayal' => 'betrayed',
        'enemies' => 'enemy'
    ];

    // Tier boundaries - 11 tiers with BELL CURVE distribution
    // Extremes are HARD to reach (10 pts), center tiers are WIDE (25 pts)
    // Score => Label (checked from high to low)
    const TIERS = [
        91  => 'Bonded',       // +91 to +100: Unbreakable connection (10 pts)
        76  => 'Devoted',      // +76 to +90: Deep loyalty/love (15 pts)
        56  => 'Fond',         // +56 to +75: Genuine affection (20 pts)
        31  => 'Friendly',     // +31 to +55: Pleasant, helpful (25 pts)
        6   => 'Acquaintance', // +6 to +30: Recognize, polite nod (25 pts)
        -5  => 'Neutral',      // -5 to +5: Stranger/indifferent (11 pts)
        -30 => 'Wary',         // -30 to -6: Distrustful, suspicious (25 pts)
        -55 => 'Cold',         // -55 to -31: Unfriendly, dismissive (25 pts)
        -75 => 'Resentful',    // -75 to -56: Bitter, holds grudges (20 pts)
        -90 => 'Hateful',      // -90 to -76: Active malice (15 pts)
        -100 => 'Hostile'      // -100 to -91: Kill on sight (10 pts)
    ];

    // Emoji for each type (for UI display)
    const TYPE_EMOJI = [
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

    // Extra emoji pool for custom types (user can pick from these)
    const EMOJI_POOL = [
        // People & Faces
        '😀', '😃', '😄', '😁', '😆', '🥹', '😅', '😂', '🤣', '🥲', '☺️', '😊', '😇',
        '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😜',
        '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏',
        '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢',
        '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐',
        '😕', '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰',
        '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡',
        '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾',
        '🤖', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾',
        // Symbols & Objects
        '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞',
        '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯',
        '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐',
        '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️', '📴', '📳', '🈶', '🈚', '🈸',
        '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈹', '🈲', '🅰️',
        '🅱️', '🆎', '🆑', '🅾️', '🆘', '❌', '⭕', '🛑', '⛔', '📛', '🚫', '💯', '💢',
        '♨️', '🚷', '🚯', '🚳', '🚱', '🔞', '📵', '🚭', '❗', '❕', '❓', '❔', '‼️',
        '⁉️', '🔅', '🔆', '〽️', '⚠️', '🚸', '🔱', '⚜️', '🔰', '♻️', '✅', '🈯', '💹',
        '❇️', '✳️', '❎', '🌐', '💠', 'Ⓜ️', '🌀', '💤', '🏧', '🚾', '♿', '🅿️', '🛗',
        '🈳', '🈂️', '🛂', '🛃', '🛄', '🛅', '🚹', '🚺', '🚼', '⚧️', '🚻', '🚮', '🎦',
        '📶', '🈁', '🔣', 'ℹ️', '🔤', '🔡', '🔠', '🆖', '🆗', '🆙', '🆒', '🆕', '🆓',
        // Actions & Things
        '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙',
        '👈', '👉', '👆', '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏',
        '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💅', '🤳', '💪', '🦾', '🦿', '🦵', '🦶',
        '👂', '🦻', '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀', '👁️', '👅', '👄', '👶',
        // Objects
        '⚔️', '🗡️', '🔪', '🛡️', '🏹', '🪓', '🔧', '🔨', '⛏️', '🪚', '🔩', '⚙️', '🗜️',
        '⚖️', '🦯', '🔗', '⛓️', '🪝', '🧰', '🧲', '🪜', '💉', '🩸', '💊', '🩹', '🩺',
        '🔬', '🔭', '📡', '🛰️', '🚀', '🛸', '🪐', '⭐', '🌟', '✨', '💫', '🌙', '☀️',
        '🌈', '☁️', '⛈️', '🔥', '💧', '🌊', '💎', '💰', '💵', '💴', '💶', '💷', '💳',
        '🎁', '🎀', '🎊', '🎉', '🎈', '🧧', '🎯', '🎲', '🎮', '🎰', '🎪', '🎭', '🎨',
        '🖼️', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🎲',
        '📚', '📖', '📜', '📰', '🗞️', '📑', '🔖', '🏷️', '💼', '📁', '📂', '🗂️', '📋',
        '📌', '📍', '📎', '🖇️', '📏', '📐', '✂️', '🗃️', '🗄️', '🗑️', '🔒', '🔓', '🔏',
        '🔐', '🔑', '🗝️', '🔨', '🪓', '⛏️', '⚒️', '🛠️', '🗡️', '⚔️', '🔫', '🪃', '🏹'
    ];

    public static function shouldRunAutomaticEvaluation($chance = null, $roll = null) {
        if ($chance === null) {
            $chance = $GLOBALS['RELATIONSHIP_UPDATE_CHANCE'] ?? 50;
        }

        $chance = is_numeric($chance) ? max(0, min(100, (int)$chance)) : 50;
        if ($chance === 0) {
            return false;
        }
        if ($chance === 100) {
            return true;
        }

        $roll = $roll === null ? random_int(1, 100) : max(1, min(100, (int)$roll));
        return $roll <= $chance;
    }

    /**
     * Relationship storage uses the canonical key "Player" for the player route.
     * UI/prompts may surface the actual character name, but reads/writes must not
     * store that display name as a separate relationship target.
     */
    public static function normalizeTargetName($targetName) {
        $target = trim((string)$targetName);
        if ($target === '') {
            return $target;
        }

        $aliases = [
            'player',
            'the player',
            'player character',
            'the player character',
            'dragonborn',
            'the dragonborn',
            '#player_name#',
            '{player_name}'
        ];

        $playerName = trim((string)($GLOBALS['PLAYER_NAME'] ?? ''));
        if ($playerName !== '') {
            $aliases[] = strtolower($playerName);
        }

        if (in_array(strtolower($target), $aliases, true)) {
            return 'Player';
        }

        return $target;
    }

    /**
     * Canonicalize a model-supplied relationship type against a closed allow-list.
     *
     * Built-in types are always valid. Custom types are valid only when the caller
     * explicitly supplies types already created by the player. Returning null means
     * the model invented a type and the requested type change must be ignored.
     */
    public static function canonicalizeRelationshipType($type, $allowedCustomTypes = []) {
        if (!is_string($type)) {
            return null;
        }

        $normalized = strtolower(trim($type));
        if ($normalized === '') {
            return null;
        }

        $normalized = self::TYPE_ALIASES[$normalized] ?? $normalized;
        if (in_array($normalized, self::TYPES, true)) {
            return $normalized;
        }

        foreach ((array)$allowedCustomTypes as $customType) {
            $customType = strtolower(trim((string)$customType));
            if ($customType === '' || !preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $customType)) {
                continue;
            }
            $customType = self::TYPE_ALIASES[$customType] ?? $customType;
            if ($normalized === $customType) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Return only player-created custom types already present in relationship data.
     * The model may select one of these, but cannot turn a new word into a type.
     */
    public static function getCustomRelationshipTypes($relationships) {
        if (!is_array($relationships)) {
            return [];
        }

        $customTypes = [];
        foreach ($relationships as $relationship) {
            if (!is_array($relationship)) {
                continue;
            }
            $type = strtolower(trim((string)($relationship['type'] ?? '')));
            if ($type === '' || isset(self::TYPE_ALIASES[$type]) || in_array($type, self::TYPES, true)) {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $type)) {
                $customTypes[$type] = true;
            }
        }

        return array_keys($customTypes);
    }

    public static function normalizeRelationshipMap($relationships) {
        if (!is_array($relationships)) {
            return [];
        }

        $normalized = [];
        foreach ($relationships as $target => $rel) {
            if (!is_array($rel)) {
                continue;
            }

            $canonicalTarget = self::normalizeTargetName($target);
            if ($canonicalTarget === '') {
                continue;
            }

            // Repair casing and historical model aliases on every read. Unknown
            // single-word values remain untouched because they may be legitimate
            // player-created custom types.
            $storedType = strtolower(trim((string)($rel['type'] ?? 'neutral')));
            if ($storedType === '') {
                $storedType = 'neutral';
            }
            $rel['type'] = self::TYPE_ALIASES[$storedType] ?? $storedType;
            if (array_key_exists('custom_info', $rel)) {
                // AI paths normalize relationship maps before saving, so preserve
                // player-authored text exactly. UI writers own input trimming.
                $customInfo = is_scalar($rel['custom_info']) ? (string)$rel['custom_info'] : '';
                if ($customInfo === '') {
                    unset($rel['custom_info']);
                } else {
                    $rel['custom_info'] = $customInfo;
                }
            }

            if (!isset($normalized[$canonicalTarget])) {
                $normalized[$canonicalTarget] = $rel;
                continue;
            }

            $existing = $normalized[$canonicalTarget];
            $existingWeight = self::relationshipDataWeight($existing);
            $incomingWeight = self::relationshipDataWeight($rel);
            if ($incomingWeight > $existingWeight) {
                $preferred = $rel;
                if (!empty($existing['custom_info'])) {
                    $preferred['custom_info'] = $existing['custom_info'];
                }
                $normalized[$canonicalTarget] = $preferred;
            } elseif (empty($existing['custom_info']) && !empty($rel['custom_info'])) {
                $normalized[$canonicalTarget]['custom_info'] = $rel['custom_info'];
            }
        }

        return $normalized;
    }

    /**
     * Merge model-produced relationships without changing player-authored Custom Info.
     */
    public static function mergeAiRelationshipMap($existingRelationships, $incomingRelationships, $replaceExisting = false) {
        $existing = self::normalizeRelationshipMap($existingRelationships);
        $incoming = self::normalizeRelationshipMap($incomingRelationships);
        foreach ($incoming as &$relationship) {
            unset($relationship['custom_info']);
        }
        unset($relationship);
        $merged = $replaceExisting ? $incoming : $existing;

        if (!$replaceExisting) {
            foreach ($incoming as $target => $relationship) {
                if (!isset($merged[$target])) {
                    $merged[$target] = $relationship;
                }
            }
        }

        foreach ($existing as $target => $relationship) {
            $customInfo = $relationship['custom_info'] ?? '';
            if ($customInfo === '') {
                continue;
            }
            if (!isset($merged[$target])) {
                $merged[$target] = $relationship;
                continue;
            }
            $merged[$target]['custom_info'] = $customInfo;
        }

        return $merged;
    }

    private static function relationshipDataWeight($rel) {
        if (!is_array($rel)) {
            return 0;
        }

        $weight = abs((int)($rel['aff'] ?? 0));
        if (($rel['type'] ?? 'neutral') !== 'neutral') {
            $weight += 5;
        }
        foreach (['relation', 'note', 'best', 'worst'] as $field) {
            if (!empty($rel[$field])) {
                $weight += 2;
            }
        }
        return $weight;
    }

    /**
     * Get tier label from affinity score
     * PHP calculates this - AI never decides the label
     *
     * 11 Tiers with BELL CURVE distribution:
     * Extremes are hard to reach (10 pts), center tiers are wide (25 pts)
     *
     * +91 to +100: Bonded       (10 pts) - unbreakable
     * +76 to +90:  Devoted      (15 pts) - deep loyalty
     * +56 to +75:  Fond         (20 pts) - genuine affection
     * +31 to +55:  Friendly     (25 pts) - pleasant, helpful
     * +6 to +30:   Acquaintance (25 pts) - polite nod
     * -5 to +5:    Neutral      (11 pts) - stranger/indifferent
     * -30 to -6:   Wary         (25 pts) - distrustful
     * -55 to -31:  Cold         (25 pts) - unfriendly
     * -75 to -56:  Resentful    (20 pts) - bitter
     * -90 to -76:  Hateful      (15 pts) - active malice
     * -100 to -91: Hostile      (10 pts) - kill on sight
     */
    public static function getTierLabel($score) {
        if ($score >= 91) return "Bonded";
        if ($score >= 76) return "Devoted";
        if ($score >= 56) return "Fond";
        if ($score >= 31) return "Friendly";
        if ($score >= 6) return "Acquaintance";
        if ($score >= -5) return "Neutral";
        if ($score >= -30) return "Wary";
        if ($score >= -55) return "Cold";
        if ($score >= -75) return "Resentful";
        if ($score >= -90) return "Hateful";
        return "Hostile";
    }

    private static function truncateRelationshipEventReason($reason, $limit = 60) {
        $reason = trim(preg_replace('/\s+/u', ' ', (string)$reason));
        if ($reason === '') {
            return '';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($length > $limit) {
            $slice = function_exists('mb_substr')
                ? mb_substr($reason, 0, $limit - 1, 'UTF-8')
                : substr($reason, 0, $limit - 1);
            $lastSpace = strrpos($slice, ' ');
            if ($lastSpace !== false && $lastSpace >= (int)($limit * 0.6)) {
                $slice = substr($slice, 0, $lastSpace);
            }
            return rtrim($slice, " \t\n\r\0\x0B.,;:!?") . '…';
        }

        return rtrim($reason, " \t\n\r\0\x0B.,;:!?") . '.';
    }

    /**
     * Describe relationship changes between two persisted relationship snapshots.
     */
    public static function buildRelationshipChangeSummaries($npcName, $oldRelationships, $newRelationships) {
        $npcName = trim((string)$npcName);
        if ($npcName === '') {
            return [];
        }

        $oldRelationships = self::normalizeRelationshipMap(is_array($oldRelationships) ? $oldRelationships : []);
        $newRelationships = self::normalizeRelationshipMap(is_array($newRelationships) ? $newRelationships : []);
        $playerDisplayName = trim((string)($GLOBALS['PLAYER_NAME'] ?? 'Player'));
        if ($playerDisplayName === '' || strcasecmp($playerDisplayName, 'the Player') === 0) {
            $playerDisplayName = 'Player';
        }

        $events = [];
        $targets = array_values(array_unique(array_merge(
            array_keys($oldRelationships),
            array_keys($newRelationships)
        )));
        foreach ($targets as $target) {
            $oldRelationship = $oldRelationships[$target] ?? ['aff' => 0, 'type' => 'neutral'];
            $newRelationship = $newRelationships[$target] ?? ['aff' => 0, 'type' => 'neutral'];
            $oldAffinity = (int)($oldRelationship['aff'] ?? 0);
            $newAffinity = (int)($newRelationship['aff'] ?? 0);
            $oldType = strtolower(trim((string)($oldRelationship['type'] ?? 'neutral')));
            $newType = strtolower(trim((string)($newRelationship['type'] ?? 'neutral')));
            $affinityChanged = $oldAffinity !== $newAffinity;
            $typeChanged = $oldType !== $newType;
            if (!$affinityChanged && !$typeChanged) {
                continue;
            }

            $targetDisplayName = strcasecmp((string)$target, 'Player') === 0
                ? $playerDisplayName
                : trim((string)$target);
            if ($targetDisplayName === '') {
                continue;
            }

            $reason = '';
            $oldNote = trim((string)($oldRelationship['note'] ?? ''));
            $newNote = trim((string)($newRelationship['note'] ?? ''));
            if ($newNote !== '' && $newNote !== $oldNote) {
                $reason = self::truncateRelationshipEventReason($newNote);
            }

            $parts = [];
            $direction = 'neutral';
            if ($affinityChanged) {
                $increased = $newAffinity > $oldAffinity;
                $direction = $increased ? 'up' : 'down';
                $verb = $increased ? 'increased' : 'decreased';
                $oldTier = self::getTierLabel($oldAffinity);
                $newTier = self::getTierLabel($newAffinity);
                $scoreChange = "{$npcName}'s affinity toward {$targetDisplayName} {$verb} by "
                    . abs($newAffinity - $oldAffinity)
                    . " ({$oldAffinity} to {$newAffinity}";
                if ($oldTier !== $newTier) {
                    $scoreChange .= ", now {$newTier}";
                }
                $parts[] = $scoreChange . ')';
            }
            if ($typeChanged) {
                $typeChange = "the relationship changed from {$oldType} to {$newType}";
                $parts[] = $affinityChanged ? $typeChange : $npcName . "'s relationship toward {$targetDisplayName} changed from {$oldType} to {$newType}";
            }

            $text = count($parts) === 2
                ? $parts[0] . ' and ' . $parts[1] . '.'
                : $parts[0] . '.';
            if ($reason !== '') {
                $text .= ' ' . $reason;
            }

            $people = [];
            foreach ([$npcName, $targetDisplayName] as $person) {
                $person = trim(str_replace('|', '', $person));
                if ($person !== '' && !in_array($person, $people, true)) {
                    $people[] = $person;
                }
            }

            $events[] = [
                'target' => (string)$target,
                'direction' => $direction,
                'data' => $text,
                'people' => '|' . implode('|', $people) . '|',
            ];
        }

        return $events;
    }

    /**
     * Get tier reference prompt from database (custom or default)
     * This is injected into NPC context to help the conversation model
     * understand how to adjust behavior based on relationship tiers.
     */
    public static function getTierReferencePrompt() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        // Default fallback if database unavailable
        $default = "[TIER REFERENCE - Adjust behavior toward NPCs based on tier]\n" .
            "HOSTILE: Wants them dead, attack on sight\n" .
            "HATEFUL: Despises, refuses cooperation, threatens\n" .
            "RESENTFUL: Deep grudge, bitter, may sabotage\n" .
            "COLD: Dismissive, unhelpful, curt\n" .
            "WARY: Suspicious, guarded, reluctant\n" .
            "NEUTRAL: Polite stranger, no special treatment\n" .
            "ACQUAINTANCE: Recognizes, mildly helpful\n" .
            "FRIENDLY: Pleasant, helpful, enjoys company\n" .
            "FOND: Warm, protective, prioritizes them\n" .
            "DEVOTED: Deep loyalty, would sacrifice\n" .
            "BONDED: Absolute trust, would die for them";

        try {
            // Query the prompts table for custom or default tier reference
            $row = $GLOBALS["db"]->fetchOne(
                "SELECT COALESCE(custom_prompt, default_prompt) as prompt " .
                "FROM prompts WHERE prompt_key = 'rel_tier_reference'"
            );
            if ($row && !empty($row['prompt'])) {
                $cached = $row['prompt'];
                return $cached;
            }
        } catch (Exception $e) {
            error_log("[REL] Failed to load tier reference prompt: " . $e->getMessage());
        }

        $cached = $default;
        return $cached;
    }

    /**
     * Get emoji for tier
     */
    public static function getTierEmoji($tier) {
        return self::TIER_EMOJI[$tier] ?? '❓';
    }

    /**
     * Get emoji for type
     */
    public static function getTypeEmoji($type) {
        return self::TYPE_EMOJI[strtolower($type)] ?? '❓';
    }

    /**
     * Resolve an NPC row by display name: exact -> suffix-stripped -> case-insensitive ->
     * in-range actor bridge. Dialogue-parsed names can drift from the spelling that created
     * the row (rename mods). Never similarity-guesses; logs closest names on a miss.
     */
    public static function resolveNpcByName($npcName) {
        require_once __DIR__ . "/core/npc_master.class.php";
        $npcMaster = new NpcMaster();

        $raw = trim((string)$npcName);
        if ($raw === '' || strcasecmp($raw, 'The Narrator') === 0) {
            return null;
        }

        $npcData = $npcMaster->getByName($raw);
        if ($npcData) { return $npcData; }

        $clean = preg_replace('/\s*\((?:far away|too far away|busy|hostile|in combat|dead|disabled|unavailable)\)\s*$/iu', '', $raw);
        $clean = trim(preg_replace('/\s+/u', ' ', (string)$clean));
        if ($clean === '') { $clean = $raw; }
        if ($clean !== $raw) {
            $npcData = $npcMaster->getByName($clean);
            if ($npcData) { return $npcData; }
        }

        $npcData = $npcMaster->getByName(ucfirst(strtolower($clean)));
        if ($npcData) { return $npcData; }

        $escaped = $GLOBALS["db"]->escape($clean);
        $rows = $GLOBALS["db"]->fetchAll(
            "SELECT * FROM core_npc_master WHERE LOWER(npc_name) = LOWER('{$escaped}') AND npc_name <> 'The Narrator'
             ORDER BY gamets_last_updated DESC NULLS LAST, id DESC LIMIT 2"
        );
        if (!empty($rows)) {
            if (count($rows) > 1) {
                error_log("[REL] Name resolve: multiple case-insensitive matches for '{$clean}', using newest row '{$rows[0]['npc_name']}'");
            }
            return $rows[0];
        }

        // Bridge: compare against in-range actors ignoring case/punctuation/spacing drift
        if (function_exists('DataBeingsInCloseRange')) {
            try {
                $squash = function ($s) { return preg_replace('/[^a-z0-9]+/', '', strtolower((string)$s)); };
                $target = $squash($clean);
                $inRange = DataBeingsInCloseRange(true);
                $candidates = is_array($inRange) ? $inRange : explode('|', trim((string)$inRange, '| '));
                foreach ($candidates as $candidate) {
                    $candidate = trim((string)preg_replace('/\s*\([^)]*\)\s*$/u', '', (string)$candidate));
                    if ($candidate === '' || $target === '' || $squash($candidate) !== $target) { continue; }
                    $npcData = $npcMaster->getByName($candidate);
                    if ($npcData) {
                        error_log("[REL] Name resolve: matched '{$clean}' to in-range actor '{$candidate}'");
                        return $npcData;
                    }
                }
            } catch (Exception $e) {
                // resolver must never break the turn
            }
        }

        try {
            $all = $GLOBALS["db"]->fetchAll("SELECT npc_name FROM core_npc_master WHERE npc_name <> 'The Narrator'");
            $scored = [];
            foreach ($all as $r) {
                $scored[$r['npc_name']] = levenshtein(strtolower(substr($clean, 0, 60)), strtolower(substr((string)$r['npc_name'], 0, 60)));
            }
            asort($scored);
            $closest = implode("', '", array_slice(array_keys($scored), 0, 3));
            error_log("[REL] Name resolve FAILED for '{$clean}' - no row matches; closest names: '{$closest}'");
        } catch (Exception $e) {
            error_log("[REL] Name resolve FAILED for '{$clean}' - no row matches");
        }
        return null;
    }

    /**
     * Get NPC's relationship data from extended_data
     */
    public static function getRelationships($npcName) {
        $npcData = self::resolveNpcByName($npcName);

        if (!$npcData) {
            return [];
        }

        $extended = json_decode($npcData['extended_data'] ?? '{}', true) ?: [];
        return self::normalizeRelationshipMap($extended['relationships'] ?? []);
    }

    /**
     * Get specific relationship between NPC and target
     *
     * @return array ['aff' => int, 'type' => string, 'tier' => string]
     */
    public static function getRelationship($npcName, $targetName) {
        $rels = self::getRelationships($npcName);
        $targetName = self::normalizeTargetName($targetName);

        if (isset($rels[$targetName])) {
            $rel = $rels[$targetName];
            $rel['tier'] = self::getTierLabel($rel['aff'] ?? 0);
            return $rel;
        }

        // Default: neutral stranger
        return [
            'aff' => 0,
            'type' => 'neutral',
            'tier' => 'Neutral'
        ];
    }

    /**
     * Get relationship with Player specifically
     * Convenience method for common use case
     */
    public static function getPlayerRelationship($npcName) {
        return self::getRelationship($npcName, 'Player');
    }

    /**
     * Convert affinity score to legacy -4 to +4 rank
     * For backwards compatibility with existing code
     */
    public static function affinityToLegacyRank($affinity) {
        // Map -100..+100 to -4..+4
        // Each tier is ~25 points
        if ($affinity >= 88) return 4;      // Devoted (Lover)
        if ($affinity >= 63) return 3;      // Attached (Ally)
        if ($affinity >= 38) return 2;      // Fond (Confidant)
        if ($affinity >= 13) return 1;      // Warm (Friend)
        if ($affinity >= -12) return 0;     // Neutral (Acquaintance)
        if ($affinity >= -37) return -1;    // Wary (Rival)
        if ($affinity >= -62) return -2;    // Cold (Foe)
        if ($affinity >= -87) return -3;    // Resentful (Enemy)
        return -4;                          // Hostile (Archnemesis)
    }

    /**
     * Build relationship context block for AI injection
     * Only includes Player + nearby NPCs to save tokens
     *
     * When RELLLM_CONNECTOR is set:
     *   - Shows only tier labels (Fond, Wary) - token efficient
     *   - Conversation model doesn't need to know exact numbers
     *
     * When RELLLM_CONNECTOR is not set:
     *   - Shows numbers too for the #REL: command system
     *
     * @param string $npcName The speaking NPC
     * @param array $nearbyNpcs Names of NPCs in the scene
     * @return string Context block to inject
     */
    public static function buildContext($npcName, $nearbyNpcs = []) {
        $rels = self::getRelationships($npcName);

        // Check if using dedicated RelationshipLLM (tier-only mode)
        $tierOnly = !empty($GLOBALS['RELLLM_CONNECTOR']) && $GLOBALS['RELLLM_CONNECTOR'] > 0;

        $lines = [];

        // Add behavioral instructions so the conversation model knows how to use these tiers
        if ($tierOnly) {
            // Get tier reference prompt from database (custom or default)
            $tierPrompt = self::getTierReferencePrompt();
            $tierLines = explode("\n", $tierPrompt);
            foreach ($tierLines as $tierLine) {
                $lines[] = trim($tierLine);
            }
            $lines[] = "";
            $lines[] = self::buildRelationshipHeading($npcName);
        } else {
            $lines[] = self::buildRelationshipHeading($npcName);
        }

        // Always include Player - use actual player name for context display
        $playerRel = $rels['Player'] ?? ['aff' => 0, 'type' => 'neutral'];
        $playerAff = $playerRel['aff'] ?? 0;
        $playerType = ucfirst($playerRel['type'] ?? 'neutral');
        $playerTier = self::getTierLabel($playerAff);
        $playerRelation = $playerRel['relation'] ?? '';
        $playerNote = $playerRel['note'] ?? '';
        $playerBest = $playerRel['best'] ?? '';
        $playerWorst = $playerRel['worst'] ?? '';

        // Get actual player name for display (falls back to "Player" if not set)
        $playerDisplayName = $GLOBALS['PLAYER_NAME'] ?? 'Player';
        if (empty($playerDisplayName) || $playerDisplayName === 'the Player') {
            $playerDisplayName = 'Player';
        }

        // Build type/relation string: "Familial/son" or just "Familial"
        $typeStr = $playerType;
        if (!empty($playerRelation)) {
            $typeStr .= "/" . $playerRelation;
        }

        if ($tierOnly) {
            // Token-efficient: tier, type/relation, and events
            $playerLine = sprintf("%s: %s (%s)", $playerDisplayName, $playerTier, $typeStr);
            $playerLine .= self::formatEventNotes($playerWorst, $playerBest, $playerNote);
            $lines[] = $playerLine;
        } else {
            // Include numbers for #REL: command system
            $playerLine = sprintf("%s: %+d (%s, %s)", $playerDisplayName, $playerAff, $playerTier, $typeStr);
            $playerLine .= self::formatEventNotes($playerWorst, $playerBest, $playerNote);
            $lines[] = $playerLine;
        }

        // Add nearby NPCs only
        foreach ($nearbyNpcs as $target) {
            $target = trim($target);
            if (empty($target) || strtolower($target) === 'player') continue;

            if (isset($rels[$target])) {
                $r = $rels[$target];
                $aff = $r['aff'] ?? 0;
                $type = ucfirst($r['type'] ?? 'neutral');
                $tier = self::getTierLabel($aff);
                $relation = $r['relation'] ?? '';
                $note = $r['note'] ?? '';
                $best = $r['best'] ?? '';
                $worst = $r['worst'] ?? '';

                // Build type/relation string
                $typeStr = $type;
                if (!empty($relation)) {
                    $typeStr .= "/" . $relation;
                }

                if ($tierOnly) {
                    $line = sprintf("%s: %s (%s)", $target, $tier, $typeStr);
                } else {
                    $line = sprintf("%s: %+d (%s, %s)", $target, $aff, $tier, $typeStr);
                }

                $line .= self::formatEventNotes($worst, $best, $note);
                $lines[] = $line;
            }
            // If no existing relationship, don't include - they're strangers
        }

        return implode("\n", $lines);
    }

    private static function buildRelationshipHeading($npcName) {
        $npcName = trim((string)$npcName);
        if ($npcName === '') {
            return "[RELATIONSHIPS]";
        }

        $suffix = preg_match('/s$/i', $npcName) ? "'" : "'s";
        return "[" . $npcName . $suffix . " RELATIONSHIPS]";
    }

    /**
     * Format event notes for context injection
     * Output: " - worst; best | note" (only includes non-empty fields)
     *
     * Examples:
     *   - killed brother; saved life | gave wine
     *   - killed brother | gave wine
     *   - saved life | gave wine
     *   - gave wine
     *   - killed brother; saved life
     */
    private static function formatEventNotes($worst, $best, $note) {
        $parts = [];

        // Major events (worst and best) come first, separated by semicolon
        $majorEvents = [];
        if (!empty($worst)) {
            $majorEvents[] = $worst;
        }
        if (!empty($best)) {
            $majorEvents[] = $best;
        }

        if (!empty($majorEvents)) {
            $parts[] = implode('; ', $majorEvents);
        }

        // Minor recent interaction comes after pipe separator
        if (!empty($note)) {
            $parts[] = $note;
        }

        if (empty($parts)) {
            return '';
        }

        // Format: " - major_events | minor_note" or " - major_events" or " - minor_note"
        if (count($parts) === 2) {
            return " - " . $parts[0] . " | " . $parts[1];
        } else {
            return " - " . $parts[0];
        }
    }

    /**
     * Parse AI output for relationship changes and apply them
     *
     * @param string $aiResponse Raw AI response
     * @param string $npcName The speaking NPC
     * @return string Cleaned response with commands stripped
     */
    public static function parseChanges($aiResponse, $npcName) {
        require_once __DIR__ . "/core/npc_master.class.php";
        $npcMaster = new NpcMaster();
        $npcData = self::resolveNpcByName($npcName);

        if (!$npcData) {
            // Can't update relationships for unknown NPC
            return preg_replace('/#(REL|TYPE):[^#]+#/', '', $aiResponse);
        }

        $extended = json_decode($npcData['extended_data'] ?? '{}', true) ?: [];
        $rels = self::normalizeRelationshipMap($extended['relationships'] ?? []);
        $changed = false;

        // Parse affinity changes: #REL:Target=+5# or #REL:Target=-10#
        if (preg_match_all('/#REL:([^=]+)=([+-]?\d+)#/', $aiResponse, $matches)) {
            foreach ($matches[1] as $i => $target) {
                $target = self::normalizeTargetName($target);
                if (in_array(strtolower($target), ['the narrator', 'narrator'], true)) continue; // never track the narrator as a relationship
                $delta = (int)$matches[2][$i];

                // Initialize if doesn't exist
                if (!isset($rels[$target])) {
                    $rels[$target] = ['aff' => 0, 'type' => 'neutral'];
                }

                // Apply delta with bounds
                $oldAff = $rels[$target]['aff'];
                $rels[$target]['aff'] = max(-100, min(100, $oldAff + $delta));

                error_log("[REL] $npcName -> $target: " . sprintf("%+d", $delta) .
                          " (was $oldAff, now " . $rels[$target]['aff'] . ")");
                $changed = true;
            }
        }

        // Parse type changes: #TYPE:Target=Romantic#
        // The model may select a built-in type or an existing player-created custom
        // type. It may never create a new type merely by emitting a new word.
        $allowedCustomTypes = self::getCustomRelationshipTypes($rels);
        if (preg_match_all('/#TYPE:([^=]+)=([a-zA-Z][a-zA-Z0-9_-]{0,49})#/', $aiResponse, $matches)) {
            foreach ($matches[1] as $i => $target) {
                $target = self::normalizeTargetName($target);
                if (in_array(strtolower($target), ['the narrator', 'narrator'], true)) continue; // never track the narrator as a relationship
                $rawType = trim($matches[2][$i]);
                $newType = self::canonicalizeRelationshipType($rawType, $allowedCustomTypes);

                if ($newType === null) {
                    error_log("[REL] Rejected invented relationship type '$rawType' for $npcName -> $target");
                    continue;
                }

                if (!isset($rels[$target])) {
                    $rels[$target] = ['aff' => 0, 'type' => 'neutral'];
                }
                $oldType = $rels[$target]['type'];
                $rels[$target]['type'] = $newType;

                error_log("[REL] $npcName -> $target: type $oldType -> $newType");
                $changed = true;
            }
        }

        // Save if changed
        if ($changed) {
            $extended['relationships'] = $rels;
            $result = chimRunWithRelationshipExtendedDataWrite(function () use ($npcMaster, $npcData, $extended) {
                return $npcMaster->updateByArray([
                    'id' => $npcData['id'],
                    'extended_data' => json_encode($extended, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ]);
            });
            if ($result !== false && function_exists('chimRelationshipTimelineStamp')) {
                chimRelationshipTimelineStamp($npcData['id']);
            }
        }

        // Strip commands before TTS
        return preg_replace('/#(REL|TYPE):[^#]+#/', '', $aiResponse);
    }

    /**
     * Set relationship directly (for initialization or admin)
     */
    public static function setRelationship($npcName, $targetName, $affinity, $type = null) {
        $targetName = self::normalizeTargetName($targetName);
        require_once __DIR__ . "/core/npc_master.class.php";
        $npcMaster = new NpcMaster();
        $npcData = self::resolveNpcByName($npcName);

        if (!$npcData) {
            error_log("[REL] Cannot set relationship - NPC not found: $npcName");
            return false;
        }

        $extended = json_decode($npcData['extended_data'] ?? '{}', true) ?: [];
        $rels = self::normalizeRelationshipMap($extended['relationships'] ?? []);

        // Initialize or update
        if (!isset($rels[$targetName])) {
            $rels[$targetName] = ['aff' => 0, 'type' => 'neutral'];
        }

        $rels[$targetName]['aff'] = max(-100, min(100, (int)$affinity));

        // Direct/admin writes may create custom types, but known model aliases are
        // still stored canonically ("romance" becomes "romantic").
        if ($type !== null && is_string($type) && strlen($type) > 0 && strlen($type) <= 50) {
            $normalizedType = strtolower(trim($type));
            $rels[$targetName]['type'] = self::TYPE_ALIASES[$normalizedType] ?? $normalizedType;
        }

        $extended['relationships'] = $rels;
        $result = chimRunWithRelationshipExtendedDataWrite(function () use ($npcMaster, $npcData, $extended) {
            return $npcMaster->updateByArray([
                'id' => $npcData['id'],
                'extended_data' => json_encode($extended, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ]);
        });
        if ($result !== false && function_exists('chimRelationshipTimelineStamp')) {
            chimRelationshipTimelineStamp($npcData['id']);
        }

        error_log("[REL] Set $npcName -> $targetName: " . $rels[$targetName]['aff'] .
                  " (" . $rels[$targetName]['type'] . ")");

        return true;
    }

    /**
     * Adjust relationship by delta (convenience method)
     */
    public static function adjustRelationship($npcName, $targetName, $delta) {
        $current = self::getRelationship($npcName, $targetName);
        $newAff = $current['aff'] + $delta;
        return self::setRelationship($npcName, $targetName, $newAff, $current['type']);
    }

    /**
     * Get the system prompt addition for relationship tracking
     */
    public static function getSystemPromptAddition() {
        $prompt = <<<'PROMPT'
[SYSTEM: RELATIONSHIPS]
You have relationships with others defined by a Score (-100 to +100) and a Type.
Levels: Hostile(-100) < Resentful < Cold < Wary < Neutral(0) < Warm < Fond < Attached < Devoted(+100).

COMMANDS (use when feelings change - scale to the significance of the action):
- To adjust affinity: #REL:Name=+/-Amount#
- To change relationship type: #TYPE:Name=NewType# where NewType is EXACTLY one of the types below. Never invent a type.

Types: {RELATIONSHIP_TYPES}

SCALE YOUR RESPONSE TO THE ACTION:
- Minor: +/-5 to 10 (small kindness, rude comment)
- Moderate: +/-15 to 25 (meaningful gift, insult, helping in danger)
- Major: +/-30 to 50 (saving life, betrayal, violence against you)
- Extreme: +/-60 to 80 (killing loved one, life-changing sacrifice)

EXAMPLES:
Small kindness: "Thank you for the flowers." #REL:Player=+5#
Saved your life: "You... you saved me! I won't forget this." #REL:Player=+40#
Attacked you: "You dare strike me?!" #REL:Player=-35# #TYPE:Player=Rival#
Killed your friend: "MURDERER! I will NEVER forgive you!" #REL:Player=-70#
PROMPT;

        return str_replace('{RELATIONSHIP_TYPES}', implode(', ', self::TYPES), $prompt);
    }

    /**
     * Build relationship context for the Rolemaster/Director
     * Called when rolemaster activates to provide relationship awareness
     *
     * Returns prose descriptions of how characters feel about each other -
     * no scores, just narrative the director can use to guide the scene.
     *
     * @param array $npcsInScene Names of NPCs currently in scene
     * @param array $mentionedNpcs Names of NPCs mentioned in dialogue (optional)
     * @return string Prose context block for director
     */
    public static function buildDirectorContext($npcsInScene = [], $mentionedNpcs = []) {
        // Combine and dedupe NPC lists
        $allNpcs = array_unique(array_merge($npcsInScene, $mentionedNpcs));
        $allNpcs = array_filter($allNpcs, function($n) {
            $n = trim($n);
            return !empty($n) && strtolower($n) !== 'player';
        });

        if (empty($allNpcs)) {
            return "";
        }

        $descriptions = [];

        // Clean NPC names (remove status tags)
        $cleanNpcs = [];
        foreach ($allNpcs as $npc) {
            $clean = trim(preg_replace('/\s*\([^)]+\)/', '', $npc));
            if (!empty($clean)) {
                $cleanNpcs[] = $clean;
            }
        }

        // For each NPC, describe their feelings
        foreach ($cleanNpcs as $npc) {
            $rels = self::getRelationships($npc);
            if (empty($rels)) continue;

            $npcDescriptions = [];

            // How this NPC feels about the Player
            if (isset($rels['Player'])) {
                $desc = self::describeFeeling($npc, 'Player', $rels['Player']);
                if ($desc) $npcDescriptions[] = $desc;
            }

            // How this NPC feels about other NPCs in scene
            foreach ($cleanNpcs as $otherNpc) {
                if ($otherNpc === $npc) continue;
                if (isset($rels[$otherNpc])) {
                    $desc = self::describeFeeling($npc, $otherNpc, $rels[$otherNpc]);
                    if ($desc) $npcDescriptions[] = $desc;
                }
            }

            // Subject/topic affinities (anything that's not an NPC name or Player)
            $knownNames = array_map('strtolower', $cleanNpcs);
            $knownNames[] = 'player';

            foreach ($rels as $target => $r) {
                if (in_array(strtolower($target), $knownNames)) continue;
                // This is a subject/topic affinity
                $desc = self::describeSubjectFeeling($npc, $target, $r);
                if ($desc) $npcDescriptions[] = $desc;
            }

            if (!empty($npcDescriptions)) {
                $descriptions = array_merge($descriptions, $npcDescriptions);
            }
        }

        if (empty($descriptions)) {
            return "";
        }

        $lines = [];
        $lines[] = "[HOW CHARACTERS FEEL]";
        $lines = array_merge($lines, $descriptions);
        $lines[] = "";
        $lines[] = "Director: Ensure your instructions respect these feelings. Don't direct characters to act against their strong emotions.";

        return implode("\n", $lines);
    }

    /**
     * Describe how one character feels about another in prose
     */
    private static function describeFeeling($npc, $target, $relData) {
        $aff = $relData['aff'] ?? 0;
        $type = $relData['type'] ?? 'neutral';
        $worst = $relData['worst'] ?? '';
        $best = $relData['best'] ?? '';

        // Skip truly neutral relationships
        if ($aff >= -5 && $aff <= 5 && $type === 'neutral') {
            return null;
        }

        // Build the feeling description based on affinity AND type
        $feeling = self::getEmotionalDescription($aff, $type);

        $desc = "- {$npc} {$feeling} {$target}";

        // Add the WHY - the significant events that shaped this feeling
        $reasons = [];
        if (!empty($worst)) $reasons[] = $worst;
        if (!empty($best)) $reasons[] = $best;

        if (!empty($reasons)) {
            $desc .= " because " . implode(" and ", $reasons);
        }

        return $desc;
    }

    /**
     * Get emotional description based on affinity and relationship type
     */
    private static function getEmotionalDescription($aff, $type) {
        // High positive affinity
        if ($aff >= 76) {
            switch ($type) {
                case 'romantic': return "is deeply in love with";
                case 'familial': return "has an unbreakable family bond with";
                case 'nemesis':
                case 'enemy': return "is completely obsessed with - can't stop thinking about";
                case 'rival': return "has immense respect for as a worthy opponent -";
                case 'fearful': return "is utterly dependent on despite fearing";
                default: return "is utterly devoted to";
            }
        }
        // Moderate-high positive
        elseif ($aff >= 56) {
            switch ($type) {
                case 'romantic': return "has strong romantic feelings for";
                case 'familial': return "deeply loves and protects";
                case 'enemy':
                case 'nemesis': return "has a complex obsession with";
                case 'rival': return "genuinely respects and enjoys competing with";
                default: return "is genuinely fond of";
            }
        }
        // Positive
        elseif ($aff >= 31) {
            switch ($type) {
                case 'romantic': return "is attracted to";
                case 'rival': return "enjoys friendly competition with";
                default: return "is friendly toward";
            }
        }
        // Slight positive
        elseif ($aff >= 6) {
            return "is slightly positive toward";
        }
        // Neutral
        elseif ($aff >= -5) {
            return "is indifferent to";
        }
        // Slight negative
        elseif ($aff >= -30) {
            switch ($type) {
                case 'suspicious': return "doesn't trust";
                case 'fearful': return "is nervous around";
                default: return "is wary of";
            }
        }
        // Moderate negative
        elseif ($aff >= -55) {
            switch ($type) {
                case 'contempt': return "looks down on";
                case 'jealous': return "is bitterly jealous of";
                default: return "is cold and unfriendly toward";
            }
        }
        // Strong negative
        elseif ($aff >= -75) {
            switch ($type) {
                case 'betrayed': return "feels deeply betrayed by";
                default: return "resents and holds grudges against";
            }
        }
        // Very strong negative
        elseif ($aff >= -90) {
            return "actively hates";
        }
        // Extreme negative
        else {
            return "is hostile toward and would attack";
        }
    }

    /**
     * Describe how a character feels about a subject/topic
     */
    private static function describeSubjectFeeling($npc, $subject, $relData) {
        $aff = $relData['aff'] ?? 0;

        // Skip neutral
        if ($aff >= -5 && $aff <= 5) {
            return null;
        }

        if ($aff >= 60) {
            $feeling = "strongly values and supports";
        } elseif ($aff >= 30) {
            $feeling = "favors";
        } elseif ($aff >= 6) {
            $feeling = "is somewhat positive toward";
        } elseif ($aff >= -30) {
            $feeling = "dislikes";
        } elseif ($aff >= -60) {
            $feeling = "strongly opposes";
        } else {
            $feeling = "despises and would act against";
        }

        return "- {$npc} {$feeling} \"{$subject}\"";
    }

    /**
     * Import OHGMA relationship text into structured data
     * Parses format: "* Name - Description of relationship"
     */
    public static function importOhgmaRelationships($npcName, $relationshipText) {
        $relationships = [];

        // Parse "* Name - Description" format
        if (preg_match_all('/\*\s*([^-\n]+)\s*-\s*([^\n]+)/m', $relationshipText, $matches)) {
            foreach ($matches[1] as $i => $targetName) {
                $targetName = trim($targetName);
                $description = strtolower(trim($matches[2][$i]));

                // Infer affinity and type from description keywords
                $parsed = self::parseDescriptionToRelationship($description);

                $relationships[$targetName] = [
                    'aff' => $parsed['aff'],
                    'type' => $parsed['type']
                ];
            }
        }

        return $relationships;
    }

    /**
     * Parse relationship description text to affinity/type
     */
    private static function parseDescriptionToRelationship($description) {
        $aff = 0;
        $type = 'neutral';

        // Romantic indicators
        if (preg_match('/(lover|spouse|husband|wife|beloved|romantic|love|passion|desire)/i', $description)) {
            $type = 'romantic';
            $aff = 75;
        }
        // Family indicators
        elseif (preg_match('/(father|mother|son|daughter|brother|sister|family|sibling|parent|child)/i', $description)) {
            $type = 'familial';
            $aff = 60;
        }
        // Professional indicators
        elseif (preg_match('/(colleague|guild|faction|employer|employee|business|trade|work)/i', $description)) {
            $type = 'professional';
            $aff = 20;
        }
        // Rivalry indicators
        elseif (preg_match('/(rival|enemy|foe|hate|despise|loathe|enemy|nemesis)/i', $description)) {
            $type = 'rival';
            $aff = -50;
        }
        // Default to platonic for other relationships
        else {
            $type = 'platonic';
        }

        // Adjust affinity based on sentiment keywords
        if (preg_match('/(devoted|unconditional|deep bond|inseparable)/i', $description)) {
            $aff = max($aff, 90);
        } elseif (preg_match('/(close|trusted|loyal|dear|best friend)/i', $description)) {
            $aff = max($aff, 70);
        } elseif (preg_match('/(friend|companion|ally|respects?|admires?)/i', $description)) {
            $aff = max($aff, 50);
        } elseif (preg_match('/(acquaintance|knows?|met)/i', $description)) {
            $aff = max($aff, 10);
        } elseif (preg_match('/(distrust|suspicious|wary|cautious)/i', $description)) {
            $aff = min($aff, -20);
        } elseif (preg_match('/(dislikes?|resent|annoy|irritate)/i', $description)) {
            $aff = min($aff, -40);
        } elseif (preg_match('/(hate|hostile|enemy|despise)/i', $description)) {
            $aff = min($aff, -70);
        }

        return ['aff' => $aff, 'type' => $type];
    }
}
